<?php
declare(strict_types=1);

$basePath = dirname(__DIR__);
$envPath = $basePath . '/.env';
$dotenv = array();

if (is_file($envPath) && is_readable($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($lines)) {
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if ($key === '') {
                continue;
            }

            if (
                strlen($value) >= 2
                && (($value[0] === '"' && substr($value, -1) === '"') || ($value[0] === "'" && substr($value, -1) === "'"))
            ) {
                $value = substr($value, 1, -1);
            }

            $dotenv[$key] = $value;
            if (!array_key_exists($key, $_ENV)) {
                $_ENV[$key] = $value;
            }
        }
    }
}

$env = static function ($key, $default = '') use ($dotenv) {
    if (array_key_exists($key, $dotenv)) {
        return $dotenv[$key];
    }

    $value = getenv($key);

    return $value === false ? $default : $value;
};

return array(
    'host' => (string) $env('DB_HOST', '127.0.0.1'),
    'port' => (int) $env('DB_PORT', '3306'),
    'database' => (string) $env('DB_DATABASE', ''),
    'username' => (string) $env('DB_USERNAME', ''),
    'password' => (string) $env('DB_PASSWORD', ''),
    'charset' => (string) $env('DB_CHARSET', 'utf8mb4'),
);
