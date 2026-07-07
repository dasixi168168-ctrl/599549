<?php
$pageTitle = isset($pageTitle) ? (string) $pageTitle : '后台管理';
$pageHeading = isset($pageHeading) ? (string) $pageHeading : '后台管理';
$pageTitleActionHtml = isset($pageTitleActionHtml) ? (string) $pageTitleActionHtml : '';
$adminHeaderRegion = isset($adminHeaderRegion)
    ? (string) $adminHeaderRegion
    : (isset($region) ? (string) $region : (isset($_GET['region']) ? (string) $_GET['region'] : 'macau'));
$adminHeaderRegion = $adminHeaderRegion === 'hongkong' ? 'hongkong' : 'macau';
$adminHeaderLiveDraw = is_array($adminHeaderLiveDraw ?? null) ? $adminHeaderLiveDraw : null;
if ($adminHeaderLiveDraw === null) {
    try {
        $adminHeaderLiveDraw = app()->prediction()->latestHomepageDraw($adminHeaderRegion);
    } catch (\Throwable $adminHeaderLiveDrawError) {
        $adminHeaderLiveDraw = null;
    }
}
$adminHeaderLiveNormalizeIssueTail = static function ($issueNo) {
    $text = trim((string) $issueNo);
    if ($text === '' || !preg_match('/^\d+$/', $text)) {
        return '--';
    }

    $tail = strlen($text) > 3 ? substr($text, -3) : $text;

    return str_pad($tail, 3, '0', STR_PAD_LEFT);
};
$adminHeaderLivePadNumber = static function ($value) {
    $number = (int) $value;

    return $number < 10 ? '0' . $number : (string) $number;
};
$adminHeaderLiveWaveColorClass = static function ($value) {
    $number = (int) $value;
    if (in_array($number, array(1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46), true)) {
        return 'is-red';
    }
    if (in_array($number, array(3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48), true)) {
        return 'is-blue';
    }

    return 'is-green';
};
$adminHeaderLiveDrawDate = is_array($adminHeaderLiveDraw) ? trim((string) ($adminHeaderLiveDraw['draw_date'] ?? '')) : '';
$adminHeaderLiveRenderBall = static function ($value) use ($adminHeaderLivePadNumber, $adminHeaderLiveWaveColorClass, $adminHeaderLiveDrawDate) {
    $value = $value === null ? null : (int) $value;
    $ballClass = $value === null ? 'is-empty' : $adminHeaderLiveWaveColorClass($value);
    $numberText = $value === null ? '--' : $adminHeaderLivePadNumber($value);
    $zodiacText = $value === null ? '--' : (app()->prediction()->drawZodiacByNumber($value, $adminHeaderLiveDrawDate) ?: '--');

    return '<div class="admin-header-draw-ball ' . e($ballClass) . '">' .
        '<div class="admin-header-draw-ball-code">' . e($numberText) . '</div>' .
        '<div class="admin-header-draw-ball-zodiac">' . e($zodiacText) . '</div>' .
        '</div>';
};
$adminHeaderLiveIssueText = '--期';
$adminHeaderLiveBallsHtml = '';
if (is_array($adminHeaderLiveDraw)) {
    $adminHeaderLiveIssueText = $adminHeaderLiveNormalizeIssueTail($adminHeaderLiveDraw['issue_no'] ?? '') . '期';
    $adminHeaderLiveNumbers = isset($adminHeaderLiveDraw['numbers']) && is_array($adminHeaderLiveDraw['numbers'])
        ? array_values($adminHeaderLiveDraw['numbers'])
        : json_decode((string) ($adminHeaderLiveDraw['numbers_json'] ?? '[]'), true);
    $adminHeaderLiveNumbers = is_array($adminHeaderLiveNumbers) ? array_values($adminHeaderLiveNumbers) : array();
    for ($adminHeaderLiveIndex = 0; $adminHeaderLiveIndex < 6; $adminHeaderLiveIndex += 1) {
        $adminHeaderLiveValue = array_key_exists($adminHeaderLiveIndex, $adminHeaderLiveNumbers) ? (int) $adminHeaderLiveNumbers[$adminHeaderLiveIndex] : null;
        $adminHeaderLiveBallsHtml .= $adminHeaderLiveRenderBall($adminHeaderLiveValue > 0 ? $adminHeaderLiveValue : null);
    }
    $adminHeaderLiveBallsHtml .= '<div class="admin-header-draw-ball-plus">+</div>';
    $adminHeaderLiveSpecialNumber = (int) ($adminHeaderLiveDraw['special_number'] ?? 0);
    $adminHeaderLiveBallsHtml .= $adminHeaderLiveRenderBall($adminHeaderLiveSpecialNumber > 0 ? $adminHeaderLiveSpecialNumber : null);
}
if ($adminHeaderLiveBallsHtml === '') {
    for ($adminHeaderLiveIndex = 0; $adminHeaderLiveIndex < 6; $adminHeaderLiveIndex += 1) {
        $adminHeaderLiveBallsHtml .= $adminHeaderLiveRenderBall(null);
    }
    $adminHeaderLiveBallsHtml .= '<div class="admin-header-draw-ball-plus">+</div>';
    $adminHeaderLiveBallsHtml .= $adminHeaderLiveRenderBall(null);
}
$adminHeaderDrawHtml = '<div class="admin-header-draw-card" data-admin-header-draw data-region="' . e($adminHeaderRegion) . '">' .
    '<div class="admin-header-draw-meta">' .
    '<span class="admin-header-draw-region">' . e($adminHeaderRegion === 'hongkong' ? '香港' : '澳门') . '</span>' .
    '<span class="admin-header-draw-issue">' . e($adminHeaderLiveIssueText) . '</span>' .
    '</div><div class="admin-header-draw-balls">' . $adminHeaderLiveBallsHtml . '</div></div>';
