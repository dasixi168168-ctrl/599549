<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/front_security.php';
front_security_apply(front_public_page_cache_options());

require dirname(__DIR__) . '/bootstrap/app.php';

ensure_installed_or_redirect();
run_housekeeping();
track_page('front_hongkong');

$user = current_user();
$region = 'hongkong';
$latestDraw = app()->prediction()->frontHomepageDraw($region);

view('front/home_legacy', array(
    'pageTitle' => browser_title_setting('888888论坛') . ' - ' . browser_region_title_setting('hongkong', '香港论坛'),
    'pageDescription' => '香港六合彩论坛首页',
    'bodyClass' => 'front-home-page front-record-page front-record-page-hongkong',
    'region' => $region,
    'user' => $user,
    'latestDraw' => $latestDraw,
), 'layouts/home_legacy');
