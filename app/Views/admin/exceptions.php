<?php
$exceptionFilters = isset($exceptionFilters) && is_array($exceptionFilters) ? $exceptionFilters : array();
$exceptionLogPage = isset($exceptionLogPage) && is_array($exceptionLogPage) ? $exceptionLogPage : array('items' => array(), 'total' => 0, 'page_no' => 1, 'page_count' => 1);
$exceptionLogs = isset($exceptionLogPage['items']) && is_array($exceptionLogPage['items']) ? $exceptionLogPage['items'] : array();
$exceptionQueryBase = array(
    'page' => 'exceptions',
    'level' => (string) ($exceptionFilters['level'] ?? ''),
    'module' => (string) ($exceptionFilters['module'] ?? ''),
    'keyword' => (string) ($exceptionFilters['keyword'] ?? ''),
    'date_from' => (string) ($exceptionFilters['date_from'] ?? ''),
    'date_to' => (string) ($exceptionFilters['date_to'] ?? ''),
);
?>
<section class="admin-card front-card">
    <h2 class="admin-card-title">异常日志</h2>
    <div class="admin-card-subtitle">这里集中记录后台链路中的真实异常信息，包含模块、场景、请求路径和堆栈摘要，用于排查线上错误，不做假日志。</div>

    <form class="mt-4" method="get" action="<?php echo e(public_url('admin.php')); ?>">
        <input type="hidden" name="page" value="exceptions">
        <div class="admin-filter-grid">
            <div>
                <label class="admin-label">异常等级</label>
                <select class="admin-select" name="level">
                    <option value="">全部等级</option>
                    <option value="error" <?php echo (string) ($exceptionFilters['level'] ?? '') === 'error' ? 'selected' : ''; ?>>错误</option>
                    <option value="warning" <?php echo (string) ($exceptionFilters['level'] ?? '') === 'warning' ? 'selected' : ''; ?>>警告</option>
                </select>
            </div>
            <div>
                <label class="admin-label">所属模块</label>
                <input class="admin-input" name="module" value="<?php echo e((string) ($exceptionFilters['module'] ?? '')); ?>" placeholder="auth / admin / users">
            </div>
            <div>
                <label class="admin-label">关键词</label>
                <input class="admin-input" name="keyword" value="<?php echo e((string) ($exceptionFilters['keyword'] ?? '')); ?>" placeholder="错误信息 / 场景 / 路径">
            </div>
            <div>
                <label class="admin-label">开始日期</label>
                <input class="admin-input" type="date" name="date_from" value="<?php echo e((string) ($exceptionFilters['date_from'] ?? '')); ?>">
            </div>
        </div>

        <div class="admin-filter-grid mt-4">
            <div>
                <label class="admin-label">结束日期</label>
                <input class="admin-input" type="date" name="date_to" value="<?php echo e((string) ($exceptionFilters['date_to'] ?? '')); ?>">
            </div>
            <div></div>
            <div></div>
            <div></div>
        </div>

        <div class="admin-form-actions">
            <button class="admin-button" type="submit">筛选异常</button>
            <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=exceptions'); ?>">重置条件</a>
        </div>
    </form>

    <?php if ($exceptionLogs): ?>
        <div class="admin-table-wrap mt-4">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>等级</th>
                    <th>模块</th>
                    <th>场景</th>
                    <th>错误信息</th>
                    <th>操作人</th>
                    <th>请求路径</th>
                    <th>时间</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($exceptionLogs as $log): ?>
                    <tr>
                        <td>
                            <span class="admin-badge <?php echo (string) $log['level'] === 'warning' ? 'is-warning' : 'is-danger'; ?>">
                                <?php echo (string) $log['level'] === 'warning' ? '警告' : '错误'; ?>
                            </span>
                        </td>
                        <td><?php echo e((string) ($log['module'] ?: '-')); ?></td>
                        <td><?php echo e((string) ($log['scene'] ?: '-')); ?></td>
                        <td>
                            <div><?php echo e((string) ($log['message'] ?: '-')); ?></div>
                            <?php if (!empty($log['trace_excerpt'])): ?>
                                <details class="admin-log-detail">
                                    <summary>查看堆栈摘要</summary>
                                    <pre><?php echo e((string) $log['trace_excerpt']); ?></pre>
                                </details>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e((string) (($log['operator_type'] ?: 'system') . '#' . (int) ($log['operator_id'] ?? 0))); ?></td>
                        <td><?php echo e((string) ($log['request_path'] ?: '-')); ?></td>
                        <td><?php echo e(format_datetime($log['created_at'] ?? null)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">
            <div class="admin-pagination-meta">共 <?php echo e((string) ($exceptionLogPage['total'] ?? 0)); ?> 条，第 <?php echo e((string) ($exceptionLogPage['page_no'] ?? 1)); ?> / <?php echo e((string) ($exceptionLogPage['page_count'] ?? 1)); ?> 页</div>
            <div class="admin-pagination-links">
                <?php $currentPage = (int) ($exceptionLogPage['page_no'] ?? 1); ?>
                <?php $pageCount = (int) ($exceptionLogPage['page_count'] ?? 1); ?>
                <?php if ($currentPage > 1): ?>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($exceptionQueryBase, array('page_no' => $currentPage - 1)))); ?>">上一页</a>
                <?php endif; ?>
                <?php if ($currentPage < $pageCount): ?>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($exceptionQueryBase, array('page_no' => $currentPage + 1)))); ?>">下一页</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="admin-empty mt-4">当前还没有异常日志，系统运行正常时这里应保持较干净。</div>
    <?php endif; ?>
</section>
