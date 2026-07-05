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

track_page($region === 'hongkong' ? 'front_history_hongkong' : 'front_history_macau');

$user = current_user();
$recentDraws = array();

try {
    $recentDraws = app()->prediction()->frontCurrentYearHistoryDraws($region, 160);
} catch (\Throwable $exception) {
    $recentDraws = array();
}

$pageRegionName = $region === 'hongkong' ? '香港' : '澳门';

view('front/history_draws', array(
    'pageTitle' => browser_title_setting('888888论坛') . ' - ' . browser_region_title_setting($region, $pageRegionName . '论坛') . '往期开奖记录',
    'pageDescription' => $pageRegionName . '六合彩历史开奖记录页面',
    'bodyClass' => 'standalone-panel front-unified-panel-page history-panel-page',
    'region' => $region,
    'user' => $user,
    'recentDraws' => $recentDraws,
), 'layouts/home_legacy');
