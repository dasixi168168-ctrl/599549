<?php
$homeCanManage = !empty($homeCanManage);
$forecastPricingConfig = isset($forecastPricingConfig) && is_array($forecastPricingConfig)
    ? $forecastPricingConfig
    : app()->admins()->forecastPricingSettings();
$forecastPricingGroups = isset($forecastPricingConfig['groups']) && is_array($forecastPricingConfig['groups']) ? $forecastPricingConfig['groups'] : array();
$forecastPricingDiscounts = isset($forecastPricingConfig['discounts']) && is_array($forecastPricingConfig['discounts']) ? $forecastPricingConfig['discounts'] : array();
$forecastParticipationIncrement = max(0, min(9999, (int) ($forecastPricingConfig['participation_increment'] ?? 8)));
$forecastAnalysisPeriodMin = max(1, min(10000, (int) ($forecastPricingConfig['analysis_period_min'] ?? ($forecastPricingConfig['analysis_period'] ?? 20))));
$forecastAnalysisPeriodMax = max(1, min(10000, (int) ($forecastPricingConfig['analysis_period_max'] ?? ($forecastPricingConfig['analysis_period'] ?? 20))));
if ($forecastAnalysisPeriodMin > $forecastAnalysisPeriodMax) {
    $forecastAnalysisPeriodSwap = $forecastAnalysisPeriodMin;
    $forecastAnalysisPeriodMin = $forecastAnalysisPeriodMax;
    $forecastAnalysisPeriodMax = $forecastAnalysisPeriodSwap;
}
$forecastMemberDailyLimit = max(1, min(9999, (int) ($forecastPricingConfig['member_daily_limit'] ?? 5)));
$forecastApiUrlDefaults = array(
    'macau_live_api_url' => 'https://www.macaumarksix.com/api/live2',
    'macau_history_api_url' => 'https://history.macaumarksix.com/history/macaujc2/y/%d',
    'hongkong_live_api_url' => 'https://api.macaumarksix.com/api/hkjc.com',
    'hongkong_history_api_url' => 'https://en.lottolyzer.com/history/hong-kong/mark-six/page/%d/per-page/%d/detail-view',
);
$forecastApiUrls = isset($forecastPricingConfig['api_urls']) && is_array($forecastPricingConfig['api_urls'])
    ? $forecastPricingConfig['api_urls']
    : array();
foreach ($forecastApiUrlDefaults as $forecastApiUrlKey => $forecastApiUrlDefault) {
    if (trim((string) ($forecastApiUrls[$forecastApiUrlKey] ?? '')) === '') {
        $forecastApiUrls[$forecastApiUrlKey] = $forecastApiUrlDefault;
    }
}
$forecastPriceText = static function ($value) {
    return app()->admins()->formatForecastPoints($value);
};
?>

