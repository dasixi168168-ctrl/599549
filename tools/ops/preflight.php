<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$root = dirname(dirname(__DIR__));
$failed = false;

function preflight_line($name, $ok, $detail)
{
    global $failed;
    if (!$ok) {
        $failed = true;
    }

    echo ($ok ? '[OK] ' : '[FAIL] ') . $name . ' - ' . $detail . PHP_EOL;
}

preflight_line('php_version', version_compare(PHP_VERSION, '5.5.0', '>='), PHP_VERSION);

$requiredFiles = array(
    'bootstrap/app.php',
    'config/database.php',
    'public/index.php',
    'public/admin.php',
    'bootstrap/front_security.php',
);

foreach ($requiredFiles as $file) {
    $path = $root . '/' . $file;
    preflight_line('file:' . $file, is_file($path), is_file($path) ? 'present' : 'missing');
}

$writableDirs = array(
    'storage',
    'storage/logs',
);

foreach ($writableDirs as $dir) {
    $path = $root . '/' . $dir;
    preflight_line('dir:' . $dir, is_dir($path) && is_writable($path), is_dir($path) ? (is_writable($path) ? 'writable' : 'not_writable') : 'missing');
}

$databaseConfigFile = $root . '/config/database.php';
if (is_file($databaseConfigFile)) {
    $config = require $databaseConfigFile;
    $hasConfig = is_array($config)
        && !empty($config['host'])
        && !empty($config['database'])
        && !empty($config['username']);
    preflight_line('database_config', $hasConfig, $hasConfig ? 'complete' : 'incomplete');

    if ($hasConfig && class_exists('PDO')) {
        $charset = isset($config['charset']) ? $config['charset'] : 'utf8mb4';
        $port = isset($config['port']) ? (int) $config['port'] : 3306;
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=%s', $config['host'], $port, $config['database'], $charset);

        try {
            $pdo = new PDO($dsn, $config['username'], isset($config['password']) ? $config['password'] : '', array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ));
            $row = $pdo->query('SELECT 1 AS ok')->fetch();
            preflight_line('database_connect', $row && (int) $row['ok'] === 1, 'checked');
        } catch (Exception $exception) {
            preflight_line('database_connect', false, 'failed');
        }
    } else {
        preflight_line('database_connect', false, class_exists('PDO') ? 'skipped' : 'pdo_missing');
    }
}

exit($failed ? 1 : 0);
