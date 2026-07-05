<?php
$roles = isset($roles) && is_array($roles) ? $roles : array();
$permissions = isset($permissions) && is_array($permissions) ? $permissions : array();
$roleForm = isset($roleForm) && is_array($roleForm) ? $roleForm : array();
$roleCanManage = !empty($roleCanManage);
?>
<section class="admin-split-grid admin-stack-layout">
    <div class="admin-card front-card">
        <h2 class="admin-card-title">角色列表</h2>
        <div class="admin-card-subtitle">第一阶段先接通角色基础信息管理，菜单授权和权限树分配将在后续迭代继续接入。</div>

        <?php if ($roles): ?>
            <div class="admin-table-wrap mt-4">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>角色名称</th>
                        <th>角色编码</th>
                        <th>数据范围</th>
                        <th>状态</th>
                        <th>排序</th>
                        <th>操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($roles as $role): ?>
                        <tr>
                            <td><?php echo e((string) $role['id']); ?></td>
                            <td><?php echo e((string) $role['name']); ?></td>
                            <td><?php echo e((string) $role['code']); ?></td>
                            <td><?php echo e((string) $role['data_scope']); ?></td>
                            <td>
                                <span class="admin-badge <?php echo (int) $role['status'] === 1 ? 'is-success' : 'is-danger'; ?>">
                                    <?php echo (int) $role['status'] === 1 ? '启用' : '停用'; ?>
                                </span>
                            </td>
                            <td><?php echo e((string) $role['sort_order']); ?></td>
                            <td><a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=roles&edit=' . (int) $role['id']); ?>">编辑</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-empty mt-4">当前还没有后台角色数据。</div>
        <?php endif; ?>

        <div class="admin-section">
            <div class="admin-card-title">当前权限节点</div>
            <div class="admin-card-subtitle">已初始化 <?php echo e((string) count($permissions)); ?> 个后台权限节点，下一轮将接入角色权限树和菜单授权页。</div>
        </div>
    </div>

    <div class="admin-card front-card">
        <h2 class="admin-card-title"><?php echo !empty($roleForm['id']) ? '编辑角色' : '新增角色'; ?></h2>
        <div class="admin-card-subtitle">角色编码建议全站唯一，便于后续菜单授权和数据范围控制。</div>

        <?php if ($roleCanManage): ?>
            <form method="post" action="<?php echo e(public_url('admin.php') . '?page=roles'); ?>" class="mt-4">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.roles')); ?>">
                <input type="hidden" name="_admin_form" value="page">
                <input type="hidden" name="_admin_action" value="save_role">
                <input type="hidden" name="id" value="<?php echo e((string) ($roleForm['id'] ?? 0)); ?>">

                <div class="admin-form-grid">
                    <div>
                        <label class="admin-label">角色名称</label>
                        <input class="admin-input" name="name" value="<?php echo e((string) ($roleForm['name'] ?? '')); ?>">
                    </div>
                    <div>
                        <label class="admin-label">角色编码</label>
                        <input class="admin-input" name="code" value="<?php echo e((string) ($roleForm['code'] ?? '')); ?>">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="admin-label">角色说明</label>
                    <textarea class="admin-textarea" name="description"><?php echo e((string) ($roleForm['description'] ?? '')); ?></textarea>
                </div>

                <div class="admin-form-grid mt-4">
                    <div>
                        <label class="admin-label">数据范围</label>
                        <select class="admin-select" name="data_scope">
                            <option value="all" <?php echo (string) ($roleForm['data_scope'] ?? 'all') === 'all' ? 'selected' : ''; ?>>全部数据</option>
                            <option value="module" <?php echo (string) ($roleForm['data_scope'] ?? 'all') === 'module' ? 'selected' : ''; ?>>模块数据</option>
                            <option value="self" <?php echo (string) ($roleForm['data_scope'] ?? 'all') === 'self' ? 'selected' : ''; ?>>仅本人数据</option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">状态</label>
                        <select class="admin-select" name="status">
                            <option value="1" <?php echo (string) ($roleForm['status'] ?? '1') === '1' ? 'selected' : ''; ?>>启用</option>
                            <option value="0" <?php echo (string) ($roleForm['status'] ?? '1') === '0' ? 'selected' : ''; ?>>停用</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="admin-label">排序值</label>
                    <input class="admin-input" type="number" name="sort_order" value="<?php echo e((string) ($roleForm['sort_order'] ?? '0')); ?>">
                </div>

                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">保存角色</button>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=roles'); ?>">新建空白表单</a>
                </div>
            </form>
        <?php else: ?>
            <div class="admin-empty mt-4">当前账号只有查看权限，不能直接修改角色。</div>
        <?php endif; ?>
    </div>
</section>
