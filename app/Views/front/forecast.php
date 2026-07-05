<?php
$regionName = $region === 'hongkong' ? '香港' : '澳门';
$forecastResultRegionMark = $region === 'hongkong' ? '港' : '澳';
$forecastCurrentIssueValue = '';
if (
    isset($livePayload)
    && is_array($livePayload)
    && isset($livePayload['current_issue'])
    && is_array($livePayload['current_issue'])
) {
    $forecastCurrentIssueValue = trim(
        (string) ($livePayload['current_issue']['issue_no'] ?? '')
    );
}
$forecastResultIssueValue = $forecastCurrentIssueValue !== ''
    ? $forecastCurrentIssueValue
    : ($currentPrediction && trim((string) $currentPrediction['issue']) !== ''
        ? trim((string) $currentPrediction['issue'])
        : '--');
if (
    $forecastResultIssueValue !== '--'
    && preg_match('/^\d{5,}$/', $forecastResultIssueValue) === 1
) {
    $forecastResultIssueValue = ltrim(substr($forecastResultIssueValue, 4), '0');
    if ($forecastResultIssueValue === '') {
        $forecastResultIssueValue = '0';
    }
}
$forecastResultIssueText = $forecastResultIssueValue . '期';
$historyPredictions = array_slice(isset($recentPredictions) && is_array($recentPredictions) ? $recentPredictions : array(), 1);
$forecastResultIdiom = trim((string) ($forecastResultIdiom ?? ''));
$forecastResultBlessing = trim((string) ($forecastResultBlessing ?? ''));
$forecastStats = isset($forecastStats) && is_array($forecastStats) ? $forecastStats : array();
$forecastCustomerServiceAgentViewer = !empty($customerServiceAgentViewer);
$forecastPreviousParticipants = (int) ($forecastStats['previous_participants'] ?? 0);
$forecastPreviousWinners = (int) ($forecastStats['previous_winners'] ?? 0);
$forecastTodayParticipants = (int) ($forecastStats['today_participants'] ?? 0);
$forecastPricingSummary = isset($forecastPricingSummary) && is_array($forecastPricingSummary) ? $forecastPricingSummary : array('selected_count' => 0, 'items' => array(), 'total_text' => '0', 'discount_label' => '无优惠');
$forecastPricingSettings = app()->admins()->forecastPricingSettings();
$forecastPricingDiscounts = array();
foreach ((array) ($forecastPricingSettings['discounts'] ?? array()) as $forecastDiscountCount => $forecastDiscountPercent) {
    $forecastPricingDiscounts[(string) $forecastDiscountCount] = max(1, min(100, (int) $forecastDiscountPercent));
}
$forecastDiscountLabel = trim((string) ($forecastPricingSummary['discount_label'] ?? ''));
$forecastDiscountLabel = (string) preg_replace_callback('/(\d+(?:\.\d+)?)%/u', static function (array $matches) {
    $discountFold = ((float) $matches[1]) / 10;
    $discountFoldText = rtrim(rtrim(number_format($discountFold, 2, '.', ''), '0'), '.');

    return $discountFoldText . '折';
}, $forecastDiscountLabel);
$forecastResultEmojiPool = array('🎯', '✨', '🍀', '🏆', '💰', '🔥', '🌟', '🎉', '💎', '🚀');
shuffle($forecastResultEmojiPool);
$forecastResultIdiomEmoji = $forecastResultEmojiPool[0];
$forecastResultBlessingEmoji = $forecastResultEmojiPool[1];
$forecastFilterOptions = isset($forecastFilterOptions) && is_array($forecastFilterOptions) ? $forecastFilterOptions : array(
    'zodiac' => array(
        'placeholder' => '生肖类型',
        'options' => array(
            array('value' => 'zodiac_1', 'label' => '一肖'),
            array('value' => 'zodiac_2', 'label' => '二肖'),
            array('value' => 'zodiac_3', 'label' => '三肖'),
            array('value' => 'zodiac_4', 'label' => '四肖'),
            array('value' => 'zodiac_5', 'label' => '五肖'),
            array('value' => 'zodiac_6', 'label' => '六肖'),
            array('value' => 'zodiac_7', 'label' => '七肖'),
            array('value' => 'zodiac_8', 'label' => '八肖'),
            array('value' => 'zodiac_9', 'label' => '九肖'),
        ),
    ),
    'number' => array(
        'placeholder' => '号码类型',
        'options' => array(
            array('value' => 'number_1', 'label' => '①码'),
            array('value' => 'number_2', 'label' => '②码'),
            array('value' => 'number_3', 'label' => '③码'),
            array('value' => 'number_4', 'label' => '④码'),
            array('value' => 'number_5', 'label' => '⑤码'),
            array('value' => 'number_6', 'label' => '⑥码'),
            array('value' => 'number_7', 'label' => '⑦码'),
            array('value' => 'number_8', 'label' => '⑧码'),
            array('value' => 'number_9', 'label' => '⑨码'),
            array('value' => 'number_10', 'label' => '⑩码'),
            array('value' => 'number_12', 'label' => '12码'),
            array('value' => 'number_14', 'label' => '14码'),
            array('value' => 'number_16', 'label' => '16码'),
            array('value' => 'number_18', 'label' => '18码'),
            array('value' => 'number_20', 'label' => '20码'),
            array('value' => 'number_24', 'label' => '24码'),
            array('value' => 'number_30', 'label' => '30码'),
        ),
    ),
    'pingte' => array(
        'placeholder' => '平码类型',
        'options' => array(
            array('value' => 'pt_2_2_group_1', 'label' => '1组2中2'),
            array('value' => 'pt_2_2_group_2', 'label' => '2组2中2'),
            array('value' => 'pt_2_2_group_3', 'label' => '3组2中2'),
            array('value' => 'pt_2_2_group_4', 'label' => '4组2中2'),
            array('value' => 'pt_2_2_group_6', 'label' => '6组2中2'),
            array('value' => 'pt_2_2_group_8', 'label' => '8组2中2'),
            array('value' => 'pt_2_2_combo_5', 'label' => '5码复式'),
            array('value' => 'pt_2_2_combo_6', 'label' => '6码复式'),
            array('value' => 'pt_2_2_combo_7', 'label' => '7码复式'),
            array('value' => 'pt_2_2_combo_8', 'label' => '8码复式'),
            array('value' => 'pt_2_2_combo_9', 'label' => '9码复式'),
            array('value' => 'pt_3_3_group_1', 'label' => '1组3中3'),
            array('value' => 'pt_3_3_group_2', 'label' => '2组3中3'),
            array('value' => 'pt_3_3_group_3', 'label' => '3组3中3'),
            array('value' => 'pt_3_3_group_4', 'label' => '4组3中3'),
            array('value' => 'pt_3_3_group_6', 'label' => '6组3中3'),
            array('value' => 'pt_3_3_group_8', 'label' => '8组3中3'),
        ),
    ),
    'other' => array(
        'placeholder' => '其他类型',
        'options' => array(
            array('value' => 'odd_even', 'label' => '单双'),
            array('value' => 'wave', 'label' => '波色'),
            array('value' => 'big_small', 'label' => '大小'),
            array('value' => 'head', 'label' => '头数'),
            array('value' => 'tail', 'label' => '尾数'),
            array('value' => 'pt_zodiac_1', 'label' => '平特一肖'),
            array('value' => 'pt_zodiac_2', 'label' => '平特二肖'),
            array('value' => 'pt_zodiac_3', 'label' => '平特三肖'),
            array('value' => 'pt_zodiac_4', 'label' => '平特四肖'),
            array('value' => 'pt_zodiac_5', 'label' => '平特五肖'),
        ),
    ),
);
$forecastCurrentNumbers = $currentPrediction && !empty($currentPrediction['numbers']) && is_array($currentPrediction['numbers'])
    ? array_values(array_map('intval', $currentPrediction['numbers']))
    : array();
