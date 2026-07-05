<?php

// ==================== 前端公共代码层防屏蔽增强（安全头统一版） ====================
// 仅增加防护，原有业务逻辑完全不变。

if (!function_exists('front_security_option')) {
    function front_security_option(array $options, $key, $default)
    {
        return array_key_exists($key, $options) ? $options[$key] : $default;
    }
}

if (!function_exists('front_security_header')) {
    function front_security_header($header)
    {
        if (!headers_sent()) {
            header($header);
        }
    }
}

if (!function_exists('front_security_is_https')) {
    function front_security_is_https()
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        if ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443') {
            return true;
        }

        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        if ($forwardedProto !== '' && strpos($forwardedProto, 'https') !== false) {
            return true;
        }

        $forwardedSsl = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_SSL'] ?? ''));
        if ($forwardedSsl === 'on') {
            return true;
        }

        $cfVisitor = strtolower((string) ($_SERVER['HTTP_CF_VISITOR'] ?? ''));
        if ($cfVisitor !== '' && strpos($cfVisitor, 'https') !== false) {
            return true;
        }

        return false;
    }
}

if (!function_exists('front_security_is_prefetch_request')) {
    function front_security_is_prefetch_request()
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
}

if (!function_exists('front_security_apply')) {
    function front_security_apply(array $options = array())
    {
        static $applied = false;
        if ($applied) {
            return;
        }
        $applied = true;

        $forceHttps = (bool) front_security_option($options, 'force_https', true);
        if ($forceHttps && !front_security_is_https()) {
            $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
            $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
            if ($host !== '' && !headers_sent()) {
                header('Location: https://' . $host . $requestUri, true, 301);
                exit;
            }
        }

        $requestMethod = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $isPrefetchRequest = front_security_is_prefetch_request();

        front_security_header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        front_security_header('X-Content-Type-Options: nosniff');
        front_security_header('X-Frame-Options: SAMEORIGIN');
        front_security_header('X-XSS-Protection: 1; mode=block');
        front_security_header('Referrer-Policy: no-referrer');
        $cacheControl = (string) front_security_option($options, 'cache_control', 'private, no-cache, must-revalidate, max-age=0');
        if (!in_array($requestMethod, array('GET', 'HEAD'), true)
            && !(bool) front_security_option($options, 'cache_non_get', false)
        ) {
            $cacheControl = 'no-store, no-cache, must-revalidate, max-age=0';
            $options['legacy_no_cache_headers'] = true;
        }
        if ($cacheControl !== '') {
            front_security_header('Cache-Control: ' . $cacheControl);
        }
        $legacyNoCacheHeaders = (bool) front_security_option(
            $options,
            'legacy_no_cache_headers',
            preg_match('/(?:no-cache|no-store|max-age=0)/i', $cacheControl) === 1
        );
        if ($legacyNoCacheHeaders) {
            front_security_header('Pragma: no-cache');
            front_security_header('Expires: 0');
        }
        front_security_header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self';");
        front_security_header('Cross-Origin-Embedder-Policy: require-corp');
        front_security_header('Cross-Origin-Opener-Policy: same-origin');
        front_security_header('Cross-Origin-Resource-Policy: same-origin');
        front_security_header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=(), interest-cohort=()');

        $botBlock = (bool) front_security_option($options, 'bot_block', true);
        if ($botBlock) {
            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
            if ($ua === ''
                || preg_match('/(bot|crawler|spider|scrapy|wget|python|go-http|java|httpclient|nikto|masscan|sqlmap|libwww|scan|probe)/i', $ua)
                || stripos($ua, 'bot') !== false
            ) {
                http_response_code(403);
                front_security_header('Content-Type: text/plain; charset=UTF-8');
                echo 'Access Denied';
                exit;
            }
        }

        $rateLimit = ($requestMethod === 'HEAD' || $isPrefetchRequest) ? false : (bool) front_security_option($options, 'rate_limit', true);
        $rateLimitSeconds = (int) front_security_option($options, 'rate_limit_seconds', 1);
        if ($rateLimit && $rateLimitSeconds > 0) {
            $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
            $scope = (string) front_security_option($options, 'rate_limit_scope', (string) ($_SERVER['SCRIPT_NAME'] ?? 'front'));
            $lockFile = sys_get_temp_dir() . '/req_limit_' . md5($ip . '|' . $scope) . '.lock';
            if (file_exists($lockFile) && (time() - filemtime($lockFile) < $rateLimitSeconds)) {
                http_response_code(429);
                front_security_header('Content-Type: text/plain; charset=UTF-8');
                echo 'Too Many Requests';
                exit;
            }
            @touch($lockFile);
        }

        $outputBuffer = (bool) front_security_option($options, 'output_buffer', true);
        if ($outputBuffer && ob_get_level() < 1) {
            ob_start();
        }
    }
}

if (!function_exists('front_public_page_cache_options')) {
    function front_public_page_cache_options($maxAge = 20)
    {
        $maxAge = max(5, min(60, (int) $maxAge));

        return array(
            'cache_control' => 'private, max-age=' . $maxAge . ', stale-while-revalidate=60',
            'legacy_no_cache_headers' => false,
        );
    }
}
