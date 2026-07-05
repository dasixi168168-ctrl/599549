<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/front_security.php';
front_security_apply(front_public_page_cache_options());

require dirname(__DIR__) . '/bootstrap/app.php';

ensure_installed_or_redirect();
run_housekeeping();

$region = (string) input('region', 'macau');
if (!in_array($region, array('macau', 'hongkong'), true)) {
    $region = 'macau';
}

track_page($region === 'hongkong' ? 'front_forecast_hongkong' : 'front_forecast_macau');

$user = current_user();
$customerServiceForecastAgent = null;
$customerServiceForecastAgentEntry = (string) input('agent', '0') === '1'
    || (string) \App\Core\Session::get('customer_service_agent_entry', '0') === '1';
if ($customerServiceForecastAgentEntry) {
    try {
        $customerServiceForecastAgent = app()->support()->currentAgent();
    } catch (Throwable $exception) {
        $customerServiceForecastAgent = null;
    }
}
$dashboardMetrics = array(
    'members' => 0,
    'posts' => 0,
    'visits_today' => 0,
    'open_threads' => 0,
);
$forecastStats = array(
    'today_participants' => 0,
    'previous_participants' => 0,
    'previous_winners' => 0,
);
$recentPredictions = array();
$recentDraws = array();
$currentPrediction = null;
$forecastError = '';
$currentFilters = array(
    'zodiac_type' => trim((string) input('zodiac_type', '')),
    'number_type' => trim((string) input('number_type', '')),
    'pingte_type' => trim((string) input('pingte_type', '')),
    'other_type' => trim((string) input('other_type', '')),
);
$forecastFilterOptions = app()->admins()->forecastFilterOptions();
$normalizeForecastFilters = static function (array $filters) {
    return array(
        'zodiac_type' => trim((string) ($filters['zodiac_type'] ?? '')),
        'number_type' => trim((string) ($filters['number_type'] ?? '')),
        'pingte_type' => trim((string) ($filters['pingte_type'] ?? '')),
        'other_type' => trim((string) ($filters['other_type'] ?? '')),
    );
};
$forecastFiltersHaveSelection = static function (array $filters) {
    foreach ($filters as $filterValue) {
        if (trim((string) $filterValue) !== '') {
            return true;
        }
    }

    return false;
};
$sessionGeneratedPrediction = isset($_SESSION['forecast_generated_predictions'][$region]) && is_array($_SESSION['forecast_generated_predictions'][$region])
    ? $_SESSION['forecast_generated_predictions'][$region]
    : array();
$sessionGeneratedFilters = isset($sessionGeneratedPrediction['filters']) && is_array($sessionGeneratedPrediction['filters'])
    ? $normalizeForecastFilters($sessionGeneratedPrediction['filters'])
    : $normalizeForecastFilters(array());
$sessionGeneratedGuestOnce = !empty($sessionGeneratedPrediction['guest_once']);
$currentFiltersLoadedFromSession = false;
if (
    $user
    && !$sessionGeneratedGuestOnce
    && !$forecastFiltersHaveSelection($currentFilters)
    && $forecastFiltersHaveSelection($sessionGeneratedFilters)
    && isset($sessionGeneratedPrediction['prediction'])
    && is_array($sessionGeneratedPrediction['prediction'])
) {
    $currentFilters = $sessionGeneratedFilters;
    $currentFiltersLoadedFromSession = true;
}
$normalizeForecastResultNote = static function ($value, $maxBytes) {
    $normalized = preg_replace('/[\r\n\t]+/', ' ', trim((string) $value));
    if ($normalized === null || strlen($normalized) > $maxBytes) {
        return '';
    }

    return $normalized;
};
$forecastResultNote = isset($_SESSION['forecast_result_notes'][$region]) && is_array($_SESSION['forecast_result_notes'][$region])
    ? $_SESSION['forecast_result_notes'][$region]
    : array();
$forecastResultNoteFilters = isset($forecastResultNote['filters']) && is_array($forecastResultNote['filters'])
    ? $forecastResultNote['filters']
    : array();
$forecastResultIdiom = $forecastResultNoteFilters == $currentFilters
    ? $normalizeForecastResultNote($forecastResultNote['idiom'] ?? '', 60)
    : '';
$forecastResultBlessing = $forecastResultNoteFilters == $currentFilters
    ? $normalizeForecastResultNote($forecastResultNote['blessing'] ?? '', 240)
    : '';
