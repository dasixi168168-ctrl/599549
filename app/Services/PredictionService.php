<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Security;
use RuntimeException;

class PredictionService extends Service
{
    protected $forecastParticipationSchemaReady = false;
    protected $latestHomepageDrawRuntimeCache = array();
    protected $latestAvailableDrawsRuntimeCache = array();
    protected $forecastBaseRuntimeCache = array();
    protected $latestForecastPredictionNumbersRuntimeCache = array();
    protected $forecastRecommendedCombinationsRuntimeCache = array();
    protected $tableExistsRuntimeCache = array();

    protected function macauLiveCacheKey()
    {
        return 'remote_macau_live2_draw';
    }

    protected function macauRealtimeStateKey($date = null)
    {
        $date = $date ?: date('Ymd');
        return 'macau_live2_poll_state_' . $date;
    }

    protected function macauRealtimeTickKey($date = null)
    {
        $date = $date ?: date('Ymd');
        return 'macau_live2_poll_tick_' . $date;
    }

    protected function macauRealtimePollStart()
    {
        return '21:32:00';
    }

    protected function macauRealtimePollEnd()
    {
        return '21:40:59';
    }

    protected function macauRealtimePollIntervalSeconds()
    {
        return 8;
    }

    protected function macauLiveCacheTtlSeconds()
    {
        return 180;
    }

    protected function configuredForecastApiUrl($settingKey, $defaultUrl, $requiredPlaceholderCount = 0)
    {
        $url = (string) $defaultUrl;
        try {
            $configuredUrl = trim((string) $this->app->settings()->get((string) $settingKey, (string) $defaultUrl));
            if ($configuredUrl !== '') {
                $url = $configuredUrl;
            }
        } catch (\Throwable $exception) {
            $url = (string) $defaultUrl;
        }

        if (substr_count($url, '%d') !== (int) $requiredPlaceholderCount) {
            return (string) $defaultUrl;
        }

        $testUrl = str_replace('%d', '1', $url);
        $scheme = parse_url($testUrl, PHP_URL_SCHEME);
        if (!in_array($scheme, array('http', 'https'), true) || !filter_var($testUrl, FILTER_VALIDATE_URL)) {
            return (string) $defaultUrl;
        }

        return $url;
    }

    protected function macauApiUrl()
    {
        return $this->configuredForecastApiUrl(
            'forecast.macau_live_api_url',
            'https://www.macaumarksix.com/api/live2'
        );
    }

    protected function hongkongLiveCacheKey()
    {
        return 'remote_hkjc_draw';
    }

    protected function hongkongApiUrl()
    {
        return $this->configuredForecastApiUrl(
            'forecast.hongkong_live_api_url',
            'https://api.macaumarksix.com/api/hkjc.com'
        );
    }

    protected function hongkongRealtimeStateKey($date = null)
    {
        $date = $date ?: date('Ymd');
        return 'hongkong_hkjc_poll_state_' . $date;
    }

    protected function hongkongRealtimeTickKey($date = null)
    {
        $date = $date ?: date('Ymd');
        return 'hongkong_hkjc_poll_tick_' . $date;
    }

    protected function hongkongHistorySyncTickKey($date = null)
    {
        $date = $date ?: date('Ymd');
        return 'hongkong_history_sync_tick_' . $date;
    }

    protected function hongkongRealtimePollIntervalSeconds()
    {
        return 8;
    }

    protected function hongkongHistorySyncIntervalSeconds()
    {
        return 900;
    }

    protected function hongkongHistoryUrl($page = 1, $perPage = 50)
    {
        return sprintf(
            $this->configuredForecastApiUrl(
                'forecast.hongkong_history_api_url',
                'https://en.lottolyzer.com/history/hong-kong/mark-six/page/%d/per-page/%d/detail-view',
                2
            ),
            max(1, (int) $page),
            max(1, (int) $perPage)
        );
    }

    protected function macauHistoryYearUrl($year)
    {
        return sprintf(
            $this->configuredForecastApiUrl(
                'forecast.macau_history_api_url',
                'https://history.macaumarksix.com/history/macaujc2/y/%d',
                1
            ),
            max(2000, (int) $year)
        );
    }

    protected function remoteDrawConfig($region)
    {
        if ($region === 'macau') {
            return array(
                'cache_key' => $this->macauLiveCacheKey(),
                'url' => $this->macauApiUrl(),
                'urls' => array($this->macauApiUrl()),
                'note' => 'remote:live2',
            );
        }

        if ($region === 'hongkong') {
            return array(
                'cache_key' => $this->hongkongLiveCacheKey(),
                'url' => $this->hongkongApiUrl(),
                'urls' => array($this->hongkongApiUrl()),
                'note' => 'remote:hkjc.com',
            );
        }

        return null;
    }

    public function invalidateLiveDrawCache($region)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $dateKey = date('Ymd');

        if ($region === 'hongkong') {
            $this->app->cache()->forget($this->hongkongLiveCacheKey());
            $this->app->cache()->forget($this->hongkongRealtimeStateKey($dateKey));
            $this->app->cache()->forget($this->hongkongRealtimeTickKey($dateKey));
            return;
        }

