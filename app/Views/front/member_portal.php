<?php echo \App\Core\View::make(app(), 'partials/front_top_bar', array('region' => $region)); ?>
<?php
$authTitles = array(
    'login' => '会员登录',
    'register' => '会员注册',
    'reset' => '找回密码',
);
$authSubtitles = array(
    'login' => '输入账号密码，进入会员中心。',
    'register' => '创建账号，并设置找回验证信息。',
    'reset' => '核对找回信息，重新设置密码。',
);
$authActions = array(
    'login' => 'auth.login',
    'register' => 'auth.register',
    'reset' => 'password_reset.verify_reset',
);
$authButtons = array(
    'login' => '立即登录',
    'register' => '注册并登录',
    'reset' => '重置密码',
);
$authTitle = isset($authTitles[$mode]) ? $authTitles[$mode] : $authTitles['login'];
$authSubtitle = isset($authSubtitles[$mode]) ? $authSubtitles[$mode] : $authSubtitles['login'];
$authAction = isset($authActions[$mode]) ? $authActions[$mode] : $authActions['login'];
$authButton = isset($authButtons[$mode]) ? $authButtons[$mode] : $authButtons['login'];
$memberLogoutUrl = $user ? public_url('member.php') . '?logout=1&region=' . urlencode($region) : '';
$inviteCode = trim((string) ($inviteCode ?? ''));
$authTabUrl = static function ($targetMode) use ($region, $inviteCode) {
    $url = public_url('member.php')
        . '?region=' . urlencode($region)
        . '&mode=' . urlencode((string) $targetMode);
    if ((string) $targetMode === 'register' && $inviteCode !== '') {
        $url .= '&invite=' . urlencode($inviteCode);
    }

    return $url;
};
$memberFrameClass = !$user ? 'member-auth-frame' : '';
$memberIconSvg = static function ($name) {
    if ($name === 'brain') {
        return '<i class="front-fa-icon front-icon-brain fa-solid fa-brain" aria-hidden="true"></i>';
    }

    $icons = array(
        'arrow-right' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M13.6 5.4 20.2 12l-6.6 6.6-1.42-1.42L16.38 13H4v-2h12.38l-4.2-4.18 1.42-1.42Z"/></svg>',
        'circle-info' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 10h2v8h-2v-8Zm0-4h2v2h-2V6Zm1-4a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16Z"/></svg>',
        'circle-user' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 4.2a3.2 3.2 0 1 1 0 6.4 3.2 3.2 0 0 1 0-6.4Zm0 13.8a7.95 7.95 0 0 1-5.6-2.28c.8-2.2 2.86-3.52 5.6-3.52s4.8 1.32 5.6 3.52A7.95 7.95 0 0 1 12 20Z"/></svg>',
        'id-card' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 2v10h16V7H4Zm3 2h5v2H7V9Zm0 4h7v2H7v-2Zm10-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm2 5h-8c.48-1.62 1.9-2.6 4-2.6s3.52.98 4 2.6Z"/></svg>',
        'receipt' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 3h14v18l-2.4-1.2-2.3 1.2-2.3-1.2L9.7 21l-2.3-1.2L5 21V3Zm2 3v11.76l.4-.2 2.3 1.2 2.3-1.2 2.3 1.2 2.3-1.2.4.2V6H7Zm2 3h6v2H9V9Zm0 4h8v2H9v-2Z"/></svg>',
        'rotate' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17.65 6.35A7.95 7.95 0 0 0 12 4a8 8 0 1 0 7.75 10h-2.1A6 6 0 1 1 12 6c1.66 0 3.14.69 4.22 1.78L13 11h8V3l-3.35 3.35Z"/></svg>',
        'shield-halved' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2 4 5.2v5.9c0 5.05 3.38 9.78 8 10.9 4.62-1.12 8-5.85 8-10.9V5.2L12 2Zm0 2.18 6 2.4v4.52c0 3.82-2.38 7.42-6 8.8V4.18Zm-2 15.1c-2.48-1.48-4-4.52-4-8.18V6.58l4-1.6v14.3Z"/></svg>',
        'user-check' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-3.3 0-6 1.7-6 4v2h9.6a6.45 6.45 0 0 1-.6-2.7c0-1.18.32-2.3.88-3.25A10.7 10.7 0 0 0 10 13Zm10.7 1.7-1.4-1.4-3.05 3.04-1.25-1.24-1.4 1.4 2.65 2.64 4.45-4.44Z"/></svg>',
        'user-plus' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M10 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0 2c-3.3 0-6 1.7-6 4v2h9.2a5.9 5.9 0 0 1-.2-1.5c0-1.78.78-3.38 2.02-4.48A12.2 12.2 0 0 0 10 13Zm9 0h-2v3h-3v2h3v3h2v-3h3v-2h-3v-3Z"/></svg>',
        'wallet' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h14a2 2 0 0 1 2 2v2h-2V7H4v10h14v-2h2v2a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm12 5h6v4h-6a2 2 0 0 1 0-4Zm0 1.5a.5.5 0 0 0 0 1h4v-1h-4Z"/></svg>',
    );
    $key = isset($icons[$name]) ? $name : 'circle-user';

    return '<i class="front-fa-icon front-icon-' . e($key) . '" aria-hidden="true">' . $icons[$key] . '</i>';
};
$memberForumGuideRules = app()->support()->forumGuideRules();
$memberForumGuideBodyHtml = static function ($key) use ($memberForumGuideRules) {
    $text = (string) ($memberForumGuideRules[$key] ?? '');
    $lines = preg_split('/\r\n|\r|\n/u', trim($text));
    $items = array();
    foreach ($lines as $line) {
        $line = trim((string) $line);
        if ($line !== '') {
            $items[] = $line;
        }
    }

    if (!$items) {
        return '';
    }

    if (count($items) === 1) {
        return '<p>' . e($items[0]) . '</p>';
    }

    $html = '<div class="member-about-points">';
    foreach ($items as $item) {
        $html .= '<span>' . e($item) . '</span>';
    }
    $html .= '</div>';

    return $html;
};
?>
<section class="front-page-shell front-unified-page member-page<?php echo !$user ? ' member-auth-shell member-auth-shell--' . e($mode) : ''; ?>">
    <div class="section-title member-section-title <?php echo $user ? 'bg-gradient-to-r from-indigo-600 to-blue-500' : 'member-auth-titlebar'; ?>">
        <span class="member-section-title-main"><?php echo $memberIconSvg($user ? 'circle-user' : 'shield-halved'); ?><?php echo $user ? '会员中心' : $authTitle; ?></span>
        <?php if ($user): ?>
            <a
                class="member-title-logout"
                href="<?php echo e($memberLogoutUrl); ?>"
                data-confirm-link="确认退出当前账号吗？"
                data-no-prefetch
            >退出登录</a>
        <?php endif; ?>
    </div>
    <div class="data-frame front-panel-stack front-unified-frame <?php echo $memberFrameClass; ?>"<?php echo !$user ? ' data-member-auth-mode="' . e($mode) . '"' : ''; ?>>
        <?php if (!$user): ?>
            <div class="member-auth-card front-auth-card front-standard-panel">
                <div class="member-auth-head front-auth-head">
                    <div>
                        <div class="member-auth-kicker"><?php echo $region === 'hongkong' ? '香港论坛' : '澳门论坛'; ?></div>
                        <h1 id="member-auth-title" class="member-auth-heading"><?php echo e($authTitle); ?></h1>
                        <p class="member-auth-copy"><?php echo e($authSubtitle); ?></p>
                    </div>
                    <div class="member-auth-mark" aria-hidden="true">
                        <?php echo $memberIconSvg('user-check'); ?>
                    </div>
                </div>
                <div class="member-auth-tabs" role="navigation" aria-label="会员入口">
                    <a
                        href="<?php echo e($authTabUrl('login')); ?>"
                        class="member-auth-tab <?php echo $mode === 'login' ? 'is-active' : ''; ?>"
                        data-no-prefetch
                        <?php echo $mode === 'login' ? 'aria-current="page"' : ''; ?>
                    >登录</a>
                    <a
                        href="<?php echo e($authTabUrl('register')); ?>"
                        class="member-auth-tab <?php echo $mode === 'register' ? 'is-active' : ''; ?>"
                        data-no-prefetch
                        <?php echo $mode === 'register' ? 'aria-current="page"' : ''; ?>
                    >注册</a>
                    <a
                        href="<?php echo e($authTabUrl('reset')); ?>"
                        class="member-auth-tab <?php echo $mode === 'reset' ? 'is-active' : ''; ?>"
                        data-no-prefetch
                        <?php echo $mode === 'reset' ? 'aria-current="page"' : ''; ?>
                    >找回</a>
                </div>
                <form method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form<?php echo $mode === 'login' ? ' data-reload-current="1"' : ($mode === 'register' ? ' data-immediate-redirect="1"' : ''); ?> class="front-auth-form member-auth-form member-auth-form--<?php echo e($mode); ?>" aria-labelledby="member-auth-title">
                    <input type="hidden" name="action" value="<?php echo e($authAction); ?>">
                    <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                    <input type="hidden" name="region" value="<?php echo e($region); ?>">
                    <div class="member-auth-field member-auth-field--account">
                        <label class="member-auth-label">会员账号</label>
                        <input class="auth-input member-auth-input" type="text" name="username" autocomplete="username"<?php echo $mode === 'register' ? ' inputmode="latin" pattern="[A-Za-z0-9]{3,16}" maxlength="16" title="会员账号需为 3-16 位字母或数字"' : ''; ?> placeholder="<?php echo e($mode === 'register' ? '请输入 3-16 位字母或数字账号' : '请输入会员账号'); ?>">
                    </div>
                    <?php if ($mode === 'login'): ?>
                        <div class="member-auth-field member-auth-field--password">
                            <label class="member-auth-label">登录密码</label>
                            <input class="auth-input member-auth-input" type="password" name="password" autocomplete="current-password" placeholder="请输入登录密码">
                        </div>
                    <?php elseif ($mode === 'register'): ?>
                        <div class="member-auth-field member-auth-field--password">
                            <label class="member-auth-label">设置密码</label>
                            <input class="auth-input member-auth-input" type="password" name="password" autocomplete="new-password" placeholder="请输入 6-20 位密码">
                        </div>
                        <div class="member-auth-field member-auth-field--confirm-password">
                            <label class="member-auth-label">确认密码</label>
                            <input class="auth-input member-auth-input" type="password" name="confirm_password" autocomplete="new-password" placeholder="请再次输入注册密码">
                        </div>
                        <div class="member-auth-field member-auth-field--recovery">
                            <label class="member-auth-label">找回验证信息</label>
                            <input class="auth-input member-auth-input" type="text" name="recovery_answer" autocomplete="off" placeholder="请设置一条便于记忆的信息">
                        </div>
                        <div class="member-auth-field member-auth-field--invite">
                            <label class="member-auth-label">邀请人账号</label>
                            <input class="auth-input member-auth-input" type="text" name="invite_code" value="<?php echo e($inviteCode); ?>" autocomplete="off" placeholder="没有邀请人可留空">
                        </div>
                    <?php else: ?>
                        <div class="member-auth-field member-auth-field--recovery">
                            <label class="member-auth-label">找回验证信息</label>
                            <input class="auth-input member-auth-input" type="text" name="recovery_answer" autocomplete="off" placeholder="请输入注册时设置的信息">
                        </div>
                        <div class="member-auth-field member-auth-field--password">
                            <label class="member-auth-label">设置新密码</label>
                            <input class="auth-input member-auth-input" type="password" name="password" autocomplete="new-password" placeholder="请输入新的登录密码">
                        </div>
                        <div class="member-auth-field member-auth-field--confirm-password">
                            <label class="member-auth-label">确认新密码</label>
                            <input class="auth-input member-auth-input" type="password" name="confirm_password" autocomplete="new-password" placeholder="请再次输入新密码">
                        </div>
                    <?php endif; ?>
                    <div class="front-form-actions member-auth-actions">
                        <button type="submit" class="member-auth-submit">
                            <span><?php echo e($authButton); ?></span>
                            <?php echo $memberIconSvg('arrow-right'); ?>
                        </button>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <?php
            $memberRechargeUrl = public_url('service.php') . '?region=' . urlencode($region);
            $memberRechargePaymentSettings = isset($memberRechargePaymentSettings) && is_array($memberRechargePaymentSettings)
                ? $memberRechargePaymentSettings
                : array();
            $memberRechargeBlankQr = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
            $memberRechargeQrVersion = (string) time();
            $memberRechargeQrSrc = static function ($url) use ($memberRechargeBlankQr, $memberRechargeQrVersion) {
                $url = trim((string) $url);
                if ($url === '') {
                    return $memberRechargeBlankQr;
                }

                return $url . (strpos($url, '?') === false ? '?' : '&') . 'qr_v=' . rawurlencode($memberRechargeQrVersion);
            };
            $memberRechargeStoredQrLists = array();
            try {
                $memberRechargeStoredQrLists = app()->support()->paymentQrLists();
            } catch (\Throwable $exception) {
                $memberRechargeStoredQrLists = array();
            }
            $memberRechargeQrLists = array();
            $memberRechargeQrListJson = array();
            foreach (array('alipay', 'wechat', 'usdt') as $memberRechargeQrType) {
                $memberRechargeQrList = array();
                if (isset($memberRechargeStoredQrLists[$memberRechargeQrType])
                    && is_array($memberRechargeStoredQrLists[$memberRechargeQrType])
                ) {
                    foreach ($memberRechargeStoredQrLists[$memberRechargeQrType] as $memberRechargeQrUrl) {
                        $memberRechargeQrUrl = trim((string) $memberRechargeQrUrl);
                        if ($memberRechargeQrUrl !== '') {
                            $memberRechargeQrList[] = $memberRechargeQrUrl;
                        }
                    }
                }
                $memberRechargeQrLists[$memberRechargeQrType] = array_values(array_unique($memberRechargeQrList));
                $memberRechargeEncodedQrList = json_encode($memberRechargeQrLists[$memberRechargeQrType], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $memberRechargeQrListJson[$memberRechargeQrType] = is_string($memberRechargeEncodedQrList) ? $memberRechargeEncodedQrList : '[]';
            }
            $memberRechargeAlipayQr = isset($memberRechargeQrLists['alipay'][0]) ? $memberRechargeQrLists['alipay'][0] : '';
            $memberRechargeWechatQr = isset($memberRechargeQrLists['wechat'][0]) ? $memberRechargeQrLists['wechat'][0] : '';
            $memberRechargeUsdtQr = isset($memberRechargeQrLists['usdt'][0]) ? $memberRechargeQrLists['usdt'][0] : '';
            $memberRechargeAlipaySrc = $memberRechargeQrSrc($memberRechargeAlipayQr);
            $memberRechargeWechatSrc = $memberRechargeQrSrc($memberRechargeWechatQr);
            $memberRechargeUsdtSrc = $memberRechargeQrSrc($memberRechargeUsdtQr);
            $memberRechargeUsdtAddress = trim((string) (isset($memberRechargePaymentSettings['usdt_address']) ? $memberRechargePaymentSettings['usdt_address'] : ''));
            $memberRechargeUsdtAddressText = $memberRechargeUsdtAddress !== '' ? $memberRechargeUsdtAddress : '请联系客服获取 USDT 地址';
            $memberLogCount = count($predictionLogs);
            $purchaseRecords = is_array($purchaseRecords ?? null) ? $purchaseRecords : array();
            $memberPurchaseCount = count($purchaseRecords);
            $memberTab = isset($memberTab) ? (string) $memberTab : 'ai';
            if ($memberTab === 'recharge') {
                $memberTab = 'about';
            }
            $memberTab = in_array($memberTab, array('ai', 'purchases', 'about', 'profile'), true) ? $memberTab : 'ai';
            $memberTabUrl = static function ($tab) use ($region) {
                return public_url('member.php') . '?region=' . urlencode($region) . '&tab=' . urlencode((string) $tab);
            };
            $memberScoreRefreshUrl = $memberTabUrl($memberTab) . '&score_refresh=' . urlencode((string) time());
            $memberAvatar = trim((string) ($user['avatar'] ?? ''));
            $memberRoleLabel = trim((string) ($user['role_name'] ?? ''));
            $memberRoleLabel = $memberRoleLabel !== '' ? $memberRoleLabel : '普通会员';
            $memberInviteUrl = public_url('member.php')
                . '?region=' . urlencode($region)
                . '&mode=register&invite=' . urlencode((string) ($user['username'] ?? ''));
            ?>
            <div class="member-console">
                <section class="member-console-hero">
                    <div class="member-console-top">
                        <div class="member-console-main">
                            <div class="member-console-avatar">
                                <?php if ($memberAvatar !== ''): ?>
                                    <img
                                        src="<?php echo e($memberAvatar); ?>"
                                        alt="会员头像"
                                        width="72"
                                        height="72"
                                        loading="lazy"
                                        decoding="async"
                                    >
                                <?php else: ?>
                                    <?php echo $memberIconSvg('circle-user'); ?>
                                <?php endif; ?>
                                <span class="member-console-avatar-level" data-avatar-level="<?php echo e($memberRoleLabel); ?>"><?php echo e($memberRoleLabel); ?></span>
                            </div>
                            <div class="member-console-identity">
                                <div class="member-console-name"><?php echo e($user['username']); ?></div>
                                <div class="member-console-meta">
                                    <span><?php echo e($memberRoleLabel); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="member-console-status">
                            <span>最近登录</span>
                            <strong><?php echo e(!empty($user['last_login_at']) ? format_datetime($user['last_login_at']) : '暂无记录'); ?></strong>
                        </div>
                        <div class="member-console-info-row">
                            <div class="member-console-score">
                                <span>积分：</span>
                                <strong><?php echo e((string) $user['score']); ?></strong>
                                <a
                                    class="member-console-score-refresh"
                                    href="<?php echo e($memberScoreRefreshUrl); ?>"
                                    data-no-prefetch
                                    aria-label="刷新积分"
                                    title="刷新积分"
                                >
                                    <?php echo $memberIconSvg('rotate'); ?>
                                </a>
                            </div>
                            <button type="button" class="member-console-recharge" data-member-recharge-open="member-recharge-modal">充值中心</button>
                        </div>
                    </div>
                </section>
                <nav class="member-console-links" aria-label="会员功能链路">
                    <a
                        href="<?php echo e($memberTabUrl('ai')); ?>"
                        class="member-console-link <?php echo $memberTab === 'ai' ? 'is-active' : ''; ?>"
                        data-no-prefetch
                        <?php echo $memberTab === 'ai' ? 'aria-current="page"' : ''; ?>
                    >
                        <?php echo $memberIconSvg('brain'); ?>
                        <span>预测记录</span>
                    </a>
                    <a
                        href="<?php echo e($memberTabUrl('purchases')); ?>"
                        class="member-console-link <?php echo $memberTab === 'purchases' ? 'is-active' : ''; ?>"
                        data-no-prefetch
                        <?php echo $memberTab === 'purchases' ? 'aria-current="page"' : ''; ?>
                    >
                        <?php echo $memberIconSvg('receipt'); ?>
                        <span>购买记录</span>
                    </a>
                    <a
                        href="<?php echo e($memberTabUrl('about')); ?>"
                        class="member-console-link <?php echo $memberTab === 'about' ? 'is-active' : ''; ?>"
                        data-no-prefetch
                        <?php echo $memberTab === 'about' ? 'aria-current="page"' : ''; ?>
                    >
                        <?php echo $memberIconSvg('circle-info'); ?>
                        <span>论坛指南</span>
                    </a>
                    <a
                        href="<?php echo e($memberTabUrl('profile')); ?>"
                        class="member-console-link <?php echo $memberTab === 'profile' ? 'is-active' : ''; ?>"
                        data-no-prefetch
                        <?php echo $memberTab === 'profile' ? 'aria-current="page"' : ''; ?>
                    >
                        <?php echo $memberIconSvg('id-card'); ?>
                        <span>个人资料</span>
                    </a>
                </nav>
                <div class="member-console-panel">
                    <?php if ($memberTab === 'ai'): ?>
                <section class="member-ai-log-panel">
                    <form
                        id="member-prediction-delete-form"
                        method="post"
                        action="<?php echo e(public_url('api.php')); ?>"
                        data-ajax-form
                        data-member-prediction-delete-form
                        data-confirm="确认删除选中的预测记录吗？"
                    >
                        <input type="hidden" name="action" value="prediction.logs.delete">
                        <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                        <input type="hidden" name="region" value="<?php echo e($region); ?>">
                        <div class="member-ai-log-head">
                            <div>
                                <h2>预测记录</h2>
                            </div>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <div class="member-ai-log-badge"><?php echo e((string) $memberLogCount); ?>条</div>
                                <?php if (!empty($predictionLogs)): ?>
                                    <label class="member-ai-log-select-all">
                                        <input
                                            type="checkbox"
                                            class="h-3.5 w-3.5 accent-blue-600"
                                            data-check-all=".member-ai-log-check-input"
                                            data-member-prediction-check-all
                                        >
                                        <span>全选</span>
                                    </label>
                                    <button
                                        type="submit"
                                        class="member-ai-log-delete-btn"
                                        data-member-prediction-delete-submit
                                        disabled
                                    >删除记录</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="member-ai-log-list">
                            <?php if (!empty($predictionLogs)): ?>
                                <?php $predictionResultDraws = isset($predictionResultDraws) && is_array($predictionResultDraws) ? $predictionResultDraws : array(); ?>
                                <?php foreach ($predictionLogs as $predictionLog): ?>
                                    <?php
                                    $predictionRegion = (string) ($predictionLog['region'] ?? '') === 'hongkong' ? 'hongkong' : 'macau';
                                    $predictionRegionMark = $predictionRegion === 'hongkong' ? '港' : '澳';
                                    $predictionIssueNo = trim((string) ($predictionLog['generated_for_issue'] ?? ''));
                                    $predictionIssueText = $predictionIssueNo !== '' ? $predictionIssueNo : '--';
                                    if ($predictionIssueText !== '--' && preg_match('/^\d{5,}$/', $predictionIssueText) === 1) {
                                        $predictionIssueText = ltrim(substr($predictionIssueText, 4), '0');
                                        if ($predictionIssueText === '') {
                                            $predictionIssueText = '0';
                                        }
                                    }
                                    $predictionIssueText .= '期';
                                    $predictionConfidence = (float) ($predictionLog['confidence'] ?? 0);
                                    $predictionNumbers = json_decode((string) ($predictionLog['numbers_json'] ?? ''), true);
                                    $predictionNumbers = is_array($predictionNumbers) ? array_values(array_map('intval', $predictionNumbers)) : array();
                                    $predictionDisplayPayloads = json_decode((string) ($predictionLog['display_payloads_json'] ?? ''), true);
                                    $predictionDisplayPayloads = is_array($predictionDisplayPayloads) ? $predictionDisplayPayloads : array();
                                    $predictionLineConfidences = json_decode((string) ($predictionLog['line_confidences_json'] ?? ''), true);
                                    $predictionLineConfidences = is_array($predictionLineConfidences) ? $predictionLineConfidences : array();
                                    $predictionFilters = json_decode((string) ($predictionLog['filters_json'] ?? ''), true);
                                    $predictionFilters = is_array($predictionFilters) ? $predictionFilters : array();
                                    $predictionSummaryLines = preg_split('/\r\n|\r|\n/', trim((string) ($predictionLog['summary'] ?? '')));
                                    $predictionSummaryLines = is_array($predictionSummaryLines) ? $predictionSummaryLines : array();
                                    $predictionIsSummaryText = static function ($value) {
                                        $value = trim((string) $value);
                                        if ($value === '') {
                                            return false;
                                        }
                                        foreach (array('下期', '倾向', '总结', '热度', '热号', '温号', '冷门', '优先', '参考', '特码取', '平码取', '波色红蓝', '核心热号') as $marker) {
                                            if (strpos($value, $marker) !== false) {
                                                return true;
                                            }
                                        }

                                        return strpos($value, '；') !== false || strpos($value, ';') !== false;
                                    };
                                    $predictionIsDisplaySummaryLine = static function ($title, $value) use ($predictionIsSummaryText) {
                                        $title = trim((string) $title);
                                        $value = trim((string) $value);
                                        if ($title === '' || $value === '' || $predictionIsSummaryText($title) || $predictionIsSummaryText($value)) {
                                            return false;
                                        }
                                        foreach (array('生肖', '号码', '平码', '平特', '其他', '波色', '单双', '大小', '头数', '尾数') as $keyword) {
                                            if (strpos($title, $keyword) !== false || strpos($value, $keyword) !== false) {
                                                return true;
                                            }
                                        }

                                        return false;
                                    };
                                    $predictionTypeLabels = array();
                                    $predictionTypeLimits = array();
                                    foreach ($predictionSummaryLines as $predictionSummaryLine) {
                                        $predictionSummaryLine = trim((string) $predictionSummaryLine);
                                        if ($predictionSummaryLine === '' || strpos($predictionSummaryLine, '热度') !== false || strpos($predictionSummaryLine, '未选择') !== false) {
                                            continue;
                                        }
                                        if (!preg_match('/^([^:：]{1,12})[:：]\s*(.+)$/u', $predictionSummaryLine, $predictionMatches)) {
                                            continue;
                                        }
                                        $predictionTypeKey = 'other';
                                        $predictionSummaryTitle = trim((string) $predictionMatches[1]);
                                        $predictionSummaryValue = trim((string) $predictionMatches[2]);
                                        $predictionSummaryTitleKey = str_replace(array(' ', "\t"), '', $predictionSummaryTitle);
                                        if (!in_array($predictionSummaryTitleKey, array('生肖类型', '号码类型', '平特类型', '平码类型', '其他类型'), true)) {
                                            continue;
                                        }
                                        if (!$predictionIsDisplaySummaryLine($predictionSummaryTitle, $predictionSummaryValue)) {
                                            continue;
                                        }
                                        if ($predictionSummaryTitleKey === '生肖类型') {
                                            $predictionTypeKey = 'zodiac';
                                        } elseif ($predictionSummaryTitleKey === '号码类型') {
                                            $predictionTypeKey = 'number';
                                        } elseif ($predictionSummaryTitleKey === '平特类型' || $predictionSummaryTitleKey === '平码类型') {
                                            $predictionTypeKey = 'pingte';
                                        }
                                        if (preg_match('/^(.+?)\s+\d+个/u', $predictionSummaryValue, $predictionLabelMatches)) {
                                            $predictionSummaryValue = trim((string) $predictionLabelMatches[1]);
                                        }
                                        if (preg_match('/(\d+)个(?:生肖|号码|波色|单双|大小|头数|尾数)/u', trim((string) $predictionMatches[2]), $predictionLimitMatches)) {
                                            $predictionTypeLimits[$predictionTypeKey] = (int) $predictionLimitMatches[1];
                                        } elseif (preg_match('/(\d+)码/u', trim((string) $predictionMatches[2]), $predictionLimitMatches)) {
                                            $predictionTypeLimits[$predictionTypeKey] = (int) $predictionLimitMatches[1];
                                        }
                                        if ($predictionSummaryValue !== '') {
                                            $predictionTypeLabels[$predictionTypeKey] = $predictionSummaryValue;
                                        }
                                    }
                                    $predictionFilterTypeLabels = static function (array $filters) {
                                        $labels = array();
                                        $zodiacType = trim((string) ($filters['zodiac_type'] ?? ''));
                                        $numberType = trim((string) ($filters['number_type'] ?? ''));
                                        $pingteType = trim((string) ($filters['pingte_type'] ?? ''));
                                        $otherType = trim((string) ($filters['other_type'] ?? ''));
                                        $zodiacLabels = array(
                                            1 => '一肖',
                                            2 => '二肖',
                                            3 => '三肖',
                                            4 => '四肖',
                                            5 => '五肖',
                                            6 => '六肖',
                                            7 => '七肖',
                                            8 => '八肖',
                                            9 => '九肖',
                                        );
                                        $otherLabels = array(
                                            'odd_even' => '单双',
                                            'big_small' => '大小',
                                            'wave' => '波色',
                                            'head' => '头数',
                                            'tail' => '尾数',
                                        );

                                        if (preg_match('/^zodiac_(\d+)$/', $zodiacType, $matches)) {
                                            $count = (int) $matches[1];
                                            if (isset($zodiacLabels[$count])) {
                                                $labels['zodiac'] = $zodiacLabels[$count];
                                            }
                                        }

                                        if (preg_match('/^number_(\d+)$/', $numberType, $matches)) {
                                            $labels['number'] = (int) $matches[1] . '码';
                                        }

                                        if (preg_match('/^pt_(\d+)_(\d+)_group_(\d+)$/', $pingteType, $matches)) {
                                            $labels['pingte'] = (int) $matches[3] . '组' . (int) $matches[2] . '中' . (int) $matches[2];
                                        } elseif (preg_match('/^pt_(\d+)_(\d+)_combo_(\d+)$/', $pingteType, $matches)) {
                                            $labels['pingte'] = (int) $matches[3] . '码复式';
                                        }

                                        if (isset($otherLabels[$otherType])) {
                                            $labels['other'] = $otherLabels[$otherType];
                                        } elseif (preg_match('/^pt_zodiac_(\d+)$/', $otherType, $matches)) {
                                            $count = (int) $matches[1];
                                            if (isset($zodiacLabels[$count])) {
                                                $labels['other'] = '平特' . $zodiacLabels[$count];
                                            }
                                        }

                                        return $labels;
                                    };
                                    foreach ($predictionFilterTypeLabels($predictionFilters) as $predictionFilterType => $predictionFilterLabel) {
                                        if (trim((string) $predictionFilterLabel) !== '') {
                                            $predictionTypeLabels[$predictionFilterType] = $predictionFilterLabel;
                                        }
                                    }
                                    $predictionEscape = static function ($value) {
                                        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
                                    };
                                    $predictionZodiacMap = array(
                                        '马' => array(1, 13, 25, 37, 49),
                                        '蛇' => array(2, 14, 26, 38),
                                        '龙' => array(3, 15, 27, 39),
                                        '兔' => array(4, 16, 28, 40),
                                        '虎' => array(5, 17, 29, 41),
                                        '牛' => array(6, 18, 30, 42),
                                        '鼠' => array(7, 19, 31, 43),
                                        '猪' => array(8, 20, 32, 44),
                                        '狗' => array(9, 21, 33, 45),
                                        '鸡' => array(10, 22, 34, 46),
                                        '猴' => array(11, 23, 35, 47),
                                        '羊' => array(12, 24, 36, 48),
                                    );
                                    $predictionWaveMap = array(
                                        '红波' => array(1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46),
                                        '蓝波' => array(3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48),
                                        '绿波' => array(5, 6, 11, 16, 17, 21, 22, 27, 28, 32, 33, 38, 39, 43, 44, 49),
                                    );
                                    $predictionNumberToZodiac = array();
                                    foreach ($predictionZodiacMap as $predictionZodiacLabel => $predictionZodiacNumbers) {
                                        foreach ($predictionZodiacNumbers as $predictionZodiacNumber) {
                                            $predictionNumberToZodiac[(int) $predictionZodiacNumber] = $predictionZodiacLabel;
                                        }
                                    }
                                    $predictionNumberToWave = array();
                                    foreach ($predictionWaveMap as $predictionWaveLabel => $predictionWaveNumbers) {
                                        foreach ($predictionWaveNumbers as $predictionWaveNumber) {
                                            $predictionNumberToWave[(int) $predictionWaveNumber] = $predictionWaveLabel;
                                        }
                                    }
                                    $predictionResultDraw = isset($predictionResultDraws[$predictionRegion][$predictionIssueNo]) && is_array($predictionResultDraws[$predictionRegion][$predictionIssueNo])
                                        ? $predictionResultDraws[$predictionRegion][$predictionIssueNo]
                                        : null;
                                    $predictionDrawNumbers = array();
                                    $predictionDrawSpecialNumber = 0;
                                    if (is_array($predictionResultDraw)) {
                                        $predictionDrawRegularNumbers = json_decode((string) ($predictionResultDraw['numbers_json'] ?? ''), true);
                                        $predictionDrawRegularNumbers = is_array($predictionDrawRegularNumbers) ? array_slice($predictionDrawRegularNumbers, 0, 6) : array();
                                        foreach ($predictionDrawRegularNumbers as $predictionDrawRegularNumber) {
                                            $predictionDrawRegularNumber = (int) $predictionDrawRegularNumber;
                                            if ($predictionDrawRegularNumber >= 1 && $predictionDrawRegularNumber <= 49) {
                                                $predictionDrawNumbers[] = $predictionDrawRegularNumber;
                                            }
                                        }
                                        $predictionDrawSpecialNumber = (int) ($predictionResultDraw['special_number'] ?? 0);
                                        if ($predictionDrawSpecialNumber < 1 || $predictionDrawSpecialNumber > 49) {
                                            $predictionDrawSpecialNumber = 0;
                                        }
                                    }
                                    $predictionDrawTokenClass = static function ($number) use ($predictionNumberToWave) {
                                        $waveLabel = (string) ($predictionNumberToWave[(int) $number] ?? '');
                                        if ($waveLabel === '红波') {
                                            return 'is-red';
                                        }
                                        if ($waveLabel === '蓝波') {
                                            return 'is-blue';
                                        }
                                        if ($waveLabel === '绿波') {
                                            return 'is-green';
                                        }

                                        return '';
                                    };
                                    $predictionRenderDrawToken = static function ($value, $number) use ($predictionEscape, $predictionDrawTokenClass) {
                                        return '<span class="' . $predictionEscape('member-ai-log-draw-token ' . $predictionDrawTokenClass($number)) . '">' . $predictionEscape($value) . '</span>';
                                    };
                                    $predictionDrawNumberCells = array();
                                    $predictionDrawZodiacCells = array();
                                    foreach ($predictionDrawNumbers as $predictionDrawResultIndex => $predictionDrawResultNumber) {
                                        $predictionDrawResultNumber = (int) $predictionDrawResultNumber;
                                        if ((int) $predictionDrawResultIndex > 0) {
                                            $predictionDrawNumberCells[] = '<span class="member-ai-log-draw-separator">-</span>';
                                            $predictionDrawZodiacCells[] = '<span class="member-ai-log-draw-gap"></span>';
                                        }
                                        $predictionDrawNumberCells[] = $predictionRenderDrawToken(str_pad((string) $predictionDrawResultNumber, 2, '0', STR_PAD_LEFT), $predictionDrawResultNumber);
                                        $predictionDrawZodiacCells[] = $predictionRenderDrawToken((string) ($predictionNumberToZodiac[$predictionDrawResultNumber] ?? '--'), $predictionDrawResultNumber);
                                    }
                                    $predictionDrawResultHtml = '';
                                    $predictionDrawZodiacResultHtml = '';
                                    if (!empty($predictionDrawNumberCells)) {
                                        if ($predictionDrawSpecialNumber > 0) {
                                            $predictionDrawNumberCells[] = '<span class="member-ai-log-draw-plus">+</span>';
                                            $predictionDrawZodiacCells[] = '<span class="member-ai-log-draw-gap"></span>';
                                            $predictionDrawNumberCells[] = $predictionRenderDrawToken(str_pad((string) $predictionDrawSpecialNumber, 2, '0', STR_PAD_LEFT), $predictionDrawSpecialNumber);
                                            $predictionDrawZodiacCells[] = $predictionRenderDrawToken((string) ($predictionNumberToZodiac[$predictionDrawSpecialNumber] ?? '--'), $predictionDrawSpecialNumber);
                                        }
                                        $predictionDrawResultHtml = implode('', $predictionDrawNumberCells);
                                        $predictionDrawZodiacResultHtml = implode('', $predictionDrawZodiacCells);
                                    }
                                    $predictionWinningNumberLookup = array();
                                    foreach ($predictionDrawNumbers as $predictionDrawNumber) {
                                        $predictionWinningNumberLookup[(int) $predictionDrawNumber] = true;
                                    }
                                    $predictionDrawZodiacLookup = array();
                                    $predictionDrawAllNumbers = $predictionDrawNumbers;
                                    if ($predictionDrawSpecialNumber > 0) {
                                        $predictionDrawAllNumbers[] = $predictionDrawSpecialNumber;
                                    }
                                    foreach ($predictionDrawAllNumbers as $predictionDrawNumber) {
                                        $predictionDrawZodiac = (string) ($predictionNumberToZodiac[(int) $predictionDrawNumber] ?? '');
                                        if ($predictionDrawZodiac !== '') {
                                            $predictionDrawZodiacLookup[$predictionDrawZodiac] = true;
                                        }
                                    }
                                    $predictionSpecialZodiac = $predictionDrawSpecialNumber > 0 ? (string) ($predictionNumberToZodiac[$predictionDrawSpecialNumber] ?? '') : '';
                                    $predictionSpecialWave = $predictionDrawSpecialNumber > 0 ? (string) ($predictionNumberToWave[$predictionDrawSpecialNumber] ?? '') : '';
                                    $predictionSpecialOddEven = $predictionDrawSpecialNumber > 0 ? ($predictionDrawSpecialNumber % 2 === 0 ? '双' : '单') : '';
                                    $predictionSpecialBigSmall = $predictionDrawSpecialNumber > 0 ? ($predictionDrawSpecialNumber <= 24 ? '小' : '大') : '';
                                    $predictionSpecialHead = $predictionDrawSpecialNumber > 0 ? (string) floor($predictionDrawSpecialNumber / 10) . '头' : '';
                                    $predictionSpecialTail = $predictionDrawSpecialNumber > 0 ? (string) ($predictionDrawSpecialNumber % 10) . '尾' : '';
                                    $predictionCorrectOtherLookup = array();
                                    foreach (array($predictionSpecialWave, $predictionSpecialOddEven, $predictionSpecialBigSmall, $predictionSpecialHead, $predictionSpecialTail) as $predictionCorrectOtherValue) {
                                        $predictionCorrectOtherValue = trim((string) $predictionCorrectOtherValue);
                                        if ($predictionCorrectOtherValue !== '') {
                                            $predictionCorrectOtherLookup[$predictionCorrectOtherValue] = true;
                                        }
                                    }
                                    $predictionOtherType = trim((string) ($predictionFilters['other_type'] ?? ''));
                                    $predictionOtherZodiacCount = 0;
                                    if (preg_match('/^pt_zodiac_(\d+)$/', $predictionOtherType, $predictionOtherZodiacMatches)) {
                                        $predictionOtherZodiacCount = max(0, min(5, (int) $predictionOtherZodiacMatches[1]));
                                    }
                                    $predictionPingteType = trim((string) ($predictionFilters['pingte_type'] ?? ''));
                                    $predictionPingteGroupHitCount = 0;
                                    $predictionPingteComboHitCount = 0;
                                    if (preg_match('/^pt_(\d+)_(\d+)_group_(\d+)$/', $predictionPingteType, $predictionPingteMatches)) {
                                        $predictionPingteGroupHitCount = max(1, (int) $predictionPingteMatches[2]);
                                    } elseif (preg_match('/^pt_(\d+)_(\d+)_combo_(\d+)$/', $predictionPingteType, $predictionPingteMatches)) {
                                        $predictionPingteComboHitCount = max(1, (int) $predictionPingteMatches[2]);
                                    }
                                    $predictionIsWinningSpecialNumber = static function ($number) use ($predictionDrawSpecialNumber) {
                                        return $predictionDrawSpecialNumber > 0 && (int) $number === $predictionDrawSpecialNumber;
                                    };
                                    $predictionCountWinningNumbersInGroup = static function (array $numbers) use ($predictionWinningNumberLookup) {
                                        $hitNumbers = array();
                                        foreach ($numbers as $number) {
                                            $number = (int) $number;
                                            if ($number < 1 || $number > 49) {
                                                continue;
                                            }
                                            if (isset($predictionWinningNumberLookup[$number])) {
                                                $hitNumbers[$number] = true;
                                            }
                                        }

                                        return count($hitNumbers);
                                    };
                                    $predictionUniqueValues = static function (array $values) {
                                        $uniqueValues = array();
                                        foreach ($values as $value) {
                                            $value = trim((string) $value);
                                            if ($value !== '' && !in_array($value, $uniqueValues, true)) {
                                                $uniqueValues[] = $value;
                                            }
                                        }

                                        return $uniqueValues;
                                    };
                                    $predictionOtherDisplayLimit = static function ($selectedValue) {
                                        if (preg_match('/^pt_zodiac_(\d+)$/', (string) $selectedValue, $matches)) {
                                            return max(0, min(5, (int) $matches[1]));
                                        }

                                        switch ((string) $selectedValue) {
                                            case 'odd_even':
                                            case 'big_small':
                                                return 1;
                                            case 'wave':
                                                return 2;
                                            case 'head':
                                                return 3;
                                            case 'tail':
                                                return 5;
                                            default:
                                                return 0;
                                        }
                                    };
                                    $predictionResolveOtherPayload = static function ($selectedValue, array $numbers) use ($predictionNumberToWave, $predictionNumberToZodiac, $predictionUniqueValues, $predictionOtherDisplayLimit) {
                                        $selectedValue = trim((string) $selectedValue);
                                        $limit = max(1, $predictionOtherDisplayLimit($selectedValue));
                                        $scores = array();

                                        if (preg_match('/^pt_zodiac_(\d+)$/', $selectedValue, $matches)) {
                                            $zodiacValues = array();
                                            foreach ($numbers as $number) {
                                                $zodiacValues[] = $predictionNumberToZodiac[(int) $number] ?? '';
                                            }

                                            return array(
                                                'kind' => 'other',
                                                'values' => array_slice($predictionUniqueValues($zodiacValues), 0, max(1, min(5, (int) $matches[1]))),
                                                'groups' => array(),
                                            );
                                        }

                                        if ($selectedValue === 'odd_even') {
                                            $scores = array('单' => 0, '双' => 0);
                                            foreach ($numbers as $number) {
                                                $number = (int) $number;
                                                if ($number >= 1 && $number <= 49) {
                                                    $scores[$number % 2 === 0 ? '双' : '单'] += 1;
                                                }
                                            }
                                        } elseif ($selectedValue === 'wave') {
                                            $scores = array('红波' => 0, '蓝波' => 0, '绿波' => 0);
                                            foreach ($numbers as $number) {
                                                $wave = (string) ($predictionNumberToWave[(int) $number] ?? '');
                                                if ($wave !== '' && isset($scores[$wave])) {
                                                    $scores[$wave] += 1;
                                                }
                                            }
                                        } elseif ($selectedValue === 'big_small') {
                                            $scores = array('小' => 0, '大' => 0);
                                            foreach ($numbers as $number) {
                                                $number = (int) $number;
                                                if ($number >= 1 && $number <= 49) {
                                                    $scores[$number <= 24 ? '小' : '大'] += 1;
                                                }
                                            }
                                        } elseif ($selectedValue === 'head') {
                                            for ($head = 0; $head <= 4; $head++) {
                                                $scores[$head . '头'] = 0;
                                            }
                                            foreach ($numbers as $number) {
                                                $number = (int) $number;
                                                if ($number >= 1 && $number <= 49) {
                                                    $scores[(int) floor($number / 10) . '头'] += 1;
                                                }
                                            }
                                        } elseif ($selectedValue === 'tail') {
                                            for ($tail = 0; $tail <= 9; $tail++) {
                                                $scores[$tail . '尾'] = 0;
                                            }
                                            foreach ($numbers as $number) {
                                                $number = (int) $number;
                                                if ($number >= 1 && $number <= 49) {
                                                    $scores[$number % 10 . '尾'] += 1;
                                                }
                                            }
                                        }

                                        arsort($scores);

                                        return array(
                                            'kind' => 'other',
                                            'values' => array_slice(array_keys($scores), 0, $limit),
                                            'groups' => array(),
                                        );
                                    };
                                    $predictionWaveClass = static function ($number) use ($predictionNumberToWave) {
                                        $waveLabel = $predictionNumberToWave[(int) $number] ?? '';
                                        if ($waveLabel === '红波') {
                                            return 'is-red';
                                        }

                                        if ($waveLabel === '蓝波') {
                                            return 'is-blue';
                                        }

                                        return 'is-green';
                                    };
                                    $predictionCorrectMark = static function ($isCorrect) {
                                        return $isCorrect ? '<span class="forecast-result-correct-mark" aria-hidden="true">✓</span>' : '';
                                    };
                                    $predictionCorrectClass = static function ($isCorrect) {
                                        return $isCorrect ? ' is-correct' : '';
                                    };
                                    $predictionRenderNumber = static function ($number, $className, $isCorrect = false) use ($predictionEscape, $predictionWaveClass, $predictionCorrectClass, $predictionCorrectMark) {
                                        $number = (int) $number;

                                        return '<span class="' . $predictionEscape($className . ' ' . $predictionWaveClass($number) . $predictionCorrectClass($isCorrect)) . '">' . $predictionEscape(str_pad((string) $number, 2, '0', STR_PAD_LEFT)) . $predictionCorrectMark($isCorrect) . '</span>';
                                    };
                                    $predictionRenderPayload = static function (array $payload) use ($predictionEscape, $predictionRenderNumber, $predictionSpecialZodiac, $predictionCorrectOtherLookup, $predictionDrawZodiacLookup, $predictionOtherZodiacCount, $predictionIsWinningSpecialNumber, $predictionCountWinningNumbersInGroup, $predictionPingteGroupHitCount, $predictionPingteComboHitCount, $predictionCorrectClass, $predictionCorrectMark) {
                                        $kind = (string) ($payload['kind'] ?? '');
                                        $parts = array();

                                        if ($kind === 'zodiac') {
                                            foreach ((array) ($payload['values'] ?? array()) as $value) {
                                                $value = trim((string) $value);
                                                if ($value !== '') {
                                                    $isCorrect = $predictionSpecialZodiac !== '' && $value === $predictionSpecialZodiac;
                                                    $parts[] = '<span class="' . $predictionEscape('forecast-result-zodiac-chip' . $predictionCorrectClass($isCorrect)) . '">' . $predictionEscape($value) . $predictionCorrectMark($isCorrect) . '</span>';
                                                }
                                            }
                                        } elseif ($kind === 'number') {
                                            foreach ((array) ($payload['values'] ?? array()) as $number) {
                                                $number = (int) $number;
                                                $parts[] = $predictionRenderNumber($number, 'forecast-result-number-chip', $predictionIsWinningSpecialNumber($number));
                                            }
                                        } elseif ($kind === 'pingte-groups') {
                                            foreach ((array) ($payload['groups'] ?? array()) as $groupItems) {
                                                $groupNumbers = array_values(array_map('intval', (array) $groupItems));
                                                $hitCount = $predictionCountWinningNumbersInGroup($groupNumbers);
                                                $needHitCount = $predictionPingteGroupHitCount > 0 ? $predictionPingteGroupHitCount : count($groupNumbers);
                                                $isCorrect = $needHitCount > 0 && $hitCount >= $needHitCount;
                                                $groupParts = array();
                                                foreach ($groupNumbers as $number) {
                                                    $groupParts[] = $predictionRenderNumber((int) $number, 'forecast-result-pingte-number', $predictionCountWinningNumbersInGroup(array((int) $number)) > 0);
                                                }
                                                if (!empty($groupParts)) {
                                                    $groupSizeClass = ' is-size-' . (string) min(6, max(1, count($groupParts)));
                                                    $parts[] = '<span class="' . $predictionEscape('forecast-result-pingte-text is-group' . $groupSizeClass . $predictionCorrectClass($isCorrect)) . '"><span class="forecast-result-pingte-bracket">【</span>' . implode('<span class="forecast-result-pingte-separator">-</span>', $groupParts) . '<span class="forecast-result-pingte-bracket">】</span>' . $predictionCorrectMark($isCorrect) . '</span>';
                                                }
                                            }
                                        } elseif ($kind === 'pingte-combo') {
                                            $comboNumbers = array_values(array_map('intval', (array) ($payload['values'] ?? array())));
                                            $hitCount = $predictionCountWinningNumbersInGroup($comboNumbers);
                                            $needHitCount = $predictionPingteComboHitCount > 0 ? $predictionPingteComboHitCount : 2;
                                            $isCorrect = $needHitCount > 0 && $hitCount >= $needHitCount;
                                            $groupParts = array();
                                            foreach ($comboNumbers as $number) {
                                                $groupParts[] = $predictionRenderNumber((int) $number, 'forecast-result-pingte-number', $predictionCountWinningNumbersInGroup(array((int) $number)) > 0);
                                            }
                                            if (!empty($groupParts)) {
                                                $parts[] = '<span class="' . $predictionEscape('forecast-result-pingte-text is-combo' . $predictionCorrectClass($isCorrect)) . '"><span class="forecast-result-pingte-bracket">【</span>' . implode('<span class="forecast-result-pingte-separator">-</span>', $groupParts) . '<span class="forecast-result-pingte-bracket">】</span>' . $predictionCorrectMark($isCorrect) . '</span>';
                                            }
                                        } elseif ($kind === 'other') {
                                            foreach ((array) ($payload['values'] ?? array()) as $value) {
                                                $value = trim((string) $value);
                                                if ($value !== '') {
                                                    $isCorrect = $predictionOtherZodiacCount > 0
                                                        ? isset($predictionDrawZodiacLookup[$value])
                                                        : isset($predictionCorrectOtherLookup[$value]);
                                                    $parts[] = '<span class="' . $predictionEscape('forecast-result-other-chip' . $predictionCorrectClass($isCorrect)) . '">' . $predictionEscape($value) . $predictionCorrectMark($isCorrect) . '</span>';
                                                }
                                            }
                                        }

                                        return empty($parts) ? '' : implode('', $parts);
                                    };
                                    $predictionPayloadIsCorrect = static function (array $payload) use ($predictionSpecialZodiac, $predictionCorrectOtherLookup, $predictionDrawZodiacLookup, $predictionOtherZodiacCount, $predictionIsWinningSpecialNumber, $predictionCountWinningNumbersInGroup, $predictionPingteGroupHitCount, $predictionPingteComboHitCount) {
                                        $kind = (string) ($payload['kind'] ?? '');

                                        if ($kind === 'zodiac') {
                                            foreach ((array) ($payload['values'] ?? array()) as $value) {
                                                if ($predictionSpecialZodiac !== '' && trim((string) $value) === $predictionSpecialZodiac) {
                                                    return true;
                                                }
                                            }
                                        } elseif ($kind === 'number') {
                                            foreach ((array) ($payload['values'] ?? array()) as $number) {
                                                if ($predictionIsWinningSpecialNumber((int) $number)) {
                                                    return true;
                                                }
                                            }
                                        } elseif ($kind === 'pingte-groups') {
                                            foreach ((array) ($payload['groups'] ?? array()) as $groupItems) {
                                                $groupNumbers = array_values(array_map('intval', (array) $groupItems));
                                                $hitCount = $predictionCountWinningNumbersInGroup($groupNumbers);
                                                $needHitCount = $predictionPingteGroupHitCount > 0 ? $predictionPingteGroupHitCount : count($groupNumbers);
                                                if ($needHitCount > 0 && $hitCount >= $needHitCount) {
                                                    return true;
                                                }
                                            }
                                        } elseif ($kind === 'pingte-combo') {
                                            $comboNumbers = array_values(array_map('intval', (array) ($payload['values'] ?? array())));
                                            $hitCount = $predictionCountWinningNumbersInGroup($comboNumbers);
                                            $needHitCount = $predictionPingteComboHitCount > 0 ? $predictionPingteComboHitCount : 2;

                                            return $needHitCount > 0 && $hitCount >= $needHitCount;
                                        } elseif ($kind === 'other') {
                                            foreach ((array) ($payload['values'] ?? array()) as $value) {
                                                $value = trim((string) $value);
                                                if ($predictionOtherZodiacCount > 0 && isset($predictionDrawZodiacLookup[$value])) {
                                                    return true;
                                                }
                                                if ($predictionOtherZodiacCount <= 0 && isset($predictionCorrectOtherLookup[$value])) {
                                                    return true;
                                                }
                                            }
                                        }

                                        return false;
                                    };
                                    $predictionPayloadHasResult = static function (array $payload) {
                                        if (!empty($payload['values']) && is_array($payload['values'])) {
                                            return true;
                                        }

                                        if (!empty($payload['groups']) && is_array($payload['groups'])) {
                                            return true;
                                        }

                                        return false;
                                    };
                                    $predictionCollectPayloadNumbers = static function (array $payload) {
                                        $numbers = array();
                                        foreach ((array) ($payload['values'] ?? array()) as $value) {
                                            $number = (int) $value;
                                            if ($number >= 1 && $number <= 49 && !in_array($number, $numbers, true)) {
                                                $numbers[] = $number;
                                            }
                                        }
                                        foreach ((array) ($payload['groups'] ?? array()) as $groupItems) {
                                            foreach ((array) $groupItems as $value) {
                                                $number = (int) $value;
                                                if ($number >= 1 && $number <= 49 && !in_array($number, $numbers, true)) {
                                                    $numbers[] = $number;
                                                }
                                            }
                                        }

                                        return $numbers;
                                    };
                                    $predictionFillNumberList = static function (array $sourceNumbers, $targetCount, array $excludedNumbers = array(), array $fallbackNumbers = array()) {
                                        $targetCount = max(0, min(49, (int) $targetCount));
                                        $excludedLookup = array();
                                        foreach ($excludedNumbers as $excludedNumber) {
                                            $excludedNumber = (int) $excludedNumber;
                                            if ($excludedNumber >= 1 && $excludedNumber <= 49) {
                                                $excludedLookup[$excludedNumber] = true;
                                            }
                                        }
                                        $numbers = array();
                                        $pool = array_merge($sourceNumbers, $fallbackNumbers, range(1, 49));
                                        $strictTargetCount = min($targetCount, max(0, 49 - count($excludedLookup)));

                                        if ($strictTargetCount > 0) {
                                            foreach ($pool as $number) {
                                                $number = (int) $number;
                                                if ($number < 1 || $number > 49 || isset($excludedLookup[$number]) || in_array($number, $numbers, true)) {
                                                    continue;
                                                }
                                                $numbers[] = $number;
                                                if (count($numbers) >= $strictTargetCount) {
                                                    if ($targetCount <= $strictTargetCount) {
                                                        return $numbers;
                                                    }
                                                    break;
                                                }
                                            }
                                        }

                                        if ($targetCount > $strictTargetCount) {
                                            foreach ($pool as $number) {
                                                $number = (int) $number;
                                                if ($number < 1 || $number > 49 || in_array($number, $numbers, true)) {
                                                    continue;
                                                }
                                                $numbers[] = $number;
                                                if (count($numbers) >= $targetCount) {
                                                    break;
                                                }
                                            }
                                        }

                                        return $numbers;
                                    };
                                    $predictionNormalizeNumberPayloads = static function (array $payloads, array $filters, array $numbers) use ($predictionCollectPayloadNumbers, $predictionFillNumberList) {
                                        $numberType = trim((string) ($filters['number_type'] ?? ''));
                                        $pingteType = trim((string) ($filters['pingte_type'] ?? ''));
                                        $numberCount = 0;
                                        if (preg_match('/^number_(\d+)$/', $numberType, $matches)) {
                                            $numberCount = max(0, min(49, (int) $matches[1]));
                                        }

                                        $pingteGroupSize = 0;
                                        $pingteGroupCount = 0;
                                        $pingteComboCount = 0;
                                        if (preg_match('/^pt_(\d+)_(\d+)_group_(\d+)$/', $pingteType, $matches)) {
                                            $pingteGroupSize = max(1, (int) $matches[2]);
                                            $pingteGroupCount = max(1, (int) $matches[3]);
                                        } elseif (preg_match('/^pt_(\d+)_(\d+)_combo_(\d+)$/', $pingteType, $matches)) {
                                            $pingteComboCount = max(1, min(49, (int) $matches[3]));
                                        }
                                        $pingteCount = $pingteGroupSize > 0 ? $pingteGroupSize * $pingteGroupCount : $pingteComboCount;

                                        if ($numberCount <= 0 && $pingteCount <= 0) {
                                            return $payloads;
                                        }

                                        $numberPayload = isset($payloads['number']) && is_array($payloads['number']) ? $payloads['number'] : array();
                                        $pingtePayload = isset($payloads['pingte']) && is_array($payloads['pingte']) ? $payloads['pingte'] : array();
                                        $baseNumbers = array_values(array_filter(array_map('intval', array_merge(
                                            $predictionCollectPayloadNumbers($numberPayload),
                                            $predictionCollectPayloadNumbers($pingtePayload),
                                            $numbers
                                        )), static function ($number) {
                                            return $number >= 1 && $number <= 49;
                                        }));
                                        $baseNumbers = array_values(array_unique($baseNumbers));

                                        $numberValues = array();
                                        if ($numberCount > 0) {
                                            $numberValues = $predictionFillNumberList($predictionCollectPayloadNumbers($numberPayload), $numberCount, array(), $baseNumbers);
                                            $payloads['number'] = array(
                                                'kind' => 'number',
                                                'values' => $numberValues,
                                                'groups' => array(),
                                            );
                                        }

                                        if ($pingteCount > 0) {
                                            $pingteValues = $predictionFillNumberList($predictionCollectPayloadNumbers($pingtePayload), $pingteCount, $numberValues, $baseNumbers);
                                            if ($pingteGroupSize > 0) {
                                                $payloads['pingte'] = array(
                                                    'kind' => 'pingte-groups',
                                                    'values' => array(),
                                                    'groups' => array_chunk(array_slice($pingteValues, 0, $pingteCount), $pingteGroupSize),
                                                );
                                            } else {
                                                $payloads['pingte'] = array(
                                                    'kind' => 'pingte-combo',
                                                    'values' => array_slice($pingteValues, 0, $pingteCount),
                                                    'groups' => array(),
                                                );
                                            }
                                        }

                                        return $payloads;
                                    };
                                    $predictionDisplayPayloads = $predictionNormalizeNumberPayloads($predictionDisplayPayloads, $predictionFilters, $predictionNumbers);
                                    $predictionResultLines = array();
                                    $predictionTitles = array(
                                        'zodiac' => '生肖类型：',
                                        'number' => '号码类型：',
                                        'pingte' => '平码类型：',
                                        'other' => '其他类型：',
                                    );
                                    foreach ($predictionTitles as $predictionType => $predictionTitle) {
                                        $predictionPayload = isset($predictionDisplayPayloads[$predictionType]) && is_array($predictionDisplayPayloads[$predictionType])
                                            ? $predictionDisplayPayloads[$predictionType]
                                            : array();
                                        if ($predictionType === 'other') {
                                            $predictionOtherType = trim((string) ($predictionFilters['other_type'] ?? ''));
                                            if (isset($predictionPayload['values']) && is_array($predictionPayload['values'])) {
                                                $predictionPayload['values'] = array_values(array_filter($predictionPayload['values'], static function ($value) use ($predictionIsSummaryText) {
                                                    return trim((string) $value) !== '' && !$predictionIsSummaryText($value);
                                                }));
                                            }
                                            $predictionExpectedOtherCount = $predictionOtherDisplayLimit($predictionOtherType);
                                            if ($predictionOtherType !== '' && $predictionExpectedOtherCount > 0) {
                                                $predictionActualOtherCount = count(array_values(array_filter((array) ($predictionPayload['values'] ?? array()), static function ($value) {
                                                    return trim((string) $value) !== '';
                                                })));
                                                if ($predictionActualOtherCount < $predictionExpectedOtherCount) {
                                                    $predictionPayload = $predictionResolveOtherPayload($predictionOtherType, $predictionNumbers);
                                                }
                                            }
                                        }
                                        if (!$predictionPayloadHasResult($predictionPayload)) {
                                            continue;
                                        }
                                        $predictionHtml = $predictionRenderPayload($predictionPayload);
                                        if ($predictionHtml !== '') {
                                            $predictionResultLines[] = array(
                                                'title' => $predictionTitle,
                                                'type_label' => (string) ($predictionTypeLabels[$predictionType] ?? ''),
                                                'html' => $predictionHtml,
                                                'type' => $predictionType,
                                                'empty' => false,
                                                'type_correct' => $predictionPayloadIsCorrect($predictionPayload),
                                                'confidence' => isset($predictionLineConfidences[$predictionType]) ? (float) $predictionLineConfidences[$predictionType] : $predictionConfidence,
                                            );
                                        }
                                    }
                                    if (empty($predictionResultLines)) {
                                        foreach ($predictionSummaryLines as $predictionSummaryLine) {
                                            $predictionSummaryLine = trim((string) $predictionSummaryLine);
                                            if ($predictionSummaryLine === '' || strpos($predictionSummaryLine, '热度') !== false || strpos($predictionSummaryLine, '未选择') !== false) {
                                                continue;
                                            }
                                            $predictionLineType = 'other';
                                            $predictionLineTitle = '结果：';
                                            $predictionLineValue = $predictionSummaryLine;
                                            if (preg_match('/^([^:：]{1,12})[:：]\s*(.+)$/u', $predictionSummaryLine, $predictionMatches)) {
                                                $predictionLineTitle = trim((string) $predictionMatches[1]) . '：';
                                                $predictionLineValue = trim((string) $predictionMatches[2]);
                                            }
                                            $predictionLineTitleKey = str_replace(array(' ', "\t", '：', ':'), '', $predictionLineTitle);
                                            if (!in_array($predictionLineTitleKey, array('生肖类型', '号码类型', '平特类型', '平码类型', '其他类型'), true)) {
                                                continue;
                                            }
                                            if (!$predictionIsDisplaySummaryLine($predictionLineTitle, $predictionLineValue)) {
                                                continue;
                                            }
                                            if ($predictionLineTitleKey === '生肖类型') {
                                                $predictionLineType = 'zodiac';
                                                preg_match('/(\d+)个生肖/u', $predictionLineValue, $predictionCountMatches);
                                                $predictionCount = isset($predictionCountMatches[1]) ? (int) $predictionCountMatches[1] : 0;
                                                $predictionZodiacValues = array();
                                                foreach ($predictionNumbers as $predictionNumber) {
                                                    $predictionZodiacValues[] = $predictionNumberToZodiac[(int) $predictionNumber] ?? '';
                                                }
                                                $predictionLineValue = array_slice($predictionUniqueValues($predictionZodiacValues), 0, max(1, $predictionCount));
                                                $predictionPayload = array('kind' => 'zodiac', 'values' => $predictionLineValue, 'groups' => array());
                                            } elseif ($predictionLineTitleKey === '号码类型') {
                                                $predictionLineType = 'number';
                                                preg_match('/(\d+)个号码/u', $predictionLineValue, $predictionCountMatches);
                                                $predictionCount = isset($predictionCountMatches[1]) ? (int) $predictionCountMatches[1] : count($predictionNumbers);
                                                $predictionPayload = array('kind' => 'number', 'values' => array_slice($predictionNumbers, 0, max(1, $predictionCount)), 'groups' => array());
                                            } elseif ($predictionLineTitleKey === '平特类型' || $predictionLineTitleKey === '平码类型') {
                                                $predictionLineType = 'pingte';
                                                if (preg_match('/(\d+)组(\d+)中\d+/u', $predictionLineValue, $predictionPingteMatches)) {
                                                    $predictionGroupCount = (int) $predictionPingteMatches[1];
                                                    $predictionGroupSize = (int) $predictionPingteMatches[2];
                                                    $predictionPayload = array(
                                                        'kind' => 'pingte-groups',
                                                        'values' => array(),
                                                        'groups' => array_chunk(array_slice($predictionNumbers, 0, $predictionGroupCount * $predictionGroupSize), $predictionGroupSize),
                                                    );
                                                } else {
                                                    preg_match('/(\d+)码/u', $predictionLineValue, $predictionCountMatches);
                                                    $predictionCount = isset($predictionCountMatches[1]) ? (int) $predictionCountMatches[1] : count($predictionNumbers);
                                                    $predictionPayload = array('kind' => 'pingte-combo', 'values' => array_slice($predictionNumbers, 0, max(1, $predictionCount)), 'groups' => array());
                                                }
                                            } else {
                                                preg_match('/(\d+)个(?:波色|单双|大小|头数|尾数)/u', $predictionLineValue, $predictionOtherCountMatches);
                                                $predictionOtherLimit = isset($predictionOtherCountMatches[1]) ? (int) $predictionOtherCountMatches[1] : 0;
                                                if ($predictionOtherZodiacCount > 0 || preg_match('/平特[一二三四五]肖/u', $predictionLineValue)) {
                                                    $predictionResolvedOtherType = $predictionOtherType;
                                                    if ($predictionResolvedOtherType === '' && preg_match('/平特([一二三四五])肖/u', $predictionLineValue, $predictionOtherZodiacLabelMatches)) {
                                                        $predictionOtherZodiacMap = array('一' => 1, '二' => 2, '三' => 3, '四' => 4, '五' => 5);
                                                        $predictionResolvedOtherType = 'pt_zodiac_' . (int) ($predictionOtherZodiacMap[$predictionOtherZodiacLabelMatches[1]] ?? 0);
                                                    }
                                                    $predictionPayload = $predictionResolveOtherPayload($predictionResolvedOtherType, $predictionNumbers);
                                                } elseif (strpos($predictionLineValue, '波色') !== false) {
                                                    $predictionPayload = $predictionResolveOtherPayload('wave', $predictionNumbers);
                                                } elseif (strpos($predictionLineValue, '单双') !== false) {
                                                    $predictionPayload = $predictionResolveOtherPayload('odd_even', $predictionNumbers);
                                                } elseif (strpos($predictionLineValue, '大小') !== false) {
                                                    $predictionPayload = $predictionResolveOtherPayload('big_small', $predictionNumbers);
                                                } elseif (strpos($predictionLineValue, '头数') !== false) {
                                                    $predictionPayload = $predictionResolveOtherPayload('head', $predictionNumbers);
                                                } elseif (strpos($predictionLineValue, '尾数') !== false) {
                                                    $predictionPayload = $predictionResolveOtherPayload('tail', $predictionNumbers);
                                                } else {
                                                    if ($predictionIsSummaryText($predictionLineValue)) {
                                                        continue;
                                                    }
                                                    $predictionOtherValues = array($predictionLineValue);
                                                    $predictionOtherValues = $predictionUniqueValues($predictionOtherValues);
                                                    if ($predictionOtherLimit > 0) {
                                                        $predictionOtherValues = array_slice($predictionOtherValues, 0, $predictionOtherLimit);
                                                    }
                                                    $predictionPayload = array('kind' => 'other', 'values' => $predictionOtherValues, 'groups' => array());
                                                }
                                            }
                                            $predictionHtml = $predictionRenderPayload($predictionPayload);
                                            if ($predictionHtml !== '') {
                                                $predictionResultLines[] = array(
                                                    'title' => $predictionLineTitle,
                                                    'type_label' => (string) ($predictionTypeLabels[$predictionLineType] ?? ''),
                                                    'html' => $predictionHtml,
                                                    'type' => $predictionLineType,
                                                    'empty' => false,
                                                    'type_correct' => $predictionPayloadIsCorrect($predictionPayload),
                                                    'confidence' => $predictionConfidence,
                                                );
                                            }
                                        }
                                    }
                                    if (empty($predictionResultLines)) {
                                        $predictionResultLines[] = array(
                                            'title' => '结果：',
                                            'type_label' => '',
                                            'html' => '<span class="forecast-result-placeholder">当前没有可展示的预测结果</span>',
                                            'type' => 'other',
                                            'empty' => false,
                                            'type_correct' => false,
                                            'confidence' => $predictionConfidence,
                                        );
                                    }
                                    ?>
                                    <div class="member-ai-log-card">
                                        <label class="member-ai-log-check">
                                            <input
                                                type="checkbox"
                                                class="member-ai-log-check-input h-3.5 w-3.5 accent-blue-600"
                                                name="prediction_ids[]"
                                                value="<?php echo e((string) ($predictionLog['id'] ?? 0)); ?>"
                                            >
                                            <span class="sr-only">选择记录</span>
                                        </label>
                                        <div class="member-ai-log-card-content">
                                            <div class="min-w-0 flex-1">
                                                <div class="forecast-block-title forecast-result-title">
                                                    <span class="forecast-result-issue-badge <?php echo $predictionRegion === 'hongkong' ? 'is-hongkong' : 'is-macau'; ?>">
                                                        <span class="forecast-result-issue-mark"><?php echo e($predictionRegionMark); ?></span>
                                                        <span class="forecast-result-issue-text"><?php echo e($predictionIssueText); ?></span>
                                                    </span>
                                                    <span class="forecast-result-title-text">结果:</span>
                                                    <span class="member-ai-log-draw-result<?php echo $predictionDrawResultHtml === '' ? ' is-pending' : ''; ?>">
                                                        <?php if ($predictionDrawResultHtml !== ''): ?>
                                                            <span class="member-ai-log-draw-row">
                                                                <?php echo $predictionDrawResultHtml; ?>
                                                            </span>
                                                            <span class="member-ai-log-draw-row">
                                                                <?php echo $predictionDrawZodiacResultHtml; ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="member-ai-log-draw-row">
                                                                <span class="member-ai-log-draw-label">待开奖</span>
                                                            </span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                                <div class="forecast-result-summary mt-2">
                                                    <?php foreach ($predictionResultLines as $predictionResultLine): ?>
                                                        <?php
                                                        $predictionLineConfidence = (float) ($predictionResultLine['confidence'] ?? $predictionConfidence);
                                                        $predictionShowsConfidence = empty($predictionResultLine['empty']) && $predictionLineConfidence > 0;
                                                        $predictionTypeCorrect = !empty($predictionResultLine['type_correct']);
                                                        ?>
                                                        <div class="forecast-result-summary-line is-<?php echo e($predictionResultLine['type']); ?><?php echo !empty($predictionResultLine['empty']) ? ' is-unselected' : ''; ?><?php echo $predictionShowsConfidence ? '' : ' is-no-confidence'; ?>">
                                                            <span class="forecast-result-summary-head">
                                                                <span class="forecast-result-summary-label"><?php echo e($predictionResultLine['title']); ?></span>
                                                            </span>
                                                            <?php if ($predictionShowsConfidence): ?>
                                                                <span class="forecast-result-summary-confidence"><?php echo e(number_format($predictionLineConfidence, 1)); ?>%</span>
                                                            <?php endif; ?>
                                                            <span class="forecast-result-summary-body">
                                                                <?php if (trim((string) ($predictionResultLine['type_label'] ?? '')) !== ''): ?>
                                                                    <span class="forecast-result-summary-type">
                                                                        <span class="forecast-result-type-chip<?php echo $predictionTypeCorrect ? ' is-correct' : ''; ?>">
                                                                            <?php echo e(trim((string) $predictionResultLine['type_label']) . ':'); ?>
                                                                            <?php if ($predictionTypeCorrect): ?>
                                                                                <span class="forecast-result-correct-mark" aria-hidden="true">✓</span>
                                                                            <?php endif; ?>
                                                                        </span>
                                                                    </span>
                                                                <?php endif; ?>
                                                                <span class="forecast-result-summary-value"><?php echo $predictionResultLine['html']; ?></span>
                                                            </span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                </div>
                                                <div class="member-ai-log-time"><?php echo e(format_datetime($predictionLog['created_at'])); ?></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="member-ai-log-empty">当前还没有预测记录。</div>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>
                <?php elseif ($memberTab === 'purchases'): ?>
                    <section class="member-tab-panel">
                        <div class="member-tab-head">
                            <div>
                                <h2>购买记录</h2>
                            </div>
                            <div class="member-ai-log-badge"><?php echo e((string) $memberPurchaseCount); ?>条</div>
                        </div>
                        <div class="member-purchase-list">
                            <?php if (!empty($purchaseRecords)): ?>
                                <?php foreach ($purchaseRecords as $purchaseRecord): ?>
                                    <?php
                                    $purchasePostUrl = public_url('post.php') . '?id=' . urlencode((string) $purchaseRecord['post_id']);
                                    $purchaseRegionText = (string) ($purchaseRecord['region'] ?? '') === 'hongkong' ? '香港' : '澳门';
                                    $purchaseStatus = (string) ($purchaseRecord['status'] ?? '');
                                    $purchaseTitle = trim((string) ($purchaseRecord['display_title_text'] ?? ''));
                                    if ($purchaseTitle === '') {
                                        $purchaseTitle = (string) ($purchaseRecord['title'] ?? '已购买帖子');
                                    }
                                    $purchaseTitleHtml = trim((string) ($purchaseRecord['display_title_html'] ?? ''));
                                    $purchaseStateText = $purchaseStatus === 'deleted' ? '已下架' : '查看内容';
                                    $purchaseStateClass = $purchaseStatus === 'deleted' ? ' is-offline' : ' is-view';
                                    $purchaseCardUrl = $purchaseStatus === 'deleted' ? $memberTabUrl('purchases') : $purchasePostUrl;
                                    ?>
                                    <a class="member-purchase-card" href="<?php echo e($purchaseCardUrl); ?>" data-member-purchase-card data-purchase-status="<?php echo e($purchaseStatus); ?>" data-purchase-post-url="<?php echo e($purchasePostUrl); ?>" data-purchase-title="<?php echo e($purchaseTitle); ?>">
                                        <span class="member-purchase-head">
                                            <span class="member-purchase-title"><?php echo $purchaseTitleHtml !== '' ? $purchaseTitleHtml : e($purchaseTitle); ?></span>
                                            <span class="member-purchase-state<?php echo e($purchaseStateClass); ?>"><?php echo e($purchaseStateText); ?></span>
                                        </span>
                                        <span class="member-purchase-meta">
                                            <span class="is-region"><?php echo e($purchaseRegionText); ?></span>
                                            <span class="is-score"><?php echo e((string) $purchaseRecord['price']); ?>积分</span>
                                            <span class="is-time"><?php echo e(format_datetime($purchaseRecord['created_at'])); ?></span>
                                        </span>
                                    </a>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="member-ai-log-empty">当前还没有购买帖子记录。</div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php elseif ($memberTab === 'about'): ?>
                    <section class="member-tab-panel">
                        <div class="member-tab-head">
                            <div>
                                <h2>论坛指南</h2>
                            </div>
                            <div class="member-ai-log-badge">规则说明</div>
                        </div>
                        <div class="member-about-list">
                            <div class="member-about-card">
                                <div class="member-about-icon is-invite"><?php echo $memberIconSvg('user-plus'); ?></div>
                                <div class="member-about-body">
                                    <strong class="member-about-title">邀请好友规则</strong>
                                    <?php echo $memberForumGuideBodyHtml('invite_rule'); ?>
                                </div>
                            </div>
                            <div class="member-about-card">
                                <div class="member-about-icon is-recharge"><?php echo $memberIconSvg('wallet'); ?></div>
                                <div class="member-about-body">
                                    <strong class="member-about-title">充值规则</strong>
                                    <?php echo $memberForumGuideBodyHtml('recharge_rule'); ?>
                                </div>
                            </div>
                            <div class="member-about-card">
                                <div class="member-about-icon is-purchase"><?php echo $memberIconSvg('receipt'); ?></div>
                                <div class="member-about-body">
                                    <strong class="member-about-title">购买规则</strong>
                                    <?php echo $memberForumGuideBodyHtml('purchase_rule'); ?>
                                </div>
                            </div>
                            <div class="member-about-card">
                                <div class="member-about-icon is-rules"><?php echo $memberIconSvg('shield-halved'); ?></div>
                                <div class="member-about-body">
                                    <strong class="member-about-title">遵守规则</strong>
                                    <?php echo $memberForumGuideBodyHtml('conduct_rule'); ?>
                                </div>
                            </div>
                        </div>
                    </section>
                <?php else: ?>
                    <section class="member-tab-panel">
                        <div class="member-tab-head">
                            <div>
                                <h2>个人资料</h2>
                            </div>
                            <div class="member-ai-log-badge">安全设置</div>
                        </div>
                        <div class="member-profile-grid">
                            <div class="member-profile-card member-invite-card">
                                <div class="member-profile-title">邀请好友</div>
                                <label class="member-form-label">邀请账号</label>
                                <input class="auth-input" type="text" value="<?php echo e((string) ($user['username'] ?? '')); ?>" readonly>
                                <label class="member-form-label">邀请注册链接</label>
                                <input class="auth-input" type="text" value="<?php echo e($memberInviteUrl); ?>" readonly>
                            </div>
                            <form method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form class="member-profile-card">
                                <input type="hidden" name="action" value="profile.update">
                                <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                                <div class="member-profile-title">基础资料</div>
                                <label class="member-form-label">邮箱</label>
                                <input class="auth-input" type="text" name="email" value="<?php echo e((string) ($user['email'] ?? '')); ?>" placeholder="可留空">
                                <label class="member-form-label">个人签名</label>
                                <textarea name="bio" rows="3" class="member-form-textarea" placeholder="填写个人说明"><?php echo e((string) ($user['bio'] ?? '')); ?></textarea>
                                <button type="submit" class="member-form-submit">保存资料</button>
                            </form>
                            <form method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form class="member-profile-card">
                                <input type="hidden" name="action" value="profile.password">
                                <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                                <div class="member-profile-title">修改会员用户密码</div>
                                <label class="member-form-label">当前密码</label>
                                <input class="auth-input" type="password" name="old_password" autocomplete="current-password" placeholder="请输入当前登录密码">
                                <label class="member-form-label">新密码</label>
                                <input class="auth-input" type="password" name="password" autocomplete="new-password" placeholder="请输入新密码">
                                <label class="member-form-label">确认新密码</label>
                                <input class="auth-input" type="password" name="confirm_password" autocomplete="new-password" placeholder="请再次输入新密码">
                                <button type="submit" class="member-form-submit">更新密码</button>
                            </form>
                            <form method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form class="member-profile-card">
                                <input type="hidden" name="action" value="profile.recovery">
                                <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                                <div class="member-profile-title">修改找回密码信息</div>
                                <label class="member-form-label">当前密码</label>
                                <input class="auth-input" type="password" name="current_password" autocomplete="current-password" placeholder="请输入当前登录密码">
                                <label class="member-form-label">新的找回验证信息</label>
                                <input class="auth-input" type="text" name="recovery_answer" autocomplete="off" placeholder="请输入新的找回验证信息，2-60 个字符">
                                <label class="member-form-label">再次填写</label>
                                <input class="auth-input" type="text" name="confirm_recovery_answer" autocomplete="off" placeholder="请再次填写找回验证信息">
                                <button type="submit" class="member-form-submit">更新找回信息</button>
                            </form>
                        </div>
                    </section>
                    <?php endif; ?>
                </div>
                <?php echo \App\Core\View::make(app(), 'partials/member_recharge_modal', array(
                    'region' => $region,
                    'memberRechargeUser' => $user,
                    'memberRechargeUrl' => $memberRechargeUrl,
                    'memberRechargePaymentSettings' => $memberRechargePaymentSettings,
                    'memberRechargeQrLists' => $memberRechargeQrLists,
                    'memberRechargeQrListJson' => $memberRechargeQrListJson,
                    'memberRechargeAlipayQr' => $memberRechargeAlipayQr,
                    'memberRechargeWechatQr' => $memberRechargeWechatQr,
                    'memberRechargeUsdtQr' => $memberRechargeUsdtQr,
                    'memberRechargeAlipaySrc' => $memberRechargeAlipaySrc,
                    'memberRechargeWechatSrc' => $memberRechargeWechatSrc,
                    'memberRechargeUsdtSrc' => $memberRechargeUsdtSrc,
                    'memberRechargeUsdtAddress' => $memberRechargeUsdtAddress,
                    'memberRechargeUsdtAddressText' => $memberRechargeUsdtAddressText,
                )); ?>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php if (!$user): ?>
<script>
(function () {
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
})();
</script>
<?php endif; ?>
<?php echo \App\Core\View::make(app(), 'partials/front_bottom_nav', array('region' => $region, 'activePanel' => 'member', 'user' => $user)); ?>
