<?php
$settingsForm = isset($settingsForm) && is_array($settingsForm) ? $settingsForm : array();
$settingsCanManage = !empty($settingsCanManage);
$siteName = (string) ($settingsForm['site_name'] ?? '');
$siteTitle = (string) ($settingsForm['site_title'] ?? '');
$macauTitle = (string) ($settingsForm['browser_region_title_macau'] ?? '');
$hongkongTitle = (string) ($settingsForm['browser_region_title_hongkong'] ?? '');
$adminBrowserTitle = (string) ($settingsForm['admin_browser_title'] ?? '');
$adminManagementName = (string) ($settingsForm['admin_management_name'] ?? '');
$frontTitlePreview = $siteTitle !== '' ? $siteTitle : $siteName;
$frontHomePreview = $frontTitlePreview !== '' ? $frontTitlePreview : '前台浏览器标题';
$macauPreview = trim($frontHomePreview . ($macauTitle !== '' ? ' - ' . $macauTitle : ''));
$hongkongPreview = trim($frontHomePreview . ($hongkongTitle !== '' ? ' - ' . $hongkongTitle : ''));
$adminPreview = trim('系统设置' . ($adminBrowserTitle !== '' ? ' - ' . $adminBrowserTitle : ''));
?>

<?php if ($settingsCanManage): ?>
<form
    class="settings-command-center"
    id="admin-settings-page-form"
    method="post"
    action="<?php echo e(public_url('api.php')); ?>"
    data-ajax-form
    data-admin-settings-composer
>
    <input type="hidden" name="action" value="admin.settings.save">
    <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
    <input type="hidden" name="_admin_form" value="page">
    <input type="hidden" name="_admin_action" value="save_settings">
<?php else: ?>
<section class="settings-command-center is-readonly">
<?php endif; ?>
    <?php if (!$settingsCanManage): ?>
        <div class="settings-readonly-banner">
            当前账号只有查看权限，不能直接修改前后台设置。
        </div>
    <?php endif; ?>

    <section class="settings-workbench">
        <div class="settings-main-column">
            <article class="settings-preview-card">
                <div class="settings-preview-head">
                    <span>实时预览</span>
                </div>
                <div class="settings-preview-list">
                    <div>
                        <span>前台首页</span>
                        <strong data-settings-preview="frontTitle"><?php echo e($frontHomePreview); ?></strong>
                    </div>
                    <div>
                        <span>澳门页面</span>
                        <strong data-settings-preview="macauTitle"><?php echo e($macauPreview !== '' ? $macauPreview : '前台标题 - 澳门区域标题'); ?></strong>
                    </div>
                    <div>
                        <span>香港页面</span>
                        <strong data-settings-preview="hongkongTitle"><?php echo e($hongkongPreview !== '' ? $hongkongPreview : '前台标题 - 香港区域标题'); ?></strong>
                    </div>
                    <div>
                        <span>后台页面</span>
                        <strong data-settings-preview="adminTitle"><?php echo e($adminPreview !== '' ? $adminPreview : '系统设置 - 后台浏览器标题'); ?></strong>
                    </div>
                    <div>
                        <span>后台左侧名称</span>
                        <strong data-settings-preview="adminName"><?php echo e($adminManagementName !== '' ? $adminManagementName : '后台管理名称'); ?></strong>
                    </div>
                </div>
            </article>

            <article class="settings-panel is-front">
                <div class="settings-panel-head">
                    <span>前台公开信息</span>
                </div>
                <div class="settings-field-grid">
                    <label class="settings-field">
                        <span>站点名称</span>
                        <input name="site_name" value="<?php echo e($siteName); ?>" maxlength="80" required<?php echo $settingsCanManage ? '' : ' readonly'; ?>>
                    </label>
                    <label class="settings-field">
                        <span>前台浏览器主标题</span>
                        <input name="site_title" value="<?php echo e($siteTitle); ?>" maxlength="120" required<?php echo $settingsCanManage ? '' : ' readonly'; ?>>
                    </label>
                    <label class="settings-field">
                        <span>澳门区域标题</span>
                        <input name="browser_region_title_macau" value="<?php echo e($macauTitle); ?>" maxlength="80" required<?php echo $settingsCanManage ? '' : ' readonly'; ?>>
                    </label>
                    <label class="settings-field">
                        <span>香港区域标题</span>
                        <input name="browser_region_title_hongkong" value="<?php echo e($hongkongTitle); ?>" maxlength="80" required<?php echo $settingsCanManage ? '' : ' readonly'; ?>>
                    </label>
                </div>
            </article>

            <article class="settings-panel is-admin">
                <div class="settings-panel-head">
                    <span>后台显示身份</span>
                </div>
                <div class="settings-field-grid">
                    <label class="settings-field">
                        <span>后台浏览器标题</span>
                        <input name="admin_browser_title" value="<?php echo e($adminBrowserTitle); ?>" maxlength="120" required<?php echo $settingsCanManage ? '' : ' readonly'; ?>>
                    </label>
                    <label class="settings-field">
                        <span>后台管理名称</span>
                        <input name="admin_management_name" value="<?php echo e($adminManagementName); ?>" maxlength="80" required<?php echo $settingsCanManage ? '' : ' readonly'; ?>>
                    </label>
                </div>
            </article>

        </div>
    </section>
<?php if ($settingsCanManage): ?>
</form>
<?php else: ?>
</section>
<?php endif; ?>
