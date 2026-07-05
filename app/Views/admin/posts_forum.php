<?php
$posts = isset($posts) && is_array($posts) ? $posts : array();
$postForm = isset($postForm) && is_array($postForm) ? $postForm : array();
$postFilters = isset($postFilters) && is_array($postFilters) ? $postFilters : array();
$postCanManage = !empty($postCanManage);
$postCurrentRegion = isset($postCurrentRegion) ? (string) $postCurrentRegion : ((string) ($postForm['region'] ?? ($postFilters['region'] ?? 'macau')));
$postViewMode = isset($postViewMode) ? (string) $postViewMode : ((string) (isset($_GET['view']) ? $_GET['view'] : 'manage'));
$postSummaryCounts = isset($postSummaryCounts) && is_array($postSummaryCounts) ? $postSummaryCounts : array();
if ($postCurrentRegion !== 'hongkong') {
    $postCurrentRegion = 'macau';
}
$postRegionLabel = $postCurrentRegion === 'hongkong' ? '香港' : '澳门';
$postQuickMode = in_array($postViewMode, array('manage', 'compose', 'published', 'recycle'), true) ? $postViewMode : 'manage';
$postNeedsManageSettings = $postQuickMode === 'manage';
$postLockSettings = $postNeedsManageSettings ? app()->posts()->postLockSettings() : array();
$postLockState = $postNeedsManageSettings ? app()->posts()->postLockState($postCurrentRegion) : array();
$postLockBeforeMinutes = (int) ($postLockSettings['before_minutes'] ?? 60);
$postLockUnlockTime = (string) ($postLockSettings['unlock_time'] ?? '23:59');
$postLikeIncrementSettings = $postNeedsManageSettings ? app()->posts()->postLikeIncrementSettings() : array();
$postLikeBaseMin = (int) ($postLikeIncrementSettings['base_min'] ?? 368);
$postLikeBaseMax = (int) ($postLikeIncrementSettings['base_max'] ?? 668);
$postLikeIncrementMin = (int) ($postLikeIncrementSettings['increment_min'] ?? 1);
$postLikeIncrementMax = (int) ($postLikeIncrementSettings['increment_max'] ?? 1);
$postViewDisplaySettings = $postNeedsManageSettings ? app()->posts()->postViewDisplaySettings() : array();
$postViewBaseMin = (int) ($postViewDisplaySettings['base_min'] ?? 4935);
$postViewBaseMax = (int) ($postViewDisplaySettings['base_max'] ?? 7563);
$postViewIncrementMin = (int) ($postViewDisplaySettings['increment_min'] ?? 14);
$postViewIncrementMax = (int) ($postViewDisplaySettings['increment_max'] ?? 20);
$postSaleBuyerIncrementSettings = $postNeedsManageSettings ? app()->posts()->postSaleBuyerIncrementSettings() : array();
$postSaleBuyerIncrementMin = (int) ($postSaleBuyerIncrementSettings['increment_min'] ?? 1);
$postSaleBuyerIncrementMax = (int) ($postSaleBuyerIncrementSettings['increment_max'] ?? 3);
$postLockUnlockMinTime = '';
if (preg_match('/\d{4}-\d{2}-\d{2}\s+(\d{2}:\d{2})/', (string) ($postLockState['lock_start_at'] ?? ''), $postLockMinMatches)) {
    $postLockUnlockMinTime = (string) ($postLockMinMatches[1] ?? '');
}
$postLockStatusIsLocked = !empty($postLockState['is_locked']);
$postLockStatusLabel = (string) ($postLockState['label'] ?? ($postLockStatusIsLocked ? '此帖已🔐' : '此帖未锁 🔓'));
$postLockStatusClass = $postLockStatusIsLocked ? 'is-locked' : 'is-unlocked';
$postSwitchBaseUrl = public_url('admin.php') . '?page=posts';
$postRegionButtonPrefix = $postCurrentRegion === 'hongkong' ? '香港' : '澳门';
$postManageUrl = $postSwitchBaseUrl . '&region=' . urlencode($postCurrentRegion) . '&view=manage';
$postComposeUrl = $postSwitchBaseUrl . '&region=' . urlencode($postCurrentRegion) . '&view=compose';
$postPublishedUrl = $postSwitchBaseUrl . '&region=' . urlencode($postCurrentRegion) . '&view=published';
$postRecycleUrl = $postSwitchBaseUrl . '&region=' . urlencode($postCurrentRegion) . '&view=recycle';
$postRegionSwitchSuffix = '&view=' . urlencode($postQuickMode);
$showPostManageSection = in_array($postQuickMode, array('manage', 'recycle'), true);
$showPostGeneratorSection = $postQuickMode === 'compose' && empty($postForm['id']);
$showPostPublishedSection = $postQuickMode === 'published' && empty($postForm['id']);
$showPostEditSection = !empty($postForm['id']);
$showPostFormSection = $showPostPublishedSection || $showPostEditSection;
$postFormViewTarget = $showPostEditSection ? $postQuickMode : ($showPostPublishedSection ? 'published' : 'compose');
$postBlankFormUrl = $showPostPublishedSection ? $postPublishedUrl : $postComposeUrl;
$postFormTitle = $showPostEditSection ? '编辑帖子' : ('发表' . $postRegionLabel . '帖子');
$postFormSubtitle = $showPostEditSection
    ? '这里可以直接修改当前帖子内容，支持同步调整标题、摘要、预览内容和正文，地区与标题期数会按当前发帖页规则保持一致。'
    : '这是手动发表帖子页面，可直接填写标题、摘要、预览内容和正文。当前页面地区会自动作为帖子地区，标题期数会按后台动态前缀当前期数自动同步。';
$latestRegionDraw = isset($postLatestRegionDraw) && is_array($postLatestRegionDraw)
    ? $postLatestRegionDraw
    : app()->prediction()->latestHomepageDraw($postCurrentRegion);
$managedCurrentIssue = isset($postManagedCurrentIssue) && is_array($postManagedCurrentIssue)
    ? $postManagedCurrentIssue
    : app()->admins()->managedIssuePrefixSnapshotByRegion($postCurrentRegion);
$normalizeIssueTail = static function ($issueNo) {
    $text = preg_replace('/\D+/', '', trim((string) $issueNo));

    if ($text === '') {
        return '--';
    }

    $tail = strlen($text) > 3 ? substr($text, -3) : $text;

    return str_pad($tail, 3, '0', STR_PAD_LEFT);
};
$waveRed = array(1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46);
$waveBlue = array(3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48);
$latestRegionDrawDate = is_array($latestRegionDraw) ? (string) ($latestRegionDraw['draw_date'] ?? '') : '';
$padDrawNumber = static function ($value) {
    $number = (int) $value;

    return $number < 10 ? '0' . $number : (string) $number;
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
$renderAdminDrawBall = static function (?int $value) use ($padDrawNumber, $waveColorClass, $latestRegionDrawDate) {
    $ballClass = $value === null ? 'is-empty' : $waveColorClass($value);
    $numberText = $value === null ? '--' : $padDrawNumber($value);
    $zodiacText = $value === null ? '--' : (app()->prediction()->drawZodiacByNumber($value, $latestRegionDrawDate) ?: '--');

    return '<div class="admin-editor-live-ball ' . e($ballClass) . '">' .
        '<div class="admin-editor-live-ball-code">' . e($numberText) . '</div>' .
        '<div class="admin-editor-live-ball-zodiac">' . e($zodiacText) . '</div>' .
        '</div>';
};
$issueSuffixText = html_entity_decode('&#26399;', ENT_QUOTES, 'UTF-8');
$latestRegionIssueText = '--' . $issueSuffixText;
$postTitleIssueText = '--' . $issueSuffixText;
$managedCurrentIssueTail = $normalizeIssueTail((string) ($managedCurrentIssue['issue_prefix_tail'] ?? ($managedCurrentIssue['issue_no'] ?? '')));
if ($managedCurrentIssueTail !== '--') {
    $postTitleIssueText = $managedCurrentIssueTail . $issueSuffixText;
}
$latestRegionBallsHtml = '';
$stripPostIssuePrefix = static function ($title) {
    return trim((string) preg_replace('/^(?:\s*\d{1,6}\s*(?:期|鏈[^\s:：]{0,6}|链[^\s:：]{0,6}|閺[^\s:：]{0,6})\s*[:：]?\s*)+/u', '', trim((string) $title)));
};

if (is_array($latestRegionDraw)) {
    $issueTail = $normalizeIssueTail($latestRegionDraw['issue_no'] ?? '');
    $drawNumbers = isset($latestRegionDraw['numbers']) && is_array($latestRegionDraw['numbers'])
        ? array_values($latestRegionDraw['numbers'])
        : json_decode((string) ($latestRegionDraw['numbers_json'] ?? '[]'), true);
    $drawNumbers = is_array($drawNumbers) ? array_values($drawNumbers) : array();
    $specialNumber = (int) ($latestRegionDraw['special_number'] ?? 0);
    $latestRegionIssueText = $issueTail !== '--' ? ($issueTail . $issueSuffixText) : $latestRegionIssueText;
    if ($postTitleIssueText === '--' . $issueSuffixText && $issueTail !== '--') {
        $postTitleIssueText = $issueTail . $issueSuffixText;
    }

    foreach ($drawNumbers as $drawNumber) {
        $drawNumber = (int) $drawNumber;
        if ($drawNumber > 0) {
            $latestRegionBallsHtml .= $renderAdminDrawBall($drawNumber);
        }
    }

    $latestRegionBallsHtml .= '<span class="admin-editor-live-ball-separator">+</span>';
    $latestRegionBallsHtml .= $renderAdminDrawBall($specialNumber > 0 ? $specialNumber : null);
}

if ($latestRegionBallsHtml === '') {
    $latestRegionBallsHtml = '<span class="admin-help">请先更新当前分区开奖号码</span>';
}

$postCount = count($posts);
$likeTotal = 0;
$favoriteTotal = 0;
$pendingReportTotal = 0;
$softDeletedCount = 0;
$publishedCount = 0;
$draftCount = 0;
$pendingCount = 0;
$archivedCount = 0;
$purchaseTotal = 0;
$dataPostCount = 0;
$normalPostCount = 0;
$pricedSalePostCount = 0;
$freePostCount = 0;
$segmentPostCounts = array(
    1 => 0,
    2 => 0,
    3 => 0,
);
$segmentOptions = array(
    1 => 1,
    2 => 2,
    3 => 3,
);

foreach ($posts as $postRow) {
    $likeTotal += (int) ($postRow['like_count'] ?? 0);
    $favoriteTotal += (int) ($postRow['favorite_count'] ?? 0);
    $pendingReportTotal += (int) ($postRow['report_pending_count'] ?? 0);
    $purchaseTotal += (int) ($postRow['purchase_count'] ?? 0);
    if ((float) ($postRow['price'] ?? 0) > 0) {
        $pricedSalePostCount++;
    } else {
        $freePostCount++;
    }
    $postKind = (string) ($postRow['manage_post_kind'] ?? (((int) ($postRow['price'] ?? 0) > 0) ? 'data' : 'normal'));
    if ($postKind === 'data') {
        $dataPostCount++;
    } else {
        $normalPostCount++;
    }
    $segmentNo = max(1, (int) ($postRow['manage_segment_no'] ?? 1));
    $segmentOptions[$segmentNo] = $segmentNo;
    if ($segmentNo >= 1 && $segmentNo <= 3) {
        $segmentPostCounts[$segmentNo]++;
    }
    $postStatus = (string) ($postRow['status'] ?? '');
    if ($postStatus === 'published') {
        $publishedCount++;
    } elseif ($postStatus === 'pending') {
        $pendingCount++;
    } elseif ($postStatus === 'archived') {
        $archivedCount++;
    } else {
        $draftCount++;
    }
    if ((string) ($postRow['status'] ?? '') === 'deleted') {
        $softDeletedCount++;
    }
}

if ($posts === array() && $postSummaryCounts !== array()) {
    $postCount = (int) ($postSummaryCounts['total_count'] ?? 0);
    $dataPostCount = (int) ($postSummaryCounts['data_post_count'] ?? 0);
    $normalPostCount = (int) ($postSummaryCounts['normal_post_count'] ?? 0);
    $pricedSalePostCount = (int) ($postSummaryCounts['priced_sale_post_count'] ?? 0);
    $freePostCount = max(0, $postCount - $pricedSalePostCount);
    $segmentPostCounts[1] = (int) ($postSummaryCounts['segment_1_count'] ?? 0);
    $segmentPostCounts[2] = (int) ($postSummaryCounts['segment_2_count'] ?? 0);
    $segmentPostCounts[3] = (int) ($postSummaryCounts['segment_3_count'] ?? 0);
}
ksort($segmentOptions);
$saleAndFreePostCount = $pricedSalePostCount + $freePostCount;
$postClassicManageSummaryHtml = sprintf(
    '<span class="admin-posts-classic-toolbar-summary-item">选择 <span class="admin-posts-classic-toolbar-summary-count" data-post-selected-count>0</span> 条</span>'
    . '<span class="admin-posts-classic-toolbar-summary-item">出售帖 <span class="admin-posts-classic-toolbar-summary-count">%s</span> 条</span>'
    . '<span class="admin-posts-classic-toolbar-summary-item">普通帖 <span class="admin-posts-classic-toolbar-summary-count">%s</span> 条</span>'
    . '<span class="admin-posts-classic-toolbar-summary-item">共 <span class="admin-posts-classic-toolbar-summary-count">%s</span> 条帖子</span>',
    e((string) $pricedSalePostCount),
    e((string) $freePostCount),
    e((string) $saleAndFreePostCount)
);
$postManageSubtitle = '';
$postTopScopeFilter = (string) ($postFilters['top_scope'] ?? '');
$postTopScopeFilter = $postTopScopeFilter === 'normal' ? 'top_1' : ($postTopScopeFilter === 'admin' ? 'top_2' : ($postTopScopeFilter === 'forever' ? 'top_4' : $postTopScopeFilter));
$postFilterHelperText = $postQuickMode === 'recycle'
    ? html_entity_decode('&#22238;&#25910;&#31449;&#20165;&#26174;&#31034;&#24050;&#21024;&#38500;&#24086;&#23376;&#65292;&#21487;&#25353;&#39640;&#25163;&#21306;&#12289;&#32622;&#39030;&#12289;&#31867;&#22411;&#21644;&#20851;&#38190;&#35789;&#31579;&#36873;&#12290;', ENT_QUOTES, 'UTF-8')
    : '高手区支持高手1/高手2/高手3筛选，置顶支持置顶1/2/3/4/5精确筛选，关键词支持帖子标题和作者名称，可快速定位需要维护的帖子。';
$postToolbarHelperText = $postQuickMode === 'recycle'
    ? html_entity_decode('&#25209;&#37327;&#24674;&#22797;&#20250;&#25226;&#24086;&#23376;&#31227;&#22238;&#21069;&#21488;&#21015;&#34920;&#65307;&#24443;&#24213;&#21024;&#38500;&#21518;&#26080;&#27861;&#24674;&#22797;&#65292;&#35831;&#35880;&#24910;&#25805;&#20316;&#12290;', ENT_QUOTES, 'UTF-8')
    : html_entity_decode('&#25209;&#37327;&#21024;&#38500;&#20250;&#25226;&#24086;&#23376;&#31227;&#20837;&#22238;&#25910;&#31449;&#65292;&#19981;&#20250;&#31435;&#21363;&#21024;&#24211;&#65307;&#8220;&#26356;&#26032;&#19979;&#26399;&#8221;&#20250;&#25353;&#24403;&#21069;&#36873;&#25321;&#25209;&#37327;&#26356;&#26032;&#27491;&#25991;&#65307;&#8220;&#25209;&#37327;&#32534;&#36753;&#24086;&#23376;&#8221;&#24403;&#21069;&#19968;&#27425;&#20165;&#25903;&#25345;&#20808;&#36873;&#20013;1&#26465;&#36827;&#20837;&#32534;&#36753;&#39029;&#12290;', ENT_QUOTES, 'UTF-8');
$postFormFieldHelp = array(
    'segment' => html_entity_decode('&#31449;&#28857;&#26631;&#39064;&#12289;&#24086;&#23376;&#31867;&#22411;&#21644;&#20316;&#32773;&#26165;&#31216;&#20250;&#21442;&#19982;&#26631;&#39064;&#39044;&#35272;&#12290;', ENT_QUOTES, 'UTF-8'),
    'price' => html_entity_decode('&#20215;&#26684;&#22635; 0 &#34920;&#31034;&#20813;&#36153;&#65307;&#22823;&#20110; 0 &#34920;&#31034;&#20184;&#36153;&#38405;&#35835;&#12290;', ENT_QUOTES, 'UTF-8'),
    'title' => html_entity_decode('&#26631;&#39064;&#39044;&#35272;&#20250;&#25353;&#24403;&#21069;&#26399;&#25968;&#33258;&#21160;&#29983;&#25104;&#65292;&#21457;&#24067;&#21518;&#20250;&#21516;&#27493;&#21040;&#24086;&#23376;&#26631;&#39064;&#12290;', ENT_QUOTES, 'UTF-8'),
    'status' => html_entity_decode('&#20999;&#25442;&#20026;&#8220;&#24050;&#36719;&#21024;&#38500;&#8221;&#21518;&#65292;&#24086;&#23376;&#20250;&#36827;&#20837;&#22238;&#25910;&#31449;&#24182;&#20174;&#21069;&#21488;&#21015;&#34920;&#31227;&#38500;&#12290;', ENT_QUOTES, 'UTF-8'),
    'excerpt' => html_entity_decode('&#25688;&#35201;&#29992;&#20110;&#21015;&#34920;&#27010;&#35272;&#23637;&#31034;&#65292;&#30041;&#31354;&#26102;&#31995;&#32479;&#20250;&#26681;&#25454;&#27491;&#25991;&#33258;&#21160;&#25130;&#21462;&#12290;', ENT_QUOTES, 'UTF-8'),
    'preview' => html_entity_decode('&#36825;&#37324;&#22635;&#20889;&#21069;&#21488;&#35797;&#30475;&#20869;&#23481;&#65292;&#30041;&#31354;&#20250;&#33258;&#21160;&#29983;&#25104;&#12290;', ENT_QUOTES, 'UTF-8'),
    'content' => html_entity_decode('&#36825;&#37324;&#22635;&#20889;&#24086;&#23376;&#27491;&#25991;&#20869;&#23481;&#65292;&#25903;&#25345; TinyMCE &#23500;&#25991;&#26412;&#32534;&#36753;&#12290;', ENT_QUOTES, 'UTF-8'),
    'top' => html_entity_decode('&#32622;&#39030;&#25903;&#25345;&#26222;&#36890;&#12289;&#31649;&#29702;&#21592;&#21644;&#27704;&#20037;&#32622;&#39030;&#65292;&#21487;&#25353;&#21069;&#21488;&#23637;&#31034;&#38656;&#27714;&#32452;&#21512;&#20351;&#29992;&#12290;', ENT_QUOTES, 'UTF-8'),
);
$postNeedsGeneratorConfig = isset($postNeedsGeneratorConfig) ? !empty($postNeedsGeneratorConfig) : ($postQuickMode !== 'recycle');
$postGeneratorConfig = isset($postGeneratorConfig) && is_array($postGeneratorConfig) ? $postGeneratorConfig : array();
if ($postGeneratorConfig === array() && $postNeedsGeneratorConfig) {
    $postGeneratorConfig = app()->admins()->managedPostGeneratorConfig($postCurrentRegion);
}
$postGeneratorState = isset($postGeneratorState) && is_array($postGeneratorState) ? $postGeneratorState : array();
$generatorTypeOptions = isset($postGeneratorConfig['type_options']) && is_array($postGeneratorConfig['type_options']) ? $postGeneratorConfig['type_options'] : array();
$generatorZodiacOptions = isset($postGeneratorConfig['zodiac_options']) && is_array($postGeneratorConfig['zodiac_options']) ? $postGeneratorConfig['zodiac_options'] : array();
$generatorNumberOptions = isset($postGeneratorConfig['number_options']) && is_array($postGeneratorConfig['number_options']) ? $postGeneratorConfig['number_options'] : array();
$generatorWaveOptions = isset($postGeneratorConfig['wave_options']) && is_array($postGeneratorConfig['wave_options']) ? $postGeneratorConfig['wave_options'] : array();
$generatorElementOptions = isset($postGeneratorConfig['element_options']) && is_array($postGeneratorConfig['element_options']) ? $postGeneratorConfig['element_options'] : array();
$generatorHeadOptions = isset($postGeneratorConfig['head_options']) && is_array($postGeneratorConfig['head_options']) ? $postGeneratorConfig['head_options'] : array();
$generatorTailOptions = isset($postGeneratorConfig['tail_options']) && is_array($postGeneratorConfig['tail_options']) ? $postGeneratorConfig['tail_options'] : array();
$generatorSegmentOptions = isset($postGeneratorConfig['segment_options']) && is_array($postGeneratorConfig['segment_options']) ? $postGeneratorConfig['segment_options'] : array();
$generatorTopOptions = isset($postGeneratorConfig['top_options']) && is_array($postGeneratorConfig['top_options']) ? $postGeneratorConfig['top_options'] : array();
$generatorTemplateGroups = isset($postGeneratorConfig['template_groups']) && is_array($postGeneratorConfig['template_groups']) ? $postGeneratorConfig['template_groups'] : array();
$generatorTemplateLabels = array();
foreach ($generatorTemplateGroups as $generatorTemplateGroup) {
    foreach ((array) ($generatorTemplateGroup['items'] ?? array()) as $generatorTemplateItem) {
        $generatorTemplateLabel = trim((string) ($generatorTemplateItem['label'] ?? ''));
        if ($generatorTemplateLabel !== '') {
            $generatorTemplateLabels[] = $generatorTemplateLabel;
        }
    }
}
$generatorTemplateLabels = array_values(array_unique($generatorTemplateLabels));
usort($generatorTemplateLabels, static function ($left, $right) {
    return mb_strlen((string) $right, 'UTF-8') <=> mb_strlen((string) $left, 'UTF-8');
});
$adminCurrentMaterialBracketPairs = array(
    array('【', '】'),
    array('〖', '〗'),
    array('《', '》'),
    array('｛', '｝'),
    array('〔', '〕'),
    array('『', '』'),
    array('「', '」'),
    array('（', '）'),
    array('［', '］'),
    array('{', '}'),
    array('〘', '〙'),
    array('〚', '〛'),
);
$adminCurrentMaterialPredictionBracketPairs = array_slice($adminCurrentMaterialBracketPairs, 0, 6);
$generatorDefaultTargets = isset($postGeneratorConfig['default_targets']) && is_array($postGeneratorConfig['default_targets']) ? $postGeneratorConfig['default_targets'] : array();
$generatorRegionLabel = (string) ($postGeneratorConfig['region_label'] ?? ($postCurrentRegion === 'hongkong' ? html_entity_decode('&#39321;&#28207;', ENT_QUOTES, 'UTF-8') : html_entity_decode('&#28595;&#38376;', ENT_QUOTES, 'UTF-8')));
$segmentLabelMap = array(
    1 => html_entity_decode('&#39640;&#25163;1', ENT_QUOTES, 'UTF-8'),
    2 => html_entity_decode('&#39640;&#25163;2', ENT_QUOTES, 'UTF-8'),
    3 => html_entity_decode('&#39640;&#25163;3', ENT_QUOTES, 'UTF-8'),
);
foreach ($generatorSegmentOptions as $option) {
    $segmentValue = max(1, (int) ($option['value'] ?? 1));
    $segmentLabel = trim((string) ($option['label'] ?? ''));
    if ($segmentLabel !== '') {
        $segmentLabelMap[$segmentValue] = $segmentLabel;
    }
}
$resolveSegmentLabel = static function ($segmentNo) {
    $segmentNo = max(1, (int) $segmentNo);

    return html_entity_decode('&#39640;&#25163;', ENT_QUOTES, 'UTF-8') . $segmentNo;
};
$postGenerateLabel = '生成' . $generatorRegionLabel . '帖子';
ob_start();
?>
<div class="admin-posts-nav-shell <?php echo $postCurrentRegion === 'hongkong' ? 'is-region-hongkong' : 'is-region-macau'; ?>">
    <div class="admin-posts-nav-table" role="navigation" aria-label="帖子管理切换">
    <div class="admin-filter-chip-group admin-posts-region-switch admin-posts-nav-row">
        <a class="admin-filter-chip admin-posts-nav-cell <?php echo $postCurrentRegion === 'macau' ? 'is-active' : ''; ?>" href="<?php echo e($postSwitchBaseUrl . '&region=macau' . $postRegionSwitchSuffix); ?>">澳门</a>
        <a class="admin-filter-chip admin-posts-nav-cell <?php echo $postCurrentRegion === 'hongkong' ? 'is-active' : ''; ?>" href="<?php echo e($postSwitchBaseUrl . '&region=hongkong' . $postRegionSwitchSuffix); ?>">香港</a>
    </div>

    <div class="admin-filter-chip-group admin-posts-action-group admin-posts-nav-row">
        <a class="admin-filter-chip admin-posts-nav-cell <?php echo $postQuickMode === 'manage' ? 'is-active' : ''; ?>" href="<?php echo e($postManageUrl); ?>">帖子管理</a>
        <a class="admin-filter-chip admin-posts-nav-cell <?php echo $postQuickMode === 'compose' ? 'is-active' : ''; ?>" href="<?php echo e($postComposeUrl); ?>">生成帖子</a>
        <a class="admin-filter-chip admin-posts-nav-cell <?php echo $postQuickMode === 'published' ? 'is-active' : ''; ?>" href="<?php echo e($postPublishedUrl); ?>">发表帖子</a>
        <a class="admin-filter-chip admin-posts-nav-cell <?php echo $postQuickMode === 'recycle' ? 'is-active' : ''; ?>" href="<?php echo e($postRecycleUrl); ?>">帖子回收站</a>
    </div>
    </div>
</div>
<?php
$postNavigationHtml = trim((string) ob_get_clean());
$postNavigationFrameHtml = '<div class="admin-posts-common-nav-frame">'
    . $postNavigationHtml
    . '</div>';
$generatorCurrentType = (string) ($postGeneratorState['generator_type'] ?? $postCurrentRegion);
if ($generatorCurrentType !== 'hongkong') {
    $generatorCurrentType = 'macau';
}
$generatorCurrentIssueTail = preg_replace('/\D+/', '', (string) ($postGeneratorState['current_issue_tail'] ?? ($postGeneratorConfig['current_issue_tail'] ?? '')));
if ($generatorCurrentIssueTail === '') {
    $generatorCurrentIssueTail = (string) ($postGeneratorConfig['current_issue_tail'] ?? '');
}
$generatorCurrentIssueTail = str_pad(substr($generatorCurrentIssueTail, -3), 3, '0', STR_PAD_LEFT);
$generatorTitlePrefix = trim((string) ($postGeneratorState['title_prefix'] ?? ''));
$generatorTitleMiddle = trim((string) ($postGeneratorState['title_middle'] ?? ''));
$generatorAuthorNickname = trim((string) ($postGeneratorState['author_nickname'] ?? ''));
if (in_array($generatorTitleMiddle, array('[帖子类型]', '[随机作者]'), true)) {
    $generatorTitleMiddle = '';
}
if (in_array($generatorAuthorNickname, array('[随机作者]', '[帖子作者]', '[帖子类型]'), true)) {
    $generatorAuthorNickname = '';
}
$generatorTitlePrefixColorMode = trim((string) ($postGeneratorState['title_prefix_color_mode'] ?? ''));
if (!in_array($generatorTitlePrefixColorMode, array('fixed', 'daily_random'), true)) {
    $generatorTitlePrefixColorMode = '';
}
$generatorTitlePrefixColorValue = strtoupper(trim((string) ($postGeneratorState['title_prefix_color_value'] ?? '')));
if (!preg_match('/^#[0-9A-F]{6}$/', $generatorTitlePrefixColorValue)) {
    $generatorTitlePrefixColorValue = '';
}
$generatorTitleMiddleColorMode = trim((string) ($postGeneratorState['title_middle_color_mode'] ?? ''));
if (!in_array($generatorTitleMiddleColorMode, array('fixed', 'daily_random'), true)) {
    $generatorTitleMiddleColorMode = '';
}
$generatorTitleMiddleColorValue = strtoupper(trim((string) ($postGeneratorState['title_middle_color_value'] ?? '')));
if (!preg_match('/^#[0-9A-F]{6}$/', $generatorTitleMiddleColorValue)) {
    $generatorTitleMiddleColorValue = '';
}
$generatorAuthorNicknameColorMode = trim((string) ($postGeneratorState['author_nickname_color_mode'] ?? ''));
if (!in_array($generatorAuthorNicknameColorMode, array('fixed', 'daily_random'), true)) {
    $generatorAuthorNicknameColorMode = '';
}
$generatorAuthorNicknameColorValue = strtoupper(trim((string) ($postGeneratorState['author_nickname_color_value'] ?? '')));
if (!preg_match('/^#[0-9A-F]{6}$/', $generatorAuthorNicknameColorValue)) {
    $generatorAuthorNicknameColorValue = '';
}
$generatorTitleColorMode = '';
$generatorTitleColorValue = '';
$generatorAuthorNicknamePoolRaw = trim((string) ($postGeneratorState['author_nickname_pool'] ?? ''));
$generatorAuthorNicknameIdioms = isset($postGeneratorConfig['author_nickname_idioms']) && is_array($postGeneratorConfig['author_nickname_idioms']) ? array_values($postGeneratorConfig['author_nickname_idioms']) : array();
$generatorAuthorNicknamePoolParts = $generatorAuthorNicknamePoolRaw === '' ? array() : preg_split('/[\r\n,，;；、\s]+/u', $generatorAuthorNicknamePoolRaw);
$generatorAuthorNicknamePool = array_values(array_filter(array_map('trim', is_array($generatorAuthorNicknamePoolParts) ? $generatorAuthorNicknamePoolParts : array()), static function ($value) {
    return $value !== '';
}));
$generatorAuthorNicknamePoolCount = count($generatorAuthorNicknamePool);
$generatorCurrentSegment = max(1, min(3, (int) ($postGeneratorState['segment_no'] ?? 1)));
$generatorCurrentTopScope = (string) ($postGeneratorState['top_scope'] ?? 'top_1');
if (!isset($generatorTopOptions[$generatorCurrentTopScope])) {
    $generatorCurrentTopScope = 'top_1';
}
$generatorCurrentGenerationMode = (string) ($postGeneratorState['generation_mode'] ?? '');
if (!in_array($generatorCurrentGenerationMode, array('auto', 'manual'), true)) {
    $generatorCurrentGenerationMode = '';
}
$generatorPresetZodiacMin = trim((string) ($postGeneratorState['preset_zodiac_min'] ?? ($postGeneratorState['preset_zodiac_count'] ?? '')));
$generatorPresetZodiacMax = trim((string) ($postGeneratorState['preset_zodiac_max'] ?? ($postGeneratorState['preset_zodiac_count'] ?? '')));
$generatorPresetSegmentMin = trim((string) ($postGeneratorState['preset_segment_min'] ?? ''));
$generatorPresetSegmentMax = trim((string) ($postGeneratorState['preset_segment_max'] ?? ''));
$generatorPresetRecordMin = trim((string) ($postGeneratorState['preset_record_min'] ?? ''));
$generatorPresetRecordMax = trim((string) ($postGeneratorState['preset_record_max'] ?? ''));
$generatorPresetRecordRateMin = trim((string) ($postGeneratorState['preset_record_rate_min'] ?? ($postGeneratorState['preset_record_wrong_count'] ?? '')));
$generatorPresetRecordRateMax = trim((string) ($postGeneratorState['preset_record_rate_max'] ?? ($postGeneratorState['preset_record_wrong_count'] ?? '')));
$generatorPostUpdateTime = trim((string) ($postGeneratorState['post_update_time'] ?? ($postGeneratorState['material_content_time'] ?? '')));
if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $generatorPostUpdateTime)) {
    $generatorPostUpdateTime = '';
}
$generatorMaterialContentTime = trim((string) ($postGeneratorState['material_content_time'] ?? ''));
if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $generatorMaterialContentTime)) {
    $generatorMaterialContentTime = '';
}
$generatorSaleMaterialContentTime = trim((string) ($postGeneratorState['sale_material_content_time'] ?? ''));
if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $generatorSaleMaterialContentTime)) {
    $generatorSaleMaterialContentTime = '';
}

