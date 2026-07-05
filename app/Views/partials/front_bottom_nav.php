<?php
$currentRegion = isset($region) && $region === 'hongkong' ? 'hongkong' : 'macau';
$activePanel = isset($activePanel) ? (string) $activePanel : '';
$memberLabel = isset($user) && is_array($user) ? '我的' : '登录';
$memberUrl = public_url('member.php') . '?region=' . urlencode($currentRegion) . (isset($user) && is_array($user) ? '' : '&mode=login');
$memberIconName = 'circle-user';
$memberLinkAttributes = 'data-no-prefetch';
$customerServiceAgent = null;
$customerServiceUnreadCount = 0;
$customerServiceLatestMessageId = 0;
$customerServiceUnreadAction = 'customer_service.member.unread';
$customerServiceAgentNavRequested = !empty($customerServiceAgentNav);
$customerServiceAgentEntryRequested = isset($_GET['agent']) && (string) $_GET['agent'] === '1';
$customerServiceAgentEntryRemembered = (string) \App\Core\Session::get('customer_service_agent_entry', '0') === '1';
$customerServiceAgentNavScope = $customerServiceAgentNavRequested
    || $customerServiceAgentEntryRequested
    || $customerServiceAgentEntryRemembered
    || $activePanel === 'service_manage';
$customerServiceAgentSessionActive = $customerServiceAgentNavScope && (int) \App\Core\Session::get('customer_service_agent_id', 0) > 0;
if ($customerServiceAgentSessionActive) {
    try {
        $customerServiceAgent = app()->support()->currentAgent();
    } catch (\Throwable $exception) {
        $customerServiceAgent = null;
    }
}
if ($customerServiceAgentSessionActive && !$customerServiceAgent) {
    $customerServiceAgentSessionActive = false;
}
if ($customerServiceAgentSessionActive) {
    $customerServiceUnreadAction = 'customer_service.agent.unread';
    $memberLabel = '管理';
    $memberUrl = public_url('service.php') . '?agent=1&panel=manage&region=' . urlencode($currentRegion);
    $memberIconName = 'screwdriver-wrench';
    $memberLinkAttributes = 'data-no-prefetch';
    if ($customerServiceAgent) {
        try {
            $customerServiceUnreadPayload = app()->support()->agentUnreadPayload($customerServiceAgent);
            $customerServiceUnreadCount = (int) ($customerServiceUnreadPayload['unread_count'] ?? 0);
            $customerServiceLatestMessageId = (int) ($customerServiceUnreadPayload['latest_message_id'] ?? 0);
        } catch (\Throwable $exception) {
            $customerServiceUnreadCount = 0;
            $customerServiceLatestMessageId = 0;
        }
    }
} elseif (isset($user) && is_array($user) && (int) ($user['id'] ?? 0) > 0) {
    try {
        $customerServiceUnreadPayload = app()->support()->memberUnreadPayload((int) $user['id']);
        $customerServiceUnreadCount = (int) ($customerServiceUnreadPayload['unread_count'] ?? 0);
        $customerServiceLatestMessageId = (int) ($customerServiceUnreadPayload['latest_message_id'] ?? 0);
    } catch (\Throwable $exception) {
        $customerServiceUnreadCount = 0;
        $customerServiceLatestMessageId = 0;
    }
}
$customerServiceUnreadLabel = $customerServiceUnreadCount > 99 ? '99+' : (string) $customerServiceUnreadCount;
$customerServiceUnreadEnabled = ($customerServiceAgentSessionActive || (isset($user) && is_array($user) && (int) ($user['id'] ?? 0) > 0)) && $activePanel !== 'service';
$agentNavSuffix = $customerServiceAgentSessionActive ? 'agent=1' : '';
$macauNavUrl = public_url('index.php') . ($agentNavSuffix !== '' ? '?' . $agentNavSuffix : '');
$hongkongNavUrl = public_url('record.php') . ($agentNavSuffix !== '' ? '?' . $agentNavSuffix : '');
$forecastNavUrl = public_url('forecast.php') . '?region=macau' . ($agentNavSuffix !== '' ? '&' . $agentNavSuffix : '');
$serviceNavUrl = public_url('service.php') . '?region=' . urlencode($currentRegion) . ($agentNavSuffix !== '' ? '&' . $agentNavSuffix : '');
$managedBottomHtml = !empty($ignoreManagedBottomNav)
    ? ''
    : trim((string) site_setting('appearance.bottom_html', ''));
