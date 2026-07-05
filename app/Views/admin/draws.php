<?php
$drawFilters = isset($drawFilters) && is_array($drawFilters) ? $drawFilters : array();
$drawEditor = isset($drawEditor) && is_array($drawEditor) ? $drawEditor : array();
$drawCanManage = !empty($drawCanManage);

$drawMode = in_array((string) ($drawFilters['mode'] ?? ''), array('material', 'component'), true)
    ? (string) $drawFilters['mode']
    : 'material';
$currentRegion = in_array((string) ($drawFilters['region'] ?? ''), array('macau', 'hongkong'), true)
    ? (string) $drawFilters['region']
    : 'macau';
$currentComponent = 'float_group';

$regionLabels = array(
    'macau' => '澳门',
    'hongkong' => '香港',
);
$componentLabels = array(
    'float_group' => '悬浮组件',
);

$currentRegionLabel = $regionLabels[$currentRegion];
$currentComponentLabel = $componentLabels[$currentComponent];
$drawEditorBodyClass = 'is-live-head-fit-pending admin-draw-preview page-frame';
if ($drawMode === 'component') {
    $drawEditorBodyClass .= ' admin-draw-component-preview admin-draw-component-preview--' . str_replace('_', '-', $currentComponent);
}
$currentEditorLabel = $drawMode === 'component' ? $currentComponentLabel : ($currentRegionLabel . '资料');
$editorContent = isset($drawEditor['content_html']) ? (string) $drawEditor['content_html'] : '';
$saveAction = $drawMode === 'component' ? 'save_draw_component' : 'save_draw_material';
$saveButtonText = $drawMode === 'component' ? ('保存' . $currentComponentLabel) : ('保存' . $currentRegionLabel . '资料');
$tipText = $drawMode === 'component'
    ? '组件编辑当前会默认载入首页模板里的顶部悬浮和底部悬浮结构。你保存后，首页和底部悬浮导航会优先读取这里的组件内容。'
    : '澳门和香港资料编辑器现在会直接载入首页当前主体 HTML，框架结构、文案内容和样式类名会一起带入。保存后，前台首页会优先使用这里的主体内容。';

$buildDrawLink = static function (array $overrides = array()) use ($drawMode, $currentRegion, $currentComponent) {
    $params = array(
        'page' => 'draws',
        'mode' => $drawMode,
        'region' => $currentRegion,
        'component' => $currentComponent,
    );

    foreach ($overrides as $key => $value) {
        $params[$key] = $value;
    }

    return public_url('admin.php') . '?' . http_build_query($params);
};

$fullscreenStorageKey = 'draw-editor-fullscreen:' . $drawMode . ':' . $currentRegion . ':' . $currentComponent;
$latestRegionDraw = isset($drawLatestRegionDraw) && is_array($drawLatestRegionDraw) ? $drawLatestRegionDraw : app()->prediction()->latestHomepageDraw($currentRegion);
$normalizeIssueTail = static function ($issueNo) {
    $text = trim((string) $issueNo);

    if ($text === '' || !preg_match('/^\d+$/', $text)) {
        return '--';
    }

    $tail = strlen($text) > 3 ? substr($text, -3) : $text;

    return str_pad($tail, 3, '0', STR_PAD_LEFT);
};
$latestRegionDrawPreviewNumbers = array();
$latestRegionDrawPreviewSpecialNumber = 0;

if (is_array($latestRegionDraw)) {
    $latestRegionDrawPreviewNumbersSource = isset($latestRegionDraw['numbers']) && is_array($latestRegionDraw['numbers'])
        ? array_values($latestRegionDraw['numbers'])
        : json_decode((string) ($latestRegionDraw['numbers_json'] ?? '[]'), true);
    $latestRegionDrawPreviewNumbersSource = is_array($latestRegionDrawPreviewNumbersSource) ? $latestRegionDrawPreviewNumbersSource : array();

    foreach (array_slice($latestRegionDrawPreviewNumbersSource, 0, 6) as $latestRegionDrawPreviewNumber) {
        $latestRegionDrawPreviewNumber = (int) $latestRegionDrawPreviewNumber;
        if ($latestRegionDrawPreviewNumber > 0) {
            $latestRegionDrawPreviewNumbers[] = $latestRegionDrawPreviewNumber;
        }
    }

    $latestRegionDrawPreviewSpecialNumber = (int) ($latestRegionDraw['special_number'] ?? 0);
}

$latestRegionDrawPreviewPayload = array(
    'issue_no' => is_array($latestRegionDraw) ? trim((string) ($latestRegionDraw['issue_no'] ?? '')) : '',
    'draw_date' => is_array($latestRegionDraw) ? trim((string) ($latestRegionDraw['draw_date'] ?? '')) : '',
    'open_time' => is_array($latestRegionDraw) ? trim((string) ($latestRegionDraw['open_time'] ?? '')) : '',
    'next_open_time' => is_array($latestRegionDraw) ? trim((string) ($latestRegionDraw['next_open_time'] ?? '')) : '',
    'numbers' => $latestRegionDrawPreviewNumbers,
    'special_number' => $latestRegionDrawPreviewSpecialNumber,
);
$managedRegionIssue = app()->admins()->managedIssuePrefixSnapshotByRegion($currentRegion);
$managedRegionIssuePreviewPayload = array(
    'issue_no' => is_array($managedRegionIssue) ? trim((string) ($managedRegionIssue['issue_no'] ?? '')) : '',
    'issue_prefix_tail' => is_array($managedRegionIssue) ? trim((string) ($managedRegionIssue['issue_prefix_tail'] ?? '')) : '',
    'issue_prefix_text' => is_array($managedRegionIssue) ? trim((string) ($managedRegionIssue['issue_prefix_text'] ?? '')) : '',
);
$latestRegionDrawLabel = $currentRegion === 'hongkong' ? '香港' : '澳门';
$latestRegionIssueText = '--期';
$latestRegionNumbersText = '暂无数据';

if (is_array($latestRegionDraw)) {
    $issueTail = $normalizeIssueTail($latestRegionDraw['issue_no'] ?? '');
    $drawNumbers = json_decode((string) ($latestRegionDraw['numbers_json'] ?? '[]'), true);
    $drawNumbers = is_array($drawNumbers) ? $drawNumbers : array();
    $formattedDrawNumbers = array();

    foreach ($drawNumbers as $drawNumber) {
        $drawNumber = (int) $drawNumber;
        if ($drawNumber > 0) {
            $formattedDrawNumbers[] = str_pad((string) $drawNumber, 2, '0', STR_PAD_LEFT);
        }
    }

    $specialNumber = (int) ($latestRegionDraw['special_number'] ?? 0);
    $latestRegionIssueText = $issueTail . '期';
    $latestRegionNumbersText = implode(' ', $formattedDrawNumbers);
    if ($specialNumber > 0) {
        $latestRegionNumbersText .= ($latestRegionNumbersText !== '' ? ' + ' : '') . str_pad((string) $specialNumber, 2, '0', STR_PAD_LEFT);
    }
    if ($latestRegionNumbersText === '') {
        $latestRegionNumbersText = '暂无数据';
    }
}

$waveRed = array(1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46);
$waveBlue = array(3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48);
$latestRegionDrawDate = is_array($latestRegionDraw) ? trim((string) ($latestRegionDraw['draw_date'] ?? '')) : '';
$fivePhaseGroups = array(
    '金' => array(2, 3, 10, 11, 24, 25, 32, 33, 40, 41),
    '木' => array(6, 7, 14, 15, 22, 23, 36, 37, 44, 45),
    '水' => array(12, 13, 20, 21, 28, 29, 42, 43),
    '火' => array(1, 8, 9, 16, 17, 30, 31, 38, 39, 46, 47),
    '土' => array(4, 5, 18, 19, 26, 27, 34, 35, 48, 49),
);
$padDrawNumber = static function ($value) {
    $number = (int) $value;

    return $number < 10 ? '0' . $number : (string) $number;
};
$findDrawGroupName = static function (array $groups, int $value, string $fallback = '--') {
    foreach ($groups as $groupName => $numbers) {
        if (in_array($value, $numbers, true)) {
            return $groupName;
        }
    }

    return $fallback;
};
$waveColorClass = static function (int $value) use ($waveRed, $waveBlue) {
    if (in_array($value, $waveRed, true)) {
        return 'is-red';
    }

    if (in_array($value, $waveBlue, true)) {
        return 'is-blue';
    }

    return 'is-green';
};
$formatAdminDrawOpenTimeText = static function (?array $draw) {
    $nextOpenTime = trim((string) ($draw['next_open_time'] ?? ''));
    $openTime = trim((string) ($draw['open_time'] ?? ''));
    $value = $nextOpenTime !== '' ? $nextOpenTime : $openTime;

    return '下期开奖：' . ($value !== '' ? substr($value, 0, 16) : '--');
};
$sumAdminDrawOddEven = static function (int $value) {
    $digits = str_split((string) abs($value));
    $sum = 0;

    foreach ($digits as $digit) {
        $sum += (int) $digit;
    }

    return $sum % 2 === 0 ? '合数双' : '合数单';
};
$renderAdminDrawBall = static function (?int $value) use ($padDrawNumber, $waveColorClass, $latestRegionDrawDate) {
    $ballClass = $value === null ? 'is-empty' : $waveColorClass($value);
    $numberText = $value === null ? '--' : $padDrawNumber($value);
    $zodiacText = $value === null ? '--' : (app()->prediction()->drawZodiacByNumber($value, $latestRegionDrawDate) ?: '--');

    return '<div class="admin-editor-live-ball ' . e($ballClass) . '">' .
        '<div class="admin-editor-live-ball-code">' . e($numberText) . '</div>' .
        '<div class="admin-editor-live-ball-zodiac">' . e($zodiacText) . '</div>' .
        '</div>';
};
$latestRegionDrawLabel = $currentRegion === 'hongkong' ? '香港' : '澳门';
$latestRegionIssueText = '--期';
$latestRegionOpenTimeText = '下期开奖：--';
$latestRegionMetaZodiac = '--';
$latestRegionMetaOddEven = '--';
$latestRegionMetaFivePhase = '--';
$latestRegionBallsHtml = '';

if (is_array($latestRegionDraw)) {
    $issueTail = $normalizeIssueTail($latestRegionDraw['issue_no'] ?? '');
    $drawNumbers = isset($latestRegionDraw['numbers']) && is_array($latestRegionDraw['numbers'])
        ? array_values($latestRegionDraw['numbers'])
        : json_decode((string) ($latestRegionDraw['numbers_json'] ?? '[]'), true);
    $drawNumbers = is_array($drawNumbers) ? $drawNumbers : array();
    $specialNumber = (int) ($latestRegionDraw['special_number'] ?? 0);

    $latestRegionIssueText = $issueTail . '期';
    $latestRegionOpenTimeText = $formatAdminDrawOpenTimeText($latestRegionDraw);

    for ($drawIndex = 0; $drawIndex < 6; $drawIndex += 1) {
        $drawValue = array_key_exists($drawIndex, $drawNumbers) ? (int) $drawNumbers[$drawIndex] : null;
        $latestRegionBallsHtml .= $renderAdminDrawBall($drawValue > 0 ? $drawValue : null);
    }

    $latestRegionBallsHtml .= '<div class="admin-editor-live-ball-plus">+</div>';
    $latestRegionBallsHtml .= $renderAdminDrawBall($specialNumber > 0 ? $specialNumber : null);

    if ($specialNumber > 0) {
        $latestRegionMetaZodiac = app()->prediction()->drawZodiacByNumber($specialNumber, $latestRegionDrawDate) ?: '--';
        $latestRegionMetaOddEven = $sumAdminDrawOddEven($specialNumber);
        $latestRegionMetaFivePhase = $findDrawGroupName($fivePhaseGroups, $specialNumber, '--');
    }
}

if ($latestRegionBallsHtml === '') {
    for ($drawIndex = 0; $drawIndex < 6; $drawIndex += 1) {
        $latestRegionBallsHtml .= $renderAdminDrawBall(null);
    }
    $latestRegionBallsHtml .= '<div class="admin-editor-live-ball-plus">+</div>';
    $latestRegionBallsHtml .= $renderAdminDrawBall(null);
}
?><?php if ($drawCanManage): ?><script>
(function () {
    try {
        if (window.sessionStorage && window.sessionStorage.getItem('<?php echo e($fullscreenStorageKey); ?>') === '1') {
            document.documentElement.classList.add('draw-editor-is-fullscreen-pending');
            if (document.body) {
                document.body.classList.add('draw-editor-is-fullscreen');
                document.body.classList.add('draw-editor-is-fullscreen-pending');
            }
        }
    } catch (error) {
        // Ignore storage errors and fall back to the normal layout.
    }
})();
</script><?php else: ?><script>
(function () {
    document.documentElement.classList.remove('draw-editor-is-fullscreen-pending');
    if (document.body) {
        document.body.classList.remove('draw-editor-is-fullscreen');
        document.body.classList.remove('draw-editor-is-fullscreen-pending');
    }
    try {
        if (window.sessionStorage) {
            window.sessionStorage.removeItem('<?php echo e($fullscreenStorageKey); ?>');
        }
    } catch (error) {
        // Ignore storage errors and keep the normal layout.
    }
})();
</script><?php endif; ?><section>
    <div class="admin-card front-card admin-draws-card">
        <div class="admin-draws-filter-shell">
            <div class="admin-filter-chip-group">
                <a class="admin-filter-chip admin-draws-mode-chip is-macau <?php echo $drawMode === 'material' && $currentRegion === 'macau' ? 'is-active' : ''; ?>" href="<?php echo e($buildDrawLink(array('mode' => 'material', 'region' => 'macau'))); ?>">澳门资料更新</a>
                <a class="admin-filter-chip admin-draws-mode-chip is-hongkong <?php echo $drawMode === 'material' && $currentRegion === 'hongkong' ? 'is-active' : ''; ?>" href="<?php echo e($buildDrawLink(array('mode' => 'material', 'region' => 'hongkong'))); ?>">香港资料更新</a>
                <a class="admin-filter-chip admin-draws-mode-chip is-component <?php echo $drawMode === 'component' ? 'is-active' : ''; ?>" href="<?php echo e($buildDrawLink(array('mode' => 'component'))); ?>">组件管理</a>
            </div>
        </div>
        <?php if ($drawCanManage): ?>
            <form class="mt-4" id="draw-material-form" method="post" action="<?php echo e(public_url('admin.php') . '?page=draws&mode=' . urlencode($drawMode) . '&region=' . urlencode($currentRegion) . '&component=' . urlencode($currentComponent)); ?>" data-draw-material-form>
                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.draws')); ?>">
                <input type="hidden" name="_admin_form" value="page">
                <input type="hidden" name="_admin_action" value="<?php echo e($saveAction); ?>">
                <input type="hidden" name="mode" value="<?php echo e($drawMode); ?>">
                <input type="hidden" name="region" value="<?php echo e($currentRegion); ?>">
                <input type="hidden" name="component_key" value="<?php echo e($currentComponent); ?>">

                <div class="admin-editor-toolbar">
                    <div class="admin-editor-toolbar-main">
                        <div class="admin-badge is-info">
                            <span>当前编辑：</span>
                            <span><?php echo e($currentEditorLabel); ?></span>
                        </div>
                        <?php if (empty($pageTitleLiveSyncHtml)): ?>
                        <div class="admin-editor-live-sync">
                            <div class="admin-editor-live-sync-head">
                                <div class="admin-editor-live-sync-left">
                                    <span class="admin-editor-live-sync-badge"><?php echo e($latestRegionDrawLabel); ?></span>
                                    <span class="admin-editor-live-sync-issue"><?php echo e($latestRegionIssueText); ?></span>
                                </div>
                            </div>
                            <div class="admin-editor-live-sync-balls"><?php echo $latestRegionBallsHtml; ?></div>
                            <div class="admin-editor-live-sync-meta">
                                <span class="admin-editor-live-sync-meta-item">特码生肖:<strong><?php echo e($latestRegionMetaZodiac); ?></strong></span>
                                <span class="admin-editor-live-sync-meta-item">合数单双:<strong><?php echo e($latestRegionMetaOddEven); ?></strong></span>
                                <span class="admin-editor-live-sync-meta-item">五行:<strong><?php echo e($latestRegionMetaFivePhase); ?></strong></span>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="admin-editor-shell mt-4">
                    <div class="admin-editor-boot-placeholder" data-draw-editor-placeholder>编辑器加载中...</div>
                    <textarea class="admin-rich-textarea is-editor-booting" id="draw-material-editor" name="content_html"><?php echo e($editorContent); ?></textarea>
                </div>
                <div class="admin-editor-save-slot" data-draw-save-slot hidden>
                    <button class="admin-button admin-editor-save-button" type="submit" form="draw-material-form"><?php echo e($saveButtonText); ?></button>
                </div>
            </form>

            <div class="admin-section-editor-modal" data-section-editor-modal hidden>
                <div class="admin-section-editor-backdrop" data-section-editor-close></div>
                <div class="admin-section-editor-dialog" role="dialog" aria-modal="true" aria-labelledby="draw-section-editor-title">
                    <div class="admin-section-editor-header">
                        <div>
                            <div class="admin-section-editor-title-row">
                                <div class="admin-section-editor-title" id="draw-section-editor-title">广告设置</div>
                                <label class="admin-section-editor-lock-toggle">
                                    <input type="checkbox" data-section-editor-field="edit-lock">
                                    <span>锁定编辑</span>
                                </label>
                            </div>
                            <div class="admin-section-editor-subtitle" data-section-editor-target>请选择要编辑的主卡片</div>
                        </div>
                        <div class="admin-section-editor-header-actions">
                            <button class="admin-button" type="submit" form="section-editor-form">保存</button>
                            <button class="admin-button is-light" type="button" data-section-editor-close>关闭</button>
                        </div>
                    </div>

                    <div class="admin-section-editor-body">
                        <form id="section-editor-form" data-section-editor-form>
                            <div class="admin-section-editor-grid">
                                <div class="admin-section-editor-panel admin-section-editor-title-panel">
                                    <div class="admin-section-editor-panel-title">标题栏</div>
                                    <div class="admin-section-editor-fields">
                                        <div data-section-editor-deleted-field="title-html" hidden>
                                            <label class="admin-label" for="section-editor-title-html">标题 HTML / 文案</label>
                                            <textarea class="admin-textarea admin-section-editor-textarea" id="section-editor-title-html" data-section-editor-field="title-html"></textarea>
                                            <div class="admin-help">支持图标、文案和简单 HTML，右侧编辑按钮不会保存到前台。</div>
                                        </div>

                                        <div>
                                            <label class="admin-label" for="section-editor-title-class">标题类名</label>
                                            <input class="admin-input" id="section-editor-title-class" type="text" data-section-editor-field="title-class">
                                        </div>

                                        <div data-section-editor-deleted-field="title-style" hidden>
                                            <label class="admin-label" for="section-editor-title-style">标题内联样式</label>
                                            <textarea class="admin-textarea admin-section-editor-textarea" id="section-editor-title-style" data-section-editor-field="title-style"></textarea>
                                        </div>

                                        <div data-section-editor-ad-copy-panel hidden>
                                            <div class="admin-section-editor-ad-copy-actions">
                                                <button class="admin-button admin-section-editor-ad-copy-add-button" type="button" data-section-editor-ad-copy-add>增加广告位置</button>
                                            </div>
                                            <div class="admin-section-editor-ad-copy-list" data-section-editor-ad-copy-list></div>
                                        </div>

                                        <div class="admin-section-editor-expert-ad-panel" data-section-editor-expert-ad-panel hidden>
                                            <div class="admin-section-editor-expert-ad-head">
                                                <div class="admin-section-editor-expert-ad-action-row">
                                                    <label class="admin-label" for="section-editor-expert-ad-interval">广告插入间隔</label>
                                                    <input class="admin-input" id="section-editor-expert-ad-interval" type="number" min="0" max="99" step="1" data-section-editor-field="expert-ad-interval" placeholder="0">
                                                    <button class="admin-button admin-section-editor-ad-copy-add-button" type="button" data-section-editor-expert-ad-add>增加广告位置</button>
                                                </div>
                                                <div class="admin-help admin-section-editor-expert-ad-help">每隔 N 条高手帖子插入 1 条广告帖子位置；有广告时留空默认每 1 条插入，填 0 关闭。</div>
                                            </div>
                                            <div class="admin-section-editor-expert-ad-list" data-section-editor-expert-ad-list></div>
                                        </div>

                                        <div class="admin-section-editor-color-grid admin-section-editor-title-color-grid admin-section-editor-title-color-grid--compact">
                                            <div class="admin-section-editor-color-field">
                                                <label class="admin-label" for="section-editor-title-start">标题背景起始色</label>
                                                <input id="section-editor-title-start" type="color" value="#1e40af" data-section-editor-field="title-start">
                                            </div>
                                            <div class="admin-section-editor-color-field">
                                                <label class="admin-label" for="section-editor-title-end">标题背景结束色</label>
                                                <input id="section-editor-title-end" type="color" value="#3b82f6" data-section-editor-field="title-end">
                                            </div>
                                            <div class="admin-section-editor-color-field">
                                                <label class="admin-label" for="section-editor-title-color">标题文字颜色</label>
                                                <input id="section-editor-title-color" type="color" value="#ffffff" data-section-editor-field="title-color">
                                            </div>
                                            <div class="admin-section-editor-color-field" data-section-editor-ad-border-grid hidden>
                                                <label class="admin-label" for="section-editor-ad-item-border">广告位边框色</label>
                                                <input id="section-editor-ad-item-border" type="color" value="#d9e2ef" data-section-editor-field="ad-item-border">
                                            </div>
                                        </div>

                                        <div class="admin-section-editor-color-grid admin-section-editor-title-color-grid">
                                            <div>
                                                <label class="admin-label" for="section-editor-title-align">标题位置</label>
                                                <select class="admin-select" id="section-editor-title-align" data-section-editor-field="title-align">
                                                    <option value="left">左对齐</option>
                                                    <option value="center">居中</option>
                                                    <option value="right">右对齐</option>
                                                </select>
                                            </div>
                                            <div data-section-editor-title-icon-field>
                                                <label class="admin-label" for="section-editor-title-icon">标题图标</label>
                                                <div class="admin-section-editor-title-icon-upload-row">
                                                    <input class="admin-input" id="section-editor-title-icon" type="text" data-section-editor-field="title-icon" autocomplete="off" placeholder="/public/uploads/material/icon.png" readonly>
                                                    <button class="admin-button admin-section-editor-title-icon-upload-button" type="button" data-section-editor-title-icon-upload>上传图标</button>
                                                </div>
                                            </div>
                                            <div data-section-editor-title-text-field>
                                                <label class="admin-label" for="section-editor-title-text">标题正文</label>
                                                <input class="admin-input" id="section-editor-title-text" type="text" data-section-editor-field="title-text" autocomplete="off" placeholder="例如：广告区、高手榜区、资料区">
                                            </div>
                                        </div>

                                        <div class="admin-section-editor-title-visual-preview" data-section-editor-title-preview>
                                            <div class="admin-section-editor-title-preview-header">
                                                <div class="admin-section-editor-title-preview-heading">标题栏实时预览</div>
                                                <div class="admin-section-editor-title-preview-mode" data-section-editor-title-preview-mode>渐变背景</div>
                                            </div>
                                            <div class="admin-section-editor-title-preview-shell">
                                                <div class="admin-section-editor-title-preview-bar" data-section-editor-title-preview-bar>
                                                    <span class="admin-section-editor-title-preview-text" data-section-editor-title-preview-text>标题区预览</span>
                                                </div>
                                                <div class="admin-section-editor-title-preview-body" data-section-editor-title-preview-body></div>
                                            </div>
                                        </div>

                                        <div data-section-editor-marquee-field hidden>
                                            <label class="admin-label" for="section-editor-marquee-text">公告内容</label>
                                            <textarea class="admin-textarea admin-section-editor-textarea" id="section-editor-marquee-text" data-section-editor-field="marquee-text"></textarea>
                                            <div class="admin-help">这里直接修改公告滚动文案，保存时会同步更新两段跑马灯内容。</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="admin-section-editor-panel">
                                    <div class="admin-section-editor-panel-title">主卡片内容区</div>
                                    <div class="admin-section-editor-fields">
                                        <div data-section-editor-internal-field="card-style" hidden>
                                            <input id="section-editor-card-style" type="hidden" data-section-editor-field="card-style">
                                        </div>
                                        <div data-section-editor-header-background-panel hidden>
                                            <label class="admin-label" for="section-editor-card-background-url">版头背景图片（主卡片 #1 占位约 708×286，上传后拉满）</label>
                                            <div class="admin-section-editor-color-grid">
                                                <div>
                                                    <input class="admin-input" id="section-editor-card-background-url" type="text" data-section-editor-field="card-background-url" placeholder="/public/uploads/material/hero-bg.jpg" readonly>
                                                </div>
                                                <div>
                                                    <button class="admin-button" type="button" data-section-editor-header-background-upload>上传更换背景</button>
                                                </div>
                                            </div>
                                            <div class="admin-section-editor-color-grid">
                                                <div>
                                                    <label class="admin-label" for="section-editor-card-slot-width">占位宽度</label>
                                                    <input class="admin-input" id="section-editor-card-slot-width" type="number" min="320" max="708" step="1" value="708" data-section-editor-field="card-slot-width">
                                                </div>
                                                <div>
                                                    <label class="admin-label" for="section-editor-card-slot-height">占位高度</label>
                                                    <input class="admin-input" id="section-editor-card-slot-height" type="number" min="120" max="520" step="1" value="286" data-section-editor-field="card-slot-height">
                                                </div>
                                            </div>
                                            <div class="admin-section-editor-color-field">
                                                <label class="admin-label" for="section-editor-card-background-overlay">笼罩深浅</label>
                                                <input class="admin-input" id="section-editor-card-background-overlay" type="range" min="0" max="90" step="5" value="60" data-section-editor-field="card-background-overlay">
                                            </div>
                                            <div class="admin-section-editor-header-background-preview" data-section-editor-header-background-preview>未设置自定义背景图</div>
                                            <div class="admin-help">上传后会按主卡片区域拉满占位显示，不会因为图片尺寸过大把主卡片撑高。</div>
                                        </div>

                                        <div class="admin-section-editor-color-grid" data-section-editor-card-text-style-fields>
                                            <div>
                                                <label class="admin-label" for="section-editor-card-font-size">当前卡片文字大小</label>
                                                <input class="admin-input" id="section-editor-card-font-size" type="text" data-section-editor-field="card-font-size" placeholder="例如：16px">
                                            </div>
                                            <div class="admin-section-editor-color-field">
                                                <label class="admin-label" for="section-editor-card-text-color">当前卡片文字颜色</label>
                                                <input id="section-editor-card-text-color" type="color" value="#0f172a" data-section-editor-field="card-text-color">
                                            </div>
                                        </div>

                                        <div data-section-editor-deleted-field="section-class" hidden>
                                            <input id="section-editor-section-class" type="hidden" data-section-editor-field="section-class">
                                        </div>

                                        <div data-section-editor-deleted-field="section-style" hidden>
                                            <input id="section-editor-section-style" type="hidden" data-section-editor-field="section-style">
                                        </div>

                                        <div>
                                            <label class="admin-label" for="section-editor-body-class">内容区类名</label>
                                            <input class="admin-input" id="section-editor-body-class" type="text" data-section-editor-field="body-class">
                                        </div>

                                        <div>
                                            <label class="admin-label" for="section-editor-body-style">内容区内联样式</label>
                                            <textarea class="admin-textarea admin-section-editor-textarea" id="section-editor-body-style" data-section-editor-field="body-style"></textarea>
                                        </div>

                                        <div class="admin-section-editor-color-grid">
                                            <div class="admin-section-editor-color-field">
                                                <label class="admin-label" for="section-editor-body-background">内容区背景色</label>
                                                <input id="section-editor-body-background" type="color" value="#ffffff" data-section-editor-field="body-background">
                                            </div>
                                            <div class="admin-section-editor-color-field">
                                                <label class="admin-label" for="section-editor-body-border">内容区边框色</label>
                                                <input id="section-editor-body-border" type="color" value="#d9e2ef" data-section-editor-field="body-border">
                                            </div>
                                        </div>

                                        <div data-section-editor-deleted-field="body-html" hidden>
                                            <textarea id="section-editor-body-html" data-section-editor-field="body-html" hidden></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="admin-empty mt-4">当前账号只有查看权限，不能修改资料和组件内容。</div>
        <?php endif; ?>

        <div class="admin-tip">
            <?php echo e($tipText); ?>
        </div>
    </div>
