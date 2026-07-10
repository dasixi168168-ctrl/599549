<?php
declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Session;

require_once dirname(__DIR__) . '/bootstrap/front_security.php';
front_security_apply(array(
    'rate_limit' => false,
    'output_buffer' => false,
    'cache_control' => 'no-store, no-cache, must-revalidate, max-age=0',
));

require dirname(__DIR__) . '/bootstrap/app.php';

ensure_installed_or_redirect();
app()->admins()->ensureReady();

$adminBaseUrl = public_url('admin.php');

$pushFlash = function ($type, $message) {
    Session::put('admin_flash', array(
        'type' => (string) $type,
        'message' => (string) $message,
    ));
};

$pullFlash = function () {
    $flash = Session::get('admin_flash');
    Session::forget('admin_flash');

    return is_array($flash) ? $flash : null;
};

$expectsJsonResponse = static function () {
    $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    $acceptHeader = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
    $responseFormat = strtolower((string) input('_response_format', ''));

    return $requestedWith === 'xmlhttprequest'
        || strpos($acceptHeader, 'application/json') !== false
        || $responseFormat === 'json';
};

$adminPostsReturnUrl = static function () use ($adminBaseUrl) {
    $postRedirectRegion = (string) input('region', isset($_GET['region']) ? (string) $_GET['region'] : 'macau');
    if ($postRedirectRegion !== 'hongkong') {
        $postRedirectRegion = 'macau';
    }

    $postRedirectView = (string) input('view', isset($_GET['view']) ? (string) $_GET['view'] : 'manage');
    if (!in_array($postRedirectView, array('manage', 'compose', 'published', 'recycle'), true)) {
        $postRedirectView = 'manage';
    }

    $postRedirectQuery = array(
        'page' => 'posts',
        'region' => $postRedirectRegion,
        'view' => $postRedirectView,
    );
    $postEditId = (int) input('id', input('edit', 0));
    if ($postRedirectView === 'compose' && $postEditId > 0) {
        $postRedirectQuery['edit'] = $postEditId;
    }

    return $adminBaseUrl . '?' . http_build_query($postRedirectQuery);
};

if (isset($_GET['logout']) && (string) $_GET['logout'] === '1') {
    app()->auth()->logoutAdmin();
    $pushFlash('success', '已退出登录。');
    redirect($adminBaseUrl);
}

if (!app()->auth()->checkAdminPortal()) {
    if ($expectsJsonResponse()) {
        json_response(array(
            'success' => false,
            'message' => '后台登录状态已失效，请重新登录后再操作。',
            'redirect' => $adminBaseUrl,
        ), 401);
    }

    $pageError = '';
    $flashMessage = $pullFlash();
    $loginNotice = isset($_GET['installed']) && (string) $_GET['installed'] === '1'
        ? 'Installation completed. Please sign in with an admin account.'
        : '';
    $oldLogin = array(
        'username' => '',
    );

    if (is_post() && (string) input('_admin_form', '') === 'login') {
        try {
            require_csrf('admin.login');
            $oldLogin['username'] = trim((string) input('username', ''));
            $loginPassword = (string) input('password', '');

            if ($oldLogin['username'] === '' || $loginPassword === '') {
                throw new \RuntimeException('请输入账号和密码。');
            }

            if (app()->admins()->findByUsername($oldLogin['username'])) {
                app()->admins()->attemptLogin(
                    $oldLogin['username'],
                    $loginPassword
                );
                $pushFlash('success', '登录成功。');
                redirect($adminBaseUrl);
            }

            try {
                app()->support()->loginAgent(
                    $oldLogin['username'],
                    $loginPassword
                );
                redirect(public_url('service.php') . '?region=macau&agent=1');
            } catch (\RuntimeException $agentException) {
                throw new \RuntimeException('账号或密码错误。');
            }
        } catch (\Throwable $exception) {
            if (!$exception instanceof \RuntimeException) {
                app()->admins()->writeManagedExceptionLog($exception, 'auth', 'admin_login', 'guest', 0);
            }
            $pageError = $exception->getMessage();
        }
    }

    view('admin/login', array(
        'pageTitle' => '后台登录 - ' . browser_title_setting(app()->config('app', 'site_name', '后台管理')),
        'pageError' => $pageError,
        'flashMessage' => $flashMessage,
        'loginNotice' => $loginNotice,
        'oldLogin' => $oldLogin,
    ), 'layouts/plain');
    return;
}
$currentAdmin = current_admin();
$page = isset($_GET['page']) ? trim((string) $_GET['page']) : 'dashboard';
$pageMap = array(
    'dashboard' => array(
        'permission' => 'dashboard.view',
        'title' => '仪表盘',
        'template' => 'admin/dashboard',
        'menu' => 'dashboard',
    ),
    'admins' => array(
        'permission' => 'admins.view',
        'title' => '管理员管理',
        'template' => 'admin/admins',
        'menu' => 'admins',
    ),
    'roles' => array(
        'permission' => 'roles.view',
        'title' => '角色权限',
        'template' => 'admin/roles',
        'menu' => 'roles',
    ),
    'settings' => array(
        'permission' => 'settings.view',
        'title' => '系统设置',
        'template' => 'admin/settings',
        'menu' => 'settings',
    ),
    'support' => array(
        'permission' => 'customer_service.view',
        'title' => '在线客服',
        'template' => 'admin/support',
        'menu' => 'support',
    ),
    'install' => array(
        'permission' => 'install.view',
        'title' => '当前安装快照',
        'template' => 'admin/install_info',
        'menu' => 'install',
    ),
    'uploads' => array(
        'permission' => 'uploads.view',
        'title' => '资料更新',
        'template' => 'admin/uploads',
        'menu' => 'uploads',
    ),
    'users' => array(
        'permission' => 'users.view',
        'title' => '会员管理',
        'template' => 'admin/users',
        'menu' => 'users',
    ),
    'posts' => array(
        'permission' => 'posts.view',
        'title' => '帖子管理',
        'template' => 'admin/posts_forum',
        'menu' => 'posts',
    ),
    'sections' => array(
        'permission' => 'sections.view',
        'title' => '板块管理',
        'template' => 'admin/sections',
        'menu' => 'sections',
    ),
    'categories' => array(
        'permission' => 'categories.view',
        'title' => '分类管理',
        'template' => 'admin/categories',
        'menu' => 'categories',
    ),
    'comments' => array(
        'permission' => 'comments.view',
        'title' => '评论管理',
        'template' => 'admin/comments',
        'menu' => 'comments',
    ),
    'interactions' => array(
        'permission' => 'interactions.view',
        'title' => '帖子互动',
        'template' => 'admin/interactions',
        'menu' => 'interactions',
    ),
    'reports' => array(
        'permission' => 'reports.view',
        'title' => '帖子举报',
        'template' => 'admin/reports',
        'menu' => 'reports',
    ),
    'audits' => array(
        'permission' => 'audits.view',
        'title' => '审核管理',
        'template' => 'admin/audits',
        'menu' => 'audits',
    ),
    'draws' => array(
        'permission' => 'draws.view',
        'title' => '开奖管理',
        'template' => 'admin/draws',
        'menu' => 'draws',
    ),
    'home' => array(
        'permission' => 'home.view',
        'title' => 'AI预测设置',
        'template' => 'admin/home',
        'menu' => 'home',
    ),
    'issues' => array(
        'permission' => 'issues.view',
        'title' => '期数管理',
        'template' => 'admin/issues',
        'menu' => 'issues',
    ),
    'login_logs' => array(
        'permission' => 'login_logs.view',
        'title' => '登录日志',
        'template' => 'admin/login_logs',
        'menu' => 'login_logs',
    ),
    'operation_logs' => array(
        'permission' => 'operation_logs.view',
        'title' => '操作日志',
        'template' => 'admin/operation_logs',
        'menu' => 'operation_logs',
    ),
    'exceptions' => array(
        'permission' => 'exceptions.view',
        'title' => '异常日志',
        'template' => 'admin/exceptions',
        'menu' => 'exceptions',
    ),
    'security' => array(
        'permission' => 'security.view',
        'title' => '安全策略',
        'template' => 'admin/security',
        'menu' => 'security',
    ),
);

if (!isset($pageMap[$page])) {
    $page = 'dashboard';
}

$pageConfig = $pageMap[$page];
app()->auth()->requireAdminPortal($pageConfig['permission'], $adminBaseUrl);

$pageError = '';
$postState = is_post() ? $_POST : array();

if ($page === 'posts' && !is_post() && (string) input('ajax', '') === 'category_options') {
    $categoryRegion = (string) input('region', 'macau');
    if ($categoryRegion !== 'hongkong') {
        $categoryRegion = 'macau';
    }

    $categories = app()->admins()->categoryOptions((int) input('section_id', 0), $categoryRegion);
    $payloadCategories = array();
    foreach ($categories as $category) {
        $payloadCategories[] = array(
            'id' => (int) ($category['id'] ?? 0),
            'section_id' => (int) ($category['section_id'] ?? 0),
            'name' => (string) ($category['name'] ?? ''),
            'region' => (string) ($category['region'] ?? $categoryRegion),
        );
    }

    json_response(array(
        'success' => true,
        'categories' => $payloadCategories,
        'data' => array(
            'categories' => $payloadCategories,
        ),
    ));
}

if ($page === 'draws' && is_post() && (string) input('_admin_action', '') === 'upload_draw_image') {
    app()->auth()->requireAdminPortal('draws.manage', $adminBaseUrl);

    if (!Csrf::validate((string) input('_token', ''), 'admin.draws')) {
        json_response(array(
            'success' => false,
            'message' => 'CSRF token expired. Please refresh and try again.',
        ), 419);
    }

    try {
        $savedUpload = app()->uploads()->saveUploadedFile('image_file', 'material', $currentAdmin, array(
            'max_size' => 5 * 1024 * 1024,
            'infer_image_extension' => true,
        ));

        app()->admins()->recordManagedOperation(
            (int) ($currentAdmin['id'] ?? 0),
            'draws',
            'upload',
            'image',
            (int) ($savedUpload['id'] ?? 0),
            'Upload draw image: ' . (string) ($savedUpload['file_name'] ?? '')
        );

        json_response(array(
            'success' => true,
            'location' => (string) ($savedUpload['file_path'] ?? ''),
            'file_name' => (string) ($savedUpload['file_name'] ?? ''),
            'width' => (int) ($savedUpload['image_width'] ?? 0),
            'height' => (int) ($savedUpload['image_height'] ?? 0),
        ));
    } catch (\Throwable $exception) {
        if (!$exception instanceof \RuntimeException) {
            app()->admins()->writeManagedExceptionLog($exception, 'draws', 'upload_draw_image', 'admin', (int) ($currentAdmin['id'] ?? 0));
        }

        json_response(array(
            'success' => false,
            'message' => $exception->getMessage(),
        ), $exception instanceof \RuntimeException ? 422 : 500);
    }
}

