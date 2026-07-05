<?php
$issues = isset($issues) && is_array($issues) ? $issues : array();
$issueForm = isset($issueForm) && is_array($issueForm) ? $issueForm : array();
$issueFilters = isset($issueFilters) && is_array($issueFilters) ? $issueFilters : array();
$currentIssueSnapshots = isset($currentIssueSnapshots) && is_array($currentIssueSnapshots) ? $currentIssueSnapshots : array();
$issueCanManage = !empty($issueCanManage);
?>
<section class="admin-split-grid admin-stack-layout">
    <div>
        <div class="admin-card front-card">
            <h2 class="admin-card-title">期数计划列表</h2>
            <div class="admin-card-subtitle">这里维护澳门和香港的期数计划。当前前台首页“下期开奖时间”已经接入当前期计划时间，保存后会真实影响首页开奖头部时间显示。</div>

            <form class="mt-4" method="get" action="<?php echo e(public_url('admin.php')); ?>">
                <input type="hidden" name="page" value="issues">
                <div class="admin-filter-grid">
                    <div>
                        <label class="admin-label">关键词</label>
                        <input class="admin-input" name="keyword" value="<?php echo e((string) ($issueFilters['keyword'] ?? '')); ?>" placeholder="按期号或备注搜索">
                    </div>
                    <div>
                        <label class="admin-label">期数分区</label>
                        <select class="admin-select" name="region">
                            <option value="">全部分区</option>
                            <option value="macau" <?php echo (string) ($issueFilters['region'] ?? '') === 'macau' ? 'selected' : ''; ?>>澳门</option>
                            <option value="hongkong" <?php echo (string) ($issueFilters['region'] ?? '') === 'hongkong' ? 'selected' : ''; ?>>香港</option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">期数状态</label>
                        <select class="admin-select" name="status">
                            <option value="">全部状态</option>
                            <option value="pending" <?php echo (string) ($issueFilters['status'] ?? '') === 'pending' ? 'selected' : ''; ?>>待开奖</option>
                            <option value="opened" <?php echo (string) ($issueFilters['status'] ?? '') === 'opened' ? 'selected' : ''; ?>>已开奖</option>
                            <option value="cancelled" <?php echo (string) ($issueFilters['status'] ?? '') === 'cancelled' ? 'selected' : ''; ?>>已取消</option>
                        </select>
                    </div>
                    <div></div>
                </div>
                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">筛选期数</button>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=issues'); ?>">重置条件</a>
                </div>
            </form>

            <?php if ($issues): ?>
                <div class="admin-table-wrap mt-4">
                    <table class="admin-table">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>分区</th>
                            <th>期号</th>
                            <th>计划开奖</th>
                            <th>实际开奖</th>
                            <th>状态</th>
                            <th>当前期</th>
                            <th>操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($issues as $issue): ?>
                            <tr>
                                <td><?php echo e((string) $issue['id']); ?></td>
                                <td><?php echo (string) $issue['region'] === 'hongkong' ? '香港' : '澳门'; ?></td>
                                <td><?php echo e((string) $issue['issue_no']); ?></td>
                                <td><?php echo e(format_datetime($issue['planned_open_at'] ?? null)); ?></td>
                                <td><?php echo e(format_datetime($issue['actual_open_at'] ?? null)); ?></td>
                                <td>
                                    <?php
                                    $statusClass = 'is-warning';
                                    $statusText = '待开奖';
                                    if ((string) $issue['status'] === 'opened') {
                                        $statusClass = 'is-success';
                                        $statusText = '已开奖';
                                    } elseif ((string) $issue['status'] === 'cancelled') {
                                        $statusClass = 'is-danger';
                                        $statusText = '已取消';
                                    }
                                    ?>
                                    <span class="admin-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </td>
                                <td><?php echo (int) $issue['is_current'] === 1 ? '是' : '否'; ?></td>
                                <td>
                                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=issues&edit=' . (int) $issue['id']); ?>">编辑</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="admin-empty mt-4">当前还没有期数计划数据。</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="admin-card front-card">
        <h2 class="admin-card-title"><?php echo !empty($issueForm['id']) ? '编辑期数计划' : '新增期数计划'; ?></h2>
        <div class="admin-card-subtitle">这里维护开奖前的期数计划。每个分区只能有一个当前期，勾选“设为当前期”后会自动取消同分区其他当前期标记。</div>

        <?php if ($issueCanManage): ?>
            <form method="post" action="<?php echo e(public_url('admin.php') . '?page=issues'); ?>" class="mt-4">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.issues')); ?>">
                <input type="hidden" name="_admin_form" value="page">
                <input type="hidden" name="_admin_action" value="save_issue">
                <input type="hidden" name="id" value="<?php echo e((string) ($issueForm['id'] ?? 0)); ?>">

                <div class="admin-form-grid">
                    <div>
                        <label class="admin-label">期数分区</label>
                        <select class="admin-select" name="region">
                            <option value="macau" <?php echo (string) ($issueForm['region'] ?? 'macau') === 'macau' ? 'selected' : ''; ?>>澳门</option>
                            <option value="hongkong" <?php echo (string) ($issueForm['region'] ?? 'macau') === 'hongkong' ? 'selected' : ''; ?>>香港</option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">期号</label>
                        <input class="admin-input" name="issue_no" value="<?php echo e((string) ($issueForm['issue_no'] ?? '')); ?>" placeholder="例如 081期">
                    </div>
                </div>

                <div class="admin-form-grid mt-4">
                    <div>
                        <label class="admin-label">计划开奖时间</label>
                        <input class="admin-input" type="datetime-local" name="planned_open_at" value="<?php echo e((string) ($issueForm['planned_open_at'] ?? '')); ?>">
                    </div>
                    <div>
                        <label class="admin-label">实际开奖时间</label>
                        <input class="admin-input" type="datetime-local" name="actual_open_at" value="<?php echo e((string) ($issueForm['actual_open_at'] ?? '')); ?>">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="admin-label">期数状态</label>
                    <select class="admin-select" name="status">
                        <option value="pending" <?php echo (string) ($issueForm['status'] ?? 'pending') === 'pending' ? 'selected' : ''; ?>>待开奖</option>
                        <option value="opened" <?php echo (string) ($issueForm['status'] ?? 'pending') === 'opened' ? 'selected' : ''; ?>>已开奖</option>
                        <option value="cancelled" <?php echo (string) ($issueForm['status'] ?? 'pending') === 'cancelled' ? 'selected' : ''; ?>>已取消</option>
                    </select>
                </div>

                <div class="admin-check-row mt-4">
                    <label class="admin-check-item">
                        <input type="checkbox" name="is_current" value="1" <?php echo (string) ($issueForm['is_current'] ?? '0') === '1' ? 'checked' : ''; ?>>
                        <span>设为当前期</span>
                    </label>
                </div>

                <div class="mt-4">
                    <label class="admin-label">备注</label>
                    <textarea class="admin-textarea" name="remark"><?php echo e((string) ($issueForm['remark'] ?? '')); ?></textarea>
                </div>

                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">保存期数计划</button>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=issues'); ?>">新建空白表单</a>
                </div>
            </form>
        <?php else: ?>
            <div class="admin-empty mt-4">当前账号只有查看权限，不能维护期数计划。</div>
        <?php endif; ?>
    </div>
