<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Security;

class LogService extends Service
{
    public function login($username, $roleKey, $status, $userId = null)
    {
        $this->db()->execute('INSERT INTO login_logs (user_id, username, role_key, login_ip, login_province, user_agent, login_status, created_at) VALUES (:user_id, :username, :role_key, :login_ip, :login_province, :user_agent, :login_status, :created_at)', array(
            'user_id' => $userId,
            'username' => $username,
            'role_key' => $roleKey,
            'login_ip' => Security::ipAddress(),
            'login_province' => Security::provinceFromIp(Security::ipAddress()),
            'user_agent' => Security::userAgent(),
            'login_status' => $status,
            'created_at' => $this->now(),
        ));
    }

    public function admin($module, $action, $description, $targetType = null, $targetId = null, $userId = null)
    {
        $this->db()->execute('INSERT INTO admin_logs (user_id, module_name, action_name, target_type, target_id, description, ip_address, created_at) VALUES (:user_id, :module_name, :action_name, :target_type, :target_id, :description, :ip_address, :created_at)', array(
            'user_id' => $userId,
            'module_name' => $module,
            'action_name' => $action,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'description' => $description,
            'ip_address' => Security::ipAddress(),
            'created_at' => $this->now(),
        ));
    }

    public function system($source, $message, $level = 'info', array $context = array())
    {
        $this->db()->execute('INSERT INTO system_logs (level_name, source_name, message, context_json, created_at) VALUES (:level_name, :source_name, :message, :context_json, :created_at)', array(
            'level_name' => $level,
            'source_name' => $source,
            'message' => $message,
            'context_json' => json_encode($context, JSON_UNESCAPED_UNICODE),
            'created_at' => $this->now(),
        ));
    }

    public function pageView($routeName, $pathName, $ipAddress, $userAgent)
    {
        if ((string) $routeName === 'post_detail') {
            return false;
        }

        $user = $this->app->currentUser();
        $userId = (int) ($user['id'] ?? 0);
        $now = $this->now();
        $viewedOn = date('Y-m-d');
        $queryString = (string) parse_url((string) $pathName, PHP_URL_QUERY);
        $queryParams = array();
        $postId = 0;
        $referer = isset($_SERVER['HTTP_REFERER']) ? substr((string) $_SERVER['HTTP_REFERER'], 0, 255) : null;

        if ($queryString !== '') {
            parse_str($queryString, $queryParams);
            $postId = (int) ($queryParams['id'] ?? 0);
        }

        $pathName = $this->limitFieldValue($pathName, 255);
        $userAgent = $this->limitFieldValue($userAgent, 255);
        if ($this->pageViewIsThrottled($routeName, $pathName, $ipAddress, $userAgent, $userId)) {
            return false;
        }

        $provinceName = Security::provinceFromIp($ipAddress);
        $cityName = Security::cityFromIp($ipAddress);

        $cleanupKey = 'page_views_cleanup_at';
        $lastCleanupAt = (int) $this->app->cache()->get($cleanupKey, 0);
        if ($lastCleanupAt <= 0 || (time() - $lastCleanupAt) >= 3600) {
            $this->db()->execute(
                'DELETE FROM page_views WHERE created_at < :retention_cutoff',
                array(
                    'retention_cutoff' => date('Y-m-d H:i:s', strtotime('-7 days')),
                )
            );
            $this->app->cache()->put($cleanupKey, time());
        }

        if (in_array((string) $routeName, array('front_macau', 'front_hongkong'), true)) {
            $existing = $this->db()->fetch(
                "SELECT id, referer
                 FROM page_views
                 WHERE route_name IN ('front_macau', 'front_hongkong')
                   AND viewed_on = :viewed_on
                   AND ip_address = :ip_address
                   AND COALESCE(user_agent, '') = :user_agent
                 LIMIT 1",
                array(
                    'viewed_on' => $viewedOn,
                    'ip_address' => $ipAddress,
                    'user_agent' => (string) $userAgent,
                )
            );

            if ($existing) {
                $this->db()->execute(
                    'UPDATE page_views
                     SET route_name = :route_name,
                         path_name = :path_name,
                         user_id = :user_id,
                         province_name = :province_name,
                         city_name = :city_name,
                         referer = :referer,
                         created_at = :created_at
                     WHERE id = :id',
                    array(
                        'route_name' => $routeName,
                        'path_name' => $pathName,
                        'user_id' => $user ? $user['id'] : null,
                        'province_name' => $provinceName,
                        'city_name' => $cityName,
                        'referer' => $referer !== null && $referer !== '' ? $referer : $existing['referer'],
                        'created_at' => $now,
                        'id' => (int) $existing['id'],
                    )
                );

                $this->rememberPageViewThrottle($routeName, $pathName, $ipAddress, $userAgent, $userId);
                return;
            }
        }

        $this->db()->execute('INSERT INTO page_views (route_name, path_name, user_id, ip_address, province_name, city_name, user_agent, referer, viewed_on, created_at) VALUES (:route_name, :path_name, :user_id, :ip_address, :province_name, :city_name, :user_agent, :referer, :viewed_on, :created_at)', array(
            'route_name' => $routeName,
            'path_name' => $pathName,
            'user_id' => $userId > 0 ? $userId : null,
            'ip_address' => $ipAddress,
            'province_name' => $provinceName,
            'city_name' => $cityName,
            'user_agent' => $userAgent,
            'referer' => $referer,
            'viewed_on' => $viewedOn,
            'created_at' => $now,
        ));

        $this->rememberPageViewThrottle($routeName, $pathName, $ipAddress, $userAgent, $userId);
        return true;
    }