if (is_post() && (string) input('_admin_form', '') === 'page') {
    try {
        require_csrf('admin.' . $page);

        switch ($page) {
            case 'admins':
                $action = (string) input('_admin_action', '');
                if ($action === 'save_admin') {
                    app()->auth()->requireAdminPortal('admins.manage', $adminBaseUrl);
                    $savedAdmin = app()->admins()->saveAdmin($_POST, $currentAdmin);
                    $pushFlash('success', '管理员已保存。');
                    redirect($adminBaseUrl . '?page=admins&edit=' . (int) $savedAdmin['id']);
                }

                if ($action === 'toggle_admin_status') {
                    app()->auth()->requireAdminPortal('admins.manage', $adminBaseUrl);
                    app()->admins()->toggleAdminStatus((int) input('target_id', 0), $currentAdmin);
                    $pushFlash('success', '管理员状态已更新。');
                    redirect($adminBaseUrl . '?page=admins');
                }
                break;

            case 'roles':
                if ((string) input('_admin_action', '') === 'save_role') {
                    app()->auth()->requireAdminPortal('roles.manage', $adminBaseUrl);
                    $savedRole = app()->admins()->saveRole($_POST, $currentAdmin);
                    $pushFlash('success', '角色已保存。');
                    redirect($adminBaseUrl . '?page=roles&edit=' . (int) $savedRole['id']);
                }
                break;

            case 'settings':
                if ((string) input('_admin_action', '') === 'save_settings') {
                    app()->auth()->requireAdminPortal('settings.manage', $adminBaseUrl);
                    app()->admins()->saveSystemSettings($_POST, $currentAdmin);
                    $pushFlash('success', '系统设置已保存。');
                    redirect($adminBaseUrl . '?page=settings');
                }
                break;
    case 'users':
                $action = (string) input('_admin_action', '');
                if ($action === 'save_user') {
                    app()->auth()->requireAdminPortal('users.manage', $adminBaseUrl);
                    $savedUser = app()->admins()->saveManagedUser($_POST, $currentAdmin);
                    $pushFlash('success', '会员资料已保存。');
                    if ((string) input('return_to_list', '') === '1') {
                        $userRedirectQuery = array('page' => 'users', 'user_panel' => 'members');
                        $userKeyword = trim((string) input('keyword', ''));
                        if ($userKeyword !== '') {
                            $userRedirectQuery['keyword'] = $userKeyword;
                        }
                        redirect($adminBaseUrl . '?' . http_build_query($userRedirectQuery));
                    }
                    redirect($adminBaseUrl . '?page=users&edit=' . (int) $savedUser['id']);
                }

                if ($action === 'score_user') {
                    app()->auth()->requireAdminPortal('users.manage', $adminBaseUrl);
                    $scoreAmount = (int) input('score_amount', 0);
                    if ($scoreAmount === 0) {
                        throw new RuntimeException('调整积分不能为 0。');
                    }
                    app()->admins()->adjustManagedUserScore((int) input('target_id', 0), $scoreAmount, $currentAdmin, input('score_note', ''));
                    $pushFlash('success', '会员积分已调整。');
                    $userRedirectQuery = array('page' => 'users', 'user_panel' => 'consumption');
                    $userKeyword = trim((string) input('keyword', ''));
                    $userRole = trim((string) input('role_key', ''));
                    $userStatus = trim((string) input('status', ''));
                    $userResetStatus = trim((string) input('reset_status', ''));
                    if ($userKeyword !== '') {
                        $userRedirectQuery['keyword'] = $userKeyword;
                    }
                    if ($userRole !== '') {
                        $userRedirectQuery['role_key'] = $userRole;
                    }
                    if (in_array($userStatus, array('active', 'disabled'), true)) {
                        $userRedirectQuery['status'] = $userStatus;
                    }
                    if (in_array($userResetStatus, array('pending', 'processed'), true)) {
                        $userRedirectQuery['reset_status'] = $userResetStatus;
                    }
                    if ((int) input('edit', 0) > 0) {
                        $userRedirectQuery['edit'] = (int) input('edit', 0);
                    }
                    redirect($adminBaseUrl . '?' . http_build_query($userRedirectQuery));
                }

                if ($action === 'save_register_rules' || $action === 'save_register_bonus') {
                    app()->auth()->requireAdminPortal('users.manage', $adminBaseUrl);
                    $registerRules = app()->admins()->saveRegisterRuleSettings(
                        input('register_bonus', 88),
                        input('register_limit_days', 1),
                        input('invite_register_bonus', 0),
                        $currentAdmin
                    );
                    $pushFlash('success', '注册规则已保存：赠送 ' . (int) $registerRules['register_bonus'] . ' 积分，邀请奖励 ' . (int) $registerRules['invite_register_bonus'] . ' 积分，重复注册限制 ' . (int) $registerRules['register_limit_days'] . ' 天。');
                    $userRedirectQuery = array('page' => 'users', 'user_panel' => 'register_rules');
                    $userKeyword = trim((string) input('keyword', ''));
                    $userRole = trim((string) input('role_key', ''));
                    $userStatus = trim((string) input('status', ''));
                    $userResetStatus = trim((string) input('reset_status', ''));
                    if ($userKeyword !== '') {
                        $userRedirectQuery['keyword'] = $userKeyword;
                    }
                    if ($userRole !== '') {
                        $userRedirectQuery['role_key'] = $userRole;
                    }
                    if (in_array($userStatus, array('active', 'disabled'), true)) {
                        $userRedirectQuery['status'] = $userStatus;
                    }
                    if (in_array($userResetStatus, array('pending', 'processed'), true)) {
                        $userRedirectQuery['reset_status'] = $userResetStatus;
                    }
                    redirect($adminBaseUrl . '?' . http_build_query($userRedirectQuery));
                }

                if ($action === 'delete_user') {
                    app()->auth()->requireAdminPortal('users.manage', $adminBaseUrl);
                    app()->admins()->deleteManagedUser((int) input('target_id', 0), $currentAdmin);
                    $pushFlash('success', '会员已删除。');
                    $userRedirectQuery = array('page' => 'users', 'user_panel' => 'members');
                    $userKeyword = trim((string) input('keyword', ''));
                    if ($userKeyword !== '') {
                        $userRedirectQuery['keyword'] = $userKeyword;
                    }
                    redirect($adminBaseUrl . '?' . http_build_query($userRedirectQuery));
                }

                if ($action === 'batch_delete_users') {
                    app()->auth()->requireAdminPortal('users.manage', $adminBaseUrl);
                    $deletedUsers = app()->admins()->deleteManagedUsers(isset($_POST['target_ids']) && is_array($_POST['target_ids']) ? $_POST['target_ids'] : array(), $currentAdmin);
                    $pushFlash('success', '已删除 ' . count($deletedUsers) . ' 个会员。');
                    $userRedirectQuery = array('page' => 'users', 'user_panel' => 'members');
                    $userKeyword = trim((string) input('keyword', ''));
                    $userRole = trim((string) input('role_key', ''));
                    $userStatus = trim((string) input('status', ''));
                    $userResetStatus = trim((string) input('reset_status', ''));
                    if ($userKeyword !== '') {
                        $userRedirectQuery['keyword'] = $userKeyword;
                    }
                    if ($userRole !== '') {
                        $userRedirectQuery['role_key'] = $userRole;
                    }
                    if (in_array($userStatus, array('active', 'disabled'), true)) {
                        $userRedirectQuery['status'] = $userStatus;
                    }
                    if (in_array($userResetStatus, array('pending', 'processed'), true)) {
                        $userRedirectQuery['reset_status'] = $userResetStatus;
                    }
                    redirect($adminBaseUrl . '?' . http_build_query($userRedirectQuery));
                }

                if ($action === 'process_reset') {
                    app()->auth()->requireAdminPortal('users.manage', $adminBaseUrl);
                    app()->admins()->processManagedPasswordReset((int) input('request_id', 0), (string) input('new_password', ''), $currentAdmin);
                    $pushFlash('success', '密码已重置。');
                    redirect($adminBaseUrl . '?page=users&user_panel=members');
                }

                if ($action === 'save_ban') {
                    app()->auth()->requireAdminPortal('users.manage', $adminBaseUrl);
                    $targetUserId = (int) input('target_user_id', 0);
                    app()->admins()->saveManagedUserBan($targetUserId, $_POST, $currentAdmin);
                    $pushFlash('success', '封禁设置已保存。');
                    redirect($adminBaseUrl . '?page=users&user_panel=members&edit=' . $targetUserId);
                }

                if ($action === 'lift_ban') {
                    app()->auth()->requireAdminPortal('users.manage', $adminBaseUrl);
                    $targetUserId = (int) input('target_user_id', 0);
                    app()->admins()->liftManagedUserBan($targetUserId, $currentAdmin);
                    $pushFlash('success', '封禁已解除。');
                    redirect($adminBaseUrl . '?page=users&user_panel=members&edit=' . $targetUserId);
                }

                if ($action === 'save_vip') {
                    app()->auth()->requireAdminPortal('users.manage', $adminBaseUrl);
                    $targetUserId = (int) input('target_user_id', 0);
                    app()->admins()->saveManagedUserVip($targetUserId, $_POST, $currentAdmin);
                    $pushFlash('success', 'VIP 已保存。');
                    if ((string) input('return_to_list', '') === '1') {
                        $userRedirectQuery = array('page' => 'users', 'user_panel' => 'members');
                        $userKeyword = trim((string) input('keyword', ''));
                        if ($userKeyword !== '') {
                            $userRedirectQuery['keyword'] = $userKeyword;
                        }
                        redirect($adminBaseUrl . '?' . http_build_query($userRedirectQuery));
                    }
                    redirect($adminBaseUrl . '?page=users&user_panel=members&edit=' . $targetUserId);
                }
                break;

            case 'posts':
                $action = (string) input('_admin_action_override', input('_admin_action', ''));
                if ($action === 'save_post') {
                    app()->auth()->requireAdminPortal('posts.manage', $adminBaseUrl);
                    $savedPost = app()->admins()->saveManagedForumPost($_POST, $currentAdmin);
                    $postRedirectRegion = (string) input('region', (string) ($savedPost['region'] ?? 'macau'));
                    if ($postRedirectRegion !== 'hongkong') {
                        $postRedirectRegion = 'macau';
                    }
                    $pushFlash('success', '帖子已保存。');
                    $postRedirectView = (string) input('view', 'compose');
                    if (!in_array($postRedirectView, array('manage', 'compose', 'published', 'recycle'), true)) {
                        $postRedirectView = 'compose';
                    }
                    if ($expectsJsonResponse()) {
                        json_response(array(
                            'success' => true,
                            'message' => '帖子已保存。',
                            'redirect' => $adminBaseUrl . '?page=posts&region=' . urlencode($postRedirectRegion) . '&view=' . urlencode($postRedirectView) . '&edit=' . (int) $savedPost['id'],
                            'post' => array(
                                'id' => (int) ($savedPost['id'] ?? 0),
                                'title' => (string) ($savedPost['title'] ?? ''),
                                'status' => (string) ($savedPost['status'] ?? ''),
                            ),
                            'data' => array(
                                'id' => (int) ($savedPost['id'] ?? 0),
                                'title' => (string) ($savedPost['title'] ?? ''),
                                'status' => (string) ($savedPost['status'] ?? ''),
                            ),
                        ));
                    }
                    redirect($adminBaseUrl . '?page=posts&region=' . urlencode($postRedirectRegion) . '&view=' . urlencode($postRedirectView) . '&edit=' . (int) $savedPost['id']);
                }

                if ($action === 'generate_posts') {
                    app()->auth()->requireAdminPortal('posts.manage', $adminBaseUrl);
                    $generatorPayload = $_POST;
                    $generatorPayload['generator_run_mode'] = 'manual';
                    $generatorResult = app()->admins()->generateManagedForumPosts($generatorPayload, $currentAdmin);
                    $generatorSettingsPayload = $generatorPayload;
                    $generatorSettingsPayload['skip_schedule_sync'] = '1';
                    app()->admins()->saveManagedPostGeneratorSettings($generatorSettingsPayload, $currentAdmin);
                    $postRedirectRegion = (string) ($generatorResult['region'] ?? input('generator_type', input('region', 'macau')));
                    if ($postRedirectRegion !== 'hongkong') {
                        $postRedirectRegion = 'macau';
                    }
                    if ($expectsJsonResponse()) {
                        json_response(array(
                            'success' => true,
                            'message' => '',
                            'redirect' => $adminBaseUrl . '?page=posts&region=' . urlencode($postRedirectRegion) . '&view=compose',
                            'data' => array(
                                'count' => (int) ($generatorResult['count'] ?? 0),
                                'ids' => isset($generatorResult['ids']) && is_array($generatorResult['ids']) ? array_values(array_map('intval', $generatorResult['ids'])) : array(),
                                'summary_delta_counts' => array(
                                    'total_count' => (int) ($generatorResult['visible_count'] ?? $generatorResult['count'] ?? 0),
                                    'segment_1_count' => (int) ($generatorResult['segment_counts'][1] ?? 0),
                                    'segment_2_count' => (int) ($generatorResult['segment_counts'][2] ?? 0),
                                    'segment_3_count' => (int) ($generatorResult['segment_counts'][3] ?? 0),
                                ),
                            ),
                        ));
                    }
                    $pushFlash('success', '已生成 ' . (int) ($generatorResult['count'] ?? 0) . ' 篇帖子。');
                    redirect($adminBaseUrl . '?page=posts&region=' . urlencode($postRedirectRegion) . '&view=compose');
                }

                if ($action === 'save_post_generator_settings') {
                    app()->auth()->requireAdminPortal('posts.manage', $adminBaseUrl);
                    $generatorSettingsPayload = $_POST;
                    $generatorSettingsView = (string) input('view', 'compose');
                    $generatorMaterialStateOnly = (string) input('material_content_state_only', '') === '1';
                    if ($generatorMaterialStateOnly) {
                        $generatorSettingsRegion = (string) (
                            $_POST['generator_type'] ?? ($_POST['region'] ?? 'macau')
                        );
                        $generatorSettingsConfig = app()->admins()->managedPostGeneratorConfig($generatorSettingsRegion);
                        $generatorSettingsPayload = $generatorSettingsConfig;
                        $generatorSettingsPayload['region'] = $generatorSettingsRegion;
                        $generatorSettingsPayload['generator_type'] = $generatorSettingsRegion;
                        $generatorSettingsPayload['view'] = $generatorSettingsView;
                        $generatorSettingsPayload['is_blank_content'] = !empty($_POST['is_blank_content']) ? '1' : '';
                        $generatorSettingsPayload['material_content_state_only'] = '1';
                    } elseif ($generatorSettingsView === 'compose') {
                        $generatorSettingsRegion = (string) (
                            $_POST['generator_type'] ?? ($_POST['region'] ?? 'macau')
                        );
                        $generatorSettingsConfig = app()->admins()->managedPostGeneratorConfig($generatorSettingsRegion);
                        $generatorSettingsPayload = $generatorSettingsConfig;
                        foreach (array(
                            'region',
                            'generator_type',
                            'view',
                            'title_prefix',
                            'title_middle',
                            'title_middle_wrap',
                            'author_nickname',
                            'author_nickname_pool',
                            'title_prefix_color_mode',
                            'title_prefix_color_value',
                            'title_middle_color_mode',
                            'title_middle_color_value',
                            'author_nickname_color_mode',
                            'author_nickname_color_value',
                            'title_font_size',
                            'title_font_weight',
                        ) as $generatorSettingsKey) {
                            if (array_key_exists($generatorSettingsKey, $_POST)) {
                                $generatorSettingsPayload[$generatorSettingsKey] = $_POST[$generatorSettingsKey];
                            }
                        }
                        $generatorSettingsPayload['is_blank_content'] = !empty($generatorSettingsConfig['is_blank_content']) ? '1' : '';
                    }
                    $generatorSettings = app()->admins()->saveManagedPostGeneratorSettings($generatorSettingsPayload, $currentAdmin);
                    $postRedirectRegion = (string) ($generatorSettings['region'] ?? input('generator_type', input('region', 'macau')));
                    if ($postRedirectRegion !== 'hongkong') {
                        $postRedirectRegion = 'macau';
                    }
                    $postRedirectView = (string) input('view', 'compose');
                    if (!in_array($postRedirectView, array('manage', 'compose'), true)) {
                        $postRedirectView = 'compose';
                    }
                    $postGeneratorSilent = (string) input('_silent', '') === '1';
                    if (!$postGeneratorSilent) {
                        $pushFlash('success', '生成设置已保存。');
                    }
                    if ($expectsJsonResponse()) {
                        json_response(array(
                            'success' => true,
                            'message' => $postGeneratorSilent ? '' : '生成设置已保存。',
                            'redirect' => $adminBaseUrl . '?page=posts&region=' . urlencode($postRedirectRegion) . '&view=' . urlencode($postRedirectView),
                            'data' => $generatorSettings,
                        ));
                    }
                    redirect($adminBaseUrl . '?page=posts&region=' . urlencode($postRedirectRegion) . '&view=' . urlencode($postRedirectView));
                }

                if ($action === 'save_post_generation_mode') {
                    app()->auth()->requireAdminPortal('posts.manage', $adminBaseUrl);
                    $generatorMode = app()->admins()->saveManagedPostGenerationMode($_POST, $currentAdmin);
                    $postRedirectRegion = (string) ($generatorMode['region'] ?? input('generator_type', input('region', 'macau')));
                    if ($postRedirectRegion !== 'hongkong') {
                        $postRedirectRegion = 'macau';
                    }
                    if ($expectsJsonResponse()) {
                        json_response(array(
                            'success' => true,
                            'message' => '',
                            'redirect' => $adminBaseUrl . '?page=posts&region=' . urlencode($postRedirectRegion) . '&view=compose',
                            'data' => $generatorMode,
                        ));
                    }
                    redirect($adminBaseUrl . '?page=posts&region=' . urlencode($postRedirectRegion) . '&view=compose');
                }

                if ($action === 'post_quick_action') {
                    app()->auth()->requireAdminPortal('posts.manage', $adminBaseUrl);
                    $postRedirectRegion = (string) input('region', 'macau');
                    if ($postRedirectRegion !== 'hongkong') {
                        $postRedirectRegion = 'macau';
                    }
                    $postRedirectView = (string) input('view', 'manage');
                    if (!in_array($postRedirectView, array('manage', 'compose', 'published', 'recycle'), true)) {
                        $postRedirectView = 'manage';
                    }
                    $processedPost = app()->admins()->processManagedForumPostAction(
                        (int) input('target_post_id', 0),
                        (string) input('quick_action', ''),
                        $_POST,
                        $currentAdmin
                    );
                    $postActionMessage = (string) ($processedPost['_message'] ?? '帖子操作已完成。');
                    if ($expectsJsonResponse()) {
                        json_response(array(
                            'success' => true,
                            'message' => $postActionMessage,
                            'redirect' => $adminBaseUrl . '?page=posts&region=' . urlencode($postRedirectRegion) . '&view=' . urlencode($postRedirectView),
                            'post_id' => (int) input('target_post_id', 0),
                            'data' => array(
                                'id' => (int) ($processedPost['id'] ?? input('target_post_id', 0)),
                                'status' => (string) ($processedPost['status'] ?? ''),
                            ),
                        ));
                    }
                    redirect($adminBaseUrl . '?page=posts&region=' . urlencode($postRedirectRegion) . '&view=' . urlencode($postRedirectView));
                }

                if ($action === 'bulk_posts') {
                    app()->auth()->requireAdminPortal('posts.manage', $adminBaseUrl);
                    $selectedIds = isset($_POST['selected_ids']) && is_array($_POST['selected_ids']) ? array_map('intval', $_POST['selected_ids']) : array();
                    $bulkPostResult = app()->admins()->bulkManagedPosts($selectedIds, (string) input('bulk_action', ''), (string) input('bulk_value', ''), $currentAdmin);
                    $postRedirectRegion = (string) input('region', 'macau');
                    if ($postRedirectRegion !== 'hongkong') {
                        $postRedirectRegion = 'macau';
                    }
                    $bulkPostMessage = (string) ($bulkPostResult['message'] ?? '批量操作已完成。');
                    $postRedirectView = (string) input('view', 'manage');
                    if (!in_array($postRedirectView, array('manage', 'compose', 'published', 'recycle'), true)) {
                        $postRedirectView = 'manage';
                    }
                    if ($expectsJsonResponse()) {
                        json_response(array(
                            'success' => true,
                            'message' => $bulkPostMessage,
                            'redirect' => $adminBaseUrl . '?page=posts&region=' . urlencode($postRedirectRegion) . '&view=' . urlencode($postRedirectView),
                            'selected_ids' => $selectedIds,
                            'data' => array(
                                'selected_ids' => array_values($selectedIds),
                                'result' => $bulkPostResult,
                            ),
                        ));
                    }
                    $pushFlash('success', $bulkPostMessage);
                    redirect($adminBaseUrl . '?page=posts&region=' . urlencode($postRedirectRegion) . '&view=' . urlencode($postRedirectView));
                }
                $postUnknownActionMessage = '帖子操作未识别，请刷新页面后重试。';
                if ($expectsJsonResponse()) {
                    json_response(array(
                        'success' => false,
                        'message' => $postUnknownActionMessage,
                    ), 422);
                }
                $pushFlash('error', $postUnknownActionMessage);
                redirect($adminPostsReturnUrl());
                break;

            case 'sections':
                if ((string) input('_admin_action', '') === 'save_section') {
                    app()->auth()->requireAdminPortal('sections.manage', $adminBaseUrl);
                    $savedSection = app()->admins()->saveManagedSection($_POST, $currentAdmin);
                    $pushFlash('success', '板块已保存。');
                    redirect($adminBaseUrl . '?page=sections&edit=' . (int) $savedSection['id']);
                }
                break;

            case 'categories':
                if ((string) input('_admin_action', '') === 'save_category') {
                    app()->auth()->requireAdminPortal('categories.manage', $adminBaseUrl);
                    $savedCategory = app()->admins()->saveManagedCategory($_POST, $currentAdmin);
                    $pushFlash('success', '分类已保存。');
                    redirect($adminBaseUrl . '?page=categories&edit=' . (int) $savedCategory['id']);
                }
                break;

            case 'comments':
                if ((string) input('_admin_action', '') === 'save_comment_status') {
                    app()->auth()->requireAdminPortal('comments.manage', $adminBaseUrl);
                    app()->admins()->setManagedCommentStatus((int) input('comment_id', 0), (string) input('comment_status', ''), $currentAdmin);
                    $pushFlash('success', '评论状态已更新。');
                    redirect($adminBaseUrl . '?page=comments');
                }
                break;

            case 'interactions':
                if ((string) input('_admin_action', '') === 'save_interaction') {
                    app()->auth()->requireAdminPortal('interactions.manage', $adminBaseUrl);
                    $savedInteraction = app()->admins()->saveManagedPostInteraction($_POST, $currentAdmin);
                    $pushFlash('success', '互动配置已保存。');
                    redirect($adminBaseUrl . '?page=interactions&edit=' . (int) $savedInteraction['id']);
                }
                break;

            case 'reports':
                $action = (string) input('_admin_action', '');
                if ($action === 'save_report') {
                    app()->auth()->requireAdminPortal('reports.manage', $adminBaseUrl);
                    $savedReport = app()->admins()->saveManagedPostReport($_POST, $currentAdmin);
                    $pushFlash('success', '举报记录已保存。');
                    redirect($adminBaseUrl . '?page=reports&edit=' . (int) $savedReport['id']);
                }

                if ($action === 'save_report_status') {
                    app()->auth()->requireAdminPortal('reports.manage', $adminBaseUrl);
                    app()->admins()->processManagedPostReportStatus(
                        (int) input('report_id', 0),
                        (string) input('report_status', ''),
                        (string) input('handle_result', ''),
                        $currentAdmin,
                        (string) input('punish_action', 'none')
                    );
                    $pushFlash('success', '举报处理已完成。');
                    redirect($adminBaseUrl . '?page=reports');
                }
                break;

            case 'audits':
                if ((string) input('_admin_action', '') === 'process_audit') {
                    app()->auth()->requireAdminPortal('audits.manage', $adminBaseUrl);
                    app()->admins()->processManagedAudit($_POST, $currentAdmin);
                    $pushFlash('success', '审核处理已完成。');
                    redirect($adminBaseUrl . '?page=audits');
                }
                break;

            case 'draws':
                $drawAction = (string) input('_admin_action', '');
                $drawImagesRedirect = static function () use ($adminBaseUrl) {
                    return $adminBaseUrl . '?page=draws&mode=images';
                };

                if ($drawAction === 'upload_draw_library_image') {
                    app()->auth()->requireAdminPortal('draws.manage', $adminBaseUrl);
                    $uploadBusinessType = trim((string) input('upload_business_type', 'site'));
                    if (!isset(app()->uploads()->businessTypeOptions()[$uploadBusinessType])) {
                        $uploadBusinessType = 'site';
                    }
                    $savedUpload = app()->uploads()->saveUploadedFile('draw_image_file', $uploadBusinessType, $currentAdmin, array(
                        'max_size' => 5 * 1024 * 1024,
                        'infer_image_extension' => true,
                    ));
                    app()->admins()->recordManagedOperation(
                        (int) ($currentAdmin['id'] ?? 0),
                        'draws',
                        'upload_image_library',
                        'upload',
                        (int) ($savedUpload['id'] ?? 0),
                        '上传图片管理资源：' . (string) ($savedUpload['file_name'] ?? '')
                    );
                    $pushFlash('success', '图片已上传。');
                    redirect($drawImagesRedirect());
                }

                if ($drawAction === 'delete_draw_library_image') {
                    app()->auth()->requireAdminPortal('draws.manage', $adminBaseUrl);
                    $deletedUpload = app()->uploads()->deleteUploadedImage((int) input('upload_id', 0), $currentAdmin);
                    app()->admins()->recordManagedOperation(
                        (int) ($currentAdmin['id'] ?? 0),
                        'draws',
                        'delete_image_library',
                        'upload',
                        (int) ($deletedUpload['id'] ?? 0),
                        '删除图片管理资源：' . (string) ($deletedUpload['file_name'] ?? '')
                    );
                    $pushFlash('success', '图片已删除。');
                    redirect($drawImagesRedirect());
                }

                if ($drawAction === 'delete_draw_library_images') {
                    app()->auth()->requireAdminPortal('draws.manage', $adminBaseUrl);
                    $rawUploadIds = $_POST['upload_ids'] ?? array();
                    if (!is_array($rawUploadIds)) {
                        $rawUploadIds = array($rawUploadIds);
                    }

                    $uploadIds = array();
                    foreach ($rawUploadIds as $rawUploadId) {
                        $uploadId = (int) $rawUploadId;
                        if ($uploadId > 0) {
                            $uploadIds[$uploadId] = $uploadId;
                        }
                    }
                    $uploadIds = array_values($uploadIds);

                    if (!$uploadIds) {
                        $pushFlash('error', '请选择要删除的图片。');
                        redirect($drawImagesRedirect());
                    }

                    $deletedUploads = array();
                    $failedCount = 0;
                    foreach ($uploadIds as $uploadId) {
                        try {
                            $deletedUploads[] = app()->uploads()->deleteUploadedImage((int) $uploadId, $currentAdmin);
                        } catch (\Throwable $deleteException) {
                            $failedCount++;
                            app()->admins()->writeManagedExceptionLog($deleteException, 'draws', 'delete_image_library_batch', 'admin', (int) ($currentAdmin['id'] ?? 0));
                        }
                    }

                    $deletedCount = count($deletedUploads);
                    if ($deletedCount <= 0) {
                        $pushFlash('error', '选中的图片未能删除，请确认图片是否仍存在。');
                        redirect($drawImagesRedirect());
                    }

                    $deletedNames = array();
                    foreach (array_slice($deletedUploads, 0, 3) as $deletedUpload) {
                        $deletedName = trim((string) ($deletedUpload['file_name'] ?? ''));
                        if ($deletedName !== '') {
                            $deletedNames[] = $deletedName;
                        }
                    }
                    app()->admins()->recordManagedOperation(
                        (int) ($currentAdmin['id'] ?? 0),
                        'draws',
                        'batch_delete_image_library',
                        'upload',
                        0,
                        '批量删除图片管理资源：' . $deletedCount . ' 张' . ($deletedNames ? '（' . implode('、', $deletedNames) . ($deletedCount > count($deletedNames) ? '等' : '') . '）' : '')
                    );

                    $pushFlash('success', $failedCount > 0 ? ('已删除 ' . $deletedCount . ' 张图片，' . $failedCount . ' 张未能删除。') : ('已删除 ' . $deletedCount . ' 张图片。'));
                    redirect($drawImagesRedirect());
                }

                if ($drawAction === 'save_draw_material') {
                    app()->auth()->requireAdminPortal('draws.manage', $adminBaseUrl);
                    $savedMaterial = app()->admins()->saveManagedDrawMaterialEditor($_POST, $currentAdmin);
                    if ($expectsJsonResponse()) {
                        json_response(array(
                            'success' => true,
                            'message' => '开奖素材已保存。',
                            'redirect' => $adminBaseUrl . '?page=draws&mode=material&region=' . urlencode((string) ($savedMaterial['region'] ?? 'macau')),
                            'content_html' => '',
                        ));
                    }
                    $pushFlash('success', '开奖素材已保存。');
                    redirect($adminBaseUrl . '?page=draws&mode=material&region=' . urlencode((string) ($savedMaterial['region'] ?? 'macau')));
                }
                if ($drawAction === 'save_draw_component') {
                    app()->auth()->requireAdminPortal('draws.manage', $adminBaseUrl);
                    $savedComponent = app()->admins()->saveManagedDrawComponentEditor($_POST, $currentAdmin);
                    if ($expectsJsonResponse()) {
                        json_response(array(
                            'success' => true,
                            'message' => '开奖组件已保存。',
                            'redirect' => $adminBaseUrl . '?page=draws&mode=component&region=' . urlencode((string) input('region', 'macau')),
                            'content_html' => '',
                        ));
                    }
                    $pushFlash('success', '开奖组件已保存。');
                    redirect($adminBaseUrl . '?page=draws&mode=component&region=' . urlencode((string) input('region', 'macau')));
                }
                break;

            case 'home':
                $action = (string) input('_admin_action', '');
                if ($action === 'save_forecast_pricing') {
                    app()->auth()->requireAdminPortal('home.manage', $adminBaseUrl);
                    app()->admins()->saveForecastPricingSettings($_POST, $currentAdmin);
                    $pushFlash('success', 'AI预测设置已保存。');
                    redirect($adminBaseUrl . '?page=home');
                }

                if ($action === 'save_home_settings') {
                    app()->auth()->requireAdminPortal('home.manage', $adminBaseUrl);
                    app()->admins()->saveHomepageSettings($_POST, $currentAdmin);
                    $pushFlash('success', '首页设置已保存。');
                    redirect($adminBaseUrl . '?page=home');
                }

                if ($action === 'save_notice') {
                    app()->auth()->requireAdminPortal('home.manage', $adminBaseUrl);
                    $savedNotice = app()->admins()->saveManagedNotice($_POST, $currentAdmin);
                    $pushFlash('success', '公告已保存。');
                    redirect($adminBaseUrl . '?page=home&edit_notice=' . (int) $savedNotice['id']);
                }

                if ($action === 'save_banner') {
                    app()->auth()->requireAdminPortal('home.manage', $adminBaseUrl);
                    $uploadedBanner = null;
                    if (app()->uploads()->hasUploadedFile('banner_image_file')) {
                        $uploadedBanner = app()->uploads()->saveUploadedFile('banner_image_file', 'banner', $currentAdmin);
                    }
                    $savedBanner = app()->admins()->saveManagedHomeBanner(array(
                        'id' => isset($_POST['id']) ? (int) $_POST['id'] : 0,
                        'region' => isset($_POST['banner_region']) ? (string) $_POST['banner_region'] : 'all',
                        'title' => isset($_POST['banner_title']) ? (string) $_POST['banner_title'] : '',
                        'image_url' => $uploadedBanner ? (string) ($uploadedBanner['file_path'] ?? '') : (isset($_POST['banner_image_url']) ? (string) $_POST['banner_image_url'] : ''),
                        'link_url' => isset($_POST['banner_link_url']) ? (string) $_POST['banner_link_url'] : '',
                        'open_type' => isset($_POST['banner_open_type']) ? (string) $_POST['banner_open_type'] : '_self',
                        'sort_order' => isset($_POST['banner_sort_order']) ? (string) $_POST['banner_sort_order'] : '10',
                        'status' => isset($_POST['banner_status']) ? (string) $_POST['banner_status'] : '1',
                        'start_at' => isset($_POST['banner_start_at']) ? (string) $_POST['banner_start_at'] : '',
                        'end_at' => isset($_POST['banner_end_at']) ? (string) $_POST['banner_end_at'] : '',
                    ), $currentAdmin);
                    $pushFlash('success', '轮播图已保存。');
                    redirect($adminBaseUrl . '?page=home&edit_banner=' . (int) $savedBanner['id']);
                }

                if ($action === 'save_top_entry') {
                    app()->auth()->requireAdminPortal('home.manage', $adminBaseUrl);
                    $savedEntry = app()->admins()->saveManagedHomeTopEntry(array(
                        'id' => isset($_POST['id']) ? (int) $_POST['id'] : 0,
                        'region' => isset($_POST['entry_region']) ? (string) $_POST['entry_region'] : 'all',
                        'title' => isset($_POST['entry_title']) ? (string) $_POST['entry_title'] : '',
                        'icon' => isset($_POST['entry_icon']) ? (string) $_POST['entry_icon'] : 'fa-solid fa-download',
                        'link_url' => isset($_POST['entry_link_url']) ? (string) $_POST['entry_link_url'] : '',
                        'target' => isset($_POST['entry_target']) ? (string) $_POST['entry_target'] : '_self',
                        'sort_order' => isset($_POST['entry_sort_order']) ? (string) $_POST['entry_sort_order'] : '10',
                        'status' => isset($_POST['entry_status']) ? (string) $_POST['entry_status'] : '1',
                    ), $currentAdmin);
                    $pushFlash('success', '顶部入口已保存。');
                    redirect($adminBaseUrl . '?page=home&edit_entry=' . (int) $savedEntry['id']);
                }

                if ($action === 'save_card') {
                    app()->auth()->requireAdminPortal('home.manage', $adminBaseUrl);
                    $savedCard = app()->admins()->saveManagedHomeCard(array(
                        'id' => isset($_POST['id']) ? (int) $_POST['id'] : 0,
                        'region' => isset($_POST['card_region']) ? (string) $_POST['card_region'] : 'all',
                        'position_code' => isset($_POST['card_position_code']) ? (string) $_POST['card_position_code'] : '',
                        'title_override' => isset($_POST['card_title_override']) ? (string) $_POST['card_title_override'] : '',
                        'sort_order' => isset($_POST['card_sort_order']) ? (string) $_POST['card_sort_order'] : '10',
                        'status' => isset($_POST['card_status']) ? (string) $_POST['card_status'] : '1',
                    ), $currentAdmin);
                    $pushFlash('success', '卡片配置已保存。');
                    redirect($adminBaseUrl . '?page=home&edit_card=' . (int) $savedCard['id']);
                }

                if ($action === 'save_ad_slot') {
                    app()->auth()->requireAdminPortal('home.manage', $adminBaseUrl);
                    $savedAdSlot = app()->admins()->saveManagedAdSlot(array(
                        'id' => isset($_POST['id']) ? (int) $_POST['id'] : 0,
                        'region' => isset($_POST['ad_region']) ? (string) $_POST['ad_region'] : 'all',
                        'slot_code' => isset($_POST['ad_slot_code']) ? (string) $_POST['ad_slot_code'] : '',
                        'title' => isset($_POST['ad_title']) ? (string) $_POST['ad_title'] : '',
                        'link_url' => isset($_POST['ad_link_url']) ? (string) $_POST['ad_link_url'] : '',
                        'sort_order' => isset($_POST['ad_sort_order']) ? (string) $_POST['ad_sort_order'] : '10',
                        'status' => isset($_POST['ad_status']) ? (string) $_POST['ad_status'] : '1',
                    ), $currentAdmin);
                    $pushFlash('success', '广告位已保存。');
                    redirect($adminBaseUrl . '?page=home&edit_ad=' . (int) $savedAdSlot['id']);
                }

                if ($action === 'save_module') {
                    app()->auth()->requireAdminPortal('home.manage', $adminBaseUrl);
                    app()->admins()->saveManagedHomeModule(array(
                        'module_key' => isset($_POST['module_key']) ? (string) $_POST['module_key'] : '',
                        'region' => isset($_POST['module_region']) ? (string) $_POST['module_region'] : 'all',
                        'sort_order' => isset($_POST['module_sort_order']) ? (string) $_POST['module_sort_order'] : '10',
                        'is_enabled' => isset($_POST['module_is_enabled']) ? (string) $_POST['module_is_enabled'] : '0',
                    ), $currentAdmin);
                    $pushFlash('success', '首页模块已保存。');
                    redirect($adminBaseUrl . '?page=home');
                }
                break;

            case 'uploads':
                if ((string) input('_admin_action', '') === 'upload_file') {
                    app()->auth()->requireAdminPortal('uploads.manage', $adminBaseUrl);
                    $savedUpload = app()->uploads()->saveUploadedFile(
                        'upload_file',
                        (string) input('upload_business_type', 'general'),
                        $currentAdmin
                    );
                    app()->admins()->recordManagedOperation((int) $currentAdmin['id'], 'uploads', 'upload', 'upload', (int) ($savedUpload['id'] ?? 0), 'Uploaded file: ' . (string) ($savedUpload['file_name'] ?? ''));
                    $pushFlash('success', '文件上传成功。');
                    redirect($adminBaseUrl . '?page=uploads');
                }
                break;

            case 'issues':
                if ((string) input('_admin_action', '') === 'save_issue') {
                    app()->auth()->requireAdminPortal('issues.manage', $adminBaseUrl);
                    $savedIssue = app()->admins()->saveManagedIssue($_POST, $currentAdmin);
                    $pushFlash('success', '期数已保存。');
                    redirect($adminBaseUrl . '?page=issues&edit=' . (int) $savedIssue['id']);
                }
                break;

            case 'security':
                if ((string) input('_admin_action', '') === 'save_security') {
                    app()->auth()->requireAdminPortal('security.manage', $adminBaseUrl);
                    app()->admins()->saveSecuritySettings($_POST, $currentAdmin);
                    $pushFlash('success', '安全设置已保存。');
                    redirect($adminBaseUrl . '?page=security');
                }
                break;
        }
    } catch (\Throwable $exception) {
        if (!$exception instanceof \RuntimeException) {
            app()->admins()->writeManagedExceptionLog($exception, 'admin', 'page_submit:' . $page, 'admin', (int) ($currentAdmin['id'] ?? 0));
        }
        $pageError = $exception->getMessage();

        if (($page === 'draws' || $page === 'posts') && $expectsJsonResponse()) {
            json_response(array(
                'success' => false,
                'message' => $pageError,
            ), $exception instanceof \RuntimeException ? 422 : 500);
        }

        if ($page === 'posts') {
            $pushFlash('error', $pageError !== '' ? $pageError : '帖子操作失败，请刷新页面后重试。');
            redirect($adminPostsReturnUrl());
        }
    }
}

