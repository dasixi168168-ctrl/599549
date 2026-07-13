<?php
$interactionPage = isset($interactionPage) && is_array($interactionPage) ? $interactionPage : array('items' => array(), 'total' => 0, 'page_no' => 1, 'page_count' => 1);
$interactionStats = isset($interactionStats) && is_array($interactionStats) ? $interactionStats : array();
$interactionFilters = isset($interactionFilters) && is_array($interactionFilters) ? $interactionFilters : array();
$interactionForm = isset($interactionForm) && is_array($interactionForm) ? $interactionForm : array();
$interactionRows = isset($interactionPage['items']) && is_array($interactionPage['items']) ? $interactionPage['items'] : array();
$interactionTypeLabels = isset($interactionTypeLabels) && is_array($interactionTypeLabels) ? $interactionTypeLabels : array();
$interactionUserOptions = isset($interactionUserOptions) && is_array($interactionUserOptions) ? $interactionUserOptions : array();
$interactionPostOptions = isset($interactionPostOptions) && is_array($interactionPostOptions) ? $interactionPostOptions : array();
$interactionCanManage = !empty($interactionCanManage);
$interactionQueryBase = array(
    'page' => 'interactions',
    'keyword' => (string) ($interactionFilters['keyword'] ?? ''),
    'region' => (string) ($interactionFilters['region'] ?? ''),
    'interaction_type' => (string) ($interactionFilters['interaction_type'] ?? ''),
    'status' => (string) ($interactionFilters['status'] ?? ''),
    'post_id' => (string) ($interactionFilters['post_id'] ?? ''),
    'user_id' => (string) ($interactionFilters['user_id'] ?? ''),
);
?>
<section class="admin-split-grid admin-stack-layout">
    <div>
        <div class="admin-card front-card">
            <h2 class="admin-card-title">帖子互动管理</h2>
            <div class="admin-card-subtitle">这里统一维护帖子点赞和收藏记录，并提供当前筛选条件下的统计概览。所有改动都会真实写入 MySQL，直接影响论坛域互动统计。</div>

            <div class="admin-kv-list mt-4">
                <div class="admin-kv-item">
                    <div class="admin-kv-label">生效点赞</div>
                    <div class="admin-kv-value"><?php echo e((string) ((int) ($interactionStats['active_like_count'] ?? 0))); ?></div>
                </div>
                <div class="admin-kv-item">
                    <div class="admin-kv-label">生效收藏</div>
                    <div class="admin-kv-value"><?php echo e((string) ((int) ($interactionStats['active_favorite_count'] ?? 0))); ?></div>
                </div>
                <div class="admin-kv-item">
                    <div class="admin-kv-label">涉及帖子</div>
                    <div class="admin-kv-value"><?php echo e((string) ((int) ($interactionStats['post_count'] ?? 0))); ?></div>
                </div>
                <div class="admin-kv-item">
                    <div class="admin-kv-label">涉及会员</div>
                    <div class="admin-kv-value"><?php echo e((string) ((int) ($interactionStats['user_count'] ?? 0))); ?></div>
                </div>
            </div>

            <form class="mt-4" method="get" action="<?php echo e(public_url('admin.php')); ?>">
                <input type="hidden" name="page" value="interactions">
                <div class="admin-filter-grid">
                    <div>
                        <label class="admin-label">关键词</label>
                        <input class="admin-input" name="keyword" value="<?php echo e((string) ($interactionFilters['keyword'] ?? '')); ?>" placeholder="帖子标题 / 互动会员 / 帖子作者">
                    </div>
                    <div>
                        <label class="admin-label">分区</label>
                        <select class="admin-select" name="region">
                            <option value="">全部分区</option>
                            <option value="macau" <?php echo (string) ($interactionFilters['region'] ?? '') === 'macau' ? 'selected' : ''; ?>>澳门</option>
                            <option value="hongkong" <?php echo (string) ($interactionFilters['region'] ?? '') === 'hongkong' ? 'selected' : ''; ?>>香港</option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">互动类型</label>
                        <select class="admin-select" name="interaction_type">
                            <option value="">全部类型</option>
                            <?php foreach ($interactionTypeLabels as $typeCode => $typeLabel): ?>
                                <option value="<?php echo e((string) $typeCode); ?>" <?php echo (string) ($interactionFilters['interaction_type'] ?? '') === (string) $typeCode ? 'selected' : ''; ?>><?php echo e((string) $typeLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">状态</label>
                        <select class="admin-select" name="status">
                            <option value="">全部状态</option>
                            <option value="1" <?php echo (string) ($interactionFilters['status'] ?? '') === '1' ? 'selected' : ''; ?>>生效</option>
                            <option value="0" <?php echo (string) ($interactionFilters['status'] ?? '') === '0' ? 'selected' : ''; ?>>已取消</option>
                        </select>
                    </div>
                </div>

                <div class="admin-filter-grid mt-4">
                    <div>
                        <label class="admin-label">帖子 ID</label>
                        <input class="admin-input" type="number" name="post_id" value="<?php echo e((string) ($interactionFilters['post_id'] ?? '')); ?>" min="0">
                    </div>
                    <div>
                        <label class="admin-label">会员 ID</label>
                        <input class="admin-input" type="number" name="user_id" value="<?php echo e((string) ($interactionFilters['user_id'] ?? '')); ?>" min="0">
                    </div>
                    <div></div>
                    <div></div>
                </div>

                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">筛选互动</button>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=interactions'); ?>">重置条件</a>
                </div>
            </form>

            <?php if ($interactionRows): ?>
                <div class="admin-table-wrap mt-4">
                    <table class="admin-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>帖子</th>
                            <th>互动会员</th>
                            <th>帖子作者</th>
                            <th>分区</th>
                            <th>类型</th>
                            <th>状态</th>
                            <th>时间</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($interactionRows as $row): ?>
                            <tr>
                                <td><?php echo e((string) $row['id']); ?></td>
                                <td>
                                    <strong><?php echo trim((string) ($row['display_title_html'] ?? '')) !== '' ? (string) $row['display_title_html'] : e((string) (($row['post_title'] ?? '') ?: '-')); ?></strong>
                                    <div class="admin-help">帖子 ID：<?php echo e((string) ($row['post_id'] ?? 0)); ?></div>
                                </td>
                                <td><?php echo e((string) (($row['interaction_username'] ?? '') ?: '-')); ?></td>
                                <td><?php echo e((string) (($row['author_name'] ?? '') ?: '-')); ?></td>
                                <td><?php echo (string) ($row['region'] ?? 'macau') === 'hongkong' ? '香港' : '澳门'; ?></td>
                                <td><?php echo e((string) ($interactionTypeLabels[(string) ($row['interaction_type'] ?? '')] ?? (string) ($row['interaction_type'] ?? '-'))); ?></td>
                                <td><span class="admin-badge <?php echo (int) ($row['status'] ?? 0) === 1 ? 'is-success' : 'is-danger'; ?>"><?php echo (int) ($row['status'] ?? 0) === 1 ? '生效' : '已取消'; ?></span></td>
                                <td><?php echo e(format_datetime($row['created_at'] ?? null)); ?></td>
                                <td>
                                    <?php if ($interactionCanManage): ?>
                                        <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=interactions&edit=' . (int) $row['id']); ?>">编辑</a>
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
                    <div class="admin-pagination-meta">共 <?php echo e((string) ($interactionPage['total'] ?? 0)); ?> 条，第 <?php echo e((string) ($interactionPage['page_no'] ?? 1)); ?> / <?php echo e((string) ($interactionPage['page_count'] ?? 1)); ?> 页</div>
                    <div class="admin-pagination-links">
                        <?php $currentPage = (int) ($interactionPage['page_no'] ?? 1); ?>
                        <?php $pageCount = (int) ($interactionPage['page_count'] ?? 1); ?>
                        <?php if ($currentPage > 1): ?>
                            <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($interactionQueryBase, array('page_no' => $currentPage - 1)))); ?>">上一页</a>
                        <?php endif; ?>
                        <?php if ($currentPage < $pageCount): ?>
                            <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($interactionQueryBase, array('page_no' => $currentPage + 1)))); ?>">下一页</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="admin-empty mt-4">当前条件下没有帖子互动记录。</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-card front-card">
        <h2 class="admin-card-title"><?php echo !empty($interactionForm['id']) ? '编辑互动记录' : '新增互动记录'; ?></h2>
        <div class="admin-card-subtitle">这里维护帖子点赞与收藏记录。保存后会直接写入互动表，统计卡片和后续论坛运营看板都会使用同一套数据。</div>

        <?php if ($interactionCanManage): ?>
            <form method="post" action="<?php echo e(public_url('admin.php') . '?page=interactions'); ?>" class="mt-4">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.interactions')); ?>">
                <input type="hidden" name="_admin_form" value="page">
                <input type="hidden" name="_admin_action" value="save_interaction">
                <input type="hidden" name="id" value="<?php echo e((string) ($interactionForm['id'] ?? 0)); ?>">

                <div class="mt-4">
                    <label class="admin-label">关联帖子</label>
                    <select class="admin-select" name="post_id">
                        <option value="0">请选择帖子</option>
                        <?php foreach ($interactionPostOptions as $option): ?>
                            <option value="<?php echo e((string) $option['id']); ?>" <?php echo (int) ($interactionForm['post_id'] ?? 0) === (int) $option['id'] ? 'selected' : ''; ?>><?php echo e((string) (($option['display_title_text'] ?? '') ?: $option['title'])); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mt-4">
                    <label class="admin-label">互动会员</label>
                    <select class="admin-select" name="user_id">
                        <option value="0">请选择会员</option>
                        <?php foreach ($interactionUserOptions as $option): ?>
                            <option value="<?php echo e((string) $option['id']); ?>" <?php echo (int) ($interactionForm['user_id'] ?? 0) === (int) $option['id'] ? 'selected' : ''; ?>><?php echo e((string) $option['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mt-4">
                    <label class="admin-label">互动类型</label>
                    <select class="admin-select" name="interaction_type">
                        <?php foreach ($interactionTypeLabels as $typeCode => $typeLabel): ?>
                            <option value="<?php echo e((string) $typeCode); ?>" <?php echo (string) ($interactionForm['interaction_type'] ?? 'like') === (string) $typeCode ? 'selected' : ''; ?>><?php echo e((string) $typeLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mt-4">
                    <label class="admin-check-item">
                        <input type="checkbox" name="status" value="1" <?php echo (string) ($interactionForm['status'] ?? '1') === '1' ? 'checked' : ''; ?>>
                        <span>设为生效状态</span>
                    </label>
                </div>

                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">保存互动</button>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=interactions'); ?>">新建空白表单</a>
                </div>
            </form>
        <?php else: ?>
            <div class="admin-empty mt-4">当前账号只有查看权限，不能直接维护帖子互动记录。</div>
        <?php endif; ?>
    </div>
</section>
