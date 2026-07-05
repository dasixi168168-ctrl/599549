<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Forbidden');
}

require dirname(__DIR__) . '/bootstrap/app.php';

if (!app()->isInstalled()) {
    exit(0);
}

$lockDir = app()->basePath('storage/locks');
if (!is_dir($lockDir) && !mkdir($lockDir, 0755, true) && !is_dir($lockDir)) {
    fwrite(STDERR, 'Cannot create lock directory.' . PHP_EOL);
    exit(1);
}

$lockHandle = fopen($lockDir . '/post_generator_schedule.lock', 'c');
if (!$lockHandle) {
    fwrite(STDERR, 'Cannot open lock file.' . PHP_EOL);
    exit(1);
}

if (!flock($lockHandle, LOCK_EX | LOCK_NB)) {
    fclose($lockHandle);
    exit(0);
}

try {
    app()->admins()->runManagedPostGeneratorSchedule();
} catch (\Throwable $exception) {
    try {
        app()->logs()->system('posts', '帖子资料定时更新失败', 'warning', array(
            'message' => $exception->getMessage(),
        ));
    } catch (\Throwable $loggingException) {
    }

    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    flock($lockHandle, LOCK_UN);
    fclose($lockHandle);
    exit(1);
}

flock($lockHandle, LOCK_UN);
fclose($lockHandle);

exit(0);
