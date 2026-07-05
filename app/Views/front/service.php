<?php
$customerServicePayload = isset($customerServicePayload) && is_array($customerServicePayload) ? $customerServicePayload : array();
$customerServiceMode = isset($customerServiceMode) ? (string) $customerServiceMode : 'guest';
$customerServiceEmbed = !empty($customerServiceEmbed);
$customerServiceAgentPanel = isset($customerServiceAgentPanel) ? (string) $customerServiceAgentPanel : 'console';
if (!in_array($customerServiceAgentPanel, array('console', 'manage'), true)) {
    $customerServiceAgentPanel = 'console';
}
$customerServiceStatus = isset($customerServiceStatus) ? (string) $customerServiceStatus : 'all';
$customerServiceAgent = isset($customerServiceAgent) && is_array($customerServiceAgent) ? $customerServiceAgent : null;
$customerServiceSession = isset($customerServicePayload['session']) && is_array($customerServicePayload['session']) ? $customerServicePayload['session'] : null;
$customerServiceSessions = isset($customerServicePayload['sessions']) && is_array($customerServicePayload['sessions']) ? $customerServicePayload['sessions'] : array();
$customerServiceMessages = isset($customerServicePayload['messages']) && is_array($customerServicePayload['messages']) ? $customerServicePayload['messages'] : array();
$customerServiceEmojis = isset($customerServicePayload['emojis']) && is_array($customerServicePayload['emojis']) ? $customerServicePayload['emojis'] : array();
$customerServiceTypingStatus = isset($customerServicePayload['typing_status']) && is_array($customerServicePayload['typing_status']) ? $customerServicePayload['typing_status'] : array();
$customerServiceMemberProfile = isset($customerServicePayload['service_profile']) && is_array($customerServicePayload['service_profile']) ? $customerServicePayload['service_profile'] : array();
$customerServiceAgentSettings = isset($customerServicePayload['agent']) && is_array($customerServicePayload['agent']) ? $customerServicePayload['agent'] : ($customerServiceAgent ?: array());
$customerServiceMemberTitle = (string) (($customerServiceMemberProfile['display_name'] ?? '') ?: '在线客服');
$customerServiceMemberHours = (string) (($customerServiceMemberProfile['service_hours'] ?? '') ?: '09:00-23:00');
$customerServiceMemberWelcomeText = (string) (($customerServiceMemberProfile['welcome_text'] ?? '') ?: '您好，这里是在线客服，请直接留言，客服看到后会尽快回复。');
$customerServiceMemberActivityNotice = trim((string) ($customerServiceMemberProfile['activity_notice'] ?? ''));
$customerServiceMemberActivityNoticeEnabled = array_key_exists('activity_notice_enabled', $customerServiceMemberProfile)
    ? !empty($customerServiceMemberProfile['activity_notice_enabled'])
    : $customerServiceMemberActivityNotice !== '';
$customerServiceStatusType = (string) ($customerServiceTypingStatus['status_type'] ?? 'serving');
$customerServiceTypingStatusText = trim((string) ($customerServiceTypingStatus['text'] ?? ''));
$customerServiceTypingStatusVisible = $customerServiceTypingStatusText !== '';
$customerServiceAvatarStatusText = (string) ($customerServiceTypingStatus['avatar_label'] ?? '在线');
$customerServiceAvatarStatusType = (string) ($customerServiceTypingStatus['avatar_status_type'] ?? 'online');
$customerServiceSessionId = $customerServiceSession ? (int) ($customerServiceSession['id'] ?? 0) : (int) ($customerServicePayload['active_id'] ?? 0);
$customerServiceLastDate = '';
$customerServiceAgentName = $customerServiceAgent ? (string) (($customerServiceAgentSettings['display_name'] ?? '') ?: ($customerServiceAgent['username'] ?? '客服')) : '';
$customerServiceAgentServiceHours = (string) (($customerServiceAgentSettings['service_hours'] ?? '') ?: '09:00-23:00');
$customerServiceAgentWelcomeText = (string) ($customerServiceAgentSettings['welcome_text'] ?? '');
$customerServiceAgentAutoReplyText = (string) ($customerServiceAgentSettings['auto_reply_text'] ?? '');
$customerServiceAgentActivityNotice = (string) ($customerServiceAgentSettings['activity_notice'] ?? '');
$customerServiceAgentActivityNoticeEnabled = array_key_exists('activity_notice_enabled', $customerServiceAgentSettings)
    ? !empty($customerServiceAgentSettings['activity_notice_enabled'])
    : trim($customerServiceAgentActivityNotice) !== '';
$customerServiceAgentNicknameOptions = isset($customerServiceAgentSettings['nickname_options']) && is_array($customerServiceAgentSettings['nickname_options'])
    ? $customerServiceAgentSettings['nickname_options']
    : array();
