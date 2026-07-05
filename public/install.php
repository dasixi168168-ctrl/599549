<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap/app.php';

if (app()->isInstalled() && (string) getenv('APP_ALLOW_WEB_INSTALL') !== '1') {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

/** @var array<string, mixed>|null $databaseConfig */
$databaseConfig = app()->databaseConfig();
$isInstalled = app()->isInstalled();
$databaseConnectionAvailable = false;
$canRestartInstall = false;

if ($isInstalled && is_array($databaseConfig)) {
    try {
        app()->db()->pdo();
        $databaseConnectionAvailable = true;
    } catch (\Throwable $throwable) {
        $databaseConnectionAvailable = false;
    }

    if (!$databaseConnectionAvailable) {
        $canRestartInstall = true;
    } else {
        try {
            $canRestartInstall = app()->auth()->checkAdminPortal();
        } catch (\Throwable $throwable) {
            $canRestartInstall = false;
        }
    }
}

$isRestartMode = $isInstalled && $canRestartInstall;

$errorMessage = '';
$successMessage = '';
$resumeMessage = '';
$defaultSiteName = app()->config('app', 'site_name', '888888论坛');
$old = is_post() ? $_POST : array();
$formAction = public_url('install.php') . ($isRestartMode ? '?restart=1' : '');
$submitLabel = $isRestartMode ? '重新开始在线安装' : '开始安装系统';
$installFormDisabled = $isInstalled && !$canRestartInstall;

if (!$old && is_array($databaseConfig)) {
    $old = array(
        'db_host' => isset($databaseConfig['host']) ? (string) $databaseConfig['host'] : '127.0.0.1',
        'db_port' => isset($databaseConfig['port']) ? (string) $databaseConfig['port'] : '3306',
        'db_name' => isset($databaseConfig['database']) ? (string) $databaseConfig['database'] : 'liuhe_forum',
        'db_user' => isset($databaseConfig['username']) ? (string) $databaseConfig['username'] : 'root',
        'db_pass' => '',
        'site_name' => $defaultSiteName,
        'admin_username' => 'admin',
        'admin_password' => '',
    );

    if ($isInstalled) {
        try {
            $installedSiteName = site_setting('site.name', $defaultSiteName);
            if ((string) $installedSiteName !== '') {
                $old['site_name'] = (string) $installedSiteName;
            }
        } catch (\Throwable $throwable) {
        }

        try {
            $adminRow = db()->fetch(
                "SELECT users.username FROM users INNER JOIN roles ON roles.id = users.role_id WHERE roles.role_key IN ('super_admin', 'admin') ORDER BY users.id ASC LIMIT 1"
            );
            if (is_array($adminRow) && isset($adminRow['username']) && (string) $adminRow['username'] !== '') {
                $old['admin_username'] = (string) $adminRow['username'];
            }
        } catch (\Throwable $throwable) {
        }
    }
}

if ($isRestartMode) {
    $resumeMessage = '当前处于重启安装模式。打开安装页不会清空现有站点；只有重新提交安装表单后，系统才会按你填写的信息覆盖数据库配置、更新管理员账号并补齐初始化数据。';
} elseif (is_file(app()->basePath('config/database.php')) && !is_file(app()->basePath('storage/install.lock'))) {
    $resumeMessage = '检测到上次安装未完成，当前可以直接重新填写并提交，系统会覆盖数据库配置并继续安装。';
}

if ($installFormDisabled) {
    $errorMessage = '当前站点已完成安装，重复安装需要先登录后台管理员账号后再进入安装页。';
}

if (is_post()) {
    try {
        if ($installFormDisabled) {
            throw new RuntimeException('当前站点已完成安装，重复安装需要先登录后台管理员账号后再进入安装页。');
        }

        require_csrf('install');

        app()->install()->install(array(
            'db_host' => input('db_host', ''),
            'db_port' => input('db_port', '3306'),
            'db_name' => input('db_name', ''),
            'db_user' => input('db_user', ''),
            'db_pass' => input('db_pass', ''),
            'site_name' => input('site_name', ''),
            'admin_username' => input('admin_username', ''),
            'admin_password' => input('admin_password', ''),
        ));

        redirect(public_url('admin.php') . '?installed=1');
    } catch (\Exception $exception) {
        $errorMessage = $exception->getMessage();

    }
}

view('front/install', array(
    'pageTitle' => '在线安装 - 六合论坛系统',
    'errorMessage' => $errorMessage,
    'successMessage' => $successMessage,
    'resumeMessage' => $resumeMessage,
    'old' => $old,
    'formAction' => $formAction,
    'submitLabel' => $submitLabel,
    'isRestartMode' => $isRestartMode,
    'installFormDisabled' => $installFormDisabled,
), 'layouts/plain');