$flashMessage = $pullFlash();
$viewData = array(
    'pageTitle' => $pageConfig['title'] . ' - ' . browser_title_setting(app()->config('app', 'site_name', '后台管理')),
    'pageHeading' => $pageConfig['title'],
    'currentAdmin' => $currentAdmin,
    'adminMenuItems' => app()->admins()->menuItemsFor($currentAdmin),
    'activeMenuCode' => $pageConfig['menu'],
    'pageError' => $pageError,
    'flashMessage' => $flashMessage,
);

if ($page === 'posts' && !is_post() && app()->auth()->adminCan('posts.manage')) {
    $postsScheduleView = isset($_GET['view']) ? trim((string) $_GET['view']) : 'manage';
    if ($postsScheduleView === '') {
        $postsScheduleView = 'manage';
    }
}

if ($page === 'posts' && !is_post() && !is_prefetch_request() && app()->auth()->adminCan('posts.manage') && $postsScheduleView === 'manage') {
    try {
        $postScheduleCheckKey = 'admin_posts_manage_schedule_checked_at';
        $postScheduleCheckedAt = (int) app()->cache()->get($postScheduleCheckKey, 0, 20);
        if ($postScheduleCheckedAt <= 0) {
            app()->cache()->put($postScheduleCheckKey, time());
            app()->admins()->runManagedPostGeneratorSchedule($currentAdmin);
        }
    } catch (\Throwable $exception) {
        app()->admins()->writeManagedExceptionLog($exception, 'posts', 'post_generator_schedule', 'admin', (int) ($currentAdmin['id'] ?? 0));
    }
}

