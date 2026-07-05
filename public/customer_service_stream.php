<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/front_security.php';
front_security_apply(array(
    'rate_limit' => false,
    'output_buffer' => false,
    'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
));

require dirname(__DIR__) . '/bootstrap/app.php';

if (!app()->isInstalled()) {
    http_response_code(503);
    exit;
}

if (!\App\Core\Csrf::validate((string) input('_token', ''), 'api')) {
    http_response_code(419);
    exit;
}

$role = (string) input('role', 'member');
$sessionId = (int) input('session_id', 0);
$lastId = max(0, (int) input('last_id', 0));
$support = app()->support();
$startedAt = time();
$lastHeartbeatAt = 0;
$userId = 0;
$agent = null;

if (!in_array($role, array('member', 'agent'), true)) {
    http_response_code(400);
    exit;
}

if ($role === 'agent') {
    $agent = $support->currentAgent();
    if (!$agent || $sessionId <= 0) {
        http_response_code(401);
        exit;
    }
} else {
    $user = current_user();
    if (!$user) {
        http_response_code(401);
        exit;
    }
    $userId = (int) $user['id'];
}

if (function_exists('session_write_close')) {
    session_write_close();
}

@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
@set_time_limit(35);

while (ob_get_level() > 0) {
    @ob_end_flush();
}

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('X-Accel-Buffering: no');

$emit = static function ($event, array $payload) {
    echo 'event: ' . $event . "\n";
    echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";
    @flush();
};

while (!connection_aborted() && time() - $startedAt < 28) {
    try {
        if ($role === 'agent') {
            $latestId = $support->latestAgentVisibleMessageId($sessionId, $agent);
        } else {
            $latestId = $support->latestMemberVisibleMessageId($userId);
        }

        if ($latestId > $lastId) {
            $lastId = $latestId;
            $emit('customer-service-message', array('latest_id' => $latestId));
        } elseif (time() - $lastHeartbeatAt >= 10) {
            echo ": ping\n\n";
            @flush();
            $lastHeartbeatAt = time();
        }
    } catch (\Throwable $exception) {
        $emit('customer-service-close', array('reason' => 'error'));
        break;
    }

    usleep(700000);
}