$frontNavIconSvg = static function ($name) {
    if ($name === 'brain') {
        return '<i class="front-fa-icon front-icon-brain fa-solid fa-brain" aria-hidden="true"></i>';
    }

    $icons = array(
        'house' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 10.8 12 3l9 7.8-1.35 1.55L18.5 11.3V20h-5v-5.2h-3V20h-5v-8.7l-1.15 1.05L3 10.8Z"/></svg>',
        'headset' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3a8 8 0 0 0-8 8v4.2A2.8 2.8 0 0 0 6.8 18H9v-7H6.2A5.8 5.8 0 0 1 18 11h-3v7h2.1c-.45 1.18-1.45 2-3.1 2h-2v2h2c3.2 0 5.45-1.86 5.9-4.55A2.8 2.8 0 0 0 22 14.8V11a10 10 0 0 0-10-8Zm-5 10h1v3H6.8a.8.8 0 0 1-.8-.8V13h1Zm10 0h1v2.2a.8.8 0 0 1-.8.8H17v-3Z"/></svg>',
        'circle-user' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 4.2a3.2 3.2 0 1 1 0 6.4 3.2 3.2 0 0 1 0-6.4Zm0 13.8a7.95 7.95 0 0 1-5.6-2.28c.8-2.2 2.86-3.52 5.6-3.52s4.8 1.32 5.6 3.52A7.95 7.95 0 0 1 12 20Z"/></svg>',
        'screwdriver-wrench' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 6.4-3.5 3.5-2.4-2.4L18.6 4a4.8 4.8 0 0 0-6.1 5.9L4 18.4V21h2.6l8.5-8.5A4.8 4.8 0 0 0 21 6.4ZM6.2 19l7.7-7.7.8.8L7 19.8H6.2V19ZM4.6 3.2 3.2 4.6l4.2 4.2 1.4-1.4L7.4 6l1.2-1.2L7.2 3.4 6 4.6 4.6 3.2Z"/></svg>',
    );
    $key = isset($icons[$name]) ? $name : 'circle-user';

    return '<i class="front-fa-icon front-icon-' . e($key) . '" aria-hidden="true">' . $icons[$key] . '</i>';
};