$customerServiceAgentNicknameOptions = array_values(array_unique(array_filter(array_merge(
    array(
        (string) ($customerServiceAgentSettings['display_name'] ?? ''),
        (string) ($customerServiceAgent['display_name'] ?? ''),
        (string) ($customerServiceAgent['username'] ?? ''),
    ),
    $customerServiceAgentNicknameOptions
))));
$customerServiceAgentOnline = !empty($customerServicePayload['agent_online']);
$customerServiceAgentOnlineLabel = (string) ($customerServicePayload['agent_online_label'] ?? ($customerServiceAgentOnline ? '在线中···' : '休息中···'));
$customerServiceAgentOnlineType = (string) ($customerServicePayload['agent_online_type'] ?? ($customerServiceAgentOnline ? 'online' : 'offline'));
$customerServiceCanReply = !empty($customerServicePayload['can_reply']);
$customerServiceCanClear = !empty($customerServicePayload['can_clear']);
$customerServiceActiveName = $customerServiceSession ? (string) (($customerServiceSession['username'] ?? '') ?: '会员') : '会员';
$customerServiceActiveInitial = function_exists('mb_substr') ? mb_substr($customerServiceActiveName, 0, 1, 'UTF-8') : substr($customerServiceActiveName, 0, 1);
$customerServiceActiveScore = 0;
if ($customerServiceMode === 'agent' && $customerServiceSession) {
    // 客服接待充值弹窗展示当前会员积分。
    $customerServiceActiveUser = app()->users()->findById((int) ($customerServiceSession['user_id'] ?? 0));
    if (is_array($customerServiceActiveUser)) {
        $customerServiceActiveScore = max(0, (int) ($customerServiceActiveUser['score'] ?? 0));
    }
}
$customerServiceAgentQueueUnread = 0;
foreach ($customerServiceSessions as $customerServiceListSession) {
    $customerServiceAgentQueueUnread += max(0, (int) ($customerServiceListSession['unread_for_admin'] ?? 0));
}
$customerServiceIconSvg = static function ($name) {
    $icons = array(
        'circle-user' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 4.2a3.2 3.2 0 1 1 0 6.4 3.2 3.2 0 0 1 0-6.4Zm0 13.8a7.95 7.95 0 0 1-5.6-2.28c.8-2.2 2.86-3.52 5.6-3.52s4.8 1.32 5.6 3.52A7.95 7.95 0 0 1 12 20Z"/></svg>',
        'coins' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3c4.42 0 8 1.57 8 3.5S16.42 10 12 10 4 8.43 4 6.5 7.58 3 12 3Zm-8 6.2c1.42 1.3 4.28 2.05 8 2.05s6.58-.75 8-2.05v2.3c0 1.93-3.58 3.5-8 3.5s-8-1.57-8-3.5V9.2Zm0 5c1.42 1.3 4.28 2.05 8 2.05s6.58-.75 8-2.05v2.3c0 1.93-3.58 3.5-8 3.5s-8-1.57-8-3.5v-2.3Z"/></svg>',
        'face-smile' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 18a8 8 0 1 1 0-16 8 8 0 0 1 0 16ZM8.5 9.5a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Zm6 0a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0ZM7.8 14h2.05a2.5 2.5 0 0 0 4.3 0h2.05a4.45 4.45 0 0 1-8.4 0Z"/></svg>',
        'headset' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a8 8 0 0 0-8 8v4.2A2.8 2.8 0 0 0 6.8 18H9v-7H6.2A5.8 5.8 0 0 1 18 11h-3v7h2.1c-.45 1.18-1.45 2-3.1 2h-2v2h2c3.2 0 5.45-1.86 5.9-4.55A2.8 2.8 0 0 0 22 14.8V11a10 10 0 0 0-10-8Zm-5 10h1v3H6.8a.8.8 0 0 1-.8-.8V13h1Zm10 0h1v2.2a.8.8 0 0 1-.8.8H17v-3Z"/></svg>',
        'image' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm0 2v8.6l4.2-4.2 3.2 3.2 2.4-2.4L20 18V7H4Zm2 10h11.2l-3.4-3.4-2.4 2.4-3.2-3.2L4 17h2Zm11-8.5a1.5 1.5 0 1 1-3 0 1.5 1.5 0 0 1 3 0Z"/></svg>',
        'microphone' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a3 3 0 0 0-3 3v6a3 3 0 0 0 6 0V6a3 3 0 0 0-3-3Zm-5 8H5a7 7 0 0 0 6 6.92V21h2v-3.08A7 7 0 0 0 19 11h-2a5 5 0 0 1-10 0Z"/></svg>',
        'paper-plane' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 20 22 12 3 4v6.2l11 1.8-11 1.8V20Z"/></svg>',
        'trash-can' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M9 3h6l1 2h4v2H4V5h4l1-2Zm-3 6h12l-1 12H7L6 9Zm3 2 .45 8h1.8L10.8 11H9Zm4.2 0-.45 8h1.8L15 11h-1.8Z"/></svg>',
        'volume-high' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 9v6h4l5 4V5L8 9H4Zm12.5-2.5-1.4 1.4A5.8 5.8 0 0 1 16.8 12a5.8 5.8 0 0 1-1.7 4.1l1.4 1.4A7.75 7.75 0 0 0 18.8 12a7.75 7.75 0 0 0-2.3-5.5Zm2.8-2.8-1.4 1.4A9.7 9.7 0 0 1 20.8 12a9.7 9.7 0 0 1-2.9 6.9l1.4 1.4A11.65 11.65 0 0 0 22.8 12a11.65 11.65 0 0 0-3.5-8.3Z"/></svg>',
        'xmark' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m6.4 5 5.6 5.6L17.6 5 19 6.4 13.4 12l5.6 5.6-1.4 1.4-5.6-5.6L6.4 19 5 17.6l5.6-5.6L5 6.4 6.4 5Z"/></svg>',
    );
    $key = isset($icons[$name]) ? $name : 'circle-user';

    return '<i class="front-fa-icon front-icon-' . e($key) . '" aria-hidden="true">' . $icons[$key] . '</i>';
};
$customerServiceAgentChatUnread = $customerServiceSession ? max(0, (int) ($customerServiceSession['unread_for_admin'] ?? 0)) : 0;
$customerServiceRequestedAgentView = isset($_GET['agent_view']) ? trim((string) $_GET['agent_view']) : '';
if (!in_array($customerServiceRequestedAgentView, array('queue', 'chat'), true)) {
    $customerServiceRequestedAgentView = 'queue';
}
$customerServiceAgentView = $customerServiceRequestedAgentView === 'chat' && $customerServiceSession ? 'chat' : 'queue';
$customerServiceAgentBaseUrl = public_url('service.php') . '?' . http_build_query(array('region' => $region, 'agent' => '1'));
$customerServiceBlockSelectLabel = function ($session) {
    if (!$session || !is_array($session) || empty($session['blocked'])) {
        return '屏蔽会话';
    }

    $blockedUntil = trim((string) ($session['blocked_until'] ?? ''));
    if ($blockedUntil === '') {
        return '永久屏蔽';
    }

    $blockedAt = trim((string) ($session['blocked_at'] ?? ''));
    $blockedAtTime = $blockedAt !== '' ? strtotime($blockedAt) : false;
    $blockedUntilTime = strtotime($blockedUntil);
    if ($blockedAtTime !== false && $blockedUntilTime !== false && $blockedUntilTime > $blockedAtTime) {
        $seconds = $blockedUntilTime - $blockedAtTime;
        $durationLabels = array(
            3600 => '屏蔽1小时',
            86400 => '屏蔽24小时',
            604800 => '屏蔽7天',
            2592000 => '屏蔽30天',
        );
        foreach ($durationLabels as $durationSeconds => $durationLabel) {
            if (abs($seconds - $durationSeconds) <= 300) {
                return $durationLabel;
            }
        }
    }

    return '限时屏蔽';
};
$customerServiceActiveBlockLabel = $customerServiceBlockSelectLabel($customerServiceSession);
$customerServicePaymentSettings = isset($customerServicePayload['payment_settings']) && is_array($customerServicePayload['payment_settings'])
    ? $customerServicePayload['payment_settings']
    : array();
$customerServicePaymentActiveType = isset($customerServicePaymentActiveType) ? (string) $customerServicePaymentActiveType : 'alipay';
if (!in_array($customerServicePaymentActiveType, array('alipay', 'wechat', 'usdt'), true)) {
    $customerServicePaymentActiveType = 'alipay';
}
$customerServicePaymentStoredQrLists = array();
try {
    $customerServicePaymentStoredQrLists = app()->support()->paymentQrLists();
} catch (\Throwable $exception) {
    $customerServicePaymentStoredQrLists = array();
}
$customerServiceForumGuideRules = app()->support()->forumGuideRules();
$customerServicePaymentQrLists = array();
foreach (array('alipay', 'wechat', 'usdt') as $customerServicePaymentType) {
    $customerServicePaymentListKey = $customerServicePaymentType . '_qrs';
    $customerServicePaymentSingleKey = $customerServicePaymentType . '_qr';
    $customerServicePaymentQrList = array();
    if (isset($customerServicePaymentStoredQrLists[$customerServicePaymentType])
        && is_array($customerServicePaymentStoredQrLists[$customerServicePaymentType])
    ) {
        foreach ($customerServicePaymentStoredQrLists[$customerServicePaymentType] as $customerServicePaymentQrUrl) {
            $customerServicePaymentQrUrl = trim((string) $customerServicePaymentQrUrl);
            if ($customerServicePaymentQrUrl !== '') {
                $customerServicePaymentQrList[] = $customerServicePaymentQrUrl;
            }
        }
    } elseif (isset($customerServicePaymentSettings[$customerServicePaymentListKey])
        && is_array($customerServicePaymentSettings[$customerServicePaymentListKey])
    ) {
        foreach ($customerServicePaymentSettings[$customerServicePaymentListKey] as $customerServicePaymentQrUrl) {
            $customerServicePaymentQrUrl = trim((string) $customerServicePaymentQrUrl);
            if ($customerServicePaymentQrUrl !== '') {
                $customerServicePaymentQrList[] = $customerServicePaymentQrUrl;
            }
        }
    }
    if (!$customerServicePaymentQrList) {
        $customerServicePaymentQrUrl = trim((string) ($customerServicePaymentSettings[$customerServicePaymentSingleKey] ?? ''));
        if ($customerServicePaymentQrUrl !== '') {
            $customerServicePaymentQrList[] = $customerServicePaymentQrUrl;
        }
    }
    $customerServicePaymentQrLists[$customerServicePaymentType] = array_values(array_unique($customerServicePaymentQrList));
}
$customerServicePaymentRows = array(
    array(
        'type' => 'alipay',
        'label' => '支付宝',
        'field' => 'payment_qr_alipay',
        'icon' => 'fa-brands fa-alipay',
        'value' => isset($customerServicePaymentQrLists['alipay'][0]) ? $customerServicePaymentQrLists['alipay'][0] : '',
        'values' => $customerServicePaymentQrLists['alipay'],
    ),
    array(
        'type' => 'wechat',
        'label' => '微信',
        'field' => 'payment_qr_wechat',
        'icon' => 'fa-brands fa-weixin',
        'value' => isset($customerServicePaymentQrLists['wechat'][0]) ? $customerServicePaymentQrLists['wechat'][0] : '',
        'values' => $customerServicePaymentQrLists['wechat'],
    ),
    array(
        'type' => 'usdt',
        'label' => 'USDT',
        'field' => 'payment_qr_usdt',
        'icon' => 'fa-solid fa-coins',
        'value' => isset($customerServicePaymentQrLists['usdt'][0]) ? $customerServicePaymentQrLists['usdt'][0] : '',
        'values' => $customerServicePaymentQrLists['usdt'],
    ),
);
$customerServiceUsdtAddress = trim((string) ($customerServicePaymentSettings['usdt_address'] ?? ''));
if ($customerServiceUsdtAddress === '') {
    try {
        $customerServicePaymentStoredSettings = app()->support()->paymentSettings();
        if (is_array($customerServicePaymentStoredSettings)) {
            $customerServiceUsdtAddress = trim((string) ($customerServicePaymentStoredSettings['usdt_address'] ?? ''));
        }
    } catch (\Throwable $exception) {
        $customerServiceUsdtAddress = '';
    }
}
?>
<?php if (!$customerServiceEmbed && $customerServiceMode !== 'agent'): ?>
    <?php echo \App\Core\View::make(app(), 'partials/front_top_bar', array('region' => $region)); ?>
