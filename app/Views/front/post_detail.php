<?php $isModalPost = strpos((string) ($bodyClass ?? ''), 'standalone-modal-post') !== false; ?>
<?php $frontAdminHistoryEmbed = !empty($adminHistoryEmbed); ?>
<?php $frontCustomerServiceAgentViewer = !empty($customerServiceAgentViewer); ?>
<?php if (!$isModalPost): ?>
    <?php echo \App\Core\View::make(app(), 'partials/front_top_bar', array('region' => $region)); ?>
<?php endif; ?>
<section class="front-page-shell" data-comment-thread data-api-url="<?php echo e($commentThreadApiUrl); ?>" data-token="<?php echo e(csrf_token('api')); ?>" data-post-id="<?php echo e((string) $post['id']); ?>" data-login-url="<?php echo e($commentThreadLoginUrl); ?>">
    <?php if (!$isModalPost): ?>
        <div class="front-detail-actions">
            <a href="<?php echo e($backUrl); ?>" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">
                <i class="fa-solid fa-angle-left"></i>
                返回列表
            </a>
            <a href="<?php echo e(public_url('service.php') . '?region=' . urlencode($region)); ?>" class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-bold text-slate-700 shadow-sm transition hover:bg-slate-50">
                <i class="fa-solid fa-headset"></i>
                在线客服
            </a>
        </div>
    <?php endif; ?>

    <div class="data-frame front-panel-stack front-post-detail-stack mt-4">
        <?php if ($isModalPost): ?>
            <div class="front-post-modal-sync-source" hidden aria-hidden="true">
                <h1 data-post-display-title><?php echo isset($displayTitleHtml) && $displayTitleHtml !== '' ? $displayTitleHtml : e(isset($displayTitle) ? $displayTitle : $post['title']); ?></h1>
                <div class="front-inline-meta">
                    <span>作者：<?php echo e($post['author_name']); ?></span>
                    <button class="front-post-like-button <?php echo !empty($postLikedByViewer) ? 'is-liked' : ''; ?>" type="button" data-post-like data-post-id="<?php echo e((string) $post['id']); ?>" data-token="<?php echo e((string) ($postViewApiToken ?? '')); ?>" data-api-url="<?php echo e(public_url('api.php')); ?>" aria-pressed="<?php echo !empty($postLikedByViewer) ? 'true' : 'false'; ?>">
                        <i class="fa-solid fa-thumbs-up" aria-hidden="true"></i>
                        <strong data-post-like-count><?php echo e((string) ($postLikeCount ?? 0)); ?></strong>
                    </button>
                    <span>发布时间：<?php echo e(format_datetime($post['created_at'])); ?></span>
                    <span>价格：<?php echo !empty($salePostOpenedForPublic) ? '已公开' : ((int) $post['price'] > 0 ? e($post['price'] . ' 积分') : '免费'); ?></span>
                    <span class="front-post-view-text">浏览：<span data-post-view-count><?php echo e((string) ($displayViewCount ?? 0)); ?></span></span>
                </div>
            </div>
        <?php else: ?>
            <div class="front-panel-card">
                <h1 class="text-[28px] font-black leading-tight text-slate-900" data-post-display-title><?php echo isset($displayTitleHtml) && $displayTitleHtml !== '' ? $displayTitleHtml : e(isset($displayTitle) ? $displayTitle : $post['title']); ?></h1>
                <div class="front-inline-meta mt-4">
                    <span>作者：<?php echo e($post['author_name']); ?></span>
                    <button class="front-post-like-button <?php echo !empty($postLikedByViewer) ? 'is-liked' : ''; ?>" type="button" data-post-like data-post-id="<?php echo e((string) $post['id']); ?>" data-token="<?php echo e((string) ($postViewApiToken ?? '')); ?>" data-api-url="<?php echo e(public_url('api.php')); ?>" aria-pressed="<?php echo !empty($postLikedByViewer) ? 'true' : 'false'; ?>">
                        <i class="fa-solid fa-thumbs-up" aria-hidden="true"></i>
                        <strong data-post-like-count><?php echo e((string) ($postLikeCount ?? 0)); ?></strong>
                    </button>
                    <span>发布时间：<?php echo e(format_datetime($post['created_at'])); ?></span>
                    <span>价格：<?php echo !empty($salePostOpenedForPublic) ? '已公开' : ((int) $post['price'] > 0 ? e($post['price'] . ' 积分') : '免费'); ?></span>
                    <span class="front-post-view-text">浏览：<span data-post-view-count><?php echo e((string) ($displayViewCount ?? 0)); ?></span></span>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($postTopHtml !== ''): ?>
            <div class="front-panel-card"><?php echo $postTopHtml; ?></div>
        <?php endif; ?>

        <?php
        $frontSaleNoticeText = '此资料出售，购买后可查看完整资料';
        $frontSaleLegacyNoticeText = '此内容为出售内容，购买后可查看完整资料。';
        $frontContentText = (string) $displayContent;
        $frontSaleContentLocked = $purchaseNeeded && in_array(
            trim($frontContentText),
            array($frontSaleNoticeText, $frontSaleLegacyNoticeText),
            true
        );
        if ($frontSaleContentLocked) {
            $frontContentText = (string) ($post['full_content'] ?? '');
        }
        $frontSaleBuyerSummary = array('issue_tail' => '', 'total' => 0, 'buyers' => array());
        if ((int) ($post['price'] ?? 0) > 0) {
            try {
                $frontSaleBuyerSummary = app()->posts()->salePostCurrentIssueBuyerSummary($post);
            } catch (\Throwable $exception) {
                $frontSaleBuyerSummary = array('issue_tail' => '', 'total' => 0, 'buyers' => array());
            }
        }
        $frontSaleCurrentIssueTail = '';
        $frontSaleDisplayTitle = isset($displayTitle) ? (string) $displayTitle : (string) ($post['title'] ?? '');
        if (preg_match('/(\d+)/u', $frontSaleDisplayTitle, $frontSaleIssueMatches)) {
            $frontSaleIssueDigits = preg_replace('/\D+/', '', (string) ($frontSaleIssueMatches[1] ?? ''));
            if ($frontSaleIssueDigits !== '') {
                $frontSaleCurrentIssueTail = str_pad(substr($frontSaleIssueDigits, -3), 3, '0', STR_PAD_LEFT);
            }
        }
        $frontPendingDrawChars = preg_split('//u', '高手发表待开奖', -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($frontPendingDrawChars)) {
            $frontPendingDrawChars = array();
        }
        $frontTitleSegments = app()->posts()->displayTitleSegments($post);
        $frontForecastDisplayTypeText = trim((string) ($frontTitleSegments['middle'] ?? ''));
        if (
            $frontForecastDisplayTypeText !== ''
            && preg_match('/^[【〖《｛〔『]\s*(.+?)\s*[】〗》｝〕』]$/u', $frontForecastDisplayTypeText, $frontForecastDisplayTypeMatch)
        ) {
            $frontForecastDisplayTypeText = trim((string) ($frontForecastDisplayTypeMatch[1] ?? ''));
        }
        $frontForecastTypeClass = '';
        $frontTitleTextStyle = app()->posts()->displayTitleTextStyle($post);
        if (preg_match('/(?:^|;)font-size\s*:\s*(\d+)px\s*;/i', $frontTitleTextStyle, $frontTitleFontSizeMatch)) {
            $frontForecastTypeFontSize = (int) ($frontTitleFontSizeMatch[1] ?? 0);
            if ($frontForecastTypeFontSize > 0) {
                $frontForecastTypeFontSizeSteps = array(12, 13, 14, 15, 16, 17, 18, 20, 22, 24);
                foreach ($frontForecastTypeFontSizeSteps as $frontForecastTypeFontSizeStep) {
                    if ($frontForecastTypeFontSizeStep > $frontForecastTypeFontSize) {
                        $frontForecastTypeFontSize = $frontForecastTypeFontSizeStep;
                        break;
                    }
                }
                $frontForecastTypeClass = ' is-type-size-' . (string) $frontForecastTypeFontSize;
            }
        }
        $frontNoMaterialWaitingContent = "资料等待更新中··· ···\n关注本站，精彩无限，中奖根本停不下来······";
        $frontNoMaterialWaitingDisplayContent = app()->admins()->managedPostWaitingDisplayContent($region);
        $frontDisplayContentText = trim($frontContentText) === $frontNoMaterialWaitingContent
            ? $frontNoMaterialWaitingDisplayContent
            : $frontContentText;
        $frontPlainContentClass = 'mt-4 whitespace-pre-line text-[15px] leading-8 text-slate-700';
        if (trim($frontContentText) === $frontNoMaterialWaitingContent) {
            $frontPlainContentClass .= ' text-center';
        }
        $frontNormalizePredictionText = static function ($predictionText) {
            $predictionText = trim((string) $predictionText);
            $predictionText = preg_replace('/^[【〖《｛〔『]\s*/u', '', $predictionText);
            $predictionText = preg_replace('/\s*[】〗》｝〕』]$/u', '', (string) $predictionText);
            $predictionText = str_replace('+', ' ', (string) $predictionText);

            return trim((string) $predictionText);
        };
        $frontFormatTypeTitleNumbersHtml = static function ($text) {
            $text = (string) $text;
            if ($text === '') {
                return '';
            }
            $pattern = '/(\d+|[零一二两三四五六七八九十百千万]+|[①-⑳㉑-㊿㈠-㈩⑴-⒇⒈-⒛])/u';
            if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                return e($text);
            }
            $html = '';
            $offset = 0;
            foreach ((array) ($matches[0] ?? array()) as $match) {
                $value = (string) ($match[0] ?? '');
                $position = (int) ($match[1] ?? 0);
                if ($position > $offset) {
                    $html .= e(substr($text, $offset, $position - $offset));
                }
                $html .= '<span class="type-title-number">' . e($value) . '</span>';
                $offset = $position + strlen($value);
            }
            if ($offset < strlen($text)) {
                $html .= e(substr($text, $offset));
            }

            return $html;
        };
        $frontResolveDrawLunarYear = static function ($drawDate) {
            $lunarNewYearDates = array(
                2020 => '2020-01-25',
                2021 => '2021-02-12',
                2022 => '2022-02-01',
                2023 => '2023-01-22',
                2024 => '2024-02-10',
                2025 => '2025-01-29',
                2026 => '2026-02-17',
                2027 => '2027-02-06',
                2028 => '2028-01-26',
                2029 => '2029-02-13',
                2030 => '2030-02-03',
                2031 => '2031-01-23',
                2032 => '2032-02-11',
                2033 => '2033-01-31',
                2034 => '2034-02-19',
                2035 => '2035-02-08',
            );
            $drawDate = trim((string) $drawDate);
            $timestamp = $drawDate !== '' ? strtotime($drawDate) : false;
            $year = $timestamp !== false ? (int) date('Y', $timestamp) : (int) date('Y');
            if (!isset($lunarNewYearDates[$year])) {
                return $year;
            }

            return substr($drawDate, 0, 10) >= $lunarNewYearDates[$year] ? $year : ($year - 1);
        };
        $frontResolveZodiacByNumber = static function ($number, $drawDate) use ($frontResolveDrawLunarYear) {
            $number = (int) $number;
            if ($number <= 0 || $number > 49) {
                return '';
            }
            $zodiacAnimals = array('鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪');
            $lunarYear = $frontResolveDrawLunarYear($drawDate);
            $yearAnimalIndex = ($lunarYear - 4) % 12;
            if ($yearAnimalIndex < 0) {
                $yearAnimalIndex += 12;
            }
            $groupIndex = ($number - 1) % 12;
            $animalIndex = ($yearAnimalIndex - $groupIndex) % 12;
            if ($animalIndex < 0) {
                $animalIndex += 12;
            }

            return $zodiacAnimals[$animalIndex];
        };
        $frontResolveDrawRowByIssue = static function ($issueText) use ($region) {
            static $drawRowCache = array();

            $issueDigits = preg_replace('/\D+/', '', (string) $issueText);
            if ($issueDigits === '') {
                return null;
            }
            $issueTail = str_pad(substr($issueDigits, -3), 3, '0', STR_PAD_LEFT);
            $cacheRegion = $region === 'hongkong' ? 'hongkong' : 'macau';
            $cacheKey = $cacheRegion . '|' . $issueDigits . '|' . $issueTail;
            if (array_key_exists($cacheKey, $drawRowCache)) {
                return $drawRowCache[$cacheKey];
            }
            try {
                $drawRow = db()->fetch(
                    'SELECT draw_date, numbers_json, special_number
                     FROM lottery_draws
                     WHERE region = :region
                       AND (issue_no = :issue_no OR RIGHT(issue_no, 3) = :issue_tail)
                     ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
                     LIMIT 1',
                    array(
                        'region' => $cacheRegion,
                        'issue_no' => $issueDigits,
                        'issue_tail' => $issueTail,
                    )
                );
            } catch (\Throwable $exception) {
                $drawRow = null;
            }
            $drawRowCache[$cacheKey] = is_array($drawRow) ? $drawRow : null;

            return $drawRowCache[$cacheKey];
        };
        $frontResolveDrawDateByIssue = static function ($issueText) use ($frontResolveDrawRowByIssue) {
            $drawRow = $frontResolveDrawRowByIssue($issueText);

            return is_array($drawRow) ? trim((string) ($drawRow['draw_date'] ?? '')) : '';
        };
        $frontSaleOpenedIssueTail = '';
        if (
            $frontSaleContentLocked
            && $frontSaleCurrentIssueTail !== ''
            && is_array($frontResolveDrawRowByIssue($frontSaleCurrentIssueTail))
        ) {
            $frontSaleOpenedIssueTail = $frontSaleCurrentIssueTail;
        }
        if ($frontSaleOpenedIssueTail === '' && $frontSaleContentLocked) {
            $frontSaleContentIssueTails = array();
            foreach (preg_split('/\R/u', $frontContentText) as $frontSaleContentIssueLine) {
                $frontSaleContentIssueLine = trim((string) $frontSaleContentIssueLine);
                if (!preg_match('/^(\d{1,6})[^:：]{0,12}[:：]/u', $frontSaleContentIssueLine, $frontSaleContentIssueMatches)) {
                    continue;
                }
                $frontSaleContentIssueDigits = preg_replace('/\D+/', '', (string) ($frontSaleContentIssueMatches[1] ?? ''));
                if ($frontSaleContentIssueDigits !== '') {
                    $frontSaleContentIssueTails[] = str_pad(substr($frontSaleContentIssueDigits, -3), 3, '0', STR_PAD_LEFT);
                }
            }
            $frontSaleContentIssueTails = array_values(array_unique($frontSaleContentIssueTails));
            if (count($frontSaleContentIssueTails) === 1 && is_array($frontResolveDrawRowByIssue($frontSaleContentIssueTails[0]))) {
                $frontSaleOpenedIssueTail = $frontSaleContentIssueTails[0];
            }
        }
        $frontSaleCurrentIssueOpened = $frontSaleOpenedIssueTail !== '';
        if ($frontSaleCurrentIssueOpened) {
            $frontSaleCurrentIssueTail = $frontSaleOpenedIssueTail;
            $frontSaleContentLocked = false;
            $purchaseNeeded = false;
        }
        $frontResolveNumberWave = static function ($number) {
            $number = (int) $number;
            if (in_array($number, array(1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46), true)) {
                return array('name' => '红波', 'short' => '红', 'class' => 'is-red');
            }
            if (in_array($number, array(3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48), true)) {
                return array('name' => '蓝波', 'short' => '蓝', 'class' => 'is-blue');
            }

            return array('name' => '绿波', 'short' => '绿', 'class' => 'is-green');
        };
        $frontResolveNumberElement = static function ($number) {
            $number = (int) $number;
            $elementGroups = array(
                '金' => array(2, 3, 10, 11, 24, 25, 32, 33, 40, 41),
                '木' => array(6, 7, 14, 15, 22, 23, 36, 37, 44, 45),
                '水' => array(12, 13, 20, 21, 28, 29, 42, 43),
                '火' => array(1, 8, 9, 16, 17, 30, 31, 38, 39, 46, 47),
                '土' => array(4, 5, 18, 19, 26, 27, 34, 35, 48, 49),
            );
            foreach ($elementGroups as $elementName => $elementNumbers) {
                if (in_array($number, $elementNumbers, true)) {
                    return $elementName;
                }
            }

            return '';
        };
        $frontOpenResultInfo = static function ($openResult, $issueText = '') use ($frontResolveDrawDateByIssue, $frontResolveDrawRowByIssue, $frontResolveZodiacByNumber, $frontResolveNumberWave, $frontResolveNumberElement) {
            $openResult = trim((string) $openResult);
            $drawRow = $frontResolveDrawRowByIssue($issueText);
            $drawDate = is_array($drawRow) ? trim((string) ($drawRow['draw_date'] ?? '')) : '';
            if (
                ($openResult === '' || $openResult === '--' || mb_strpos($openResult, '待开奖', 0, 'UTF-8') !== false)
                && is_array($drawRow)
            ) {
                $specialNumber = (int) ($drawRow['special_number'] ?? 0);
                if ($specialNumber >= 1 && $specialNumber <= 49) {
                    $openResult = str_pad((string) $specialNumber, 2, '0', STR_PAD_LEFT);
                }
            }
            if ($openResult === '' || $openResult === '--' || mb_strpos($openResult, '待开奖', 0, 'UTF-8') !== false) {
                return null;
            }
            $number = 0;
            if (preg_match('/(?<!\d)(0?[1-9]|[1-4]\d)(?!\d)/u', $openResult, $numberMatch)) {
                $number = (int) $numberMatch[1];
            }
            $zodiac = '';
            foreach (array('鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪') as $zodiacName) {
                if (mb_strpos($openResult, $zodiacName, 0, 'UTF-8') !== false) {
                    $zodiac = $zodiacName;
                    break;
                }
            }
            if ($number > 0) {
                if ($drawDate === '') {
                    $drawDate = $frontResolveDrawDateByIssue($issueText);
                }
                $resolvedZodiac = $frontResolveZodiacByNumber($number, $drawDate);
                if ($resolvedZodiac !== '') {
                    $zodiac = $resolvedZodiac;
                }
            }
            if ($number <= 0 && $zodiac === '') {
                return null;
            }
            $allDrawNumbers = array();
            $regularDrawNumbers = array();
            $regularNumbers = is_array($drawRow) ? json_decode((string) ($drawRow['numbers_json'] ?? ''), true) : null;
            if (is_array($regularNumbers)) {
                foreach ($regularNumbers as $regularNumber) {
                    $regularNumber = (int) $regularNumber;
                    if ($regularNumber >= 1 && $regularNumber <= 49) {
                        $allDrawNumbers[] = $regularNumber;
                        $regularDrawNumbers[] = $regularNumber;
                    }
                }
            }
            if (is_array($drawRow)) {
                $specialNumber = (int) ($drawRow['special_number'] ?? 0);
                if ($specialNumber >= 1 && $specialNumber <= 49) {
                    $allDrawNumbers[] = $specialNumber;
                }
            }
            if (empty($allDrawNumbers) && $number >= 1 && $number <= 49) {
                $allDrawNumbers[] = $number;
            }
            $allDrawNumbers = array_values(array_unique($allDrawNumbers));
            $regularDrawNumbers = array_values(array_unique($regularDrawNumbers));
            $allDrawNumberTexts = array();
            $regularDrawNumberTexts = array();
            $allDrawTails = array();
            $allDrawZodiacs = array();
            $regularDrawZodiacs = array();
            foreach ($regularDrawNumbers as $regularDrawNumber) {
                $regularDrawNumberTexts[] = str_pad((string) ((int) $regularDrawNumber), 2, '0', STR_PAD_LEFT);
                $regularDrawZodiac = $frontResolveZodiacByNumber((int) $regularDrawNumber, $drawDate);
                if ($regularDrawZodiac !== '' && !in_array($regularDrawZodiac, $regularDrawZodiacs, true)) {
                    $regularDrawZodiacs[] = $regularDrawZodiac;
                }
            }
            $allDrawItems = array();
            foreach ($allDrawNumbers as $drawNumber) {
                $drawNumber = (int) $drawNumber;
                $drawNumberText = str_pad((string) $drawNumber, 2, '0', STR_PAD_LEFT);
                $allDrawNumberTexts[] = $drawNumberText;
                $drawTail = (string) ($drawNumber % 10) . '尾';
                if (!in_array($drawTail, $allDrawTails, true)) {
                    $allDrawTails[] = $drawTail;
                }
                $drawZodiac = $frontResolveZodiacByNumber($drawNumber, $drawDate);
                if ($drawZodiac !== '' && !in_array($drawZodiac, $allDrawZodiacs, true)) {
                    $allDrawZodiacs[] = $drawZodiac;
                }
                $drawWave = $frontResolveNumberWave($drawNumber);
                $allDrawItems[] = array(
                    'number' => $drawNumber,
                    'number_text' => $drawNumberText,
                    'zodiac' => $drawZodiac,
                    'tail' => $drawTail,
                    'wave' => (string) ($drawWave['name'] ?? ''),
                    'wave_short' => (string) ($drawWave['short'] ?? ''),
                    'tone_class' => (string) ($drawWave['class'] ?? ''),
                    'element' => $frontResolveNumberElement($drawNumber),
                );
            }
            $wave = '';
            if ($number > 0) {
                $waveInfo = $frontResolveNumberWave($number);
                $wave = (string) ($waveInfo['short'] ?? '');
            }

            return array(
                'number' => $number,
                'number_text' => $number > 0 ? str_pad((string) $number, 2, '0', STR_PAD_LEFT) : '',
                'regular_number_texts' => array_values(array_unique($regularDrawNumberTexts)),
                'all_number_texts' => array_values(array_unique($allDrawNumberTexts)),
                'all_draw_items' => $allDrawItems,
                'zodiac' => $zodiac,
                'all_zodiacs' => $allDrawZodiacs,
                'regular_zodiacs' => $regularDrawZodiacs,
                'all_tails' => $allDrawTails,
                'head' => $number > 0 ? ((string) floor($number / 10) . '头') : '',
                'tail' => $number > 0 ? ((string) ($number % 10) . '尾') : '',
                'wave' => $wave,
                'element' => $number > 0 ? $frontResolveNumberElement($number) : '',
                'odd_even' => $number > 0 ? ($number % 2 === 1 ? '单' : '双') : '',
                'big_small' => $number > 0 ? ($number >= 25 ? '大' : '小') : '',
            );
        };
        $frontEvaluateForecastStatus = static function ($typeText, $predictionText, $openResult, $fallbackStatus, $issueText = '') use ($frontNormalizePredictionText, $frontOpenResultInfo) {
            $typeText = (string) $typeText;
            $predictionText = $frontNormalizePredictionText($predictionText);
            $fallbackStatus = trim((string) $fallbackStatus);
            $openInfo = $frontOpenResultInfo($openResult, $issueText);
            if ($predictionText === '' || $openInfo === null) {
                return $fallbackStatus;
            }

            $hasNumber = false;
            $numberHit = false;
            $allNumberHit = false;
            $regularNumberHit = false;
            $predictionNumbers = array();
            if (preg_match_all('/(?<!\d)(0?[1-9]|[1-4]\d)(?!\d)/u', $predictionText, $numberMatches)) {
                foreach ((array) ($numberMatches[1] ?? array()) as $numberMatch) {
                    $number = (int) $numberMatch;
                    if ($number >= 1 && $number <= 49) {
                        $hasNumber = true;
                        $numberText = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                        $predictionNumbers[] = $numberText;
                        if ($numberText === (string) ($openInfo['number_text'] ?? '')) {
                            $numberHit = true;
                        }
                        if (in_array($numberText, (array) ($openInfo['regular_number_texts'] ?? array()), true)) {
                            $regularNumberHit = true;
                        }
                        if (in_array($numberText, (array) ($openInfo['all_number_texts'] ?? array()), true)) {
                            $allNumberHit = true;
                        }
                    }
                }
            }
            $predictionNumbers = array_values(array_unique($predictionNumbers));
            $regularNumberTexts = array_values(array_unique(array_filter(array_map('strval', (array) ($openInfo['regular_number_texts'] ?? array())))));
            if (empty($regularNumberTexts)) {
                $regularNumberTexts = array_values(array_unique(array_filter(array_map('strval', (array) ($openInfo['all_number_texts'] ?? array())))));
            }
            $frontNormalCodeStructure = static function ($label) {
                $label = trim((string) $label);
                $resolveCnNumber = static function ($text) {
                    $text = trim((string) $text);
                    if ($text === '') {
                        return 0;
                    }
                    if (ctype_digit($text)) {
                        return (int) $text;
                    }
                    $map = array('零' => 0, '一' => 1, '二' => 2, '两' => 2, '三' => 3, '四' => 4, '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9, '十' => 10, '②' => 2, '③' => 3);
                    if (isset($map[$text])) {
                        return (int) $map[$text];
                    }
                    if (mb_strpos($text, '十', 0, 'UTF-8') !== false) {
                        $parts = explode('十', $text, 2);
                        $left = (string) ($parts[0] ?? '');
                        $right = (string) ($parts[1] ?? '');
                        $tens = $left === '' ? 1 : (int) ($map[$left] ?? 0);
                        $ones = $right === '' ? 0 : (int) ($map[$right] ?? 0);

                        return ($tens * 10) + $ones;
                    }

                    return 0;
                };
                if (preg_match('/([零一二两三四五六七八九十\d]+)组([二三23②③])中\2/u', $label, $groupMatches)) {
                    return array('mode' => 'group', 'hit_count' => max(1, min(3, $resolveCnNumber((string) $groupMatches[2]))), 'group_count' => max(1, $resolveCnNumber((string) $groupMatches[1])));
                }
                if (preg_match('/([零一二两三四五六七八九十\d]+)码复(?:式|试)([二三23②③])中\2/u', $label, $comboMatches)) {
                    return array('mode' => 'combo', 'hit_count' => max(1, min(3, $resolveCnNumber((string) $comboMatches[2]))), 'group_count' => 1);
                }

                return array('mode' => '', 'hit_count' => 0, 'group_count' => 0);
            };
            $frontNormalCodeHit = static function ($text, array $numbers, array $regularNumbers, array $structure) {
                if (empty($numbers) || empty($regularNumbers)) {
                    return false;
                }
                $hitCount = max(1, (int) ($structure['hit_count'] ?? 0));
                $extractNumbers = static function ($groupText) {
                    $groupNumbers = array();
                    if (preg_match_all('/(?<!\d)(0?[1-9]|[1-4]\d)(?![\d头尾])/u', (string) $groupText, $groupMatches)) {
                        foreach ((array) ($groupMatches[1] ?? array()) as $groupMatch) {
                            $groupNumber = (int) $groupMatch;
                            if ($groupNumber >= 1 && $groupNumber <= 49) {
                                $groupNumbers[] = str_pad((string) $groupNumber, 2, '0', STR_PAD_LEFT);
                            }
                        }
                    }

                    return $groupNumbers;
                };
                if (($structure['mode'] ?? '') === 'group') {
                    $groups = array();
                    if (preg_match_all('/[【〖《｛〔『{]\s*([^【〖《｛〔『{】〗》｝〕』}]+?)\s*[】〗》｝〕』}]/u', (string) $text, $groupMatches)) {
                        foreach ((array) ($groupMatches[1] ?? array()) as $groupBody) {
                            $groupNumbers = $extractNumbers((string) $groupBody);
                            if ($groupNumbers !== array()) {
                                $groups[] = $groupNumbers;
                            }
                        }
                    }
                    if ($groups === array() && mb_strpos((string) $text, '|', 0, 'UTF-8') !== false) {
                        foreach ((array) preg_split('/\s*\|\s*/u', (string) $text) as $segment) {
                            $groupNumbers = $extractNumbers((string) $segment);
                            if ($groupNumbers !== array()) {
                                $groups[] = $groupNumbers;
                            }
                        }
                    }
                    if ($groups === array()) {
                        $numberSequence = $extractNumbers((string) $text);
                        $groups = array_chunk($numberSequence !== array() ? $numberSequence : $numbers, $hitCount);
                    }
                    foreach ($groups as $groupNumbers) {
                        if (count(array_intersect(array_values(array_unique((array) $groupNumbers)), $regularNumbers)) >= $hitCount) {
                            return true;
                        }
                    }

                    return false;
                }
                if (($structure['mode'] ?? '') === 'combo') {
                    return count(array_intersect(array_values(array_unique($numbers)), $regularNumbers)) >= $hitCount;
                }

                return count(array_intersect($numbers, $regularNumbers)) > 0;
            };
            $normalCodeStructure = $frontNormalCodeStructure($typeText);
            $normalCodeHit = $frontNormalCodeHit($predictionText, $predictionNumbers, $regularNumberTexts, $normalCodeStructure);

            $hasZodiac = false;
            $zodiacHit = false;
            $allZodiacHit = false;
            $regularZodiacHit = false;
            foreach (array('鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪') as $zodiacName) {
                if (mb_strpos($predictionText, $zodiacName, 0, 'UTF-8') !== false) {
                    $hasZodiac = true;
                    if ($zodiacName === (string) ($openInfo['zodiac'] ?? '')) {
                        $zodiacHit = true;
                    }
                    if (in_array($zodiacName, (array) ($openInfo['all_zodiacs'] ?? array()), true)) {
                        $allZodiacHit = true;
                    }
                    if (in_array($zodiacName, (array) ($openInfo['regular_zodiacs'] ?? array()), true)) {
                        $regularZodiacHit = true;
                    }
                }
            }

            $hasHead = (string) ($openInfo['head'] ?? '') !== '' && preg_match('/[0-4]头/u', $predictionText);
            $headHit = $hasHead && mb_strpos($predictionText, (string) $openInfo['head'], 0, 'UTF-8') !== false;
            $hasTail = (string) ($openInfo['tail'] ?? '') !== '' && preg_match('/[0-9]尾/u', $predictionText);
            $tailHit = $hasTail && mb_strpos($predictionText, (string) $openInfo['tail'], 0, 'UTF-8') !== false;
            $allTailHit = false;
            if (preg_match_all('/[0-9]尾/u', $predictionText, $tailMatches)) {
                foreach ((array) ($tailMatches[0] ?? array()) as $tailMatch) {
                    if (in_array((string) $tailMatch, (array) ($openInfo['all_tails'] ?? array()), true)) {
                        $allTailHit = true;
                        break;
                    }
                }
            }
            $hasWave = preg_match('/红波|蓝波|绿波|红|蓝|绿/u', $predictionText);
            $waveHit = $hasWave && (string) ($openInfo['wave'] ?? '') !== '' && mb_strpos($predictionText, (string) $openInfo['wave'], 0, 'UTF-8') !== false;
            $hasElement = (string) ($openInfo['element'] ?? '') !== '' && preg_match('/金|木|水|火|土/u', $predictionText);
            $elementHit = $hasElement && mb_strpos($predictionText, (string) $openInfo['element'], 0, 'UTF-8') !== false;
            $hasOddEven = preg_match('/单|双/u', $predictionText);
            $oddEvenHit = $hasOddEven && (string) ($openInfo['odd_even'] ?? '') !== '' && mb_strpos($predictionText, (string) $openInfo['odd_even'], 0, 'UTF-8') !== false;
            $hasBigSmall = preg_match('/大|小/u', $predictionText);
            $bigSmallHit = $hasBigSmall && (string) ($openInfo['big_small'] ?? '') !== '' && mb_strpos($predictionText, (string) $openInfo['big_small'], 0, 'UTF-8') !== false;
            $typeHasZodiac = mb_strpos($typeText, '肖', 0, 'UTF-8') !== false;
            $typeHasNumber = mb_strpos($typeText, '码', 0, 'UTF-8') !== false;
            $typeHasHead = mb_strpos($typeText, '头', 0, 'UTF-8') !== false;
            $typeHasTail = mb_strpos($typeText, '尾', 0, 'UTF-8') !== false;
            $typeHasWave = mb_strpos($typeText, '波', 0, 'UTF-8') !== false;
            $typeHasElement = mb_strpos($typeText, '行', 0, 'UTF-8') !== false;
            $typeHasOddEven = mb_strpos($typeText, '单', 0, 'UTF-8') !== false || mb_strpos($typeText, '双', 0, 'UTF-8') !== false;
            $typeHasBigSmall = mb_strpos($typeText, '大', 0, 'UTF-8') !== false || mb_strpos($typeText, '小', 0, 'UTF-8') !== false;
            $typeUsesAllDrawZodiac = mb_strpos($typeText, '平特', 0, 'UTF-8') !== false;
            $typeUsesRegularDrawZodiac = preg_match('/复式[一二两三四五六七八九十\d]+连肖/u', $typeText);
            $typeIsNormalCode = mb_strpos($typeText, '平码', 0, 'UTF-8') !== false
                || mb_strpos($typeText, '一码三中三', 0, 'UTF-8') !== false
                || preg_match('/[零一二两三四五六七八九十\d]+组([二三23②③])中\1/u', $typeText)
                || preg_match('/[零一二两三四五六七八九十\d]+码复(?:式|试)([二三23②③])中\1/u', $typeText);
            $typeIsKill = mb_strpos($typeText, '绝杀', 0, 'UTF-8') !== false;
            $typeIsMissNumber = mb_strpos($typeText, '不中', 0, 'UTF-8') !== false;
            $typeIsFlatNumber = mb_strpos($typeText, '平特', 0, 'UTF-8') !== false
                && (mb_strpos($typeText, '码', 0, 'UTF-8') !== false || mb_strpos($typeText, '连', 0, 'UTF-8') !== false);
            $typeIsBigSmallNumber = preg_match('/大小[零一二两三四五六七八九十\d]+码/u', $typeText);
            $typeIsOddEvenNumber = preg_match('/单双[零一二两三四五六七八九十\d]+码/u', $typeText);
            $hit = null;
            if ($typeIsMissNumber) {
                $hit = $hasNumber ? !$numberHit : null;
            } elseif ($typeIsKill) {
                $killTargetHit = false;
                $hasKillTarget = false;
                if ($typeHasZodiac && $hasZodiac) {
                    $killTargetHit = $killTargetHit || $zodiacHit;
                    $hasKillTarget = true;
                }
                if ($typeHasTail && $hasTail) {
                    $killTargetHit = $killTargetHit || $tailHit;
                    $hasKillTarget = true;
                }
                if ($typeHasWave && $hasWave) {
                    $killTargetHit = $killTargetHit || $waveHit;
                    $hasKillTarget = true;
                }
                if ($typeHasElement && $hasElement) {
                    $killTargetHit = $killTargetHit || $elementHit;
                    $hasKillTarget = true;
                }
                if ($typeHasHead && $hasHead) {
                    $killTargetHit = $killTargetHit || $headHit;
                    $hasKillTarget = true;
                }
                if ($hasNumber) {
                    $killTargetHit = $killTargetHit || $numberHit;
                    $hasKillTarget = true;
                }
                $hit = $hasKillTarget ? !$killTargetHit : null;
            } elseif ($typeIsBigSmallNumber) {
                $hit = $hasBigSmall ? $bigSmallHit : null;
            } elseif ($typeIsOddEvenNumber) {
                $hit = $hasOddEven ? $oddEvenHit : null;
            } elseif ($typeIsNormalCode) {
                $hit = $hasNumber ? $normalCodeHit : null;
            } elseif ($typeIsFlatNumber) {
                $hit = $hasNumber ? $allNumberHit : null;
            } elseif ($typeHasHead) {
                $hit = ($hasHead || ($typeHasNumber && $hasNumber)) ? ($headHit || $numberHit) : null;
            } elseif ($typeHasTail) {
                $hit = mb_strpos($typeText, '平特', 0, 'UTF-8') !== false
                    ? ($hasTail ? $allTailHit : null)
                    : (($hasTail || ($typeHasNumber && $hasNumber)) ? ($tailHit || $numberHit) : null);
            } elseif ($typeHasWave) {
                $hit = ($hasWave || $hasNumber) ? ($waveHit || $numberHit) : null;
            } elseif ($typeHasElement) {
                $hit = $hasElement ? $elementHit : null;
            } elseif ($typeHasZodiac && !$typeHasNumber) {
                $hit = $hasZodiac ? ($typeUsesRegularDrawZodiac ? (!empty($openInfo['regular_zodiacs']) ? $regularZodiacHit : null) : ($typeUsesAllDrawZodiac ? $allZodiacHit : $zodiacHit)) : null;
            } elseif ($typeHasNumber && !$typeHasZodiac) {
                $hit = $hasNumber ? (mb_strpos($typeText, '平特', 0, 'UTF-8') !== false ? $allNumberHit : $numberHit) : null;
            } elseif ($typeHasZodiac && $typeHasNumber) {
                $hit = $hasZodiac ? $zodiacHit : null;
            } elseif ($typeHasOddEven || $typeHasBigSmall) {
                $hit = ($hasOddEven || $hasBigSmall) ? ($oddEvenHit || $bigSmallHit) : null;
            }
            if ($hit === null) {
                return $fallbackStatus;
            }

            return $hit ? '准' : '错';
        };
        $frontHitRateStats = app()->admins()->managedForecastRecordStats(
            $region,
            $frontContentText,
            (string) ($post['manage_recent_result_log'] ?? ''),
            true,
            true
        );
        $frontHitRateTotal = (int) ($frontHitRateStats['total'] ?? 0);
        $frontHitRateHit = (int) ($frontHitRateStats['hit'] ?? 0);
        $frontHitRateText = $frontHitRateTotal > 0 ? ((string) $frontHitRateTotal . '中' . (string) $frontHitRateHit) : '';
        $frontHitRateColors = array(
            array('class' => 'red', 'color' => '#dc2626'),
            array('class' => 'blue', 'color' => '#2563eb'),
            array('class' => 'green', 'color' => '#16a34a'),
            array('class' => 'purple', 'color' => '#7c3aed'),
            array('class' => 'orange', 'color' => '#c2410c'),
            array('class' => 'cyan', 'color' => '#0891b2'),
            array('class' => 'rose', 'color' => '#be123c'),
        );
        $frontHitRateDateKey = date('Y-m-d');
        usort($frontHitRateColors, static function ($left, $right) use ($frontHitRateDateKey) {
            return strcmp(
                hash('crc32b', $frontHitRateDateKey . '|' . (string) ($left['color'] ?? '')),
                hash('crc32b', $frontHitRateDateKey . '|' . (string) ($right['color'] ?? ''))
            );
        });
        $frontHitRateTone = (string) ($frontHitRateColors[abs(crc32('post-hit-rate|' . (string) ($post['id'] ?? ''))) % count($frontHitRateColors)]['class'] ?? 'red');
        $frontPostLockState = app()->posts()->postLockState($region);
        $frontPostLockIsLocked = !empty($frontPostLockState['is_locked']);
        $frontPostLockLabel = (string) ($frontPostLockState['label'] ?? ($frontPostLockIsLocked ? '此帖已🔐' : '此帖未锁 🔓'));
        $frontPostLockClass = $frontPostLockIsLocked ? 'is-locked' : 'is-unlocked';
        ?>
        <div class="front-post-main-content">
            <div class="front-post-modal-sticky-head">
                <div class="front-post-modal-inline-meta">
                    <span class="front-post-modal-shelf-time">上架：<span class="front-post-modal-inline-meta-value"><?php echo e(format_datetime($post['created_at'])); ?></span></span>
                    <span class="front-post-modal-lock-state <?php echo e($frontPostLockClass); ?>"><?php echo e($frontPostLockLabel); ?></span>
                    <?php if ($frontHitRateText !== ''): ?><span class="front-post-modal-hit-rate is-tone-<?php echo e($frontHitRateTone); ?>"><?php echo e($frontHitRateText); ?></span><?php endif; ?>
                </div>
                <div class="front-post-content-rule" aria-hidden="true"></div>
            </div>
            <?php
            $frontContentRows = preg_split('/\R/u', $frontContentText);
            $frontForecastRows = array();
            $frontForecastParsedRows = array();
            $frontForecastPlainRows = array();
            $frontForecastRecordCount = 0;
            $frontHasSaleLockedForecastRow = false;
            $frontAuthorPalettes = array(
                array('class' => '1'),
                array('class' => '2'),
                array('class' => '3'),
                array('class' => '4'),
                array('class' => '5'),
            );
            $frontAuthorPalette = $frontAuthorPalettes[abs(crc32((string) ($post['id'] ?? '') . '|' . $frontContentText)) % count($frontAuthorPalettes)];
            $frontAuthorPaletteClass = 'is-author-palette-' . (string) ($frontAuthorPalette['class'] ?? '1');
            $frontFallbackAuthorIconPair = app()->admins()->managedAuthorIconPair(
                (string) ($post['author_name'] ?? '')
            );
            $frontPredictionBracketPairs = array(
                array('【', '】'),
                array('〖', '〗'),
                array('《', '》'),
                array('｛', '｝'),
                array('〔', '〕'),
                array('『', '』'),
            );
            $frontPredictionBracketSeed = abs(crc32('prediction-bracket|' . (string) ($post['id'] ?? '') . '|' . $frontContentText));
            $frontPredictionBracketPair = $frontPredictionBracketPairs[$frontPredictionBracketSeed % count($frontPredictionBracketPairs)];
            $frontFormatPredictionText = static function ($predictionText, array $bracketPair) {
                $predictionText = trim((string) $predictionText);
                if ($predictionText === '') {
                    return '';
                }
                $bracketMap = array(
                    '【' => '】',
                    '〖' => '〗',
                    '《' => '》',
                    '｛' => '｝',
                    '{' => '}',
                    '〔' => '〕',
                    '『' => '』',
                );
                foreach ($bracketMap as $leftBracket => $rightBracket) {
                    if (mb_substr($predictionText, 0, 1, 'UTF-8') === $leftBracket && mb_substr($predictionText, -1, 1, 'UTF-8') === $rightBracket) {
                        $predictionText = mb_substr($predictionText, 1, max(0, mb_strlen($predictionText, 'UTF-8') - 2), 'UTF-8');
                        $bracketPair = array($leftBracket, $rightBracket);
                        break;
                    }
                }
                $predictionGroupPattern = '/([【〖《｛〔『{])\s*([^【〖《｛〔『{】〗》｝〕』}]+?)\s*([】〗》｝〕』}])/u';
                if (preg_match_all($predictionGroupPattern, $predictionText, $predictionGroupMatches, PREG_SET_ORDER)) {
                    $leftoverPredictionText = trim((string) preg_replace($predictionGroupPattern, '', $predictionText));
                    if (count($predictionGroupMatches) > 1 && $leftoverPredictionText === '') {
                        return $predictionText;
                    }
                }
                if (mb_strpos($predictionText, '+', 0, 'UTF-8') === false
                    && !preg_match('/[】〗》｝〕』}]\s*[【〖《｛〔『{]/u', $predictionText)
                    && preg_match('/(?<!\d)(0?[1-9]|[1-4]\d)(?!\d)(?!\s*[头尾])/u', $predictionText, $predictionMatches, PREG_OFFSET_CAPTURE)
                ) {
                    $numberOffset = (int) ($predictionMatches[0][1] ?? -1);
                    $typeText = $numberOffset >= 0 ? trim((string) substr($predictionText, 0, $numberOffset)) : '';
                    $numberText = $numberOffset >= 0 ? trim((string) substr($predictionText, $numberOffset)) : '';
                    if ($typeText !== ''
                        && $numberText !== ''
                        && preg_match('/红波|蓝波|绿波|红|蓝|绿|[0-4]头|[0-9]尾|鼠|牛|虎|兔|龙|蛇|马|羊|猴|鸡|狗|猪/u', $typeText)
                    ) {
                        $predictionText = $typeText . '+' . $numberText;
                    }
                }

                return (string) ($bracketPair[0] ?? '【') . $predictionText . (string) ($bracketPair[1] ?? '】');
            };
            $frontPredictionParamCount = static function ($predictionText) {
                $predictionText = trim((string) $predictionText);
                if ($predictionText === '') {
                    return 0;
                }

                $predictionText = preg_replace('/[【】〖〗《》｛｝{}〔〕『』]/u', ' ', $predictionText);
                $tokens = preg_split('/[\s、,，|\/\+]+/u', trim((string) $predictionText));
                if (!is_array($tokens)) {
                    return 0;
                }

                return count(array_values(array_filter($tokens, static function ($token) {
                    return trim((string) $token) !== '';
                })));
            };
            $frontResolveForecastRecordStatText = static function ($typeText, $predictionText, $openResult, $issueText = '') use ($frontOpenResultInfo) {
                $typeText = preg_replace('/\s+/u', '', app()->admins()->managedNormalizeForecastTypeText((string) $typeText));
                if ($typeText === '') {
                    return '';
                }
                if (in_array($typeText, array('平特一肖', '平特一尾', '平码一码', '一组3中3'), true)) {
                    return '';
                }

                $openInfo = $frontOpenResultInfo((string) $openResult, (string) $issueText);
                if (!is_array($openInfo)) {
                    return '';
                }

                $countTextPattern = '零一二两三四五六七八九十\d①②③④⑤⑥⑦⑧⑨⑩';
                $resolveCountText = static function ($text) {
                    $text = trim((string) $text);
                    if ($text === '') {
                        return 0;
                    }
                    if (ctype_digit($text)) {
                        return (int) $text;
                    }
                    $map = array(
                        '①' => 1, '②' => 2, '③' => 3, '④' => 4, '⑤' => 5,
                        '⑥' => 6, '⑦' => 7, '⑧' => 8, '⑨' => 9, '⑩' => 10,
                        '零' => 0, '一' => 1, '二' => 2, '两' => 2, '三' => 3, '四' => 4,
                        '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9, '十' => 10,
                    );
                    if (isset($map[$text])) {
                        return (int) $map[$text];
                    }
                    if (mb_strpos($text, '十', 0, 'UTF-8') !== false) {
                        $parts = explode('十', $text, 2);
                        $left = trim((string) ($parts[0] ?? ''));
                        $right = trim((string) ($parts[1] ?? ''));
                        $tens = $left === '' ? 1 : (int) ($map[$left] ?? 0);
                        $ones = $right === '' ? 0 : (int) ($map[$right] ?? 0);

                        return ($tens * 10) + $ones;
                    }

                    return 0;
                };
                $predictionText = trim((string) $predictionText);
                $predictionNumberSequence = static function ($text) {
                    $numbers = array();
                    if (preg_match_all('/(?<!\d)(0?[1-9]|[1-4]\d)(?!\d)/u', (string) $text, $matches)) {
                        foreach ((array) ($matches[1] ?? array()) as $match) {
                            $number = (int) $match;
                            if ($number >= 1 && $number <= 49) {
                                $numbers[] = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                            }
                        }
                    }

                    return $numbers;
                };
                $predictionNumbers = static function ($text) use ($predictionNumberSequence) {
                    $numbers = $predictionNumberSequence($text);

                    return array_values(array_unique($numbers));
                };
                $predictionZodiacs = static function ($text) {
                    $zodiacs = array();
                    if (preg_match_all('/[鼠牛虎兔龙蛇马羊猴鸡狗猪]/u', (string) $text, $matches)) {
                        $zodiacs = array_values(array_unique((array) ($matches[0] ?? array())));
                    }

                    return $zodiacs;
                };
                $predictionTails = static function ($text) {
                    $tails = array();
                    if (preg_match_all('/[0-9]尾/u', (string) $text, $matches)) {
                        $tails = array_values(array_unique((array) ($matches[0] ?? array())));
                    }

                    return $tails;
                };
                $countHitZodiacs = static function (array $targets) use ($predictionText, $predictionZodiacs) {
                    $targets = array_values(array_unique(array_filter(array_map('strval', $targets))));

                    return count(array_intersect($predictionZodiacs($predictionText), $targets));
                };
                $countHitNumbers = static function (array $targets) use ($predictionText, $predictionNumbers) {
                    $targets = array_values(array_unique(array_filter(array_map('strval', $targets))));

                    return count(array_intersect($predictionNumbers($predictionText), $targets));
                };
                $countHitTails = static function (array $targets) use ($predictionText, $predictionTails) {
                    $targets = array_values(array_unique(array_filter(array_map('strval', $targets))));

                    return count(array_intersect($predictionTails($predictionText), $targets));
                };
                $countHitGroups = static function ($hitCount, $expectedGroupCount, array $targets) use (
                    $predictionText,
                    $predictionNumberSequence
                ) {
                    $hitCount = max(1, (int) $hitCount);
                    $expectedGroupCount = max(0, (int) $expectedGroupCount);
                    $targets = array_values(array_unique(array_filter(array_map('strval', $targets))));
                    if (empty($targets)) {
                        return 0;
                    }

                    $groups = array();
                    $groupMatches = array();
                    if (preg_match_all('/[【〖《｛〔『{]\s*([^【〖《｛〔『{】〗》｝〕』}]+?)\s*[】〗》｝〕』}]/u', (string) $predictionText, $groupMatches)) {
                        foreach ((array) ($groupMatches[1] ?? array()) as $groupBody) {
                            $numbers = $predictionNumberSequence((string) $groupBody);
                            if (!empty($numbers)) {
                                $groups[] = $numbers;
                            }
                        }
                    }
                    if (empty($groups) && mb_strpos($predictionText, '|', 0, 'UTF-8') !== false) {
                        foreach (preg_split('/\s*\|\s*/u', (string) $predictionText) as $groupBody) {
                            $numbers = $predictionNumberSequence((string) $groupBody);
                            if (!empty($numbers)) {
                                $groups[] = $numbers;
                            }
                        }
                    }
                    $numberSequence = $predictionNumberSequence($predictionText);
                    if (
                        $expectedGroupCount > 1
                        && count($numberSequence) >= $hitCount * $expectedGroupCount
                        && (
                            count($groups) !== $expectedGroupCount
                            || (count($groups) === 1 && count((array) ($groups[0] ?? array())) > $hitCount)
                        )
                    ) {
                        $groups = array_slice(array_chunk($numberSequence, $hitCount), 0, $expectedGroupCount);
                    }
                    if (empty($groups)) {
                        $groups = array_chunk($numberSequence, $hitCount);
                    }

                    $hitGroups = 0;
                    foreach ($groups as $numbers) {
                        if (count(array_intersect(array_values(array_unique((array) $numbers)), $targets)) >= $hitCount) {
                            $hitGroups++;
                        }
                    }

                    return min(2, $hitGroups);
                };

                if (preg_match('/([' . $countTextPattern . ']+)组([' . $countTextPattern . ']+)中\2/u', $typeText, $matches)) {
                    return '中' . (string) $countHitGroups(
                        $resolveCountText((string) ($matches[2] ?? '')),
                        $resolveCountText((string) ($matches[1] ?? '')),
                        (array) ($openInfo['regular_number_texts'] ?? array())
                    ) . '组';
                }

                if (preg_match('/复(?:式|试)([' . $countTextPattern . ']+)连肖/u', $typeText)) {
                    if (count((array) ($openInfo['regular_number_texts'] ?? array())) < 6) {
                        return '';
                    }

                    return '中' . (string) $countHitZodiacs((array) ($openInfo['regular_zodiacs'] ?? array())) . '个';
                }
                if (preg_match('/平特([' . $countTextPattern . ']+)肖/u', $typeText)) {
                    if (count((array) ($openInfo['all_number_texts'] ?? array())) < 7) {
                        return '';
                    }

                    return '中' . (string) $countHitZodiacs((array) ($openInfo['all_zodiacs'] ?? array())) . '个';
                }
                if (preg_match('/平特([' . $countTextPattern . ']+)尾/u', $typeText)) {
                    if (count((array) ($openInfo['all_number_texts'] ?? array())) < 7) {
                        return '';
                    }

                    return '中' . (string) $countHitTails((array) ($openInfo['all_tails'] ?? array())) . '个';
                }
                if (preg_match('/平特([' . $countTextPattern . ']+)连/u', $typeText)) {
                    if (count((array) ($openInfo['all_number_texts'] ?? array())) < 7) {
                        return '';
                    }

                    return '中' . (string) $countHitNumbers((array) ($openInfo['all_number_texts'] ?? array())) . '个';
                }
                if (preg_match('/([' . $countTextPattern . ']+)码复(?:式|试)([' . $countTextPattern . ']+)中\2/u', $typeText)) {
                    return '中' . (string) $countHitNumbers((array) ($openInfo['regular_number_texts'] ?? array())) . '个';
                }

                return '';
            };
            $frontRenderPredictionHtml = static function ($predictionText, $openResult, $statusText, $typeText = '', $issueText = '') use ($frontOpenResultInfo) {
                $predictionText = (string) $predictionText;
                $openResult = trim((string) $openResult);
                $statusText = (string) $statusText;
                $typeText = (string) $typeText;
                $issueText = (string) $issueText;
                $splitPredictionText = static function ($text) {
                    $text = trim((string) $text);
                    $parts = array(
                        'left' => '',
                        'tokens' => array(),
                        'right' => '',
                    );
                    if ($text === '') {
                        return $parts;
                    }
                    $bracketMap = array(
                        '【' => '】',
                        '〖' => '〗',
                        '《' => '》',
                        '｛' => '｝',
                        '{' => '}',
                        '〔' => '〕',
                        '『' => '』',
                    );
                    foreach ($bracketMap as $leftBracket => $rightBracket) {
                        if (mb_substr($text, 0, 1, 'UTF-8') === $leftBracket && mb_substr($text, -1, 1, 'UTF-8') === $rightBracket) {
                            $parts['left'] = $leftBracket;
                            $parts['right'] = $rightBracket;
                            $text = mb_substr($text, 1, max(0, mb_strlen($text, 'UTF-8') - 2), 'UTF-8');
                            break;
                        }
                    }
                    $rawTokens = preg_split('/(\+|[\s、,，|\/]+)/u', trim((string) $text), -1, PREG_SPLIT_DELIM_CAPTURE);
                    $tokens = array();
                    if (is_array($rawTokens)) {
                        foreach ($rawTokens as $rawToken) {
                            $rawToken = trim((string) $rawToken);
                            if ($rawToken === '' || preg_match('/^[、,，|\/]+$/u', $rawToken)) {
                                continue;
                            }
                            if (preg_match('/^\s+$/u', $rawToken)) {
                                continue;
                            }
                            $tokens[] = $rawToken;
                        }
                    }
                    $parts['tokens'] = $tokens;

                    return $parts;
                };
                $tokenMatchesCandidate = static function ($token, $candidate) {
                    $token = trim((string) $token);
                    $candidate = trim((string) $candidate);
                    if ($token === '' || $candidate === '') {
                        return false;
                    }
                    if (preg_match('/^\d+$/', $candidate)) {
                        return preg_match('/^\d+$/', $token) && (int) $token === (int) $candidate;
                    }

                    return mb_strpos($token, $candidate, 0, 'UTF-8') !== false;
                };
                $renderPredictionTokens = static function ($text, array $hitCandidates = array(), $blockClass = 'front-forecast-prediction-text') use ($splitPredictionText, $tokenMatchesCandidate) {
                    $text = trim((string) $text);
                    $hitCandidates = array_values(array_unique(array_filter(array_map(static function ($candidate) {
                        return trim((string) $candidate);
                    }, $hitCandidates))));
                    $blockClass = trim((string) $blockClass);
                    $html = $blockClass !== '' ? '<span class="' . e($blockClass) . '">' : '';
                    $renderTokenList = static function (array $tokens, array $hitCandidates) use ($tokenMatchesCandidate) {
                        $html = '';
                        foreach ($tokens as $token) {
                            $token = (string) $token;
                            if ($token === '+') {
                                $html .= '<span class="front-forecast-prediction-plus">+</span>';
                                continue;
                            }
                            $isHitToken = false;
                            foreach ($hitCandidates as $hitCandidate) {
                                if ($tokenMatchesCandidate($token, (string) $hitCandidate)) {
                                    $isHitToken = true;
                                    break;
                                }
                            }
                            if ($isHitToken) {
                                $html .= '<span class="front-forecast-prediction-token"><span class="front-forecast-prediction-param">' . e($token) . '<span class="front-forecast-prediction-check">✓</span></span></span>';
                            } else {
                                $html .= '<span class="front-forecast-prediction-token">' . e($token) . '</span>';
                            }
                        }

                        return $html;
                    };
                    $isZodiacToken = static function ($token) {
                        $token = preg_replace('/^[【〖《｛〔『]+|[】〗》｝〕』]+$/u', '', trim((string) $token));
                        if ($token === '') {
                            return false;
                        }

                        return preg_match('/^[鼠牛虎兔龙蛇马羊猴鸡狗猪]+$/u', $token) === 1;
                    };
                    $expandZodiacTokens = static function (array $tokens) use ($isZodiacToken) {
                        $expandedTokens = array();
                        foreach ($tokens as $token) {
                            $token = preg_replace('/^[【〖《｛〔『]+|[】〗》｝〕』]+$/u', '', trim((string) $token));
                            if ($token === '') {
                                continue;
                            }
                            if ($isZodiacToken($token) && mb_strlen($token, 'UTF-8') > 1) {
                                for ($index = 0, $length = mb_strlen($token, 'UTF-8'); $index < $length; $index++) {
                                    $expandedTokens[] = mb_substr($token, $index, 1, 'UTF-8');
                                }
                                continue;
                            }
                            $expandedTokens[] = $token;
                        }

                        return $expandedTokens;
                    };
                    $renderZodiacCodeLayout = static function (array $tokens, array $hitCandidates, $leftBracket = '', $rightBracket = '') use ($renderTokenList, $isZodiacToken, $expandZodiacTokens) {
                        $hasPlus = false;
                        $zodiacTokens = array();
                        $codeTokens = array();
                        foreach ($tokens as $token) {
                            $token = trim((string) $token);
                            if ($token === '') {
                                continue;
                            }
                            if ($token === '+') {
                                $hasPlus = true;
                                continue;
                            }
                            if ($isZodiacToken($token)) {
                                foreach ($expandZodiacTokens(array($token)) as $zodiacToken) {
                                    $zodiacTokens[] = $zodiacToken;
                                }
                                continue;
                            }
                            $codeTokens[] = $token;
                        }
                        if (!$hasPlus) {
                            return '';
                        }

                        if (empty($zodiacTokens) || empty($codeTokens)) {
                            return '';
                        }
                        foreach ($zodiacTokens as $zodiacToken) {
                            if (!$isZodiacToken($zodiacToken)) {
                                return '';
                            }
                        }

                        $zodiacCount = max(1, count($zodiacTokens));
                        $isLongLayout = count($codeTokens) >= 12 || count($zodiacTokens) + count($codeTokens) >= 20;
                        $layoutClass = count($codeTokens) >= $zodiacCount ? ' is-balanced' : '';
                        $layoutClass .= $isLongLayout ? ' is-long' : '';
                        $html = '<span class="front-forecast-prediction-zodiac-code' . $layoutClass . '">';
                        if ($isLongLayout) {
                            $html .= '<span class="front-forecast-prediction-zodiac-code-row is-zodiac">';
                            if ((string) $leftBracket !== '') {
                                $html .= '<span class="front-forecast-prediction-bracket">' . e((string) $leftBracket) . '</span>';
                            }
                            $html .= '<span class="front-forecast-prediction-zodiac-list">' . $renderTokenList($zodiacTokens, $hitCandidates) . '</span>';
                            $html .= '</span>';
                            $html .= '<span class="front-forecast-prediction-zodiac-code-row is-code">';
                            $html .= '<span class="front-forecast-prediction-plus">+</span>';
                            $html .= '<span class="front-forecast-prediction-code-list">' . $renderTokenList($codeTokens, $hitCandidates) . '</span>';
                            if ((string) $rightBracket !== '') {
                                $html .= '<span class="front-forecast-prediction-bracket">' . e((string) $rightBracket) . '</span>';
                            }
                            $html .= '</span>';
                        } else {
                            if ((string) $leftBracket !== '') {
                                $html .= '<span class="front-forecast-prediction-bracket">' . e((string) $leftBracket) . '</span>';
                            }
                            $html .= '<span class="front-forecast-prediction-zodiac-list">' . $renderTokenList($zodiacTokens, $hitCandidates) . '</span>';
                            $html .= '<span class="front-forecast-prediction-plus">+</span>';
                            $html .= '<span class="front-forecast-prediction-code-list">' . $renderTokenList($codeTokens, $hitCandidates) . '</span>';
                            if ((string) $rightBracket !== '') {
                                $html .= '<span class="front-forecast-prediction-bracket">' . e((string) $rightBracket) . '</span>';
                            }
                        }
                        $html .= '</span>';

                        return $html;
                    };
                    $parts = $splitPredictionText($text);
                    $zodiacCodeHtml = $renderZodiacCodeLayout((array) ($parts['tokens'] ?? array()), $hitCandidates, (string) ($parts['left'] ?? ''), (string) ($parts['right'] ?? ''));
                    if ($zodiacCodeHtml !== '') {
                        $html .= $zodiacCodeHtml;
                        if ($blockClass !== '') {
                            $html .= '</span>';
                        }

                        return $html !== '' ? $html : e($text);
                    }
                    $groupMatches = array();
                    if (preg_match_all('/([【〖《｛〔『{])\s*([^【〖《｛〔『{】〗》｝〕』}]+?)\s*([】〗》｝〕』}])/u', $text, $groupMatches, PREG_SET_ORDER)) {
                        $leftoverText = trim((string) preg_replace('/([【〖《｛〔『{])\s*([^【〖《｛〔『{】〗》｝〕』}]+?)\s*([】〗》｝〕』}])/u', '', $text));
                        if (count($groupMatches) > 1 && $leftoverText === '') {
                            $html .= '<span class="front-forecast-prediction-groups">';
                            foreach ($groupMatches as $groupMatch) {
                                $groupLeft = (string) ($groupMatch[1] ?? '');
                                $groupBody = (string) ($groupMatch[2] ?? '');
                                $groupRight = (string) ($groupMatch[3] ?? '');
                                $groupParts = $splitPredictionText($groupLeft . $groupBody . $groupRight);
                                $groupTokens = (array) ($groupParts['tokens'] ?? array());
                                $groupClass = 'front-forecast-prediction-group';
                                if (count($groupTokens) >= 5) {
                                    $groupClass .= ' is-count-row';
                                }
                                $html .= '<span class="' . e($groupClass) . '">';
                                $html .= '<span class="front-forecast-prediction-bracket">' . e($groupLeft) . '</span>';
                                $html .= '<span class="front-forecast-prediction-grid">' . $renderTokenList($groupTokens, $hitCandidates) . '</span>';
                                $html .= '<span class="front-forecast-prediction-bracket">' . e($groupRight) . '</span>';
                                $html .= '</span>';
                            }
                            $html .= '</span>';
                            if ($blockClass !== '') {
                                $html .= '</span>';
                            }

                            return $html !== '' ? $html : e($text);
                        }
                    }

                    $parts = $splitPredictionText($text);
                    if ((string) ($parts['left'] ?? '') !== '') {
                        $html .= '<span class="front-forecast-prediction-bracket">' . e((string) $parts['left']) . '</span>';
                    }
                    $html .= '<span class="front-forecast-prediction-grid">';
                    $html .= $renderTokenList((array) ($parts['tokens'] ?? array()), $hitCandidates);
                    $html .= '</span>';
                    if ((string) ($parts['right'] ?? '') !== '') {
                        $html .= '<span class="front-forecast-prediction-bracket">' . e((string) $parts['right']) . '</span>';
                    }
                    if ($blockClass !== '') {
                        $html .= '</span>';
                    }

                    return $html !== '' ? $html : e($text);
                };
                if ($predictionText === '') {
                    return '';
                }
                $compactTypeText = preg_replace('/\s+/u', '', app()->admins()->managedNormalizeForecastTypeText($typeText));
                $typeIsNormalCodeGroupType = preg_match('/[零一二两三四五六七八九十\d①②③④⑤⑥⑦⑧⑨⑩]+组([二三23②③])中\1/u', (string) $compactTypeText);
                $typeIsNormalCodeComboType = preg_match('/[零一二两三四五六七八九十\d①②③④⑤⑥⑦⑧⑨⑩]+码复(?:式|试)([二三23②③])中\1/u', (string) $compactTypeText);
                $typeAllowsPartialHitDisplay = $typeIsNormalCodeGroupType || $typeIsNormalCodeComboType;
                if ($statusText === '错' && !$typeAllowsPartialHitDisplay) {
                    return $renderPredictionTokens($predictionText, array(), 'front-forecast-prediction-miss-text');
                }
                if (
                    !in_array($statusText, array('准', '中', '赢', '發', '发'), true)
                    && !($statusText === '错' && $typeAllowsPartialHitDisplay)
                ) {
                    return $renderPredictionTokens($predictionText);
                }

                $candidates = array();
                $preferredCandidates = array();
                $addCandidate = static function ($value) use (&$candidates) {
                    $value = trim((string) $value);
                    if ($value !== '' && !in_array($value, $candidates, true)) {
                        $candidates[] = $value;
                    }
                };
                $addPreferredCandidate = static function ($value) use (&$preferredCandidates) {
                    $value = trim((string) $value);
                    if ($value !== '' && !in_array($value, $preferredCandidates, true)) {
                        $preferredCandidates[] = $value;
                    }
                };
                $addNormalCodeGroupHitCandidates = static function ($predictionText, $typeText, array $regularNumbers) use ($addPreferredCandidate) {
                    $typeText = trim((string) $typeText);
                    $compactTypeText = preg_replace('/\s+/u', '', app()->admins()->managedNormalizeForecastTypeText($typeText));
                    $regularNumbers = array_values(array_unique(array_filter(array_map('strval', $regularNumbers))));
                    if ($typeText === '' || empty($regularNumbers)) {
                        return;
                    }
                    $resolveCountText = static function ($text) {
                        $text = trim((string) $text);
                        if ($text === '') {
                            return 0;
                        }
                        if (ctype_digit($text)) {
                            return (int) $text;
                        }
                        $map = array(
                            '①' => 1, '②' => 2, '③' => 3, '④' => 4, '⑤' => 5,
                            '⑥' => 6, '⑦' => 7, '⑧' => 8, '⑨' => 9, '⑩' => 10,
                            '零' => 0, '一' => 1, '二' => 2, '两' => 2, '三' => 3, '四' => 4,
                            '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9, '十' => 10,
                        );
                        if (isset($map[$text])) {
                            return (int) $map[$text];
                        }
                        if (mb_strpos($text, '十', 0, 'UTF-8') !== false) {
                            $parts = explode('十', $text, 2);
                            $left = trim((string) ($parts[0] ?? ''));
                            $right = trim((string) ($parts[1] ?? ''));
                            $tens = $left === '' ? 1 : (int) ($map[$left] ?? 0);
                            $ones = $right === '' ? 0 : (int) ($map[$right] ?? 0);

                            return ($tens * 10) + $ones;
                        }

                        return 0;
                    };
                    $isComboType = false;
                    if (preg_match('/([零一二两三四五六七八九十\d①②③④⑤⑥⑦⑧⑨⑩]+)组([二三23②③])中\2/u', (string) $compactTypeText, $hitMatches)) {
                        $expectedGroupCount = max(1, $resolveCountText((string) ($hitMatches[1] ?? '')));
                        $hitCount = max(1, min(3, $resolveCountText((string) ($hitMatches[2] ?? ''))));
                    } elseif (preg_match('/([零一二两三四五六七八九十\d①②③④⑤⑥⑦⑧⑨⑩]+)码复(?:式|试)([二三23②③])中\2/u', (string) $compactTypeText, $hitMatches)) {
                        $isComboType = true;
                        $expectedGroupCount = 0;
                        $hitCount = max(1, min(3, $resolveCountText((string) ($hitMatches[2] ?? ''))));
                    } else {
                        return;
                    }
                    if ($hitCount <= 0) {
                        return;
                    }
                    $predictionNumbers = array();
                    if (preg_match_all('/(?<!\d)(0?[1-9]|[1-4]\d)(?!\d)/u', (string) $predictionText, $predictionNumberMatches)) {
                        foreach ((array) ($predictionNumberMatches[1] ?? array()) as $numberMatch) {
                            $number = (int) $numberMatch;
                            if ($number >= 1 && $number <= 49) {
                                $predictionNumbers[] = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                            }
                        }
                    }
                    $groups = array();
                    if (!preg_match_all('/[【〖《｛〔『{]\s*([^【〖《｛〔『{】〗》｝〕』}]+?)\s*[】〗》｝〕』}]/u', (string) $predictionText, $groupMatches)) {
                        $groupMatches = array();
                    }
                    foreach ((array) ($groupMatches[1] ?? array()) as $groupBody) {
                        $groupNumbers = array();
                        if (preg_match_all('/(?<!\d)(0?[1-9]|[1-4]\d)(?!\d)/u', (string) $groupBody, $numberMatches)) {
                            foreach ((array) ($numberMatches[1] ?? array()) as $numberMatch) {
                                $number = (int) $numberMatch;
                                if ($number >= 1 && $number <= 49) {
                                    $groupNumbers[] = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
                                }
                            }
                        }
                        if ($groupNumbers !== array()) {
                            $groups[] = $groupNumbers;
                        }
                    }
                    if ($isComboType && count($predictionNumbers) > $hitCount) {
                        $groups = array_chunk($predictionNumbers, $hitCount);
                    }
                    if (
                        !$isComboType
                        &&
                        $expectedGroupCount > 1
                        && count($predictionNumbers) >= $hitCount * $expectedGroupCount
                        && (
                            count($groups) !== $expectedGroupCount
                            || (count($groups) === 1 && count((array) ($groups[0] ?? array())) > $hitCount)
                        )
                    ) {
                        $groups = array_slice(array_chunk($predictionNumbers, $hitCount), 0, $expectedGroupCount);
                    }
                    if ($groups === array()) {
                        $groups = array_chunk($predictionNumbers, $hitCount);
                    }
                    foreach ($groups as $groupNumbers) {
                        $hitNumbers = array_values(array_intersect(array_values(array_unique((array) $groupNumbers)), $regularNumbers));
                        if (count($hitNumbers) > 0) {
                            foreach ($hitNumbers as $groupNumber) {
                                $addPreferredCandidate($groupNumber);
                            }
                        }
                    }
                };
                $typeUsesRegularDrawZodiac = preg_match('/复式[一二两三四五六七八九十\d]+连肖/u', (string) $compactTypeText);
                $typeUsesRegularNumbers = mb_strpos((string) $compactTypeText, '平码', 0, 'UTF-8') !== false
                    || mb_strpos((string) $compactTypeText, '一码三中三', 0, 'UTF-8') !== false
                    || $typeIsNormalCodeGroupType
                    || $typeIsNormalCodeComboType;
                $typeUsesAllDrawNumbers = mb_strpos((string) $compactTypeText, '平特', 0, 'UTF-8') !== false
                    && (mb_strpos((string) $compactTypeText, '码', 0, 'UTF-8') !== false || mb_strpos((string) $compactTypeText, '连', 0, 'UTF-8') !== false);
                $fullOpenInfo = $frontOpenResultInfo($openResult, $issueText);
                $fullOpenInfo = is_array($fullOpenInfo) ? $fullOpenInfo : array();
                $openNumberMatches = array();
                if (preg_match_all('/(?<!\d)(0?[1-9]|[1-4]\d)(?!\d)/u', $openResult, $numberMatches)) {
                    $openNumberMatches = (array) ($numberMatches[1] ?? array());
                }
                if (empty($openNumberMatches) && trim((string) ($fullOpenInfo['number_text'] ?? '')) !== '') {
                    $openNumberMatches[] = (string) $fullOpenInfo['number_text'];
                }
                if (!empty($openNumberMatches)) {
                    foreach ($openNumberMatches as $numberMatch) {
                        $number = (int) $numberMatch;
                        if ($number >= 1 && $number <= 49) {
                            $addCandidate(str_pad((string) $number, 2, '0', STR_PAD_LEFT));
                            $addCandidate((string) $number);
                            $headCandidate = (string) floor($number / 10) . '头';
                            $tailCandidate = (string) ($number % 10) . '尾';
                            if (mb_strpos($typeText, '头', 0, 'UTF-8') !== false) {
                                $addPreferredCandidate($headCandidate);
                            }
                            if (mb_strpos($typeText, '尾', 0, 'UTF-8') !== false) {
                                $addPreferredCandidate($tailCandidate);
                            }
                            $addCandidate($headCandidate);
                            $addCandidate($tailCandidate);
                            $addCandidate($number % 2 === 1 ? '单' : '双');
                            $addCandidate($number >= 25 ? '大' : '小');
                            if (in_array($number, array(1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46), true)) {
                                if (mb_strpos($typeText, '波', 0, 'UTF-8') !== false) {
                                    $addPreferredCandidate('红波');
                                    $addPreferredCandidate('红');
                                }
                                $addCandidate('红波');
                                $addCandidate('红');
                            } elseif (in_array($number, array(3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48), true)) {
                                if (mb_strpos($typeText, '波', 0, 'UTF-8') !== false) {
                                    $addPreferredCandidate('蓝波');
                                    $addPreferredCandidate('蓝');
                                }
                                $addCandidate('蓝波');
                                $addCandidate('蓝');
                            } else {
                                if (mb_strpos($typeText, '波', 0, 'UTF-8') !== false) {
                                    $addPreferredCandidate('绿波');
                                    $addPreferredCandidate('绿');
                                }
                                $addCandidate('绿波');
                                $addCandidate('绿');
                            }
                            $elementGroups = array(
                                '金' => array(2, 3, 10, 11, 24, 25, 32, 33, 40, 41),
                                '木' => array(6, 7, 14, 15, 22, 23, 36, 37, 44, 45),
                                '水' => array(12, 13, 20, 21, 28, 29, 42, 43),
                                '火' => array(1, 8, 9, 16, 17, 30, 31, 38, 39, 46, 47),
                                '土' => array(4, 5, 18, 19, 26, 27, 34, 35, 48, 49),
                            );
                            foreach ($elementGroups as $elementName => $elementNumbers) {
                                if (in_array($number, $elementNumbers, true)) {
                                    $addCandidate($elementName);
                                    break;
                                }
                            }
                        }
                    }
                }
                foreach (array('鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪') as $zodiacName) {
                    if (
                        mb_strpos($openResult, $zodiacName, 0, 'UTF-8') !== false
                        || $zodiacName === (string) ($fullOpenInfo['zodiac'] ?? '')
                    ) {
                        if (!$typeUsesRegularDrawZodiac && mb_strpos($typeText, '肖', 0, 'UTF-8') !== false) {
                            $addPreferredCandidate($zodiacName);
                        }
                        if (!$typeUsesRegularDrawZodiac) {
                            $addCandidate($zodiacName);
                        }
                    }
                }
                if ($typeUsesRegularDrawZodiac) {
                    foreach ((array) ($fullOpenInfo['regular_zodiacs'] ?? array()) as $zodiacName) {
                        $addPreferredCandidate($zodiacName);
                        $addCandidate($zodiacName);
                    }
                } elseif (mb_strpos($typeText, '平特', 0, 'UTF-8') !== false && mb_strpos($typeText, '肖', 0, 'UTF-8') !== false) {
                    foreach ((array) ($fullOpenInfo['all_zodiacs'] ?? array()) as $zodiacName) {
                        $addPreferredCandidate($zodiacName);
                        $addCandidate($zodiacName);
                    }
                }
                if ($typeUsesRegularNumbers) {
                    if ($typeIsNormalCodeGroupType || $typeIsNormalCodeComboType) {
                        $addNormalCodeGroupHitCandidates($predictionText, $typeText, (array) ($fullOpenInfo['regular_number_texts'] ?? array()));
                    } else {
                        foreach ((array) ($fullOpenInfo['regular_number_texts'] ?? array()) as $numberText) {
                            $addPreferredCandidate($numberText);
                            $addCandidate($numberText);
                        }
                    }
                } elseif ($typeUsesAllDrawNumbers) {
                    foreach ((array) ($fullOpenInfo['all_number_texts'] ?? array()) as $numberText) {
                        $addPreferredCandidate($numberText);
                        $addCandidate($numberText);
                    }
                }
                if (mb_strpos($typeText, '平特', 0, 'UTF-8') !== false && mb_strpos($typeText, '尾', 0, 'UTF-8') !== false) {
                    foreach ((array) ($fullOpenInfo['all_tails'] ?? array()) as $tailName) {
                        $addPreferredCandidate($tailName);
                        $addCandidate($tailName);
                    }
                }

                usort($candidates, static function ($left, $right) {
                    return mb_strlen((string) $right, 'UTF-8') <=> mb_strlen((string) $left, 'UTF-8');
                });
                $candidates = $typeAllowsPartialHitDisplay
                    ? array_values(array_unique($preferredCandidates))
                    : array_values(array_unique(array_merge($preferredCandidates, $candidates)));
                $matchedCandidates = array();
                foreach ($candidates as $candidate) {
                    foreach ((array) ($splitPredictionText($predictionText)['tokens'] ?? array()) as $predictionToken) {
                        if ($tokenMatchesCandidate((string) $predictionToken, (string) $candidate)) {
                            $matchedCandidates[] = (string) $candidate;
                            break;
                        }
                    }
                }
                if (!empty($matchedCandidates)) {
                    return $renderPredictionTokens($predictionText, $matchedCandidates);
                }

                return $renderPredictionTokens($predictionText);
            };
            $frontParseForecastRecordRow = static function ($row) {
                $row = trim((string) $row);
                if ($row === '' || !preg_match('/^\d{1,6}[^:：]{0,12}[:：]/u', $row)) {
                    return null;
                }

                $parts = preg_split('/\s{2,}/u', $row, 4);
                if (!is_array($parts) || count($parts) !== 4) {
                    $rowPattern = '/^(\d{1,6}[^:：]{0,12}[:：])\s*(\S+)\s+(\S+)\s+(.+?)\s+(开[:：]\s*.*?)\s*$/u';
                    if (!preg_match($rowPattern, $row, $rowMatches)) {
                        return null;
                    }
                    $parts = array(
                        (string) $rowMatches[1] . (string) $rowMatches[2],
                        (string) $rowMatches[3],
                        (string) $rowMatches[4],
                        (string) $rowMatches[5],
                    );
                }

                if (!preg_match('/^(\d{1,6}[^:：]{0,12}[:：])\s*(.+)$/u', (string) $parts[0], $prefixMatches)) {
                    return null;
                }

                $openLabel = '开：';
                $openResult = trim((string) $parts[3]);
                $statusText = '';
                if (preg_match('/^(开[:：])\s*(.*?)\s*(准|中|赢|發|发|错)?\s*$/u', $openResult, $openMatches)) {
                    $openLabel = (string) $openMatches[1];
                    $openResult = trim((string) ($openMatches[2] ?? ''));
                    $statusText = trim((string) ($openMatches[3] ?? ''));
                }

                return array(
                    'issue' => (string) $prefixMatches[1],
                    'author_token' => trim((string) $prefixMatches[2]),
                    'type' => trim((string) $parts[1]),
                    'prediction' => trim((string) $parts[2]),
                    'open_label' => $openLabel,
                    'open_result' => $openResult,
                    'status' => $statusText,
                );
            };
            if (is_array($frontContentRows)) {
                foreach ($frontContentRows as $frontContentRow) {
                    $frontContentRow = trim((string) $frontContentRow);
                    if ($frontContentRow === '') {
                        continue;
                    }
                    $frontForecastParsedRow = null;
                    $isFrontForecastRecordRow = preg_match('/^\d{1,6}[^:：]{0,6}[:：]/u', $frontContentRow) && (substr_count($frontContentRow, '/') >= 4 || mb_strpos($frontContentRow, '开') !== false);
                    if ($isFrontForecastRecordRow) {
                        $frontRecordRow = $frontParseForecastRecordRow($frontContentRow);
                        if (is_array($frontRecordRow)) {
                            $frontAuthorToken = trim((string) $frontRecordRow['author_token']);
                            $frontAuthorText = $frontAuthorToken;
                            $frontExpectedAuthorText = trim((string) ($post['author_name'] ?? ''));
                            if ($frontExpectedAuthorText !== '' && mb_strpos($frontAuthorToken, $frontExpectedAuthorText, 0, 'UTF-8') !== false) {
                                $frontAuthorText = $frontExpectedAuthorText;
                            }
                            $frontOpenLabel = (string) $frontRecordRow['open_label'];
                            $frontOpenResult = (string) $frontRecordRow['open_result'];
                            $frontStatusText = (string) $frontRecordRow['status'];
                            $frontStatusText = $frontEvaluateForecastStatus(
                                (string) $frontRecordRow['type'],
                                (string) $frontRecordRow['prediction'],
                                $frontOpenResult,
                                $frontStatusText,
                                (string) $frontRecordRow['issue']
                            );
                            $frontOpenInfo = $frontOpenResultInfo($frontOpenResult, (string) $frontRecordRow['issue']);
                            $frontDrawItems = is_array($frontOpenInfo) ? (array) ($frontOpenInfo['all_draw_items'] ?? array()) : array();
                            $frontIssueDigits = preg_replace('/\D+/', '', (string) $frontRecordRow['issue']);
                            $frontIssueTail = $frontIssueDigits !== ''
                                ? str_pad(substr($frontIssueDigits, -3), 3, '0', STR_PAD_LEFT)
                                : '';
                            $frontIsCurrentIssue = $frontSaleCurrentIssueTail !== ''
                                && $frontIssueTail === $frontSaleCurrentIssueTail;
                            $frontIsSaleLockedIssue = $frontSaleContentLocked
                                && (
                                    $frontIsCurrentIssue
                                    || ($frontSaleCurrentIssueTail === '' && empty($frontDrawItems))
                                );
                            if ($frontIsSaleLockedIssue) {
                                $frontForecastRows[] = $frontContentRow;
                                $frontForecastRecordCount++;
                                $frontHasSaleLockedForecastRow = true;
                                $frontForecastParsedRows[] = array(
                                    'issue' => str_replace('：', ':', (string) $frontRecordRow['issue']),
                                    'author' => $frontAuthorText,
                                    'type' => trim((string) $frontRecordRow['type']),
                                    'display_type' => $frontForecastDisplayTypeText !== ''
                                        ? $frontForecastDisplayTypeText
                                        : trim((string) $frontRecordRow['type']),
                                    'prediction' => '',
                                    'prediction_param_count' => 0,
                                    'open_label' => $frontOpenLabel,
                                    'open_result' => $frontOpenResult,
                                    'show_draw_record' => !empty($frontDrawItems),
                                    'draw_items' => $frontDrawItems,
                                    'status' => '',
                                    'is_sale_locked' => true,
                                    'is_current_issue' => $frontIsCurrentIssue,
                                );
                                continue;
                            }
                            $frontDisplayPredictionText = app()->admins()->managedNormalizeForecastPredictionCount(
                                (string) $frontRecordRow['type'],
                                (string) $frontRecordRow['prediction'],
                                (string) $frontRecordRow['issue'],
                                (string) $region,
                                $frontStatusText
                            );
                            $frontFormattedPrediction = $frontFormatPredictionText($frontDisplayPredictionText, $frontPredictionBracketPair);
                            $frontPredictionParamTotal = $frontPredictionParamCount($frontFormattedPrediction);
                            $frontForecastRows[] = $frontContentRow;
                            $frontForecastRecordCount++;
                            $frontShowPendingDraw = $frontIsCurrentIssue
                                && empty($frontDrawItems);
                            $frontIsWaitingMaterial = mb_strpos((string) $frontRecordRow['prediction'], '资料等待更新中', 0, 'UTF-8') !== false;
                            $frontForecastParsedRow = array(
                                'issue' => str_replace('：', ':', (string) $frontRecordRow['issue']),
                                'author' => $frontAuthorText,
                                'type' => trim((string) $frontRecordRow['type']),
                                'display_type' => $frontForecastDisplayTypeText !== ''
                                    ? $frontForecastDisplayTypeText
                                    : trim((string) $frontRecordRow['type']),
                                'prediction' => $frontFormattedPrediction,
                                'prediction_param_count' => $frontPredictionParamTotal,
                                'open_label' => $frontOpenLabel,
                                'open_result' => $frontOpenResult,
                                'show_draw_record' => !empty($frontDrawItems),
                                'draw_items' => $frontDrawItems,
                                'show_pending_draw' => $frontShowPendingDraw,
                                'is_waiting_material' => $frontIsWaitingMaterial,
                                'status' => $frontStatusText,
                                'is_current_issue' => $frontIsCurrentIssue,
                            );
                            $frontForecastParsedRows[] = $frontForecastParsedRow;
                            continue;
                        }
                    }
                    if ($frontSaleContentLocked) {
                        continue;
                    }
                    $frontForecastPlainRows[] = $frontContentRow;
                }
            }
            $isFrontForecastRecordContent = count($frontForecastRows) > 0 && $frontForecastRecordCount === count($frontForecastRows);
            $frontForecastPlainText = trim(implode("\n", $frontForecastPlainRows));
            $frontForecastPlainContentClass = $frontPlainContentClass;
            if ($frontForecastPlainText === $frontNoMaterialWaitingContent && mb_strpos($frontForecastPlainContentClass, 'text-center', 0, 'UTF-8') === false) {
                $frontForecastPlainContentClass .= ' text-center';
            }
            $frontForecastWaitingInCurrentRow = false;
            foreach ($frontForecastParsedRows as $frontForecastParsedRowItem) {
                if (
                    is_array($frontForecastParsedRowItem)
                    && !empty($frontForecastParsedRowItem['show_pending_draw'])
                    && (!empty($frontForecastParsedRowItem['is_waiting_material']) || $frontForecastPlainText === $frontNoMaterialWaitingContent)
                ) {
                    $frontForecastWaitingInCurrentRow = true;
                    break;
                }
            }
            $frontHitStatusTexts = array('准', '中', '赢', '發');
            $frontForecastHitBlessingLuckParts = array(
                '喜气入门',
                '鸿运升温',
                '财星照临',
                '好彩开场',
                '红运正旺',
                '福气到位',
                '运气开始抬头',
                '红火劲头上来了',
                '财路慢慢打开',
                '好兆头已经露面',
                '喜事正在靠近',
                '顺风顺水有苗头',
                '好运已经冒头',
                '红气开始往上走',
                '财气有点热起来',
                '顺劲已经来了',
                '喜头已经打开',
                '今天这个势头不差',
                '好事开始有影了',
                '这波气场挺顺',
                '手气慢慢上来了',
                '红运开始贴近',
            );
            $frontForecastHitBlessingHarvestParts = array(
                '中奖好势头已起',
                '财喜一路相随',
                '好彩头稳稳落袋',
                '收获喜讯继续来',
                '顺势见喜更添彩',
                '喜报连连有盼头',
                '这波准得挺漂亮',
                '手气在线不含糊',
                '看着就顺眼',
                '稳稳添了一份喜气',
                '这一下很提气',
                '好结果来得够实在',
                '这一把很有看头',
                '结果出来挺给力',
                '这波算是打中了节奏',
                '中爆的感觉已经有了',
                '漂亮结果先到手',
                '看准方向就有收获',
                '这一下给人信心',
                '好消息来得刚刚好',
                '这份喜气很实在',
                '顺着这个劲头就对了',
            );
            $frontForecastHitBlessingWishParts = array(
                '愿好运继续延伸',
                '愿财气越走越旺',
                '愿下一场再添惊喜',
                '愿红火势头不断',
                '愿福运一路相伴',
                '愿后面继续见喜',
                '愿接下来越走越顺',
                '愿好彩头继续跟上',
                '愿喜气一场接一场',
                '愿财运再往上冲',
                '愿这份好运别停',
                '愿后面还有大惊喜',
                '愿后面越跟越顺',
                '愿好势头继续放大',
                '愿这波红气接着来',
                '愿接下来还有好结果',
                '愿好运一波接一波',
                '愿下一次继续漂亮',
                '愿财喜不要断档',
                '愿这份顺劲再延长',
                '愿越看越准越顺',
                '愿后面继续有收成',
            );
            $frontForecastHitBlessingPlainParts = array(
                '这把有点意思',
                '今天状态挺稳',
                '稳了就继续冲',
                '越看越有盼头',
                '红起来就有劲',
                '这一场够提神',
                '顺手又顺心',
                '好彩来了挡不住',
                '这波不赖',
                '气势已经起来',
                '继续保持这个手感',
                '漂亮结果先收下',
                '机会到了别观望',
                '别等太久要动起来',
                '看准了就行动',
                '该出手时就出手',
                '不要一直等机会溜走',
                '趁着手气好抓一把',
                '有苗头就别犹豫',
                '好运来了要跟上',
                '别只看热闹赶紧动',
                '顺势而上更有劲',
                '时机到了就要上',
                '把握住这波好气势',
                '别想太多直接上',
                '有机会就别空看',
                '看见苗头就抓紧',
                '这波可以跟紧一点',
                '别错过眼前好势头',
                '不要光看不动',
                '等来等去容易错过',
                '该动就动别拖',
                '别犹豫太久',
                '行动起来才有结果',
                '看准就抓住',
                '有感觉就别放空',
                '别站旁边干等',
                '机会来了就要接住',
            );
            $frontForecastHitBlessingDirectParts = array(
                '别观望了',
                '别再等了',
                '直接行动',
                '赶紧把握',
                '现在就动起来',
                '机会别放走',
                '看准别手软',
                '别拖到没机会',
                '该冲就冲',
                '别等别人先动',
                '抓紧这个点',
                '别让好运空跑',
                '要上就趁现在',
                '别光想要去做',
                '好势头别浪费',
                '别把机会看没了',
            );
            $frontForecastMissBlessingCareParts = array(
                '这次未中先稳住',
                '本次差一点别灰心',
                '暂时落空也有机会',
                '结果不理想也继续向前',
                '这一场先当蓄势',
                '短暂停顿不算输',
                '这把没中也别急',
                '先别泄气',
                '差一点也有方向',
                '今天先把心态稳住',
                '暂时慢一步没关系',
                '这一回先记经验',
                '没中也正常别上头',
                '这把先别急',
                '差一点不代表没路',
                '先缓一缓再看',
                '心态别乱',
                '这次先当试手感',
                '别因为一把乱节奏',
                '结果没来先稳住',
                '不顺的时候更要稳',
                '先把方向看清楚',
            );
            $frontForecastMissBlessingPushParts = array(
                '调整节奏继续加油',
                '把经验留住再冲一次',
                '保持信心继续努力',
                '沉住气再接一场',
                '鼓励自己重新出发',
                '稳住心态继续追',
                '下一把再认真来',
                '别慌慢慢找感觉',
                '继续努力不掉队',
                '重新整理再出发',
                '把劲头留到下一场',
                '稳一点再往前冲',
                '别泄气继续来',
                '再来一把找回感觉',
                '慢慢调整别乱冲',
                '把状态拉回来',
                '继续努力别停下',
                '下一场再认真看',
                '稳住手感继续走',
                '先找节奏再发力',
                '别急着否定自己',
                '重新来过也不迟',
            );
            $frontForecastMissBlessingWishParts = array(
                '下一次争取翻红',
                '后面还有机会见喜',
                '好运还在路上',
                '下回继续冲刺',
                '下一轮再把握机会',
                '后面争取迎来好结果',
                '下一场争取拿回来',
                '好结果还可以再等一等',
                '后面机会还不少',
                '下一把争取漂亮翻身',
                '好运迟早会跟上',
                '后面再看更有希望',
                '下一场再把机会拿回来',
                '后面还有翻身空间',
                '下一回争取更准一点',
                '机会还在后面等着',
                '下次争取一把到位',
                '后面继续找好结果',
                '下一把别错过节奏',
                '好运慢慢会靠近',
                '后面再争一口气',
                '下一次争取漂亮收回来',
            );
            $frontForecastMissBlessingPlainParts = array(
                '没事先记一笔',
                '这把当练手',
                '先把节奏找回来',
                '路还长不用急',
                '继续来就有机会',
                '心态稳住才好冲',
                '下一把再看看',
                '先稳住阵脚',
                '别急慢慢来',
                '这场先过',
                '后面再发力',
                '鼓劲继续走',
                '别慌',
                '稳住',
                '没中也别停',
                '先别泄气',
                '再看一把',
                '继续冲',
                '别乱了阵脚',
                '今天先稳一点',
                '慢慢来不急',
                '下一把再说话',
                '把心态放平',
                '别急着放弃',
                '先把感觉找回来',
                '继续盯住机会',
            );
            $frontForecastMissBlessingDirectParts = array(
                '别灰心',
                '别上头',
                '先稳住',
                '继续来',
                '再冲一次',
                '别急着退',
                '该调整就调整',
                '别乱跟节奏',
                '先看准再动',
                '这把先放下',
                '下把再争',
                '把劲留住',
                '别让一次结果影响心态',
                '继续加把劲',
                '慢一点也没事',
                '稳了再出手',
            );
            $frontForecastHitBlessingLines = array(
                '开奖结果一出来，心里那股开心真的压不住，这期总算给了一个漂亮回应。',
                '这一期中得很提气，看到结果那一刻，确实有点激动。',
                '这期能对上，心里一下松了，这份喜悦很实在。',
                '开出来的那一下很有感觉，前面等的时间都值了。',
                '这次准得不是冷冰冰的记录，看到结果时是真的开心。',
                '本期结果一落地，心里马上亮了一下，这种对上的感觉很舒服。',
                '这一把中得及时，既开心也感动，整个人都精神了。',
                '开奖后看到结果贴住重点，真的忍不住想多说一句，漂亮。',
                '这期准得很提神，开完那一刻的激动劲还在。',
                '本期对上以后，信心一下被点起来了，这种感觉很难装出来。',
                '这一期算是给大家争了一口气，结果出来那一刻很开心。',
                '开出来能中，心里确实热了一下，前面的判断没有白费。',
                '这次结果一对上，真的有点坐不住，想马上再看下一条。',
                '本期中爆带来的不是一句准就完了，是那种等到回响的开心。',
                '看到开奖记录对上时，心里挺感动，至少这期没有辜负大家。',
                '这把开得让人精神一振，中爆的喜悦确实藏不住。',
                '本期结果很给面子，看完真的忍不住笑了一下。',
                '这次对上得很干脆，开心是真的，激动也是真的。',
                '开完回来再看这期，心里有种终于等到的踏实和兴奋。',
                '这一期准得让人很想马上往后看，手都有点按不住。',
                '这期开奖结果一出，心情直接拉满，真有点上头。',
                '开出来对上的那一秒，真的比什么都痛快。',
                '这次中爆看得人心里发热，开心得很直接。',
                '刚看到结果的时候，第一反应就是这期真争气。',
                '这把准得漂亮，心里那口气一下顺了。',
                '开完以后真想喊一句漂亮，这期确实让人开心。',
            );
            $frontForecastMissBlessingLines = array(
                '开出来没对上，这期确实有点扫兴。',
                '结果一出就知道差了，这把不硬撑。',
                '这期没贴住，心里多少有点失落。',
                '开奖一看不搭，这次就不说漂亮话了。',
                '这把没中，确实不够舒服。',
                '结果摆出来了，没对上就是没对上。',
                '这期差了一口气，看完结果有点闷。',
                '开完以后没惊喜，这次只能认了。',
                '本来还期待一下，结果出来确实没对上。',
                '这次开奖不配合，心情一下冷了。',
                '这把没开到点上，说多了也没意思。',
                '本期结果不搭，确实有点可惜。',
                '开出来那一刻就知道没戏，这期先算了。',
                '这次没有中，心里有点空。',
                '结果出来以后就安静了，这把不理想。',
                '这期看完有点失望，没必要硬夸。',
            );
            $frontHitStatusSeed = abs(crc32('front-hit-status|' . (string) ($post['id'] ?? '') . '|' . (string) ($post['title'] ?? '')));
            $frontUnifiedHitStatusText = $frontHitStatusTexts[$frontHitStatusSeed % count($frontHitStatusTexts)];
            ?>
            <?php if ($isFrontForecastRecordContent): ?>
                <div class="front-forecast-list mt-4<?php echo e($frontForecastTypeClass); ?>">
                    <?php foreach ($frontForecastRows as $frontForecastRowIndex => $frontForecastRow): ?>
                        <?php $frontParsedRow = $frontForecastParsedRows[$frontForecastRowIndex] ?? null; ?>
                        <?php if ($frontAdminHistoryEmbed && is_array($frontParsedRow) && !empty($frontParsedRow['is_current_issue'])) { continue; } ?>
                        <article class="front-forecast-card" data-forecast-row>
                            <?php if (is_array($frontParsedRow)): ?>
                                <?php
                                $frontPredictionStatus = (string) ($frontParsedRow['status'] ?? '');
                                $frontPredictionClass = 'front-forecast-prediction';
                                if (in_array($frontPredictionStatus, array('准', '中', '赢', '發', '发'), true)) {
                                    $frontPredictionClass .= ' is-hit';
                                } elseif ($frontPredictionStatus === '错') {
                                    $frontPredictionClass .= ' is-miss';
                                }
                                $frontPredictionParamTotal = (int) ($frontParsedRow['prediction_param_count'] ?? 0);
                                if ($frontPredictionParamTotal === 1) {
                                    $frontPredictionClass .= ' is-one-option-count';
                                } elseif ($frontPredictionParamTotal >= 2 && $frontPredictionParamTotal <= 3) {
                                    $frontPredictionClass .= ' is-large-option-count';
                                } elseif ($frontPredictionParamTotal >= 4 && $frontPredictionParamTotal <= 5) {
                                    $frontPredictionClass .= ' is-five-option-count';
                                }
                                $frontStatusDisplayText = $frontPredictionStatus;
                                if ($frontPredictionStatus === '准') {
                                    $frontStatusDisplayText = $frontUnifiedHitStatusText;
                                }
                                $frontForecastBlessingText = '';
                                $frontForecastBlessingClass = '';
                                $frontForecastBlessingSeedBase = 'front-blessing|'
                                    . (string) ($post['id'] ?? '')
                                    . '|'
                                    . (string) ($region ?? '')
                                    . '|'
                                    . (string) ($frontParsedRow['issue'] ?? '')
                                    . '|'
                                    . (string) ($frontParsedRow['type'] ?? '')
                                    . '|'
                                    . (string) ($frontParsedRow['prediction'] ?? '')
                                    . '|'
                                    . (string) ($frontParsedRow['open_result'] ?? '')
                                    . '|'
                                    . $frontPredictionStatus
                                    . '|'
                                    . (string) $frontForecastRow;
                                if (in_array($frontPredictionStatus, array('准', '中', '赢', '發', '发'), true)) {
                                    if ($frontForecastHitBlessingLines !== array()) {
                                        $frontForecastBlessingStyle = (int) sprintf(
                                            '%u',
                                            crc32($frontForecastBlessingSeedBase . '|style')
                                        ) % 32;
                                        if ($frontForecastBlessingStyle !== 0) {
                                            $frontForecastBlessingLineIndex = (int) sprintf(
                                                '%u',
                                                crc32($frontForecastBlessingSeedBase . '|line')
                                            ) % count($frontForecastHitBlessingLines);
                                            $frontForecastBlessingText = $frontForecastHitBlessingLines[$frontForecastBlessingLineIndex];
                                        }
                                    }
                                    if ($frontForecastBlessingText === '') {
                                        $frontForecastBlessingPools = array(
                                            array('key' => 'hit-luck', 'items' => $frontForecastHitBlessingLuckParts),
                                            array('key' => 'hit-harvest', 'items' => $frontForecastHitBlessingHarvestParts),
                                            array('key' => 'hit-wish', 'items' => $frontForecastHitBlessingWishParts),
                                            array('key' => 'hit-plain', 'items' => $frontForecastHitBlessingPlainParts),
                                            array('key' => 'hit-direct', 'items' => $frontForecastHitBlessingDirectParts),
                                        );
                                        $frontForecastBlessingPoolOrder = array();
                                        foreach ($frontForecastBlessingPools as $frontForecastBlessingPoolRow) {
                                            $frontForecastBlessingPoolOrder[] = hash('crc32b', $frontForecastBlessingSeedBase . '|pool|' . $frontForecastBlessingPoolRow['key']);
                                        }
                                        array_multisort($frontForecastBlessingPoolOrder, SORT_ASC, SORT_STRING, $frontForecastBlessingPools);
                                        $frontForecastBlessingMaxParts = min(5, count($frontForecastBlessingPools));
                                        $frontForecastBlessingPartCount = 1 + ((int) sprintf('%u', crc32($frontForecastBlessingSeedBase . '|count')) % $frontForecastBlessingMaxParts);
                                        $frontForecastBlessingParts = array();
                                        for ($frontForecastBlessingPartIndex = 0; $frontForecastBlessingPartIndex < $frontForecastBlessingPartCount; $frontForecastBlessingPartIndex++) {
                                            $frontForecastBlessingPool = $frontForecastBlessingPools[$frontForecastBlessingPartIndex];
                                            $frontForecastBlessingPoolItems = $frontForecastBlessingPool['items'];
                                            $frontForecastBlessingItemIndex = (int) sprintf(
                                                '%u',
                                                crc32($frontForecastBlessingSeedBase . '|item|' . $frontForecastBlessingPool['key'])
                                            ) % count($frontForecastBlessingPoolItems);
                                            $frontForecastBlessingParts[] = $frontForecastBlessingPoolItems[$frontForecastBlessingItemIndex];
                                        }
                                        $frontForecastBlessingPartOrder = array();
                                        foreach ($frontForecastBlessingParts as $frontForecastBlessingPartIndex => $frontForecastBlessingPartText) {
                                            $frontForecastBlessingPartOrder[] = hash('crc32b', $frontForecastBlessingSeedBase . '|part|' . $frontForecastBlessingPartIndex . '|' . $frontForecastBlessingPartText);
                                        }
                                        array_multisort($frontForecastBlessingPartOrder, SORT_ASC, SORT_STRING, $frontForecastBlessingParts);
                                        $frontForecastBlessingText = implode('，', $frontForecastBlessingParts) . '。';
                                    }
                                    $frontForecastBlessingClass = ' is-hit';
                                } elseif ($frontPredictionStatus === '错') {
                                    if ($frontForecastMissBlessingLines !== array()) {
                                        $frontForecastBlessingStyle = (int) sprintf(
                                            '%u',
                                            crc32($frontForecastBlessingSeedBase . '|style')
                                        ) % 8;
                                        if ($frontForecastBlessingStyle !== 0) {
                                            $frontForecastBlessingLineIndex = (int) sprintf(
                                                '%u',
                                                crc32($frontForecastBlessingSeedBase . '|line')
                                            ) % count($frontForecastMissBlessingLines);
                                            $frontForecastBlessingText = $frontForecastMissBlessingLines[$frontForecastBlessingLineIndex];
                                        }
                                    }
                                    if ($frontForecastBlessingText === '') {
                                        $frontForecastBlessingPools = array(
                                            array('key' => 'miss-care', 'items' => $frontForecastMissBlessingCareParts),
                                            array('key' => 'miss-push', 'items' => $frontForecastMissBlessingPushParts),
                                            array('key' => 'miss-wish', 'items' => $frontForecastMissBlessingWishParts),
                                            array('key' => 'miss-plain', 'items' => $frontForecastMissBlessingPlainParts),
                                            array('key' => 'miss-direct', 'items' => $frontForecastMissBlessingDirectParts),
                                        );
                                        $frontForecastBlessingPoolOrder = array();
                                        foreach ($frontForecastBlessingPools as $frontForecastBlessingPoolRow) {
                                            $frontForecastBlessingPoolOrder[] = hash('crc32b', $frontForecastBlessingSeedBase . '|pool|' . $frontForecastBlessingPoolRow['key']);
                                        }
                                        array_multisort($frontForecastBlessingPoolOrder, SORT_ASC, SORT_STRING, $frontForecastBlessingPools);
                                        $frontForecastBlessingMaxParts = min(5, count($frontForecastBlessingPools));
                                        $frontForecastBlessingPartCount = 1 + ((int) sprintf('%u', crc32($frontForecastBlessingSeedBase . '|count')) % $frontForecastBlessingMaxParts);
                                        $frontForecastBlessingParts = array();
                                        for ($frontForecastBlessingPartIndex = 0; $frontForecastBlessingPartIndex < $frontForecastBlessingPartCount; $frontForecastBlessingPartIndex++) {
                                            $frontForecastBlessingPool = $frontForecastBlessingPools[$frontForecastBlessingPartIndex];
                                            $frontForecastBlessingPoolItems = $frontForecastBlessingPool['items'];
                                            $frontForecastBlessingItemIndex = (int) sprintf(
                                                '%u',
                                                crc32($frontForecastBlessingSeedBase . '|item|' . $frontForecastBlessingPool['key'])
                                            ) % count($frontForecastBlessingPoolItems);
                                            $frontForecastBlessingParts[] = $frontForecastBlessingPoolItems[$frontForecastBlessingItemIndex];
                                        }
                                        $frontForecastBlessingPartOrder = array();
                                        foreach ($frontForecastBlessingParts as $frontForecastBlessingPartIndex => $frontForecastBlessingPartText) {
                                            $frontForecastBlessingPartOrder[] = hash('crc32b', $frontForecastBlessingSeedBase . '|part|' . $frontForecastBlessingPartIndex . '|' . $frontForecastBlessingPartText);
                                        }
                                        array_multisort($frontForecastBlessingPartOrder, SORT_ASC, SORT_STRING, $frontForecastBlessingParts);
                                        $frontForecastBlessingText = implode('，', $frontForecastBlessingParts) . '。';
                                    }
                                    $frontForecastBlessingClass = ' is-miss';
                                }
                                $frontRecordStatText = $frontResolveForecastRecordStatText(
                                    (string) ($frontParsedRow['type'] ?? ''),
                                    (string) ($frontParsedRow['prediction'] ?? ''),
                                    (string) ($frontParsedRow['open_result'] ?? ''),
                                    (string) ($frontParsedRow['issue'] ?? '')
                                );
                                $frontShowPredictionStatus = $frontPredictionStatus !== '' && $frontRecordStatText === '';
                                ?>
                                <header class="front-forecast-card-head">
                                    <span class="front-forecast-issue"><?php echo e($frontParsedRow['issue']); ?></span>
                                    <span class="front-forecast-author-shine is-shine <?php echo e($frontAuthorPaletteClass); ?>">
                                        <?php
                                        $frontDisplayEmojiLeft = (string) ($frontFallbackAuthorIconPair[0] ?? '');
                                        $frontDisplayEmojiRight = (string) ($frontFallbackAuthorIconPair[1] ?? '');
                                        ?>
                                        <span class="front-forecast-emoji"><?php echo e($frontDisplayEmojiLeft); ?></span><?php echo e($frontParsedRow['author']); ?><span class="front-forecast-emoji"><?php echo e($frontDisplayEmojiRight); ?></span>
                                    </span>
                                    <span class="front-forecast-type"><?php echo $frontFormatTypeTitleNumbersHtml((string) ($frontParsedRow['display_type'] ?? $frontParsedRow['type'])); ?></span>
                                    <?php if ($frontRecordStatText !== ''): ?><span class="front-forecast-record-stat"><?php echo e($frontRecordStatText); ?></span><?php endif; ?>
                                    <?php if ($frontShowPredictionStatus): ?><span class="<?php echo in_array($frontPredictionStatus, array('准', '中', '赢', '發', '发'), true) ? 'front-forecast-status is-hit' : 'front-forecast-status is-miss'; ?>"><?php echo e($frontStatusDisplayText); ?></span><?php endif; ?>
                                </header>
                                <div class="front-forecast-card-body">
                                    <div class="front-forecast-prediction-box">
                                        <?php if (!empty($frontParsedRow['is_sale_locked'])): ?>
                                            <div class="front-post-buy-actions">
                                                <div class="front-post-sale-pill"><?php echo e($frontSaleNoticeText); ?></div>
                                                <?php if (!empty($frontParsedRow['draw_items'])): ?>
                                                    <div class="front-forecast-draw-record front-forecast-draw-record--sale" aria-label="开奖结果记录">
                                                        <span class="front-forecast-draw-label">开奖结果</span>
                                                        <div class="front-forecast-draw-items">
                                                            <?php foreach ((array) $frontParsedRow['draw_items'] as $frontDrawIndex => $frontDrawItem): ?>
                                                                <?php if ($frontDrawIndex === 6): ?><span class="front-forecast-draw-plus">+</span><?php endif; ?>
                                                                <span class="front-forecast-draw-item <?php echo e((string) ($frontDrawItem['tone_class'] ?? '')); ?>">
                                                                    <span class="front-forecast-draw-num"><?php echo e((string) ($frontDrawItem['number_text'] ?? '')); ?></span>
                                                                    <span class="front-forecast-draw-zodiac"><?php echo e((string) ($frontDrawItem['zodiac'] ?? '')); ?></span>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="front-forecast-draw-record front-forecast-draw-record--sale is-pending" aria-label="等待开奖结果">
                                                        <span class="front-forecast-draw-label">开奖记录</span>
                                                        <div class="front-forecast-draw-items">
                                                            <?php foreach ($frontPendingDrawChars as $frontPendingDrawChar): ?>
                                                                <span class="front-forecast-draw-item is-pending-char">
                                                                    <span class="front-forecast-draw-num"><?php echo e((string) $frontPendingDrawChar); ?></span>
                                                                </span>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                                <?php if ($frontCustomerServiceAgentViewer): ?>
                                                    <form class="front-post-buy-form" method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form>
                                                        <input type="hidden" name="action" value="post.buy">
                                                        <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                                                        <input type="hidden" name="post_id" value="<?php echo e($post['id']); ?>">
                                                        <input type="hidden" name="agent" value="1">
                                                        <span class="front-post-buy-price">售价 <?php echo e((string) (int) $post['price']); ?> 积分</span>
                                                        <button class="front-post-buy-submit admin-button" type="submit">购买资料</button>
                                                    </form>
                                                <?php elseif ($viewer): ?>
                                                    <form class="front-post-buy-form" method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form>
                                                        <input type="hidden" name="action" value="post.buy">
                                                        <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                                                        <input type="hidden" name="post_id" value="<?php echo e($post['id']); ?>">
                                                        <span class="front-post-buy-price">售价 <?php echo e((string) (int) $post['price']); ?> 积分</span>
                                                        <button class="front-post-buy-submit admin-button" type="submit">购买资料</button>
                                                    </form>
                                                <?php else: ?>
                                                    <div class="front-post-buy-form">
                                                        <span class="front-post-buy-price">售价 <?php echo e((string) (int) $post['price']); ?> 积分</span>
                                                        <a class="front-post-buy-submit admin-button" href="<?php echo e(public_url('member.php') . '?region=' . urlencode($region) . '&mode=login'); ?>" data-front-post-login-open>购买资料</a>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <?php $frontShowNoMaterialWaiting = !empty($frontParsedRow['show_pending_draw']) && (!empty($frontParsedRow['is_waiting_material']) || $frontForecastPlainText === $frontNoMaterialWaitingContent); ?>
                                            <?php if ($frontShowNoMaterialWaiting): ?>
                                                <div class="front-forecast-prediction">
                                                    <span class="front-forecast-prediction-inner front-forecast-waiting-text"><?php echo e($frontNoMaterialWaitingDisplayContent); ?></span>
                                                </div>
                                            <?php else: ?>
                                                <span class="<?php echo e($frontPredictionClass); ?>"><span class="front-forecast-prediction-inner"><?php echo $frontRenderPredictionHtml((string) $frontParsedRow['prediction'], (string) $frontParsedRow['open_result'], $frontPredictionStatus, (string) $frontParsedRow['type'], (string) $frontParsedRow['issue']); ?></span></span>
                                            <?php endif; ?>
                                            <?php if (!empty($frontParsedRow['show_draw_record']) && !empty($frontParsedRow['draw_items'])): ?>
                                                <div class="front-forecast-draw-record" aria-label="开奖结果记录">
                                                    <span class="front-forecast-draw-label">开奖记录</span>
                                                    <div class="front-forecast-draw-items">
                                                        <?php foreach ((array) $frontParsedRow['draw_items'] as $frontDrawIndex => $frontDrawItem): ?>
                                                            <?php if ($frontDrawIndex === 6): ?><span class="front-forecast-draw-plus">+</span><?php endif; ?>
                                                            <span class="front-forecast-draw-item <?php echo e((string) ($frontDrawItem['tone_class'] ?? '')); ?>">
                                                                <span class="front-forecast-draw-num"><?php echo e((string) ($frontDrawItem['number_text'] ?? '')); ?></span>
                                                                <span class="front-forecast-draw-zodiac"><?php echo e((string) ($frontDrawItem['zodiac'] ?? '')); ?></span>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <?php if ($frontForecastBlessingText !== '' && (empty($frontParsedRow['is_current_issue']) || !$frontPostLockIsLocked)): ?>
                                                    <div class="front-forecast-blessing-card<?php echo e($frontForecastBlessingClass); ?>">
                                                        <span class="front-forecast-blessing-text"><?php echo e($frontForecastBlessingText); ?></span>
                                                    </div>
                                                <?php endif; ?>
                                            <?php elseif (!empty($frontParsedRow['show_pending_draw'])): ?>
                                                <div class="front-forecast-draw-record is-pending" aria-label="等待开奖结果">
                                                    <span class="front-forecast-draw-label">开奖记录</span>
                                                    <div class="front-forecast-draw-items">
                                                        <?php foreach ($frontPendingDrawChars as $frontPendingDrawChar): ?>
                                                            <span class="front-forecast-draw-item is-pending-char">
                                                                <span class="front-forecast-draw-num"><?php echo e((string) $frontPendingDrawChar); ?></span>
                                                            </span>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <?php echo e($frontForecastRow); ?>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
                <?php if ((int) ($post['price'] ?? 0) > 0): ?>
                    <?php
                    $frontSaleBuyerIssueTail = trim((string) ($frontSaleBuyerSummary['issue_tail'] ?? ''));
                    $frontSaleBuyerTitle = $frontSaleBuyerIssueTail !== '' ? $frontSaleBuyerIssueTail . '期购买会员' : '本期购买会员';
                    $frontSaleBuyerRows = isset($frontSaleBuyerSummary['buyers']) && is_array($frontSaleBuyerSummary['buyers'])
                        ? $frontSaleBuyerSummary['buyers']
                        : array();
                    $frontSaleBuyerDesktopRows = array_slice($frontSaleBuyerRows, 0, 5);
                    $frontSaleBuyerDesktopHiddenCount = max(0, count($frontSaleBuyerRows) - count($frontSaleBuyerDesktopRows));
                    $frontSaleBuyerMobileRows = array_slice($frontSaleBuyerRows, 0, 2);
                    $frontSaleBuyerMobileHiddenCount = max(0, count($frontSaleBuyerRows) - count($frontSaleBuyerMobileRows));
                    ?>
                    <div class="front-post-sale-buyer-board">
                        <div class="front-post-sale-buyer-head">
                            <span><?php echo e($frontSaleBuyerTitle); ?></span>
                            <strong><?php echo e((string) (int) ($frontSaleBuyerSummary['total'] ?? 0)); ?>人</strong>
                        </div>
                        <?php if ($frontSaleBuyerRows !== array()): ?>
                            <div class="front-post-sale-buyer-list is-desktop">
                                <?php foreach ($frontSaleBuyerDesktopRows as $frontSaleBuyerRow): ?>
                                    <span class="front-post-sale-buyer-name"><?php echo e((string) ($frontSaleBuyerRow['username'] ?? '')); ?></span>
                                <?php endforeach; ?>
                                <?php if ($frontSaleBuyerDesktopHiddenCount > 0): ?>
                                    <span class="front-post-sale-buyer-more">...</span>
                                <?php endif; ?>
                            </div>
                            <div class="front-post-sale-buyer-list is-mobile">
                                <?php foreach ($frontSaleBuyerMobileRows as $frontSaleBuyerRow): ?>
                                    <span class="front-post-sale-buyer-name"><?php echo e((string) ($frontSaleBuyerRow['username'] ?? '')); ?></span>
                                <?php endforeach; ?>
                                <?php if ($frontSaleBuyerMobileHiddenCount > 0): ?>
                                    <span class="front-post-sale-buyer-more">...</span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($frontForecastPlainText !== '' && !$frontForecastWaitingInCurrentRow): ?>
                    <div class="<?php echo e($frontForecastPlainContentClass); ?>"><?php echo e($frontForecastPlainText); ?></div>
                <?php endif; ?>
            <?php else: ?>
                <div class="<?php echo e($frontPlainContentClass); ?>"><?php echo e($frontDisplayContentText); ?></div>
            <?php endif; ?>
            <?php if ($purchaseNeeded && !$frontHasSaleLockedForecastRow): ?>
                <div class="front-post-buy-actions mt-5">
                    <div class="front-post-sale-pill"><?php echo e($frontSaleNoticeText); ?></div>
                    <?php if ($frontCustomerServiceAgentViewer): ?>
                        <form class="front-post-buy-form" method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form>
                            <input type="hidden" name="action" value="post.buy">
                            <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                            <input type="hidden" name="post_id" value="<?php echo e($post['id']); ?>">
                            <input type="hidden" name="agent" value="1">
                            <span class="front-post-buy-price">售价 <?php echo e((string) (int) $post['price']); ?> 积分</span>
                            <button class="front-post-buy-submit admin-button" type="submit">购买资料</button>
                        </form>
                    <?php elseif ($viewer): ?>
                        <form class="front-post-buy-form" method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form>
                            <input type="hidden" name="action" value="post.buy">
                            <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                            <input type="hidden" name="post_id" value="<?php echo e($post['id']); ?>">
                            <span class="front-post-buy-price">售价 <?php echo e((string) (int) $post['price']); ?> 积分</span>
                            <button class="front-post-buy-submit admin-button" type="submit">购买资料</button>
                        </form>
                    <?php else: ?>
                        <div class="front-post-buy-form">
                            <span class="front-post-buy-price">售价 <?php echo e((string) (int) $post['price']); ?> 积分</span>
                            <a class="front-post-buy-submit admin-button" href="<?php echo e(public_url('member.php') . '?region=' . urlencode($region) . '&mode=login'); ?>" data-front-post-login-open>购买资料</a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="front-panel-card front-post-replies-card">
            <div class="front-post-replies-shell">
                <div class="front-post-replies-header">
                    <div>
                        <div class="front-post-replies-kicker">即时互动</div>
                        <h2 class="front-post-replies-title">发表回复</h2>
                    </div>
                    <div class="front-post-replies-count"><?php echo e((string) ($replyTotal ?? 0)); ?> 条</div>
                </div>

                <div class="front-post-replies-list">
                    <?php foreach ($replies as $reply): ?>
                        <article class="front-post-reply-item" data-comment-id="<?php echo e((string) $reply['id']); ?>">
                            <div class="front-post-reply-meta">
                                <div class="front-post-reply-user">
                                    <span class="front-post-reply-avatar">
                                        <i class="fa-solid fa-circle-user" aria-hidden="true"></i>
                                        <span class="front-post-reply-avatar-level" data-avatar-level="<?php echo e((string) ($reply['avatar_level_label'] ?? 'vip3')); ?>"><?php echo e((string) ($reply['avatar_level_label'] ?? 'vip3')); ?></span>
                                    </span>
                                    <span class="front-post-reply-author"><?php echo e($reply['username']); ?></span>
                                </div>
                                <time class="front-post-reply-time"><?php echo e(format_datetime($reply['created_at'])); ?></time>
                            </div>
                            <div class="front-post-reply-body"><?php echo e($reply['content']); ?></div>
                            <?php if (!empty($canComment)): ?>
                                <div class="front-post-reply-tools">
                                    <button class="front-post-reply-tool" type="button" data-comment-reply data-comment-id="<?php echo e((string) $reply['id']); ?>" data-comment-author="<?php echo e($reply['username']); ?>">回复</button>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($reply['children']) && is_array($reply['children'])): ?>
                                <div class="front-post-reply-children">
                                    <?php foreach ($reply['children'] as $childReply): ?>
                                        <article class="front-post-reply-item is-child" data-comment-id="<?php echo e((string) $childReply['id']); ?>">
                                            <div class="front-post-reply-meta">
                                                <div class="front-post-reply-user">
                                                    <span class="front-post-reply-avatar">
                                                        <i class="fa-solid fa-circle-user" aria-hidden="true"></i>
                                                        <span class="front-post-reply-avatar-level" data-avatar-level="<?php echo e((string) ($childReply['avatar_level_label'] ?? 'vip3')); ?>"><?php echo e((string) ($childReply['avatar_level_label'] ?? 'vip3')); ?></span>
                                                    </span>
                                                    <span class="front-post-reply-author"><?php echo e($childReply['username']); ?></span>
                                                </div>
                                                <time class="front-post-reply-time"><?php echo e(format_datetime($childReply['created_at'])); ?></time>
                                            </div>
                                            <div class="front-post-reply-body"><?php echo e($childReply['content']); ?></div>
                                            <?php if (!empty($canComment)): ?>
                                                <div class="front-post-reply-tools">
                                                    <button class="front-post-reply-tool" type="button" data-comment-reply data-comment-id="<?php echo e((string) $childReply['id']); ?>" data-comment-author="<?php echo e($childReply['username']); ?>">回复</button>
                                                </div>
                                            <?php endif; ?>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                    <?php if (empty($replies)): ?>
                        <div class="front-post-replies-empty">
                            <span class="front-post-replies-empty-icon"></span>
                            <div>
                                <div class="front-post-replies-empty-title">暂无评论</div>
                                <p class="front-post-replies-empty-text">欢迎发表第一条回复。</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                <?php if (!empty($canCustomerServiceEditPost)): ?>
                    <div class="front-post-customer-service-edit-actions">
                        <button class="front-post-customer-service-edit-open" type="button" data-front-post-customer-service-edit-open>
                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            <span>编辑</span>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (!$isModalPost): ?>
                    <div class="front-post-reply-composer">
                        <?php if ($viewer): ?>
                            <form class="front-post-reply-form" method="post" action="<?php echo e(public_url('api.php')); ?>" data-comment-form>
                                <input type="hidden" name="action" value="post.reply">
                                <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                                <input type="hidden" name="post_id" value="<?php echo e($post['id']); ?>">
                                <input type="hidden" name="parent_id" value="0" data-comment-parent-id>
                                <div class="front-post-reply-form-head">
                                    <label class="front-post-reply-form-title" for="front-post-reply-content" data-comment-form-title>写下你的回复</label>
                                    <span class="front-post-reply-form-help"><?php echo e($commentPermissionText); ?></span>
                                </div>
                                <div class="front-post-reply-target" data-comment-reply-target hidden>
                                    <span data-comment-reply-target-text></span>
                                    <button type="button" data-comment-reply-cancel>取消</button>
                                </div>
                                <textarea id="front-post-reply-content" class="admin-textarea front-post-reply-textarea" name="content" placeholder="<?php echo !empty($canComment) ? e('善于结善缘，恶言伤人心') : e($commentPermissionText); ?>" <?php echo !empty($canComment) ? '' : 'disabled'; ?>></textarea>
                                <div data-form-error class="hidden front-post-reply-error"></div>
                                <div class="front-post-reply-form-actions">
                                    <span class="front-post-reply-note">支持多行内容</span>
                                    <button class="admin-button front-post-reply-submit" type="submit" <?php echo !empty($canComment) ? '' : 'disabled'; ?>>提交回复</button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="front-post-reply-login-tip <?php echo $purchaseNeeded ? 'has-purchase-login' : ''; ?>">
                                <div class="front-post-reply-login-text">
                                    <span>登录后可回复、购买资料和进入会员中心</span>
                                </div>
                                <a class="front-post-reply-login-link" href="<?php echo e(public_url('member.php') . '?region=' . urlencode($region) . '&mode=login'); ?>" data-front-post-login-open><?php echo $purchaseNeeded ? '登录后购买' : '立即登录'; ?></a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($postBottomHtml !== ''): ?>
            <div class="front-panel-card"><?php echo $postBottomHtml; ?></div>
        <?php endif; ?>
    </div>

    <?php if ($isModalPost): ?>
        <div class="front-post-modal-composer-card">
            <div class="front-post-reply-composer">
                <?php if ($viewer): ?>
                    <form class="front-post-reply-form" method="post" action="<?php echo e(public_url('api.php')); ?>" data-comment-form>
                        <input type="hidden" name="action" value="post.reply">
                        <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                        <input type="hidden" name="post_id" value="<?php echo e($post['id']); ?>">
                        <input type="hidden" name="parent_id" value="0" data-comment-parent-id>
                        <div class="front-post-reply-form-head">
                            <label class="front-post-reply-form-title" for="front-post-reply-content" data-comment-form-title>写下你的回复</label>
                            <span class="front-post-reply-form-help"><?php echo e($commentPermissionText); ?></span>
                        </div>
                        <div class="front-post-reply-target" data-comment-reply-target hidden>
                            <span data-comment-reply-target-text></span>
                            <button type="button" data-comment-reply-cancel>取消</button>
                        </div>
                        <textarea id="front-post-reply-content" class="admin-textarea front-post-reply-textarea" name="content" placeholder="<?php echo !empty($canComment) ? e('善于结善缘，恶言伤人心') : e($commentPermissionText); ?>" <?php echo !empty($canComment) ? '' : 'disabled'; ?>></textarea>
                        <div data-form-error class="hidden front-post-reply-error"></div>
                        <div class="front-post-reply-form-actions">
                            <span class="front-post-reply-note">支持多行内容</span>
                            <button class="admin-button front-post-reply-submit" type="submit" <?php echo !empty($canComment) ? '' : 'disabled'; ?>>提交回复</button>
                        </div>
                    </form>
                <?php else: ?>
                    <div class="front-post-reply-login-tip <?php echo $purchaseNeeded ? 'has-purchase-login' : ''; ?>">
                        <div class="front-post-reply-login-text">
                            <span>登录后可回复、购买资料和进入会员中心</span>
                        </div>
                        <a class="front-post-reply-login-link" href="<?php echo e(public_url('member.php') . '?region=' . urlencode($region) . '&mode=login'); ?>" data-front-post-login-open><?php echo $purchaseNeeded ? '登录后购买' : '立即登录'; ?></a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    <?php if (!empty($canCustomerServiceEditPost)): ?>
        <?php
        $customerServiceEditBracketPairs = array(
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
        ?>
        <div class="front-post-login-modal front-post-customer-service-edit-modal front-standard-modal" data-front-post-customer-service-edit-modal hidden role="dialog" aria-modal="true" aria-labelledby="front-post-customer-service-edit-title">
            <div class="front-post-login-backdrop front-standard-modal-backdrop" data-front-post-customer-service-edit-close></div>
            <form class="front-post-customer-service-edit-card front-standard-modal-dialog" method="post" action="<?php echo e(public_url('api.php')); ?>" data-front-post-customer-service-edit-form>
                <input type="hidden" name="action" value="post.customer_service.update">
                <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                <input type="hidden" name="post_id" value="<?php echo e($post['id']); ?>">
                <div class="front-post-customer-service-edit-head front-standard-modal-head">
                    <div class="front-post-customer-service-edit-title">
                        <h2 id="front-post-customer-service-edit-title">编辑资料</h2>
                        <p><?php echo isset($displayTitleHtml) && $displayTitleHtml !== '' ? $displayTitleHtml : e(isset($displayTitle) ? (string) $displayTitle : (string) ($post['title'] ?? '')); ?></p>
                    </div>
                    <button type="button" data-front-post-customer-service-edit-close aria-label="关闭编辑窗口">
                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="front-post-customer-service-edit-brackets" aria-label="资料内容括号快捷插入">
                    <?php foreach ($customerServiceEditBracketPairs as $customerServiceEditBracketPair): ?>
                        <?php
                        $customerServiceEditBracketLeft = (string) ($customerServiceEditBracketPair[0] ?? '');
                        $customerServiceEditBracketRight = (string) ($customerServiceEditBracketPair[1] ?? '');
                        ?>
                        <button
                            type="button"
                            data-front-post-customer-service-edit-bracket
                            data-front-post-customer-service-edit-bracket-left="<?php echo e($customerServiceEditBracketLeft); ?>"
                            data-front-post-customer-service-edit-bracket-right="<?php echo e($customerServiceEditBracketRight); ?>"
                            aria-label="<?php echo e('插入' . $customerServiceEditBracketLeft . $customerServiceEditBracketRight); ?>"
                        ><?php echo e($customerServiceEditBracketLeft . $customerServiceEditBracketRight); ?></button>
                    <?php endforeach; ?>
                </div>
                <label class="front-post-customer-service-edit-field" for="front-post-customer-service-edit-content">
                    <textarea id="front-post-customer-service-edit-content" name="full_content" rows="3"><?php echo e((string) ($customerServiceEditContent ?? '')); ?></textarea>
                </label>
                <div class="front-post-customer-service-edit-error" data-front-post-customer-service-edit-error hidden></div>
                <div class="front-post-customer-service-edit-foot">
                    <label class="front-post-customer-service-edit-field is-price" for="front-post-customer-service-edit-price">
                        <span>出售价格</span>
                        <input id="front-post-customer-service-edit-price" type="number" name="price" min="0" step="1" value="<?php echo e((string) max(0, (int) ($post['price'] ?? 0))); ?>">
                    </label>
                    <button type="submit">保存</button>
                </div>
            </form>
        </div>
    <?php endif; ?>
    <?php if ($purchaseNeeded && $viewer): ?>
        <?php echo \App\Core\View::make(app(), 'partials/member_recharge_modal', array(
            'region' => $region,
            'memberRechargeUser' => $viewer,
            'memberRechargeUrl' => public_url('service.php') . '?' . http_build_query(array('region' => $region, 'embed' => '1')),
        )); ?>
    <?php endif; ?>
    <?php if (!$viewer): ?>
        <div class="front-post-login-modal front-standard-modal" data-front-post-login-modal hidden role="dialog" aria-modal="true" aria-labelledby="front-post-login-title">
            <div class="front-post-login-backdrop front-standard-modal-backdrop" data-front-post-login-close></div>
            <div class="member-auth-card front-auth-card front-post-login-card front-standard-panel front-standard-modal-dialog">
                <div class="member-auth-head front-auth-head front-standard-modal-head">
                    <div>
                        <div class="member-auth-kicker"><?php echo $region === 'hongkong' ? '香港论坛' : '澳门论坛'; ?></div>
                        <h1 id="front-post-login-title" class="member-auth-heading">会员登录</h1>
                        <p class="member-auth-copy">输入账号密码，进入会员中心。</p>
                    </div>
                    <div class="member-auth-mark" aria-hidden="true">
                        <i class="fa-solid fa-user-check"></i>
                    </div>
                </div>
                <div class="member-auth-tabs" role="tablist" aria-label="会员入口">
                    <button type="button" class="member-auth-tab is-active" data-front-post-auth-mode="login" aria-selected="true">登录</button>
                    <button type="button" class="member-auth-tab" data-front-post-auth-mode="register" aria-selected="false">注册</button>
                    <button type="button" class="member-auth-tab" data-front-post-auth-mode="reset" aria-selected="false">找回</button>
                </div>
                <form method="post" action="<?php echo e(public_url('api.php')); ?>" class="front-auth-form member-auth-form member-auth-form--modal" data-front-post-login-form aria-labelledby="front-post-login-title">
                    <input type="hidden" name="action" value="auth.login" data-front-post-auth-action>
                    <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                    <input type="hidden" name="region" value="<?php echo e($region); ?>">
                    <div class="member-auth-field member-auth-field--account">
                        <label class="member-auth-label" for="front-post-login-username">会员账号</label>
                        <input id="front-post-login-username" class="auth-input member-auth-input" type="text" name="username" autocomplete="username" placeholder="请输入会员账号">
                    </div>
                    <div class="member-auth-field member-auth-field--password" data-front-post-password-field>
                        <label class="member-auth-label" for="front-post-login-password" data-front-post-password-label>登录密码</label>
                        <input id="front-post-login-password" class="auth-input member-auth-input" type="password" name="password" autocomplete="current-password" placeholder="请输入登录密码">
                    </div>
                    <div class="member-auth-field member-auth-field--confirm-password" data-front-post-confirm-field hidden>
                        <label class="member-auth-label" for="front-post-login-confirm-password" data-front-post-confirm-label>确认密码</label>
                        <input id="front-post-login-confirm-password" class="auth-input member-auth-input" type="password" name="confirm_password" autocomplete="new-password" placeholder="请再次输入注册密码" disabled>
                    </div>
                    <div class="member-auth-field member-auth-field--recovery" data-front-post-recovery-field hidden>
                        <label class="member-auth-label" for="front-post-login-recovery-answer" data-front-post-recovery-label>找回验证信息</label>
                        <input id="front-post-login-recovery-answer" class="auth-input member-auth-input" type="text" name="recovery_answer" autocomplete="off" placeholder="请设置一条便于记忆的信息" disabled>
                    </div>
                    <div data-front-post-login-error class="front-post-login-error" hidden></div>
                    <div class="front-form-actions member-auth-actions">
                        <button type="submit" class="member-auth-submit">
                            <span data-front-post-auth-submit-text>立即登录</span>
                            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</section>
<script src="<?php echo asset('comment-thread.js?v=20260612-site-notice-prompt-01'); ?>"></script>
<script>
(function () {
    var modal = document.querySelector('[data-front-post-login-modal]');
    var form = modal ? modal.querySelector('[data-front-post-login-form]') : null;
    var endpoint = form ? String(form.getAttribute('action') || './api.php') : './api.php';
    var authMode = 'login';
    var authConfig = {
        login: {
            action: 'auth.login',
            title: '会员登录',
            copy: '输入账号密码，进入会员中心。',
            passwordLabel: '登录密码',
            passwordPlaceholder: '请输入登录密码',
            passwordAutocomplete: 'current-password',
            submit: '立即登录',
            success: '登录成功。',
            error: '登录失败，请重试。'
        },
        register: {
            action: 'auth.register',
            title: '会员注册',
            copy: '创建账号，并设置找回验证信息。',
            passwordLabel: '设置密码',
            passwordPlaceholder: '请输入 6-20 位密码',
            passwordAutocomplete: 'new-password',
            confirmLabel: '确认密码',
            confirmPlaceholder: '请再次输入注册密码',
            recoveryLabel: '找回验证信息',
            recoveryPlaceholder: '请设置一条便于记忆的信息',
            submit: '注册并登录',
            success: '注册成功，已自动登录。',
            error: '注册失败，请重试。',
            showConfirm: true,
            showRecovery: true
        },
        reset: {
            action: 'password_reset.verify_reset',
            title: '找回密码',
            copy: '核对找回信息，重新设置密码。',
            passwordLabel: '设置新密码',
            passwordPlaceholder: '请输入新的登录密码',
            passwordAutocomplete: 'new-password',
            confirmLabel: '确认新密码',
            confirmPlaceholder: '请再次输入新密码',
            recoveryLabel: '找回验证信息',
            recoveryPlaceholder: '请输入注册时设置的信息',
            submit: '重置密码',
            success: '密码已重置，请使用新密码登录。',
            error: '重置失败，请重试。',
            showConfirm: true,
            showRecovery: true
        }
    };

    if (!modal || !form || !window.fetch || !window.FormData) {
        return;
    }

    var actionInput = form.querySelector('[data-front-post-auth-action]');
    var titleNode = modal.querySelector('#front-post-login-title');
    var copyNode = modal.querySelector('.member-auth-copy');
    var usernameInput = form.querySelector('input[name="username"]');
    var passwordLabel = modal.querySelector('[data-front-post-password-label]');
    var passwordInput = form.querySelector('input[name="password"]');
    var confirmField = modal.querySelector('[data-front-post-confirm-field]');
    var confirmLabel = modal.querySelector('[data-front-post-confirm-label]');
    var confirmInput = form.querySelector('input[name="confirm_password"]');
    var recoveryField = modal.querySelector('[data-front-post-recovery-field]');
    var recoveryLabel = modal.querySelector('[data-front-post-recovery-label]');
    var recoveryInput = form.querySelector('input[name="recovery_answer"]');
    var submitText = modal.querySelector('[data-front-post-auth-submit-text]');
    var tabs = modal.querySelectorAll('[data-front-post-auth-mode]');

    function showToast(message, type) {
        if (window.AppUI && typeof window.AppUI.toast === 'function') {
            window.AppUI.toast(String(message || ''), type || 'info');
            return;
        }

        if (window.console && message) {
            window.console.warn(String(message));
        }
    }

    function notifyParentAuthSuccess() {
        if (!window.parent || window.parent === window || typeof window.parent.postMessage !== 'function') {
            return;
        }

        try {
            window.parent.postMessage({
                type: 'front-post-auth-success'
            }, window.location.origin);
        } catch (messageError) {
        }
    }

    function setError(message) {
        var node = modal.querySelector('[data-front-post-login-error]');
        if (!node) {
            return;
        }

        node.textContent = String(message || '');
        node.hidden = !message;
    }

    function setFieldVisible(field, input, isVisible) {
        if (!field || !input) {
            return;
        }

        field.hidden = !isVisible;
        input.disabled = !isVisible;
        if (!isVisible) {
            input.value = '';
        }
    }

    function setMode(mode) {
        var config = authConfig[mode] || authConfig.login;
        authMode = authConfig[mode] ? mode : 'login';
        setError('');

        if (actionInput) {
            actionInput.value = config.action;
        }
        if (titleNode) {
            titleNode.textContent = config.title;
        }
        if (copyNode) {
            copyNode.textContent = config.copy;
        }
        if (usernameInput) {
            usernameInput.value = '';
            if (authMode === 'register') {
                usernameInput.setAttribute('placeholder', '请输入 3-16 位字母或数字账号');
                usernameInput.setAttribute('inputmode', 'latin');
                usernameInput.setAttribute('pattern', '[A-Za-z0-9]{3,16}');
                usernameInput.setAttribute('maxlength', '16');
                usernameInput.setAttribute('title', '会员账号需为 3-16 位字母或数字');
            } else {
                usernameInput.setAttribute('placeholder', '请输入会员账号');
                usernameInput.removeAttribute('inputmode');
                usernameInput.removeAttribute('pattern');
                usernameInput.removeAttribute('maxlength');
                usernameInput.removeAttribute('title');
            }
        }
        if (passwordLabel) {
            passwordLabel.textContent = config.passwordLabel;
        }
        if (passwordInput) {
            passwordInput.value = '';
            passwordInput.setAttribute('placeholder', config.passwordPlaceholder);
            passwordInput.setAttribute('autocomplete', config.passwordAutocomplete);
        }
        if (confirmInput) {
            confirmInput.value = '';
            confirmInput.setAttribute('placeholder', config.confirmPlaceholder || '');
        }
        if (confirmLabel) {
            confirmLabel.textContent = config.confirmLabel || '确认密码';
        }
        if (recoveryInput) {
            recoveryInput.value = '';
            recoveryInput.setAttribute('placeholder', config.recoveryPlaceholder || '');
        }
        if (recoveryLabel) {
            recoveryLabel.textContent = config.recoveryLabel || '找回验证信息';
        }
        if (submitText) {
            submitText.textContent = config.submit;
        }

        setFieldVisible(confirmField, confirmInput, !!config.showConfirm);
        setFieldVisible(recoveryField, recoveryInput, !!config.showRecovery);

        Array.prototype.forEach.call(tabs, function (tab) {
            var isActive = tab.getAttribute('data-front-post-auth-mode') === authMode;
            tab.classList.toggle('is-active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
        });
    }

    function setOpen(isOpen) {
        var firstInput;

        modal.hidden = !isOpen;
        document.body.classList.toggle('front-post-login-modal-open', !!isOpen);
        setError('');

        if (isOpen) {
            setMode('login');
            firstInput = modal.querySelector('input[name="username"]');
            window.setTimeout(function () {
                if (firstInput) {
                    firstInput.focus();
                }
            }, 0);
        }
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-front-post-login-open]'), function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            setOpen(true);
        });
    });

    modal.addEventListener('click', function (event) {
        var modeButton = event.target.closest('[data-front-post-auth-mode]');
        var nextMode;

        if (!modeButton) {
            return;
        }

        event.preventDefault();
        nextMode = modeButton.getAttribute('data-front-post-auth-mode') || 'login';
        if (nextMode === authMode) {
            return;
        }

        setMode(nextMode);
    });

    modal.addEventListener('click', function (event) {
        if (event.target && event.target.hasAttribute('data-front-post-login-close')) {
            event.preventDefault();
            setOpen(false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if ((event.key === 'Escape' || event.key === 'Esc') && !modal.hidden) {
            setOpen(false);
        }
    });

    form.addEventListener('submit', function (event) {
        var button = form.querySelector('[type="submit"]');
        var payload = new FormData(form);
        var config = authConfig[authMode] || authConfig.login;

        event.preventDefault();
        event.stopPropagation();
        setError('');

        if (button) {
            button.disabled = true;
        }

        fetch(endpoint, {
            method: 'POST',
            body: payload,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (result) {
            if (!result || !result.success) {
                throw new Error((result && result.message) || config.error);
            }

            showToast(result.message || config.success, 'success');
            if (authMode === 'reset') {
                setMode('login');
                return;
            }

            setOpen(false);
            notifyParentAuthSuccess();
            window.setTimeout(function () {
                window.location.reload();
            }, 180);
        }).catch(function (error) {
            setError(error.message || config.error);
            showToast(error.message || config.error, 'error');
        }).finally(function () {
            if (button) {
                button.disabled = false;
            }
        });
    });

}());

(function () {
    var modal = document.querySelector('[data-front-post-customer-service-edit-modal]');
    var form = modal ? modal.querySelector('[data-front-post-customer-service-edit-form]') : null;
    var textarea = form ? form.querySelector('textarea[name="full_content"]') : null;
    var endpoint = form ? String(form.getAttribute('action') || './api.php') : './api.php';

    if (!modal || !form || !window.fetch || !window.FormData) {
        return;
    }

    function showToast(message, type) {
        if (window.AppUI && typeof window.AppUI.toast === 'function') {
            window.AppUI.toast(String(message || ''), type || 'info');
            return;
        }

        if (window.console && message) {
            window.console.warn(String(message));
        }
    }

    function setError(message) {
        var node = modal.querySelector('[data-front-post-customer-service-edit-error]');
        if (!node) {
            return;
        }

        node.textContent = String(message || '');
        node.hidden = !message;
    }

    function syncViewport() {
        var viewport = window.visualViewport;
        var height = viewport ? viewport.height : window.innerHeight;
        var top = viewport ? viewport.offsetTop : 0;

        modal.style.setProperty('--front-post-edit-viewport-height', String(Math.max(320, Math.floor(height))) + 'px');
        modal.style.setProperty('--front-post-edit-viewport-top', String(Math.max(0, Math.floor(top))) + 'px');
        modal.classList.toggle('is-keyboard-active', !!viewport && height < window.innerHeight - 80);
    }

    function resizeTextarea() {
        if (!textarea) {
            return;
        }

        textarea.style.height = 'auto';
        textarea.style.height = String(Math.max(52, textarea.scrollHeight)) + 'px';
    }

    function setOpen(isOpen) {
        modal.hidden = !isOpen;
        document.body.classList.toggle('front-post-customer-service-edit-modal-open', !!isOpen);
        setError('');

        if (isOpen) {
            syncViewport();
            resizeTextarea();
            window.setTimeout(function () {
                if (textarea) {
                    textarea.focus();
                    resizeTextarea();
                }
            }, 0);
        }
    }

    Array.prototype.forEach.call(document.querySelectorAll('[data-front-post-customer-service-edit-open]'), function (button) {
        button.addEventListener('click', function (event) {
            event.preventDefault();
            setOpen(true);
        });
    });

    modal.addEventListener('click', function (event) {
        if (event.target && event.target.closest('[data-front-post-customer-service-edit-close]')) {
            event.preventDefault();
            setOpen(false);
        }
    });

    modal.addEventListener('click', function (event) {
        var bracketButton = event.target.closest('[data-front-post-customer-service-edit-bracket]');
        var leftText;
        var rightText;
        var currentValue;
        var start;
        var end;

        if (!bracketButton || !textarea) {
            return;
        }

        event.preventDefault();
        leftText = bracketButton.getAttribute('data-front-post-customer-service-edit-bracket-left') || '';
        rightText = bracketButton.getAttribute('data-front-post-customer-service-edit-bracket-right') || '';
        if (leftText === '' && rightText === '') {
            return;
        }

        currentValue = textarea.value;
        start = typeof textarea.selectionStart === 'number' ? textarea.selectionStart : currentValue.length;
        end = typeof textarea.selectionEnd === 'number' ? textarea.selectionEnd : start;
        textarea.value = currentValue.slice(0, start) + leftText + currentValue.slice(start, end) + rightText + currentValue.slice(end);
        textarea.focus();
        textarea.selectionStart = start + leftText.length;
        textarea.selectionEnd = end + leftText.length;
        resizeTextarea();
    });

    document.addEventListener('keydown', function (event) {
        if ((event.key === 'Escape' || event.key === 'Esc') && !modal.hidden) {
            setOpen(false);
        }
    });

    if (textarea) {
        textarea.addEventListener('input', resizeTextarea);
    }

    if (window.visualViewport) {
        window.visualViewport.addEventListener('resize', function () {
            if (!modal.hidden) {
                syncViewport();
            }
        });
        window.visualViewport.addEventListener('scroll', function () {
            if (!modal.hidden) {
                syncViewport();
            }
        });
    }

    form.addEventListener('submit', function (event) {
        var button = form.querySelector('[type="submit"]');
        var payload = new FormData(form);

        event.preventDefault();
        event.stopPropagation();
        setError('');

        if (button) {
            button.disabled = true;
        }

        fetch(endpoint, {
            method: 'POST',
            body: payload,
            credentials: 'same-origin'
        }).then(function (response) {
            return response.json();
        }).then(function (result) {
            if (!result || !result.success) {
                throw new Error((result && result.message) || '保存失败，请重试。');
            }

            showToast(result.message || '帖子资料已更新。', 'success');
            if (result.reload) {
                window.setTimeout(function () {
                    window.location.reload();
                }, 360);
            } else {
                setOpen(false);
            }
        }).catch(function (error) {
            setError(error.message || '保存失败，请重试。');
            showToast(error.message || '保存失败，请重试。', 'error');
        }).finally(function () {
            if (button) {
                button.disabled = false;
            }
        });
    });
}());

(function () {
    var notifyTimer = 0;

    window.notifyFrontPostModalMetaReady = function () {
        if (!window.parent || window.parent === window) {
            return;
        }

        try {
            window.parent.postMessage({
                type: 'front-post-modal-meta-ready',
                postId: '<?php echo e((string) ($post['id'] ?? '')); ?>'
            }, window.location.origin);
        } catch (error) {
        }
    };

    function scheduleFrontPostModalMetaNotify(delay) {
        window.setTimeout(function () {
            window.clearTimeout(notifyTimer);
            notifyTimer = window.setTimeout(window.notifyFrontPostModalMetaReady, 0);
        }, delay);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            scheduleFrontPostModalMetaNotify(0);
        }, { once: true });
    } else {
        scheduleFrontPostModalMetaNotify(0);
    }
    scheduleFrontPostModalMetaNotify(240);
    scheduleFrontPostModalMetaNotify(900);
}());