$currentAdmin = is_array($currentAdmin ?? null) ? $currentAdmin : array();
$adminAccountCanManage = false;
$adminAccountRoles = array();
$adminAccountAdmins = array();
try {
    $adminAccountCanManage = app()->auth()->adminCan('admins.manage');
    if ($adminAccountCanManage) {
        $adminAccountRoles = app()->admins()->listRoles();
        $adminAccountAdmins = app()->admins()->listAdmins();
    }
} catch (\Throwable $adminAccountModalError) {
    $adminAccountCanManage = false;
    $adminAccountRoles = array();
    $adminAccountAdmins = array();
}
$adminAccountCurrentRoleId = (int) ($currentAdmin['role_id'] ?? 0);
$adminAccountDefaultRoleId = 0;
$adminAccountAdminCount = count($adminAccountAdmins);
foreach ($adminAccountRoles as $adminAccountRole) {
    $roleId = (int) ($adminAccountRole['id'] ?? 0);
    if ($roleId <= 0) {
        continue;
    }
    if ($adminAccountDefaultRoleId === 0) {
        $adminAccountDefaultRoleId = $roleId;
    }
    if ($roleId === $adminAccountCurrentRoleId) {
        $adminAccountDefaultRoleId = $roleId;
    }
}
$pageTitleShellClass = 'admin-page-title admin-page-title-shell';
if ($pageTitleActionHtml !== '') {
    $pageTitleShellClass .= ' admin-page-title-shell--with-action';
}
$activeMenuCode = isset($activeMenuCode) ? (string) $activeMenuCode : '';
$menuItems = is_array($adminMenuItems ?? null) ? $adminMenuItems : array();

$menuItemsByCode = array();
foreach ($menuItems as $menuItem) {
    $menuItemsByCode[(string) ($menuItem['code'] ?? '')] = $menuItem;
}

$sidebarMenuSpec = array(
    'dashboard' => array('label' => '仪表盘', 'icon' => 'dashboard'),
    'support' => array('label' => '在线客服', 'icon' => 'service'),
    'settings' => array('label' => '前后台设置', 'icon' => 'setting'),
    'home' => array('label' => 'AI预测设置', 'icon' => 'ai'),
    'draws' => array('label' => '资料更新', 'icon' => 'refresh'),
    'posts' => array('label' => '帖子管理', 'icon' => 'edit'),
    'users' => array('label' => '会员管理', 'icon' => 'members'),
    'login_logs' => array('label' => '流量统计', 'icon' => 'stats'),
    'operation_logs' => array('label' => '网站日志', 'icon' => 'logs'),
    'security' => array('label' => '安全策略', 'icon' => 'setting'),
);

$sidebarItems = array();
foreach ($sidebarMenuSpec as $menuCode => $menuMeta) {
    if (isset($menuItemsByCode[$menuCode])) {
        $sidebarItems[] = array_merge($menuItemsByCode[$menuCode], $menuMeta);
    }
}