<?php endif; ?>

<?php if ($customerServiceMode === 'agent' && $customerServiceAgentPanel === 'manage'): ?>
    <?php if (!$customerServiceEmbed): ?>
        <?php echo \App\Core\View::make(app(), 'partials/front_top_bar', array('region' => $region, 'agentLogoutAction' => true)); ?>
    <?php endif; ?>
    <?php if (!empty($customerServicePaymentFlash['message'])): ?>
        <div hidden data-app-notice-seed data-app-notice-type="<?php echo e((string) ($customerServicePaymentFlash['type'] ?? 'info')); ?>" data-app-notice-message="<?php echo e((string) $customerServicePaymentFlash['message']); ?>"></div>
    <?php endif; ?>
    <section class="front-page-shell front-unified-page customer-service-page customer-service-agent-page">
        <div class="data-frame front-panel-stack front-unified-frame customer-service-frame customer-service-agent-frame">
            <div class="customer-service-agent-shell front-unified-content">
                <section class="customer-service-agent-console customer-service-agent-management">
                    <header class="service-agent-top">
                        <div class="service-agent-top-main">
                            <h1 class="service-agent-title-name">收款二维码后台</h1>
                        </div>
                    </header>

            <main class="service-agent-payment-panel">
                <div
                    id="service-agent-payment-form"
                    class="service-agent-payment-form"
                >
                    <div class="service-agent-payment-head">
                        <div class="service-agent-payment-actions">
                            <div class="service-agent-payment-switches" role="group" aria-label="二维码池切换">
                                <?php foreach ($customerServicePaymentRows as $paymentSwitchRow): ?>
                                    <?php
                                    $paymentSwitchType = (string) $paymentSwitchRow['type'];
                                    $paymentSwitchActive = $paymentSwitchType === $customerServicePaymentActiveType;
                                    $paymentSwitchUrl = public_url('service.php') . '?' . http_build_query(array(
                                        'region' => $region,
                                        'agent' => '1',
                                        'panel' => 'manage',
                                        'payment_type' => $paymentSwitchType,
                                    ));
                                    ?>
                                    <a
                                        class="service-agent-payment-switch is-<?php echo e($paymentSwitchType); ?><?php echo $paymentSwitchActive ? ' is-active' : ''; ?>"
                                        href="<?php echo e($paymentSwitchUrl); ?>"
                                        aria-controls="service-agent-payment-<?php echo e($paymentSwitchType); ?>"
                                        aria-current="<?php echo $paymentSwitchActive ? 'page' : 'false'; ?>"
                                        aria-selected="<?php echo $paymentSwitchActive ? 'true' : 'false'; ?>"
                                    >
                                        <i class="<?php echo e((string) $paymentSwitchRow['icon']); ?>"></i>
                                        <span><?php echo e((string) $paymentSwitchRow['label']); ?></span>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="service-agent-payment-grid">
                        <?php foreach ($customerServicePaymentRows as $paymentRow): ?>
                            <?php
                            $paymentImageUrls = isset($paymentRow['values']) && is_array($paymentRow['values'])
                                ? $paymentRow['values']
                                : array();
                            $paymentRowType = (string) $paymentRow['type'];
                            $paymentCardActive = $paymentRowType === $customerServicePaymentActiveType;
                            $paymentUploadAction = public_url('service.php') . '?' . http_build_query(array(
                                'region' => $region,
                                'agent' => '1',
                                'panel' => 'manage',
                                '_service_action' => 'customer_service.payment_upload',
                                'payment_type' => $paymentRowType,
                            ));
                            ?>
                            <form
                                id="service-agent-payment-<?php echo e($paymentRowType); ?>"
                                class="service-agent-payment-card is-<?php echo e($paymentRowType); ?><?php echo $paymentCardActive ? ' is-active' : ''; ?>"
                                method="post"
                                action="<?php echo e($paymentUploadAction); ?>"
                                enctype="multipart/form-data"
                                data-service-agent-payment-upload-form
                            >
                                <input type="hidden" name="_service_action" value="customer_service.payment_upload">
                                <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                                <input type="hidden" name="region" value="<?php echo e($region); ?>">
                                <input type="hidden" name="agent" value="1">
                                <input type="hidden" name="panel" value="manage">
                                <input type="hidden" name="payment_type" value="<?php echo e($paymentRowType); ?>">
                                <div class="service-agent-payment-card-head">
                                    <i class="<?php echo e((string) $paymentRow['icon']); ?>"></i>
                                    <div>
                                        <strong><?php echo e((string) $paymentRow['label']); ?></strong>
                                    </div>
                                </div>
                                <div class="service-agent-payment-preview">
                                    <?php if ($paymentImageUrls): ?>
                                        <?php foreach ($paymentImageUrls as $paymentImageIndex => $paymentImageUrl): ?>
                                            <?php $paymentImageUrl = trim((string) $paymentImageUrl); ?>
                                            <?php if ($paymentImageUrl === ''): ?>
                                                <?php continue; ?>
                                            <?php endif; ?>
                                            <?php $paymentImageLoading = $paymentCardActive ? 'eager' : 'lazy'; ?>
                                            <?php $paymentImagePriority = $paymentCardActive ? 'high' : 'low'; ?>
                                            <div class="service-agent-payment-preview-item">
                                                <button
                                                    type="button"
                                                    class="service-agent-payment-preview-open"
                                                    data-service-agent-payment-preview-open="<?php echo e($paymentImageUrl); ?>"
                                                    data-service-agent-payment-preview-title="<?php echo e((string) $paymentRow['label']); ?>二维码<?php echo e((string) ($paymentImageIndex + 1)); ?>"
                                                    aria-label="预览<?php echo e((string) $paymentRow['label']); ?>二维码<?php echo e((string) ($paymentImageIndex + 1)); ?>"
                                                >
                                                    <img src="<?php echo e($paymentImageUrl); ?>" alt="<?php echo e((string) $paymentRow['label']); ?>二维码<?php echo e((string) ($paymentImageIndex + 1)); ?>" width="118" height="118" loading="<?php echo e($paymentImageLoading); ?>" decoding="async" fetchpriority="<?php echo e($paymentImagePriority); ?>">
                                                </button>
                                                <span class="service-agent-payment-preview-order"><?php echo e((string) ($paymentImageIndex + 1)); ?></span>
                                                <button
                                                    type="submit"
                                                    class="service-agent-payment-delete"
                                                    name="payment_qr_delete"
                                                    value="<?php echo e((string) $paymentRow['type']); ?>:<?php echo e((string) $paymentImageIndex); ?>"
                                                    data-service-agent-payment-delete-confirm="确认删除这张收款二维码吗？"
                                                    aria-label="删除<?php echo e((string) $paymentRow['label']); ?>二维码<?php echo e((string) ($paymentImageIndex + 1)); ?>"
                                                >
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="service-agent-payment-empty">未上传</div>
                                    <?php endif; ?>
                                </div>
                                <div class="service-agent-payment-upload-row">
                                    <span class="service-agent-payment-upload-label">选择图片</span>
                                    <label class="service-agent-payment-upload">
                                        <input type="file" name="<?php echo e((string) $paymentRow['field']); ?>" accept="image/*,.jpg,.jpeg,.png,.gif,.webp,.bmp,image/jpeg,image/png,image/gif,image/webp,image/bmp,image/x-ms-bmp">
                                    </label>
                                    <button type="submit" class="service-agent-payment-submit is-<?php echo e((string) $paymentRow['type']); ?>">
                                        <i class="fa-solid fa-upload"></i>
                                        <span>上传图片</span>
                                    </button>
                                </div>
                                <?php if ((string) $paymentRow['type'] === 'usdt'): ?>
                                    <div class="service-agent-usdt-address">
                                        <div class="service-agent-usdt-address-head">
                                            <span class="service-agent-usdt-address-title">USDT地址</span>
                                            <button type="submit" class="service-agent-usdt-save" name="save_usdt_address" value="1">
                                                <i class="fa-solid fa-floppy-disk"></i>
                                                <span>保存地址</span>
                                            </button>
                                        </div>
                                        <input type="hidden" name="payment_save_target" value="usdt_address">
                                        <input type="text" name="usdt_address" value="<?php echo e($customerServiceUsdtAddress); ?>" maxlength="255" placeholder="请输入 USDT 收款地址">
                                    </div>
                                <?php endif; ?>
                            </form>
                        <?php endforeach; ?>
                    </div>
                </div>
            </main>
            <div class="service-agent-payment-preview-modal" data-service-agent-payment-preview-modal hidden>
                <button type="button" class="service-agent-payment-preview-backdrop" data-service-agent-payment-preview-close aria-label="关闭二维码预览"></button>
                <section class="service-agent-payment-preview-dialog" role="dialog" aria-modal="true" aria-label="二维码预览">
                    <button type="button" class="service-agent-payment-preview-close" data-service-agent-payment-preview-close aria-label="关闭二维码预览">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                    <img src="" alt="" decoding="async" fetchpriority="low" data-service-agent-payment-preview-image>
                    <strong data-service-agent-payment-preview-title></strong>
                </section>
            </div>
                </section>
            </div>
        </div>
    </section>
    <?php if (!$customerServiceEmbed): ?>
        <?php echo \App\Core\View::make(app(), 'partials/front_bottom_nav', array('region' => $region, 'activePanel' => 'service_manage', 'user' => $user, 'customerServiceAgentNav' => true)); ?>
    <?php endif; ?>