<section class="forecast-admin-shell">
    <form
        id="forecast-pricing-form"
        class="forecast-admin-form"
        method="post"
        action="<?php echo e(public_url('admin.php') . '?page=home'); ?>"
        data-forecast-pricing-form
    >
        <input type="hidden" name="_token" value="<?php echo e(csrf_token('admin.home')); ?>">
        <input type="hidden" name="_admin_form" value="page">
        <input type="hidden" name="_admin_action" value="save_forecast_pricing">

        <?php if (!$homeCanManage): ?>
            <div class="forecast-admin-readonly">当前账号只有查看权限，不能修改 AI预测选项价格。</div>
        <?php endif; ?>

        <section class="forecast-discount-board">
            <div class="forecast-config-panel forecast-api-url-panel">
                <div class="forecast-config-panel-head forecast-api-url-panel-head">
                    <span class="forecast-admin-kicker is-blue">开奖接口</span>
                    <small>澳门 / 香港实时与历史数据源</small>
                </div>
                <div class="forecast-api-url-grid">
                    <fieldset class="forecast-api-url-region">
                        <legend>澳门</legend>
                        <label class="forecast-api-url-field">
                            <span>澳门实时开奖</span>
                            <input
                                type="url"
                                name="api_urls[macau_live_api_url]"
                                value="<?php echo e((string) $forecastApiUrls['macau_live_api_url']); ?>"
                                inputmode="url"
                                spellcheck="false"
                                <?php echo $homeCanManage ? '' : 'readonly'; ?>
                            >
                            <small>当前 / 实时开奖数据</small>
                        </label>
                        <label class="forecast-api-url-field">
                            <span>历史开奖记录</span>
                            <input
                                type="text"
                                name="api_urls[macau_history_api_url]"
                                value="<?php echo e((string) $forecastApiUrls['macau_history_api_url']); ?>"
                                inputmode="url"
                                spellcheck="false"
                                <?php echo $homeCanManage ? '' : 'readonly'; ?>
                            >
                            <small>必须保留 1 个 %d 年份占位</small>
                        </label>
                    </fieldset>
                    <fieldset class="forecast-api-url-region">
                        <legend>香港</legend>
                        <label class="forecast-api-url-field">
                            <span>香港实时开奖</span>
                            <input
                                type="url"
                                name="api_urls[hongkong_live_api_url]"
                                value="<?php echo e((string) $forecastApiUrls['hongkong_live_api_url']); ?>"
                                inputmode="url"
                                spellcheck="false"
                                <?php echo $homeCanManage ? '' : 'readonly'; ?>
                            >
                            <small>当前 / 实时开奖数据</small>
                        </label>
                        <label class="forecast-api-url-field">
                            <span>历史开奖记录</span>
                            <input
                                type="text"
                                name="api_urls[hongkong_history_api_url]"
                                value="<?php echo e((string) $forecastApiUrls['hongkong_history_api_url']); ?>"
                                inputmode="url"
                                spellcheck="false"
                                <?php echo $homeCanManage ? '' : 'readonly'; ?>
                            >
                            <small>必须保留 2 个 %d 页码占位</small>
                        </label>
                    </fieldset>
                </div>
            </div>
            <section class="forecast-config-panel forecast-basic-panel">
                <div class="forecast-config-panel-head forecast-section-head">
                    <span class="forecast-admin-kicker is-blue">基础参数</span>
                    <small>人数增量 · n-m期范围 · 每日限次</small>
                </div>
                <div class="forecast-basic-table">
                    <label class="forecast-control-card">
                        <span class="forecast-control-card-head">
                            <strong>参与人数增量</strong>
                        </span>
                        <span class="forecast-control-card-body">
                            <input
                                type="number"
                                name="participation_increment"
                                min="0"
                                max="9999"
                                step="1"
                                value="<?php echo e((string) $forecastParticipationIncrement); ?>"
                                aria-label="参与人数增量（人）"
                                <?php echo $homeCanManage ? '' : 'readonly'; ?>
                            >
                            <span class="forecast-control-unit">人</span>
                        </span>
                    </label>
                    <div class="forecast-control-card">
                        <span class="forecast-control-card-head">
                            <strong>分析期数</strong>
                        </span>
                        <div class="forecast-control-card-body forecast-basic-control-period">
                            <span class="forecast-analysis-period-range">
                                <input
                                    type="number"
                                    name="analysis_period_min"
                                    min="1"
                                    max="10000"
                                    step="1"
                                    value="<?php echo e((string) $forecastAnalysisPeriodMin); ?>"
                                    aria-label="分析起始期数 n"
                                    <?php echo $homeCanManage ? '' : 'readonly'; ?>
                                >
                                <span class="forecast-analysis-period-separator" aria-hidden="true">-</span>
                                <input
                                    type="number"
                                    name="analysis_period_max"
                                    min="1"
                                    max="10000"
                                    step="1"
                                    value="<?php echo e((string) $forecastAnalysisPeriodMax); ?>"
                                    aria-label="分析结束期数 m"
                                    <?php echo $homeCanManage ? '' : 'readonly'; ?>
                                >
                                <span class="forecast-control-unit">期</span>
                            </span>
                        </div>
                    </div>
                    <label class="forecast-control-card">
                        <span class="forecast-control-card-head">
                            <strong>会员每日上限</strong>
                        </span>
                        <span class="forecast-control-card-body">
                            <input
                                type="number"
                                name="member_daily_limit"
                                min="1"
                                max="9999"
                                step="1"
                                value="<?php echo e((string) $forecastMemberDailyLimit); ?>"
                                aria-label="会员每日上限（次）"
                                <?php echo $homeCanManage ? '' : 'readonly'; ?>
                            >
                            <span class="forecast-control-unit">次</span>
                        </span>
                    </label>
                </div>
            </section>
            <section class="forecast-config-panel forecast-discount-panel">
                <div class="forecast-config-panel-head forecast-discount-copy">
                    <span class="forecast-admin-kicker is-gold">类型优惠</span>
                    <small>按选中类型数量计算折扣</small>
                </div>
                <div class="forecast-discount-grid">
                    <?php foreach (array('1' => '选一项', '2' => '选两项', '3' => '选三项', '4' => '选四项') as $countKey => $countLabel): ?>
                        <label class="forecast-control-card">
                            <span class="forecast-control-card-head">
                                <strong><?php echo e($countLabel); ?></strong>
                                <small><?php echo $countKey === '1' ? '无优惠' : '按百分比计价'; ?></small>
                            </span>
                            <span class="forecast-control-card-body">
                                <input
                                    type="number"
                                    name="discounts[<?php echo e($countKey); ?>]"
                                    min="1"
                                    max="100"
                                    step="1"
                                    value="<?php echo e((string) (int) ($forecastPricingDiscounts[$countKey] ?? 100)); ?>"
                                    aria-label="<?php echo e($countLabel); ?>计价比例（百分比）"
                                    data-forecast-discount="<?php echo e($countKey); ?>"
                                    <?php echo $homeCanManage ? '' : 'disabled'; ?>
                                >
                                <span class="forecast-control-unit">%</span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </section>
        </section>

        <section class="forecast-admin-layout">
            <div class="forecast-option-stack">
                <?php foreach ($forecastPricingGroups as $typeKey => $group): ?>
                    <article class="forecast-config-panel forecast-option-panel is-<?php echo e((string) $typeKey); ?>">
                        <div class="forecast-config-panel-head forecast-option-panel-head">
                            <div>
                                <span><?php echo e((string) ($group['label'] ?? $typeKey)); ?>价格</span>
                                <small>单项价格 · 单位：积分</small>
                            </div>
                        </div>
                        <div class="forecast-option-table">
                            <?php foreach ((array) ($group['options'] ?? array()) as $option): ?>
                                <?php
                                $optionValue = (string) ($option['value'] ?? '');
                                $optionLabel = (string) ($option['label'] ?? '');
                                $optionPrice = $forecastPriceText($option['price'] ?? 0);
                                ?>
                                <div class="forecast-option-row">
                                    <input type="hidden" name="enabled[<?php echo e((string) $typeKey); ?>][<?php echo e($optionValue); ?>]" value="1">
                                    <input type="hidden" name="labels[<?php echo e((string) $typeKey); ?>][<?php echo e($optionValue); ?>]" value="<?php echo e($optionLabel); ?>">
                                    <span class="forecast-option-name is-fixed" data-forecast-option-label><?php echo e($optionLabel); ?></span>
                                    <label class="forecast-price-field">
                                        <input
                                            type="number"
                                            name="prices[<?php echo e((string) $typeKey); ?>][<?php echo e($optionValue); ?>]"
                                            min="0"
                                            max="999999"
                                            step="0.01"
                                            value="<?php echo e($optionPrice); ?>"
                                            aria-label="<?php echo e($optionLabel); ?>价格（积分）"
                                            data-forecast-price-input
                                            <?php echo $homeCanManage ? '' : 'readonly'; ?>
                                        >
                                        <span class="forecast-control-unit">积分</span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        </section>
    </form>
</section>