        $this->app->cache()->forget($this->macauLiveCacheKey());
        $this->app->cache()->forget($this->macauRealtimeStateKey($dateKey));
        $this->app->cache()->forget($this->macauRealtimeTickKey($dateKey));
    }

    protected function drawOrderSql()
    {
        return ' ORDER BY CAST(issue_no AS UNSIGNED) DESC, draw_date DESC, id DESC';
    }

    protected function fetchRemoteJson($url)
    {
        $raw = $this->fetchRemoteJsonByCurl($url);
        if (!is_string($raw) || trim($raw) === '') {
            $raw = $this->fetchRemoteJsonByStream($url);
        }
        if (!is_string($raw) || trim($raw) === '') {
            $raw = $this->fetchRemoteJsonByPowerShell($url);
        }

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    protected function fetchRemoteHtml($url)
    {
        $raw = $this->fetchRemoteJsonByCurl($url);
        if (!is_string($raw) || trim($raw) === '') {
            $raw = $this->fetchRemoteJsonByStream($url);
        }
        if (!is_string($raw) || trim($raw) === '') {
            $raw = $this->fetchRemoteHtmlByPowerShell($url);
        }

        return is_string($raw) && trim($raw) !== '' ? $raw : null;
    }

    protected function fetchRemoteJsonByCurl($url)
    {
        if (!function_exists('curl_init')) {
            return null;
        }

        $handler = curl_init();
        curl_setopt_array($handler, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_ENCODING => '',
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json,text/plain,*/*',
                'User-Agent: Mozilla/5.0 PHP Forum Sync',
            ),
        ));

        $raw = curl_exec($handler);
        $httpCode = (int) curl_getinfo($handler, CURLINFO_HTTP_CODE);
        curl_close($handler);

        if (!is_string($raw) || trim($raw) === '' || $httpCode >= 400) {
            return null;
        }

        return $raw;
    }

    protected function fetchRemoteJsonByStream($url)
    {
        if (!ini_get('allow_url_fopen')) {
            return null;
        }

        $context = stream_context_create(array(
            'http' => array(
                'method' => 'GET',
                'timeout' => 5,
                'header' => "Accept: application/json,text/plain,*/*\r\nUser-Agent: Mozilla/5.0 PHP Forum Sync\r\n",
            ),
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
            ),
        ));

        $raw = @file_get_contents($url, false, $context);
        return is_string($raw) && trim($raw) !== '' ? $raw : null;
    }

    protected function fetchRemoteJsonByPowerShell($url)
    {
        if (stripos(PHP_OS, 'WIN') !== 0 || !function_exists('shell_exec')) {
            return null;
        }

        $script = '$ProgressPreference=\'SilentlyContinue\';'
            . '[Console]::OutputEncoding=[System.Text.Encoding]::UTF8;'
            . 'try {'
            . '(Invoke-RestMethod -Uri ' . escapeshellarg($url) . ' -TimeoutSec 5 | ConvertTo-Json -Depth 8 -Compress)'
            . '} catch { "" }';

        $command = 'powershell -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($script);
        $raw = @shell_exec($command);

        return is_string($raw) && trim($raw) !== '' ? $raw : null;
    }

    protected function fetchRemoteHtmlByPowerShell($url)
    {
        if (stripos(PHP_OS, 'WIN') !== 0 || !function_exists('shell_exec')) {
            return null;
        }

        $script = '$ProgressPreference=\'SilentlyContinue\';'
            . '[Console]::OutputEncoding=[System.Text.Encoding]::UTF8;'
            . 'try {'
            . '(Invoke-WebRequest -Uri ' . escapeshellarg($url) . ' -UseBasicParsing -TimeoutSec 8).Content'
            . '} catch { "" }';

        $command = 'powershell -NoProfile -ExecutionPolicy Bypass -Command ' . escapeshellarg($script);
        $raw = @shell_exec($command);

        return is_string($raw) && trim($raw) !== '' ? $raw : null;
    }

    protected function deriveHongkongNextOpenTime($openTime)
    {
        $timestamp = strtotime((string) $openTime);
        if ($timestamp === false) {
            return null;
        }

        try {
            $timezone = new \DateTimeZone('Asia/Shanghai');
            $openedAt = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
            $now = new \DateTimeImmutable('now', $timezone);
            $anchor = $openedAt > $now ? $openedAt : $now;
            $base = $anchor->setTime(21, 30, 0);

            for ($offset = 0; $offset <= 7; $offset++) {
                $candidate = $offset === 0 ? $base : $base->modify('+' . $offset . ' day');
                $weekday = (int) $candidate->format('w');

                if (in_array($weekday, array(2, 4, 6), true) && $candidate > $anchor) {
                    return $candidate->format('Y-m-d H:i:s');
                }
            }
        } catch (\Throwable $exception) {
            return null;
        }

        return null;
    }

    protected function deriveHongkongFollowingOpenTime($openTime)
    {
        $timestamp = strtotime((string) $openTime);
        if ($timestamp === false) {
            return null;
        }

        try {
            $timezone = new \DateTimeZone('Asia/Shanghai');
            $openedAt = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
            $base = $openedAt->setTime(21, 30, 0);

            for ($offset = 1; $offset <= 7; $offset++) {
                $candidate = $base->modify('+' . $offset . ' day');
                $weekday = (int) $candidate->format('w');

                if (in_array($weekday, array(2, 4, 6), true)) {
                    return $candidate->format('Y-m-d H:i:s');
                }
            }
        } catch (\Throwable $exception) {
            return null;
        }

        return null;
    }

    protected function deriveNextOpenTime($openTime, $region = 'macau')
    {
        if ($region === 'hongkong') {
            $next = $this->deriveHongkongNextOpenTime($openTime);
            if (is_string($next) && $next !== '') {
                return $next;
            }
        }

        $timestamp = strtotime((string) $openTime);
        if ($timestamp === false) {
            return null;
        }

        try {
            $timezone = new \DateTimeZone('Asia/Shanghai');
            $openedAt = (new \DateTimeImmutable('@' . $timestamp))->setTimezone($timezone);
            $now = new \DateTimeImmutable('now', $timezone);
            $anchor = $openedAt > $now ? $openedAt : $now;
            $clockParts = explode(':', $this->macauRealtimePollStart());
            $base = $anchor->setTime(
                (int) ($clockParts[0] ?? 21),
                (int) ($clockParts[1] ?? 32),
                (int) ($clockParts[2] ?? 0)
            );

            if ($base <= $anchor) {
                $base = $base->modify('+1 day');
            }

            return $base->format('Y-m-d H:i:s');
        } catch (\Throwable $exception) {
            return null;
        }
    }

    protected function incrementIssueNo($issueNo)
    {
        $issueNo = trim((string) $issueNo);
        if ($issueNo === '') {
            return '';
        }

        if (preg_match('/^(.*?)(\d+)(\D*)$/u', $issueNo, $matches)) {
            $prefix = (string) $matches[1];
            $numberPart = (string) $matches[2];
            $suffix = (string) $matches[3];
            $nextNumber = (string) ((int) $numberPart + 1);
            $nextNumber = str_pad($nextNumber, strlen($numberPart), '0', STR_PAD_LEFT);

            return $prefix . $nextNumber . $suffix;
        }

        return $issueNo;
    }

    protected function incrementIssueNoBySteps($issueNo, $steps)
    {
        $issueNo = trim((string) $issueNo);
        $steps = max(1, (int) $steps);
        if ($issueNo === '') {
            return '';
        }

        if (preg_match('/^(.*?)(\d+)(\D*)$/u', $issueNo, $matches)) {
            $prefix = (string) $matches[1];
            $numberPart = (string) $matches[2];
            $suffix = (string) $matches[3];
            $nextNumber = (string) ((int) $numberPart + $steps);
            $nextNumber = str_pad($nextNumber, strlen($numberPart), '0', STR_PAD_LEFT);

            return $prefix . $nextNumber . $suffix;
        }

        return $this->incrementIssueNo($issueNo);
    }

    protected function forecastMoment($createdAt = null)
    {
        $timezone = new \DateTimeZone('Asia/Shanghai');
        $createdAt = trim((string) $createdAt);

        try {
            return new \DateTimeImmutable($createdAt !== '' ? $createdAt : 'now', $timezone);
        } catch (\Exception $exception) {
            return new \DateTimeImmutable('now', $timezone);
        }
    }

    protected function macauForecastTargetDate(\DateTimeImmutable $moment)
    {
        $target = $moment;
        if (strcmp($moment->format('H:i:s'), $this->macauRealtimePollEnd()) > 0) {
            $target = $moment->modify('+1 day');
        }

        return $target->format('Y-m-d');
    }

    protected function drawDateDiffDays($fromDate, $toDate)
    {
        $fromDate = trim((string) $fromDate);
        $toDate = trim((string) $toDate);
        if ($fromDate === '' || $toDate === '') {
            return 1;
        }

        $timezone = new \DateTimeZone('Asia/Shanghai');
        $from = \DateTimeImmutable::createFromFormat('!Y-m-d', $fromDate, $timezone);
        $to = \DateTimeImmutable::createFromFormat('!Y-m-d', $toDate, $timezone);
        if (!$from instanceof \DateTimeImmutable || !$to instanceof \DateTimeImmutable) {
            return 1;
        }

        return max(1, (int) $from->diff($to)->format('%r%a'));
    }

    protected function drawByDate($region, $drawDate)
    {
        $drawDate = trim((string) $drawDate);
        if ($drawDate === '') {
            return null;
        }

        return $this->db()->fetch(
            'SELECT * FROM lottery_draws
             WHERE region = :region AND draw_date = :draw_date
             ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
             LIMIT 1',
            array(
                'region' => (string) $region,
                'draw_date' => $drawDate,
            )
        );
    }

    protected function drawBeforeDate($region, $drawDate, array $draws = array())
    {
        $drawDate = trim((string) $drawDate);
        if ($drawDate !== '') {
            foreach ($draws as $draw) {
                $candidateDate = trim((string) ($draw['draw_date'] ?? ''));
                if ($candidateDate !== '' && strcmp($candidateDate, $drawDate) < 0) {
                    return $draw;
                }
            }

            $draw = $this->db()->fetch(
                'SELECT * FROM lottery_draws
                 WHERE region = :region AND draw_date < :draw_date
                 ORDER BY draw_date DESC, CAST(issue_no AS UNSIGNED) DESC, id DESC
                 LIMIT 1',
                array(
                    'region' => (string) $region,
                    'draw_date' => $drawDate,
                )
            );
            if ($draw) {
                return $draw;
            }
        }

        if (!empty($draws[0])) {
            return $draws[0];
        }

        return $this->latestStoredDrawRow($region);
    }

    protected function scheduledForecastIssueNo($region)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $scheduledIssueNo = '';
        if (!$this->tableExists('lottery_issues')) {
            $latestDraw = $this->latestStoredDrawRow($region);

            return $latestDraw && trim((string) ($latestDraw['issue_no'] ?? '')) !== ''
                ? $this->incrementIssueNo((string) $latestDraw['issue_no'])
                : '';
        }

        $issue = $this->db()->fetch(
            'SELECT issue_no
             FROM lottery_issues
             WHERE region = :region AND is_current = 1
             ORDER BY planned_open_at DESC, id DESC
             LIMIT 1',
            array('region' => $region)
        );
        if ($issue && trim((string) ($issue['issue_no'] ?? '')) !== '') {
            $scheduledIssueNo = trim((string) $issue['issue_no']);
        } else {
            $issue = $this->db()->fetch(
                'SELECT issue_no
                 FROM lottery_issues
                 WHERE region = :region
                   AND planned_open_at >= :now
                 ORDER BY planned_open_at ASC, id DESC
                 LIMIT 1',
                array(
                    'region' => $region,
                    'now' => $this->now(),
                )
            );
            if ($issue && trim((string) ($issue['issue_no'] ?? '')) !== '') {
                $scheduledIssueNo = trim((string) $issue['issue_no']);
            }
        }

        $latestDraw = $this->latestStoredDrawRow($region);
        $latestDrawIssueNo = $latestDraw && trim((string) ($latestDraw['issue_no'] ?? '')) !== ''
            ? trim((string) $latestDraw['issue_no'])
            : '';
        if ($latestDrawIssueNo === '') {
            return $scheduledIssueNo;
        }

        if ($scheduledIssueNo === '') {
            return $this->incrementIssueNo($latestDrawIssueNo);
        }

        return $this->isSameOrAdvancedIssue($latestDrawIssueNo, $scheduledIssueNo)
            ? $this->incrementIssueNo($latestDrawIssueNo)
            : $scheduledIssueNo;
    }

    public function resolveForecastIssueForMoment($region, $createdAt = null, array $draws = array(), $fallbackIssueNo = '')
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $fallbackIssueNo = trim((string) $fallbackIssueNo);
        $scheduledIssueNo = $this->scheduledForecastIssueNo($region);
        if ($scheduledIssueNo !== '') {
            return $scheduledIssueNo;
        }

        if ($region === 'macau') {
            $targetDate = $this->macauForecastTargetDate($this->forecastMoment($createdAt));
            $targetDraw = $this->drawByDate($region, $targetDate);
            if (is_array($targetDraw) && trim((string) ($targetDraw['issue_no'] ?? '')) !== '') {
                return trim((string) $targetDraw['issue_no']);
            }

            $baseDraw = $this->drawBeforeDate($region, $targetDate, $draws);
            if (is_array($baseDraw) && trim((string) ($baseDraw['issue_no'] ?? '')) !== '') {
                $steps = $this->drawDateDiffDays((string) ($baseDraw['draw_date'] ?? ''), $targetDate);

                return $this->incrementIssueNoBySteps((string) $baseDraw['issue_no'], $steps);
            }
        } else {
            $baseDraw = !empty($draws[0]) ? $draws[0] : $this->latestStoredDrawRow($region);
            if (is_array($baseDraw) && trim((string) ($baseDraw['issue_no'] ?? '')) !== '') {
                return $this->incrementIssueNo((string) $baseDraw['issue_no']);
            }
        }

        return $fallbackIssueNo;
    }

    protected function refreshDrawNextOpenTime(array $draw, $region)
    {
        $openTime = trim((string) ($draw['open_time'] ?? ''));
        if ($openTime === '') {
            return $draw;
        }

        $nextOpenTime = $this->deriveNextOpenTime($openTime, $region);
        if (is_string($nextOpenTime) && $nextOpenTime !== '') {
            $draw['next_open_time'] = $nextOpenTime;
        }

        return $draw;
    }

    protected function shouldPromoteHomepageIssue(array $draw)
    {
        $openTime = trim((string) ($draw['open_time'] ?? ''));
        $drawDate = trim((string) ($draw['draw_date'] ?? ''));
        $baseTimestamp = $openTime !== '' ? strtotime($openTime) : false;
        if ($baseTimestamp === false && $drawDate !== '') {
            $baseTimestamp = strtotime($drawDate);
        }
        if ($baseTimestamp === false) {
            return true;
        }

        $promoteTimestamp = strtotime(date('Y-m-d 23:59:00', $baseTimestamp));
        $nowTimestamp = strtotime($this->now());

        return $promoteTimestamp === false || $nowTimestamp === false || $nowTimestamp >= $promoteTimestamp;
    }

    protected function isHomepageIssueScheduleStale(array $issue = null, array $draw = null)
    {
        if (!$issue) {
            return true;
        }

        $plannedOpenAt = trim((string) ($issue['planned_open_at'] ?? ''));
        if ($plannedOpenAt === '') {
            return true;
        }

        if (!$draw) {
            return false;
        }

        $issueNumber = $this->issueNumberValue((string) ($issue['issue_no'] ?? ''));
        $drawNumber = $this->issueNumberValue((string) ($draw['issue_no'] ?? ''));
        $drawOpenTime = trim((string) ($draw['open_time'] ?? ''));
        if ($drawOpenTime !== '') {
            $plannedTimestamp = strtotime($plannedOpenAt);
            $drawTimestamp = strtotime($drawOpenTime);

            if ($plannedTimestamp !== false && $drawTimestamp !== false && $plannedTimestamp <= $drawTimestamp) {
                return true;
            }
        }

        if ($issueNumber !== null && $drawNumber !== null && $issueNumber <= $drawNumber) {
            return true;
        }

        return false;
    }

    protected function synchronizeHomepageIssueSchedule($region, array $draw = null, array $issue = null)
    {
        if (!$draw) {
            return $issue;
        }

        $issueNo = trim((string) ($draw['issue_no'] ?? ''));
        $plannedOpenAt = trim((string) ($draw['next_open_time'] ?? ''));
        if ($issueNo === '' || $plannedOpenAt === '') {
            return $issue;
        }

        $targetIssueNo = $this->incrementIssueNo($issueNo);
        if ($targetIssueNo === '') {
            return $issue;
        }

        $targetIssue = $this->db()->fetch(
            'SELECT * FROM lottery_issues WHERE region = :region AND issue_no = :issue_no LIMIT 1',
            array(
                'region' => $region,
                'issue_no' => $targetIssueNo,
            )
        );

        $now = $this->now();
        $lastRunIssueNo = trim((string) $this->app->settings()->get('post_generator.schedule_last_run.' . $region, ''));
        $targetIsCurrent = $lastRunIssueNo !== '' && $this->isSameOrAdvancedIssue($lastRunIssueNo, $targetIssueNo) ? 1 : 0;

        if ($targetIssue) {
            $targetIssueId = (int) $targetIssue['id'];
            $targetActualOpenAt = trim((string) ($targetIssue['actual_open_at'] ?? ''));
            if (
                (string) ($targetIssue['planned_open_at'] ?? '') !== $plannedOpenAt
                || $targetActualOpenAt !== ''
                || (string) ($targetIssue['status'] ?? '') !== 'pending'
                || (int) ($targetIssue['is_current'] ?? 0) !== $targetIsCurrent
            ) {
                $this->db()->execute(
                    'UPDATE lottery_issues
                     SET planned_open_at = :planned_open_at, actual_open_at = NULL, status = :status, is_current = :is_current, remark = :remark, updated_at = :updated_at
                     WHERE id = :id',
                    array(
                        'planned_open_at' => $plannedOpenAt,
                        'status' => 'pending',
                        'is_current' => $targetIsCurrent,
                        'remark' => '系统根据最新开奖结果自动同步',
                        'updated_at' => $now,
                        'id' => $targetIssueId,
                    )
                );
            }
        } else {
            $targetIssueId = (int) $this->db()->insertGetId(
                'INSERT INTO lottery_issues (region, issue_no, planned_open_at, actual_open_at, status, is_current, remark, created_at, updated_at)
                 VALUES (:region, :issue_no, :planned_open_at, :actual_open_at, :status, :is_current, :remark, :created_at, :updated_at)',
                array(
                    'region' => $region,
                    'issue_no' => $targetIssueNo,
                    'planned_open_at' => $plannedOpenAt,
                    'actual_open_at' => null,
                    'status' => 'pending',
                    'is_current' => $targetIsCurrent,
                    'remark' => '系统根据最新开奖结果自动同步',
                    'created_at' => $now,
                    'updated_at' => $now,
                )
            );
        }

        if ($targetIsCurrent === 1) {
            $this->db()->execute(
                'UPDATE lottery_issues SET is_current = 0, updated_at = :updated_at WHERE region = :region AND id <> :id AND is_current = 1',
                array(
                    'updated_at' => $now,
                    'region' => $region,
                    'id' => $targetIssueId,
                )
            );
        }

        return $this->db()->fetch('SELECT * FROM lottery_issues WHERE id = :id LIMIT 1', array('id' => $targetIssueId));
    }

    public function synchronizeIssueAfterOpenedDraw($region, array $draw)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        if (!$this->tableExists('lottery_issues')) {
            return null;
        }

        $issueNo = trim((string) ($draw['issue_no'] ?? ''));
        if ($issueNo === '') {
            return null;
        }

        $latestDraw = $this->latestStoredDrawRow($region);
        $latestIssueNo = is_array($latestDraw)
            ? trim((string) ($latestDraw['issue_no'] ?? ''))
            : '';
        if ($latestIssueNo !== '' && !$this->isSameOrAdvancedIssue($issueNo, $latestIssueNo)) {
            return null;
        }

        $actualOpenAt = trim((string) ($draw['open_time'] ?? ''));
        if ($actualOpenAt === '') {
            $drawDate = trim((string) ($draw['draw_date'] ?? ''));
            if ($drawDate !== '') {
                $defaultOpenClock = $region === 'hongkong' ? ' 21:30:00' : ' 21:32:00';
                $actualOpenAt = $drawDate . $defaultOpenClock;
            }
        }
        if ($actualOpenAt !== '') {
            $targetIssueNo = $this->incrementIssueNo($issueNo);
            $lastRunIssueNo = trim((string) $this->app->settings()->get('post_generator.schedule_last_run.' . $region, ''));
            $openedIsCurrent = $targetIssueNo === ''
                || $lastRunIssueNo === ''
                || !$this->isSameOrAdvancedIssue($lastRunIssueNo, $targetIssueNo)
                ? 1
                : 0;
            $openedIssue = $this->db()->fetch(
                'SELECT * FROM lottery_issues WHERE region = :region AND issue_no = :issue_no LIMIT 1',
                array(
                    'region' => $region,
                    'issue_no' => $issueNo,
                )
            );

            if ($openedIssue) {
                $this->db()->execute(
                    'UPDATE lottery_issues
                     SET planned_open_at = :planned_open_at,
                         actual_open_at = :actual_open_at,
                         status = :status,
                         is_current = :is_current,
                         updated_at = :updated_at
                     WHERE id = :id',
                    array(
                        'planned_open_at' => $actualOpenAt,
                        'actual_open_at' => $actualOpenAt,
                        'status' => 'opened',
                        'is_current' => $openedIsCurrent,
                        'updated_at' => $this->now(),
                        'id' => (int) $openedIssue['id'],
                    )
                );
                $openedIssueId = (int) $openedIssue['id'];
            } else {
                $openedIssueId = (int) $this->db()->insertGetId(
                    'INSERT INTO lottery_issues (region, issue_no, planned_open_at, actual_open_at, status, is_current, remark, created_at, updated_at)
                     VALUES (:region, :issue_no, :planned_open_at, :actual_open_at, :status, :is_current, :remark, :created_at, :updated_at)',
                    array(
                        'region' => $region,
                        'issue_no' => $issueNo,
                        'planned_open_at' => $actualOpenAt,
                        'actual_open_at' => $actualOpenAt,
                        'status' => 'opened',
                        'is_current' => $openedIsCurrent,
                        'remark' => 'opened draw sync',
                        'created_at' => $this->now(),
                        'updated_at' => $this->now(),
                    )
                );
            }

            if ($openedIsCurrent === 1) {
                $this->db()->execute(
                    'UPDATE lottery_issues SET is_current = 0, updated_at = :updated_at WHERE region = :region AND id <> :id AND is_current = 1',
                    array(
                        'updated_at' => $this->now(),
                        'region' => $region,
                        'id' => $openedIssueId,
                    )
                );
            }
        }

        $nextOpenTime = trim((string) ($draw['next_open_time'] ?? ''));
        if ($nextOpenTime === '') {
            $openTime = trim((string) ($draw['open_time'] ?? ''));
            if ($openTime === '') {
                $drawDate = trim((string) ($draw['draw_date'] ?? ''));
                if ($drawDate !== '') {
                    $defaultOpenClock = $region === 'hongkong' ? ' 21:30:00' : ' 21:32:00';
                    $openTime = $drawDate . $defaultOpenClock;
                }
            }

            if ($openTime !== '') {
                $nextOpenTime = (string) $this->deriveNextOpenTime($openTime, $region);
            }
        }

        if ($nextOpenTime === '') {
            return null;
        }

        $draw['next_open_time'] = $nextOpenTime;

        return $this->synchronizeHomepageIssueSchedule($region, $draw, null);
    }

    protected function extractRemoteLatestRow($response)
    {
        if (isset($response[0]) && is_array($response[0])) {
            return $response[0];
        }

        if (isset($response['value'][0]) && is_array($response['value'][0])) {
            return $response['value'][0];
        }

        return null;
    }

    protected function parseRemoteDraw(array $remoteRow, $region = 'macau', $note = 'remote:live2', $allowPartial = false)
    {
        $issueNo = trim((string) (isset($remoteRow['expect']) ? $remoteRow['expect'] : ''));
        $openTime = trim((string) (isset($remoteRow['openTime']) ? $remoteRow['openTime'] : ''));
        $openCode = trim((string) (isset($remoteRow['openCode']) ? $remoteRow['openCode'] : ''));

        if ($issueNo === '' || $openTime === '' || $openCode === '') {
            return null;
        }

        $parts = array_values(array_filter(array_map('trim', explode(',', $openCode)), 'strlen'));
        if (empty($parts) || (!$allowPartial && count($parts) < 7)) {
            return null;
        }

        $numbers = array();
        $specialNumber = 0;
        $seenNumbers = array();
        foreach (array_slice($parts, 0, 6) as $part) {
            if (preg_match('/^\d+$/', (string) $part) !== 1) {
                if ($allowPartial) {
                    continue;
                }
                return null;
            }
            $number = (int) $part;
            if ($number < 1 || $number > 49) {
                if ($allowPartial && $number <= 0) {
                    continue;
                }
                return null;
            }
            if (isset($seenNumbers[$number])) {
                return null;
            }
            $seenNumbers[$number] = true;
            $numbers[] = $number;
        }

        if (isset($parts[6])) {
            if (preg_match('/^\d+$/', (string) $parts[6]) !== 1) {
                if (!$allowPartial) {
                    return null;
                }
            } else {
                $specialNumber = (int) $parts[6];
                if ($specialNumber < 1 || $specialNumber > 49 || isset($seenNumbers[$specialNumber])) {
                    if ($allowPartial && $specialNumber <= 0) {
                        $specialNumber = 0;
                    } else {
                        return null;
                    }
                }
            }
        }
        $drawDate = date('Y-m-d', strtotime($openTime));

        return array(
            'issue_no' => $issueNo,
            'draw_date' => $drawDate,
            'open_time' => $openTime,
            'numbers' => $numbers,
            'special_number' => $specialNumber,
            'note' => (string) $note,
            'next_open_time' => $this->deriveNextOpenTime($openTime, $region),
            'is_partial' => count($numbers) < 6 || $specialNumber <= 0,
        );
    }

    protected function parseHongkongHistoryRows($html)
    {
        if (!is_string($html) || trim($html) === '') {
            return array();
        }

        if (!preg_match_all('~<div class="block">(.*?)</table>\s*</div>~si', $html, $blockMatches)) {
            return array();
        }

        $rows = array();
        foreach ((array) $blockMatches[1] as $blockHtml) {
            if (!preg_match('~<div class="col-xs-4 pad-fix">\s*(\d{2})/(\d{3})\s*</div>~si', $blockHtml, $issueMatch)) {
                continue;
            }

            if (!preg_match('~<div class="col-xs-8 pad-fix">\s*(?:<span class="s-hide">[^<]+</span>)?(\d{1,2})<sup>[^<]+</sup>\s*([A-Za-z]+)\s*(\d{4})~si', $blockHtml, $dateMatch)) {
                continue;
            }

            if (!preg_match_all('~alt="(\d{1,2})"~', $blockHtml, $numberMatches)) {
                continue;
            }

            $allNumbers = array_values(array_map('intval', (array) $numberMatches[1]));
            if (count($allNumbers) < 7) {
                continue;
            }

            $drawDate = \DateTimeImmutable::createFromFormat(
                '!j F Y',
                $dateMatch[1] . ' ' . $dateMatch[2] . ' ' . $dateMatch[3],
                new \DateTimeZone('Asia/Shanghai')
            );

            if (!$drawDate instanceof \DateTimeImmutable) {
                continue;
            }

            $issueYear = '20' . $issueMatch[1];
            $issueTail = str_pad($issueMatch[2], 3, '0', STR_PAD_LEFT);

            $rows[] = array(
                'issue_no' => $issueYear . $issueTail,
                'issue_year' => $issueYear,
                'draw_date' => $drawDate->format('Y-m-d'),
                'numbers' => array_slice($allNumbers, 0, 6),
                'special_number' => (int) $allNumbers[6],
                'note' => 'remote:lottolyzer',
            );
        }

        return $rows;
    }

    protected function parseMacauHistoryRows($response, $issueYear)
    {
        $issueYear = trim((string) $issueYear);
        if ($issueYear === '' || !is_array($response) || empty($response['data']) || !is_array($response['data'])) {
            return array();
        }

        $rows = array();
        foreach ((array) $response['data'] as $remoteRow) {
            if (!is_array($remoteRow)) {
                continue;
            }

            $parsed = $this->parseRemoteDraw($remoteRow, 'macau', 'remote:macaujc2-history');
            if (!is_array($parsed)) {
                continue;
            }

            if (strpos((string) $parsed['issue_no'], $issueYear) !== 0) {
                continue;
            }

            $rows[] = array(
                'issue_no' => (string) $parsed['issue_no'],
                'issue_year' => $issueYear,
                'draw_date' => (string) $parsed['draw_date'],
                'numbers' => array_values(array_map('intval', (array) $parsed['numbers'])),
                'special_number' => (int) $parsed['special_number'],
                'note' => 'remote:macaujc2-history',
            );
        }

        return $rows;
    }

    protected function latestStoredDrawRow($region)
    {
        return $this->db()->fetch('SELECT * FROM lottery_draws WHERE region = :region' . $this->drawOrderSql() . ' LIMIT 1', array(
            'region' => $region,
        ));
    }

    protected function normalizeStoredDraw(array $draw)
    {
        return array(
            'issue_no' => (string) $draw['issue_no'],
            'draw_date' => (string) $draw['draw_date'],
            'open_time' => null,
            'numbers' => array_values(array_map('intval', (array) json_decode((string) $draw['numbers_json'], true))),
            'special_number' => (int) $draw['special_number'],
            'note' => (string) $draw['note'],
            'next_open_time' => null,
        );
    }

    protected function normalizeHomepageStoredDraw($region, array $draw)
    {
        $normalized = $this->normalizeStoredDraw($draw);

        if (in_array((string) $region, array('macau', 'hongkong'), true)) {
            $drawDate = trim((string) ($normalized['draw_date'] ?? ''));
            if ($drawDate !== '' && trim((string) ($normalized['open_time'] ?? '')) === '') {
                $defaultOpenClock = (string) $region === 'hongkong' ? '21:30:00' : $this->macauRealtimePollStart();
                $normalized['open_time'] = $drawDate . ' ' . $defaultOpenClock;
            }
            $normalized = $this->refreshDrawNextOpenTime($normalized, (string) $region);
        }

        return $normalized;
    }

    protected function historyRowFromLatestDraw($region, array $draw)
    {
        $issueNo = trim((string) ($draw['issue_no'] ?? ''));
        $drawDate = trim((string) ($draw['draw_date'] ?? ''));
        $numbers = array_values(array_map('intval', (array) ($draw['numbers'] ?? array())));
        $specialNumber = (int) ($draw['special_number'] ?? 0);

        if ($issueNo === '' || $drawDate === '' || count($numbers) < 6 || $specialNumber <= 0) {
            return null;
        }

        return array(
            'region' => (string) $region,
            'issue_no' => $issueNo,
            'draw_date' => $drawDate,
            'numbers_json' => json_encode(array_slice($numbers, 0, 6)),
            'special_number' => $specialNumber,
            'note' => trim((string) ($draw['note'] ?? '')),
        );
    }

    protected function mergeLatestDrawIntoHistoryRows($region, array $rows, array $latestDraw = null, $issueYear = '', $limit = 160)
    {
        $merged = array();
        $seenIssues = array();
        $issueYear = trim((string) $issueYear);

        if (is_array($latestDraw)) {
            $latestRow = $this->historyRowFromLatestDraw($region, $latestDraw);
            if (is_array($latestRow)) {
                $latestIssue = (string) $latestRow['issue_no'];
                if ($issueYear === '' || strpos($latestIssue, $issueYear) === 0) {
                    $merged[] = $latestRow;
                    $seenIssues[$latestIssue] = true;
                }
            }
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $issueNo = trim((string) ($row['issue_no'] ?? ''));
            if ($issueNo === '' || isset($seenIssues[$issueNo])) {
                continue;
            }

            if ($issueYear !== '' && strpos($issueNo, $issueYear) !== 0) {
                continue;
            }

            $merged[] = $row;
            $seenIssues[$issueNo] = true;

            if (count($merged) >= (int) $limit) {
                break;
            }
        }

        return array_slice($merged, 0, (int) $limit);
    }

    protected function latestDrawsByDate($region, $limit = 12)
    {
        return $this->db()->fetchAll('SELECT * FROM lottery_draws WHERE region = :region ORDER BY draw_date DESC, CAST(issue_no AS UNSIGNED) DESC, id DESC LIMIT ' . (int) $limit, array(
            'region' => $region,
        ));
    }

    protected function latestDrawsByIssueYear($region, $issueYear, $limit = 160)
    {
        return $this->db()->fetchAll('SELECT * FROM lottery_draws WHERE region = :region AND issue_no LIKE :issue_year_like' . $this->drawOrderSql() . ' LIMIT ' . (int) $limit, array(
            'region' => $region,
            'issue_year_like' => trim((string) $issueYear) . '%',
        ));
    }

    protected function forecastAllowedSourceNote($region)
    {
        return (string) $region === 'hongkong' ? 'remote:hkjc.com' : 'remote:live2';
    }

    protected function latestForecastSourceDraws($region, $limit = 12)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';

        return $this->db()->fetchAll(
            'SELECT *
             FROM lottery_draws
             WHERE region = :region
               AND note = :note' . $this->drawOrderSql() . '
             LIMIT ' . (int) $limit,
            array(
                'region' => $region,
                'note' => $this->forecastAllowedSourceNote($region),
            )
        );
    }

    protected function issueNumberValue($issueNo)
    {
        $issueNo = trim((string) $issueNo);
        if ($issueNo !== '' && preg_match('/^\d+$/', $issueNo)) {
            return (int) $issueNo;
        }

        return null;
    }

    protected function isAdvancedIssue($candidateIssue, $baselineIssue)
    {
        $candidateIssue = trim((string) $candidateIssue);
        $baselineIssue = trim((string) $baselineIssue);

        if ($candidateIssue === '') {
            return false;
        }

        if ($baselineIssue === '') {
            return true;
        }

        $candidateNumber = $this->issueNumberValue($candidateIssue);
        $baselineNumber = $this->issueNumberValue($baselineIssue);

        if ($candidateNumber !== null && $baselineNumber !== null) {
            return $candidateNumber > $baselineNumber;
        }

        return strcmp($candidateIssue, $baselineIssue) > 0;
    }

    protected function isSameOrAdvancedIssue($candidateIssue, $baselineIssue)
    {
        $candidateIssue = trim((string) $candidateIssue);
        $baselineIssue = trim((string) $baselineIssue);

        if ($candidateIssue === '') {
            return false;
        }

        if ($baselineIssue === '') {
            return true;
        }

        $candidateNumber = $this->issueNumberValue($candidateIssue);
        $baselineNumber = $this->issueNumberValue($baselineIssue);

        if ($candidateNumber !== null && $baselineNumber !== null) {
            return $candidateNumber >= $baselineNumber;
        }

        return strcmp($candidateIssue, $baselineIssue) >= 0;
    }

    protected function isMacauRealtimeWindow()
    {
        $time = date('H:i:s');

        return $time >= $this->macauRealtimePollStart() && $time <= $this->macauRealtimePollEnd();
    }

    protected function isHongkongRealtimeWindow(array $draw = null)
    {
        return $this->isMacauRealtimeWindow();
    }

    protected function persistFetchedDraw($region, array $remoteRow, $note = 'remote:live2', $runAfterDrawGenerator = true)
    {
        $parsed = $this->parseRemoteDraw($remoteRow, $region, $note);
        if (!is_array($parsed)) {
            return null;
        }

        $issueNo = $parsed['issue_no'];
        $drawDate = $parsed['draw_date'];
        $openTime = $parsed['open_time'];
        $numbers = $parsed['numbers'];
        $specialNumber = $parsed['special_number'];
        $note = $parsed['note'];

        $existing = $this->db()->fetch('SELECT * FROM lottery_draws WHERE region = :region AND issue_no = :issue_no ORDER BY id DESC LIMIT 1', array(
            'region' => $region,
            'issue_no' => $issueNo,
        ));

        $payload = array(
            'region' => $region,
            'issue_no' => $issueNo,
            'draw_date' => $drawDate,
            'numbers_json' => json_encode($numbers),
            'special_number' => $specialNumber,
            'note' => $note,
            'updated_at' => $this->now(),
        );

        if ($existing) {
            $this->db()->execute('UPDATE lottery_draws SET draw_date = :draw_date, numbers_json = :numbers_json, special_number = :special_number, note = :note, updated_at = :updated_at WHERE id = :id', array(
                'draw_date' => $drawDate,
                'numbers_json' => json_encode($numbers),
                'special_number' => $specialNumber,
                'note' => $note,
                'updated_at' => $this->now(),
                'id' => $existing['id'],
            ));
            $drawId = (int) $existing['id'];
        } else {
            $drawId = $this->db()->insertGetId('INSERT INTO lottery_draws (region, issue_no, draw_date, numbers_json, special_number, note, created_by, created_at, updated_at) VALUES (:region, :issue_no, :draw_date, :numbers_json, :special_number, :note, :created_by, :created_at, :updated_at)', $payload + array(
                'created_by' => null,
                'created_at' => $this->now(),
            ));
        }

        $savedDraw = array(
            'id' => $drawId,
            'issue_no' => $issueNo,
            'draw_date' => $drawDate,
            'open_time' => $openTime,
            'numbers' => $numbers,
            'special_number' => $specialNumber,
            'note' => $note,
            'next_open_time' => $parsed['next_open_time'],
        );

        $this->synchronizeIssueAfterOpenedDraw($region, $savedDraw);
        if ($runAfterDrawGenerator) {
            try {
                $this->app->admins()->runManagedPostGeneratorAfterDraw($region, $savedDraw);
            } catch (\Throwable $exception) {
                $this->app->admins()->writeManagedExceptionLog($exception, 'posts', 'post_generator_after_draw', 'system', 0);
            }
        }

        return $savedDraw;
    }

    protected function persistHistoricalDraw($region, array $draw)
    {
        $issueNo = trim((string) (isset($draw['issue_no']) ? $draw['issue_no'] : ''));
        $drawDate = trim((string) (isset($draw['draw_date']) ? $draw['draw_date'] : ''));
        $numbers = array_values(array_map('intval', (array) (isset($draw['numbers']) ? $draw['numbers'] : array())));
        $specialNumber = (int) (isset($draw['special_number']) ? $draw['special_number'] : 0);
        $note = trim((string) (isset($draw['note']) ? $draw['note'] : ''));

        if ($issueNo === '' || $drawDate === '' || count($numbers) < 6 || $specialNumber <= 0) {
            return null;
        }

        $existing = $this->db()->fetch('SELECT * FROM lottery_draws WHERE region = :region AND issue_no = :issue_no ORDER BY id DESC LIMIT 1', array(
            'region' => $region,
            'issue_no' => $issueNo,
        ));
        if (
            $existing
            && $note !== $this->forecastAllowedSourceNote($region)
            && (string) ($existing['note'] ?? '') === $this->forecastAllowedSourceNote($region)
        ) {
            $note = (string) $existing['note'];
        }

        $payload = array(
            'draw_date' => $drawDate,
            'numbers_json' => json_encode(array_slice($numbers, 0, 6)),
            'special_number' => $specialNumber,
            'note' => $note,
            'updated_at' => $this->now(),
        );

        if ($existing) {
            $this->db()->execute('UPDATE lottery_draws SET draw_date = :draw_date, numbers_json = :numbers_json, special_number = :special_number, note = :note, updated_at = :updated_at WHERE id = :id', $payload + array(
                'id' => $existing['id'],
            ));
            return (int) $existing['id'];
        }

        return (int) $this->db()->insertGetId('INSERT INTO lottery_draws (region, issue_no, draw_date, numbers_json, special_number, note, created_by, created_at, updated_at) VALUES (:region, :issue_no, :draw_date, :numbers_json, :special_number, :note, :created_by, :created_at, :updated_at)', array(
            'region' => $region,
            'issue_no' => $issueNo,
            'draw_date' => $drawDate,
            'numbers_json' => json_encode(array_slice($numbers, 0, 6)),
            'special_number' => $specialNumber,
            'note' => $note,
            'created_by' => null,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ));
    }

    protected function cleanupDuplicateDrawRows($region, $issueYear = '')
    {
        $region = trim((string) $region);
        $issueYear = trim((string) $issueYear);
        if ($region === '') {
            return;
        }

        $params = array(
            'region' => $region,
        );

        $issueYearSql = '';
        if ($issueYear !== '') {
            $issueYearSql = ' AND duplicate_row.issue_no LIKE :issue_year_like';
            $params['issue_year_like'] = $issueYear . '%';
        }

        $this->db()->execute(
            'DELETE duplicate_row
             FROM lottery_draws AS duplicate_row
             INNER JOIN lottery_draws AS kept_row
                 ON duplicate_row.region = kept_row.region
                AND duplicate_row.issue_no = kept_row.issue_no
                AND duplicate_row.id < kept_row.id
             WHERE duplicate_row.region = :region' . $issueYearSql,
            $params
        );
    }

    protected function cleanupDuplicateIssueRows($region)
    {
        $region = trim((string) $region);
        if ($region === '' || !$this->tableExists('lottery_issues')) {
            return;
        }

        $this->db()->execute(
            'DELETE duplicate_row
             FROM lottery_issues AS duplicate_row
             INNER JOIN lottery_issues AS kept_row
                 ON duplicate_row.region = kept_row.region
                AND duplicate_row.issue_no = kept_row.issue_no
                AND duplicate_row.id < kept_row.id
             WHERE duplicate_row.region = :region',
            array(
                'region' => $region,
            )
        );
    }

    protected function cleanupStaleHistoricalDraws($region, $issueYear, array $validIssueNos, array $preserveIssueNos = array())
    {
        $region = trim((string) $region);
        $issueYear = trim((string) $issueYear);
        if ($region === '' || $issueYear === '') {
            return;
        }

        $retainIssueNos = array_values(array_unique(array_filter(array_map('trim', array_merge($validIssueNos, $preserveIssueNos)), 'strlen')));
        if (empty($retainIssueNos)) {
            return;
        }

        $params = array(
            'region' => $region,
            'issue_year_like' => $issueYear . '%',
        );

        $placeholders = array();
        foreach ($retainIssueNos as $index => $issueNo) {
            $key = 'retain_issue_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $issueNo;
        }

        $this->db()->execute(
            'DELETE FROM lottery_draws
             WHERE region = :region
               AND issue_no LIKE :issue_year_like
               AND issue_no NOT IN (' . implode(', ', $placeholders) . ')',
            $params
        );
    }

    protected function cleanupSampleDraws($region, $issueYear, array $validIssueNos)
    {
        $region = trim((string) $region);
        $issueYear = trim((string) $issueYear);
        if ($region === '' || $issueYear === '') {
            return;
        }

        $params = array(
            'region' => $region,
            'sample_note' => '系统初始化示例数据',
            'issue_year_like' => $issueYear . '%',
        );

        $sql = 'DELETE FROM lottery_draws WHERE region = :region AND note = :sample_note AND issue_no LIKE :issue_year_like';
        if (!empty($validIssueNos)) {
            $placeholders = array();
            foreach (array_values($validIssueNos) as $index => $issueNo) {
                $key = 'issue_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = (string) $issueNo;
            }
            $sql .= ' AND issue_no NOT IN (' . implode(', ', $placeholders) . ')';
        }

        $this->db()->execute($sql, $params);
    }

    public function syncHongkongCurrentYearHistory($runAfterDrawGenerator = true)
    {
        $latest = $this->syncRemoteLatestDraw('hongkong', $runAfterDrawGenerator);
        $latestIssue = is_array($latest) && !empty($latest['issue_no']) ? trim((string) $latest['issue_no']) : '';
        $issueYear = preg_match('/^\d{7}$/', $latestIssue) ? substr($latestIssue, 0, 4) : date('Y');
        $this->cleanupDuplicateDrawRows('hongkong', $issueYear);
        $this->cleanupDuplicateIssueRows('hongkong');
        $imported = array();
        $matchedTargetYear = false;

        for ($page = 1; $page <= 8; $page++) {
            $html = $this->fetchRemoteHtml($this->hongkongHistoryUrl($page, 50));
            if (!is_string($html) || trim($html) === '') {
                break;
            }

            $pageRows = $this->parseHongkongHistoryRows($html);
            if (empty($pageRows)) {
                break;
            }

            $pageMatchCount = 0;
            foreach ($pageRows as $row) {
                if ((string) $row['issue_year'] !== $issueYear) {
                    continue;
                }

                $pageMatchCount++;
                $matchedTargetYear = true;
                $this->persistHistoricalDraw('hongkong', $row);
                $imported[(string) $row['issue_no']] = true;
            }

            if ($matchedTargetYear && $pageMatchCount === 0) {
                break;
            }
        }

        if (!empty($imported)) {
            $this->cleanupSampleDraws('hongkong', $issueYear, array_keys($imported));
        }

        return array_keys($imported);
    }

    protected function shouldSyncHongkongHomepageHistory(array $draw = null)
    {
        if (!is_array($draw) || trim((string) ($draw['issue_no'] ?? '')) === '') {
            return true;
        }

        $openTime = trim((string) ($draw['open_time'] ?? ''));
        if ($openTime === '') {
            $drawDate = trim((string) ($draw['draw_date'] ?? ''));
            $openTime = $drawDate !== '' ? $drawDate . ' 21:30:00' : '';
        }
        if ($openTime === '') {
            return true;
        }

        $followingOpenTime = $this->deriveHongkongFollowingOpenTime($openTime);
        $followingTimestamp = is_string($followingOpenTime) ? strtotime($followingOpenTime) : false;
        if ($followingTimestamp === false) {
            return false;
        }

        return time() >= ($followingTimestamp + 1800);
    }

    protected function maybeSyncHongkongHomepageHistory(array $draw = null, $runAfterDrawGenerator = true)
    {
        if (!$this->shouldSyncHongkongHomepageHistory($draw)) {
            return $draw;
        }

        $tickKey = $this->hongkongHistorySyncTickKey();
        $lastTick = (int) $this->app->cache()->get($tickKey, 0);
        if ($lastTick > 0 && (time() - $lastTick) < $this->hongkongHistorySyncIntervalSeconds()) {
            return $draw;
        }

        $this->app->cache()->put($tickKey, time());
        $this->syncHongkongCurrentYearHistory($runAfterDrawGenerator);

        $latestStored = $this->latestStoredDrawRow('hongkong');
        if (!is_array($latestStored)) {
            return $draw;
        }

        return $this->normalizeHomepageStoredDraw('hongkong', $latestStored);
    }

    public function hongkongCurrentYearHistoryDraws($limit = 160, $runAfterDrawGenerator = true)
    {
        $latest = $this->syncRemoteLatestDraw('hongkong', $runAfterDrawGenerator);
        $latestIssue = is_array($latest) && !empty($latest['issue_no']) ? trim((string) $latest['issue_no']) : '';
        $issueYear = preg_match('/^\d{7}$/', $latestIssue) ? substr($latestIssue, 0, 4) : date('Y');

        $this->syncHongkongCurrentYearHistory($runAfterDrawGenerator);

        return $this->latestDrawsByIssueYear('hongkong', $issueYear, $limit);
    }

    public function syncMacauCurrentYearHistory()
    {
        $latest = $this->latestHomepageDraw('macau');
        $latestIssue = is_array($latest) && !empty($latest['issue_no']) ? trim((string) $latest['issue_no']) : '';
        $issueYear = preg_match('/^\d{7}$/', $latestIssue) ? substr($latestIssue, 0, 4) : date('Y');
        $this->cleanupDuplicateDrawRows('macau', $issueYear);
        $this->cleanupDuplicateIssueRows('macau');

        $response = $this->fetchRemoteJson($this->macauHistoryYearUrl($issueYear));
        $rows = $this->parseMacauHistoryRows($response, $issueYear);
        if (empty($rows)) {
            return array();
        }

        $imported = array();
        foreach ($rows as $row) {
            $this->persistHistoricalDraw('macau', $row);
            $imported[(string) $row['issue_no']] = true;
        }

        $this->cleanupSampleDraws('macau', $issueYear, array_keys($imported));
        $this->cleanupStaleHistoricalDraws('macau', $issueYear, array_keys($imported), $latestIssue !== '' ? array($latestIssue) : array());
        $this->cleanupDuplicateDrawRows('macau', $issueYear);

        return array_keys($imported);
    }

    public function macauCurrentYearHistoryDraws($limit = 160)
    {
        $latest = $this->latestHomepageDraw('macau');
        $latestIssue = is_array($latest) && !empty($latest['issue_no']) ? trim((string) $latest['issue_no']) : '';
        $issueYear = preg_match('/^\d{7}$/', $latestIssue) ? substr($latestIssue, 0, 4) : date('Y');

        $this->syncMacauCurrentYearHistory();

        return $this->mergeLatestDrawIntoHistoryRows(
            'macau',
            $this->latestDrawsByIssueYear('macau', $issueYear, $limit + 1),
            is_array($latest) ? $latest : null,
            $issueYear,
            $limit
        );
    }

    public function maybePollMacauLiveResult($runAfterDrawGenerator = true)
    {
        if (!$this->isMacauRealtimeWindow()) {
            return $this->syncRemoteLatestDraw('macau', $runAfterDrawGenerator);
        }

        $stateKey = $this->macauRealtimeStateKey();
        $tickKey = $this->macauRealtimeTickKey();
        $state = $this->app->cache()->get($stateKey, array());
        $cachedDraw = $this->app->cache()->get($this->macauLiveCacheKey(), null, $this->macauLiveCacheTtlSeconds());

        if (is_array($state) && !empty($state['completed'])) {
            return is_array($cachedDraw) ? $cachedDraw : null;
        }

        $lastTick = (int) $this->app->cache()->get($tickKey, 0);
        if ($lastTick > 0 && (time() - $lastTick) < $this->macauRealtimePollIntervalSeconds()) {
            return is_array($cachedDraw) ? $cachedDraw : null;
        }

        $latestStored = $this->latestStoredDrawRow('macau');
        $watchIssue = is_array($state) && isset($state['watch_issue']) ? (string) $state['watch_issue'] : '';
        if ($watchIssue === '') {
            $watchIssue = $latestStored ? (string) $latestStored['issue_no'] : '';
        }

        $this->app->cache()->put($stateKey, array(
            'date' => date('Y-m-d'),
            'watch_issue' => $watchIssue,
            'completed' => false,
        ));
        $this->app->cache()->put($tickKey, time());

        $response = $this->fetchRemoteJson($this->macauApiUrl());
        $remoteRow = $this->extractRemoteLatestRow($response);
        $parsed = is_array($remoteRow) ? $this->parseRemoteDraw($remoteRow, 'macau', 'remote:live2', true) : null;
        if (!is_array($parsed)) {
            return null;
        }

        $this->app->cache()->put($this->macauLiveCacheKey(), $parsed);

        if (!$this->isAdvancedIssue($parsed['issue_no'], $watchIssue)) {
            return $parsed;
        }

        if (!empty($parsed['is_partial'])) {
            return $parsed;
        }

        $draw = $this->persistFetchedDraw('macau', $remoteRow, 'remote:live2', $runAfterDrawGenerator);
        if (!is_array($draw)) {
            return null;
        }

        $this->app->cache()->put($this->macauLiveCacheKey(), $draw);
        $this->app->cache()->put($stateKey, array(
            'date' => date('Y-m-d'),
            'watch_issue' => $watchIssue,
            'completed' => true,
            'completed_issue' => $draw['issue_no'],
            'completed_at' => $this->now(),
        ));

        return $draw;
    }

    public function maybePollHongkongLiveResult($runAfterDrawGenerator = true)
    {
        $cacheKey = $this->hongkongLiveCacheKey();
        $current = $this->app->cache()->get($cacheKey, null);

        if (!$this->isHongkongRealtimeWindow($current)) {
            $synced = $this->syncRemoteLatestDraw('hongkong', $runAfterDrawGenerator);

            return is_array($synced) && !empty($synced['issue_no'])
                ? $synced
                : (is_array($current) && !empty($current['issue_no']) ? $current : null);
        }

        if (!is_array($current) || empty($current['issue_no'])) {
            $latestStored = $this->latestStoredDrawRow('hongkong');
            $current = is_array($latestStored) ? $this->normalizeStoredDraw($latestStored) : null;
            if (is_array($current)) {
                $current = $this->refreshDrawNextOpenTime($current, 'hongkong');
            }
        }

        if (!is_array($current) || empty($current['issue_no'])) {
            return null;
        }

        $windowDate = date('Ymd');
        $stateKey = $this->hongkongRealtimeStateKey($windowDate);
        $tickKey = $this->hongkongRealtimeTickKey($windowDate);
        $state = $this->app->cache()->get($stateKey, array());
        $watchIssue = is_array($state) && isset($state['watch_issue']) ? (string) $state['watch_issue'] : '';

        if ($watchIssue !== (string) $current['issue_no']) {
            $watchIssue = (string) $current['issue_no'];
            $state = array(
                'date' => date('Y-m-d'),
                'watch_issue' => $watchIssue,
                'watch_open_time' => date('Y-m-d') . ' ' . $this->macauRealtimePollStart(),
                'completed' => false,
            );
            $this->app->cache()->put($stateKey, $state);
            $this->app->cache()->forget($tickKey);
        }

        if (is_array($state) && !empty($state['completed'])) {
            return $this->app->cache()->get($cacheKey, $current);
        }

        $lastTick = (int) $this->app->cache()->get($tickKey, 0);
        if ($lastTick > 0 && (time() - $lastTick) < $this->hongkongRealtimePollIntervalSeconds()) {
            return $current;
        }

        $this->app->cache()->put($tickKey, time());

        $response = $this->fetchRemoteJson($this->hongkongApiUrl());
        $remoteRow = $this->extractRemoteLatestRow($response);
        $parsed = is_array($remoteRow) ? $this->parseRemoteDraw($remoteRow, 'hongkong', 'remote:hkjc.com', true) : null;
        if (!is_array($parsed)) {
            return $current;
        }

        $this->app->cache()->put($cacheKey, $parsed);

        if (!$this->isAdvancedIssue($parsed['issue_no'], $watchIssue)) {
            return $parsed;
        }

        if (!empty($parsed['is_partial'])) {
            return $parsed;
        }

        $draw = $this->persistFetchedDraw('hongkong', $remoteRow, 'remote:hkjc.com', $runAfterDrawGenerator);
        if (!is_array($draw)) {
            return $current;
        }

        $this->app->cache()->put($cacheKey, $draw);
        $this->app->cache()->put($stateKey, array(
            'date' => date('Y-m-d'),
            'watch_issue' => $watchIssue,
            'watch_open_time' => date('Y-m-d') . ' ' . $this->macauRealtimePollStart(),
            'completed' => true,
            'completed_issue' => $draw['issue_no'],
            'completed_at' => $this->now(),
        ));

        return $draw;
    }

    public function syncRemoteLatestDraw($region, $runAfterDrawGenerator = true)
    {
        if (!in_array($region, array('macau', 'hongkong'), true)) {
            return null;
        }

        $config = $this->remoteDrawConfig($region);
        if (!is_array($config)) {
            return null;
        }

        $cacheKey = (string) $config['cache_key'];
        $cacheTtl = $this->macauLiveCacheTtlSeconds();
        if (($region === 'macau' && !$this->isMacauRealtimeWindow())
            || ($region === 'hongkong' && !$this->isHongkongRealtimeWindow())
        ) {
            $cacheTtl = 900;
        }
        $cached = $this->app->cache()->get($cacheKey, null, $cacheTtl);
        if (is_array($cached) && !empty($cached['issue_no'])) {
            $cachedNote = trim((string) ($cached['note'] ?? ''));
            if ($cachedNote !== '' && $cachedNote !== (string) $config['note']) {
                $this->app->cache()->forget($cacheKey);
            } else {
                if (!empty($cached['open_time'])) {
                    $cached = $this->refreshDrawNextOpenTime($cached, $region);
                    $this->app->cache()->put($cacheKey, $cached);
                }
                return $cached;
            }
        }

        $remoteRow = null;
        $remoteNote = (string) $config['note'];
        $urls = !empty($config['urls']) && is_array($config['urls'])
            ? array_values($config['urls'])
            : array((string) $config['url']);
        foreach ($urls as $url) {
            $response = $this->fetchRemoteJson((string) $url);
            $remoteRow = $this->extractRemoteLatestRow($response);
            if (is_array($remoteRow)) {
                break;
            }
        }
        if (!is_array($remoteRow)) {
            return null;
        }

        $parsed = $this->parseRemoteDraw($remoteRow, $region, $remoteNote, true);
        if (!is_array($parsed) || !empty($parsed['is_partial'])) {
            return null;
        }

        $latestStored = $this->latestStoredDrawRow($region);
        $latestStoredIssue = is_array($latestStored) ? trim((string) ($latestStored['issue_no'] ?? '')) : '';
        if ($latestStoredIssue !== '' && !$this->isAdvancedIssue((string) $parsed['issue_no'], $latestStoredIssue)) {
            if ($runAfterDrawGenerator && (string) $parsed['issue_no'] === $latestStoredIssue) {
                try {
                    $this->app->admins()->runManagedPostGeneratorAfterDraw($region, $parsed);
                } catch (\Throwable $exception) {
                    $this->app->admins()->writeManagedExceptionLog($exception, 'posts', 'post_generator_after_draw', 'system', 0);
                }
            }
            $this->app->cache()->put($cacheKey, $parsed);

            return $parsed;
        }

        $draw = $this->persistFetchedDraw($region, $remoteRow, $remoteNote, $runAfterDrawGenerator);
        if (is_array($draw)) {
            $this->app->cache()->put($cacheKey, $draw);
        }

        return $draw;
    }

    public function latestHomepageDraw($region)
    {
        $cacheKey = (string) $region;
        if (array_key_exists($cacheKey, $this->latestHomepageDrawRuntimeCache)) {
            return $this->latestHomepageDrawRuntimeCache[$cacheKey];
        }

        $draws = $this->latestDraws($region, 1);
        $normalized = null;

        if (!empty($draws)) {
            $normalized = $this->normalizeHomepageStoredDraw($region, $draws[0]);
        }

        if (!in_array($region, array('macau', 'hongkong'), true)) {
            $draw = $this->applyHomepageIssueSchedule($region, $normalized);
            $this->latestHomepageDrawRuntimeCache[$cacheKey] = $draw;
            return $draw;
        }

        $isRealtimeWindow = $region === 'hongkong'
            ? $this->isHongkongRealtimeWindow(is_array($normalized) ? $normalized : null)
            : $this->isMacauRealtimeWindow();
        if (!$isRealtimeWindow && is_array($normalized) && !empty($normalized['issue_no'])) {
            $draw = $this->applyHomepageIssueSchedule($region, $normalized);
            $this->latestHomepageDrawRuntimeCache[$cacheKey] = $draw;
            return $draw;
        }

        if ($region === 'hongkong') {
            $normalized = $this->maybeSyncHongkongHomepageHistory(is_array($normalized) ? $normalized : null, false);
        }

        $remote = $region === 'hongkong'
            ? $this->maybePollHongkongLiveResult(false)
            : $this->maybePollMacauLiveResult(false);
        if (!is_array($remote) || empty($remote['issue_no'])) {
            $draw = $this->applyHomepageIssueSchedule($region, $normalized);
            $this->latestHomepageDrawRuntimeCache[$cacheKey] = $draw;
            return $draw;
        }

        $remote = $this->refreshDrawNextOpenTime($remote, $region);

        if (!is_array($normalized) || empty($normalized['issue_no'])) {
            $draw = $this->applyHomepageIssueSchedule($region, $remote);
            $this->latestHomepageDrawRuntimeCache[$cacheKey] = $draw;
            return $draw;
        }

        if ($this->isAdvancedIssue($remote['issue_no'], $normalized['issue_no'])) {
            $draw = $this->applyHomepageIssueSchedule($region, $remote);
            $this->latestHomepageDrawRuntimeCache[$cacheKey] = $draw;
            return $draw;
        }

        $draw = $this->applyHomepageIssueSchedule($region, $normalized);
        $this->latestHomepageDrawRuntimeCache[$cacheKey] = $draw;
        return $draw;
    }

    public function drawZodiacByNumber($number, $drawDate)
    {
        $number = (int) $number;
        if ($number <= 0 || $number > 49) {
            return '';
        }

        $lunarNewYearDates = array(
            2020 => '2020-01-25',
            2021 => '2021-02-12',
            2022 => '2022-02-01',
            2023 => '2023-01-22',
            2024 => '2024-02-10',
            2025 => '2025-01-29',
            2026 => '2026-02-17',
            2027 => '2027-02-06',
            2028 => '2028-01-26',
            2029 => '2029-02-13',
            2030 => '2030-02-03',
            2031 => '2031-01-23',
            2032 => '2032-02-11',
            2033 => '2033-01-31',
            2034 => '2034-02-19',
            2035 => '2035-02-08',
        );
        $drawDateText = trim((string) $drawDate);
        $timestamp = $drawDateText !== '' ? strtotime($drawDateText) : false;
        $compareDate = $timestamp !== false ? date('Y-m-d', $timestamp) : date('Y-m-d');
        $year = (int) substr($compareDate, 0, 4);
        if (isset($lunarNewYearDates[$year]) && $compareDate < $lunarNewYearDates[$year]) {
            $year--;
        }

        $zodiacAnimals = array('鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪');
        $yearAnimalIndex = ($year - 4) % 12;
        if ($yearAnimalIndex < 0) {
            $yearAnimalIndex += 12;
        }
        $groupIndex = ($number - 1) % 12;
        $animalIndex = ($yearAnimalIndex - $groupIndex) % 12;
        if ($animalIndex < 0) {
            $animalIndex += 12;
        }

        return $zodiacAnimals[$animalIndex];
    }

    public function frontHomepageDraw($region)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $cacheKey = 'front_homepage_draw_' . $region;
        $cached = $this->app->cache()->get($cacheKey, null, 8);
        if (is_array($cached)) {
            return $cached;
        }

        $draws = $this->latestDraws($region, 1);
        $normalized = !empty($draws) ? $this->normalizeHomepageStoredDraw($region, $draws[0]) : null;
        $draw = $this->applyHomepageIssueSchedule($region, $normalized);
        if (is_array($draw)) {
            $this->app->cache()->put($cacheKey, $draw);
        }

        return $draw;
    }

    public function frontCurrentYearHistoryDraws($region, $limit = 160)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $limit = max(1, (int) $limit);
        $cacheKey = 'front_history_draws_' . $region . '_' . (int) $limit;
        $cached = $this->app->cache()->get($cacheKey, null, 60);
        if (is_array($cached)) {
            return $cached;
        }

        $latestStored = $this->latestStoredDrawRow($region);
        $latestDraw = is_array($latestStored) ? $this->normalizeHomepageStoredDraw($region, $latestStored) : null;
        $latestIssue = is_array($latestStored) ? trim((string) ($latestStored['issue_no'] ?? '')) : '';
        $issueYear = preg_match('/^\d{7}$/', $latestIssue) ? substr($latestIssue, 0, 4) : date('Y');
        $draws = $this->mergeLatestDrawIntoHistoryRows(
            $region,
            $this->latestDrawsByIssueYear($region, $issueYear, $limit + 1),
            is_array($latestDraw) ? $latestDraw : null,
            $issueYear,
            $limit
        );

        $this->app->cache()->put($cacheKey, $draws);

        return $draws;
    }

    protected function applyHomepageIssueSchedule($region, array $draw = null)
    {
        if (!$this->tableExists('lottery_issues')) {
            return $draw;
        }

        if (is_array($draw)) {
            $draw = $this->refreshDrawNextOpenTime($draw, $region);
        }

        $issue = $this->db()->fetch(
            'SELECT * FROM lottery_issues
             WHERE region = :region AND is_current = 1
             ORDER BY id DESC
             LIMIT 1',
            array('region' => $region)
        );

        if ($this->isHomepageIssueScheduleStale($issue ?: null, $draw)) {
            $issue = $this->synchronizeHomepageIssueSchedule($region, $draw, $issue ?: null);
        }

        if (!$issue || empty($issue['planned_open_at'])) {
            return $draw;
        }

        if (!is_array($draw)) {
            return array(
                'issue_no' => '',
                'draw_date' => '',
                'open_time' => null,
                'numbers' => array(),
                'special_number' => 0,
                'note' => '',
                'next_open_time' => (string) $issue['planned_open_at'],
            );
        }

        $draw['next_open_time'] = (string) $issue['planned_open_at'];
        return $draw;
    }

    protected function tableExists($tableName)
    {
        $tableName = (string) $tableName;
        if (array_key_exists($tableName, $this->tableExistsRuntimeCache)) {
            return $this->tableExistsRuntimeCache[$tableName];
        }

        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS total_count
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
            array('table_name' => $tableName)
        );

        $this->tableExistsRuntimeCache[$tableName] = $row && (int) $row['total_count'] > 0;

        return $this->tableExistsRuntimeCache[$tableName];
    }

    protected function columnExists($tableName, $columnName)
    {
        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS total_count
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :table_name
               AND COLUMN_NAME = :column_name',
            array(
                'table_name' => (string) $tableName,
                'column_name' => (string) $columnName,
            )
        );

        return $row && (int) $row['total_count'] > 0;
    }

    protected function ensurePredictionLogColumns()
    {
        if (!$this->tableExists('ai_predictions')) {
            return;
        }

        if (!$this->columnExists('ai_predictions', 'filters_json')) {
            $this->db()->pdo()->exec("ALTER TABLE ai_predictions ADD COLUMN filters_json TEXT DEFAULT NULL AFTER confidence");
        }

        if (!$this->columnExists('ai_predictions', 'display_payloads_json')) {
            $this->db()->pdo()->exec("ALTER TABLE ai_predictions ADD COLUMN display_payloads_json MEDIUMTEXT DEFAULT NULL AFTER filters_json");
        }

        if (!$this->columnExists('ai_predictions', 'line_confidences_json')) {
            $this->db()->pdo()->exec("ALTER TABLE ai_predictions ADD COLUMN line_confidences_json TEXT DEFAULT NULL AFTER display_payloads_json");
        }
    }

    protected function ensureForecastParticipationSchema()
    {
        if ($this->forecastParticipationSchemaReady) {
            return;
        }

        $this->db()->execute(
            "CREATE TABLE IF NOT EXISTS ai_prediction_participations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                region VARCHAR(20) NOT NULL,
                actor_type VARCHAR(20) NOT NULL,
                actor_key VARCHAR(191) NOT NULL,
                user_id INT UNSIGNED DEFAULT NULL,
                participated_on DATE NOT NULL,
                created_at DATETIME NOT NULL,
                INDEX idx_ai_prediction_participations_actor_day (actor_type, actor_key, participated_on),
                INDEX idx_ai_prediction_participations_region_day (region, participated_on),
                INDEX idx_ai_prediction_participations_user_created (user_id, created_at),
                CONSTRAINT fk_ai_prediction_participations_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        $this->forecastParticipationSchemaReady = true;
    }

    protected function normalizeForecastRegion($region)
    {
        return in_array((string) $region, array('macau', 'hongkong'), true) ? (string) $region : 'macau';
    }

    protected function forecastActorIdentity($generatedBy = null)
    {
        $userId = (int) $generatedBy;
        if ($userId > 0) {
            return array(
                'actor_type' => 'member',
                'actor_key' => 'user:' . $userId,
                'user_id' => $userId,
                'daily_limit' => $this->forecastMemberDailyLimit(),
            );
        }

        $sessionId = function_exists('session_id') ? (string) session_id() : '';
        $identityText = implode('|', array(
            'guest',
            $sessionId,
            Security::ipAddress(),
            Security::userAgent(),
        ));

        return array(
            'actor_type' => 'guest',
            'actor_key' => hash('sha256', $identityText),
            'user_id' => null,
            'daily_limit' => 1,
        );
    }

    protected function forecastMemberDailyLimit()
    {
        $limit = 5;
        try {
            $configured = (int) $this->app->settings()->get('forecast.member_daily_limit', '5');
            if ($configured > 0) {
                $limit = $configured;
            }
        } catch (\Throwable $exception) {
            $limit = 5;
        }

        return max(1, min(9999, $limit));
    }

    protected function forecastParticipationCountForActor(array $actor, $date)
    {
        $this->ensureForecastParticipationSchema();

        $participationRow = $this->db()->fetch(
            'SELECT COUNT(*) AS total_count
             FROM ai_prediction_participations
             WHERE actor_type = :actor_type
               AND actor_key = :actor_key
               AND participated_on = :participated_on',
            array(
                'actor_type' => (string) $actor['actor_type'],
                'actor_key' => (string) $actor['actor_key'],
                'participated_on' => (string) $date,
            )
        );
        $participationCount = (int) ($participationRow['total_count'] ?? 0);

        if ((string) $actor['actor_type'] !== 'member' || (int) ($actor['user_id'] ?? 0) <= 0 || !$this->tableExists('ai_predictions')) {
            return $participationCount;
        }

        $dayStart = date('Y-m-d 00:00:00', strtotime((string) $date));
        $nextDayStart = date('Y-m-d 00:00:00', strtotime((string) $date . ' +1 day'));
        $predictionRow = $this->db()->fetch(
            'SELECT COUNT(*) AS total_count
             FROM ai_predictions
             WHERE generated_by = :generated_by
               AND created_at >= :day_start
               AND created_at < :next_day_start',
            array(
                'generated_by' => (int) $actor['user_id'],
                'day_start' => $dayStart,
                'next_day_start' => $nextDayStart,
            )
        );

        return max($participationCount, (int) ($predictionRow['total_count'] ?? 0));
    }

    public function cleanupOldMemberPredictionLogs()
    {
        $expiresAt = date('Y-m-d H:i:s', strtotime('-2 months'));

        if ($this->tableExists('ai_predictions')) {
            $this->db()->execute(
                'DELETE FROM ai_predictions WHERE generated_by IS NOT NULL AND created_at < :expires_at',
                array('expires_at' => $expiresAt)
            );
        }

        $this->ensureForecastParticipationSchema();
        $this->db()->execute(
            'DELETE FROM ai_prediction_participations WHERE created_at < :expires_at',
            array('expires_at' => $expiresAt)
        );
    }

    public function assertForecastParticipationAllowed($region, $generatedBy = null)
    {
        $this->cleanupOldMemberPredictionLogs();

        $actor = $this->forecastActorIdentity($generatedBy);
        if ((string) $actor['actor_type'] !== 'member') {
            throw new RuntimeException('请注册或登录后再参与AI预测。');
        }
        $participatedOn = date('Y-m-d');
        $usedCount = $this->forecastParticipationCountForActor($actor, $participatedOn);
        $dailyLimit = (int) $actor['daily_limit'];

        if ($usedCount >= $dailyLimit) {
            if ((string) $actor['actor_type'] === 'member') {
                throw new RuntimeException('会员每天最多参与 ' . $dailyLimit . ' 次预测，请明天再试。');
            }

            throw new RuntimeException('游客每天只能参与 1 次预测，本次结果不保留，请明天再试。');
        }

        return array(
            'region' => $this->normalizeForecastRegion($region),
            'actor' => $actor,
            'used_count' => $usedCount,
            'remaining_count' => max(0, $dailyLimit - $usedCount),
        );
    }

    public function recordForecastParticipation($region, $generatedBy = null)
    {
        $this->ensureForecastParticipationSchema();

        $actor = $this->forecastActorIdentity($generatedBy);
        $this->db()->execute(
            'INSERT INTO ai_prediction_participations (region, actor_type, actor_key, user_id, participated_on, created_at)
             VALUES (:region, :actor_type, :actor_key, :user_id, :participated_on, :created_at)',
            array(
                'region' => $this->normalizeForecastRegion($region),
                'actor_type' => (string) $actor['actor_type'],
                'actor_key' => (string) $actor['actor_key'],
                'user_id' => $actor['user_id'],
                'participated_on' => date('Y-m-d'),
                'created_at' => $this->now(),
            )
        );
    }

    public function memberPredictionLogs($userId, $limit = 20)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !$this->tableExists('ai_predictions')) {
            return array();
        }

        $this->ensurePredictionLogColumns();
        $this->cleanupOldMemberPredictionLogs();
        $expiresAt = date('Y-m-d H:i:s', strtotime('-2 months'));

        return $this->db()->fetchAll(
            'SELECT *
             FROM ai_predictions
             WHERE generated_by = :generated_by
               AND created_at >= :expires_at
             ORDER BY created_at DESC
             LIMIT ' . (int) max(1, $limit),
            array(
                'generated_by' => $userId,
                'expires_at' => $expiresAt,
            )
        );
    }

    public function deleteMemberPredictionLogs($userId, array $predictionIds)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            throw new RuntimeException('请先登录。');
        }

        if (!$this->tableExists('ai_predictions')) {
            throw new RuntimeException('预测快照记录不存在。');
        }

        $ids = array();
        foreach ($predictionIds as $predictionId) {
            $predictionId = (int) $predictionId;
            if ($predictionId > 0 && !in_array($predictionId, $ids, true)) {
                $ids[] = $predictionId;
            }
        }

        if (empty($ids)) {
            throw new RuntimeException('请先选择要删除的预测快照。');
        }

        $params = array('generated_by' => $userId);
        $placeholders = array();
        foreach ($ids as $index => $predictionId) {
            $paramName = 'id_' . $index;
            $placeholders[] = ':' . $paramName;
            $params[$paramName] = $predictionId;
        }

        $whereSql = 'generated_by = :generated_by AND id IN (' . implode(',', $placeholders) . ')';
        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS total_count FROM ai_predictions WHERE ' . $whereSql,
            $params
        );
        $deletedCount = (int) ($row['total_count'] ?? 0);
        if ($deletedCount <= 0) {
            throw new RuntimeException('未找到可删除的预测快照。');
        }

        $this->db()->execute(
            'DELETE FROM ai_predictions WHERE ' . $whereSql,
            $params
        );

        return $deletedCount;
    }

    public function latestAvailableDraws($region, $limit = 12, $runAfterDrawGenerator = false)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $limit = max(1, (int) $limit);
        $cacheKey = $region . ':' . (string) $limit;
        if (array_key_exists($cacheKey, $this->latestAvailableDrawsRuntimeCache)) {
            return $this->latestAvailableDrawsRuntimeCache[$cacheKey];
        }

        if ($region === 'hongkong') {
            $this->maybePollHongkongLiveResult($runAfterDrawGenerator);
            $draws = $this->latestForecastSourceDraws($region, $limit);
            $this->latestAvailableDrawsRuntimeCache[$cacheKey] = $draws;
            return $draws;
        }

        if ($region === 'macau') {
            $this->maybePollMacauLiveResult($runAfterDrawGenerator);
            $draws = $this->latestForecastSourceDraws($region, $limit);
            $this->latestAvailableDrawsRuntimeCache[$cacheKey] = $draws;
            return $draws;
        }

        $draws = $this->latestForecastSourceDraws($region, $limit);
        $this->latestAvailableDrawsRuntimeCache[$cacheKey] = $draws;
        return $draws;
    }

    public function latestDraws($region, $limit = 12)
    {
        return $this->db()->fetchAll('SELECT * FROM lottery_draws WHERE region = :region' . $this->drawOrderSql() . ' LIMIT ' . (int) $limit, array(
            'region' => $region,
        ));
    }

    public function recentPredictions($region, $limit = 5)
    {
        return $this->db()->fetchAll('SELECT * FROM ai_predictions WHERE region = :region ORDER BY created_at DESC LIMIT ' . (int) $limit, array(
            'region' => $region,
        ));
    }

    public function frontCachedForecast($region, array $filters = array(), $ttl = 300, $currentIssueNo = '')
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $filters = $this->normalizeForecastFilters($filters);
        $ttl = max(30, (int) $ttl);
        $currentIssueNo = trim((string) $currentIssueNo);
        $cacheKey = 'front_forecast_preview_' . $region . '_' . md5(json_encode($filters));
        $cachedPayload = $this->readCachePayload($cacheKey);
        $cached = is_array($cachedPayload) && array_key_exists('value', $cachedPayload)
            ? $cachedPayload['value']
            : null;
        $cacheAge = isset($cachedPayload['stored_at']) ? time() - (int) $cachedPayload['stored_at'] : null;

        if ($this->forecastPreviewCacheUsable($cached, $currentIssueNo)) {
            if ($cacheAge === null || $cacheAge <= $ttl) {
                return $cached;
            }

            $this->refreshFrontCachedForecastAfterResponse($cacheKey, $region, $filters);
            return $cached;
        }

        $generated = $this->buildForecast($region, null, false, $filters);
        if (is_array($generated) && !empty($generated['generated_for_issue'])) {
            $this->app->cache()->put($cacheKey, $generated);
        }

        return $generated;
    }

    public function prewarmFrontForecastPreviewCaches($ttl = 300)
    {
        $ttl = max(30, (int) $ttl);
        $filters = $this->normalizeForecastFilters(array());
        $jobs = array();

        foreach (array('macau', 'hongkong') as $region) {
            $cacheKey = 'front_forecast_preview_' . $region . '_' . md5(json_encode($filters));
            $cachedPayload = $this->readCachePayload($cacheKey);
            $cached = is_array($cachedPayload) && array_key_exists('value', $cachedPayload)
                ? $cachedPayload['value']
                : null;
            $cacheAge = isset($cachedPayload['stored_at']) ? time() - (int) $cachedPayload['stored_at'] : null;
            $currentIssueNo = $this->scheduledForecastIssueNo($region);

            if ($this->forecastPreviewCacheUsable($cached, $currentIssueNo)
                && ($cacheAge === null || $cacheAge <= $ttl)
            ) {
                continue;
            }

            $jobs[] = array(
                'region' => $region,
                'cache_key' => $cacheKey,
                'filters' => $filters,
            );
        }

        if (!$jobs) {
            return;
        }

        $this->prewarmFrontForecastPreviewCachesAfterResponse($jobs);
    }

    protected function prewarmFrontForecastPreviewCachesAfterResponse(array $jobs)
    {
        $lockPath = $this->app->cache()->path('front_forecast_preview_prewarm');
        $lockTtl = 120;
        if (is_file($lockPath) && time() - (int) @filemtime($lockPath) < $lockTtl) {
            return;
        }

        @file_put_contents($lockPath, (string) time(), LOCK_EX);
        register_shutdown_function(function () use ($jobs, $lockPath) {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }

            try {
                foreach ($jobs as $job) {
                    $generated = $this->buildForecast(
                        (string) ($job['region'] ?? 'macau'),
                        null,
                        false,
                        isset($job['filters']) && is_array($job['filters']) ? $job['filters'] : array()
                    );
                    if (is_array($generated) && !empty($generated['generated_for_issue'])) {
                        $this->app->cache()->put((string) ($job['cache_key'] ?? ''), $generated);
                    }
                }
            } catch (\Throwable $exception) {
                // Keep normal page requests independent from forecast prewarm failures.
            }

            if (is_file($lockPath)) {
                @unlink($lockPath);
            }
        });
    }

    protected function readCachePayload($cacheKey)
    {
        $path = $this->app->cache()->path($cacheKey);
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $bufferLevel = ob_get_level();
        try {
            ob_start();
            $payload = @include $path;
        } catch (\Throwable $exception) {
            while (ob_get_level() > $bufferLevel) {
                ob_end_clean();
            }

            return null;
        }
        while (ob_get_level() > $bufferLevel) {
            ob_end_clean();
        }

        return is_array($payload) ? $payload : null;
    }

    protected function forecastPreviewCacheUsable($cached, $currentIssueNo = '')
    {
        if (!is_array($cached) || empty($cached['generated_for_issue'])) {
            return false;
        }

        $currentIssueNo = trim((string) $currentIssueNo);
        if ($currentIssueNo === '') {
            return true;
        }

        return $this->isSameOrAdvancedIssue((string) $cached['generated_for_issue'], $currentIssueNo);
    }

    protected function refreshFrontCachedForecastAfterResponse($cacheKey, $region, array $filters)
    {
        $lockPath = $this->app->cache()->path('front_forecast_preview_refresh_' . md5((string) $cacheKey));
        $lockTtl = 120;
        if (is_file($lockPath) && time() - (int) @filemtime($lockPath) < $lockTtl) {
            return;
        }

        @file_put_contents($lockPath, (string) time(), LOCK_EX);
        register_shutdown_function(function () use ($cacheKey, $region, $filters, $lockPath) {
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }

            try {
                $generated = $this->buildForecast($region, null, false, $filters);
                if (is_array($generated) && !empty($generated['generated_for_issue'])) {
                    $this->app->cache()->put($cacheKey, $generated);
                }
            } catch (\Throwable $exception) {
                // Keep serving the existing preview cache when background refresh fails.
            }

            if (is_file($lockPath)) {
                @unlink($lockPath);
            }
        });
    }

    public function buildForecast($region, $generatedBy = null, $persist = true, array $filters = array())
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $filters = $this->normalizeForecastFilters($filters);
        $generatedAt = $this->now();
        $analysisPeriod = $this->forecastAnalysisPeriod();
        $baseCacheKey = $region . ':' . (string) $analysisPeriod;
        if (array_key_exists($baseCacheKey, $this->forecastBaseRuntimeCache)) {
            $baseContext = $this->forecastBaseRuntimeCache[$baseCacheKey];
            $draws = (array) ($baseContext['draws'] ?? array());
            $analysisPeriod = (int) ($baseContext['analysis_period'] ?? count($draws));
            $heatStats = (array) ($baseContext['heat_stats'] ?? array());
            $frequency = (array) ($baseContext['frequency'] ?? array());
            $rankedHotNumbers = (array) ($baseContext['ranked_hot_numbers'] ?? array());
            $hot = (array) ($baseContext['hot'] ?? array());
            $rankedColdNumbers = (array) ($baseContext['ranked_cold_numbers'] ?? array());
            $cold = (array) ($baseContext['cold'] ?? array());
        } else {
            $draws = $this->latestAvailableDraws($region, $analysisPeriod);
            if (count($draws) < 1) {
                $sourceLabel = $region === 'hongkong'
                    ? '香港API（https://api.macaumarksix.com/api/hkjc.com）'
                    : '澳门API（https://www.macaumarksix.com/api/live2）';
                throw new RuntimeException($sourceLabel . '开奖资料不足，请先同步最近 ' . $analysisPeriod . ' 期完整数据。');
            }

            $analysisPeriod = count($draws);

            $heatStats = $this->buildForecastHeatStats($draws);
            if (count($draws) < 2) {
                $heatStats = $this->applyForecastRepeatedSpecialColdRule(
                    $heatStats,
                    $this->latestAvailableDraws($region, 2)
                );
            }
            if ((int) ($heatStats['draw_count'] ?? 0) < 1) {
                $sourceLabel = $region === 'hongkong'
                    ? '香港API（https://api.macaumarksix.com/api/hkjc.com）'
                    : '澳门API（https://www.macaumarksix.com/api/live2）';
                throw new RuntimeException($sourceLabel . '开奖资料存在缺号、重复号或越界号码，请补齐最近 ' . $analysisPeriod . ' 期有效数据。');
            }
            $frequency = (array) ($heatStats['numbers'] ?? array());
            $rankedHotNumbers = $this->rankForecastNumbersByHeat($heatStats);
            $hot = array_slice($rankedHotNumbers, 0, 6);

            $coldFrequency = $frequency;
            asort($coldFrequency);
            $rankedColdNumbers = array_values(array_map('intval', array_keys($coldFrequency)));
            $cold = array_slice($rankedColdNumbers, 0, 6);
            $this->forecastBaseRuntimeCache[$baseCacheKey] = array(
                'draws' => $draws,
                'analysis_period' => $analysisPeriod,
                'heat_stats' => $heatStats,
                'frequency' => $frequency,
                'ranked_hot_numbers' => $rankedHotNumbers,
                'hot' => $hot,
                'ranked_cold_numbers' => $rankedColdNumbers,
                'cold' => $cold,
            );
        }

        $filterContext = $this->buildForecastFilterContext($frequency, $filters, $heatStats);
        $recommended = $this->buildForecastRecommendedNumbers($rankedHotNumbers, $rankedColdNumbers, $filterContext);
        $combinationCandidates = $this->buildForecastRecommendedCombinations($heatStats, $rankedHotNumbers, $filterContext);
        if (!empty($combinationCandidates[0]['numbers']) && is_array($combinationCandidates[0]['numbers'])) {
            $recommended = $this->mergeForecastRecommendedNumbers(
                (array) $combinationCandidates[0]['numbers'],
                $recommended,
                $rankedHotNumbers,
                $filterContext
            );
        }

        $lastIssue = (string) $draws[0]['issue_no'];
        $nextIssue = $this->resolveForecastIssueForMoment($region, $generatedAt, $draws, $this->incrementIssueNo($lastIssue));
        $recommended = $this->applyForecastHeatRepeatLimit($region, $nextIssue, $recommended, $rankedHotNumbers, $filterContext);
        $recommended = $this->applyForecastTypedRepeatLimits($region, $nextIssue, $recommended, $rankedHotNumbers, $filterContext, $filters, $frequency, $heatStats);
        $forecastCombinations = array($this->buildForecastHeatCombinationPayload(array_slice($recommended, 0, 7), $heatStats));

        $summary = $this->buildForecastSummary(
            count($draws),
            $recommended,
            array_slice($hot, 0, 5),
            array_slice($cold, 0, 5),
            $filterContext,
            $forecastCombinations,
            $heatStats
        );
        $displayPayloads = $this->buildForecastDisplayPayloads(
            $region,
            $nextIssue,
            $filters,
            $recommended,
            $frequency,
            $rankedHotNumbers,
            $heatStats
        );
        $displayPayloads = $this->filterForecastSpecialRepeatDisplayPayloads($displayPayloads, $heatStats, $rankedHotNumbers, $filters);
        $confidence = $this->buildForecastConfidence($region, $nextIssue, $heatStats, $recommended, $filterContext, $filters);
        $lineConfidences = $this->buildForecastLineConfidences($region, $nextIssue, $confidence, $filters, $displayPayloads, $heatStats, $recommended);

        $result = array(
            'region' => $region,
            'generated_for_issue' => $nextIssue,
            'numbers' => $recommended,
            'hot_numbers' => array_slice($hot, 0, 5),
            'cold_numbers' => array_slice($cold, 0, 5),
            'confidence' => $confidence,
            'summary' => $summary,
            'filters' => $filters,
            'display_payloads' => $displayPayloads,
            'line_confidences' => $lineConfidences,
            'forecast_stats' => $this->buildForecastStatsPayload($heatStats),
            'forecast_combinations' => $forecastCombinations,
        );

        if ($persist) {
            $this->ensurePredictionLogColumns();
            $this->db()->execute('INSERT INTO ai_predictions (region, generated_for_issue, summary, numbers_json, confidence, filters_json, display_payloads_json, line_confidences_json, generated_by, created_at) VALUES (:region, :generated_for_issue, :summary, :numbers_json, :confidence, :filters_json, :display_payloads_json, :line_confidences_json, :generated_by, :created_at)', array(
                'region' => $region,
                'generated_for_issue' => $nextIssue,
                'summary' => $summary,
                'numbers_json' => json_encode($recommended),
                'confidence' => $confidence,
                'filters_json' => json_encode($filters, JSON_UNESCAPED_UNICODE),
                'display_payloads_json' => json_encode($displayPayloads, JSON_UNESCAPED_UNICODE),
                'line_confidences_json' => json_encode($lineConfidences, JSON_UNESCAPED_UNICODE),
                'generated_by' => $generatedBy,
                'created_at' => $generatedAt,
            ));
        }

        return $result;
    }

    public function forecastZodiacPoolForGenerator($region, $minCount = 0, $maxCount = 0)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $minCount = max(0, min(12, (int) $minCount));
        $maxCount = max(0, min(12, (int) $maxCount));
        if ($minCount > $maxCount) {
            $swapCount = $minCount;
            $minCount = $maxCount;
            $maxCount = $swapCount;
        }
        if ($maxCount <= 0) {
            return array(
                'region' => $region,
                'target_count' => 0,
                'labels' => array(),
                'analysis_period' => 0,
            );
        }

        $targetCount = mt_rand($minCount, $maxCount);
        if ($targetCount <= 0) {
            return array(
                'region' => $region,
                'target_count' => 0,
                'labels' => array(),
                'analysis_period' => 0,
            );
        }

        $analysisPeriod = $this->forecastAnalysisPeriod();
        $draws = $this->latestAvailableDraws($region, $analysisPeriod);
        if (count($draws) < 1) {
            $sourceLabel = $region === 'hongkong'
                ? '香港API（https://api.macaumarksix.com/api/hkjc.com）'
                : '澳门API（https://www.macaumarksix.com/api/live2）';
            throw new RuntimeException($sourceLabel . '开奖资料不足，请先同步最近 ' . $analysisPeriod . ' 期完整数据。');
        }

        $heatStats = $this->buildForecastHeatStats($draws);
        $labels = $this->resolveForecastZodiacLabels(
            (array) ($heatStats['numbers'] ?? array()),
            $targetCount,
            $heatStats,
            'special'
        );
        if (count($labels) < $targetCount) {
            foreach ($this->rankForecastStatLabels((array) ($heatStats['special_zodiacs'] ?? array()), 12) as $zodiac) {
                if (!in_array($zodiac, $labels, true)) {
                    $labels[] = $zodiac;
                }
                if (count($labels) >= $targetCount) {
                    break;
                }
            }
        }
        if (count($labels) < $targetCount) {
            foreach (array_keys($this->forecastZodiacNumberMap()) as $zodiac) {
                if (!in_array($zodiac, $labels, true)) {
                    $labels[] = (string) $zodiac;
                }
                if (count($labels) >= $targetCount) {
                    break;
                }
            }
        }

        return array(
            'region' => $region,
            'target_count' => $targetCount,
            'labels' => array_slice(array_values($labels), 0, $targetCount),
            'analysis_period' => count($draws),
            'forecast_stats' => $this->buildForecastStatsPayload($heatStats),
        );
    }

    protected function normalizeForecastFilters(array $filters)
    {
        return array(
            'zodiac_type' => trim((string) ($filters['zodiac_type'] ?? '')),
            'number_type' => trim((string) ($filters['number_type'] ?? '')),
            'pingte_type' => trim((string) ($filters['pingte_type'] ?? '')),
            'other_type' => trim((string) ($filters['other_type'] ?? '')),
        );
    }

    protected function forecastAnalysisPeriod()
    {
        $periodMin = 20;
        $periodMax = 20;
        try {
            $legacyPeriod = (int) $this->app->settings()->get('forecast.analysis_period', '20');
            $configuredMin = (int) $this->app->settings()->get('forecast.analysis_period_min', (string) $legacyPeriod);
            $configuredMax = (int) $this->app->settings()->get('forecast.analysis_period_max', (string) $legacyPeriod);
            if ($configuredMin > 0) {
                $periodMin = $configuredMin;
            }
            if ($configuredMax > 0) {
                $periodMax = $configuredMax;
            }
        } catch (\Throwable $exception) {
            $periodMin = 20;
            $periodMax = 20;
        }
        $periodMin = max(1, min(10000, $periodMin));
        $periodMax = max(1, min(10000, $periodMax));
        if ($periodMin > $periodMax) {
            $periodSwap = $periodMin;
            $periodMin = $periodMax;
            $periodMax = $periodSwap;
        }
        if ($periodMin === $periodMax) {
            return $periodMin;
        }

        return mt_rand($periodMin, $periodMax);
    }

    protected function buildForecastHeatStats(array $draws)
    {
        $zodiacMap = $this->forecastZodiacNumberMap();
        $waveMap = $this->forecastWaveNumberMap();
        $elementMap = $this->forecastFiveElementNumberMap();
        $numberFrequency = array();
        $normalFrequency = array();
        $specialFrequency = array();
        $headFrequency = array_fill(0, 5, 0.0);
        $tailFrequency = array_fill(0, 10, 0.0);
        for ($number = 1; $number <= 49; $number++) {
            $numberFrequency[$number] = 0.0;
            $normalFrequency[$number] = 0.0;
            $specialFrequency[$number] = 0.0;
        }

        $zodiacFrequency = array_fill_keys(array_keys($zodiacMap), 0.0);
        $normalZodiacFrequency = array_fill_keys(array_keys($zodiacMap), 0.0);
        $specialZodiacFrequency = array_fill_keys(array_keys($zodiacMap), 0.0);
        $waveFrequency = array_fill_keys(array_keys($waveMap), 0.0);
        $specialWaveFrequency = array_fill_keys(array_keys($waveMap), 0.0);
        $elementFrequency = array_fill_keys(array_keys($elementMap), 0.0);
        $specialElementFrequency = array_fill_keys(array_keys($elementMap), 0.0);
        $oddEvenFrequency = array('单' => 0.0, '双' => 0.0);
        $specialOddEvenFrequency = array('单' => 0.0, '双' => 0.0);
        $specialBigSmallFrequency = array('小' => 0.0, '大' => 0.0);
        $specialHeadFrequency = array_fill(0, 5, 0.0);
        $specialTailFrequency = array_fill(0, 10, 0.0);
        $waveRows = array();
        $validDrawCount = 0;
        $totalOpenCount = 0;

        foreach ($draws as $draw) {
            if (!is_array($draw)) {
                continue;
            }

            $parts = $this->extractForecastDrawNumberParts($draw);
            if (empty($parts)) {
                continue;
            }

            $validDrawCount++;
            $drawNumbers = (array) $parts['all'];
            $drawWaveCounts = array('红波' => 0, '蓝波' => 0, '绿波' => 0);

            foreach ((array) $parts['normal'] as $number) {
                $number = (int) $number;
                $normalFrequency[$number] += 1.0;
                $normalZodiac = $this->resolveForecastGroupLabel($zodiacMap, $number);
                if ($normalZodiac !== '' && isset($normalZodiacFrequency[$normalZodiac])) {
                    $normalZodiacFrequency[$normalZodiac] += 1.0;
                }
            }

            $specialNumber = (int) $parts['special'];
            $specialFrequency[$specialNumber] += 1.0;
            $specialZodiac = $this->resolveForecastGroupLabel($zodiacMap, $specialNumber);
            if ($specialZodiac !== '' && isset($specialZodiacFrequency[$specialZodiac])) {
                $specialZodiacFrequency[$specialZodiac] += 1.0;
            }
            $specialWave = $this->resolveForecastGroupLabel($waveMap, $specialNumber);
            if ($specialWave !== '' && isset($specialWaveFrequency[$specialWave])) {
                $specialWaveFrequency[$specialWave] += 1.0;
            }
            $specialElement = $this->resolveForecastGroupLabel($elementMap, $specialNumber);
            if ($specialElement !== '' && isset($specialElementFrequency[$specialElement])) {
                $specialElementFrequency[$specialElement] += 1.0;
            }
            $specialOddEvenFrequency[$specialNumber % 2 === 0 ? '双' : '单'] += 1.0;
            $specialBigSmallFrequency[$specialNumber <= 24 ? '小' : '大'] += 1.0;
            $specialHeadFrequency[(int) floor($specialNumber / 10)] += 1.0;
            $specialTailFrequency[$specialNumber % 10] += 1.0;

            foreach ($drawNumbers as $number) {
                $number = (int) $number;
                if ($number < 1 || $number > 49) {
                    continue;
                }

                $numberFrequency[$number] += 1.0;
                $totalOpenCount++;

                $zodiac = $this->resolveForecastGroupLabel($zodiacMap, $number);
                if ($zodiac !== '' && isset($zodiacFrequency[$zodiac])) {
                    $zodiacFrequency[$zodiac] += 1.0;
                }

                $wave = $this->resolveForecastGroupLabel($waveMap, $number);
                if ($wave !== '' && isset($waveFrequency[$wave])) {
                    $waveFrequency[$wave] += 1.0;
                    $drawWaveCounts[$wave] += 1;
                }

                $element = $this->resolveForecastGroupLabel($elementMap, $number);
                if ($element !== '' && isset($elementFrequency[$element])) {
                    $elementFrequency[$element] += 1.0;
                }

                $oddEven = $number % 2 === 0 ? '双' : '单';
                $oddEvenFrequency[$oddEven] += 1.0;
                $headFrequency[(int) floor($number / 10)] += 1.0;
                $tailFrequency[$number % 10] += 1.0;
            }

            $waveRows[] = $drawWaveCounts;
        }

        $coldNumbers = array();
        $activeNumbers = array();
        foreach ($numberFrequency as $number => $count) {
            $number = (int) $number;
            if ((float) $count <= 0.0) {
                $coldNumbers[] = $number;
            } else {
                $activeNumbers[] = $number;
            }
        }

        $coreNormalNumbers = array();
        $warmNormalNumbers = array();
        foreach ($normalFrequency as $number => $count) {
            if ((float) $count >= 5.0) {
                $coreNormalNumbers[] = (int) $number;
            } elseif ((float) $count >= 3.0) {
                $warmNormalNumbers[] = (int) $number;
            }
        }

        $hotSpecialNumbers = array();
        $warmSpecialNumbers = array();
        foreach ($specialFrequency as $number => $count) {
            if ((float) $count >= 2.0) {
                $hotSpecialNumbers[] = (int) $number;
            } elseif ((float) $count >= 1.0 && in_array((int) $number, array_merge($coreNormalNumbers, $warmNormalNumbers), true)) {
                $warmSpecialNumbers[] = (int) $number;
            }
        }

        $hotHeads = $this->rankForecastStatLabels($this->formatForecastHeadTailScores($headFrequency, '头'), 3);
        $hotTails = $this->rankForecastStatLabels($this->formatForecastHeadTailScores($tailFrequency, '尾'), 4);
        $averageZodiacCount = $validDrawCount > 0 ? ($totalOpenCount / max(1, count($zodiacFrequency))) : 0.0;
        $hotZodiacs = array();
        foreach ($zodiacFrequency as $zodiac => $count) {
            if ((float) $count > $averageZodiacCount) {
                $hotZodiacs[] = (string) $zodiac;
            }
        }
        usort($hotZodiacs, function ($left, $right) use ($zodiacFrequency) {
            $leftScore = (float) ($zodiacFrequency[$left] ?? 0.0);
            $rightScore = (float) ($zodiacFrequency[$right] ?? 0.0);
            if ($leftScore === $rightScore) {
                return strcmp((string) $left, (string) $right);
            }

            return $leftScore < $rightScore ? 1 : -1;
        });

        $coldZodiacs = array();
        foreach ($zodiacFrequency as $zodiac => $count) {
            if ((float) $count <= 0.0) {
                $coldZodiacs[] = (string) $zodiac;
            }
        }
        $normalColdZodiacs = array();
        foreach ($normalZodiacFrequency as $zodiac => $count) {
            if ((float) $count <= 0.0) {
                $normalColdZodiacs[] = (string) $zodiac;
            }
        }
        $specialColdZodiacs = array();
        foreach ($specialZodiacFrequency as $zodiac => $count) {
            if ((float) $count <= 0.0) {
                $specialColdZodiacs[] = (string) $zodiac;
            }
        }

        $waveAverage = array();
        foreach ($waveFrequency as $wave => $count) {
            $waveAverage[$wave] = $validDrawCount > 0 ? round(((float) $count / $validDrawCount), 2) : 0.0;
        }
        $previousSpecialDowngrade = array(
            'active' => false,
            'numbers' => array(),
            'zodiacs' => array(),
        );
        if (!empty($draws[0]) && is_array($draws[0])) {
            $latestParts = $this->extractForecastDrawNumberParts($draws[0]);
            if (!empty($latestParts['special'])) {
                $latestSpecialNumber = (int) $latestParts['special'];
                $latestSpecialZodiac = $this->resolveForecastGroupLabel($zodiacMap, $latestSpecialNumber);
                $previousSpecialDowngrade = array(
                    'active' => $latestSpecialZodiac !== '',
                    'issue_no' => trim((string) ($draws[0]['issue_no'] ?? '')),
                    'special_number' => $latestSpecialNumber,
                    'numbers' => array($latestSpecialNumber),
                    'zodiacs' => $latestSpecialZodiac !== '' ? array($latestSpecialZodiac) : array(),
                );
            }
        }
        $blockedNumbers = array();
        $blockedZodiacs = array();
        $downgradedZodiacs = array();
        foreach ((array) ($previousSpecialDowngrade['zodiacs'] ?? array()) as $zodiac) {
            $zodiac = trim((string) $zodiac);
            if ($zodiac !== '') {
                $downgradedZodiacs[$zodiac] = 1.0;
            }
        }
        $numberLevels = $this->buildForecastNumberHeatLevels($numberFrequency);
        $normalNumberLevels = $this->buildForecastNumberHeatLevels($normalFrequency);
        $specialNumberLevels = $this->buildForecastNumberHeatLevels($specialFrequency);
        $zodiacLevels = $this->buildForecastZodiacHeatLevels($zodiacFrequency, $downgradedZodiacs);
        $specialZodiacLevels = $this->buildForecastZodiacHeatLevels($specialZodiacFrequency, $downgradedZodiacs);
        $normalZodiacLevels = $this->buildForecastZodiacHeatLevels($normalZodiacFrequency, array());

        return array(
            'numbers' => $numberFrequency,
            'normal_numbers' => $normalFrequency,
            'special_numbers' => $specialFrequency,
            'zodiacs' => $zodiacFrequency,
            'normal_zodiacs' => $normalZodiacFrequency,
            'special_zodiacs' => $specialZodiacFrequency,
            'waves' => $waveFrequency,
            'special_waves' => $specialWaveFrequency,
            'wave_average' => $waveAverage,
            'wave_trend' => $this->buildForecastWaveTrend($waveRows),
            'elements' => $elementFrequency,
            'special_elements' => $specialElementFrequency,
            'odd_even' => $oddEvenFrequency,
            'special_odd_even' => $specialOddEvenFrequency,
            'special_big_small' => $specialBigSmallFrequency,
            'heads' => $headFrequency,
            'tails' => $tailFrequency,
            'special_heads' => $specialHeadFrequency,
            'special_tails' => $specialTailFrequency,
            'number_levels' => $numberLevels,
            'normal_number_levels' => $normalNumberLevels,
            'special_number_levels' => $specialNumberLevels,
            'zodiac_levels' => $zodiacLevels,
            'special_zodiac_levels' => $specialZodiacLevels,
            'normal_zodiac_levels' => $normalZodiacLevels,
            'core_normal_numbers' => $coreNormalNumbers,
            'warm_normal_numbers' => $warmNormalNumbers,
            'hot_special_numbers' => $hotSpecialNumbers,
            'warm_special_numbers' => $warmSpecialNumbers,
            'hot_heads' => $hotHeads,
            'hot_tails' => $hotTails,
            'hot_zodiacs' => $hotZodiacs,
            'cold_zodiacs' => $coldZodiacs,
            'normal_cold_zodiacs' => $normalColdZodiacs,
            'special_cold_zodiacs' => $specialColdZodiacs,
            'cold_numbers' => $coldNumbers,
            'active_numbers' => $activeNumbers,
            'downgraded_numbers' => array_values(array_unique($coldNumbers)),
            'downgraded_zodiacs' => $downgradedZodiacs,
            'blocked_numbers' => $blockedNumbers,
            'blocked_zodiacs' => $blockedZodiacs,
            'repeat_special_cold_rule' => $previousSpecialDowngrade,
            'draw_count' => $validDrawCount,
            'total_open_count' => $totalOpenCount,
            'normal_open_count' => $validDrawCount * 6,
            'special_open_count' => $validDrawCount,
        );
    }

    protected function buildForecastNumberHeatLevels(array $scores)
    {
        $numbers = range(1, 49);
        usort($numbers, function ($left, $right) use ($scores) {
            $leftScore = (float) ($scores[$left] ?? 0.0);
            $rightScore = (float) ($scores[$right] ?? 0.0);
            if ($leftScore === $rightScore) {
                return $left <=> $right;
            }

            return $leftScore < $rightScore ? 1 : -1;
        });

        $levels = array();
        foreach ($numbers as $index => $number) {
            $score = (float) ($scores[$number] ?? 0.0);
            if ($score <= 0.0) {
                $levels[$number] = 4;
            } elseif ($index < 10) {
                $levels[$number] = 1;
            } elseif ($index < 20) {
                $levels[$number] = 2;
            } elseif ($index < 30) {
                $levels[$number] = 3;
            } else {
                $levels[$number] = 4;
            }
        }

        return $levels;
    }

    protected function buildForecastZodiacHeatLevels(array $scores, array $downgradedZodiacs)
    {
        $zodiacs = array_keys($this->forecastZodiacNumberMap());
        usort($zodiacs, function ($left, $right) use ($scores) {
            $leftScore = (float) ($scores[$left] ?? 0.0);
            $rightScore = (float) ($scores[$right] ?? 0.0);
            if ($leftScore === $rightScore) {
                return strcmp((string) $left, (string) $right);
            }

            return $leftScore < $rightScore ? 1 : -1;
        });

        $levels = array();
        foreach ($zodiacs as $index => $zodiac) {
            $score = (float) ($scores[$zodiac] ?? 0.0);
            if ($score <= 0.0) {
                $level = 4;
            } elseif ($index < 3) {
                $level = 1;
            } elseif ($index < 6) {
                $level = 2;
            } elseif ($index < 9) {
                $level = 3;
            } else {
                $level = 4;
            }
            if (isset($downgradedZodiacs[$zodiac]) && $level < 4) {
                $level = min(3, $level + 1);
            }
            $levels[$zodiac] = $level;
        }

        return $levels;
    }

    protected function buildForecastRepeatedSpecialColdRule(array $draws)
    {
        $specialRows = array();
        foreach ($draws as $draw) {
            if (!is_array($draw)) {
                continue;
            }
            $parts = $this->extractForecastDrawNumberParts($draw);
            if (empty($parts)) {
                continue;
            }
            $specialRows[] = array(
                'issue_no' => trim((string) ($draw['issue_no'] ?? '')),
                'special' => (int) $parts['special'],
            );
            if (count($specialRows) >= 2) {
                break;
            }
        }

        if (count($specialRows) < 2) {
            return array(
                'active' => false,
                'numbers' => array(),
                'zodiacs' => array(),
            );
        }

        $zodiacMap = $this->forecastZodiacNumberMap();
        $specialNumber = (int) $specialRows[0]['special'];
        $previousSpecialNumber = (int) $specialRows[1]['special'];
        $zodiac = $this->resolveForecastGroupLabel($zodiacMap, $specialNumber);
        $previousZodiac = $this->resolveForecastGroupLabel($zodiacMap, $previousSpecialNumber);
        if ($zodiac === '' || $zodiac !== $previousZodiac) {
            return array(
                'active' => false,
                'numbers' => array(),
                'zodiacs' => array(),
            );
        }

        return array(
            'active' => true,
            'issue_no' => (string) $specialRows[0]['issue_no'],
            'previous_issue_no' => (string) $specialRows[1]['issue_no'],
            'special_number' => $specialNumber,
            'previous_special_number' => $previousSpecialNumber,
            'numbers' => isset($zodiacMap[$zodiac]) ? array_values(array_map('intval', $zodiacMap[$zodiac])) : array(),
            'zodiacs' => $zodiac !== '' ? array($zodiac) : array(),
        );
    }

    protected function applyForecastRepeatedSpecialColdRule(array $heatStats, array $draws)
    {
        $repeatColdRule = $this->buildForecastRepeatedSpecialColdRule($draws);
        if (empty($repeatColdRule['active'])) {
            return $heatStats;
        }

        $blockedNumbers = array_values(array_unique(array_filter(array_merge(
            array_map('intval', (array) ($heatStats['blocked_numbers'] ?? array())),
            array_map('intval', (array) ($repeatColdRule['numbers'] ?? array()))
        ), function ($number) {
            return (int) $number >= 1 && (int) $number <= 49;
        })));

        $blockedZodiacs = (array) ($heatStats['blocked_zodiacs'] ?? array());
        foreach ((array) ($repeatColdRule['zodiacs'] ?? array()) as $zodiac) {
            $zodiac = trim((string) $zodiac);
            if ($zodiac !== '') {
                $blockedZodiacs[$zodiac] = 1.0;
            }
        }

        $heatStats['blocked_numbers'] = $blockedNumbers;
        $heatStats['blocked_zodiacs'] = $blockedZodiacs;
        $heatStats['downgraded_numbers'] = array_values(array_unique(array_merge(
            array_map('intval', (array) ($heatStats['downgraded_numbers'] ?? array())),
            $blockedNumbers
        )));
        $heatStats['downgraded_zodiacs'] = $blockedZodiacs;
        $heatStats['repeat_special_cold_rule'] = $repeatColdRule;

        return $heatStats;
    }

    protected function extractForecastDrawNumberParts(array $draw)
    {
        $numbers = json_decode((string) ($draw['numbers_json'] ?? ''), true);
        if (!is_array($numbers) || count($numbers) < 6) {
            return array();
        }

        $normal = array();
        foreach (array_slice($numbers, 0, 6) as $number) {
            $number = (int) $number;
            if ($number < 1 || $number > 49) {
                return array();
            }
            $normal[] = $number;
        }

        $special = (int) ($draw['special_number'] ?? 0);
        if ($special < 1 || $special > 49) {
            return array();
        }

        $all = array_merge($normal, array($special));
        if (count(array_unique($all)) !== 7) {
            return array();
        }

        return array(
            'normal' => $normal,
            'special' => $special,
            'all' => $all,
        );
    }

    protected function extractForecastDrawNumbers(array $draw)
    {
        $parts = $this->extractForecastDrawNumberParts($draw);

        return !empty($parts['all']) ? array_values(array_map('intval', (array) $parts['all'])) : array();
    }

    protected function rankForecastNumbersByHeat(array $heatStats, $scope = 'all')
    {
        $scope = (string) $scope;
        if ($scope === 'special' || $scope === 'normal') {
            $frequencyKey = $scope === 'special' ? 'special_numbers' : 'normal_numbers';
            $levelKey = $scope === 'special' ? 'special_number_levels' : 'normal_number_levels';
            $frequency = (array) ($heatStats[$frequencyKey] ?? array());
            $levels = (array) ($heatStats[$levelKey] ?? array());
            $fallbackScores = $this->buildForecastNumberHeatScores($heatStats);
            $blockedLookup = array_fill_keys(array_values(array_map('intval', (array) ($heatStats['blocked_numbers'] ?? array()))), true);
            $numbers = range(1, 49);
            usort($numbers, function ($left, $right) use ($frequency, $levels, $fallbackScores, $blockedLookup) {
                $leftBlocked = isset($blockedLookup[(int) $left]) ? 1 : 0;
                $rightBlocked = isset($blockedLookup[(int) $right]) ? 1 : 0;
                if ($leftBlocked !== $rightBlocked) {
                    return $leftBlocked < $rightBlocked ? -1 : 1;
                }

                $leftLevel = (int) ($levels[(int) $left] ?? 4);
                $rightLevel = (int) ($levels[(int) $right] ?? 4);
                if ($leftLevel !== $rightLevel) {
                    return $leftLevel < $rightLevel ? -1 : 1;
                }

                $leftFrequency = (float) ($frequency[(int) $left] ?? 0.0);
                $rightFrequency = (float) ($frequency[(int) $right] ?? 0.0);
                if ($leftFrequency !== $rightFrequency) {
                    return $leftFrequency < $rightFrequency ? 1 : -1;
                }

                $leftScore = (float) ($fallbackScores[(int) $left] ?? 0.0);
                $rightScore = (float) ($fallbackScores[(int) $right] ?? 0.0);
                if ($leftScore === $rightScore) {
                    return (int) $left <=> (int) $right;
                }

                return $leftScore < $rightScore ? 1 : -1;
            });

            return array_values(array_map('intval', $numbers));
        }

        $scores = $this->buildForecastNumberHeatScores($heatStats);
        $frequency = (array) ($heatStats['numbers'] ?? array());
        $numbers = array_values(array_map('intval', (array) ($heatStats['active_numbers'] ?? array())));
        if (empty($numbers)) {
            return array();
        }

        usort($numbers, function ($left, $right) use ($scores, $frequency) {
            $leftScore = (float) ($scores[$left] ?? 0.0);
            $rightScore = (float) ($scores[$right] ?? 0.0);
            if ($leftScore === $rightScore) {
                $leftFrequency = (float) ($frequency[$left] ?? 0.0);
                $rightFrequency = (float) ($frequency[$right] ?? 0.0);
                if ($leftFrequency === $rightFrequency) {
                    return $left <=> $right;
                }

                return $leftFrequency < $rightFrequency ? 1 : -1;
            }

            return $leftScore < $rightScore ? 1 : -1;
        });

        return array_values(array_map('intval', $numbers));
    }

    protected function buildForecastNumberHeatScores(array $heatStats)
    {
        $zodiacMap = $this->forecastZodiacNumberMap();
        $waveMap = $this->forecastWaveNumberMap();
        $elementMap = $this->forecastFiveElementNumberMap();
        $frequency = (array) ($heatStats['numbers'] ?? array());
        $normalFrequency = (array) ($heatStats['normal_numbers'] ?? array());
        $specialFrequency = (array) ($heatStats['special_numbers'] ?? array());
        $zodiacFrequency = (array) ($heatStats['zodiacs'] ?? array());
        $waveFrequency = (array) ($heatStats['waves'] ?? array());
        $elementFrequency = (array) ($heatStats['elements'] ?? array());
        $oddEvenFrequency = (array) ($heatStats['odd_even'] ?? array());
        $coldLookup = array_fill_keys(array_values(array_map('intval', (array) ($heatStats['cold_numbers'] ?? array()))), true);
        $coreLookup = array_fill_keys(array_values(array_map('intval', (array) ($heatStats['core_normal_numbers'] ?? array()))), true);
        $warmLookup = array_fill_keys(array_values(array_map('intval', (array) ($heatStats['warm_normal_numbers'] ?? array()))), true);
        $hotSpecialLookup = array_fill_keys(array_values(array_map('intval', (array) ($heatStats['hot_special_numbers'] ?? array()))), true);
        $warmSpecialLookup = array_fill_keys(array_values(array_map('intval', (array) ($heatStats['warm_special_numbers'] ?? array()))), true);
        $hotZodiacLookup = array_fill_keys(array_values(array_map('strval', (array) ($heatStats['hot_zodiacs'] ?? array()))), true);
        $hotHeadLookup = array_fill_keys(array_values(array_map('strval', (array) ($heatStats['hot_heads'] ?? array()))), true);
        $hotTailLookup = array_fill_keys(array_values(array_map('strval', (array) ($heatStats['hot_tails'] ?? array()))), true);
        $scores = array();

        for ($number = 1; $number <= 49; $number++) {
            if (isset($coldLookup[$number])) {
                $scores[$number] = 0.0;
                continue;
            }

            $zodiac = $this->resolveForecastGroupLabel($zodiacMap, $number);
            $wave = $this->resolveForecastGroupLabel($waveMap, $number);
            $element = $this->resolveForecastGroupLabel($elementMap, $number);
            $oddEven = $number % 2 === 0 ? '双' : '单';
            $headLabel = (int) floor($number / 10) . '头';
            $tailLabel = $number % 10 . '尾';
            $zodiacBase = $zodiac !== '' && isset($zodiacMap[$zodiac]) ? max(1, count($zodiacMap[$zodiac])) : 1;
            $waveBase = $wave !== '' && isset($waveMap[$wave]) ? max(1, count($waveMap[$wave])) : 1;
            $elementBase = $element !== '' && isset($elementMap[$element]) ? max(1, count($elementMap[$element])) : 1;
            $oddEvenBase = $oddEven === '双' ? 24 : 25;

            $score = (float) ($normalFrequency[$number] ?? 0.0) * 11.0
                + (float) ($specialFrequency[$number] ?? 0.0) * 9.0
                + (float) ($frequency[$number] ?? 0.0) * 3.0
                + ((float) ($zodiacFrequency[$zodiac] ?? 0.0) / $zodiacBase) * 3.0
                + ((float) ($waveFrequency[$wave] ?? 0.0) / $waveBase) * 1.8
                + ((float) ($elementFrequency[$element] ?? 0.0) / $elementBase) * 1.6
                + ((float) ($oddEvenFrequency[$oddEven] ?? 0.0) / $oddEvenBase);
            if (isset($coreLookup[$number])) {
                $score += 32.0;
            }
            if (isset($warmLookup[$number])) {
                $score += 16.0;
            }
            if (isset($hotSpecialLookup[$number])) {
                $score += 18.0;
            }
            if (isset($warmSpecialLookup[$number])) {
                $score += 8.0;
            }
            if ($zodiac !== '' && isset($hotZodiacLookup[$zodiac])) {
                $score += 7.0;
            }
            if (isset($hotHeadLookup[$headLabel])) {
                $score += 6.0;
            }
            if (isset($hotTailLookup[$tailLabel])) {
                $score += 5.0;
            }
            if ($wave === '红波' || $wave === '蓝波') {
                $score += 2.0;
            }

            $scores[$number] = max(0.0, $score);
        }

        return $scores;
    }

    protected function resolveForecastGroupLabel(array $groupMap, $number)
    {
        $number = (int) $number;
        foreach ($groupMap as $label => $numbers) {
            if (in_array($number, (array) $numbers, true)) {
                return (string) $label;
            }
        }

        return '';
    }

    protected function buildForecastFilterContext(array $frequency, array $filters, array $heatStats = array())
    {
        $blockedNumbers = array();
        $blockedZodiacs = array();
        $downgradedNumbers = array_values(array_map('intval', (array) ($heatStats['downgraded_numbers'] ?? array())));
        $downgradedZodiacs = (array) ($heatStats['downgraded_zodiacs'] ?? array());
        if (!empty($downgradedZodiacs)) {
            $zodiacMap = $this->forecastZodiacNumberMap();
            foreach (array_keys($downgradedZodiacs) as $zodiac) {
                if (isset($zodiacMap[$zodiac])) {
                    $downgradedNumbers = array_merge($downgradedNumbers, $zodiacMap[$zodiac]);
                }
            }
        }
        $downgradedNumbers = array_values(array_unique(array_filter(array_map('intval', $downgradedNumbers), function ($number) {
            return $number >= 1 && $number <= 49;
        })));

        $context = array(
            'labels' => array(),
            'details' => array(),
            'preferred_numbers' => array(),
            'downgraded_numbers' => $downgradedNumbers,
            'excluded_numbers' => $blockedNumbers,
            'excluded_zodiacs' => $blockedZodiacs,
            'target_count' => 7,
            'summary_lines' => array(
                'heat' => $this->buildForecastHeatSummaryLine($heatStats),
                'zodiac' => '生肖类型：未选择',
                'number' => '号码类型：未选择',
                'pingte' => '平码类型：未选择',
                'other' => '其他类型：未选择',
            ),
        );
        $preferredSets = array();

        $zodiacCount = $this->forecastZodiacSelectionCount($filters['zodiac_type']);
        if ($zodiacCount > 0) {
            $zodiacMap = $this->forecastZodiacNumberMap();
            $selectedZodiacs = $this->resolveForecastZodiacLabels($frequency, $zodiacCount, $heatStats, 'special');
            if (!empty($selectedZodiacs)) {
                $selectedZodiacNumbers = array();
                foreach ($selectedZodiacs as $zodiac) {
                    $selectedZodiacNumbers = array_merge($selectedZodiacNumbers, $zodiacMap[$zodiac]);
                }
                $preferredSets[] = $selectedZodiacNumbers;
                $context['labels'][] = $this->forecastZodiacTypeLabel($zodiacCount);
                $context['details'][] = '生肖优先参考 ' . implode('、', $selectedZodiacs);
                $context['summary_lines']['zodiac'] = $this->forecastSummaryLine(
                    '生肖类型',
                    $this->forecastZodiacTypeLabel($zodiacCount),
                    $zodiacCount,
                    '个生肖'
                );
            }
        }

        $numberCount = $this->forecastNumberSelectionCount($filters['number_type']);
        if ($numberCount > 0) {
            $numberTypeZodiacs = $this->resolveForecastNumberTypeZodiacLabels($numberCount, $heatStats);
            $preferredNumbers = $this->resolveForecastSpecialNumbersInZodiacRange(
                $numberCount,
                $heatStats,
                $numberTypeZodiacs
            );
            if (!empty($preferredNumbers)) {
                $preferredSets[] = $preferredNumbers;
            }
            $context['labels'][] = $this->forecastNumberTypeLabel($numberCount);
            $context['details'][] = '号码类型按特肖热度范围 '
                . (!empty($numberTypeZodiacs) ? implode('、', $numberTypeZodiacs) : '全部生肖')
                . ' 输出 ' . $this->forecastNumberTypeLabel($numberCount);
            $context['target_count'] = max(1, min(49, $numberCount));
            $context['summary_lines']['number'] = $this->forecastSummaryLine(
                '号码类型',
                $this->forecastNumberTypeLabel($numberCount),
                $numberCount,
                '个号码'
            );
        }

        $pingteInfo = $this->forecastPingteTypeInfo($filters['pingte_type']);
        if ($pingteInfo !== null) {
            $preferredPingteNumbers = $this->resolveForecastPingteNumbersByHeatLevels(
                (int) $pingteInfo['number_count'],
                $heatStats,
                array(),
                'normal'
            );
            if (!empty($preferredPingteNumbers)) {
                $preferredSets[] = $preferredPingteNumbers;
            }
            $context['labels'][] = $pingteInfo['label'];
            $context['details'][] = '平码结构为 ' . $pingteInfo['label'] . '，基础展开 ' . $pingteInfo['number_count'] . ' 码';
            $context['target_count'] = max($context['target_count'], $pingteInfo['number_count']);
            $context['summary_lines']['pingte'] = $this->forecastSummaryLine(
                '平码类型',
                $pingteInfo['label'],
                (int) $pingteInfo['summary_count'],
                $pingteInfo['summary_unit']
            );
        }

        $otherInfo = $this->forecastOtherTypeInfo($frequency, $filters['other_type'], $heatStats);
        if ($otherInfo !== null) {
            $context['labels'][] = $otherInfo['label'];
            $context['details'][] = $otherInfo['detail'];
            if (!empty($otherInfo['preferred_numbers'])) {
                $preferredSets[] = $otherInfo['preferred_numbers'];
            }
            $context['summary_lines']['other'] = $this->forecastSummaryLine(
                '其他类型',
                $otherInfo['label'],
                (int) ($otherInfo['summary_count'] ?? 0),
                (string) ($otherInfo['summary_unit'] ?? '')
            );
        }

        if (!empty($preferredSets)) {
            $preferredNumbers = $this->intersectForecastNumberSets($preferredSets);
            if (empty($preferredNumbers)) {
                $preferredNumbers = $this->mergeForecastNumberSets($preferredSets);
            }
            $context['preferred_numbers'] = $preferredNumbers;
        }

        return $context;
    }

    protected function resolveForecastNumberTypeZodiacLabels($numberCount, array $heatStats)
    {
        $numberCount = max(0, min(49, (int) $numberCount));
        if ($numberCount <= 0) {
            return array();
        }

        $maxLevel = $numberCount <= 10 ? 1 : ($numberCount <= 20 ? 2 : 3);
        $specialZodiacs = (array) ($heatStats['special_zodiacs'] ?? array());
        $specialLevels = (array) ($heatStats['special_zodiac_levels'] ?? array());
        $blockedZodiacs = (array) ($heatStats['blocked_zodiacs'] ?? array());
        $candidates = array();

        foreach ($this->forecastZodiacNumberMap() as $zodiac => $numbers) {
            if (isset($blockedZodiacs[$zodiac])) {
                continue;
            }
            $level = (int) ($specialLevels[$zodiac] ?? 4);
            if (!empty($specialLevels) && $level > $maxLevel) {
                continue;
            }
            $candidates[$zodiac] = array(
                'level' => $level,
                'score' => (float) ($specialZodiacs[$zodiac] ?? 0.0),
            );
        }

        if (empty($candidates)) {
            foreach ($this->rankForecastStatLabels($specialZodiacs, 12) as $zodiac) {
                if (!isset($blockedZodiacs[$zodiac])) {
                    $candidates[$zodiac] = array(
                        'level' => (int) ($specialLevels[$zodiac] ?? 4),
                        'score' => (float) ($specialZodiacs[$zodiac] ?? 0.0),
                    );
                }
            }
        }

        uasort($candidates, function ($left, $right) {
            $leftLevel = (int) ($left['level'] ?? 4);
            $rightLevel = (int) ($right['level'] ?? 4);
            if ($leftLevel !== $rightLevel) {
                return $leftLevel < $rightLevel ? -1 : 1;
            }

            $leftScore = (float) ($left['score'] ?? 0.0);
            $rightScore = (float) ($right['score'] ?? 0.0);
            if ($leftScore === $rightScore) {
                return 0;
            }

            return $leftScore < $rightScore ? 1 : -1;
        });

        return array_values(array_keys($candidates));
    }

    protected function forecastNumbersForZodiacLabels(array $zodiacs)
    {
        $zodiacMap = $this->forecastZodiacNumberMap();
        $numbers = array();

        foreach ($zodiacs as $zodiac) {
            $zodiac = trim((string) $zodiac);
            if ($zodiac === '' || !isset($zodiacMap[$zodiac])) {
                continue;
            }
            foreach ((array) $zodiacMap[$zodiac] as $number) {
                $number = (int) $number;
                if ($number >= 1 && $number <= 49 && !in_array($number, $numbers, true)) {
                    $numbers[] = $number;
                }
            }
        }

        return $numbers;
    }

    protected function resolveForecastSpecialNumbersInZodiacRange($count, array $heatStats, array $zodiacs, array $fallbackNumbers = array())
    {
        $count = max(0, min(49, (int) $count));
        if ($count <= 0) {
            return array();
        }

        $allowedNumbers = $this->forecastNumbersForZodiacLabels($zodiacs);
        $allowedLookup = !empty($allowedNumbers) ? array_fill_keys($allowedNumbers, true) : array();
        $levels = (array) ($heatStats['special_number_levels'] ?? array());
        $blockedLookup = array_fill_keys(array_values(array_map('intval', (array) ($heatStats['blocked_numbers'] ?? array()))), true);
        $maxLevel = $count <= 10 ? 1 : ($count <= 20 ? 2 : 3);
        $pool = array_merge($this->rankForecastNumbersByHeat($heatStats, 'special'), $fallbackNumbers, $allowedNumbers, range(1, 49));
        $numbers = array();

        foreach (array(
            array('allowed' => true, 'level' => true),
            array('allowed' => true, 'level' => false),
            array('allowed' => false, 'level' => true),
            array('allowed' => false, 'level' => false),
        ) as $passRule) {
            foreach ($pool as $number) {
                $number = (int) $number;
                if ($number < 1 || $number > 49 || isset($blockedLookup[$number]) || in_array($number, $numbers, true)) {
                    continue;
                }
                if (!empty($passRule['allowed']) && !empty($allowedLookup) && !isset($allowedLookup[$number])) {
                    continue;
                }
                if (!empty($passRule['level']) && !empty($levels) && (int) ($levels[$number] ?? 4) > $maxLevel) {
                    continue;
                }
                $numbers[] = $number;
                if (count($numbers) >= $count) {
                    return $numbers;
                }
            }
        }

        return $numbers;
    }

    protected function resolveForecastNumbersByHeatLevels($count, array $heatStats, array $fallbackNumbers = array(), $scope = 'special')
    {
        $count = max(0, min(49, (int) $count));
        if ($count <= 0) {
            return array();
        }

        $maxLevel = $count <= 10 ? 1 : ($count <= 20 ? 2 : 3);
        $scope = (string) $scope;
        $levelKey = $scope === 'normal' ? 'normal_number_levels' : ($scope === 'special' ? 'special_number_levels' : 'number_levels');
        $levels = (array) ($heatStats[$levelKey] ?? array());
        $blockedLookup = array_fill_keys(array_values(array_map('intval', (array) ($heatStats['blocked_numbers'] ?? array()))), true);
        $pool = array_merge($this->rankForecastNumbersByHeat($heatStats, $scope), $fallbackNumbers, range(1, 49));
        $numbers = array();

        foreach ($pool as $number) {
            $number = (int) $number;
            if ($number < 1 || $number > 49 || isset($blockedLookup[$number]) || in_array($number, $numbers, true)) {
                continue;
            }
            if (!empty($levels) && (int) ($levels[$number] ?? 4) > $maxLevel) {
                continue;
            }
            $numbers[] = $number;
            if (count($numbers) >= $count) {
                return $numbers;
            }
        }

        foreach ($pool as $number) {
            $number = (int) $number;
            if ($number < 1 || $number > 49 || isset($blockedLookup[$number]) || in_array($number, $numbers, true)) {
                continue;
            }
            $numbers[] = $number;
            if (count($numbers) >= $count) {
                break;
            }
        }

        return $numbers;
    }

    protected function resolveForecastPingteNumbersByHeatLevels($count, array $heatStats, array $fallbackNumbers = array(), $scope = 'normal')
    {
        $count = max(0, min(49, (int) $count));
        if ($count <= 0) {
            return array();
        }

        $maxLevel = $count <= 3 ? 1 : ($count <= 6 ? 2 : 3);
        $scope = (string) $scope;
        $levelKey = $scope === 'special' ? 'special_number_levels' : ($scope === 'normal' ? 'normal_number_levels' : 'number_levels');
        $levels = (array) ($heatStats[$levelKey] ?? array());
        $blockedLookup = array_fill_keys(array_values(array_map('intval', (array) ($heatStats['blocked_numbers'] ?? array()))), true);
        $pool = array_merge($this->rankForecastNumbersByHeat($heatStats, $scope), $fallbackNumbers, range(1, 49));
        $numbers = array();

        foreach ($pool as $number) {
            $number = (int) $number;
            if ($number < 1 || $number > 49 || isset($blockedLookup[$number]) || in_array($number, $numbers, true)) {
                continue;
            }
            if (!empty($levels) && (int) ($levels[$number] ?? 4) > $maxLevel) {
                continue;
            }
            $numbers[] = $number;
            if (count($numbers) >= $count) {
                return $numbers;
            }
        }

        foreach ($pool as $number) {
            $number = (int) $number;
            if ($number < 1 || $number > 49 || isset($blockedLookup[$number]) || in_array($number, $numbers, true)) {
                continue;
            }
            $numbers[] = $number;
            if (count($numbers) >= $count) {
                break;
            }
        }

        return $numbers;
    }

    protected function buildForecastDisplayPayloads($region, $nextIssue, array $filters, array $recommended, array $frequency, array $rankedHotNumbers, array $heatStats)
    {
        $filters = $this->normalizeForecastFilters($filters);
        $numberDisplayCount = $this->forecastNumberResultDisplayCount($filters['number_type']);
        $pingteInfo = $this->forecastPingteTypeInfo($filters['pingte_type']);
        $pingteDisplayCount = is_array($pingteInfo) ? (int) ($pingteInfo['number_count'] ?? 0) : 0;
        $displayTargetCount = max(count($recommended), min(49, $numberDisplayCount + $pingteDisplayCount), $numberDisplayCount, $pingteDisplayCount);
        $displayNumbers = $this->normalizeForecastDisplayNumbers($this->buildForecastExpandedDisplayNumbers($recommended, $rankedHotNumbers, $heatStats, $displayTargetCount));
        $numberDisplayNumbers = array();
        $pingteDisplayNumbers = array();
        $displayOffset = 0;

        if ($numberDisplayCount > 0) {
            $numberDisplayNumbers = array_slice($displayNumbers, 0, $numberDisplayCount);
            $displayOffset = count($numberDisplayNumbers);
        }

        if ($pingteDisplayCount > 0) {
            $pingteDisplayNumbers = $this->normalizeForecastDisplayNumbers(array_slice($displayNumbers, $displayOffset, $pingteDisplayCount), $numberDisplayNumbers);
            if (count($pingteDisplayNumbers) < $pingteDisplayCount) {
                foreach ($displayNumbers as $displayNumber) {
                    $displayNumber = (int) $displayNumber;
                    if ($displayNumber >= 1
                        && $displayNumber <= 49
                        && !in_array($displayNumber, $numberDisplayNumbers, true)
                        && !in_array($displayNumber, $pingteDisplayNumbers, true)
                    ) {
                        $pingteDisplayNumbers[] = $displayNumber;
                    }
                    if (count($pingteDisplayNumbers) >= $pingteDisplayCount) {
                        break;
                    }
                }
            }
            if (count($numberDisplayNumbers) + $pingteDisplayCount > 49 && count($pingteDisplayNumbers) < $pingteDisplayCount) {
                foreach ($displayNumbers as $displayNumber) {
                    $displayNumber = (int) $displayNumber;
                    if ($displayNumber >= 1 && $displayNumber <= 49 && !in_array($displayNumber, $pingteDisplayNumbers, true)) {
                        $pingteDisplayNumbers[] = $displayNumber;
                    }
                    if (count($pingteDisplayNumbers) >= $pingteDisplayCount) {
                        break;
                    }
                }
            }
        }

        $zodiacPayload = $this->buildForecastZodiacDisplayPayload($filters['zodiac_type'], $recommended, $frequency, $heatStats);
        $numberAvoidNumbers = array();
        if ($numberDisplayCount > 0) {
            $previousNumbers = $this->latestForecastPredictionNumbers($region, $nextIssue);
            if ($previousNumbers !== array()) {
                $previousNumberPayload = $this->buildForecastNumberDisplayPayload(
                    $filters['number_type'],
                    $previousNumbers,
                    $heatStats,
                    $zodiacPayload
                );
                $numberAvoidNumbers = array_values(array_unique(array_filter(array_map(
                    'intval',
                    $this->flattenForecastDisplayPayload($previousNumberPayload)
                ), function ($number) {
                    return $number >= 1 && $number <= 49;
                })));
            }
        }

        return array(
            'zodiac' => $zodiacPayload,
            'number' => $this->buildForecastNumberDisplayPayload($filters['number_type'], $numberDisplayCount > 0 ? $numberDisplayNumbers : $displayNumbers, $heatStats, $zodiacPayload, $numberAvoidNumbers),
            'pingte' => $this->buildForecastPingteDisplayPayload($filters['pingte_type'], $pingteDisplayCount > 0 ? $pingteDisplayNumbers : $displayNumbers, $heatStats),
            'other' => $this->buildForecastOtherDisplayPayload($filters['other_type'], $recommended, $frequency, $heatStats),
        );
    }

    protected function buildForecastExpandedDisplayNumbers(array $recommended, array $rankedHotNumbers, array $heatStats, $targetCount)
    {
        $targetCount = max(0, min(49, (int) $targetCount));
        $pool = array_merge($recommended, $rankedHotNumbers, (array) ($heatStats['active_numbers'] ?? array()), range(1, 49));
        $numbers = array();

        foreach ($pool as $number) {
            $number = (int) $number;
            if ($number < 1 || $number > 49) {
                continue;
            }
            if (!in_array($number, $numbers, true)) {
                $numbers[] = $number;
            }
            if ($targetCount > 0 && count($numbers) >= $targetCount) {
                break;
            }
        }

        return array_values($this->shuffleForecastDisplayItems($numbers));
    }

    protected function normalizeForecastDisplayNumbers(array $numbers, array $excludedNumbers = array())
    {
        $excludedLookup = array();
        foreach ($excludedNumbers as $excludedNumber) {
            $excludedNumber = (int) $excludedNumber;
            if ($excludedNumber >= 1 && $excludedNumber <= 49) {
                $excludedLookup[$excludedNumber] = true;
            }
        }

        $normalized = array();
        foreach ($numbers as $number) {
            $number = (int) $number;
            if ($number < 1 || $number > 49 || isset($excludedLookup[$number]) || in_array($number, $normalized, true)) {
                continue;
            }
            $normalized[] = $number;
        }

        return $normalized;
    }

    protected function shuffleForecastDisplayItems(array $items)
    {
        $items = array_values($items);
        if (count($items) > 1) {
            shuffle($items);
        }

        return $items;
    }

    protected function buildForecastZodiacDisplayPayload($value, array $recommended, array $frequency, array $heatStats = array())
    {
        $selectedCount = $this->forecastZodiacSelectionCount($value);
        $values = array();
        if ($selectedCount > 0) {
            $values = $this->resolveForecastZodiacLabels($frequency, $selectedCount, $heatStats, 'special');
            $blockedZodiacs = array_fill_keys(array_keys((array) ($heatStats['blocked_zodiacs'] ?? array())), true);
            if (!empty($blockedZodiacs)) {
                $values = array_values(array_filter($values, function ($zodiac) use ($blockedZodiacs) {
                    return !isset($blockedZodiacs[(string) $zodiac]);
                }));
            }
            if (count($values) < $selectedCount) {
                foreach ($this->resolveForecastZodiacLabelsFromNumbers($recommended, $selectedCount) as $zodiac) {
                    if (!in_array($zodiac, $values, true)) {
                        $values[] = $zodiac;
                    }
                    if (count($values) >= $selectedCount) {
                        break;
                    }
                }
            }
            if (count($values) < $selectedCount) {
                foreach ($this->resolveForecastZodiacLabels($frequency, 12, $heatStats, 'special') as $zodiac) {
                    if (!in_array($zodiac, $values, true)) {
                        $values[] = $zodiac;
                    }
                    if (count($values) >= $selectedCount) {
                        break;
                    }
                }
            }
        }
        $values = $this->shuffleForecastDisplayItems($values);

        return array(
            'kind' => 'zodiac',
            'values' => $values,
            'groups' => array(),
        );
    }

    protected function buildForecastNumberDisplayPayload($value, array $recommended, array $heatStats = array(), array $zodiacPayload = array(), array $avoidNumbers = array())
    {
        $selectedCount = $this->forecastNumberResultDisplayCount($value);
        $excludedLookup = array_fill_keys(array_values(array_map('intval', (array) ($heatStats['blocked_numbers'] ?? array()))), true);
        if ($selectedCount <= 0) {
            return array(
                'kind' => 'number',
                'values' => array(),
                'groups' => array(),
            );
        }

        $avoidLookup = array_fill_keys(array_values(array_map('intval', $avoidNumbers)), true);
        $allowedLookup = array();
        $zodiacValues = array_values(array_filter(array_map('strval', (array) ($zodiacPayload['values'] ?? array())), function ($zodiac) {
            return trim((string) $zodiac) !== '';
        }));
        if (empty($zodiacValues)) {
            $zodiacValues = $this->resolveForecastNumberTypeZodiacLabels($selectedCount, $heatStats);
        }
        if ($selectedCount > 0 && !empty($zodiacValues)) {
            $zodiacMap = $this->forecastZodiacNumberMap();
            foreach ($zodiacValues as $zodiac) {
                if (!isset($zodiacMap[$zodiac])) {
                    continue;
                }
                foreach ((array) $zodiacMap[$zodiac] as $number) {
                    $number = (int) $number;
                    if ($number >= 1 && $number <= 49 && !isset($excludedLookup[$number])) {
                        $allowedLookup[$number] = true;
                    }
                }
            }
        }
        $recommendedPool = $recommended;
        if (!empty($allowedLookup)) {
            $recommendedPool = array_values(array_filter($recommended, function ($number) use ($allowedLookup) {
                $number = (int) $number;

                return isset($allowedLookup[$number]);
            }));
        }
        $values = array();
        if (!empty($allowedLookup)) {
            $levels = (array) ($heatStats['special_number_levels'] ?? array());
            $maxLevel = $selectedCount <= 10 ? 1 : ($selectedCount <= 20 ? 2 : 3);
            $pool = array_merge($recommendedPool, $this->rankForecastNumbersByHeat($heatStats, 'special'), array_keys($allowedLookup));
            foreach (array(
                array('avoid' => true, 'level' => true, 'allowed' => true),
                array('avoid' => true, 'level' => false, 'allowed' => true),
                array('avoid' => false, 'level' => true, 'allowed' => true),
                array('avoid' => false, 'level' => false, 'allowed' => true),
                array('avoid' => true, 'level' => true, 'allowed' => false),
                array('avoid' => false, 'level' => true, 'allowed' => false),
                array('avoid' => false, 'level' => false, 'allowed' => false),
            ) as $passRule) {
                foreach ($pool as $number) {
                    $number = (int) $number;
                    if ($number < 1 || $number > 49 || isset($excludedLookup[$number]) || in_array($number, $values, true)) {
                        continue;
                    }
                    if (!empty($passRule['allowed']) && !isset($allowedLookup[$number])) {
                        continue;
                    }
                    if (!empty($passRule['avoid']) && isset($avoidLookup[$number])) {
                        continue;
                    }
                    if (!empty($passRule['level']) && !empty($levels) && (int) ($levels[$number] ?? 4) > $maxLevel) {
                        continue;
                    }
                    $values[] = $number;
                    if (count($values) >= $selectedCount) {
                        break 2;
                    }
                }
            }
        } else {
            $pool = array_merge($recommendedPool, $this->rankForecastNumbersByHeat($heatStats, 'special'), range(1, 49));
            foreach (array(true, false) as $avoidPrevious) {
                foreach ($pool as $number) {
                    $number = (int) $number;
                    if ($number < 1 || $number > 49 || isset($excludedLookup[$number]) || in_array($number, $values, true)) {
                        continue;
                    }
                    if ($avoidPrevious && isset($avoidLookup[$number])) {
                        continue;
                    }
                    $values[] = $number;
                    if (count($values) >= $selectedCount) {
                        break 2;
                    }
                }
            }
        }
        if ($selectedCount > 0 && count($values) < $selectedCount) {
            $fallbackPool = !empty($allowedLookup)
                ? array_merge(array_keys($allowedLookup), $this->rankForecastNumbersByHeat($heatStats, 'special'), range(1, 49))
                : range(1, 49);
            foreach ($fallbackPool as $number) {
                $number = (int) $number;
                if (isset($excludedLookup[$number]) || in_array($number, $values, true)) {
                    continue;
                }
                $values[] = $number;
                if (count($values) >= $selectedCount) {
                    break;
                }
            }
        }
        $values = $this->shuffleForecastDisplayItems($values);

        return array(
            'kind' => 'number',
            'values' => $values,
            'groups' => array(),
        );
    }

    protected function filterForecastSpecialRepeatDisplayPayloads(array $displayPayloads, array $heatStats, array $rankedHotNumbers = array(), array $filters = array())
    {
        $blockedZodiacs = array_fill_keys(array_keys((array) ($heatStats['blocked_zodiacs'] ?? array())), true);
        $blockedNumbers = array_fill_keys(array_values(array_map('intval', (array) ($heatStats['blocked_numbers'] ?? array()))), true);
        if (empty($blockedZodiacs) && empty($blockedNumbers)) {
            return $displayPayloads;
        }

        if (isset($displayPayloads['zodiac']) && is_array($displayPayloads['zodiac'])) {
            $values = array_values(array_filter(array_map('strval', (array) ($displayPayloads['zodiac']['values'] ?? array())), function ($zodiac) use ($blockedZodiacs) {
                return trim($zodiac) !== '' && !isset($blockedZodiacs[$zodiac]);
            }));
            $targetCount = $this->forecastZodiacSelectionCount($filters['zodiac_type'] ?? '');
            if ($targetCount <= 0) {
                $targetCount = max(count((array) ($displayPayloads['zodiac']['values'] ?? array())), count($values));
            }
            if ($targetCount > count($values)) {
                foreach ($this->resolveForecastZodiacLabels((array) ($heatStats['numbers'] ?? array()), 12, $heatStats, 'special') as $zodiac) {
                    if (!isset($blockedZodiacs[$zodiac]) && !in_array($zodiac, $values, true)) {
                        $values[] = $zodiac;
                    }
                    if (count($values) >= $targetCount) {
                        break;
                    }
                }
            }
            $displayPayloads['zodiac']['values'] = array_slice(array_values($this->shuffleForecastDisplayItems($values)), 0, $targetCount);
        }

        if (isset($displayPayloads['number']) && is_array($displayPayloads['number'])) {
            $allowedNumberLookup = array();
            $zodiacValues = array_values(array_filter(array_map('strval', (array) ($displayPayloads['zodiac']['values'] ?? array())), function ($zodiac) {
                return trim((string) $zodiac) !== '';
            }));
            if (!empty($zodiacValues)) {
                $zodiacMap = $this->forecastZodiacNumberMap();
                foreach ($zodiacValues as $zodiac) {
                    if (!isset($zodiacMap[$zodiac])) {
                        continue;
                    }
                    foreach ((array) $zodiacMap[$zodiac] as $number) {
                        $number = (int) $number;
                        if ($number >= 1 && $number <= 49 && !isset($blockedNumbers[$number])) {
                            $allowedNumberLookup[$number] = true;
                        }
                    }
                }
            }
            $values = array_values(array_filter(array_map('intval', (array) ($displayPayloads['number']['values'] ?? array())), function ($number) use ($blockedNumbers, $allowedNumberLookup) {
                if ($number < 1 || $number > 49 || isset($blockedNumbers[$number])) {
                    return false;
                }

                return empty($allowedNumberLookup) || isset($allowedNumberLookup[$number]);
            }));
            $targetCount = $this->forecastNumberResultDisplayCount($filters['number_type'] ?? '');
            if ($targetCount <= 0) {
                $targetCount = max(count((array) ($displayPayloads['number']['values'] ?? array())), count($values));
            }
            $pool = !empty($allowedNumberLookup)
                ? array_merge(array_keys($allowedNumberLookup), $rankedHotNumbers, (array) ($heatStats['active_numbers'] ?? array()))
                : array_merge($rankedHotNumbers, (array) ($heatStats['active_numbers'] ?? array()), range(1, 49));
            foreach ($pool as $number) {
                $number = (int) $number;
                if ($number < 1
                    || $number > 49
                    || isset($blockedNumbers[$number])
                    || (!empty($allowedNumberLookup) && !isset($allowedNumberLookup[$number]))
                    || in_array($number, $values, true)
                ) {
                    continue;
                }
                $values[] = $number;
                if (count($values) >= $targetCount) {
                    break;
                }
            }
            $displayPayloads['number']['values'] = array_slice(array_values($this->shuffleForecastDisplayItems($values)), 0, $targetCount);
        }

        return $displayPayloads;
    }

    protected function buildForecastPingteDisplayPayload($value, array $recommended, array $heatStats = array())
    {
        $value = (string) $value;
        $hotPool = array();
        foreach (array_merge($this->rankForecastNumbersByHeat($heatStats, 'normal'), $recommended, range(1, 49)) as $number) {
            $number = (int) $number;
            if ($number >= 1 && $number <= 49 && !in_array($number, $hotPool, true)) {
                $hotPool[] = $number;
            }
            if (count($hotPool) >= 10) {
                break;
            }
        }
        $buildHotPoolGroups = function ($groupSize, $groupCount) use ($hotPool) {
            $groupSize = max(1, min(10, (int) $groupSize));
            $groupCount = max(1, min(100, (int) $groupCount));
            $pool = array_values($hotPool);
            if (count($pool) < $groupSize) {
                return array();
            }

            $candidates = array();
            $walk = function ($start, array $group) use (&$walk, &$candidates, $pool, $groupSize) {
                if (count($group) === $groupSize) {
                    $candidates[] = array_values($group);
                    return;
                }
                for ($index = $start; $index < count($pool); $index++) {
                    $nextGroup = $group;
                    $nextGroup[] = (int) $pool[$index];
                    $walk($index + 1, $nextGroup);
                }
            };
            $walk(0, array());
            shuffle($candidates);

            $groups = array();
            $usedKeys = array();
            foreach ($candidates as $candidate) {
                $keyParts = array_values(array_map('intval', $candidate));
                sort($keyParts, SORT_NUMERIC);
                $key = implode('-', $keyParts);
                if (isset($usedKeys[$key])) {
                    continue;
                }
                $usedKeys[$key] = true;
                $groups[] = array_values($this->shuffleForecastDisplayItems($candidate));
                if (count($groups) >= $groupCount) {
                    break;
                }
            }

            return $groups;
        };

        if (preg_match('/^pt_(\d+)_(\d+)_group_(\d+)$/', $value, $matches)) {
            $groupSize = max(1, min(49, (int) $matches[2]));
            $groupCount = max(1, min(100, (int) $matches[3]));
            $groupResults = $buildHotPoolGroups($groupSize, $groupCount);
            $groupResults = $this->shuffleForecastDisplayItems($groupResults);

            return array(
                'kind' => 'pingte-groups',
                'values' => array(),
                'groups' => $groupResults,
            );
        }

        if (preg_match('/^pt_(\d+)_(\d+)_combo_(\d+)$/', $value, $matches)) {
            $comboCount = max(0, min(49, (int) $matches[3]));
            $values = array_slice(
                $this->shuffleForecastDisplayItems($hotPool),
                0,
                min($comboCount, count($hotPool))
            );

            return array(
                'kind' => 'pingte-combo',
                'values' => $values,
                'groups' => array(),
            );
        }

        return array(
            'kind' => 'pingte',
            'values' => array(),
            'groups' => array(),
        );
    }

    protected function buildForecastOtherDisplayPayload($value, array $recommended, array $frequency, array $heatStats = array())
    {
        return array(
            'kind' => 'other',
            'values' => $this->resolveForecastOtherDisplayValues($value, $recommended, $frequency, $heatStats),
            'groups' => array(),
        );
    }

    protected function buildForecastConfidence($region, $issueNo, array $heatStats, array $recommended, array $filterContext, array $filters)
    {
        $drawCount = max(3, min(50, (int) ($heatStats['draw_count'] ?? 0)));
        $sampleRatio = $drawCount / 50;
        $numberRatio = $this->forecastNumberConfidenceScore($recommended, $heatStats, 1.0);
        $zodiacRatio = $this->forecastGroupConfidenceScore((array) ($heatStats['zodiacs'] ?? array()), 1.0);
        $waveRatio = $this->forecastGroupConfidenceScore((array) ($heatStats['waves'] ?? array()), 1.0);
        $elementRatio = $this->forecastGroupConfidenceScore((array) ($heatStats['elements'] ?? array()), 1.0);
        $oddEvenRatio = $this->forecastGroupConfidenceScore((array) ($heatStats['odd_even'] ?? array()), 1.0);
        $filterRatio = min(1.0, count((array) ($filterContext['labels'] ?? array())) / 4);
        $analysisRatio = max(0.0, min(1.0, ($sampleRatio * 0.18)
            + ($numberRatio * 0.35)
            + ($zodiacRatio * 0.14)
            + ($waveRatio * 0.09)
            + ($elementRatio * 0.09)
            + ($oddEvenRatio * 0.06)
            + ($filterRatio * 0.09)));
        $jitter = $this->forecastStableConfidenceJitter(array(
            'base',
            (string) $region,
            (string) $issueNo,
            array_values(array_map('intval', $recommended)),
            $this->normalizeForecastFilters($filters),
            array_values((array) ($filterContext['labels'] ?? array())),
            (int) ($heatStats['draw_count'] ?? 0),
        ), -4, 4);
        $confidence = 89.0 + ($analysisRatio * 6.4) + $jitter;

        return round($this->clampForecastConfidence($confidence), 1);
    }

    protected function buildForecastLineConfidences($region, $issueNo, $baseConfidence, array $filters, array $displayPayloads, array $heatStats, array $recommended)
    {
        $filters = $this->normalizeForecastFilters($filters);
        $typeFilterKeys = array(
            'zodiac' => 'zodiac_type',
            'number' => 'number_type',
            'pingte' => 'pingte_type',
            'other' => 'other_type',
        );
        $typeOffsets = array(
            'zodiac' => 0.18,
            'number' => 0.12,
            'pingte' => -0.05,
            'other' => -0.12,
        );
        $lineConfidences = array();

        foreach ($typeFilterKeys as $typeKey => $filterKey) {
            if (trim((string) ($filters[$filterKey] ?? '')) === '') {
                continue;
            }

            $payload = (array) ($displayPayloads[$typeKey] ?? array());
            $payloadSize = count($this->flattenForecastDisplayPayload($payload));
            if ($payloadSize <= 0) {
                continue;
            }

            $payloadScore = min(1.0, $this->forecastPayloadConfidenceScore($typeKey, $payload, $heatStats) / 2.4);
            $complexityPenalty = min(0.8, max(0, $payloadSize - 1) * 0.06);
            $jitter = $this->forecastStableConfidenceJitter(array(
                'line',
                (string) $region,
                (string) $issueNo,
                (string) $typeKey,
                (string) ($filters[$filterKey] ?? ''),
                array_values(array_map('intval', $recommended)),
                $payload,
            ), -3, 3);
            $lineConfidence = (float) $baseConfidence
                + (float) ($typeOffsets[$typeKey] ?? 0.0)
                + ($payloadScore * 0.8)
                - $complexityPenalty
                + $jitter;
            $lineConfidence = max((float) $baseConfidence - 1.1, min((float) $baseConfidence + 1.1, $lineConfidence));
            $lineConfidences[$typeKey] = round($this->clampForecastConfidence($lineConfidence), 1);
        }

        return $lineConfidences;
    }

    protected function forecastStableConfidenceJitter(array $seedParts, $minTenths, $maxTenths)
    {
        $minTenths = (int) $minTenths;
        $maxTenths = (int) $maxTenths;
        if ($maxTenths < $minTenths) {
            $temp = $minTenths;
            $minTenths = $maxTenths;
            $maxTenths = $temp;
        }

        $seed = json_encode($seedParts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($seed === false) {
            $seed = serialize($seedParts);
        }
        $range = max(1, $maxTenths - $minTenths + 1);
        $hash = (int) sprintf('%u', crc32((string) $seed));

        return ($minTenths + ($hash % $range)) / 10;
    }

    protected function clampForecastConfidence($confidence)
    {
        return max(89.0, min(97.0, (float) $confidence));
    }

    protected function forecastNumberConfidenceScore(array $numbers, array $heatStats, $maxScore, $frequencyKey = 'numbers')
    {
        $frequency = (array) ($heatStats[(string) $frequencyKey] ?? array());
        $maxFrequency = !empty($frequency) ? max(array_map('floatval', $frequency)) : 0.0;
        if ($maxFrequency <= 0 || empty($numbers)) {
            return 0.0;
        }

        $total = 0.0;
        $count = 0;
        foreach ($numbers as $number) {
            $number = (int) $number;
            if ($number >= 1 && $number <= 49) {
                $total += (float) ($frequency[$number] ?? 0.0);
                $count++;
            }
        }
        if ($count <= 0) {
            return 0.0;
        }

        return min((float) $maxScore, (($total / $count) / $maxFrequency) * (float) $maxScore);
    }

    protected function forecastGroupConfidenceScore(array $scores, $maxScore)
    {
        $scores = array_values(array_map('floatval', $scores));
        $total = array_sum($scores);
        $count = count($scores);
        if ($total <= 0 || $count <= 0) {
            return 0.0;
        }

        $max = max($scores);
        $average = $total / $count;
        $dominance = $average > 0 ? max(0.0, ($max - $average) / $average) : 0.0;
        $share = $max / $total;

        return min((float) $maxScore, ($share * 0.55 + min(1.0, $dominance / 2.0) * 0.45) * (float) $maxScore);
    }

    protected function forecastPayloadConfidenceScore($typeKey, array $payload, array $heatStats)
    {
        $items = $this->flattenForecastDisplayPayload($payload);
        if (empty($items)) {
            return 0.0;
        }

        if ($typeKey === 'number') {
            return $this->forecastNumberConfidenceScore(array_map('intval', $items), $heatStats, 2.4, 'special_numbers');
        }

        if ($typeKey === 'pingte') {
            return $this->forecastNumberConfidenceScore(array_map('intval', $items), $heatStats, 2.4, 'normal_numbers');
        }

        $scores = array();
        foreach ($items as $item) {
            $scores[] = $this->forecastDisplayValueHeatScore($typeKey, $item, $heatStats);
        }

        $max = !empty($scores) ? max($scores) : 0.0;
        if ($max <= 0) {
            return 0.0;
        }

        return min(2.4, (array_sum($scores) / count($scores)) / $max * 2.4);
    }

    protected function forecastDisplayValueHeatScore($typeKey, $value, array $heatStats)
    {
        $value = trim((string) $value);
        if ($typeKey === 'zodiac') {
            $zodiacs = (array) ($heatStats['special_zodiacs'] ?? array());
            return isset($zodiacs[$value]) ? (float) $zodiacs[$value] : 0.0;
        }

        if ($typeKey !== 'other') {
            return 0.0;
        }

        $zodiacs = (array) ($heatStats['normal_zodiacs'] ?? array());
        if (isset($zodiacs[$value])) {
            return (float) $zodiacs[$value];
        }

        $oddEven = !empty($heatStats['special_odd_even']) ? (array) $heatStats['special_odd_even'] : (array) ($heatStats['odd_even'] ?? array());
        if (isset($oddEven[$value])) {
            return (float) $oddEven[$value];
        }

        $waves = !empty($heatStats['special_waves']) ? (array) $heatStats['special_waves'] : (array) ($heatStats['waves'] ?? array());
        if (isset($waves[$value])) {
            return (float) $waves[$value];
        }

        $frequency = (array) ($heatStats['numbers'] ?? array());
        if ($value === '小' || $value === '大') {
            $specialBigSmall = (array) ($heatStats['special_big_small'] ?? array());
            if (isset($specialBigSmall[$value])) {
                return (float) $specialBigSmall[$value];
            }
            $score = 0.0;
            for ($number = 1; $number <= 49; $number++) {
                if (($number <= 24 ? '小' : '大') === $value) {
                    $score += (float) ($frequency[$number] ?? 0.0);
                }
            }

            return $score;
        }

        if (preg_match('/^(\d)头$/u', $value, $matches)) {
            if (isset($heatStats['special_heads'][(int) $matches[1]])) {
                return (float) $heatStats['special_heads'][(int) $matches[1]];
            }
            $score = 0.0;
            for ($number = 1; $number <= 49; $number++) {
                if ((int) floor($number / 10) === (int) $matches[1]) {
                    $score += (float) ($frequency[$number] ?? 0.0);
                }
            }

            return $score;
        }

        if (preg_match('/^(\d)尾$/u', $value, $matches)) {
            if (isset($heatStats['special_tails'][(int) $matches[1]])) {
                return (float) $heatStats['special_tails'][(int) $matches[1]];
            }
            $score = 0.0;
            for ($number = 1; $number <= 49; $number++) {
                if ($number % 10 === (int) $matches[1]) {
                    $score += (float) ($frequency[$number] ?? 0.0);
                }
            }

            return $score;
        }

        return 0.0;
    }

    protected function applyForecastTypedRepeatLimits($region, $nextIssue, array $recommended, array $rankedHotNumbers, array $filterContext, array $filters, array $frequency, array $heatStats)
    {
        $recommended = array_values(array_unique(array_map('intval', $recommended)));
        if (empty($recommended)) {
            return $recommended;
        }

        $previousNumbers = $this->latestForecastPredictionNumbers($region, $nextIssue);
        if (empty($previousNumbers)) {
            return $recommended;
        }

        $typeFilterKeys = array(
            'zodiac' => 'zodiac_type',
            'number' => 'number_type',
            'pingte' => 'pingte_type',
            'other' => 'other_type',
        );
        $replacementPool = $this->buildForecastReplacementPool($rankedHotNumbers, $filterContext);

        for ($pass = 0; $pass < count($recommended); $pass++) {
            $changed = false;
            $allPassed = true;
            foreach ($typeFilterKeys as $typeKey => $filterKey) {
                $selectedValue = trim((string) ($filters[$filterKey] ?? ''));
                if ($selectedValue === '') {
                    continue;
                }

                $previousPayload = $this->buildForecastDisplayPayloadForType($typeKey, $selectedValue, $previousNumbers, $frequency, $heatStats);
                $currentPayload = $this->buildForecastDisplayPayloadForType($typeKey, $selectedValue, $recommended, $frequency, $heatStats);
                if (!$this->isForecastDisplayRepeatOverLimit($currentPayload, $previousPayload)) {
                    continue;
                }

                $allPassed = false;
                $updated = $this->replaceForecastNumbersForDisplayRepeatLimit(
                    $recommended,
                    $previousPayload,
                    $typeKey,
                    $selectedValue,
                    $frequency,
                    $heatStats,
                    $replacementPool,
                    $rankedHotNumbers,
                    $this->buildForecastDisplayCandidateValues($typeKey, $selectedValue, $rankedHotNumbers, $heatStats)
                );
                if ($updated !== $recommended) {
                    $recommended = $updated;
                    $changed = true;
                }
            }

            if ($allPassed || !$changed) {
                break;
            }
        }

        return $recommended;
    }

    protected function buildForecastDisplayPayloadForType($typeKey, $selectedValue, array $numbers, array $frequency, array $heatStats = array())
    {
        if ($typeKey === 'zodiac') {
            return $this->buildForecastZodiacDisplayPayload($selectedValue, $numbers, $frequency, $heatStats);
        }

        if ($typeKey === 'number') {
            return $this->buildForecastNumberDisplayPayload($selectedValue, $numbers, $heatStats);
        }

        if ($typeKey === 'pingte') {
            return $this->buildForecastPingteDisplayPayload($selectedValue, $numbers, $heatStats);
        }

        if ($typeKey === 'other') {
            return $this->buildForecastOtherDisplayPayload($selectedValue, $numbers, $frequency, $heatStats);
        }

        return array('kind' => (string) $typeKey, 'values' => array(), 'groups' => array());
    }

    protected function isForecastDisplayRepeatOverLimit(array $currentPayload, array $previousPayload)
    {
        $currentItems = $this->flattenForecastDisplayPayload($currentPayload);
        $previousItems = $this->flattenForecastDisplayPayload($previousPayload);
        $targetCount = count($currentItems);
        if ($targetCount <= 0 || empty($previousItems)) {
            return false;
        }

        $maxSameCount = (int) floor($targetCount * 0.98);
        if ($maxSameCount >= $targetCount) {
            $maxSameCount = max(0, $targetCount - 1);
        }

        return $this->countForecastDisplayOverlap($currentItems, $previousItems) > $maxSameCount;
    }

    protected function replaceForecastNumbersForDisplayRepeatLimit(array $recommended, array $previousPayload, $typeKey, $selectedValue, array $frequency, array $heatStats, array $replacementPool, array $rankedHotNumbers, array $displayCandidateValues)
    {
        $current = array_values(array_unique(array_map('intval', $recommended)));
        $targetCount = count($current);
        $previousItems = $this->flattenForecastDisplayPayload($previousPayload);
        if ($targetCount <= 0 || empty($previousItems)) {
            return $current;
        }

        $maxSameCount = (int) floor(max(1, count($previousItems)) * 0.98);
        if ($maxSameCount >= count($previousItems)) {
            $maxSameCount = max(0, count($previousItems) - 1);
        }

        for ($attempt = 0; $attempt < $targetCount; $attempt++) {
            $currentPayload = $this->buildForecastDisplayPayloadForType($typeKey, $selectedValue, $current, $frequency, $heatStats);
            $currentItems = $this->flattenForecastDisplayPayload($currentPayload);
            if ($this->countForecastDisplayOverlap($currentItems, $previousItems) <= $maxSameCount) {
                return $current;
            }

            $bestTrial = array();
            $bestOverlap = $this->countForecastDisplayOverlap($currentItems, $previousItems);
            for ($replaceIndex = $targetCount - 1; $replaceIndex >= 0; $replaceIndex--) {
                foreach ($replacementPool as $candidate) {
                    $candidate = (int) $candidate;
                    if ($candidate < 1 || $candidate > 49 || in_array($candidate, $current, true)) {
                        continue;
                    }

                    $trial = $current;
                    $trial[$replaceIndex] = $candidate;
                    $trial = array_values(array_unique(array_map('intval', $trial)));
                    if (count($trial) !== $targetCount) {
                        continue;
                    }

                    $trialPayload = $this->buildForecastDisplayPayloadForType($typeKey, $selectedValue, $trial, $frequency, $heatStats);
                    $trialOverlap = $this->countForecastDisplayOverlap($this->flattenForecastDisplayPayload($trialPayload), $previousItems);
                    if ($trialOverlap < $bestOverlap) {
                        $bestOverlap = $trialOverlap;
                        $bestTrial = $trial;
                        if ($bestOverlap <= $maxSameCount) {
                            return $bestTrial;
                        }
                    }
                }
            }

            if (empty($bestTrial)) {
                $targetDisplayValue = $this->resolveForecastDisplayReplacementValue($displayCandidateValues, $currentItems, $previousItems);
                if ($targetDisplayValue === null) {
                    break;
                }

                $targetedPool = $this->buildForecastNumberPoolForDisplayValue($typeKey, $selectedValue, $targetDisplayValue, $rankedHotNumbers);
                $targetedTrial = $this->replaceForecastNumberTowardDisplayValue($current, $targetedPool);
                if ($targetedTrial === $current) {
                    break;
                }

                $current = $targetedTrial;
                continue;
            }

            $current = $bestTrial;
        }

        return $current;
    }

    protected function replaceForecastNumberTowardDisplayValue(array $current, array $targetedPool)
    {
        $targetedPool = array_values(array_unique(array_filter(array_map('intval', $targetedPool), function ($number) {
            return $number >= 1 && $number <= 49;
        })));
        if (empty($targetedPool)) {
            return $current;
        }

        $targetLookup = array_fill_keys($targetedPool, true);
        for ($replaceIndex = count($current) - 1; $replaceIndex >= 0; $replaceIndex--) {
            if (isset($targetLookup[(int) $current[$replaceIndex]])) {
                continue;
            }

            foreach ($targetedPool as $candidate) {
                if (!in_array($candidate, $current, true)) {
                    $trial = $current;
                    $trial[$replaceIndex] = $candidate;
                    return array_values(array_unique(array_map('intval', $trial)));
                }
            }
        }

        return $current;
    }

    protected function buildForecastNumberPoolForDisplayValue($typeKey, $selectedValue, $displayValue, array $rankedHotNumbers)
    {
        $displayValue = trim((string) $displayValue);
        $selectedValue = trim((string) $selectedValue);
        $pool = array();

        foreach ($rankedHotNumbers as $number) {
            $number = (int) $number;
            if ($number < 1 || $number > 49) {
                continue;
            }

            if ($this->forecastNumberMatchesDisplayValue($typeKey, $selectedValue, $displayValue, $number)) {
                $pool[] = $number;
            }
        }

        return $pool;
    }

    protected function forecastNumberMatchesDisplayValue($typeKey, $selectedValue, $displayValue, $number)
    {
        $number = (int) $number;
        if ($typeKey === 'zodiac') {
            return $this->resolveForecastGroupLabel($this->forecastZodiacNumberMap(), $number) === $displayValue;
        }

        if ($typeKey !== 'other') {
            return true;
        }

        if ($selectedValue === 'odd_even') {
            return $displayValue === ($number % 2 === 0 ? '双' : '单');
        }

        if ($selectedValue === 'wave') {
            return $this->resolveForecastGroupLabel($this->forecastWaveNumberMap(), $number) === $displayValue;
        }

        if ($selectedValue === 'big_small') {
            return $displayValue === ($number <= 24 ? '小' : '大');
        }

        if ($selectedValue === 'head' && preg_match('/^(\d)头$/u', $displayValue, $matches)) {
            return (int) floor($number / 10) === (int) $matches[1];
        }

        if ($selectedValue === 'tail' && preg_match('/^(\d)尾$/u', $displayValue, $matches)) {
            return $number % 10 === (int) $matches[1];
        }

        return true;
    }

    protected function flattenForecastDisplayPayload(array $payload)
    {
        $items = array();
        foreach ((array) ($payload['values'] ?? array()) as $value) {
            $items[] = $this->normalizeForecastDisplayValue($value);
        }

        foreach ((array) ($payload['groups'] ?? array()) as $group) {
            foreach ((array) $group as $value) {
                $items[] = $this->normalizeForecastDisplayValue($value);
            }
        }

        return array_values(array_filter($items, function ($value) {
            return $value !== '';
        }));
    }

    protected function countForecastDisplayOverlap(array $currentItems, array $previousItems)
    {
        $previousLookup = array_fill_keys($previousItems, true);
        $sameCount = 0;
        foreach ($currentItems as $item) {
            if (isset($previousLookup[$item])) {
                $sameCount++;
            }
        }

        return $sameCount;
    }

    protected function resolveForecastDisplayReplacementValue(array $candidateValues, array $currentItems, array $previousItems)
    {
        foreach ($candidateValues as $candidateValue) {
            $candidateKey = $this->normalizeForecastDisplayValue($candidateValue);
            if ($candidateKey !== '' && !in_array($candidateKey, $currentItems, true) && !in_array($candidateKey, $previousItems, true)) {
                return $candidateValue;
            }
        }

        foreach ($candidateValues as $candidateValue) {
            $candidateKey = $this->normalizeForecastDisplayValue($candidateValue);
            if ($candidateKey !== '' && !in_array($candidateKey, $currentItems, true)) {
                return $candidateValue;
            }
        }

        return null;
    }

    protected function buildForecastDisplayCandidateValues($typeKey, $selectedValue, array $rankedHotNumbers, array $heatStats)
    {
        if ($typeKey === 'zodiac') {
            return $this->rankForecastStatLabels((array) ($heatStats['zodiacs'] ?? array()), 12);
        }

        if ($typeKey === 'number' || $typeKey === 'pingte') {
            return array_values(array_map('intval', $rankedHotNumbers));
        }

        if ($typeKey === 'other') {
            return $this->buildForecastOtherCandidateValues($selectedValue, $heatStats);
        }

        return array();
    }

    protected function buildForecastOtherCandidateValues($value, array $heatStats)
    {
        $value = (string) $value;
        if ($this->forecastOtherZodiacSelectionCount($value) > 0) {
            return $this->rankForecastStatLabels(!empty($heatStats['normal_zodiacs']) ? (array) $heatStats['normal_zodiacs'] : (array) ($heatStats['zodiacs'] ?? array()), 12);
        }

        if ($value === 'odd_even') {
            return $this->rankForecastStatLabels(!empty($heatStats['special_odd_even']) ? (array) $heatStats['special_odd_even'] : (array) ($heatStats['odd_even'] ?? array()), 2);
        }

        if ($value === 'wave') {
            return $this->rankForecastStatLabels(!empty($heatStats['special_waves']) ? (array) $heatStats['special_waves'] : (array) ($heatStats['waves'] ?? array()), 3);
        }

        if ($value === 'big_small') {
            $scores = !empty($heatStats['special_big_small']) && is_array($heatStats['special_big_small'])
                ? (array) $heatStats['special_big_small']
                : array('小' => 0.0, '大' => 0.0);

            return $this->rankForecastStatLabels($scores, 2);
        }

        if ($value === 'head') {
            $scores = array();
            for ($head = 0; $head <= 4; $head++) {
                $scores[$head . '头'] = (float) ($heatStats['special_heads'][$head] ?? 0.0);
            }

            return $this->rankForecastStatLabels($scores, 5);
        }

        if ($value === 'tail') {
            $scores = array();
            for ($tail = 0; $tail <= 9; $tail++) {
                $scores[$tail . '尾'] = (float) ($heatStats['special_tails'][$tail] ?? 0.0);
            }

            return $this->rankForecastStatLabels($scores, 10);
        }

        return array();
    }

    protected function resolveForecastZodiacLabelsFromNumbers(array $numbers, $count)
    {
        $zodiacMap = $this->forecastZodiacNumberMap();
        $labels = array();
        foreach ($numbers as $number) {
            $zodiac = $this->resolveForecastGroupLabel($zodiacMap, (int) $number);
            if ($zodiac !== '' && !in_array($zodiac, $labels, true)) {
                $labels[] = $zodiac;
            }
            if (count($labels) >= (int) $count) {
                break;
            }
        }

        return $labels;
    }

    protected function resolveForecastOtherDisplayValues($value, array $numbers, array $frequency, array $heatStats = array())
    {
        $numberFrequency = $this->buildForecastFrequencyFromNumbers($numbers);
        $otherInfo = $this->forecastOtherTypeInfo($numberFrequency, $value, $heatStats);
        if ($otherInfo === null || empty($otherInfo['display_values'])) {
            $otherInfo = $this->forecastOtherTypeInfo($frequency, $value, $heatStats);
        }

        $values = array();
        foreach ((array) ($otherInfo['display_values'] ?? array()) as $displayValue) {
            $displayValue = trim((string) $displayValue);
            if ($displayValue !== '' && !in_array($displayValue, $values, true)) {
                $values[] = $displayValue;
            }
        }

        $limit = $this->forecastOtherDisplayLimit($value);
        if ($limit > 0 && count($values) < $limit) {
            $fallbackInfo = $this->forecastOtherTypeInfo($frequency, $value, $heatStats);
            foreach ((array) ($fallbackInfo['display_values'] ?? array()) as $displayValue) {
                $displayValue = trim((string) $displayValue);
                if ($displayValue !== '' && !in_array($displayValue, $values, true)) {
                    $values[] = $displayValue;
                }
                if (count($values) >= $limit) {
                    break;
                }
            }
        }

        if ($limit > 0) {
            $values = array_slice($values, 0, $limit);
        }

        return $this->shuffleForecastDisplayItems($values);
    }

    protected function forecastOtherDisplayLimit($value)
    {
        $otherZodiacCount = $this->forecastOtherZodiacSelectionCount($value);
        if ($otherZodiacCount > 0) {
            return $otherZodiacCount;
        }

        switch ((string) $value) {
            case 'odd_even':
            case 'big_small':
            case 'wave':
            case 'head':
            case 'tail':
                return 1;
            default:
                return 0;
        }
    }

    protected function forecastOtherZodiacSelectionCount($value)
    {
        if (preg_match('/^pt_zodiac_(\d+)$/', (string) $value, $matches)) {
            return max(0, min(5, (int) $matches[1]));
        }

        return 0;
    }

    protected function buildForecastFrequencyFromNumbers(array $numbers)
    {
        $frequency = array();
        for ($number = 1; $number <= 49; $number++) {
            $frequency[$number] = 0.0;
        }

        foreach ($numbers as $number) {
            $number = (int) $number;
            if (isset($frequency[$number])) {
                $frequency[$number] += 1.0;
            }
        }

        return $frequency;
    }

    protected function normalizeForecastDisplayValue($value)
    {
        if (is_int($value) || is_float($value)) {
            return (string) ((int) $value);
        }

        return trim((string) $value);
    }

    protected function moveForecastDowngradedNumbersLast(array $numbers, array $downgradedLookup)
    {
        if (empty($downgradedLookup)) {
            return array_values(array_map('intval', $numbers));
        }

        $normalNumbers = array();
        $downgradedNumbers = array();
        $seen = array();
        foreach ($numbers as $number) {
            $number = (int) $number;
            if ($number < 1 || $number > 49 || isset($seen[$number])) {
                continue;
            }

            $seen[$number] = true;
            if (isset($downgradedLookup[$number])) {
                $downgradedNumbers[] = $number;
            } else {
                $normalNumbers[] = $number;
            }
        }

        return array_merge($normalNumbers, $downgradedNumbers);
    }

    protected function buildForecastRecommendedNumbers(array $rankedHotNumbers, array $rankedColdNumbers, array $filterContext)
    {
        $targetCount = max(1, min(49, (int) ($filterContext['target_count'] ?? 7)));
        $excludedLookup = array();
        foreach ((array) ($filterContext['excluded_numbers'] ?? array()) as $number) {
            $number = (int) $number;
            if ($number >= 1 && $number <= 49) {
                $excludedLookup[$number] = true;
            }
        }
        $preferredLookup = array();
        foreach ((array) ($filterContext['preferred_numbers'] ?? array()) as $number) {
            $number = (int) $number;
            if ($number >= 1 && $number <= 49 && !isset($excludedLookup[$number])) {
                $preferredLookup[$number] = true;
            }
        }

        $recommended = array();
        $candidatePools = array(
            $this->filterForecastNumberPool($rankedHotNumbers, $preferredLookup),
            $rankedHotNumbers,
            range(1, 49),
        );

        foreach ($candidatePools as $pool) {
            foreach ($pool as $number) {
                $number = (int) $number;
                if (isset($excludedLookup[$number])) {
                    continue;
                }
                if (!in_array($number, $recommended, true)) {
                    $recommended[] = $number;
                }
                if (count($recommended) >= $targetCount) {
                    break 2;
                }
            }
        }

        return $recommended;
    }

    protected function mergeForecastRecommendedNumbers(array $primaryNumbers, array $fallbackNumbers, array $rankedHotNumbers, array $filterContext)
    {
        $targetCount = max(
            1,
            min(
                49,
                max(
                    (int) ($filterContext['target_count'] ?? 7),
                    count($primaryNumbers),
                    count($fallbackNumbers)
                )
            )
        );
        $excludedLookup = array();
        foreach ((array) ($filterContext['excluded_numbers'] ?? array()) as $number) {
            $number = (int) $number;
            if ($number >= 1 && $number <= 49) {
                $excludedLookup[$number] = true;
            }
        }

        $merged = array();
        $pools = array(
            $primaryNumbers,
            $fallbackNumbers,
            $this->buildForecastReplacementPool($rankedHotNumbers, $filterContext),
            range(1, 49),
        );

        foreach ($pools as $pool) {
            foreach ((array) $pool as $number) {
                $number = (int) $number;
                if ($number < 1 || $number > 49 || isset($excludedLookup[$number]) || in_array($number, $merged, true)) {
                    continue;
                }

                $merged[] = $number;
                if (count($merged) >= $targetCount) {
                    break 2;
                }
            }
        }

        return $merged;
    }

    protected function applyForecastHeatRepeatLimit($region, $nextIssue, array $recommended, array $rankedHotNumbers, array $filterContext)
    {
        $recommended = array_values(array_unique(array_filter(array_map('intval', $recommended), function ($number) {
            return $number >= 1 && $number <= 49;
        })));
        $targetCount = count($recommended);
        if ($targetCount <= 0) {
            return $recommended;
        }

        $previousNumbers = $this->latestForecastPredictionNumbers($region, $nextIssue);
        if (empty($previousNumbers)) {
            return $recommended;
        }

        $maxSameCount = (int) floor($targetCount * 0.98);
        if ($maxSameCount >= $targetCount) {
            $maxSameCount = max(0, $targetCount - 1);
        }

        $replacementPool = array_values(array_unique(array_filter(array_map('intval', array_merge(
            $this->buildForecastReplacementPool($rankedHotNumbers, $filterContext),
            range(1, 49)
        )), function ($number) {
            return $number >= 1 && $number <= 49;
        })));

        while (count(array_intersect($recommended, $previousNumbers)) > $maxSameCount) {
            $replacement = null;
            foreach ($replacementPool as $candidate) {
                if (!in_array($candidate, $recommended, true) && !in_array($candidate, $previousNumbers, true)) {
                    $replacement = (int) $candidate;
                    break;
                }
            }
            if ($replacement === null) {
                break;
            }

            for ($index = count($recommended) - 1; $index >= 0; $index--) {
                if (in_array((int) $recommended[$index], $previousNumbers, true)) {
                    $recommended[$index] = $replacement;
                    break;
                }
            }
        }

        return array_values(array_map('intval', $recommended));
    }

    protected function buildForecastHeatCombinationPayload(array $numbers, array $heatStats)
    {
        $numbers = array_values(array_unique(array_filter(array_map('intval', $numbers), function ($number) {
            return $number >= 1 && $number <= 49;
        })));
        $scoreMap = $this->buildForecastNumberHeatScores($heatStats);
        $score = 0.0;
        foreach ($numbers as $number) {
            $score += (float) ($scoreMap[$number] ?? 0.0);
        }

        $normalNumbers = array_slice($numbers, 0, 6);
        $specialNumber = (int) ($numbers[6] ?? 0);

        return array(
            'numbers' => $numbers,
            'normal_numbers' => $normalNumbers,
            'special_number' => $specialNumber,
            'score' => round($score, 1),
            'passed' => true,
            'details' => array(
                '按近期开奖热度统计输出',
                '核心参考生肖、号码、波色、五行热度',
                '结果重复度控制不超过98%',
            ),
        );
    }

    protected function applyForecastRepeatLimit($region, $nextIssue, array $recommended, array $rankedHotNumbers, array $filterContext)
    {
        $recommended = array_values(array_unique(array_map('intval', $recommended)));
        $targetCount = count($recommended);
        if ($targetCount <= 0) {
            return $recommended;
        }

        $previousNumbers = $this->latestForecastPredictionNumbers($region, $nextIssue);
        if (empty($previousNumbers)) {
            return $recommended;
        }

        $maxSameCount = (int) floor($targetCount * 0.96);
        if ($maxSameCount >= $targetCount) {
            $maxSameCount = max(0, $targetCount - 1);
        }

        $replacementPool = $this->buildForecastReplacementPool($rankedHotNumbers, $filterContext);
        $sameCount = count(array_intersect($recommended, $previousNumbers));
        while ($sameCount > $maxSameCount) {
            $replacement = null;
            foreach ($replacementPool as $candidate) {
                $candidate = (int) $candidate;
                if (!in_array($candidate, $recommended, true) && !in_array($candidate, $previousNumbers, true)) {
                    $replacement = $candidate;
                    break;
                }
            }

            if ($replacement === null) {
                foreach ($replacementPool as $candidate) {
                    $candidate = (int) $candidate;
                    if (!in_array($candidate, $recommended, true)) {
                        $replacement = $candidate;
                        break;
                    }
                }
            }

            if ($replacement === null) {
                break;
            }

            $replaceIndex = null;
            for ($index = count($recommended) - 1; $index >= 0; $index--) {
                if (in_array((int) $recommended[$index], $previousNumbers, true)) {
                    $replaceIndex = $index;
                    break;
                }
            }

            if ($replaceIndex === null) {
                $replaceIndex = count($recommended) - 1;
            }

            $recommended[$replaceIndex] = $replacement;
            $recommended = array_values(array_unique(array_map('intval', $recommended)));
            $sameCount = count(array_intersect($recommended, $previousNumbers));
        }

        return $recommended;
    }

    protected function latestForecastPredictionNumbers($region, $nextIssue)
    {
        $cacheKey = (string) $region . ':' . (string) $nextIssue;
        if (array_key_exists($cacheKey, $this->latestForecastPredictionNumbersRuntimeCache)) {
            return $this->latestForecastPredictionNumbersRuntimeCache[$cacheKey];
        }

        if (!$this->tableExists('ai_predictions')) {
            $this->latestForecastPredictionNumbersRuntimeCache[$cacheKey] = array();
            return array();
        }

        $row = $this->db()->fetch(
            'SELECT numbers_json FROM ai_predictions WHERE region = :region AND generated_for_issue = :generated_for_issue ORDER BY created_at DESC LIMIT 1',
            array(
                'region' => (string) $region,
                'generated_for_issue' => (string) $nextIssue,
            )
        );
        if (!$row) {
            $row = $this->db()->fetch(
                'SELECT numbers_json FROM ai_predictions WHERE region = :region ORDER BY created_at DESC LIMIT 1',
                array('region' => (string) $region)
            );
        }
        if (!$row) {
            $this->latestForecastPredictionNumbersRuntimeCache[$cacheKey] = array();
            return array();
        }

        $numbers = json_decode((string) ($row['numbers_json'] ?? ''), true);
        if (!is_array($numbers)) {
            $this->latestForecastPredictionNumbersRuntimeCache[$cacheKey] = array();
            return array();
        }

        $normalizedNumbers = array_values(array_unique(array_filter(array_map('intval', $numbers), function ($number) {
            return $number >= 1 && $number <= 49;
        })));
        $this->latestForecastPredictionNumbersRuntimeCache[$cacheKey] = $normalizedNumbers;

        return $normalizedNumbers;
    }

    protected function buildForecastReplacementPool(array $rankedHotNumbers, array $filterContext)
    {
        $excludedLookup = array();
        foreach ((array) ($filterContext['excluded_numbers'] ?? array()) as $number) {
            $number = (int) $number;
            if ($number >= 1 && $number <= 49) {
                $excludedLookup[$number] = true;
            }
        }
        $preferredLookup = array();
        foreach ((array) ($filterContext['preferred_numbers'] ?? array()) as $number) {
            $number = (int) $number;
            if ($number >= 1 && $number <= 49 && !isset($excludedLookup[$number])) {
                $preferredLookup[$number] = true;
            }
        }

        $pool = empty($preferredLookup)
            ? array_values(array_map('intval', $rankedHotNumbers))
            : array_merge(
                $this->filterForecastNumberPool($rankedHotNumbers, $preferredLookup),
                array_values(array_map('intval', $rankedHotNumbers))
            );

        return array_values(array_unique(array_filter(array_map('intval', $pool), function ($number) use ($excludedLookup) {
            return $number >= 1 && $number <= 49 && !isset($excludedLookup[(int) $number]);
        })));
    }

    protected function buildForecastRecommendedCombinations(array $heatStats, array $rankedHotNumbers, array $filterContext)
    {
        $cacheKey = md5(json_encode(array(
            'heat_stats' => $heatStats,
            'ranked_hot_numbers' => $rankedHotNumbers,
        ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        if (array_key_exists($cacheKey, $this->forecastRecommendedCombinationsRuntimeCache)) {
            return $this->forecastRecommendedCombinationsRuntimeCache[$cacheKey];
        }

        $activeLookup = array_fill_keys(array_values(array_map('intval', (array) ($heatStats['active_numbers'] ?? array()))), true);
        $heatScores = $this->buildForecastNumberHeatScores($heatStats);
        $core = array_values(array_map('intval', (array) ($heatStats['core_normal_numbers'] ?? array())));
        $warm = array_values(array_map('intval', (array) ($heatStats['warm_normal_numbers'] ?? array())));
        $hotSpecial = array_values(array_map('intval', (array) ($heatStats['hot_special_numbers'] ?? array())));
        $warmSpecial = array_values(array_map('intval', (array) ($heatStats['warm_special_numbers'] ?? array())));
        $rankedHotNumbers = array_values(array_filter(array_map('intval', $rankedHotNumbers), function ($number) use ($activeLookup) {
            return isset($activeLookup[$number]);
        }));
        $mainPool = array_values(array_unique(array_filter(array_merge($core, $warm, $rankedHotNumbers), function ($number) use ($activeLookup) {
            return isset($activeLookup[(int) $number]);
        })));
        $specialPool = array_values(array_unique(array_filter(array_merge($hotSpecial, $warmSpecial, $rankedHotNumbers), function ($number) use ($activeLookup) {
            return isset($activeLookup[(int) $number]);
        })));

        usort($mainPool, function ($left, $right) use ($heatScores) {
            $leftScore = (float) ($heatScores[$left] ?? 0.0);
            $rightScore = (float) ($heatScores[$right] ?? 0.0);
            if ($leftScore === $rightScore) {
                return $left <=> $right;
            }

            return $leftScore < $rightScore ? 1 : -1;
        });
        usort($specialPool, function ($left, $right) use ($heatStats, $heatScores) {
            $specialFrequency = (array) ($heatStats['special_numbers'] ?? array());
            $leftScore = ((float) ($specialFrequency[$left] ?? 0.0) * 12.0) + (float) ($heatScores[$left] ?? 0.0);
            $rightScore = ((float) ($specialFrequency[$right] ?? 0.0) * 12.0) + (float) ($heatScores[$right] ?? 0.0);
            if ($leftScore === $rightScore) {
                return $left <=> $right;
            }

            return $leftScore < $rightScore ? 1 : -1;
        });

        $combinations = array();
        $fallbacks = array();
        $poolSize = max(1, count($mainPool));
        for ($attempt = 0; $attempt < max(80, $poolSize * 8); $attempt++) {
            $numbers = $this->buildForecastOneCombination($mainPool, $specialPool, $heatStats, $heatScores, $attempt);
            if (count($numbers) !== 7) {
                continue;
            }

            $payload = $this->buildForecastCombinationPayload($numbers, $heatStats);
            $key = implode('-', array_map('intval', $payload['numbers']));
            if (isset($fallbacks[$key])) {
                continue;
            }
            $fallbacks[$key] = $payload;

            if (!empty($payload['passed'])) {
                $combinations[$key] = $payload;
            }
            if (count($combinations) >= 3) {
                break;
            }
        }

        if (count($combinations) < 3 && !empty($fallbacks)) {
            uasort($fallbacks, function ($left, $right) {
                $leftScore = (float) ($left['score'] ?? 0.0);
                $rightScore = (float) ($right['score'] ?? 0.0);
                if ($leftScore === $rightScore) {
                    return 0;
                }

                return $leftScore < $rightScore ? 1 : -1;
            });
            foreach ($fallbacks as $key => $payload) {
                if (!isset($combinations[$key])) {
                    $combinations[$key] = $payload;
                }
                if (count($combinations) >= 3) {
                    break;
                }
            }
        }

        $combinations = array_values($combinations);
        $this->forecastRecommendedCombinationsRuntimeCache[$cacheKey] = $combinations;

        return $combinations;
    }

    protected function buildForecastOneCombination(array $mainPool, array $specialPool, array $heatStats, array $heatScores, $attempt)
    {
        if (count($mainPool) < 6 || count($specialPool) < 1) {
            return array();
        }

        $selected = array();
        for ($slot = 0; $slot < 6; $slot++) {
            $bestNumber = null;
            $bestScore = null;
            foreach ($mainPool as $index => $number) {
                $number = (int) $number;
                if (in_array($number, $selected, true)) {
                    continue;
                }

                $score = $this->forecastCandidateSlotScore($number, $selected, $heatStats, $heatScores, false);
                $score += (($attempt + 1) * (($index % 7) + 1)) % 13 / 100;
                if ($bestScore === null || $score > $bestScore) {
                    $bestNumber = $number;
                    $bestScore = $score;
                }
            }
            if ($bestNumber === null) {
                return array();
            }
            $selected[] = $bestNumber;
        }

        $bestSpecial = null;
        $bestSpecialScore = null;
        foreach ($specialPool as $index => $number) {
            $number = (int) $number;
            if (in_array($number, $selected, true)) {
                continue;
            }

            $score = $this->forecastCandidateSlotScore($number, $selected, $heatStats, $heatScores, true);
            $score += (($attempt + 3) * (($index % 11) + 1)) % 17 / 100;
            if ($bestSpecialScore === null || $score > $bestSpecialScore) {
                $bestSpecial = $number;
                $bestSpecialScore = $score;
            }
        }
        if ($bestSpecial === null) {
            return array();
        }

        $selected[] = $bestSpecial;

        return array_values(array_map('intval', $selected));
    }

    protected function forecastCandidateSlotScore($number, array $current, array $heatStats, array $heatScores, $special)
    {
        $number = (int) $number;
        $test = array_merge($current, array($number));
        $metrics = $this->forecastCombinationMetrics($test, $heatStats);
        $score = (float) ($heatScores[$number] ?? 0.0);
        $specialFrequency = (array) ($heatStats['special_numbers'] ?? array());
        if ($special) {
            $score += (float) ($specialFrequency[$number] ?? 0.0) * 14.0;
        }

        $score += min(4, (int) ($metrics['hot_head_count'] ?? 0)) * 7.0;
        $score += min(3, (int) ($metrics['hot_tail_count'] ?? 0)) * 6.0;
        $score += min((int) ($metrics['required_hot_zodiac_count'] ?? 7), (int) ($metrics['hot_zodiac_count'] ?? 0)) * 5.0;
        $score += min(4, (int) ($metrics['waves']['红波'] ?? 0)) * 2.4;
        $score += min(3, (int) ($metrics['waves']['蓝波'] ?? 0)) * 2.2;
        $score += min(2, (int) ($metrics['waves']['绿波'] ?? 0)) * 1.2;
        if ((int) ($metrics['waves']['绿波'] ?? 0) > 2) {
            $score -= 12.0;
        }

        $oddCount = (int) ($metrics['odd'] ?? 0);
        $oddTarget = $this->forecastOddTarget($heatStats);
        if ($oddCount >= (int) $oddTarget['min'] && $oddCount <= (int) $oddTarget['max']) {
            $score += 5.0;
        }

        return $score;
    }

    protected function buildForecastCombinationPayload(array $numbers, array $heatStats)
    {
        $numbers = array_values(array_unique(array_filter(array_map('intval', $numbers), function ($number) {
            return $number >= 1 && $number <= 49;
        })));
        if (count($numbers) !== 7) {
            return array();
        }

        $metrics = $this->forecastCombinationMetrics($numbers, $heatStats);
        $passed = !empty($metrics['active_pass'])
            && !empty($metrics['odd_pass'])
            && !empty($metrics['head_pass'])
            && !empty($metrics['tail_pass'])
            && !empty($metrics['zodiac_pass'])
            && !empty($metrics['wave_pass']);
        $score = $this->scoreForecastCombinationMetrics($metrics);
        $details = array(
            '热平码 ' . (int) $metrics['hot_normal_count'] . '/6',
            '特码' . (!empty($metrics['special_hot']) ? '热度命中' : '参考高频平码'),
            '单双 ' . (int) $metrics['odd'] . ':' . (int) $metrics['even'],
            '头数 ' . (int) $metrics['hot_head_count'] . '/7',
            '尾数 ' . (int) $metrics['hot_tail_count'] . '/7',
            '热生肖 ' . (int) $metrics['hot_zodiac_count'] . '/' . (int) $metrics['required_hot_zodiac_count'],
            '波色 红' . (int) $metrics['waves']['红波'] . ' 蓝' . (int) $metrics['waves']['蓝波'] . ' 绿' . (int) $metrics['waves']['绿波'],
        );

        return array(
            'numbers' => $numbers,
            'normal_numbers' => array_slice($numbers, 0, 6),
            'special_number' => $numbers[6],
            'score' => $score,
            'passed' => $passed,
            'details' => $details,
        );
    }

    protected function forecastCombinationMetrics(array $numbers, array $heatStats)
    {
        $coldLookup = array_fill_keys(array_values(array_map('intval', (array) ($heatStats['cold_numbers'] ?? array()))), true);
        $coreWarmLookup = array_fill_keys(array_values(array_map('intval', array_merge(
            (array) ($heatStats['core_normal_numbers'] ?? array()),
            (array) ($heatStats['warm_normal_numbers'] ?? array())
        ))), true);
        $specialLookup = array_fill_keys(array_values(array_map('intval', array_merge(
            (array) ($heatStats['hot_special_numbers'] ?? array()),
            (array) ($heatStats['warm_special_numbers'] ?? array())
        ))), true);
        $hotZodiacLookup = array_fill_keys(array_values(array_map('strval', (array) ($heatStats['hot_zodiacs'] ?? array()))), true);
        $hotHeadLookup = array_fill_keys(array_values(array_map('strval', (array) ($heatStats['hot_heads'] ?? array()))), true);
        $hotTailLookup = array_fill_keys(array_values(array_map('strval', (array) ($heatStats['hot_tails'] ?? array()))), true);
        $zodiacMap = $this->forecastZodiacNumberMap();
        $waveMap = $this->forecastWaveNumberMap();
        $normalNumbers = array_slice(array_values(array_map('intval', $numbers)), 0, min(6, count($numbers)));
        $specialNumber = isset($numbers[6]) ? (int) $numbers[6] : 0;
        $waves = array('红波' => 0, '蓝波' => 0, '绿波' => 0);
        $odd = 0;
        $hotNormalCount = 0;
        $hotHeadCount = 0;
        $hotTailCount = 0;
        $hotZodiacCount = 0;
        $activePass = true;

        foreach ($numbers as $number) {
            $number = (int) $number;
            if (isset($coldLookup[$number])) {
                $activePass = false;
            }
            if ($number % 2 !== 0) {
                $odd++;
            }
            $headLabel = (int) floor($number / 10) . '头';
            $tailLabel = $number % 10 . '尾';
            if (isset($hotHeadLookup[$headLabel])) {
                $hotHeadCount++;
            }
            if (isset($hotTailLookup[$tailLabel])) {
                $hotTailCount++;
            }
            $zodiac = $this->resolveForecastGroupLabel($zodiacMap, $number);
            if ($zodiac !== '' && isset($hotZodiacLookup[$zodiac])) {
                $hotZodiacCount++;
            }
            $wave = $this->resolveForecastGroupLabel($waveMap, $number);
            if ($wave !== '' && isset($waves[$wave])) {
                $waves[$wave]++;
            }
        }

        foreach ($normalNumbers as $number) {
            if (isset($coreWarmLookup[(int) $number])) {
                $hotNormalCount++;
            }
        }

        $oddTarget = $this->forecastOddTarget($heatStats);
        $requiredHotZodiacCount = min(count($numbers), 9);

        return array(
            'active_pass' => $activePass,
            'hot_normal_count' => $hotNormalCount,
            'special_hot' => isset($specialLookup[$specialNumber]) || isset($coreWarmLookup[$specialNumber]),
            'odd' => $odd,
            'even' => max(0, count($numbers) - $odd),
            'odd_pass' => $odd >= (int) $oddTarget['min'] && $odd <= (int) $oddTarget['max'],
            'hot_head_count' => $hotHeadCount,
            'head_pass' => $hotHeadCount >= 4,
            'hot_tail_count' => $hotTailCount,
            'tail_pass' => $hotTailCount >= 3,
            'hot_zodiac_count' => $hotZodiacCount,
            'required_hot_zodiac_count' => $requiredHotZodiacCount,
            'zodiac_pass' => $hotZodiacCount >= $requiredHotZodiacCount,
            'waves' => $waves,
            'wave_pass' => $waves['红波'] >= 3 && $waves['红波'] <= 4
                && $waves['蓝波'] >= 2 && $waves['蓝波'] <= 3
                && $waves['绿波'] >= 1 && $waves['绿波'] <= 2,
        );
    }

    protected function scoreForecastCombinationMetrics(array $metrics)
    {
        $score = 0.0;
        $score += !empty($metrics['active_pass']) ? 15.0 : 0.0;
        $score += min(6, (int) ($metrics['hot_normal_count'] ?? 0)) / 6 * 20.0;
        $score += !empty($metrics['special_hot']) ? 10.0 : 5.0;
        $score += !empty($metrics['odd_pass']) ? 12.0 : 4.0;
        $score += min(4, (int) ($metrics['hot_head_count'] ?? 0)) / 4 * 12.0;
        $score += min(3, (int) ($metrics['hot_tail_count'] ?? 0)) / 3 * 11.0;
        $requiredZodiac = max(1, (int) ($metrics['required_hot_zodiac_count'] ?? 7));
        $score += min($requiredZodiac, (int) ($metrics['hot_zodiac_count'] ?? 0)) / $requiredZodiac * 12.0;
        $score += !empty($metrics['wave_pass']) ? 8.0 : 3.0;

        return round(min(100.0, $score), 1);
    }

    protected function forecastOddTarget(array $heatStats)
    {
        $oddEven = (array) ($heatStats['odd_even'] ?? array());
        $odd = (float) ($oddEven['单'] ?? 0.0);
        $even = (float) ($oddEven['双'] ?? 0.0);
        $total = max(1.0, $odd + $even);
        $oddRatio = $odd / $total;

        if ($oddRatio > 0.52) {
            return array('min' => 4, 'max' => 4, 'label' => '4奇3偶');
        }
        if ($oddRatio < 0.48) {
            return array('min' => 3, 'max' => 3, 'label' => '3奇4偶');
        }

        return array('min' => 3, 'max' => 4, 'label' => '3:4 或 4:3');
    }

    protected function buildForecastStatsPayload(array $heatStats)
    {
        return array(
            'draw_count' => (int) ($heatStats['draw_count'] ?? 0),
            'normal_frequency' => $this->rankForecastNumberStats((array) ($heatStats['normal_numbers'] ?? array()), 49),
            'special_frequency' => $this->rankForecastNumberStats((array) ($heatStats['special_numbers'] ?? array()), 49),
            'zodiac_frequency' => $this->rankForecastAssocStats((array) ($heatStats['zodiacs'] ?? array()), 12),
            'normal_zodiac_frequency' => $this->rankForecastAssocStats((array) ($heatStats['normal_zodiacs'] ?? array()), 12),
            'special_zodiac_frequency' => $this->rankForecastAssocStats((array) ($heatStats['special_zodiacs'] ?? array()), 12),
            'number_levels' => (array) ($heatStats['number_levels'] ?? array()),
            'zodiac_levels' => (array) ($heatStats['zodiac_levels'] ?? array()),
            'wave_average' => (array) ($heatStats['wave_average'] ?? array()),
            'wave_trend' => (array) ($heatStats['wave_trend'] ?? array()),
            'odd_even' => (array) ($heatStats['odd_even'] ?? array()),
            'special_odd_even' => (array) ($heatStats['special_odd_even'] ?? array()),
            'special_waves' => (array) ($heatStats['special_waves'] ?? array()),
            'special_elements' => (array) ($heatStats['special_elements'] ?? array()),
            'heads' => $this->formatForecastHeadTailScores((array) ($heatStats['heads'] ?? array()), '头'),
            'tails' => $this->formatForecastHeadTailScores((array) ($heatStats['tails'] ?? array()), '尾'),
            'special_heads' => $this->formatForecastHeadTailScores((array) ($heatStats['special_heads'] ?? array()), '头'),
            'special_tails' => $this->formatForecastHeadTailScores((array) ($heatStats['special_tails'] ?? array()), '尾'),
            'cold_numbers' => array_values(array_map('intval', (array) ($heatStats['cold_numbers'] ?? array()))),
            'core_normal_numbers' => array_values(array_map('intval', (array) ($heatStats['core_normal_numbers'] ?? array()))),
            'warm_normal_numbers' => array_values(array_map('intval', (array) ($heatStats['warm_normal_numbers'] ?? array()))),
            'hot_special_numbers' => array_values(array_map('intval', (array) ($heatStats['hot_special_numbers'] ?? array()))),
            'hot_heads' => array_values(array_map('strval', (array) ($heatStats['hot_heads'] ?? array()))),
            'hot_tails' => array_values(array_map('strval', (array) ($heatStats['hot_tails'] ?? array()))),
            'hot_zodiacs' => array_values(array_map('strval', (array) ($heatStats['hot_zodiacs'] ?? array()))),
            'cold_zodiacs' => array_values(array_map('strval', (array) ($heatStats['cold_zodiacs'] ?? array()))),
            'normal_cold_zodiacs' => array_values(array_map('strval', (array) ($heatStats['normal_cold_zodiacs'] ?? array()))),
            'special_cold_zodiacs' => array_values(array_map('strval', (array) ($heatStats['special_cold_zodiacs'] ?? array()))),
            'normal_number_levels' => (array) ($heatStats['normal_number_levels'] ?? array()),
            'special_number_levels' => (array) ($heatStats['special_number_levels'] ?? array()),
            'normal_zodiac_levels' => (array) ($heatStats['normal_zodiac_levels'] ?? array()),
            'special_zodiac_levels' => (array) ($heatStats['special_zodiac_levels'] ?? array()),
        );
    }

    protected function buildForecastSummary($drawCount, array $recommended, array $hot, array $cold, array $filterContext, array $forecastCombinations = array(), array $heatStats = array())
    {
        $summaryLines = array_values(array_filter((array) ($filterContext['summary_lines'] ?? array()), function ($line) {
            return trim((string) $line) !== '';
        }));

        if (!empty($heatStats)) {
            $summaryLines[] = $this->buildForecastHeatSummaryLine($heatStats);
            $summaryLines[] = '核心号码：' . $this->formatForecastHotNumbers($recommended);
        }

        if (!empty($forecastCombinations)) {
            foreach (array_slice($forecastCombinations, 0, 3) as $index => $combination) {
                $numbers = array_values(array_map('intval', (array) ($combination['numbers'] ?? array())));
                if (!empty($numbers)) {
                    $summaryLines[] = '推荐组合' . ((int) $index + 1) . '：'
                        . $this->formatForecastHotNumbers($numbers);
                }
            }
        }

        $summaryLines = array_values(array_unique(array_filter($summaryLines, function ($line) {
            return trim((string) $line) !== '';
        })));

        return !empty($summaryLines) ? implode("\n", $summaryLines) : '当前按开奖记录热度统计生成预测结果。';

        if (!empty($heatStats)) {
            $oddTarget = $this->forecastOddTarget($heatStats);
            $summaryLines[] = '冷门已取消：' . $this->formatForecastHotNumbers((array) ($heatStats['cold_numbers'] ?? array()));
            $summaryLines[] = '下期倾向：平码取核心热号+重要温号；特码取热特码或高频平码；单双参考 ' . $oddTarget['label'] . '；头数优先 ' . implode('、', (array) ($heatStats['hot_heads'] ?? array())) . '；尾数优先 ' . implode('、', (array) ($heatStats['hot_tails'] ?? array())) . '；波色红蓝为主、绿波控制。';
        }

        if (!empty($forecastCombinations)) {
            foreach (array_slice($forecastCombinations, 0, 3) as $index => $combination) {
                $numbers = array_values(array_map('intval', (array) ($combination['numbers'] ?? array())));
                if (empty($numbers)) {
                    continue;
                }
                $summaryLines[] = '推荐组合' . ((int) $index + 1) . '：' . $this->formatForecastHotNumbers($numbers)
                    . '，符合度 ' . (float) ($combination['score'] ?? 0.0) . '分，'
                    . implode('；', (array) ($combination['details'] ?? array()));
            }
        }

        if (!empty($summaryLines)) {
            return implode("\n", $summaryLines);
        }

        return '当前没有可展示的预测结果。';
    }

    protected function forecastNumberListText(array $numbers, $limit = 10)
    {
        $numbers = array_values(array_map('intval', $numbers));
        if (count($numbers) <= (int) $limit) {
            return implode('、', $numbers);
        }

        return implode('、', array_slice($numbers, 0, (int) $limit)) . ' 等 ' . count($numbers) . ' 码';
    }

    protected function buildForecastHeatSummaryLine(array $heatStats)
    {
        if ((int) ($heatStats['total_open_count'] ?? 0) <= 0) {
            return '';
        }

        $hotSpecialZodiacs = $this->rankForecastStatLabels((array) ($heatStats['special_zodiacs'] ?? array()), 3);
        $hotNormalZodiacs = $this->rankForecastStatLabels((array) ($heatStats['normal_zodiacs'] ?? array()), 3);
        $hotNormalNumbers = $this->rankForecastStatNumbers((array) ($heatStats['normal_numbers'] ?? array()), 6);
        $hotSpecialNumbers = $this->rankForecastStatNumbers((array) ($heatStats['special_numbers'] ?? array()), 6);
        $hotOddEven = $this->rankForecastStatLabels((array) ($heatStats['special_odd_even'] ?? array()), 1);
        $hotWaves = $this->rankForecastStatLabels((array) ($heatStats['special_waves'] ?? array()), 2);
        $hotElements = $this->rankForecastStatLabels((array) ($heatStats['special_elements'] ?? array()), 2);
        $hotHeads = array_values(array_map('strval', (array) ($heatStats['hot_heads'] ?? array())));
        $hotTails = array_values(array_map('strval', (array) ($heatStats['hot_tails'] ?? array())));
        $drawCount = max(1, (int) ($heatStats['draw_count'] ?? 0));

        return '最近' . $drawCount . '期热度：特肖 '
            . implode('、', $hotSpecialZodiacs)
            . '；平肖 ' . implode('、', $hotNormalZodiacs)
            . '；正码 ' . $this->formatForecastHotNumbers($hotNormalNumbers)
            . '；特码 ' . $this->formatForecastHotNumbers($hotSpecialNumbers)
            . '；热单双 ' . implode('、', $hotOddEven)
            . '；热波色 ' . implode('、', $hotWaves)
            . '；热五行 ' . implode('、', $hotElements)
            . '；热头数 ' . implode('、', $hotHeads)
            . '；热尾数 ' . implode('、', $hotTails);
    }

    protected function formatForecastHeadTailScores(array $scores, $suffix)
    {
        $formatted = array();
        foreach ($scores as $key => $score) {
            $formatted[(int) $key . (string) $suffix] = (float) $score;
        }

        return $formatted;
    }

    protected function buildForecastWaveTrend(array $waveRows)
    {
        $trend = array('红波' => '平稳', '蓝波' => '平稳', '绿波' => '平稳');
        if (count($waveRows) < 4) {
            return $trend;
        }

        $recentRows = array_slice($waveRows, 0, max(2, (int) floor(count($waveRows) / 2)));
        $olderRows = array_slice($waveRows, count($recentRows));
        if (empty($olderRows)) {
            return $trend;
        }

        foreach ($trend as $wave => $label) {
            $recentAverage = 0.0;
            foreach ($recentRows as $row) {
                $recentAverage += (float) ($row[$wave] ?? 0.0);
            }
            $recentAverage = $recentAverage / max(1, count($recentRows));

            $olderAverage = 0.0;
            foreach ($olderRows as $row) {
                $olderAverage += (float) ($row[$wave] ?? 0.0);
            }
            $olderAverage = $olderAverage / max(1, count($olderRows));

            if ($recentAverage - $olderAverage >= 0.25) {
                $trend[$wave] = '上升';
            } elseif ($olderAverage - $recentAverage >= 0.25) {
                $trend[$wave] = '回落';
            }
        }

        return $trend;
    }

    protected function rankForecastNumberStats(array $scores, $limit)
    {
        $numbers = array_values(array_map('intval', array_keys($scores)));
        usort($numbers, function ($left, $right) use ($scores) {
            $leftScore = (float) ($scores[$left] ?? 0.0);
            $rightScore = (float) ($scores[$right] ?? 0.0);
            if ($leftScore === $rightScore) {
                return $left <=> $right;
            }

            return $leftScore < $rightScore ? 1 : -1;
        });

        $ranked = array();
        foreach (array_slice($numbers, 0, max(0, (int) $limit)) as $number) {
            $ranked[] = array(
                'number' => (int) $number,
                'count' => (float) ($scores[$number] ?? 0.0),
            );
        }

        return $ranked;
    }

    protected function rankForecastAssocStats(array $scores, $limit)
    {
        $labels = $this->rankForecastStatLabels($scores, $limit);
        $ranked = array();
        foreach ($labels as $label) {
            $ranked[] = array(
                'label' => (string) $label,
                'count' => (float) ($scores[$label] ?? 0.0),
            );
        }

        return $ranked;
    }

    protected function rankForecastStatLabels(array $scores, $limit)
    {
        $labels = array_keys($scores);
        usort($labels, function ($left, $right) use ($scores) {
            $leftScore = (float) ($scores[$left] ?? 0.0);
            $rightScore = (float) ($scores[$right] ?? 0.0);
            if ($leftScore === $rightScore) {
                return strcmp((string) $left, (string) $right);
            }

            return $leftScore < $rightScore ? 1 : -1;
        });

        return array_slice(array_values(array_map('strval', $labels)), 0, max(0, (int) $limit));
    }

    protected function rankForecastStatNumbers(array $scores, $limit)
    {
        $numbers = array_values(array_map('intval', array_keys($scores)));
        usort($numbers, function ($left, $right) use ($scores) {
            $leftScore = (float) ($scores[$left] ?? 0.0);
            $rightScore = (float) ($scores[$right] ?? 0.0);
            if ($leftScore === $rightScore) {
                return $left <=> $right;
            }

            return $leftScore < $rightScore ? 1 : -1;
        });

        return array_slice($numbers, 0, max(0, (int) $limit));
    }

    protected function formatForecastHotNumbers(array $numbers)
    {
        $formatted = array();
        foreach ($numbers as $number) {
            $formatted[] = str_pad((string) ((int) $number), 2, '0', STR_PAD_LEFT);
        }

        return implode('、', $formatted);
    }

    protected function forecastZodiacSelectionCount($value)
    {
        if (preg_match('/^zodiac_(\d+)$/', (string) $value, $matches)) {
            return max(0, min(9, (int) $matches[1]));
        }

        return 0;
    }

    protected function forecastNumberSelectionCount($value)
    {
        if (preg_match('/^number_(\d+)$/', (string) $value, $matches)) {
            return max(0, min(49, (int) $matches[1]));
        }

        return 0;
    }

    protected function forecastNumberResultDisplayCount($value)
    {
        return $this->forecastNumberSelectionCount($value);
    }

    protected function forecastZodiacTypeLabel($count)
    {
        $labels = array(
            1 => '一肖',
            2 => '二肖',
            3 => '三肖',
            4 => '四肖',
            5 => '五肖',
            6 => '六肖',
            7 => '七肖',
            8 => '八肖',
            9 => '九肖',
        );

        return $labels[(int) $count] ?? '';
    }

    protected function forecastNumberTypeLabel($count)
    {
        $labels = array(
            1 => '①码',
            2 => '②码',
            3 => '③码',
            4 => '④码',
            5 => '⑤码',
            6 => '⑥码',
            7 => '⑦码',
            8 => '⑧码',
            9 => '⑨码',
            10 => '⑩码',
        );

        if (isset($labels[(int) $count])) {
            return $labels[(int) $count];
        }

        return (int) $count . '码';
    }

    protected function forecastPingteTypeInfo($value)
    {
        $value = (string) $value;
        if (preg_match('/^pt_(\d+)_(\d+)_group_(\d+)$/', $value, $matches)) {
            $groupSize = (int) $matches[2];
            $groupCount = (int) $matches[3];

            return array(
                'label' => $groupCount . '组' . $groupSize . '中' . $groupSize,
                'number_count' => $groupSize * $groupCount,
                'summary_count' => $groupSize * $groupCount,
                'summary_unit' => '个号码',
            );
        }

        if (preg_match('/^pt_(\d+)_(\d+)_combo_(\d+)$/', $value, $matches)) {
            $numberCount = (int) $matches[3];

            return array(
                'label' => $numberCount . '码复式',
                'number_count' => $numberCount,
                'summary_count' => $numberCount,
                'summary_unit' => '个号码',
            );
        }

        return null;
    }

    protected function forecastOtherTypeInfo(array $frequency, $value, array $heatStats = array())
    {
        $value = (string) $value;
        if ($value === '') {
            return null;
        }

        $otherZodiacCount = $this->forecastOtherZodiacSelectionCount($value);
        if ($otherZodiacCount > 0) {
            $zodiacMap = $this->forecastZodiacNumberMap();
            $zodiacScores = !empty($heatStats['normal_zodiacs']) && is_array($heatStats['normal_zodiacs'])
                ? (array) $heatStats['normal_zodiacs']
                : array();
            if (empty($zodiacScores)) {
                foreach ($zodiacMap as $zodiac => $numbers) {
                    $zodiacScores[$zodiac] = 0.0;
                    foreach ($numbers as $number) {
                        $zodiacScores[$zodiac] += (float) ($frequency[$number] ?? 0);
                    }
                }
            }
            $normalZodiacLevels = (array) ($heatStats['normal_zodiac_levels'] ?? array());
            $maxLevel = $otherZodiacCount <= 3 ? 1 : 2;
            if (!empty($normalZodiacLevels)) {
                foreach ($zodiacScores as $zodiac => $score) {
                    if ((int) ($normalZodiacLevels[$zodiac] ?? 4) > $maxLevel) {
                        unset($zodiacScores[$zodiac]);
                    }
                }
            }
            arsort($zodiacScores);
            $preferredZodiacs = array_slice(array_keys($zodiacScores), 0, $otherZodiacCount);
            if (count($preferredZodiacs) < $otherZodiacCount) {
                foreach ($this->rankForecastStatLabels(!empty($heatStats['normal_zodiacs']) ? (array) $heatStats['normal_zodiacs'] : (array) ($heatStats['zodiacs'] ?? array()), 12) as $zodiac) {
                    if (!in_array($zodiac, $preferredZodiacs, true)) {
                        $preferredZodiacs[] = $zodiac;
                    }
                    if (count($preferredZodiacs) >= $otherZodiacCount) {
                        break;
                    }
                }
            }
            $preferredNumbers = array();
            foreach ($preferredZodiacs as $zodiac) {
                $preferredNumbers = array_merge($preferredNumbers, $zodiacMap[$zodiac]);
            }
            $label = '平特' . $this->forecastZodiacTypeLabel($otherZodiacCount);

            return array(
                'label' => $label,
                'detail' => $label . '优先：' . implode('、', $preferredZodiacs),
                'preferred_numbers' => $preferredNumbers,
                'display_values' => $preferredZodiacs,
                'summary_count' => $otherZodiacCount,
                'summary_unit' => '个生肖',
            );
        }

        if ($value === 'odd_even') {
            $specialOddEven = !empty($heatStats['special_odd_even']) && is_array($heatStats['special_odd_even'])
                ? (array) $heatStats['special_odd_even']
                : array('单' => 0.0, '双' => 0.0);
            $oddScore = (float) ($specialOddEven['单'] ?? 0.0);
            $evenScore = (float) ($specialOddEven['双'] ?? 0.0);
            $preferOdd = $oddScore >= $evenScore;
            $preferredNumbers = array();
            for ($number = 1; $number <= 49; $number++) {
                if (($number % 2 !== 0) === $preferOdd) {
                    $preferredNumbers[] = $number;
                }
            }

            return array(
                'label' => '单双',
                'detail' => '单双倾向：' . ($preferOdd ? '单' : '双'),
                'preferred_numbers' => $preferredNumbers,
                'display_values' => array($preferOdd ? '单' : '双'),
                'summary_count' => 1,
                'summary_unit' => '个单双',
            );
        }

        if ($value === 'wave') {
            $waveMap = $this->forecastWaveNumberMap();
            $waveScores = !empty($heatStats['special_waves']) && is_array($heatStats['special_waves'])
                ? (array) $heatStats['special_waves']
                : array();
            if (empty($waveScores)) {
                foreach ($waveMap as $wave => $numbers) {
                    $waveScores[$wave] = 0.0;
                    foreach ($numbers as $number) {
                        $waveScores[$wave] += (float) ($frequency[$number] ?? 0);
                    }
                }
            }
            arsort($waveScores);
            $preferredWaves = array_slice(array_keys($waveScores), 0, 1);
            $preferredNumbers = array();
            foreach ($preferredWaves as $wave) {
                $preferredNumbers = array_merge($preferredNumbers, $waveMap[$wave]);
            }

            return array(
                'label' => '波色',
                'detail' => '波色优先：' . implode('、', $preferredWaves),
                'preferred_numbers' => $preferredNumbers,
                'display_values' => $preferredWaves,
                'summary_count' => 1,
                'summary_unit' => '个波色',
            );
        }

        if ($value === 'big_small') {
            $specialBigSmall = !empty($heatStats['special_big_small']) && is_array($heatStats['special_big_small'])
                ? (array) $heatStats['special_big_small']
                : array('小' => 0.0, '大' => 0.0);
            $smallScore = (float) ($specialBigSmall['小'] ?? 0.0);
            $bigScore = (float) ($specialBigSmall['大'] ?? 0.0);
            $preferSmall = $smallScore >= $bigScore;
            $preferredNumbers = array();
            for ($number = 1; $number <= 49; $number++) {
                if (($number <= 24) === $preferSmall) {
                    $preferredNumbers[] = $number;
                }
            }

            return array(
                'label' => '大小',
                'detail' => '大小倾向：' . ($preferSmall ? '小' : '大'),
                'preferred_numbers' => $preferredNumbers,
                'display_values' => array($preferSmall ? '小' : '大'),
                'summary_count' => 1,
                'summary_unit' => '个大小',
            );
        }

        if ($value === 'head') {
            $headScores = !empty($heatStats['special_heads']) && is_array($heatStats['special_heads'])
                ? (array) $heatStats['special_heads']
                : array_fill(0, 5, 0.0);
            arsort($headScores);
            $preferredHeads = array_slice(array_keys($headScores), 0, 1);
            $preferredNumbers = array();
            for ($number = 1; $number <= 49; $number++) {
                if (in_array((int) floor($number / 10), $preferredHeads, true)) {
                    $preferredNumbers[] = $number;
                }
            }

            return array(
                'label' => '头数',
                'detail' => '头数优先：' . implode('、', array_map(function ($head) {
                    return $head . '头';
                }, $preferredHeads)),
                'preferred_numbers' => $preferredNumbers,
                'display_values' => array_map(function ($head) {
                    return $head . '头';
                }, $preferredHeads),
                'summary_count' => 1,
                'summary_unit' => '个头数',
            );
        }

        if ($value === 'tail') {
            $tailScores = !empty($heatStats['special_tails']) && is_array($heatStats['special_tails'])
                ? (array) $heatStats['special_tails']
                : array_fill(0, 10, 0.0);
            arsort($tailScores);
            $preferredTails = array_slice(array_keys($tailScores), 0, 1);
            $preferredNumbers = array();
            for ($number = 1; $number <= 49; $number++) {
                if (in_array($number % 10, $preferredTails, true)) {
                    $preferredNumbers[] = $number;
                }
            }

            return array(
                'label' => '尾数',
                'detail' => '尾数优先：' . implode('、', array_map(function ($tail) {
                    return $tail . '尾';
                }, $preferredTails)),
                'preferred_numbers' => $preferredNumbers,
                'display_values' => array_map(function ($tail) {
                    return $tail . '尾';
                }, $preferredTails),
                'summary_count' => 1,
                'summary_unit' => '个尾数',
            );
        }

        return null;
    }

    protected function forecastSummaryLine($title, $label, $count, $unit)
    {
        $title = trim((string) $title);
        $label = trim((string) $label);
        $unit = trim((string) $unit);
        $count = (int) $count;

        if ($title === '') {
            return '';
        }

        if ($label === '') {
            return $title . '：未选择';
        }

        if ($count > 0 && $unit !== '') {
            return $title . '：' . $label . ' ' . $count . $unit;
        }

        return $title . '：' . $label;
    }

    protected function forecastZodiacNumberMap()
    {
        return array(
            '马' => array(1, 13, 25, 37, 49),
            '蛇' => array(2, 14, 26, 38),
            '龙' => array(3, 15, 27, 39),
            '兔' => array(4, 16, 28, 40),
            '虎' => array(5, 17, 29, 41),
            '牛' => array(6, 18, 30, 42),
            '鼠' => array(7, 19, 31, 43),
            '猪' => array(8, 20, 32, 44),
            '狗' => array(9, 21, 33, 45),
            '鸡' => array(10, 22, 34, 46),
            '猴' => array(11, 23, 35, 47),
            '羊' => array(12, 24, 36, 48),
        );
    }

    protected function resolveForecastZodiacLabels(array $frequency, $count, array $heatStats = array(), $scope = 'all')
    {
        $count = max(0, min(12, (int) $count));
        if ($count === 0) {
            return array();
        }

        $scope = (string) $scope;
        $blockedZodiacs = (array) ($heatStats['blocked_zodiacs'] ?? array());
        if ($scope === 'special') {
            $zodiacLevels = (array) ($heatStats['special_zodiac_levels'] ?? array());
            $zodiacFrequency = (array) ($heatStats['special_zodiacs'] ?? array());
        } elseif ($scope === 'normal') {
            $zodiacLevels = (array) ($heatStats['normal_zodiac_levels'] ?? array());
            $zodiacFrequency = (array) ($heatStats['normal_zodiacs'] ?? array());
        } else {
            $zodiacLevels = (array) ($heatStats['zodiac_levels'] ?? array());
            $zodiacFrequency = array();
        }
        $maxLevel = $count <= 3 ? 1 : ($count <= 6 ? 2 : 3);
        $zodiacScores = array();
        foreach ($this->forecastZodiacNumberMap() as $zodiac => $numbers) {
            if (isset($blockedZodiacs[$zodiac])) {
                continue;
            }
            $level = (int) ($zodiacLevels[$zodiac] ?? 4);
            if (!empty($zodiacLevels) && $level > $maxLevel) {
                continue;
            }
            if ($scope === 'special' || $scope === 'normal') {
                $zodiacScores[$zodiac] = (float) ($zodiacFrequency[$zodiac] ?? 0.0);
            } else {
                $zodiacScores[$zodiac] = 0.0;
                foreach ($numbers as $number) {
                    $zodiacScores[$zodiac] += (float) ($frequency[$number] ?? 0);
                }
            }
        }

        arsort($zodiacScores);
        if (count($zodiacScores) < $count && !empty($zodiacLevels)) {
            $fallbackZodiacs = ($scope === 'special' || $scope === 'normal') ? $zodiacFrequency : (array) ($heatStats['zodiacs'] ?? array());
            foreach ($this->rankForecastStatLabels($fallbackZodiacs, 12) as $zodiac) {
                if (!isset($blockedZodiacs[$zodiac]) && !isset($zodiacScores[$zodiac])) {
                    $zodiacScores[$zodiac] = (float) ($fallbackZodiacs[$zodiac] ?? 0.0);
                }
                if (count($zodiacScores) >= $count) {
                    break;
                }
            }
            arsort($zodiacScores);
        }

        return array_slice(array_keys($zodiacScores), 0, $count);
    }

    protected function forecastWaveNumberMap()
    {
        return array(
            '红波' => array(1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46),
            '蓝波' => array(3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48),
            '绿波' => array(5, 6, 11, 16, 17, 21, 22, 27, 28, 32, 33, 38, 39, 43, 44, 49),
        );
    }

    protected function forecastFiveElementNumberMap()
    {
        return array(
            '金' => array(2, 3, 10, 11, 24, 25, 32, 33, 40, 41),
            '木' => array(6, 7, 14, 15, 22, 23, 36, 37, 44, 45),
            '水' => array(12, 13, 20, 21, 28, 29, 42, 43),
            '火' => array(1, 8, 9, 16, 17, 30, 31, 38, 39, 46, 47),
            '土' => array(4, 5, 18, 19, 26, 27, 34, 35, 48, 49),
        );
    }

    protected function mergeForecastNumberSets(array $sets)
    {
        $merged = array();
        foreach ($sets as $set) {
            foreach ((array) $set as $number) {
                $merged[(int) $number] = true;
            }
        }

        $numbers = array_map('intval', array_keys($merged));
        sort($numbers);

        return $numbers;
    }

    protected function intersectForecastNumberSets(array $sets)
    {
        if (empty($sets)) {
            return array();
        }

        $intersection = array_values(array_unique(array_map('intval', (array) array_shift($sets))));
        foreach ($sets as $set) {
            $intersection = array_values(array_intersect($intersection, array_values(array_unique(array_map('intval', (array) $set)))));
            if (empty($intersection)) {
                return array();
            }
        }

        sort($intersection);

        return $intersection;
    }

    protected function filterForecastNumberPool(array $numbers, array $lookup)
    {
        if (empty($lookup)) {
            return array_values(array_map('intval', $numbers));
        }

        $filtered = array();
        foreach ($numbers as $number) {
            $number = (int) $number;
            if (isset($lookup[$number])) {
                $filtered[] = $number;
            }
        }

        return $filtered;
    }

    public function saveDraw(array $payload, $actor)
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $region = isset($payload['region']) ? (string) $payload['region'] : 'macau';
        $issueNo = trim((string) $payload['issue_no']);
        $drawDate = trim((string) $payload['draw_date']);
        $numbers = trim((string) $payload['numbers']);
        $special = (int) $payload['special_number'];
        $note = trim((string) $payload['note']);

        if ($issueNo === '' || $drawDate === '' || $numbers === '') {
            throw new RuntimeException('期号、日期和开奖号码不能为空。');
        }

        $numberList = array_values(array_filter(array_map('trim', preg_split('/[\\s,，]+/', $numbers))));
        if (count($numberList) !== 6) {
            throw new RuntimeException('必须填写 6 个正选号码。');
        }

        $numberList = array_map('intval', $numberList);
        foreach ($numberList as $number) {
            if ($number < 1 || $number > 49) {
                throw new RuntimeException('正选号码必须是 01-49 范围内的整数。');
            }
        }
        if ($special < 1 || $special > 49) {
            throw new RuntimeException('特码必须是 01-49 范围内的整数。');
        }
        if (count(array_unique(array_merge($numberList, array($special)))) !== 7) {
            throw new RuntimeException('每期 7 个号码不能重复。');
        }

        $data = array(
            'region' => $region,
            'issue_no' => $issueNo,
            'draw_date' => $drawDate,
            'numbers_json' => json_encode($numberList),
            'special_number' => $special,
            'note' => $note,
            'created_by' => $actor['id'],
            'updated_at' => $this->now(),
        );

        if ($id > 0) {
            $this->db()->execute('UPDATE lottery_draws SET region = :region, issue_no = :issue_no, draw_date = :draw_date, numbers_json = :numbers_json, special_number = :special_number, note = :note, updated_at = :updated_at WHERE id = :id', $data + array('id' => $id));
        } else {
            $this->db()->execute('INSERT INTO lottery_draws (region, issue_no, draw_date, numbers_json, special_number, note, created_by, created_at, updated_at) VALUES (:region, :issue_no, :draw_date, :numbers_json, :special_number, :note, :created_by, :created_at, :updated_at)', $data + array('created_at' => $this->now()));
        }

        $this->app->logs()->admin('draws', $id > 0 ? 'update' : 'create', '保存开奖资料：' . $issueNo, 'draw', $id > 0 ? (string) $id : $issueNo, $actor['id']);
    }

    public function listDraws($region = null, $limit = 50)
    {
        if ($region !== null && $region !== '') {
            return $this->db()->fetchAll('SELECT lottery_draws.*, users.username FROM lottery_draws LEFT JOIN users ON users.id = lottery_draws.created_by WHERE lottery_draws.region = :region' . $this->drawOrderSql() . ' LIMIT ' . (int) $limit, array(
                'region' => $region,
            ));
        }

        return $this->db()->fetchAll('SELECT lottery_draws.*, users.username FROM lottery_draws LEFT JOIN users ON users.id = lottery_draws.created_by' . $this->drawOrderSql() . ' LIMIT ' . (int) $limit);
    }
}