<?php elseif ($customerServiceMode === 'agent'): ?>
    <?php if (!$customerServiceEmbed): ?>
        <?php echo \App\Core\View::make(app(), 'partials/front_top_bar', array('region' => $region)); ?>
    <?php endif; ?>
    <section class="front-page-shell front-unified-page customer-service-page customer-service-agent-page">
        <div class="data-frame front-panel-stack front-unified-frame customer-service-frame customer-service-agent-frame">
            <div class="customer-service-agent-shell front-unified-content">
                <section
                    class="customer-service-agent-console is-<?php echo e($customerServiceAgentView); ?>-view"
                    data-customer-service
                    data-customer-service-role="agent"
                    data-agent-active-view="<?php echo e($customerServiceAgentView); ?>"
                    data-api-url="<?php echo e(public_url('api.php')); ?>"
                    data-token="<?php echo e(csrf_token('api')); ?>"
                    data-session-id="<?php echo e((string) $customerServiceSessionId); ?>"
                    data-has-session="<?php echo $customerServiceSession ? '1' : '0'; ?>"
                    data-status="<?php echo e($customerServiceStatus); ?>"
                    data-session-base-url="<?php echo e($customerServiceAgentBaseUrl); ?>"
                    data-service-agent-chat-unread-count="<?php echo e((string) $customerServiceAgentChatUnread); ?>"
                    data-send-action="customer_service.agent.send"
                    data-poll-action="customer_service.agent.poll"
                    data-typing-action="customer_service.typing"
                    data-clear-action="customer_service.agent.clear"
                    data-delete-action="customer_service.agent.queue_delete"
                    data-block-action="customer_service.agent.block"
                    data-unblock-action="customer_service.agent.unblock"
                    data-presence-action="customer_service.agent.presence"
                    data-settings-action="customer_service.agent.settings"
                    data-nickname-delete-action="customer_service.agent.nickname_delete"
                    data-agent-online="<?php echo $customerServiceAgentOnline ? '1' : '0'; ?>"
                    data-enabled="1"
                    data-empty-text="切换到会话列表选择会员后即可查看消息。"
                    data-score-action="customer_service.agent.score"
                    data-service-agent-score-account="<?php echo e($customerServiceSession ? $customerServiceActiveName : ''); ?>"
                    data-service-agent-score-current="<?php echo e((string) $customerServiceActiveScore); ?>"
                >
        <header class="service-agent-top">
            <div class="service-agent-top-main">
                <h1 class="service-agent-title-name" data-service-agent-title><?php echo e($customerServiceAgentName); ?></h1>
                <button
                    type="button"
                    class="service-agent-presence-pill"
                    data-service-agent-presence-label
                    data-service-agent-presence-toggle
                    data-status-type="<?php echo e($customerServiceAgentOnlineType); ?>"
                    data-next-online="<?php echo $customerServiceAgentOnline ? '0' : '1'; ?>"
                    aria-pressed="<?php echo $customerServiceAgentOnline ? 'true' : 'false'; ?>"
                ><?php echo e($customerServiceAgentOnlineLabel); ?></button>
                <button
                    type="button"
                    class="service-agent-settings-toggle"
                    data-service-agent-settings-open
                    aria-haspopup="dialog"
                    aria-controls="service-agent-settings-modal"
                ><i class="fa-solid fa-gear"></i> 设置</button>
            </div>
            <div class="service-agent-view-switch" role="tablist" aria-label="客服接待视图切换">
                <button
                    type="button"
                    role="tab"
                    class="<?php echo $customerServiceAgentView === 'queue' ? 'is-active' : ''; ?>"
                    aria-controls="service-agent-queue"
                    aria-selected="<?php echo $customerServiceAgentView === 'queue' ? 'true' : 'false'; ?>"
                    data-service-agent-view-target="queue"
                >
                    <span>会话列表</span>
                    <em class="service-agent-view-badge" data-service-agent-queue-unread <?php echo $customerServiceAgentQueueUnread > 0 ? '' : 'hidden'; ?>><?php echo e($customerServiceAgentQueueUnread > 99 ? '99+' : (string) $customerServiceAgentQueueUnread); ?></em>
                </button>
                <button
                    type="button"
                    role="tab"
                    class="<?php echo $customerServiceAgentView === 'chat' ? 'is-active' : ''; ?>"
                    aria-controls="service-agent-chat"
                    aria-selected="<?php echo $customerServiceAgentView === 'chat' ? 'true' : 'false'; ?>"
                    data-service-agent-view-target="chat"
                    <?php echo $customerServiceSession ? '' : 'disabled'; ?>
                >
                    <span>会话窗口</span>
                    <strong class="service-agent-active-account-pill" data-service-agent-active-label><?php echo e($customerServiceSession ? $customerServiceActiveName : '未选择'); ?></strong>
                    <em class="service-agent-view-badge" data-service-agent-chat-unread <?php echo $customerServiceAgentChatUnread > 0 ? '' : 'hidden'; ?>><?php echo e($customerServiceAgentChatUnread > 99 ? '99+' : (string) $customerServiceAgentChatUnread); ?></em>
                </button>
            </div>
        </header>

        <div class="service-agent-settings-modal" id="service-agent-settings-modal" hidden data-service-agent-settings-modal role="dialog" aria-modal="true" aria-labelledby="service-agent-settings-title">
            <button class="service-agent-settings-backdrop" type="button" data-service-agent-settings-close aria-label="关闭设置弹窗"></button>
            <section class="service-agent-settings-card">
                <div class="service-agent-settings-head">
                    <div>
                        <h2 id="service-agent-settings-title">客服设置</h2>
                    </div>
                    <button type="button" data-service-agent-settings-close aria-label="关闭设置"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <form class="service-agent-settings-form" method="post" action="<?php echo e(public_url('api.php')); ?>" data-service-agent-settings-form>
                    <input type="hidden" name="action" value="customer_service.agent.settings">
                    <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                    <input type="hidden" name="session_id" value="<?php echo e((string) $customerServiceSessionId); ?>">
                    <input type="hidden" name="status" value="<?php echo e($customerServiceStatus); ?>">
                    <div class="service-agent-settings-duo">
                        <div class="service-agent-settings-field is-nickname">
                            <label for="service-agent-display-name">昵称选择</label>
                            <div class="service-agent-nickname-picker" data-service-agent-nickname-picker>
                                <input id="service-agent-display-name" name="display_name" maxlength="80" value="<?php echo e($customerServiceAgentName); ?>" placeholder="例如：值班客服" autocomplete="off" data-service-agent-nickname-input>
                                <button type="button" data-service-agent-nickname-toggle aria-haspopup="listbox" aria-expanded="false" aria-label="展开昵称选择">
                                    <i class="fa-solid fa-caret-down" aria-hidden="true"></i>
                                </button>
                                <div class="service-agent-nickname-menu" data-service-agent-nickname-menu role="listbox" hidden>
                                    <?php foreach ($customerServiceAgentNicknameOptions as $nicknameOption): ?>
                                        <div class="service-agent-nickname-row<?php echo $nicknameOption === $customerServiceAgentName ? ' is-active' : ''; ?>" role="option" data-service-agent-nickname-row="<?php echo e($nicknameOption); ?>" aria-selected="<?php echo $nicknameOption === $customerServiceAgentName ? 'true' : 'false'; ?>">
                                            <button type="button" data-service-agent-nickname-option="<?php echo e($nicknameOption); ?>"><?php echo e($nicknameOption); ?></button>
                                            <button type="button" data-service-agent-nickname-delete="<?php echo e($nicknameOption); ?>" aria-label="删除昵称 <?php echo e($nicknameOption); ?>">x</button>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="service-agent-settings-field is-hours">
                            <label for="service-agent-service-hours">接待时间</label>
                            <input id="service-agent-service-hours" name="service_hours" maxlength="80" value="<?php echo e($customerServiceAgentServiceHours); ?>" placeholder="例如：09:00-23:00">
                        </div>
                    </div>
                    <div class="service-agent-settings-notice is-wide">
                        <div class="service-agent-settings-notice-head">
                            <span>活动公告</span>
                            <label class="service-agent-settings-switch">
                                <input type="hidden" name="activity_notice_enabled" value="0">
                                <input type="checkbox" name="activity_notice_enabled" value="1" <?php echo $customerServiceAgentActivityNoticeEnabled ? 'checked' : ''; ?>>
                                <span aria-hidden="true"></span>
                                <em>启动活动</em>
                            </label>
                        </div>
                        <textarea name="activity_notice" rows="4" maxlength="2000" placeholder="填写后会显示在会员会话窗口顶部，留空则不展示"><?php echo e($customerServiceAgentActivityNotice); ?></textarea>
                    </div>
                    <label class="is-wide">
                        <span>欢迎语</span>
                        <textarea name="welcome_text" rows="3" maxlength="255" placeholder="会员打开在线客服时展示的欢迎语"><?php echo e($customerServiceAgentWelcomeText); ?></textarea>
                    </label>
                    <label class="is-wide">
                        <span>自动回复语</span>
                        <textarea name="auto_reply_text" rows="4" maxlength="1000" placeholder="会员发送消息后自动回复，留空则不启用"><?php echo e($customerServiceAgentAutoReplyText); ?></textarea>
                    </label>
                    <div class="service-agent-settings-guide is-wide">
                        <div class="service-agent-settings-guide-head">论坛指南</div>
                        <label>
                            <span>邀请好友规则</span>
                            <textarea name="forum_guide_invite_rule" rows="2" maxlength="800" placeholder="会员中心论坛指南展示的邀请好友说明"><?php echo e((string) ($customerServiceForumGuideRules['invite_rule'] ?? '')); ?></textarea>
                        </label>
                        <label>
                            <span>充值规则</span>
                            <textarea name="forum_guide_recharge_rule" rows="2" maxlength="800" placeholder="会员中心论坛指南展示的充值说明"><?php echo e((string) ($customerServiceForumGuideRules['recharge_rule'] ?? '')); ?></textarea>
                        </label>
                        <label>
                            <span>购买规则</span>
                            <textarea name="forum_guide_purchase_rule" rows="3" maxlength="800" placeholder="会员中心论坛指南展示的购买说明"><?php echo e((string) ($customerServiceForumGuideRules['purchase_rule'] ?? '')); ?></textarea>
                        </label>
                        <label>
                            <span>遵守规则</span>
                            <textarea name="forum_guide_conduct_rule" rows="3" maxlength="800" placeholder="会员中心论坛指南展示的发帖、评论和处罚说明"><?php echo e((string) ($customerServiceForumGuideRules['conduct_rule'] ?? '')); ?></textarea>
                        </label>
                    </div>
                    <div class="service-agent-settings-actions">
                        <button type="submit">保存设置</button>
                    </div>
                </form>
            </section>
        </div>

        <div class="service-agent-grid">
            <aside id="service-agent-queue" class="service-agent-queue service-agent-pane" aria-label="会员会话列表" data-service-agent-panel="queue">
                <div class="service-agent-panel-head">
                    <div>
                        <h2 class="service-agent-queue-title">会话列表</h2>
                        <strong data-service-agent-queue-count><?php echo e((string) count($customerServiceSessions)); ?>人</strong>
                    </div>
                    <div class="service-agent-queue-actions">
                        <label class="service-agent-queue-select" title="全选会话">
                            <input type="checkbox" data-service-agent-select-all <?php echo $customerServiceSessions ? '' : 'disabled'; ?>>
                            <span>全选</span>
                        </label>
                        <button
                            class="service-agent-queue-delete is-disabled"
                            type="button"
                            data-service-agent-batch-delete
                            aria-label="删除已选会话"
                            title="删除已选会话"
                            aria-disabled="true"
                        >
                            <?php echo $customerServiceIconSvg('trash-can'); ?>
                        </button>
                    </div>
                </div>
                <div class="service-agent-session-list" data-customer-service-session-list>
                    <?php if ($customerServiceSessions): ?>
                        <?php foreach ($customerServiceSessions as $session): ?>
                            <?php
                            $sessionId = (int) ($session['id'] ?? 0);
                            $isActive = $sessionId === $customerServiceSessionId;
                            $unreadCount = (int) ($session['unread_for_admin'] ?? 0);
                            $isBlocked = !empty($session['blocked']);
                            $sessionPreview = (string) (($session['last_message_preview'] ?? '') ?: '暂无消息');
                            if (strpos($sessionPreview, '您已被系统屏蔽，解除时间:') === 0) {
                                $sessionPreview = '对方已被屏蔽，解除时间:' . substr($sessionPreview, strlen('您已被系统屏蔽，解除时间:'));
                            } elseif (strpos($sessionPreview, '您已被系统屏蔽，解除时间：') === 0) {
                                $sessionPreview = '对方已被屏蔽，解除时间:' . substr($sessionPreview, strlen('您已被系统屏蔽，解除时间：'));
                            }
                            ?>
                            <div
                                class="customer-service-session-item service-agent-session<?php echo $isActive ? ' is-active' : ''; ?>"
                                role="link"
                                tabindex="0"
                                data-service-agent-session-card
                                data-session-href="<?php echo e($customerServiceAgentBaseUrl . '&' . http_build_query(array('status' => $customerServiceStatus, 'session_id' => $sessionId, 'agent_view' => 'chat'))); ?>"
                                data-customer-service-session-id="<?php echo e((string) $sessionId); ?>"
                            >
                                <span class="customer-service-session-main service-agent-session-main">
                                    <span class="service-agent-session-top">
                                        <span class="service-agent-session-meta">
                                            <label class="service-agent-session-check" title="选择会话">
                                                <input type="checkbox" data-service-agent-session-select value="<?php echo e((string) $sessionId); ?>" aria-label="选择<?php echo e((string) (($session['username'] ?? '') ?: '会员')); ?>">
                                                <span></span>
                                            </label>
                                            <strong><?php echo e((string) (($session['username'] ?? '') ?: '会员')); ?></strong>
                                            <span class="customer-service-session-presence" data-status-type="<?php echo e((string) ($session['member_online_type'] ?? 'offline')); ?>"><?php echo e((string) ($session['member_online_label'] ?? '离线')); ?></span>
                                        </span>
                                        <span class="customer-service-session-side service-agent-session-side">
                                            <small class="service-agent-session-time"><?php echo e((string) ($session['last_message_at'] ?? '')); ?></small>
                                            <em class="service-agent-session-unread" aria-label="未读信息<?php echo e($unreadCount > 99 ? '99+' : (string) $unreadCount); ?>条" <?php echo $unreadCount > 0 ? '' : 'hidden'; ?>><?php echo e($unreadCount > 99 ? '99+' : (string) $unreadCount); ?></em>
                                        </span>
                                    </span>
                                    <span class="service-agent-session-preview-row">
                                        <span class="service-agent-session-preview"><?php echo e($sessionPreview); ?></span>
                                        <button
                                            class="service-agent-session-delete"
                                            type="button"
                                            data-service-agent-session-delete
                                            data-session-id="<?php echo e((string) $sessionId); ?>"
                                            aria-label="删除<?php echo e((string) (($session['username'] ?? '') ?: '会员')); ?>会话"
                                            title="删除会话"
                                        >
                                            <?php echo $customerServiceIconSvg('trash-can'); ?>
                                        </button>
                                    </span>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="customer-service-empty">当前没有需要接待的会话。</div>
                    <?php endif; ?>
                </div>
            </aside>

            <main id="service-agent-chat" class="service-thread service-thread--agent" aria-label="客服接待区" data-service-agent-panel="chat" data-service-thread>
                <div class="service-thread-head">
                    <div class="service-thread-peer">
                        <span class="service-thread-avatar" data-customer-service-active-avatar aria-label="会员头像"><?php echo $customerServiceIconSvg('circle-user'); ?></span>
                        <div class="service-thread-peer-copy">
                            <div class="service-thread-title" data-customer-service-active-name><?php echo e($customerServiceSession ? (string) (($customerServiceSession['username'] ?? '') ?: '会员') : '未选择会话'); ?></div>
                            <div class="service-thread-status" data-customer-service-active-online data-status-type="<?php echo e((string) ($customerServiceSession['member_online_type'] ?? 'offline')); ?>"><?php echo e((string) ($customerServiceSession['member_online_label'] ?? '离线')); ?></div>
                        </div>
                    </div>
                    <div class="service-thread-actions service-thread-actions--agent">
                        <?php $customerServiceActiveBlocked = $customerServiceSession && !empty($customerServiceSession['blocked']); ?>
                        <?php if ($customerServiceSession): ?>
                            <button
                                class="service-thread-action service-thread-action--score"
                                type="button"
                                data-service-agent-score-open
                                aria-label="积分充值"
                                title="积分充值"
                            >
                                <?php echo $customerServiceIconSvg('coins'); ?>
                                <span>积分充值</span>
                            </button>
                        <?php endif; ?>
                        <span class="service-thread-block-controls" data-service-agent-chat-block-controls <?php echo $customerServiceSession ? '' : 'hidden'; ?>>
                            <select
                                data-service-agent-block-limit
                                data-session-id="<?php echo e((string) $customerServiceSessionId); ?>"
                                data-blocked="<?php echo $customerServiceActiveBlocked ? '1' : '0'; ?>"
                                aria-label="屏蔽会话"
                                <?php echo $customerServiceSession ? '' : 'disabled'; ?>
                            >
                                <option value="" data-service-agent-block-placeholder selected disabled><?php echo e($customerServiceActiveBlockLabel); ?></option>
                                <option value="permanent" data-service-agent-block-mode="block" <?php echo $customerServiceActiveBlocked ? 'hidden disabled' : ''; ?>>永久屏蔽</option>
                                <option value="1h" data-service-agent-block-mode="block" <?php echo $customerServiceActiveBlocked ? 'hidden disabled' : ''; ?>>屏蔽1小时</option>
                                <option value="24h" data-service-agent-block-mode="block" <?php echo $customerServiceActiveBlocked ? 'hidden disabled' : ''; ?>>屏蔽24小时</option>
                                <option value="7d" data-service-agent-block-mode="block" <?php echo $customerServiceActiveBlocked ? 'hidden disabled' : ''; ?>>屏蔽7天</option>
                                <option value="30d" data-service-agent-block-mode="block" <?php echo $customerServiceActiveBlocked ? 'hidden disabled' : ''; ?>>屏蔽30天</option>
                                <option value="unblock" data-service-agent-block-mode="unblock" <?php echo $customerServiceActiveBlocked ? '' : 'hidden disabled'; ?>>解除屏蔽</option>
                            </select>
                        </span>
                        <?php if ($customerServiceSession && $customerServiceCanClear): ?>
                            <button
                                class="service-thread-action service-thread-action--danger"
                                type="button"
                                data-customer-service-clear
                                aria-label="清除聊天记录"
                                title="清除聊天记录"
                            >
                                <?php echo $customerServiceIconSvg('trash-can'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="service-thread-log" data-customer-service-log>
                    <?php if ($customerServiceSession && $customerServiceMessages): ?>
                        <?php foreach ($customerServiceMessages as $message): ?>
                            <?php
                            $messageDate = (string) ($message['created_date'] ?? '');
                            $messageType = (string) ($message['message_type'] ?? 'text');
                            $messageContent = (string) ($message['content'] ?? '');
                            $messageSenderType = (string) ($message['sender_type'] ?? '');
                            $messageSenderName = (string) ($message['sender_name'] ?? '');
                            $isSystem = $messageSenderType === 'system'
                                || ($messageSenderName === '系统'
                                    && (strpos($messageContent, '您已被系统屏蔽，解除时间') === 0
                                        || $messageContent === '您已被系统永久屏蔽，暂时无法继续发送在线客服消息。'));
                            $agentSystemDisplayContent = $messageContent;
                            if (strpos($agentSystemDisplayContent, '您已被系统屏蔽，解除时间:') === 0) {
                                $agentSystemDisplayContent = '对方已被屏蔽，解除时间:' . substr($agentSystemDisplayContent, strlen('您已被系统屏蔽，解除时间:'));
                            } elseif (strpos($agentSystemDisplayContent, '您已被系统屏蔽，解除时间：') === 0) {
                                $agentSystemDisplayContent = '对方已被屏蔽，解除时间:' . substr($agentSystemDisplayContent, strlen('您已被系统屏蔽，解除时间：'));
                            } elseif (preg_match('/^您的邀请好友\s*(?:「([^」]+)」|【([^】]+)】|\[([^\]]+)\]|(.+?))\s*已注册成功，邀请奖励\s*\+([0-9]+)\s*积分已到账。$/u', $agentSystemDisplayContent, $inviteRewardNoticeMatch)) {
                                $inviteRewardNoticeName = trim((string) ($inviteRewardNoticeMatch[1] ?: ($inviteRewardNoticeMatch[2] ?: ($inviteRewardNoticeMatch[3] ?: $inviteRewardNoticeMatch[4]))));
                                $agentSystemDisplayContent = '该会员邀请的好友「' . $inviteRewardNoticeName . '」已注册成功，已向该会员发放邀请奖励 +' . $inviteRewardNoticeMatch[5] . ' 积分。';
                            }
                            $isSelf = (string) ($message['sender_type'] ?? '') === 'agent';
                            ?>
                            <?php if ($messageDate !== '' && $messageDate !== $customerServiceLastDate): ?>
                                <div class="service-thread-date"><?php echo e($messageDate); ?></div>
                                <?php $customerServiceLastDate = $messageDate; ?>
                            <?php endif; ?>
                            <?php if ($isSystem): ?>
                                <div class="service-thread-system" data-customer-service-message-id="<?php echo e((string) ($message['id'] ?? 0)); ?>">
                                    <div class="service-thread-system-pill"><?php echo nl2br(e($agentSystemDisplayContent)); ?></div>
                                </div>
                            <?php else: ?>
                            <div class="service-thread-message <?php echo $isSelf ? 'is-self' : 'is-peer'; ?>" data-customer-service-message-id="<?php echo e((string) ($message['id'] ?? 0)); ?>">
                                <div class="service-thread-message-wrap">
                                    <div class="service-thread-meta">
                                        <span><?php echo e((string) ($message['sender_name'] ?? ($isSelf ? '客服' : '会员'))); ?></span>
                                        <span><?php echo e((string) ($message['created_time'] ?? '')); ?></span>
                                    </div>
                                    <div class="service-thread-bubble is-<?php echo e($messageType); ?>">
                                        <?php if ($messageType === 'image'): ?>
                                            <button
                                                type="button"
                                                class="service-thread-image-open"
                                                data-customer-service-image-preview-open="<?php echo e((string) ($message['attachment_url'] ?? '')); ?>"
                                                data-customer-service-image-preview-title="聊天图片"
                                                aria-label="预览聊天图片"
                                            >
                                                <img
                                                    src="<?php echo e((string) ($message['attachment_url'] ?? '')); ?>"
                                                    alt="聊天图片"
                                                    width="240"
                                                    height="180"
                                                    loading="lazy"
                                                    decoding="async"
                                                    fetchpriority="low"
                                                >
                                            </button>
                                        <?php elseif ($messageType === 'voice'): ?>
                                            <div class="service-thread-voice">
                                                <?php echo $customerServiceIconSvg('volume-high'); ?>
                                                <span><?php echo max(1, (int) ($message['voice_duration'] ?? 0)); ?> 秒语音</span>
                                            </div>
                                            <audio controls controlsList="nodownload noplaybackrate" disablepictureinpicture preload="none" src="<?php echo e((string) ($message['attachment_url'] ?? '')); ?>"></audio>
                                        <?php else: ?>
                                            <?php echo nl2br(e((string) ($message['content'] ?? ''))); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php elseif ($customerServiceSession): ?>
                        <div class="service-thread-empty" data-customer-service-empty>当前会话暂无消息。</div>
                    <?php else: ?>
                        <div class="service-thread-empty" data-customer-service-empty>请先切换到会话列表选择会员。</div>
                    <?php endif; ?>
                </div>

                <?php if ($customerServiceSession): ?>
                    <form class="service-thread-composer service-thread-composer--agent" method="post" action="<?php echo e(public_url('api.php')); ?>" enctype="multipart/form-data" data-customer-service-form <?php echo $customerServiceCanReply ? '' : 'hidden'; ?>>
                        <input type="hidden" name="action" value="customer_service.agent.send">
                        <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                        <input type="hidden" name="session_id" value="<?php echo e((string) $customerServiceSessionId); ?>">
                        <input type="hidden" name="status" value="<?php echo e($customerServiceStatus); ?>">
                        <input type="hidden" name="message_type" value="text" data-customer-service-message-type>
                        <input class="sr-only" type="file" name="attachment" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp" data-customer-service-image>
                        <div class="service-thread-pending" hidden data-customer-service-pending></div>
                        <div class="service-thread-tools">
                            <button class="service-thread-tool" type="button" data-customer-service-voice aria-label="发送语音" title="发送语音"><?php echo $customerServiceIconSvg('microphone'); ?></button>
                            <button class="service-thread-tool" type="button" data-customer-service-image-trigger aria-label="发送图片" title="发送图片"><?php echo $customerServiceIconSvg('image'); ?></button>
                            <button class="service-thread-tool" type="button" data-customer-service-emoji-toggle aria-label="发送表情" title="发送表情" aria-haspopup="dialog" aria-expanded="false"><?php echo $customerServiceIconSvg('face-smile'); ?></button>
                        </div>
                        <textarea class="service-thread-input" name="content" rows="1" maxlength="1000" placeholder="输入回复内容..." autocomplete="off" data-customer-service-input></textarea>
                        <button class="service-thread-send" type="submit" aria-label="发送消息"><?php echo $customerServiceIconSvg('paper-plane'); ?></button>
                        <div class="service-thread-emoji-panel" hidden data-customer-service-emoji-panel>
                            <?php foreach ($customerServiceEmojis as $emoji): ?>
                                <button type="button" data-customer-service-emoji="<?php echo e((string) $emoji); ?>"><?php echo e((string) $emoji); ?></button>
                            <?php endforeach; ?>
                        </div>
                    </form>
                    <div class="service-thread-locked" data-service-agent-locked <?php echo $customerServiceCanReply ? 'hidden' : ''; ?>><?php echo $customerServiceAgentOnline ? '当前账号无回复权限，或该会话不可操作。' : '当前休息状态...'; ?></div>
                <?php else: ?>
                    <div class="service-thread-locked" data-service-agent-locked>请先在会话列表选择会员。</div>
                <?php endif; ?>
            </main>
        </div>
        <div
            class="service-agent-score-modal"
            id="service-agent-score-modal"
            hidden
            data-service-agent-score-modal
            role="dialog"
            aria-modal="true"
            aria-labelledby="service-agent-score-title"
        >
            <button class="service-agent-score-backdrop" type="button" data-service-agent-score-close aria-label="关闭积分充值弹窗"></button>
            <section class="service-agent-score-card">
                <div class="service-agent-score-head">
                    <h2 id="service-agent-score-title">积分充值</h2>
                    <button type="button" data-service-agent-score-close aria-label="关闭积分充值">
                        <?php echo $customerServiceIconSvg('xmark'); ?>
                    </button>
                </div>
                <form class="service-agent-score-form" data-service-agent-score-form>
                    <div class="service-agent-score-summary">
                        <div class="service-agent-score-account-row">
                            <span class="service-agent-score-avatar" data-service-agent-score-avatar aria-label="会员头像"><?php echo $customerServiceIconSvg('circle-user'); ?></span>
                            <span>账号</span>
                            <strong data-service-agent-score-account><?php echo e($customerServiceSession ? $customerServiceActiveName : '未选择'); ?></strong>
                        </div>
                        <div class="service-agent-score-current-row">
                            <span>积分</span>
                            <strong data-service-agent-score-current><?php echo e((string) $customerServiceActiveScore); ?></strong>
                        </div>
                    </div>
                    <div class="service-agent-score-actions">
                        <label class="service-agent-score-field">
                            <span>充值金额</span>
                            <input
                                type="number"
                                min="-100000000"
                                max="100000000"
                                step="1"
                                inputmode="numeric"
                                placeholder="请输入充值积分，扣减请用负数"
                                data-service-agent-score-amount
                                required
                            >
                        </label>
                        <button type="submit" data-service-agent-score-submit>确认充值</button>
                    </div>
                </form>
            </section>
        </div>
                </section>
            </div>
        </div>
    </section>
    <?php if (!$customerServiceEmbed): ?>
        <?php echo \App\Core\View::make(app(), 'partials/front_bottom_nav', array('region' => $region, 'activePanel' => 'service', 'user' => $user, 'customerServiceAgentNav' => true)); ?>
    <?php endif; ?>
<?php elseif ($customerServiceMode === 'agent_login'): ?>
    <section class="front-page-shell front-unified-page customer-service-page customer-service-agent-login-page">
        <div class="data-frame front-panel-stack front-unified-frame customer-service-frame customer-service-agent-login-frame">
            <div class="customer-service-agent-login">
                <div class="service-login-card">
                    <span>客服前台登录</span>
                    <h1>在线客服接待</h1>
                    <p>客服账号由超级管理员在后台创建，只能登录前台接待页，不能进入后台管理。</p>
                    <form method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form>
                        <input type="hidden" name="action" value="customer_service.agent.login">
                        <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                        <input type="hidden" name="region" value="<?php echo e($region); ?>">
                        <input class="service-login-field" name="username" maxlength="32" autocomplete="username" required placeholder="客服账号">
                        <input class="service-login-field" type="password" name="password" maxlength="64" autocomplete="current-password" required placeholder="登录密码">
                        <button type="submit">进入接待台</button>
                    </form>
                    <a href="<?php echo e(public_url('service.php') . '?region=' . urlencode($region)); ?>">返回会员客服页</a>
                </div>
            </div>
        </div>
    </section>
<?php else: ?>
    <section
        class="front-page-shell front-unified-page customer-service-page"
        data-customer-service
        data-customer-service-role="member"
        data-api-url="<?php echo e(public_url('api.php')); ?>"
        data-token="<?php echo e(csrf_token('api')); ?>"
        data-session-id="<?php echo e((string) $customerServiceSessionId); ?>"
        data-send-action="customer_service.member.send"
        data-poll-action="<?php echo $user ? 'customer_service.member.poll' : 'customer_service.public.status'; ?>"
        data-typing-action="customer_service.typing"
        data-clear-action="customer_service.member.clear"
        data-enabled="1"
        data-empty-text="<?php echo e($customerServiceMemberWelcomeText); ?>"
    >
        <div class="data-frame front-panel-stack front-unified-frame customer-service-frame">
            <div class="service-thread service-thread--member" data-service-thread>
            <div class="service-thread-head">
                <div class="service-thread-peer">
                    <div class="service-thread-avatar" data-customer-service-avatar>
                        <span class="service-thread-avatar-icon"><?php echo $customerServiceIconSvg('circle-user'); ?></span>
                        <span class="service-thread-avatar-state" data-customer-service-avatar-status data-status-type="<?php echo e($customerServiceAvatarStatusType); ?>"><?php echo e($customerServiceAvatarStatusText); ?></span>
                    </div>
                    <div class="service-thread-peer-copy">
                        <div class="service-thread-title"><?php echo e($customerServiceMemberTitle); ?></div>
                        <div class="service-thread-hours">接待时间：<?php echo e($customerServiceMemberHours); ?></div>
                    </div>
                </div>
                <?php if ($user): ?>
                    <button class="service-thread-action service-thread-action--danger" type="button" data-customer-service-clear aria-label="删除聊天记录" title="删除聊天记录">
                        <?php echo $customerServiceIconSvg('trash-can'); ?>
                    </button>
                <?php endif; ?>
            </div>

            <div class="service-thread-notice" data-customer-service-activity-notice <?php echo $customerServiceMemberActivityNoticeEnabled && $customerServiceMemberActivityNotice !== '' ? '' : 'hidden'; ?>>
                <strong>活動公告</strong>
                <span><em data-customer-service-activity-notice-text><?php echo e($customerServiceMemberActivityNotice); ?></em></span>
            </div>
            <?php if ($user): ?>
                <div class="service-thread-log" data-customer-service-log>
                    <?php if ($customerServiceMessages): ?>
                        <?php foreach ($customerServiceMessages as $message): ?>
                            <?php
                            $messageDate = (string) ($message['created_date'] ?? '');
                            $messageType = (string) ($message['message_type'] ?? 'text');
                            $messageContent = (string) ($message['content'] ?? '');
                            $messageSenderType = (string) ($message['sender_type'] ?? '');
                            $messageSenderName = (string) ($message['sender_name'] ?? '');
                            $isSystem = $messageSenderType === 'system'
                                || ($messageSenderName === '系统'
                                    && (strpos($messageContent, '您已被系统屏蔽，解除时间') === 0
                                        || $messageContent === '您已被系统永久屏蔽，暂时无法继续发送在线客服消息。'));
                            $isSelf = (string) ($message['sender_type'] ?? '') === 'member';
                            ?>
                            <?php if ($messageDate !== '' && $messageDate !== $customerServiceLastDate): ?>
                                <div class="service-thread-date"><?php echo e($messageDate); ?></div>
                                <?php $customerServiceLastDate = $messageDate; ?>
                            <?php endif; ?>
                            <?php if ($isSystem): ?>
                                <div class="service-thread-system" data-customer-service-message-id="<?php echo e((string) ($message['id'] ?? 0)); ?>">
                                    <div class="service-thread-system-pill"><?php echo nl2br(e((string) ($message['content'] ?? ''))); ?></div>
                                </div>
                            <?php else: ?>
                            <div class="service-thread-message <?php echo $isSelf ? 'is-self' : 'is-peer'; ?>" data-customer-service-message-id="<?php echo e((string) ($message['id'] ?? 0)); ?>">
                                <div class="service-thread-message-wrap">
                                    <div class="service-thread-meta">
                                        <span><?php echo e((string) ($message['sender_name'] ?? ($isSelf ? '我' : '客服'))); ?></span>
                                        <span><?php echo e((string) ($message['created_time'] ?? '')); ?></span>
                                    </div>
                                    <div class="service-thread-bubble is-<?php echo e($messageType); ?>">
                                        <?php if ($messageType === 'image'): ?>
                                            <button
                                                type="button"
                                                class="service-thread-image-open"
                                                data-customer-service-image-preview-open="<?php echo e((string) ($message['attachment_url'] ?? '')); ?>"
                                                data-customer-service-image-preview-title="聊天图片"
                                                aria-label="预览聊天图片"
                                            >
                                                <img
                                                    src="<?php echo e((string) ($message['attachment_url'] ?? '')); ?>"
                                                    alt="聊天图片"
                                                    width="240"
                                                    height="180"
                                                    loading="lazy"
                                                    decoding="async"
                                                    fetchpriority="low"
                                                >
                                            </button>
                                        <?php elseif ($messageType === 'voice'): ?>
                                            <div class="service-thread-voice">
                                                <?php echo $customerServiceIconSvg('volume-high'); ?>
                                                <span><?php echo max(1, (int) ($message['voice_duration'] ?? 0)); ?> 秒</span>
                                            </div>
                                            <audio controls controlsList="nodownload noplaybackrate" disablepictureinpicture preload="none" src="<?php echo e((string) ($message['attachment_url'] ?? '')); ?>"></audio>
                                        <?php else: ?>
                                            <?php echo nl2br(e((string) ($message['content'] ?? ''))); ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="service-thread-empty" data-customer-service-empty><?php echo e($customerServiceMemberWelcomeText); ?></div>
                    <?php endif; ?>
                </div>

                <form class="service-thread-composer service-thread-composer--member" method="post" action="<?php echo e(public_url('api.php')); ?>" enctype="multipart/form-data" data-customer-service-form>
                    <input type="hidden" name="action" value="customer_service.member.send">
                    <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                    <input type="hidden" name="message_type" value="text" data-customer-service-message-type>
                    <input class="sr-only" type="file" name="attachment" accept="image/jpeg,image/png,image/gif,image/webp,image/bmp" data-customer-service-image>
                    <div class="service-thread-pending" hidden data-customer-service-pending></div>
                    <div class="service-thread-tools">
                        <button class="service-thread-tool" type="button" data-customer-service-voice aria-label="发送语音" title="发送语音"><?php echo $customerServiceIconSvg('microphone'); ?></button>
                        <button class="service-thread-tool" type="button" data-customer-service-image-trigger aria-label="发送图片" title="发送图片"><?php echo $customerServiceIconSvg('image'); ?></button>
                        <button class="service-thread-tool" type="button" data-customer-service-emoji-toggle aria-label="发送表情" title="发送表情" aria-haspopup="dialog" aria-expanded="false"><?php echo $customerServiceIconSvg('face-smile'); ?></button>
                    </div>
                    <textarea class="service-thread-input" name="content" rows="1" maxlength="1000" placeholder="输入消息..." autocomplete="off" data-customer-service-input></textarea>
                    <button class="service-thread-send" type="submit" aria-label="发送消息"><?php echo $customerServiceIconSvg('paper-plane'); ?></button>
                    <div class="service-thread-emoji-panel" hidden data-customer-service-emoji-panel>
                        <?php foreach ($customerServiceEmojis as $emoji): ?>
                            <button type="button" data-customer-service-emoji="<?php echo e((string) $emoji); ?>"><?php echo e((string) $emoji); ?></button>
                        <?php endforeach; ?>
                    </div>
                </form>
            <?php else: ?>
                <div class="service-thread-placeholder">
                    <div class="service-thread-placeholder-icon"><?php echo $customerServiceIconSvg('headset'); ?></div>
                    <h2><?php echo e($customerServiceMemberTitle); ?></h2>
                    <p><?php echo e($customerServiceMemberWelcomeText); ?></p>
                    <div class="service-thread-placeholder-actions">
                        <a href="<?php echo e(public_url('member.php') . '?region=' . urlencode($region) . '&mode=login'); ?>">去登录</a>
                        <a href="<?php echo e(public_url('member.php') . '?region=' . urlencode($region) . '&mode=register'); ?>">去注册</a>
                    </div>
                </div>
            <?php endif; ?>
            </div>
        </div>
    </section>
    <?php if (!$customerServiceEmbed): ?>
        <?php echo \App\Core\View::make(app(), 'partials/front_bottom_nav', array('region' => $region, 'activePanel' => 'service', 'user' => $user)); ?>
    <?php endif; ?>
    <?php if ($customerServiceMode === 'guest'): ?>
        <script>
        (function () {
            var loadedAt = authChangedAt();
            var reloadPrefix = 'front_service_guest_auth_reload_';

            function storageValue(storage, key) {
                try {
                    return storage ? storage.getItem(key) : '';
                } catch (error) {
                    return '';
                }
            }

            function authChangedAt() {
                var localStorageRef = null;
                var changedAt = 0;
                var match;

                try {
                    localStorageRef = window.localStorage || null;
                } catch (error) {
                    localStorageRef = null;
                }

                changedAt = Math.max(changedAt, parseInt(storageValue(localStorageRef, 'front_auth_changed_at') || '0', 10) || 0);
                try {
                    match = document.cookie.match(/(?:^|;\s*)front_auth_changed_at=([^;]+)/);
                    if (match) {
                        changedAt = Math.max(changedAt, parseInt(decodeURIComponent(match[1]) || '0', 10) || 0);
                    }
                } catch (error) {}

                return changedAt;
            }

            function reloadGuestPage(force) {
                var changedAt = authChangedAt();
                var sessionStorageRef = null;
                var reloadKey;
                var url;

                if (!force && changedAt <= loadedAt) {
                    return;
                }

                reloadKey = reloadPrefix + String(changedAt || 'pageshow');
                try {
                    sessionStorageRef = window.sessionStorage || null;
                    if (sessionStorageRef && sessionStorageRef.getItem(reloadKey) === '1') {
                        return;
                    }
                    if (sessionStorageRef) {
                        sessionStorageRef.setItem(reloadKey, '1');
                    }
                } catch (error) {}

                try {
                    url = new URL(window.location.href);
                    url.searchParams.set('_auth_refresh', String(changedAt || (Date.now ? Date.now() : (new Date()).getTime())));
                    window.location.replace(url.href);
                } catch (error) {
                    window.location.reload();
                }
            }

            window.addEventListener('pageshow', function (event) {
                reloadGuestPage(!!(event && event.persisted));
            }, true);
            window.addEventListener('focus', function () {
                reloadGuestPage(false);
            }, true);
            window.addEventListener('storage', function (event) {
                if (event && event.key === 'front_auth_changed_at') {
                    reloadGuestPage(false);
                }
            }, true);
            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'visible') {
                    reloadGuestPage(false);
                }
            }, true);
        })();
        </script>
    <?php endif; ?>
<?php endif; ?>