$menuIconSvg = static function ($iconKey) {
    switch ((string) $iconKey) {
        case 'dashboard':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5.5A1.5 1.5 0 0 1 5.5 4h4A1.5 1.5 0 0 1 11 5.5v4A1.5 1.5 0 0 1 9.5 11h-4A1.5 1.5 0 0 1 4 9.5v-4Zm9 0A1.5 1.5 0 0 1 14.5 4h4A1.5 1.5 0 0 1 20 5.5v7A1.5 1.5 0 0 1 18.5 14h-4A1.5 1.5 0 0 1 13 12.5v-7ZM4 14.5A1.5 1.5 0 0 1 5.5 13h4A1.5 1.5 0 0 1 11 14.5v4A1.5 1.5 0 0 1 9.5 20h-4A1.5 1.5 0 0 1 4 18.5v-4Zm9 2A3.5 3.5 0 0 1 16.5 13h2a3.5 3.5 0 1 1 0 7h-2a3.5 3.5 0 1 1 0-7Z" fill="currentColor"/></svg>';
        case 'service':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4a8 8 0 0 0-8 8v3a3 3 0 0 0 3 3h1.25a1.25 1.25 0 0 0 1.25-1.25v-4.5A1.25 1.25 0 0 0 8.25 11H6.04A6 6 0 0 1 18 12v6h-4a1 1 0 1 0 0 2h4a2 2 0 0 0 2-2v-3.02A3 3 0 0 0 21 12v-.25A7.75 7.75 0 0 0 12 4Z" fill="currentColor"/></svg>';
        case 'setting':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m10.86 3.43-.44 1.77a6.9 6.9 0 0 0-1.52.88L7.2 5.3a1 1 0 0 0-1.28.15L4.45 6.92a1 1 0 0 0-.15 1.28l.78 1.7c-.37.47-.66.98-.88 1.52l-1.77.44A1 1 0 0 0 2 12.84v2.32a1 1 0 0 0 .77.98l1.77.44c.22.54.5 1.05.88 1.52l-.78 1.7a1 1 0 0 0 .15 1.28l1.47 1.47a1 1 0 0 0 1.28.15l1.7-.78c.47.37.98.66 1.52.88l.44 1.77a1 1 0 0 0 .98.77h2.32a1 1 0 0 0 .98-.77l.44-1.77c.54-.22 1.05-.5 1.52-.88l1.7.78a1 1 0 0 0 1.28-.15l1.47-1.47a1 1 0 0 0 .15-1.28l-.78-1.7c.37-.47.66-.98.88-1.52l1.77-.44a1 1 0 0 0 .77-.98v-2.32a1 1 0 0 0-.77-.98l-1.77-.44a6.9 6.9 0 0 0-.88-1.52l.78-1.7a1 1 0 0 0-.15-1.28l-1.47-1.47a1 1 0 0 0-1.28-.15l-1.7.78a6.9 6.9 0 0 0-1.52-.88l-.44-1.77a1 1 0 0 0-.98-.77h-2.32a1 1 0 0 0-.98.77ZM12 9a3 3 0 1 1 0 6 3 3 0 0 1 0-6Z" fill="currentColor"/></svg>';
        case 'ai':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a7 7 0 0 0-7 7v1.2a4.8 4.8 0 0 0 2.03 3.92l2.69 1.92a3.8 3.8 0 0 0 4.56 0l2.69-1.92A4.8 4.8 0 0 0 19 11.2V10a7 7 0 0 0-7-7Zm-1.75 6.5a1 1 0 1 1 0 2 1 1 0 0 1 0-2Zm3.5 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2ZM8 19.5h8a1 1 0 1 1 0 2H8a1 1 0 1 1 0-2Z" fill="currentColor"/></svg>';
        case 'refresh':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5a7 7 0 0 1 6.16 3.67V6.5a1 1 0 1 1 2 0V12a1 1 0 0 1-1 1h-5.5a1 1 0 1 1 0-2h3.3A5 5 0 1 0 17 16a1 1 0 1 1 2 0 7 7 0 1 1-7-11Z" fill="currentColor"/></svg>';
        case 'edit':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M15.17 4.41a2 2 0 0 1 2.83 0l1.59 1.59a2 2 0 0 1 0 2.83L10 18.41 5 19l.59-5 9.58-9.59ZM4 21h16a1 1 0 1 0 0-2H4a1 1 0 1 0 0 2Z" fill="currentColor"/></svg>';
        case 'members':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M16.5 11a3.5 3.5 0 1 0-2.45-5.99A4.48 4.48 0 0 1 14.5 7c0 1.34-.58 2.55-1.5 3.37A3.48 3.48 0 0 0 16.5 11ZM8.5 11A3.5 3.5 0 1 0 8.5 4a3.5 3.5 0 0 0 0 7Zm0 2C5.46 13 3 15.24 3 18v1a1 1 0 0 0 1 1h9a1 1 0 0 0 1-1v-1c0-2.76-2.46-5-5.5-5Zm8 0c-.74 0-1.44.12-2.09.35A6.96 6.96 0 0 1 17 18v1c0 .35-.04.69-.12 1H20a1 1 0 0 0 1-1v-.8c0-2.87-2.02-5.2-4.5-5.2Z" fill="currentColor"/></svg>';
        case 'stats':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 19h14a1 1 0 1 1 0 2H4a1 1 0 0 1-1-1V5a1 1 0 1 1 2 0v14Zm3-3.5a1 1 0 0 1-1-1v-3a1 1 0 1 1 2 0v3a1 1 0 0 1-1 1Zm4 0a1 1 0 0 1-1-1V8a1 1 0 1 1 2 0v6.5a1 1 0 0 1-1 1Zm4 0a1 1 0 0 1-1-1V10a1 1 0 1 1 2 0v4.5a1 1 0 0 1-1 1Z" fill="currentColor"/></svg>';
        case 'logs':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V9.41a2 2 0 0 0-.59-1.41l-3.41-3.41A2 2 0 0 0 13.59 4H7Zm2 5h6a1 1 0 1 1 0 2H9a1 1 0 1 1 0-2Zm0 4h6a1 1 0 1 1 0 2H9a1 1 0 1 1 0-2Z" fill="currentColor"/></svg>';
        default:
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="4" fill="currentColor"/></svg>';
    }
};

