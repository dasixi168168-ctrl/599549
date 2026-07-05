<?php
$templatePath = app()->basePath('resources/defaults/home_editor_default.html');
$templateHtml = is_file($templatePath) ? (string) file_get_contents($templatePath) : '';
$currentRegion = isset($region) && $region === 'hongkong' ? 'hongkong' : 'macau';
$ignoreManagedDrawMaterial = !empty($ignoreManagedDrawMaterial);
$ignoreManagedDrawComponents = !empty($ignoreManagedDrawComponents);
$managedMaterialHtml = $ignoreManagedDrawMaterial ? '' : (string) site_setting('draws.material_html.' . $currentRegion, '');
$managedMaterialUpdatedAt = $ignoreManagedDrawMaterial ? '' : trim((string) site_setting('draws.material_updated_at.' . $currentRegion, ''));
$managedMaterialUpdatedBy = $ignoreManagedDrawMaterial ? '' : trim((string) site_setting('draws.material_updated_by.' . $currentRegion, ''));
$hasManagedMaterial = !$ignoreManagedDrawMaterial && ($managedMaterialUpdatedAt !== '' || $managedMaterialUpdatedBy !== '' || trim($managedMaterialHtml) !== '');
$currentUser = isset($user) && is_array($user) ? $user : current_user();
$customerServiceAgentEntryRemembered = (string) \App\Core\Session::get('customer_service_agent_entry', '0') === '1';
$customerServiceAgentNav = ((string) input('agent', '0') === '1' || $customerServiceAgentEntryRemembered)
    && (int) \App\Core\Session::get('customer_service_agent_id', 0) > 0;
