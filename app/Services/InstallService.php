<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use PDO;
use PDOException;
use RuntimeException;

class InstallService extends Service
{
    public function install(array $payload)
    {
        $dbHost = trim((string) $payload['db_host']);
        $dbPort = (int) $payload['db_port'];
        $dbName = trim((string) $payload['db_name']);
        $dbUser = trim((string) $payload['db_user']);
        $dbPass = (string) $payload['db_pass'];
        $siteName = trim((string) $payload['site_name']);
        $adminUsername = trim((string) $payload['admin_username']);
        $adminPassword = (string) $payload['admin_password'];

        if ($dbHost === '' || $dbPort <= 0 || $dbName === '' || $dbUser === '' || $siteName === '' || $adminUsername === '' || $adminPassword === '') {
            throw new RuntimeException('安装信息不完整，请填写全部必填项。');
        }

        $serverDsn = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $dbHost, $dbPort);

        try {
            $pdo = new PDO($serverDsn, $dbUser, $dbPass, array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ));
        } catch (PDOException $exception) {
            throw new RuntimeException('无法连接数据库服务器，请检查主机、端口、账号和密码。');
        }

        $safeDbName = str_replace('`', '', $dbName);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . $safeDbName . '` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        $databaseConfig = array(
            'host' => $dbHost,
            'port' => $dbPort,
            'database' => $dbName,
            'username' => $dbUser,
            'password' => $dbPass,
            'charset' => 'utf8mb4',
        );

        $this->writeEnvironmentConfig($databaseConfig);
        $this->writeDatabaseConfig();
        $this->app->useDatabaseConfig($databaseConfig);
        $database = new Database($databaseConfig);
        $this->runSchema($database);

        $this->seedPermissions($database);
        $roleIds = $this->seedRoles($database);
        $this->attachRolePermissions($database, $roleIds);
        $adminId = $this->seedAdmin($database, $roleIds['super_admin'], $adminUsername, $adminPassword);
        $this->app->admins()->installSeed($database, array(
            'db_host' => $dbHost,
            'db_port' => $dbPort,
            'db_name' => $dbName,
            'db_prefix' => '',
            'site_name' => $siteName,
            'site_domain' => isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '',
            'admin_username' => $adminUsername,
            'admin_password' => $adminPassword,
        ));
        $this->seedSettings($database, $siteName);
        $this->seedDraws($database, $adminId);
        $this->seedPosts($database, $adminId);

        file_put_contents($this->app->basePath('storage/install.lock'), 'installed_at=' . date('c'));

        return array(
            'admin_username' => $adminUsername,
            'admin_password' => $adminPassword,
        );
    }

    protected function writeEnvironmentConfig(array $config)
    {
        $content = 'DB_HOST=' . (string) $config['host'] . "\n" .
            'DB_PORT=' . (string) $config['port'] . "\n" .
            'DB_DATABASE=' . (string) $config['database'] . "\n" .
            'DB_USERNAME=' . (string) $config['username'] . "\n" .
            'DB_PASSWORD=' . (string) $config['password'] . "\n" .
            "DB_CHARSET=utf8mb4\n";

        $envPath = $this->app->basePath('.env');
        file_put_contents($envPath, $content, LOCK_EX);
        @chmod($envPath, 0600);
    }

    protected function writeDatabaseConfig()
    {
        $content = "<?php\n" .
            "declare(strict_types=1);\n\n" .
            "\$basePath = dirname(__DIR__);\n" .
            "\$envPath = \$basePath . '/.env';\n\n" .
            "\$dotenv = array();\n\n" .
            "if (is_file(\$envPath) && is_readable(\$envPath)) {\n" .
            "    \$lines = file(\$envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);\n" .
            "    if (is_array(\$lines)) {\n" .
            "        foreach (\$lines as \$line) {\n" .
            "            \$line = trim((string) \$line);\n" .
            "            if (\$line === '' || strpos(\$line, '#') === 0 || strpos(\$line, '=') === false) {\n" .
            "                continue;\n" .
            "            }\n\n" .
            "            [\$key, \$value] = explode('=', \$line, 2);\n" .
            "            \$key = trim(\$key);\n" .
            "            \$value = trim(\$value);\n" .
            "            if (\$key === '') {\n" .
            "                continue;\n" .
            "            }\n\n" .
            "            if (\n" .
            "                strlen(\$value) >= 2\n" .
            "                && ((\$value[0] === '\"' && substr(\$value, -1) === '\"') || (\$value[0] === \"'\" && substr(\$value, -1) === \"'\"))\n" .
            "            ) {\n" .
            "                \$value = substr(\$value, 1, -1);\n" .
            "            }\n\n" .
            "            \$dotenv[\$key] = \$value;\n" .
            "            if (!array_key_exists(\$key, \$_ENV)) {\n" .
            "                \$_ENV[\$key] = \$value;\n" .
            "            }\n" .
            "        }\n" .
            "    }\n" .
            "}\n\n" .
            "\$env = static function (\$key, \$default = '') use (\$dotenv) {\n" .
            "    if (array_key_exists(\$key, \$dotenv)) {\n" .
            "        return \$dotenv[\$key];\n" .
            "    }\n\n" .
            "    \$value = getenv(\$key);\n\n" .
            "    return \$value === false ? \$default : \$value;\n" .
            "};\n\n" .
            "return array(\n" .
            "    'host' => (string) \$env('DB_HOST', '127.0.0.1'),\n" .
            "    'port' => (int) \$env('DB_PORT', '3306'),\n" .
            "    'database' => (string) \$env('DB_DATABASE', ''),\n" .
            "    'username' => (string) \$env('DB_USERNAME', ''),\n" .
            "    'password' => (string) \$env('DB_PASSWORD', ''),\n" .
            "    'charset' => (string) \$env('DB_CHARSET', 'utf8mb4'),\n" .
            ");\n";

        file_put_contents($this->app->basePath('config/database.php'), $content);
    }

    protected function runSchema(Database $database)
    {
        $this->runSchemaFile($database, $this->app->basePath('database/schema.sql'));
    }

    protected function runSchemaFile(Database $database, $filePath)
    {
        $schema = (string) file_get_contents($filePath);
        $statements = array_filter(array_map('trim', explode(';', $schema)));

        foreach ($statements as $statement) {
            if ($statement !== '') {
                $database->pdo()->exec($statement);
            }
        }
    }

    protected function seedPermissions(Database $database)
    {
        $now = date('Y-m-d H:i:s');
        $permissions = array(
            array('dashboard.view', '查看仪表盘', 'dashboard'),
            array('customer_service.view', '查看在线客服', 'customer_service'),
            array('customer_service.reply', '查看客服链路', 'customer_service'),
            array('customer_service.manage', '管理客服账号', 'customer_service'),
            array('settings.manage', '管理后台设置', 'settings'),
            array('forecast.manage', '管理AI预测', 'forecast'),
            array('draws.manage', '管理开奖资料', 'forecast'),
            array('posts.manage', '管理帖子', 'posts'),
            array('users.manage', '管理会员', 'users'),
            array('stats.view', '查看流量统计', 'stats'),
            array('logs.view', '查看网站日志', 'logs'),
            array('admins.manage', '管理管理员', 'users'),
        );

        foreach ($permissions as $permission) {
            $exists = $database->fetch('SELECT id FROM permissions WHERE permission_key = :permission_key LIMIT 1', array(
                'permission_key' => $permission[0],
            ));

            if ($exists) {
                continue;
            }

            $database->execute('INSERT INTO permissions (permission_key, permission_name, permission_group, created_at, updated_at) VALUES (:permission_key, :permission_name, :permission_group, :created_at, :updated_at)', array(
                'permission_key' => $permission[0],
                'permission_name' => $permission[1],
                'permission_group' => $permission[2],
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }
    }

    protected function seedRoles(Database $database)
    {
        $now = date('Y-m-d H:i:s');
        $roles = array(
            'super_admin' => '超级管理员',
            'admin' => '管理员',
            'member' => '普通会员',
            'vip1' => 'VIP1会员',
            'vip2' => 'VIP2会员',
            'vip3' => 'VIP3会员',
            'vip_annual' => 'VIP年度会员',
            'super_vip' => '超级VIP会员',
        );
        $ids = array();

        foreach ($roles as $key => $name) {
            $exists = $database->fetch('SELECT id FROM roles WHERE role_key = :role_key LIMIT 1', array(
                'role_key' => $key,
            ));

            if ($exists) {
                $ids[$key] = (int) $exists['id'];
                continue;
            }

            $ids[$key] = $database->insertGetId('INSERT INTO roles (role_key, role_name, created_at, updated_at) VALUES (:role_key, :role_name, :created_at, :updated_at)', array(
                'role_key' => $key,
                'role_name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }

        return $ids;
    }

    protected function attachRolePermissions(Database $database, array $roleIds)
    {
        $permissions = $database->fetchAll('SELECT id FROM permissions ORDER BY id ASC');
        $now = date('Y-m-d H:i:s');

        foreach ($permissions as $permission) {
            foreach (array('super_admin', 'admin') as $roleKey) {
                $exists = $database->fetch('SELECT id FROM role_permissions WHERE role_id = :role_id AND permission_id = :permission_id LIMIT 1', array(
                    'role_id' => $roleIds[$roleKey],
                    'permission_id' => $permission['id'],
                ));

                if ($exists) {
                    continue;
                }

                $database->execute('INSERT INTO role_permissions (role_id, permission_id, created_at) VALUES (:role_id, :permission_id, :created_at)', array(
                    'role_id' => $roleIds[$roleKey],
                    'permission_id' => $permission['id'],
                    'created_at' => $now,
                ));
            }
        }
    }

    protected function seedAdmin(Database $database, $roleId, $username, $password)
    {
        $now = date('Y-m-d H:i:s');
        $existing = $database->fetch('SELECT id FROM users WHERE username = :username LIMIT 1', array(
            'username' => $username,
        ));

        if ($existing) {
            $database->execute('UPDATE users SET role_id = :role_id, password_hash = :password_hash, score = :score, status = :status, bio = :bio, updated_at = :updated_at WHERE id = :id', array(
                'role_id' => $roleId,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'score' => 99999,
                'status' => 'active',
                'bio' => '系统初始化超级管理员',
                'updated_at' => $now,
                'id' => $existing['id'],
            ));

            return (int) $existing['id'];
        }

        return $database->insertGetId('INSERT INTO users (role_id, username, password_hash, email, avatar, bio, score, status, created_at, updated_at) VALUES (:role_id, :username, :password_hash, :email, :avatar, :bio, :score, :status, :created_at, :updated_at)', array(
            'role_id' => $roleId,
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'email' => null,
            'avatar' => null,
            'bio' => '系统初始化超级管理员',
            'score' => 99999,
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ));
    }

    protected function seedSettings(Database $database, $siteName)
    {
        $now = date('Y-m-d H:i:s');
        $macauPostGeneratorSettings = '{"generation_mode":"manual","segment_no":"2","top_scope":"top_3","preset_zodiac_min":"6","preset_zodiac_max":"9","preset_segment_min":"26","preset_segment_max":"30","preset_record_min":"10","preset_record_max":"12","preset_record_rate_min":"67","preset_record_rate_max":"89","preset_segments":["3"],"title_prefix":"澳门六合","title_middle":"","title_middle_wrap":"〖〗","author_nickname":"","author_nickname_pool":"","title_prefix_color_mode":"fixed","title_prefix_color_value":"#1F1F1F","title_middle_color_mode":"daily_random","title_middle_color_value":"#2563EB","author_nickname_color_mode":"fixed","author_nickname_color_value":"#1F1F1F","title_font_size":"14","title_font_weight":"500","target_zodiac":[],"target_number":[],"target_wave":[],"target_element":[],"target_head":[],"target_tail":[],"templates":["tema_3","zodiac_3","zodiac_code_3"],"manage_templates":["tema_1","tema_2","tema_3","tema_4","tema_5","tema_6","tema_7","tema_8","tema_9","tema_10","tema_11","tema_12","tema_13","tema_14","tema_15","tema_16","tema_17","tema_18","tema_19","tema_20","tema_21","tema_22","tema_23","zodiac_1","zodiac_2","zodiac_3","zodiac_4","zodiac_5","zodiac_6","zodiac_7","zodiac_8","zodiac_9","zodiac_code_1","zodiac_code_10","zodiac_code_2","zodiac_code_11","zodiac_code_3","zodiac_code_12","zodiac_code_4","zodiac_code_13","zodiac_code_5","zodiac_code_6","zodiac_code_9","zodiac_code_7","zodiac_code_8","flat_special_1","flat_special_2","flat_special_3","flat_special_4","flat_special_5","flat_special_6","flat_special_7","flat_special_8","flat_special_9","flat_special_10","flat_special_11","normal_code_1","normal_code_2","normal_code_3","normal_code_4","normal_code_5","normal_code_6","normal_code_7","normal_code_8","normal_code_9","normal_code_10","normal_code_11","normal_code_12","normal_code_13","normal_code_14","normal_code_15","normal_code_16","normal_code_17","normal_code_18","normal_code_19","normal_code_20","normal_code_21","normal_code_22","normal_code_23","normal_code_24","normal_code_25","normal_code_26","normal_code_27","normal_code_28","normal_code_29","normal_code_30","normal_code_31","normal_code_32","normal_code_33","head_1","head_2","head_3","head_4","head_5","head_6","head_7","head_8","head_9","head_10","tail_1","tail_2","tail_3","tail_4","tail_5","tail_6","tail_7","tail_8","tail_9","tail_10","tail_11","tail_12","wave_element_1","wave_element_2","wave_element_3","wave_element_4","wave_element_5","wave_element_6","wave_element_7","double_single_1","double_single_2","double_single_3","double_single_4","double_single_5","double_single_6","zodiac_attr_1_1","kills_1","kills_2","kills_3","kills_4","kills_5","kills_6","kills_7","kills_8","kills_9","kills_10","kills_11","kills_12","kills_13"],"is_blank_content":"1","is_fake_after_open":"1","post_update_time":"23:59","material_content_time":"19:20"}';
        $hongkongPostGeneratorSettings = '{"generation_mode":"manual","segment_no":"1","top_scope":"top_1","preset_zodiac_min":"6","preset_zodiac_max":"8","preset_segment_min":"22","preset_segment_max":"33","preset_record_min":"12","preset_record_max":"21","preset_record_rate_min":"56","preset_record_rate_max":"72","preset_segments":["3"],"title_prefix":"香港六合","title_middle":"","title_middle_wrap":"〖〗","author_nickname":"","author_nickname_pool":"","title_prefix_color_mode":"fixed","title_prefix_color_value":"#1F1F1F","title_middle_color_mode":"daily_random","title_middle_color_value":"#2563EB","author_nickname_color_mode":"fixed","author_nickname_color_value":"#1F1F1F","title_font_size":"14","title_font_weight":"500","target_zodiac":[],"target_number":[],"target_wave":[],"target_element":[],"target_head":[],"target_tail":[],"templates":["tema_3","zodiac_3"],"manage_templates":["kills_1","kills_2","kills_3","kills_4","kills_5","kills_6","kills_7","kills_8","kills_9","kills_10","kills_11","kills_12","kills_13"],"is_blank_content":"1","is_fake_after_open":"1","post_update_time":"23:59","material_content_time":"14:30"}';
        $settings = array(
            array('site.title', 'site', $siteName, 1),
            array('browser.title', 'site', $siteName, 1),
            array('browser.region_title_macau', 'site', '澳门', 1),
            array('browser.region_title_hongkong', 'site', '香港', 1),
            array('site.name', 'site', $siteName, 1),
            array('admin.browser_title', 'admin', '97128', 0),
            array('admin.management_name', 'admin', '97128摇奖机', 0),
            array('appearance.top_html', 'appearance', '', 0),
            array('appearance.middle_html', 'appearance', '', 0),
            array('appearance.bottom_html', 'appearance', '', 0),
            array('appearance.post_top_html', 'appearance', '', 0),
            array('appearance.post_bottom_html', 'appearance', '', 0),
            array('points.register_bonus', 'points', '88', 0),
            array('members.register_limit_days', 'members', '1', 0),
            array('cache.auto_clear', 'cache', '0', 0),
            array('cache.auto_clear_hours', 'cache', '24', 0),
            array('security.max_login_attempts', 'security', '5', 0),
            array('security.admin_session_minutes', 'security', '120', 0),
            array('forecast.participation_increment', 'forecast', '8', 1),
            array('forecast.analysis_period_min', 'forecast', '20', 1),
            array('forecast.analysis_period_max', 'forecast', '20', 1),
            array('forecast.analysis_period', 'forecast', '20', 1),
            array('forecast.member_daily_limit', 'forecast', '100', 1),
            array('post_lock.before_minutes', 'post_lock', '60', 0),
            array('post_lock.unlock_time', 'post_lock', '23:59', 0),
            array('post_generator.settings.macau', 'post_generator', $macauPostGeneratorSettings, 0),
            array('post_generator.settings.hongkong', 'post_generator', $hongkongPostGeneratorSettings, 0),
            array('service.welcome', 'service', '您好，这里是在线客服，请留言，我们会尽快处理。', 1),
            array('payment.wechat', 'payment', '请在后台填写微信收款说明', 0),
            array('payment.alipay', 'payment', '请在后台填写支付宝收款说明', 0),
        );

        foreach ($settings as $setting) {
            $exists = $database->fetch('SELECT id FROM settings WHERE setting_key = :setting_key LIMIT 1', array(
                'setting_key' => $setting[0],
            ));

            if ($exists) {
                $database->execute('UPDATE settings SET setting_group = :setting_group, setting_value = :setting_value, is_public = :is_public, updated_at = :updated_at WHERE id = :id', array(
                    'setting_group' => $setting[1],
                    'setting_value' => $setting[2],
                    'is_public' => $setting[3],
                    'updated_at' => $now,
                    'id' => $exists['id'],
                ));
                continue;
            }

            $database->execute('INSERT INTO settings (setting_key, setting_group, setting_value, is_public, created_at, updated_at) VALUES (:setting_key, :setting_group, :setting_value, :is_public, :created_at, :updated_at)', array(
                'setting_key' => $setting[0],
                'setting_group' => $setting[1],
                'setting_value' => $setting[2],
                'is_public' => $setting[3],
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }
    }

    protected function seedDraws(Database $database, $adminId)
    {
        $now = date('Y-m-d H:i:s');
        $sample = array(
            array('macau', '2026036', '2026-03-17', array(3, 8, 12, 18, 27, 41), 46),
            array('macau', '2026035', '2026-03-15', array(2, 6, 19, 24, 33, 42), 11),
            array('macau', '2026034', '2026-03-13', array(1, 9, 14, 21, 35, 44), 17),
            array('hongkong', '2026036', '2026-03-17', array(4, 7, 16, 22, 31, 43), 8),
            array('hongkong', '2026035', '2026-03-15', array(5, 11, 20, 25, 36, 45), 12),
            array('hongkong', '2026034', '2026-03-13', array(6, 10, 13, 29, 38, 47), 18),
        );

        foreach ($sample as $item) {
            $exists = $database->fetch('SELECT id FROM lottery_draws WHERE region = :region AND issue_no = :issue_no LIMIT 1', array(
                'region' => $item[0],
                'issue_no' => $item[1],
            ));

            if ($exists) {
                continue;
            }

            $database->execute('INSERT INTO lottery_draws (region, issue_no, draw_date, numbers_json, special_number, note, created_by, created_at, updated_at) VALUES (:region, :issue_no, :draw_date, :numbers_json, :special_number, :note, :created_by, :created_at, :updated_at)', array(
                'region' => $item[0],
                'issue_no' => $item[1],
                'draw_date' => $item[2],
                'numbers_json' => json_encode($item[3]),
                'special_number' => $item[4],
                'note' => '系统初始化示例数据',
                'created_by' => $adminId,
                'created_at' => $now,
                'updated_at' => $now,
            ));
        }
    }

    protected function seedPosts(Database $database, $adminId)
    {
        $countRow = $database->fetch('SELECT COUNT(*) AS total_count FROM posts');
        if ($countRow && (int) $countRow['total_count'] > 0) {
            return;
        }

        $actor = $this->seedPostAdminActor($database, $adminId);

        foreach (array('macau', 'hongkong') as $region) {
            $payload = $this->seedPostGeneratorPayload($database, $region);
            $this->app->admins()->generateManagedForumPosts($payload, $actor);
        }
    }

    protected function seedPostAdminActor(Database $database, $adminId)
    {
        $frontUser = $database->fetch('SELECT username FROM users WHERE id = :id LIMIT 1', array(
            'id' => (int) $adminId,
        ));
        $username = trim((string) ($frontUser['username'] ?? ''));

        $adminUser = null;
        if ($username !== '') {
            $adminUser = $database->fetch(
                'SELECT id, username FROM admin_users WHERE username = :username AND deleted_at IS NULL LIMIT 1',
                array('username' => $username)
            );
        }
        if (!$adminUser) {
            $adminUser = $database->fetch('SELECT id, username FROM admin_users WHERE deleted_at IS NULL ORDER BY is_super DESC, id ASC LIMIT 1');
        }

        if (!$adminUser) {
            throw new RuntimeException('后台管理员未初始化，无法生成初始帖子资料。');
        }

        return array(
            'id' => (int) $adminUser['id'],
            'username' => (string) $adminUser['username'],
        );
    }

    protected function seedPostGeneratorPayload(Database $database, $region)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $row = $database->fetch(
            'SELECT setting_value FROM settings WHERE setting_key = :setting_key LIMIT 1',
            array('setting_key' => 'post_generator.settings.' . $region)
        );

        $payload = array();
        $raw = trim((string) ($row['setting_value'] ?? ''));
        if ($raw !== '') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = $decoded;
            }
        }

        if (empty($payload['templates']) || !is_array($payload['templates'])) {
            $payload['templates'] = $region === 'hongkong'
                ? array('tema_3', 'zodiac_3')
                : array('tema_3', 'zodiac_3', 'zodiac_code_3');
        }

        $payload['region'] = $region;
        $payload['generator_type'] = $region;
        $payload['generator_run_mode'] = 'manual';

        return $payload;
    }

}
