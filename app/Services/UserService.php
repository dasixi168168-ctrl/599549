<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Security;
use App\Core\Validator;
use RuntimeException;
use Throwable;

class UserService extends Service
{
    protected function normalizeRecoveryAnswer($value)
    {
        $answer = trim((string) $value);
        if ($answer === '') {
            return '';
        }

        if (function_exists('mb_strtolower')) {
            return mb_strtolower($answer, 'UTF-8');
        }

        return strtolower($answer);
    }

    protected function validateRecoveryAnswer($value)
    {
        $answer = trim((string) $value);
        $length = function_exists('mb_strlen') ? (int) mb_strlen($answer, 'UTF-8') : strlen($answer);

        if ($length < 2) {
            throw new RuntimeException('找回验证信息至少填写 2 个字符。');
        }

        if ($length > 60) {
            throw new RuntimeException('找回验证信息不能超过 60 个字符。');
        }
    }

    protected function validateRegistrationUsername($value)
    {
        $username = trim((string) $value);

        if (!preg_match('/^[A-Za-z0-9]{3,16}$/', $username)) {
            throw new RuntimeException('会员账号需为 3-16 位字母或数字。');
        }
    }

    protected function enforceDuplicateRegistrationLimit()
    {
        $days = max(0, min(365, (int) $this->app->settings()->get('members.register_limit_days', '1')));
        if ($days <= 0) {
            return;
        }

        $ip = trim((string) Security::ipAddress());
        $userAgent = trim(substr((string) Security::userAgent(), 0, 255));
        $matches = array();
        $params = array(
            'role_key' => 'member',
            'login_status' => 'success',
            'since' => date('Y-m-d H:i:s', time() - ($days * 86400)),
        );

        if ($ip !== '') {
            $matches[] = 'login_logs.login_ip = :login_ip';
            $params['login_ip'] = $ip;
        }

        if ($userAgent !== '' && $userAgent !== 'unknown' && strlen($userAgent) >= 12) {
            $matches[] = 'login_logs.user_agent = :user_agent';
            $params['user_agent'] = $userAgent;
        }

        if (!$matches) {
            return;
        }

        $existing = $this->db()->fetch(
            'SELECT users.id
             FROM users
             INNER JOIN roles ON roles.id = users.role_id
             INNER JOIN login_logs ON login_logs.user_id = users.id
             WHERE roles.role_key = :role_key
               AND users.created_at >= :since
               AND login_logs.login_status = :login_status
               AND (' . implode(' OR ', $matches) . ')
             ORDER BY users.created_at DESC
             LIMIT 1',
            $params
        );

        if ($existing) {
            throw new RuntimeException('当前 IP 或设备在 ' . $days . ' 天内已注册过会员，请稍后再试。');
        }
    }

    protected function ensureRecoverySchema()
    {
        $database = (string) $this->app->config('database', 'database', '');
        if ($database === '') {
            return;
        }

        $columns = array(
            'recovery_answer_hash' => "ALTER TABLE users ADD COLUMN recovery_answer_hash VARCHAR(255) DEFAULT NULL AFTER bio",
            'registered_recovery_answer' => "ALTER TABLE users ADD COLUMN registered_recovery_answer VARCHAR(255) DEFAULT NULL AFTER recovery_answer_hash",
        );

        foreach ($columns as $column => $sql) {
            $row = $this->db()->fetch(
                'SELECT COUNT(*) AS total_count
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = :schema AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
                array(
                    'schema' => $database,
                    'table_name' => 'users',
                    'column_name' => $column,
                )
            );

            if (!$row || (int) $row['total_count'] === 0) {
                $this->db()->pdo()->exec($sql);
            }
        }
    }

    public function findById($userId)
    {
        return $this->db()->fetch('SELECT users.*, roles.role_key, roles.role_name FROM users INNER JOIN roles ON roles.id = users.role_id WHERE users.id = :id LIMIT 1', array(
            'id' => $userId,
        ));
    }

    public function findByUsername($username)
    {
        return $this->db()->fetch('SELECT users.*, roles.role_key, roles.role_name FROM users INNER JOIN roles ON roles.id = users.role_id WHERE users.username = :username LIMIT 1', array(
            'username' => trim((string) $username),
        ));
    }

