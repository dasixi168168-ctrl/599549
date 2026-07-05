<?php
$commentPage = isset($commentPage) && is_array($commentPage) ? $commentPage : array('items' => array(), 'total' => 0, 'page_no' => 1, 'page_count' => 1);
$commentFilters = isset($commentFilters) && is_array($commentFilters) ? $commentFilters : array();
$comments = isset($commentPage['items']) && is_array($commentPage['items']) ? $commentPage['items'] : array();
$commentCanManage = !empty($commentCanManage);
$commentQueryBase = array(
    'page' => 'comments',
    'keyword' => (string) ($commentFilters['keyword'] ?? ''),
    'region' => (string) ($commentFilters['region'] ?? ''),
    'status' => (string) ($commentFilters['status'] ?? ''),
    'post_id' => (string) ($commentFilters['post_id'] ?? ''),
);
?>
<section class="admin-card front-card">
    <h2 class="admin-card-title">评论管理</h2>
    <div class="admin-card-subtitle">这里直接管理前台真实评论和回复，可切换已发布、待审核、已隐藏三种状态，并同步回写帖子回复数。</div>

    <form class="mt-4" method="get" action="<?php echo e(public_url('admin.php')); ?>">
        <input type="hidden" name="page" value="comments">
        <div class="admin-filter-grid">
            <div>
                <label class="admin-label">关键词</label>
                <input class="admin-input" name="keyword" value="<?php echo e((string) ($commentFilters['keyword'] ?? '')); ?>" placeholder="评论内容 / 帖子标题 / 评论用户">
            </div>
            <div>
                <label class="admin-label">分区</label>
                <select class="admin-select" name="region">
                    <option value="">全部分区</option>
                    <option value="macau" <?php echo (string) ($commentFilters['region'] ?? '') === 'macau' ? 'selected' : ''; ?>>澳门</option>
                    <option value="hongkong" <?php echo (string) ($commentFilters['region'] ?? '') === 'hongkong' ? 'selected' : ''; ?>>香港</option>
                </select>
            </div>
            <div>
                <label class="admin-label">状态</label>
                <select class="admin-select" name="status">
                    <option value="">全部状态</option>
                    <option value="published" <?php echo (string) ($commentFilters['status'] ?? '') === 'published' ? 'selected' : ''; ?>>已发布</option>
                    <option value="pending" <?php echo (string) ($commentFilters['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>待审核</option>
                    <option value="hidden" <?php echo (string) ($commentFilters['status'] ?? '') === 'hidden' ? 'selected' : ''; ?>>已隐藏</option>
                </select>
            </div>
            <div>
                <label class="admin-label">帖子ID</label>
                <input class="admin-input" type="number" name="post_id" value="<?php echo e((string) ($commentFilters['post_id'] ?? '')); ?>" min="0">
            </div>
        </div>
        <div class="admin-form-actions">
            <button class="admin-button" type="submit">筛选评论</button>
            <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=comments'); ?>">重置条件</a>
        </div>
    </form>

    <?php if ($comments): ?>
        <div class="admin-table-wrap mt-4">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>帖子</th>
                    <th>评论用户</th>
                    <th>分区</th>
                    <th>内容</th>
                    <th>状态</th>
                    <th>时间</th>
                    <th>处理</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($comments as $row): ?>
                    <tr>
                        <td><?php echo e((string) $row['id']); ?></td>
                        <td><?php echo e((string) ($row['post_title'] ?? '-')); ?></td>
                        <td><?php echo e((string) ($row['username'] ?? '-')); ?></td>
                        <td><?php echo (string) ($row['region'] ?? 'macau') === 'hongkong' ? '香港' : '澳门'; ?></td>
                        <td><?php echo e(truncate_text((string) ($row['content'] ?? ''), 70)); ?></td>
                        <td>
                            <?php $status = (string) ($row['status'] ?? 'published'); ?>
                            <span class="admin-badge <?php echo $status === 'published' ? 'is-success' : ($status === 'pending' ? 'is-warning' : 'is-danger'); ?>">
                                <?php echo e($status === 'published' ? '已发布' : ($status === 'pending' ? '待审核' : '已隐藏')); ?>
                            </span>
                        </td>
                        <td><?php echo e(format_datetime($row['created_at'] ?? null)); ?></td>
                        <td>
                            <?php if ($commentCanManage): ?>
                                <div class="admin-inline-actions">
                                    <?php foreach (array('published' => '发布', 'pending' => '待审', 'hidden' => '隐藏') as $statusValue => $statusLabel): ?>
                                        <form method="post" action="<?php echo e(public_url('admin.php') . '?page=comments'); ?>">
                                            <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.comments')); ?>">
                                            <input type="hidden" name="_admin_form" value="page">
                                            <input type="hidden" name="_admin_action" value="save_comment_status">
                                            <input type="hidden" name="comment_id" value="<?php echo e((string) $row['id']); ?>">
                                            <input type="hidden" name="comment_status" value="<?php echo e($statusValue); ?>">
                                            <button class="admin-button is-light" type="submit"><?php echo e($statusLabel); ?></button>
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

        <div class="admin-pagination">
            <div class="admin-pagination-meta">共 <?php echo e((string) ($commentPage['total'] ?? 0)); ?> 条，第 <?php echo e((string) ($commentPage['page_no'] ?? 1)); ?> / <?php echo e((string) ($commentPage['page_count'] ?? 1)); ?> 页</div>
            <div class="admin-pagination-links">
                <?php $currentPage = (int) ($commentPage['page_no'] ?? 1); ?>
                <?php $pageCount = (int) ($commentPage['page_count'] ?? 1); ?>
                <?php if ($currentPage > 1): ?>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($commentQueryBase, array('page_no' => $currentPage - 1)))); ?>">上一页</a>
                <?php endif; ?>
                <?php if ($currentPage < $pageCount): ?>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($commentQueryBase, array('page_no' => $currentPage + 1)))); ?>">下一页</a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <div class="admin-empty mt-4">当前筛选条件下没有评论数据。</div>
    <?php endif; ?>
</section>
