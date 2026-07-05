<?php
$regionName = $region === 'hongkong' ? '香港' : '澳门';
$draws = isset($recentDraws) && is_array($recentDraws) ? $recentDraws : array();
$pageHistoryTitle = $regionName . '开奖记录';
$redWave = array(1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46);
$blueWave = array(3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48);
$predictionService = app()->prediction();
$resolveZodiacByNumber = static function ($number, $drawDate) use ($predictionService) {
    $zodiac = $predictionService->drawZodiacByNumber($number, $drawDate);

    return $zodiac !== '' ? $zodiac : '--';
};
$formatIssueBadge = static function ($issueNo) use ($region) {
    $text = trim((string) $issueNo);
    if ($text === '') {
        return '--';
    }

    if ($region === 'hongkong') {
        $numeric = preg_match('/^\d+$/', $text) ? (int) $text : null;
        if ($numeric !== null) {
            $text = str_pad((string) ($numeric % 1000), 3, '0', STR_PAD_LEFT);
        }

        return '香港 ' . $text . '期';
    }

    return '澳门 ' . str_pad((string) (((int) $text) % 1000), 3, '0', STR_PAD_LEFT) . '期';
};
$padNumber = static function ($number) {
    $value = (int) $number;
    return $value > 0 ? str_pad((string) $value, 2, '0', STR_PAD_LEFT) : '--';
};
$ballToneClass = static function ($number) use ($redWave, $blueWave) {
    $value = (int) $number;

    if (in_array($value, $redWave, true)) {
        return 'red';
    }

    if (in_array($value, $blueWave, true)) {
        return 'blue';
    }

    return 'green';
};
?>
<?php echo \App\Core\View::make(app(), 'partials/front_top_bar', array('region' => $region)); ?>
<section class="front-page-shell front-unified-page history-page">
    <div class="section-title history-section-title bg-gradient-to-r from-indigo-600 to-blue-500">
        <span class="history-section-title-main"><i class="fa-solid fa-clock-rotate-left"></i><?php echo e($pageHistoryTitle); ?></span>
        <a href="<?php echo e(public_url($region === 'hongkong' ? 'record.php' : 'index.php')); ?>" class="history-list-back-btn">返回首页</a>
    </div>
    <div class="data-frame front-panel-stack front-unified-frame history-frame">
        <div class="front-list-stack history-list-stack">
            <?php if (!empty($draws)): ?>
                <?php foreach ($draws as $drawItem): ?>
                    <?php
                    $numbers = array_slice(array_values(array_map('intval', (array) json_decode((string) $drawItem['numbers_json'], true))), 0, 6);
                    while (count($numbers) < 6) {
                        $numbers[] = 0;
                    }
                    $specialNumber = (int) $drawItem['special_number'];
                    $drawDate = (string) ($drawItem['draw_date'] ?? '');
                    $specialZodiac = $resolveZodiacByNumber($specialNumber, $drawDate);
                    ?>
                    <div class="history-draw-card history-draw-card-rich">
                        <div class="history-draw-card-head">
                            <span class="history-draw-issue"><?php echo e($formatIssueBadge($drawItem['issue_no'])); ?></span>
                            <strong class="history-draw-date"><?php echo e($drawDate !== '' ? $drawDate : '--'); ?></strong>
                        </div>
                        <div class="history-draw-rows">
                            <div class="history-draw-items history-live-numbers hero-live-numbers" aria-label="开奖结果">
                                <?php foreach ($numbers as $number): ?>
                                    <span class="hero-ball-item">
                                        <span class="result-jl-code <?php echo e($ballToneClass($number)); ?>"><?php echo e($padNumber($number)); ?></span>
                                        <span class="hero-ball-zodiac <?php echo e($ballToneClass($number)); ?>"><?php echo e($resolveZodiacByNumber($number, $drawDate)); ?></span>
                                    </span>
                                <?php endforeach; ?>
                                <span class="hero-ball-plus result-jl-plus" aria-hidden="true">+</span>
                                <span class="hero-ball-item">
                                    <span class="result-jl-code <?php echo e($ballToneClass($specialNumber)); ?>"><?php echo e($padNumber($specialNumber)); ?></span>
                                    <span class="hero-ball-zodiac <?php echo e($ballToneClass($specialNumber)); ?>"><?php echo e($specialZodiac); ?></span>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="rounded-[14px] border border-dashed border-blue-300 bg-white px-4 py-5 text-sm text-blue-500">当前还没有可展示的<?php echo e($pageHistoryTitle); ?>。</div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php echo \App\Core\View::make(app(), 'partials/front_bottom_nav', array('region' => $region, 'activePanel' => '', 'user' => $user)); ?>