$memberLabel = $currentUser ? '我的' : '登录';
$indexUrl = public_url('index.php');
$recordUrl = public_url('record.php');
$memberUrl = public_url('member.php') . '?region=' . urlencode($currentRegion);
$forecastUrl = public_url('forecast.php') . '?region=macau';
$serviceUrl = public_url('service.php') . '?region=' . urlencode($currentRegion);
if ($customerServiceAgentNav) {
    $memberLabel = '管理';
    $indexUrl .= '?agent=1';
    $recordUrl .= '?agent=1';
    $forecastUrl .= '&agent=1';
    $serviceUrl .= '&agent=1';
    $memberUrl = public_url('service.php') . '?agent=1&panel=manage&region=' . urlencode($currentRegion);
} elseif (!$currentUser) {
    $memberUrl .= '&mode=login';
}
$calendarNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Shanghai'));
$weekdayNames = array('星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六');
$lunarInfo = array(
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
);
$lunarLeapMonth = static function ($year) use ($lunarInfo) {
    return $lunarInfo[$year - 1900] & 0xf;
};
$lunarLeapDays = static function ($year) use ($lunarInfo, $lunarLeapMonth) {
    return $lunarLeapMonth($year) ? (($lunarInfo[$year - 1900] & 0x10000) ? 30 : 29) : 0;
};
$lunarMonthDays = static function ($year, $month) use ($lunarInfo) {
    return ($lunarInfo[$year - 1900] & (0x10000 >> $month)) ? 30 : 29;
};
$lunarYearDays = static function ($year) use ($lunarInfo, $lunarLeapDays) {
    $sum = 348;
    for ($mask = 0x8000; $mask > 0x8; $mask >>= 1) {
        $sum += ($lunarInfo[$year - 1900] & $mask) ? 1 : 0;
    }

    return $sum + $lunarLeapDays($year);
};
$formatLunarDate = static function (DateTimeImmutable $date) use ($lunarLeapMonth, $lunarLeapDays, $lunarMonthDays, $lunarYearDays) {
    $base = new DateTimeImmutable('1900-01-31', new DateTimeZone('Asia/Shanghai'));
    $offset = (int) floor(($date->setTime(0, 0)->getTimestamp() - $base->getTimestamp()) / 86400);
    $monthNames = array('正', '二', '三', '四', '五', '六', '七', '八', '九', '十', '冬', '腊');
    $dayNames = array('初十', '二十', '三十');

    if ($offset < 0) {
        return '';
    }

    for ($year = 1900; $year < 2050 && $offset > 0; $year++) {
        $temp = $lunarYearDays($year);
        $offset -= $temp;
    }
    if ($offset < 0) {
        $offset += $temp;
        $year--;
    }

    $leap = $lunarLeapMonth($year);
    $isLeap = false;
    for ($month = 1; $month < 13 && $offset > 0; $month++) {
        if ($leap > 0 && $month === ($leap + 1) && !$isLeap) {
            $month--;
            $isLeap = true;
            $temp = $lunarLeapDays($year);
        } else {
            $temp = $lunarMonthDays($year, $month);
        }
        if ($isLeap && $month === ($leap + 1)) {
            $isLeap = false;
        }
        $offset -= $temp;
    }
    if ($offset === 0 && $leap > 0 && $month === ($leap + 1)) {
        if ($isLeap) {
            $isLeap = false;
        } else {
            $isLeap = true;
            $month--;
        }
    }
    if ($offset < 0) {
        $offset += $temp;
        $month--;
    }

    $day = $offset + 1;
    $monthText = ($isLeap ? '闰' : '') . ($monthNames[$month - 1] ?? '') . '月';
    if ($day === 10 || $day === 20 || $day === 30) {
        $dayText = $dayNames[(int) ($day / 10) - 1] ?? '';
    } else {
        $prefix = array('初', '十', '廿', '卅')[(int) floor($day / 10)] ?? '';
        $suffix = array('一', '二', '三', '四', '五', '六', '七', '八', '九')[(($day - 1) % 10)] ?? '';
        $dayText = $prefix . $suffix;
    }

    return $monthText . $dayText;
};
$resolveLunarConflict = static function (DateTimeImmutable $date) {
    $branches = array('子', '丑', '寅', '卯', '辰', '巳', '午', '未', '申', '酉', '戌', '亥');
    $zodiacByBranch = array('子' => '鼠', '丑' => '牛', '寅' => '虎', '卯' => '兔', '辰' => '龙', '巳' => '蛇', '午' => '马', '未' => '羊', '申' => '猴', '酉' => '鸡', '戌' => '狗', '亥' => '猪');
    $shaByBranch = array('子' => '南', '丑' => '东', '寅' => '北', '卯' => '西', '辰' => '南', '巳' => '东', '午' => '北', '未' => '西', '申' => '南', '酉' => '东', '戌' => '北', '亥' => '西');
    $anchor = new DateTimeImmutable('2026-03-21', new DateTimeZone('Asia/Shanghai'));
    $diffDays = (int) round(($date->setTime(0, 0)->getTimestamp() - $anchor->getTimestamp()) / 86400);
    $dayIndex = (($diffDays + 6) % 12 + 12) % 12;
    $dayBranch = $branches[$dayIndex];
    $chongBranch = $branches[($dayIndex + 6) % 12];

    return array(
        'sha' => $shaByBranch[$dayBranch] ?? '--',
        'chong' => $zodiacByBranch[$chongBranch] ?? '--',
    );
};
$lunarConflict = $resolveLunarConflict($calendarNow);
$calendarSnapshot = array(
    'live-time' => $calendarNow->format('H:i:s'),
    'live-weekday' => $weekdayNames[(int) $calendarNow->format('w')],
    'solar-date' => $calendarNow->format('n') . '月' . $calendarNow->format('j') . '日',
    'solar-year' => $calendarNow->format('Y') . '年',
    'lunar-date' => $formatLunarDate($calendarNow),
    'lunar-sha' => '煞:' . $lunarConflict['sha'],
    'lunar-chong' => '冲肖:' . $lunarConflict['chong'],
);
$replaceNodeText = static function ($html, $id, $text) {
    return (string) preg_replace_callback(
        '/(<[^>]+id="' . preg_quote($id, '/') . '"[^>]*>)(.*?)(<\/[^>]+>)/su',
        static function ($matches) use ($text) {
            return $matches[1] . e($text) . $matches[3];
        },
        (string) $html,
        1
    );
};
$replaceLimitedNodeText = static function ($html, $className, array $texts) {
    $index = 0;

    return (string) preg_replace_callback(
        '/(<[^>]+class="[^"]*\b' . preg_quote($className, '/') . '\b[^"]*"[^>]*>)(.*?)(<\/[^>]+>)/su',
        static function ($matches) use (&$index, $texts) {
            if (!array_key_exists($index, $texts)) {
                return $matches[0];
            }

            $text = $texts[$index];
            $index++;

            return $matches[1] . e($text) . $matches[3];
        },
        (string) $html,
        count($texts)
    );
};
$replaceLimitedNodeClass = static function ($html, $className, array $classes) {
    $index = 0;

    return (string) preg_replace_callback(
        '/<(?P<tag>[a-z0-9]+)(?P<before>[^>]*class=")(?P<class>[^"]*\b' . preg_quote($className, '/') . '\b[^"]*)("(?P<after>[^>]*)>)/sui',
        static function ($matches) use (&$index, $classes) {
            if (!array_key_exists($index, $classes)) {
                return $matches[0];
            }

            $classParts = preg_split('/\s+/', trim((string) $matches['class']));
            $classParts = array_values(array_filter($classParts, static function ($part) {
                return !in_array($part, array('red', 'blue', 'green'), true);
            }));
            $classParts[] = $classes[$index];
            $index++;

            return '<' . $matches['tag'] . $matches['before'] . e(implode(' ', $classParts)) . '"' . $matches['after'] . '>';
        },
        (string) $html,
        count($classes)
    );
};
$drawWaveClass = static function ($number) {
    $number = (int) $number;
    if (in_array($number, array(1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46), true)) {
        return 'red';
    }
    if (in_array($number, array(3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48), true)) {
        return 'blue';
    }

    return 'green';
};
$findDrawGroup = static function (array $groups, $number, $fallback = '--') {
    $number = (int) $number;
    foreach ($groups as $name => $values) {
        if (in_array($number, $values, true)) {
            return $name;
        }
    }

    return $fallback;
};
$predictionService = app()->prediction();
$resolveDrawZodiac = static function ($number, $drawDate) use ($predictionService) {
    $zodiac = $predictionService->drawZodiacByNumber($number, $drawDate);

    return $zodiac !== '' ? $zodiac : '--';
};
$formatDrawIssue = static function (array $draw = null) use ($currentRegion) {
    $issueNo = trim((string) ($draw['issue_no'] ?? ''));
    $regionLabel = $currentRegion === 'hongkong' ? '香港' : '澳门';
    if ($issueNo === '' || !ctype_digit($issueNo)) {
        return '--';
    }
    $tail = substr($issueNo, -3);

    return $regionLabel . ' ' . str_pad($tail, 3, '0', STR_PAD_LEFT) . '期';
};
$formatDrawOpenTime = static function (array $draw = null, array $issue = null) {
    $value = trim((string) ($draw['next_open_time'] ?? $draw['open_time'] ?? $draw['draw_date'] ?? ''));
    if (is_array($issue)
        && trim((string) ($issue['planned_open_at'] ?? '')) !== ''
        && trim((string) ($issue['status'] ?? '')) !== 'opened'
    ) {
        $value = trim((string) $issue['planned_open_at']);
    }

    return '下期开奖：' . ($value !== '' ? substr($value, 0, 16) : '--');
};
$applyDrawSnapshot = static function ($html, array $draw = null, array $issue = null) use (
    $replaceNodeText,
    $replaceLimitedNodeText,
    $replaceLimitedNodeClass,
    $drawWaveClass,
    $findDrawGroup,
    $resolveDrawZodiac,
    $formatDrawIssue,
    $formatDrawOpenTime
) {
    if (!is_array($draw)) {
        return (string) $html;
    }

    $numbers = array_slice(array_map('intval', (array) ($draw['numbers'] ?? array())), 0, 6);
    while (count($numbers) < 6) {
        $numbers[] = 0;
    }
    $numbers[] = (int) ($draw['special_number'] ?? 0);
    $drawDate = trim((string) ($draw['draw_date'] ?? ''));

    $fivePhaseGroups = array(
        '金' => array(2, 3, 10, 11, 24, 25, 32, 33, 40, 41),
        '木' => array(6, 7, 14, 15, 22, 23, 36, 37, 44, 45),
        '水' => array(12, 13, 20, 21, 28, 29, 42, 43),
        '火' => array(1, 8, 9, 16, 17, 30, 31, 38, 39, 46, 47),
        '土' => array(4, 5, 18, 19, 26, 27, 34, 35, 48, 49),
    );

    $classes = array_map($drawWaveClass, $numbers);
    $codes = array_map(static function ($number) {
        return $number > 0 ? str_pad((string) $number, 2, '0', STR_PAD_LEFT) : '--';
    }, $numbers);
    $zodiacs = array_map(static function ($number) use ($resolveDrawZodiac, $drawDate) {
        return $number > 0 ? $resolveDrawZodiac($number, $drawDate) : '--';
    }, $numbers);
    $specialNumber = (int) end($numbers);
    $digitSum = array_sum(array_map('intval', str_split((string) abs($specialNumber))));

    $html = $replaceNodeText($html, 'hero-result-period', $formatDrawIssue($draw));
    $html = $replaceNodeText($html, 'hero-result-open-time', $formatDrawOpenTime($draw, $issue));
    $html = $replaceLimitedNodeClass($html, 'result-jl-code', $classes);
    $html = $replaceLimitedNodeClass($html, 'hero-ball-zodiac', $classes);
    $html = $replaceLimitedNodeText($html, 'result-jl-code', $codes);
    $html = $replaceLimitedNodeText($html, 'hero-ball-zodiac', $zodiacs);
    $html = $replaceNodeText($html, 'hero-result-zodiac', $specialNumber > 0 ? $resolveDrawZodiac($specialNumber, $drawDate) : '--');
    $html = $replaceNodeText($html, 'hero-result-odd-even', $specialNumber > 0 ? ($digitSum % 2 === 0 ? '合数双' : '合数单') : '--');
    $html = $replaceNodeText($html, 'hero-result-five-phase', $specialNumber > 0 ? $findDrawGroup($fivePhaseGroups, $specialNumber, '--') : '--');

    return $html;
};
$stripLegacyHomeData = static function ($html) {
    return (string) preg_replace('/\s*<script id="legacy-home-data"[\s\S]*?<\/script>\s*$/u', '', (string) $html, 1);
};
$frontHomeIconSvg = static function ($name) {
    $icons = array(
        'dice-three' => '<svg viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3" ry="3" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="8.5" cy="8.5" r="1.45"/><circle cx="12" cy="12" r="1.45"/><circle cx="15.5" cy="15.5" r="1.45"/></svg>',
        'download' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M11 4h2v8.1l2.9-2.9 1.4 1.42L12 15.9l-5.3-5.28L8.1 9.2l2.9 2.9V4Zm-5 14h12v2H6v-2Z"/></svg>',
        'clock-rotate-left' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 5a7 7 0 1 1-6.3 10H3.55A9 9 0 1 0 5.1 6.7L3.5 5.1V10h4.9L6.55 8.15A6.96 6.96 0 0 1 12 5Zm-1 3h2v4.2l3 1.8-1 1.72-4-2.38V8Z"/></svg>',
        'bullhorn' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M20 5v14h-2.2l-8.1-3H6a3 3 0 0 1-3-3v-2a3 3 0 0 1 3-3h3.7l8.1-3H20ZM6 10a1 1 0 0 0-1 1v2a1 1 0 0 0 1 1h3V10H6Zm11.8-2.7L11 9.82v4.36l6.8 2.52V7.3ZM7 16h2.1l1.5 4H8.45L7 16Z"/></svg>',
        'crown' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="m3 7 4.7 4.1L12 4l4.3 7.1L21 7l-2 11H5L3 7Zm4 9h10l.8-4.5-2.2 1.9L12 7.5l-3.6 5.9-2.2-1.9L7 16Z"/></svg>',
        'copyright' => '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20Zm0 2a8 8 0 1 1 0 16 8 8 0 0 1 0-16Zm.2 4.2c1.55 0 2.8.7 3.55 1.9l-1.75 1a2.03 2.03 0 0 0-1.78-.98A2.65 2.65 0 0 0 9.6 12a2.66 2.66 0 0 0 2.62 2.9c.8 0 1.42-.32 1.86-1l1.72.98c-.76 1.24-2.04 1.98-3.6 1.98A4.68 4.68 0 0 1 7.55 12a4.68 4.68 0 0 1 4.65-3.8Z"/></svg>',
    );
    $key = isset($icons[$name]) ? $name : 'crown';

    return '<i class="front-fa-icon front-icon-' . e($key) . '" aria-hidden="true">' . $icons[$key] . '</i>';
};
$replaceFrontHomeFontAwesomeIcons = static function ($html) use ($frontHomeIconSvg) {
    $map = array(
        'fa-dice-three' => 'dice-three',
        'fa-download' => 'download',
        'fa-clock-rotate-left' => 'clock-rotate-left',
        'fa-bullhorn' => 'bullhorn',
        'fa-crown' => 'crown',
        'fa-copyright' => 'copyright',
    );

    return (string) preg_replace_callback(
        '/<i\b([^>]*)class="([^"]*\bfa-(?:solid|regular|brands)\b[^"]*)"([^>]*)>\s*<\/i>/su',
        static function ($matches) use ($map, $frontHomeIconSvg) {
            $classes = preg_split('/\s+/', trim((string) $matches[2]));
            foreach ($classes as $className) {
                if (isset($map[$className])) {
                    return $frontHomeIconSvg($map[$className]);
                }
            }

            return $matches[0];
        },
        (string) $html
    );
};
$applyFrontLinks = static function ($html) use ($indexUrl, $recordUrl, $forecastUrl, $serviceUrl, $memberUrl, $currentRegion, $memberLabel) {
    $html = (string) $html;

    if ($html === '') {
        return '';
    }

    if ($currentRegion === 'hongkong') {
        $html = str_replace(
            'href="./index.php" class="bottom-nav-link is-active"',
            'href="./index.php" class="bottom-nav-link"',
            $html
        );
        $html = str_replace(
            'href="./record.php" class="bottom-nav-link"',
            'href="./record.php" class="bottom-nav-link is-active"',
            $html
        );
    }

    $html = str_replace('./index.php"', $indexUrl . '"', $html);
    $html = str_replace('./record.php"', $recordUrl . '"', $html);
    $html = str_replace('./forecast.php"', $forecastUrl . '"', $html);
    $html = str_replace('./service.php"', $serviceUrl . '"', $html);
    $html = str_replace('./member.php"', $memberUrl . '"', $html);

    return (string) preg_replace(
        '/(<a href="[^"]*" class="bottom-nav-link bottom-nav-login">\s*<i class="fa-solid fa-circle-user"><\/i>\s*<span>)(?:登录|我的|管理)(<\/span>\s*<\/a>)/u',
        '$1' . $memberLabel . '$2',
        $html,
        1
    );
};
$extractDefaultMaterial = static function ($html) {
    $html = (string) $html;

    if (preg_match('/(<section id="section-home"[\s\S]*?)(?=\s*<nav class="bottom-float-nav"[\s\S]*?<\/nav>)/u', $html, $matches)) {
        return trim((string) ($matches[1] ?? ''));
    }

    return trim($html);
};

