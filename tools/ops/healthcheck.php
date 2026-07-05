<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    echo 'Not Found';
    exit;
}

$targets = array(
    'root' => 'https://599549.com/',
    'macau_home' => 'https://599549.com/public/index.php',
    'hongkong_home' => 'https://599549.com/public/record.php',
    'admin' => 'https://599549.com/public/admin.php',
);

$failed = false;

foreach ($targets as $name => $url) {
    $context = stream_context_create(array(
        'http' => array(
            'method' => 'HEAD',
            'header' => "User-Agent: Mozilla/5.0\r\n",
            'ignore_errors' => true,
            'timeout' => 8,
        ),
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
        ),
    ));

    $result = @file_get_contents($url, false, $context);
    $headers = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : array();
    $statusLine = isset($headers[0]) ? $headers[0] : 'NO_RESPONSE';
    $ok = preg_match('/\s(200|302)\s/', $statusLine) === 1;
    if (!$ok) {
        $failed = true;
    }

    echo ($ok ? '[OK] ' : '[FAIL] ') . $name . ' - ' . $statusLine . PHP_EOL;
}

exit($failed ? 1 : 0);