$generatorAutoReplyEnabled = (string) ($postGeneratorState['auto_reply_enabled'] ?? '1') === '1';
$generatorAutoReplyCount = trim((string) ($postGeneratorState['auto_reply_count'] ?? '3'));
if (!preg_match('/^\d+$/', $generatorAutoReplyCount)) {
    $generatorAutoReplyCount = '3';
}
$generatorAutoReplyCount = (string) max(1, min(99, (int) $generatorAutoReplyCount));
$generatorAutoReplyBaseMin = trim((string) ($postGeneratorState['auto_reply_base_min'] ?? '2'));
$generatorAutoReplyBaseMax = trim((string) ($postGeneratorState['auto_reply_base_max'] ?? $generatorAutoReplyCount));
$generatorAutoReplyDailyMin = trim((string) ($postGeneratorState['auto_reply_daily_min'] ?? '1'));
$generatorAutoReplyDailyMax = trim((string) ($postGeneratorState['auto_reply_daily_max'] ?? '3'));
$generatorAutoReplyIssueMin = trim((string) ($postGeneratorState['auto_reply_issue_min'] ?? '1'));
$generatorAutoReplyIssueMax = trim((string) ($postGeneratorState['auto_reply_issue_max'] ?? '3'));
$generatorAutoReplyForbidStartHour = trim((string) ($postGeneratorState['auto_reply_forbid_start_hour'] ?? '1'));
$generatorAutoReplyForbidEndHour = trim((string) ($postGeneratorState['auto_reply_forbid_end_hour'] ?? '8'));
$generatorWrongRefundStreak = trim((string) ($postGeneratorState['wrong_refund_streak'] ?? '2'));
$generatorWrongRefundPercent = trim((string) ($postGeneratorState['wrong_refund_percent'] ?? '100'));
$generatorAfterDrawDeleteWrongStreak = trim((string) ($postGeneratorState['after_draw_delete_wrong_streak'] ?? '2'));
foreach (array('generatorAutoReplyBaseMin', 'generatorAutoReplyBaseMax', 'generatorAutoReplyDailyMin', 'generatorAutoReplyDailyMax', 'generatorAutoReplyIssueMin', 'generatorAutoReplyIssueMax') as $generatorAutoReplyRangeVar) {
    if (!preg_match('/^\d+$/', $$generatorAutoReplyRangeVar)) {
        $$generatorAutoReplyRangeVar = in_array($generatorAutoReplyRangeVar, array('generatorAutoReplyDailyMin', 'generatorAutoReplyIssueMin'), true) ? '1' : '3';
    }
    $$generatorAutoReplyRangeVar = (string) max(0, min(99, (int) $$generatorAutoReplyRangeVar));
}
foreach (array('generatorAutoReplyForbidStartHour', 'generatorAutoReplyForbidEndHour') as $generatorAutoReplyHourVar) {
    if (!preg_match('/^\d+$/', $$generatorAutoReplyHourVar)) {
        $$generatorAutoReplyHourVar = $generatorAutoReplyHourVar === 'generatorAutoReplyForbidStartHour' ? '1' : '8';
    }
    $$generatorAutoReplyHourVar = (string) max(0, min(23, (int) $$generatorAutoReplyHourVar));
}
if (!preg_match('/^\d+$/', $generatorWrongRefundStreak)) {
    $generatorWrongRefundStreak = '2';
}
$generatorWrongRefundStreak = (string) max(2, min(99, (int) $generatorWrongRefundStreak));
if (!preg_match('/^\d+$/', $generatorWrongRefundPercent)) {
    $generatorWrongRefundPercent = '100';
}
$generatorWrongRefundPercent = (string) max(0, min(999, (int) $generatorWrongRefundPercent));
if (!preg_match('/^\d+$/', $generatorAfterDrawDeleteWrongStreak)) {
    $generatorAfterDrawDeleteWrongStreak = '2';
}
$generatorAfterDrawDeleteWrongStreak = (string) max(2, min(99, (int) $generatorAfterDrawDeleteWrongStreak));
if ((int) $generatorAutoReplyBaseMin <= 0) {
    $generatorAutoReplyBaseMin = '1';
}
if ((int) $generatorAutoReplyBaseMax <= 0) {
    $generatorAutoReplyBaseMax = $generatorAutoReplyBaseMin;
}
if ((int) $generatorAutoReplyIssueMin <= 0) {
    $generatorAutoReplyIssueMin = '1';
}
if ((int) $generatorAutoReplyIssueMax <= 0) {
    $generatorAutoReplyIssueMax = $generatorAutoReplyIssueMin;
}
$generatorPresetSegments = isset($postGeneratorState['preset_segments']) && is_array($postGeneratorState['preset_segments'])
    ? array_values(array_filter(array_map('strval', $postGeneratorState['preset_segments'])))
    : array();
if ($generatorPresetSegments === array()) {
    $generatorPresetSegments = array('1', '2', '3');
}
$generatorSelectedPresetSegments = array_fill_keys($generatorPresetSegments, true);
$generatorTitleMiddleWrapOptions = array('【】', '〖〗', '《》', '｛｝', '〔〕', '『』');
$generatorTitleMiddleWrap = (string) ($postGeneratorState['title_middle_wrap'] ?? '');
if (!in_array($generatorTitleMiddleWrap, $generatorTitleMiddleWrapOptions, true)) {
    $generatorTitleMiddleWrap = '';
}
$generatorTitleFontSizeOptions = array('12', '13', '14', '15', '16', '17', '18', '20', '22', '24');
$generatorTitleFontSize = (string) ($postGeneratorState['title_font_size'] ?? '');
if (!in_array($generatorTitleFontSize, $generatorTitleFontSizeOptions, true)) {
    $generatorTitleFontSize = '';
}
$generatorTitleFontWeightOptions = array('400', '500', '600', '700', '800', '900');
$generatorTitleFontWeight = (string) ($postGeneratorState['title_font_weight'] ?? '');
if (!in_array($generatorTitleFontWeight, $generatorTitleFontWeightOptions, true)) {
    $generatorTitleFontWeight = '';
}
$generatorSelectedZodiac = array_fill_keys(array_map('strval', isset($postGeneratorState['target_zodiac']) && is_array($postGeneratorState['target_zodiac']) ? $postGeneratorState['target_zodiac'] : array()), true);
$generatorSelectedNumber = array_fill_keys(array_map('strval', isset($postGeneratorState['target_number']) && is_array($postGeneratorState['target_number']) ? $postGeneratorState['target_number'] : array()), true);
$generatorSelectedWave = array_fill_keys(array_map('strval', isset($postGeneratorState['target_wave']) && is_array($postGeneratorState['target_wave']) ? $postGeneratorState['target_wave'] : array()), true);
$generatorSelectedElement = array_fill_keys(array_map('strval', isset($postGeneratorState['target_element']) && is_array($postGeneratorState['target_element']) ? $postGeneratorState['target_element'] : array()), true);
$generatorSelectedHead = array_fill_keys(array_map('strval', isset($postGeneratorState['target_head']) && is_array($postGeneratorState['target_head']) ? $postGeneratorState['target_head'] : array()), true);
$generatorSelectedTail = array_fill_keys(array_map('strval', isset($postGeneratorState['target_tail']) && is_array($postGeneratorState['target_tail']) ? $postGeneratorState['target_tail'] : array()), true);
$generatorNumberRows = array_chunk($generatorNumberOptions, 12);
$generatorSelectedTemplates = array_fill_keys(array_map('strval', isset($postGeneratorState['templates']) && is_array($postGeneratorState['templates']) ? $postGeneratorState['templates'] : array()), true);
$generatorSelectedManageTemplates = array_fill_keys(array_map('strval', isset($postGeneratorState['manage_templates']) && is_array($postGeneratorState['manage_templates']) ? $postGeneratorState['manage_templates'] : array()), true);
$generatorTemplateCount = 0;
foreach ($generatorTemplateGroups as $generatorTemplateGroup) {
    $generatorTemplateCount += count((array) ($generatorTemplateGroup['items'] ?? array()));
}
$generatorSelectedTemplateCount = count($generatorSelectedManageTemplates);
$generatorSummaryTotalCount = $postCount;
$generatorSummaryValueHtml = static function ($value) {
    return '<span class="admin-posts-generator-summary-value">' . e((string) $value) . '</span>';
};
$generatorSummaryHtml = '选择 ' . $generatorSummaryValueHtml(0)
    . ' 条，共 ' . $generatorSummaryValueHtml($generatorSummaryTotalCount)
    . ' 条，高手① ' . $generatorSummaryValueHtml($segmentPostCounts[1])
    . ' 条，高手② ' . $generatorSummaryValueHtml($segmentPostCounts[2])
    . ' 条，高手③ ' . $generatorSummaryValueHtml($segmentPostCounts[3])
    . ' 条';