$forecastDisplayPayloads = $currentPrediction && !empty($currentPrediction['display_payloads']) && is_array($currentPrediction['display_payloads'])
    ? $currentPrediction['display_payloads']
    : array();
$forecastFormatNumbers = static function (array $numbers) {
    $formattedNumbers = array();
    foreach ($numbers as $number) {
        $formattedNumbers[] = str_pad((string) ((int) $number), 2, '0', STR_PAD_LEFT);
    }

    return implode('、', $formattedNumbers);
};
$forecastUniqueLabels = static function (array $labels) {
    $uniqueLabels = array();
    foreach ($labels as $label) {
        $label = trim((string) $label);
        if ($label !== '' && !in_array($label, $uniqueLabels, true)) {
            $uniqueLabels[] = $label;
        }
    }

    return $uniqueLabels;
};
$forecastZodiacMap = array(
    '马' => array(1, 13, 25, 37, 49),
    '蛇' => array(2, 14, 26, 38),
    '龙' => array(3, 15, 27, 39),
    '兔' => array(4, 16, 28, 40),
    '虎' => array(5, 17, 29, 41),
    '牛' => array(6, 18, 30, 42),
    '鼠' => array(7, 19, 31, 43),
    '猪' => array(8, 20, 32, 44),
    '狗' => array(9, 21, 33, 45),
    '鸡' => array(10, 22, 34, 46),
    '猴' => array(11, 23, 35, 47),
    '羊' => array(12, 24, 36, 48),
);
$forecastWaveMap = array(
    '红波' => array(1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46),
    '蓝波' => array(3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48),
    '绿波' => array(5, 6, 11, 16, 17, 21, 22, 27, 28, 32, 33, 38, 39, 43, 44, 49),
);
$forecastNumberToZodiac = array();
foreach ($forecastZodiacMap as $forecastZodiacLabel => $forecastZodiacNumbers) {
    foreach ($forecastZodiacNumbers as $forecastZodiacNumber) {
        $forecastNumberToZodiac[(int) $forecastZodiacNumber] = $forecastZodiacLabel;
    }
}
$forecastNumberToWave = array();
foreach ($forecastWaveMap as $forecastWaveLabel => $forecastWaveNumbers) {
    foreach ($forecastWaveNumbers as $forecastWaveNumber) {
        $forecastNumberToWave[(int) $forecastWaveNumber] = $forecastWaveLabel;
    }
}
$forecastOptionLabel = static function (array $optionGroup, $selectedValue) {
    $selectedValue = trim((string) $selectedValue);
    if ($selectedValue === '') {
        return '';
    }

    foreach ((array) ($optionGroup['options'] ?? array()) as $option) {
        if ((string) ($option['value'] ?? '') === $selectedValue) {
            return trim((string) ($option['label'] ?? ''));
        }
    }

    return '';
};
$forecastEscape = static function ($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
};
$forecastFormatDisplayNumber = static function ($number) {
    return str_pad((string) ((int) $number), 2, '0', STR_PAD_LEFT);
};
$forecastResolveWaveClass = static function ($number) use ($forecastNumberToWave) {
    $waveLabel = $forecastNumberToWave[(int) $number] ?? '';

    if ($waveLabel === '红波') {
        return 'is-red';
    }

    if ($waveLabel === '蓝波') {
        return 'is-blue';
    }

    return 'is-green';
};
$forecastRenderTypeChip = static function ($label) use ($forecastEscape) {
    $label = trim((string) $label);
    if ($label === '') {
        return '';
    }

    return '<span class="forecast-result-type-chip">' . $forecastEscape($label . ':') . '</span>';
};
$forecastRenderZodiacChips = static function (array $zodiacs) use ($forecastEscape) {
    $html = array();
    foreach ($zodiacs as $zodiac) {
        $zodiac = trim((string) $zodiac);
        if ($zodiac !== '') {
            $html[] = '<span class="forecast-result-zodiac-chip">' . $forecastEscape($zodiac) . '</span>';
        }
    }

    return $html;
};
$forecastRenderNumberChips = static function (array $numbers) use ($forecastEscape, $forecastResolveWaveClass) {
    $html = array();
    foreach ($numbers as $number) {
        $number = (int) $number;
        $html[] = '<span class="forecast-result-number-chip ' . $forecastResolveWaveClass($number) . '">' . $forecastEscape(str_pad((string) $number, 2, '0', STR_PAD_LEFT)) . '</span>';
    }

    return $html;
};
$forecastRenderOtherChips = static function (array $values) use ($forecastEscape) {
    $html = array();
    foreach ($values as $value) {
        $value = trim((string) $value);
        if ($value !== '') {
            $html[] = '<span class="forecast-result-other-chip">' . $forecastEscape($value) . '</span>';
        }
    }

    return $html;
};
$forecastOtherDisplayLimit = static function ($selectedValue) {
    if (preg_match('/^pt_zodiac_(\d+)$/', (string) $selectedValue, $matches)) {
        return max(0, min(5, (int) $matches[1]));
    }

    switch ((string) $selectedValue) {
        case 'odd_even':
        case 'big_small':
            return 1;
        case 'wave':
            return 2;
        case 'head':
            return 3;
        case 'tail':
            return 5;
        default:
            return 0;
    }
};
$forecastRenderPingteNumberInline = static function (array $numbers, $separator) use ($forecastEscape, $forecastFormatDisplayNumber, $forecastResolveWaveClass) {
    $parts = array();
    foreach ($numbers as $number) {
        $number = (int) $number;
        $parts[] = '<span class="forecast-result-pingte-number ' . $forecastResolveWaveClass($number) . '">' . $forecastEscape($forecastFormatDisplayNumber($number)) . '</span>';
    }

    return implode('<span class="forecast-result-pingte-separator">' . $forecastEscape((string) $separator) . '</span>', $parts);
};
$forecastRenderPingteGroupTexts = static function (array $groups) use ($forecastRenderPingteNumberInline) {
    $html = array();
    foreach ($groups as $groupItems) {
        $groupItems = array_values(array_map('intval', (array) $groupItems));
        if (!empty($groupItems)) {
            $groupSizeClass = ' is-size-' . (string) min(6, max(1, count($groupItems)));
            $html[] = '<span class="forecast-result-pingte-text is-group' . $groupSizeClass . '"><span class="forecast-result-pingte-bracket">【</span>' . $forecastRenderPingteNumberInline($groupItems, '-') . '<span class="forecast-result-pingte-bracket">】</span></span>';
        }
    }

    return $html;
};
$forecastRenderPingteComboText = static function (array $numbers) use ($forecastRenderPingteNumberInline) {
    $numbers = array_values(array_map('intval', $numbers));
    if (empty($numbers)) {
        return '';
    }

    return '<span class="forecast-result-pingte-text is-combo"><span class="forecast-result-pingte-bracket">【</span>' . $forecastRenderPingteNumberInline($numbers, '-') . '<span class="forecast-result-pingte-bracket">】</span></span>';
};
$forecastHasGeneratedFilters = false;
foreach ($currentFilters as $currentFilterValue) {
    if (trim((string) $currentFilterValue) !== '') {
        $forecastHasGeneratedFilters = true;
        break;
    }
}
$forecastBaseConfidence = $currentPrediction ? (float) $currentPrediction['confidence'] : 0.0;
$forecastLineConfidences = $currentPrediction && !empty($currentPrediction['line_confidences']) && is_array($currentPrediction['line_confidences'])
    ? $currentPrediction['line_confidences']
    : array();
