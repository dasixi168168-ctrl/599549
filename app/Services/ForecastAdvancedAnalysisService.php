<?php
declare(strict_types=1);

namespace App\Services;

class ForecastAdvancedAnalysisService extends Service
{
    const VERSION = '2026-high-priority-1';

    protected $rulesCache = null;
    protected $attributeMapCache = null;

    /**
     * 统一编排 6 个高优先级模块，供综合推算主流程直接挂接。
     */
    public function buildAdvancedForecast($region, array $draws, array $recommendedNumbers = array(), array $heatStats = array(), $issueNo = '', array $filters = array(), $baseConfidence = 0.0)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $draws = $this->normalizeDraws($draws);
        $scores = $this->scoreNumbers($draws, $heatStats);

        $fiveElements = $this->analyzeFiveElements($draws, $scores);
        $killStrategy = $this->buildKillStrategy($draws, $scores, $fiveElements);
        $sumAnalysis = $this->analyzeSum($draws);
        $spanAnalysis = $this->analyzeSpan($draws);
        $omissionCycle = $this->analyzeOmissionCycle($draws, $scores);
        $danTuoStrategy = $this->buildDanTuoStrategy($draws, $scores, $killStrategy, $omissionCycle);

        $moduleConfidences = array(
            (float) ($fiveElements['confidence'] ?? 0.0),
            (float) ($killStrategy['confidence'] ?? 0.0),
            (float) ($sumAnalysis['confidence'] ?? 0.0),
            (float) ($spanAnalysis['confidence'] ?? 0.0),
            (float) ($omissionCycle['confidence'] ?? 0.0),
            (float) ($danTuoStrategy['confidence'] ?? 0.0),
        );
        $advancedConfidence = $this->clamp(
            ($this->average($moduleConfidences) * 0.65) + ((float) $baseConfidence * 0.35),
            0.0,
            97.0
        );

        $summaryLine = $this->buildSummaryLine(
            $fiveElements,
            $killStrategy,
            $sumAnalysis,
            $spanAnalysis,
            $omissionCycle,
            $danTuoStrategy
        );

