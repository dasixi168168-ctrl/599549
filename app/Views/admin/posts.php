<?php
$posts = isset($posts) && is_array($posts) ? $posts : array();
$posts = app()->posts()->attachDisplayTitlePayloads($posts, 'id');
$postForm = isset($postForm) && is_array($postForm) ? $postForm : array();
$postFilters = isset($postFilters) && is_array($postFilters) ? $postFilters : array();
$sectionOptions = isset($sectionOptions) && is_array($sectionOptions) ? $sectionOptions : array();
$categoryOptions = isset($categoryOptions) && is_array($categoryOptions) ? $categoryOptions : array();
$postCanManage = !empty($postCanManage);
?>
<section class="admin-split-grid admin-posts-stack">
    <div>
        <div class="admin-card front-card">
            <h2 class="admin-card-title">帖子管理</h2>
            <div class="admin-card-subtitle">这里直接接管前台帖子主表，支持发布、编辑、批量置顶、改色和删除。当前批量删除走真实删除，请操作前确认。</div>

            <form class="mt-4" method="get" action="<?php echo e(public_url('admin.php')); ?>">
                <input type="hidden" name="page" value="posts">
                <div class="admin-filter-grid">
                    <div>
                        <label class="admin-label">关键词</label>
                        <input class="admin-input" name="keyword" value="<?php echo e((string) ($postFilters['keyword'] ?? '')); ?>" placeholder="标题 / 摘要 / 作者">
                    </div>
                    <div>
                        <label class="admin-label">分区</label>
                        <select class="admin-select" name="region">
                            <option value="">全部分区</option>
                            <option value="macau" <?php echo (string) ($postFilters['region'] ?? '') === 'macau' ? 'selected' : ''; ?>>澳门</option>
                            <option value="hongkong" <?php echo (string) ($postFilters['region'] ?? '') === 'hongkong' ? 'selected' : ''; ?>>香港</option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">状态</label>
                        <select class="admin-select" name="status">
                            <option value="">全部状态</option>
                            <option value="published" <?php echo (string) ($postFilters['status'] ?? '') === 'published' ? 'selected' : ''; ?>>已发布</option>
                            <option value="draft" <?php echo (string) ($postFilters['status'] ?? '') === 'draft' ? 'selected' : ''; ?>>草稿</option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">色签</label>
                        <select class="admin-select" name="color_tag">
                            <option value="">全部色签</option>
                            <option value="slate" <?php echo (string) ($postFilters['color_tag'] ?? '') === 'slate' ? 'selected' : ''; ?>>默认</option>
                            <option value="red" <?php echo (string) ($postFilters['color_tag'] ?? '') === 'red' ? 'selected' : ''; ?>>红签</option>
                            <option value="green" <?php echo (string) ($postFilters['color_tag'] ?? '') === 'green' ? 'selected' : ''; ?>>绿签</option>
                            <option value="gold" <?php echo (string) ($postFilters['color_tag'] ?? '') === 'gold' ? 'selected' : ''; ?>>金签</option>
                        </select>
                    </div>
                </div>
                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">筛选帖子</button>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=posts'); ?>">重置条件</a>
                </div>
            </form>

            <?php if ($posts): ?>
                <form method="post" action="<?php echo e(public_url('admin.php') . '?page=posts'); ?>" class="mt-4" data-confirm="确认执行这次批量帖子操作吗？">
                    <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.posts')); ?>">
                    <input type="hidden" name="_admin_form" value="page">
                    <input type="hidden" name="_admin_action" value="bulk_posts">

                    <?php if ($postCanManage): ?>
                        <div class="admin-inline-actions">
                            <select class="admin-select is-compact" name="bulk_action">
                                <option value="top_normal">设为普通置顶</option>
                                <option value="top_admin">设为后台置顶</option>
                                <option value="top_forever">设为永久置顶</option>
                                <option value="color">批量改色签</option>
                                <option value="delete">直接删除</option>
                            </select>
                            <select class="admin-select is-compact" name="bulk_value">
                                <option value="red">红签</option>
                                <option value="green">绿签</option>
                                <option value="gold">金签</option>
                                <option value="slate">默认</option>
                            </select>
                            <button class="admin-button is-ghost" type="submit">执行批量操作</button>
                        </div>
                    <?php endif; ?>

                    <div class="admin-table-wrap mt-4">
                        <table class="admin-table">
                            <thead>
                            <tr>
                                <th><?php if ($postCanManage): ?><input type="checkbox" data-check-all="input[name=&quot;selected_ids[]&quot;]"><?php endif; ?></th>
                                <th>ID</th>
                                <th>标题</th>
                                <th>作者</th>
                                <th>分区</th>
                                <th>价格</th>
                                <th>状态</th>
                                <th>色签</th>
                                <th>置顶</th>
                                <th>时间</th>
                                <th>操作</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($posts as $row): ?>
                                <tr>
                                    <td>
                                        <?php if ($postCanManage): ?>
                                            <input type="checkbox" name="selected_ids[]" value="<?php echo e((string) $row['id']); ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo e((string) $row['id']); ?></td>
                                    <td>
                                        <strong><?php echo trim((string) ($row['display_title_html'] ?? '')) !== '' ? (string) $row['display_title_html'] : e((string) $row['title']); ?></strong>
                                        <div class="admin-help"><?php echo e(truncate_text((string) ($row['excerpt'] ?? ''), 50)); ?></div>
                                    </td>
                                    <td><?php echo e((string) ($row['author_name'] ?? '-')); ?></td>
                                    <td><?php echo (string) ($row['region'] ?? 'macau') === 'hongkong' ? '香港' : '澳门'; ?></td>
                                    <td><?php echo e((string) ($row['price'] ?? 0)); ?></td>
                                    <td>
                                        <span class="admin-badge <?php echo (string) ($row['status'] ?? '') === 'published' ? 'is-success' : 'is-warning'; ?>">
                                            <?php echo (string) ($row['status'] ?? '') === 'published' ? '已发布' : '草稿'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo e((string) ($row['color_tag'] ?: 'slate')); ?></td>
                                    <td>
                                        <?php
                                        $tops = array();
                                        if (!empty($row['is_top_forever'])) {
                                            $tops[] = '永久';
                                        }
                                        if (!empty($row['is_top_admin'])) {
                                            $tops[] = '后台';
                                        }
                                        if (!empty($row['is_top_normal'])) {
                                            $tops[] = '普通';
                                        }
                                        ?>
                                        <?php echo e(!empty($tops) ? implode(' / ', $tops) : '-'); ?>
                                    </td>
                                    <td><?php echo e(format_datetime($row['created_at'] ?? null)); ?></td>
                                    <td>
                                        <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=posts&edit=' . (int) $row['id']); ?>">编辑</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            <?php else: ?>
                <div class="admin-empty mt-4">当前没有符合条件的帖子。</div>
            <?php endif; ?>

            <div class="admin-tip">说明：批量删除会直接删除现有帖子记录，不保留回收站。这一行为已经明确保留为当前项目的真实逻辑，没有额外加兼容分支。</div>
        </div>
    </div>

    <div class="admin-card front-card">
        <h2 class="admin-card-title"><?php echo !empty($postForm['id']) ? '编辑帖子' : '新增帖子'; ?></h2>
        <div class="admin-card-subtitle">这里写入的是前台真实帖子数据，保存后会直接参与前台帖子列表、帖子详情和付费查看逻辑。</div>

        <?php if ($postCanManage): ?>
            <form method="post" action="<?php echo e(public_url('admin.php') . '?page=posts'); ?>" class="mt-4">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.posts')); ?>">
                <input type="hidden" name="_admin_form" value="page">
                <input type="hidden" name="_admin_action" value="save_post">
                <input type="hidden" name="id" value="<?php echo e((string) ($postForm['id'] ?? 0)); ?>">

                <div class="admin-form-grid">
                    <div>
                        <label class="admin-label">发布分区</label>
                        <select class="admin-select" name="region">
                            <option value="macau" <?php echo (string) ($postForm['region'] ?? 'macau') === 'macau' ? 'selected' : ''; ?>>澳门</option>
                            <option value="hongkong" <?php echo (string) ($postForm['region'] ?? 'macau') === 'hongkong' ? 'selected' : ''; ?>>香港</option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">销售价格</label>
                        <input class="admin-input" type="number" name="price" value="<?php echo e((string) ($postForm['price'] ?? '0')); ?>" step="1" min="0">
                    </div>
                </div>

                <div class="mt-4">
                    <label class="admin-label">帖子标题</label>
                    <input class="admin-input" name="title" value="<?php echo e((string) ($postForm['title'] ?? '')); ?>">
                </div>

                <div class="admin-form-grid mt-4">
                    <div>
                        <label class="admin-label">色签</label>
                        <select class="admin-select" name="color_tag">
                            <option value="slate" <?php echo (string) ($postForm['color_tag'] ?? 'slate') === 'slate' ? 'selected' : ''; ?>>默认</option>
                            <option value="red" <?php echo (string) ($postForm['color_tag'] ?? 'slate') === 'red' ? 'selected' : ''; ?>>红签</option>
                            <option value="green" <?php echo (string) ($postForm['color_tag'] ?? 'slate') === 'green' ? 'selected' : ''; ?>>绿签</option>
                            <option value="gold" <?php echo (string) ($postForm['color_tag'] ?? 'slate') === 'gold' ? 'selected' : ''; ?>>金签</option>
                        </select>
                    </div>
                    <div>
                        <label class="admin-label">发布状态</label>
                        <select class="admin-select" name="status">
                            <option value="published" <?php echo (string) ($postForm['status'] ?? 'published') === 'published' ? 'selected' : ''; ?>>已发布</option>
                            <option value="draft" <?php echo (string) ($postForm['status'] ?? 'published') === 'draft' ? 'selected' : ''; ?>>草稿</option>
                        </select>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="admin-label">摘要</label>
                    <textarea class="admin-textarea" name="excerpt"><?php echo e((string) ($postForm['excerpt'] ?? '')); ?></textarea>
                </div>

                <div class="mt-4">
                    <label class="admin-label">付费前预览内容</label>
                    <textarea class="admin-textarea" name="preview_content"><?php echo e((string) ($postForm['preview_content'] ?? '')); ?></textarea>
                </div>

                <div class="mt-4">
                    <label class="admin-label">正文内容</label>
                    <textarea class="admin-textarea" name="full_content"><?php echo e((string) ($postForm['full_content'] ?? '')); ?></textarea>
                </div>

                <div class="mt-4">
                    <label class="admin-label">置顶选项</label>
                    <div class="admin-check-row">
                        <label class="admin-check-item">
                            <input type="checkbox" name="is_top_normal" value="1" <?php echo (string) ($postForm['is_top_normal'] ?? '0') === '1' ? 'checked' : ''; ?>>
                            <span>普通置顶</span>
                        </label>
                        <label class="admin-check-item">
                            <input type="checkbox" name="is_top_admin" value="1" <?php echo (string) ($postForm['is_top_admin'] ?? '0') === '1' ? 'checked' : ''; ?>>
                            <span>后台置顶</span>
                        </label>
                        <label class="admin-check-item">
                            <input type="checkbox" name="is_top_forever" value="1" <?php echo (string) ($postForm['is_top_forever'] ?? '0') === '1' ? 'checked' : ''; ?>>
                            <span>永久置顶</span>
                        </label>
                    </div>
                </div>

                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">保存帖子</button>
                    <a class="admin-button is-light" href="<?php echo e(public_url('admin.php') . '?page=posts'); ?>">新建空白表单</a>
                </div>
            </form>
        <?php else: ?>
            <div class="admin-empty mt-4">当前账号只有查看权限，不能直接维护帖子内容。</div>
        <?php endif; ?>
    </div>
</section>