(function () {
    var likeButtons = document.querySelectorAll('[data-post-like]');

    if (!likeButtons.length || !window.fetch || !window.FormData) {
        return;
    }

    function showPostLikeToast(message, type) {
        if (window.AppUI && typeof window.AppUI.toast === 'function') {
            window.AppUI.toast(String(message || ''), type || 'info');
            return;
        }

        if (window.console && message) {
            window.console.warn(String(message));
        }
    }

    function syncPostLikeButtons(likeCount, liked) {
        Array.prototype.forEach.call(likeButtons, function (button) {
            var countNode = button.querySelector('[data-post-like-count]');

            if (countNode) {
                countNode.textContent = String(Math.max(0, parseInt(likeCount, 10) || 0));
            }

            button.classList.toggle('is-liked', !!liked);
            button.setAttribute('aria-pressed', liked ? 'true' : 'false');
            button.setAttribute('data-liked', liked ? '1' : '0');
        });

        if (typeof window.notifyFrontPostModalMetaReady === 'function') {
            window.notifyFrontPostModalMetaReady();
        }
    }

    Array.prototype.forEach.call(likeButtons, function (button) {
        button.addEventListener('click', function (event) {
            var apiUrl = button.getAttribute('data-api-url') || '';
            var token = button.getAttribute('data-token') || '';
            var postId = button.getAttribute('data-post-id') || '';
            var formData;

            event.preventDefault();

            if (!apiUrl || !token || !postId || button.classList.contains('is-loading')) {
                return;
            }

            formData = new window.FormData();
            formData.append('action', 'post.like');
            formData.append('_token', token);
            formData.append('post_id', postId);
            button.classList.add('is-loading');

            window.fetch(apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            }).then(function (response) {
                return response.text().then(function (text) {
                    try {
                        return JSON.parse(text);
                    } catch (error) {
                        throw new Error(text || '点赞请求返回异常。');
                    }
                });
            }).then(function (payload) {
                var data;

                if (!payload || payload.success !== true) {
                    throw new Error((payload && payload.message) || '点赞失败。');
                }

                data = payload.data || {};
                syncPostLikeButtons(data.like_count || 0, !!data.liked);
            }).catch(function (error) {
                showPostLikeToast(error.message || '点赞失败。', 'error');
            }).then(function () {
                button.classList.remove('is-loading');
            });
        });
    });
}());