$hasActiveForecastFilters = $forecastFiltersHaveSelection($currentFilters);
if (!$hasActiveForecastFilters) {
    $forecastResultIdiom = '';
    $forecastResultBlessing = '';
}
$forecastPricingSummary = app()->admins()->forecastPricingForFilters($currentFilters);
$liveBoxHtml = '';
$livePayload = array(
    'region' => $region,
    'draw' => null,
    'current_issue' => null,
    'api_url' => public_url('api.php'),
    'api_token' => csrf_token('api'),
);

$normalizePrediction = function (array $item) use ($region) {
    $numbers = json_decode((string) $item['numbers_json'], true);
    $displayPayloads = json_decode((string) ($item['display_payloads_json'] ?? ''), true);
    $lineConfidences = json_decode((string) ($item['line_confidences_json'] ?? ''), true);
    $confidence = isset($item['confidence']) ? (float) $item['confidence'] : 0.0;
    $predictionIssue = (string) ($item['generated_for_issue'] ?? '');

    return array(
        'issue' => (string) $predictionIssue,
        'summary' => (string) $item['summary'],
        'numbers' => array_values(array_map('intval', is_array($numbers) ? $numbers : array())),
        'confidence' => $confidence > 0 ? min(97.0, max(89.0, $confidence)) : 0.0,
        'created_at' => isset($item['created_at']) ? format_datetime($item['created_at']) : '',
        'display_payloads' => is_array($displayPayloads) ? $displayPayloads : array(),
        'line_confidences' => is_array($lineConfidences) ? $lineConfidences : array(),
    );
};

$mapGeneratedPrediction = function (array $generated) use ($region) {
    $confidence = isset($generated['confidence']) ? (float) $generated['confidence'] : 0.0;
    $predictionIssue = (string) ($generated['generated_for_issue'] ?? '');

    return array(
        'issue' => (string) $predictionIssue,
        'summary' => (string) ($generated['summary'] ?? ''),
        'numbers' => array_values(array_map('intval', (array) ($generated['numbers'] ?? array()))),
        'confidence' => $confidence > 0 ? min(97.0, max(89.0, $confidence)) : 0.0,
        'created_at' => '',
        'display_payloads' => is_array($generated['display_payloads'] ?? null) ? $generated['display_payloads'] : array(),
        'line_confidences' => is_array($generated['line_confidences'] ?? null) ? $generated['line_confidences'] : array(),
    );
};

$forecastIssueParts = static function ($issueNo) {
    $issueNo = trim((string) $issueNo);
    if ($issueNo === '' || preg_match('/(\d+)$/', $issueNo, $matches) !== 1) {
        return null;
    }

    $digits = (string) $matches[1];
    $visibleDigits = strlen($digits) >= 5 ? substr($digits, 4) : $digits;
    $fullDigits = ltrim($digits, '0');
    $visibleDigits = ltrim($visibleDigits, '0');

    return array(
        'digits' => $digits,
        'full' => $fullDigits === '' ? 0 : (int) $fullDigits,
        'visible' => $visibleDigits === '' ? 0 : (int) $visibleDigits,
    );
};

$forecastIssueCompare = static function ($leftIssue, $rightIssue) use ($forecastIssueParts) {
    $leftParts = $forecastIssueParts($leftIssue);
    $rightParts = $forecastIssueParts($rightIssue);
    if (!$leftParts || !$rightParts) {
        return null;
    }

    $leftValue = $leftParts['visible'];
    $rightValue = $rightParts['visible'];
    if (strlen($leftParts['digits']) >= 5 && strlen($rightParts['digits']) >= 5) {
        $leftValue = $leftParts['full'];
        $rightValue = $rightParts['full'];
    }

    if ($leftValue === $rightValue) {
        return 0;
    }

    return $leftValue < $rightValue ? -1 : 1;
};

$forecastIssueIsBehind = static function (
    $predictionIssue,
    $currentIssue
) use ($forecastIssueCompare) {
    return $forecastIssueCompare($predictionIssue, $currentIssue) === -1;
};

$forecastIssueIsSameOrBehind = static function (
    $leftIssue,
    $rightIssue
) use ($forecastIssueCompare) {
    $issueCompare = $forecastIssueCompare($leftIssue, $rightIssue);

    return $issueCompare !== null && $issueCompare <= 0;
};

