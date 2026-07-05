<?php
$loginLogFilters = isset($loginLogFilters) && is_array($loginLogFilters) ? $loginLogFilters : array();
$loginLogPage = isset($loginLogPage) && is_array($loginLogPage) ? $loginLogPage : array('items' => array(), 'total' => 0, 'page_no' => 1, 'page_count' => 1);
$loginLogs = isset($loginLogPage['items']) && is_array($loginLogPage['items']) ? $loginLogPage['items'] : array();
$loginQueryBase = array(
    'page' => 'login_logs',
    'keyword' => (string) ($loginLogFilters['keyword'] ?? ''),
    'status' => (string) ($loginLogFilters['status'] ?? ''),
    'device' => (string) ($loginLogFilters['device'] ?? ''),
    'date_from' => (string) ($loginLogFilters['date_from'] ?? ''),
    'date_to' => (string) ($loginLogFilters['date_to'] ?? ''),
);
?>
<section class="admin-card front-card">
    <h2 class="admin-card-title">后台登录日志</h2>
    <div class="admin-card-subtitle">这里记录新后台账号的真实登录结果，包括成功、失败、IP、设备和失败原因，便于排查后台登录问题和风控异常。</div>

    <form class="mt-4" method="get" action="<?php echo e(public_url('admin.php')); ?>">
        <input type="hidden" name="page" value="login_logs">
        <div class="admin-filter-grid">
            <div>
                <label class="admin-label">关键词</label>
                <input class="admin-input" name="keyword" value="<?php echo e((string) ($loginLogFilters['keyword'] ?? '')); ?>" placeholder="账号 / IP / 失败原因">
            </div>
            <div>
                <label class="admin-label">登录状态</label>
                <select class="admin-select" name="status">
                    <option value="">全部状态</option>
                    <option value="1" <?php echo (string) ($loginLogFilters['status'] ?? '') === '1' ? 'selected' : ''; ?>>成功</option>
                    <option value="0" <?php echo (string) ($loginLogFilters['status'] ?? '') === '0' ? 'selected' : ''; ?>>失败</option>
                </select>
            </div>
            <div>
                <label class="admin-label">登录设备</label>
                <select class="admin-select" name="device">
                    <option value="">全部设备</option>
                    <option value="desktop" <?php echo (string) ($loginLogFilters['device'] ?? '') === 'desktop' ? 'selected' : ''; ?>>桌面端</option>
                    <option value="mobile" <?php echo (string) ($loginLogFilters['device'] ?? '') === 'mobile' ? 'selected' : ''; ?>>手机端</option>
                </select>
            </div>
            <div>
                <label class="admin-label">开始日期</label>
                <input class="admin-input" type="date" name="date_from" value="<?php echo e((string) ($loginLogFilters['date_from'] ?? '')); ?>">
            </div>
        </div>

        <div class="admin-filter-grid mt-4">
            <div>
                <label class="admin-label">结束日期</label>
                <input class="admin-input" type="date" name="date_to" value="<?php echo e((string) ($loginLogFilters['date_to'] ?? '')); ?>">
            </div>
            <div></div>
            <div></div>
            <div></div>
        </div>

        <div class="admin-form-actions">
            <button class="admin-button" type="submit">筛选日志</button>
            <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=login_logs'); ?>">重置条件</a>
        </div>
    </form>

    <?php if ($loginLogs): ?>
        <div class="admin-table-wrap mt-4">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>账号</th>
                    <th>状态</th>
                    <th>IP</th>
                    <th>地区</th>
                    <th>设备</th>
                    <th>失败原因</th>
                    <th>时间</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($loginLogs as $log): ?>
                    <tr>
                        <td><?php echo e((string) $log['username']); ?></td>
                        <td>
                            <span class="admin-badge <?php echo (int) $log['status'] === 1 ? 'is-success' : 'is-danger'; ?>">
                                <?php echo (int) $log['status'] === 1 ? '成功' : '失败'; ?>
                            </span>
                        </td>
                        <td><?php echo e((string) $log['ip']); ?></td>
                        <td><?php echo e((string) ($log['area'] ?: '-')); ?></td>
                        <td><?php echo e((string) ($log['device'] ?: '-')); ?></td>
                        <td><?php echo e((string) ($log['fail_reason'] ?: '-')); ?></td>
                        <td><?php echo e(format_datetime($log['login_at'] ?? null)); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="admin-pagination">
            <div class="admin-pagination-meta">共 <?php echo e((string) ($loginLogPage['total'] ?? 0)); ?> 条，第 <?php echo e((string) ($loginLogPage['page_no'] ?? 1)); ?> / <?php echo e((string) ($loginLogPage['page_count'] ?? 1)); ?> 页</div>
            <div class="admin-pagination-links">
                <?php $currentPage = (int) ($loginLogPage['page_no'] ?? 1); ?>
                <?php $pageCount = (int) ($loginLogPage['page_count'] ?? 1); ?>
                <?php if ($currentPage > 1): ?>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($loginQueryBase, array('page_no' => $currentPage - 1)))); ?>">上一页</a>
                <?php endif; ?>
                <?php if ($currentPage < $pageCount): ?>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($loginQueryBase, array('page_no' => $currentPage + 1)))); ?>">下一页</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="admin-empty mt-4">当前筛选条件下还没有后台登录日志。</div>
    <?php endif; ?>
</section>