$buttonIconSvg = static function ($iconKey) {
    switch ((string) $iconKey) {
        case 'home':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 4.5 4 11v8a1 1 0 0 0 1 1h4.5a.5.5 0 0 0 .5-.5V15a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1v4.5a.5.5 0 0 0 .5.5H19a1 1 0 0 0 1-1v-8l-8-6.5Z" fill="currentColor"/></svg>';
        case 'logout':
            return '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 5a1 1 0 0 1 0 2H6v10h4a1 1 0 1 1 0 2H6a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2h4Zm5.3 2.3a1 1 0 0 1 1.4 0l3.99 4a1 1 0 0 1 0 1.4l-4 4a1 1 0 1 1-1.39-1.42L17.58 13H9a1 1 0 1 1 0-2h8.58l-2.29-2.29a1 1 0 0 1 0-1.41Z" fill="currentColor"/></svg>';
        default:
            return '';
    }
};

$adminBodyClasses = array('admin-body');
$adminBodyPage = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower((string) $activeMenuCode));
if ($adminBodyPage !== '') {
    $adminBodyClasses[] = 'admin-page-' . trim((string) $adminBodyPage, '-');
}
$adminCssUrl = asset('app.css?v=20260707-admin-modal-standard-01');
$adminJsUrl = asset('app.js?v=20260707-admin-modal-standard-01');
$needsAdminTinyMce = isset($needsAdminTinyMce) ? (bool) $needsAdminTinyMce : $activeMenuCode === 'draws';
$enableAdminUiSystem = false;
$appendAdminUiClasses = static function ($html, $classPattern, $classes) {
    return (string) preg_replace_callback(
        '/class="([^"]*\b(?:' . $classPattern . ')\b[^"]*)"/u',
        static function ($matches) use ($classes) {
            $classList = preg_split('/\s+/', trim((string) $matches[1]));
            $classList = is_array($classList) ? $classList : array();

            foreach (preg_split('/\s+/', trim((string) $classes)) as $className) {
                if ($className !== '' && !in_array($className, $classList, true)) {
                    $classList[] = $className;
                }
            }

            return 'class="' . e(implode(' ', $classList)) . '"';
        },
        (string) $html
    );
};
$adminUiContent = (string) $content;
if ($enableAdminUiSystem) {
    $adminUiContent = $appendAdminUiClasses($adminUiContent, 'admin-table-wrap|member-table-wrap|posts-table-wrap|draw-table-wrap|support-table-wrap|settings-table-wrap', 'ui-admin-table-wrap');
    $adminUiContent = $appendAdminUiClasses($adminUiContent, 'admin-table(?!-wrap)|member-table(?!-wrap)|posts-table(?!-wrap)|draw-table(?!-wrap)|support-table(?!-wrap)|settings-table(?!-wrap)', 'ui-admin-table');
    $adminUiContent = $appendAdminUiClasses($adminUiContent, 'admin-toolbar|member-list-toolbar|posts-toolbar|draw-toolbar|support-toolbar|settings-toolbar|filterbar|filter-bar', 'ui-admin-toolbar ui-admin-actions');
    $adminUiContent = $appendAdminUiClasses($adminUiContent, 'admin-actions|member-row-actions|member-edit-actions|posts-actions|draw-actions|support-actions|settings-actions', 'ui-admin-actions');
    $adminUiContent = $appendAdminUiClasses($adminUiContent, 'admin-card|member-console-card|posts-card|draw-card|support-card|settings-card', 'ui-admin-card');
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e($pageTitle); ?></title>
    <script>
    (function () {
        var index = 0;
        var key = '';

        try {
            if (window.location.search.indexOf('page=draws') === -1 || !window.sessionStorage) {
                return;
            }

            for (index = 0; index < window.sessionStorage.length; index += 1) {
                key = window.sessionStorage.key(index) || '';
                if (key.indexOf('draw-editor-fullscreen:') === 0 && window.sessionStorage.getItem(key) === '1') {
                    document.documentElement.classList.add('draw-editor-is-fullscreen-pending');
                    break;
                }
            }
        } catch (error) {
            // Ignore storage access errors and keep the default layout.
        }
    })();
    </script>
    <style>
    html.draw-editor-is-fullscreen-pending {
        overflow: hidden;
        background: #ffffff;
    }

    html.draw-editor-is-fullscreen-pending body {
        overflow: hidden;
        background: #ffffff;
    }

    html.draw-editor-is-fullscreen-pending .admin-frame-header,
    html.draw-editor-is-fullscreen-pending .admin-sidebar,
    html.draw-editor-is-fullscreen-pending .admin-page-title-shell,
    html.draw-editor-is-fullscreen-pending .admin-tip,
    html.draw-editor-is-fullscreen-pending .admin-editor-toolbar {
        display: none !important;
    }

    html.draw-editor-is-fullscreen-pending .admin-shell,
    html.draw-editor-is-fullscreen-pending .admin-stage,
    html.draw-editor-is-fullscreen-pending .admin-workspace,
    html.draw-editor-is-fullscreen-pending .admin-main,
    html.draw-editor-is-fullscreen-pending .admin-content-shell {
        width: 100%;
        max-width: none;
        margin: 0;
        padding: 0;
        gap: 0;
        border: 0;
        border-radius: 0;
        box-shadow: none;
    }

    html.draw-editor-is-fullscreen-pending .admin-stage,
    html.draw-editor-is-fullscreen-pending .admin-shell {
        height: 100vh;
        min-height: 100vh;
        overflow: hidden;
    }

    html.draw-editor-is-fullscreen-pending .admin-editor-shell {
        position: fixed;
        inset: 0;
        z-index: 1000;
        margin: 0;
        padding: 0;
        border: 0;
        border-radius: 0;
        background: #ffffff;
    }

    html.draw-editor-is-fullscreen-pending #draw-material-editor {
        min-height: 100vh;
        visibility: hidden;
    }
    </style>
    <link rel="preload" href="<?php echo e($adminCssUrl); ?>" as="style">
    <link rel="preload" href="<?php echo e($adminJsUrl); ?>" as="script">
    <?php if ($needsAdminTinyMce): ?>
        <link rel="preload" href="<?php echo e(asset('vendor/tinymce/tinymce.min.js?v=8.4.0-local')); ?>" as="script">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo e($adminCssUrl); ?>">
    <?php if ($enableAdminUiSystem): ?>
        <link rel="stylesheet" href="/public/assets/ui-system.css?v=20260628-css-repair-01">
        <link rel="stylesheet" href="/public/assets/ui-override.css?v=20260628-css-governance-01">
    <?php endif; ?>
