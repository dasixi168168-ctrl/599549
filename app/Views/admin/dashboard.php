<?php
$stats = isset($stats) && is_array($stats) ? $stats : array();
$todayMemberList = isset($stats['members_today_list']) && is_array($stats['members_today_list'])
    ? $stats['members_today_list']
    : array();
$homeVisitSummary = isset($stats['home_visit_summary']) && is_array($stats['home_visit_summary'])
    ? $stats['home_visit_summary']
    : array();
$homeVisitRows = isset($stats['home_visit_rows']) && is_array($stats['home_visit_rows'])
    ? $stats['home_visit_rows']
    : array();

$formatNumber = static function ($value, $decimals = null) {
    $precision = $decimals === null ? (is_float($value) ? 1 : 0) : (int) $decimals;

    return number_format((float) $value, $precision);
};

$formatSignedNumber = static function ($value, $suffix = '') {
    $number = (float) $value;
    $prefix = $number > 0 ? '+' : '';

    return $prefix . number_format($number, 0) . $suffix;
};

$formatSignedPercent = static function ($value) {
    $number = (float) $value;
    $prefix = $number > 0 ? '+' : '';

    return $prefix . number_format($number, 1) . '%';
};

$trendClass = static function ($value) {
    $number = (float) $value;
    if ($number < 0) {
        return 'down';
    }

    return $number > 0 ? 'up' : 'neutral';
};

$adminUrl = static function ($page, array $params = array()) {
    return public_url('admin.php') . '?' . http_build_query(array_merge(array('page' => (string) $page), $params));
};

$renderVisitLink = static function ($url) {
    $text = trim((string) $url);

    if ($text === '' || $text === '直接访问') {
        return '<span class="command-source-link is-muted">直接访问</span>';
    }

    $isAllowedLink = stripos($text, 'http://') === 0
        || stripos($text, 'https://') === 0
        || (strpos($text, '/') === 0 && strpos($text, '//') !== 0);
    if (!$isAllowedLink) {
        return '<span class="command-source-link is-muted">' . e($text) . '</span>';
    }

    return '<a class="command-source-link" href="' . e($text) . '" target="_blank" rel="noopener noreferrer">' . e($text) . '</a>';
};

$membersToday = (int) ($stats['members_today_count'] ?? 0);
$frontVisitsToday = (int) ($stats['front_visits_today'] ?? ($stats['visits_today'] ?? 0));
$frontVisitsGrowthPercent = (float) ($stats['front_visits_growth_percent'] ?? ($stats['visits_growth_percent'] ?? 0));
$returnVisitsToday = (int) ($stats['return_visits_today'] ?? 0);
$returnVisitsThisWeek = (int) ($stats['return_visits_this_week'] ?? 0);
$memberVisitsToday = (int) ($stats['member_visits_today'] ?? 0);
$memberVisitsGrowthCount = (int) ($stats['member_visits_growth_count'] ?? 0);
$rechargeScoreTotal = (int) ($stats['recharge_score_total'] ?? ($stats['recharge_score_today'] ?? 0));
$rechargeScoreToday = (int) ($stats['recharge_score_today'] ?? 0);
$rechargeScoreYesterday = (int) ($stats['recharge_score_yesterday'] ?? 0);
$purchasesToday = (int) ($stats['purchases_today'] ?? 0);
$purchasePostsToday = (int) ($stats['purchase_posts_today'] ?? 0);
$repeatPurchasesToday = (int) ($stats['repeat_purchases_today'] ?? 0);

