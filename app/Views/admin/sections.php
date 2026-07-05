<?php
$sections = isset($sections) && is_array($sections) ? $sections : array();
$sectionFilters = isset($sectionFilters) && is_array($sectionFilters) ? $sectionFilters : array();
$sectionForm = isset($sectionForm) && is_array($sectionForm) ? $sectionForm : array();
$sectionCanManage = !empty($sectionCanManage);
?>
<section class="admin-split-grid admin-stack-layout">
    <div>
        <div class="admin-card front-card">
            <h2 class="admin-card-title">版块列表</h2>
            <div class="admin-card-subtitle">这里维护论坛版块。帖子会按版块和分区归类，不改变前台原有帖子结构和展示规则。</div>

            <form class="mt-4" method="get" action="<?php echo e(public_url('admin.php')); ?>">
                <input type="hidden" name="page" value="sections">
                <div class="admin-filter-grid">
                    <div>
                        <label class="admin-label">关键词</label>
                        <input class="admin-input" name="keyword" value="<?php echo e((string) ($sectionFilters['keyword'] ?? '')); ?>" placeholder="版块名称 / 编码 / 描述">
                    </div>
                    <div>
                        <label class="admin-label">分区</label>
                        <select class="admin-select" name="region">
                            <option value="">全部分区</option>
                            <option value="macau" <?php echo (string) ($sectionFilters['region'] ?? '') === 'macau' ? 'selected' : ''; ?>>澳门</option>
                            <option value="hongkong" <?php echo (string) ($sectionFilters['region'] ?? '') === 'hongkong' ? 'selected' : ''; ?>>香港</option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">状态</label>
                        <select class="admin-select" name="status">
                            <option value="">全部状态</option>
                            <option value="1" <?php echo (string) ($sectionFilters['status'] ?? '') === '1' ? 'selected' : ''; ?>>启用</option>
                            <option value="0" <?php echo (string) ($sectionFilters['status'] ?? '') === '0' ? 'selected' : ''; ?>>停用</option>
                        </select>
                    </div>
                    <div></div>
                </div>
                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">筛选版块</button>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=sections'); ?>">重置条件</a>
                </div>
            </form>

            <?php if ($sections): ?>
                <div class="admin-table-wrap mt-4">
                    <table class="admin-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>版块名称</th>
                            <th>编码</th>
                            <th>分区</th>
                            <th>帖子数</th>
                            <th>排序</th>
                            <th>状态</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($sections as $row): ?>
                            <tr>
                                <td><?php echo e((string) $row['id']); ?></td>
                                <td>
                                    <strong><?php echo e((string) $row['name']); ?></strong>
                                    <div class="admin-help"><?php echo e((string) (($row['description'] ?? '') ?: '-')); ?></div>
                                </td>
                                <td><?php echo e((string) $row['code']); ?></td>
                                <td><?php echo (string) ($row['region'] ?? 'macau') === 'hongkong' ? '香港' : '澳门'; ?></td>
                                <td><?php echo e((string) ($row['post_total'] ?? 0)); ?></td>
                                <td><?php echo e((string) ($row['sort_order'] ?? 0)); ?></td>
                                <td><span class="admin-badge <?php echo (int) ($row['status'] ?? 0) === 1 ? 'is-success' : 'is-danger'; ?>"><?php echo (int) ($row['status'] ?? 0) === 1 ? '启用' : '停用'; ?></span></td>
                                <td><a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=sections&edit=' . (int) $row['id']); ?>">编辑</a></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="admin-empty mt-4">当前还没有版块数据。</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-card front-card">
        <h2 class="admin-card-title"><?php echo !empty($sectionForm['id']) ? '编辑版块' : '新增版块'; ?></h2>
        <div class="admin-card-subtitle">版块保存后会直接影响帖子归类、后台筛选和分类挂载关系。</div>
        <?php if ($sectionCanManage): ?>
            <form method="post" action="<?php echo e(public_url('admin.php') . '?page=sections'); ?>" class="mt-4">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.sections')); ?>">
                <input type="hidden" name="_admin_form" value="page">
                <input type="hidden" name="_admin_action" value="save_section">
                <input type="hidden" name="id" value="<?php echo e((string) ($sectionForm['id'] ?? 0)); ?>">

                <div class="admin-form-grid">
                    <div>
                        <label class="admin-label">分区</label>
                        <select class="admin-select" name="region">
                            <option value="macau" <?php echo (string) ($sectionForm['region'] ?? 'macau') === 'macau' ? 'selected' : ''; ?>>澳门</option>
                            <option value="hongkong" <?php echo (string) ($sectionForm['region'] ?? 'macau') === 'hongkong' ? 'selected' : ''; ?>>香港</option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">排序</label>
                        <input class="admin-input" type="number" name="sort_order" value="<?php echo e((string) ($sectionForm['sort_order'] ?? '0')); ?>" step="1">
                    </div>
                </div>
                <div class="mt-4">
                    <label class="admin-label">版块名称</label>
                    <input class="admin-input" name="name" value="<?php echo e((string) ($sectionForm['name'] ?? '')); ?>">
                </div>
                <div class="mt-4">
                    <label class="admin-label">版块编码</label>
                    <input class="admin-input" name="code" value="<?php echo e((string) ($sectionForm['code'] ?? '')); ?>" placeholder="macau_forum">
                </div>
                <div class="mt-4">
                    <label class="admin-label">版块描述</label>
                    <textarea class="admin-textarea" name="description"><?php echo e((string) ($sectionForm['description'] ?? '')); ?></textarea>
                </div>
                <div class="mt-4">
                    <label class="admin-label">图标文本</label>
                    <input class="admin-input" name="icon" value="<?php echo e((string) ($sectionForm['icon'] ?? '')); ?>" placeholder="论坛 / 热门 / 资料">
                </div>
                <div class="mt-4">
                    <label class="admin-label">发帖规则说明</label>
                    <textarea class="admin-textarea" name="post_rule"><?php echo e((string) ($sectionForm['post_rule'] ?? '')); ?></textarea>
                </div>
                <div class="mt-4">
                    <label class="admin-check-item">
                        <input type="checkbox" name="status" value="1" <?php echo (string) ($sectionForm['status'] ?? '1') === '1' ? 'checked' : ''; ?>>
                        <span>启用版块</span>
                    </label>
                </div>
                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">保存版块</button>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=sections'); ?>">新建空白表单</a>
                </div>
            </form>
        <?php else: ?>
            <div class="admin-empty mt-4">当前账号只有查看权限，不能直接维护版块。</div>
        <?php endif; ?>
    </div>
</section>
