<?php
$uploadFilters = isset($uploadFilters) && is_array($uploadFilters) ? $uploadFilters : array();
$uploadFiles = isset($uploadFiles) && is_array($uploadFiles) ? $uploadFiles : array();
$uploadBusinessOptions = isset($uploadBusinessOptions) && is_array($uploadBusinessOptions) ? $uploadBusinessOptions : array();
$uploadCanManage = !empty($uploadCanManage);
?>
<section class="admin-card front-card">
    <h2 class="admin-card-title">附件上传</h2>
    <div class="admin-card-subtitle">这里是后台统一附件入口。当前第一阶段先支持图片上传到本地目录并写入 MySQL，后续首页轮播、帖子封面、资料图片都会复用这一套上传链路。</div>

    <?php if ($uploadCanManage): ?>
        <form method="post" action="<?php echo e(public_url('admin.php') . '?page=uploads'); ?>" enctype="multipart/form-data" class="mt-4" data-admin-upload-compress>
            <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.uploads')); ?>">
            <input type="hidden" name="_admin_form" value="page">
            <input type="hidden" name="_admin_action" value="upload_file">

            <div class="admin-form-grid">
                <div>
                    <label class="admin-label">附件业务类型</label>
                    <select class="admin-select" name="upload_business_type">
                        <?php foreach ($uploadBusinessOptions as $businessKey => $businessLabel): ?>
                            <option value="<?php echo e((string) $businessKey); ?>" <?php echo (string) ($uploadFilters['business_type'] ?? 'general') === (string) $businessKey ? 'selected' : ''; ?>><?php echo e((string) $businessLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <label class="app-upload-file-field">
                    <span class="admin-label app-upload-file-label">上传图片</span>
                    <span class="app-upload-file-control" data-app-upload-file-control>
                        <span class="app-upload-file-button">选择文件</span>
                        <span class="app-upload-file-name" data-app-upload-file-name data-empty-text="未选择任何文件">未选择任何文件</span>
                        <input class="admin-input app-upload-file-input" type="file" name="upload_file" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,image/jpeg,image/png,image/gif,image/webp,image/bmp,image/x-ms-bmp" data-app-upload-file-input>
                    </span>
                </label>
            </div>

            <div class="admin-help mt-4">当前允许上传 jpg、jpeg、png、gif、webp，单文件最大 5MB，保存后会自动写入本地目录和上传文件表。</div>

            <div class="admin-form-actions">
                <button class="admin-button" type="submit">上传图片</button>
            </div>
        </form>
    <?php else: ?>
        <div class="admin-empty mt-4">当前账号只有查看权限，不能上传附件。</div>
    <?php endif; ?>
</section>

<section class="admin-card front-card admin-section">
    <h2 class="admin-card-title">附件列表</h2>
    <div class="admin-card-subtitle">你可以在这里查看已经上传到本地目录的文件，复制路径用于首页轮播、帖子封面和后续模块。</div>

    <form method="get" action="<?php echo e(public_url('admin.php')); ?>" class="admin-toolbar mt-4">
        <input type="hidden" name="page" value="uploads">
        <div class="admin-toolbar-filters">
            <input class="admin-input" type="search" name="keyword" value="<?php echo e((string) ($uploadFilters['keyword'] ?? '')); ?>" placeholder="搜索文件名或路径">
            <select class="admin-select" name="business_type">
                <option value="">全部业务</option>
                <?php foreach ($uploadBusinessOptions as $businessKey => $businessLabel): ?>
                    <option value="<?php echo e((string) $businessKey); ?>" <?php echo (string) ($uploadFilters['business_type'] ?? '') === (string) $businessKey ? 'selected' : ''; ?>><?php echo e((string) $businessLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="admin-toolbar-actions">
            <button class="admin-button is-light" type="submit">筛选</button>
        </div>
    </form>

    <?php if ($uploadFiles): ?>
        <div class="admin-table-wrap mt-4">
            <table class="admin-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>业务类型</th>
                    <th>原文件名</th>
                    <th>访问路径</th>
                    <th>大小</th>
                    <th>尺寸</th>
                    <th>上传人</th>
                    <th>时间</th>
                    <th>查看</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($uploadFiles as $uploadRow): ?>
                    <tr>
                        <td><?php echo e((string) $uploadRow['id']); ?></td>
                        <td><?php echo e((string) ($uploadBusinessOptions[$uploadRow['business_type']] ?? $uploadRow['business_type'])); ?></td>
                        <td><?php echo e((string) $uploadRow['file_name']); ?></td>
                        <td><?php echo e(truncate_text((string) $uploadRow['file_path'], 40)); ?></td>
                        <td><?php echo e(number_format(((int) $uploadRow['file_size']) / 1024, 1)); ?> KB</td>
                        <td><?php echo e((string) $uploadRow['image_width']); ?> x <?php echo e((string) $uploadRow['image_height']); ?></td>
                        <td><?php echo e((string) $uploadRow['uploaded_by_id']); ?></td>
                        <td><?php echo e(format_datetime($uploadRow['created_at'])); ?></td>
                        <td><a class="admin-button is-light" href="<?php echo e((string) $uploadRow['file_path']); ?>" target="_blank">查看文件</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="admin-empty mt-4">当前还没有上传附件。</div>
    <?php endif; ?>
</section>