$metricCards = array(
    array(
        'code' => 'green',
        'label' => '今日新增会员',
        'value' => $formatNumber($membersToday),
        'note' => '今日新增',
        'delta' => '访问转化 ' . $formatNumber((float) ($stats['traffic_conversion_rate'] ?? 0), 1) . '%',
        'trend' => $trendClass((float) ($stats['traffic_conversion_rate'] ?? 0)),
        'href' => $adminUrl('users'),
    ),
    array(
        'code' => 'blue',
        'label' => '前台访问',
        'value' => $formatNumber($frontVisitsToday),
        'note' => '今日触达',
        'delta' => '较昨日 ' . $formatSignedPercent($frontVisitsGrowthPercent),
        'trend' => $trendClass($frontVisitsGrowthPercent),
        'href' => $adminUrl('dashboard') . '#dashboard-front-visits',
    ),
    array(
        'code' => 'green',
        'label' => '回头访问',
        'value' => $formatNumber($returnVisitsToday),
        'note' => '今日回访',
        'delta' => '本周回访 ' . $formatNumber($returnVisitsThisWeek),
        'trend' => $trendClass($returnVisitsThisWeek),
        'href' => $adminUrl('dashboard'),
    ),
    array(
        'code' => 'orange',
        'label' => '首页会员访问',
        'value' => $formatNumber($memberVisitsToday),
        'note' => '今日首页会员访问',
        'delta' => '较昨日 ' . $formatSignedNumber($memberVisitsGrowthCount, ' 人'),
        'trend' => $trendClass($memberVisitsGrowthCount),
        'href' => $adminUrl('login_logs'),
    ),
    array(
        'code' => 'purple',
        'label' => '出售帖子',
        'value' => $formatNumber($purchasePostsToday),
        'note' => '今日购买 ' . $formatNumber($purchasesToday),
        'delta' => '今日重复购买 ' . $formatNumber($repeatPurchasesToday),
        'trend' => $trendClass($purchasesToday),
        'href' => $adminUrl('posts'),
    ),
    array(
        'code' => 'red',
        'label' => '累计充值积分',
        'value' => $formatNumber($rechargeScoreTotal),
        'note' => '当前会员累计',
        'delta' => '今日充值 ' . $formatNumber($rechargeScoreToday),
        'trend' => $trendClass($rechargeScoreToday - $rechargeScoreYesterday),
        'href' => $adminUrl('users'),
    ),
);

?>
<section class="command-dashboard" aria-label="后台仪表盘">
    <section class="command-metric-grid" aria-label="核心运营指标">
        <?php foreach ($metricCards as $card): ?>
            <a class="command-metric-card is-<?php echo e((string) $card['code']); ?>" href="<?php echo e((string) $card['href']); ?>" aria-label="<?php echo e((string) $card['label'] . '：' . (string) $card['value']); ?>">
                <span class="command-metric-label"><?php echo e((string) $card['label']); ?></span>
                <strong><?php echo e((string) $card['value']); ?></strong>
                <small><?php echo e((string) $card['note']); ?></small>
                <em class="is-<?php echo e((string) $card['trend']); ?>"><?php echo e((string) $card['delta']); ?></em>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="command-card command-member-card">
        <div class="command-card-head">
            <div>
                <h3>今日新注册</h3>
            </div>
        </div>
        <div class="command-member-list">
            <?php if ($todayMemberList): ?>
                <?php foreach (array_slice($todayMemberList, 0, 8) as $member): ?>
                    <article>
                        <strong><?php echo e((string) ($member['username'] ?? '新会员')); ?></strong>
                        <span><?php echo e(format_datetime($member['created_at'] ?? null)); ?></span>
                        <small><?php echo e(trim((string) ($member['register_ip'] ?? '')) !== '' ? (string) $member['register_ip'] : '未记录 IP'); ?></small>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="command-empty">今日暂无新增会员，会员新增后会实时出现在这里。</div>
            <?php endif; ?>
        </div>
    </section>

    <section class="command-card command-source-card" id="dashboard-front-visits">
        <div class="command-card-head">
            <div>
                <h3>最近 7 天前台访问</h3>
            </div>
            <div class="command-source-summary">
                <span>IP <?php echo e(number_format((int) ($homeVisitSummary['ip_count'] ?? 0))); ?></span>
                <span>手机 <?php echo e(number_format((int) ($homeVisitSummary['mobile_count'] ?? 0))); ?></span>
                <span>电脑 <?php echo e(number_format((int) ($homeVisitSummary['desktop_count'] ?? 0))); ?></span>
            </div>
        </div>
        <div class="command-source-list">
            <?php if ($homeVisitRows): ?>
                <?php foreach (array_slice($homeVisitRows, 0, 12) as $row): ?>
                    <article>
                        <div class="command-source-main">
                            <?php echo $renderVisitLink($row['source_url'] ?? ''); ?>
                            <span class="command-source-visit">访问 <?php echo $renderVisitLink($row['visit_url'] ?? ''); ?></span>
                        </div>
                        <small>
                            <?php echo e((string) ($row['ip_address'] ?? '-')); ?>
                            · <?php echo e((string) ($row['province_name'] ?? '未知省份')); ?>
                            · <?php echo e((string) ($row['city_name'] ?? '未知城市')); ?>
                            · <?php echo e((string) ($row['device_label'] ?? '电脑')); ?>
                            · <?php echo e(format_datetime($row['visited_at'] ?? null)); ?>
                        </small>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="command-empty">最近 7 天暂无前台访问来源记录。</div>
            <?php endif; ?>
        </div>
    </section>
</section>
