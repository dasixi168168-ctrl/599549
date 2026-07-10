<?php
$operationLogFilters = isset($operationLogFilters) && is_array($operationLogFilters) ? $operationLogFilters : array();
$operationLogPage = isset($operationLogPage) && is_array($operationLogPage) ? $operationLogPage : array('items' => array(), 'total' => 0, 'page_no' => 1, 'page_count' => 1);
$operationLogs = isset($operationLogPage['items']) && is_array($operationLogPage['items']) ? $operationLogPage['items'] : array();
$systemLogFilters = isset($systemLogFilters) && is_array($systemLogFilters) ? $systemLogFilters : array();
$systemLogPage = isset($systemLogPage) && is_array($systemLogPage) ? $systemLogPage : array('items' => array(), 'total' => 0, 'page_no' => 1, 'page_count' => 1);
$systemLogs = isset($systemLogPage['items']) && is_array($systemLogPage['items']) ? $systemLogPage['items'] : array();
$operationQueryBase = array(
    'page' => 'operation_logs',
    'module' => (string) ($operationLogFilters['module'] ?? ''),
    'keyword' => (string) ($operationLogFilters['keyword'] ?? ''),
    'date_from' => (string) ($operationLogFilters['date_from'] ?? ''),
    'date_to' => (string) ($operationLogFilters['date_to'] ?? ''),
    'system_source' => (string) ($systemLogFilters['source'] ?? ''),
    'system_keyword' => (string) ($systemLogFilters['keyword'] ?? ''),
    'system_date_from' => (string) ($systemLogFilters['date_from'] ?? ''),
    'system_date_to' => (string) ($systemLogFilters['date_to'] ?? ''),
    'system_page_no' => (int) ($systemLogPage['page_no'] ?? 1),
);
$systemQueryBase = array(
    'page' => 'operation_logs',
    'module' => (string) ($operationLogFilters['module'] ?? ''),
    'keyword' => (string) ($operationLogFilters['keyword'] ?? ''),
    'date_from' => (string) ($operationLogFilters['date_from'] ?? ''),
    'date_to' => (string) ($operationLogFilters['date_to'] ?? ''),
    'page_no' => (int) ($operationLogPage['page_no'] ?? 1),
    'system_source' => (string) ($systemLogFilters['source'] ?? ''),
    'system_keyword' => (string) ($systemLogFilters['keyword'] ?? ''),
    'system_date_from' => (string) ($systemLogFilters['date_from'] ?? ''),
    'system_date_to' => (string) ($systemLogFilters['date_to'] ?? ''),
);
$operationResetQuery = array(
    'page' => 'operation_logs',
    'system_source' => (string) ($systemLogFilters['source'] ?? ''),
    'system_keyword' => (string) ($systemLogFilters['keyword'] ?? ''),
    'system_date_from' => (string) ($systemLogFilters['date_from'] ?? ''),
    'system_date_to' => (string) ($systemLogFilters['date_to'] ?? ''),
);
$systemResetQuery = array(
    'page' => 'operation_logs',
    'module' => (string) ($operationLogFilters['module'] ?? ''),
    'keyword' => (string) ($operationLogFilters['keyword'] ?? ''),
    'date_from' => (string) ($operationLogFilters['date_from'] ?? ''),
    'date_to' => (string) ($operationLogFilters['date_to'] ?? ''),
);
?>
<section class="admin-card front-card">
    <h2 class="admin-card-title">后台操作日志</h2>
    <div class="admin-card-subtitle">这里记录后台对系统设置、用户、帖子、开奖、首页运营等模块的真实增删改查动作，所有关键链路都可以在这里追踪。</div>

    <form class="mt-4" method="get" action="<?php echo e(public_url('admin.php')); ?>">
        <input type="hidden" name="page" value="operation_logs">
        <input type="hidden" name="system_source" value="<?php echo e((string) ($systemLogFilters['source'] ?? '')); ?>">
        <input type="hidden" name="system_keyword" value="<?php echo e((string) ($systemLogFilters['keyword'] ?? '')); ?>">
        <input type="hidden" name="system_date_from" value="<?php echo e((string) ($systemLogFilters['date_from'] ?? '')); ?>">
        <input type="hidden" name="system_date_to" value="<?php echo e((string) ($systemLogFilters['date_to'] ?? '')); ?>">
        <input type="hidden" name="system_page_no" value="<?php echo e((string) ((int) ($systemLogPage['page_no'] ?? 1))); ?>">
        <div class="admin-filter-grid">
            <div>
                <label class="admin-label">所属模块</label>
                <input class="admin-input" name="module" value="<?php echo e((string) ($operationLogFilters['module'] ?? '')); ?>" placeholder="settings / users / posts">
            </div>
            <div>
                <label class="admin-label">关键词</label>
                <input class="admin-input" name="keyword" value="<?php echo e((string) ($operationLogFilters['keyword'] ?? '')); ?>" placeholder="操作摘要 / 路径 / 操作人">
            </div>
            <div>
                <label class="admin-label">开始日期</label>
                <input class="admin-input" type="date" name="date_from" value="<?php echo e((string) ($operationLogFilters['date_from'] ?? '')); ?>">
            </div>
            <div>
                <label class="admin-label">结束日期</label>
                <input class="admin-input" type="date" name="date_to" value="<?php echo e((string) ($operationLogFilters['date_to'] ?? '')); ?>">
            </div>
        </div>
        <div class="admin-form-actions">
            <button class="admin-button" type="submit">筛选日志</button>
            <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query($operationResetQuery)); ?>">重置条件</a>
        </div>
    </form>

    <?php if ($operationLogs): ?>
        <div class="admin-table-wrap mt-4">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>模块</th>
                    <th>动作</th>
                    <th>操作摘要</th>
                    <th>操作人</th>
                    <th>请求方式</th>
                    <th>请求路径</th>
                    <th>IP</th>
                    <th>时间</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($operationLogs as $log): ?>
                    <tr>
                        <td><?php echo e((string) $log['module']); ?></td>
                        <td><?php echo e((string) $log['action']); ?></td>
                        <td><?php echo e((string) ($log['summary'] ?: '-')); ?></td>
                        <td><?php echo e((string) (($log['username'] ?? '') ?: '-')); ?></td>
                        <td><?php echo e((string) ($log['request_method'] ?: '-')); ?></td>
                        <td><?php echo e((string) ($log['request_path'] ?: '-')); ?></td>
                        <td><?php echo e((string) ($log['ip'] ?: '-')); ?></td>
                        <td><?php echo e(format_datetime($log['created_at'] ?? null)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">
            <div class="admin-pagination-meta">共 <?php echo e((string) ($operationLogPage['total'] ?? 0)); ?> 条，第 <?php echo e((string) ($operationLogPage['page_no'] ?? 1)); ?> / <?php echo e((string) ($operationLogPage['page_count'] ?? 1)); ?> 页</div>
            <div class="admin-pagination-links">
                <?php $currentPage = (int) ($operationLogPage['page_no'] ?? 1); ?>
                <?php $pageCount = (int) ($operationLogPage['page_count'] ?? 1); ?>
                <?php if ($currentPage > 1): ?>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($operationQueryBase, array('page_no' => $currentPage - 1)))); ?>">上一页</a>
                <?php endif; ?>
                <?php if ($currentPage < $pageCount): ?>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($operationQueryBase, array('page_no' => $currentPage + 1)))); ?>">下一页</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="admin-empty mt-4">当前筛选条件下还没有后台操作日志。</div>
    <?php endif; ?>