</section>

<section class="admin-section">
    <div class="admin-card front-card">
        <h2 class="admin-card-title">当前期联动预览</h2>
        <div class="admin-card-subtitle">这里显示前台首页“下期开奖时间”当前会读取的期数计划。每个分区只会命中一个当前期，没有当前期时回退到最近一条计划。</div>

        <div class="admin-grid-2 mt-4">
            <?php foreach ($currentIssueSnapshots as $snapshot): ?>
                <?php
                $statusText = '待开奖';
                $statusClass = 'is-warning';
                if ((string) ($snapshot['status'] ?? '') === 'opened') {
                    $statusText = '已开奖';
                    $statusClass = 'is-success';
                } elseif ((string) ($snapshot['status'] ?? '') === 'cancelled') {
                    $statusText = '已取消';
                    $statusClass = 'is-danger';
                }
                ?>
                <div class="stat-card admin-preview-card">
                    <div class="stat-card-icon"><?php echo e((string) ($snapshot['label'] ?? '区')); ?></div>
                    <div class="stat-card-label text-blue-600"><?php echo e((string) ($snapshot['label'] ?? '')); ?>当前期计划</div>
                    <div class="admin-preview-title"><?php echo e((string) ($snapshot['issue_no'] !== '' ? $snapshot['issue_no'] : '未设置')); ?></div>
                    <div class="admin-preview-line"><strong>计划开奖：</strong><?php echo e(format_datetime($snapshot['planned_open_at'] ?? null)); ?></div>
                    <div class="admin-preview-line"><strong>实际开奖：</strong><?php echo e(format_datetime($snapshot['actual_open_at'] ?? null)); ?></div>
                    <div class="admin-preview-line"><strong>当前期标记：</strong><?php echo !empty($snapshot['is_current']) ? '是' : '否'; ?></div>
                    <div class="admin-preview-line"><strong>状态：</strong><span class="admin-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></div>
                    <div class="admin-preview-line"><strong>备注：</strong><?php echo e((string) ($snapshot['remark'] !== '' ? $snapshot['remark'] : '暂无备注')); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