(function () {
    var viewNodes = document.querySelectorAll('[data-post-view-count]');
    var postId = <?php echo (int) ($post['id'] ?? 0); ?>;
    var endpoint = <?php echo json_encode(public_url('api.php'), JSON_UNESCAPED_UNICODE); ?>;
    var token = <?php echo json_encode((string) ($postViewApiToken ?? ''), JSON_UNESCAPED_UNICODE); ?>;
    var pollStartedAt = Date.now();
    var pollInterval = 20000;

    if (!viewNodes.length || !postId || !endpoint || !token || !window.fetch || !window.URLSearchParams) {
        return;
    }

    function scheduleNextPoll() {
        if ((Date.now() - pollStartedAt) >= (31 * 60 * 1000)) {
            return;
        }

        window.setTimeout(runPoll, pollInterval);
    }

    function runPoll() {
        var payload = new URLSearchParams();

        payload.append('action', 'post.view_count');
        payload.append('_token', token);
        payload.append('post_id', String(postId));

        window.fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload.toString()
        }).then(function (response) {
            return response.json();
        }).then(function (result) {
            var nextCount;

            if (!result || result.success !== true || !result.data) {
                return;
            }

            nextCount = parseInt(result.data.display_view_count, 10);
            if (!isNaN(nextCount)) {
                Array.prototype.forEach.call(viewNodes, function (viewNode) {
                    viewNode.textContent = String(nextCount);
                });
                if (typeof window.notifyFrontPostModalMetaReady === 'function') {
                    window.notifyFrontPostModalMetaReady();
                }
            }
        }).catch(function () {
        }).then(function () {
            scheduleNextPoll();
        });
    }

    scheduleNextPoll();
}());
</script>
<?php echo \App\Core\View::make(app(), 'partials/front_bottom_nav', array('region' => $region, 'activePanel' => '', 'user' => $viewer)); ?>
