<?php
$securitySettingsForm = isset($securitySettingsForm) && is_array($securitySettingsForm) ? $securitySettingsForm : array();
$securityCanManage = !empty($securityCanManage);
?>
<section class="admin-split-grid admin-stack-layout">
    <div class="admin-card front-card">
        <h2 class="admin-card-title">后台安全策略</h2>
        <div class="admin-card-subtitle">这里维护后台登录失败上限和后台会话超时分钟数。两个配置都已经接入真实后台登录与会话逻辑，不是展示字段。</div>

        <?php if ($securityCanManage): ?>
            <form method="post" action="<?php echo e(public_url('admin.php') . '?page=security'); ?>" class="mt-4">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.security')); ?>">
                <input type="hidden" name="_admin_form" value="page">
                <input type="hidden" name="_admin_action" value="save_security">

                <div class="admin-form-grid">
                    <div>
                        <label class="admin-label">后台登录失败上限</label>
                        <input class="admin-input" type="number" min="1" name="max_login_attempts" value="<?php echo e((string) ($securitySettingsForm['max_login_attempts'] ?? '5')); ?>">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="admin-label">后台会话超时分钟数</label>
                    <input class="admin-input" type="number" min="5" name="admin_session_minutes" value="<?php echo e((string) ($securitySettingsForm['admin_session_minutes'] ?? '120')); ?>">
                    <div class="admin-help">管理员在后台连续无操作超过该分钟数后，会被系统自动退出并要求重新登录。</div>
                </div>

                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">保存安全策略</button>
                </div>
            </form>
        <?php else: ?>
            <div class="admin-empty mt-4">当前账号只有查看权限，不能修改后台安全策略。</div>
        <?php endif; ?>
    </div>

    <div class="admin-card front-card">
        <h2 class="admin-card-title">当前生效规则</h2>
        <div class="admin-card-subtitle">右侧显示的是当前后台真实生效中的风控参数，保存后立即作用于新后台登录页和后台会话。</div>

        <div class="admin-kv-list mt-4">
            <div class="admin-kv-item">
                <div class="admin-kv-label">登录失败上限</div>
                <div class="admin-kv-value"><?php echo e((string) ($securitySettingsForm['max_login_attempts'] ?? '5')); ?> 次</div>
            </div>
            <div class="admin-kv-item">
                <div class="admin-kv-label">后台会话超时</div>
                <div class="admin-kv-value"><?php echo e((string) ($securitySettingsForm['admin_session_minutes'] ?? '120')); ?> 分钟</div>
            </div>
            <div class="admin-kv-item">
                <div class="admin-kv-label">真实生效范围</div>
                <div class="admin-kv-value">后台登录、后台会话、后台风控</div>
            </div>
        </div>

        <div class="admin-tip">
            当前第一阶段已经把失败次数限制和会话超时接入真实逻辑。后续第二阶段会继续补强上传白名单、敏感词命中日志和更细粒度的后台二次确认策略。
        </div>
    </div>
</section>