$generatorRenderCheckbox = static function ($name, $value, $label, $checked, $className = '') {
    return '<label class="admin-posts-generator-check' . ($className !== '' ? ' ' . $className : '') . '">' .
        '<input type="checkbox" name="' . e($name) . '[]" value="' . e($value) . '"' . ($checked ? ' checked' : '') . '>' .
        '<span>' . e($label) . '</span>' .
        '</label>';
};
$generatorRenderRadio = static function ($name, $value, $label, $checked, $className = '', $extraHtml = '') {
    return '<label class="admin-posts-generator-radio' . ($className !== '' ? ' ' . $className : '') . '">' .
        '<input type="radio" name="' . e($name) . '" value="' . e($value) . '"' . ($checked ? ' checked' : '') . '>' .
        '<span>' . e($label) . '</span>' .
        $extraHtml .
        '</label>';
};
$generatorTopBadgeClass = static function (array $option) {
    if (!empty($option['is_top_forever'])) {
        return 'is-gold';
    }

    $colorTag = (string) ($option['color_tag'] ?? 'slate');
    if ($colorTag === 'red') {
        return 'is-red';
    }

    if ($colorTag === 'green') {
        return 'is-green';
    }

    if ($colorTag === 'gold') {
        return 'is-gold';
    }

    return 'is-slate';
};
?>
<section class="admin-split-grid admin-posts-stack mt-4">
    <?php if ($showPostManageSection): ?>
    <div>
        <div class="admin-card front-card admin-posts-classic-card" id="post-manage-card">
            <div class="admin-posts-card-titlebar">
                <?php echo $postNavigationFrameHtml; ?>
            </div>
            <?php if ($postManageSubtitle !== ''): ?>
            <div class="admin-card-subtitle"><?php echo e($postManageSubtitle); ?></div>
            <?php endif; ?>
            <?php if ($postCanManage && $postQuickMode === 'manage'): ?>
                <form method="post" action="<?php echo e(public_url('admin.php') . '?page=posts'); ?>" id="admin-post-generator-inline-form" hidden>
                    <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.posts')); ?>">
                    <input type="hidden" name="_admin_form" value="page">
                    <input type="hidden" name="_admin_action_override" value="save_post_generator_settings">
                    <input type="hidden" name="region" value="<?php echo e($postCurrentRegion); ?>">
                    <input type="hidden" name="view" value="manage">
                    <input type="hidden" name="generator_type" value="<?php echo e($generatorCurrentType); ?>">
                    <input type="hidden" name="generation_mode" value="<?php echo e($generatorCurrentGenerationMode); ?>">
                    <input type="hidden" name="segment_no" value="<?php echo e((string) $generatorCurrentSegment); ?>">
                    <input type="hidden" name="top_scope" value="<?php echo e($generatorCurrentTopScope); ?>">
                    <input type="hidden" name="preset_zodiac_min" value="<?php echo e($generatorPresetZodiacMin); ?>" data-generator-setting-value="preset_zodiac_min">
                    <input type="hidden" name="preset_zodiac_max" value="<?php echo e($generatorPresetZodiacMax); ?>" data-generator-setting-value="preset_zodiac_max">
                    <input type="hidden" name="preset_segment_min" value="<?php echo e($generatorPresetSegmentMin); ?>" data-generator-setting-value="preset_segment_min">
                    <input type="hidden" name="preset_segment_max" value="<?php echo e($generatorPresetSegmentMax); ?>" data-generator-setting-value="preset_segment_max">
                    <input type="hidden" name="preset_record_min" value="<?php echo e($generatorPresetRecordMin); ?>" data-generator-setting-value="preset_record_min">
                    <input type="hidden" name="preset_record_max" value="<?php echo e($generatorPresetRecordMax); ?>" data-generator-setting-value="preset_record_max">
                    <input type="hidden" name="preset_record_rate_min" value="<?php echo e($generatorPresetRecordRateMin); ?>" data-generator-setting-value="preset_record_rate_min">
                    <input type="hidden" name="preset_record_rate_max" value="<?php echo e($generatorPresetRecordRateMax); ?>" data-generator-setting-value="preset_record_rate_max">
                    <input type="hidden" name="title_prefix" value="<?php echo e($generatorTitlePrefix); ?>">
                    <input type="hidden" name="title_middle" value="<?php echo e($generatorTitleMiddle); ?>">
                    <input type="hidden" name="title_middle_wrap" value="<?php echo e($generatorTitleMiddleWrap); ?>">
                    <input type="hidden" name="author_nickname" value="<?php echo e($generatorAuthorNickname); ?>">
                    <input type="hidden" name="author_nickname_pool" value="<?php echo e($generatorAuthorNicknamePoolRaw); ?>">
                    <input type="hidden" name="title_prefix_color_mode" value="<?php echo e($generatorTitlePrefixColorMode); ?>">
                    <input type="hidden" name="title_prefix_color_value" value="<?php echo e($generatorTitlePrefixColorValue); ?>">
                    <input type="hidden" name="title_middle_color_mode" value="<?php echo e($generatorTitleMiddleColorMode); ?>">
                    <input type="hidden" name="title_middle_color_value" value="<?php echo e($generatorTitleMiddleColorValue); ?>">
                    <input type="hidden" name="author_nickname_color_mode" value="<?php echo e($generatorAuthorNicknameColorMode); ?>">
                    <input type="hidden" name="author_nickname_color_value" value="<?php echo e($generatorAuthorNicknameColorValue); ?>">
                    <input type="hidden" name="title_font_size" value="<?php echo e($generatorTitleFontSize); ?>">
                    <input type="hidden" name="title_font_weight" value="<?php echo e($generatorTitleFontWeight); ?>">
                    <input type="hidden" name="post_update_time" value="<?php echo e($generatorPostUpdateTime); ?>" data-post-update-time-value>
                    <input type="hidden" name="material_content_time" value="<?php echo e($generatorMaterialContentTime); ?>" data-material-content-time-value>
                    <input type="hidden" name="sale_material_content_time" value="<?php echo e($generatorSaleMaterialContentTime); ?>" data-sale-material-content-time-value>
                    <input type="hidden" name="is_blank_content" value="<?php echo !empty($postGeneratorState['is_blank_content']) ? '1' : ''; ?>" data-material-content-value>
                    <?php foreach (array_keys($generatorSelectedPresetSegments) as $segmentValue): ?>
                        <input type="hidden" name="preset_segments[]" value="<?php echo e((string) $segmentValue); ?>">
                    <?php endforeach; ?>
                    <?php foreach (array_keys($generatorSelectedZodiac) as $targetValue): ?>
                        <input type="hidden" name="target_zodiac[]" value="<?php echo e((string) $targetValue); ?>">
                    <?php endforeach; ?>
                    <?php foreach (array_keys($generatorSelectedNumber) as $targetValue): ?>
                        <input type="hidden" name="target_number[]" value="<?php echo e((string) $targetValue); ?>">
                    <?php endforeach; ?>
                    <?php foreach (array_keys($generatorSelectedWave) as $targetValue): ?>
                        <input type="hidden" name="target_wave[]" value="<?php echo e((string) $targetValue); ?>">
                    <?php endforeach; ?>
                    <?php foreach (array_keys($generatorSelectedElement) as $targetValue): ?>
                        <input type="hidden" name="target_element[]" value="<?php echo e((string) $targetValue); ?>">
                    <?php endforeach; ?>
                    <?php foreach (array_keys($generatorSelectedHead) as $targetValue): ?>
                        <input type="hidden" name="target_head[]" value="<?php echo e((string) $targetValue); ?>">
                    <?php endforeach; ?>
                    <?php foreach (array_keys($generatorSelectedTail) as $targetValue): ?>
                        <input type="hidden" name="target_tail[]" value="<?php echo e((string) $targetValue); ?>">
                    <?php endforeach; ?>
                    <input type="hidden" name="manage_templates_submitted" value="1">
                    <input type="hidden" name="is_fake_after_open" value="<?php echo !empty($postGeneratorState['is_fake_after_open']) ? '1' : ''; ?>" data-generator-setting-value="is_fake_after_open">
                    <input type="hidden" name="after_draw_delete_wrong_streak" value="<?php echo e($generatorAfterDrawDeleteWrongStreak); ?>" data-generator-setting-value="after_draw_delete_wrong_streak">
                    <input type="hidden" name="auto_reply_enabled" value="<?php echo $generatorAutoReplyEnabled ? '1' : ''; ?>" data-auto-reply-enabled-value>
                    <input type="hidden" name="auto_reply_count" value="<?php echo e($generatorAutoReplyCount); ?>" data-auto-reply-count-value>
                    <input type="hidden" name="auto_reply_base_min" value="<?php echo e($generatorAutoReplyBaseMin); ?>" data-auto-reply-base-min-value>
                    <input type="hidden" name="auto_reply_base_max" value="<?php echo e($generatorAutoReplyBaseMax); ?>" data-auto-reply-base-max-value>
                    <input type="hidden" name="auto_reply_daily_min" value="<?php echo e($generatorAutoReplyDailyMin); ?>" data-auto-reply-daily-min-value>
                    <input type="hidden" name="auto_reply_daily_max" value="<?php echo e($generatorAutoReplyDailyMax); ?>" data-auto-reply-daily-max-value>
                    <input type="hidden" name="auto_reply_issue_min" value="<?php echo e($generatorAutoReplyIssueMin); ?>" data-auto-reply-issue-min-value>
                    <input type="hidden" name="auto_reply_issue_max" value="<?php echo e($generatorAutoReplyIssueMax); ?>" data-auto-reply-issue-max-value>
                    <input type="hidden" name="auto_reply_forbid_start_hour" value="<?php echo e($generatorAutoReplyForbidStartHour); ?>" data-auto-reply-forbid-start-hour-value>
                    <input type="hidden" name="auto_reply_forbid_end_hour" value="<?php echo e($generatorAutoReplyForbidEndHour); ?>" data-auto-reply-forbid-end-hour-value>
                    <input type="hidden" name="wrong_refund_streak" value="<?php echo e($generatorWrongRefundStreak); ?>" data-wrong-refund-streak-value>
                    <input type="hidden" name="wrong_refund_percent" value="<?php echo e($generatorWrongRefundPercent); ?>" data-wrong-refund-percent-value>
                </form>
                <div class="admin-posts-generator-template-modal" data-generator-template-modal hidden>
                    <button class="admin-posts-generator-template-modal-backdrop" type="button" data-generator-template-modal-close aria-label="关闭指定类型选择"></button>
                    <div class="admin-posts-generator-template-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-posts-generator-template-modal-title">
                        <div class="admin-posts-generator-template-modal-head">
                            <div>
                                <div class="admin-posts-generator-template-modal-title" id="admin-posts-generator-template-modal-title">指定补帖类型</div>
                                <div class="admin-posts-generator-template-modal-subtitle">保存后，自动更新期数只按已选类型补被删除的帖子。</div>
                            </div>
                            <button class="admin-posts-generator-template-modal-close" type="button" data-generator-template-modal-close aria-label="关闭">×</button>
                        </div>
                        <div class="admin-posts-generator-template-modal-body">
                            <?php foreach ($generatorTemplateGroups as $group): ?>
                                <?php $groupLabel = (string) ($group['label'] ?? '模板'); ?>
                                <div class="admin-posts-generator-template-modal-group">
                                    <div class="admin-posts-generator-template-modal-group-title"><?php echo e($groupLabel); ?></div>
                                    <div class="admin-posts-generator-template-modal-grid">
                                        <?php foreach ((array) ($group['items'] ?? array()) as $item): ?>
                                            <?php $templateKey = (string) ($item['key'] ?? ''); ?>
                                            <label class="admin-posts-generator-check is-template admin-posts-generator-template-modal-option">
                                                <input type="checkbox" form="admin-post-generator-inline-form" name="manage_templates[]" value="<?php echo e($templateKey); ?>" data-generator-template-setting <?php echo isset($generatorSelectedManageTemplates[$templateKey]) ? 'checked' : ''; ?>>
                                                <span><?php echo e((string) ($item['label'] ?? $templateKey)); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="admin-posts-generator-template-modal-actions">
                            <span class="admin-posts-generator-template-modal-count">已选 <b data-generator-template-selected-count><?php echo e((string) $generatorSelectedTemplateCount); ?></b> 个类型</span>
                            <button class="admin-button is-danger" type="button" data-generator-template-clear>清空条件</button>
                            <button class="admin-button is-light" type="button" data-generator-template-modal-close>取消</button>
                            <button class="admin-button" type="submit" form="admin-post-generator-inline-form" name="_admin_action_override" value="save_post_generator_settings">保存类型</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

                    <?php if ($postCanManage && $postQuickMode !== 'recycle'): ?>
                        <div class="mt-4 admin-posts-classic-toolbar">
                            <?php if ($postQuickMode === 'manage'): ?>
                            <div class="admin-posts-classic-toolbar-row admin-posts-classic-toolbar-row--generator-settings">
                                <div class="admin-posts-generator-preset-table admin-posts-manage-generator-preset-table">
                                    <div class="admin-posts-generator-preset-card admin-posts-manage-auto-card">
                                        <div class="admin-posts-generator-preset-cell is-head">自动更新期数</div>
                                        <div class="admin-posts-generator-preset-cell is-control">
                                            <label class="admin-posts-generator-material-time-switch">
                                                <input type="checkbox" form="admin-post-generator-inline-form" value="1" data-post-update-time-toggle aria-label="启用自动更新期数时间" <?php echo $generatorPostUpdateTime !== '' ? 'checked' : ''; ?>>
                                            </label>
                                            <input class="admin-input admin-posts-generator-material-time-input" type="time" form="admin-post-generator-inline-form" value="<?php echo e($generatorPostUpdateTime); ?>" data-post-update-time-input aria-label="自动更新期数时间" <?php echo $generatorPostUpdateTime === '' ? 'disabled' : ''; ?>>
                                        </div>
                                    </div>
                                    <div class="admin-posts-generator-preset-card admin-posts-manage-material-card">
                                        <div class="admin-posts-generator-preset-cell is-head">更新普通帖子</div>
                                        <div class="admin-posts-generator-preset-cell is-control">
                                            <label class="admin-posts-generator-material-time-switch">
                                                <input type="checkbox" form="admin-post-generator-inline-form" value="1" data-material-content-time-toggle aria-label="启用更新普通帖子时间" <?php echo $generatorMaterialContentTime !== '' ? 'checked' : ''; ?>>
                                            </label>
                                            <input class="admin-input admin-posts-generator-material-time-input" type="time" form="admin-post-generator-inline-form" value="<?php echo e($generatorMaterialContentTime); ?>" data-material-content-time-input aria-label="更新普通帖子时间" <?php echo $generatorMaterialContentTime === '' ? 'disabled' : ''; ?>>
                                        </div>
                                    </div>
                                    <div class="admin-posts-generator-preset-card admin-posts-manage-sale-material-card">
                                        <div class="admin-posts-generator-preset-cell is-head">更新出售帖子</div>
                                        <div class="admin-posts-generator-preset-cell is-control">
                                            <label class="admin-posts-generator-material-time-switch">
                                                <input type="checkbox" form="admin-post-generator-inline-form" value="1" data-sale-material-content-time-toggle aria-label="启用更新出售帖子时间" <?php echo $generatorSaleMaterialContentTime !== '' ? 'checked' : ''; ?>>
                                            </label>
                                            <input class="admin-input admin-posts-generator-material-time-input" type="time" form="admin-post-generator-inline-form" value="<?php echo e($generatorSaleMaterialContentTime); ?>" data-sale-material-content-time-input aria-label="更新出售帖子时间" <?php echo $generatorSaleMaterialContentTime === '' ? 'disabled' : ''; ?>>
                                        </div>
                                    </div>
                                    <div class="admin-posts-generator-preset-card">
                                        <div class="admin-posts-generator-preset-cell is-head">指定生肖范围</div>
                                        <div class="admin-posts-generator-preset-cell is-control">
                                            <label class="admin-posts-generator-preset-field admin-posts-generator-preset-field--range">
                                                <input class="admin-input" type="number" form="admin-post-generator-inline-form" value="<?php echo e($generatorPresetZodiacMin); ?>" min="0" max="12" data-generator-setting-control="preset_zodiac_min">
                                                <em>-</em>
                                                <input class="admin-input" type="number" form="admin-post-generator-inline-form" value="<?php echo e($generatorPresetZodiacMax); ?>" min="0" max="12" data-generator-setting-control="preset_zodiac_max">
                                                <em>个</em>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="admin-posts-generator-preset-card admin-posts-manage-template-card">
                                        <div class="admin-posts-generator-preset-cell is-head">指定选择类型</div>
                                        <div class="admin-posts-generator-preset-cell is-control">
                                            <button class="admin-posts-generator-template-pill" type="button" data-generator-template-modal-open>
                                                已选<span data-generator-template-pill-count><?php echo e((string) $generatorSelectedTemplateCount); ?></span>个
                                            </button>
                                        </div>
                                    </div>
                                    <div class="admin-posts-generator-preset-card">
                                        <div class="admin-posts-generator-preset-cell is-head">高手榜区数量</div>
                                        <div class="admin-posts-generator-preset-cell is-control">
                                            <label class="admin-posts-generator-preset-field admin-posts-generator-preset-field--range">
                                                <input class="admin-input" type="number" form="admin-post-generator-inline-form" value="<?php echo e($generatorPresetSegmentMin); ?>" min="0" max="99" data-generator-setting-control="preset_segment_min">
                                                <em>-</em>
                                                <input class="admin-input" type="number" form="admin-post-generator-inline-form" value="<?php echo e($generatorPresetSegmentMax); ?>" min="0" max="99" data-generator-setting-control="preset_segment_max">
                                                <em>条</em>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="admin-posts-generator-preset-card">
                                        <div class="admin-posts-generator-preset-cell is-head">记录数量</div>
                                        <div class="admin-posts-generator-preset-cell is-control">
                                            <label class="admin-posts-generator-preset-field admin-posts-generator-preset-field--range">
                                                <input class="admin-input" type="number" form="admin-post-generator-inline-form" value="<?php echo e($generatorPresetRecordMin); ?>" min="1" max="99" data-generator-setting-control="preset_record_min">
                                                <em>-</em>
                                                <input class="admin-input" type="number" form="admin-post-generator-inline-form" value="<?php echo e($generatorPresetRecordMax); ?>" min="1" max="99" data-generator-setting-control="preset_record_max">
                                                <em>期</em>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="admin-posts-generator-preset-card">
                                        <div class="admin-posts-generator-preset-cell is-head">记录中奖率</div>
                                        <div class="admin-posts-generator-preset-cell is-control">
                                            <label class="admin-posts-generator-preset-field admin-posts-generator-preset-field--range">
                                                <input class="admin-input" type="number" form="admin-post-generator-inline-form" value="<?php echo e($generatorPresetRecordRateMin); ?>" min="0" max="100" data-generator-setting-control="preset_record_rate_min">
                                                <em>-</em>
                                                <input class="admin-input" type="number" form="admin-post-generator-inline-form" value="<?php echo e($generatorPresetRecordRateMax); ?>" min="0" max="100" data-generator-setting-control="preset_record_rate_max">
                                                <em>%</em>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="admin-posts-generator-preset-card">
                                        <div class="admin-posts-generator-preset-cell is-head">开奖后删帖补帖</div>
                                        <div class="admin-posts-generator-preset-cell is-control admin-posts-generator-after-draw-control">
                                            <label class="admin-posts-generator-check is-flag">
                                                <input type="checkbox" form="admin-post-generator-inline-form" value="1" data-generator-setting-control="is_fake_after_open" data-fake-after-open-toggle aria-label="启用开奖后删帖补帖" <?php echo !empty($postGeneratorState['is_fake_after_open']) ? 'checked' : ''; ?>>
                                            </label>
                                            <label class="admin-posts-generator-preset-field admin-posts-generator-wrong-streak-field" title="帖子历史记录包含当前期数记录，连续错 N 期后开奖后马上删帖补帖">
                                                <em>连错</em>
                                                <input class="admin-input" type="number" form="admin-post-generator-inline-form" value="<?php echo e($generatorAfterDrawDeleteWrongStreak); ?>" min="2" max="99" data-generator-setting-control="after_draw_delete_wrong_streak">
                                                <em>期</em>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="admin-posts-classic-toolbar-row admin-posts-classic-toolbar-row--auto-reply-settings">
                                <div class="admin-posts-generator-preset-table admin-posts-manage-generator-preset-table admin-posts-auto-reply-table">
                                    <div class="admin-posts-generator-preset-card">
                                        <div class="admin-posts-generator-preset-cell is-head">自动评论</div>
                                        <div class="admin-posts-generator-preset-cell is-control">
                                            <label class="admin-posts-generator-check is-flag">
                                                <input type="checkbox" value="1" data-auto-reply-enabled-input <?php echo $generatorAutoReplyEnabled ? 'checked' : ''; ?>>
                                                <span data-auto-reply-status><?php echo $generatorAutoReplyEnabled ? '已开启' : '已关闭'; ?></span>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="admin-posts-generator-preset-card">
                                        <div class="admin-posts-generator-preset-cell is-head">每帖评论范围</div>
                                        <div class="admin-posts-generator-preset-cell is-control">
                                            <label class="admin-posts-generator-preset-field">
                                                <input class="admin-input" type="number" value="<?php echo e($generatorAutoReplyBaseMin); ?>" min="1" max="99" data-auto-reply-base-min-input>
                                                <em>-</em>
                                                <input class="admin-input" type="number" value="<?php echo e($generatorAutoReplyBaseMax); ?>" min="1" max="99" data-auto-reply-base-max-input>
                                                <em>条</em>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="admin-posts-generator-preset-card">
                                        <div class="admin-posts-generator-preset-cell is-head">每日帖子递增范围</div>
                                        <div class="admin-posts-generator-preset-cell is-control">
                                            <label class="admin-posts-generator-preset-field">
                                                <input class="admin-input" type="number" value="<?php echo e($generatorAutoReplyDailyMin); ?>" min="0" max="99" data-auto-reply-daily-min-input>
                                                <em>-</em>
                                                <input class="admin-input" type="number" value="<?php echo e($generatorAutoReplyDailyMax); ?>" min="0" max="99" data-auto-reply-daily-max-input>
                                                <em>条</em>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="admin-posts-generator-preset-card">
                                        <div class="admin-posts-generator-preset-cell is-head">近期评论开始范围</div>
                                        <div class="admin-posts-generator-preset-cell is-control">
                                            <label class="admin-posts-generator-preset-field">
                                                <input class="admin-input" type="number" value="<?php echo e($generatorAutoReplyIssueMin); ?>" min="1" max="99" data-auto-reply-issue-min-input>
                                                <em>-</em>
                                                <input class="admin-input" type="number" value="<?php echo e($generatorAutoReplyIssueMax); ?>" min="1" max="99" data-auto-reply-issue-max-input>
                                                <em>期</em>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="admin-posts-generator-preset-card">
                                        <div class="admin-posts-generator-preset-cell is-head">禁止评论时段</div>
                                        <div class="admin-posts-generator-preset-cell is-control">
                                            <label class="admin-posts-generator-preset-field">
                                                <input class="admin-input" type="number" value="<?php echo e($generatorAutoReplyForbidStartHour); ?>" min="0" max="23" data-auto-reply-forbid-start-hour-input>
                                                <em>-</em>
                                                <input class="admin-input" type="number" value="<?php echo e($generatorAutoReplyForbidEndHour); ?>" min="0" max="23" data-auto-reply-forbid-end-hour-input>
                                                <em>时</em>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="admin-posts-generator-preset-card">
                                        <div class="admin-posts-generator-preset-cell is-head">连错返积分</div>
                                        <div class="admin-posts-generator-preset-cell is-control">
                                            <label class="admin-posts-generator-preset-field">
                                                <input class="admin-input" type="number" value="<?php echo e($generatorWrongRefundStreak); ?>" min="2" max="99" data-wrong-refund-streak-input>
                                                <em>期</em>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="admin-posts-generator-preset-card">
                                        <div class="admin-posts-generator-preset-cell is-head">返积分</div>
                                        <div class="admin-posts-generator-preset-cell is-control">
                                            <label class="admin-posts-generator-preset-field">
                                                <input class="admin-input" type="number" value="<?php echo e($generatorWrongRefundPercent); ?>" min="0" max="999" data-wrong-refund-percent-input>
                                                <em>%</em>
                                            </label>
                                        </div>
                                    </div>
                                    <div class="admin-posts-auto-reply-save-action">
                                        <button class="admin-posts-classic-toolbar-btn" type="button" data-post-settings-save>保存</button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div class="admin-posts-classic-toolbar-row admin-posts-classic-toolbar-row--post-settings-table">
                                <div class="admin-post-lock-settings-frame">
                                    <div class="admin-post-lock-settings-grid" role="group" aria-label="帖子显示与锁帖设置">
                                        <section class="admin-post-lock-settings-card">
                                            <div class="admin-post-lock-settings-card-title">开奖前锁帖时间</div>
                                            <div class="admin-post-lock-settings-card-body">
                                                <div class="admin-post-lock-settings" data-post-lock-settings data-api-url="<?php echo e(public_url('api.php')); ?>" data-token="<?php echo e(csrf_token('api')); ?>" data-region="<?php echo e($postCurrentRegion); ?>">
                                                    <div class="admin-post-lock-settings-controls">
                                                        <label class="admin-post-lock-settings-field">
                                                            <input type="number" min="0" max="1440" step="1" value="<?php echo e((string) $postLockBeforeMinutes); ?>" data-post-lock-before-minutes>
                                                            <span>分钟</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                                        <section class="admin-post-lock-settings-card admin-post-lock-settings-card--unlock-time">
                                            <div class="admin-post-lock-settings-card-title">解锁帖时间</div>
                                            <div class="admin-post-lock-settings-card-body">
                                                <div class="admin-post-lock-settings">
                                                    <div class="admin-post-lock-settings-controls">
                                                        <label class="admin-post-lock-settings-field">
                                                            <input type="time" min="<?php echo e($postLockUnlockMinTime); ?>" max="23:59" value="<?php echo e($postLockUnlockTime); ?>" data-post-lock-unlock-time>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                                        <section class="admin-post-lock-settings-card">
                                            <div class="admin-post-lock-settings-card-title">默认点赞范围</div>
                                            <div class="admin-post-lock-settings-card-body">
                                                <div class="admin-post-lock-settings" data-post-like-increment-settings data-api-url="<?php echo e(public_url('api.php')); ?>" data-token="<?php echo e(csrf_token('api')); ?>">
                                                    <div class="admin-post-lock-settings-controls">
                                                        <label class="admin-post-lock-settings-field">
                                                            <input type="number" min="1" max="999999" step="1" value="<?php echo e((string) $postLikeBaseMin); ?>" data-post-like-base-min>
                                                        </label>
                                                        <label class="admin-post-lock-settings-field">
                                                            <span class="admin-post-lock-settings-separator">-</span>
                                                            <input type="number" min="1" max="999999" step="1" value="<?php echo e((string) $postLikeBaseMax); ?>" data-post-like-base-max>
                                                            <span>量</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                                        <section class="admin-post-lock-settings-card">
                                            <div class="admin-post-lock-settings-card-title">点赞递增</div>
                                            <div class="admin-post-lock-settings-card-body">
                                                <div class="admin-post-lock-settings">
                                                    <div class="admin-post-lock-settings-controls">
                                                        <label class="admin-post-lock-settings-field">
                                                            <input type="number" min="1" max="999" step="1" value="<?php echo e((string) $postLikeIncrementMin); ?>" data-post-like-increment-min>
                                                        </label>
                                                        <label class="admin-post-lock-settings-field">
                                                            <span class="admin-post-lock-settings-separator">-</span>
                                                            <input type="number" min="1" max="999" step="1" value="<?php echo e((string) $postLikeIncrementMax); ?>" data-post-like-increment-max>
                                                            <span>量</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                                        <section class="admin-post-lock-settings-card">
                                            <div class="admin-post-lock-settings-card-title">浏览量初始默认范围</div>
                                            <div class="admin-post-lock-settings-card-body">
                                                <div class="admin-post-lock-settings" data-post-view-display-settings data-api-url="<?php echo e(public_url('api.php')); ?>" data-token="<?php echo e(csrf_token('api')); ?>">
                                                    <div class="admin-post-lock-settings-controls">
                                                        <label class="admin-post-lock-settings-field">
                                                            <input type="number" min="1" max="999999" step="1" value="<?php echo e((string) $postViewBaseMin); ?>" data-post-view-base-min>
                                                        </label>
                                                        <label class="admin-post-lock-settings-field">
                                                            <span class="admin-post-lock-settings-separator">-</span>
                                                            <input type="number" min="1" max="999999" step="1" value="<?php echo e((string) $postViewBaseMax); ?>" data-post-view-base-max>
                                                            <span>量</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                                        <section class="admin-post-lock-settings-card">
                                            <div class="admin-post-lock-settings-card-title">浏览量递增范围</div>
                                            <div class="admin-post-lock-settings-card-body">
                                                <div class="admin-post-lock-settings">
                                                    <div class="admin-post-lock-settings-controls">
                                                        <label class="admin-post-lock-settings-field">
                                                            <input type="number" min="1" max="999" step="1" value="<?php echo e((string) $postViewIncrementMin); ?>" data-post-view-increment-min>
                                                        </label>
                                                        <label class="admin-post-lock-settings-field">
                                                            <span class="admin-post-lock-settings-separator">-</span>
                                                            <input type="number" min="1" max="999" step="1" value="<?php echo e((string) $postViewIncrementMax); ?>" data-post-view-increment-max>
                                                            <span>量</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                                        <section class="admin-post-lock-settings-card">
                                            <div class="admin-post-lock-settings-card-title">帖子购买递增范围</div>
                                            <div class="admin-post-lock-settings-card-body">
                                                <div class="admin-post-lock-settings" data-post-sale-buyer-increment-settings data-api-url="<?php echo e(public_url('api.php')); ?>" data-token="<?php echo e(csrf_token('api')); ?>">
                                                    <div class="admin-post-lock-settings-controls">
                                                        <label class="admin-post-lock-settings-field">
                                                            <input type="number" min="0" max="999" step="1" value="<?php echo e((string) $postSaleBuyerIncrementMin); ?>" data-post-sale-buyer-increment-min>
                                                        </label>
                                                        <label class="admin-post-lock-settings-field">
                                                            <span class="admin-post-lock-settings-separator">-</span>
                                                            <input type="number" min="0" max="999" step="1" value="<?php echo e((string) $postSaleBuyerIncrementMax); ?>" data-post-sale-buyer-increment-max>
                                                            <span>量</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </section>
                                        <?php if ($postQuickMode !== 'manage'): ?>
                                        <div class="admin-post-lock-settings-actions admin-post-lock-settings-actions--standalone">
                                            <button class="admin-posts-classic-toolbar-btn" type="button" data-post-settings-save>保存</button>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <form class="admin-posts-classic-filterbar" method="get" action="<?php echo e(public_url('admin.php')); ?>" id="admin-posts-classic-filter-form">
                                <input type="hidden" name="page" value="posts">
                                <input type="hidden" name="region" value="<?php echo e($postCurrentRegion); ?>">
                                <input type="hidden" name="view" value="<?php echo e($postQuickMode); ?>">
                                <div class="admin-posts-classic-filter-frame">
                                    <div class="admin-posts-classic-filter-grid">
                                        <div class="admin-posts-classic-filter-head">高手区帖</div>
                                        <div class="admin-posts-classic-filter-head">置顶帖</div>
                                        <div class="admin-posts-classic-filter-head">出售/已出售帖</div>
                                        <div class="admin-posts-classic-filter-head">准错帖</div>
                                        <div class="admin-posts-classic-filter-head">标题查询帖子</div>
                                        <label class="admin-posts-classic-filter-control">
                                            <select class="admin-select" name="segment_no" title="筛选高手n区的帖子" aria-label="高手区帖" data-post-filter-submit>
                                                <option value="0">全部</option>
                                                <?php foreach ($segmentOptions as $segmentOption): ?>
                                                    <option value="<?php echo e((string) $segmentOption); ?>" <?php echo (int) ($postFilters['segment_no'] ?? 0) === (int) $segmentOption ? 'selected' : ''; ?>><?php echo e($resolveSegmentLabel($segmentOption)); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </label>
                                        <label class="admin-posts-classic-filter-control">
                                            <select class="admin-select" name="top_scope" title="筛选置顶n区的帖子" aria-label="置顶帖" data-post-filter-submit>
                                                <option value="">全部</option>
                                                <option value="top_1" <?php echo $postTopScopeFilter === 'top_1' ? 'selected' : ''; ?>>置顶1</option>
                                                <option value="top_2" <?php echo $postTopScopeFilter === 'top_2' ? 'selected' : ''; ?>>置顶2</option>
                                                <option value="top_3" <?php echo $postTopScopeFilter === 'top_3' ? 'selected' : ''; ?>>置顶3</option>
                                                <option value="top_4" <?php echo $postTopScopeFilter === 'top_4' ? 'selected' : ''; ?>>置顶4</option>
                                                <option value="top_5" <?php echo $postTopScopeFilter === 'top_5' ? 'selected' : ''; ?>>置顶5</option>
                                            </select>
                                        </label>
                                        <div class="admin-posts-classic-filter-control admin-posts-classic-filter-control--combo">
                                            <?php
                                            $salePurchaseFilterValue = '';
                                            if ((string) ($postFilters['purchase_filter'] ?? '') === 'purchased') {
                                                $salePurchaseFilterValue = 'purchased';
                                            } elseif ((string) ($postFilters['sale_filter'] ?? '') === 'sale') {
                                                $salePurchaseFilterValue = 'sale';
                                            } elseif ((string) ($postFilters['sale_filter'] ?? '') === 'free') {
                                                $salePurchaseFilterValue = 'free';
                                            }
                                            ?>
                                            <input type="hidden" name="sale_filter" value="<?php echo e((string) ($postFilters['sale_filter'] ?? '')); ?>" data-sale-filter-value>
                                            <input type="hidden" name="purchase_filter" value="<?php echo e((string) ($postFilters['purchase_filter'] ?? '')); ?>" data-purchase-filter-value>
                                            <select class="admin-select" title="筛选出售、免费或已出售的帖子" aria-label="出售/已出售帖" data-sale-purchase-filter data-post-filter-submit>
                                                <option value="" <?php echo $salePurchaseFilterValue === '' ? 'selected' : ''; ?>>全部</option>
                                                <option value="sale" <?php echo $salePurchaseFilterValue === 'sale' ? 'selected' : ''; ?>>出售</option>
                                                <option value="free" <?php echo $salePurchaseFilterValue === 'free' ? 'selected' : ''; ?>>免费</option>
                                                <option value="purchased" <?php echo $salePurchaseFilterValue === 'purchased' ? 'selected' : ''; ?>>已出售</option>
                                            </select>
                                        </div>
                                        <div class="admin-posts-classic-filter-control admin-posts-classic-filter-control--combo">
                                            <?php
                                            $resultStreakFilterValue = '';
                                            if ((string) ($postFilters['result_filter'] ?? '') === 'hit') {
                                                $resultStreakFilterValue = 'hit';
                                            } elseif ((string) ($postFilters['result_filter'] ?? '') === 'wrong') {
                                                $resultStreakFilterValue = 'wrong';
                                            }
                                            ?>
                                            <input type="hidden" name="result_filter" value="<?php echo e((string) ($postFilters['result_filter'] ?? '')); ?>" data-result-filter-value>
                                            <select class="admin-select" title="筛选最近一期为准或错的帖子" aria-label="准错帖" data-result-streak-filter data-post-filter-submit>
                                                <option value="" <?php echo $resultStreakFilterValue === '' ? 'selected' : ''; ?>>全部</option>
                                                <option value="hit" <?php echo $resultStreakFilterValue === 'hit' ? 'selected' : ''; ?>>准帖</option>
                                                <option value="wrong" <?php echo $resultStreakFilterValue === 'wrong' ? 'selected' : ''; ?>>错帖</option>
                                            </select>
                                        </div>
                                        <div class="admin-posts-classic-filter-control admin-posts-classic-filter-control--keyword">
                                            <input class="admin-input" type="text" name="keyword" value="<?php echo e((string) ($postFilters['keyword'] ?? '')); ?>" placeholder="请输入帖子标题、作者名或关键字查询" title="关键词支持帖子标题和作者名称，可快速定位需要维护的帖子。" aria-label="标题查询帖子">
                                        </div>
                                    </div>
                                    <div class="admin-posts-classic-filter-action">
                                        <button class="admin-button admin-posts-classic-query" type="submit">查询</button>
                                    </div>
                                </div>
                            </form>
                            <div class="admin-posts-classic-toolbar-row is-secondary">
                                <div class="admin-posts-classic-toolbar-meta is-manage-summary">
                                    <button class="admin-posts-classic-toolbar-btn is-danger" type="button" data-bulk-action="delete" title="批量删除会把帖子移入回收站">批量删除</button>
                                    <select class="admin-select is-compact" title="把勾选帖子批量移动到高手区" aria-label="批量高手" data-bulk-select-action="set_segment_no">
                                        <option value="">批量高手</option>
                                        <?php foreach ($segmentOptions as $segmentOption): ?>
                                            <option value="<?php echo e((string) $segmentOption); ?>">高手<?php echo e((string) $segmentOption); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select class="admin-select is-compact" title="把勾选帖子批量设置为置顶位置" aria-label="批量置顶" data-bulk-select-action="set_segment_sort">
                                        <option value="">批量置顶</option>
                                        <?php for ($sortOption = 1; $sortOption <= 5; $sortOption++): ?>
                                            <option value="<?php echo e((string) $sortOption); ?>">置顶<?php echo e((string) $sortOption); ?></option>
                                        <?php endfor; ?>
                                    </select>
                                    <div class="admin-posts-classic-toolbar-summary-side">
                                        <span class="admin-posts-classic-toolbar-summary"><?php echo $postClassicManageSummaryHtml; ?></span>
                                        <span class="admin-posts-classic-toolbar-pager">1</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($postQuickMode !== 'recycle'): ?>
                    <script>
                    (function () {
                        var filterForm = document.getElementById('admin-posts-classic-filter-form');
                        var filterControls = document.querySelectorAll('[data-post-filter-submit]');

                        if (!filterForm || !filterControls.length) {
                            return;
                        }

                        function syncCombinedFilters(changedControl) {
                            var salePurchaseFilter = filterForm.querySelector('[data-sale-purchase-filter]');
                            var saleFilterValue = filterForm.querySelector('[data-sale-filter-value]');
                            var purchaseFilterValue = filterForm.querySelector('[data-purchase-filter-value]');
                            var resultStreakFilter = filterForm.querySelector('[data-result-streak-filter]');
                            var resultFilterValue = filterForm.querySelector('[data-result-filter-value]');
                            var salePurchaseValue = salePurchaseFilter ? salePurchaseFilter.value : '';
                            var resultStreakValue = resultStreakFilter ? resultStreakFilter.value : '';

                            if (!changedControl || changedControl === salePurchaseFilter) {
                                if (saleFilterValue) {
                                    saleFilterValue.value = salePurchaseValue === 'sale' ? 'sale' : (salePurchaseValue === 'free' ? 'free' : '');
                                }
                                if (purchaseFilterValue) {
                                    purchaseFilterValue.value = salePurchaseValue === 'purchased' ? 'purchased' : '';
                                }
                            }

                            if (!changedControl || changedControl === resultStreakFilter) {
                                if (resultFilterValue) {
                                    resultFilterValue.value = resultStreakValue === 'hit' ? 'hit' : (resultStreakValue === 'wrong' ? 'wrong' : '');
                                }
                            }
                        }

                        function submitFilterFormOnce() {
                            if (filterForm.getAttribute('data-submitting') === '1') {
                                return;
                            }
                            filterForm.setAttribute('data-submitting', '1');
                            if (typeof filterForm.requestSubmit === 'function') {
                                filterForm.requestSubmit();
                                return;
                            }

                            filterForm.submit();
                        }

                        filterForm.addEventListener('submit', function () {
                            syncCombinedFilters(null);
                            filterForm.setAttribute('data-submitting', '1');
                        });

                        syncCombinedFilters(null);
                        Array.prototype.slice.call(filterControls).forEach(function (filterControl) {
                            filterControl.addEventListener('change', function () {
                                syncCombinedFilters(filterControl);
                                submitFilterFormOnce();
                            });
                        });
                    })();
                    </script>
                    <?php endif; ?>

                <form method="post" action="<?php echo e(public_url('admin.php') . '?page=posts'); ?>" class="admin-posts-classic-manage-form" data-posts-bulk-form data-current-issue-tail="<?php echo e($generatorCurrentIssueTail); ?>">
                    <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.posts')); ?>">
                    <input type="hidden" name="_admin_form" value="page">
                    <input type="hidden" name="_admin_action" value="bulk_posts">
                    <input type="hidden" name="region" value="<?php echo e($postCurrentRegion); ?>">
                    <input type="hidden" name="view" value="<?php echo e($postQuickMode); ?>">
                    <input type="hidden" name="bulk_action" value="">
                    <input type="hidden" name="bulk_value" value="">

                    <?php if ($postQuickMode === 'recycle'): ?>
                        <div class="admin-posts-classic-toolbar">
                            <div class="admin-posts-classic-toolbar-row is-secondary">
                                <?php if ($postCanManage): ?>
                                    <button class="admin-posts-classic-toolbar-btn is-danger" type="button" data-bulk-action="purge" title="批量彻底删除已勾选的回收站帖子">批量删除</button>
                                <?php endif; ?>
                                <div class="admin-posts-classic-toolbar-meta">
                                    <span class="admin-posts-classic-toolbar-summary"><?php echo $postClassicManageSummaryHtml; ?></span>
                                    <span class="admin-posts-classic-toolbar-pager">1</span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php
                    $postRecycleRetentionDays = 3;
                    $postRecycleDeletedAtText = static function ($deletedAt) {
                        $text = trim((string) $deletedAt);
                        if ($text === '') {
                            return '-';
                        }

                        return format_datetime($text);
                    };
                    $postRecycleExpiryText = static function ($deletedAt) use ($postRecycleRetentionDays) {
                        $text = trim((string) $deletedAt);
                        if ($text === '') {
                            return '-';
                        }

                        try {
                            $deletedAtTime = new \DateTimeImmutable($text);
                            $purgeAtTime = $deletedAtTime->modify('+' . $postRecycleRetentionDays . ' days');
                            $nowTime = new \DateTimeImmutable('now');
                        } catch (\Exception $exception) {
                            return '-';
                        }

                        if ($purgeAtTime <= $nowTime) {
                            return '今日';
                        }

                        $remainingSeconds = $purgeAtTime->getTimestamp() - $nowTime->getTimestamp();
                        $remainingDays = (int) ceil($remainingSeconds / 86400);
                        if ($remainingDays < 1) {
                            $remainingDays = 1;
                        }

                        return $remainingDays . '天后';
                    };
                    ?>
                    <div class="admin-posts-classic-table-wrap mt-4">
                        <?php if ($postQuickMode === 'recycle'): ?>
                        <table class="admin-posts-classic-table is-recycle-table">
                            <thead>
                            <tr>
                                <th class="is-check"><?php if ($postCanManage): ?><input type="checkbox" data-check-all="input[name=&quot;selected_ids[]&quot;]"><?php endif; ?></th>
                                <th class="is-title">回收帖子</th>
                                <th class="is-delete-time">删除时间</th>
                                <th class="is-expire-time">自动清理</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$posts): ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="admin-empty">回收站暂无帖子。</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                            <?php foreach ($posts as $row): ?>
                                <?php
                                $postId = (int) ($row['id'] ?? 0);
                                $topScopeText = (string) ($row['manage_top_scope'] ?? '');
                                $topText = '';
                                if (in_array($topScopeText, array('top_1', 'top_2', 'top_3', 'top_4'), true)) {
                                    $topText = '置顶' . substr($topScopeText, -1);
                                }
                                $priceText = number_format((float) ($row['price'] ?? 0), 0, '.', ',');
                                $isDataPost = (string) ($row['manage_post_kind'] ?? (((int) ($row['price'] ?? 0) > 0) ? 'data' : 'normal')) === 'data';
                                $segmentNo = max(1, (int) ($row['manage_segment_no'] ?? 1));
                                $segmentLabel = $resolveSegmentLabel($segmentNo);
                                $titleText = trim((string) ($row['title'] ?? ''));
                                $authorDisplayText = trim((string) (($row['author_name'] ?? '') ?: ''));
                                if ($titleText === '') {
                                    $titleText = '-';
                                }
                                if ($authorDisplayText !== '' && mb_strpos($titleText, $authorDisplayText, 0, 'UTF-8') === false) {
                                    $titleText .= $authorDisplayText;
                                }
                                $deletedIssueText = trim((string) ($row['manage_deleted_issue_text'] ?? ''));
                                $deletedAtText = $postRecycleDeletedAtText($row['deleted_at'] ?? null);
                                $expireAtText = $postRecycleExpiryText($row['deleted_at'] ?? null);
                                ?>
                                <tr data-post-id="<?php echo e((string) $postId); ?>">
                                    <td class="admin-posts-classic-check-cell">
                                        <?php if ($postCanManage): ?>
                                            <input type="checkbox" name="selected_ids[]" value="<?php echo e((string) $postId); ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td class="admin-posts-recycle-title-cell">
                                        <div class="admin-posts-recycle-meta">
                                            <span class="admin-posts-classic-chip is-danger">已删</span>
                                            <span class="admin-posts-classic-chip"><?php echo e($segmentLabel); ?></span>
                                            <?php if ($topText !== ''): ?>
                                                <span class="admin-posts-classic-chip is-top"><?php echo e($topText); ?></span>
                                            <?php endif; ?>
                                            <span class="admin-posts-recycle-price">价格：<strong><?php echo e($priceText); ?></strong></span>
                                        </div>
                                        <div class="admin-posts-recycle-title-line">
                                            <span class="admin-posts-classic-sale-badge is-recycle <?php echo $isDataPost ? 'is-sale' : 'is-normal'; ?>"><?php echo $isDataPost ? '出售贴' : '普通帖'; ?></span>
                                            <?php if ($deletedIssueText !== ''): ?>
                                                <span class="admin-posts-recycle-issue-text"><?php echo e($deletedIssueText); ?></span>
                                            <?php endif; ?>
                                            <span class="admin-posts-recycle-title-main" title="<?php echo e($titleText); ?>"><?php echo e($titleText); ?></span>
                                        </div>
                                    </td>
                                    <td class="admin-posts-recycle-time-cell"><span><?php echo e($deletedAtText); ?></span></td>
                                    <td class="admin-posts-recycle-expire-cell"><span class="admin-posts-recycle-expire-badge"><?php echo e($expireAtText); ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <table class="admin-posts-classic-table">
                            <thead>
                            <tr>
                                <th class="is-check"><?php if ($postCanManage): ?><input type="checkbox" data-check-all="input[name=&quot;selected_ids[]&quot;]"><?php endif; ?></th>
                                <th class="is-seq">区位</th>
                                <th class="is-title">帖子信息</th>
                                <th class="is-actions">操作</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (!$posts): ?>
                                <tr>
                                    <td colspan="4">
                                        <div class="admin-empty">当前没有符合条件的帖子。</div>
                                    </td>
                                </tr>
                            <?php else: ?>
                            <?php foreach ($posts as $index => $row): ?>
                                <?php
                                $postId = (int) ($row['id'] ?? 0);
                                $status = (string) ($row['status'] ?? 'draft');
                                $segmentNo = max(1, (int) ($row['manage_segment_no'] ?? 1));
                                $segmentSort = max(1, min(5, (int) ($row['manage_segment_sort'] ?? 5)));
                                $postKind = (string) ($row['manage_post_kind'] ?? (((int) ($row['price'] ?? 0) > 0) ? 'data' : 'normal'));
                                $isDataPost = $postKind === 'data';
                                $viewCount = (int) ($row['real_view_count'] ?? 0);
                                $todayViewCount = (int) ($row['today_view_count'] ?? 0);
                                $likeCount = $todayViewCount;
                                $buyerCount = (int) ($row['manage_total_purchase_count'] ?? 0);
                                $postPriceValue = (float) ($row['price'] ?? 0);
                                $priceText = number_format($postPriceValue, 0, '.', ',');
                                $postSaleStatusText = $postPriceValue > 0 ? '出售' : '普通';
                                $postTotalIncomeValue = (float) ($row['manage_total_purchase_income'] ?? 0);
                                $postTotalIncomeText = number_format($postTotalIncomeValue, 0, '.', ',');
                                $recentResultItems = array();
                                $recentWrongIssueTails = array();
                                $recentHitCount = 0;
                                foreach (preg_split('/[\s,，]+/u', trim((string) ($row['manage_recent_result_log'] ?? ''))) as $recentResultItem) {
                                    $recentResultItem = trim((string) $recentResultItem);
                                    if ($recentResultItem === '') {
                                        continue;
                                    }
                                    $recentResultItems[] = $recentResultItem;
                                    if (preg_match('/^(\d+)/u', $recentResultItem, $recentResultIssueMatches)) {
                                        $recentResultIssueTail = $normalizeIssueTail((string) $recentResultIssueMatches[1]);
                                        if (preg_match('/[错錯]/u', $recentResultItem)) {
                                            $recentWrongIssueTails[$recentResultIssueTail] = true;
                                        }
                                    }
                                    if (!preg_match('/[错錯]/u', $recentResultItem)) {
                                        $recentHitCount++;
                                    }
                                }
                                $recentTotalCount = count($recentResultItems);
                                $recentHitSummaryText = $recentTotalCount . '中' . $recentHitCount;
                                $titleText = $stripPostIssuePrefix((string) ($row['title'] ?? ''));
                                $authorDisplayText = trim((string) (($row['author_name'] ?? '') ?: ''));

                                if ($authorDisplayText !== '' && mb_strpos($titleText, $authorDisplayText, 0, 'UTF-8') === false) {
                                    $titleText .= $authorDisplayText;
                                }
                                $currentContentSource = (string) ($row['full_content'] ?? '');
                                if ($currentContentSource === '') {
                                    $currentContentSource = (string) ($row['manage_manual_material'] ?? '');
                                }
                                if ($currentContentSource === '') {
                                    $currentContentSource = (string) ($row['manage_auto_update_content'] ?? '');
                                }
                                if ($currentContentSource === '') {
                                    $currentContentSource = (string) ($row['excerpt'] ?? '');
                                }
                                $currentContentText = preg_replace('/<\s*(br|\/p|\/div|\/li|\/tr)\b[^>]*>/iu', "\n", $currentContentSource);
                                $currentContentText = str_replace(array("\r\n", "\r"), "\n", strip_tags((string) $currentContentText));
                                $currentContentText = trim((string) preg_replace('/[^\S\n]+/u', ' ', $currentContentText));
                                $currentContentText = (string) preg_replace_callback(
                                    '/(?<!\d)((?:\d\s*){1,4})期\s*([：:])/u',
                                    function ($currentContentIssueMatches) {
                                        return preg_replace('/\s+/u', '', $currentContentIssueMatches[1])
                                            . '期'
                                            . $currentContentIssueMatches[2];
                                    },
                                    $currentContentText
                                );
                                $currentContentText = (string) preg_replace_callback(
                                    '/(?<!\d)((?:\d\s*){1,4})期/u',
                                    function ($currentContentIssueMatches) {
                                        return preg_replace('/\s+/u', '', $currentContentIssueMatches[1]) . '期';
                                    },
                                    $currentContentText
                                );
                                $currentContentLines = array();
                                $currentContentPendingIssueDigits = '';
                                foreach (preg_split('/\n+/u', $currentContentText) as $currentContentLine) {
                                    $currentContentLine = trim((string) preg_replace('/[^\S\n]+/u', ' ', $currentContentLine));
                                    if ($currentContentLine === '') {
                                        continue;
                                    }
                                    $currentContentIssueDigitLine = (string) preg_replace('/\s+/u', '', $currentContentLine);
                                    if (preg_match('/^\d+$/u', $currentContentIssueDigitLine)) {
                                        $currentContentPendingIssueDigits .= $currentContentIssueDigitLine;
                                        continue;
                                    }
                                    if ($currentContentPendingIssueDigits !== '') {
                                        if (preg_match('/^(\d{0,4})\s*期\s*([：:]?)(.*)$/u', $currentContentLine, $currentContentIssueLineMatches)) {
                                            $currentContentLine = $currentContentPendingIssueDigits
                                                . $currentContentIssueLineMatches[1]
                                                . '期'
                                                . $currentContentIssueLineMatches[2]
                                                . trim((string) $currentContentIssueLineMatches[3]);
                                            $currentContentPendingIssueDigits = '';
                                        } else {
                                            $currentContentLines[] = $currentContentPendingIssueDigits;
                                            $currentContentPendingIssueDigits = '';
                                        }
                                    }
                                    $currentContentIssueParts = preg_split('/(?<!\d)(?=\d{1,4}期[：:])/u', $currentContentLine, -1, PREG_SPLIT_NO_EMPTY);
                                    foreach ($currentContentIssueParts as $currentContentIssuePart) {
                                        $currentContentIssuePart = trim((string) $currentContentIssuePart);
                                        if ($currentContentIssuePart !== '') {
                                            $currentContentLines[] = $currentContentIssuePart;
                                        }
                                    }
                                }
                                if ($currentContentPendingIssueDigits !== '') {
                                    $currentContentLines[] = $currentContentPendingIssueDigits;
                                }
                                if ($currentContentLines === array()) {
                                    $currentContentLines = array(html_entity_decode('&#26242;&#26080;&#20869;&#23481;', ENT_QUOTES, 'UTF-8'));
                                }
                                $managedOldAuthorDiamondIcon = html_entity_decode('&#128142;', ENT_QUOTES, 'UTF-8');
                                if ($authorDisplayText !== '') {
                                    $managedAuthorLeftIcon = '';
                                    $managedAuthorRightIcon = '';
                                    $managedAuthorPattern = '/(?:([^\p{L}\p{N}\s:：{}（）()【】\[\]]{1,3})\s*)?' . preg_quote($authorDisplayText, '/') . '(?:\s*([^\p{L}\p{N}\s:：{}（）()【】\[\]]{1,3}))?/u';
                                    foreach ($currentContentLines as $currentContentLine) {
                                        if (!preg_match($managedAuthorPattern, (string) $currentContentLine, $managedAuthorMatches)) {
                                            continue;
                                        }
                                        $lineLeftIcon = trim((string) ($managedAuthorMatches[1] ?? ''));
                                        $lineRightIcon = trim((string) ($managedAuthorMatches[2] ?? ''));
                                        if (
                                            $lineLeftIcon !== ''
                                            && $lineLeftIcon === $lineRightIcon
                                            && $lineLeftIcon !== $managedOldAuthorDiamondIcon
                                        ) {
                                            $managedAuthorLeftIcon = $lineLeftIcon;
                                            $managedAuthorRightIcon = $lineRightIcon;
                                            break;
                                        }
                                    }
                                    if ($managedAuthorLeftIcon === '' && $managedAuthorRightIcon === '') {
                                        $managedAuthorIconPairs = \App\Services\AdminService::MANAGED_AUTHOR_ICON_PAIRS;
                                        $managedAuthorIconSeed = abs(crc32('managed-author-icons|' . (string) $postId . '|' . $authorDisplayText . '|' . $currentContentText));
                                        $managedAuthorIconPair = $managedAuthorIconPairs[$managedAuthorIconSeed % count($managedAuthorIconPairs)];
                                        $managedAuthorLeftIcon = $managedAuthorIconPair[0];
                                        $managedAuthorRightIcon = $managedAuthorIconPair[1];
                                    }
                                    foreach ($currentContentLines as $currentContentLineIndex => $currentContentLine) {
                                        $currentContentLines[$currentContentLineIndex] = (string) preg_replace_callback(
                                            $managedAuthorPattern,
                                            static function ($managedAuthorMatches) use ($authorDisplayText, $managedOldAuthorDiamondIcon, $managedAuthorLeftIcon, $managedAuthorRightIcon) {
                                                $leftIcon = trim((string) ($managedAuthorMatches[1] ?? ''));
                                                $rightIcon = trim((string) ($managedAuthorMatches[2] ?? ''));
                                                if (
                                                    $leftIcon !== ''
                                                    && $leftIcon === $rightIcon
                                                    && $leftIcon !== $managedOldAuthorDiamondIcon
                                                ) {
                                                    return (string) ($managedAuthorMatches[0] ?? '');
                                                }

                                                return $managedAuthorLeftIcon . $authorDisplayText . $managedAuthorRightIcon;
                                            },
                                            (string) $currentContentLine,
                                            1
                                        );
                                    }
                                }
                                $currentIssueMaterialText = '';
                                $currentIssueTailForEdit = '';
                                $currentIssueDisplayLineText = '';
                                $currentIssueLabelText = '--' . $issueSuffixText;
                                $currentIssuePreferredTail = $managedCurrentIssueTail !== '--' ? $managedCurrentIssueTail : '';
                                foreach ($currentContentLines as $currentContentLine) {
                                    if (preg_match('/^(\d{1,6})期[：:]?(.*)$/u', $currentContentLine, $currentIssueMaterialMatches)) {
                                        $currentContentLineIssueTail = $normalizeIssueTail($currentIssueMaterialMatches[1]);
                                        if (
                                            $currentIssuePreferredTail !== ''
                                            && $currentContentLineIssueTail !== $currentIssuePreferredTail
                                        ) {
                                            $currentIssueTailForEdit = $currentContentLineIssueTail;
                                            $currentIssueLabelText = $currentIssueTailForEdit . $issueSuffixText;
                                            $currentIssueMaterialText = trim((string) $currentIssueMaterialMatches[2]);
                                            $currentIssueDisplayLineText = $currentContentLine;
                                            continue;
                                        }
                                        $currentIssueTailForEdit = $currentContentLineIssueTail;
                                        $currentIssueLabelText = $currentIssueTailForEdit . $issueSuffixText;
                                        $currentIssueMaterialText = trim((string) $currentIssueMaterialMatches[2]);
                                        $currentIssueDisplayLineText = $currentContentLine;
                                        if ($currentIssuePreferredTail !== '') {
                                            break;
                                        }
                                    }
                                }
                                if ($currentIssueTailForEdit !== '') {
                                    $currentIssueMaterialParsedText = '';
                                    if ($currentIssueDisplayLineText !== '') {
                                        $currentIssueDisplayLineForParse = (string) preg_replace('/\s+/u', ' ', $currentIssueDisplayLineText);
                                        $currentIssueHasAuthorText = $authorDisplayText !== ''
                                            && mb_strpos($currentIssueDisplayLineForParse, $authorDisplayText, 0, 'UTF-8') !== false;
                                        $currentIssuePredictionPattern = $currentIssueHasAuthorText
                                            ? '/^\s*\d{1,6}期[：:]?\s*\S+\s+\S+\s+(.+?)\s+开[：:].*$/u'
                                            : '/^\s*\d{1,6}期[：:]?\s*\S+\s+(.+?)\s+开[：:].*$/u';
                                        if (preg_match($currentIssuePredictionPattern, $currentIssueDisplayLineForParse, $currentIssuePredictionMatches)) {
                                            $currentIssueMaterialParsedText = trim((string) ($currentIssuePredictionMatches[1] ?? ''));
                                        }
                                    }
                                    $currentIssueMaterialText = (string) preg_replace('/\s+/u', ' ', $currentIssueMaterialText);
                                    if ($currentIssueMaterialParsedText !== '') {
                                        $currentIssueMaterialText = $currentIssueMaterialParsedText;
                                    } else {
                                        if ($authorDisplayText !== '') {
                                            $currentIssueMaterialText = trim((string) preg_replace(
                                                '/^(?:[^\p{L}\p{N}\s:：{}（）()【】\[\]]{1,3}\s*)?' . preg_quote($authorDisplayText, '/') . '(?:\s*[^\p{L}\p{N}\s:：{}（）()【】\[\]]{1,3})?\s*/u',
                                                '',
                                                $currentIssueMaterialText,
                                                1
                                            ));
                                        }
                                        foreach ($generatorTemplateLabels as $generatorTemplateLabel) {
                                            if (mb_strpos($currentIssueMaterialText, $generatorTemplateLabel, 0, 'UTF-8') === 0) {
                                                $currentIssueMaterialText = trim((string) mb_substr(
                                                    $currentIssueMaterialText,
                                                    mb_strlen($generatorTemplateLabel, 'UTF-8'),
                                                    mb_strlen($currentIssueMaterialText, 'UTF-8'),
                                                    'UTF-8'
                                                ));
                                                break;
                                            }
                                            foreach ($adminCurrentMaterialBracketPairs as $adminCurrentMaterialBracketPair) {
                                                $currentIssueWrappedTemplateLabel = (string) ($adminCurrentMaterialBracketPair[0] ?? '')
                                                    . $generatorTemplateLabel
                                                    . (string) ($adminCurrentMaterialBracketPair[1] ?? '');
                                                if (mb_strpos($currentIssueMaterialText, $currentIssueWrappedTemplateLabel, 0, 'UTF-8') === 0) {
                                                    $currentIssueMaterialText = trim((string) mb_substr(
                                                        $currentIssueMaterialText,
                                                        mb_strlen($currentIssueWrappedTemplateLabel, 'UTF-8'),
                                                        mb_strlen($currentIssueMaterialText, 'UTF-8'),
                                                        'UTF-8'
                                                    ));
                                                    break 2;
                                                }
                                            }
                                        }
                                        $currentIssueMaterialText = trim((string) preg_replace('/\s+开[：:].*$/u', '', $currentIssueMaterialText));
                                    }
                                }
                                if (
                                    $currentIssueMaterialText !== ''
                                    && mb_strpos($currentIssueMaterialText, '资料等待更新中', 0, 'UTF-8') !== false
                                ) {
                                    $currentIssueMaterialFirstChar = mb_substr($currentIssueMaterialText, 0, 1, 'UTF-8');
                                    $currentIssueMaterialLastChar = mb_substr($currentIssueMaterialText, -1, 1, 'UTF-8');
                                    $currentIssueMaterialWrapped = false;
                                    foreach ($adminCurrentMaterialPredictionBracketPairs as $currentIssueMaterialBracketPair) {
                                        $currentIssueMaterialLeftBracket = (string) ($currentIssueMaterialBracketPair[0] ?? '');
                                        $currentIssueMaterialRightBracket = (string) ($currentIssueMaterialBracketPair[1] ?? '');
                                        if (
                                            $currentIssueMaterialLeftBracket !== ''
                                            && $currentIssueMaterialRightBracket !== ''
                                            && $currentIssueMaterialFirstChar === $currentIssueMaterialLeftBracket
                                            && $currentIssueMaterialLastChar === $currentIssueMaterialRightBracket
                                        ) {
                                            $currentIssueMaterialWrapped = true;
                                            break;
                                        }
                                    }
                                    if (!$currentIssueMaterialWrapped) {
                                        $currentIssueMaterialLeftBracket = '';
                                        $currentIssueMaterialRightBracket = '';
                                        foreach ($currentContentLines as $currentIssueMaterialLine) {
                                            $currentIssueMaterialLineForParse = (string) preg_replace(
                                                '/\s+/u',
                                                ' ',
                                                (string) $currentIssueMaterialLine
                                            );
                                            $currentIssueMaterialHasAuthorText = $authorDisplayText !== ''
                                                && mb_strpos(
                                                    $currentIssueMaterialLineForParse,
                                                    $authorDisplayText,
                                                    0,
                                                    'UTF-8'
                                                ) !== false;
                                            $currentIssueMaterialPredictionPattern = $currentIssueMaterialHasAuthorText
                                                ? '/^\s*\d{1,6}期[：:]?\s*\S+\s+\S+\s+(.+?)\s+开[：:].*$/u'
                                                : '/^\s*\d{1,6}期[：:]?\s*\S+\s+(.+?)\s+开[：:].*$/u';
                                            if (!preg_match(
                                                $currentIssueMaterialPredictionPattern,
                                                $currentIssueMaterialLineForParse,
                                                $currentIssueMaterialPredictionMatches
                                            )) {
                                                continue;
                                            }
                                            $currentIssueMaterialPredictionText = trim(
                                                (string) ($currentIssueMaterialPredictionMatches[1] ?? '')
                                            );
                                            $currentIssueMaterialPredictionFirstChar = mb_substr(
                                                $currentIssueMaterialPredictionText,
                                                0,
                                                1,
                                                'UTF-8'
                                            );
                                            $currentIssueMaterialPredictionLastChar = mb_substr(
                                                $currentIssueMaterialPredictionText,
                                                -1,
                                                1,
                                                'UTF-8'
                                            );
                                            foreach ($adminCurrentMaterialPredictionBracketPairs as $currentIssueMaterialBracketPair) {
                                                $currentIssueRowLeftBracket = (string) ($currentIssueMaterialBracketPair[0] ?? '');
                                                $currentIssueRowRightBracket = (string) ($currentIssueMaterialBracketPair[1] ?? '');
                                                if (
                                                    $currentIssueRowLeftBracket !== ''
                                                    && $currentIssueRowRightBracket !== ''
                                                    && $currentIssueMaterialPredictionFirstChar === $currentIssueRowLeftBracket
                                                    && $currentIssueMaterialPredictionLastChar === $currentIssueRowRightBracket
                                                ) {
                                                    $currentIssueMaterialLeftBracket = $currentIssueRowLeftBracket;
                                                    $currentIssueMaterialRightBracket = $currentIssueRowRightBracket;
                                                    break 2;
                                                }
                                            }
                                        }
                                        if (
                                            ($currentIssueMaterialLeftBracket === '' || $currentIssueMaterialRightBracket === '')
                                            && count($adminCurrentMaterialPredictionBracketPairs) > 0
                                        ) {
                                            $currentIssueMaterialBracketSeed = abs(crc32(
                                                'prediction-bracket|' . (string) $postId . '|' . $currentContentText
                                            ));
                                            $currentIssueMaterialBracketPair = $adminCurrentMaterialPredictionBracketPairs[
                                                $currentIssueMaterialBracketSeed % count($adminCurrentMaterialPredictionBracketPairs)
                                            ];
                                            $currentIssueMaterialLeftBracket = (string) ($currentIssueMaterialBracketPair[0] ?? '');
                                            $currentIssueMaterialRightBracket = (string) ($currentIssueMaterialBracketPair[1] ?? '');
                                        }
                                        if (
                                            $currentIssueMaterialLeftBracket !== ''
                                            && $currentIssueMaterialRightBracket !== ''
                                        ) {
                                            $currentIssueMaterialText = $currentIssueMaterialLeftBracket
                                                . trim($currentIssueMaterialText)
                                                . $currentIssueMaterialRightBracket;
                                        }
                                    }
                                }
                                $currentIssueEditTargetLabel = $currentIssueLabelText;
                                if ($currentIssueEditTargetLabel === '--' . $issueSuffixText && $currentIssuePreferredTail !== '') {
                                    $currentIssueEditTargetLabel = $currentIssuePreferredTail . $issueSuffixText;
                                }
                                $currentIssueEditPayload = app()->posts()->customerServiceEditPayload(array_merge(
                                    $row,
                                    array('full_content' => $currentContentSource)
                                ), $currentIssueEditTargetLabel);
                                $currentIssueEditContent = trim((string) ($currentIssueEditPayload['content'] ?? ''));
                                if ($currentIssueEditContent !== '') {
                                    $currentIssueMaterialText = $currentIssueEditContent;
                                }
                                $currentIssueEditLabel = trim((string) ($currentIssueEditPayload['issue_text'] ?? ''));
                                if ($currentIssueEditLabel !== '') {
                                    $currentIssueEditTail = $normalizeIssueTail($currentIssueEditLabel);
                                    if ($currentIssueEditTail !== '' && $currentIssueEditTail !== '--') {
                                        $currentIssueTailForEdit = $currentIssueEditTail;
                                        $currentIssueLabelText = $currentIssueEditTail . $issueSuffixText;
                                    }
                                }
                                if ($currentIssueTailForEdit === '' && $currentIssuePreferredTail !== '') {
                                    $currentIssueTailForEdit = $currentIssuePreferredTail;
                                    $currentIssueLabelText = $currentIssuePreferredTail . $issueSuffixText;
                                }
                                $currentContentDisplayLines = $currentIssueDisplayLineText !== ''
                                    ? array($currentIssueDisplayLineText)
                                    : $currentContentLines;
                                $currentIssueModalTitleText = trim($currentIssueLabelText . ' ' . $titleText);
                                $publishStatusText = $status === 'published' ? '已发布' : '未发布';
                                $publishStatusTimeText = format_datetime($row['created_at'] ?? null);
                                $deleteAction = $status === 'deleted' ? 'restore' : 'delete';
                                $deleteLabel = $status === 'deleted' ? html_entity_decode('&#24674;&#22797;', ENT_QUOTES, 'UTF-8') : html_entity_decode('&#21024;&#38500;', ENT_QUOTES, 'UTF-8');
                                $editUrl = $postSwitchBaseUrl . '&region=' . urlencode($postCurrentRegion) . '&view=compose&edit=' . $postId;
                                $postUrl = ($postCurrentRegion === 'hongkong' ? public_url('record.php') : public_url('index.php')) . '?open_post=' . $postId;
                                ?>
                                <tr data-post-id="<?php echo e((string) $postId); ?>">
                                    <td class="admin-posts-classic-check-cell">
                                        <?php if ($postCanManage): ?>
                                            <input type="checkbox" name="selected_ids[]" value="<?php echo e((string) $postId); ?>" data-edit-url="<?php echo e($editUrl); ?>">
                                        <?php endif; ?>
                                    </td>
                                    <td class="admin-posts-classic-seq-cell">
                                        <div class="admin-posts-classic-seq-stack">
                                            <div class="admin-posts-classic-seq-row">
                                                <div class="admin-posts-classic-seq-menu" data-post-seq-menu>
                                                    <button class="admin-posts-classic-seq-menu-toggle admin-posts-classic-seq-menu-toggle--segment" type="button" data-post-seq-menu-toggle aria-expanded="false" title="高手榜区"><?php echo e($resolveSegmentLabel($segmentNo)); ?></button>
                                                    <div class="admin-posts-classic-seq-menu-panel" data-post-seq-menu-panel hidden>
                                                    <?php for ($segmentOption = 1; $segmentOption <= 3; $segmentOption++): ?>
                                                        <?php $segmentOptionLabel = $resolveSegmentLabel($segmentOption); ?>
                                                        <button class="admin-posts-classic-seq-menu-option <?php echo $segmentNo === $segmentOption ? 'is-active' : ''; ?>" type="button" data-post-seq-option data-post-id="<?php echo e((string) $postId); ?>" data-post-seq-action="set_segment_no" data-post-seq-value="<?php echo e((string) $segmentOption); ?>" data-post-seq-label="<?php echo e($segmentOptionLabel); ?>"><?php echo e($segmentOptionLabel); ?></button>
                                                    <?php endfor; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="admin-posts-classic-seq-row">
                                                <div class="admin-posts-classic-seq-menu" data-post-seq-menu>
                                                    <button class="admin-posts-classic-seq-menu-toggle admin-posts-classic-seq-menu-toggle--sort" type="button" data-post-seq-menu-toggle aria-expanded="false" title="当前高手区排序位置">置顶<?php echo e((string) $segmentSort); ?></button>
                                                    <div class="admin-posts-classic-seq-menu-panel admin-posts-classic-seq-menu-panel--sort" data-post-seq-menu-panel hidden>
                                                    <?php for ($sortOption = 1; $sortOption <= 5; $sortOption++): ?>
                                                        <button class="admin-posts-classic-seq-menu-option <?php echo $segmentSort === $sortOption ? 'is-active' : ''; ?>" type="button" data-post-seq-option data-post-id="<?php echo e((string) $postId); ?>" data-post-seq-action="set_segment_sort" data-post-seq-value="<?php echo e((string) $sortOption); ?>" data-post-seq-label="置顶<?php echo e((string) $sortOption); ?>">置顶<?php echo e((string) $sortOption); ?></button>
                                                    <?php endfor; ?>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="admin-posts-classic-seq-param">
                                                <span>排序</span><strong><?php echo e((string) $segmentSort); ?></strong>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="admin-posts-classic-title-cell">
                                        <div class="admin-posts-classic-title-layout">
                                            <div class="admin-posts-classic-title-content">
                                                <div class="admin-posts-classic-meta admin-posts-classic-title-row">
                                                    <span class="admin-posts-classic-chip is-counter">今日访问 <strong><?php echo e((string) $likeCount); ?></strong></span>
                                                    <span class="admin-posts-classic-chip is-counter">总访问 <strong><?php echo e((string) $viewCount); ?></strong></span>
                                                    <span class="admin-posts-classic-chip is-counter is-price-param">价格 <strong><?php echo e($priceText); ?></strong></span>
                                                    <span class="admin-posts-classic-chip is-counter is-buyer-param">购买 <strong><?php echo e((string) $buyerCount); ?></strong></span>
                                                    <span class="admin-posts-classic-chip is-counter is-income-param">收入 <strong><?php echo e($postTotalIncomeText); ?></strong></span>
                                                </div>
                                                <div class="admin-posts-classic-title-current admin-posts-classic-title-row">
                                                    <span class="admin-posts-classic-sale-badge <?php echo $postPriceValue > 0 ? 'is-sale' : 'is-normal'; ?>"><?php echo e($postSaleStatusText); ?></span>
                                                    <div class="admin-posts-classic-content-main">
                                                        <?php foreach ($currentContentDisplayLines as $currentContentLine): ?>
                                                            <?php
                                                            $currentContentLineClass = 'admin-posts-classic-content-line';
                                                            $currentContentDisplayLine = app()->admins()->managedForecastDisplayLine(
                                                                (string) ($row['region'] ?? $postCurrentRegion),
                                                                $currentContentLine
                                                            );
                                                            $currentContentLineStats = app()->admins()->managedForecastRecordStats(
                                                                (string) ($row['region'] ?? $postCurrentRegion),
                                                                $currentContentDisplayLine,
                                                                '',
                                                                true,
                                                                false
                                                            );
                                                            $currentContentLineStatus = '';
                                                            if ((int) ($currentContentLineStats['total'] ?? 0) > 0) {
                                                                $currentContentLineStatus = (int) ($currentContentLineStats['hit'] ?? 0) > 0 ? 'hit' : 'miss';
                                                            }
                                                            $currentContentLineIsWrong = preg_match('/[错錯]\s*$/u', $currentContentDisplayLine) === 1;
                                                            if (preg_match('/^(\d{1,4})期/u', $currentContentDisplayLine, $currentContentLineIssueMatches)) {
                                                                $currentContentLineIssueTail = $normalizeIssueTail((string) $currentContentLineIssueMatches[1]);
                                                                if (isset($recentWrongIssueTails[$currentContentLineIssueTail])) {
                                                                    $currentContentLineIsWrong = true;
                                                                }
                                                            }
                                                            if ($currentContentLineIsWrong) {
                                                                $currentContentLineClass .= ' is-wrong';
                                                            }
                                                            ?>
                                                            <div class="<?php echo e($currentContentLineClass); ?>">
                                                                <?php if (preg_match('/^(\d{1,4}期)([：:]?)(.*)$/u', $currentContentDisplayLine, $currentContentLineMatches)): ?>
                                                                    <?php
                                                                    $currentContentLineBodyText = trim((string) $currentContentLineMatches[3]);
                                                                    if ($currentContentLineStatus !== '') {
                                                                        $currentContentLineBodyText = trim((string) preg_replace('/\s*(准|中|赢|發|发|错|錯)\s*$/u', '', $currentContentLineBodyText));
                                                                    }
                                                                    ?>
                                                                    <span class="admin-posts-classic-content-issue"><?php echo e($currentContentLineMatches[1]); ?></span>
                                                                    <?php if ($currentContentLineMatches[2] !== ''): ?>
                                                                        <span class="admin-posts-classic-content-separator"><?php echo e($currentContentLineMatches[2]); ?></span>
                                                                    <?php endif; ?>
                                                                    <span class="admin-posts-classic-content-text"><?php echo e($currentContentLineBodyText); ?></span>
                                                                <?php else: ?>
                                                                    <?php
                                                                    $currentContentLineText = $currentContentLineStatus !== ''
                                                                        ? trim((string) preg_replace('/\s*(准|中|赢|發|发|错|錯)\s*$/u', '', $currentContentDisplayLine))
                                                                        : $currentContentDisplayLine;
                                                                    ?>
                                                                    <span class="admin-posts-classic-content-text"><?php echo e($currentContentLineText); ?></span>
                                                                <?php endif; ?>
                                                                <?php if ($currentContentLineStatus !== ''): ?>
                                                                    <span class="admin-posts-classic-result-badge is-<?php echo e($currentContentLineStatus); ?>"><?php echo $currentContentLineStatus === 'hit' ? '✔' : 'x'; ?></span>
                                                                <?php endif; ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="admin-posts-classic-title-sub admin-posts-classic-title-row">
                                                    <span><?php echo e((string) (($row['section_name'] ?? '') ?: '-')); ?></span>
                                                    <span><?php echo e((string) (($row['category_name'] ?? '') ?: '-')); ?></span>
                                                    <span>作者 <?php echo e((string) (($row['author_name'] ?? '') ?: '-')); ?></span>
                                                    <span><?php echo e($publishStatusText . ' ' . $publishStatusTimeText); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="admin-posts-classic-actions-cell">
                                        <?php if ($postCanManage): ?>
                                            <div class="admin-posts-classic-title-material-actions">
                                                <button class="admin-posts-classic-row-btn is-danger" type="button" data-post-id="<?php echo e((string) $postId); ?>" data-post-quick-action="<?php echo e($deleteAction); ?>" title="<?php echo e($status === 'deleted' ? html_entity_decode('&#24674;&#22797;&#21040;&#21069;&#21488;', ENT_QUOTES, 'UTF-8') : html_entity_decode('&#31227;&#20837;&#22238;&#25910;&#31449;', ENT_QUOTES, 'UTF-8')); ?>"><?php echo e($deleteLabel); ?></button>
                                                <button
                                                    class="admin-posts-classic-row-btn admin-posts-classic-current-material-open"
                                                    type="button"
                                                    data-post-id="<?php echo e((string) $postId); ?>"
                                                    data-post-current-issue-tail="<?php echo e($currentIssueTailForEdit); ?>"
                                                    data-post-current-issue-label="<?php echo e($currentIssueLabelText); ?>"
                                                    data-post-current-modal-title="<?php echo e($currentIssueModalTitleText); ?>"
                                                    data-post-current-display-line="<?php echo e($currentIssueDisplayLineText); ?>"
                                                    data-post-current-price="<?php echo e((string) max(0, (int) $postPriceValue)); ?>"
                                                    data-post-current-material-open
                                                >编辑资料</button>
                                                <textarea hidden data-post-current-material-source><?php echo e($currentIssueMaterialText); ?></textarea>
                                            </div>
                                        <?php else: ?>
                                            <span class="admin-help">当前账号没有管理权限</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if ($postCanManage): ?>
                    <form method="post" action="<?php echo e(public_url('admin.php') . '?page=posts'); ?>" data-posts-quick-form hidden>
                        <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.posts')); ?>">
                        <input type="hidden" name="_admin_form" value="page">
                        <input type="hidden" name="_admin_action" value="post_quick_action">
                        <input type="hidden" name="region" value="<?php echo e($postCurrentRegion); ?>">
                        <input type="hidden" name="view" value="<?php echo e($postQuickMode); ?>">
                        <input type="hidden" name="target_post_id" value="">
                        <input type="hidden" name="quick_action" value="">
                        <input type="hidden" name="value" value="">
                        <input type="hidden" name="buyer_count" value="">
                        <input type="hidden" name="mark" value="">
                        <input type="hidden" name="content" value="">
                        <input type="hidden" name="price" value="">
                    </form>
                    <div class="admin-posts-current-material-modal" data-post-current-material-modal hidden aria-hidden="true">
                        <button class="admin-posts-current-material-backdrop" type="button" data-post-current-material-close aria-label="关闭弹窗"></button>
                        <div class="admin-posts-current-material-dialog" role="dialog" aria-modal="true" aria-labelledby="admin-posts-current-material-title">
                            <div class="admin-posts-current-material-dialog-head">
                                <div>
                                    <div class="admin-posts-current-material-title" id="admin-posts-current-material-title">编辑资料</div>
                                    <div class="admin-posts-current-material-issue" data-post-current-material-modal-issue>--期</div>
                                </div>
                                <button class="admin-posts-current-material-close" type="button" data-post-current-material-close aria-label="关闭">×</button>
                            </div>
                            <div class="admin-posts-current-material-brackets" aria-label="资料内容括号快捷插入">
                                <?php foreach ($adminCurrentMaterialBracketPairs as $adminCurrentMaterialBracketPair): ?>
                                    <?php
                                    $adminCurrentMaterialBracketLeft = (string) ($adminCurrentMaterialBracketPair[0] ?? '');
                                    $adminCurrentMaterialBracketRight = (string) ($adminCurrentMaterialBracketPair[1] ?? '');
                                    ?>
                                    <button
                                        type="button"
                                        data-post-current-material-bracket
                                        data-post-current-material-bracket-left="<?php echo e($adminCurrentMaterialBracketLeft); ?>"
                                        data-post-current-material-bracket-right="<?php echo e($adminCurrentMaterialBracketRight); ?>"
                                        aria-label="<?php echo e('插入' . $adminCurrentMaterialBracketLeft . $adminCurrentMaterialBracketRight); ?>"
                                    ><?php echo e($adminCurrentMaterialBracketLeft . $adminCurrentMaterialBracketRight); ?></button>
                                <?php endforeach; ?>
                            </div>
                            <textarea class="admin-posts-current-material-textarea" data-post-current-material-modal-input></textarea>
                            <div class="admin-posts-current-material-actions">
                                <label class="admin-posts-current-material-price-field" for="admin-posts-current-material-price">
                                    <span>出售价格</span>
                                    <input class="admin-posts-current-material-price-input" id="admin-posts-current-material-price" type="number" min="0" step="1" value="0" data-post-current-material-modal-price>
                                </label>
                                <button class="admin-posts-classic-row-btn admin-posts-classic-current-issue-save" type="button" data-post-current-material-modal-save>保存</button>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($showPostGeneratorSection): ?>
    <div class="admin-card front-card admin-posts-generator-card" id="post-generator-card">
        <div class="admin-posts-card-titlebar">
            <?php echo $postNavigationFrameHtml; ?>
        </div>

        <?php if ($postCanManage): ?>
            <form method="post" action="<?php echo e(public_url('admin.php') . '?page=posts'); ?>" class="mt-4 admin-posts-generator-form" id="admin-post-generator-form" data-posts-generator-form data-default-issue-tail="<?php echo e($generatorCurrentIssueTail); ?>" data-default-title-prefix="<?php echo e($generatorTitlePrefix); ?>" data-default-title-middle="<?php echo e($generatorTitleMiddle); ?>" data-default-title-prefix-color-mode="<?php echo e($generatorTitlePrefixColorMode); ?>" data-default-title-prefix-color-value="<?php echo e($generatorTitlePrefixColorValue); ?>" data-default-title-middle-color-mode="<?php echo e($generatorTitleMiddleColorMode); ?>" data-default-title-middle-color-value="<?php echo e($generatorTitleMiddleColorValue); ?>" data-default-author-nickname-color-mode="<?php echo e($generatorAuthorNicknameColorMode); ?>" data-default-author-nickname-color-value="<?php echo e($generatorAuthorNicknameColorValue); ?>" data-default-author-nickname="<?php echo e($generatorAuthorNickname); ?>" data-default-author-nickname-pool="<?php echo e($generatorAuthorNicknamePoolRaw); ?>">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.posts')); ?>">
                <input type="hidden" name="_admin_form" value="page">
                <input type="hidden" name="_admin_action" value="generate_posts">
                <input type="hidden" name="region" value="<?php echo e($postCurrentRegion); ?>">
                <input type="hidden" name="view" value="<?php echo e($postFormViewTarget); ?>">

                <div class="admin-posts-generator-table admin-posts-generator-table--rules">
                    <div class="admin-posts-generator-table-title">生成规则设置</div>
                    <div class="admin-posts-generator-table-row admin-posts-generator-table-row--nowrap">
                        <div class="admin-posts-generator-label-cell">生肖</div>
                        <div class="admin-posts-generator-content-cell admin-posts-generator-content-cell--nowrap">
                            <div class="admin-posts-generator-check-grid is-zodiac-grid">
                                <?php foreach ($generatorZodiacOptions as $option): ?>
                                    <?php echo $generatorRenderCheckbox('target_zodiac', (string) $option['value'], (string) $option['label'], isset($generatorSelectedZodiac[(string) $option['value']])); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="admin-posts-generator-table-row admin-posts-generator-table-row--nowrap">
                        <div class="admin-posts-generator-label-cell">号码</div>
                        <div class="admin-posts-generator-content-cell admin-posts-generator-content-cell--nowrap">
                            <div class="admin-posts-generator-number-matrix">
                                <?php foreach ($generatorNumberRows as $numberRow): ?>
                                    <div class="admin-posts-generator-check-grid is-number-grid">
                                        <?php foreach ($numberRow as $option): ?>
                                            <?php
                                            $numberValue = (string) ($option['value'] ?? '');
                                            $numberWave = (string) ($option['wave'] ?? 'green');
                                            ?>
                                            <?php echo $generatorRenderCheckbox('target_number', $numberValue, (string) ($option['label'] ?? $numberValue), isset($generatorSelectedNumber[$numberValue]), 'is-' . $numberWave . ' is-number'); ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="admin-posts-generator-table-row">
                        <div class="admin-posts-generator-label-cell">波色</div>
                        <div class="admin-posts-generator-content-cell admin-posts-generator-content-cell--single-line">
                            <div class="admin-posts-generator-check-grid is-compact-grid">
                                <?php foreach ($generatorWaveOptions as $option): ?>
                                    <?php
                                    $waveValue = (string) ($option['value'] ?? '');
                                    $waveClass = 'is-green';
                                    if ($waveValue === html_entity_decode('&#32418;', ENT_QUOTES, 'UTF-8')) {
                                        $waveClass = 'is-red';
                                    } elseif ($waveValue === html_entity_decode('&#34013;', ENT_QUOTES, 'UTF-8')) {
                                        $waveClass = 'is-blue';
                                    }
                                    ?>
                                    <?php echo $generatorRenderCheckbox('target_wave', $waveValue, (string) ($option['label'] ?? $waveValue), isset($generatorSelectedWave[$waveValue]), $waveClass); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="admin-posts-generator-table-row">
                        <div class="admin-posts-generator-label-cell">五行</div>
                        <div class="admin-posts-generator-content-cell admin-posts-generator-content-cell--single-line">
                            <div class="admin-posts-generator-check-grid is-compact-grid">
                                <?php foreach ($generatorElementOptions as $option): ?>
                                    <?php $elementValue = (string) ($option['value'] ?? ''); ?>
                                    <?php echo $generatorRenderCheckbox('target_element', $elementValue, (string) ($option['label'] ?? $elementValue), isset($generatorSelectedElement[$elementValue])); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="admin-posts-generator-table-row">
                        <div class="admin-posts-generator-label-cell">头数</div>
                        <div class="admin-posts-generator-content-cell admin-posts-generator-content-cell--single-line">
                            <div class="admin-posts-generator-check-grid is-compact-grid">
                                <?php foreach ($generatorHeadOptions as $option): ?>
                                    <?php $headValue = (string) ($option['value'] ?? ''); ?>
                                    <?php echo $generatorRenderCheckbox('target_head', $headValue, (string) ($option['label'] ?? $headValue), isset($generatorSelectedHead[$headValue])); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <div class="admin-posts-generator-table-row">
                        <div class="admin-posts-generator-label-cell">尾数</div>
                        <div class="admin-posts-generator-content-cell">
                            <div class="admin-posts-generator-check-grid is-compact-grid">
                                <?php foreach ($generatorTailOptions as $option): ?>
                                    <?php $tailValue = (string) ($option['value'] ?? ''); ?>
                                    <?php echo $generatorRenderCheckbox('target_tail', $tailValue, (string) ($option['label'] ?? $tailValue), isset($generatorSelectedTail[$tailValue])); ?>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="admin-posts-generator-table admin-posts-generator-table--compact admin-posts-generator-table--templates mt-4">
                    <div class="admin-posts-generator-table-title admin-posts-generator-table-title--with-action">
                        <span class="admin-posts-generator-table-title-text">生成帖子类型设置</span>
                        <div class="admin-posts-generator-settings-save" aria-label="保存站点标题、帖子类型、作者昵称和标题样式设置">
                            <button class="admin-button admin-posts-generator-save-settings" type="submit" form="admin-post-generator-form" name="_admin_action_override" value="save_post_generator_settings">保存设置</button>
                        </div>
                    </div>
                    <div class="admin-posts-generator-table-row admin-posts-generator-table-row--settings admin-posts-generator-table-row--settings-full">
                        <div class="admin-posts-generator-content-cell">
                            <div class="admin-posts-generator-settings-board">
                                <input type="hidden" name="generator_type" value="<?php echo e($postCurrentRegion); ?>">
                                <input type="hidden" name="segment_no" value="<?php echo e((string) $generatorCurrentSegment); ?>">
                                <input type="hidden" name="top_scope" value="<?php echo e($generatorCurrentTopScope); ?>">

                                <div class="admin-posts-generator-settings-line-table" aria-label="生成帖子标题设置">
                                    <div class="admin-posts-generator-settings-issue" aria-label="当前期数">
                                        <input type="hidden" name="current_issue_tail" value="<?php echo e($generatorCurrentIssueTail); ?>">
                                        <span class="admin-posts-generator-issue-capsule">
                                            <span class="admin-posts-generator-issue-pill"><?php echo e($generatorCurrentIssueTail); ?></span>
                                        </span>
                                        <span class="admin-posts-generator-helper admin-posts-generator-helper--region-icon <?php echo $postCurrentRegion === 'hongkong' ? 'is-hongkong' : 'is-macau'; ?>"><?php echo e($postCurrentRegion === 'hongkong' ? '香港' : '澳门'); ?></span>
                                    </div>
                                    <div class="admin-posts-generator-settings-grid">
                                        <div class="admin-posts-generator-settings-row">
                                            <label class="admin-posts-generator-setting-control is-title-control">
                                                <span class="admin-posts-generator-inline-label">站点标题</span>
                                                <input class="admin-input" type="text" name="title_prefix" value="<?php echo e($generatorTitlePrefix); ?>">
                                                <select class="admin-select" name="title_prefix_color_mode" data-generator-color-mode="title_prefix">
                                                    <option value="" <?php echo $generatorTitlePrefixColorMode === '' ? 'selected' : ''; ?>>不设置</option>
                                                    <option value="fixed" <?php echo $generatorTitlePrefixColorMode === 'fixed' ? 'selected' : ''; ?>>固定颜色</option>
                                                    <option value="daily_random" <?php echo $generatorTitlePrefixColorMode === 'daily_random' ? 'selected' : ''; ?>>每日随机</option>
                                                </select>
                                                <span class="admin-posts-generator-title-color-wrap" data-generator-color-value-wrap="title_prefix"<?php echo $generatorTitlePrefixColorMode === 'fixed' ? '' : ' hidden'; ?>>
                                                    <input class="admin-input admin-input--color" type="color" name="title_prefix_color_value" value="<?php echo e($generatorTitlePrefixColorValue); ?>" data-generator-color-value="title_prefix">
                                                </span>
                                            </label>
                                            <label class="admin-posts-generator-setting-control is-type-control">
                                                <span class="admin-posts-generator-inline-label">帖子类型</span>
                                                <input class="admin-input" type="text" name="title_middle" value="<?php echo e($generatorTitleMiddle); ?>" placeholder="<?php echo e('[帖子类型]'); ?>">
                                                <select class="admin-select admin-posts-generator-middle-wrap-select" name="title_middle_wrap">
                                                    <option value="" <?php echo $generatorTitleMiddleWrap === '' ? 'selected' : ''; ?>>无</option>
                                                    <?php foreach ($generatorTitleMiddleWrapOptions as $wrapOption): ?>
                                                        <option value="<?php echo e($wrapOption); ?>" <?php echo $generatorTitleMiddleWrap === $wrapOption ? 'selected' : ''; ?>><?php echo e($wrapOption); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <select class="admin-select" name="title_middle_color_mode" data-generator-color-mode="title_middle">
                                                    <option value="" <?php echo $generatorTitleMiddleColorMode === '' ? 'selected' : ''; ?>>不设置</option>
                                                    <option value="fixed" <?php echo $generatorTitleMiddleColorMode === 'fixed' ? 'selected' : ''; ?>>固定颜色</option>
                                                    <option value="daily_random" <?php echo $generatorTitleMiddleColorMode === 'daily_random' ? 'selected' : ''; ?>>每日随机</option>
                                                </select>
                                                <span class="admin-posts-generator-title-color-wrap" data-generator-color-value-wrap="title_middle"<?php echo $generatorTitleMiddleColorMode === 'fixed' ? '' : ' hidden'; ?>>
                                                    <input class="admin-input admin-input--color" type="color" name="title_middle_color_value" value="<?php echo e($generatorTitleMiddleColorValue); ?>" data-generator-color-value="title_middle">
                                                </span>
                                            </label>
                                            <label class="admin-posts-generator-setting-control is-author-control">
                                                <span class="admin-posts-generator-inline-label">作者昵称</span>
                                                <input type="hidden" name="author_nickname_pool" value="<?php echo e($generatorAuthorNicknamePoolRaw); ?>" data-author-nickname-pool>
                                                <input class="admin-input" type="text" name="author_nickname" value="<?php echo e($generatorAuthorNickname); ?>" placeholder="<?php echo e('[随机作者]'); ?>">
                                                <select class="admin-select" name="author_nickname_color_mode" data-generator-color-mode="author_nickname">
                                                    <option value="" <?php echo $generatorAuthorNicknameColorMode === '' ? 'selected' : ''; ?>>不设置</option>
                                                    <option value="fixed" <?php echo $generatorAuthorNicknameColorMode === 'fixed' ? 'selected' : ''; ?>>固定颜色</option>
                                                    <option value="daily_random" <?php echo $generatorAuthorNicknameColorMode === 'daily_random' ? 'selected' : ''; ?>>每日随机</option>
                                                </select>
                                                <span class="admin-posts-generator-title-color-wrap" data-generator-color-value-wrap="author_nickname"<?php echo $generatorAuthorNicknameColorMode === 'fixed' ? '' : ' hidden'; ?>>
                                                    <input class="admin-input admin-input--color" type="color" name="author_nickname_color_value" value="<?php echo e($generatorAuthorNicknameColorValue); ?>" data-generator-color-value="author_nickname">
                                                </span>
                                            </label>
                                        </div>
                                        <div class="admin-posts-generator-settings-row is-secondary-row">
                                            <label class="admin-posts-generator-setting-control is-size-control">
                                                <span class="admin-posts-generator-inline-label">标题字号</span>
                                                <select class="admin-select" name="title_font_size">
                                                    <option value="" <?php echo $generatorTitleFontSize === '' ? 'selected' : ''; ?>>不设置</option>
                                                    <?php foreach ($generatorTitleFontSizeOptions as $fontSizeOption): ?>
                                                        <option value="<?php echo e($fontSizeOption); ?>" <?php echo $generatorTitleFontSize === $fontSizeOption ? 'selected' : ''; ?>><?php echo e($fontSizeOption); ?>px</option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label class="admin-posts-generator-setting-control is-weight-control">
                                                <span class="admin-posts-generator-inline-label">标题粗细</span>
                                                <select class="admin-select" name="title_font_weight">
                                                    <option value="" <?php echo $generatorTitleFontWeight === '' ? 'selected' : ''; ?>>不设置</option>
                                                    <?php foreach ($generatorTitleFontWeightOptions as $fontWeightOption): ?>
                                                        <option value="<?php echo e($fontWeightOption); ?>" <?php echo $generatorTitleFontWeight === $fontWeightOption ? 'selected' : ''; ?>><?php echo e($fontWeightOption); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <div class="admin-posts-generator-setting-control is-segment-control" aria-label="高手榜区">
                                                <span class="admin-posts-generator-inline-label">高手榜区</span>
                                                <input type="hidden" name="preset_segments_submitted" value="1">
                                                <div class="admin-posts-generator-segment-controls">
                                                    <?php foreach ($generatorSegmentOptions as $option): ?>
                                                        <?php $segmentValue = (string) ($option['value'] ?? '1'); ?>
                                                        <label>
                                                            <input type="checkbox" name="preset_segments[]" value="<?php echo e($segmentValue); ?>" <?php echo isset($generatorSelectedPresetSegments[$segmentValue]) ? 'checked' : ''; ?>>
                                                            <b><?php echo e($segmentValue); ?></b>
                                                        </label>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="post_update_time" value="<?php echo e($generatorPostUpdateTime); ?>">
                                <input type="hidden" name="material_content_time" value="<?php echo e($generatorMaterialContentTime); ?>">
                                <input type="hidden" name="sale_material_content_time" value="<?php echo e($generatorSaleMaterialContentTime); ?>">
                                <input type="hidden" name="is_blank_content" value="<?php echo !empty($postGeneratorState['is_blank_content']) ? '1' : ''; ?>" data-material-content-value>
                                <input type="hidden" name="preset_zodiac_min" value="<?php echo e($generatorPresetZodiacMin); ?>">
                                <input type="hidden" name="preset_zodiac_max" value="<?php echo e($generatorPresetZodiacMax); ?>">
                                <input type="hidden" name="preset_segment_min" value="<?php echo e($generatorPresetSegmentMin); ?>">
                                <input type="hidden" name="preset_segment_max" value="<?php echo e($generatorPresetSegmentMax); ?>">
                                <input type="hidden" name="preset_record_min" value="<?php echo e($generatorPresetRecordMin); ?>">
                                <input type="hidden" name="preset_record_max" value="<?php echo e($generatorPresetRecordMax); ?>">
                                <input type="hidden" name="preset_record_rate_min" value="<?php echo e($generatorPresetRecordRateMin); ?>">
                                <input type="hidden" name="preset_record_rate_max" value="<?php echo e($generatorPresetRecordRateMax); ?>">
                                <input type="hidden" name="is_fake_after_open" value="<?php echo !empty($postGeneratorState['is_fake_after_open']) ? '1' : ''; ?>">
                                <input type="hidden" name="after_draw_delete_wrong_streak" value="<?php echo e($generatorAfterDrawDeleteWrongStreak); ?>">

                                <div class="admin-posts-generator-action-row admin-posts-generator-action-table">
                                    <div class="admin-posts-generator-action-cell admin-posts-generator-action-cell--material">
                                        <label class="admin-posts-generator-check is-flag admin-posts-generator-material-toggle<?php echo empty($postGeneratorState['is_blank_content']) ? ' is-has-material' : ''; ?>" data-material-content-pill>
                                            <input type="checkbox" value="1" data-material-content-toggle <?php echo empty($postGeneratorState['is_blank_content']) ? 'checked' : ''; ?>>
                                            <span data-material-content-label><?php echo empty($postGeneratorState['is_blank_content']) ? '有资料' : '无资料'; ?></span>
                                        </label>
                                    </div>
                                    <div class="admin-posts-generator-action-cell admin-posts-generator-action-cell--submit">
                                        <button class="admin-button admin-posts-generator-submit" type="submit"><?php echo e($postGenerateLabel); ?></button>
                                    </div>
                                    <div class="admin-posts-generator-action-cell admin-posts-generator-action-cell--clear">
                                        <button class="admin-button is-light admin-posts-generator-clear" type="button" data-post-generator-clear>清空条件</button>
                                    </div>
                                    <div class="admin-posts-generator-action-cell admin-posts-generator-action-cell--summary">
                                        <div class="admin-posts-generator-summary admin-posts-generator-summary--status" data-post-generator-summary data-total-post-count="<?php echo e((string) $generatorSummaryTotalCount); ?>" data-segment-one-count="<?php echo e((string) $segmentPostCounts[1]); ?>" data-segment-two-count="<?php echo e((string) $segmentPostCounts[2]); ?>" data-segment-three-count="<?php echo e((string) $segmentPostCounts[3]); ?>"><?php echo $generatorSummaryHtml; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php foreach ($generatorTemplateGroups as $group): ?>
                        <?php
                        $groupLabel = (string) ($group['label'] ?? '模板');
                        $groupLabelParts = array($groupLabel);
                        if ($groupLabel === '波色号码') {
                            $groupLabelParts = array('波色', '号码');
                        } elseif ($groupLabel === '大小单双') {
                            $groupLabelParts = array('大小', '单双');
                        }
                        ?>
                        <div class="admin-posts-generator-table-row">
                            <div class="admin-posts-generator-label-cell admin-posts-generator-label-cell--template<?php echo count($groupLabelParts) > 1 ? ' is-stacked' : ''; ?>">
                                <?php if (count($groupLabelParts) > 1): ?>
                                    <span class="admin-posts-generator-label-stack">
                                        <?php foreach ($groupLabelParts as $groupLabelPart): ?>
                                            <span><?php echo e($groupLabelPart); ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                <?php else: ?>
                                    <?php echo e($groupLabel); ?>
                                <?php endif; ?>
                            </div>
                            <div class="admin-posts-generator-content-cell">
                                <div class="admin-posts-generator-template-grid">
                                    <?php foreach ((array) ($group['items'] ?? array()) as $item): ?>
                                        <?php $templateKey = (string) ($item['key'] ?? ''); ?>
                                        <?php echo $generatorRenderCheckbox('templates', $templateKey, (string) ($item['label'] ?? $templateKey), false, 'is-template'); ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </form>
        <?php else: ?>
            <div class="admin-empty mt-4">当前账号没有生成权限，请联系管理员开通。</div>
        <?php endif; ?>
    </div>
    <?php elseif ($showPostFormSection): ?>
    <div class="admin-card front-card" id="post-form-card">
        <div class="admin-posts-card-titlebar">
            <?php echo $postNavigationFrameHtml; ?>
        </div>
        <div class="admin-card-subtitle"><?php echo e($postFormSubtitle); ?></div>

        <?php if ($postCanManage): ?>
            <form method="post" action="<?php echo e(public_url('admin.php') . '?page=posts'); ?>" class="mt-4" data-post-form>
                <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.posts')); ?>">
                <input type="hidden" name="_admin_form" value="page">
                <input type="hidden" name="_admin_action" value="save_post">
                <input type="hidden" name="id" value="<?php echo e((string) ($postForm['id'] ?? 0)); ?>">
                <input type="hidden" name="region" value="<?php echo e($postCurrentRegion); ?>">
                <input type="hidden" name="view" value="compose">

                <?php if ($showPostEditSection): ?>
                <div class="mt-4">
                    <label class="admin-label">高手区</label>
                    <select class="admin-select" name="segment_no" title="用于选择帖子归属的高手分区">
                        <?php foreach ($generatorSegmentOptions as $option): ?>
                            <?php $segmentValue = max(1, (int) ($option['value'] ?? 1)); ?>
                            <option value="<?php echo e((string) $segmentValue); ?>" <?php echo (int) ($postForm['segment_no'] ?? 1) === $segmentValue ? 'selected' : ''; ?>><?php echo e((string) ($option['label'] ?? (html_entity_decode('&#39640;&#25163;', ENT_QUOTES, 'UTF-8') . $segmentValue))); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="mt-4">
                    <label class="admin-label">帖子标题</label>
                    <div class="admin-form-grid-4 admin-post-title-grid">
                        <div>
                            <label class="admin-label">站点标题</label>
                            <input class="admin-input" name="title_prefix" value="<?php echo e((string) ($postForm['title_prefix'] ?? '')); ?>" placeholder="&#40511;&#36816;&#20845;&#21512;" data-post-title-part="prefix">
                        </div>
                        <div>
                            <label class="admin-label">帖子类型</label>
                            <input class="admin-input" name="title_middle" value="<?php echo e((string) ($postForm['title_middle'] ?? '')); ?>" placeholder="&#12304;&#19968;&#30721;&#20013;&#29305;&#12305;" data-post-title-part="middle">
                        </div>
                        <div>
                            <label class="admin-label">作者昵称</label>
                            <input class="admin-input" name="title_suffix" value="<?php echo e((string) ($postForm['title_suffix'] ?? '')); ?>" placeholder="&#26399;&#26399;&#20013;&#22870;" data-post-title-part="suffix">
                        </div>
                        <div>
                            <label class="admin-label">作者昵称</label>
                            <input class="admin-input" name="author_nickname" value="<?php echo e((string) ($postForm['author_nickname'] ?? '')); ?>" placeholder="&#26399;&#26399;&#20013;&#22870;">
                        </div>
                    </div>

                    <div class="admin-post-title-preview">
                        <span class="admin-post-title-preview-label">&#26631;&#39064;&#39044;&#35272;:</span>
                        <span class="admin-post-title-preview-value" data-post-title-preview><?php echo e($postTitleIssueText . ': ' . ((((string) ($postForm['title_prefix'] ?? '') !== '' || (string) ($postForm['title_middle'] ?? '') !== '') ? ((string) ($postForm['title_prefix'] ?? '') . (string) ($postForm['title_middle'] ?? '')) : html_entity_decode('&#40511;&#36816;&#20845;&#21512;&#12304;&#19968;&#30721;&#20013;&#29305;&#12305;', ENT_QUOTES, 'UTF-8')) . (((string) ($postForm['author_nickname'] ?? '') !== '') ? (string) ($postForm['author_nickname'] ?? '') : html_entity_decode('&#26399;&#26399;&#20013;&#22870;', ENT_QUOTES, 'UTF-8')))); ?></span>
                    </div>
                </div>

                <input type="hidden" name="color_tag" value="<?php echo e((string) ($postForm['color_tag'] ?? 'slate')); ?>">
                <input type="hidden" name="status" value="<?php echo e((string) ($postForm['status'] ?? 'published')); ?>">
                <textarea name="excerpt" hidden><?php echo e((string) ($postForm['excerpt'] ?? '')); ?></textarea>
                <textarea name="preview_content" hidden><?php echo e((string) ($postForm['preview_content'] ?? '')); ?></textarea>

                <div class="mt-4">
                    <label class="admin-label">销售价格</label>
                    <input class="admin-input" type="number" name="price" value="<?php echo e((string) ($postForm['price'] ?? '0')); ?>" step="1" min="0" placeholder="0">
                </div>

                <div class="mt-4">
                    <label class="admin-label">正文内容</label>
                    <div class="admin-editor-shell admin-post-editor-shell">
                        <div class="admin-editor-boot-placeholder" data-post-editor-placeholder>编辑器加载中...</div>
                        <textarea class="admin-textarea is-editor-booting" id="post-full-content-editor" name="full_content"><?php echo e((string) ($postForm['full_content'] ?? '')); ?></textarea>
                    </div>
                </div>

                <div class="mt-4">
                    <label class="admin-label">置顶选项</label>
                    <div class="admin-check-row">
                        <label class="admin-check-item"><input type="checkbox" name="is_top_normal" value="1" <?php echo (string) ($postForm['is_top_normal'] ?? '0') === '1' ? 'checked' : ''; ?>><span>普通置顶</span></label>
                        <label class="admin-check-item"><input type="checkbox" name="is_top_admin" value="1" <?php echo (string) ($postForm['is_top_admin'] ?? '0') === '1' ? 'checked' : ''; ?>><span>后台置顶</span></label>
                        <label class="admin-check-item"><input type="checkbox" name="is_top_forever" value="1" <?php echo (string) ($postForm['is_top_forever'] ?? '0') === '1' ? 'checked' : ''; ?>><span>永久置顶</span></label>
                    </div>
                </div>

                <div class="admin-form-actions">
                    <button class="admin-button" type="submit">保存帖子</button>
                    <a class="admin-button is-light" href="<?php echo e($postComposeUrl); ?>">返回发帖页</a>
                </div>
            </form>
        <?php else: ?>
            <div class="admin-empty mt-4">当前账号没有发帖权限，请联系管理员开通。</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>