</head>
<body class="<?php echo e(trim(implode(' ', $adminBodyClasses) . ($enableAdminUiSystem ? ' ui-admin-page' : ''))); ?>">
<div class="admin-shell">
    <div class="admin-stage">
        <header class="admin-frame-header<?php echo $enableAdminUiSystem ? ' ui-admin-header' : ''; ?>" role="banner">
            <button class="admin-frame-btn admin-mobile-nav-toggle" type="button" data-admin-nav-drawer-toggle aria-controls="admin-nav-drawer" aria-expanded="false" aria-label="打开后台导航">
                <span class="admin-mobile-nav-toggle-icon" aria-hidden="true"><span></span><span></span><span></span></span>
                <span>菜单</span>
            </button>
            <div class="admin-frame-brand" aria-label="后台管理">
                <div class="admin-frame-brand-title">后台管理</div>
                <div class="admin-frame-brand-site"><?php echo e(admin_management_name_setting(site_setting('site.name', app()->config('app', 'site_name', '')))); ?></div>
            </div>
            <?php if ($adminAccountCanManage): ?>
                <button class="admin-frame-account is-clickable" type="button" data-admin-account-modal-open aria-haspopup="dialog" aria-controls="admin-account-modal" aria-expanded="false" aria-label="打开管理账号设置">
                    <span class="admin-frame-account-row admin-frame-account-role">
                        <span class="admin-frame-account-label">当前</span>
                        <strong class="admin-frame-account-value"><?php echo e(isset($currentAdmin['role_name']) ? (string) $currentAdmin['role_name'] : '管理员'); ?></strong>
                    </span>
                    <span class="admin-frame-account-row admin-frame-account-name">
                        <span class="admin-frame-account-label">账号</span>
                        <span class="admin-frame-account-value"><?php echo e(isset($currentAdmin['username']) ? (string) $currentAdmin['username'] : '-'); ?></span>
                    </span>
                </button>
            <?php else: ?>
                <div class="admin-frame-account" aria-label="当前后台账号">
                    <div class="admin-frame-account-row admin-frame-account-role">
                        <span class="admin-frame-account-label">当前</span>
                        <strong class="admin-frame-account-value"><?php echo e(isset($currentAdmin['role_name']) ? (string) $currentAdmin['role_name'] : '管理员'); ?></strong>
                    </div>
                    <div class="admin-frame-account-row admin-frame-account-name">
                        <span class="admin-frame-account-label">账号</span>
                        <span class="admin-frame-account-value"><?php echo e(isset($currentAdmin['username']) ? (string) $currentAdmin['username'] : '-'); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            <div class="admin-header-draw-slot" aria-label="<?php echo e($adminHeaderRegion === 'hongkong' ? '香港开奖结果' : '澳门开奖结果'); ?>">
                <?php echo $adminHeaderDrawHtml; ?>
            </div>
            <nav class="admin-frame-actions<?php echo $enableAdminUiSystem ? ' ui-admin-actions' : ''; ?>" aria-label="后台快捷操作">
                <a class="admin-frame-btn is-blue" href="<?php echo e(public_url('index.php')); ?>" target="_blank" rel="noopener noreferrer">
                    <span class="admin-frame-btn-icon"><?php echo $buttonIconSvg('home'); ?></span>
                    <span>首页</span>
                </a>
                <a class="admin-frame-btn is-red" href="<?php echo e(public_url('admin.php') . '?logout=1'); ?>">
                    <span class="admin-frame-btn-icon"><?php echo $buttonIconSvg('logout'); ?></span>
                    <span>退出</span>
                </a>
            </nav>
        </header>

        <div class="admin-frame-divider"></div>

        <div class="admin-workspace">
            <aside class="admin-sidebar" id="admin-nav-drawer" data-admin-nav-drawer>
                <div class="admin-drawer-head">
                    <span>后台导航</span>
                    <button type="button" data-admin-nav-drawer-close>关闭</button>
                </div>
                <nav class="admin-nav">
                    <?php foreach ($sidebarItems as $menuItem): ?>
                        <?php
                        $menuCode = (string) ($menuItem['code'] ?? '');
                        $isActive = $activeMenuCode === $menuCode;
                        ?>
                        <a class="admin-nav-link<?php echo $isActive ? ' is-active' : ''; ?>" href="<?php echo e(public_url('admin.php') . '?page=' . urlencode((string) ($menuItem['route_path'] ?? 'dashboard'))); ?>" data-admin-nav-item="<?php echo e($menuCode); ?>" data-flood-guard="off"<?php echo $isActive ? ' aria-current="page"' : ''; ?>>
                            <span class="admin-menu-icon"><?php echo $menuIconSvg((string) ($menuItem['icon'] ?? 'dot')); ?></span>
                            <span class="admin-menu-label"><?php echo e((string) ($menuItem['label'] ?? '')); ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </aside>
            <div class="admin-drawer-backdrop" data-admin-nav-drawer-backdrop hidden></div>
            <?php if ($adminAccountCanManage): ?>
                <div class="admin-account-modal admin-modal" id="admin-account-modal" data-admin-account-modal hidden>
                    <div class="admin-account-modal-backdrop admin-modal-backdrop" data-admin-account-modal-close></div>
                    <div class="admin-account-modal-card admin-modal-card admin-modal-card--md" role="dialog" aria-modal="true" aria-labelledby="admin-account-modal-title">
                        <div class="admin-account-modal-head admin-modal-head">
                            <div class="admin-modal-heading">
                                <div class="admin-modal-title-row">
                                    <h2 class="admin-modal-title" id="admin-account-modal-title">管理账号</h2>
                                </div>
                                <p class="admin-modal-subtitle">共 <?php echo e((string) $adminAccountAdminCount); ?> 位管理人员</p>
                            </div>
                            <div class="admin-modal-head-actions">
                                <button class="admin-account-modal-close admin-modal-close" type="button" data-admin-account-modal-close aria-label="关闭">×</button>
                            </div>
                        </div>

                        <div class="admin-account-tabs admin-modal-tabs" role="tablist" aria-label="管理账号操作">
                            <button class="admin-account-tab admin-modal-tab is-active" type="button" data-admin-account-tab="current" role="tab" aria-selected="true">当前账号</button>
                            <button class="admin-account-tab admin-modal-tab" type="button" data-admin-account-tab="manage" role="tab" aria-selected="false">管理人员 <?php echo e((string) $adminAccountAdminCount); ?></button>
                            <button class="admin-account-tab admin-modal-tab" type="button" data-admin-account-tab="create" role="tab" aria-selected="false">新增管理人员</button>
                        </div>

                        <div class="admin-account-panel admin-modal-body is-active" data-admin-account-panel="current">
                            <form method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form data-reload-current="1" class="admin-account-form">
                                <input type="hidden" name="action" value="admin.admin.save">
                                <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                                <input type="hidden" name="id" value="<?php echo e((string) ((int) ($currentAdmin['id'] ?? 0))); ?>">
                                <input type="hidden" name="role_id" value="<?php echo e((string) $adminAccountCurrentRoleId); ?>">
                                <input type="hidden" name="status" value="<?php echo e((string) ((int) ($currentAdmin['status'] ?? 1))); ?>">
                                <input type="hidden" name="real_name" value="<?php echo e((string) ($currentAdmin['real_name'] ?? '')); ?>">
                                <input type="hidden" name="nickname" value="<?php echo e((string) ($currentAdmin['nickname'] ?? '')); ?>">
                                <input type="hidden" name="mobile" value="<?php echo e((string) ($currentAdmin['mobile'] ?? '')); ?>">
                                <input type="hidden" name="email" value="<?php echo e((string) ($currentAdmin['email'] ?? '')); ?>">
                                <input type="hidden" name="remark" value="<?php echo e((string) ($currentAdmin['remark'] ?? '')); ?>">
                                <div data-form-error class="admin-account-form-error hidden"></div>

                                <label class="admin-account-field">
                                    <span>管理账号</span>
                                    <input class="admin-input" name="username" value="<?php echo e((string) ($currentAdmin['username'] ?? '')); ?>" autocomplete="username">
                                </label>
                                <label class="admin-account-field">
                                    <span>登录密码</span>
                                    <input class="admin-input" type="password" name="password" autocomplete="new-password" placeholder="留空不修改">
                                </label>
                                <div class="admin-account-actions">
                                    <button class="admin-button" type="submit">保存当前账号</button>
                                </div>
                            </form>
                        </div>

                        <div class="admin-account-panel admin-modal-body" data-admin-account-panel="manage" hidden>
                            <div class="admin-account-summary">
                                <strong><?php echo e((string) $adminAccountAdminCount); ?></strong>
                                <span>位管理人员</span>
                            </div>
                            <?php if ($adminAccountAdmins): ?>
                                <div class="admin-account-list">
                                    <?php foreach ($adminAccountAdmins as $adminAccountRow): ?>
                                        <?php
                                        $adminAccountRowId = (int) ($adminAccountRow['id'] ?? 0);
                                        $adminAccountRowPayload = array(
                                            'id' => $adminAccountRowId,
                                            'username' => (string) ($adminAccountRow['username'] ?? ''),
                                            'real_name' => (string) ($adminAccountRow['real_name'] ?? ''),
                                            'nickname' => (string) ($adminAccountRow['nickname'] ?? ''),
                                            'mobile' => (string) ($adminAccountRow['mobile'] ?? ''),
                                            'email' => (string) ($adminAccountRow['email'] ?? ''),
                                            'role_id' => (int) ($adminAccountRow['role_id'] ?? 0),
                                            'status' => (int) ($adminAccountRow['status'] ?? 1),
                                            'remark' => (string) ($adminAccountRow['remark'] ?? ''),
                                        );
                                        $adminAccountRowJson = json_encode($adminAccountRowPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
                                        $adminAccountRowLocked = $adminAccountRowId === (int) ($currentAdmin['id'] ?? 0) || (int) ($adminAccountRow['is_super'] ?? 0) === 1;
                                        ?>
                                        <div class="admin-account-list-item">
                                            <div class="admin-account-list-main">
                                                <strong><?php echo e((string) ($adminAccountRow['username'] ?? '-')); ?></strong>
                                                <span><?php echo e((string) ($adminAccountRow['role_name'] ?? '管理员')); ?> · <?php echo (int) ($adminAccountRow['status'] ?? 1) === 1 ? '启用' : '停用'; ?></span>
                                            </div>
                                            <div class="admin-account-list-actions">
                                                <button class="admin-button is-light" type="button" data-admin-account-edit data-admin-account-json="<?php echo e((string) $adminAccountRowJson); ?>">编辑</button>
                                                <?php if ($adminAccountRowLocked): ?>
                                                    <button class="admin-button is-ghost" type="button" disabled>保护</button>
                                                <?php else: ?>
                                                    <form method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form data-reload-current="1" data-confirm="确认删除该管理员吗？">
                                                        <input type="hidden" name="action" value="admin.admin.delete">
                                                        <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                                                        <input type="hidden" name="target_id" value="<?php echo e((string) $adminAccountRowId); ?>">
                                                        <button class="admin-button is-danger" type="submit">删除</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="admin-empty">暂无管理人员。</div>
                            <?php endif; ?>
                        </div>

                        <div class="admin-account-panel admin-modal-body" data-admin-account-panel="edit" hidden>
                            <form method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form data-reload-current="1" class="admin-account-form" data-admin-account-edit-form>
                                <input type="hidden" name="action" value="admin.admin.save">
                                <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                                <input type="hidden" name="id" value="0">
                                <div data-form-error class="admin-account-form-error hidden"></div>

                                <div class="admin-account-form-grid">
                                    <label class="admin-account-field">
                                        <span>管理账号</span>
                                        <input class="admin-input" name="username" autocomplete="username">
                                    </label>
                                    <label class="admin-account-field">
                                        <span>登录密码</span>
                                        <input class="admin-input" type="password" name="password" autocomplete="new-password" placeholder="留空不修改">
                                    </label>
                                </div>
                                <div class="admin-account-form-grid">
                                    <label class="admin-account-field">
                                        <span>姓名</span>
                                        <input class="admin-input" name="real_name" autocomplete="name">
                                    </label>
                                    <label class="admin-account-field">
                                        <span>角色</span>
                                        <select class="admin-select" name="role_id">
                                            <option value="0">请选择角色</option>
                                            <?php foreach ($adminAccountRoles as $adminAccountRole): ?>
                                                <option value="<?php echo e((string) ((int) ($adminAccountRole['id'] ?? 0))); ?>">
                                                    <?php echo e((string) ($adminAccountRole['name'] ?? '管理员')); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                                <div class="admin-account-form-grid">
                                    <label class="admin-account-field">
                                        <span>昵称</span>
                                        <input class="admin-input" name="nickname">
                                    </label>
                                    <label class="admin-account-field">
                                        <span>状态</span>
                                        <select class="admin-select" name="status">
                                            <option value="1">启用</option>
                                            <option value="0">停用</option>
                                        </select>
                                    </label>
                                </div>
                                <div class="admin-account-form-grid">
                                    <label class="admin-account-field">
                                        <span>手机号码</span>
                                        <input class="admin-input" name="mobile" autocomplete="tel">
                                    </label>
                                    <label class="admin-account-field">
                                        <span>邮箱地址</span>
                                        <input class="admin-input" name="email" autocomplete="email">
                                    </label>
                                </div>
                                <label class="admin-account-field">
                                    <span>备注</span>
                                    <textarea class="admin-textarea" name="remark" rows="3"></textarea>
                                </label>
                                <div class="admin-account-actions">
                                    <button class="admin-button is-light" type="button" data-admin-account-panel-open="manage">返回列表</button>
                                    <button class="admin-button" type="submit">保存管理员</button>
                                </div>
                            </form>
                        </div>

                        <div class="admin-account-panel admin-modal-body" data-admin-account-panel="create" hidden>
                            <form method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form data-reload-current="1" class="admin-account-form">
                                <input type="hidden" name="action" value="admin.admin.save">
                                <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                                <input type="hidden" name="id" value="0">
                                <input type="hidden" name="status" value="1">
                                <div data-form-error class="admin-account-form-error hidden"></div>

                                <div class="admin-account-form-grid">
                                    <label class="admin-account-field">
                                        <span>管理账号</span>
                                        <input class="admin-input" name="username" autocomplete="username">
                                    </label>
                                    <label class="admin-account-field">
                                        <span>登录密码</span>
                                        <input class="admin-input" type="password" name="password" autocomplete="new-password">
                                    </label>
                                </div>
                                <div class="admin-account-form-grid">
                                    <label class="admin-account-field">
                                        <span>姓名</span>
                                        <input class="admin-input" name="real_name" autocomplete="name">
                                    </label>
                                    <label class="admin-account-field">
                                        <span>角色</span>
                                        <select class="admin-select" name="role_id">
                                            <option value="0">请选择角色</option>
                                            <?php foreach ($adminAccountRoles as $adminAccountRole): ?>
                                                <option value="<?php echo e((string) ((int) ($adminAccountRole['id'] ?? 0))); ?>" <?php echo (int) ($adminAccountRole['id'] ?? 0) === $adminAccountDefaultRoleId ? 'selected' : ''; ?>>
                                                    <?php echo e((string) ($adminAccountRole['name'] ?? '管理员')); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                </div>
                                <label class="admin-account-field">
                                    <span>备注</span>
                                    <textarea class="admin-textarea" name="remark" rows="3"></textarea>
                                </label>
                                <div class="admin-account-actions">
                                    <button class="admin-button" type="submit">新增管理人员</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <main class="admin-main">
                <div class="admin-content-shell">
                    <div class="<?php echo e($pageTitleShellClass . ($enableAdminUiSystem ? ' ui-admin-header' : '')); ?>">
                        <div class="admin-page-title-text-slot">
                            <span class="admin-page-title-dot"></span>
                            <span class="admin-page-title-label"><?php echo e($pageHeading); ?></span>
                        </div>
                        <?php if ($pageTitleActionHtml !== ''): ?>
                            <div class="admin-page-title-action-slot"><?php echo $pageTitleActionHtml; ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($flashMessage['message'])): ?>
                        <div hidden data-app-notice-seed data-app-notice-type="<?php echo e(isset($flashMessage['type']) ? (string) $flashMessage['type'] : 'info'); ?>" data-app-notice-message="<?php echo e((string) $flashMessage['message']); ?>"></div>
                    <?php endif; ?><?php if (!empty($pageError)): ?>
                        <div hidden data-app-notice-seed data-app-notice-type="error" data-app-notice-message="<?php echo e((string) $pageError); ?>"></div>
                    <?php endif; ?>
                    <section class="admin-page-surface<?php echo $enableAdminUiSystem ? ' ui-admin-card' : ''; ?>" aria-label="<?php echo e($pageHeading); ?>">
                        <div class="admin-page-body">
                            <?php echo ltrim($adminUiContent); ?>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>
</div>
<script src="<?php echo e($adminJsUrl); ?>" defer></script>
</body>
</html>
