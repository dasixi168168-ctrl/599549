<?php
$installSnapshot = isset($installSnapshot) && is_array($installSnapshot) ? $installSnapshot : array();
$dbConfig = isset($installSnapshot['database']) && is_array($installSnapshot['database']) ? $installSnapshot['database'] : array();
$latestRecord = isset($installSnapshot['latest_record']) && is_array($installSnapshot['latest_record']) ? $installSnapshot['latest_record'] : null;
?>
<section class="admin-card front-card">
    <h2 class="admin-card-title">安装信息</h2>
    <div class="admin-card-subtitle">这里保留当前站点数据库配置快照、安装锁状态和最近一次安装记录。</div>

    <div class="admin-kv-list mt-4">
        <div class="admin-kv-item">
            <div class="admin-kv-label">安装锁状态</div>
            <div class="admin-kv-value"><?php echo !empty($installSnapshot['lock_exists']) ? '已启用' : '未启用'; ?></div>
        </div>
        <div class="admin-kv-item">
            <div class="admin-kv-label">锁文件时间</div>
            <div class="admin-kv-value"><?php echo e((string) ($installSnapshot['lock_time'] ?: '-')); ?></div>
        </div>
        <div class="admin-kv-item">
            <div class="admin-kv-label">数据库主机</div>
            <div class="admin-kv-value"><?php echo e((string) ($dbConfig['host'] ?? '-')); ?></div>
        </div>
        <div class="admin-kv-item">
            <div class="admin-kv-label">数据库端口</div>
            <div class="admin-kv-value"><?php echo e((string) ($dbConfig['port'] ?? '-')); ?></div>
        </div>
        <div class="admin-kv-item">
            <div class="admin-kv-label">数据库名称</div>
            <div class="admin-kv-value"><?php echo e((string) ($dbConfig['database'] ?? '-')); ?></div>
        </div>
        <div class="admin-kv-item">
            <div class="admin-kv-label">数据库账号</div>
            <div class="admin-kv-value"><?php echo e((string) ($dbConfig['username'] ?? '-')); ?></div>
        </div>
    </div>
</section>

<section class="admin-section admin-card front-card">
    <h2 class="admin-card-title">最近一次安装记录</h2>
    <div class="admin-card-subtitle">用于核对当前站点最后一次安装写入的信息。</div>

    <?php if ($latestRecord): ?>
        <div class="admin-kv-list mt-4">
            <div class="admin-kv-item">
                <div class="admin-kv-label">安装批次号</div>
                <div class="admin-kv-value"><?php echo e((string) $latestRecord['install_code']); ?></div>
            </div>
            <div class="admin-kv-item">
                <div class="admin-kv-label">站点名称</div>
                <div class="admin-kv-value"><?php echo e((string) $latestRecord['site_name']); ?></div>
            </div>
            <div class="admin-kv-item">
                <div class="admin-kv-label">安装域名</div>
                <div class="admin-kv-value"><?php echo e((string) $latestRecord['site_domain']); ?></div>
            </div>
            <div class="admin-kv-item">
                <div class="admin-kv-label">数据库前缀</div>
                <div class="admin-kv-value"><?php echo e((string) $latestRecord['db_prefix']); ?></div>
            </div>
            <div class="admin-kv-item">
                <div class="admin-kv-label">安装状态</div>
                <div class="admin-kv-value"><?php echo e((string) $latestRecord['status']); ?></div>
            </div>
            <div class="admin-kv-item">
                <div class="admin-kv-label">安装时间</div>
                <div class="admin-kv-value"><?php echo e(format_datetime($latestRecord['installed_at'])); ?></div>
            </div>
        </div>
    <?php else: ?>
        <div class="admin-empty mt-4">当前还没有 install_records 记录，说明站点尚未通过新安装器写入安装日志。</div>
    <?php endif; ?>
</section>