<?php if ($postCanManage && $postQuickMode === 'manage'): ?>
<script>
(function () {
    var settingsForm = document.getElementById('admin-post-generator-inline-form');
    var settingControls = Array.prototype.slice.call(document.querySelectorAll('[data-generator-setting-control]'));
    var postUpdateTimeToggle = document.querySelector('[data-post-update-time-toggle]');
    var postUpdateTimeInput = document.querySelector('[data-post-update-time-input]');
    var postUpdateTimeValue = settingsForm ? settingsForm.querySelector('[data-post-update-time-value]') : null;
    var materialTimeToggle = document.querySelector('[data-material-content-time-toggle]');
    var materialTimeInput = document.querySelector('[data-material-content-time-input]');
    var materialTimeValue = settingsForm ? settingsForm.querySelector('[data-material-content-time-value]') : null;
    var saleMaterialTimeToggle = document.querySelector('[data-sale-material-content-time-toggle]');
    var saleMaterialTimeInput = document.querySelector('[data-sale-material-content-time-input]');
    var saleMaterialTimeValue = settingsForm ? settingsForm.querySelector('[data-sale-material-content-time-value]') : null;
    var materialContentToggle = document.querySelector('[data-material-content-toggle]');
    var materialContentValue = settingsForm ? settingsForm.querySelector('[data-material-content-value]') : null;
    var materialContentLabel = document.querySelector('[data-material-content-label]');
    var materialContentPill = document.querySelector('[data-material-content-pill]');
    var fakeAfterOpenToggle = document.querySelector('[data-fake-after-open-toggle]');
    var autoReplyEnabledInput = document.querySelector('[data-auto-reply-enabled-input]');
    var autoReplyEnabledValue = settingsForm ? settingsForm.querySelector('[data-auto-reply-enabled-value]') : null;
    var autoReplyCountInput = document.querySelector('[data-auto-reply-count-input]');
    var autoReplyCountValue = settingsForm ? settingsForm.querySelector('[data-auto-reply-count-value]') : null;
    var autoReplyBaseMinInput = document.querySelector('[data-auto-reply-base-min-input]');
    var autoReplyBaseMaxInput = document.querySelector('[data-auto-reply-base-max-input]');
    var autoReplyBaseMinValue = settingsForm ? settingsForm.querySelector('[data-auto-reply-base-min-value]') : null;
    var autoReplyBaseMaxValue = settingsForm ? settingsForm.querySelector('[data-auto-reply-base-max-value]') : null;
    var autoReplyDailyMinInput = document.querySelector('[data-auto-reply-daily-min-input]');
    var autoReplyDailyMaxInput = document.querySelector('[data-auto-reply-daily-max-input]');
    var autoReplyDailyMinValue = settingsForm ? settingsForm.querySelector('[data-auto-reply-daily-min-value]') : null;
    var autoReplyDailyMaxValue = settingsForm ? settingsForm.querySelector('[data-auto-reply-daily-max-value]') : null;
    var autoReplyIssueMinInput = document.querySelector('[data-auto-reply-issue-min-input]');
    var autoReplyIssueMaxInput = document.querySelector('[data-auto-reply-issue-max-input]');
    var autoReplyIssueMinValue = settingsForm ? settingsForm.querySelector('[data-auto-reply-issue-min-value]') : null;
    var autoReplyIssueMaxValue = settingsForm ? settingsForm.querySelector('[data-auto-reply-issue-max-value]') : null;
    var autoReplyForbidStartHourInput = document.querySelector('[data-auto-reply-forbid-start-hour-input]');
    var autoReplyForbidEndHourInput = document.querySelector('[data-auto-reply-forbid-end-hour-input]');
    var autoReplyForbidStartHourValue = settingsForm ? settingsForm.querySelector('[data-auto-reply-forbid-start-hour-value]') : null;
    var autoReplyForbidEndHourValue = settingsForm ? settingsForm.querySelector('[data-auto-reply-forbid-end-hour-value]') : null;
    var wrongRefundStreakInput = document.querySelector('[data-wrong-refund-streak-input]');
    var wrongRefundPercentInput = document.querySelector('[data-wrong-refund-percent-input]');
    var wrongRefundStreakValue = settingsForm ? settingsForm.querySelector('[data-wrong-refund-streak-value]') : null;
    var wrongRefundPercentValue = settingsForm ? settingsForm.querySelector('[data-wrong-refund-percent-value]') : null;
    var autoReplyStatus = document.querySelector('[data-auto-reply-status]');
    var templateModal = document.querySelector('[data-generator-template-modal]');
    var templateOpenButton = document.querySelector('[data-generator-template-modal-open]');
    var templateCloseButtons = Array.prototype.slice.call(document.querySelectorAll('[data-generator-template-modal-close]'));
    var templateClearButton = document.querySelector('[data-generator-template-clear]');
    var templateInputs = Array.prototype.slice.call(document.querySelectorAll('[data-generator-template-setting]'));
    var templateSelectedCount = document.querySelector('[data-generator-template-selected-count]');
    var templatePillCount = document.querySelector('[data-generator-template-pill-count]');

    function syncGeneratorSettingControls() {
        settingControls.forEach(function (control) {
            var settingKey = control.getAttribute('data-generator-setting-control') || '';
            var target = settingKey && settingsForm
                ? settingsForm.querySelector('[data-generator-setting-value="' + settingKey + '"]')
                : null;

            if (!target) {
                return;
            }

            target.value = control.type === 'checkbox'
                ? (control.checked ? '1' : '')
                : (control.value || '');
        });
    }

    function syncMaterialSettings() {
        var hasMaterial;
        var hasPostUpdateTime;
        var hasMaterialTime;

        hasPostUpdateTime = !postUpdateTimeToggle || postUpdateTimeToggle.checked;
        if (postUpdateTimeInput) {
            postUpdateTimeInput.disabled = !hasPostUpdateTime;
        }
        if (postUpdateTimeValue) {
            postUpdateTimeValue.value = hasPostUpdateTime && postUpdateTimeInput ? (postUpdateTimeInput.value || '') : '';
        }

        hasMaterialTime = !materialTimeToggle || materialTimeToggle.checked;
        if (materialTimeInput) {
            materialTimeInput.disabled = !hasMaterialTime;
        }
        if (materialTimeValue) {
            materialTimeValue.value = hasMaterialTime && materialTimeInput ? (materialTimeInput.value || '') : '';
        }

        var hasSaleMaterialTime = !saleMaterialTimeToggle || saleMaterialTimeToggle.checked;
        if (saleMaterialTimeInput) {
            saleMaterialTimeInput.disabled = !hasSaleMaterialTime;
        }
        if (saleMaterialTimeValue) {
            saleMaterialTimeValue.value = hasSaleMaterialTime && saleMaterialTimeInput ? (saleMaterialTimeInput.value || '') : '';
        }

        if (!materialContentToggle) {
            return;
        }

        hasMaterial = materialContentToggle.checked;

        if (materialContentValue) {
            materialContentValue.value = hasMaterial ? '' : '1';
        }

        if (materialContentLabel) {
            materialContentLabel.textContent = hasMaterial ? '有资料' : '无资料';
        }

        if (materialContentPill) {
            materialContentPill.classList.toggle('is-has-material', hasMaterial);
        }
    }

    function syncFakeAfterOpenState() {}

    function normalizeAutoReplyCount(value) {
        var number = parseInt(value || '3', 10);

        if (!number || number < 1) {
            number = 1;
        }

        if (number > 99) {
            number = 99;
        }

        return String(number);
    }

    function normalizeAutoReplyRange(input, fallback, minValue, maxValue) {
        var number = parseInt(input ? input.value : fallback, 10);
        var floor = typeof minValue === 'number' ? minValue : 0;
        var ceiling = typeof maxValue === 'number' ? maxValue : 99;

        if (isNaN(number) || number < floor) {
            number = floor;
        }

        if (number > ceiling) {
            number = ceiling;
        }

        return String(number);
    }

    function normalizeAutoReplyHour(input, fallback) {
        var number = parseInt(input ? input.value : fallback, 10);

        if (isNaN(number) || number < 0) {
            number = 0;
        }

        if (number > 23) {
            number = 23;
        }

        return String(number);
    }

    function syncAutoReplySettings() {
        var enabled = !autoReplyEnabledInput || autoReplyEnabledInput.checked;
        var baseMinValue = normalizeAutoReplyRange(autoReplyBaseMinInput, '2', 1);
        var baseMaxValue = normalizeAutoReplyRange(autoReplyBaseMaxInput, baseMinValue, 1);
        var dailyMinValue = normalizeAutoReplyRange(autoReplyDailyMinInput, '1', 0);
        var dailyMaxValue = normalizeAutoReplyRange(autoReplyDailyMaxInput, dailyMinValue, 0);
        var issueMinValue = normalizeAutoReplyRange(autoReplyIssueMinInput, '1', 1);
        var issueMaxValue = normalizeAutoReplyRange(autoReplyIssueMaxInput, issueMinValue, 1);
        var forbidStartHourValue = normalizeAutoReplyHour(autoReplyForbidStartHourInput, '1');
        var forbidEndHourValue = normalizeAutoReplyHour(autoReplyForbidEndHourInput, '8');
        var wrongRefundStreakText = normalizeAutoReplyRange(wrongRefundStreakInput, '2', 2);
        var wrongRefundPercentText = normalizeAutoReplyRange(wrongRefundPercentInput, '100', 0, 999);
        var countValue = autoReplyCountInput ? normalizeAutoReplyCount(autoReplyCountInput.value) : baseMaxValue;

        if (autoReplyCountInput) {
            autoReplyCountInput.value = countValue;
        }
        if (parseInt(baseMinValue, 10) > parseInt(baseMaxValue, 10)) {
            countValue = baseMinValue;
            baseMaxValue = baseMinValue;
        } else {
            countValue = baseMaxValue;
        }
        if (parseInt(dailyMinValue, 10) > parseInt(dailyMaxValue, 10)) {
            dailyMaxValue = dailyMinValue;
        }
        if (parseInt(issueMinValue, 10) > parseInt(issueMaxValue, 10)) {
            issueMaxValue = issueMinValue;
        }
        if (autoReplyBaseMinInput) {
            autoReplyBaseMinInput.value = baseMinValue;
        }
        if (autoReplyBaseMaxInput) {
            autoReplyBaseMaxInput.value = baseMaxValue;
        }
        if (autoReplyDailyMinInput) {
            autoReplyDailyMinInput.value = dailyMinValue;
        }
        if (autoReplyDailyMaxInput) {
            autoReplyDailyMaxInput.value = dailyMaxValue;
        }
        if (autoReplyIssueMinInput) {
            autoReplyIssueMinInput.value = issueMinValue;
        }
        if (autoReplyIssueMaxInput) {
            autoReplyIssueMaxInput.value = issueMaxValue;
        }
        if (autoReplyForbidStartHourInput) {
            autoReplyForbidStartHourInput.value = forbidStartHourValue;
        }
        if (autoReplyForbidEndHourInput) {
            autoReplyForbidEndHourInput.value = forbidEndHourValue;
        }
        if (wrongRefundStreakInput) {
            wrongRefundStreakInput.value = wrongRefundStreakText;
        }
        if (wrongRefundPercentInput) {
            wrongRefundPercentInput.value = wrongRefundPercentText;
        }

        if (autoReplyEnabledValue) {
            autoReplyEnabledValue.value = enabled ? '1' : '';
        }

        if (autoReplyCountValue) {
            autoReplyCountValue.value = countValue;
        }
        if (autoReplyBaseMinValue) {
            autoReplyBaseMinValue.value = baseMinValue;
        }
        if (autoReplyBaseMaxValue) {
            autoReplyBaseMaxValue.value = baseMaxValue;
        }
        if (autoReplyDailyMinValue) {
            autoReplyDailyMinValue.value = dailyMinValue;
        }
        if (autoReplyDailyMaxValue) {
            autoReplyDailyMaxValue.value = dailyMaxValue;
        }
        if (autoReplyIssueMinValue) {
            autoReplyIssueMinValue.value = issueMinValue;
        }
        if (autoReplyIssueMaxValue) {
            autoReplyIssueMaxValue.value = issueMaxValue;
        }
        if (autoReplyForbidStartHourValue) {
            autoReplyForbidStartHourValue.value = forbidStartHourValue;
        }
        if (autoReplyForbidEndHourValue) {
            autoReplyForbidEndHourValue.value = forbidEndHourValue;
        }
        if (wrongRefundStreakValue) {
            wrongRefundStreakValue.value = wrongRefundStreakText;
        }
        if (wrongRefundPercentValue) {
            wrongRefundPercentValue.value = wrongRefundPercentText;
        }

        if (autoReplyStatus) {
            autoReplyStatus.textContent = enabled ? '已开启' : '已关闭';
            autoReplyStatus.classList.toggle('is-active', enabled);
        }
    }

    function syncTemplateSelectedCount() {
        var selectedCount = 0;

        templateInputs.forEach(function (input) {
            if (input.checked) {
                selectedCount++;
            }
        });

        if (templateSelectedCount) {
            templateSelectedCount.textContent = String(selectedCount);
        }

        if (templatePillCount) {
            templatePillCount.textContent = String(selectedCount);
        }
    }

    function openTemplateModal() {
        if (!templateModal) {
            return;
        }

        templateModal.hidden = false;
        document.body.classList.add('admin-posts-generator-template-modal-open');
        syncTemplateSelectedCount();
    }

    function closeTemplateModal() {
        if (!templateModal) {
            return;
        }

        templateModal.hidden = true;
        document.body.classList.remove('admin-posts-generator-template-modal-open');
    }

    function clearTemplateSelections() {
        templateInputs.forEach(function (input) {
            input.checked = false;
        });

        syncManageGeneratorSettings();
    }

    function syncManageGeneratorSettings() {
        syncGeneratorSettingControls();
        syncMaterialSettings();
        syncFakeAfterOpenState();
        syncAutoReplySettings();
        syncTemplateSelectedCount();
    }

    settingControls.forEach(function (control) {
        control.addEventListener('input', syncManageGeneratorSettings);
        control.addEventListener('change', syncManageGeneratorSettings);
    });

    if (postUpdateTimeInput) {
        postUpdateTimeInput.addEventListener('input', syncManageGeneratorSettings);
        postUpdateTimeInput.addEventListener('change', syncManageGeneratorSettings);
    }

    if (postUpdateTimeToggle) {
        postUpdateTimeToggle.addEventListener('change', syncManageGeneratorSettings);
    }

    if (materialTimeInput) {
        materialTimeInput.addEventListener('input', syncManageGeneratorSettings);
        materialTimeInput.addEventListener('change', syncManageGeneratorSettings);
    }

    if (materialTimeToggle) {
        materialTimeToggle.addEventListener('change', syncManageGeneratorSettings);
    }

    if (saleMaterialTimeInput) {
        saleMaterialTimeInput.addEventListener('input', syncManageGeneratorSettings);
        saleMaterialTimeInput.addEventListener('change', syncManageGeneratorSettings);
    }

    if (saleMaterialTimeToggle) {
        saleMaterialTimeToggle.addEventListener('change', syncManageGeneratorSettings);
    }

    if (materialContentToggle) {
        materialContentToggle.addEventListener('change', syncManageGeneratorSettings);
    }

    if (autoReplyEnabledInput) {
        autoReplyEnabledInput.addEventListener('change', syncManageGeneratorSettings);
    }

    if (autoReplyCountInput) {
        autoReplyCountInput.addEventListener('input', syncManageGeneratorSettings);
        autoReplyCountInput.addEventListener('change', syncManageGeneratorSettings);
    }

    [
        autoReplyBaseMinInput,
        autoReplyBaseMaxInput,
        autoReplyDailyMinInput,
        autoReplyDailyMaxInput,
        autoReplyIssueMinInput,
        autoReplyIssueMaxInput,
        autoReplyForbidStartHourInput,
        autoReplyForbidEndHourInput,
        wrongRefundStreakInput,
        wrongRefundPercentInput
    ].forEach(function (input) {
        if (!input) {
            return;
        }

        input.addEventListener('input', syncManageGeneratorSettings);
        input.addEventListener('change', syncManageGeneratorSettings);
    });

    if (templateOpenButton) {
        templateOpenButton.addEventListener('click', openTemplateModal);
    }

    templateCloseButtons.forEach(function (button) {
        button.addEventListener('click', closeTemplateModal);
    });

    if (templateClearButton) {
        templateClearButton.addEventListener('click', clearTemplateSelections);
    }

    templateInputs.forEach(function (input) {
        input.addEventListener('change', syncManageGeneratorSettings);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && templateModal && !templateModal.hidden) {
            closeTemplateModal();
        }
    });

    function saveManageGeneratorSettings() {
        var formData;

        if (!settingsForm || !window.fetch) {
            return window.Promise.resolve();
        }

        syncManageGeneratorSettings();
        formData = new window.FormData(settingsForm);
        formData.set('_response_format', 'json');
        formData.set('_silent', '1');

        return window.fetch(settingsForm.getAttribute('action') || window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || '自动更新和自动评论设置保存失败。');
                }

                return payload;
            });
        });
    }

    if (settingsForm) {
        settingsForm.addEventListener('submit', syncManageGeneratorSettings);
    }

    window.AdminPostManageGeneratorSettings = {
        sync: syncManageGeneratorSettings,
        save: saveManageGeneratorSettings
    };

    syncManageGeneratorSettings();
}());
</script>
<?php endif; ?>

