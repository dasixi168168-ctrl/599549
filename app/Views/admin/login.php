<div class="ops-auth-page ops-auth-page--login">
    <main class="ops-login-console" aria-label="统一登录">
        <section class="ops-login-form-panel">
            <div class="ops-brand-row ops-brand-row--login">
                <span class="ops-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 24 24" role="img" focusable="false">
                        <path d="M7 10V8a5 5 0 0 1 10 0v2"></path>
                        <rect x="5" y="10" width="14" height="10" rx="3"></rect>
                        <path d="M12 14v3"></path>
                    </svg>
                </span>
                <h1 class="ops-login-title">登录入口</h1>
                <a class="ops-login-reinstall-link" href="<?php echo e(public_url('install.php') . '?restart=1'); ?>" title="进入重装站点入口">
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M21 12a9 9 0 0 1-15.2 6.5"></path>
                        <path d="M3 12A9 9 0 0 1 18.2 5.5"></path>
                        <path d="M18 2v4h-4"></path>
                        <path d="M6 22v-4h4"></path>
                    </svg>
                    <span>重装站点</span>
                </a>
            </div>

            <?php if (!empty($flashMessage['message'])): ?>
                <div hidden data-app-notice-seed data-app-notice-type="<?php echo e(isset($flashMessage['type']) ? (string) $flashMessage['type'] : 'info'); ?>" data-app-notice-message="<?php echo e((string) $flashMessage['message']); ?>"></div>
            <?php endif; ?>
            <?php if (!empty($loginNotice)): ?>
                <div hidden data-app-notice-seed data-app-notice-type="success" data-app-notice-message="<?php echo e((string) $loginNotice); ?>"></div>
            <?php endif; ?>
            <?php if (!empty($pageError)): ?>
                <div class="ops-alert ops-alert--error"><?php echo e((string) $pageError); ?></div>
            <?php endif; ?>

            <form method="post" action="<?php echo e(public_url('admin.php')); ?>" class="ops-form">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.login')); ?>">
                <input type="hidden" name="_admin_form" value="login">

                <div class="ops-unified-login-note" aria-label="可登录身份"></div>

                <label class="ops-field">
                    <span>账号</span>
                    <span class="ops-input-wrap">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M20 21a8 8 0 0 0-16 0"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                        <input class="admin-input ops-input" type="text" name="username" autocomplete="username" value="<?php echo e(isset($oldLogin['username']) ? (string) $oldLogin['username'] : ''); ?>" placeholder="请输入登录账号">
                    </span>
                </label>

                <label class="ops-field">
                    <span>密码</span>
                    <span class="ops-input-wrap">
                        <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                            <path d="M7 11V8a5 5 0 0 1 10 0v3"></path>
                            <rect x="5" y="11" width="14" height="9" rx="2"></rect>
                            <path d="M12 15v2"></path>
                        </svg>
                        <input class="admin-input ops-input" type="password" name="password" autocomplete="current-password" placeholder="请输入登录密码">
                    </span>
                </label>

                <button class="ops-submit-button" type="submit">
                    <span>登录并自动进入</span>
                    <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <path d="M5 12h14"></path>
                        <path d="m13 6 6 6-6 6"></path>
                    </svg>
                </button>
            </form>
        </section>
    </main>
</div>
