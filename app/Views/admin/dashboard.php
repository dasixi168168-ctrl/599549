<?php
$stats = isset($stats) && is_array($stats) ? $stats : array();
$recentLoginLogs = isset($recentLoginLogs) && is_array($recentLoginLogs) ? $recentLoginLogs : array();
$recentOperationLogs = isset($recentOperationLogs) && is_array($recentOperationLogs) ? $recentOperationLogs : array();
$todayMemberList = isset($stats['members_today_list']) && is_array($stats['members_today_list'])
    ? $stats['members_today_list']
    : array();
$homeVisitSummary = isset($stats['home_visit_summary']) && is_array($stats['home_visit_summary'])
    ? $stats['home_visit_summary']
    : array();
$homeVisitRows = isset($stats['home_visit_rows']) && is_array($stats['home_visit_rows'])
    ? $stats['home_visit_rows']
    : array();

$formatNumber = static function ($value) {
    return number_format((float) $value, is_float($value) ? 1 : 0);
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

$adminUrl = static function ($page, array $params = array()) {
    return public_url('admin.php') . '?' . http_build_query(array_merge(array('page' => (string) $page), $params));
};

$frontUrl = static function ($path, array $params = array()) {
    $url = public_url((string) $path);

    return $params ? $url . '?' . http_build_query($params) : $url;
};

$renderVisitLink = static function ($url) {
    $text = trim((string) $url);

    if ($text === '' || $text === '直接访问') {
        return '<span class="command-source-link is-muted">直接访问</span>';
    }

    return '<a class="command-source-link" href="' . e($text) . '" target="_blank" rel="noopener noreferrer">' . e($text) . '</a>';
};

$metricCards = array(
    array(
        'code' => 'reach',
        'label' => '前台访问',
        'value' => $formatNumber((int) ($stats['visits_today'] ?? 0)),
        'note' => '今日触达',
        'delta' => '较昨日 ' . $formatSignedPercent((float) ($stats['visits_growth_percent'] ?? 0)),
        'trend' => (float) ($stats['visits_growth_percent'] ?? 0) < 0 ? 'down' : 'up',
        'href' => $adminUrl('login_logs'),
    ),
    array(
        'code' => 'member',
        'label' => '会员增长',
        'value' => $formatNumber((int) ($stats['members_today_count'] ?? 0)),
        'note' => '今日新增',
        'delta' => '本周 ' . $formatSignedNumber((int) ($stats['members_this_week'] ?? 0)),
        'trend' => (int) ($stats['members_this_week'] ?? 0) < 0 ? 'down' : 'up',
        'href' => $adminUrl('users'),
    ),
    array(
        'code' => 'content',
        'label' => '内容生产',
        'value' => $formatNumber((int) ($stats['posts_today'] ?? 0)),
        'note' => '今日发帖',
        'delta' => '较昨日 ' . $formatSignedNumber((int) ($stats['posts_growth_count'] ?? 0), ' 篇'),
        'trend' => (int) ($stats['posts_growth_count'] ?? 0) < 0 ? 'down' : 'up',
        'href' => $adminUrl('posts'),
    ),
    array(
        'code' => 'service',
        'label' => '客服队列',
        'value' => $formatNumber((int) ($stats['open_threads'] ?? 0)),
        'note' => '待处理会话',
        'delta' => '前台在线客服联动',
        'trend' => 'up',
        'href' => $adminUrl('support'),
    ),
    array(
        'code' => 'convert',
        'label' => '访问转化',
        'value' => number_format((float) ($stats['traffic_conversion_rate'] ?? 0), 1) . '%',
        'note' => '访客到会员',
        'delta' => '较昨日 ' . $formatSignedPercent((float) ($stats['traffic_conversion_diff_percent'] ?? 0)),
        'trend' => (float) ($stats['traffic_conversion_diff_percent'] ?? 0) < 0 ? 'down' : 'up',
        'href' => $adminUrl('users'),
    ),
);

$frontLinks = array(
    array('title' => '澳门首页', 'desc' => '首页内容、论坛入口、澳门开奖展示', 'href' => $frontUrl('index.php'), 'tag' => '前台'),
    array('title' => '香港首页', 'desc' => '香港开奖与论坛首页入口', 'href' => $frontUrl('record.php'), 'tag' => '前台'),
    array('title' => 'AI预测大厅', 'desc' => '会员预测互动与开奖记录联动', 'href' => $frontUrl('forecast.php', array('region' => 'macau')), 'tag' => '互动'),
    array('title' => '在线客服', 'desc' => '会员咨询入口与接待工作台同链路', 'href' => $frontUrl('service.php', array('region' => 'macau')), 'tag' => '客服'),
    array('title' => '接待工作台', 'desc' => '客服账号前台接待入口', 'href' => $frontUrl('service.php', array('region' => 'macau', 'agent' => '1')), 'tag' => '接待'),
);

$operationGroups = array(
    array(
        'title' => '内容与前台',
        'desc' => '发帖、版块、首页运营直接影响前台展示。',
        'items' => array(
            array('label' => '帖子管理', 'href' => $adminUrl('posts'), 'meta' => '发布 / 编辑 / 上下架'),
            array('label' => '版块管理', 'href' => $adminUrl('sections'), 'meta' => '论坛结构'),
            array('label' => 'AI预测设置', 'href' => $adminUrl('home'), 'meta' => '首页运营内容'),
        ),
    ),
    array(
        'title' => '开奖与资料',
        'desc' => '开奖、期数、资料同步到澳门/香港前台页面。',
        'items' => array(
            array('label' => '资料更新', 'href' => $adminUrl('draws'), 'meta' => '开奖记录'),
            array('label' => '期数管理', 'href' => $adminUrl('issues'), 'meta' => '期号节奏'),
            array('label' => '前后台设置', 'href' => $adminUrl('settings'), 'meta' => '站点配置'),
        ),
    ),
    array(
        'title' => '会员与服务',
        'desc' => '会员、客服、审核链路保持前后台实时闭环。',
        'items' => array(
            array('label' => '会员管理', 'href' => $adminUrl('users'), 'meta' => '账号与身份'),
            array('label' => '在线客服', 'href' => $adminUrl('support'), 'meta' => '监督 / 账号'),
            array('label' => '审核管理', 'href' => $adminUrl('audits'), 'meta' => '内容审核'),
        ),
    ),
    array(
        'title' => '安全与追踪',
        'desc' => '安全策略、登录日志、操作日志保障可追溯。',
        'items' => array(
            array('label' => '安全策略', 'href' => $adminUrl('security'), 'meta' => '风控规则'),
            array('label' => '流量统计', 'href' => $adminUrl('login_logs'), 'meta' => '登录来源'),
            array('label' => '网站日志', 'href' => $adminUrl('operation_logs'), 'meta' => '操作审计'),
        ),
    ),
);
?>
<section class="command-dashboard" aria-label="后台仪表盘">
    <section class="command-hero">
        <div class="command-hero-main">
            <span class="command-kicker">前后台联动工作台</span>
            <h2>把运营、会员、内容和客服放到一张指挥图里。</h2>
            <p>此页只重构管理视图，不改变固定标题栏；所有入口继续连接现有后台接口和前台页面。</p>
            <div class="command-hero-actions">
                <a href="<?php echo e($adminUrl('posts')); ?>">发布内容</a>
                <a href="<?php echo e($adminUrl('support')); ?>">处理客服</a>
                <a href="<?php echo e($frontUrl('index.php')); ?>" target="_blank" rel="noopener noreferrer">查看前台</a>
            </div>
        </div>
        <div class="command-hero-side">
            <span>今日新增会员</span>
            <strong><?php echo e($formatNumber((int) ($stats['members_today_count'] ?? 0))); ?></strong>
            <em>访问转化 <?php echo e(number_format((float) ($stats['traffic_conversion_rate'] ?? 0), 1)); ?>%</em>
        </div>
    </section>

    <section class="command-metric-grid" aria-label="核心运营指标">
        <?php foreach ($metricCards as $card): ?>
            <a class="command-metric-card is-<?php echo e((string) $card['code']); ?>" href="<?php echo e((string) $card['href']); ?>">
                <span class="command-metric-label"><?php echo e((string) $card['label']); ?></span>
                <strong><?php echo e((string) $card['value']); ?></strong>
                <small><?php echo e((string) $card['note']); ?></small>
                <em class="is-<?php echo e((string) $card['trend']); ?>"><?php echo e((string) $card['delta']); ?></em>
            </a>
        <?php endforeach; ?>
    </section>

    <section class="command-grid">
        <section class="command-card command-front-card">
            <div class="command-card-head">
                <div>
                    <span>前台链路</span>
                    <h3>真实页面直达</h3>
                </div>
                <strong><?php echo e(number_format((int) ($homeVisitSummary['total_count'] ?? 0))); ?> 次今日首页访问</strong>
            </div>
            <div class="command-front-links">
                <?php foreach ($frontLinks as $link): ?>
                    <a href="<?php echo e((string) $link['href']); ?>" target="_blank" rel="noopener noreferrer">
                        <span><?php echo e((string) $link['tag']); ?></span>
                        <strong><?php echo e((string) $link['title']); ?></strong>
                        <small><?php echo e((string) $link['desc']); ?></small>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="command-card command-member-card">
            <div class="command-card-head">
                <div>
                    <span>会员信号</span>
                    <h3>今日新注册</h3>
                </div>
                <a href="<?php echo e($adminUrl('users')); ?>">管理会员</a>
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
    </section>

    <section class="command-card command-ops-card">
        <div class="command-card-head">
            <div>
                <span>管理动作</span>
                <h3>按前台影响面组织操作入口</h3>
            </div>
            <strong>GET 后台页 + POST API 保持现有链路</strong>
        </div>
        <div class="command-operation-grid">
            <?php foreach ($operationGroups as $group): ?>
                <article class="command-operation-group">
                    <h4><?php echo e((string) $group['title']); ?></h4>
                    <p><?php echo e((string) $group['desc']); ?></p>
                    <div>
                        <?php foreach ($group['items'] as $item): ?>
                            <a href="<?php echo e((string) $item['href']); ?>">
                                <strong><?php echo e((string) $item['label']); ?></strong>
                                <span><?php echo e((string) $item['meta']); ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="command-grid is-lower">
        <section class="command-card command-source-card">
            <div class="command-card-head">
                <div>
                    <span>来源追踪</span>
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
                            <div>
                                <?php echo $renderVisitLink($row['source_url'] ?? ''); ?>
                                <span>访问 <?php echo $renderVisitLink($row['visit_url'] ?? ''); ?></span>
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

        <section class="command-card command-audit-card">
            <div class="command-card-head">
                <div>
                    <span>审计动态</span>
                    <h3>后台最近动作</h3>
                </div>
                <a href="<?php echo e($adminUrl('operation_logs')); ?>">查看日志</a>
            </div>
            <div class="command-audit-list">
                <?php foreach (array_slice($recentOperationLogs, 0, 6) as $log): ?>
                    <article>
                        <strong><?php echo e((string) (($log['summary'] ?? '') ?: '后台操作')); ?></strong>
                        <span><?php echo e((string) (($log['username'] ?? '') ?: '系统')); ?> · <?php echo e((string) (($log['module'] ?? '') ?: 'general')); ?></span>
                        <small><?php echo e(format_datetime($log['created_at'] ?? null)); ?></small>
                    </article>
                <?php endforeach; ?>
                <?php if (!$recentOperationLogs): ?>
                    <div class="command-empty">暂无后台操作记录。</div>
                <?php endif; ?>
            </div>
            <div class="command-login-strip">
                <?php foreach (array_slice($recentLoginLogs, 0, 4) as $log): ?>
                    <span>
                        <?php echo e((string) (($log['username'] ?? '') ?: 'admin')); ?>
                        · <?php echo e((int) ($log['status'] ?? 0) === 1 ? '成功' : '失败'); ?>
                    </span>
                <?php endforeach; ?>
            </div>
        </section>
    </section>
</section>