<?php if ($showPostFormSection): ?>
<script src="<?php echo e(asset('vendor/tinymce/tinymce.min.js?v=8.4.0-local')); ?>"></script>
<script>
(function () {
    var postFormCard = document.getElementById('post-form-card');
    var postForm = postFormCard ? postFormCard.querySelector('form') : null;
    var titleNode = postFormCard ? postFormCard.querySelector('.admin-card-title') : null;
    var subtitleNode = postFormCard ? postFormCard.querySelector('.admin-card-subtitle') : null;
    var blankLink = postFormCard ? postFormCard.querySelector('.admin-form-actions .admin-button.is-light') : null;
    var contentEditor = document.getElementById('post-full-content-editor');
    var contentEditorPlaceholder = postFormCard ? postFormCard.querySelector('[data-post-editor-placeholder]') : null;
    var titlePreviewNode = postFormCard ? postFormCard.querySelector('[data-post-title-preview]') : null;
    var titlePartInputs = postFormCard ? postFormCard.querySelectorAll('[data-post-title-part]') : [];
    var titleGrid = postFormCard ? postFormCard.querySelector('.admin-post-title-grid') : null;
    var titlePrefixInput = postForm ? postForm.querySelector('input[name="title_prefix"]') : null;
    var titleMiddleInput = postForm ? postForm.querySelector('input[name="title_middle"]') : null;
    var titleSuffixInput = postForm ? postForm.querySelector('input[name="title_suffix"]') : null;
    var authorNicknameInput = postForm ? postForm.querySelector('input[name="author_nickname"]') : null;
    var previewAuthorInput = null;

    if (titleNode) {
        titleNode.textContent = <?php echo json_encode($postFormTitle, JSON_UNESCAPED_UNICODE); ?>;
    }

    if (subtitleNode) {
        subtitleNode.textContent = <?php echo json_encode($postFormSubtitle, JSON_UNESCAPED_UNICODE); ?>;
    }

    if (blankLink) {
        blankLink.setAttribute('href', <?php echo json_encode($postBlankFormUrl, JSON_UNESCAPED_UNICODE); ?>);
    }

    if (!postForm) {
        return;
    }

    if (titleGrid) {
        titleGrid.classList.remove('admin-form-grid-4');
        titleGrid.classList.add('admin-form-grid');
    }

    if (titlePrefixInput) {
        var prefixLabel = titlePrefixInput.parentNode ? titlePrefixInput.parentNode.querySelector('.admin-label') : null;

        if (prefixLabel) {
            prefixLabel.textContent = '站点标题';
        }
    }

    if (titleMiddleInput) {
        var middleLabel = titleMiddleInput.parentNode ? titleMiddleInput.parentNode.querySelector('.admin-label') : null;

        if (middleLabel) {
            middleLabel.textContent = '帖子类型';
        }
    }

    if (titleSuffixInput) {
        var suffixLabel = titleSuffixInput.parentNode ? titleSuffixInput.parentNode.querySelector('.admin-label') : null;

        if (suffixLabel) {
            suffixLabel.textContent = '\u4f5c\u8005\u6635\u79f0';
        }

        titleSuffixInput.value = String((authorNicknameInput && authorNicknameInput.value) || '').trim();
        titleSuffixInput.setAttribute('name', 'author_nickname');
        titleSuffixInput.setAttribute('placeholder', '\u671f\u671f\u4e2d\u5956');
        titleSuffixInput.removeAttribute('data-post-title-part');
    }

    previewAuthorInput = titleSuffixInput;

    if (authorNicknameInput) {
        var authorFieldWrap = authorNicknameInput.parentNode;

        authorNicknameInput.removeAttribute('name');
        authorNicknameInput.disabled = true;

        if (authorFieldWrap) {
            authorFieldWrap.style.display = 'none';
        }
    }


    titlePartInputs = postFormCard ? postFormCard.querySelectorAll('[data-post-title-part]') : [];

    function syncTitlePreview() {
        var authorText;
        var issueText;
        var titleText;

        if (!titlePreviewNode) {
            return;
        }

        issueText = <?php echo json_encode($postTitleIssueText, JSON_UNESCAPED_UNICODE); ?>;
        titleText = Array.prototype.map.call(titlePartInputs || [], function (input) {
            var value = String((input && input.value) || '').trim();

            if (value !== '') {
                return value;
            }

            return String((input && input.getAttribute('placeholder')) || '').trim();
        }).join('').trim();

        if (titleText === '') {
            titleText = '\u9e3f\u8fd0\u516d\u5408\u3010\u4e00\u7801\u4e2d\u7279\u3011';
        }

        authorText = String((previewAuthorInput && previewAuthorInput.value) || '').trim();
        if (authorText === '') {
            authorText = String((previewAuthorInput && previewAuthorInput.getAttribute('placeholder')) || '').trim();
        }
        if (authorText !== '') {
            titleText += authorText;
        }

        titlePreviewNode.textContent = (issueText ? issueText + ': ' : '') + titleText;
    }

    Array.prototype.forEach.call(titlePartInputs || [], function (input) {
        input.addEventListener('input', syncTitlePreview);
    });
    if (previewAuthorInput) {
        previewAuthorInput.addEventListener('input', syncTitlePreview);
    }
    syncTitlePreview();

    function clearPostEditorBootState() {
        if (contentEditor) {
            contentEditor.classList.remove('is-editor-booting');
        }

        if (contentEditorPlaceholder && contentEditorPlaceholder.parentNode) {
            contentEditorPlaceholder.parentNode.removeChild(contentEditorPlaceholder);
        }
    }

    if (contentEditor && typeof window.tinymce !== 'undefined') {
        if (window.tinymce.get('post-full-content-editor')) {
            window.tinymce.get('post-full-content-editor').remove();
        }

        window.tinymce.init({
            selector: '#post-full-content-editor',
            license_key: 'gpl',
            language: 'zh-CN',
            language_url: '<?php echo e(asset('vendor/tinymce/langs/zh-CN.js?v=8.4.0-local')); ?>',
            height: 680,
            min_height: 680,
            menubar: 'file edit view insert format table tools',
            plugins: 'advlist autolink lists link image table code preview searchreplace fullscreen wordcount autoresize charmap anchor',
            toolbar_mode: 'wrap',
            toolbar: 'undo redo | blocks fontfamily fontsize | bold italic underline strikethrough | forecolor backcolor | removeformat | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link image table | code preview fullscreen',
            branding: false,
            promotion: false,
            convert_urls: false,
            relative_urls: false,
            remove_script_host: false,
            content_style: 'body{font-family:Microsoft YaHei,PingFang SC,sans-serif;font-size:15px;line-height:1.8;color:#0f172a;}',
            init_instance_callback: function () {
                clearPostEditorBootState();
            }
        });
    } else if (contentEditorPlaceholder) {
        contentEditorPlaceholder.textContent = '编辑器加载失败，请刷新页面重试。';
        contentEditorPlaceholder.classList.add('is-error');
    }

    postForm.addEventListener('submit', function () {
        if (window.tinymce) {
            window.tinymce.triggerSave();
        }
    });
})();
</script>
<?php endif; ?>