    protected function findInviteUser($inviteCode)
    {
        $inviteCode = trim((string) $inviteCode);
        if ($inviteCode === '') {
            return null;
        }

        $user = $this->findByUsername($inviteCode);
        if ($user && $this->isMemberRole((string) ($user['role_key'] ?? ''))) {
            return $user;
        }

        if (ctype_digit($inviteCode)) {
            $user = $this->findById((int) $inviteCode);
            if ($user && $this->isMemberRole((string) ($user['role_key'] ?? ''))) {
                return $user;
            }
        }

        return null;
    }

    public function roleIdByKey($roleKey)
    {
        if (array_key_exists((string) $roleKey, $this->memberRoleDefinitions())) {
            $this->ensureMembershipSchema();
        }

        $row = $this->db()->fetch('SELECT id FROM roles WHERE role_key = :role_key LIMIT 1', array(
            'role_key' => $roleKey,
        ));

        if (!$row) {
            throw new RuntimeException('角色不存在：' . $roleKey);
        }

        return (int) $row['id'];
    }

    public function allRoles()
    {
        return $this->db()->fetchAll('SELECT * FROM roles ORDER BY id ASC');
    }

    public function memberRoleDefinitions()
    {
        return array(
            'member' => array('name' => '普通会员', 'required_recharge' => 0, 'comment_limit' => 0, 'post_limit' => 0),
            'vip1' => array('name' => 'VIP1会员', 'required_recharge' => 0, 'comment_limit' => 0, 'post_limit' => 0),
            'vip2' => array('name' => 'VIP2会员', 'required_recharge' => 80000, 'comment_limit' => 2, 'post_limit' => 0),
            'vip3' => array('name' => 'VIP3会员', 'required_recharge' => 120000, 'comment_limit' => 10, 'post_limit' => 0),
            'vip_annual' => array('name' => 'VIP年度会员', 'required_recharge' => 200000, 'comment_limit' => -1, 'post_limit' => 1),
            'super_vip' => array('name' => '超级VIP会员', 'required_recharge' => 500000, 'comment_limit' => -1, 'post_limit' => 2),
        );
    }

    public function memberRoleKeys()
    {
        return array_keys($this->memberRoleDefinitions());
    }

    public function memberRoles()
    {
        $this->ensureMembershipSchema();

        return $this->db()->fetchAll(
            "SELECT * FROM roles
             WHERE role_key IN ('member', 'vip1', 'vip2', 'vip3', 'vip_annual', 'super_vip')
             ORDER BY CASE role_key
                 WHEN 'member' THEN 1
                 WHEN 'vip1' THEN 2
                 WHEN 'vip2' THEN 3
                 WHEN 'vip3' THEN 4
                 WHEN 'vip_annual' THEN 5
                 WHEN 'super_vip' THEN 6
                 ELSE 99
             END"
        );
    }

    public function isMemberRole($roleKey)
    {
        return array_key_exists((string) $roleKey, $this->memberRoleDefinitions());
    }

    public function memberPolicy($roleKey)
    {
        $definitions = $this->memberRoleDefinitions();

        return isset($definitions[(string) $roleKey]) ? $definitions[(string) $roleKey] : null;
    }

    public function canUsePostInteraction($user)
    {
        return is_array($user) && $this->isMemberRole((string) ($user['role_key'] ?? ''));
    }

    public function commentLimitFor($user)
    {
        $policy = is_array($user) ? $this->memberPolicy((string) ($user['role_key'] ?? '')) : null;

        return $policy ? (int) $policy['comment_limit'] : 0;
    }

    public function postLimitFor($user)
    {
        $policy = is_array($user) ? $this->memberPolicy((string) ($user['role_key'] ?? '')) : null;

        return $policy ? (int) $policy['post_limit'] : 0;
    }

    public function assertCanUsePostInteraction($user, $message = '当前会员等级暂无该操作权限。')
    {
        if (!$user) {
            throw new RuntimeException('请先登录。');
        }

        if (!$this->canUsePostInteraction($user)) {
            throw new RuntimeException($message);
        }
    }

    public function assertCanComment($user)
    {
        $this->assertCanUsePostInteraction($user, '当前账号不是会员角色，不能评论。');
        $limit = $this->commentLimitFor($user);

        if ($limit === 0) {
            throw new RuntimeException('当前会员等级暂未开放评论权限。');
        }

        if ($limit > 0 && $this->todayCommentCount((int) $user['id']) >= $limit) {
            throw new RuntimeException('今日评论次数已达上限。');
        }
    }