$forecastIncrementIssueNo = static function ($issueNo) {
    $issueNo = trim((string) $issueNo);
    if ($issueNo === '') {
        return '';
    }

    if (preg_match('/^(.*?)(\d+)(\D*)$/u', $issueNo, $matches) !== 1) {
        return $issueNo;
    }

    $prefix = (string) $matches[1];
    $numberPart = (string) $matches[2];
    $suffix = (string) $matches[3];
    $nextNumber = str_pad((string) ((int) $numberPart + 1), strlen($numberPart), '0', STR_PAD_LEFT);

    return $prefix . $nextNumber . $suffix;
};

$normalizeDraw = function (array $item) {
    $numbers = json_decode((string) $item['numbers_json'], true);

    return array(
        'issue_no' => (string) $item['issue_no'],
        'draw_date' => (string) $item['draw_date'],
        'numbers' => array_values(array_map('intval', is_array($numbers) ? $numbers : array())),
        'special_number' => (int) $item['special_number'],
    );
};

$stripLegacyHomeData = static function (string $html): string {
    return (string) preg_replace('/\s*<script id="legacy-home-data"[\s\S]*?<\/script>\s*$/u', '', $html, 1);
};

$extractLiveBox = static function (string $html) use ($stripLegacyHomeData): string {
    $html = $stripLegacyHomeData($html);
    $patterns = array(
        '/<div id="section-live"[\s\S]*?<\/div>\s*(?=<div\b[^>]*class="[^"]*\bmarquee\b)/u',
        '/<div id="section-live"[\s\S]*?<\/div>\s*(?=<\/div>\s*<\/section>)/u',
    );

    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $html, $matches)) {
            return trim((string) ($matches[0] ?? ''));
        }
    }

    return '';
};

try {
    $dashboardMetrics = app()->stats()->dashboardMetrics();
} catch (\Throwable $exception) {
    $dashboardMetrics = $dashboardMetrics;
}

try {
    $forecastStats = app()->stats()->forecastParticipationMetrics($region);
} catch (\Throwable $exception) {
    $forecastStats = $forecastStats;
}

try {
    foreach (app()->prediction()->recentPredictions($region, 6) as $predictionRow) {
        $recentPredictions[] = $normalizePrediction($predictionRow);
    }
    if (!empty($recentPredictions)) {
        $currentPrediction = $recentPredictions[0];
    }
} catch (\Throwable $exception) {
    $recentPredictions = array();
}

try {
    foreach (app()->prediction()->latestAvailableDraws($region, 6) as $drawRow) {
        $recentDraws[] = $normalizeDraw($drawRow);
    }
} catch (\Throwable $exception) {
    $recentDraws = array();
}

try {
    $livePayload['draw'] = app()->prediction()->latestHomepageDraw($region);
} catch (\Throwable $exception) {
    $livePayload['draw'] = null;
}

if (!is_array($livePayload['draw']) && !empty($recentDraws)) {
    $livePayload['draw'] = $recentDraws[0];
}

try {
    $livePayload['current_issue'] = app()->admins()->currentIssueSnapshotByRegion($region);
} catch (\Throwable $exception) {
    $livePayload['current_issue'] = null;
}

$forecastCurrentIssueNo = is_array($livePayload['current_issue'])
    ? trim((string) ($livePayload['current_issue']['issue_no'] ?? ''))
    : '';
$latestOpenedIssueNo = is_array($livePayload['draw'])
    ? trim((string) ($livePayload['draw']['issue_no'] ?? ''))
    : '';
if (
    $latestOpenedIssueNo !== ''
    && (
        $forecastCurrentIssueNo === ''
        || $forecastIssueIsSameOrBehind($forecastCurrentIssueNo, $latestOpenedIssueNo)
    )
) {
    $nextIssueNo = $forecastIncrementIssueNo($latestOpenedIssueNo);
    if ($nextIssueNo !== '') {
        $forecastCurrentIssueNo = $nextIssueNo;
        if (!is_array($livePayload['current_issue'])) {
            $livePayload['current_issue'] = array();
        }
        $livePayload['current_issue']['region'] = $region;
        $livePayload['current_issue']['issue_no'] = $nextIssueNo;
    }
}

