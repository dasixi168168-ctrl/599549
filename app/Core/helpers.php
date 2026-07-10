<?php
declare(strict_types=1);

use App\Core\Application;
use App\Core\Csrf;
use App\Core\Security;
use App\Core\View;

function app()
{
    return Application::getInstance();
}

function db()
{
    return app()->db();
}

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect($url)
{
    header('Location: ' . $url, true, 302);
    exit;
}

function abort($statusCode, $message)
{
    http_response_code((int) $statusCode);
    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>请求失败</title><style>body{margin:0;font-family:"PingFang SC","Microsoft YaHei",sans-serif;background:#f1f5f9;color:#0f172a}.abort-wrap{min-height:100vh;display:grid;place-items:center;padding:24px}.abort-card{width:min(100%,640px);padding:32px;border-radius:28px;border:1px solid #e2e8f0;background:#fff;box-shadow:0 18px 40px rgba(15,23,42,.08)}.abort-code{color:#dc2626;font-size:13px;font-weight:800;letter-spacing:.18em}.abort-title{margin:14px 0 0;font-size:34px;line-height:1.2;font-weight:900}.abort-text{margin:16px 0 0;color:#475569;font-size:15px;line-height:1.9}.abort-link{display:inline-flex;margin-top:22px;align-items:center;justify-content:center;min-height:46px;padding:0 20px;border-radius:18px;background:#0f172a;color:#fff;text-decoration:none;font-weight:800}</style></head><body><div class="abort-wrap"><div class="abort-card"><div class="abort-code">HTTP ' . (int) $statusCode . '</div><h1 class="abort-title">操作未完成</h1><p class="abort-text">' . e($message) . '</p><a href="javascript:history.back()" class="abort-link">返回上一页</a></div></div></body></html>';
    exit;
}

function json_response(array $payload, $statusCode = 200)
{
    http_response_code((int) $statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function request_method()
{
    return strtoupper(isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'GET');
}

function is_post()
{
    return request_method() === 'POST';
}

function is_prefetch_request()
{
    $headers = array(
        'HTTP_PURPOSE',
        'HTTP_SEC_PURPOSE',
        'HTTP_X_MOZ',
    );

    foreach ($headers as $headerName) {
        $headerValue = isset($_SERVER[$headerName]) ? (string) $_SERVER[$headerName] : '';
        if ($headerValue !== '' && stripos($headerValue, 'prefetch') !== false) {
            return true;
        }
    }

    return false;
}

function input($key, $default = null)
{
    if (request_method() === 'POST') {
        return isset($_POST[$key]) ? $_POST[$key] : $default;
    }

    return isset($_GET[$key]) ? $_GET[$key] : $default;
}

function view($template, array $data = array(), $layout = null)
{
    View::render(app(), $template, $data, $layout);
}

function asset($path)
{
    return public_base_path() . '/assets/' . ltrim($path, '/');
}

function public_base_path()
{
    $scriptName = str_replace('\\', '/', isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '');
    $directory = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

    if ($directory === '' || $directory === '.') {
        return '/public';
    }

    return $directory;
}

function public_url($path = '')
{
    $base = rtrim(public_base_path(), '/');

    return $base . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function site_setting($key, $default = '')
{
    if (!app()->isInstalled()) {
        return $default;
    }

    return app()->settings()->get($key, $default);
}

function browser_title_setting($default = '')
{
    $fallback = $default;
    if ($fallback === '') {
        $fallback = app()->config('app', 'site_name', '888888论坛');
    }

    $browserTitle = site_setting('browser.title', site_setting('site.title', $fallback));
    $scriptName = str_replace('\\', '/', isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '');

    if (basename($scriptName) === 'admin.php') {
        return site_setting('admin.browser_title', $browserTitle);
    }

    return $browserTitle;
}

function admin_browser_title_setting($default = '')
{
    $fallback = $default;
    if ($fallback === '') {
        $fallback = browser_title_setting(app()->config('app', 'site_name', '后台管理'));
    }

    return site_setting('admin.browser_title', $fallback);
}

function admin_management_name_setting($default = '')
{
    $fallback = $default !== '' ? $default : '666绀惧尯';

    return site_setting('admin.management_name', $fallback);
}

function browser_region_title_setting($region, $default = '')
{
    $regionKey = $region === 'hongkong' ? 'hongkong' : 'macau';
    $fallback = $default !== '' ? $default : ($regionKey === 'hongkong' ? '香港论坛' : '澳门论坛');

    return site_setting('browser.region_title_' . $regionKey, $fallback);
}

function csrf_token($namespace = 'default')
{
    return Csrf::token($namespace);
}

function require_csrf($namespace = 'default', $token = null)
{
    $token = $token === null ? input('_token', '') : $token;
    if (!Csrf::validate((string) $token, $namespace)) {
        abort(419, '表单令牌已失效，请刷新页面后重试。');
    }
}

function current_user()
{
    return app()->currentUser();
}

function current_admin()
{
    return app()->auth()->adminUser();
}

function is_admin()
{
    return app()->auth()->isAdmin();
}

function format_datetime($value)
{
    if (!$value) {
        return '-';
    }

    return date('Y-m-d H:i', strtotime((string) $value));
}

function format_date($value)
{
    if (!$value) {
        return '-';
    }

    return date('Y-m-d', strtotime((string) $value));
}

function money_points($value)
{
    return (int) $value . ' 积分';
}

function truncate_text($value, $length = 48)
{
    $value = trim(strip_tags((string) $value));
    if (mb_strlen($value, 'UTF-8') <= $length) {
        return $value;
    }

    return mb_substr($value, 0, $length, 'UTF-8') . '...';
}

function admin_draw_region_key($region)
{
    return (string) $region === 'hongkong' ? 'hongkong' : 'macau';
}

function admin_draw_region_label($region)
{
    return admin_draw_region_key($region) === 'hongkong' ? '香港' : '澳门';
}

function admin_draw_issue_tail($issueNo)
{
    $text = trim((string) $issueNo);
    if ($text === '' || !preg_match('/^\d+$/', $text)) {
        return '--';
    }

    $tail = strlen($text) > 3 ? substr($text, -3) : $text;

    return str_pad($tail, 3, '0', STR_PAD_LEFT);
}

function admin_draw_pad_number($value)
{
    $number = (int) $value;

    return $number < 10 ? '0' . $number : (string) $number;
}

function admin_draw_wave_color_class($value)
{
    $number = (int) $value;
    if (in_array($number, array(1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46), true)) {
        return 'is-red';
    }

    if (in_array($number, array(3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48), true)) {
        return 'is-blue';
    }

    return 'is-green';
}

function admin_render_draw_ball($value, $drawDate = '')
{
    $value = $value === null ? null : (int) $value;
    $ballClass = $value === null ? 'is-empty' : admin_draw_wave_color_class($value);
    $numberText = $value === null ? '--' : admin_draw_pad_number($value);
    $zodiacText = $value === null ? '--' : (app()->prediction()->drawZodiacByNumber($value, (string) $drawDate) ?: '--');

    return '<div class="admin-header-draw-ball ' . e($ballClass) . '">' .
        '<div class="admin-header-draw-ball-code">' . e($numberText) . '</div>' .
        '<div class="admin-header-draw-ball-zodiac">' . e($zodiacText) . '</div>' .
        '</div>';
}

function admin_render_shared_draw_card($draw, $region, array $options = array())
{
    $region = admin_draw_region_key($region);
    $draw = is_array($draw) ? $draw : null;
    $drawDate = $draw !== null ? trim((string) ($draw['draw_date'] ?? '')) : '';
    $issueText = '--期';
    $ballsHtml = '';

    if ($draw !== null) {
        $issueText = admin_draw_issue_tail($draw['issue_no'] ?? '') . '期';
        $numbers = isset($draw['numbers']) && is_array($draw['numbers'])
            ? array_values($draw['numbers'])
            : json_decode((string) ($draw['numbers_json'] ?? '[]'), true);
        $numbers = is_array($numbers) ? array_values($numbers) : array();

        for ($index = 0; $index < 6; $index += 1) {
            $value = array_key_exists($index, $numbers) ? (int) $numbers[$index] : null;
            $ballsHtml .= admin_render_draw_ball($value > 0 ? $value : null, $drawDate);
        }

        $ballsHtml .= '<div class="admin-header-draw-ball-plus">+</div>';
        $specialNumber = (int) ($draw['special_number'] ?? 0);
        $ballsHtml .= admin_render_draw_ball($specialNumber > 0 ? $specialNumber : null, $drawDate);
    }

    if ($ballsHtml === '') {
        for ($index = 0; $index < 6; $index += 1) {
            $ballsHtml .= admin_render_draw_ball(null, $drawDate);
        }
        $ballsHtml .= '<div class="admin-header-draw-ball-plus">+</div>';
        $ballsHtml .= admin_render_draw_ball(null, $drawDate);
    }

    $classes = trim('admin-header-draw-card admin-shared-draw-card ' . (string) ($options['extra_class'] ?? ''));
    $attributes = 'data-region="' . e($region) . '"';
    if (!empty($options['data_admin_header_draw'])) {
        $attributes = 'data-admin-header-draw ' . $attributes;
    }

    return '<div class="' . e($classes) . '" ' . $attributes . '>' .
        '<div class="admin-header-draw-meta">' .
        '<span class="admin-header-draw-region">' . e(admin_draw_region_label($region)) . '</span>' .
        '<span class="admin-header-draw-issue">' . e($issueText) . '</span>' .
        '</div>' .
        '<div class="admin-header-draw-balls">' . $ballsHtml . '</div>' .
        '</div>';
}

function ensure_installed_or_redirect()
{
    $scriptName = isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '';
    if (strpos($scriptName, 'install.php') !== false) {
        return;
    }

    if (!app()->isInstalled()) {
        redirect(public_url('install.php'));
    }

    try {
        app()->db()->pdo();
    } catch (\Throwable $exception) {
        redirect(public_url('install.php?restart=1'));
    }
}

function run_housekeeping()
{
    if (!app()->isInstalled()) {
        return;
    }

    if (is_prefetch_request()) {
        return;
    }

    $now = time();
    $recycleKey = 'front_housekeeping_recycle_last_run';
    $recycleLastRun = (int) app()->cache()->get($recycleKey, 0);

    if ($recycleLastRun <= 0 || ($now - $recycleLastRun) >= 300) {
        try {
            app()->posts()->maintainRecycleRules();
            app()->cache()->put($recycleKey, $now);
        } catch (\Throwable $exception) {
            try {
                app()->logs()->system('posts', '回收站自动维护失败', 'warning', array(
                    'message' => $exception->getMessage(),
                ));
            } catch (\Throwable $loggingException) {
            }
        }
    }

    $forecastPrewarmKey = 'front_housekeeping_forecast_prewarm_last_run';
    $forecastPrewarmLastRun = (int) app()->cache()->get($forecastPrewarmKey, 0);
    if ($forecastPrewarmLastRun <= 0 || ($now - $forecastPrewarmLastRun) >= 120) {
        try {
            app()->cache()->put($forecastPrewarmKey, $now);
            app()->prediction()->prewarmFrontForecastPreviewCaches(300);
        } catch (\Throwable $exception) {
            try {
                app()->logs()->system('prediction', '预测缓存预热失败', 'warning', array(
                    'message' => $exception->getMessage(),
                ));
            } catch (\Throwable $loggingException) {
            }
        }
    }

    if (should_run_macau_live_polling_request()) {
        try {
            app()->prediction()->maybePollMacauLiveResult(false);
        } catch (\Throwable $exception) {
            try {
                app()->logs()->system('prediction', '澳门实时开奖同步失败', 'warning', array(
                    'message' => $exception->getMessage(),
                ));
            } catch (\Throwable $loggingException) {
            }
        }
    }

    if ((string) site_setting('cache.auto_clear', '0') !== '1') {
        return;
    }

    $hours = max(1, (int) site_setting('cache.auto_clear_hours', 24));
    $flag = app()->basePath('storage/cache/.auto_clear_at');
    $lastRun = is_file($flag) ? strtotime((string) file_get_contents($flag)) : false;

    if ($lastRun !== false && (time() - $lastRun) < ($hours * 3600)) {
        return;
    }

    app()->cache()->clearAll();
    $flagDirectory = dirname($flag);
    if (!is_dir($flagDirectory)) {
        mkdir($flagDirectory, 0755, true);
    }

    file_put_contents($flag, date('c'));
}

function track_page($routeName)
{
    if (!app()->isInstalled()) {
        return;
    }

    if (is_prefetch_request()) {
        return;
    }

    app()->logs()->pageView($routeName, isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '', Security::ipAddress(), Security::userAgent());
}

function should_run_macau_live_polling_request()
{
    if (is_prefetch_request()) {
        return false;
    }

    $time = date('H:i:s');
    if ($time < '21:32:00' || $time > '21:40:59') {
        return false;
    }

    $scriptName = str_replace('\\', '/', isset($_SERVER['SCRIPT_NAME']) ? (string) $_SERVER['SCRIPT_NAME'] : '');
    $script = basename($scriptName);
    $region = (string) input('region', '');

    if ($script === 'index.php') {
        return true;
    }

    if (in_array($script, array('forecast.php', 'service.php', 'member.php'), true)) {
        return $region !== 'hongkong';
    }

    return false;
}
