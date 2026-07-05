<?php
$reportPage = isset($reportPage) && is_array($reportPage) ? $reportPage : array('items' => array(), 'total' => 0, 'page_no' => 1, 'page_count' => 1);
$reportStats = isset($reportStats) && is_array($reportStats) ? $reportStats : array();
$reportFilters = isset($reportFilters) && is_array($reportFilters) ? $reportFilters : array();
$reportForm = isset($reportForm) && is_array($reportForm) ? $reportForm : array();
$reportRows = isset($reportPage['items']) && is_array($reportPage['items']) ? $reportPage['items'] : array();
$reportTypeLabels = isset($reportTypeLabels) && is_array($reportTypeLabels) ? $reportTypeLabels : array();
$reportPunishmentLabels = isset($reportPunishmentLabels) && is_array($reportPunishmentLabels) ? $reportPunishmentLabels : array();
$reportUserOptions = isset($reportUserOptions) && is_array($reportUserOptions) ? $reportUserOptions : array();
$reportPostOptions = isset($reportPostOptions) && is_array($reportPostOptions) ? $reportPostOptions : array();
$reportCanManage = !empty($reportCanManage);
$reportQueryBase = array(
    'page' => 'reports',
    'keyword' => (string) ($reportFilters['keyword'] ?? ''),
    'region' => (string) ($reportFilters['region'] ?? ''),
    'report_type' => (string) ($reportFilters['report_type'] ?? ''),
    'status' => (string) ($reportFilters['status'] ?? ''),
    'post_id' => (string) ($reportFilters['post_id'] ?? ''),
    'reporter_id' => (string) ($reportFilters['reporter_id'] ?? ''),
);
?>
<section class="admin-split-grid admin-stack-layout">
    <div>
        <div class="admin-card front-card">
            <h2 class="admin-card-title">帖子举报管理</h2>
            <div class="admin-card-subtitle">这里集中处理前台帖子举报。当前这版已经接通处罚联动，处理举报时可以直接把帖子转待审、归档、软删除，或封禁作者，处理结果会真实写入数据库和后台日志。</div>

            <div class="admin-kv-list mt-4">
                <div class="admin-kv-item">
                    <div class="admin-kv-label">待处理举报</div>
                    <div class="admin-kv-value"><?php echo e((string) ((int) ($reportStats['pending_count'] ?? 0))); ?></div>
                </div>
                <div class="admin-kv-item">
                    <div class="admin-kv-label">已处理举报</div>
                    <div class="admin-kv-value"><?php echo e((string) ((int) ($reportStats['processed_count'] ?? 0))); ?></div>
                </div>
                <div class="admin-kv-item">
                    <div class="admin-kv-label">已忽略举报</div>
                    <div class="admin-kv-value"><?php echo e((string) ((int) ($reportStats['ignored_count'] ?? 0))); ?></div>
                </div>
                <div class="admin-kv-item">
                    <div class="admin-kv-label">涉及帖子数</div>
                    <div class="admin-kv-value"><?php echo e((string) ((int) ($reportStats['post_count'] ?? 0))); ?></div>
                </div>
            </div>

            <form class="mt-4" method="get" action="<?php echo e(public_url('admin.php')); ?>">
                <input type="hidden" name="page" value="reports">
                <div class="admin-filter-grid">
                    <div>
                        <label class="admin-label">关键词</label>
                        <input class="admin-input" name="keyword" value="<?php echo e((string) ($reportFilters['keyword'] ?? '')); ?>" placeholder="帖子标题 / 举报人 / 处理结果">
                    </div>
                    <div>
                        <label class="admin-label">分区</label>
                        <select class="admin-select" name="region">
                            <option value="">全部分区</option>
                            <option value="macau" <?php echo (string) ($reportFilters['region'] ?? '') === 'macau' ? 'selected' : ''; ?>>澳门</option>
                            <option value="hongkong" <?php echo (string) ($reportFilters['region'] ?? '') === 'hongkong' ? 'selected' : ''; ?>>香港</option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">举报类型</label>
                        <select class="admin-select" name="report_type">
                            <option value="">全部类型</option>
                            <?php foreach ($reportTypeLabels as $typeCode => $typeLabel): ?>
                                <option value="<?php echo e((string) $typeCode); ?>" <?php echo (string) ($reportFilters['report_type'] ?? '') === (string) $typeCode ? 'selected' : ''; ?>><?php echo e((string) $typeLabel); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">处理状态</label>
                        <select class="admin-select" name="status">
                            <option value="">全部状态</option>
                            <option value="pending" <?php echo (string) ($reportFilters['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>待处理</option>
                            <option value="processed" <?php echo (string) ($reportFilters['status'] ?? '') === 'processed' ? 'selected' : ''; ?>>已处理</option>
                            <option value="ignored" <?php echo (string) ($reportFilters['status'] ?? '') === 'ignored' ? 'selected' : ''; ?>>已忽略</option>
                        </select>
                    </div>
                </div>

                <div class="admin-filter-grid mt-4">
                    <div>
                        <label class="admin-label">帖子 ID</label>
                        <input class="admin-input" type="number" name="post_id" value="<?php echo e((string) ($reportFilters['post_id'] ?? '')); ?>" min="0">
                    </div>
                    <div>
                        <label class="admin-label">举报人 ID</label>
                        <input class="admin-input" type="number" name="reporter_id" value="<?php echo e((string) ($reportFilters['reporter_id'] ?? '')); ?>" min="0">
                    </div>
                    <div></div>
                    <div></div>
                </div>

                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">筛选举报</button>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=reports'); ?>">重置条件</a>
                </div>
            </form>

            <?php if ($reportRows): ?>
                <div class="admin-table-wrap mt-4">
                    <table class="admin-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>帖子</th>
                            <th>作者 / 举报人</th>
                            <th>分区</th>
                            <th>举报类型</th>
                            <th>帖子状态</th>
                            <th>处理状态</th>
                            <th>处理结果</th>
                            <th>时间</th>
                            <th>快捷处理</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($reportRows as $row): ?>
                            <?php
                            $reportStatus = (string) ($row['status'] ?? 'pending');
                            $reportStatusClass = $reportStatus === 'processed' ? 'is-success' : ($reportStatus === 'ignored' ? 'is-info' : 'is-warning');
                            $reportStatusText = $reportStatus === 'processed' ? '已处理' : ($reportStatus === 'ignored' ? '已忽略' : '待处理');

                            $postStatus = (string) ($row['post_status'] ?? 'draft');
                            $postStatusClass = 'is-info';
                            $postStatusText = '草稿';
                            if ($postStatus === 'published') {
                                $postStatusClass = 'is-success';
                                $postStatusText = '已发布';
                            } elseif ($postStatus === 'pending') {
                                $postStatusClass = 'is-warning';
                                $postStatusText = '待审核';
                            } elseif ($postStatus === 'archived') {
                                $postStatusClass = 'is-info';
                                $postStatusText = '已归档';
                            } elseif ($postStatus === 'deleted') {
                                $postStatusClass = 'is-danger';
                                $postStatusText = '已软删除';
                            }
                            ?>
                            <tr>
                                <td><?php echo e((string) $row['id']); ?></td>
                                <td>
                                    <strong><?php echo e((string) (($row['post_title'] ?? '') ?: '-')); ?></strong>
                                    <div class="admin-help">帖子 ID：<?php echo e((string) ($row['post_id'] ?? 0)); ?></div>
                                    <div class="admin-help"><?php echo e((string) (($row['content'] ?? '') ?: '-')); ?></div>
                                </td>
                                <td>
                                    作者：<?php echo e((string) (($row['author_name'] ?? '') ?: '-')); ?>
                                    <div class="admin-help">举报人：<?php echo e((string) (($row['reporter_name'] ?? '') ?: '-')); ?></div>
                                </td>
                                <td><?php echo (string) ($row['region'] ?? 'macau') === 'hongkong' ? '香港' : '澳门'; ?></td>
                                <td><?php echo e((string) ($reportTypeLabels[(string) ($row['report_type'] ?? '')] ?? (string) ($row['report_type'] ?? '-'))); ?></td>
                                <td><span class="admin-badge <?php echo e($postStatusClass); ?>"><?php echo e($postStatusText); ?></span></td>
                                <td><span class="admin-badge <?php echo e($reportStatusClass); ?>"><?php echo e($reportStatusText); ?></span></td>
                                <td><?php echo e((string) (($row['handle_result'] ?? '') ?: '-')); ?></td>
                                <td><?php echo e(format_datetime($row['created_at'] ?? null)); ?></td>
                                <td>
                                    <?php if ($reportCanManage): ?>
                                        <form method="post" action="<?php echo e(public_url('admin.php') . '?page=reports'); ?>" class="admin-inline-actions">
                                            <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.reports')); ?>">
                                            <input type="hidden" name="_admin_form" value="page">
                                            <input type="hidden" name="_admin_action" value="save_report_status">
                                            <input type="hidden" name="report_id" value="<?php echo e((string) $row['id']); ?>">
                                            <input type="hidden" name="report_status" value="processed">
                                            <input type="hidden" name="handle_result" value="">
                                            <select class="admin-select is-compact" name="punish_action">
                                                <?php foreach ($reportPunishmentLabels as $punishCode => $punishLabel): ?>
                                                    <option value="<?php echo e((string) $punishCode); ?>"><?php echo e((string) $punishLabel); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button class="admin-button is-light" type="submit">处理举报</button>
                                        </form>
                                        <form method="post" action="<?php echo e(public_url('admin.php') . '?page=reports'); ?>" class="admin-inline-actions mt-3">
                                            <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.reports')); ?>">
                                            <input type="hidden" name="_admin_form" value="page">
                                            <input type="hidden" name="_admin_action" value="save_report_status">
                                            <input type="hidden" name="report_id" value="<?php echo e((string) $row['id']); ?>">
                                            <input type="hidden" name="report_status" value="ignored">
                                            <input type="hidden" name="punish_action" value="none">
                                            <input type="hidden" name="handle_result" value="后台已忽略该举报">
                                            <button class="admin-button is-light" type="submit">标记忽略</button>
                                            <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=reports&edit=' . (int) $row['id']); ?>">编辑</a>
                                        </form>
                                    <?php else: ?>
                                        <span class="admin-help">只读</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="admin-pagination">
                    <div class="admin-pagination-meta">共 <?php echo e((string) ($reportPage['total'] ?? 0)); ?> 条，第 <?php echo e((string) ($reportPage['page_no'] ?? 1)); ?> / <?php echo e((string) ($reportPage['page_count'] ?? 1)); ?> 页</div>
                    <div class="admin-pagination-links">
                        <?php $currentPage = (int) ($reportPage['page_no'] ?? 1); ?>
                        <?php $pageCount = (int) ($reportPage['page_count'] ?? 1); ?>
                        <?php if ($currentPage > 1): ?>
                            <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($reportQueryBase, array('page_no' => $currentPage - 1)))); ?>">上一页</a>
                        <?php endif; ?>
                        <?php if ($currentPage < $pageCount): ?>
                            <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?' . http_build_query(array_merge($reportQueryBase, array('page_no' => $currentPage + 1)))); ?>">下一页</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="admin-empty mt-4">当前条件下没有帖子举报记录。</div>
            <?php endif; ?>

            <div class="admin-tip">说明：处罚联动只保留当前这一套后台逻辑。处理举报后会同步更新帖子状态，封禁作者时会写入会员封禁记录，不再保留旧的人工外部处理分支。</div>
        </div>
    </div>

    <div class="admin-card front-card">
        <h2 class="admin-card-title"><?php echo !empty($reportForm['id']) ? '编辑举报记录' : '新增举报记录'; ?></h2>
        <div class="admin-card-subtitle">这里维护举报记录本身和处理结果。保存后会直接写入举报表，前台举报流和后台处罚流共用同一套数据。</div>

        <?php if ($reportCanManage): ?>
            <form method="post" action="<?php echo e(public_url('admin.php') . '?page=reports'); ?>" class="mt-4">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.reports')); ?>">
                <input type="hidden" name="_admin_form" value="page">
                <input type="hidden" name="_admin_action" value="save_report">
                <input type="hidden" name="id" value="<?php echo e((string) ($reportForm['id'] ?? 0)); ?>">

                <div class="mt-4">
                    <label class="admin-label">关联帖子</label>
                    <select class="admin-select" name="post_id">
                        <option value="0">请选择帖子</option>
                        <?php foreach ($reportPostOptions as $option): ?>
                            <option value="<?php echo e((string) $option['id']); ?>" <?php echo (int) ($reportForm['post_id'] ?? 0) === (int) $option['id'] ? 'selected' : ''; ?>><?php echo e((string) $option['title']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mt-4">
                    <label class="admin-label">举报会员</label>
                    <select class="admin-select" name="reporter_id">
                        <option value="0">请选择会员</option>
                        <?php foreach ($reportUserOptions as $option): ?>
                            <option value="<?php echo e((string) $option['id']); ?>" <?php echo (int) ($reportForm['reporter_id'] ?? 0) === (int) $option['id'] ? 'selected' : ''; ?>><?php echo e((string) $option['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mt-4">
                    <label class="admin-label">举报类型</label>
                    <select class="admin-select" name="report_type">
                        <?php foreach ($reportTypeLabels as $typeCode => $typeLabel): ?>
                            <option value="<?php echo e((string) $typeCode); ?>" <?php echo (string) ($reportForm['report_type'] ?? 'other') === (string) $typeCode ? 'selected' : ''; ?>><?php echo e((string) $typeLabel); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mt-4">
                    <label class="admin-label">举报说明</label>
                    <textarea class="admin-textarea" name="content"><?php echo e((string) ($reportForm['content'] ?? '')); ?></textarea>
                </div>

                <div class="mt-4">
                    <label class="admin-label">处理状态</label>
                    <select class="admin-select" name="status">
                        <option value="pending" <?php echo (string) ($reportForm['status'] ?? 'pending') === 'pending' ? 'selected' : ''; ?>>待处理</option>
                        <option value="processed" <?php echo (string) ($reportForm['status'] ?? 'pending') === 'processed' ? 'selected' : ''; ?>>已处理</option>
                        <option value="ignored" <?php echo (string) ($reportForm['status'] ?? 'pending') === 'ignored' ? 'selected' : ''; ?>>已忽略</option>
                    </select>
                </div>

                <div class="mt-4">
                    <label class="admin-label">处理结果</label>
                    <textarea class="admin-textarea" name="handle_result"><?php echo e((string) ($reportForm['handle_result'] ?? '')); ?></textarea>
                </div>

                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">保存举报</button>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=reports'); ?>">新建空白表单</a>
                </div>
            </form>
        <?php else: ?>
            <div class="admin-empty mt-4">当前账号只有查看权限，不能直接维护帖子举报记录。</div>
        <?php endif; ?>
    </div>
</section>