    public function assertCanCreatePost($user)
    {
        $this->assertCanUsePostInteraction($user, '当前账号不是会员角色，不能发帖。');
        $limit = $this->postLimitFor($user);

        if ($limit === 0) {
            throw new RuntimeException('当前会员等级暂未开放发帖权限。');
        }

        if ($limit > 0 && $this->todayPostCount((int) $user['id']) >= $limit) {
            throw new RuntimeException('今日发帖次数已达上限。');
        }
    }

    public function todayCommentCount($userId)
    {
        if (!$this->tableExists('replies')) {
            return 0;
        }

        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS total_count
             FROM replies
             WHERE user_id = :user_id
               AND status = :status
               AND created_at >= :start_at
               AND created_at < :end_at',
            array(
                'user_id' => (int) $userId,
                'status' => 'published',
                'start_at' => date('Y-m-d 00:00:00'),
                'end_at' => date('Y-m-d 00:00:00', strtotime('+1 day')),
            )
        );

        return (int) ($row['total_count'] ?? 0);
    }

    public function todayPostCount($userId)
    {
        if (!$this->tableExists('posts')) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) AS total_count
                FROM posts
                WHERE author_id = :author_id
                  AND created_at >= :start_at
                  AND created_at < :end_at';
        if ($this->columnExists('posts', 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }

        $row = $this->db()->fetch($sql, array(
            'author_id' => (int) $userId,
            'start_at' => date('Y-m-d 00:00:00'),
            'end_at' => date('Y-m-d 00:00:00', strtotime('+1 day')),
        ));

        return (int) ($row['total_count'] ?? 0);
    }

    public function ensureMembershipSchema()
    {
        if ($this->tableExists('users') && !$this->columnExists('users', 'recharge_score_total')) {
            $this->db()->pdo()->exec("ALTER TABLE users ADD COLUMN recharge_score_total INT NOT NULL DEFAULT 0 AFTER score");
        }

        if (!$this->tableExists('roles')) {
            return;
        }

        $now = $this->now();
        foreach ($this->memberRoleDefinitions() as $roleKey => $definition) {
            $exists = $this->db()->fetch('SELECT id FROM roles WHERE role_key = :role_key LIMIT 1', array(
                'role_key' => $roleKey,
            ));

            if ($exists) {
                $this->db()->execute('UPDATE roles SET role_name = :role_name, updated_at = :updated_at WHERE id = :id', array(
                    'role_name' => (string) $definition['name'],
                    'updated_at' => $now,
                    'id' => (int) $exists['id'],
                ));
                continue;
            }

            $this->db()->insertGetId('INSERT INTO roles (role_key, role_name, created_at, updated_at) VALUES (:role_key, :role_name, :created_at, :updated_at)', array(
                'role_key' => $roleKey,
                'role_name' => (string) $definition['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }
    }

    protected function assertRoleThreshold($roleKey, $rechargeScoreTotal)
    {
        $policy = $this->memberPolicy($roleKey);
        if (!$policy) {
            throw new RuntimeException('会员角色无效。');
        }

        $requiredRecharge = (int) $policy['required_recharge'];
        if ((int) $rechargeScoreTotal < $requiredRecharge) {
            throw new RuntimeException($policy['name'] . '需要累计充值积分不少于 ' . $requiredRecharge . '。');
        }
    }

    protected function tableExists($tableName)
    {
        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS total_count
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name',
            array('table_name' => (string) $tableName)
        );

        return $row && (int) $row['total_count'] > 0;
    }

    protected function columnExists($tableName, $columnName)
    {
        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS total_count
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name',
            array(
                'table_name' => (string) $tableName,
                'column_name' => (string) $columnName,
            )
        );

        return $row && (int) $row['total_count'] > 0;
    }

    public function allPermissions()
    {
        return $this->db()->fetchAll('SELECT * FROM permissions ORDER BY permission_group ASC, id ASC');
    }

    public function userHasPermission($userId, $permissionKey)
    {
        $row = $this->db()->fetch('SELECT permissions.id FROM users INNER JOIN roles ON roles.id = users.role_id INNER JOIN role_permissions ON role_permissions.role_id = roles.id INNER JOIN permissions ON permissions.id = role_permissions.permission_id WHERE users.id = :user_id AND permissions.permission_key = :permission_key LIMIT 1', array(
            'user_id' => $userId,
            'permission_key' => $permissionKey,
        ));

        return $row !== null;
    }

    public function register(array $payload)
    {
        $this->ensureRecoverySchema();

        $username = trim((string) $payload['username']);
        $password = (string) $payload['password'];
        $confirmPassword = (string) $payload['confirm_password'];
        $recoveryAnswer = isset($payload['recovery_answer']) ? (string) $payload['recovery_answer'] : '';
        $this->validateRegistrationUsername($username);

        if ($error = Validator::password($password)) {
            throw new RuntimeException($error);
        }

        if ($password !== $confirmPassword) {
            throw new RuntimeException('两次输入的密码不一致。');
        }

        if ($this->findByUsername($username)) {
            throw new RuntimeException('用户名已存在，请更换后重试。');
        }

        $this->enforceDuplicateRegistrationLimit();
        $this->validateRecoveryAnswer($recoveryAnswer);

        $bonus = (int) $this->app->settings()->get('points.register_bonus', 88);
        $inviteCode = isset($payload['invite_code'])
            ? trim((string) $payload['invite_code'])
            : trim((string) ($payload['invite'] ?? ''));
        $inviteReward = max(0, (int) $this->app->settings()->get('points.invite_register_bonus', 0));
        $inviteUser = null;
        if ($inviteCode !== '') {
            $inviteUser = $this->findInviteUser($inviteCode);
            if (!$inviteUser) {
                throw new RuntimeException('邀请人不存在，请核对邀请账号。');
            }
        }

        $roleId = $this->roleIdByKey('member');
        $now = $this->now();
        $this->db()->beginTransaction();
        try {
            $userId = $this->db()->insertGetId('INSERT INTO users (role_id, username, password_hash, recovery_answer_hash, registered_recovery_answer, score, status, created_at, updated_at) VALUES (:role_id, :username, :password_hash, :recovery_answer_hash, :registered_recovery_answer, :score, :status, :created_at, :updated_at)', array(
                'role_id' => $roleId,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'recovery_answer_hash' => password_hash($this->normalizeRecoveryAnswer($recoveryAnswer), PASSWORD_DEFAULT),
                'registered_recovery_answer' => trim((string) $recoveryAnswer),
                'score' => $bonus,
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ));

            if ($inviteUser && $inviteReward > 0 && (int) $inviteUser['id'] !== $userId) {
                $this->db()->execute('UPDATE users SET score = score + :score, updated_at = :updated_at WHERE id = :id', array(
                    'score' => $inviteReward,
                    'updated_at' => $now,
                    'id' => (int) $inviteUser['id'],
                ));
            }

            $this->db()->commit();
        } catch (Throwable $exception) {
            $this->db()->rollBack();
            throw $exception;
        }

        $user = $this->findById($userId);
        $this->app->auth()->login($user);
        $this->app->logs()->login($username, $user['role_key'], 'success', $userId);
        $this->app->logs()->system('auth', '新用户注册成功', 'info', array(
            'user_id' => $userId,
            'username' => $username,
            'invite_user_id' => $inviteUser ? (int) $inviteUser['id'] : 0,
            'invite_reward' => $inviteUser ? $inviteReward : 0,
        ));

        if ($inviteUser && $inviteReward > 0 && (int) $inviteUser['id'] !== $userId) {
            try {
                $this->app->support()->notifyInviteRegisterReward($inviteUser, $username, $inviteReward);
            } catch (Throwable $exception) {
                $this->app->logs()->system('support', '邀请奖励客服通知发送失败', 'warning', array(
                    'invite_user_id' => (int) $inviteUser['id'],
                    'registered_user_id' => $userId,
                    'registered_username' => $username,
                    'invite_reward' => $inviteReward,
                    'error' => $exception->getMessage(),
                ));
            }
        }

        return $user;
    }

    public function attemptLogin($username, $password, $adminOnly = false)
    {
        $bucket = 'login:' . Security::ipAddress() . ':' . trim((string) $username);
        $maxAttempts = (int) $this->app->settings()->get('security.max_login_attempts', 5);

        if (!Security::rateLimit($this->app, $bucket, $maxAttempts, 300)) {
            throw new RuntimeException('登录失败次数过多，请稍后再试。');
        }

        $user = $this->findByUsername($username);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->app->logs()->login((string) $username, $user ? $user['role_key'] : null, 'failed', $user ? $user['id'] : null);
            throw new RuntimeException('账号或密码错误。');
        }

        if ($user['status'] !== 'active') {
            $this->app->logs()->login((string) $username, $user['role_key'], 'disabled', $user['id']);
            throw new RuntimeException('当前账号已被禁用，请联系管理员。');
        }

        if (!$adminOnly && in_array($user['role_key'], array('super_admin', 'admin'), true)) {
            $this->app->logs()->login((string) $username, $user['role_key'], 'forbidden', $user['id']);
            throw new RuntimeException('管理员账号请从后台入口登录，不能作为会员登录前台。');
        }

        if ($adminOnly && !in_array($user['role_key'], array('super_admin', 'admin'), true)) {
            $this->app->logs()->login((string) $username, $user['role_key'], 'forbidden', $user['id']);
            throw new RuntimeException('该账号没有后台登录权限。');
        }

        $this->app->auth()->login($user);
        Security::clearRateLimit($this->app, $bucket);
        $this->app->logs()->login((string) $username, $user['role_key'], 'success', $user['id']);

        return $this->findById($user['id']);
    }

    public function touchLoginMeta($userId)
    {
        $ip = Security::ipAddress();
        $this->db()->execute('UPDATE users SET last_login_at = :last_login_at, last_login_ip = :last_login_ip, login_province = :login_province, updated_at = :updated_at WHERE id = :id', array(
            'last_login_at' => $this->now(),
            'last_login_ip' => $ip,
            'login_province' => Security::provinceFromIp($ip),
            'updated_at' => $this->now(),
            'id' => $userId,
        ));
    }

    public function recentLoginLogs($userId, $limit = 3)
    {
        $rows = $this->db()->fetchAll('SELECT * FROM login_logs WHERE user_id = :user_id ORDER BY created_at DESC LIMIT ' . (int) $limit, array(
            'user_id' => $userId,
        ));

        return $this->enrichLoginAreaRows($rows);
    }

    public function listUsers($roleFilter = null)
    {
        if ($roleFilter !== null) {
            return $this->db()->fetchAll('SELECT users.*, roles.role_key, roles.role_name FROM users INNER JOIN roles ON roles.id = users.role_id WHERE roles.role_key = :role_key ORDER BY users.created_at DESC', array(
                'role_key' => $roleFilter,
            ));
        }

        return $this->db()->fetchAll('SELECT users.*, roles.role_key, roles.role_name FROM users INNER JOIN roles ON roles.id = users.role_id ORDER BY users.created_at DESC');
    }

    public function updateProfile($userId, array $payload)
    {
        $email = trim((string) ($payload['email'] ?? ''));
        $bio = trim((string) ($payload['bio'] ?? ''));

        $this->db()->execute('UPDATE users SET email = :email, bio = :bio, updated_at = :updated_at WHERE id = :id', array(
            'email' => $email !== '' ? $email : null,
            'bio' => $bio !== '' ? $bio : null,
            'updated_at' => $this->now(),
            'id' => $userId,
        ));

        return $this->findById($userId);
    }

    public function updateAvatar($userId, array $file)
    {
        if (empty($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new RuntimeException('请选择要上传的头像图片。');
        }

        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new RuntimeException('头像上传失败，请重新选择图片。');
        }

        if (!$this->columnExists('users', 'avatar')) {
            throw new RuntimeException('当前会员表还没有头像字段，请先完成系统升级。');
        }

        $originalName = trim((string) ($file['name'] ?? ''));
        $tmpName = (string) ($file['tmp_name'] ?? '');
        $fileSize = (int) ($file['size'] ?? 0);
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $allowedExtensions = array('jpg', 'jpeg', 'png', 'gif', 'webp');

        if ($originalName === '' || $tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new RuntimeException('头像文件无效，请重新选择图片。');
        }

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new RuntimeException('头像仅支持 jpg、jpeg、png、gif、webp 图片。');
        }

        if ($fileSize <= 0 || $fileSize > 3 * 1024 * 1024) {
            throw new RuntimeException('头像图片不能超过 3MB。');
        }

        $imageInfo = @getimagesize($tmpName);
        if (!is_array($imageInfo) || empty($imageInfo['mime'])) {
            throw new RuntimeException('头像必须是有效图片。');
        }

        $allowedMimeTypes = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if (!in_array((string) $imageInfo['mime'], $allowedMimeTypes, true)) {
            throw new RuntimeException('头像图片类型无效。');
        }

        $relativeDirectory = 'uploads/avatar/' . date('Ym');
        $absoluteDirectory = $this->app->basePath('public/' . $relativeDirectory);
        if (!is_dir($absoluteDirectory) && !mkdir($absoluteDirectory, 0777, true) && !is_dir($absoluteDirectory)) {
            throw new RuntimeException('头像目录创建失败，请检查权限。');
        }

        $storageName = date('YmdHis') . '-' . bin2hex(random_bytes(8)) . '.' . $extension;
        $absolutePath = $absoluteDirectory . DIRECTORY_SEPARATOR . $storageName;
        if (!move_uploaded_file($tmpName, $absolutePath)) {
            throw new RuntimeException('头像保存失败，请重试。');
        }

        $avatarPath = \public_url($relativeDirectory . '/' . $storageName);
        $this->db()->execute('UPDATE users SET avatar = :avatar, updated_at = :updated_at WHERE id = :id', array(
            'avatar' => $avatarPath,
            'updated_at' => $this->now(),
            'id' => $userId,
        ));

        return $this->findById($userId);
    }

    public function changePassword($userId, array $payload)
    {
        $user = $this->findById($userId);
        $oldPassword = (string) $payload['old_password'];
        $password = (string) $payload['password'];
        $confirmPassword = (string) $payload['confirm_password'];

        if (!password_verify($oldPassword, $user['password_hash'])) {
            throw new RuntimeException('旧密码不正确。');
        }

        if ($error = Validator::password($password)) {
            throw new RuntimeException($error);
        }

        if ($password !== $confirmPassword) {
            throw new RuntimeException('两次输入的新密码不一致。');
        }

        $this->db()->execute('UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id', array(
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'updated_at' => $this->now(),
            'id' => $userId,
        ));

        return true;
    }

    public function changeRecoveryAnswer($userId, array $payload)
    {
        $this->ensureRecoverySchema();

        $user = $this->findById($userId);
        $currentPassword = (string) ($payload['current_password'] ?? '');
        $recoveryAnswer = (string) ($payload['recovery_answer'] ?? '');
        $confirmRecoveryAnswer = (string) ($payload['confirm_recovery_answer'] ?? '');

        if (!$user || !password_verify($currentPassword, (string) $user['password_hash'])) {
            throw new RuntimeException('当前密码不正确。');
        }

        $this->validateRecoveryAnswer($recoveryAnswer);

        if ($this->normalizeRecoveryAnswer($recoveryAnswer) !== $this->normalizeRecoveryAnswer($confirmRecoveryAnswer)) {
            throw new RuntimeException('两次填写的找回验证信息不一致。');
        }

        $this->db()->execute('UPDATE users SET recovery_answer_hash = :recovery_answer_hash, updated_at = :updated_at WHERE id = :id', array(
            'recovery_answer_hash' => password_hash($this->normalizeRecoveryAnswer($recoveryAnswer), PASSWORD_DEFAULT),
            'updated_at' => $this->now(),
            'id' => $userId,
        ));

        return true;
    }

    public function saveUser(array $payload, $actor)
    {
        $this->ensureMembershipSchema();

        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $roleKey = isset($payload['role_key']) ? (string) $payload['role_key'] : 'member';
        $username = trim((string) $payload['username']);
        $password = isset($payload['password']) ? (string) $payload['password'] : '';
        $email = trim((string) $payload['email']);
        $bio = trim((string) $payload['bio']);
        $score = isset($payload['score']) ? (int) $payload['score'] : 0;
        $status = isset($payload['status']) && $payload['status'] === 'disabled' ? 'disabled' : 'active';

        if ($error = Validator::username($username)) {
            throw new RuntimeException($error);
        }

        if (!$this->isMemberRole($roleKey)) {
            throw new RuntimeException('会员管理只能选择会员角色，不能混用管理员角色。');
        }

        $roleId = $this->roleIdByKey($roleKey);
        $existing = $this->findByUsername($username);
        if ($existing && (int) $existing['id'] !== $id) {
            throw new RuntimeException('该用户名已被占用。');
        }

        $currentUser = $id > 0 ? $this->findById($id) : null;
        if ($id > 0 && (!$currentUser || !$this->isMemberRole((string) ($currentUser['role_key'] ?? '')))) {
            throw new RuntimeException('不能在会员管理中编辑管理员账号。');
        }

        $rechargeScoreTotal = $currentUser
            ? max((int) ($currentUser['recharge_score_total'] ?? 0), max(0, $score))
            : max(0, $score);
        $this->assertRoleThreshold($roleKey, $rechargeScoreTotal);

        if ($id > 0) {
            $query = 'UPDATE users SET role_id = :role_id, username = :username, email = :email, bio = :bio, score = :score, recharge_score_total = :recharge_score_total, status = :status, updated_at = :updated_at';
            $params = array(
                'role_id' => $roleId,
                'username' => $username,
                'email' => $email !== '' ? $email : null,
                'bio' => $bio !== '' ? $bio : null,
                'score' => $score,
                'recharge_score_total' => $rechargeScoreTotal,
                'status' => $status,
                'updated_at' => $this->now(),
                'id' => $id,
            );

            if ($password !== '') {
                if ($error = Validator::password($password)) {
                    throw new RuntimeException($error);
                }

                $query .= ', password_hash = :password_hash';
                $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
            }

            $query .= ' WHERE id = :id';
            $this->db()->execute($query, $params);
            $userId = $id;
            $action = 'update';
        } else {
            if ($error = Validator::password($password)) {
                throw new RuntimeException('新建用户时必须填写 6-20 位字母或数字密码。');
            }

            $userId = $this->db()->insertGetId('INSERT INTO users (role_id, username, password_hash, email, bio, score, recharge_score_total, status, created_at, updated_at) VALUES (:role_id, :username, :password_hash, :email, :bio, :score, :recharge_score_total, :status, :created_at, :updated_at)', array(
                'role_id' => $roleId,
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'email' => $email !== '' ? $email : null,
                'bio' => $bio !== '' ? $bio : null,
                'score' => $score,
                'recharge_score_total' => $rechargeScoreTotal,
                'status' => $status,
                'created_at' => $this->now(),
                'updated_at' => $this->now(),
            ));
            $action = 'create';
        }

        $this->app->logs()->admin('members', $action, '保存用户：' . $username, 'user', (string) $userId, $actor['id']);

        return $this->findById($userId);
    }

    public function addScore($userId, $amount, $actor)
    {
        $this->ensureMembershipSchema();

        $user = $this->findById($userId);
        if (!$user || !$this->isMemberRole((string) ($user['role_key'] ?? ''))) {
            throw new RuntimeException('只能调整会员账号积分。');
        }

        $newScore = (int) $user['score'] + (int) $amount;

        if ($newScore < 0) {
            throw new RuntimeException('积分调整后不能小于 0。');
        }

        $this->db()->execute('UPDATE users SET score = :score, recharge_score_total = recharge_score_total + :recharge_delta, updated_at = :updated_at WHERE id = :id', array(
            'score' => $newScore,
            'recharge_delta' => max(0, (int) $amount),
            'updated_at' => $this->now(),
            'id' => $userId,
        ));

        $actorId = array_key_exists('id', $actor) ? $actor['id'] : null;
        $scoreLogSource = trim((string) ($actor['score_log_source'] ?? ''));
        if ($scoreLogSource === 'customer_service') {
            $scoreLogOperator = trim((string) ($actor['score_log_operator'] ?? '客服'));
            $scoreLogDescription = '接待端客服调分：' . $scoreLogOperator . ' 为 ' . $user['username'] . ' => ' . (int) $amount . '，剩余 ' . $newScore;
        } else {
            $scoreLogDescription = '调整会员积分：' . $user['username'] . ' => ' . (int) $amount;
        }

        $this->app->logs()->admin('members', 'charge', $scoreLogDescription, 'user', (string) $userId, $actorId);

        return $this->findById($userId);
    }

    public function createPasswordResetRequest($username, $note)
    {
        $user = $this->findByUsername($username);
        if (!$user) {
            throw new RuntimeException('未找到该用户名，请确认后重试。');
        }

        $this->db()->execute('INSERT INTO password_reset_requests (user_id, username, note, status, created_at) VALUES (:user_id, :username, :note, :status, :created_at)', array(
            'user_id' => $user['id'],
            'username' => $user['username'],
            'note' => trim((string) $note) !== '' ? trim((string) $note) : '用户发起找回密码申请',
            'status' => 'pending',
            'created_at' => $this->now(),
        ));

        return true;
    }

    public function resetPasswordByRecovery(array $payload)
    {
        $this->ensureRecoverySchema();

        $username = trim((string) ($payload['username'] ?? ''));
        $recoveryAnswer = (string) ($payload['recovery_answer'] ?? '');
        $password = (string) ($payload['password'] ?? '');
        $confirmPassword = (string) ($payload['confirm_password'] ?? '');

        if ($username === '') {
            throw new RuntimeException('请输入会员账号。');
        }

        $user = $this->findByUsername($username);
        if (!$user) {
            throw new RuntimeException('未找到该会员账号，请确认后重试。');
        }

        if ((string) $user['status'] !== 'active') {
            throw new RuntimeException('当前账号已被禁用，请联系管理员处理。');
        }

        if (empty($user['recovery_answer_hash'])) {
            throw new RuntimeException('该账号还未设置找回验证信息，请联系管理员处理。');
        }

        $this->validateRecoveryAnswer($recoveryAnswer);

        if (!password_verify($this->normalizeRecoveryAnswer($recoveryAnswer), (string) $user['recovery_answer_hash'])) {
            throw new RuntimeException('找回验证信息不正确，请核对后重试。');
        }

        if ($error = Validator::password($password)) {
            throw new RuntimeException($error);
        }

        if ($password !== $confirmPassword) {
            throw new RuntimeException('两次输入的新密码不一致。');
        }

        $now = $this->now();
        $this->db()->beginTransaction();
        try {
            $this->db()->execute('UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id', array(
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'updated_at' => $now,
                'id' => $user['id'],
            ));

            $this->db()->execute('INSERT INTO password_reset_requests (user_id, username, note, status, created_at, processed_at, processed_by) VALUES (:user_id, :username, :note, :status, :created_at, :processed_at, :processed_by)', array(
                'user_id' => $user['id'],
                'username' => $user['username'],
                'note' => '用户通过找回验证信息自助重置密码',
                'status' => 'self_service',
                'created_at' => $now,
                'processed_at' => $now,
                'processed_by' => null,
            ));

            $this->db()->commit();
        } catch (\Exception $exception) {
            $this->db()->rollBack();
            throw $exception;
        }

        $this->app->logs()->system('auth', '用户通过验证信息重置密码', 'info', array(
            'user_id' => $user['id'],
            'username' => $user['username'],
        ));

        return true;
    }

    public function listPasswordResetRequests()
    {
        return $this->db()->fetchAll('SELECT * FROM password_reset_requests ORDER BY created_at DESC');
    }

    public function processPasswordReset($requestId, $newPassword, $adminUser)
    {
        $request = $this->db()->fetch('SELECT * FROM password_reset_requests WHERE id = :id LIMIT 1', array(
            'id' => $requestId,
        ));

        if (!$request) {
            throw new RuntimeException('找回申请不存在。');
        }

        if ($request['status'] !== 'pending') {
            throw new RuntimeException('该申请已经处理过了。');
        }

        if ($error = Validator::password($newPassword)) {
            throw new RuntimeException($error);
        }

        $this->db()->beginTransaction();
        try {
            $this->db()->execute('UPDATE users SET password_hash = :password_hash, updated_at = :updated_at WHERE id = :id', array(
                'password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
                'updated_at' => $this->now(),
                'id' => $request['user_id'],
            ));

            $this->db()->execute('UPDATE password_reset_requests SET status = :status, processed_at = :processed_at, processed_by = :processed_by WHERE id = :id', array(
                'status' => 'processed',
                'processed_at' => $this->now(),
                'processed_by' => $adminUser['id'],
                'id' => $requestId,
            ));

            $this->db()->commit();
        } catch (\Exception $exception) {
            $this->db()->rollBack();
            throw $exception;
        }

        $this->app->logs()->admin('members', 'reset_password', '处理找回密码申请：' . $request['username'], 'password_reset_request', (string) $requestId, $adminUser['id']);
    }

    public function assignRolePermissions($roleId, array $permissionIds)
    {
        $this->db()->execute('DELETE FROM role_permissions WHERE role_id = :role_id', array(
            'role_id' => $roleId,
        ));

        foreach ($permissionIds as $permissionId) {
            $this->db()->execute('INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (:role_id, :permission_id, :created_at)', array(
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => $this->now(),
            ));
        }
    }

}