if ($managedBottomHtml !== '') {
    $navDataAttributes = ' data-customer-service-unread-poll'
        . ' data-api-url="' . e(public_url('api.php')) . '"'
        . ' data-token="' . e(csrf_token('api')) . '"'
        . ' data-poll-action="' . e($customerServiceUnreadAction) . '"'
        . ' data-enabled="' . ($customerServiceUnreadEnabled ? '1' : '0') . '"'
        . ' data-last-message-id="' . e((string) $customerServiceLatestMessageId) . '"';
    $managedBottomHtml = (string) preg_replace_callback(
        '/<nav\b([^>]*\bclass="[^"]*\bbottom-float-nav\b[^"]*"[^>]*)>/u',
        static function ($matches) use ($navDataAttributes) {
            $tag = (string) $matches[0];

            if (strpos($tag, 'data-customer-service-unread-poll') !== false) {
                return $tag;
            }

            return rtrim(substr($tag, 0, -1)) . $navDataAttributes . '>';
        },
        $managedBottomHtml,
        1
    );

    $managedBottomHtml = (string) preg_replace_callback(
        '/<a\b(?P<attrs>[^>]*)>(?P<body>[\s\S]*?)<\/a>/u',
        static function ($matches) use (
            $currentRegion,
            $activePanel,
            $macauNavUrl,
            $hongkongNavUrl,
            $forecastNavUrl,
            $serviceNavUrl,
            $memberUrl,
            $memberLabel,
            $customerServiceUnreadLabel,
            $customerServiceUnreadEnabled,
            $customerServiceUnreadCount
        ) {
            $attrs = (string) $matches['attrs'];
            $body = (string) $matches['body'];

            if (!preg_match('/\bclass="([^"]*\bbottom-nav-link\b[^"]*)"/u', $attrs, $classMatches)) {
                return $matches[0];
            }

            $plainText = trim((string) preg_replace('/\s+/u', '', strip_tags($body)));
            $item = '';
            if (strpos($plainText, '澳门') !== false) {
                $item = 'macau';
            } elseif (strpos($plainText, '香港') !== false) {
                $item = 'hongkong';
            } elseif (strpos($plainText, '预测') !== false) {
                $item = 'forecast';
            } elseif (strpos($plainText, '客服') !== false) {
                $item = 'service';
            } elseif (strpos($plainText, '登录') !== false || strpos($plainText, '我的') !== false || strpos($plainText, '管理') !== false || preg_match('/\bbottom-nav-login\b/u', $attrs)) {
                $item = 'member';
            }

            if ($item === '') {
                return $matches[0];
            }

            $href = $memberUrl;
            $active = false;
            $extraAttribute = ' data-no-prefetch';
            if ($item === 'macau') {
                $href = $macauNavUrl;
                $active = $currentRegion === 'macau' && $activePanel === '';
                $extraAttribute = ' data-nav-prefetch="1"';
            } elseif ($item === 'hongkong') {
                $href = $hongkongNavUrl;
                $active = $currentRegion === 'hongkong' && $activePanel === '';
                $extraAttribute = ' data-nav-prefetch="1"';
            } elseif ($item === 'forecast') {
                $href = $forecastNavUrl;
                $active = $activePanel === 'forecast';
                $extraAttribute = ' data-nav-prefetch="1"';
            } elseif ($item === 'service') {
                $href = $serviceNavUrl;
                $active = $activePanel === 'service';
                $extraAttribute = ' data-no-prefetch';
            } elseif ($item === 'member') {
                $active = in_array($activePanel, array('member', 'service_manage'), true);
            }

            if (preg_match('/\bhref="/u', $attrs)) {
                $attrs = (string) preg_replace('/\bhref="[^"]*"/u', 'href="' . e($href) . '"', $attrs, 1);
            } else {
                $attrs = ' href="' . e($href) . '"' . $attrs;
            }

            $attrs = (string) preg_replace('/\sdata-nav-prefetch="[^"]*"/u', '', $attrs);
            $attrs = (string) preg_replace('/\sdata-no-prefetch\b/u', '', $attrs);
            $attrs .= $extraAttribute;

            $classList = preg_split('/\s+/', trim((string) $classMatches[1]));
            $classList = is_array($classList) ? $classList : array();
            $classList = array_values(array_filter($classList, static function ($className) {
                return $className !== '' && $className !== 'is-active';
            }));
            if ($item === 'member' && !in_array('bottom-nav-login', $classList, true)) {
                $classList[] = 'bottom-nav-login';
            }
            if ($active) {
                $classList[] = 'is-active';
            }
            $attrs = (string) preg_replace('/\bclass="[^"]*"/u', 'class="' . e(implode(' ', $classList)) . '"', $attrs, 1);

            if ($item === 'member') {
                $body = (string) preg_replace(
                    '/(<span\b(?![^>]*\bbottom-nav-unread\b)[^>]*>)(?:登录|我的|管理)(<\/span>)/u',
                    '$1' . e($memberLabel) . '$2',
                    $body,
                    1
                );
            }

            if ($item === 'service' && strpos($body, 'bottom-nav-unread') === false) {
                $unreadBadge = '<span class="bottom-nav-unread" data-customer-service-unread-badge aria-label="未阅读信息'
                    . e($customerServiceUnreadLabel)
                    . '条" '
                    . ($customerServiceUnreadEnabled && $customerServiceUnreadCount > 0 ? '' : 'hidden')
                    . '>'
                    . e($customerServiceUnreadLabel)
                    . '</span>';
                $body = (string) preg_replace(
                    '/(<span\b(?![^>]*\bbottom-nav-unread\b)[^>]*>\s*客服\s*<\/span>)/u',
                    $unreadBadge . '$1',
                    $body,
                    1
                );
            }

            return '<a' . $attrs . '>' . $body . '</a>';
        },
        $managedBottomHtml
    );

    echo $managedBottomHtml;
    return;
}
?>
<nav class="bottom-float-nav" aria-label="底部悬浮导航" data-customer-service-unread-poll data-api-url="<?php echo e(public_url('api.php')); ?>" data-token="<?php echo e(csrf_token('api')); ?>" data-poll-action="<?php echo e($customerServiceUnreadAction); ?>" data-enabled="<?php echo $customerServiceUnreadEnabled ? '1' : '0'; ?>" data-last-message-id="<?php echo e((string) $customerServiceLatestMessageId); ?>">
    <a href="<?php echo e($macauNavUrl); ?>" class="bottom-nav-link <?php echo $currentRegion === 'macau' && $activePanel === '' ? 'is-active' : ''; ?>" data-nav-prefetch="1">
        <?php echo $frontNavIconSvg('house'); ?>
        <span>澳门</span>
    </a>
    <a href="<?php echo e($hongkongNavUrl); ?>" class="bottom-nav-link <?php echo $currentRegion === 'hongkong' && $activePanel === '' ? 'is-active' : ''; ?>" data-nav-prefetch="1">
        <?php echo $frontNavIconSvg('house'); ?>
        <span>香港</span>
    </a>
    <a href="<?php echo e($forecastNavUrl); ?>" class="bottom-nav-link <?php echo $activePanel === 'forecast' ? 'is-active' : ''; ?>" data-nav-prefetch="1">
        <?php echo $frontNavIconSvg('brain'); ?>
        <span>预测</span>
    </a>
    <a href="<?php echo e($serviceNavUrl); ?>" class="bottom-nav-link <?php echo $activePanel === 'service' ? 'is-active' : ''; ?>" data-no-prefetch>
        <?php echo $frontNavIconSvg('headset'); ?>
        <span class="bottom-nav-unread" data-customer-service-unread-badge aria-label="未阅读信息<?php echo e($customerServiceUnreadLabel); ?>条" <?php echo $customerServiceUnreadEnabled && $customerServiceUnreadCount > 0 ? '' : 'hidden'; ?>><?php echo e($customerServiceUnreadLabel); ?></span>
        <span>客服</span>
    </a>
    <a href="<?php echo e($memberUrl); ?>" class="bottom-nav-link bottom-nav-login <?php echo in_array($activePanel, array('member', 'service_manage'), true) ? 'is-active' : ''; ?>" <?php echo $memberLinkAttributes; ?>>
        <?php echo $frontNavIconSvg($memberIconName); ?>
        <span><?php echo e($memberLabel); ?></span>
    </a>
</nav>
