<?php
declare(strict_types=1);

namespace App\Core;

class Session
{
    public static function start()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        static::configureRuntime();
        static::useStableSavePath();

        $secure = static::isSecureRequest();

        session_set_cookie_params(array(
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'secure' => $secure,
            'samesite' => 'Lax',
        ));

        session_name('lhlt_session');
        session_start();
    }

    protected static function configureRuntime()
    {
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string) (8 * 60 * 60));
        session_cache_limiter('');
    }

    protected static function useStableSavePath()
    {
        $projectSessionPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR
            . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
        if (static::useWritablePath($projectSessionPath)) {
            return;
        }

        $basePath = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        if ($basePath === '' || !is_dir($basePath) || !is_writable($basePath)) {
            return;
        }

        static::useWritablePath($basePath . DIRECTORY_SEPARATOR . 'lhlt_sessions');
    }

    protected static function isSecureRequest()
    {
        if (function_exists('front_security_is_https')) {
            return front_security_is_https();
        }

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

    protected static function useWritablePath($sessionPath)
    {
        $sessionPath = (string) $sessionPath;
        if (!is_dir($sessionPath) && !@mkdir($sessionPath, 0700, true)) {
            return false;
        }

        if (is_dir($sessionPath) && is_writable($sessionPath)) {
            session_save_path($sessionPath);

            return true;
        }

        return false;
    }

    public static function get($key, $default = null)
    {
        return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
    }

    public static function put($key, $value)
    {
        $_SESSION[$key] = $value;
    }

    public static function forget($key)
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate()
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