$templateHtml = $stripLegacyHomeData($templateHtml);
$templateHtml = $applyFrontLinks($templateHtml);
foreach ($calendarSnapshot as $nodeId => $nodeValue) {
    $templateHtml = $replaceNodeText($templateHtml, $nodeId, $nodeValue);
}

$defaultMaterialHtml = $extractDefaultMaterial($templateHtml);
$managedCurrentIssue = app()->admins()->managedIssuePrefixSnapshotByRegion($currentRegion);
$incrementManagedDrawAdViews = !isset($incrementManagedDrawAdViews) || (bool) $incrementManagedDrawAdViews;

$bodyHtml = $hasManagedMaterial ? trim($managedMaterialHtml) : $defaultMaterialHtml;

$bodyHtml = $applyFrontLinks($extractDefaultMaterial($stripLegacyHomeData($bodyHtml)));
$bodyHtml = app()->admins()->stripManagedDrawHeroCopy($bodyHtml);
$bodyHtml = app()->admins()->moveManagedDrawLiveBlockBelowHomeSection($bodyHtml);
$bodyHtml = app()->admins()->syncManagedDrawExpertLinks($currentRegion, $bodyHtml, $incrementManagedDrawAdViews);
$bodyHtml = app()->admins()->syncManagedDrawAdLinks($bodyHtml, $currentRegion);
$bodyHtml = $replaceFrontHomeFontAwesomeIcons($bodyHtml);

