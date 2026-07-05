<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo e(isset($pageTitle) ? $pageTitle : (app()->isInstalled() ? browser_title_setting(app()->config('app', 'site_name', '六合论坛系统')) : '六合论坛系统')); ?></title>
    <?php
    $plainCssUrl = asset('app.css?v=20260612-site-notice-prompt-01');
    $plainJsUrl = asset('app.js?v=20260623-upload-compress-01');
    ?>
    <link rel="preload" href="<?php echo e($plainCssUrl); ?>" as="style">
    <link rel="stylesheet" href="<?php echo e($plainCssUrl); ?>">
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
<?php echo $content; ?>
<script src="<?php echo e($plainJsUrl); ?>" defer></script>
</body>
</html>
