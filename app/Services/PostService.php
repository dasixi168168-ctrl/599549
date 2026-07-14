<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Security;
use RuntimeException;

class PostService extends Service
{
    public function displayTitle(array $post)
    {
        $segments = $this->displayTitleSegments($post);
        $titleBody = $segments['prefix'] . $segments['middle'] . $segments['author'];
        $issuePrefixText = $this->formatIssuePrefixText($segments['issue']);

        if ($titleBody === '') {
            return $issuePrefixText;
        }

        if ($issuePrefixText === '') {
            return $titleBody;
        }

        return $issuePrefixText . $titleBody;
    }

    public function displayTitleSegments(array $post)
    {
        $titleBody = $this->stripSyncedIssuePrefix((string) ($post['title'] ?? ''));
        $authorName = trim((string) ($post['author_name'] ?? ''));
        $issueText = $this->displayIssueTail($post);
        $titlePrefix = trim((string) ($post['title_prefix_text'] ?? $post['manage_title_prefix_text'] ?? ''));
        $titleMiddle = trim((string) ($post['title_middle_text'] ?? $post['manage_title_middle_text'] ?? ''));
        if ($titlePrefix !== '' && $titleMiddle === '') {
            foreach (preg_split('/\R/u', trim((string) ($post['full_content'] ?? ''))) as $contentRow) {
                $contentRow = trim((string) $contentRow);
                if ($contentRow === '') {
                    continue;
                }
                if (preg_match('/^\d{1,6}[^:：]*[:：]\s*\S+\s{2,}(.+?)\s{2,}.+?\s{2,}开[:：]/u', $contentRow, $matches)) {
                    $titleMiddle = trim((string) ($matches[1] ?? ''));
                    break;
                }
            }
        }

        if ($titlePrefix === '' && $titleMiddle === '') {
            if ($authorName !== '' && mb_strpos($titleBody, $authorName, 0, 'UTF-8') === false) {
                $titleBody .= $authorName;
            }

            return array(
                'issue' => $issueText,
                'prefix' => $titleBody,
                'middle' => '',
                'author' => '',
            );
        }

        if ($titleMiddle !== '') {
            $leftWrap = '';
            $rightWrap = '';
            $middleCore = $titleMiddle;
            if (preg_match('/^([【〖《｛〔『])\s*(.+?)\s*([】〗》｝〕』])$/u', $titleMiddle, $wrapMatches)) {
                $leftWrap = (string) ($wrapMatches[1] ?? '');
                $middleCore = trim((string) ($wrapMatches[2] ?? ''));
                $rightWrap = (string) ($wrapMatches[3] ?? '');
            }

            if ($middleCore !== '') {
                $seedSource = (string) ($post['region'] ?? '')
                    . '|'
                    . (string) ($post['id'] ?? '')
                    . '|'
                    . $middleCore;
                $seed = (int) sprintf('%u', crc32($seedSource));
                $pickStable = static function (array $items, $salt) use ($seed) {
                    $items = array_values($items);
                    if ($items === array()) {
                        return '';
                    }

                    return $items[(int) sprintf('%u', crc32((string) $seed . '|' . (string) $salt)) % count($items)];
                };
                $parseTitleNumber = static function ($value) {
                    $value = trim((string) $value);
                    if ($value === '') {
                        return 0;
                    }
                    if (preg_match('/^\d+$/', $value)) {
                        return (int) $value;
                    }

                    $numberMap = array(
                        '①' => 1,
                        '②' => 2,
                        '③' => 3,
                        '④' => 4,
                        '⑤' => 5,
                        '⑥' => 6,
                        '⑦' => 7,
                        '⑧' => 8,
                        '⑨' => 9,
                        '⑩' => 10,
                        '⑪' => 11,
                        '⑫' => 12,
                        '⑬' => 13,
                        '⑭' => 14,
                        '⑮' => 15,
                        '⑯' => 16,
                        '⑰' => 17,
                        '⑱' => 18,
                        '⑲' => 19,
                        '⑳' => 20,
                        '一' => 1,
                        '二' => 2,
                        '两' => 2,
                        '三' => 3,
                        '四' => 4,
                        '五' => 5,
                        '六' => 6,
                        '七' => 7,
                        '八' => 8,
                        '九' => 9,
                    );
                    if (isset($numberMap[$value])) {
                        return (int) $numberMap[$value];
                    }
                    if ($value === '十') {
                        return 10;
                    }
                    if (preg_match('/^十([一二两三四五六七八九])$/u', $value, $matches)) {
                        return 10 + (int) ($numberMap[(string) ($matches[1] ?? '')] ?? 0);
                    }
                    if (preg_match('/^([一二两三四五六七八九])十$/u', $value, $matches)) {
                        return (int) ($numberMap[(string) ($matches[1] ?? '')] ?? 0) * 10;
                    }
                    if (preg_match('/^([一二两三四五六七八九])十([一二两三四五六七八九])$/u', $value, $matches)) {
                        return ((int) ($numberMap[(string) ($matches[1] ?? '')] ?? 0) * 10)
                            + (int) ($numberMap[(string) ($matches[2] ?? '')] ?? 0);
                    }

                    return 0;
                };
                $titleNumberOptions = static function ($number, $fallback) {
                    $number = (int) $number;
                    $fallback = trim((string) $fallback);
                    $chineseNumbers = array(
                        1 => '一',
                        2 => '二',
                        3 => '三',
                        4 => '四',
                        5 => '五',
                        6 => '六',
                        7 => '七',
                        8 => '八',
                        9 => '九',
                        10 => '十',
                    );
                    $circleNumbers = array(
                        1 => '①',
                        2 => '②',
                        3 => '③',
                        4 => '④',
                        5 => '⑤',
                        6 => '⑥',
                        7 => '⑦',
                        8 => '⑧',
                        9 => '⑨',
                        10 => '⑩',
                        11 => '⑪',
                        12 => '⑫',
                        13 => '⑬',
                        14 => '⑭',
                        15 => '⑮',
                        16 => '⑯',
                        17 => '⑰',
                        18 => '⑱',
                        19 => '⑲',
                        20 => '⑳',
                    );

                    $options = array();
                    $isLongChineseNumber = $number > 10
                        && preg_match('/^[一二两三四五六七八九十]+$/u', $fallback);
                    if ($fallback !== '' && !$isLongChineseNumber) {
                        $options[] = $fallback;
                    }
                    if ($number > 0) {
                        $options[] = (string) $number;
                    }
                    if (isset($chineseNumbers[$number])) {
                        $options[] = $chineseNumbers[$number];
                    }
                    if (isset($circleNumbers[$number])) {
                        $options[] = $circleNumbers[$number];
                    }

                    return array_values(array_unique($options));
                };
                $randomizeTitleNumbers = static function ($text) use ($parseTitleNumber, $titleNumberOptions, $pickStable) {
                    $text = (string) $text;
                    $pattern = '/(\d+|[一二两三四五六七八九十]+|[①②③④⑤⑥⑦⑧⑨⑩⑪⑫⑬⑭⑮⑯⑰⑱⑲⑳])(?=\s*(?:码|肖|组|中|连|头|尾|波|行|段|季|不中|$))/u';
                    if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                        return $text;
                    }

                    $result = '';
                    $offset = 0;
                    foreach ((array) ($matches[0] ?? array()) as $index => $match) {
                        $value = (string) ($match[0] ?? '');
                        $position = (int) ($match[1] ?? 0);
                        if ($position > $offset) {
                            $result .= substr($text, $offset, $position - $offset);
                        }

                        $number = $parseTitleNumber($value);
                        $result .= $pickStable($titleNumberOptions($number, $value), 'number-' . (string) $index . '-' . $value);
                        $offset = $position + strlen($value);
                    }
                    if ($offset < strlen($text)) {
                        $result .= substr($text, $offset);
                    }

                    return $result;
                };
                $normalizeHitPairTitleNumbers = static function ($text) use ($parseTitleNumber, $pickStable) {
                    $parseHitPairNumber = static function ($value) use ($parseTitleNumber) {
                        $value = trim((string) $value);
                        if ($value === '') {
                            return 0;
                        }

                        $value = strtr($value, array(
                            '０' => '0',
                            '１' => '1',
                            '２' => '2',
                            '３' => '3',
                            '４' => '4',
                            '５' => '5',
                            '６' => '6',
                            '７' => '7',
                            '８' => '8',
                            '９' => '9',
                        ));
                        $decoratedNumbers = array(
                            '①' => 1,
                            '②' => 2,
                            '③' => 3,
                            '④' => 4,
                            '⑤' => 5,
                            '⑥' => 6,
                            '⑦' => 7,
                            '⑧' => 8,
                            '⑨' => 9,
                            '⑩' => 10,
                            '⑪' => 11,
                            '⑫' => 12,
                            '⑬' => 13,
                            '⑭' => 14,
                            '⑮' => 15,
                            '⑯' => 16,
                            '⑰' => 17,
                            '⑱' => 18,
                            '⑲' => 19,
                            '⑳' => 20,
                            '㈠' => 1,
                            '㈡' => 2,
                            '㈢' => 3,
                            '㈣' => 4,
                            '㈤' => 5,
                            '㈥' => 6,
                            '㈦' => 7,
                            '㈧' => 8,
                            '㈨' => 9,
                            '㈩' => 10,
                            '⑴' => 1,
                            '⑵' => 2,
                            '⑶' => 3,
                            '⑷' => 4,
                            '⑸' => 5,
                            '⑹' => 6,
                            '⑺' => 7,
                            '⑻' => 8,
                            '⑼' => 9,
                            '⑽' => 10,
                            '⑾' => 11,
                            '⑿' => 12,
                            '⒀' => 13,
                            '⒁' => 14,
                            '⒂' => 15,
                            '⒃' => 16,
                            '⒄' => 17,
                            '⒅' => 18,
                            '⒆' => 19,
                            '⒇' => 20,
                            '⒈' => 1,
                            '⒉' => 2,
                            '⒊' => 3,
                            '⒋' => 4,
                            '⒌' => 5,
                            '⒍' => 6,
                            '⒎' => 7,
                            '⒏' => 8,
                            '⒐' => 9,
                            '⒑' => 10,
                            '⒒' => 11,
                            '⒓' => 12,
                            '⒔' => 13,
                            '⒕' => 14,
                            '⒖' => 15,
                            '⒗' => 16,
                            '⒘' => 17,
                            '⒙' => 18,
                            '⒚' => 19,
                            '⒛' => 20,
                        );
                        if (isset($decoratedNumbers[$value])) {
                            return (int) $decoratedNumbers[$value];
                        }
                        if (preg_match('/^\d+$/', $value)) {
                            return (int) $value;
                        }

                        return $parseTitleNumber($value);
                    };
                    $hitPairNumberOptions = static function ($number) {
                        $number = (int) $number;
                        if ($number <= 0) {
                            return array();
                        }

                        $digits = (string) $number;
                        $fullWidthDigits = strtr($digits, array(
                            '0' => '０',
                            '1' => '１',
                            '2' => '２',
                            '3' => '３',
                            '4' => '４',
                            '5' => '５',
                            '6' => '６',
                            '7' => '７',
                            '8' => '８',
                            '9' => '９',
                        ));
                        $chineseNumbers = array(
                            1 => '一',
                            2 => '二',
                            3 => '三',
                            4 => '四',
                            5 => '五',
                            6 => '六',
                            7 => '七',
                            8 => '八',
                            9 => '九',
                            10 => '十',
                        );
                        $circleNumbers = array(
                            1 => '①',
                            2 => '②',
                            3 => '③',
                            4 => '④',
                            5 => '⑤',
                            6 => '⑥',
                            7 => '⑦',
                            8 => '⑧',
                            9 => '⑨',
                            10 => '⑩',
                            11 => '⑪',
                            12 => '⑫',
                            13 => '⑬',
                            14 => '⑭',
                            15 => '⑮',
                            16 => '⑯',
                            17 => '⑰',
                            18 => '⑱',
                            19 => '⑲',
                            20 => '⑳',
                        );
                        $parenthesizedChineseNumbers = array(
                            1 => '㈠',
                            2 => '㈡',
                            3 => '㈢',
                            4 => '㈣',
                            5 => '㈤',
                            6 => '㈥',
                            7 => '㈦',
                            8 => '㈧',
                            9 => '㈨',
                            10 => '㈩',
                        );
                        $parenthesizedNumbers = array(
                            1 => '⑴',
                            2 => '⑵',
                            3 => '⑶',
                            4 => '⑷',
                            5 => '⑸',
                            6 => '⑹',
                            7 => '⑺',
                            8 => '⑻',
                            9 => '⑼',
                            10 => '⑽',
                            11 => '⑾',
                            12 => '⑿',
                            13 => '⒀',
                            14 => '⒁',
                            15 => '⒂',
                            16 => '⒃',
                            17 => '⒄',
                            18 => '⒅',
                            19 => '⒆',
                            20 => '⒇',
                        );
                        $dottedNumbers = array(
                            1 => '⒈',
                            2 => '⒉',
                            3 => '⒊',
                            4 => '⒋',
                            5 => '⒌',
                            6 => '⒍',
                            7 => '⒎',
                            8 => '⒏',
                            9 => '⒐',
                            10 => '⒑',
                            11 => '⒒',
                            12 => '⒓',
                            13 => '⒔',
                            14 => '⒕',
                            15 => '⒖',
                            16 => '⒗',
                            17 => '⒘',
                            18 => '⒙',
                            19 => '⒚',
                            20 => '⒛',
                        );
                        $options = array($digits, $fullWidthDigits);
                        if (isset($chineseNumbers[$number])) {
                            $options[] = $chineseNumbers[$number];
                        }
                        if (isset($circleNumbers[$number])) {
                            $options[] = $circleNumbers[$number];
                        }
                        if (isset($parenthesizedChineseNumbers[$number])) {
                            $options[] = $parenthesizedChineseNumbers[$number];
                        }
                        if (isset($parenthesizedNumbers[$number])) {
                            $options[] = $parenthesizedNumbers[$number];
                        }
                        if (isset($dottedNumbers[$number])) {
                            $options[] = $dottedNumbers[$number];
                        }

                        return array_values(array_unique($options));
                    };
                    $numberPattern = '[0-9０-９]+|[零一二两三四五六七八九十]+|[①-⑳㈠-㈩⑴-⒇⒈-⒛]';

                    return preg_replace_callback(
                        '/(' . $numberPattern . ')中(' . $numberPattern . ')/u',
                        static function ($matches) use ($parseHitPairNumber, $hitPairNumberOptions, $pickStable) {
                            $leftNumber = $parseHitPairNumber((string) ($matches[1] ?? ''));
                            $rightNumber = $parseHitPairNumber((string) ($matches[2] ?? ''));
                            if ($leftNumber <= 0 || $leftNumber !== $rightNumber) {
                                return (string) ($matches[0] ?? '');
                            }

                            $numberText = $pickStable(
                                $hitPairNumberOptions($leftNumber),
                                'hit-pair-number-' . (string) $leftNumber
                            );

                            return $numberText . '中' . $numberText;
                        },
                        (string) $text
                    );
                };

                $displayMiddleCore = $middleCore;
                if (preg_match('/^精选(.+?)(码|肖)$/u', $middleCore, $typeMatches)) {
                    $numberValue = (string) ($typeMatches[1] ?? '');
                    $unitText = (string) ($typeMatches[2] ?? '');
                    $numberText = $pickStable(
                        $titleNumberOptions($parseTitleNumber($numberValue), $numberValue),
                        'select-number'
                    );
                    $wordText = $pickStable(array('精选', '优选', '严选', '准选'), 'select-word');
                    $selectVariants = array($wordText . $numberText . $unitText, $numberText . $unitText . $wordText);
                    if (count($selectVariants) > 1) {
                        $filteredSelectVariants = array();
                        foreach ($selectVariants as $selectVariant) {
                            if ($selectVariant !== $middleCore) {
                                $filteredSelectVariants[] = $selectVariant;
                            }
                        }
                        if ($filteredSelectVariants !== array()) {
                            $selectVariants = $filteredSelectVariants;
                        }
                    }
                    $displayMiddleCore = $pickStable($selectVariants, 'select-order');
                } elseif (preg_match('/^(.+?)(码|肖|头|尾|波|行|段|季)中特$/u', $middleCore, $typeMatches)) {
                    $numberValue = (string) ($typeMatches[1] ?? '');
                    $unitText = (string) ($typeMatches[2] ?? '');
                    $numberText = $randomizeTitleNumbers($numberValue);
                    $wordText = $pickStable(array('中特', '必中'), 'hit-word');
                    $hitVariants = array($numberText . $unitText . $wordText, $wordText . $numberText . $unitText);
                    if (count($hitVariants) > 1) {
                        $filteredHitVariants = array();
                        foreach ($hitVariants as $hitVariant) {
                            if ($hitVariant !== $middleCore) {
                                $filteredHitVariants[] = $hitVariant;
                            }
                        }
                        if ($filteredHitVariants !== array()) {
                            $hitVariants = $filteredHitVariants;
                        }
                    }
                    $displayMiddleCore = $pickStable($hitVariants, 'hit-order');
                } else {
                    $displayMiddleCore = $randomizeTitleNumbers($displayMiddleCore);
                    if (preg_match('/^(.+)中特$/u', $displayMiddleCore, $hitMatches)) {
                        $plainHitWords = array('中特', '必中');
                        if ($displayMiddleCore === $middleCore) {
                            $plainHitWords = array('必中');
                        }
                        $displayMiddleCore = (string) ($hitMatches[1] ?? '')
                            . $pickStable($plainHitWords, 'plain-hit-word');
                    }
                }

                $displayMiddleCore = $normalizeHitPairTitleNumbers($displayMiddleCore);
                $titleMiddle = $leftWrap . $displayMiddleCore . $rightWrap;
            }
        }