</section>

<section class="admin-card front-card mt-4">
    <h2 class="admin-card-title">系统日志</h2>
    <div class="admin-card-subtitle">这里读取系统级日志来源和消息内容，接待端调分可通过来源 customer_service 或关键词筛选追踪。</div>

    <form class="mt-4" method="get" action="<?php echo e(public_url('admin.php')); ?>">
        <input type="hidden" name="page" value="operation_logs">
        <input type="hidden" name="module" value="<?php echo e((string) ($operationLogFilters['module'] ?? '')); ?>">
        <input type="hidden" name="keyword" value="<?php echo e((string) ($operationLogFilters['keyword'] ?? '')); ?>">
        <input type="hidden" name="date_from" value="<?php echo e((string) ($operationLogFilters['date_from'] ?? '')); ?>">
        <input type="hidden" name="date_to" value="<?php echo e((string) ($operationLogFilters['date_to'] ?? '')); ?>">
        <input type="hidden" name="page_no" value="<?php echo e((string) ((int) ($operationLogPage['page_no'] ?? 1))); ?>">
        <div class="admin-filter-grid">
            <div>
                <label class="admin-label">日志来源</label>
                <input class="admin-input" name="system_source" value="<?php echo e((string) ($systemLogFilters['source'] ?? '')); ?>" placeholder="customer_service / auth / post">
            </div>
            <div>
                <label class="admin-label">关键词</label>
                <input class="admin-input" name="system_keyword" value="<?php echo e((string) ($systemLogFilters['keyword'] ?? '')); ?>" placeholder="消息内容 / 上下文">
            </div>
            <div>
                <label class="admin-label">开始日期</label>
                <input class="admin-input" type="date" name="system_date_from" value="<?php echo e((string) ($systemLogFilters['date_from'] ?? '')); ?>">
            </div>
            <div>
                <label class="admin-label">结束日期</label>
                <input class="admin-input" type="date" name="system_date_to" value="<?php echo e((string) ($systemLogFilters['date_to'] ?? '')); ?>">
            </div>
        </div>
        <div class="admin-form-actions">
            <button class="admin-button" type="submit">筛选系统日志</button>
            <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query($systemResetQuery)); ?>">重置系统日志</a>
        </div>
    </form>

    <?php if ($systemLogs): ?>
        <div class="admin-table-wrap mt-4">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>来源</th>
                    <th>级别</th>
                    <th>消息</th>
                    <th>上下文</th>
                    <th>时间</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($systemLogs as $log): ?>
                    <?php $contextJson = trim((string) ($log['context_json'] ?? '')); ?>
                    <tr>
                        <td><?php echo e((string) ($log['source_name'] ?: '-')); ?></td>
                        <td><?php echo e((string) ($log['level_name'] ?: '-')); ?></td>
                        <td><?php echo e((string) ($log['message'] ?: '-')); ?></td>
                        <td>
                            <?php if ($contextJson !== ''): ?>
                                <details class="admin-log-detail">
                                    <summary>查看上下文</summary>
                                    <pre><?php echo e($contextJson); ?></pre>
                                </details>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                        <td><?php echo e(format_datetime($log['created_at'] ?? null)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">
            <div class="admin-pagination-meta">共 <?php echo e((string) ($systemLogPage['total'] ?? 0)); ?> 条，第 <?php echo e((string) ($systemLogPage['page_no'] ?? 1)); ?> / <?php echo e((string) ($systemLogPage['page_count'] ?? 1)); ?> 页</div>
            <div class="admin-pagination-links">
                <?php $currentPage = (int) ($systemLogPage['page_no'] ?? 1); ?>
                <?php $pageCount = (int) ($systemLogPage['page_count'] ?? 1); ?>
                <?php if ($currentPage > 1): ?>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($systemQueryBase, array('system_page_no' => $currentPage - 1)))); ?>">上一页</a>
                <?php endif; ?>
                <?php if ($currentPage < $pageCount): ?>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($systemQueryBase, array('system_page_no' => $currentPage + 1)))); ?>">下一页</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="admin-empty mt-4">当前筛选条件下还没有系统日志。</div>
    <?php endif; ?>
</section>