switch ($page) {
    case 'dashboard':
        $viewData['stats'] = app()->admins()->dashboardStats();
        $viewData['recentLoginLogs'] = app()->admins()->recentLoginLogs(8);
        $viewData['recentOperationLogs'] = app()->admins()->recentOperationLogs(8);
        break;

    case 'admins':
        $editingAdminId = isset($postState['id']) ? (int) $postState['id'] : (isset($_GET['edit']) ? (int) $_GET['edit'] : 0);
        $viewData['admins'] = app()->admins()->listAdmins();
        $editingAdmin = $editingAdminId > 0 ? app()->admins()->findById($editingAdminId) : null;
        $viewData['roles'] = app()->admins()->listRoles();
        $viewData['editingAdmin'] = $editingAdmin;
        $viewData['adminCanManage'] = app()->auth()->adminCan('admins.manage');
        $viewData['adminForm'] = array(
            'id' => isset($postState['id']) ? (int) $postState['id'] : ($editingAdmin ? (int) $editingAdmin['id'] : 0),
            'username' => isset($postState['username']) ? (string) $postState['username'] : ($editingAdmin ? (string) $editingAdmin['username'] : ''),
            'real_name' => isset($postState['real_name']) ? (string) $postState['real_name'] : ($editingAdmin ? (string) $editingAdmin['real_name'] : ''),
            'nickname' => isset($postState['nickname']) ? (string) $postState['nickname'] : ($editingAdmin ? (string) $editingAdmin['nickname'] : ''),
            'mobile' => isset($postState['mobile']) ? (string) $postState['mobile'] : ($editingAdmin ? (string) $editingAdmin['mobile'] : ''),
            'email' => isset($postState['email']) ? (string) $postState['email'] : ($editingAdmin ? (string) $editingAdmin['email'] : ''),
            'role_id' => isset($postState['role_id']) ? (int) $postState['role_id'] : ($editingAdmin ? (int) $editingAdmin['role_id'] : 0),
            'status' => isset($postState['status']) ? (string) $postState['status'] : ($editingAdmin ? (string) $editingAdmin['status'] : '1'),
            'remark' => isset($postState['remark']) ? (string) $postState['remark'] : ($editingAdmin ? (string) $editingAdmin['remark'] : ''),
        );
        break;

    case 'roles':
        $editingRoleId = isset($postState['id']) ? (int) $postState['id'] : (isset($_GET['edit']) ? (int) $_GET['edit'] : 0);
        $editingRole = $editingRoleId > 0 ? app()->admins()->roleById($editingRoleId) : null;
        $viewData['roles'] = app()->admins()->listRoles();
        $viewData['permissions'] = app()->admins()->listPermissions();
        $viewData['roleCanManage'] = app()->auth()->adminCan('roles.manage');
        $viewData['roleForm'] = array(
            'id' => isset($postState['id']) ? (int) $postState['id'] : ($editingRole ? (int) $editingRole['id'] : 0),
            'name' => isset($postState['name']) ? (string) $postState['name'] : ($editingRole ? (string) $editingRole['name'] : ''),
            'code' => isset($postState['code']) ? (string) $postState['code'] : ($editingRole ? (string) $editingRole['code'] : ''),
            'description' => isset($postState['description']) ? (string) $postState['description'] : ($editingRole ? (string) $editingRole['description'] : ''),
            'data_scope' => isset($postState['data_scope']) ? (string) $postState['data_scope'] : ($editingRole ? (string) $editingRole['data_scope'] : 'all'),
            'status' => isset($postState['status']) ? (string) $postState['status'] : ($editingRole ? (string) $editingRole['status'] : '1'),
            'sort_order' => isset($postState['sort_order']) ? (string) $postState['sort_order'] : ($editingRole ? (string) $editingRole['sort_order'] : '0'),
        );
        break;

    case 'settings':
        $viewData['settingsCanManage'] = app()->auth()->adminCan('settings.manage');
        if ($viewData['settingsCanManage']) {
            $viewData['pageTitleActionHtml'] = '<button class="admin-button" type="submit" form="admin-settings-page-form">保存设置</button>';
        }
        $viewData['settingsForm'] = array(
            'site_name' => isset($postState['site_name']) ? (string) $postState['site_name'] : site_setting('site.name', app()->config('app', 'site_name', '888888 Forum')),
            'site_title' => isset($postState['site_title']) ? (string) $postState['site_title'] : site_setting('browser.title', site_setting('site.title', app()->config('app', 'site_name', '888888 Forum'))),
            'browser_region_title_macau' => isset($postState['browser_region_title_macau']) ? (string) $postState['browser_region_title_macau'] : browser_region_title_setting('macau', 'Macau Forum'),
            'browser_region_title_hongkong' => isset($postState['browser_region_title_hongkong']) ? (string) $postState['browser_region_title_hongkong'] : browser_region_title_setting('hongkong', 'Hong Kong Forum'),
            'admin_browser_title' => isset($postState['admin_browser_title']) ? (string) $postState['admin_browser_title'] : admin_browser_title_setting(site_setting('browser.title', site_setting('site.title', app()->config('app', 'site_name', '888888 Forum')))),
            'admin_management_name' => isset($postState['admin_management_name']) ? (string) $postState['admin_management_name'] : admin_management_name_setting(site_setting('site.name', app()->config('app', 'site_name', '888888 Forum'))),
        );
        break;

    case 'support':
        $customerServiceStatus = isset($_GET['status']) ? trim((string) $_GET['status']) : 'all';
        if (!in_array($customerServiceStatus, array('all', 'unread', 'waiting', 'open', 'closed'), true)) {
            $customerServiceStatus = 'all';
        }
        $customerServiceSessionId = isset($_GET['session_id']) ? (int) $_GET['session_id'] : 0;
        $customerServiceEditingAgentId = isset($_GET['edit_agent']) ? (int) $_GET['edit_agent'] : 0;
        $customerServiceAddingAgent = (string) ($_GET['add_agent'] ?? '') === '1';
        $customerServiceView = isset($_GET['support_view']) ? trim((string) $_GET['support_view']) : '';
        if (in_array($customerServiceView, array('agent_form', 'agent_list'), true)) {
            $customerServiceView = 'agents';
        }
        if (!in_array($customerServiceView, array('supervision', 'agents'), true)) {
            $customerServiceView = $customerServiceEditingAgentId > 0 || $customerServiceAddingAgent ? 'agents' : 'supervision';
        }
        $viewData['customerServiceFilters'] = array(
            'status' => $customerServiceStatus,
            'view' => $customerServiceView,
        );
        $viewData['customerServicePayload'] = app()->support()->managementPayload(
            $customerServiceStatus,
            $currentAdmin,
            $customerServiceSessionId,
            $customerServiceView === 'supervision'
        );
        $viewData['customerServiceCanManage'] = app()->auth()->adminCan('customer_service.manage');
        $viewData['customerServiceEditingAgent'] = $customerServiceEditingAgentId > 0
            ? app()->support()->agentForManagement($customerServiceEditingAgentId)
            : null;
        $viewData['customerServiceAddingAgent'] = $customerServiceAddingAgent;
        break;

    case 'users':
        $userFilters = array(
            'keyword' => isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '',
            'role_key' => isset($_GET['role_key']) ? trim((string) $_GET['role_key']) : '',
            'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
            'reset_status' => isset($_GET['reset_status']) ? trim((string) $_GET['reset_status']) : '',
            'page_no' => isset($_GET['page_no']) ? max(1, (int) $_GET['page_no']) : 1,
        );
        $userPanel = isset($_GET['user_panel']) ? trim((string) $_GET['user_panel']) : 'members';
        if (!in_array($userPanel, array('register_rules', 'members', 'consumption'), true)) {
            $userPanel = 'members';
        }
        $managedUserPage = $userPanel === 'members'
            ? app()->admins()->listManagedUsersPage($userFilters)
            : array('items' => array(), 'total' => 0, 'page_no' => 1, 'per_page' => 40, 'page_count' => 1);
        $viewData['userFilters'] = $userFilters;
        $viewData['userPanel'] = $userPanel;
        $viewData['userPage'] = $managedUserPage;
        $viewData['users'] = $userPanel === 'members' ? (array) ($managedUserPage['items'] ?? array()) : array();
        $viewData['consumptionRecords'] = $userPanel === 'consumption' ? app()->admins()->listManagedUserConsumptionRecords(80) : array();
        $viewData['userRoles'] = $userPanel === 'members' ? app()->admins()->managedUserRoles() : array();
        $viewData['passwordResetRequests'] = $userPanel === 'members' ? app()->admins()->listManagedPasswordResetRequests(array('status' => $userFilters['reset_status'])) : array();
        $viewData['userCanManage'] = app()->auth()->adminCan('users.manage');
        $viewData['registerBonus'] = (string) app()->settings()->get('points.register_bonus', '88');
        $viewData['registerLimitDays'] = (string) app()->settings()->get('members.register_limit_days', '1');
        $viewData['inviteRegisterBonus'] = (string) app()->settings()->get('points.invite_register_bonus', '0');
        break;

    case 'uploads':
        $uploadFilters = array(
            'keyword' => isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '',
            'business_type' => isset($_GET['business_type']) ? trim((string) $_GET['business_type']) : '',
        );
        $viewData['uploadFilters'] = $uploadFilters;
        $viewData['uploadFiles'] = app()->uploads()->listUploads($uploadFilters);
        $viewData['uploadBusinessOptions'] = app()->uploads()->businessTypeOptions();
        $viewData['uploadCanManage'] = app()->auth()->adminCan('uploads.manage');
        break;

    case 'posts':
        $editingPostId = isset($postState['id']) ? (int) $postState['id'] : (isset($_GET['edit']) ? (int) $_GET['edit'] : 0);
        $editingPost = $editingPostId > 0 ? app()->admins()->managedForumPostById($editingPostId) : null;
        $postRegion = isset($postState['region'])
            ? (string) $postState['region']
            : ($editingPost ? (string) $editingPost['region'] : ((string) (isset($_GET['region']) ? trim((string) $_GET['region']) : 'macau')));
        if ($postRegion !== 'hongkong') {
            $postRegion = 'macau';
        }
        $postLiveDraw = app()->prediction()->latestHomepageDraw($postRegion);
        $postManagedCurrentIssue = app()->admins()->managedIssuePrefixSnapshotByRegion($postRegion);
        $viewData['adminHeaderRegion'] = $postRegion;
        $viewData['adminHeaderLiveDraw'] = $postLiveDraw;
        $viewData['postLatestRegionDraw'] = $postLiveDraw;
        $viewData['postManagedCurrentIssue'] = $postManagedCurrentIssue;
        $postViewMode = isset($_GET['view']) ? trim((string) $_GET['view']) : '';
        if ($postViewMode === '' && !empty($_GET['compose'])) {
            $postViewMode = 'compose';
        }
        if ($postViewMode === '' && $editingPostId > 0) {
            $postViewMode = 'compose';
        }
        if (!in_array($postViewMode, array('manage', 'compose', 'published', 'recycle'), true)) {
            $postViewMode = 'manage';
        }
        $shouldLoadPostList = in_array($postViewMode, array('manage', 'recycle'), true);
        $shouldLoadPostEditorData = in_array($postViewMode, array('compose', 'published'), true) || $editingPostId > 0;
        $postNeedsGeneratorConfig = $shouldLoadPostEditorData || $postViewMode === 'manage';
        $postStatusFilter = isset($_GET['status']) ? trim((string) $_GET['status']) : '';
        if ($postViewMode === 'published') {
            $postStatusFilter = 'published';
        } elseif ($postViewMode === 'recycle') {
            $postStatusFilter = 'deleted';
        }
        $postFilters = array(
            'keyword' => isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '',
            'region' => $postRegion,
            'status' => $postStatusFilter,
            'color_tag' => isset($_GET['color_tag']) ? trim((string) $_GET['color_tag']) : '',
            'section_id' => isset($_GET['section_id']) ? (int) $_GET['section_id'] : 0,
            'category_id' => isset($_GET['category_id']) ? (int) $_GET['category_id'] : 0,
            'segment_no' => isset($_GET['segment_no']) ? (int) $_GET['segment_no'] : 0,
            'top_scope' => isset($_GET['top_scope']) ? trim((string) $_GET['top_scope']) : '',
            'sale_filter' => isset($_GET['sale_filter']) ? trim((string) $_GET['sale_filter']) : '',
            'purchase_filter' => isset($_GET['purchase_filter']) ? trim((string) $_GET['purchase_filter']) : '',
            'result_filter' => isset($_GET['result_filter']) ? trim((string) $_GET['result_filter']) : '',
            'wrong_streak_filter' => isset($_GET['wrong_streak_filter']) ? trim((string) $_GET['wrong_streak_filter']) : '',
            'include_stats' => $postViewMode !== 'recycle',
            'page_no' => isset($_GET['page_no']) ? max(1, (int) $_GET['page_no']) : 1,
        );
        $managedPostPage = $shouldLoadPostList
            ? app()->admins()->listManagedForumPostsPage($postFilters)
            : array('items' => array(), 'total' => 0, 'page_no' => 1, 'per_page' => 40, 'page_count' => 1);
        $viewData['postFilters'] = $postFilters;
        $viewData['postPage'] = $managedPostPage;
        $viewData['posts'] = $shouldLoadPostList ? (array) ($managedPostPage['items'] ?? array()) : array();
        $viewData['postSummaryCounts'] = $shouldLoadPostList
            ? array()
            : app()->admins()->managedForumPostSummaryCounts($postRegion);
        $viewData['postCanManage'] = app()->auth()->adminCan('posts.manage');
        $viewData['postCurrentRegion'] = $postRegion;
        $viewData['postViewMode'] = $postViewMode;
        $viewData['postNeedsGeneratorConfig'] = $postNeedsGeneratorConfig;
        $viewData['postGeneratorConfig'] = $postNeedsGeneratorConfig ? app()->admins()->managedPostGeneratorConfig($postRegion) : array();
        $postGeneratorAction = is_post() ? (string) input('_admin_action_override', input('_admin_action', '')) : '';
        $usePostedGeneratorSettings = $postGeneratorAction === 'save_post_generator_settings';
        $usePostedGenerationMode = $postGeneratorAction === 'generate_posts';
        $postDefaultTargets = isset($viewData['postGeneratorConfig']['default_targets']) && is_array($viewData['postGeneratorConfig']['default_targets'])
            ? $viewData['postGeneratorConfig']['default_targets']
            : array();
        $viewData['sectionOptions'] = $shouldLoadPostEditorData ? app()->admins()->sectionOptions($postRegion) : array();
        $postDefaultSectionId = (int) ($postDefaultTargets['section_id'] ?? 0);
        if ($postDefaultSectionId <= 0 && !empty($viewData['sectionOptions'])) {
            $postDefaultSectionId = (int) ($viewData['sectionOptions'][0]['id'] ?? 0);
        }
        $postSectionId = isset($postState['section_id'])
            ? (int) $postState['section_id']
            : ($editingPost
                ? (int) ($editingPost['section_id'] ?? 0)
                : ((int) ($postFilters['section_id'] ?? 0) > 0 ? (int) ($postFilters['section_id'] ?? 0) : $postDefaultSectionId));
        $viewData['categoryOptions'] = $shouldLoadPostEditorData
            ? app()->admins()->categoryOptions($postSectionId, $postRegion)
            : array();
        $postDefaultCategoryId = 0;
        if ((int) ($postDefaultTargets['section_id'] ?? 0) === $postSectionId) {
            $postDefaultCategoryId = (int) ($postDefaultTargets['category_id'] ?? 0);
        }
        if ($postDefaultCategoryId <= 0 && !empty($viewData['categoryOptions'])) {
            $postDefaultCategoryId = (int) ($viewData['categoryOptions'][0]['id'] ?? 0);
        }
        $viewData['postGeneratorState'] = array(
            'generator_type' => isset($postState['generator_type']) ? (string) $postState['generator_type'] : $postRegion,
            'generation_mode' => $usePostedGenerationMode && isset($postState['generation_mode']) ? (string) $postState['generation_mode'] : (string) ($viewData['postGeneratorConfig']['generation_mode'] ?? ''),
            'preset_zodiac_count' => $usePostedGeneratorSettings && isset($postState['preset_zodiac_count']) ? (string) $postState['preset_zodiac_count'] : (string) ($viewData['postGeneratorConfig']['preset_zodiac_count'] ?? ''),
            'preset_zodiac_min' => $usePostedGeneratorSettings && isset($postState['preset_zodiac_min']) ? (string) $postState['preset_zodiac_min'] : (string) ($viewData['postGeneratorConfig']['preset_zodiac_min'] ?? ($viewData['postGeneratorConfig']['preset_zodiac_count'] ?? '')),
            'preset_zodiac_max' => $usePostedGeneratorSettings && isset($postState['preset_zodiac_max']) ? (string) $postState['preset_zodiac_max'] : (string) ($viewData['postGeneratorConfig']['preset_zodiac_max'] ?? ($viewData['postGeneratorConfig']['preset_zodiac_count'] ?? '')),
            'preset_number_count' => $usePostedGeneratorSettings && isset($postState['preset_number_count']) ? (string) $postState['preset_number_count'] : (string) ($viewData['postGeneratorConfig']['preset_number_count'] ?? ''),
            'preset_segment_min' => $usePostedGeneratorSettings && isset($postState['preset_segment_min']) ? (string) $postState['preset_segment_min'] : (string) ($viewData['postGeneratorConfig']['preset_segment_min'] ?? ''),
            'preset_segment_max' => $usePostedGeneratorSettings && isset($postState['preset_segment_max']) ? (string) $postState['preset_segment_max'] : (string) ($viewData['postGeneratorConfig']['preset_segment_max'] ?? ''),
            'preset_record_min' => $usePostedGeneratorSettings && isset($postState['preset_record_min']) ? (string) $postState['preset_record_min'] : (string) ($viewData['postGeneratorConfig']['preset_record_min'] ?? ''),
            'preset_record_max' => $usePostedGeneratorSettings && isset($postState['preset_record_max']) ? (string) $postState['preset_record_max'] : (string) ($viewData['postGeneratorConfig']['preset_record_max'] ?? ''),
            'preset_record_wrong_count' => $usePostedGeneratorSettings && isset($postState['preset_record_wrong_count']) ? (string) $postState['preset_record_wrong_count'] : (string) ($viewData['postGeneratorConfig']['preset_record_wrong_count'] ?? ''),
            'preset_record_rate_min' => $usePostedGeneratorSettings && isset($postState['preset_record_rate_min']) ? (string) $postState['preset_record_rate_min'] : (string) ($viewData['postGeneratorConfig']['preset_record_rate_min'] ?? ($viewData['postGeneratorConfig']['preset_record_wrong_count'] ?? '')),
            'preset_record_rate_max' => $usePostedGeneratorSettings && isset($postState['preset_record_rate_max']) ? (string) $postState['preset_record_rate_max'] : (string) ($viewData['postGeneratorConfig']['preset_record_rate_max'] ?? ($viewData['postGeneratorConfig']['preset_record_wrong_count'] ?? '')),
            'preset_segments' => $usePostedGeneratorSettings && isset($postState['preset_segments']) && is_array($postState['preset_segments']) ? array_values($postState['preset_segments']) : (isset($viewData['postGeneratorConfig']['preset_segments']) && is_array($viewData['postGeneratorConfig']['preset_segments']) ? array_values($viewData['postGeneratorConfig']['preset_segments']) : array()),
            'current_issue_tail' => (string) ($viewData['postGeneratorConfig']['current_issue_tail'] ?? ''),
            'title_prefix' => $usePostedGeneratorSettings && isset($postState['title_prefix']) ? (string) $postState['title_prefix'] : (string) ($viewData['postGeneratorConfig']['title_prefix'] ?? ''),
            'title_middle' => $usePostedGeneratorSettings && isset($postState['title_middle']) ? (string) $postState['title_middle'] : (string) ($viewData['postGeneratorConfig']['title_middle'] ?? ''),
            'title_middle_wrap' => $usePostedGeneratorSettings && isset($postState['title_middle_wrap']) ? (string) $postState['title_middle_wrap'] : (string) ($viewData['postGeneratorConfig']['title_middle_wrap'] ?? ''),
            'author_nickname' => $usePostedGeneratorSettings && isset($postState['author_nickname']) ? (string) $postState['author_nickname'] : (string) ($viewData['postGeneratorConfig']['author_nickname'] ?? ''),
            'title_prefix_color_mode' => $usePostedGeneratorSettings && isset($postState['title_prefix_color_mode']) ? (string) $postState['title_prefix_color_mode'] : (string) ($viewData['postGeneratorConfig']['title_prefix_color_mode'] ?? ''),
            'title_prefix_color_value' => $usePostedGeneratorSettings && isset($postState['title_prefix_color_value']) ? (string) $postState['title_prefix_color_value'] : (string) ($viewData['postGeneratorConfig']['title_prefix_color_value'] ?? ''),
            'title_middle_color_mode' => $usePostedGeneratorSettings && isset($postState['title_middle_color_mode']) ? (string) $postState['title_middle_color_mode'] : (string) ($viewData['postGeneratorConfig']['title_middle_color_mode'] ?? ''),
            'title_middle_color_value' => $usePostedGeneratorSettings && isset($postState['title_middle_color_value']) ? (string) $postState['title_middle_color_value'] : (string) ($viewData['postGeneratorConfig']['title_middle_color_value'] ?? ''),
            'author_nickname_color_mode' => $usePostedGeneratorSettings && isset($postState['author_nickname_color_mode']) ? (string) $postState['author_nickname_color_mode'] : (string) ($viewData['postGeneratorConfig']['author_nickname_color_mode'] ?? ''),
            'author_nickname_color_value' => $usePostedGeneratorSettings && isset($postState['author_nickname_color_value']) ? (string) $postState['author_nickname_color_value'] : (string) ($viewData['postGeneratorConfig']['author_nickname_color_value'] ?? ''),
            'title_font_size' => $usePostedGeneratorSettings && isset($postState['title_font_size']) ? (string) $postState['title_font_size'] : (string) ($viewData['postGeneratorConfig']['title_font_size'] ?? ''),
            'title_font_weight' => $usePostedGeneratorSettings && isset($postState['title_font_weight']) ? (string) $postState['title_font_weight'] : (string) ($viewData['postGeneratorConfig']['title_font_weight'] ?? ''),
            'author_nickname_pool' => isset($postState['author_nickname_pool']) ? (string) $postState['author_nickname_pool'] : (string) ($viewData['postGeneratorConfig']['author_nickname_pool'] ?? ''),
            'segment_no' => isset($postState['segment_no']) ? (int) $postState['segment_no'] : (int) ($viewData['postGeneratorConfig']['segment_no'] ?? 1),
            'top_scope' => isset($postState['top_scope']) ? (string) $postState['top_scope'] : (string) ($viewData['postGeneratorConfig']['top_scope'] ?? 'top_1'),
            'target_zodiac' => isset($postState['target_zodiac']) && is_array($postState['target_zodiac']) ? array_values($postState['target_zodiac']) : (isset($viewData['postGeneratorConfig']['target_zodiac']) && is_array($viewData['postGeneratorConfig']['target_zodiac']) ? array_values($viewData['postGeneratorConfig']['target_zodiac']) : array()),
            'target_number' => isset($postState['target_number']) && is_array($postState['target_number']) ? array_values($postState['target_number']) : (isset($viewData['postGeneratorConfig']['target_number']) && is_array($viewData['postGeneratorConfig']['target_number']) ? array_values($viewData['postGeneratorConfig']['target_number']) : array()),
            'target_wave' => isset($postState['target_wave']) && is_array($postState['target_wave']) ? array_values($postState['target_wave']) : (isset($viewData['postGeneratorConfig']['target_wave']) && is_array($viewData['postGeneratorConfig']['target_wave']) ? array_values($viewData['postGeneratorConfig']['target_wave']) : array()),
            'target_element' => isset($postState['target_element']) && is_array($postState['target_element']) ? array_values($postState['target_element']) : (isset($viewData['postGeneratorConfig']['target_element']) && is_array($viewData['postGeneratorConfig']['target_element']) ? array_values($viewData['postGeneratorConfig']['target_element']) : array()),
            'target_head' => isset($postState['target_head']) && is_array($postState['target_head']) ? array_values($postState['target_head']) : (isset($viewData['postGeneratorConfig']['target_head']) && is_array($viewData['postGeneratorConfig']['target_head']) ? array_values($viewData['postGeneratorConfig']['target_head']) : array()),
            'target_tail' => isset($postState['target_tail']) && is_array($postState['target_tail']) ? array_values($postState['target_tail']) : (isset($viewData['postGeneratorConfig']['target_tail']) && is_array($viewData['postGeneratorConfig']['target_tail']) ? array_values($viewData['postGeneratorConfig']['target_tail']) : array()),
            'templates' => isset($postState['templates']) && is_array($postState['templates']) ? array_values($postState['templates']) : (isset($viewData['postGeneratorConfig']['templates']) && is_array($viewData['postGeneratorConfig']['templates']) ? array_values($viewData['postGeneratorConfig']['templates']) : array()),
            'manage_templates' => $usePostedGeneratorSettings && isset($postState['manage_templates_submitted'])
                ? (isset($postState['manage_templates']) && is_array($postState['manage_templates']) ? array_values($postState['manage_templates']) : array())
                : (isset($viewData['postGeneratorConfig']['manage_templates']) && is_array($viewData['postGeneratorConfig']['manage_templates']) ? array_values($viewData['postGeneratorConfig']['manage_templates']) : array()),
            'auto_reply_enabled' => $usePostedGeneratorSettings ? (!empty($postState['auto_reply_enabled']) ? '1' : '') : (!empty($viewData['postGeneratorConfig']['auto_reply_enabled']) ? '1' : ''),
            'auto_reply_count' => $usePostedGeneratorSettings && isset($postState['auto_reply_count']) ? (string) $postState['auto_reply_count'] : (string) ($viewData['postGeneratorConfig']['auto_reply_count'] ?? '3'),
            'auto_reply_base_min' => $usePostedGeneratorSettings && isset($postState['auto_reply_base_min']) ? (string) $postState['auto_reply_base_min'] : (string) ($viewData['postGeneratorConfig']['auto_reply_base_min'] ?? '2'),
            'auto_reply_base_max' => $usePostedGeneratorSettings && isset($postState['auto_reply_base_max']) ? (string) $postState['auto_reply_base_max'] : (string) ($viewData['postGeneratorConfig']['auto_reply_base_max'] ?? '5'),
            'auto_reply_daily_min' => $usePostedGeneratorSettings && isset($postState['auto_reply_daily_min']) ? (string) $postState['auto_reply_daily_min'] : (string) ($viewData['postGeneratorConfig']['auto_reply_daily_min'] ?? '1'),
            'auto_reply_daily_max' => $usePostedGeneratorSettings && isset($postState['auto_reply_daily_max']) ? (string) $postState['auto_reply_daily_max'] : (string) ($viewData['postGeneratorConfig']['auto_reply_daily_max'] ?? '3'),
            'auto_reply_issue_min' => $usePostedGeneratorSettings && isset($postState['auto_reply_issue_min']) ? (string) $postState['auto_reply_issue_min'] : (string) ($viewData['postGeneratorConfig']['auto_reply_issue_min'] ?? '1'),
            'auto_reply_issue_max' => $usePostedGeneratorSettings && isset($postState['auto_reply_issue_max']) ? (string) $postState['auto_reply_issue_max'] : (string) ($viewData['postGeneratorConfig']['auto_reply_issue_max'] ?? '3'),
            'auto_reply_forbid_start_hour' => $usePostedGeneratorSettings && isset($postState['auto_reply_forbid_start_hour']) ? (string) $postState['auto_reply_forbid_start_hour'] : (string) ($viewData['postGeneratorConfig']['auto_reply_forbid_start_hour'] ?? '1'),
            'auto_reply_forbid_end_hour' => $usePostedGeneratorSettings && isset($postState['auto_reply_forbid_end_hour']) ? (string) $postState['auto_reply_forbid_end_hour'] : (string) ($viewData['postGeneratorConfig']['auto_reply_forbid_end_hour'] ?? '8'),
            'wrong_refund_streak' => $usePostedGeneratorSettings && isset($postState['wrong_refund_streak']) ? (string) $postState['wrong_refund_streak'] : (string) ($viewData['postGeneratorConfig']['wrong_refund_streak'] ?? '2'),
            'wrong_refund_percent' => $usePostedGeneratorSettings && isset($postState['wrong_refund_percent']) ? (string) $postState['wrong_refund_percent'] : (string) ($viewData['postGeneratorConfig']['wrong_refund_percent'] ?? '100'),
            'after_draw_delete_wrong_streak' => $usePostedGeneratorSettings && isset($postState['after_draw_delete_wrong_streak']) ? (string) $postState['after_draw_delete_wrong_streak'] : (string) ($viewData['postGeneratorConfig']['after_draw_delete_wrong_streak'] ?? '2'),
            'auto_reply_items' => $usePostedGeneratorSettings && isset($postState['auto_reply_items']) ? (string) $postState['auto_reply_items'] : (string) ($viewData['postGeneratorConfig']['auto_reply_items'] ?? ''),
            'is_blank_content' => $usePostedGeneratorSettings ? (!empty($postState['is_blank_content']) ? '1' : '') : (!empty($viewData['postGeneratorConfig']['is_blank_content']) ? '1' : ''),
            'is_fake_after_open' => $usePostedGeneratorSettings ? (!empty($postState['is_fake_after_open']) ? '1' : '') : (!empty($viewData['postGeneratorConfig']['is_fake_after_open']) ? '1' : ''),
            'post_update_time' => $usePostedGeneratorSettings && isset($postState['post_update_time'])
                ? (string) $postState['post_update_time']
                : (string) ($viewData['postGeneratorConfig']['post_update_time']
                    ?? ($viewData['postGeneratorConfig']['material_content_time'] ?? '')),
            'material_content_time' => $usePostedGeneratorSettings && isset($postState['material_content_time']) ? (string) $postState['material_content_time'] : (string) ($viewData['postGeneratorConfig']['material_content_time'] ?? ''),
            'sale_material_content_time' => $usePostedGeneratorSettings && isset($postState['sale_material_content_time']) ? (string) $postState['sale_material_content_time'] : (string) ($viewData['postGeneratorConfig']['sale_material_content_time'] ?? ($viewData['postGeneratorConfig']['material_content_time'] ?? '')),
        );
        $postFormTitleValue = isset($postState['title']) ? (string) $postState['title'] : ($editingPost ? (string) $editingPost['title'] : '');
        $postFormTitleBodyValue = trim((string) preg_replace('/^\\s*\\d{1,3}期[:：]?\\s*/u', '', $postFormTitleValue));
        $viewData['postForm'] = array(
            'id' => isset($postState['id']) ? (int) $postState['id'] : ($editingPost ? (int) $editingPost['id'] : 0),
            'region' => $postRegion,
            'segment_no' => isset($postState['segment_no']) ? (int) $postState['segment_no'] : ($editingPost ? (int) ($editingPost['manage_segment_no'] ?? 1) : 1),
            'section_id' => $postSectionId,
            'category_id' => isset($postState['category_id']) ? (int) $postState['category_id'] : ($editingPost ? (int) ($editingPost['category_id'] ?? 0) : $postDefaultCategoryId),
            'title' => $postFormTitleValue,
            'title_prefix' => isset($postState['title_prefix']) ? (string) $postState['title_prefix'] : '',
            'title_middle' => isset($postState['title_middle']) ? (string) $postState['title_middle'] : $postFormTitleBodyValue,
            'title_suffix' => isset($postState['title_suffix']) ? (string) $postState['title_suffix'] : '',
            'author_nickname' => isset($postState['author_nickname']) ? (string) $postState['author_nickname'] : ($editingPost ? (string) ($editingPost['manage_author_nickname'] ?? '') : ''),
            'excerpt' => isset($postState['excerpt']) ? (string) $postState['excerpt'] : ($editingPost ? (string) $editingPost['excerpt'] : ''),
            'preview_content' => isset($postState['preview_content']) ? (string) $postState['preview_content'] : ($editingPost ? (string) $editingPost['preview_content'] : ''),
            'full_content' => isset($postState['full_content']) ? (string) $postState['full_content'] : ($editingPost ? (string) $editingPost['full_content'] : ''),
            'price' => isset($postState['price']) ? (string) $postState['price'] : ($editingPost ? (string) $editingPost['price'] : '0'),
            'color_tag' => isset($postState['color_tag']) ? (string) $postState['color_tag'] : ($editingPost ? (string) $editingPost['color_tag'] : 'slate'),
            'status' => isset($postState['status']) ? (string) $postState['status'] : ($editingPost ? (string) $editingPost['status'] : 'published'),
            'is_top_forever' => isset($postState['is_top_forever']) ? '1' : ($editingPost && (int) $editingPost['is_top_forever'] === 1 ? '1' : '0'),
            'is_top_admin' => isset($postState['is_top_admin']) ? '1' : ($editingPost && (int) $editingPost['is_top_admin'] === 1 ? '1' : '0'),
            'is_top_normal' => isset($postState['is_top_normal']) ? '1' : ($editingPost && (int) $editingPost['is_top_normal'] === 1 ? '1' : '0'),
        );
        break;

    case 'sections':
        $sectionFilters = array(
            'keyword' => isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '',
            'region' => isset($_GET['region']) ? trim((string) $_GET['region']) : '',
            'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
        );
        $editingSectionId = isset($postState['id']) ? (int) $postState['id'] : (isset($_GET['edit']) ? (int) $_GET['edit'] : 0);
        $viewData['sectionFilters'] = $sectionFilters;
        $viewData['sections'] = app()->admins()->listManagedSections($sectionFilters);
        $editingSection = null;
        if ($editingSectionId > 0) {
            foreach ($viewData['sections'] as $sectionRow) {
                if ((int) ($sectionRow['id'] ?? 0) === $editingSectionId) {
                    $editingSection = $sectionRow;
                    break;
                }
            }
            if (!$editingSection) {
                $editingSection = app()->admins()->managedSectionById($editingSectionId);
            }
        }
        $viewData['sectionCanManage'] = app()->auth()->adminCan('sections.manage');
        $viewData['sectionForm'] = array(
            'id' => isset($postState['id']) ? (int) $postState['id'] : ($editingSection ? (int) $editingSection['id'] : 0),
            'region' => isset($postState['region']) ? (string) $postState['region'] : ($editingSection ? (string) $editingSection['region'] : 'macau'),
            'name' => isset($postState['name']) ? (string) $postState['name'] : ($editingSection ? (string) $editingSection['name'] : ''),
            'code' => isset($postState['code']) ? (string) $postState['code'] : ($editingSection ? (string) $editingSection['code'] : ''),
            'description' => isset($postState['description']) ? (string) $postState['description'] : ($editingSection ? (string) $editingSection['description'] : ''),
            'icon' => isset($postState['icon']) ? (string) $postState['icon'] : ($editingSection ? (string) $editingSection['icon'] : ''),
            'post_rule' => isset($postState['post_rule']) ? (string) $postState['post_rule'] : ($editingSection ? (string) $editingSection['post_rule'] : ''),
            'sort_order' => isset($postState['sort_order']) ? (string) $postState['sort_order'] : ($editingSection ? (string) $editingSection['sort_order'] : '0'),
            'status' => isset($postState['status']) ? '1' : ($editingSection && (int) $editingSection['status'] === 1 ? '1' : '0'),
        );
        break;

    case 'categories':
        $categoryFilters = array(
            'keyword' => isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '',
            'region' => isset($_GET['region']) ? trim((string) $_GET['region']) : '',
            'section_id' => isset($_GET['section_id']) ? (int) $_GET['section_id'] : 0,
            'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
        );
        $editingCategoryId = isset($postState['id']) ? (int) $postState['id'] : (isset($_GET['edit']) ? (int) $_GET['edit'] : 0);
        $viewData['categoryFilters'] = $categoryFilters;
        $viewData['categories'] = app()->admins()->listManagedCategories($categoryFilters);
        $editingCategory = null;
        if ($editingCategoryId > 0) {
            foreach ($viewData['categories'] as $categoryRow) {
                if ((int) ($categoryRow['id'] ?? 0) === $editingCategoryId) {
                    $editingCategory = $categoryRow;
                    break;
                }
            }
            if (!$editingCategory) {
                $editingCategory = app()->admins()->managedCategoryById($editingCategoryId);
            }
        }
        $categoryRegion = isset($postState['region']) ? (string) $postState['region'] : ($editingCategory ? (string) $editingCategory['region'] : ($categoryFilters['region'] ?: 'macau'));
        $viewData['categoryCanManage'] = app()->auth()->adminCan('categories.manage');
        $viewData['categorySectionOptions'] = app()->admins()->sectionOptions($categoryRegion);
        $viewData['categoryForm'] = array(
            'id' => isset($postState['id']) ? (int) $postState['id'] : ($editingCategory ? (int) $editingCategory['id'] : 0),
            'section_id' => isset($postState['section_id']) ? (int) $postState['section_id'] : ($editingCategory ? (int) ($editingCategory['section_id'] ?? 0) : 0),
            'region' => $categoryRegion,
            'name' => isset($postState['name']) ? (string) $postState['name'] : ($editingCategory ? (string) $editingCategory['name'] : ''),
            'code' => isset($postState['code']) ? (string) $postState['code'] : ($editingCategory ? (string) $editingCategory['code'] : ''),
            'description' => isset($postState['description']) ? (string) $postState['description'] : ($editingCategory ? (string) $editingCategory['description'] : ''),
            'sort_order' => isset($postState['sort_order']) ? (string) $postState['sort_order'] : ($editingCategory ? (string) $editingCategory['sort_order'] : '0'),
            'status' => isset($postState['status']) ? '1' : ($editingCategory && (int) $editingCategory['status'] === 1 ? '1' : '0'),
        );
        break;

    case 'comments':
        $commentFilters = array(
            'keyword' => isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '',
            'region' => isset($_GET['region']) ? trim((string) $_GET['region']) : '',
            'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
            'post_id' => isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0,
            'page_no' => isset($_GET['page_no']) ? (int) $_GET['page_no'] : 1,
        );
        $viewData['commentFilters'] = $commentFilters;
        $viewData['commentPage'] = app()->admins()->listManagedComments($commentFilters);
        $viewData['commentCanManage'] = app()->auth()->adminCan('comments.manage');
        break;

    case 'interactions':
        $interactionFilters = array(
            'keyword' => isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '',
            'region' => isset($_GET['region']) ? trim((string) $_GET['region']) : '',
            'interaction_type' => isset($_GET['interaction_type']) ? trim((string) $_GET['interaction_type']) : '',
            'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
            'post_id' => isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0,
            'user_id' => isset($_GET['user_id']) ? (int) $_GET['user_id'] : 0,
            'page_no' => isset($_GET['page_no']) ? (int) $_GET['page_no'] : 1,
        );
        $editingInteractionId = isset($postState['id']) ? (int) $postState['id'] : (isset($_GET['edit']) ? (int) $_GET['edit'] : 0);
        $viewData['interactionFilters'] = $interactionFilters;
        $viewData['interactionPage'] = app()->admins()->listManagedPostInteractions($interactionFilters);
        $editingInteraction = null;
        if ($editingInteractionId > 0) {
            foreach ((array) ($viewData['interactionPage']['items'] ?? array()) as $interactionRow) {
                if ((int) ($interactionRow['id'] ?? 0) === $editingInteractionId) {
                    $editingInteraction = $interactionRow;
                    break;
                }
            }
            if (!$editingInteraction) {
                $editingInteraction = app()->admins()->managedPostInteractionById($editingInteractionId);
            }
        }
        $interactionFormPostId = isset($postState['post_id']) ? (int) $postState['post_id'] : ($editingInteraction ? (int) ($editingInteraction['post_id'] ?? 0) : 0);
        $interactionFormUserId = isset($postState['user_id']) ? (int) $postState['user_id'] : ($editingInteraction ? (int) ($editingInteraction['user_id'] ?? 0) : 0);
        $viewData['interactionStats'] = app()->admins()->managedPostInteractionStats($interactionFilters);
        $viewData['interactionCanManage'] = app()->auth()->adminCan('interactions.manage');
        $viewData['interactionTypeLabels'] = app()->admins()->managedInteractionTypeOptions();
        $viewData['interactionUserOptions'] = app()->admins()->managedUserSelectOptions($interactionFormUserId);
        $viewData['interactionPostOptions'] = app()->admins()->managedPostSelectOptions($interactionFormPostId);
        $viewData['interactionForm'] = array(
            'id' => isset($postState['id']) ? (int) $postState['id'] : ($editingInteraction ? (int) $editingInteraction['id'] : 0),
            'post_id' => $interactionFormPostId,
            'user_id' => $interactionFormUserId,
            'interaction_type' => isset($postState['interaction_type']) ? (string) $postState['interaction_type'] : ($editingInteraction ? (string) ($editingInteraction['interaction_type'] ?? 'like') : 'like'),
            'status' => isset($postState['status']) ? (string) $postState['status'] : ($editingInteraction ? (string) ((int) ($editingInteraction['status'] ?? 1)) : '1'),
        );
        break;

    case 'reports':
        $reportFilters = array(
            'keyword' => isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '',
            'region' => isset($_GET['region']) ? trim((string) $_GET['region']) : '',
            'report_type' => isset($_GET['report_type']) ? trim((string) $_GET['report_type']) : '',
            'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
            'post_id' => isset($_GET['post_id']) ? (int) $_GET['post_id'] : 0,
            'reporter_id' => isset($_GET['reporter_id']) ? (int) $_GET['reporter_id'] : 0,
            'page_no' => isset($_GET['page_no']) ? (int) $_GET['page_no'] : 1,
        );
        $editingReportId = isset($postState['id']) ? (int) $postState['id'] : (isset($_GET['edit']) ? (int) $_GET['edit'] : 0);
        $viewData['reportFilters'] = $reportFilters;
        $viewData['reportPage'] = app()->admins()->listManagedPostReports($reportFilters);
        $editingReport = null;
        if ($editingReportId > 0) {
            foreach ((array) ($viewData['reportPage']['items'] ?? array()) as $reportRow) {
                if ((int) ($reportRow['id'] ?? 0) === $editingReportId) {
                    $editingReport = $reportRow;
                    break;
                }
            }
            if (!$editingReport) {
                $editingReport = app()->admins()->managedPostReportById($editingReportId);
            }
        }
        $reportFormPostId = isset($postState['post_id']) ? (int) $postState['post_id'] : ($editingReport ? (int) ($editingReport['post_id'] ?? 0) : 0);
        $reportFormReporterId = isset($postState['reporter_id']) ? (int) $postState['reporter_id'] : ($editingReport ? (int) ($editingReport['reporter_id'] ?? 0) : 0);
        $viewData['reportStats'] = app()->admins()->managedPostReportStats($reportFilters);
        $viewData['reportCanManage'] = app()->auth()->adminCan('reports.manage');
        $viewData['reportTypeLabels'] = app()->admins()->managedReportTypeOptions();
        $viewData['reportPunishmentLabels'] = app()->admins()->managedReportPunishmentChoices();
        $viewData['reportUserOptions'] = app()->admins()->managedUserSelectOptions($reportFormReporterId);
        $viewData['reportPostOptions'] = app()->admins()->managedPostSelectOptions($reportFormPostId);
        $viewData['reportForm'] = array(
            'id' => isset($postState['id']) ? (int) $postState['id'] : ($editingReport ? (int) $editingReport['id'] : 0),
            'post_id' => $reportFormPostId,
            'reporter_id' => $reportFormReporterId,
            'report_type' => isset($postState['report_type']) ? (string) $postState['report_type'] : ($editingReport ? (string) ($editingReport['report_type'] ?? 'other') : 'other'),
            'content' => isset($postState['content']) ? (string) $postState['content'] : ($editingReport ? (string) ($editingReport['content'] ?? '') : ''),
            'status' => isset($postState['status']) ? (string) $postState['status'] : ($editingReport ? (string) ($editingReport['status'] ?? 'pending') : 'pending'),
            'handle_result' => isset($postState['handle_result']) ? (string) $postState['handle_result'] : ($editingReport ? (string) ($editingReport['handle_result'] ?? '') : ''),
        );
        break;

    case 'audits':
        $auditFilters = array(
            'keyword' => isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '',
            'region' => isset($_GET['region']) ? trim((string) $_GET['region']) : '',
            'target_type' => isset($_GET['target_type']) ? trim((string) $_GET['target_type']) : '',
            'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
            'page_no' => isset($_GET['page_no']) ? (int) $_GET['page_no'] : 1,
        );
        $viewData['auditFilters'] = $auditFilters;
        $viewData['pendingAuditTargets'] = app()->admins()->listPendingAuditTargets($auditFilters);
        $viewData['auditRecordPage'] = app()->admins()->listManagedAuditRecords($auditFilters);
        $viewData['auditCanManage'] = app()->auth()->adminCan('audits.manage');
        break;

    case 'draws':
        $drawMode = isset($_GET['mode']) ? trim((string) $_GET['mode']) : 'material';
        if (!in_array($drawMode, array('material', 'component', 'images'), true)) {
            $drawMode = 'material';
        }
        $drawRegion = isset($_GET['region']) ? trim((string) $_GET['region']) : 'macau';
        if (!in_array($drawRegion, array('macau', 'hongkong'), true)) {
            $drawRegion = 'macau';
        }
        $drawComponent = 'float_group';
        $viewData['drawFilters'] = array(
            'region' => $drawRegion,
            'mode' => $drawMode,
            'component' => $drawComponent,
        );
        $viewData['pageHeading'] = '资料分区';
        $viewData['drawCanManage'] = app()->auth()->adminCan('draws.manage');
        if ($viewData['drawCanManage'] && $drawMode !== 'images') {
            $drawSaveLabel = $drawMode === 'component'
                ? '保存悬浮组件'
                : '保存资料';
            $viewData['pageTitleActionHtml'] = '<button class="admin-button admin-editor-save-button is-in-page-title-shell" type="submit" form="draw-material-form" data-draw-header-save>' . e($drawSaveLabel) . '</button>';
        }
        $viewData['needsAdminTinyMce'] = $viewData['drawCanManage'] && $drawMode !== 'images';
        $viewData['drawEditor'] = $drawMode === 'images'
            ? array('content_html' => '')
            : ($drawMode === 'component'
                ? app()->admins()->managedDrawComponentEditor($drawComponent)
                : app()->admins()->managedDrawMaterialEditor($drawRegion));
        $drawImageBusinessOptions = app()->uploads()->businessTypeOptions();
        $drawImageFilters = array(
            'keyword' => '',
            'business_type' => '',
        );
        $viewData['drawImageFilters'] = $drawImageFilters;
        $viewData['drawImageFiles'] = $drawMode === 'images' ? app()->uploads()->listUploads($drawImageFilters) : array();
        $viewData['drawImageAllCount'] = $drawMode === 'images' ? app()->uploads()->countUploads(array()) : 0;
        $viewData['drawImageBusinessOptions'] = $drawImageBusinessOptions;
        $drawLiveDraw = app()->prediction()->latestHomepageDraw($drawRegion);
        $viewData['adminHeaderRegion'] = $drawRegion;
        $viewData['adminHeaderLiveDraw'] = $drawLiveDraw;
        $viewData['drawLatestRegionDraw'] = $drawLiveDraw;
        break;

    case 'home':
        $viewData['homeCanManage'] = app()->auth()->adminCan('home.manage');
        if ($viewData['homeCanManage']) {
            $viewData['pageTitleActionHtml'] =
                '<button class="forecast-pricing-reset is-in-page-title-shell" type="reset" form="forecast-pricing-form">还原修改</button>'
                . '<button class="forecast-pricing-save is-in-page-title-shell" type="submit" form="forecast-pricing-form">保存设置</button>';
        }
        $viewData['forecastPricingConfig'] = app()->admins()->forecastPricingSettings();
        break;

    case 'issues':
        $issueFilters = array(
            'keyword' => isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '',
            'region' => isset($_GET['region']) ? trim((string) $_GET['region']) : '',
            'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
        );
        $editingIssueId = isset($postState['id']) ? (int) $postState['id'] : (isset($_GET['edit']) ? (int) $_GET['edit'] : 0);
        $editingIssue = $editingIssueId > 0 ? app()->admins()->managedIssueById($editingIssueId) : null;
        $viewData['issueFilters'] = $issueFilters;
        $viewData['issues'] = app()->admins()->listManagedIssues($issueFilters);
        $viewData['currentIssueSnapshots'] = app()->admins()->currentIssueSnapshots();
        $viewData['issueCanManage'] = app()->auth()->adminCan('issues.manage');
        $viewData['issueForm'] = array(
            'id' => isset($postState['id']) ? (int) $postState['id'] : ($editingIssue ? (int) $editingIssue['id'] : 0),
            'region' => isset($postState['region']) ? (string) $postState['region'] : ($editingIssue ? (string) $editingIssue['region'] : 'macau'),
            'issue_no' => isset($postState['issue_no']) ? (string) $postState['issue_no'] : ($editingIssue ? (string) $editingIssue['issue_no'] : ''),
            'planned_open_at' => isset($postState['planned_open_at']) ? (string) $postState['planned_open_at'] : ($editingIssue ? (string) ($editingIssue['planned_open_at'] ? str_replace(' ', 'T', substr((string) $editingIssue['planned_open_at'], 0, 16)) : '') : ''),
            'actual_open_at' => isset($postState['actual_open_at']) ? (string) $postState['actual_open_at'] : ($editingIssue ? (string) ($editingIssue['actual_open_at'] ? str_replace(' ', 'T', substr((string) $editingIssue['actual_open_at'], 0, 16)) : '') : ''),
            'status' => isset($postState['status']) ? (string) $postState['status'] : ($editingIssue ? (string) $editingIssue['status'] : 'pending'),
            'is_current' => isset($postState['is_current']) ? '1' : ($editingIssue && (int) $editingIssue['is_current'] === 1 ? '1' : '0'),
            'remark' => isset($postState['remark']) ? (string) $postState['remark'] : ($editingIssue ? (string) $editingIssue['remark'] : ''),
        );
        break;

    case 'login_logs':
        $loginLogFilters = array(
            'keyword' => isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '',
            'status' => isset($_GET['status']) ? trim((string) $_GET['status']) : '',
            'device' => isset($_GET['device']) ? trim((string) $_GET['device']) : '',
            'date_from' => isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '',
            'page_no' => isset($_GET['page_no']) ? (int) $_GET['page_no'] : 1,
        );
        $viewData['loginLogFilters'] = $loginLogFilters;
        $viewData['loginLogPage'] = app()->admins()->listAdminLoginLogs($loginLogFilters);
        break;

    case 'operation_logs':
        $operationLogFilters = array(
            'module' => isset($_GET['module']) ? trim((string) $_GET['module']) : '',
            'keyword' => isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '',
            'date_from' => isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '',
            'page_no' => isset($_GET['page_no']) ? (int) $_GET['page_no'] : 1,
        );
        $systemLogFilters = array(
            'source' => isset($_GET['system_source']) ? trim((string) $_GET['system_source']) : '',
            'keyword' => isset($_GET['system_keyword']) ? trim((string) $_GET['system_keyword']) : '',
            'date_from' => isset($_GET['system_date_from']) ? trim((string) $_GET['system_date_from']) : '',
            'date_to' => isset($_GET['system_date_to']) ? trim((string) $_GET['system_date_to']) : '',
            'page_no' => isset($_GET['system_page_no']) ? (int) $_GET['system_page_no'] : 1,
        );
        $viewData['operationLogFilters'] = $operationLogFilters;
        $viewData['operationLogPage'] = app()->admins()->listAdminOperationLogsPage($operationLogFilters);
        $viewData['systemLogFilters'] = $systemLogFilters;
        $viewData['systemLogPage'] = app()->admins()->listAdminSystemLogsPage($systemLogFilters);
        break;

    case 'exceptions':
        $exceptionFilters = array(
            'level' => isset($_GET['level']) ? trim((string) $_GET['level']) : '',
            'module' => isset($_GET['module']) ? trim((string) $_GET['module']) : '',
            'keyword' => isset($_GET['keyword']) ? trim((string) $_GET['keyword']) : '',
            'date_from' => isset($_GET['date_from']) ? trim((string) $_GET['date_from']) : '',
            'date_to' => isset($_GET['date_to']) ? trim((string) $_GET['date_to']) : '',
            'page_no' => isset($_GET['page_no']) ? (int) $_GET['page_no'] : 1,
        );
        $viewData['exceptionFilters'] = $exceptionFilters;
        $viewData['exceptionLogPage'] = app()->admins()->listExceptionLogs($exceptionFilters);
        break;

    case 'security':
        $securityDefaults = app()->admins()->securitySettings();
        $viewData['securityCanManage'] = app()->auth()->adminCan('security.manage');
        $viewData['securitySettingsForm'] = array(
            'max_login_attempts' => isset($postState['max_login_attempts']) ? (string) $postState['max_login_attempts'] : (string) ($securityDefaults['max_login_attempts'] ?? '5'),
            'admin_session_minutes' => isset($postState['admin_session_minutes']) ? (string) $postState['admin_session_minutes'] : (string) ($securityDefaults['admin_session_minutes'] ?? '120'),
        );
        break;

    case 'install':
        $viewData['installSnapshot'] = app()->admins()->installSnapshot();
        break;
}

view($pageConfig['template'], $viewData, 'layouts/admin');