foreach ($calendarSnapshot as $nodeId => $nodeValue) {
    $bodyHtml = $replaceNodeText($bodyHtml, $nodeId, $nodeValue);
}
$bodyHtml = $applyDrawSnapshot(
    $bodyHtml,
    isset($latestDraw) && is_array($latestDraw) ? $latestDraw : null,
    is_array($managedCurrentIssue) ? $managedCurrentIssue : null
);

$homePayload = array(
    'region' => $currentRegion,
    'draw' => isset($latestDraw) && is_array($latestDraw) ? $latestDraw : null,
    'current_issue' => is_array($managedCurrentIssue) ? $managedCurrentIssue : null,
    'api_url' => public_url('api.php'),
    'api_token' => csrf_token('api'),
);
?>
<?php echo \App\Core\View::make(app(), 'partials/front_top_bar', array('region' => $currentRegion, 'ignoreManagedTopBar' => $ignoreManagedDrawComponents)); ?>
<?php if ($bodyHtml !== ''): ?>
    <?php echo $bodyHtml; ?>
<?php else: ?>
    <section class="max-w-7xl mx-auto px-[14px] pt-[10px] pb-0">
        <div class="data-frame">
            <p class="text-center text-lg font-medium text-red-500">首页模板缺失，请检查 `resources/defaults/home_editor_default.html`。</p>
        </div>
    </section>
<?php endif; ?>
<?php
echo \App\Core\View::make(
    app(),
    'partials/front_bottom_nav',
    array(
        'region' => $currentRegion,
        'activePanel' => '',
        'user' => $currentUser,
        'customerServiceAgentNav' => $customerServiceAgentNav,
        'ignoreManagedBottomNav' => $ignoreManagedDrawComponents,
    )
);
?>
<script id="legacy-home-data" type="application/json"><?php echo json_encode($homePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