if ($forecastCurrentIssueNo !== '') {
    $currentPredictionIssue = $currentPrediction
        ? trim((string) ($currentPrediction['issue'] ?? ''))
        : '';
    $isPredictionBehindCurrent = $currentPredictionIssue !== ''
        && $forecastIssueIsBehind($currentPredictionIssue, $forecastCurrentIssueNo);
    if ($isPredictionBehindCurrent) {
        $currentPrediction = null;
    }

    $sessionPredictionIssue = '';
    $hasSessionPrediction = isset($sessionGeneratedPrediction['prediction'])
        && is_array($sessionGeneratedPrediction['prediction']);
    if ($hasSessionPrediction) {
        $sessionPredictionIssue = trim(
            (string) ($sessionGeneratedPrediction['prediction']['generated_for_issue'] ?? '')
        );
    }

    $isSessionPredictionBehindCurrent = $sessionPredictionIssue !== ''
        && $forecastIssueIsBehind($sessionPredictionIssue, $forecastCurrentIssueNo);
    if ($isSessionPredictionBehindCurrent) {
        unset(
            $_SESSION['forecast_generated_predictions'][$region],
            $_SESSION['forecast_result_notes'][$region]
        );
        $sessionGeneratedPrediction = array();
        $sessionGeneratedFilters = $normalizeForecastFilters(array());
        $sessionGeneratedGuestOnce = false;
        $forecastResultIdiom = '';
        $forecastResultBlessing = '';

        if ($currentFiltersLoadedFromSession) {
            $currentFilters = $normalizeForecastFilters(array());
            $hasActiveForecastFilters = false;
            $forecastPricingSummary = app()->admins()->forecastPricingForFilters($currentFilters);
        }
    }
}

$templatePath = app()->basePath('resources/defaults/home_editor_default.html');
$templateHtml = is_file($templatePath) ? (string) file_get_contents($templatePath) : '';
$managedMaterialHtml = trim((string) site_setting('draws.material_html.' . $region, ''));

if ($managedMaterialHtml !== '') {
    $liveBoxHtml = $extractLiveBox($managedMaterialHtml);
}

if ($liveBoxHtml === '') {
    $liveBoxHtml = $extractLiveBox($templateHtml);
}

$fixedGeneratedPrediction = null;
if ($hasActiveForecastFilters && $sessionGeneratedFilters == $currentFilters && isset($sessionGeneratedPrediction['prediction']) && is_array($sessionGeneratedPrediction['prediction'])) {
    $fixedGeneratedPrediction = $mapGeneratedPrediction($sessionGeneratedPrediction['prediction']);
    if (!$user && $sessionGeneratedGuestOnce) {
        unset($_SESSION['forecast_generated_predictions'][$region], $_SESSION['forecast_result_notes'][$region]);
    }
}

if ($hasActiveForecastFilters) {
    if ($fixedGeneratedPrediction !== null) {
        $currentPrediction = $fixedGeneratedPrediction;
    } else {
        $currentPrediction = null;
        $forecastError = '请点击即刻中奖参与预测。';
    }
} elseif ($currentPrediction === null) {
    try {
        $generated = app()->prediction()->frontCachedForecast($region, $currentFilters, 300, $forecastCurrentIssueNo);
        $currentPrediction = $mapGeneratedPrediction($generated);
    } catch (\Throwable $exception) {
        $forecastError = $exception->getMessage();
    }
}

$pageRegionName = $region === 'hongkong' ? '香港' : '澳门';

view('front/forecast', array(
    'pageTitle' => browser_title_setting('888888论坛') . ' - ' . browser_region_title_setting($region, $pageRegionName . '论坛') . 'AI预测',
    'pageDescription' => $pageRegionName . '六合彩 AI 预测页面',
    'bodyClass' => 'standalone-panel front-unified-panel-page forecast-panel-page',
    'region' => $region,
    'currentPrediction' => $currentPrediction,
    'recentPredictions' => $recentPredictions,
    'recentDraws' => $recentDraws,
    'dashboardMetrics' => $dashboardMetrics,
    'forecastStats' => $forecastStats,
    'forecastError' => $forecastError,
    'currentFilters' => $currentFilters,
    'forecastFilterOptions' => $forecastFilterOptions,
    'forecastPricingSummary' => $forecastPricingSummary,
    'forecastResultIdiom' => $forecastResultIdiom,
    'forecastResultBlessing' => $forecastResultBlessing,
    'liveBoxHtml' => $liveBoxHtml,
    'livePayload' => $livePayload,
    'user' => $user,
    'customerServiceAgentViewer' => is_array($customerServiceForecastAgent),
), 'layouts/home_legacy');