</section>
<?php if ($drawCanManage): ?>
<script src="<?php echo e(asset('vendor/tinymce/tinymce.min.js?v=8.4.0-local')); ?>" defer></script>
<script>
(function () {
    var drawTinyMceWaitTimer = 0;
    var drawTinyMceWaitAttempts = 0;
    var warmedDrawModeLinks = {};
    var waitForDrawTinyMce = function (callback, fallback) {
        window.clearTimeout(drawTinyMceWaitTimer);

        if (typeof window.tinymce !== 'undefined') {
            callback();
            return;
        }

        drawTinyMceWaitAttempts += 1;
        if (drawTinyMceWaitAttempts >= 80) {
            if (typeof fallback === 'function') {
                fallback();
            }
            return;
        }

        drawTinyMceWaitTimer = window.setTimeout(function () {
            waitForDrawTinyMce(callback, fallback);
        }, 50);
    };
    var warmDrawModeLink = function (href) {
        var cleanHref = String(href || '').replace(/#.*$/, '');
        var currentHref = String(window.location.href || '').replace(/#.*$/, '');
        var link = null;

        if (!cleanHref || cleanHref === currentHref || warmedDrawModeLinks[cleanHref]) {
            return;
        }

        warmedDrawModeLinks[cleanHref] = true;
        link = document.createElement('link');
        link.rel = 'prefetch';
        link.as = 'document';
        link.href = cleanHref;
        link.setAttribute('fetchpriority', 'low');
        document.head.appendChild(link);
    };
    var startDrawEditorPage = function () {
    var form = document.querySelector('[data-draw-material-form]');
    var textarea = document.getElementById('draw-material-editor');
    var editorBootPlaceholder = document.querySelector('[data-draw-editor-placeholder]');
    var saveButton = form ? form.querySelector('.admin-editor-save-button') : null;
    var saveButtonSlot = form ? form.querySelector('[data-draw-save-slot]') : null;
    var liveSyncBar = document.querySelector('[data-draws-live-sync]') || (form ? form.querySelector('.admin-editor-live-sync') : null);
    var csrfTokenInput = form ? form.querySelector('input[name="_token"]') : null;
    var normalizeDrawImageUploadUrl = function (url) {
        var target = (url || '').trim();

        if (!target || /^&(?:amp;)?;?$/i.test(target) || /\/&(?:amp;)?;?$/i.test(target)) {
            return window.location.pathname + window.location.search;
        }

        return target.replace(/&amp;/g, '&');
    };
    var drawImageUploadUrl = normalizeDrawImageUploadUrl(form ? form.getAttribute('action') : '');
    var drawImageAccept = '.jpg,.jpeg,.png,.gif,.webp,.bmp,image/jpeg,image/png,image/gif,image/webp,image/bmp,image/x-ms-bmp';
    var pageTitleShell = document.querySelector('.admin-page-title-shell');
    var pageTitleActionSlot = null;
    var pageTitleLiveSyncSlot = null;
    var pageTitleTextSlot = null;
    var drawsSectionTitle = document.querySelector('.admin-draws-section-title');
    var drawsCard = document.querySelector('.admin-draws-card');
    var adminMain = document.querySelector('.admin-main');
    var drawStickyLayoutTimer = 0;
    var liveSyncOriginalParent = liveSyncBar ? liveSyncBar.parentNode : null;
    var sectionEditorModal = document.querySelector('[data-section-editor-modal]');
    var sectionEditorForm = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-form]') : null;
    var sectionEditorTarget = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-target]') : null;
    var sectionEditorCloseButtons = sectionEditorModal ? sectionEditorModal.querySelectorAll('[data-section-editor-close]') : [];
    var sectionEditorSectionClass = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="section-class"]') : null;
    var sectionEditorSectionStyle = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="section-style"]') : null;
    var sectionEditorTitleHtml = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="title-html"]') : null;
    var sectionEditorTitleText = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="title-text"]') : null;
    var sectionEditorTitleIcon = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="title-icon"]') : null;
    var sectionEditorTitleIconUploadButton = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-title-icon-upload]') : null;
    var sectionEditorTitleClass = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="title-class"]') : null;
    var sectionEditorTitleStyle = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="title-style"]') : null;
    var sectionEditorTitleStart = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="title-start"]') : null;
    var sectionEditorTitleEnd = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="title-end"]') : null;
    var sectionEditorTitleColor = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="title-color"]') : null;
    var sectionEditorTitleAlign = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="title-align"]') : null;
    var sectionEditorTitlePreview = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-title-preview]') : null;
    var sectionEditorTitlePreviewBar = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-title-preview-bar]') : null;
    var sectionEditorTitlePreviewText = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-title-preview-text]') : null;
    var sectionEditorTitlePreviewBody = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-title-preview-body]') : null;
    var sectionEditorTitlePreviewMode = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-title-preview-mode]') : null;
    var sectionEditorAdCopyPanel = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-ad-copy-panel]') : null;
    var sectionEditorAdCopyList = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-ad-copy-list]') : null;
    var sectionEditorAdCopyAddButton = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-ad-copy-add]') : null;
    var sectionEditorAdBorderGrid = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-ad-border-grid]') : null;
    var sectionEditorAdItemBorder = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="ad-item-border"]') : null;
    var sectionEditorExpertAdPanel = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-expert-ad-panel]') : null;
    var sectionEditorExpertAdInterval = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="expert-ad-interval"]') : null;
    var sectionEditorExpertAdAddButton = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-expert-ad-add]') : null;
    var sectionEditorExpertAdList = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-expert-ad-list]') : null;
    var sectionEditorBodyClass = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="body-class"]') : null;
    var sectionEditorBodyStyle = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="body-style"]') : null;
    var sectionEditorBodyBackground = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="body-background"]') : null;
    var sectionEditorBodyBorder = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="body-border"]') : null;
    var sectionEditorBodyHtml = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="body-html"]') : null;
    var sectionEditorCardStyle = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="card-style"]') : null;
    var sectionEditorCardFontSize = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="card-font-size"]') : null;
    var sectionEditorCardTextColor = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="card-text-color"]') : null;
    var sectionEditorCardTextStyleFields = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-card-text-style-fields]') : null;
    var sectionEditorEditLock = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="edit-lock"]') : null;
    var sectionEditorCardBackgroundUrl = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="card-background-url"]') : null;
    var sectionEditorCardSlotWidth = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="card-slot-width"]') : null;
    var sectionEditorCardSlotHeight = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="card-slot-height"]') : null;
    var sectionEditorCardBackgroundOverlay = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="card-background-overlay"]') : null;
    var sectionEditorHeaderBackgroundPanel = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-header-background-panel]') : null;
    var sectionEditorHeaderBackgroundUploadButton = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-header-background-upload]') : null;
    var sectionEditorHeaderBackgroundPreview = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-header-background-preview]') : null;
    var sectionEditorMarqueeText = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-field="marquee-text"]') : null;
    var sectionEditorMarqueeField = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-marquee-field]') : null;
    var sectionEditorTitlePanel = sectionEditorTitleStart && sectionEditorTitleStart.closest ? sectionEditorTitleStart.closest('.admin-section-editor-panel') : null;
    var sectionEditorTitlePanelTitle = sectionEditorTitlePanel ? sectionEditorTitlePanel.querySelector('.admin-section-editor-panel-title') : null;
    var sectionEditorTitleIconField = sectionEditorTitleIcon && sectionEditorTitleIcon.closest ? sectionEditorTitleIcon.closest('[data-section-editor-title-icon-field]') : null;
    var sectionEditorTitleTextField = sectionEditorTitleText && sectionEditorTitleText.closest ? sectionEditorTitleText.closest('[data-section-editor-title-text-field]') : null;
    var sectionEditorTitleHtmlField = sectionEditorTitleHtml && sectionEditorTitleHtml.closest ? sectionEditorTitleHtml.closest('div') : null;
    var sectionEditorTitleStyleField = sectionEditorTitleStyle && sectionEditorTitleStyle.closest ? sectionEditorTitleStyle.closest('div') : null;
    var sectionEditorCardPanel = sectionEditorCardStyle && sectionEditorCardStyle.closest ? sectionEditorCardStyle.closest('.admin-section-editor-panel') : null;
    var sectionEditorAdCopyInputs = [];
    var sectionEditorExpertAdInputs = [];
    var sectionEditorTitlePreviewSwatches = sectionEditorModal ? {
        start: sectionEditorModal.querySelector('[data-section-editor-title-preview-swatch="start"]'),
        end: sectionEditorModal.querySelector('[data-section-editor-title-preview-swatch="end"]'),
        text: sectionEditorModal.querySelector('[data-section-editor-title-preview-swatch="text"]')
    } : null;
    var sectionEditorTitlePreviewValues = sectionEditorModal ? {
        start: sectionEditorModal.querySelector('[data-section-editor-title-preview-value="start"]'),
        end: sectionEditorModal.querySelector('[data-section-editor-title-preview-value="end"]'),
        text: sectionEditorModal.querySelector('[data-section-editor-title-preview-value="text"]')
    } : null;
    var sectionEditorBodyFields = [
        sectionEditorBodyClass,
        sectionEditorBodyStyle,
        sectionEditorBodyBackground,
        sectionEditorBodyBorder,
        sectionEditorBodyHtml
    ];
    var activeSectionState = {
        editor: null,
        blockEl: null,
        sectionEl: null,
        titleEl: null,
        bodyEl: null,
        styleTargetEl: null,
        textTargetEl: null,
        blockType: ''
    };
    var sectionSourceState = {
        editor: null,
        blockEl: null,
        panelEl: null,
        textareaEl: null,
        syncTimer: null,
        isSyncingFromSource: false
    };
    var sectionSourceButtonOpenText = '\u6e90\u7801';
    var sectionSourceButtonCloseText = '\u6536\u8d77\u6e90\u7801';
    var defaultSectionExpertAdTitle = '\u5e7f\u544a\u63a8\u8350';
    var lazyMaterialEditorControls = <?php echo $drawMode === 'material' ? 'true' : 'false'; ?>;
    var sectionEditorControlsActivated = !lazyMaterialEditorControls;
    var sectionDragState = {
        editor: null,
        doc: null,
        body: null,
        win: null,
        sectionEl: null,
        titleEl: null,
        placeholderEl: null,
        moved: false,
        isActive: false,
        startX: 0,
        startY: 0,
        pendingClientX: 0,
        pendingClientY: 0,
        rafId: 0
    };
    var sectionEditorBlockIdSeed = 0;
    var fontSizeToolbarState = {
        editor: null,
        groupEl: null,
        inputEl: null,
        bookmark: null
    };
    var saveRequestState = {
        pending: false,
        originalLabel: saveButton ? (saveButton.textContent || '') : ''
    };
    var fullscreenStorageKey = '<?php echo e($fullscreenStorageKey); ?>';
    var fullscreenState = {
        isFullscreen: false,
        shouldRestore: false,
        isSubmitting: false,
        isNavigatingAway: false,
        isRestoring: false,
        explicitToggle: false
    };
    var fullscreenLiveSyncBar = null;
    var adminPreviewRegion = '<?php echo e($currentRegion); ?>';
    var adminPreviewDraw = <?php echo json_encode($latestRegionDrawPreviewPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var adminPreviewCurrentIssue = <?php echo json_encode($managedRegionIssuePreviewPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var adminPreviewWaveRed = <?php echo json_encode(array_values($waveRed), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var adminPreviewWaveBlue = <?php echo json_encode(array_values($waveBlue), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var adminPreviewDrawZodiacAnimals = ['鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪'];
    var adminPreviewDrawLunarNewYearDates = {
        2020: '2020-01-25',
        2021: '2021-02-12',
        2022: '2022-02-01',
        2023: '2023-01-22',
        2024: '2024-02-10',
        2025: '2025-01-29',
        2026: '2026-02-17',
        2027: '2027-02-06',
        2028: '2028-01-26',
        2029: '2029-02-13',
        2030: '2030-02-03',
        2031: '2031-01-23',
        2032: '2032-02-11',
        2033: '2033-01-31',
        2034: '2034-02-19',
        2035: '2035-02-08'
    };
    var adminPreviewFivePhaseGroups = <?php echo json_encode($fivePhaseGroups, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var adminPreviewEarthlyBranches = ['子', '丑', '寅', '卯', '辰', '巳', '午', '未', '申', '酉', '戌', '亥'];
    var adminPreviewZodiacByBranch = {
        '子': '鼠',
        '丑': '牛',
        '寅': '虎',
        '卯': '兔',
        '辰': '龙',
        '巳': '蛇',
        '午': '马',
        '未': '羊',
        '申': '猴',
        '酉': '鸡',
        '戌': '狗',
        '亥': '猪'
    };
    var adminPreviewShaDirectionByBranch = {
        '子': '南',
        '丑': '东',
        '寅': '北',
        '卯': '西',
        '辰': '南',
        '巳': '东',
        '午': '北',
        '未': '西',
        '申': '南',
        '酉': '东',
        '戌': '北',
        '亥': '西'
    };
    var adminPreviewWeekdayNames = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];
    var adminPreviewDayBranchAnchorUtc = Date.UTC(2026, 2, 21);
    var adminPreviewDayBranchAnchorIndex = 6;
    var adminPreviewLiveTimer = 0;
    var scheduleEditorDecorationRefresh = function (editor, options) {
        var run = function () {
            if (!editor || editor.removed || !editor.getBody()) {
                return;
            }

            refreshDrawEditorDecorations(editor, options || {});
        };

        window.setTimeout(function () {
            if (window.requestIdleCallback) {
                window.requestIdleCallback(run, {
                    timeout: 700
                });
                return;
            }

            run();
        }, 160);
    };
    var clearDrawEditorBootState = function () {
        if (textarea) {
            textarea.classList.remove('is-editor-booting');
        }

        if (editorBootPlaceholder && editorBootPlaceholder.parentNode) {
            editorBootPlaceholder.parentNode.removeChild(editorBootPlaceholder);
        }

        if (document.body) {
            document.body.classList.remove('admin-draws-editor-booting');
        }
    };
    var bindDrawModeSwitchFeedback = function () {
        var links = document.querySelectorAll('.admin-draws-filter-shell .admin-filter-chip[href]');
        var currentHref = String(window.location.href || '').replace(/#.*$/, '');

        if (!links || !links.length) {
            return;
        }

        Array.prototype.forEach.call(links, function (link) {
            var warmCurrentLink = function () {
                warmDrawModeLink(link.href);
            };

            link.addEventListener('mouseenter', warmCurrentLink);
            link.addEventListener('focus', warmCurrentLink);
            link.addEventListener('click', function (event) {
                var targetHref = String(link.href || '').replace(/#.*$/, '');

                if (event.defaultPrevented
                    || (typeof event.button === 'number' && event.button !== 0)
                    || event.metaKey
                    || event.ctrlKey
                    || event.shiftKey
                    || event.altKey
                    || !targetHref
                    || targetHref === currentHref) {
                    return;
                }

                fullscreenState.isNavigatingAway = true;
                if (document.body) {
                    document.body.classList.add('admin-draws-switching');
                }
                if (drawsCard) {
                    drawsCard.classList.add('is-switching');
                }
                link.classList.add('is-loading');
                link.setAttribute('aria-busy', 'true');
            });
        });
    };

    bindDrawModeSwitchFeedback();

    if (drawsSectionTitle && drawsSectionTitle.parentNode) {
        drawsSectionTitle.parentNode.removeChild(drawsSectionTitle);
    }

    if (!form || !textarea) {
        clearDrawEditorBootState();
        if (saveButtonSlot) {
            saveButtonSlot.hidden = false;
            saveButtonSlot.style.display = '';
        }
        return;
    }

    if (typeof window.tinymce === 'undefined') {
        waitForDrawTinyMce(startDrawEditorPage, function () {
            setPendingFullscreenShell(false);
            if (textarea) {
                textarea.classList.add('is-editor-booting');
            }
            if (editorBootPlaceholder) {
                editorBootPlaceholder.textContent = '编辑器加载失败，请刷新页面重试。';
                editorBootPlaceholder.classList.add('is-error');
            }
            if (saveButtonSlot) {
                saveButtonSlot.hidden = true;
                saveButtonSlot.style.display = 'none';
            }
        });
        return;
    }

    var mountSaveButtonToPageTitle = function () {
        if (!saveButton || !pageTitleShell) {
            return false;
        }

        pageTitleTextSlot = pageTitleShell.querySelector('.admin-page-title-text-slot');
        if (!pageTitleTextSlot) {
            pageTitleTextSlot = document.createElement('div');
            pageTitleTextSlot.className = 'admin-page-title-text-slot';
            while (pageTitleShell.firstChild) {
                pageTitleTextSlot.appendChild(pageTitleShell.firstChild);
            }
            pageTitleShell.appendChild(pageTitleTextSlot);
        }

        if (saveButtonSlot) {
            saveButtonSlot.hidden = true;
            saveButtonSlot.style.display = 'none';
        }

        if (liveSyncBar) {
                if (!pageTitleLiveSyncSlot && pageTitleShell) {
                    pageTitleLiveSyncSlot = pageTitleShell.querySelector('.admin-page-title-live-sync-slot');
                }
            if (!pageTitleLiveSyncSlot) {
                pageTitleLiveSyncSlot = document.createElement('div');
                pageTitleLiveSyncSlot.className = 'admin-page-title-live-sync-slot';
                pageTitleShell.appendChild(pageTitleLiveSyncSlot);
            }

            pageTitleShell.classList.add('admin-page-title-shell--draws-live-sync');
            pageTitleShell.classList.add('admin-page-title-shell--with-live-sync');
            liveSyncBar.hidden = false;
            liveSyncBar.classList.add('is-in-page-title-shell');

            if (liveSyncBar.parentNode !== pageTitleLiveSyncSlot) {
                pageTitleLiveSyncSlot.appendChild(liveSyncBar);
            }
        } else {
            pageTitleShell.classList.remove('admin-page-title-shell--draws-live-sync');
            pageTitleShell.classList.remove('admin-page-title-shell--with-live-sync');
        }

        if (!pageTitleActionSlot) {
            pageTitleActionSlot = document.createElement('div');
            pageTitleActionSlot.className = 'admin-page-title-action-slot';
            pageTitleShell.classList.add('admin-page-title-shell--with-action');
            pageTitleShell.appendChild(pageTitleActionSlot);
        }

        if (saveButton) {
            saveButton.classList.remove('is-in-editor-menubar');
            saveButton.classList.remove('is-in-editor-header');
            saveButton.classList.add('is-in-page-title-shell');

            if (saveButton.parentNode !== pageTitleActionSlot) {
                pageTitleActionSlot.appendChild(saveButton);
            }
        }

        return true;
    };

    var removeFullscreenLiveSyncBar = function () {
        if (fullscreenLiveSyncBar && fullscreenLiveSyncBar.parentNode) {
            fullscreenLiveSyncBar.parentNode.removeChild(fullscreenLiveSyncBar);
        }

        fullscreenLiveSyncBar = null;
    };

    var mountFullscreenLiveSyncBar = function (editorInstance) {
        var currentEditor = editorInstance || (typeof window.tinymce !== 'undefined' ? window.tinymce.get('draw-material-editor') : null);
        var container = currentEditor && currentEditor.getContainer ? currentEditor.getContainer() : null;
        var header = container ? container.querySelector('.tox-editor-header') : null;
        var menubar = container ? container.querySelector('.tox-menubar') : null;

        if (!liveSyncBar || !header) {
            removeFullscreenLiveSyncBar();
            return;
        }

        if (!fullscreenLiveSyncBar) {
            fullscreenLiveSyncBar = liveSyncBar.cloneNode(true);
            fullscreenLiveSyncBar.classList.add('is-in-editor-menubar');
            fullscreenLiveSyncBar.setAttribute('aria-hidden', 'true');
        } else {
            fullscreenLiveSyncBar.innerHTML = liveSyncBar.innerHTML;
        }

        if (menubar) {
            if (saveButton && saveButton.parentNode === menubar) {
                menubar.insertBefore(fullscreenLiveSyncBar, saveButton);
            } else if (fullscreenLiveSyncBar.parentNode !== menubar) {
                menubar.appendChild(fullscreenLiveSyncBar);
            }
        } else if (fullscreenLiveSyncBar.parentNode !== header) {
            header.appendChild(fullscreenLiveSyncBar);
        }
    };

    var syncDrawStickyLayout = function (editorInstance) {
        if (!drawsCard) {
            return;
        }

        drawsCard.style.removeProperty('--draw-sticky-top');
        drawsCard.style.removeProperty('--draw-sticky-editor-top');
        resetDrawEditorHeaderFloating(editorInstance);
    };

    var scheduleDrawStickyLayout = function (editorInstance) {
        window.clearTimeout(drawStickyLayoutTimer);
        drawStickyLayoutTimer = window.setTimeout(function () {
            syncDrawStickyLayout(editorInstance);
        }, 0);
    };

    var resetDrawEditorHeaderFloating = function (editorInstance) {
        var currentEditor = editorInstance || (typeof window.tinymce !== 'undefined' ? window.tinymce.get('draw-material-editor') : null);
        var container = currentEditor && currentEditor.getContainer ? currentEditor.getContainer() : null;
        var header = container ? container.querySelector('.tox-editor-header') : null;

        if (!container || !header) {
            return;
        }

        container.classList.remove('tox-draw-toolbar-floating');
        container.style.paddingTop = '';
        header.style.position = '';
        header.style.top = '';
        header.style.left = '';
        header.style.width = '';
        header.style.zIndex = '';
        header.style.background = '';
        header.style.boxShadow = '';
    };

    var syncDrawEditorHeaderFloating = function (editorInstance) {
        resetDrawEditorHeaderFloating(editorInstance);
    };

    var restoreSaveButton = function () {
        if (!saveButton) {
            return;
        }

        if (mountSaveButtonToPageTitle()) {
            return;
        }

        if (!saveButtonSlot) {
            return;
        }

        if (liveSyncBar && liveSyncOriginalParent && liveSyncBar.parentNode !== liveSyncOriginalParent) {
            liveSyncBar.classList.remove('is-in-page-title-shell');
            liveSyncOriginalParent.appendChild(liveSyncBar);
        }

        saveButtonSlot.hidden = false;
        saveButtonSlot.style.display = '';

        if (saveButton.parentNode !== saveButtonSlot) {
            saveButton.classList.remove('is-in-page-title-shell');
            saveButton.classList.remove('is-in-editor-menubar');
            saveButton.classList.remove('is-in-editor-header');
            saveButtonSlot.appendChild(saveButton);
        }

        helperNote = sectionEditorModal.querySelector('[data-section-editor-color-help]');
        if (helperNote) {
            helperNote.textContent = '这里可以直接编辑主卡片标题文案、标题文字颜色、背景颜色和标题样式。';
        }
    };

    restoreSaveButton();
    scheduleDrawStickyLayout();

    var setPendingFullscreenShell = function (isPending) {
        document.documentElement.classList.toggle('draw-editor-is-fullscreen-pending', !!isPending);

        if (document.body && document.body.classList) {
            document.body.classList.toggle('draw-editor-is-fullscreen-pending', !!isPending);
        }
    };

    var setSaveButtonBusy = function (isBusy) {
        if (!saveButton) {
            return;
        }

        if (!saveRequestState.originalLabel) {
            saveRequestState.originalLabel = saveButton.textContent || '';
        }

        saveButton.disabled = !!isBusy;
        saveButton.classList.toggle('is-loading', !!isBusy);
        saveButton.textContent = isBusy ? '保存中...' : saveRequestState.originalLabel;
    };

    var showDrawEditorNotice = function (message, type) {
        if (!message) {
            return;
        }

        if (window.AppUI && typeof window.AppUI.toast === 'function') {
            window.AppUI.toast(message, type || 'info');
            return;
        }

        if (window.console && message) {
            window.console.warn(message);
        }
    };

    var readFullscreenPreference = function () {
        try {
            return window.sessionStorage && window.sessionStorage.getItem(fullscreenStorageKey) === '1';
        } catch (error) {
            return false;
        }
    };

    var writeFullscreenPreference = function (isFullscreen) {
        try {
            if (!window.sessionStorage) {
                return;
            }

            fullscreenState.shouldRestore = !!isFullscreen;

            if (isFullscreen) {
                window.sessionStorage.setItem(fullscreenStorageKey, '1');
            } else {
                window.sessionStorage.removeItem(fullscreenStorageKey);
            }
        } catch (error) {
            // Ignore storage errors and keep the editor usable.
        }
    };

    var syncFullscreenPreferenceFromState = function () {
        writeFullscreenPreference(!!fullscreenState.isFullscreen || !!fullscreenState.shouldRestore);
    };

    var applyEditorFullscreenState = function (editor, shouldBeFullscreen) {
        if (!editor || editor.removed) {
            return;
        }

        if (!!fullscreenState.isFullscreen === !!shouldBeFullscreen) {
            return;
        }

        editor.execCommand('mceFullScreen');
    };

    fullscreenState.shouldRestore = readFullscreenPreference();
    setPendingFullscreenShell(fullscreenState.shouldRestore);

    window.addEventListener('beforeunload', function () {
        fullscreenState.isNavigatingAway = true;
        syncFullscreenPreferenceFromState();
    });

    var prepareDrawUploadImageFile = function (file, options) {
        if (!file || !window.AppUI || typeof window.AppUI.compressImageForUpload !== 'function') {
            return Promise.resolve(file);
        }

        return window.AppUI.compressImageForUpload(file, options || {
            maxWidth: 1920,
            maxHeight: 1920,
            quality: 0.82,
            directSize: 900 * 1024,
            targetSize: 5 * 1024 * 1024,
            outputSuffix: '-material'
        });
    };

    var uploadEditorImageFile = function (file, editor, progress) {
        var token = csrfTokenInput ? csrfTokenInput.value : '';

        if (!file) {
            return Promise.reject(new Error('Please choose an image file.'));
        }

        if (typeof progress === 'function') {
            progress(8);
        }

        if (editor && editor.setProgressState) {
            editor.setProgressState(true);
        }

        return prepareDrawUploadImageFile(file).then(function (preparedFile) {
            var formData = new FormData();
            formData.append('_token', token);
            formData.append('_admin_action', 'upload_draw_image');
            formData.append('image_file', preparedFile || file);

            if (typeof progress === 'function') {
                progress(18);
            }

            return fetch(drawImageUploadUrl, {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (payload) {
                if (!response.ok || !payload || payload.success !== true || !payload.location) {
                    throw new Error((payload && payload.message) || 'Image upload failed. Please try again.');
                }

                if (typeof progress === 'function') {
                    progress(100);
                }

                return payload;
            });
        }).finally(function () {
            if (editor && editor.setProgressState) {
                editor.setProgressState(false);
            }
        });
    };

    var prepareHeaderBackgroundUploadFile = function (file) {
        return prepareDrawUploadImageFile(file, {
            maxWidth: 1600,
            maxHeight: 900,
            quality: 0.82,
            directSize: 900 * 1024,
            targetSize: 5 * 1024 * 1024,
            outputSuffix: '-bg'
        });
    };
    var openEditorImagePicker = function (callback, editor, options) {
        var input = document.createElement('input');
        var hooks = options || {};

        input.type = 'file';
        input.accept = drawImageAccept;
        input.multiple = false;

        input.addEventListener('change', function () {
            var file = input.files && input.files[0] ? input.files[0] : null;

            if (!file) {
                return;
            }

            if (typeof hooks.onStart === 'function') {
                hooks.onStart(file);
            }

            Promise.resolve(
                typeof hooks.prepareFile === 'function' ? hooks.prepareFile(file) : file
            ).then(function (preparedFile) {
                return uploadEditorImageFile(preparedFile || file, editor);
            }).then(function (payload) {
                callback(payload.location, {
                    alt: (file.name || '').replace(/\.[^.]+$/, '')
                });
                if (typeof hooks.onSuccess === 'function') {
                    hooks.onSuccess(payload);
                }
            }).catch(function (error) {
                showDrawEditorNotice(error.message || 'Image upload failed. Please try again.', 'error');
                if (typeof hooks.onError === 'function') {
                    hooks.onError(error);
                }
            }).finally(function () {
                if (typeof hooks.onFinish === 'function') {
                    hooks.onFinish();
                }
            });
        });

        input.click();
    };

    var hideSectionEditorField = function (field) {
        var wrapper = null;

        if (!field || !field.closest) {
            return;
        }

        wrapper = field.closest('div');
        if (wrapper) {
            wrapper.hidden = true;
        }
    };

    var setSectionEditorFieldVisibility = function (field, visible) {
        var wrapper = null;

        if (!field || !field.closest) {
            return;
        }

        wrapper = field.closest('div');
        if (wrapper) {
            if (wrapper.getAttribute('data-section-editor-deleted-field')) {
                wrapper.hidden = true;
                return;
            }

            wrapper.hidden = !visible;
        }
    };

    var extractSectionEditorCssBackgroundUrl = function (styleText) {
        var match = (styleText || '').match(/url\(\s*(?:(['"])(.*?)\1|([^'")]+))\s*\)/i);

        if (!match) {
            return '';
        }

        return (match[2] || match[3] || '').trim();
    };

    var normalizeHeaderBackgroundOverlayPercent = function (value, fallback) {
        var rawValue = (typeof value === 'undefined' || value === null) ? '' : value;
        var number = parseInt(String(rawValue).replace(/[^0-9]+/g, ''), 10);

        if (isNaN(number)) {
            number = typeof fallback === 'number' ? fallback : 60;
        }

        return Math.max(0, Math.min(90, number));
    };

    var formatHeaderBackgroundOverlayOpacity = function (overlayPercent) {
        return (normalizeHeaderBackgroundOverlayPercent(overlayPercent, 60) / 100)
            .toFixed(2)
            .replace(/0$/, '')
            .replace(/\.0$/, '');
    };

    var extractHeaderBackgroundOverlayPercent = function (styleText) {
        var match = (styleText || '').match(/rgba\(\s*0\s*,\s*0\s*,\s*0\s*,\s*(0?\.\d+|1(?:\.0+)?)\s*\)/i);

        if (!match) {
            return 60;
        }

        return normalizeHeaderBackgroundOverlayPercent(Math.round(parseFloat(match[1]) * 100), 60);
    };

    var escapeSectionEditorCssUrl = function (url) {
        return (url || '')
            .replace(/\\/g, '\\\\')
            .replace(/"/g, '\\"')
            .replace(/\r?\n/g, '');
    };

    var normalizeSectionEditorSlotDimension = function (value, fallback, min, max) {
        var parsed = parseInt(value, 10);

        if (!Number.isFinite(parsed)) {
            parsed = fallback;
        }

        return Math.max(min, Math.min(max, parsed));
    };

    var extractSectionEditorCssPixelVar = function (styleText, varName, fallback) {
        var escapedName = (varName || '').replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&');
        var match = (styleText || '').match(new RegExp(escapedName + '\\s*:\\s*(\\d+(?:\\.\\d+)?)px', 'i'));

        if (!match) {
            return fallback;
        }

        return parseInt(match[1], 10) || fallback;
    };

    var mergeHeaderSlotSizeStyleText = function (styleText, slotWidth, slotHeight) {
        var widthValue = normalizeSectionEditorSlotDimension(slotWidth, 708, 320, 708);
        var heightValue = normalizeSectionEditorSlotDimension(slotHeight, 286, 120, 520);
        var declarations = (styleText || '').split(';').map(function (declaration) {
            return declaration.trim();
        }).filter(function (declaration) {
            return declaration !== '' && !/^--home-hero-editor-(?:width|height|ratio-width|ratio-height)\s*:/i.test(declaration);
        });

        declarations.push('--home-hero-editor-width: ' + widthValue + 'px');
        declarations.push('--home-hero-editor-height: ' + heightValue + 'px');
        declarations.push('--home-hero-editor-ratio-width: ' + widthValue);
        declarations.push('--home-hero-editor-ratio-height: ' + heightValue);

        return declarations.join('; ') + (declarations.length ? ';' : '');
    };

    var mergeHeaderBackgroundStyleText = function (styleText, imageUrl, overlayPercent) {
        var declarations = (styleText || '').split(';').map(function (declaration) {
            return declaration.trim();
        }).filter(function (declaration) {
            return declaration !== '' && !/^background(?:-[a-z-]+)?\s*:/i.test(declaration);
        });
        var normalizedUrl = (imageUrl || '').trim();
        var overlayOpacity = formatHeaderBackgroundOverlayOpacity(overlayPercent);

        if (normalizedUrl !== '') {
            declarations.push(
                'background: linear-gradient(rgba(0,0,0,' + overlayOpacity + '), rgba(0,0,0,' + overlayOpacity + ')), url(' +
                escapeSectionEditorCssUrl(normalizedUrl) +
                ') center/100% 100% no-repeat'
            );
        }

        return declarations.join('; ') + (declarations.length ? ';' : '');
    };

    var refreshSectionEditorHeaderBackgroundControls = function () {
        var isHeaderSection = isSectionEditorHeaderBlock(activeSectionState.blockEl);
        var imageUrl = extractSectionEditorCssBackgroundUrl(sectionEditorCardStyle ? sectionEditorCardStyle.value : '');
        var overlayPercent = extractHeaderBackgroundOverlayPercent(sectionEditorCardStyle ? sectionEditorCardStyle.value : '');
        var overlayOpacity = formatHeaderBackgroundOverlayOpacity(overlayPercent);
        var styleText = sectionEditorCardStyle ? sectionEditorCardStyle.value : '';
        var slotWidth = normalizeSectionEditorSlotDimension(
            extractSectionEditorCssPixelVar(styleText, '--home-hero-editor-width', 708),
            708,
            320,
            708
        );
        var slotHeight = normalizeSectionEditorSlotDimension(
            extractSectionEditorCssPixelVar(styleText, '--home-hero-editor-height', 286),
            286,
            120,
            520
        );

        if (sectionEditorHeaderBackgroundPanel) {
            sectionEditorHeaderBackgroundPanel.hidden = !isHeaderSection;
        }

        if (sectionEditorCardBackgroundUrl) {
            sectionEditorCardBackgroundUrl.value = imageUrl;
        }

        if (sectionEditorCardBackgroundOverlay) {
            sectionEditorCardBackgroundOverlay.value = String(overlayPercent);
        }

        if (sectionEditorCardSlotWidth) {
            sectionEditorCardSlotWidth.value = String(slotWidth);
        }

        if (sectionEditorCardSlotHeight) {
            sectionEditorCardSlotHeight.value = String(slotHeight);
        }

        if (sectionEditorHeaderBackgroundPreview) {
            if (!isHeaderSection) {
                sectionEditorHeaderBackgroundPreview.hidden = true;
                return;
            }

            sectionEditorHeaderBackgroundPreview.hidden = false;
            sectionEditorHeaderBackgroundPreview.style.aspectRatio = slotWidth + ' / ' + slotHeight;

            if (imageUrl !== '') {
                sectionEditorHeaderBackgroundPreview.style.background =
                    'linear-gradient(rgba(0,0,0,' + overlayOpacity + '), rgba(0,0,0,' + overlayOpacity + ')), url(' +
                    escapeSectionEditorCssUrl(imageUrl) +
                    ') center/100% 100% no-repeat';
                sectionEditorHeaderBackgroundPreview.textContent = '当前已设置自定义背景图';
            } else {
                sectionEditorHeaderBackgroundPreview.style.background =
                    'linear-gradient(135deg, rgba(15, 23, 42, 0.96), rgba(30, 64, 175, 0.88))';
                sectionEditorHeaderBackgroundPreview.textContent = '未设置自定义背景图';
            }
        }
    };

    var createSectionEditorAdCopyField = function (fieldId, labelText) {
        var wrapper = document.createElement('div');
        var label = document.createElement('label');
        var input = document.createElement('input');

        wrapper.className = 'admin-section-editor-color-field';
        label.className = 'admin-label';
        label.setAttribute('for', fieldId);
        label.textContent = labelText;

        input.className = 'admin-input';
        input.id = fieldId;
        input.type = 'text';
        input.autocomplete = 'off';

        wrapper.appendChild(label);
        wrapper.appendChild(input);

        return {
            wrapper: wrapper,
            input: input
        };
    };

    var createSectionEditorAdCopyUrlField = function (fieldId, labelText) {
        var field = createSectionEditorAdCopyField(fieldId, labelText);

        field.wrapper.className += ' admin-section-editor-ad-copy-url-row';
        field.input.type = 'url';
        field.input.placeholder = 'https://example.com';
        field.input.inputMode = 'url';

        return field;
    };

    var createSectionEditorAdCopyDateField = function (fieldId, labelText) {
        var field = createSectionEditorAdCopyField(fieldId, labelText);

        field.wrapper.className += ' admin-section-editor-ad-copy-date-row';
        field.input.type = 'date';
        field.input.inputMode = 'numeric';
        field.input.setAttribute('aria-label', labelText || '到期');

        return field;
    };

    var resolveAdItemMiddleColorMode = function (value) {
        var mode = (value || '').toString().trim();

        if (mode === 'fixed' || mode === 'daily-random') {
            return mode;
        }

        return 'default';
    };

    var resolveAdItemTailTextMode = function (value) {
        var mode = (value || '').toString().trim();

        if (mode === 'daily-random') {
            return mode;
        }

        return 'fixed';
    };

    var getShanghaiDateKey = function () {
        var now = new Date();
        var parts = null;
        var year = '';
        var month = '';
        var day = '';

        try {
            if (typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
                parts = new Intl.DateTimeFormat('en-US', {
                    timeZone: 'Asia/Shanghai',
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit'
                }).formatToParts(now);

                parts.forEach(function (part) {
                    if (part.type === 'year') {
                        year = part.value;
                    } else if (part.type === 'month') {
                        month = part.value;
                    } else if (part.type === 'day') {
                        day = part.value;
                    }
                });
            }
        } catch (error) {
            year = '';
        }

        if (year && month && day) {
            return year + '-' + month + '-' + day;
        }

        year = String(now.getFullYear());
        month = String(now.getMonth() + 1);
        day = String(now.getDate());

        if (month.length < 2) {
            month = '0' + month;
        }

        if (day.length < 2) {
            day = '0' + day;
        }

        return year + '-' + month + '-' + day;
    };

    var hashAdItemColorSeed = function (value) {
        var text = (value || '').toString();
        var hash = 0;
        var index = 0;

        for (index = 0; index < text.length; index += 1) {
            hash = ((hash << 5) - hash + text.charCodeAt(index)) >>> 0;
        }

        return hash >>> 0;
    };

    var parseAdItemRandomWordList = function (value) {
        var normalized = (value || '').toString().replace(/\r\n?/g, '\n');

        if (normalized.indexOf('||') !== -1) {
            return normalized.split('||').map(function (item) {
                return item.trim();
            }).filter(function (item) {
                return item !== '';
            });
        }

        return normalized.split(/[\n|]+/).map(function (item) {
            return item.trim();
        }).filter(function (item) {
            return item !== '';
        });
    };

    var serializeAdItemRandomWordList = function (items) {
        return (items || []).map(function (item) {
            return (item || '').toString().trim();
        }).filter(function (item) {
            return item !== '';
        }).join('||');
    };

    var adItemMiddleRandomPalette = [
        '#ef4444',
        '#f97316',
        '#eab308',
        '#22c55e',
        '#06b6d4',
        '#3b82f6',
        '#8b5cf6',
        '#ec4899'
    ];

    var getAdItemMiddleGroupRoot = function (node, fallbackRoot) {
        if (node && node.closest) {
            return node.closest('.grid') || node.closest('.data-frame') || node.closest('section') || fallbackRoot || node;
        }

        return fallbackRoot || node;
    };

    var getAdItemMiddleGroupSeed = function (groupRoot, fallbackIndex) {
        var sectionRoot = groupRoot && groupRoot.closest ? groupRoot.closest('section') : null;
        var titleNode = sectionRoot && sectionRoot.querySelector ? sectionRoot.querySelector('.section-title') : null;
        var titleText = titleNode ? (titleNode.textContent || '').replace(/\s+/g, ' ').trim() : '';

        if (!titleText && groupRoot && groupRoot.previousElementSibling && groupRoot.previousElementSibling.classList && groupRoot.previousElementSibling.classList.contains('section-title')) {
            titleText = (groupRoot.previousElementSibling.textContent || '').replace(/\s+/g, ' ').trim();
        }

        return titleText || ('ad-group-' + fallbackIndex);
    };

    var shuffleAdItemMiddlePalette = function (palette, seedText) {
        var shuffled = (palette || []).slice();
        var index = 0;
        var swapIndex = 0;
        var seed = seedText || '';
        var temp = '';

        for (index = shuffled.length - 1; index > 0; index -= 1) {
            seed = String(hashAdItemColorSeed(seed + '|' + index));
            swapIndex = Number(seed) % (index + 1);
            temp = shuffled[index];
            shuffled[index] = shuffled[swapIndex];
            shuffled[swapIndex] = temp;
        }

        return shuffled;
    };

    var setAdItemMiddlePreviewColor = function (node, color) {
        if (!node || !node.style) {
            return;
        }

        if (color !== '') {
            node.style.color = color;
        } else {
            node.style.removeProperty('color');
        }

        if (!node.getAttribute('style')) {
            node.removeAttribute('style');
        }
    };

    var updateAdCopyMiddleColorControlState = function (entry) {
        if (!entry || !entry.middleColorMode || !entry.middleColor) {
            return;
        }

        entry.middleColor.disabled = entry.middleColorMode.value !== 'fixed';
        entry.middleColor.style.opacity = entry.middleColor.disabled ? '0.55' : '1';
        entry.middleColor.style.cursor = entry.middleColor.disabled ? 'not-allowed' : 'pointer';
    };

    var updateAdCopyTailTextControlState = function (entry) {
        if (!entry || !entry.tailTextMode || !entry.tailRandomWords) {
            return;
        }

        entry.tailRandomWords.hidden = entry.tailTextMode.value !== 'daily-random';
        entry.tailRandomWords.disabled = entry.tailTextMode.value !== 'daily-random';
    };

    var applyAdItemMiddleColorPreview = function (root) {
        var nodes = root && root.querySelectorAll ? Array.prototype.slice.call(root.querySelectorAll('.ad-item-middle')) : [];
        var groups = [];
        var index = 0;
        var groupIndex = 0;
        var node = null;
        var groupRoot = null;
        var mode = 'default';
        var fixedColor = '';
        var dateKey = getShanghaiDateKey();
        var group = null;
        var groupSeed = '';
        var palette = [];
        var paletteColor = '';
        var dailyEntries = [];
        var dailyIndex = 0;

        for (index = 0; index < nodes.length; index += 1) {
            node = nodes[index];
            groupRoot = getAdItemMiddleGroupRoot(node, root);
            groupIndex = -1;

            for (dailyIndex = 0; dailyIndex < groups.length; dailyIndex += 1) {
                if (groups[dailyIndex].root === groupRoot) {
                    groupIndex = dailyIndex;
                    break;
                }
            }

            if (groupIndex === -1) {
                groups.push({
                    root: groupRoot,
                    nodes: []
                });
                groupIndex = groups.length - 1;
            }

            groups[groupIndex].nodes.push(node);
        }

        for (index = 0; index < groups.length; index += 1) {
            group = groups[index];
            groupSeed = getAdItemMiddleGroupSeed(group.root, index);
            dailyEntries = [];
            palette = adItemMiddleRandomPalette.slice();

            for (dailyIndex = 0; dailyIndex < group.nodes.length; dailyIndex += 1) {
                node = group.nodes[dailyIndex];
                mode = resolveAdItemMiddleColorMode(node.getAttribute('data-middle-color-mode'));
                fixedColor = normalizeHexColor(node.getAttribute('data-middle-fixed-color') || node.style.color || '');

                if (mode === 'fixed') {
                    setAdItemMiddlePreviewColor(node, fixedColor);
                    if (fixedColor !== '') {
                        palette = palette.filter(function (color) {
                            return color !== fixedColor;
                        });
                    }
                    continue;
                }

                if (mode !== 'daily-random') {
                    setAdItemMiddlePreviewColor(node, '');
                    continue;
                }

                dailyEntries.push({
                    node: node,
                    seed: (node.getAttribute('data-middle-color-key') || node.textContent || ('slot-' + dailyIndex)).trim()
                });
            }

            if (!palette.length) {
                palette = adItemMiddleRandomPalette.slice();
            }

            palette = shuffleAdItemMiddlePalette(palette, dateKey + '|' + groupSeed);

            dailyEntries.sort(function (left, right) {
                var leftHash = hashAdItemColorSeed(dateKey + '|' + groupSeed + '|' + left.seed);
                var rightHash = hashAdItemColorSeed(dateKey + '|' + groupSeed + '|' + right.seed);

                if (leftHash === rightHash) {
                    return left.seed.localeCompare(right.seed);
                }

                return leftHash - rightHash;
            });

            for (dailyIndex = 0; dailyIndex < dailyEntries.length; dailyIndex += 1) {
                paletteColor = palette[dailyIndex % palette.length] || '';
                setAdItemMiddlePreviewColor(dailyEntries[dailyIndex].node, paletteColor);
            }
        }
    };

    var stripAdItemMiddleRandomPreview = function (root) {
        var nodes = root && root.querySelectorAll ? root.querySelectorAll('.ad-item-middle[data-middle-color-mode="daily-random"]') : [];
        var index = 0;

        for (index = 0; index < nodes.length; index += 1) {
            nodes[index].style.removeProperty('color');
            if (!nodes[index].getAttribute('style')) {
                nodes[index].removeAttribute('style');
            }
        }
    };

    var resolveAdItemTailPreviewText = function (mode, defaultText, randomOptions, seedText) {
        var list = parseAdItemRandomWordList(randomOptions);
        var dateKey = '';
        var hash = 0;

        if (mode !== 'daily-random') {
            return (defaultText || '').trim();
        }

        if (!list.length) {
            return (defaultText || '').trim();
        }

        dateKey = getShanghaiDateKey();
        hash = hashAdItemColorSeed(dateKey + '|' + (seedText || ''));
        return list[hash % list.length];
    };

    var applyAdItemTailTextPreview = function (root) {
        var nodes = root && root.querySelectorAll ? root.querySelectorAll('.ad-item-tail') : [];
        var index = 0;
        var mode = 'fixed';
        var defaultText = '';
        var randomOptions = '';
        var seedText = '';

        for (index = 0; index < nodes.length; index += 1) {
            mode = resolveAdItemTailTextMode(nodes[index].getAttribute('data-tail-text-mode'));
            defaultText = (nodes[index].getAttribute('data-tail-default-text') || nodes[index].textContent || '').replace(/\s+/g, ' ').trim();
            randomOptions = nodes[index].getAttribute('data-tail-random-options') || '';
            seedText = (nodes[index].getAttribute('data-tail-random-key') || defaultText || ('tail-' + index)).trim();

            nodes[index].textContent = resolveAdItemTailPreviewText(mode, defaultText, randomOptions, seedText);
        }
    };

    var stripAdItemTailRandomPreview = function (root) {
        var nodes = root && root.querySelectorAll ? root.querySelectorAll('.ad-item-tail[data-tail-text-mode="daily-random"]') : [];
        var index = 0;
        var defaultText = '';

        for (index = 0; index < nodes.length; index += 1) {
            defaultText = (nodes[index].getAttribute('data-tail-default-text') || '').replace(/\s+/g, ' ').trim();
            nodes[index].textContent = defaultText;
        }
    };

    var ensureSectionEditorAdCopyFields = function (count) {
        var fieldCount = Math.max(0, count || 0);
        var index = 0;
        var row = null;
        var rowTitle = null;
        var rowTools = null;
        var rowGrid = null;
        var rowMeta = null;
        var leadField = null;
        var middleField = null;
        var tailField = null;
        var middleTools = null;
        var middleMode = null;
        var middleColor = null;
        var defaultOption = null;
        var fixedOption = null;
        var dailyRandomOption = null;
        var tailTools = null;
        var tailMode = null;
        var tailFixedOption = null;
        var tailDailyRandomOption = null;
        var tailRandomWords = null;
        var linkField = null;
        var expireField = null;
        var removeButton = null;
        var entry = null;

        if (!sectionEditorAdCopyList) {
            return;
        }

        sectionEditorAdCopyInputs = [];
        sectionEditorAdCopyList.innerHTML = '';

        for (index = 0; index < fieldCount; index += 1) {
            row = document.createElement('div');
            row.className = 'admin-section-editor-ad-copy-card';

            rowTitle = document.createElement('div');
            rowTitle.className = 'admin-section-editor-ad-copy-card-title';
            rowTitle.textContent = '\u5e7f\u544a\u4f4d ' + (index + 1);

            rowTools = document.createElement('div');
            rowTools.className = 'admin-section-editor-ad-copy-top-tools';

            rowGrid = document.createElement('div');
            rowGrid.className = 'admin-section-editor-ad-copy-grid';

            leadField = createSectionEditorAdCopyField('section-editor-ad-copy-lead-' + index, '\u524d\u6bb5');
            middleField = createSectionEditorAdCopyField('section-editor-ad-copy-middle-' + index, '\u4e2d\u6bb5');
            tailField = createSectionEditorAdCopyField('section-editor-ad-copy-tail-' + index, '\u540e\u6bb5');
            linkField = createSectionEditorAdCopyUrlField('section-editor-ad-copy-link-' + index, '\u94fe\u63a5');
            expireField = createSectionEditorAdCopyDateField('section-editor-ad-copy-expire-' + index, '\u5230\u671f');

            middleTools = document.createElement('div');
            middleTools.className = 'admin-section-editor-ad-copy-tools admin-section-editor-ad-copy-tools-middle';

            middleMode = document.createElement('select');
            middleMode.className = 'admin-input is-compact';
            middleMode.style.flex = '1 1 auto';
            middleMode.id = 'section-editor-ad-copy-middle-mode-' + index;

            defaultOption = document.createElement('option');
            defaultOption.value = 'default';
            defaultOption.textContent = '\u9ed8\u8ba4\u989c\u8272';
            middleMode.appendChild(defaultOption);

            fixedOption = document.createElement('option');
            fixedOption.value = 'fixed';
            fixedOption.textContent = '\u56fa\u5b9a\u989c\u8272';
            middleMode.appendChild(fixedOption);

            dailyRandomOption = document.createElement('option');
            dailyRandomOption.value = 'daily-random';
            dailyRandomOption.textContent = '\u6bcf\u65e5\u968f\u673a\u56fa\u5b9a';
            middleMode.appendChild(dailyRandomOption);

            middleColor = document.createElement('input');
            middleColor.type = 'color';
            middleColor.value = '#2563eb';
            middleColor.title = '\u4e2d\u6bb5\u56fa\u5b9a\u989c\u8272';
            middleColor.style.width = '52px';
            middleColor.style.height = '38px';
            middleColor.style.padding = '4px';
            middleColor.style.border = '1px solid #d9e2ec';
            middleColor.style.borderRadius = '12px';
            middleColor.style.background = '#ffffff';

            middleTools.appendChild(middleMode);
            middleTools.appendChild(middleColor);
            rowTools.appendChild(rowTitle);
            rowTools.appendChild(middleTools);

            tailTools = document.createElement('div');
            tailTools.className = 'admin-section-editor-ad-copy-tools';

            tailMode = document.createElement('select');
            tailMode.className = 'admin-input is-compact';
            tailMode.id = 'section-editor-ad-copy-tail-mode-' + index;

            tailFixedOption = document.createElement('option');
            tailFixedOption.value = 'fixed';
            tailFixedOption.textContent = '\u56fa\u5b9a\u8bcd';
            tailMode.appendChild(tailFixedOption);

            tailDailyRandomOption = document.createElement('option');
            tailDailyRandomOption.value = 'daily-random';
            tailDailyRandomOption.textContent = '\u6bcf\u65e5\u968f\u673a\u56fa\u5b9a\u8bcd';
            tailMode.appendChild(tailDailyRandomOption);

            tailRandomWords = document.createElement('textarea');
            tailRandomWords.className = 'admin-textarea';
            tailRandomWords.id = 'section-editor-ad-copy-tail-random-' + index;
            tailRandomWords.rows = 3;
            tailRandomWords.placeholder = '\u4e00\u884c\u4e00\u4e2a\u5019\u9009\u8bcd';
            tailRandomWords.style.minHeight = '82px';
            tailRandomWords.hidden = true;

            tailTools.appendChild(tailMode);
            rowTools.appendChild(tailTools);
            tailField.wrapper.appendChild(tailRandomWords);

            rowGrid.appendChild(leadField.wrapper);
            rowGrid.appendChild(middleField.wrapper);
            rowGrid.appendChild(tailField.wrapper);
            row.appendChild(rowTools);
            row.appendChild(rowGrid);

            rowMeta = document.createElement('div');
            rowMeta.className = 'admin-section-editor-ad-copy-meta-row';

            removeButton = document.createElement('button');
            removeButton.className = 'admin-button is-danger admin-section-editor-ad-copy-remove-button';
            removeButton.type = 'button';
            removeButton.textContent = '\u5220\u9664';

            rowMeta.appendChild(linkField.wrapper);
            rowMeta.appendChild(expireField.wrapper);
            rowMeta.appendChild(removeButton);
            row.appendChild(rowMeta);
            sectionEditorAdCopyList.appendChild(row);

            entry = {
                lead: leadField.input,
                middle: middleField.input,
                tail: tailField.input,
                linkUrl: linkField.input,
                expireDate: expireField.input,
                middleColorMode: middleMode,
                middleColor: middleColor,
                tailTextMode: tailMode,
                tailRandomWords: tailRandomWords,
                removeButton: removeButton
            };
            sectionEditorAdCopyInputs.push(entry);

            [leadField.input, middleField.input, tailField.input].forEach(function (field) {
                field.addEventListener('input', updateSectionEditorTitlePreview);
                field.addEventListener('change', updateSectionEditorTitlePreview);
            });

            middleMode.addEventListener('change', (function (currentEntry) {
                return function () {
                    updateAdCopyMiddleColorControlState(currentEntry);
                    updateSectionEditorTitlePreview();
                };
            })(entry));

            middleColor.addEventListener('input', updateSectionEditorTitlePreview);
            middleColor.addEventListener('change', updateSectionEditorTitlePreview);

            tailMode.addEventListener('change', (function (currentEntry) {
                return function () {
                    updateAdCopyTailTextControlState(currentEntry);
                };
            })(entry));

            removeButton.addEventListener('click', (function (currentIndex) {
                return function () {
                    var entries = readSectionEditorAdCopyEntries();

                    if (entries.length <= 1) {
                        showDrawEditorNotice('\u81f3\u5c11\u4fdd\u7559 1 \u4e2a\u5e7f\u544a\u4f4d\u7f6e\u3002', 'info');
                        return;
                    }

                    entries.splice(currentIndex, 1);
                    ensureSectionEditorAdCopyFields(entries.length);
                    writeSectionEditorAdCopyEntries(entries);
                    updateSectionEditorTitlePreview();
                };
            })(index));

            updateAdCopyMiddleColorControlState(entry);
            updateAdCopyTailTextControlState(entry);
        }
    };

    var getAdItemText = function (itemEl) {
        var clone = null;
        var prefix = null;

        if (!itemEl) {
            return '';
        }

        clone = itemEl.cloneNode(true);
        prefix = clone.querySelector('.issue-prefix');

        if (prefix && prefix.parentNode) {
            prefix.parentNode.removeChild(prefix);
        }

        return (clone.textContent || '').replace(/\s+/g, ' ').trim();
    };

    var splitAdItemTextParts = function (text) {
        var normalized = (text || '').replace(/\s+/g, ' ').trim();
        var bracketPairs = [
            { open: '\u3010', close: '\u3011' },
            { open: '[', close: ']' },
            { open: '\uff08', close: '\uff09' },
            { open: '(', close: ')' }
        ];
        var pair = null;
        var index = 0;
        var openIndex = -1;
        var closeIndex = -1;

        if (normalized === '') {
            return {
                lead: '',
                middle: '',
                tail: ''
            };
        }

        for (index = 0; index < bracketPairs.length; index += 1) {
            openIndex = normalized.indexOf(bracketPairs[index].open);
            if (openIndex !== -1) {
                pair = bracketPairs[index];
                break;
            }
        }

        if (!pair) {
            return {
                lead: normalized,
                middle: '',
                tail: ''
            };
        }

        openIndex = normalized.indexOf(pair.open);
        closeIndex = normalized.indexOf(pair.close, openIndex + pair.open.length);

        if (closeIndex === -1) {
            return {
                lead: normalized,
                middle: '',
                tail: ''
            };
        }

        return {
            lead: normalized.slice(0, openIndex).trim(),
            middle: normalized.slice(openIndex, closeIndex + pair.close.length).trim(),
            tail: normalized.slice(closeIndex + pair.close.length).trim()
        };
    };

    var joinAdItemTextParts = function (parts) {
        return [
            parts && parts.lead ? parts.lead.trim() : '',
            parts && parts.middle ? parts.middle.trim() : '',
            parts && parts.tail ? parts.tail.trim() : ''
        ].join('').trim();
    };

    var getSectionAdItems = function (nodes) {
        return nodes && nodes.bodyEl && nodes.bodyEl.querySelectorAll
            ? Array.prototype.slice.call(nodes.bodyEl.querySelectorAll('.ad-item'))
            : [];
    };

    var getSectionAdItemBorderFallbackColor = function (adItems) {
        var firstItem = Array.isArray(adItems) && adItems.length ? adItems[0] : null;
        var styleWindow = null;
        var itemStyles = null;

        if (!firstItem) {
            return '#d9e2ef';
        }

        styleWindow = firstItem.ownerDocument && firstItem.ownerDocument.defaultView ? firstItem.ownerDocument.defaultView : window;
        itemStyles = styleWindow.getComputedStyle(firstItem);

        return colorToHex(itemStyles.borderTopColor) || '#d9e2ef';
    };

    var readSectionAdItemBorderColor = function (adItems) {
        var firstItem = Array.isArray(adItems) && adItems.length ? adItems[0] : null;
        var inlineCustomColor = '';
        var inlineBorderColor = '';

        if (!firstItem || !firstItem.style) {
            return getSectionAdItemBorderFallbackColor(adItems);
        }

        inlineCustomColor = colorToHex(firstItem.style.getPropertyValue('--ad-item-border-color'));
        inlineBorderColor = colorToHex(firstItem.style.getPropertyValue('border-color') || firstItem.style.borderColor || '');

        return inlineCustomColor || inlineBorderColor || getSectionAdItemBorderFallbackColor(adItems);
    };

    var writeSectionEditorAdItemBorderColor = function (color) {
        if (sectionEditorAdItemBorder) {
            sectionEditorAdItemBorder.value = normalizeHexColor(color) || '#d9e2ef';
        }
    };

    var getSectionEditorAdItemBorderColor = function () {
        return normalizeHexColor(sectionEditorAdItemBorder ? sectionEditorAdItemBorder.value : '') || '#d9e2ef';
    };

    var applySectionAdItemBorderColor = function (adItems, color) {
        var normalizedColor = normalizeHexColor(color);
        var items = Array.isArray(adItems)
            ? adItems
            : (adItems && typeof adItems.length === 'number' ? Array.prototype.slice.call(adItems) : []);

        items.forEach(function (itemEl) {
            if (!itemEl || !itemEl.style) {
                return;
            }

            if (normalizedColor) {
                itemEl.style.setProperty('--ad-item-border-color', normalizedColor);
                itemEl.style.setProperty('border-color', normalizedColor);
            } else {
                itemEl.style.removeProperty('--ad-item-border-color');
                itemEl.style.removeProperty('border-color');
            }
        });
    };

    var extractAdItemParts = function (itemEl) {
        var leadNode = itemEl ? itemEl.querySelector('.ad-item-lead') : null;
        var middleNode = itemEl ? itemEl.querySelector('.ad-item-middle') : null;
        var tailNode = itemEl ? itemEl.querySelector('.ad-item-tail') : null;
        var parts = splitAdItemTextParts(getAdItemText(itemEl));
        var adUrl = itemEl ? String(itemEl.getAttribute('data-ad-url') || '').trim() : '';
        var expireDate = itemEl ? String(itemEl.getAttribute('data-ad-expire-date') || '').trim() : '';
        var mode = resolveAdItemMiddleColorMode(middleNode ? middleNode.getAttribute('data-middle-color-mode') : '');
        var fixedColor = '';
        var middleWindow = middleNode && middleNode.ownerDocument && middleNode.ownerDocument.defaultView
            ? middleNode.ownerDocument.defaultView
            : window;
        var middleComputedStyles = null;
        var tailMode = resolveAdItemTailTextMode(tailNode ? tailNode.getAttribute('data-tail-text-mode') : '');
        var tailDefaultText = tailNode ? ((tailNode.getAttribute('data-tail-default-text') || tailNode.textContent || '').replace(/\s+/g, ' ').trim()) : '';
        var tailRandomWords = tailNode ? String(tailNode.getAttribute('data-tail-random-options') || '').split('||').join('\n') : '';

        if (middleNode) {
            fixedColor = normalizeHexColor(middleNode.getAttribute('data-middle-fixed-color') || '')
                || colorToHex(middleNode.style && middleNode.style.color ? middleNode.style.color : '');
            if (fixedColor === '' && middleWindow && middleWindow.getComputedStyle) {
                middleComputedStyles = middleWindow.getComputedStyle(middleNode);
                fixedColor = colorToHex(middleComputedStyles ? middleComputedStyles.color : '');
            }
        }

        if (leadNode || middleNode || tailNode) {
            parts.lead = leadNode ? (leadNode.textContent || '').replace(/\s+/g, ' ').trim() : parts.lead;
            parts.middle = middleNode ? (middleNode.textContent || '').replace(/\s+/g, ' ').trim() : parts.middle;
            parts.tail = tailNode ? tailDefaultText : parts.tail;
        }

        parts.middleColorMode = mode;
        parts.middleColor = fixedColor || '#2563eb';
        parts.tailTextMode = tailMode;
        parts.tailRandomWords = tailRandomWords;
        parts.linkUrl = adUrl;
        parts.expireDate = /^\d{4}-\d{2}-\d{2}$/.test(expireDate) ? expireDate : '';

        return parts;
    };

    var isSectionAdBlock = function (nodes) {
        return getSectionAdItems(nodes).length > 0;
    };

    var createEmptySectionEditorAdCopyEntry = function () {
        return {
            lead: '',
            middle: '',
            tail: '',
            linkUrl: '',
            expireDate: '',
            middleColorMode: 'default',
            middleColor: '#2563eb',
            tailTextMode: 'fixed',
            tailRandomWords: ''
        };
    };

    var readSectionEditorAdCopyEntries = function () {
        return sectionEditorAdCopyInputs.map(function (entry) {
            return {
                lead: entry.lead ? entry.lead.value : '',
                middle: entry.middle ? entry.middle.value : '',
                tail: entry.tail ? entry.tail.value : '',
                linkUrl: entry.linkUrl ? entry.linkUrl.value : '',
                expireDate: entry.expireDate ? entry.expireDate.value : '',
                middleColorMode: entry.middleColorMode ? entry.middleColorMode.value : 'default',
                middleColor: entry.middleColor ? entry.middleColor.value : '#2563eb',
                tailTextMode: entry.tailTextMode ? entry.tailTextMode.value : 'fixed',
                tailRandomWords: entry.tailRandomWords ? entry.tailRandomWords.value : ''
            };
        });
    };

    var writeSectionEditorAdCopyEntries = function (entries) {
        var normalizedEntries = Array.isArray(entries) ? entries : [];
        var index = 0;
        var parts = null;

        for (index = 0; index < sectionEditorAdCopyInputs.length; index += 1) {
            parts = normalizedEntries[index] || createEmptySectionEditorAdCopyEntry();
            sectionEditorAdCopyInputs[index].lead.value = parts.lead || '';
            sectionEditorAdCopyInputs[index].middle.value = parts.middle || '';
            sectionEditorAdCopyInputs[index].tail.value = parts.tail || '';
            sectionEditorAdCopyInputs[index].linkUrl.value = parts.linkUrl || '';
            sectionEditorAdCopyInputs[index].expireDate.value = /^\d{4}-\d{2}-\d{2}$/.test(parts.expireDate || '') ? parts.expireDate : '';
            sectionEditorAdCopyInputs[index].middleColorMode.value = parts.middleColorMode || 'default';
            sectionEditorAdCopyInputs[index].middleColor.value = parts.middleColor || '#2563eb';
            sectionEditorAdCopyInputs[index].tailTextMode.value = parts.tailTextMode || 'fixed';
            sectionEditorAdCopyInputs[index].tailRandomWords.value = parts.tailRandomWords || '';
            updateAdCopyMiddleColorControlState(sectionEditorAdCopyInputs[index]);
            updateAdCopyTailTextControlState(sectionEditorAdCopyInputs[index]);
        }

        updateSectionEditorTitlePreview();
    };

    var populateSectionEditorAdCopyFields = function (adItems) {
        var index = 0;
        var entries = [];

        ensureSectionEditorAdCopyFields(adItems.length);

        for (index = 0; index < adItems.length; index += 1) {
            entries.push(extractAdItemParts(adItems[index]));
        }

        writeSectionEditorAdCopyEntries(entries);
    };

    var createSectionEditorAdItemElement = function (bodyEl, referenceItemEl) {
        var doc = bodyEl ? bodyEl.ownerDocument : document;
        var itemEl = doc.createElement(referenceItemEl && referenceItemEl.tagName ? referenceItemEl.tagName.toLowerCase() : 'div');
        var className = referenceItemEl && referenceItemEl.className ? referenceItemEl.className : 'ad-item';

        itemEl.className = className;
        itemEl.innerHTML =
            '<span class="issue-prefix issue-prefix-ad"></span>' +
            '<span class="ad-item-lead"></span>' +
            '<span class="ad-item-middle"></span>' +
            '<span class="ad-item-tail"></span>';

        return itemEl;
    };

    var ensureSectionAdItemElements = function (bodyEl, desiredCount) {
        var adItems = bodyEl && bodyEl.querySelectorAll
            ? Array.prototype.slice.call(bodyEl.querySelectorAll('.ad-item'))
            : [];
        var referenceItemEl = adItems.length ? adItems[adItems.length - 1] : null;
        var nextItemEl = null;

        if (!bodyEl || desiredCount <= adItems.length) {
            return adItems;
        }

        while (adItems.length < desiredCount) {
            nextItemEl = createSectionEditorAdItemElement(bodyEl, referenceItemEl);
            bodyEl.appendChild(nextItemEl);
            adItems.push(nextItemEl);
            referenceItemEl = nextItemEl;
        }

        return adItems;
    };

    var applySectionEditorAdCopyFields = function (adItems) {
        var index = 0;
        var prefixEl = null;
        var parts = null;
        var mode = 'default';
        var fixedColor = '';
        var middleAttrs = '';
        var middleColorKey = '';
        var tailMode = 'fixed';
        var tailRandomItems = [];
        var tailAttrs = '';
        var tailDefaultText = '';
        var tailRandomSeed = '';
        var linkUrl = '';
        var expireDate = '';
        var previewRoot = adItems.length && adItems[0] && adItems[0].parentNode ? adItems[0].parentNode : null;

        for (index = 0; index < adItems.length && index < sectionEditorAdCopyInputs.length; index += 1) {
            prefixEl = adItems[index].querySelector('.issue-prefix');
            parts = {
                lead: sectionEditorAdCopyInputs[index].lead.value,
                middle: sectionEditorAdCopyInputs[index].middle.value,
                tail: sectionEditorAdCopyInputs[index].tail.value
            };
            linkUrl = (sectionEditorAdCopyInputs[index].linkUrl.value || '').trim();
            expireDate = (sectionEditorAdCopyInputs[index].expireDate.value || '').trim();
            mode = resolveAdItemMiddleColorMode(sectionEditorAdCopyInputs[index].middleColorMode.value);
            fixedColor = normalizeHexColor(sectionEditorAdCopyInputs[index].middleColor.value);
            middleColorKey = joinAdItemTextParts(parts);
            tailMode = resolveAdItemTailTextMode(sectionEditorAdCopyInputs[index].tailTextMode.value);
            tailRandomItems = parseAdItemRandomWordList(sectionEditorAdCopyInputs[index].tailRandomWords.value);
            tailDefaultText = (parts.tail || '').trim();
            if (tailDefaultText === '' && tailRandomItems.length) {
                tailDefaultText = tailRandomItems[0];
            }
            tailRandomSeed = (parts.lead + '|' + parts.middle + '|' + serializeAdItemRandomWordList(tailRandomItems)).trim();

            if (!prefixEl) {
                prefixEl = adItems[index].ownerDocument.createElement('span');
                prefixEl.className = 'issue-prefix issue-prefix-ad';
            }

            middleAttrs = ' class="ad-item-middle"';
            if (mode === 'fixed' && fixedColor !== '') {
                middleAttrs += ' data-middle-color-mode="fixed"';
                middleAttrs += ' data-middle-fixed-color="' + escapeHtmlAttribute(fixedColor) + '"';
                middleAttrs += ' style="color:' + escapeHtmlAttribute(fixedColor) + ';"';
            } else if (mode === 'daily-random') {
                middleAttrs += ' data-middle-color-mode="daily-random"';
                middleAttrs += ' data-middle-color-key="' + escapeHtmlAttribute(middleColorKey) + '"';
            }

            tailAttrs = ' class="ad-item-tail"';
            if (tailMode === 'daily-random') {
                tailAttrs += ' data-tail-text-mode="daily-random"';
                tailAttrs += ' data-tail-default-text="' + escapeHtmlAttribute(tailDefaultText) + '"';
                tailAttrs += ' data-tail-random-options="' + escapeHtmlAttribute(serializeAdItemRandomWordList(tailRandomItems)) + '"';
                tailAttrs += ' data-tail-random-key="' + escapeHtmlAttribute(tailRandomSeed) + '"';
            }

            adItems[index].innerHTML = prefixEl.outerHTML +
                '<span class="ad-item-lead">' + escapeHtmlText(parts.lead) + '</span>' +
                '<span' + middleAttrs + '>' + escapeHtmlText(parts.middle) + '</span>' +
                '<span' + tailAttrs + '>' + escapeHtmlText(tailDefaultText) + '</span>';

            if (linkUrl !== '') {
                adItems[index].setAttribute('data-ad-url', linkUrl);
            } else {
                adItems[index].removeAttribute('data-ad-url');
            }

            if (/^\d{4}-\d{2}-\d{2}$/.test(expireDate)) {
                adItems[index].setAttribute('data-ad-expire-date', expireDate);
            } else {
                adItems[index].removeAttribute('data-ad-expire-date');
            }
        }

        while (adItems.length > sectionEditorAdCopyInputs.length) {
            if (adItems[adItems.length - 1] && adItems[adItems.length - 1].parentNode) {
                adItems[adItems.length - 1].parentNode.removeChild(adItems[adItems.length - 1]);
            }
            adItems.pop();
        }

        if (previewRoot) {
            applyAdItemMiddleColorPreview(previewRoot);
            applyAdItemTailTextPreview(previewRoot);
        }
    };

    var normalizeSectionExpertAdInterval = function (value) {
        var text = String(value || '').replace(/[^0-9]+/g, '');
        var number = parseInt(text || '0', 10);

        if (isNaN(number) || number <= 0) {
            return 0;
        }

        return Math.min(99, number);
    };

    var resolveSectionExpertAdEffectiveInterval = function (value, entries) {
        var raw = String(value || '').trim();
        var interval = normalizeSectionExpertAdInterval(raw);

        if (interval > 0) {
            return interval;
        }

        if (raw === '' && entries && entries.length) {
            return 1;
        }

        return 0;
    };

    var isSectionExpertAdExplicitOff = function (value) {
        return /^0+$/.test(String(value || '').trim());
    };

    var normalizeSectionExpertAdText = function (value, maxLength) {
        var text = String(value || '').replace(/\s+/g, ' ').trim();
        var limit = maxLength || 80;

        if (text.length > limit) {
            return text.slice(0, limit);
        }

        return text;
    };

    var createEmptySectionEditorExpertAdEntry = function () {
        return {
            lead: defaultSectionExpertAdTitle,
            middle: '',
            tail: '',
            linkUrl: '',
            badgeText: '广告',
            middleColorMode: 'default',
            middleColor: '#2563eb',
            tailTextMode: 'fixed',
            tailRandomWords: ''
        };
    };

    var normalizeSectionExpertAdEntry = function (entry) {
        return {
            lead: normalizeSectionExpertAdText(entry && entry.lead, 80),
            middle: normalizeSectionExpertAdText(entry && entry.middle, 80),
            tail: normalizeSectionExpertAdText(entry && entry.tail, 80),
            linkUrl: normalizeSectionExpertAdText(entry && entry.linkUrl, 500),
            badgeText: normalizeSectionExpertAdText(entry && (entry.badgeText || entry.badge || entry.badgeLabel), 12) || '广告',
            middleColorMode: resolveAdItemMiddleColorMode(entry && entry.middleColorMode),
            middleColor: normalizeHexColor(entry && entry.middleColor) || '#2563eb',
            tailTextMode: resolveAdItemTailTextMode(entry && entry.tailTextMode),
            tailRandomWords: parseAdItemRandomWordList(entry && (entry.tailRandomWords || entry.tailRandomOptions || '')).join('\\n')
        };
    };

    var isSectionExpertAdEntryEmpty = function (entry) {
        var normalized = normalizeSectionExpertAdEntry(entry);

        return normalized.lead === '' && normalized.middle === '' && normalized.tail === '';
    };

    var compactSectionExpertAdEntries = function (entries) {
        var source = Array.isArray(entries) ? entries : [];
        var normalizedEntries = [];
        var index = 0;
        var entry = null;

        for (index = 0; index < source.length; index += 1) {
            entry = normalizeSectionExpertAdEntry(source[index]);
            if (!isSectionExpertAdEntryEmpty(entry)) {
                normalizedEntries.push(entry);
            }
        }

        return normalizedEntries;
    };

    var splitSectionExpertAdTitle = function (title) {
        var parts = splitAdItemTextParts(normalizeSectionExpertAdText(title, 240));
        var entry = normalizeSectionExpertAdEntry({
            lead: parts.lead || '',
            middle: parts.middle || '',
            tail: parts.tail || '',
            linkUrl: ''
        });

        if (isSectionExpertAdEntryEmpty(entry)) {
            entry.lead = defaultSectionExpertAdTitle;
        }

        return entry;
    };

    var readSectionExpertAdEntriesFromBlock = function (blockEl) {
        var raw = blockEl && blockEl.getAttribute ? String(blockEl.getAttribute('data-expert-ad-items') || '') : '';
        var decoded = null;
        var entries = [];
        var index = 0;
        var legacyTitle = '';

        if (raw !== '') {
            try {
                decoded = JSON.parse(raw);
            } catch (error) {
                decoded = null;
            }

            if (Array.isArray(decoded)) {
                for (index = 0; index < decoded.length; index += 1) {
                    entries.push(normalizeSectionExpertAdEntry(decoded[index]));
                }
                entries = compactSectionExpertAdEntries(entries);
            }
        }

        if (!entries.length && blockEl && blockEl.getAttribute) {
            legacyTitle = normalizeSectionExpertAdText(blockEl.getAttribute('data-expert-ad-title') || '', 240);
            if (legacyTitle !== '') {
                entries.push(splitSectionExpertAdTitle(legacyTitle));
            }
        }

        return entries;
    };

    var ensureSectionEditorExpertAdFields = function (count) {
        var fieldCount = Math.max(0, count || 0);
        var index = 0;
        var row = null;
        var rowTitle = null;
        var rowTitleLabel = null;
        var rowHeaderTools = null;
        var rowGrid = null;
        var rowMeta = null;
        var leadField = null;
        var middleField = null;
        var tailField = null;
        var linkField = null;
        var badgeField = null;
        var middleTools = null;
        var middleMode = null;
        var middleColor = null;
        var defaultOption = null;
        var fixedOption = null;
        var dailyRandomOption = null;
        var tailTools = null;
        var tailMode = null;
        var tailFixedOption = null;
        var tailDailyRandomOption = null;
        var tailRandomWords = null;
        var removeButton = null;
        var entry = null;

        if (!sectionEditorExpertAdList) {
            return;
        }

        sectionEditorExpertAdInputs = [];
        sectionEditorExpertAdList.innerHTML = '';

        for (index = 0; index < fieldCount; index += 1) {
            row = document.createElement('div');
            row.className = 'admin-section-editor-ad-copy-card admin-section-editor-expert-ad-card';

            rowTitle = document.createElement('div');
            rowTitle.className = 'admin-section-editor-ad-copy-card-title';

            rowTitleLabel = document.createElement('span');
            rowTitleLabel.className = 'admin-section-editor-expert-ad-title-label';
            rowTitleLabel.textContent = '广告位置 ' + (index + 1);

            badgeField = document.createElement('input');
            badgeField.className = 'admin-input is-compact admin-section-editor-expert-ad-badge-input';
            badgeField.type = 'text';
            badgeField.maxLength = 12;
            badgeField.placeholder = '广告';
            badgeField.value = '广告';
            badgeField.setAttribute('aria-label', '广告胶囊正文');

            rowTitle.appendChild(rowTitleLabel);
            rowTitle.appendChild(badgeField);

            rowHeaderTools = document.createElement('div');
            rowHeaderTools.className = 'admin-section-editor-expert-ad-title-tools';

            rowGrid = document.createElement('div');
            rowGrid.className = 'admin-section-editor-ad-copy-grid';

            leadField = createSectionEditorAdCopyField('section-editor-expert-ad-lead-' + index, '前段');
            middleField = createSectionEditorAdCopyField('section-editor-expert-ad-middle-' + index, '中段');
            tailField = createSectionEditorAdCopyField('section-editor-expert-ad-tail-' + index, '后段');
            linkField = createSectionEditorAdCopyUrlField('section-editor-expert-ad-link-' + index, '广告连接');

            middleTools = document.createElement('div');
            middleTools.className = 'admin-section-editor-ad-copy-tools admin-section-editor-ad-copy-tools-middle';

            middleMode = document.createElement('select');
            middleMode.className = 'admin-input is-compact';
            middleMode.style.flex = '1 1 auto';
            middleMode.id = 'section-editor-expert-ad-middle-mode-' + index;

            defaultOption = document.createElement('option');
            defaultOption.value = 'default';
            defaultOption.textContent = '默认颜色';
            middleMode.appendChild(defaultOption);

            fixedOption = document.createElement('option');
            fixedOption.value = 'fixed';
            fixedOption.textContent = '固定颜色';
            middleMode.appendChild(fixedOption);

            dailyRandomOption = document.createElement('option');
            dailyRandomOption.value = 'daily-random';
            dailyRandomOption.textContent = '每日随机固定颜色';
            middleMode.appendChild(dailyRandomOption);

            middleColor = document.createElement('input');
            middleColor.type = 'color';
            middleColor.value = '#2563eb';
            middleColor.title = '中段固定颜色';
            middleColor.style.width = '52px';
            middleColor.style.height = '38px';
            middleColor.style.padding = '4px';
            middleColor.style.border = '1px solid #d9e2ec';
            middleColor.style.borderRadius = '12px';
            middleColor.style.background = '#ffffff';

            middleTools.appendChild(middleMode);
            middleTools.appendChild(middleColor);
            rowHeaderTools.appendChild(middleTools);

            tailTools = document.createElement('div');
            tailTools.className = 'admin-section-editor-ad-copy-tools';

            tailMode = document.createElement('select');
            tailMode.className = 'admin-input is-compact';
            tailMode.id = 'section-editor-expert-ad-tail-mode-' + index;

            tailFixedOption = document.createElement('option');
            tailFixedOption.value = 'fixed';
            tailFixedOption.textContent = '固定词';
            tailMode.appendChild(tailFixedOption);

            tailDailyRandomOption = document.createElement('option');
            tailDailyRandomOption.value = 'daily-random';
            tailDailyRandomOption.textContent = '每日随机固定词';
            tailMode.appendChild(tailDailyRandomOption);

            tailRandomWords = document.createElement('textarea');
            tailRandomWords.className = 'admin-textarea';
            tailRandomWords.id = 'section-editor-expert-ad-tail-random-' + index;
            tailRandomWords.rows = 3;
            tailRandomWords.placeholder = '一行一个候选词';
            tailRandomWords.style.minHeight = '82px';
            tailRandomWords.hidden = true;

            tailTools.appendChild(tailMode);
            rowHeaderTools.appendChild(tailTools);
            tailField.wrapper.appendChild(tailRandomWords);
            rowTitle.appendChild(rowHeaderTools);

            rowGrid.appendChild(leadField.wrapper);
            rowGrid.appendChild(middleField.wrapper);
            rowGrid.appendChild(tailField.wrapper);
            row.appendChild(rowTitle);
            row.appendChild(rowGrid);

            rowMeta = document.createElement('div');
            rowMeta.className = 'admin-section-editor-ad-copy-meta-row admin-section-editor-expert-ad-meta-row';

            removeButton = document.createElement('button');
            removeButton.className = 'admin-button is-danger admin-section-editor-ad-copy-remove-button';
            removeButton.type = 'button';
            removeButton.textContent = '删除';

            rowMeta.appendChild(linkField.wrapper);
            rowMeta.appendChild(removeButton);
            row.appendChild(rowMeta);
            sectionEditorExpertAdList.appendChild(row);

            entry = {
                lead: leadField.input,
                middle: middleField.input,
                tail: tailField.input,
                linkUrl: linkField.input,
                badgeText: badgeField,
                middleColorMode: middleMode,
                middleColor: middleColor,
                tailTextMode: tailMode,
                tailRandomWords: tailRandomWords,
                removeButton: removeButton
            };
            sectionEditorExpertAdInputs.push(entry);

            [leadField.input, middleField.input, tailField.input, linkField.input, badgeField, middleColor, tailRandomWords].forEach(function (field) {
                field.addEventListener('input', updateSectionEditorTitlePreview);
                field.addEventListener('change', updateSectionEditorTitlePreview);
            });

            middleMode.addEventListener('change', (function (currentEntry) {
                return function () {
                    updateAdCopyMiddleColorControlState(currentEntry);
                    updateSectionEditorTitlePreview();
                };
            })(entry));

            tailMode.addEventListener('change', (function (currentEntry) {
                return function () {
                    updateAdCopyTailTextControlState(currentEntry);
                    updateSectionEditorTitlePreview();
                };
            })(entry));

            removeButton.addEventListener('click', (function (currentIndex) {
                return function () {
                    var entries = readSectionEditorExpertAdEntries();

                    entries.splice(currentIndex, 1);
                    ensureSectionEditorExpertAdFields(entries.length);
                    writeSectionEditorExpertAdEntries(entries);
                    updateSectionEditorTitlePreview();
                };
            })(index));
        }
    };

    var readSectionEditorExpertAdEntries = function () {
        return sectionEditorExpertAdInputs.map(function (entry) {
            return normalizeSectionExpertAdEntry({
                lead: entry.lead ? entry.lead.value : '',
                middle: entry.middle ? entry.middle.value : '',
                tail: entry.tail ? entry.tail.value : '',
                linkUrl: entry.linkUrl ? entry.linkUrl.value : '',
                badgeText: entry.badgeText ? entry.badgeText.value : '广告',
                middleColorMode: entry.middleColorMode ? entry.middleColorMode.value : 'default',
                middleColor: entry.middleColor ? entry.middleColor.value : '#2563eb',
                tailTextMode: entry.tailTextMode ? entry.tailTextMode.value : 'fixed',
                tailRandomWords: entry.tailRandomWords ? entry.tailRandomWords.value : ''
            });
        });
    };

    var writeSectionEditorExpertAdEntries = function (entries) {
        var normalizedEntries = Array.isArray(entries) ? entries : [];
        var index = 0;
        var entry = null;

        for (index = 0; index < sectionEditorExpertAdInputs.length; index += 1) {
            entry = normalizeSectionExpertAdEntry(normalizedEntries[index] || createEmptySectionEditorExpertAdEntry());
            sectionEditorExpertAdInputs[index].lead.value = entry.lead || '';
            sectionEditorExpertAdInputs[index].middle.value = entry.middle || '';
            sectionEditorExpertAdInputs[index].tail.value = entry.tail || '';
            sectionEditorExpertAdInputs[index].linkUrl.value = entry.linkUrl || '';
            sectionEditorExpertAdInputs[index].badgeText.value = entry.badgeText || '广告';
            sectionEditorExpertAdInputs[index].middleColorMode.value = entry.middleColorMode || 'default';
            sectionEditorExpertAdInputs[index].middleColor.value = entry.middleColor || '#2563eb';
            sectionEditorExpertAdInputs[index].tailTextMode.value = entry.tailTextMode || 'fixed';
            sectionEditorExpertAdInputs[index].tailRandomWords.value = entry.tailRandomWords || '';
            updateAdCopyMiddleColorControlState(sectionEditorExpertAdInputs[index]);
            updateAdCopyTailTextControlState(sectionEditorExpertAdInputs[index]);
        }

        updateSectionEditorTitlePreview();
    };

    var writeSectionEditorExpertAdFields = function (blockEl) {
        var rawInterval = blockEl && blockEl.getAttribute ? (blockEl.getAttribute('data-expert-ad-interval') || '') : '';
        var entries = blockEl && blockEl.getAttribute ? readSectionExpertAdEntriesFromBlock(blockEl) : [];
        var interval = resolveSectionExpertAdEffectiveInterval(rawInterval, entries);

        if (sectionEditorExpertAdInterval) {
            sectionEditorExpertAdInterval.value = isSectionExpertAdExplicitOff(rawInterval) ? '0' : (interval > 0 ? String(interval) : '');
        }

        ensureSectionEditorExpertAdFields(entries.length);
        writeSectionEditorExpertAdEntries(entries);
    };

    var resolveSectionEditorExpertIssuePrefixText = function (bodyEl) {
        var prefixNode = bodyEl && bodyEl.querySelector
            ? bodyEl.querySelector('.expert-item-card:not(.expert-ad-slot-card) .issue-prefix-expert, .expert-item-card:not([data-expert-ad-slot="1"]) .issue-prefix-expert')
            : null;
        var text = prefixNode ? String(prefixNode.textContent || '').replace(/\s+/g, ' ').trim() : '';

        return text || '171期：';
    };

    var resolveSectionEditorExpertAdViewText = function (existingAdRows, index) {
        var row = existingAdRows && existingAdRows[index] ? existingAdRows[index] : null;
        var countNode = row && row.querySelector ? row.querySelector('.expert-view-number') : null;
        var text = countNode ? String(countNode.textContent || '').replace(/[^0-9]+/g, '') : '';

        return text !== '' ? text : '2';
    };

    var resolveSectionEditorExpertTitleTextStyle = function (bodyEl) {
        var sampleNode = bodyEl && bodyEl.querySelector
            ? bodyEl.querySelector('.expert-item-card:not(.expert-ad-slot-card) .expert-item-title span[style], .expert-item-card:not([data-expert-ad-slot="1"]) .expert-item-title span[style]')
            : null;

        return sampleNode ? (sampleNode.getAttribute('style') || '') : '';
    };

    var resolveSectionEditorExpertPrefixTextStyle = function (titleTextStyle) {
        var match = String(titleTextStyle || '').match(/(?:^|;)\s*font-size\s*:\s*([^;]+)/i);

        return match ? 'font-size:' + String(match[1] || '').trim() + ';' : '';
    };

    var appendSectionEditorExpertStyle = function (baseStyle, extraStyle) {
        var style = String(baseStyle || '').trim();
        var extra = String(extraStyle || '').trim();

        if (style !== '' && style.slice(-1) !== ';') {
            style += ';';
        }
        if (extra !== '') {
            style += extra;
        }

        return style;
    };

    var createSectionEditorExpertAdSlotElement = function (doc, entry, slotIndex, issuePrefixText, viewText, titleTextStyle) {
        var itemNode = doc.createElement('div');
        var linkNode = null;
        var mainNode = doc.createElement('span');
        var prefixNode = doc.createElement('span');
        var titleNode = doc.createElement('span');
        var metaNode = doc.createElement('div');
        var badgeNode = doc.createElement('span');
        var viewNode = doc.createElement('span');
        var iconNode = doc.createElement('span');
        var countNode = doc.createElement('span');
        var leadText = normalizeSectionExpertAdText(entry && entry.lead, 80) || defaultSectionExpertAdTitle;
        var middleText = normalizeSectionExpertAdText(entry && entry.middle, 80);
        var tailText = normalizeSectionExpertAdText(entry && entry.tail, 80);
        var badgeText = normalizeSectionExpertAdText(entry && entry.badgeText, 12) || '广告';
        var linkUrl = normalizeSectionExpertAdText(entry && entry.linkUrl, 500);
        var middleColorMode = resolveAdItemMiddleColorMode(entry && entry.middleColorMode);
        var middleColor = normalizeHexColor(entry && entry.middleColor) || '#2563eb';
        var tailTextMode = resolveAdItemTailTextMode(entry && entry.tailTextMode);
        var tailRandomItems = parseAdItemRandomWordList(entry && entry.tailRandomWords);
        var titleSeed = joinAdItemTextParts(entry || {});
        var tailSeed = (String(leadText || '') + '|' + String(middleText || '') + '|' + serializeAdItemRandomWordList(tailRandomItems)).trim();
        var leadNode = doc.createElement('span');
        var middleNode = doc.createElement('span');
        var tailNode = doc.createElement('span');

        if (tailText === '' && tailRandomItems.length) {
            tailText = tailRandomItems[0];
        }

        itemNode.className = 'expert-item-card expert-ad-slot-card bg-white p-4 rounded-xl';
        itemNode.setAttribute('data-expert-ad-slot', '1');
        itemNode.setAttribute('data-expert-ad-slot-index', String(Math.max(1, slotIndex || 1)));
        if (linkUrl !== '') {
            itemNode.setAttribute('data-ad-url', linkUrl);
        }

        linkNode = linkUrl !== '' ? doc.createElement('a') : doc.createElement('span');
        if (linkUrl !== '') {
            linkNode.setAttribute('href', linkUrl);
            linkNode.setAttribute('target', '_blank');
            linkNode.setAttribute('rel', 'noopener');
        }
        linkNode.className = 'expert-item-link';
        linkNode.setAttribute('style', 'text-decoration: none; color: inherit');

        mainNode.className = 'expert-item-main';
        prefixNode.className = 'issue-prefix issue-prefix-expert';
        prefixNode.textContent = issuePrefixText || '171期：';
        if (resolveSectionEditorExpertPrefixTextStyle(titleTextStyle) !== '') {
            prefixNode.setAttribute('style', resolveSectionEditorExpertPrefixTextStyle(titleTextStyle));
        }
        titleNode.className = 'expert-item-title';

        leadNode.className = 'ad-item-lead';
        leadNode.textContent = leadText;
        middleNode.className = 'ad-item-middle';
        middleNode.textContent = middleText;
        tailNode.className = 'ad-item-tail';
        tailNode.textContent = tailText;

        if (titleTextStyle) {
            leadNode.setAttribute('style', titleTextStyle);
            middleNode.setAttribute('style', titleTextStyle);
            tailNode.setAttribute('style', titleTextStyle);
        }

        if (middleColorMode === 'fixed' && middleColor !== '') {
            middleNode.setAttribute('data-middle-color-mode', 'fixed');
            middleNode.setAttribute('data-middle-fixed-color', middleColor);
            middleNode.setAttribute('style', appendSectionEditorExpertStyle(titleTextStyle, 'color:' + middleColor + ';'));
        } else if (middleColorMode === 'daily-random') {
            middleNode.setAttribute('data-middle-color-mode', 'daily-random');
            middleNode.setAttribute('data-middle-color-key', titleSeed);
        }

        if (tailTextMode === 'daily-random') {
            tailNode.setAttribute('data-tail-text-mode', 'daily-random');
            tailNode.setAttribute('data-tail-default-text', tailText);
            tailNode.setAttribute('data-tail-random-options', serializeAdItemRandomWordList(tailRandomItems));
            tailNode.setAttribute('data-tail-random-key', tailSeed);
        }

        titleNode.appendChild(leadNode);
        titleNode.appendChild(middleNode);
        titleNode.appendChild(tailNode);
        mainNode.appendChild(prefixNode);
        mainNode.appendChild(titleNode);
        linkNode.appendChild(mainNode);
        itemNode.appendChild(linkNode);

        metaNode.className = 'expert-item-meta';
        badgeNode.className = 'expert-item-result expert-ad-slot-badge';
        badgeNode.textContent = badgeText;
        metaNode.appendChild(badgeNode);

        viewNode.className = 'expert-view-count';
        viewNode.setAttribute('data-post-view-count', viewText || '2');
        viewNode.setAttribute('data-expert-ad-view-count', viewText || '2');
        viewNode.setAttribute('aria-label', '浏览量 ' + (viewText || '2'));
        iconNode.className = 'expert-view-icon';
        iconNode.setAttribute('aria-hidden', 'true');
        iconNode.textContent = '👁‍🗨';
        countNode.className = 'expert-view-number';
        countNode.textContent = viewText || '2';
        viewNode.appendChild(iconNode);
        viewNode.appendChild(countNode);
        metaNode.appendChild(viewNode);
        itemNode.appendChild(metaNode);

        return itemNode;
    };

    var syncSectionEditorExpertAdRows = function (bodyEl, interval, entries) {
        var doc = bodyEl ? bodyEl.ownerDocument : document;
        var existingAdRows = bodyEl && bodyEl.querySelectorAll ? Array.prototype.slice.call(bodyEl.querySelectorAll('.expert-ad-slot-card, [data-expert-ad-slot="1"]')) : [];
        var normalRows = [];
        var issuePrefixText = resolveSectionEditorExpertIssuePrefixText(bodyEl);
        var titleTextStyle = resolveSectionEditorExpertTitleTextStyle(bodyEl);
        var slotIndex = 0;
        var index = 0;
        var insertAfter = null;
        var adRow = null;

        if (!bodyEl) {
            return;
        }

        existingAdRows.forEach(function (row) {
            if (row && row.parentNode) {
                row.parentNode.removeChild(row);
            }
        });

        normalRows = bodyEl.querySelectorAll
            ? Array.prototype.slice.call(bodyEl.querySelectorAll('.expert-item-card')).filter(function (row) {
                return row && !row.classList.contains('expert-ad-slot-card') && row.getAttribute('data-expert-ad-slot') !== '1';
            })
            : [];

        if (interval <= 0 || !entries.length || !normalRows.length) {
            return;
        }

        for (index = 0; index < normalRows.length && slotIndex < entries.length; index += 1) {
            if (((index + 1) % interval) !== 0) {
                continue;
            }

            insertAfter = normalRows[index];
            adRow = createSectionEditorExpertAdSlotElement(
                doc,
                entries[slotIndex],
                slotIndex + 1,
                issuePrefixText,
                resolveSectionEditorExpertAdViewText(existingAdRows, slotIndex),
                titleTextStyle
            );
            if (insertAfter && insertAfter.parentNode) {
                insertAfter.parentNode.insertBefore(adRow, insertAfter.nextSibling);
            }
            slotIndex += 1;
        }
    };

    var applySectionEditorExpertAdFields = function (blockEl) {
        var rawInterval = sectionEditorExpertAdInterval ? sectionEditorExpertAdInterval.value : '';
        var entries = compactSectionExpertAdEntries(readSectionEditorExpertAdEntries());
        var interval = resolveSectionExpertAdEffectiveInterval(rawInterval, entries);

        if (!blockEl || !blockEl.setAttribute) {
            return;
        }

        if (interval > 0) {
            blockEl.setAttribute('data-expert-ad-interval', String(interval));
        } else if (isSectionExpertAdExplicitOff(rawInterval) && entries.length) {
            blockEl.setAttribute('data-expert-ad-interval', '0');
        } else {
            blockEl.removeAttribute('data-expert-ad-interval');
        }

        if (entries.length) {
            blockEl.setAttribute('data-expert-ad-items', JSON.stringify(entries));
            blockEl.setAttribute('data-expert-ad-title', joinAdItemTextParts(entries[0]));
        } else {
            blockEl.removeAttribute('data-expert-ad-items');
            blockEl.removeAttribute('data-expert-ad-title');
        }
    };

    var clearSectionEditorExpertAdFields = function (blockEl) {
        if (!blockEl || !blockEl.removeAttribute) {
            return;
        }

        blockEl.removeAttribute('data-expert-ad-interval');
        blockEl.removeAttribute('data-expert-ad-title');
        blockEl.removeAttribute('data-expert-ad-items');
    };

    var isExpertSectionNodes = function (nodes) {
        var blockEl = nodes ? nodes.blockEl : null;
        var titleEl = nodes ? nodes.titleEl : null;
        var bodyEl = nodes ? nodes.bodyEl : null;
        var titleText = titleEl ? String(titleEl.textContent || '').replace(/\s+/g, '').trim() : '';

        if (bodyEl && bodyEl.querySelector && bodyEl.querySelector('.expert-item-card, .expert-item-main, .issue-prefix-expert')) {
            return true;
        }

        return !!(blockEl && blockEl.tagName === 'SECTION' && titleText.indexOf('高手') !== -1);
    };

    var configureSectionEditorUi = function () {
        var firstPanelTitle = null;
        var secondPanel = null;
        var secondPanelTitle = null;
        var colorFieldsContainer = null;
        var cardFieldsContainer = null;
        var helperNote = null;
        var cardHelperNote = null;

        if (!sectionEditorModal) {
            return;
        }

        firstPanelTitle = sectionEditorModal.querySelector('.admin-section-editor-panel-title');
        secondPanel = sectionEditorCardPanel;
        secondPanelTitle = secondPanel ? secondPanel.querySelector('.admin-section-editor-panel-title') : null;
        colorFieldsContainer = sectionEditorTitleStart && sectionEditorTitleStart.closest ? sectionEditorTitleStart.closest('.admin-section-editor-fields') : null;
        cardFieldsContainer = sectionEditorCardStyle && sectionEditorCardStyle.closest ? sectionEditorCardStyle.closest('.admin-section-editor-fields') : null;

        if (firstPanelTitle) {
            firstPanelTitle.textContent = '标题背景颜色';
        }

        if (secondPanelTitle) {
            secondPanelTitle.textContent = '当前卡片样式';
        }

        if (firstPanelTitle) {
            firstPanelTitle.textContent = '标题设置';
        }

        hideSectionEditorField(sectionEditorTitleHtml);
        hideSectionEditorField(sectionEditorTitleStyle);
        hideSectionEditorField(sectionEditorTitleClass);
        hideSectionEditorField(sectionEditorSectionClass);
        hideSectionEditorField(sectionEditorSectionStyle);
        hideSectionEditorField(sectionEditorBodyClass);
        hideSectionEditorField(sectionEditorBodyStyle);
        hideSectionEditorField(sectionEditorBodyBackground);
        hideSectionEditorField(sectionEditorBodyBorder);
        hideSectionEditorField(sectionEditorBodyHtml);

        if (secondPanel) {
            secondPanel.hidden = true;
        }

        if (colorFieldsContainer && !sectionEditorModal.querySelector('[data-section-editor-color-help]')) {
            helperNote = document.createElement('div');
            helperNote.className = 'admin-help';
            helperNote.setAttribute('data-section-editor-color-help', '1');
            helperNote.textContent = '这里只保留标题背景色调整。起始色和结束色相同，就会显示为纯色背景。';
            colorFieldsContainer.appendChild(helperNote);
        }
    };

        /* if (cardFieldsContainer && !sectionEditorModal.querySelector('[data-section-editor-card-help]')) {
            cardHelperNote = document.createElement('div');
            cardHelperNote.className = 'admin-help';
            cardHelperNote.setAttribute('data-section-editor-card-help', '1');
            cardHelperNote.textContent = '这里调整当前卡片的外层样式、文字大小和文字颜色。文字大小可填 16px、18px 或 1rem。';
            cardFieldsContainer.appendChild(cardHelperNote);
        } */

    configureSectionEditorUi();
    helperNote = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-color-help]') : null;
    if (helperNote) {
        helperNote.textContent = '这里可以直接编辑主卡片标题文案、标题文字颜色、背景颜色和标题样式。';
    }

    var setSectionEditorOpen = function (open) {
        if (!sectionEditorModal) {
            return;
        }

        if (open) {
            sectionEditorModal.removeAttribute('hidden');
            document.body.classList.add('admin-section-editor-open');
            return;
        }

        sectionEditorModal.setAttribute('hidden', 'hidden');
        document.body.classList.remove('admin-section-editor-open');
    };

    var clearActiveSection = function () {
        var nodes = [
            activeSectionState.blockEl,
            activeSectionState.sectionEl,
            activeSectionState.titleEl,
            activeSectionState.bodyEl
        ];
        var index = 0;

        for (index = 0; index < nodes.length; index += 1) {
            if (nodes[index] && nodes[index].removeAttribute) {
                nodes[index].removeAttribute('data-section-editor-active');
            }
        }
    };

    var normalizeHexColor = function (value) {
        var normalized = (value || '').toString().trim();

        if (normalized === '') {
            return '';
        }

        if (normalized.charAt(0) === '#') {
            normalized = normalized.slice(1);
        }

        if (normalized.length === 3) {
            normalized = normalized.charAt(0) + normalized.charAt(0) +
                normalized.charAt(1) + normalized.charAt(1) +
                normalized.charAt(2) + normalized.charAt(2);
        }

        if (!/^[0-9a-fA-F]{6}$/.test(normalized)) {
            return '';
        }

        return '#' + normalized.toLowerCase();
    };

    var rgbStringToHex = function (value) {
        var match = (value || '').match(/rgba?\(([^)]+)\)/i);
        var parts = null;
        var red = 0;
        var green = 0;
        var blue = 0;
        var toHex = function (number) {
            var hex = Number(number).toString(16);
            return hex.length === 1 ? '0' + hex : hex;
        };

        if (!match) {
            return '';
        }

        parts = match[1].split(',');
        if (parts.length < 3) {
            return '';
        }

        red = Math.max(0, Math.min(255, parseInt(parts[0], 10) || 0));
        green = Math.max(0, Math.min(255, parseInt(parts[1], 10) || 0));
        blue = Math.max(0, Math.min(255, parseInt(parts[2], 10) || 0));

        return '#' + toHex(red) + toHex(green) + toHex(blue);
    };

    var colorToHex = function (value) {
        var normalized = normalizeHexColor(value);

        if (normalized !== '') {
            return normalized;
        }

        if (!value || value === 'transparent' || value === 'rgba(0, 0, 0, 0)') {
            return '';
        }

        return rgbStringToHex(value);
    };

    var extractGradientColors = function (backgroundImage, backgroundColor) {
        var gradientValue = (backgroundImage || '').toString();
        var matches = gradientValue.match(/(rgba?\([^)]+\)|#[0-9a-fA-F]{3,8})/g);
        var startColor = '';
        var endColor = '';

        if (matches && matches.length > 0) {
            startColor = colorToHex(matches[0]);
            endColor = colorToHex(matches[matches.length > 1 ? 1 : 0]);
        } else {
            startColor = colorToHex(backgroundColor);
            endColor = startColor;
        }

        return {
            start: startColor || '#1e40af',
            end: endColor || startColor || '#3b82f6'
        };
    };

    var getStoredTitleBackgroundColors = function (element, fallbackImage, fallbackColor) {
        var start = element ? colorToHex(element.getAttribute('data-title-bg-start')) : '';
        var end = element ? colorToHex(element.getAttribute('data-title-bg-end')) : '';

        if (start || end) {
            if (!start) {
                start = end;
            }

            if (!end) {
                end = start;
            }

            return {
                start: start || '#1e40af',
                end: end || start || '#3b82f6'
            };
        }

        return extractGradientColors(fallbackImage, fallbackColor);
    };

    var getCleanNodeHtml = function (element) {
        var clone = null;
        var controls = null;
        var index = 0;

        if (!element) {
            return '';
        }

        clone = element.cloneNode(true);
        controls = clone.querySelectorAll('[data-section-editor-control]');

        for (index = 0; index < controls.length; index += 1) {
            controls[index].parentNode.removeChild(controls[index]);
        }

        clone.removeAttribute('data-section-editor-active');

        return clone.innerHTML;
    };
    var getSectionTitleTextFieldVisible = function () {
        return !!(sectionEditorTitleTextField && !sectionEditorTitleTextField.hidden);
    };

    var extractSectionTitleText = function (titleEl) {
        var holder = document.createElement('div');
        var decorativeNodes = null;
        var index = 0;

        if (!titleEl) {
            return '';
        }

        holder.innerHTML = getCleanNodeHtml(titleEl);
        decorativeNodes = holder.querySelectorAll('i, svg, img, [aria-hidden="true"]');
        for (index = 0; index < decorativeNodes.length; index += 1) {
            if (decorativeNodes[index].parentNode) {
                decorativeNodes[index].parentNode.removeChild(decorativeNodes[index]);
            }
        }

        return String(holder.textContent || '').replace(/\s+/g, ' ').trim();
    };

    var sanitizeSectionTitleIconClass = function (value) {
        return String(value || '')
            .replace(/[<>"']/g, '')
            .replace(/\s+/g, ' ')
            .split(' ')
            .filter(function (token) {
                return /^[a-z0-9_-]+$/i.test(token);
            })
            .join(' ')
            .trim();
    };

    var isSectionTitleIconImageValue = function (value) {
        var text = String(value || '').trim();

        return text !== '' && (
            /^(?:\/|https?:\/\/|data:image\/)/i.test(text) ||
            /\.(?:jpg|jpeg|png|gif|webp|bmp)(?:[?#].*)?$/i.test(text)
        );
    };

    var sanitizeSectionTitleIconValue = function (value) {
        var text = String(value || '').trim();

        if (isSectionTitleIconImageValue(text)) {
            return text.replace(/[<>"']/g, '');
        }

        return sanitizeSectionTitleIconClass(text);
    };

    var buildSectionTitleIconHtml = function (iconValue) {
        var icon = sanitizeSectionTitleIconValue(iconValue);

        if (!icon) {
            return '';
        }

        if (isSectionTitleIconImageValue(icon)) {
            return '<img class="section-title-icon-image" src="' + escapeHtmlAttribute(icon) + '" alt="" aria-hidden="true" style="width:1.1em;height:1.1em;object-fit:contain;vertical-align:-0.12em;">';
        }

        return '<i class="' + escapeHtmlAttribute(icon) + '" aria-hidden="true"></i>';
    };

    var extractSectionTitleIconValue = function (titleEl) {
        var holder = document.createElement('div');
        var iconNode = null;

        if (!titleEl) {
            return '';
        }

        holder.innerHTML = getCleanNodeHtml(titleEl);
        iconNode = holder.querySelector('img');
        if (iconNode) {
            return sanitizeSectionTitleIconValue(iconNode.getAttribute('src') || '');
        }

        iconNode = holder.querySelector('i');

        return iconNode ? sanitizeSectionTitleIconValue(iconNode.className || '') : '';
    };

    var buildSectionTitleHtmlWithText = function (titleEl, textValue, iconClassValue) {
        var holder = document.createElement('div');
        var output = '';
        var children = null;
        var index = 0;
        var child = null;
        var tagName = '';
        var className = '';
        var text = String(textValue || '').replace(/\s+/g, ' ').trim();
        var iconValue = typeof iconClassValue === 'string' ? sanitizeSectionTitleIconValue(iconClassValue) : null;

        if (iconValue !== null) {
            output = buildSectionTitleIconHtml(iconValue);
            return output + escapeHtmlText(text);
        }

        if (titleEl) {
            holder.innerHTML = getCleanNodeHtml(titleEl);
            children = Array.prototype.slice.call(holder.childNodes || []);
            for (index = 0; index < children.length; index += 1) {
                child = children[index];
                if (child.nodeType !== 1) {
                    continue;
                }
                tagName = String(child.tagName || '').toLowerCase();
                className = String(child.className || '');
                if (tagName === 'i' || tagName === 'svg' || tagName === 'img' || /(^|\s)fa[srbl]?(\s|-)/.test(className)) {
                    output += child.outerHTML;
                }
            }
        }

        return output + escapeHtmlText(text);
    };

    var ensureMarqueeTrack = function (blockEl) {
        var doc = blockEl ? blockEl.ownerDocument : null;
        var scrollingTextEl = blockEl ? blockEl.querySelector('.scrolling-text') : null;
        var trackEl = blockEl ? blockEl.querySelector('.scrolling-track') : null;

        if (!blockEl || !doc) {
            return null;
        }

        if (!scrollingTextEl) {
            scrollingTextEl = doc.createElement('div');
            scrollingTextEl.className = 'scrolling-text';
            blockEl.appendChild(scrollingTextEl);
        }

        if (!trackEl) {
            trackEl = doc.createElement('div');
            trackEl.className = 'scrolling-track';
            scrollingTextEl.appendChild(trackEl);
        }

        return trackEl;
    };

    var getMarqueeText = function (blockEl) {
        var primaryEl = blockEl ? blockEl.querySelector('#home-marquee-primary') : null;
        var trackEl = blockEl ? blockEl.querySelector('.scrolling-track') : null;
        var text = '';

        if (primaryEl) {
            text = primaryEl.textContent || '';
        } else if (trackEl) {
            text = trackEl.textContent || '';
        }

        return text.replace(/\s+/g, ' ').trim();
    };

    var setMarqueeText = function (blockEl, value) {
        var doc = blockEl ? blockEl.ownerDocument : null;
        var trackEl = ensureMarqueeTrack(blockEl);
        var normalized = (value || '').replace(/\s+/g, ' ').trim();
        var primaryEl = null;
        var secondaryEl = null;

        if (!doc || !trackEl) {
            return;
        }

        while (trackEl.firstChild) {
            trackEl.removeChild(trackEl.firstChild);
        }

        primaryEl = doc.createElement('span');
        primaryEl.id = 'home-marquee-primary';
        primaryEl.textContent = normalized;

        secondaryEl = doc.createElement('span');
        secondaryEl.id = 'home-marquee-secondary';
        secondaryEl.setAttribute('aria-hidden', 'true');
        secondaryEl.textContent = normalized;

        trackEl.appendChild(primaryEl);
        trackEl.appendChild(doc.createTextNode(' '));
        trackEl.appendChild(secondaryEl);
    };

    var setInlineStyle = function (element, styleText) {
        var normalized = (styleText || '').trim();

        if (!element) {
            return;
        }

        if (normalized === '') {
            element.removeAttribute('style');
            return;
        }

        element.setAttribute('style', normalized);
    };

    var applyTitleBackgroundColors = function (element, startColor, endColor) {
        var start = (startColor || '').trim();
        var end = (endColor || '').trim();

        if (!element) {
            return;
        }

        if (start || end) {
            element.setAttribute('data-title-bg-start', start || end);
            element.setAttribute('data-title-bg-end', end || start);
        } else {
            element.removeAttribute('data-title-bg-start');
            element.removeAttribute('data-title-bg-end');
        }

        element.style.removeProperty('background');

        if (!start && !end) {
            element.style.removeProperty('background-color');
            element.style.removeProperty('background-image');
            return;
        }

        if (!start) {
            start = end;
        }

        if (!end) {
            end = start;
        }

        if (start && end && start !== end) {
            element.style.backgroundColor = start;
            element.style.backgroundImage = 'linear-gradient(90deg, ' + start + ', ' + end + ')';
            return;
        }

        element.style.backgroundImage = 'none';
        element.style.backgroundColor = start || end;
    };

    var normalizeTitleAlign = function (value) {
        var normalized = (value || '').toString().trim().toLowerCase();

        if (normalized === 'center' || normalized === 'right') {
            return normalized;
        }

        return 'left';
    };

    var resolveTitleAlign = function (element, computedStyles) {
        var storedAlign = element ? normalizeTitleAlign(element.getAttribute('data-title-align') || '') : 'left';
        var justifyContent = computedStyles && computedStyles.justifyContent
            ? String(computedStyles.justifyContent).toLowerCase()
            : '';
        var textAlign = computedStyles && computedStyles.textAlign
            ? String(computedStyles.textAlign).toLowerCase()
            : '';

        if (element && element.hasAttribute('data-title-align')) {
            return storedAlign;
        }

        if (justifyContent.indexOf('center') !== -1 || textAlign === 'center') {
            return 'center';
        }

        if (justifyContent.indexOf('flex-end') !== -1 || justifyContent.indexOf('end') !== -1 || textAlign === 'right') {
            return 'right';
        }

        return 'left';
    };

    var applyTitleAlign = function (element, align) {
        var normalized = normalizeTitleAlign(align);

        if (!element) {
            return;
        }

        element.setAttribute('data-title-align', normalized);

        if (normalized === 'center') {
            element.style.justifyContent = 'center';
            element.style.textAlign = 'center';
            return;
        }

        if (normalized === 'right') {
            element.style.justifyContent = 'flex-end';
            element.style.textAlign = 'right';
            return;
        }

        element.style.justifyContent = 'flex-start';
        element.style.textAlign = 'left';
    };

    var applyManagedTitleBackgrounds = function (root) {
        var targets = root ? root.querySelectorAll('[data-title-bg-start], [data-title-bg-end]') : [];
        var index = 0;
        var start = '';
        var end = '';

        for (index = 0; index < targets.length; index += 1) {
            start = colorToHex(targets[index].getAttribute('data-title-bg-start') || '');
            end = colorToHex(targets[index].getAttribute('data-title-bg-end') || '');

            if (!start && !end) {
                continue;
            }

            applyTitleBackgroundColors(targets[index], start, end);
        }
    };

    var updateSectionEditorTitlePreview = function () {
        var start = normalizeHexColor(sectionEditorTitleStart ? sectionEditorTitleStart.value : '') || '#1e40af';
        var end = normalizeHexColor(sectionEditorTitleEnd ? sectionEditorTitleEnd.value : '') || start || '#3b82f6';
        var textColor = normalizeHexColor(sectionEditorTitleColor ? sectionEditorTitleColor.value : '') || '#ffffff';
        var previewHtml = getSectionTitleTextFieldVisible()
            ? buildSectionTitleHtmlWithText(activeSectionState.titleEl, sectionEditorTitleText ? sectionEditorTitleText.value : '', sectionEditorTitleIcon ? sectionEditorTitleIcon.value : '')
            : (sectionEditorTitleHtml ? (sectionEditorTitleHtml.value || '').trim() : '');
        var colorValue = '';

        if (!sectionEditorTitlePreview) {
            return;
        }

        if (sectionEditorTitlePreviewBar) {
            applyTitleBackgroundColors(sectionEditorTitlePreviewBar, start, end);
            sectionEditorTitlePreviewBar.style.color = textColor;
        }

        if (sectionEditorTitlePreviewText) {
            sectionEditorTitlePreviewText.innerHTML = previewHtml || '标题区预览';
            sectionEditorTitlePreviewText.style.color = textColor;
        }

        if (sectionEditorTitlePreviewMode) {
            sectionEditorTitlePreviewMode.textContent = start === end ? '纯色背景' : '渐变背景';
        }

        if (sectionEditorTitlePreviewSwatches && sectionEditorTitlePreviewValues) {
            ['start', 'end', 'text'].forEach(function (name) {
                colorValue = name === 'start' ? start : (name === 'end' ? end : textColor);

                if (sectionEditorTitlePreviewSwatches[name]) {
                    sectionEditorTitlePreviewSwatches[name].style.background = colorValue;
                }

                if (sectionEditorTitlePreviewValues[name]) {
                    sectionEditorTitlePreviewValues[name].textContent = colorValue;
                }
            });
        }
    };

    var updateSectionEditorTitlePreview = function () {
        var start = normalizeHexColor(sectionEditorTitleStart ? sectionEditorTitleStart.value : '') || '#1e40af';
        var end = normalizeHexColor(sectionEditorTitleEnd ? sectionEditorTitleEnd.value : '') || start || '#3b82f6';
        var textColor = normalizeHexColor(sectionEditorTitleColor ? sectionEditorTitleColor.value : '') || '#ffffff';
        var titleAlign = normalizeTitleAlign(sectionEditorTitleAlign ? sectionEditorTitleAlign.value : 'left');
        var previewHtml = getSectionTitleTextFieldVisible()
            ? buildSectionTitleHtmlWithText(activeSectionState.titleEl, sectionEditorTitleText ? sectionEditorTitleText.value : '', sectionEditorTitleIcon ? sectionEditorTitleIcon.value : '')
            : (sectionEditorTitleHtml ? (sectionEditorTitleHtml.value || '').trim() : '');
        var isAdSection = !!(sectionEditorAdCopyPanel && !sectionEditorAdCopyPanel.hidden);
        var adItemBorderColor = getSectionEditorAdItemBorderColor();
        var previewItem = null;
        var previewLead = sectionEditorAdCopyInputs.length && sectionEditorAdCopyInputs[0] && sectionEditorAdCopyInputs[0].lead
            ? String(sectionEditorAdCopyInputs[0].lead.value || '').trim()
            : '';
        var previewMiddle = sectionEditorAdCopyInputs.length && sectionEditorAdCopyInputs[0] && sectionEditorAdCopyInputs[0].middle
            ? String(sectionEditorAdCopyInputs[0].middle.value || '').trim()
            : '';
        var previewTail = sectionEditorAdCopyInputs.length && sectionEditorAdCopyInputs[0] && sectionEditorAdCopyInputs[0].tail
            ? String(sectionEditorAdCopyInputs[0].tail.value || '').trim()
            : '';
        var colorValue = '';

        previewLead = previewLead || '前段文案';
        previewMiddle = previewMiddle || '【中段文案】';
        previewTail = previewTail || '后段文案';

        if (!sectionEditorTitlePreview) {
            return;
        }

        if (sectionEditorTitlePreviewBar) {
            applyTitleBackgroundColors(sectionEditorTitlePreviewBar, start, end);
            sectionEditorTitlePreviewBar.style.color = textColor;
        }

        if (sectionEditorTitlePreviewText) {
            sectionEditorTitlePreviewText.innerHTML = previewHtml || '标题区预览';
            sectionEditorTitlePreviewText.style.color = textColor;
        }

        if (sectionEditorTitlePreviewBody) {
            if (isAdSection) {
                sectionEditorTitlePreviewBody.classList.add('is-ad-copy-preview');
                sectionEditorTitlePreviewBody.classList.remove('is-expert-ad-preview');
                sectionEditorTitlePreviewBody.innerHTML =
                    '<div class="admin-section-editor-ad-card-preview-item">' +
                        '<span class="ad-item-lead">' + escapeHtmlText(previewLead || '前段边框') + '</span>' +
                        '<span class="ad-item-middle">' + escapeHtmlText(previewMiddle || '中段边框') + '</span>' +
                        '<span class="ad-item-tail">' + escapeHtmlText(previewTail || '后段边框') + '</span>' +
                    '</div>';
                previewItem = sectionEditorTitlePreviewBody.querySelector('.admin-section-editor-ad-card-preview-item');
                applySectionAdItemBorderColor(previewItem ? [previewItem] : [], adItemBorderColor);
            } else if (isExpertSection) {
                sectionEditorTitlePreviewBody.classList.remove('is-ad-copy-preview');
                sectionEditorTitlePreviewBody.classList.add('is-expert-ad-preview');
                sectionEditorTitlePreviewBody.innerHTML =
                    '<div class="admin-section-editor-expert-ad-preview-item">' +
                        '<span class="admin-section-editor-expert-ad-preview-main">' +
                            '<span class="admin-section-editor-expert-ad-preview-prefix">' + (expertAdInterval > 0 ? '\u6bcf' + expertAdInterval + '\u6761' : '\u672a\u542f\u7528') + '</span>' +
                            '<span class="admin-section-editor-expert-ad-preview-title">' +
                                '<span class="ad-item-lead">' + escapeHtmlText(expertAdEntry.lead || defaultSectionExpertAdTitle) + '</span>' +
                                '<span class="ad-item-middle">' + escapeHtmlText(expertAdEntry.middle || '') + '</span>' +
                                '<span class="ad-item-tail">' + escapeHtmlText(expertAdEntry.tail || '') + '</span>' +
                            '</span>' +
                        '</span>' +
                        '<span class="admin-section-editor-expert-ad-preview-meta">' +
                            '<span class="admin-section-editor-expert-ad-preview-badge">' + escapeHtmlText(expertBadgeText) + '</span>' +
                            '<span class="expert-view-count"><span class="expert-view-icon" aria-hidden="true">👁</span><span class="expert-view-number">6388</span></span>' +
                        '</span>' +
                    '</div>';
            } else {
                sectionEditorTitlePreviewBody.classList.remove('is-ad-copy-preview');
                sectionEditorTitlePreviewBody.classList.remove('is-expert-ad-preview');
                sectionEditorTitlePreviewBody.innerHTML = '';
            }
        }

        if (sectionEditorTitlePreviewMode) {
            sectionEditorTitlePreviewMode.textContent = start === end ? '纯色背景' : '渐变背景';
        }

        if (sectionEditorTitlePreviewSwatches && sectionEditorTitlePreviewValues) {
            ['start', 'end', 'text'].forEach(function (name) {
                colorValue = name === 'start' ? start : (name === 'end' ? end : textColor);

                if (sectionEditorTitlePreviewSwatches[name]) {
                    sectionEditorTitlePreviewSwatches[name].style.background = colorValue;
                }

                if (sectionEditorTitlePreviewValues[name]) {
                    sectionEditorTitlePreviewValues[name].textContent = colorValue;
                }
            });
        }
    };

    var updateSectionEditorTitlePreview = function () {
        var start = normalizeHexColor(sectionEditorTitleStart ? sectionEditorTitleStart.value : '') || '#1e40af';
        var end = normalizeHexColor(sectionEditorTitleEnd ? sectionEditorTitleEnd.value : '') || start || '#3b82f6';
        var textColor = normalizeHexColor(sectionEditorTitleColor ? sectionEditorTitleColor.value : '') || '#ffffff';
        var titleAlign = normalizeTitleAlign(sectionEditorTitleAlign ? sectionEditorTitleAlign.value : 'left');
        var previewHtml = getSectionTitleTextFieldVisible()
            ? buildSectionTitleHtmlWithText(activeSectionState.titleEl, sectionEditorTitleText ? sectionEditorTitleText.value : '', sectionEditorTitleIcon ? sectionEditorTitleIcon.value : '')
            : (sectionEditorTitleHtml ? (sectionEditorTitleHtml.value || '').trim() : '');
        var isAdSection = !!(sectionEditorAdCopyPanel && !sectionEditorAdCopyPanel.hidden);
        var isExpertSection = !!(sectionEditorExpertAdPanel && !sectionEditorExpertAdPanel.hidden);
        var adItemBorderColor = getSectionEditorAdItemBorderColor();
        var expertAdEntries = compactSectionExpertAdEntries(readSectionEditorExpertAdEntries());
        var expertAdInterval = resolveSectionExpertAdEffectiveInterval(sectionEditorExpertAdInterval ? sectionEditorExpertAdInterval.value : '', expertAdEntries);
        var expertAdEntry = expertAdEntries.length ? expertAdEntries[0] : createEmptySectionEditorExpertAdEntry();
        var expertAdTitle = joinAdItemTextParts(expertAdEntry) || defaultSectionExpertAdTitle;
        var expertBadgeText = normalizeSectionExpertAdText(expertAdEntry.badgeText || '广告', 12) || '广告';
        var expertMiddleColorMode = resolveAdItemMiddleColorMode(expertAdEntry.middleColorMode || 'default');
        var expertMiddleColor = normalizeHexColor(expertAdEntry.middleColor || '') || '#2563eb';
        var expertMiddleAttrs = ' class="ad-item-middle"';
        var expertMiddleColorKey = expertAdTitle;
        var expertTailTextMode = resolveAdItemTailTextMode(expertAdEntry.tailTextMode || 'fixed');
        var expertTailRandomItems = parseAdItemRandomWordList(expertAdEntry.tailRandomWords || '');
        var expertTailText = String(expertAdEntry.tail || '').trim();
        var expertTailAttrs = ' class="ad-item-tail"';
        var expertTailRandomSeed = (String(expertAdEntry.lead || '') + '|' + String(expertAdEntry.middle || '') + '|' + serializeAdItemRandomWordList(expertTailRandomItems)).trim();
        var previewLead = sectionEditorAdCopyInputs.length && sectionEditorAdCopyInputs[0] && sectionEditorAdCopyInputs[0].lead
            ? String(sectionEditorAdCopyInputs[0].lead.value || '').trim()
            : '';
        var previewMiddle = sectionEditorAdCopyInputs.length && sectionEditorAdCopyInputs[0] && sectionEditorAdCopyInputs[0].middle
            ? String(sectionEditorAdCopyInputs[0].middle.value || '').trim()
            : '';
        var previewTail = sectionEditorAdCopyInputs.length && sectionEditorAdCopyInputs[0] && sectionEditorAdCopyInputs[0].tail
            ? String(sectionEditorAdCopyInputs[0].tail.value || '').trim()
            : '';
        var previewMiddleColorMode = sectionEditorAdCopyInputs.length && sectionEditorAdCopyInputs[0] && sectionEditorAdCopyInputs[0].middleColorMode
            ? resolveAdItemMiddleColorMode(sectionEditorAdCopyInputs[0].middleColorMode.value)
            : 'default';
        var previewMiddleColor = sectionEditorAdCopyInputs.length && sectionEditorAdCopyInputs[0] && sectionEditorAdCopyInputs[0].middleColor
            ? normalizeHexColor(sectionEditorAdCopyInputs[0].middleColor.value)
            : '';
        var previewItem = null;
        var previewMiddleNode = null;
        var colorValue = '';

        previewLead = previewLead || '\u524d\u6bb5\u6587\u6848';
        previewMiddle = previewMiddle || '\u3010\u4e2d\u6bb5\u6587\u6848\u3011';
        previewTail = previewTail || '\u540e\u6bb5\u6587\u6848';

        if (expertTailText === '' && expertTailRandomItems.length) {
            expertTailText = expertTailRandomItems[0];
        }

        if (expertMiddleColorMode === 'fixed' && expertMiddleColor !== '') {
            expertMiddleAttrs += ' data-middle-color-mode="fixed"';
            expertMiddleAttrs += ' data-middle-fixed-color="' + escapeHtmlAttribute(expertMiddleColor) + '"';
            expertMiddleAttrs += ' style="color:' + escapeHtmlAttribute(expertMiddleColor) + ';"';
        } else if (expertMiddleColorMode === 'daily-random') {
            expertMiddleAttrs += ' data-middle-color-mode="daily-random"';
            expertMiddleAttrs += ' data-middle-color-key="' + escapeHtmlAttribute(expertMiddleColorKey) + '"';
        }

        if (expertTailTextMode === 'daily-random') {
            expertTailAttrs += ' data-tail-text-mode="daily-random"';
            expertTailAttrs += ' data-tail-default-text="' + escapeHtmlAttribute(expertTailText) + '"';
            expertTailAttrs += ' data-tail-random-options="' + escapeHtmlAttribute(serializeAdItemRandomWordList(expertTailRandomItems)) + '"';
            expertTailAttrs += ' data-tail-random-key="' + escapeHtmlAttribute(expertTailRandomSeed) + '"';
        }

        if (!sectionEditorTitlePreview) {
            return;
        }

        if (sectionEditorTitlePreviewBar) {
            applyTitleBackgroundColors(sectionEditorTitlePreviewBar, start, end);
            sectionEditorTitlePreviewBar.style.color = textColor;
            applyTitleAlign(sectionEditorTitlePreviewBar, titleAlign);
        }

        if (sectionEditorTitlePreviewText) {
            sectionEditorTitlePreviewText.innerHTML = previewHtml || '\u6807\u9898\u533a\u9884\u89c8';
            sectionEditorTitlePreviewText.style.color = textColor;
            sectionEditorTitlePreviewText.style.textAlign = titleAlign;
        }

        if (sectionEditorTitlePreviewBody) {
            if (isAdSection) {
                sectionEditorTitlePreviewBody.classList.add('is-ad-copy-preview');
                sectionEditorTitlePreviewBody.classList.remove('is-expert-ad-preview');
                sectionEditorTitlePreviewBody.innerHTML =
                    '<div class="admin-section-editor-ad-card-preview-item">' +
                        '<span class="ad-item-lead">' + escapeHtmlText(previewLead) + '</span>' +
                        '<span class="ad-item-middle">' + escapeHtmlText(previewMiddle) + '</span>' +
                        '<span class="ad-item-tail">' + escapeHtmlText(previewTail) + '</span>' +
                    '</div>';
                previewItem = sectionEditorTitlePreviewBody.querySelector('.admin-section-editor-ad-card-preview-item');
                previewMiddleNode = previewItem ? previewItem.querySelector('.ad-item-middle') : null;
                if (previewMiddleNode) {
                    if (previewMiddleColorMode === 'fixed' && previewMiddleColor !== '') {
                        previewMiddleNode.style.color = previewMiddleColor;
                    } else {
                        previewMiddleNode.style.removeProperty('color');
                    }
                }
                applySectionAdItemBorderColor(previewItem ? [previewItem] : [], adItemBorderColor);
            } else if (isExpertSection) {
                sectionEditorTitlePreviewBody.classList.remove('is-ad-copy-preview');
                sectionEditorTitlePreviewBody.classList.add('is-expert-ad-preview');
                sectionEditorTitlePreviewBody.innerHTML =
                    '<div class="admin-section-editor-expert-ad-preview-item">' +
                        '<span class="admin-section-editor-expert-ad-preview-main">' +
                            '<span class="admin-section-editor-expert-ad-preview-prefix">' + (expertAdInterval > 0 ? '\u6bcf' + expertAdInterval + '\u6761' : '\u672a\u542f\u7528') + '</span>' +
                            '<span class="admin-section-editor-expert-ad-preview-title">' +
                                '<span class="ad-item-lead">' + escapeHtmlText(expertAdEntry.lead || defaultSectionExpertAdTitle) + '</span>' +
                                '<span' + expertMiddleAttrs + '>' + escapeHtmlText(expertAdEntry.middle || '') + '</span>' +
                                '<span' + expertTailAttrs + '>' + escapeHtmlText(expertTailText) + '</span>' +
                            '</span>' +
                        '</span>' +
                        '<span class="admin-section-editor-expert-ad-preview-meta">' +
                            '<span class="admin-section-editor-expert-ad-preview-badge">\u5e7f\u544a</span>' +
                            '<span class="expert-view-count"><span class="expert-view-icon" aria-hidden="true">👁</span><span class="expert-view-number">6388</span></span>' +
                        '</span>' +
                    '</div>';
                applyAdItemMiddleColorPreview(sectionEditorTitlePreviewBody);
                applyAdItemTailTextPreview(sectionEditorTitlePreviewBody);
            } else {
                sectionEditorTitlePreviewBody.classList.remove('is-ad-copy-preview');
                sectionEditorTitlePreviewBody.classList.remove('is-expert-ad-preview');
                sectionEditorTitlePreviewBody.innerHTML = '';
            }
        }

        if (sectionEditorTitlePreviewMode) {
            sectionEditorTitlePreviewMode.textContent = start === end
                ? '\u7eaf\u8272\u80cc\u666f'
                : '\u6e10\u53d8\u80cc\u666f';
        }

        if (sectionEditorTitlePreviewSwatches && sectionEditorTitlePreviewValues) {
            ['start', 'end', 'text'].forEach(function (name) {
                colorValue = name === 'start' ? start : (name === 'end' ? end : textColor);

                if (sectionEditorTitlePreviewSwatches[name]) {
                    sectionEditorTitlePreviewSwatches[name].style.background = colorValue;
                }

                if (sectionEditorTitlePreviewValues[name]) {
                    sectionEditorTitlePreviewValues[name].textContent = colorValue;
                }
            });
        }
    };

    var ensureTitleClassName = function (className) {
        var tokens = (className || '').split(/\s+/);
        var filtered = [];
        var seenSectionTitle = false;
        var index = 0;

        for (index = 0; index < tokens.length; index += 1) {
            if (!tokens[index]) {
                continue;
            }

            if (tokens[index] === 'section-title') {
                seenSectionTitle = true;
            }

            filtered.push(tokens[index]);
        }

        if (!seenSectionTitle) {
            filtered.unshift('section-title');
        }

        return filtered.join(' ').trim();
    };

    var sanitizeTitleClassName = function (className, isMarquee) {
        var tokens = (className || '').split(/\s+/);
        var filtered = [];
        var requiredClass = isMarquee ? 'marquee-tag' : 'section-title';
        var seenRequired = false;
        var index = 0;

        for (index = 0; index < tokens.length; index += 1) {
            if (!tokens[index]) {
                continue;
            }

            if (tokens[index] === requiredClass) {
                seenRequired = true;
                filtered.push(tokens[index]);
                continue;
            }

            if (tokens[index] === 'bg-gradient-to-r' || tokens[index].indexOf('from-') === 0 || tokens[index].indexOf('to-') === 0) {
                continue;
            }

            filtered.push(tokens[index]);
        }

        if (!seenRequired) {
            filtered.unshift(requiredClass);
        }

        return filtered.join(' ').trim();
    };

    var getDirectSectionTitle = function (blockEl) {
        var children = blockEl ? blockEl.children : [];
        var index = 0;

        for (index = 0; index < children.length; index += 1) {
            if (children[index].classList && children[index].classList.contains('section-title')) {
                return children[index];
            }
        }

        return null;
    };

    var isSectionEditorHeaderBlock = function (blockEl) {
        return !!(
            blockEl &&
            blockEl.tagName === 'SECTION' &&
            blockEl.id === 'section-home'
        );
    };

    var isComponentFloatBlock = function (element) {
        var body = element && element.ownerDocument ? element.ownerDocument.body : null;

        return !!(
            element
            && body
            && body.classList
            && body.classList.contains('admin-draw-component-preview')
            && element.classList
            && (
                (element.tagName === 'HEADER' && element.classList.contains('top-bar'))
                || (element.tagName === 'NAV' && element.classList.contains('bottom-float-nav'))
            )
        );
    };

    var getSectionNodesFromBlock = function (blockEl) {
        var titleEl = null;
        var bodyEl = null;
        var isMarquee = false;

        if (!blockEl) {
            return null;
        }

        isMarquee = !!(blockEl.classList && blockEl.classList.contains('marquee'));
        if (isMarquee) {
            titleEl = blockEl.querySelector('.marquee-tag');
            bodyEl = blockEl.querySelector('.scrolling-track') || blockEl.querySelector('.scrolling-text');

            return {
                blockEl: blockEl,
                sectionEl: null,
                titleEl: titleEl,
                bodyEl: bodyEl,
                styleTargetEl: blockEl,
                textTargetEl: bodyEl || blockEl,
                blockType: 'marquee'
            };
        }

        if (isComponentFloatBlock(blockEl)) {
            return {
                blockEl: blockEl,
                sectionEl: null,
                titleEl: null,
                bodyEl: blockEl,
                styleTargetEl: blockEl,
                textTargetEl: blockEl,
                blockType: 'component'
            };
        }

        titleEl = getDirectSectionTitle(blockEl);
        bodyEl = titleEl
            ? titleEl.nextElementSibling
            : (isSectionEditorHeaderBlock(blockEl) ? blockEl : null);

        return {
            blockEl: blockEl,
            sectionEl: blockEl.tagName === 'SECTION' ? blockEl : null,
            titleEl: titleEl,
            bodyEl: bodyEl,
            styleTargetEl: blockEl,
            textTargetEl: bodyEl || blockEl,
            blockType: 'section'
        };
    };

    var getSectionNodesFromTitle = function (titleEl) {
        if (!titleEl) {
            return null;
        }

        return getSectionNodesFromBlock(titleEl.closest('section'));
    };

    var setBodyFieldsDisabled = function (disabled) {
        var index = 0;

        for (index = 0; index < sectionEditorBodyFields.length; index += 1) {
            if (!sectionEditorBodyFields[index]) {
                continue;
            }

            sectionEditorBodyFields[index].disabled = disabled;
        }
    };

    var getSectionLabel = function (titleEl) {
        var doc = titleEl ? titleEl.ownerDocument : null;
        var titles = doc ? doc.querySelectorAll('.section-title') : [];
        var cleanTitleHolder = null;
        var titleText = '';
        var index = 0;
        var position = 0;

        if (titleEl) {
            cleanTitleHolder = document.createElement('div');
            cleanTitleHolder.innerHTML = getCleanNodeHtml(titleEl);
            titleText = cleanTitleHolder.textContent.replace(/\s+/g, ' ').trim();
        }

        for (index = 0; index < titles.length; index += 1) {
            if (titles[index] === titleEl) {
                position = index + 1;
                break;
            }
        }

        return '主卡片 #' + position + (titleText ? ' · ' + titleText : '');
    };

    var getSectionLabel = function (nodes) {
        var blockEl = nodes ? nodes.blockEl : null;
        var titleEl = nodes ? nodes.titleEl : null;
        var siblings = blockEl && blockEl.parentNode ? getSortableBlocks(blockEl.parentNode) : [];
        var cleanTitleHolder = null;
        var titleText = '';
        var fallbackText = '';
        var index = 0;
        var position = 0;

        if (titleEl) {
            cleanTitleHolder = document.createElement('div');
            cleanTitleHolder.innerHTML = getCleanNodeHtml(titleEl);
            titleText = cleanTitleHolder.textContent.replace(/\s+/g, ' ').trim();
        }

        for (index = 0; index < siblings.length; index += 1) {
            if (siblings[index] === blockEl) {
                position = index + 1;
                break;
            }
        }

        if (!titleText && blockEl) {
            if (blockEl.classList && blockEl.classList.contains('marquee')) {
                fallbackText = '公告条';
            } else {
                fallbackText = blockEl.textContent.replace(/\s+/g, ' ').trim().slice(0, 16);
            }
        }

        return '主卡片 #' + position + (titleText ? ' · ' + titleText : (fallbackText ? ' · ' + fallbackText : ''));
    };

    var populateSectionEditor = function (nodes) {
        var titleStyles = null;
        var bodyStyles = null;
        var gradientColors = null;
        var styleWindow = null;

        if (!nodes || !nodes.titleEl) {
            return;
        }

        styleWindow = nodes.titleEl.ownerDocument && nodes.titleEl.ownerDocument.defaultView ? nodes.titleEl.ownerDocument.defaultView : window;
        titleStyles = styleWindow.getComputedStyle(nodes.titleEl);
        bodyStyles = nodes.bodyEl ? styleWindow.getComputedStyle(nodes.bodyEl) : null;
        gradientColors = extractGradientColors(titleStyles.backgroundImage, titleStyles.backgroundColor);

        if (sectionEditorTarget) {
            sectionEditorTarget.textContent = getSectionLabel(nodes.titleEl);
        }

        if (sectionEditorSectionClass) {
            sectionEditorSectionClass.value = nodes.sectionEl ? nodes.sectionEl.className : '';
        }

        if (sectionEditorSectionStyle) {
            sectionEditorSectionStyle.value = nodes.sectionEl ? (nodes.sectionEl.getAttribute('style') || '') : '';
        }

        if (sectionEditorTitleHtml) {
            sectionEditorTitleHtml.value = getCleanNodeHtml(nodes.titleEl);
        }

        if (sectionEditorTitleClass) {
            sectionEditorTitleClass.value = nodes.titleEl.className || 'section-title';
        }

        if (sectionEditorTitleStyle) {
            sectionEditorTitleStyle.value = nodes.titleEl.getAttribute('style') || '';
        }

        if (sectionEditorTitleStart) {
            sectionEditorTitleStart.value = gradientColors.start;
        }

        if (sectionEditorTitleEnd) {
            sectionEditorTitleEnd.value = gradientColors.end;
        }

        if (sectionEditorTitleColor) {
            sectionEditorTitleColor.value = colorToHex(titleStyles.color) || '#ffffff';
        }

        if (sectionEditorBodyClass) {
            sectionEditorBodyClass.value = nodes.bodyEl ? (nodes.bodyEl.className || '') : '';
        }

        if (sectionEditorBodyStyle) {
            sectionEditorBodyStyle.value = nodes.bodyEl ? (nodes.bodyEl.getAttribute('style') || '') : '';
        }

        if (sectionEditorBodyBackground) {
            sectionEditorBodyBackground.value = bodyStyles ? (colorToHex(bodyStyles.backgroundColor) || '#ffffff') : '#ffffff';
        }

        if (sectionEditorBodyBorder) {
            sectionEditorBodyBorder.value = bodyStyles ? (colorToHex(bodyStyles.borderTopColor) || '#d9e2ef') : '#d9e2ef';
        }

        if (sectionEditorBodyHtml) {
            sectionEditorBodyHtml.value = nodes.bodyEl ? nodes.bodyEl.innerHTML : '';
        }

        if (helperNote) {
            helperNote.hidden = !!isAdSection;
            if (isMarquee) {
                helperNote.textContent = '这里可以调整公告标签样式，下面的公告内容会同步更新滚动条里的两段文案。';
            } else if (isAdSection) {
                helperNote.textContent = '广告区在这里按三段修改每条广告词，下面颜色仍然控制这张主卡片标题栏。';
            } else {
                helperNote.textContent = '这里可以直接调整主卡片标题文字颜色、背景颜色和标题样式。';
            }
        }

        if (helperNote && !isMarquee) {
            helperNote.textContent = isAdSection
                ? '\u8fd9\u91cc\u53ef\u4ee5\u628a\u5e7f\u544a\u8bcd\u6309\u524d\u6bb5\u3001\u4e2d\u6bb5\u3001\u540e\u6bb5\u5206\u5f00\u7f16\u8f91\uff0c\u4f8b\u5982\u201c\u6fb3\u95e8\u591a\u5b9d\u201d\u201c\u3010\u4e09\u8096\u4e8c\u8fde\u3011\u201d\u201c\u5f3a\u529b\u63a8\u8350\u201d\u3002'
                : '\u8fd9\u91cc\u53ef\u4ee5\u76f4\u63a5\u8c03\u6574\u4e3b\u5361\u7247\u6807\u9898\u6587\u5b57\u989c\u8272\u3001\u80cc\u666f\u989c\u8272\u548c\u6807\u9898\u6837\u5f0f\u3002';
        }

        if (helperNote && !isMarquee) {
            helperNote.textContent = isAdSection
                ? '\u8fd9\u91cc\u53ef\u4ee5\u628a\u5e7f\u544a\u8bcd\u6309\u524d\u6bb5\u3001\u4e2d\u6bb5\u3001\u540e\u6bb5\u5206\u5f00\u7f16\u8f91\uff0c\u4f8b\u5982\u201c\u6fb3\u95e8\u591a\u5b9d\u201d\u201c\u3010\u4e09\u8096\u4e8c\u8fde\u3011\u201d\u201c\u5f3a\u529b\u63a8\u8350\u201d\u3002'
                : '\u8fd9\u91cc\u53ef\u4ee5\u76f4\u63a5\u8c03\u6574\u4e3b\u5361\u7247\u6807\u9898\u6587\u5b57\u989c\u8272\u3001\u80cc\u666f\u989c\u8272\u548c\u6807\u9898\u6837\u5f0f\u3002';
        }

        updateSectionEditorTitlePreview();
        updateSectionEditorTitlePreview();
        setBodyFieldsDisabled(!nodes.bodyEl);
        updateSectionEditorTitlePreview();
    };

    var openSectionEditor = function (editor, titleEl) {
        var nodes = getSectionNodesFromTitle(titleEl);

        if (!sectionEditorModal || !sectionEditorForm || !nodes) {
            return;
        }

        clearActiveSection();

        activeSectionState.editor = editor;
        activeSectionState.sectionEl = nodes.sectionEl;
        activeSectionState.titleEl = nodes.titleEl;
        activeSectionState.bodyEl = nodes.bodyEl;

        if (nodes.sectionEl) {
            nodes.sectionEl.setAttribute('data-section-editor-active', '1');
        }

        nodes.titleEl.setAttribute('data-section-editor-active', '1');

        if (nodes.bodyEl) {
            nodes.bodyEl.setAttribute('data-section-editor-active', '1');
        }

        populateSectionEditor(nodes);
        setSectionEditorOpen(true);
    };

    var closeSectionEditor = function () {
        clearActiveSection();
        activeSectionState.editor = null;
        activeSectionState.sectionEl = null;
        activeSectionState.titleEl = null;
        activeSectionState.bodyEl = null;
        setSectionEditorOpen(false);
    };

    var populateSectionEditor = function (nodes) {
        var isMarquee = false;
        var styleWindow = null;
        var titleStyles = null;
        var textTargetStyles = null;
        var gradientColors = null;
        var helperNote = null;
        var adItems = [];
        var isAdSection = false;
        var isExpertSection = false;
        var isHeaderSection = false;

        if (!nodes || !nodes.blockEl) {
            return;
        }

        isMarquee = nodes.blockType === 'marquee';
        isHeaderSection = isSectionEditorHeaderBlock(nodes.blockEl);
        styleWindow = nodes.blockEl.ownerDocument && nodes.blockEl.ownerDocument.defaultView ? nodes.blockEl.ownerDocument.defaultView : window;
        titleStyles = nodes.titleEl ? styleWindow.getComputedStyle(nodes.titleEl) : null;
        textTargetStyles = nodes.textTargetEl ? styleWindow.getComputedStyle(nodes.textTargetEl) : null;
        gradientColors = titleStyles ? getStoredTitleBackgroundColors(nodes.titleEl, titleStyles.backgroundImage, titleStyles.backgroundColor) : null;
        adItems = getSectionAdItems(nodes);
        isAdSection = adItems.length > 0;
        isExpertSection = isExpertSectionNodes(nodes);

        if (sectionEditorTarget) {
            sectionEditorTarget.textContent = getSectionLabel(nodes);
        }

        if (sectionEditorEditLock) {
            sectionEditorEditLock.checked = isSectionEditLocked(nodes.blockEl);
        }

        if (sectionEditorTitlePanelTitle) {
            sectionEditorTitlePanelTitle.textContent = isAdSection
                ? '\u5e7f\u544a\u8bcd\u8bbe\u7f6e'
                : '\u6807\u9898\u8bbe\u7f6e';
            sectionEditorTitlePanelTitle.hidden = isAdSection;
        }

        if (sectionEditorAdCopyPanel) {
            sectionEditorAdCopyPanel.hidden = !isAdSection;
        }

        if (sectionEditorAdBorderGrid) {
            sectionEditorAdBorderGrid.hidden = !isAdSection;
        }

        if (sectionEditorExpertAdPanel) {
            sectionEditorExpertAdPanel.hidden = !isExpertSection;
        }

        setSectionEditorFieldVisibility(sectionEditorTitleHtml, false);
        setSectionEditorFieldVisibility(sectionEditorTitleIcon, !!nodes.titleEl);
        setSectionEditorFieldVisibility(sectionEditorTitleText, !!nodes.titleEl);
        setSectionEditorFieldVisibility(sectionEditorTitleStyle, !isAdSection);
        setSectionEditorFieldVisibility(sectionEditorBodyHtml, !!nodes.bodyEl && isHeaderSection);

        if (isAdSection) {
            populateSectionEditorAdCopyFields(adItems);
            writeSectionEditorAdItemBorderColor(readSectionAdItemBorderColor(adItems));
        } else {
            ensureSectionEditorAdCopyFields(0);
            writeSectionEditorAdItemBorderColor('#d9e2ef');
        }

        if (isExpertSection) {
            writeSectionEditorExpertAdFields(nodes.blockEl);
        } else {
            writeSectionEditorExpertAdFields(null);
        }

        if (sectionEditorTitlePanel) {
            if (sectionEditorTitlePanel.classList) {
                sectionEditorTitlePanel.classList.toggle('is-ad-copy-mode', !!isAdSection);
            }
            sectionEditorTitlePanel.hidden = !nodes.titleEl;
        }

        if (sectionEditorCardPanel) {
            sectionEditorCardPanel.hidden = !isHeaderSection;
        }

        if (sectionEditorMarqueeField) {
            sectionEditorMarqueeField.hidden = !isMarquee;
        }

        if (sectionEditorSectionClass) {
            sectionEditorSectionClass.value = nodes.sectionEl ? nodes.sectionEl.className : '';
        }

        if (sectionEditorSectionStyle) {
            sectionEditorSectionStyle.value = nodes.sectionEl ? (nodes.sectionEl.getAttribute('style') || '') : '';
        }

        if (sectionEditorTitleHtml) {
            sectionEditorTitleHtml.value = nodes.titleEl ? getCleanNodeHtml(nodes.titleEl) : '';
        }

        if (sectionEditorTitleIcon) {
            sectionEditorTitleIcon.value = nodes.titleEl ? extractSectionTitleIconValue(nodes.titleEl) : '';
        }

        if (sectionEditorTitleText) {
            sectionEditorTitleText.value = nodes.titleEl ? extractSectionTitleText(nodes.titleEl) : '';
        }

        if (sectionEditorTitleClass) {
            sectionEditorTitleClass.value = nodes.titleEl ? (nodes.titleEl.className || 'section-title') : 'section-title';
        }

        if (sectionEditorTitleStyle) {
            sectionEditorTitleStyle.value = nodes.titleEl ? (nodes.titleEl.getAttribute('style') || '') : '';
        }

        if (sectionEditorTitleStart) {
            sectionEditorTitleStart.value = gradientColors ? gradientColors.start : '#1e40af';
        }

        if (sectionEditorTitleEnd) {
            sectionEditorTitleEnd.value = gradientColors ? gradientColors.end : '#3b82f6';
        }

        if (sectionEditorTitleColor) {
            sectionEditorTitleColor.value = titleStyles ? (colorToHex(titleStyles.color) || '#ffffff') : '#ffffff';
        }

        if (sectionEditorTitleAlign) {
            sectionEditorTitleAlign.value = titleStyles ? resolveTitleAlign(nodes.titleEl, titleStyles) : 'left';
        }

        if (sectionEditorMarqueeText) {
            sectionEditorMarqueeText.value = isMarquee ? getMarqueeText(nodes.blockEl) : '';
        }

        if (sectionEditorBodyClass) {
            sectionEditorBodyClass.value = nodes.bodyEl ? (nodes.bodyEl.className || '') : '';
        }

        if (sectionEditorBodyStyle) {
            sectionEditorBodyStyle.value = nodes.bodyEl ? (nodes.bodyEl.getAttribute('style') || '') : '';
        }

        if (sectionEditorBodyBackground) {
            sectionEditorBodyBackground.value = textTargetStyles ? (colorToHex(textTargetStyles.backgroundColor) || '#ffffff') : '#ffffff';
        }

        if (sectionEditorBodyBorder) {
            sectionEditorBodyBorder.value = textTargetStyles ? (colorToHex(textTargetStyles.borderTopColor) || '#d9e2ef') : '#d9e2ef';
        }

        if (sectionEditorBodyHtml) {
            sectionEditorBodyHtml.value = nodes.bodyEl ? getCleanNodeHtml(nodes.bodyEl) : '';
        }

        if (sectionEditorCardStyle) {
            sectionEditorCardStyle.value = nodes.styleTargetEl ? (nodes.styleTargetEl.getAttribute('style') || '') : '';
        }

        refreshSectionEditorHeaderBackgroundControls();

        if (sectionEditorCardTextStyleFields) {
            sectionEditorCardTextStyleFields.hidden = isSectionEditorHeaderBlock(nodes.blockEl);
        }

        if (sectionEditorCardFontSize) {
            sectionEditorCardFontSize.value = nodes.textTargetEl
                ? ((nodes.textTargetEl.style && nodes.textTargetEl.style.fontSize) ? nodes.textTargetEl.style.fontSize : (textTargetStyles ? textTargetStyles.fontSize : ''))
                : '';
        }

        if (sectionEditorCardTextColor) {
            sectionEditorCardTextColor.value = textTargetStyles ? (colorToHex(textTargetStyles.color) || '#0f172a') : '#0f172a';
        }

        helperNote = sectionEditorModal ? sectionEditorModal.querySelector('[data-section-editor-color-help]') : null;
        if (helperNote) {
            helperNote.hidden = !!isAdSection;
            if (isMarquee) {
                helperNote.textContent = '这里可以调整公告标签样式，下面的公告内容会同步更新滚动条里的两段文案。';
            } else if (isAdSection) {
                helperNote.textContent = '这里可以把广告词按前段、中段、后段分开编辑，例如“澳门多宝”“【三肖二连】”“强力推荐”。';
            } else if (isExpertSection) {
                helperNote.textContent = '这里可以编辑高手榜标题，并按插入间隔维护广告位置；广告词支持前段、中段、后段和广告连接。';
            } else {
                helperNote.textContent = '这里可以直接编辑主卡片标题文案、标题文字颜色、背景颜色和标题样式。';
            }
        }

        setBodyFieldsDisabled(!nodes.bodyEl);
        updateSectionEditorTitlePreview();
    };

    var openSectionEditor = function (editor, blockOrTitleEl) {
        var nodes = null;

        if (blockOrTitleEl && blockOrTitleEl.classList && blockOrTitleEl.classList.contains('section-title')) {
            nodes = getSectionNodesFromTitle(blockOrTitleEl);
        } else {
            nodes = getSectionNodesFromBlock(blockOrTitleEl);
        }

        if (!sectionEditorModal || !sectionEditorForm || !nodes) {
            return;
        }

        clearActiveSection();

        activeSectionState.editor = editor;
        activeSectionState.blockEl = nodes.blockEl;
        activeSectionState.sectionEl = nodes.sectionEl;
        activeSectionState.titleEl = nodes.titleEl;
        activeSectionState.bodyEl = nodes.bodyEl;
        activeSectionState.styleTargetEl = nodes.styleTargetEl;
        activeSectionState.textTargetEl = nodes.textTargetEl;
        activeSectionState.blockType = nodes.blockType || 'section';

        if (nodes.blockEl) {
            nodes.blockEl.setAttribute('data-section-editor-active', '1');
        }

        if (nodes.sectionEl) {
            nodes.sectionEl.setAttribute('data-section-editor-active', '1');
        }

        if (nodes.titleEl) {
            nodes.titleEl.setAttribute('data-section-editor-active', '1');
        }

        if (nodes.bodyEl) {
            nodes.bodyEl.setAttribute('data-section-editor-active', '1');
        }

        populateSectionEditor(nodes);
        setSectionEditorOpen(true);
    };

    var closeSectionEditor = function () {
        clearActiveSection();
        activeSectionState.editor = null;
        activeSectionState.blockEl = null;
        activeSectionState.sectionEl = null;
        activeSectionState.titleEl = null;
        activeSectionState.bodyEl = null;
        activeSectionState.styleTargetEl = null;
        activeSectionState.textTargetEl = null;
        activeSectionState.blockType = '';
        setSectionEditorOpen(false);
    };

    var isLiveDrawBlock = function (element) {
        return !!(
            element
            && element.id === 'section-live'
            && element.classList
            && element.classList.contains('hero-live-box')
        );
    };

    var isSortableBlock = function (element) {
        if (!element || !element.tagName) {
            return false;
        }

        if (element.tagName === 'SECTION') {
            return true;
        }

        if (isLiveDrawBlock(element)) {
            return true;
        }

        if (isComponentFloatBlock(element)) {
            return true;
        }

        return !!(element.classList && element.classList.contains('marquee'));
    };

    var getSortableBlocks = function (root, excludeBlock) {
        var children = root ? root.children : [];
        var sortable = [];
        var index = 0;

        for (index = 0; index < children.length; index += 1) {
            if (!isSortableBlock(children[index])) {
                continue;
            }

            if (excludeBlock && children[index] === excludeBlock) {
                continue;
            }

            sortable.push(children[index]);
        }

        return sortable;
    };

    var isSectionEditLocked = function (blockEl) {
        return !!(
            blockEl
            && blockEl.getAttribute
            && blockEl.getAttribute('data-section-edit-locked') === '1'
        );
    };

    var applySectionEditLockState = function (blockEl, locked) {
        if (!blockEl || !blockEl.setAttribute) {
            return;
        }

        if (locked) {
            blockEl.setAttribute('data-section-edit-locked', '1');
            blockEl.setAttribute('contenteditable', 'false');
            return;
        }

        blockEl.removeAttribute('data-section-edit-locked');
        blockEl.removeAttribute('contenteditable');
    };

    var refreshSectionEditLockStates = function (root) {
        var blocks = getSortableBlocks(root);
        var index = 0;

        for (index = 0; index < blocks.length; index += 1) {
            if (isSectionEditLocked(blocks[index])) {
                blocks[index].setAttribute('contenteditable', 'false');
            } else if (blocks[index].getAttribute && blocks[index].getAttribute('contenteditable') === 'false') {
                blocks[index].removeAttribute('contenteditable');
            }
        }
    };

    var getSectionEditorBlockId = function (blockEl) {
        var blockId = '';

        if (!blockEl) {
            return '';
        }

        blockId = blockEl.getAttribute ? (blockEl.getAttribute('data-section-editor-block-key') || '') : '';
        if (blockId !== '') {
            blockEl._sectionEditorBlockId = blockId;
            return blockId;
        }

        if (!blockEl._sectionEditorBlockId) {
            sectionEditorBlockIdSeed += 1;
            blockEl._sectionEditorBlockId = 'section-editor-block-' + sectionEditorBlockIdSeed;
        }

        if (blockEl.setAttribute) {
            blockEl.setAttribute('data-section-editor-block-key', blockEl._sectionEditorBlockId);
        }

        return blockEl._sectionEditorBlockId;
    };

    var getSectionEditorStructureSignature = function (body) {
        var blocks = getSortableBlocks(body);

        return blocks.map(function (blockEl) {
            var kind = isLiveDrawBlock(blockEl)
                ? 'live'
                : (blockEl.classList && blockEl.classList.contains('marquee') ? 'marquee' : 'section');
            var hasTitle = getDirectSectionTitle(blockEl) ? 'title' : 'body';

            return getSectionEditorBlockId(blockEl) + ':' + kind + ':' + hasTitle;
        }).join('|');
    };

    var getSortableBlocksWithDirectTitles = function (root) {
        return getSortableBlocks(root).map(function (blockEl) {
            return {
                blockEl: blockEl,
                titleEl: getDirectSectionTitle(blockEl)
            };
        }).filter(function (item) {
            return !!item.titleEl;
        });
    };

    var resetSectionDragState = function () {
        var sectionEl = sectionDragState.sectionEl;
        var placeholderEl = sectionDragState.placeholderEl;

        if (placeholderEl && placeholderEl.parentNode) {
            placeholderEl.parentNode.removeChild(placeholderEl);
        }

        if (sectionEl) {
            sectionEl.classList.remove('editor-section-dragging');
            sectionEl.style.pointerEvents = '';
        }

        if (sectionDragState.body) {
            sectionDragState.body.classList.remove('editor-section-sort-mode');
        }

        if (sectionDragState.doc) {
            sectionDragState.doc.removeEventListener('mousemove', handleSectionDragMove, true);
            sectionDragState.doc.removeEventListener('mouseup', handleSectionDragEnd, true);
        }

        if (sectionDragState.win && sectionDragState.rafId) {
            sectionDragState.win.cancelAnimationFrame(sectionDragState.rafId);
        }

        sectionDragState.editor = null;
        sectionDragState.doc = null;
        sectionDragState.body = null;
        sectionDragState.win = null;
        sectionDragState.sectionEl = null;
        sectionDragState.titleEl = null;
        sectionDragState.placeholderEl = null;
        sectionDragState.moved = false;
        sectionDragState.isActive = false;
        sectionDragState.startX = 0;
        sectionDragState.startY = 0;
        sectionDragState.pendingClientX = 0;
        sectionDragState.pendingClientY = 0;
        sectionDragState.rafId = 0;
    };

    var getSectionDropTarget = function (clientY) {
        var sortableSections = getSortableBlocks(sectionDragState.body, sectionDragState.sectionEl);
        var lastSection = null;
        var rect = null;
        var midpoint = 0;
        var index = 0;

        if (!sectionDragState.doc || !sortableSections.length) {
            return null;
        }

        for (index = 0; index < sortableSections.length; index += 1) {
            rect = sortableSections[index].getBoundingClientRect();
            midpoint = rect.top + (rect.height / 2);

            if (clientY <= midpoint) {
                return {
                    parentNode: sortableSections[index].parentNode,
                    referenceNode: sortableSections[index]
                };
            }
        }

        lastSection = sortableSections[sortableSections.length - 1];
        if (lastSection) {
            return {
                parentNode: lastSection.parentNode,
                referenceNode: lastSection.nextSibling
            };
        }

        return null;
    };

    var updateSectionDropIndicator = function (clientY) {
        var dropTarget = getSectionDropTarget(clientY);
        var placeholderEl = sectionDragState.placeholderEl;

        if (!dropTarget || !placeholderEl || !dropTarget.parentNode) {
            return;
        }

        if (placeholderEl.parentNode !== dropTarget.parentNode || placeholderEl.nextSibling !== dropTarget.referenceNode) {
            dropTarget.parentNode.insertBefore(placeholderEl, dropTarget.referenceNode);
        }
    };

    var activateSectionDrag = function () {
        var sectionEl = sectionDragState.sectionEl;
        var placeholderEl = sectionDragState.placeholderEl;

        if (!sectionEl || !placeholderEl || sectionDragState.isActive) {
            return;
        }

        sectionDragState.isActive = true;
        sectionEl.classList.add('editor-section-dragging');
        sectionEl.style.pointerEvents = 'none';
        sectionDragState.body.classList.add('editor-section-sort-mode');
        placeholderEl.style.height = Math.max(sectionEl.offsetHeight, 36) + 'px';
        sectionEl.parentNode.insertBefore(placeholderEl, sectionEl.nextSibling);
    };

    var autoScrollSectionDragViewport = function (clientY) {
        var viewportHeight = 0;

        if (!sectionDragState.win || !sectionDragState.doc) {
            return;
        }

        viewportHeight = sectionDragState.doc.documentElement ? sectionDragState.doc.documentElement.clientHeight : 0;

        if (!viewportHeight) {
            return;
        }

        if (clientY < 90) {
            sectionDragState.win.scrollBy(0, -18);
        } else if (clientY > viewportHeight - 90) {
            sectionDragState.win.scrollBy(0, 18);
        }
    };

    function handleSectionDragMove(event) {
        var deltaX = 0;
        var deltaY = 0;

        if (!sectionDragState.sectionEl || !sectionDragState.win) {
            return;
        }

        event.preventDefault();
        deltaX = Math.abs(event.clientX - sectionDragState.startX);
        deltaY = Math.abs(event.clientY - sectionDragState.startY);

        if (!sectionDragState.isActive && Math.max(deltaX, deltaY) < 6) {
            return;
        }

        if (!sectionDragState.isActive) {
            activateSectionDrag();
        }

        sectionDragState.moved = true;
        sectionDragState.pendingClientX = event.clientX;
        sectionDragState.pendingClientY = event.clientY;

        if (sectionDragState.rafId) {
            return;
        }

        sectionDragState.rafId = sectionDragState.win.requestAnimationFrame(function () {
            sectionDragState.rafId = 0;

            if (!sectionDragState.sectionEl || !sectionDragState.isActive) {
                return;
            }

            autoScrollSectionDragViewport(sectionDragState.pendingClientY);
            updateSectionDropIndicator(sectionDragState.pendingClientY);
        });
    }

    function handleSectionDragEnd(event) {
        var moved = sectionDragState.moved;
        var wasActive = sectionDragState.isActive;
        var sectionEl = sectionDragState.sectionEl;
        var placeholderEl = sectionDragState.placeholderEl;

        if (!sectionEl) {
            return;
        }

        if (event) {
            event.preventDefault();
        }

        if (moved && wasActive && placeholderEl && placeholderEl.parentNode) {
            placeholderEl.parentNode.insertBefore(sectionEl, placeholderEl);
        }

        resetSectionDragState();

        if (moved && wasActive) {
            syncEditorTextarea();
        } else {
            ensureSectionEditorButtons(window.tinymce.get('draw-material-editor'));
        }
    }

    var startSectionDrag = function (editor, blockEl, titleEl, event) {
        var placeholderEl = null;

        if (!blockEl || !editor || (typeof event.button === 'number' && event.button !== 0)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        closeSectionEditor();
        resetSectionDragState();

        placeholderEl = editor.getDoc().createElement('div');
        placeholderEl.className = 'editor-section-drop-indicator';
        placeholderEl.setAttribute('contenteditable', 'false');
        placeholderEl.setAttribute('data-mce-bogus', '1');

        sectionDragState.editor = editor;
        sectionDragState.doc = editor.getDoc();
        sectionDragState.body = editor.getBody();
        sectionDragState.win = editor.getDoc() ? editor.getDoc().defaultView : null;
        sectionDragState.sectionEl = blockEl;
        sectionDragState.titleEl = titleEl || null;
        sectionDragState.placeholderEl = placeholderEl;
        sectionDragState.moved = false;

        sectionDragState.startX = event.clientX;
        sectionDragState.startY = event.clientY;
        sectionDragState.pendingClientX = event.clientX;
        sectionDragState.pendingClientY = event.clientY;

        sectionDragState.doc.addEventListener('mousemove', handleSectionDragMove, true);
        sectionDragState.doc.addEventListener('mouseup', handleSectionDragEnd, true);
    };

    var consumeSectionControlEvent = function (event) {
        if (!event) {
            return;
        }

        if (typeof event.preventDefault === 'function') {
            event.preventDefault();
        }

        if (typeof event.stopPropagation === 'function') {
            event.stopPropagation();
        }
    };

    var shouldIgnoreSectionPointerButton = function (event) {
        return !!(event && typeof event.button === 'number' && event.button !== 0);
    };

    var runSectionControlOnce = function (button, event, handler) {
        var now = Date.now ? Date.now() : new Date().getTime();
        var lastRunAt = button ? (button._sectionEditorLastRunAt || 0) : 0;

        if (!button || typeof handler !== 'function' || shouldIgnoreSectionPointerButton(event)) {
            return;
        }

        consumeSectionControlEvent(event);

        if (now - lastRunAt < 180) {
            return;
        }

        button._sectionEditorLastRunAt = now;
        handler(event);
    };

    var bindSectionActionButton = function (button, handler) {
        if (!button || typeof handler !== 'function') {
            return;
        }

        ['pointerdown', 'mousedown', 'touchstart'].forEach(function (eventName) {
            button.addEventListener(eventName, function (event) {
                runSectionControlOnce(button, event, handler);
            }, false);
        });

        button.addEventListener('click', function (event) {
            consumeSectionControlEvent(event);
        }, false);
    };

    var bindSectionDragButton = function (button, editor, blockEl, titleEl) {
        if (!button || !editor || !blockEl) {
            return;
        }

        button.addEventListener('mousedown', function (event) {
            runSectionControlOnce(button, event, function (currentEvent) {
                startSectionDrag(editor, blockEl, titleEl || null, currentEvent);
            });
        }, false);

        button.addEventListener('pointerdown', function (event) {
            if (event && typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }
        }, false);

        button.addEventListener('click', function (event) {
            consumeSectionControlEvent(event);
        }, false);
    };

    var removeSectionEditorControls = function (root, options) {
        var controls = root ? root.querySelectorAll('[data-section-editor-control]') : [];
        var index = 0;
        var keepSourcePanel = !!(options && options.keepSourcePanel);
        var current = null;

        for (index = 0; index < controls.length; index += 1) {
            current = controls[index];

            if (!current || !current.parentNode) {
                continue;
            }

            if (keepSourcePanel && current.closest && current.closest('[data-section-editor-control="source-panel"]')) {
                continue;
            }

            current.parentNode.removeChild(current);
        }

        if (!keepSourcePanel) {
            resetSectionSourceState();
        }

        if (root) {
            root._sectionEditorControlsSignature = '';
        }
    };

    var stripManagedSectionCardStyleProperties = function (element) {
        if (!element || !element.style) {
            return;
        }

        element.style.removeProperty('border-color');
        element.style.removeProperty('border-top-color');
        element.style.removeProperty('border-right-color');
        element.style.removeProperty('border-bottom-color');
        element.style.removeProperty('border-left-color');
        element.style.removeProperty('box-shadow');

        if (!(element.getAttribute('style') || '').trim()) {
            element.removeAttribute('style');
        }
    };

    var normalizeManagedSectionCardStyles = function (root) {
        var sections = [];
        var index = 0;
        var titleEl = null;
        var bodyEl = null;

        if (!root) {
            return;
        }

        sections = root.querySelectorAll ? root.querySelectorAll('section') : [];

        for (index = 0; index < sections.length; index += 1) {
            titleEl = getDirectSectionTitle(sections[index]);
            bodyEl = titleEl ? titleEl.nextElementSibling : null;

            if (!bodyEl || !bodyEl.classList) {
                continue;
            }

            if (bodyEl.classList.contains('data-frame') || bodyEl.classList.contains('grid')) {
                stripManagedSectionCardStyleProperties(bodyEl);
            }
        }
    };

    var syncEditorTextarea = function (options) {
        var editor = null;
        var body = null;
        var bodyClone = null;
        var keepSourcePanel = !!(options && options.keepSourcePanel);
        var previewNow = new Date();

        if (typeof window.tinymce === 'undefined') {
            return;
        }

        editor = window.tinymce.get('draw-material-editor');
        if (!editor) {
            return;
        }

        body = editor.getBody();
        if (!body) {
            return;
        }

        applyAdminLivePreview(body, previewNow);
        applyManagedTitleBackgrounds(body);
        applyAdItemMiddleColorPreview(body);
        applyAdItemTailTextPreview(body);
        normalizeManagedSectionCardStyles(body);
        clearActiveSection();

        if (!keepSourcePanel && sectionSourceState.panelEl && sectionSourceState.panelEl.parentNode) {
            sectionSourceState.panelEl.parentNode.removeChild(sectionSourceState.panelEl);
            resetSectionSourceState();
        }

        bodyClone = body.cloneNode(true);
        cleanEditorClone(bodyClone);
        stripAdItemMiddleRandomPreview(bodyClone);
        stripAdItemTailRandomPreview(bodyClone);
        applyAdminLivePreview(bodyClone, previewNow);
        stripAdminIssuePrefixPreview(bodyClone);
        normalizeManagedSectionCardStyles(bodyClone);
        textarea.value = bodyClone.innerHTML;
        ensureSectionEditorButtons(editor, {
            keepSourcePanel: keepSourcePanel
        });
    };

    var scheduleSectionEditorRefresh = function (editor) {
        if (!editor) {
            return;
        }

        window.clearTimeout(editor._sectionEditorRefreshTimer);
        editor._sectionEditorRefreshTimer = window.setTimeout(function () {
            ensureSectionEditorButtons(editor, {
                keepSourcePanel: !!(sectionSourceState.panelEl && sectionSourceState.panelEl.parentNode && sectionSourceState.editor === editor)
            });

            if (
                sectionSourceState.panelEl &&
                sectionSourceState.panelEl.parentNode &&
                sectionSourceState.editor === editor &&
                sectionSourceState.textareaEl &&
                sectionSourceState.blockEl &&
                !sectionSourceState.isSyncingFromSource &&
                (!sectionSourceState.textareaEl.ownerDocument || sectionSourceState.textareaEl.ownerDocument.activeElement !== sectionSourceState.textareaEl)
            ) {
                sectionSourceState.textareaEl.value = getCleanBlockHtml(sectionSourceState.blockEl);
            }
        }, 220);
    };

    var adminPreviewLunarInfo = [
        0x04bd8, 0x04ae0, 0x0a570, 0x054d5, 0x0d260, 0x0d950, 0x16554, 0x056a0, 0x09ad0, 0x055d2,
        0x04ae0, 0x0a5b6, 0x0a4d0, 0x0d250, 0x1d255, 0x0b540, 0x0d6a0, 0x0ada2, 0x095b0, 0x14977,
        0x04970, 0x0a4b0, 0x0b4b5, 0x06a50, 0x06d40, 0x1ab54, 0x02b60, 0x09570, 0x052f2, 0x04970,
        0x06566, 0x0d4a0, 0x0ea50, 0x06e95, 0x05ad0, 0x02b60, 0x186e3, 0x092e0, 0x1c8d7, 0x0c950,
        0x0d4a0, 0x1d8a6, 0x0b550, 0x056a0, 0x1a5b4, 0x025d0, 0x092d0, 0x0d2b2, 0x0a950, 0x0b557,
        0x06ca0, 0x0b550, 0x15355, 0x04da0, 0x0a5d0, 0x14573, 0x052d0, 0x0a9a8, 0x0e950, 0x06aa0,
        0x0aea6, 0x0ab50, 0x04b60, 0x0aae4, 0x0a570, 0x05260, 0x0f263, 0x0d950, 0x05b57, 0x056a0,
        0x096d0, 0x04dd5, 0x04ad0, 0x0a4d0, 0x0d4d4, 0x0d250, 0x0d558, 0x0b540, 0x0b5a0, 0x195a6,
        0x095b0, 0x049b0, 0x0a974, 0x0a4b0, 0x0b27a, 0x06a50, 0x06d40, 0x0af46, 0x0ab60, 0x09570,
        0x04af5, 0x04970, 0x064b0, 0x074a3, 0x0ea50, 0x06b58, 0x05ac0, 0x0ab60, 0x096d5, 0x092e0,
        0x0c960, 0x0d954, 0x0d4a0, 0x0da50, 0x07552, 0x056a0, 0x0abb7, 0x025d0, 0x092d0, 0x0cab5,
        0x0a950, 0x0b4a0, 0x0baa4, 0x0ad50, 0x055d9, 0x04ba0, 0x0a5b0, 0x15176, 0x052b0, 0x0a930,
        0x07954, 0x06aa0, 0x0ad50, 0x05b52, 0x04b60, 0x0a6e6, 0x0a4e0, 0x0d260, 0x0ea65, 0x0d530,
        0x05aa0, 0x076a3, 0x096d0, 0x04bd7, 0x04ad0, 0x0a4d0, 0x1d0b6, 0x0d250, 0x0d520, 0x0dd45,
        0x0b5a0, 0x056d0, 0x055b2, 0x049b0, 0x0a577, 0x0a4b0, 0x0aa50, 0x1b255, 0x06d20, 0x0ada0
    ];

    var formatAdminPreviewLunarDayText = function (dayNumber) {
        var day = Number(dayNumber || 0);
        var units = ['一', '二', '三', '四', '五', '六', '七', '八', '九', '十'];

        if (day <= 0 || day > 30) {
            return '';
        }

        if (day <= 10) {
            return '初' + units[day - 1];
        }

        if (day < 20) {
            return '十' + units[day - 11];
        }

        if (day === 20) {
            return '二十';
        }

        if (day < 30) {
            return '廿' + units[day - 21];
        }

        return '三十';
    };

    var formatAdminPreviewLunarMonthText = function (monthNumber, isLeapMonth) {
        var monthNames = ['正', '二', '三', '四', '五', '六', '七', '八', '九', '十', '冬', '腊'];
        var month = Number(monthNumber || 0);

        if (month <= 0 || month > 12) {
            return '';
        }

        return (isLeapMonth ? '闰' : '') + monthNames[month - 1] + '月';
    };

    var getAdminPreviewLeapMonth = function (year) {
        return adminPreviewLunarInfo[year - 1900] & 0xf;
    };

    var getAdminPreviewLeapDays = function (year) {
        if (!getAdminPreviewLeapMonth(year)) {
            return 0;
        }

        return (adminPreviewLunarInfo[year - 1900] & 0x10000) ? 30 : 29;
    };

    var getAdminPreviewMonthDays = function (year, month) {
        return (adminPreviewLunarInfo[year - 1900] & (0x10000 >> month)) ? 30 : 29;
    };

    var getAdminPreviewLunarYearDays = function (year) {
        var sum = 348;
        var mask = 0;

        for (mask = 0x8000; mask > 0x8; mask >>= 1) {
            sum += (adminPreviewLunarInfo[year - 1900] & mask) ? 1 : 0;
        }

        return sum + getAdminPreviewLeapDays(year);
    };

    var convertAdminPreviewSolarToLunar = function (currentDate) {
        var baseDate = Date.UTC(1900, 0, 31);
        var targetDate = Date.UTC(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate());
        var offset = Math.floor((targetDate - baseDate) / 86400000);
        var year = 0;
        var month = 0;
        var temp = 0;
        var leap = 0;
        var isLeap = false;

        if (offset < 0) {
            return null;
        }

        for (year = 1900; year < 2050 && offset > 0; year += 1) {
            temp = getAdminPreviewLunarYearDays(year);
            offset -= temp;
        }

        if (offset < 0) {
            offset += temp;
            year -= 1;
        }

        leap = getAdminPreviewLeapMonth(year);

        for (month = 1; month < 13 && offset > 0; month += 1) {
            if (leap > 0 && month === (leap + 1) && !isLeap) {
                month -= 1;
                isLeap = true;
                temp = getAdminPreviewLeapDays(year);
            } else {
                temp = getAdminPreviewMonthDays(year, month);
            }

            if (isLeap && month === (leap + 1)) {
                isLeap = false;
            }

            offset -= temp;
        }

        if (offset === 0 && leap > 0 && month === (leap + 1)) {
            if (isLeap) {
                isLeap = false;
            } else {
                isLeap = true;
                month -= 1;
            }
        }

        if (offset < 0) {
            offset += temp;
            month -= 1;
        }

        return {
            year: year,
            month: month,
            day: offset + 1,
            isLeap: isLeap
        };
    };

    var formatAdminPreviewLunarDateText = function (currentDate) {
        var lunarDate = convertAdminPreviewSolarToLunar(currentDate);
        var monthText = '';
        var dayText = '';

        if (!lunarDate) {
            return '农历日期';
        }

        monthText = formatAdminPreviewLunarMonthText(lunarDate.month, lunarDate.isLeap);
        dayText = formatAdminPreviewLunarDayText(lunarDate.day);

        if (!monthText || !dayText) {
            return '农历日期';
        }

        return monthText + dayText;
    };

    var moduloAdminPreview = function (value, divisor) {
        return ((value % divisor) + divisor) % divisor;
    };

    var setAdminPreviewText = function (root, id, value) {
        var node = root ? root.querySelector('#' + id) : null;

        if (!node) {
            return;
        }

        node.textContent = value;
    };

    var padAdminPreviewNumber = function (value) {
        var number = Number(value || 0);

        return number < 10 ? '0' + number : String(number);
    };

    var normalizeAdminPreviewIssueTail = function (issueNo) {
        var text = String(issueNo || '').trim();

        if (!/^\d+$/.test(text)) {
            return '--';
        }

        text = text.length > 3 ? text.slice(-3) : text;
        while (text.length < 3) {
            text = '0' + text;
        }

        return text;
    };

    var findAdminPreviewGroupName = function (groups, value, fallback) {
        var normalizedValue = Number(value || 0);
        var groupName = '';
        var groupValues = [];
        var index = 0;

        for (groupName in groups) {
            if (!Object.prototype.hasOwnProperty.call(groups, groupName)) {
                continue;
            }

            groupValues = Array.isArray(groups[groupName]) ? groups[groupName] : [];
            for (index = 0; index < groupValues.length; index += 1) {
                if (Number(groupValues[index] || 0) === normalizedValue) {
                    return groupName;
                }
            }
        }

        return fallback;
    };

    var getAdminPreviewWaveColorClass = function (value) {
        var number = Number(value || 0);

        if (adminPreviewWaveRed.indexOf(number) !== -1) {
            return 'red';
        }

        if (adminPreviewWaveBlue.indexOf(number) !== -1) {
            return 'blue';
        }

        return 'green';
    };

    var sumAdminPreviewDrawOddEven = function (value) {
        var digits = String(Math.abs(Number(value || 0))).split('');
        var sum = 0;

        digits.forEach(function (digit) {
            sum += Number(digit || 0);
        });

        return sum % 2 === 0 ? '合数双' : '合数单';
    };

    var resolveAdminPreviewDayBranch = function (currentDate) {
        var currentUtcDate = Date.UTC(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate());
        var diffDays = Math.round((currentUtcDate - adminPreviewDayBranchAnchorUtc) / 86400000);

        return adminPreviewEarthlyBranches[moduloAdminPreview(adminPreviewDayBranchAnchorIndex + diffDays, adminPreviewEarthlyBranches.length)];
    };

    var resolveAdminPreviewLunarConflictMeta = function (currentDate) {
        var dayBranch = resolveAdminPreviewDayBranch(currentDate);
        var chongBranch = adminPreviewEarthlyBranches[moduloAdminPreview(adminPreviewEarthlyBranches.indexOf(dayBranch) + 6, adminPreviewEarthlyBranches.length)];

        return {
            sha: adminPreviewShaDirectionByBranch[dayBranch] || '--',
            chongZodiac: adminPreviewZodiacByBranch[chongBranch] || '--'
        };
    };

    var formatAdminPreviewDrawIssueText = function (draw, region) {
        var issueNo = draw && draw.issue_no ? String(draw.issue_no).trim() : '';

        if (!issueNo || (region !== 'macau' && region !== 'hongkong')) {
            return '--';
        }

        var regionLabel = region === 'hongkong' ? '香港' : '澳门';

        return regionLabel + ' ' + normalizeAdminPreviewIssueTail(issueNo) + '期';
    };

    var normalizeAdminPreviewDynamicIssueTail = function (issueNo) {
        var text = String(issueNo || '').trim().replace(/[^0-9]+/g, '');

        if (!text) {
            return '';
        }

        text = text.length > 3 ? text.slice(-3) : text;
        while (text.length < 3) {
            text = '0' + text;
        }

        return text;
    };

    var resolveAdminPreviewCurrentIssueTail = function () {
        var issue = adminPreviewCurrentIssue && typeof adminPreviewCurrentIssue === 'object' ? adminPreviewCurrentIssue : {};

        return normalizeAdminPreviewDynamicIssueTail(
            issue.issue_prefix_tail || issue.issue_prefix_text || issue.issue_no || (adminPreviewDraw ? adminPreviewDraw.issue_no : '')
        );
    };

    var formatAdminPreviewAdIssuePrefixText = function () {
        var issueTail = resolveAdminPreviewCurrentIssueTail();

        return issueTail ? issueTail + '期' : '';
    };

    var formatAdminPreviewPostIssuePrefixText = function () {
        var issueTail = resolveAdminPreviewCurrentIssueTail();

        return issueTail ? issueTail + '期：' : '';
    };

    var findAdminIssuePrefixTitleNode = function (node) {
        var section = node && node.closest ? node.closest('section') : null;

        return section && section.querySelector ? section.querySelector('.section-title') : null;
    };

    var applyAdminIssuePrefixTheme = function (node) {
        var titleNode = findAdminIssuePrefixTitleNode(node);
        var start = titleNode ? colorToHex(titleNode.getAttribute('data-title-bg-start') || '') : '';
        var end = titleNode ? colorToHex(titleNode.getAttribute('data-title-bg-end') || '') : '';

        if (!node || !node.classList || !node.style) {
            return;
        }

        if (!start && titleNode && titleNode.style) {
            start = colorToHex(titleNode.style.backgroundColor || '');
        }

        if (!end) {
            end = start;
        }

        if (!start && !end) {
            node.classList.remove('is-title-synced');
            node.style.removeProperty('--issue-prefix-start');
            node.style.removeProperty('--issue-prefix-end');
            return;
        }

        node.classList.add('is-title-synced');
        node.style.setProperty('--issue-prefix-start', start || end);
        node.style.setProperty('--issue-prefix-end', end || start);
    };

    var stripAdminIssuePrefixPreview = function (root) {
        var nodes = root && root.querySelectorAll ? root.querySelectorAll('.issue-prefix[data-admin-issue-prefix-preview="1"]') : [];
        var index = 0;

        for (index = 0; index < nodes.length; index += 1) {
            nodes[index].textContent = '';
            nodes[index].removeAttribute('data-admin-issue-prefix-preview');

            if (nodes[index].classList) {
                nodes[index].classList.remove('is-ready');
                nodes[index].classList.remove('is-title-synced');
            }

            if (nodes[index].style) {
                nodes[index].style.removeProperty('--issue-prefix-start');
                nodes[index].style.removeProperty('--issue-prefix-end');
                if (!(nodes[index].getAttribute('style') || '').trim()) {
                    nodes[index].removeAttribute('style');
                }
            }
        }
    };

    var applyAdminIssuePrefixPreview = function (root) {
        var nodes = root && root.querySelectorAll ? root.querySelectorAll('.issue-prefix') : [];
        var adPrefixText = formatAdminPreviewAdIssuePrefixText();
        var postPrefixText = formatAdminPreviewPostIssuePrefixText();
        var index = 0;
        var node = null;
        var savedPostPrefixText = '';
        var isAdPrefix = false;
        var isExpertPrefix = false;
        var text = '';

        for (index = 0; index < nodes.length; index += 1) {
            node = nodes[index];
            isAdPrefix = !!(node.classList && node.classList.contains('issue-prefix-ad'));
            isExpertPrefix = !!(node.classList && node.classList.contains('issue-prefix-expert'));
            savedPostPrefixText = isExpertPrefix ? '' : String(node.getAttribute('data-post-issue-prefix') || '').trim();
            text = savedPostPrefixText || (isAdPrefix ? adPrefixText : postPrefixText);

            if (!text) {
                if (node.getAttribute('data-admin-issue-prefix-preview') === '1') {
                    node.textContent = '';
                    node.removeAttribute('data-admin-issue-prefix-preview');
                }

                if (node.classList) {
                    node.classList.remove('is-ready');
                    node.classList.remove('is-title-synced');
                }

                continue;
            }

            node.textContent = text;
            if (node.classList) {
                node.classList.add('is-ready');
            }

            if (savedPostPrefixText) {
                node.removeAttribute('data-admin-issue-prefix-preview');
            } else {
                node.setAttribute('data-admin-issue-prefix-preview', '1');
            }

            if (isAdPrefix) {
                applyAdminIssuePrefixTheme(node);
            } else if (node.classList) {
                node.classList.remove('is-title-synced');
            }
        }
    };

    var formatAdminPreviewDrawOpenTimeText = function (draw) {
        var value = draw && (draw.next_open_time || draw.open_time || draw.draw_date)
            ? String(draw.next_open_time || draw.open_time || draw.draw_date).trim()
            : '';

        return '下期开奖：' + (value ? value.slice(0, 16) : '--');
    };

    var resolveAdminPreviewDrawLunarYear = function (drawDate) {
        var text = String(drawDate || '').trim();
        var match = text.match(/^(\d{4})-\d{2}-\d{2}/);
        var now = new Date();
        var year = match ? Number(match[1]) : now.getFullYear();

        if (adminPreviewDrawLunarNewYearDates[year] && text && text.slice(0, 10) < adminPreviewDrawLunarNewYearDates[year]) {
            year -= 1;
        }

        return year;
    };

    var resolveAdminPreviewDrawZodiac = function (number, drawDate) {
        var value = Number(number || 0);
        var lunarYear = 0;
        var yearAnimalIndex = 0;
        var groupIndex = 0;
        var animalIndex = 0;

        if (value <= 0 || value > 49) {
            return '--';
        }

        lunarYear = resolveAdminPreviewDrawLunarYear(drawDate);
        yearAnimalIndex = moduloAdminPreview(lunarYear - 4, 12);
        groupIndex = moduloAdminPreview(value - 1, 12);
        animalIndex = moduloAdminPreview(yearAnimalIndex - groupIndex, 12);

        return adminPreviewDrawZodiacAnimals[animalIndex] || '--';
    };

    var renderAdminPreviewLiveBall = function (itemNode, value, drawDate) {
        var number = Number(value || 0);
        var colorClass = getAdminPreviewWaveColorClass(number);
        var codeNode = null;
        var zodiacNode = null;

        if (!itemNode) {
            return;
        }

        codeNode = itemNode.querySelector('.result-jl-code');
        zodiacNode = itemNode.querySelector('.hero-ball-zodiac');

        if (codeNode) {
            codeNode.className = 'result-jl-code ' + colorClass;
            codeNode.textContent = number > 0 ? padAdminPreviewNumber(number) : '--';
        }

        if (zodiacNode) {
            zodiacNode.className = 'hero-ball-zodiac ' + colorClass;
            zodiacNode.textContent = number > 0 ? resolveAdminPreviewDrawZodiac(number, drawDate) : '--';
        }
    };

    var applyAdminDrawPreview = function (root, draw, region) {
        var values = [];
        var specialNumber = 0;
        var ballNodes = [];
        var index = 0;
        var drawDate = draw && draw.draw_date ? String(draw.draw_date).trim() : '';

        if (!root) {
            return;
        }

        setAdminPreviewText(root, 'hero-result-period', formatAdminPreviewDrawIssueText(draw, region));
        setAdminPreviewText(root, 'hero-result-open-time', formatAdminPreviewDrawOpenTimeText(draw));

        if (draw && Array.isArray(draw.numbers)) {
            values = draw.numbers.slice(0, 6).map(function (value) {
                return Number(value || 0);
            });
        }

        while (values.length < 6) {
            values.push(0);
        }

        specialNumber = draw ? Number(draw.special_number || 0) : 0;
        values.push(specialNumber);
        ballNodes = root.querySelectorAll ? root.querySelectorAll('#hero-result-numbers .hero-ball-item') : [];

        for (index = 0; index < ballNodes.length; index += 1) {
            renderAdminPreviewLiveBall(ballNodes[index], values[index] || 0, drawDate);
        }

        setAdminPreviewText(root, 'hero-result-zodiac', specialNumber > 0 ? resolveAdminPreviewDrawZodiac(specialNumber, drawDate) : '--');
        setAdminPreviewText(root, 'hero-result-odd-even', specialNumber > 0 ? sumAdminPreviewDrawOddEven(specialNumber) : '--');
        setAdminPreviewText(root, 'hero-result-five-phase', specialNumber > 0 ? findAdminPreviewGroupName(adminPreviewFivePhaseGroups, specialNumber, '--') : '--');
    };

    var applyAdminCalendarPreview = function (body, currentDate) {
        var now = currentDate instanceof Date ? currentDate : new Date();
        var lunarConflict = resolveAdminPreviewLunarConflictMeta(now);

        if (!body) {
            return;
        }

        setAdminPreviewText(body, 'live-time', padAdminPreviewNumber(now.getHours()) + ':' + padAdminPreviewNumber(now.getMinutes()) + ':' + padAdminPreviewNumber(now.getSeconds()));
        setAdminPreviewText(body, 'live-weekday', adminPreviewWeekdayNames[now.getDay()]);
        setAdminPreviewText(body, 'solar-date', (now.getMonth() + 1) + '月' + now.getDate() + '日');
        setAdminPreviewText(body, 'solar-year', now.getFullYear() + '年');
        setAdminPreviewText(body, 'lunar-date', formatAdminPreviewLunarDateText(now));
        setAdminPreviewText(body, 'lunar-sha', '煞:' + lunarConflict.sha);
        setAdminPreviewText(body, 'lunar-chong', '冲肖:' + lunarConflict.chongZodiac);
    };

    var applyAdminLivePreview = function (root, currentDate) {
        if (!root) {
            return;
        }

        applyAdminDrawPreview(root, adminPreviewDraw, adminPreviewRegion);
        applyAdminIssuePrefixPreview(root);
        applyAdminCalendarPreview(root, currentDate instanceof Date ? currentDate : new Date());
    };

    var refreshAdminLivePreview = function (editor, currentDate) {
        var body = editor ? editor.getBody() : null;

        if (!body) {
            return;
        }

        applyAdminLivePreview(body, currentDate instanceof Date ? currentDate : new Date());
    };

    var stopAdminPreviewLiveTimer = function () {
        if (adminPreviewLiveTimer) {
            window.clearInterval(adminPreviewLiveTimer);
            adminPreviewLiveTimer = 0;
        }
    };

    var startAdminPreviewLiveTimer = function (editor) {
        stopAdminPreviewLiveTimer();
        refreshAdminLivePreview(editor, new Date());
        adminPreviewLiveTimer = window.setInterval(function () {
            if (!editor || editor.removed || !editor.getBody()) {
                return;
            }

            refreshAdminLivePreview(editor, new Date());
        }, 1000);
    };

    var refreshDrawEditorDecorations = function (editor, options) {
        var body = editor ? editor.getBody() : null;
        var refreshControls = !options || options.refreshControls !== false;
        var previewNow = new Date();

        if (!editor || !body) {
            return;
        }

        applyAdminLivePreview(body, previewNow);
        applyManagedTitleBackgrounds(body);
        applyAdItemMiddleColorPreview(body);
        applyAdItemTailTextPreview(body);
        ensureManualFontSizeInput(editor);
        syncManualFontSizeInput(editor);

        if (refreshControls && sectionEditorControlsActivated) {
            scheduleSectionEditorRefresh(editor);
        }
    };

    var activateSectionEditorControls = function (editor, options) {
        if (!editor) {
            return;
        }

        if (sectionEditorControlsActivated && !(options && options.force)) {
            return;
        }

        sectionEditorControlsActivated = true;
        refreshDrawEditorDecorations(editor);
    };

    var normalizeManualFontSize = function (value) {
        var normalized = (value || '').toString().trim();

        if (normalized === '') {
            return '';
        }

        if (/^\d+(\.\d+)?$/.test(normalized)) {
            return normalized + 'px';
        }

        if (/^\d+(\.\d+)?(px|pt|em|rem|%)$/i.test(normalized)) {
            return normalized;
        }

        return '';
    };

    var getSelectionFontSize = function (editor) {
        var node = editor && editor.selection ? editor.selection.getStart() : null;
        var doc = editor ? editor.getDoc() : null;
        var win = doc ? doc.defaultView : null;
        var size = '';

        try {
            size = editor ? (editor.queryCommandValue('FontSize') || '') : '';
        } catch (error) {
            size = '';
        }

        if (size) {
            return size;
        }

        if (!node) {
            return '';
        }

        if (node.nodeType === 3) {
            node = node.parentNode;
        }

        if (!node) {
            return '';
        }

        if (editor && editor.dom && editor.dom.getStyle) {
            size = editor.dom.getStyle(node, 'font-size', true) || '';
        }

        if (!size && win && win.getComputedStyle) {
            size = win.getComputedStyle(node).fontSize || '';
        }

        return size || '';
    };

    var syncManualFontSizeInput = function (editor) {
        var inputEl = fontSizeToolbarState.inputEl;
        var activeEl = inputEl ? inputEl.ownerDocument.activeElement : null;

        if (!inputEl || fontSizeToolbarState.editor !== editor || activeEl === inputEl) {
            return;
        }

        inputEl.value = getSelectionFontSize(editor) || '';
    };

    var applyManualFontSize = function (editor) {
        var inputEl = fontSizeToolbarState.inputEl;
        var value = normalizeManualFontSize(inputEl ? inputEl.value : '');

        if (!editor || !inputEl) {
            return;
        }

        if (!value) {
            syncManualFontSizeInput(editor);
            return;
        }

        if (fontSizeToolbarState.bookmark && editor.selection && editor.selection.moveToBookmark) {
            editor.focus();
            editor.selection.moveToBookmark(fontSizeToolbarState.bookmark);
        } else {
            editor.focus();
        }

        editor.execCommand('FontSize', false, value);
        fontSizeToolbarState.bookmark = null;
        inputEl.value = value;
        syncEditorTextarea({
            keepSourcePanel: !!(sectionSourceState.panelEl && sectionSourceState.panelEl.parentNode)
        });
    };

    var ensureManualFontSizeInput = function (editor) {
        var container = editor ? editor.getContainer() : null;
        var toolbar = container ? container.querySelector('.tox-toolbar__primary') : null;
        var groups = toolbar ? toolbar.querySelectorAll('.tox-toolbar__group') : [];
        var anchorGroup = groups.length > 1 ? groups[1] : null;
        var groupEl = null;
        var inputEl = null;

        if (!container || !toolbar) {
            return;
        }

        groupEl = container.querySelector('[data-manual-fontsize-group]');
        inputEl = groupEl ? groupEl.querySelector('[data-manual-fontsize-input]') : null;

        if (!groupEl) {
            groupEl = document.createElement('div');
            groupEl.className = 'tox-toolbar__group';
            groupEl.setAttribute('data-manual-fontsize-group', '1');
            groupEl.style.display = 'inline-flex';
            groupEl.style.alignItems = 'center';
            groupEl.style.gap = '6px';

            inputEl = document.createElement('input');
            inputEl.setAttribute('type', 'text');
            inputEl.setAttribute('inputmode', 'decimal');
            inputEl.setAttribute('data-manual-fontsize-input', '1');
            inputEl.setAttribute('placeholder', '14px');
            inputEl.setAttribute('title', '手动输入字号，例如 14px、1rem、120%');
            inputEl.className = 'tox-textfield';
            inputEl.style.width = '78px';
            inputEl.style.minWidth = '78px';
            inputEl.style.height = '30px';
            inputEl.style.padding = '0 8px';
            inputEl.style.fontSize = '13px';
            inputEl.style.lineHeight = '30px';

            inputEl.addEventListener('mousedown', function () {
                if (editor && editor.selection && editor.selection.getBookmark) {
                    fontSizeToolbarState.bookmark = editor.selection.getBookmark(2, true);
                }
            });

            inputEl.addEventListener('keydown', function (event) {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    applyManualFontSize(editor);
                }
            });

            inputEl.addEventListener('change', function () {
                applyManualFontSize(editor);
            });

            inputEl.addEventListener('blur', function () {
                applyManualFontSize(editor);
            });

            groupEl.appendChild(inputEl);

            if (anchorGroup && anchorGroup.parentNode) {
                anchorGroup.parentNode.insertBefore(groupEl, anchorGroup.nextSibling);
            } else {
                toolbar.appendChild(groupEl);
            }
        }

        fontSizeToolbarState.editor = editor;
        fontSizeToolbarState.groupEl = groupEl;
        fontSizeToolbarState.inputEl = inputEl;
        syncManualFontSizeInput(editor);
    };

    ensureManualFontSizeInput = function (editor) {
        var container = editor ? editor.getContainer() : null;
        var controls = [];
        var detachedGroup = null;
        var controlEl = null;
        var labelEl = null;
        var selectEl = null;
        var overlayEl = null;
        var inputEl = null;

        if (!container) {
            return;
        }

        detachedGroup = container.querySelector('[data-manual-fontsize-group]');
        if (detachedGroup && !detachedGroup.closest('.tox-listboxfield, .tox-selectfield')) {
            detachedGroup.parentNode.removeChild(detachedGroup);
        }

        controls = Array.prototype.slice.call(
            container.querySelectorAll('.tox-toolbar .tox-listboxfield, .tox-toolbar .tox-selectfield, .tox-toolbar__primary .tox-listboxfield, .tox-toolbar__primary .tox-selectfield')
        );

        controlEl = controls.find(function (item, index) {
            var currentLabel = item.querySelector('.tox-listbox__select-label');
            var text = currentLabel ? currentLabel.textContent.trim() : (item.textContent || '').trim();
            var meta = ((item.getAttribute('title') || '') + ' ' + (item.getAttribute('aria-label') || '')).toLowerCase();

            if (/^\d+(\.\d+)?(px|pt|em|rem|%)?$/i.test(text)) {
                return true;
            }

            if (/font\s*size|字号|字体大小/.test(meta)) {
                return true;
            }

            return controls.length >= 3 && index === 2;
        }) || null;

        if (!controlEl) {
            fontSizeToolbarState.editor = editor;
            fontSizeToolbarState.groupEl = null;
            fontSizeToolbarState.inputEl = null;
            return;
        }

        labelEl = controlEl.querySelector('.tox-listbox__select-label');
        selectEl = controlEl.querySelector('select');
        overlayEl = controlEl.querySelector('[data-manual-fontsize-group]');
        inputEl = overlayEl ? overlayEl.querySelector('[data-manual-fontsize-input]') : null;

        controlEl.style.position = 'relative';
        controlEl.style.overflow = 'hidden';

        if (labelEl) {
            labelEl.style.opacity = '0';
            labelEl.style.pointerEvents = 'none';
        }

        if (selectEl) {
            selectEl.style.color = 'transparent';
            selectEl.style.textShadow = 'none';
        }

        if (!overlayEl) {
            overlayEl = document.createElement('span');
            overlayEl.setAttribute('data-manual-fontsize-group', '1');
            overlayEl.style.position = 'absolute';
            overlayEl.style.left = '1px';
            overlayEl.style.right = '24px';
            overlayEl.style.top = '1px';
            overlayEl.style.bottom = '1px';
            overlayEl.style.display = 'flex';
            overlayEl.style.alignItems = 'center';
            overlayEl.style.zIndex = '2';
            overlayEl.style.pointerEvents = 'auto';

            inputEl = document.createElement('input');
            inputEl.setAttribute('type', 'text');
            inputEl.setAttribute('inputmode', 'text');
            inputEl.setAttribute('data-manual-fontsize-input', '1');
            inputEl.setAttribute('placeholder', '14px');
            inputEl.setAttribute('title', '手动输入字号，例如 14px、1rem、120%');
            inputEl.className = 'tox-toolbar-textfield';
            inputEl.style.width = '100%';
            inputEl.style.maxWidth = '100%';
            inputEl.style.minHeight = '0';
            inputEl.style.height = '100%';
            inputEl.style.margin = '0';
            inputEl.style.border = '0';
            inputEl.style.borderRadius = '0';
            inputEl.style.background = 'transparent';
            inputEl.style.boxShadow = 'none';
            inputEl.style.padding = '0 6px';
            inputEl.style.font = 'inherit';
            inputEl.style.lineHeight = '1';
            inputEl.style.cursor = 'text';

            inputEl.addEventListener('pointerdown', function (event) {
                event.stopPropagation();

                if (editor && editor.selection && editor.selection.getBookmark) {
                    fontSizeToolbarState.bookmark = editor.selection.getBookmark(2, true);
                }
            });

            inputEl.addEventListener('mousedown', function (event) {
                event.stopPropagation();
            });

            inputEl.addEventListener('click', function (event) {
                event.stopPropagation();
            });

            inputEl.addEventListener('focus', function () {
                if (editor && editor.selection && editor.selection.getBookmark) {
                    fontSizeToolbarState.bookmark = editor.selection.getBookmark(2, true);
                }
            });

            inputEl.addEventListener('keydown', function (event) {
                event.stopPropagation();

                if (event.key === 'Enter') {
                    event.preventDefault();
                    applyManualFontSize(editor);
                }
            });

            inputEl.addEventListener('change', function () {
                applyManualFontSize(editor);
            });

            inputEl.addEventListener('blur', function () {
                applyManualFontSize(editor);
            });

            overlayEl.appendChild(inputEl);
            controlEl.appendChild(overlayEl);
        }

        fontSizeToolbarState.editor = editor;
        fontSizeToolbarState.groupEl = overlayEl;
        fontSizeToolbarState.inputEl = inputEl;
        syncManualFontSizeInput(editor);
    };

    var enhanceSectionEditButton = function (button) {
        if (!button) {
            return;
        }

        button.style.pointerEvents = 'auto';
        button.style.minWidth = '66px';
        button.style.padding = '0 14px';
        button.style.background = 'linear-gradient(135deg, #2563eb, #1d4ed8)';
        button.style.color = '#ffffff';
        button.style.fontSize = '12px';
        button.style.fontWeight = '900';
        button.style.letterSpacing = '0.02em';
        button.style.boxShadow = '0 10px 20px rgba(37, 99, 235, 0.28), inset 0 0 0 1px rgba(255, 255, 255, 0.22)';
        button.style.textShadow = '0 1px 1px rgba(15, 23, 42, 0.22)';
        button.style.border = '0';
        button.style.userSelect = 'none';
        button.style.touchAction = 'manipulation';
    };

    var enhanceSectionSourceButton = function (button) {
        if (!button) {
            return;
        }

        button.style.pointerEvents = 'auto';
        button.style.minWidth = '66px';
        button.style.padding = '0 14px';
        button.style.background = 'linear-gradient(135deg, #0f172a, #334155)';
        button.style.color = '#ffffff';
        button.style.fontSize = '12px';
        button.style.fontWeight = '900';
        button.style.letterSpacing = '0.02em';
        button.style.boxShadow = '0 10px 20px rgba(15, 23, 42, 0.22), inset 0 0 0 1px rgba(255, 255, 255, 0.12)';
        button.style.textShadow = '0 1px 1px rgba(15, 23, 42, 0.22)';
        button.style.border = '0';
        button.style.cursor = 'pointer';
        button.style.userSelect = 'none';
        button.style.touchAction = 'manipulation';
    };

    var enhanceSectionVisibilityButton = function (button) {
        if (!button) {
            return;
        }

        button.style.pointerEvents = 'auto';
        button.style.minWidth = '48px';
        button.style.padding = '0 12px';
        button.style.color = '#ffffff';
        button.style.fontSize = '12px';
        button.style.fontWeight = '900';
        button.style.letterSpacing = '0.02em';
        button.style.textShadow = '0 1px 1px rgba(15, 23, 42, 0.22)';
        button.style.border = '0';
        button.style.cursor = 'pointer';
        button.style.userSelect = 'none';
        button.style.touchAction = 'manipulation';
    };

    var applySectionVisibilityButtonState = function (button, isHidden) {
        if (!button) {
            return;
        }

        button.textContent = '\u2611\ufe0f';
        button.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        button.setAttribute('title', isHidden ? '当前区块已隐藏，点击重新显示' : '隐藏当前区块');
        button.style.background = isHidden
            ? 'linear-gradient(135deg, #f59e0b, #d97706)'
            : 'linear-gradient(135deg, #10b981, #059669)';
        button.style.boxShadow = isHidden
            ? '0 10px 20px rgba(217, 119, 6, 0.28), inset 0 0 0 1px rgba(255, 255, 255, 0.18)'
            : '0 10px 20px rgba(5, 150, 105, 0.28), inset 0 0 0 1px rgba(255, 255, 255, 0.18)';
    };

    var enhanceSectionDeleteButton = function (button) {
        if (!button) {
            return;
        }

        button.style.pointerEvents = 'auto';
        button.style.minWidth = '66px';
        button.style.padding = '0 14px';
        button.style.background = 'linear-gradient(135deg, #ef4444, #dc2626)';
        button.style.color = '#ffffff';
        button.style.fontSize = '12px';
        button.style.fontWeight = '900';
        button.style.letterSpacing = '0.02em';
        button.style.boxShadow = '0 10px 20px rgba(220, 38, 38, 0.24), inset 0 0 0 1px rgba(255, 255, 255, 0.18)';
        button.style.textShadow = '0 1px 1px rgba(15, 23, 42, 0.22)';
        button.style.border = '0';
        button.style.cursor = 'pointer';
        button.style.userSelect = 'none';
        button.style.touchAction = 'manipulation';
    };

    var isSectionBlockHidden = function (blockEl) {
        return !!(blockEl && blockEl.getAttribute && blockEl.getAttribute('data-section-hidden') === '1');
    };

    var setSectionBlockHidden = function (blockEl, shouldHide) {
        var currentDisplay = '';

        if (!blockEl || !blockEl.style || !blockEl.getAttribute || !blockEl.setAttribute) {
            return;
        }

        if (shouldHide) {
            currentDisplay = blockEl.style.display || '';
            if (currentDisplay && currentDisplay !== 'none') {
                blockEl.setAttribute('data-section-display', currentDisplay);
            }
            blockEl.setAttribute('data-section-hidden', '1');
            blockEl.style.display = 'none';
            return;
        }

        blockEl.removeAttribute('data-section-hidden');
        currentDisplay = blockEl.getAttribute('data-section-display') || '';
        if (currentDisplay) {
            blockEl.style.display = currentDisplay;
            blockEl.removeAttribute('data-section-display');
        } else {
            blockEl.style.removeProperty('display');
        }

        if (!(blockEl.getAttribute('style') || '').trim()) {
            blockEl.removeAttribute('style');
        }
    };

    var getSectionBlockById = function (root, blockId) {
        var blocks = getSortableBlocks(root);
        var index = 0;

        for (index = 0; index < blocks.length; index += 1) {
            if (blocks[index].getAttribute && blocks[index].getAttribute('data-section-editor-block-key') === blockId) {
                return blocks[index];
            }

            if (getSectionEditorBlockId(blocks[index]) === blockId) {
                return blocks[index];
            }
        }

        return null;
    };

    var findSectionEditorControlBlock = function (editor, control) {
        var body = editor ? editor.getBody() : null;
        var blockId = control && control.getAttribute ? (control.getAttribute('data-section-editor-block-id') || '') : '';
        var titleEl = null;
        var blockEl = null;

        if (!control || !body) {
            return null;
        }

        if (blockId) {
            blockEl = getSectionBlockById(body, blockId);
            if (blockEl) {
                return blockEl;
            }
        }

        titleEl = control.closest ? control.closest('.section-title') : null;
        if (titleEl && titleEl.closest) {
            blockEl = titleEl.closest('section');
            if (blockEl) {
                return blockEl;
            }
        }

        blockEl = control.closest ? control.closest('section, #section-live.hero-live-box, .marquee, header.top-bar, nav.bottom-float-nav') : null;
        return isSortableBlock(blockEl) ? blockEl : null;
    };

    var runSectionEditorControlAction = function (editor, control, event) {
        var action = control && control.getAttribute ? (control.getAttribute('data-section-editor-control') || '') : '';
        var blockEl = findSectionEditorControlBlock(editor, control);
        var titleEl = blockEl ? getDirectSectionTitle(blockEl) : null;

        if (!action) {
            return false;
        }

        if (action === 'button') {
            openSectionEditor(editor, blockEl || (control.closest ? control.closest('.section-title') : null) || titleEl);
            return true;
        }

        if (action === 'floating-button') {
            openSectionEditor(editor, blockEl);
            return true;
        }

        if (action === 'source-button' || action === 'floating-source-button') {
            showSectionSourceEditor(editor, blockEl);
            return true;
        }

        if (action === 'visibility-button' || action === 'floating-visibility-button') {
            toggleSectionBlockHidden(editor, blockEl);
            return true;
        }

        if (action === 'delete-button' || action === 'floating-delete-button') {
            removeSectionBlock(editor, blockEl);
            return true;
        }

        if (action === 'handle' || action === 'floating-handle') {
            startSectionDrag(editor, blockEl, titleEl, event);
            return true;
        }

        return false;
    };

    var resolveSectionEditorControlFromEvent = function (event, doc) {
        var target = event ? (event.target || null) : null;
        var control = null;
        var hitTarget = null;

        if (target && target.nodeType === 3) {
            target = target.parentNode;
        }

        control = target && target.closest ? target.closest('[data-section-editor-control]') : null;
        if (control || !doc || !event || typeof doc.elementFromPoint !== 'function') {
            return control;
        }

        if (typeof event.clientX !== 'number' || typeof event.clientY !== 'number') {
            return null;
        }

        hitTarget = doc.elementFromPoint(event.clientX, event.clientY);
        if (hitTarget && hitTarget.nodeType === 3) {
            hitTarget = hitTarget.parentNode;
        }

        return hitTarget && hitTarget.closest ? hitTarget.closest('[data-section-editor-control]') : null;
    };

    var shouldPassThroughSectionEditorControl = function (action) {
        return action === 'source-panel' ||
            action === 'source-textarea' ||
            action === 'source-actions' ||
            action === 'source-close' ||
            action === 'source-apply';
    };

    var dispatchSectionEditorControlEvent = function (editor, event, eventName) {
        var doc = editor ? editor.getDoc() : null;
        var control = resolveSectionEditorControlFromEvent(event, doc);
        var action = control && control.getAttribute ? (control.getAttribute('data-section-editor-control') || '') : '';
        var now = Date.now ? Date.now() : new Date().getTime();
        var lastRunAt = control ? (control._sectionEditorBridgeRunAt || 0) : 0;

        if (!control || shouldPassThroughSectionEditorControl(action) || shouldIgnoreSectionPointerButton(event)) {
            return false;
        }

        if ((action === 'handle' || action === 'floating-handle') && eventName !== 'mousedown') {
            if (event && typeof event.stopPropagation === 'function') {
                event.stopPropagation();
            }
            return true;
        }

        if (now - lastRunAt < 180) {
            consumeSectionControlEvent(event);
            return true;
        }

        consumeSectionControlEvent(event);
        control._sectionEditorBridgeRunAt = now;
        control._sectionEditorLastRunAt = now;

        return runSectionEditorControlAction(editor, control, event);
    };

    var installSectionEditorControlBridge = function (editor) {
        var doc = editor ? editor.getDoc() : null;
        var body = editor ? editor.getBody() : null;
        var attachBridgeTarget = function (target) {
            if (!target || target._sectionEditorControlBridgeInstalled) {
                return;
            }

            target._sectionEditorControlBridgeInstalled = true;

            ['pointerdown', 'mousedown', 'touchstart', 'click'].forEach(function (eventName) {
                target.addEventListener(eventName, function (event) {
                    dispatchSectionEditorControlEvent(editor, event, eventName);
                }, true);
            });
        };

        attachBridgeTarget(doc);
        attachBridgeTarget(body);
    };

    var ensureSectionEditorPreviewStyles = function (editor) {
        var doc = editor ? editor.getDoc() : null;
        var styleEl = null;

        if (!doc) {
            return;
        }

        styleEl = doc.getElementById('section-editor-preview-style');
        if (styleEl) {
            installSectionEditorControlBridge(editor);
            return;
        }

        styleEl = doc.createElement('style');
        styleEl.id = 'section-editor-preview-style';
        styleEl.textContent = ''
            + '.editor-sortable-block[data-section-hidden="1"] { display: block !important; opacity: 0.54; filter: grayscale(0.08); }'
            + '.editor-sortable-block[data-section-hidden="1"]::after { content: "\\5DF2\\9690\\85CF"; position: absolute; top: 12px; left: 12px; z-index: 14; padding: 4px 10px; border-radius: 999px; background: rgba(15, 23, 42, 0.82); color: #ffffff; font-size: 12px; font-weight: 900; line-height: 1; box-shadow: 0 8px 20px rgba(15, 23, 42, 0.18); }'
            + '.editor-sortable-block[data-section-edit-locked="1"] { outline: 2px dashed rgba(37, 99, 235, 0.32); outline-offset: 4px; }'
            + '.editor-sortable-block[data-section-edit-locked="1"]::before { content: "\\5DF2\\9501\\5B9A"; position: absolute; top: 48px; right: 12px; z-index: 14; padding: 4px 10px; border-radius: 999px; background: rgba(37, 99, 235, 0.88); color: #ffffff; font-size: 12px; font-weight: 900; line-height: 1; box-shadow: 0 8px 20px rgba(37, 99, 235, 0.18); }'
            + '.editor-section-floating-handle-row { pointer-events: auto !important; z-index: 20 !important; }'
            + '.editor-section-drag-handle, .editor-section-edit-button { pointer-events: auto !important; position: relative; z-index: 21; }';
        doc.head.appendChild(styleEl);
        installSectionEditorControlBridge(editor);
    };

    var refreshSectionVisibilityButtons = function (editor) {
        var currentEditor = editor || (typeof window.tinymce !== 'undefined' ? window.tinymce.get('draw-material-editor') : null);
        var body = currentEditor ? currentEditor.getBody() : null;
        var buttons = body ? body.querySelectorAll('[data-section-editor-control="visibility-button"], [data-section-editor-control="floating-visibility-button"]') : [];
        var index = 0;
        var currentButton = null;
        var blockId = '';
        var blockEl = null;

        if (!body) {
            return;
        }

        for (index = 0; index < buttons.length; index += 1) {
            currentButton = buttons[index];
            blockId = currentButton ? (currentButton.getAttribute('data-section-editor-block-id') || '') : '';
            blockEl = blockId ? getSectionBlockById(body, blockId) : null;
            applySectionVisibilityButtonState(currentButton, isSectionBlockHidden(blockEl));
        }
    };

    var toggleSectionBlockHidden = function (editor, blockEl) {
        var shouldHide = false;

        if (!editor || !blockEl) {
            return;
        }

        shouldHide = !isSectionBlockHidden(blockEl);
        setSectionBlockHidden(blockEl, shouldHide);
        syncEditorTextarea();
        showDrawEditorNotice(shouldHide ? '当前区块已隐藏。' : '当前区块已恢复显示。', 'success');
    };

    var removeSectionBlock = function (editor, blockEl) {
        if (!editor || !blockEl || !blockEl.parentNode) {
            return;
        }

        if (!window.AppUI || typeof window.AppUI.confirm !== 'function') {
            return;
        }

        window.AppUI.confirm('确定删除这个区块吗？删除后可通过保存生效。', '确认操作', '确定', '取消').then(function (confirmed) {
            if (!confirmed || !blockEl.parentNode) {
                return;
            }

            if (sectionSourceState.blockEl && getSectionEditorBlockId(sectionSourceState.blockEl) === getSectionEditorBlockId(blockEl)) {
                closeSectionSourceEditor(editor);
            }

            if (activeSectionState.blockEl && getSectionEditorBlockId(activeSectionState.blockEl) === getSectionEditorBlockId(blockEl)) {
                closeSectionEditor();
            }

            blockEl.parentNode.removeChild(blockEl);
            syncEditorTextarea();
            showDrawEditorNotice('当前区块已删除。', 'success');
        });
    };

    var isVoidHtmlElement = function (tagName) {
        return /^(area|base|br|col|embed|hr|img|input|link|meta|param|source|track|wbr)$/i.test(tagName || '');
    };

    var escapeHtmlText = function (value) {
        return (value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    };

    var escapeHtmlAttribute = function (value) {
        return escapeHtmlText(value).replace(/"/g, '&quot;');
    };

    var cleanEditorClone = function (root) {
        var nodes = root ? root.querySelectorAll('*') : [];
        var controls = root ? root.querySelectorAll('[data-section-editor-control]') : [];
        var index = 0;
        var attrIndex = 0;
        var attributes = null;
        var classNames = null;

        if (!root) {
            return;
        }

        root.removeAttribute('data-section-editor-active');
        root.removeAttribute('data-section-editor-block-key');

        if (root.classList) {
            root.classList.remove('editor-sortable-block');
            root.classList.remove('editor-sortable-block--floating');
        }

        for (index = 0; index < controls.length; index += 1) {
            if (controls[index] && controls[index].parentNode) {
                controls[index].parentNode.removeChild(controls[index]);
            }
        }

        nodes = root ? root.querySelectorAll('*') : [];

        for (index = 0; index < nodes.length; index += 1) {
            nodes[index].removeAttribute('data-section-editor-active');
            nodes[index].removeAttribute('data-section-editor-block-key');
            nodes[index].removeAttribute('contenteditable');

            if (nodes[index].classList) {
                nodes[index].classList.remove('editor-sortable-block');
                nodes[index].classList.remove('editor-sortable-block--floating');
            }

            attributes = Array.prototype.slice.call(nodes[index].attributes || []);
            for (attrIndex = 0; attrIndex < attributes.length; attrIndex += 1) {
                if (/^data-mce-/i.test(attributes[attrIndex].name)) {
                    nodes[index].removeAttribute(attributes[attrIndex].name);
                }
            }

            classNames = (nodes[index].getAttribute('class') || '').split(/\s+/).filter(function (name) {
                return name && name.indexOf('mce-') !== 0;
            });

            if (classNames.length) {
                nodes[index].setAttribute('class', classNames.join(' '));
            } else {
                nodes[index].removeAttribute('class');
            }
        }
    };

    var formatHtmlNode = function (node, depth) {
        var indent = new Array(depth + 1).join('    ');
        var tagName = '';
        var attrs = '';
        var children = [];
        var index = 0;
        var childLines = [];
        var textValue = '';
        var child = null;
        var childResult = null;

        if (!node) {
            return [];
        }

        if (node.nodeType === 3) {
            textValue = node.textContent.replace(/\s+/g, ' ').trim();
            return textValue ? [indent + escapeHtmlText(textValue)] : [];
        }

        if (node.nodeType !== 1) {
            return [];
        }

        tagName = node.tagName.toLowerCase();
        attrs = Array.prototype.map.call(node.attributes || [], function (attribute) {
            return ' ' + attribute.name + '="' + escapeHtmlAttribute(attribute.value) + '"';
        }).join('');

        for (index = 0; index < node.childNodes.length; index += 1) {
            child = node.childNodes[index];
            if (child.nodeType === 3 && child.textContent.replace(/\s+/g, ' ').trim() === '') {
                continue;
            }
            children.push(child);
        }

        if (!children.length) {
            return [indent + '<' + tagName + attrs + '>' + (isVoidHtmlElement(tagName) ? '' : '</' + tagName + '>')];
        }

        if (children.length === 1 && children[0].nodeType === 3) {
            textValue = children[0].textContent.replace(/\s+/g, ' ').trim();
            return [indent + '<' + tagName + attrs + '>' + escapeHtmlText(textValue) + '</' + tagName + '>'];
        }

        childLines.push(indent + '<' + tagName + attrs + '>');

        for (index = 0; index < children.length; index += 1) {
            childResult = formatHtmlNode(children[index], depth + 1);
            if (childResult.length) {
                childLines = childLines.concat(childResult);
            }
        }

        childLines.push(indent + '</' + tagName + '>');
        return childLines;
    };

    var resetSectionSourceState = function () {
        window.clearTimeout(sectionSourceState.syncTimer);
        sectionSourceState.editor = null;
        sectionSourceState.blockEl = null;
        sectionSourceState.panelEl = null;
        sectionSourceState.textareaEl = null;
        sectionSourceState.syncTimer = null;
        sectionSourceState.isSyncingFromSource = false;
    };

    var isSectionSourcePanelOpenForBlock = function (editor, blockEl) {
        if (!editor || !blockEl) {
            return false;
        }

        if (!sectionSourceState.panelEl || !sectionSourceState.panelEl.parentNode || sectionSourceState.editor !== editor || !sectionSourceState.blockEl) {
            return false;
        }

        return getSectionEditorBlockId(sectionSourceState.blockEl) === getSectionEditorBlockId(blockEl);
    };

    var refreshSectionSourceButtons = function (editor) {
        var currentEditor = editor || sectionSourceState.editor || (typeof window.tinymce !== 'undefined' ? window.tinymce.get('draw-material-editor') : null);
        var body = currentEditor ? currentEditor.getBody() : null;
        var buttons = body ? body.querySelectorAll('[data-section-editor-control="source-button"], [data-section-editor-control="floating-source-button"]') : [];
        var activeBlockId = sectionSourceState.blockEl && isSectionSourcePanelOpenForBlock(currentEditor, sectionSourceState.blockEl)
            ? getSectionEditorBlockId(sectionSourceState.blockEl)
            : '';
        var index = 0;
        var currentButton = null;
        var buttonBlockId = '';

        if (!body) {
            return;
        }

        for (index = 0; index < buttons.length; index += 1) {
            currentButton = buttons[index];
            buttonBlockId = currentButton ? (currentButton.getAttribute('data-section-editor-block-id') || '') : '';

            if (!currentButton) {
                continue;
            }

            currentButton.textContent = buttonBlockId !== '' && buttonBlockId === activeBlockId
                ? sectionSourceButtonCloseText
                : sectionSourceButtonOpenText;
            currentButton.setAttribute('aria-pressed', buttonBlockId !== '' && buttonBlockId === activeBlockId ? 'true' : 'false');
        }
    };

    var closeSectionSourceEditor = function (editor) {
        var currentEditor = editor || sectionSourceState.editor || (typeof window.tinymce !== 'undefined' ? window.tinymce.get('draw-material-editor') : null);

        if (sectionSourceState.panelEl && sectionSourceState.panelEl.parentNode) {
            sectionSourceState.panelEl.parentNode.removeChild(sectionSourceState.panelEl);
        }

        clearActiveSection();
        resetSectionSourceState();
        refreshSectionSourceButtons(currentEditor);
    };

    var getCleanBlockHtml = function (blockEl) {
        var clone = null;

        if (!blockEl) {
            return '';
        }

        clone = blockEl.cloneNode(true);
        cleanEditorClone(clone);
        return formatHtmlNode(clone, 0).join('\n');
    };

    var showSectionSourceEditor = function (editor, blockEl) {
        var doc = editor ? editor.getDoc() : null;
        var panelEl = null;
        var titleEl = null;
        var bodyEl = null;
        var textareaEl = null;
        var actionRow = null;
        var applyButton = null;
        var closeButton = null;
        var wrapperTitle = null;
        var setCurrentBlockState = function (nextBlock) {
            titleEl = getDirectSectionTitle(nextBlock);
            bodyEl = titleEl ? titleEl.nextElementSibling : null;

            activeSectionState.editor = editor;
            activeSectionState.blockEl = nextBlock;
            activeSectionState.sectionEl = nextBlock && nextBlock.tagName === 'SECTION' ? nextBlock : null;
            activeSectionState.titleEl = titleEl;
            activeSectionState.bodyEl = bodyEl;

            if (nextBlock) {
                nextBlock.setAttribute('data-section-editor-active', '1');
            }

            if (titleEl) {
                titleEl.setAttribute('data-section-editor-active', '1');
                if (bodyEl) {
                    bodyEl.setAttribute('data-section-editor-active', '1');
                }
            }

            sectionSourceState.editor = editor;
            sectionSourceState.blockEl = nextBlock;
        };
        var parseSourceToBlock = function (sourceHtml, shouldAlert) {
            var holder = doc.createElement('div');
            var candidate = null;
            var index = 0;

            holder.innerHTML = sourceHtml;

            for (index = 0; index < holder.children.length; index += 1) {
                if (isSortableBlock(holder.children[index])) {
                    candidate = holder.children[index];
                    break;
                }
            }

            if (!candidate || holder.children.length !== 1) {
                showDrawEditorNotice('源码需要保留一个主卡片根节点，例如 <section>...</section>。', 'error');
                return null;
            }

            return candidate;
        };
        var applySourceChanges = function () {
            var sourceHtml = textareaEl ? textareaEl.value : '';
            var newBlock = null;
            var parentNode = null;
            var previewHolder = null;

            if (!blockEl || !doc) {
                return false;
            }

            newBlock = parseSourceToBlock(sourceHtml);
            if (!newBlock || !blockEl.parentNode) {
                return false;
            }

            sectionSourceState.isSyncingFromSource = true;
            parentNode = blockEl.parentNode;
            parentNode.insertBefore(newBlock, blockEl);
            parentNode.removeChild(blockEl);
            blockEl = newBlock;
            setCurrentBlockState(newBlock);

            syncEditorTextarea({
                keepSourcePanel: true
            });
            setCurrentBlockState(blockEl);
            previewHolder = textareaEl;
            sectionSourceState.isSyncingFromSource = false;

            if (previewHolder && previewHolder.focus) {
                previewHolder.focus();
            }

            return true;
        };
        var scheduleLiveSourcePreview = function () {
            window.clearTimeout(sectionSourceState.syncTimer);
            sectionSourceState.syncTimer = window.setTimeout(function () {
                var holder = null;
                var candidate = null;
                var index = 0;
                var parentNode = null;

                if (!textareaEl || !blockEl || !doc || !blockEl.parentNode) {
                    return;
                }

                holder = doc.createElement('div');
                holder.innerHTML = textareaEl.value;

                for (index = 0; index < holder.children.length; index += 1) {
                    if (isSortableBlock(holder.children[index])) {
                        candidate = holder.children[index];
                        break;
                    }
                }

                if (!candidate || holder.children.length !== 1) {
                    return;
                }

                sectionSourceState.isSyncingFromSource = true;
                parentNode = blockEl.parentNode;
                parentNode.insertBefore(candidate, blockEl);
                parentNode.removeChild(blockEl);
                blockEl = candidate;
                setCurrentBlockState(candidate);
                syncEditorTextarea({
                    keepSourcePanel: true
                });
                setCurrentBlockState(blockEl);
                sectionSourceState.isSyncingFromSource = false;
            }, 180);
        };

        if (!editor || !doc || !blockEl || !isSortableBlock(blockEl) || blockEl.parentNode !== editor.getBody()) {
            return;
        }

        if (isSectionSourcePanelOpenForBlock(editor, blockEl)) {
            closeSectionSourceEditor(editor);
            return;
        }

        clearActiveSection();
        if (sectionSourceState.panelEl && sectionSourceState.panelEl.parentNode) {
            sectionSourceState.panelEl.parentNode.removeChild(sectionSourceState.panelEl);
        }
        resetSectionSourceState();

        setCurrentBlockState(blockEl);

        panelEl = doc.createElement('div');
        panelEl.setAttribute('contenteditable', 'true');
        panelEl.setAttribute('data-section-editor-control', 'source-panel');
        panelEl.style.margin = '14px 0 0';
        panelEl.style.padding = '16px';
        panelEl.style.border = '1px solid #bfdbfe';
        panelEl.style.borderRadius = '18px';
        panelEl.style.background = '#eff6ff';
        panelEl.style.boxShadow = '0 12px 30px rgba(37, 99, 235, 0.10)';
        panelEl.style.userSelect = 'text';

        wrapperTitle = doc.createElement('div');
        wrapperTitle.textContent = '当前主卡片源码';
        wrapperTitle.style.color = '#1e3a8a';
        wrapperTitle.style.fontSize = '14px';
        wrapperTitle.style.fontWeight = '900';
        wrapperTitle.style.marginBottom = '10px';
        panelEl.appendChild(wrapperTitle);
        wrapperTitle.style.display = 'none';

        textareaEl = doc.createElement('textarea');
        textareaEl.setAttribute('contenteditable', 'true');
        textareaEl.setAttribute('data-section-editor-control', 'source-textarea');
        textareaEl.setAttribute('wrap', 'off');
        textareaEl.readOnly = false;
        textareaEl.disabled = false;
        textareaEl.tabIndex = 0;
        textareaEl.value = getCleanBlockHtml(blockEl);
        textareaEl.style.width = '100%';
        textareaEl.style.minHeight = '260px';
        textareaEl.style.padding = '16px 18px';
        textareaEl.style.border = '1px solid #1d4ed8';
        textareaEl.style.borderRadius = '16px';
        textareaEl.style.background = '#0f172a';
        textareaEl.style.color = '#e2e8f0';
        textareaEl.style.fontFamily = 'Consolas, "SFMono-Regular", Menlo, Monaco, "Courier New", monospace';
        textareaEl.style.fontSize = '13px';
        textareaEl.style.lineHeight = '1.8';
        textareaEl.style.boxSizing = 'border-box';
        textareaEl.style.whiteSpace = 'pre';
        textareaEl.style.overflow = 'auto';
        textareaEl.style.overflowWrap = 'normal';
        textareaEl.style.wordBreak = 'normal';
        textareaEl.style.tabSize = '4';
        textareaEl.style.resize = 'vertical';
        textareaEl.style.caretColor = '#ffffff';
        textareaEl.style.boxShadow = 'inset 0 1px 2px rgba(15, 23, 42, 0.28)';
        textareaEl.spellcheck = false;
        textareaEl.addEventListener('mousedown', function (event) {
            event.stopPropagation();
        });
        textareaEl.addEventListener('click', function (event) {
            event.stopPropagation();
        });
        textareaEl.addEventListener('focus', function (event) {
            event.stopPropagation();
        });
        textareaEl.addEventListener('keydown', function (event) {
            event.stopPropagation();
        });
        textareaEl.addEventListener('input', function () {
            scheduleLiveSourcePreview();
        });
        panelEl.appendChild(textareaEl);

        actionRow = doc.createElement('div');
        actionRow.setAttribute('data-section-editor-control', 'source-actions');
        actionRow.style.display = 'flex';
        actionRow.style.justifyContent = 'flex-end';
        actionRow.style.gap = '10px';
        actionRow.style.marginTop = '12px';

        closeButton = doc.createElement('button');
        closeButton.setAttribute('type', 'button');
        closeButton.setAttribute('contenteditable', 'false');
        closeButton.setAttribute('data-section-editor-control', 'source-close');
        closeButton.textContent = '收起源码';
        closeButton.style.padding = '0 14px';
        closeButton.style.height = '34px';
        closeButton.style.border = '1px solid #cbd5e1';
        closeButton.style.borderRadius = '999px';
        closeButton.style.background = '#ffffff';
        closeButton.style.color = '#334155';
        closeButton.style.fontWeight = '800';
        bindSectionActionButton(closeButton, function () {
            closeSectionSourceEditor(editor);
        });

        applyButton = doc.createElement('button');
        applyButton.setAttribute('type', 'button');
        applyButton.setAttribute('contenteditable', 'false');
        applyButton.setAttribute('data-section-editor-control', 'source-apply');
        applyButton.textContent = '应用源码';
        applyButton.style.padding = '0 16px';
        applyButton.style.height = '34px';
        applyButton.style.border = '0';
        applyButton.style.borderRadius = '999px';
        applyButton.style.background = 'linear-gradient(135deg, #2563eb, #1d4ed8)';
        applyButton.style.color = '#ffffff';
        applyButton.style.fontWeight = '900';
        applyButton.style.boxShadow = '0 8px 18px rgba(37, 99, 235, 0.24)';
        bindSectionActionButton(applyButton, function () {
            applySourceChanges();
        });

        actionRow.appendChild(closeButton);
        panelEl.appendChild(actionRow);
        actionRow.style.display = 'none';

        blockEl.parentNode.insertBefore(panelEl, blockEl.nextSibling);

        sectionSourceState.editor = editor;
        sectionSourceState.blockEl = blockEl;
        sectionSourceState.panelEl = panelEl;
        sectionSourceState.textareaEl = textareaEl;
        refreshSectionSourceButtons(editor);

        if (typeof window !== 'undefined' && typeof window.setTimeout === 'function') {
            window.setTimeout(function () {
                if (!textareaEl || typeof textareaEl.focus !== 'function') {
                    return;
                }
                textareaEl.focus();
            }, 0);
        }
    };

    var hasCompleteSectionEditorControls = function (titledBlocks, blocks) {
        var index = 0;
        var wrap = null;
        var titleEl = null;

        for (index = 0; index < titledBlocks.length; index += 1) {
            wrap = titledBlocks[index].blockEl ? titledBlocks[index].blockEl.querySelector('[data-section-editor-control="floating-handle-wrap"]') : null;
            if (
                !wrap ||
                !wrap.querySelector('[data-section-editor-control="floating-handle"]') ||
                !wrap.querySelector('[data-section-editor-control="floating-button"]') ||
                !wrap.querySelector('[data-section-editor-control="floating-source-button"]') ||
                !wrap.querySelector('[data-section-editor-control="floating-visibility-button"]') ||
                !wrap.querySelector('[data-section-editor-control="floating-delete-button"]')
            ) {
                return false;
            }
        }

        for (index = 0; index < blocks.length; index += 1) {
            if (blocks[index].querySelector('.section-title')) {
                continue;
            }

            wrap = blocks[index].querySelector('[data-section-editor-control="floating-handle-wrap"]');
            if (
                !wrap ||
                !wrap.querySelector('[data-section-editor-control="floating-handle"]') ||
                !wrap.querySelector('[data-section-editor-control="floating-button"]') ||
                !wrap.querySelector('[data-section-editor-control="floating-source-button"]') ||
                !wrap.querySelector('[data-section-editor-control="floating-visibility-button"]') ||
                !wrap.querySelector('[data-section-editor-control="floating-delete-button"]')
            ) {
                return false;
            }
        }

        return true;
    };

    var ensureSectionEditorButtons = function (editor, options) {
        var body = editor ? editor.getBody() : null;
        var doc = editor ? editor.getDoc() : null;
        var blocks = body ? getSortableBlocks(body) : [];
        var titledBlocks = body ? getSortableBlocksWithDirectTitles(body) : [];
        var signature = body ? getSectionEditorStructureSignature(body) : '';
        var existingControlCount = body ? body.querySelectorAll('[data-section-editor-control="handle"], [data-section-editor-control="floating-handle"]').length : 0;
        var controlsComplete = false;
        var index = 0;
        var handleButton = null;
        var floatingHandleWrap = null;
        var button = null;
        var sourceButton = null;
        var visibilityButton = null;
        var deleteButton = null;

        if (!body || !doc) {
            return;
        }

        sectionEditorControlsActivated = true;
        ensureSectionEditorPreviewStyles(editor);

        if (!options || !options.force) {
            if (body._sectionEditorControlsSignature === signature && (existingControlCount > 0 || blocks.length === 0)) {
                return;
            }
        }

        controlsComplete = hasCompleteSectionEditorControls(titledBlocks, blocks);

        if (!options || !options.force) {
            if (body._sectionEditorControlsSignature === signature && controlsComplete && (existingControlCount > 0 || blocks.length === 0)) {
                return;
            }
        }

        removeSectionEditorControls(body, options);

        for (index = 0; index < blocks.length; index += 1) {
            blocks[index].classList.add('editor-sortable-block');
            getSectionEditorBlockId(blocks[index]);
        }

        for (index = 0; index < titledBlocks.length; index += 1) {
            var currentTitle = titledBlocks[index].titleEl;
            var currentBlock = titledBlocks[index].blockEl;

            handleButton = doc.createElement('button');
            handleButton.setAttribute('type', 'button');
            handleButton.setAttribute('contenteditable', 'false');
            handleButton.setAttribute('data-section-editor-control', 'handle');
            handleButton.setAttribute('data-mce-bogus', '1');
            handleButton.className = 'editor-section-drag-handle';
            handleButton.style.userSelect = 'none';
            handleButton.style.touchAction = 'none';
            handleButton.setAttribute('title', '拖拽排序');
            handleButton.innerHTML = '<span class="editor-section-drag-handle-icon" aria-hidden="true">⋮⋮</span><span class="editor-section-drag-handle-label">拖拽</span>';

            bindSectionDragButton(handleButton, editor, currentBlock, currentTitle);

            button = doc.createElement('button');
            button.setAttribute('type', 'button');
            button.setAttribute('contenteditable', 'false');
            button.setAttribute('data-section-editor-control', 'button');
            button.setAttribute('data-mce-bogus', '1');
            button.setAttribute('data-section-editor-block-id', getSectionEditorBlockId(currentBlock));
            button.className = 'editor-section-edit-button';
            enhanceSectionEditButton(button);
            button.textContent = '编辑';

            button.textContent = '编辑';
            bindSectionActionButton(button, (function (currentTitle) {
                return function (event) {
                    openSectionEditor(editor, currentTitle);
                };
            })(currentTitle));

            sourceButton = doc.createElement('button');
            sourceButton.setAttribute('type', 'button');
            sourceButton.setAttribute('contenteditable', 'false');
            sourceButton.setAttribute('data-section-editor-control', 'source-button');
            sourceButton.setAttribute('data-mce-bogus', '1');
            sourceButton.setAttribute('data-section-editor-block-id', getSectionEditorBlockId(currentBlock));
            sourceButton.className = 'editor-section-edit-button editor-section-source-button';
            enhanceSectionSourceButton(sourceButton);
            sourceButton.textContent = '源码';
            bindSectionActionButton(sourceButton, (function (currentBlock) {
                return function (event) {
                    showSectionSourceEditor(editor, currentBlock);
                };
            })(currentBlock));

            visibilityButton = doc.createElement('button');
            visibilityButton.setAttribute('type', 'button');
            visibilityButton.setAttribute('contenteditable', 'false');
            visibilityButton.setAttribute('data-section-editor-control', 'visibility-button');
            visibilityButton.setAttribute('data-mce-bogus', '1');
            visibilityButton.setAttribute('data-section-editor-block-id', getSectionEditorBlockId(currentBlock));
            visibilityButton.className = 'editor-section-edit-button editor-section-visibility-button';
            enhanceSectionVisibilityButton(visibilityButton);
            applySectionVisibilityButtonState(visibilityButton, isSectionBlockHidden(currentBlock));
            bindSectionActionButton(visibilityButton, (function (currentBlock) {
                return function (event) {
                    toggleSectionBlockHidden(editor, currentBlock);
                };
            })(currentBlock));

            deleteButton = doc.createElement('button');
            deleteButton.setAttribute('type', 'button');
            deleteButton.setAttribute('contenteditable', 'false');
            deleteButton.setAttribute('data-section-editor-control', 'delete-button');
            deleteButton.setAttribute('data-mce-bogus', '1');
            deleteButton.setAttribute('data-section-editor-block-id', getSectionEditorBlockId(currentBlock));
            deleteButton.className = 'editor-section-edit-button editor-section-delete-button';
            enhanceSectionDeleteButton(deleteButton);
            deleteButton.textContent = '删除';
            bindSectionActionButton(deleteButton, (function (currentBlock) {
                return function (event) {
                    removeSectionBlock(editor, currentBlock);
                };
            })(currentBlock));

            floatingHandleWrap = doc.createElement('div');
            floatingHandleWrap.setAttribute('contenteditable', 'false');
            floatingHandleWrap.setAttribute('data-section-editor-control', 'floating-handle-wrap');
            floatingHandleWrap.setAttribute('data-mce-bogus', '1');
            floatingHandleWrap.className = 'editor-section-floating-handle-row';
            floatingHandleWrap.style.display = 'inline-flex';
            floatingHandleWrap.style.alignItems = 'center';
            floatingHandleWrap.style.gap = '8px';
            floatingHandleWrap.style.pointerEvents = 'auto';

            handleButton.setAttribute('data-section-editor-control', 'floating-handle');
            handleButton.className = 'editor-section-drag-handle editor-section-drag-handle--floating';
            button.setAttribute('data-section-editor-control', 'floating-button');
            button.className = 'editor-section-edit-button editor-section-edit-button--floating';
            sourceButton.setAttribute('data-section-editor-control', 'floating-source-button');
            sourceButton.className = 'editor-section-edit-button editor-section-source-button editor-section-source-button--floating';
            visibilityButton.setAttribute('data-section-editor-control', 'floating-visibility-button');
            visibilityButton.className = 'editor-section-edit-button editor-section-visibility-button editor-section-visibility-button--floating';
            deleteButton.setAttribute('data-section-editor-control', 'floating-delete-button');
            deleteButton.className = 'editor-section-edit-button editor-section-delete-button editor-section-delete-button--floating';

            floatingHandleWrap.appendChild(handleButton);
            floatingHandleWrap.appendChild(button);
            floatingHandleWrap.appendChild(sourceButton);
            floatingHandleWrap.appendChild(visibilityButton);
            floatingHandleWrap.appendChild(deleteButton);
            currentBlock.insertBefore(floatingHandleWrap, currentBlock.firstChild);
        }

        for (index = 0; index < blocks.length; index += 1) {
            if (blocks[index].querySelector('.section-title')) {
                continue;
            }

            blocks[index].classList.add('editor-sortable-block--floating');

            floatingHandleWrap = doc.createElement('div');
            floatingHandleWrap.setAttribute('contenteditable', 'false');
            floatingHandleWrap.setAttribute('data-section-editor-control', 'floating-handle-wrap');
            floatingHandleWrap.setAttribute('data-mce-bogus', '1');
            floatingHandleWrap.className = 'editor-section-floating-handle-row';
            floatingHandleWrap.style.display = 'inline-flex';
            floatingHandleWrap.style.alignItems = 'center';
            floatingHandleWrap.style.gap = '8px';
            floatingHandleWrap.style.pointerEvents = 'auto';

            handleButton = doc.createElement('button');
            handleButton.setAttribute('type', 'button');
            handleButton.setAttribute('contenteditable', 'false');
            handleButton.setAttribute('data-section-editor-control', 'floating-handle');
            handleButton.setAttribute('data-mce-bogus', '1');
            handleButton.className = 'editor-section-drag-handle editor-section-drag-handle--floating';
            handleButton.style.userSelect = 'none';
            handleButton.style.touchAction = 'none';
            handleButton.style.pointerEvents = 'auto';
            handleButton.setAttribute('title', '拖拽排序');
            handleButton.innerHTML = '<span class="editor-section-drag-handle-icon" aria-hidden="true">⋮⋮</span><span class="editor-section-drag-handle-label">拖拽</span>';

            bindSectionDragButton(handleButton, editor, blocks[index], null);

            floatingHandleWrap.appendChild(handleButton);

            button = doc.createElement('button');
            button.setAttribute('type', 'button');
            button.setAttribute('contenteditable', 'false');
            button.setAttribute('data-section-editor-control', 'floating-button');
            button.setAttribute('data-mce-bogus', '1');
            button.setAttribute('data-section-editor-block-id', getSectionEditorBlockId(blocks[index]));
            button.className = 'editor-section-edit-button editor-section-edit-button--floating';
            enhanceSectionEditButton(button);
            button.style.display = 'inline-flex';
            button.textContent = '编辑';
            button.style.pointerEvents = 'auto';

            button.textContent = '编辑';
            bindSectionActionButton(button, (function (currentBlock) {
                return function (event) {
                    openSectionEditor(editor, currentBlock);
                };
            })(blocks[index]));

            sourceButton = doc.createElement('button');
            sourceButton.setAttribute('type', 'button');
            sourceButton.setAttribute('contenteditable', 'false');
            sourceButton.setAttribute('data-section-editor-control', 'floating-source-button');
            sourceButton.setAttribute('data-mce-bogus', '1');
            sourceButton.setAttribute('data-section-editor-block-id', getSectionEditorBlockId(blocks[index]));
            sourceButton.className = 'editor-section-edit-button editor-section-source-button editor-section-source-button--floating';
            enhanceSectionSourceButton(sourceButton);
            sourceButton.style.display = 'inline-flex';
            sourceButton.style.pointerEvents = 'auto';
            sourceButton.textContent = '源码';
            bindSectionActionButton(sourceButton, (function (currentBlock) {
                return function (event) {
                    showSectionSourceEditor(editor, currentBlock);
                };
            })(blocks[index]));

            visibilityButton = doc.createElement('button');
            visibilityButton.setAttribute('type', 'button');
            visibilityButton.setAttribute('contenteditable', 'false');
            visibilityButton.setAttribute('data-section-editor-control', 'floating-visibility-button');
            visibilityButton.setAttribute('data-mce-bogus', '1');
            visibilityButton.setAttribute('data-section-editor-block-id', getSectionEditorBlockId(blocks[index]));
            visibilityButton.className = 'editor-section-edit-button editor-section-visibility-button editor-section-visibility-button--floating';
            enhanceSectionVisibilityButton(visibilityButton);
            visibilityButton.style.display = 'inline-flex';
            visibilityButton.style.pointerEvents = 'auto';
            applySectionVisibilityButtonState(visibilityButton, isSectionBlockHidden(blocks[index]));
            bindSectionActionButton(visibilityButton, (function (currentBlock) {
                return function (event) {
                    toggleSectionBlockHidden(editor, currentBlock);
                };
            })(blocks[index]));

            deleteButton = doc.createElement('button');
            deleteButton.setAttribute('type', 'button');
            deleteButton.setAttribute('contenteditable', 'false');
            deleteButton.setAttribute('data-section-editor-control', 'floating-delete-button');
            deleteButton.setAttribute('data-mce-bogus', '1');
            deleteButton.setAttribute('data-section-editor-block-id', getSectionEditorBlockId(blocks[index]));
            deleteButton.className = 'editor-section-edit-button editor-section-delete-button editor-section-delete-button--floating';
            enhanceSectionDeleteButton(deleteButton);
            deleteButton.style.display = 'inline-flex';
            deleteButton.style.pointerEvents = 'auto';
            deleteButton.textContent = '删除';
            bindSectionActionButton(deleteButton, (function (currentBlock) {
                return function (event) {
                    removeSectionBlock(editor, currentBlock);
                };
            })(blocks[index]));

            floatingHandleWrap.appendChild(button);
            floatingHandleWrap.appendChild(sourceButton);
            floatingHandleWrap.appendChild(visibilityButton);
            floatingHandleWrap.appendChild(deleteButton);
            blocks[index].insertBefore(floatingHandleWrap, blocks[index].firstChild);
        }

        body._sectionEditorControlsSignature = signature;
        refreshSectionEditLockStates(body);
        refreshSectionSourceButtons(editor);
        refreshSectionVisibilityButtons(editor);
    };

    var applySectionEditorChanges = function () {
        var editor = activeSectionState.editor || window.tinymce.get('draw-material-editor');
        var blockEl = activeSectionState.blockEl;
        var titleEl = activeSectionState.titleEl;
        var bodyEl = activeSectionState.bodyEl;
        var styleTargetEl = activeSectionState.styleTargetEl;
        var textTargetEl = activeSectionState.textTargetEl;
        var titleStart = sectionEditorTitleStart ? sectionEditorTitleStart.value : '';
        var titleEnd = sectionEditorTitleEnd ? sectionEditorTitleEnd.value : '';
        var titleAlign = normalizeTitleAlign(sectionEditorTitleAlign ? sectionEditorTitleAlign.value : 'left');
        var isMarquee = activeSectionState.blockType === 'marquee';
        var isHeaderSection = isSectionEditorHeaderBlock(blockEl);
        var adItems = getSectionAdItems({
            bodyEl: bodyEl
        });
        var isAdSection = adItems.length > 0;
        var isExpertSection = isExpertSectionNodes({
            blockEl: blockEl,
            titleEl: titleEl,
            bodyEl: bodyEl
        });
        var cardFontSize = sectionEditorCardFontSize ? (sectionEditorCardFontSize.value || '').trim() : '';
        var cardTextColor = sectionEditorCardTextColor ? (sectionEditorCardTextColor.value || '').trim() : '';

        if (!blockEl) {
            return;
        }

        applySectionEditLockState(blockEl, !!(sectionEditorEditLock && sectionEditorEditLock.checked));

        if (titleEl) {
            titleEl.className = sanitizeTitleClassName(
                sectionEditorTitleClass ? sectionEditorTitleClass.value : titleEl.className,
                isMarquee
            );
            titleEl.innerHTML = getSectionTitleTextFieldVisible()
                ? buildSectionTitleHtmlWithText(titleEl, sectionEditorTitleText ? sectionEditorTitleText.value : '', sectionEditorTitleIcon ? sectionEditorTitleIcon.value : '')
                : (sectionEditorTitleHtml ? sectionEditorTitleHtml.value : titleEl.innerHTML);
            if (sectionEditorTitleHtml) {
                sectionEditorTitleHtml.value = getCleanNodeHtml(titleEl);
            }
            setInlineStyle(titleEl, sectionEditorTitleStyle ? sectionEditorTitleStyle.value : '');

            if (sectionEditorTitleColor && sectionEditorTitleColor.value) {
                titleEl.style.color = sectionEditorTitleColor.value;
            } else {
                titleEl.style.removeProperty('color');
            }

            applyTitleBackgroundColors(titleEl, titleStart, titleEnd);
            applyTitleAlign(titleEl, titleAlign);
        }

        if (isMarquee && sectionEditorMarqueeText) {
            setMarqueeText(blockEl, sectionEditorMarqueeText.value);
        }

        if (isAdSection) {
            adItems = ensureSectionAdItemElements(bodyEl, sectionEditorAdCopyInputs.length);
            applySectionEditorAdCopyFields(adItems);
            applySectionAdItemBorderColor(adItems, getSectionEditorAdItemBorderColor());
        }

        if (isExpertSection) {
            var expertAdEntries = compactSectionExpertAdEntries(readSectionEditorExpertAdEntries());
            applySectionEditorExpertAdFields(blockEl);
            syncSectionEditorExpertAdRows(
                bodyEl,
                resolveSectionExpertAdEffectiveInterval(sectionEditorExpertAdInterval ? sectionEditorExpertAdInterval.value : '', expertAdEntries),
                expertAdEntries
            );
        } else {
            clearSectionEditorExpertAdFields(blockEl);
        }

        if (isHeaderSection) {
            if (sectionEditorCardStyle) {
                sectionEditorCardStyle.value = mergeHeaderSlotSizeStyleText(
                    sectionEditorCardStyle.value,
                    sectionEditorCardSlotWidth ? sectionEditorCardSlotWidth.value : 708,
                    sectionEditorCardSlotHeight ? sectionEditorCardSlotHeight.value : 286
                );
            }

            if (styleTargetEl && sectionEditorCardStyle) {
                setInlineStyle(styleTargetEl, sectionEditorCardStyle.value);
            }

            if (bodyEl && sectionEditorBodyHtml) {
                bodyEl.innerHTML = sectionEditorBodyHtml.value;
            }

        }

        closeSectionEditor();
        ensureSectionEditorButtons(editor || window.tinymce.get('draw-material-editor'), {
            force: true
        });
        syncEditorTextarea();
    };

    var submitDrawFormAjax = function (editor) {
        var formData = null;

        if (!form || !window.fetch || typeof window.FormData === 'undefined') {
            return false;
        }

        if (saveRequestState.pending) {
            return true;
        }

        fullscreenState.isSubmitting = true;
        saveRequestState.pending = true;
        setSaveButtonBusy(true);
        syncFullscreenPreferenceFromState();

        if (editor) {
            syncEditorTextarea();
        } else if (window.tinymce) {
            window.tinymce.triggerSave();
        }

        formData = new FormData(form);
        formData.set('_response_format', 'json');

        fetch(form.getAttribute('action') || window.location.href, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            }
        }).then(function (response) {
            return response.json().catch(function () {
                return {};
            }).then(function (payload) {
                if (!response.ok || !payload || payload.success !== true) {
                    throw new Error((payload && payload.message) || '保存失败，请稍后重试。');
                }

                return payload;
            });
        }).then(function (payload) {
            fullscreenState.isSubmitting = false;
            saveRequestState.pending = false;
            setSaveButtonBusy(false);
            showDrawEditorNotice((payload && payload.message) || '资料内容已保存。', 'success');
        }).catch(function (error) {
            fullscreenState.isSubmitting = false;
            saveRequestState.pending = false;
            setSaveButtonBusy(false);
            showDrawEditorNotice(error && error.message ? error.message : '保存失败，请稍后重试。', 'error');
        });

        return true;
    };

    var getInsertableSectionTemplate = function (templateType) {
        if (templateType === 'ad') {
            return [
                '<section class="max-w-7xl mx-auto px-[14px] pt-[10px] pb-0 bottom-nav-target">',
                '<div class="section-title is-template-title-ad" data-title-bg-start="#34d399" data-title-bg-end="#b4db29"><i class="fa-solid fa-bullhorn"></i>\u5e7f\u544a\u533a</div>',
                '<div class="grid gap-2">',
                '<div class="ad-item is-template-border"><span class="issue-prefix issue-prefix-ad"></span><span class="ad-item-lead">\u6fb3\u95e8\u591a\u5b9d</span><span class="ad-item-middle">\u3010\u4e09\u8096\u4e8c\u8fde\u3011</span><span class="ad-item-tail">\u5f3a\u529b\u63a8\u8350</span></div>',
                '<div class="ad-item is-template-border"><span class="issue-prefix issue-prefix-ad"></span><span class="ad-item-lead">\u5929\u964d\u6a2a\u8d22</span><span class="ad-item-middle">\u3010\u2460\u5934\u2461\u5c3e\u3011</span><span class="ad-item-tail">\u4e00\u51fb\u547d\u4e2d</span></div>',
                '<div class="ad-item is-template-border"><span class="issue-prefix issue-prefix-ad"></span><span class="ad-item-lead">\u516d\u5408\u96c4\u9738</span><span class="ad-item-middle">\u3010\u5929\u5730\u4eba\u8096\u3011</span><span class="ad-item-tail">\u5168\u7f51\u72ec\u5bb6</span></div>',
                '</div>',
                '</section>'
            ].join('');
        }

        if (templateType === 'expert') {
            return [
                '<section class="max-w-7xl mx-auto px-[14px] pt-[10px] pb-0 bottom-nav-target">',
                '<div class="section-title is-template-title-expert" data-title-bg-start="#f59e0b" data-title-bg-end="#ef4444"><i class="fa-solid fa-crown"></i>\u9ad8\u624b\u699c\u533a</div>',
                '<div class="data-frame space-y-3">',
                '<div class="flex justify-between items-center bg-white p-4 rounded-xl"><span class="expert-item-main"><span class="issue-prefix issue-prefix-expert"></span><span>\u72ec\u80c6\u738b</span></span><span class="admin-template-score is-red">12\u4e2d8</span></div>',
                '<div class="flex justify-between items-center bg-white p-4 rounded-xl"><span class="expert-item-main"><span class="issue-prefix issue-prefix-expert"></span><span>\u4e09\u4e2d\u4e09\u9ad8\u624b</span></span><span class="admin-template-score is-blue">9\u4e2d6</span></div>',
                '<div class="flex justify-between items-center bg-white p-4 rounded-xl"><span class="expert-item-main"><span class="issue-prefix issue-prefix-expert"></span><span>\u7279\u7801\u5b9a\u4f4d</span></span><span class="admin-template-score is-green">7\u4e2d5</span></div>',
                '</div>',
                '</section>'
            ].join('');
        }

        return [
            '<section class="max-w-7xl mx-auto px-[14px] pt-[10px] pb-0 bottom-nav-target">',
            '<div class="section-title is-template-title-data" data-title-bg-start="#1e40af" data-title-bg-end="#3b82f6"><i class="fa-solid fa-database"></i>\u8d44\u6599\u533a</div>',
            '<div class="data-frame">',
            '<p>\u8d44\u6599\u6807\u9898\u4e00 \u8fd9\u91cc\u586b\u5199\u5185\u5bb9</p>',
            '<p>\u8d44\u6599\u6807\u9898\u4e8c \u652f\u6301\u7ee7\u7eed\u7f16\u8f91</p>',
            '<p>\u8d44\u6599\u6807\u9898\u4e09 \u53ef\u76f4\u63a5\u66ff\u6362\u6587\u6848</p>',
            '</div>',
            '</section>'
        ].join('');
    };

    var insertEditorSectionTemplate = function (editor, templateType, successText) {
        var html = '';
        var body = null;
        var doc = null;
        var anchor = null;
        var anchorParent = null;
        var container = null;
        var nodes = [];
        var nextNode = null;

        if (!editor) {
            return;
        }

        html = getInsertableSectionTemplate(templateType);
        if (!html) {
            return;
        }

        body = editor.getBody();
        doc = editor.getDoc();
        anchor = body && body.querySelector ? body.querySelector('section.calendar-panel') : null;
        anchorParent = anchor && anchor.parentNode ? anchor.parentNode : null;

        if (doc && anchor && anchorParent) {
            container = doc.createElement('div');
            container.innerHTML = html;
            nodes = Array.prototype.slice.call(container.childNodes || []).filter(function (node) {
                return node && (node.nodeType === 1 || (node.nodeType === 3 && /\S/.test(node.textContent || '')));
            });
            nextNode = anchor.nextSibling;

            editor.undoManager.transact(function () {
                nodes.forEach(function (node) {
                    anchorParent.insertBefore(node, nextNode);
                });
            });
        } else {
            editor.focus();
            editor.undoManager.transact(function () {
                editor.insertContent(html);
            });
        }

        ensureSectionEditorButtons(editor, {
            force: true
        });
        syncEditorTextarea();
        showDrawEditorNotice(successText || '\u533a\u5757\u5df2\u63d2\u5165', 'success');
    };

    var initDrawMaterialEditor = function () {
        if (initDrawMaterialEditor.started || fullscreenState.isNavigatingAway) {
            return;
        }

        initDrawMaterialEditor.started = true;
        window.tinymce.init({
        selector: '#draw-material-editor',
        license_key: 'gpl',
        language: 'zh-CN',
        language_url: '<?php echo e(asset('vendor/tinymce/langs/zh-CN.js?v=8.4.0-local')); ?>',
        height: 680,
        min_height: 680,
        content_css: [
            '<?php echo e(asset('vendor/fontawesome/css/all.min.css?v=20260621-front-fa-sync-01')); ?>',
            '<?php echo e(public_url('styles/style.css?v=20260629-brand-gap-02')); ?>',
            '<?php echo e(public_url('styles/home-editor-preview.css?v=20260630-component-controls-01')); ?>'
        ],
        body_class: '<?php echo e($drawEditorBodyClass); ?>',
        font_family_formats: '微软雅黑=Microsoft YaHei,PingFang SC,sans-serif;' +
            '宋体=SimSun,Songti SC,serif;' +
            '黑体=SimHei,Heiti SC,sans-serif;' +
            '楷体=KaiTi,Kaiti SC,serif;' +
            '仿宋=FangSong,serif;' +
            '等线=DengXian,sans-serif;' +
            '幼圆=YouYuan,sans-serif;' +
            '无衬线=Arial,Helvetica,sans-serif;' +
            '衬线=Times New Roman,Times,serif;' +
            '等宽=Courier New,Courier,monospace',
        menubar: 'file edit view insert format table tools sectiontools help',
        plugins: '<?php echo $drawMode === 'material'
            ? 'advlist autolink lists link image table code preview searchreplace fullscreen charmap anchor'
            : 'advlist autolink lists link image table code preview searchreplace fullscreen wordcount autoresize charmap anchor'; ?>',
        menu: {
            sectiontools: {
                title: '\u65b0\u589e\u533a',
                items: 'insertdatasection insertadsection insertexpertsection'
            }
        },
        toolbar_mode: 'wrap',
        toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | removeformat | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | code preview fullscreen',
        automatic_uploads: true,
        file_picker_types: 'image',
        images_file_types: 'jpg,jpeg,png,gif,webp,bmp',
        promotion: false,
        branding: false,
        convert_urls: false,
        verify_html: false,
        images_upload_handler: function (blobInfo, progress) {
            var editor = window.tinymce.activeEditor || window.tinymce.get('draw-material-editor');

            return uploadEditorImageFile(blobInfo.blob(), editor, progress).then(function (payload) {
                return payload.location;
            });
        },
        file_picker_callback: function (callback, value, meta) {
            var editor = window.tinymce.activeEditor || window.tinymce.get('draw-material-editor');

            if (!meta || meta.filetype !== 'image') {
                return;
            }

            openEditorImagePicker(callback, editor);
        },
        setup: function (editor) {
            editor.ui.registry.addMenuItem('insertdatasection', {
                text: '\u63d2\u5165\u8d44\u6599\u533a',
                onAction: function () {
                    insertEditorSectionTemplate(editor, 'data', '\u8d44\u6599\u533a\u5df2\u63d2\u5165');
                }
            });

            editor.ui.registry.addMenuItem('insertadsection', {
                text: '\u63d2\u5165\u5e7f\u544a\u533a',
                onAction: function () {
                    insertEditorSectionTemplate(editor, 'ad', '\u5e7f\u544a\u533a\u5df2\u63d2\u5165');
                }
            });

            editor.ui.registry.addMenuItem('insertexpertsection', {
                text: '\u63d2\u5165\u9ad8\u624b\u699c\u533a',
                onAction: function () {
                    insertEditorSectionTemplate(editor, 'expert', '\u9ad8\u624b\u699c\u533a\u5df2\u63d2\u5165');
                }
            });

            editor.on('init', function () {
                var body = editor.getBody();
                var root = editor.getDoc() ? editor.getDoc().documentElement : null;

                if (body) {
                    body.setAttribute('data-region', '<?php echo e($currentRegion); ?>');
                    body.classList.remove('mce-visualblocks');
                }

                if (root) {
                    root.setAttribute('data-region', '<?php echo e($currentRegion); ?>');
                }

                clearDrawEditorBootState();

                startAdminPreviewLiveTimer(editor);
                scheduleEditorDecorationRefresh(editor, {
                    refreshControls: sectionEditorControlsActivated
                });
                scheduleDrawStickyLayout(editor);

                if (fullscreenState.shouldRestore) {
                    fullscreenState.isRestoring = true;
                    window.setTimeout(function () {
                        applyEditorFullscreenState(editor, true);
                    }, 0);
                } else {
                    setPendingFullscreenShell(false);
                }
            });

            editor.on('ExecCommand', function (event) {
                if (event && event.command === 'mceFullScreen') {
                    fullscreenState.explicitToggle = true;
                }
            });

            editor.on('FullscreenStateChanged', function (event) {
                var isFullscreen = !!event.state;

                fullscreenState.isFullscreen = isFullscreen;
                document.body.classList.toggle('draw-editor-is-fullscreen', isFullscreen);
                ensureManualFontSizeInput(editor);
                syncManualFontSizeInput(editor);

                if (isFullscreen) {
                    writeFullscreenPreference(true);
                } else if (fullscreenState.explicitToggle && !fullscreenState.isSubmitting && !fullscreenState.isNavigatingAway && !fullscreenState.isRestoring) {
                    writeFullscreenPreference(false);
                }

                setPendingFullscreenShell(false);

                if (fullscreenState.isRestoring && isFullscreen) {
                    fullscreenState.isRestoring = false;
                }

                fullscreenState.explicitToggle = false;

                if (isFullscreen) {
                    mountFullscreenLiveSyncBar(editor);
                } else {
                    removeFullscreenLiveSyncBar();
                }

                if (!saveButton) {
                    return;
                }

                if (isFullscreen) {
                    var menubar = editor.getContainer() ? editor.getContainer().querySelector('.tox-menubar') : null;
                    var header = editor.getContainer() ? editor.getContainer().querySelector('.tox-editor-header') : null;

                    if (menubar) {
                        saveButton.classList.remove('is-in-page-title-shell');
                        saveButton.classList.remove('is-in-editor-header');
                        saveButton.classList.add('is-in-editor-menubar');
                        menubar.appendChild(saveButton);
                    } else if (header) {
                        saveButton.classList.remove('is-in-page-title-shell');
                        saveButton.classList.remove('is-in-editor-menubar');
                        saveButton.classList.add('is-in-editor-header');
                        header.appendChild(saveButton);
                    }

                    return;
                }

                restoreSaveButton();
                scheduleDrawStickyLayout(editor);
            });

            editor.on('remove', function () {
                stopAdminPreviewLiveTimer();
                setPendingFullscreenShell(false);
                document.body.classList.remove('draw-editor-is-fullscreen');
                resetSectionDragState();
                closeSectionEditor();
                removeFullscreenLiveSyncBar();
                resetDrawEditorHeaderFloating(editor);
                restoreSaveButton();
                scheduleDrawStickyLayout(editor);
            });

            editor.on('SetContent undo redo', function () {
                refreshDrawEditorDecorations(editor, {
                    refreshControls: sectionEditorControlsActivated
                });
            });

            editor.on('change', function () {
                refreshDrawEditorDecorations(editor, {
                    refreshControls: false
                });
            });

            editor.on('input', function () {
                ensureManualFontSizeInput(editor);
                syncManualFontSizeInput(editor);
            });

            editor.on('keyup NodeChange', function () {
                syncManualFontSizeInput(editor);
            });

            ['pointerdown', 'mousedown', 'touchstart', 'click'].forEach(function (eventName) {
                editor.on(eventName, function (event) {
                    var target = event.target || null;

                    if (!sectionEditorControlsActivated) {
                        activateSectionEditorControls(editor);
                    }

                    if (dispatchSectionEditorControlEvent(editor, event, eventName)) {
                        return;
                    }

                    if (eventName !== 'click') {
                        return;
                    }

                    if (target && target.nodeType === 3) {
                        target = target.parentNode;
                    }

                    if (!target || !target.closest) {
                        return;
                    }

                    if (target.closest('[data-section-editor-control]')) {
                        return;
                    }
                });
            });

            editor.on('focus', function () {
                if (!sectionEditorControlsActivated) {
                    activateSectionEditorControls(editor);
                }
            });
        }
        });
    };

    var scheduleDrawMaterialEditorInit = function () {
        var start = function () {
            if (fullscreenState.isNavigatingAway) {
                return;
            }

            initDrawMaterialEditor();
        };

        if (document.body) {
            document.body.classList.add('admin-draws-editor-booting');
        }

        if (fullscreenState.shouldRestore) {
            window.setTimeout(start, 0);
            return;
        }

        if (window.requestAnimationFrame) {
            window.requestAnimationFrame(function () {
                window.setTimeout(start, 20);
            });
            return;
        }

        window.setTimeout(start, 20);
    };

    scheduleDrawMaterialEditorInit();

    if (sectionEditorForm) {
        sectionEditorForm.addEventListener('submit', function (event) {
            event.preventDefault();
            applySectionEditorChanges();
        });
    }

    if (sectionEditorCloseButtons && sectionEditorCloseButtons.length) {
        Array.prototype.forEach.call(sectionEditorCloseButtons, function (button) {
            button.addEventListener('click', function () {
                closeSectionEditor();
            });
        });
    }

    if (sectionEditorAdCopyAddButton) {
        sectionEditorAdCopyAddButton.addEventListener('click', function () {
            var entries = readSectionEditorAdCopyEntries();

            entries.push(createEmptySectionEditorAdCopyEntry());
            ensureSectionEditorAdCopyFields(entries.length);
            writeSectionEditorAdCopyEntries(entries);

            if (sectionEditorAdCopyInputs.length && sectionEditorAdCopyInputs[sectionEditorAdCopyInputs.length - 1].lead) {
                sectionEditorAdCopyInputs[sectionEditorAdCopyInputs.length - 1].lead.focus();
            }
        });
    }

    if (sectionEditorExpertAdAddButton) {
        sectionEditorExpertAdAddButton.addEventListener('click', function () {
            var entries = readSectionEditorExpertAdEntries();

            entries.push(createEmptySectionEditorExpertAdEntry());
            ensureSectionEditorExpertAdFields(entries.length);
            writeSectionEditorExpertAdEntries(entries);

            if (sectionEditorExpertAdInputs.length && sectionEditorExpertAdInputs[sectionEditorExpertAdInputs.length - 1].lead) {
                sectionEditorExpertAdInputs[sectionEditorExpertAdInputs.length - 1].lead.focus();
            }
        });
    }

    if (sectionEditorHeaderBackgroundUploadButton) {
        sectionEditorHeaderBackgroundUploadButton.addEventListener('click', function () {
            var editor = activeSectionState.editor || (window.tinymce ? window.tinymce.get('draw-material-editor') : null);
            var originalText = sectionEditorHeaderBackgroundUploadButton.textContent;

            openEditorImagePicker(function (location) {
                if (!sectionEditorCardStyle) {
                    return;
                }

                sectionEditorCardStyle.value = mergeHeaderBackgroundStyleText(
                    sectionEditorCardStyle.value,
                    location,
                    sectionEditorCardBackgroundOverlay ? sectionEditorCardBackgroundOverlay.value : extractHeaderBackgroundOverlayPercent(sectionEditorCardStyle.value)
                );
                refreshSectionEditorHeaderBackgroundControls();
            }, editor, {
                prepareFile: prepareHeaderBackgroundUploadFile,
                onStart: function () {
                    sectionEditorHeaderBackgroundUploadButton.disabled = true;
                    sectionEditorHeaderBackgroundUploadButton.textContent = '处理中...';
                    showDrawEditorNotice('图片处理中，请稍候...', 'info');
                },
                onSuccess: function () {
                    showDrawEditorNotice('图片已上传，请点击保存生效。', 'success');
                },
                onFinish: function () {
                    sectionEditorHeaderBackgroundUploadButton.disabled = false;
                    sectionEditorHeaderBackgroundUploadButton.textContent = originalText || '上传更换背景';
                }
            });
        });
    }

    if (sectionEditorTitleIconUploadButton) {
        sectionEditorTitleIconUploadButton.addEventListener('click', function () {
            var editor = activeSectionState.editor || (window.tinymce ? window.tinymce.get('draw-material-editor') : null);
            var originalText = sectionEditorTitleIconUploadButton.textContent;

            openEditorImagePicker(function (location) {
                if (!sectionEditorTitleIcon) {
                    return;
                }

                sectionEditorTitleIcon.value = location;
                updateSectionEditorTitlePreview();
            }, editor, {
                onStart: function () {
                    sectionEditorTitleIconUploadButton.disabled = true;
                    sectionEditorTitleIconUploadButton.textContent = '处理中...';
                    showDrawEditorNotice('图标处理中，请稍候...', 'info');
                },
                onSuccess: function () {
                    showDrawEditorNotice('图标已上传，请点击保存生效。', 'success');
                },
                onFinish: function () {
                    sectionEditorTitleIconUploadButton.disabled = false;
                    sectionEditorTitleIconUploadButton.textContent = originalText || '上传图标';
                }
            });
        });
    }

    if (sectionEditorCardStyle && sectionEditorCardStyle.addEventListener) {
        sectionEditorCardStyle.addEventListener('input', refreshSectionEditorHeaderBackgroundControls);
        sectionEditorCardStyle.addEventListener('change', refreshSectionEditorHeaderBackgroundControls);
    }

    if (sectionEditorCardBackgroundOverlay && sectionEditorCardBackgroundOverlay.addEventListener) {
        ['input', 'change'].forEach(function (eventName) {
            sectionEditorCardBackgroundOverlay.addEventListener(eventName, function () {
                var imageUrl = extractSectionEditorCssBackgroundUrl(sectionEditorCardStyle ? sectionEditorCardStyle.value : '');

                if (!sectionEditorCardStyle || imageUrl === '') {
                    refreshSectionEditorHeaderBackgroundControls();
                    return;
                }

                sectionEditorCardStyle.value = mergeHeaderBackgroundStyleText(
                    sectionEditorCardStyle.value,
                    imageUrl,
                    sectionEditorCardBackgroundOverlay.value
                );
                refreshSectionEditorHeaderBackgroundControls();
            });
        });
    }

    [sectionEditorCardSlotWidth, sectionEditorCardSlotHeight].forEach(function (inputEl) {
        if (!inputEl || !inputEl.addEventListener) {
            return;
        }

        ['input', 'change'].forEach(function (eventName) {
            inputEl.addEventListener(eventName, function () {
                if (!sectionEditorCardStyle) {
                    return;
                }

                sectionEditorCardStyle.value = mergeHeaderSlotSizeStyleText(
                    sectionEditorCardStyle.value,
                    sectionEditorCardSlotWidth ? sectionEditorCardSlotWidth.value : 708,
                    sectionEditorCardSlotHeight ? sectionEditorCardSlotHeight.value : 286
                );
                refreshSectionEditorHeaderBackgroundControls();
            });
        });
    });

    [
        sectionEditorTitleHtml,
        sectionEditorTitleText,
        sectionEditorTitleIcon,
        sectionEditorTitleStart,
        sectionEditorTitleEnd,
        sectionEditorTitleColor,
        sectionEditorTitleAlign,
        sectionEditorAdItemBorder,
        sectionEditorExpertAdInterval
    ].forEach(function (field) {
        if (!field || !field.addEventListener) {
            return;
        }

        field.addEventListener('input', updateSectionEditorTitlePreview);
        field.addEventListener('change', updateSectionEditorTitlePreview);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && sectionEditorModal && !sectionEditorModal.hasAttribute('hidden')) {
            closeSectionEditor();
        }
    });

    form.addEventListener('submit', function (event) {
        var editor = window.tinymce ? window.tinymce.get('draw-material-editor') : null;

        if (submitDrawFormAjax(editor)) {
            event.preventDefault();
            return;
        }

        fullscreenState.isSubmitting = true;

        if (editor) {
            syncFullscreenPreferenceFromState();
            syncEditorTextarea();
        } else if (window.tinymce) {
            syncFullscreenPreferenceFromState();
            window.tinymce.triggerSave();
        }
    });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', startDrawEditorPage, { once: true });
    } else {
        startDrawEditorPage();
    }
})();
</script>
<?php endif; ?>