$forecastResolveResultPayload = static function ($typeKey, $selectedValue, array $numbers) use (
    $forecastCurrentNumbers,
    $forecastUniqueLabels,
    $forecastNumberToZodiac,
    $forecastNumberToWave,
    $forecastOtherDisplayLimit
) {
    $selectedValue = trim((string) $selectedValue);
    if ($selectedValue === '' || empty($numbers) || empty($forecastCurrentNumbers)) {
        return array(
            'kind' => $typeKey,
            'values' => array(),
            'groups' => array(),
        );
    }

    if ($typeKey === 'zodiac') {
        $selectedCount = 0;
        if (preg_match('/^zodiac_(\d+)$/', $selectedValue, $matches)) {
            $selectedCount = max(0, min(9, (int) $matches[1]));
        }
        $zodiacResults = array();
        foreach ($numbers as $number) {
            $zodiacResults[] = $forecastNumberToZodiac[(int) $number] ?? '';
        }
        $zodiacResults = $forecastUniqueLabels($zodiacResults);
        if ($selectedCount > 0) {
            $zodiacResults = array_slice($zodiacResults, 0, $selectedCount);
        }

        return array(
            'kind' => 'zodiac',
            'values' => $zodiacResults,
            'groups' => array(),
        );
    }

    if ($typeKey === 'number') {
        if (preg_match('/^number_(\d+)$/', $selectedValue, $matches)) {
            return array(
                'kind' => 'number',
                'values' => array_slice($numbers, 0, max(0, min(49, (int) $matches[1]))),
                'groups' => array(),
            );
        }

        return array(
            'kind' => 'number',
            'values' => array(),
            'groups' => array(),
        );
    }

    if ($typeKey === 'pingte') {
        $hotNumbers = array();
        foreach ($numbers as $number) {
            $number = (int) $number;
            if ($number >= 1 && $number <= 49 && !in_array($number, $hotNumbers, true)) {
                $hotNumbers[] = $number;
            }
            if (count($hotNumbers) >= 10) {
                break;
            }
        }
        $buildPingteHotGroups = static function (array $pool, $groupSize, $groupCount) {
            $groupSize = max(1, min(10, (int) $groupSize));
            $groupCount = max(1, min(100, (int) $groupCount));
            $pool = array_values(array_unique(array_filter(array_map('intval', $pool), function ($number) {
                return $number >= 1 && $number <= 49;
            })));
            if (count($pool) < $groupSize) {
                return array();
            }

            $candidates = array();
            $walk = function ($start, array $group) use (&$walk, &$candidates, $pool, $groupSize) {
                if (count($group) === $groupSize) {
                    $candidates[] = array_values($group);
                    return;
                }
                for ($index = $start; $index < count($pool); $index++) {
                    $nextGroup = $group;
                    $nextGroup[] = (int) $pool[$index];
                    $walk($index + 1, $nextGroup);
                }
            };
            $walk(0, array());

            return array_slice($candidates, 0, $groupCount);
        };
        if (preg_match('/^pt_(\d+)_(\d+)_group_(\d+)$/', $selectedValue, $matches)) {
            $groupSize = (int) $matches[2];
            $groupCount = (int) $matches[3];
            $groupResults = $buildPingteHotGroups($hotNumbers, $groupSize, $groupCount);

            return array(
                'kind' => 'pingte-groups',
                'values' => array(),
                'groups' => $groupResults,
            );
        }

        if (preg_match('/^pt_(\d+)_(\d+)_combo_(\d+)$/', $selectedValue, $matches)) {
            return array(
                'kind' => 'pingte-combo',
                'values' => array_slice($hotNumbers, 0, max(0, min(10, (int) $matches[3]))),
                'groups' => array(),
            );
        }

        return array(
            'kind' => 'pingte',
            'values' => array(),
            'groups' => array(),
        );
    }

    if ($typeKey === 'other') {
        $limit = max(1, $forecastOtherDisplayLimit($selectedValue));
        $scores = array();
        if (preg_match('/^pt_zodiac_(\d+)$/', $selectedValue, $matches)) {
            $zodiacResults = array();
            foreach ($numbers as $number) {
                $zodiacResults[] = $forecastNumberToZodiac[(int) $number] ?? '';
            }
            $zodiacResults = $forecastUniqueLabels($zodiacResults);

            return array(
                'kind' => 'other',
                'values' => array_slice($zodiacResults, 0, max(1, min(5, (int) $matches[1]))),
                'groups' => array(),
            );
        }

        if ($selectedValue === 'odd_even') {
            $scores = array('单' => 0, '双' => 0);
            foreach ($numbers as $number) {
                $number = (int) $number;
                if ($number >= 1 && $number <= 49) {
                    $scores[$number % 2 === 0 ? '双' : '单'] += 1;
                }
            }
        } elseif ($selectedValue === 'wave') {
            $scores = array('红波' => 0, '蓝波' => 0, '绿波' => 0);
            foreach ($numbers as $number) {
                $wave = (string) ($forecastNumberToWave[(int) $number] ?? '');
                if ($wave !== '' && isset($scores[$wave])) {
                    $scores[$wave] += 1;
                }
            }
        } elseif ($selectedValue === 'big_small') {
            $scores = array('小' => 0, '大' => 0);
            foreach ($numbers as $number) {
                $number = (int) $number;
                if ($number >= 1 && $number <= 49) {
                    $scores[$number <= 24 ? '小' : '大'] += 1;
                }
            }
        } elseif ($selectedValue === 'head') {
            for ($head = 0; $head <= 4; $head++) {
                $scores[$head . '头'] = 0;
            }
            foreach ($numbers as $number) {
                $number = (int) $number;
                if ($number >= 1 && $number <= 49) {
                    $scores[(int) floor($number / 10) . '头'] += 1;
                }
            }
        } elseif ($selectedValue === 'tail') {
            for ($tail = 0; $tail <= 9; $tail++) {
                $scores[$tail . '尾'] = 0;
            }
            foreach ($numbers as $number) {
                $number = (int) $number;
                if ($number >= 1 && $number <= 49) {
                    $scores[$number % 10 . '尾'] += 1;
                }
            }
        }
        arsort($scores);

        return array(
            'kind' => 'other',
            'values' => array_slice(array_keys($scores), 0, $limit),
            'groups' => array(),
        );
    }

    return array(
        'kind' => $typeKey,
        'values' => array(),
        'groups' => array(),
    );
};
$forecastRenderResultHtml = static function (array $payload) use (
    $forecastRenderZodiacChips,
    $forecastRenderNumberChips,
    $forecastRenderOtherChips,
    $forecastRenderPingteGroupTexts,
    $forecastRenderPingteComboText
) {
    $parts = array();

    if (($payload['kind'] ?? '') === 'zodiac') {
        $parts = array_merge($parts, $forecastRenderZodiacChips((array) ($payload['values'] ?? array())));
    } elseif (($payload['kind'] ?? '') === 'number') {
        $parts = array_merge($parts, $forecastRenderNumberChips((array) ($payload['values'] ?? array())));
    } elseif (($payload['kind'] ?? '') === 'pingte-groups') {
        $parts = array_merge($parts, $forecastRenderPingteGroupTexts((array) ($payload['groups'] ?? array())));
    } elseif (($payload['kind'] ?? '') === 'pingte-combo') {
        $comboHtml = $forecastRenderPingteComboText((array) ($payload['values'] ?? array()));
        if ($comboHtml !== '') {
            $parts[] = $comboHtml;
        }
    } elseif (($payload['kind'] ?? '') === 'other') {
        $parts = array_merge($parts, $forecastRenderOtherChips((array) ($payload['values'] ?? array())));
    }

    if (empty($parts)) {
        return '<span class="forecast-result-placeholder">暂无结果</span>';
    }

    return implode('', $parts);
};
$forecastResolveLineConfidence = static function ($typeKey, $selectedValue) use ($forecastBaseConfidence, $forecastLineConfidences) {
    $selectedValue = trim((string) $selectedValue);
    if ($selectedValue === '' || $forecastBaseConfidence <= 0) {
        return '';
    }

    $resolvedConfidence = isset($forecastLineConfidences[$typeKey])
        ? (float) $forecastLineConfidences[$typeKey]
        : $forecastBaseConfidence;
    $resolvedConfidence = min(97.0, max(89.0, $resolvedConfidence));

    return number_format($resolvedConfidence, 1) . '%';
};
$forecastPlaceholderIdioms = array(
    '旗开得胜',
    '财运亨通',
    '时来运转',
    '机不可失',
    '乘势而上',
    '势在必得',
    '鸿运当头',
    '顺势而为',
    '马到功成',
    '稳操胜券',
    '一举夺魁',
    '财源广进',
    '金玉满堂',
    '大吉大利',
    '福星高照',
    '日进斗金',
    '捷报频传',
    '满载而归',
    '高歌猛进',
    '大展宏图',
    '扬帆起航',
    '乘风破浪',
    '一鼓作气',
    '一路长虹',
    '福运连连',
    '左右逢源',
    '心想事成',
    '步步高升',
    '所向披靡',
    '直取佳绩',
    '喜从天降',
    '锦上添花',
    '春风得意',
    '一路生花',
    '连连报喜',
    '如虎添翼',
    '赢在当下',
    '全力以赴',
    '决胜千里',
    '抢占先机',
    '当机立断',
    '果断出击',
    '趁热打铁',
    '奋勇争先',
    '一马当先',
    '快人一步',
    '先声夺人',
    '乘胜追击',
    '百发百中',
    '十拿九稳',
    '连战连捷',
    '开门见喜',
    '喜气盈门',
    '吉星高照',
    '紫气东来',
    '顺风顺水',
    '风生水起',
    '时运亨通',
    '招财进宝',
    '福运亨通',
    '发财有道',
    '财气冲天',
    '富贵临门',
    '福到财到',
    '金运亨通',
    '运开时泰',
    '福满乾坤',
    '吉运临门',
    '大有可为',
    '前程似锦',
    '鹏程万里',
    '大有作为',
    '万事顺遂',
    '顺心顺意',
    '一帆风顺',
    '万事大吉',
    '吉庆有余',
    '喜事连连',
    '鸿运连连',
    '财喜双收',
    '大获全胜',
    '凯歌高奏',
    '佳绩连连',
    '一战成名',
    '一鸣惊人',
    '光彩夺目',
    '志在必成',
    '胜券在握',
    '一击即中',
    '出手即赢',
    '一路凯旋',
    '争取良机',
    '蓄势待发',
    '顺势出手',
    '及时行动',
    '借势而赢',
    '勇往直前',
    '乘机而动',
    '大步向前',
    '迎势而上',
    '先赢一步',
    '好运加身',
    '福运加身',
    '财气满满',
    '喜气满满',
    '财势双旺',
    '财福双全',
    '旺运当先',
    '旺气冲天',
    '兴旺发达',
    '财路大开',
    '大财将至',
    '财来运转',
    '金运大开',
    '顺赢当头',
    '开局即红',
    '红运加持',
    '红火腾达',
    '热势正旺',
    '旺势如虹',
    '喜迎丰收',
    '丰收在望',
    '收获满满',
    '斩获佳绩',
    '佳运正盛',
    '盛运当头',
    '吉报频来',
    '好运在握',
    '吉运在握',
    '成竹在胸',
    '胜算倍增',
    '把握当下',
    '趁势拿下',
    '拿下此局',
    '一举拿下',
    '直上云霄',
    '飞黄腾达',
    '步步登高',
    '一路向赢',
    '势不可挡',
    '顺手拈来',
    '轻取佳绩',
    '赢面大开',
    '运势上扬',
    '喜运上扬',
    '财势上扬',
    '鸿运高照',
    '金喜临门',
    '得势得财',
    '财旺福旺',
    '运旺财旺',
    '吉旺双收',
    '旺上加旺',
    '时机已到',
    '抢赢当下',
    '顺赢而上',
    '迅捷出手',
    '先机在握',
    '机会在前',
    '当下即赢',
    '乘势即发',
    '全面开花',
    '四方来财',
    '八方进宝',
    '八方来财',
    '富贵盈门',
    '财福盈门',
    '吉庆临门',
    '喜讯频来',
    '大势已成',
    '势如破竹',
    '大刀阔斧',
    '拔得头筹',
    '功成名就',
    '喜获丰盈',
    '盈利可期',
    '进财有望',
    '好运即来',
    '胜利在望',
    '赢势显现',
    '红利在前',
    '喜赢四方',
    '财聚八方',
    '财到手到',
    '吉祥如意',
    '瑞气东来',
    '喜乐盈门',
    '顺利拿下',
    '抢得头彩',
    '一路稳赢',
    '旺势开局',
    '气势如虹',
    '虎跃龙腾',
    '龙腾虎跃',
    '喜报临门',
    '顺遂无忧',
    '赢势当先',
    '财运大旺',
    '乘胜扬帆',
    '财星高照',
);
$forecastPlaceholderIdiomPool = $forecastPlaceholderIdioms;
shuffle($forecastPlaceholderIdiomPool);
$forecastPlaceholderEmojiPool = array('🎯', '✨', '🍀', '🏆', '💰', '🔥', '🌟', '🎉', '💎', '🚀');
shuffle($forecastPlaceholderEmojiPool);
$forecastTakePlaceholderIdiom = static function () use (&$forecastPlaceholderIdiomPool, $forecastPlaceholderIdioms) {
    if (empty($forecastPlaceholderIdiomPool)) {
        $forecastPlaceholderIdiomPool = $forecastPlaceholderIdioms;
        shuffle($forecastPlaceholderIdiomPool);
    }

    return (string) array_shift($forecastPlaceholderIdiomPool);
};
$forecastTakePlaceholderEmoji = static function () use (&$forecastPlaceholderEmojiPool) {
    if (empty($forecastPlaceholderEmojiPool)) {
        $forecastPlaceholderEmojiPool = array('🎯', '✨', '🍀', '🏆', '💰', '🔥', '🌟', '🎉', '💎', '🚀');
        shuffle($forecastPlaceholderEmojiPool);
    }

    return (string) array_shift($forecastPlaceholderEmojiPool);
};
$forecastRenderPlaceholder = static function () use ($forecastEscape, $forecastTakePlaceholderIdiom, $forecastTakePlaceholderEmoji) {
    $placeholderIdiom = trim((string) $forecastTakePlaceholderIdiom());
    $placeholderEmoji = trim((string) $forecastTakePlaceholderEmoji());

    return '<span class="forecast-result-placeholder"><span class="forecast-result-placeholder-text">请选择类型</span><span class="forecast-result-placeholder-idiom"><span class="forecast-result-placeholder-emoji">' . $forecastEscape($placeholderEmoji) . '</span><span>' . $forecastEscape($placeholderIdiom) . '</span></span></span>';
};
$forecastBuildResultLine = static function ($title, $selectedValue, $typeKey, $selectedLabel) use ($forecastHasGeneratedFilters, $forecastCurrentNumbers, $forecastDisplayPayloads, $forecastResolveResultPayload, $forecastRenderTypeChip, $forecastRenderResultHtml, $forecastResolveLineConfidence, $forecastRenderPlaceholder, $forecastOtherDisplayLimit) {
    $selectedValue = trim((string) $selectedValue);
    $selectedLabel = trim((string) $selectedLabel);
    $hasSelectedType = $selectedValue !== '';
    $resolvedPayload = array('kind' => $typeKey, 'values' => array(), 'groups' => array());
    if ($hasSelectedType && $forecastHasGeneratedFilters) {
        $structuredPayload = $forecastDisplayPayloads[$typeKey] ?? null;
        if (is_array($structuredPayload)) {
            $resolvedPayload = array_merge($resolvedPayload, $structuredPayload);
            if ($typeKey === 'other') {
                $fallbackPayload = $forecastResolveResultPayload($typeKey, $selectedValue, $forecastCurrentNumbers);
                $expectedCount = $forecastOtherDisplayLimit($selectedValue);
                $actualCount = count(array_values(array_filter((array) ($resolvedPayload['values'] ?? array()), static function ($value) {
                    return trim((string) $value) !== '';
                })));
                if ($expectedCount > 0 && $actualCount < $expectedCount) {
                    $resolvedPayload = $fallbackPayload;
                }
            }
        } else {
            $resolvedPayload = $forecastResolveResultPayload($typeKey, $selectedValue, $forecastCurrentNumbers);
        }
    }
    $displayResultHtml = $hasSelectedType
        ? $forecastRenderResultHtml($resolvedPayload)
        : '<span class="forecast-result-placeholder">请选择类型</span>';
    if (!$hasSelectedType) {
        $displayResultHtml = $forecastRenderPlaceholder();
    }

    return array(
        'title' => $title,
        'type_key' => $typeKey,
        'is_unselected' => !$hasSelectedType,
        'type_html' => $hasSelectedType ? $forecastRenderTypeChip($selectedLabel) : '',
        'result_html' => $displayResultHtml,
        'confidence' => $hasSelectedType && $forecastHasGeneratedFilters && $forecastResolveLineConfidence($typeKey, $selectedValue) !== '' ? $forecastResolveLineConfidence($typeKey, $selectedValue) : '',
    );
};
$forecastResultLines = array(
    $forecastBuildResultLine('生肖类型：', $currentFilters['zodiac_type'], 'zodiac', $forecastOptionLabel($forecastFilterOptions['zodiac'], $currentFilters['zodiac_type'])),
    $forecastBuildResultLine('号码类型：', $currentFilters['number_type'], 'number', $forecastOptionLabel($forecastFilterOptions['number'], $currentFilters['number_type'])),
    $forecastBuildResultLine('平码类型：', $currentFilters['pingte_type'], 'pingte', $forecastOptionLabel($forecastFilterOptions['pingte'], $currentFilters['pingte_type'])),
    $forecastBuildResultLine('其他类型：', $currentFilters['other_type'], 'other', $forecastOptionLabel($forecastFilterOptions['other'], $currentFilters['other_type'])),
);
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

        return '香港' . $text . '期';
    }

    return '第' . $text . '期';
};
?>
<?php echo \App\Core\View::make(app(), 'partials/front_top_bar', array('region' => $region)); ?>
<section class="front-page-shell front-unified-page forecast-page">
    <div class="section-title forecast-section-title">
        <div class="forecast-section-title-main">
            <i class="fa-solid fa-brain"></i>
            <span>AI预测</span>
        </div>
        <div class="forecast-region-switch">
            <a href="<?php echo e(public_url('forecast.php') . '?region=macau'); ?>" class="forecast-region-tab <?php echo $region === 'macau' ? 'is-active' : ''; ?>">
                <span class="forecast-region-mark">澳</span>
                <span>澳门</span>
            </a>
            <a href="<?php echo e(public_url('forecast.php') . '?region=hongkong'); ?>" class="forecast-region-tab <?php echo $region === 'hongkong' ? 'is-active' : ''; ?>">
                <span class="forecast-region-mark">港</span>
                <span>香港</span>
            </a>
        </div>
    </div>
    <div id="section-home" class="data-frame front-panel-stack front-unified-frame forecast-stack">
        <?php if (!empty($liveBoxHtml)): ?>
            <div class="forecast-live-slot">
                <?php echo $liveBoxHtml; ?>
            </div>
        <?php endif; ?>
        <div class="front-panel-card front-panel-card-soft forecast-card forecast-card-primary">
            <div class="front-action-row forecast-hero-row">
                <div class="forecast-hero-copy">
                    <div class="forecast-filter-row" aria-label="AI预测选项">
                        <select class="forecast-filter-select" name="zodiac_type" form="forecast-generate-form" aria-label="生肖选项" data-forecast-price-select>
                            <option value=""<?php echo $currentFilters['zodiac_type'] === '' ? ' selected' : ''; ?>><?php echo e($forecastFilterOptions['zodiac']['placeholder']); ?></option>
                            <?php foreach ($forecastFilterOptions['zodiac']['options'] as $option): ?>
                                <option value="<?php echo e($option['value']); ?>" data-forecast-price="<?php echo e((string) ($option['price'] ?? 0)); ?>"<?php echo $currentFilters['zodiac_type'] === $option['value'] ? ' selected' : ''; ?>><?php echo e($option['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="forecast-filter-select" name="number_type" form="forecast-generate-form" aria-label="号码选项" data-forecast-price-select>
                            <option value=""<?php echo $currentFilters['number_type'] === '' ? ' selected' : ''; ?>><?php echo e($forecastFilterOptions['number']['placeholder']); ?></option>
                            <?php foreach ($forecastFilterOptions['number']['options'] as $option): ?>
                                <option value="<?php echo e($option['value']); ?>" data-forecast-price="<?php echo e((string) ($option['price'] ?? 0)); ?>"<?php echo $currentFilters['number_type'] === $option['value'] ? ' selected' : ''; ?>><?php echo e($option['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="forecast-filter-select" name="pingte_type" form="forecast-generate-form" aria-label="平特选项" data-forecast-price-select>
                            <option value=""<?php echo $currentFilters['pingte_type'] === '' ? ' selected' : ''; ?>><?php echo e($forecastFilterOptions['pingte']['placeholder']); ?></option>
                            <?php foreach ($forecastFilterOptions['pingte']['options'] as $option): ?>
                                <option value="<?php echo e($option['value']); ?>" data-forecast-price="<?php echo e((string) ($option['price'] ?? 0)); ?>"<?php echo $currentFilters['pingte_type'] === $option['value'] ? ' selected' : ''; ?>><?php echo e($option['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <select class="forecast-filter-select" name="other_type" form="forecast-generate-form" aria-label="其他选项" data-forecast-price-select>
                            <option value=""<?php echo $currentFilters['other_type'] === '' ? ' selected' : ''; ?>><?php echo e($forecastFilterOptions['other']['placeholder']); ?></option>
                            <?php foreach ($forecastFilterOptions['other']['options'] as $option): ?>
                                <option value="<?php echo e($option['value']); ?>" data-forecast-price="<?php echo e((string) ($option['price'] ?? 0)); ?>"<?php echo $currentFilters['other_type'] === $option['value'] ? ' selected' : ''; ?>><?php echo e($option['label']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <div class="forecast-result-card" data-front-forecast-pricing data-forecast-agent-free="<?php echo $forecastCustomerServiceAgentViewer ? '1' : '0'; ?>" data-forecast-discounts="<?php echo e(json_encode($forecastPricingDiscounts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?>">
                <div class="forecast-block-title forecast-result-title">
                    <span class="forecast-result-issue-badge <?php echo $region === 'hongkong' ? 'is-hongkong' : 'is-macau'; ?>">
                        <span class="forecast-result-issue-mark"><?php echo e($forecastResultRegionMark); ?></span>
                        <span class="forecast-result-issue-text"><?php echo e($forecastResultIssueText); ?></span>
                    </span>
                    <span class="forecast-result-title-text">结果</span>
                    <span class="forecast-result-price-strip" aria-label="AI预测积分价格" data-forecast-price-strip<?php echo (int) ($forecastPricingSummary['selected_count'] ?? 0) > 0 && !empty($forecastPricingSummary['items']) ? '' : ' hidden'; ?>>
                        <strong class="forecast-result-price-total">
                            <span>合计：</span>
                            <b data-forecast-price-total><?php echo $forecastCustomerServiceAgentViewer ? '免积分' : e((string) ($forecastPricingSummary['total_text'] ?? '0')) . '积分'; ?></b>
                        </strong>
                        <em data-forecast-price-discount<?php echo (int) ($forecastPricingSummary['selected_count'] ?? 0) > 1 ? '' : ' hidden'; ?>><?php echo e($forecastDiscountLabel); ?></em>
                    </span>
                </div>
                <div class="forecast-result-scroll">
                    <div class="forecast-result-summary">
                        <?php if ($forecastError !== '' && !$currentPrediction): ?>
                            <div class="forecast-result-summary-line is-error">
                                <span class="forecast-result-summary-value"><?php echo e($forecastError); ?></span>
                            </div>
                        <?php else: ?>
                            <?php foreach ($forecastResultLines as $forecastResultLine): ?>
                                <div class="forecast-result-summary-line is-<?php echo e($forecastResultLine['type_key']); ?><?php echo $forecastResultLine['confidence'] === '' ? ' is-no-confidence' : ''; ?><?php echo !empty($forecastResultLine['is_unselected']) ? ' is-unselected' : ''; ?>">
                                    <span class="forecast-result-summary-head">
                                        <span class="forecast-result-summary-label"><?php echo e($forecastResultLine['title']); ?></span>
                                    </span>
                                    <?php if ($forecastResultLine['confidence'] !== ''): ?>
                                        <span class="forecast-result-summary-confidence"><?php echo e($forecastResultLine['confidence']); ?></span>
                                    <?php endif; ?>
                                    <span class="forecast-result-summary-body">
                                        <?php if ($forecastResultLine['type_html'] !== ''): ?>
                                            <span class="forecast-result-summary-type"><?php echo $forecastResultLine['type_html']; ?></span>
                                        <?php endif; ?>
                                        <span class="forecast-result-summary-value"><?php echo $forecastResultLine['result_html']; ?></span>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php if ($forecastResultIdiom !== '' || $forecastResultBlessing !== ''): ?>
                        <div class="forecast-result-blessing-card">
                            <?php if ($forecastResultIdiom !== ''): ?>
                                <div class="forecast-result-blessing-row">
                                    <span class="forecast-result-blessing-label" title="寓意词" aria-label="寓意词"><?php echo e($forecastResultIdiomEmoji); ?></span>
                                    <span class="forecast-result-blessing-text is-idiom"><?php echo e($forecastResultIdiom); ?></span>
                                </div>
                            <?php endif; ?>
                            <?php if ($forecastResultBlessing !== ''): ?>
                                <div class="forecast-result-blessing-row">
                                    <span class="forecast-result-blessing-label" title="寓意语" aria-label="寓意语"><?php echo e($forecastResultBlessingEmoji); ?></span>
                                    <span class="forecast-result-blessing-text"><?php echo e($forecastResultBlessing); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="front-stat-grid forecast-stat-grid">
                <div class="forecast-stat-card forecast-stat-card-issue">
                    <div class="forecast-stat-label">上期参与</div>
                    <div class="forecast-stat-value"><span class="forecast-stat-number"><?php echo e($forecastPreviousParticipants); ?></span><span>人</span></div>
                </div>
                <div class="forecast-stat-card forecast-stat-card-members">
                    <div class="forecast-stat-label">上期中奖</div>
                    <div class="forecast-stat-value"><span class="forecast-stat-number"><?php echo e($forecastPreviousWinners); ?></span><span>人</span></div>
                </div>
                <div class="forecast-stat-card forecast-stat-card-visits">
                    <div class="forecast-stat-label">本期参与</div>
                    <div class="forecast-stat-value"><span class="forecast-stat-number"><?php echo e($forecastTodayParticipants); ?></span><span>人</span></div>
                </div>
                <form id="forecast-generate-form" method="post" action="<?php echo e(public_url('api.php')); ?>" data-ajax-form data-forecast-progress-form data-forecast-guest-form="<?php echo (isset($user) && is_array($user)) || $forecastCustomerServiceAgentViewer ? '0' : '1'; ?>" data-forecast-login-url="<?php echo e(public_url('member.php') . '?region=' . urlencode($region) . '&mode=login'); ?>" data-forecast-register-url="<?php echo e(public_url('member.php') . '?region=' . urlencode($region) . '&mode=register'); ?>" class="forecast-generate-form forecast-generate-form-block">
                    <input type="hidden" name="action" value="forecast.generate">
                    <input type="hidden" name="_token" value="<?php echo e(csrf_token('api')); ?>">
                    <input type="hidden" name="region" value="<?php echo e($region); ?>">
                    <?php if ($forecastCustomerServiceAgentViewer): ?>
                        <input type="hidden" name="agent" value="1">
                    <?php endif; ?>
                    <button type="submit" class="forecast-generate-btn"><span>即刻</span><span>中奖</span></button>
                </form>
            </div>
            <div class="forecast-progress-panel" data-forecast-progress hidden aria-hidden="true" aria-live="polite" role="alertdialog" aria-modal="true" aria-labelledby="forecast-progress-title">
                <section class="forecast-progress-dialog">
                    <div class="forecast-progress-ring" aria-hidden="true">
                        <span></span>
                        <span></span>
                        <span></span>
                        <b>AI</b>
                    </div>
                    <div class="forecast-progress-main">
                        <div class="forecast-progress-title" id="forecast-progress-title" data-forecast-progress-title>&#29572;&#38376;&#25512;&#28436;&#20013;</div>
                        <div class="forecast-progress-text" data-forecast-progress-text>&#27491;&#22312;&#21796;&#36215;&#36817;&#26399;&#24320;&#22870;&#27668;&#25968;</div>
                        <div class="forecast-progress-codes" aria-hidden="true">
                            <span data-forecast-progress-code>07</span>
                            <span data-forecast-progress-code>19</span>
                            <span data-forecast-progress-code>31</span>
                            <span data-forecast-progress-code>43</span>
                        </div>
                        <div class="forecast-progress-track"><i data-forecast-progress-bar></i></div>
                        <div class="forecast-progress-marks" aria-hidden="true">
                            <span>&#22825;&#26102;</span>
                            <span>&#22320;&#21033;</span>
                            <span>&#20154;&#21644;</span>
                        </div>
                        <div class="forecast-progress-actions" data-forecast-progress-actions hidden>
                            <button type="button" class="forecast-progress-button" data-forecast-progress-close>&#26597;&#30475;&#32467;&#26524;</button>
                        </div>
                    </div>
                </section>
            </div>
        </div>

    </div>
</section>

<?php if (!empty($liveBoxHtml) && !empty($livePayload) && is_array($livePayload)): ?>
<script id="legacy-home-data" type="application/json"><?php echo json_encode($livePayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>
<?php endif; ?>

<?php if (isset($user) && is_array($user)): ?>
    <?php echo \App\Core\View::make(app(), 'partials/member_recharge_modal', array(
        'region' => $region,
        'memberRechargeUser' => $user,
        'memberRechargeUrl' => public_url('service.php') . '?' . http_build_query(array('region' => $region, 'embed' => '1')),
    )); ?>
<?php endif; ?>

<?php echo \App\Core\View::make(app(), 'partials/front_bottom_nav', array('region' => $region, 'activePanel' => 'forecast', 'user' => $user)); ?>
