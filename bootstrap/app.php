<?php
declare(strict_types=1);

spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . '/app/' . $relative . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

require_once dirname(__DIR__) . '/app/Core/helpers.php';

$app = new App\Core\Application(dirname(__DIR__));
date_default_timezone_set((string) $app->config('app', 'timezone', 'Asia/Shanghai'));
