<?php
$categories = isset($categories) && is_array($categories) ? $categories : array();
$categoryFilters = isset($categoryFilters) && is_array($categoryFilters) ? $categoryFilters : array();
$categorySectionOptions = isset($categorySectionOptions) && is_array($categorySectionOptions) ? $categorySectionOptions : array();
$categoryForm = isset($categoryForm) && is_array($categoryForm) ? $categoryForm : array();
$categoryCanManage = !empty($categoryCanManage);
?>
<section class="admin-split-grid admin-stack-layout">
    <div>
        <div class="admin-card front-card">
            <h2 class="admin-card-title">分类列表</h2>
            <div class="admin-card-subtitle">这里维护论坛帖子分类。分类必须挂在已存在的版块下，前台帖子仍按现有结构读取。</div>

            <form class="mt-4" method="get" action="<?php echo e(public_url('admin.php')); ?>">
                <input type="hidden" name="page" value="categories">
                <div class="admin-filter-grid">
                    <div>
                        <label class="admin-label">关键词</label>
                        <input class="admin-input" name="keyword" value="<?php echo e((string) ($categoryFilters['keyword'] ?? '')); ?>" placeholder="分类名称 / 编码 / 描述">
                    </div>
                    <div>
                        <label class="admin-label">分区</label>
                        <select class="admin-select" name="region">
                            <option value="">全部分区</option>
                            <option value="macau" <?php echo (string) ($categoryFilters['region'] ?? '') === 'macau' ? 'selected' : ''; ?>>澳门</option>
                            <option value="hongkong" <?php echo (string) ($categoryFilters['region'] ?? '') === 'hongkong' ? 'selected' : ''; ?>>香港</option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">所属版块</label>
                        <select class="admin-select" name="section_id">
                            <option value="0">全部版块</option>
                            <?php foreach ($categorySectionOptions as $option): ?>
                                <option value="<?php echo e((string) $option['id']); ?>" <?php echo (int) ($categoryFilters['section_id'] ?? 0) === (int) $option['id'] ? 'selected' : ''; ?>><?php echo e((string) $option['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">状态</label>
                        <select class="admin-select" name="status">
                            <option value="">全部状态</option>
                            <option value="1" <?php echo (string) ($categoryFilters['status'] ?? '') === '1' ? 'selected' : ''; ?>>启用</option>
                            <option value="0" <?php echo (string) ($categoryFilters['status'] ?? '') === '0' ? 'selected' : ''; ?>>停用</option>
                        </select>
                    </div>
                </div>
                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">筛选分类</button>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=categories'); ?>">重置条件</a>
                </div>
            </form>

            <?php if ($categories): ?>
                <div class="admin-table-wrap mt-4">
                    <table class="admin-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>分类名称</th>
                            <th>所属版块</th>
                            <th>编码</th>
                            <th>分区</th>
                            <th>帖子数</th>
                            <th>排序</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($categories as $row): ?>
                            <tr>
                                <td><?php echo e((string) $row['id']); ?></td>
                                <td>
                                    <strong><?php echo e((string) $row['name']); ?></strong>
                                    <div class="admin-help"><?php echo e((string) (($row['description'] ?? '') ?: '-')); ?></div>
                                </td>
                                <td><?php echo e((string) (($row['section_name'] ?? '') ?: '-')); ?></td>
                                <td><?php echo e((string) $row['code']); ?></td>
                                <td><?php echo (string) ($row['region'] ?? 'macau') === 'hongkong' ? '香港' : '澳门'; ?></td>
                                <td><?php echo e((string) ($row['post_total'] ?? 0)); ?></td>
                                <td><?php echo e((string) ($row['sort_order'] ?? 0)); ?></td>
                                <td><span class="admin-badge <?php echo (int) ($row['status'] ?? 0) === 1 ? 'is-success' : 'is-danger'; ?>"><?php echo (int) ($row['status'] ?? 0) === 1 ? '启用' : '停用'; ?></span></td>
                                <td><a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=categories&edit=' . (int) $row['id']); ?>">编辑</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="admin-empty mt-4">当前还没有分类数据。</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-card front-card">
        <h2 class="admin-card-title"><?php echo !empty($categoryForm['id']) ? '编辑分类' : '新增分类'; ?></h2>
        <div class="admin-card-subtitle">分类保存后会直接参与帖子筛选和后台帖子表单归类。</div>
        <?php if ($categoryCanManage): ?>
            <form method="post" action="<?php echo e(public_url('admin.php') . '?page=categories'); ?>" class="mt-4">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.categories')); ?>">
                <input type="hidden" name="_admin_form" value="page">
                <input type="hidden" name="_admin_action" value="save_category">
                <input type="hidden" name="id" value="<?php echo e((string) ($categoryForm['id'] ?? 0)); ?>">

                <div class="admin-form-grid">
                    <div>
                        <label class="admin-label">分区</label>
                        <select class="admin-select" name="region">
                            <option value="macau" <?php echo (string) ($categoryForm['region'] ?? 'macau') === 'macau' ? 'selected' : ''; ?>>澳门</option>
                            <option value="hongkong" <?php echo (string) ($categoryForm['region'] ?? 'macau') === 'hongkong' ? 'selected' : ''; ?>>香港</option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">所属版块</label>
                        <select class="admin-select" name="section_id">
                            <option value="0">请选择版块</option>
                            <?php foreach ($categorySectionOptions as $option): ?>
                                <option value="<?php echo e((string) $option['id']); ?>" <?php echo (int) ($categoryForm['section_id'] ?? 0) === (int) $option['id'] ? 'selected' : ''; ?>><?php echo e((string) $option['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="admin-label">分类名称</label>
                    <input class="admin-input" name="name" value="<?php echo e((string) ($categoryForm['name'] ?? '')); ?>">
                </div>
                <div class="mt-4">
                    <label class="admin-label">分类编码</label>
                    <input class="admin-input" name="code" value="<?php echo e((string) ($categoryForm['code'] ?? '')); ?>" placeholder="macau_general">
                </div>
                <div class="mt-4">
                    <label class="admin-label">分类描述</label>
                    <textarea class="admin-textarea" name="description"><?php echo e((string) ($categoryForm['description'] ?? '')); ?></textarea>
                </div>
                <div class="admin-form-grid mt-4">
                    <div>
                        <label class="admin-label">排序</label>
                        <input class="admin-input" type="number" name="sort_order" value="<?php echo e((string) ($categoryForm['sort_order'] ?? '0')); ?>" step="1">
                    </div>
                    <div class="admin-check-row">
                        <label class="admin-check-item">
                            <input type="checkbox" name="status" value="1" <?php echo (string) ($categoryForm['status'] ?? '1') === '1' ? 'checked' : ''; ?>>
                            <span>启用分类</span>
                        </label>
                    </div>
                </div>
                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">保存分类</button>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=categories'); ?>">新建空白表单</a>
                </div>
            </form>
        <?php else: ?>
            <div class="admin-empty mt-4">当前账号只有查看权限，不能直接维护分类。</div>
        <?php endif; ?>
    </div>
</section>