<?php if ($showPostGeneratorSection): ?>
<script>
(function () {
    var generatorForm = document.querySelector('[data-posts-generator-form]');
    var clearButton;
    var generatorRegionInput;
    var materialContentToggle;
    var materialContentValue;
    var materialContentLabel;
    var materialContentPill;
    var fakeAfterOpenToggle;
    var conditionInputs;
    var colorModeInputs;
    var templateInputs;
    var summaryNode;
    var summaryTotalPostCount;
    var summarySegmentOneCount;
    var summarySegmentTwoCount;
    var summarySegmentThreeCount;
    var saveSettingsButton;
    var scrollStorageKey;

    if (!generatorForm) {
        return;
    }

    clearButton = generatorForm.querySelector('[data-post-generator-clear]');
    generatorRegionInput = generatorForm.querySelector('input[name="region"]');
    materialContentToggle = generatorForm.querySelector('[data-material-content-toggle]');
    materialContentValue = generatorForm.querySelector('[data-material-content-value]');
    materialContentLabel = generatorForm.querySelector('[data-material-content-label]');
    materialContentPill = generatorForm.querySelector('[data-material-content-pill]');
    fakeAfterOpenToggle = generatorForm.querySelector('[data-fake-after-open-toggle]');
    conditionInputs = Array.prototype.slice.call(generatorForm.querySelectorAll(
        'input[name="target_zodiac[]"], input[name="target_number[]"], input[name="target_wave[]"], input[name="target_element[]"], input[name="target_head[]"], input[name="target_tail[]"]'
    ));
    colorModeInputs = Array.prototype.slice.call(generatorForm.querySelectorAll('[data-generator-color-mode]'));
    templateInputs = Array.prototype.slice.call(generatorForm.querySelectorAll('input[name="templates[]"]'));
    summaryNode = generatorForm.querySelector('[data-post-generator-summary]');
    summaryTotalPostCount = summaryNode ? Math.max(0, parseInt(summaryNode.getAttribute('data-total-post-count') || '0', 10) || 0) : 0;
    summarySegmentOneCount = summaryNode ? Math.max(0, parseInt(summaryNode.getAttribute('data-segment-one-count') || '0', 10) || 0) : 0;
    summarySegmentTwoCount = summaryNode ? Math.max(0, parseInt(summaryNode.getAttribute('data-segment-two-count') || '0', 10) || 0) : 0;
    summarySegmentThreeCount = summaryNode ? Math.max(0, parseInt(summaryNode.getAttribute('data-segment-three-count') || '0', 10) || 0) : 0;
    saveSettingsButton = document.querySelector('.admin-posts-generator-save-settings[form="admin-post-generator-form"]');
    scrollStorageKey = 'admin-post-generator-scroll:' + window.location.pathname + ':' + (generatorRegionInput ? generatorRegionInput.value : '');

    function generatorScrollContainer() {
        return document.querySelector('.admin-main') || document.scrollingElement || document.documentElement;
    }

    function generatorSessionStorage() {
        try {
            return window.sessionStorage || null;
        } catch (error) {
            return null;
        }
    }

    function rememberGeneratorScrollPosition() {
        var scrollContainer;
        var storage;

        storage = generatorSessionStorage();
        if (!storage) {
            return;
        }

        scrollContainer = generatorScrollContainer();
        storage.setItem(scrollStorageKey, String(scrollContainer ? scrollContainer.scrollTop : 0));
    }

    function restoreGeneratorScrollPosition() {
        var rawValue;
        var scrollValue;
        var scrollContainer;
        var storage;

        storage = generatorSessionStorage();
        if (!storage) {
            return;
        }

        rawValue = storage.getItem(scrollStorageKey);
        if (rawValue === null) {
            return;
        }
        storage.removeItem(scrollStorageKey);

        scrollValue = Math.max(0, parseInt(rawValue || '0', 10) || 0);
        scrollContainer = generatorScrollContainer();
        if (!scrollContainer) {
            return;
        }

        if (!window.requestAnimationFrame) {
            scrollContainer.scrollTop = scrollValue;
            return;
        }

        window.requestAnimationFrame(function () {
            scrollContainer.scrollTop = scrollValue;
        });
    }

    function syncGeneratorColorField(segmentKey) {
        var modeInput = generatorForm.querySelector('[data-generator-color-mode="' + segmentKey + '"]');
        var colorWrap = generatorForm.querySelector('[data-generator-color-value-wrap="' + segmentKey + '"]');

        if (colorWrap) {
            colorWrap.hidden = !(modeInput && modeInput.value === 'fixed');
        }
    }

    function syncGeneratorSummary() {
        var selectedCount = 0;

        if (!summaryNode) {
            return;
        }

        templateInputs.forEach(function (input) {
            if (input.checked) {
                selectedCount++;
            }
        });

        summaryNode.innerHTML = '选择 <span class="admin-posts-generator-summary-value">' + selectedCount
            + '</span> 条，共 <span class="admin-posts-generator-summary-value">' + summaryTotalPostCount
            + '</span> 条，高手① <span class="admin-posts-generator-summary-value">' + summarySegmentOneCount
            + '</span> 条，高手② <span class="admin-posts-generator-summary-value">' + summarySegmentTwoCount
            + '</span> 条，高手③ <span class="admin-posts-generator-summary-value">' + summarySegmentThreeCount
            + '</span> 条';
    }

    function applyGeneratorSummaryCounts(counts) {
        if (!counts || !summaryNode) {
            return;
        }

        summaryTotalPostCount = Math.max(0, parseInt(counts.total_count || '0', 10) || 0);
        summarySegmentOneCount = Math.max(0, parseInt(counts.segment_1_count || '0', 10) || 0);
        summarySegmentTwoCount = Math.max(0, parseInt(counts.segment_2_count || '0', 10) || 0);
        summarySegmentThreeCount = Math.max(0, parseInt(counts.segment_3_count || '0', 10) || 0);
        summaryNode.setAttribute('data-total-post-count', String(summaryTotalPostCount));
        summaryNode.setAttribute('data-segment-one-count', String(summarySegmentOneCount));
        summaryNode.setAttribute('data-segment-two-count', String(summarySegmentTwoCount));
        summaryNode.setAttribute('data-segment-three-count', String(summarySegmentThreeCount));
        syncGeneratorSummary();
    }

    function applyGeneratorSummaryDeltaCounts(counts) {
        if (!counts || !summaryNode) {
            return;
        }

        summaryTotalPostCount += Math.max(0, parseInt(counts.total_count || '0', 10) || 0);
        summarySegmentOneCount += Math.max(0, parseInt(counts.segment_1_count || '0', 10) || 0);
        summarySegmentTwoCount += Math.max(0, parseInt(counts.segment_2_count || '0', 10) || 0);
        summarySegmentThreeCount += Math.max(0, parseInt(counts.segment_3_count || '0', 10) || 0);
        summaryNode.setAttribute('data-total-post-count', String(summaryTotalPostCount));
        summaryNode.setAttribute('data-segment-one-count', String(summarySegmentOneCount));
        summaryNode.setAttribute('data-segment-two-count', String(summarySegmentTwoCount));
        summaryNode.setAttribute('data-segment-three-count', String(summarySegmentThreeCount));
        syncGeneratorSummary();
    }

    function syncMaterialContentState() {
        var hasMaterial = materialContentToggle ? materialContentToggle.checked : false;

        if (materialContentValue) {
            materialContentValue.value = hasMaterial ? '' : '1';
        }

        if (materialContentLabel) {
            materialContentLabel.textContent = hasMaterial ? '有资料' : '无资料';
        }

        if (materialContentPill) {
            materialContentPill.classList.toggle('is-has-material', hasMaterial);
        }
    }

    function showMaterialContentSaveError(message) {
        if (window.AppUI && typeof window.AppUI.toast === 'function') {
            window.AppUI.toast(message, 'error');
            return;
        }

        if (window.console && message) {
            window.console.warn(message);
        }
    }

    function saveMaterialContentState() {
        var formData;
        var generatorTypeInput;
        var regionInput;
        var tokenInput;
        var viewInput;

        if (!window.fetch || !materialContentValue) {
            return;
        }

        tokenInput = generatorForm.querySelector('input[name="_token"]');
        regionInput = generatorForm.querySelector('input[name="region"]');
        generatorTypeInput = generatorForm.querySelector('input[name="generator_type"]');
        viewInput = generatorForm.querySelector('input[name="view"]');

        formData = new window.FormData();
        formData.append('_token', tokenInput ? tokenInput.value : '');
        formData.append('_admin_form', 'page');
        formData.append('_admin_action', 'save_post_generator_settings');
        formData.append('_admin_action_override', 'save_post_generator_settings');
        formData.append('_silent', '1');
        formData.append('_response_format', 'json');
        formData.append('material_content_state_only', '1');
        formData.append('region', regionInput ? regionInput.value : '');
        formData.append('generator_type', generatorTypeInput ? generatorTypeInput.value : (regionInput ? regionInput.value : 'macau'));
        formData.append('view', viewInput ? viewInput.value : 'compose');
        formData.append('is_blank_content', materialContentValue.value || '');

        window.fetch(generatorForm.getAttribute('action') || window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || '有/无资料状态保存失败。');
                }

                return payload;
            });
        }).catch(function (error) {
            showMaterialContentSaveError(error && error.message ? error.message : '有/无资料状态保存失败。');
        });
    }

    function syncFakeAfterOpenState() {}

    function resetGeneratorForm() {
        conditionInputs.forEach(function (input) {
            input.checked = false;
        });

        templateInputs.forEach(function (input) {
            input.checked = false;
        });

        syncGeneratorSummary();
    }

    if (clearButton) {
        clearButton.addEventListener('click', function () {
            resetGeneratorForm();
        });
    }

    if (saveSettingsButton) {
        saveSettingsButton.addEventListener('click', rememberGeneratorScrollPosition);
    }

    function showGeneratorSubmitMessage(message, type) {
        if (window.AppUI && typeof window.AppUI.toast === 'function') {
            window.AppUI.toast(message, type || 'success');
        }
    }

    function setGeneratorSubmitting(submitter, submitting) {
        if (!submitter) {
            return;
        }

        if (submitting) {
            submitter.setAttribute('data-submit-text', submitter.textContent || '生成帖子');
            submitter.disabled = true;
            submitter.textContent = '生成中...';
            return;
        }

        submitter.disabled = false;
        submitter.textContent = submitter.getAttribute('data-submit-text') || '生成帖子';
        submitter.removeAttribute('data-submit-text');
    }

    generatorForm.addEventListener('submit', function (event) {
        var submitter = event.submitter || document.activeElement;
        var isSaveSettings = submitter
            && submitter.name === '_admin_action_override'
            && submitter.value === 'save_post_generator_settings';
        var formData;

        if (!isSaveSettings) {
            syncMaterialContentState();
        }

        if (isSaveSettings) {
            rememberGeneratorScrollPosition();
            return;
        }

        if (!window.fetch) {
            return;
        }

        event.preventDefault();
        formData = new window.FormData(generatorForm);
        formData.set('_response_format', 'json');
        setGeneratorSubmitting(submitter, true);

        window.fetch(generatorForm.getAttribute('action') || window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || '生成帖子失败，请稍后重试。');
                }

                return payload;
            });
        }).then(function (payload) {
            var count = payload && payload.data ? parseInt(payload.data.count || '0', 10) || 0 : 0;
            resetGeneratorForm();
            if (payload && payload.data && payload.data.summary_counts) {
                applyGeneratorSummaryCounts(payload.data.summary_counts);
            } else {
                applyGeneratorSummaryDeltaCounts(payload && payload.data ? payload.data.summary_delta_counts : null);
            }
            showGeneratorSubmitMessage('已生成 ' + count + ' 篇帖子。', 'success');
        }).catch(function (error) {
            showGeneratorSubmitMessage(error && error.message ? error.message : '生成帖子失败，请稍后重试。', 'error');
        }).then(function () {
            setGeneratorSubmitting(submitter, false);
        });
    });

    if (materialContentToggle) {
        materialContentToggle.addEventListener('change', function () {
            syncMaterialContentState();
            saveMaterialContentState();
        });
        syncMaterialContentState();
    }

    if (fakeAfterOpenToggle) {
        fakeAfterOpenToggle.addEventListener('change', syncFakeAfterOpenState);
        syncFakeAfterOpenState();
    }

    colorModeInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            syncGeneratorColorField(input.getAttribute('data-generator-color-mode') || '');
        });
        syncGeneratorColorField(input.getAttribute('data-generator-color-mode') || '');
    });

    templateInputs.forEach(function (input) {
        input.addEventListener('change', syncGeneratorSummary);
    });
    syncGeneratorSummary();
    restoreGeneratorScrollPosition();

})();
</script>
<?php endif; ?>