    protected function pageViewIsThrottled($routeName, $pathName, $ipAddress, $userAgent, $userId)
    {
        $routeName = (string) $routeName;
        if (!in_array($routeName, $this->throttledPageViewRoutes(), true)) {
            return false;
        }

        $key = $this->pageViewThrottleKey($routeName, $pathName, $ipAddress, $userAgent, $userId);
        $cachedAt = (int) $this->app->cache()->get($key, 0, 45);

        return $cachedAt > 0;
    }

    protected function rememberPageViewThrottle($routeName, $pathName, $ipAddress, $userAgent, $userId)
    {
        $routeName = (string) $routeName;
        if (!in_array($routeName, $this->throttledPageViewRoutes(), true)) {
            return;
        }

        $this->cleanupPageViewThrottleCache();

        $this->app->cache()->put(
            $this->pageViewThrottleKey($routeName, $pathName, $ipAddress, $userAgent, $userId),
            time()
        );
    }

    protected function cleanupPageViewThrottleCache()
    {
        $now = time();
        $cleanupKey = 'page_view_throttle_cleanup_at';
        $lastCleanupAt = (int) $this->app->cache()->get($cleanupKey, 0);
        if ($lastCleanupAt > 0 && ($now - $lastCleanupAt) < 600) {
            return;
        }

        $this->app->cache()->put($cleanupKey, $now);

        $directory = $this->app->basePath('storage/cache');
        $files = glob($directory . DIRECTORY_SEPARATOR . 'page_view_throttle_*.cache.php');
        if ($files === false) {
            return;
        }

        $cutoff = $now - 3600;
        $deleted = 0;
        foreach ($files as $file) {
            if ($deleted >= 1000) {
                break;
            }

            if (is_file($file) && @filemtime($file) < $cutoff && @unlink($file)) {
                $deleted++;
            }
        }
    }

    protected function pageViewThrottleKey($routeName, $pathName, $ipAddress, $userAgent, $userId)
    {
        return 'page_view_throttle_' . md5(implode('|', array(
            (string) $routeName,
            (string) $pathName,
            (string) $ipAddress,
            (string) $userAgent,
            (string) (int) $userId,
        )));
    }

    protected function throttledPageViewRoutes()
    {
        return array(
            'front_macau',
            'front_hongkong',
            'front_history_macau',
            'front_history_hongkong',
            'front_forecast_macau',
            'front_forecast_hongkong',
            'front_service_macau',
            'front_service_hongkong',
            'front_member_macau',
            'front_member_hongkong',
        );
    }

    protected function limitFieldValue($value, $maxBytes)
    {
        $value = (string) $value;
        $maxBytes = max(1, (int) $maxBytes);

        if (strlen($value) <= $maxBytes) {
            return $value;
        }

        if (function_exists('mb_strcut')) {
            return mb_strcut($value, 0, $maxBytes, 'UTF-8');
        }

        return substr($value, 0, $maxBytes);
    }

    public function recentLoginLogs($limit = 20, $userId = null)
    {
        if ($userId !== null) {
            return $this->db()->fetchAll('SELECT * FROM login_logs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT ' . (int) $limit, array(
                'user_id' => $userId,
            ));
        }

        return $this->db()->fetchAll('SELECT * FROM login_logs ORDER BY created_at DESC LIMIT ' . (int) $limit);
    }

    public function adminLogs($limit = 50)
    {
        return $this->db()->fetchAll('SELECT admin_logs.*, users.username FROM admin_logs LEFT JOIN users ON users.id = admin_logs.user_id ORDER BY admin_logs.created_at DESC LIMIT ' . (int) $limit);
    }

    public function systemLogs($limit = 50)
    {
        return $this->db()->fetchAll('SELECT * FROM system_logs ORDER BY created_at DESC LIMIT ' . (int) $limit);
    }
}
