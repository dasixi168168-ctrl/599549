<?php
$admins = isset($admins) && is_array($admins) ? $admins : array();
$roles = isset($roles) && is_array($roles) ? $roles : array();
$editingAdmin = isset($editingAdmin) && is_array($editingAdmin) ? $editingAdmin : null;
$adminForm = isset($adminForm) && is_array($adminForm) ? $adminForm : array();
$adminCanManage = !empty($adminCanManage);
?>
<section class="admin-split-grid admin-stack-layout">
    <div class="admin-card front-card">
        <h2 class="admin-card-title">管理员列表</h2>
        <div class="admin-card-subtitle">这里管理新后台独立管理员账号，不改动前台会员账号体系。</div>

        <?php if ($admins): ?>
            <div class="admin-table-wrap mt-4">
                <table class="admin-table">
                    <thead>
                    <tr>
                        <th>ID</th>
                        <th>账号</th>
                        <th>姓名</th>
                        <th>角色</th>
                        <th>状态</th>
                        <th>最后登录</th>
                        <th>操作</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($admins as $row): ?>
                        <tr>
                            <td><?php echo e((string) $row['id']); ?></td>
                            <td><?php echo e((string) $row['username']); ?></td>
                            <td><?php echo e((string) ($row['real_name'] ?: $row['nickname'] ?: '-')); ?></td>
                            <td><?php echo e((string) ($row['role_name'] ?? '-')); ?></td>
                            <td>
                                <span class="admin-badge <?php echo (int) $row['status'] === 1 ? 'is-success' : 'is-danger'; ?>">
                                    <?php echo (int) $row['status'] === 1 ? '启用' : '停用'; ?>
                                </span>
                            </td>
                            <td><?php echo e(format_datetime($row['last_login_at'])); ?></td>
                            <td>
                                <div class="admin-inline-actions">
                                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=admins&edit=' . (int) $row['id']); ?>">编辑</a>
                                    <?php if ($adminCanManage): ?>
                                        <form class="admin-inline-form" method="post" action="<?php echo e(public_url('admin.php') . '?page=admins'); ?>" data-confirm="确认切换该管理员状态吗？">
                                            <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.admins')); ?>">
                                            <input type="hidden" name="_admin_form" value="page">
                                            <input type="hidden" name="_admin_action" value="toggle_admin_status">
                                            <input type="hidden" name="target_id" value="<?php echo e((string) $row['id']); ?>">
                                            <button class="admin-button is-ghost" type="submit"><?php echo (int) $row['status'] === 1 ? '停用' : '启用'; ?></button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="admin-empty mt-4">当前还没有后台管理员数据。</div>
        <?php endif; ?>
    </div>

    <div class="admin-card front-card">
        <h2 class="admin-card-title"><?php echo !empty($adminForm['id']) ? '编辑管理员' : '新增管理员'; ?></h2>
        <div class="admin-card-subtitle">超级管理员账号默认由系统初始化，后续可在这里继续扩展后台账号。</div>

        <?php if ($adminCanManage): ?>
            <form method="post" action="<?php echo e(public_url('admin.php') . '?page=admins'); ?>" class="mt-4">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.admins')); ?>">
                <input type="hidden" name="_admin_form" value="page">
                <input type="hidden" name="_admin_action" value="save_admin">
                <input type="hidden" name="id" value="<?php echo e((string) ($adminForm['id'] ?? 0)); ?>">

                <div class="admin-form-grid">
                    <div>
                        <label class="admin-label">管理员账号</label>
                        <input class="admin-input" name="username" value="<?php echo e((string) ($adminForm['username'] ?? '')); ?>">
                    </div>
                    <div>
                        <label class="admin-label">登录密码</label>
                        <input class="admin-input" type="password" name="password">
                        <div class="admin-help"><?php echo !empty($adminForm['id']) ? '编辑时留空表示不修改密码。' : '新建管理员时密码不能少于 6 位。'; ?></div>
                    </div>
                </div>

                <div class="admin-form-grid mt-4">
                    <div>
                        <label class="admin-label">真实姓名</label>
                        <input class="admin-input" name="real_name" value="<?php echo e((string) ($adminForm['real_name'] ?? '')); ?>">
                    </div>
                    <div>
                        <label class="admin-label">昵称</label>
                        <input class="admin-input" name="nickname" value="<?php echo e((string) ($adminForm['nickname'] ?? '')); ?>">
                    </div>
                </div>

                <div class="admin-form-grid mt-4">
                    <div>
                        <label class="admin-label">手机号码</label>
                        <input class="admin-input" name="mobile" value="<?php echo e((string) ($adminForm['mobile'] ?? '')); ?>">
                    </div>
                    <div>
                        <label class="admin-label">邮箱地址</label>
                        <input class="admin-input" name="email" value="<?php echo e((string) ($adminForm['email'] ?? '')); ?>">
                    </div>
                </div>

                <div class="admin-form-grid mt-4">
                    <div>
                        <label class="admin-label">角色</label>
                        <select class="admin-select" name="role_id">
                            <option value="0">请选择角色</option>
                            <?php foreach ($roles as $role): ?>
                                <option value="<?php echo e((string) $role['id']); ?>" <?php echo (int) ($adminForm['role_id'] ?? 0) === (int) $role['id'] ? 'selected' : ''; ?>>
                                    <?php echo e((string) $role['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">状态</label>
                        <select class="admin-select" name="status">
                            <option value="1" <?php echo (string) ($adminForm['status'] ?? '1') === '1' ? 'selected' : ''; ?>>启用</option>
                            <option value="0" <?php echo (string) ($adminForm['status'] ?? '1') === '0' ? 'selected' : ''; ?>>停用</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="admin-label">备注</label>
                    <textarea class="admin-textarea" name="remark"><?php echo e((string) ($adminForm['remark'] ?? '')); ?></textarea>
                </div>

                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">保存管理员</button>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=admins'); ?>">新建空白表单</a>
                </div>
            </form>
        <?php else: ?>
            <div class="admin-empty mt-4">当前账号只有查看权限，不能直接修改后台管理员。</div>
        <?php endif; ?>
    </div>
</section>