<?php if ($showPostManageSection && $postCanManage): ?>
<script>
(function () {
    var bulkForm = document.querySelector('[data-posts-bulk-form]');
    var quickForm = document.querySelector('[data-posts-quick-form]');
    var actionField;
    var valueField;
    var checkAllToggles;
    var itemCheckboxes;
    var selectedCountNodes;
    var quickActionField;
    var quickTargetField;
    var quickValueField;
    var quickBuyerField;
    var quickMarkField;
    var quickContentField;
    var quickPriceField;
    var postSettingsSaveButton;
    var postLockSettingsNode;
    var postLockBeforeInput;
    var postLockUnlockInput;
    var postLockStatusNode;
    var postLikeIncrementSettingsNode;
    var postLikeBaseMinInput;
    var postLikeBaseMaxInput;
    var postLikeIncrementMinInput;
    var postLikeIncrementMaxInput;
    var postLikeIncrementStatusNode;
    var postViewDisplaySettingsNode;
    var postViewBaseMinInput;
    var postViewBaseMaxInput;
    var postViewIncrementMinInput;
    var postViewIncrementMaxInput;
    var postViewDisplayStatusNode;
    var postSaleBuyerIncrementSettingsNode;
    var postSaleBuyerIncrementMinInput;
    var postSaleBuyerIncrementMaxInput;
    var postSaleBuyerIncrementStatusNode;
    var currentMaterialModal;
    var currentMaterialModalIssue;
    var currentMaterialModalInput;
    var currentMaterialModalPrice;
    var currentMaterialModalSave;
    var currentMaterialActivePostId = '';
    var currentMaterialActiveIssueTail = '';
    var currentMaterialActiveSource = null;
    var currentMaterialActiveRow = null;
    var currentMaterialActiveButton = null;

    if (!bulkForm) {
        return;
    }

    actionField = bulkForm.querySelector('input[name="bulk_action"]');
    valueField = bulkForm.querySelector('input[name="bulk_value"]');
    checkAllToggles = Array.prototype.slice.call(bulkForm.querySelectorAll('input[data-check-all]'));
    itemCheckboxes = Array.prototype.slice.call(bulkForm.querySelectorAll('input[name="selected_ids[]"]'));
    selectedCountNodes = Array.prototype.slice.call(document.querySelectorAll('[data-post-selected-count]'));
    quickActionField = quickForm ? quickForm.querySelector('input[name="quick_action"]') : null;
    quickTargetField = quickForm ? quickForm.querySelector('input[name="target_post_id"]') : null;
    quickValueField = quickForm ? quickForm.querySelector('input[name="value"]') : null;
    quickBuyerField = quickForm ? quickForm.querySelector('input[name="buyer_count"]') : null;
    quickMarkField = quickForm ? quickForm.querySelector('input[name="mark"]') : null;
    quickContentField = quickForm ? quickForm.querySelector('input[name="content"]') : null;
    quickPriceField = quickForm ? quickForm.querySelector('input[name="price"]') : null;
    postSettingsSaveButton = document.querySelector('[data-post-settings-save]');
    postLockSettingsNode = document.querySelector('[data-post-lock-settings]');
    postLockBeforeInput = postLockSettingsNode ? postLockSettingsNode.querySelector('[data-post-lock-before-minutes]') : null;
    postLockUnlockInput = document.querySelector('[data-post-lock-unlock-time]');
    postLockStatusNode = postLockSettingsNode ? postLockSettingsNode.querySelector('[data-post-lock-settings-status]') : null;
    postLikeIncrementSettingsNode = document.querySelector('[data-post-like-increment-settings]');
    postLikeBaseMinInput = document.querySelector('[data-post-like-base-min]');
    postLikeBaseMaxInput = document.querySelector('[data-post-like-base-max]');
    postLikeIncrementMinInput = document.querySelector('[data-post-like-increment-min]');
    postLikeIncrementMaxInput = document.querySelector('[data-post-like-increment-max]');
    postLikeIncrementStatusNode = postLikeIncrementSettingsNode ? postLikeIncrementSettingsNode.querySelector('[data-post-like-increment-status]') : null;
    postViewDisplaySettingsNode = document.querySelector('[data-post-view-display-settings]');
    postViewBaseMinInput = document.querySelector('[data-post-view-base-min]');
    postViewBaseMaxInput = document.querySelector('[data-post-view-base-max]');
    postViewIncrementMinInput = document.querySelector('[data-post-view-increment-min]');
    postViewIncrementMaxInput = document.querySelector('[data-post-view-increment-max]');
    postViewDisplayStatusNode = postViewDisplaySettingsNode ? postViewDisplaySettingsNode.querySelector('[data-post-view-display-status]') : null;
    postSaleBuyerIncrementSettingsNode = document.querySelector('[data-post-sale-buyer-increment-settings]');
    postSaleBuyerIncrementMinInput = postSaleBuyerIncrementSettingsNode ? postSaleBuyerIncrementSettingsNode.querySelector('[data-post-sale-buyer-increment-min]') : null;
    postSaleBuyerIncrementMaxInput = postSaleBuyerIncrementSettingsNode ? postSaleBuyerIncrementSettingsNode.querySelector('[data-post-sale-buyer-increment-max]') : null;
    postSaleBuyerIncrementStatusNode = postSaleBuyerIncrementSettingsNode ? postSaleBuyerIncrementSettingsNode.querySelector('[data-post-sale-buyer-increment-status]') : null;
    currentMaterialModal = document.querySelector('[data-post-current-material-modal]');
    currentMaterialModalIssue = currentMaterialModal ? currentMaterialModal.querySelector('[data-post-current-material-modal-issue]') : null;
    currentMaterialModalInput = currentMaterialModal ? currentMaterialModal.querySelector('[data-post-current-material-modal-input]') : null;
    currentMaterialModalPrice = currentMaterialModal ? currentMaterialModal.querySelector('[data-post-current-material-modal-price]') : null;
    currentMaterialModalSave = currentMaterialModal ? currentMaterialModal.querySelector('[data-post-current-material-modal-save]') : null;

    if (!actionField || !valueField) {
        return;
    }

    function getSelectedCheckboxes() {
        return Array.prototype.slice.call(bulkForm.querySelectorAll('input[name="selected_ids[]"]:checked'));
    }

    function syncCheckAllToggle() {
        var checkedCount;

        checkedCount = getSelectedCheckboxes().length;
        checkAllToggles.forEach(function (checkAllToggle) {
            checkAllToggle.checked = itemCheckboxes.length > 0 && checkedCount === itemCheckboxes.length;
            checkAllToggle.indeterminate = checkedCount > 0 && checkedCount < itemCheckboxes.length;
        });
        selectedCountNodes.forEach(function (selectedCountNode) {
            selectedCountNode.textContent = String(checkedCount);
        });
    }

    function showPostLockMessage(message, type) {
        if (window.AppUI && typeof window.AppUI.toast === 'function') {
            window.AppUI.toast(message, type || 'success');
            return;
        }

        if (window.console && message) {
            window.console.warn(message);
        }
    }

    function syncPostLockStatus(state) {
        if (!postLockStatusNode || !state) {
            return;
        }

        if (postLockUnlockInput) {
            if (state.lock_start_at) {
                postLockUnlockInput.min = String(state.lock_start_at).slice(11, 16);
            }
            postLockUnlockInput.max = '23:59';
        }
        postLockStatusNode.classList.toggle('is-locked', !!state.is_locked);
        postLockStatusNode.classList.toggle('is-unlocked', !state.is_locked);
        if (state.is_locked) {
            postLockStatusNode.textContent = state.label || '此帖已🔐';
            return;
        }

        postLockStatusNode.textContent = state.label || '此帖未锁 🔓';
    }

    function savePostLockSettings(options) {
        var formData;
        var apiUrl;
        var silent = options && options.silent === true;

        if (!postLockSettingsNode || !postLockBeforeInput || !postLockUnlockInput) {
            return window.Promise.resolve();
        }

        apiUrl = postLockSettingsNode.getAttribute('data-api-url') || '';
        if (!apiUrl) {
            return window.Promise.resolve();
        }

        formData = new window.FormData();
        formData.append('action', 'admin.post_lock.save');
        formData.append('_token', postLockSettingsNode.getAttribute('data-token') || '');
        formData.append('region', postLockSettingsNode.getAttribute('data-region') || 'macau');
        formData.append('before_minutes', postLockBeforeInput.value || '60');
        formData.append('unlock_time', postLockUnlockInput.value || '23:59');

        return window.fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || '锁帖时间保存失败。');
                }

                return payload;
            });
        }).then(function (payload) {
            var settings = payload.data && payload.data.settings ? payload.data.settings : {};

            if (settings.before_minutes !== undefined) {
                postLockBeforeInput.value = String(settings.before_minutes);
            }
            if (settings.unlock_time !== undefined) {
                postLockUnlockInput.value = String(settings.unlock_time);
            }
            if (payload.data && payload.data.state) {
                syncPostLockStatus(payload.data.state);
            }
            if (!silent) {
                showPostLockMessage(payload.message || '锁帖时间设置已保存。', 'success');
            }
            return payload;
        }).catch(function (error) {
            if (!silent) {
                showPostLockMessage(error.message || '锁帖时间保存失败。', 'error');
                return null;
            }
            throw error;
        });
    }

    function syncPostLikeIncrementStatus(settings) {
        var baseMinValue;
        var baseMaxValue;
        var minValue;
        var maxValue;

        if (!settings) {
            return;
        }

        baseMinValue = parseInt(settings.base_min, 10);
        baseMaxValue = parseInt(settings.base_max, 10);
        minValue = parseInt(settings.increment_min, 10);
        maxValue = parseInt(settings.increment_max, 10);
        if (isNaN(baseMinValue) || baseMinValue < 1) {
            baseMinValue = 368;
        }
        if (isNaN(baseMaxValue) || baseMaxValue < baseMinValue) {
            baseMaxValue = baseMinValue;
        }
        if (isNaN(minValue) || minValue < 1) {
            minValue = 1;
        }
        if (isNaN(maxValue) || maxValue < minValue) {
            maxValue = minValue;
        }
        if (postLikeBaseMinInput) {
            postLikeBaseMinInput.value = String(baseMinValue);
        }
        if (postLikeBaseMaxInput) {
            postLikeBaseMaxInput.value = String(baseMaxValue);
        }
        if (postLikeIncrementMinInput) {
            postLikeIncrementMinInput.value = String(minValue);
        }
        if (postLikeIncrementMaxInput) {
            postLikeIncrementMaxInput.value = String(maxValue);
        }
        if (postLikeIncrementStatusNode) {
            postLikeIncrementStatusNode.textContent = '默认 ' + baseMinValue + '-' + baseMaxValue + '，每帖每天 ' + minValue + '-' + maxValue;
        }
    }

    function savePostLikeIncrementSettings(options) {
        var formData;
        var apiUrl;
        var silent = options && options.silent === true;

        if (!postLikeIncrementSettingsNode || !postLikeBaseMinInput || !postLikeBaseMaxInput || !postLikeIncrementMinInput || !postLikeIncrementMaxInput) {
            return window.Promise.resolve();
        }

        apiUrl = postLikeIncrementSettingsNode.getAttribute('data-api-url') || '';
        if (!apiUrl) {
            return window.Promise.resolve();
        }

        formData = new window.FormData();
        formData.append('action', 'admin.post_like_increment.save');
        formData.append('_token', postLikeIncrementSettingsNode.getAttribute('data-token') || '');
        formData.append('base_min', postLikeBaseMinInput.value || '368');
        formData.append('base_max', postLikeBaseMaxInput.value || '668');
        formData.append('increment_min', postLikeIncrementMinInput.value || '1');
        formData.append('increment_max', postLikeIncrementMaxInput.value || '1');

        return window.fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || '帖子点赞显示参数保存失败。');
                }

                return payload;
            });
        }).then(function (payload) {
            var settings = payload.data && payload.data.settings ? payload.data.settings : {};

            syncPostLikeIncrementStatus(settings);
            if (!silent) {
                showPostLockMessage(payload.message || '帖子点赞显示参数已保存。', 'success');
            }
            return payload;
        }).catch(function (error) {
            if (!silent) {
                showPostLockMessage(error.message || '帖子点赞显示参数保存失败。', 'error');
                return null;
            }
            throw error;
        });
    }

    function syncPostViewDisplayStatus(settings) {
        var baseMinValue;
        var baseMaxValue;
        var incrementMinValue;
        var incrementMaxValue;

        if (!settings) {
            return;
        }

        baseMinValue = parseInt(settings.base_min, 10);
        baseMaxValue = parseInt(settings.base_max, 10);
        incrementMinValue = parseInt(settings.increment_min, 10);
        incrementMaxValue = parseInt(settings.increment_max, 10);
        if (isNaN(baseMinValue) || baseMinValue < 1) {
            baseMinValue = 4935;
        }
        if (isNaN(baseMaxValue) || baseMaxValue < baseMinValue) {
            baseMaxValue = baseMinValue;
        }
        if (isNaN(incrementMinValue) || incrementMinValue < 1) {
            incrementMinValue = 14;
        }
        if (isNaN(incrementMaxValue) || incrementMaxValue < incrementMinValue) {
            incrementMaxValue = incrementMinValue;
        }
        if (postViewBaseMinInput) {
            postViewBaseMinInput.value = String(baseMinValue);
        }
        if (postViewBaseMaxInput) {
            postViewBaseMaxInput.value = String(baseMaxValue);
        }
        if (postViewIncrementMinInput) {
            postViewIncrementMinInput.value = String(incrementMinValue);
        }
        if (postViewIncrementMaxInput) {
            postViewIncrementMaxInput.value = String(incrementMaxValue);
        }
        if (postViewDisplayStatusNode) {
            postViewDisplayStatusNode.textContent = '默认 ' + baseMinValue + '-' + baseMaxValue + '，每次访问递增 ' + incrementMinValue + '-' + incrementMaxValue;
        }
    }

    function savePostViewDisplaySettings(options) {
        var formData;
        var apiUrl;
        var silent = options && options.silent === true;

        if (!postViewDisplaySettingsNode || !postViewBaseMinInput || !postViewBaseMaxInput || !postViewIncrementMinInput || !postViewIncrementMaxInput) {
            return window.Promise.resolve();
        }

        apiUrl = postViewDisplaySettingsNode.getAttribute('data-api-url') || '';
        if (!apiUrl) {
            return window.Promise.resolve();
        }

        formData = new window.FormData();
        formData.append('action', 'admin.post_view_display.save');
        formData.append('_token', postViewDisplaySettingsNode.getAttribute('data-token') || '');
        formData.append('base_min', postViewBaseMinInput.value || '4935');
        formData.append('base_max', postViewBaseMaxInput.value || '7563');
        formData.append('increment_min', postViewIncrementMinInput.value || '14');
        formData.append('increment_max', postViewIncrementMaxInput.value || '20');

        return window.fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || '帖子浏览量显示参数保存失败。');
                }

                return payload;
            });
        }).then(function (payload) {
            var settings = payload.data && payload.data.settings ? payload.data.settings : {};

            syncPostViewDisplayStatus(settings);
            if (!silent) {
                showPostLockMessage(payload.message || '帖子浏览量显示参数已保存。', 'success');
            }
            return payload;
        }).catch(function (error) {
            if (!silent) {
                showPostLockMessage(error.message || '帖子浏览量显示参数保存失败。', 'error');
                return null;
            }
            throw error;
        });
    }

    function syncPostSaleBuyerIncrementStatus(settings) {
        var incrementMinValue;
        var incrementMaxValue;

        if (!settings) {
            return;
        }

        incrementMinValue = parseInt(settings.increment_min, 10);
        incrementMaxValue = parseInt(settings.increment_max, 10);
        if (isNaN(incrementMinValue) || incrementMinValue < 0) {
            incrementMinValue = 0;
        }
        if (isNaN(incrementMaxValue) || incrementMaxValue < incrementMinValue) {
            incrementMaxValue = incrementMinValue;
        }
        if (postSaleBuyerIncrementMinInput) {
            postSaleBuyerIncrementMinInput.value = String(incrementMinValue);
        }
        if (postSaleBuyerIncrementMaxInput) {
            postSaleBuyerIncrementMaxInput.value = String(incrementMaxValue);
        }
        if (postSaleBuyerIncrementStatusNode) {
            postSaleBuyerIncrementStatusNode.textContent = '当前期随机递增 ' + incrementMinValue + '-' + incrementMaxValue + ' 人';
        }
    }

    function savePostSaleBuyerIncrementSettings(options) {
        var formData;
        var apiUrl;
        var silent = options && options.silent === true;

        if (!postSaleBuyerIncrementSettingsNode || !postSaleBuyerIncrementMinInput || !postSaleBuyerIncrementMaxInput) {
            return window.Promise.resolve();
        }

        apiUrl = postSaleBuyerIncrementSettingsNode.getAttribute('data-api-url') || '';
        if (!apiUrl) {
            return window.Promise.resolve();
        }

        formData = new window.FormData();
        formData.append('action', 'admin.post_sale_buyer_increment.save');
        formData.append('_token', postSaleBuyerIncrementSettingsNode.getAttribute('data-token') || '');
        formData.append('increment_min', postSaleBuyerIncrementMinInput.value || '0');
        formData.append('increment_max', postSaleBuyerIncrementMaxInput.value || '0');

        return window.fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            body: formData
        }).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || '出售购买递增参数保存失败。');
                }

                return payload;
            });
        }).then(function (payload) {
            var settings = payload.data && payload.data.settings ? payload.data.settings : {};

            syncPostSaleBuyerIncrementStatus(settings);
            if (!silent) {
                showPostLockMessage(payload.message || '出售购买递增参数已保存。', 'success');
            }
            return payload;
        }).catch(function (error) {
            if (!silent) {
                showPostLockMessage(error.message || '出售购买递增参数保存失败。', 'error');
                return null;
            }
            throw error;
        });
    }

    function formatAdminPostCount(value) {
        value = Math.max(0, parseInt(String(value || "0").replace(/,/g, ""), 10) || 0);
        return value.toLocaleString("en-US");
    }

    function findManagedPostRow(postId) {
        var rows = bulkForm.querySelectorAll("tr[data-post-id]");
        var index;

        postId = String(postId || "");
        for (index = 0; index < rows.length; index++) {
            if (String(rows[index].getAttribute("data-post-id") || "") === postId) {
                return rows[index];
            }
        }

        return null;
    }

    function setPostRowBusy(row, busy) {
        if (!row) {
            return;
        }

        row.classList.toggle("is-updating", !!busy);
    }

    function updatePostSeqState(row, action, value, label) {
        var toggleSelector = action === "set_segment_no" ? ".admin-posts-classic-seq-menu-toggle--segment" : ".admin-posts-classic-seq-menu-toggle--sort";
        var toggle = row ? row.querySelector(toggleSelector) : null;
        var options = row ? row.querySelectorAll("[data-post-seq-option]") : [];
        var sortValueNode = row ? row.querySelector(".admin-posts-classic-seq-param strong") : null;

        if (!row) {
            return;
        }

        Array.prototype.slice.call(options).forEach(function (option) {
            if ((option.getAttribute("data-post-seq-action") || "") !== action) {
                return;
            }

            option.classList.toggle("is-active", String(option.getAttribute("data-post-seq-value") || "") === String(value || ""));
        });

        if (toggle) {
            toggle.textContent = label || (action === "set_segment_sort" ? "置顶" + String(value || "") : toggle.textContent);
        }
        if (action === "set_segment_sort" && sortValueNode) {
            sortValueNode.textContent = String(value || "");
        }
    }

    function updatePostBuyerCount(row, increase) {
        var buyerNode = row ? row.querySelector(".is-buyer-param strong") : null;
        var currentValue;

        if (!buyerNode) {
            return;
        }

        currentValue = parseInt(String(buyerNode.textContent || "0").replace(/,/g, ""), 10) || 0;
        buyerNode.textContent = formatAdminPostCount(currentValue + Math.max(0, parseInt(increase, 10) || 0));
    }

    function removeManagedPostRow(row) {
        if (!row || !row.parentNode) {
            return;
        }

        row.parentNode.removeChild(row);
        itemCheckboxes = Array.prototype.slice.call(bulkForm.querySelectorAll('input[name="selected_ids[]"]'));
        syncCheckAllToggle();
    }

    function handleQuickActionSuccess(postId, action, options, payload) {
        var row = findManagedPostRow(postId);
        var status = payload && payload.data ? String(payload.data.status || "") : "";

        if (action === "delete") {
            if (status === "deleted") {
                removeManagedPostRow(row);
            }
            return;
        }
        if (action === "restore" || action === "purge") {
            removeManagedPostRow(row);
            return;
        }
        if (action === "set_segment_no" || action === "set_segment_sort") {
            updatePostSeqState(row, action, options.value || "", options.label || "");
            return;
        }
        if (action === "add_buyer") {
            updatePostBuyerCount(row, options.buyerCount || options.value || "1");
        }
    }

    function submitQuickAjax(postId, action, options) {
        var formData;
        var row;
        var sourceButton;
        var originalText;

        options = options || {};
        if (!window.fetch) {
            window.HTMLFormElement.prototype.submit.call(quickForm);
            return;
        }

        row = findManagedPostRow(postId);
        if (row && row.classList.contains("is-updating")) {
            return;
        }
        sourceButton = options.sourceButton || null;
        originalText = sourceButton ? sourceButton.textContent : "";
        if (sourceButton) {
            sourceButton.disabled = true;
            sourceButton.textContent = "处理中...";
        }
        setPostRowBusy(row, true);

        formData = new window.FormData(quickForm);
        formData.set("_response_format", "json");
        formData.set("_admin_action", "post_quick_action");

        window.fetch(quickForm.getAttribute("action") || window.location.href, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            },
            body: formData
        }).then(function (response) {
            return response.text().then(function (responseText) {
                var payload;

                try {
                    payload = JSON.parse(responseText || "{}");
                } catch (parseError) {
                    if (response.status === 401 || /^\s*</.test(responseText || "")) {
                        throw new Error("后台登录状态已失效，请重新登录后台后再操作。");
                    }
                    throw new Error("帖子操作失败，服务器返回格式异常。");
                }

                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || "帖子操作失败。");
                }

                handleQuickActionSuccess(postId, action, options, payload);
                showPostLockMessage(payload.message || "帖子操作已完成。", "success");
            });
        }).catch(function (error) {
            showPostLockMessage(error && error.message ? error.message : "帖子操作失败。", "error");
        }).then(function () {
            setPostRowBusy(row, false);
            if (sourceButton) {
                sourceButton.disabled = false;
                sourceButton.textContent = originalText;
            }
        });
    }

    function handleBulkActionSuccess(action, value, selectedCheckboxes) {
        selectedCheckboxes.forEach(function (checkbox) {
            var row = checkbox.closest("tr[data-post-id]");

            if (!row) {
                return;
            }
            if (action === "delete" || action === "restore" || action === "purge") {
                removeManagedPostRow(row);
                return;
            }
            if (action === "set_segment_no") {
                updatePostSeqState(row, action, value, "高手" + String(value || ""));
                checkbox.checked = false;
                return;
            }
            if (action === "set_segment_sort") {
                updatePostSeqState(row, action, value, "置顶" + String(value || ""));
                checkbox.checked = false;
                return;
            }
            checkbox.checked = false;
        });
        syncCheckAllToggle();
    }

    function submitBulkAjax(action, value) {
        var selectedCheckboxes = getSelectedCheckboxes();
        var formData;

        if (!window.fetch) {
            window.HTMLFormElement.prototype.submit.call(bulkForm);
            return;
        }
        if (bulkForm.getAttribute("data-submitting") === "1") {
            return;
        }

        bulkForm.setAttribute("data-submitting", "1");
        selectedCheckboxes.forEach(function (checkbox) {
            setPostRowBusy(checkbox.closest("tr[data-post-id]"), true);
        });

        formData = new window.FormData(bulkForm);
        formData.set("_response_format", "json");
        formData.set("_admin_action", "bulk_posts");

        window.fetch(bulkForm.getAttribute("action") || window.location.href, {
            method: "POST",
            credentials: "same-origin",
            headers: {
                "X-Requested-With": "XMLHttpRequest",
                "Accept": "application/json"
            },
            body: formData
        }).then(function (response) {
            return response.text().then(function (responseText) {
                var payload;

                try {
                    payload = JSON.parse(responseText || "{}");
                } catch (parseError) {
                    if (response.status === 401 || /^\s*</.test(responseText || "")) {
                        throw new Error("后台登录状态已失效，请重新登录后台后再操作。");
                    }
                    throw new Error("批量操作失败，服务器返回格式异常。");
                }

                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || "批量操作失败。");
                }

                handleBulkActionSuccess(action, value, selectedCheckboxes);
                showPostLockMessage(payload.message || "批量操作已完成。", "success");
            });
        }).catch(function (error) {
            showPostLockMessage(error && error.message ? error.message : "批量操作失败。", "error");
        }).then(function () {
            selectedCheckboxes.forEach(function (checkbox) {
                setPostRowBusy(checkbox.closest("tr[data-post-id]"), false);
            });
            bulkForm.removeAttribute("data-submitting");
        });
    }

    function submitBulk(action, value, confirmMessage) {
        var selectedCheckboxes = getSelectedCheckboxes();

        if (!selectedCheckboxes.length) {
            showPostLockMessage('请先勾选至少一条帖子。', 'error');
            return;
        }

        actionField.value = action || '';
        valueField.value = value || '';

        if (!actionField.value) {
            return;
        }

        if (confirmMessage) {
            if (!window.AppUI || typeof window.AppUI.confirm !== 'function') {
                return;
            }

            window.AppUI.confirm(confirmMessage, '确认操作', '确定', '取消').then(function (confirmed) {
                if (confirmed) {
                    submitBulkAjax(actionField.value, valueField.value);
                }
            });
            return;
        }

        submitBulkAjax(actionField.value, valueField.value);
    }

    function submitQuick(postId, action, options) {
        var confirmMessage;

        if (!quickForm || !quickActionField || !quickTargetField) {
            return;
        }

        options = options || {};
        confirmMessage = options.confirmMessage || '';

        quickTargetField.value = String(postId || '');
        quickActionField.value = action || '';

        if (quickValueField) {
            quickValueField.value = options.value || '';
        }
        if (quickBuyerField) {
            quickBuyerField.value = options.buyerCount || '';
        }
        if (quickMarkField) {
            quickMarkField.value = options.mark || '';
        }
        if (quickContentField) {
            quickContentField.value = options.content || '';
        }
        if (quickPriceField) {
            quickPriceField.value = options.price || '';
        }

        if (!quickActionField.value) {
            return;
        }

        if (confirmMessage) {
            if (!window.AppUI || typeof window.AppUI.confirm !== 'function') {
                return;
            }

            window.AppUI.confirm(confirmMessage, '确认操作', '确定', '取消').then(function (confirmed) {
                if (confirmed) {
                    submitQuickAjax(postId, action, options);
                }
            });
            return;
        }

        submitQuickAjax(postId, action, options);
    }

    function closeCurrentMaterialModal() {
        if (!currentMaterialModal) {
            return;
        }

        currentMaterialModal.hidden = true;
        currentMaterialModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('admin-posts-current-material-modal-open');
        currentMaterialActivePostId = '';
        currentMaterialActiveIssueTail = '';
        currentMaterialActiveSource = null;
        currentMaterialActiveRow = null;
        currentMaterialActiveButton = null;
    }

    function openCurrentMaterialModal(button) {
        var contentLayout;
        var sourceInput;
        var issueTail;
        var issueLabel;
        var modalTitle;

        if (!currentMaterialModal || !currentMaterialModalInput || !currentMaterialModalIssue) {
            return;
        }

        contentLayout = button.parentNode && button.parentNode.parentNode ? button.parentNode.parentNode : null;
        sourceInput = contentLayout ? contentLayout.querySelector('[data-post-current-material-source]') : null;
        issueTail = String(button.getAttribute('data-post-current-issue-tail') || '').replace(/\D+/g, '');
        issueLabel = button.getAttribute('data-post-current-issue-label') || '';
        modalTitle = button.getAttribute('data-post-current-modal-title') || '';
        if (!issueTail) {
            issueTail = String(issueLabel || modalTitle || '').replace(/\D+/g, '');
        }
        if (!issueTail && bulkForm) {
            issueTail = String(bulkForm.getAttribute('data-current-issue-tail') || '').replace(/\D+/g, '');
        }

        if (!sourceInput) {
            showPostLockMessage('当前期数资料内容不存在。', 'error');
            return;
        }
        if (!issueTail) {
            showPostLockMessage('当前期数不存在，无法编辑资料。', 'error');
            return;
        }

        currentMaterialActivePostId = button.getAttribute('data-post-id') || '';
        currentMaterialActiveIssueTail = issueTail;
        currentMaterialActiveSource = sourceInput;
        currentMaterialActiveRow = button.closest('tr');
        currentMaterialActiveButton = button;
        currentMaterialModalIssue.textContent = modalTitle
            || issueLabel
            || (issueTail + '期');
        currentMaterialModalInput.value = sourceInput.value || '';
        if (currentMaterialModalPrice) {
            currentMaterialModalPrice.value = String(Math.max(0, parseInt(button.getAttribute('data-post-current-price') || '0', 10) || 0));
        }
        currentMaterialModal.hidden = false;
        currentMaterialModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('admin-posts-current-material-modal-open');
        window.setTimeout(function () {
            currentMaterialModalInput.focus();
        }, 0);
    }

    function scrollContentRecordsToBottom() {
        bulkForm.querySelectorAll('.admin-posts-classic-content-main').forEach(function (contentNode) {
            contentNode.scrollTop = contentNode.scrollHeight;
        });
    }

    if (checkAllToggles.length) {
        checkAllToggles.forEach(function (checkAllToggle) {
            checkAllToggle.addEventListener('change', function () {
                window.setTimeout(syncCheckAllToggle, 0);
            });
        });
    }

    itemCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', syncCheckAllToggle);
    });

    syncCheckAllToggle();
    window.setTimeout(scrollContentRecordsToBottom, 0);

    if (postSettingsSaveButton) {
        postSettingsSaveButton.addEventListener('click', function () {
            var originalText = postSettingsSaveButton.textContent || '保存';
            var saveTasks = [];

            postSettingsSaveButton.disabled = true;
            postSettingsSaveButton.textContent = '保存中...';
            if (window.AdminPostManageGeneratorSettings && typeof window.AdminPostManageGeneratorSettings.save === 'function') {
                saveTasks.push(window.AdminPostManageGeneratorSettings.save());
            }
            saveTasks.push(savePostLockSettings({ silent: true }));
            saveTasks.push(savePostLikeIncrementSettings({ silent: true }));
            saveTasks.push(savePostViewDisplaySettings({ silent: true }));
            saveTasks.push(savePostSaleBuyerIncrementSettings({ silent: true }));

            window.Promise.all(saveTasks)
                .then(function () {
                    showPostLockMessage('帖子管理设置已全部保存。', 'success');
                })
                .catch(function (error) {
                    showPostLockMessage(error.message || '帖子管理设置保存失败。', 'error');
                })
                .then(function () {
                    postSettingsSaveButton.disabled = false;
                    postSettingsSaveButton.textContent = originalText;
                });
        });
    }

    bulkForm.querySelectorAll('[data-post-current-material-open]').forEach(function (button) {
        button.addEventListener('click', function () {
            openCurrentMaterialModal(button);
        });
    });

    if (currentMaterialModal) {
        currentMaterialModal.querySelectorAll('[data-post-current-material-close]').forEach(function (button) {
            button.addEventListener('click', closeCurrentMaterialModal);
        });
        currentMaterialModal.addEventListener('click', function (event) {
            var bracketButton = event.target.closest('[data-post-current-material-bracket]');
            var leftText;
            var rightText;
            var currentValue;
            var start;
            var end;

            if (!bracketButton || !currentMaterialModalInput) {
                return;
            }

            event.preventDefault();
            leftText = bracketButton.getAttribute('data-post-current-material-bracket-left') || '';
            rightText = bracketButton.getAttribute('data-post-current-material-bracket-right') || '';
            if (leftText === '' && rightText === '') {
                return;
            }

            currentValue = currentMaterialModalInput.value;
            start = typeof currentMaterialModalInput.selectionStart === 'number' ? currentMaterialModalInput.selectionStart : currentValue.length;
            end = typeof currentMaterialModalInput.selectionEnd === 'number' ? currentMaterialModalInput.selectionEnd : start;
            currentMaterialModalInput.value = currentValue.slice(0, start) + leftText + currentValue.slice(start, end) + rightText + currentValue.slice(end);
            currentMaterialModalInput.focus();
            currentMaterialModalInput.selectionStart = start + leftText.length;
            currentMaterialModalInput.selectionEnd = end + leftText.length;
        });
    }

    function renderCurrentMaterialLine(lineText) {
        var lineNode = document.createElement('div');
        var issueMatch;
        var issueNode;
        var separatorNode;
        var contentNode;

        lineNode.className = 'admin-posts-classic-content-line';
        issueMatch = String(lineText || '').match(/^(\d{1,4}期)([：:]?)(.*)$/);
        if (issueMatch) {
            issueNode = document.createElement('span');
            issueNode.className = 'admin-posts-classic-content-issue';
            issueNode.textContent = issueMatch[1];
            lineNode.appendChild(issueNode);
            if (issueMatch[2]) {
                separatorNode = document.createElement('span');
                separatorNode.className = 'admin-posts-classic-content-separator';
                separatorNode.textContent = issueMatch[2];
                lineNode.appendChild(separatorNode);
            }
            contentNode = document.createElement('span');
            contentNode.className = 'admin-posts-classic-content-text';
            contentNode.textContent = String(issueMatch[3] || '').trim();
            lineNode.appendChild(contentNode);
            return lineNode;
        }

        contentNode = document.createElement('span');
        contentNode.className = 'admin-posts-classic-content-text';
        contentNode.textContent = String(lineText || '');
        lineNode.appendChild(contentNode);
        return lineNode;
    }

    function normalizeCurrentMaterialLineText(lineText) {
        return String(lineText || '').replace(/\s+/g, ' ').trim();
    }

    function currentMaterialRowDisplayLine() {
        var lineNode;
        var displayLine;

        if (currentMaterialActiveButton) {
            displayLine = currentMaterialActiveButton.getAttribute('data-post-current-display-line') || '';
            if (displayLine !== '') {
                return normalizeCurrentMaterialLineText(displayLine);
            }
        }
        if (!currentMaterialActiveRow) {
            return '';
        }

        lineNode = currentMaterialActiveRow.querySelector('.admin-posts-classic-content-line');
        return lineNode ? normalizeCurrentMaterialLineText(lineNode.textContent || '') : '';
    }

    function buildCurrentMaterialDisplayLines(content) {
        var contentLines;
        var predictionText;
        var sourceLine;
        var recordMatch;
        var issueLabel;

        contentLines = String(content || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n').split(/\n+/).map(function (lineText) {
            return normalizeCurrentMaterialLineText(lineText);
        }).filter(function (lineText) {
            return lineText !== '';
        });
        if (contentLines.length <= 0) {
            return [];
        }

        predictionText = contentLines.join(' ');
        sourceLine = currentMaterialRowDisplayLine();
        recordMatch = sourceLine.match(/^(\s*\d{1,6}[^:：]{0,12}[:：]\s*)(\S+)\s+(\S+)\s+(.+?)\s+(开[:：]\s*.*?)\s*$/);
        if (recordMatch) {
            return [
                String(recordMatch[1] || '')
                + String(recordMatch[2] || '')
                + ' '
                + String(recordMatch[3] || '')
                + ' '
                + predictionText
                + ' '
                + String(recordMatch[5] || '')
            ];
        }

        if (/^\d{1,6}期[：:]?/.test(contentLines[0])) {
            return contentLines;
        }

        issueLabel = currentMaterialActiveButton
            ? normalizeCurrentMaterialLineText(currentMaterialActiveButton.getAttribute('data-post-current-issue-label') || '')
            : '';
        if (issueLabel !== '' && issueLabel !== '--期') {
            return [issueLabel + '：' + predictionText];
        }

        return contentLines;
    }

    function updateCurrentMaterialRow(content, price) {
        var contentMain;
        var saleBadge;
        var priceChip;
        var priceValue;
        var lines;

        if (!currentMaterialActiveRow) {
            return;
        }

        contentMain = currentMaterialActiveRow.querySelector('.admin-posts-classic-content-main');
        if (contentMain) {
            contentMain.innerHTML = '';
            lines = buildCurrentMaterialDisplayLines(content);
            lines.forEach(function (lineText) {
                lineText = String(lineText || '').trim();
                if (lineText !== '') {
                    contentMain.appendChild(renderCurrentMaterialLine(lineText));
                }
            });
            if (currentMaterialActiveButton && lines.length > 0) {
                currentMaterialActiveButton.setAttribute('data-post-current-display-line', lines[0]);
            }
            contentMain.scrollTop = contentMain.scrollHeight;
        }

        priceValue = Math.max(0, parseInt(price || '0', 10) || 0);
        saleBadge = currentMaterialActiveRow.querySelector('.admin-posts-classic-sale-badge');
        if (saleBadge) {
            saleBadge.textContent = priceValue > 0 ? '出售' : '普通';
            saleBadge.classList.toggle('is-sale', priceValue > 0);
            saleBadge.classList.toggle('is-normal', priceValue <= 0);
        }
        priceChip = currentMaterialActiveRow.querySelector('.is-price-param strong');
        if (priceChip) {
            priceChip.textContent = String(priceValue);
        }
        if (currentMaterialActiveButton) {
            currentMaterialActiveButton.setAttribute('data-post-current-price', String(priceValue));
        }
    }

    function submitCurrentMaterialAjax() {
        var originalText;
        var formData;
        var contentValue;
        var priceValue;

        if (!quickForm || !window.fetch) {
            submitQuick(currentMaterialActivePostId, 'save_current_issue_material', {
                value: currentMaterialActiveIssueTail,
                content: currentMaterialModalInput.value || '',
                price: currentMaterialModalPrice ? currentMaterialModalPrice.value || '0' : '0'
            });
            return;
        }

        contentValue = currentMaterialModalInput.value || '';
        priceValue = currentMaterialModalPrice ? currentMaterialModalPrice.value || '0' : '0';
        originalText = currentMaterialModalSave.textContent || '保存';
        currentMaterialModalSave.disabled = true;
        currentMaterialModalSave.textContent = '保存中...';

        formData = new window.FormData(quickForm);
        formData.set('_response_format', 'json');
        formData.set('_admin_action', 'post_quick_action');
        formData.set('target_post_id', String(currentMaterialActivePostId || ''));
        formData.set('quick_action', 'save_current_issue_material');
        formData.set('value', String(currentMaterialActiveIssueTail || ''));
        formData.set('content', contentValue);
        formData.set('price', priceValue);

        window.fetch(quickForm.getAttribute('action') || window.location.href, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        }).then(function (response) {
            return response.text().then(function (responseText) {
                var payload;

                try {
                    payload = JSON.parse(responseText || '{}');
                } catch (parseError) {
                    if (response.status === 401 || /^\s*</.test(responseText || '')) {
                        throw new Error('后台登录状态已失效，请重新登录后台后再保存。');
                    }
                    throw new Error('保存当前期数资料失败，服务器返回格式异常。');
                }

                if (!response.ok || !payload.success) {
                    throw new Error(payload.message || '保存当前期数资料失败。');
                }

                if (currentMaterialActiveSource) {
                    currentMaterialActiveSource.value = contentValue;
                }
                updateCurrentMaterialRow(contentValue, priceValue);
                closeCurrentMaterialModal();
                showPostLockMessage(payload.message || '当前期数资料已保存。', 'success');
            });
        }).catch(function (error) {
            showPostLockMessage(error && error.message ? error.message : '保存当前期数资料失败。', 'error');
        }).then(function () {
            currentMaterialModalSave.disabled = false;
            currentMaterialModalSave.textContent = originalText;
        });
    }

    if (currentMaterialModalSave) {
        currentMaterialModalSave.addEventListener('click', function () {
            if (!currentMaterialActivePostId || !currentMaterialActiveIssueTail || !currentMaterialModalInput) {
                showPostLockMessage('当前期数资料内容不存在。', 'error');
                return;
            }
            if (!window.AppUI || typeof window.AppUI.confirm !== 'function') {
                submitCurrentMaterialAjax();
                return;
            }

            window.AppUI.confirm('确认保存当前期数资料内容吗？', '确认操作', '确定', '取消').then(function (confirmed) {
                if (confirmed) {
                    submitCurrentMaterialAjax();
                }
            });
        });
    }

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && currentMaterialModal && !currentMaterialModal.hidden) {
            closeCurrentMaterialModal();
        }
    });

    document.querySelectorAll('[data-bulk-action]').forEach(function (button) {
        button.addEventListener('click', function () {
            submitBulk(
                button.getAttribute('data-bulk-action') || '',
                button.getAttribute('data-bulk-value') || '',
                '确认对选中的帖子执行这项批量操作吗？'
            );
        });
    });

    document.querySelectorAll('[data-bulk-select-action]').forEach(function (select) {
        select.addEventListener('change', function () {
            var action = select.getAttribute('data-bulk-select-action') || '';
            var selectedOption = select.options[select.selectedIndex] || null;
            var selectedText = selectedOption ? selectedOption.text : '';
            var value = select.value || '';

            if (!action || !value) {
                return;
            }

            submitBulk(
                action,
                value,
                selectedText ? '确认把选中的帖子批量设置为' + selectedText + '吗？' : '确认对选中的帖子执行这项批量操作吗？'
            );
            select.value = '';
        });
    });

    function closePostSeqMenus(exceptMenu) {
        bulkForm.querySelectorAll('[data-post-seq-menu]').forEach(function (menu) {
            var panel = menu.querySelector('[data-post-seq-menu-panel]');
            var toggle = menu.querySelector('[data-post-seq-menu-toggle]');

            if (exceptMenu && menu === exceptMenu) {
                return;
            }
            if (panel) {
                panel.hidden = true;
            }
            if (toggle) {
                toggle.setAttribute('aria-expanded', 'false');
            }
        });
    }

    bulkForm.querySelectorAll('[data-post-seq-menu-toggle]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            var menu = button.closest('[data-post-seq-menu]');
            var panel = menu ? menu.querySelector('[data-post-seq-menu-panel]') : null;
            var shouldOpen;

            event.preventDefault();
            event.stopPropagation();

            if (!menu || !panel) {
                return;
            }

            shouldOpen = panel.hidden;
            closePostSeqMenus(menu);
            panel.hidden = !shouldOpen;
            button.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
        });
    });

    bulkForm.querySelectorAll('[data-post-seq-option]').forEach(function (button) {
        button.addEventListener('click', function (event) {
            var action = button.getAttribute('data-post-seq-action') || '';
            var confirmMessage;
            var label = button.getAttribute('data-post-seq-label') || '';
            var postId = button.getAttribute('data-post-id') || '';
            var value = button.getAttribute('data-post-seq-value') || '';

            event.preventDefault();
            event.stopPropagation();
            closePostSeqMenus(null);

            if (!postId || !action || !value || button.classList.contains('is-active')) {
                return;
            }

            confirmMessage = action === 'set_segment_no'
                ? '确认把这条帖子移动到' + label + '吗？'
                : '确认把这条帖子排序位置设为' + label + '吗？';

            if (!window.AppUI || typeof window.AppUI.confirm !== 'function') {
                return;
            }

            window.AppUI.confirm(confirmMessage, '确认操作', '确定', '取消').then(function (confirmed) {
                if (!confirmed) {
                    return;
                }

                submitQuick(postId, action, {
                    value: value,
                    label: label,
                    sourceButton: button
                });
            });
        });
    });

    document.addEventListener('click', function (event) {
        if (event.target && typeof event.target.closest === 'function' && event.target.closest('[data-post-seq-menu]')) {
            return;
        }

        closePostSeqMenus(null);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closePostSeqMenus(null);
        }
    });

    bulkForm.querySelectorAll('[data-post-quick-action]').forEach(function (button) {
        button.addEventListener('click', function () {
            var action = button.getAttribute('data-post-quick-action') || '';
            var postId = button.getAttribute('data-post-id') || '';
            var value = '';

            if (!postId || !action) {
                return;
            }

            if (action === 'buyer_prompt') {
                if (!window.AppUI || typeof window.AppUI.prompt !== 'function') {
                    showPostLockMessage('站内输入组件未加载，请刷新页面后重试。', 'error');
                    return;
                }

                window.AppUI.prompt('请输入要增加的购买人数', '增加购买人数', '确定', '取消', '1').then(function (inputValue) {
                    if (inputValue === null) {
                        return;
                    }
                    value = String(Math.max(1, parseInt(inputValue, 10) || 0));
                    submitQuick(postId, 'add_buyer', {
                        buyerCount: value,
                        value: value,
                        sourceButton: button,
                        confirmMessage: '确认给这条帖子增加购买人数吗？'
                    });
                });
                return;
            }

            if (action === 'manual_specified') {
                if (!window.AppUI || typeof window.AppUI.prompt !== 'function') {
                    showPostLockMessage('站内输入组件未加载，请刷新页面后重试。', 'error');
                    return;
                }

                window.AppUI.prompt('请输入这条帖子的指定内容', '指定内容', '确定', '取消', '').then(function (inputValue) {
                    if (inputValue === null) {
                        return;
                    }
                    value = String(inputValue || '').trim();
                    if (!value) {
                        showPostLockMessage('指定内容不能为空，请重新输入。', 'error');
                        return;
                    }
                    submitQuick(postId, 'set_auto_specified', {
                        content: value,
                        value: value,
                        confirmMessage: '确认把这条帖子设置为指定内容自动更新吗？'
                    });
                });
                return;
            }

            if (action === 'delete' || action === 'restore' || action === 'purge') {
                submitQuick(postId, action, {
                    sourceButton: button,
                    confirmMessage: action === 'delete'
                        ? '确认将这条帖子移入回收站吗？'
                        : (action === 'restore' ? '确认恢复这条帖子到前台列表吗？' : '确认彻底删除这条帖子吗？')
                });
                return;
            }

            submitQuick(postId, action, {
                sourceButton: button,
                confirmMessage: '确认执行这项帖子操作吗？'
            });
        });
    });
})();
</script>
<?php endif; ?>
