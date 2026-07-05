<?php
declare(strict_types=1);

namespace App\Core;

class Auth
{
    const FRONT_MEMBER_COOKIE = 'front_member_auth';

    protected $app;
    protected $adminUserLoaded = false;
    protected $adminUserCache = null;
    protected $adminPermissionCache = array();

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function user()
    {
        $userId = Session::get('user_id');
        if (!$userId || !$this->app->isInstalled()) {
            return null;
        }

        return $this->app->users()->findById((int) $userId);
    }

    public function check()
    {
        return $this->user() !== null;
    }

    public function isAdmin()
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        return in_array($user['role_key'], array('super_admin', 'admin'), true);
    }

    public function login(array $user, $rememberMetadata = true)
    {
        Session::regenerate();
        Session::put('user_id', (int) $user['id']);
        Session::put('role_key', $user['role_key']);
        Session::put('logged_in_at', date('Y-m-d H:i:s'));
        Session::forget('customer_service_agent_id');
        Session::forget('customer_service_agent_entry');
        Session::forget('customer_service_agent_logged_in_at');
        Session::forget('customer_service_agent_serving');

        if ($rememberMetadata) {
            $this->app->users()->touchLoginMeta((int) $user['id']);
        }
    }

    public function issueFrontMemberCookie(array $user, $ttlSeconds = 86400)
    {
        $userId = (int) ($user['id'] ?? 0);
        $roleKey = (string) ($user['role_key'] ?? '');
        if ($userId <= 0 || !in_array($roleKey, $this->app->users()->memberRoleKeys(), true)) {
            return;
        }

        $expires = time() + max(300, (int) $ttlSeconds);
        $signature = $this->frontMemberSignature($userId, $expires);
        $this->setFrontMemberCookie($userId . '.' . $expires . '.' . $signature, $expires);
    }

    public function clearFrontMemberCookie()
    {
        $this->setFrontMemberCookie('', time() - 3600);
        unset($_COOKIE[self::FRONT_MEMBER_COOKIE]);
    }

    public function restoreUserFromFrontMemberCookie()
    {
        $rawCookie = isset($_COOKIE[self::FRONT_MEMBER_COOKIE]) ? (string) $_COOKIE[self::FRONT_MEMBER_COOKIE] : '';
        if ($rawCookie === '') {
            return null;
        }

        $parts = explode('.', $rawCookie);
        if (count($parts) !== 3) {
            $this->clearFrontMemberCookie();
            return null;
        }

        $userId = (int) $parts[0];
        $expires = (int) $parts[1];
        $signature = (string) $parts[2];
        if ($userId <= 0 || $expires <= time() || $signature === '') {
            $this->clearFrontMemberCookie();
            return null;
        }

        $expectedSignature = $this->frontMemberSignature($userId, $expires);
        if (!hash_equals($expectedSignature, $signature)) {
            $this->clearFrontMemberCookie();
            return null;
        }

        $user = $this->app->users()->findById($userId);
        if (
            !$user
            || (string) ($user['status'] ?? '') !== 'active'
            || !in_array((string) ($user['role_key'] ?? ''), $this->app->users()->memberRoleKeys(), true)
        ) {
            $this->clearFrontMemberCookie();
            return null;
        }

        Session::put('user_id', $userId);
        Session::put('role_key', (string) $user['role_key']);
        if (!Session::get('logged_in_at')) {
            Session::put('logged_in_at', date('Y-m-d H:i:s'));
        }
        Session::forget('customer_service_agent_id');
        Session::forget('customer_service_agent_entry');
        Session::forget('customer_service_agent_logged_in_at');
        Session::forget('customer_service_agent_serving');

        return $user;
    }

    public function logout()
    {
        $this->clearFrontMemberCookie();
        Session::forget('user_id');
        Session::forget('role_key');
        Session::forget('logged_in_at');
        Session::regenerate();
    }

    protected function frontMemberSignature($userId, $expires)
    {
        return hash_hmac('sha256', (int) $userId . '|' . (int) $expires, $this->frontMemberCookieSecret());
    }

    protected function frontMemberCookieSecret()
    {
        return hash('sha256', $this->app->basePath() . '|' . json_encode($this->app->config('database')) . '|front-member-auth');
    }

    protected function setFrontMemberCookie($value, $expires)
    {
        if (headers_sent()) {
            return;
        }

        setcookie(self::FRONT_MEMBER_COOKIE, (string) $value, array(
            'expires' => (int) $expires,
            'path' => '/',
            'secure' => $this->frontMemberCookieSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ));
    }

    protected function frontMemberCookieSecure()
    {
        if (function_exists('front_security_is_https') && front_security_is_https()) {
            return true;
        }
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }

        return (string) ($_SERVER['SERVER_PORT'] ?? '') === '443';
    }

    public function adminUser()
    {
        $adminId = Session::get('admin_user_id');
        if (!$adminId || !$this->app->isInstalled()) {
            $this->adminUserLoaded = true;
            $this->adminUserCache = null;

            return null;
        }

        if ($this->adminUserLoaded) {
            return $this->adminUserCache;
        }

        $timeoutMinutes = max(5, (int) $this->app->settings()->get('security.admin_session_minutes', 120));
        $lastActiveAt = (int) Session::get('admin_last_active_at', 0);

        if ($lastActiveAt > 0 && (time() - $lastActiveAt) > ($timeoutMinutes * 60)) {
            Session::put('admin_flash', array(
                'type' => 'error',
                'message' => '后台会话已超时，请重新登录。',
            ));
            $this->logoutAdmin();

            return null;
        }

        Session::put('admin_last_active_at', time());

        $this->adminUserLoaded = true;
        $this->adminUserCache = $this->app->admins()->findById((int) $adminId);

        return $this->adminUserCache;
    }

    public function checkAdminPortal()
    {
        return $this->adminUser() !== null;
    }

    public function loginAdmin(array $admin)
    {
        $roleCode = (string) ($admin['role_code'] ?? '');
        if ($roleCode === '') {
            throw new \RuntimeException('后台账号角色无效。');
        }

        if (in_array($roleCode, $this->app->users()->memberRoleKeys(), true)) {
            throw new \RuntimeException('会员角色禁止登录后台通道。');
        }

        Session::regenerate();
        Session::put('admin_user_id', (int) $admin['id']);
        Session::put('admin_role_code', $roleCode);
        Session::put('admin_logged_in_at', date('Y-m-d H:i:s'));
        Session::put('admin_last_active_at', time());
        $this->adminUserLoaded = true;
        $this->adminUserCache = $admin;
        $this->adminPermissionCache = array();
    }

    public function logoutAdmin()
    {
        Session::forget('admin_user_id');
        Session::forget('admin_role_code');
        Session::forget('admin_logged_in_at');
        Session::forget('admin_last_active_at');
        Session::regenerate();
        $this->adminUserLoaded = true;
        $this->adminUserCache = null;
        $this->adminPermissionCache = array();
    }

    public function adminCan($permissionCode)
    {
        $admin = $this->adminUser();
        if (!$admin) {
            return false;
        }

        if ((int) ($admin['is_super'] ?? 0) === 1 || (string) ($admin['role_code'] ?? '') === 'super_admin') {
            return true;
        }

        $permissionCode = (string) $permissionCode;
        $cacheKey = (int) $admin['id'] . ':' . $permissionCode;
        if (!array_key_exists($cacheKey, $this->adminPermissionCache)) {
            $permissions = $this->app->admins()->adminPermissionCodeMapFor($admin);
            $this->adminPermissionCache[$cacheKey] = isset($permissions[$permissionCode]);
        }

        return $this->adminPermissionCache[$cacheKey];
    }

    public function can($permissionKey)
    {
        $user = $this->user();
        if (!$user) {
            return false;
        }

        if ($user['role_key'] === 'super_admin') {
            return true;
        }

        return $this->app->users()->userHasPermission((int) $user['id'], $permissionKey);
    }

    public function requireLogin($redirectUrl = null)
    {
        if ($redirectUrl === null) {
            $redirectUrl = public_url('member.php');
        }

        if ($this->check()) {
            return;
        }

        redirect($redirectUrl);
    }

    public function requireAdmin($permissionKey = null, $redirectUrl = null)
    {
        if ($redirectUrl === null) {
            $redirectUrl = public_url('index.php');
        }

        if (!$this->isAdmin()) {
            redirect($redirectUrl);
        }

        if ($permissionKey !== null && !$this->can($permissionKey)) {
            abort(403, '您没有访问该模块的权限。');
        }
    }
    public function requireAdminPortal($permissionCode = null, $redirectUrl = null)
    {
        if ($redirectUrl === null) {
            $redirectUrl = public_url('admin.php');
        }

        if (!$this->checkAdminPortal()) {
            redirect($redirectUrl);
        }

        if ($permissionCode !== null && !$this->adminCan($permissionCode)) {
            abort(403, '您没有访问该后台模块的权限。');
        }
    }
}
