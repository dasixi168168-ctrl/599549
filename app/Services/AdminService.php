<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Security;
use RuntimeException;

class AdminService extends Service
{
    const INSTALL_DEFAULT_VERSION = '20260526-local-default';
    const INSTALL_READY_SETTING_KEY = 'system.install_default_version';
    const ADMIN_MENU_ITEMS_CACHE_VERSION_KEY = 'admin_menu_items_version';
    const MANAGED_FORUM_OPTIONS_CACHE_VERSION_KEY = 'admin_managed_forum_options_version';
    const MANAGED_USER_SELECT_OPTIONS_CACHE_VERSION_KEY = 'admin_managed_user_select_options_version';
    const MANAGED_POST_SELECT_OPTIONS_CACHE_VERSION_KEY = 'admin_managed_post_select_options_version';
    const MANAGED_PASSWORD_RESET_REQUESTS_CACHE_VERSION_KEY = 'admin_managed_password_reset_requests_version';
    const FORECAST_PRICING_SETTINGS_CACHE_VERSION_KEY = 'admin_forecast_pricing_settings_version';
    const MANAGED_AUTHOR_ICON_PAIRS = array(
        array('🔥', '🔥'),
        array('🌟', '🌟'),
        array('🎯', '🎯'),
        array('🍀', '🍀'),
        array('🧧', '🧧'),
        array('⭐', '⭐'),
        array('🏆', '🏆'),
        array('✨', '✨'),
        array('🎉', '🎉'),
        array('💰', '💰'),
    );

    public function managedAuthorIconPair($authorNickname)
    {
        $authorIconPairs = self::MANAGED_AUTHOR_ICON_PAIRS;
        $authorIconSeed = abs(crc32('managed-author-icons|' . trim((string) $authorNickname)));

        return $authorIconPairs[$authorIconSeed % count($authorIconPairs)];
    }

    protected $tableExistsCache = array();
    protected $columnExistsCache = array();
    protected $managedForecastRecordStatsCache = array();
    protected $managedForecastDrawCache = array();
    protected $managedForecastDisplayLineCache = array();
    protected $managedExpertAdViewStateCache = array();
    protected $managedExpertAdViewStateDirty = array();
    protected $installReadyChecked = false;
    protected $adminMenuItemsCache = array();
    protected $adminPermissionCodesCache = array();
    protected $managedIssuePrefixSnapshotCache = array();
    protected $currentIssueSnapshotCache = array();
    protected $homeExpertPostsCache = array();
    protected $managedPostGeneratorConfigCache = array();
    protected $managedSectionListCache = array();
    protected $managedCategoryListCache = array();
    protected $managedSectionOptionsCache = array();
    protected $managedCategoryOptionsCache = array();
    protected $adminByIdRequestCache = array();
    protected $adminListRequestCache = null;
    protected $roleListRequestCache = null;
    protected $permissionListRequestCache = null;
    protected $dashboardStatsRequestCache = null;
    protected $recentLoginLogsRequestCache = array();
    protected $recentOperationLogsRequestCache = array();
    protected $adminLoginLogPageRequestCache = array();
    protected $adminOperationLogPageRequestCache = array();
    protected $adminSystemLogPageRequestCache = array();
    protected $adminExceptionLogPageRequestCache = array();
    protected $managedUserRolesRequestCache = null;
    protected $managedUserSelectBaseCache = array();
    protected $managedUserSelectExtraCache = array();
    protected $managedPasswordResetRequestsCache = array();
    protected $managedPostSelectBaseCache = array();
    protected $managedPostSelectExtraCache = array();
    protected $securitySettingsRequestCache = null;
    protected $forecastPricingSettingsRequestCache = null;
    protected $homepageSettingsRequestCache = null;
    protected $forecastPricingCatalogRequestCache = null;
    protected $defaultForecastPricingSettingsRequestCache = null;
    protected $defaultHomeSettingsRequestCache = null;
    protected $managedDrawMaterialEditorExpertVersionRequestCache = array();
    protected $managedDrawMaterialEditorRequestCache = array();
    protected $managedDrawDefaultComponentTemplatesRequestCache = null;
    protected $managedDrawComponentEditorRequestCache = array();

    public function ensureReady()
    {
        if ($this->installReadyChecked) {
            return;
        }

        $readyCacheKey = 'admin_install_ready_' . preg_replace('/[^A-Za-z0-9_\\-]/', '_', self::INSTALL_DEFAULT_VERSION);
        if ((string) $this->app->cache()->get($readyCacheKey, '', 30) === self::INSTALL_DEFAULT_VERSION) {
            $this->installReadyChecked = true;

            return;
        }

        $database = $this->db();

        try {
            $readyRow = $database->fetch(
                'SELECT setting_value FROM settings WHERE setting_key = :setting_key LIMIT 1',
                array('setting_key' => self::INSTALL_READY_SETTING_KEY)
            );
            if ($readyRow && (string) $readyRow['setting_value'] === self::INSTALL_DEFAULT_VERSION) {
                $this->app->cache()->put($readyCacheKey, self::INSTALL_DEFAULT_VERSION);
                $this->installReadyChecked = true;

                return;
            }
        } catch (\Throwable $exception) {
        }

        $this->runSchemaOn($database);
        $this->seedDefaults($database);
        $this->ensurePrimaryAdmin($database);

        if ($this->tableExists($database, 'settings')) {
            $database->execute(
                'INSERT INTO settings (setting_key, setting_group, setting_value, is_public, created_at, updated_at)
                 VALUES (:setting_key, :setting_group, :setting_value, :is_public, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    setting_group = VALUES(setting_group),
                    setting_value = VALUES(setting_value),
                    is_public = VALUES(is_public),
                    updated_at = VALUES(updated_at)',
                array(
                    'setting_key' => self::INSTALL_READY_SETTING_KEY,
                    'setting_group' => 'system',
                    'setting_value' => self::INSTALL_DEFAULT_VERSION,
                    'is_public' => 0,
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                )
            );
            $this->app->settings()->clearCache();
            $this->app->cache()->put($readyCacheKey, self::INSTALL_DEFAULT_VERSION);
            $this->installReadyChecked = true;
        }
    }

    public function installSeed(Database $database, array $payload)
    {
        $this->runSchemaOn($database);
        $this->seedDefaults($database);
        $admin = $this->ensurePrimaryAdmin($database, array(
            'username' => (string) $payload['admin_username'],
            'password_hash' => password_hash((string) $payload['admin_password'], PASSWORD_DEFAULT),
            'email' => isset($payload['admin_email']) ? (string) $payload['admin_email'] : '',
            'real_name' => '超级管理员',
        ));

        $this->recordInstall($database, array(
            'site_name' => isset($payload['site_name']) ? (string) $payload['site_name'] : '',
            'site_domain' => isset($payload['site_domain']) ? (string) $payload['site_domain'] : '',
            'db_host' => isset($payload['db_host']) ? (string) $payload['db_host'] : '',
            'db_port' => isset($payload['db_port']) ? (int) $payload['db_port'] : 3306,
            'db_name' => isset($payload['db_name']) ? (string) $payload['db_name'] : '',
            'db_prefix' => isset($payload['db_prefix']) ? (string) $payload['db_prefix'] : '',
            'status' => 'success',
        ));

        $this->recordInitGroup($database, 'admin_roles', 'admin_roles', 2);
        $this->recordInitGroup($database, 'admin_permissions', 'admin_permissions', count(array_merge($this->defaultPermissions(), $this->phaseOneExtraPermissions())));
        $this->recordInitGroup($database, 'admin_menus', 'admin_menus', count(array_merge($this->defaultMenus(), $this->phaseOneExtraMenus())));
        $database->execute(
            'INSERT INTO settings (setting_key, setting_group, setting_value, is_public, created_at, updated_at)
             VALUES (:setting_key, :setting_group, :setting_value, :is_public, :created_at, :updated_at)
             ON DUPLICATE KEY UPDATE
                setting_group = VALUES(setting_group),
                setting_value = VALUES(setting_value),
                is_public = VALUES(is_public),
                updated_at = VALUES(updated_at)',
            array(
                'setting_key' => self::INSTALL_READY_SETTING_KEY,
                'setting_group' => 'system',
                'setting_value' => self::INSTALL_DEFAULT_VERSION,
                'is_public' => 0,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            )
        );
        $this->app->settings()->clearCache();

        return $admin;
    }

    public function findById($adminId)
    {
        $adminId = (int) $adminId;
        if ($adminId <= 0) {
            return null;
        }

        if (array_key_exists($adminId, $this->adminByIdRequestCache)) {
            return $this->adminByIdRequestCache[$adminId];
        }

        if ($this->adminListRequestCache !== null) {
            foreach ($this->adminListRequestCache as $admin) {
                if ((int) ($admin['id'] ?? 0) === $adminId) {
                    $this->adminByIdRequestCache[$adminId] = $admin;

                    return $admin;
                }
            }
        }

        $admin = $this->findByIdFrom($this->db(), $adminId);
        $this->adminByIdRequestCache[$adminId] = is_array($admin) ? $admin : null;

        return $this->adminByIdRequestCache[$adminId];
    }

    public function findByUsername($username)
    {
        return $this->findByUsernameFrom($this->db(), $username);
    }

    public function roleById($roleId)
    {
        $roleId = (int) $roleId;
        if ($roleId <= 0) {
            return null;
        }

        foreach ($this->listRoles() as $role) {
            if ((int) ($role['id'] ?? 0) === $roleId) {
                return $role;
            }
        }

        return null;
    }

    protected function findByIdFrom(Database $database, $adminId)
    {
        return $database->fetch(
            'SELECT admin_users.*, admin_roles.name AS role_name, admin_roles.code AS role_code, admin_roles.data_scope
             FROM admin_users
             INNER JOIN admin_roles ON admin_roles.id = admin_users.role_id
             WHERE admin_users.id = :id AND admin_users.deleted_at IS NULL
             LIMIT 1',
            array('id' => $adminId)
        );
    }

    protected function findByUsernameFrom(Database $database, $username)
    {
        return $database->fetch(
            'SELECT admin_users.*, admin_roles.name AS role_name, admin_roles.code AS role_code, admin_roles.data_scope
             FROM admin_users
             INNER JOIN admin_roles ON admin_roles.id = admin_users.role_id
             WHERE admin_users.username = :username AND admin_users.deleted_at IS NULL
             LIMIT 1',
            array('username' => trim((string) $username))
        );
    }

    public function attemptLogin($username, $password)
    {
        $bucket = 'admin-login:' . Security::ipAddress() . ':' . trim((string) $username);
        $maxAttempts = (int) $this->app->settings()->get('security.max_login_attempts', 5);

        if (!Security::rateLimit($this->app, $bucket, $maxAttempts, 300)) {
            $this->recordLoginAttempt(null, (string) $username, 0, '登录失败次数过多');
            throw new RuntimeException('登录失败次数过多，请稍后再试。');
        }

        $admin = $this->findByUsername($username);
        if (!$admin || !password_verify((string) $password, (string) $admin['password_hash'])) {
            if ($admin) {
                $this->db()->execute(
                    'UPDATE admin_users SET login_fail_count = login_fail_count + 1, updated_at = :updated_at WHERE id = :id',
                    array('updated_at' => $this->now(), 'id' => $admin['id'])
                );
            }

            $this->recordLoginAttempt($admin ? (int) $admin['id'] : null, (string) $username, 0, '账号或密码错误');
            throw new RuntimeException('账号或密码错误。');
        }

        if ((int) $admin['status'] !== 1) {
            $this->recordLoginAttempt((int) $admin['id'], (string) $admin['username'], 0, '账号已停用');
            throw new RuntimeException('当前后台账号已停用。');
        }

        $this->app->auth()->loginAdmin($admin);
        Security::clearRateLimit($this->app, $bucket);
        $this->touchLoginMeta((int) $admin['id']);
        $this->recordLoginAttempt((int) $admin['id'], (string) $admin['username'], 1, '');

        return $this->findById((int) $admin['id']);
    }

    public function touchLoginMeta($adminId)
    {
        $ip = Security::ipAddress();

        $this->db()->execute(
            'UPDATE admin_users
             SET login_fail_count = 0,
                 last_login_at = :last_login_at,
                 last_login_ip = :last_login_ip,
                 last_login_area = :last_login_area,
                 session_token = :session_token,
                 updated_at = :updated_at
             WHERE id = :id',
            array(
                'last_login_at' => $this->now(),
                'last_login_ip' => $ip,
                'last_login_area' => Security::provinceFromIp($ip),
                'session_token' => session_id(),
                'updated_at' => $this->now(),
                'id' => $adminId,
            )
        );
    }

    public function adminHasPermission($adminId, $permissionCode)
    {
        $admin = $this->findById($adminId);
        if (!$admin) {
            return false;
        }

        if ((int) $admin['is_super'] === 1 || (string) $admin['role_code'] === 'super_admin') {
            return true;
        }

        $permissions = $this->adminPermissionCodeMapFor($admin);

        return isset($permissions[(string) $permissionCode]);
    }

    public function adminPermissionCodeMapFor(array $admin)
    {
        $adminId = (int) ($admin['id'] ?? 0);
        if ($adminId <= 0) {
            return array();
        }

        if ((int) ($admin['is_super'] ?? 0) === 1 || (string) ($admin['role_code'] ?? '') === 'super_admin') {
            return array('*' => true);
        }

        $roleId = (int) ($admin['role_id'] ?? 0);
        $roleCode = (string) ($admin['role_code'] ?? '');
        $cacheKey = $adminId . ':' . $roleId . ':' . $roleCode;
        if (isset($this->adminPermissionCodesCache[$cacheKey])) {
            return $this->adminPermissionCodesCache[$cacheKey];
        }

        if ($roleId <= 0) {
            $this->adminPermissionCodesCache[$cacheKey] = array();

            return array();
        }

        $cacheVersion = (string) $this->app->cache()->get(self::ADMIN_MENU_ITEMS_CACHE_VERSION_KEY, '1', 3600);
        $persistentCacheKey = 'admin_permission_codes_' . md5(implode('|', array(
            $cacheVersion,
            $adminId,
            $roleId,
            $roleCode,
        )));
        $cached = $this->app->cache()->get($persistentCacheKey, null, 15);
        if (is_array($cached)) {
            $this->adminPermissionCodesCache[$cacheKey] = $cached;

            return $cached;
        }

        $rows = $this->db()->fetchAll(
            'SELECT admin_permissions.code
             FROM admin_role_permissions
             INNER JOIN admin_permissions ON admin_permissions.id = admin_role_permissions.permission_id
             WHERE admin_role_permissions.role_id = :role_id
               AND admin_permissions.status = 1',
            array('role_id' => $roleId)
        );
        $permissions = array();
        foreach ($rows as $row) {
            $code = (string) ($row['code'] ?? '');
            if ($code !== '') {
                $permissions[$code] = true;
            }
        }

        $this->adminPermissionCodesCache[$cacheKey] = $permissions;
        $this->app->cache()->put($persistentCacheKey, $permissions);

        return $permissions;
    }

    public function menuItemsFor(array $admin)
    {
        $adminId = (int) ($admin['id'] ?? 0);
        $roleId = (int) ($admin['role_id'] ?? 0);
        $roleCode = (string) ($admin['role_code'] ?? '');
        $isSuper = (int) ($admin['is_super'] ?? 0) === 1 || $roleCode === 'super_admin';
        $cacheVersion = (string) $this->app->cache()->get(self::ADMIN_MENU_ITEMS_CACHE_VERSION_KEY, '1', 3600);
        $cacheKey = 'admin_menu_items_' . md5(implode('|', array(
            $cacheVersion,
            $adminId,
            $roleId,
            $roleCode,
            $isSuper ? '1' : '0',
        )));

        if (isset($this->adminMenuItemsCache[$cacheKey])) {
            return $this->adminMenuItemsCache[$cacheKey];
        }

        $cached = $this->app->cache()->get($cacheKey, null, 15);
        if (is_array($cached)) {
            $this->adminMenuItemsCache[$cacheKey] = $cached;

            return $cached;
        }

        if ((int) $admin['is_super'] === 1 || (string) $admin['role_code'] === 'super_admin') {
            $items = $this->db()->fetchAll(
                'SELECT * FROM admin_menus
                 WHERE status = 1 AND is_visible = 1
                 ORDER BY sort_order ASC, id ASC'
            );
            $this->adminMenuItemsCache[$cacheKey] = $items;
            $this->app->cache()->put($cacheKey, $items);

            return $items;
        }

        $items = $this->db()->fetchAll(
            'SELECT admin_menus.*
             FROM admin_role_menus
             INNER JOIN admin_menus ON admin_menus.id = admin_role_menus.menu_id
             INNER JOIN admin_users ON admin_users.role_id = admin_role_menus.role_id
             WHERE admin_users.id = :admin_id
               AND admin_menus.status = 1
               AND admin_menus.is_visible = 1
             ORDER BY admin_menus.sort_order ASC, admin_menus.id ASC',
            array('admin_id' => $admin['id'])
        );
        $this->adminMenuItemsCache[$cacheKey] = $items;
        $this->app->cache()->put($cacheKey, $items);

        return $items;
    }

    public function listAdmins()
    {
        if ($this->adminListRequestCache !== null) {
            return $this->adminListRequestCache;
        }

        $cacheVersion = (string) $this->app->cache()->get(self::ADMIN_MENU_ITEMS_CACHE_VERSION_KEY, '1', 3600);
        $cacheKey = 'admin_users_list_' . md5($cacheVersion);
        $cached = $this->app->cache()->get($cacheKey, null, 15);
        if (is_array($cached)) {
            $this->adminListRequestCache = $cached;
            foreach ($this->adminListRequestCache as $admin) {
                $adminId = (int) ($admin['id'] ?? 0);
                if ($adminId > 0) {
                    $this->adminByIdRequestCache[$adminId] = $admin;
                }
            }

            return $this->adminListRequestCache;
        }

        $this->adminListRequestCache = $this->db()->fetchAll(
            'SELECT admin_users.*, admin_roles.name AS role_name, admin_roles.code AS role_code, admin_roles.data_scope
             FROM admin_users
             INNER JOIN admin_roles ON admin_roles.id = admin_users.role_id
             WHERE admin_users.deleted_at IS NULL
             ORDER BY admin_users.is_super DESC, admin_users.id ASC'
        );
        foreach ($this->adminListRequestCache as $admin) {
            $adminId = (int) ($admin['id'] ?? 0);
            if ($adminId > 0) {
                $this->adminByIdRequestCache[$adminId] = $admin;
            }
        }
        $this->app->cache()->put($cacheKey, $this->adminListRequestCache);

        return $this->adminListRequestCache;
    }

    public function listRoles()
    {
        if ($this->roleListRequestCache !== null) {
            return $this->roleListRequestCache;
        }

        $cacheVersion = (string) $this->app->cache()->get(self::ADMIN_MENU_ITEMS_CACHE_VERSION_KEY, '1', 3600);
        $cacheKey = 'admin_roles_list_' . md5($cacheVersion);
        $cached = $this->app->cache()->get($cacheKey, null, 15);
        if (is_array($cached)) {
            $this->roleListRequestCache = $cached;

            return $this->roleListRequestCache;
        }

        $this->roleListRequestCache = $this->db()->fetchAll('SELECT * FROM admin_roles ORDER BY sort_order ASC, id ASC');
        $this->app->cache()->put($cacheKey, $this->roleListRequestCache);

        return $this->roleListRequestCache;
    }

    public function listPermissions()
    {
        if ($this->permissionListRequestCache !== null) {
            return $this->permissionListRequestCache;
        }

        $cacheVersion = (string) $this->app->cache()->get(self::ADMIN_MENU_ITEMS_CACHE_VERSION_KEY, '1', 3600);
        $cacheKey = 'admin_permissions_list_' . md5($cacheVersion);
        $cached = $this->app->cache()->get($cacheKey, null, 15);
        if (is_array($cached)) {
            $this->permissionListRequestCache = $cached;

            return $this->permissionListRequestCache;
        }

        $this->permissionListRequestCache = $this->db()->fetchAll('SELECT * FROM admin_permissions WHERE status = 1 ORDER BY module ASC, sort_order ASC, id ASC');
        $this->app->cache()->put($cacheKey, $this->permissionListRequestCache);

        return $this->permissionListRequestCache;
    }

    public function saveAdmin(array $payload, array $actor)
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $username = trim((string) ($payload['username'] ?? ''));
        $password = (string) ($payload['password'] ?? '');
        $realName = trim((string) ($payload['real_name'] ?? ''));
        $nickname = trim((string) ($payload['nickname'] ?? ''));
        $mobile = trim((string) ($payload['mobile'] ?? ''));
        $email = trim((string) ($payload['email'] ?? ''));
        $roleId = (int) ($payload['role_id'] ?? 0);
        $status = isset($payload['status']) && (string) $payload['status'] === '0' ? 0 : 1;
        $remark = trim((string) ($payload['remark'] ?? ''));

        if ($username === '') {
            throw new RuntimeException('管理员账号不能为空。');
        }

        if ($roleId <= 0) {
            throw new RuntimeException('请选择管理员角色。');
        }

        $existing = $this->findByUsername($username);
        if ($existing && (int) $existing['id'] !== $id) {
            throw new RuntimeException('该管理员账号已存在。');
        }

        if ($id === 0 && strlen($password) < 6) {
            throw new RuntimeException('新建管理员时密码不能少于 6 位。');
        }

        if ($id > 0) {
            $query = 'UPDATE admin_users
                      SET role_id = :role_id,
                          username = :username,
                          real_name = :real_name,
                          nickname = :nickname,
                          mobile = :mobile,
                          email = :email,
                          status = :status,
                          remark = :remark,
                          updated_at = :updated_at';
            $params = array(
                'role_id' => $roleId,
                'username' => $username,
                'real_name' => $realName,
                'nickname' => $nickname,
                'mobile' => $mobile,
                'email' => $email,
                'status' => $status,
                'remark' => $remark,
                'updated_at' => $this->now(),
                'id' => $id,
            );

            if ($password !== '') {
                $query .= ', password_hash = :password_hash';
                $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $query .= ' WHERE id = :id';
            $this->db()->execute($query, $params);
            $adminId = $id;
            $action = 'update';
        } else {
            $adminId = $this->db()->insertGetId(
                'INSERT INTO admin_users (role_id, username, password_hash, real_name, nickname, mobile, email, status, is_super, remark, created_at, updated_at)
                 VALUES (:role_id, :username, :password_hash, :real_name, :nickname, :mobile, :email, :status, :is_super, :remark, :created_at, :updated_at)',
                array(
                    'role_id' => $roleId,
                    'username' => $username,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'real_name' => $realName,
                    'nickname' => $nickname,
                    'mobile' => $mobile,
                    'email' => $email,
                    'status' => $status,
                    'is_super' => 0,
                    'remark' => $remark,
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                )
            );
            $action = 'create';
        }

        $this->recordOperation((int) $actor['id'], 'admins', $action, 'admin_user', $adminId, '保存管理员：' . $username);
        $this->flushAdminMenuItemsCache();

        return $this->findById($adminId);
    }

    public function toggleAdminStatus($adminId, array $actor)
    {
        $admin = $this->findById($adminId);
        if (!$admin) {
            throw new RuntimeException('管理员不存在。');
        }

        if ((int) $admin['is_super'] === 1 && (int) $actor['id'] === (int) $admin['id']) {
            throw new RuntimeException('不能停用当前登录的超级管理员。');
        }

        $nextStatus = (int) $admin['status'] === 1 ? 0 : 1;
        $this->db()->execute(
            'UPDATE admin_users SET status = :status, updated_at = :updated_at WHERE id = :id',
            array(
                'status' => $nextStatus,
                'updated_at' => $this->now(),
                'id' => $adminId,
            )
        );

        $this->recordOperation((int) $actor['id'], 'admins', $nextStatus === 1 ? 'enable' : 'disable', 'admin_user', $adminId, '切换管理员状态：' . $admin['username']);
        $this->flushAdminMenuItemsCache();

        return $this->findById($adminId);
    }

    public function deleteAdmin($adminId, array $actor)
    {
        $admin = $this->findById($adminId);
        if (!$admin) {
            throw new RuntimeException('管理员不存在。');
        }

        if ((int) $admin['id'] === (int) ($actor['id'] ?? 0)) {
            throw new RuntimeException('不能删除当前登录管理员。');
        }

        if ((int) ($admin['is_super'] ?? 0) === 1) {
            throw new RuntimeException('不能删除超级管理员。');
        }

        $this->db()->execute(
            'UPDATE admin_users SET deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id AND deleted_at IS NULL',
            array(
                'deleted_at' => $this->now(),
                'updated_at' => $this->now(),
                'id' => (int) $admin['id'],
            )
        );

        $this->recordOperation((int) $actor['id'], 'admins', 'delete', 'admin_user', (int) $admin['id'], '删除管理员：' . $admin['username']);
        $this->flushAdminMenuItemsCache();

        return true;
    }

    public function saveRole(array $payload, array $actor)
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $name = trim((string) ($payload['name'] ?? ''));
        $code = trim((string) ($payload['code'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $dataScope = trim((string) ($payload['data_scope'] ?? 'all'));
        $status = isset($payload['status']) && (string) $payload['status'] === '0' ? 0 : 1;
        $sortOrder = (int) ($payload['sort_order'] ?? 0);

        if ($name === '' || $code === '') {
            throw new RuntimeException('角色名称和角色编码不能为空。');
        }

        if (in_array($code, $this->app->users()->memberRoleKeys(), true)) {
            throw new RuntimeException('后台角色编码不能使用会员角色编码。');
        }

        $existing = $this->db()->fetch('SELECT * FROM admin_roles WHERE code = :code LIMIT 1', array('code' => $code));
        if ($existing && (int) $existing['id'] !== $id) {
            throw new RuntimeException('角色编码已存在。');
        }

        if ($id > 0) {
            $this->db()->execute(
                'UPDATE admin_roles
                 SET name = :name, code = :code, description = :description, data_scope = :data_scope, status = :status, sort_order = :sort_order, updated_at = :updated_at
                 WHERE id = :id',
                array(
                    'name' => $name,
                    'code' => $code,
                    'description' => $description,
                    'data_scope' => $dataScope,
                    'status' => $status,
                    'sort_order' => $sortOrder,
                    'updated_at' => $this->now(),
                    'id' => $id,
                )
            );
            $roleId = $id;
            $action = 'update';
        } else {
            $roleId = $this->db()->insertGetId(
                'INSERT INTO admin_roles (name, code, description, data_scope, status, sort_order, created_at, updated_at)
                 VALUES (:name, :code, :description, :data_scope, :status, :sort_order, :created_at, :updated_at)',
                array(
                    'name' => $name,
                    'code' => $code,
                    'description' => $description,
                    'data_scope' => $dataScope,
                    'status' => $status,
                    'sort_order' => $sortOrder,
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                )
            );
            $action = 'create';
        }

        $this->recordOperation((int) $actor['id'], 'roles', $action, 'admin_role', $roleId, '保存角色：' . $name);
        $this->flushAdminMenuItemsCache();

        return $this->db()->fetch('SELECT * FROM admin_roles WHERE id = :id LIMIT 1', array('id' => $roleId));
    }

    public function saveSystemSettings(array $payload, array $actor)
    {
        $siteItems = array(
            'site.name' => trim((string) ($payload['site_name'] ?? '')),
            'site.title' => trim((string) ($payload['site_title'] ?? '')),
            'browser.title' => trim((string) ($payload['site_title'] ?? '')),
            'browser.region_title_macau' => trim((string) ($payload['browser_region_title_macau'] ?? '')),
            'browser.region_title_hongkong' => trim((string) ($payload['browser_region_title_hongkong'] ?? '')),
        );
        $adminItems = array(
            'admin.browser_title' => trim((string) ($payload['admin_browser_title'] ?? '')),
            'admin.management_name' => trim((string) ($payload['admin_management_name'] ?? '')),
        );

        if ($siteItems['site.name'] === '' || $siteItems['browser.title'] === '') {
            throw new RuntimeException('站点名称和浏览器标题信息不能为空。');
        }

        if ($siteItems['browser.region_title_macau'] === '' || $siteItems['browser.region_title_hongkong'] === '') {
            throw new RuntimeException('澳门浏览器后缀和香港浏览器后缀不能为空。');
        }

        if ($adminItems['admin.browser_title'] === '' || $adminItems['admin.management_name'] === '') {
            throw new RuntimeException('后台浏览器标题和后台管理名称不能为空。');
        }

        $this->app->settings()->setMany('site', array(
            'site.name' => $siteItems['site.name'],
            'site.title' => $siteItems['site.title'],
            'browser.title' => $siteItems['browser.title'],
            'browser.region_title_macau' => $siteItems['browser.region_title_macau'],
            'browser.region_title_hongkong' => $siteItems['browser.region_title_hongkong'],
        ), array('site.name', 'site.title', 'browser.title', 'browser.region_title_macau', 'browser.region_title_hongkong'));
        $this->app->settings()->setMany('admin', $adminItems);
        $this->recordOperation((int) $actor['id'], 'settings', 'save', 'settings', 0, '保存后台基础设置');

        return array_merge($siteItems, $adminItems);
    }

    public function securitySettings()
    {
        if ($this->securitySettingsRequestCache !== null) {
            return $this->securitySettingsRequestCache;
        }

        $this->securitySettingsRequestCache = array(
            'max_login_attempts' => (string) $this->app->settings()->get('security.max_login_attempts', '5'),
            'admin_session_minutes' => (string) $this->app->settings()->get('security.admin_session_minutes', '120'),
        );

        return $this->securitySettingsRequestCache;
    }

    public function saveSecuritySettings(array $payload, array $actor)
    {
        $securityItems = array(
            'security.max_login_attempts' => (string) max(1, (int) ($payload['max_login_attempts'] ?? 5)),
            'security.admin_session_minutes' => (string) max(5, (int) ($payload['admin_session_minutes'] ?? 120)),
        );

        $this->app->settings()->setMany('security', $securityItems);
        $this->securitySettingsRequestCache = null;
        $this->recordOperation((int) $actor['id'], 'security', 'save', 'settings', 0, '保存后台安全策略');

        return $this->securitySettings();
    }

    public function forecastPricingCatalog()
    {
        if ($this->forecastPricingCatalogRequestCache !== null) {
            return $this->forecastPricingCatalogRequestCache;
        }

        $numberOptions = array();
        foreach (array(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 12, 14, 16, 18, 20, 24, 30) as $count) {
            $numberOptions[] = array(
                'value' => 'number_' . $count,
                'label' => $this->forecastNumberTypeLabelForAdmin($count),
            );
        }

        $pingteOptions = array();
        foreach (array(1, 2, 3, 4, 6, 8) as $groupCount) {
            $pingteOptions[] = array('value' => 'pt_2_2_group_' . $groupCount, 'label' => $groupCount . '组2中2');
        }
        foreach (array(5, 6, 7, 8, 9) as $comboCount) {
            $pingteOptions[] = array('value' => 'pt_2_2_combo_' . $comboCount, 'label' => $comboCount . '码复式');
        }
        foreach (array(1, 2, 3, 4, 6, 8) as $groupCount) {
            $pingteOptions[] = array('value' => 'pt_3_3_group_' . $groupCount, 'label' => $groupCount . '组3中3');
        }
        $this->forecastPricingCatalogRequestCache = array(
            'zodiac' => array(
                'label' => '生肖类型',
                'field' => 'zodiac_type',
                'placeholder' => '生肖类型',
                'note' => '前台按选择项输出对应数量生肖，后端 value 保持 zodiac_N。',
                'options' => array(
                    array('value' => 'zodiac_1', 'label' => '一肖'),
                    array('value' => 'zodiac_2', 'label' => '二肖'),
                    array('value' => 'zodiac_3', 'label' => '三肖'),
                    array('value' => 'zodiac_4', 'label' => '四肖'),
                    array('value' => 'zodiac_5', 'label' => '五肖'),
                    array('value' => 'zodiac_6', 'label' => '六肖'),
                    array('value' => 'zodiac_7', 'label' => '七肖'),
                    array('value' => 'zodiac_8', 'label' => '八肖'),
                    array('value' => 'zodiac_9', 'label' => '九肖'),
                ),
            ),
            'number' => array(
                'label' => '号码类型',
                'field' => 'number_type',
                'placeholder' => '号码类型',
                'note' => '前台按选择项输出对应数量号码，号码展示固定为 01-49。',
                'options' => $numberOptions,
            ),
            'pingte' => array(
                'label' => '平码类型',
                'field' => 'pingte_type',
                'placeholder' => '平码类型',
                'note' => '包含 N组2中2、N组3中3 与 N码复式，value 保持 pt_* 结构。',
                'options' => $pingteOptions,
            ),
            'other' => array(
                'label' => '其他类型',
                'field' => 'other_type',
                'placeholder' => '其他类型',
                'note' => '包含单双、波色、大小、头数、尾数和平特一肖到平特五肖。',
                'options' => array(
                    array('value' => 'odd_even', 'label' => '单双'),
                    array('value' => 'wave', 'label' => '波色'),
                    array('value' => 'big_small', 'label' => '大小'),
                    array('value' => 'head', 'label' => '头数'),
                    array('value' => 'tail', 'label' => '尾数'),
                    array('value' => 'pt_zodiac_1', 'label' => '平特一肖'),
                    array('value' => 'pt_zodiac_2', 'label' => '平特二肖'),
                    array('value' => 'pt_zodiac_3', 'label' => '平特三肖'),
                    array('value' => 'pt_zodiac_4', 'label' => '平特四肖'),
                    array('value' => 'pt_zodiac_5', 'label' => '平特五肖'),
                ),
            ),
        );

        return $this->forecastPricingCatalogRequestCache;
    }

    public function forecastPricingSettings()
    {
        if ($this->forecastPricingSettingsRequestCache !== null) {
            return $this->forecastPricingSettingsRequestCache;
        }

        $cacheVersion = (string) $this->app->cache()->get(self::FORECAST_PRICING_SETTINGS_CACHE_VERSION_KEY, '1', 3600);
        $cacheKey = 'admin_forecast_pricing_settings_' . md5($cacheVersion);
        $cached = $this->app->cache()->get($cacheKey, null, 15);
        if (is_array($cached)) {
            $this->forecastPricingSettingsRequestCache = $cached;

            return $this->forecastPricingSettingsRequestCache;
        }

        $defaults = $this->defaultForecastPricingSettings();
        $rawConfig = trim((string) $this->app->settings()->get('forecast.pricing_config', ''));
        $savedConfig = $rawConfig !== '' ? json_decode($rawConfig, true) : array();
        if (!is_array($savedConfig)) {
            $this->forecastPricingSettingsRequestCache = $defaults;
            $this->app->cache()->put($cacheKey, $this->forecastPricingSettingsRequestCache);

            return $this->forecastPricingSettingsRequestCache;
        }

        foreach (array('1', '2', '3', '4') as $countKey) {
            if (isset($savedConfig['discounts'][$countKey])) {
                $defaults['discounts'][$countKey] = max(1, min(100, (int) $savedConfig['discounts'][$countKey]));
            }
        }
        $defaults['participation_increment'] = max(
            0,
            min(
                9999,
                (int) ($savedConfig['participation_increment'] ?? $this->app->settings()->get('forecast.participation_increment', '8'))
            )
        );
        $legacyAnalysisPeriod = (int) ($savedConfig['analysis_period'] ?? $this->app->settings()->get('forecast.analysis_period', '20'));
        $defaults['analysis_period_min'] = max(
            1,
            min(
                10000,
                (int) ($savedConfig['analysis_period_min'] ?? $this->app->settings()->get('forecast.analysis_period_min', (string) $legacyAnalysisPeriod))
            )
        );
        $defaults['analysis_period_max'] = max(
            1,
            min(
                10000,
                (int) ($savedConfig['analysis_period_max'] ?? $this->app->settings()->get('forecast.analysis_period_max', (string) $legacyAnalysisPeriod))
            )
        );
        if ($defaults['analysis_period_min'] > $defaults['analysis_period_max']) {
            $analysisPeriodSwap = $defaults['analysis_period_min'];
            $defaults['analysis_period_min'] = $defaults['analysis_period_max'];
            $defaults['analysis_period_max'] = $analysisPeriodSwap;
        }
        $defaults['analysis_period'] = $defaults['analysis_period_max'];
        $defaults['member_daily_limit'] = max(
            1,
            min(
                9999,
                (int) ($savedConfig['member_daily_limit'] ?? $this->app->settings()->get('forecast.member_daily_limit', (string) $defaults['member_daily_limit']))
            )
        );
        $defaults['api_urls'] = $this->forecastApiUrlSettings($savedConfig);

        foreach ($defaults['groups'] as $typeKey => $group) {
            if (empty($savedConfig['groups'][$typeKey]['options']) || !is_array($savedConfig['groups'][$typeKey]['options'])) {
                continue;
            }
            $savedOptions = array();
            foreach ($savedConfig['groups'][$typeKey]['options'] as $savedOptionKey => $savedOptionValue) {
                if (!is_array($savedOptionValue)) {
                    continue;
                }
                $savedOptionValueKey = (string) ($savedOptionValue['value'] ?? $savedOptionKey);
                if ($savedOptionValueKey !== '') {
                    $savedOptions[$savedOptionValueKey] = $savedOptionValue;
                }
            }
            foreach ($group['options'] as $optionIndex => $option) {
                $value = (string) ($option['value'] ?? '');
                if ($value === '' || !isset($savedOptions[$value]) || !is_array($savedOptions[$value])) {
                    continue;
                }
                $savedOption = $savedOptions[$value];
                $label = trim((string) ($savedOption['label'] ?? ''));
                if ($label !== '') {
                    $defaults['groups'][$typeKey]['options'][$optionIndex]['label'] = $this->truncateForecastLabel($label);
                }
                $defaults['groups'][$typeKey]['options'][$optionIndex]['price'] = $this->normalizeForecastPrice($savedOption['price'] ?? $option['price']);
                $defaults['groups'][$typeKey]['options'][$optionIndex]['enabled'] = empty($savedOption['enabled']) ? 0 : 1;
            }
        }

        $this->forecastPricingSettingsRequestCache = $defaults;
        $this->app->cache()->put($cacheKey, $this->forecastPricingSettingsRequestCache);

        return $this->forecastPricingSettingsRequestCache;
    }

    public function saveForecastPricingSettings(array $payload, array $actor)
    {
        $config = $this->defaultForecastPricingSettings();
        foreach (array('1', '2', '3', '4') as $countKey) {
            $config['discounts'][$countKey] = max(1, min(100, (int) ($payload['discounts'][$countKey] ?? $config['discounts'][$countKey])));
        }
        $config['participation_increment'] = max(0, min(9999, (int) ($payload['participation_increment'] ?? $config['participation_increment'])));
        $analysisPeriodMin = max(1, min(10000, (int) ($payload['analysis_period_min'] ?? ($payload['analysis_period'] ?? $config['analysis_period_min']))));
        $analysisPeriodMax = max(1, min(10000, (int) ($payload['analysis_period_max'] ?? ($payload['analysis_period'] ?? $config['analysis_period_max']))));
        if ($analysisPeriodMin > $analysisPeriodMax) {
            $analysisPeriodSwap = $analysisPeriodMin;
            $analysisPeriodMin = $analysisPeriodMax;
            $analysisPeriodMax = $analysisPeriodSwap;
        }
        $config['analysis_period_min'] = $analysisPeriodMin;
        $config['analysis_period_max'] = $analysisPeriodMax;
        $config['analysis_period'] = $analysisPeriodMax;
        $config['member_daily_limit'] = max(1, min(9999, (int) ($payload['member_daily_limit'] ?? $config['member_daily_limit'])));
        $config['api_urls'] = $this->normalizeForecastApiUrlsForSave((array) ($payload['api_urls'] ?? array()));

        foreach ($config['groups'] as $typeKey => $group) {
            foreach ($group['options'] as $optionIndex => $option) {
                $value = (string) ($option['value'] ?? '');
                $label = trim((string) ($payload['labels'][$typeKey][$value] ?? $option['label']));
                $config['groups'][$typeKey]['options'][$optionIndex]['label'] = $label !== '' ? $this->truncateForecastLabel($label) : (string) $option['label'];
                $config['groups'][$typeKey]['options'][$optionIndex]['price'] = $this->normalizeForecastPrice($payload['prices'][$typeKey][$value] ?? $option['price']);
                $config['groups'][$typeKey]['options'][$optionIndex]['enabled'] = isset($payload['enabled'][$typeKey][$value]) ? 1 : 0;
            }
        }

        $this->app->settings()->setMany('forecast', array(
            'forecast.pricing_config' => json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'forecast.participation_increment' => (string) $config['participation_increment'],
            'forecast.analysis_period_min' => (string) $config['analysis_period_min'],
            'forecast.analysis_period_max' => (string) $config['analysis_period_max'],
            'forecast.analysis_period' => (string) $config['analysis_period'],
            'forecast.member_daily_limit' => (string) $config['member_daily_limit'],
            'forecast.macau_live_api_url' => (string) $config['api_urls']['macau_live_api_url'],
            'forecast.macau_history_api_url' => (string) $config['api_urls']['macau_history_api_url'],
            'forecast.hongkong_live_api_url' => (string) $config['api_urls']['hongkong_live_api_url'],
            'forecast.hongkong_history_api_url' => (string) $config['api_urls']['hongkong_history_api_url'],
        ), array(
            'forecast.pricing_config',
            'forecast.participation_increment',
            'forecast.analysis_period_min',
            'forecast.analysis_period_max',
            'forecast.analysis_period',
            'forecast.member_daily_limit',
        ));
        $this->clearForecastPricingSettingsCache();
        $this->recordOperation((int) $actor['id'], 'home', 'save_forecast_pricing', 'settings', 0, '保存AI预测选项价格');

        return $this->forecastPricingSettings();
    }

    protected function clearForecastPricingSettingsCache()
    {
        $this->forecastPricingSettingsRequestCache = null;
        $this->app->cache()->put(self::FORECAST_PRICING_SETTINGS_CACHE_VERSION_KEY, (string) microtime(true));
    }

    public function forecastFilterOptions()
    {
        $config = $this->forecastPricingSettings();
        $groups = array();

        foreach ($config['groups'] as $typeKey => $group) {
            $options = array();
            foreach ((array) ($group['options'] ?? array()) as $option) {
                if (empty($option['enabled'])) {
                    continue;
                }
                $options[] = array(
                    'value' => (string) ($option['value'] ?? ''),
                    'label' => (string) ($option['label'] ?? ''),
                    'price' => $this->normalizeForecastPrice($option['price'] ?? 0),
                );
            }
            $groups[$typeKey] = array(
                'placeholder' => (string) ($group['placeholder'] ?? $group['label'] ?? ''),
                'label' => (string) ($group['label'] ?? ''),
                'field' => (string) ($group['field'] ?? ''),
                'options' => $options,
            );
        }

        return $groups;
    }

    public function forecastPricingForFilters(array $filters)
    {
        $config = $this->forecastPricingSettings();
        $selectedItems = array();

        foreach ($config['groups'] as $typeKey => $group) {
            $fieldKey = (string) ($group['field'] ?? '');
            $selectedValue = trim((string) ($filters[$fieldKey] ?? ''));
            if ($selectedValue === '') {
                continue;
            }
            foreach ((array) ($group['options'] ?? array()) as $option) {
                if ((string) ($option['value'] ?? '') !== $selectedValue || empty($option['enabled'])) {
                    continue;
                }
                $price = $this->normalizeForecastPrice($option['price'] ?? 0);
                $selectedItems[] = array(
                    'type_key' => $typeKey,
                    'type_label' => (string) ($group['label'] ?? ''),
                    'value' => $selectedValue,
                    'label' => (string) ($option['label'] ?? ''),
                    'price' => $price,
                    'price_text' => $this->formatForecastPoints($price),
                );
                break;
            }
        }

        $selectedCount = count($selectedItems);
        $discountPercent = (int) ($config['discounts'][(string) $selectedCount] ?? 100);
        if ($selectedCount <= 1) {
            $discountPercent = 100;
        }
        $discountRate = max(1, min(100, $discountPercent)) / 100;
        $totalOriginal = 0.0;
        $totalDiscounted = 0.0;

        foreach ($selectedItems as $itemIndex => $item) {
            $discountedPrice = round((float) $item['price'] * $discountRate, 2);
            $selectedItems[$itemIndex]['discounted_price'] = $discountedPrice;
            $selectedItems[$itemIndex]['discounted_price_text'] = $this->formatForecastPoints($discountedPrice);
            $totalOriginal += (float) $item['price'];
            $totalDiscounted += $discountedPrice;
        }

        return array(
            'selected_count' => $selectedCount,
            'discount_percent' => $discountPercent,
            'discount_label' => $selectedCount > 1 ? $discountPercent . '%' : '无优惠',
            'items' => $selectedItems,
            'total_original' => round($totalOriginal, 2),
            'total_original_text' => $this->formatForecastPoints($totalOriginal),
            'total' => round($totalDiscounted, 2),
            'total_text' => $this->formatForecastPoints($totalDiscounted),
        );
    }

    protected function defaultForecastPricingSettings()
    {
        if ($this->defaultForecastPricingSettingsRequestCache !== null) {
            return $this->defaultForecastPricingSettingsRequestCache;
        }

        $catalog = $this->forecastPricingCatalog();
        foreach ($catalog as $typeKey => $group) {
            foreach ($group['options'] as $optionIndex => $option) {
                $optionValue = (string) ($option['value'] ?? '');
                $catalog[$typeKey]['options'][$optionIndex]['price'] = $optionValue === 'pt_2_2_group_1' ? 9.98 : 10;
                $catalog[$typeKey]['options'][$optionIndex]['enabled'] = 1;
            }
        }

        $this->defaultForecastPricingSettingsRequestCache = array(
            'version' => 1,
            'participation_increment' => 8,
            'analysis_period_min' => 20,
            'analysis_period_max' => 20,
            'analysis_period' => 20,
            'member_daily_limit' => 100,
            'api_urls' => $this->defaultForecastApiUrls(),
            'discounts' => array(
                '1' => 100,
                '2' => 95,
                '3' => 90,
                '4' => 85,
            ),
            'groups' => $catalog,
        );

        return $this->defaultForecastPricingSettingsRequestCache;
    }

    protected function defaultForecastApiUrls()
    {
        return array(
            'macau_live_api_url' => 'https://www.macaumarksix.com/api/live2',
            'macau_history_api_url' => 'https://history.macaumarksix.com/history/macaujc2/y/%d',
            'hongkong_live_api_url' => 'https://api.macaumarksix.com/api/hkjc.com',
            'hongkong_history_api_url' => 'https://en.lottolyzer.com/history/hong-kong/mark-six/page/%d/per-page/%d/detail-view',
        );
    }

    protected function forecastApiUrlSettings(array $savedConfig = array())
    {
        $urls = $this->defaultForecastApiUrls();
        foreach ($urls as $key => $defaultUrl) {
            $value = isset($savedConfig['api_urls'][$key]) ? trim((string) $savedConfig['api_urls'][$key]) : '';
            if ($value === '') {
                $value = (string) $this->app->settings()->get('forecast.' . $key, $defaultUrl);
            }
            $urls[$key] = $this->normalizeForecastApiUrl($value, $defaultUrl, $this->forecastApiUrlPlaceholderCount($key));
        }

        return $urls;
    }

    protected function normalizeForecastApiUrlsForSave(array $payload)
    {
        $defaults = $this->defaultForecastApiUrls();
        $labels = array(
            'macau_live_api_url' => '澳门实时开奖 API URL',
            'macau_history_api_url' => '澳门历史开奖记录 API URL',
            'hongkong_live_api_url' => '香港实时开奖 API URL',
            'hongkong_history_api_url' => '香港历史开奖记录 API URL',
        );
        $urls = array();
        foreach ($defaults as $key => $defaultUrl) {
            $urls[$key] = $this->validateForecastApiUrl(
                $payload[$key] ?? $defaultUrl,
                $defaultUrl,
                $this->forecastApiUrlPlaceholderCount($key),
                $labels[$key] ?? $key
            );
        }

        return $urls;
    }

    protected function forecastApiUrlPlaceholderCount($key)
    {
        if ((string) $key === 'macau_history_api_url') {
            return 1;
        }
        if ((string) $key === 'hongkong_history_api_url') {
            return 2;
        }

        return 0;
    }

    protected function normalizeForecastApiUrl($value, $defaultUrl, $requiredPlaceholderCount = 0)
    {
        $url = trim((string) $value);
        if ($url === '') {
            return (string) $defaultUrl;
        }
        if (substr_count($url, '%d') !== (int) $requiredPlaceholderCount) {
            return (string) $defaultUrl;
        }

        $testUrl = str_replace('%d', '1', $url);
        $scheme = parse_url($testUrl, PHP_URL_SCHEME);
        if (!in_array($scheme, array('http', 'https'), true)) {
            return (string) $defaultUrl;
        }
        if (!filter_var($testUrl, FILTER_VALIDATE_URL)) {
            return (string) $defaultUrl;
        }

        return $url;
    }

    protected function validateForecastApiUrl($value, $defaultUrl, $requiredPlaceholderCount, $label)
    {
        $url = trim((string) $value);
        if ($url === '') {
            return (string) $defaultUrl;
        }
        if (substr_count($url, '%d') !== (int) $requiredPlaceholderCount) {
            if ((int) $requiredPlaceholderCount <= 0) {
                throw new RuntimeException((string) $label . '不能包含 %d 占位。');
            }
            throw new RuntimeException((string) $label . '必须保留 ' . (int) $requiredPlaceholderCount . ' 个 %d 占位。');
        }

        $testUrl = str_replace('%d', '1', $url);
        $scheme = parse_url($testUrl, PHP_URL_SCHEME);
        if (!in_array($scheme, array('http', 'https'), true) || !filter_var($testUrl, FILTER_VALIDATE_URL)) {
            throw new RuntimeException((string) $label . '必须填写 http:// 或 https:// 开头的有效 URL。');
        }

        return $url;
    }

    protected function forecastNumberTypeLabelForAdmin($count)
    {
        $labels = array(
            1 => '①码',
            2 => '②码',
            3 => '③码',
            4 => '④码',
            5 => '⑤码',
            6 => '⑥码',
            7 => '⑦码',
            8 => '⑧码',
            9 => '⑨码',
            10 => '⑩码',
        );

        return $labels[(int) $count] ?? ((int) $count . '码');
    }

    protected function truncateForecastLabel($label)
    {
        $label = trim((string) $label);
        if (function_exists('mb_substr')) {
            return (string) mb_substr($label, 0, 40, 'UTF-8');
        }

        return substr($label, 0, 120);
    }

    protected function normalizeForecastPrice($value)
    {
        $normalized = preg_replace('/[^\d.]+/', '', (string) $value);
        if ($normalized === null || $normalized === '') {
            return 0.0;
        }

        return round(max(0, min(999999, (float) $normalized)), 2);
    }

    public function formatForecastPoints($value)
    {
        $value = round((float) $value, 2);
        if (abs($value - round($value)) < 0.001) {
            return (string) (int) round($value);
        }

        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    public function homepageSettings()
    {
        if ($this->homepageSettingsRequestCache !== null) {
            return $this->homepageSettingsRequestCache;
        }

        $defaults = $this->defaultHomeSettings();
        $settings = array();

        foreach ($defaults as $settingKey => $defaultValue) {
            $resolvedValue = trim((string) $this->app->settings()->get($settingKey, (string) $defaultValue));
            $settings[$settingKey] = $resolvedValue !== '' ? $resolvedValue : (string) $defaultValue;
        }

        $this->homepageSettingsRequestCache = $settings;

        return $this->homepageSettingsRequestCache;
    }

    public function saveHomepageSettings(array $payload, array $actor)
    {
        $defaults = $this->defaultHomeSettings();
        $fieldMap = array(
            'brand_name_main' => 'home.brand_name_main',
            'brand_domain' => 'home.brand_domain',
            'download_text' => 'home.download_text',
            'download_url' => 'home.download_url',
            'hero_library_title' => 'home.hero_library_title',
            'hero_library_subtitle' => 'home.hero_library_subtitle',
            'hero_main_title' => 'home.hero_main_title',
            'hero_main_subtitle' => 'home.hero_main_subtitle',
            'marquee_text' => 'home.marquee_text',
            'vip_title' => 'home.vip_title',
            'vip_subtitle' => 'home.vip_subtitle',
            'vip_button_text' => 'home.vip_button_text',
            'vip_button_message' => 'home.vip_button_message',
        );
        $homeItems = array();

        foreach ($fieldMap as $fieldKey => $settingKey) {
            $value = trim((string) ($payload[$fieldKey] ?? ''));
            $homeItems[$settingKey] = $value !== '' ? $value : (string) ($defaults[$settingKey] ?? '');
        }

        $this->app->settings()->setMany('home', $homeItems, array_keys($homeItems));
        $this->homepageSettingsRequestCache = null;
        $this->recordOperation((int) $actor['id'], 'home', 'save_settings', 'settings', 0, '保存首页运营设置');

        return $this->homepageSettings();
    }

    public function frontHomeSnapshot($region)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $settings = $this->homepageSettings();
        $activeNotice = $this->activeScrollNotice($region);
        $downloadUrl = trim((string) ($settings['home.download_url'] ?? ''));
        $adSlotEntries = $this->homeAdSlotEntries($region);

        if ($downloadUrl === '') {
            $downloadUrl = public_url('service.php') . '?region=' . urlencode($region);
        }

        return array(
            'region' => $region,
            'brand_name_main' => (string) ($settings['home.brand_name_main'] ?? ''),
            'brand_domain' => (string) ($settings['home.brand_domain'] ?? ''),
            'download_text' => (string) ($settings['home.download_text'] ?? ''),
            'download_url' => $downloadUrl,
            'download_target' => '_self',
            'download_icon' => 'fa-solid fa-download',
            'hero_library_title' => (string) ($settings['home.hero_library_title'] ?? ''),
            'hero_library_subtitle' => (string) ($settings['home.hero_library_subtitle'] ?? ''),
            'hero_main_title' => (string) ($settings['home.hero_main_title'] ?? ''),
            'hero_main_subtitle' => (string) ($settings['home.hero_main_subtitle'] ?? ''),
            'marquee_text' => $activeNotice ? trim((string) ($activeNotice['content'] ?? '')) : (string) ($settings['home.marquee_text'] ?? ''),
            'banner_title' => '',
            'banner_image_url' => '',
            'banner_link_url' => '',
            'banner_target' => '_self',
            'vip_title' => (string) ($settings['home.vip_title'] ?? ''),
            'vip_subtitle' => (string) ($settings['home.vip_subtitle'] ?? ''),
            'vip_button_text' => (string) ($settings['home.vip_button_text'] ?? ''),
            'vip_button_message' => (string) ($settings['home.vip_button_message'] ?? ''),
            'data_cards' => $this->homeDataCards($region),
            'ad_slot_texts' => array_values(array_map(static function ($entry) {
                return (string) ($entry['title'] ?? '');
            }, $adSlotEntries)),
            'ad_slot_links' => array_values(array_map(static function ($entry) {
                return (string) ($entry['link_url'] ?? '');
            }, $adSlotEntries)),
            'expert_posts' => $this->homeExpertPosts($region),
            'modules' => $this->homeModuleConfigs($region),
        );
    }

    public function managedFrontHomeSnapshot($region)
    {
        $snapshot = $this->frontHomeSnapshot($region);
        $settings = $this->homepageSettings();
        $topEntry = $this->activeHomeTopEntry($region);
        $banner = $this->activeHomeBanner($region);

        $snapshot['download_text'] = $topEntry ? (string) ($topEntry['title'] ?? '') : (string) ($settings['home.download_text'] ?? '');
        $snapshot['download_url'] = $topEntry
            ? (string) ($topEntry['link_url'] ?? '')
            : (trim((string) ($settings['home.download_url'] ?? '')) !== '' ? (string) $settings['home.download_url'] : public_url('service.php') . '?region=' . urlencode((string) $region));
        $snapshot['download_target'] = $topEntry ? (string) ($topEntry['target'] ?? '_self') : '_self';
        $snapshot['download_icon'] = $topEntry ? (string) ($topEntry['icon'] ?? 'fa-solid fa-download') : 'fa-solid fa-download';
        $snapshot['banner_title'] = $banner ? (string) ($banner['title'] ?? '') : '';
        $snapshot['banner_image_url'] = $banner ? (string) ($banner['image_url'] ?? '') : '';
        $snapshot['banner_link_url'] = $banner ? (string) ($banner['link_url'] ?? '') : '';
        $snapshot['banner_target'] = $banner ? (string) ($banner['open_type'] ?? '_self') : '_self';
        $snapshot['data_cards'] = $this->homeDataCards($region);
        $adSlotEntries = $this->homeAdSlotEntries($region);
        $snapshot['ad_slot_texts'] = array_values(array_map(static function ($entry) {
            return (string) ($entry['title'] ?? '');
        }, $adSlotEntries));
        $snapshot['ad_slot_links'] = array_values(array_map(static function ($entry) {
            return (string) ($entry['link_url'] ?? '');
        }, $adSlotEntries));
        $snapshot['expert_posts'] = $this->homeExpertPosts($region);
        $snapshot['modules'] = $this->homeModuleConfigs($region);

        return $snapshot;
    }

    public function homeExpertPosts($region, $limitPerSegment = 0)
    {
        $region = $this->normalizeManagedDrawMaterialRegion((string) $region);
        $limitPerSegment = max(0, (int) $limitPerSegment);
        $viewer = current_user();
        $viewerId = (int) ($viewer['id'] ?? 0);
        $cacheKey = $region . '|' . $limitPerSegment . '|' . $viewerId;

        if (isset($this->homeExpertPostsCache[$cacheKey])) {
            return $this->homeExpertPostsCache[$cacheKey];
        }

        $grouped = array(
            1 => array(),
            2 => array(),
            3 => array(),
        );

        if (!$this->tableExists($this->db(), 'posts')) {
            $this->homeExpertPostsCache[$cacheKey] = $grouped;

            return $grouped;
        }

        $this->ensureManagedPostMetaReady();
        $interactionSelect = '0 AS like_count, 0 AS liked_by_viewer';
        $params = array(
            'region' => (string) $region,
            'status' => 'published',
        );

        if ($this->tableExists($this->db(), 'post_interactions')) {
            $interactionSelect = '(SELECT COUNT(*)
                                   FROM post_interactions
                                   WHERE post_interactions.post_id = posts.id
                                     AND post_interactions.interaction_type = \'like\'
                                     AND post_interactions.status = 1) AS like_count, ';

            if ($viewerId > 0) {
                $interactionSelect .= '(SELECT COUNT(*)
                                        FROM post_interactions
                                        WHERE post_interactions.post_id = posts.id
                                          AND post_interactions.user_id = :viewer_id
                                          AND post_interactions.interaction_type = \'like\'
                                          AND post_interactions.status = 1) AS liked_by_viewer';
                $params['viewer_id'] = $viewerId;
            } else {
                $interactionSelect .= '0 AS liked_by_viewer';
            }
        }

        $rows = $this->db()->fetchAll(
            'SELECT posts.id,
                    posts.region,
                    posts.title,
                    posts.price,
                    posts.view_count,
                    posts.full_content,
                    posts.created_at,
                    COALESCE(post_meta.recent_result_log, \'\') AS manage_recent_result_log,
                    COALESCE(NULLIF(post_meta.author_nickname, \'\'), users.username) AS author_name,
                    COALESCE(post_meta.title_prefix_text, \'\') AS title_prefix_text,
                    COALESCE(post_meta.title_middle_text, \'\') AS title_middle_text,
                    COALESCE(post_meta.title_prefix_color_mode, \'\') AS title_prefix_color_mode,
                    COALESCE(post_meta.title_prefix_color_value, \'\') AS title_prefix_color_value,
                    COALESCE(post_meta.title_middle_color_mode, \'\') AS title_middle_color_mode,
                    COALESCE(post_meta.title_middle_color_value, \'\') AS title_middle_color_value,
                    COALESCE(post_meta.author_nickname_color_mode, \'\') AS author_nickname_color_mode,
                    COALESCE(post_meta.author_nickname_color_value, \'\') AS author_nickname_color_value,
                    COALESCE(post_meta.title_font_size, \'\') AS title_font_size,
                    COALESCE(post_meta.title_font_weight, \'\') AS title_font_weight,
                    COALESCE(post_meta.segment_no, 1) AS segment_no,
                    COALESCE(NULLIF(post_meta.segment_sort, 0), posts.id) AS segment_sort,
                    ' . $interactionSelect . '
             FROM posts
             INNER JOIN users ON users.id = posts.author_id
             LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
             WHERE posts.region = :region
               AND posts.status = :status
               AND COALESCE(post_meta.is_hidden, 0) = 0
             ORDER BY COALESCE(post_meta.segment_no, 1) ASC,
                      COALESCE(NULLIF(post_meta.segment_sort, 0), posts.id) ASC,
                      posts.created_at DESC
             LIMIT 120',
            $params
        );

        if ($rows !== array() && $this->tableExists($this->db(), 'post_view_display_events')) {
            $postIds = array_values(array_unique(array_filter(array_map(static function ($row) {
                return (int) ($row['id'] ?? 0);
            }, $rows))));

            if ($postIds !== array()) {
                $countParams = array(
                    'release_at' => $this->now(),
                );
                $placeholders = array();
                foreach ($postIds as $index => $postId) {
                    $key = 'post_id_' . $index;
                    $placeholders[] = ':' . $key;
                    $countParams[$key] = $postId;
                }

                $countRows = $this->db()->fetchAll(
                    'SELECT post_id, COUNT(*) AS total_count
                     FROM post_view_display_events
                     WHERE post_id IN (' . implode(', ', $placeholders) . ')
                       AND release_at <= :release_at
                     GROUP BY post_id',
                    $countParams
                );
                $displayViewCounts = array();
                foreach ($countRows as $countRow) {
                    $displayViewCounts[(int) ($countRow['post_id'] ?? 0)] = (int) ($countRow['total_count'] ?? 0);
                }

                foreach ($rows as $rowIndex => $row) {
                    $postId = (int) ($row['id'] ?? 0);
                    $rows[$rowIndex]['display_view_count'] = max(0, (int) ($row['view_count'] ?? 0))
                        + (isset($displayViewCounts[$postId]) ? $displayViewCounts[$postId] : 0);
                }
            }
        }

        foreach ($rows as $row) {
            $segmentNo = max(1, min(3, (int) ($row['segment_no'] ?? 1)));

            if ($limitPerSegment > 0 && count($grouped[$segmentNo]) >= $limitPerSegment) {
                continue;
            }

            $grouped[$segmentNo][] = $row;
        }

        $this->homeExpertPostsCache[$cacheKey] = $grouped;

        return $grouped;
    }

    public function listManagedNotices(array $filters = array())
    {
        $sql = 'SELECT * FROM notices WHERE 1 = 1';
        $params = array();
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $region = trim((string) ($filters['region'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        if ($keyword !== '') {
            $sql .= ' AND (title LIKE :title_keyword OR content LIKE :content_keyword)';
            $params['title_keyword'] = '%' . $keyword . '%';
            $params['content_keyword'] = '%' . $keyword . '%';
        }

        if (in_array($region, array('all', 'macau', 'hongkong'), true)) {
            $sql .= ' AND region = :region';
            $params['region'] = $region;
        }

        if ($status !== '' && in_array($status, array('0', '1'), true)) {
            $sql .= ' AND status = :status';
            $params['status'] = (int) $status;
        }

        $sql .= ' ORDER BY sort_order ASC, id DESC LIMIT 80';

        return $this->db()->fetchAll($sql, $params);
    }

    public function managedNoticeById($noticeId)
    {
        return $this->db()->fetch('SELECT * FROM notices WHERE id = :id LIMIT 1', array('id' => (int) $noticeId));
    }

    public function saveManagedNotice(array $payload, array $actor)
    {
        $noticeId = isset($payload['id']) ? (int) $payload['id'] : 0;
        $region = trim((string) ($payload['region'] ?? 'all'));
        $title = trim((string) ($payload['title'] ?? ''));
        $content = trim((string) ($payload['content'] ?? ''));
        $linkUrl = trim((string) ($payload['link_url'] ?? ''));
        $sortOrder = (int) ($payload['sort_order'] ?? 0);
        $status = isset($payload['status']) && (string) $payload['status'] === '0' ? 0 : 1;
        $startAt = $this->normalizeDateTimeInput((string) ($payload['start_at'] ?? ''));
        $endAt = $this->normalizeDateTimeInput((string) ($payload['end_at'] ?? ''));
        $isUpdating = $noticeId > 0;

        if (!in_array($region, array('all', 'macau', 'hongkong'), true)) {
            throw new RuntimeException('公告分区无效。');
        }

        if ($title === '' || $content === '') {
            throw new RuntimeException('公告标题和内容不能为空。');
        }

        $data = array(
            'region' => $region,
            'title' => $title,
            'content' => $content,
            'notice_type' => 'scroll',
            'link_url' => $linkUrl,
            'sort_order' => $sortOrder,
            'status' => $status,
            'start_at' => $startAt !== '' ? $startAt : null,
            'end_at' => $endAt !== '' ? $endAt : null,
            'updated_at' => $this->now(),
        );

        if ($noticeId > 0) {
            $this->db()->execute(
                'UPDATE notices
                 SET region = :region, title = :title, content = :content, notice_type = :notice_type, link_url = :link_url,
                     sort_order = :sort_order, status = :status, start_at = :start_at, end_at = :end_at, updated_at = :updated_at
                 WHERE id = :id',
                $data + array('id' => $noticeId)
            );
        } else {
            $noticeId = $this->db()->insertGetId(
                'INSERT INTO notices (region, title, content, notice_type, link_url, sort_order, status, start_at, end_at, created_at, updated_at)
                 VALUES (:region, :title, :content, :notice_type, :link_url, :sort_order, :status, :start_at, :end_at, :created_at, :updated_at)',
                $data + array('created_at' => $this->now())
            );
        }

        $savedNotice = $this->managedNoticeById($noticeId);
        $this->recordOperation((int) $actor['id'], 'home', $isUpdating ? 'save_notice' : 'create_notice', 'notice', (int) $noticeId, '保存首页滚动公告：' . $title);

        return $savedNotice;
    }

    public function activeScrollNotice($region)
    {
        if (!$this->tableExists($this->db(), 'notices')) {
            return null;
        }

        $now = $this->now();

        return $this->db()->fetch(
            'SELECT *
             FROM notices
             WHERE notice_type = :notice_type
               AND status = 1
               AND (region = :region OR region = :all_region)
               AND (start_at IS NULL OR start_at = "" OR start_at <= :start_now_time)
               AND (end_at IS NULL OR end_at = "" OR end_at >= :end_now_time)
             ORDER BY CASE WHEN region = :region_first THEN 0 ELSE 1 END, sort_order ASC, id DESC
             LIMIT 1',
            array(
                'notice_type' => 'scroll',
                'region' => (string) $region,
                'all_region' => 'all',
                'start_now_time' => $now,
                'end_now_time' => $now,
                'region_first' => (string) $region,
            )
        );
    }

    public function homeRegionSnapshots()
    {
        $regions = array(
            'macau' => '澳门',
            'hongkong' => '香港',
        );
        $cards = array();

        foreach ($regions as $region => $label) {
            $snapshot = $this->managedFrontHomeSnapshot($region);
            $notice = $this->activeScrollNotice($region);
            $cards[] = array(
                'region' => $region,
                'label' => $label,
                'brand_name_main' => (string) ($snapshot['brand_name_main'] ?? ''),
                'brand_domain' => (string) ($snapshot['brand_domain'] ?? ''),
                'hero_main_title' => (string) ($snapshot['hero_main_title'] ?? ''),
                'hero_main_subtitle' => (string) ($snapshot['hero_main_subtitle'] ?? ''),
                'marquee_text' => (string) ($snapshot['marquee_text'] ?? ''),
                'download_text' => (string) ($snapshot['download_text'] ?? ''),
                'download_url' => (string) ($snapshot['download_url'] ?? ''),
                'vip_title' => (string) ($snapshot['vip_title'] ?? ''),
                'vip_button_text' => (string) ($snapshot['vip_button_text'] ?? ''),
                'notice_title' => $notice ? (string) ($notice['title'] ?? '') : '未命中公告',
                'notice_region' => $notice ? (string) ($notice['region'] ?? 'all') : '',
                'notice_status_text' => $notice ? '正在使用公告内容' : '当前回退为默认文案',
                'notice_start_at' => $notice ? (string) ($notice['start_at'] ?? '') : '',
                'notice_end_at' => $notice ? (string) ($notice['end_at'] ?? '') : '',
            );
        }

        return $cards;
    }

    public function homeCardPositionLabels()
    {
        return array(
            'home_data_card_1' => '资料卡片一',
            'home_data_card_2' => '资料卡片二',
            'home_data_card_3' => '资料卡片三',
            'home_data_card_4' => '资料卡片四',
        );
    }

    public function homeAdSlotLabels()
    {
        return array(
            'home_ad_primary_1' => '广告一区-1',
            'home_ad_primary_2' => '广告一区-2',
            'home_ad_primary_3' => '广告一区-3',
            'home_ad_primary_4' => '广告一区-4',
            'home_ad_primary_5' => '广告一区-5',
            'home_ad_primary_6' => '广告一区-6',
            'home_ad_secondary_1' => '广告二区-1',
            'home_ad_secondary_2' => '广告二区-2',
            'home_ad_secondary_3' => '广告二区-3',
            'home_ad_secondary_4' => '广告二区-4',
            'home_ad_secondary_5' => '广告二区-5',
            'home_ad_secondary_6' => '广告二区-6',
            'home_ad_tertiary_1' => '广告三区-1',
            'home_ad_tertiary_2' => '广告三区-2',
            'home_ad_tertiary_3' => '广告三区-3',
            'home_ad_tertiary_4' => '广告三区-4',
            'home_ad_tertiary_5' => '广告三区-5',
            'home_ad_tertiary_6' => '广告三区-6',
        );
    }

    public function homeModuleLabels()
    {
        return array(
            'home_marquee' => '滚动公告',
            'home_calendar' => '首页日历',
            'home_data_cards' => '资料卡片区',
            'home_primary_ads' => '广告一区',
        );
    }

    public function listManagedHomeBanners(array $filters = array())
    {
        if (!$this->tableExists($this->db(), 'home_banners')) {
            return array();
        }

        $sql = 'SELECT * FROM home_banners WHERE 1 = 1';
        $params = array();
        $region = trim((string) ($filters['region'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        if (in_array($region, array('all', 'macau', 'hongkong'), true)) {
            $sql .= ' AND region = :region';
            $params['region'] = $region;
        }

        if (in_array($status, array('0', '1'), true)) {
            $sql .= ' AND status = :status';
            $params['status'] = (int) $status;
        }

        $sql .= ' ORDER BY sort_order ASC, id DESC';

        return $this->db()->fetchAll($sql, $params);
    }

    public function managedHomeBannerById($bannerId)
    {
        if (!$this->tableExists($this->db(), 'home_banners')) {
            return null;
        }

        return $this->db()->fetch('SELECT * FROM home_banners WHERE id = :id LIMIT 1', array('id' => (int) $bannerId));
    }

    public function saveManagedHomeBanner(array $payload, array $actor)
    {
        if (!$this->tableExists($this->db(), 'home_banners')) {
            throw new RuntimeException('当前数据库还没有首页轮播图表，请先刷新后台完成补表。');
        }

        $bannerId = isset($payload['id']) ? (int) $payload['id'] : 0;
        $region = trim((string) ($payload['region'] ?? 'all'));
        $title = trim((string) ($payload['title'] ?? ''));
        $imageUrl = trim((string) ($payload['image_url'] ?? ''));
        $linkUrl = trim((string) ($payload['link_url'] ?? ''));
        $openType = trim((string) ($payload['open_type'] ?? '_self'));
        $sortOrder = (int) ($payload['sort_order'] ?? 10);
        $status = isset($payload['status']) && (string) $payload['status'] === '0' ? 0 : 1;
        $startAt = $this->normalizeDateTimeInput((string) ($payload['start_at'] ?? ''));
        $endAt = $this->normalizeDateTimeInput((string) ($payload['end_at'] ?? ''));

        if (!in_array($region, array('all', 'macau', 'hongkong'), true)) {
            throw new RuntimeException('首页轮播图分区无效。');
        }

        if ($title === '' || $imageUrl === '') {
            throw new RuntimeException('轮播图标题和图片地址不能为空。');
        }

        if (!in_array($openType, array('_self', '_blank'), true)) {
            $openType = '_self';
        }

        $data = array(
            'region' => $region,
            'title' => $title,
            'image_url' => $imageUrl,
            'link_url' => $linkUrl,
            'open_type' => $openType,
            'sort_order' => $sortOrder,
            'status' => $status,
            'start_at' => $startAt !== '' ? $startAt : null,
            'end_at' => $endAt !== '' ? $endAt : null,
            'updated_at' => $this->now(),
        );

        if ($bannerId > 0) {
            $this->db()->execute(
                'UPDATE home_banners
                 SET region = :region, title = :title, image_url = :image_url, link_url = :link_url, open_type = :open_type,
                     sort_order = :sort_order, status = :status, start_at = :start_at, end_at = :end_at, updated_at = :updated_at
                 WHERE id = :id',
                $data + array('id' => $bannerId)
            );
            $action = 'update_banner';
        } else {
            $bannerId = (int) $this->db()->insertGetId(
                'INSERT INTO home_banners (region, title, image_url, link_url, open_type, sort_order, status, start_at, end_at, created_at, updated_at)
                 VALUES (:region, :title, :image_url, :link_url, :open_type, :sort_order, :status, :start_at, :end_at, :created_at, :updated_at)',
                $data + array('created_at' => $this->now())
            );
            $action = 'create_banner';
        }

        $savedBanner = $this->managedHomeBannerById($bannerId);
        $this->recordOperation((int) $actor['id'], 'home', $action, 'home_banner', $bannerId, '保存首页轮播图：' . $title);

        return $savedBanner;
    }

    public function activeHomeBanner($region)
    {
        if (!$this->tableExists($this->db(), 'home_banners')) {
            return null;
        }

        $now = $this->now();

        return $this->db()->fetch(
            'SELECT *
             FROM home_banners
             WHERE status = 1
               AND (region = :region OR region = :all_region)
               AND (start_at IS NULL OR start_at = "" OR start_at <= :start_now)
               AND (end_at IS NULL OR end_at = "" OR end_at >= :end_now)
             ORDER BY CASE WHEN region = :region_first THEN 0 ELSE 1 END, sort_order ASC, id DESC
             LIMIT 1',
            array(
                'region' => (string) $region,
                'all_region' => 'all',
                'start_now' => $now,
                'end_now' => $now,
                'region_first' => (string) $region,
            )
        );
    }

    public function listManagedHomeTopEntries(array $filters = array())
    {
        if (!$this->tableExists($this->db(), 'home_nav_entries')) {
            return array();
        }

        $sql = 'SELECT * FROM home_nav_entries WHERE 1 = 1';
        $params = array();
        $region = trim((string) ($filters['region'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        if (in_array($region, array('all', 'macau', 'hongkong'), true)) {
            $sql .= ' AND region = :region';
            $params['region'] = $region;
        }

        if (in_array($status, array('0', '1'), true)) {
            $sql .= ' AND status = :status';
            $params['status'] = (int) $status;
        }

        $sql .= ' ORDER BY sort_order ASC, id ASC';

        return $this->db()->fetchAll($sql, $params);
    }

    public function managedHomeTopEntryById($entryId)
    {
        if (!$this->tableExists($this->db(), 'home_nav_entries')) {
            return null;
        }

        return $this->db()->fetch('SELECT * FROM home_nav_entries WHERE id = :id LIMIT 1', array('id' => (int) $entryId));
    }

    public function saveManagedHomeTopEntry(array $payload, array $actor)
    {
        if (!$this->tableExists($this->db(), 'home_nav_entries')) {
            throw new RuntimeException('当前数据库还没有首页顶部入口表，请先刷新后台完成补表。');
        }

        $entryId = isset($payload['id']) ? (int) $payload['id'] : 0;
        $region = trim((string) ($payload['region'] ?? 'all'));
        $title = trim((string) ($payload['title'] ?? ''));
        $icon = trim((string) ($payload['icon'] ?? 'fa-solid fa-download'));
        $linkUrl = trim((string) ($payload['link_url'] ?? ''));
        $target = trim((string) ($payload['target'] ?? '_self'));
        $sortOrder = (int) ($payload['sort_order'] ?? 10);
        $status = isset($payload['status']) && (string) $payload['status'] === '0' ? 0 : 1;

        if (!in_array($region, array('all', 'macau', 'hongkong'), true)) {
            throw new RuntimeException('顶部入口分区无效。');
        }

        if ($title === '' || $linkUrl === '') {
            throw new RuntimeException('顶部入口文案和链接不能为空。');
        }

        if (!in_array($target, array('_self', '_blank'), true)) {
            $target = '_self';
        }

        $data = array(
            'region' => $region,
            'title' => $title,
            'icon' => $icon,
            'link_url' => $linkUrl,
            'target' => $target,
            'sort_order' => $sortOrder,
            'status' => $status,
            'updated_at' => $this->now(),
        );

        if ($entryId > 0) {
            $this->db()->execute(
                'UPDATE home_nav_entries
                 SET region = :region, title = :title, icon = :icon, link_url = :link_url, target = :target,
                     sort_order = :sort_order, status = :status, updated_at = :updated_at
                 WHERE id = :id',
                $data + array('id' => $entryId)
            );
            $action = 'update_top_entry';
        } else {
            $entryId = (int) $this->db()->insertGetId(
                'INSERT INTO home_nav_entries (region, title, icon, link_url, target, sort_order, status, created_at, updated_at)
                 VALUES (:region, :title, :icon, :link_url, :target, :sort_order, :status, :created_at, :updated_at)',
                $data + array('created_at' => $this->now())
            );
            $action = 'create_top_entry';
        }

        $savedEntry = $this->managedHomeTopEntryById($entryId);
        $this->recordOperation((int) $actor['id'], 'home', $action, 'home_top_entry', $entryId, '保存首页顶部入口：' . $title);

        return $savedEntry;
    }

    public function activeHomeTopEntry($region)
    {
        if (!$this->tableExists($this->db(), 'home_nav_entries')) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT *
             FROM home_nav_entries
             WHERE status = 1
               AND (region = :region OR region = :all_region)
             ORDER BY CASE WHEN region = :region_first THEN 0 ELSE 1 END, sort_order ASC, id ASC
             LIMIT 1',
            array(
                'region' => (string) $region,
                'all_region' => 'all',
                'region_first' => (string) $region,
            )
        );
    }

    public function listManagedHomeCards(array $filters = array())
    {
        if (!$this->tableExists($this->db(), 'recommend_positions')) {
            return array();
        }

        $positionLabels = $this->homeCardPositionLabels();
        $sql = 'SELECT * FROM recommend_positions WHERE target_type = :target_type';
        $params = array('target_type' => 'custom_html');
        $region = trim((string) ($filters['region'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        if (in_array($region, array('all', 'macau', 'hongkong'), true)) {
            $sql .= ' AND region = :region';
            $params['region'] = $region;
        }

        if (in_array($status, array('0', '1'), true)) {
            $sql .= ' AND status = :status';
            $params['status'] = (int) $status;
        }

        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $rows = $this->db()->fetchAll($sql, $params);
        $items = array();

        foreach ($rows as $row) {
            $positionCode = (string) ($row['position_code'] ?? '');
            if (!isset($positionLabels[$positionCode])) {
                continue;
            }

            $row['position_label'] = $positionLabels[$positionCode];
            $items[] = $row;
        }

        return $items;
    }

    public function managedHomeCardById($cardId)
    {
        if (!$this->tableExists($this->db(), 'recommend_positions')) {
            return null;
        }

        return $this->db()->fetch('SELECT * FROM recommend_positions WHERE id = :id LIMIT 1', array('id' => (int) $cardId));
    }

    public function saveManagedHomeCard(array $payload, array $actor)
    {
        if (!$this->tableExists($this->db(), 'recommend_positions')) {
            throw new RuntimeException('当前数据库还没有首页推荐位表，请先刷新后台完成补表。');
        }

        $cardId = isset($payload['id']) ? (int) $payload['id'] : 0;
        $positionCode = trim((string) ($payload['position_code'] ?? ''));
        $region = trim((string) ($payload['region'] ?? 'all'));
        $titleOverride = trim((string) ($payload['title_override'] ?? ''));
        $sortOrder = (int) ($payload['sort_order'] ?? 10);
        $status = isset($payload['status']) && (string) $payload['status'] === '0' ? 0 : 1;

        if (!isset($this->homeCardPositionLabels()[$positionCode])) {
            throw new RuntimeException('资料卡片位置无效。');
        }

        if (!in_array($region, array('all', 'macau', 'hongkong'), true)) {
            throw new RuntimeException('资料卡片分区无效。');
        }

        if ($titleOverride === '') {
            throw new RuntimeException('资料卡片内容不能为空。');
        }

        $existing = $this->db()->fetch(
            'SELECT id FROM recommend_positions WHERE region = :region AND position_code = :position_code AND id <> :id LIMIT 1',
            array(
                'region' => $region,
                'position_code' => $positionCode,
                'id' => $cardId,
            )
        );
        if ($existing) {
            throw new RuntimeException('当前分区下该资料卡片位置已经存在，请直接编辑原记录。');
        }

        $data = array(
            'position_code' => $positionCode,
            'target_type' => 'custom_html',
            'target_id' => 0,
            'region' => $region,
            'title_override' => $titleOverride,
            'sort_order' => $sortOrder,
            'status' => $status,
            'start_at' => null,
            'end_at' => null,
            'updated_at' => $this->now(),
        );

        if ($cardId > 0) {
            $this->db()->execute(
                'UPDATE recommend_positions
                 SET position_code = :position_code, target_type = :target_type, target_id = :target_id, region = :region,
                     title_override = :title_override, sort_order = :sort_order, status = :status, start_at = :start_at,
                     end_at = :end_at, updated_at = :updated_at
                 WHERE id = :id',
                $data + array('id' => $cardId)
            );
            $action = 'update_card';
        } else {
            $cardId = (int) $this->db()->insertGetId(
                'INSERT INTO recommend_positions (position_code, target_type, target_id, region, title_override, sort_order, status, start_at, end_at, created_at, updated_at)
                 VALUES (:position_code, :target_type, :target_id, :region, :title_override, :sort_order, :status, :start_at, :end_at, :created_at, :updated_at)',
                $data + array('created_at' => $this->now())
            );
            $action = 'create_card';
        }

        $savedCard = $this->managedHomeCardById($cardId);
        $this->recordOperation((int) $actor['id'], 'home', $action, 'home_card', $cardId, '保存首页资料卡片：' . $positionCode);

        return $savedCard;
    }

    public function homeDataCards($region)
    {
        $defaults = $this->defaultHomeCardSeeds();
        if (!$this->tableExists($this->db(), 'recommend_positions')) {
            return array_values(array_map(static function ($row) {
                return (string) $row['title_override'];
            }, $defaults));
        }

        $rows = $this->db()->fetchAll(
            'SELECT * FROM recommend_positions
             WHERE target_type = :target_type
               AND status = 1
               AND (region = :region OR region = :all_region)
             ORDER BY CASE WHEN region = :region_first THEN 0 ELSE 1 END, sort_order ASC, id DESC',
            array(
                'target_type' => 'custom_html',
                'region' => (string) $region,
                'all_region' => 'all',
                'region_first' => (string) $region,
            )
        );

        $resolved = array();
        foreach ($rows as $row) {
            $positionCode = (string) ($row['position_code'] ?? '');
            if (!isset($defaults[$positionCode]) || isset($resolved[$positionCode])) {
                continue;
            }
            $resolved[$positionCode] = trim((string) ($row['title_override'] ?? ''));
        }

        $cards = array();
        foreach ($defaults as $positionCode => $seed) {
            $cards[] = $resolved[$positionCode] ?? (string) $seed['title_override'];
        }

        return $cards;
    }

    public function listManagedAdSlots(array $filters = array())
    {
        if (!$this->tableExists($this->db(), 'ad_slots')) {
            return array();
        }

        $slotLabels = $this->homeAdSlotLabels();
        $sql = 'SELECT * FROM ad_slots WHERE page_key = :page_key';
        $params = array('page_key' => 'home');
        $region = trim((string) ($filters['region'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        if (in_array($region, array('all', 'macau', 'hongkong'), true)) {
            $sql .= ' AND region = :region';
            $params['region'] = $region;
        }

        if (in_array($status, array('0', '1'), true)) {
            $sql .= ' AND status = :status';
            $params['status'] = (int) $status;
        }

        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $rows = $this->db()->fetchAll($sql, $params);
        $items = array();

        foreach ($rows as $row) {
            $slotCode = (string) ($row['slot_code'] ?? '');
            if (!isset($slotLabels[$slotCode])) {
                continue;
            }

            $row['slot_label'] = $slotLabels[$slotCode];
            $items[] = $row;
        }

        return $items;
    }

    public function managedAdSlotById($slotId)
    {
        if (!$this->tableExists($this->db(), 'ad_slots')) {
            return null;
        }

        return $this->db()->fetch('SELECT * FROM ad_slots WHERE id = :id LIMIT 1', array('id' => (int) $slotId));
    }

    public function saveManagedAdSlot(array $payload, array $actor)
    {
        if (!$this->tableExists($this->db(), 'ad_slots')) {
            throw new RuntimeException('当前数据库还没有首页广告位表，请先刷新后台完成补表。');
        }

        $slotId = isset($payload['id']) ? (int) $payload['id'] : 0;
        $slotCode = trim((string) ($payload['slot_code'] ?? ''));
        $region = trim((string) ($payload['region'] ?? 'all'));
        $title = trim((string) ($payload['title'] ?? ''));
        $linkUrl = trim((string) ($payload['link_url'] ?? ''));
        $sortOrder = (int) ($payload['sort_order'] ?? 10);
        $status = isset($payload['status']) && (string) $payload['status'] === '0' ? 0 : 1;

        if (!isset($this->homeAdSlotLabels()[$slotCode])) {
            throw new RuntimeException('广告位编码无效。');
        }

        if (!in_array($region, array('all', 'macau', 'hongkong'), true)) {
            throw new RuntimeException('广告位分区无效。');
        }

        if ($title === '') {
            throw new RuntimeException('广告位文案不能为空。');
        }

        $existing = $this->db()->fetch(
            'SELECT id FROM ad_slots WHERE region = :region AND slot_code = :slot_code AND id <> :id LIMIT 1',
            array(
                'region' => $region,
                'slot_code' => $slotCode,
                'id' => $slotId,
            )
        );
        if ($existing) {
            throw new RuntimeException('当前分区下该广告位已经存在，请直接编辑原记录。');
        }

        $data = array(
            'slot_code' => $slotCode,
            'slot_name' => $this->homeAdSlotLabels()[$slotCode],
            'region' => $region,
            'page_key' => 'home',
            'title' => $title,
            'image_url' => '',
            'link_url' => $linkUrl,
            'sort_order' => $sortOrder,
            'status' => $status,
            'updated_at' => $this->now(),
        );

        if ($slotId > 0) {
            $this->db()->execute(
                'UPDATE ad_slots
                 SET slot_code = :slot_code, slot_name = :slot_name, region = :region, page_key = :page_key, title = :title,
                     image_url = :image_url, link_url = :link_url, sort_order = :sort_order, status = :status, updated_at = :updated_at
                 WHERE id = :id',
                $data + array('id' => $slotId)
            );
            $action = 'update_ad_slot';
        } else {
            $slotId = (int) $this->db()->insertGetId(
                'INSERT INTO ad_slots (slot_code, slot_name, region, page_key, title, image_url, link_url, sort_order, status, created_at, updated_at)
                 VALUES (:slot_code, :slot_name, :region, :page_key, :title, :image_url, :link_url, :sort_order, :status, :created_at, :updated_at)',
                $data + array('created_at' => $this->now())
            );
            $action = 'create_ad_slot';
        }

        $savedAd = $this->managedAdSlotById($slotId);
        $this->recordOperation((int) $actor['id'], 'home', $action, 'ad_slot', $slotId, '保存首页广告位：' . $slotCode);

        return $savedAd;
    }

    public function homeAdSlotTexts($region)
    {
        return array_values(array_map(static function ($entry) {
            return (string) ($entry['title'] ?? '');
        }, $this->homeAdSlotEntries($region)));
    }

    public function homeAdSlotEntries($region)
    {
        $defaults = $this->defaultHomeAdSlotSeeds();

        if (!$this->tableExists($this->db(), 'ad_slots')) {
            return array_values(array_map(static function ($row) {
                return array(
                    'slot_code' => (string) ($row['slot_code'] ?? ''),
                    'title' => (string) ($row['title'] ?? ''),
                    'link_url' => trim((string) ($row['link_url'] ?? '')),
                );
            }, $defaults));
        }

        $rows = $this->db()->fetchAll(
            'SELECT * FROM ad_slots
             WHERE page_key = :page_key
               AND status = 1
               AND (region = :region OR region = :all_region)
             ORDER BY CASE WHEN region = :region_first THEN 0 ELSE 1 END, sort_order ASC, id ASC',
            array(
                'page_key' => 'home',
                'region' => (string) $region,
                'all_region' => 'all',
                'region_first' => (string) $region,
            )
        );

        $resolved = array();
        foreach ($rows as $row) {
            $slotCode = (string) ($row['slot_code'] ?? '');
            if (!isset($defaults[$slotCode]) || isset($resolved[$slotCode])) {
                continue;
            }
            $resolved[$slotCode] = array(
                'slot_code' => $slotCode,
                'title' => trim((string) ($row['title'] ?? '')),
                'link_url' => trim((string) ($row['link_url'] ?? '')),
            );
        }

        $items = array();
        foreach ($defaults as $slotCode => $seed) {
            $items[] = array(
                'slot_code' => $slotCode,
                'title' => isset($resolved[$slotCode]['title']) && trim((string) $resolved[$slotCode]['title']) !== ''
                    ? (string) $resolved[$slotCode]['title']
                    : (string) ($seed['title'] ?? ''),
                'link_url' => isset($resolved[$slotCode]['link_url']) ? (string) $resolved[$slotCode]['link_url'] : trim((string) ($seed['link_url'] ?? '')),
            );
        }

        return $items;
    }

    public function listManagedHomeModules(array $filters = array())
    {
        if (!$this->tableExists($this->db(), 'home_module_configs')) {
            return array();
        }

        $moduleLabels = $this->homeModuleLabels();
        $sql = 'SELECT * FROM home_module_configs WHERE 1 = 1';
        $params = array();
        $region = trim((string) ($filters['region'] ?? ''));

        if (in_array($region, array('all', 'macau', 'hongkong'), true)) {
            $sql .= ' AND region = :region';
            $params['region'] = $region;
        }

        $sql .= ' ORDER BY sort_order ASC, id ASC';
        $rows = $this->db()->fetchAll($sql, $params);
        $items = array();

        foreach ($rows as $row) {
            $moduleKey = (string) ($row['module_key'] ?? '');
            if (!isset($moduleLabels[$moduleKey])) {
                continue;
            }

            $row['module_label'] = $moduleLabels[$moduleKey];
            $items[] = $row;
        }

        return $items;
    }

    public function saveManagedHomeModule(array $payload, array $actor)
    {
        if (!$this->tableExists($this->db(), 'home_module_configs')) {
            throw new RuntimeException('当前数据库还没有首页模块配置表，请先刷新后台完成补表。');
        }

        $moduleKey = trim((string) ($payload['module_key'] ?? ''));
        $region = trim((string) ($payload['region'] ?? 'all'));
        $sortOrder = (int) ($payload['sort_order'] ?? 10);
        $isEnabled = isset($payload['is_enabled']) && (string) ($payload['is_enabled']) === '0' ? 0 : 1;

        if (!isset($this->homeModuleLabels()[$moduleKey])) {
            throw new RuntimeException('首页模块键值无效。');
        }

        if (!in_array($region, array('all', 'macau', 'hongkong'), true)) {
            throw new RuntimeException('首页模块分区无效。');
        }

        $existing = $this->db()->fetch(
            'SELECT id FROM home_module_configs WHERE region = :region AND module_key = :module_key LIMIT 1',
            array(
                'region' => $region,
                'module_key' => $moduleKey,
            )
        );

        $data = array(
            'module_key' => $moduleKey,
            'module_name' => $this->homeModuleLabels()[$moduleKey],
            'region' => $region,
            'is_enabled' => $isEnabled,
            'sort_order' => $sortOrder,
            'config_json' => null,
            'updated_by' => (int) $actor['id'],
            'updated_at' => $this->now(),
        );

        if ($existing) {
            $this->db()->execute(
                'UPDATE home_module_configs
                 SET module_name = :module_name, is_enabled = :is_enabled, sort_order = :sort_order, updated_by = :updated_by, updated_at = :updated_at
                 WHERE id = :id',
                array(
                    'module_name' => $data['module_name'],
                    'is_enabled' => $data['is_enabled'],
                    'sort_order' => $data['sort_order'],
                    'updated_by' => $data['updated_by'],
                    'updated_at' => $data['updated_at'],
                    'id' => (int) $existing['id'],
                )
            );
            $moduleId = (int) $existing['id'];
            $action = 'update_module';
        } else {
            $moduleId = (int) $this->db()->insertGetId(
                'INSERT INTO home_module_configs (module_key, module_name, region, is_enabled, sort_order, config_json, updated_by, created_at, updated_at)
                 VALUES (:module_key, :module_name, :region, :is_enabled, :sort_order, :config_json, :updated_by, :created_at, :updated_at)',
                $data + array('created_at' => $this->now())
            );
            $action = 'create_module';
        }

        $this->recordOperation((int) $actor['id'], 'home', $action, 'home_module', $moduleId, '保存首页模块：' . $moduleKey);

        return $this->db()->fetch('SELECT * FROM home_module_configs WHERE id = :id LIMIT 1', array('id' => $moduleId));
    }

    public function homeModuleConfigs($region)
    {
        $labels = $this->homeModuleLabels();
        $defaults = $this->defaultHomeModuleSeeds();
        $resolved = array();

        if ($this->tableExists($this->db(), 'home_module_configs')) {
            $rows = $this->db()->fetchAll(
                'SELECT * FROM home_module_configs
                 WHERE region = :region OR region = :all_region
                 ORDER BY CASE WHEN region = :region_first THEN 0 ELSE 1 END, sort_order ASC, id ASC',
                array(
                    'region' => (string) $region,
                    'all_region' => 'all',
                    'region_first' => (string) $region,
                )
            );

            foreach ($rows as $row) {
                $moduleKey = (string) ($row['module_key'] ?? '');
                if (!isset($labels[$moduleKey]) || isset($resolved[$moduleKey])) {
                    continue;
                }

                $resolved[$moduleKey] = array(
                    'module_key' => $moduleKey,
                    'module_name' => $labels[$moduleKey],
                    'region' => (string) ($row['region'] ?? 'all'),
                    'is_enabled' => (int) ($row['is_enabled'] ?? 1),
                    'sort_order' => (int) ($row['sort_order'] ?? 0),
                );
            }
        }

        foreach ($defaults as $moduleKey => $seed) {
            if (!isset($resolved[$moduleKey])) {
                $resolved[$moduleKey] = array(
                    'module_key' => $moduleKey,
                    'module_name' => $labels[$moduleKey],
                    'region' => 'all',
                    'is_enabled' => (int) $seed['is_enabled'],
                    'sort_order' => (int) $seed['sort_order'],
                );
            }
        }

        uasort($resolved, static function ($left, $right) {
            $sortCompare = (int) $left['sort_order'] <=> (int) $right['sort_order'];
            if ($sortCompare !== 0) {
                return $sortCompare;
            }

            return strcmp((string) $left['module_key'], (string) $right['module_key']);
        });

        return array_values($resolved);
    }

    public function dashboardStats()
    {
        if ($this->dashboardStatsRequestCache !== null) {
            return $this->dashboardStatsRequestCache;
        }

        $cacheKey = 'admin_dashboard_stats_v18';
        $cached = $this->app->cache()->get($cacheKey, null, 30);
        if (is_array($cached)) {
            $this->dashboardStatsRequestCache = $cached;

            return $cached;
        }

        $today = date('Y-m-d');
        $todayStart = $today . ' 00:00:00';
        $tomorrowStart = date('Y-m-d 00:00:00', strtotime($todayStart . ' +1 day'));
        $yesterday = date('Y-m-d', strtotime($today . ' -1 day'));
        $yesterdayStart = $yesterday . ' 00:00:00';
        $weekStart = date('Y-m-d 00:00:00', strtotime('monday this week', strtotime($todayStart)));
        $visitRetentionStart = date('Y-m-d H:i:s', strtotime('-7 days'));
        $dashboardTableNames = array('page_views', 'admin_operation_logs', 'admin_logs', 'purchases', 'customer_service_sessions', 'password_reset_requests');
        $dashboardTables = array_fill_keys($dashboardTableNames, false);
        $dashboardTableParams = array();
        $dashboardTablePlaceholders = array();
        foreach ($dashboardTableNames as $index => $tableName) {
            $paramKey = 'dashboard_table_' . $index;
            $dashboardTablePlaceholders[] = ':' . $paramKey;
            $dashboardTableParams[$paramKey] = $tableName;
        }
        $dashboardTableRows = $this->db()->fetchAll(
            'SELECT TABLE_NAME
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN (' . implode(',', $dashboardTablePlaceholders) . ')',
            $dashboardTableParams
        );
        foreach ($dashboardTableRows as $row) {
            $tableName = (string) ($row['TABLE_NAME'] ?? '');
            if (array_key_exists($tableName, $dashboardTables)) {
                $dashboardTables[$tableName] = true;
                $this->tableExistsCache[$tableName] = true;
            }
        }
        $hasRechargeScoreTotalColumn = $this->columnExists($this->db(), 'users', 'recharge_score_total');
        $memberTotalRow = $this->db()->fetch(
            'SELECT COUNT(*) AS members_total,
                    ' . ($hasRechargeScoreTotalColumn ? 'COALESCE(SUM(recharge_score_total), 0)' : '0') . ' AS recharge_score_total
             FROM users',
            array()
        );
        $memberActivityStart = $yesterdayStart < $weekStart ? $yesterdayStart : $weekStart;
        $memberRoleMap = array_fill_keys(array('member', 'vip1', 'vip2', 'vip3', 'vip_annual', 'super_vip'), true);
        $memberActivityRows = $this->db()->fetchAll(
            'SELECT users.id,
                    users.username,
                    users.email,
                    users.created_at,
                    users.last_login_ip,
                    roles.role_key
             FROM users
             LEFT JOIN roles ON roles.id = users.role_id
             WHERE users.created_at >= :members_activity_start
               AND users.created_at < :members_activity_end
             ORDER BY users.created_at DESC',
            array(
                'members_activity_start' => $memberActivityStart,
                'members_activity_end' => $tomorrowStart,
            )
        );
        $membersTotal = (int) ($memberTotalRow['members_total'] ?? 0);
        $rechargeScoreTotal = max(0, (int) ($memberTotalRow['recharge_score_total'] ?? 0));
        $membersThisWeek = 0;
        $todayMemberCount = 0;
        $yesterdayMemberCount = 0;
        $todayMemberRows = array();
        foreach ($memberActivityRows as $row) {
            $createdAt = (string) ($row['created_at'] ?? '');
            if ($createdAt >= $weekStart && $createdAt < $tomorrowStart) {
                $membersThisWeek++;
            }

            $roleKey = (string) ($row['role_key'] ?? '');
            if (!isset($memberRoleMap[$roleKey])) {
                continue;
            }

            if ($createdAt >= $todayStart && $createdAt < $tomorrowStart) {
                $todayMemberCount++;
                if (count($todayMemberRows) < 12) {
                    $todayMemberRows[] = array(
                        'id' => (int) ($row['id'] ?? 0),
                        'username' => (string) ($row['username'] ?? ''),
                        'email' => (string) ($row['email'] ?? ''),
                        'created_at' => $createdAt,
                        'register_ip' => (string) ($row['last_login_ip'] ?? ''),
                    );
                }
            } elseif ($createdAt >= $yesterdayStart && $createdAt < $todayStart) {
                $yesterdayMemberCount++;
            }
        }

        if (!empty($todayMemberRows)) {
            $loginParams = array('login_status' => 'success');
            $loginPlaceholders = array();
            foreach ($todayMemberRows as $index => $row) {
                $paramKey = 'user_id_' . $index;
                $loginPlaceholders[] = ':' . $paramKey;
                $loginParams[$paramKey] = (int) ($row['id'] ?? 0);
            }
            $loginIpRows = $this->db()->fetchAll(
                'SELECT user_id, login_ip
                 FROM login_logs
                 WHERE login_status = :login_status
                   AND user_id IN (' . implode(',', $loginPlaceholders) . ')
                 ORDER BY id ASC',
                $loginParams
            );
            $loginIpByUserId = array();
            foreach ($loginIpRows as $row) {
                $userId = (int) ($row['user_id'] ?? 0);
                if ($userId > 0 && !isset($loginIpByUserId[$userId])) {
                    $loginIpByUserId[$userId] = (string) ($row['login_ip'] ?? '');
                }
            }
            foreach ($todayMemberRows as $index => $row) {
                $userId = (int) ($row['id'] ?? 0);
                if ($userId > 0 && isset($loginIpByUserId[$userId]) && $loginIpByUserId[$userId] !== '') {
                    $todayMemberRows[$index]['register_ip'] = $loginIpByUserId[$userId];
                }
            }
        }

        $visitsToday = 0;
        $visitsYesterday = 0;
        $uniqueVisitorsToday = 0;
        $uniqueVisitorsYesterday = 0;
        $frontVisitsToday = 0;
        $frontVisitsYesterday = 0;
        $frontVisitsGrowthPercent = 0.0;
        $returnVisitsToday = 0;
        $returnVisitsThisWeek = 0;
        $memberVisitsToday = 0;
        $memberVisitsYesterday = 0;
        $memberVisitsGrowthCount = 0;
        $homeVisitSummary = array(
            'total_count' => 0,
            'ip_count' => 0,
            'mobile_count' => 0,
            'desktop_count' => 0,
        );
        $homeVisitRows = array();
        $dashboardVisitorKey = static function (array $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId > 0) {
                return 'u:' . $userId;
            }

            $ipAddress = trim((string) ($row['ip_address'] ?? ''));
            $userAgent = trim((string) ($row['user_agent'] ?? ''));

            return 'a:' . ($ipAddress !== '' ? $ipAddress : '-') . '|' . $userAgent;
        };
        $dashboardVisitorKeySet = static function (array $rows) use ($dashboardVisitorKey) {
            $keys = array();
            foreach ($rows as $row) {
                $key = $dashboardVisitorKey(is_array($row) ? $row : array());
                if ($key !== '') {
                    $keys[$key] = true;
                }
            }

            return $keys;
        };
        $dashboardUserIdCount = static function (array $rows) {
            $ids = array();
            foreach ($rows as $row) {
                $userId = (int) ($row['user_id'] ?? 0);
                if ($userId > 0) {
                    $ids[$userId] = true;
                }
            }

            return count($ids);
        };
        if ($dashboardTables['page_views']) {
            $dailyVisitRows = $this->db()->fetchAll(
                'SELECT viewed_on,
                        COUNT(*) AS total_count,
                        COUNT(DISTINCT ip_address) AS unique_count
                 FROM page_views
                 WHERE viewed_on IN (:today_viewed_on, :yesterday_viewed_on)
                 GROUP BY viewed_on',
                array(
                    'today_viewed_on' => $today,
                    'yesterday_viewed_on' => $yesterday,
                )
            );
            foreach ($dailyVisitRows as $row) {
                $viewedOn = (string) ($row['viewed_on'] ?? '');
                if ($viewedOn === $today) {
                    $visitsToday = (int) ($row['total_count'] ?? 0);
                    $uniqueVisitorsToday = (int) ($row['unique_count'] ?? 0);
                } elseif ($viewedOn === $yesterday) {
                    $visitsYesterday = (int) ($row['total_count'] ?? 0);
                    $uniqueVisitorsYesterday = (int) ($row['unique_count'] ?? 0);
                }
            }

            $frontHomeVisitRows = $this->db()->fetchAll(
                "SELECT page_views.viewed_on,
                        COALESCE(page_views.user_id, 0) AS user_id,
                        page_views.ip_address,
                        COALESCE(page_views.user_agent, '') AS user_agent
                 FROM page_views
                 WHERE page_views.route_name IN ('front_macau', 'front_hongkong')
                   AND page_views.created_at >= :retention_start
                   AND page_views.created_at < :tomorrow_start
                 GROUP BY page_views.viewed_on, COALESCE(page_views.user_id, 0), page_views.ip_address, COALESCE(page_views.user_agent, '')",
                array(
                    'retention_start' => $visitRetentionStart,
                    'tomorrow_start' => $tomorrowStart,
                )
            );
            $todayHomeVisitRows = array();
            $yesterdayHomeVisitRows = array();
            $priorHomeVisitRows = array();
            $weekHomeVisitRows = array();
            $weekStartDate = substr($weekStart, 0, 10);
            foreach ($frontHomeVisitRows as $row) {
                $viewedOn = trim((string) ($row['viewed_on'] ?? ''));
                if ($viewedOn === $today) {
                    $todayHomeVisitRows[] = $row;
                } elseif ($viewedOn === $yesterday) {
                    $yesterdayHomeVisitRows[] = $row;
                }

                if ($viewedOn !== '' && $viewedOn < $today) {
                    $priorHomeVisitRows[] = $row;
                }
                if ($viewedOn !== '' && $viewedOn >= $weekStartDate && $viewedOn <= $today) {
                    $weekHomeVisitRows[] = $row;
                }
            }

            $frontVisitsTodayKeys = $dashboardVisitorKeySet($todayHomeVisitRows);
            $frontVisitsYesterdayKeys = $dashboardVisitorKeySet($yesterdayHomeVisitRows);
            $frontVisitsPriorKeys = $dashboardVisitorKeySet($priorHomeVisitRows);
            $frontVisitsToday = count($frontVisitsTodayKeys);
            $frontVisitsYesterday = count($frontVisitsYesterdayKeys);
            $frontVisitsGrowthPercent = $frontVisitsYesterday > 0
                ? round((($frontVisitsToday - $frontVisitsYesterday) / $frontVisitsYesterday) * 100, 1)
                : ($frontVisitsToday > 0 ? 100.0 : 0.0);
            $returnVisitsToday = count(array_intersect_key($frontVisitsTodayKeys, $frontVisitsPriorKeys));

            $weekVisitDaysByKey = array();
            foreach ($weekHomeVisitRows as $homeRow) {
                $key = $dashboardVisitorKey(is_array($homeRow) ? $homeRow : array());
                $viewedOn = trim((string) ($homeRow['viewed_on'] ?? ''));
                if ($key !== '' && $viewedOn !== '') {
                    if (!isset($weekVisitDaysByKey[$key])) {
                        $weekVisitDaysByKey[$key] = array();
                    }
                    $weekVisitDaysByKey[$key][$viewedOn] = true;
                }
            }
            foreach ($weekVisitDaysByKey as $visitDays) {
                if (count($visitDays) > 1) {
                    $returnVisitsThisWeek++;
                }
            }

            $homeVisitIpSet = array();
            $homeVisitSummaryKeys = array();
            foreach (array_merge($priorHomeVisitRows, $todayHomeVisitRows) as $homeRow) {
                $key = $dashboardVisitorKey(is_array($homeRow) ? $homeRow : array());
                if ($key === '' || isset($homeVisitSummaryKeys[$key])) {
                    continue;
                }

                $deviceLabel = $this->dashboardSourceDeviceLabel($homeRow['user_agent'] ?? '');
                $homeVisitSummaryKeys[$key] = true;
                $homeVisitSummary['total_count']++;
                $ipAddress = trim((string) ($homeRow['ip_address'] ?? ''));
                if ($ipAddress !== '') {
                    $homeVisitIpSet[$ipAddress] = true;
                }
                if ($deviceLabel === '电脑') {
                    $homeVisitSummary['desktop_count']++;
                } else {
                    $homeVisitSummary['mobile_count']++;
                }
            }
            $homeVisitSummary['ip_count'] = count($homeVisitIpSet);

            $memberVisitsToday = $dashboardUserIdCount($todayHomeVisitRows);
            $memberVisitsYesterday = $dashboardUserIdCount($yesterdayHomeVisitRows);
            $memberVisitsGrowthCount = $memberVisitsToday - $memberVisitsYesterday;

            $homeVisitRecentLimit = 800;
            $homeVisitRecentRows = $this->db()->fetchAll(
                "SELECT page_views.viewed_on,
                        page_views.created_at,
                        page_views.referer,
                        page_views.path_name,
                        page_views.ip_address,
                        COALESCE(page_views.province_name, '') AS province_name,
                        COALESCE(page_views.city_name, '') AS city_name,
                        COALESCE(page_views.user_agent, '') AS user_agent
                 FROM page_views
                 WHERE page_views.route_name IN ('front_macau', 'front_hongkong')
                   AND page_views.created_at >= :retention_start
                 ORDER BY page_views.created_at DESC
                 LIMIT " . $homeVisitRecentLimit,
                array(
                    'retention_start' => $visitRetentionStart,
                )
            );
            $homeVisitSourceRows = array();
            foreach ($homeVisitRecentRows as $row) {
                $sourceKey = (string) ($row['viewed_on'] ?? '')
                    . '|'
                    . (string) ($row['ip_address'] ?? '')
                    . '|'
                    . (string) ($row['user_agent'] ?? '');
                if (!isset($homeVisitSourceRows[$sourceKey])) {
                    $homeVisitSourceRows[$sourceKey] = array(
                        'created_at' => '',
                        'referer' => '',
                        'path_name' => '',
                        'ip_address' => '',
                        'province_name' => '',
                        'city_name' => '',
                        'user_agent' => '',
                    );
                }
                foreach (array('created_at', 'referer', 'path_name', 'ip_address', 'province_name', 'city_name', 'user_agent') as $field) {
                    $fieldValue = (string) ($row[$field] ?? '');
                    if ($fieldValue > (string) ($homeVisitSourceRows[$sourceKey][$field] ?? '')) {
                        $homeVisitSourceRows[$sourceKey][$field] = $fieldValue;
                    }
                }
            }
            if (count($homeVisitSourceRows) < 30 && count($homeVisitRecentRows) >= $homeVisitRecentLimit) {
                $homeVisitSourceRows = $this->db()->fetchAll(
                    "SELECT MAX(page_views.created_at) AS created_at,
                            MAX(page_views.referer) AS referer,
                            MAX(page_views.path_name) AS path_name,
                            MAX(page_views.ip_address) AS ip_address,
                            MAX(COALESCE(page_views.province_name, '')) AS province_name,
                            MAX(COALESCE(page_views.city_name, '')) AS city_name,
                            MAX(COALESCE(page_views.user_agent, '')) AS user_agent
                     FROM page_views
                     WHERE page_views.route_name IN ('front_macau', 'front_hongkong')
                       AND page_views.created_at >= :retention_start
                     GROUP BY page_views.viewed_on, page_views.ip_address, COALESCE(page_views.user_agent, '')
                     ORDER BY MAX(page_views.created_at) DESC
                     LIMIT 30",
                    array(
                        'retention_start' => $visitRetentionStart,
                    )
                );
            }
            $homeVisitRawRows = array_values($homeVisitSourceRows);
            usort($homeVisitRawRows, static function (array $left, array $right) {
                return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
            });
            $homeVisitRawRows = array_slice($homeVisitRawRows, 0, 30);

            foreach ($homeVisitRawRows as $homeRow) {
                $ipAddress = trim((string) ($homeRow['ip_address'] ?? '')) !== '' ? (string) $homeRow['ip_address'] : '-';
                $provinceName = trim((string) ($homeRow['province_name'] ?? ''));
                $cityName = trim((string) ($homeRow['city_name'] ?? ''));
                $sourceLocation = $this->dashboardSourceLocationLabels($ipAddress, $provinceName, $cityName);
                $homeVisitRows[] = array(
                    'source_url' => $this->dashboardSourceReferer($homeRow['referer'] ?? ''),
                    'visit_url' => $this->dashboardSourcePath($homeRow['path_name'] ?? ''),
                    'ip_address' => $ipAddress,
                    'province_name' => $sourceLocation['province'],
                    'city_name' => $sourceLocation['city'],
                    'visited_at' => $homeRow['created_at'] ?? '',
                    'device_label' => $this->dashboardSourceDeviceLabel($homeRow['user_agent'] ?? ''),
                );
            }
        }

        $postCountRow = $this->db()->fetch(
            "SELECT COALESCE(SUM(CASE WHEN created_at >= :today_count_start AND created_at < :today_count_end THEN 1 ELSE 0 END), 0) AS today_count,
                    COALESCE(SUM(CASE WHEN created_at >= :yesterday_count_start AND created_at < :yesterday_count_end THEN 1 ELSE 0 END), 0) AS yesterday_count
             FROM posts
             WHERE created_at >= :range_start
               AND created_at < :range_end
               AND status <> :deleted_status",
            array(
                'today_count_start' => $todayStart,
                'today_count_end' => $tomorrowStart,
                'yesterday_count_start' => $yesterdayStart,
                'yesterday_count_end' => $todayStart,
                'range_start' => $yesterdayStart,
                'range_end' => $tomorrowStart,
                'deleted_status' => 'deleted',
            )
        );
        $postsToday = (int) ($postCountRow['today_count'] ?? 0);
        $postsYesterday = (int) ($postCountRow['yesterday_count'] ?? 0);

        $parseRechargeScoreAmount = static function (array $row) {
            $payload = json_decode((string) ($row['request_data'] ?? ''), true);
            $payload = is_array($payload) ? $payload : array();
            $amount = isset($payload['score_amount']) ? (int) $payload['score_amount'] : 0;
            if ($amount === 0 && preg_match('/=>\s*([+-]?\d+)/u', (string) ($row['summary'] ?? ''), $matches)) {
                $amount = (int) $matches[1];
            }

            return $amount > 0 ? $amount : 0;
        };
        $rechargeScoreToday = 0;
        $rechargeScoreYesterday = 0;
        $addRechargeScoreByCreatedAt = static function ($createdAt, $amount) use (&$rechargeScoreToday, &$rechargeScoreYesterday, $yesterdayStart, $todayStart, $tomorrowStart) {
            $createdAt = (string) $createdAt;
            $amount = (int) $amount;
            if ($amount <= 0) {
                return;
            }
            if ($createdAt >= $todayStart && $createdAt < $tomorrowStart) {
                $rechargeScoreToday += $amount;
            } elseif ($createdAt >= $yesterdayStart && $createdAt < $todayStart) {
                $rechargeScoreYesterday += $amount;
            }
        };
        $hasOperationLogsTable = $dashboardTables['admin_operation_logs'];
        $hasAdminLogsTable = $dashboardTables['admin_logs'];
        if ($hasOperationLogsTable) {
            $rechargeOperationRows = $this->db()->fetchAll(
                'SELECT summary, request_data, created_at
                 FROM admin_operation_logs
                 WHERE module = :module
                   AND action = :action
                   AND target_type = :target_type
                   AND created_at >= :range_start
                   AND created_at < :range_end',
                array(
                    'module' => 'users',
                    'action' => 'score',
                    'target_type' => 'user',
                    'range_start' => $yesterdayStart,
                    'range_end' => $tomorrowStart,
                )
            );
            foreach ($rechargeOperationRows as $row) {
                $addRechargeScoreByCreatedAt($row['created_at'] ?? '', $parseRechargeScoreAmount($row));
            }
        }
        if ($hasAdminLogsTable) {
            $legacyRechargeSql = 'SELECT admin_logs.description AS summary, admin_logs.created_at
                 FROM admin_logs
                 WHERE admin_logs.module_name = :module
                   AND admin_logs.action_name = :action
                   AND admin_logs.target_type = :target_type
                   AND admin_logs.target_id <> \'\'
                   AND admin_logs.created_at >= :range_start
                   AND admin_logs.created_at < :range_end';
            if ($hasOperationLogsTable) {
                $legacyRechargeSql .= ' AND NOT EXISTS (
                       SELECT 1
                       FROM admin_operation_logs
                       WHERE admin_operation_logs.module = :operation_module
                         AND admin_operation_logs.action = :operation_action
                         AND admin_operation_logs.target_type = :operation_target_type
                         AND admin_operation_logs.target_id = CAST(admin_logs.target_id AS UNSIGNED)
                         AND ABS(TIMESTAMPDIFF(SECOND, admin_operation_logs.created_at, admin_logs.created_at)) <= 2
                       LIMIT 1
                   )';
            }
            $legacyRechargeParams = array(
                'module' => 'members',
                'action' => 'charge',
                'target_type' => 'user',
                'range_start' => $yesterdayStart,
                'range_end' => $tomorrowStart,
            );
            if ($hasOperationLogsTable) {
                $legacyRechargeParams['operation_module'] = 'users';
                $legacyRechargeParams['operation_action'] = 'score';
                $legacyRechargeParams['operation_target_type'] = 'user';
            }
            $legacyRechargeRows = $this->db()->fetchAll(
                $legacyRechargeSql,
                $legacyRechargeParams
            );
            foreach ($legacyRechargeRows as $row) {
                $addRechargeScoreByCreatedAt($row['created_at'] ?? '', $parseRechargeScoreAmount($row));
            }
        }

        $purchasesToday = 0;
        $purchasePostsToday = 0;
        $purchaseScoreToday = 0;
        $repeatPurchasesToday = 0;
        if ($dashboardTables['purchases']) {
            $purchaseRow = $this->db()->fetch(
                'SELECT COUNT(*) AS total_count,
                        COUNT(DISTINCT post_id) AS post_count,
                        COALESCE(SUM(price), 0) AS score_count,
                        GREATEST(
                            COALESCE(SUM(CASE WHEN user_id IS NOT NULL AND user_id > 0 THEN 1 ELSE 0 END), 0)
                            - COUNT(DISTINCT CASE WHEN user_id IS NOT NULL AND user_id > 0 THEN user_id END),
                            0
                        ) AS repeat_count
                 FROM purchases
                 WHERE created_at >= :today_start
                   AND created_at < :tomorrow_start',
                array(
                    'today_start' => $todayStart,
                    'tomorrow_start' => $tomorrowStart,
                )
            );
            $purchasesToday = (int) ($purchaseRow['total_count'] ?? 0);
            $purchasePostsToday = (int) ($purchaseRow['post_count'] ?? 0);
            $purchaseScoreToday = (int) ($purchaseRow['score_count'] ?? 0);
            $repeatPurchasesToday = (int) ($purchaseRow['repeat_count'] ?? 0);
        }

        $conversionVisitorsToday = $frontVisitsToday > 0 ? $frontVisitsToday : $uniqueVisitorsToday;
        $conversionVisitorsYesterday = $frontVisitsYesterday > 0 ? $frontVisitsYesterday : $uniqueVisitorsYesterday;
        $trafficConversionToday = $conversionVisitorsToday > 0
            ? round(($todayMemberCount / $conversionVisitorsToday) * 100, 1)
            : 0.0;
        $trafficConversionYesterday = $conversionVisitorsYesterday > 0
            ? round(($yesterdayMemberCount / $conversionVisitorsYesterday) * 100, 1)
            : 0.0;
        $visitsGrowthPercent = $visitsYesterday > 0
            ? round((($visitsToday - $visitsYesterday) / $visitsYesterday) * 100, 1)
            : ($visitsToday > 0 ? 100.0 : 0.0);
        $trafficConversionDiffPercent = round($trafficConversionToday - $trafficConversionYesterday, 1);
        $dashboardQuickMetrics = array(
            'open_threads' => 0,
            'pending_resets' => 0,
        );
        $dashboardQuickMetricSql = array();
        $dashboardQuickMetricParams = array();
        if ($dashboardTables['customer_service_sessions']) {
            $dashboardQuickMetricSql[] = "SELECT 'open_threads' AS metric_name,
                    COUNT(*) AS total_count
                 FROM customer_service_sessions
                 WHERE status IN (:open_threads_waiting_status, :open_threads_open_status)";
            $dashboardQuickMetricParams['open_threads_waiting_status'] = 'waiting';
            $dashboardQuickMetricParams['open_threads_open_status'] = 'open';
        }
        if ($dashboardTables['password_reset_requests']) {
            $dashboardQuickMetricSql[] = "SELECT 'pending_resets' AS metric_name,
                    COUNT(*) AS total_count
                 FROM password_reset_requests
                 WHERE status = :pending_resets_status";
            $dashboardQuickMetricParams['pending_resets_status'] = 'pending';
        }
        if (!empty($dashboardQuickMetricSql)) {
            $dashboardQuickMetricRows = $this->db()->fetchAll(
                implode(' UNION ALL ', $dashboardQuickMetricSql),
                $dashboardQuickMetricParams
            );
            foreach ($dashboardQuickMetricRows as $row) {
                $metricName = (string) ($row['metric_name'] ?? '');
                if (array_key_exists($metricName, $dashboardQuickMetrics)) {
                    $dashboardQuickMetrics[$metricName] = (int) ($row['total_count'] ?? 0);
                }
            }
        }

        $stats = array(
            'visits_today' => $visitsToday,
            'visits_yesterday' => $visitsYesterday,
            'visits_growth_percent' => $visitsGrowthPercent,
            'front_visits_today' => $frontVisitsToday,
            'front_visits_yesterday' => $frontVisitsYesterday,
            'front_visits_growth_percent' => $frontVisitsGrowthPercent,
            'return_visits_today' => $returnVisitsToday,
            'return_visits_this_week' => $returnVisitsThisWeek,
            'member_visits_today' => $memberVisitsToday,
            'member_visits_yesterday' => $memberVisitsYesterday,
            'member_visits_growth_count' => $memberVisitsGrowthCount,
            'members_total' => $membersTotal,
            'members_this_week' => $membersThisWeek,
            'posts_today' => $postsToday,
            'posts_yesterday' => $postsYesterday,
            'posts_growth_count' => $postsToday - $postsYesterday,
            'traffic_conversion_rate' => $trafficConversionToday,
            'traffic_conversion_rate_yesterday' => $trafficConversionYesterday,
            'traffic_conversion_diff_percent' => $trafficConversionDiffPercent,
            'recharge_score_total' => $rechargeScoreTotal,
            'recharge_score_today' => $rechargeScoreToday,
            'recharge_score_yesterday' => $rechargeScoreYesterday,
            'purchases_today' => $purchasesToday,
            'purchase_posts_today' => $purchasePostsToday,
            'purchase_score_today' => $purchaseScoreToday,
            'repeat_purchases_today' => $repeatPurchasesToday,
            'open_threads' => $dashboardQuickMetrics['open_threads'],
            'pending_resets' => $dashboardQuickMetrics['pending_resets'],
            'members_today_count' => $todayMemberCount,
            'members_today_list' => is_array($todayMemberRows) ? $todayMemberRows : array(),
            'home_visit_summary' => $homeVisitSummary,
            'home_visit_rows' => $homeVisitRows,
        );

        $this->app->cache()->put($cacheKey, $stats);
        $this->dashboardStatsRequestCache = $stats;

        return $stats;
    }

    protected function dashboardSourceReferer($referer)
    {
        $value = trim((string) $referer);

        return $value !== '' ? $value : '直接访问';
    }

    protected function dashboardSourcePath($path)
    {
        $value = trim((string) $path);
        if ($value === '') {
            return '/';
        }

        if (stripos($value, 'http://') === 0 || stripos($value, 'https://') === 0 || strpos($value, '/') === 0) {
            return $value;
        }

        return '/' . ltrim($value, '/');
    }

    protected function dashboardSourceProvinceLabel($ipAddress)
    {
        $location = $this->dashboardSourceLocationLabels($ipAddress);

        return (string) ($location['province'] ?? '未知省份');
    }

    protected function dashboardSourceCityLabel($ipAddress)
    {
        $location = $this->dashboardSourceLocationLabels($ipAddress);

        return (string) ($location['city'] ?? '未知城市');
    }

    protected function dashboardSourceLocationLabels($ipAddress, $provinceName = '', $cityName = '')
    {
        $value = trim((string) $ipAddress);
        $provinceName = trim((string) $provinceName);
        $cityName = trim((string) $cityName);

        if (!$this->dashboardLocationLabelIsUnknown($provinceName) && !$this->dashboardLocationLabelIsUnknown($cityName)) {
            return array('province' => $provinceName, 'city' => $cityName);
        }

        if ($value === '' || $value === '-') {
            if ($this->dashboardLocationLabelIsUnknown($cityName) && !$this->dashboardLocationLabelIsUnknown($provinceName)) {
                $cityName = '地区未细分';
            }

            return array(
                'province' => !$this->dashboardLocationLabelIsUnknown($provinceName) ? $provinceName : '未知省份',
                'city' => !$this->dashboardLocationLabelIsUnknown($cityName) ? $cityName : '未知城市',
            );
        }

        $location = Security::ipLocationFromAddress($value);
        $resolvedProvince = trim((string) ($location['province'] ?? ''));
        $resolvedCity = trim((string) ($location['city'] ?? ''));

        if ($this->dashboardLocationLabelIsUnknown($provinceName) && !$this->dashboardLocationLabelIsUnknown($resolvedProvince)) {
            $provinceName = $resolvedProvince;
        }
        if ($this->dashboardLocationLabelIsUnknown($cityName) && !$this->dashboardLocationLabelIsUnknown($resolvedCity)) {
            $cityName = $resolvedCity;
        }
        if ($this->dashboardLocationLabelIsUnknown($cityName) && !$this->dashboardLocationLabelIsUnknown($provinceName)) {
            $cityName = '地区未细分';
        }

        return array(
            'province' => !$this->dashboardLocationLabelIsUnknown($provinceName) ? $provinceName : '未知省份',
            'city' => !$this->dashboardLocationLabelIsUnknown($cityName) ? $cityName : '未知城市',
        );
    }

    protected function dashboardLocationLabelIsUnknown($label)
    {
        $value = strtolower(trim((string) $label));
        if ($value === '') {
            return true;
        }

        return in_array($value, array('未知', '未知地区', '未知省份', '未知城市', 'unknown', 'unknown province', 'unknown city', '-'), true);
    }

    protected function dashboardSourceDeviceLabel($userAgent)
    {
        $agent = strtolower(trim((string) $userAgent));

        if (strpos($agent, 'android') !== false) {
            return '安卓';
        }

        if (preg_match('/iphone|ipad|ipod|ios/', $agent)) {
            return '苹果';
        }

        return '电脑';
    }

    public function installSnapshot()
    {
        $config = $this->app->databaseConfig();
        $lockPath = $this->app->basePath('storage/install.lock');
        $latestRecord = null;

        if ($this->tableExists($this->db(), 'install_records')) {
            $latestRecord = $this->db()->fetch('SELECT * FROM install_records ORDER BY id DESC LIMIT 1');
        }

        return array(
            'database' => is_array($config) ? $config : array(),
            'lock_exists' => is_file($lockPath),
            'lock_time' => is_file($lockPath) ? date('Y-m-d H:i:s', filemtime($lockPath)) : '',
            'latest_record' => $latestRecord,
        );
    }

    public function listAdminLoginLogs(array $filters = array())
    {
        $normalizedFilters = array(
            'keyword' => trim((string) ($filters['keyword'] ?? '')),
            'status' => trim((string) ($filters['status'] ?? '')),
            'device' => trim((string) ($filters['device'] ?? '')),
            'date_from' => trim((string) ($filters['date_from'] ?? '')),
            'date_to' => trim((string) ($filters['date_to'] ?? '')),
            'page_no' => max(1, (int) ($filters['page_no'] ?? 1)),
        );
        $cacheKey = 'admin_login_log_page_v2_' . md5(json_encode($normalizedFilters));
        if (isset($this->adminLoginLogPageRequestCache[$cacheKey])) {
            return $this->adminLoginLogPageRequestCache[$cacheKey];
        }

        $cachedPage = $this->app->cache()->get($cacheKey, null, 10);
        if (is_array($cachedPage)) {
            $this->adminLoginLogPageRequestCache[$cacheKey] = $cachedPage;

            return $cachedPage;
        }

        $sql = 'SELECT admin_login_logs.*, admin_users.real_name
                FROM admin_login_logs
                LEFT JOIN admin_users ON admin_users.id = admin_login_logs.admin_id
                WHERE 1 = 1';
        $params = array();
        $keyword = (string) $normalizedFilters['keyword'];
        $status = (string) $normalizedFilters['status'];
        $device = (string) $normalizedFilters['device'];
        $dateFrom = (string) $normalizedFilters['date_from'];
        $dateTo = (string) $normalizedFilters['date_to'];

        if ($keyword !== '') {
            $sql .= ' AND (admin_login_logs.username LIKE :keyword_username OR admin_login_logs.ip LIKE :keyword_ip OR admin_login_logs.fail_reason LIKE :keyword_fail_reason)';
            $params['keyword_username'] = '%' . $keyword . '%';
            $params['keyword_ip'] = '%' . $keyword . '%';
            $params['keyword_fail_reason'] = '%' . $keyword . '%';
        }

        if ($status !== '' && in_array($status, array('0', '1'), true)) {
            $sql .= ' AND admin_login_logs.status = :status';
            $params['status'] = (int) $status;
        }

        if ($device !== '' && in_array($device, array('desktop', 'mobile'), true)) {
            $sql .= ' AND admin_login_logs.device = :device';
            $params['device'] = $device;
        }

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $sql .= ' AND admin_login_logs.login_at >= :date_from';
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $sql .= ' AND admin_login_logs.login_at <= :date_to';
            $params['date_to'] = $dateTo . ' 23:59:59';
        }

        $page = $this->paginateAdminQuery(
            'SELECT COUNT(*) AS total_count FROM (' . $sql . ') AS login_count_table',
            $sql . ' ORDER BY admin_login_logs.login_at DESC',
            $params,
            (int) $normalizedFilters['page_no'],
            20
        );
        $page['items'] = $this->enrichAdminLoginLogAreaRows((array) ($page['items'] ?? array()));
        $this->adminLoginLogPageRequestCache[$cacheKey] = $page;
        $this->app->cache()->put($cacheKey, $page);

        return $page;
    }

    protected function enrichAdminLoginLogAreaRows(array $rows)
    {
        foreach ($rows as $index => $row) {
            if (!is_array($row)) {
                continue;
            }

            $rows[$index]['area'] = $this->loginAreaDisplayLabel($row['ip'] ?? '', $row['area'] ?? '');
        }

        return $rows;
    }

    public function listAdminOperationLogsPage(array $filters = array())
    {
        $normalizedFilters = array(
            'module' => trim((string) ($filters['module'] ?? '')),
            'keyword' => trim((string) ($filters['keyword'] ?? '')),
            'date_from' => trim((string) ($filters['date_from'] ?? '')),
            'date_to' => trim((string) ($filters['date_to'] ?? '')),
            'page_no' => max(1, (int) ($filters['page_no'] ?? 1)),
        );
        $cacheKey = 'admin_operation_log_page_' . md5(json_encode($normalizedFilters));
        if (isset($this->adminOperationLogPageRequestCache[$cacheKey])) {
            return $this->adminOperationLogPageRequestCache[$cacheKey];
        }

        $cachedPage = $this->app->cache()->get($cacheKey, null, 10);
        if (is_array($cachedPage)) {
            $this->adminOperationLogPageRequestCache[$cacheKey] = $cachedPage;

            return $cachedPage;
        }

        $sql = 'SELECT admin_operation_logs.*, admin_users.username
                FROM admin_operation_logs
                LEFT JOIN admin_users ON admin_users.id = admin_operation_logs.admin_id
                WHERE 1 = 1';
        $params = array();
        $module = (string) $normalizedFilters['module'];
        $keyword = (string) $normalizedFilters['keyword'];
        $dateFrom = (string) $normalizedFilters['date_from'];
        $dateTo = (string) $normalizedFilters['date_to'];

        if ($module !== '') {
            $sql .= ' AND admin_operation_logs.module = :module';
            $params['module'] = $module;
        }

        if ($keyword !== '') {
            $sql .= ' AND (admin_operation_logs.summary LIKE :keyword_summary OR admin_operation_logs.request_path LIKE :keyword_path OR admin_users.username LIKE :keyword_user)';
            $params['keyword_summary'] = '%' . $keyword . '%';
            $params['keyword_path'] = '%' . $keyword . '%';
            $params['keyword_user'] = '%' . $keyword . '%';
        }

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $sql .= ' AND admin_operation_logs.created_at >= :date_from';
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $sql .= ' AND admin_operation_logs.created_at <= :date_to';
            $params['date_to'] = $dateTo . ' 23:59:59';
        }

        $page = $this->paginateAdminQuery(
            'SELECT COUNT(*) AS total_count FROM (' . $sql . ') AS operation_count_table',
            $sql . ' ORDER BY admin_operation_logs.created_at DESC',
            $params,
            (int) $normalizedFilters['page_no'],
            20
        );
        $this->adminOperationLogPageRequestCache[$cacheKey] = $page;
        $this->app->cache()->put($cacheKey, $page);

        return $page;
    }

    public function listAdminSystemLogsPage(array $filters = array())
    {
        if (!$this->tableExists($this->db(), 'system_logs')) {
            return $this->emptyPaginatedResult();
        }

        $normalizedFilters = array(
            'source' => trim((string) ($filters['source'] ?? '')),
            'keyword' => trim((string) ($filters['keyword'] ?? '')),
            'date_from' => trim((string) ($filters['date_from'] ?? '')),
            'date_to' => trim((string) ($filters['date_to'] ?? '')),
            'page_no' => max(1, (int) ($filters['page_no'] ?? 1)),
        );
        $cacheKey = 'admin_system_log_page_' . md5(json_encode($normalizedFilters));
        if (isset($this->adminSystemLogPageRequestCache[$cacheKey])) {
            return $this->adminSystemLogPageRequestCache[$cacheKey];
        }

        $cachedPage = $this->app->cache()->get($cacheKey, null, 10);
        if (is_array($cachedPage)) {
            $this->adminSystemLogPageRequestCache[$cacheKey] = $cachedPage;

            return $cachedPage;
        }

        $sql = 'SELECT * FROM system_logs WHERE 1 = 1';
        $params = array();
        $source = (string) $normalizedFilters['source'];
        $keyword = (string) $normalizedFilters['keyword'];
        $dateFrom = (string) $normalizedFilters['date_from'];
        $dateTo = (string) $normalizedFilters['date_to'];

        if ($source !== '') {
            $sql .= ' AND source_name = :source_name';
            $params['source_name'] = $source;
        }

        if ($keyword !== '') {
            $sql .= ' AND (message LIKE :keyword_message OR context_json LIKE :keyword_context)';
            $params['keyword_message'] = '%' . $keyword . '%';
            $params['keyword_context'] = '%' . $keyword . '%';
        }

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $sql .= ' AND created_at >= :date_from';
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $sql .= ' AND created_at <= :date_to';
            $params['date_to'] = $dateTo . ' 23:59:59';
        }

        $page = $this->paginateAdminQuery(
            'SELECT COUNT(*) AS total_count FROM (' . $sql . ') AS system_log_count_table',
            $sql . ' ORDER BY created_at DESC, id DESC',
            $params,
            (int) $normalizedFilters['page_no'],
            20
        );
        $this->adminSystemLogPageRequestCache[$cacheKey] = $page;
        $this->app->cache()->put($cacheKey, $page);

        return $page;
    }

    public function listExceptionLogs(array $filters = array())
    {
        if (!$this->tableExists($this->db(), 'system_exception_logs')) {
            return $this->emptyPaginatedResult();
        }

        $normalizedFilters = array(
            'level' => trim((string) ($filters['level'] ?? '')),
            'module' => trim((string) ($filters['module'] ?? '')),
            'keyword' => trim((string) ($filters['keyword'] ?? '')),
            'date_from' => trim((string) ($filters['date_from'] ?? '')),
            'date_to' => trim((string) ($filters['date_to'] ?? '')),
            'page_no' => max(1, (int) ($filters['page_no'] ?? 1)),
        );
        $cacheKey = 'admin_exception_log_page_' . md5(json_encode($normalizedFilters));
        if (isset($this->adminExceptionLogPageRequestCache[$cacheKey])) {
            return $this->adminExceptionLogPageRequestCache[$cacheKey];
        }

        $cachedPage = $this->app->cache()->get($cacheKey, null, 10);
        if (is_array($cachedPage)) {
            $this->adminExceptionLogPageRequestCache[$cacheKey] = $cachedPage;

            return $cachedPage;
        }

        $sql = 'SELECT * FROM system_exception_logs WHERE 1 = 1';
        $params = array();
        $level = (string) $normalizedFilters['level'];
        $module = (string) $normalizedFilters['module'];
        $keyword = (string) $normalizedFilters['keyword'];
        $dateFrom = (string) $normalizedFilters['date_from'];
        $dateTo = (string) $normalizedFilters['date_to'];

        if ($level !== '') {
            $sql .= ' AND level = :level';
            $params['level'] = $level;
        }

        if ($module !== '') {
            $sql .= ' AND module = :module';
            $params['module'] = $module;
        }

        if ($keyword !== '') {
            $sql .= ' AND (message LIKE :keyword_message OR scene LIKE :keyword_scene OR request_path LIKE :keyword_path)';
            $params['keyword_message'] = '%' . $keyword . '%';
            $params['keyword_scene'] = '%' . $keyword . '%';
            $params['keyword_path'] = '%' . $keyword . '%';
        }

        if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
            $sql .= ' AND created_at >= :date_from';
            $params['date_from'] = $dateFrom . ' 00:00:00';
        }

        if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
            $sql .= ' AND created_at <= :date_to';
            $params['date_to'] = $dateTo . ' 23:59:59';
        }

        $page = $this->paginateAdminQuery(
            'SELECT COUNT(*) AS total_count FROM (' . $sql . ') AS exception_count_table',
            $sql . ' ORDER BY created_at DESC',
            $params,
            (int) $normalizedFilters['page_no'],
            20
        );
        $this->adminExceptionLogPageRequestCache[$cacheKey] = $page;
        $this->app->cache()->put($cacheKey, $page);

        return $page;
    }

    public function recentLoginLogs($limit = 8)
    {
        $limit = max(1, min(20, (int) $limit));
        if (isset($this->recentLoginLogsRequestCache[$limit])) {
            return $this->recentLoginLogsRequestCache[$limit];
        }

        $cacheKey = 'admin_dashboard_recent_login_logs_' . $limit;
        $cached = $this->app->cache()->get($cacheKey, null, 15);
        if (is_array($cached)) {
            $this->recentLoginLogsRequestCache[$limit] = $cached;

            return $cached;
        }

        $logs = $this->db()->fetchAll('SELECT * FROM admin_login_logs ORDER BY login_at DESC LIMIT ' . $limit);
        $this->app->cache()->put($cacheKey, $logs);
        $this->recentLoginLogsRequestCache[$limit] = $logs;

        return $logs;
    }

    public function recentOperationLogs($limit = 8)
    {
        $limit = max(1, min(20, (int) $limit));
        if (isset($this->recentOperationLogsRequestCache[$limit])) {
            return $this->recentOperationLogsRequestCache[$limit];
        }

        $cacheKey = 'admin_dashboard_recent_operation_logs_' . $limit;
        $cached = $this->app->cache()->get($cacheKey, null, 15);
        if (is_array($cached)) {
            $this->recentOperationLogsRequestCache[$limit] = $cached;

            return $cached;
        }

        $logs = $this->db()->fetchAll(
            'SELECT admin_operation_logs.*, admin_users.username
             FROM admin_operation_logs
             LEFT JOIN admin_users ON admin_users.id = admin_operation_logs.admin_id
             ORDER BY admin_operation_logs.created_at DESC
             LIMIT ' . $limit
        );
        $this->app->cache()->put($cacheKey, $logs);
        $this->recentOperationLogsRequestCache[$limit] = $logs;

        return $logs;
    }

    public function recordException(\Throwable $exception, $module, $scene, $operatorType = 'system', $operatorId = 0)
    {
        $this->writeManagedExceptionLog($exception, $module, $scene, $operatorType, $operatorId);
    }

    public function listManagedUsers(array $filters = array())
    {
        $page = $this->listManagedUsersPage($filters);

        return (array) ($page['items'] ?? array());
    }

    public function listManagedUsersPage(array $filters = array())
    {
        $memberRoleKeys = $this->app->users()->memberRoleKeys();
        $memberRoleSql = "'" . implode("','", array_map('addslashes', $memberRoleKeys)) . "'";
        $selectSql = 'SELECT users.*,
                             roles.role_key,
                             roles.role_name,
                             COALESCE(
                                 (
                                     SELECT NULLIF(login_logs.login_ip, \'\')
                                     FROM login_logs
                                     WHERE login_logs.user_id = users.id
                                       AND login_logs.login_status = :register_login_status_ip
                                     ORDER BY login_logs.id ASC
                                     LIMIT 1
                                 ),
                                 NULLIF(users.last_login_ip, \'\'),
                                 \'\'
                             ) AS register_ip,
                             COALESCE(
                                 (
                                     SELECT NULLIF(login_logs.login_province, \'\')
                                     FROM login_logs
                                     WHERE login_logs.user_id = users.id
                                       AND login_logs.login_status = :register_login_status_province
                                     ORDER BY login_logs.id ASC
                                     LIMIT 1
                                 ),
                                 NULLIF(users.login_province, \'\'),
                                 \'\'
                             ) AS register_province';
        $fromSql = ' FROM users
                     INNER JOIN roles ON roles.id = users.role_id
                     WHERE roles.role_key IN (' . $memberRoleSql . ')';
        $countParams = array();
        $listParams = array('register_login_status_ip' => 'success', 'register_login_status_province' => 'success');
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $roleKey = trim((string) ($filters['role_key'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $pageNo = max(1, (int) ($filters['page_no'] ?? 1));
        $perPage = max(20, min(60, (int) ($filters['per_page'] ?? 40)));

        if ($keyword !== '') {
            $fromSql .= ' AND (users.username LIKE :keyword_username OR users.email LIKE :keyword_email OR users.bio LIKE :keyword_bio)';
            $countParams['keyword_username'] = '%' . $keyword . '%';
            $countParams['keyword_email'] = '%' . $keyword . '%';
            $countParams['keyword_bio'] = '%' . $keyword . '%';
            $listParams['keyword_username'] = $countParams['keyword_username'];
            $listParams['keyword_email'] = $countParams['keyword_email'];
            $listParams['keyword_bio'] = $countParams['keyword_bio'];
        }

        if ($roleKey !== '' && in_array($roleKey, $memberRoleKeys, true)) {
            $fromSql .= ' AND roles.role_key = :role_key';
            $countParams['role_key'] = $roleKey;
            $listParams['role_key'] = $roleKey;
        }

        if (in_array($status, array('active', 'disabled'), true)) {
            $fromSql .= ' AND users.status = :status';
            $countParams['status'] = $status;
            $listParams['status'] = $status;
        }

        $totalRow = $this->db()->fetch('SELECT COUNT(*) AS total_count' . $fromSql, $countParams);
        $total = $totalRow ? (int) ($totalRow['total_count'] ?? 0) : 0;
        $pageCount = max(1, (int) ceil($total / $perPage));
        $pageNo = min($pageNo, $pageCount);
        $offset = ($pageNo - 1) * $perPage;
        $rows = $this->db()->fetchAll(
            $selectSql . $fromSql . ' ORDER BY users.created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset,
            $listParams
        );
        $rows = $this->enrichManagedUserRows($rows);

        return array(
            'items' => $rows,
            'total' => $total,
            'page_no' => $pageNo,
            'per_page' => $perPage,
            'page_count' => $pageCount,
        );
    }

    protected function enrichManagedUserRows(array $rows)
    {
        $pageViewLocations = $this->managedUserPageViewLocationFallbacks($rows);
        foreach ($rows as $index => $row) {
            $registerIp = trim((string) ($row['register_ip'] ?? ''));
            $lastLoginIp = trim((string) ($row['last_login_ip'] ?? ''));
            $province = trim((string) ($row['register_province'] ?? ''));
            $lastLoginProvince = trim((string) ($row['login_province'] ?? ''));
            $candidateIps = $this->managedUserLocationCandidateIps($registerIp, $lastLoginIp);
            $pageViewLocation = $this->managedUserPageViewLocation($pageViewLocations, (int) ($row['id'] ?? 0), $candidateIps);
            if ($this->managedUserLocationNeedsFallback($province)) {
                $province = $pageViewLocation['province'];
            }
            if ($this->managedUserLocationNeedsFallback($province)) {
                $province = $lastLoginProvince;
            }
            if ($this->managedUserLocationNeedsFallback($province)) {
                foreach ($candidateIps as $candidateIp) {
                    $province = $this->dashboardSourceProvinceLabel($candidateIp);
                    if (!$this->managedUserLocationNeedsFallback($province)) {
                        break;
                    }
                }
            }
            $city = $pageViewLocation['city'];
            if ($this->managedUserLocationNeedsFallback($city)) {
                foreach ($candidateIps as $candidateIp) {
                    $city = $this->dashboardSourceCityLabel($candidateIp);
                    if (!$this->managedUserLocationNeedsFallback($city)) {
                        break;
                    }
                }
            }
            if ($registerIp === '' && !empty($candidateIps)) {
                $registerIp = $candidateIps[0];
            }
            if ($this->managedUserLocationNeedsFallback($province)) {
                $province = '未知省份';
            }
            if ($this->managedUserLocationNeedsFallback($city)) {
                $city = '未知城市';
            }
            $carrier = $this->managedUserCarrierLabel($candidateIps);
            $rows[$index]['register_ip'] = $registerIp;
            $rows[$index]['register_province'] = $province;
            $rows[$index]['register_city'] = $city;
            $rows[$index]['register_carrier'] = $carrier;
            $rows[$index]['register_area_label'] = $province . ' / ' . $city . ' / ' . $carrier;
        }

        return $rows;
    }

    protected function managedUserPageViewLocationFallbacks(array $rows)
    {
        $fallbacks = array('users' => array(), 'ips' => array());
        if (empty($rows) || !$this->tableExists($this->db(), 'page_views')) {
            return $fallbacks;
        }

        $userIds = array();
        $ips = array();
        foreach ($rows as $row) {
            $userId = (int) ($row['id'] ?? 0);
            $ip = trim((string) ($row['register_ip'] ?? ''));
            if ($userId > 0) {
                $userIds[$userId] = $userId;
            }
            if ($ip !== '') {
                $ips[$ip] = $ip;
            }
            $lastLoginIp = trim((string) ($row['last_login_ip'] ?? ''));
            if ($lastLoginIp !== '') {
                $ips[$lastLoginIp] = $lastLoginIp;
            }
        }

        $where = array();
        $params = array();
        if (!empty($userIds)) {
            $placeholders = array();
            foreach (array_values($userIds) as $index => $userId) {
                $key = 'user_id_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $userId;
            }
            $where[] = 'user_id IN (' . implode(',', $placeholders) . ')';
        }
        if (!empty($ips)) {
            $placeholders = array();
            foreach (array_values($ips) as $index => $ip) {
                $key = 'ip_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $ip;
            }
            $where[] = 'ip_address IN (' . implode(',', $placeholders) . ')';
        }

        if (empty($where)) {
            return $fallbacks;
        }

        $pageViews = $this->db()->fetchAll(
            'SELECT user_id, ip_address, province_name, city_name
             FROM page_views
             WHERE (' . implode(' OR ', $where) . ')
             ORDER BY id DESC
             LIMIT 600',
            $params
        );

        foreach ($pageViews as $pageView) {
            $location = array(
                'province' => trim((string) ($pageView['province_name'] ?? '')),
                'city' => trim((string) ($pageView['city_name'] ?? '')),
            );
            if ($this->dashboardLocationLabelIsUnknown($location['province']) && $this->dashboardLocationLabelIsUnknown($location['city'])) {
                continue;
            }

            $userId = (int) ($pageView['user_id'] ?? 0);
            if ($userId > 0) {
                $fallbacks['users'][$userId] = $this->mergeManagedUserLocationFallback($fallbacks['users'][$userId] ?? array(), $location);
            }

            $ip = trim((string) ($pageView['ip_address'] ?? ''));
            if ($ip !== '') {
                $fallbacks['ips'][$ip] = $this->mergeManagedUserLocationFallback($fallbacks['ips'][$ip] ?? array(), $location);
            }
        }

        return $fallbacks;
    }

    protected function managedUserPageViewLocation(array $fallbacks, $userId, $ip)
    {
        $userId = (int) $userId;
        if ($userId > 0 && !empty($fallbacks['users'][$userId])) {
            return $fallbacks['users'][$userId];
        }

        $ips = is_array($ip) ? $ip : array($ip);
        foreach ($ips as $candidateIp) {
            $candidateIp = trim((string) $candidateIp);
            if ($candidateIp !== '' && !empty($fallbacks['ips'][$candidateIp])) {
                return $fallbacks['ips'][$candidateIp];
            }
        }

        return array('province' => '', 'city' => '');
    }

    protected function managedUserLocationCandidateIps($registerIp, $lastLoginIp)
    {
        $ips = array();
        foreach (array($registerIp, $lastLoginIp) as $ip) {
            $ip = trim((string) $ip);
            if ($ip === '' || isset($ips[$ip])) {
                continue;
            }
            $ips[$ip] = $ip;
        }

        return array_values($ips);
    }

    protected function managedUserCarrierLabel(array $candidateIps)
    {
        foreach ($candidateIps as $candidateIp) {
            $carrier = trim((string) Security::carrierFromIpAddress($candidateIp));
            if ($carrier !== '' && !in_array($carrier, array('未知运营商', '内网', '本地网络'), true)) {
                return $carrier;
            }
        }

        return '未知运营商';
    }

    protected function mergeManagedUserLocationFallback(array $current, array $incoming)
    {
        $province = trim((string) ($current['province'] ?? ''));
        $city = trim((string) ($current['city'] ?? ''));
        $incomingProvince = trim((string) ($incoming['province'] ?? ''));
        $incomingCity = trim((string) ($incoming['city'] ?? ''));

        if ($this->managedUserLocationNeedsFallback($province) && !$this->managedUserLocationNeedsFallback($incomingProvince)) {
            $province = $incomingProvince;
        }
        if ($this->managedUserLocationNeedsFallback($city) && !$this->managedUserLocationNeedsFallback($incomingCity)) {
            $city = $incomingCity;
        }

        return array('province' => $province, 'city' => $city);
    }

    protected function managedUserLocationNeedsFallback($label)
    {
        if ($this->dashboardLocationLabelIsUnknown($label)) {
            return true;
        }

        $value = strtolower(trim((string) $label));

        return in_array($value, array('内网地址', '内网访问', '本地网络', '本地访问', 'private network', 'local network', 'localhost'), true);
    }

    public function managedUserSelectOptions($selectedUserId = 0, $limit = 120)
    {
        $limit = max(20, min(120, (int) $limit));
        $rows = $this->managedUserSelectBaseOptions($limit);

        $selectedUserId = (int) $selectedUserId;
        if ($selectedUserId > 0) {
            $hasSelected = false;
            foreach ($rows as $row) {
                if ((int) ($row['id'] ?? 0) === $selectedUserId) {
                    $hasSelected = true;
                    break;
                }
            }
            if (!$hasSelected) {
                $selectedRow = $this->managedUserSelectExtraOption($selectedUserId);
                if ($selectedRow) {
                    array_unshift($rows, $selectedRow);
                }
            }
        }

        return $rows;
    }

    protected function managedUserSelectBaseOptions($limit)
    {
        $limit = max(20, min(120, (int) $limit));
        if (isset($this->managedUserSelectBaseCache[$limit])) {
            return $this->managedUserSelectBaseCache[$limit];
        }

        $cacheVersion = (string) $this->app->cache()->get(self::MANAGED_USER_SELECT_OPTIONS_CACHE_VERSION_KEY, '1', 3600);
        $cacheKey = 'admin_managed_user_select_options_' . md5($cacheVersion . '|' . $limit);
        $cached = $this->app->cache()->get($cacheKey, null, 15);
        if (is_array($cached)) {
            $this->managedUserSelectBaseCache[$limit] = $cached;

            return $cached;
        }

        $memberRoleKeys = $this->app->users()->memberRoleKeys();
        $memberRoleSql = "'" . implode("','", array_map('addslashes', $memberRoleKeys)) . "'";
        $this->managedUserSelectBaseCache[$limit] = $this->db()->fetchAll(
            'SELECT users.id, users.username
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE roles.role_key IN (' . $memberRoleSql . ')
             ORDER BY users.created_at DESC, users.id DESC
             LIMIT ' . $limit
        );
        $this->app->cache()->put($cacheKey, $this->managedUserSelectBaseCache[$limit]);

        return $this->managedUserSelectBaseCache[$limit];
    }

    protected function managedUserSelectExtraOption($userId)
    {
        $userId = (int) $userId;
        if ($userId <= 0) {
            return null;
        }

        if (array_key_exists($userId, $this->managedUserSelectExtraCache)) {
            return $this->managedUserSelectExtraCache[$userId];
        }

        $cacheVersion = (string) $this->app->cache()->get(self::MANAGED_USER_SELECT_OPTIONS_CACHE_VERSION_KEY, '1', 3600);
        $cacheKey = 'admin_managed_user_select_extra_' . md5($cacheVersion . '|' . $userId);
        $cached = $this->app->cache()->get($cacheKey, null, 15);
        if (is_array($cached) || $cached === false) {
            $this->managedUserSelectExtraCache[$userId] = $cached === false ? null : $cached;

            return $this->managedUserSelectExtraCache[$userId];
        }

        $memberRoleKeys = $this->app->users()->memberRoleKeys();
        $memberRoleSql = "'" . implode("','", array_map('addslashes', $memberRoleKeys)) . "'";
        $this->managedUserSelectExtraCache[$userId] = $this->db()->fetch(
            'SELECT users.id, users.username
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE roles.role_key IN (' . $memberRoleSql . ')
               AND users.id = :id
             LIMIT 1',
            array('id' => $userId)
        );
        $this->app->cache()->put($cacheKey, $this->managedUserSelectExtraCache[$userId] ?: false);

        return $this->managedUserSelectExtraCache[$userId];
    }

    protected function clearManagedUserSelectOptionsCache()
    {
        $this->managedUserSelectBaseCache = array();
        $this->managedUserSelectExtraCache = array();
        $this->app->cache()->put(self::MANAGED_USER_SELECT_OPTIONS_CACHE_VERSION_KEY, (string) microtime(true));
    }

    public function managedUserById($userId)
    {
        $user = $this->app->users()->findById($userId);

        if (!$user || !$this->app->users()->isMemberRole((string) ($user['role_key'] ?? ''))) {
            return null;
        }

        return $user;
    }

    public function managedUserRoles()
    {
        if ($this->managedUserRolesRequestCache !== null) {
            return $this->managedUserRolesRequestCache;
        }

        $cacheKey = 'admin_managed_user_roles';
        $cached = $this->app->cache()->get($cacheKey, null, 15);
        if (is_array($cached)) {
            $this->managedUserRolesRequestCache = $cached;

            return $this->managedUserRolesRequestCache;
        }

        $this->managedUserRolesRequestCache = $this->app->users()->memberRoles();
        $this->app->cache()->put($cacheKey, $this->managedUserRolesRequestCache);

        return $this->managedUserRolesRequestCache;
    }

    public function saveManagedUser(array $payload, array $actor)
    {
        $savedUser = $this->app->users()->saveUser($payload, $actor);
        $this->clearManagedUserSelectOptionsCache();
        $action = isset($payload['id']) && (int) $payload['id'] > 0 ? 'update' : 'create';
        $this->recordOperation((int) $actor['id'], 'users', $action, 'user', (int) $savedUser['id'], '保存会员：' . $savedUser['username']);

        return $savedUser;
    }

    public function adjustManagedUserScore($userId, $amount, array $actor, $note = '')
    {
        $savedUser = $this->app->users()->addScore($userId, $amount, $actor);
        $note = trim((string) $note);
        if ($note === '') {
            $note = (int) $amount > 0 ? '后台充值积分' : '后台扣减积分';
        }
        if (function_exists('mb_substr')) {
            $note = mb_substr($note, 0, 80, 'UTF-8');
        } else {
            $note = substr($note, 0, 80);
        }
        $this->recordOperation(
            (int) $actor['id'],
            'users',
            'score',
            'user',
            (int) $savedUser['id'],
            '调整会员积分：' . $savedUser['username'] . ' => ' . (int) $amount . '，剩余 ' . (int) ($savedUser['score'] ?? 0) . '，说明：' . $note
        );

        return $savedUser;
    }

    public function listManagedUserConsumptionRecords($limit = 80)
    {
        $limit = max(10, min(200, (int) $limit));
        $this->cleanupManagedUserConsumptionRecords();

        $rows = array();
        if ($this->tableExists($this->db(), 'admin_operation_logs')) {
            $rows = $this->db()->fetchAll(
                'SELECT admin_operation_logs.id,
                    admin_operation_logs.target_id AS user_id,
                    admin_operation_logs.summary,
                    admin_operation_logs.request_data,
                    admin_operation_logs.created_at,
                    users.username,
                    users.score AS current_score,
                    users.status AS user_status
             FROM admin_operation_logs
             LEFT JOIN users ON users.id = admin_operation_logs.target_id
             WHERE admin_operation_logs.module = :module
               AND admin_operation_logs.action = :action
               AND admin_operation_logs.target_type = :target_type
               AND admin_operation_logs.target_id > 0
             ORDER BY admin_operation_logs.created_at DESC, admin_operation_logs.id DESC
             LIMIT ' . $limit,
                array(
                    'module' => 'users',
                    'action' => 'score',
                    'target_type' => 'user',
                )
            );
        }

        $records = array();
        foreach ($rows as $row) {
            $summary = trim((string) ($row['summary'] ?? ''));
            $payload = json_decode((string) ($row['request_data'] ?? ''), true);
            if (!is_array($payload)) {
                $payload = array();
            }

            $amount = isset($payload['score_amount']) ? (int) $payload['score_amount'] : 0;
            if ($amount === 0 && preg_match('/=>\s*([+-]?\d+)/u', $summary, $matches)) {
                $amount = (int) $matches[1];
            }

            $scoreAfter = null;
            if (preg_match('/剩余\s*([+-]?\d+)/u', $summary, $matches)) {
                $scoreAfter = (int) $matches[1];
            }

            $note = trim((string) ($payload['score_note'] ?? ''));
            if ($note === '' && preg_match('/说明：(.+)$/u', $summary, $matches)) {
                $note = trim((string) $matches[1]);
            }
            if ($note === '') {
                $note = $summary !== '' ? $summary : '后台积分调整';
            }

            $records[] = array(
                'id' => (int) ($row['id'] ?? 0),
                'user_id' => (int) ($row['user_id'] ?? 0),
                'username' => (string) (($row['username'] ?? '') ?: '会员已删除'),
                'score_amount' => $amount,
                'score_after' => $scoreAfter,
                'current_score' => ($row['username'] ?? '') !== '' ? (int) ($row['current_score'] ?? 0) : null,
                'status' => (string) (($row['username'] ?? '') !== '' ? '成功' : '会员已删除'),
                'user_status' => (string) ($row['user_status'] ?? ''),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'created_at_sort' => strtotime((string) ($row['created_at'] ?? '')) ?: 0,
                'source_label' => '后台调分',
                'note' => $note,
            );
        }

        if ($this->tableExists($this->db(), 'admin_logs')) {
            $legacyRows = $this->db()->fetchAll(
                'SELECT admin_logs.id,
                        admin_logs.target_id AS user_id,
                        admin_logs.user_id AS operator_id,
                        admin_logs.description AS summary,
                        admin_logs.created_at,
                        users.username,
                        users.score AS current_score,
                        users.status AS user_status
                 FROM admin_logs
                 LEFT JOIN users ON users.id = CAST(admin_logs.target_id AS UNSIGNED)
                 WHERE admin_logs.module_name = :module
                   AND admin_logs.action_name = :action
                   AND admin_logs.target_type = :target_type
                   AND admin_logs.target_id <> \'\'
                   AND NOT EXISTS (
                       SELECT 1
                       FROM admin_operation_logs
                       WHERE admin_operation_logs.module = :operation_module
                         AND admin_operation_logs.action = :operation_action
                         AND admin_operation_logs.target_type = :operation_target_type
                         AND admin_operation_logs.target_id = CAST(admin_logs.target_id AS UNSIGNED)
                         AND ABS(TIMESTAMPDIFF(SECOND, admin_operation_logs.created_at, admin_logs.created_at)) <= 2
                       LIMIT 1
                   )
                 ORDER BY admin_logs.created_at DESC, admin_logs.id DESC
                 LIMIT ' . $limit,
                array(
                    'module' => 'members',
                    'action' => 'charge',
                    'target_type' => 'user',
                    'operation_module' => 'users',
                    'operation_action' => 'score',
                    'operation_target_type' => 'user',
                )
            );

            foreach ($legacyRows as $row) {
                $summary = trim((string) ($row['summary'] ?? ''));
                $sourceLabel = strpos($summary, '接待端客服调分') === 0 ? '接待端客服' : '后台调分';
                $amount = 0;
                if (preg_match('/=>\s*([+-]?\d+)/u', $summary, $matches)) {
                    $amount = (int) $matches[1];
                }
                $scoreAfter = null;
                if (preg_match('/剩余\s*([+-]?\d+)/u', $summary, $matches)) {
                    $scoreAfter = (int) $matches[1];
                }
                $records[] = array(
                    'id' => (int) ($row['id'] ?? 0),
                    'user_id' => (int) ($row['user_id'] ?? 0),
                    'username' => (string) (($row['username'] ?? '') ?: '会员已删除'),
                    'score_amount' => $amount,
                    'score_after' => $scoreAfter,
                    'current_score' => ($row['username'] ?? '') !== '' ? (int) ($row['current_score'] ?? 0) : null,
                    'status' => (string) (($row['username'] ?? '') !== '' ? '成功' : '会员已删除'),
                    'user_status' => (string) ($row['user_status'] ?? ''),
                    'created_at' => (string) ($row['created_at'] ?? ''),
                    'created_at_sort' => strtotime((string) ($row['created_at'] ?? '')) ?: 0,
                    'source_label' => $sourceLabel,
                    'note' => $summary !== '' ? $summary : '后台积分调整',
                );
            }
        }

        usort($records, function ($left, $right) {
            $leftTime = (int) ($left['created_at_sort'] ?? 0);
            $rightTime = (int) ($right['created_at_sort'] ?? 0);
            if ($leftTime === $rightTime) {
                return (int) ($right['id'] ?? 0) <=> (int) ($left['id'] ?? 0);
            }

            return $rightTime <=> $leftTime;
        });

        $records = $this->fillManagedUserConsumptionScoreAfter($records);

        return array_slice($records, 0, $limit);
    }

    protected function cleanupManagedUserConsumptionRecords()
    {
        $cacheKey = 'admin_managed_user_consumption_cleanup_at';
        $lastCleanupAt = (int) $this->app->cache()->get($cacheKey, 0);
        if ($lastCleanupAt > 0 && (time() - $lastCleanupAt) < 86400) {
            return;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime('-60 days'));
        if ($this->tableExists($this->db(), 'admin_operation_logs')) {
            $this->db()->execute(
                'DELETE FROM admin_operation_logs
                 WHERE module = :module
                   AND action = :action
                   AND target_type = :target_type
                   AND created_at < :cutoff',
                array(
                    'module' => 'users',
                    'action' => 'score',
                    'target_type' => 'user',
                    'cutoff' => $cutoff,
                )
            );
        }

        if ($this->tableExists($this->db(), 'admin_logs')) {
            $this->db()->execute(
                'DELETE FROM admin_logs
                 WHERE module_name = :module
                   AND action_name = :action
                   AND target_type = :target_type
                   AND created_at < :cutoff',
                array(
                    'module' => 'members',
                    'action' => 'charge',
                    'target_type' => 'user',
                    'cutoff' => $cutoff,
                )
            );
        }

        $this->app->cache()->forget('admin_managed_user_consumption_records');
        $this->app->cache()->put($cacheKey, time());
    }

    protected function fillManagedUserConsumptionScoreAfter(array $records)
    {
        $runningScores = array();
        foreach ($records as $index => $record) {
            $userId = (int) ($record['user_id'] ?? 0);
            if ($userId <= 0 || !array_key_exists('current_score', $record) || $record['current_score'] === null) {
                continue;
            }

            if (!array_key_exists($userId, $runningScores)) {
                $runningScores[$userId] = (int) $record['current_score'];
            }

            if ($records[$index]['score_after'] === null) {
                $records[$index]['score_after'] = $runningScores[$userId];
            } else {
                $runningScores[$userId] = (int) $records[$index]['score_after'];
            }

            $runningScores[$userId] -= (int) ($record['score_amount'] ?? 0);
        }

        return $records;
    }

    public function deleteManagedUser($userId, array $actor)
    {
        $userId = (int) $userId;
        $user = $this->managedUserById($userId);
        if (!$user) {
            throw new RuntimeException('会员不存在或不能删除管理员账号。');
        }

        if ($this->managedUserAuthoredPostCount($userId) > 0) {
            throw new RuntimeException('会员 ' . $user['username'] . ' 存在帖子，请先处理该会员帖子后再删除。');
        }

        $database = $this->db();
        $now = $this->now();
        $database->beginTransaction();
        try {
            $this->deleteManagedUserRelatedRows($userId);
            $database->execute('DELETE FROM users WHERE id = :id', array('id' => $userId));
            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }

        $this->clearManagedUserSelectOptionsCache();
        $this->recordOperation((int) $actor['id'], 'users', 'delete', 'user', $userId, '删除会员：' . $user['username'] . ' / ' . $now);

        return $user;
    }

    public function deleteManagedUsers(array $userIds, array $actor)
    {
        $normalizedIds = array();
        foreach ($userIds as $userId) {
            $userId = (int) $userId;
            if ($userId > 0) {
                $normalizedIds[$userId] = $userId;
            }
        }

        if (!$normalizedIds) {
            throw new RuntimeException('请先勾选要删除的会员。');
        }

        foreach (array_values($normalizedIds) as $userId) {
            $user = $this->managedUserById($userId);
            if (!$user) {
                throw new RuntimeException('会员不存在或不能删除管理员账号。');
            }
            if ($this->managedUserAuthoredPostCount($userId) > 0) {
                throw new RuntimeException('会员 ' . $user['username'] . ' 存在帖子，请先处理该会员帖子后再删除。');
            }
        }

        $deletedUsers = array();
        foreach (array_values($normalizedIds) as $userId) {
            $deletedUsers[] = $this->deleteManagedUser($userId, $actor);
        }

        return $deletedUsers;
    }

    protected function managedUserAuthoredPostCount($userId)
    {
        if (!$this->tableExists($this->db(), 'posts')) {
            return 0;
        }

        $postRow = $this->db()->fetch(
            'SELECT COUNT(*) AS total_count FROM posts WHERE author_id = :author_id',
            array('author_id' => (int) $userId)
        );

        return $postRow ? (int) $postRow['total_count'] : 0;
    }

    protected function deleteManagedUserRelatedRows($userId)
    {
        $userId = (int) $userId;
        $database = $this->db();

        $deleteTables = array(
            'user_ban_records' => 'user_id',
            'user_vips' => 'user_id',
            'post_interactions' => 'user_id',
            'post_reports' => 'reporter_id',
            'comment_likes' => 'user_id',
        );
        foreach ($deleteTables as $table => $column) {
            if ($this->tableExists($database, $table)) {
                $database->execute('DELETE FROM ' . $table . ' WHERE ' . $column . ' = :user_id', array('user_id' => $userId));
            }
        }

        $nullableReferences = array(
            'login_logs' => array('user_id'),
            'password_reset_requests' => array('user_id', 'processed_by'),
            'lottery_draws' => array('created_by'),
            'ai_predictions' => array('generated_by'),
            'ai_prediction_participations' => array('user_id'),
            'admin_logs' => array('user_id'),
            'page_views' => array('user_id'),
            'post_unique_views' => array('user_id'),
            'customer_service_messages' => array('sender_user_id'),
        );
        foreach ($nullableReferences as $table => $columns) {
            if (!$this->tableExists($database, $table)) {
                continue;
            }
            foreach ($columns as $column) {
                $database->execute('UPDATE ' . $table . ' SET ' . $column . ' = NULL WHERE ' . $column . ' = :user_id', array('user_id' => $userId));
            }
        }
    }

    public function saveRegisterBonusSetting($bonus, array $actor)
    {
        $saved = $this->saveRegisterRuleSettings(
            $bonus,
            $this->app->settings()->get('members.register_limit_days', '1'),
            $this->app->settings()->get('points.invite_register_bonus', '0'),
            $actor
        );

        return (int) $saved['register_bonus'];
    }

    public function saveRegisterRuleSettings($bonus, $limitDays, $inviteBonus, array $actor)
    {
        $bonus = max(0, min(100000000, (int) $bonus));
        $limitDays = max(0, min(365, (int) $limitDays));
        $inviteBonus = max(0, min(100000000, (int) $inviteBonus));
        $this->app->settings()->setMany('points', array(
            'points.register_bonus' => (string) $bonus,
            'points.invite_register_bonus' => (string) $inviteBonus,
        ));
        $this->app->settings()->setMany('members', array(
            'members.register_limit_days' => (string) $limitDays,
        ));
        $this->recordOperation(
            (int) $actor['id'],
            'users',
            'settings',
            'settings',
            0,
            '保存注册规则：赠送积分 ' . $bonus . '，邀请奖励 ' . $inviteBonus . '，重复注册限制 ' . $limitDays . ' 天'
        );

        return array(
            'register_bonus' => $bonus,
            'register_limit_days' => $limitDays,
            'invite_register_bonus' => $inviteBonus,
        );
    }

    public function listManagedPasswordResetRequests(array $filters = array())
    {
        $status = trim((string) ($filters['status'] ?? ''));
        if (!in_array($status, array('pending', 'processed'), true)) {
            $status = '';
        }

        $requestCacheKey = $status !== '' ? $status : 'all';
        if (isset($this->managedPasswordResetRequestsCache[$requestCacheKey])) {
            return $this->managedPasswordResetRequestsCache[$requestCacheKey];
        }

        $cacheVersion = (string) $this->app->cache()->get(self::MANAGED_PASSWORD_RESET_REQUESTS_CACHE_VERSION_KEY, '1', 3600);
        $persistentCacheKey = 'admin_managed_password_reset_requests_' . md5($cacheVersion . '|' . $requestCacheKey);
        $cached = $this->app->cache()->get($persistentCacheKey, null, 15);
        if (is_array($cached)) {
            $this->managedPasswordResetRequestsCache[$requestCacheKey] = $cached;

            return $cached;
        }

        $sql = 'SELECT password_reset_requests.*, users.email, users.status AS user_status
                FROM password_reset_requests
                LEFT JOIN users ON users.id = password_reset_requests.user_id
                WHERE 1 = 1';
        $params = array();

        if ($status !== '') {
            $sql .= ' AND password_reset_requests.status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY password_reset_requests.created_at DESC LIMIT 80';
        $this->managedPasswordResetRequestsCache[$requestCacheKey] = $this->db()->fetchAll($sql, $params);
        $this->app->cache()->put($persistentCacheKey, $this->managedPasswordResetRequestsCache[$requestCacheKey]);

        return $this->managedPasswordResetRequestsCache[$requestCacheKey];
    }

    protected function clearManagedPasswordResetRequestsCache()
    {
        $this->managedPasswordResetRequestsCache = array();
        $this->app->cache()->put(self::MANAGED_PASSWORD_RESET_REQUESTS_CACHE_VERSION_KEY, (string) microtime(true));
    }

    public function processManagedPasswordReset($requestId, $newPassword, array $actor)
    {
        $request = $this->db()->fetch('SELECT * FROM password_reset_requests WHERE id = :id LIMIT 1', array(
            'id' => (int) $requestId,
        ));

        $this->app->users()->processPasswordReset($requestId, $newPassword, $actor);
        $this->clearManagedPasswordResetRequestsCache();

        if ($request) {
            $this->recordOperation((int) $actor['id'], 'users', 'reset_password', 'password_reset_request', (int) $requestId, '处理找回密码：' . $request['username']);
        }
    }

    public function listManagedUserLoginLogs($userId, $limit = 20)
    {
        return $this->db()->fetchAll(
            'SELECT * FROM login_logs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT ' . (int) $limit,
            array('user_id' => (int) $userId)
        );
    }

    public function listManagedUserBanRecords($userId, $limit = 20)
    {
        if (!$this->tableExists($this->db(), 'user_ban_records')) {
            return array();
        }

        return $this->db()->fetchAll(
            'SELECT * FROM user_ban_records WHERE user_id = :user_id ORDER BY id DESC LIMIT ' . (int) $limit,
            array('user_id' => (int) $userId)
        );
    }

    public function activeManagedUserBan($userId)
    {
        if (!$this->tableExists($this->db(), 'user_ban_records')) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT * FROM user_ban_records WHERE user_id = :user_id AND status = 1 ORDER BY id DESC LIMIT 1',
            array('user_id' => (int) $userId)
        );
    }

    public function saveManagedUserBan($userId, array $payload, array $actor)
    {
        if (!$this->tableExists($this->db(), 'user_ban_records')) {
            throw new RuntimeException('当前数据库还没有会员封禁表，请先刷新后台完成补表。');
        }

        $user = $this->managedUserById($userId);
        if (!$user) {
            throw new RuntimeException('会员不存在，无法执行封禁。');
        }

        $reason = trim((string) ($payload['ban_reason'] ?? ''));
        $remark = trim((string) ($payload['ban_remark'] ?? ''));
        $endAt = $this->normalizeDateTimeInput((string) ($payload['ban_end_at'] ?? ''));

        if ($reason === '') {
            throw new RuntimeException('封禁原因不能为空。');
        }

        $now = $this->now();
        $database = $this->db();
        $database->beginTransaction();

        try {
            $database->execute(
                'UPDATE user_ban_records
                 SET status = 0, unbanned_by = :unbanned_by, unbanned_at = :unbanned_at, updated_at = :updated_at
                 WHERE user_id = :user_id AND status = 1',
                array(
                    'unbanned_by' => (int) $actor['id'],
                    'unbanned_at' => $now,
                    'updated_at' => $now,
                    'user_id' => (int) $userId,
                )
            );

            $database->insertGetId(
                'INSERT INTO user_ban_records (user_id, ban_type, reason, start_at, end_at, status, banned_by, unbanned_by, unbanned_at, remark, created_at, updated_at)
                 VALUES (:user_id, :ban_type, :reason, :start_at, :end_at, :status, :banned_by, :unbanned_by, :unbanned_at, :remark, :created_at, :updated_at)',
                array(
                    'user_id' => (int) $userId,
                    'ban_type' => 'account',
                    'reason' => $reason,
                    'start_at' => $now,
                    'end_at' => $endAt !== '' ? $endAt : null,
                    'status' => 1,
                    'banned_by' => (int) $actor['id'],
                    'unbanned_by' => 0,
                    'unbanned_at' => null,
                    'remark' => $remark,
                    'created_at' => $now,
                    'updated_at' => $now,
                )
            );

            $database->execute(
                'UPDATE users SET status = :status, updated_at = :updated_at WHERE id = :id',
                array(
                    'status' => 'disabled',
                    'updated_at' => $now,
                    'id' => (int) $userId,
                )
            );

            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }

        $this->recordOperation((int) $actor['id'], 'users', 'ban', 'user', (int) $userId, '封禁会员：' . $user['username']);

        return $this->activeManagedUserBan($userId);
    }

    public function liftManagedUserBan($userId, array $actor)
    {
        if (!$this->tableExists($this->db(), 'user_ban_records')) {
            throw new RuntimeException('当前数据库还没有会员封禁表，请先刷新后台完成补表。');
        }

        $user = $this->managedUserById($userId);
        if (!$user) {
            throw new RuntimeException('会员不存在，无法解除封禁。');
        }

        $activeBan = $this->activeManagedUserBan($userId);
        if (!$activeBan) {
            throw new RuntimeException('当前会员没有生效中的封禁记录。');
        }

        $now = $this->now();
        $database = $this->db();
        $database->beginTransaction();

        try {
            $database->execute(
                'UPDATE user_ban_records
                 SET status = 0, unbanned_by = :unbanned_by, unbanned_at = :unbanned_at, updated_at = :updated_at
                 WHERE id = :id',
                array(
                    'unbanned_by' => (int) $actor['id'],
                    'unbanned_at' => $now,
                    'updated_at' => $now,
                    'id' => (int) $activeBan['id'],
                )
            );

            $database->execute(
                'UPDATE users SET status = :status, updated_at = :updated_at WHERE id = :id',
                array(
                    'status' => 'active',
                    'updated_at' => $now,
                    'id' => (int) $userId,
                )
            );

            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }

        $this->recordOperation((int) $actor['id'], 'users', 'lift_ban', 'user', (int) $userId, '解除封禁会员：' . $user['username']);

        return $this->managedUserById($userId);
    }

    public function listManagedUserVipRecords($userId, $limit = 20)
    {
        if (!$this->tableExists($this->db(), 'user_vips')) {
            return array();
        }

        return $this->db()->fetchAll(
            'SELECT * FROM user_vips WHERE user_id = :user_id ORDER BY id DESC LIMIT ' . (int) $limit,
            array('user_id' => (int) $userId)
        );
    }

    public function activeManagedUserVip($userId)
    {
        if (!$this->tableExists($this->db(), 'user_vips')) {
            return null;
        }

        return $this->db()->fetch(
            "SELECT * FROM user_vips WHERE user_id = :user_id AND status = 'active' ORDER BY id DESC LIMIT 1",
            array('user_id' => (int) $userId)
        );
    }

    public function saveManagedUserVip($userId, array $payload, array $actor)
    {
        if (!$this->tableExists($this->db(), 'user_vips')) {
            throw new RuntimeException('当前数据库还没有会员VIP表，请先刷新后台完成补表。');
        }

        $user = $this->managedUserById($userId);
        if (!$user) {
            throw new RuntimeException('会员不存在，无法开通VIP。');
        }

        $vipName = trim((string) ($payload['vip_name'] ?? ''));
        $vipLevelCode = trim((string) ($payload['vip_level_code'] ?? ''));
        $startAt = $this->normalizeDateTimeInput((string) ($payload['vip_start_at'] ?? ''));
        $expireAt = $this->normalizeDateTimeInput((string) ($payload['vip_expire_at'] ?? ''));
        $remark = trim((string) ($payload['vip_remark'] ?? ''));

        if ($vipName === '') {
            throw new RuntimeException('VIP名称不能为空。');
        }

        if ($vipLevelCode === '') {
            throw new RuntimeException('VIP等级标识不能为空。');
        }

        if ($expireAt === '') {
            throw new RuntimeException('VIP到期时间不能为空。');
        }

        $now = $this->now();
        $database = $this->db();
        $database->beginTransaction();

        try {
            $database->execute(
                "UPDATE user_vips SET status = 'inactive', updated_at = :updated_at WHERE user_id = :user_id AND status = 'active'",
                array(
                    'updated_at' => $now,
                    'user_id' => (int) $userId,
                )
            );

            $database->insertGetId(
                'INSERT INTO user_vips (user_id, vip_name, level_code, start_at, expire_at, source_type, source_id, status, remark, created_at, updated_at)
                 VALUES (:user_id, :vip_name, :level_code, :start_at, :expire_at, :source_type, :source_id, :status, :remark, :created_at, :updated_at)',
                array(
                    'user_id' => (int) $userId,
                    'vip_name' => $vipName,
                    'level_code' => $vipLevelCode,
                    'start_at' => $startAt !== '' ? $startAt : $now,
                    'expire_at' => $expireAt,
                    'source_type' => 'manual',
                    'source_id' => (int) $actor['id'],
                    'status' => 'active',
                    'remark' => $remark,
                    'created_at' => $now,
                    'updated_at' => $now,
                )
            );

            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }

        $this->recordOperation((int) $actor['id'], 'users', 'save_vip', 'user', (int) $userId, '开通VIP会员：' . $user['username']);

        return $this->activeManagedUserVip($userId);
    }

    public function listManagedSections(array $filters = array())
    {
        if (!$this->tableExists($this->db(), 'forum_sections')) {
            return array();
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $region = trim((string) ($filters['region'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $normalizedFilters = array(
            'keyword' => $keyword,
            'region' => in_array($region, array('macau', 'hongkong'), true) ? $region : '',
            'status' => in_array($status, array('0', '1'), true) ? $status : '',
        );
        $cacheVersion = (string) $this->app->cache()->get(self::MANAGED_FORUM_OPTIONS_CACHE_VERSION_KEY, '1', 3600);
        $cacheKey = md5($cacheVersion . '|' . json_encode($normalizedFilters));
        if (isset($this->managedSectionListCache[$cacheKey])) {
            return $this->managedSectionListCache[$cacheKey];
        }
        $persistentCacheKey = 'admin_managed_section_list_' . $cacheKey;
        $cached = $this->app->cache()->get($persistentCacheKey, null, 15);
        if (is_array($cached)) {
            $this->managedSectionListCache[$cacheKey] = $cached;

            return $cached;
        }

        $sql = 'SELECT forum_sections.*,
                       (
                           SELECT COUNT(*)
                           FROM posts
                           WHERE posts.section_id = forum_sections.id
                       ) AS post_total
                FROM forum_sections
                WHERE 1 = 1';
        $params = array();

        if ($keyword !== '') {
            $sql .= ' AND (forum_sections.name LIKE :keyword_name OR forum_sections.code LIKE :keyword_code OR forum_sections.description LIKE :keyword_description)';
            $params['keyword_name'] = '%' . $keyword . '%';
            $params['keyword_code'] = '%' . $keyword . '%';
            $params['keyword_description'] = '%' . $keyword . '%';
        }

        if (in_array($region, array('macau', 'hongkong'), true)) {
            $sql .= ' AND forum_sections.region = :region';
            $params['region'] = $region;
        }

        if (in_array($status, array('0', '1'), true)) {
            $sql .= ' AND forum_sections.status = :status';
            $params['status'] = (int) $status;
        }

        $sql .= ' ORDER BY forum_sections.sort_order ASC, forum_sections.id ASC';

        $sections = $this->db()->fetchAll($sql, $params);
        $this->managedSectionListCache[$cacheKey] = $sections;
        $this->app->cache()->put($persistentCacheKey, $sections);

        return $sections;
    }

    public function managedSectionById($sectionId)
    {
        if (!$this->tableExists($this->db(), 'forum_sections')) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT * FROM forum_sections WHERE id = :id LIMIT 1',
            array('id' => (int) $sectionId)
        );
    }

    public function saveManagedSection(array $payload, array $actor)
    {
        if (!$this->tableExists($this->db(), 'forum_sections')) {
            throw new RuntimeException('当前数据库还没有版块表，请先刷新后台完成补表。');
        }

        $sectionId = isset($payload['id']) ? (int) $payload['id'] : 0;
        $region = trim((string) ($payload['region'] ?? 'macau'));
        $name = trim((string) ($payload['name'] ?? ''));
        $code = trim((string) ($payload['code'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $icon = trim((string) ($payload['icon'] ?? ''));
        $postRule = trim((string) ($payload['post_rule'] ?? ''));
        $sortOrder = (int) ($payload['sort_order'] ?? 0);
        $status = !empty($payload['status']) ? 1 : 0;

        if (!in_array($region, array('macau', 'hongkong'), true)) {
            throw new RuntimeException('版块分区无效。');
        }

        if ($name === '') {
            throw new RuntimeException('版块名称不能为空。');
        }

        if ($code === '' || !preg_match('/^[a-z0-9_]+$/', $code)) {
            throw new RuntimeException('版块编码只能使用小写字母、数字和下划线。');
        }

        $existing = $this->db()->fetch(
            'SELECT id FROM forum_sections WHERE code = :code AND id <> :id LIMIT 1',
            array('code' => $code, 'id' => $sectionId)
        );
        if ($existing) {
            throw new RuntimeException('版块编码已存在，请更换后再保存。');
        }

        $now = $this->now();
        if ($sectionId > 0) {
            $this->db()->execute(
                'UPDATE forum_sections
                 SET region = :region,
                     name = :name,
                     code = :code,
                     description = :description,
                     icon = :icon,
                     post_rule = :post_rule,
                     sort_order = :sort_order,
                     status = :status,
                     updated_at = :updated_at
                 WHERE id = :id',
                array(
                    'region' => $region,
                    'name' => $name,
                    'code' => $code,
                    'description' => $description,
                    'icon' => $icon,
                    'post_rule' => $postRule,
                    'sort_order' => $sortOrder,
                    'status' => $status,
                    'updated_at' => $now,
                    'id' => $sectionId,
                )
            );
            $action = 'update';
        } else {
            $sectionId = (int) $this->db()->insertGetId(
                'INSERT INTO forum_sections (region, name, code, description, icon, sort_order, status, post_rule, created_at, updated_at)
                 VALUES (:region, :name, :code, :description, :icon, :sort_order, :status, :post_rule, :created_at, :updated_at)',
                array(
                    'region' => $region,
                    'name' => $name,
                    'code' => $code,
                    'description' => $description,
                    'icon' => $icon,
                    'sort_order' => $sortOrder,
                    'status' => $status,
                    'post_rule' => $postRule,
                    'created_at' => $now,
                    'updated_at' => $now,
                )
            );
            $action = 'create';
        }

        $savedSection = $this->managedSectionById($sectionId);
        if (!$savedSection) {
            throw new RuntimeException('版块保存后未能回读，请刷新后重试。');
        }

        $this->recordOperation((int) $actor['id'], 'sections', $action, 'forum_section', $sectionId, '保存版块：' . $savedSection['name']);
        $this->clearManagedForumOptionCaches();

        return $savedSection;
    }

    public function listManagedCategories(array $filters = array())
    {
        if (!$this->tableExists($this->db(), 'forum_categories')) {
            return array();
        }

        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $region = trim((string) ($filters['region'] ?? ''));
        $sectionId = (int) ($filters['section_id'] ?? 0);
        $status = trim((string) ($filters['status'] ?? ''));
        $normalizedFilters = array(
            'keyword' => $keyword,
            'region' => in_array($region, array('macau', 'hongkong'), true) ? $region : '',
            'section_id' => max(0, $sectionId),
            'status' => in_array($status, array('0', '1'), true) ? $status : '',
        );
        $cacheVersion = (string) $this->app->cache()->get(self::MANAGED_FORUM_OPTIONS_CACHE_VERSION_KEY, '1', 3600);
        $cacheKey = md5($cacheVersion . '|' . json_encode($normalizedFilters));
        if (isset($this->managedCategoryListCache[$cacheKey])) {
            return $this->managedCategoryListCache[$cacheKey];
        }
        $persistentCacheKey = 'admin_managed_category_list_' . $cacheKey;
        $cached = $this->app->cache()->get($persistentCacheKey, null, 15);
        if (is_array($cached)) {
            $this->managedCategoryListCache[$cacheKey] = $cached;

            return $cached;
        }

        $sql = 'SELECT forum_categories.*, forum_sections.name AS section_name,
                       (
                           SELECT COUNT(*)
                           FROM posts
                           WHERE posts.category_id = forum_categories.id
                       ) AS post_total
                FROM forum_categories
                LEFT JOIN forum_sections ON forum_sections.id = forum_categories.section_id
                WHERE 1 = 1';
        $params = array();

        if ($keyword !== '') {
            $sql .= ' AND (forum_categories.name LIKE :keyword_name OR forum_categories.code LIKE :keyword_code OR forum_categories.description LIKE :keyword_description)';
            $params['keyword_name'] = '%' . $keyword . '%';
            $params['keyword_code'] = '%' . $keyword . '%';
            $params['keyword_description'] = '%' . $keyword . '%';
        }

        if (in_array($region, array('macau', 'hongkong'), true)) {
            $sql .= ' AND forum_categories.region = :region';
            $params['region'] = $region;
        }

        if ($sectionId > 0) {
            $sql .= ' AND forum_categories.section_id = :section_id';
            $params['section_id'] = $sectionId;
        }

        if (in_array($status, array('0', '1'), true)) {
            $sql .= ' AND forum_categories.status = :status';
            $params['status'] = (int) $status;
        }

        $sql .= ' ORDER BY forum_sections.sort_order ASC, forum_categories.sort_order ASC, forum_categories.id ASC';

        $categories = $this->db()->fetchAll($sql, $params);
        $this->managedCategoryListCache[$cacheKey] = $categories;
        $this->app->cache()->put($persistentCacheKey, $categories);

        return $categories;
    }

    public function managedCategoryById($categoryId)
    {
        if (!$this->tableExists($this->db(), 'forum_categories')) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT forum_categories.*, forum_sections.name AS section_name
             FROM forum_categories
             LEFT JOIN forum_sections ON forum_sections.id = forum_categories.section_id
             WHERE forum_categories.id = :id
             LIMIT 1',
            array('id' => (int) $categoryId)
        );
    }

    public function saveManagedCategory(array $payload, array $actor)
    {
        if (!$this->tableExists($this->db(), 'forum_categories')) {
            throw new RuntimeException('当前数据库还没有分类表，请先刷新后台完成补表。');
        }

        $categoryId = isset($payload['id']) ? (int) $payload['id'] : 0;
        $sectionId = (int) ($payload['section_id'] ?? 0);
        $region = trim((string) ($payload['region'] ?? 'macau'));
        $name = trim((string) ($payload['name'] ?? ''));
        $code = trim((string) ($payload['code'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $sortOrder = (int) ($payload['sort_order'] ?? 0);
        $status = !empty($payload['status']) ? 1 : 0;

        if ($sectionId <= 0) {
            throw new RuntimeException('请选择所属版块。');
        }

        $section = $this->managedSectionById($sectionId);
        if (!$section) {
            throw new RuntimeException('所属版块不存在，请刷新页面后重试。');
        }

        if (!in_array($region, array('macau', 'hongkong'), true)) {
            throw new RuntimeException('分类分区无效。');
        }

        if ($region !== (string) $section['region']) {
            throw new RuntimeException('分类分区必须与所属版块保持一致。');
        }

        if ($name === '') {
            throw new RuntimeException('分类名称不能为空。');
        }

        if ($code === '' || !preg_match('/^[a-z0-9_]+$/', $code)) {
            throw new RuntimeException('分类编码只能使用小写字母、数字和下划线。');
        }

        $existing = $this->db()->fetch(
            'SELECT id FROM forum_categories WHERE code = :code AND id <> :id LIMIT 1',
            array('code' => $code, 'id' => $categoryId)
        );
        if ($existing) {
            throw new RuntimeException('分类编码已存在，请更换后再保存。');
        }

        $now = $this->now();
        if ($categoryId > 0) {
            $this->db()->execute(
                'UPDATE forum_categories
                 SET section_id = :section_id,
                     region = :region,
                     name = :name,
                     code = :code,
                     description = :description,
                     sort_order = :sort_order,
                     status = :status,
                     updated_at = :updated_at
                 WHERE id = :id',
                array(
                    'section_id' => $sectionId,
                    'region' => $region,
                    'name' => $name,
                    'code' => $code,
                    'description' => $description,
                    'sort_order' => $sortOrder,
                    'status' => $status,
                    'updated_at' => $now,
                    'id' => $categoryId,
                )
            );
            $action = 'update';
        } else {
            $categoryId = (int) $this->db()->insertGetId(
                'INSERT INTO forum_categories (section_id, region, name, code, description, sort_order, status, created_at, updated_at)
                 VALUES (:section_id, :region, :name, :code, :description, :sort_order, :status, :created_at, :updated_at)',
                array(
                    'section_id' => $sectionId,
                    'region' => $region,
                    'name' => $name,
                    'code' => $code,
                    'description' => $description,
                    'sort_order' => $sortOrder,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                )
            );
            $action = 'create';
        }

        $savedCategory = $this->managedCategoryById($categoryId);
        if (!$savedCategory) {
            throw new RuntimeException('分类保存后未能回读，请刷新后重试。');
        }

        $this->recordOperation((int) $actor['id'], 'categories', $action, 'forum_category', $categoryId, '保存分类：' . $savedCategory['name']);
        $this->clearManagedForumOptionCaches();

        return $savedCategory;
    }

    public function sectionOptions($region = '')
    {
        $region = trim((string) $region);
        $cacheKey = $region === '' ? '*' : $region;
        if (isset($this->managedSectionOptionsCache[$cacheKey])) {
            return $this->managedSectionOptionsCache[$cacheKey];
        }

        $cacheVersion = (string) $this->app->cache()->get(self::MANAGED_FORUM_OPTIONS_CACHE_VERSION_KEY, '1', 3600);
        $persistentCacheKey = 'admin_managed_section_options_' . md5($cacheVersion . '|' . $cacheKey);
        $cached = $this->app->cache()->get($persistentCacheKey, null, 15);
        if (is_array($cached)) {
            $this->managedSectionOptionsCache[$cacheKey] = $cached;

            return $cached;
        }

        $filters = array('status' => '1');
        if ($region !== '') {
            $filters['region'] = $region;
        }

        $sections = $this->listManagedSections($filters);
        $this->managedSectionOptionsCache[$cacheKey] = $sections;
        $this->app->cache()->put($persistentCacheKey, $sections);

        return $sections;
    }

    public function categoryOptions($sectionId = 0, $region = '')
    {
        $sectionId = (int) $sectionId;
        $region = trim((string) $region);
        $cacheKey = $sectionId . '|' . ($region === '' ? '*' : $region);
        if (isset($this->managedCategoryOptionsCache[$cacheKey])) {
            return $this->managedCategoryOptionsCache[$cacheKey];
        }

        $cacheVersion = (string) $this->app->cache()->get(self::MANAGED_FORUM_OPTIONS_CACHE_VERSION_KEY, '1', 3600);
        $persistentCacheKey = 'admin_managed_category_options_' . md5($cacheVersion . '|' . $cacheKey);
        $cached = $this->app->cache()->get($persistentCacheKey, null, 15);
        if (is_array($cached)) {
            $this->managedCategoryOptionsCache[$cacheKey] = $cached;

            return $cached;
        }

        $filters = array('status' => '1');
        if ($sectionId > 0) {
            $filters['section_id'] = $sectionId;
        }
        if ($region !== '') {
            $filters['region'] = $region;
        }

        $categories = $this->listManagedCategories($filters);
        $this->managedCategoryOptionsCache[$cacheKey] = $categories;
        $this->app->cache()->put($persistentCacheKey, $categories);

        return $categories;
    }

    protected function clearManagedForumOptionCaches()
    {
        $this->managedSectionListCache = array();
        $this->managedCategoryListCache = array();
        $this->managedSectionOptionsCache = array();
        $this->managedCategoryOptionsCache = array();
        $this->app->cache()->put(self::MANAGED_FORUM_OPTIONS_CACHE_VERSION_KEY, (string) microtime(true));
        $this->clearManagedPostGeneratorConfigCache();
    }

    public function managedPostGeneratorConfig($region = 'macau')
    {
        $region = $region === 'hongkong' ? 'hongkong' : 'macau';
        if (isset($this->managedPostGeneratorConfigCache[$region])) {
            return $this->managedPostGeneratorConfigCache[$region];
        }

        $currentIssueSnapshot = $this->managedIssuePrefixSnapshotByRegion($region);
        $currentIssueTail = preg_replace('/\D+/', '', (string) ($currentIssueSnapshot['issue_prefix_tail'] ?? ''));
        if ($currentIssueTail === '') {
            $currentIssueTail = $this->nextManagedIssueTail($region);
        }
        $waveRed = array(1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46);
        $waveBlue = array(3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48);
        $numberOptions = array();
        for ($number = 1; $number <= 49; $number++) {
            $wave = in_array($number, $waveRed, true) ? 'red' : (in_array($number, $waveBlue, true) ? 'blue' : 'green');
            $numberOptions[] = array(
                'value' => str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'label' => str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'wave' => $wave,
            );
        }

        $config = array(
            'region' => $region,
            'region_label' => $region === 'hongkong' ? '香港' : '澳门',
            'type_options' => array(
                array('value' => 'macau', 'label' => '新澳'),
                array('value' => 'hongkong', 'label' => '香港'),
            ),
            'zodiac_options' => array(
                array('value' => '马', 'label' => '马'),
                array('value' => '蛇', 'label' => '蛇'),
                array('value' => '龙', 'label' => '龙'),
                array('value' => '兔', 'label' => '兔'),
                array('value' => '虎', 'label' => '虎'),
                array('value' => '牛', 'label' => '牛'),
                array('value' => '鼠', 'label' => '鼠'),
                array('value' => '猪', 'label' => '猪'),
                array('value' => '狗', 'label' => '狗'),
                array('value' => '鸡', 'label' => '鸡'),
                array('value' => '猴', 'label' => '猴'),
                array('value' => '羊', 'label' => '羊'),
            ),
            'number_options' => $numberOptions,
            'wave_options' => array(
                array('value' => '红', 'label' => '红'),
                array('value' => '蓝', 'label' => '蓝'),
                array('value' => '绿', 'label' => '绿'),
            ),
            'element_options' => array(
                array('value' => '金', 'label' => '金'),
                array('value' => '木', 'label' => '木'),
                array('value' => '水', 'label' => '水'),
                array('value' => '火', 'label' => '火'),
                array('value' => '土', 'label' => '土'),
            ),
            'head_options' => array(
                array('value' => '0头', 'label' => '0头'),
                array('value' => '1头', 'label' => '1头'),
                array('value' => '2头', 'label' => '2头'),
                array('value' => '3头', 'label' => '3头'),
                array('value' => '4头', 'label' => '4头'),
            ),
            'tail_options' => array(
                array('value' => '0尾', 'label' => '0尾'),
                array('value' => '1尾', 'label' => '1尾'),
                array('value' => '2尾', 'label' => '2尾'),
                array('value' => '3尾', 'label' => '3尾'),
                array('value' => '4尾', 'label' => '4尾'),
                array('value' => '5尾', 'label' => '5尾'),
                array('value' => '6尾', 'label' => '6尾'),
                array('value' => '7尾', 'label' => '7尾'),
                array('value' => '8尾', 'label' => '8尾'),
                array('value' => '9尾', 'label' => '9尾'),
            ),
            'segment_options' => $this->managedPostSegmentOptions($region),
            'top_options' => $this->managedPostGeneratorTopOptions(),
            'template_groups' => $this->managedPostGeneratorTemplateGroups(),
            'default_targets' => $this->managedPostGeneratorDefaultTargets($region),
            'current_issue_tail' => $currentIssueTail,
            'title_prefix_color_mode' => '',
            'title_prefix_color_value' => '#2563eb',
            'title_middle_color_mode' => '',
            'title_middle_color_value' => '#2563eb',
            'author_nickname_color_mode' => '',
            'author_nickname_color_value' => '#2563eb',
            'author_nickname_idioms' => $this->managedAuthorNicknameIdiomLibraryExpanded(),
            'auto_reply_enabled' => '1',
            'auto_reply_count' => '5',
            'auto_reply_base_min' => '2',
            'auto_reply_base_max' => '5',
            'auto_reply_daily_min' => '1',
            'auto_reply_daily_max' => '3',
            'auto_reply_issue_min' => '1',
            'auto_reply_issue_max' => '3',
            'auto_reply_forbid_start_hour' => '1',
            'auto_reply_forbid_end_hour' => '8',
            'wrong_refund_streak' => '2',
            'wrong_refund_percent' => '100',
            'after_draw_delete_wrong_streak' => '2',
            'auto_reply_items' => '',
            'post_update_time' => '',
            'material_content_time' => '',
            'sale_material_content_time' => '',
            'waiting_display_content' => "资料等待更新中··· ···\n关注本站，精彩无限，中奖根本停不下来······",
        );

        foreach ($this->managedPostGeneratorStoredSettings($region, $config) as $key => $value) {
            $config[$key] = $value;
        }

        $this->managedPostGeneratorConfigCache[$region] = $config;

        return $config;
    }

    public function managedPostWaitingDisplayContent($region = 'macau')
    {
        $region = $this->normalizeManagedPostGeneratorRegion($region);
        $raw = trim((string) $this->app->settings()->get($this->managedPostGeneratorSettingsKey($region), ''));
        $stored = $raw !== '' ? json_decode($raw, true) : array();

        return $this->normalizeManagedPostWaitingDisplayContent(
            is_array($stored) ? ($stored['waiting_display_content'] ?? '') : ''
        );
    }

    public function saveManagedPostWaitingDisplayContent($region, $content)
    {
        $region = $this->normalizeManagedPostGeneratorRegion($region);
        $config = $this->managedPostGeneratorConfig($region);
        $config['waiting_display_content'] = $this->normalizeManagedPostWaitingDisplayContent($content);
        $settings = $this->normalizeManagedPostGeneratorSettingsPayload($config, $config);

        $this->app->settings()->setMany('post_generator', array(
            $this->managedPostGeneratorSettingsKey($region) => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));
        $this->clearManagedPostGeneratorConfigCache($region);

        return (string) ($settings['waiting_display_content'] ?? '');
    }

    public function saveManagedPostGeneratorSettings(array $payload, array $actor)
    {
        $region = $this->normalizeManagedPostGeneratorRegion((string) ($payload['generator_type'] ?? ($payload['region'] ?? 'macau')));
        $config = $this->managedPostGeneratorConfig($region);
        $settings = $this->normalizeManagedPostGeneratorSettingsPayload($payload, $config);
        $materialStateOnly = !empty($payload['material_content_state_only']);

        $this->app->settings()->setMany('post_generator', array(
            $this->managedPostGeneratorSettingsKey($region) => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));
        $this->clearManagedPostGeneratorConfigCache($region);
        $skipScheduleSync = !empty($payload['skip_schedule_sync']);
        if (!$materialStateOnly && !$skipScheduleSync) {
            $this->syncManagedPostGeneratorWindowsScheduleTasks();
        }
        $this->recordOperation((int) ($actor['id'] ?? 0), 'posts', 'save_generator_settings', 'settings', 0, 'save post generator settings: ' . $region);

        if ($skipScheduleSync) {
            $settings['region'] = $region;
            return $settings;
        }

        return $this->managedPostGeneratorConfig($region);
    }

    public function saveManagedPostGenerationMode(array $payload, array $actor)
    {
        $region = $this->normalizeManagedPostGeneratorRegion((string) ($payload['generator_type'] ?? ($payload['region'] ?? 'macau')));
        $config = $this->managedPostGeneratorConfig($region);
        $settings = $this->normalizeManagedPostGeneratorSettingsPayload($config, $config);
        $settings['generation_mode'] = $this->normalizeManagedPostGenerationMode((string) ($payload['generation_mode'] ?? ''));

        $this->app->settings()->setMany('post_generator', array(
            $this->managedPostGeneratorSettingsKey($region) => json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));
        $this->clearManagedPostGeneratorConfigCache($region);
        $this->recordOperation((int) ($actor['id'] ?? 0), 'posts', 'save_generation_mode', 'settings', 0, 'save post generation mode: ' . $region);

        return $this->managedPostGeneratorConfig($region);
    }

    protected function clearManagedPostGeneratorConfigCache($region = null)
    {
        if ($region === null || $region === '') {
            $this->managedPostGeneratorConfigCache = array();

            return;
        }

        unset($this->managedPostGeneratorConfigCache[$this->normalizeManagedPostGeneratorRegion($region)]);
    }

    public function syncManagedPostGeneratorWindowsScheduleTasks()
    {
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN' || !function_exists('exec')) {
            return array('created' => array(), 'deleted' => array());
        }

        $scriptPath = $this->app->basePath('cli/run_post_generator_schedule.php');
        if (!is_file($scriptPath)) {
            return array('created' => array(), 'deleted' => array());
        }

        $phpBinary = $this->managedPostGeneratorSchedulePhpBinary();
        if ($phpBinary === '') {
            return array('created' => array(), 'deleted' => array());
        }

        $taskRun = str_replace('/', '\\', $phpBinary) . ' ' . str_replace('/', '\\', $scriptPath);
        $taskNames = array();
        foreach (array('macau', 'hongkong') as $region) {
            $config = $this->managedPostGeneratorConfig($region);
            foreach (array('post_update_time', 'material_content_time', 'sale_material_content_time') as $timeKey) {
                $timeValue = $this->normalizeManagedPostGeneratorTime($config[$timeKey] ?? '');
                if ($timeValue !== '') {
                    $taskNames[$timeValue] = '888888ForumPostGeneratorAt' . str_replace(':', '', $timeValue);
                }
            }
            if (!empty($config['auto_reply_enabled'])) {
                for ($autoReplyHour = 0; $autoReplyHour < 24; $autoReplyHour++) {
                    $timeValue = str_pad((string) $autoReplyHour, 2, '0', STR_PAD_LEFT) . ':26';
                    $taskNames[$timeValue] = '888888ForumPostGeneratorAt' . str_replace(':', '', $timeValue);
                }
            }
        }
        ksort($taskNames);

        $storedTaskNames = $this->managedPostGeneratorStoredWindowsTaskNames();
        $storedTaskNames[] = '888888ForumPostGeneratorSchedule';
        $deletedTaskNames = array();
        foreach (array_values(array_unique($storedTaskNames)) as $taskName) {
            if ($taskName === '') {
                continue;
            }
            $deleteCommand = 'schtasks.exe /Delete /TN ' . $this->windowsCommandQuote($taskName) . ' /F';
            $deleteExitCode = 0;
            exec($deleteCommand, $deleteOutput, $deleteExitCode);
            if ((int) $deleteExitCode === 0) {
                $deletedTaskNames[] = $taskName;
            }
        }

        $createdTaskNames = array();
        foreach ($taskNames as $timeValue => $taskName) {
            $createCommand = 'schtasks.exe /Create /TN ' . $this->windowsCommandQuote($taskName)
                . ' /SC DAILY /ST ' . $this->windowsCommandQuote($timeValue)
                . ' /TR ' . $this->windowsCommandQuote($taskRun)
                . ' /F';
            $createExitCode = 0;
            exec($createCommand, $createOutput, $createExitCode);
            if ((int) $createExitCode === 0) {
                $createdTaskNames[] = $taskName;
            }
        }

        $this->app->settings()->setMany('post_generator', array(
            'post_generator.windows_schedule_tasks' => json_encode($createdTaskNames, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ));

        return array(
            'created' => $createdTaskNames,
            'deleted' => $deletedTaskNames,
        );
    }

    protected function managedPostGeneratorSchedulePhpBinary()
    {
        $phpBinary = trim((string) PHP_BINARY);
        $phpDir = $phpBinary !== '' ? dirname($phpBinary) : '';
        if ($phpDir !== '') {
            $phpWin = $phpDir . DIRECTORY_SEPARATOR . 'php-win.exe';
            if (is_file($phpWin)) {
                return $phpWin;
            }
        }

        return is_file($phpBinary) ? $phpBinary : '';
    }

    protected function managedPostGeneratorStoredWindowsTaskNames()
    {
        $raw = trim((string) $this->app->settings()->get('post_generator.windows_schedule_tasks', ''));
        if ($raw === '') {
            return array();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return array();
        }

        return array_values(array_filter(array_map('strval', $decoded), static function ($value) {
            return $value !== '';
        }));
    }

    protected function windowsCommandQuote($value)
    {
        return '"' . str_replace('"', '""', (string) $value) . '"';
    }

    protected function managedPostGeneratorStoredSettings($region, array $config)
    {
        $raw = trim((string) $this->app->settings()->get($this->managedPostGeneratorSettingsKey($region), ''));
        if ($raw === '') {
            return array();
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return array();
        }

        return $this->normalizeManagedPostGeneratorSettingsPayload($decoded, $config);
    }

    protected function managedPostGeneratorSettingsKey($region)
    {
        return 'post_generator.settings.' . $this->normalizeManagedPostGeneratorRegion($region);
    }

    protected function normalizeManagedPostGeneratorRegion($region)
    {
        return (string) $region === 'hongkong' ? 'hongkong' : 'macau';
    }

    protected function normalizeManagedPostGenerationMode($value)
    {
        $value = trim((string) $value);

        return in_array($value, array('auto', 'manual'), true) ? $value : '';
    }

    protected function normalizeManagedPostGeneratorSettingsPayload(array $payload, array $config)
    {
        $templateMap = array();
        foreach ((array) ($config['template_groups'] ?? array()) as $group) {
            foreach ((array) ($group['items'] ?? array()) as $item) {
                $key = (string) ($item['key'] ?? '');
                if ($key !== '') {
                    $templateMap[$key] = true;
                }
            }
        }

        $titleMiddleValue = trim((string) ($payload['title_middle'] ?? ($config['title_middle'] ?? '')));
        if (in_array($titleMiddleValue, array('[帖子类型]', '[随机作者]'), true)) {
            $titleMiddleValue = '';
        }
        $authorNicknameValue = trim((string) ($payload['author_nickname'] ?? ($config['author_nickname'] ?? '')));
        if (in_array($authorNicknameValue, array('[随机作者]', '[帖子作者]', '[帖子类型]'), true)) {
            $authorNicknameValue = '';
        }

        $templatesValue = array_key_exists('templates', $payload)
            ? $payload['templates']
            : ($config['templates'] ?? array());
        if (array_key_exists('manage_templates', $payload)) {
            $manageTemplatesValue = $payload['manage_templates'];
        } elseif (array_key_exists('manage_templates_submitted', $payload)) {
            $manageTemplatesValue = array();
        } else {
            $manageTemplatesValue = $config['manage_templates'] ?? ($payload['templates'] ?? array());
        }

        return array(
            'generation_mode' => $this->normalizeManagedPostGenerationMode((string) ($payload['generation_mode'] ?? ($config['generation_mode'] ?? ''))),
            'segment_no' => $this->normalizeManagedPostGeneratorRangeValue($payload['segment_no'] ?? ($config['segment_no'] ?? ''), 1, 3),
            'top_scope' => $this->normalizeManagedPostGeneratorChoice((string) ($payload['top_scope'] ?? ($config['top_scope'] ?? 'top_1')), array_keys($this->managedPostGeneratorTopOptions())),
            'preset_zodiac_min' => $this->normalizeManagedPostGeneratorRangeValue($payload['preset_zodiac_min'] ?? ($config['preset_zodiac_min'] ?? ''), 0, 12),
            'preset_zodiac_max' => $this->normalizeManagedPostGeneratorRangeValue($payload['preset_zodiac_max'] ?? ($config['preset_zodiac_max'] ?? ''), 0, 12),
            'preset_segment_min' => $this->normalizeManagedPostGeneratorRangeValue($payload['preset_segment_min'] ?? ($config['preset_segment_min'] ?? ''), 0, 99),
            'preset_segment_max' => $this->normalizeManagedPostGeneratorRangeValue($payload['preset_segment_max'] ?? ($config['preset_segment_max'] ?? ''), 0, 99),
            'preset_record_min' => $this->normalizeManagedPostGeneratorRangeValue($payload['preset_record_min'] ?? ($config['preset_record_min'] ?? ''), 1, 99),
            'preset_record_max' => $this->normalizeManagedPostGeneratorRangeValue($payload['preset_record_max'] ?? ($config['preset_record_max'] ?? ''), 1, 99),
            'preset_record_rate_min' => $this->normalizeManagedPostGeneratorRangeValue($payload['preset_record_rate_min'] ?? ($config['preset_record_rate_min'] ?? ''), 0, 100),
            'preset_record_rate_max' => $this->normalizeManagedPostGeneratorRangeValue($payload['preset_record_rate_max'] ?? ($config['preset_record_rate_max'] ?? ''), 0, 100),
            'preset_segments' => $this->normalizeManagedPostGeneratorArray($payload['preset_segments'] ?? ($config['preset_segments'] ?? array()), $this->managedPostGeneratorAllowedValues((array) ($config['segment_options'] ?? array()))),
            'title_prefix' => $this->normalizeManagedPostGeneratorText($payload['title_prefix'] ?? ($config['title_prefix'] ?? ''), 120),
            'title_middle' => $this->normalizeManagedPostGeneratorText($titleMiddleValue, 120),
            'title_middle_wrap' => $this->normalizeManagedPostGeneratorText($payload['title_middle_wrap'] ?? ($config['title_middle_wrap'] ?? ''), 20),
            'author_nickname' => $this->normalizeManagedPostGeneratorText($authorNicknameValue, 60),
            'author_nickname_pool' => $this->normalizeManagedPostGeneratorText($payload['author_nickname_pool'] ?? ($config['author_nickname_pool'] ?? ''), 2000),
            'title_prefix_color_mode' => $this->normalizeManagedTitleColorMode((string) ($payload['title_prefix_color_mode'] ?? ($config['title_prefix_color_mode'] ?? ''))),
            'title_prefix_color_value' => $this->normalizeManagedTitleColorValue((string) ($payload['title_prefix_color_value'] ?? ($config['title_prefix_color_value'] ?? '#2563eb'))),
            'title_middle_color_mode' => $this->normalizeManagedTitleColorMode((string) ($payload['title_middle_color_mode'] ?? ($config['title_middle_color_mode'] ?? ''))),
            'title_middle_color_value' => $this->normalizeManagedTitleColorValue((string) ($payload['title_middle_color_value'] ?? ($config['title_middle_color_value'] ?? '#2563eb'))),
            'author_nickname_color_mode' => $this->normalizeManagedTitleColorMode((string) ($payload['author_nickname_color_mode'] ?? ($config['author_nickname_color_mode'] ?? ''))),
            'author_nickname_color_value' => $this->normalizeManagedTitleColorValue((string) ($payload['author_nickname_color_value'] ?? ($config['author_nickname_color_value'] ?? '#2563eb'))),
            'title_font_size' => $this->normalizeManagedPostGeneratorChoice((string) ($payload['title_font_size'] ?? ($config['title_font_size'] ?? '')), array('12', '13', '14', '15', '16', '17', '18', '20', '22', '24')),
            'title_font_weight' => $this->normalizeManagedPostGeneratorChoice((string) ($payload['title_font_weight'] ?? ($config['title_font_weight'] ?? '')), array('400', '500', '600', '700', '800', '900')),
            'target_zodiac' => $this->normalizeManagedPostGeneratorArray($payload['target_zodiac'] ?? ($config['target_zodiac'] ?? array()), $this->managedPostGeneratorAllowedValues((array) ($config['zodiac_options'] ?? array()))),
            'target_number' => $this->normalizeManagedPostGeneratorArray($payload['target_number'] ?? ($config['target_number'] ?? array()), $this->managedPostGeneratorAllowedValues((array) ($config['number_options'] ?? array()))),
            'target_wave' => $this->normalizeManagedPostGeneratorArray($payload['target_wave'] ?? ($config['target_wave'] ?? array()), $this->managedPostGeneratorAllowedValues((array) ($config['wave_options'] ?? array()))),
            'target_element' => $this->normalizeManagedPostGeneratorArray($payload['target_element'] ?? ($config['target_element'] ?? array()), $this->managedPostGeneratorAllowedValues((array) ($config['element_options'] ?? array()))),
            'target_head' => $this->normalizeManagedPostGeneratorArray($payload['target_head'] ?? ($config['target_head'] ?? array()), $this->managedPostGeneratorAllowedValues((array) ($config['head_options'] ?? array()))),
            'target_tail' => $this->normalizeManagedPostGeneratorArray($payload['target_tail'] ?? ($config['target_tail'] ?? array()), $this->managedPostGeneratorAllowedValues((array) ($config['tail_options'] ?? array()))),
            'templates' => $this->normalizeManagedPostGeneratorArray($templatesValue, array_keys($templateMap)),
            'manage_templates' => $this->normalizeManagedPostGeneratorArray($manageTemplatesValue, array_keys($templateMap)),
            'auto_reply_enabled' => array_key_exists('auto_reply_enabled', $payload)
                ? (!empty($payload['auto_reply_enabled']) ? '1' : '')
                : (!empty($config['auto_reply_enabled']) ? '1' : ''),
            'auto_reply_count' => $this->normalizeManagedPostGeneratorRangeValue($payload['auto_reply_count'] ?? ($config['auto_reply_count'] ?? '5'), 1, 99),
            'auto_reply_base_min' => $this->normalizeManagedPostGeneratorRangeValue($payload['auto_reply_base_min'] ?? ($config['auto_reply_base_min'] ?? '2'), 1, 99),
            'auto_reply_base_max' => $this->normalizeManagedPostGeneratorRangeValue($payload['auto_reply_base_max'] ?? ($config['auto_reply_base_max'] ?? '5'), 1, 99),
            'auto_reply_daily_min' => $this->normalizeManagedPostGeneratorRangeValue($payload['auto_reply_daily_min'] ?? ($config['auto_reply_daily_min'] ?? '1'), 0, 99),
            'auto_reply_daily_max' => $this->normalizeManagedPostGeneratorRangeValue($payload['auto_reply_daily_max'] ?? ($config['auto_reply_daily_max'] ?? '3'), 0, 99),
            'auto_reply_issue_min' => $this->normalizeManagedPostGeneratorRangeValue($payload['auto_reply_issue_min'] ?? ($config['auto_reply_issue_min'] ?? '1'), 1, 99),
            'auto_reply_issue_max' => $this->normalizeManagedPostGeneratorRangeValue($payload['auto_reply_issue_max'] ?? ($config['auto_reply_issue_max'] ?? '3'), 1, 99),
            'auto_reply_forbid_start_hour' => $this->normalizeManagedPostGeneratorRangeValue($payload['auto_reply_forbid_start_hour'] ?? ($config['auto_reply_forbid_start_hour'] ?? '1'), 0, 23),
            'auto_reply_forbid_end_hour' => $this->normalizeManagedPostGeneratorRangeValue($payload['auto_reply_forbid_end_hour'] ?? ($config['auto_reply_forbid_end_hour'] ?? '8'), 0, 23),
            'wrong_refund_streak' => $this->normalizeManagedPostGeneratorRangeValue($payload['wrong_refund_streak'] ?? ($config['wrong_refund_streak'] ?? '2'), 2, 99),
            'wrong_refund_percent' => $this->normalizeManagedPostGeneratorRangeValue($payload['wrong_refund_percent'] ?? ($config['wrong_refund_percent'] ?? '100'), 0, 999),
            'after_draw_delete_wrong_streak' => $this->normalizeManagedPostGeneratorRangeValue($payload['after_draw_delete_wrong_streak'] ?? ($config['after_draw_delete_wrong_streak'] ?? '2'), 2, 99),
            'auto_reply_items' => '',
            'is_blank_content' => !empty($payload['is_blank_content']) ? '1' : '',
            'is_fake_after_open' => !empty($payload['is_fake_after_open']) ? '1' : '',
            'post_update_time' => $this->normalizeManagedPostGeneratorTime(
                $payload['post_update_time'] ?? ($config['post_update_time'] ?? ($config['material_content_time'] ?? ''))
            ),
            'sale_material_content_time' => $this->normalizeManagedPostGeneratorTime(
                array_key_exists('sale_material_content_time', $payload)
                    ? $payload['sale_material_content_time']
                    : ($config['sale_material_content_time'] ?? '')
            ),
            'material_content_time' => $this->normalizeManagedPostGeneratorTime($payload['material_content_time'] ?? ($config['material_content_time'] ?? '')),
            'waiting_display_content' => $this->normalizeManagedPostWaitingDisplayContent(
                $payload['waiting_display_content'] ?? ($config['waiting_display_content'] ?? '')
            ),
        );
    }

    protected function normalizeManagedPostWaitingDisplayContent($value)
    {
        $value = str_replace(array("\r\n", "\r"), "\n", trim((string) $value));
        $lines = array();
        foreach (explode("\n", $value) as $line) {
            $line = trim((string) preg_replace('/[^\S\n]+/u', ' ', (string) $line));
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        $value = implode("\n", $lines);
        if ($value === '') {
            return "资料等待更新中··· ···\n关注本站，精彩无限，中奖根本停不下来······";
        }

        return function_exists('mb_substr')
            ? mb_substr($value, 0, 300, 'UTF-8')
            : substr($value, 0, 300);
    }

    protected function normalizeManagedPostGeneratorText($value, $maxLength)
    {
        $value = trim((string) $value);
        $maxLength = max(1, (int) $maxLength);

        if (function_exists('mb_substr')) {
            return mb_substr($value, 0, $maxLength, 'UTF-8');
        }

        return substr($value, 0, $maxLength);
    }

    protected function normalizeManagedPostGeneratorRangeValue($value, $min, $max)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $number = (int) preg_replace('/\D+/', '', $value);
        $number = max((int) $min, min((int) $max, $number));

        return (string) $number;
    }

    protected function normalizeManagedPostGeneratorTime($value)
    {
        $value = trim((string) $value);

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) ? $value : '';
    }

    protected function normalizeManagedPostGeneratorChoice($value, array $allowed)
    {
        $value = trim((string) $value);

        return in_array($value, $allowed, true) ? $value : '';
    }

    protected function managedPostGeneratorAllowedValues(array $options)
    {
        $values = array();
        foreach ($options as $option) {
            $value = (string) ($option['value'] ?? '');
            if ($value !== '') {
                $values[] = $value;
            }
        }

        return array_values(array_unique($values));
    }

    protected function normalizeManagedPostGeneratorArray($values, array $allowed)
    {
        $values = is_array($values) ? $values : array();
        $allowedMap = array_fill_keys(array_map('strval', $allowed), true);
        $result = array();

        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value !== '' && isset($allowedMap[$value])) {
                $result[] = $value;
            }
        }

        return array_values(array_unique($result));
    }

    protected function managedPostGeneratorCreatedAt($timeValue, $dateValue = '', $backDays = 0, $randomTime = false)
    {
        $timeValue = $this->normalizeManagedPostGeneratorTime($timeValue);
        if ($timeValue === '' && !$randomTime) {
            return '';
        }

        $dateValue = trim((string) $dateValue);
        $date = substr($this->now(), 0, 10);
        if (preg_match('/^\d{4}-\d{2}-\d{2}/', $dateValue, $dateMatches)) {
            $date = $dateMatches[0];
        }

        $backDays = max(0, (int) $backDays);
        if ($backDays > 0) {
            $dateTime = strtotime($date . ' 00:00:00');
            if ($dateTime !== false) {
                $date = date('Y-m-d', strtotime('-' . $backDays . ' days', $dateTime));
            }
        }

        if ($randomTime) {
            return $date . ' ' . sprintf('%02d:%02d:%02d', mt_rand(0, 23), mt_rand(0, 59), mt_rand(0, 59));
        }

        return $date . ' ' . $timeValue . ':00';
    }

    protected function managedPostGeneratorRandomRange($min, $max)
    {
        $min = max(0, (int) $min);
        $max = max(0, (int) $max);
        if ($min > $max) {
            $swap = $min;
            $min = $max;
            $max = $swap;
        }

        if ($min === $max) {
            return $min;
        }

        return mt_rand($min, $max);
    }

    protected function managedPostGeneratorBuildPlan(array $selectedTemplates, array $segmentNos, $segmentMin, $segmentMax, $region = '', $maintainSegmentCounts = true, $fillSegmentRangeAfterDelete = false)
    {
        $selectedTemplates = array_values($selectedTemplates);
        $segmentNos = array_values($segmentNos);
        if ($selectedTemplates === array()) {
            return array();
        }
        if ($segmentNos === array()) {
            $segmentNos = array(1);
        }

        $plan = array();
        $templateIndex = 0;
        if (!$maintainSegmentCounts) {
            foreach ($selectedTemplates as $index => $templateKey) {
                $plan[] = array(
                    'template_key' => $templateKey,
                    'segment_no' => max(1, min(3, (int) $segmentNos[$index % count($segmentNos)])),
                );
            }

            return $plan;
        }

        $segmentMin = max(0, (int) $segmentMin);
        $segmentMax = max(0, (int) $segmentMax);
        if ($segmentMin > $segmentMax) {
            $swap = $segmentMin;
            $segmentMin = $segmentMax;
            $segmentMax = $swap;
        }
        if ($segmentMax <= 0) {
            return array();
        }

        $region = $region === 'hongkong' ? 'hongkong' : 'macau';
        foreach ($segmentNos as $segmentNo) {
            $segmentNo = max(1, min(3, (int) $segmentNo));
            $row = $this->db()->fetch(
                'SELECT COUNT(*) AS total_count
                 FROM posts
                 LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
                 WHERE posts.region = :region
                   AND posts.status = :status
                   AND posts.deleted_at IS NULL
                   AND COALESCE(post_meta.is_hidden, 0) = 0
                   AND COALESCE(post_meta.segment_no, 1) = :segment_no',
                array(
                    'region' => $region,
                    'status' => 'published',
                    'segment_no' => $segmentNo,
                )
            );
            $currentCount = (int) ($row['total_count'] ?? 0);
            if (!empty($fillSegmentRangeAfterDelete)) {
                if ($currentCount >= $segmentMax) {
                    continue;
                }
                $targetCount = $this->managedPostGeneratorRandomRange(max($segmentMin, $currentCount + 1), $segmentMax);
            } else {
                if ($currentCount >= $segmentMin) {
                    continue;
                }
                $targetCount = $this->managedPostGeneratorRandomRange($segmentMin, $segmentMax);
            }
            $missingCount = max(0, $targetCount - $currentCount);
            for ($index = 0; $index < $missingCount; $index++) {
                $plan[] = array(
                    'template_key' => $selectedTemplates[$templateIndex % count($selectedTemplates)],
                    'segment_no' => $segmentNo,
                );
                $templateIndex++;
            }
        }

        return $plan;
    }

    protected function managedPostGeneratorOverflowDeleteIds($region, array $config)
    {
        $segmentMax = (int) $this->normalizeManagedPostGeneratorRangeValue($config['preset_segment_max'] ?? '', 0, 99);
        $segmentMin = (int) $this->normalizeManagedPostGeneratorRangeValue($config['preset_segment_min'] ?? '', 0, 99);
        if ($segmentMin > $segmentMax) {
            $swap = $segmentMin;
            $segmentMin = $segmentMax;
            $segmentMax = $swap;
        }
        if ($segmentMax <= 0) {
            return array();
        }

        $segmentNos = $this->normalizeManagedPostGeneratorArray(
            $config['preset_segments'] ?? array(),
            $this->managedPostGeneratorAllowedValues((array) ($config['segment_options'] ?? array()))
        );
        if ($segmentNos === array()) {
            $segmentNos = array('1', '2', '3');
        }

        $region = $this->normalizeManagedPostGeneratorRegion($region);
        $deleteIds = array();
        foreach ($segmentNos as $segmentNo) {
            $segmentNo = max(1, min(3, (int) $segmentNo));
            $rows = $this->db()->fetchAll(
                'SELECT posts.id
                 FROM posts
                 LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
                 WHERE posts.region = :region
                   AND posts.status = :status
                   AND posts.deleted_at IS NULL
                   AND COALESCE(post_meta.is_hidden, 0) = 0
                   AND COALESCE(post_meta.segment_no, 1) = :segment_no
                 ORDER BY COALESCE(NULLIF(post_meta.segment_sort, 0), posts.id) DESC,
                          posts.created_at ASC,
                          posts.id ASC',
                array(
                    'region' => $region,
                    'status' => 'published',
                    'segment_no' => $segmentNo,
                )
            );
            $overflowCount = count($rows) - $segmentMax;
            if ($overflowCount <= 0) {
                continue;
            }
            foreach (array_slice($rows, 0, $overflowCount) as $row) {
                $deleteIds[] = (int) ($row['id'] ?? 0);
            }
        }

        return array_values(array_unique(array_filter($deleteIds)));
    }

    protected function managedPostGeneratorForecastContent($region, $issueTail, $authorNickname, $templateLabel, array $options)
    {
        $recordMin = (int) ($options['record_min'] ?? 0);
        $recordMax = (int) ($options['record_max'] ?? 0);
        $rateMin = (int) ($options['rate_min'] ?? 0);
        $rateMax = (int) ($options['rate_max'] ?? 0);
        $normalizedTemplateLabel = $this->managedNormalizeForecastTypeText($templateLabel);
        $isKillTemplate = mb_strpos($normalizedTemplateLabel, '绝杀', 0, 'UTF-8') !== false;
        if ($isKillTemplate) {
            $rateMin = 80;
            $rateMax = 90;
        }
        $recordCount = $this->managedPostGeneratorRandomRange($recordMin, $recordMax);
        if ($recordCount <= 0) {
            $recordCount = 8;
        }
        $predictionBracketPairs = array(
            array('【', '】'),
            array('〖', '〗'),
            array('《', '》'),
            array('｛', '｝'),
            array('〔', '〕'),
            array('『', '』'),
        );
        $predictionBracketPair = isset($options['prediction_bracket_pair']) && is_array($options['prediction_bracket_pair'])
            ? array_values((array) $options['prediction_bracket_pair'])
            : array();
        $predictionTemplateLayout = array();
        $predictionTemplateContent = trim((string) ($options['prediction_template_content'] ?? ''));
        if ($predictionTemplateContent !== '') {
            $predictionTemplateIssuePrefix = trim((string) ($options['prediction_template_issue_prefix'] ?? ''));
            if ($predictionTemplateIssuePrefix === '') {
                $predictionTemplateIssuePrefix = (string) ((int) $issueTail) . html_entity_decode('&#26399;:', ENT_QUOTES, 'UTF-8');
            }
            $predictionTemplateLayout = $this->app->posts()->forecastPredictionTemplateLayout(
                $predictionTemplateContent,
                $predictionTemplateIssuePrefix
            );
        }
        if (
            isset($predictionTemplateLayout['bracket_pair'])
            && is_array($predictionTemplateLayout['bracket_pair'])
            && count((array) $predictionTemplateLayout['bracket_pair']) >= 2
        ) {
            $predictionBracketPair = array_values((array) $predictionTemplateLayout['bracket_pair']);
        }
        if (count($predictionBracketPair) < 2 || trim((string) $predictionBracketPair[0]) === '' || trim((string) $predictionBracketPair[1]) === '') {
            $predictionBracketPair = $predictionBracketPairs[mt_rand(0, count($predictionBracketPairs) - 1)];
        }
        $options['prediction_bracket_pair'] = array(
            (string) $predictionBracketPair[0],
            (string) $predictionBracketPair[1],
        );
        $options['prediction_template_layout'] = $predictionTemplateLayout;
        if (empty($options['normal_code_hot_numbers'])) {
            $options['normal_code_hot_numbers'] = array();
        }

        $issueNumber = preg_match('/^\d+$/', (string) $issueTail) ? (int) $issueTail : 0;
        if ($issueNumber <= 0) {
            $issueNumber = $recordCount + 1;
        }

        $startIssue = max(1, $issueNumber - $recordCount + 1);
        $issueRows = array();
        $drawnCount = 0;
        for ($index = 0; $index < $recordCount; $index++) {
            $rowIssue = $startIssue + $index;
            $rowIssueTail = str_pad((string) $rowIssue, 3, '0', STR_PAD_LEFT);
            $drawInfo = $this->managedPostGeneratorDrawInfo((string) $region, $rowIssueTail);
            if (empty($drawInfo['has_draw']) && array_key_exists('include_pending', $options) && empty($options['include_pending'])) {
                continue;
            }
            if (!empty($drawInfo['has_draw'])) {
                $drawnCount++;
            }
            $issueRows[] = array(
                'issue_tail' => $rowIssueTail,
                'draw_info' => $drawInfo,
            );
        }

        $firstIssueTail = '';
        $dateBackDays = 0;
        if (!empty($issueRows)) {
            $firstIssueTail = (string) ($issueRows[0]['issue_tail'] ?? '');
            if (preg_match('/^\d+$/', $firstIssueTail)) {
                $dateBackDays = max(0, $issueNumber - (int) $firstIssueTail);
            }
        }

        $rate = $this->managedPostGeneratorRandomRange($rateMin, $rateMax);
        $fixedHitCount = $drawnCount > 1 ? 2 : 1;
        $hitCount = $drawnCount > 0
            ? max($fixedHitCount, min($drawnCount, (int) ceil($drawnCount * $rate / 100)))
            : 0;
        if ($drawnCount > 2 && $rate < 100 && $hitCount >= $drawnCount) {
            $hitCount = $drawnCount - 1;
        }
        $hitText = html_entity_decode('&#20013;', ENT_QUOTES, 'UTF-8');
        $killHitText = html_entity_decode('&#20934;', ENT_QUOTES, 'UTF-8');
        $wrongText = html_entity_decode('&#38169;', ENT_QUOTES, 'UTF-8');
        $statuses = array();
        if ($drawnCount > 0) {
            $missCount = max(0, $drawnCount - $hitCount);
            $middleCount = max(0, $drawnCount - 2);
            $missCount = min($missCount, (int) ceil($middleCount / 2));
            $missPositions = array();
            for ($attempt = 0; $attempt < 20 && count($missPositions) < $missCount; $attempt++) {
                $candidatePositions = $middleCount > 0 ? range(1, $drawnCount - 2) : array();
                shuffle($candidatePositions);
                $candidateMissPositions = array();
                foreach ($candidatePositions as $position) {
                    if (count($candidateMissPositions) >= $missCount) {
                        break;
                    }
                    if (
                        isset($candidateMissPositions[$position - 1])
                        || isset($candidateMissPositions[$position + 1])
                    ) {
                        continue;
                    }
                    $candidateMissPositions[$position] = true;
                }
                if (count($candidateMissPositions) > count($missPositions)) {
                    $missPositions = $candidateMissPositions;
                }
            }
            for ($position = 1; $position < $drawnCount - 1 && count($missPositions) < $missCount; $position++) {
                if (isset($missPositions[$position - 1]) || isset($missPositions[$position + 1])) {
                    continue;
                }
                $missPositions[$position] = true;
            }
            $statuses = array_fill(0, $drawnCount, true);
            foreach (array_keys($missPositions) as $position) {
                $statuses[(int) $position] = false;
            }
            if (trim((string) $templateLabel) === '一码三中三' && $statuses !== array()) {
                $statuses[0] = true;
            }
            if ($statuses !== array()) {
                $previousWrong = false;
                foreach ($statuses as $statusPosition => $statusValue) {
                    if (!empty($statusValue)) {
                        $previousWrong = false;
                        continue;
                    }
                    if ($previousWrong) {
                        $statuses[(int) $statusPosition] = true;
                        $previousWrong = false;
                        continue;
                    }
                    $previousWrong = true;
                }
            }
        }

        if ($statuses !== array() && $this->managedPostGeneratorLatestResultIsWrong((string) ($options['previous_result_log'] ?? ''))) {
            $statuses[0] = true;
        }

        $lines = array();
        $logItems = array();
        $statusIndex = 0;
        $currentIssueTail = str_pad((string) $issueNumber, 3, '0', STR_PAD_LEFT);
        if ($statuses !== array()) {
            $historyDrawStatusIndexes = array();
            $historyStatusIndexForRequiredHits = 0;
            foreach ($issueRows as $issueRowForRequiredHits) {
                $requiredHitIssueTail = (string) ($issueRowForRequiredHits['issue_tail'] ?? '');
                $requiredHitDrawInfo = (array) ($issueRowForRequiredHits['draw_info'] ?? array());
                if (empty($requiredHitDrawInfo['has_draw'])) {
                    continue;
                }
                if ($requiredHitIssueTail !== $currentIssueTail) {
                    $historyDrawStatusIndexes[] = $historyStatusIndexForRequiredHits;
                }
                $historyStatusIndexForRequiredHits++;
            }
            if ($historyDrawStatusIndexes !== array()) {
                $statuses[(int) $historyDrawStatusIndexes[0]] = true;
                $statuses[(int) $historyDrawStatusIndexes[count($historyDrawStatusIndexes) - 1]] = true;
                $previousHistoryWrong = false;
                foreach ($historyDrawStatusIndexes as $historyDrawStatusIndex) {
                    $historyDrawStatusIndex = (int) $historyDrawStatusIndex;
                    if (!empty($statuses[$historyDrawStatusIndex] ?? false)) {
                        $previousHistoryWrong = false;
                        continue;
                    }
                    if ($previousHistoryWrong) {
                        $statuses[$historyDrawStatusIndex] = true;
                        $previousHistoryWrong = false;
                        continue;
                    }
                    $previousHistoryWrong = true;
                }
            }
        }
        $combinedNumberHitPlan = array();
        $historyHitIndexes = array();
        $historyStatusIndex = 0;
        foreach ($issueRows as $issueRowForPlan) {
            $planIssueTail = (string) ($issueRowForPlan['issue_tail'] ?? '');
            $planDrawInfo = (array) ($issueRowForPlan['draw_info'] ?? array());
            if (empty($planDrawInfo['has_draw'])) {
                continue;
            }
            if ($planIssueTail !== $currentIssueTail && !empty($statuses[$historyStatusIndex] ?? false)) {
                $historyHitIndexes[] = $historyStatusIndex;
            }
            $historyStatusIndex++;
        }
        if (count($historyHitIndexes) === 1) {
            $combinedNumberHitPlan[$historyHitIndexes[0]] = mt_rand(1, 100) <= 50;
        } elseif (count($historyHitIndexes) > 1) {
            shuffle($historyHitIndexes);
            $numberHitCount = max(1, min(count($historyHitIndexes) - 1, (int) round(count($historyHitIndexes) / 2)));
            foreach ($historyHitIndexes as $planIndex => $drawStatusIndex) {
                $combinedNumberHitPlan[$drawStatusIndex] = $planIndex < $numberHitCount;
            }
        }
        foreach ($issueRows as $issueRow) {
            $rowIssueTail = (string) ($issueRow['issue_tail'] ?? '');
            $drawInfo = (array) ($issueRow['draw_info'] ?? array());
            $hasDraw = !empty($drawInfo['has_draw']);
            $isHit = false;
            $drawStatusIndex = $statusIndex;
            if ($hasDraw) {
                $isHit = !empty($statuses[$drawStatusIndex] ?? false);
                $statusIndex++;
            }
            $predictionOptions = $options;
            if ($hasDraw && $rowIssueTail !== $currentIssueTail) {
                $predictionOptions['mixed_normal_hit_count'] = !empty($isHit) ? (string) mt_rand(1, 3) : '0';
                if (!empty($isHit) && array_key_exists($drawStatusIndex, $combinedNumberHitPlan)) {
                    $predictionOptions['combined_number_hit_for_history'] = !empty($combinedNumberHitPlan[$drawStatusIndex]) ? '1' : '';
                } else {
                    $predictionOptions['reduce_combined_number_hit_rate'] = '1';
                }
            } else {
                unset($predictionOptions['reduce_combined_number_hit_rate']);
                unset($predictionOptions['combined_number_hit_for_history']);
                unset($predictionOptions['mixed_normal_hit_count']);
            }
            if (!$hasDraw && empty($predictionOptions['normal_code_hot_numbers'])) {
                $predictionOptions['normal_code_hot_numbers'] = $this->managedPostGeneratorAiHotNumbers((string) $region);
            }
            $predictionInfo = $this->managedPostGeneratorPredictionInfo(
                (string) $templateLabel,
                $drawInfo,
                $isHit,
                $predictionOptions
            );
            if (
                !$hasDraw
                && trim((string) ($options['pending_prediction'] ?? '')) !== ''
            ) {
                $pendingPrediction = trim((string) ($options['pending_prediction'] ?? ''));
                $pendingPredictionFirstChar = mb_substr($pendingPrediction, 0, 1, 'UTF-8');
                $pendingPredictionLastChar = mb_substr($pendingPrediction, -1, 1, 'UTF-8');
                $pendingPredictionWrapped = false;
                foreach ($predictionBracketPairs as $pendingPredictionBracketPair) {
                    $pendingPredictionLeftBracket = (string) ($pendingPredictionBracketPair[0] ?? '');
                    $pendingPredictionRightBracket = (string) ($pendingPredictionBracketPair[1] ?? '');
                    if (
                        $pendingPredictionLeftBracket !== ''
                        && $pendingPredictionRightBracket !== ''
                        && $pendingPredictionFirstChar === $pendingPredictionLeftBracket
                        && $pendingPredictionLastChar === $pendingPredictionRightBracket
                    ) {
                        $pendingPredictionWrapped = true;
                        break;
                    }
                }
                if (!$pendingPredictionWrapped) {
                    $pendingPrediction = (string) $predictionBracketPair[0]
                        . $pendingPrediction
                        . (string) $predictionBracketPair[1];
                }
                $predictionInfo['prediction'] = $pendingPrediction;
            }
            $issueText = (string) ((int) $rowIssueTail) . html_entity_decode('&#26399;:', ENT_QUOTES, 'UTF-8');
            $authorNicknameText = trim((string) $authorNickname);
            $authorIconPair = $this->managedAuthorIconPair($authorNicknameText);
            $authorText = $authorNicknameText !== '' ? $authorIconPair[0] . $authorNicknameText . $authorIconPair[1] : '';
            $line = $issueText
                . ($authorText !== '' ? ' ' . $authorText : '')
                . '  ' . trim((string) $templateLabel)
                . '  ' . (string) ($predictionInfo['prediction'] ?? '')
                . '  ' . html_entity_decode('&#24320;:', ENT_QUOTES, 'UTF-8') . ' ' . (string) ($predictionInfo['open_result'] ?? '');
            if ($hasDraw) {
                $statusText = !empty($isHit) ? ($isKillTemplate ? $killHitText : $hitText) : $wrongText;
                $displayStatusText = !empty($isHit)
                    ? $killHitText
                    : $wrongText;
                $verifiedLine = $line . ' ' . $displayStatusText;
                $verifiedStats = $this->managedForecastRecordStats((string) $region, $verifiedLine, '', true, false);
                $verifiedStatuses = (array) ($verifiedStats['statuses'] ?? array());
                $verifiedStatusText = $verifiedStatuses !== array()
                    ? (string) $verifiedStatuses[count($verifiedStatuses) - 1]
                    : '';
                if ($verifiedStatusText !== '') {
                    $verifiedIsHit = in_array($verifiedStatusText, array('准', '中', '赢', '發', '发'), true);
                    $statusText = $verifiedIsHit ? ($isKillTemplate ? $killHitText : $hitText) : $wrongText;
                    $displayStatusText = $verifiedIsHit ? $killHitText : $wrongText;
                }
                $line .= ' ' . $displayStatusText;
                $logItems[] = $rowIssueTail . $statusText;
            }
            $lines[] = $line;
        }

        return array(
            'content' => implode("\n", $lines),
            'recent_result_log' => implode(',', $logItems),
            'date_back_days' => $dateBackDays,
        );
    }

    protected function managedPostGeneratorAiHotNumbers($region)
    {
        try {
            $normalCodeForecast = $this->app->prediction()->buildForecast(
                (string) $region,
                null,
                false,
                array(
                    'zodiac_type' => '',
                    'number_type' => 'number_10',
                    'pingte_type' => '',
                    'other_type' => '',
                )
            );
            $normalCodeHotNumbers = array();
            foreach ((array) ($normalCodeForecast['display_payloads']['number']['values'] ?? array()) as $normalCodeHotNumber) {
                $normalCodeHotNumber = (int) $normalCodeHotNumber;
                if ($normalCodeHotNumber >= 1 && $normalCodeHotNumber <= 49 && !in_array($normalCodeHotNumber, $normalCodeHotNumbers, true)) {
                    $normalCodeHotNumbers[] = $normalCodeHotNumber;
                }
                if (count($normalCodeHotNumbers) >= 10) {
                    break;
                }
            }

            return $normalCodeHotNumbers;
        } catch (\Throwable $exception) {
            return array();
        }
    }

    protected function managedPostGeneratorForecastContentForPost(array $post, array $meta, $issueTail)
    {
        $region = (string) ($post['region'] ?? 'macau') === 'hongkong' ? 'hongkong' : 'macau';
        $issueTail = str_pad(substr(preg_replace('/\D+/', '', (string) $issueTail), -3), 3, '0', STR_PAD_LEFT);
        if ($issueTail === '000') {
            throw new RuntimeException('当前期数不能为空。');
        }

        $config = $this->managedPostGeneratorConfig($region);
        $zodiacLabels = array();
        foreach ((array) ($config['zodiac_options'] ?? array()) as $option) {
            $zodiacLabels[(string) $option['value']] = (string) $option['label'];
        }
        $waveLabels = array();
        foreach ((array) ($config['wave_options'] ?? array()) as $option) {
            $waveLabels[(string) $option['value']] = (string) $option['label'];
        }
        $headLabels = array();
        foreach ((array) ($config['head_options'] ?? array()) as $option) {
            $headLabels[(string) $option['value']] = (string) $option['label'];
        }
        $tailLabels = array();
        foreach ((array) ($config['tail_options'] ?? array()) as $option) {
            $tailLabels[(string) $option['value']] = (string) $option['label'];
        }

        $templateLabel = trim((string) ($meta['title_middle_text'] ?? ($post['manage_title_middle_text'] ?? '')));
        $templateLabel = preg_replace('/^[【〖《｛〔『]\s*/u', '', (string) $templateLabel);
        $templateLabel = preg_replace('/\s*[】〗》｝〕』]$/u', '', (string) $templateLabel);
        if ($templateLabel === '') {
            $title = trim((string) ($post['title'] ?? ''));
            foreach ($this->managedPostGeneratorTemplateGroups() as $group) {
                foreach ((array) ($group['items'] ?? array()) as $item) {
                    $label = trim((string) ($item['label'] ?? ''));
                    if ($label !== '' && mb_strpos($title, $label, 0, 'UTF-8') !== false) {
                        $templateLabel = $label;
                        break 2;
                    }
                }
            }
        }
        if ($templateLabel === '') {
            throw new RuntimeException('当前帖子缺少生成模板类型，无法生成当期资料。');
        }

        $forecastContent = $this->managedPostGeneratorForecastContent(
            $region,
            $issueTail,
            trim((string) ($meta['author_nickname'] ?? ($post['manage_author_nickname'] ?? ($post['author_name'] ?? '')))),
            $templateLabel,
            array(
                'record_min' => 1,
                'record_max' => 1,
                'rate_min' => $config['preset_record_rate_min'] ?? '',
                'rate_max' => $config['preset_record_rate_max'] ?? '',
                'zodiac_min' => $config['preset_zodiac_min'] ?? '',
                'zodiac_max' => $config['preset_zodiac_max'] ?? '',
                'selected_zodiacs' => (array) ($config['target_zodiac'] ?? array()),
                'zodiac_labels' => $zodiacLabels,
                'selected_numbers' => (array) ($config['target_number'] ?? array()),
                'selected_waves' => (array) ($config['target_wave'] ?? array()),
                'wave_labels' => $waveLabels,
                'selected_heads' => (array) ($config['target_head'] ?? array()),
                'head_labels' => $headLabels,
                'selected_tails' => (array) ($config['target_tail'] ?? array()),
                'tail_labels' => $tailLabels,
                'previous_result_log' => (string) ($meta['recent_result_log'] ?? ($post['manage_recent_result_log'] ?? '')),
                'prediction_template_content' => (string) ($post['full_content'] ?? ''),
            )
        );

        $content = trim((string) ($forecastContent['content'] ?? ''));
        if ($content === '') {
            throw new RuntimeException('预测分析未生成有效资料内容。');
        }

        return $forecastContent;
    }

    protected function managedPostGeneratorDrawInfo($region, $issueTail)
    {
        $region = $region === 'hongkong' ? 'hongkong' : 'macau';
        $issueTail = str_pad(substr(preg_replace('/\D+/', '', (string) $issueTail), -3), 3, '0', STR_PAD_LEFT);
        $draw = null;
        if ($this->tableExists($this->db(), 'lottery_draws')) {
            $draw = $this->db()->fetch(
                'SELECT issue_no, draw_date, numbers_json, special_number
                 FROM lottery_draws
                 WHERE region = :region
                   AND RIGHT(issue_no, 3) = :issue_tail
                 ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
                 LIMIT 1',
                array(
                    'region' => $region,
                    'issue_tail' => $issueTail,
                )
            );
        }

        $numbers = array();
        $regularNumbers = array();
        $drawDate = is_array($draw) ? trim((string) ($draw['draw_date'] ?? '')) : '';
        $decodedNumbers = is_array($draw) ? json_decode((string) ($draw['numbers_json'] ?? ''), true) : null;
        if (is_array($decodedNumbers)) {
            foreach ($decodedNumbers as $number) {
                $number = (int) $number;
                if ($number >= 1 && $number <= 49 && !in_array($number, $numbers, true)) {
                    $numbers[] = $number;
                    $regularNumbers[] = $number;
                }
            }
        }

        $specialNumber = is_array($draw) ? (int) ($draw['special_number'] ?? 0) : 0;
        if ($specialNumber >= 1 && $specialNumber <= 49 && !in_array($specialNumber, $numbers, true)) {
            $numbers[] = $specialNumber;
        }

        if ($numbers === array()) {
            return array(
                'numbers' => array(),
                'regular_numbers' => array(),
                'special_number' => 0,
                'open_number' => 0,
                'open_number_text' => '',
                'open_zodiac' => '',
                'draw_date' => $drawDate,
                'has_draw' => false,
            );
        } elseif ($specialNumber < 1 || $specialNumber > 49) {
            $specialNumber = (int) $numbers[count($numbers) - 1];
        }

        $openNumber = $specialNumber > 0 ? $specialNumber : (int) $numbers[0];

        return array(
            'numbers' => array_values(array_unique($numbers)),
            'regular_numbers' => array_values(array_unique($regularNumbers)),
            'special_number' => $specialNumber,
            'open_number' => $openNumber,
            'open_number_text' => str_pad((string) $openNumber, 2, '0', STR_PAD_LEFT),
            'open_zodiac' => $this->managedPostGeneratorZodiacByNumber($openNumber, $drawDate),
            'draw_date' => $drawDate,
            'has_draw' => true,
        );
    }

    protected function managedPostGeneratorPredictionInfo($templateLabel, array $drawInfo, $isHit, array $options)
    {
        $templateLabel = trim((string) $templateLabel);
        $typeText = $this->managedNormalizeForecastTypeText($templateLabel);
        $openNumber = max(1, min(49, (int) ($drawInfo['open_number'] ?? 1)));
        $openZodiac = trim((string) ($drawInfo['open_zodiac'] ?? ''));
        $openResult = str_pad((string) $openNumber, 2, '0', STR_PAD_LEFT) . $openZodiac;
        $count = $this->managedPostGeneratorPredictionCount($typeText);
        $allDrawNumbers = array_values(array_unique(array_filter(array_map('intval', (array) ($drawInfo['numbers'] ?? array())))));
        $regularDrawNumbers = array_values(array_unique(array_filter(array_map('intval', (array) ($drawInfo['regular_numbers'] ?? array())))));
        $selectedNumbers = $this->managedPostGeneratorSelectedNumbers((array) ($options['selected_numbers'] ?? array()));
        $selectedZodiacs = $this->managedPostGeneratorSelectedLabels((array) ($options['selected_zodiacs'] ?? array()), (array) ($options['zodiac_labels'] ?? array()));
        $selectedNumbersScopedByZodiac = false;
        $resolveZodiacNumberPool = function (array $zodiacs) use ($drawInfo) {
            $zodiacLookup = array();
            foreach ($zodiacs as $zodiac) {
                $zodiac = trim((string) $zodiac);
                if ($zodiac !== '') {
                    $zodiacLookup[$zodiac] = true;
                }
            }
            if ($zodiacLookup === array()) {
                return array();
            }

            $numbers = array();
            for ($candidateNumber = 1; $candidateNumber <= 49; $candidateNumber++) {
                $candidateZodiac = $this->managedPostGeneratorZodiacByNumber(
                    $candidateNumber,
                    (string) ($drawInfo['draw_date'] ?? '')
                );
                if ($candidateZodiac !== '' && isset($zodiacLookup[$candidateZodiac])) {
                    $numbers[] = $candidateNumber;
                }
            }

            return $numbers;
        };
        if ($selectedZodiacs !== array()) {
            $selectedZodiacNumbers = $resolveZodiacNumberPool($selectedZodiacs);
            if ($selectedZodiacNumbers !== array()) {
                $selectedZodiacNumberLookup = array_fill_keys($selectedZodiacNumbers, true);
                $scopedSelectedNumbers = array();
                foreach ($selectedNumbers as $selectedNumber) {
                    $selectedNumber = (int) $selectedNumber;
                    if (isset($selectedZodiacNumberLookup[$selectedNumber]) && !in_array($selectedNumber, $scopedSelectedNumbers, true)) {
                        $scopedSelectedNumbers[] = $selectedNumber;
                    }
                }
                $selectedNumbers = $scopedSelectedNumbers !== array()
                    ? $scopedSelectedNumbers
                    : $selectedZodiacNumbers;
                $selectedNumbersScopedByZodiac = true;
            }
        }
        $selectedWaves = $this->managedPostGeneratorSelectedLabels((array) ($options['selected_waves'] ?? array()), (array) ($options['wave_labels'] ?? array()));
        $selectedElements = $this->managedPostGeneratorSelectedLabels((array) ($options['selected_elements'] ?? array()), (array) ($options['element_labels'] ?? array()));
        $selectedHeads = $this->managedPostGeneratorSelectedLabels((array) ($options['selected_heads'] ?? array()), (array) ($options['head_labels'] ?? array()));
        $selectedTails = $this->managedPostGeneratorSelectedLabels((array) ($options['selected_tails'] ?? array()), (array) ($options['tail_labels'] ?? array()));
        $typeHasNumber = mb_strpos($typeText, '码', 0, 'UTF-8') !== false;
        $typeHasZodiac = mb_strpos($typeText, '肖', 0, 'UTF-8') !== false;
        $typeHasWave = mb_strpos($typeText, '波', 0, 'UTF-8') !== false;
        $typeHasElement = mb_strpos($typeText, '行', 0, 'UTF-8') !== false;
        $typeHasHead = mb_strpos($typeText, '头', 0, 'UTF-8') !== false;
        $typeHasTail = mb_strpos($typeText, '尾', 0, 'UTF-8') !== false;
        $typeHasOddEven = mb_strpos($typeText, '单', 0, 'UTF-8') !== false
            || mb_strpos($typeText, '双', 0, 'UTF-8') !== false;
        $typeHasBigSmall = mb_strpos($typeText, '大', 0, 'UTF-8') !== false
            || mb_strpos($typeText, '小', 0, 'UTF-8') !== false;
        $typeHasHomeWild = mb_strpos($typeText, '家野', 0, 'UTF-8') !== false;
        $typeIsKill = mb_strpos($typeText, '绝杀', 0, 'UTF-8') !== false;
        $predictionIsHit = $typeIsKill ? empty($isHit) : !empty($isHit);
        $homeZodiacs = array('牛', '马', '羊', '鸡', '狗', '猪');
        $resolveNumberElement = static function ($number) {
            $number = (int) $number;
            $elementGroups = array(
                '金' => array(2, 3, 10, 11, 24, 25, 32, 33, 40, 41),
                '木' => array(6, 7, 14, 15, 22, 23, 36, 37, 44, 45),
                '水' => array(12, 13, 20, 21, 28, 29, 42, 43),
                '火' => array(1, 8, 9, 16, 17, 30, 31, 38, 39, 46, 47),
                '土' => array(4, 5, 18, 19, 26, 27, 34, 35, 48, 49),
            );
            foreach ($elementGroups as $elementName => $elementNumbers) {
                if (in_array($number, $elementNumbers, true)) {
                    return $elementName;
                }
            }

            return '';
        };
        $openElement = $resolveNumberElement($openNumber);
        $resolveForecastCount = static function ($text) {
            $text = trim((string) $text);
            if ($text === '') {
                return 0;
            }
            if (ctype_digit($text)) {
                return (int) $text;
            }
            $numberMap = array(
                '①' => 1, '②' => 2, '③' => 3, '④' => 4, '⑤' => 5,
                '⑥' => 6, '⑦' => 7, '⑧' => 8, '⑨' => 9, '⑩' => 10,
                '零' => 0, '一' => 1, '二' => 2, '两' => 2, '三' => 3, '四' => 4,
                '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9, '十' => 10,
            );
            if (isset($numberMap[$text])) {
                return (int) $numberMap[$text];
            }
            if (mb_strpos($text, '十', 0, 'UTF-8') !== false) {
                $parts = explode('十', $text, 2);
                $left = trim((string) ($parts[0] ?? ''));
                $right = trim((string) ($parts[1] ?? ''));
                $tens = $left === '' ? 1 : (int) ($numberMap[$left] ?? 0);
                $ones = $right === '' ? 0 : (int) ($numberMap[$right] ?? 0);

                return ($tens * 10) + $ones;
            }

            return 0;
        };
        $extractPredictionTokens = static function ($prediction) {
            $prediction = preg_replace('/[{}]/u', ' ', (string) $prediction);
            $parts = preg_split('/[\s,，、]+/u', (string) $prediction, -1, PREG_SPLIT_NO_EMPTY);
            $tokens = array();
            foreach ((array) $parts as $part) {
                $part = trim((string) $part);
                if ($part === '' || $part === '+') {
                    continue;
                }
                $tokens[] = $part;
            }

            return $tokens;
        };
        $combineTypeNumberPrediction = static function (array $typeTokens, $numberPrediction) use ($extractPredictionTokens) {
            $formatTokenGroups = static function (array $tokenGroups) {
                $groups = array();
                foreach ($tokenGroups as $tokenGroup) {
                    $cleanTokens = array();
                    foreach ((array) $tokenGroup as $token) {
                        $token = trim((string) $token);
                        if ($token !== '') {
                            $cleanTokens[] = $token;
                        }
                    }
                    if ($cleanTokens !== array()) {
                        $groups[] = '{ ' . implode(' ', $cleanTokens) . ' }';
                    }
                }

                return implode(' ', $groups);
            };
            $splitNumberRows = static function (array $numberTokens) {
                $numberTokens = array_values($numberTokens);
                $total = count($numberTokens);
                if ($total <= 9) {
                    return array($numberTokens);
                }
                $rowCount = (int) ceil($total / 9);
                $baseRowSize = (int) floor($total / max(1, $rowCount));
                $extraCount = $total % max(1, $rowCount);
                $rows = array();
                $offset = 0;
                for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
                    $rowSize = $baseRowSize + ($rowIndex < $extraCount ? 1 : 0);
                    $rows[] = array_slice($numberTokens, $offset, max(1, $rowSize));
                    $offset += max(1, $rowSize);
                }

                return $rows;
            };
            $cleanTypeTokens = array();
            foreach ($typeTokens as $typeToken) {
                $typeToken = trim((string) $typeToken);
                if ($typeToken !== '' && $typeToken !== '+') {
                    $cleanTypeTokens[] = $typeToken;
                }
            }
            $numberTokens = array();
            foreach ($extractPredictionTokens($numberPrediction) as $numberToken) {
                if (!preg_match('/^\d{1,2}$/', (string) $numberToken)) {
                    continue;
                }
                $number = (int) $numberToken;
                if ($number >= 1 && $number <= 49) {
                    $numberTokens[] = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                }
            }
            if ($cleanTypeTokens === array() || $numberTokens === array()) {
                return (string) $numberPrediction;
            }

            return $formatTokenGroups(array_merge(array($cleanTypeTokens), $splitNumberRows($numberTokens)));
        };
        $formatNumberPredictionRows = static function ($numberPrediction, $groupSize = 0) use ($extractPredictionTokens) {
            $numberTokens = array();
            foreach ($extractPredictionTokens($numberPrediction) as $numberToken) {
                if (!preg_match('/^\d{1,2}$/', (string) $numberToken)) {
                    continue;
                }
                $number = (int) $numberToken;
                if ($number >= 1 && $number <= 49) {
                    $numberTokens[] = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                }
            }
            if ($numberTokens === array()) {
                return (string) $numberPrediction;
            }
            $total = count($numberTokens);
            $groupSize = max(0, (int) $groupSize);
            if ($groupSize > 1 && $total > $groupSize) {
                $groups = array();
                foreach (array_chunk($numberTokens, $groupSize) as $numberGroup) {
                    $groups[] = '{ ' . implode(' ', $numberGroup) . ' }';
                }

                return implode(' ', $groups);
            }
            $rowCount = $total > 10 ? (int) ceil($total / 10) : 1;
            $rowSize = $total > 10 ? (int) ceil($total / max(1, $rowCount)) : $total;
            $groups = array();
            foreach (array_chunk($numberTokens, max(1, $rowSize)) as $numberGroup) {
                $groups[] = '{ ' . implode(' ', $numberGroup) . ' }';
            }

            return implode(' ', $groups);
        };
        $extractNumberPredictionTokens = static function ($numberPrediction) use ($extractPredictionTokens) {
            $numberTokens = array();
            foreach ($extractPredictionTokens($numberPrediction) as $numberToken) {
                if (!preg_match('/^\d{1,2}$/', (string) $numberToken)) {
                    continue;
                }
                $number = (int) $numberToken;
                if ($number >= 1 && $number <= 49) {
                    $numberTokens[] = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                }
            }

            return $numberTokens;
        };
        $formatNumberTokenGroups = static function (array $tokenGroups) {
            $groups = array();
            foreach ($tokenGroups as $tokenGroup) {
                $cleanTokens = array();
                foreach ((array) $tokenGroup as $token) {
                    $token = trim((string) $token);
                    if ($token !== '') {
                        if (preg_match('/^\d{1,2}$/', $token) && (int) $token >= 1 && (int) $token <= 49) {
                            $token = str_pad((string) ((int) $token), 2, '0', STR_PAD_LEFT);
                        }
                        $cleanTokens[] = $token;
                    }
                }
                if ($cleanTokens !== array()) {
                    $groups[] = '{ ' . implode(' ', $cleanTokens) . ' }';
                }
            }

            return implode(' ', $groups);
        };
        $buildCombinedNumberPrediction = function ($combinedType, array $typeTokens, $count, $isHit) use (
            $openNumber,
            $allDrawNumbers,
            $selectedNumbers,
            $selectedNumbersScopedByZodiac,
            $extractNumberPredictionTokens,
            $formatNumberTokenGroups
        ) {
            $combinedType = trim((string) $combinedType);
            $count = max(1, min(240, (int) $count));
            $typeLookup = array();
            foreach ($typeTokens as $typeToken) {
                $typeToken = trim((string) $typeToken);
                if ($typeToken !== '' && $typeToken !== '+') {
                    $typeLookup[$typeToken] = true;
                }
            }
            if ($typeLookup === array() || !in_array($combinedType, array('head', 'tail', 'wave', 'big_small', 'odd_even'), true)) {
                return $this->managedPostGeneratorNumberPrediction(
                    $openNumber,
                    $allDrawNumbers,
                    $selectedNumbers,
                    $count,
                    !empty($isHit),
                    $selectedNumbersScopedByZodiac
                );
            }

            $allowedPool = array();
            for ($candidateNumber = 1; $candidateNumber <= 49; $candidateNumber++) {
                if ($combinedType === 'head') {
                    $candidateType = (string) floor($candidateNumber / 10) . '头';
                } elseif ($combinedType === 'tail') {
                    $candidateType = (string) ($candidateNumber % 10) . '尾';
                } elseif ($combinedType === 'wave') {
                    $candidateType = $this->managedPostGeneratorWaveByNumber($candidateNumber);
                } elseif ($combinedType === 'big_small') {
                    $candidateType = $candidateNumber >= 25 ? '大' : '小';
                } else {
                    $candidateType = $candidateNumber % 2 === 1 ? '单' : '双';
                }
                if (isset($typeLookup[$candidateType])) {
                    $allowedPool[] = $candidateNumber;
                }
            }
            if ($allowedPool === array()) {
                return $this->managedPostGeneratorNumberPrediction(
                    $openNumber,
                    $allDrawNumbers,
                    $selectedNumbers,
                    $count,
                    !empty($isHit),
                    $selectedNumbersScopedByZodiac
                );
            }

            $allowedLookup = array_fill_keys($allowedPool, true);
            $preferredPool = array();
            $preferredSource = $selectedNumbersScopedByZodiac ? $selectedNumbers : array_merge($selectedNumbers, $allowedPool);
            foreach ($preferredSource as $number) {
                $number = (int) $number;
                if (
                    $number >= 1
                    && $number <= 49
                    && isset($allowedLookup[$number])
                    && !in_array($number, $preferredPool, true)
                ) {
                    $preferredPool[] = $number;
                }
            }
            if ($preferredPool === array() && $selectedNumbersScopedByZodiac) {
                $preferredPool = $selectedNumbers;
            }

            $prediction = $this->managedPostGeneratorNumberPrediction(
                $openNumber,
                $allDrawNumbers,
                $preferredPool,
                $count,
                !empty($isHit),
                $selectedNumbersScopedByZodiac
            );
            $drawLookup = array_fill_keys(array_values(array_map('intval', $allDrawNumbers)), true);
            $numberTokens = array();
            if (!empty($isHit)) {
                $hitNumber = (int) $openNumber;
                if (isset($allowedLookup[$hitNumber])) {
                    $numberTokens[] = $hitNumber;
                }
            }
            foreach ($extractNumberPredictionTokens($prediction) as $numberToken) {
                if (count($numberTokens) >= $count) {
                    break;
                }
                $number = (int) $numberToken;
                if (
                    $number < 1
                    || $number > 49
                    || !isset($allowedLookup[$number])
                    || in_array($number, $numberTokens, true)
                ) {
                    continue;
                }
                if (empty($isHit) && isset($drawLookup[$number])) {
                    continue;
                }
                $numberTokens[] = $number;
            }
            foreach ($preferredPool as $number) {
                if (count($numberTokens) >= $count) {
                    break;
                }
                $number = (int) $number;
                if ($number < 1 || $number > 49 || in_array($number, $numberTokens, true)) {
                    continue;
                }
                if (empty($isHit) && isset($drawLookup[$number])) {
                    continue;
                }
                $numberTokens[] = $number;
            }
            foreach ($allowedPool as $number) {
                if (count($numberTokens) >= $count) {
                    break;
                }
                $number = (int) $number;
                if ($number < 1 || $number > 49 || in_array($number, $numberTokens, true)) {
                    continue;
                }
                if (empty($isHit) && isset($drawLookup[$number])) {
                    continue;
                }
                $numberTokens[] = $number;
            }
            for ($number = 1; count($numberTokens) < $count && $number <= 49; $number++) {
                if (in_array($number, $numberTokens, true)) {
                    continue;
                }
                if (empty($isHit) && isset($drawLookup[$number])) {
                    continue;
                }
                $numberTokens[] = $number;
            }
            shuffle($numberTokens);

            return $formatNumberTokenGroups(array(array_slice($numberTokens, 0, $count)));
        };
        $predictionBracketPair = isset($options['prediction_bracket_pair']) && is_array($options['prediction_bracket_pair'])
            ? array_values((array) $options['prediction_bracket_pair'])
            : array();
        $predictionTemplateLayout = isset($options['prediction_template_layout']) && is_array($options['prediction_template_layout'])
            ? (array) $options['prediction_template_layout']
            : array();
        $postService = $this->app->posts();
        $randomizePredictionBrackets = static function ($prediction) use ($postService, $predictionTemplateLayout, $predictionBracketPair) {
            return $postService->applyForecastPredictionTemplateLayout(
                $prediction,
                $predictionTemplateLayout,
                $predictionBracketPair
            );
        };
        $combinedPredictionType = '';
        $combinedTypeCount = 0;
        $combinedNumberCount = 0;
        if (preg_match('/([零一二两三四五六七八九十\d]+)肖([零一二两三四五六七八九十\d]+)码/u', $typeText, $matches)) {
            $combinedPredictionType = 'zodiac';
            $combinedTypeCount = max(1, min(12, $resolveForecastCount((string) $matches[1])));
            $combinedNumberCount = max(1, min(240, $resolveForecastCount((string) $matches[2])));
        } elseif (preg_match('/([零一二两三四五六七八九十①②③④⑤⑥⑦⑧⑨⑩\d]+)头([零一二两三四五六七八九十①②③④⑤⑥⑦⑧⑨⑩\d]+)码/u', $typeText, $matches)) {
            $combinedPredictionType = 'head';
            $combinedTypeCount = max(1, min(5, $resolveForecastCount((string) $matches[1])));
            $combinedNumberCount = max(1, min(240, $resolveForecastCount((string) $matches[2])));
        } elseif (preg_match('/([零一二两三四五六七八九十①②③④⑤⑥⑦⑧⑨⑩\d]+)尾([零一二两三四五六七八九十①②③④⑤⑥⑦⑧⑨⑩\d]+)码/u', $typeText, $matches)) {
            $combinedPredictionType = 'tail';
            $combinedTypeCount = max(1, min(10, $resolveForecastCount((string) $matches[1])));
            $combinedNumberCount = max(1, min(240, $resolveForecastCount((string) $matches[2])));
        } elseif (preg_match('/([零一二两三四五六七八九十①②③④⑤⑥⑦⑧⑨⑩\d]+)波([零一二两三四五六七八九十①②③④⑤⑥⑦⑧⑨⑩\d]+)码/u', $typeText, $matches)) {
            $combinedPredictionType = 'wave';
            $combinedTypeCount = max(1, min(3, $resolveForecastCount((string) $matches[1])));
            $combinedNumberCount = max(1, min(240, $resolveForecastCount((string) $matches[2])));
        } elseif (preg_match('/大小([零一二两三四五六七八九十\d]+)码/u', $typeText, $matches)) {
            $combinedPredictionType = 'big_small';
            $combinedTypeCount = 1;
            $combinedNumberCount = max(1, min(240, $resolveForecastCount((string) $matches[1])));
        } elseif (preg_match('/单双([零一二两三四五六七八九十\d]+)码/u', $typeText, $matches)) {
            $combinedPredictionType = 'odd_even';
            $combinedTypeCount = 1;
            $combinedNumberCount = max(1, min(240, $resolveForecastCount((string) $matches[1])));
        }
        $mixedSpecialCodeCount = 0;
        if (preg_match('/([零一二两三四五六七八九十\d]+)码三中三/u', $typeText, $matches)) {
            $mixedSpecialCodeCount = max(1, min(49, $resolveForecastCount((string) $matches[1])));
        }
        $normalCodeHotNumbers = $this->managedPostGeneratorSelectedNumbers((array) ($options['normal_code_hot_numbers'] ?? array()));
        $normalCodeNumberPool = array();
        foreach (array_merge($normalCodeHotNumbers, $selectedNumbers, range(1, 49)) as $normalCodePoolNumber) {
            $normalCodePoolNumber = (int) $normalCodePoolNumber;
            if ($normalCodePoolNumber >= 1 && $normalCodePoolNumber <= 49 && !in_array($normalCodePoolNumber, $normalCodeNumberPool, true)) {
                $normalCodeNumberPool[] = $normalCodePoolNumber;
            }
            if (count($normalCodeNumberPool) >= 10) {
                break;
            }
        }
        $normalCodeRegularLookup = array_fill_keys(array_values(array_map('intval', $regularDrawNumbers)), true);
        $normalCodeGroupKey = static function (array $numbers) {
            $numbers = array_values(array_map('intval', $numbers));
            sort($numbers, SORT_NUMERIC);

            return implode('-', $numbers);
        };
        $normalCodeGroupHitCountFor = static function (array $numbers) use ($normalCodeRegularLookup) {
            $hitCount = 0;
            foreach ($numbers as $number) {
                if (isset($normalCodeRegularLookup[(int) $number])) {
                    $hitCount++;
                }
            }

            return $hitCount;
        };
        $buildNormalCodeCombinations = static function (array $pool, $groupSize) {
            $groupSize = max(1, min(10, (int) $groupSize));
            $pool = array_values(array_unique(array_filter(array_map('intval', $pool), function ($number) {
                return $number >= 1 && $number <= 49;
            })));
            if (count($pool) < $groupSize) {
                return array();
            }

            $candidates = array();
            $walk = function ($start, array $group) use (&$walk, &$candidates, $pool, $groupSize) {
                if (count($group) === $groupSize) {
                    $candidates[] = array_values($group);
                    return;
                }
                for ($index = $start; $index < count($pool); $index++) {
                    $nextGroup = $group;
                    $nextGroup[] = (int) $pool[$index];
                    $walk($index + 1, $nextGroup);
                }
            };
            $walk(0, array());

            return $candidates;
        };
        $buildNormalCodeHotGroups = static function ($groupSize, $groupCount, $hitCount, $isHit, array $excludedNumbers = array()) use (
            $normalCodeNumberPool,
            $normalCodeRegularLookup,
            $buildNormalCodeCombinations,
            $normalCodeGroupHitCountFor,
            $normalCodeGroupKey
        ) {
            $groupSize = max(1, min(10, (int) $groupSize));
            $groupCount = max(1, min(100, (int) $groupCount));
            $hitCount = max(1, min($groupSize, (int) $hitCount));
            $excludedLookup = array_fill_keys(array_values(array_map('intval', $excludedNumbers)), true);
            $pool = array();
            foreach ($normalCodeNumberPool as $number) {
                $number = (int) $number;
                if ($number >= 1 && $number <= 49 && !isset($excludedLookup[$number])) {
                    $pool[] = $number;
                }
            }
            if (count($pool) < $groupSize) {
                $pool = $normalCodeNumberPool;
            }
            if ($normalCodeRegularLookup !== array() && $excludedNumbers === array()) {
                $regularPool = array();
                foreach (array_keys($normalCodeRegularLookup) as $number) {
                    $number = (int) $number;
                    if ($number >= 1 && $number <= 49) {
                        $regularPool[] = $number;
                    }
                }
                shuffle($regularPool);
                $currentRegularCount = 0;
                foreach ($pool as $number) {
                    if (isset($normalCodeRegularLookup[(int) $number])) {
                        $currentRegularCount++;
                    }
                }
                $targetRegularCount = !empty($isHit)
                    ? min(count($regularPool), $hitCount)
                    : min(count($regularPool), 5);
                foreach ($regularPool as $number) {
                    if ($currentRegularCount >= $targetRegularCount) {
                        break;
                    }
                    if (in_array($number, $pool, true)) {
                        continue;
                    }
                    if (count($pool) >= 10) {
                        for ($removeIndex = count($pool) - 1; $removeIndex >= 0; $removeIndex--) {
                            if (!isset($normalCodeRegularLookup[(int) $pool[$removeIndex]])) {
                                array_splice($pool, $removeIndex, 1);
                                break;
                            }
                        }
                    }
                    if (count($pool) >= 10) {
                        break;
                    }
                    $pool[] = $number;
                    $currentRegularCount++;
                }
            }

            $candidates = $buildNormalCodeCombinations($pool, $groupSize);
            if ($candidates === array()) {
                return array();
            }
            shuffle($candidates);

            $hitCandidates = array();
            $missCandidates = array();
            $partialHitCandidates = array();
            $zeroHitCandidates = array();
            foreach ($candidates as $candidate) {
                $candidateHitCount = $normalCodeGroupHitCountFor($candidate);
                if ($candidateHitCount >= $hitCount) {
                    $hitCandidates[] = $candidate;
                } else {
                    $missCandidates[] = $candidate;
                    if ($candidateHitCount > 0) {
                        $partialHitCandidates[] = $candidate;
                    } else {
                        $zeroHitCandidates[] = $candidate;
                    }
                }
            }

            $groups = array();
            $usedKeys = array();
            if (!empty($isHit) && $hitCandidates !== array()) {
                $hitGroup = $hitCandidates[0];
                $usedKeys[$normalCodeGroupKey($hitGroup)] = true;
                shuffle($hitGroup);
                $groups[] = array_values($hitGroup);
            }

            $fillCandidates = !empty($isHit)
                ? array_merge($missCandidates, $candidates)
                : ($partialHitCandidates !== array()
                    ? array_merge($partialHitCandidates, $zeroHitCandidates)
                    : $missCandidates);
            foreach ($fillCandidates as $candidate) {
                if (count($groups) >= $groupCount) {
                    break;
                }
                $key = $normalCodeGroupKey($candidate);
                if (isset($usedKeys[$key])) {
                    continue;
                }
                $usedKeys[$key] = true;
                shuffle($candidate);
                $groups[] = array_values($candidate);
            }
            if (!empty($isHit) && count($groups) > 1) {
                shuffle($groups);
            }

            return $groups;
        };
        $buildNormalCodeHotSet = static function ($totalCount, $hitCount, $isHit, array $excludedNumbers = array()) use (
            $normalCodeNumberPool,
            $normalCodeRegularLookup
        ) {
            $totalCount = max(1, min(10, (int) $totalCount));
            $hitCount = max(1, min($totalCount, 8, (int) $hitCount));
            $excludedLookup = array_fill_keys(array_values(array_map('intval', $excludedNumbers)), true);
            $pool = array();
            foreach ($normalCodeNumberPool as $number) {
                $number = (int) $number;
                if ($number >= 1 && $number <= 49 && !isset($excludedLookup[$number])) {
                    $pool[] = $number;
                }
            }
            if (count($pool) < $totalCount) {
                $pool = $normalCodeNumberPool;
            }
            if (!empty($isHit) && $normalCodeRegularLookup !== array() && $excludedNumbers === array()) {
                $regularPool = array();
                foreach (array_keys($normalCodeRegularLookup) as $number) {
                    $number = (int) $number;
                    if ($number >= 1 && $number <= 49) {
                        $regularPool[] = $number;
                    }
                }
                shuffle($regularPool);
                $currentRegularCount = 0;
                foreach ($pool as $number) {
                    if (isset($normalCodeRegularLookup[(int) $number])) {
                        $currentRegularCount++;
                    }
                }
                $targetRegularCount = min(count($regularPool), $hitCount, $totalCount);
                foreach ($regularPool as $number) {
                    if ($currentRegularCount >= $targetRegularCount) {
                        break;
                    }
                    if (in_array($number, $pool, true)) {
                        continue;
                    }
                    if (count($pool) >= 10) {
                        for ($removeIndex = count($pool) - 1; $removeIndex >= 0; $removeIndex--) {
                            if (!isset($normalCodeRegularLookup[(int) $pool[$removeIndex]])) {
                                array_splice($pool, $removeIndex, 1);
                                break;
                            }
                        }
                    }
                    if (count($pool) >= 10) {
                        break;
                    }
                    $pool[] = $number;
                    $currentRegularCount++;
                }
            }

            $hitPool = array();
            $missPool = array();
            foreach ($pool as $number) {
                if (isset($normalCodeRegularLookup[(int) $number])) {
                    $hitPool[] = (int) $number;
                } else {
                    $missPool[] = (int) $number;
                }
            }
            shuffle($hitPool);
            shuffle($missPool);

            $numbers = array();
            if (!empty($isHit) && $hitPool !== array()) {
                foreach ($hitPool as $number) {
                    if (!in_array($number, $numbers, true)) {
                        $numbers[] = $number;
                    }
                    if (count($numbers) >= min($hitCount, $totalCount)) {
                        break;
                    }
                }
            }

            $fillPool = !empty($isHit) ? $missPool : array_merge($missPool, $pool);
            foreach ($fillPool as $number) {
                if (count($numbers) >= $totalCount) {
                    break;
                }
                $number = (int) $number;
                if ($number < 1 || $number > 49 || in_array($number, $numbers, true)) {
                    continue;
                }
                if (empty($isHit) && isset($normalCodeRegularLookup[$number])) {
                    $currentHitCount = 0;
                    foreach ($numbers as $selectedNumber) {
                        if (isset($normalCodeRegularLookup[(int) $selectedNumber])) {
                            $currentHitCount++;
                        }
                    }
                    if ($currentHitCount + 1 >= $hitCount && count($numbers) + 1 < $totalCount) {
                        continue;
                    }
                }
                $numbers[] = $number;
                if (count($numbers) >= $totalCount) {
                    break;
                }
            }
            if (!empty($isHit) && count($numbers) < $totalCount) {
                $fallbackMissPool = range(1, 49);
                shuffle($fallbackMissPool);
                foreach ($fallbackMissPool as $number) {
                    $number = (int) $number;
                    if (
                        $number < 1
                        || $number > 49
                        || isset($normalCodeRegularLookup[$number])
                        || isset($excludedLookup[$number])
                        || in_array($number, $numbers, true)
                    ) {
                        continue;
                    }
                    $numbers[] = $number;
                    if (count($numbers) >= $totalCount) {
                        break;
                    }
                }
            }
            if (!empty($isHit) && count($numbers) < $totalCount) {
                foreach (array_merge($hitPool, $pool) as $number) {
                    $number = (int) $number;
                    if ($number < 1 || $number > 49 || in_array($number, $numbers, true)) {
                        continue;
                    }
                    $numbers[] = $number;
                    if (count($numbers) >= $totalCount) {
                        break;
                    }
                }
            }
            shuffle($numbers);

            return array_values($numbers);
        };
        $buildMixedSpecialNormalPrediction = function ($specialIsHit, $normalHitTarget) use (
            $openNumber,
            $allDrawNumbers,
            $selectedNumbers,
            $selectedNumbersScopedByZodiac,
            $mixedSpecialCodeCount,
            $extractNumberPredictionTokens,
            $formatNumberTokenGroups,
            $normalCodeNumberPool,
            $normalCodeRegularLookup,
            $buildNormalCodeHotGroups,
            $buildNormalCodeHotSet
        ) {
            $specialPrediction = $this->managedPostGeneratorNumberPrediction(
                $openNumber,
                $allDrawNumbers,
                $selectedNumbers,
                $mixedSpecialCodeCount,
                !empty($specialIsHit),
                $selectedNumbersScopedByZodiac
            );
            $specialTokens = array_slice($extractNumberPredictionTokens($specialPrediction), 0, $mixedSpecialCodeCount);
            $normalHitTarget = is_bool($normalHitTarget) ? (!empty($normalHitTarget) ? 1 : 0) : (int) $normalHitTarget;
            $normalHitTarget = max(0, min(3, $normalHitTarget));
            if ($normalHitTarget > 0) {
                $normalGroups = $buildNormalCodeHotGroups(3, 1, $normalHitTarget, true, array_map('intval', $specialTokens));
                $normalTokens = isset($normalGroups[0]) ? (array) $normalGroups[0] : array();
                if ($normalTokens === array()) {
                    $normalTokens = $buildNormalCodeHotSet(3, $normalHitTarget, true, array_map('intval', $specialTokens));
                }

                return $formatNumberTokenGroups(array($specialTokens, $normalTokens));
            }
            $excludedNormalLookup = array_fill_keys(array_values(array_map('intval', $specialTokens)), true);
            $normalTokens = array();
            foreach ($normalCodeNumberPool as $number) {
                $number = (int) $number;
                if (
                    $number >= 1
                    && $number <= 49
                    && !isset($normalCodeRegularLookup[$number])
                    && !isset($excludedNormalLookup[$number])
                    && !in_array($number, $normalTokens, true)
                ) {
                    $normalTokens[] = $number;
                }
                if (count($normalTokens) >= 3) {
                    break;
                }
            }
            if (count($normalTokens) < 3) {
                $fallbackMissPool = range(1, 49);
                shuffle($fallbackMissPool);
                foreach ($fallbackMissPool as $number) {
                    $number = (int) $number;
                    if (
                        $number >= 1
                        && $number <= 49
                        && !isset($normalCodeRegularLookup[$number])
                        && !isset($excludedNormalLookup[$number])
                        && !in_array($number, $normalTokens, true)
                    ) {
                        $normalTokens[] = $number;
                    }
                    if (count($normalTokens) >= 3) {
                        break;
                    }
                }
            }
            shuffle($normalTokens);

            return $formatNumberTokenGroups(array($specialTokens, $normalTokens));
        };
        $numberGroupSize = 0;
        if (preg_match('/([零一二两三四五六七八九十\d]+)组([二三23②③])中\2/u', $typeText, $matches)) {
            $numberGroupSize = max(1, min(3, $resolveForecastCount((string) $matches[2])));
        } elseif ($mixedSpecialCodeCount <= 0 && preg_match('/([零一二两三四五六七八九十\d]+)码三中三/u', $typeText)) {
            $numberGroupSize = 3;
        }
        $normalCodeGroupHitCount = 0;
        $normalCodeComboHitCount = 0;
        $normalCodeComboNumberCount = 0;
        $typeIsNormalCodeNumber = false;
        $flatNumberHitCount = 0;
        if (preg_match('/平特([零一二两三四五六七八九十\d]+)连/u', $typeText, $matches)) {
            $flatNumberHitCount = max(1, min(7, $resolveForecastCount((string) $matches[1])));
        }
        if ($mixedSpecialCodeCount <= 0) {
            if (preg_match('/([零一二两三四五六七八九十\d]+)组([二三23②③])中\2/u', $typeText, $matches)) {
                $normalCodeGroupHitCount = max(1, min(3, $resolveForecastCount((string) $matches[2])));
                $typeIsNormalCodeNumber = true;
            } elseif (preg_match('/([零一二两三四五六七八九十\d]+)码复(?:式|试)([二三23②③])中\2/u', $typeText, $matches)) {
                $normalCodeComboHitCount = max(1, min(3, $resolveForecastCount((string) $matches[2])));
                $normalCodeComboNumberCount = max(1, min(8, $resolveForecastCount((string) $matches[1])));
                $typeIsNormalCodeNumber = true;
            } elseif (mb_strpos($typeText, '平码', 0, 'UTF-8') !== false && $typeHasNumber) {
                $normalCodeComboHitCount = 1;
                $typeIsNormalCodeNumber = true;
            }
        }
        $resolveNormalCodeHitCount = static function ($isHit) use (
            $normalCodeGroupHitCount,
            $normalCodeComboHitCount,
            $normalCodeComboNumberCount,
            $normalCodeRegularLookup
        ) {
            if (
                !empty($isHit)
                && $normalCodeGroupHitCount <= 0
                && $normalCodeComboHitCount > 0
                && $normalCodeComboNumberCount > 0
            ) {
                $availableHitCount = $normalCodeRegularLookup !== array()
                    ? min($normalCodeComboNumberCount, count($normalCodeRegularLookup))
                    : $normalCodeComboNumberCount;

                return mt_rand(1, max(1, min($normalCodeComboNumberCount, $availableHitCount)));
            }

            return $normalCodeGroupHitCount > 0 ? $normalCodeGroupHitCount : $normalCodeComboHitCount;
        };
        $buildNormalCodePrediction = function ($totalCount, $hitCount, $isHit, $groupSize, $comboGroupSize = 0) use (
            $formatNumberTokenGroups,
            $buildNormalCodeHotGroups,
            $buildNormalCodeHotSet,
            $normalCodeNumberPool,
            $normalCodeRegularLookup
        ) {
            $totalCount = max(1, min(240, (int) $totalCount));
            $hitCount = max(1, min($totalCount, 8, (int) $hitCount));
            $groupSize = max(0, (int) $groupSize);
            $comboGroupSize = max(0, min($totalCount, (int) $comboGroupSize));
            if ($groupSize > 1) {
                $groupCount = (int) ceil($totalCount / max(1, $groupSize));
                $groups = $buildNormalCodeHotGroups($groupSize, $groupCount, $hitCount, !empty($isHit));
                return $formatNumberTokenGroups($groups);
            }

            $numberTokens = array();
            if (empty($isHit) && $comboGroupSize > 1) {
                foreach ($normalCodeNumberPool as $numberToken) {
                    $number = (int) $numberToken;
                    if ($number >= 1 && $number <= 49 && !isset($normalCodeRegularLookup[$number])) {
                        $numberTokens[] = $number;
                    }
                }
                if (count($numberTokens) < $totalCount) {
                    $fallbackMissPool = range(1, 49);
                    shuffle($fallbackMissPool);
                    foreach ($fallbackMissPool as $number) {
                        $number = (int) $number;
                        if (
                            $number >= 1
                            && $number <= 49
                            && !isset($normalCodeRegularLookup[$number])
                            && !in_array($number, $numberTokens, true)
                        ) {
                            $numberTokens[] = $number;
                        }
                        if (count($numberTokens) >= $totalCount) {
                            break;
                        }
                    }
                }
                shuffle($numberTokens);
                $numberTokens = array_slice($numberTokens, 0, $totalCount);
            } else {
                $numberTokens = $buildNormalCodeHotSet($totalCount, $hitCount, !empty($isHit));
            }
            if (!empty($isHit) && $comboGroupSize > 1) {
                shuffle($numberTokens);
            }
            return $formatNumberTokenGroups(array($numberTokens));
        };
        $buildFlatNumberPrediction = function ($totalCount, $hitCount, $isHit) use (
            $allDrawNumbers,
            $selectedNumbers,
            $selectedNumbersScopedByZodiac,
            $extractNumberPredictionTokens,
            $formatNumberTokenGroups
        ) {
            $totalCount = max(1, min(240, (int) $totalCount));
            $hitCount = max(1, min($totalCount, (int) $hitCount));
            if (empty($isHit) || $allDrawNumbers === array()) {
                $prediction = $this->managedPostGeneratorNumberPrediction(
                    0,
                    $allDrawNumbers,
                    $selectedNumbers,
                    $totalCount,
                    false,
                    $selectedNumbersScopedByZodiac
                );

                return $formatNumberTokenGroups(array($extractNumberPredictionTokens($prediction)));
            }

            $hitTokens = array();
            $hitPool = array_values(array_unique(array_map('intval', $allDrawNumbers)));
            if ($selectedNumbersScopedByZodiac) {
                $selectedNumberLookup = array_fill_keys(array_values(array_map('intval', $selectedNumbers)), true);
                $hitPool = array_values(array_filter($hitPool, function ($number) use ($selectedNumberLookup) {
                    return isset($selectedNumberLookup[(int) $number]);
                }));
            }
            shuffle($hitPool);
            foreach ($hitPool as $hitNumber) {
                $hitNumber = (int) $hitNumber;
                if ($hitNumber < 1 || $hitNumber > 49) {
                    continue;
                }
                $hitTokens[] = str_pad((string) $hitNumber, 2, '0', STR_PAD_LEFT);
                if (count($hitTokens) >= $hitCount) {
                    break;
                }
            }

            $remainingCount = max(0, $totalCount - count($hitTokens));
            $missTokens = array();
            if ($remainingCount > 0) {
                $missPrediction = $this->managedPostGeneratorNumberPrediction(
                    0,
                    $allDrawNumbers,
                    $selectedNumbers,
                    $remainingCount,
                    false,
                    $selectedNumbersScopedByZodiac
                );
                $missTokens = array_slice($extractNumberPredictionTokens($missPrediction), 0, $remainingCount);
            }

            $numberTokens = array_merge($hitTokens, $missTokens);
            shuffle($numberTokens);

            return $formatNumberTokenGroups(array($numberTokens));
        };

        if (empty($drawInfo['has_draw'])) {
            if ($mixedSpecialCodeCount > 0) {
                $prediction = $buildMixedSpecialNormalPrediction(false, false);
            } elseif ($combinedPredictionType !== '') {
                $numberPrediction = $this->managedPostGeneratorNumberPrediction(0, array(), $selectedNumbers, $combinedNumberCount, false, $selectedNumbersScopedByZodiac);
                if ($combinedPredictionType === 'zodiac') {
                    $typePrediction = $this->managedPostGeneratorZodiacPrediction('', $selectedZodiacs, $combinedTypeCount, false);
                    $typeTokens = $extractPredictionTokens($typePrediction);
                    $typeNumberPool = $resolveZodiacNumberPool($typeTokens);
                    if ($typeNumberPool !== array()) {
                        $numberPrediction = $this->managedPostGeneratorNumberPrediction(0, array(), $typeNumberPool, $combinedNumberCount, false, true);
                    }
                    $prediction = $combineTypeNumberPrediction($typeTokens, $numberPrediction);
                } elseif ($combinedPredictionType === 'head') {
                    $typePrediction = $this->managedPostGeneratorChoicePrediction('', $selectedHeads, array('0头', '1头', '2头', '3头', '4头'), false, $combinedTypeCount, true);
                    $typeTokens = $extractPredictionTokens($typePrediction);
                    $numberPrediction = $buildCombinedNumberPrediction('head', $typeTokens, $combinedNumberCount, false);
                    $prediction = $combineTypeNumberPrediction($typeTokens, $numberPrediction);
                } elseif ($combinedPredictionType === 'tail') {
                    $typePrediction = $this->managedPostGeneratorChoicePrediction('', $selectedTails, array('0尾', '1尾', '2尾', '3尾', '4尾', '5尾', '6尾', '7尾', '8尾', '9尾'), false, $combinedTypeCount, true);
                    $typeTokens = $extractPredictionTokens($typePrediction);
                    $numberPrediction = $buildCombinedNumberPrediction('tail', $typeTokens, $combinedNumberCount, false);
                    $prediction = $combineTypeNumberPrediction($typeTokens, $numberPrediction);
                } elseif ($combinedPredictionType === 'wave') {
                    $typePrediction = $this->managedPostGeneratorChoicePrediction('', $selectedWaves, array('红', '蓝', '绿'), false, $combinedTypeCount, true);
                    $typeTokens = $extractPredictionTokens($typePrediction);
                    $numberPrediction = $buildCombinedNumberPrediction('wave', $typeTokens, $combinedNumberCount, false);
                    $prediction = $combineTypeNumberPrediction($typeTokens, $numberPrediction);
                } elseif ($combinedPredictionType === 'big_small') {
                    $typePrediction = $this->managedPostGeneratorChoicePrediction('', array(), array('大', '小'), false, $combinedTypeCount, true);
                    $typeTokens = $extractPredictionTokens($typePrediction);
                    $numberPrediction = $buildCombinedNumberPrediction('big_small', $typeTokens, $combinedNumberCount, false);
                    $prediction = $combineTypeNumberPrediction($typeTokens, $numberPrediction);
                } else {
                    $typePrediction = $this->managedPostGeneratorChoicePrediction('', array(), array('单', '双'), false, $combinedTypeCount, true);
                    $typeTokens = $extractPredictionTokens($typePrediction);
                    $numberPrediction = $buildCombinedNumberPrediction('odd_even', $typeTokens, $combinedNumberCount, false);
                    $prediction = $combineTypeNumberPrediction($typeTokens, $numberPrediction);
                }
            } elseif ($typeHasBigSmall && !$typeHasNumber) {
                $prediction = $this->managedPostGeneratorChoicePrediction('', array(), array('大', '小'), false, $count);
            } elseif ($typeHasOddEven && !$typeHasNumber) {
                $prediction = $this->managedPostGeneratorChoicePrediction('', array(), array('单', '双'), false, $count);
            } elseif ($typeHasHomeWild && !$typeHasNumber) {
                $prediction = $this->managedPostGeneratorChoicePrediction('', array(), array('家', '野'), false, $count);
            } elseif ($typeHasWave && !$typeHasNumber) {
                $prediction = $this->managedPostGeneratorChoicePrediction('', $selectedWaves, array('红', '蓝', '绿'), false, $count, true);
            } elseif ($typeHasElement && !$typeHasNumber) {
                $prediction = $this->managedPostGeneratorChoicePrediction('', $selectedElements, array('金', '木', '水', '火', '土'), false, $count, true);
            } elseif ($typeHasHead && !$typeHasNumber) {
                $prediction = $this->managedPostGeneratorChoicePrediction('', $selectedHeads, array('0头', '1头', '2头', '3头', '4头'), false, $count, true);
            } elseif ($typeHasTail && !$typeHasNumber) {
                $prediction = $this->managedPostGeneratorChoicePrediction('', $selectedTails, array('0尾', '1尾', '2尾', '3尾', '4尾', '5尾', '6尾', '7尾', '8尾', '9尾'), false, $count, true);
            } elseif ($typeHasZodiac && !$typeHasNumber) {
                $prediction = $this->managedPostGeneratorZodiacPrediction('', $selectedZodiacs, $count, false);
            } elseif ($typeIsNormalCodeNumber) {
                $normalCodeHitCount = $resolveNormalCodeHitCount(false);
                $prediction = $buildNormalCodePrediction($count, $normalCodeHitCount, false, $numberGroupSize, $normalCodeComboHitCount);
            } else {
                $prediction = $this->managedPostGeneratorNumberPrediction(0, array(), $selectedNumbers, $count, false, $selectedNumbersScopedByZodiac);
            }
            if ($combinedPredictionType === '' && $numberGroupSize > 0) {
                $prediction = $formatNumberPredictionRows($prediction, $numberGroupSize);
            }

            return array(
                'prediction' => $randomizePredictionBrackets($prediction),
                'open_result' => '待开奖',
            );
        }

        if ($mixedSpecialCodeCount > 0) {
            $mixedNormalHitTarget = array_key_exists('mixed_normal_hit_count', $options)
                ? (int) $options['mixed_normal_hit_count']
                : !empty($isHit);
            $prediction = $buildMixedSpecialNormalPrediction(!empty($isHit), $mixedNormalHitTarget);
        } elseif ($combinedPredictionType !== '') {
            $combinedNumberIsHit = !empty($isHit);
            if (array_key_exists('combined_number_hit_for_history', $options)) {
                $combinedNumberIsHit = $combinedPredictionType === 'wave'
                    ? !empty($isHit)
                    : (!empty($isHit) && !empty($options['combined_number_hit_for_history']));
            } elseif (!empty($options['reduce_combined_number_hit_rate'])) {
                $combinedNumberRate = $combinedPredictionType === 'wave' ? 100 : 50;
                $combinedNumberIsHit = $combinedNumberIsHit && mt_rand(1, 100) <= $combinedNumberRate;
            }
            $numberPrediction = $this->managedPostGeneratorNumberPrediction($openNumber, $allDrawNumbers, $selectedNumbers, $combinedNumberCount, $combinedNumberIsHit, $selectedNumbersScopedByZodiac);
            if ($combinedPredictionType === 'zodiac') {
                $typePrediction = $this->managedPostGeneratorZodiacPrediction($openZodiac, $selectedZodiacs, $combinedTypeCount, !empty($isHit));
                $typeTokens = $extractPredictionTokens($typePrediction);
                $typeNumberPool = $resolveZodiacNumberPool($typeTokens);
                if ($typeNumberPool !== array()) {
                    $numberPrediction = $this->managedPostGeneratorNumberPrediction($openNumber, $allDrawNumbers, $typeNumberPool, $combinedNumberCount, $combinedNumberIsHit, true);
                }
                $prediction = $combineTypeNumberPrediction($typeTokens, $numberPrediction);
            } elseif ($combinedPredictionType === 'head') {
                $hitValue = (string) floor($openNumber / 10) . '头';
                $typePrediction = $this->managedPostGeneratorChoicePrediction($hitValue, $selectedHeads, array('0头', '1头', '2头', '3头', '4头'), !empty($isHit), $combinedTypeCount, true);
                $typeTokens = $extractPredictionTokens($typePrediction);
                $numberPrediction = $buildCombinedNumberPrediction('head', $typeTokens, $combinedNumberCount, $combinedNumberIsHit);
                $prediction = $combineTypeNumberPrediction($typeTokens, $numberPrediction);
            } elseif ($combinedPredictionType === 'tail') {
                $hitValue = (string) ($openNumber % 10) . '尾';
                $typePrediction = $this->managedPostGeneratorChoicePrediction($hitValue, $selectedTails, array('0尾', '1尾', '2尾', '3尾', '4尾', '5尾', '6尾', '7尾', '8尾', '9尾'), !empty($isHit), $combinedTypeCount, true);
                $typeTokens = $extractPredictionTokens($typePrediction);
                $numberPrediction = $buildCombinedNumberPrediction('tail', $typeTokens, $combinedNumberCount, $combinedNumberIsHit);
                $prediction = $combineTypeNumberPrediction($typeTokens, $numberPrediction);
            } elseif ($combinedPredictionType === 'wave') {
                $hitValue = $this->managedPostGeneratorWaveByNumber($openNumber);
                $typePrediction = $this->managedPostGeneratorChoicePrediction($hitValue, $selectedWaves, array('红', '蓝', '绿'), !empty($isHit), $combinedTypeCount, true);
                $typeTokens = $extractPredictionTokens($typePrediction);
                $numberPrediction = $buildCombinedNumberPrediction('wave', $typeTokens, $combinedNumberCount, $combinedNumberIsHit);
                $prediction = $combineTypeNumberPrediction($typeTokens, $numberPrediction);
            } elseif ($combinedPredictionType === 'big_small') {
                $hitValue = $openNumber >= 25 ? '大' : '小';
                $typePrediction = $this->managedPostGeneratorChoicePrediction($hitValue, array(), array('大', '小'), !empty($isHit), $combinedTypeCount, true);
                $typeTokens = $extractPredictionTokens($typePrediction);
                $numberPrediction = $buildCombinedNumberPrediction('big_small', $typeTokens, $combinedNumberCount, $combinedNumberIsHit);
                $prediction = $combineTypeNumberPrediction($typeTokens, $numberPrediction);
            } else {
                $hitValue = $openNumber % 2 === 1 ? '单' : '双';
                $typePrediction = $this->managedPostGeneratorChoicePrediction($hitValue, array(), array('单', '双'), !empty($isHit), $combinedTypeCount, true);
                $typeTokens = $extractPredictionTokens($typePrediction);
                $numberPrediction = $buildCombinedNumberPrediction('odd_even', $typeTokens, $combinedNumberCount, $combinedNumberIsHit);
                $prediction = $combineTypeNumberPrediction($typeTokens, $numberPrediction);
            }
        } elseif ($typeHasBigSmall && !$typeHasNumber) {
            $hitValue = $openNumber >= 25 ? '大' : '小';
            $prediction = $this->managedPostGeneratorChoicePrediction($hitValue, array(), array('大', '小'), $predictionIsHit, $count);
        } elseif ($typeHasOddEven && !$typeHasNumber) {
            $hitValue = $openNumber % 2 === 1 ? '单' : '双';
            $prediction = $this->managedPostGeneratorChoicePrediction($hitValue, array(), array('单', '双'), $predictionIsHit, $count);
        } elseif ($typeHasHomeWild && !$typeHasNumber) {
            $hitValue = in_array($openZodiac, $homeZodiacs, true) ? '家' : '野';
            $prediction = $this->managedPostGeneratorChoicePrediction($hitValue, array(), array('家', '野'), $predictionIsHit, $count);
        } elseif ($typeHasWave && !$typeHasNumber) {
            $hitValue = $this->managedPostGeneratorWaveByNumber($openNumber);
            $prediction = $this->managedPostGeneratorChoicePrediction($hitValue, $selectedWaves, array('红', '蓝', '绿'), $predictionIsHit, $count, true);
        } elseif ($typeHasElement && !$typeHasNumber) {
            $prediction = $this->managedPostGeneratorChoicePrediction($openElement, $selectedElements, array('金', '木', '水', '火', '土'), $predictionIsHit, $count, true);
        } elseif ($typeHasHead && !$typeHasNumber) {
            $hitValue = (string) floor($openNumber / 10) . '头';
            $prediction = $this->managedPostGeneratorChoicePrediction($hitValue, $selectedHeads, array('0头', '1头', '2头', '3头', '4头'), $predictionIsHit, $count, true);
        } elseif ($typeHasTail && !$typeHasNumber) {
            $hitValue = (string) ($openNumber % 10) . '尾';
            $prediction = $this->managedPostGeneratorChoicePrediction($hitValue, $selectedTails, array('0尾', '1尾', '2尾', '3尾', '4尾', '5尾', '6尾', '7尾', '8尾', '9尾'), $predictionIsHit, $count, true);
        } elseif ($typeHasZodiac && !$typeHasNumber) {
            $prediction = $this->managedPostGeneratorZodiacPrediction($openZodiac, $selectedZodiacs, $count, $predictionIsHit);
        } elseif ($flatNumberHitCount > 0) {
            $flatNumberTargetHitCount = $predictionIsHit ? mt_rand(1, $flatNumberHitCount) : $flatNumberHitCount;
            $prediction = $buildFlatNumberPrediction($count, $flatNumberTargetHitCount, $predictionIsHit);
        } elseif ($typeIsNormalCodeNumber) {
            $normalCodeHitCount = $resolveNormalCodeHitCount($predictionIsHit);
            $prediction = $buildNormalCodePrediction($count, $normalCodeHitCount, $predictionIsHit, $numberGroupSize, $normalCodeComboHitCount);
        } else {
            $numberDrawBlock = $typeIsKill ? array($openNumber) : $allDrawNumbers;
            $prediction = $this->managedPostGeneratorNumberPrediction($openNumber, $numberDrawBlock, $selectedNumbers, $count, $predictionIsHit, $selectedNumbersScopedByZodiac);
        }
        if ($combinedPredictionType === '' && $numberGroupSize > 0) {
            $prediction = $formatNumberPredictionRows($prediction, $numberGroupSize);
        }

        return array(
            'prediction' => $randomizePredictionBrackets($prediction),
            'open_result' => $openResult,
        );
    }

    protected function managedPostGeneratorNumberPrediction($openNumber, array $drawNumbers, array $preferredNumbers, $count, $isHit, $strictPreferredPool = false)
    {
        $count = max(1, min(240, (int) $count));
        $blocked = array_fill_keys(array_map('intval', $drawNumbers), true);
        $preferredNumbers = array_values(array_unique(array_filter(array_map('intval', $preferredNumbers), function ($number) {
            return $number >= 1 && $number <= 49;
        })));
        $strictPreferredPool = !empty($strictPreferredPool) && $preferredNumbers !== array();
        $items = array();
        if ($isHit && $openNumber >= 1 && $openNumber <= 49) {
            $items[] = (int) $openNumber;
        }

        $pool = $preferredNumbers;
        if ($pool === array() && !$strictPreferredPool) {
            $pool = range(1, 49);
        }
        shuffle($pool);
        foreach ($pool as $number) {
            if (count($items) >= $count) {
                break;
            }
            $number = (int) $number;
            if ($number < 1 || $number > 49 || in_array($number, $items, true)) {
                continue;
            }
            if (!$isHit && isset($blocked[$number])) {
                continue;
            }
            $items[] = $number;
            if (count($items) >= $count) {
                break;
            }
        }

        for ($number = 1; count($items) < $count && $number <= 49; $number++) {
            if (in_array($number, $items, true) || (!$isHit && isset($blocked[$number]))) {
                continue;
            }
            $items[] = $number;
        }

        shuffle($items);
        $tokens = array();
        foreach (array_slice($items, 0, $count) as $number) {
            $tokens[] = str_pad((string) ((int) $number), 2, '0', STR_PAD_LEFT);
        }
        if (count($tokens) > 9) {
            $total = count($tokens);
            $rowCount = (int) ceil($total / 9);
            $baseRowSize = (int) floor($total / max(1, $rowCount));
            $extraCount = $total % max(1, $rowCount);
            $groups = array();
            $offset = 0;
            for ($rowIndex = 0; $rowIndex < $rowCount; $rowIndex++) {
                $rowSize = $baseRowSize + ($rowIndex < $extraCount ? 1 : 0);
                $tokenGroup = array_slice($tokens, $offset, max(1, $rowSize));
                $offset += max(1, $rowSize);
                $groups[] = '{ ' . implode(' ', $tokenGroup) . ' }';
            }

            return implode(' ', $groups);
        }

        return '{ ' . implode(' ', $tokens) . ' }';
    }

    protected function managedPostGeneratorZodiacPrediction($openZodiac, array $preferredZodiacs, $count, $isHit)
    {
        $zodiacs = array('鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪');
        $count = max(1, min(12, (int) $count));
        $items = array();
        if ($isHit && $openZodiac !== '') {
            $items[] = $openZodiac;
        }

        $pool = $preferredZodiacs !== array() ? $preferredZodiacs : $zodiacs;
        shuffle($pool);
        foreach ($pool as $zodiac) {
            if (count($items) >= $count) {
                break;
            }
            $zodiac = trim((string) $zodiac);
            if ($zodiac === '' || in_array($zodiac, $items, true) || (!$isHit && $zodiac === $openZodiac)) {
                continue;
            }
            $items[] = $zodiac;
            if (count($items) >= $count) {
                break;
            }
        }

        foreach ($zodiacs as $zodiac) {
            if (count($items) >= $count) {
                break;
            }
            if (in_array($zodiac, $items, true) || (!$isHit && $zodiac === $openZodiac)) {
                continue;
            }
            $items[] = $zodiac;
        }

        shuffle($items);
        $tokens = array_slice($items, 0, $count);
        if (count($tokens) > 10) {
            $total = count($tokens);
            $rowCount = (int) ceil($total / 10);
            $rowSize = (int) ceil($total / max(1, $rowCount));
            $groups = array();
            foreach (array_chunk($tokens, max(1, $rowSize)) as $tokenGroup) {
                $groups[] = '{ ' . implode(' ', $tokenGroup) . ' }';
            }

            return implode(' ', $groups);
        }

        return '{ ' . implode(' ', $tokens) . ' }';
    }

    protected function managedPostGeneratorChoicePrediction($hitValue, array $preferredValues, array $allValues, $isHit, $count = 1, $shuffleItems = false)
    {
        $hitValue = trim((string) $hitValue);
        $count = max(1, min(count($allValues), (int) $count));
        $pool = $preferredValues !== array() ? $preferredValues : $allValues;
        $items = array();
        if ($isHit && $hitValue !== '') {
            $items[] = $hitValue;
        }
        if (count($items) < $count) {
            shuffle($pool);
            foreach ($pool as $value) {
                $value = trim((string) $value);
                if ($value === '' || in_array($value, $items, true) || (!$isHit && $value === $hitValue)) {
                    continue;
                }
                $items[] = $value;
                if (count($items) >= $count) {
                    break;
                }
            }
        }

        if (count($items) < $count) {
            foreach ($allValues as $value) {
                $value = trim((string) $value);
                if ($value !== '' && ($isHit || $value !== $hitValue) && !in_array($value, $items, true)) {
                    $items[] = $value;
                    if (count($items) >= $count) {
                        break;
                    }
                }
            }
        }
        if (!empty($shuffleItems) && count($items) > 1) {
            shuffle($items);
        }

        return '{ ' . implode(' ', $items) . ' }';
    }

    protected function managedPostGeneratorSelectedNumbers(array $values)
    {
        $numbers = array();
        foreach ($values as $value) {
            $number = (int) preg_replace('/\D+/', '', (string) $value);
            if ($number >= 1 && $number <= 49) {
                $numbers[] = $number;
            }
        }

        return array_values(array_unique($numbers));
    }

    protected function managedPostGeneratorSelectedLabels(array $values, array $labels)
    {
        $items = array();
        foreach ($values as $value) {
            $value = (string) $value;
            $label = trim((string) ($labels[$value] ?? $value));
            if ($label !== '') {
                $items[] = $label;
            }
        }

        return array_values(array_unique($items));
    }

    protected function managedPostGeneratorPredictionCount($typeText)
    {
        $typeText = trim((string) $typeText);
        $resolveNumber = static function ($text) {
            $text = trim((string) $text);
            if ($text === '') {
                return 0;
            }
            if (ctype_digit($text)) {
                return (int) $text;
            }
            $numberMap = array(
                '①' => 1, '②' => 2, '③' => 3, '④' => 4, '⑤' => 5,
                '⑥' => 6, '⑦' => 7, '⑧' => 8, '⑨' => 9, '⑩' => 10,
                '零' => 0, '一' => 1, '二' => 2, '两' => 2, '三' => 3, '四' => 4,
                '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9, '十' => 10,
            );
            if (isset($numberMap[$text])) {
                return (int) $numberMap[$text];
            }
            if (mb_strpos($text, '十', 0, 'UTF-8') !== false) {
                $parts = explode('十', $text, 2);
                $left = trim((string) ($parts[0] ?? ''));
                $right = trim((string) ($parts[1] ?? ''));
                $tens = $left === '' ? 1 : (int) ($numberMap[$left] ?? 0);
                $ones = $right === '' ? 0 : (int) ($numberMap[$right] ?? 0);

                return ($tens * 10) + $ones;
            }

            return 0;
        };

        if ($typeText === '平码一码') {
            return 1;
        }
        if (preg_match('/([零一二两三四五六七八九十\d]+)组([二三23②③])中\2/u', $typeText, $matches)) {
            return max(1, min(240, $resolveNumber((string) $matches[1]) * $resolveNumber((string) $matches[2])));
        }
        if (preg_match('/([零一二两三四五六七八九十\d]+)码三中三/u', $typeText, $matches)) {
            return max(1, min(240, $resolveNumber((string) $matches[1]) + 3));
        }
        if (preg_match('/([零一二两三四五六七八九十\d]+)码复(?:式|试)([二三23②③])中\2/u', $typeText, $matches)) {
            return max(1, min(49, $resolveNumber((string) $matches[1])));
        }
        if (preg_match('/(?:头|尾|波|大小|单双)([零一二两三四五六七八九十①②③④⑤⑥⑦⑧⑨⑩\d]+)码/u', $typeText, $matches)) {
            return max(1, min(49, $resolveNumber((string) $matches[1])));
        }
        if (preg_match('/([零一二两三四五六七八九十\d]+)码/u', $typeText, $matches)) {
            return max(1, min(49, $resolveNumber((string) $matches[1])));
        }
        if (preg_match('/([零一二两三四五六七八九十\d]+)肖/u', $typeText, $matches)) {
            return max(1, min(12, $resolveNumber((string) $matches[1])));
        }
        if (preg_match('/([零一二两三四五六七八九十①②③④⑤⑥⑦⑧⑨⑩\d]+)(?:头|尾|波)/u', $typeText, $matches)) {
            return max(1, min(10, $resolveNumber((string) $matches[1])));
        }
        if (mb_strpos($typeText, '半头', 0, 'UTF-8') !== false
            || mb_strpos($typeText, '大小中特', 0, 'UTF-8') !== false
            || mb_strpos($typeText, '单双中特', 0, 'UTF-8') !== false
            || mb_strpos($typeText, '家野中特', 0, 'UTF-8') !== false
        ) {
            return 1;
        }
        if (preg_match('/([零一二两三四五六七八九十\d]+)/u', $typeText, $matches)) {
            return max(1, min(49, $resolveNumber((string) $matches[1])));
        }

        return mb_strpos($typeText, '肖', 0, 'UTF-8') !== false ? 6 : 5;
    }

    protected function managedPostGeneratorZodiacByNumber($number, $drawDate)
    {
        return $this->app->prediction()->drawZodiacByNumber($number, $drawDate);
    }

    protected function managedPostGeneratorWaveByNumber($number)
    {
        $number = (int) $number;
        if (in_array($number, array(1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46), true)) {
            return '红';
        }
        if (in_array($number, array(3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48), true)) {
            return '蓝';
        }

        return '绿';
    }

    protected function wrapManagedPostGeneratorTitleMiddle($titleMiddle, $wrap)
    {
        $titleMiddle = trim((string) $titleMiddle);
        $wrap = trim((string) $wrap);
        if ($titleMiddle === '' || $wrap === '') {
            return $titleMiddle;
        }

        $wrapLength = function_exists('mb_strlen') ? mb_strlen($wrap, 'UTF-8') : strlen($wrap);
        if ($wrapLength <= 0) {
            return $titleMiddle;
        }

        if ($wrapLength === 1) {
            $left = $wrap;
            $right = $wrap;
        } elseif (function_exists('mb_substr')) {
            $left = mb_substr($wrap, 0, 1, 'UTF-8');
            $right = mb_substr($wrap, -1, 1, 'UTF-8');
        } else {
            $left = substr($wrap, 0, 1);
            $right = substr($wrap, -1);
        }

        if ($left === '' || $right === '') {
            return $titleMiddle;
        }

        $leftLength = function_exists('mb_strlen') ? mb_strlen($left, 'UTF-8') : strlen($left);
        $rightLength = function_exists('mb_strlen') ? mb_strlen($right, 'UTF-8') : strlen($right);
        $currentLeft = function_exists('mb_substr') ? mb_substr($titleMiddle, 0, $leftLength, 'UTF-8') : substr($titleMiddle, 0, $leftLength);
        $currentRight = function_exists('mb_substr') ? mb_substr($titleMiddle, -1 * $rightLength, $rightLength, 'UTF-8') : substr($titleMiddle, -1 * $rightLength);

        if ($currentLeft === $left && $currentRight === $right) {
            return $titleMiddle;
        }

        return $left . $titleMiddle . $right;
    }

    public function generateManagedForumPosts(array $payload, array $actor)
    {
        $config = $this->managedPostGeneratorConfig((string) ($payload['region'] ?? 'macau'));
        $region = (string) ($payload['generator_type'] ?? $config['region']);
        if ($region !== 'hongkong') {
            $region = 'macau';
        }
        if ($region !== $config['region']) {
            $config = $this->managedPostGeneratorConfig($region);
        }
        if (!array_key_exists('is_blank_content', $payload)) {
            $payload['is_blank_content'] = !empty($config['is_blank_content']) ? '1' : '';
        }
        $defaultTargets = (array) ($config['default_targets'] ?? array());
        $sectionId = (int) ($defaultTargets['section_id'] ?? 0);
        $categoryId = (int) ($defaultTargets['category_id'] ?? 0);
        if ($sectionId <= 0 || $categoryId <= 0) {
            throw new RuntimeException('当前地区还没有可用的版块和分类，无法生成帖子。');
        }

        $segmentNo = max(1, min(3, (int) ($payload['segment_no'] ?? 1)));
        $topScope = trim((string) ($payload['top_scope'] ?? 'top_1'));
        $topOptions = $this->managedPostGeneratorTopOptions();
        if (!isset($topOptions[$topScope])) {
            $topScope = 'top_1';
        }
        $topOption = $topOptions[$topScope];

        $issueTail = preg_replace('/\D+/', '', (string) ($payload['current_issue_tail'] ?? ''));
        if ($issueTail === '') {
            $issueTail = (string) ($config['current_issue_tail'] ?? $this->nextManagedIssueTail($region));
        }
        $issueTail = str_pad(substr($issueTail, -3), 3, '0', STR_PAD_LEFT);

        $templateMap = array();
        foreach ((array) ($config['template_groups'] ?? array()) as $group) {
            foreach ((array) ($group['items'] ?? array()) as $item) {
                $templateMap[(string) $item['key']] = array(
                    'group_label' => (string) ($group['label'] ?? ''),
                    'label' => (string) ($item['label'] ?? ''),
                );
            }
        }

        $selectedTemplates = isset($payload['templates']) && is_array($payload['templates'])
            ? array_values(array_unique(array_filter(array_map('strval', $payload['templates']))))
            : array();
        $selectedTemplates = array_values(array_filter($selectedTemplates, static function ($key) use ($templateMap) {
            return isset($templateMap[$key]);
        }));
        if (empty($selectedTemplates)) {
            throw new RuntimeException('请至少勾选一个生成模板。');
        }

        $zodiacLabels = array();
        foreach ((array) ($config['zodiac_options'] ?? array()) as $option) {
            $zodiacLabels[(string) $option['value']] = (string) $option['label'];
        }
        $numberLabels = array();
        foreach ((array) ($config['number_options'] ?? array()) as $option) {
            $numberLabels[(string) $option['value']] = (string) $option['label'];
        }
        $waveLabels = array();
        foreach ((array) ($config['wave_options'] ?? array()) as $option) {
            $waveLabels[(string) $option['value']] = (string) $option['label'];
        }
        $elementLabels = array();
        foreach ((array) ($config['element_options'] ?? array()) as $option) {
            $elementLabels[(string) $option['value']] = (string) $option['label'];
        }
        $headLabels = array();
        foreach ((array) ($config['head_options'] ?? array()) as $option) {
            $headLabels[(string) $option['value']] = (string) $option['label'];
        }
        $tailLabels = array();
        foreach ((array) ($config['tail_options'] ?? array()) as $option) {
            $tailLabels[(string) $option['value']] = (string) $option['label'];
        }

        $segmentLabels = array();
        foreach ((array) ($config['segment_options'] ?? array()) as $option) {
            $segmentLabels[(int) ($option['value'] ?? 0)] = (string) ($option['label'] ?? '');
        }

        $selectedZodiacs = isset($payload['target_zodiac']) && is_array($payload['target_zodiac'])
            ? array_values(array_unique(array_filter(array_map('strval', $payload['target_zodiac']))))
            : array();
        $selectedNumbers = isset($payload['target_number']) && is_array($payload['target_number'])
            ? array_values(array_unique(array_filter(array_map('strval', $payload['target_number']))))
            : array();
        $selectedWaves = isset($payload['target_wave']) && is_array($payload['target_wave'])
            ? array_values(array_unique(array_filter(array_map('strval', $payload['target_wave']))))
            : array();
        $selectedElements = isset($payload['target_element']) && is_array($payload['target_element'])
            ? array_values(array_unique(array_filter(array_map('strval', $payload['target_element']))))
            : array();
        $selectedHeads = isset($payload['target_head']) && is_array($payload['target_head'])
            ? array_values(array_unique(array_filter(array_map('strval', $payload['target_head']))))
            : array();
        $selectedTails = isset($payload['target_tail']) && is_array($payload['target_tail'])
            ? array_values(array_unique(array_filter(array_map('strval', $payload['target_tail']))))
            : array();

        $noMaterial = !empty($payload['is_blank_content']);
        $isHidden = !empty($payload['is_hidden_first']);
        $titlePrefix = array_key_exists('title_prefix', $payload) ? trim((string) ($payload['title_prefix'] ?? '')) : '鸿运六合';
        $titleMiddle = array_key_exists('title_middle', $payload) ? trim((string) ($payload['title_middle'] ?? '')) : '';
        $titleMiddleWrap = $this->normalizeManagedPostGeneratorText($payload['title_middle_wrap'] ?? ($config['title_middle_wrap'] ?? ''), 20);
        if (in_array($titleMiddle, array('[帖子类型]', '[随机作者]', '【一码中特】', '一码中特'), true)) {
            $titleMiddle = '';
        }
        $authorNickname = array_key_exists('author_nickname', $payload) ? trim((string) ($payload['author_nickname'] ?? '')) : '';
        if (in_array($authorNickname, array('[随机作者]', '[帖子作者]', '[帖子类型]'), true)) {
            $authorNickname = '';
        }
        $this->assertManagedAuthorNicknameAllowed($authorNickname);
        $titlePrefixColorMode = $this->normalizeManagedTitleColorMode((string) ($payload['title_prefix_color_mode'] ?? ($config['title_prefix_color_mode'] ?? '')));
        $titlePrefixColorValue = $titlePrefixColorMode === 'fixed'
            ? $this->normalizeManagedTitleColorValue((string) ($payload['title_prefix_color_value'] ?? ($config['title_prefix_color_value'] ?? '#2563eb')))
            : '';
        $titleMiddleColorMode = $this->normalizeManagedTitleColorMode((string) ($payload['title_middle_color_mode'] ?? ($config['title_middle_color_mode'] ?? '')));
        $titleMiddleColorValue = $titleMiddleColorMode === 'fixed'
            ? $this->normalizeManagedTitleColorValue((string) ($payload['title_middle_color_value'] ?? ($config['title_middle_color_value'] ?? '#2563eb')))
            : '';
        $authorNicknameColorMode = $this->normalizeManagedTitleColorMode((string) ($payload['author_nickname_color_mode'] ?? ($config['author_nickname_color_mode'] ?? '')));
        $authorNicknameColorValue = $authorNicknameColorMode === 'fixed'
            ? $this->normalizeManagedTitleColorValue((string) ($payload['author_nickname_color_value'] ?? ($config['author_nickname_color_value'] ?? '#2563eb')))
            : '';
        $authorNicknamePoolRaw = array_key_exists('author_nickname_pool', $payload) ? (string) ($payload['author_nickname_pool'] ?? '') : '';
        $authorNicknamePool = $this->normalizeManagedAuthorNicknamePoolSafe($authorNicknamePoolRaw);
        if ($authorNicknamePoolRaw !== '' && empty($authorNicknamePool)) {
            throw new RuntimeException('批量作者昵称不能为空，且必须为四个字的成语。');
        }
        if ($authorNickname !== '') {
            array_unshift($authorNicknamePool, $authorNickname);
            $authorNicknamePool = array_values(array_unique($authorNicknamePool));
        }
        $titleFontSize = $this->normalizeManagedPostGeneratorChoice((string) ($payload['title_font_size'] ?? ($config['title_font_size'] ?? '')), array('12', '13', '14', '15', '16', '17', '18', '20', '22', '24'));
        $titleFontWeight = $this->normalizeManagedPostGeneratorChoice((string) ($payload['title_font_weight'] ?? ($config['title_font_weight'] ?? '')), array('400', '500', '600', '700', '800', '900'));
        $materialContentTime = $this->normalizeManagedPostGeneratorTime($payload['material_content_time'] ?? ($config['material_content_time'] ?? ''));
        $createdAt = $this->managedPostGeneratorCreatedAt($materialContentTime);
        $selectedPresetSegments = $this->normalizeManagedPostGeneratorArray(
            $payload['preset_segments'] ?? ($config['preset_segments'] ?? array()),
            $this->managedPostGeneratorAllowedValues((array) ($config['segment_options'] ?? array()))
        );
        $selectedSegmentNos = array();
        foreach ($selectedPresetSegments as $selectedPresetSegment) {
            $selectedSegmentNo = max(1, min(3, (int) $selectedPresetSegment));
            $selectedSegmentNos[$selectedSegmentNo] = $selectedSegmentNo;
        }
        if ($selectedSegmentNos === array()) {
            $selectedSegmentNos = array(1 => 1, 2 => 2, 3 => 3);
        }
        $selectedSegmentNos = array_values($selectedSegmentNos);
        $presetZodiacMin = (int) $this->normalizeManagedPostGeneratorRangeValue($payload['preset_zodiac_min'] ?? ($config['preset_zodiac_min'] ?? ''), 0, 12);
        $presetZodiacMax = (int) $this->normalizeManagedPostGeneratorRangeValue($payload['preset_zodiac_max'] ?? ($config['preset_zodiac_max'] ?? ''), 0, 12);
        if ($presetZodiacMin > $presetZodiacMax) {
            $swapZodiacCount = $presetZodiacMin;
            $presetZodiacMin = $presetZodiacMax;
            $presetZodiacMax = $swapZodiacCount;
        }
        if ($presetZodiacMax > 0) {
            try {
                $aiForecast = $this->app->prediction()->forecastZodiacPoolForGenerator(
                    $region,
                    $presetZodiacMin,
                    $presetZodiacMax
                );
                $aiZodiacValues = array();
                $zodiacValueByLabel = array();
                foreach ($zodiacLabels as $zodiacValue => $zodiacLabel) {
                    $zodiacValueByLabel[(string) $zodiacLabel] = (string) $zodiacValue;
                }
                foreach ((array) ($aiForecast['labels'] ?? array()) as $aiZodiacLabel) {
                    $aiZodiacLabel = trim((string) $aiZodiacLabel);
                    if ($aiZodiacLabel === '') {
                        continue;
                    }
                    $aiZodiacValues[] = (string) ($zodiacValueByLabel[$aiZodiacLabel] ?? $aiZodiacLabel);
                    if (count($aiZodiacValues) >= (int) ($aiForecast['target_count'] ?? $presetZodiacMax)) {
                        break;
                    }
                }
                if ($aiZodiacValues !== array()) {
                    $selectedZodiacs = array_values(array_unique($aiZodiacValues));
                }
            } catch (\Throwable $exception) {
                // 保留原指定生肖，避免预测接口临时不可用时中断帖子生成。
            }
        }
        $presetSegmentMin = (int) $this->normalizeManagedPostGeneratorRangeValue($payload['preset_segment_min'] ?? ($config['preset_segment_min'] ?? ''), 0, 99);
        $presetSegmentMax = (int) $this->normalizeManagedPostGeneratorRangeValue($payload['preset_segment_max'] ?? ($config['preset_segment_max'] ?? ''), 0, 99);
        if ($presetSegmentMin > $presetSegmentMax) {
            $swapSegmentCount = $presetSegmentMin;
            $presetSegmentMin = $presetSegmentMax;
            $presetSegmentMax = $swapSegmentCount;
        }
        $maintainSegmentCounts = (string) ($payload['generator_run_mode'] ?? 'manual') === 'maintain';
        $generationPlan = $this->managedPostGeneratorBuildPlan(
            $selectedTemplates,
            $selectedSegmentNos,
            $presetSegmentMin,
            $presetSegmentMax,
            $region,
            $maintainSegmentCounts,
            !empty($payload['fill_segment_range_after_delete'])
        );
        if ($generationPlan === array()) {
            return array(
                'count' => 0,
                'ids' => array(),
                'region' => $region,
            );
        }
        $generatedAuthorNicknameQueue = $this->managedGeneratorUniqueAuthorNicknames($region, $authorNicknamePool, count($generationPlan));
        $titleMiddleRandomColorPalette = array();
        $titleMiddleRandomColorIndex = 0;
        if ($titleMiddleColorMode === 'daily_random') {
            $titleMiddleRandomColorPalette = array(
                '#DC2626',
                '#EA580C',
                '#CA8A04',
                '#16A34A',
                '#0891B2',
                '#2563EB',
                '#7C3AED',
                '#DB2777',
                '#0F766E',
                '#9333EA',
                '#1D4ED8',
                '#BE123C',
            );
            shuffle($titleMiddleRandomColorPalette);
        }
        $summaryPrice = isset($topOption['price']) ? max(0, (int) $topOption['price']) : 0;
        $segmentSortMap = array();
        foreach ($selectedSegmentNos as $selectedSegmentNo) {
            $segmentSortMap[$selectedSegmentNo] = $this->managedPostGeneratorNextSort($region, $selectedSegmentNo);
        }
        $presetRecordMin = (int) $this->normalizeManagedPostGeneratorRangeValue($payload['preset_record_min'] ?? ($config['preset_record_min'] ?? ''), 1, 99);
        $presetRecordMax = (int) $this->normalizeManagedPostGeneratorRangeValue($payload['preset_record_max'] ?? ($config['preset_record_max'] ?? ''), 1, 99);
        $presetRecordRateMin = (int) $this->normalizeManagedPostGeneratorRangeValue($payload['preset_record_rate_min'] ?? ($config['preset_record_rate_min'] ?? ''), 0, 100);
        $presetRecordRateMax = (int) $this->normalizeManagedPostGeneratorRangeValue($payload['preset_record_rate_max'] ?? ($config['preset_record_rate_max'] ?? ''), 0, 100);
        $postViewDisplaySettings = $this->app->posts()->postViewDisplaySettings();
        $postViewBaseMin = (int) $postViewDisplaySettings['base_min'];
        $postViewBaseMax = (int) $postViewDisplaySettings['base_max'];
        $createdIds = array();
        $createdVisibleCount = 0;
        $createdSegmentCounts = array(
            1 => 0,
            2 => 0,
            3 => 0,
        );
        $normalCodeHotNumbersForGeneration = $this->managedPostGeneratorAiHotNumbers($region);

        foreach ($generationPlan as $planItem) {
            $templateKey = (string) ($planItem['template_key'] ?? '');
            $currentSegmentNo = max(1, min(3, (int) ($planItem['segment_no'] ?? $segmentNo)));
            $template = $templateMap[$templateKey];
            $templateLabel = trim((string) ($template['label'] ?? ''));
            $currentSegmentSort = (int) ($segmentSortMap[$currentSegmentNo] ?? $this->managedPostGeneratorNextSort($region, $currentSegmentNo));
            $currentAuthorNickname = (string) array_shift($generatedAuthorNicknameQueue);
            $this->assertManagedAuthorNicknameAllowed($currentAuthorNickname);
            $currentTitleMiddleColorValue = $titleMiddleColorValue;
            if ($titleMiddleColorMode === 'daily_random' && $titleMiddleRandomColorPalette !== array()) {
                $currentTitleMiddleColorValue = $titleMiddleRandomColorPalette[$titleMiddleRandomColorIndex % count($titleMiddleRandomColorPalette)];
                $titleMiddleRandomColorIndex++;
            }
            $generatorVariables = $this->managedPostGeneratorVariableMap($issueTail, $templateLabel, $currentAuthorNickname);
            $resolvedTitlePrefix = $this->applyManagedPostGeneratorVariables($titlePrefix, $generatorVariables);
            $titleMiddleTemplate = $titleMiddle !== '' ? $titleMiddle : '[帖子类型]';
            $resolvedTitleMiddle = $this->wrapManagedPostGeneratorTitleMiddle(
                $this->applyManagedPostGeneratorVariables($titleMiddleTemplate, $generatorVariables),
                $titleMiddleWrap
            );
            $titleBody = trim(
                $resolvedTitlePrefix
                . $resolvedTitleMiddle
            );
            if ($titleBody === '') {
                $titleBody = $templateLabel;
            }
            $title = $titleBody;
            $content = "资料等待更新中··· ···\n关注本站，精彩无限，中奖根本停不下来······";
            $recentResultLog = '';
            $forecastOptions = array(
                'record_min' => $presetRecordMin,
                'record_max' => $presetRecordMax,
                'rate_min' => $presetRecordRateMin,
                'rate_max' => $presetRecordRateMax,
                'zodiac_min' => $presetZodiacMin,
                'zodiac_max' => $presetZodiacMax,
                'selected_zodiacs' => $selectedZodiacs,
                'zodiac_labels' => $zodiacLabels,
                'selected_numbers' => $selectedNumbers,
                'selected_waves' => $selectedWaves,
                'wave_labels' => $waveLabels,
                'selected_elements' => $selectedElements,
                'element_labels' => $elementLabels,
                'selected_heads' => $selectedHeads,
                'head_labels' => $headLabels,
                'selected_tails' => $selectedTails,
                'tail_labels' => $tailLabels,
                'normal_code_hot_numbers' => $normalCodeHotNumbersForGeneration,
            );
            if ($noMaterial) {
                $forecastOptions['pending_prediction'] = '资料等待更新中··· ···';
            }
            $forecastContent = $this->managedPostGeneratorForecastContent(
                $region,
                $issueTail,
                $currentAuthorNickname,
                $templateLabel,
                $forecastOptions
            );
            $forecastText = trim((string) ($forecastContent['content'] ?? ''));
            $recentResultLog = trim((string) ($forecastContent['recent_result_log'] ?? ''));
            $postCreatedAt = $this->managedPostGeneratorCreatedAt(
                '',
                '',
                (int) ($forecastContent['date_back_days'] ?? 0),
                true
            );
            if ($postCreatedAt === '') {
                $postCreatedAt = $createdAt;
            }
            $content = $forecastText;
            if ($content === '') {
                $content = $noMaterial
                    ? "资料等待更新中··· ···\n关注本站，精彩无限，中奖根本停不下来······"
                    : '无资料';
            }

            $savedPost = $this->createGeneratedManagedForumPost(
                array(
                    'region' => $region,
                    'section_id' => $sectionId,
                    'category_id' => $categoryId,
                    'title' => $title,
                    'excerpt' => truncate_text($content, 60),
                    'preview_content' => truncate_text($content, 80),
                    'full_content' => $content,
                    'price' => $summaryPrice,
                    'view_count' => $this->managedPostGeneratorRandomRange($postViewBaseMin, $postViewBaseMax),
                    'color_tag' => (string) ($topOption['color_tag'] ?? 'slate'),
                    'status' => 'published',
                    'is_top_forever' => !empty($topOption['is_top_forever']) ? 1 : 0,
                    'is_top_admin' => !empty($topOption['is_top_admin']) ? 1 : 0,
                    'is_top_normal' => !empty($topOption['is_top_normal']) ? 1 : 0,
                    'segment_no' => $currentSegmentNo,
                    'segment_sort' => $currentSegmentSort,
                    'post_kind' => 'normal',
                    'is_fake' => 0,
                    'is_hidden' => $isHidden ? 1 : 0,
                    'manual_material' => $content,
                    'auto_update_mode' => 'none',
                    'auto_update_content' => '',
                    'recent_result_log' => $recentResultLog,
                    'fake_buyer_count' => 0,
                    'title_prefix' => $resolvedTitlePrefix,
                    'title_middle' => $resolvedTitleMiddle,
                    'title_prefix_color_mode' => $titlePrefixColorMode,
                    'title_prefix_color_value' => $titlePrefixColorValue,
                    'title_middle_color_mode' => $titleMiddleColorMode,
                    'title_middle_color_value' => $currentTitleMiddleColorValue,
                    'author_nickname_color_mode' => $authorNicknameColorMode,
                    'author_nickname_color_value' => $authorNicknameColorValue,
                    'title_font_size' => $titleFontSize,
                    'title_font_weight' => $titleFontWeight,
                    'author_nickname' => $currentAuthorNickname,
                    'created_at' => $postCreatedAt,
                ),
                $actor
            );
            $createdIds[] = (int) ($savedPost['id'] ?? 0);
            if (!$isHidden) {
                $createdVisibleCount++;
                $createdSegmentCounts[$currentSegmentNo] = (int) ($createdSegmentCounts[$currentSegmentNo] ?? 0) + 1;
            }
            $segmentSortMap[$currentSegmentNo] = $currentSegmentSort + 1;
        }
        $this->clearManagedPostSelectOptionsCache();
        if ($createdIds !== array()) {
            $this->recordOperation(
                (int) ($actor['id'] ?? 0),
                'posts',
                'generate_posts',
                'post',
                0,
                '批量生成帖子：' . $region . ' / ' . count($createdIds) . '篇'
            );
        }

        return array(
            'count' => count($createdIds),
            'ids' => $createdIds,
            'region' => $region,
            'visible_count' => $createdVisibleCount,
            'segment_counts' => $createdSegmentCounts,
        );
    }

    public function runManagedPostGeneratorSchedule(array $actor = array())
    {
        $runActor = $this->managedPostGeneratorRunActor($actor);
        if ((int) ($runActor['id'] ?? 0) <= 0) {
            return array();
        }

        $waitingText = '资料等待更新中··· ···';
        $waitingFollowText = '关注本站，精彩无限，中奖根本停不下来······';
        $waitingContent = $waitingText . "\n" . $waitingFollowText;
        $results = array();
        foreach (array('macau', 'hongkong') as $region) {
            $config = $this->managedPostGeneratorConfig($region);
            $postTimeValue = $this->normalizeManagedPostGeneratorTime($config['post_update_time'] ?? '');
            $materialTimeValue = $this->normalizeManagedPostGeneratorTime($config['material_content_time'] ?? '');
            $saleMaterialTimeValue = $this->normalizeManagedPostGeneratorTime($config['sale_material_content_time'] ?? '');
            $lockState = $this->app->posts()->postLockState($region);
            $autoReplyInsertedCount = 0;
            if (!empty($config['auto_reply_enabled'])) {
                $autoReplyRows = $this->db()->fetchAll(
                    'SELECT posts.id
                     FROM posts
                     LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
                     WHERE posts.region = :region
                       AND posts.status = :status
                       AND posts.deleted_at IS NULL
                       AND COALESCE(post_meta.is_hidden, 0) = 0
                       AND COALESCE(post_meta.segment_no, 1) BETWEEN 1 AND 3
                     ORDER BY posts.id ASC
                     LIMIT 500',
                    array(
                        'region' => $region,
                        'status' => 'published',
                    )
                );
                foreach ($autoReplyRows as $autoReplyRow) {
                    $autoReplyInsertedCount += $this->app->posts()->seedAutoRepliesForPost((int) ($autoReplyRow['id'] ?? 0));
                }
            }
            if ($postTimeValue === '' && $materialTimeValue === '' && $saleMaterialTimeValue === '' && empty($lockState['is_locked'])) {
                if ($autoReplyInsertedCount > 0) {
                    $results[$region] = array(
                        'count' => 0,
                        'ids' => array(),
                        'region' => $region,
                        'auto_reply_count' => $autoReplyInsertedCount,
                    );
                }
                continue;
            }

            $runDate = date('Y-m-d');
            $runKey = 'post_generator.schedule_last_run.' . $region;
            $materialRunKey = 'post_generator.material_last_run.' . $region;
            $saleMaterialRunKey = 'post_generator.sale_material_last_run.' . $region;
            $sourceSyncKey = 'post_generator.schedule_source_sync_date.' . $region;
            $latestStoredDraw = $this->db()->fetch(
                'SELECT issue_no
                 FROM lottery_draws
                 WHERE region = :region
                 ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
                 LIMIT 1',
                array('region' => $region)
            );
            $preliminaryIssueNo = is_array($latestStoredDraw)
                ? preg_replace('/\D+/', '', (string) ($latestStoredDraw['issue_no'] ?? ''))
                : '';
            $preliminaryTargetIssueNo = $preliminaryIssueNo !== ''
                ? preg_replace('/\D+/', '', (string) $this->incrementIssueNo($preliminaryIssueNo))
                : '';
            $lastRunIssueNo = (string) $this->app->settings()->get($runKey, '');
            $sourceSyncedDate = (string) $this->app->settings()->get($sourceSyncKey, '');

            if ($sourceSyncedDate !== $runDate) {
                try {
                    if ($region === 'hongkong') {
                        $this->app->prediction()->syncHongkongCurrentYearHistory();
                    } else {
                        $this->app->prediction()->syncMacauCurrentYearHistory();
                    }
                    $this->app->settings()->setMany('post_generator', array(
                        $sourceSyncKey => $runDate,
                    ));
                } catch (\Throwable $exception) {
                    try {
                        $this->writeManagedExceptionLog($exception, 'posts', 'post_generator_schedule_source_sync', 'system', 0);
                    } catch (\Throwable $loggingException) {
                    }
                }

                $config = $this->managedPostGeneratorConfig($region);
            }

            $latestDraw = $this->app->prediction()->latestHomepageDraw($region);
            $latestIssueNo = is_array($latestDraw) ? preg_replace('/\D+/', '', (string) ($latestDraw['issue_no'] ?? '')) : '';
            $afterDrawBackfillResult = array('deleted_count' => 0, 'generated_count' => 0);
            if ($latestIssueNo !== '') {
                $afterDrawRunKey = 'post_generator.after_draw_last_run.' . $region;
                $afterDrawLastRunIssueNo = preg_replace(
                    '/\D+/',
                    '',
                    (string) $this->app->settings()->get($afterDrawRunKey, '')
                );
                $shouldRunAfterDrawBackfill = $afterDrawLastRunIssueNo === ''
                    || (int) $afterDrawLastRunIssueNo < (int) $latestIssueNo;
                if (!$shouldRunAfterDrawBackfill) {
                    $shouldRunAfterDrawBackfill = $this->managedPostGeneratorOverflowDeleteIds($region, $config) !== array();
                }
                if ($shouldRunAfterDrawBackfill) {
                    try {
                        $afterDrawBackfillResult = $this->runManagedPostGeneratorAfterDraw($region, (array) $latestDraw, $runActor);
                    } catch (\Throwable $exception) {
                        try {
                            $this->writeManagedExceptionLog($exception, 'posts', 'post_generator_after_draw_backfill', 'system', 0);
                        } catch (\Throwable $loggingException) {
                        }
                    }
                }
            }
            $targetIssueNo = $latestIssueNo !== ''
                ? preg_replace('/\D+/', '', (string) $this->incrementIssueNo($latestIssueNo))
                : '';
            $scheduleDate = date('Y-m-d');
            $targetOpenAt = is_array($latestDraw) ? trim((string) ($latestDraw['next_open_time'] ?? '')) : '';
            if ($targetOpenAt === '') {
                $currentIssue = $this->currentIssueSnapshotByRegion($region);
                $currentIssueNo = is_array($currentIssue)
                    ? preg_replace('/\D+/', '', (string) ($currentIssue['issue_no'] ?? ''))
                    : '';
                if ($currentIssueNo !== '' && ($targetIssueNo === '' || $currentIssueNo === $targetIssueNo)) {
                    $targetOpenAt = trim((string) ($currentIssue['planned_open_at'] ?? ''));
                }
            }
            $postUpdateScheduleDate = $scheduleDate;
            $materialUpdateScheduleDate = $scheduleDate;
            $saleMaterialUpdateScheduleDate = $scheduleDate;
            $targetOpenTimestamp = $targetOpenAt !== '' ? strtotime($targetOpenAt) : false;
            if ($targetOpenTimestamp !== false) {
                $postUpdateScheduleDate = date('Y-m-d', $targetOpenTimestamp);
                $materialUpdateScheduleDate = date('Y-m-d', $targetOpenTimestamp);
                $saleMaterialUpdateScheduleDate = date('Y-m-d', $targetOpenTimestamp);
                if ($postTimeValue !== '') {
                    $postUpdateTimestamp = strtotime($postUpdateScheduleDate . ' ' . $postTimeValue . ':00');
                    if ($postUpdateTimestamp !== false && $postUpdateTimestamp >= $targetOpenTimestamp) {
                        $postUpdateScheduleDate = date('Y-m-d', strtotime('-1 day', $postUpdateTimestamp));
                    }
                }
                if ($materialTimeValue !== '') {
                    $materialUpdateTimestamp = strtotime($materialUpdateScheduleDate . ' ' . $materialTimeValue . ':00');
                    if ($materialUpdateTimestamp !== false && $materialUpdateTimestamp >= $targetOpenTimestamp) {
                        $materialUpdateScheduleDate = date('Y-m-d', strtotime('-1 day', $materialUpdateTimestamp));
                    }
                }
                if ($saleMaterialTimeValue !== '') {
                    $saleMaterialUpdateTimestamp = strtotime($saleMaterialUpdateScheduleDate . ' ' . $saleMaterialTimeValue . ':00');
                    if ($saleMaterialUpdateTimestamp !== false && $saleMaterialUpdateTimestamp >= $targetOpenTimestamp) {
                        $saleMaterialUpdateScheduleDate = date('Y-m-d', strtotime('-1 day', $saleMaterialUpdateTimestamp));
                    }
                }
            }
            $nowText = $this->now();
            $lockFallbackMaterialUpdate = false;
            if (!empty($lockState['is_locked'])) {
                $lockPlannedOpenAt = trim((string) ($lockState['planned_open_at'] ?? ''));
                $nowTimestamp = strtotime($nowText);
                $lockPlannedOpenTimestamp = $lockPlannedOpenAt !== '' ? strtotime($lockPlannedOpenAt) : false;
                $lockFallbackMaterialUpdate = $nowTimestamp !== false
                    && $lockPlannedOpenTimestamp !== false
                    && $nowTimestamp < $lockPlannedOpenTimestamp;
            }
            $shouldRunPostUpdate = $postTimeValue !== ''
                && $nowText >= $postUpdateScheduleDate . ' ' . $postTimeValue . ':00';
            $saleMaterialTimeTriggered = $saleMaterialTimeValue !== ''
                && $nowText >= $saleMaterialUpdateScheduleDate . ' ' . $saleMaterialTimeValue . ':00';
            $shouldRunSaleMaterialUpdate = $saleMaterialTimeTriggered;
            $managedIssueTailForSchedule = preg_replace(
                '/\D+/',
                '',
                (string) ($config['current_issue_tail'] ?? $this->nextManagedIssueTail($region))
            );
            $issueTailSource = $shouldRunPostUpdate && $targetIssueNo !== ''
                ? $targetIssueNo
                : $managedIssueTailForSchedule;
            $issueTail = preg_replace(
                '/\D+/',
                '',
                $issueTailSource !== '' ? $issueTailSource : $targetIssueNo
            );
            if ($issueTail === '') {
                continue;
            }
            $issueTail = str_pad(substr($issueTail, -3), 3, '0', STR_PAD_LEFT);
            $targetRunIssueNo = $targetIssueNo !== '' ? $targetIssueNo : $issueTail;

            $zodiacLabels = array();
            foreach ((array) ($config['zodiac_options'] ?? array()) as $option) {
                $zodiacLabels[(string) $option['value']] = (string) $option['label'];
            }
            $waveLabels = array();
            foreach ((array) ($config['wave_options'] ?? array()) as $option) {
                $waveLabels[(string) $option['value']] = (string) $option['label'];
            }
            $headLabels = array();
            foreach ((array) ($config['head_options'] ?? array()) as $option) {
                $headLabels[(string) $option['value']] = (string) $option['label'];
            }
            $tailLabels = array();
            foreach ((array) ($config['tail_options'] ?? array()) as $option) {
                $tailLabels[(string) $option['value']] = (string) $option['label'];
            }

            $updatedIds = array();
            $materialUpdatedIds = array();
            $saleMaterialUpdatedIds = array();
            $issueMarker = (string) ((int) $issueTail) . html_entity_decode('&#26399;:', ENT_QUOTES, 'UTF-8');

            if ($shouldRunSaleMaterialUpdate) {
                $saleMaterialLockFallbackOnly = $lockFallbackMaterialUpdate && !$saleMaterialTimeTriggered;
                $saleRows = $this->db()->fetchAll(
                    'SELECT posts.id,
                            posts.full_content,
                            (SELECT COUNT(*) FROM purchases WHERE purchases.post_id = posts.id) AS real_purchase_count,
                            COALESCE(NULLIF(post_meta.author_nickname, \'\'), users.username) AS author_nickname,
                            COALESCE(post_meta.title_middle_text, \'\') AS title_middle_text,
                            COALESCE(post_meta.recent_result_log, \'\') AS recent_result_log
                     FROM posts
                     INNER JOIN users ON users.id = posts.author_id
                     LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
                     WHERE posts.region = :region
                       AND posts.status = :status
                       AND posts.deleted_at IS NULL
                       AND posts.price > 0
                       AND COALESCE(post_meta.is_hidden, 0) = 0
                       AND COALESCE(post_meta.segment_no, 1) BETWEEN 1 AND 3
                     ORDER BY COALESCE(post_meta.segment_no, 1) ASC,
                              COALESCE(NULLIF(post_meta.segment_sort, 0), posts.id) ASC,
                              posts.id ASC
                     LIMIT 300',
                    array(
                        'region' => $region,
                        'status' => 'published',
                    )
                );
                foreach ($saleRows as $saleRow) {
                    $postId = (int) ($saleRow['id'] ?? 0);
                    $existingContent = trim((string) ($saleRow['full_content'] ?? ''));
                    $hasCurrentIssueContent = strpos($existingContent, $issueMarker) !== false;
                    $hasWaitingMaterial = mb_strpos($existingContent, '资料等待更新中', 0, 'UTF-8') !== false;
                    $hasCurrentIssuePlaceholder = false;
                    $existingContentLines = preg_split('/\R/u', $existingContent);
                    if (is_array($existingContentLines)) {
                        foreach ($existingContentLines as $existingContentLine) {
                            $lineText = (string) $existingContentLine;
                            if (!preg_match('/^\s*(\d{1,6})\s*[^:\x{FF1A}]{0,16}[:\x{FF1A}]/u', $lineText, $issueMatches)) {
                                continue;
                            }

                            $issueDigits = preg_replace('/\D+/', '', (string) ($issueMatches[1] ?? ''));
                            $lineIssueTail = $issueDigits !== ''
                                ? str_pad(substr($issueDigits, -3), 3, '0', STR_PAD_LEFT)
                                : '';
                            if (
                                $lineIssueTail === $issueTail
                                && mb_strpos($lineText, '待开奖', 0, 'UTF-8') !== false
                                && preg_match('/--/u', $lineText)
                            ) {
                                $hasCurrentIssuePlaceholder = true;
                                break;
                            }
                        }
                    }
                    if (
                        $postId <= 0
                        || (
                            $saleMaterialLockFallbackOnly
                            && (!$hasCurrentIssueContent || (!$hasWaitingMaterial && !$hasCurrentIssuePlaceholder))
                            && !($shouldRunPostUpdate && !$hasCurrentIssueContent)
                        )
                        || ($hasCurrentIssueContent && !$hasWaitingMaterial && !$hasCurrentIssuePlaceholder)
                    ) {
                        continue;
                    }

                    $templateLabel = trim((string) ($saleRow['title_middle_text'] ?? ''));
                    $templateLabel = preg_replace('/^[【〖《｛〔『]\s*/u', '', $templateLabel);
                    $templateLabel = preg_replace('/\s*[】〗》｝〕』]$/u', '', (string) $templateLabel);
                    if ($templateLabel === '') {
                        foreach (preg_split('/\R/u', $existingContent) as $contentRow) {
                            $contentRow = trim((string) $contentRow);
                            if ($contentRow === '') {
                                continue;
                            }
                            if (preg_match('/^\d{1,6}[^:：]*[:：]\s*\S+\s{2,}(.+?)\s{2,}.+?\s{2,}开[:：]/u', $contentRow, $matches)) {
                                $templateLabel = trim((string) ($matches[1] ?? ''));
                                break;
                            }
                        }
                    }
                    if ($templateLabel === '') {
                        continue;
                    }

                    $forecastContent = $this->managedPostGeneratorForecastContent(
                        $region,
                        $issueTail,
                        trim((string) ($saleRow['author_nickname'] ?? '')),
                        $templateLabel,
                        array(
                            'record_min' => 1,
                            'record_max' => 1,
                            'rate_min' => $config['preset_record_rate_min'] ?? '',
                            'rate_max' => $config['preset_record_rate_max'] ?? '',
                            'zodiac_min' => $config['preset_zodiac_min'] ?? '',
                            'zodiac_max' => $config['preset_zodiac_max'] ?? '',
                            'selected_zodiacs' => (array) ($config['target_zodiac'] ?? array()),
                            'zodiac_labels' => $zodiacLabels,
                            'selected_numbers' => (array) ($config['target_number'] ?? array()),
                            'selected_waves' => (array) ($config['target_wave'] ?? array()),
                            'wave_labels' => $waveLabels,
                            'selected_heads' => (array) ($config['target_head'] ?? array()),
                            'head_labels' => $headLabels,
                            'selected_tails' => (array) ($config['target_tail'] ?? array()),
                            'tail_labels' => $tailLabels,
                            'previous_result_log' => (string) ($saleRow['recent_result_log'] ?? ''),
                            'prediction_template_content' => $existingContent,
                        )
                    );
                    $currentIssueContent = trim((string) ($forecastContent['content'] ?? ''));
                    if ($currentIssueContent === '') {
                        continue;
                    }

                    if ($hasCurrentIssueContent && ($hasWaitingMaterial || $hasCurrentIssuePlaceholder)) {
                        $replacementLines = preg_split('/\R/u', $currentIssueContent);
                        $replacementLine = is_array($replacementLines) && isset($replacementLines[0])
                            ? trim((string) $replacementLines[0])
                            : $currentIssueContent;
                        if ($replacementLine === '') {
                            continue;
                        }

                        $contentLines = preg_split('/\R/u', $existingContent);
                        if (!is_array($contentLines) || empty($contentLines)) {
                            continue;
                        }

                        $updatedLines = array();
                        $contentChanged = false;
                        $skipWaitingFollow = false;
                        foreach ($contentLines as $contentLine) {
                            $lineText = (string) $contentLine;
                            if ($skipWaitingFollow && in_array(trim($lineText), array($waitingText, $waitingFollowText), true)) {
                                $contentChanged = true;
                                continue;
                            }
                            $skipWaitingFollow = false;
                            if (
                                preg_match('/^\s*(\d{1,6})\s*[^:\x{FF1A}]{0,16}[:\x{FF1A}]/u', $lineText, $issueMatches)
                            ) {
                                $issueDigits = preg_replace('/\D+/', '', (string) ($issueMatches[1] ?? ''));
                                $lineIssueTail = $issueDigits !== ''
                                    ? str_pad(substr($issueDigits, -3), 3, '0', STR_PAD_LEFT)
                                    : '';
                                $lineHasWaitingMaterial = mb_strpos($lineText, '资料等待更新中', 0, 'UTF-8') !== false;
                                $lineHasPlaceholder = mb_strpos($lineText, '待开奖', 0, 'UTF-8') !== false
                                    && preg_match('/--/u', $lineText);
                                if ($lineIssueTail === $issueTail && ($lineHasWaitingMaterial || $lineHasPlaceholder)) {
                                    $updatedLines[] = $replacementLine;
                                    $skipWaitingFollow = $lineHasWaitingMaterial;
                                    $contentChanged = true;
                                    continue;
                                }
                            }

                            $updatedLines[] = $lineText;
                        }
                        if (!$contentChanged) {
                            continue;
                        }

                        $content = trim(implode("\n", $updatedLines));
                    } elseif (
                        $existingContent === ''
                        || in_array($existingContent, array(
                            $waitingContent,
                            '此资料出售，购买后可查看完整资料',
                            '此内容为出售内容，购买后可查看完整资料。',
                        ), true)
                    ) {
                        $content = $currentIssueContent;
                    } elseif (mb_strpos($existingContent, $waitingContent, 0, 'UTF-8') !== false) {
                        $content = trim(str_replace($waitingContent, $currentIssueContent, $existingContent));
                    } else {
                        $content = $existingContent . "\n" . $currentIssueContent;
                    }

                    $this->db()->execute(
                        'UPDATE posts
                         SET excerpt = :excerpt,
                             preview_content = :preview_content,
                             full_content = :full_content,
                             updated_at = :updated_at
                         WHERE id = :id',
                        array(
                            'excerpt' => truncate_text($content, 60),
                            'preview_content' => truncate_text($content, 80),
                            'full_content' => $content,
                            'updated_at' => $this->now(),
                            'id' => $postId,
                        )
                    );
                    $metaData = array(
                        'manual_material' => $content,
                    );
                    $forecastLog = trim((string) ($forecastContent['recent_result_log'] ?? ''));
                    if ($forecastLog !== '') {
                        $metaData['recent_result_log'] = $this->pushManagedResultLog(
                            (string) ($saleRow['recent_result_log'] ?? ''),
                            $forecastLog
                        );
                    }
                    $this->saveManagedPostMeta($postId, $metaData);
                    $saleMaterialUpdatedIds[] = $postId;
                }
            }

            if ($shouldRunPostUpdate) {
                $alreadyRanForTarget = (string) $this->app->settings()->get($runKey, '') === $targetRunIssueNo;
                $queryParams = array(
                    'region' => $region,
                    'status' => 'published',
                );

                $rows = $this->db()->fetchAll(
                    'SELECT posts.id,
                            posts.region,
                            posts.title,
                            posts.price,
                            posts.full_content,
                            (SELECT COUNT(*) FROM purchases WHERE purchases.post_id = posts.id) AS real_purchase_count,
                            COALESCE(NULLIF(post_meta.author_nickname, \'\'), users.username) AS author_nickname,
                            COALESCE(post_meta.title_middle_text, \'\') AS title_middle_text,
                            COALESCE(post_meta.segment_no, 1) AS segment_no
                     FROM posts
                     INNER JOIN users ON users.id = posts.author_id
                     LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
                     WHERE posts.region = :region
                       AND posts.status = :status
                       AND posts.deleted_at IS NULL
                       AND COALESCE(post_meta.is_hidden, 0) = 0
                       AND COALESCE(post_meta.segment_no, 1) BETWEEN 1 AND 3
                     ORDER BY COALESCE(post_meta.segment_no, 1) ASC,
                              COALESCE(NULLIF(post_meta.segment_sort, 0), posts.id) ASC,
                              posts.id ASC
                     LIMIT 300',
                    $queryParams
                );

                if ($alreadyRanForTarget) {
                    $hasPendingTargetContent = false;
                    foreach ($rows as $row) {
                        if (strpos((string) ($row['full_content'] ?? ''), $issueMarker) === false) {
                            $hasPendingTargetContent = true;
                            break;
                        }
                    }
                    if (!$hasPendingTargetContent) {
                        $rows = array();
                    }
                }

                foreach ($rows as $row) {
                    $postId = (int) ($row['id'] ?? 0);
                    if ($postId <= 0) {
                        continue;
                    }

                    $existingContent = trim((string) ($row['full_content'] ?? ''));
                    if ($existingContent !== '' && strpos($existingContent, $issueMarker) !== false) {
                        continue;
                    }

                    $templateLabel = trim((string) ($row['title_middle_text'] ?? ''));
                    $templateLabel = preg_replace('/^[【〖《｛〔『]\s*/u', '', $templateLabel);
                    $templateLabel = preg_replace('/\s*[】〗》｝〕』]$/u', '', (string) $templateLabel);
                    if ($templateLabel === '') {
                        foreach (preg_split('/\R/u', trim((string) ($row['full_content'] ?? ''))) as $contentRow) {
                            $contentRow = trim((string) $contentRow);
                            if ($contentRow === '') {
                                continue;
                            }
                            if (preg_match('/^\d{1,6}[^:：]*[:：]\s*\S+\s{2,}(.+?)\s{2,}.+?\s{2,}开[:：]/u', $contentRow, $matches)) {
                                $templateLabel = trim((string) ($matches[1] ?? ''));
                                break;
                            }
                        }
                    }
                    if ($templateLabel === '') {
                        continue;
                    }

                    $forecastContent = $this->managedPostGeneratorForecastContent(
                        $region,
                        $issueTail,
                        trim((string) ($row['author_nickname'] ?? '')),
                        $templateLabel,
                        array(
                            'record_min' => 1,
                            'record_max' => 1,
                            'rate_min' => 0,
                            'rate_max' => 0,
                            'zodiac_min' => $config['preset_zodiac_min'] ?? '',
                            'zodiac_max' => $config['preset_zodiac_max'] ?? '',
                            'selected_zodiacs' => (array) ($config['target_zodiac'] ?? array()),
                            'zodiac_labels' => $zodiacLabels,
                            'selected_numbers' => (array) ($config['target_number'] ?? array()),
                            'selected_waves' => (array) ($config['target_wave'] ?? array()),
                            'wave_labels' => $waveLabels,
                            'selected_heads' => (array) ($config['target_head'] ?? array()),
                            'head_labels' => $headLabels,
                            'selected_tails' => (array) ($config['target_tail'] ?? array()),
                            'tail_labels' => $tailLabels,
                            'pending_prediction' => $waitingText,
                            'prediction_template_content' => $existingContent,
                        )
                    );
                    $nextIssueContent = trim((string) ($forecastContent['content'] ?? ''));
                    if ($nextIssueContent === '') {
                        continue;
                    }
                    if ($existingContent !== '' && mb_strpos($existingContent, $waitingContent, 0, 'UTF-8') !== false) {
                        $content = trim(str_replace($waitingContent, $nextIssueContent, $existingContent));
                    } else {
                        $content = $existingContent !== ''
                            ? $existingContent . "\n" . $nextIssueContent
                            : $nextIssueContent;
                    }

                    $this->db()->execute(
                        'UPDATE posts
                         SET excerpt = :excerpt,
                             preview_content = :preview_content,
                             full_content = :full_content,
                             updated_at = :updated_at
                         WHERE id = :id',
                        array(
                            'excerpt' => truncate_text($content, 60),
                            'preview_content' => truncate_text($content, 80),
                            'full_content' => $content,
                            'updated_at' => $this->now(),
                            'id' => $postId,
                        )
                    );
                    $this->saveManagedPostMeta($postId, array(
                        'manual_material' => $content,
                    ));
                    $updatedIds[] = $postId;
                }

                $this->app->settings()->setMany('post_generator', array(
                    $runKey => $targetRunIssueNo,
                ));

                if ($targetIssueNo !== '' && $targetOpenAt !== '') {
                    $targetIssue = $this->db()->fetch(
                        'SELECT * FROM lottery_issues WHERE region = :region AND issue_no = :issue_no LIMIT 1',
                        array(
                            'region' => $region,
                            'issue_no' => $targetIssueNo,
                        )
                    );
                    $this->saveManagedIssue(
                        array(
                            'id' => is_array($targetIssue) ? (int) ($targetIssue['id'] ?? 0) : 0,
                            'region' => $region,
                            'issue_no' => $targetIssueNo,
                            'planned_open_at' => $targetOpenAt,
                            'actual_open_at' => '',
                            'status' => 'pending',
                            'is_current' => 1,
                            'remark' => 'post generator schedule promoted current issue',
                        ),
                        $runActor
                    );
                }
            }

            $materialTimeTriggered = $materialTimeValue !== ''
                && $nowText >= $materialUpdateScheduleDate . ' ' . $materialTimeValue . ':00';
            $shouldRunMaterialUpdate = $materialTimeTriggered || $lockFallbackMaterialUpdate;
            if ($shouldRunMaterialUpdate) {
                $materialCutoffAt = $lockFallbackMaterialUpdate
                    ? $nowText
                    : $materialUpdateScheduleDate . ' ' . $materialTimeValue . ':00';
                $materialRows = $this->db()->fetchAll(
                    'SELECT posts.id,
                            posts.price,
                            posts.full_content,
                            (SELECT COUNT(*) FROM purchases WHERE purchases.post_id = posts.id) AS real_purchase_count,
                            COALESCE(NULLIF(post_meta.author_nickname, \'\'), users.username) AS author_nickname,
                            COALESCE(post_meta.title_middle_text, \'\') AS title_middle_text,
                            COALESCE(post_meta.recent_result_log, \'\') AS recent_result_log
                     FROM posts
                     INNER JOIN users ON users.id = posts.author_id
                     LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
                     WHERE posts.region = :region
                       AND posts.status = :status
                       AND posts.deleted_at IS NULL
                       AND COALESCE(post_meta.is_hidden, 0) = 0
                       AND COALESCE(post_meta.segment_no, 1) BETWEEN 1 AND 3
                       AND (:ignore_material_cutoff = 1 OR posts.updated_at <= :material_cutoff_at)
                       AND posts.price <= 0
                       AND posts.full_content LIKE :pending_needle
                      ORDER BY posts.id ASC
                      LIMIT 300',
                    array(
                        'region' => $region,
                        'status' => 'published',
                        'material_cutoff_at' => $materialCutoffAt,
                        'ignore_material_cutoff' => $lockFallbackMaterialUpdate ? 1 : 0,
                        'pending_needle' => '%' . $issueMarker . '%' . $waitingText . '%',
                    )
                );
                $lineIssuePattern = '/^\s*(\d{1,6})\s*[^:\x{FF1A}]{0,16}[:\x{FF1A}]/u';
                foreach ($materialRows as $materialRow) {
                    $postId = (int) ($materialRow['id'] ?? 0);
                    $existingContent = trim((string) ($materialRow['full_content'] ?? ''));
                    if ($postId <= 0 || $existingContent === '') {
                        continue;
                    }
                    $templateLabel = trim((string) ($materialRow['title_middle_text'] ?? ''));
                    $templateLabel = preg_replace('/^[【〖《｛〔『]\s*/u', '', $templateLabel);
                    $templateLabel = preg_replace('/\s*[】〗》｝〕』]$/u', '', (string) $templateLabel);
                    if ($templateLabel === '') {
                        foreach (preg_split('/\R/u', $existingContent) as $contentRow) {
                            $contentRow = trim((string) $contentRow);
                            if ($contentRow === '') {
                                continue;
                            }
                            if (preg_match('/^\d{1,6}[^:：]*[:：]\s*\S+\s{2,}(.+?)\s{2,}.+?\s{2,}开[:：]/u', $contentRow, $matches)) {
                                $templateLabel = trim((string) ($matches[1] ?? ''));
                                break;
                            }
                        }
                    }
                    if ($templateLabel === '') {
                        continue;
                    }

                    $forecastContent = $this->managedPostGeneratorForecastContent(
                        $region,
                        $issueTail,
                        trim((string) ($materialRow['author_nickname'] ?? '')),
                        $templateLabel,
                        array(
                            'record_min' => 1,
                            'record_max' => 1,
                            'rate_min' => $config['preset_record_rate_min'] ?? '',
                            'rate_max' => $config['preset_record_rate_max'] ?? '',
                            'zodiac_min' => $config['preset_zodiac_min'] ?? '',
                            'zodiac_max' => $config['preset_zodiac_max'] ?? '',
                            'selected_zodiacs' => (array) ($config['target_zodiac'] ?? array()),
                            'zodiac_labels' => $zodiacLabels,
                            'selected_numbers' => (array) ($config['target_number'] ?? array()),
                            'selected_waves' => (array) ($config['target_wave'] ?? array()),
                            'wave_labels' => $waveLabels,
                            'selected_heads' => (array) ($config['target_head'] ?? array()),
                            'head_labels' => $headLabels,
                            'selected_tails' => (array) ($config['target_tail'] ?? array()),
                            'tail_labels' => $tailLabels,
                            'previous_result_log' => (string) ($materialRow['recent_result_log'] ?? ''),
                            'prediction_template_content' => $existingContent,
                        )
                    );
                    $replacementText = trim((string) ($forecastContent['content'] ?? ''));
                    $replacementLines = preg_split('/\R/u', $replacementText);
                    $replacementLine = is_array($replacementLines) && isset($replacementLines[0])
                        ? trim((string) $replacementLines[0])
                        : $replacementText;
                    if ($replacementLine === '') {
                        continue;
                    }

                    $contentLines = preg_split('/\R/u', $existingContent);
                    if (!is_array($contentLines) || empty($contentLines)) {
                        continue;
                    }

                    $updatedLines = array();
                    $contentChanged = false;
                    $skipWaitingFollow = false;
                    foreach ($contentLines as $contentLine) {
                        $lineText = (string) $contentLine;
                        if ($skipWaitingFollow && in_array(trim($lineText), array($waitingText, $waitingFollowText), true)) {
                            $contentChanged = true;
                            continue;
                        }
                        $skipWaitingFollow = false;
                        if (
                            preg_match($lineIssuePattern, $lineText, $issueMatches)
                            && mb_strpos($lineText, $waitingText, 0, 'UTF-8') !== false
                        ) {
                            $issueDigits = preg_replace('/\D+/', '', (string) ($issueMatches[1] ?? ''));
                            $lineIssueTail = $issueDigits !== ''
                                ? str_pad(substr($issueDigits, -3), 3, '0', STR_PAD_LEFT)
                                : '';
                            if ($lineIssueTail === $issueTail) {
                                $updatedLines[] = $replacementLine;
                                $skipWaitingFollow = true;
                                $contentChanged = true;
                                continue;
                            }
                        }

                        $updatedLines[] = $lineText;
                    }
                    if (!$contentChanged) {
                        continue;
                    }

                    $content = trim(implode("\n", $updatedLines));
                    if (mb_strpos($content, $waitingContent, 0, 'UTF-8') !== false) {
                        $content = trim(str_replace($waitingContent, '', $content));
                        $content = trim((string) preg_replace('/\R{2,}/u', "\n", $content));
                    }
                    $this->db()->execute(
                        'UPDATE posts
                         SET excerpt = :excerpt,
                             preview_content = :preview_content,
                             full_content = :full_content,
                             updated_at = :updated_at
                         WHERE id = :id',
                        array(
                            'excerpt' => truncate_text($content, 60),
                            'preview_content' => truncate_text($content, 80),
                            'full_content' => $content,
                            'updated_at' => $this->now(),
                            'id' => $postId,
                        )
                    );
                    $metaData = array(
                        'manual_material' => $content,
                    );
                    $forecastLog = trim((string) ($forecastContent['recent_result_log'] ?? ''));
                    if ($forecastLog !== '') {
                        $metaData['recent_result_log'] = $this->pushManagedResultLog(
                            (string) ($materialRow['recent_result_log'] ?? ''),
                            $forecastLog
                        );
                    }
                    $this->saveManagedPostMeta($postId, $metaData);
                    $materialUpdatedIds[] = $postId;
                }

                if ($materialUpdatedIds !== array()) {
                    $this->app->settings()->setMany('post_generator', array(
                        $materialRunKey => $targetRunIssueNo,
                    ));
                }
            }

            if ($saleMaterialUpdatedIds !== array()) {
                $this->app->settings()->setMany('post_generator', array(
                    $saleMaterialRunKey => $targetRunIssueNo,
                ));
            }

            $results[$region] = array(
                'count' => count($updatedIds),
                'ids' => $updatedIds,
                'region' => $region,
                'auto_reply_count' => $autoReplyInsertedCount,
                'sale_material_count' => count($saleMaterialUpdatedIds),
                'sale_material_ids' => $saleMaterialUpdatedIds,
                'after_draw_backfill' => $afterDrawBackfillResult,
            );
        }

        return $results;
    }

    public function runManagedPostGeneratorAfterDraw($region, array $draw, array $actor = array())
    {
        $region = $this->normalizeManagedPostGeneratorRegion($region);
        $config = $this->managedPostGeneratorConfig($region);
        $issueNo = preg_replace('/\D+/', '', (string) ($draw['issue_no'] ?? ''));
        if ($issueNo === '') {
            return array('deleted_count' => 0, 'generated_count' => 0, 'region' => $region);
        }

        $latestDraw = $this->db()->fetch(
            'SELECT issue_no
             FROM lottery_draws
             WHERE region = :region
             ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
             LIMIT 1',
            array('region' => $region)
        );
        $latestIssueNo = is_array($latestDraw) ? preg_replace('/\D+/', '', (string) ($latestDraw['issue_no'] ?? '')) : '';
        if ($latestIssueNo !== '' && $latestIssueNo !== $issueNo) {
            return array('deleted_count' => 0, 'generated_count' => 0, 'region' => $region);
        }

        $drawIssueTail = str_pad(substr($issueNo, -3), 3, '0', STR_PAD_LEFT);
        $pendingText = html_entity_decode('&#24453;&#24320;&#22870;', ENT_QUOTES, 'UTF-8');
        $hitText = html_entity_decode('&#20013;', ENT_QUOTES, 'UTF-8');
        $killHitText = html_entity_decode('&#20934;', ENT_QUOTES, 'UTF-8');
        $waitingContent = "资料等待更新中··· ···\n关注本站，精彩无限，中奖根本停不下来······";
        $zodiacLabels = array();
        foreach ((array) ($config['zodiac_options'] ?? array()) as $option) {
            $zodiacLabels[(string) $option['value']] = (string) $option['label'];
        }
        $waveLabels = array();
        foreach ((array) ($config['wave_options'] ?? array()) as $option) {
            $waveLabels[(string) $option['value']] = (string) $option['label'];
        }
        $headLabels = array();
        foreach ((array) ($config['head_options'] ?? array()) as $option) {
            $headLabels[(string) $option['value']] = (string) $option['label'];
        }
        $tailLabels = array();
        foreach ((array) ($config['tail_options'] ?? array()) as $option) {
            $tailLabels[(string) $option['value']] = (string) $option['label'];
        }
        $lineIssuePattern = '/^\s*(\d{1,6})\s*[^:\x{FF1A}]{0,12}[:\x{FF1A}]/u';
        $drawInfo = $this->managedPostGeneratorDrawInfo($region, $drawIssueTail);
        $openResultText = !empty($drawInfo['has_draw'])
            ? trim((string) ($drawInfo['open_number_text'] ?? '') . (string) ($drawInfo['open_zodiac'] ?? ''))
            : '';
        if ($openResultText !== '') {
            $freeRows = $this->db()->fetchAll(
                'SELECT posts.id,
                        posts.full_content,
                        COALESCE(NULLIF(post_meta.author_nickname, \'\'), users.username) AS author_nickname,
                        COALESCE(post_meta.title_middle_text, \'\') AS title_middle_text,
                        COALESCE(post_meta.recent_result_log, \'\') AS recent_result_log
                 FROM posts
                 INNER JOIN users ON users.id = posts.author_id
                 LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
                 WHERE posts.region = :region
                   AND posts.status = :status
                   AND posts.deleted_at IS NULL
                   AND posts.price <= 0
                   AND COALESCE(post_meta.is_hidden, 0) = 0
                   AND posts.full_content LIKE :pending_needle
                 ORDER BY posts.id ASC
                 LIMIT 300',
                array(
                    'region' => $region,
                    'status' => 'published',
                    'pending_needle' => '%' . (string) ((int) $drawIssueTail) . '期:%' . $pendingText . '%',
                )
            );
            foreach ($freeRows as $freeRow) {
                $postId = (int) ($freeRow['id'] ?? 0);
                $existingContent = trim((string) ($freeRow['full_content'] ?? ''));
                if ($postId <= 0 || $existingContent === '') {
                    continue;
                }

                $contentLines = preg_split('/\R/u', $existingContent);
                if (!is_array($contentLines) || empty($contentLines)) {
                    continue;
                }

                $updatedLines = array();
                $contentChanged = false;
                $resultLogEntry = '';
                foreach ($contentLines as $contentLine) {
                    $lineText = (string) $contentLine;
                    if (
                        preg_match($lineIssuePattern, $lineText, $issueMatches)
                        && mb_strpos($lineText, $pendingText, 0, 'UTF-8') !== false
                    ) {
                        $issueDigits = preg_replace('/\D+/', '', (string) ($issueMatches[1] ?? ''));
                        $lineIssueTail = $issueDigits !== ''
                            ? str_pad(substr($issueDigits, -3), 3, '0', STR_PAD_LEFT)
                            : '';
                        if ($lineIssueTail === $drawIssueTail) {
                            $updatedLine = '';
                            $templateLabel = '';
                            if (mb_strpos($lineText, '资料等待更新中', 0, 'UTF-8') !== false) {
                                $templateLabel = trim((string) ($freeRow['title_middle_text'] ?? ''));
                                $templateLabel = preg_replace('/^[\x{300A}\x{300C}\x{3010}\x{3014}\x{3016}\x{3018}\x{301A}\x{301D}\x{FE17}\x{FF08}\x{FF3B}\x{FF5B}\[\(\{【〔〖《「『（［｛]\s*/u', '', $templateLabel);
                                $templateLabel = preg_replace('/\s*[\x{300B}\x{300D}\x{3011}\x{3015}\x{3017}\x{3019}\x{301B}\x{301E}\x{FE18}\x{FF09}\x{FF3D}\x{FF5D}\]\)\}】〕〗》」』）］｝]$/u', '', (string) $templateLabel);
                                if ($templateLabel === '') {
                                    $targetLineParts = preg_split('/\s{2,}/u', trim($lineText), 4);
                                    if (is_array($targetLineParts) && isset($targetLineParts[1])) {
                                        $templateLabel = trim((string) $targetLineParts[1]);
                                    }
                                }
                                if ($templateLabel !== '') {
                                    $forecastContent = $this->managedPostGeneratorForecastContent(
                                        $region,
                                        $drawIssueTail,
                                        trim((string) ($freeRow['author_nickname'] ?? '')),
                                        $templateLabel,
                                        array(
                                            'record_min' => 1,
                                            'record_max' => 1,
                                            'rate_min' => $config['preset_record_rate_min'] ?? '',
                                            'rate_max' => $config['preset_record_rate_max'] ?? '',
                                            'zodiac_min' => $config['preset_zodiac_min'] ?? '',
                                            'zodiac_max' => $config['preset_zodiac_max'] ?? '',
                                            'selected_zodiacs' => (array) ($config['target_zodiac'] ?? array()),
                                            'zodiac_labels' => $zodiacLabels,
                                            'selected_numbers' => (array) ($config['target_number'] ?? array()),
                                            'selected_waves' => (array) ($config['target_wave'] ?? array()),
                                            'wave_labels' => $waveLabels,
                                            'selected_heads' => (array) ($config['target_head'] ?? array()),
                                            'head_labels' => $headLabels,
                                            'selected_tails' => (array) ($config['target_tail'] ?? array()),
                                            'tail_labels' => $tailLabels,
                                            'previous_result_log' => (string) ($freeRow['recent_result_log'] ?? ''),
                                            'prediction_template_content' => $existingContent,
                                        )
                                    );
                                    $replacementText = trim((string) ($forecastContent['content'] ?? ''));
                                    $replacementLines = preg_split('/\R/u', $replacementText);
                                    if (is_array($replacementLines) && !empty($replacementLines)) {
                                        $updatedLine = trim((string) $replacementLines[0]);
                                    }
                                    $forecastLog = trim((string) ($forecastContent['recent_result_log'] ?? ''));
                                    if ($forecastLog !== '') {
                                        $resultLogEntry = $forecastLog;
                                    }
                                }
                            }
                            if ($updatedLine === '') {
                                $updatedLine = preg_replace(
                                    '/(开[:：]\s*)' . preg_quote($pendingText, '/') . '/u',
                                    '${1}' . $openResultText,
                                    $lineText,
                                    1
                                );
                            }
                            $lineStats = $this->managedForecastRecordStats($region, (string) $updatedLine, '', true, false);
                            if (
                                is_array($lineStats)
                                && (int) ($lineStats['total'] ?? 0) > 0
                                && !preg_match('/(准|中|赢|發|发|错)\s*$/u', (string) $updatedLine)
                            ) {
                                $statusText = (int) ($lineStats['hit'] ?? 0) > 0 ? '准' : '错';
                                if (
                                    $statusText === '错'
                                    && empty($config['is_fake_after_open'])
                                    && $this->managedPostGeneratorLatestResultIsWrong((string) ($freeRow['recent_result_log'] ?? ''))
                                ) {
                                    $statusText = '准';
                                }
                                $updatedLine = rtrim((string) $updatedLine) . ' ' . $statusText;
                                $resultLogHitText = $hitText;
                                if ($templateLabel !== '') {
                                    $resultLogTemplateLabel = $this->managedNormalizeForecastTypeText($templateLabel);
                                    if (mb_strpos($resultLogTemplateLabel, '绝杀', 0, 'UTF-8') !== false) {
                                        $resultLogHitText = $killHitText;
                                    }
                                }
                                $resultLogEntry = $drawIssueTail . ($statusText === '准' ? $resultLogHitText : '错');
                            }
                            if ((string) $updatedLine !== $lineText) {
                                $lineText = (string) $updatedLine;
                                $contentChanged = true;
                            }
                        }
                    }
                    $updatedLines[] = $lineText;
                }

                if (!$contentChanged) {
                    continue;
                }

                $content = trim(implode("\n", $updatedLines));
                if (mb_strpos($content, $waitingContent, 0, 'UTF-8') !== false) {
                    $content = trim(str_replace($waitingContent, '', $content));
                    $content = trim((string) preg_replace('/\R{2,}/u', "\n", $content));
                }
                $this->db()->execute(
                    'UPDATE posts
                     SET excerpt = :excerpt,
                         preview_content = :preview_content,
                         full_content = :full_content,
                         updated_at = :updated_at
                     WHERE id = :id',
                    array(
                        'excerpt' => truncate_text($content, 60),
                        'preview_content' => truncate_text($content, 80),
                        'full_content' => $content,
                        'updated_at' => $this->now(),
                        'id' => $postId,
                    )
                );
                $metaData = array(
                    'manual_material' => $content,
                );
                if ($resultLogEntry !== '') {
                    $metaData['recent_result_log'] = $this->pushManagedResultLog(
                        (string) ($freeRow['recent_result_log'] ?? ''),
                        $resultLogEntry
                    );
                }
                $this->saveManagedPostMeta($postId, $metaData);
            }
        }
        $saleRows = $this->db()->fetchAll(
            'SELECT posts.id,
                    posts.full_content,
                    COALESCE(NULLIF(post_meta.author_nickname, \'\'), users.username) AS author_nickname,
                    COALESCE(post_meta.title_middle_text, \'\') AS title_middle_text,
                    COALESCE(post_meta.recent_result_log, \'\') AS recent_result_log
             FROM posts
             INNER JOIN users ON users.id = posts.author_id
             LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
             WHERE posts.region = :region
               AND posts.status = :status
               AND posts.deleted_at IS NULL
               AND posts.price > 0
               AND COALESCE(post_meta.is_hidden, 0) = 0
             ORDER BY posts.id ASC
             LIMIT 300',
            array(
                'region' => $region,
                'status' => 'published',
            )
        );
        foreach ($saleRows as $saleRow) {
            $postId = (int) ($saleRow['id'] ?? 0);
            $existingContent = trim((string) ($saleRow['full_content'] ?? ''));
            if ($postId <= 0 || $existingContent === '') {
                continue;
            }

            $contentLines = preg_split('/\R/u', $existingContent);
            if (!is_array($contentLines) || empty($contentLines)) {
                continue;
            }

            $targetBlockLines = array();
            $activeIssueTail = '';
            foreach ($contentLines as $contentLine) {
                $lineText = (string) $contentLine;
                if (preg_match($lineIssuePattern, $lineText, $issueMatches)) {
                    if ($activeIssueTail === $drawIssueTail && !empty($targetBlockLines)) {
                        break;
                    }

                    $issueDigits = preg_replace('/\D+/', '', (string) ($issueMatches[1] ?? ''));
                    $activeIssueTail = $issueDigits !== ''
                        ? str_pad(substr($issueDigits, -3), 3, '0', STR_PAD_LEFT)
                        : '';
                    $targetBlockLines = $activeIssueTail === $drawIssueTail ? array($lineText) : array();
                    continue;
                }

                if ($activeIssueTail === $drawIssueTail) {
                    $targetBlockLines[] = $lineText;
                }
            }

            $targetBlockText = trim(implode("\n", $targetBlockLines));
            if ($targetBlockText === '' || mb_strpos($targetBlockText, $pendingText, 0, 'UTF-8') === false) {
                continue;
            }

            $templateLabel = trim((string) ($saleRow['title_middle_text'] ?? ''));
            $templateLabel = preg_replace('/^[\x{300A}\x{300C}\x{3010}\x{3014}\x{3016}\x{3018}\x{301A}\x{301D}\x{FE17}\x{FF08}\x{FF3B}\x{FF5B}\[\(\{【〔〖《「『（［｛]\s*/u', '', $templateLabel);
            $templateLabel = preg_replace('/\s*[\x{300B}\x{300D}\x{3011}\x{3015}\x{3017}\x{3019}\x{301B}\x{301E}\x{FE18}\x{FF09}\x{FF3D}\x{FF5D}\]\)\}】〕〗》」』）］｝]$/u', '', (string) $templateLabel);
            if ($templateLabel === '') {
                $targetLineParts = preg_split('/\s{2,}/u', trim((string) ($targetBlockLines[0] ?? '')), 4);
                if (is_array($targetLineParts) && isset($targetLineParts[1])) {
                    $templateLabel = trim((string) $targetLineParts[1]);
                }
            }
            if ($templateLabel === '') {
                continue;
            }

            $forecastContent = $this->managedPostGeneratorForecastContent(
                $region,
                $drawIssueTail,
                trim((string) ($saleRow['author_nickname'] ?? '')),
                $templateLabel,
                array(
                    'record_min' => 1,
                    'record_max' => 1,
                    'rate_min' => 100,
                    'rate_max' => 100,
                    'zodiac_min' => $config['preset_zodiac_min'] ?? '',
                    'zodiac_max' => $config['preset_zodiac_max'] ?? '',
                    'selected_zodiacs' => (array) ($config['target_zodiac'] ?? array()),
                    'zodiac_labels' => $zodiacLabels,
                    'selected_numbers' => (array) ($config['target_number'] ?? array()),
                    'selected_waves' => (array) ($config['target_wave'] ?? array()),
                    'wave_labels' => $waveLabels,
                    'selected_heads' => (array) ($config['target_head'] ?? array()),
                    'head_labels' => $headLabels,
                    'selected_tails' => (array) ($config['target_tail'] ?? array()),
                    'tail_labels' => $tailLabels,
                    'previous_result_log' => (string) ($saleRow['recent_result_log'] ?? ''),
                    'prediction_template_content' => $existingContent,
                )
            );
            $hitIssueContent = trim((string) ($forecastContent['content'] ?? ''));
            if ($hitIssueContent === '') {
                continue;
            }

            $replacementLines = preg_split('/\R/u', $hitIssueContent);
            if (!is_array($replacementLines) || empty($replacementLines)) {
                $replacementLines = array($hitIssueContent);
            }

            $updatedLines = array();
            $skipTargetBlock = false;
            $replacedTargetBlock = false;
            foreach ($contentLines as $contentLine) {
                $lineText = (string) $contentLine;
                if (preg_match($lineIssuePattern, $lineText, $issueMatches)) {
                    if ($skipTargetBlock && !$replacedTargetBlock) {
                        foreach ($replacementLines as $replacementLine) {
                            $updatedLines[] = (string) $replacementLine;
                        }
                        $replacedTargetBlock = true;
                    }

                    $issueDigits = preg_replace('/\D+/', '', (string) ($issueMatches[1] ?? ''));
                    $lineIssueTail = $issueDigits !== ''
                        ? str_pad(substr($issueDigits, -3), 3, '0', STR_PAD_LEFT)
                        : '';
                    if ($lineIssueTail === $drawIssueTail) {
                        $skipTargetBlock = true;
                        continue;
                    }

                    $skipTargetBlock = false;
                    $updatedLines[] = $lineText;
                    continue;
                }

                if ($skipTargetBlock) {
                    continue;
                }

                $updatedLines[] = $lineText;
            }
            if ($skipTargetBlock && !$replacedTargetBlock) {
                foreach ($replacementLines as $replacementLine) {
                    $updatedLines[] = (string) $replacementLine;
                }
            }

            $content = trim(implode("\n", $updatedLines));
            if (mb_strpos($content, $waitingContent, 0, 'UTF-8') !== false) {
                $content = trim(str_replace($waitingContent, '', $content));
                $content = trim((string) preg_replace('/\R{2,}/u', "\n", $content));
            }
            if ($content === '' || $content === $existingContent) {
                continue;
            }

            $resultLogHitText = $hitText;
            $resultLogTemplateLabel = $this->managedNormalizeForecastTypeText($templateLabel);
            if (mb_strpos($resultLogTemplateLabel, '绝杀', 0, 'UTF-8') !== false) {
                $resultLogHitText = $killHitText;
            }
            $this->db()->execute(
                'UPDATE posts
                 SET excerpt = :excerpt,
                     preview_content = :preview_content,
                     full_content = :full_content,
                     updated_at = :updated_at
                 WHERE id = :id',
                array(
                    'excerpt' => truncate_text($content, 60),
                    'preview_content' => truncate_text($content, 80),
                    'full_content' => $content,
                    'updated_at' => $this->now(),
                    'id' => $postId,
                )
            );
            $this->saveManagedPostMeta($postId, array(
                'manual_material' => $content,
                'recent_result_log' => $this->pushManagedResultLog(
                    (string) ($saleRow['recent_result_log'] ?? ''),
                    $drawIssueTail . $resultLogHitText
                ),
            ));
        }

        $refundResult = $this->refundManagedPostWrongStreakPurchases($region, $drawIssueTail, $config);

        if (empty($config['is_fake_after_open'])) {
            return array(
                'deleted_count' => 0,
                'generated_count' => 0,
                'region' => $region,
                'refund_count' => (int) ($refundResult['count'] ?? 0),
                'refund_score' => (int) ($refundResult['score'] ?? 0),
            );
        }

        $manageTemplates = isset($config['manage_templates']) && is_array($config['manage_templates'])
            ? array_values($config['manage_templates'])
            : array();
        if ($manageTemplates === array()) {
            return array(
                'deleted_count' => 0,
                'generated_count' => 0,
                'region' => $region,
                'refund_count' => (int) ($refundResult['count'] ?? 0),
                'refund_score' => (int) ($refundResult['score'] ?? 0),
            );
        }

        $runKey = 'post_generator.after_draw_last_run.' . $region;
        $hasRunForIssue = (string) $this->app->settings()->get($runKey, '') === $issueNo;

        $runActor = $this->managedPostGeneratorRunActor($actor);
        if ((int) ($runActor['id'] ?? 0) <= 0) {
            return array(
                'deleted_count' => 0,
                'generated_count' => 0,
                'region' => $region,
                'refund_count' => (int) ($refundResult['count'] ?? 0),
                'refund_score' => (int) ($refundResult['score'] ?? 0),
            );
        }

        $rows = $this->db()->fetchAll(
            'SELECT posts.id,
                    posts.full_content,
                    COALESCE(post_meta.recent_result_log, \'\') AS recent_result_log
             FROM posts
             LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
             WHERE posts.region = :region
               AND posts.status = :status
               AND posts.deleted_at IS NULL
               AND COALESCE(post_meta.is_hidden, 0) = 0
               AND COALESCE(post_meta.segment_no, 1) BETWEEN 1 AND 3',
            array(
                'region' => $region,
                'status' => 'published',
            )
        );

        $deleteWrongStreak = max(2, min(99, (int) ($config['after_draw_delete_wrong_streak'] ?? 2)));
        $deleteIds = array();
        foreach ($rows as $row) {
            if ($this->managedPostGeneratorHasTwoWrongRecordsForPost(
                $region,
                (string) ($row['recent_result_log'] ?? ''),
                (string) ($row['full_content'] ?? ''),
                $deleteWrongStreak
            )) {
                $deleteIds[] = (int) ($row['id'] ?? 0);
            }
        }
        $deleteIds = array_values(array_unique(array_filter($deleteIds)));
        $overflowDeleteIds = $this->managedPostGeneratorOverflowDeleteIds($region, $config);
        $allDeleteIds = array_values(array_unique(array_merge($deleteIds, $overflowDeleteIds)));
        if ($hasRunForIssue && $allDeleteIds === array()) {
            return array(
                'deleted_count' => 0,
                'overflow_deleted_count' => 0,
                'generated_count' => 0,
                'region' => $region,
                'refund_count' => (int) ($refundResult['count'] ?? 0),
                'refund_score' => (int) ($refundResult['score'] ?? 0),
            );
        }
        if ($allDeleteIds !== array()) {
            $this->bulkManagedPosts($allDeleteIds, 'delete', '', $runActor);
        }

        $payload = $config;
        $payload['region'] = $region;
        $payload['generator_type'] = $region;
        $payload['templates'] = $manageTemplates;
        $payload['current_issue_tail'] = (string) ($config['current_issue_tail'] ?? $this->nextManagedIssueTail($region));
        $payload['generator_run_mode'] = 'maintain';
        $payload['fill_segment_range_after_delete'] = $allDeleteIds !== array() ? '1' : '';
        $generated = $this->generateManagedForumPosts($payload, $runActor);
        $this->app->settings()->setMany('post_generator', array(
            $runKey => $issueNo,
        ));

        return array(
            'deleted_count' => count($allDeleteIds),
            'wrong_streak_deleted_count' => count($deleteIds),
            'overflow_deleted_count' => count($overflowDeleteIds),
            'generated_count' => (int) ($generated['count'] ?? 0),
            'region' => $region,
            'refund_count' => (int) ($refundResult['count'] ?? 0),
            'refund_score' => (int) ($refundResult['score'] ?? 0),
        );
    }

    protected function refundManagedPostWrongStreakPurchases($region, $drawIssueTail, array $config)
    {
        $region = $this->normalizeManagedPostGeneratorRegion($region);
        $drawIssueTail = str_pad(substr(preg_replace('/\D+/', '', (string) $drawIssueTail), -3), 3, '0', STR_PAD_LEFT);
        $streak = max(2, min(99, (int) ($config['wrong_refund_streak'] ?? 2)));
        $percent = max(0, min(999, (int) ($config['wrong_refund_percent'] ?? 100)));
        if ($drawIssueTail === '' || $percent <= 0) {
            return array('count' => 0, 'score' => 0);
        }

        $window = $this->managedPostWrongRefundPurchaseWindow($region, $drawIssueTail);
        if ($window === array()) {
            return array('count' => 0, 'score' => 0);
        }

        $rows = $this->db()->fetchAll(
            'SELECT posts.id AS post_id,
                    posts.title,
                    posts.full_content AS post_content,
                    posts.price AS post_price,
                    purchases.id AS purchase_id,
                    purchases.user_id,
                    purchases.price AS purchase_price,
                    purchases.created_at AS purchase_created_at,
                    users.username,
                    users.score AS user_score,
                    COALESCE(post_meta.recent_result_log, \'\') AS recent_result_log
             FROM posts
             INNER JOIN purchases ON purchases.post_id = posts.id
             INNER JOIN users ON users.id = purchases.user_id
             LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
             WHERE posts.region = :region
               AND posts.status = :status
               AND posts.deleted_at IS NULL
               AND posts.price > 0
               AND purchases.created_at >= :window_start
               AND purchases.created_at < :window_end
               AND COALESCE(post_meta.is_hidden, 0) = 0
             ORDER BY posts.id ASC, purchases.id ASC
             LIMIT 500',
            array(
                'region' => $region,
                'status' => 'published',
                'window_start' => (string) ($window['start'] ?? ''),
                'window_end' => (string) ($window['end'] ?? ''),
            )
        );

        $refundCount = 0;
        $refundScore = 0;
        foreach ($rows as $row) {
            $postId = (int) ($row['post_id'] ?? 0);
            $userId = (int) ($row['user_id'] ?? 0);
            $purchaseId = (int) ($row['purchase_id'] ?? 0);
            if ($postId <= 0 || $userId <= 0 || $purchaseId <= 0) {
                continue;
            }

            $lastRefundIssueTail = $this->managedPostWrongRefundLastIssueTail($postId, $userId);
            $tailIssue = $this->managedPostGeneratorWrongStreakTailIssue(
                (string) ($row['recent_result_log'] ?? ''),
                $streak,
                $lastRefundIssueTail
            );
            if ($tailIssue === '') {
                $tailIssue = $this->managedPostGeneratorWrongStreakTailIssueFromContent(
                    $region,
                    (string) ($row['post_content'] ?? ''),
                    $streak,
                    $lastRefundIssueTail
                );
            }
            if ($tailIssue === '' || $tailIssue !== $drawIssueTail) {
                continue;
            }

            $purchasePrice = max(0, (int) ($row['purchase_price'] ?? 0));
            $refundAmount = (int) floor($purchasePrice * $percent / 100);
            if ($refundAmount <= 0) {
                continue;
            }

            $refundKey = hash('sha256', implode('|', array(
                'wrong_refund',
                $region,
                (string) $drawIssueTail,
                (string) $postId,
                (string) $userId,
                (string) $purchaseId,
                (string) $streak,
                (string) $percent,
            )));
            if ($this->managedPostWrongRefundAlreadyLogged($refundKey)) {
                continue;
            }

            $now = $this->now();
            $this->db()->beginTransaction();
            try {
                $lockedUser = $this->db()->fetch(
                    'SELECT id, username, score FROM users WHERE id = :id FOR UPDATE',
                    array('id' => $userId)
                );
                if (!is_array($lockedUser)) {
                    $this->db()->rollBack();
                    continue;
                }

                if ($this->managedPostWrongRefundAlreadyLogged($refundKey)) {
                    $this->db()->rollBack();
                    continue;
                }

                $newScore = (int) ($lockedUser['score'] ?? 0) + $refundAmount;
                $this->db()->execute(
                    'UPDATE users SET score = :score, updated_at = :updated_at WHERE id = :id',
                    array(
                        'score' => $newScore,
                        'updated_at' => $now,
                        'id' => $userId,
                    )
                );

                $this->app->logs()->system(
                    'post_wrong_refund',
                    '会员购买帖子连错返积分',
                    'info',
                    array(
                        'refund_key' => $refundKey,
                        'region' => $region,
                        'issue_tail' => $drawIssueTail,
                        'post_id' => $postId,
                        'purchase_id' => $purchaseId,
                        'user_id' => $userId,
                        'username' => (string) ($lockedUser['username'] ?? ($row['username'] ?? '')),
                        'purchase_price' => $purchasePrice,
                        'refund_amount' => $refundAmount,
                        'refund_percent' => $percent,
                        'wrong_streak' => $streak,
                        'score_before' => (int) ($lockedUser['score'] ?? 0),
                        'score_after' => $newScore,
                    )
                );
                $this->db()->commit();

                $refundCount++;
                $refundScore += $refundAmount;
                try {
                    $this->notifyMemberWrongRefund(
                        $userId,
                        $region,
                        $drawIssueTail,
                        (string) ($row['title'] ?? ''),
                        $refundAmount,
                        $newScore,
                        $streak,
                        $percent
                    );
                } catch (\Throwable $notifyException) {
                    try {
                        $this->writeManagedExceptionLog($notifyException, 'posts', 'wrong_refund_notify', 'system', 0);
                    } catch (\Throwable $loggingException) {
                    }
                }
            } catch (\Throwable $exception) {
                $this->db()->rollBack();
                try {
                    $this->writeManagedExceptionLog($exception, 'posts', 'wrong_refund', 'system', 0);
                } catch (\Throwable $loggingException) {
                }
            }
        }

        return array('count' => $refundCount, 'score' => $refundScore);
    }

    protected function managedPostWrongRefundPurchaseWindow($region, $drawIssueTail)
    {
        $region = $this->normalizeManagedPostGeneratorRegion($region);
        $drawIssueTail = str_pad(substr(preg_replace('/\D+/', '', (string) $drawIssueTail), -3), 3, '0', STR_PAD_LEFT);
        if ($drawIssueTail === '') {
            return array();
        }

        $currentIssue = $this->db()->fetch(
            'SELECT issue_no, planned_open_at, actual_open_at
             FROM lottery_issues
             WHERE region = :region
               AND RIGHT(issue_no, 3) = :issue_tail
             ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
             LIMIT 1',
            array(
                'region' => $region,
                'issue_tail' => $drawIssueTail,
            )
        );
        if (!is_array($currentIssue)) {
            return array();
        }

        $issueNo = preg_replace('/\D+/', '', (string) ($currentIssue['issue_no'] ?? ''));
        $endAt = trim((string) ($currentIssue['actual_open_at'] ?? ''));
        if ($endAt === '') {
            $endAt = trim((string) ($currentIssue['planned_open_at'] ?? ''));
        }
        if ($issueNo === '' || $endAt === '' || strtotime($endAt) === false) {
            return array();
        }

        $previousIssue = $this->db()->fetch(
            'SELECT planned_open_at, actual_open_at
             FROM lottery_issues
             WHERE region = :region
               AND CAST(issue_no AS UNSIGNED) < :issue_no
             ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
             LIMIT 1',
            array(
                'region' => $region,
                'issue_no' => (int) $issueNo,
            )
        );
        $startAt = '';
        if (is_array($previousIssue)) {
            $startAt = trim((string) ($previousIssue['actual_open_at'] ?? ''));
            if ($startAt === '') {
                $startAt = trim((string) ($previousIssue['planned_open_at'] ?? ''));
            }
        }
        if ($startAt === '' || strtotime($startAt) === false) {
            $startAt = date('Y-m-d H:i:s', strtotime('-1 day', strtotime($endAt)));
        }

        return array(
            'start' => $startAt,
            'end' => $endAt,
        );
    }

    protected function managedPostWrongRefundAlreadyLogged($refundKey)
    {
        if (!$this->tableExists($this->db(), 'system_logs')) {
            return false;
        }

        $row = $this->db()->fetch(
            'SELECT id
             FROM system_logs
             WHERE source_name = :source_name
               AND context_json LIKE :refund_key
             ORDER BY id DESC
             LIMIT 1',
            array(
                'source_name' => 'post_wrong_refund',
                'refund_key' => '%' . (string) $refundKey . '%',
            )
        );

        return is_array($row);
    }

    protected function managedPostWrongRefundLastIssueTail($postId, $userId)
    {
        $postId = (int) $postId;
        $userId = (int) $userId;
        if ($postId <= 0 || $userId <= 0 || !$this->tableExists($this->db(), 'system_logs')) {
            return '';
        }

        $rows = $this->db()->fetchAll(
            'SELECT context_json
             FROM system_logs
             WHERE source_name = :source_name
               AND context_json LIKE :post_needle
               AND context_json LIKE :user_needle
             ORDER BY id DESC
             LIMIT 50',
            array(
                'source_name' => 'post_wrong_refund',
                'post_needle' => '%"post_id":' . $postId . '%',
                'user_needle' => '%"user_id":' . $userId . '%',
            )
        );

        $latestIssue = 0;
        foreach ($rows as $row) {
            $decoded = json_decode((string) ($row['context_json'] ?? ''), true);
            if (!is_array($decoded)) {
                continue;
            }
            if ((int) ($decoded['post_id'] ?? 0) !== $postId || (int) ($decoded['user_id'] ?? 0) !== $userId) {
                continue;
            }

            $issueTail = preg_replace('/\D+/', '', (string) ($decoded['issue_tail'] ?? ''));
            if ($issueTail === '') {
                continue;
            }
            $latestIssue = max($latestIssue, (int) $issueTail);
        }

        return $latestIssue > 0 ? str_pad(substr((string) $latestIssue, -3), 3, '0', STR_PAD_LEFT) : '';
    }

    protected function notifyMemberWrongRefund($userId, $region, $issueTail, $postTitle, $refundAmount, $scoreAfter, $streak, $percent)
    {
        $userId = (int) $userId;
        if ($userId <= 0 || !$this->tableExists($this->db(), 'customer_service_sessions') || !$this->tableExists($this->db(), 'customer_service_messages')) {
            return;
        }

        $now = $this->now();
        $session = $this->db()->fetch(
            'SELECT id FROM customer_service_sessions WHERE user_id = :user_id LIMIT 1',
            array('user_id' => $userId)
        );
        if (!is_array($session)) {
            $sessionId = (int) $this->db()->insertGetId(
                'INSERT INTO customer_service_sessions (session_key, user_id, assigned_agent_id, status, unread_for_member, unread_for_admin, last_message_type, last_message_preview, last_message_at, closed_at, created_at, updated_at)
                 VALUES (:session_key, :user_id, :assigned_agent_id, :status, :unread_for_member, :unread_for_admin, :last_message_type, :last_message_preview, :last_message_at, :closed_at, :created_at, :updated_at)',
                array(
                    'session_key' => 'member-' . $userId,
                    'user_id' => $userId,
                    'assigned_agent_id' => null,
                    'status' => 'waiting',
                    'unread_for_member' => 0,
                    'unread_for_admin' => 0,
                    'last_message_type' => 'text',
                    'last_message_preview' => '',
                    'last_message_at' => $now,
                    'closed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                )
            );
        } else {
            $sessionId = (int) ($session['id'] ?? 0);
        }
        if ($sessionId <= 0) {
            return;
        }

        $regionLabel = $region === 'hongkong' ? '香港' : '澳门';
        $titleText = trim((string) $postTitle);
        if ($titleText !== '' && function_exists('mb_substr')) {
            $titleText = mb_substr($titleText, 0, 60, 'UTF-8');
        }
        $content = $regionLabel . $issueTail . '期购买资料连错' . (int) $streak . '期，已按' . (int) $percent . '%返还' . (int) $refundAmount . '积分，当前积分' . (int) $scoreAfter . '。';
        if ($titleText !== '') {
            $content .= '帖子：' . $titleText;
        }
        $preview = $content;
        if (function_exists('mb_substr')) {
            $preview = mb_substr($preview, 0, 120, 'UTF-8');
        } else {
            $preview = substr($preview, 0, 120);
        }

        $this->db()->execute(
            'INSERT INTO customer_service_messages (session_id, sender_type, sender_user_id, sender_agent_id, message_type, content, attachment_url, attachment_name, attachment_mime, attachment_size, voice_duration, user_deleted_at, agent_deleted_at, created_at)
             VALUES (:session_id, :sender_type, :sender_user_id, :sender_agent_id, :message_type, :content, :attachment_url, :attachment_name, :attachment_mime, :attachment_size, :voice_duration, :user_deleted_at, :agent_deleted_at, :created_at)',
            array(
                'session_id' => $sessionId,
                'sender_type' => 'system',
                'sender_user_id' => null,
                'sender_agent_id' => null,
                'message_type' => 'text',
                'content' => $content,
                'attachment_url' => '',
                'attachment_name' => '',
                'attachment_mime' => '',
                'attachment_size' => 0,
                'voice_duration' => 0,
                'user_deleted_at' => null,
                'agent_deleted_at' => null,
                'created_at' => $now,
            )
        );
        $this->db()->execute(
            'UPDATE customer_service_sessions
             SET status = CASE WHEN status = :closed_status THEN :waiting_status ELSE status END,
                 unread_for_member = unread_for_member + 1,
                 last_message_type = :last_message_type,
                 last_message_preview = :last_message_preview,
                 last_message_at = :last_message_at,
                 closed_at = NULL,
                 updated_at = :updated_at
             WHERE id = :id',
            array(
                'closed_status' => 'closed',
                'waiting_status' => 'waiting',
                'last_message_type' => 'text',
                'last_message_preview' => $preview,
                'last_message_at' => $now,
                'updated_at' => $now,
                'id' => $sessionId,
            )
        );
    }

    protected function managedPostGeneratorRunActor(array $actor)
    {
        if ((int) ($actor['id'] ?? 0) > 0) {
            return $actor;
        }

        $admin = $this->db()->fetch(
            'SELECT id, username
             FROM admin_users
             WHERE deleted_at IS NULL
             ORDER BY is_super DESC, id ASC
             LIMIT 1'
        );
        if (!is_array($admin)) {
            return array('id' => 0, 'username' => '');
        }

        return array(
            'id' => (int) ($admin['id'] ?? 0),
            'username' => (string) ($admin['username'] ?? ''),
        );
    }

    protected function managedPostGeneratorLatestResultRecord($recentResultLog)
    {
        $latestRecord = null;
        foreach (preg_split('/[\s,，]+/u', trim((string) $recentResultLog)) as $item) {
            $item = trim((string) $item);
            if ($item === '' || !preg_match('/(\d{1,6})\s*(准|中|赢|發|发|错|錯)$/u', $item, $matches)) {
                continue;
            }

            $issue = (int) ($matches[1] ?? 0);
            if (is_array($latestRecord) && $issue < (int) ($latestRecord['issue'] ?? 0)) {
                continue;
            }

            $latestRecord = array(
                'issue' => $issue,
                'status' => (string) ($matches[2] ?? ''),
            );
        }

        return $latestRecord;
    }

    protected function managedPostGeneratorLatestResultIsWrong($recentResultLog)
    {
        $latestRecord = $this->managedPostGeneratorLatestResultRecord($recentResultLog);
        if (!is_array($latestRecord)) {
            return false;
        }

        return in_array((string) ($latestRecord['status'] ?? ''), array('错', '錯'), true);
    }

    protected function managedPostGeneratorHasTwoWrongRecords($recentResultLog, $requiredStreak = 2)
    {
        $requiredStreak = max(2, min(99, (int) $requiredStreak));
        $records = array();
        foreach (preg_split('/[\s,，]+/u', trim((string) $recentResultLog)) as $item) {
            $item = trim((string) $item);
            if ($item === '' || !preg_match('/(\d{1,6})\s*(准|中|错|錯)$/u', $item, $matches)) {
                continue;
            }

            $records[] = array(
                'issue' => (int) ($matches[1] ?? 0),
                'status' => (string) ($matches[2] ?? ''),
            );
        }
        if (count($records) < $requiredStreak) {
            return false;
        }

        usort($records, static function ($left, $right) {
            return (int) ($left['issue'] ?? 0) <=> (int) ($right['issue'] ?? 0);
        });

        foreach (array_slice($records, -1 * $requiredStreak) as $record) {
            if (!in_array((string) ($record['status'] ?? ''), array('错', '錯'), true)) {
                return false;
            }
        }

        return true;
    }

    protected function managedPostGeneratorHasTwoWrongRecordsForPost($region, $recentResultLog, $contentText, $requiredStreak = 2)
    {
        $requiredStreak = max(2, min(99, (int) $requiredStreak));
        if ($this->managedPostGeneratorHasTwoWrongRecords($recentResultLog, $requiredStreak)) {
            return true;
        }

        return $this->managedPostGeneratorWrongStreakTailIssueFromContent($region, $contentText, $requiredStreak) !== '';
    }

    protected function managedPostGeneratorWrongStreakTailIssueFromContent($region, $contentText, $requiredStreak, $afterIssueTail = '')
    {
        $records = $this->managedPostGeneratorResultRecordsFromContent($region, $contentText);

        return $this->managedPostGeneratorWrongStreakTailIssueFromRecords($records, $requiredStreak, $afterIssueTail);
    }

    protected function managedPostGeneratorResultRecordsFromContent($region, $contentText)
    {
        $contentText = trim((string) $contentText);
        if ($contentText === '') {
            return array();
        }

        $stats = $this->managedForecastRecordStats($region, $contentText, '', true, false);
        $records = isset($stats['records']) && is_array($stats['records']) ? $stats['records'] : array();
        $normalizedRecords = array();
        foreach ($records as $record) {
            $issueNo = (int) ($record['issue'] ?? 0);
            $statusText = (string) ($record['status'] ?? '');
            if ($issueNo <= 0 || $statusText === '') {
                continue;
            }
            $normalizedRecords[] = array(
                'issue' => $issueNo,
                'status' => $statusText,
            );
        }

        return $normalizedRecords;
    }

    protected function managedPostGeneratorWrongStreakTailIssueFromRecords(array $records, $requiredStreak, $afterIssueTail = '')
    {
        $afterIssueNo = (int) preg_replace('/\D+/', '', (string) $afterIssueTail);
        $requiredStreak = max(2, min(99, (int) $requiredStreak));
        $filteredRecords = array();
        foreach ($records as $record) {
            $issueNo = (int) ($record['issue'] ?? 0);
            if ($issueNo <= 0 || ($afterIssueNo > 0 && $issueNo <= $afterIssueNo)) {
                continue;
            }
            $filteredRecords[] = array(
                'issue' => $issueNo,
                'status' => (string) ($record['status'] ?? ''),
            );
        }
        if (count($filteredRecords) < $requiredStreak) {
            return '';
        }

        usort($filteredRecords, static function ($left, $right) {
            return (int) ($left['issue'] ?? 0) <=> (int) ($right['issue'] ?? 0);
        });

        $tailRecords = array_slice($filteredRecords, -$requiredStreak);
        foreach ($tailRecords as $record) {
            if (!in_array((string) ($record['status'] ?? ''), array('错', '錯'), true)) {
                return '';
            }
        }

        $tailIssue = (int) ($tailRecords[count($tailRecords) - 1]['issue'] ?? 0);

        return $tailIssue > 0 ? str_pad(substr((string) $tailIssue, -3), 3, '0', STR_PAD_LEFT) : '';
    }

    protected function managedPostGeneratorWrongStreakTailIssue($recentResultLog, $requiredStreak, $afterIssueTail = '')
    {
        $records = array();
        $afterIssueNo = (int) preg_replace('/\D+/', '', (string) $afterIssueTail);
        foreach (preg_split('/[\s,，]+/u', trim((string) $recentResultLog)) as $item) {
            $item = trim((string) $item);
            if ($item === '' || !preg_match('/(\d{1,6})\s*(准|中|错|錯)$/u', $item, $matches)) {
                continue;
            }

            $issueNo = (int) ($matches[1] ?? 0);
            if ($afterIssueNo > 0 && $issueNo <= $afterIssueNo) {
                continue;
            }

            $records[] = array(
                'issue' => $issueNo,
                'status' => (string) ($matches[2] ?? ''),
            );
        }
        $requiredStreak = max(2, min(99, (int) $requiredStreak));
        if (count($records) < $requiredStreak) {
            return '';
        }

        usort($records, static function ($left, $right) {
            return (int) ($left['issue'] ?? 0) <=> (int) ($right['issue'] ?? 0);
        });

        $tailRecords = array_slice($records, -1 * $requiredStreak);
        foreach ($tailRecords as $record) {
            if (!in_array((string) ($record['status'] ?? ''), array('错', '錯'), true)) {
                return '';
            }
        }

        $tailIssue = (int) ($tailRecords[count($tailRecords) - 1]['issue'] ?? 0);

        return $tailIssue > 0 ? str_pad(substr((string) $tailIssue, -3), 3, '0', STR_PAD_LEFT) : '';
    }

    public function bulkManagedPosts(array $ids, $action, $value, array $actor)
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (empty($ids)) {
            throw new RuntimeException('请先选择至少一条帖子。');
        }

        $this->ensureManagedPostMetaReady();
        $postsById = $this->managedPostsByIds($ids);
        if (empty($postsById)) {
            throw new RuntimeException('未找到要处理的帖子。');
        }

        $in = implode(',', array_keys($postsById));
        $now = $this->now();
        $value = trim((string) $value);

        if (in_array((string) $action, array('next_blank', 'next_random', 'next_specified'), true)) {
            $this->assertManagedPostsUnlockedForEdit($postsById, $actor);
        }

        switch ((string) $action) {
            case 'delete':
                $this->db()->execute(
                    'UPDATE posts
                     SET status = :status,
                         is_top_forever = 0,
                         is_top_admin = 0,
                         is_top_normal = 0,
                         deleted_at = :deleted_at,
                         updated_at = :updated_at
                     WHERE id IN (' . $in . ')',
                    array(
                        'status' => 'deleted',
                        'deleted_at' => $now,
                        'updated_at' => $now,
                    )
                );
                $restoredPostIds = $this->app->posts()->restoreDeletedPurchasedPosts(array_keys($postsById));
                $restoredPostIdMap = array_fill_keys(array_map('intval', $restoredPostIds), true);
                foreach ($postsById as $postId => $post) {
                    if (isset($restoredPostIdMap[(int) $postId])) {
                        continue;
                    }

                    $this->saveManagedPostMeta($postId, array(
                        'deleted_issue_text' => $this->nextManagedIssueText((string) ($post['region'] ?? 'macau')),
                    ));
                }
                break;

            case 'restore':
                $this->db()->execute(
                    'UPDATE posts
                     SET status = :status,
                         deleted_at = NULL,
                         updated_at = :updated_at
                     WHERE id IN (' . $in . ')',
                    array(
                        'status' => 'published',
                        'updated_at' => $now,
                    )
                );
                break;

            case 'purge':
                foreach ($postsById as $postId => $post) {
                    if ((string) ($post['status'] ?? '') !== 'deleted') {
                        throw new RuntimeException('仅回收站帖子支持批量彻底删除。');
                    }
                }

                if ($this->tableExists($this->db(), 'post_interactions')) {
                    $this->db()->execute(
                        'DELETE FROM post_interactions WHERE post_id IN (' . $in . ')'
                    );
                }

                if ($this->tableExists($this->db(), 'post_reports')) {
                    $this->db()->execute(
                        'DELETE FROM post_reports WHERE post_id IN (' . $in . ')'
                    );
                }

                if ($this->tableExists($this->db(), 'page_views')) {
                    $this->db()->execute(
                        "DELETE FROM page_views
                         WHERE route_name = 'post_detail'
                           AND path_name LIKE '%id=%'
                           AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(path_name, 'id=', -1), '&', 1) AS UNSIGNED) IN (" . $in . ')'
                    );
                }

                if ($this->tableExists($this->db(), 'audit_records')) {
                    $this->db()->execute(
                        "DELETE FROM audit_records
                         WHERE target_type = 'post'
                           AND target_id IN (" . $in . ')'
                    );
                }

                $this->db()->execute(
                    'DELETE FROM posts WHERE id IN (' . $in . ')'
                );
                break;

            case 'color':
                if (!in_array($value, array('slate', 'red', 'green', 'gold'), true)) {
                    throw new RuntimeException('色签类型无效。');
                }
                $this->db()->execute(
                    'UPDATE posts SET color_tag = :color_tag, updated_at = :updated_at WHERE id IN (' . $in . ')',
                    array(
                        'color_tag' => $value,
                        'updated_at' => $now,
                    )
                );
                break;

            case 'top_forever':
            case 'top_admin':
            case 'top_normal':
                $column = $action === 'top_forever' ? 'is_top_forever' : ($action === 'top_admin' ? 'is_top_admin' : 'is_top_normal');
                $this->db()->execute(
                    'UPDATE posts SET ' . $column . ' = 1, updated_at = :updated_at WHERE id IN (' . $in . ')',
                    array('updated_at' => $now)
                );
                break;

            case 'set_segment_no':
                if (!in_array($value, array('1', '2', '3'), true)) {
                    throw new RuntimeException('高手区参数无效。');
                }
                foreach ($postsById as $postId => $post) {
                    $this->saveManagedPostMeta((int) $postId, array(
                        'segment_no' => (int) $value,
                    ));
                }
                break;

            case 'set_segment_sort':
                if (!in_array($value, array('1', '2', '3', '4', '5'), true)) {
                    throw new RuntimeException('置顶参数无效。');
                }
                foreach ($postsById as $postId => $post) {
                    $this->saveManagedPostMeta((int) $postId, array(
                        'segment_sort' => (int) $value,
                    ));
                }
                break;

            case 'fake_public':
                $this->db()->execute(
                    'UPDATE posts
                     SET status = :status,
                         deleted_at = NULL,
                         updated_at = :updated_at
                     WHERE id IN (' . $in . ')',
                    array(
                        'status' => 'published',
                        'updated_at' => $now,
                    )
                );
                foreach ($postsById as $postId => $post) {
                    $this->saveManagedPostMeta($postId, array(
                        'is_fake' => 1,
                        'is_hidden' => 0,
                    ));
                }
                break;

            case 'mark_result':
                $markLabel = $value !== '' ? $value : '准';
                if (!in_array($markLabel, array('准', '错'), true)) {
                    $markLabel = '准';
                }
                foreach ($postsById as $postId => $post) {
                    $metaMap = $this->listManagedPostMetaByIds(array($postId));
                    $meta = $metaMap[$postId] ?? $this->defaultManagedPostMeta($post);
                    $entry = $this->latestManagedIssueTail((string) ($post['region'] ?? 'macau')) . $markLabel;
                    $this->saveManagedPostMeta($postId, array(
                        'recent_result_log' => $this->pushManagedResultLog((string) ($meta['recent_result_log'] ?? ''), $entry),
                    ));
                }
                break;

            case 'add_buyer':
                $increase = max(1, (int) ($value !== '' ? $value : 1));
                foreach ($postsById as $postId => $post) {
                    $metaMap = $this->listManagedPostMetaByIds(array($postId));
                    $meta = $metaMap[$postId] ?? $this->defaultManagedPostMeta($post);
                    $this->saveManagedPostMeta($postId, array(
                        'fake_buyer_count' => (int) ($meta['fake_buyer_count'] ?? 0) + $increase,
                    ));
                }
                break;

            case 'next_blank':
            case 'next_random':
            case 'next_specified':
                foreach ($postsById as $postId => $post) {
                    $mode = $action === 'next_random' ? 'random' : ($action === 'next_specified' ? 'specified' : 'none');
                    $metaPayload = array('auto_update_mode' => $mode);
                    if ($mode === 'specified' && $value !== '') {
                        $metaPayload['auto_update_content'] = $value;
                    }
                    $meta = $this->saveManagedPostMeta($postId, $metaPayload);
                    $this->applyManagedPostContentUpdate($postId, $post, $meta);
                }
                break;

            default:
                $this->app->posts()->bulkAction(array_keys($postsById), $action, $value, $actor);
                break;
        }

        $this->recordOperation((int) $actor['id'], 'posts', 'bulk_' . (string) $action, 'post', 0, '批量处理帖子：' . implode(',', array_keys($postsById)));
        $this->clearManagedPostSelectOptionsCache();

        return;

        $this->app->posts()->bulkAction($ids, $action, $value, $actor);
        $this->recordOperation((int) $actor['id'], 'posts', 'bulk_' . (string) $action, 'post', 0, '批量处理帖子：' . implode(',', array_map('intval', $ids)));
    }

    public function listManagedForumPosts(array $filters = array())
    {
        $this->ensureManagedPostMetaReady();
        $maintenanceRegion = trim((string) ($filters['region'] ?? ''));
        $this->app->posts()->maintainRecycleRules(in_array($maintenanceRegion, array('macau', 'hongkong'), true) ? $maintenanceRegion : null);
        if ((string) ($filters['status'] ?? '') === 'deleted') {
            $this->backfillManagedDeletedIssueText(in_array($maintenanceRegion, array('macau', 'hongkong'), true) ? $maintenanceRegion : '');
        }
        $interactionSelect = '0 AS like_count, 0 AS favorite_count';
        $reportSelect = '0 AS report_total_count, 0 AS report_pending_count';
        $realViewSelect = '0 AS real_view_count';
        $todayViewSelect = '0 AS today_view_count';
        $hasPurchasesTable = false;

        $topScopeCaseSql = "CASE
                                WHEN posts.is_top_forever = 1 THEN 'top_4'
                                WHEN posts.is_top_admin = 1 THEN 'top_2'
                                WHEN posts.is_top_normal = 1 AND (posts.price > 0 OR posts.color_tag = 'red') THEN 'top_1'
                                WHEN posts.is_top_normal = 1 THEN 'top_3'
                                ELSE 'top_5'
                            END";

        $sql = 'SELECT posts.*,
                       COALESCE(NULLIF(post_meta.author_nickname, \'\'), users.username) AS author_name,
                       COALESCE(post_meta.author_nickname, \'\') AS manage_author_nickname,
                       forum_sections.name AS section_name,
                       forum_categories.name AS category_name,
                       COALESCE(post_meta.segment_no, 1) AS manage_segment_no,
                       GREATEST(1, LEAST(5, COALESCE(NULLIF(post_meta.segment_sort, 0), 5))) AS manage_segment_sort,
                       ' . $topScopeCaseSql . ' AS manage_top_scope,
                       COALESCE(post_meta.post_kind, CASE WHEN posts.price > 0 THEN \'data\' ELSE \'normal\' END) AS manage_post_kind,
                       COALESCE(post_meta.is_fake, 0) AS manage_is_fake,
                       COALESCE(post_meta.recent_result_log, \'\') AS manage_recent_result_log,
                       COALESCE(post_meta.fake_buyer_count, 0) AS manage_fake_buyer_count,
                       COALESCE(post_meta.is_hidden, 0) AS manage_is_hidden,
                       COALESCE(post_meta.is_encrypted, 0) AS manage_is_encrypted,
                       COALESCE(post_meta.title_prefix_text, \'\') AS manage_title_prefix_text,
                       COALESCE(post_meta.title_middle_text, \'\') AS manage_title_middle_text,
                       COALESCE(post_meta.title_prefix_color_mode, \'\') AS manage_title_prefix_color_mode,
                       COALESCE(post_meta.title_prefix_color_value, \'\') AS manage_title_prefix_color_value,
                       COALESCE(post_meta.title_middle_color_mode, \'\') AS manage_title_middle_color_mode,
                       COALESCE(post_meta.title_middle_color_value, \'\') AS manage_title_middle_color_value,
                       COALESCE(post_meta.author_nickname_color_mode, \'\') AS manage_author_nickname_color_mode,
                       COALESCE(post_meta.author_nickname_color_value, \'\') AS manage_author_nickname_color_value,
                       COALESCE(post_meta.title_font_size, \'\') AS manage_title_font_size,
                       COALESCE(post_meta.title_font_weight, \'\') AS manage_title_font_weight,
                       COALESCE(post_meta.deleted_issue_text, \'\') AS manage_deleted_issue_text,
                       COALESCE(post_meta.auto_update_mode, \'none\') AS manage_auto_update_mode,
                       COALESCE(post_meta.auto_update_content, \'\') AS manage_auto_update_content,
                       COALESCE(post_meta.manual_material, \'\') AS manage_manual_material,
                       COALESCE(post_meta.updated_at, \'\') AS manage_meta_updated_at,
                       0 AS manage_total_purchase_count,
                       0 AS manage_total_purchase_income,
                       ' . $realViewSelect . ',
                       ' . $todayViewSelect . ',
                       ' . $interactionSelect . ',
                       ' . $reportSelect . '
                FROM posts
                INNER JOIN users ON users.id = posts.author_id
                LEFT JOIN forum_sections ON forum_sections.id = posts.section_id
                LEFT JOIN forum_categories ON forum_categories.id = posts.category_id
                LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
                WHERE 1 = 1';
        $params = array();
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $region = trim((string) ($filters['region'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $colorTag = trim((string) ($filters['color_tag'] ?? ''));
        $sectionId = (int) ($filters['section_id'] ?? 0);
        $categoryId = (int) ($filters['category_id'] ?? 0);
        $segmentNo = (int) ($filters['segment_no'] ?? 0);
        $topScope = trim((string) ($filters['top_scope'] ?? ''));
        $materialUpdateFilter = trim((string) ($filters['material_update_filter'] ?? ''));
        $postKind = trim((string) ($filters['post_kind'] ?? ''));
        $hasPurchases = !empty($filters['has_purchases']);
        $wrongOnly = !empty($filters['wrong_only']);
        $saleFilter = trim((string) ($filters['sale_filter'] ?? ''));
        $purchaseFilter = trim((string) ($filters['purchase_filter'] ?? ''));
        $resultFilter = trim((string) ($filters['result_filter'] ?? ''));
        $wrongStreakFilter = trim((string) ($filters['wrong_streak_filter'] ?? ''));
        $resultLogSql = "REPLACE(REPLACE(COALESCE(post_meta.recent_result_log, ''), '，', ','), ' ', ',')";

        if ($keyword !== '') {
            $sql .= ' AND (posts.title LIKE :keyword_title OR posts.excerpt LIKE :keyword_excerpt OR users.username LIKE :keyword_author)';
            $params['keyword_title'] = '%' . $keyword . '%';
            $params['keyword_excerpt'] = '%' . $keyword . '%';
            $params['keyword_author'] = '%' . $keyword . '%';
        }

        if (in_array($region, array('macau', 'hongkong'), true)) {
            $sql .= ' AND posts.region = :region';
            $params['region'] = $region;
        }

        if (in_array($status, array('draft', 'pending', 'published', 'archived', 'deleted'), true)) {
            $sql .= ' AND posts.status = :status';
            $params['status'] = $status;
        } else {
            $sql .= ' AND posts.status <> :deleted_status';
            $params['deleted_status'] = 'deleted';
        }

        if ($colorTag !== '') {
            $sql .= ' AND posts.color_tag = :color_tag';
            $params['color_tag'] = $colorTag;
        }

        if ($sectionId > 0) {
            $sql .= ' AND posts.section_id = :section_id';
            $params['section_id'] = $sectionId;
        }

        if ($categoryId > 0) {
            $sql .= ' AND posts.category_id = :category_id';
            $params['category_id'] = $categoryId;
        }

        if ($segmentNo > 0) {
            $sql .= ' AND COALESCE(post_meta.segment_no, 1) = :segment_no';
            $params['segment_no'] = $segmentNo;
        }

        if (preg_match('/^top_([1-5])$/', $topScope, $topScopeMatches)) {
            $sql .= ' AND GREATEST(1, LEAST(5, COALESCE(NULLIF(post_meta.segment_sort, 0), 5))) = :top_scope_sort';
            $params['top_scope_sort'] = (int) ($topScopeMatches[1] ?? 5);
        } elseif (in_array($topScope, array('forever', 'admin', 'normal'), true)) {
            if ($topScope === 'forever') {
                $sql .= ' AND ' . $topScopeCaseSql . ' = :top_scope_legacy';
                $params['top_scope_legacy'] = 'top_4';
            } elseif ($topScope === 'admin') {
                $sql .= ' AND ' . $topScopeCaseSql . ' = :top_scope_legacy';
                $params['top_scope_legacy'] = 'top_2';
            } else {
                $sql .= ' AND ' . $topScopeCaseSql . ' IN (:top_scope_legacy_primary, :top_scope_legacy_secondary)';
                $params['top_scope_legacy_primary'] = 'top_1';
                $params['top_scope_legacy_secondary'] = 'top_3';
            }
        }

        if (in_array($postKind, array('data', 'normal'), true)) {
            $sql .= ' AND COALESCE(post_meta.post_kind, CASE WHEN posts.price > 0 THEN \'data\' ELSE \'normal\' END) = :post_kind';
            $params['post_kind'] = $postKind;
        }

        if ($hasPurchases) {
            $sql .= ' AND (posts.purchase_count + COALESCE(post_meta.fake_buyer_count, 0)) > 0';
        }

        if ($wrongOnly) {
            $sql .= ' AND COALESCE(post_meta.recent_result_log, \'\') LIKE :wrong_mark';
            $params['wrong_mark'] = '%错%';
        }

        if ($saleFilter === 'sale') {
            $sql .= ' AND posts.price > 0';
        } elseif ($saleFilter === 'free') {
            $sql .= ' AND posts.price <= 0';
        }

        if ($purchaseFilter === 'purchased') {
            $hasPurchasesTable = $this->tableExists($this->db(), 'purchases');
            $sql .= $hasPurchasesTable
                ? ' AND EXISTS (SELECT 1 FROM purchases WHERE purchases.post_id = posts.id LIMIT 1)'
                : ' AND 1 = 0';
        }

        if ($wrongStreakFilter === 'two_plus') {
            $sql .= ' AND ' . $resultLogSql . ' REGEXP :wrong_streak_any_mark';
            $params['wrong_streak_any_mark'] = '[0-9]+[[:space:]]*(错|錯)';
        }

        $orderSql = ' ORDER BY COALESCE(post_meta.segment_no, 1) ASC, GREATEST(1, LEAST(5, COALESCE(NULLIF(post_meta.segment_sort, 0), 5))) ASC, posts.created_at DESC';
        $usePagination = !empty($filters['paginate'])
            && !in_array($materialUpdateFilter, array('waiting', 'updated'), true)
            && !in_array($resultFilter, array('hit', 'wrong'), true);
        if ($usePagination) {
            $page = $this->paginateAdminQuery(
                'SELECT COUNT(*) AS total_count FROM (' . $sql . ') AS managed_forum_post_count_table',
                $sql . $orderSql,
                $params,
                (int) ($filters['page_no'] ?? 1),
                (int) ($filters['per_page'] ?? 40)
            );
            $page['items'] = $this->filterManagedForumPostsByMaterialUpdateState((array) ($page['items'] ?? array()), $materialUpdateFilter);
            $page['items'] = $this->filterManagedForumPostsByResultState((array) ($page['items'] ?? array()), $resultFilter, $wrongStreakFilter);
            if (array_key_exists('include_stats', $filters) && empty($filters['include_stats'])) {
                return $page;
            }

            $page['items'] = $this->enrichManagedForumPostStats($page['items']);

            return $page;
        }

        $sql .= $orderSql . ' LIMIT 150';

        $rows = $this->db()->fetchAll($sql, $params);
        $rows = $this->filterManagedForumPostsByMaterialUpdateState($rows, $materialUpdateFilter);
        $rows = $this->filterManagedForumPostsByResultState($rows, $resultFilter, $wrongStreakFilter);
        $shouldPaginateMaterialUpdate = !empty($filters['paginate'])
            && in_array($materialUpdateFilter, array('waiting', 'updated'), true)
            && !in_array($resultFilter, array('hit', 'wrong'), true);
        if ($shouldPaginateMaterialUpdate) {
            $perPage = max(1, min(100, (int) ($filters['per_page'] ?? 40)));
            $total = count($rows);
            $pageCount = max(1, (int) ceil($total / $perPage));
            $pageNo = min(max(1, (int) ($filters['page_no'] ?? 1)), $pageCount);
            $items = array_slice($rows, ($pageNo - 1) * $perPage, $perPage);
            if (!array_key_exists('include_stats', $filters) || !empty($filters['include_stats'])) {
                $items = $this->enrichManagedForumPostStats($items);
            }

            return array(
                'items' => $items,
                'total' => $total,
                'page_no' => $pageNo,
                'per_page' => $perPage,
                'page_count' => $pageCount,
            );
        }
        if (array_key_exists('include_stats', $filters) && empty($filters['include_stats'])) {
            return $rows;
        }

        return $this->enrichManagedForumPostStats($rows);

    }

    public function listManagedForumPostsPage(array $filters = array())
    {
        $filters['paginate'] = true;
        $page = $this->listManagedForumPosts($filters);
        if (isset($page['items']) && is_array($page)) {
            return $page;
        }

        $rows = is_array($page) ? $page : array();

        return array(
            'items' => $rows,
            'total' => count($rows),
            'page_no' => 1,
            'per_page' => max(1, count($rows)),
            'page_count' => 1,
        );
    }

    public function managedPostSelectOptions($selectedPostId = 0, $region = '', $limit = 150)
    {
        $this->ensureManagedPostMetaReady();
        $limit = max(20, min(150, (int) $limit));
        $region = in_array((string) $region, array('macau', 'hongkong'), true) ? (string) $region : '';
        $rows = $this->managedPostSelectBaseOptions($region, $limit);

        $selectedPostId = (int) $selectedPostId;
        if ($selectedPostId > 0) {
            $hasSelected = false;
            foreach ($rows as $row) {
                if ((int) ($row['id'] ?? 0) === $selectedPostId) {
                    $hasSelected = true;
                    break;
                }
            }
            if (!$hasSelected) {
                $selectedRow = $this->managedPostSelectExtraOption($selectedPostId);
                if ($selectedRow) {
                    array_unshift($rows, $selectedRow);
                }
            }
        }

        return $this->app->posts()->attachDisplayTitlePayloads($rows, 'id');
    }

    protected function managedPostSelectBaseOptions($region, $limit)
    {
        $region = in_array((string) $region, array('macau', 'hongkong'), true) ? (string) $region : '';
        $limit = max(20, min(150, (int) $limit));
        $cacheKey = ($region !== '' ? $region : 'all') . '|' . $limit;
        if (isset($this->managedPostSelectBaseCache[$cacheKey])) {
            return $this->managedPostSelectBaseCache[$cacheKey];
        }

        $cacheVersion = (string) $this->app->cache()->get(self::MANAGED_POST_SELECT_OPTIONS_CACHE_VERSION_KEY, '1', 3600);
        $persistentCacheKey = 'admin_managed_post_select_options_' . md5($cacheVersion . '|' . $cacheKey);
        $cached = $this->app->cache()->get($persistentCacheKey, null, 15);
        if (is_array($cached)) {
            $this->managedPostSelectBaseCache[$cacheKey] = $cached;

            return $cached;
        }

        $params = array('deleted_status' => 'deleted');
        $regionSql = '';
        if ($region !== '') {
            $regionSql = ' AND posts.region = :region';
            $params['region'] = $region;
        }

        $this->managedPostSelectBaseCache[$cacheKey] = $this->db()->fetchAll(
            'SELECT posts.id, posts.title, posts.region
             FROM posts
             LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
             WHERE posts.status <> :deleted_status'
                . $regionSql .
             ' ORDER BY COALESCE(post_meta.segment_no, 1) ASC,
                      GREATEST(1, LEAST(5, COALESCE(NULLIF(post_meta.segment_sort, 0), 5))) ASC,
                      posts.created_at DESC,
                      posts.id DESC
             LIMIT ' . $limit,
            $params
        );
        $this->app->cache()->put($persistentCacheKey, $this->managedPostSelectBaseCache[$cacheKey]);

        return $this->managedPostSelectBaseCache[$cacheKey];
    }

    protected function managedPostSelectExtraOption($postId)
    {
        $postId = (int) $postId;
        if ($postId <= 0) {
            return null;
        }
        if (array_key_exists($postId, $this->managedPostSelectExtraCache)) {
            return $this->managedPostSelectExtraCache[$postId];
        }

        $cacheVersion = (string) $this->app->cache()->get(self::MANAGED_POST_SELECT_OPTIONS_CACHE_VERSION_KEY, '1', 3600);
        $cacheKey = 'admin_managed_post_select_extra_' . md5($cacheVersion . '|' . $postId);
        $cached = $this->app->cache()->get($cacheKey, null, 15);
        if (is_array($cached) || $cached === false) {
            $this->managedPostSelectExtraCache[$postId] = $cached === false ? null : $cached;

            return $this->managedPostSelectExtraCache[$postId];
        }

        $this->managedPostSelectExtraCache[$postId] = $this->db()->fetch(
            'SELECT posts.id, posts.title, posts.region
             FROM posts
             WHERE posts.id = :id
             LIMIT 1',
            array('id' => $postId)
        );
        $this->app->cache()->put($cacheKey, $this->managedPostSelectExtraCache[$postId] ?: false);

        return $this->managedPostSelectExtraCache[$postId];
    }

    protected function clearManagedPostSelectOptionsCache()
    {
        $this->managedPostSelectBaseCache = array();
        $this->managedPostSelectExtraCache = array();
        $this->app->cache()->put(self::MANAGED_POST_SELECT_OPTIONS_CACHE_VERSION_KEY, (string) microtime(true));
        $this->clearManagedDrawMaterialEditorExpertVersionCache();
    }

    protected function filterManagedForumPostsByMaterialUpdateState(array $rows, $materialUpdateFilter)
    {
        $materialUpdateFilter = trim((string) $materialUpdateFilter);
        if (!in_array($materialUpdateFilter, array('waiting', 'updated'), true)) {
            return $rows;
        }

        $filtered = array();
        foreach ($rows as $row) {
            $payload = $this->app->posts()->currentIssueEditorPayload($row);
            $isWaiting = !empty($payload['is_waiting']);
            if ($materialUpdateFilter === 'waiting' && !$isWaiting) {
                continue;
            }
            if ($materialUpdateFilter === 'updated' && $isWaiting) {
                continue;
            }
            $filtered[] = $row;
        }

        return $filtered;
    }

    protected function managedDrawMaterialEditorExpertVersionCacheKey(string $region): string
    {
        return 'admin_draw_material_editor_expert_version_' . $this->normalizeManagedDrawMaterialRegion($region);
    }

    protected function clearManagedDrawMaterialEditorExpertVersionCache(string $region = null): void
    {
        $this->homeExpertPostsCache = array();

        if ($region === null) {
            $this->managedDrawMaterialEditorExpertVersionRequestCache = array();
            foreach (array('macau', 'hongkong') as $cacheRegion) {
                $this->app->cache()->forget($this->managedDrawMaterialEditorExpertVersionCacheKey($cacheRegion));
            }

            return;
        }

        $region = $this->normalizeManagedDrawMaterialRegion($region);
        unset($this->managedDrawMaterialEditorExpertVersionRequestCache[$region]);
        $this->app->cache()->forget($this->managedDrawMaterialEditorExpertVersionCacheKey($region));
    }

    protected function filterManagedForumPostsByResultState(array $rows, $resultFilter, $wrongStreakFilter)
    {
        $resultFilter = trim((string) $resultFilter);
        $wrongStreakFilter = trim((string) $wrongStreakFilter);
        if (!in_array($resultFilter, array('hit', 'wrong'), true) && $wrongStreakFilter !== 'two_plus') {
            return $rows;
        }

        $filtered = array();
        foreach ($rows as $row) {
            $logText = (string) ($row['manage_recent_result_log'] ?? ($row['recent_result_log'] ?? ''));
            if (in_array($resultFilter, array('hit', 'wrong'), true)) {
                $latestRecord = $this->managedForumPostVisibleResultRecord($row);
                if (!is_array($latestRecord)) {
                    continue;
                }

                $latestStatus = (string) ($latestRecord['status'] ?? '');
                $latestWrong = in_array($latestStatus, array('错', '錯'), true);
                if ($resultFilter === 'hit' && $latestWrong) {
                    continue;
                }
                if ($resultFilter === 'wrong' && !$latestWrong) {
                    continue;
                }
            }

            if ($wrongStreakFilter === 'two_plus' && !$this->managedPostGeneratorHasTwoWrongRecords($logText)) {
                continue;
            }

            $filtered[] = $row;
        }

        return $filtered;
    }

    protected function managedForumPostVisibleResultRecord(array $row)
    {
        $region = (string) ($row['region'] ?? 'macau') === 'hongkong' ? 'hongkong' : 'macau';
        $contentSource = (string) ($row['full_content'] ?? '');
        if (trim($contentSource) === '') {
            $contentSource = (string) ($row['manage_manual_material'] ?? '');
        }
        if (trim($contentSource) === '') {
            $contentSource = (string) ($row['manage_auto_update_content'] ?? '');
        }
        if (trim($contentSource) === '') {
            $contentSource = (string) ($row['excerpt'] ?? '');
        }

        $contentText = preg_replace('/<\s*(br|\/p|\/div|\/li|\/tr)\b[^>]*>/iu', "\n", $contentSource);
        $contentText = str_replace(array("\r\n", "\r"), "\n", strip_tags((string) $contentText));
        $contentText = trim((string) preg_replace('/[^\S\n]+/u', ' ', $contentText));
        $displayLines = array();
        foreach (preg_split('/\R/u', $contentText) as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            $displayLines[] = $this->managedForecastDisplayLine($region, $line);
        }

        if ($displayLines !== array()) {
            $stats = $this->managedForecastRecordStats($region, implode("\n", $displayLines), '', true, false);
            $latestRecord = $this->managedLatestResultRecordFromStats($stats);
            if (is_array($latestRecord)) {
                return $latestRecord;
            }
        }

        return $this->managedPostGeneratorLatestResultRecord((string) ($row['manage_recent_result_log'] ?? ($row['recent_result_log'] ?? '')));
    }

    protected function managedLatestResultRecordFromStats(array $stats)
    {
        $latestRecord = null;
        foreach ((array) ($stats['records'] ?? array()) as $record) {
            $issue = (int) ($record['issue'] ?? 0);
            $status = trim((string) ($record['status'] ?? ''));
            if ($issue <= 0 || $status === '') {
                continue;
            }
            if (is_array($latestRecord) && $issue < (int) ($latestRecord['issue'] ?? 0)) {
                continue;
            }
            $latestRecord = array('issue' => $issue, 'status' => $status);
        }

        return $latestRecord;
    }

    protected function enrichManagedForumPostStats(array $posts)
    {
        if ($posts === array()) {
            return $posts;
        }

        $postIds = array();
        foreach ($posts as $index => $post) {
            $postId = (int) ($post['id'] ?? 0);
            if ($postId <= 0) {
                continue;
            }

            $postIds[] = $postId;
            $posts[$index]['manage_total_purchase_count'] = 0;
            $posts[$index]['manage_total_purchase_income'] = 0;
            $posts[$index]['real_view_count'] = 0;
            $posts[$index]['today_view_count'] = 0;
            $posts[$index]['like_count'] = 0;
            $posts[$index]['favorite_count'] = 0;
            $posts[$index]['report_total_count'] = 0;
            $posts[$index]['report_pending_count'] = 0;
        }

        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
        if ($postIds === array()) {
            return $posts;
        }

        $postIdSql = implode(',', $postIds);
        $postIndex = array();
        foreach ($posts as $index => $post) {
            $postIndex[(int) ($post['id'] ?? 0)] = $index;
        }

        $statsCacheKey = 'managed_forum_post_stats_' . md5(implode(',', $postIds));
        $statsCacheFields = array(
            'manage_total_purchase_count',
            'manage_total_purchase_income',
            'real_view_count',
            'today_view_count',
            'like_count',
            'favorite_count',
            'report_total_count',
            'report_pending_count',
        );
        $cachedStats = $this->app->cache()->get($statsCacheKey, null, 300);
        if (is_array($cachedStats)) {
            foreach ($posts as $index => $post) {
                $postId = (int) ($post['id'] ?? 0);
                if ($postId <= 0 || !isset($cachedStats[$postId]) || !is_array($cachedStats[$postId])) {
                    continue;
                }
                foreach ($statsCacheFields as $field) {
                    if (array_key_exists($field, $cachedStats[$postId])) {
                        $posts[$index][$field] = (int) $cachedStats[$postId][$field];
                    }
                }
            }

            return $posts;
        }

        if ($this->tableExists($this->db(), 'purchases')) {
            $purchaseRows = $this->db()->fetchAll(
                'SELECT purchases.post_id,
                        COUNT(*) AS real_purchase_count,
                        COALESCE(SUM(purchases.price), 0) AS real_purchase_income
                 FROM purchases
                 WHERE purchases.post_id IN (' . $postIdSql . ')
                 GROUP BY purchases.post_id'
            );
            foreach ($purchaseRows as $row) {
                $postId = (int) ($row['post_id'] ?? 0);
                if (!array_key_exists($postId, $postIndex)) {
                    continue;
                }
                $index = $postIndex[$postId];
                $posts[$index]['manage_total_purchase_count'] = (int) ($row['real_purchase_count'] ?? 0);
                $posts[$index]['manage_total_purchase_income'] = (int) ($row['real_purchase_income'] ?? 0);
            }
        }

        foreach ($posts as $index => $post) {
            $storedPurchaseCount = max(0, (int) ($post['purchase_count'] ?? 0));
            $listedPurchaseCount = max(0, (int) ($posts[$index]['manage_total_purchase_count'] ?? 0));
            if ($storedPurchaseCount > $listedPurchaseCount) {
                $posts[$index]['manage_total_purchase_count'] = $storedPurchaseCount;
            }

            $estimatedPurchaseIncome = $storedPurchaseCount * max(0, (int) ($post['price'] ?? 0));
            if ($estimatedPurchaseIncome > (int) ($posts[$index]['manage_total_purchase_income'] ?? 0)) {
                $posts[$index]['manage_total_purchase_income'] = $estimatedPurchaseIncome;
            }
        }

        if ($this->tableExists($this->db(), 'post_unique_views')) {
            $viewRows = $this->db()->fetchAll(
                'SELECT post_unique_views.post_id,
                        COUNT(*) AS real_view_count,
                        COALESCE(SUM(CASE WHEN post_unique_views.viewed_on = CURDATE() THEN 1 ELSE 0 END), 0) AS today_view_count
                 FROM post_unique_views
                 WHERE post_unique_views.post_id IN (' . $postIdSql . ')
                 GROUP BY post_unique_views.post_id'
            );
            foreach ($viewRows as $row) {
                $postId = (int) ($row['post_id'] ?? 0);
                if (!array_key_exists($postId, $postIndex)) {
                    continue;
                }
                $index = $postIndex[$postId];
                $posts[$index]['real_view_count'] = (int) ($row['real_view_count'] ?? 0);
                $posts[$index]['today_view_count'] = (int) ($row['today_view_count'] ?? 0);
            }
        }

        if ($this->tableExists($this->db(), 'post_interactions')) {
            $interactionRows = $this->db()->fetchAll(
                "SELECT post_interactions.post_id,
                        post_interactions.interaction_type,
                        COUNT(*) AS total_count
                 FROM post_interactions
                 WHERE post_interactions.post_id IN (" . $postIdSql . ")
                   AND post_interactions.status = 1
                   AND post_interactions.interaction_type IN ('like', 'favorite')
                 GROUP BY post_interactions.post_id, post_interactions.interaction_type"
            );
            foreach ($interactionRows as $row) {
                $postId = (int) ($row['post_id'] ?? 0);
                $type = (string) ($row['interaction_type'] ?? '');
                if (!array_key_exists($postId, $postIndex)) {
                    continue;
                }
                $index = $postIndex[$postId];
                if ($type === 'like') {
                    $posts[$index]['like_count'] = (int) ($row['total_count'] ?? 0);
                } elseif ($type === 'favorite') {
                    $posts[$index]['favorite_count'] = (int) ($row['total_count'] ?? 0);
                }
            }
        }

        if ($this->tableExists($this->db(), 'post_reports')) {
            $reportRows = $this->db()->fetchAll(
                "SELECT post_reports.post_id,
                        COUNT(*) AS report_total_count,
                        COALESCE(SUM(CASE WHEN post_reports.status = 'pending' THEN 1 ELSE 0 END), 0) AS report_pending_count
                 FROM post_reports
                 WHERE post_reports.post_id IN (" . $postIdSql . ")
                 GROUP BY post_reports.post_id"
            );
            foreach ($reportRows as $row) {
                $postId = (int) ($row['post_id'] ?? 0);
                if (!array_key_exists($postId, $postIndex)) {
                    continue;
                }
                $index = $postIndex[$postId];
                $posts[$index]['report_total_count'] = (int) ($row['report_total_count'] ?? 0);
                $posts[$index]['report_pending_count'] = (int) ($row['report_pending_count'] ?? 0);
            }
        }

        $statsCachePayload = array();
        foreach ($posts as $post) {
            $postId = (int) ($post['id'] ?? 0);
            if ($postId <= 0) {
                continue;
            }
            $statsCachePayload[$postId] = array();
            foreach ($statsCacheFields as $field) {
                $statsCachePayload[$postId][$field] = (int) ($post[$field] ?? 0);
            }
        }
        if ($statsCachePayload !== array()) {
            $this->app->cache()->put($statsCacheKey, $statsCachePayload);
        }

        return $posts;
    }

    public function managedForumPostSummaryCounts($region = 'macau')
    {
        $this->ensureManagedPostMetaReady();
        $region = trim((string) $region);
        if (!in_array($region, array('macau', 'hongkong'), true)) {
            $region = 'macau';
        }

        $this->app->posts()->maintainRecycleRules($region);

        $postKindSql = "COALESCE(post_meta.post_kind, CASE WHEN posts.price > 0 THEN 'data' ELSE 'normal' END)";
        $segmentSql = 'COALESCE(post_meta.segment_no, 1)';
        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS total_count,
                    SUM(CASE WHEN ' . $postKindSql . ' = :data_kind THEN 1 ELSE 0 END) AS data_post_count,
                    SUM(CASE WHEN ' . $postKindSql . ' = :normal_kind THEN 1 ELSE 0 END) AS normal_post_count,
                    SUM(CASE WHEN posts.price > 0 THEN 1 ELSE 0 END) AS priced_sale_post_count,
                    SUM(CASE WHEN ' . $segmentSql . ' = 1 THEN 1 ELSE 0 END) AS segment_1_count,
                    SUM(CASE WHEN ' . $segmentSql . ' = 2 THEN 1 ELSE 0 END) AS segment_2_count,
                    SUM(CASE WHEN ' . $segmentSql . ' = 3 THEN 1 ELSE 0 END) AS segment_3_count
             FROM posts
             LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
             WHERE posts.region = :region
               AND posts.status = :status
               AND COALESCE(post_meta.is_hidden, 0) = 0',
            array(
                'data_kind' => 'data',
                'normal_kind' => 'normal',
                'region' => $region,
                'status' => 'published',
            )
        );
        $row = is_array($row) ? $row : array();

        return array(
            'total_count' => (int) ($row['total_count'] ?? 0),
            'data_post_count' => (int) ($row['data_post_count'] ?? 0),
            'normal_post_count' => (int) ($row['normal_post_count'] ?? 0),
            'priced_sale_post_count' => (int) ($row['priced_sale_post_count'] ?? 0),
            'segment_1_count' => (int) ($row['segment_1_count'] ?? 0),
            'segment_2_count' => (int) ($row['segment_2_count'] ?? 0),
            'segment_3_count' => (int) ($row['segment_3_count'] ?? 0),
        );
    }

    public function managedForumPostById($postId)
    {
        $this->ensureManagedPostMetaReady();
        $this->app->posts()->maintainRecycleRules();
        $interactionSelect = '0 AS like_count, 0 AS favorite_count';
        $reportSelect = '0 AS report_total_count, 0 AS report_pending_count';
        $realViewSelect = '0 AS real_view_count';
        $todayViewSelect = '0 AS today_view_count';

        if ($this->tableExists($this->db(), 'post_interactions')) {
            $interactionSelect = '(SELECT COUNT(*) FROM post_interactions WHERE post_interactions.post_id = posts.id AND post_interactions.interaction_type = \'like\' AND post_interactions.status = 1) AS like_count,
                                  (SELECT COUNT(*) FROM post_interactions WHERE post_interactions.post_id = posts.id AND post_interactions.interaction_type = \'favorite\' AND post_interactions.status = 1) AS favorite_count';
        }

        if ($this->tableExists($this->db(), 'post_reports')) {
            $reportSelect = '(SELECT COUNT(*) FROM post_reports WHERE post_reports.post_id = posts.id) AS report_total_count,
                             (SELECT COUNT(*) FROM post_reports WHERE post_reports.post_id = posts.id AND post_reports.status = \'pending\') AS report_pending_count';
        }

        if ($this->tableExists($this->db(), 'post_unique_views')) {
            $realViewSelect = "(SELECT COUNT(*)
                                FROM post_unique_views
                                WHERE post_unique_views.post_id = posts.id) AS real_view_count";
            $todayViewSelect = "(SELECT COUNT(*)
                                 FROM post_unique_views
                                 WHERE post_unique_views.post_id = posts.id
                                   AND post_unique_views.viewed_on = CURDATE()) AS today_view_count";
        }

        return $this->db()->fetch(
            'SELECT posts.*,
                    COALESCE(NULLIF(post_meta.author_nickname, \'\'), users.username) AS author_name,
                    COALESCE(post_meta.author_nickname, \'\') AS manage_author_nickname,
                    forum_sections.name AS section_name,
                    forum_categories.name AS category_name,
                    COALESCE(post_meta.segment_no, 1) AS manage_segment_no,
                    GREATEST(1, LEAST(5, COALESCE(NULLIF(post_meta.segment_sort, 0), 5))) AS manage_segment_sort,
                    COALESCE(post_meta.post_kind, CASE WHEN posts.price > 0 THEN \'data\' ELSE \'normal\' END) AS manage_post_kind,
                    COALESCE(post_meta.is_fake, 0) AS manage_is_fake,
                    COALESCE(post_meta.recent_result_log, \'\') AS manage_recent_result_log,
                    COALESCE(post_meta.fake_buyer_count, 0) AS manage_fake_buyer_count,
                    COALESCE(post_meta.is_hidden, 0) AS manage_is_hidden,
                    COALESCE(post_meta.is_encrypted, 0) AS manage_is_encrypted,
                    COALESCE(post_meta.title_prefix_text, \'\') AS manage_title_prefix_text,
                    COALESCE(post_meta.title_middle_text, \'\') AS manage_title_middle_text,
                    COALESCE(post_meta.title_prefix_color_mode, \'\') AS manage_title_prefix_color_mode,
                    COALESCE(post_meta.title_prefix_color_value, \'\') AS manage_title_prefix_color_value,
                    COALESCE(post_meta.title_middle_color_mode, \'\') AS manage_title_middle_color_mode,
                    COALESCE(post_meta.title_middle_color_value, \'\') AS manage_title_middle_color_value,
                    COALESCE(post_meta.author_nickname_color_mode, \'\') AS manage_author_nickname_color_mode,
                    COALESCE(post_meta.author_nickname_color_value, \'\') AS manage_author_nickname_color_value,
                    COALESCE(post_meta.title_font_size, \'\') AS manage_title_font_size,
                    COALESCE(post_meta.title_font_weight, \'\') AS manage_title_font_weight,
                    COALESCE(post_meta.deleted_issue_text, \'\') AS manage_deleted_issue_text,
                    COALESCE(post_meta.auto_update_mode, \'none\') AS manage_auto_update_mode,
                    COALESCE(post_meta.auto_update_content, \'\') AS manage_auto_update_content,
                    COALESCE(post_meta.manual_material, \'\') AS manage_manual_material,
                    COALESCE(post_meta.updated_at, \'\') AS manage_meta_updated_at,
                    GREATEST(
                        COALESCE(posts.purchase_count, 0),
                        (SELECT COUNT(*) FROM purchases WHERE purchases.post_id = posts.id)
                    ) AS manage_total_purchase_count,
                    GREATEST(
                        (GREATEST(COALESCE(posts.purchase_count, 0), 0) * GREATEST(COALESCE(posts.price, 0), 0)),
                        (SELECT COALESCE(SUM(purchases.price), 0) FROM purchases WHERE purchases.post_id = posts.id)
                    ) AS manage_total_purchase_income,
                    ' . $realViewSelect . ',
                    ' . $todayViewSelect . ',
                    ' . $interactionSelect . ',
                    ' . $reportSelect . '
             FROM posts
             INNER JOIN users ON users.id = posts.author_id
             LEFT JOIN forum_sections ON forum_sections.id = posts.section_id
             LEFT JOIN forum_categories ON forum_categories.id = posts.category_id
             LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
             WHERE posts.id = :id
             LIMIT 1',
            array('id' => (int) $postId)
        );

        return $this->db()->fetch(
            'SELECT posts.*,
                    users.username AS author_name,
                    \'\' AS manage_author_nickname,
                    forum_sections.name AS section_name,
                    forum_categories.name AS category_name,
                    ' . $interactionSelect . ',
                    ' . $reportSelect . '
             FROM posts
             INNER JOIN users ON users.id = posts.author_id
             LEFT JOIN forum_sections ON forum_sections.id = posts.section_id
             LEFT JOIN forum_categories ON forum_categories.id = posts.category_id
             WHERE posts.id = :id
             LIMIT 1',
            array('id' => (int) $postId)
        );
    }

    public function saveManagedPostLockSettings(array $payload, array $actor)
    {
        $region = $this->normalizeManagedPostGeneratorRegion((string) ($payload['region'] ?? 'macau'));
        $beforeMinutes = (int) ($payload['before_minutes'] ?? 60);
        $currentSettings = $this->app->posts()->postLockSettings();
        $unlockTime = trim((string) ($payload['unlock_time'] ?? ($currentSettings['unlock_time'] ?? '23:59')));

        if ($beforeMinutes < 0) {
            $beforeMinutes = 0;
        } elseif ($beforeMinutes > 1440) {
            $beforeMinutes = 1440;
        }
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $unlockTime)) {
            throw new RuntimeException('解锁时间格式错误。');
        }

        try {
            $issue = $this->currentIssueSnapshotByRegion($region);
            $plannedOpenAt = trim((string) ($issue['planned_open_at'] ?? ''));
            if ($plannedOpenAt !== '') {
                $openAt = new \DateTimeImmutable($plannedOpenAt);
                $actualOpenAt = trim((string) ($issue['actual_open_at'] ?? ''));
                $baseOpenAt = $actualOpenAt !== '' ? new \DateTimeImmutable($actualOpenAt) : $openAt;
                $lockStartAt = $openAt->modify('-' . $beforeMinutes . ' minutes');
                $minUnlockTime = $lockStartAt->format('Y-m-d') === $baseOpenAt->format('Y-m-d')
                    ? $lockStartAt->format('H:i')
                    : '00:00';
                if ($unlockTime < $minUnlockTime) {
                    throw new RuntimeException('解锁时间不能早于锁定帖子时间（' . $minUnlockTime . '）。');
                }
            }
        } catch (\Exception $exception) {
            if ($exception instanceof RuntimeException) {
                throw $exception;
            }
        }

        $this->app->settings()->setMany('post_lock', array(
            'post_lock.before_minutes' => (string) $beforeMinutes,
            'post_lock.unlock_time' => $unlockTime,
        ));

        $this->recordOperation((int) ($actor['id'] ?? 0), 'posts', 'save_lock_settings', 'settings', 0, '保存锁帖时间设置');

        return $this->app->posts()->postLockSettings();
    }

    public function saveManagedPostLikeIncrementSettings(array $payload, array $actor)
    {
        $baseMin = (int) ($payload['base_min'] ?? 368);
        $baseMax = (int) ($payload['base_max'] ?? 668);
        $min = (int) ($payload['increment_min'] ?? 1);
        $max = (int) ($payload['increment_max'] ?? 1);

        if ($baseMin < 1 || $baseMax < 1) {
            throw new RuntimeException('默认点赞量范围必须大于 0。');
        }

        if ($baseMin > 999999 || $baseMax > 999999) {
            throw new RuntimeException('默认点赞量范围不能超过 999999。');
        }

        if ($baseMax < $baseMin) {
            throw new RuntimeException('默认点赞量最大值不能小于最小值。');
        }

        if ($min < 1 || $max < 1) {
            throw new RuntimeException('点赞递增范围必须大于 0。');
        }

        if ($min > 999 || $max > 999) {
            throw new RuntimeException('点赞递增范围不能超过 999。');
        }

        if ($max < $min) {
            throw new RuntimeException('点赞递增最大值不能小于最小值。');
        }

        $this->app->settings()->setMany('post_like', array(
            'post_like.base_min' => (string) $baseMin,
            'post_like.base_max' => (string) $baseMax,
            'post_like.increment_min' => (string) $min,
            'post_like.increment_max' => (string) $max,
        ));

        $this->recordOperation((int) ($actor['id'] ?? 0), 'posts', 'save_like_increment_settings', 'settings', 0, '保存帖子点赞显示参数');

        return $this->app->posts()->postLikeIncrementSettings();
    }

    public function saveManagedPostViewDisplaySettings(array $payload, array $actor)
    {
        $baseMin = (int) ($payload['base_min'] ?? 4935);
        $baseMax = (int) ($payload['base_max'] ?? 7563);
        $incrementMin = (int) ($payload['increment_min'] ?? 14);
        $incrementMax = (int) ($payload['increment_max'] ?? 20);

        if ($baseMin < 1 || $baseMax < 1) {
            throw new RuntimeException('默认浏览量范围必须大于 0。');
        }

        if ($baseMin > 999999 || $baseMax > 999999) {
            throw new RuntimeException('默认浏览量范围不能超过 999999。');
        }

        if ($baseMax < $baseMin) {
            throw new RuntimeException('默认浏览量最大值不能小于最小值。');
        }

        if ($incrementMin < 1 || $incrementMax < 1) {
            throw new RuntimeException('递增浏览量范围必须大于 0。');
        }

        if ($incrementMin > 999 || $incrementMax > 999) {
            throw new RuntimeException('递增浏览量范围不能超过 999。');
        }

        if ($incrementMax < $incrementMin) {
            throw new RuntimeException('递增浏览量最大值不能小于最小值。');
        }

        $this->app->settings()->setMany('post_view', array(
            'post_view.base_min' => (string) $baseMin,
            'post_view.base_max' => (string) $baseMax,
            'post_view.increment_min' => (string) $incrementMin,
            'post_view.increment_max' => (string) $incrementMax,
        ));

        $this->recordOperation((int) ($actor['id'] ?? 0), 'posts', 'save_view_display_settings', 'settings', 0, '保存帖子浏览量显示参数');

        return $this->app->posts()->postViewDisplaySettings();
    }

    public function saveManagedPostSaleBuyerIncrementSettings(array $payload, array $actor)
    {
        $incrementMin = (int) ($payload['increment_min'] ?? 1);
        $incrementMax = (int) ($payload['increment_max'] ?? 3);

        if ($incrementMin < 0 || $incrementMax < 0) {
            throw new RuntimeException('出售购买递增范围不能小于 0。');
        }

        if ($incrementMin > 999 || $incrementMax > 999) {
            throw new RuntimeException('出售购买递增范围不能超过 999。');
        }

        if ($incrementMax < $incrementMin) {
            throw new RuntimeException('出售购买递增最大值不能小于最小值。');
        }

        $this->app->settings()->setMany('post_sale_buyer', array(
            'post_sale_buyer.increment_min' => (string) $incrementMin,
            'post_sale_buyer.increment_max' => (string) $incrementMax,
        ));

        $this->recordOperation((int) ($actor['id'] ?? 0), 'posts', 'save_sale_buyer_increment_settings', 'settings', 0, '保存出售购买递增参数');

        return $this->app->posts()->postSaleBuyerIncrementSettings();
    }

    protected function managedPostLockBypassActor(array $actor)
    {
        $roleCode = (string) ($actor['role_code'] ?? '');
        $roleName = (string) ($actor['role_name'] ?? '');

        return (int) ($actor['is_super'] ?? 0) === 1
            || in_array($roleCode, array('super_admin', 'site_manager', 'maintenance_admin', 'maintainer', 'customer_service'), true)
            || in_array($roleName, array('超级管理员', '维护管理员', '在线客服', '站点管理员'), true);
    }

    protected function assertManagedPostUnlockedForEdit($region, array $actor, array $post = array())
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        if (!empty($post) && (int) ($post['price'] ?? 0) > 0) {
            $postId = (int) ($post['id'] ?? 0);
            $content = trim((string) ($post['full_content'] ?? ''));
            $waitingContent = "资料等待更新中··· ···\n关注本站，精彩无限，中奖根本停不下来······";
            $hasMaterialContent = $content !== ''
                && mb_strpos($content, '资料等待更新中', 0, 'UTF-8') === false
                && !in_array($content, array(
                    $waitingContent,
                    '此资料出售，购买后可查看完整资料',
                    '此内容为出售内容，购买后可查看完整资料。',
                ), true);
            $realPurchaseCount = 0;
            if ($postId > 0 && $this->tableExists($this->db(), 'purchases')) {
                $purchaseRow = $this->db()->fetch(
                    'SELECT COUNT(*) AS total_count FROM purchases WHERE post_id = :post_id',
                    array('post_id' => $postId)
                );
                $realPurchaseCount = (int) ($purchaseRow['total_count'] ?? 0);
            }

            $openedForPublic = false;
            if ($postId > 0) {
                try {
                    $frontPost = $this->app->posts()->findPost($postId);
                    if (is_array($frontPost)) {
                        $openedForPublic = $hasMaterialContent
                            && trim((string) $this->app->posts()->visibleContent($frontPost, null))
                            === trim((string) ($frontPost['full_content'] ?? ''));
                    }
                } catch (\Throwable $exception) {
                    $openedForPublic = false;
                }
            }

            if ($openedForPublic) {
                throw new RuntimeException('公开出售帖子的资料内容已经固定，不能再变更。');
            }

            if ($realPurchaseCount > 0 && $hasMaterialContent) {
                throw new RuntimeException('出售帖子已有会员购买，资料内容不能再变更。');
            }

            $state = $this->app->posts()->postLockState($region);
            if (!empty($state['is_locked']) && $hasMaterialContent) {
                throw new RuntimeException('出售帖子已进入锁帖期，已更新的资料内容不能再变更。');
            }
        }

        $this->app->posts()->assertPostUnlockedForEdit(
            $region,
            $this->managedPostLockBypassActor($actor)
        );
    }

    protected function assertManagedPostsUnlockedForEdit(array $postsById, array $actor)
    {
        foreach ($postsById as $post) {
            $this->assertManagedPostUnlockedForEdit(
                (string) ($post['region'] ?? 'macau'),
                $actor,
                $post
            );
        }
    }

    public function saveManagedForumPost(array $payload, array $actor)
    {
        $this->ensureManagedPostMetaReady();
        $postId = isset($payload['id']) ? (int) $payload['id'] : 0;
        $region = trim((string) ($payload['region'] ?? 'macau'));
        $segmentNo = isset($payload['segment_no']) ? max(1, min(3, (int) $payload['segment_no'])) : 0;
        $titlePrefix = trim((string) ($payload['title_prefix'] ?? ''));
        $titleMiddle = trim((string) ($payload['title_middle'] ?? ''));
        $titleSuffix = trim((string) ($payload['title_suffix'] ?? ''));
        $authorNickname = trim((string) ($payload['author_nickname'] ?? ''));
        $title = trim((string) ($payload['title'] ?? ''));
        if ($titlePrefix !== '' || $titleMiddle !== '') {
            $title = trim($titlePrefix . $titleMiddle);
        }
        $title = trim((string) preg_replace('/^\s*\d{1,3}期[:：]?\s*/u', '', $title));
        $excerpt = trim((string) ($payload['excerpt'] ?? ''));
        $previewContent = trim((string) ($payload['preview_content'] ?? ''));
        $fullContent = trim((string) ($payload['full_content'] ?? ''));
        $price = max(0, (int) ($payload['price'] ?? 0));
        $colorTag = trim((string) ($payload['color_tag'] ?? 'slate'));
        $status = trim((string) ($payload['status'] ?? 'published'));

        if (!in_array($region, array('macau', 'hongkong'), true)) {
            throw new RuntimeException('帖子分区无效。');
        }

        $existingPost = array();
        if ($postId > 0) {
            $existingPost = $this->managedForumPostById($postId);
            if (!$existingPost) {
                throw new RuntimeException('帖子不存在，无法继续保存。');
            }
            $this->assertManagedPostUnlockedForEdit((string) ($existingPost['region'] ?? $region), $actor, $existingPost);
        }

        $defaultTargets = $this->managedPostGeneratorDefaultTargets($region);
        $sectionId = (int) ($defaultTargets['section_id'] ?? 0);
        $categoryId = (int) ($defaultTargets['category_id'] ?? 0);
        if ($sectionId <= 0 || $categoryId <= 0) {
            throw new RuntimeException('当前地区还没有可用的默认帖子版块，无法保存帖子。');
        }

        $section = $this->managedSectionById($sectionId);
        if (!$section) {
            throw new RuntimeException('当前地区默认帖子版块不存在，请先检查论坛版块设置。');
        }

        $category = $this->managedCategoryById($categoryId);
        if (!$category) {
            throw new RuntimeException('当前地区默认帖子分类不存在，请先检查论坛分类设置。');
        }

        if ((int) $category['section_id'] !== $sectionId) {
            throw new RuntimeException('当前地区默认帖子分类和版块不匹配，请先检查论坛分类设置。');
        }

        if ((string) $section['region'] !== $region || (string) $category['region'] !== $region) {
            throw new RuntimeException('当前地区默认帖子版块配置无效，请先检查论坛版块设置。');
        }

        if ($title === '' || $fullContent === '') {
            throw new RuntimeException('帖子标题和正文不能为空。');
        }

        $this->assertManagedAuthorNicknameAllowed($authorNickname);

        if (!in_array($status, array('draft', 'pending', 'published', 'archived', 'deleted'), true)) {
            throw new RuntimeException('帖子状态无效。');
        }

        if ($excerpt === '') {
            $excerpt = truncate_text($fullContent, 60);
        }

        if ($previewContent === '') {
            $previewContent = truncate_text($fullContent, 80);
        }

        if (!in_array($colorTag, array('slate', 'red', 'green', 'gold'), true)) {
            $colorTag = 'slate';
        }

        $contentActor = $this->resolveFrontContentActor($actor);
        $now = $this->now();
        $createdAt = $now;
        $payloadCreatedAt = trim((string) ($payload['created_at'] ?? ''));
        if ($postId <= 0 && preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $payloadCreatedAt)) {
            $createdAt = $payloadCreatedAt;
        }
        $isDeleted = $status === 'deleted';
        $data = array(
            'section_id' => $sectionId,
            'category_id' => $categoryId,
            'region' => $region,
            'title' => $title,
            'excerpt' => $excerpt,
            'preview_content' => $previewContent,
            'full_content' => $fullContent,
            'price' => $price,
            'color_tag' => $colorTag,
            'status' => $status,
            'is_top_forever' => $isDeleted ? 0 : (!empty($payload['is_top_forever']) ? 1 : 0),
            'is_top_admin' => $isDeleted ? 0 : (!empty($payload['is_top_admin']) ? 1 : 0),
            'is_top_normal' => $isDeleted ? 0 : (!empty($payload['is_top_normal']) ? 1 : 0),
            'deleted_at' => $isDeleted ? $now : null,
            'updated_at' => $now,
        );
        if ($postId > 0) {
            $this->db()->execute(
                'UPDATE posts
                 SET section_id = :section_id,
                     category_id = :category_id,
                     region = :region,
                     title = :title,
                     excerpt = :excerpt,
                     preview_content = :preview_content,
                     full_content = :full_content,
                     price = :price,
                     color_tag = :color_tag,
                     status = :status,
                     is_top_forever = :is_top_forever,
                     is_top_admin = :is_top_admin,
                     is_top_normal = :is_top_normal,
                     deleted_at = :deleted_at,
                     updated_at = :updated_at
                 WHERE id = :id',
                $data + array('id' => $postId)
            );
            $action = 'update';
        } else {
            $postId = (int) $this->db()->insertGetId(
                'INSERT INTO posts (section_id, category_id, region, author_id, title, excerpt, preview_content, full_content, price, color_tag, status, is_top_forever, is_top_admin, is_top_normal, view_count, reply_count, purchase_count, created_at, updated_at, deleted_at)
                 VALUES (:section_id, :category_id, :region, :author_id, :title, :excerpt, :preview_content, :full_content, :price, :color_tag, :status, :is_top_forever, :is_top_admin, :is_top_normal, :view_count, 0, 0, :created_at, :updated_at, :deleted_at)',
                $data + array(
                    'author_id' => (int) $contentActor['id'],
                    'view_count' => isset($payload['view_count']) ? max(0, (int) $payload['view_count']) : 0,
                    'created_at' => $createdAt,
                )
            );
            $action = 'create';
        }

        $savedPost = $this->managedForumPostById($postId);
        if (!$savedPost) {
            throw new RuntimeException('帖子保存后未能回读，请稍后刷新重试。');
        }

        $this->recordOperation((int) $actor['id'], 'posts', $action, 'post', $postId, '保存帖子：' . $savedPost['title']);

        $existingTitlePrefixText = (string) (($existingPost['manage_title_prefix_text'] ?? ''));
        $existingTitleMiddleText = (string) (($existingPost['manage_title_middle_text'] ?? ''));
        $existingTitlePrefixColorMode = (string) (($existingPost['manage_title_prefix_color_mode'] ?? ''));
        $existingTitlePrefixColorValue = (string) (($existingPost['manage_title_prefix_color_value'] ?? ''));
        $existingTitleMiddleColorMode = (string) (($existingPost['manage_title_middle_color_mode'] ?? ''));
        $existingTitleMiddleColorValue = (string) (($existingPost['manage_title_middle_color_value'] ?? ''));
        $existingAuthorNicknameColorMode = (string) (($existingPost['manage_author_nickname_color_mode'] ?? ''));
        $existingAuthorNicknameColorValue = (string) (($existingPost['manage_author_nickname_color_value'] ?? ''));
        $existingTitleFontSize = (string) (($existingPost['manage_title_font_size'] ?? ''));
        $existingTitleFontWeight = (string) (($existingPost['manage_title_font_weight'] ?? ''));
        $titlePrefixText = array_key_exists('title_prefix', $payload) ? $titlePrefix : $existingTitlePrefixText;
        $titleMiddleText = array_key_exists('title_middle', $payload) ? $titleMiddle : $existingTitleMiddleText;
        $titlePrefixColorMode = array_key_exists('title_prefix_color_mode', $payload)
            ? $this->normalizeManagedTitleColorMode((string) ($payload['title_prefix_color_mode'] ?? ''))
            : $this->normalizeManagedTitleColorMode($existingTitlePrefixColorMode);
        $titlePrefixColorValue = $titlePrefixColorMode === 'fixed'
            ? $this->normalizeManagedTitleColorValue(array_key_exists('title_prefix_color_value', $payload) ? (string) ($payload['title_prefix_color_value'] ?? '') : $existingTitlePrefixColorValue)
            : '';
        $titleMiddleColorMode = array_key_exists('title_middle_color_mode', $payload)
            ? $this->normalizeManagedTitleColorMode((string) ($payload['title_middle_color_mode'] ?? ''))
            : $this->normalizeManagedTitleColorMode($existingTitleMiddleColorMode);
        $titleMiddleColorValue = $titleMiddleColorMode === 'fixed'
            ? $this->normalizeManagedTitleColorValue(array_key_exists('title_middle_color_value', $payload) ? (string) ($payload['title_middle_color_value'] ?? '') : $existingTitleMiddleColorValue)
            : '';
        $authorNicknameColorMode = array_key_exists('author_nickname_color_mode', $payload)
            ? $this->normalizeManagedTitleColorMode((string) ($payload['author_nickname_color_mode'] ?? ''))
            : $this->normalizeManagedTitleColorMode($existingAuthorNicknameColorMode);
        $authorNicknameColorValue = $authorNicknameColorMode === 'fixed'
            ? $this->normalizeManagedTitleColorValue(array_key_exists('author_nickname_color_value', $payload) ? (string) ($payload['author_nickname_color_value'] ?? '') : $existingAuthorNicknameColorValue)
            : '';
        $titleFontSize = array_key_exists('title_font_size', $payload)
            ? $this->normalizeManagedPostGeneratorChoice((string) ($payload['title_font_size'] ?? ''), array('12', '13', '14', '15', '16', '17', '18', '20', '22', '24'))
            : $this->normalizeManagedPostGeneratorChoice($existingTitleFontSize, array('12', '13', '14', '15', '16', '17', '18', '20', '22', '24'));
        $titleFontWeight = array_key_exists('title_font_weight', $payload)
            ? $this->normalizeManagedPostGeneratorChoice((string) ($payload['title_font_weight'] ?? ''), array('400', '500', '600', '700', '800', '900'))
            : $this->normalizeManagedPostGeneratorChoice($existingTitleFontWeight, array('400', '500', '600', '700', '800', '900'));
        if (isset($payload['post_kind'])) {
            $managedPostKind = (string) $payload['post_kind'];
        } elseif (array_key_exists('price', $payload)) {
            $managedPostKind = $price > 0 ? 'data' : 'normal';
        } else {
            $managedPostKind = (string) (($existingPost['manage_post_kind'] ?? ($price > 0 ? 'data' : 'normal')));
        }

        $metaPayload = array(
            'segment_no' => $segmentNo > 0 ? $segmentNo : (int) (($existingPost['manage_segment_no'] ?? 1)),
            'segment_sort' => isset($payload['segment_sort']) ? (int) $payload['segment_sort'] : (int) (($existingPost['manage_segment_sort'] ?? $postId)),
            'post_kind' => $managedPostKind,
            'is_fake' => !empty($payload['is_fake']) ? 1 : (int) (($existingPost['manage_is_fake'] ?? 0)),
            'recent_result_log' => isset($payload['recent_result_log']) ? (string) $payload['recent_result_log'] : (string) (($existingPost['manage_recent_result_log'] ?? '')),
            'fake_buyer_count' => isset($payload['fake_buyer_count']) ? (int) $payload['fake_buyer_count'] : (int) (($existingPost['manage_fake_buyer_count'] ?? 0)),
            'is_hidden' => !empty($payload['is_hidden']) ? 1 : (int) (($existingPost['manage_is_hidden'] ?? 0)),
            'is_encrypted' => !empty($payload['is_encrypted']) ? 1 : (int) (($existingPost['manage_is_encrypted'] ?? 0)),
            'auto_update_mode' => isset($payload['auto_update_mode']) ? (string) $payload['auto_update_mode'] : (string) (($existingPost['manage_auto_update_mode'] ?? 'none')),
            'auto_update_content' => isset($payload['auto_update_content']) ? (string) $payload['auto_update_content'] : (string) (($existingPost['manage_auto_update_content'] ?? '')),
            'manual_material' => isset($payload['manual_material'])
                ? (string) $payload['manual_material']
                : (array_key_exists('full_content', $payload) ? $fullContent : (string) (($existingPost['manage_manual_material'] ?? ''))),
            'author_nickname' => array_key_exists('author_nickname', $payload) ? $authorNickname : (string) (($existingPost['manage_author_nickname'] ?? '')),
            'title_prefix_text' => $titlePrefixText,
            'title_middle_text' => $titleMiddleText,
            'title_prefix_color_mode' => $titlePrefixColorMode,
            'title_prefix_color_value' => $titlePrefixColorValue,
            'title_middle_color_mode' => $titleMiddleColorMode,
            'title_middle_color_value' => $titleMiddleColorValue,
            'author_nickname_color_mode' => $authorNicknameColorMode,
            'author_nickname_color_value' => $authorNicknameColorValue,
            'title_font_size' => $titleFontSize,
            'title_font_weight' => $titleFontWeight,
        );
        $this->saveManagedPostMeta($postId, $metaPayload);
        $this->clearManagedPostSelectOptionsCache();
        $shouldSeedAutoReplies = $status === 'published'
            && (int) ($metaPayload['is_hidden'] ?? 0) !== 1
            && ($action === 'create' || (string) ($existingPost['status'] ?? '') !== 'published');
        if ($shouldSeedAutoReplies) {
            $this->app->posts()->seedAutoRepliesForPost($postId);
            $savedPost = $this->managedForumPostById($postId) ?: $savedPost;
        }

        return $savedPost;
    }

    protected function createGeneratedManagedForumPost(array $payload, array $actor)
    {
        $this->ensureManagedPostMetaReady();
        $region = trim((string) ($payload['region'] ?? 'macau'));
        if (!in_array($region, array('macau', 'hongkong'), true)) {
            throw new RuntimeException('帖子分区无效。');
        }

        $sectionId = (int) ($payload['section_id'] ?? 0);
        $categoryId = (int) ($payload['category_id'] ?? 0);
        $title = trim((string) ($payload['title'] ?? ''));
        $fullContent = trim((string) ($payload['full_content'] ?? ''));
        if ($title === '' || $fullContent === '') {
            throw new RuntimeException('帖子标题和正文不能为空。');
        }

        $authorNickname = trim((string) ($payload['author_nickname'] ?? ''));
        $this->assertManagedAuthorNicknameAllowed($authorNickname);

        $status = trim((string) ($payload['status'] ?? 'published'));
        if (!in_array($status, array('draft', 'pending', 'published', 'archived', 'deleted'), true)) {
            throw new RuntimeException('帖子状态无效。');
        }

        $colorTag = trim((string) ($payload['color_tag'] ?? 'slate'));
        if (!in_array($colorTag, array('slate', 'red', 'green', 'gold'), true)) {
            $colorTag = 'slate';
        }

        $price = max(0, (int) ($payload['price'] ?? 0));
        $excerpt = trim((string) ($payload['excerpt'] ?? ''));
        $previewContent = trim((string) ($payload['preview_content'] ?? ''));
        if ($excerpt === '') {
            $excerpt = truncate_text($fullContent, 60);
        }
        if ($previewContent === '') {
            $previewContent = truncate_text($fullContent, 80);
        }

        $contentActor = $this->resolveFrontContentActor($actor);
        $now = $this->now();
        $createdAt = $now;
        $payloadCreatedAt = trim((string) ($payload['created_at'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $payloadCreatedAt)) {
            $createdAt = $payloadCreatedAt;
        }
        $isDeleted = $status === 'deleted';
        $postId = (int) $this->db()->insertGetId(
            'INSERT INTO posts (section_id, category_id, region, author_id, title, excerpt, preview_content, full_content, price, color_tag, status, is_top_forever, is_top_admin, is_top_normal, view_count, reply_count, purchase_count, created_at, updated_at, deleted_at)
             VALUES (:section_id, :category_id, :region, :author_id, :title, :excerpt, :preview_content, :full_content, :price, :color_tag, :status, :is_top_forever, :is_top_admin, :is_top_normal, :view_count, 0, 0, :created_at, :updated_at, :deleted_at)',
            array(
                'section_id' => $sectionId,
                'category_id' => $categoryId,
                'region' => $region,
                'author_id' => (int) ($contentActor['id'] ?? 0),
                'title' => trim((string) preg_replace('/^\s*\d{1,3}期[:：]?\s*/u', '', $title)),
                'excerpt' => $excerpt,
                'preview_content' => $previewContent,
                'full_content' => $fullContent,
                'price' => $price,
                'color_tag' => $colorTag,
                'status' => $status,
                'is_top_forever' => $isDeleted ? 0 : (!empty($payload['is_top_forever']) ? 1 : 0),
                'is_top_admin' => $isDeleted ? 0 : (!empty($payload['is_top_admin']) ? 1 : 0),
                'is_top_normal' => $isDeleted ? 0 : (!empty($payload['is_top_normal']) ? 1 : 0),
                'view_count' => isset($payload['view_count']) ? max(0, (int) $payload['view_count']) : 0,
                'created_at' => $createdAt,
                'updated_at' => $now,
                'deleted_at' => $isDeleted ? $now : null,
            )
        );

        if ($postId <= 0) {
            throw new RuntimeException('帖子保存失败，请稍后重试。');
        }

        $postForMeta = array(
            'id' => $postId,
            'price' => $price,
        );
        $this->insertGeneratedManagedPostMeta($postId, array(
            'segment_no' => max(1, min(3, (int) ($payload['segment_no'] ?? 1))),
            'segment_sort' => isset($payload['segment_sort']) ? (int) $payload['segment_sort'] : $postId,
            'post_kind' => isset($payload['post_kind']) ? (string) $payload['post_kind'] : ($price > 0 ? 'data' : 'normal'),
            'is_fake' => !empty($payload['is_fake']) ? 1 : 0,
            'recent_result_log' => isset($payload['recent_result_log']) ? (string) $payload['recent_result_log'] : '',
            'fake_buyer_count' => isset($payload['fake_buyer_count']) ? (int) $payload['fake_buyer_count'] : 0,
            'is_hidden' => !empty($payload['is_hidden']) ? 1 : 0,
            'is_encrypted' => !empty($payload['is_encrypted']) ? 1 : 0,
            'auto_update_mode' => isset($payload['auto_update_mode']) ? (string) $payload['auto_update_mode'] : 'none',
            'auto_update_content' => isset($payload['auto_update_content']) ? (string) $payload['auto_update_content'] : '',
            'manual_material' => isset($payload['manual_material']) ? (string) $payload['manual_material'] : $fullContent,
            'author_nickname' => $authorNickname,
            'title_prefix_text' => isset($payload['title_prefix']) ? (string) $payload['title_prefix'] : '',
            'title_middle_text' => isset($payload['title_middle']) ? (string) $payload['title_middle'] : '',
            'title_prefix_color_mode' => isset($payload['title_prefix_color_mode']) ? (string) $payload['title_prefix_color_mode'] : '',
            'title_prefix_color_value' => isset($payload['title_prefix_color_value']) ? (string) $payload['title_prefix_color_value'] : '',
            'title_middle_color_mode' => isset($payload['title_middle_color_mode']) ? (string) $payload['title_middle_color_mode'] : '',
            'title_middle_color_value' => isset($payload['title_middle_color_value']) ? (string) $payload['title_middle_color_value'] : '',
            'author_nickname_color_mode' => isset($payload['author_nickname_color_mode']) ? (string) $payload['author_nickname_color_mode'] : '',
            'author_nickname_color_value' => isset($payload['author_nickname_color_value']) ? (string) $payload['author_nickname_color_value'] : '',
            'title_font_size' => isset($payload['title_font_size']) ? (string) $payload['title_font_size'] : '',
            'title_font_weight' => isset($payload['title_font_weight']) ? (string) $payload['title_font_weight'] : '',
        ), $postForMeta);

        if ($status === 'published' && empty($payload['is_hidden'])) {
            $this->app->posts()->seedAutoRepliesForPost($postId);
        }

        return array(
            'id' => $postId,
            'title' => trim((string) preg_replace('/^\s*\d{1,3}期[:：]?\s*/u', '', $title)),
            'status' => $status,
        );
    }

    public function processManagedForumPostAction($postId, $action, array $payload, array $actor)
    {
        $postId = (int) $postId;
        if ($postId <= 0) {
            throw new RuntimeException('帖子不存在。');
        }

        $this->ensureManagedPostMetaReady();
        $post = $this->managedForumPostById($postId);
        if (!$post) {
            throw new RuntimeException('帖子不存在。');
        }

        if (in_array((string) $action, array(
            'refresh_post',
            'set_auto_random',
            'set_auto_specified',
            'set_auto_none',
            'save_current_issue_content',
            'save_current_issue_price',
            'save_current_issue_material',
        ), true)) {
            $this->assertManagedPostUnlockedForEdit((string) ($post['region'] ?? 'macau'), $actor, $post);
        }

        $metaMap = $this->listManagedPostMetaByIds(array($postId));
        $meta = $metaMap[$postId] ?? $this->defaultManagedPostMeta($post);
        $db = $this->db();
        $now = $this->now();
        $summary = '';

        switch ((string) $action) {
            case 'toggle_fake':
                $nextValue = (int) ($meta['is_fake'] ?? 0) === 1 ? 0 : 1;
                $meta = $this->saveManagedPostMeta($postId, array('is_fake' => $nextValue));
                $summary = ($nextValue === 1 ? '设为假贴：' : '取消假贴：') . (string) $post['title'];
                break;

            case 'toggle_hidden':
                $nextValue = (int) ($meta['is_hidden'] ?? 0) === 1 ? 0 : 1;
                $meta = $this->saveManagedPostMeta($postId, array('is_hidden' => $nextValue));
                $summary = ($nextValue === 1 ? '隐藏帖子：' : '取消隐藏：') . (string) $post['title'];
                break;

            case 'toggle_encrypt':
                $nextValue = (int) ($meta['is_encrypted'] ?? 0) === 1 ? 0 : 1;
                $meta = $this->saveManagedPostMeta($postId, array('is_encrypted' => $nextValue));
                $summary = ($nextValue === 1 ? '加密帖子：' : '解除加密：') . (string) $post['title'];
                break;

            case 'set_segment_no':
                $nextSegmentNo = max(1, min(3, (int) ($payload['value'] ?? 1)));
                $meta = $this->saveManagedPostMeta($postId, array('segment_no' => $nextSegmentNo));
                $summary = '设置高手区' . $nextSegmentNo . '：' . (string) $post['title'];
                break;

            case 'set_segment_sort':
                $nextSegmentSort = max(1, min(5, (int) ($payload['value'] ?? 1)));
                $meta = $this->saveManagedPostMeta($postId, array('segment_sort' => $nextSegmentSort));
                $summary = '设置排序' . $nextSegmentSort . '：' . (string) $post['title'];
                break;

            case 'add_buyer':
                $increase = max(1, (int) ($payload['buyer_count'] ?? $payload['value'] ?? 1));
                $meta = $this->saveManagedPostMeta($postId, array(
                    'fake_buyer_count' => (int) ($meta['fake_buyer_count'] ?? 0) + $increase,
                ));
                $summary = '增加购买人数 +' . $increase . '：' . (string) $post['title'];
                break;

            case 'mark_result':
                $markLabel = trim((string) ($payload['mark'] ?? $payload['value'] ?? '准'));
                if (!in_array($markLabel, array('准', '错'), true)) {
                    $markLabel = '准';
                }
                $meta = $this->saveManagedPostMeta($postId, array(
                    'recent_result_log' => $this->pushManagedResultLog(
                        (string) ($meta['recent_result_log'] ?? ''),
                        $this->latestManagedIssueTail((string) ($post['region'] ?? 'macau')) . $markLabel
                    ),
                ));
                $summary = '标注对错[' . $markLabel . ']：' . (string) $post['title'];
                break;

            case 'refresh_post':
                $meta = $this->applyManagedPostContentUpdate($postId, $post, $meta);
                $summary = '刷新帖子内容：' . (string) $post['title'];
                break;

            case 'set_auto_random':
                $meta = $this->saveManagedPostMeta($postId, array('auto_update_mode' => 'random'));
                $meta = $this->applyManagedPostContentUpdate($postId, $post, $meta);
                $summary = '设为随机自动改料：' . (string) $post['title'];
                break;

            case 'set_auto_specified':
                $content = trim((string) ($payload['content'] ?? $payload['value'] ?? ''));
                $meta = $this->saveManagedPostMeta($postId, array(
                    'auto_update_mode' => 'specified',
                    'auto_update_content' => $content,
                    'manual_material' => $content,
                ));
                $meta = $this->applyManagedPostContentUpdate($postId, $post, $meta);
                $summary = '设为指定自动改料：' . (string) $post['title'];
                break;

            case 'set_auto_none':
                $meta = $this->saveManagedPostMeta($postId, array('auto_update_mode' => 'none'));
                $meta = $this->applyManagedPostContentUpdate($postId, $post, $meta);
                $summary = '更新下期无资料：' . (string) $post['title'];
                break;

            case 'save_waiting_display_content':
                $waitingDisplayContent = str_replace(
                    array("\r\n", "\r"),
                    "\n",
                    trim((string) ($payload['waiting_display_content'] ?? ''))
                );
                if ($waitingDisplayContent === '') {
                    throw new RuntimeException('资料更新状态正文不能为空。');
                }
                $waitingDisplayLength = function_exists('mb_strlen')
                    ? mb_strlen($waitingDisplayContent, 'UTF-8')
                    : strlen($waitingDisplayContent);
                if ($waitingDisplayLength > 300) {
                    throw new RuntimeException('资料更新状态正文不能超过 300 个字符。');
                }
                $this->saveManagedPostWaitingDisplayContent(
                    (string) ($post['region'] ?? 'macau'),
                    $waitingDisplayContent
                );
                $summary = '保存资料更新状态正文：' . (string) ($post['region'] ?? 'macau');
                break;

            case 'save_current_issue_content':
            case 'save_current_issue_price':
            case 'save_current_issue_material':
                $currentIssueSaveAction = (string) $action;
                $saveCurrentIssuePriceOnly = $currentIssueSaveAction === 'save_current_issue_price';
                $saveCurrentIssueContentOnly = $currentIssueSaveAction === 'save_current_issue_content';
                $issueTail = preg_replace('/\D+/', '', (string) ($payload['value'] ?? ''));
                if ($issueTail === '') {
                    throw new RuntimeException('当前期数不能为空。');
                }
                $issueTail = str_pad(substr($issueTail, -3), 3, '0', STR_PAD_LEFT);
                $priceText = $saveCurrentIssueContentOnly
                    ? (string) max(0, (int) ($post['price'] ?? 0))
                    : trim((string) ($payload['price'] ?? (string) ($post['price'] ?? '0')));
                if (!preg_match('/^\d{1,9}$/', $priceText)) {
                    throw new RuntimeException('帖子出售价格必须是 0 到 999999999 的整数。');
                }
                $submittedIssueContent = trim((string) ($payload['content'] ?? ''));
                $forecastContent = array();
                if ($saveCurrentIssuePriceOnly) {
                    $issueContent = (string) ($post['full_content'] ?? '');
                } elseif ($submittedIssueContent !== '') {
                    $issueContent = $submittedIssueContent;
                } elseif ($saveCurrentIssueContentOnly) {
                    throw new RuntimeException('当前期数资料内容不能为空。');
                } else {
                    $forecastContent = $this->managedPostGeneratorForecastContentForPost($post, $meta, $issueTail);
                    $issueContent = trim((string) ($forecastContent['content'] ?? ''));
                }

                $updatedPost = $this->app->posts()->updatePostContentByCustomerService(
                    $postId,
                    array(
                        'full_content' => $issueContent,
                        'issue_tail' => $issueTail,
                        'price' => $priceText,
                        'preserve_summary_fields' => $saveCurrentIssueContentOnly,
                    ),
                    array('id' => (int) ($actor['id'] ?? 0)),
                    'admin'
                );
                $post = is_array($updatedPost) ? $updatedPost : ($this->managedForumPostById($postId) ?: $post);
                if (!$saveCurrentIssuePriceOnly) {
                    $forecastLog = trim((string) ($forecastContent['recent_result_log'] ?? ''));
                    $savedIssueContent = trim((string) ($post['full_content'] ?? ''));
                    $metaPayload = array(
                        'manual_material' => $savedIssueContent !== '' ? $savedIssueContent : $issueContent,
                    );
                    if ($forecastLog !== '') {
                        $metaPayload['recent_result_log'] = $this->pushManagedResultLog(
                            (string) ($meta['recent_result_log'] ?? ''),
                            $forecastLog
                        );
                    }
                    $meta = $this->saveManagedPostMeta($postId, $metaPayload);
                }
                $summary = $saveCurrentIssuePriceOnly
                    ? '保存帖子出售积分：' . (string) $post['title']
                    : '保存当前期数资料内容：' . (string) $post['title'];
                break;

            case 'delete':
                $db->execute(
                    'UPDATE posts
                     SET status = :status,
                         deleted_at = :deleted_at,
                         is_top_forever = 0,
                         is_top_admin = 0,
                         is_top_normal = 0,
                         updated_at = :updated_at
                     WHERE id = :id',
                    array(
                        'status' => 'deleted',
                        'deleted_at' => $now,
                        'updated_at' => $now,
                        'id' => $postId,
                    )
                );
                $restoredPostIds = $this->app->posts()->restoreDeletedPurchasedPosts(array($postId));
                if (!empty($restoredPostIds)) {
                    $post = $this->managedForumPostById($postId) ?: $post;
                    $summary = '帖子存在会员购买记录，已自动恢复：' . (string) $post['title'];
                    break;
                }
                $this->saveManagedPostMeta($postId, array(
                    'deleted_issue_text' => $this->nextManagedIssueText((string) ($post['region'] ?? 'macau')),
                ));
                $summary = '软删除帖子：' . (string) $post['title'];
                break;

            case 'restore':
                $db->execute(
                    'UPDATE posts
                     SET status = :status,
                         deleted_at = NULL,
                         updated_at = :updated_at
                     WHERE id = :id',
                    array(
                        'status' => 'published',
                        'updated_at' => $now,
                        'id' => $postId,
                    )
                );
                $summary = '恢复帖子：' . (string) $post['title'];
                break;

            case 'purge':
                if ((string) ($post['status'] ?? '') !== 'deleted') {
                    throw new RuntimeException('仅回收站帖子支持彻底删除。');
                }

                if ($this->tableExists($db, 'post_interactions')) {
                    $db->execute('DELETE FROM post_interactions WHERE post_id = :post_id', array(
                        'post_id' => $postId,
                    ));
                }

                if ($this->tableExists($db, 'post_reports')) {
                    $db->execute('DELETE FROM post_reports WHERE post_id = :post_id', array(
                        'post_id' => $postId,
                    ));
                }

                if ($this->tableExists($db, 'page_views')) {
                    $db->execute(
                        "DELETE FROM page_views
                         WHERE route_name = 'post_detail'
                           AND path_name LIKE '%id=%'
                           AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(path_name, 'id=', -1), '&', 1) AS UNSIGNED) = :post_id",
                        array('post_id' => $postId)
                    );
                }

                if ($this->tableExists($db, 'audit_records')) {
                    $db->execute(
                        "DELETE FROM audit_records
                         WHERE target_type = 'post'
                           AND target_id = :target_id",
                        array('target_id' => $postId)
                    );
                }

                $db->execute('DELETE FROM posts WHERE id = :id', array(
                    'id' => $postId,
                ));
                $summary = '彻底删除帖子：' . (string) $post['title'];
                $this->recordOperation((int) $actor['id'], 'posts', (string) $action, 'post', $postId, $summary);
                $this->clearManagedPostSelectOptionsCache();

                return array(
                    'id' => $postId,
                    'status' => 'purged',
                );

            default:
                throw new RuntimeException('帖子操作无效。');
        }

        $this->recordOperation((int) $actor['id'], 'posts', (string) $action, 'post', $postId, $summary);
        $this->clearManagedPostSelectOptionsCache();

        $processedPost = $this->managedForumPostById($postId);
        if ((string) $action === 'save_waiting_display_content' && is_array($processedPost)) {
            $processedPost['_message'] = '资料更新状态正文已保存。';
        } elseif ((string) $action === 'save_current_issue_content' && is_array($processedPost)) {
            $processedPost['_message'] = '当前期数资料内容已保存。';
        } elseif ((string) $action === 'save_current_issue_price' && is_array($processedPost)) {
            $processedPost['_message'] = '出售积分已保存。';
        }

        return $processedPost;
    }

    protected function ensureManagedPostMetaReady()
    {
        static $managedPostMetaReady = false;
        if ($managedPostMetaReady) {
            return;
        }

        $cacheKey = 'admin_managed_post_meta_schema_ready_20260707_01';
        if ($this->app->cache()->get($cacheKey, false, 86400) === true) {
            $managedPostMetaReady = true;
            return;
        }

        $database = $this->db();
        if (!$this->tableExists($database, 'post_manage_meta')) {
            $this->runSchemaOn($database);
            $this->app->cache()->put($cacheKey, true);
            $managedPostMetaReady = true;
            return;
        }

        foreach (array('segment_no', 'segment_sort', 'post_kind', 'is_fake', 'recent_result_log', 'fake_buyer_count', 'is_hidden', 'is_encrypted', 'auto_update_mode', 'auto_update_content', 'manual_material', 'author_nickname', 'title_prefix_text', 'title_middle_text', 'title_prefix_color_mode', 'title_prefix_color_value', 'title_middle_color_mode', 'title_middle_color_value', 'author_nickname_color_mode', 'author_nickname_color_value', 'title_font_size', 'title_font_weight', 'deleted_issue_text') as $column) {
            if (!$this->columnExists($database, 'post_manage_meta', $column)) {
                $this->runSchemaOn($database);
                break;
            }
        }
        $this->app->cache()->put($cacheKey, true);
        $managedPostMetaReady = true;
    }

    protected function defaultManagedPostMeta(array $post = array())
    {
        $postId = (int) ($post['id'] ?? 0);
        $price = (int) ($post['price'] ?? 0);

        return array(
            'post_id' => $postId,
            'segment_no' => 1,
            'segment_sort' => 5,
            'post_kind' => $price > 0 ? 'data' : 'normal',
            'is_fake' => 0,
            'recent_result_log' => '',
            'fake_buyer_count' => 0,
            'is_hidden' => 0,
            'is_encrypted' => 0,
            'auto_update_mode' => 'none',
            'auto_update_content' => '',
            'manual_material' => '',
            'author_nickname' => '',
            'title_prefix_text' => '',
            'title_middle_text' => '',
            'title_prefix_color_mode' => '',
            'title_prefix_color_value' => '',
            'title_middle_color_mode' => '',
            'title_middle_color_value' => '',
            'author_nickname_color_mode' => '',
            'author_nickname_color_value' => '',
            'title_font_size' => '',
            'title_font_weight' => '',
            'deleted_issue_text' => '',
        );
    }

    protected function normalizeManagedPostMetaRow(array $row = array(), array $post = array())
    {
        $defaults = $this->defaultManagedPostMeta($post);
        $data = array_merge($defaults, $row);
        $postKind = trim((string) ($data['post_kind'] ?? $defaults['post_kind']));
        if (!in_array($postKind, array('data', 'normal'), true)) {
            $postKind = $defaults['post_kind'];
        }

        $autoMode = trim((string) ($data['auto_update_mode'] ?? 'none'));
        if (!in_array($autoMode, array('none', 'random', 'specified'), true)) {
            $autoMode = 'none';
        }
        $titlePrefixColorMode = $this->normalizeManagedTitleColorMode((string) ($data['title_prefix_color_mode'] ?? ''));
        $titlePrefixColorValue = $titlePrefixColorMode === 'fixed'
            ? $this->normalizeManagedTitleColorValue((string) ($data['title_prefix_color_value'] ?? ''))
            : '';
        $titleMiddleColorMode = $this->normalizeManagedTitleColorMode((string) ($data['title_middle_color_mode'] ?? ''));
        $titleMiddleColorRawValue = trim((string) ($data['title_middle_color_value'] ?? ''));
        $titleMiddleColorValue = $titleMiddleColorMode === 'fixed'
            || ($titleMiddleColorMode === 'daily_random' && $titleMiddleColorRawValue !== '')
            ? $this->normalizeManagedTitleColorValue($titleMiddleColorRawValue)
            : '';
        $authorNicknameColorMode = $this->normalizeManagedTitleColorMode((string) ($data['author_nickname_color_mode'] ?? ''));
        $authorNicknameColorValue = $authorNicknameColorMode === 'fixed'
            ? $this->normalizeManagedTitleColorValue((string) ($data['author_nickname_color_value'] ?? ''))
            : '';
        $titleFontSize = $this->normalizeManagedPostGeneratorChoice((string) ($data['title_font_size'] ?? ''), array('12', '13', '14', '15', '16', '17', '18', '20', '22', '24'));
        $titleFontWeight = $this->normalizeManagedPostGeneratorChoice((string) ($data['title_font_weight'] ?? ''), array('400', '500', '600', '700', '800', '900'));

        return array(
            'post_id' => (int) ($data['post_id'] ?? ($post['id'] ?? 0)),
            'segment_no' => max(1, (int) ($data['segment_no'] ?? 1)),
            'segment_sort' => max(1, min(5, (int) ($data['segment_sort'] ?? 5))),
            'post_kind' => $postKind,
            'is_fake' => !empty($data['is_fake']) ? 1 : 0,
            'recent_result_log' => trim((string) ($data['recent_result_log'] ?? '')),
            'fake_buyer_count' => max(0, (int) ($data['fake_buyer_count'] ?? 0)),
            'is_hidden' => !empty($data['is_hidden']) ? 1 : 0,
            'is_encrypted' => !empty($data['is_encrypted']) ? 1 : 0,
            'auto_update_mode' => $autoMode,
            'auto_update_content' => trim((string) ($data['auto_update_content'] ?? '')),
            'manual_material' => trim((string) ($data['manual_material'] ?? '')),
            'author_nickname' => trim((string) ($data['author_nickname'] ?? '')),
            'title_prefix_text' => trim((string) ($data['title_prefix_text'] ?? '')),
            'title_middle_text' => trim((string) ($data['title_middle_text'] ?? '')),
            'title_prefix_color_mode' => $titlePrefixColorMode,
            'title_prefix_color_value' => $titlePrefixColorValue,
            'title_middle_color_mode' => $titleMiddleColorMode,
            'title_middle_color_value' => $titleMiddleColorValue,
            'author_nickname_color_mode' => $authorNicknameColorMode,
            'author_nickname_color_value' => $authorNicknameColorValue,
            'title_font_size' => $titleFontSize,
            'title_font_weight' => $titleFontWeight,
            'deleted_issue_text' => trim((string) ($data['deleted_issue_text'] ?? '')),
        );
    }

    protected function managedPostsByIds(array $postIds)
    {
        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
        if (empty($postIds)) {
            return array();
        }

        $rows = $this->db()->fetchAll('SELECT * FROM posts WHERE id IN (' . implode(',', $postIds) . ')');
        $result = array();
        foreach ($rows as $row) {
            $result[(int) $row['id']] = $row;
        }

        return $result;
    }

    protected function listManagedPostMetaByIds(array $postIds)
    {
        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
        if (empty($postIds) || !$this->tableExists($this->db(), 'post_manage_meta')) {
            return array();
        }

        $rows = $this->db()->fetchAll('SELECT * FROM post_manage_meta WHERE post_id IN (' . implode(',', $postIds) . ')');
        $result = array();
        foreach ($rows as $row) {
            $result[(int) $row['post_id']] = $this->normalizeManagedPostMetaRow($row);
        }

        return $result;
    }

    protected function saveManagedPostMeta($postId, array $payload)
    {
        $postId = (int) $postId;
        if ($postId <= 0) {
            throw new RuntimeException('帖子不存在。');
        }

        $posts = $this->managedPostsByIds(array($postId));
        $post = $posts[$postId] ?? array('id' => $postId);
        $existingMap = $this->listManagedPostMetaByIds(array($postId));
        $existing = $existingMap[$postId] ?? $this->defaultManagedPostMeta($post);
        $data = $this->normalizeManagedPostMetaRow(array_merge($existing, $payload), $post);
        $now = $this->now();

        $this->db()->execute(
            'INSERT INTO post_manage_meta (
                post_id, segment_no, segment_sort, post_kind, is_fake, recent_result_log,
                fake_buyer_count, is_hidden, is_encrypted, auto_update_mode, auto_update_content,
                manual_material, author_nickname, title_prefix_text, title_middle_text,
                title_prefix_color_mode, title_prefix_color_value,
                title_middle_color_mode, title_middle_color_value,
                author_nickname_color_mode, author_nickname_color_value,
                title_font_size, title_font_weight, deleted_issue_text,
                created_at, updated_at
             ) VALUES (
                :post_id, :segment_no, :segment_sort, :post_kind, :is_fake, :recent_result_log,
                :fake_buyer_count, :is_hidden, :is_encrypted, :auto_update_mode, :auto_update_content,
                :manual_material, :author_nickname, :title_prefix_text, :title_middle_text,
                :title_prefix_color_mode, :title_prefix_color_value,
                :title_middle_color_mode, :title_middle_color_value,
                :author_nickname_color_mode, :author_nickname_color_value,
                :title_font_size, :title_font_weight, :deleted_issue_text,
                :created_at, :updated_at
             )
             ON DUPLICATE KEY UPDATE
                segment_no = VALUES(segment_no),
                segment_sort = VALUES(segment_sort),
                post_kind = VALUES(post_kind),
                is_fake = VALUES(is_fake),
                recent_result_log = VALUES(recent_result_log),
                fake_buyer_count = VALUES(fake_buyer_count),
                is_hidden = VALUES(is_hidden),
                is_encrypted = VALUES(is_encrypted),
                auto_update_mode = VALUES(auto_update_mode),
                auto_update_content = VALUES(auto_update_content),
                manual_material = VALUES(manual_material),
                author_nickname = VALUES(author_nickname),
                title_prefix_text = VALUES(title_prefix_text),
                title_middle_text = VALUES(title_middle_text),
                title_prefix_color_mode = VALUES(title_prefix_color_mode),
                title_prefix_color_value = VALUES(title_prefix_color_value),
                title_middle_color_mode = VALUES(title_middle_color_mode),
                title_middle_color_value = VALUES(title_middle_color_value),
                author_nickname_color_mode = VALUES(author_nickname_color_mode),
                author_nickname_color_value = VALUES(author_nickname_color_value),
                title_font_size = VALUES(title_font_size),
                title_font_weight = VALUES(title_font_weight),
                deleted_issue_text = VALUES(deleted_issue_text),
                updated_at = VALUES(updated_at)',
            array(
                'post_id' => $postId,
                'segment_no' => $data['segment_no'],
                'segment_sort' => $data['segment_sort'],
                'post_kind' => $data['post_kind'],
                'is_fake' => $data['is_fake'],
                'recent_result_log' => $data['recent_result_log'],
                'fake_buyer_count' => $data['fake_buyer_count'],
                'is_hidden' => $data['is_hidden'],
                'is_encrypted' => $data['is_encrypted'],
                'auto_update_mode' => $data['auto_update_mode'],
                'auto_update_content' => $data['auto_update_content'],
                'manual_material' => $data['manual_material'],
                'author_nickname' => $data['author_nickname'],
                'title_prefix_text' => $data['title_prefix_text'],
                'title_middle_text' => $data['title_middle_text'],
                'title_prefix_color_mode' => $data['title_prefix_color_mode'],
                'title_prefix_color_value' => $data['title_prefix_color_value'],
                'title_middle_color_mode' => $data['title_middle_color_mode'],
                'title_middle_color_value' => $data['title_middle_color_value'],
                'author_nickname_color_mode' => $data['author_nickname_color_mode'],
                'author_nickname_color_value' => $data['author_nickname_color_value'],
                'title_font_size' => $data['title_font_size'],
                'title_font_weight' => $data['title_font_weight'],
                'deleted_issue_text' => $data['deleted_issue_text'],
                'created_at' => $existingMap ? ($existing['created_at'] ?? $now) : $now,
                'updated_at' => $now,
            )
        );
        $this->clearManagedPostSelectOptionsCache();

        return $this->normalizeManagedPostMetaRow($data, $post);
    }

    protected function insertGeneratedManagedPostMeta($postId, array $payload, array $post)
    {
        $postId = (int) $postId;
        if ($postId <= 0) {
            throw new RuntimeException('帖子不存在。');
        }

        $post['id'] = $postId;
        $data = $this->normalizeManagedPostMetaRow(array_merge(
            $this->defaultManagedPostMeta($post),
            $payload,
            array('post_id' => $postId)
        ), $post);
        $now = $this->now();

        $this->db()->execute(
            'INSERT INTO post_manage_meta (
                post_id, segment_no, segment_sort, post_kind, is_fake, recent_result_log,
                fake_buyer_count, is_hidden, is_encrypted, auto_update_mode, auto_update_content,
                manual_material, author_nickname, title_prefix_text, title_middle_text,
                title_prefix_color_mode, title_prefix_color_value,
                title_middle_color_mode, title_middle_color_value,
                author_nickname_color_mode, author_nickname_color_value,
                title_font_size, title_font_weight, deleted_issue_text,
                created_at, updated_at
             ) VALUES (
                :post_id, :segment_no, :segment_sort, :post_kind, :is_fake, :recent_result_log,
                :fake_buyer_count, :is_hidden, :is_encrypted, :auto_update_mode, :auto_update_content,
                :manual_material, :author_nickname, :title_prefix_text, :title_middle_text,
                :title_prefix_color_mode, :title_prefix_color_value,
                :title_middle_color_mode, :title_middle_color_value,
                :author_nickname_color_mode, :author_nickname_color_value,
                :title_font_size, :title_font_weight, :deleted_issue_text,
                :created_at, :updated_at
             )',
            array(
                'post_id' => $postId,
                'segment_no' => $data['segment_no'],
                'segment_sort' => $data['segment_sort'],
                'post_kind' => $data['post_kind'],
                'is_fake' => $data['is_fake'],
                'recent_result_log' => $data['recent_result_log'],
                'fake_buyer_count' => $data['fake_buyer_count'],
                'is_hidden' => $data['is_hidden'],
                'is_encrypted' => $data['is_encrypted'],
                'auto_update_mode' => $data['auto_update_mode'],
                'auto_update_content' => $data['auto_update_content'],
                'manual_material' => $data['manual_material'],
                'author_nickname' => $data['author_nickname'],
                'title_prefix_text' => $data['title_prefix_text'],
                'title_middle_text' => $data['title_middle_text'],
                'title_prefix_color_mode' => $data['title_prefix_color_mode'],
                'title_prefix_color_value' => $data['title_prefix_color_value'],
                'title_middle_color_mode' => $data['title_middle_color_mode'],
                'title_middle_color_value' => $data['title_middle_color_value'],
                'author_nickname_color_mode' => $data['author_nickname_color_mode'],
                'author_nickname_color_value' => $data['author_nickname_color_value'],
                'title_font_size' => $data['title_font_size'],
                'title_font_weight' => $data['title_font_weight'],
                'deleted_issue_text' => $data['deleted_issue_text'],
                'created_at' => $now,
                'updated_at' => $now,
            )
        );

        return $data;
    }

    protected function latestManagedIssueTail($region)
    {
        $drawRegion = $region === 'hongkong' ? 'hongkong' : 'macau';
        $draw = $this->db()->fetch(
            'SELECT issue_no
             FROM lottery_draws
             WHERE region = :region
             ORDER BY CAST(issue_no AS UNSIGNED) DESC, draw_date DESC, id DESC
             LIMIT 1',
            array('region' => $drawRegion)
        );
        if (!is_array($draw)) {
            return '---';
        }

        $issueNo = trim((string) ($draw['issue_no'] ?? ''));
        if ($issueNo === '' || !preg_match('/^\d+$/', $issueNo)) {
            return '---';
        }

        $tail = strlen($issueNo) > 3 ? substr($issueNo, -3) : $issueNo;

        return str_pad($tail, 3, '0', STR_PAD_LEFT);
    }

    protected function latestManagedIssueText($region)
    {
        $tail = $this->latestManagedIssueTail($region);
        if ($tail === '---') {
            return '';
        }

        return $tail . '期';
    }

    protected function nextManagedIssueText($region)
    {
        $tail = $this->nextManagedIssueTail($region);
        if ($tail === '---') {
            return '';
        }

        return $tail . '期';
    }

    protected function backfillManagedDeletedIssueText($region = '')
    {
        $region = in_array($region, array('macau', 'hongkong'), true) ? (string) $region : '';
        if (!$this->tableExists($this->db(), 'lottery_draws')) {
            return;
        }

        $sql = 'SELECT posts.id, posts.region, posts.deleted_at, COALESCE(post_meta.deleted_issue_text, \'\') AS deleted_issue_text
                FROM posts
                LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
                WHERE posts.status = :status
                  AND posts.deleted_at IS NOT NULL';
        $params = array(
            'status' => 'deleted',
        );

        if ($region !== '') {
            $sql .= ' AND posts.region = :region';
            $params['region'] = $region;
        }

        $sql .= ' ORDER BY posts.deleted_at DESC, posts.id DESC LIMIT 150';
        $rows = $this->db()->fetchAll($sql, $params);
        if ($rows === array()) {
            return;
        }

        $issueTextCache = array();
        foreach ($rows as $row) {
            $postId = (int) ($row['id'] ?? 0);
            $postRegion = in_array((string) ($row['region'] ?? ''), array('macau', 'hongkong'), true)
                ? (string) $row['region']
                : '';
            $deletedAt = trim((string) ($row['deleted_at'] ?? ''));
            $currentIssueText = trim((string) ($row['deleted_issue_text'] ?? ''));
            if ($postId <= 0 || $postRegion === '' || $deletedAt === '') {
                continue;
            }

            $cacheKey = $postRegion . '|' . substr($deletedAt, 0, 10);
            if (!array_key_exists($cacheKey, $issueTextCache)) {
                $issueTextCache[$cacheKey] = $this->managedDeletedIssueTextFromDeletedAt($postRegion, $deletedAt);
            }

            $issueText = $issueTextCache[$cacheKey];
            if ($issueText === '' || $issueText === $currentIssueText) {
                continue;
            }

            $this->saveManagedPostMeta($postId, array(
                'deleted_issue_text' => $issueText,
            ));
        }
    }

    protected function managedDeletedIssueTextFromDeletedAt($region, $deletedAt)
    {
        $region = in_array($region, array('macau', 'hongkong'), true) ? (string) $region : '';
        $deletedAt = trim((string) $deletedAt);
        if ($region === '' || $deletedAt === '') {
            return '';
        }

        $deletedTime = strtotime($deletedAt);
        if ($deletedTime === false) {
            return '';
        }

        $draw = $this->db()->fetch(
            'SELECT issue_no
             FROM lottery_draws
             WHERE region = :region
               AND draw_date <= :draw_date
             ORDER BY draw_date DESC, CAST(issue_no AS UNSIGNED) DESC, id DESC
             LIMIT 1',
            array(
                'region' => $region,
                'draw_date' => date('Y-m-d', $deletedTime),
            )
        );
        if (!$draw || empty($draw['issue_no'])) {
            return '';
        }

        $issueNo = preg_replace('/\D+/', '', trim((string) $this->incrementIssueNo((string) ($draw['issue_no'] ?? ''))));
        if ($issueNo === '') {
            return '';
        }

        $tail = strlen($issueNo) > 3 ? substr($issueNo, -3) : $issueNo;

        return str_pad($tail, 3, '0', STR_PAD_LEFT) . '期';
    }

    protected function nextManagedIssueTail($region)
    {
        $latestTail = $this->latestManagedIssueTail($region);
        if ($latestTail === '---') {
            return '---';
        }

        $nextTail = preg_replace('/\D+/', '', (string) $this->incrementIssueNo($latestTail));
        if ($nextTail === '') {
            return '---';
        }

        return str_pad(substr($nextTail, -3), 3, '0', STR_PAD_LEFT);
    }

    protected function normalizeManagedTitleColorMode($mode)
    {
        $mode = trim((string) $mode);

        return in_array($mode, array('fixed', 'daily_random'), true) ? $mode : '';
    }

    protected function normalizeManagedTitleColorValue($value)
    {
        $value = strtoupper(trim((string) $value));
        if (!preg_match('/^#[0-9A-F]{6}$/', $value)) {
            return '#2563EB';
        }

        return $value;
    }

    protected function managedPostGeneratorTemplateGroups()
    {
        $buildItems = static function ($groupKey, array $labels) {
            $items = array();
            foreach ($labels as $index => $label) {
                $items[] = array(
                    'key' => $groupKey . '_' . ($index + 1),
                    'label' => $label,
                );
            }

            return $items;
        };

        return array(
            array(
                'key' => 'tema',
                'label' => '特码',
                'items' => $buildItems('tema', array('一码中特', '二码中特', '三码中特', '四码中特', '五码中特', '六码中特', '七码中特', '精选八码', '精选九码', '精选10码', '精选11码', '精选12码', '精选13码', '精选14码', '精选15码', '精选16码', '精选17码', '精选18码', '精选19码', '精选20码', '精选24码', '精选30码', '精选36码')),
            ),
            array(
                'key' => 'zodiac',
                'label' => '特肖',
                'items' => $buildItems('zodiac', array('一肖中特', '二肖中特', '三肖中特', '四肖中特', '五肖中特', '六肖中特', '七肖中特', '精选八肖', '精选九肖')),
            ),
            array(
                'key' => 'zodiac_code',
                'label' => '肖+码',
                'items' => array(
                    array('key' => 'zodiac_code_1', 'label' => '一肖一码'),
                    array('key' => 'zodiac_code_10', 'label' => '一肖二码'),
                    array('key' => 'zodiac_code_2', 'label' => '二肖二码'),
                    array('key' => 'zodiac_code_11', 'label' => '二肖四码'),
                    array('key' => 'zodiac_code_3', 'label' => '三肖三码'),
                    array('key' => 'zodiac_code_12', 'label' => '三肖六码'),
                    array('key' => 'zodiac_code_4', 'label' => '四肖四码'),
                    array('key' => 'zodiac_code_13', 'label' => '四肖八码'),
                    array('key' => 'zodiac_code_5', 'label' => '五肖五码'),
                    array('key' => 'zodiac_code_6', 'label' => '六肖六码'),
                    array('key' => 'zodiac_code_9', 'label' => '六肖十二码'),
                    array('key' => 'zodiac_code_7', 'label' => '七肖七码'),
                    array('key' => 'zodiac_code_8', 'label' => '八肖八码'),
                ),
            ),
            array(
                'key' => 'flat_special',
                'label' => '平特',
                'items' => $buildItems('flat_special', array('平特一肖', '平特两肖', '平特一尾', '平特二尾', '平特二连', '平特三连', '平特四连', '平特五连', '复式四连肖', '复式五连肖', '复式六连肖')),
            ),
            array(
                'key' => 'normal_code',
                'label' => '平码',
                'items' => $buildItems('normal_code', array('一码三中三', '平码一码', '一组3中3', '二组3中3', '三组3中3', '四组3中3', '六组3中3', '八组3中3', '九组3中3', '十组3中3', '12组3中3', '15组3中3', '20组3中3', '25组3中3', '30组3中3', '一组2中2', '三组2中2', '四组2中2', '六组2中2', '八组2中2', '九组2中2', '十组2中2', '12组2中2', '16组2中2', '20组2中2', '24组2中2', '30组2中2', '六码复试2中2', '七码复试2中2', '八码复试2中2', '六码复试3中3', '七码复试3中3', '八码复试3中3')),
            ),
            array(
                'key' => 'head',
                'label' => '头数',
                'items' => $buildItems('head', array('一头中特', '二头中特', '三头中特', '四头中特', '一头三码', '一头四码', '一头五码', '二头六码', '二头七码', '二头八码')),
            ),
            array(
                'key' => 'tail',
                'label' => '尾数',
                'items' => $buildItems('tail', array('一尾中特', '二尾中特', '三尾中特', '四尾中特', '五尾中特', '六尾中特', '七尾中特', '一尾两码', '二尾四码', '三尾六码', '四尾八码', '五尾十码')),
            ),
            array(
                'key' => 'wave_element',
                'label' => '波色',
                'items' => $buildItems('wave_element', array('一波中特', '二波中特', '一波五码', '一波六码', '一波八码', '二波十码', '二波12码')),
            ),
            array(
                'key' => 'double_single',
                'label' => '大小单双',
                'items' => array_merge(
                    $buildItems('double_single', array('大小中特', '单双中特', '大小五码', '大小十码', '单双五码', '单双十码')),
                    array(
                        array(
                            'key' => 'zodiac_attr_1_1',
                            'label' => '家野中特',
                        ),
                    )
                ),
            ),
            array(
                'key' => 'kills',
                'label' => '绝杀',
                'items' => $buildItems('kills', array('绝杀一肖', '绝杀二肖', '绝杀三肖', '绝杀一尾', '绝杀二尾', '绝杀三尾', '绝杀一行', '绝杀二行', '绝杀三行', '绝杀五码', '绝杀七码', '绝杀一波', '绝杀一头')),
            ),
        );
    }

    protected function managedPostGeneratorTopOptions()
    {
        return array(
            'top_1' => array(
                'label' => '置顶1',
                'summary_label' => '置顶1 出售',
                'badge' => '出售',
                'color_tag' => 'red',
                'price' => 0,
                'is_top_normal' => 1,
                'is_top_admin' => 0,
                'is_top_forever' => 0,
            ),
            'top_2' => array(
                'label' => '置顶2',
                'summary_label' => '置顶2 高级',
                'badge' => '高级',
                'color_tag' => 'green',
                'price' => 0,
                'is_top_normal' => 0,
                'is_top_admin' => 1,
                'is_top_forever' => 0,
            ),
            'top_3' => array(
                'label' => '置顶3',
                'summary_label' => '置顶3 初级',
                'badge' => '初级',
                'color_tag' => 'slate',
                'price' => 0,
                'is_top_normal' => 1,
                'is_top_admin' => 0,
                'is_top_forever' => 0,
            ),
            'top_4' => array(
                'label' => '置顶4',
                'summary_label' => '置顶4 💎',
                'badge' => '💎',
                'color_tag' => 'gold',
                'price' => 0,
                'is_top_normal' => 0,
                'is_top_admin' => 0,
                'is_top_forever' => 1,
            ),
            'top_5' => array(
                'label' => '置顶5',
                'summary_label' => '置顶5',
                'badge' => '',
                'color_tag' => 'slate',
                'price' => 0,
                'is_top_normal' => 0,
                'is_top_admin' => 0,
                'is_top_forever' => 0,
            ),
        );
    }

    protected function managedPostGeneratorDefaultTargets($region)
    {
        $region = $region === 'hongkong' ? 'hongkong' : 'macau';
        $sectionId = 0;
        $categoryId = 0;
        $sectionName = '';
        $categoryName = '';
        $sections = $this->sectionOptions($region);

        foreach ($sections as $section) {
            $candidateSectionId = (int) ($section['id'] ?? 0);
            if ($candidateSectionId <= 0) {
                continue;
            }
            $categories = $this->categoryOptions($candidateSectionId, $region);
            if (!$categories) {
                continue;
            }
            $sectionId = $candidateSectionId;
            $sectionName = (string) ($section['name'] ?? '');
            $categoryId = (int) ($categories[0]['id'] ?? 0);
            $categoryName = (string) ($categories[0]['name'] ?? '');
            break;
        }

        return array(
            'section_id' => $sectionId,
            'section_name' => $sectionName,
            'category_id' => $categoryId,
            'category_name' => $categoryName,
        );
    }

    protected function managedPostGeneratorChoiceSummary(array $selected, array $labels, $prefix)
    {
        $parts = array();
        foreach ($selected as $value) {
            $value = (string) $value;
            if (isset($labels[$value])) {
                $parts[] = (string) $labels[$value];
            }
        }
        $parts = array_values(array_unique(array_filter($parts)));
        if (empty($parts)) {
            return '';
        }

        return trim((string) $prefix) . '：' . implode('、', $parts);
    }

    protected function managedPostGeneratorNextSort($region, $segmentNo)
    {
        $row = $this->db()->fetch(
            'SELECT COALESCE(MAX(COALESCE(NULLIF(post_meta.segment_sort, 0), posts.id)), 0) AS max_sort
             FROM posts
             LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
             WHERE posts.region = :region
               AND COALESCE(post_meta.segment_no, 1) = :segment_no',
            array(
                'region' => $region === 'hongkong' ? 'hongkong' : 'macau',
                'segment_no' => max(1, (int) $segmentNo),
            )
        );

        return max(1, (int) ($row['max_sort'] ?? 0) + 1);
    }

    protected function pushManagedResultLog($current, $entry)
    {
        $items = array();
        foreach (preg_split('/[\s,，]+/u', trim((string) $current)) as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $items[] = $item;
            }
        }

        array_unshift($items, trim((string) $entry));
        $items = array_values(array_unique(array_filter($items)));
        $items = array_slice($items, 0, 10);

        return implode(',', $items);
    }

    protected function applyManagedPostContentUpdate($postId, array $post, array $meta)
    {
        $mode = (string) ($meta['auto_update_mode'] ?? 'none');
        $issueTail = $this->latestManagedIssueTail((string) ($post['region'] ?? 'macau'));
        $specified = trim((string) ($meta['auto_update_content'] ?? ''));
        $material = trim((string) ($meta['manual_material'] ?? ''));
        $blankContent = "资料等待更新中··· ···\n关注本站，精彩无限，中奖根本停不下来······";

        if ($mode === 'random') {
            $candidates = array(
                '研究中，稍后更新……',
                '本期资料更新中，正在补充内容……',
                '第' . $issueTail . '期资料整理中，晚些发布。',
                '本期资料已启动更新，请稍后刷新查看。',
            );
            $content = $candidates[array_rand($candidates)];
        } elseif ($mode === 'specified') {
            $content = $specified !== '' ? $specified : ($material !== '' ? $material : $blankContent);
        } else {
            $content = $blankContent;
        }

        $this->db()->execute(
            'UPDATE posts
             SET excerpt = :excerpt,
                 preview_content = :preview_content,
                 full_content = :full_content,
                 updated_at = :updated_at
             WHERE id = :id',
            array(
                'excerpt' => truncate_text($content, 60),
                'preview_content' => truncate_text($content, 80),
                'full_content' => $content,
                'updated_at' => $this->now(),
                'id' => (int) $postId,
            )
        );

        return $this->saveManagedPostMeta($postId, array(
            'manual_material' => $material !== '' ? $material : $content,
        ));
    }

    public function listManagedComments(array $filters = array())
    {
        $params = array();
        $where = ' FROM replies
                   INNER JOIN posts ON posts.id = replies.post_id
                   INNER JOIN users ON users.id = replies.user_id
                   WHERE 1 = 1';
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $region = trim((string) ($filters['region'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $postId = (int) ($filters['post_id'] ?? 0);
        $pageNo = (int) ($filters['page_no'] ?? 1);

        if ($keyword !== '') {
            $where .= ' AND (replies.content LIKE :keyword_content OR posts.title LIKE :keyword_post_title OR users.username LIKE :keyword_username)';
            $params['keyword_content'] = '%' . $keyword . '%';
            $params['keyword_post_title'] = '%' . $keyword . '%';
            $params['keyword_username'] = '%' . $keyword . '%';
        }

        if (in_array($region, array('macau', 'hongkong'), true)) {
            $where .= ' AND posts.region = :region';
            $params['region'] = $region;
        }

        if (in_array($status, array('published', 'pending', 'hidden'), true)) {
            $where .= ' AND replies.status = :status';
            $params['status'] = $status;
        }

        if ($postId > 0) {
            $where .= ' AND replies.post_id = :post_id';
            $params['post_id'] = $postId;
        }

        $page = $this->paginateAdminQuery(
            'SELECT COUNT(*) AS total_count' . $where,
            'SELECT replies.*, posts.title AS post_title, posts.region, users.username'
                . $where .
                ' ORDER BY replies.created_at DESC',
            $params,
            $pageNo,
            20
        );
        $page['items'] = $this->app->posts()->attachDisplayTitlePayloads((array) ($page['items'] ?? array()));

        return $page;
    }

    public function setManagedCommentStatus($commentId, $status, array $actor)
    {
        if (!in_array($status, array('published', 'pending', 'hidden'), true)) {
            throw new RuntimeException('评论状态无效。');
        }

        $comment = $this->db()->fetch(
            'SELECT replies.*, posts.title AS post_title
             FROM replies
             INNER JOIN posts ON posts.id = replies.post_id
             WHERE replies.id = :id
             LIMIT 1',
            array('id' => (int) $commentId)
        );

        if (!$comment) {
            throw new RuntimeException('评论不存在，无法更新状态。');
        }

        $this->db()->execute(
            'UPDATE replies SET status = :status WHERE id = :id',
            array(
                'status' => $status,
                'id' => (int) $commentId,
            )
        );
        $this->refreshManagedPostReplyCount((int) $comment['post_id']);
        $this->recordOperation((int) $actor['id'], 'comments', 'status_' . $status, 'reply', (int) $commentId, '调整评论状态：' . $comment['post_title']);
    }

    public function interactionTypeLabels()
    {
        return $this->managedInteractionTypeOptions();
    }

    public function reportTypeLabels()
    {
        return $this->managedReportTypeOptions();
    }

    public function reportPunishmentLabels()
    {
        return $this->managedReportPunishmentChoices();
    }

    public function managedReportPunishmentOptions()
    {
        return $this->managedReportPunishmentChoices();
    }

    public function managedInteractionTypeOptions()
    {
        return array(
            'like' => '点赞',
            'favorite' => '收藏',
        );
    }

    public function managedReportTypeOptions()
    {
        return array(
            'spam' => '广告垃圾',
            'illegal' => '违法违规',
            'abuse' => '辱骂攻击',
            'other' => '其他问题',
        );
    }

    public function managedReportPunishmentChoices()
    {
        return array(
            'none' => '仅更新举报状态',
            'hide_post' => '处理并转为待审核',
            'archive_post' => '处理并归档帖子',
            'delete_post' => '处理并软删除帖子',
            'ban_author' => '处理并封禁作者',
        );
    }

    public function writeManagedExceptionLog(\Throwable $exception, $module, $scene, $operatorType = 'system', $operatorId = 0)
    {
        if (!$this->tableExists($this->db(), 'system_exception_logs')) {
            return;
        }

        $requestPath = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $requestPath = function_exists('mb_substr')
            ? mb_substr($requestPath, 0, 150, 'UTF-8')
            : substr($requestPath, 0, 150);

        $this->db()->execute(
            'INSERT INTO system_exception_logs (level, module, scene, message, trace_excerpt, request_path, request_data, operator_type, operator_id, ip, created_at)
             VALUES (:level, :module, :scene, :message, :trace_excerpt, :request_path, :request_data, :operator_type, :operator_id, :ip, :created_at)',
            array(
                'level' => $exception instanceof RuntimeException ? 'warning' : 'error',
                'module' => (string) $module,
                'scene' => (string) $scene,
                'message' => truncate_text((string) $exception->getMessage(), 240),
                'trace_excerpt' => mb_substr($exception->getTraceAsString(), 0, 3000, 'UTF-8'),
                'request_path' => $requestPath,
                'request_data' => json_encode($_POST, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'operator_type' => (string) $operatorType,
                'operator_id' => (int) $operatorId,
                'ip' => Security::ipAddress(),
                'created_at' => $this->now(),
            )
        );
    }

    public function recordManagedOperation($adminId, $module, $action, $targetType, $targetId, $summary)
    {
        $this->recordOperation((int) $adminId, (string) $module, (string) $action, (string) $targetType, (int) $targetId, (string) $summary);
    }

    public function processManagedPostReportStatus($reportId, $status, $handleResult, array $actor, $punishAction = 'none')
    {
        if (!$this->tableExists($this->db(), 'post_reports')) {
            throw new RuntimeException('当前数据库还没有帖子举报表，请先刷新后台完成补表。');
        }

        if (!in_array($status, array('pending', 'processed', 'ignored'), true)) {
            throw new RuntimeException('举报状态无效。');
        }

        $report = $this->managedPostReportById($reportId);
        if (!$report) {
            throw new RuntimeException('举报记录不存在，无法继续处理。');
        }

        $punishmentOptions = $this->managedReportPunishmentChoices();
        if (!isset($punishmentOptions[$punishAction])) {
            throw new RuntimeException('举报处罚动作无效。');
        }

        if ($status !== 'processed') {
            $punishAction = 'none';
        }

        $resultText = trim((string) $handleResult);
        if ($status === 'ignored' && $resultText === '') {
            $resultText = '后台已忽略该举报';
        }

        if ($status === 'processed') {
            $punishmentRemark = $this->applyManagedReportAction($report, $punishAction, $actor);
            if ($punishmentRemark !== '') {
                $resultText = $resultText === '' ? $punishmentRemark : $resultText . '；' . $punishmentRemark;
            }
            if ($resultText === '') {
                $resultText = '后台已处理该举报';
            }
        }

        $this->db()->execute(
            'UPDATE post_reports
             SET status = :status,
                 handled_by = :handled_by,
                 handle_result = :handle_result,
                 updated_at = :updated_at
             WHERE id = :id',
            array(
                'status' => $status,
                'handled_by' => $status === 'pending' ? 0 : (int) $actor['id'],
                'handle_result' => $resultText,
                'updated_at' => $this->now(),
                'id' => (int) $reportId,
            )
        );

        $this->recordOperation((int) $actor['id'], 'reports', 'status_' . $status, 'post_report', (int) $reportId, '处理帖子举报：' . $report['post_title']);
    }

    protected function applyManagedReportAction(array $report, $punishAction, array $actor)
    {
        if ($punishAction === 'none') {
            return '';
        }

        $post = $this->managedForumPostById((int) ($report['post_id'] ?? 0));
        if (!$post) {
            throw new RuntimeException('举报对应的帖子不存在，无法执行处罚联动。');
        }

        $now = $this->now();

        switch ($punishAction) {
            case 'hide_post':
                $this->db()->execute(
                    'UPDATE posts
                     SET status = :status,
                         is_top_forever = 0,
                         is_top_admin = 0,
                         is_top_normal = 0,
                         deleted_at = NULL,
                         updated_at = :updated_at
                     WHERE id = :id',
                    array(
                        'status' => 'pending',
                        'updated_at' => $now,
                        'id' => (int) $post['id'],
                    )
                );
                return '已将帖子转为待审核';

            case 'archive_post':
                $this->db()->execute(
                    'UPDATE posts
                     SET status = :status,
                         is_top_forever = 0,
                         is_top_admin = 0,
                         is_top_normal = 0,
                         deleted_at = NULL,
                         updated_at = :updated_at
                     WHERE id = :id',
                    array(
                        'status' => 'archived',
                        'updated_at' => $now,
                        'id' => (int) $post['id'],
                    )
                );
                return '已将帖子归档';

            case 'delete_post':
                $this->db()->execute(
                    'UPDATE posts
                     SET status = :status,
                         is_top_forever = 0,
                         is_top_admin = 0,
                         is_top_normal = 0,
                         deleted_at = :deleted_at,
                         updated_at = :updated_at
                     WHERE id = :id',
                    array(
                        'status' => 'deleted',
                        'deleted_at' => $now,
                        'updated_at' => $now,
                        'id' => (int) $post['id'],
                    )
                );
                return '已将帖子软删除';

            case 'ban_author':
                if (!$this->tableExists($this->db(), 'user_ban_records')) {
                    throw new RuntimeException('当前数据库还没有会员封禁表，无法执行作者封禁。');
                }

                $this->db()->execute(
                    'UPDATE user_ban_records
                     SET status = 0,
                         unbanned_by = :unbanned_by,
                         unbanned_at = :unbanned_at
                     WHERE user_id = :user_id AND status = 1',
                    array(
                        'unbanned_by' => (int) $actor['id'],
                        'unbanned_at' => $now,
                        'user_id' => (int) $post['author_id'],
                    )
                );

                $this->db()->insert(
                    'INSERT INTO user_ban_records (user_id, ban_type, reason, banned_by, start_at, end_at, status, unbanned_by, unbanned_at, remark)
                     VALUES (:user_id, :ban_type, :reason, :banned_by, :start_at, :end_at, :status, 0, NULL, :remark)',
                    array(
                        'user_id' => (int) $post['author_id'],
                        'ban_type' => 'account',
                        'reason' => '帖子举报处罚联动',
                        'banned_by' => (int) $actor['id'],
                        'start_at' => $now,
                        'end_at' => null,
                        'status' => 1,
                        'remark' => '来源帖子：' . (string) $post['title'],
                    )
                );

                $this->db()->execute(
                    'UPDATE users
                     SET status = :status,
                         updated_at = :updated_at
                     WHERE id = :id',
                    array(
                        'status' => 'disabled',
                        'updated_at' => $now,
                        'id' => (int) $post['author_id'],
                    )
                );
                return '已封禁帖子作者';
        }

        throw new RuntimeException('不支持的举报处罚动作。');
    }

    public function listManagedPostInteractions(array $filters = array())
    {
        if (!$this->tableExists($this->db(), 'post_interactions')) {
            return $this->emptyPaginatedResult();
        }

        $params = array();
        $where = ' FROM post_interactions
                   INNER JOIN posts ON posts.id = post_interactions.post_id
                   INNER JOIN users ON users.id = post_interactions.user_id
                   LEFT JOIN users AS authors ON authors.id = posts.author_id
                   WHERE 1 = 1';
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $region = trim((string) ($filters['region'] ?? ''));
        $interactionType = trim((string) ($filters['interaction_type'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $postId = (int) ($filters['post_id'] ?? 0);
        $userId = (int) ($filters['user_id'] ?? 0);
        $pageNo = (int) ($filters['page_no'] ?? 1);

        if ($keyword !== '') {
            $where .= ' AND (posts.title LIKE :keyword_post_title OR users.username LIKE :keyword_username OR authors.username LIKE :keyword_author_name)';
            $params['keyword_post_title'] = '%' . $keyword . '%';
            $params['keyword_username'] = '%' . $keyword . '%';
            $params['keyword_author_name'] = '%' . $keyword . '%';
        }

        if (in_array($region, array('macau', 'hongkong'), true)) {
            $where .= ' AND posts.region = :region';
            $params['region'] = $region;
        }

        if (isset($this->managedInteractionTypeOptions()[$interactionType])) {
            $where .= ' AND post_interactions.interaction_type = :interaction_type';
            $params['interaction_type'] = $interactionType;
        }

        if (in_array($status, array('0', '1'), true)) {
            $where .= ' AND post_interactions.status = :status';
            $params['status'] = (int) $status;
        }

        if ($postId > 0) {
            $where .= ' AND post_interactions.post_id = :post_id';
            $params['post_id'] = $postId;
        }

        if ($userId > 0) {
            $where .= ' AND post_interactions.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $page = $this->paginateAdminQuery(
            'SELECT COUNT(*) AS total_count' . $where,
            'SELECT post_interactions.*,
                    posts.title AS post_title,
                    posts.region,
                    users.username AS interaction_username,
                    authors.username AS author_name'
                . $where .
                ' ORDER BY post_interactions.id DESC',
            $params,
            $pageNo,
            20
        );
        $page['items'] = $this->app->posts()->attachDisplayTitlePayloads((array) ($page['items'] ?? array()));

        return $page;
    }

    public function managedPostInteractionStats(array $filters = array())
    {
        if (!$this->tableExists($this->db(), 'post_interactions')) {
            return array(
                'active_like_count' => 0,
                'active_favorite_count' => 0,
                'post_count' => 0,
                'user_count' => 0,
            );
        }

        $params = array();
        $where = ' FROM post_interactions
                   INNER JOIN posts ON posts.id = post_interactions.post_id
                   INNER JOIN users ON users.id = post_interactions.user_id
                   LEFT JOIN users AS authors ON authors.id = posts.author_id
                   WHERE 1 = 1';
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $region = trim((string) ($filters['region'] ?? ''));
        $interactionType = trim((string) ($filters['interaction_type'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $postId = (int) ($filters['post_id'] ?? 0);
        $userId = (int) ($filters['user_id'] ?? 0);

        if ($keyword !== '') {
            $where .= ' AND (posts.title LIKE :keyword_post_title OR users.username LIKE :keyword_username OR authors.username LIKE :keyword_author_name)';
            $params['keyword_post_title'] = '%' . $keyword . '%';
            $params['keyword_username'] = '%' . $keyword . '%';
            $params['keyword_author_name'] = '%' . $keyword . '%';
        }

        if (in_array($region, array('macau', 'hongkong'), true)) {
            $where .= ' AND posts.region = :region';
            $params['region'] = $region;
        }

        if (isset($this->managedInteractionTypeOptions()[$interactionType])) {
            $where .= ' AND post_interactions.interaction_type = :interaction_type';
            $params['interaction_type'] = $interactionType;
        }

        if (in_array($status, array('0', '1'), true)) {
            $where .= ' AND post_interactions.status = :status';
            $params['status'] = (int) $status;
        }

        if ($postId > 0) {
            $where .= ' AND post_interactions.post_id = :post_id';
            $params['post_id'] = $postId;
        }

        if ($userId > 0) {
            $where .= ' AND post_interactions.user_id = :user_id';
            $params['user_id'] = $userId;
        }

        $row = $this->db()->fetch(
            'SELECT
                SUM(CASE WHEN post_interactions.status = 1 AND post_interactions.interaction_type = \'like\' THEN 1 ELSE 0 END) AS active_like_count,
                SUM(CASE WHEN post_interactions.status = 1 AND post_interactions.interaction_type = \'favorite\' THEN 1 ELSE 0 END) AS active_favorite_count,
                COUNT(DISTINCT post_interactions.post_id) AS post_count,
                COUNT(DISTINCT post_interactions.user_id) AS user_count'
            . $where,
            $params
        );

        return array(
            'active_like_count' => (int) ($row['active_like_count'] ?? 0),
            'active_favorite_count' => (int) ($row['active_favorite_count'] ?? 0),
            'post_count' => (int) ($row['post_count'] ?? 0),
            'user_count' => (int) ($row['user_count'] ?? 0),
        );
    }

    public function managedPostInteractionById($interactionId)
    {
        if (!$this->tableExists($this->db(), 'post_interactions')) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT post_interactions.*,
                    posts.title AS post_title,
                    posts.region,
                    users.username AS interaction_username
             FROM post_interactions
             INNER JOIN posts ON posts.id = post_interactions.post_id
             INNER JOIN users ON users.id = post_interactions.user_id
             WHERE post_interactions.id = :id
             LIMIT 1',
            array('id' => (int) $interactionId)
        );
    }

    public function saveManagedPostInteraction(array $payload, array $actor)
    {
        if (!$this->tableExists($this->db(), 'post_interactions')) {
            throw new RuntimeException('当前数据库还没有帖子互动表，请先刷新后台完成补表。');
        }

        $interactionId = isset($payload['id']) ? (int) $payload['id'] : 0;
        $postId = (int) ($payload['post_id'] ?? 0);
        $userId = (int) ($payload['user_id'] ?? 0);
        $interactionType = trim((string) ($payload['interaction_type'] ?? 'like'));
        $status = !empty($payload['status']) ? 1 : 0;

        if ($postId <= 0 || !$this->managedForumPostById($postId)) {
            throw new RuntimeException('请选择有效的帖子。');
        }

        if ($userId <= 0 || !$this->managedUserById($userId)) {
            throw new RuntimeException('请选择有效的会员。');
        }

        if (!isset($this->managedInteractionTypeOptions()[$interactionType])) {
            throw new RuntimeException('互动类型无效。');
        }

        $existing = $this->db()->fetch(
            'SELECT id FROM post_interactions
             WHERE post_id = :post_id AND user_id = :user_id AND interaction_type = :interaction_type AND id <> :id
             LIMIT 1',
            array(
                'post_id' => $postId,
                'user_id' => $userId,
                'interaction_type' => $interactionType,
                'id' => $interactionId,
            )
        );

        if ($existing) {
            throw new RuntimeException('该会员对该帖子已经存在同类型互动记录，请直接编辑原记录。');
        }

        $now = $this->now();
        if ($interactionId > 0) {
            $this->db()->execute(
                'UPDATE post_interactions
                 SET post_id = :post_id,
                     user_id = :user_id,
                     interaction_type = :interaction_type,
                     status = :status,
                     updated_at = :updated_at
                 WHERE id = :id',
                array(
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'interaction_type' => $interactionType,
                    'status' => $status,
                    'updated_at' => $now,
                    'id' => $interactionId,
                )
            );
            $action = 'update';
        } else {
            $interactionId = (int) $this->db()->insertGetId(
                'INSERT INTO post_interactions (post_id, user_id, interaction_type, status, created_at, updated_at)
                 VALUES (:post_id, :user_id, :interaction_type, :status, :created_at, :updated_at)',
                array(
                    'post_id' => $postId,
                    'user_id' => $userId,
                    'interaction_type' => $interactionType,
                    'status' => $status,
                    'created_at' => $now,
                    'updated_at' => $now,
                )
            );
            $action = 'create';
        }

        $savedInteraction = $this->managedPostInteractionById($interactionId);
        if (!$savedInteraction) {
            throw new RuntimeException('帖子互动保存后未能回读，请刷新后重试。');
        }

        $this->recordOperation((int) $actor['id'], 'interactions', $action, 'post_interaction', $interactionId, '保存帖子互动：' . $savedInteraction['post_title']);

        return $savedInteraction;
    }

    public function listManagedPostReports(array $filters = array())
    {
        if (!$this->tableExists($this->db(), 'post_reports')) {
            return $this->emptyPaginatedResult();
        }

        $params = array();
        $where = ' FROM post_reports
                   INNER JOIN posts ON posts.id = post_reports.post_id
                   INNER JOIN users ON users.id = post_reports.reporter_id
                   LEFT JOIN users AS authors ON authors.id = posts.author_id
                   LEFT JOIN admin_users ON admin_users.id = post_reports.handled_by
                   WHERE 1 = 1';
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $region = trim((string) ($filters['region'] ?? ''));
        $reportType = trim((string) ($filters['report_type'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $postId = (int) ($filters['post_id'] ?? 0);
        $reporterId = (int) ($filters['reporter_id'] ?? 0);
        $pageNo = (int) ($filters['page_no'] ?? 1);

        if ($keyword !== '') {
            $where .= ' AND (
                posts.title LIKE :keyword_post_title
                OR users.username LIKE :keyword_username
                OR post_reports.content LIKE :keyword_content
                OR post_reports.handle_result LIKE :keyword_handle_result
            )';
            $params['keyword_post_title'] = '%' . $keyword . '%';
            $params['keyword_username'] = '%' . $keyword . '%';
            $params['keyword_content'] = '%' . $keyword . '%';
            $params['keyword_handle_result'] = '%' . $keyword . '%';
        }

        if (in_array($region, array('macau', 'hongkong'), true)) {
            $where .= ' AND posts.region = :region';
            $params['region'] = $region;
        }

        if (isset($this->managedReportTypeOptions()[$reportType])) {
            $where .= ' AND post_reports.report_type = :report_type';
            $params['report_type'] = $reportType;
        }

        if (in_array($status, array('pending', 'processed', 'ignored'), true)) {
            $where .= ' AND post_reports.status = :status';
            $params['status'] = $status;
        }

        if ($postId > 0) {
            $where .= ' AND post_reports.post_id = :post_id';
            $params['post_id'] = $postId;
        }

        if ($reporterId > 0) {
            $where .= ' AND post_reports.reporter_id = :reporter_id';
            $params['reporter_id'] = $reporterId;
        }

        $page = $this->paginateAdminQuery(
            'SELECT COUNT(*) AS total_count' . $where,
            'SELECT post_reports.*,
                    posts.title AS post_title,
                    posts.region,
                    posts.status AS post_status,
                    posts.author_id,
                    users.username AS reporter_name,
                    authors.username AS author_name,
                    admin_users.username AS handled_admin_name'
                . $where .
                ' ORDER BY post_reports.id DESC',
            $params,
            $pageNo,
            20
        );
        $page['items'] = $this->app->posts()->attachDisplayTitlePayloads((array) ($page['items'] ?? array()));

        return $page;
    }

    public function managedPostReportStats(array $filters = array())
    {
        if (!$this->tableExists($this->db(), 'post_reports')) {
            return array(
                'pending_count' => 0,
                'processed_count' => 0,
                'ignored_count' => 0,
                'post_count' => 0,
            );
        }

        $params = array();
        $where = ' FROM post_reports
                   INNER JOIN posts ON posts.id = post_reports.post_id
                   INNER JOIN users ON users.id = post_reports.reporter_id
                   LEFT JOIN users AS authors ON authors.id = posts.author_id
                   LEFT JOIN admin_users ON admin_users.id = post_reports.handled_by
                   WHERE 1 = 1';
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $region = trim((string) ($filters['region'] ?? ''));
        $reportType = trim((string) ($filters['report_type'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $postId = (int) ($filters['post_id'] ?? 0);
        $reporterId = (int) ($filters['reporter_id'] ?? 0);

        if ($keyword !== '') {
            $where .= ' AND (
                posts.title LIKE :keyword_post_title
                OR users.username LIKE :keyword_username
                OR post_reports.content LIKE :keyword_content
                OR post_reports.handle_result LIKE :keyword_handle_result
            )';
            $params['keyword_post_title'] = '%' . $keyword . '%';
            $params['keyword_username'] = '%' . $keyword . '%';
            $params['keyword_content'] = '%' . $keyword . '%';
            $params['keyword_handle_result'] = '%' . $keyword . '%';
        }

        if (in_array($region, array('macau', 'hongkong'), true)) {
            $where .= ' AND posts.region = :region';
            $params['region'] = $region;
        }

        if (isset($this->managedReportTypeOptions()[$reportType])) {
            $where .= ' AND post_reports.report_type = :report_type';
            $params['report_type'] = $reportType;
        }

        if (in_array($status, array('pending', 'processed', 'ignored'), true)) {
            $where .= ' AND post_reports.status = :status';
            $params['status'] = $status;
        }

        if ($postId > 0) {
            $where .= ' AND post_reports.post_id = :post_id';
            $params['post_id'] = $postId;
        }

        if ($reporterId > 0) {
            $where .= ' AND post_reports.reporter_id = :reporter_id';
            $params['reporter_id'] = $reporterId;
        }

        $row = $this->db()->fetch(
            'SELECT
                SUM(CASE WHEN post_reports.status = \'pending\' THEN 1 ELSE 0 END) AS pending_count,
                SUM(CASE WHEN post_reports.status = \'processed\' THEN 1 ELSE 0 END) AS processed_count,
                SUM(CASE WHEN post_reports.status = \'ignored\' THEN 1 ELSE 0 END) AS ignored_count,
                COUNT(DISTINCT post_reports.post_id) AS post_count'
            . $where,
            $params
        );

        return array(
            'pending_count' => (int) ($row['pending_count'] ?? 0),
            'processed_count' => (int) ($row['processed_count'] ?? 0),
            'ignored_count' => (int) ($row['ignored_count'] ?? 0),
            'post_count' => (int) ($row['post_count'] ?? 0),
        );
    }

    public function managedPostReportById($reportId)
    {
        if (!$this->tableExists($this->db(), 'post_reports')) {
            return null;
        }

        return $this->db()->fetch(
            'SELECT post_reports.*,
                    posts.title AS post_title,
                    posts.region,
                    posts.status AS post_status,
                    posts.author_id,
                    users.username AS reporter_name,
                    admin_users.username AS handled_admin_name
             FROM post_reports
             INNER JOIN posts ON posts.id = post_reports.post_id
             INNER JOIN users ON users.id = post_reports.reporter_id
             LEFT JOIN admin_users ON admin_users.id = post_reports.handled_by
             WHERE post_reports.id = :id
             LIMIT 1',
            array('id' => (int) $reportId)
        );
    }

    public function saveManagedPostReport(array $payload, array $actor)
    {
        if (!$this->tableExists($this->db(), 'post_reports')) {
            throw new RuntimeException('当前数据库还没有帖子举报表，请先刷新后台完成补表。');
        }

        $reportId = isset($payload['id']) ? (int) $payload['id'] : 0;
        $postId = (int) ($payload['post_id'] ?? 0);
        $reporterId = (int) ($payload['reporter_id'] ?? 0);
        $reportType = trim((string) ($payload['report_type'] ?? 'other'));
        $content = trim((string) ($payload['content'] ?? ''));
        $status = trim((string) ($payload['status'] ?? 'pending'));
        $handleResult = trim((string) ($payload['handle_result'] ?? ''));

        if ($postId <= 0 || !$this->managedForumPostById($postId)) {
            throw new RuntimeException('请选择有效的被举报帖子。');
        }

        if ($reporterId <= 0 || !$this->managedUserById($reporterId)) {
            throw new RuntimeException('请选择有效的举报会员。');
        }

        if (!isset($this->managedReportTypeOptions()[$reportType])) {
            throw new RuntimeException('举报类型无效。');
        }

        if ($content === '') {
            throw new RuntimeException('举报说明不能为空。');
        }

        if (!in_array($status, array('pending', 'processed', 'ignored'), true)) {
            throw new RuntimeException('举报状态无效。');
        }

        $now = $this->now();
        $handledBy = $status === 'pending' ? 0 : (int) $actor['id'];

        if ($reportId > 0) {
            $this->db()->execute(
                'UPDATE post_reports
                 SET post_id = :post_id,
                     reporter_id = :reporter_id,
                     report_type = :report_type,
                     content = :content,
                     status = :status,
                     handled_by = :handled_by,
                     handle_result = :handle_result,
                     updated_at = :updated_at
                 WHERE id = :id',
                array(
                    'post_id' => $postId,
                    'reporter_id' => $reporterId,
                    'report_type' => $reportType,
                    'content' => $content,
                    'status' => $status,
                    'handled_by' => $handledBy,
                    'handle_result' => $handleResult,
                    'updated_at' => $now,
                    'id' => $reportId,
                )
            );
            $action = 'update';
        } else {
            $reportId = (int) $this->db()->insertGetId(
                'INSERT INTO post_reports (post_id, reporter_id, report_type, content, status, handled_by, handle_result, created_at, updated_at)
                 VALUES (:post_id, :reporter_id, :report_type, :content, :status, :handled_by, :handle_result, :created_at, :updated_at)',
                array(
                    'post_id' => $postId,
                    'reporter_id' => $reporterId,
                    'report_type' => $reportType,
                    'content' => $content,
                    'status' => $status,
                    'handled_by' => $handledBy,
                    'handle_result' => $handleResult,
                    'created_at' => $now,
                    'updated_at' => $now,
                )
            );
            $action = 'create';
        }

        $savedReport = $this->managedPostReportById($reportId);
        if (!$savedReport) {
            throw new RuntimeException('举报记录保存后未能回读，请刷新后重试。');
        }

        $this->recordOperation((int) $actor['id'], 'reports', $action, 'post_report', $reportId, '保存帖子举报：' . $savedReport['post_title']);

        return $savedReport;
    }

    public function setManagedPostReportStatus($reportId, $status, $handleResult, array $actor, $punishAction = 'none')
    {
        return $this->processManagedPostReportStatus($reportId, $status, $handleResult, $actor, $punishAction);
    }

    protected function applyManagedReportPunishment(array $report, $punishAction, array $actor)
    {
        return $this->applyManagedReportAction($report, $punishAction, $actor);
    }

    public function listPendingAuditTargets(array $filters = array())
    {
        $items = array();
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $region = trim((string) ($filters['region'] ?? ''));
        $targetType = trim((string) ($filters['target_type'] ?? ''));

        if ($targetType === '' || $targetType === 'post') {
            $sql = 'SELECT posts.id AS target_id,
                           posts.id AS post_id,
                           \'post\' AS target_type,
                           posts.title AS target_title,
                           posts.excerpt AS target_excerpt,
                           posts.region,
                           users.username AS submitter_name,
                           posts.created_at
                    FROM posts
                    INNER JOIN users ON users.id = posts.author_id
                    WHERE posts.status = :status';
            $params = array('status' => 'pending');
            if ($keyword !== '') {
                $sql .= ' AND (posts.title LIKE :title_keyword OR posts.excerpt LIKE :excerpt_keyword OR users.username LIKE :username_keyword)';
                $params['title_keyword'] = '%' . $keyword . '%';
                $params['excerpt_keyword'] = '%' . $keyword . '%';
                $params['username_keyword'] = '%' . $keyword . '%';
            }
            if (in_array($region, array('macau', 'hongkong'), true)) {
                $sql .= ' AND posts.region = :region';
                $params['region'] = $region;
            }
            $items = array_merge($items, $this->db()->fetchAll($sql, $params));
        }

        if ($targetType === '' || $targetType === 'comment') {
            $sql = 'SELECT replies.id AS target_id,
                           posts.id AS post_id,
                           \'comment\' AS target_type,
                           posts.title AS target_title,
                           replies.content AS target_excerpt,
                           posts.region,
                           users.username AS submitter_name,
                           replies.created_at
                    FROM replies
                    INNER JOIN posts ON posts.id = replies.post_id
                    INNER JOIN users ON users.id = replies.user_id
                    WHERE replies.status = :status';
            $params = array('status' => 'pending');
            if ($keyword !== '') {
                $sql .= ' AND (replies.content LIKE :content_keyword OR posts.title LIKE :title_keyword OR users.username LIKE :username_keyword)';
                $params['content_keyword'] = '%' . $keyword . '%';
                $params['title_keyword'] = '%' . $keyword . '%';
                $params['username_keyword'] = '%' . $keyword . '%';
            }
            if (in_array($region, array('macau', 'hongkong'), true)) {
                $sql .= ' AND posts.region = :region';
                $params['region'] = $region;
            }
            $items = array_merge($items, $this->db()->fetchAll($sql, $params));
        }

        usort($items, function ($left, $right) {
            return strcmp((string) ($right['created_at'] ?? ''), (string) ($left['created_at'] ?? ''));
        });

        return $this->app->posts()->attachDisplayTitlePayloads($items);
    }

    public function listManagedAuditRecords(array $filters = array())
    {
        if (!$this->tableExists($this->db(), 'audit_records')) {
            return $this->emptyPaginatedResult();
        }

        $params = array();
        $where = ' FROM audit_records
                   LEFT JOIN posts ON audit_records.target_type = \'post\' AND posts.id = audit_records.target_id
                   LEFT JOIN replies ON audit_records.target_type = \'comment\' AND replies.id = audit_records.target_id
                   LEFT JOIN posts AS comment_posts ON comment_posts.id = replies.post_id
                   LEFT JOIN admin_users ON admin_users.id = audit_records.auditor_id
                   WHERE 1 = 1';
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $region = trim((string) ($filters['region'] ?? ''));
        $targetType = trim((string) ($filters['target_type'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));
        $pageNo = (int) ($filters['page_no'] ?? 1);

        if ($keyword !== '') {
            $where .= ' AND (
                posts.title LIKE :post_title_keyword
                OR replies.content LIKE :reply_keyword
                OR comment_posts.title LIKE :comment_post_title_keyword
                OR audit_records.audit_remark LIKE :remark_keyword
            )';
            $params['post_title_keyword'] = '%' . $keyword . '%';
            $params['reply_keyword'] = '%' . $keyword . '%';
            $params['comment_post_title_keyword'] = '%' . $keyword . '%';
            $params['remark_keyword'] = '%' . $keyword . '%';
        }

        if (in_array($region, array('macau', 'hongkong'), true)) {
            $where .= ' AND (
                posts.region = :post_region
                OR comment_posts.region = :comment_post_region
            )';
            $params['post_region'] = $region;
            $params['comment_post_region'] = $region;
        }

        if (in_array($targetType, array('post', 'comment'), true)) {
            $where .= ' AND audit_records.target_type = :target_type';
            $params['target_type'] = $targetType;
        }

        if (in_array($status, array('pass', 'reject', 'pending'), true)) {
            $where .= ' AND audit_records.status = :status';
            $params['status'] = $status;
        }

        $page = $this->paginateAdminQuery(
            'SELECT COUNT(*) AS total_count' . $where,
            'SELECT audit_records.*,
                    COALESCE(posts.id, comment_posts.id) AS post_id,
                    COALESCE(posts.title, comment_posts.title) AS target_title,
                    replies.content AS comment_content,
                    admin_users.username AS auditor_name'
                . $where .
                ' ORDER BY audit_records.id DESC',
            $params,
            $pageNo,
            20
        );
        $page['items'] = $this->app->posts()->attachDisplayTitlePayloads((array) ($page['items'] ?? array()));

        return $page;
    }

    public function processManagedAudit(array $payload, array $actor)
    {
        if (!$this->tableExists($this->db(), 'audit_records')) {
            throw new RuntimeException('当前数据库还没有审核记录表，请先刷新后台完成补表。');
        }

        $targetType = trim((string) ($payload['target_type'] ?? ''));
        $targetId = (int) ($payload['target_id'] ?? 0);
        $reviewAction = trim((string) ($payload['review_action'] ?? ''));
        $reviewRemark = trim((string) ($payload['review_remark'] ?? ''));

        if (!in_array($targetType, array('post', 'comment'), true)) {
            throw new RuntimeException('审核目标类型无效。');
        }

        if ($targetId <= 0) {
            throw new RuntimeException('审核目标不能为空。');
        }

        if (!in_array($reviewAction, array('pass', 'reject'), true)) {
            throw new RuntimeException('审核动作无效。');
        }

        $database = $this->db();
        $database->beginTransaction();

        try {
            if ($targetType === 'post') {
                $target = $this->managedForumPostById($targetId);
                if (!$target) {
                    throw new RuntimeException('待审核帖子不存在。');
                }

                $database->execute(
                    'UPDATE posts SET status = :status, updated_at = :updated_at WHERE id = :id',
                    array(
                        'status' => $reviewAction === 'pass' ? 'published' : 'draft',
                        'updated_at' => $this->now(),
                        'id' => $targetId,
                    )
                );
                $summary = $target['title'];
            } else {
                $target = $database->fetch(
                    'SELECT replies.*, posts.title AS post_title
                     FROM replies
                     INNER JOIN posts ON posts.id = replies.post_id
                     WHERE replies.id = :id
                     LIMIT 1',
                    array('id' => $targetId)
                );
                if (!$target) {
                    throw new RuntimeException('待审核评论不存在。');
                }

                $database->execute(
                    'UPDATE replies SET status = :status WHERE id = :id',
                    array(
                        'status' => $reviewAction === 'pass' ? 'published' : 'hidden',
                        'id' => $targetId,
                    )
                );
                $this->refreshManagedPostReplyCount((int) $target['post_id']);
                $summary = $target['post_title'];
            }

            $database->insertGetId(
                'INSERT INTO audit_records (target_type, target_id, status, auditor_id, audit_remark, audited_at, created_at)
                 VALUES (:target_type, :target_id, :status, :auditor_id, :audit_remark, :audited_at, :created_at)',
                array(
                    'target_type' => $targetType,
                    'target_id' => $targetId,
                    'status' => $reviewAction,
                    'auditor_id' => (int) $actor['id'],
                    'audit_remark' => $reviewRemark,
                    'audited_at' => $this->now(),
                    'created_at' => $this->now(),
                )
            );

            $database->commit();
        } catch (\Throwable $exception) {
            $database->rollBack();
            throw $exception;
        }

        $this->recordOperation((int) $actor['id'], 'audits', $reviewAction, $targetType, $targetId, '审核内容：' . $summary);
    }

    protected function refreshManagedPostReplyCount($postId)
    {
        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS total_count FROM replies WHERE post_id = :post_id AND status = :status',
            array(
                'post_id' => (int) $postId,
                'status' => 'published',
            )
        );

        $this->db()->execute(
            'UPDATE posts SET reply_count = :reply_count, updated_at = :updated_at WHERE id = :id',
            array(
                'reply_count' => $row ? (int) $row['total_count'] : 0,
                'updated_at' => $this->now(),
                'id' => (int) $postId,
            )
        );
    }

    public function listManagedDraws(array $filters = array())
    {
        $sql = 'SELECT lottery_draws.*, users.username
                FROM lottery_draws
                LEFT JOIN users ON users.id = lottery_draws.created_by
                WHERE 1 = 1';
        $params = array();
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $region = trim((string) ($filters['region'] ?? ''));
        $drawDate = trim((string) ($filters['draw_date'] ?? ''));

        if ($keyword !== '') {
            $sql .= ' AND (lottery_draws.issue_no LIKE :keyword_issue_no OR lottery_draws.note LIKE :keyword_note)';
            $params['keyword_issue_no'] = '%' . $keyword . '%';
            $params['keyword_note'] = '%' . $keyword . '%';
        }

        if (in_array($region, array('macau', 'hongkong'), true)) {
            $sql .= ' AND lottery_draws.region = :region';
            $params['region'] = $region;
        }

        if ($drawDate !== '') {
            $sql .= ' AND lottery_draws.draw_date = :draw_date';
            $params['draw_date'] = $drawDate;
        }

        $sql .= ' ORDER BY CAST(lottery_draws.issue_no AS UNSIGNED) DESC, lottery_draws.draw_date DESC, lottery_draws.id DESC LIMIT 120';

        return $this->db()->fetchAll($sql, $params);
    }

    public function managedDrawById($drawId)
    {
        return $this->db()->fetch(
            'SELECT lottery_draws.*, users.username
             FROM lottery_draws
             LEFT JOIN users ON users.id = lottery_draws.created_by
             WHERE lottery_draws.id = :id
             LIMIT 1',
            array('id' => (int) $drawId)
        );
    }

    public function saveManagedDraw(array $payload, array $actor)
    {
        $drawId = isset($payload['id']) ? (int) $payload['id'] : 0;
        $region = isset($payload['region']) ? (string) $payload['region'] : 'macau';
        $issueNo = trim((string) ($payload['issue_no'] ?? ''));
        $drawDate = trim((string) ($payload['draw_date'] ?? ''));
        $numbers = trim((string) ($payload['numbers'] ?? ''));
        $specialNumber = (int) ($payload['special_number'] ?? 0);
        $note = trim((string) ($payload['note'] ?? ''));

        if ($issueNo === '' || $drawDate === '' || $numbers === '') {
            throw new RuntimeException('期号、日期和开奖号码不能为空。');
        }

        if (!in_array($region, array('macau', 'hongkong'), true)) {
            throw new RuntimeException('开奖分区无效。');
        }

        $numberList = array_values(array_filter(array_map('trim', preg_split('/[\s,，]+/u', $numbers))));
        if (count($numberList) < 6) {
            throw new RuntimeException('至少需要填写 6 个正码。');
        }

        $normalizedNumberList = array();
        foreach (array_slice($numberList, 0, 6) as $numberText) {
            $numberText = trim((string) $numberText);
            if (!preg_match('/^\d{1,2}$/', $numberText)) {
                throw new RuntimeException('开奖号码必须是 01 到 49 的数字。');
            }

            $numberValue = (int) $numberText;
            if ($numberValue < 1 || $numberValue > 49) {
                throw new RuntimeException('开奖号码必须是 01 到 49 的数字。');
            }

            $normalizedNumberList[] = str_pad((string) $numberValue, 2, '0', STR_PAD_LEFT);
        }
        $numberList = $normalizedNumberList;
        $frontActor = $this->resolveFrontContentActor($actor);

        if ($drawId > 0) {
            $this->db()->execute(
                'UPDATE lottery_draws
                 SET region = :region,
                     issue_no = :issue_no,
                     draw_date = :draw_date,
                     numbers_json = :numbers_json,
                     special_number = :special_number,
                     note = :note,
                     updated_at = :updated_at
                 WHERE id = :id',
                array(
                    'region' => $region,
                    'issue_no' => $issueNo,
                    'draw_date' => $drawDate,
                    'numbers_json' => json_encode($numberList),
                    'special_number' => $specialNumber,
                    'note' => $note,
                    'updated_at' => $this->now(),
                    'id' => $drawId,
                )
            );
        } else {
            $drawId = $this->db()->insertGetId(
                'INSERT INTO lottery_draws (region, issue_no, draw_date, numbers_json, special_number, note, created_by, created_at, updated_at)
                 VALUES (:region, :issue_no, :draw_date, :numbers_json, :special_number, :note, :created_by, :created_at, :updated_at)',
                array(
                    'region' => $region,
                    'issue_no' => $issueNo,
                    'draw_date' => $drawDate,
                    'numbers_json' => json_encode($numberList),
                    'special_number' => $specialNumber,
                    'note' => $note,
                    'created_by' => (int) $frontActor['id'],
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                )
            );
        }

        $savedDraw = $this->managedDrawById($drawId);

        if (!$savedDraw) {
            throw new RuntimeException('开奖数据保存后未能回读，请稍后刷新重试。');
        }

        $action = $drawId > 0 ? 'update' : 'create';
        $this->app->logs()->admin('draws', $action, '保存开奖资料：' . $savedDraw['issue_no'], 'draw', (string) $savedDraw['id'], (int) $actor['id']);
        $this->recordOperation((int) $actor['id'], 'draws', $action, 'draw', (int) $savedDraw['id'], '保存开奖：' . $savedDraw['region'] . ' ' . $savedDraw['issue_no']);
        try {
            $this->runManagedPostGeneratorAfterDraw($region, $savedDraw, $actor);
        } catch (\Throwable $exception) {
            $this->writeManagedExceptionLog($exception, 'posts', 'post_generator_after_draw', 'admin', (int) ($actor['id'] ?? 0));
        }

        return $savedDraw;
    }

    protected function normalizeManagedDrawMaterialRegion(string $region): string
    {
        $region = trim($region);

        return in_array($region, array('macau', 'hongkong'), true) ? $region : 'macau';
    }

    protected function managedExpertDefaultTitleMap(): array
    {
        return array(
            1 => '高手榜一',
            2 => '高手榜二',
            3 => '高手榜三',
        );
    }

    protected function managedExpertTitleText(string $html): string
    {
        $titleHtml = trim($html);

        if (preg_match('/<div class="section-title\b[^>]*>([\s\S]*?)<\/div>/u', $html, $matches)) {
            $titleHtml = (string) ($matches[1] ?? '');
        }

        $titleText = html_entity_decode(strip_tags($titleHtml), ENT_QUOTES, 'UTF-8');
        $titleText = trim((string) preg_replace('/\s+/u', ' ', $titleText));

        return $titleText;
    }

    protected function managedExpertTitleKey(string $titleText): string
    {
        return trim((string) preg_replace('/\s+/u', '', $this->managedExpertTitleText($titleText)));
    }

    protected function managedExpertExplicitSegmentNo(string $titleText): int
    {
        $titleKey = $this->managedExpertTitleKey($titleText);
        $tokenMap = array(
            1 => array('1', '１', '一', '壹', '①'),
            2 => array('2', '２', '二', '贰', '②'),
            3 => array('3', '３', '三', '叁', '③'),
        );

        if ($titleKey === '' || mb_strpos($titleKey, '高手', 0, 'UTF-8') === false) {
            return 0;
        }

        foreach ($tokenMap as $segmentNo => $tokens) {
            foreach ($tokens as $token) {
                if (mb_strpos($titleKey, $token, 0, 'UTF-8') !== false) {
                    return $segmentNo;
                }
            }
        }

        return 0;
    }

    protected function managedExpertExplicitSegmentNoFromSection(string $sectionHtml, string $titleText): int
    {
        if (preg_match('/\sdata-managed-expert-segment\s*=\s*(["\']?)([1-3])\1/iu', $sectionHtml, $matches)) {
            return (int) ($matches[2] ?? 0);
        }

        return $this->managedExpertExplicitSegmentNo($titleText);
    }

    protected function extractManagedExpertSections(string $html): array
    {
        $sections = array();
        $defaultTitleKeys = array_map(array($this, 'managedExpertTitleKey'), $this->managedExpertDefaultTitleMap());
        $pattern = '/<section\b[^>]*>(?:(?!<section\b)[\s\S])*?<div class="section-title\b[^>]*>[\s\S]*?高手[\s\S]*?<\/div>\s*<div\b[^>]*class="[^"]*\bdata-frame\b[^"]*"[^>]*>[\s\S]*?<\/div>\s*<\/section>/u';

        if (!preg_match_all($pattern, $html, $matches) || empty($matches[0])) {
            return $sections;
        }

        foreach ((array) $matches[0] as $sectionHtml) {
            $sectionHtml = trim((string) $sectionHtml);
            $titleText = $this->managedExpertTitleText($sectionHtml);
            $titleKey = $this->managedExpertTitleKey($titleText);

            if ($titleKey === '' || mb_strpos($titleKey, '高手', 0, 'UTF-8') === false) {
                continue;
            }

            $sections[] = array(
                'html' => $sectionHtml,
                'title' => $titleText,
                'title_key' => $titleKey,
                'explicit_segment_no' => $this->managedExpertExplicitSegmentNoFromSection($sectionHtml, $titleKey),
                'is_legacy_default' => strpos($sectionHtml, 'data-home-expert-segment="') !== false && in_array($titleKey, $defaultTitleKeys, true),
            );
        }

        return $sections;
    }

    protected function extractManagedExpertSectionsByDom(string $html): array
    {
        $sections = array();
        $html = trim($html);

        if ($html === '') {
            return $sections;
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="managed-expert-section-root-dom">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if (!$loaded) {
            return $sections;
        }

        $xpath = new \DOMXPath($document);
        $rootNode = $document->getElementById('managed-expert-section-root-dom');
        if (!$rootNode instanceof \DOMElement) {
            return $sections;
        }

        $sourceOffset = 0;
        foreach ($xpath->query('.//section', $rootNode) as $sectionNode) {
            if (!$sectionNode instanceof \DOMElement) {
                continue;
            }

            $titleNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " section-title ")]', $sectionNode)->item(0);
            $frameNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " data-frame ")]', $sectionNode)->item(0);

            if (!$titleNode instanceof \DOMElement || !$frameNode instanceof \DOMElement) {
                continue;
            }

            $titleText = trim((string) preg_replace(
                '/\s+/u',
                ' ',
                html_entity_decode((string) $titleNode->textContent, ENT_QUOTES, 'UTF-8')
            ));
            $sectionHtml = '';
            if ($titleText !== '') {
                $titlePosition = strpos($html, $titleText, $sourceOffset);
                if ($titlePosition === false) {
                    $titlePosition = strpos($html, $titleText);
                }
                if ($titlePosition !== false) {
                    $beforeTitle = substr($html, 0, $titlePosition);
                    $sectionStart = strrpos($beforeTitle, '<section');
                    $sectionEnd = strpos($html, '</section>', $titlePosition);
                    if ($sectionStart !== false && $sectionEnd !== false) {
                        $sectionEnd += strlen('</section>');
                        $sectionHtml = trim(substr($html, $sectionStart, $sectionEnd - $sectionStart));
                        $sourceOffset = $sectionEnd;
                    }
                }
            }

            if ($sectionHtml === '') {
                $sectionHtml = trim((string) $document->saveHTML($sectionNode));
            }
            if ($sectionHtml === '') {
                continue;
            }

            if ($titleText === '') {
                $titleText = $this->managedExpertTitleText($sectionHtml);
            }
            $titleKey = $this->managedExpertTitleKey($titleText);

            if ($titleKey === '' || mb_strpos($titleKey, '高手', 0, 'UTF-8') === false) {
                continue;
            }

            $sections[] = array(
                'html' => $sectionHtml,
                'title' => $titleText,
                'title_key' => $titleKey,
                'explicit_segment_no' => $this->managedExpertExplicitSegmentNoFromSection($sectionHtml, $titleKey),
                'is_legacy_default' => strpos($sectionHtml, 'data-home-expert-segment="') !== false,
            );
        }

        return $sections;
    }

    protected function managedExpertSectionsForMapping(string $html): array
    {
        $sections = $this->extractManagedExpertSectionsByDom($html);
        if ($sections === array()) {
            $sections = $this->extractManagedExpertSections($html);
        }
        $usedSegments = array();
        $hasCustomSection = false;

        foreach ($sections as $section) {
            if (empty($section['is_legacy_default'])) {
                $hasCustomSection = true;
                break;
            }
        }

        if ($hasCustomSection) {
            $sections = array_values(array_filter($sections, static function (array $section): bool {
                return empty($section['is_legacy_default']);
            }));
        }

        foreach ($sections as $index => $section) {
            $segmentNo = (int) ($section['explicit_segment_no'] ?? 0);
            if ($segmentNo < 1 || $segmentNo > 3) {
                continue;
            }

            $sections[$index]['segment_no'] = $segmentNo;
            $usedSegments[$segmentNo] = true;
        }

        foreach ($sections as $index => $section) {
            if (isset($sections[$index]['segment_no'])) {
                continue;
            }

            for ($segmentNo = 1; $segmentNo <= 3; $segmentNo += 1) {
                if (isset($usedSegments[$segmentNo])) {
                    continue;
                }

                $sections[$index]['segment_no'] = $segmentNo;
                $usedSegments[$segmentNo] = true;
                break;
            }

            if (!isset($sections[$index]['segment_no'])) {
                $sections[$index]['segment_no'] = ($index % 3) + 1;
            }
        }

        return array_values(array_filter($sections, static function (array $section): bool {
            $segmentNo = (int) ($section['segment_no'] ?? 0);

            return $segmentNo >= 1 && $segmentNo <= 3;
        }));
    }

    protected function removeManagedLegacyExpertSections(string $html): string
    {
        $sections = $this->extractManagedExpertSections($html);
        $hasCustomSection = false;

        foreach ($sections as $section) {
            if (empty($section['is_legacy_default'])) {
                $hasCustomSection = true;
                break;
            }
        }

        if (!$hasCustomSection) {
            return $html;
        }

        foreach ($sections as $section) {
            if (empty($section['is_legacy_default'])) {
                continue;
            }

            $legacyHtml = (string) ($section['html'] ?? '');
            if ($legacyHtml === '') {
                continue;
            }

            $position = strpos($html, $legacyHtml);
            if ($position === false) {
                continue;
            }

            $html = substr($html, 0, $position) . substr($html, $position + strlen($legacyHtml));
        }

        return trim($html);
    }

    public function managedPostSegmentOptions(string $region): array
    {
        return array(
            array('value' => 1, 'label' => html_entity_decode('&#39640;&#25163;1', ENT_QUOTES, 'UTF-8')),
            array('value' => 2, 'label' => html_entity_decode('&#39640;&#25163;2', ENT_QUOTES, 'UTF-8')),
            array('value' => 3, 'label' => html_entity_decode('&#39640;&#25163;3', ENT_QUOTES, 'UTF-8')),
        );

        $region = $this->normalizeManagedDrawMaterialRegion($region);
        $labels = array(
            1 => '高手1',
            2 => '高手2',
            3 => '高手3',
        );
        $storedHtml = trim((string) $this->app->settings()->get('draws.material_html.' . $region, ''));
        $sourceHtml = $storedHtml !== '' ? $this->normalizeManagedDrawMaterialHtml($region, $storedHtml) : $this->managedDrawDefaultMaterialTemplate($region);

        foreach ($this->managedExpertSectionsForMapping($sourceHtml) as $section) {
            $segmentNo = (int) ($section['segment_no'] ?? 0);
            $title = trim((string) ($section['title'] ?? ''));

            if ($segmentNo < 1 || $segmentNo > 3 || $title === '') {
                continue;
            }

            $labels[$segmentNo] = $title;
        }

        return array(
            array('value' => 1, 'label' => (string) ($labels[1] ?? '高手1')),
            array('value' => 2, 'label' => (string) ($labels[2] ?? '高手2')),
            array('value' => 3, 'label' => (string) ($labels[3] ?? '高手3')),
        );
    }

    protected function managedDrawDefaultMaterialTemplate(string $region): string
    {
        $templatePath = $this->app->basePath('resources/defaults/home_editor_default.html');
        $templateHtml = is_file($templatePath) ? (string) file_get_contents($templatePath) : '';

        if ($templateHtml === '') {
            return '';
        }

        $templateHtml = (string) preg_replace('/\s*<script id="legacy-home-data"[\s\S]*?<\/script>\s*$/u', '', $templateHtml, 1);

        if (preg_match('/(<section id="section-home"[\s\S]*?)(?=\s*<nav class="bottom-float-nav"[\s\S]*?<\/nav>)/u', $templateHtml, $matches)) {
            return $this->ensureManagedDrawLiveBadgeHtml(
                $this->moveManagedDrawLiveBlockBelowHomeSection(trim((string) ($matches[1] ?? '')))
            );
        }

        return $this->ensureManagedDrawLiveBadgeHtml($this->moveManagedDrawLiveBlockBelowHomeSection(trim($templateHtml)));
    }

    protected function managedDrawProtectedMaterialTemplate(string $region): string
    {
        return $this->managedDrawDefaultMaterialTemplate($region);
    }

    protected function managedDrawEditorExpertTitleText(array $post): string
    {
        $segments = $this->app->posts()->displayTitleSegments($post);
        $titleText = trim((string) (($segments['prefix'] ?? '') . ($segments['middle'] ?? '') . ($segments['author'] ?? '')));

        if ($titleText !== '') {
            return $titleText;
        }

        return trim((string) preg_replace('/^\s*\d{1,6}\s*期\s*[:：]?\s*/u', '', (string) ($post['title'] ?? '')));
    }

    protected function managedDrawEditorExpertPostUrl(array $post, string $region): string
    {
        $postId = (int) ($post['id'] ?? 0);
        if ($postId <= 0) {
            return '';
        }

        return public_url('post.php') . '?id=' . $postId . '&region=' . urlencode($this->normalizeManagedDrawMaterialRegion($region));
    }

    protected function managedDrawEditorExpertLinkStyle(string $styleValue): string
    {
        $styles = array();
        foreach (preg_split('/\s*;\s*/', trim($styleValue)) ?: array() as $declaration) {
            $parts = explode(':', (string) $declaration, 2);
            $property = strtolower(trim((string) ($parts[0] ?? '')));
            $value = trim((string) ($parts[1] ?? ''));

            if ($property === '' || $value === '') {
                continue;
            }

            $styles[$property] = $value;
        }

        if (!isset($styles['text-decoration'])) {
            $styles['text-decoration'] = 'none';
        }

        if (!isset($styles['color'])) {
            $styles['color'] = 'inherit';
        }

        $normalized = array();
        foreach ($styles as $property => $value) {
            $normalized[] = $property . ': ' . $value;
        }

        return implode('; ', $normalized);
    }

    protected function appendManagedDrawEditorExpertTitleSegments(\DOMDocument $document, \DOMElement $titleNode, array $displayTitle, string $fallbackTitleText): void
    {
        $bodyHtml = trim((string) ($displayTitle['body_html'] ?? ''));
        if ($bodyHtml !== '') {
            $fragment = $document->createDocumentFragment();
            if ($fragment->appendXML($bodyHtml)) {
                $titleNode->appendChild($fragment);

                return;
            }
        }

        $titleNode->appendChild($document->createTextNode($fallbackTitleText));
    }

    protected function managedDrawEditorExpertLinkNode(\DOMDocument $document, \DOMNode $itemNode, string $href): \DOMNode
    {
        if ($href === '' || !$itemNode instanceof \DOMElement) {
            return $itemNode;
        }

        $itemElement = $this->normalizeManagedDrawEditorExpertItemNode($document, $itemNode);
        if (!$itemElement instanceof \DOMElement) {
            return $itemNode;
        }

        $xpath = new \DOMXPath($document);
        $linkNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " expert-item-link ")]', $itemElement)->item(0);

        if (!$linkNode instanceof \DOMElement) {
            $linkNode = $document->createElement('a');
            $linkNode->setAttribute('class', 'expert-item-link');
            $mainNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " expert-item-main ")]', $itemElement)->item(0);

            if ($mainNode instanceof \DOMNode) {
                $itemElement->insertBefore($linkNode, $itemElement->firstChild);
                $linkNode->appendChild($mainNode);
            } else {
                $itemElement->appendChild($linkNode);
            }
        }

        $linkNode->setAttribute('href', $href);
        $linkNode->setAttribute('style', $this->managedDrawEditorExpertLinkStyle((string) $linkNode->getAttribute('style')));
        $linkNode->removeAttribute('target');
        $linkNode->removeAttribute('rel');

        return $itemElement;
    }

    protected function managedDrawEditorAdLinkNode(\DOMDocument $document, \DOMNode $itemNode, string $href): \DOMNode
    {
        if ($href === '' || !$itemNode instanceof \DOMElement) {
            return $itemNode;
        }

        if (strcasecmp($itemNode->tagName, 'a') === 0) {
            $itemNode->setAttribute('href', $href);
            $itemNode->setAttribute('style', $this->managedDrawEditorExpertLinkStyle((string) $itemNode->getAttribute('style')));
            $itemNode->removeAttribute('target');
            $itemNode->removeAttribute('rel');
            $itemNode->setAttribute('data-front-ad-link', '1');
            $itemNode->setAttribute('data-front-flood-bypass', '1');
            $itemNode->setAttribute('data-no-prefetch', '1');

            return $itemNode;
        }

        $linkNode = $document->createElement('a');
        if ($itemNode->hasAttributes()) {
            foreach ($itemNode->attributes as $attributeNode) {
                if ($attributeNode instanceof \DOMAttr) {
                    $linkNode->setAttribute($attributeNode->nodeName, $attributeNode->nodeValue);
                }
            }
        }

        while ($itemNode->firstChild) {
            $linkNode->appendChild($itemNode->firstChild);
        }

        $linkNode->setAttribute('href', $href);
        $linkNode->setAttribute('style', $this->managedDrawEditorExpertLinkStyle((string) $linkNode->getAttribute('style')));
        $linkNode->removeAttribute('target');
        $linkNode->removeAttribute('rel');
        $linkNode->setAttribute('data-front-ad-link', '1');
        $linkNode->setAttribute('data-front-flood-bypass', '1');
        $linkNode->setAttribute('data-no-prefetch', '1');

        return $linkNode;
    }

    protected function normalizeManagedDrawEditorExpertItemNode(\DOMDocument $document, \DOMNode $itemNode): ?\DOMElement
    {
        if (!$itemNode instanceof \DOMElement) {
            return null;
        }

        if (strcasecmp($itemNode->tagName, 'a') !== 0) {
            return $itemNode;
        }

        $containerNode = $document->createElement('div');
        if ($itemNode->hasAttributes()) {
            foreach ($itemNode->attributes as $attributeNode) {
                if (!$attributeNode instanceof \DOMAttr) {
                    continue;
                }

                if (in_array(strtolower((string) $attributeNode->nodeName), array('href', 'target', 'rel'), true)) {
                    continue;
                }

                $containerNode->setAttribute($attributeNode->nodeName, $attributeNode->nodeValue);
            }
        }

        while ($itemNode->firstChild) {
            $containerNode->appendChild($itemNode->firstChild);
        }

        if ($itemNode->parentNode) {
            $itemNode->parentNode->replaceChild($containerNode, $itemNode);
        }

        return $containerNode;
    }

    protected function managedDrawEditorExpertViewerContext(string $region): array
    {
        static $cache = array();

        $region = $this->normalizeManagedDrawMaterialRegion($region);
        $viewer = current_user();
        $cacheKey = $region . ':' . (int) ($viewer['id'] ?? 0);

        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $cache[$cacheKey] = array(
            'api_url' => public_url('api.php'),
            'csrf_token' => csrf_token('api'),
            'login_url' => public_url('member.php') . '?region=' . urlencode($region) . '&mode=login',
            'logged_in' => $viewer ? 1 : 0,
        );

        return $cache[$cacheKey];
    }

    protected function managedDrawEditorCurrentIssuePrefixText(string $region): string
    {
        $snapshot = $this->managedIssuePrefixSnapshotByRegion($region);

        return $this->app->posts()->formatIssuePrefixText((string) ($snapshot['issue_prefix_tail'] ?? ''));
    }

    protected function rebuildManagedDrawEditorExpertItem(\DOMDocument $document, \DOMNode $itemNode, array $post, int $index, string $region, string $titleText, ?int $colorIndex = null): ?\DOMElement
    {
        $itemNode = $this->normalizeManagedDrawEditorExpertItemNode($document, $itemNode);
        if (!$itemNode instanceof \DOMElement) {
            return null;
        }

        $postId = (int) ($post['id'] ?? 0);
        $href = $this->managedDrawEditorExpertPostUrl($post, $region);
        $displayViewCount = array_key_exists('display_view_count', $post)
            ? max(0, (int) $post['display_view_count'])
            : max(0, (int) $this->app->posts()->currentDisplayedViewCount($postId));
        $preset = $this->managedDrawEditorExpertResultPreset($index, $colorIndex, $post);
        $displayTitle = $this->app->posts()->displayTitleHtml($post);
        $titleTextStyle = trim((string) ($displayTitle['text_style'] ?? ''));

        while ($itemNode->firstChild) {
            $itemNode->removeChild($itemNode->firstChild);
        }

        $itemNode->setAttribute('class', 'expert-item-card bg-white p-4 rounded-xl');
        $itemNode->removeAttribute('style');
        $itemNode->removeAttribute('href');
        $itemNode->removeAttribute('target');
        $itemNode->removeAttribute('rel');

        $linkNode = $document->createElement('a');
        $linkNode->setAttribute('class', 'expert-item-link');
        $linkNode->setAttribute('href', $href);
        $linkNode->setAttribute('style', $this->managedDrawEditorExpertLinkStyle((string) $linkNode->getAttribute('style')));

        $mainNode = $document->createElement('span');
        $mainNode->setAttribute('class', 'expert-item-main');

        $issuePrefixNode = $document->createElement('span');
        $issuePrefixNode->setAttribute('class', 'issue-prefix issue-prefix-expert');
        $issuePrefixText = $this->managedDrawEditorCurrentIssuePrefixText($region);
        if ($issuePrefixText !== '') {
            $issuePrefixNode->appendChild($document->createTextNode($issuePrefixText));
        }
        if ($titleTextStyle !== '') {
            $issuePrefixNode->setAttribute('style', $titleTextStyle);
        }
        $mainNode->appendChild($issuePrefixNode);

        $titleNode = $document->createElement('span');
        $titleNode->setAttribute('class', 'expert-item-title');
        $this->appendManagedDrawEditorExpertTitleSegments($document, $titleNode, $displayTitle, $titleText);
        $mainNode->appendChild($titleNode);

        $linkNode->appendChild($mainNode);
        $itemNode->appendChild($linkNode);

        $metaNode = $document->createElement('div');
        $metaNode->setAttribute('class', 'expert-item-meta');

        $resultNode = $document->createElement('span');
        $resultNode->setAttribute('class', 'expert-item-result');
        $resultNode->setAttribute('style', 'color: ' . $preset['color'] . ';');
        $resultNode->appendChild($document->createTextNode((string) $preset['text']));
        $metaNode->appendChild($resultNode);

        $viewNode = $document->createElement('span');
        $viewNode->setAttribute('class', 'expert-view-count');
        $viewNode->setAttribute('data-post-id', (string) $postId);
        $viewNode->setAttribute('data-post-view-count', (string) $displayViewCount);
        $viewNode->setAttribute('aria-label', '浏览量 ' . $displayViewCount);

        $iconNode = $document->createElement('span');
        $iconNode->setAttribute('class', 'expert-view-icon');
        $iconNode->setAttribute('aria-hidden', 'true');
        $iconNode->appendChild($document->createTextNode('👁‍🗨'));
        $viewNode->appendChild($iconNode);

        $countNode = $document->createElement('span');
        $countNode->setAttribute('class', 'expert-view-number');
        $countNode->appendChild($document->createTextNode((string) $displayViewCount));
        $viewNode->appendChild($countNode);

        $metaNode->appendChild($viewNode);
        $itemNode->appendChild($metaNode);

        return $mainNode;
    }

    protected function managedDrawEditorExpertResultPreset(int $index, ?int $colorIndex = null, array $post = array()): array
    {
        $colorIndex = $colorIndex === null ? $index : $colorIndex;
        $colorIndex = $colorIndex < 0 ? 0 : $colorIndex;
        $dailyColors = $this->managedDrawEditorExpertDailyResultColors();
        $dailyColor = $dailyColors[$colorIndex % count($dailyColors)];
        $stats = $post !== array()
            ? $this->managedDrawEditorExpertResultStats($post)
            : $this->managedForecastRecordStats('macau', '', '', true, true);
        $total = (int) ($stats['total'] ?? 0);
        if ($total > 0) {
            return array(
                'text' => (string) $total . '中' . (string) ((int) ($stats['hit'] ?? 0)),
                'color' => $dailyColor,
            );
        }
        $presets = array(
            array('text' => '12中8', 'color' => $dailyColor),
            array('text' => '9中6', 'color' => $dailyColor),
            array('text' => '7中5', 'color' => $dailyColor),
        );

        if ($index < 0) {
            $index = 0;
        }

        if ($index >= count($presets)) {
            $index = count($presets) - 1;
        }

        return $presets[$index];
    }

    protected function managedDrawEditorExpertResultStats(array $post): array
    {
        $region = (string) ($post['region'] ?? 'macau') === 'hongkong' ? 'hongkong' : 'macau';
        $contentText = (string) ($post['full_content'] ?? '');
        $recentResultLog = (string) ($post['manage_recent_result_log'] ?? $post['recent_result_log'] ?? '');
        $drawVersion = $this->managedDrawEditorExpertStatsDrawVersion($region);
        $cacheKey = 'home_expert_result_stats_' . md5($region . "\n" . $contentText . "\n" . $recentResultLog . "\n1\n1\n" . $drawVersion);
        $cachedStats = $this->app->cache()->get($cacheKey, null, 3600);

        if (is_array($cachedStats) && array_key_exists('total', $cachedStats)) {
            return $cachedStats;
        }

        $stats = $this->managedForecastRecordStats($region, $contentText, $recentResultLog, true, true);
        $this->app->cache()->put($cacheKey, $stats);

        return $stats;
    }

    protected function managedDrawEditorExpertStatsDrawVersion(string $region): string
    {
        static $cache = array();

        $region = $this->normalizeManagedDrawMaterialRegion($region);
        if (array_key_exists($region, $cache)) {
            return $cache[$region];
        }

        $version = 'no-draw-table';
        if ($this->tableExists($this->db(), 'lottery_draws')) {
            $row = $this->db()->fetch(
                'SELECT MAX(id) AS max_id, MAX(updated_at) AS max_updated_at FROM lottery_draws WHERE region = :region',
                array('region' => $region)
            );
            if (is_array($row)) {
                $version = (string) ((int) ($row['max_id'] ?? 0)) . '|' . (string) ($row['max_updated_at'] ?? '');
            }
        }

        $cache[$region] = $version;

        return $version;
    }

    protected function managedDrawEditorExpertDailyResultColors(): array
    {
        static $cache = array();

        $dateKey = substr($this->now(), 0, 10);
        if (isset($cache[$dateKey])) {
            return $cache[$dateKey];
        }

        $colors = array(
            '#dc2626',
            '#2563eb',
            '#16a34a',
            '#7c3aed',
            '#c2410c',
            '#0891b2',
            '#be123c',
        );
        usort($colors, function (string $left, string $right) use ($dateKey): int {
            return strcmp(hash('crc32b', $dateKey . '|' . $left), hash('crc32b', $dateKey . '|' . $right));
        });

        $cache[$dateKey] = $colors;

        return $colors;
    }

    protected function hydrateManagedDrawEditorExpertResultNode(\DOMDocument $document, \DOMNode $itemNode, int $index, ?int $colorIndex = null, array $post = array()): void
    {
        if (!$itemNode instanceof \DOMElement) {
            return;
        }

        $preset = $this->managedDrawEditorExpertResultPreset($index, $colorIndex, $post);
        $xpath = new \DOMXPath($document);
        $resultNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " expert-item-result ")]', $itemNode)->item(0);

        if (!$resultNode instanceof \DOMElement) {
            $metaNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " expert-item-meta ")]', $itemNode)->item(0);
            if (!$metaNode instanceof \DOMElement) {
                $metaNode = $document->createElement('div');
                $metaNode->setAttribute('class', 'expert-item-meta');
                $itemNode->appendChild($metaNode);
            }

            $resultNode = $document->createElement('span');
            $resultNode->setAttribute('class', 'expert-item-result');
            $metaNode->insertBefore($resultNode, $metaNode->firstChild);
        }

        while ($resultNode->firstChild) {
            $resultNode->removeChild($resultNode->firstChild);
        }

        $resultNode->setAttribute('style', 'color: ' . $preset['color'] . ';');
        $resultNode->appendChild($document->createTextNode((string) $preset['text']));
    }

    protected function hydrateManagedDrawEditorExpertItem(\DOMDocument $document, \DOMXPath $xpath, \DOMNode $itemNode, array $post, int $index, string $region, ?int $colorIndex = null): bool
    {
        $titleText = trim($this->managedDrawEditorExpertTitleText($post));
        if ($titleText === '') {
            return false;
        }

        return $this->rebuildManagedDrawEditorExpertItem($document, $itemNode, $post, $index, $region, $titleText, $colorIndex) instanceof \DOMElement;
    }

    protected function isManagedDrawEditorExpertAdSlotNode(\DOMNode $node): bool
    {
        if (!$node instanceof \DOMElement) {
            return false;
        }

        $className = ' ' . (string) $node->getAttribute('class') . ' ';

        return (string) $node->getAttribute('data-expert-ad-slot') === '1'
            || strpos($className, ' expert-ad-slot-card ') !== false;
    }

    protected function managedDrawEditorExpertAdInterval(\DOMElement $sectionNode): int
    {
        $value = preg_replace('/[^0-9]+/', '', (string) $sectionNode->getAttribute('data-expert-ad-interval'));
        $interval = (int) $value;

        if ($interval <= 0) {
            return 0;
        }

        return min(99, $interval);
    }

    protected function managedDrawEditorExpertAdTitle(\DOMElement $sectionNode): string
    {
        $title = html_entity_decode((string) $sectionNode->getAttribute('data-expert-ad-title'), ENT_QUOTES, 'UTF-8');
        $title = trim((string) preg_replace('/\s+/u', ' ', strip_tags($title)));

        if ($title === '') {
            return '广告推荐';
        }

        return mb_substr($title, 0, 80, 'UTF-8');
    }

    protected function sanitizeManagedDrawEditorExpertAdText($value, int $limit = 80): string
    {
        $text = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));

        if ($limit > 0 && mb_strlen($text, 'UTF-8') > $limit) {
            return mb_substr($text, 0, $limit, 'UTF-8');
        }

        return $text;
    }

    protected function normalizeManagedDrawEditorExpertAdMiddleColorMode($mode): string
    {
        $mode = str_replace('_', '-', strtolower(trim((string) $mode)));

        if ($mode === 'fixed' || $mode === 'daily-random') {
            return $mode;
        }

        return 'default';
    }

    protected function normalizeManagedDrawEditorExpertAdTailTextMode($mode): string
    {
        $mode = str_replace('_', '-', strtolower(trim((string) $mode)));

        return $mode === 'daily-random' ? 'daily-random' : 'fixed';
    }

    protected function normalizeManagedDrawEditorExpertAdHexColor($color): string
    {
        $color = strtolower(trim((string) $color));

        return preg_match('/^#[0-9a-f]{6}$/', $color) ? $color : '';
    }

    protected function normalizeManagedDrawEditorExpertAdRandomWords($value): array
    {
        $text = html_entity_decode((string) $value, ENT_QUOTES, 'UTF-8');
        $text = str_replace(array("\r\n", "\r"), "\n", $text);
        $parts = strpos($text, '||') !== false ? explode('||', $text) : preg_split('/[\n|]+/u', $text);
        $words = array();

        foreach ((array) $parts as $part) {
            $word = $this->sanitizeManagedDrawEditorExpertAdText($part, 80);
            if ($word === '') {
                continue;
            }

            $words[$word] = true;
            if (count($words) >= 50) {
                break;
            }
        }

        return array_keys($words);
    }

    protected function splitManagedDrawEditorExpertAdTitle(string $title): array
    {
        $title = $this->sanitizeManagedDrawEditorExpertAdText($title, 240);
        $entry = array(
            'lead' => $title,
            'middle' => '',
            'tail' => '',
            'linkUrl' => '',
            'badgeText' => '广告',
            'middleColorMode' => 'default',
            'middleColor' => '#2563eb',
            'tailTextMode' => 'fixed',
            'tailRandomWords' => '',
        );

        if (preg_match('/^(.*?)(【[^】]+】|\[[^\]]+\]|（[^）]+）|\([^)]*\))(.*)$/u', $title, $matches)) {
            $entry['lead'] = $this->sanitizeManagedDrawEditorExpertAdText((string) $matches[1], 80);
            $entry['middle'] = $this->sanitizeManagedDrawEditorExpertAdText((string) $matches[2], 80);
            $entry['tail'] = $this->sanitizeManagedDrawEditorExpertAdText((string) $matches[3], 80);
        }

        if ($entry['lead'] === '' && $entry['middle'] === '' && $entry['tail'] === '') {
            $entry['lead'] = '广告推荐';
        }

        return $entry;
    }

    protected function managedDrawEditorExpertAdItems(\DOMElement $sectionNode): array
    {
        $items = array();
        $raw = html_entity_decode((string) $sectionNode->getAttribute('data-expert-ad-items'), ENT_QUOTES, 'UTF-8');
        $decoded = $raw !== '' ? json_decode($raw, true) : null;

        if (is_array($decoded)) {
            foreach ($decoded as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $middleColor = $this->normalizeManagedDrawEditorExpertAdHexColor($item['middleColor'] ?? '');
                $tailRandomWords = $this->normalizeManagedDrawEditorExpertAdRandomWords($item['tailRandomWords'] ?? ($item['tailRandomOptions'] ?? ''));
                $entry = array(
                    'lead' => $this->sanitizeManagedDrawEditorExpertAdText($item['lead'] ?? '', 80),
                    'middle' => $this->sanitizeManagedDrawEditorExpertAdText($item['middle'] ?? '', 80),
                    'tail' => $this->sanitizeManagedDrawEditorExpertAdText($item['tail'] ?? '', 80),
                    'linkUrl' => $this->sanitizeManagedDrawEditorExpertAdText($item['linkUrl'] ?? ($item['link'] ?? ($item['url'] ?? '')), 500),
                    'badgeText' => $this->sanitizeManagedDrawEditorExpertAdText($item['badgeText'] ?? ($item['badge'] ?? ''), 12) ?: '广告',
                    'middleColorMode' => $this->normalizeManagedDrawEditorExpertAdMiddleColorMode($item['middleColorMode'] ?? ''),
                    'middleColor' => $middleColor !== '' ? $middleColor : '#2563eb',
                    'tailTextMode' => $this->normalizeManagedDrawEditorExpertAdTailTextMode($item['tailTextMode'] ?? ''),
                    'tailRandomWords' => implode('||', $tailRandomWords),
                );

                if ($entry['lead'] === '' && $entry['middle'] === '' && $entry['tail'] === '' && $tailRandomWords === array()) {
                    continue;
                }

                $items[] = $entry;
            }
        }

        if ($items === array() && $sectionNode->hasAttribute('data-expert-ad-title')) {
            $legacyTitle = $this->managedDrawEditorExpertAdTitle($sectionNode);
            if ($legacyTitle !== '') {
                $items[] = $this->splitManagedDrawEditorExpertAdTitle($legacyTitle);
            }
        }

        return $items;
    }

    protected function managedDrawEditorExpertAdViewState(string $region): array
    {
        $region = $this->normalizeManagedDrawMaterialRegion($region);
        if (isset($this->managedExpertAdViewStateCache[$region]) && is_array($this->managedExpertAdViewStateCache[$region])) {
            return $this->managedExpertAdViewStateCache[$region];
        }

        $rawState = (string) $this->app->settings()->get('draws.expert_ad_view_state.' . $region, '');
        $state = json_decode($rawState, true);
        if (!is_array($state)) {
            $state = array();
        }
        if (!isset($state['slots']) || !is_array($state['slots'])) {
            $state['slots'] = array();
        }

        $this->managedExpertAdViewStateCache[$region] = $state;

        return $state;
    }

    protected function persistManagedDrawEditorExpertAdViewState(string $region): void
    {
        $region = $this->normalizeManagedDrawMaterialRegion($region);
        if (empty($this->managedExpertAdViewStateDirty[$region])) {
            return;
        }

        $state = $this->managedDrawEditorExpertAdViewState($region);
        $encoded = json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($encoded) || $encoded === '') {
            $encoded = '{"slots":{}}';
        }

        $this->app->settings()->setMany('draws', array(
            'draws.expert_ad_view_state.' . $region => $encoded,
        ));
        unset($this->managedExpertAdViewStateDirty[$region]);
    }

    protected function managedDrawEditorExpertAdIssueKey(string $region): string
    {
        $snapshot = $this->managedIssuePrefixSnapshotByRegion($region);
        $issueTail = trim((string) ($snapshot['issue_prefix_tail'] ?? ''));

        return $issueTail !== '' ? $issueTail : substr($this->now(), 0, 10);
    }

    protected function managedDrawEditorExpertAdSlotKey(int $segmentNo, int $slotIndex): string
    {
        $segmentNo = max(1, min(3, (int) $segmentNo));
        $slotIndex = max(1, min(99, (int) $slotIndex));

        return 's' . $segmentNo . ':a' . $slotIndex;
    }

    protected function managedDrawEditorExpertAdViewerHash(): string
    {
        $viewer = current_user();
        $viewerId = is_array($viewer) ? (int) ($viewer['id'] ?? 0) : 0;
        if ($viewerId > 0) {
            return sha1('expert-ad-post-viewer|user:' . $viewerId);
        }

        $cookieName = 'front_viewer_identity';
        $existingToken = strtolower(trim((string) ($_COOKIE[$cookieName] ?? '')));
        if (preg_match('/^[a-f0-9]{32}$/', $existingToken)) {
            return sha1('expert-ad-post-viewer|guest:' . $existingToken);
        }

        try {
            $token = bin2hex(random_bytes(16));
        } catch (\Throwable $exception) {
            $token = md5(uniqid('post-viewer', true));
        }

        if (!headers_sent()) {
            $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
            $expiresAt = time() + (365 * 24 * 60 * 60);
            if (PHP_VERSION_ID >= 70300) {
                setcookie($cookieName, $token, array(
                    'expires' => $expiresAt,
                    'path' => '/',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ));
            } else {
                setcookie($cookieName, $token, $expiresAt, '/', '', $secure, true);
            }
            $_COOKIE[$cookieName] = $token;

            return sha1('expert-ad-post-viewer|guest:' . $token);
        }

        return sha1('expert-ad-post-viewer|fingerprint:' . Security::ipAddress() . '|' . Security::userAgent());
    }

    protected function managedDrawEditorExpertAdViewCount(string $region, int $segmentNo, int $slotIndex, bool $incrementView = false): int
    {
        $region = $this->normalizeManagedDrawMaterialRegion($region);
        $settings = $this->app->posts()->postViewDisplaySettings();
        $baseMin = max(1, (int) ($settings['base_min'] ?? 4935));
        $baseMax = max($baseMin, (int) ($settings['base_max'] ?? 7563));
        $incrementMin = 1;
        $incrementMax = 2;
        $slotKey = $this->managedDrawEditorExpertAdSlotKey($segmentNo, $slotIndex);
        $issueKey = $this->managedDrawEditorExpertAdIssueKey($region);
        $state = $this->managedDrawEditorExpertAdViewState($region);
        $slotState = isset($state['slots'][$slotKey]) && is_array($state['slots'][$slotKey])
            ? $state['slots'][$slotKey]
            : array();
        $slotIssue = isset($slotState['issue']) ? (string) $slotState['issue'] : '';
        $slotVersion = isset($slotState['version']) ? (int) $slotState['version'] : 0;
        $baseCount = isset($slotState['base']) ? (int) $slotState['base'] : 0;
        $releasedCount = isset($slotState['released']) ? max(0, (int) $slotState['released']) : 0;
        $nowTimestamp = time();

        if ($slotVersion < 2 || $slotIssue !== $issueKey || $baseCount < 1) {
            $baseCount = $this->managedPostGeneratorRandomRange($baseMin, $baseMax);
            $releasedCount = 0;
            $slotState = array(
                'version' => 2,
                'base' => $baseCount,
                'released' => 0,
                'total' => $baseCount,
                'issue' => $issueKey,
                'seen' => array(),
                'events' => array(),
            );
            $this->managedExpertAdViewStateDirty[$region] = true;
        }

        if (!isset($slotState['seen']) || !is_array($slotState['seen'])) {
            $slotState['seen'] = array();
        }
        if (!isset($slotState['events']) || !is_array($slotState['events'])) {
            $slotState['events'] = array();
        }

        $releasePendingEvents = function (array $events) use (&$releasedCount, $nowTimestamp): array {
            $pendingEvents = array();
            foreach ($events as $event) {
                $releaseAt = is_array($event) ? (string) ($event['release_at'] ?? '') : (string) $event;
                $releaseTimestamp = strtotime($releaseAt);
                if ($releaseTimestamp === false) {
                    continue;
                }
                if ($releaseTimestamp <= $nowTimestamp) {
                    $releasedCount += 1;
                    continue;
                }
                $pendingEvents[] = array('release_at' => date('Y-m-d H:i:s', $releaseTimestamp));
            }

            return $pendingEvents;
        };

        $beforePendingCount = count($slotState['events']);
        $slotState['events'] = $releasePendingEvents((array) $slotState['events']);
        if (count($slotState['events']) !== $beforePendingCount) {
            $slotState['released'] = $releasedCount;
            $this->managedExpertAdViewStateDirty[$region] = true;
        }

        if ($incrementView) {
            $viewerHash = $this->managedDrawEditorExpertAdViewerHash();
            if ($viewerHash !== '' && !isset($slotState['seen'][$viewerHash])) {
                try {
                    $eventCount = random_int($incrementMin, $incrementMax);
                } catch (\Throwable $exception) {
                    $eventCount = $this->managedPostGeneratorRandomRange($incrementMin, $incrementMax);
                }
                $releaseOffsets = array(0);
                for ($index = 1; $index < $eventCount; $index += 1) {
                    try {
                        $releaseOffsets[] = random_int(1, 30 * 60);
                    } catch (\Throwable $exception) {
                        $releaseOffsets[] = mt_rand(1, 30 * 60);
                    }
                }
                sort($releaseOffsets, SORT_NUMERIC);

                foreach ($releaseOffsets as $releaseOffset) {
                    $slotState['events'][] = array(
                        'release_at' => date('Y-m-d H:i:s', $nowTimestamp + (int) $releaseOffset),
                    );
                }

                $slotState['seen'][$viewerHash] = array(
                    'at' => date('Y-m-d H:i:s', $nowTimestamp),
                    'event_count' => $eventCount,
                );
                if (count($slotState['seen']) > 500) {
                    $slotState['seen'] = array_slice($slotState['seen'], -300, null, true);
                }
                $slotState['events'] = $releasePendingEvents((array) $slotState['events']);
                $slotState['released'] = $releasedCount;
                $this->managedExpertAdViewStateDirty[$region] = true;
            }
        }

        $slotState['version'] = 2;
        $slotState['base'] = $baseCount;
        $slotState['released'] = $releasedCount;
        $slotState['total'] = $baseCount + $releasedCount;
        $state['slots'][$slotKey] = $slotState;
        $this->managedExpertAdViewStateCache[$region] = $state;

        return max(0, (int) $slotState['total']);
    }

    protected function appendManagedDrawEditorExpertAdTitleSegments(\DOMDocument $document, \DOMElement $titleNode, array $entry, string $titleStyle = ''): void
    {
        $leadText = $this->sanitizeManagedDrawEditorExpertAdText($entry['lead'] ?? '', 80);
        $middleText = $this->sanitizeManagedDrawEditorExpertAdText($entry['middle'] ?? '', 80);
        $tailText = $this->sanitizeManagedDrawEditorExpertAdText($entry['tail'] ?? '', 80);
        $middleColorMode = $this->normalizeManagedDrawEditorExpertAdMiddleColorMode($entry['middleColorMode'] ?? '');
        $middleColor = $this->normalizeManagedDrawEditorExpertAdHexColor($entry['middleColor'] ?? '');
        $tailTextMode = $this->normalizeManagedDrawEditorExpertAdTailTextMode($entry['tailTextMode'] ?? '');
        $tailRandomWords = $this->normalizeManagedDrawEditorExpertAdRandomWords($entry['tailRandomWords'] ?? ($entry['tailRandomOptions'] ?? ''));
        $tailRandomOptions = implode('||', $tailRandomWords);
        $middleColorKey = trim($leadText . '|' . $middleText . '|' . $tailText);
        $tailRandomKey = trim($leadText . '|' . $middleText . '|' . $tailRandomOptions);
        $segments = array(
            'lead' => array('class' => 'ad-item-lead', 'text' => $leadText),
            'middle' => array('class' => 'ad-item-middle', 'text' => $middleText),
            'tail' => array('class' => 'ad-item-tail', 'text' => $tailText),
        );
        $hasText = false;

        if ($segments['tail']['text'] === '' && $tailRandomWords !== array()) {
            $segments['tail']['text'] = (string) $tailRandomWords[0];
            $tailText = $segments['tail']['text'];
        }

        foreach ($segments as $key => $segment) {
            $text = (string) ($segment['text'] ?? '');
            if ($text === '') {
                continue;
            }

            $segmentStyle = $titleStyle;
            $segmentNode = $document->createElement('span');
            $segmentNode->setAttribute('class', (string) ($segment['class'] ?? ''));

            if ($key === 'middle') {
                if ($middleColorMode === 'fixed' && $middleColor !== '') {
                    $segmentNode->setAttribute('data-middle-color-mode', 'fixed');
                    $segmentNode->setAttribute('data-middle-fixed-color', $middleColor);
                    $segmentStyle = rtrim(trim($segmentStyle), ';');
                    $segmentStyle .= ($segmentStyle !== '' ? ';' : '') . 'color:' . $middleColor . ';';
                } elseif ($middleColorMode === 'daily-random') {
                    $segmentNode->setAttribute('data-middle-color-mode', 'daily-random');
                    $segmentNode->setAttribute('data-middle-color-key', $middleColorKey);
                }
            }

            if ($key === 'tail' && $tailTextMode === 'daily-random') {
                $segmentNode->setAttribute('data-tail-text-mode', 'daily-random');
                $segmentNode->setAttribute('data-tail-default-text', $tailText);
                $segmentNode->setAttribute('data-tail-random-options', $tailRandomOptions);
                $segmentNode->setAttribute('data-tail-random-key', $tailRandomKey);
            }

            if ($segmentStyle !== '') {
                $segmentNode->setAttribute('style', $segmentStyle);
            }
            $segmentNode->appendChild($document->createTextNode($text));
            $titleNode->appendChild($segmentNode);
            $hasText = true;
        }

        if (!$hasText) {
            $fallbackNode = $document->createElement('span');
            $fallbackNode->setAttribute('class', 'ad-item-lead');
            if ($titleStyle !== '') {
                $fallbackNode->setAttribute('style', $titleStyle);
            }
            $fallbackNode->appendChild($document->createTextNode('广告推荐'));
            $titleNode->appendChild($fallbackNode);
        }
    }

    protected function buildManagedDrawEditorExpertAdSlotNode(\DOMDocument $document, \DOMElement $sectionNode, int $slotIndex, array $adItems, string $region, array $stylePost = array(), int $segmentNo = 1, bool $incrementView = false): \DOMElement
    {
        $items = $adItems !== array() ? array_values($adItems) : array($this->splitManagedDrawEditorExpertAdTitle($this->managedDrawEditorExpertAdTitle($sectionNode)));
        $entryIndex = max(1, $slotIndex) - 1;
        $entry = (array) ($items[$entryIndex] ?? $items[0]);
        $linkUrl = $this->sanitizeManagedDrawEditorExpertAdText($entry['linkUrl'] ?? '', 500);
        $badgeText = $this->sanitizeManagedDrawEditorExpertAdText($entry['badgeText'] ?? ($entry['badge'] ?? ''), 12);
        if ($badgeText === '') {
            $badgeText = '广告';
        }
        $displayViewCount = $this->managedDrawEditorExpertAdViewCount($region, $segmentNo, $slotIndex, $incrementView);
        $titleTextStyle = $stylePost !== array() ? $this->app->posts()->displayTitleTextStyle($stylePost) : '';

        $itemNode = $document->createElement('div');
        $itemNode->setAttribute('class', 'expert-item-card expert-ad-slot-card bg-white p-4 rounded-xl');
        $itemNode->setAttribute('data-expert-ad-slot', '1');
        $itemNode->setAttribute('data-expert-ad-slot-index', (string) max(1, $slotIndex));
        if ($linkUrl !== '') {
            $itemNode->setAttribute('data-ad-url', $linkUrl);
        }

        $mainNode = $document->createElement('span');
        $mainNode->setAttribute('class', 'expert-item-main');

        $issuePrefixNode = $document->createElement('span');
        $issuePrefixNode->setAttribute('class', 'issue-prefix issue-prefix-expert');
        $issuePrefixText = $this->managedDrawEditorCurrentIssuePrefixText($region);
        if ($issuePrefixText !== '') {
            $issuePrefixNode->appendChild($document->createTextNode($issuePrefixText));
        }
        if (preg_match('/(?:^|;)font-size\s*:\s*(\d+)px\s*;/i', $titleTextStyle, $fontSizeMatch)) {
            $issuePrefixNode->setAttribute('style', 'font-size:' . (int) $fontSizeMatch[1] . 'px;');
        }
        $mainNode->appendChild($issuePrefixNode);

        $titleNode = $document->createElement('span');
        $titleNode->setAttribute('class', 'expert-item-title');
        $this->appendManagedDrawEditorExpertAdTitleSegments($document, $titleNode, $entry, $titleTextStyle);
        $mainNode->appendChild($titleNode);

        if ($linkUrl !== '') {
            $linkNode = $document->createElement('a');
            $linkNode->setAttribute('href', $linkUrl);
            $linkNode->setAttribute('data-front-ad-link', '1');
            $linkNode->setAttribute('data-front-flood-bypass', '1');
            $linkNode->setAttribute('data-no-prefetch', '1');
        } else {
            $linkNode = $document->createElement('span');
        }
        $linkNode->setAttribute('class', 'expert-item-link');
        $linkNode->setAttribute('style', $this->managedDrawEditorExpertLinkStyle((string) $linkNode->getAttribute('style')));
        $linkNode->appendChild($mainNode);
        $itemNode->appendChild($linkNode);

        $metaNode = $document->createElement('div');
        $metaNode->setAttribute('class', 'expert-item-meta');

        $badgeNode = $document->createElement('span');
        $badgeNode->setAttribute('class', 'expert-item-result expert-ad-slot-badge');
        $badgeNode->appendChild($document->createTextNode($badgeText));
        $metaNode->appendChild($badgeNode);

        $viewNode = $document->createElement('span');
        $viewNode->setAttribute('class', 'expert-view-count');
        $viewNode->setAttribute('data-post-view-count', (string) $displayViewCount);
        $viewNode->setAttribute('data-expert-ad-view-count', (string) $displayViewCount);
        $viewNode->setAttribute('aria-label', '浏览量 ' . $displayViewCount);

        $iconNode = $document->createElement('span');
        $iconNode->setAttribute('class', 'expert-view-icon');
        $iconNode->setAttribute('aria-hidden', 'true');
        $iconNode->appendChild($document->createTextNode('👁‍🗨'));
        $viewNode->appendChild($iconNode);

        $countNode = $document->createElement('span');
        $countNode->setAttribute('class', 'expert-view-number');
        $countNode->appendChild($document->createTextNode((string) $displayViewCount));
        $viewNode->appendChild($countNode);

        $metaNode->appendChild($viewNode);
        $itemNode->appendChild($metaNode);

        return $itemNode;
    }

    protected function hydrateManagedDrawEditorExpertSection(string $region, string $sectionHtml, array $posts, int $colorIndexOffset = 0, int $segmentNo = 1, bool $incrementAdViews = false): string
    {
        $sectionHtml = trim($sectionHtml);
        if ($sectionHtml === '' || $posts === array()) {
            return $sectionHtml;
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="managed-draw-expert-root">' . $sectionHtml . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if (!$loaded) {
            return $sectionHtml;
        }

        $xpath = new \DOMXPath($document);
        $rootNode = $document->getElementById('managed-draw-expert-root');
        if (!$rootNode instanceof \DOMElement) {
            return $sectionHtml;
        }

        $frameNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " data-frame ")]', $rootNode)->item(0);
        if (!$frameNode instanceof \DOMElement) {
            return $sectionHtml;
        }

        $sectionNode = $xpath->query('.//section', $rootNode)->item(0);
        if (!$sectionNode instanceof \DOMElement) {
            $sectionNode = $frameNode;
        }
        $adInterval = $this->managedDrawEditorExpertAdInterval($sectionNode);
        $adItems = $this->managedDrawEditorExpertAdItems($sectionNode);
        if ($adInterval <= 0 && $adItems !== array()) {
            $rawAdInterval = trim((string) $sectionNode->getAttribute('data-expert-ad-interval'));
            if (!$sectionNode->hasAttribute('data-expert-ad-interval') || $rawAdInterval === '') {
                $adInterval = 1;
            }
        }
        $maxAdSlotCount = count($adItems);
        $adSlotIndex = 0;

        $itemTemplates = array();
        foreach ($frameNode->childNodes as $childNode) {
            if ($childNode instanceof \DOMElement && !$this->isManagedDrawEditorExpertAdSlotNode($childNode)) {
                $itemTemplates[] = $childNode->cloneNode(true);
            }
        }

        if ($itemTemplates === array()) {
            return $sectionHtml;
        }

        while ($frameNode->firstChild) {
            $frameNode->removeChild($frameNode->firstChild);
        }

        $lastTemplateIndex = count($itemTemplates) - 1;
        foreach (array_values($posts) as $index => $post) {
            $colorIndex = $colorIndexOffset + $index;
            $templateIndex = $index <= $lastTemplateIndex ? $index : $lastTemplateIndex;
            $itemNode = $itemTemplates[$templateIndex]->cloneNode(true);
            $normalizedItemNode = $this->normalizeManagedDrawEditorExpertItemNode($document, $itemNode);
            if ($normalizedItemNode instanceof \DOMNode) {
                $itemNode = $normalizedItemNode;
            }

            $itemRebuilt = $this->hydrateManagedDrawEditorExpertItem($document, $xpath, $itemNode, (array) $post, $index, $region, $colorIndex);
            if (!$itemRebuilt) {
                $this->hydrateManagedDrawEditorExpertResultNode($document, $itemNode, $index, $colorIndex, (array) $post);
            }
            $frameNode->appendChild($itemNode);

            if ($adInterval > 0 && $maxAdSlotCount > 0 && $adSlotIndex < $maxAdSlotCount && (($index + 1) % $adInterval) === 0) {
                $adSlotIndex += 1;
                $frameNode->appendChild($this->buildManagedDrawEditorExpertAdSlotNode($document, $sectionNode, $adSlotIndex, $adItems, $region, (array) $post, $segmentNo, $incrementAdViews));
            }
        }

        $resultHtml = '';
        foreach ($rootNode->childNodes as $childNode) {
            $resultHtml .= $document->saveHTML($childNode);
        }

        return trim($resultHtml) !== '' ? trim($resultHtml) : $sectionHtml;
    }

    protected function hydrateManagedDrawEditorExpertEmptySection(string $sectionHtml): string
    {
        $sectionHtml = trim($sectionHtml);
        if ($sectionHtml === '') {
            return $sectionHtml;
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="managed-draw-expert-empty-root">' . $sectionHtml . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if (!$loaded) {
            return $sectionHtml;
        }

        $xpath = new \DOMXPath($document);
        $rootNode = $document->getElementById('managed-draw-expert-empty-root');
        if (!$rootNode instanceof \DOMElement) {
            return $sectionHtml;
        }

        $frameNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " data-frame ")]', $rootNode)->item(0);
        if (!$frameNode instanceof \DOMElement) {
            return $sectionHtml;
        }

        while ($frameNode->firstChild) {
            $frameNode->removeChild($frameNode->firstChild);
        }

        $emptyNode = $document->createElement('div');
        $emptyNode->setAttribute('class', 'flex justify-center items-center bg-white p-4 rounded-xl');
        $emptyNode->setAttribute('style', 'color: #64748b; font-weight: 600;');
        $emptyNode->appendChild($document->createTextNode('请发表帖子··· ···'));
        $frameNode->appendChild($emptyNode);

        $resultHtml = '';
        foreach ($rootNode->childNodes as $childNode) {
            $resultHtml .= $document->saveHTML($childNode);
        }

        return trim($resultHtml) !== '' ? trim($resultHtml) : $sectionHtml;
    }

    protected function linkManagedDrawEditorExpertSection(string $region, string $sectionHtml, array $posts): string
    {
        $sectionHtml = trim($sectionHtml);
        if ($sectionHtml === '' || $posts === array()) {
            return $sectionHtml;
        }

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="managed-draw-expert-link-root">' . $sectionHtml . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if (!$loaded) {
            return $sectionHtml;
        }

        $xpath = new \DOMXPath($document);
        $rootNode = $document->getElementById('managed-draw-expert-link-root');
        if (!$rootNode instanceof \DOMElement) {
            return $sectionHtml;
        }

        $frameNode = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " data-frame ")]', $rootNode)->item(0);
        if (!$frameNode instanceof \DOMElement) {
            return $sectionHtml;
        }

        $itemNodes = array();
        foreach ($frameNode->childNodes as $childNode) {
            if ($childNode instanceof \DOMElement && !$this->isManagedDrawEditorExpertAdSlotNode($childNode)) {
                $itemNodes[] = $childNode;
            }
        }

        if ($itemNodes === array()) {
            return $sectionHtml;
        }

        $posts = array_values($posts);
        $maxIndex = min(count($itemNodes), count($posts));

        for ($index = 0; $index < $maxIndex; $index += 1) {
            $href = $this->managedDrawEditorExpertPostUrl((array) $posts[$index], $region);
            if ($href === '') {
                continue;
            }

            $itemNode = $itemNodes[$index];
            $normalizedItemNode = $this->normalizeManagedDrawEditorExpertItemNode($document, $itemNode);
            if ($normalizedItemNode instanceof \DOMNode) {
                $itemNode = $normalizedItemNode;
            }

            $linkedNode = $this->managedDrawEditorExpertLinkNode($document, $itemNode, $href);
            if ($linkedNode !== $itemNode && $itemNode->parentNode === $frameNode) {
                $frameNode->replaceChild($linkedNode, $itemNode);
            }
        }

        $resultHtml = '';
        foreach ($rootNode->childNodes as $childNode) {
            $resultHtml .= $document->saveHTML($childNode);
        }

        return trim($resultHtml) !== '' ? trim($resultHtml) : $sectionHtml;
    }

    protected function hydrateManagedDrawEditorExpertSections(string $region, string $contentHtml, array $groupedPosts = array(), bool $incrementAdViews = false): string
    {
        $contentHtml = trim($contentHtml);
        if ($contentHtml === '') {
            return $contentHtml;
        }

        if ($groupedPosts === array()) {
            $groupedPosts = $this->homeExpertPosts($region);
        }
        $colorIndexOffset = 0;
        foreach ($this->managedExpertSectionsForMapping($contentHtml) as $section) {
            $segmentNo = (int) ($section['segment_no'] ?? 0);
            $sectionHtml = trim((string) ($section['html'] ?? ''));

            if ($segmentNo < 1 || $segmentNo > 3 || $sectionHtml === '') {
                continue;
            }

            if (empty($groupedPosts[$segmentNo])) {
                $replacementHtml = $this->hydrateManagedDrawEditorExpertEmptySection($sectionHtml);
            } else {
                $sectionPosts = (array) $groupedPosts[$segmentNo];
                $replacementHtml = $this->hydrateManagedDrawEditorExpertSection($region, $sectionHtml, $sectionPosts, $colorIndexOffset, $segmentNo, $incrementAdViews);
                $colorIndexOffset += count($sectionPosts);
            }

            if ($replacementHtml === '' || $replacementHtml === $sectionHtml) {
                continue;
            }

            $position = strpos($contentHtml, $sectionHtml);
            if ($position === false) {
                continue;
            }

            $contentHtml = substr($contentHtml, 0, $position) . $replacementHtml . substr($contentHtml, $position + strlen($sectionHtml));
        }

        return trim($contentHtml);
    }

    protected function linkManagedDrawEditorExpertSections(string $region, string $contentHtml, array $groupedPosts = array()): string
    {
        $contentHtml = trim($contentHtml);
        if ($contentHtml === '') {
            return $contentHtml;
        }

        if ($groupedPosts === array()) {
            $groupedPosts = $this->homeExpertPosts($region);
        }
        foreach ($this->managedExpertSectionsForMapping($contentHtml) as $section) {
            $segmentNo = (int) ($section['segment_no'] ?? 0);
            $sectionHtml = trim((string) ($section['html'] ?? ''));

            if ($segmentNo < 1 || $segmentNo > 3 || $sectionHtml === '' || empty($groupedPosts[$segmentNo])) {
                continue;
            }

            $replacementHtml = $this->linkManagedDrawEditorExpertSection($region, $sectionHtml, (array) $groupedPosts[$segmentNo]);
            if ($replacementHtml === '' || $replacementHtml === $sectionHtml) {
                continue;
            }

            $position = strpos($contentHtml, $sectionHtml);
            if ($position === false) {
                continue;
            }

            $contentHtml = substr($contentHtml, 0, $position) . $replacementHtml . substr($contentHtml, $position + strlen($sectionHtml));
        }

        return trim($contentHtml);
    }

    public function linkManagedDrawExpertLinks(string $region, string $contentHtml): string
    {
        $region = $this->normalizeManagedDrawMaterialRegion($region);
        $contentHtml = $this->linkManagedDrawEditorExpertSections($region, $contentHtml);
        $contentHtml = $this->repairManagedDrawMaterialCalendarPanelClosers($contentHtml);
        $contentHtml = $this->repairManagedDrawMaterialOrphanSectionClosers($contentHtml);

        return trim($contentHtml);
    }

    public function syncManagedDrawExpertLinks(string $region, string $contentHtml, bool $incrementAdViews = false): string
    {
        $region = $this->normalizeManagedDrawMaterialRegion($region);
        $contentHtml = trim($contentHtml);
        if ($contentHtml === '') {
            return $contentHtml;
        }

        $groupedPosts = $this->homeExpertPosts($region);
        $hydratedHtml = $this->hydrateManagedDrawEditorExpertSections($region, $contentHtml, $groupedPosts, $incrementAdViews);
        if ($hydratedHtml !== '' && $hydratedHtml !== $contentHtml) {
            $contentHtml = $hydratedHtml;
        } else {
            $contentHtml = $this->linkManagedDrawEditorExpertSections($region, $contentHtml, $groupedPosts);
        }
        if ($incrementAdViews) {
            $this->persistManagedDrawEditorExpertAdViewState($region);
        }

        $contentHtml = $this->repairManagedDrawMaterialCalendarPanelClosers($contentHtml);
        $contentHtml = $this->repairManagedDrawMaterialOrphanSectionClosers($contentHtml);

        return $contentHtml;
    }

    protected function linkManagedDrawAdItems(string $contentHtml, string $region = ''): string
    {
        $contentHtml = trim($contentHtml);
        if ($contentHtml === '') {
            return $contentHtml;
        }
        $contentHadEditableMaterial = $this->managedDrawMaterialEditableHtmlLooksComplete($contentHtml);
        $contentHtml = $this->repairManagedDrawMaterialOrphanSectionClosers($contentHtml);

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="managed-draw-ad-link-root">' . $contentHtml . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if (!$loaded) {
            return $contentHtml;
        }

        $xpath = new \DOMXPath($document);
        $rootNode = $document->getElementById('managed-draw-ad-link-root');
        if (!$rootNode instanceof \DOMElement) {
            return $contentHtml;
        }

        $itemNodes = array();
        foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " ad-item ") and @data-ad-url]', $rootNode) as $itemNode) {
            if ($itemNode instanceof \DOMElement) {
                $itemNodes[] = $itemNode;
            }
        }

        if ($itemNodes !== array()) {
            foreach ($itemNodes as $itemNode) {
                $href = trim((string) $itemNode->getAttribute('data-ad-url'));
                if ($href === '') {
                    continue;
                }

                $linkedNode = $this->managedDrawEditorAdLinkNode($document, $itemNode, $href);
                if ($linkedNode !== $itemNode && $itemNode->parentNode instanceof \DOMNode) {
                    $itemNode->parentNode->replaceChild($linkedNode, $itemNode);
                }
            }
        }

        $adIssuePrefixText = '';
        if (in_array($region, array('macau', 'hongkong'), true)) {
            $snapshot = $this->managedIssuePrefixSnapshotByRegion($region);
            $adIssuePrefixText = $this->app->posts()->formatIssuePrefixText(
                (string) ($snapshot['issue_prefix_tail'] ?? '')
            );
        }

        if ($adIssuePrefixText !== '') {
            foreach ($xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " issue-prefix-ad ")]', $rootNode) as $prefixNode) {
                if (!$prefixNode instanceof \DOMElement) {
                    continue;
                }

                while ($prefixNode->firstChild) {
                    $prefixNode->removeChild($prefixNode->firstChild);
                }
                $prefixNode->removeAttribute('data-post-issue-prefix');
                $prefixNode->appendChild($document->createTextNode($adIssuePrefixText));
            }
        }

        $resultHtml = '';
        foreach ($rootNode->childNodes as $childNode) {
            $resultHtml .= $document->saveHTML($childNode);
        }
        $resultHtml = trim($resultHtml);
        $resultHtml = $this->repairManagedDrawMaterialCalendarPanelClosers($resultHtml);
        $resultHtml = $this->repairManagedDrawMaterialOrphanSectionClosers($resultHtml);

        if ($contentHadEditableMaterial && !$this->managedDrawMaterialEditableHtmlLooksComplete($resultHtml)) {
            return $contentHtml;
        }

        return $resultHtml !== '' ? $resultHtml : $contentHtml;
    }

    public function syncManagedDrawAdLinks(string $contentHtml, string $region = ''): string
    {
        $region = in_array($region, array('macau', 'hongkong'), true)
            ? $this->normalizeManagedDrawMaterialRegion($region)
            : '';

        $contentHtml = $this->linkManagedDrawAdItems($contentHtml, $region);
        $contentHtml = $this->repairManagedDrawMaterialCalendarPanelClosers($contentHtml);
        $contentHtml = $this->repairManagedDrawMaterialOrphanSectionClosers($contentHtml);

        return $contentHtml;
    }

    protected function managedDrawLiveBlockPatterns(): array
    {
        return array(
            '~\s*<div\b[^>]*\bid=(["\'])section-live\1[^>]*>[\s\S]*?</div>\s*(?=<div\b[^>]*\bclass=(["\'])[^"\']*\bmarquee\b)~iu',
            '~\s*<div\b[^>]*\bid=(["\'])section-live\1[^>]*>[\s\S]*?</div>\s*(?=</div>\s*</section>)~iu',
        );
    }

    protected function managedDrawMarqueeBlockPattern(): string
    {
        return '/<div\b[^>]*\bclass=["\'][^"\']*\bmarquee\b[^"\']*["\'][^>]*>[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/iu';
    }

    protected function managedDrawCalendarBlockPattern(): string
    {
        return '/<section\b[^>]*\bclass=["\'][^"\']*\bcalendar-panel\b[^"\']*["\'][^>]*>[\s\S]*?<\/section>/iu';
    }

    protected function managedDrawLiveBlockRange(string $html): array
    {
        if (!preg_match('/<div\b[^>]*\bid=(["\'])section-live\1[^>]*>/iu', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return array();
        }

        $start = (int) ($matches[0][1] ?? -1);
        $openTag = (string) ($matches[0][0] ?? '');
        if ($start < 0 || $openTag === '') {
            return array();
        }

        $offset = $start + strlen($openTag);
        $depth = 1;
        if (!preg_match_all('/<div\b[^>]*>|<\/div>/iu', $html, $tagMatches, PREG_OFFSET_CAPTURE, $offset)) {
            return array();
        }

        foreach ($tagMatches[0] as $tagMatch) {
            $tag = (string) ($tagMatch[0] ?? '');
            $tagOffset = (int) ($tagMatch[1] ?? -1);
            if ($tagOffset < 0 || $tag === '') {
                continue;
            }

            if (stripos($tag, '</div') === 0) {
                $depth--;
            } else {
                $depth++;
            }

            if ($depth === 0) {
                $end = $tagOffset + strlen($tag);

                return array(
                    'offset' => $start,
                    'length' => $end - $start,
                    'html' => substr($html, $start, $end - $start),
                );
            }
        }

        return array();
    }

    protected function extractManagedDrawLiveBlock(string $html): string
    {
        $range = $this->managedDrawLiveBlockRange($html);
        if ($range !== array()) {
            return trim((string) ($range['html'] ?? ''));
        }

        foreach ($this->managedDrawLiveBlockPatterns() as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                return trim((string) ($matches[0] ?? ''));
            }
        }

        return '';
    }

    protected function removeManagedDrawLiveBlock(string $html): string
    {
        $range = $this->managedDrawLiveBlockRange($html);
        if ($range !== array()) {
            return trim(substr($html, 0, (int) $range['offset']) . substr($html, (int) $range['offset'] + (int) $range['length']));
        }

        foreach ($this->managedDrawLiveBlockPatterns() as $pattern) {
            if (preg_match($pattern, $html)) {
                return trim((string) preg_replace($pattern, '', $html, 1));
            }
        }

        return trim($html);
    }

    protected function replaceManagedDrawLiveBlock(string $html, string $replacement): string
    {
        $replacement = trim($replacement);
        if ($replacement === '') {
            return trim($html);
        }

        $range = $this->managedDrawLiveBlockRange($html);
        if ($range !== array()) {
            return trim(substr($html, 0, (int) $range['offset']) . "\n" . $replacement . "\n" . substr($html, (int) $range['offset'] + (int) $range['length']));
        }

        foreach ($this->managedDrawLiveBlockPatterns() as $pattern) {
            if (!preg_match($pattern, $html)) {
                continue;
            }

            return trim((string) preg_replace_callback(
                $pattern,
                static function () use ($replacement) {
                    return "\n" . $replacement . "\n";
                },
                $html,
                1
            ));
        }

        return trim($html);
    }

    public function moveManagedDrawLiveBlockBelowHomeSection(string $html): string
    {
        $html = trim($html);
        if ($html === '' || stripos($html, 'section-live') === false || stripos($html, 'section-home') === false) {
            return $html;
        }

        $liveBlock = $this->extractManagedDrawLiveBlock($html);
        if ($liveBlock === '') {
            return $html;
        }

        $withoutLiveBlock = $this->removeManagedDrawLiveBlock($html);
        if (stripos($withoutLiveBlock, 'section-live') !== false) {
            return trim($withoutLiveBlock);
        }

        return trim($this->insertManagedDrawMaterialBlockAfter(
            $withoutLiveBlock,
            '/<section\b[^>]*\bid=["\']section-home["\'][^>]*>[\s\S]*?<\/section>/iu',
            $liveBlock
        ));
    }

    public function ensureManagedDrawMarqueeBlock(string $html, string $fallbackHtml): string
    {
        $html = trim($html);
        $fallbackHtml = trim($fallbackHtml);
        if ($html === '' || $fallbackHtml === '' || preg_match($this->managedDrawMarqueeBlockPattern(), $html)) {
            return $html;
        }

        $marqueeBlock = $this->extractManagedDrawMaterialBlock($fallbackHtml, $this->managedDrawMarqueeBlockPattern());
        if ($marqueeBlock === '') {
            return $html;
        }

        $liveRange = $this->managedDrawLiveBlockRange($html);
        if ($liveRange !== array()) {
            $insertOffset = (int) $liveRange['offset'] + (int) $liveRange['length'];

            return trim(substr($html, 0, $insertOffset) . "\n" . $marqueeBlock . "\n" . substr($html, $insertOffset));
        }

        return trim($this->insertManagedDrawMaterialBlockAfter(
            $html,
            '/<section\b[^>]*\bid=["\']section-home["\'][^>]*>[\s\S]*?<\/section>/iu',
            $marqueeBlock
        ));
    }

    public function ensureManagedDrawLiveBadgeHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '' || stripos($html, 'section-live') === false || stripos($html, 'hero-result-period') === false) {
            return $html;
        }

        $liveBlock = $this->extractManagedDrawLiveBlock($html);
        if ($liveBlock === '' || stripos($liveBlock, 'hero-live-badge') !== false) {
            return $html;
        }

        $badgeHtml = '<button id="hero-mode-badge" type="button" class="hero-live-badge" aria-live="polite" title="点击查看开奖记录"><i id="hero-mode-icon" class="fa-solid fa-clock-rotate-left"></i><span id="hero-mode-label">开奖记录</span></button>';
        $updatedLiveBlock = (string) preg_replace(
            '/(<div\b(?=[^>]*\bclass=(["\'])[^"\']*\bhero-live-left\b[^"\']*\2)[^>]*>\s*)(?=<span\b[^>]*\bid=(["\'])hero-result-period\3)/iu',
            '$1' . $badgeHtml . ' ',
            $liveBlock,
            1,
            $replaceCount
        );

        if ((int) $replaceCount <= 0) {
            $updatedLiveBlock = (string) preg_replace(
                '/(?=<span\b[^>]*\bid=(["\'])hero-result-period\1)/iu',
                $badgeHtml . ' ',
                $liveBlock,
                1,
                $replaceCount
            );
        }

        if ((int) $replaceCount <= 0 || $updatedLiveBlock === $liveBlock) {
            return $html;
        }

        return $this->replaceManagedDrawLiveBlock($html, $updatedLiveBlock);
    }

    public function normalizeManagedDrawProtectedHeaderBlocks(string $html, string $fallbackHtml): string
    {
        $html = trim($html);
        $fallbackHtml = trim($fallbackHtml);
        if ($html === '') {
            return '';
        }

        $homeSectionPattern = '/<section\b[^>]*\bid=["\']section-home["\'][^>]*>[\s\S]*?<\/section>/iu';
        $homeBlock = $this->extractManagedDrawMaterialBlock($html, $homeSectionPattern);
        if ($homeBlock === '') {
            return $this->ensureManagedDrawMarqueeBlock($this->moveManagedDrawLiveBlockBelowHomeSection($html), $fallbackHtml);
        }

        $liveBlock = $this->extractManagedDrawLiveBlock($html);
        if ($liveBlock === '' && $fallbackHtml !== '') {
            $liveBlock = $this->extractManagedDrawLiveBlock($fallbackHtml);
        }
        if ($liveBlock !== '') {
            $liveBlock = $this->ensureManagedDrawLiveBadgeHtml($liveBlock);
        }

        $marqueeBlock = $this->extractManagedDrawMaterialBlock($html, $this->managedDrawMarqueeBlockPattern());
        if ($marqueeBlock === '' && $fallbackHtml !== '') {
            $marqueeBlock = $this->extractManagedDrawMaterialBlock($fallbackHtml, $this->managedDrawMarqueeBlockPattern());
        }

        $calendarBlock = $this->extractManagedDrawMaterialBlock($html, $this->managedDrawCalendarBlockPattern());
        if ($calendarBlock === '' && $fallbackHtml !== '') {
            $calendarBlock = $this->extractManagedDrawMaterialBlock($fallbackHtml, $this->managedDrawCalendarBlockPattern());
        }

        $contentHtml = $this->replaceManagedDrawMaterialBlock($html, $homeSectionPattern, '');
        $contentHtml = $this->removeManagedDrawLiveBlock($contentHtml);
        $contentHtml = (string) preg_replace($this->managedDrawMarqueeBlockPattern(), '', $contentHtml);
        $contentHtml = (string) preg_replace($this->managedDrawCalendarBlockPattern(), '', $contentHtml);
        $contentHtml = (string) preg_replace('~^\s*(?:</div>\s*</section>|</section>|</div>)+\s*~iu', '', $contentHtml);

        $blocks = array($homeBlock);
        if ($liveBlock !== '') {
            $blocks[] = $liveBlock;
        }
        if ($marqueeBlock !== '') {
            $blocks[] = $marqueeBlock;
        }
        if ($calendarBlock !== '') {
            $blocks[] = $calendarBlock;
        }
        if (trim($contentHtml) !== '') {
            $blocks[] = trim($contentHtml);
        }

        return trim(implode("\n", $blocks));
    }

    public function stripManagedDrawHeroCopy(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = (string) preg_replace(
            '/\s*<div\b[^>]*\bid=(["\'])hero-library-pill\1[^>]*>[\s\S]*?(?=\s*<h1\b[^>]*\bid=(["\'])hero-main-title\2)/iu',
            '',
            $html,
            1
        );
        $html = (string) preg_replace('/\s*<h1\b[^>]*\bid=(["\'])hero-main-title\1[^>]*>[\s\S]*?<\/h1>/iu', '', $html);
        $html = (string) preg_replace('/\s*<p\b[^>]*\bid=(["\'])hero-main-subtitle\1[^>]*>[\s\S]*?<\/p>/iu', '', $html);

        return trim($html);
    }

    protected function managedDrawMaterialProtectedBlockPatterns(): array
    {
        return array_merge(array(
            '/<section\b[^>]*\bid=["\']section-home["\'][^>]*>[\s\S]*?<\/section>/iu',
        ), $this->managedDrawLiveBlockPatterns(), array(
            $this->managedDrawMarqueeBlockPattern(),
            $this->managedDrawCalendarBlockPattern(),
        ));
    }

    protected function managedDrawMaterialProtectedBoundary(string $html): int
    {
        $boundary = 0;

        foreach ($this->managedDrawMaterialProtectedBlockPatterns() as $pattern) {
            if (!preg_match($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
                continue;
            }

            $matchedHtml = (string) ($matches[0][0] ?? '');
            $matchedOffset = (int) ($matches[0][1] ?? 0);
            $boundary = max($boundary, $matchedOffset + strlen($matchedHtml));
        }

        return $boundary;
    }

    protected function removeManagedDrawMaterialProtectedBlocks(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        foreach ($this->managedDrawMaterialProtectedBlockPatterns() as $pattern) {
            $html = (string) preg_replace($pattern, '', $html);
        }

        return trim($html);
    }

    protected function managedDrawHomeSectionRootAttribute(string $sectionHtml, string $attribute): string
    {
        if (!preg_match('/<section\b[^>]*\bid=["\']section-home["\'][^>]*>/iu', $sectionHtml, $matches)) {
            return '';
        }

        $tagHtml = (string) ($matches[0] ?? '');
        if (!preg_match('/\s' . preg_quote($attribute, '/') . '\s*=\s*("([^"]*)"|\'([^\']*)\')/iu', $tagHtml, $attrMatches)) {
            return '';
        }

        return html_entity_decode((string) (($attrMatches[2] ?? '') !== '' ? $attrMatches[2] : ($attrMatches[3] ?? '')), ENT_QUOTES, 'UTF-8');
    }

    protected function managedDrawValidHomeSectionStyle(string $sectionHtml): string
    {
        $styleValue = trim($this->managedDrawHomeSectionRootAttribute($sectionHtml, 'style'));
        if ($styleValue === '') {
            return '';
        }

        $normalizedStyle = $this->normalizeManagedDrawInlineStyle($styleValue);
        if ($normalizedStyle === '') {
            return '';
        }

        $styleForCheck = html_entity_decode($normalizedStyle, ENT_QUOTES, 'UTF-8');
        if (stripos($styleForCheck, 'url(') !== false) {
            if (stripos($styleForCheck, '&amp') !== false) {
                return '';
            }

            if (!preg_match('~url\(\s*(["\']?)/public/uploads/material/[^)"\']+\.(?:jpg|jpeg|png|gif|webp|bmp)\1\s*\)~iu', $styleForCheck)) {
                return '';
            }
        }

        return $normalizedStyle;
    }

    protected function replaceManagedDrawHomeSectionRootAttributes(string $sectionHtml, string $styleValue, bool $locked): string
    {
        return (string) preg_replace_callback(
            '/<section\b([^>]*)\bid=(["\'])section-home\2([^>]*)>/iu',
            function ($matches) use ($styleValue, $locked) {
                $attributes = trim((string) (($matches[1] ?? '') . ' id="section-home" ' . ($matches[3] ?? '')));
                $attributes = (string) preg_replace('/\sstyle\s*=\s*("[^"]*"|\'[^\']*\')/iu', '', $attributes);
                $attributes = (string) preg_replace('/\sdata-section-edit-locked\s*=\s*("[^"]*"|\'[^\']*\')/iu', '', $attributes);
                $attributes = trim((string) preg_replace('/\s+/', ' ', $attributes));

                if ($styleValue !== '') {
                    $attributes .= ' style="' . htmlspecialchars($styleValue, ENT_QUOTES, 'UTF-8') . '"';
                }
                if ($locked) {
                    $attributes .= ' data-section-edit-locked="1"';
                }

                return '<section ' . trim($attributes) . '>';
            },
            $sectionHtml,
            1
        );
    }

    protected function normalizeManagedDrawHomeSection(string $contentHtml, string $baseHtml): string
    {
        $homeSectionPattern = '/<section\b[^>]*\bid=["\']section-home["\'][^>]*>[\s\S]*?<\/section>/iu';
        $currentSection = $this->extractManagedDrawMaterialBlock($contentHtml, $homeSectionPattern);
        $baseSection = $this->extractManagedDrawMaterialBlock($baseHtml, $homeSectionPattern);
        if ($currentSection === '' || $baseSection === '') {
            return $contentHtml;
        }

        $styleValue = $this->managedDrawValidHomeSectionStyle($currentSection);
        $locked = trim($this->managedDrawHomeSectionRootAttribute($currentSection, 'data-section-edit-locked')) === '1';
        $replacement = $this->replaceManagedDrawHomeSectionRootAttributes($currentSection, $styleValue, $locked);

        return $this->replaceManagedDrawMaterialBlock($contentHtml, $homeSectionPattern, $replacement);
    }

    protected function isManagedDrawMaterialFullHtml(string $html): bool
    {
        $html = trim($html);

        return $html !== '' && preg_match('/<section\b[^>]*\bid=["\']section-home["\']/iu', $html) === 1;
    }

    protected function hasManagedDrawMaterialDuplicatedShell(string $html): bool
    {
        $html = trim($html);
        if ($html === '') {
            return false;
        }

        return preg_match_all('/<section\b[^>]*\bid=["\']section-home["\']/iu', $html) > 1;
    }

    protected function extractManagedDrawMaterialEditableHtml(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $boundary = $this->managedDrawMaterialProtectedBoundary($html);
        if ($boundary <= 0) {
            return $html;
        }

        if ($boundary >= strlen($html)) {
            return '';
        }

        return trim((string) substr($html, $boundary));
    }

    public function managedDrawMaterialHasEditableContent(string $html): bool
    {
        return $this->managedDrawMaterialEditableHtmlLooksComplete($html);
    }

    protected function managedDrawMaterialEditableHtmlLooksComplete(string $html): bool
    {
        $html = trim($html);
        if ($html === '') {
            return false;
        }

        $editableHtml = $this->removeManagedDrawMaterialSectionSpacerParagraphs($this->removeManagedDrawMaterialProtectedBlocks($html));
        if ($editableHtml === '') {
            return false;
        }

        $plainText = trim((string) preg_replace('/\s+/u', '', strip_tags($editableHtml)));
        if ($plainText === '') {
            return false;
        }

        if (preg_match('/\b(?:section-title|data-frame|expert-item-card|zodiac-reference-card|zodiac-attr-list|home-vip-title)\b/u', $editableHtml)) {
            return true;
        }

        return preg_match('/<section\b(?![^>]*\bid=["\']section-home["\'])[\s\S]*?<\/section>/iu', $editableHtml) === 1;
    }

    protected function mergeManagedDrawMaterialHtml(string $baseHtml, string $editableHtml): string
    {
        $baseHtml = trim($baseHtml);
        $editableHtml = trim($editableHtml);

        if ($baseHtml === '') {
            return $editableHtml;
        }

        $boundary = $this->managedDrawMaterialProtectedBoundary($baseHtml);
        if ($boundary <= 0 || $boundary >= strlen($baseHtml)) {
            return trim($baseHtml . ($editableHtml !== '' ? "\n" . $editableHtml : ''));
        }

        $prefixHtml = rtrim((string) substr($baseHtml, 0, $boundary));
        if ($editableHtml === '') {
            return trim($prefixHtml);
        }

        return trim($prefixHtml . "\n" . $editableHtml);
    }

    protected function ensureManagedDrawMaterialEditableHtml(string $region, string $html, string $primaryFallbackHtml = '', string $secondaryFallbackHtml = ''): string
    {
        $html = trim($html);
        if ($html === '') {
            return $html;
        }

        foreach (array($primaryFallbackHtml, $secondaryFallbackHtml) as $fallbackHtml) {
            $fallbackHtml = trim((string) $fallbackHtml);
            if ($fallbackHtml === '') {
                continue;
            }

            $fallbackHtml = $this->sanitizeManagedDrawMaterialHtml($fallbackHtml);
            $fallbackHtml = $this->stripManagedDrawHeroCopy($fallbackHtml);
            $fallbackHtml = $this->removeManagedDrawMaterialSectionSpacerParagraphs($fallbackHtml);
            $backfilledHtml = $this->backfillManagedDrawMaterialStaticSections($html, $fallbackHtml);
            if ($backfilledHtml !== $html) {
                $html = $this->moveManagedDrawLiveBlockBelowHomeSection($backfilledHtml);
                $html = $this->syncManagedDrawExpertLinks($region, $html);
                $html = $this->removeManagedDrawMaterialSectionSpacerParagraphs($html);
                $html = $this->normalizeManagedDrawZodiacReferenceHeads($html);
            }

            if ($this->managedDrawMaterialEditableHtmlLooksComplete($html)) {
                return $html;
            }

            if (!$this->managedDrawMaterialEditableHtmlLooksComplete($fallbackHtml)) {
                continue;
            }

            $editableHtml = $this->removeManagedDrawMaterialProtectedBlocks($fallbackHtml);
            if (!$this->managedDrawMaterialEditableHtmlLooksComplete($editableHtml)) {
                continue;
            }

            $mergedHtml = $this->mergeManagedDrawMaterialHtml($html, $editableHtml);
            $mergedHtml = $this->moveManagedDrawLiveBlockBelowHomeSection($mergedHtml);
            $mergedHtml = $this->syncManagedDrawExpertLinks($region, $mergedHtml);
            $mergedHtml = $this->removeManagedDrawMaterialSectionSpacerParagraphs($mergedHtml);

            return $this->normalizeManagedDrawZodiacReferenceHeads($mergedHtml);
        }

        return $html;
    }

    protected function extractManagedDrawMaterialBlock(string $html, string $pattern): string
    {
        if (!preg_match($pattern, $html, $matches)) {
            return '';
        }

        return trim((string) ($matches[0] ?? ''));
    }

    protected function replaceManagedDrawMaterialBlock(string $html, string $pattern, string $replacement): string
    {
        return (string) preg_replace_callback(
            $pattern,
            static function () use ($replacement) {
                return $replacement;
            },
            $html,
            1
        );
    }

    protected function insertManagedDrawMaterialBlockAfter(string $html, string $anchorPattern, string $blockHtml): string
    {
        return (string) preg_replace_callback(
            $anchorPattern,
            static function ($matches) use ($blockHtml) {
                return (string) ($matches[0] ?? '') . "\n" . $blockHtml;
            },
            $html,
            1
        );
    }

    protected function extractManagedDrawMaterialSectionContaining(string $html, string $marker): string
    {
        $html = trim($html);
        $marker = trim($marker);
        if ($html === '' || $marker === '' || strpos($html, $marker) === false) {
            return '';
        }

        if (!preg_match_all('/<section\b[^>]*>[\s\S]*?<\/section>/iu', $html, $matches)) {
            return '';
        }

        foreach ($matches[0] as $sectionHtml) {
            $sectionHtml = trim((string) $sectionHtml);
            if ($sectionHtml !== '' && strpos($sectionHtml, $marker) !== false) {
                return $sectionHtml;
            }
        }

        return '';
    }

    protected function insertManagedDrawMaterialSectionBeforeMarker(string $html, string $marker, string $blockHtml): string
    {
        $html = trim($html);
        $marker = trim($marker);
        $blockHtml = trim($blockHtml);
        if ($html === '' || $blockHtml === '') {
            return $html;
        }

        if ($marker !== '' && strpos($html, $marker) !== false && preg_match_all('/<section\b[^>]*>[\s\S]*?<\/section>/iu', $html, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                $sectionHtml = (string) ($match[0] ?? '');
                $offset = (int) ($match[1] ?? -1);
                if ($offset >= 0 && strpos($sectionHtml, $marker) !== false) {
                    return trim(substr($html, 0, $offset) . $blockHtml . "\n" . substr($html, $offset));
                }
            }
        }

        if (preg_match('/\s*<nav\b[^>]*class="[^"]*\bbottom-float-nav\b[^"]*"[\s\S]*$/iu', $html, $navMatch, PREG_OFFSET_CAPTURE)) {
            $offset = (int) ($navMatch[0][1] ?? -1);
            if ($offset >= 0) {
                return trim(substr($html, 0, $offset) . "\n" . $blockHtml . substr($html, $offset));
            }
        }

        return trim($html . "\n" . $blockHtml);
    }

    protected function backfillManagedDrawMaterialStaticSections(string $html, string $fallbackHtml): string
    {
        $html = trim($html);
        $fallbackHtml = trim($fallbackHtml);
        if ($html === '' || $fallbackHtml === '') {
            return $html;
        }

        $staticSections = array(
            array('marker' => 'home-vip-title', 'insert_before' => 'section-zodiac'),
            array('marker' => 'section-zodiac', 'insert_before' => '版权信息'),
            array('marker' => '版权信息', 'insert_before' => ''),
        );

        foreach ($staticSections as $section) {
            $marker = (string) ($section['marker'] ?? '');
            if ($marker === '' || strpos($html, $marker) !== false) {
                continue;
            }

            $blockHtml = $this->extractManagedDrawMaterialSectionContaining($fallbackHtml, $marker);
            if ($blockHtml === '') {
                continue;
            }

            $html = $this->insertManagedDrawMaterialSectionBeforeMarker(
                $html,
                (string) ($section['insert_before'] ?? ''),
                $blockHtml
            );
        }

        return trim($html);
    }

    protected function normalizeManagedDrawEditorClassList(string $classList): string
    {
        $tokens = preg_split('/\s+/', trim($classList)) ?: array();
        $filtered = array();
        $editorOnlyClasses = array(
            'editor-sortable-block' => true,
            'editor-sortable-block--floating' => true,
            'editor-sortable-block--control-anchor' => true,
            'editor-section-dragging' => true,
            'editor-section-drop-indicator' => true,
        );

        foreach ($tokens as $token) {
            $token = trim((string) $token);
            if ($token === '' || isset($editorOnlyClasses[$token])) {
                continue;
            }

            if (
                strpos($token, 'mce-') === 0
                || strpos($token, 'tox-') === 0
                || strpos($token, 'editor-section-') === 0
            ) {
                continue;
            }

            $filtered[$token] = true;
        }

        return implode(' ', array_keys($filtered));
    }

    protected function repairManagedDrawMaterialOrphanSectionClosers(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        do {
            $previousHtml = $html;
            $html = (string) preg_replace(
                '~(</section>)\s*</div>\s*</section>(?=\s*<section\b)~iu',
                '$1',
                $html
            );
            $html = (string) preg_replace(
                '~(<div\b(?=[^>]*\bclass=["\'][^"\']*\bmarquee\b)[^>]*>[\s\S]*?</div>\s*</div>\s*</div>)\s*</div>\s*</section>(?=\s*<section\b)~iu',
                '$1',
                $html
            );
            $liveRange = $this->managedDrawLiveBlockRange($html);
            if ($liveRange !== array()) {
                $afterOffset = (int) $liveRange['offset'] + (int) $liveRange['length'];
                $afterHtml = substr($html, $afterOffset);
                if (preg_match('~^\s*</div>\s*</section>(?=\s*<section\b)~iu', $afterHtml, $matches)) {
                    $html = substr($html, 0, $afterOffset) . substr($afterHtml, strlen((string) ($matches[0] ?? '')));
                }
            }
        } while ($html !== $previousHtml);

        return trim($html);
    }

    protected function repairManagedDrawMaterialCalendarPanelClosers(string $html): string
    {
        $html = trim($html);
        if ($html === '' || stripos($html, 'calendar-panel') === false || stripos($html, 'calendar-grid') === false) {
            return $html;
        }

        return trim((string) preg_replace(
            '~(<section\b(?=[^>]*\bclass=["\'][^"\']*\bcalendar-panel\b)[^>]*>\s*<div\b(?=[^>]*\bclass=["\'][^"\']*\bcalendar-grid\b)[^>]*>[\s\S]*?<div\b[^>]*\bid=["\']lunar-date["\'][^>]*>[\s\S]*?</div>\s*<div\b(?=[^>]*\bclass=["\'][^"\']*\bcalendar-lunar-meta\b)[^>]*>[\s\S]*?</div>\s*</div>)\s*(?=<section\b)~iu',
            "$1\n</div>\n</section>\n",
            $html
        ));
    }

    protected function normalizeManagedDrawInlineStyle(string $styleValue, array $removedProperties = array()): string
    {
        $styleValue = $this->normalizeManagedDrawInlineResourceUrls(html_entity_decode(trim($styleValue), ENT_QUOTES, 'UTF-8'));
        if ($styleValue === '') {
            return '';
        }

        $removedLookup = array();
        $normalized = array();
        $declarations = preg_split('/\s*;\s*/', $styleValue) ?: array();

        foreach ($removedProperties as $property) {
            $removedLookup[strtolower((string) $property)] = true;
        }

        foreach ($declarations as $declaration) {
            $parts = explode(':', (string) $declaration, 2);
            $property = isset($parts[0]) ? trim((string) $parts[0]) : '';
            $value = isset($parts[1]) ? trim((string) $parts[1]) : '';
            $propertyKey = strtolower($property);

            if ($propertyKey === '' || $value === '' || isset($removedLookup[$propertyKey])) {
                continue;
            }

            $decodedValue = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            $normalizedValue = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $decodedValue));
            if (
                strpos($normalizedValue, 'expression(') !== false
                || strpos($normalizedValue, 'javascript:') !== false
                || strpos($normalizedValue, 'vbscript:') !== false
                || strpos($normalizedValue, 'data:') !== false
                || strpos($normalizedValue, '@import') !== false
                || $propertyKey === 'behavior'
                || $propertyKey === '-moz-binding'
            ) {
                continue;
            }

            $value = $this->normalizeManagedDrawInlineResourceUrls($value);
            if ($value === '') {
                continue;
            }

            $normalized[$propertyKey] = $value;
        }

        $result = array();
        foreach ($normalized as $property => $value) {
            $result[] = $property . ': ' . $value;
        }

        return implode('; ', $result);
    }

    protected function normalizeManagedDrawInlineResourceUrls(string $styleValue): string
    {
        $styleValue = trim($styleValue);
        if ($styleValue === '') {
            return '';
        }

        $styleValue = (string) preg_replace('/url\(\s*(?:&amp;|&)(?:;)?\s*\)?(?=\s*(?:[;"\']|$))/iu', 'none', $styleValue);

        return (string) preg_replace_callback(
            '/url\(\s*([\'"]?)(.*?)\1\s*\)/iu',
            static function ($matches) {
                $rawValue = html_entity_decode(trim((string) ($matches[2] ?? '')), ENT_QUOTES, 'UTF-8');
                $normalizedValue = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $rawValue));

                if (
                    $normalizedValue === ''
                    || $normalizedValue === '&'
                    || $normalizedValue === '&;'
                    || strpos($normalizedValue, '&amp') === 0
                    || $normalizedValue === '/public/&'
                    || strpos($normalizedValue, '/public/&amp') === 0
                ) {
                    return 'none';
                }

                return (string) ($matches[0] ?? '');
            },
            $styleValue
        );
    }

    protected function normalizeManagedDrawEditorResourceReferences(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $html = (string) preg_replace('/url\(\s*(?:&amp;|&)(?:;)?\s*\)?(?=\s*(?:[;"\']|$))/iu', 'none', $html);

        return (string) preg_replace(
            '/\s+(src|href|xlink:href)\s*=\s*(["\'])(?:\/public\/)?(?:&amp;|&)(?:;)?\2/iu',
            '',
            $html
        );
    }

    protected function normalizeManagedDrawHeroBannerBackgroundStyle(string $styleValue): string
    {
        $styleValue = $this->normalizeManagedDrawInlineStyle($styleValue);
        if ($styleValue === '') {
            return 'background-color: #0f172a';
        }

        $imageUrl = '';
        if (preg_match('/url\(\s*([\'"]?)(.*?)\1\s*\)/iu', $styleValue, $matches)) {
            $imageUrl = trim((string) ($matches[2] ?? ''));
        }

        $declarations = preg_split('/\s*;\s*/', $styleValue) ?: array();
        $keptDeclarations = array();
        foreach ($declarations as $declaration) {
            $parts = explode(':', (string) $declaration, 2);
            $property = isset($parts[0]) ? strtolower(trim((string) $parts[0])) : '';
            if ($property === '' || strpos($property, 'background') === 0) {
                continue;
            }

            $keptDeclarations[] = trim((string) $declaration);
        }

        if ($imageUrl !== '') {
            array_unshift($keptDeclarations, 'background: #0f172a url(' . $imageUrl . ') center/100% 100% no-repeat');
        } else {
            array_unshift($keptDeclarations, 'background-color: #0f172a');
        }

        return implode('; ', array_values(array_filter($keptDeclarations, static function ($declaration) {
            return trim((string) $declaration) !== '';
        })));
    }

    protected function normalizeManagedDrawHeroBannerBackgroundHtml(string $html): string
    {
        if ($html === '' || stripos($html, 'section-home') === false || stripos($html, 'hero-banner') === false) {
            return $html;
        }

        return (string) preg_replace_callback(
            '/<section\b(?=[^>]*\bid=(["\'])section-home\1)(?=[^>]*\bclass=(["\'])[^"\']*\bhero-banner\b[^"\']*\2)([^>]*)>/iu',
            function ($matches) {
                $attributes = (string) ($matches[3] ?? '');
                if (!preg_match('/\sstyle\s*=\s*("([^"]*)"|\'([^\']*)\'|[^\s>]+)/iu', $attributes, $styleMatches)) {
                    return (string) ($matches[0] ?? '');
                }

                $styleValue = '';
                if (isset($styleMatches[2])) {
                    $styleValue = (string) $styleMatches[2];
                } elseif (isset($styleMatches[3])) {
                    $styleValue = (string) $styleMatches[3];
                } elseif (isset($styleMatches[1])) {
                    $styleValue = trim((string) $styleMatches[1], '"\'');
                }

                $normalizedStyle = $this->normalizeManagedDrawHeroBannerBackgroundStyle($styleValue);
                $tagHtml = (string) ($matches[0] ?? '');

                return (string) preg_replace(
                    '/\sstyle\s*=\s*("([^"]*)"|\'([^\']*)\'|[^\s>]+)/iu',
                    ' style="' . htmlspecialchars($normalizedStyle, ENT_QUOTES, 'UTF-8') . '"',
                    $tagHtml,
                    1
                );
            },
            $html
        );
    }

    protected function removeManagedDrawEditorControlElements(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        for ($index = 0; $index < 6; $index += 1) {
            $nextHtml = (string) preg_replace(
                '/<([a-z][a-z0-9:-]*)\b(?=[^>]*\sdata-section-editor-control\s*=)[^>]*>[\s\S]*?<\/\s*\1\s*>/iu',
                '',
                $html
            );
            $nextHtml = (string) preg_replace(
                '/<([a-z][a-z0-9:-]*)\b(?=[^>]*\sdata-section-editor-control\s*=)[^>]*\/?\s*>/iu',
                '',
                $nextHtml
            );

            if ($nextHtml === $html) {
                break;
            }

            $html = $nextHtml;
        }

        return trim($html);
    }

    public function stripManagedDrawEditorControlResidues(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $span = static function (string $textPattern): string {
            return '\s*<span\b(?![^>]*\b(?:class|data-section-editor-control)\s*=)[^>]*>\s*' . $textPattern . '\s*<\/span>';
        };
        $handleText = '(?:⋮⋮|&#8942;&#8942;|&#x22ee;&#x22ee;|&vellip;&vellip;)';
        $controlClusterPattern = '~'
            . $span($handleText)
            . $span('拖拽')
            . $span('编辑')
            . $span('源码')
            . $span('(?:隐藏|显示)')
            . $span('删除')
            . '~iu';

        do {
            $previousHtml = $html;
            $html = (string) preg_replace($controlClusterPattern, '', $html);
        } while ($html !== $previousHtml);

        return trim($html);
    }

    protected function normalizeManagedDrawEditorLockAttributes(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        return trim((string) preg_replace_callback(
            '/\sdata-section-edit-locked\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/iu',
            static function ($matches) {
                $value = '';
                if (isset($matches[2])) {
                    $value = (string) $matches[2];
                } elseif (isset($matches[3])) {
                    $value = (string) $matches[3];
                } elseif (isset($matches[4])) {
                    $value = (string) $matches[4];
                }

                return trim(html_entity_decode($value, ENT_QUOTES, 'UTF-8')) === '1'
                    ? ' data-section-edit-locked="1"'
                    : '';
            },
            $html
        ));
    }

    protected function managedDrawTagAttributeValue(string $tagHtml, string $attribute): string
    {
        if ($tagHtml === '' || $attribute === '') {
            return '';
        }

        if (!preg_match('/\s' . preg_quote($attribute, '/') . '\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/iu', $tagHtml, $matches)) {
            return '';
        }

        if (isset($matches[2])) {
            return html_entity_decode((string) $matches[2], ENT_QUOTES, 'UTF-8');
        }

        if (isset($matches[3])) {
            return html_entity_decode((string) $matches[3], ENT_QUOTES, 'UTF-8');
        }

        if (isset($matches[4])) {
            return html_entity_decode((string) $matches[4], ENT_QUOTES, 'UTF-8');
        }

        return '';
    }

    protected function managedDrawInlineStylePropertyValue(string $styleValue, string $property): string
    {
        $property = strtolower(trim($property));
        if ($styleValue === '' || $property === '') {
            return '';
        }

        $declarations = preg_split('/\s*;\s*/', html_entity_decode(trim($styleValue), ENT_QUOTES, 'UTF-8')) ?: array();
        foreach ($declarations as $declaration) {
            $parts = explode(':', (string) $declaration, 2);
            $propertyName = isset($parts[0]) ? strtolower(trim((string) $parts[0])) : '';
            $propertyValue = isset($parts[1]) ? trim((string) $parts[1]) : '';

            if ($propertyName === $property) {
                return $propertyValue;
            }
        }

        return '';
    }

    protected function withManagedDrawTagDisplayNone(string $tagHtml): string
    {
        $styleValue = '';

        if (!preg_match('/\sstyle\s*=\s*("([^"]*)"|\'([^\']*)\'|[^\s>]+)/iu', $tagHtml, $styleMatches)) {
            return (string) preg_replace(
                '/\s*(\/?)>$/u',
                ' style="display: none"$1>',
                $tagHtml,
                1
            );
        }

        if (isset($styleMatches[2])) {
            $styleValue = (string) $styleMatches[2];
        } elseif (isset($styleMatches[3])) {
            $styleValue = (string) $styleMatches[3];
        } elseif (isset($styleMatches[1])) {
            $styleValue = trim((string) $styleMatches[1], '"\'');
        }

        $styleValue = $this->normalizeManagedDrawInlineStyle($styleValue, array('display'));
        $styleValue = 'display: none' . ($styleValue !== '' ? '; ' . $styleValue : '');

        return (string) preg_replace(
            '/\sstyle\s*=\s*("([^"]*)"|\'([^\']*)\'|[^\s>]+)/iu',
            ' style="' . htmlspecialchars($styleValue, ENT_QUOTES, 'UTF-8') . '"',
            $tagHtml,
            1
        );
    }

    protected function applyManagedDrawEditorHiddenDisplayState(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        return trim((string) preg_replace_callback(
            '/<([a-z][a-z0-9:-]*)\b(?=[^>]*\sdata-section-(?:hidden|display)\s*=)[^>]*>/iu',
            function ($matches) {
                $tagHtml = (string) ($matches[0] ?? '');
                $hiddenState = trim($this->managedDrawTagAttributeValue($tagHtml, 'data-section-hidden'));

                if ($hiddenState !== '1') {
                    return $tagHtml;
                }

                return $this->withManagedDrawTagDisplayNone($tagHtml);
            },
            $html
        ));
    }

    protected function applyManagedDrawHiddenTopSpacerState(string $html): string
    {
        $html = trim($html);
        if ($html === '' || stripos($html, 'top-bar-spacer') === false) {
            return $html;
        }

        return trim((string) preg_replace_callback(
            '/(<header\b(?=[^>]*\bclass\s*=\s*(["\'])[^"\']*\btop-bar\b[^"\']*\2)(?=[^>]*\bstyle\s*=\s*(["\'])[^"\']*display\s*:\s*none\b[^"\']*\3)[^>]*>[\s\S]*?<\/header>\s*)(<div\b(?=[^>]*\bclass\s*=\s*(["\'])[^"\']*\btop-bar-spacer\b[^"\']*\5)[^>]*>)/iu',
            function ($matches) {
                return (string) ($matches[1] ?? '') . $this->withManagedDrawTagDisplayNone((string) ($matches[4] ?? ''));
            },
            $html
        ));
    }

    public function stripManagedDrawEditorFrontendState(string $html): string
    {
        $html = $this->removeManagedDrawEditorControlElements($html);
        $html = $this->stripManagedDrawEditorControlResidues($html);
        if ($html === '') {
            return '';
        }

        $html = $this->applyManagedDrawEditorHiddenDisplayState($html);
        $html = $this->applyManagedDrawHiddenTopSpacerState($html);
        $html = (string) preg_replace('/\scontenteditable\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html);
        $html = (string) preg_replace('/\sdraggable\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html);
        $html = (string) preg_replace('/\sdata-section-(?:editor-[a-z0-9_-]+|edit-locked|hidden|display)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html);
        $html = (string) preg_replace('/\sdata-mce-[a-z0-9_-]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html);

        return trim($html);
    }

    protected function sanitizeManagedDrawComponentHtml(string $html): string
    {
        $html = $this->removeManagedDrawEditorControlElements($this->normalizeManagedDrawEditorResourceReferences($html));
        $html = $this->stripManagedDrawEditorControlResidues($html);
        $html = $this->applyManagedDrawEditorHiddenDisplayState($html);
        $html = $this->applyManagedDrawHiddenTopSpacerState($html);
        if ($html === '') {
            return '';
        }

        $html = (string) preg_replace('/\scontenteditable\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html);
        $html = (string) preg_replace('/\sdraggable\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html);
        $html = (string) preg_replace('/\sdata-section-(?:editor-[a-z0-9_-]+|hidden|display)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html);
        $html = $this->normalizeManagedDrawEditorLockAttributes($html);
        $html = (string) preg_replace('/\sdata-mce-[a-z0-9_-]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html);
        $html = (string) preg_replace('/<\s*(script|iframe|object|embed|link|meta|base|form|input|button|textarea|select|option)\b[\s\S]*?<\s*\/\s*\1\s*>/iu', '', $html);
        $html = (string) preg_replace('/<\s*(script|iframe|object|embed|link|meta|base|form|input|button|textarea|select|option)\b[^>]*\/?\s*>/iu', '', $html);
        $html = (string) preg_replace('/\s+on[a-z0-9_-]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/iu', '', $html);
        $html = (string) preg_replace_callback(
            '/\s+(href|src|xlink:href|action|formaction)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/iu',
            function ($matches) {
                $attribute = strtolower((string) ($matches[1] ?? ''));
                $value = '';
                if (isset($matches[3])) {
                    $value = (string) $matches[3];
                } elseif (isset($matches[4])) {
                    $value = (string) $matches[4];
                } elseif (isset($matches[5])) {
                    $value = (string) $matches[5];
                }

                $decodedValue = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
                $normalizedValue = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $decodedValue));
                if (
                    strpos($normalizedValue, 'javascript:') === 0
                    || strpos($normalizedValue, 'vbscript:') === 0
                    || strpos($normalizedValue, 'data:') === 0
                ) {
                    return '';
                }

                return ' ' . $attribute . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
            },
            $html
        );
        $html = (string) preg_replace_callback(
            '/\sclass="([^"]*)"/i',
            function ($matches) {
                $classList = $this->normalizeManagedDrawEditorClassList((string) ($matches[1] ?? ''));
                if ($classList === '') {
                    return '';
                }

                return ' class="' . htmlspecialchars($classList, ENT_QUOTES, 'UTF-8') . '"';
            },
            $html
        );
        $html = (string) preg_replace_callback(
            '/\sstyle\s*=\s*("([^"]*)"|\'([^\']*)\'|[^\s>]+)/i',
            function ($matches) {
                $styleValue = '';
                if (isset($matches[2])) {
                    $styleValue = (string) $matches[2];
                } elseif (isset($matches[3])) {
                    $styleValue = (string) $matches[3];
                } elseif (isset($matches[1])) {
                    $styleValue = trim((string) $matches[1], '"\'');
                }

                $styleValue = $this->normalizeManagedDrawInlineStyle($styleValue);
                if ($styleValue === '') {
                    return '';
                }

                return ' style="' . htmlspecialchars($styleValue, ENT_QUOTES, 'UTF-8') . '"';
            },
            $html
        );
        $html = (string) preg_replace('/\sstyle=""/i', '', $html);
        $html = $this->removeManagedDrawComponentRootEditorDisplayState($html);

        return trim($html);
    }

    protected function removeManagedDrawComponentRootEditorDisplayState(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        return trim((string) preg_replace_callback(
            '/<(header|nav)\b(?=[^>]*\bclass\s*=\s*(["\'])[^"\']*\b(?:top-bar|bottom-float-nav)\b[^"\']*\2)([^>]*)>/iu',
            function ($matches) {
                $tagName = strtolower((string) ($matches[1] ?? ''));
                $tagHtml = (string) ($matches[0] ?? '');

                if ($tagName !== 'header' && $tagName !== 'nav') {
                    return $tagHtml;
                }

                if (!preg_match('/\sstyle\s*=\s*("([^"]*)"|\'([^\']*)\'|[^\s>]+)/iu', $tagHtml, $styleMatches)) {
                    return $tagHtml;
                }

                $styleValue = '';
                if (isset($styleMatches[2])) {
                    $styleValue = (string) $styleMatches[2];
                } elseif (isset($styleMatches[3])) {
                    $styleValue = (string) $styleMatches[3];
                } elseif (isset($styleMatches[1])) {
                    $styleValue = trim((string) $styleMatches[1], '"\'');
                }

                $displayValue = strtolower((string) preg_replace(
                    '/[\x00-\x20]+/',
                    '',
                    html_entity_decode($this->managedDrawInlineStylePropertyValue($styleValue, 'display'), ENT_QUOTES, 'UTF-8')
                ));
                $styleValue = $this->normalizeManagedDrawInlineStyle(
                    $styleValue,
                    $displayValue === 'none' ? array() : array('display')
                );
                if ($styleValue === '') {
                    return (string) preg_replace('/\sstyle\s*=\s*("([^"]*)"|\'([^\']*)\'|[^\s>]+)/iu', '', $tagHtml, 1);
                }

                return (string) preg_replace(
                    '/\sstyle\s*=\s*("([^"]*)"|\'([^\']*)\'|[^\s>]+)/iu',
                    ' style="' . htmlspecialchars($styleValue, ENT_QUOTES, 'UTF-8') . '"',
                    $tagHtml,
                    1
                );
            },
            $html
        ));
    }

    protected function sanitizeManagedDrawMaterialHtml(string $html): string
    {
        $html = $this->removeManagedDrawEditorControlElements($this->normalizeManagedDrawEditorResourceReferences($html));
        $html = $this->stripManagedDrawEditorControlResidues($html);
        $html = $this->applyManagedDrawEditorHiddenDisplayState($html);
        $html = $this->repairManagedDrawMaterialOrphanSectionClosers($html);
        $html = $this->repairManagedDrawMaterialCalendarPanelClosers($html);
        if ($html === '') {
            return '';
        }

        $html = (string) preg_replace('/\scontenteditable\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html);
        $html = (string) preg_replace('/\sdraggable\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html);
        $html = (string) preg_replace('/\sdata-section-(?:editor-[a-z0-9_-]+|hidden|display)\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html);
        $html = $this->normalizeManagedDrawEditorLockAttributes($html);
        $html = (string) preg_replace('/\sdata-mce-[a-z0-9_-]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/iu', '', $html);
        $html = (string) preg_replace('/<\s*(script|iframe|object|embed|link|meta|base|form|input|button|textarea|select|option)\b[\s\S]*?<\s*\/\s*\1\s*>/iu', '', $html);
        $html = (string) preg_replace('/<\s*(script|iframe|object|embed|link|meta|base|form|input|button|textarea|select|option)\b[^>]*\/?\s*>/iu', '', $html);
        $html = (string) preg_replace('/\s+on[a-z0-9_-]+\s*=\s*(".*?"|\'.*?\'|[^\s>]+)/iu', '', $html);
        $html = (string) preg_replace_callback(
            '/\s+(href|src|xlink:href|action|formaction)\s*=\s*("([^"]*)"|\'([^\']*)\'|([^\s>]+))/iu',
            function ($matches) {
                $attribute = strtolower((string) ($matches[1] ?? ''));
                $value = '';
                if (isset($matches[3])) {
                    $value = (string) $matches[3];
                } elseif (isset($matches[4])) {
                    $value = (string) $matches[4];
                } elseif (isset($matches[5])) {
                    $value = (string) $matches[5];
                }

                $decodedValue = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
                $normalizedValue = strtolower((string) preg_replace('/[\x00-\x20]+/', '', $decodedValue));
                if (
                    strpos($normalizedValue, 'javascript:') === 0
                    || strpos($normalizedValue, 'vbscript:') === 0
                    || strpos($normalizedValue, 'data:') === 0
                ) {
                    return '';
                }

                return ' ' . $attribute . '="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '"';
            },
            $html
        );

        $html = (string) preg_replace_callback(
            '/\sclass="([^"]*)"/i',
            function ($matches) {
                $classList = $this->normalizeManagedDrawEditorClassList((string) ($matches[1] ?? ''));
                if ($classList === '') {
                    return '';
                }

                return ' class="' . htmlspecialchars($classList, ENT_QUOTES, 'UTF-8') . '"';
            },
            $html
        );

        $html = (string) preg_replace_callback(
            '/\sstyle\s*=\s*("([^"]*)"|\'([^\']*)\'|[^\s>]+)/i',
            function ($matches) {
                $styleValue = '';
                if (isset($matches[2])) {
                    $styleValue = (string) $matches[2];
                } elseif (isset($matches[3])) {
                    $styleValue = (string) $matches[3];
                } elseif (isset($matches[1])) {
                    $styleValue = trim((string) $matches[1], '"\'');
                }

                $styleValue = $this->normalizeManagedDrawInlineStyle($styleValue);
                if ($styleValue === '') {
                    return '';
                }

                return ' style="' . htmlspecialchars($styleValue, ENT_QUOTES, 'UTF-8') . '"';
            },
            $html
        );

        $html = (string) preg_replace_callback(
            '/<div\b[^>]*class="[^"]*\b(?:data-frame|grid)\b[^"]*"[^>]*>/i',
            function ($matches) {
                $tagHtml = (string) ($matches[0] ?? '');

                return (string) preg_replace_callback(
                    '/\sstyle="([^"]*)"/i',
                    function ($styleMatches) {
                        $styleValue = $this->normalizeManagedDrawInlineStyle((string) ($styleMatches[1] ?? ''), array(
                            'border-color',
                            'border-top-color',
                            'border-right-color',
                            'border-bottom-color',
                            'border-left-color',
                            'box-shadow',
                        ));

                        if ($styleValue === '') {
                            return '';
                        }

                        return ' style="' . htmlspecialchars($styleValue, ENT_QUOTES, 'UTF-8') . '"';
                    },
                    $tagHtml,
                    1
                );
            },
            $html
        );

        $html = (string) preg_replace('/\sstyle=""/i', '', $html);
        $html = $this->normalizeManagedDrawHeroBannerBackgroundHtml($html);

        return trim($html);
    }

    protected function normalizeManagedDrawZodiacReferenceHeads(string $html): string
    {
        $html = trim($html);
        if ($html === '' || stripos($html, 'zodiac-ref-head') === false) {
            return $html;
        }

        $zodiacIcons = array(
            '鼠' => '🐀', '牛' => '🐂', '虎' => '🐅', '兔' => '🐇',
            '龙' => '🐉', '蛇' => '🐍', '马' => '🐎', '羊' => '🐐',
            '猴' => '🐒', '鸡' => '🐓', '狗' => '🐕', '猪' => '🐖',
        );

        $previousUseInternalErrors = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $document->loadHTML(
            '<?xml encoding="utf-8" ?><div id="managed-draw-zodiac-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previousUseInternalErrors);

        if (!$loaded) {
            return $html;
        }

        $xpath = new \DOMXPath($document);
        $rootNode = $document->getElementById('managed-draw-zodiac-root');
        if (!$rootNode instanceof \DOMElement) {
            return $html;
        }

        $headNodes = $xpath->query('.//*[contains(concat(" ", normalize-space(@class), " "), " zodiac-reference-card ")]//*[contains(concat(" ", normalize-space(@class), " "), " zodiac-ref-grid ")]//*[contains(concat(" ", normalize-space(@class), " "), " zodiac-ref-head ")]', $rootNode);
        if (!$headNodes instanceof \DOMNodeList || $headNodes->length <= 0) {
            return $html;
        }

        foreach ($headNodes as $headNode) {
            if (!$headNode instanceof \DOMElement) {
                continue;
            }

            $rawText = trim((string) $headNode->textContent);
            $rawText = (string) preg_replace('/\s+/u', '', $rawText);
            $animalName = '';
            foreach (array_keys($zodiacIcons) as $candidateName) {
                if (mb_strpos($rawText, $candidateName, 0, 'UTF-8') !== false) {
                    $animalName = $candidateName;
                    break;
                }
            }
            if ($animalName === '') {
                continue;
            }

            $clashName = '';
            if (preg_match('/冲([鼠牛虎兔龙蛇马羊猴鸡狗猪])/u', $rawText, $matches)) {
                $clashName = (string) ($matches[1] ?? '');
            }
            if ($clashName === '') {
                continue;
            }

            $iconText = (string) ($zodiacIcons[$animalName] ?? '');
            $existingIconNode = $xpath->query('.//*[@aria-hidden="true"]', $headNode)->item(0);
            if ($existingIconNode instanceof \DOMNode) {
                $existingIconText = trim((string) $existingIconNode->textContent);
                if ($existingIconText !== '') {
                    $iconText = $existingIconText;
                }
            }

            while ($headNode->firstChild) {
                $headNode->removeChild($headNode->firstChild);
            }

            $iconNode = $document->createElement('span');
            $iconNode->setAttribute('class', 'zodiac-ref-head-icon');
            $iconNode->setAttribute('aria-hidden', 'true');
            $iconNode->appendChild($document->createTextNode($iconText));

            $labelNode = $document->createElement('span');
            $labelNode->setAttribute('class', 'zodiac-ref-head-label');
            $labelNode->appendChild($document->createTextNode($animalName . '【冲' . $clashName . '】'));

            $headNode->appendChild($iconNode);
            $headNode->appendChild($labelNode);
        }

        $resultHtml = '';
        foreach ($rootNode->childNodes as $childNode) {
            $resultHtml .= $document->saveHTML($childNode);
        }

        return trim($resultHtml) !== '' ? trim($resultHtml) : $html;
    }
    protected function normalizeManagedDrawMaterialHtml(string $region, string $contentHtml, string $fallbackHtml = ''): string
    {
        $region = $this->normalizeManagedDrawMaterialRegion($region);
        $contentHtml = $this->sanitizeManagedDrawMaterialHtml($contentHtml);
        $contentHtml = $this->stripManagedDrawHeroCopy($contentHtml);
        $contentHtml = $this->removeManagedDrawMaterialSectionSpacerParagraphs($contentHtml);
        if ($contentHtml === '') {
            return '';
        }

        $baseHtml = $this->managedDrawProtectedMaterialTemplate($region);
        if ($baseHtml === '') {
            return $contentHtml;
        }
        $baseHtml = $this->moveManagedDrawLiveBlockBelowHomeSection($baseHtml);
        $baseHtml = $this->ensureManagedDrawLiveBadgeHtml($baseHtml);

        if (!$this->isManagedDrawMaterialFullHtml($contentHtml)) {
            $mergedHtml = $this->removeManagedDrawMaterialSectionSpacerParagraphs($this->mergeManagedDrawMaterialHtml($baseHtml, $contentHtml));
            $normalizedHtml = $this->normalizeManagedDrawZodiacReferenceHeads($this->moveManagedDrawLiveBlockBelowHomeSection($this->removeManagedDrawMaterialSectionSpacerParagraphs($this->syncManagedDrawExpertLinks($region, $mergedHtml))));
            $normalizedHtml = $this->ensureManagedDrawLiveBadgeHtml($normalizedHtml);

            return $this->ensureManagedDrawMaterialEditableHtml($region, $normalizedHtml, $fallbackHtml, $baseHtml);
        }

        if ($this->hasManagedDrawMaterialDuplicatedShell($contentHtml)) {
            if (preg_match_all('/<section\b[^>]*\bid=["\']section-home["\']/iu', $contentHtml, $shellMatches, PREG_OFFSET_CAPTURE)) {
                $lastShell = end($shellMatches[0]);
                $lastShellOffset = is_array($lastShell) ? (int) ($lastShell[1] ?? -1) : -1;
                if ($lastShellOffset >= 0) {
                    $contentHtml = (string) substr($contentHtml, $lastShellOffset);
                }
            }
        }

        $normalizedHtml = (string) preg_replace('/\s*<script id="legacy-home-data"[\s\S]*?<\/script>\s*$/', '', $contentHtml, 1);
        $normalizedHtml = $this->normalizeManagedDrawHomeSection($normalizedHtml, $baseHtml);
        $homeSectionPattern = '/<section\b[^>]*\bid=["\']section-home["\'][^>]*>[\s\S]*?<\/section>/iu';
        $marqueePattern = $this->managedDrawMarqueeBlockPattern();
        $calendarPattern = $this->managedDrawCalendarBlockPattern();
        $liveBlock = $this->extractManagedDrawLiveBlock($baseHtml);
        $calendarBlock = $this->extractManagedDrawMaterialBlock($baseHtml, $calendarPattern);
        if ($liveBlock !== '') {
            if ($this->extractManagedDrawLiveBlock($normalizedHtml) !== '') {
                $normalizedHtml = $this->replaceManagedDrawLiveBlock($normalizedHtml, $liveBlock);
            } else {
                $normalizedHtml = $this->insertManagedDrawMaterialBlockAfter(
                    $normalizedHtml,
                    $homeSectionPattern,
                    $liveBlock
                );
            }
        }
        $normalizedHtml = $this->ensureManagedDrawMarqueeBlock($normalizedHtml, $baseHtml);

        if ($calendarBlock !== '') {
            if (preg_match($calendarPattern, $normalizedHtml)) {
                $normalizedHtml = $this->replaceManagedDrawMaterialBlock($normalizedHtml, $calendarPattern, $calendarBlock);
            } elseif (preg_match($marqueePattern, $normalizedHtml)) {
                $normalizedHtml = $this->insertManagedDrawMaterialBlockAfter(
                    $normalizedHtml,
                    $marqueePattern,
                    $calendarBlock
                );
            } else {
                $normalizedHtml = $this->insertManagedDrawMaterialBlockAfter(
                    $normalizedHtml,
                    $homeSectionPattern,
                    $calendarBlock
                );
            }
        }
        $normalizedHtml = $this->normalizeManagedDrawProtectedHeaderBlocks($normalizedHtml, $baseHtml);

        $normalizedHtml = $this->ensureManagedDrawLiveBadgeHtml($normalizedHtml);
        $normalizedHtml = $this->syncManagedDrawExpertLinks($region, $normalizedHtml);
        $normalizedHtml = $this->removeManagedDrawMaterialSectionSpacerParagraphs($normalizedHtml);
        $normalizedHtml = $this->normalizeManagedDrawZodiacReferenceHeads($normalizedHtml);
        $normalizedHtml = $this->ensureManagedDrawMaterialEditableHtml($region, $normalizedHtml, $fallbackHtml, $baseHtml);

        return trim($normalizedHtml);
    }

    protected function normalizeManagedDrawMaterialEditorSourceHtml(string $region, string $contentHtml, string $fallbackHtml = ''): string
    {
        $region = $this->normalizeManagedDrawMaterialRegion($region);
        $contentHtml = $this->sanitizeManagedDrawMaterialHtml($contentHtml);
        $contentHtml = $this->stripManagedDrawHeroCopy($contentHtml);
        $contentHtml = $this->removeManagedDrawMaterialSectionSpacerParagraphs($contentHtml);
        if ($contentHtml === '') {
            return '';
        }

        if (!$this->isManagedDrawMaterialFullHtml($contentHtml)) {
            $baseHtml = trim($fallbackHtml);
            if ($baseHtml === '') {
                $baseHtml = $this->managedDrawProtectedMaterialTemplate($region);
            }
            if ($baseHtml !== '') {
                $contentHtml = $this->removeManagedDrawMaterialSectionSpacerParagraphs(
                    $this->mergeManagedDrawMaterialHtml($baseHtml, $contentHtml)
                );
            }
        }

        if ($this->hasManagedDrawMaterialDuplicatedShell($contentHtml)) {
            if (preg_match_all('/<section\b[^>]*\bid=["\']section-home["\']/iu', $contentHtml, $shellMatches, PREG_OFFSET_CAPTURE)) {
                $lastShell = end($shellMatches[0]);
                $lastShellOffset = is_array($lastShell) ? (int) ($lastShell[1] ?? -1) : -1;
                if ($lastShellOffset >= 0) {
                    $contentHtml = (string) substr($contentHtml, $lastShellOffset);
                }
            }
        }

        $contentHtml = (string) preg_replace('/\s*<script id="legacy-home-data"[\s\S]*?<\/script>\s*$/', '', $contentHtml, 1);
        $contentHtml = $this->repairManagedDrawMaterialCalendarPanelClosers($contentHtml);
        $contentHtml = $this->repairManagedDrawMaterialOrphanSectionClosers($contentHtml);
        $contentHtml = $this->removeManagedDrawMaterialSectionSpacerParagraphs($contentHtml);
        $contentHtml = $this->normalizeManagedDrawZodiacReferenceHeads($contentHtml);
        $contentHtml = $this->ensureManagedDrawLiveBadgeHtml($contentHtml);

        return trim($contentHtml);
    }

    protected function removeManagedDrawMaterialSectionSpacerParagraphs(string $html): string
    {
        $html = trim($html);
        if ($html === '') {
            return '';
        }

        $emptyParagraphPattern = '<p\b[^>]*>\s*(?:(?:&nbsp;|&#160;|\\x{00a0})|<br\s*/?>|\s)*</p>\s*';

        $html = (string) preg_replace(
            '~<p\b(?=[^>]*caret-color\s*:\s*transparent)[^>]*>\s*(?:(?:&nbsp;|&#160;|\\x{00a0})|<br\s*/?>|\s)*</p>\s*~iu',
            '',
            $html
        );

        $html = (string) preg_replace(
            '~(<section\b[^>]*\bid=["\']section-home["\'][^>]*>)\s*(?:' . $emptyParagraphPattern . ')+~iu',
            '$1',
            $html
        );

        $html = (string) preg_replace(
            '~(</section>)\s*(?:' . $emptyParagraphPattern . ')+(?=<(?:section\b|div\b[^>]*\bclass=["\'][^"\']*\bmarquee\b))~iu',
            '$1',
            $html
        );

        $html = (string) preg_replace(
            '~<div\b(?=[^>]*\bstyle=["\'][^"\']*\binset\s*:)(?=[^>]*\bstyle=["\'][^"\']*\bwidth\s*:)(?=[^>]*\bstyle=["\'][^"\']*\bheight\s*:)(?![^>]*\b(?:class|id|data-[a-z0-9_-]+|aria-[a-z0-9_-]+)\b)[^>]*>\s*(?:(?:&nbsp;|&#160;|\\x{00a0})|<br\s*/?>|\s)*</div>\s*~iu',
            '',
            $html
        );

        return trim($html);
    }

    protected function repairManagedDrawMaterialStorage(string $region, string $storedContentHtml, string $updatedAt, string $updatedBy): string
    {
        $storedContentHtml = trim($storedContentHtml);
        if ($storedContentHtml === '') {
            return $storedContentHtml;
        }

        $normalizedContentHtml = $this->normalizeManagedDrawMaterialEditorSourceHtml($region, $storedContentHtml);

        if ($normalizedContentHtml !== $storedContentHtml) {
            $this->db()->execute(
                'UPDATE settings
                 SET setting_value = :setting_value
                 WHERE setting_key = :setting_key',
                array(
                    'setting_value' => $normalizedContentHtml,
                    'setting_key' => 'draws.material_html.' . $region,
                )
            );
            $this->app->settings()->clearCache();

            return $normalizedContentHtml;
        }

        return $storedContentHtml;
    }

    protected function managedDrawMaterialEditorExpertVersion(string $region): string
    {
        $region = $this->normalizeManagedDrawMaterialRegion($region);
        if (isset($this->managedDrawMaterialEditorExpertVersionRequestCache[$region])) {
            return $this->managedDrawMaterialEditorExpertVersionRequestCache[$region];
        }

        $cacheKey = $this->managedDrawMaterialEditorExpertVersionCacheKey($region);
        $cachedVersion = $this->app->cache()->get($cacheKey, null, 20);
        if (is_string($cachedVersion) && $cachedVersion !== '') {
            $this->managedDrawMaterialEditorExpertVersionRequestCache[$region] = $cachedVersion;

            return $cachedVersion;
        }

        if (!$this->tableExists($this->db(), 'posts')) {
            $this->managedDrawMaterialEditorExpertVersionRequestCache[$region] = 'no-posts';
            $this->app->cache()->put($cacheKey, 'no-posts');

            return $this->managedDrawMaterialEditorExpertVersionRequestCache[$region];
        }

        $hasPostMeta = $this->tableExists($this->db(), 'post_manage_meta');
        $hasPostMetaUpdatedAt = $hasPostMeta && $this->columnExists($this->db(), 'post_manage_meta', 'updated_at');
        $hasPostMetaHidden = $hasPostMeta && $this->columnExists($this->db(), 'post_manage_meta', 'is_hidden');

        if ($hasPostMeta) {
            $metaUpdatedSelect = $hasPostMetaUpdatedAt
                ? 'COALESCE(MAX(post_meta.updated_at), \'\') AS max_meta_updated_at'
                : '\'\' AS max_meta_updated_at';
            $hiddenWhere = $hasPostMetaHidden ? ' AND COALESCE(post_meta.is_hidden, 0) = 0' : '';
            $row = $this->db()->fetch(
                'SELECT COUNT(*) AS total_count,
                        COALESCE(MAX(posts.id), 0) AS max_post_id,
                        COALESCE(MAX(posts.updated_at), \'\') AS max_post_updated_at,
                        ' . $metaUpdatedSelect . '
                 FROM posts
                 LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
                 WHERE posts.region = :region
                   AND posts.status = :status'
                   . $hiddenWhere,
                array(
                    'region' => $region,
                    'status' => 'published',
                )
            );
        } else {
            $row = $this->db()->fetch(
                'SELECT COUNT(*) AS total_count,
                        COALESCE(MAX(id), 0) AS max_post_id,
                        COALESCE(MAX(updated_at), \'\') AS max_post_updated_at,
                        \'\' AS max_meta_updated_at
                 FROM posts
                 WHERE region = :region
                   AND status = :status',
                array(
                    'region' => $region,
                    'status' => 'published',
                )
            );
        }

        if (!$row) {
            $this->managedDrawMaterialEditorExpertVersionRequestCache[$region] = 'empty';
            $this->app->cache()->put($cacheKey, 'empty');

            return $this->managedDrawMaterialEditorExpertVersionRequestCache[$region];
        }

        $version = implode('|', array(
            (string) ((int) ($row['total_count'] ?? 0)),
            (string) ((int) ($row['max_post_id'] ?? 0)),
            (string) ($row['max_post_updated_at'] ?? ''),
            (string) ($row['max_meta_updated_at'] ?? ''),
        ));
        $this->managedDrawMaterialEditorExpertVersionRequestCache[$region] = $version;
        $this->app->cache()->put($cacheKey, $version);

        return $this->managedDrawMaterialEditorExpertVersionRequestCache[$region];
    }

    protected function normalizeManagedDrawMaterialHtmlForEditor(string $region, string $storedContentHtml): string
    {
        $region = $this->normalizeManagedDrawMaterialRegion($region);
        $snapshot = $this->managedIssuePrefixSnapshotByRegion($region);
        $cacheSource = array(
            'region' => $region,
            'html_hash' => md5($storedContentHtml),
            'issue_no' => (string) ($snapshot['issue_no'] ?? ''),
            'issue_prefix_tail' => (string) ($snapshot['issue_prefix_tail'] ?? ''),
            'expert_version' => $this->managedDrawMaterialEditorExpertVersion($region),
            'editor_source_of_truth' => 'settings-or-default-v2',
            'editor_control_residue_strip' => 'v1',
            'editor_lock_persistence' => 'v1',
            'editor_hidden_persistence' => 'v2',
            'editor_expert_runtime_hydration' => 'v1',
            'editor_live_badge_repair' => 'v1',
        );
        $cacheKey = 'admin_draw_material_editor_html_' . $region . '_' . md5(json_encode($cacheSource));
        $cachedHtml = $this->app->cache()->get($cacheKey, null, 20);
        if (is_string($cachedHtml)) {
            return $cachedHtml;
        }

        $normalizedHtml = $this->normalizeManagedDrawMaterialEditorSourceHtml($region, $storedContentHtml);
        $normalizedHtml = $this->syncManagedDrawExpertLinks($region, $normalizedHtml, false);
        $this->app->cache()->put($cacheKey, $normalizedHtml);

        return $normalizedHtml;
    }

    public function managedDrawMaterialEditor(string $region)
    {
        $region = $this->normalizeManagedDrawMaterialRegion($region);
        $storedContentHtml = (string) $this->app->settings()->get('draws.material_html.' . $region, '');
        $updatedAt = (string) $this->app->settings()->get('draws.material_updated_at.' . $region, '');
        $updatedBy = (string) $this->app->settings()->get('draws.material_updated_by.' . $region, '');
        $hasCustomized = trim($updatedAt) !== '' || trim($updatedBy) !== '' || trim($storedContentHtml) !== '';
        $snapshot = $this->managedIssuePrefixSnapshotByRegion($region);
        $cacheSource = array(
            'region' => $region,
            'html_hash' => md5($storedContentHtml),
            'updated_at' => $updatedAt,
            'updated_by' => $updatedBy,
            'has_customized' => $hasCustomized ? '1' : '0',
            'issue_no' => (string) ($snapshot['issue_no'] ?? ''),
            'issue_prefix_tail' => (string) ($snapshot['issue_prefix_tail'] ?? ''),
            'expert_version' => $this->managedDrawMaterialEditorExpertVersion($region),
            'editor_source_of_truth' => 'settings-or-default-v2',
            'editor_control_residue_strip' => 'v1',
            'editor_lock_persistence' => 'v1',
            'editor_hidden_persistence' => 'v2',
            'editor_expert_runtime_hydration' => 'v1',
            'editor_live_badge_repair' => 'v1',
        );
        $cacheKey = 'admin_draw_material_editor_' . $region . '_' . md5(json_encode($cacheSource));
        if (isset($this->managedDrawMaterialEditorRequestCache[$cacheKey])) {
            return $this->managedDrawMaterialEditorRequestCache[$cacheKey];
        }

        $cachedEditor = $this->app->cache()->get($cacheKey, null, 20);
        if (is_array($cachedEditor)) {
            $this->managedDrawMaterialEditorRequestCache[$cacheKey] = $cachedEditor;

            return $cachedEditor;
        }

        $resolvedContentHtml = $hasCustomized
            ? $this->normalizeManagedDrawMaterialHtmlForEditor($region, $storedContentHtml)
            : $this->normalizeManagedDrawMaterialHtmlForEditor($region, $this->managedDrawDefaultMaterialTemplate($region));

        $editor = array(
            'region' => $region,
            'content_html' => $resolvedContentHtml,
            'stored_content_html' => $storedContentHtml,
            'updated_at' => $updatedAt,
            'updated_by' => $updatedBy,
            'has_customized' => $hasCustomized,
        );
        $this->managedDrawMaterialEditorRequestCache[$cacheKey] = $editor;
        $this->app->cache()->put($cacheKey, $editor);

        return $editor;
    }

    public function saveManagedDrawMaterialEditor(array $payload, array $actor)
    {
        $region = $this->normalizeManagedDrawMaterialRegion((string) ($payload['region'] ?? 'macau'));
        if (!in_array($region, array('macau', 'hongkong'), true)) {
            throw new RuntimeException('资料分区无效。');
        }

        $contentHtml = isset($payload['content_html']) ? trim((string) $payload['content_html']) : '';
        $storedContentHtml = (string) $this->app->settings()->get('draws.material_html.' . $region, '');
        $updatedAt = $this->now();
        $updatedBy = isset($actor['username']) ? (string) $actor['username'] : '';

        $settings = array(
            'draws.material_html.' . $region => $this->normalizeManagedDrawMaterialEditorSourceHtml(
                $region,
                $contentHtml,
                $storedContentHtml
            ),
            'draws.material_updated_at.' . $region => $updatedAt,
            'draws.material_updated_by.' . $region => $updatedBy,
        );

        $this->app->settings()->setMany('draws', $settings);

        $regionLabel = $region === 'hongkong' ? '香港' : '澳门';
        $this->app->logs()->admin('draws', 'save_editor', '保存资料编辑器内容：' . $regionLabel, 'settings', $region, (int) $actor['id']);
        $this->recordOperation((int) $actor['id'], 'draws', 'save_editor', 'settings', 0, '保存' . $regionLabel . '资料编辑内容');

        return array(
            'region' => $region,
        );
    }

    protected function normalizeManagedDrawComponentKey(string $componentKey): string
    {
        return 'float_group';
    }

    protected function managedDrawComponentMap(string $componentKey): array
    {
        return array(
            'label' => '悬浮组件',
            'content_keys' => array(
                'top_float' => 'appearance.top_html',
                'bottom_float' => 'appearance.bottom_html',
            ),
            'updated_at_keys' => array(
                'top_float' => 'appearance.top_html_updated_at',
                'bottom_float' => 'appearance.bottom_html_updated_at',
            ),
            'updated_by_keys' => array(
                'top_float' => 'appearance.top_html_updated_by',
                'bottom_float' => 'appearance.bottom_html_updated_by',
            ),
        );
    }

    protected function splitManagedDrawComponentHtml(string $contentHtml): array
    {
        $parts = array(
            'top_float' => '',
            'bottom_float' => '',
        );

        if (preg_match('/<header\b(?=[^>]*\bclass\s*=\s*["\'][^"\']*\btop-bar\b)[\s\S]*?<\/header>(?:\s*<div\b(?=[^>]*\bclass\s*=\s*["\'][^"\']*\btop-bar-spacer\b)[^>]*>\s*<\/div>)?/u', $contentHtml, $matches)) {
            $topFloat = trim((string) ($matches[0] ?? ''));
            if ($topFloat !== '' && strpos($topFloat, 'top-bar-spacer') === false) {
                $topFloat .= "\n" . '<div class="top-bar-spacer" aria-hidden="true"></div>';
            }
            $parts['top_float'] = $topFloat;
        }

        if (preg_match('/<nav\b(?=[^>]*\bclass\s*=\s*["\'][^"\']*\bbottom-float-nav\b)[\s\S]*?<\/nav>/u', $contentHtml, $matches)) {
            $parts['bottom_float'] = trim((string) ($matches[0] ?? ''));
        }

        return $parts;
    }

    protected function managedDrawDefaultComponentTemplates(): array
    {
        if ($this->managedDrawDefaultComponentTemplatesRequestCache !== null) {
            return $this->managedDrawDefaultComponentTemplatesRequestCache;
        }

        $templatePath = $this->app->basePath('resources/defaults/home_editor_default.html');
        $templateHtml = is_file($templatePath) ? (string) file_get_contents($templatePath) : '';

        $this->managedDrawDefaultComponentTemplatesRequestCache = $this->splitManagedDrawComponentHtml($templateHtml);

        return $this->managedDrawDefaultComponentTemplatesRequestCache;
    }

    protected function managedDrawComponentHtmlLooksComplete(string $componentPart, string $contentHtml): bool
    {
        $contentHtml = trim($contentHtml);
        if ($contentHtml === '') {
            return false;
        }

        $plainText = trim((string) preg_replace('/\s+/u', '', strip_tags($contentHtml)));
        if ($plainText === '') {
            return false;
        }

        if ($componentPart === 'top_float') {
            return preg_match('/<header\b(?=[^>]*\bclass\s*=\s*["\'][^"\']*\btop-bar\b)/u', $contentHtml) === 1
                && strpos($contentHtml, 'top-bar-inner') !== false
                && strpos($contentHtml, 'top-brand') !== false
                && strpos($contentHtml, 'top-bar-actions') !== false;
        }

        if ($componentPart === 'bottom_float') {
            return preg_match('/<nav\b(?=[^>]*\bclass\s*=\s*["\'][^"\']*\bbottom-float-nav\b)/u', $contentHtml) === 1
                && substr_count($contentHtml, 'bottom-nav-link') >= 3
                && $this->managedDrawBottomNavHasForecastItem($contentHtml);
        }

        return false;
    }

    protected function managedDrawBottomNavHasForecastItem(string $contentHtml): bool
    {
        $contentHtml = trim($contentHtml);
        if ($contentHtml === '') {
            return false;
        }

        if (!preg_match_all('/<a\b(?P<attrs>[^>]*)>(?P<body>[\s\S]*?)<\/a>/u', $contentHtml, $matches, PREG_SET_ORDER)) {
            return false;
        }

        foreach ($matches as $match) {
            $attrs = (string) ($match['attrs'] ?? '');
            if (!preg_match('/\bclass\s*=\s*(["\'])(?P<class>.*?)\1/iu', $attrs, $classMatches)) {
                continue;
            }

            $classList = preg_split('/\s+/', trim(html_entity_decode((string) ($classMatches['class'] ?? ''), ENT_QUOTES, 'UTF-8')));
            $classList = is_array($classList) ? $classList : array();
            if (!in_array('bottom-nav-link', $classList, true)) {
                continue;
            }

            $plainText = trim((string) preg_replace('/\s+/u', '', strip_tags((string) ($match['body'] ?? ''))));
            if (strpos($plainText, '预测') !== false) {
                return true;
            }
        }

        return false;
    }

    protected function managedDrawComponentLatestMeta(array $components): array
    {
        $latestAt = '';
        $latestBy = '';

        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }

            $updatedAt = trim((string) ($component['updated_at'] ?? ''));
            $updatedBy = trim((string) ($component['updated_by'] ?? ''));

            if ($updatedAt !== '' && ($latestAt === '' || strcmp($updatedAt, $latestAt) >= 0)) {
                $latestAt = $updatedAt;
                $latestBy = $updatedBy;
            }

            if ($latestAt === '' && $latestBy === '' && $updatedBy !== '') {
                $latestBy = $updatedBy;
            }
        }

        return array(
            'updated_at' => $latestAt,
            'updated_by' => $latestBy,
        );
    }

    public function managedDrawComponentEditor(string $componentKey)
    {
        $componentKey = $this->normalizeManagedDrawComponentKey($componentKey);
        $component = $this->managedDrawComponentMap($componentKey);
        $topStoredHtml = (string) $this->app->settings()->get($component['content_keys']['top_float'], '');
        $bottomStoredHtml = (string) $this->app->settings()->get($component['content_keys']['bottom_float'], '');
        $topUpdatedAt = (string) $this->app->settings()->get($component['updated_at_keys']['top_float'], '');
        $bottomUpdatedAt = (string) $this->app->settings()->get($component['updated_at_keys']['bottom_float'], '');
        $topUpdatedBy = (string) $this->app->settings()->get($component['updated_by_keys']['top_float'], '');
        $bottomUpdatedBy = (string) $this->app->settings()->get($component['updated_by_keys']['bottom_float'], '');
        $cacheSource = array(
            'component_key' => $componentKey,
            'fallback_version' => 9,
            'editor_hidden_persistence' => 'v2',
            'top_html_hash' => md5($topStoredHtml),
            'bottom_html_hash' => md5($bottomStoredHtml),
            'top_updated_at' => $topUpdatedAt,
            'bottom_updated_at' => $bottomUpdatedAt,
            'top_updated_by' => $topUpdatedBy,
            'bottom_updated_by' => $bottomUpdatedBy,
        );
        $cacheKey = 'admin_draw_component_editor_' . md5(json_encode($cacheSource));
        if (isset($this->managedDrawComponentEditorRequestCache[$cacheKey])) {
            return $this->managedDrawComponentEditorRequestCache[$cacheKey];
        }

        $cachedEditor = $this->app->cache()->get($cacheKey, null, 20);
        if (is_array($cachedEditor)) {
            $this->managedDrawComponentEditorRequestCache[$cacheKey] = $cachedEditor;

            return $cachedEditor;
        }

        $topHasCustomized = trim($topUpdatedAt) !== '' || trim($topUpdatedBy) !== '' || trim($topStoredHtml) !== '';
        $bottomHasCustomized = trim($bottomUpdatedAt) !== '' || trim($bottomUpdatedBy) !== '' || trim($bottomStoredHtml) !== '';
        $topHasContent = $this->managedDrawComponentHtmlLooksComplete('top_float', $topStoredHtml);
        $bottomHasContent = $this->managedDrawComponentHtmlLooksComplete('bottom_float', $bottomStoredHtml);
        $defaults = (!$topHasContent || !$bottomHasContent)
            ? $this->managedDrawDefaultComponentTemplates()
            : array();
        $topContentHtml = $this->sanitizeManagedDrawComponentHtml($topHasContent ? $topStoredHtml : (string) ($defaults['top_float'] ?? ''));
        $bottomContentHtml = $this->sanitizeManagedDrawComponentHtml($bottomHasContent ? $bottomStoredHtml : (string) ($defaults['bottom_float'] ?? ''));
        $storedContentHtml = trim(
            $this->sanitizeManagedDrawComponentHtml($topStoredHtml)
            . "\n\n"
            . $this->sanitizeManagedDrawComponentHtml($bottomStoredHtml)
        );
        $latestMeta = $this->managedDrawComponentLatestMeta(array(
            array('updated_at' => $topUpdatedAt, 'updated_by' => $topUpdatedBy),
            array('updated_at' => $bottomUpdatedAt, 'updated_by' => $bottomUpdatedBy),
        ));

        $editor = array(
            'component_key' => $componentKey,
            'component_label' => (string) $component['label'],
            'content_html' => trim($topContentHtml . "\n\n" . $bottomContentHtml),
            'stored_content_html' => $storedContentHtml,
            'updated_at' => (string) ($latestMeta['updated_at'] ?? ''),
            'updated_by' => (string) ($latestMeta['updated_by'] ?? ''),
            'has_customized' => $topHasCustomized || $bottomHasCustomized,
        );
        $this->managedDrawComponentEditorRequestCache[$cacheKey] = $editor;
        $this->app->cache()->put($cacheKey, $editor);

        return $editor;
    }

    public function saveManagedDrawComponentEditor(array $payload, array $actor)
    {
        $componentKey = $this->normalizeManagedDrawComponentKey((string) ($payload['component_key'] ?? 'float_group'));
        $component = $this->managedDrawComponentMap($componentKey);
        $contentHtml = isset($payload['content_html']) ? $this->sanitizeManagedDrawComponentHtml((string) $payload['content_html']) : '';
        $componentParts = $this->splitManagedDrawComponentHtml($contentHtml);
        $contentIsBlank = trim($contentHtml) === '';
        $topContentHtml = (string) ($componentParts['top_float'] ?? '');
        $bottomContentHtml = (string) ($componentParts['bottom_float'] ?? '');
        $storedTopContentHtml = $this->sanitizeManagedDrawComponentHtml((string) $this->app->settings()->get($component['content_keys']['top_float'], ''));
        $storedBottomContentHtml = $this->sanitizeManagedDrawComponentHtml((string) $this->app->settings()->get($component['content_keys']['bottom_float'], ''));
        $topContentComplete = $this->managedDrawComponentHtmlLooksComplete('top_float', $topContentHtml);
        $bottomContentComplete = $this->managedDrawComponentHtmlLooksComplete('bottom_float', $bottomContentHtml);
        $storedTopContentComplete = $this->managedDrawComponentHtmlLooksComplete('top_float', $storedTopContentHtml);
        $storedBottomContentComplete = $this->managedDrawComponentHtmlLooksComplete('bottom_float', $storedBottomContentHtml);
        $defaultComponents = array();

        if (!$contentIsBlank && trim($topContentHtml) === '' && trim($bottomContentHtml) === '') {
            throw new RuntimeException('未识别到悬浮组件内容，请确认编辑器中包含顶部悬浮卡片或底部悬浮卡片后再保存。');
        }

        if (!$contentIsBlank && trim($bottomContentHtml) !== '' && !$this->managedDrawBottomNavHasForecastItem($bottomContentHtml)) {
            throw new RuntimeException('底部悬浮导航必须保留带 bottom-nav-link 类名且文案包含“预测”的预测项，否则香港首页无法自动重写到香港预测。');
        }

        if ($contentIsBlank || !$topContentComplete || !$bottomContentComplete) {
            $defaultComponents = $this->managedDrawDefaultComponentTemplates();
        }

        if ($contentIsBlank || !$topContentComplete) {
            $topContentHtml = $storedTopContentComplete
                ? $storedTopContentHtml
                : (string) ($defaultComponents['top_float'] ?? '');
        }

        if ($contentIsBlank || !$bottomContentComplete) {
            $bottomContentHtml = $storedBottomContentComplete
                ? $storedBottomContentHtml
                : (string) ($defaultComponents['bottom_float'] ?? '');
        }

        $topContentHtml = $this->sanitizeManagedDrawComponentHtml($topContentHtml);
        $bottomContentHtml = $this->sanitizeManagedDrawComponentHtml($bottomContentHtml);

        $updatedAt = $this->now();
        $updatedBy = isset($actor['username']) ? (string) $actor['username'] : '';

        $this->app->settings()->setMany('appearance', array(
            $component['content_keys']['top_float'] => $topContentHtml,
            $component['updated_at_keys']['top_float'] => $updatedAt,
            $component['updated_by_keys']['top_float'] => $updatedBy,
            $component['content_keys']['bottom_float'] => $bottomContentHtml,
            $component['updated_at_keys']['bottom_float'] => $updatedAt,
            $component['updated_by_keys']['bottom_float'] => $updatedBy,
        ));

        $componentLabel = (string) $component['label'];
        $this->app->logs()->admin('draws', 'save_component_editor', '保存组件编辑器内容：' . $componentLabel, 'settings', $componentKey, (int) $actor['id']);
        $this->recordOperation((int) $actor['id'], 'draws', 'save_component_editor', 'settings', 0, '保存组件：' . $componentLabel);

        return array(
            'component_key' => $componentKey,
        );
    }

    public function listManagedIssues(array $filters = array())
    {
        $sql = 'SELECT * FROM lottery_issues WHERE 1 = 1';
        $params = array();
        $keyword = trim((string) ($filters['keyword'] ?? ''));
        $region = trim((string) ($filters['region'] ?? ''));
        $status = trim((string) ($filters['status'] ?? ''));

        if ($keyword !== '') {
            $sql .= ' AND (issue_no LIKE :issue_keyword OR remark LIKE :remark_keyword)';
            $params['issue_keyword'] = '%' . $keyword . '%';
            $params['remark_keyword'] = '%' . $keyword . '%';
        }

        if (in_array($region, array('macau', 'hongkong'), true)) {
            $sql .= ' AND region = :region';
            $params['region'] = $region;
        }

        if (in_array($status, array('pending', 'opened', 'cancelled'), true)) {
            $sql .= ' AND status = :status';
            $params['status'] = $status;
        }

        $sql .= ' ORDER BY is_current DESC, planned_open_at DESC, id DESC LIMIT 120';

        return $this->db()->fetchAll($sql, $params);
    }

    public function managedIssueById($issueId)
    {
        return $this->db()->fetch('SELECT * FROM lottery_issues WHERE id = :id LIMIT 1', array('id' => (int) $issueId));
    }

    public function saveManagedIssue(array $payload, array $actor)
    {
        $issueId = isset($payload['id']) ? (int) $payload['id'] : 0;
        $region = trim((string) ($payload['region'] ?? 'macau'));
        $issueNo = trim((string) ($payload['issue_no'] ?? ''));
        $plannedOpenAt = $this->normalizeDateTimeInput((string) ($payload['planned_open_at'] ?? ''));
        $actualOpenAt = $this->normalizeDateTimeInput((string) ($payload['actual_open_at'] ?? ''));
        $status = trim((string) ($payload['status'] ?? 'pending'));
        $isCurrent = !empty($payload['is_current']) ? 1 : 0;
        $remark = trim((string) ($payload['remark'] ?? ''));
        $isUpdating = $issueId > 0;

        if (!in_array($region, array('macau', 'hongkong'), true)) {
            throw new RuntimeException('期数分区无效。');
        }

        if ($issueNo === '') {
            throw new RuntimeException('期号不能为空。');
        }

        if ($plannedOpenAt === '') {
            throw new RuntimeException('计划开奖时间不能为空。');
        }

        if (!in_array($status, array('pending', 'opened', 'cancelled'), true)) {
            throw new RuntimeException('期数状态无效。');
        }

        $existing = $this->db()->fetch(
            'SELECT * FROM lottery_issues WHERE region = :region AND issue_no = :issue_no LIMIT 1',
            array(
                'region' => $region,
                'issue_no' => $issueNo,
            )
        );

        if ($existing && (int) $existing['id'] !== $issueId) {
            throw new RuntimeException('该分区期号已存在。');
        }

        $data = array(
            'region' => $region,
            'issue_no' => $issueNo,
            'planned_open_at' => $plannedOpenAt,
            'actual_open_at' => $actualOpenAt !== '' ? $actualOpenAt : null,
            'status' => $status,
            'is_current' => $isCurrent,
            'remark' => $remark,
            'updated_at' => $this->now(),
        );

        $this->db()->beginTransaction();

        try {
            if ($isCurrent === 1) {
                $this->db()->execute(
                    'UPDATE lottery_issues SET is_current = 0, updated_at = :updated_at WHERE region = :region' . ($issueId > 0 ? ' AND id <> :id' : ''),
                    $issueId > 0
                        ? array('updated_at' => $this->now(), 'region' => $region, 'id' => $issueId)
                        : array('updated_at' => $this->now(), 'region' => $region)
                );
            }

            if ($issueId > 0) {
                $this->db()->execute(
                    'UPDATE lottery_issues
                     SET region = :region, issue_no = :issue_no, planned_open_at = :planned_open_at, actual_open_at = :actual_open_at,
                         status = :status, is_current = :is_current, remark = :remark, updated_at = :updated_at
                     WHERE id = :id',
                    $data + array('id' => $issueId)
                );
            } else {
                $issueId = $this->db()->insertGetId(
                    'INSERT INTO lottery_issues (region, issue_no, planned_open_at, actual_open_at, status, is_current, remark, created_at, updated_at)
                     VALUES (:region, :issue_no, :planned_open_at, :actual_open_at, :status, :is_current, :remark, :created_at, :updated_at)',
                    $data + array('created_at' => $this->now())
                );
            }

            $this->db()->commit();
        } catch (\Exception $exception) {
            $this->db()->rollBack();
            throw $exception;
        }

        $this->forgetManagedIssueSnapshotCache($region);
        $savedIssue = $this->managedIssueById($issueId);
        $this->recordOperation((int) $actor['id'], 'issues', $isUpdating ? 'save' : 'create', 'lottery_issue', (int) $issueId, '保存期数：' . $region . ' ' . $issueNo);

        return $savedIssue;
    }

    public function managedIssuePrefixTextByRegion($region)
    {
        $snapshot = $this->managedIssuePrefixSnapshotByRegion($region);

        return (string) ($snapshot['issue_prefix_tail'] ?? '');
    }

    public function managedIssuePrefixSnapshotByRegion($region)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        if (isset($this->managedIssuePrefixSnapshotCache[$region])) {
            return $this->managedIssuePrefixSnapshotCache[$region];
        }

        $snapshot = $this->currentIssueSnapshotByRegion($region);
        $issueNo = preg_replace('/\D+/', '', (string) ($snapshot['issue_no'] ?? ''));
        $draw = $this->app->prediction()->latestHomepageDraw((string) ($snapshot['region'] ?? $region));
        $drawIssueNo = is_array($draw) ? preg_replace('/\D+/', '', (string) ($draw['issue_no'] ?? '')) : '';
        if ($drawIssueNo !== '') {
            $targetIssueNo = preg_replace('/\D+/', '', (string) $this->incrementIssueNo($drawIssueNo));
            $lastRunIssueNo = preg_replace(
                '/\D+/',
                '',
                (string) $this->app->settings()->get(
                    'post_generator.schedule_last_run.' . (string) ($snapshot['region'] ?? $region),
                    ''
                )
            );
            if ($targetIssueNo !== '' && $lastRunIssueNo !== '' && (int) $lastRunIssueNo >= (int) $targetIssueNo) {
                $issueNo = $targetIssueNo;
            } elseif ($issueNo === '' || ($targetIssueNo !== '' && (int) $issueNo >= (int) $targetIssueNo)) {
                $issueNo = $drawIssueNo;
            }
        }
        $issueTail = $issueNo !== '' ? str_pad(substr($issueNo, -3), 3, '0', STR_PAD_LEFT) : '';
        $snapshot['issue_no'] = $issueNo;
        $snapshot['issue_prefix_tail'] = $issueTail;
        $snapshot['issue_prefix_text'] = $issueTail !== '' ? $issueTail . '期' : '';

        $this->managedIssuePrefixSnapshotCache[$region] = $snapshot;

        return $snapshot;
    }

    public function currentIssueSnapshotByRegion($region)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        if (isset($this->currentIssueSnapshotCache[$region])) {
            return $this->currentIssueSnapshotCache[$region];
        }

        $label = $region === 'hongkong'
            ? html_entity_decode('&#39321;&#28207;', ENT_QUOTES, 'UTF-8')
            : html_entity_decode('&#28595;&#38376;', ENT_QUOTES, 'UTF-8');
        $issue = $this->db()->fetch(
            'SELECT * FROM lottery_issues WHERE region = :region AND is_current = 1 ORDER BY id DESC LIMIT 1',
            array('region' => $region)
        );

        if (!$issue) {
            $issue = $this->db()->fetch(
                'SELECT * FROM lottery_issues WHERE region = :region ORDER BY planned_open_at DESC, id DESC LIMIT 1',
                array('region' => $region)
            );
        }

        $snapshot = array(
            'region' => $region,
            'label' => $label,
            'issue_no' => $issue ? (string) ($issue['issue_no'] ?? '') : '',
            'planned_open_at' => $issue ? (string) ($issue['planned_open_at'] ?? '') : '',
            'actual_open_at' => $issue ? (string) ($issue['actual_open_at'] ?? '') : '',
            'status' => $issue ? (string) ($issue['status'] ?? 'pending') : '',
            'remark' => $issue ? (string) ($issue['remark'] ?? '') : '',
            'is_current' => $issue ? (int) ($issue['is_current'] ?? 0) : 0,
        );
        $this->currentIssueSnapshotCache[$region] = $snapshot;

        return $snapshot;
    }

    protected function forgetManagedIssueSnapshotCache($region = null)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : ((string) $region === 'macau' ? 'macau' : '');
        if ($region === '') {
            $this->managedIssuePrefixSnapshotCache = array();
            $this->currentIssueSnapshotCache = array();

            return;
        }

        unset($this->managedIssuePrefixSnapshotCache[$region], $this->currentIssueSnapshotCache[$region]);
    }

    public function managedNormalizeForecastTypeText($typeText)
    {
        $typeText = trim((string) $typeText);
        if ($typeText === '') {
            return '';
        }

        return strtr($typeText, array(
            '肖＋码' => '肖+码',
            '肖码' => '肖+码',
            '波色号码' => '波色+号码',
            '波色码' => '波色+号码',
        ));
    }

    public function managedNormalizeForecastPredictionCount($typeText, $predictionText, $issueText, $region, $statusText)
    {
        return trim((string) $predictionText);
    }

    public function managedForecastDisplayLine($region, $line)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $line = (string) $line;
        if ($line === '' || mb_strpos($line, '待开奖', 0, 'UTF-8') === false || mb_strpos($line, '开', 0, 'UTF-8') === false) {
            return $line;
        }

        if (!preg_match('/^\s*(\d{1,6})\s*期/u', $line, $issueMatches)) {
            return $line;
        }

        $issueDigits = preg_replace('/\D+/u', '', (string) ($issueMatches[1] ?? ''));
        $issueTail = $issueDigits !== '' ? str_pad(substr($issueDigits, -3), 3, '0', STR_PAD_LEFT) : '';
        if ($issueTail === '') {
            return $line;
        }

        $cacheKey = $region . '|' . $issueTail;
        if (!array_key_exists($cacheKey, $this->managedForecastDisplayLineCache)) {
            $this->managedForecastDisplayLineCache[$cacheKey] = '';
            if ($this->tableExists($this->db(), 'lottery_draws')) {
                $drawRow = $this->db()->fetch(
                    'SELECT draw_date, special_number
                     FROM lottery_draws
                     WHERE region = :region
                       AND (issue_no = :issue_no OR RIGHT(issue_no, 3) = :issue_tail)
                     ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
                     LIMIT 1',
                    array(
                        'region' => $region,
                        'issue_no' => $issueDigits,
                        'issue_tail' => $issueTail,
                    )
                );
                if (is_array($drawRow)) {
                    $specialNumber = (int) ($drawRow['special_number'] ?? 0);
                    if ($specialNumber >= 1 && $specialNumber <= 49) {
                        $displayNumber = str_pad((string) $specialNumber, 2, '0', STR_PAD_LEFT);
                        $displayZodiac = $this->managedPostGeneratorZodiacByNumber($specialNumber, (string) ($drawRow['draw_date'] ?? ''));
                        $this->managedForecastDisplayLineCache[$cacheKey] = $displayNumber . $displayZodiac;
                    }
                }
            }
        }

        $displayResult = (string) $this->managedForecastDisplayLineCache[$cacheKey];
        if ($displayResult === '') {
            return $line;
        }

        return (string) preg_replace('/(开[:：]\s*)待开奖/u', '${1}' . $displayResult, $line, 1);
    }

    public function managedForecastRecordStats($region, $contentText, $recentResultLog = '', $includeContent = true, $includeRecentLog = true)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $statsCacheKey = md5(
            $region . "\n"
            . (string) $contentText . "\n"
            . (string) $recentResultLog . "\n"
            . ($includeContent ? '1' : '0') . "\n"
            . ($includeRecentLog ? '1' : '0')
        );
        if (array_key_exists($statsCacheKey, $this->managedForecastRecordStatsCache)) {
            return $this->managedForecastRecordStatsCache[$statsCacheKey];
        }

        $normalizePredictionText = static function ($predictionText) {
            $predictionText = trim((string) $predictionText);
            $predictionText = preg_replace('/^[【〖《｛〔『]\s*/u', '', $predictionText);
            $predictionText = preg_replace('/\s*[】〗》｝〕』]$/u', '', (string) $predictionText);
            $predictionText = str_replace('+', ' ', (string) $predictionText);

            return trim((string) $predictionText);
        };
        $resolveNumberElement = static function ($number) {
            $number = (int) $number;
            $elementGroups = array(
                '金' => array(2, 3, 10, 11, 24, 25, 32, 33, 40, 41),
                '木' => array(6, 7, 14, 15, 22, 23, 36, 37, 44, 45),
                '水' => array(12, 13, 20, 21, 28, 29, 42, 43),
                '火' => array(1, 8, 9, 16, 17, 30, 31, 38, 39, 46, 47),
                '土' => array(4, 5, 18, 19, 26, 27, 34, 35, 48, 49),
            );
            foreach ($elementGroups as $elementName => $elementNumbers) {
                if (in_array($number, $elementNumbers, true)) {
                    return $elementName;
                }
            }

            return '';
        };
        $resolveOpenInfo = function ($openResult, $issueText) use ($region, $resolveNumberElement) {
            $openResult = trim((string) $openResult);
            $issueDigits = preg_replace('/\D+/', '', (string) $issueText);
            $issueTail = $issueDigits !== '' ? str_pad(substr($issueDigits, -3), 3, '0', STR_PAD_LEFT) : '';
            $cacheKey = $region . '|' . $issueTail;
            if (!array_key_exists($cacheKey, $this->managedForecastDrawCache)) {
                $this->managedForecastDrawCache[$cacheKey] = null;
                if ($issueTail !== '' && $this->tableExists($this->db(), 'lottery_draws')) {
                    $this->managedForecastDrawCache[$cacheKey] = $this->db()->fetch(
                        'SELECT draw_date, numbers_json, special_number
                         FROM lottery_draws
                         WHERE region = :region
                           AND (issue_no = :issue_no OR RIGHT(issue_no, 3) = :issue_tail)
                         ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
                         LIMIT 1',
                        array(
                            'region' => $region,
                            'issue_no' => $issueDigits,
                            'issue_tail' => $issueTail,
                        )
                    );
                }
            }
            $drawRow = is_array($this->managedForecastDrawCache[$cacheKey]) ? $this->managedForecastDrawCache[$cacheKey] : null;
            $drawDate = is_array($drawRow) ? trim((string) ($drawRow['draw_date'] ?? '')) : '';
            if (($openResult === '' || $openResult === '--' || mb_strpos($openResult, '待开奖', 0, 'UTF-8') !== false) && is_array($drawRow)) {
                $specialNumber = (int) ($drawRow['special_number'] ?? 0);
                if ($specialNumber >= 1 && $specialNumber <= 49) {
                    $openResult = str_pad((string) $specialNumber, 2, '0', STR_PAD_LEFT);
                }
            }

            $number = 0;
            if (preg_match('/(?<!\d)(0?[1-9]|[1-4]\d)(?!\d)/u', $openResult, $numberMatch)) {
                $number = (int) $numberMatch[1];
            }
            $zodiac = '';
            foreach (array('鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪') as $zodiacName) {
                if (mb_strpos($openResult, $zodiacName, 0, 'UTF-8') !== false) {
                    $zodiac = $zodiacName;
                    break;
                }
            }
            if ($number > 0) {
                $resolvedZodiac = $this->managedPostGeneratorZodiacByNumber($number, $drawDate);
                if ($resolvedZodiac !== '') {
                    $zodiac = $resolvedZodiac;
                }
            }
            if ($number <= 0 && $zodiac === '') {
                return null;
            }

            $allDrawNumbers = array();
            $regularDrawNumbers = array();
            $regularNumbers = is_array($drawRow) ? json_decode((string) ($drawRow['numbers_json'] ?? ''), true) : null;
            if (is_array($regularNumbers)) {
                foreach ($regularNumbers as $regularNumber) {
                    $regularNumber = (int) $regularNumber;
                    if ($regularNumber >= 1 && $regularNumber <= 49) {
                        $allDrawNumbers[] = $regularNumber;
                        $regularDrawNumbers[] = $regularNumber;
                    }
                }
            }
            if (is_array($drawRow)) {
                $specialNumber = (int) ($drawRow['special_number'] ?? 0);
                if ($specialNumber >= 1 && $specialNumber <= 49) {
                    $allDrawNumbers[] = $specialNumber;
                }
            }
            if (empty($allDrawNumbers) && $number >= 1 && $number <= 49) {
                $allDrawNumbers[] = $number;
            }
            $allDrawNumbers = array_values(array_unique($allDrawNumbers));
            $regularDrawNumbers = array_values(array_unique($regularDrawNumbers));
            $allDrawNumberTexts = array();
            $regularDrawNumberTexts = array();
            $allDrawTails = array();
            $allDrawZodiacs = array();
            $regularDrawZodiacs = array();
            foreach ($regularDrawNumbers as $regularDrawNumber) {
                $regularDrawNumberText = str_pad((string) ((int) $regularDrawNumber), 2, '0', STR_PAD_LEFT);
                $regularDrawNumberTexts[] = $regularDrawNumberText;
                $regularDrawZodiac = $this->managedPostGeneratorZodiacByNumber((int) $regularDrawNumber, $drawDate);
                if ($regularDrawZodiac !== '' && !in_array($regularDrawZodiac, $regularDrawZodiacs, true)) {
                    $regularDrawZodiacs[] = $regularDrawZodiac;
                }
            }
            foreach ($allDrawNumbers as $drawNumber) {
                $drawNumber = (int) $drawNumber;
                $drawNumberText = str_pad((string) $drawNumber, 2, '0', STR_PAD_LEFT);
                $allDrawNumberTexts[] = $drawNumberText;
                $drawTail = (string) ($drawNumber % 10) . '尾';
                if (!in_array($drawTail, $allDrawTails, true)) {
                    $allDrawTails[] = $drawTail;
                }
                $drawZodiac = $this->managedPostGeneratorZodiacByNumber($drawNumber, $drawDate);
                if ($drawZodiac !== '' && !in_array($drawZodiac, $allDrawZodiacs, true)) {
                    $allDrawZodiacs[] = $drawZodiac;
                }
            }

            return array(
                'number_text' => $number > 0 ? str_pad((string) $number, 2, '0', STR_PAD_LEFT) : '',
                'regular_number_texts' => array_values(array_unique($regularDrawNumberTexts)),
                'all_number_texts' => array_values(array_unique($allDrawNumberTexts)),
                'zodiac' => $zodiac,
                'all_zodiacs' => $allDrawZodiacs,
                'regular_zodiacs' => $regularDrawZodiacs,
                'all_tails' => $allDrawTails,
                'head' => $number > 0 ? ((string) floor($number / 10) . '头') : '',
                'tail' => $number > 0 ? ((string) ($number % 10) . '尾') : '',
                'wave' => $number > 0 ? $this->managedPostGeneratorWaveByNumber($number) : '',
                'element' => $number > 0 ? $resolveNumberElement($number) : '',
                'odd_even' => $number > 0 ? ($number % 2 === 1 ? '单' : '双') : '',
                'big_small' => $number > 0 ? ($number >= 25 ? '大' : '小') : '',
            );
        };
        $normalCodeStructure = static function ($label) {
            $label = trim((string) $label);
            if (preg_match('/([零一二两三四五六七八九十\d]+)组([二三23②③])中\2/u', $label, $groupMatches)) {
                $groupHitCountText = (string) ($groupMatches[2] ?? '');
                $groupHitCount = ctype_digit($groupHitCountText)
                    ? (int) $groupHitCountText
                    : (int) strtr($groupHitCountText, array('二' => '2', '三' => '3', '②' => '2', '③' => '3'));

                return array('mode' => 'group', 'hit_count' => max(1, min(3, $groupHitCount)));
            }
            if (preg_match('/([零一二两三四五六七八九十\d]+)码复(?:式|试)([二三23②③])中\2/u', $label, $comboMatches)) {
                $comboHitCountText = (string) ($comboMatches[2] ?? '');
                $comboHitCount = ctype_digit($comboHitCountText)
                    ? (int) $comboHitCountText
                    : (int) strtr($comboHitCountText, array('二' => '2', '三' => '3', '②' => '2', '③' => '3'));

                return array('mode' => 'combo', 'hit_count' => max(1, min(3, $comboHitCount)));
            }
            return array('mode' => '', 'hit_count' => 0);
        };
        $normalCodeHit = static function ($text, array $numbers, array $regularNumbers, array $structure) {
            if (empty($numbers) || empty($regularNumbers)) {
                return false;
            }
            $hitCount = max(1, (int) ($structure['hit_count'] ?? 0));
            $extractNumbers = static function ($groupText) {
                $groupNumbers = array();
                if (preg_match_all('/(?<!\d)(0?[1-9]|[1-4]\d)(?![\d头尾])/u', (string) $groupText, $groupMatches)) {
                    foreach ((array) ($groupMatches[1] ?? array()) as $groupMatch) {
                        $groupNumber = (int) $groupMatch;
                        if ($groupNumber >= 1 && $groupNumber <= 49) {
                            $groupNumbers[] = str_pad((string) $groupNumber, 2, '0', STR_PAD_LEFT);
                        }
                    }
                }

                return $groupNumbers;
            };
            if (($structure['mode'] ?? '') === 'group') {
                $groups = array();
                if (preg_match_all('/[【〖《｛〔『{]\s*([^【〖《｛〔『{】〗》｝〕』}]+?)\s*[】〗》｝〕』}]/u', (string) $text, $groupMatches)) {
                    foreach ((array) ($groupMatches[1] ?? array()) as $groupBody) {
                        $groupNumbers = $extractNumbers((string) $groupBody);
                        if ($groupNumbers !== array()) {
                            $groups[] = $groupNumbers;
                        }
                    }
                }
                if ($groups === array() && mb_strpos((string) $text, '|', 0, 'UTF-8') !== false) {
                    foreach ((array) preg_split('/\s*\|\s*/u', (string) $text) as $segment) {
                        $groupNumbers = $extractNumbers((string) $segment);
                        if ($groupNumbers !== array()) {
                            $groups[] = $groupNumbers;
                        }
                    }
                }
                if ($groups === array()) {
                    $numberSequence = $extractNumbers((string) $text);
                    $groups = array_chunk($numberSequence !== array() ? $numberSequence : $numbers, $hitCount);
                }
                foreach ($groups as $groupNumbers) {
                    if (count(array_intersect(array_values(array_unique((array) $groupNumbers)), $regularNumbers)) >= $hitCount) {
                        return true;
                    }
                }

                return false;
            }
            if (($structure['mode'] ?? '') === 'combo') {
                return count(array_intersect(array_values(array_unique($numbers)), $regularNumbers)) >= $hitCount;
            }

            return count(array_intersect($numbers, $regularNumbers)) > 0;
        };
        $resolveLineStatus = function ($line) use ($normalizePredictionText, $resolveOpenInfo, $normalCodeStructure, $normalCodeHit) {
            $line = trim((string) $line);
            $parts = preg_split('/\s{2,}/u', $line, 4);
            if (!is_array($parts) || count($parts) !== 4) {
                $rowPattern = '/^(\d{1,6}[^:：]{0,12}[:：])\s*(\S+)\s+(\S+)\s+(.+?)\s+(开[:：]\s*.*?)\s*$/u';
                if (!preg_match($rowPattern, $line, $rowMatches)) {
                    return '';
                }
                $parts = array(
                    (string) $rowMatches[1] . (string) $rowMatches[2],
                    (string) $rowMatches[3],
                    (string) $rowMatches[4],
                    (string) $rowMatches[5],
                );
            }
            if (!preg_match('/^(\d{1,6}[^:：]{0,12}[:：])\s*(.+)$/u', (string) $parts[0], $prefixMatches)) {
                return '';
            }

            $openResult = trim((string) $parts[3]);
            $fallbackStatus = '';
            if (preg_match('/^(开[:：])\s*(.*?)\s*(准|中|赢|發|发|错)?\s*$/u', $openResult, $openMatches)) {
                $openResult = trim((string) ($openMatches[2] ?? ''));
                $fallbackStatus = trim((string) ($openMatches[3] ?? ''));
            }

            $typeText = (string) $parts[1];
            $predictionText = $normalizePredictionText((string) $parts[2]);
            $openInfo = $resolveOpenInfo($openResult, (string) $prefixMatches[1]);
            if ($predictionText === '' || !is_array($openInfo)) {
                return $fallbackStatus;
            }

            $hasNumber = false;
            $numberHit = false;
            $allNumberHit = false;
            $predictionNumbers = array();
            if (preg_match_all('/(?<!\d)(0?[1-9]|[1-4]\d)(?![\d头尾])/u', $predictionText, $numberMatches)) {
                foreach ((array) ($numberMatches[1] ?? array()) as $numberMatch) {
                    $number = (int) $numberMatch;
                    if ($number >= 1 && $number <= 49) {
                        $hasNumber = true;
                        $numberText = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                        $predictionNumbers[] = $numberText;
                        if ($numberText === (string) ($openInfo['number_text'] ?? '')) {
                            $numberHit = true;
                        }
                        if (in_array($numberText, (array) ($openInfo['all_number_texts'] ?? array()), true)) {
                            $allNumberHit = true;
                        }
                    }
                }
            }
            $predictionNumbers = array_values(array_unique($predictionNumbers));
            $regularNumberTexts = array_values(array_unique(array_filter(array_map('strval', (array) ($openInfo['regular_number_texts'] ?? array())))));
            if (empty($regularNumberTexts)) {
                $regularNumberTexts = array_values(array_unique(array_filter(array_map('strval', (array) ($openInfo['all_number_texts'] ?? array())))));
            }
            $normalCodeHitResult = $normalCodeHit($predictionText, $predictionNumbers, $regularNumberTexts, $normalCodeStructure($typeText));

            $hasZodiac = false;
            $zodiacHit = false;
            $allZodiacHit = false;
            $regularZodiacHit = false;
            foreach (array('鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪') as $zodiacName) {
                if (mb_strpos($predictionText, $zodiacName, 0, 'UTF-8') !== false) {
                    $hasZodiac = true;
                    if ($zodiacName === (string) ($openInfo['zodiac'] ?? '')) {
                        $zodiacHit = true;
                    }
                    if (in_array($zodiacName, (array) ($openInfo['all_zodiacs'] ?? array()), true)) {
                        $allZodiacHit = true;
                    }
                    if (in_array($zodiacName, (array) ($openInfo['regular_zodiacs'] ?? array()), true)) {
                        $regularZodiacHit = true;
                    }
                }
            }

            $hasHead = (string) ($openInfo['head'] ?? '') !== '' && preg_match('/[0-4]头/u', $predictionText);
            $headHit = $hasHead && mb_strpos($predictionText, (string) $openInfo['head'], 0, 'UTF-8') !== false;
            $hasTail = (string) ($openInfo['tail'] ?? '') !== '' && preg_match('/[0-9]尾/u', $predictionText);
            $tailHit = $hasTail && mb_strpos($predictionText, (string) $openInfo['tail'], 0, 'UTF-8') !== false;
            $allTailHit = false;
            if (preg_match_all('/[0-9]尾/u', $predictionText, $tailMatches)) {
                foreach ((array) ($tailMatches[0] ?? array()) as $tailMatch) {
                    if (in_array((string) $tailMatch, (array) ($openInfo['all_tails'] ?? array()), true)) {
                        $allTailHit = true;
                        break;
                    }
                }
            }
            $hasWave = preg_match('/红波|蓝波|绿波|红|蓝|绿/u', $predictionText);
            $waveHit = $hasWave && (string) ($openInfo['wave'] ?? '') !== '' && mb_strpos($predictionText, (string) $openInfo['wave'], 0, 'UTF-8') !== false;
            $hasElement = (string) ($openInfo['element'] ?? '') !== '' && preg_match('/金|木|水|火|土/u', $predictionText);
            $elementHit = $hasElement && mb_strpos($predictionText, (string) $openInfo['element'], 0, 'UTF-8') !== false;
            $hasOddEven = preg_match('/单|双/u', $predictionText);
            $oddEvenHit = $hasOddEven && (string) ($openInfo['odd_even'] ?? '') !== '' && mb_strpos($predictionText, (string) $openInfo['odd_even'], 0, 'UTF-8') !== false;
            $hasBigSmall = preg_match('/大|小/u', $predictionText);
            $bigSmallHit = $hasBigSmall && (string) ($openInfo['big_small'] ?? '') !== '' && mb_strpos($predictionText, (string) $openInfo['big_small'], 0, 'UTF-8') !== false;
            $typeText = $this->managedNormalizeForecastTypeText($typeText);
            $typeHasZodiac = mb_strpos($typeText, '肖', 0, 'UTF-8') !== false;
            $typeHasNumber = mb_strpos($typeText, '码', 0, 'UTF-8') !== false;
            $typeHasHead = mb_strpos($typeText, '头', 0, 'UTF-8') !== false;
            $typeHasTail = mb_strpos($typeText, '尾', 0, 'UTF-8') !== false;
            $typeHasWave = mb_strpos($typeText, '波', 0, 'UTF-8') !== false;
            $typeHasElement = mb_strpos($typeText, '行', 0, 'UTF-8') !== false;
            $typeHasOddEven = mb_strpos($typeText, '单', 0, 'UTF-8') !== false || mb_strpos($typeText, '双', 0, 'UTF-8') !== false;
            $typeHasBigSmall = mb_strpos($typeText, '大', 0, 'UTF-8') !== false || mb_strpos($typeText, '小', 0, 'UTF-8') !== false;
            $typeUsesAllDrawZodiac = mb_strpos($typeText, '平特', 0, 'UTF-8') !== false;
            $typeUsesRegularDrawZodiac = preg_match('/复式[一二两三四五六七八九十\d]+连肖/u', $typeText);
            $typeIsNormalCode = mb_strpos($typeText, '平码', 0, 'UTF-8') !== false
                || mb_strpos($typeText, '一码三中三', 0, 'UTF-8') !== false
                || preg_match('/[零一二两三四五六七八九十\d]+组([二三23②③])中\1/u', $typeText)
                || preg_match('/[零一二两三四五六七八九十\d]+码复(?:式|试)([二三23②③])中\1/u', $typeText);
            $typeIsKill = mb_strpos($typeText, '绝杀', 0, 'UTF-8') !== false;
            $typeIsMissNumber = mb_strpos($typeText, '不中', 0, 'UTF-8') !== false;
            $typeIsFlatNumber = mb_strpos($typeText, '平特', 0, 'UTF-8') !== false
                && (mb_strpos($typeText, '码', 0, 'UTF-8') !== false || mb_strpos($typeText, '连', 0, 'UTF-8') !== false);
            $typeIsBigSmallNumber = preg_match('/大小[零一二两三四五六七八九十\d]+码/u', $typeText);
            $typeIsOddEvenNumber = preg_match('/单双[零一二两三四五六七八九十\d]+码/u', $typeText);
            $hit = null;
            if ($typeIsMissNumber) {
                $hit = $hasNumber ? !$numberHit : null;
            } elseif ($typeIsKill) {
                $killTargetHit = false;
                $hasKillTarget = false;
                if ($typeHasZodiac && $hasZodiac) {
                    $killTargetHit = $killTargetHit || $zodiacHit;
                    $hasKillTarget = true;
                }
                if ($typeHasTail && $hasTail) {
                    $killTargetHit = $killTargetHit || $tailHit;
                    $hasKillTarget = true;
                }
                if ($typeHasWave && $hasWave) {
                    $killTargetHit = $killTargetHit || $waveHit;
                    $hasKillTarget = true;
                }
                if ($typeHasElement && $hasElement) {
                    $killTargetHit = $killTargetHit || $elementHit;
                    $hasKillTarget = true;
                }
                if ($typeHasHead && $hasHead) {
                    $killTargetHit = $killTargetHit || $headHit;
                    $hasKillTarget = true;
                }
                if ($hasNumber) {
                    $killTargetHit = $killTargetHit || $numberHit;
                    $hasKillTarget = true;
                }
                $hit = $hasKillTarget ? !$killTargetHit : null;
            } elseif ($typeIsBigSmallNumber) {
                $hit = $hasBigSmall ? $bigSmallHit : null;
            } elseif ($typeIsOddEvenNumber) {
                $hit = $hasOddEven ? $oddEvenHit : null;
            } elseif ($typeIsNormalCode) {
                $hit = $hasNumber ? $normalCodeHitResult : null;
            } elseif ($typeIsFlatNumber) {
                $hit = $hasNumber ? $allNumberHit : null;
            } elseif ($typeHasHead) {
                $hit = ($hasHead || ($typeHasNumber && $hasNumber)) ? ($headHit || $numberHit) : null;
            } elseif ($typeHasTail) {
                $hit = mb_strpos($typeText, '平特', 0, 'UTF-8') !== false
                    ? ($hasTail ? $allTailHit : null)
                    : (($hasTail || ($typeHasNumber && $hasNumber)) ? ($tailHit || $numberHit) : null);
            } elseif ($typeHasWave) {
                $hit = ($hasWave || $hasNumber) ? ($waveHit || $numberHit) : null;
            } elseif ($typeHasElement) {
                $hit = $hasElement ? $elementHit : null;
            } elseif ($typeHasZodiac && !$typeHasNumber) {
                $hit = $hasZodiac ? ($typeUsesRegularDrawZodiac ? (!empty($openInfo['regular_zodiacs']) ? $regularZodiacHit : null) : ($typeUsesAllDrawZodiac ? $allZodiacHit : $zodiacHit)) : null;
            } elseif ($typeHasNumber && !$typeHasZodiac) {
                $hit = $hasNumber ? (mb_strpos($typeText, '平特', 0, 'UTF-8') !== false ? $allNumberHit : $numberHit) : null;
            } elseif ($typeHasZodiac && $typeHasNumber) {
                $hit = $hasZodiac ? $zodiacHit : null;
            } elseif ($typeHasOddEven || $typeHasBigSmall) {
                $hit = ($hasOddEven || $hasBigSmall) ? ($oddEvenHit || $bigSmallHit) : null;
            }
            if ($hit === null) {
                return $fallbackStatus;
            }

            return $hit ? '准' : '错';
        };
        $statuses = array();
        $statusRecords = array();
        if ($includeContent) {
            $lines = preg_split('/\R/u', trim((string) $contentText));
            if (is_array($lines)) {
                foreach ($lines as $line) {
                    $line = trim((string) $line);
                    if ($line === '' || mb_strpos($line, '开', 0, 'UTF-8') === false) {
                        continue;
                    }
                    $lineIssueNo = 0;
                    if (preg_match('/^\s*(\d{1,6})/u', $line, $issueMatches)) {
                        $lineIssueNo = (int) ($issueMatches[1] ?? 0);
                    }
                    $lineStatus = $resolveLineStatus($line);
                    if ($lineStatus !== '') {
                        $statusText = $lineStatus;
                        $statuses[] = $statusText;
                        if ($lineIssueNo > 0) {
                            $statusRecords[] = array('issue' => $lineIssueNo, 'status' => $statusText);
                        }
                        continue;
                    }
                    if (preg_match('/(准|中|赢|發|发|错|錯)\s*$/u', $line, $matches)) {
                        $statusText = (string) $matches[1];
                        $statuses[] = $statusText;
                        if ($lineIssueNo > 0) {
                            $statusRecords[] = array('issue' => $lineIssueNo, 'status' => $statusText);
                        }
                    }
                }
            }
        }
        if (empty($statuses) && $includeRecentLog) {
            $items = preg_split('/[,，\s]+/u', trim((string) $recentResultLog));
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (preg_match('/(\d{1,6})\s*(准|中|赢|發|发|错|錯)\s*$/u', trim((string) $item), $matches)) {
                        $statusText = (string) $matches[2];
                        $statuses[] = $statusText;
                        $statusRecords[] = array(
                            'issue' => (int) ($matches[1] ?? 0),
                            'status' => $statusText,
                        );
                    } elseif (preg_match('/(准|中|赢|發|发|错|錯)\s*$/u', trim((string) $item), $matches)) {
                        $statuses[] = (string) $matches[1];
                    }
                }
            }
        }

        $total = count($statuses);
        $hit = 0;
        foreach ($statuses as $status) {
            if (in_array($status, array('准', '中', '赢', '發', '发'), true)) {
                $hit++;
            }
        }

        $result = array(
            'total' => $total,
            'hit' => $hit,
            'miss' => max(0, $total - $hit),
            'rate' => $total > 0 ? round(($hit / $total) * 100, 2) : 0,
            'statuses' => $statuses,
            'records' => $statusRecords,
        );
        $this->managedForecastRecordStatsCache[$statsCacheKey] = $result;

        return $result;
    }

    public function currentIssueSnapshots()
    {
        $regions = array(
            'macau' => '澳门',
            'hongkong' => '香港',
        );
        $items = array();

        foreach ($regions as $region => $label) {
            $issue = $this->db()->fetch(
                'SELECT * FROM lottery_issues WHERE region = :region AND is_current = 1 ORDER BY id DESC LIMIT 1',
                array('region' => $region)
            );

            if (!$issue) {
                $issue = $this->db()->fetch(
                    'SELECT * FROM lottery_issues WHERE region = :region ORDER BY planned_open_at DESC, id DESC LIMIT 1',
                    array('region' => $region)
                );
            }

            $items[] = array(
                'region' => $region,
                'label' => $label,
                'issue_no' => $issue ? (string) ($issue['issue_no'] ?? '') : '',
                'planned_open_at' => $issue ? (string) ($issue['planned_open_at'] ?? '') : '',
                'actual_open_at' => $issue ? (string) ($issue['actual_open_at'] ?? '') : '',
                'status' => $issue ? (string) ($issue['status'] ?? 'pending') : '',
                'remark' => $issue ? (string) ($issue['remark'] ?? '') : '',
                'is_current' => $issue ? (int) ($issue['is_current'] ?? 0) : 0,
            );
        }

        return $items;
    }

    protected function managedAuthorNicknameIdiomLibrary()
    {
        return $this->managedAuthorNicknameIdiomLibraryExpanded();
    }

    protected function managedAuthorNicknameIdiomLibraryExpanded()
    {
        $seedNicknames = array(
            '鸿运当头', '紫气东来', '彩运亨通', '彩光满堂', '彩门大开', '彩源滚滚',
            '彩势如虹', '彩虹高照', '彩星报喜', '彩福盈门', '准星高照', '准彩临门',
            '准势先机', '准门大启', '准绳在握', '准发连连', '准赢常在', '中彩临门',
            '中榜题名', '中门大吉', '中魁得胜', '中盈满仓', '中发长红', '中奖有数',
            '奖运亨通', '奖星高照', '奖门大启', '奖彩腾辉', '奖旺福来', '奖盈满堂',
            '赢彩四方', '赢门大开', '赢运长虹', '赢势冲天', '赢发有道', '赢福满仓',
            '发彩有门', '发运长虹', '发旺生辉', '发福迎祥', '发盈满库', '发达昌隆',
            '富彩盈门', '富运天成', '富盈满仓', '富贵生辉', '富发同春', '富门广开',
            '胜彩有门', '胜券在握', '胜运当头', '胜势如虹', '胜门大吉', '胜发长红',
            '才气纵横', '才华横溢', '才胜兼资', '才锋毕露', '才名远播', '才运高照',
            '稳中求胜', '稳赢有道', '稳彩常临', '稳发常红', '稳进稳赢', '稳操胜券',
            '无敌稳赢', '无敌必中', '无敌通关', '无敌长红', '无敌夺魁', '无敌封神',
            '神机妙算', '神准无双', '神彩飞扬', '神赢四海', '神发长红', '神威大振',
            '天彩高照', '天赢有数', '天发其财', '天富其门', '天机在握', '天成大吉',
            '扶贫助赢', '扶贫添富', '扶贫得彩', '扶贫增福', '扶贫有成', '扶贫长红',
            '天机必现', '天机必中', '天机独得', '天机先知', '天机妙得', '天机高照',
            '内幕独享', '内幕先知', '内幕必中', '内幕高照', '内幕常赢', '内幕连红',
            '透密连赢', '透密先机', '透密得彩', '透密独中', '透密高照', '透密长红',
            '内部独享', '内部必中', '内部得胜', '内部长虹', '内部先知', '内部常赢'
        );

        $prefixPairs = array(
            '彩运', '彩光', '彩门', '彩路', '彩星', '彩盈', '彩源', '彩势', '彩虹', '彩魁',
            '彩旺', '彩耀', '彩福', '彩盛', '彩腾', '彩胜', '彩发', '彩富', '彩天', '彩神',
            '准运', '准星', '准门', '准彩', '准绳', '准势', '准赢', '准发', '准旺', '准魁',
            '准耀', '准胜', '准天', '准神', '准福', '准盛', '准腾', '准富', '准光', '准虹',
            '中彩', '中魁', '中榜', '中门', '中宝', '中盈', '中发', '中兴', '中旺', '中盛',
            '中耀', '中胜', '中福', '中运', '中虹', '中腾', '中光', '中富', '中准', '中神',
            '奖运', '奖门', '奖星', '奖彩', '奖旺', '奖盈', '奖福', '奖盛', '奖顺', '奖魁',
            '奖耀', '奖腾', '奖富', '奖发', '奖胜', '奖光', '奖虹', '奖宝', '奖天', '奖神',
            '赢彩', '赢门', '赢运', '赢势', '赢发', '赢福', '赢盛', '赢丰', '赢耀', '赢鼎',
            '赢旺', '赢魁', '赢顺', '赢富', '赢宝', '赢腾', '赢光', '赢虹', '赢天', '赢神',
            '发彩', '发运', '发旺', '发福', '发盈', '发顺', '发盛', '发达', '发荣', '发兴',
            '发腾', '发耀', '发富', '发胜', '发宝', '发门', '发源', '发光', '发虹', '发天',
            '富彩', '富运', '富盈', '富贵', '富发', '富门', '富盛', '富旺', '富源', '富瑞',
            '富耀', '富腾', '富胜', '富宝', '富福', '富顺', '富虹', '富光', '富魁', '富天',
            '胜彩', '胜券', '胜运', '胜势', '胜门', '胜发', '胜盈', '胜魁', '胜峰', '胜耀',
            '胜腾', '胜富', '胜福', '胜旺', '胜顺', '胜宝', '胜光', '胜虹', '胜神', '胜天',
            '才魁', '才运', '才彩', '才锋', '才胜', '才旺', '才耀', '才丰', '才捷', '才成',
            '才福', '才富', '才门', '才宝', '才虹', '才光', '才腾', '才神', '才准', '才赢',
            '稳中', '稳赢', '稳彩', '稳运', '稳发', '稳进', '稳盛', '稳门', '稳冠', '稳魁',
            '稳耀', '稳福', '稳富', '稳顺', '稳宝', '稳虹', '稳光', '稳腾', '稳天', '稳神',
            '无敌', '神算', '神准', '神彩', '神赢', '神发', '神机', '神威', '神锋', '神旺',
            '神耀', '神盛', '神福', '神富', '神腾', '神虹', '神光', '神门', '神宝', '神天',
            '天彩', '天赢', '天发', '天富', '天盛', '天耀', '天机', '天运', '天成', '天玑',
            '天福', '天宝', '天顺', '天旺', '天虹', '天光', '天腾', '天准', '天神', '天稳',
            '扶富', '扶奖', '扶运', '扶赢', '扶彩', '扶盛', '扶发', '扶盈', '扶贫', '扶强',
            '扶旺', '扶福', '扶顺', '扶耀', '扶腾', '扶宝', '扶门', '扶光', '扶虹', '扶胜',
            '内参', '内幕', '内部', '透密', '透彩', '透赢', '透发', '透富', '透奖', '透准',
            '透福', '透旺', '透耀', '透腾', '透虹', '透光', '透门', '透宝', '透胜', '透神'
        );

        $suffixPairs = array(
            '高照', '当头', '临门', '盈门', '满堂', '满仓', '得胜', '得彩', '得福', '得宝',
            '连赢', '连中', '连发', '稳赢', '稳胜', '必赢', '必发', '必富', '必胜', '常胜',
            '开门', '入怀', '顺达', '兴旺', '丰登', '腾达', '登峰', '致胜', '夺魁', '耀世',
            '先机', '长虹', '长红', '生辉', '添富', '添财', '呈祥', '迎福', '纳吉', '封神'
        );

        $items = array();
        $seen = array();
        foreach ($seedNicknames as $nickname) {
            if (count($items) >= 1000) {
                break;
            }

            if (!isset($seen[$nickname]) && preg_match('/^.{4}$/us', $nickname)) {
                $seen[$nickname] = true;
                $items[] = $nickname;
            }
        }

        foreach ($prefixPairs as $prefix) {
            foreach ($suffixPairs as $suffix) {
                if (count($items) >= 1000) {
                    break 2;
                }

                $nickname = $prefix . $suffix;
                if (!isset($seen[$nickname]) && preg_match('/^.{4}$/us', $nickname)) {
                    $seen[$nickname] = true;
                    $items[] = $nickname;
                }
            }
        }

        return $items;
    }

    protected function normalizeManagedAuthorNicknamePool($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array();
        }

        $parts = preg_split('/[\r\n,，;；、\s]+/u', $raw);
        if (!is_array($parts)) {
            return array();
        }

        $pool = array();
        foreach ($parts as $part) {
            $nickname = trim((string) $part);
            if ($nickname === '' || !preg_match('/^.{4}$/us', $nickname)) {
                continue;
            }
            $pool[] = $nickname;
        }

        return array_values(array_unique($pool));
    }

    protected function normalizeManagedAuthorNicknamePoolSafe($raw)
    {
        $raw = trim((string) $raw);
        if ($raw === '') {
            return array();
        }

        $parts = preg_split('/[\r\n,\x{FF0C};\x{FF1B}\x{3001}\s]+/u', $raw);
        if (!is_array($parts)) {
            return array();
        }

        $pool = array();
        foreach ($parts as $part) {
            $nickname = trim((string) $part);
            if ($nickname === '' || !preg_match('/^.{4}$/us', $nickname)) {
                continue;
            }
            $pool[] = $nickname;
        }

        return array_values(array_unique($pool));
    }

    protected function managedGeneratorExistingAuthorNicknames($region, array $candidatePool = array())
    {
        if (!$this->tableExists($this->db(), 'posts') || !$this->tableExists($this->db(), 'users')) {
            return array();
        }

        $params = array(
            'region' => (string) $region,
            'deleted_status' => 'deleted',
        );
        $candidatePool = array_values(array_unique(array_filter(array_map('strval', $candidatePool), static function ($value) {
            return trim((string) $value) !== '';
        })));

        $sql = 'SELECT DISTINCT COALESCE(NULLIF(post_meta.author_nickname, \'\'), users.username) AS author_name
                FROM posts
                INNER JOIN users ON users.id = posts.author_id
                LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
                WHERE posts.region = :region
                  AND posts.status <> :deleted_status';

        if ($candidatePool !== array()) {
            $candidatePlaceholders = array();
            foreach ($candidatePool as $candidateIndex => $candidateNickname) {
                $paramKey = 'candidate_' . $candidateIndex;
                $candidatePlaceholders[] = ':' . $paramKey;
                $params[$paramKey] = trim((string) $candidateNickname);
            }
            $sql .= ' AND COALESCE(NULLIF(post_meta.author_nickname, \'\'), users.username) IN (' . implode(',', $candidatePlaceholders) . ')';
        }

        $rows = $this->db()->fetchAll($sql, $params);

        $items = array();
        foreach ($rows as $row) {
            $nickname = trim((string) ($row['author_name'] ?? ''));
            if ($nickname !== '') {
                $items[] = $nickname;
            }
        }

        return array_values(array_unique($items));
    }

    protected function managedGeneratorUniqueAuthorNicknames($region, array $preferredPool, $requiredCount)
    {
        $requiredCount = max(1, (int) $requiredCount);
        $fallbackPool = $this->managedAuthorNicknameIdiomLibraryExpanded();
        $existingNicknames = $this->managedGeneratorExistingAuthorNicknames(
            $region,
            array_merge(array_values($preferredPool), $fallbackPool)
        );
        $blockedNicknames = array_fill_keys($existingNicknames, true);
        $preferredAvailable = array();
        $fallbackAvailable = array();

        foreach (array_values($preferredPool) as $candidate) {
            $nickname = trim((string) $candidate);
            if ($nickname === '' || isset($blockedNicknames[$nickname])) {
                continue;
            }
            $this->assertManagedAuthorNicknameAllowed($nickname);
            $preferredAvailable[] = $nickname;
            $blockedNicknames[$nickname] = true;
        }

        foreach ($fallbackPool as $candidate) {
            $nickname = trim((string) $candidate);
            if ($nickname === '' || isset($blockedNicknames[$nickname])) {
                continue;
            }
            $this->assertManagedAuthorNicknameAllowed($nickname);
            $fallbackAvailable[] = $nickname;
            $blockedNicknames[$nickname] = true;
        }

        if (count($preferredAvailable) + count($fallbackAvailable) < $requiredCount) {
            throw new RuntimeException('当前可用作者昵称不足，请减少勾选数量或清理重复作者昵称。');
        }

        shuffle($preferredAvailable);
        shuffle($fallbackAvailable);
        $available = array_merge($preferredAvailable, $fallbackAvailable);
        shuffle($available);
        $groupedAvailable = array();
        foreach ($available as $candidateNickname) {
            $candidateNickname = trim((string) $candidateNickname);
            if ($candidateNickname === '') {
                continue;
            }
            $firstChar = function_exists('mb_substr')
                ? mb_substr($candidateNickname, 0, 1, 'UTF-8')
                : substr($candidateNickname, 0, 1);
            if (!isset($groupedAvailable[$firstChar])) {
                $groupedAvailable[$firstChar] = array();
            }
            $groupedAvailable[$firstChar][] = $candidateNickname;
        }
        $orderedAvailable = array();
        $groupKeys = array_keys($groupedAvailable);
        shuffle($groupKeys);
        while ($groupKeys !== array()) {
            $nextGroupKeys = array();
            $lastGroupKey = '';
            foreach ($groupKeys as $groupKey) {
                if (empty($groupedAvailable[$groupKey])) {
                    continue;
                }
                $orderedAvailable[] = (string) array_shift($groupedAvailable[$groupKey]);
                $lastGroupKey = (string) $groupKey;
                if (!empty($groupedAvailable[$groupKey])) {
                    $nextGroupKeys[] = $groupKey;
                }
            }
            shuffle($nextGroupKeys);
            if (count($nextGroupKeys) > 1 && (string) ($nextGroupKeys[0] ?? '') === $lastGroupKey) {
                $firstGroupKey = array_shift($nextGroupKeys);
                $nextGroupKeys[] = $firstGroupKey;
            }
            $groupKeys = $nextGroupKeys;
        }
        $available = $orderedAvailable;

        return array_slice($available, 0, $requiredCount);
    }

    protected function managedPostGeneratorVariableMap($issueTail, $templateLabel, $authorNickname)
    {
        $templateLabel = trim((string) $templateLabel);
        if ($templateLabel !== '一码三中三') {
            $normalizedTemplateLabel = preg_replace_callback(
                '/([2２二两贰②])中([2２二两贰②])|([3３三叁③])中([3３三叁③])/u',
                static function ($matches) {
                    return trim((string) ($matches[1] ?? '')) !== '' ? '二中二' : '3中3';
                },
                $templateLabel
            );
            if (is_string($normalizedTemplateLabel) && $normalizedTemplateLabel !== '') {
                $templateLabel = $normalizedTemplateLabel;
            }
        }

        return array(
            '[期数]' => trim((string) $issueTail),
            '[帖子类型]' => $templateLabel,
            '[帖子作者]' => trim((string) $authorNickname),
        );
    }

    protected function applyManagedPostGeneratorVariables($value, array $variables)
    {
        return strtr((string) $value, $variables);
    }

    protected function resolveManagedGeneratorAuthorNickname($authorNickname, $issueTail, $templateLabel)
    {
        $rawAuthorNickname = trim((string) $authorNickname);
        if ($rawAuthorNickname === '') {
            return '';
        }

        $plainAuthorNickname = strtr($rawAuthorNickname, array(
            '[期数]' => '',
            '[帖子类型]' => '',
            '[帖子作者]' => '',
        ));

        $resolvedAuthorNickname = $this->applyManagedPostGeneratorVariables(
            $rawAuthorNickname,
            $this->managedPostGeneratorVariableMap($issueTail, $templateLabel, $plainAuthorNickname)
        );

        return trim($resolvedAuthorNickname) !== '' ? trim($resolvedAuthorNickname) : trim($plainAuthorNickname);
    }

    protected function assertManagedAuthorNicknameAllowed($authorNickname)
    {
        $authorNickname = trim((string) $authorNickname);

        if ($authorNickname === '') {
            return;
        }

        $normalizedNickname = function_exists('mb_strtolower')
            ? mb_strtolower($authorNickname, 'UTF-8')
            : strtolower($authorNickname);

        foreach ($this->managedReservedSuperAdminNicknames() as $reservedNickname) {
            $normalizedReservedNickname = function_exists('mb_strtolower')
                ? mb_strtolower($reservedNickname, 'UTF-8')
                : strtolower($reservedNickname);

            if ($normalizedNickname === $normalizedReservedNickname) {
                throw new RuntimeException('作者昵称不能使用超级管理员昵称。');
            }
        }
    }

    protected function managedReservedSuperAdminNicknames()
    {
        $rows = $this->db()->fetchAll(
            'SELECT DISTINCT TRIM(users.username) AS username
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             WHERE roles.role_key = :role_key
               AND users.status = :status',
            array(
                'role_key' => 'super_admin',
                'status' => 'active',
            )
        );
        $nicknames = array();

        foreach ($rows as $row) {
            $nickname = trim((string) ($row['username'] ?? ''));
            if ($nickname !== '') {
                $nicknames[] = $nickname;
            }
        }

        return array_values(array_unique($nicknames));
    }

    protected function resolveFrontContentActor(array $actor)
    {
        $user = null;
        if (!empty($actor['username'])) {
            $user = $this->app->users()->findByUsername((string) $actor['username']);
        }

        if (!$user) {
            $user = $this->db()->fetch('SELECT users.*, roles.role_key, roles.role_name
                                       FROM users
                                       INNER JOIN roles ON roles.id = users.role_id
                                       WHERE users.status = :status
                                       ORDER BY users.id ASC
                                       LIMIT 1', array('status' => 'active'));
        }

        if (!$user) {
            throw new RuntimeException('当前前台用户库中没有可用作者账号，请先创建前台用户后再新增帖子或开奖。');
        }

        return array(
            'id' => (int) $user['id'],
            'username' => (string) $user['username'],
        );
    }

    protected function runSchemaOn(Database $database)
    {
        $schema = (string) file_get_contents($this->app->basePath('database/schema.sql'));
        $statements = array_filter(array_map('trim', explode(';', $schema)));

        foreach ($statements as $statement) {
            if ($statement !== '') {
                $database->pdo()->exec($statement);
            }
        }

        $menuPermissionColumn = $database->fetch(
            'SELECT COUNT(*) AS total_count
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
            array(
                'table_name' => 'admin_menus',
                'column_name' => 'permission_code',
            )
        );

        if (!$menuPermissionColumn || (int) $menuPermissionColumn['total_count'] === 0) {
            $database->pdo()->exec("ALTER TABLE admin_menus ADD COLUMN permission_code VARCHAR(100) NOT NULL DEFAULT '' AFTER component_key");
        }

        if ($this->tableExists($database, 'posts') && !$this->columnExists($database, 'posts', 'section_id')) {
            $database->pdo()->exec("ALTER TABLE posts ADD COLUMN section_id BIGINT UNSIGNED DEFAULT NULL AFTER region, ADD INDEX idx_posts_section_id (section_id)");
        }

        if ($this->tableExists($database, 'posts') && !$this->columnExists($database, 'posts', 'category_id')) {
            $database->pdo()->exec("ALTER TABLE posts ADD COLUMN category_id BIGINT UNSIGNED DEFAULT NULL AFTER section_id, ADD INDEX idx_posts_category_id (category_id)");
        }

        if ($this->tableExists($database, 'posts') && !$this->columnExists($database, 'posts', 'deleted_at')) {
            $database->pdo()->exec("ALTER TABLE posts ADD COLUMN deleted_at DATETIME DEFAULT NULL AFTER updated_at, ADD INDEX idx_posts_deleted_at (deleted_at)");
        }

        if ($this->tableExists($database, 'users') && !$this->columnExists($database, 'users', 'recharge_score_total')) {
            $database->pdo()->exec("ALTER TABLE users ADD COLUMN recharge_score_total INT NOT NULL DEFAULT 0 AFTER score");
        }

        if ($this->tableExists($database, 'replies') && !$this->columnExists($database, 'replies', 'parent_id')) {
            $database->pdo()->exec("ALTER TABLE replies ADD COLUMN parent_id BIGINT UNSIGNED DEFAULT NULL AFTER post_id, ADD INDEX idx_replies_parent (parent_id, created_at)");
        }

        if ($this->tableExists($database, 'replies') && !$this->columnExists($database, 'replies', 'like_count')) {
            $database->pdo()->exec("ALTER TABLE replies ADD COLUMN like_count INT NOT NULL DEFAULT 0 AFTER content");
        }

        if ($this->tableExists($database, 'post_manage_meta') && !$this->columnExists($database, 'post_manage_meta', 'author_nickname')) {
            $database->pdo()->exec("ALTER TABLE post_manage_meta ADD COLUMN author_nickname VARCHAR(60) NOT NULL DEFAULT '' AFTER manual_material");
        }

        if ($this->tableExists($database, 'post_manage_meta') && !$this->columnExists($database, 'post_manage_meta', 'title_prefix_text')) {
            $database->pdo()->exec("ALTER TABLE post_manage_meta ADD COLUMN title_prefix_text VARCHAR(120) NOT NULL DEFAULT '' AFTER author_nickname");
        }

        if ($this->tableExists($database, 'post_manage_meta') && !$this->columnExists($database, 'post_manage_meta', 'title_middle_text')) {
            $database->pdo()->exec("ALTER TABLE post_manage_meta ADD COLUMN title_middle_text VARCHAR(120) NOT NULL DEFAULT '' AFTER title_prefix_text");
        }

        if ($this->tableExists($database, 'post_manage_meta') && !$this->columnExists($database, 'post_manage_meta', 'title_prefix_color_mode')) {
            $database->pdo()->exec("ALTER TABLE post_manage_meta ADD COLUMN title_prefix_color_mode VARCHAR(20) NOT NULL DEFAULT '' AFTER title_middle_text");
        }

        if ($this->tableExists($database, 'post_manage_meta') && !$this->columnExists($database, 'post_manage_meta', 'title_prefix_color_value')) {
            $database->pdo()->exec("ALTER TABLE post_manage_meta ADD COLUMN title_prefix_color_value VARCHAR(20) NOT NULL DEFAULT '' AFTER title_prefix_color_mode");
        }

        if ($this->tableExists($database, 'post_manage_meta') && !$this->columnExists($database, 'post_manage_meta', 'title_middle_color_mode')) {
            $database->pdo()->exec("ALTER TABLE post_manage_meta ADD COLUMN title_middle_color_mode VARCHAR(20) NOT NULL DEFAULT '' AFTER title_prefix_color_value");
        }

        if ($this->tableExists($database, 'post_manage_meta') && !$this->columnExists($database, 'post_manage_meta', 'title_middle_color_value')) {
            $database->pdo()->exec("ALTER TABLE post_manage_meta ADD COLUMN title_middle_color_value VARCHAR(20) NOT NULL DEFAULT '' AFTER title_middle_color_mode");
        }

        if ($this->tableExists($database, 'post_manage_meta') && !$this->columnExists($database, 'post_manage_meta', 'author_nickname_color_mode')) {
            $database->pdo()->exec("ALTER TABLE post_manage_meta ADD COLUMN author_nickname_color_mode VARCHAR(20) NOT NULL DEFAULT '' AFTER title_middle_color_value");
        }

        if ($this->tableExists($database, 'post_manage_meta') && !$this->columnExists($database, 'post_manage_meta', 'author_nickname_color_value')) {
            $database->pdo()->exec("ALTER TABLE post_manage_meta ADD COLUMN author_nickname_color_value VARCHAR(20) NOT NULL DEFAULT '' AFTER author_nickname_color_mode");
        }

        if ($this->tableExists($database, 'post_manage_meta') && !$this->columnExists($database, 'post_manage_meta', 'title_font_size')) {
            $database->pdo()->exec("ALTER TABLE post_manage_meta ADD COLUMN title_font_size VARCHAR(8) NOT NULL DEFAULT '' AFTER author_nickname_color_value");
        }

        if ($this->tableExists($database, 'post_manage_meta') && !$this->columnExists($database, 'post_manage_meta', 'title_font_weight')) {
            $database->pdo()->exec("ALTER TABLE post_manage_meta ADD COLUMN title_font_weight VARCHAR(8) NOT NULL DEFAULT '' AFTER title_font_size");
        }

        if ($this->tableExists($database, 'post_manage_meta') && !$this->columnExists($database, 'post_manage_meta', 'deleted_issue_text')) {
            $database->pdo()->exec("ALTER TABLE post_manage_meta ADD COLUMN deleted_issue_text VARCHAR(20) NOT NULL DEFAULT '' AFTER title_font_weight");
        }

        if ($this->tableExists($database, 'page_views') && !$this->columnExists($database, 'page_views', 'province_name')) {
            $database->pdo()->exec("ALTER TABLE page_views ADD COLUMN province_name VARCHAR(60) NOT NULL DEFAULT '' AFTER ip_address");
        }

        if ($this->tableExists($database, 'page_views') && !$this->columnExists($database, 'page_views', 'city_name')) {
            $database->pdo()->exec("ALTER TABLE page_views ADD COLUMN city_name VARCHAR(60) NOT NULL DEFAULT '' AFTER province_name");
        }

        if ($this->tableExists($database, 'post_unique_views') && !$this->columnExists($database, 'post_unique_views', 'identity_type')) {
            $database->pdo()->exec("ALTER TABLE post_unique_views ADD COLUMN identity_type VARCHAR(20) NOT NULL DEFAULT 'guest' AFTER user_id");
        }

        if ($this->tableExists($database, 'post_unique_views') && !$this->columnExists($database, 'post_unique_views', 'identity_key')) {
            $database->pdo()->exec("ALTER TABLE post_unique_views ADD COLUMN identity_key VARCHAR(120) NOT NULL DEFAULT '' AFTER identity_type");
        }

        if ($this->tableExists($database, 'post_unique_views') && !$this->columnExists($database, 'post_unique_views', 'ip_address')) {
            $database->pdo()->exec("ALTER TABLE post_unique_views ADD COLUMN ip_address VARCHAR(64) NOT NULL DEFAULT '' AFTER identity_key");
        }

        if ($this->tableExists($database, 'post_unique_views') && !$this->columnExists($database, 'post_unique_views', 'user_agent')) {
            $database->pdo()->exec("ALTER TABLE post_unique_views ADD COLUMN user_agent VARCHAR(255) DEFAULT NULL AFTER ip_address");
        }

        if ($this->tableExists($database, 'post_unique_views') && !$this->columnExists($database, 'post_unique_views', 'viewed_on')) {
            $database->pdo()->exec("ALTER TABLE post_unique_views ADD COLUMN viewed_on DATE NOT NULL AFTER user_agent");
        }

        if ($this->tableExists($database, 'post_unique_views') && !$this->columnExists($database, 'post_unique_views', 'updated_at')) {
            $database->pdo()->exec("ALTER TABLE post_unique_views ADD COLUMN updated_at DATETIME NOT NULL AFTER created_at");
        }

        if ($this->tableExists($database, 'post_view_display_events') && !$this->columnExists($database, 'post_view_display_events', 'event_no')) {
            $database->pdo()->exec("ALTER TABLE post_view_display_events ADD COLUMN event_no SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER unique_view_id");
        }

        if ($this->tableExists($database, 'post_view_display_events') && !$this->columnExists($database, 'post_view_display_events', 'release_at')) {
            $database->pdo()->exec("ALTER TABLE post_view_display_events ADD COLUMN release_at DATETIME NOT NULL AFTER event_no");
        }

        $this->seedMemberRoles($database);
    }

    protected function seedMemberRoles(Database $database)
    {
        $now = $this->now();
        $roles = array(
            'member' => '普通会员',
            'vip1' => 'VIP1会员',
            'vip2' => 'VIP2会员',
            'vip3' => 'VIP3会员',
            'vip_annual' => 'VIP年度会员',
            'super_vip' => '超级VIP会员',
        );

        foreach ($roles as $roleKey => $roleName) {
            $exists = $database->fetch('SELECT id FROM roles WHERE role_key = :role_key LIMIT 1', array(
                'role_key' => $roleKey,
            ));

            if ($exists) {
                $database->execute('UPDATE roles SET role_name = :role_name, updated_at = :updated_at WHERE id = :id', array(
                    'role_name' => $roleName,
                    'updated_at' => $now,
                    'id' => (int) $exists['id'],
                ));
                continue;
            }

            $database->insertGetId('INSERT INTO roles (role_key, role_name, created_at, updated_at) VALUES (:role_key, :role_name, :created_at, :updated_at)', array(
                'role_key' => $roleKey,
                'role_name' => $roleName,
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }
    }

    protected function seedDefaults(Database $database)
    {
        $roleIds = array();
        foreach ($this->defaultRoles() as $role) {
            $row = $database->fetch('SELECT id FROM admin_roles WHERE code = :code LIMIT 1', array('code' => $role['code']));
            if ($row) {
                $roleIds[$role['code']] = (int) $row['id'];
                continue;
            }

            $roleIds[$role['code']] = $database->insertGetId(
                'INSERT INTO admin_roles (name, code, description, data_scope, status, sort_order, created_at, updated_at)
                 VALUES (:name, :code, :description, :data_scope, :status, :sort_order, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    description = VALUES(description),
                    data_scope = VALUES(data_scope),
                    status = VALUES(status),
                    sort_order = VALUES(sort_order),
                    updated_at = VALUES(updated_at),
                    id = LAST_INSERT_ID(id)',
                array(
                    'name' => $role['name'],
                    'code' => $role['code'],
                    'description' => $role['description'],
                    'data_scope' => $role['data_scope'],
                    'status' => 1,
                    'sort_order' => $role['sort_order'],
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                )
            );
        }

        $permissionIds = array();
        foreach (array_merge($this->defaultPermissions(), $this->phaseOneExtraPermissions()) as $permission) {
            $row = $database->fetch('SELECT id FROM admin_permissions WHERE code = :code LIMIT 1', array('code' => $permission['code']));
            if ($row) {
                $permissionIds[$permission['code']] = (int) $row['id'];
                continue;
            }

            $permissionIds[$permission['code']] = $database->insertGetId(
                'INSERT INTO admin_permissions (parent_id, name, code, type, module, route_path, method, sort_order, status, created_at, updated_at)
                 VALUES (0, :name, :code, :type, :module, :route_path, :method, :sort_order, 1, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    name = VALUES(name),
                    type = VALUES(type),
                    module = VALUES(module),
                    route_path = VALUES(route_path),
                    method = VALUES(method),
                    sort_order = VALUES(sort_order),
                    updated_at = VALUES(updated_at),
                    id = LAST_INSERT_ID(id)',
                array(
                    'name' => $permission['name'],
                    'code' => $permission['code'],
                    'type' => $permission['type'],
                    'module' => $permission['module'],
                    'route_path' => $permission['route_path'],
                    'method' => $permission['method'],
                    'sort_order' => $permission['sort_order'],
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                )
            );
        }

        $menuIds = array();
        foreach (array_merge($this->defaultMenus(), $this->phaseOneExtraMenus()) as $menu) {
            $row = $database->fetch('SELECT id FROM admin_menus WHERE code = :code LIMIT 1', array('code' => $menu['code']));
            if ($row) {
                $menuIds[$menu['code']] = (int) $row['id'];
                continue;
            }

            $menuIds[$menu['code']] = $database->insertGetId(
                'INSERT INTO admin_menus (parent_id, title, code, icon, route_path, component_key, permission_code, menu_type, is_visible, sort_order, status, created_at, updated_at)
                 VALUES (0, :title, :code, :icon, :route_path, :component_key, :permission_code, :menu_type, 1, :sort_order, 1, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    title = VALUES(title),
                    icon = VALUES(icon),
                    route_path = VALUES(route_path),
                    component_key = VALUES(component_key),
                    permission_code = VALUES(permission_code),
                    menu_type = VALUES(menu_type),
                    sort_order = VALUES(sort_order),
                    updated_at = VALUES(updated_at),
                    id = LAST_INSERT_ID(id)',
                array(
                    'title' => $menu['title'],
                    'code' => $menu['code'],
                    'icon' => $menu['icon'],
                    'route_path' => $menu['route_path'],
                    'component_key' => $menu['component_key'],
                    'permission_code' => $menu['permission_code'],
                    'menu_type' => 'menu',
                    'sort_order' => $menu['sort_order'],
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                )
            );
        }

        $this->seedHomeDefaults($database);
        $this->seedForumDefaults($database);
        $this->seedIssueDefaults($database);
        $this->deactivateLegacyCustomerServicePermissions($database);

        if (isset($roleIds['super_admin'])) {
            $this->grantRoleAccess($database, $roleIds['super_admin'], array_values($permissionIds), array_values($menuIds));
        }

        if (isset($roleIds['site_manager'])) {
            $siteManagerPermissionCodes = array(
                'dashboard.view',
                'users.view',
                'users.manage',
                'posts.view',
                'posts.manage',
                'sections.view',
                'sections.manage',
                'categories.view',
                'categories.manage',
                'comments.view',
                'comments.manage',
                'interactions.view',
                'interactions.manage',
                'reports.view',
                'reports.manage',
                'audits.view',
                'audits.manage',
                'home.view',
                'home.manage',
                'draws.view',
                'draws.manage',
                'issues.view',
                'issues.manage',
                'login_logs.view',
                'operation_logs.view',
                'exceptions.view',
                'customer_service.view',
                'customer_service.reply',
                'customer_service.manage',
                'security.view',
                'security.manage',
                'settings.view',
                'settings.manage',
                'install.view',
            );
            $siteManagerMenuCodes = array('dashboard', 'users', 'posts', 'sections', 'categories', 'comments', 'interactions', 'reports', 'audits', 'home', 'draws', 'issues', 'login_logs', 'operation_logs', 'exceptions', 'support', 'security', 'settings', 'install');
            $siteManagerPermissionIds = array();
            $siteManagerMenuIds = array();

            foreach ($siteManagerPermissionCodes as $permissionCode) {
                if (isset($permissionIds[$permissionCode])) {
                    $siteManagerPermissionIds[] = $permissionIds[$permissionCode];
                }
            }

            foreach ($siteManagerMenuCodes as $menuCode) {
                if (isset($menuIds[$menuCode])) {
                    $siteManagerMenuIds[] = $menuIds[$menuCode];
                }
            }

            $this->grantRoleAccess($database, $roleIds['site_manager'], $siteManagerPermissionIds, $siteManagerMenuIds);
        }

        $this->flushAdminMenuItemsCache();
    }

    protected function grantRoleAccess(Database $database, $roleId, array $permissionIds, array $menuIds)
    {
        foreach ($permissionIds as $permissionId) {
            $exists = $database->fetch(
                'SELECT id FROM admin_role_permissions WHERE role_id = :role_id AND permission_id = :permission_id LIMIT 1',
                array(
                    'role_id' => $roleId,
                    'permission_id' => (int) $permissionId,
                )
            );

            if (!$exists) {
                $database->execute(
                    'INSERT INTO admin_role_permissions (role_id, permission_id, created_at) VALUES (:role_id, :permission_id, :created_at)',
                    array(
                        'role_id' => $roleId,
                        'permission_id' => (int) $permissionId,
                        'created_at' => $this->now(),
                    )
                );
            }
        }

        foreach ($menuIds as $menuId) {
            $exists = $database->fetch(
                'SELECT id FROM admin_role_menus WHERE role_id = :role_id AND menu_id = :menu_id LIMIT 1',
                array(
                    'role_id' => $roleId,
                    'menu_id' => (int) $menuId,
                )
            );

            if (!$exists) {
                $database->execute(
                    'INSERT INTO admin_role_menus (role_id, menu_id, created_at) VALUES (:role_id, :menu_id, :created_at)',
                    array(
                        'role_id' => $roleId,
                        'menu_id' => (int) $menuId,
                        'created_at' => $this->now(),
                    )
                );
            }
        }
    }

    protected function flushAdminMenuItemsCache()
    {
        $this->adminMenuItemsCache = array();
        $this->adminPermissionCodesCache = array();
        $this->adminByIdRequestCache = array();
        $this->adminListRequestCache = null;
        $this->roleListRequestCache = null;
        $this->permissionListRequestCache = null;
        $this->app->cache()->put(self::ADMIN_MENU_ITEMS_CACHE_VERSION_KEY, (string) microtime(true));
    }

    protected function seedHomeDefaults(Database $database)
    {
        if ($this->tableExists($database, 'settings')) {
            $now = $this->now();

            foreach ($this->defaultHomeSettings() as $settingKey => $settingValue) {
                $exists = $database->fetch('SELECT id FROM settings WHERE setting_key = :setting_key LIMIT 1', array(
                    'setting_key' => $settingKey,
                ));

                if ($exists) {
                    continue;
                }

                $database->execute(
                    'INSERT INTO settings (setting_key, setting_group, setting_value, is_public, created_at, updated_at)
                     VALUES (:setting_key, :setting_group, :setting_value, :is_public, :created_at, :updated_at)',
                    array(
                        'setting_key' => $settingKey,
                        'setting_group' => 'home',
                        'setting_value' => (string) $settingValue,
                        'is_public' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    )
                );
            }
        }

        if ($this->tableExists($database, 'notices')) {
            $noticeRow = $database->fetch('SELECT id FROM notices LIMIT 1');
            if (!$noticeRow) {
                $now = $this->now();
                $database->execute(
                    'INSERT INTO notices (region, title, content, notice_type, link_url, sort_order, status, start_at, end_at, created_at, updated_at)
                     VALUES (:region, :title, :content, :notice_type, :link_url, :sort_order, :status, :start_at, :end_at, :created_at, :updated_at)',
                    array(
                        'region' => 'all',
                        'title' => '首页滚动公告',
                        'content' => (string) $this->defaultHomeSettings()['home.marquee_text'],
                        'notice_type' => 'scroll',
                        'link_url' => '',
                        'sort_order' => 10,
                        'status' => 1,
                        'start_at' => null,
                        'end_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    )
                );
            }
        }

        if ($this->tableExists($database, 'home_banners')) {
            foreach ($this->defaultHomeBannerSeeds() as $seed) {
                $exists = $database->fetch(
                    'SELECT id FROM home_banners WHERE region = :region AND sort_order = :sort_order LIMIT 1',
                    array(
                        'region' => $seed['region'],
                        'sort_order' => $seed['sort_order'],
                    )
                );
                if ($exists) {
                    continue;
                }

                $database->execute(
                    'INSERT INTO home_banners (region, title, image_url, link_url, open_type, sort_order, status, start_at, end_at, created_at, updated_at)
                     VALUES (:region, :title, :image_url, :link_url, :open_type, :sort_order, :status, :start_at, :end_at, :created_at, :updated_at)',
                    $seed + array(
                        'created_at' => $this->now(),
                        'updated_at' => $this->now(),
                    )
                );
            }
        }

        if ($this->tableExists($database, 'home_nav_entries')) {
            foreach ($this->defaultHomeTopEntrySeeds() as $seed) {
                $exists = $database->fetch(
                    'SELECT id FROM home_nav_entries WHERE region = :region AND sort_order = :sort_order LIMIT 1',
                    array(
                        'region' => $seed['region'],
                        'sort_order' => $seed['sort_order'],
                    )
                );
                if ($exists) {
                    continue;
                }

                $database->execute(
                    'INSERT INTO home_nav_entries (region, title, icon, link_url, target, sort_order, status, created_at, updated_at)
                     VALUES (:region, :title, :icon, :link_url, :target, :sort_order, :status, :created_at, :updated_at)',
                    $seed + array(
                        'created_at' => $this->now(),
                        'updated_at' => $this->now(),
                    )
                );
            }
        }

        if ($this->tableExists($database, 'recommend_positions')) {
            foreach ($this->defaultHomeCardSeeds() as $seed) {
                $exists = $database->fetch(
                    'SELECT id FROM recommend_positions WHERE region = :region AND position_code = :position_code LIMIT 1',
                    array(
                        'region' => $seed['region'],
                        'position_code' => $seed['position_code'],
                    )
                );
                if ($exists) {
                    continue;
                }

                $database->execute(
                    'INSERT INTO recommend_positions (position_code, target_type, target_id, region, title_override, sort_order, status, start_at, end_at, created_at, updated_at)
                     VALUES (:position_code, :target_type, :target_id, :region, :title_override, :sort_order, :status, :start_at, :end_at, :created_at, :updated_at)',
                    $seed + array(
                        'created_at' => $this->now(),
                        'updated_at' => $this->now(),
                    )
                );
            }
        }

        if ($this->tableExists($database, 'ad_slots')) {
            foreach ($this->defaultHomeAdSlotSeeds() as $seed) {
                $exists = $database->fetch(
                    'SELECT id FROM ad_slots WHERE region = :region AND slot_code = :slot_code LIMIT 1',
                    array(
                        'region' => $seed['region'],
                        'slot_code' => $seed['slot_code'],
                    )
                );
                if ($exists) {
                    continue;
                }

                $database->execute(
                    'INSERT INTO ad_slots (slot_code, slot_name, region, page_key, title, image_url, link_url, sort_order, status, created_at, updated_at)
                     VALUES (:slot_code, :slot_name, :region, :page_key, :title, :image_url, :link_url, :sort_order, :status, :created_at, :updated_at)',
                    $seed + array(
                        'created_at' => $this->now(),
                        'updated_at' => $this->now(),
                    )
                );
            }
        }

        if ($this->tableExists($database, 'home_module_configs')) {
            foreach ($this->defaultHomeModuleSeeds() as $moduleKey => $seed) {
                $exists = $database->fetch(
                    'SELECT id FROM home_module_configs WHERE region = :region AND module_key = :module_key LIMIT 1',
                    array(
                        'region' => $seed['region'],
                        'module_key' => $moduleKey,
                    )
                );
                if ($exists) {
                    continue;
                }

                $database->execute(
                    'INSERT INTO home_module_configs (module_key, module_name, region, is_enabled, sort_order, config_json, updated_by, created_at, updated_at)
                     VALUES (:module_key, :module_name, :region, :is_enabled, :sort_order, :config_json, :updated_by, :created_at, :updated_at)',
                    array(
                        'module_key' => $moduleKey,
                        'module_name' => $seed['module_name'],
                        'region' => $seed['region'],
                        'is_enabled' => $seed['is_enabled'],
                        'sort_order' => $seed['sort_order'],
                        'config_json' => null,
                        'updated_by' => 0,
                        'created_at' => $this->now(),
                        'updated_at' => $this->now(),
                    )
                );
            }
        }
    }

    protected function seedForumDefaults(Database $database)
    {
        if (!$this->tableExists($database, 'forum_sections') || !$this->tableExists($database, 'forum_categories')) {
            return;
        }

        $now = $this->now();
        $sectionSeeds = array(
            array(
                'region' => 'macau',
                'name' => '澳门论坛版块',
                'code' => 'macau_forum',
                'description' => '澳门社区交流、发帖与内容审核入口',
                'icon' => '',
                'sort_order' => 10,
                'status' => 1,
                'post_rule' => '默认审核通过后展示，可在后台继续调整。',
            ),
            array(
                'region' => 'hongkong',
                'name' => '香港论坛版块',
                'code' => 'hongkong_forum',
                'description' => '香港社区交流、发帖与内容审核入口',
                'icon' => '',
                'sort_order' => 20,
                'status' => 1,
                'post_rule' => '默认审核通过后展示，可在后台继续调整。',
            ),
        );

        $sectionIds = array();
        foreach ($sectionSeeds as $sectionSeed) {
            $sectionIds[$sectionSeed['code']] = $database->insertGetId(
                'INSERT INTO forum_sections (region, name, code, description, icon, sort_order, status, post_rule, created_at, updated_at)
                 VALUES (:region, :name, :code, :description, :icon, :sort_order, :status, :post_rule, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    region = VALUES(region),
                    name = VALUES(name),
                    description = VALUES(description),
                    icon = VALUES(icon),
                    sort_order = VALUES(sort_order),
                    status = VALUES(status),
                    post_rule = VALUES(post_rule),
                    updated_at = VALUES(updated_at),
                    id = LAST_INSERT_ID(id)',
                array(
                    'region' => $sectionSeed['region'],
                    'name' => $sectionSeed['name'],
                    'code' => $sectionSeed['code'],
                    'description' => $sectionSeed['description'],
                    'icon' => $sectionSeed['icon'],
                    'sort_order' => $sectionSeed['sort_order'],
                    'status' => $sectionSeed['status'],
                    'post_rule' => $sectionSeed['post_rule'],
                    'created_at' => $now,
                    'updated_at' => $now,
                )
            );
        }

        $categorySeeds = array(
            array(
                'section_code' => 'macau_forum',
                'region' => 'macau',
                'name' => '澳门综合交流',
                'code' => 'macau_general',
                'description' => '澳门分区默认帖子分类',
                'sort_order' => 10,
                'status' => 1,
            ),
            array(
                'section_code' => 'hongkong_forum',
                'region' => 'hongkong',
                'name' => '香港综合交流',
                'code' => 'hongkong_general',
                'description' => '香港分区默认帖子分类',
                'sort_order' => 20,
                'status' => 1,
            ),
        );

        $categoryIds = array();
        foreach ($categorySeeds as $categorySeed) {
            if (!isset($sectionIds[$categorySeed['section_code']])) {
                continue;
            }

            $categoryIds[$categorySeed['code']] = $database->insertGetId(
                'INSERT INTO forum_categories (section_id, region, name, code, description, sort_order, status, created_at, updated_at)
                 VALUES (:section_id, :region, :name, :code, :description, :sort_order, :status, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    section_id = VALUES(section_id),
                    region = VALUES(region),
                    name = VALUES(name),
                    description = VALUES(description),
                    sort_order = VALUES(sort_order),
                    status = VALUES(status),
                    updated_at = VALUES(updated_at),
                    id = LAST_INSERT_ID(id)',
                array(
                    'section_id' => (int) $sectionIds[$categorySeed['section_code']],
                    'region' => $categorySeed['region'],
                    'name' => $categorySeed['name'],
                    'code' => $categorySeed['code'],
                    'description' => $categorySeed['description'],
                    'sort_order' => $categorySeed['sort_order'],
                    'status' => $categorySeed['status'],
                    'created_at' => $now,
                    'updated_at' => $now,
                )
            );
        }

        if ($this->tableExists($database, 'posts') && $this->columnExists($database, 'posts', 'section_id') && $this->columnExists($database, 'posts', 'category_id')) {
            if (isset($sectionIds['macau_forum'], $categoryIds['macau_general'])) {
                $database->execute(
                    'UPDATE posts
                     SET section_id = :section_id, category_id = :category_id
                     WHERE region = :region AND (section_id IS NULL OR section_id = 0)',
                    array(
                        'section_id' => (int) $sectionIds['macau_forum'],
                        'category_id' => (int) $categoryIds['macau_general'],
                        'region' => 'macau',
                    )
                );
            }

            if (isset($sectionIds['hongkong_forum'], $categoryIds['hongkong_general'])) {
                $database->execute(
                    'UPDATE posts
                     SET section_id = :section_id, category_id = :category_id
                     WHERE region = :region AND (section_id IS NULL OR section_id = 0)',
                    array(
                        'section_id' => (int) $sectionIds['hongkong_forum'],
                        'category_id' => (int) $categoryIds['hongkong_general'],
                        'region' => 'hongkong',
                    )
                );
            }
        }
    }

    protected function seedIssueDefaults(Database $database)
    {
        if (!$this->tableExists($database, 'lottery_issues')) {
            return;
        }

        foreach (array('macau', 'hongkong') as $region) {
            $exists = $database->fetch(
                'SELECT id FROM lottery_issues WHERE region = :region LIMIT 1',
                array('region' => $region)
            );
            if ($exists) {
                continue;
            }

            $seed = $this->defaultIssueSeed($region);
            if (!$seed) {
                continue;
            }

            $database->execute(
                'INSERT INTO lottery_issues (region, issue_no, planned_open_at, actual_open_at, status, is_current, remark, created_at, updated_at)
                 VALUES (:region, :issue_no, :planned_open_at, :actual_open_at, :status, :is_current, :remark, :created_at, :updated_at)
                 ON DUPLICATE KEY UPDATE
                    planned_open_at = VALUES(planned_open_at),
                    actual_open_at = VALUES(actual_open_at),
                    status = VALUES(status),
                    is_current = VALUES(is_current),
                    remark = VALUES(remark),
                    updated_at = VALUES(updated_at)',
                array(
                    'region' => $region,
                    'issue_no' => $seed['issue_no'],
                    'planned_open_at' => $seed['planned_open_at'],
                    'actual_open_at' => null,
                    'status' => 'pending',
                    'is_current' => 1,
                    'remark' => '系统根据首页当前开奖时间自动初始化',
                    'created_at' => $this->now(),
                    'updated_at' => $this->now(),
                )
            );
        }
    }

    protected function defaultIssueSeed($region)
    {
        $latestDraw = $this->app->prediction()->latestHomepageDraw((string) $region);
        if (!is_array($latestDraw)) {
            return null;
        }

        $plannedOpenAt = trim((string) ($latestDraw['next_open_time'] ?? ''));
        $sourceIssueNo = trim((string) ($latestDraw['issue_no'] ?? ''));
        if ($plannedOpenAt === '' || $sourceIssueNo === '') {
            return null;
        }

        return array(
            'issue_no' => $this->incrementIssueNo($sourceIssueNo),
            'planned_open_at' => $plannedOpenAt,
        );
    }

    protected function incrementIssueNo($issueNo)
    {
        $issueNo = trim((string) $issueNo);
        if ($issueNo === '') {
            return '';
        }

        if (preg_match('/^(.*?)(\d+)(\D*)$/u', $issueNo, $matches)) {
            $prefix = (string) $matches[1];
            $numberPart = (string) $matches[2];
            $suffix = (string) $matches[3];
            $nextNumber = (string) ((int) $numberPart + 1);
            $nextNumber = str_pad($nextNumber, strlen($numberPart), '0', STR_PAD_LEFT);

            return $prefix . $nextNumber . $suffix;
        }

        return $issueNo;
    }

    protected function ensurePrimaryAdmin(Database $database, array $seed = array())
    {
        $existing = $database->fetch('SELECT id FROM admin_users WHERE deleted_at IS NULL ORDER BY is_super DESC, id ASC LIMIT 1');
        if ($existing) {
            return $this->findByIdFrom($database, (int) $existing['id']);
        }

        $superRole = $database->fetch('SELECT id FROM admin_roles WHERE code = :code LIMIT 1', array('code' => 'super_admin'));
        if (!$superRole) {
            throw new RuntimeException('后台超级管理员角色未初始化。');
        }

        $legacyAdmin = null;
        if ($this->tableExists($database, 'users') && $this->tableExists($database, 'roles')) {
            $legacyAdmin = $database->fetch(
                "SELECT users.username, users.password_hash, users.email
                 FROM users
                 INNER JOIN roles ON roles.id = users.role_id
                 WHERE roles.role_key IN ('super_admin', 'admin')
                 ORDER BY users.id ASC
                 LIMIT 1"
            );
        }

        $username = isset($seed['username']) && trim((string) $seed['username']) !== ''
            ? trim((string) $seed['username'])
            : ($legacyAdmin ? (string) $legacyAdmin['username'] : 'admin');
        $passwordHash = isset($seed['password_hash']) && (string) $seed['password_hash'] !== ''
            ? (string) $seed['password_hash']
            : ($legacyAdmin ? (string) $legacyAdmin['password_hash'] : password_hash('admin888', PASSWORD_DEFAULT));
        $email = isset($seed['email']) ? (string) $seed['email'] : ($legacyAdmin && isset($legacyAdmin['email']) ? (string) $legacyAdmin['email'] : '');
        $realName = isset($seed['real_name']) && trim((string) $seed['real_name']) !== '' ? trim((string) $seed['real_name']) : '超级管理员';

        $adminId = $database->insertGetId(
            'INSERT INTO admin_users (role_id, username, password_hash, real_name, nickname, mobile, email, status, is_super, login_fail_count, remark, created_at, updated_at)
             VALUES (:role_id, :username, :password_hash, :real_name, :nickname, :mobile, :email, 1, 1, 0, :remark, :created_at, :updated_at)',
            array(
                'role_id' => (int) $superRole['id'],
                'username' => $username,
                'password_hash' => $passwordHash,
                'real_name' => $realName,
                'nickname' => $username,
                'mobile' => '',
                'email' => $email,
                'remark' => '系统初始化超级管理员',
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            )
        );

        return $this->findByIdFrom($database, $adminId);
    }

    protected function recordLoginAttempt($adminId, $username, $status, $reason)
    {
        if (!$this->tableExists($this->db(), 'admin_login_logs')) {
            return;
        }

        $this->db()->execute(
            'INSERT INTO admin_login_logs (admin_id, username, login_type, status, ip, area, user_agent, device, fail_reason, login_at)
             VALUES (:admin_id, :username, :login_type, :status, :ip, :area, :user_agent, :device, :fail_reason, :login_at)',
            array(
                'admin_id' => $adminId,
                'username' => $username,
                'login_type' => 'password',
                'status' => $status,
                'ip' => Security::ipAddress(),
                'area' => Security::provinceFromIp(Security::ipAddress()),
                'user_agent' => Security::userAgent(),
                'device' => stripos(Security::userAgent(), 'mobile') !== false ? 'mobile' : 'desktop',
                'fail_reason' => $reason,
                'login_at' => $this->now(),
            )
        );
    }

    protected function recordOperation($adminId, $module, $action, $targetType, $targetId, $summary)
    {
        if (!$this->tableExists($this->db(), 'admin_operation_logs')) {
            return;
        }

        $summary = trim((string) $summary);
        if ($summary === '') {
            $summary = '-';
        }

        if (function_exists('mb_substr')) {
            if (mb_strlen($summary, 'UTF-8') > 255) {
                $summary = rtrim((string) mb_substr($summary, 0, 252, 'UTF-8')) . '...';
            }
        } elseif (strlen($summary) > 255) {
            $summary = rtrim((string) substr($summary, 0, 252)) . '...';
        }

        $requestPath = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $requestPath = function_exists('mb_substr')
            ? mb_substr($requestPath, 0, 150, 'UTF-8')
            : substr($requestPath, 0, 150);
        $requestData = $this->operationLogRequestData($_POST);

        $this->db()->execute(
            'INSERT INTO admin_operation_logs (admin_id, module, action, target_type, target_id, summary, request_method, request_path, request_data, ip, created_at)
             VALUES (:admin_id, :module, :action, :target_type, :target_id, :summary, :request_method, :request_path, :request_data, :ip, :created_at)',
            array(
                'admin_id' => $adminId,
                'module' => $module,
                'action' => $action,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'summary' => $summary,
                'request_method' => isset($_SERVER['REQUEST_METHOD']) ? (string) $_SERVER['REQUEST_METHOD'] : 'CLI',
                'request_path' => $requestPath,
                'request_data' => $requestData,
                'ip' => Security::ipAddress(),
                'created_at' => $this->now(),
            )
        );
    }

    protected function operationLogRequestData(array $payload): string
    {
        $snapshot = $this->compactOperationLogPayload($payload);
        $json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($json) && $json !== '' ? $json : '{}';
    }

    protected function compactOperationLogPayload(array $payload): array
    {
        $snapshot = array();

        foreach ($payload as $key => $value) {
            $keyText = is_int($key) ? (string) $key : (string) $key;

            if (is_array($value)) {
                $snapshot[$key] = $this->compactOperationLogPayload($value);
                continue;
            }

            if ($keyText === 'content_html') {
                $snapshot[$key] = '[content_html omitted, bytes=' . strlen((string) $value) . ']';
                continue;
            }

            $snapshot[$key] = $value;
        }

        return $snapshot;
    }

    protected function recordInstall(Database $database, array $payload)
    {
        $installCode = 'install-' . date('YmdHis');
        $database->execute(
            'INSERT INTO install_records (install_code, app_version, site_name, site_domain, db_host, db_port, db_name, db_prefix, installer_ip, status, error_message, installed_at)
             VALUES (:install_code, :app_version, :site_name, :site_domain, :db_host, :db_port, :db_name, :db_prefix, :installer_ip, :status, :error_message, :installed_at)',
            array(
                'install_code' => $installCode,
                'app_version' => self::INSTALL_DEFAULT_VERSION,
                'site_name' => $payload['site_name'],
                'site_domain' => $payload['site_domain'],
                'db_host' => $payload['db_host'],
                'db_port' => $payload['db_port'],
                'db_name' => $payload['db_name'],
                'db_prefix' => $payload['db_prefix'],
                'installer_ip' => Security::ipAddress(),
                'status' => $payload['status'],
                'error_message' => '',
                'installed_at' => $this->now(),
            )
        );
    }

    protected function recordInitGroup(Database $database, $group, $tableName, $count)
    {
        $latestRecord = $database->fetch('SELECT install_code FROM install_records ORDER BY id DESC LIMIT 1');
        if (!$latestRecord) {
            return;
        }

        $database->execute(
            'INSERT INTO init_data_records (install_code, data_group, table_name, record_count, status, remark, created_at)
             VALUES (:install_code, :data_group, :table_name, :record_count, :status, :remark, :created_at)',
            array(
                'install_code' => $latestRecord['install_code'],
                'data_group' => $group,
                'table_name' => $tableName,
                'record_count' => $count,
                'status' => 'success',
                'remark' => '后台初始化数据导入',
                'created_at' => $this->now(),
            )
        );
    }

    protected function tableExists(Database $database, $tableName)
    {
        $tableName = (string) $tableName;
        if (!empty($this->tableExistsCache[$tableName])) {
            return true;
        }

        $row = $database->fetch(
            'SELECT COUNT(*) AS total_count
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
            array('table_name' => $tableName)
        );

        $exists = $row && (int) $row['total_count'] > 0;
        if ($exists) {
            $this->tableExistsCache[$tableName] = true;
        }

        return $exists;
    }

    protected function columnExists(Database $database, $tableName, $columnName)
    {
        $cacheKey = (string) $tableName . '.' . (string) $columnName;
        if (!empty($this->columnExistsCache[$cacheKey])) {
            return true;
        }

        $row = $database->fetch(
            'SELECT COUNT(*) AS total_count
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
            array(
                'table_name' => (string) $tableName,
                'column_name' => (string) $columnName,
            )
        );

        $exists = $row && (int) $row['total_count'] > 0;
        if ($exists) {
            $this->columnExistsCache[$cacheKey] = true;
        }

        return $exists;
    }

    protected function paginateAdminQuery($countSql, $listSql, array $params, $pageNo, $perPage)
    {
        $pageNo = max(1, (int) $pageNo);
        $perPage = max(1, min(100, (int) $perPage));
        $totalRow = $this->db()->fetch($countSql, $params);
        $total = $totalRow ? (int) $totalRow['total_count'] : 0;
        $pageCount = max(1, (int) ceil($total / $perPage));
        $pageNo = min($pageNo, $pageCount);
        $offset = ($pageNo - 1) * $perPage;
        $items = $this->db()->fetchAll($listSql . ' LIMIT ' . $perPage . ' OFFSET ' . $offset, $params);

        return array(
            'items' => $items,
            'total' => $total,
            'page_no' => $pageNo,
            'per_page' => $perPage,
            'page_count' => $pageCount,
        );
    }

    protected function emptyPaginatedResult()
    {
        return array(
            'items' => array(),
            'total' => 0,
            'page_no' => 1,
            'per_page' => 20,
            'page_count' => 1,
        );
    }

    protected function deactivateLegacyCustomerServicePermissions(Database $database)
    {
        $database->execute(
            "UPDATE admin_permissions
             SET status = 0, updated_at = :updated_at
             WHERE code IN ('support.view', 'support.reply', 'support.manage')",
            array('updated_at' => $this->now())
        );
    }

    protected function defaultRoles()
    {
        return array(
            array('name' => '超级管理员', 'code' => 'super_admin', 'description' => '拥有后台全部权限', 'data_scope' => 'all', 'sort_order' => 1),
            array('name' => '站点管理员', 'code' => 'site_manager', 'description' => '拥有控制台和基础设置查看能力', 'data_scope' => 'all', 'sort_order' => 2),
        );
    }

    protected function defaultHomeSettings()
    {
        if ($this->defaultHomeSettingsRequestCache !== null) {
            return $this->defaultHomeSettingsRequestCache;
        }

        $this->defaultHomeSettingsRequestCache = array(
            'home.brand_name_main' => 'HONGYUNLIUHE',
            'home.brand_domain' => 'HONGYUN666.COM',
            'home.download_text' => '下载APP',
            'home.download_url' => public_url('service.php') . '?region=macau',
            'home.hero_library_title' => '2027六合彩',
            'home.hero_library_subtitle' => '官方正版资料库',
            'home.hero_main_title' => '鸿运当头 · 一击必中',
            'home.hero_main_subtitle' => '每日更新 · 精准生肖 · 独家特码',
            'home.marquee_text' => '2026年六合彩全面开启！061期开奖号已出：6 17 08 25 02 37 + 29（虎）| 新手必看：2026生肖属性全表已上线 | 欢迎加入VIP群，独家资料每日推送！ | 本站资料仅供参考，理性娱乐！',
            'home.vip_title' => '🔥 限时VIP特惠 🔥',
            'home.vip_subtitle' => '加入鸿运VIP · 每日精准三码 · 仅需88元/月',
            'home.vip_button_text' => '立即开通VIP',
            'home.vip_button_message' => '可在后台配置跳转支付页面',
        );

        return $this->defaultHomeSettingsRequestCache;
    }

    protected function defaultHomeBannerSeeds()
    {
        return array(
            array(
                'region' => 'all',
                'title' => '首页主视觉',
                'image_url' => 'https://picsum.photos/id/1015/1920/600',
                'link_url' => public_url('service.php') . '?region=macau',
                'open_type' => '_self',
                'sort_order' => 10,
                'status' => 1,
                'start_at' => null,
                'end_at' => null,
            ),
        );
    }

    protected function defaultHomeTopEntrySeeds()
    {
        return array(
            array(
                'region' => 'all',
                'title' => '下载APP',
                'icon' => 'fa-solid fa-download',
                'link_url' => public_url('service.php') . '?region=macau',
                'target' => '_self',
                'sort_order' => 10,
                'status' => 1,
            ),
        );
    }

    protected function defaultHomeCardSeeds()
    {
        return array(
            'home_data_card_1' => array('position_code' => 'home_data_card_1', 'target_type' => 'custom_html', 'target_id' => 0, 'region' => 'all', 'title_override' => '最新特码诗：鸿运当头金光闪', 'sort_order' => 10, 'status' => 1, 'start_at' => null, 'end_at' => null),
            'home_data_card_2' => array('position_code' => 'home_data_card_2', 'target_type' => 'custom_html', 'target_id' => 0, 'region' => 'all', 'title_override' => '七肖精选：鼠牛虎兔龙马', 'sort_order' => 20, 'status' => 1, 'start_at' => null, 'end_at' => null),
            'home_data_card_3' => array('position_code' => 'home_data_card_3', 'target_type' => 'custom_html', 'target_id' => 0, 'region' => 'all', 'title_override' => '四肖八码实时更新', 'sort_order' => 30, 'status' => 1, 'start_at' => null, 'end_at' => null),
            'home_data_card_4' => array('position_code' => 'home_data_card_4', 'target_type' => 'custom_html', 'target_id' => 0, 'region' => 'all', 'title_override' => '2026全年波色', 'sort_order' => 40, 'status' => 1, 'start_at' => null, 'end_at' => null),
        );
    }

    protected function defaultHomeAdSlotSeeds()
    {
        return array(
            'home_ad_primary_1' => array('slot_code' => 'home_ad_primary_1', 'slot_name' => '广告一区-1', 'region' => 'all', 'page_key' => 'home', 'title' => '澳门多宝【三肖二连】强力推荐', 'image_url' => '', 'link_url' => '', 'sort_order' => 10, 'status' => 1),
            'home_ad_primary_2' => array('slot_code' => 'home_ad_primary_2', 'slot_name' => '广告一区-2', 'region' => 'all', 'page_key' => 'home', 'title' => '天降横财【①头②尾】一击命中', 'image_url' => '', 'link_url' => '', 'sort_order' => 20, 'status' => 1),
            'home_ad_primary_3' => array('slot_code' => 'home_ad_primary_3', 'slot_name' => '广告一区-3', 'region' => 'all', 'page_key' => 'home', 'title' => '六合雄霸【天地人肖】全网独家', 'image_url' => '', 'link_url' => '', 'sort_order' => 30, 'status' => 1),
            'home_ad_primary_4' => array('slot_code' => 'home_ad_primary_4', 'slot_name' => '广告一区-4', 'region' => 'all', 'page_key' => 'home', 'title' => '香港六合【五白三红】全网独家', 'image_url' => '', 'link_url' => '', 'sort_order' => 40, 'status' => 1),
            'home_ad_primary_5' => array('slot_code' => 'home_ad_primary_5', 'slot_name' => '广告一区-5', 'region' => 'all', 'page_key' => 'home', 'title' => '港彩霸王【蓝波1码】实力验证', 'image_url' => '', 'link_url' => '', 'sort_order' => 50, 'status' => 1),
            'home_ad_primary_6' => array('slot_code' => 'home_ad_primary_6', 'slot_name' => '广告一区-6', 'region' => 'all', 'page_key' => 'home', 'title' => '智多星【内幕一肖】期期公开', 'image_url' => '', 'link_url' => '', 'sort_order' => 60, 'status' => 1),
            'home_ad_secondary_1' => array('slot_code' => 'home_ad_secondary_1', 'slot_name' => '广告二区-1', 'region' => 'all', 'page_key' => 'home', 'title' => '六合雄霸【天地人肖】全网独家', 'image_url' => '', 'link_url' => '', 'sort_order' => 70, 'status' => 1),
            'home_ad_secondary_2' => array('slot_code' => 'home_ad_secondary_2', 'slot_name' => '广告二区-2', 'region' => 'all', 'page_key' => 'home', 'title' => '澳门多宝【三肖二连】强力推荐', 'image_url' => '', 'link_url' => '', 'sort_order' => 80, 'status' => 1),
            'home_ad_secondary_3' => array('slot_code' => 'home_ad_secondary_3', 'slot_name' => '广告二区-3', 'region' => 'all', 'page_key' => 'home', 'title' => '天降横财【①头②尾】一击命中', 'image_url' => '', 'link_url' => '', 'sort_order' => 90, 'status' => 1),
            'home_ad_secondary_4' => array('slot_code' => 'home_ad_secondary_4', 'slot_name' => '广告二区-4', 'region' => 'all', 'page_key' => 'home', 'title' => '香港六合【五白三红】全网独家', 'image_url' => '', 'link_url' => '', 'sort_order' => 100, 'status' => 1),
            'home_ad_secondary_5' => array('slot_code' => 'home_ad_secondary_5', 'slot_name' => '广告二区-5', 'region' => 'all', 'page_key' => 'home', 'title' => '港彩霸王【蓝波1码】实力验证', 'image_url' => '', 'link_url' => '', 'sort_order' => 110, 'status' => 1),
            'home_ad_secondary_6' => array('slot_code' => 'home_ad_secondary_6', 'slot_name' => '广告二区-6', 'region' => 'all', 'page_key' => 'home', 'title' => '智多星【内幕一肖】期期公开', 'image_url' => '', 'link_url' => '', 'sort_order' => 120, 'status' => 1),
            'home_ad_tertiary_1' => array('slot_code' => 'home_ad_tertiary_1', 'slot_name' => '广告三区-1', 'region' => 'all', 'page_key' => 'home', 'title' => '六合雄霸【天地人肖】全网独家', 'image_url' => '', 'link_url' => '', 'sort_order' => 130, 'status' => 1),
            'home_ad_tertiary_2' => array('slot_code' => 'home_ad_tertiary_2', 'slot_name' => '广告三区-2', 'region' => 'all', 'page_key' => 'home', 'title' => '澳门多宝【三肖二连】强力推荐', 'image_url' => '', 'link_url' => '', 'sort_order' => 140, 'status' => 1),
            'home_ad_tertiary_3' => array('slot_code' => 'home_ad_tertiary_3', 'slot_name' => '广告三区-3', 'region' => 'all', 'page_key' => 'home', 'title' => '天降横财【①头②尾】一击命中', 'image_url' => '', 'link_url' => '', 'sort_order' => 150, 'status' => 1),
            'home_ad_tertiary_4' => array('slot_code' => 'home_ad_tertiary_4', 'slot_name' => '广告三区-4', 'region' => 'all', 'page_key' => 'home', 'title' => '香港六合【五白三红】全网独家', 'image_url' => '', 'link_url' => '', 'sort_order' => 160, 'status' => 1),
            'home_ad_tertiary_5' => array('slot_code' => 'home_ad_tertiary_5', 'slot_name' => '广告三区-5', 'region' => 'all', 'page_key' => 'home', 'title' => '港彩霸王【蓝波1码】实力验证', 'image_url' => '', 'link_url' => '', 'sort_order' => 170, 'status' => 1),
            'home_ad_tertiary_6' => array('slot_code' => 'home_ad_tertiary_6', 'slot_name' => '广告三区-6', 'region' => 'all', 'page_key' => 'home', 'title' => '智多星【内幕一肖】期期公开', 'image_url' => '', 'link_url' => '', 'sort_order' => 180, 'status' => 1),
        );
    }

    protected function defaultHomeModuleSeeds()
    {
        return array(
            'home_marquee' => array('module_name' => '滚动公告', 'region' => 'all', 'is_enabled' => 1, 'sort_order' => 10),
            'home_calendar' => array('module_name' => '首页日历', 'region' => 'all', 'is_enabled' => 1, 'sort_order' => 20),
            'home_data_cards' => array('module_name' => '资料卡片区', 'region' => 'all', 'is_enabled' => 1, 'sort_order' => 30),
            'home_primary_ads' => array('module_name' => '广告一区', 'region' => 'all', 'is_enabled' => 1, 'sort_order' => 40),
        );
    }

    protected function defaultPermissions()
    {
        return array(
            array('name' => '查看仪表盘', 'code' => 'dashboard.view', 'type' => 'page', 'module' => 'dashboard', 'route_path' => 'dashboard', 'method' => 'GET', 'sort_order' => 10),
            array('name' => '查看管理员列表', 'code' => 'admins.view', 'type' => 'page', 'module' => 'admins', 'route_path' => 'admins', 'method' => 'GET', 'sort_order' => 20),
            array('name' => '管理管理员', 'code' => 'admins.manage', 'type' => 'button', 'module' => 'admins', 'route_path' => 'admin.admin.save', 'method' => 'POST', 'sort_order' => 21),
            array('name' => '查看角色列表', 'code' => 'roles.view', 'type' => 'page', 'module' => 'roles', 'route_path' => 'roles', 'method' => 'GET', 'sort_order' => 30),
            array('name' => '管理角色', 'code' => 'roles.manage', 'type' => 'button', 'module' => 'roles', 'route_path' => 'admin.role.save', 'method' => 'POST', 'sort_order' => 31),
            array('name' => '查看系统设置', 'code' => 'settings.view', 'type' => 'page', 'module' => 'settings', 'route_path' => 'settings', 'method' => 'GET', 'sort_order' => 40),
            array('name' => '管理系统设置', 'code' => 'settings.manage', 'type' => 'button', 'module' => 'settings', 'route_path' => 'admin.settings.save', 'method' => 'POST', 'sort_order' => 41),
            array('name' => '查看安装信息', 'code' => 'install.view', 'type' => 'page', 'module' => 'install', 'route_path' => 'install', 'method' => 'GET', 'sort_order' => 50),
            array('name' => '查看用户管理', 'code' => 'users.view', 'type' => 'page', 'module' => 'users', 'route_path' => 'users', 'method' => 'GET', 'sort_order' => 60),
            array('name' => '管理用户', 'code' => 'users.manage', 'type' => 'button', 'module' => 'users', 'route_path' => 'admin.user.save', 'method' => 'POST', 'sort_order' => 61),
            array('name' => '查看帖子管理', 'code' => 'posts.view', 'type' => 'page', 'module' => 'posts', 'route_path' => 'posts', 'method' => 'GET', 'sort_order' => 70),
            array('name' => '管理帖子', 'code' => 'posts.manage', 'type' => 'button', 'module' => 'posts', 'route_path' => 'admin.post.save', 'method' => 'POST', 'sort_order' => 71),
            array('name' => '查看版块管理', 'code' => 'sections.view', 'type' => 'page', 'module' => 'sections', 'route_path' => 'sections', 'method' => 'GET', 'sort_order' => 72),
            array('name' => '管理版块', 'code' => 'sections.manage', 'type' => 'button', 'module' => 'sections', 'route_path' => 'admin.section.save', 'method' => 'POST', 'sort_order' => 73),
            array('name' => '查看分类管理', 'code' => 'categories.view', 'type' => 'page', 'module' => 'categories', 'route_path' => 'categories', 'method' => 'GET', 'sort_order' => 74),
            array('name' => '管理分类', 'code' => 'categories.manage', 'type' => 'button', 'module' => 'categories', 'route_path' => 'admin.category.save', 'method' => 'POST', 'sort_order' => 75),
            array('name' => '查看评论管理', 'code' => 'comments.view', 'type' => 'page', 'module' => 'comments', 'route_path' => 'comments', 'method' => 'GET', 'sort_order' => 76),
            array('name' => '管理评论', 'code' => 'comments.manage', 'type' => 'button', 'module' => 'comments', 'route_path' => 'admin.comment.save', 'method' => 'POST', 'sort_order' => 77),
            array('name' => '查看帖子互动管理', 'code' => 'interactions.view', 'type' => 'page', 'module' => 'interactions', 'route_path' => 'interactions', 'method' => 'GET', 'sort_order' => 77),
            array('name' => '管理帖子互动', 'code' => 'interactions.manage', 'type' => 'button', 'module' => 'interactions', 'route_path' => 'admin.interaction.save', 'method' => 'POST', 'sort_order' => 78),
            array('name' => '查看帖子举报管理', 'code' => 'reports.view', 'type' => 'page', 'module' => 'reports', 'route_path' => 'reports', 'method' => 'GET', 'sort_order' => 79),
            array('name' => '管理帖子举报', 'code' => 'reports.manage', 'type' => 'button', 'module' => 'reports', 'route_path' => 'admin.report.save', 'method' => 'POST', 'sort_order' => 80),
            array('name' => '查看审核管理', 'code' => 'audits.view', 'type' => 'page', 'module' => 'audits', 'route_path' => 'audits', 'method' => 'GET', 'sort_order' => 78),
            array('name' => '管理审核', 'code' => 'audits.manage', 'type' => 'button', 'module' => 'audits', 'route_path' => 'admin.audit.save', 'method' => 'POST', 'sort_order' => 79),
            array('name' => '查看开奖管理', 'code' => 'draws.view', 'type' => 'page', 'module' => 'draws', 'route_path' => 'draws', 'method' => 'GET', 'sort_order' => 80),
            array('name' => '管理开奖', 'code' => 'draws.manage', 'type' => 'button', 'module' => 'draws', 'route_path' => 'admin.draw.save', 'method' => 'POST', 'sort_order' => 81),
        );
    }

    protected function defaultMenus()
    {
        return array(
            array('title' => '仪表盘', 'code' => 'dashboard', 'icon' => '仪', 'route_path' => 'dashboard', 'component_key' => 'dashboard', 'permission_code' => 'dashboard.view', 'sort_order' => 10),
            array('title' => '管理员', 'code' => 'admins', 'icon' => '管', 'route_path' => 'admins', 'component_key' => 'admins', 'permission_code' => 'admins.view', 'sort_order' => 20),
            array('title' => '角色权限', 'code' => 'roles', 'icon' => '角', 'route_path' => 'roles', 'component_key' => 'roles', 'permission_code' => 'roles.view', 'sort_order' => 30),
            array('title' => '系统设置', 'code' => 'settings', 'icon' => '设', 'route_path' => 'settings', 'component_key' => 'settings', 'permission_code' => 'settings.view', 'sort_order' => 40),
            array('title' => '安装信息', 'code' => 'install', 'icon' => '装', 'route_path' => 'install', 'component_key' => 'install', 'permission_code' => 'install.view', 'sort_order' => 50),
            array('title' => '用户管理', 'code' => 'users', 'icon' => '会', 'route_path' => 'users', 'component_key' => 'users', 'permission_code' => 'users.view', 'sort_order' => 60),
            array('title' => '帖子管理', 'code' => 'posts', 'icon' => '帖', 'route_path' => 'posts', 'component_key' => 'posts', 'permission_code' => 'posts.view', 'sort_order' => 70),
            array('title' => '版块管理', 'code' => 'sections', 'icon' => '版', 'route_path' => 'sections', 'component_key' => 'sections', 'permission_code' => 'sections.view', 'sort_order' => 72),
            array('title' => '分类管理', 'code' => 'categories', 'icon' => '类', 'route_path' => 'categories', 'component_key' => 'categories', 'permission_code' => 'categories.view', 'sort_order' => 74),
            array('title' => '评论管理', 'code' => 'comments', 'icon' => '评', 'route_path' => 'comments', 'component_key' => 'comments', 'permission_code' => 'comments.view', 'sort_order' => 76),
            array('title' => '帖子互动', 'code' => 'interactions', 'icon' => '互', 'route_path' => 'interactions', 'component_key' => 'interactions', 'permission_code' => 'interactions.view', 'sort_order' => 77),
            array('title' => '帖子举报', 'code' => 'reports', 'icon' => '报', 'route_path' => 'reports', 'component_key' => 'reports', 'permission_code' => 'reports.view', 'sort_order' => 79),
            array('title' => '审核管理', 'code' => 'audits', 'icon' => '审', 'route_path' => 'audits', 'component_key' => 'audits', 'permission_code' => 'audits.view', 'sort_order' => 78),
            array('title' => '开奖管理', 'code' => 'draws', 'icon' => '开', 'route_path' => 'draws', 'component_key' => 'draws', 'permission_code' => 'draws.view', 'sort_order' => 80),
        );
    }

    protected function phaseOneExtraPermissions()
    {
        return array(
            array('name' => '查看附件管理', 'code' => 'uploads.view', 'type' => 'page', 'module' => 'uploads', 'route_path' => 'uploads', 'method' => 'GET', 'sort_order' => 83),
            array('name' => '管理附件', 'code' => 'uploads.manage', 'type' => 'button', 'module' => 'uploads', 'route_path' => 'admin.upload.save', 'method' => 'POST', 'sort_order' => 84),
            array('name' => '查看首页运营', 'code' => 'home.view', 'type' => 'page', 'module' => 'home', 'route_path' => 'home', 'method' => 'GET', 'sort_order' => 85),
            array('name' => '管理首页运营', 'code' => 'home.manage', 'type' => 'button', 'module' => 'home', 'route_path' => 'admin.home.save', 'method' => 'POST', 'sort_order' => 86),
            array('name' => '查看期数管理', 'code' => 'issues.view', 'type' => 'page', 'module' => 'issues', 'route_path' => 'issues', 'method' => 'GET', 'sort_order' => 87),
            array('name' => '管理期数', 'code' => 'issues.manage', 'type' => 'button', 'module' => 'issues', 'route_path' => 'admin.issue.save', 'method' => 'POST', 'sort_order' => 88),
            array('name' => '查看后台登录日志', 'code' => 'login_logs.view', 'type' => 'page', 'module' => 'login_logs', 'route_path' => 'login_logs', 'method' => 'GET', 'sort_order' => 89),
            array('name' => '查看后台操作日志', 'code' => 'operation_logs.view', 'type' => 'page', 'module' => 'operation_logs', 'route_path' => 'operation_logs', 'method' => 'GET', 'sort_order' => 90),
            array('name' => '查看异常日志', 'code' => 'exceptions.view', 'type' => 'page', 'module' => 'exceptions', 'route_path' => 'exceptions', 'method' => 'GET', 'sort_order' => 91),
            array('name' => '查看在线客服', 'code' => 'customer_service.view', 'type' => 'page', 'module' => 'customer_service', 'route_path' => 'support', 'method' => 'GET', 'sort_order' => 92),
            array('name' => '回复在线客服', 'code' => 'customer_service.reply', 'type' => 'button', 'module' => 'customer_service', 'route_path' => 'customer_service.admin.send', 'method' => 'POST', 'sort_order' => 93),
            array('name' => '管理在线客服', 'code' => 'customer_service.manage', 'type' => 'button', 'module' => 'customer_service', 'route_path' => 'customer_service.admin.close', 'method' => 'POST', 'sort_order' => 94),
            array('name' => '查看安全策略', 'code' => 'security.view', 'type' => 'page', 'module' => 'security', 'route_path' => 'security', 'method' => 'GET', 'sort_order' => 92),
            array('name' => '管理安全策略', 'code' => 'security.manage', 'type' => 'button', 'module' => 'security', 'route_path' => 'admin.security.save', 'method' => 'POST', 'sort_order' => 93),
        );
    }

    protected function phaseOneExtraMenus()
    {
        return array(
            array('title' => '附件管理', 'code' => 'uploads', 'icon' => '附', 'route_path' => 'uploads', 'component_key' => 'uploads', 'permission_code' => 'uploads.view', 'sort_order' => 83),
            array('title' => '首页运营', 'code' => 'home', 'icon' => '首', 'route_path' => 'home', 'component_key' => 'home', 'permission_code' => 'home.view', 'sort_order' => 85),
            array('title' => '期数管理', 'code' => 'issues', 'icon' => '期', 'route_path' => 'issues', 'component_key' => 'issues', 'permission_code' => 'issues.view', 'sort_order' => 90),
            array('title' => '登录日志', 'code' => 'login_logs', 'icon' => '登', 'route_path' => 'login_logs', 'component_key' => 'login_logs', 'permission_code' => 'login_logs.view', 'sort_order' => 95),
            array('title' => '操作日志', 'code' => 'operation_logs', 'icon' => '操', 'route_path' => 'operation_logs', 'component_key' => 'operation_logs', 'permission_code' => 'operation_logs.view', 'sort_order' => 100),
            array('title' => '异常日志', 'code' => 'exceptions', 'icon' => '异', 'route_path' => 'exceptions', 'component_key' => 'exceptions', 'permission_code' => 'exceptions.view', 'sort_order' => 105),
            array('title' => '在线客服', 'code' => 'support', 'icon' => '客', 'route_path' => 'support', 'component_key' => 'customer_service', 'permission_code' => 'customer_service.view', 'sort_order' => 108),
            array('title' => '安全策略', 'code' => 'security', 'icon' => '安', 'route_path' => 'security', 'component_key' => 'security', 'permission_code' => 'security.view', 'sort_order' => 110),
        );
    }

    protected function normalizeDateTimeInput($value)
    {
        $value = trim((string) $value);

        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value)) {
            return str_replace('T', ' ', $value) . ':00';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $value)) {
            return $value . ':00';
        }

        return $value;
    }
}
