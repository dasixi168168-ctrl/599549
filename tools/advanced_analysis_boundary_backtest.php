<?php
declare(strict_types=1);

use App\Services\ForecastAdvancedAnalysisService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    echo 'Not Found';
    exit(1);
}

$root = dirname(__DIR__);
require $root . '/bootstrap/app.php';

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$options = advanced_analysis_boundary_options($argv);
if (!empty($options['help'])) {
    advanced_analysis_boundary_usage();
    exit(0);
}

$service = new ForecastAdvancedAnalysisService(app());
$sources = advanced_analysis_boundary_sources();
$regions = $options['region'] === 'all' ? array_keys($sources) : array($options['region']);
$failed = false;

$result = array(
    'engine_version' => ForecastAdvancedAnalysisService::VERSION,
    'generated_at' => date('c'),
    'read_only' => true,
    'source_mode' => 'lottery_draws by region and allowed note list',
    'profiles' => array(
        'minimum' => 'history=1, training=8, analysis_limit=8',
        'critical' => 'history=8, training=8, analysis_limit=16',
        'large' => 'history=min(160,total-30), training=30, analysis_limit=min(240,total)',
    ),
    'regions' => array(),
);

try {
    foreach ($regions as $region) {
        $notes = $sources[$region];
        $summary = advanced_analysis_boundary_count($region, $notes);
        $profiles = advanced_analysis_boundary_profiles((int) ($summary['total'] ?? 0));
        $regionResult = array(
            'available' => $summary,
            'notes' => $notes,
            'runs' => array(),
        );

        foreach ($profiles as $name => $profile) {
            $run = advanced_analysis_boundary_run($service, $region, $notes, $name, $profile);
            if (!empty($run['status']) && $run['status'] !== 'ok') {
                $failed = true;
            }
            $regionResult['runs'][$name] = $run;
        }

        $result['regions'][$region] = $regionResult;
    }
} catch (Throwable $exception) {
    $failed = true;
    $result['error'] = array(
        'type' => get_class($exception),
        'message' => $exception->getMessage(),
    );
}

restore_error_handler();

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
exit($failed ? 1 : 0);

function advanced_analysis_boundary_usage()
{
    echo "Usage: php80 tools/advanced_analysis_boundary_backtest.php [--region=all|macau|hongkong]" . PHP_EOL;
    echo PHP_EOL;
    echo "Runs a read-only advanced_analysis boundary backtest against lottery_draws." . PHP_EOL;
    echo "Default: --region=all" . PHP_EOL;
}

function advanced_analysis_boundary_options(array $argv)
{
    $options = array(
        'region' => 'all',
        'help' => false,
    );

    foreach (array_slice($argv, 1) as $arg) {
        if ($arg === '-h' || $arg === '--help') {
            $options['help'] = true;
            continue;
        }
        if (strpos($arg, '--region=') === 0) {
            $region = substr($arg, strlen('--region='));
            if (!in_array($region, array('all', 'macau', 'hongkong'), true)) {
                throw new InvalidArgumentException('Invalid --region value: ' . $region);
            }
            $options['region'] = $region;
            continue;
        }

        throw new InvalidArgumentException('Unknown option: ' . $arg);
    }

    return $options;
}

function advanced_analysis_boundary_sources()
{
    $prediction = app()->prediction();

    return array(
        'macau' => array_values($prediction->advancedForecastSourceNotes('macau')),
        'hongkong' => array_values($prediction->advancedForecastSourceNotes('hongkong')),
    );
}

function advanced_analysis_boundary_profiles($total)
{
    $total = max(0, (int) $total);
    $largeHistory = $total > 30 ? min(160, $total - 30) : 1;

    return array(
        'minimum' => array('history' => 1, 'training' => 8, 'analysis_limit' => 8),
        'critical' => array('history' => 8, 'training' => 8, 'analysis_limit' => 16),
        'large' => array('history' => $largeHistory, 'training' => 30, 'analysis_limit' => min(240, max(1, $total))),
    );
}

function advanced_analysis_boundary_count($region, array $notes)
{
    $query = advanced_analysis_boundary_note_query(
        'SELECT COUNT(*) AS total,
                MIN(draw_date) AS min_date,
                MAX(draw_date) AS max_date,
                MIN(issue_no) AS min_issue,
                MAX(issue_no) AS max_issue
         FROM lottery_draws
         WHERE region = :region
           AND note IN (%s)',
        $region,
        $notes
    );

    return app()->db()->fetch($query['sql'], $query['params']);
}

function advanced_analysis_boundary_fetch($region, array $notes, $limit)
{
    $query = advanced_analysis_boundary_note_query(
        'SELECT *
         FROM lottery_draws
         WHERE region = :region
           AND note IN (%s)
         ORDER BY CAST(issue_no AS UNSIGNED) DESC, draw_date DESC, id DESC
         LIMIT ' . (int) $limit,
        $region,
        $notes
    );

    return app()->db()->fetchAll($query['sql'], $query['params']);
}

function advanced_analysis_boundary_note_query($sqlTemplate, $region, array $notes)
{
    $params = array('region' => $region);
    $holders = array();
    foreach ($notes as $index => $note) {
        $key = 'note_' . (int) $index;
        $holders[] = ':' . $key;
        $params[$key] = (string) $note;
    }

    return array(
        'sql' => sprintf($sqlTemplate, implode(', ', $holders)),
        'params' => $params,
    );
}

function advanced_analysis_boundary_run(ForecastAdvancedAnalysisService $service, $region, array $notes, $name, array $profile)
{
    $history = max(1, (int) $profile['history']);
    $training = max(8, (int) $profile['training']);
    $analysisLimit = max(1, (int) $profile['analysis_limit']);
    $requiredDraws = $history + $training;
    $limit = max($analysisLimit, $requiredDraws);
    $draws = advanced_analysis_boundary_fetch($region, $notes, $limit);

    if (count($draws) < $requiredDraws) {
        return array(
            'status' => 'skipped',
            'reason' => 'insufficient_draws',
            'requested' => $profile,
            'loaded_draws' => count($draws),
            'required_draws' => $requiredDraws,
        );
    }

    if (function_exists('memory_reset_peak_usage')) {
        memory_reset_peak_usage();
    }

    $started = microtime(true);
    $analysis = $service->buildAdvancedForecast(
        $region,
        array_slice($draws, 0, $analysisLimit),
        array(),
        array(),
        'boundary-' . $name,
        array('profile' => $name),
        0.0
    );
    $backtest = $service->backtest($draws, $history, $training);
    $elapsedMs = round((microtime(true) - $started) * 1000, 2);

    return array(
        'status' => 'ok',
        'requested' => $profile,
        'loaded_draws' => count($draws),
        'analysis_draw_count' => (int) ($analysis['analysis_draw_count'] ?? 0),
        'analysis_confidence' => (float) ($analysis['confidence'] ?? 0.0),
        'tested_issues' => (int) ($backtest['tested_issues'] ?? 0),
        'hit_rates' => (array) ($backtest['hit_rates'] ?? array()),
        'hits' => (array) ($backtest['hits'] ?? array()),
        'elapsed_ms' => $elapsedMs,
        'peak_memory_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        'sample' => advanced_analysis_boundary_sample($backtest),
    );
}

function advanced_analysis_boundary_sample(array $backtest)
{
    $items = isset($backtest['items']) && is_array($backtest['items']) ? $backtest['items'] : array();
    if ($items === array()) {
        return array(
            'first_item' => null,
            'last_item' => null,
        );
    }

    return array(
        'first_item' => $items[0],
        'last_item' => $items[count($items) - 1],
    );
}
