<?php
$operationLogFilters = isset($operationLogFilters) && is_array($operationLogFilters) ? $operationLogFilters : array();
$operationLogPage = isset($operationLogPage) && is_array($operationLogPage) ? $operationLogPage : array('items' => array(), 'total' => 0, 'page_no' => 1, 'page_count' => 1);
$operationLogs = isset($operationLogPage['items']) && is_array($operationLogPage['items']) ? $operationLogPage['items'] : array();
$operationQueryBase = array(
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
            <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=operation_logs'); ?>">重置条件</a>
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
