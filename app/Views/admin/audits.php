<?php
$auditFilters = isset($auditFilters) && is_array($auditFilters) ? $auditFilters : array();
$pendingAuditTargets = isset($pendingAuditTargets) && is_array($pendingAuditTargets) ? $pendingAuditTargets : array();
$auditRecordPage = isset($auditRecordPage) && is_array($auditRecordPage) ? $auditRecordPage : array('items' => array(), 'total' => 0, 'page_no' => 1, 'page_count' => 1);
$auditRecords = isset($auditRecordPage['items']) && is_array($auditRecordPage['items']) ? $auditRecordPage['items'] : array();
$auditCanManage = !empty($auditCanManage);
$auditQueryBase = array(
    'page' => 'audits',
    'keyword' => (string) ($auditFilters['keyword'] ?? ''),
    'region' => (string) ($auditFilters['region'] ?? ''),
    'target_type' => (string) ($auditFilters['target_type'] ?? ''),
    'status' => (string) ($auditFilters['status'] ?? ''),
);
?>
<section class="admin-card front-card">
    <h2 class="admin-card-title">审核管理</h2>
    <div class="admin-card-subtitle">这里统一处理论坛帖子和评论审核。审核通过后会直接回写前台真实状态，不增加前台兼容分支。</div>

    <form class="mt-4" method="get" action="<?php echo e(public_url('admin.php')); ?>">
        <input type="hidden" name="page" value="audits">
        <div class="admin-filter-grid">
            <div>
                <label class="admin-label">关键词</label>
                <input class="admin-input" name="keyword" value="<?php echo e((string) ($auditFilters['keyword'] ?? '')); ?>" placeholder="标题 / 内容 / 审核备注">
            </div>
            <div>
                <label class="admin-label">分区</label>
                <select class="admin-select" name="region">
                    <option value="">全部分区</option>
                    <option value="macau" <?php echo (string) ($auditFilters['region'] ?? '') === 'macau' ? 'selected' : ''; ?>>澳门</option>
                    <option value="hongkong" <?php echo (string) ($auditFilters['region'] ?? '') === 'hongkong' ? 'selected' : ''; ?>>香港</option>
                </select>
            </div>
            <div>
                <label class="admin-label">目标类型</label>
                <select class="admin-select" name="target_type">
                    <option value="">全部类型</option>
                    <option value="post" <?php echo (string) ($auditFilters['target_type'] ?? '') === 'post' ? 'selected' : ''; ?>>帖子</option>
                    <option value="comment" <?php echo (string) ($auditFilters['target_type'] ?? '') === 'comment' ? 'selected' : ''; ?>>评论</option>
                </select>
            </div>
            <div>
                <label class="admin-label">审核结果</label>
                <select class="admin-select" name="status">
                    <option value="">全部结果</option>
                    <option value="pass" <?php echo (string) ($auditFilters['status'] ?? '') === 'pass' ? 'selected' : ''; ?>>通过</option>
                    <option value="reject" <?php echo (string) ($auditFilters['status'] ?? '') === 'reject' ? 'selected' : ''; ?>>驳回</option>
                </select>
            </div>
        </div>
        <div class="admin-form-actions">
            <button class="admin-button" type="submit">筛选审核记录</button>
            <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=audits'); ?>">重置条件</a>
        </div>
    </form>

    <div class="admin-section">
        <h3 class="admin-card-title">待审核内容</h3>
        <div class="admin-card-subtitle">当前待审核的帖子和评论都汇总在这里，可直接处理。</div>

        <?php if ($pendingAuditTargets): ?>
            <div class="admin-table-wrap mt-4">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>类型</th>
                        <th>标题</th>
                        <th>摘要</th>
                        <th>分区</th>
                        <th>提交人</th>
                        <th>提交时间</th>
                        <th>审核</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pendingAuditTargets as $row): ?>
                        <tr>
                            <td><?php echo (string) ($row['target_type'] ?? '') === 'comment' ? '评论' : '帖子'; ?></td>
                            <td><?php echo trim((string) ($row['display_title_html'] ?? '')) !== '' ? (string) $row['display_title_html'] : e((string) ($row['target_title'] ?? '-')); ?></td>
                            <td><?php echo e(truncate_text((string) ($row['target_excerpt'] ?? ''), 70)); ?></td>
                            <td><?php echo (string) ($row['region'] ?? 'macau') === 'hongkong' ? '香港' : '澳门'; ?></td>
                            <td><?php echo e((string) ($row['submitter_name'] ?? '-')); ?></td>
                            <td><?php echo e(format_datetime($row['created_at'] ?? null)); ?></td>
                            <td>
                                <?php if ($auditCanManage): ?>
                                    <div class="admin-inline-actions">
                                        <?php foreach (array('pass' => '通过', 'reject' => '驳回') as $actionValue => $actionLabel): ?>
                                            <form method="post" action="<?php echo e(public_url('admin.php') . '?page=audits'); ?>">
                                                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.audits')); ?>">
                                                <input type="hidden" name="_admin_form" value="page">
                                                <input type="hidden" name="_admin_action" value="process_audit">
                                                <input type="hidden" name="target_type" value="<?php echo e((string) ($row['target_type'] ?? 'post')); ?>">
                                                <input type="hidden" name="target_id" value="<?php echo e((string) ($row['target_id'] ?? 0)); ?>">
                                                <input type="hidden" name="review_action" value="<?php echo e($actionValue); ?>">
                                                <input class="admin-input is-compact" type="text" name="review_remark" placeholder="审核备注">
                                                <button class="admin-button is-light" type="submit"><?php echo e($actionLabel); ?></button>
                                            </form>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="admin-help">只读</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-empty mt-4">当前没有待审核内容。</div>
        <?php endif; ?>
    </div>

    <div class="admin-section">
        <h3 class="admin-card-title">审核历史</h3>
        <div class="admin-card-subtitle">这里保留已处理的帖子与评论审核记录，供后台追踪。</div>

        <?php if ($auditRecords): ?>
            <div class="admin-table-wrap mt-4">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>类型</th>
                        <th>目标标题</th>
                        <th>审核结果</th>
                        <th>审核备注</th>
                        <th>审核人</th>
                        <th>审核时间</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($auditRecords as $row): ?>
                        <tr>
                            <td><?php echo e((string) $row['id']); ?></td>
                            <td><?php echo (string) ($row['target_type'] ?? '') === 'comment' ? '评论' : '帖子'; ?></td>
                            <td>
                                <strong><?php echo trim((string) ($row['display_title_html'] ?? '')) !== '' ? (string) $row['display_title_html'] : e((string) (($row['target_title'] ?? '') ?: '-')); ?></strong>
                                <?php if (!empty($row['comment_content'])): ?>
                                    <div class="admin-help"><?php echo e(truncate_text((string) $row['comment_content'], 60)); ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php $status = (string) ($row['status'] ?? 'pending'); ?>
                                <span class="admin-badge <?php echo $status === 'pass' ? 'is-success' : ($status === 'reject' ? 'is-danger' : 'is-warning'); ?>">
                                    <?php echo e($status === 'pass' ? '通过' : ($status === 'reject' ? '驳回' : '待处理')); ?>
                                </span>
                            </td>
                            <td><?php echo e((string) (($row['audit_remark'] ?? '') ?: '-')); ?></td>
                            <td><?php echo e((string) (($row['auditor_name'] ?? '') ?: '-')); ?></td>
                            <td><?php echo e(format_datetime($row['audited_at'] ?? $row['created_at'] ?? null)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="admin-pagination">
                <div class="admin-pagination-meta">共 <?php echo e((string) ($auditRecordPage['total'] ?? 0)); ?> 条，第 <?php echo e((string) ($auditRecordPage['page_no'] ?? 1)); ?> / <?php echo e((string) ($auditRecordPage['page_count'] ?? 1)); ?> 页</div>
                <div class="admin-pagination-links">
                    <?php $currentPage = (int) ($auditRecordPage['page_no'] ?? 1); ?>
                    <?php $pageCount = (int) ($auditRecordPage['page_count'] ?? 1); ?>
                    <?php if ($currentPage > 1): ?>
                        <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($auditQueryBase, array('page_no' => $currentPage - 1)))); ?>">上一页</a>
                    <?php endif; ?>
                    <?php if ($currentPage < $pageCount): ?>
                        <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($auditQueryBase, array('page_no' => $currentPage + 1)))); ?>">下一页</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="admin-empty mt-4">当前没有审核历史记录。</div>
        <?php endif; ?>
    </div>
</section>
