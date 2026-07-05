<?php
$currentRegion = isset($region) && $region === 'hongkong' ? 'hongkong' : 'macau';
$showAgentLogoutAction = !empty($agentLogoutAction);
$customerServiceAgentEntryRequested = isset($_GET['agent']) && (string) $_GET['agent'] === '1';
$customerServiceAgentEntryRemembered = (string) \App\Core\Session::get('customer_service_agent_entry', '0') === '1';
$customerServiceAgentNav = ($customerServiceAgentEntryRequested || $customerServiceAgentEntryRemembered)
    && (int) \App\Core\Session::get('customer_service_agent_id', 0) > 0;
$indexUrl = public_url('index.php') . ($customerServiceAgentNav ? '?agent=1' : '');
$recordUrl = public_url('record.php') . ($customerServiceAgentNav ? '?agent=1' : '');
$forecastUrl = public_url('forecast.php') . '?region=' . urlencode($currentRegion) . ($customerServiceAgentNav ? '&agent=1' : '');
$serviceUrl = public_url('service.php') . '?region=' . urlencode($currentRegion) . ($customerServiceAgentNav ? '&agent=1' : '');
$memberUrl = public_url('member.php') . '?region=' . urlencode($currentRegion);
$managedTopHtml = !empty($ignoreManagedTopBar)
    ? ''
    : trim((string) site_setting('appearance.top_html', ''));

$frontTopIconSvg = static function ($name) {
    $icons = array(
        'dice-three' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3" ry="3" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="8.5" cy="8.5" r="1.45"/><circle cx="12" cy="12" r="1.45"/><circle cx="15.5" cy="15.5" r="1.45"/></svg>',
        'download' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 4h2v8.1l2.9-2.9 1.4 1.42L12 15.9l-5.3-5.28L8.1 9.2l2.9 2.9V4Zm-5 14h12v2H6v-2Z"/></svg>',
        'right-from-bracket' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 4h8v2H6v12h6v2H4V4Zm11.6 4.4L20.2 13l-4.6 4.6-1.4-1.42 2.18-2.18H10v-2h6.38L14.2 9.82l1.4-1.42Z"/></svg>',
    );
    $key = isset($icons[$name]) ? $name : 'download';

    return '<i class="front-fa-icon front-icon-' . e($key) . '" aria-hidden="true">' . $icons[$key] . '</i>';
};

if ($managedTopHtml !== '') {
    $managedTopHtml = str_replace('./index.php"', $indexUrl . '"', $managedTopHtml);
    $managedTopHtml = str_replace('./record.php"', $recordUrl . '"', $managedTopHtml);
    $managedTopHtml = str_replace('./forecast.php"', $forecastUrl . '"', $managedTopHtml);
    $managedTopHtml = str_replace('./service.php"', $serviceUrl . '"', $managedTopHtml);
    $managedTopHtml = str_replace('./member.php"', $memberUrl . '"', $managedTopHtml);

    if ($showAgentLogoutAction) {
        $agentLogoutHtml = '<form class="top-agent-logout-form" method="post" action="' . e(public_url('api.php')) . '" data-ajax-form data-immediate-redirect="1">'
            . '<input type="hidden" name="action" value="customer_service.agent.logout">'
            . '<input type="hidden" name="_token" value="' . e(csrf_token('api')) . '">'
            . '<input type="hidden" name="region" value="' . e($currentRegion) . '">'
            . '<button type="submit" class="top-action-btn top-action-agent-logout">'
            . $frontTopIconSvg('right-from-bracket')
            . '<span>退出接待</span>'
            . '</button>'
            . '</form>';
        $managedTopHtml = preg_replace('/<a\b(?=[^>]*\btop-action-download\b)[\s\S]*?<\/a>/u', $agentLogoutHtml, $managedTopHtml, 1);
    }

    echo $managedTopHtml;
    return;
}
?>
<header class="top-bar text-white">
    <div class="top-bar-inner max-w-7xl mx-auto px-4 sm:px-6 py-2.5 sm:py-3 flex items-center justify-between gap-3">
        <div class="top-brand flex items-center gap-3 sm:gap-4 flex-shrink-0">
            <div class="top-brand-mark w-10 h-10 sm:w-12 sm:h-12 rounded-2xl flex items-center justify-center shadow-inner" aria-hidden="true">
                <?php echo $frontTopIconSvg('dice-three'); ?>
                <span>777</span>
            </div>
            <div class="top-brand-copy flex flex-col">
                <div class="top-brand-title flex items-baseline">
                    <span class="top-brand-name-main text-2xl sm:text-3xl font-black tracking-tighter">HONGYUNLIUHE</span>
                </div>
                <div class="top-brand-domain text-[10px] text-yellow-200 leading-none">HONGYUN666.COM</div>
            </div>
        </div>
        <div class="top-bar-actions flex items-center gap-2 sm:gap-3 flex-shrink-0">
            <?php if ($showAgentLogoutAction): ?>
                <form class="top-agent-logout-form" method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form data-immediate-redirect="1">
                    <input type="hidden" name="action" value="customer_service.agent.logout">
                    <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                    <input type="hidden" name="region" value="<?php echo e($currentRegion); ?>">
                    <button type="submit" class="top-action-btn top-action-agent-logout">
                        <?php echo $frontTopIconSvg('right-from-bracket'); ?>
                        <span>退出接待</span>
                    </button>
                </form>
            <?php else: ?>
                <a href="<?php echo e($serviceUrl); ?>" class="top-action-btn top-action-download bg-gradient-to-r from-yellow-400 to-amber-500 hover:from-amber-500 hover:to-yellow-400 text-black font-bold text-sm sm:text-base rounded-2xl flex items-center gap-1.5 shadow-lg transition-all whitespace-nowrap">
                    <?php echo $frontTopIconSvg('download'); ?>
                    <span>下载APP</span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</header>
<div class="top-bar-spacer" aria-hidden="true"></div>