        return array(
            'issue' => $issueText,
            'prefix' => $titlePrefix,
            'middle' => $titleMiddle,
            'author' => $authorName,
        );
    }

    public function displayIssueTail(array $post)
    {
        $contentIssueTail = $this->latestIssueTailFromContent((string) ($post['full_content'] ?? ''));
        if ($contentIssueTail !== '') {
            return $contentIssueTail;
        }

        return $this->normalizeIssueTail($this->latestIssueTextByRegion((string) ($post['region'] ?? 'macau')));
    }

    public function displayIssuePrefixText(array $post)
    {
        return $this->formatIssuePrefixText($this->displayIssueTail($post));
    }

    public function displayArchivedIssueTail(array $post, $nextIssueFallback = '')
    {
        $openedIssueTail = $this->latestOpenedIssueTailFromContent((string) ($post['full_content'] ?? ''));
        if ($openedIssueTail !== '') {
            return $openedIssueTail;
        }

        $nextIssueTail = $this->normalizeIssueTail((string) $nextIssueFallback);
        if ($nextIssueTail !== '' && (int) $nextIssueTail > 0) {
            return str_pad((string) ((int) $nextIssueTail - 1), strlen($nextIssueTail), '0', STR_PAD_LEFT);
        }

        return $this->normalizeIssueTail($this->latestIssueTextByRegion((string) ($post['region'] ?? 'macau')));
    }

    public function displayIssueTailForRecordTime($region, $recordAt, $fallbackIssueText = '')
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $recordAt = trim((string) $recordAt);
        $fallbackTail = $this->normalizeIssueTail((string) $fallbackIssueText);

        if ($recordAt === '' || strtotime($recordAt) === false) {
            return $fallbackTail;
        }
        $recordDate = date('Y-m-d', strtotime($recordAt));

        if ($this->tableExists('lottery_issues')) {
            try {
                $issue = $this->db()->fetch(
                    'SELECT issue_no
                     FROM lottery_issues
                     WHERE region = :region
                       AND COALESCE(actual_open_at, planned_open_at) IS NOT NULL
                       AND DATE(COALESCE(actual_open_at, planned_open_at)) = :record_date
                     ORDER BY COALESCE(actual_open_at, planned_open_at) DESC,
                              CAST(issue_no AS UNSIGNED) DESC,
                              id DESC
                     LIMIT 1',
                    array(
                        'region' => $region,
                        'record_date' => $recordDate,
                    )
                );

                $issueTail = $issue ? $this->normalizeIssueTail((string) ($issue['issue_no'] ?? '')) : '';
                if ($issueTail !== '') {
                    return $issueTail;
                }
            } catch (\Throwable $exception) {
            }
        }

        if ($this->tableExists('lottery_draws')) {
            try {
                $draw = $this->db()->fetch(
                    'SELECT issue_no
                     FROM lottery_draws
                     WHERE region = :region
                       AND draw_date = :draw_date
                     ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
                     LIMIT 1',
                    array(
                        'region' => $region,
                        'draw_date' => $recordDate,
                    )
                );
                if ($draw && trim((string) ($draw['issue_no'] ?? '')) !== '') {
                    return $this->normalizeIssueTail((string) $draw['issue_no']);
                }
            } catch (\Throwable $exception) {
            }
        }

        if ($fallbackTail !== '') {
            return $fallbackTail;
        }

        return $this->normalizeIssueTail($this->latestIssueTextByRegion($region));
    }

    public function formatIssuePrefixText($issueNo)
    {
        $issueTail = $this->normalizeIssueTail($issueNo);
        if ($issueTail === '') {
            return '';
        }

        return $issueTail . html_entity_decode('&#26399;&#65306;', ENT_QUOTES, 'UTF-8');
    }

    public function customerServiceEditableContent(array $post)
    {
        return $this->extractCurrentIssueContent(
            (string) ($post['full_content'] ?? ''),
            (string) ($post['region'] ?? 'macau')
        );
    }

    public function currentIssueEditorPayload(array $post, $targetIssueText = '')
    {
        $issueText = trim((string) $targetIssueText);
        if ($issueText === '') {
            $issueText = $this->latestIssueTextByRegion((string) ($post['region'] ?? 'macau'));
        }
        $currentContent = $this->extractCurrentIssueContent(
            (string) ($post['full_content'] ?? ''),
            (string) ($post['region'] ?? 'macau'),
            $issueText
        );
        $record = $this->parseForecastRecordContent($currentContent);
        $isWaiting = mb_strpos($currentContent, '资料等待更新中', 0, 'UTF-8') !== false;
        $issueLabel = is_array($record) && trim((string) ($record['issue_prefix'] ?? '')) !== ''
            ? trim((string) $record['issue_prefix'])
            : $issueText;
        $content = is_array($record) ? trim((string) ($record['prediction'] ?? '')) : trim($currentContent);
        if ($isWaiting && is_array($record)) {
            $historicalPredictionLayout = $this->forecastPredictionTemplateLayout(
                (string) ($post['full_content'] ?? ''),
                (string) ($record['issue_prefix'] ?? '')
            );
            $quantityTemplate = $this->customerServicePredictionQuantityTemplate(
                trim((string) ($record['type'] ?? '')),
                $content,
                $historicalPredictionLayout
            );
            if ($quantityTemplate !== '') {
                $content = $quantityTemplate;
            }
        }

        return array(
            'issue_text' => $issueLabel,
            'content' => $content,
            'current_record' => trim($currentContent),
            'is_waiting' => $isWaiting,
        );
    }

    public function customerServiceEditPayload(array $post, $targetIssueText = '')
    {
        $issueText = trim((string) $targetIssueText);
        $requestedIssueText = $issueText;
        if ($issueText === '') {
            $issueText = $this->latestIssueTextByRegion((string) ($post['region'] ?? 'macau'));
        }
        $currentContent = $this->extractCurrentIssueContent(
            (string) ($post['full_content'] ?? ''),
            (string) ($post['region'] ?? 'macau'),
            $issueText
        );
        $record = $this->parseForecastRecordContent($currentContent);
        $issueLabel = is_array($record) && trim((string) $record['issue_prefix']) !== ''
            ? trim((string) $record['issue_prefix'])
            : $issueText;
        if ($requestedIssueText !== '') {
            $issueLabel = $requestedIssueText;
        }
        $content = is_array($record) ? (string) $record['prediction'] : $currentContent;
        $historicalPredictionLayout = array();
        if (is_array($record)) {
            $typeText = trim((string) ($record['type'] ?? ''));
            $predictionText = trim((string) ($record['prediction'] ?? ''));
            $historicalPredictionLayout = $this->forecastPredictionTemplateLayout(
                (string) ($post['full_content'] ?? ''),
                (string) ($record['issue_prefix'] ?? '')
            );
            $quantityTemplate = $this->customerServicePredictionQuantityTemplate(
                $typeText,
                $predictionText,
                $historicalPredictionLayout
            );
            if ($quantityTemplate !== '') {
                $content = $quantityTemplate;
            }
        }
        if (is_array($record) && mb_strpos($content, '资料等待更新中', 0, 'UTF-8') !== false) {
            $predictionBracketPairs = array(
                array('【', '】'),
                array('〖', '〗'),
                array('《', '》'),
                array('｛', '｝'),
                array('〔', '〕'),
                array('『', '』'),
            );
            $contentText = trim((string) $content);
            $contentFirstChar = mb_substr($contentText, 0, 1, 'UTF-8');
            $contentLastChar = mb_substr($contentText, -1, 1, 'UTF-8');
            $contentWrapped = false;
            foreach ($predictionBracketPairs as $predictionBracketPair) {
                $predictionLeftBracket = (string) ($predictionBracketPair[0] ?? '');
                $predictionRightBracket = (string) ($predictionBracketPair[1] ?? '');
                if (
                    $predictionLeftBracket !== ''
                    && $predictionRightBracket !== ''
                    && $contentFirstChar === $predictionLeftBracket
                    && $contentLastChar === $predictionRightBracket
                ) {
                    $contentWrapped = true;
                    break;
                }
            }

            if (!$contentWrapped && $contentText !== '') {
                $predictionLeftBracket = '';
                $predictionRightBracket = '';
                $historicalBracketPair = (array) ($historicalPredictionLayout['bracket_pair'] ?? array());
                if ($historicalBracketPair !== array()) {
                    $predictionLeftBracket = (string) ($historicalBracketPair[0] ?? '');
                    $predictionRightBracket = (string) ($historicalBracketPair[1] ?? '');
                }
                if ($predictionLeftBracket === '' || $predictionRightBracket === '') {
                    $predictionLeftBracket = '【';
                    $predictionRightBracket = '】';
                }
                if ($predictionLeftBracket !== '' && $predictionRightBracket !== '') {
                    $content = $predictionLeftBracket . $contentText . $predictionRightBracket;
                }
            }
        }

        return array(
            'issue_text' => $issueLabel,
            'content' => $content,
        );
    }

    protected function customerServiceHistoricalPredictionBracketPair($content, $currentIssuePrefix = '')
    {
        $layout = $this->forecastPredictionTemplateLayout($content, $currentIssuePrefix);

        return (array) ($layout['bracket_pair'] ?? array());
    }

    public function forecastPredictionTemplateLayout($content, $currentIssuePrefix = '')
    {
        $content = trim((string) $content);
        if ($content === '') {
            return array();
        }

        $currentIssueDigits = preg_replace('/\D+/', '', (string) $currentIssuePrefix);
        $pairs = array(
            array('【', '】'),
            array('〖', '〗'),
            array('《', '》'),
            array('｛', '｝'),
            array('〔', '〕'),
            array('『', '』'),
            array('{', '}'),
        );
        $lines = preg_split('/\R/u', $content);
        if (!is_array($lines)) {
            return array();
        }

        $blocks = array();
        $currentBlock = array();
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^\s*\d{1,6}[^:：]{0,12}[:：]/u', $line) && $currentBlock !== array()) {
                $blocks[] = $currentBlock;
                $currentBlock = array();
            }
            $currentBlock[] = $line;
        }
        if ($currentBlock !== array()) {
            $blocks[] = $currentBlock;
        }

        foreach ($blocks as $blockLines) {
            $recordIssueDigits = '';
            $firstBlockLine = trim((string) ((array) $blockLines)[0]);
            if (preg_match('/^\s*(\d{1,6})/u', $firstBlockLine, $issueMatches)) {
                $recordIssueDigits = preg_replace('/\D+/', '', (string) ($issueMatches[1] ?? ''));
            }
            if ($currentIssueDigits !== '' && $recordIssueDigits === $currentIssueDigits) {
                continue;
            }

            foreach ($pairs as $pair) {
                $left = (string) ($pair[0] ?? '');
                $right = (string) ($pair[1] ?? '');
                $numberRowSizes = $this->customerServiceHistoricalPredictionNumberRowSizes((array) $blockLines, $left, $right);
                if ($numberRowSizes !== array()) {
                    return array(
                        'bracket_pair' => array($left, $right),
                        'number_row_sizes' => $numberRowSizes,
                    );
                }
            }
        }

        return array();
    }

    protected function customerServiceHistoricalPredictionLayout($content, $currentIssuePrefix = '')
    {
        return $this->forecastPredictionTemplateLayout($content, $currentIssuePrefix);
    }

    protected function customerServiceHistoricalPredictionNumberRowSizes(array $lines, $leftBracket, $rightBracket)
    {
        $leftBracket = (string) $leftBracket;
        $rightBracket = (string) $rightBracket;
        if ($leftBracket === '' || $rightBracket === '') {
            return array();
        }

        $pattern = '/' . preg_quote($leftBracket, '/') . '\s*(.*?)\s*' . preg_quote($rightBracket, '/') . '/u';
        $rowSizes = array();
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || !preg_match_all($pattern, $line, $matches)) {
                continue;
            }
            foreach ((array) ($matches[1] ?? array()) as $groupText) {
                $groupText = trim((string) $groupText);
                $numberTokens = $this->forecastPredictionNumberTokens($groupText);
                if (
                    count($numberTokens) <= 1
                    && !preg_match('/\s/u', $groupText)
                    && preg_match('/(?:精选|优选|码|肖|中|波|尾|头|单双|大小|绝杀|平特)/u', $groupText)
                ) {
                    continue;
                }
                $rowSizes[] = count($numberTokens);
            }
        }

        return $rowSizes;
    }

    protected function forecastPredictionBracketGroups($prediction)
    {
        $prediction = trim((string) $prediction);
        if ($prediction === '') {
            return array();
        }

        $pattern = '/[【〖《｛〔『{]\s*([^【〖《｛〔『{】〗》｝〕』}]+?)\s*[】〗》｝〕』}]/u';
        if (!preg_match_all($pattern, $prediction, $matches)) {
            return array();
        }

        $groups = array();
        foreach ((array) ($matches[1] ?? array()) as $groupText) {
            $groupText = trim((string) $groupText);
            $numbers = $this->forecastPredictionNumberTokens($groupText);
            $groups[] = array(
                'text' => $groupText,
                'numbers' => $numbers,
            );
        }

        return $groups;
    }

    protected function forecastPredictionNumberTokens($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return array();
        }

        preg_match_all('/(?<!\d)(0?[1-9]|[1-4]\d)(?![\d头尾])/u', $text, $matches);
        $numbers = array();
        foreach ((array) ($matches[1] ?? array()) as $numberText) {
            $number = (int) $numberText;
            if ($number >= 1 && $number <= 49) {
                $numbers[] = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            }
        }

        return $numbers;
    }

    public function applyForecastPredictionTemplateLayout($prediction, array $layout = array(), array $fallbackBracketPair = array())
    {
        $prediction = trim((string) $prediction);
        if ($prediction === '') {
            return '';
        }

        $bracketPair = isset($layout['bracket_pair']) && is_array($layout['bracket_pair'])
            ? (array) $layout['bracket_pair']
            : array();
        if ($bracketPair === array() && $fallbackBracketPair !== array()) {
            $bracketPair = array_values($fallbackBracketPair);
        }

        $leftBracket = (string) ($bracketPair[0] ?? '');
        $rightBracket = (string) ($bracketPair[1] ?? '');
        if ($leftBracket === '' || $rightBracket === '') {
            return $prediction;
        }

        $rowSizes = isset($layout['number_row_sizes']) && is_array($layout['number_row_sizes'])
            ? array_values((array) $layout['number_row_sizes'])
            : array();
        if ($rowSizes !== array()) {
            $cleanRowSizes = array();
            $numberTotal = 0;
            foreach ($rowSizes as $rowSize) {
                $rowSize = max(0, (int) $rowSize);
                $cleanRowSizes[] = $rowSize;
                $numberTotal += $rowSize;
            }

            if ($numberTotal > 0) {
                $predictionGroups = $this->forecastPredictionBracketGroups($prediction);
                $attributeGroups = array();
                $numbers = array();
                foreach ($predictionGroups as $predictionGroup) {
                    $groupNumbers = (array) ($predictionGroup['numbers'] ?? array());
                    if ($groupNumbers === array()) {
                        $groupText = trim((string) ($predictionGroup['text'] ?? ''));
                        if ($groupText !== '') {
                            $attributeGroups[] = $groupText;
                        }
                        continue;
                    }
                    foreach ($groupNumbers as $groupNumber) {
                        $numbers[] = (string) $groupNumber;
                    }
                }
                if ($numbers === array()) {
                    $numbers = $this->forecastPredictionNumberTokens($prediction);
                }
                if (count($numbers) >= $numberTotal) {
                    $formattedRows = array();
                    $numberOffset = 0;
                    $attributeOffset = 0;
                    $layoutMatched = true;
                    foreach ($cleanRowSizes as $rowSize) {
                        if ($rowSize <= 0) {
                            $attributeText = trim((string) ($attributeGroups[$attributeOffset] ?? ''));
                            if ($attributeText === '') {
                                $layoutMatched = false;
                                break;
                            }
                            $formattedRows[] = $leftBracket . ' ' . $attributeText . ' ' . $rightBracket;
                            $attributeOffset++;
                            continue;
                        }

                        $rowItems = array_slice($numbers, $numberOffset, $rowSize);
                        if (count($rowItems) !== $rowSize) {
                            $layoutMatched = false;
                            break;
                        }
                        $formattedRows[] = $leftBracket . ' ' . implode(' ', $rowItems) . ' ' . $rightBracket;
                        $numberOffset += $rowSize;
                    }

                    if ($layoutMatched && $numberOffset === $numberTotal) {
                        return implode(' ', $formattedRows);
                    }
                }
            }
        }

        $converted = preg_replace_callback(
            '/[【〖《｛〔『{]\s*([^【〖《｛〔『{】〗》｝〕』}]+?)\s*[】〗》｝〕』}]/u',
            static function ($matches) use ($leftBracket, $rightBracket) {
                return $leftBracket . ' ' . trim((string) ($matches[1] ?? '')) . ' ' . $rightBracket;
            },
            $prediction
        );

        return is_string($converted) && $converted !== '' ? $converted : $prediction;
    }

    protected function customerServicePredictionQuantityTemplate($typeText, $predictionText, array $preferredLayout = array())
    {
        $typeText = trim((string) $typeText);
        $predictionText = trim((string) $predictionText);
        $preferredBracketPair = isset($preferredLayout['bracket_pair']) && is_array($preferredLayout['bracket_pair'])
            ? (array) $preferredLayout['bracket_pair']
            : $preferredLayout;
        $preferredNumberRowSizes = isset($preferredLayout['number_row_sizes']) && is_array($preferredLayout['number_row_sizes'])
            ? (array) $preferredLayout['number_row_sizes']
            : array();
        $groupSpec = $this->customerServicePredictionQuantityGroupSpec($typeText);
        $compositeSpec = $this->customerServicePredictionQuantityCompositeSpec($typeText);
        $zodiacOnlyCount = $this->customerServicePredictionQuantityZodiacOnlyCount($typeText);
        $count = $groupSpec !== array()
            ? (int) (($groupSpec['group_count'] ?? 0) * ($groupSpec['group_size'] ?? 0))
            : ($compositeSpec !== array()
                ? (int) ($compositeSpec['number_count'] ?? 0)
                : ($zodiacOnlyCount > 0
                    ? $zodiacOnlyCount
                    : $this->customerServicePredictionQuantityCount($typeText)));
        if ($count <= 0) {
            return '';
        }

        $leftBracket = (string) ($preferredBracketPair[0] ?? '');
        $rightBracket = (string) ($preferredBracketPair[1] ?? '');
        if ($leftBracket === '' || $rightBracket === '') {
            $leftBracket = '【';
            $rightBracket = '】';
        }

        if ($zodiacOnlyCount > 0) {
            return $leftBracket . ' '
                . implode(' ', $this->customerServicePredictionQuantityAttributePlaceholderItems(array(
                    'attribute_type' => 'zodiac',
                    'attribute_count' => $zodiacOnlyCount,
                )))
                . ' ' . $rightBracket;
        }

        $items = $this->customerServicePredictionQuantityPlaceholderItems($count);

        if ($compositeSpec !== array()) {
            $rows = array(
                $leftBracket . ' '
                . implode(' ', $this->customerServicePredictionQuantityAttributePlaceholderItems($compositeSpec))
                . ' ' . $rightBracket,
            );
            $rowSize = max(1, (int) ($compositeSpec['number_row_size'] ?? $count));
            foreach (array_chunk($items, $rowSize) as $rowItems) {
                $rows[] = $leftBracket . ' ' . implode(' ', $rowItems) . ' ' . $rightBracket;
            }

            return implode("\n", $rows);
        }

        if ($groupSpec !== array()) {
            $groupSize = max(1, min(3, (int) ($groupSpec['group_size'] ?? 0)));
            $rows = array();
            foreach (array_chunk($items, $groupSize) as $rowItems) {
                $rows[] = $leftBracket . ' ' . implode(' ', $rowItems) . ' ' . $rightBracket;
            }

            return implode("\n", $rows);
        }

        if ($count <= 10) {
            return $leftBracket . ' ' . implode(' ', $items) . ' ' . $rightBracket;
        }

        $historicalRows = $this->customerServicePredictionQuantityRowsBySizes($items, $preferredNumberRowSizes, $count);
        if ($historicalRows !== array()) {
            $rows = array();
            foreach ($historicalRows as $rowItems) {
                $rows[] = $leftBracket . ' ' . implode(' ', $rowItems) . ' ' . $rightBracket;
            }

            return implode("\n", $rows);
        }

        $rowCount = (int) ceil($count / 10);
        $rowSize = (int) ceil($count / max(1, $rowCount));
        $rows = array();
        foreach (array_chunk($items, max(1, $rowSize)) as $rowItems) {
            $rows[] = $leftBracket . ' ' . implode(' ', $rowItems) . ' ' . $rightBracket;
        }

        return implode("\n", $rows);
    }

    protected function customerServicePredictionQuantityRowsBySizes(array $items, array $rowSizes, $count)
    {
        $count = max(0, min(240, (int) $count));
        if ($count <= 0 || count($items) < $count || $rowSizes === array()) {
            return array();
        }

        $cleanRowSizes = array();
        foreach ($rowSizes as $rowSize) {
            $rowSize = (int) $rowSize;
            if ($rowSize > 0 && $rowSize <= $count) {
                $cleanRowSizes[] = $rowSize;
            }
        }
        if ($cleanRowSizes === array() || array_sum($cleanRowSizes) !== $count) {
            return array();
        }

        $rows = array();
        $offset = 0;
        foreach ($cleanRowSizes as $rowSize) {
            $rowItems = array_slice($items, $offset, $rowSize);
            if (count($rowItems) !== $rowSize) {
                return array();
            }
            $rows[] = $rowItems;
            $offset += $rowSize;
        }

        return $offset === $count ? $rows : array();
    }

    protected function customerServicePredictionQuantityZodiacOnlyCount($typeText)
    {
        $typeText = trim((string) $typeText);
        if ($typeText === '') {
            return 0;
        }

        $normalizeNumber = $this->customerServicePredictionQuantityNumberNormalizer();
        $numberPattern = '[零一二两三四五六七八九十①②③④⑤⑥⑦⑧⑨⑩\d]+';
        if (preg_match('/复式(' . $numberPattern . ')连肖/u', $typeText, $matches)) {
            return max(1, min(12, $normalizeNumber((string) $matches[1])));
        }

        return 0;
    }

    protected function customerServicePredictionQuantityCompositeSpec($typeText)
    {
        $typeText = trim((string) $typeText);
        if ($typeText === '') {
            return array();
        }

        $normalizeNumber = $this->customerServicePredictionQuantityNumberNormalizer();
        $numberPattern = '[零一二两三四五六七八九十①②③④⑤⑥⑦⑧⑨⑩\d]+';
        $matchedSpec = array();
        if (preg_match('/(' . $numberPattern . ')肖(' . $numberPattern . ')码/u', $typeText, $matches)) {
            $matchedSpec = array('attribute_type' => 'zodiac', 'attribute_count' => $normalizeNumber((string) $matches[1]), 'number_count' => $normalizeNumber((string) $matches[2]));
        } elseif (preg_match('/(' . $numberPattern . ')头(' . $numberPattern . ')码/u', $typeText, $matches)) {
            $matchedSpec = array('attribute_type' => 'head', 'attribute_count' => $normalizeNumber((string) $matches[1]), 'number_count' => $normalizeNumber((string) $matches[2]));
        } elseif (preg_match('/(' . $numberPattern . ')尾(' . $numberPattern . ')码/u', $typeText, $matches)) {
            $matchedSpec = array('attribute_type' => 'tail', 'attribute_count' => $normalizeNumber((string) $matches[1]), 'number_count' => $normalizeNumber((string) $matches[2]));
        } elseif (preg_match('/(' . $numberPattern . ')波(' . $numberPattern . ')码/u', $typeText, $matches)) {
            $matchedSpec = array('attribute_type' => 'wave', 'attribute_count' => $normalizeNumber((string) $matches[1]), 'number_count' => $normalizeNumber((string) $matches[2]));
        } elseif (preg_match('/大小(' . $numberPattern . ')码/u', $typeText, $matches)) {
            $matchedSpec = array('attribute_type' => 'big_small', 'attribute_count' => 1, 'number_count' => $normalizeNumber((string) $matches[1]));
        } elseif (preg_match('/单双(' . $numberPattern . ')码/u', $typeText, $matches)) {
            $matchedSpec = array('attribute_type' => 'odd_even', 'attribute_count' => 1, 'number_count' => $normalizeNumber((string) $matches[1]));
        }

        if ($matchedSpec === array()) {
            return array();
        }

        $attributeCount = max(1, min(12, (int) ($matchedSpec['attribute_count'] ?? 0)));
        $numberCount = max(1, min(49, (int) ($matchedSpec['number_count'] ?? 0)));
        $rowSize = $numberCount;
        if ($numberCount > 10 && $numberCount % 6 === 0) {
            $rowSize = 6;
        } elseif ($numberCount > 8 && $numberCount % 5 === 0) {
            $rowSize = 5;
        } elseif ($numberCount > 10) {
            $rowSize = (int) ceil($numberCount / 2);
        }

        return array(
            'attribute_type' => (string) ($matchedSpec['attribute_type'] ?? ''),
            'attribute_count' => $attributeCount,
            'number_count' => $numberCount,
            'number_row_size' => max(1, min(10, $rowSize)),
        );
    }

    protected function customerServicePredictionQuantityAttributeItems(array $spec)
    {
        $type = (string) ($spec['attribute_type'] ?? '');
        $count = max(1, min(12, (int) ($spec['attribute_count'] ?? 1)));
        if ($type === 'zodiac') {
            $pool = array('鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪');
        } elseif ($type === 'head') {
            $pool = array('0头', '1头', '2头', '3头', '4头');
        } elseif ($type === 'tail') {
            $pool = array('0尾', '1尾', '2尾', '3尾', '4尾', '5尾', '6尾', '7尾', '8尾', '9尾');
        } elseif ($type === 'wave') {
            $pool = array('红', '蓝', '绿');
        } elseif ($type === 'big_small') {
            $pool = array('大', '小');
        } elseif ($type === 'odd_even') {
            $pool = array('单', '双');
        } else {
            return array('待选');
        }

        $items = array();
        for ($index = 0; $index < $count; $index++) {
            $items[] = (string) $pool[$index % count($pool)];
        }

        return $items;
    }

    protected function customerServicePredictionQuantityGroupSpec($typeText)
    {
        $typeText = trim((string) $typeText);
        if ($typeText === '') {
            return array();
        }

        $normalizeNumber = $this->customerServicePredictionQuantityNumberNormalizer();
        $numberPattern = '[零一二两三四五六七八九十①②③④⑤⑥⑦⑧⑨⑩\d]+';
        if (preg_match('/(' . $numberPattern . ')组([二三23②③])中\2/u', $typeText, $matches)) {
            $groupCount = max(1, min(80, $normalizeNumber((string) $matches[1])));
            $groupSize = max(1, min(3, $normalizeNumber((string) $matches[2])));

            return array('group_count' => $groupCount, 'group_size' => $groupSize);
        }

        return array();
    }

    protected function customerServicePredictionQuantityItems($predictionText, $count)
    {
        $predictionText = trim((string) $predictionText);
        $count = max(0, min(240, (int) $count));
        if ($predictionText === '' || $count <= 0 || mb_strpos($predictionText, '资料等待更新中', 0, 'UTF-8') !== false) {
            return array();
        }

        preg_match_all('/(?<!\d)(\d{1,2})(?!\d)/u', $predictionText, $matches);
        $items = array();
        foreach ((array) ($matches[1] ?? array()) as $match) {
            $number = (int) $match;
            if ($number >= 1 && $number <= 49) {
                $items[] = str_pad((string) $number, 2, '0', STR_PAD_LEFT);
            }
            if (count($items) >= $count) {
                break;
            }
        }

        return count($items) >= $count ? $items : array();
    }

    protected function customerServicePredictionQuantityPlaceholderItems($count)
    {
        $count = max(0, min(240, (int) $count));
        $items = array();
        for ($index = 0; $index < $count; $index++) {
            $items[] = '--';
        }

        return $items;
    }

    protected function customerServicePredictionQuantityAttributePlaceholderItems(array $spec)
    {
        $type = (string) ($spec['attribute_type'] ?? '');
        $count = max(1, min(12, (int) ($spec['attribute_count'] ?? 1)));
        $placeholder = '--';
        if ($type === 'head') {
            $placeholder = '--头';
        } elseif ($type === 'tail') {
            $placeholder = '--尾';
        } elseif ($type === 'wave') {
            $placeholder = '--波';
        }

        $items = array();
        for ($index = 0; $index < $count; $index++) {
            $items[] = $placeholder;
        }

        return $items;
    }

    protected function customerServicePredictionQuantityCount($typeText)
    {
        $typeText = trim((string) $typeText);
        if ($typeText === '') {
            return 0;
        }

        $normalizeNumber = $this->customerServicePredictionQuantityNumberNormalizer();
        $numberPattern = '[零一二两三四五六七八九十①②③④⑤⑥⑦⑧⑨⑩\d]+';

        if ($typeText === '平码一码') {
            return 1;
        }
        if (preg_match('/平特(' . $numberPattern . ')连/u', $typeText, $matches)) {
            return max(1, min(7, $normalizeNumber((string) $matches[1])));
        }
        if (preg_match('/(' . $numberPattern . ')组([二三23②③])中\2/u', $typeText, $matches)) {
            return max(1, min(240, $normalizeNumber((string) $matches[1]) * $normalizeNumber((string) $matches[2])));
        }
        if (preg_match('/(' . $numberPattern . ')码三中三/u', $typeText, $matches)) {
            return max(1, min(240, $normalizeNumber((string) $matches[1]) + 3));
        }
        if (preg_match('/(' . $numberPattern . ')码复(?:式|试)([二三23②③])中\2/u', $typeText, $matches)) {
            return max(1, min(49, $normalizeNumber((string) $matches[1])));
        }
        if (preg_match('/(?:头|尾|波|大小|单双)(' . $numberPattern . ')码/u', $typeText, $matches)) {
            return max(1, min(49, $normalizeNumber((string) $matches[1])));
        }
        if (preg_match('/(' . $numberPattern . ')\s*码/u', $typeText, $matches)) {
            return max(1, min(240, $normalizeNumber((string) $matches[1])));
        }
        if (preg_match('/(' . $numberPattern . ')\s*肖/u', $typeText, $matches)) {
            return max(1, min(12, $normalizeNumber((string) $matches[1])));
        }
        if (preg_match('/(' . $numberPattern . ')\s*头/u', $typeText, $matches)) {
            return max(1, min(5, $normalizeNumber((string) $matches[1])));
        }
        if (preg_match('/(' . $numberPattern . ')\s*尾/u', $typeText, $matches)) {
            return max(1, min(10, $normalizeNumber((string) $matches[1])));
        }
        if (preg_match('/(' . $numberPattern . ')(?:波|行|段|季)/u', $typeText, $matches)) {
            return max(1, min(10, $normalizeNumber((string) $matches[1])));
        }
        if (
            mb_strpos($typeText, '半头', 0, 'UTF-8') !== false
            || mb_strpos($typeText, '大小中特', 0, 'UTF-8') !== false
            || mb_strpos($typeText, '单双中特', 0, 'UTF-8') !== false
            || mb_strpos($typeText, '家野中特', 0, 'UTF-8') !== false
        ) {
            return 1;
        }

        return 0;
    }

    protected function customerServicePredictionQuantityNumberNormalizer()
    {
        return static function ($value) {
            $value = trim((string) $value);
            if ($value === '') {
                return 0;
            }
            if (preg_match('/^\d+$/u', $value)) {
                return (int) $value;
            }
            $map = array(
                '①' => 1, '②' => 2, '③' => 3, '④' => 4, '⑤' => 5,
                '⑥' => 6, '⑦' => 7, '⑧' => 8, '⑨' => 9, '⑩' => 10,
                '零' => 0, '一' => 1, '二' => 2, '两' => 2, '三' => 3, '四' => 4,
                '五' => 5, '六' => 6, '七' => 7, '八' => 8, '九' => 9, '十' => 10,
            );
            if (isset($map[$value])) {
                return (int) $map[$value];
            }
            if (mb_strpos($value, '十', 0, 'UTF-8') !== false) {
                $parts = explode('十', $value, 2);
                $left = trim((string) ($parts[0] ?? ''));
                $right = trim((string) ($parts[1] ?? ''));
                $tens = $left === '' ? 1 : (int) ($map[$left] ?? 0);
                $ones = $right === '' ? 0 : (int) ($map[$right] ?? 0);

                return ($tens * 10) + $ones;
            }

            return 0;
        };
    }

    public function displayTitleHtml(array $post, array $previousColors = array(), $issueTextOverride = null)
    {
        $segments = $this->displayTitleSegments($post);
        if ($issueTextOverride !== null) {
            $segments['issue'] = $this->normalizeIssueTail((string) $issueTextOverride);
        }
        $colors = $this->displayTitleSegmentColors($post, $previousColors);
        $titleStyle = $this->displayTitleTextStyle($post);
        $titleStyleAttribute = $titleStyle !== ''
            ? ' style="' . htmlspecialchars($titleStyle, ENT_QUOTES, 'UTF-8') . '"'
            : '';
        $issueHtml = '';
        $bodyInnerHtml = '';
        $bodyText = '';

        if ($segments['issue'] !== '') {
            $issueText = htmlspecialchars(
                $this->formatIssuePrefixText($segments['issue']),
                ENT_QUOTES,
                'UTF-8'
            );
            $issueHtml = '<span class="post-display-title-issue">' . $issueText . '</span>';
        }

        foreach (array('prefix', 'middle', 'author') as $segmentKey) {
            $segmentText = (string) ($segments[$segmentKey] ?? '');
            if ($segmentText === '') {
                continue;
            }

            $segmentHtml = $segmentKey === 'middle'
                ? $this->formatTypeTitleNumbersHtml($segmentText)
                : htmlspecialchars($segmentText, ENT_QUOTES, 'UTF-8');
            $segmentColor = (string) ($colors[$segmentKey] ?? '');
            $segmentStyleAttribute = $segmentColor !== ''
                ? ' style="color:' . htmlspecialchars($segmentColor, ENT_QUOTES, 'UTF-8') . ';"'
                : '';
            $bodyInnerHtml .= '<span class="post-display-title-segment is-' . $segmentKey . '"'
                . $segmentStyleAttribute
                . '>'
                . $segmentHtml
                . '</span>';
            $bodyText .= $segmentText;
        }

        $bodyHtml = $bodyInnerHtml !== ''
            ? '<span class="post-display-title post-display-title--body"' . $titleStyleAttribute . '>' . $bodyInnerHtml . '</span>'
            : '';
        $fullInnerHtml = $issueHtml . $bodyInnerHtml;
        $html = $fullInnerHtml !== ''
            ? '<span class="post-display-title post-display-title--full"' . $titleStyleAttribute . '>' . $fullInnerHtml . '</span>'
            : '';
        $plainText = trim((string) (
            ($segments['issue'] !== ''
                ? $this->formatIssuePrefixText($segments['issue'])
                : '')
            . $bodyText
        ));

        return array(
            'html' => $html,
            'body_html' => $bodyHtml,
            'plain_text' => $plainText,
            'body_text' => $bodyText,
            'text_style' => $titleStyle,
            'colors' => $colors,
            'segments' => $segments,
        );
    }

    public function attachDisplayTitlePayloads(array $rows, $postIdKey = 'post_id', $issueMode = '')
    {
        $postIds = array();
        foreach ($rows as $row) {
            $postId = (int) ($row[$postIdKey] ?? 0);
            if ($postId > 0) {
                $postIds[$postId] = $postId;
            }
        }

        if ($postIds === array()) {
            return $rows;
        }

        $postIdSql = implode(',', array_values($postIds));
        $posts = $this->db()->fetchAll(
            "SELECT posts.*,
                    COALESCE(NULLIF(post_meta.author_nickname, ''), users.username) AS author_name,
                    COALESCE(post_meta.title_prefix_text, '') AS title_prefix_text,
                    COALESCE(post_meta.title_middle_text, '') AS title_middle_text,
                    COALESCE(post_meta.title_prefix_color_mode, '') AS title_prefix_color_mode,
                    COALESCE(post_meta.title_prefix_color_value, '') AS title_prefix_color_value,
                    COALESCE(post_meta.title_middle_color_mode, '') AS title_middle_color_mode,
                    COALESCE(post_meta.title_middle_color_value, '') AS title_middle_color_value,
                    COALESCE(post_meta.author_nickname_color_mode, '') AS author_nickname_color_mode,
                    COALESCE(post_meta.author_nickname_color_value, '') AS author_nickname_color_value,
                    COALESCE(post_meta.title_font_size, '') AS title_font_size,
                    COALESCE(post_meta.title_font_weight, '') AS title_font_weight
             FROM posts
             INNER JOIN users ON users.id = posts.author_id
             LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
             WHERE posts.id IN (" . $postIdSql . ")"
        );
        $postsById = array();
        foreach ($posts as $post) {
            $postId = (int) ($post['id'] ?? 0);
            if ($postId > 0) {
                $postsById[$postId] = $post;
            }
        }

        foreach ($rows as &$row) {
            $postId = (int) ($row[$postIdKey] ?? 0);
            $post = $postsById[$postId] ?? array();
            $issueOverride = null;
            if ($post !== array() && (string) $issueMode === 'archived') {
                $recordAt = trim((string) ($row['created_at'] ?? $row['deleted_at'] ?? ''));
                $fallbackIssueText = trim((string) ($row['manage_deleted_issue_text'] ?? $row['deleted_issue_text'] ?? ''));
                $issueOverride = $recordAt !== ''
                    ? $this->displayIssueTailForRecordTime((string) ($post['region'] ?? $row['region'] ?? 'macau'), $recordAt, $fallbackIssueText)
                    : '';
                if ($issueOverride === '') {
                    $issueOverride = $this->displayArchivedIssueTail($post, $fallbackIssueText);
                }
            }
            $payload = $post !== array()
                ? $this->displayTitleHtml($post, array(), $issueOverride)
                : array();
            $row['display_title_text'] = trim((string) ($payload['plain_text'] ?? ''));
            $row['display_title_html'] = trim((string) ($payload['html'] ?? ''));
        }
        unset($row);

        return $rows;
    }

    protected function formatTypeTitleNumbersHtml($text)
    {
        $text = (string) $text;
        if ($text === '') {
            return '';
        }
        $pattern = '/(\d+|[零一二两三四五六七八九十百千万]+|[①-⑳㉑-㊿㈠-㈩⑴-⒇⒈-⒛])/u';
        $html = '';
        $offset = 0;
        if (!preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
            return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        }

        foreach ((array) ($matches[0] ?? array()) as $match) {
            $value = (string) ($match[0] ?? '');
            $position = (int) ($match[1] ?? 0);
            if ($position > $offset) {
                $html .= htmlspecialchars(substr($text, $offset, $position - $offset), ENT_QUOTES, 'UTF-8');
            }
            $html .= '<span class="type-title-number">' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '</span>';
            $offset = $position + strlen($value);
        }
        if ($offset < strlen($text)) {
            $html .= htmlspecialchars(substr($text, $offset), ENT_QUOTES, 'UTF-8');
        }

        return $html;
    }

    public function displayTitleTextStyle(array $post)
    {
        $sizeValue = (string) ($post['title_font_size'] ?? $post['manage_title_font_size'] ?? '');
        $weightValue = (string) ($post['title_font_weight'] ?? $post['manage_title_font_weight'] ?? '');
        if (trim($sizeValue) === '' || trim($weightValue) === '') {
            try {
                $generatorConfig = $this->app->admins()->managedPostGeneratorConfig(
                    (string) ($post['region'] ?? 'macau')
                );
                if (trim($sizeValue) === '') {
                    $sizeValue = (string) ($generatorConfig['title_font_size'] ?? '');
                }
                if (trim($weightValue) === '') {
                    $weightValue = (string) ($generatorConfig['title_font_weight'] ?? '');
                }
            } catch (\Throwable $exception) {
                // Keep the legacy container fallback when generator settings are unavailable.
            }
        }
        $size = $this->normalizeDisplayTitleFontSize($sizeValue);
        $weight = $this->normalizeDisplayTitleFontWeight($weightValue);
        $style = '';

        if ($size !== '') {
            $style .= '--post-display-title-font-size:' . $size . 'px;font-size:' . $size . 'px;';
        }
        if ($weight !== '') {
            $style .= '--post-display-title-font-weight:' . $weight . ';font-weight:' . $weight . ';';
        }

        return $style;
    }

    public function displayTitleSegmentColors(array $post, array $previousColors = array())
    {
        $legacyMode = trim((string) ($post['title_color_mode'] ?? ''));
        $legacyValue = strtoupper(trim((string) ($post['title_color_value'] ?? '')));
        $colors = array();

        foreach (array(
            'prefix' => array(
                'mode' => trim((string) ($post['title_prefix_color_mode'] ?? $post['manage_title_prefix_color_mode'] ?? '')),
                'value' => strtoupper(trim((string) ($post['title_prefix_color_value'] ?? $post['manage_title_prefix_color_value'] ?? ''))),
            ),
            'middle' => array(
                'mode' => trim((string) ($post['title_middle_color_mode'] ?? $post['manage_title_middle_color_mode'] ?? '')),
                'value' => strtoupper(trim((string) ($post['title_middle_color_value'] ?? $post['manage_title_middle_color_value'] ?? ''))),
            ),
            'author' => array(
                'mode' => trim((string) ($post['author_nickname_color_mode'] ?? $post['manage_author_nickname_color_mode'] ?? '')),
                'value' => strtoupper(trim((string) ($post['author_nickname_color_value'] ?? $post['manage_author_nickname_color_value'] ?? ''))),
            ),
        ) as $segmentKey => $settings) {
            $mode = $settings['mode'];
            $value = $settings['value'];

            if ($mode === '' && $legacyMode !== '') {
                $mode = $legacyMode;
                $value = $legacyValue;
            }

            $color = $this->resolveDisplayTitleColor($segmentKey, $mode, $value, $post, (string) ($previousColors[$segmentKey] ?? ''));
            if ($mode === 'daily_random' && $color !== '') {
                $color = $this->avoidRepeatedDisplayTitleColor($color, array_merge(array_values($previousColors), array_values($colors)));
            }
            $colors[$segmentKey] = $color;
        }

        return $colors;
    }

    protected function avoidRepeatedDisplayTitleColor($color, array $usedColors)
    {
        $color = strtoupper(trim((string) $color));
        $used = array_fill_keys(array_map('strtoupper', array_filter(array_map('strval', $usedColors))), true);
        if (!isset($used[$color])) {
            return $color;
        }

        $palette = $this->dailyTitleColorPalette();
        $start = array_search($color, $palette, true);
        $start = $start === false ? 0 : (int) $start;
        $count = count($palette);
        for ($offset = 1; $offset < $count; $offset++) {
            $candidate = $palette[($start + $offset) % $count];
            if (!isset($used[$candidate])) {
                return $candidate;
            }
        }

        return $color;
    }

    protected function resolveDisplayTitleColor($segmentKey, $mode, $value, array $post, $previousColor = '')
    {
        if ($mode === 'fixed' && preg_match('/^#[0-9A-F]{6}$/', $value)) {
            return $value;
        }

        if ($mode !== 'daily_random') {
            return '';
        }

        $palette = $this->dailyTitleColorPalette();
        if ($palette === array()) {
            return '';
        }

        $seedSource = date('Y-m-d') . '|' . $segmentKey . '|' . (string) ($post['id'] ?? 0) . '|' . (string) ($post['title'] ?? '');
        $index = abs(crc32($seedSource)) % count($palette);
        if (preg_match('/^#[0-9A-F]{6}$/', $value)) {
            $baseIndex = array_search($value, $palette, true);
            if ($baseIndex !== false) {
                $index = ($index + (int) $baseIndex) % count($palette);
            }
        }
        $color = $palette[$index];

        if ($previousColor !== '' && $color === $previousColor) {
            $color = $palette[($index + 1) % count($palette)];
        }

        return $color;
    }

    protected function normalizeDisplayTitleFontSize($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $size = (int) preg_replace('/\D+/', '', $value);

        return in_array($size, array(12, 13, 14, 15, 16, 17, 18, 20, 22, 24), true) ? (string) $size : '';
    }

    protected function normalizeDisplayTitleFontWeight($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $weight = preg_replace('/\D+/', '', $value);

        return in_array($weight, array('400', '500', '600', '700', '800', '900'), true) ? $weight : '';
    }

    public function listPostsByRegion($region, $limit = 50)
    {
        $this->maintainRecycleRules($region);
        $this->ensurePostTitleStyleColumns();

        return $this->db()->fetchAll('SELECT posts.*,
                                             COALESCE(NULLIF(post_meta.author_nickname, \'\'), users.username) AS author_name,
                                             COALESCE(post_meta.title_prefix_text, \'\') AS title_prefix_text,
                                             COALESCE(post_meta.title_middle_text, \'\') AS title_middle_text,
                                             COALESCE(post_meta.title_prefix_color_mode, \'\') AS title_prefix_color_mode,
                                             COALESCE(post_meta.title_prefix_color_value, \'\') AS title_prefix_color_value,
                                             COALESCE(post_meta.title_middle_color_mode, \'\') AS title_middle_color_mode,
                                             COALESCE(post_meta.title_middle_color_value, \'\') AS title_middle_color_value,
                                             COALESCE(post_meta.author_nickname_color_mode, \'\') AS author_nickname_color_mode,
                                             COALESCE(post_meta.author_nickname_color_value, \'\') AS author_nickname_color_value,
                                             COALESCE(post_meta.title_font_size, \'\') AS title_font_size,
                                             COALESCE(post_meta.title_font_weight, \'\') AS title_font_weight,
                                             COALESCE(post_meta.title_color_mode, \'\') AS title_color_mode,
                                             COALESCE(post_meta.title_color_value, \'\') AS title_color_value,
                                             COALESCE(post_meta.is_hidden, 0) AS manage_is_hidden,
                                             COALESCE(post_meta.is_encrypted, 0) AS manage_is_encrypted,
                                             COALESCE(post_meta.fake_buyer_count, 0) AS manage_fake_buyer_count
                                      FROM posts
                                      INNER JOIN users ON users.id = posts.author_id
                                      LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
                                      WHERE posts.region = :region
                                        AND posts.status = :status
                                        AND COALESCE(post_meta.is_hidden, 0) = 0
                                      ORDER BY posts.is_top_forever DESC, posts.is_top_admin DESC, posts.is_top_normal DESC, posts.created_at DESC LIMIT ' . (int) $limit, array(
            'region' => $region,
            'status' => 'published',
        ));
    }

    public function adminListPosts($region = null)
    {
        $this->maintainRecycleRules($region);
        $this->ensurePostTitleStyleColumns();

        if ($region !== null && $region !== '') {
            return $this->db()->fetchAll("SELECT posts.*, COALESCE(NULLIF(post_meta.author_nickname, ''), users.username) AS author_name, COALESCE(post_meta.title_prefix_text, '') AS title_prefix_text, COALESCE(post_meta.title_middle_text, '') AS title_middle_text, COALESCE(post_meta.title_prefix_color_mode, '') AS title_prefix_color_mode, COALESCE(post_meta.title_prefix_color_value, '') AS title_prefix_color_value, COALESCE(post_meta.title_middle_color_mode, '') AS title_middle_color_mode, COALESCE(post_meta.title_middle_color_value, '') AS title_middle_color_value, COALESCE(post_meta.author_nickname_color_mode, '') AS author_nickname_color_mode, COALESCE(post_meta.author_nickname_color_value, '') AS author_nickname_color_value, COALESCE(post_meta.title_color_mode, '') AS title_color_mode, COALESCE(post_meta.title_color_value, '') AS title_color_value FROM posts INNER JOIN users ON users.id = posts.author_id LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id WHERE posts.region = :region AND posts.status <> 'deleted' ORDER BY posts.created_at DESC", array(
                'region' => $region,
            ));
        }

        return $this->db()->fetchAll("SELECT posts.*, COALESCE(NULLIF(post_meta.author_nickname, ''), users.username) AS author_name, COALESCE(post_meta.title_prefix_text, '') AS title_prefix_text, COALESCE(post_meta.title_middle_text, '') AS title_middle_text, COALESCE(post_meta.title_prefix_color_mode, '') AS title_prefix_color_mode, COALESCE(post_meta.title_prefix_color_value, '') AS title_prefix_color_value, COALESCE(post_meta.title_middle_color_mode, '') AS title_middle_color_mode, COALESCE(post_meta.title_middle_color_value, '') AS title_middle_color_value, COALESCE(post_meta.author_nickname_color_mode, '') AS author_nickname_color_mode, COALESCE(post_meta.author_nickname_color_value, '') AS author_nickname_color_value, COALESCE(post_meta.title_color_mode, '') AS title_color_mode, COALESCE(post_meta.title_color_value, '') AS title_color_value FROM posts INNER JOIN users ON users.id = posts.author_id LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id WHERE posts.status <> 'deleted' ORDER BY posts.created_at DESC");
    }

    public function findPost($postId)
    {
        $this->maintainRecycleRules();
        $this->ensurePostTitleStyleColumns();

        return $this->db()->fetch("SELECT posts.*,
                                          COALESCE(NULLIF(post_meta.author_nickname, ''), users.username) AS author_name,
                                          COALESCE(users.bio, '') AS author_bio,
                                          (
                                              SELECT MIN(author_posts.created_at)
                                              FROM posts author_posts
                                              WHERE author_posts.author_id = posts.author_id
                                                AND author_posts.status <> 'deleted'
                                          ) AS author_first_post_at,
                                          COALESCE(post_meta.title_prefix_text, '') AS title_prefix_text,
                                          COALESCE(post_meta.title_middle_text, '') AS title_middle_text,
                                          COALESCE(post_meta.title_prefix_color_mode, '') AS title_prefix_color_mode,
                                          COALESCE(post_meta.title_prefix_color_value, '') AS title_prefix_color_value,
                                          COALESCE(post_meta.title_middle_color_mode, '') AS title_middle_color_mode,
                                          COALESCE(post_meta.title_middle_color_value, '') AS title_middle_color_value,
                                          COALESCE(post_meta.author_nickname_color_mode, '') AS author_nickname_color_mode,
                                          COALESCE(post_meta.author_nickname_color_value, '') AS author_nickname_color_value,
                                          COALESCE(post_meta.title_font_size, '') AS title_font_size,
                                          COALESCE(post_meta.title_font_weight, '') AS title_font_weight,
                                          COALESCE(post_meta.updated_at, posts.updated_at) AS sale_buyer_increment_start_at,
                                          COALESCE(post_meta.is_hidden, 0) AS manage_is_hidden,
                                          COALESCE(post_meta.is_encrypted, 0) AS manage_is_encrypted,
                                          COALESCE(post_meta.fake_buyer_count, 0) AS manage_fake_buyer_count
                                   FROM posts
                                   INNER JOIN users ON users.id = posts.author_id
                                   LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
                                   WHERE posts.id = :id
                                     AND posts.status <> 'deleted'
                                     AND COALESCE(post_meta.is_hidden, 0) = 0
                                   LIMIT 1", array(
            'id' => $postId,
        ));
    }

    public function hasPurchased($postId, $userId)
    {
        $postId = (int) $postId;
        $userId = (int) $userId;
        if ($postId <= 0 || $userId <= 0) {
            return false;
        }

        $row = $this->db()->fetch(
            'SELECT purchases.id AS purchase_id,
                    purchases.created_at,
                    posts.id,
                    posts.region,
                    posts.title,
                    posts.full_content,
                    posts.price
             FROM purchases
             INNER JOIN posts ON posts.id = purchases.post_id
             WHERE purchases.post_id = :post_id
               AND purchases.user_id = :user_id
             LIMIT 1',
            array(
                'post_id' => $postId,
                'user_id' => $userId,
            )
        );
        if (!$row) {
            return false;
        }

        $window = $this->salePostPurchaseWindowForPost($row);

        try {
            $purchaseAt = new \DateTimeImmutable((string) ($row['created_at'] ?? ''));
        } catch (\Exception $exception) {
            return false;
        }

        return $purchaseAt >= $window['start'] && $purchaseAt < $window['end'];
    }

    public function salePostCurrentIssueBuyerSummary(array $post)
    {
        $postId = (int) ($post['id'] ?? 0);
        $region = (string) ($post['region'] ?? '') === 'hongkong' ? 'hongkong' : 'macau';
        $issueTail = $this->displayIssueTail($post);
        $window = $this->salePostPurchaseWindowForPost($post);
        $buyers = array();
        $usedNames = array();
        $firstPurchaseTimestamp = 0;

        if ($postId > 0 && $this->tableExists('purchases')) {
            $buyerNameSql = $this->columnExists('users', 'nickname')
                ? "COALESCE(NULLIF(users.nickname, ''), users.username)"
                : 'users.username';
            $rows = $this->db()->fetchAll(
                "SELECT purchases.user_id,
                        purchases.created_at,
                        " . $buyerNameSql . " AS username
                 FROM purchases
                 INNER JOIN users ON users.id = purchases.user_id
                 WHERE purchases.post_id = :post_id
                   AND purchases.created_at >= :window_start
                   AND purchases.created_at < :window_end
                 ORDER BY purchases.created_at DESC, purchases.id DESC",
                array(
                    'post_id' => $postId,
                    'window_start' => $window['start']->format('Y-m-d H:i:s'),
                    'window_end' => $window['end']->format('Y-m-d H:i:s'),
                )
            );

            foreach ($rows as $row) {
                $username = trim((string) ($row['username'] ?? ''));
                if ($username === '') {
                    continue;
                }

                $createdAt = (string) ($row['created_at'] ?? '');
                $createdTimestamp = strtotime($createdAt);
                if ($createdTimestamp !== false && ($firstPurchaseTimestamp === 0 || $createdTimestamp < $firstPurchaseTimestamp)) {
                    $firstPurchaseTimestamp = (int) $createdTimestamp;
                }
                $buyers[] = array(
                    'username' => $username,
                    'created_at' => $createdAt,
                    'created_timestamp' => $createdTimestamp === false ? 0 : $createdTimestamp,
                    'is_fake' => false,
                );
                $usedNames[$username] = true;
            }
        }

        $saleIncrementPost = $post;
        if ($firstPurchaseTimestamp > 0) {
            $saleIncrementPost['sale_buyer_increment_anchor_at'] = date('Y-m-d H:i:s', $firstPurchaseTimestamp);
        }

        $fakeCount = $this->currentSaleBuyerIncrementCount($postId, $issueTail, $window, $saleIncrementPost);
        foreach ($this->salePostFakeBuyerRows($postId, $issueTail, $fakeCount, $window, $usedNames, $saleIncrementPost) as $fakeBuyer) {
            $buyers[] = $fakeBuyer;
            $usedNames[(string) ($fakeBuyer['username'] ?? '')] = true;
        }

        usort($buyers, static function ($left, $right) {
            $leftTime = (int) ($left['created_timestamp'] ?? 0);
            $rightTime = (int) ($right['created_timestamp'] ?? 0);
            if ($leftTime === $rightTime) {
                return strcmp((string) ($right['username'] ?? ''), (string) ($left['username'] ?? ''));
            }

            return $rightTime <=> $leftTime;
        });

        return array(
            'issue_tail' => $issueTail,
            'total' => count($buyers),
            'buyers' => $buyers,
            'window_start' => $window['start']->format('Y-m-d H:i:s'),
            'window_end' => $window['end']->format('Y-m-d H:i:s'),
        );
    }

    protected function salePostCurrentPurchaseWindow($region)
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $timeValue = '21:35';
        try {
            $rawConfig = trim((string) $this->app->settings()->get('post_generator.settings.' . $region, ''));
            $config = array();
            if ($rawConfig !== '') {
                $decodedConfig = json_decode($rawConfig, true);
                if (is_array($decodedConfig)) {
                    $config = $decodedConfig;
                }
            }

            $configuredTime = trim((string) ($config['post_update_time'] ?? ''));
            if ($configuredTime === '') {
                $configuredTime = trim((string) ($config['material_content_time'] ?? ''));
            }
            if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $configuredTime)) {
                $timeValue = $configuredTime;
            }
        } catch (\Throwable $exception) {
        }

        $hour = (int) substr($timeValue, 0, 2);
        $minute = (int) substr($timeValue, 3, 2);
        $now = new \DateTimeImmutable($this->now());
        $windowBoundary = $now->setTime($hour, $minute, 0);
        if ($now < $windowBoundary) {
            $windowStart = $windowBoundary->modify('-1 day');
            $windowEnd = $windowBoundary;
        } else {
            $windowStart = $windowBoundary;
            $windowEnd = $windowBoundary->modify('+1 day');
        }

        return array(
            'start' => $windowStart,
            'end' => $windowEnd,
            'now' => $now,
        );
    }

    protected function salePostPurchaseWindowForPost(array $post)
    {
        $region = (string) ($post['region'] ?? '') === 'hongkong' ? 'hongkong' : 'macau';
        $fallback = $this->salePostCurrentPurchaseWindow($region);
        $issueTail = $this->displayIssueTail($post);

        if ($issueTail === '' || !$this->tableExists('lottery_issues')) {
            return $fallback;
        }

        try {
            $currentIssue = $this->db()->fetch(
                'SELECT issue_no, planned_open_at, actual_open_at
                 FROM lottery_issues
                 WHERE region = :region
                   AND RIGHT(issue_no, 3) = :issue_tail
                 ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
                 LIMIT 1',
                array(
                    'region' => $region,
                    'issue_tail' => $issueTail,
                )
            );
        } catch (\Throwable $exception) {
            return $fallback;
        }

        if (!is_array($currentIssue)) {
            return $fallback;
        }

        $issueNo = preg_replace('/\D+/', '', (string) ($currentIssue['issue_no'] ?? ''));
        $endAt = trim((string) ($currentIssue['actual_open_at'] ?? ''));
        if ($endAt === '') {
            $endAt = trim((string) ($currentIssue['planned_open_at'] ?? ''));
        }
        if ($issueNo === '' || $endAt === '') {
            return $fallback;
        }

        try {
            $windowEnd = new \DateTimeImmutable($endAt);
        } catch (\Exception $exception) {
            return $fallback;
        }

        $startAt = '';
        try {
            $previousIssue = $this->db()->fetch(
                'SELECT planned_open_at, actual_open_at
                 FROM lottery_issues
                 WHERE region = :region
                   AND CAST(issue_no AS UNSIGNED) < :issue_no
                 ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
                 LIMIT 1',
                array(
                    'region' => $region,
                    'issue_no' => (int) $issueNo,
                )
            );
            if (is_array($previousIssue)) {
                $startAt = trim((string) ($previousIssue['actual_open_at'] ?? ''));
                if ($startAt === '') {
                    $startAt = trim((string) ($previousIssue['planned_open_at'] ?? ''));
                }
            }
        } catch (\Throwable $exception) {
            $startAt = '';
        }

        try {
            $windowStart = $startAt !== ''
                ? new \DateTimeImmutable($startAt)
                : $windowEnd->modify('-1 day');
        } catch (\Exception $exception) {
            $windowStart = $windowEnd->modify('-1 day');
        }

        if ($windowStart >= $windowEnd) {
            return $fallback;
        }

        return array(
            'start' => $windowStart,
            'end' => $windowEnd,
            'now' => $fallback['now'],
            'issue_tail' => $issueTail,
        );
    }

    protected function currentSaleBuyerIncrementCount($postId, $issueTail, array $window, array $post = array())
    {
        $postId = (int) $postId;
        if ($postId <= 0) {
            return 0;
        }

        $settings = $this->postSaleBuyerIncrementSettings();
        $min = (int) $settings['increment_min'];
        $max = (int) $settings['increment_max'];
        if ($max <= 0) {
            return 0;
        }

        $now = $window['now'] instanceof \DateTimeImmutable ? $window['now'] : new \DateTimeImmutable($this->now());
        $releaseWindow = $this->salePostBuyerReleaseWindow($window, $post);
        $dateText = date('Y-m-d', (int) $releaseWindow['start_timestamp']);
        $startTimestamp = (int) $releaseWindow['start_timestamp'];
        $endTimestamp = (int) $releaseWindow['end_timestamp'];
        $nowTimestamp = $now->getTimestamp();
        if ($endTimestamp <= $startTimestamp || $nowTimestamp < $startTimestamp) {
            return 0;
        }

        $targetSeed = (int) sprintf(
            '%u',
            crc32('sale-buyer-increment|' . (string) $postId . '|' . (string) $issueTail . '|' . $dateText)
        );
        $target = $min + ($targetSeed % (($max - $min) + 1));
        if ($target <= 0) {
            return 0;
        }

        $releaseLimitTimestamp = min($nowTimestamp, $endTimestamp);
        $releaseRange = max(1, $endTimestamp - $startTimestamp);
        $released = 0;

        for ($index = 0; $index < $target; $index++) {
            $seed = (int) sprintf(
                '%u',
                crc32(
                    'sale-buyer-release|'
                    . (string) $postId
                    . '|'
                    . (string) $issueTail
                    . '|'
                    . $dateText
                    . '|'
                    . (string) $index
                )
            );
            $slotStartTimestamp = $startTimestamp + (int) floor($releaseRange * ($index / $target));
            $slotEndTimestamp = $startTimestamp + (int) floor($releaseRange * (($index + 1) / $target));
            $slotRange = max(0, $slotEndTimestamp - $slotStartTimestamp);
            $releaseTimestamp = $slotStartTimestamp + ($seed % ($slotRange + 1));
            $releaseTimestamp = max($startTimestamp, min($endTimestamp, $releaseTimestamp));

            if ($releaseTimestamp <= $releaseLimitTimestamp) {
                $released++;
            }
        }

        return max(0, min($target, $released));
    }

    protected function salePostFakeBuyerRows($postId, $issueTail, $count, array $window, array $usedNames, array $post = array())
    {
        $postId = (int) $postId;
        $count = max(0, (int) $count);
        if ($postId <= 0 || $count <= 0) {
            return array();
        }

        $now = $window['now'] instanceof \DateTimeImmutable ? $window['now'] : new \DateTimeImmutable($this->now());
        $releaseWindow = $this->salePostBuyerReleaseWindow($window, $post);
        $dateText = date('Y-m-d', (int) $releaseWindow['start_timestamp']);
        $startTimestamp = (int) $releaseWindow['start_timestamp'];
        $endTimestamp = (int) $releaseWindow['end_timestamp'];
        $nowTimestamp = $now->getTimestamp();
        if ($endTimestamp <= $startTimestamp) {
            return array();
        }

        $releaseEndTimestamp = min($nowTimestamp, $endTimestamp);
        if ($releaseEndTimestamp < $startTimestamp) {
            return array();
        }

        $settings = $this->postSaleBuyerIncrementSettings();
        $min = (int) $settings['increment_min'];
        $max = (int) $settings['increment_max'];
        if ($max <= 0) {
            return array();
        }

        $targetSeed = (int) sprintf(
            '%u',
            crc32('sale-buyer-increment|' . (string) $postId . '|' . (string) $issueTail . '|' . $dateText)
        );
        $target = $min + ($targetSeed % (($max - $min) + 1));
        if ($target <= 0) {
            return array();
        }

        $prefixes = array('vkg', 'em', 'ruim', 'ape', 'k', 'f', 'am', 'hk', 'vip', 'win');
        $buyers = array();
        $releaseRange = max(1, $endTimestamp - $startTimestamp);
        $nameIndex = 0;

        for ($index = 0; $index < $target; $index++) {
            $seed = (int) sprintf(
                '%u',
                crc32(
                    'sale-buyer-release|'
                    . (string) $postId
                    . '|'
                    . (string) $issueTail
                    . '|'
                    . $dateText
                    . '|'
                    . (string) $index
                )
            );
            $slotStartTimestamp = $startTimestamp + (int) floor($releaseRange * ($index / $target));
            $slotEndTimestamp = $startTimestamp + (int) floor($releaseRange * (($index + 1) / $target));
            $slotRange = max(0, $slotEndTimestamp - $slotStartTimestamp);
            $createdTimestamp = $slotStartTimestamp + ($seed % ($slotRange + 1));
            $createdTimestamp = max($startTimestamp, min($endTimestamp, $createdTimestamp));
            if ($createdTimestamp > $releaseEndTimestamp) {
                continue;
            }

            $username = '';

            while ($username === '' || isset($usedNames[$username])) {
                $nameSeed = (int) sprintf(
                    '%u',
                    crc32('sale-buyer-name|' . (string) $postId . '|' . (string) $issueTail . '|' . $dateText . '|' . (string) $nameIndex)
                );
                $prefix = $prefixes[$nameSeed % count($prefixes)];
                $numberLength = 5 + ($nameSeed % 6);
                $numberBase = (string) ($nameSeed % (int) pow(10, $numberLength));
                $username = $prefix . str_pad($numberBase, $numberLength, '0', STR_PAD_LEFT);
                $nameIndex++;
            }

            $usedNames[$username] = true;
            $buyers[] = array(
                'username' => $username,
                'created_at' => date('Y-m-d H:i:s', $createdTimestamp),
                'created_timestamp' => $createdTimestamp,
                'is_fake' => true,
            );

            if (count($buyers) >= $count) {
                break;
            }
        }

        return $buyers;
    }

    protected function salePostBuyerReleaseWindow(array $window, array $post = array())
    {
        $windowStart = $window['start'] instanceof \DateTimeImmutable ? $window['start'] : new \DateTimeImmutable($this->now());
        $windowEnd = $window['end'] instanceof \DateTimeImmutable ? $window['end'] : $windowStart->modify('+1 day');
        $windowStartTimestamp = $windowStart->getTimestamp();
        $windowEndTimestamp = $windowEnd->getTimestamp();
        $releaseStartTimestamp = strtotime(date('Y-m-d', $windowStartTimestamp) . ' 08:00:00');
        if ($releaseStartTimestamp === false) {
            $releaseStartTimestamp = $windowStartTimestamp;
        }
        if ($releaseStartTimestamp < $windowStartTimestamp) {
            $releaseStartTimestamp = strtotime(date('Y-m-d', $windowStartTimestamp + 86400) . ' 08:00:00');
            if ($releaseStartTimestamp === false) {
                $releaseStartTimestamp = $windowStartTimestamp;
            }
        }

        $releaseEndTimestamp = strtotime(date('Y-m-d', $releaseStartTimestamp) . ' 21:10:00');
        if ($releaseEndTimestamp === false) {
            $releaseEndTimestamp = $releaseStartTimestamp;
        }
        if ($releaseEndTimestamp > $windowEndTimestamp) {
            $releaseEndTimestamp = $windowEndTimestamp;
        }

        $saleStartTimestamp = 0;
        foreach (array('sale_buyer_increment_anchor_at', 'sale_buyer_increment_start_at') as $timeField) {
            $timeText = trim((string) ($post[$timeField] ?? ''));
            if ($timeText === '') {
                continue;
            }

            $timeTimestamp = strtotime($timeText);
            if ($timeTimestamp !== false) {
                $saleStartTimestamp = max($saleStartTimestamp, (int) $timeTimestamp);
            }
        }

        if ($saleStartTimestamp <= 0) {
            foreach (array('updated_at', 'created_at') as $timeField) {
                $timeText = trim((string) ($post[$timeField] ?? ''));
                if ($timeText === '') {
                    continue;
                }

                $timeTimestamp = strtotime($timeText);
                if ($timeTimestamp !== false) {
                    $saleStartTimestamp = max($saleStartTimestamp, (int) $timeTimestamp);
                }
            }
        }

        if (
            $saleStartTimestamp > $windowStartTimestamp
            && $saleStartTimestamp < $windowEndTimestamp
            && $saleStartTimestamp < $releaseEndTimestamp
            && $saleStartTimestamp > $releaseStartTimestamp
        ) {
            $releaseStartTimestamp = $saleStartTimestamp;
        }

        return array(
            'start_timestamp' => $releaseStartTimestamp,
            'end_timestamp' => $releaseEndTimestamp,
        );
    }

    public function maintainRecycleRules($region = null)
    {
        static $handledRegions = array();

        $region = in_array($region, array('macau', 'hongkong'), true) ? (string) $region : '';
        $cacheKey = $region === '' ? 'all' : $region;
        if (isset($handledRegions[$cacheKey])) {
            return;
        }

        $this->restoreDeletedPurchasedPosts(array(), $region);
        $this->purgeExpiredDeletedPosts($region);
        $handledRegions[$cacheKey] = true;
    }

    public function restoreDeletedPurchasedPosts(array $postIds = array(), $region = null)
    {
        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
        $region = in_array($region, array('macau', 'hongkong'), true) ? (string) $region : '';

        $sql = "SELECT posts.id
                FROM posts
                WHERE posts.status = 'deleted'
                  AND posts.deleted_at IS NULL
                  AND EXISTS (
                      SELECT 1
                      FROM purchases
                      WHERE purchases.post_id = posts.id
                      LIMIT 1
                  )";
        $params = array();

        if ($region !== '') {
            $sql .= ' AND posts.region = :region';
            $params['region'] = $region;
        }

        if ($postIds !== array()) {
            $sql .= ' AND posts.id IN (' . implode(',', $postIds) . ')';
        }

        $rows = $this->db()->fetchAll($sql, $params);
        if ($rows === array()) {
            return array();
        }

        $restoreIds = array_values(array_unique(array_map(static function ($row) {
            return (int) ($row['id'] ?? 0);
        }, $rows)));
        $restoreIds = array_values(array_filter($restoreIds));
        if ($restoreIds === array()) {
            return array();
        }

        $this->db()->execute(
            'UPDATE posts
             SET status = :status,
                 deleted_at = NULL,
                 updated_at = :updated_at
             WHERE id IN (' . implode(',', $restoreIds) . ')',
            array(
                'status' => 'published',
                'updated_at' => $this->now(),
            )
        );

        return $restoreIds;
    }

    protected function purgeExpiredDeletedPosts($region = null)
    {
        $region = in_array($region, array('macau', 'hongkong'), true) ? (string) $region : '';
        $purgeBefore = date('Y-m-d H:i:s', time() - (3 * 24 * 3600));
        $sql = "SELECT posts.id
                FROM posts
                WHERE posts.status = 'deleted'
                  AND posts.deleted_at IS NOT NULL
                  AND posts.deleted_at <= :purge_before
                  AND NOT EXISTS (
                      SELECT 1
                      FROM purchases
                      WHERE purchases.post_id = posts.id
                      LIMIT 1
                  )";
        $params = array(
            'purge_before' => $purgeBefore,
        );

        if ($region !== '') {
            $sql .= ' AND posts.region = :region';
            $params['region'] = $region;
        }

        $rows = $this->db()->fetchAll($sql, $params);
        if ($rows === array()) {
            return array();
        }

        $purgeIds = array_values(array_unique(array_map(static function ($row) {
            return (int) ($row['id'] ?? 0);
        }, $rows)));
        $purgeIds = array_values(array_filter($purgeIds));
        if ($purgeIds === array()) {
            return array();
        }

        $this->purgePostsByIds($purgeIds);

        return $purgeIds;
    }

    public function purgeDeletedPostsByIds(array $postIds)
    {
        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
        if ($postIds === array()) {
            return 0;
        }

        $this->purgePostsByIds($postIds);

        return count($postIds);
    }

    protected function purgePostsByIds(array $postIds)
    {
        $postIds = array_values(array_unique(array_filter(array_map('intval', $postIds))));
        if ($postIds === array()) {
            return;
        }

        $in = implode(',', $postIds);
        $startedTransaction = !$this->db()->pdo()->inTransaction();
        if ($startedTransaction) {
            $this->db()->beginTransaction();
        }

        try {
            if ($this->tableExists('comment_likes') && $this->tableExists('replies')) {
                $this->db()->execute(
                    'DELETE FROM comment_likes
                     WHERE comment_id IN (
                         SELECT id FROM replies WHERE post_id IN (' . $in . ')
                     )'
                );
            }

            if ($this->tableExists('audit_records') && $this->tableExists('replies')) {
                $this->db()->execute(
                    'DELETE FROM audit_records
                     WHERE target_type = \'comment\'
                       AND target_id IN (
                           SELECT id FROM replies WHERE post_id IN (' . $in . ')
                       )'
                );
            }

            if ($this->tableExists('post_view_display_events')) {
                $this->db()->execute('DELETE FROM post_view_display_events WHERE post_id IN (' . $in . ')');
            }

            if ($this->tableExists('post_unique_views')) {
                $this->db()->execute('DELETE FROM post_unique_views WHERE post_id IN (' . $in . ')');
            }

            if ($this->tableExists('replies')) {
                $this->db()->execute('DELETE FROM replies WHERE post_id IN (' . $in . ')');
            }

            if ($this->tableExists('purchases')) {
                $this->db()->execute('DELETE FROM purchases WHERE post_id IN (' . $in . ')');
            }

            if ($this->tableExists('post_manage_meta')) {
                $this->db()->execute('DELETE FROM post_manage_meta WHERE post_id IN (' . $in . ')');
            }

            if ($this->tableExists('post_interactions')) {
                $this->db()->execute('DELETE FROM post_interactions WHERE post_id IN (' . $in . ')');
            }

            if ($this->tableExists('post_reports')) {
                $this->db()->execute('DELETE FROM post_reports WHERE post_id IN (' . $in . ')');
            }

            if ($this->tableExists('page_views')) {
                $this->db()->execute(
                    "DELETE FROM page_views
                     WHERE route_name = 'post_detail'
                       AND path_name LIKE '%id=%'
                       AND CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(path_name, 'id=', -1), '&', 1) AS UNSIGNED) IN (" . $in . ')'
                );
            }

            if ($this->tableExists('audit_records')) {
                $this->db()->execute(
                    "DELETE FROM audit_records
                     WHERE target_type = 'post'
                       AND target_id IN (" . $in . ')'
                );
            }

            $this->db()->execute('DELETE FROM posts WHERE id IN (' . $in . ')');
            if ($startedTransaction) {
                $this->db()->commit();
            }
        } catch (\Throwable $exception) {
            if ($startedTransaction) {
                $this->db()->rollBack();
            }
            throw $exception;
        }
    }

    protected function tableExists($table)
    {
        $table = trim((string) $table);
        if ($table === '') {
            return false;
        }

        try {
            $row = $this->db()->fetch(
                'SELECT COUNT(*) AS total_count
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name',
                array(
                    'table_name' => $table,
                )
            );
            return ((int) ($row['total_count'] ?? 0)) > 0;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    protected function columnExists($table, $column)
    {
        $table = trim((string) $table);
        $column = trim((string) $column);
        if ($table === '' || $column === '') {
            return false;
        }

        try {
            $row = $this->db()->fetch(
                'SELECT COUNT(*) AS total_count
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND COLUMN_NAME = :column_name',
                array(
                    'table_name' => $table,
                    'column_name' => $column,
                )
            );

            return ((int) ($row['total_count'] ?? 0)) > 0;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public function ensureCommunitySchema()
    {
        if ($this->tableExists('replies') && !$this->columnExists('replies', 'parent_id')) {
            $this->db()->pdo()->exec("ALTER TABLE replies ADD COLUMN parent_id BIGINT UNSIGNED DEFAULT NULL AFTER post_id, ADD INDEX idx_replies_parent (parent_id, created_at)");
        }

        if ($this->tableExists('replies') && !$this->columnExists('replies', 'like_count')) {
            $this->db()->pdo()->exec("ALTER TABLE replies ADD COLUMN like_count INT NOT NULL DEFAULT 0 AFTER content");
        }

        if (!$this->tableExists('comment_likes')) {
            $this->db()->pdo()->exec("CREATE TABLE IF NOT EXISTS comment_likes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                comment_id BIGINT UNSIGNED NOT NULL,
                user_id INT UNSIGNED NOT NULL,
                status TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uniq_comment_like (comment_id, user_id),
                INDEX idx_comment_likes_comment_status (comment_id, status),
                INDEX idx_comment_likes_user_status (user_id, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if ($this->tableExists('comment_likes')) {
            $foreignKeys = $this->db()->fetchAll(
                'SELECT CONSTRAINT_NAME
                 FROM information_schema.KEY_COLUMN_USAGE
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :table_name
                   AND COLUMN_NAME = :column_name
                   AND REFERENCED_TABLE_NAME = :referenced_table
                   AND REFERENCED_COLUMN_NAME = :referenced_column',
                array(
                    'table_name' => 'comment_likes',
                    'column_name' => 'user_id',
                    'referenced_table' => 'users',
                    'referenced_column' => 'id',
                )
            );
            foreach ($foreignKeys as $foreignKey) {
                $constraintName = (string) ($foreignKey['CONSTRAINT_NAME'] ?? '');
                if (!preg_match('/^[A-Za-z0-9_]+$/', $constraintName)) {
                    continue;
                }

                $this->db()->pdo()->exec(
                    'ALTER TABLE comment_likes DROP FOREIGN KEY `' . str_replace('`', '``', $constraintName) . '`'
                );
            }
        }
    }

    protected function ensurePostTitleStyleColumns()
    {
        if (!$this->tableExists('post_manage_meta')) {
            return;
        }

        if ($this->columnExists('post_manage_meta', 'author_nickname_color_value') && !$this->columnExists('post_manage_meta', 'title_font_size')) {
            $this->db()->pdo()->exec("ALTER TABLE post_manage_meta ADD COLUMN title_font_size VARCHAR(8) NOT NULL DEFAULT '' AFTER author_nickname_color_value");
        }
        if ($this->columnExists('post_manage_meta', 'title_font_size') && !$this->columnExists('post_manage_meta', 'title_font_weight')) {
            $this->db()->pdo()->exec("ALTER TABLE post_manage_meta ADD COLUMN title_font_weight VARCHAR(8) NOT NULL DEFAULT '' AFTER title_font_size");
        }
    }

    public function visibleContent(array $post, $viewer = null)
    {
        $isPrivilegedViewer = $viewer && (int) $viewer['id'] === (int) $post['author_id'];

        if ((int) ($post['price'] ?? 0) > 0 && $this->salePostHasOpenedDraw($post)) {
            return (string) $post['full_content'];
        }

        if ((int) ($post['manage_is_encrypted'] ?? 0) === 1 && !$isPrivilegedViewer && !($viewer && $this->hasPurchased((int) $post['id'], (int) $viewer['id']))) {
            return '该帖子已加密，请联系管理员或购买后查看完整内容。';
        }

        if ((int) $post['price'] <= 0) {
            return (string) $post['full_content'];
        }

        if ($viewer && ($isPrivilegedViewer || $this->hasPurchased($post['id'], $viewer['id']))) {
            return (string) $post['full_content'];
        }

        return '此资料出售，购买后可查看完整资料';
    }

    protected function salePostHasOpenedDraw(array $post)
    {
        if ((int) ($post['price'] ?? 0) <= 0) {
            return false;
        }

        $region = (string) ($post['region'] ?? '') === 'hongkong' ? 'hongkong' : 'macau';
        $issueTails = array();
        $contentIssueTails = array();
        $currentIssueTail = $this->displayIssueTail($post);

        $contentLines = preg_split('/\R/u', trim((string) ($post['full_content'] ?? '')));
        if (!is_array($contentLines)) {
            $contentLines = array();
        }

        foreach ($contentLines as $contentLine) {
            $contentLine = trim((string) $contentLine);
            if (!preg_match('/^(\d{1,6})[^:：]{0,12}[:：]/u', $contentLine, $matches)) {
                continue;
            }

            $contentIssueTail = $this->normalizeIssueTail((string) ($matches[1] ?? ''));
            if ($contentIssueTail !== '') {
                $contentIssueTails[] = $contentIssueTail;
            }
        }

        $contentIssueTails = array_values(array_unique($contentIssueTails));
        try {
            $latestDraw = $this->app->prediction()->latestHomepageDraw($region);
        } catch (\Throwable $exception) {
            $latestDraw = null;
        }
        $latestDrawIssueTail = is_array($latestDraw) ? $this->normalizeIssueTail((string) ($latestDraw['issue_no'] ?? '')) : '';
        if ($currentIssueTail !== '' && ($contentIssueTails === array() || in_array($currentIssueTail, $contentIssueTails, true))) {
            $issueTails[] = $currentIssueTail;
        } elseif (count($contentIssueTails) === 1) {
            $issueTails[] = $contentIssueTails[0];
        }

        if ($issueTails === array()) {
            $titleIssueTail = $this->normalizeIssueTail((string) ($post['title'] ?? ''));
            if ($titleIssueTail !== '') {
                $issueTails[] = $titleIssueTail;
            }
        }

        $issueTails = array_values(array_unique($issueTails));
        if ($issueTails === array()) {
            return false;
        }

        if ($latestDrawIssueTail !== '' && in_array($latestDrawIssueTail, $issueTails, true)) {
            return true;
        }

        if (!$this->tableExists('lottery_draws')) {
            return false;
        }

        foreach ($issueTails as $issueTail) {
            $drawRow = $this->db()->fetch(
                'SELECT id
                 FROM lottery_draws
                 WHERE region = :region
                   AND RIGHT(issue_no, 3) = :issue_tail
                 ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
                 LIMIT 1',
                array(
                    'region' => $region,
                    'issue_tail' => $issueTail,
                )
            );

            if ($drawRow) {
                return true;
            }
        }

        return false;
    }

    public function postLockSettings()
    {
        $beforeMinutes = (int) $this->app->settings()->get('post_lock.before_minutes', '60');
        $unlockTime = trim((string) $this->app->settings()->get('post_lock.unlock_time', '23:59'));
        if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $unlockTime)) {
            $unlockTime = '23:59';
        }

        if ($beforeMinutes < 0) {
            $beforeMinutes = 0;
        } elseif ($beforeMinutes > 1440) {
            $beforeMinutes = 1440;
        }

        return array(
            'before_minutes' => $beforeMinutes,
            'unlock_time' => $unlockTime,
        );
    }

    public function postLockState($region, $nowText = '')
    {
        $region = (string) $region === 'hongkong' ? 'hongkong' : 'macau';
        $settings = $this->postLockSettings();
        $state = array(
            'region' => $region,
            'is_locked' => false,
            'label' => '此帖未锁 🔓',
            'lock_start_at' => '',
            'unlock_at' => '',
            'planned_open_at' => '',
            'actual_open_at' => '',
            'before_minutes' => (int) $settings['before_minutes'],
            'unlock_time' => (string) $settings['unlock_time'],
        );

        try {
            $issue = $this->app->admins()->currentIssueSnapshotByRegion($region);
        } catch (\Throwable $exception) {
            $issue = array();
        }

        try {
            $now = $nowText !== '' ? new \DateTimeImmutable((string) $nowText) : new \DateTimeImmutable($this->now());
            if ($this->tableExists('lottery_issues')) {
                $openedIssue = $this->db()->fetch(
                    'SELECT *
                     FROM lottery_issues
                     WHERE region = :region
                       AND status = :status
                       AND planned_open_at IS NOT NULL
                     ORDER BY COALESCE(actual_open_at, planned_open_at) DESC, id DESC
                     LIMIT 1',
                    array(
                        'region' => $region,
                        'status' => 'opened',
                    )
                );
                if ($openedIssue) {
                    $openedPlannedOpenAt = trim((string) ($openedIssue['planned_open_at'] ?? ''));
                    if ($openedPlannedOpenAt !== '') {
                        $openedOpenAt = new \DateTimeImmutable($openedPlannedOpenAt);
                        $openedActualOpenAt = trim((string) ($openedIssue['actual_open_at'] ?? ''));
                        $openedBaseOpenAt = $openedActualOpenAt !== ''
                            ? new \DateTimeImmutable($openedActualOpenAt)
                            : $openedOpenAt;
                        $openedLockStartAt = $openedOpenAt->modify('-' . (int) $settings['before_minutes'] . ' minutes');
                        $openedUnlockAt = new \DateTimeImmutable(
                            $openedBaseOpenAt->format('Y-m-d') . ' ' . (string) $settings['unlock_time'] . ':00'
                        );
                        if ($openedUnlockAt < $openedLockStartAt) {
                            $openedUnlockAt = new \DateTimeImmutable($openedBaseOpenAt->format('Y-m-d') . ' 23:59:00');
                        }
                        if ($now >= $openedLockStartAt && $now < $openedUnlockAt) {
                            $issue = $openedIssue;
                        }
                    }
                }
            }

            $plannedOpenAt = trim((string) ($issue['planned_open_at'] ?? ''));
            if ($plannedOpenAt === '') {
                return $state;
            }

            $openAt = new \DateTimeImmutable($plannedOpenAt);
            $actualOpenAt = trim((string) ($issue['actual_open_at'] ?? ''));
            $baseOpenAt = $actualOpenAt !== '' ? new \DateTimeImmutable($actualOpenAt) : $openAt;
            $lockStartAt = $openAt->modify('-' . (int) $settings['before_minutes'] . ' minutes');
            $unlockAt = new \DateTimeImmutable($baseOpenAt->format('Y-m-d') . ' ' . (string) $settings['unlock_time'] . ':00');

            if ($unlockAt < $lockStartAt) {
                $unlockAt = new \DateTimeImmutable($baseOpenAt->format('Y-m-d') . ' 23:59:00');
            }

            $isLocked = $now >= $lockStartAt && $now < $unlockAt;

            $state['is_locked'] = $isLocked;
            $state['label'] = $isLocked ? '此帖已🔐' : '此帖未锁 🔓';
            $state['lock_start_at'] = $lockStartAt->format('Y-m-d H:i:s');
            $state['unlock_at'] = $unlockAt->format('Y-m-d H:i:s');
            $state['planned_open_at'] = $openAt->format('Y-m-d H:i:s');
            $state['actual_open_at'] = $actualOpenAt !== '' ? $baseOpenAt->format('Y-m-d H:i:s') : '';
        } catch (\Exception $exception) {
            return $state;
        }

        return $state;
    }

    public function assertPostUnlockedForEdit($region, $bypassLock = false)
    {
        if ($bypassLock) {
            return;
        }

        $state = $this->postLockState($region);
        if (empty($state['is_locked'])) {
            return;
        }

        $unlockAt = trim((string) ($state['unlock_at'] ?? ''));
        $message = '当前帖子已锁定，开奖后到达解锁时间才允许编辑。';
        if ($unlockAt !== '') {
            $message = '当前帖子已锁定，预计 ' . format_datetime($unlockAt) . ' 后自动解锁。';
        }

        throw new RuntimeException($message);
    }

    public function listReplies($postId, $viewerId = 0)
    {
        $this->ensureCommunitySchema();
        $rows = $this->db()->fetchAll(
            'SELECT replies.*,
                    users.username,
                    roles.role_key AS user_role_key
             FROM replies
             INNER JOIN users ON users.id = replies.user_id
             LEFT JOIN roles ON roles.id = users.role_id
             WHERE replies.post_id = :post_id
               AND replies.status = :status
             ORDER BY replies.created_at ASC, replies.id ASC',
            array(
                'post_id' => $postId,
                'status' => 'published',
            )
        );

        $indexedRows = array();
        $avatarLevelLabels = array('vip3', '年度vip', '高级vip');
        foreach ($rows as $row) {
            $row['id'] = (int) $row['id'];
            $row['parent_id'] = isset($row['parent_id']) && $row['parent_id'] !== null ? (int) $row['parent_id'] : 0;
            $row['like_count'] = 0;
            $row['liked_by_viewer'] = false;
            $roleKey = (string) ($row['user_role_key'] ?? '');
            if ($roleKey === 'vip_annual') {
                $row['avatar_level_label'] = '年度vip';
            } elseif ($roleKey === 'super_vip' || $roleKey === 'vip2') {
                $row['avatar_level_label'] = '高级vip';
            } elseif ($roleKey === 'vip3') {
                $row['avatar_level_label'] = 'vip3';
            } else {
                $avatarLevelSeed = (int) sprintf(
                    '%u',
                    crc32((string) ($row['user_id'] ?? '') . '|' . (string) ($row['username'] ?? ''))
                );
                $row['avatar_level_label'] = $avatarLevelLabels[$avatarLevelSeed % count($avatarLevelLabels)];
            }
            $row['children'] = array();
            $indexedRows[(int) $row['id']] = $row;
        }

        $roots = array();
        foreach ($indexedRows as $replyId => $row) {
            $parentId = (int) ($row['parent_id'] ?? 0);
            if ($parentId <= 0 || !isset($indexedRows[$parentId])) {
                $roots[$replyId] = $row;
                continue;
            }

            $rootParentId = (int) ($indexedRows[$parentId]['parent_id'] ?? 0);
            if ($rootParentId > 0 && isset($indexedRows[$rootParentId])) {
                $parentId = $rootParentId;
            }

            if (!isset($roots[$parentId])) {
                $roots[$parentId] = $indexedRows[$parentId];
            }

            $roots[$parentId]['children'][] = $row;
        }

        return array_values($roots);
    }

    public function createPost($authorId, array $payload)
    {
        $title = trim((string) $payload['title']);
        $region = (string) $payload['region'];
        $excerpt = trim((string) $payload['excerpt']);
        $preview = trim((string) $payload['preview_content']);
        $full = trim((string) $payload['full_content']);
        $price = (int) $payload['price'];

        if ($title === '' || mb_strlen($title, 'UTF-8') > 180) {
            throw new RuntimeException('帖子标题不能为空且不能超过 180 个字符。');
        }

        if (!in_array($region, array('macau', 'hongkong'), true)) {
            throw new RuntimeException('帖子分区无效。');
        }

        if ($full === '') {
            throw new RuntimeException('帖子内容不能为空。');
        }

        $postId = $this->db()->insertGetId('INSERT INTO posts (region, author_id, title, excerpt, preview_content, full_content, price, color_tag, status, is_top_forever, is_top_admin, is_top_normal, created_at, updated_at) VALUES (:region, :author_id, :title, :excerpt, :preview_content, :full_content, :price, :color_tag, :status, :is_top_forever, :is_top_admin, :is_top_normal, :created_at, :updated_at)', array(
            'region' => $region,
            'author_id' => $authorId,
            'title' => $title,
            'excerpt' => $excerpt !== '' ? $excerpt : truncate_text($full, 40),
            'preview_content' => $preview !== '' ? $preview : truncate_text($full, 60),
            'full_content' => $full,
            'price' => max(0, $price),
            'color_tag' => isset($payload['color_tag']) && $payload['color_tag'] !== '' ? (string) $payload['color_tag'] : 'slate',
            'status' => isset($payload['status']) && $payload['status'] === 'draft' ? 'draft' : 'published',
            'is_top_forever' => !empty($payload['is_top_forever']) ? 1 : 0,
            'is_top_admin' => !empty($payload['is_top_admin']) ? 1 : 0,
            'is_top_normal' => !empty($payload['is_top_normal']) ? 1 : 0,
            'created_at' => $this->now(),
            'updated_at' => $this->now(),
        ));

        if (!isset($payload['status']) || $payload['status'] !== 'draft') {
            $this->seedAutoRepliesForPost($postId);
        }

        return $this->findPost($postId);
    }

    public function savePost(array $payload, $actor)
    {
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;

        if ($id > 0) {
            $post = $this->findPost($id);
            if (!$post) {
                throw new RuntimeException('帖子不存在。');
            }

            $data = $this->createMutablePayload($payload, $post);
            $this->db()->execute('UPDATE posts SET region = :region, title = :title, excerpt = :excerpt, preview_content = :preview_content, full_content = :full_content, price = :price, color_tag = :color_tag, status = :status, is_top_forever = :is_top_forever, is_top_admin = :is_top_admin, is_top_normal = :is_top_normal, deleted_at = :deleted_at, updated_at = :updated_at WHERE id = :id', $data + array('id' => $id));
            $this->app->logs()->admin('posts', 'update', '更新帖子：' . $data['title'], 'post', (string) $id, $actor['id']);

            return $this->findPost($id);
        }

        $post = $this->createPost($actor['id'], $payload);
        $this->app->logs()->admin('posts', 'create', '发布帖子：' . $post['title'], 'post', (string) $post['id'], $actor['id']);

        return $post;
    }

    public function updatePostContentByCustomerService($postId, array $payload, array $actor, $actorType = 'customer_service')
    {
        $postId = (int) $postId;
        $actorId = (int) ($actor['id'] ?? 0);
        if ($postId <= 0 || $actorId <= 0) {
            throw new RuntimeException('帖子不存在或不可编辑。');
        }

        $post = $this->findPost($postId);
        if (!$post) {
            throw new RuntimeException('帖子不存在或不可编辑。');
        }

        $postRegion = (string) ($post['region'] ?? 'macau') === 'hongkong' ? 'hongkong' : 'macau';
        $targetIssueTail = isset($payload['issue_tail'])
            ? $this->normalizeIssueTail((string) $payload['issue_tail'])
            : '';
        if ($targetIssueTail === '') {
            $targetIssueTail = $this->normalizeIssueTail($this->latestIssueTextByRegion($postRegion));
        }
        $priceText = trim((string) ($payload['price'] ?? '0'));
        if (!preg_match('/^\d{1,9}$/', $priceText)) {
            throw new RuntimeException('帖子出售价格必须是 0 到 999999999 的整数。');
        }

        $submittedIssueContent = $this->extractCurrentIssueContent(
            (string) ($payload['full_content'] ?? ''),
            $postRegion,
            $targetIssueTail
        );
        $existingIssueContent = $this->extractCurrentIssueContent(
            (string) ($post['full_content'] ?? ''),
            $postRegion,
            $targetIssueTail
        );
        $submittedRecord = $this->parseForecastRecordContent($submittedIssueContent);
        $existingRecord = $this->parseForecastRecordContent($existingIssueContent);
        $submittedComparable = is_array($submittedRecord)
            ? (string) ($submittedRecord['prediction'] ?? '')
            : $submittedIssueContent;
        $existingComparable = is_array($existingRecord)
            ? (string) ($existingRecord['prediction'] ?? '')
            : $existingIssueContent;
        $submittedComparable = trim((string) preg_replace('/\s+/u', ' ', $submittedComparable));
        $existingComparable = trim((string) preg_replace('/\s+/u', ' ', $existingComparable));

        if ($submittedComparable !== '' && $submittedComparable === $existingComparable) {
            $now = $this->now();
            $this->db()->execute(
                'UPDATE posts
                 SET price = :price,
                     updated_at = CASE
                         WHEN price <> :price_for_update THEN :updated_at
                         ELSE updated_at
                     END
                 WHERE id = :id',
                array(
                    'price' => (int) $priceText,
                    'price_for_update' => (int) $priceText,
                    'updated_at' => $now,
                    'id' => $postId,
                )
            );
            if ((int) ($post['price'] ?? 0) !== (int) $priceText && $this->tableExists('post_manage_meta')) {
                $this->db()->execute(
                    'UPDATE post_manage_meta
                     SET updated_at = :updated_at
                     WHERE post_id = :post_id',
                    array(
                        'updated_at' => $now,
                        'post_id' => $postId,
                    )
                );
            }

            return $this->findPost($postId);
        }
        if ((int) ($post['price'] ?? 0) > 0) {
            $realPurchaseCount = 0;
            if ($this->tableExists('purchases')) {
                $purchaseRow = $this->db()->fetch(
                    'SELECT COUNT(*) AS total_count FROM purchases WHERE post_id = :post_id',
                    array('post_id' => $postId)
                );
                $realPurchaseCount = (int) ($purchaseRow['total_count'] ?? 0);
            }
            $content = trim((string) ($post['full_content'] ?? ''));
            $waitingContent = "资料等待更新中··· ···\n关注本站，精彩无限，中奖根本停不下来······";
            $hasMaterialContent = $content !== ''
                && mb_strpos($content, '资料等待更新中', 0, 'UTF-8') === false
                && !in_array($content, array(
                    $waitingContent,
                    '此资料出售，购买后可查看完整资料',
                    '此内容为出售内容，购买后可查看完整资料。',
                ), true);
            if ($this->salePostHasOpenedDraw($post)) {
                throw new RuntimeException('公开出售帖子的资料内容已经固定，不能再变更。');
            }
            if ($realPurchaseCount > 0 && $hasMaterialContent) {
                throw new RuntimeException('出售帖子已有会员购买，资料内容不能再变更。');
            }

            $state = $this->postLockState($postRegion);
            if (!empty($state['is_locked']) && $hasMaterialContent) {
                throw new RuntimeException('出售帖子已进入锁帖期，已更新的资料内容不能再变更。');
            }
        }
        $targetIssueTail = isset($payload['issue_tail'])
            ? $this->normalizeIssueTail((string) $payload['issue_tail'])
            : '';
        if ($targetIssueTail === '') {
            $targetIssueTail = $this->normalizeIssueTail($this->latestIssueTextByRegion($postRegion));
        }
        if ($targetIssueTail !== '' && $this->tableExists('lottery_draws')) {
            $targetIssueDraw = $this->db()->fetch(
                'SELECT id
                 FROM lottery_draws
                 WHERE region = :region
                   AND RIGHT(issue_no, 3) = :issue_tail
                 ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
                 LIMIT 1',
                array(
                    'region' => $postRegion,
                    'issue_tail' => $targetIssueTail,
                )
            );
            if ($targetIssueDraw) {
                throw new RuntimeException('该期已开奖，资料内容已进入历史记录，不能再编辑。');
            }
        }
        $content = $this->resolveCustomerServiceSaveContent(
            (string) ($payload['full_content'] ?? ''),
            $post,
            $targetIssueTail
        );
        if ($content === '') {
            throw new RuntimeException('本期资料内容不能为空。');
        }
        $contentLength = function_exists('mb_strlen') ? mb_strlen($content, 'UTF-8') : strlen($content);
        if ($contentLength > 20000) {
            throw new RuntimeException('本期资料内容不能超过 20000 个字符。');
        }

        $priceText = trim((string) ($payload['price'] ?? '0'));
        if (!preg_match('/^\d{1,9}$/', $priceText)) {
            throw new RuntimeException('帖子出售价格必须是 0 到 999999999 的整数。');
        }

        $price = (int) $priceText;
        $preserveSummaryFields = !empty($payload['preserve_summary_fields']);
        $now = $this->now();
        if ($preserveSummaryFields) {
            $this->db()->execute(
                'UPDATE posts
                 SET full_content = :full_content,
                     price = :price,
                     updated_at = :updated_at
                 WHERE id = :id',
                array(
                    'full_content' => $content,
                    'price' => $price,
                    'updated_at' => $now,
                    'id' => $postId,
                )
            );
        } else {
            $this->db()->execute(
                'UPDATE posts
                 SET excerpt = :excerpt,
                     preview_content = :preview_content,
                     full_content = :full_content,
                     price = :price,
                     updated_at = :updated_at
                 WHERE id = :id',
                array(
                    'excerpt' => truncate_text($content, 40),
                    'preview_content' => $content,
                    'full_content' => $content,
                    'price' => $price,
                    'updated_at' => $now,
                    'id' => $postId,
                )
            );
        }

        if ($this->tableExists('post_manage_meta')) {
            $this->db()->execute(
                'UPDATE post_manage_meta
                 SET manual_material = :manual_material,
                     auto_update_content = CASE
                         WHEN auto_update_mode = :specified_mode THEN :auto_update_content
                         ELSE auto_update_content
                     END,
                     updated_at = :updated_at
                 WHERE post_id = :post_id',
                array(
                    'manual_material' => $content,
                    'specified_mode' => 'specified',
                    'auto_update_content' => $content,
                    'updated_at' => $now,
                    'post_id' => $postId,
                )
            );
        }

        if ((string) $actorType === 'admin') {
            $this->app->logs()->system('post', '后台编辑帖子资料', 'info', array(
                'post_id' => $postId,
                'admin_id' => $actorId,
            ));
        } else {
            $this->app->logs()->system('post', '在线客服编辑帖子资料', 'info', array(
                'post_id' => $postId,
                'agent_id' => $actorId,
            ));
        }

        return $this->findPost($postId);
    }

    protected function createMutablePayload(array $payload, array $fallback)
    {
        $title = trim((string) (isset($payload['title']) ? $payload['title'] : $fallback['title']));
        $full = trim((string) (isset($payload['full_content']) ? $payload['full_content'] : $fallback['full_content']));

        if ($title === '') {
            throw new RuntimeException('帖子标题不能为空。');
        }

        if ($full === '') {
            throw new RuntimeException('帖子正文不能为空。');
        }

        return array(
            'region' => isset($payload['region']) ? (string) $payload['region'] : $fallback['region'],
            'title' => $title,
            'excerpt' => trim((string) (isset($payload['excerpt']) ? $payload['excerpt'] : $fallback['excerpt'])),
            'preview_content' => trim((string) (isset($payload['preview_content']) ? $payload['preview_content'] : $fallback['preview_content'])),
            'full_content' => $full,
            'price' => max(0, (int) (isset($payload['price']) ? $payload['price'] : $fallback['price'])),
            'color_tag' => isset($payload['color_tag']) ? (string) $payload['color_tag'] : $fallback['color_tag'],
            'status' => isset($payload['status']) ? (string) $payload['status'] : $fallback['status'],
            'is_top_forever' => !empty($payload['is_top_forever']) ? 1 : 0,
            'is_top_admin' => !empty($payload['is_top_admin']) ? 1 : 0,
            'is_top_normal' => !empty($payload['is_top_normal']) ? 1 : 0,
            'deleted_at' => (isset($payload['status']) ? (string) $payload['status'] : (string) $fallback['status']) === 'deleted' ? $this->now() : null,
            'updated_at' => $this->now(),
        );
    }

    protected function stripSyncedIssuePrefix($title)
    {
        $title = trim((string) $title);

        return trim((string) preg_replace('/^(?:\s*\d{1,6}\s*(?:期|鏈[^\s:：]{0,6}|链[^\s:：]{0,6}|閺[^\s:：]{0,6})\s*[:：]?\s*)+/u', '', $title));
    }

    protected function extractCurrentIssueContent($content, $region, $targetIssueTail = '')
    {
        $content = trim((string) $content);
        if ($content === '') {
            return '';
        }

        $currentIssueTail = $this->normalizeIssueTail((string) $targetIssueTail);
        if ($currentIssueTail === '') {
            $currentIssueTail = $this->normalizeIssueTail($this->latestIssueTextByRegion((string) $region));
        }
        if ($currentIssueTail === '') {
            return $content;
        }

        $lines = preg_split('/\R/u', $content);
        if (!is_array($lines)) {
            return $content;
        }

        $currentBlockLines = array();
        $currentBlockTail = '';
        foreach ($lines as $line) {
            $lineText = (string) $line;
            if (preg_match('/^\s*(\d{1,6})\s*[^:：]{0,12}[:：]/u', $lineText, $matches)) {
                if ($currentBlockTail === $currentIssueTail && !empty($currentBlockLines)) {
                    return trim(implode("\n", $currentBlockLines));
                }

                $currentBlockTail = $this->normalizeIssueTail((string) $matches[1]);
                $currentBlockLines = array($lineText);
                continue;
            }

            if ($currentBlockTail !== '') {
                $currentBlockLines[] = $lineText;
            }
        }

        if ($currentBlockTail === $currentIssueTail && !empty($currentBlockLines)) {
            return trim(implode("\n", $currentBlockLines));
        }

        return $content;
    }

    protected function resolveCustomerServiceSaveContent($inputContent, array $post, $targetIssueTail = '')
    {
        $inputContent = trim((string) $inputContent);
        if ($inputContent === '') {
            return '';
        }

        $region = (string) ($post['region'] ?? 'macau');
        $currentInputContent = $this->extractCurrentIssueContent($inputContent, $region, $targetIssueTail);
        if ($this->parseForecastRecordContent($currentInputContent)) {
            return $this->replaceCurrentIssueContent(
                (string) ($post['full_content'] ?? ''),
                $region,
                $currentInputContent,
                $targetIssueTail
            );
        }

        $currentPostContent = $this->extractCurrentIssueContent(
            (string) ($post['full_content'] ?? ''),
            $region,
            $targetIssueTail
        );
        $record = $this->parseForecastRecordContent($currentPostContent);
        if (!is_array($record)) {
            return $currentInputContent;
        }

        $prediction = preg_replace('/\s+/u', ' ', trim($currentInputContent));
        if ($prediction === '') {
            return '';
        }

        $currentContent = $this->replaceForecastRecordPredictionPreservingLayout(
            $currentPostContent,
            $prediction,
            $record
        );
        if ($currentContent === '') {
            $currentContent = $this->formatForecastRecordContent($record, $prediction);
        }

        return $this->replaceCurrentIssueContent(
            (string) ($post['full_content'] ?? ''),
            $region,
            $currentContent,
            $targetIssueTail
        );
    }

    protected function replaceForecastRecordPredictionPreservingLayout($content, $prediction, array $record)
    {
        $content = trim((string) $content);
        $prediction = trim((string) $prediction);
        if ($content === '' || $prediction === '') {
            return '';
        }

        if ((string) ($record['layout'] ?? '') === 'double') {
            $lines = preg_split('/\R/u', $content);
            if (!is_array($lines) || count($lines) < 2) {
                return '';
            }
            if (!preg_match('/^(\s*).+?(\s+开[:：]\s*.*?)(\s*)$/u', (string) $lines[1], $matches)) {
                return '';
            }
            $lines[1] = (string) $matches[1] . $prediction . (string) $matches[2] . (string) $matches[3];

            return implode("\n", $lines);
        }

        if (!preg_match(
            '/^(\s*\d{1,6}[^:：]{0,12}[:：]\s*\S+\s+\S+\s+).+?(\s+开[:：]\s*.*?)(\s*)$/us',
            $content,
            $matches
        )) {
            return '';
        }

        return (string) $matches[1] . $prediction . (string) $matches[2] . (string) $matches[3];
    }

    protected function replaceCurrentIssueContent($content, $region, $replacement, $targetIssueTail = '')
    {
        $content = trim((string) $content);
        $replacement = trim((string) $replacement);
        if ($content === '') {
            return $replacement;
        }

        $currentIssueTail = $this->normalizeIssueTail((string) $targetIssueTail);
        if ($currentIssueTail === '') {
            $currentIssueTail = $this->normalizeIssueTail($this->latestIssueTextByRegion((string) $region));
        }
        if ($currentIssueTail === '') {
            return $replacement;
        }

        $lines = preg_split('/\R/u', $content);
        if (!is_array($lines)) {
            return $replacement;
        }

        $blocks = array();
        $activeIndex = -1;
        foreach ($lines as $line) {
            $lineText = (string) $line;
            if (preg_match('/^\s*(\d{1,6})\s*[^:：]{0,12}[:：]/u', $lineText, $matches)) {
                $blocks[] = array(
                    'tail' => $this->normalizeIssueTail((string) $matches[1]),
                    'lines' => array($lineText),
                );
                $activeIndex = count($blocks) - 1;
                continue;
            }

            if ($activeIndex >= 0) {
                $blocks[$activeIndex]['lines'][] = $lineText;
                continue;
            }

            $blocks[] = array(
                'tail' => '',
                'lines' => array($lineText),
            );
            $activeIndex = count($blocks) - 1;
        }

        $replaced = false;
        foreach ($blocks as $index => $block) {
            if ((string) ($block['tail'] ?? '') !== $currentIssueTail) {
                continue;
            }

            $blocks[$index]['lines'] = preg_split('/\R/u', $replacement);
            if (!is_array($blocks[$index]['lines'])) {
                $blocks[$index]['lines'] = array($replacement);
            }
            $replaced = true;
            break;
        }

        if (!$replaced) {
            $blocks[] = array(
                'tail' => $currentIssueTail,
                'lines' => preg_split('/\R/u', $replacement),
            );
            if (!is_array($blocks[count($blocks) - 1]['lines'])) {
                $blocks[count($blocks) - 1]['lines'] = array($replacement);
            }
        }

        $mergedLines = array();
        foreach ($blocks as $block) {
            foreach ((array) ($block['lines'] ?? array()) as $line) {
                $mergedLines[] = (string) $line;
            }
        }

        return trim(implode("\n", $mergedLines));
    }

    protected function parseForecastRecordContent($content)
    {
        $content = trim((string) $content);
        if ($content === '') {
            return null;
        }

        $lines = preg_split('/\R/u', $content);
        if (!is_array($lines) || empty($lines)) {
            return null;
        }

        $line = trim((string) $lines[0]);
        if ($line === '') {
            return null;
        }

        if (preg_match('/^(\s*\d{1,6}[^:：]{0,12}[:：]\s*)(\S+)\s+(\S+)\s+(.+?)\s+(开[:：]\s*.*?)\s*$/u', $line, $matches)) {
            return array(
                'issue_prefix' => (string) $matches[1],
                'author' => (string) $matches[2],
                'type' => (string) $matches[3],
                'prediction' => trim((string) $matches[4]),
                'open' => trim((string) $matches[5]),
                'layout' => 'single',
            );
        }

        if (count($lines) < 2 || !preg_match('/^(\s*\d{1,6}[^:：]{0,12}[:：]\s*)(\S+)\s+(.+?)\s*$/u', $line, $matches)) {
            return null;
        }

        $detailLine = trim((string) $lines[1]);
        if (!preg_match('/^(.+?)\s+(开[:：]\s*.*?)\s*$/u', $detailLine, $detailMatches)) {
            return null;
        }

        return array(
            'issue_prefix' => (string) $matches[1],
            'author' => (string) $matches[2],
            'type' => (string) $matches[3],
            'prediction' => trim((string) $detailMatches[1]),
            'open' => trim((string) $detailMatches[2]),
            'layout' => 'double',
        );
    }

    protected function formatForecastRecordContent(array $record, $prediction)
    {
        $prediction = trim((string) $prediction);
        if ((string) ($record['layout'] ?? '') === 'double') {
            return (string) $record['issue_prefix']
                . (string) $record['author']
                . ' '
                . (string) $record['type']
                . "\n"
                . $prediction
                . ' '
                . (string) $record['open'];
        }

        return (string) $record['issue_prefix']
            . (string) $record['author']
            . ' '
            . (string) $record['type']
            . ' '
            . $prediction
            . ' '
            . (string) $record['open'];
    }

    protected function incrementIssueNo($issueNo)
    {
        $issueNo = trim((string) $issueNo);
        if ($issueNo === '') {
            return '';
        }

        if (preg_match('/^(.*?)(\d+)(\D*)$/u', $issueNo, $matches)) {
            $prefix = (string) $matches[1];
            $numberPart = (string) $matches[2];
            $suffix = (string) $matches[3];
            $nextNumber = str_pad((string) ((int) $numberPart + 1), strlen($numberPart), '0', STR_PAD_LEFT);

            return $prefix . $nextNumber . $suffix;
        }

        return $issueNo;
    }

    protected function normalizeIssueTail($issueNo)
    {
        $issueNo = preg_replace('/\D+/', '', trim((string) $issueNo));

        if ($issueNo === '') {
            return '';
        }

        $tail = strlen($issueNo) > 3 ? substr($issueNo, -3) : $issueNo;

        return str_pad($tail, 3, '0', STR_PAD_LEFT);
    }

    protected function latestIssueTailFromContent($content)
    {
        $content = trim((string) $content);
        if ($content === '') {
            return '';
        }

        $lines = preg_split('/\R/u', $content);
        if (!is_array($lines)) {
            return '';
        }

        $latestIssueTail = '';
        foreach ($lines as $line) {
            $lineText = trim((string) $line);
            if (!preg_match('/^\s*(\d{1,6})\s*[^:\x{FF1A}]{0,16}[:\x{FF1A}]/u', $lineText, $matches)) {
                continue;
            }

            $issueTail = $this->normalizeIssueTail((string) ($matches[1] ?? ''));
            if ($issueTail !== '') {
                $latestIssueTail = $issueTail;
            }
        }

        return $latestIssueTail;
    }

    protected function latestOpenedIssueTailFromContent($content)
    {
        $content = trim((string) $content);
        if ($content === '') {
            return '';
        }

        $lines = preg_split('/\R/u', $content);
        if (!is_array($lines)) {
            return '';
        }

        $currentIssueTail = '';
        $latestOpenedIssueTail = '';
        foreach ($lines as $line) {
            $lineText = trim((string) $line);
            if (preg_match('/^\s*(\d{1,6})\s*[^:\x{FF1A}]{0,16}[:\x{FF1A}]/u', $lineText, $issueMatches)) {
                $currentIssueTail = $this->normalizeIssueTail((string) ($issueMatches[1] ?? ''));
            }

            if ($currentIssueTail === '' || !preg_match('/[开開]\s*[:：]\s*(.+)$/u', $lineText, $openMatches)) {
                continue;
            }

            $openText = trim((string) ($openMatches[1] ?? ''));
            if ($openText === ''
                || preg_match('/^(?:待(?:开奖|開獎)|[-?？]+)$/u', $openText)
                || mb_strpos($lineText, '资料等待更新中', 0, 'UTF-8') !== false
                || mb_strpos($lineText, '資料等待更新中', 0, 'UTF-8') !== false
            ) {
                continue;
            }

            $latestOpenedIssueTail = $currentIssueTail;
        }

        return $latestOpenedIssueTail;
    }

    protected function latestIssueTextByRegion($region)
    {
        static $cache = array();

        $region = $region === 'hongkong' ? 'hongkong' : 'macau';
        if (isset($cache[$region])) {
            return $cache[$region];
        }
        $cache[$region] = $this->app->admins()->managedIssuePrefixTextByRegion($region);

        return $cache[$region];
    }

    protected function dailyTitleColorPalette()
    {
        return array(
            '#DC2626',
            '#EA580C',
            '#CA8A04',
            '#16A34A',
            '#0891B2',
            '#2563EB',
            '#7C3AED',
            '#DB2777',
            '#0F766E',
            '#9333EA',
            '#1D4ED8',
            '#BE123C',
        );
    }

    public function addReply($postId, $userId, $content, $parentId = 0)
    {
        $this->ensureCommunitySchema();

        $content = trim((string) $content);
        if ($content === '') {
            throw new RuntimeException('回复内容不能为空。');
        }

        $post = $this->findPost($postId);
        if (!$post) {
            throw new RuntimeException('帖子不存在或已下架。');
        }

        $normalizedParentId = $this->normalizeReplyParentId((int) $postId, (int) $parentId);

        $this->db()->beginTransaction();
        try {
            $this->db()->execute('INSERT INTO replies (post_id, parent_id, user_id, content, status, created_at) VALUES (:post_id, :parent_id, :user_id, :content, :status, :created_at)', array(
                'post_id' => $postId,
                'parent_id' => $normalizedParentId > 0 ? $normalizedParentId : null,
                'user_id' => $userId,
                'content' => $content,
                'status' => 'published',
                'created_at' => $this->now(),
            ));

            $this->db()->execute('UPDATE posts SET reply_count = reply_count + 1, updated_at = :updated_at WHERE id = :id', array(
                'updated_at' => $this->now(),
                'id' => $postId,
            ));

            $this->db()->commit();
        } catch (\Exception $exception) {
            $this->db()->rollBack();
            throw $exception;
        }

        return true;
    }

    public function autoReplyDefaultItems()
    {
        return array();
    }

    public function seedAutoRepliesForPost($postId, $limit = 3)
    {
        $this->ensureCommunitySchema();

        static $autoReplyRuntimeCache = array();

        $postId = (int) $postId;
        if ($postId <= 0) {
            return 0;
        }

        $post = $this->db()->fetch(
            'SELECT posts.id,
                    posts.author_id,
                    posts.region,
                    posts.status,
                    posts.created_at,
                    posts.full_content,
                    COALESCE(post_meta.recent_result_log, \'\') AS recent_result_log
             FROM posts
             LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
             WHERE posts.id = :id
               AND posts.status = :status
               AND posts.deleted_at IS NULL
               AND COALESCE(post_meta.is_hidden, 0) = 0
             LIMIT 1',
            array(
                'id' => $postId,
                'status' => 'published',
            )
        );
        if (!$post) {
            return 0;
        }

        $region = (string) ($post['region'] ?? 'macau');
        if ($region !== 'hongkong') {
            $region = 'macau';
        }

        $postContent = trim((string) ($post['full_content'] ?? ''));
        $recentResultLog = trim((string) ($post['recent_result_log'] ?? ''));

        $settings = array();
        $settingsRaw = trim((string) $this->app->settings()->get('post_generator.settings.' . $region, ''));
        if ($settingsRaw !== '') {
            $decodedSettings = json_decode($settingsRaw, true);
            if (is_array($decodedSettings)) {
                $settings = $decodedSettings;
                if (array_key_exists('auto_reply_enabled', $settings) && (string) $settings['auto_reply_enabled'] !== '1') {
                    return 0;
                }
            }
        }

        $autoReplyTone = 'pending';
        $contentLines = $postContent !== '' ? preg_split('/\R/u', $postContent) : array();
        if (!is_array($contentLines)) {
            $contentLines = array();
        }
        $currentForecastLine = '';
        $forecastLines = array();
        foreach ($contentLines as $contentLine) {
            $contentLine = trim((string) $contentLine);
            if ($contentLine !== '' && mb_strpos($contentLine, '开', 0, 'UTF-8') !== false) {
                $forecastLines[] = $contentLine;
                $currentForecastLine = $contentLine;
            }
        }
        $pendingRecordText = $currentForecastLine !== '' ? $currentForecastLine : $postContent;
        $hasPendingRecord = $postContent === ''
            || mb_strpos($pendingRecordText, '资料等待更新中', 0, 'UTF-8') !== false
            || mb_strpos($pendingRecordText, '待开奖', 0, 'UTF-8') !== false;
        $resolveAutoReplyTone = static function ($statusText) {
            $statusText = trim((string) $statusText);
            if (in_array($statusText, array('准', '中', '赢', '发', '發', '对', '對'), true)) {
                return 'hit';
            }
            if (in_array($statusText, array('错', '錯'), true)) {
                return 'miss';
            }

            return '';
        };
        $resolveAutoReplyToneFromLine = static function ($line) use ($resolveAutoReplyTone) {
            $line = trim((string) $line);
            if ($line !== '' && preg_match('/(准|中|赢|发|發|对|對|错|錯)\s*$/u', $line, $statusMatches)) {
                return $resolveAutoReplyTone((string) ($statusMatches[1] ?? ''));
            }

            return '';
        };
        $historicalAutoReplyTone = '';
        if (count($forecastLines) > 1) {
            for ($forecastLineIndex = count($forecastLines) - 2; $forecastLineIndex >= 0; $forecastLineIndex--) {
                $historicalAutoReplyTone = $resolveAutoReplyToneFromLine((string) ($forecastLines[$forecastLineIndex] ?? ''));
                if ($historicalAutoReplyTone !== '') {
                    break;
                }
            }
        }
        if (!$hasPendingRecord) {
            $latestStatusTone = '';
            if ($latestStatusTone === '' && $currentForecastLine !== '') {
                $latestStatusTone = $resolveAutoReplyToneFromLine($currentForecastLine);
            }
            if ($latestStatusTone === '' && $currentForecastLine !== '') {
                $currentLineStats = $this->app->admins()->managedForecastRecordStats(
                    $region,
                    $currentForecastLine,
                    '',
                    true,
                    false
                );
                $currentLineTotal = (int) ($currentLineStats['total'] ?? 0);
                $currentLineHit = (int) ($currentLineStats['hit'] ?? 0);
                if ($currentLineTotal > 0) {
                    $latestStatusTone = $currentLineHit > 0 ? 'hit' : 'miss';
                }
            }
            if ($latestStatusTone === '' && $recentResultLog !== '') {
                $recentItems = preg_split('/[\s,，]+/u', $recentResultLog);
                if (is_array($recentItems)) {
                    foreach ($recentItems as $recentItem) {
                        if (preg_match('/(准|中|赢|发|發|对|對|错|錯)\s*$/u', trim((string) $recentItem), $statusMatches)) {
                            $latestStatusTone = $resolveAutoReplyTone((string) $statusMatches[1]);
                            break;
                        }
                    }
                }
            }
            if ($latestStatusTone === '' && $contentLines !== array()) {
                foreach ($contentLines as $contentLine) {
                    $contentLine = trim((string) $contentLine);
                    if ($contentLine === '' || mb_strpos($contentLine, '开', 0, 'UTF-8') === false) {
                        continue;
                    }
                    if (preg_match('/(准|中|赢|发|發|对|對|错|錯)\s*$/u', $contentLine, $statusMatches)) {
                        $latestStatusTone = $resolveAutoReplyTone((string) $statusMatches[1]);
                        break;
                    }
                }
            }
            if ($latestStatusTone === '') {
                $recordStats = $this->app->admins()->managedForecastRecordStats(
                    $region,
                    $postContent,
                    $recentResultLog,
                    true,
                    true
                );
                $recordTotal = (int) ($recordStats['total'] ?? 0);
                $recordHit = (int) ($recordStats['hit'] ?? 0);
                if ($recordTotal > 0) {
                    $latestStatusTone = $recordHit > 0 ? 'hit' : 'miss';
                }
            }
            if ($latestStatusTone !== '') {
                $autoReplyTone = $latestStatusTone;
            }
        }

        $normalizeSettingValue = static function ($value, $min, $max, $default) {
            $value = trim((string) $value);
            if ($value === '') {
                $number = (int) $default;
            } else {
                $number = (int) preg_replace('/\D+/', '', $value);
            }

            return max((int) $min, min((int) $max, $number));
        };
        $stableRange = static function ($key, $min, $max) {
            $min = (int) $min;
            $max = (int) $max;
            if ($min > $max) {
                $swap = $min;
                $min = $max;
                $max = $swap;
            }
            if ($min === $max) {
                return $min;
            }

            $seedNumber = (int) sprintf('%u', crc32((string) $key));

            return $min + ($seedNumber % ($max - $min + 1));
        };

        $legacyLimit = isset($settings['auto_reply_count'])
            ? $normalizeSettingValue($settings['auto_reply_count'], 1, 99, $limit)
            : $normalizeSettingValue($limit, 1, 99, 3);
        $baseMin = array_key_exists('auto_reply_base_min', $settings)
            ? $normalizeSettingValue($settings['auto_reply_base_min'], 1, 99, 2)
            : $legacyLimit;
        $baseMax = array_key_exists('auto_reply_base_max', $settings)
            ? $normalizeSettingValue($settings['auto_reply_base_max'], 1, 99, 5)
            : $legacyLimit;
        $dailyMin = $normalizeSettingValue($settings['auto_reply_daily_min'] ?? '1', 0, 99, 1);
        $dailyMax = $normalizeSettingValue($settings['auto_reply_daily_max'] ?? '3', 0, 99, 3);
        $issueMin = $normalizeSettingValue($settings['auto_reply_issue_min'] ?? '1', 1, 99, 1);
        $issueMax = $normalizeSettingValue($settings['auto_reply_issue_max'] ?? '3', 1, 99, 3);
        $forbidStartHour = $normalizeSettingValue($settings['auto_reply_forbid_start_hour'] ?? '1', 0, 23, 1);
        $forbidEndHour = $normalizeSettingValue($settings['auto_reply_forbid_end_hour'] ?? '8', 0, 23, 8);
        if ($baseMin > $baseMax) {
            $swapBase = $baseMin;
            $baseMin = $baseMax;
            $baseMax = $swapBase;
        }
        if ($dailyMin > $dailyMax) {
            $swapDaily = $dailyMin;
            $dailyMin = $dailyMax;
            $dailyMax = $swapDaily;
        }
        if ($issueMin > $issueMax) {
            $swapIssue = $issueMin;
            $issueMin = $issueMax;
            $issueMax = $swapIssue;
        }

        $now = $this->now();
        $nowTimestamp = strtotime($now);
        if ($nowTimestamp === false) {
            $nowTimestamp = time();
        }
        $nowClock = date('H:i:s', $nowTimestamp);
        $forbidStartClock = str_pad((string) $forbidStartHour, 2, '0', STR_PAD_LEFT) . ':00:00';
        $forbidEndClock = str_pad((string) $forbidEndHour, 2, '0', STR_PAD_LEFT) . ':00:00';
        $isForbiddenNow = false;
        if ($forbidStartHour < $forbidEndHour) {
            $isForbiddenNow = $nowClock >= $forbidStartClock && $nowClock < $forbidEndClock;
        } elseif ($forbidStartHour > $forbidEndHour) {
            $isForbiddenNow = $nowClock >= $forbidStartClock || $nowClock < $forbidEndClock;
        }
        if ($isForbiddenNow) {
            return 0;
        }

        $postCreatedTimestamp = strtotime((string) ($post['created_at'] ?? ''));
        if ($postCreatedTimestamp === false || $postCreatedTimestamp > $nowTimestamp) {
            $postCreatedTimestamp = $nowTimestamp;
        }
        $postCreatedDate = date('Y-m-d', $postCreatedTimestamp);
        $todayDate = date('Y-m-d', $nowTimestamp);
        $todayStartTimestamp = strtotime($todayDate . ' 00:00:00');
        if ($todayStartTimestamp === false) {
            $todayStartTimestamp = strtotime(date('Y-m-d') . ' 00:00:00');
        }
        $currentIssueOpenTimestamp = null;
        $currentIssueTail = $this->latestIssueTailFromContent($postContent);
        $currentIssueHasDraw = false;
        if ($currentIssueTail !== '' && $this->tableExists('lottery_draws')) {
            $currentIssueDraw = $this->db()->fetch(
                'SELECT id
                 FROM lottery_draws
                 WHERE region = :region
                   AND RIGHT(issue_no, 3) = :issue_tail
                 ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
                 LIMIT 1',
                array(
                    'region' => $region,
                    'issue_tail' => $currentIssueTail,
                )
            );
            $currentIssueHasDraw = !empty($currentIssueDraw);
        }
        if ($currentIssueTail !== '' && $this->tableExists('lottery_issues')) {
            $currentIssueRow = $this->db()->fetch(
                'SELECT planned_open_at, actual_open_at
                 FROM lottery_issues
                 WHERE region = :region
                   AND RIGHT(issue_no, 3) = :issue_tail
                 ORDER BY CAST(issue_no AS UNSIGNED) DESC, id DESC
                 LIMIT 1',
                array(
                    'region' => $region,
                    'issue_tail' => $currentIssueTail,
                )
            );
            if ($currentIssueRow) {
                $currentIssueOpenAt = trim((string) ($currentIssueRow['actual_open_at'] ?? ''));
                if ($currentIssueOpenAt === '') {
                    $currentIssueOpenAt = trim((string) ($currentIssueRow['planned_open_at'] ?? ''));
                }
                if ($currentIssueOpenAt !== '') {
                    $currentIssueOpenTimestamp = strtotime($currentIssueOpenAt);
                    if ($currentIssueOpenTimestamp === false) {
                        $currentIssueOpenTimestamp = null;
                    }
                }
            }
        }

        $randomAllowedTimestamp = static function ($date, $earliestTimestamp, $latestTimestamp, $seedKey) use ($stableRange, $forbidStartHour, $forbidEndHour) {
            $earliestTimestamp = (int) $earliestTimestamp;
            $latestTimestamp = (int) $latestTimestamp;
            if ($earliestTimestamp > $latestTimestamp) {
                return null;
            }

            $date = date('Y-m-d', strtotime((string) $date));
            $dayStartTimestamp = strtotime($date . ' 00:00:00');
            $dayEndTimestamp = strtotime($date . ' 23:59:59');
            $forbidStartTimestamp = strtotime($date . ' ' . str_pad((string) $forbidStartHour, 2, '0', STR_PAD_LEFT) . ':00:00');
            $forbidEndTimestamp = strtotime($date . ' ' . str_pad((string) $forbidEndHour, 2, '0', STR_PAD_LEFT) . ':00:00');
            if ($dayStartTimestamp === false || $dayEndTimestamp === false || $forbidStartTimestamp === false || $forbidEndTimestamp === false) {
                return null;
            }

            if ($forbidStartHour === $forbidEndHour) {
                $windows = array(array($dayStartTimestamp, $dayEndTimestamp));
            } elseif ($forbidStartHour < $forbidEndHour) {
                $windows = array(
                    array($dayStartTimestamp, $forbidStartTimestamp - 1),
                    array($forbidEndTimestamp, $dayEndTimestamp),
                );
            } else {
                $windows = array(
                    array($forbidEndTimestamp, $forbidStartTimestamp - 1),
                );
            }
            $availableWindows = array();
            $totalSeconds = 0;
            foreach ($windows as $window) {
                $windowStart = max((int) $window[0], $earliestTimestamp);
                $windowEnd = min((int) $window[1], $latestTimestamp);
                if ($windowStart > $windowEnd) {
                    continue;
                }

                $length = $windowEnd - $windowStart + 1;
                $availableWindows[] = array($windowStart, $windowEnd, $length);
                $totalSeconds += $length;
            }
            if ($totalSeconds <= 0) {
                return null;
            }

            $pick = $stableRange($seedKey, 0, $totalSeconds - 1);
            foreach ($availableWindows as $window) {
                if ($pick < (int) $window[2]) {
                    return (int) $window[0] + $pick;
                }

                $pick -= (int) $window[2];
            }

            $lastWindow = end($availableWindows);

            return is_array($lastWindow) ? (int) $lastWindow[1] : null;
        };

        $slotTimestamps = array();
        $baseTarget = $stableRange('post-auto-replies-base|' . $postId, $baseMin, $baseMax);
        $initialTarget = max(1, min(2, $baseTarget));
        $firstReplyDelayDays = $stableRange('post-auto-replies-start-delay|' . $postId, $issueMin, $issueMax);
        $firstReplyTimestamp = strtotime('+' . $firstReplyDelayDays . ' days', $postCreatedTimestamp);
        if ($firstReplyTimestamp === false) {
            $firstReplyTimestamp = strtotime('+' . max(1, $issueMin) . ' days', $postCreatedTimestamp);
        }
        if ($firstReplyTimestamp === false) {
            $firstReplyTimestamp = $postCreatedTimestamp;
        }
        $firstReplyDate = date('Y-m-d', $firstReplyTimestamp);
        $firstReplyDayStartTimestamp = strtotime($firstReplyDate . ' 00:00:00');
        $firstReplyLatestTimestamp = strtotime($firstReplyDate . ' 23:59:59');
        if ($firstReplyLatestTimestamp !== false && $firstReplyTimestamp <= $nowTimestamp) {
            for ($index = 0; $index < $initialTarget; $index++) {
                $slotTimestamp = $randomAllowedTimestamp(
                    $firstReplyDate,
                    $firstReplyTimestamp + $index,
                    $firstReplyLatestTimestamp,
                    'post-auto-replies-base-time|' . $postId . '|' . $index
                );
                if ($slotTimestamp !== null && $slotTimestamp <= $nowTimestamp) {
                    $slotTimestamps[] = (int) $slotTimestamp;
                }
            }
        }

        if ($dailyMax > 0 && $todayStartTimestamp !== false && $firstReplyDayStartTimestamp !== false) {
            $dailyStartTimestamp = strtotime('+1 day', $firstReplyDayStartTimestamp);
            if ($dailyStartTimestamp === false) {
                $dailyStartTimestamp = $firstReplyDayStartTimestamp;
            }
            for ($dayTimestamp = $dailyStartTimestamp; $dayTimestamp <= $todayStartTimestamp; $dayTimestamp = strtotime('+1 day', $dayTimestamp)) {
                if ($dayTimestamp === false) {
                    break;
                }
                $dayDate = date('Y-m-d', $dayTimestamp);
                if ($dayDate <= $postCreatedDate) {
                    continue;
                }

                $dailyTarget = $stableRange('post-auto-replies-daily|' . $postId . '|' . $dayDate, $dailyMin, $dailyMax);
                $dayLatestTimestamp = strtotime($dayDate . ' 23:59:59');
                if ($dayLatestTimestamp === false) {
                    continue;
                }
                for ($dailyIndex = 0; $dailyIndex < $dailyTarget; $dailyIndex++) {
                    $slotTimestamp = $randomAllowedTimestamp(
                        $dayDate,
                        strtotime($dayDate . ' 00:00:00'),
                        $dayLatestTimestamp,
                        'post-auto-replies-daily-time|' . $postId . '|' . $dayDate . '|' . $dailyIndex
                    );
                    if ($slotTimestamp !== null && $slotTimestamp <= $nowTimestamp) {
                        $slotTimestamps[] = (int) $slotTimestamp;
                    }
                }
            }
        }
        sort($slotTimestamps);
        if ($slotTimestamps === array()) {
            return 0;
        }

        $usernameLetters = 'abcdefghijklmnopqrstuvwxyz';
        $usernameNumbers = '0123456789';
        $usernameLetterMaxIndex = strlen($usernameLetters) - 1;
        $usernameNumberMaxIndex = strlen($usernameNumbers) - 1;
        $buildUsernamePart = static function ($characters, $maxIndex, $length) {
            $part = '';
            for ($partIndex = 0; $partIndex < $length; $partIndex++) {
                $part .= $characters[mt_rand(0, $maxIndex)];
            }

            return $part;
        };
        $followIssueOne = mt_rand(2, 8);
        $followIssueTwo = mt_rand(3, 12);
        $followIssueThree = mt_rand(2, 10);
        // 按开奖记录状态拆分文案，避免开奖前后和准错结果语气错位。
        if ($autoReplyTone === 'hit') {
            $autoReplyShortParts = array(
                '中奖了就是爽，这期真的开心。',
                '开出来那一刻笑出来了，真香。',
                '这期跟上真中了，心里一下热了。',
                '小中一口也舒服，今天心情直接好了。',
                '这波有点上头，看到结果忍不住想喊。',
                '终于赢回来一口，等这一期太值了。',
                '哈哈，这期真给我整激动了。',
                '看到开奖对上那一下，手都想拍桌子。',
                '这期中了，开心得有点坐不住。',
                '赢钱的感觉真不一样，这口喜气舒服。',
                '本来没抱太大希望，结果真中了。',
                '这期一开就对上，心情直接起飞。',
                '跟着看中了，真的有点感动。',
                '这口回得漂亮，赢了' . mt_rand(2, 9) . '千多，今天不白等。',
                '刚看结果就笑了，资料牛，真不是随便吹。',
                '终于回血了，赢回' . mt_rand(1, 3) . '万多，心里一下顺了。',
                '这次中爆来得太及时，终于回本了，开心坏了。',
                '开出来真对上，忍不住想再看下一期。',
                '这把中了就舒服，跟了' . mt_rand(2, 5) . '期总算没白跟。',
                '今晚心情稳了，这期好资料真争气。',
                '刚看到开奖结果那一下，真的有点激动，这期跟上太值了。',
                '连看' . $followIssueTwo . '期，这回总算中回来了。',
                '开出来那一刻真开心，感谢楼主这期方向。',
                '跟了' . $followIssueOne . '期终于中一口，心里真舒服。',
                '总算回了一口，今天心情都不一样了。',
                '没想到真对上了，看到结果的时候笑出来了。',
                '这期中了，前面蹲了' . $followIssueOne . '期算值了。',
                '感谢高手分享，开出来那一刻真的很舒服。',
                '本来只是试着跟一下，结果真中了，太开心了。',
                '这口喜气来得刚好，终于把心态拉回来了。',
                '哇，开出来真中了，刚才心都跳了一下。',
                '这期有点爽，网站靠谱，等开奖等得值了。',
                '哈哈，真香，这次跟着看赢了' . mt_rand(3, 8) . '千。',
                '中了就想出来喊一声，太牛了，真的开心。',
                '这波舒服，连中' . mt_rand(2, 4) . '期的感觉真不一样。',
                '终于等到这一口了，前面输了点这回补回来了，真实可靠。',
                '跟踪了' . mt_rand(3, 9) . '期，今天终于中爆，心里这口气顺了。',
                '赢了几千虽然不算夸张，但这次真的很提气。',
                '这期资料牛，开奖一出来就知道不是瞎碰。',
                '感谢高手，这次直接帮我回血了一大口。',
                '这把回本了，前面熬的几期总算没白熬。',
                '网站靠谱不靠谱，看这期结果就有数了。',
                '今天真像被活佛拉了一把，差点就不跟了。',
                '赢回几万那一刻，整个人都清醒了。',
                '跟着看了' . $followIssueTwo . '期，这次真的服气。',
                '这次不是小开心，是那种终于翻身的开心。',
                '资料准起来是真有劲，开完我都想再追着看。',
                '这期中得太及时，前面紧绷的心一下放下了。',
                '真心话，这站资料这次挺真实可靠。',
                '高手这期太牛了，跟着的人应该都懂。',
                '这一口回来的感觉太明显，今天总算不闷了。',
            );
            $autoReplyOpenParts = array(
                '中奖了',
                '真中了',
                '小中一口',
                '赢了' . mt_rand(2, 9) . '千多',
                '终于回血了',
                '开奖那刻真爽',
                '心情直接起飞',
                '这期中爆了',
                '看到结果就激动',
                '忍不住想喊一声',
                '连中' . mt_rand(2, 4) . '期了',
                '这期小中一口',
                '赢回' . mt_rand(1, 3) . '万多',
                '感谢楼主带路',
                '跟了' . $followIssueTwo . '期终于中',
                '这口等得值了',
                '好资料真的稳',
                '感谢高手',
                '终于回本了',
                '网站挺靠谱',
                '真实可靠',
                '跟踪' . mt_rand(3, 9) . '期终于见效',
                '赢了几千心里踏实',
                '赢回几万真舒服',
                '资料牛得很',
                '高手太牛了',
                '像活佛救了一口',
                '这站资料有点东西',
                '会员真心话是真的',
                '回本这口太及时',
                '这次真服了',
            );
            $autoReplyMiddleParts = array(
                '心里一下热了',
                '开心得有点坐不住',
                '这口等得太值',
                '今天真有喜气',
                '赢了' . mt_rand(2, 9) . '千多心里真踏实',
                '手都有点抖',
                '真想喊一声中了',
                '笑得停不下来',
                '连中' . mt_rand(2, 4) . '期是真的提气',
                '心情直接亮了',
                '心里一下踏实了',
                '真的有点激动',
                '等这一口等挺久了',
                '跟了' . $followIssueThree . '期终于看到回头钱',
                '这次是真开心',
                '这次像会员群里说的真中爆',
                '好资料看得出来',
                '高手确实牛',
                '这回本金拿回来了',
                '网站靠谱不是空话',
                '真心话，真中了',
                '跟踪下来终于有回报',
                '赢了几千也够开心',
                '赢回几万心里都亮了',
                '这期资料是真牛',
                '感谢高手这句话要说',
                '像被活佛点了一下',
                '回本以后人都轻松了',
                '连着看才知道靠谱',
                '会员真实体验就是这样',
                '这次不夸都不行',
            );
            $autoReplyTailParts = array(
                '真香',
                '舒服了',
                '开心坏了',
                '太提气了',
                '今晚稳了',
                '继续开心',
                '赢回来了',
                '连中' . mt_rand(2, 4) . '期',
                '今晚心情都好了',
                '真的感谢',
                '先开心一下',
                '这回舒服了',
                '这把中爆了',
                '开心得很',
                '资料牛',
                '太牛了',
                '感谢高手',
                '终于回本',
                '网站靠谱',
                '赢得舒服',
                '几千到手',
                '几万回来了',
                '真心服',
                '继续跟踪',
                '活佛救场',
                '靠谱得很',
                '好资料',
                '真实可靠',
                '下期还看',
            );
            $autoReplySceneParts = array(
                '开奖一出来真有点忍不住笑，这期跟着看中了。',
                '本来只是随手看，没想到真中，这一下太开心了。',
                '这期对上那一刻真的激动，感觉整个人都精神了。',
                '哈哈，看到结果的时候心里就一句话，这波真香。',
                '这把小中一口也舒服，蹲了' . $followIssueOne . '期总算有回响。',
                '刚才刷新开奖结果，看到对上那一下手都抖了一下。',
                '这期中了以后心情直接拉满，赢了' . mt_rand(2, 9) . '千多没白蹲。',
                '这次真赢钱了，虽然不算太多，但这口气特别顺。',
                '真中了才知道等开奖有多刺激，这期资料太牛了。',
                '这口喜气来得刚好，今天看完结果整个人都顺了。',
                '忍不住回来留一句，这期真的中了，开心。',
                '开出来对上的时候有点上头，差点想直接喊出来。',
                '这次跟着看真有结果，赢回' . mt_rand(1, 4) . '万多那一下挺感动。',
                '这期让我笑出来了，之前半信半疑现在真服了，网站挺靠谱。',
                '看到结果那刻真爽，连中' . mt_rand(2, 4) . '期终于不是白等。',
                '这把回了一口，前面亏的今天补了不少。',
                '这期有中奖的感觉了，评论区必须冒个泡，感谢高手。',
                '刚开出来就对上，开心是真的，激动也是真的。',
                '开奖那一刻我真有点愣住了，前面看的方向居然真对上了。',
                '今天本来没抱太大希望，结果一出来是真的开心，活佛一样救了一口。',
                '说实话，这期中了以后有点感动，跟了' . $followIssueTwo . '期终于等到回头钱。',
                '刚看到结果的时候心里一热，这种对上的感觉太舒服了。',
                '跟了' . $followIssueThree . '期终于看到回响了，这次真的要感谢楼主。',
                '这期结果出来后心情一下好了，终于回血了，有种等到的感觉。',
                '本来只是收藏着看看，没想到开奖后真能对上，挺激动的。',
                '这次不只是准，主要是开出来那一刻真的让人开心，好资料看得见。',
                '终于有一口回来的感觉，今天赢了' . mt_rand(3, 8) . '千这帖看得值。',
                '开完回来补个真心话，这期中了以后确实很感谢，高手太牛了。',
                '哈哈，刚看开奖结果的时候我都愣了一下，这期真对上了。',
                '这次开出来真的有点上头，开心归开心，还是先稳住。',
                '不装了，这期对上我是真高兴，评论区必须留一句。',
                '刚才刷新结果那一下挺刺激的，看到对上就放心了。',
                '这期算是给我整开心了，前面没白蹲，资料牛。',
                '说句接地气的，今天看完结果心情直接好了，真实可靠。',
                '跟踪了' . mt_rand(3, 9) . '期终于等到中爆，这口气真顺。',
                '本来只是想回本，没想到这次赢了几千，心情一下起来了。',
                '这期资料是真的牛，开奖前还犹豫，开完直接服了。',
                '感谢高手这期救了一口，前面亏的今天补回来不少。',
                '赢回几万那一下真的有点懵，手都停在屏幕上了。',
                '说句实在的，这网站资料这次挺靠谱，不是光喊口号。',
                '今天这把像活佛救场，差点放弃，结果真给回来了。',
                '会员真心话说一句，这期跟着看不亏，几千到手。',
                '连看' . $followIssueTwo . '期才懂这个资料的劲，今天总算回本。',
                '这期不是普通对上，是直接把心态拉回来了，太牛了。',
                '开完我第一反应就是资料牛，重点真没白看。',
                '之前还怀疑是不是吹得太满，这次赢回来以后真没话说。',
                '今天算是被高手带了一把，回血的感觉太明显。',
                '这口中了以后人都轻松了，前面压着的心终于放下。',
                '真实可靠这几个字今天能说，开奖结果已经摆在这了。',
                '跟着看的人应该懂，连中' . mt_rand(2, 4) . '期那种兴奋藏不住。',
                '赢了几千先不说多少，主要是这次信心回来了。',
                '这把回本以后我是真开心，感谢高手不多说。',
                '网站靠谱不靠谱，像今天这种结果最直接。',
                '这期中爆以后，下一期我肯定还要继续跟踪。',
            );
        } elseif ($autoReplyTone === 'miss') {
            $autoReplyShortParts = array(
                '开奖后对了一下，这期没对上。',
                '这次结果不理想，先复盘下期再看。',
                '这期方向偏了，别急着追。',
                '开奖出来没对上，理性看待。',
                '这期先当参考，下期继续观察。',
                '结果已经出来了，这次确实错了。',
                '这次没准，后面还是要看长期。',
                '先别冲动，错了就复盘。',
                '开奖后看清楚了，这期不能硬说准。',
                '这帖这次没对上，留着看后面表现。',
                '刚对了开奖记录，这期不搭，别硬圆。',
                '我以为会贴近一点，结果出来还是差了。',
                '这次没跟上开奖方向，先看下期怎么调整。',
                '说实话这期看错了，评论区别只报喜。',
                '开完以后就明白了，这期不能算稳。',
                '我先停一停，下期看有没有新思路。',
                '这次当成一次参考就好，别越错越急。',
                '这期没有对上重点，后面再观察。',
                '结果摆出来了，错就是错，复盘比硬夸有用。',
                '我看完开奖再回来，这次确实不理想。',
                '唉，这期没贴上，先别上头。',
                '这把有点可惜，先缓缓再看。',
                '没中就先认，别硬追了。',
                '今天这期差点意思，下期再说。',
                '结果不配合，先稳住心态。',
            );
            $autoReplyOpenParts = array(
                '这期没对上',
                '结果不理想',
                '这次方向偏了',
                '开奖后看错了',
                '先复盘一下',
                '下期再观察',
                '这期不能硬追',
                '先把节奏稳住',
                '连看' . $followIssueThree . '期再判断',
                '理性看待这次结果',
            );
            $autoReplyMiddleParts = array(
                '资料也要看长期',
                '错了就先停一停',
                '别因为一帖乱了节奏',
                '复盘比硬追更重要',
                '开奖后再判断比较稳',
                '这次结果已经说明问题',
                '后面继续看准确率',
                '先不要盲目加码',
                '看资料也要分期数',
                '下期再看有没有调整',
            );
            $autoReplyTailParts = array(
                '别乱冲',
                '下期再说',
                '先观察',
                '稳一点',
                '继续看后面',
                '别急着追',
                '复盘一下',
                '理性一点',
            );
            $autoReplySceneParts = array(
                '开奖后回来对了一下，这期确实没对上，先复盘。',
                '这次结果不搭，别急着追，等下期再看调整。',
                '错了就认，资料还是要结合后面几期一起看。',
                '本来想等开奖验证，结果出来后看这期方向偏了。',
                '这期没准，先留个记录，后面看能不能调整回来。',
                '开奖记录已经出来了，这帖这次不算准，理性一点。',
                '评论区别只报好不报坏，这期没对上就先停停。',
                '看资料不能只看热闹，开错了就要回头复盘。',
                '刚才把开奖记录又核了一遍，这期和帖子方向没接上。',
                '我不是来泼冷水，单说这期结果，确实没有对上。',
                '这种时候别硬说稳，错了就先记下来，下期再看。',
                '本来想等开完再评价，现在看这期只能算偏了。',
                '资料有时候会走偏，这期先别急着继续追。',
                '看错一两期正常，关键是后面能不能把方向拉回来。',
                '这期我不跟着夸，结果已经说明了，先复盘。',
                '评论区还是要真实点，这次没准就没准。',
                '我会再看几期，单独这一期确实不太行。',
                '开完再回来讲比较公平，这次帖子和结果不搭。',
                '唉，开出来没对上，先别急，越急越容易乱。',
                '这期看完结果有点失望，不过也正常，下期再观察。',
                '没中就先停一下，别一股脑往前冲。',
                '这把确实不舒服，还是先复盘一下更实际。',
                '结果出来就别嘴硬了，这期先当踩个刹车。',
            );
        } else {
            $autoReplyShortParts = array(
                '先收藏一下，开奖后回来对照。',
                '这期先看资料方向，等开奖验证。',
                '开前先留个记号，别急着下结论。',
                '资料先收好，开奖后再看准不准。',
                '理性参考，结果出来再说。',
                '看完先观察，别乱追。',
                '这期内容先留意，等开奖见真章。',
                '先把重点记下来，晚点再回来对。',
                '这帖先不吹，开奖后看结果。',
                '连续看' . $followIssueOne . '期再判断更稳。',
                '我先放个记号，开完再回来翻。',
                '这期资料有点意思，但还是等结果说话。',
                '先不急着信，也不急着否，开奖后自然清楚。',
                '看完感觉有线索，等晚上再对。',
                '我先看一遍，后面开了再回来补评论。',
                '这种帖先别争，结果出来最直接。',
                '先收藏，免得开奖后找不到原帖。',
                '我比较谨慎，先看这期能不能对上。',
                '资料先留着，开前说太满没意义。',
                '等开奖吧，现在说准不准都太早。',
                '先蹲一个结果，开了再说。',
                '我先占个楼，晚点回来对。',
                '这期先不冲，看看开奖怎么走。',
                '先别急着喊准，结果出来最实在。',
                '开前先看看热闹，开后再讲真话。',
            );
            $autoReplyOpenParts = array(
                '资料先收下',
                '开奖后回来对照',
                '先看方向',
                '理性参考',
                '这期先观察',
                '别急着下结论',
                '重点先记住',
                '开前先稳住',
                '连看' . $followIssueTwo . '期再判断',
                '等结果出来再说',
            );
            $autoReplyMiddleParts = array(
                '等结果出来再验证',
                '先把重点记下来',
                '看清楚再行动',
                '稳一点更合适',
                '开奖前先别吹',
                '资料方向可以留意',
                '不要一上来就冲',
                '先按自己的节奏来',
                '晚点回来对开奖记录',
                '这期先当参考',
            );
            $autoReplyTailParts = array(
                '开奖后再回来看',
                '先收藏不迷路',
                '祝大家都顺利',
                '别乱冲，稳住',
                '结果出来再说',
                '先留个记录',
                '晚点再看',
                '理性一点',
            );
            $autoReplySceneParts = array(
                '新人路过，先收藏一下，开奖后回来对照。',
                '评论区留个脚印，等开奖记录出来再看准不准。',
                '这期先别急着吹，资料收好，开完再对。',
                '看帖不急，先把重点记下来，晚点再回来验证。',
                '楼主这个思路比较直接，先按资料方向观察。',
                '朋友推荐过来的，先看看这期资料成色。',
                '这类资料我一般会连看几期，准不准很快就知道。',
                '今天心态放平，资料先看，结果交给开奖。',
                '开前先留个记号，结果出来再说话。',
                '这期资料先压箱底，开奖后再翻出来对。',
                '我先不站队，帖子内容看着有点线索，等开奖验证。',
                '刚刷到这帖，先记一下，开完回来看看是不是有料。',
                '这类内容不能光看标题，还是要等结果出来再评价。',
                '先把话放这，开奖前别吹太狠，开奖后自然见分晓。',
                '我只看长期，不看一时热闹，这期先作为观察样本。',
                '楼里先别吵，资料有没有用，开完一对就知道。',
                '这期我先轻轻看一眼，不急着跟，也不急着否。',
                '之前错过几次，这回先留个位置，等结果出来再翻。',
                '看资料最怕冲动，先冷静点，晚上再回来对。',
                '这帖我先放收藏夹，等开奖后再决定后面还看不看。',
                '先蹲在这里，开完回来看看有没有惊喜。',
                '现在说啥都早，等开奖出来再看楼主这期灵不灵。',
                '我先不跟风，先收藏，晚点开奖再回来对一下。',
                '这期先轻轻看，别一上来就把话说太满。',
                '先留个脚印，开完要是对上再回来夸。',
            );
        }
        $autoReplyEmojiParts = array();
        if ($autoReplyTone === 'hit') {
            $autoReplyEmojiParts = array('😄', '🎉', '🧧', '👍', '🤩', '🔥');
        } elseif ($autoReplyTone === 'miss') {
            $autoReplyEmojiParts = array('😅', '🙂', '🤝', '📌', '💪');
        } else {
            $autoReplyEmojiParts = array('👀', '📌', '🙂', '🤔', '⏳');
        }
        $normalizeAutoReplyContentKey = static function ($content) {
            $content = trim((string) $content);
            if ($content === '') {
                return '';
            }

            $normalized = preg_replace('/[\x{200D}\x{FE0F}\p{So}\p{Sk}\p{Cs}]+/u', '', $content);
            if (!is_string($normalized)) {
                $normalized = $content;
            }
            $normalized = preg_replace('/\s+/u', ' ', trim($normalized));
            if (!is_string($normalized)) {
                $normalized = trim($content);
            }

            return $normalized;
        };
        $preOpenAutoReplyParts = array(
            'pending' => array(
                'short' => array(
                    '当前这期还没开奖，先看资料方向。',
                    '这期先别急着喊准，等开奖后再说。',
                    '现在还没到开奖时间，先收藏等结果。',
                    '开前先稳住，结果出来再回来对。',
                    '当前期数先观察，别太早下结论。',
                    '今天这期还在等开奖，先把重点记一下。',
                ),
                'open' => array(
                    '当前这期还没开奖',
                    '开前先稳住',
                    '先等结果',
                    '资料先收下',
                    '这期先观察',
                    '先别急着下结论',
                ),
                'middle' => array(
                    '等开奖后再验证',
                    '先把重点记下来',
                    '现在说结果还太早',
                    '开奖前先按节奏看',
                    '晚点再回来对资料',
                    '别把话说太满',
                ),
                'tail' => array(
                    '开奖后再看',
                    '先收藏一下',
                    '稳一点',
                    '晚点回来对',
                    '先留个记录',
                ),
                'scene' => array(
                    '当前这期还没开奖，我先收藏，等结果出来再回来对。',
                    '开前先别急着吹，资料方向先记下，晚点看结果。',
                    '现在说准不准都早，等开奖后再看这帖有没有东西。',
                    '这期先当参考，开奖前还是稳一点比较好。',
                    '先蹲一个结果，等开奖记录出来再回来讲真话。',
                ),
                'emoji' => array('👀', '📌', '🙂', '🤔', '⏳'),
            ),
            'history_hit' => array(
                'short' => array(
                    '先说前一期，跟着中了，赢了' . mt_rand(2, 9) . '千多。',
                    '先看前面那口，已经回血了，资料真牛。',
                    '昨天那单赢回' . mt_rand(1, 3) . '万多，挺感谢高手。',
                    '上一口已经回本了，网站看着靠谱。',
                    '跟踪了' . $followIssueOne . '期，前一期总算中爆，心里踏实。',
                    '先不说当前这期，昨天那把真香，赢了几千心情都不一样。',
                    '前一期好资料已经验证过了，真实可靠。',
                    '昨天那口像活佛救场一样，终于把前面亏的补回来点。',
                    '前面那把赢了几千，开心是真的。',
                    '先说前面那口，跟踪' . mt_rand(3, 9) . '期总算回本。',
                    '前一期资料牛，开完以后不少人都说太牛了。',
                    '昨天赢回几万那种开心是真的。',
                    '前面那口感谢高手，回血回血，心态都拉回来了。',
                    '昨天那次网站靠谱感挺明显。',
                    '前一期像活佛救场，快没信心的时候给我拉回来。',
                    '前面那单真实可靠，赢了几千不是评论区乱喊。',
                    '前面好资料已经看出来了。',
                    '跟踪到前一期才知道，这资料准起来是真舒服。',
                    '昨天那把终于回本，后面继续稳着看。',
                    '前一期中爆以后，我对这帖是真多了一点信心。',
                ),
                'open' => array(
                    '先看前面那口',
                    '前一期已经中爆',
                    '昨天那单赢了' . mt_rand(2, 9) . '千多',
                    '前面赢回' . mt_rand(1, 3) . '万多',
                    '跟踪' . $followIssueTwo . '期终于回本',
                    '好资料先记住',
                    '感谢高手带路',
                    '网站这次挺靠谱',
                    '前面那口先回血',
                    '前面赢了几千',
                    '昨天赢回几万',
                    '前一期资料牛',
                    '像活佛救场',
                    '这口真心话先放这',
                    '会员跟踪有结果',
                    '前一把太牛了',
                    '先看前面中爆',
                    '前面回本了',
                ),
                'middle' => array(
                    '先按前面那口看',
                    '先按那次赢钱劲看',
                    '昨天那口确实回血了',
                    '资料牛不牛开完就知道',
                    '后面继续看结果',
                    '前面那把真中了',
                    '会员真心话不会骗人',
                    '高手思路有东西',
                    '赢了几千不是重点，回本才舒服',
                    '赢回几万那一下很提气',
                    '资料牛不牛要看开奖记录',
                    '网站靠谱要看长期结果',
                    '感谢高手不是客套话',
                    '跟踪了几期才有这种感觉',
                    '前面中爆以后心态稳多了',
                    '真实可靠看前面那口',
                    '这口回血来得很及时',
                    '先别硬套当前结果',
                ),
                'tail' => array(
                    '后面继续看',
                    '先留句真心话',
                    '继续跟踪',
                    '等开奖后再说',
                    '前一期够牛',
                    '这资料可以',
                    '网站靠谱',
                    '终于回本',
                    '赢得踏实',
                    '感谢高手',
                    '资料牛',
                    '太牛了',
                    '真实可靠',
                    '先别乱套当前',
                    '后面看结果',
                    '前面那口先记住',
                    '继续看下去',
                    '回血就稳了',
                ),
                'scene' => array(
                    '先说前一期，跟着中了，赢了' . mt_rand(2, 9) . '千多是真的开心。',
                    '昨天那口已经回血，资料牛不牛自己能看出来。',
                    '前面跟踪了' . $followIssueThree . '期终于回本，这点可以先记一下。',
                    '昨天那把赢回' . mt_rand(1, 3) . '万多，真心感谢高手。',
                    '前一期好资料已经开出效果，网站这次确实靠谱。',
                    '昨天那口像活佛救场一样，前面亏的总算补回来点。',
                    '先看前面那口，真中爆，会员说的赢了几千不是空话。',
                    '前面那单真实可靠，跟着看的人心里有数。',
                    '前一期太牛了，资料一对就知道不是乱说。',
                    '先说前面那把，跟踪' . mt_rand(3, 9) . '期终于中了，回本那一下真舒服。',
                    '前面那单赢了几千，会员真心话是真有的。',
                    '前一期赢回几万以后，我对这资料是真服，后面继续看。',
                    '昨天那口回血让我心态稳了很多。',
                    '前面那把资料牛，开完一对就知道，不是靠评论区硬吹。',
                    '前一期感谢高手带了一口，这种回本感觉太明显。',
                    '前一期像活佛救场，差点不跟结果救回来了。',
                    '真心话先留一句，网站这次挺靠谱，前面赢了几千是真的。',
                    '前一把真实可靠，跟着看的人应该懂。',
                    '前一期中爆以后心里有底了，后面继续看结果说话。',
                    '昨天那次太牛了，资料一开就对上。',
                    '前面跟踪下来终于回血，这点挺实在。',
                    '前一期好资料已经开出效果，后面不乱夸，等结果说话。',
                    '前面那口赢回来了，我已经愿意继续看。',
                    '先按前一期说，高手这次确实有东西。',
                    '昨天那把回本以后整个人轻松不少，后面看结果再评价。',
                    '前面那单不是只对上，是真让人赢回一口，资料牛。',
                    '前一期真实可靠，赢了几千这事不算夸张。',
                    '前一期看完就觉得网站靠谱了，后面还是看结果说话。',
                    '先把真心话放这，跟踪久了才知道哪家资料稳。',
                ),
                'emoji' => array('🙂', '👍', '📌', '🎉'),
            ),
            'history_miss' => array(
                'short' => array(
                    '当前这期还没开，先看昨天那期不算理想。',
                    '今天这期先等结果，上一期没对上就先稳点。',
                    '当前还不能说结果，昨天那期先当提醒。',
                    '这期未开奖前别急，前一期没中就更要稳。',
                    '先等当前开奖，昨天那次不顺就别乱冲。',
                ),
                'open' => array(
                    '当前这期先等开奖',
                    '先看昨天那期',
                    '上一期没对上',
                    '前一期先当提醒',
                    '今天这期先稳住',
                ),
                'middle' => array(
                    '现在不能说当前结果',
                    '昨天结果不算理想',
                    '先别急着追',
                    '当前这期等开完再判断',
                    '看清楚再动更稳',
                ),
                'tail' => array(
                    '晚点再看当前这期',
                    '先稳住',
                    '别乱冲',
                    '等开奖后再说',
                    '先留个记录',
                ),
                'scene' => array(
                    '当前这期还没开奖，不能提前说结果，昨天那期没对上就先稳住。',
                    '今天这期先等开，上一期不理想，后面还是要看结果说话。',
                    '现在说当前准错都早，前一期没中，先别一股脑往前冲。',
                    '当前结果还没出来，昨天那次算提醒，今晚看完再判断。',
                    '这期先别急，前一期没对上，等当前开奖记录出来再说。',
                ),
                'emoji' => array('😅', '🙂', '🤝', '📌'),
            ),
        );
        $autoReplyGenerated = array();

        $this->db()->beginTransaction();
        try {
            if (isset($autoReplyRuntimeCache['auto_reply_role_id'])) {
                $autoReplyRoleId = (int) $autoReplyRuntimeCache['auto_reply_role_id'];
            } else {
                $autoReplyRole = $this->db()->fetch(
                    'SELECT id
                     FROM roles
                     WHERE role_key = :role_key
                     LIMIT 1',
                    array('role_key' => 'auto_comment')
                );
                if ($autoReplyRole) {
                    $autoReplyRoleId = (int) $autoReplyRole['id'];
                } else {
                    $autoReplyRoleId = $this->db()->insertGetId(
                        'INSERT INTO roles (role_key, role_name, created_at, updated_at)
                         VALUES (:role_key, :role_name, :created_at, :updated_at)',
                        array(
                            'role_key' => 'auto_comment',
                            'role_name' => '自动评论账号',
                            'created_at' => $now,
                            'updated_at' => $now,
                        )
                    );
                }
                $autoReplyRuntimeCache['auto_reply_role_id'] = $autoReplyRoleId;
            }

            $existingAutoRows = $this->db()->fetchAll(
                'SELECT replies.id
                 FROM replies
                 INNER JOIN users ON users.id = replies.user_id
                 INNER JOIN roles ON roles.id = users.role_id
                 WHERE replies.post_id = :post_id
                   AND replies.status = :reply_status
                   AND roles.role_key = :role_key
                 ORDER BY replies.created_at ASC, replies.id ASC',
                array(
                    'post_id' => $postId,
                    'reply_status' => 'published',
                    'role_key' => 'auto_comment',
                )
            );
            $existingAutoCount = count($existingAutoRows);
            if ($existingAutoCount >= count($slotTimestamps)) {
                $this->db()->commit();

                return 0;
            }

            $pendingSlotTimestamps = array_slice($slotTimestamps, $existingAutoCount);
            $insertCount = 0;
            if (isset($autoReplyRuntimeCache['used_contents']) && is_array($autoReplyRuntimeCache['used_contents'])) {
                $usedContents = $autoReplyRuntimeCache['used_contents'];
            } else {
                $usedContentRows = $this->db()->fetchAll(
                    'SELECT DISTINCT replies.content
                     FROM replies
                     INNER JOIN posts ON posts.id = replies.post_id
                     WHERE replies.status = :reply_status
                       AND posts.status <> :deleted_status
                       AND posts.deleted_at IS NULL
                       AND replies.content <> \'\'',
                    array(
                        'reply_status' => 'published',
                        'deleted_status' => 'deleted',
                    )
                );
                $usedContents = array();
                foreach ($usedContentRows as $usedContentRow) {
                    $usedContent = trim((string) ($usedContentRow['content'] ?? ''));
                    $usedContentKey = $normalizeAutoReplyContentKey($usedContent);
                    if ($usedContentKey !== '') {
                        $usedContents[$usedContentKey] = true;
                    }
                }
                $autoReplyRuntimeCache['used_contents'] = $usedContents;
            }

            $usedUserRows = $this->db()->fetchAll(
                'SELECT DISTINCT user_id
                 FROM replies
                 WHERE post_id = :post_id
                   AND user_id > 0',
                array('post_id' => $postId)
            );
            $usedUserIds = array();
            foreach ($usedUserRows as $usedUserRow) {
                $usedUserId = (int) ($usedUserRow['user_id'] ?? 0);
                if ($usedUserId > 0) {
                    $usedUserIds[$usedUserId] = true;
                }
            }

            if (isset($autoReplyRuntimeCache['auto_users']) && is_array($autoReplyRuntimeCache['auto_users'])) {
                $autoUsers = $autoReplyRuntimeCache['auto_users'];
            } else {
                $autoUserRows = $this->db()->fetchAll(
                    'SELECT users.id,
                            users.username,
                            COUNT(active_posts.id) AS active_reply_count
                     FROM users
                     INNER JOIN roles ON roles.id = users.role_id
                     LEFT JOIN replies active_replies ON active_replies.user_id = users.id
                        AND active_replies.status = :reply_status
                     LEFT JOIN posts active_posts ON active_posts.id = active_replies.post_id
                        AND active_posts.status <> :deleted_status
                        AND active_posts.deleted_at IS NULL
                     WHERE roles.role_key = :role_key
                       AND users.status = :user_status
                     GROUP BY users.id, users.username
                     ORDER BY users.id ASC
                     LIMIT 500',
                    array(
                        'reply_status' => 'published',
                        'deleted_status' => 'deleted',
                        'role_key' => 'auto_comment',
                        'user_status' => 'disabled',
                    )
                );
                $autoUsers = array();
                foreach ($autoUserRows as $autoUserRow) {
                    $autoUsername = (string) ($autoUserRow['username'] ?? '');
                    $autoUsers[] = array(
                        'id' => (int) ($autoUserRow['id'] ?? 0),
                        'username' => $autoUsername,
                        'active_reply_count' => (int) ($autoUserRow['active_reply_count'] ?? 0),
                        'reply_limit' => 2 + ((int) sprintf('%u', crc32($autoUsername)) % 2),
                    );
                }
                $autoReplyRuntimeCache['auto_users'] = $autoUsers;
            }

            foreach ($pendingSlotTimestamps as $index => $slotTimestamp) {
                $userId = 0;
                $username = '';
                if ($autoUsers !== array()) {
                    $autoUserIndexes = array_keys($autoUsers);
                    shuffle($autoUserIndexes);
                    foreach ($autoUserIndexes as $autoUserIndex) {
                        $autoUser = $autoUsers[$autoUserIndex];
                        $candidateUserId = (int) ($autoUser['id'] ?? 0);
                        if ($candidateUserId <= 0 || isset($usedUserIds[$candidateUserId])) {
                            continue;
                        }
                        if ((int) ($autoUser['active_reply_count'] ?? 0) >= (int) ($autoUser['reply_limit'] ?? 2)) {
                            continue;
                        }

                        $userId = $candidateUserId;
                        $username = (string) ($autoUser['username'] ?? '');
                        $autoUsers[$autoUserIndex]['active_reply_count'] = (int) ($autoUser['active_reply_count'] ?? 0) + 1;
                        $autoReplyRuntimeCache['auto_users'][$autoUserIndex]['active_reply_count'] = $autoUsers[$autoUserIndex]['active_reply_count'];
                        break;
                    }
                }

                if ($userId <= 0) {
                    $username = '';
                    for ($attempt = 0; $attempt < 50; $attempt++) {
                        $usernameLength = mt_rand(6, 13);
                        $usernameMode = mt_rand(1, 3);
                        if ($usernameMode === 1) {
                            $letterLength = mt_rand(2, min(4, (int) floor(($usernameLength - 1) / 2)));
                            $numberLength = $usernameLength - $letterLength;
                            $candidateUsername = $buildUsernamePart($usernameLetters, $usernameLetterMaxIndex, $letterLength)
                                . $buildUsernamePart($usernameNumbers, $usernameNumberMaxIndex, $numberLength);
                        } elseif ($usernameMode === 2) {
                            $letterLength = mt_rand(2, min(4, (int) floor(($usernameLength - 1) / 2)));
                            $numberLength = $usernameLength - $letterLength;
                            $candidateUsername = $buildUsernamePart($usernameNumbers, $usernameNumberMaxIndex, $numberLength)
                                . $buildUsernamePart($usernameLetters, $usernameLetterMaxIndex, $letterLength);
                        } else {
                            $letterLength = mt_rand(2, min(4, (int) floor(($usernameLength - 1) / 2)));
                            $numberLength = $usernameLength - $letterLength;
                            $leftLetterLength = mt_rand(1, $letterLength - 1);
                            $rightLetterLength = $letterLength - $leftLetterLength;
                            $candidateUsername = $buildUsernamePart($usernameLetters, $usernameLetterMaxIndex, $leftLetterLength)
                                . $buildUsernamePart($usernameNumbers, $usernameNumberMaxIndex, $numberLength)
                                . $buildUsernamePart($usernameLetters, $usernameLetterMaxIndex, $rightLetterLength);
                        }

                        $existingUser = $this->db()->fetch(
                            'SELECT id
                             FROM users
                             WHERE username = :username
                             LIMIT 1',
                            array('username' => $candidateUsername)
                        );
                        if (!$existingUser) {
                            $username = $candidateUsername;
                            break;
                        }
                    }

                    if ($username === '') {
                        throw new RuntimeException('自动评论账号生成失败。');
                    }

                    static $autoReplyDisabledPasswordHash = null;
                    if ($autoReplyDisabledPasswordHash === null) {
                        try {
                            $passwordSource = bin2hex(random_bytes(32));
                        } catch (\Throwable $exception) {
                            $passwordSource = uniqid('auto-reply-disabled-', true) . '|' . mt_rand();
                        }
                        $autoReplyDisabledPasswordHash = password_hash(hash('sha256', $passwordSource), PASSWORD_DEFAULT);
                    }

                    $userId = $this->db()->insertGetId(
                        'INSERT INTO users (role_id, username, password_hash, score, status, created_at, updated_at)
                         VALUES (:role_id, :username, :password_hash, :score, :status, :created_at, :updated_at)',
                        array(
                            'role_id' => $autoReplyRoleId,
                            'username' => $username,
                            'password_hash' => $autoReplyDisabledPasswordHash,
                            'score' => 0,
                            'status' => 'disabled',
                            'created_at' => $now,
                            'updated_at' => $now,
                        )
                    );
                    $autoUsers[] = array(
                        'id' => (int) $userId,
                        'username' => $username,
                        'active_reply_count' => 1,
                        'reply_limit' => 2 + ((int) sprintf('%u', crc32($username)) % 2),
                    );
                    $autoReplyRuntimeCache['auto_users'] = $autoUsers;
                }
                $usedUserIds[(int) $userId] = true;

                $slotAutoReplyShortParts = $autoReplyShortParts;
                $slotAutoReplyOpenParts = $autoReplyOpenParts;
                $slotAutoReplyMiddleParts = $autoReplyMiddleParts;
                $slotAutoReplyTailParts = $autoReplyTailParts;
                $slotAutoReplySceneParts = $autoReplySceneParts;
                $slotAutoReplyEmojiParts = $autoReplyEmojiParts;
                if (
                    !$currentIssueHasDraw
                    && $currentIssueOpenTimestamp !== null
                    && (int) $slotTimestamp < $currentIssueOpenTimestamp
                ) {
                    $preOpenTone = 'pending';
                    if ($historicalAutoReplyTone === 'hit') {
                        $preOpenTone = 'history_hit';
                    } elseif ($historicalAutoReplyTone === 'miss') {
                        $preOpenTone = 'history_miss';
                    }
                    $preOpenParts = (array) ($preOpenAutoReplyParts[$preOpenTone] ?? $preOpenAutoReplyParts['pending']);
                    $slotAutoReplyShortParts = (array) ($preOpenParts['short'] ?? $slotAutoReplyShortParts);
                    $slotAutoReplyOpenParts = (array) ($preOpenParts['open'] ?? $slotAutoReplyOpenParts);
                    $slotAutoReplyMiddleParts = (array) ($preOpenParts['middle'] ?? $slotAutoReplyMiddleParts);
                    $slotAutoReplyTailParts = (array) ($preOpenParts['tail'] ?? $slotAutoReplyTailParts);
                    $slotAutoReplySceneParts = (array) ($preOpenParts['scene'] ?? $slotAutoReplySceneParts);
                    $slotAutoReplyEmojiParts = (array) ($preOpenParts['emoji'] ?? $slotAutoReplyEmojiParts);
                }

                $content = '';
                for ($contentAttempt = 0; $contentAttempt < 120; $contentAttempt++) {
                    $replyMode = ($index + $contentAttempt + mt_rand(0, 2)) % 3;
                    if ($replyMode === 0) {
                        $candidateContent = $slotAutoReplyShortParts[mt_rand(0, count($slotAutoReplyShortParts) - 1)];
                    } elseif ($replyMode === 1) {
                        $candidateContent = $slotAutoReplySceneParts[mt_rand(0, count($slotAutoReplySceneParts) - 1)];
                    } else {
                        $contentParts = array(
                            $slotAutoReplyOpenParts[mt_rand(0, count($slotAutoReplyOpenParts) - 1)],
                            $slotAutoReplyMiddleParts[mt_rand(0, count($slotAutoReplyMiddleParts) - 1)],
                        );
                        if (mt_rand(1, 3) !== 1) {
                            $contentParts[] = $slotAutoReplyTailParts[mt_rand(0, count($slotAutoReplyTailParts) - 1)];
                        }
                        $candidateContent = implode('，', $contentParts) . '。';
                    }
                    if ($slotAutoReplyEmojiParts !== array() && mt_rand(1, 100) <= 70) {
                        $autoReplyEmoji = $slotAutoReplyEmojiParts[mt_rand(0, count($slotAutoReplyEmojiParts) - 1)];
                        if (mt_rand(0, 1) === 1) {
                            $candidateContent = $autoReplyEmoji . ' ' . $candidateContent;
                        } else {
                            $candidateContent .= ' ' . $autoReplyEmoji;
                        }
                    }

                    $candidateContentKey = $normalizeAutoReplyContentKey($candidateContent);
                    if (
                        $candidateContentKey !== ''
                        && !isset($usedContents[$candidateContentKey])
                        && !isset($autoReplyGenerated[$candidateContentKey])
                    ) {
                        $content = $candidateContent;
                        $usedContents[$candidateContentKey] = true;
                        $autoReplyRuntimeCache['used_contents'][$candidateContentKey] = true;
                        $autoReplyGenerated[$candidateContentKey] = true;
                        break;
                    }
                }

                if ($content === '') {
                    continue;
                }

                $createdAt = date('Y-m-d H:i:s', (int) $slotTimestamp);

                $this->db()->execute(
                    'INSERT INTO replies (post_id, parent_id, user_id, content, status, created_at)
                     VALUES (:post_id, :parent_id, :user_id, :content, :status, :created_at)',
                    array(
                        'post_id' => $postId,
                        'parent_id' => null,
                        'user_id' => $userId,
                        'content' => $content,
                        'status' => 'published',
                        'created_at' => $createdAt,
                    )
                );
                $insertCount++;
            }

            if ($insertCount > 0) {
                $this->db()->execute(
                    'UPDATE posts
                     SET reply_count = reply_count + :reply_count,
                         updated_at = :updated_at
                     WHERE id = :id',
                    array(
                        'reply_count' => $insertCount,
                        'updated_at' => $this->now(),
                        'id' => $postId,
                    )
                );
            }

            $this->db()->commit();
        } catch (\Exception $exception) {
            $this->db()->rollBack();
            throw $exception;
        }

        return $insertCount;
    }

    protected function normalizeReplyParentId($postId, $parentId)
    {
        if ($parentId <= 0) {
            return 0;
        }

        $parent = $this->db()->fetch(
            'SELECT id, parent_id
             FROM replies
             WHERE id = :id
               AND post_id = :post_id
               AND status = :status
             LIMIT 1',
            array(
                'id' => (int) $parentId,
                'post_id' => (int) $postId,
                'status' => 'published',
            )
        );

        if (!$parent) {
            throw new RuntimeException('要回复的评论不存在。');
        }

        $rootParentId = (int) ($parent['parent_id'] ?? 0);

        return $rootParentId > 0 ? $rootParentId : (int) $parent['id'];
    }

    public function buyPost($postId, $user)
    {
        $postId = (int) $postId;
        $userId = (int) ($user['id'] ?? 0);
        if ($postId <= 0 || $userId <= 0) {
            throw new RuntimeException('请先登录后再购买。');
        }

        $post = $this->findPost($postId);
        if (!$post) {
            throw new RuntimeException('帖子不存在。');
        }

        if ((int) $post['price'] <= 0) {
            throw new RuntimeException('该帖子无需购买。');
        }

        if ($this->salePostHasOpenedDraw($post)) {
            throw new RuntimeException('该帖子已开奖公开，无需购买。');
        }

        if ($this->hasPurchased($postId, $user['id'])) {
            throw new RuntimeException('您已经购买过该帖子。');
        }

        $price = (int) $post['price'];
        $now = $this->now();
        $this->db()->beginTransaction();
        try {
            $lockedUser = $this->db()->fetch(
                'SELECT id, score FROM users WHERE id = :id FOR UPDATE',
                array('id' => $userId)
            );
            if (!$lockedUser) {
                throw new RuntimeException('会员账号不存在，请重新登录。');
            }

            $existingPurchase = $this->db()->fetch(
                'SELECT id, created_at
                 FROM purchases
                 WHERE post_id = :post_id
                   AND user_id = :user_id
                 LIMIT 1
                 FOR UPDATE',
                array(
                    'post_id' => $postId,
                    'user_id' => $userId,
                )
            );
            if ($existingPurchase) {
                $window = $this->salePostPurchaseWindowForPost($post);
                try {
                    $purchaseAt = new \DateTimeImmutable((string) ($existingPurchase['created_at'] ?? ''));
                } catch (\Exception $exception) {
                    $purchaseAt = null;
                }

                if ($purchaseAt && $purchaseAt >= $window['start'] && $purchaseAt < $window['end']) {
                    throw new RuntimeException('您已经购买过该帖子。');
                }
            }

            if ((int) ($lockedUser['score'] ?? 0) < $price) {
                throw new RuntimeException('积分不足，请先充值后再购买。');
            }

            $this->db()->execute('INSERT INTO purchases (post_id, user_id, price, created_at) VALUES (:post_id, :user_id, :price, :created_at) ON DUPLICATE KEY UPDATE price = :update_price, created_at = :update_created_at', array(
                'post_id' => $postId,
                'user_id' => $userId,
                'price' => $price,
                'created_at' => $now,
                'update_price' => $price,
                'update_created_at' => $now,
            ));

            $this->db()->execute('UPDATE users SET score = score - :price, updated_at = :updated_at WHERE id = :id', array(
                'price' => $price,
                'updated_at' => $now,
                'id' => $userId,
            ));

            $this->db()->execute('UPDATE posts SET purchase_count = purchase_count + 1, updated_at = :updated_at WHERE id = :id', array(
                'updated_at' => $now,
                'id' => $postId,
            ));

            $this->db()->commit();
        } catch (\Throwable $exception) {
            $this->db()->rollBack();
            throw $exception;
        }

        return true;
    }

    public function incrementView($postId)
    {
        $this->db()->execute('UPDATE posts SET view_count = view_count + 1 WHERE id = :id', array(
            'id' => $postId,
        ));
    }

    public function registerRealView($postId, array $user = array())
    {
        $postId = (int) $postId;

        if ($postId <= 0) {
            return false;
        }

        if (!$this->tableExists('post_unique_views') || !$this->tableExists('post_view_display_events')) {
            return false;
        }

        $identity = $this->resolvePostViewIdentity($user);

        if ($identity === array()) {
            return false;
        }

        $this->db()->beginTransaction();

        try {
            $existingView = $this->findUniquePostView($postId, (string) $identity['identity_key']);
            if ($existingView) {
                $this->touchUniquePostView((int) $existingView['id'], $identity);
                $this->db()->commit();

                return true;
            }

            if ((string) $identity['identity_type'] === 'user' && (string) ($identity['guest_identity_key'] ?? '') !== '') {
                $guestView = $this->findUniquePostView($postId, (string) $identity['guest_identity_key']);
                if ($guestView && (int) ($guestView['user_id'] ?? 0) === 0) {
                    $this->db()->execute(
                        'UPDATE post_unique_views
                         SET user_id = :user_id,
                             identity_type = :identity_type,
                             identity_key = :identity_key,
                             ip_address = :ip_address,
                             user_agent = :user_agent,
                             updated_at = :updated_at
                         WHERE id = :id',
                        array(
                            'user_id' => $identity['user_id'],
                            'identity_type' => $identity['identity_type'],
                            'identity_key' => $identity['identity_key'],
                            'ip_address' => $identity['ip_address'],
                            'user_agent' => $identity['user_agent'],
                            'updated_at' => $this->now(),
                            'id' => (int) $guestView['id'],
                        )
                    );
                    $this->db()->commit();

                    return true;
                }
            }

            $createdAt = $this->now();
            $uniqueViewId = $this->db()->insertGetId(
                'INSERT INTO post_unique_views (post_id, user_id, identity_type, identity_key, ip_address, user_agent, viewed_on, created_at, updated_at)
                 VALUES (:post_id, :user_id, :identity_type, :identity_key, :ip_address, :user_agent, :viewed_on, :created_at, :updated_at)',
                array(
                    'post_id' => $postId,
                    'user_id' => $identity['user_id'],
                    'identity_type' => $identity['identity_type'],
                    'identity_key' => $identity['identity_key'],
                    'ip_address' => $identity['ip_address'],
                    'user_agent' => $identity['user_agent'],
                    'viewed_on' => date('Y-m-d'),
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                )
            );

            $this->seedPostDisplayViewEvents($postId, $uniqueViewId, $createdAt);
            $this->db()->commit();
        } catch (\Throwable $exception) {
            $this->db()->rollBack();

            $existingView = $this->findUniquePostView($postId, (string) ($identity['identity_key'] ?? ''));
            if ($existingView) {
                $this->touchUniquePostView((int) $existingView['id'], $identity);

                return true;
            }

            throw $exception;
        }

        return true;
    }

    public function currentDisplayedViewCount($postId)
    {
        $postId = (int) $postId;
        if ($postId <= 0 || !$this->tableExists('post_view_display_events')) {
            return 0;
        }

        $row = $this->db()->fetch(
            'SELECT COALESCE(posts.view_count, 0) + COUNT(post_view_display_events.id) AS total_count
             FROM posts
             LEFT JOIN post_view_display_events ON post_view_display_events.post_id = posts.id
              AND post_view_display_events.release_at <= :release_at
             WHERE posts.id = :post_id
               AND posts.status <> :deleted_status
             GROUP BY posts.id, posts.view_count
             LIMIT 1',
            array(
                'post_id' => $postId,
                'release_at' => $this->now(),
                'deleted_status' => 'deleted',
            )
        );

        return (int) ($row['total_count'] ?? 0);
    }

    public function postViewDisplaySettings()
    {
        $baseMin = (int) $this->app->settings()->get('post_view.base_min', '4935');
        $baseMax = (int) $this->app->settings()->get('post_view.base_max', '7563');
        $incrementMin = (int) $this->app->settings()->get('post_view.increment_min', '14');
        $incrementMax = (int) $this->app->settings()->get('post_view.increment_max', '20');

        if ($baseMin < 1) {
            $baseMin = 1;
        } elseif ($baseMin > 999999) {
            $baseMin = 999999;
        }

        if ($baseMax < 1) {
            $baseMax = 1;
        } elseif ($baseMax > 999999) {
            $baseMax = 999999;
        }

        if ($baseMax < $baseMin) {
            $baseMax = $baseMin;
        }

        if ($incrementMin < 1) {
            $incrementMin = 1;
        } elseif ($incrementMin > 999) {
            $incrementMin = 999;
        }

        if ($incrementMax < 1) {
            $incrementMax = 1;
        } elseif ($incrementMax > 999) {
            $incrementMax = 999;
        }

        if ($incrementMax < $incrementMin) {
            $incrementMax = $incrementMin;
        }

        return array(
            'base_min' => $baseMin,
            'base_max' => $baseMax,
            'increment_min' => $incrementMin,
            'increment_max' => $incrementMax,
        );
    }

    public function postSaleBuyerIncrementSettings()
    {
        $incrementMin = (int) $this->app->settings()->get('post_sale_buyer.increment_min', '1');
        $incrementMax = (int) $this->app->settings()->get('post_sale_buyer.increment_max', '3');

        if ($incrementMin < 0) {
            $incrementMin = 0;
        } elseif ($incrementMin > 999) {
            $incrementMin = 999;
        }

        if ($incrementMax < 0) {
            $incrementMax = 0;
        } elseif ($incrementMax > 999) {
            $incrementMax = 999;
        }

        if ($incrementMax < $incrementMin) {
            $incrementMax = $incrementMin;
        }

        return array(
            'increment_min' => $incrementMin,
            'increment_max' => $incrementMax,
            'start_time' => '08:00',
            'end_time' => '21:10',
        );
    }

    public function currentUniqueViewCount($postId)
    {
        $postId = (int) $postId;
        if ($postId <= 0 || !$this->tableExists('post_unique_views')) {
            return 0;
        }

        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS total_count
             FROM post_unique_views
             WHERE post_id = :post_id',
            array(
                'post_id' => $postId,
            )
        );

        return (int) ($row['total_count'] ?? 0);
    }

    protected function resolvePostViewIdentity(array $user = array())
    {
        $userId = (int) ($user['id'] ?? 0);
        $guestIdentityToken = $this->ensureGuestViewerIdentityToken();
        $guestIdentityKey = 'guest:' . $guestIdentityToken;
        $guestInteractionUserId = 1900000000 + ((int) sprintf('%u', crc32('post-like|' . $guestIdentityToken)) % 200000000);

        if ($userId > 0) {
            return array(
                'user_id' => $userId,
                'interaction_user_id' => $userId,
                'identity_type' => 'user',
                'identity_key' => 'user:' . $userId,
                'guest_identity_key' => $guestIdentityKey,
                'ip_address' => Security::ipAddress(),
                'user_agent' => Security::userAgent(),
            );
        }

        return array(
            'user_id' => null,
            'interaction_user_id' => $guestInteractionUserId,
            'identity_type' => 'guest',
            'identity_key' => $guestIdentityKey,
            'guest_identity_key' => $guestIdentityKey,
            'ip_address' => Security::ipAddress(),
            'user_agent' => Security::userAgent(),
        );
    }

    protected function findUniquePostView($postId, $identityKey)
    {
        $postId = (int) $postId;
        $identityKey = trim((string) $identityKey);

        if ($postId <= 0 || $identityKey === '') {
            return null;
        }

        return $this->db()->fetch(
            'SELECT id, user_id, identity_type, identity_key
             FROM post_unique_views
             WHERE post_id = :post_id
               AND identity_key = :identity_key
             LIMIT 1',
            array(
                'post_id' => $postId,
                'identity_key' => $identityKey,
            )
        );
    }

    protected function touchUniquePostView($uniqueViewId, array $identity)
    {
        $uniqueViewId = (int) $uniqueViewId;

        if ($uniqueViewId <= 0) {
            return;
        }

        $this->db()->execute(
            'UPDATE post_unique_views
             SET ip_address = :ip_address,
                 user_agent = :user_agent,
                 updated_at = :updated_at
             WHERE id = :id',
            array(
                'ip_address' => (string) ($identity['ip_address'] ?? ''),
                'user_agent' => (string) ($identity['user_agent'] ?? ''),
                'updated_at' => $this->now(),
                'id' => $uniqueViewId,
            )
        );
    }

    protected function seedPostDisplayViewEvents($postId, $uniqueViewId, $createdAt)
    {
        $postId = (int) $postId;
        $uniqueViewId = (int) $uniqueViewId;

        if ($postId <= 0 || $uniqueViewId <= 0) {
            return;
        }

        $createdTimestamp = strtotime((string) $createdAt);
        if ($createdTimestamp === false) {
            $createdTimestamp = time();
            $createdAt = date('Y-m-d H:i:s', $createdTimestamp);
        }

        $viewSettings = $this->postViewDisplaySettings();
        $eventCount = random_int((int) $viewSettings['increment_min'], (int) $viewSettings['increment_max']);
        $releaseOffsets = array(0);

        for ($index = 1; $index < $eventCount; $index++) {
            $releaseOffsets[] = random_int(1, 30 * 60);
        }

        sort($releaseOffsets, SORT_NUMERIC);

        foreach ($releaseOffsets as $index => $releaseOffset) {
            $this->db()->execute(
                'INSERT INTO post_view_display_events (post_id, unique_view_id, event_no, release_at, created_at)
                 VALUES (:post_id, :unique_view_id, :event_no, :release_at, :created_at)',
                array(
                    'post_id' => $postId,
                    'unique_view_id' => $uniqueViewId,
                    'event_no' => $index + 1,
                    'release_at' => date('Y-m-d H:i:s', $createdTimestamp + (int) $releaseOffset),
                    'created_at' => (string) $createdAt,
                )
            );
        }
    }

    protected function guestViewerIdentityCookieName()
    {
        return 'front_viewer_identity';
    }

    protected function ensureGuestViewerIdentityToken()
    {
        $cookieName = $this->guestViewerIdentityCookieName();
        $existing = strtolower(trim((string) ($_COOKIE[$cookieName] ?? '')));

        if (preg_match('/^[a-f0-9]{32}$/', $existing)) {
            return $existing;
        }

        $token = bin2hex(random_bytes(16));
        $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
        $expiresAt = time() + (365 * 24 * 60 * 60);

        if (PHP_VERSION_ID >= 70300) {
            setcookie($cookieName, $token, array(
                'expires' => $expiresAt,
                'path' => '/',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax',
            ));
        } else {
            setcookie($cookieName, $token, $expiresAt, '/');
        }

        $_COOKIE[$cookieName] = $token;

        return $token;
    }

    public function toggleLike($postId, array $user)
    {
        return $this->togglePostInteraction($postId, $user, 'like');
    }

    public function postLikeIncrementSettings()
    {
        $baseMin = (int) $this->app->settings()->get('post_like.base_min', '368');
        $baseMax = (int) $this->app->settings()->get('post_like.base_max', '668');
        $min = (int) $this->app->settings()->get('post_like.increment_min', '1');
        $max = (int) $this->app->settings()->get('post_like.increment_max', '1');

        if ($baseMin < 1) {
            $baseMin = 1;
        } elseif ($baseMin > 999999) {
            $baseMin = 999999;
        }

        if ($baseMax < 1) {
            $baseMax = 1;
        } elseif ($baseMax > 999999) {
            $baseMax = 999999;
        }

        if ($baseMax < $baseMin) {
            $baseMax = $baseMin;
        }

        if ($min < 1) {
            $min = 1;
        } elseif ($min > 999) {
            $min = 999;
        }

        if ($max < 1) {
            $max = 1;
        } elseif ($max > 999) {
            $max = 999;
        }

        if ($max < $min) {
            $max = $min;
        }

        return array(
            'base_min' => $baseMin,
            'base_max' => $baseMax,
            'increment_min' => $min,
            'increment_max' => $max,
        );
    }

    public function currentDisplayedLikeCount($postId)
    {
        $postId = (int) $postId;
        if ($postId <= 0) {
            return 0;
        }

        $post = $this->db()->fetch(
            'SELECT posts.id,
                    posts.created_at,
                    COALESCE(post_meta.created_at, posts.created_at) AS like_increment_start_at
             FROM posts
             LEFT JOIN post_manage_meta post_meta ON post_meta.post_id = posts.id
             WHERE posts.id = :id
               AND posts.status <> :deleted_status
             LIMIT 1',
            array(
                'id' => $postId,
                'deleted_status' => 'deleted',
            )
        );
        if (!$post) {
            return 0;
        }

        $settings = $this->postLikeIncrementSettings();
        $baseMin = (int) $settings['base_min'];
        $baseMax = (int) $settings['base_max'];
        $min = (int) $settings['increment_min'];
        $max = (int) $settings['increment_max'];
        $baseSeed = (int) sprintf(
            '%u',
            crc32('post-like-initial|' . (string) $postId . '|base')
        );
        $count = $baseMin + ($baseSeed % (($baseMax - $baseMin) + 1));
        $createdTimestamp = strtotime((string) ($post['like_increment_start_at'] ?? $post['created_at'] ?? ''));
        $todayTimestamp = strtotime($this->now());
        if ($createdTimestamp !== false && $todayTimestamp !== false) {
            $createdDate = date('Y-m-d', $createdTimestamp);
            $todayDate = date('Y-m-d', $todayTimestamp);
            $dailyIncrement = static function ($dateValue) use ($postId, $min, $max) {
                $dailySeed = (int) sprintf(
                    '%u',
                    crc32(
                        'post-like-daily|'
                        . (string) $postId
                        . '|'
                        . (string) $dateValue
                    )
                );

                return $min + ($dailySeed % (($max - $min) + 1));
            };
            $weightedProgress = static function ($minuteValue) {
                $minuteValue = max(0.0, min(1440.0, (float) $minuteValue));
                $peakStart = 690.0;
                $peakEnd = 1350.0;
                $peakElapsed = 0.0;

                if ($minuteValue > $peakStart) {
                    $peakElapsed = min($minuteValue, $peakEnd) - $peakStart;
                }

                $outsideElapsed = $minuteValue - $peakElapsed;

                return $outsideElapsed + ($peakElapsed * 3.0);
            };
            $releasedDailyIncrement = static function (
                $dailyCount,
                $currentTimestamp,
                $startTimestamp = null
            ) use ($weightedProgress) {
                $currentMinute = ((int) date('G', $currentTimestamp) * 60)
                    + (int) date('i', $currentTimestamp)
                    + ((int) date('s', $currentTimestamp) / 60);
                $startMinute = 0.0;

                if ($startTimestamp !== null && date('Y-m-d', $startTimestamp) === date('Y-m-d', $currentTimestamp)) {
                    $startMinute = ((int) date('G', $startTimestamp) * 60)
                        + (int) date('i', $startTimestamp)
                        + ((int) date('s', $startTimestamp) / 60);
                }

                if ($currentMinute <= $startMinute) {
                    return 0;
                }

                $weightedTotal = $weightedProgress(1440);
                $weightedElapsed = $weightedProgress($currentMinute) - $weightedProgress($startMinute);
                if ($weightedTotal <= 0 || $weightedElapsed <= 0) {
                    return 0;
                }

                $releasedCount = (int) floor(
                    ((int) $dailyCount) * min($weightedElapsed, $weightedTotal) / $weightedTotal
                );

                return max(0, min((int) $dailyCount, $releasedCount));
            };
            if ($createdDate !== '' && $todayDate !== '') {
                try {
                    $cursor = new \DateTimeImmutable($createdDate . ' 00:00:00');
                    $today = new \DateTimeImmutable($todayDate . ' 00:00:00');
                    $guard = 0;
                    while ($cursor < $today && $guard < 5000) {
                        $cursor = $cursor->modify('+1 day');
                        $cursorDate = $cursor->format('Y-m-d');
                        if ($cursorDate >= $todayDate) {
                            break;
                        }

                        $count += $dailyIncrement($cursorDate);
                        $guard++;
                    }

                    if ($createdDate <= $todayDate) {
                        $todayStartTimestamp = $createdDate === $todayDate ? $createdTimestamp : null;
                        $count += $releasedDailyIncrement(
                            $dailyIncrement($todayDate),
                            $todayTimestamp,
                            $todayStartTimestamp
                        );
                    }
                } catch (\Exception $exception) {
                }
            }
        }
        $count += $this->activeInteractionCount($postId, 'like');

        return max(0, (int) $count);
    }

    public function userLikedPost($postId, $userId)
    {
        $postId = (int) $postId;
        $actor = $userId;
        $userId = is_array($actor) ? (int) ($actor['id'] ?? 0) : (int) $actor;
        if ($postId <= 0) {
            return false;
        }

        if ($userId <= 0 && is_array($actor)) {
            $identity = $this->resolvePostViewIdentity($actor);
            $userId = (int) ($identity['interaction_user_id'] ?? 0);
        }

        if ($userId <= 0) {
            return false;
        }

        return $this->hasActiveUserInteraction($postId, $userId, 'like');
    }

    public function toggleFavorite($postId, array $user)
    {
        return $this->togglePostInteraction($postId, $user, 'favorite');
    }

    public function toggleFollow($postId, array $user)
    {
        return $this->togglePostInteraction($postId, $user, 'follow');
    }

    public function togglePostInteraction($postId, array $user, $interactionType)
    {
        $interactionType = (string) $interactionType;
        if (!in_array($interactionType, array('like', 'favorite', 'follow'), true)) {
            throw new RuntimeException('互动类型无效。');
        }

        $viewerUserId = (int) ($user['id'] ?? 0);
        $userId = $viewerUserId;

        if ($viewerUserId <= 0) {
            if ($interactionType !== 'like') {
                throw new RuntimeException('请先登录后再操作。');
            }

            $identity = $this->resolvePostViewIdentity(array());
            $userId = (int) ($identity['interaction_user_id'] ?? 0);
        }

        if ($userId <= 0) {
            throw new RuntimeException('请先登录后再操作。');
        }

        if ($interactionType !== 'like') {
            $this->app->users()->assertCanUsePostInteraction($user, '当前会员等级暂无帖子互动权限。');
        }

        if (!$this->tableExists('post_interactions')) {
            throw new RuntimeException('帖子互动功能尚未初始化，请联系管理员。');
        }

        $post = $this->findPost($postId);
        if (!$post) {
            throw new RuntimeException('帖子不存在或已下架。');
        }

        $now = $this->now();
        $liked = false;
        $likeCount = 0;

        $this->db()->beginTransaction();
        try {
            $existing = $this->db()->fetch(
                'SELECT id, status
                 FROM post_interactions
                 WHERE post_id = :post_id
                   AND user_id = :user_id
                   AND interaction_type = :interaction_type
                 LIMIT 1',
                array(
                    'post_id' => (int) $postId,
                    'user_id' => $userId,
                    'interaction_type' => $interactionType,
                )
            );

            if ($existing) {
                $nextStatus = (int) ($existing['status'] ?? 0) === 1 ? 0 : 1;
                $this->db()->execute(
                    'UPDATE post_interactions
                     SET status = :status,
                         updated_at = :updated_at
                     WHERE id = :id',
                    array(
                        'status' => $nextStatus,
                        'updated_at' => $now,
                        'id' => (int) ($existing['id'] ?? 0),
                    )
                );
                $liked = $nextStatus === 1;
            } else {
                $this->db()->insertGetId(
                    'INSERT INTO post_interactions (post_id, user_id, interaction_type, status, created_at, updated_at)
                     VALUES (:post_id, :user_id, :interaction_type, :status, :created_at, :updated_at)',
                    array(
                        'post_id' => (int) $postId,
                        'user_id' => $userId,
                        'interaction_type' => $interactionType,
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    )
                );
                $liked = true;
            }

            $likeCount = $this->activeInteractionCount((int) $postId, $interactionType);
            $this->db()->commit();
        } catch (\Exception $exception) {
            $this->db()->rollBack();
            throw $exception;
        }

        $displayLikeCount = $this->currentDisplayedLikeCount((int) $postId);

        return array(
            'post_id' => (int) $postId,
            'region' => (string) ($post['region'] ?? 'macau'),
            'interaction_type' => $interactionType,
            'liked' => $liked,
            'active' => $liked,
            'like_count' => $displayLikeCount,
            'count' => $likeCount,
        );
    }

    public function toggleCommentLike($commentId, array $user)
    {
        $this->ensureCommunitySchema();

        $userId = (int) ($user['id'] ?? 0);
        if ($userId <= 0) {
            $identity = $this->resolvePostViewIdentity(array());
            $userId = (int) ($identity['interaction_user_id'] ?? 0);
        }

        if ($userId <= 0) {
            throw new RuntimeException('请先登录后再点赞。');
        }

        $comment = $this->db()->fetch(
            'SELECT replies.*, posts.region
             FROM replies
             INNER JOIN posts ON posts.id = replies.post_id
             WHERE replies.id = :id
               AND replies.status = :status
             LIMIT 1',
            array(
                'id' => (int) $commentId,
                'status' => 'published',
            )
        );

        if (!$comment) {
            throw new RuntimeException('评论不存在或已隐藏。');
        }

        $now = $this->now();
        $liked = false;
        $likeCount = 0;
        $this->db()->beginTransaction();

        try {
            $existing = $this->db()->fetch(
                'SELECT id, status
                 FROM comment_likes
                 WHERE comment_id = :comment_id
                   AND user_id = :user_id
                 LIMIT 1',
                array(
                    'comment_id' => (int) $commentId,
                    'user_id' => $userId,
                )
            );

            if ($existing) {
                $nextStatus = (int) ($existing['status'] ?? 0) === 1 ? 0 : 1;
                $this->db()->execute(
                    'UPDATE comment_likes
                     SET status = :status,
                         updated_at = :updated_at
                     WHERE id = :id',
                    array(
                        'status' => $nextStatus,
                        'updated_at' => $now,
                        'id' => (int) $existing['id'],
                    )
                );
                $liked = $nextStatus === 1;
            } else {
                $this->db()->insertGetId(
                    'INSERT INTO comment_likes (comment_id, user_id, status, created_at, updated_at)
                     VALUES (:comment_id, :user_id, :status, :created_at, :updated_at)',
                    array(
                        'comment_id' => (int) $commentId,
                        'user_id' => $userId,
                        'status' => 1,
                        'created_at' => $now,
                        'updated_at' => $now,
                    )
                );
                $liked = true;
            }

            $likeCount = $this->activeCommentLikeCount((int) $commentId);
            $this->db()->execute('UPDATE replies SET like_count = :like_count WHERE id = :id', array(
                'like_count' => $likeCount,
                'id' => (int) $commentId,
            ));
            $this->db()->commit();
        } catch (\Exception $exception) {
            $this->db()->rollBack();
            throw $exception;
        }

        return array(
            'comment_id' => (int) $commentId,
            'post_id' => (int) ($comment['post_id'] ?? 0),
            'region' => (string) ($comment['region'] ?? 'macau'),
            'liked' => $liked,
            'like_count' => $likeCount,
        );
    }

    protected function activeCommentLikeCount($commentId)
    {
        if (!$this->tableExists('comment_likes')) {
            return 0;
        }

        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS aggregate_count
             FROM comment_likes
             WHERE comment_id = :comment_id
               AND status = 1',
            array(
                'comment_id' => (int) $commentId,
            )
        );

        return (int) ($row['aggregate_count'] ?? 0);
    }

    protected function activeInteractionCount($postId, $interactionType)
    {
        if (!$this->tableExists('post_interactions')) {
            return 0;
        }

        $row = $this->db()->fetch(
            'SELECT COUNT(*) AS aggregate_count
             FROM post_interactions
             WHERE post_id = :post_id
               AND interaction_type = :interaction_type
               AND status = 1',
            array(
                'post_id' => (int) $postId,
                'interaction_type' => (string) $interactionType,
            )
        );

        return (int) ($row['aggregate_count'] ?? 0);
    }

    protected function hasActiveUserInteraction($postId, $userId, $interactionType)
    {
        if (!$this->tableExists('post_interactions')) {
            return false;
        }

        $row = $this->db()->fetch(
            'SELECT id
             FROM post_interactions
             WHERE post_id = :post_id
               AND user_id = :user_id
               AND interaction_type = :interaction_type
               AND status = 1
             LIMIT 1',
            array(
                'post_id' => (int) $postId,
                'user_id' => (int) $userId,
                'interaction_type' => (string) $interactionType,
            )
        );

        return $row !== null;
    }

    protected function guestViewedPostCookieName()
    {
        return 'front_post_viewed_ids';
    }

    protected function guestHasViewedPost($postId)
    {
        return in_array((int) $postId, $this->readGuestViewedPostIds(), true);
    }

    protected function readGuestViewedPostIds()
    {
        $raw = trim((string) ($_COOKIE[$this->guestViewedPostCookieName()] ?? ''));

        if ($raw === '') {
            return array();
        }

        return array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)), function ($value) {
            return $value > 0;
        })));
    }

    protected function rememberGuestViewedPost($postId)
    {
        $postId = (int) $postId;
        $ids = $this->readGuestViewedPostIds();
        $cookieName = $this->guestViewedPostCookieName();
        $value = '';
        $expiresAt;
        $secure = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';

        if ($postId <= 0) {
            return;
        }

        $ids = array_values(array_diff($ids, array($postId)));
        $ids[] = $postId;
        $ids = array_slice($ids, -180);
        $value = implode(',', $ids);
        $expiresAt = time() + (365 * 24 * 60 * 60);

        if (PHP_VERSION_ID >= 70300) {
            setcookie($cookieName, $value, array(
                'expires' => $expiresAt,
                'path' => '/',
                'secure' => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ));
        } else {
            setcookie($cookieName, $value, $expiresAt, '/');
        }

        $_COOKIE[$cookieName] = $value;
    }

    public function bulkAction(array $ids, $action, $value, $actor)
    {
        if (empty($ids)) {
            throw new RuntimeException('请先勾选要处理的帖子。');
        }

        $in = implode(',', array_map('intval', $ids));

        switch ($action) {
            case 'delete':
                $this->db()->execute(
                    'UPDATE posts
                     SET status = :status,
                         is_top_forever = 0,
                         is_top_admin = 0,
                         is_top_normal = 0,
                         deleted_at = :deleted_at,
                         updated_at = :updated_at
                     WHERE id IN (' . $in . ')',
                    array(
                        'status' => 'deleted',
                        'deleted_at' => $this->now(),
                        'updated_at' => $this->now(),
                    )
                );
                break;
            case 'restore':
                $this->db()->execute(
                    'UPDATE posts
                     SET status = :status,
                         deleted_at = NULL,
                         updated_at = :updated_at
                     WHERE id IN (' . $in . ')',
                    array(
                        'status' => 'draft',
                        'updated_at' => $this->now(),
                    )
                );
                break;
            case 'color':
                $this->db()->execute('UPDATE posts SET color_tag = :color_tag, updated_at = :updated_at WHERE id IN (' . $in . ')', array(
                    'color_tag' => (string) $value,
                    'updated_at' => $this->now(),
                ));
                break;
            case 'top_forever':
                $this->db()->execute('UPDATE posts SET is_top_forever = 1, updated_at = :updated_at WHERE id IN (' . $in . ')', array(
                    'updated_at' => $this->now(),
                ));
                break;
            case 'top_admin':
                $this->db()->execute('UPDATE posts SET is_top_admin = 1, updated_at = :updated_at WHERE id IN (' . $in . ')', array(
                    'updated_at' => $this->now(),
                ));
                break;
            case 'top_normal':
                $this->db()->execute('UPDATE posts SET is_top_normal = 1, updated_at = :updated_at WHERE id IN (' . $in . ')', array(
                    'updated_at' => $this->now(),
                ));
                break;
            default:
                throw new RuntimeException('不支持的批量操作。');
        }

        $this->app->logs()->admin('posts', 'bulk_' . $action, '批量处理帖子：' . implode(',', $ids), 'post', implode(',', $ids), $actor['id']);
    }
}
