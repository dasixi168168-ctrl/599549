<div class="ops-auth-page ops-auth-page--install">
    <main class="ops-install-workbench" aria-label="在线安装">
        <header class="ops-install-header">
            <div class="ops-brand-row">
                <span class="ops-brand-mark ops-brand-mark--install" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="img" focusable="false">
                        <path d="M4 7h16"></path>
                        <path d="M6 7V5h12v2"></path>
                        <rect x="5" y="7" width="14" height="12" rx="3"></rect>
                        <path d="M9 12h6"></path>
                        <path d="M9 15h4"></path>
                    </svg>
                </span>
                <span class="ops-brand-copy">
                    <strong>系统安装</strong>
                    <small><?php echo !empty($isRestartMode) ? '维护模式' : '首次部署'; ?></small>
                </span>
            </div>
            <div class="ops-install-heading">
                <span class="ops-eyebrow">DEPLOY WORKBENCH</span>
                <h1>部署工作台</h1>
                <p>填写数据库连接与超级管理员资料，提交后写入配置并初始化站点。</p>
            </div>
            <div class="ops-install-steps" aria-label="安装步骤">
                <span>连接数据库</span>
                <span>创建结构</span>
                <span>写入管理员</span>
                <span>进入后台</span>
            </div>
        </header>

        <?php if (!empty($errorMessage)): ?>
            <div class="ops-alert ops-alert--error"><?php echo e($errorMessage); ?></div>
        <?php endif; ?>
        <?php if (!empty($successMessage)): ?>
            <div class="ops-alert ops-alert--success"><?php echo e($successMessage); ?></div>
        <?php endif; ?>
        <?php if (!empty($resumeMessage)): ?>
            <div class="ops-alert ops-alert--warn"><?php echo e($resumeMessage); ?></div>
        <?php endif; ?>

        <form method="post" action="<?php echo e(isset($formAction) ? $formAction : public_url('install.php')); ?>" class="ops-install-form" data-install-progress-form>
            <input type="hidden" name="_token" value="<?php echo e(csrf_token('install')); ?>">

            <section class="ops-install-section">
                <div class="ops-section-title">
                    <span>01</span>
                    <h2>数据库连接</h2>
                </div>
                <div class="ops-form-grid">
                    <label class="ops-field">
                        <span>数据库主机</span>
                        <input class="admin-input ops-input" name="db_host" value="<?php echo e(isset($old['db_host']) ? $old['db_host'] : '127.0.0.1'); ?>">
                    </label>
                    <label class="ops-field">
                        <span>数据库端口</span>
                        <input class="admin-input ops-input" name="db_port" value="<?php echo e(isset($old['db_port']) ? $old['db_port'] : '3306'); ?>">
                    </label>
                    <label class="ops-field">
                        <span>数据库名称</span>
                        <input class="admin-input ops-input" name="db_name" value="<?php echo e(isset($old['db_name']) ? $old['db_name'] : 'liuhe_forum'); ?>">
                    </label>
                    <label class="ops-field">
                        <span>数据库账号</span>
                        <input class="admin-input ops-input" name="db_user" value="<?php echo e(isset($old['db_user']) ? $old['db_user'] : 'root'); ?>">
                    </label>
                    <label class="ops-field ops-field--wide">
                        <span>数据库密码</span>
                        <input class="admin-input ops-input" type="password" name="db_pass" value="<?php echo e(isset($old['db_pass']) ? $old['db_pass'] : ''); ?>">
                    </label>
                </div>
            </section>

            <section class="ops-install-section">
                <div class="ops-section-title">
                    <span>02</span>
                    <h2>站点与管理员</h2>
                </div>
                <div class="ops-form-grid">
                    <label class="ops-field">
                        <span>站点名称</span>
                        <input class="admin-input ops-input" name="site_name" value="<?php echo e(isset($old['site_name']) ? $old['site_name'] : '88666999com'); ?>">
                    </label>
                    <label class="ops-field">
                        <span>超级管理员账号</span>
                        <input class="admin-input ops-input" name="admin_username" value="<?php echo e(isset($old['admin_username']) ? $old['admin_username'] : 'admin'); ?>">
                    </label>
                    <label class="ops-field ops-field--wide">
                        <span>超级管理员密码</span>
                        <input class="admin-input ops-input" type="password" name="admin_password" value="<?php echo e(isset($old['admin_password']) ? $old['admin_password'] : ''); ?>">
                    </label>
                </div>
            </section>

            <aside class="ops-install-submit">
                <div>
                    <span class="ops-submit-label">准备执行</span>
                    <strong><?php echo !empty($isRestartMode) ? '重新安装流程' : '首次安装流程'; ?></strong>
                </div>
                <button class="ops-submit-button" type="submit"<?php echo !empty($installFormDisabled) ? ' disabled aria-disabled="true"' : ''; ?>>
                    <span><?php echo e(isset($submitLabel) ? $submitLabel : '开始安装系统'); ?></span>
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M5 12h14"></path>
                        <path d="m13 6 6 6-6 6"></path>
                    </svg>
                </button>
                <div class="ops-install-progress" data-install-progress hidden aria-live="polite">
                    <div class="ops-install-progress-head">
                        <span data-install-progress-text>准备开始安装</span>
                        <strong data-install-progress-percent>0%</strong>
                    </div>
                    <div class="ops-install-progress-track">
                        <i data-install-progress-bar></i>
                    </div>
                    <div class="ops-install-progress-note">请勿关闭页面或重复提交。</div>
                </div>
            </aside>
        </form>
    </main>
</div>