        return array(
            'version' => self::VERSION,
            'region' => $region,
            'generated_for_issue' => (string) $issueNo,
            'generated_at' => date('Y-m-d H:i:s'),
            'analysis_draw_count' => count($draws),
            'attribute_map' => array(
                'year' => 2026,
                'zodiac_year' => '马',
                'number_count' => 49,
                'fields' => array('number', 'wave', 'zodiac', 'element', 'home_wild', 'sky_earth', 'gender', 'odd_even', 'big_small', 'sum_odd_even', 'head', 'tail', 'section'),
            ),
            'filters' => $filters,
            'base_recommended_numbers' => $this->normalizeNumberList($recommendedNumbers),
            'recommendations' => array(
                'five_elements' => $fiveElements,
                'kill_strategy' => $killStrategy,
                'sum' => $sumAnalysis,
                'span' => $spanAnalysis,
                'omission_cycle' => $omissionCycle,
                'dan_tuo' => $danTuoStrategy,
            ),
            'confidence' => round($advancedConfidence, 2),
            'summary_line' => $summaryLine,
            'changelog' => array(
                '新增五行频率/遗漏/相生相克加权。',
                '新增杀特码、杀尾、杀肖、杀波色、杀段、杀五行。',
                '新增正码和值、总码和值、和值区间与奇偶大小判断。',
                '新增正码跨度、特码相对跨度、总码跨度区间。',
                '新增当前遗漏、历史最大遗漏、平均遗漏与反弹评分。',
                '新增胆码 Top3、拖码池与自动胆拖组合。',
            ),
        );
    }

    /**
     * 简单滚动回测：每一期只使用它之前的历史窗口，避免用未来数据反推。
     */
    public function backtest(array $draws, $historyLimit = 30, $trainingWindow = 60)
    {
        $draws = $this->normalizeDraws($draws);
        $historyLimit = max(1, (int) $historyLimit);
        $trainingWindow = max(8, (int) $trainingWindow);
        $tested = 0;
        $hits = array(
            'recommended_special' => 0,
            'recommended_any' => 0,
            'five_element' => 0,
            'kill_success' => 0,
            'sum_range' => 0,
            'span_range' => 0,
            'dan_any' => 0,
        );
        $items = array();

        $maxIndex = min(count($draws) - 1, $historyLimit - 1);
        for ($index = 0; $index <= $maxIndex; $index++) {
            $actual = $draws[$index];
            $trainingDraws = array_slice($draws, $index + 1, $trainingWindow);
            if (count($trainingDraws) < 8) {
                continue;
            }

            $scores = $this->scoreNumbers($trainingDraws, array());
            $analysis = $this->buildAdvancedForecast(
                (string) ($actual['region'] ?? 'macau'),
                $trainingDraws,
                array_slice(array_keys($scores), 0, 10),
                array(),
                (string) ($actual['issue_no'] ?? ''),
                array(),
                0.0
            );
            $numbers = array_values(array_map('intval', (array) ($analysis['recommendations']['dan_tuo']['numbers'] ?? array())));
            if ($numbers === array()) {
                $numbers = array_values(array_map('intval', (array) ($analysis['recommendations']['omission_cycle']['high_omission_numbers'] ?? array())));
            }

            $parts = $actual['parts'];
            $actualNormal = (array) ($parts['normal'] ?? array());
            $actualSpecial = (int) ($parts['special'] ?? 0);
            $actualAll = (array) ($parts['all'] ?? array());
            $actualAttributes = $actualSpecial >= 1 ? $this->numberAttributes($actualSpecial) : array();
            $killNumbers = array_values(array_map('intval', (array) ($analysis['recommendations']['kill_strategy']['special_numbers'] ?? array())));
            $sumRange = (array) ($analysis['recommendations']['sum']['total_sum']['range'] ?? array());
            $spanRange = (array) ($analysis['recommendations']['span']['total_span']['range'] ?? array());
            $danNumbers = array_values(array_map('intval', (array) ($analysis['recommendations']['dan_tuo']['dan_numbers'] ?? array())));

            $totalSum = array_sum($actualAll);
            $totalSpan = empty($actualAll) ? 0 : (max($actualAll) - min($actualAll));
            $item = array(
                'issue_no' => (string) ($actual['issue_no'] ?? ''),
                'special_number' => $actualSpecial,
                'recommended_special_hit' => in_array($actualSpecial, $numbers, true),
                'recommended_any_hit' => count(array_intersect($actualAll, $numbers)) > 0,
                'five_element_hit' => (string) ($analysis['recommendations']['five_elements']['primary_element'] ?? '') === (string) ($actualAttributes['element'] ?? ''),
                'kill_success' => !in_array($actualSpecial, $killNumbers, true),
                'sum_range_hit' => $this->valueInRange($totalSum, $sumRange),
                'span_range_hit' => $this->valueInRange($totalSpan, $spanRange),
                'dan_any_hit' => count(array_intersect($actualAll, $danNumbers)) > 0,
            );

            foreach ($hits as $key => $count) {
                $itemKey = $key . '_hit';
                if ($key === 'recommended_special') {
                    $itemKey = 'recommended_special_hit';
                } elseif ($key === 'recommended_any') {
                    $itemKey = 'recommended_any_hit';
                } elseif ($key === 'five_element') {
                    $itemKey = 'five_element_hit';
                } elseif ($key === 'kill_success') {
                    $itemKey = 'kill_success';
                } elseif ($key === 'sum_range') {
                    $itemKey = 'sum_range_hit';
                } elseif ($key === 'span_range') {
                    $itemKey = 'span_range_hit';
                } elseif ($key === 'dan_any') {
                    $itemKey = 'dan_any_hit';
                }
                if (!empty($item[$itemKey])) {
                    $hits[$key]++;
                }
            }

            $tested++;
            $items[] = $item;
        }

        $rates = array();
        foreach ($hits as $key => $count) {
            $rates[$key] = $tested > 0 ? round(((int) $count / $tested) * 100, 2) : 0.0;
        }

        return array(
            'version' => self::VERSION,
            'tested_issues' => $tested,
            'training_window' => $trainingWindow,
            'hits' => $hits,
            'hit_rates' => $rates,
            'items' => $items,
        );
    }

    /**
     * 五行模块：统计正码/特码五行频率与遗漏，并加入相生相克权重。
     */
    public function analyzeFiveElements(array $draws, array $numberScores = array())
    {
        $draws = $this->normalizeDraws($draws);
        $elements = array('金', '木', '水', '火', '土');
        $frequency = array();
        foreach ($elements as $element) {
            $frequency[$element] = array('normal' => 0.0, 'special' => 0.0, 'all' => 0.0);
        }

        foreach ($draws as $draw) {
            foreach ((array) ($draw['parts']['normal'] ?? array()) as $number) {
                $element = (string) ($this->numberAttributes((int) $number)['element'] ?? '');
                if (isset($frequency[$element])) {
                    $frequency[$element]['normal'] += 1.0;
                    $frequency[$element]['all'] += 1.0;
                }
            }
            $special = (int) ($draw['parts']['special'] ?? 0);
            $element = (string) ($this->numberAttributes($special)['element'] ?? '');
            if (isset($frequency[$element])) {
                $frequency[$element]['special'] += 1.0;
                $frequency[$element]['all'] += 1.0;
            }
        }

        $omission = $this->buildGroupOmission($draws, $elements, function ($number) {
            return (string) ($this->numberAttributes((int) $number)['element'] ?? '');
        }, true);
        $scores = array();
        foreach ($elements as $element) {
            $parent = $this->generatedByElement($element);
            $overcomeBy = $this->overcomeByElement($element);
            $scores[$element] = ((float) $frequency[$element]['special'] * $this->rule('weights.five_elements.special', 2.4))
                + ((float) $frequency[$element]['normal'] * $this->rule('weights.five_elements.normal', 1.0))
                + ((float) ($omission[$element]['current'] ?? 0) * $this->rule('weights.five_elements.current_omission', 0.7))
                + ((float) ($omission[$element]['max'] ?? 0) * $this->rule('weights.five_elements.max_omission', 0.25))
                + ((float) ($omission[$element]['average'] ?? 0) * $this->rule('weights.five_elements.avg_omission', 0.2))
                + ((float) ($frequency[$parent]['special'] ?? 0.0) * $this->rule('weights.five_elements.generating', 0.6))
                + ((float) ($frequency[$overcomeBy]['special'] ?? 0.0) * $this->rule('weights.five_elements.overcoming', -0.35));
        }
        arsort($scores);

        $primary = (string) array_key_first($scores);
        $numbers = $this->numbersByAttribute('element', $primary);
        $rankedNumbers = $this->rankNumbersByScore($numbers, $numberScores, true);
        $confidence = $this->confidenceFromLead($scores, 62.0, 92.0);

        return array(
            'module' => 'five_elements',
            'primary_element' => $primary,
            'numbers' => array_slice($rankedNumbers, 0, 12),
            'confidence' => $confidence,
            'ranking' => $this->assocRanking($scores),
            'frequency' => $frequency,
            'omission' => $omission,
            'relation' => array(
                'generates' => $this->generatesElement($primary),
                'generated_by' => $this->generatedByElement($primary),
                'overcomes' => $this->overcomesElement($primary),
                'overcome_by' => $this->overcomeByElement($primary),
            ),
        );
    }

    /**
     * 杀号模块：从低分号码及低频属性中生成杀特码、杀尾、杀肖、杀波色、杀段、杀五行。
     */
    public function buildKillStrategy(array $draws, array $numberScores = array(), array $fiveElements = array())
    {
        $draws = $this->normalizeDraws($draws);
        if ($numberScores === array()) {
            $numberScores = $this->scoreNumbers($draws, array());
        } else {
            $numberScores = $this->completeNumberScores($numberScores, $draws);
        }
        asort($numberScores);
        $killCount = max(5, min(8, (int) $this->rule('kill.special_number_count', 6)));
        $specialNumbers = array_slice(array_values(array_map('intval', array_keys($numberScores))), 0, $killCount);
        $confidenceItems = array();
        foreach ($specialNumbers as $number) {
            $confidenceItems[] = $this->killConfidenceForNumber($number, $numberScores);
        }

        $tails = $this->lowestAttributeTargets($draws, 'tail', 2);
        $zodiacs = $this->lowestAttributeTargets($draws, 'zodiac', 2);
        $waves = $this->lowestAttributeTargets($draws, 'wave', 1);
        $sections = $this->lowestAttributeTargets($draws, 'section', 1);
        $elements = $this->lowestAttributeTargets($draws, 'element', 1);

        return array(
            'module' => 'kill_strategy',
            'special_numbers' => $specialNumbers,
            'kill_tail' => $tails,
            'kill_zodiac' => $zodiacs,
            'kill_wave' => $waves,
            'kill_section' => $sections,
            'kill_element' => $elements !== array() ? $elements : array((string) ($fiveElements['relation']['overcome_by'] ?? '')),
            'confidence' => round($this->average($confidenceItems), 2),
            'items' => array(
                array('type' => 'kill_special', 'values' => $specialNumbers, 'confidence' => round($this->average($confidenceItems), 2)),
                array('type' => 'kill_tail', 'values' => $tails, 'confidence' => 66.0),
                array('type' => 'kill_zodiac', 'values' => $zodiacs, 'confidence' => 64.0),
                array('type' => 'kill_wave', 'values' => $waves, 'confidence' => 61.0),
                array('type' => 'kill_section', 'values' => $sections, 'confidence' => 62.0),
                array('type' => 'kill_element', 'values' => $elements, 'confidence' => 63.0),
            ),
        );
    }

    /**
     * 和值模块：输出正码和值、总码和值的推荐区间、奇偶与大小方向。
     */
    public function analyzeSum(array $draws)
    {
        $draws = $this->normalizeDraws($draws);
        $normalSums = array();
        $totalSums = array();
        foreach ($draws as $draw) {
            $normal = array_values(array_map('intval', (array) ($draw['parts']['normal'] ?? array())));
            $all = array_values(array_map('intval', (array) ($draw['parts']['all'] ?? array())));
            if ($normal !== array()) {
                $normalSums[] = array_sum($normal);
            }
            if ($all !== array()) {
                $totalSums[] = array_sum($all);
            }
        }

        $normal = $this->buildRangeRecommendation($normalSums, 14, 150);
        $total = $this->buildRangeRecommendation($totalSums, 16, 175);

        return array(
            'module' => 'sum',
            'normal_sum' => $normal,
            'total_sum' => $total,
            'confidence' => round(($normal['confidence'] + $total['confidence']) / 2, 2),
        );
    }

    /**
     * 跨度模块：正码跨度、特码相对正码边界跨度、总码跨度。
     */
    public function analyzeSpan(array $draws)
    {
        $draws = $this->normalizeDraws($draws);
        $normalSpans = array();
        $specialSpans = array();
        $totalSpans = array();
        foreach ($draws as $draw) {
            $normal = array_values(array_map('intval', (array) ($draw['parts']['normal'] ?? array())));
            $all = array_values(array_map('intval', (array) ($draw['parts']['all'] ?? array())));
            $special = (int) ($draw['parts']['special'] ?? 0);
            if ($normal !== array()) {
                $normalSpans[] = max($normal) - min($normal);
                if ($special >= 1) {
                    $specialSpans[] = max(abs($special - min($normal)), abs(max($normal) - $special));
                }
            }
            if ($all !== array()) {
                $totalSpans[] = max($all) - min($all);
            }
        }

        $normal = $this->buildRangeRecommendation($normalSpans, 5, 24);
        $special = $this->buildRangeRecommendation($specialSpans, 5, 24);
        $total = $this->buildRangeRecommendation($totalSpans, 5, 28);

        return array(
            'module' => 'span',
            'normal_span' => $normal,
            'special_span' => $special,
            'total_span' => $total,
            'confidence' => round(($normal['confidence'] + $special['confidence'] + $total['confidence']) / 3, 2),
        );
    }

    /**
     * 遗漏周期模块：当前遗漏、历史最大遗漏、平均遗漏与反弹评分。
     */
    public function analyzeOmissionCycle(array $draws, array $numberScores = array())
    {
        $draws = $this->normalizeDraws($draws);
        $numbers = range(1, 49);
        $omission = $this->buildNumberOmission($draws);
        $reboundScores = array();
        foreach ($numbers as $number) {
            $current = (float) ($omission[$number]['current'] ?? 0);
            $max = max(1.0, (float) ($omission[$number]['max'] ?? 1));
            $average = max(1.0, (float) ($omission[$number]['average'] ?? 1));
            $heat = (float) ($numberScores[$number] ?? 0.0);
            $reboundScores[$number] = ($current / $max) * $this->rule('weights.omission.current_vs_max', 48.0)
                + min(2.5, $current / $average) * $this->rule('weights.omission.current_vs_average', 18.0)
                + min(20.0, $heat / 8.0);
        }
        arsort($reboundScores);
        $high = array_slice(array_values(array_map('intval', array_keys($reboundScores))), 0, (int) $this->rule('omission.top_count', 10));

        return array(
            'module' => 'omission_cycle',
            'high_omission_numbers' => $high,
            'confidence' => $this->confidenceFromLead($reboundScores, 60.0, 91.0),
            'numbers' => $this->formatNumberOmissionRows($omission, $reboundScores),
        );
    }

    /**
     * 胆拖模块：取高置信 Top3 做胆码，热号叠加冷号回补做拖码池。
     */
    public function buildDanTuoStrategy(array $draws, array $numberScores = array(), array $killStrategy = array(), array $omissionCycle = array())
    {
        $draws = $this->normalizeDraws($draws);
        if ($numberScores === array()) {
            $numberScores = $this->scoreNumbers($draws, array());
        } else {
            $numberScores = $this->completeNumberScores($numberScores, $draws);
        }
        arsort($numberScores);
        $killLookup = array_fill_keys(array_values(array_map('intval', (array) ($killStrategy['special_numbers'] ?? array()))), true);
        $danNumbers = array();
        foreach (array_keys($numberScores) as $number) {
            $number = (int) $number;
            if ($number < 1 || $number > 49 || isset($killLookup[$number])) {
                continue;
            }
            $danNumbers[] = $number;
            if (count($danNumbers) >= 3) {
                break;
            }
        }

        $dragLookup = array();
        $dragNumbers = array();
        $appendDrag = function ($number) use (&$dragLookup, &$dragNumbers, $killLookup, $danNumbers) {
            $number = (int) $number;
            if ($number < 1 || $number > 49 || isset($killLookup[$number]) || in_array($number, $danNumbers, true) || isset($dragLookup[$number])) {
                return;
            }
            $dragLookup[$number] = true;
            $dragNumbers[] = $number;
        };
        foreach (array_keys($numberScores) as $number) {
            $appendDrag($number);
            if (count($dragNumbers) >= 8) {
                break;
            }
        }
        foreach ((array) ($omissionCycle['high_omission_numbers'] ?? array()) as $number) {
            $appendDrag($number);
            if (count($dragNumbers) >= (int) $this->rule('dan_tuo.drag_count', 14)) {
                break;
            }
        }
        foreach (range(1, 49) as $number) {
            $appendDrag($number);
            if (count($dragNumbers) >= (int) $this->rule('dan_tuo.drag_count', 14)) {
                break;
            }
        }

        $combinations = $this->buildDanTuoCombinations($danNumbers, $dragNumbers, (int) $this->rule('dan_tuo.combo_limit', 12));
        $numbers = array_values(array_unique(array_merge($danNumbers, array_slice($dragNumbers, 0, 7))));

        return array(
            'module' => 'dan_tuo',
            'dan_numbers' => $danNumbers,
            'drag_numbers' => $dragNumbers,
            'numbers' => $numbers,
            'combinations' => $combinations,
            'confidence' => $this->confidenceFromLead($numberScores, 65.0, 94.0),
        );
    }

    /**
     * 2026 马年 01-49 完整属性表：波色、生肖、五行、家野、天地、男女、单双、大小、合数、头尾、段位。
     */
    public function numberAttributes2026()
    {
        if (is_array($this->attributeMapCache)) {
            return $this->attributeMapCache;
        }

        $zodiacMap = array(
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
        $waveMap = array(
            '红波' => array(1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46),
            '蓝波' => array(3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48),
            '绿波' => array(5, 6, 11, 16, 17, 21, 22, 27, 28, 32, 33, 38, 39, 43, 44, 49),
        );
        $elementMap = array(
            '金' => array(2, 3, 10, 11, 24, 25, 32, 33, 40, 41),
            '木' => array(6, 7, 14, 15, 22, 23, 36, 37, 44, 45),
            '水' => array(12, 13, 20, 21, 28, 29, 42, 43),
            '火' => array(1, 8, 9, 16, 17, 30, 31, 38, 39, 46, 47),
            '土' => array(4, 5, 18, 19, 26, 27, 34, 35, 48, 49),
        );
        $homeZodiacs = array('牛', '马', '羊', '鸡', '狗', '猪');
        $skyZodiacs = array('兔', '马', '猴', '猪', '牛', '龙');
        $maleZodiacs = array('鼠', '牛', '虎', '龙', '马', '猴', '狗');
        $map = array();
        for ($number = 1; $number <= 49; $number++) {
            $zodiac = $this->labelForNumber($zodiacMap, $number);
            $digitSum = array_sum(array_map('intval', str_split((string) $number)));
            $map[$number] = array(
                'number' => $number,
                'label' => str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                'wave' => $this->labelForNumber($waveMap, $number),
                'zodiac' => $zodiac,
                'element' => $this->labelForNumber($elementMap, $number),
                'home_wild' => in_array($zodiac, $homeZodiacs, true) ? '家' : '野',
                'sky_earth' => in_array($zodiac, $skyZodiacs, true) ? '天' : '地',
                'gender' => in_array($zodiac, $maleZodiacs, true) ? '男' : '女',
                'odd_even' => $number % 2 === 0 ? '双' : '单',
                'big_small' => $number >= 25 ? '大' : '小',
                'digit_sum' => $digitSum,
                'sum_odd_even' => $digitSum % 2 === 0 ? '合数双' : '合数单',
                'head' => (string) floor($number / 10) . '头',
                'tail' => (string) ($number % 10) . '尾',
                'section' => $this->sectionLabel($number),
            );
        }

        $this->attributeMapCache = $map;
        return $this->attributeMapCache;
    }

    protected function normalizeDraws(array $draws)
    {
        $normalized = array();
        foreach ($draws as $draw) {
            if (!is_array($draw)) {
                continue;
            }
            $numbers = json_decode((string) ($draw['numbers_json'] ?? ''), true);
            if (!is_array($numbers) || count($numbers) < 6) {
                continue;
            }
            $normal = array();
            foreach (array_slice($numbers, 0, 6) as $number) {
                $number = (int) $number;
                if ($number < 1 || $number > 49) {
                    $normal = array();
                    break;
                }
                $normal[] = $number;
            }
            $special = (int) ($draw['special_number'] ?? 0);
            if ($normal === array() || $special < 1 || $special > 49) {
                continue;
            }
            $all = array_merge($normal, array($special));
            if (count(array_unique($all)) !== 7) {
                continue;
            }
            $draw['parts'] = array(
                'normal' => $normal,
                'special' => $special,
                'all' => $all,
            );
            $normalized[] = $draw;
        }

        return $normalized;
    }

    protected function scoreNumbers(array $draws, array $heatStats)
    {
        $draws = $this->normalizeDraws($draws);
        $frequency = array_fill(1, 49, 0.0);
        $normalFrequency = array_fill(1, 49, 0.0);
        $specialFrequency = array_fill(1, 49, 0.0);
        foreach ($draws as $draw) {
            foreach ((array) ($draw['parts']['normal'] ?? array()) as $number) {
                $number = (int) $number;
                $frequency[$number] += 1.0;
                $normalFrequency[$number] += 1.0;
            }
            $special = (int) ($draw['parts']['special'] ?? 0);
            if ($special >= 1 && $special <= 49) {
                $frequency[$special] += 1.0;
                $specialFrequency[$special] += 1.0;
            }
        }

        $omission = $this->buildNumberOmission($draws);
        $scores = array();
        for ($number = 1; $number <= 49; $number++) {
            $attributes = $this->numberAttributes($number);
            $score = ((float) ($normalFrequency[$number] ?? 0.0) * $this->rule('weights.number.normal', 10.0))
                + ((float) ($specialFrequency[$number] ?? 0.0) * $this->rule('weights.number.special', 12.0))
                + ((float) ($frequency[$number] ?? 0.0) * $this->rule('weights.number.all', 2.5))
                + min(18.0, (float) ($omission[$number]['current'] ?? 0) * $this->rule('weights.number.current_omission', 0.7))
                + min(12.0, (float) ($omission[$number]['average'] ?? 0) * $this->rule('weights.number.average_omission', 0.35));
            foreach (array('zodiac', 'wave', 'element', 'head', 'tail') as $field) {
                $score += $this->recentAttributeMomentum($draws, $field, (string) ($attributes[$field] ?? ''));
            }
            $scores[$number] = round(max(0.0, $score), 4);
        }
        arsort($scores);

        return $scores;
    }

    protected function normalizeNumberScores(array $scores)
    {
        $normalized = array();
        foreach ($scores as $number => $score) {
            $number = (int) $number;
            if ($number >= 1 && $number <= 49) {
                $normalized[$number] = (float) $score;
            }
        }

        return $normalized;
    }

    protected function completeNumberScores(array $scores, array $draws)
    {
        $normalized = $this->normalizeNumberScores($scores);
        if ($normalized === array()) {
            return $this->scoreNumbers($draws, array());
        }

        if (count($normalized) < 49) {
            $fallback = $this->scoreNumbers($draws, array());
            foreach ($fallback as $number => $score) {
                $number = (int) $number;
                if ($number >= 1 && $number <= 49 && !array_key_exists($number, $normalized)) {
                    $normalized[$number] = (float) $score;
                }
            }
            foreach (range(1, 49) as $number) {
                if (!array_key_exists($number, $normalized)) {
                    $normalized[$number] = 0.0;
                }
            }
        }

        return $normalized;
    }

    protected function normalizeNumberList(array $numbers, $limit = 0)
    {
        $normalized = array();
        $limit = max(0, (int) $limit);
        foreach ($numbers as $number) {
            $number = (int) $number;
            if ($number < 1 || $number > 49 || in_array($number, $normalized, true)) {
                continue;
            }
            $normalized[] = $number;
            if ($limit > 0 && count($normalized) >= $limit) {
                break;
            }
        }

        return $normalized;
    }

    protected function buildNumberOmission(array $draws)
    {
        $labels = range(1, 49);
        return $this->buildGroupOmission($draws, $labels, function ($number) {
            return (int) $number;
        }, true);
    }

    protected function buildGroupOmission(array $draws, array $labels, callable $resolver, $includeAllNumbers)
    {
        $draws = $this->normalizeDraws($draws);
        $positions = array();
        foreach ($labels as $label) {
            $positions[(string) $label] = array();
        }

        foreach ($draws as $index => $draw) {
            $numbers = !empty($includeAllNumbers) ? (array) ($draw['parts']['all'] ?? array()) : array((int) ($draw['parts']['special'] ?? 0));
            $seen = array();
            foreach ($numbers as $number) {
                $label = (string) $resolver((int) $number);
                if ($label !== '' && isset($positions[$label]) && !isset($seen[$label])) {
                    $positions[$label][] = (int) $index;
                    $seen[$label] = true;
                }
            }
        }

        $result = array();
        $drawCount = count($draws);
        foreach ($positions as $label => $items) {
            sort($items);
            $current = $items === array() ? $drawCount : (int) $items[0];
            $gaps = array($current);
            for ($i = 0; $i < count($items) - 1; $i++) {
                $gaps[] = max(0, ((int) $items[$i + 1] - (int) $items[$i]) - 1);
            }
            if ($items !== array()) {
                $gaps[] = max(0, ($drawCount - 1) - (int) $items[count($items) - 1]);
            }
            $result[$label] = array(
                'current' => $current,
                'max' => $gaps !== array() ? max($gaps) : $drawCount,
                'average' => $gaps !== array() ? round($this->average($gaps), 2) : 0.0,
                'hits' => count($items),
            );
        }

        return $result;
    }

    protected function buildRangeRecommendation(array $values, $padding, $bigSmallThreshold)
    {
        $values = array_values(array_map('intval', $values));
        if ($values === array()) {
            return array(
                'range' => array('min' => 0, 'max' => 0),
                'average' => 0.0,
                'recent_average' => 0.0,
                'odd_even' => '',
                'big_small' => '',
                'confidence' => 0.0,
            );
        }

        $average = $this->average($values);
        $recentAverage = $this->average(array_slice($values, 0, min(5, count($values))));
        $center = ($average * 0.55) + ($recentAverage * 0.45);
        $min = max(0, (int) floor($center - (int) $padding));
        $max = (int) ceil($center + (int) $padding);
        $oddCount = 0;
        $bigCount = 0;
        foreach (array_slice($values, 0, min(10, count($values))) as $value) {
            if ((int) $value % 2 === 1) {
                $oddCount++;
            }
            if ((int) $value >= (int) $bigSmallThreshold) {
                $bigCount++;
            }
        }
        $confidence = $this->clamp(58.0 + min(18.0, count($values) * 0.7) + min(10.0, abs($recentAverage - $average) / 2), 0.0, 91.0);

        return array(
            'range' => array('min' => $min, 'max' => $max),
            'average' => round($average, 2),
            'recent_average' => round($recentAverage, 2),
            'odd_even' => $oddCount >= (count(array_slice($values, 0, min(10, count($values)))) / 2) ? '奇' : '偶',
            'big_small' => $bigCount >= (count(array_slice($values, 0, min(10, count($values)))) / 2) ? '大' : '小',
            'confidence' => round($confidence, 2),
        );
    }

    protected function lowestAttributeTargets(array $draws, $field, $limit)
    {
        $draws = $this->normalizeDraws($draws);
        $labels = array();
        foreach ($this->numberAttributes2026() as $attributes) {
            $label = (string) ($attributes[$field] ?? '');
            if ($label !== '') {
                $labels[$label] = 0.0;
            }
        }
        foreach ($draws as $draw) {
            $special = (int) ($draw['parts']['special'] ?? 0);
            $label = (string) ($this->numberAttributes($special)[$field] ?? '');
            if ($label !== '' && isset($labels[$label])) {
                $labels[$label] += 1.0;
            }
        }
        asort($labels);

        return array_slice(array_keys($labels), 0, max(1, (int) $limit));
    }

    protected function formatNumberOmissionRows(array $omission, array $reboundScores)
    {
        arsort($reboundScores);
        $rows = array();
        foreach (array_slice($reboundScores, 0, 16, true) as $number => $score) {
            $number = (int) $number;
            $rows[] = array(
                'number' => $number,
                'current_omission' => (int) ($omission[$number]['current'] ?? 0),
                'max_omission' => (int) ($omission[$number]['max'] ?? 0),
                'average_omission' => (float) ($omission[$number]['average'] ?? 0.0),
                'rebound_score' => round((float) $score, 2),
            );
        }

        return $rows;
    }

    protected function buildDanTuoCombinations(array $danNumbers, array $dragNumbers, $limit)
    {
        $limit = max(1, min(30, (int) $limit));
        $danNumbers = array_values(array_unique(array_map('intval', $danNumbers)));
        $dragNumbers = array_values(array_unique(array_map('intval', $dragNumbers)));
        $needed = max(0, 6 - count($danNumbers));
        if ($needed <= 0) {
            return array(array_slice($danNumbers, 0, 6));
        }

        $combinations = array();
        $dragNumbers = array_slice($dragNumbers, 0, min(14, count($dragNumbers)));
        $this->combineDragNumbers($dragNumbers, $needed, 0, array(), $danNumbers, $limit, $combinations);

        return $combinations;
    }

    protected function combineDragNumbers(array $dragNumbers, $needed, $offset, array $picked, array $danNumbers, $limit, array &$combinations)
    {
        if (count($combinations) >= (int) $limit) {
            return;
        }
        if (count($picked) === (int) $needed) {
            $combo = array_values(array_unique(array_merge($danNumbers, $picked)));
            sort($combo);
            $combinations[] = $combo;
            return;
        }
        for ($i = (int) $offset; $i < count($dragNumbers); $i++) {
            $picked[] = (int) $dragNumbers[$i];
            $this->combineDragNumbers($dragNumbers, $needed, $i + 1, $picked, $danNumbers, $limit, $combinations);
            array_pop($picked);
            if (count($combinations) >= (int) $limit) {
                return;
            }
        }
    }

    protected function recentAttributeMomentum(array $draws, $field, $target)
    {
        $target = (string) $target;
        if ($target === '') {
            return 0.0;
        }
        $score = 0.0;
        foreach (array_slice($draws, 0, min(6, count($draws))) as $index => $draw) {
            $weight = max(0.2, 1.0 - ((int) $index * 0.12));
            foreach ((array) ($draw['parts']['all'] ?? array()) as $number) {
                if ((string) ($this->numberAttributes((int) $number)[$field] ?? '') === $target) {
                    $score += $weight;
                }
            }
        }

        return $score;
    }

    protected function rankNumbersByScore(array $numbers, array $scores, $descending)
    {
        $numbers = array_values(array_unique(array_map('intval', $numbers)));
        usort($numbers, function ($left, $right) use ($scores, $descending) {
            $leftScore = (float) ($scores[(int) $left] ?? 0.0);
            $rightScore = (float) ($scores[(int) $right] ?? 0.0);
            if ($leftScore === $rightScore) {
                return (int) $left <=> (int) $right;
            }

            return !empty($descending)
                ? ($leftScore < $rightScore ? 1 : -1)
                : ($leftScore < $rightScore ? -1 : 1);
        });

        return $numbers;
    }

    protected function killConfidenceForNumber($number, array $numberScores)
    {
        $number = (int) $number;
        $scores = array_values(array_map('floatval', $numberScores));
        if ($scores === array()) {
            return 60.0;
        }
        $max = max($scores);
        $score = (float) ($numberScores[$number] ?? 0.0);
        $ratio = $max > 0 ? 1.0 - ($score / $max) : 0.5;

        return round($this->clamp(58.0 + ($ratio * 32.0), 55.0, 90.0), 2);
    }

    protected function buildSummaryLine(array $fiveElements, array $killStrategy, array $sumAnalysis, array $spanAnalysis, array $omissionCycle, array $danTuoStrategy)
    {
        $sumRange = (array) ($sumAnalysis['total_sum']['range'] ?? array());
        $spanRange = (array) ($spanAnalysis['total_span']['range'] ?? array());

        return '高级分析：主五行 '
            . (string) ($fiveElements['primary_element'] ?? '')
            . '；绝杀 ' . implode('、', array_map('intval', (array) ($killStrategy['special_numbers'] ?? array())))
            . '；和值 ' . (int) ($sumRange['min'] ?? 0) . '-' . (int) ($sumRange['max'] ?? 0)
            . '；跨度 ' . (int) ($spanRange['min'] ?? 0) . '-' . (int) ($spanRange['max'] ?? 0)
            . '；高遗漏 ' . implode('、', array_slice(array_map('intval', (array) ($omissionCycle['high_omission_numbers'] ?? array())), 0, 6))
            . '；胆码 ' . implode('、', array_map('intval', (array) ($danTuoStrategy['dan_numbers'] ?? array())));
    }

    protected function numberAttributes($number)
    {
        $number = (int) $number;
        $map = $this->numberAttributes2026();

        return isset($map[$number]) ? $map[$number] : array();
    }

    protected function numbersByAttribute($field, $value)
    {
        $numbers = array();
        foreach ($this->numberAttributes2026() as $number => $attributes) {
            if ((string) ($attributes[$field] ?? '') === (string) $value) {
                $numbers[] = (int) $number;
            }
        }

        return $numbers;
    }

    protected function labelForNumber(array $groups, $number)
    {
        $number = (int) $number;
        foreach ($groups as $label => $numbers) {
            if (in_array($number, (array) $numbers, true)) {
                return (string) $label;
            }
        }

        return '';
    }

    protected function sectionLabel($number)
    {
        $number = (int) $number;
        if ($number <= 12) {
            return '一区';
        }
        if ($number <= 24) {
            return '二区';
        }
        if ($number <= 36) {
            return '三区';
        }

        return '四区';
    }

    protected function generatesElement($element)
    {
        $map = array('金' => '水', '水' => '木', '木' => '火', '火' => '土', '土' => '金');
        return (string) ($map[(string) $element] ?? '');
    }

    protected function generatedByElement($element)
    {
        $map = array('水' => '金', '木' => '水', '火' => '木', '土' => '火', '金' => '土');
        return (string) ($map[(string) $element] ?? '');
    }

    protected function overcomesElement($element)
    {
        $map = array('金' => '木', '木' => '土', '土' => '水', '水' => '火', '火' => '金');
        return (string) ($map[(string) $element] ?? '');
    }

    protected function overcomeByElement($element)
    {
        $map = array('木' => '金', '土' => '木', '水' => '土', '火' => '水', '金' => '火');
        return (string) ($map[(string) $element] ?? '');
    }

    protected function assocRanking(array $scores)
    {
        arsort($scores);
        $rows = array();
        foreach ($scores as $label => $score) {
            $rows[] = array('label' => (string) $label, 'score' => round((float) $score, 2));
        }

        return $rows;
    }

    protected function confidenceFromLead(array $scores, $base, $max)
    {
        $values = array_values(array_map('floatval', $scores));
        rsort($values);
        if ($values === array()) {
            return 0.0;
        }
        $lead = (float) $values[0] - (float) ($values[1] ?? 0.0);
        $confidence = (float) $base + min(24.0, $lead * 1.6);

        return round($this->clamp($confidence, 0.0, (float) $max), 2);
    }

    protected function valueInRange($value, array $range)
    {
        return (int) $value >= (int) ($range['min'] ?? 0) && (int) $value <= (int) ($range['max'] ?? 0);
    }

    protected function average(array $values)
    {
        $values = array_values(array_map('floatval', $values));
        return $values !== array() ? array_sum($values) / count($values) : 0.0;
    }

    protected function clamp($value, $min, $max)
    {
        return min((float) $max, max((float) $min, (float) $value));
    }

    protected function rule($path, $default = null)
    {
        $rules = $this->rules();
        $value = $rules;
        foreach (explode('.', (string) $path) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }

    /**
     * 权重优先从 config/forecast_advanced_rules.json 或 YAML 合并，缺失项走内置默认值。
     */
    protected function rules()
    {
        if (is_array($this->rulesCache)) {
            return $this->rulesCache;
        }

        $rules = $this->defaultRules();
        foreach (array('config/forecast_advanced_rules.json', 'config/forecast_advanced_rules.yaml', 'config/forecast_advanced_rules.yml') as $relativePath) {
            $path = $this->app->basePath($relativePath);
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }
            $loaded = array();
            $content = (string) file_get_contents($path);
            if (substr($relativePath, -5) === '.json') {
                $decoded = json_decode($content, true);
                $loaded = is_array($decoded) ? $decoded : array();
            } else {
                $loaded = $this->parseSimpleYaml($content);
            }
            if ($loaded !== array()) {
                $rules = array_replace_recursive($rules, $loaded);
            }
        }

        $this->rulesCache = $rules;
        return $this->rulesCache;
    }

    protected function defaultRules()
    {
        return array(
            'version' => self::VERSION,
            'weights' => array(
                'number' => array(
                    'normal' => 10.0,
                    'special' => 12.0,
                    'all' => 2.5,
                    'current_omission' => 0.7,
                    'average_omission' => 0.35,
                ),
                'five_elements' => array(
                    'normal' => 1.0,
                    'special' => 2.4,
                    'current_omission' => 0.7,
                    'max_omission' => 0.25,
                    'avg_omission' => 0.2,
                    'generating' => 0.6,
                    'overcoming' => -0.35,
                ),
                'omission' => array(
                    'current_vs_max' => 48.0,
                    'current_vs_average' => 18.0,
                ),
            ),
            'kill' => array(
                'special_number_count' => 6,
            ),
            'omission' => array(
                'top_count' => 10,
            ),
            'dan_tuo' => array(
                'drag_count' => 14,
                'combo_limit' => 12,
            ),
        );
    }

    protected function parseSimpleYaml($content)
    {
        if (function_exists('yaml_parse')) {
            $parsed = @yaml_parse((string) $content);
            return is_array($parsed) ? $parsed : array();
        }

        $result = array();
        $pathStack = array();
        foreach (preg_split('/\r\n|\r|\n/', (string) $content) as $line) {
            $line = rtrim((string) $line);
            if (trim($line) === '' || preg_match('/^\s*#/', $line)) {
                continue;
            }
            if (!preg_match('/^(\s*)([A-Za-z0-9_.-]+)\s*:\s*(.*)$/', $line, $matches)) {
                continue;
            }
            $level = (int) floor(strlen((string) $matches[1]) / 2);
            $key = (string) $matches[2];
            $rawValue = trim((string) $matches[3]);
            $pathStack[$level] = $key;
            foreach (array_keys($pathStack) as $stackLevel) {
                if ((int) $stackLevel > $level) {
                    unset($pathStack[$stackLevel]);
                }
            }
            $path = array();
            for ($i = 0; $i <= $level; $i++) {
                if (isset($pathStack[$i])) {
                    $path[] = $pathStack[$i];
                }
            }
            $this->setNestedValue($result, $path, $rawValue === '' ? array() : $this->parseYamlScalar($rawValue));
        }

        return $result;
    }

    protected function parseYamlScalar($value)
    {
        $value = trim((string) $value);
        if ($value === 'true') {
            return true;
        }
        if ($value === 'false') {
            return false;
        }
        if ($value === 'null') {
            return null;
        }
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float) $value : (int) $value;
        }
        if (strlen($value) >= 2 && $value[0] === '[' && substr($value, -1) === ']') {
            $items = trim(substr($value, 1, -1));
            if ($items === '') {
                return array();
            }
            return array_map(function ($item) {
                return trim((string) $item, " \t\n\r\0\x0B\"'");
            }, explode(',', $items));
        }

        return trim($value, "\"'");
    }

    protected function setNestedValue(array &$target, array $path, $value)
    {
        $cursor = &$target;
        foreach ($path as $index => $part) {
            if ($index === count($path) - 1) {
                $cursor[$part] = $value;
                return;
            }
            if (!isset($cursor[$part]) || !is_array($cursor[$part])) {
                $cursor[$part] = array();
            }
            $cursor = &$cursor[$part];
        }
    }
}
