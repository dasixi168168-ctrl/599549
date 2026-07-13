(function () {
    'use strict';

    var issuePrefixFormatter = window.AppIssuePrefix || null;

    function byId(id) {
        return document.getElementById(id);
    }

    function qsa(selector, root) {
        return Array.prototype.slice.call((root || document).querySelectorAll(selector));
    }

    function throttle(callback, wait) {
        var locked = false;

        return function () {
            var context = this;
            var args = arguments;

            if (locked) {
                return;
            }

            locked = true;
            callback.apply(context, args);
            window.setTimeout(function () {
                locked = false;
            }, wait || 160);
        };
    }

    function applyManagedTitleBackgrounds(root) {
        qsa('[data-title-bg-start], [data-title-bg-end]', root).forEach(function (element) {
            var start = String(element.getAttribute('data-title-bg-start') || '').trim();
            var end = String(element.getAttribute('data-title-bg-end') || '').trim();

            if (!start && !end) {
                return;
            }

            if (!start) {
                start = end;
            }

            if (!end) {
                end = start;
            }

            element.style.removeProperty('background');

            if (start === end) {
                element.style.setProperty('background-image', 'none', 'important');
                element.style.setProperty('background-color', start, 'important');
                return;
            }

            element.style.setProperty('background-color', start, 'important');
            element.style.setProperty('background-image', 'linear-gradient(90deg, ' + start + ', ' + end + ')', 'important');
        });
    }

    function normalizeHexColor(value) {
        var normalized = String(value || '').trim();

        if (!normalized) {
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
    }

    function colorToHex(value) {
        var normalizedHex = normalizeHexColor(value);
        var rgbMatch;
        var parts;

        if (normalizedHex) {
            return normalizedHex;
        }

        rgbMatch = String(value || '').trim().match(/^rgba?\(([^)]+)\)$/i);
        if (!rgbMatch) {
            return '';
        }

        parts = rgbMatch[1].split(',').map(function (part) {
            return parseInt(part, 10);
        });

        if (parts.length < 3 || parts.some(function (part, index) {
            return index < 3 && (isNaN(part) || part < 0 || part > 255);
        })) {
            return '';
        }

        return '#' + parts.slice(0, 3).map(function (part) {
            var hex = part.toString(16);
            return hex.length < 2 ? '0' + hex : hex;
        }).join('');
    }

    function findIssuePrefixTitleNode(node) {
        var groupRoot = getAdItemMiddleGroupRoot(node, document);
        var sectionRoot = groupRoot && groupRoot.closest ? groupRoot.closest('section') : null;
        var titleNode = null;

        if (groupRoot && groupRoot.previousElementSibling && groupRoot.previousElementSibling.classList && groupRoot.previousElementSibling.classList.contains('section-title')) {
            return groupRoot.previousElementSibling;
        }

        if (sectionRoot && sectionRoot.querySelector) {
            titleNode = sectionRoot.querySelector('.section-title');
        }

        return titleNode;
    }

    function getTitleThemeColors(titleNode) {
        var start = titleNode ? normalizeHexColor(titleNode.getAttribute('data-title-bg-start') || '') : '';
        var end = titleNode ? normalizeHexColor(titleNode.getAttribute('data-title-bg-end') || '') : '';
        var computedStyles;
        var backgroundColor = '';
        var gradientTokens = [];

        if (!titleNode) {
            return { start: '', end: '' };
        }

        if (start || end) {
            return {
                start: start || end,
                end: end || start
            };
        }

        computedStyles = window.getComputedStyle(titleNode);
        backgroundColor = colorToHex(computedStyles.backgroundColor || '');
        gradientTokens = String(computedStyles.backgroundImage || '').match(/(#[0-9a-fA-F]{3,6}|rgba?\([^)]+\))/g) || [];

        if (gradientTokens.length >= 2) {
            start = colorToHex(gradientTokens[0]) || backgroundColor;
            end = colorToHex(gradientTokens[1]) || start || backgroundColor;

            return {
                start: start,
                end: end
            };
        }

        return {
            start: backgroundColor,
            end: backgroundColor
        };
    }

    function applyIssuePrefixTheme(node) {
        var titleNode = findIssuePrefixTitleNode(node);
        var colors = getTitleThemeColors(titleNode);
        var start = colors.start || '';
        var end = colors.end || start || '';

        if (!node || !node.style) {
            return;
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
    }

    function resolveMiddleColorMode(value) {
        var mode = String(value || '').trim();

        if (mode === 'fixed' || mode === 'daily-random') {
            return mode;
        }

        return 'default';
    }

    function getShanghaiDateKey() {
        var now = new Date();
        var formatter;
        var parts;
        var year = '';
        var month = '';
        var day = '';

        try {
            if (typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
                formatter = new Intl.DateTimeFormat('en-US', {
                    timeZone: 'Asia/Shanghai',
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit'
                });
                parts = formatter.formatToParts(now);
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
    }

    function hashMiddleColorSeed(value) {
        var text = String(value || '');
        var hash = 0;
        var index;

        for (index = 0; index < text.length; index += 1) {
            hash = ((hash << 5) - hash + text.charCodeAt(index)) >>> 0;
        }

        return hash >>> 0;
    }

    function resolveTailTextMode(value) {
        return String(value || '').trim() === 'daily-random' ? 'daily-random' : 'fixed';
    }

    function parseRandomWordList(value) {
        var normalized = String(value || '').replace(/\r\n?/g, '\n');

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
    }

    var adMiddleRandomPalette = [
        '#ef4444',
        '#f97316',
        '#eab308',
        '#22c55e',
        '#06b6d4',
        '#3b82f6',
        '#8b5cf6',
        '#ec4899'
    ];

    function getAdItemMiddleGroupRoot(node, fallbackRoot) {
        if (node && node.closest) {
            return node.closest('.grid') || node.closest('.data-frame') || node.closest('section') || fallbackRoot || node;
        }

        return fallbackRoot || node;
    }

    function getAdItemMiddleGroupSeed(groupRoot, fallbackIndex) {
        var sectionRoot = groupRoot && groupRoot.closest ? groupRoot.closest('section') : null;
        var titleNode = sectionRoot && sectionRoot.querySelector ? sectionRoot.querySelector('.section-title') : null;
        var titleText = titleNode ? String(titleNode.textContent || '').replace(/\s+/g, ' ').trim() : '';

        if (!titleText && groupRoot && groupRoot.previousElementSibling && groupRoot.previousElementSibling.classList && groupRoot.previousElementSibling.classList.contains('section-title')) {
            titleText = String(groupRoot.previousElementSibling.textContent || '').replace(/\s+/g, ' ').trim();
        }

        return titleText || ('ad-group-' + fallbackIndex);
    }

    function shuffleAdItemMiddlePalette(palette, seedText) {
        var shuffled = (palette || []).slice();
        var index;
        var swapIndex;
        var seed = String(seedText || '');
        var temp;

        for (index = shuffled.length - 1; index > 0; index -= 1) {
            seed = String(hashMiddleColorSeed(seed + '|' + index));
            swapIndex = Number(seed) % (index + 1);
            temp = shuffled[index];
            shuffled[index] = shuffled[swapIndex];
            shuffled[swapIndex] = temp;
        }

        return shuffled;
    }

    function setAdItemMiddleColor(node, color) {
        if (color) {
            node.style.color = color;
        } else {
            node.style.removeProperty('color');
        }
    }

    function applyAdItemMiddleColors(root) {
        var nodes = qsa('.ad-item-middle', root);
        var groups = [];
        var dateKey = getShanghaiDateKey();

        nodes.forEach(function (node) {
            var groupRoot = getAdItemMiddleGroupRoot(node, root);
            var existingGroup = groups.filter(function (group) {
                return group.root === groupRoot;
            })[0];

            if (!existingGroup) {
                existingGroup = {
                    root: groupRoot,
                    nodes: []
                };
                groups.push(existingGroup);
            }

            existingGroup.nodes.push(node);
        });

        groups.forEach(function (group, groupIndex) {
            var groupSeed = getAdItemMiddleGroupSeed(group.root, groupIndex);
            var palette = adMiddleRandomPalette.slice();
            var dailyEntries = [];

            group.nodes.forEach(function (node, nodeIndex) {
                var mode = resolveMiddleColorMode(node.getAttribute('data-middle-color-mode'));
                var fixedColor = normalizeHexColor(node.getAttribute('data-middle-fixed-color') || node.style.color || '');

                if (mode === 'fixed') {
                    setAdItemMiddleColor(node, fixedColor);
                    if (fixedColor) {
                        palette = palette.filter(function (color) {
                            return color !== fixedColor;
                        });
                    }
                    return;
                }

                if (mode !== 'daily-random') {
                    setAdItemMiddleColor(node, '');
                    return;
                }

                dailyEntries.push({
                    node: node,
                    seed: String(node.getAttribute('data-middle-color-key') || node.textContent || ('slot-' + nodeIndex)).trim()
                });
            });

            if (!palette.length) {
                palette = adMiddleRandomPalette.slice();
            }

            palette = shuffleAdItemMiddlePalette(palette, dateKey + '|' + groupSeed);

            dailyEntries.sort(function (left, right) {
                var leftHash = hashMiddleColorSeed(dateKey + '|' + groupSeed + '|' + left.seed);
                var rightHash = hashMiddleColorSeed(dateKey + '|' + groupSeed + '|' + right.seed);

                if (leftHash === rightHash) {
                    return left.seed.localeCompare(right.seed);
                }

                return leftHash - rightHash;
            });

            dailyEntries.forEach(function (entry, entryIndex) {
                setAdItemMiddleColor(entry.node, palette[entryIndex % palette.length] || '');
            });
        });
    }

    function applyAdItemTailTexts(root) {
        qsa('.ad-item-tail', root).forEach(function (node, index) {
            var mode = resolveTailTextMode(node.getAttribute('data-tail-text-mode'));
            var defaultText = String(node.getAttribute('data-tail-default-text') || node.textContent || '').replace(/\s+/g, ' ').trim();
            var options = parseRandomWordList(node.getAttribute('data-tail-random-options') || '');
            var seed = String(node.getAttribute('data-tail-random-key') || defaultText || ('tail-' + index)).trim();
            var chosenText = defaultText;

            if (mode === 'daily-random' && options.length) {
                chosenText = options[hashMiddleColorSeed(getShanghaiDateKey() + '|' + seed) % options.length];
            }

            node.textContent = chosenText;
        });
    }

    function normalizeAdItemUrl(value) {
        var text = String(value || '').trim();
        var parsed;

        if (!text) {
            return '';
        }

        if (/^(javascript|data|vbscript):/i.test(text)) {
            return '';
        }

        if (/^(https?:)?\/\//i.test(text)) {
            try {
                parsed = new URL(text, window.location.href);
            } catch (error) {
                return '';
            }

            if (!/^(http:|https:)$/i.test(parsed.protocol)) {
                return '';
            }

            return parsed.href;
        }

        if (/^(\/|\.{1,2}\/|#|\?)/.test(text)) {
            return text;
        }

        if (/^[^\s/?#]+\.[^\s]+$/.test(text) || /^[^\s/?#]+\.[^\s]+[/?#].*$/.test(text)) {
            try {
                parsed = new URL('https://' + text);
            } catch (error) {
                return '';
            }

            return parsed.href;
        }

        if (/^\S+$/.test(text)) {
            return text;
        }

        return '';
    }

    function isExternalAdItemUrl(value) {
        var normalized = normalizeAdItemUrl(value);
        var parsed;

        if (!normalized) {
            return false;
        }

        try {
            parsed = new URL(normalized, window.location.href);
        } catch (error) {
            return /^(https?:)?\/\//i.test(normalized);
        }

        return /^(http:|https:)$/i.test(parsed.protocol) && parsed.origin !== window.location.origin;
    }

    function isAdNativeLink(node) {
        if (!node || !node.matches) {
            return false;
        }

        return node.matches('a[href][data-front-ad-link], a[href].ad-item[data-ad-url]')
            || !!(node.closest && node.closest('.expert-ad-slot-card, [data-expert-ad-slot="1"]'));
    }

    function applyAdItemExpiry(root) {
        var todayKey = getShanghaiDateKey();

        qsa('.ad-item', root).forEach(function (node) {
            var expireDate = String(node.getAttribute('data-ad-expire-date') || '').trim();
            var isExpired = /^\d{4}-\d{2}-\d{2}$/.test(expireDate) && todayKey > expireDate;

            if (isExpired) {
                node.setAttribute('hidden', 'hidden');
                node.setAttribute('aria-hidden', 'true');
            } else {
                node.removeAttribute('hidden');
                node.removeAttribute('aria-hidden');
            }
        });
    }

    function applyAdItemBorderColors(root) {
        qsa('.ad-item', root).forEach(function (node) {
            var customColor = normalizeHexColor(node.style.getPropertyValue('--ad-item-border-color'));
            var inlineColor = colorToHex(node.style.getPropertyValue('border-color') || node.style.borderColor || '');
            var resolvedColor = customColor || inlineColor;

            if (resolvedColor) {
                node.style.setProperty('--ad-item-border-color', resolvedColor);
            } else {
                node.style.removeProperty('--ad-item-border-color');
            }
        });
    }

    function ensureAdItemAnchorNode(node) {
        var linkNode = node;

        if (!node || !node.tagName) {
            return node;
        }

        if (String(node.tagName).toUpperCase() === 'A') {
            return node;
        }

        linkNode = document.createElement('a');
        Array.prototype.slice.call(node.attributes || []).forEach(function (attribute) {
            linkNode.setAttribute(attribute.name, attribute.value);
        });

        while (node.firstChild) {
            linkNode.appendChild(node.firstChild);
        }

        if (node.parentNode) {
            node.parentNode.replaceChild(linkNode, node);
        }

        return linkNode;
    }

    function applyAdItemLinks(root) {
        qsa('.ad-item', root).forEach(function (node) {
            var rawUrl = String(node.getAttribute('data-ad-url') || '').trim();
            var normalizedUrl = normalizeAdItemUrl(rawUrl);
            var text = String(node.textContent || '').replace(/\s+/g, ' ').trim();
            var linkNode = node;

            if (!normalizedUrl) {
                node.removeAttribute('href');
                node.removeAttribute('target');
                node.removeAttribute('rel');
                node.removeAttribute('aria-label');
                node.removeAttribute('tabindex');
                node.removeAttribute('role');
                node.removeAttribute('data-ad-link-bound');
                node.removeAttribute('data-front-ad-link');
                node.removeAttribute('data-front-flood-bypass');
                node.removeAttribute('data-no-prefetch');
                return;
            }

            linkNode = ensureAdItemAnchorNode(node);
            linkNode.setAttribute('href', normalizedUrl);
            linkNode.setAttribute('data-front-ad-link', '1');
            linkNode.setAttribute('data-front-flood-bypass', '1');
            linkNode.setAttribute('data-no-prefetch', '1');
            linkNode.style.textDecoration = 'none';
            linkNode.style.color = 'inherit';
            linkNode.removeAttribute('tabindex');
            linkNode.removeAttribute('role');
            linkNode.removeAttribute('data-ad-link-bound');
            linkNode.setAttribute('aria-label', text ? text + '，打开广告链接' : '打开广告链接');

            linkNode.removeAttribute('target');
            linkNode.removeAttribute('rel');
        });
    }

    function pad(value) {
        var number = Number(value || 0);
        return number < 10 ? '0' + number : String(number);
    }

    function getShanghaiNow() {
        var now = new Date();
        var formatter;
        var parts;
        var values = {};

        try {
            if (typeof Intl !== 'undefined' && Intl.DateTimeFormat) {
                formatter = new Intl.DateTimeFormat('en-US', {
                    timeZone: 'Asia/Shanghai',
                    year: 'numeric',
                    month: '2-digit',
                    day: '2-digit',
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false,
                    hourCycle: 'h23'
                });
                parts = formatter.formatToParts(now);
                parts.forEach(function (part) {
                    if (part.type !== 'literal') {
                        values[part.type] = part.value;
                    }
                });

                if (values.year && values.month && values.day && values.hour && values.minute && values.second) {
                    return new Date(
                        Number(values.year),
                        Number(values.month) - 1,
                        Number(values.day),
                        Number(values.hour) >= 24 ? 0 : Number(values.hour),
                        Number(values.minute),
                        Number(values.second)
                    );
                }
            }
        } catch (error) {
        }

        return now;
    }

    function parseDateTime(value) {
        var text = String(value || '').trim();
        var match;

        if (!text) {
            return null;
        }

        match = text.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}):(\d{2})(?::(\d{2}))?)?$/);
        if (!match) {
            return null;
        }

        return new Date(
            Number(match[1]),
            Number(match[2]) - 1,
            Number(match[3]),
            Number(match[4] || 0),
            Number(match[5] || 0),
            Number(match[6] || 0)
        );
    }

    function issueNumberValue(issueNo) {
        var text = String(issueNo || '').trim();

        if (!/^\d+$/.test(text)) {
            return null;
        }

        return Number(text);
    }

    function normalizeIssueTail(issueNo) {
        var numericIssue = issueNumberValue(issueNo);
        var text;

        if (numericIssue === null) {
            return '--';
        }

        text = String(numericIssue);
        text = text.length > 3 ? text.slice(-3) : text;
        while (text.length < 3) {
            text = '0' + text;
        }

        return text;
    }


    function resolvePromotedIssue(draw, region, now, managedIssue) {
        var managedPrefixTail = managedIssue && managedIssue.issue_prefix_tail ? String(managedIssue.issue_prefix_tail).trim() : '';
        var managedIssueNo = managedIssue && managedIssue.issue_no ? String(managedIssue.issue_no).trim() : '';
        var managedNumericIssue = issueNumberValue(managedIssueNo);
        var numericIssue = issueNumberValue(draw && draw.issue_no);
        var drawDate = parseDateTime(draw && draw.draw_date);
        var openedAt = drawDate || parseDateTime(draw && draw.open_time);
        var promoteAt;

        if (managedPrefixTail) {
            return managedPrefixTail;
        }

        if (numericIssue === null) {
            return managedIssueNo || (draw && draw.issue_no ? String(draw.issue_no) : '');
        }

        if ((region !== 'macau' && region !== 'hongkong') || !openedAt) {
            return draw && draw.issue_no ? String(draw.issue_no) : '';
        }

        promoteAt = new Date(openedAt.getFullYear(), openedAt.getMonth(), openedAt.getDate(), 23, 59, 0, 0);
        if ((now || new Date()).getTime() >= promoteAt.getTime()) {
            if (managedNumericIssue !== null && managedNumericIssue > numericIssue) {
                return String(managedNumericIssue);
            }

            return String(numericIssue + 1);
        }

        return String(numericIssue);
    }

    function normalizeDraw(rawDraw) {
        var draw = rawDraw && typeof rawDraw === 'object' ? rawDraw : null;
        var numbers = [];
        var index;

        if (!draw) {
            return null;
        }

        if (Array.isArray(draw.numbers)) {
            for (index = 0; index < draw.numbers.length; index += 1) {
                numbers.push(Number(draw.numbers[index] || 0));
            }
        }

        return {
            issue_no: String(draw.issue_no || '').trim(),
            draw_date: String(draw.draw_date || '').trim(),
            open_time: String(draw.open_time || '').trim(),
            next_open_time: String(draw.next_open_time || '').trim(),
            numbers: numbers.slice(0, 6),
            special_number: Number(draw.special_number || 0),
            note: String(draw.note || '').trim()
        };
    }

    function normalizeManagedIssue(rawIssue) {
        var issue = rawIssue && typeof rawIssue === 'object' ? rawIssue : null;

        if (!issue) {
            return null;
        }

        return {
            issue_no: String(issue.issue_no || '').trim(),
            issue_prefix_tail: String(issue.issue_prefix_tail || '').trim(),
            issue_prefix_text: String(issue.issue_prefix_text || '').trim(),
            planned_open_at: String(issue.planned_open_at || '').trim(),
            actual_open_at: String(issue.actual_open_at || '').trim(),
            status: String(issue.status || '').trim(),
            is_current: Number(issue.is_current || 0)
        };
    }

    function parseData() {
        var node = byId('legacy-home-data');

        if (!node) {
            return {};
        }

        try {
            return JSON.parse(node.textContent || '{}');
        } catch (error) {
            return {};
        }
    }

    var lunarInfo = [
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
    var earthlyBranches = ['子', '丑', '寅', '卯', '辰', '巳', '午', '未', '申', '酉', '戌', '亥'];
    var zodiacByBranch = {
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
    var shaDirectionByBranch = {
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
    var waveRed = [1, 2, 7, 8, 12, 13, 18, 19, 23, 24, 29, 30, 34, 35, 40, 45, 46];
    var waveBlue = [3, 4, 9, 10, 14, 15, 20, 25, 26, 31, 36, 37, 41, 42, 47, 48];
    var drawZodiacAnimals = ['鼠', '牛', '虎', '兔', '龙', '蛇', '马', '羊', '猴', '鸡', '狗', '猪'];
    var drawLunarNewYearDates = {
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
    var fivePhaseGroups = {
        '金': [2, 3, 10, 11, 24, 25, 32, 33, 40, 41],
        '木': [6, 7, 14, 15, 22, 23, 36, 37, 44, 45],
        '水': [12, 13, 20, 21, 28, 29, 42, 43],
        '火': [1, 8, 9, 16, 17, 30, 31, 38, 39, 46, 47],
        '土': [4, 5, 18, 19, 26, 27, 34, 35, 48, 49]
    };
    var dayBranchAnchorUtc = Date.UTC(2026, 2, 21);
    var dayBranchAnchorIndex = 6;

    function modulo(value, divisor) {
        return ((value % divisor) + divisor) % divisor;
    }

    function resolveDayBranch(currentDate) {
        var currentUtcDate = Date.UTC(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate());
        var diffDays = Math.round((currentUtcDate - dayBranchAnchorUtc) / 86400000);

        return earthlyBranches[modulo(dayBranchAnchorIndex + diffDays, earthlyBranches.length)];
    }

    function resolveLunarConflictMeta(currentDate) {
        var dayBranch = resolveDayBranch(currentDate);
        var chongBranch = earthlyBranches[modulo(earthlyBranches.indexOf(dayBranch) + 6, earthlyBranches.length)];

        return {
            sha: shaDirectionByBranch[dayBranch] || '--',
            chongZodiac: zodiacByBranch[chongBranch] || '--'
        };
    }

    function setText(id, text) {
        var node = byId(id);

        if (node && node.textContent !== String(text)) {
            node.textContent = text;
        }
    }

    function padDrawNumber(value) {
        var numericValue = Number(value || 0);

        if (!numericValue) {
            return '--';
        }

        return numericValue < 10 ? '0' + numericValue : String(numericValue);
    }

    function findGroupName(groups, value, fallback) {
        var number = Number(value || 0);
        var groupName;
        var groupValues;
        var index;

        for (groupName in groups) {
            if (!Object.prototype.hasOwnProperty.call(groups, groupName)) {
                continue;
            }

            groupValues = groups[groupName];
            for (index = 0; index < groupValues.length; index += 1) {
                if (Number(groupValues[index] || 0) === number) {
                    return groupName;
                }
            }
        }

        return fallback;
    }

    function resolveDrawLunarYear(drawDate) {
        var text = String(drawDate || '').trim();
        var match = text.match(/^(\d{4})-\d{2}-\d{2}/);
        var now = new Date();
        var year = match ? Number(match[1]) : now.getFullYear();

        if (drawLunarNewYearDates[year] && text && text.slice(0, 10) < drawLunarNewYearDates[year]) {
            year -= 1;
        }

        return year;
    }

    function resolveDrawZodiac(number, drawDate) {
        var value = Number(number || 0);
        var lunarYear;
        var yearAnimalIndex;
        var groupIndex;
        var animalIndex;

        if (value <= 0 || value > 49) {
            return '--';
        }

        lunarYear = resolveDrawLunarYear(drawDate);
        yearAnimalIndex = modulo(lunarYear - 4, 12);
        groupIndex = modulo(value - 1, 12);
        animalIndex = modulo(yearAnimalIndex - groupIndex, 12);

        return drawZodiacAnimals[animalIndex] || '--';
    }

    function waveColorClass(value) {
        var number = Number(value || 0);

        if (waveRed.indexOf(number) !== -1) {
            return 'red';
        }

        if (waveBlue.indexOf(number) !== -1) {
            return 'blue';
        }

        return 'green';
    }

    function sumDrawOddEven(value) {
        var digits = String(Math.abs(Number(value || 0))).split('');
        var sum = 0;

        digits.forEach(function (digit) {
            sum += Number(digit || 0);
        });

        return sum % 2 === 0 ? '合数双' : '合数单';
    }

    function formatLiveIssueText(draw, region) {
        var issueNo = draw && draw.issue_no ? String(draw.issue_no).trim() : '';
        var regionLabel = region === 'hongkong' ? '香港' : '澳门';
        var isForecastPage = !!document.querySelector('.forecast-page #section-live');

        if (!issueNo || (region !== 'macau' && region !== 'hongkong')) {
            return '--';
        }

        if (isForecastPage) {
            return normalizeIssueTail(issueNo) + '期';
        }

        return regionLabel + ' ' + normalizeIssueTail(issueNo) + '期';
    }

    function formatLiveOpenTimeText(draw, managedIssue) {
        var value = draw && (draw.next_open_time || draw.open_time || draw.draw_date)
            ? String(draw.next_open_time || draw.open_time || draw.draw_date).trim()
            : '';
        if (managedIssue && managedIssue.planned_open_at && String(managedIssue.status || '').trim() !== 'opened') {
            value = String(managedIssue.planned_open_at).trim();
        }

        return '下期开奖: ' + (value ? value.slice(0, 16) : '--');
    }

    function renderLiveBall(itemNode, value, drawDate) {
        var number = Number(value || 0);
        var colorClass = waveColorClass(number);
        var codeNode;
        var zodiacNode;

        if (!itemNode) {
            return;
        }

        codeNode = itemNode.querySelector('.result-jl-code');
        zodiacNode = itemNode.querySelector('.hero-ball-zodiac');

        if (codeNode) {
            codeNode.className = 'result-jl-code ' + colorClass;
            codeNode.textContent = number > 0 ? padDrawNumber(number) : '--';
        }

        if (zodiacNode) {
            zodiacNode.className = 'hero-ball-zodiac ' + colorClass;
            zodiacNode.textContent = number > 0 ? resolveDrawZodiac(number, drawDate) : '--';
        }
    }

    function renderLatestDraw(draw, region, now, managedIssue) {
        var values = [];
        var specialNumber = 0;
        var ballNodes;
        var index;
        var drawDate = draw && draw.draw_date ? String(draw.draw_date).trim() : '';

        setText('hero-result-period', formatLiveIssueText(draw, region));
        setText('hero-result-open-time', formatLiveOpenTimeText(draw, managedIssue));

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
        ballNodes = qsa('#hero-result-numbers .hero-ball-item');
        for (index = 0; index < ballNodes.length; index += 1) {
            renderLiveBall(ballNodes[index], values[index] || 0, drawDate);
        }

        setText('hero-result-zodiac', specialNumber > 0 ? resolveDrawZodiac(specialNumber, drawDate) : '--');
        setText('hero-result-odd-even', specialNumber > 0 ? sumDrawOddEven(specialNumber) : '--');
        setText('hero-result-five-phase', specialNumber > 0 ? findGroupName(fivePhaseGroups, specialNumber, '--') : '--');
    }

    function renderIssuePrefixes(draw, region, now, managedIssue) {
        var promotedIssue = resolvePromotedIssue(draw, region, now || new Date(), managedIssue);
        var prefixText = issuePrefixFormatter ? issuePrefixFormatter.format(promotedIssue) : '';
        var managedAdIssue = managedIssue
            ? String(managedIssue.issue_prefix_tail || managedIssue.issue_prefix_text || managedIssue.issue_no || '').trim()
            : '';
        var adIssue = managedAdIssue || promotedIssue;
        var adPrefixText = adIssue && issuePrefixFormatter ? issuePrefixFormatter.format(adIssue) : '';

        qsa('.issue-prefix').forEach(function (node) {
            var isExpertPrefix = !!(node.classList && node.classList.contains('issue-prefix-expert'));
            var postPrefixText = isExpertPrefix ? '' : String(node.getAttribute('data-post-issue-prefix') || '').trim();
            var nodePrefixText = postPrefixText || (node.classList.contains('issue-prefix-ad') ? adPrefixText : prefixText);

            if (nodePrefixText) {
                node.textContent = nodePrefixText;
                node.classList.add('is-ready');
                applyIssuePrefixTheme(node);
            } else {
                node.textContent = '';
                node.classList.remove('is-ready');
                node.classList.remove('is-title-synced');
                node.style.removeProperty('--issue-prefix-start');
                node.style.removeProperty('--issue-prefix-end');
            }
        });
    }

    function fitLiveHead() {
        var box = byId('section-live');
        var head;
        var left;
        var badge;
        var period;
        var time;
        var headGap;
        var leftGap;
        var viewportWidth;
        var isPhoneViewport;
        var isCompactPhone;
        var leftTargetRatio;
        var periodFont;
        var timeFont;
        var badgeFont;
        var badgeWidth;
        var timePadX;
        var periodTrack;
        var timeTrack;
        var config = {
            periodMin: 13.2,
            badgeMinWidth: 48,
            badgeMinFont: 9.2,
            timeMin: 8.8,
            timePadMin: 4,
            periodTrackMin: -0.05,
            timeTrackMin: -0.08
        };
        var guard = 0;

        function clampNumber(min, value, max) {
            return Math.max(min, Math.min(value, max));
        }

        function totalWidth() {
            return leftWidth() + headGap + time.scrollWidth;
        }

        function gapValue(node) {
            var style;
            if (!node) {
                return 0;
            }

            style = window.getComputedStyle(node);
            return parseFloat(style.columnGap || style.gap || '0') || 0;
        }

        function leftWidth() {
            return badge.getBoundingClientRect().width + leftGap + period.scrollWidth;
        }

        function applyTimePadding(value) {
            time.style.paddingLeft = value.toFixed(2) + 'px';
            time.style.paddingRight = value.toFixed(2) + 'px';
        }

        if (!box) {
            return;
        }

        head = box.querySelector('.hero-live-head');
        left = box.querySelector('.hero-live-left');
        badge = box.querySelector('.hero-live-badge');
        period = byId('hero-result-period');
        time = byId('hero-result-open-time');

        if (!head || !left || !badge || !period || !time) {
            return;
        }

        badge.style.removeProperty('width');
        badge.style.removeProperty('min-width');
        badge.style.removeProperty('font-size');
        period.style.removeProperty('font-size');
        period.style.removeProperty('letter-spacing');
        time.style.removeProperty('font-size');
        time.style.removeProperty('letter-spacing');
        time.style.removeProperty('padding-left');
        time.style.removeProperty('padding-right');

        leftGap = gapValue(left);
        headGap = gapValue(head);
        viewportWidth = window.visualViewport && window.visualViewport.width
            ? window.visualViewport.width
            : window.innerWidth;
        isPhoneViewport = viewportWidth <= 480;
        isCompactPhone = viewportWidth <= 390;
        leftTargetRatio = isPhoneViewport
            ? clampNumber(0.44, 0.31 + (viewportWidth / 2400), 0.47)
            : 0.52;
        if (isPhoneViewport) {
            config.periodMin = clampNumber(14.2, (viewportWidth * 0.035) + 1.2, 16.2);
            config.badgeMinWidth = Math.round(clampNumber(48, viewportWidth * 0.14, 54));
            config.badgeMinFont = clampNumber(9.5, viewportWidth * 0.027, 10.2);
            config.timeMin = clampNumber(9.8, viewportWidth * 0.0275, 11.2);
            config.timePadMin = clampNumber(2, viewportWidth * 0.012, 5);
            if (isCompactPhone) {
                config.periodTrackMin = -0.06;
                config.timeTrackMin = -0.09;
            }
        }
        periodFont = parseFloat(window.getComputedStyle(period).fontSize) || 24;
        timeFont = parseFloat(window.getComputedStyle(time).fontSize) || 13;
        badgeFont = parseFloat(window.getComputedStyle(badge).fontSize) || 12;
        badgeWidth = Math.round(badge.getBoundingClientRect().width);
        timePadX = parseFloat(window.getComputedStyle(time).paddingLeft) || 8;
        periodTrack = 0;
        timeTrack = 0;

        while (isPhoneViewport && leftWidth() > (head.clientWidth * leftTargetRatio) && guard < 90) {
            if (periodFont > config.periodMin) {
                periodFont -= 0.2;
                period.style.fontSize = periodFont.toFixed(2) + 'px';
            } else if (badgeWidth > config.badgeMinWidth) {
                badgeWidth -= 1;
                badge.style.width = badgeWidth + 'px';
                badge.style.minWidth = badgeWidth + 'px';
            } else if (periodTrack > config.periodTrackMin) {
                periodTrack -= 0.003;
                period.style.letterSpacing = periodTrack.toFixed(3) + 'em';
            } else if (badgeFont > config.badgeMinFont) {
                badgeFont -= 0.18;
                badge.style.fontSize = badgeFont.toFixed(2) + 'px';
            } else {
                break;
            }

            guard += 1;
        }

        guard = 0;

        while ((time.scrollWidth > time.clientWidth + 1.5 || totalWidth() > head.clientWidth - 1.5) && guard < 90) {
            if (time.scrollWidth > time.clientWidth + 1.5 && timeFont > config.timeMin) {
                timeFont -= 0.18;
                time.style.fontSize = timeFont.toFixed(2) + 'px';
            } else if (timePadX > config.timePadMin) {
                timePadX -= 0.5;
                applyTimePadding(timePadX);
            } else if (time.scrollWidth <= time.clientWidth + 1.5 && leftWidth() > left.clientWidth + 1.5) {
                if (periodFont > config.periodMin) {
                    periodFont -= 0.2;
                    period.style.fontSize = periodFont.toFixed(2) + 'px';
                } else if (badgeWidth > config.badgeMinWidth) {
                    badgeWidth -= 1;
                    badge.style.width = badgeWidth + 'px';
                    badge.style.minWidth = badgeWidth + 'px';
                } else if (badgeFont > config.badgeMinFont) {
                    badgeFont -= 0.18;
                    badge.style.fontSize = badgeFont.toFixed(2) + 'px';
                } else if (periodTrack > config.periodTrackMin) {
                    periodTrack -= 0.003;
                    period.style.letterSpacing = periodTrack.toFixed(3) + 'em';
                } else {
                    break;
                }
            } else if (timeTrack > config.timeTrackMin) {
                timeTrack -= 0.004;
                time.style.letterSpacing = timeTrack.toFixed(3) + 'em';
            } else {
                break;
            }

            guard += 1;
        }
    }

    function resetLiveHeadInlineStyles(box) {
        var badge;
        var period;
        var time;

        if (!box) {
            return;
        }

        badge = box.querySelector('.hero-live-badge');
        period = byId('hero-result-period');
        time = byId('hero-result-open-time');

        if (badge) {
            badge.style.removeProperty('width');
            badge.style.removeProperty('min-width');
            badge.style.removeProperty('font-size');
        }

        if (period) {
            period.style.removeProperty('font-size');
            period.style.removeProperty('letter-spacing');
        }

        if (time) {
            time.style.removeProperty('font-size');
            time.style.removeProperty('letter-spacing');
            time.style.removeProperty('padding-left');
            time.style.removeProperty('padding-right');
        }
    }

    function formatLunarDayText(dayNumber) {
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
    }

    function formatLunarMonthText(monthNumber, isLeapMonth) {
        var monthNames = ['正', '二', '三', '四', '五', '六', '七', '八', '九', '十', '冬', '腊'];
        var month = Number(monthNumber || 0);

        if (month <= 0 || month > 12) {
            return '';
        }

        return (isLeapMonth ? '闰' : '') + monthNames[month - 1] + '月';
    }

    function formatLunarDateTextByIntl(currentDate) {
        var formatter;
        var parts;
        var month = '';
        var day = '';

        try {
            if (typeof Intl === 'undefined' || !Intl.DateTimeFormat) {
                return '';
            }

            formatter = new Intl.DateTimeFormat('zh-Hans-u-ca-chinese', {
                month: 'long',
                day: 'numeric'
            });
            parts = formatter.formatToParts(currentDate);
            parts.forEach(function (part) {
                if (part.type === 'month') {
                    month = String(part.value || '');
                } else if (part.type === 'day') {
                    day = String(part.value || '');
                }
            });
        } catch (error) {
            return '';
        }

        return month && day ? month + day : '';
    }

    function leapMonth(year) {
        return lunarInfo[year - 1900] & 0xf;
    }

    function leapDays(year) {
        if (!leapMonth(year)) {
            return 0;
        }

        return (lunarInfo[year - 1900] & 0x10000) ? 30 : 29;
    }

    function monthDays(year, month) {
        return (lunarInfo[year - 1900] & (0x10000 >> month)) ? 30 : 29;
    }

    function lunarYearDays(year) {
        var sum = 348;
        var mask;

        for (mask = 0x8000; mask > 0x8; mask >>= 1) {
            sum += (lunarInfo[year - 1900] & mask) ? 1 : 0;
        }

        return sum + leapDays(year);
    }

    function solarToLunar(currentDate) {
        var baseDate = Date.UTC(1900, 0, 31);
        var targetDate = Date.UTC(currentDate.getFullYear(), currentDate.getMonth(), currentDate.getDate());
        var offset = Math.floor((targetDate - baseDate) / 86400000);
        var year;
        var month;
        var temp = 0;
        var leap = 0;
        var isLeap = false;

        if (offset < 0) {
            return null;
        }

        for (year = 1900; year < 2050 && offset > 0; year += 1) {
            temp = lunarYearDays(year);
            offset -= temp;
        }

        if (offset < 0) {
            offset += temp;
            year -= 1;
        }

        leap = leapMonth(year);

        for (month = 1; month < 13 && offset > 0; month += 1) {
            if (leap > 0 && month === (leap + 1) && !isLeap) {
                month -= 1;
                isLeap = true;
                temp = leapDays(year);
            } else {
                temp = monthDays(year, month);
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
    }

    function formatLunarDateText(currentDate) {
        var lunarDate = solarToLunar(currentDate);
        var intlText;
        var monthText;
        var dayText;

        if (!lunarDate) {
            intlText = formatLunarDateTextByIntl(currentDate);
            return intlText && !/[0-9]/.test(intlText) ? intlText : '--';
        }

        monthText = formatLunarMonthText(lunarDate.month, lunarDate.isLeap);
        dayText = formatLunarDayText(lunarDate.day);

        if (!monthText || !dayText) {
            return '--';
        }

        return monthText + dayText;
    }

    function showAppToast(message, type) {
        if (!message) {
            return;
        }

        if (window.AppUI && typeof window.AppUI.toast === 'function') {
            window.AppUI.toast(String(message), type || 'info');
            return;
        }

        if (window.console && message) {
            window.console.warn(String(message));
        }
    }

    function updateExpertLikeButtonState(button, likeCount, liked) {
        var countNode;

        if (!button) {
            return;
        }

        countNode = button.querySelector('.expert-like-count');
        if (countNode) {
            countNode.textContent = String(Math.max(0, Number(likeCount || 0)));
        }

        button.setAttribute('data-liked', liked ? '1' : '0');
        button.setAttribute('aria-pressed', liked ? 'true' : 'false');
        button.classList.toggle('is-liked', !!liked);
    }

    function syncExpertLikeButtons(postId, likeCount, liked) {
        qsa('.expert-like-button').forEach(function (button) {
            if (String(button.getAttribute('data-post-id') || '') !== String(postId || '')) {
                return;
            }

            updateExpertLikeButtonState(button, likeCount, liked);
        });
    }

    function initExpertLikeButtons() {
        qsa('.expert-like-button').forEach(function (button) {
            if (button.getAttribute('data-like-bound') === '1') {
                return;
            }

            button.addEventListener('click', function (event) {
                var postId = String(button.getAttribute('data-post-id') || '').trim();
                var token = String(button.getAttribute('data-token') || '').trim();
                var apiUrl = String(button.getAttribute('data-api-url') || './api.php').trim();
                var formData;

                event.preventDefault();
                event.stopPropagation();

                if (!postId) {
                    return;
                }

                if (!token || button.classList.contains('is-loading')) {
                    return;
                }

                button.classList.add('is-loading');
                formData = new FormData();
                formData.append('action', 'post.like');
                formData.append('_token', token);
                formData.append('post_id', postId);

                fetch(apiUrl || './api.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                }).then(function (response) {
                    return response.text().then(function (text) {
                        try {
                            return JSON.parse(text);
                        } catch (error) {
                            throw new Error(text || '点赞请求返回异常。');
                        }
                    });
                }).then(function (payload) {
                    var result;

                    if (!payload || !payload.success) {
                        throw new Error((payload && payload.message) || '点赞失败。');
                    }

                    result = payload.data || {};
                    syncExpertLikeButtons(postId, Number(result.like_count || 0), !!result.liked);
                }).catch(function (error) {
                    showAppToast(error.message || '点赞失败。', 'error');
                }).finally(function () {
                    button.classList.remove('is-loading');
                });
            });

            button.setAttribute('data-like-bound', '1');
        });
    }

    function bindExpertPostModalFrame(modal, frame) {
        if (!modal || !frame || frame.getAttribute('data-expert-post-frame-ready') === '1') {
            return;
        }

        frame.width = '720';
        frame.height = '760';
        frame.addEventListener('load', function () {
            if (!modal || modal.querySelector('.expert-post-modal-frame') !== frame) {
                return;
            }
            scheduleExpertPostModalFrameSync();
        });
        frame.setAttribute('data-expert-post-frame-ready', '1');
    }

    function replaceExpertPostModalFrame(modal) {
        var body = modal ? modal.querySelector('.expert-post-modal-body') : null;
        var oldFrame = body ? body.querySelector('.expert-post-modal-frame') : null;
        var frame;

        if (!body) {
            return oldFrame;
        }

        frame = document.createElement('iframe');
        frame.className = 'expert-post-modal-frame';
        frame.title = '帖子阅读窗口';
        frame.loading = 'eager';
        frame.referrerPolicy = 'same-origin';
        bindExpertPostModalFrame(modal, frame);

        if (oldFrame && oldFrame.parentNode === body) {
            body.replaceChild(frame, oldFrame);
        } else {
            body.appendChild(frame);
        }

        return frame;
    }

    function ensureExpertPostModal() {
        var modal = byId('expert-post-modal');
        var frame;
        var title;
        var meta;

        if (modal) {
            return {
                modal: modal,
                frame: modal.querySelector('.expert-post-modal-frame'),
                title: modal.querySelector('.expert-post-modal-title'),
                meta: modal.querySelector('.expert-post-modal-meta'),
                author: modal.querySelector('.expert-post-modal-author')
            };
        }

        modal = document.createElement('div');
        modal.id = 'expert-post-modal';
        modal.className = 'expert-post-modal front-standard-modal';
        modal.setAttribute('hidden', 'hidden');
        modal.innerHTML = '' +
            '<div class="expert-post-modal-backdrop front-standard-modal-backdrop" data-expert-post-close="1"></div>' +
            '<div class="expert-post-modal-dialog front-standard-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="expert-post-modal-title">' +
                '<div class="expert-post-modal-header front-standard-modal-head">' +
                    '<div class="expert-post-modal-heading">' +
                        '<div class="expert-post-modal-identity">' +
                            '<span class="expert-post-modal-avatar" aria-hidden="true"><i class="fa-solid fa-circle-user"></i><span class="expert-post-modal-avatar-level">超级vip</span></span>' +
                            '<div class="expert-post-modal-author" hidden></div>' +
                        '</div>' +
                        '<div class="expert-post-modal-copy">' +
                            '<div id="expert-post-modal-title" class="expert-post-modal-title">帖子阅读</div>' +
                            '<div class="expert-post-modal-meta" hidden></div>' +
                        '</div>' +
                    '</div>' +
                    '<button type="button" class="expert-post-modal-close" data-expert-post-close="1" aria-label="关闭">×</button>' +
                '</div>' +
                '<div class="expert-post-modal-body front-standard-modal-body">' +
                    '<iframe class="expert-post-modal-frame" title="帖子阅读窗口" loading="eager" referrerpolicy="same-origin"></iframe>' +
                '</div>' +
            '</div>';

        document.body.appendChild(modal);

        modal.addEventListener('click', function (event) {
            var likeButton = event.target && event.target.closest ? event.target.closest('[data-expert-post-modal-like]') : null;

            if (likeButton && modal.contains(likeButton)) {
                event.preventDefault();
                toggleExpertPostModalLike(likeButton);
                return;
            }

            if (event.target && event.target.getAttribute('data-expert-post-close') === '1') {
                closeExpertPostModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' || event.key === 'Esc') {
                closeExpertPostModal();
            }
        });

        frame = modal.querySelector('.expert-post-modal-frame');
        title = modal.querySelector('.expert-post-modal-title');
        meta = modal.querySelector('.expert-post-modal-meta');

        bindExpertPostModalFrame(modal, frame);

        return {
            modal: modal,
            frame: frame,
            title: title,
            meta: meta,
            author: modal.querySelector('.expert-post-modal-author')
        };
    }

    function setExpertPostModalMeta(items) {
        var state = ensureExpertPostModal();

        if (!state.meta) {
            return;
        }

        state.meta.innerHTML = '';
        if (state.author) {
            state.author.innerHTML = '';
            state.author.setAttribute('hidden', 'hidden');
        }

        if (!items || !items.length) {
            if (state.author) {
                var authorNode = document.createElement('span');
                var authorValueNode = document.createElement('span');
                authorNode.className = 'expert-post-modal-meta-item is-placeholder';
                authorValueNode.className = 'expert-post-modal-meta-value';
                authorValueNode.textContent = '--';
                authorNode.appendChild(authorValueNode);
                state.author.appendChild(authorNode);
                state.author.removeAttribute('hidden');
            }

            var viewNode = document.createElement('span');
            var viewLabelNode = document.createElement('span');
            var viewValueNode = document.createElement('span');
            var likeNode = document.createElement('span');
            var likeIconNode = document.createElement('i');
            var likeValueNode = document.createElement('span');

            viewNode.className = 'expert-post-modal-meta-item is-placeholder';
            viewLabelNode.className = 'expert-post-modal-meta-label';
            viewLabelNode.textContent = '浏览：';
            viewValueNode.className = 'expert-post-modal-meta-value';
            viewValueNode.textContent = '--';
            viewNode.appendChild(viewLabelNode);
            viewNode.appendChild(viewValueNode);

            likeNode.className = 'expert-post-modal-meta-item expert-post-modal-meta-like is-placeholder';
            likeIconNode.className = 'fa-solid fa-thumbs-up expert-post-modal-like-icon';
            likeIconNode.setAttribute('aria-hidden', 'true');
            likeValueNode.className = 'expert-post-modal-meta-value';
            likeValueNode.textContent = '0';
            likeNode.appendChild(likeIconNode);
            likeNode.appendChild(document.createTextNode(textFromCharCodes([65306])));
            likeNode.appendChild(likeValueNode);

            state.meta.appendChild(viewNode);
            state.meta.appendChild(likeNode);
            state.meta.removeAttribute('hidden');
            return;
        }

        items.forEach(function (item, index) {
            var isLikeItem = item && typeof item === 'object' && item.type === 'like';
            var isAuthorItem = index === 0 && !isLikeItem && state.author;
            var node = document.createElement(isLikeItem ? 'button' : 'span');
            var iconNode;
            var labelNode;
            var valueNode;
            node.className = 'expert-post-modal-meta-item';

            if (isLikeItem) {
                node.className += ' expert-post-modal-meta-like';
                node.setAttribute('type', 'button');
                node.setAttribute('data-expert-post-modal-like', '1');
                node.setAttribute('aria-pressed', item.liked ? 'true' : 'false');
                if (item.liked) {
                    node.className += ' is-liked';
                }
                if (item.postId) {
                    node.setAttribute('data-post-id', String(item.postId));
                }
                iconNode = document.createElement('i');
                iconNode.className = 'fa-solid fa-thumbs-up expert-post-modal-like-icon';
                iconNode.setAttribute('aria-hidden', 'true');
                node.appendChild(iconNode);
            }

            if (item && typeof item === 'object') {
                labelNode = document.createElement('span');
                labelNode.className = 'expert-post-modal-meta-label';
                labelNode.textContent = String(item.label || '').trim();

                valueNode = document.createElement('span');
                valueNode.className = 'expert-post-modal-meta-value';
                valueNode.textContent = String(item.value || '').trim();

                if (!labelNode.textContent && !valueNode.textContent) {
                    return;
                }

                if (labelNode.textContent) {
                    node.appendChild(labelNode);
                }

                if (valueNode.textContent) {
                    if (isLikeItem) {
                        node.appendChild(document.createTextNode(textFromCharCodes([65306])));
                    }
                    node.appendChild(valueNode);
                }
            } else {
                node.textContent = String(item || '').trim();
            }

            if (!node.textContent && !node.childNodes.length) {
                return;
            }

            if (isAuthorItem) {
                state.author.appendChild(node);
            } else {
                state.meta.appendChild(node);
            }
        });

        if (state.author && state.author.childNodes.length) {
            state.author.removeAttribute('hidden');
        }

        if (!state.meta.childNodes.length) {
            state.meta.setAttribute('hidden', 'hidden');
            return;
        }

        state.meta.removeAttribute('hidden');
    }

    function formatShanghaiDateTime(value) {
        var date = value instanceof Date ? value : new Date(value);
        var formatter;
        var parts;
        var map = {};

        if (!(date instanceof Date) || isNaN(date.getTime())) {
            return '';
        }

        try {
            formatter = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Shanghai',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            });
            parts = formatter.formatToParts(date);
            parts.forEach(function (part) {
                if (part.type !== 'literal') {
                    map[part.type] = part.value;
                }
            });

            if (map.year && map.month && map.day && map.hour && map.minute) {
                return map.year + '-' + map.month + '-' + map.day + ' ' + map.hour + ':' + map.minute;
            }
        } catch (error) {
            return '';
        }

        return '';
    }

    function textFromCharCodes(codes) {
        return String.fromCharCode.apply(String, codes || []);
    }

    function expertPostMetaLabel(type) {
        if (type === 'author') {
            return textFromCharCodes([20316, 32773, 65306]);
        }

        if (type === 'shelf') {
            return textFromCharCodes([19978, 26550, 65306]);
        }

        if (type === 'published') {
            return textFromCharCodes([21457, 24067, 26102, 38388, 65306]);
        }

        if (type === 'views') {
            return textFromCharCodes([27983, 35272, 65306]);
        }

        return '';
    }

    function expertPostViewIcon() {
        return String.fromCodePoint(128065, 8205, 128488);
    }

    function stripLeadingMetaLabel(text, label) {
        var normalizedText = String(text || '').trim();
        var normalizedLabel = String(label || '').trim();

        if (!normalizedText) {
            return '';
        }

        if (normalizedLabel && normalizedText.indexOf(normalizedLabel) === 0) {
            return normalizedText.slice(normalizedLabel.length).trim();
        }

        return normalizedText;
    }

    function metaValueAfterLabel(items, label) {
        var normalizedLabel = String(label || '').trim();
        var matched = '';

        if (!normalizedLabel) {
            return '';
        }

        (items || []).some(function (text) {
            text = String(text || '').trim();
            if (text.indexOf(normalizedLabel) !== 0) {
                return false;
            }

            matched = text.slice(normalizedLabel.length).trim();
            return true;
        });

        return matched;
    }

    function buildExpertPostModalMeta(metaItems, likeState) {
        var normalizedItems = (metaItems || []).map(function (text) {
            return String(text || '').replace(/\s+/g, ' ').trim();
        }).filter(function (text) {
            return text !== '';
        });
        var authorValue = stripLeadingMetaLabel(normalizedItems[0], expertPostMetaLabel('author')) || '--';
        var shelfTime = metaValueAfterLabel(normalizedItems, expertPostMetaLabel('shelf'))
            || metaValueAfterLabel(normalizedItems, expertPostMetaLabel('published'));
        var viewValue = stripLeadingMetaLabel(normalizedItems.length ? normalizedItems[normalizedItems.length - 1] : '', expertPostMetaLabel('views')) || '--';
        var likeCount = likeState && likeState.count !== '' ? likeState.count : '0';

        return [
            {
                label: '',
                value: authorValue
            },
            {
                label: expertPostViewIcon() + expertPostMetaLabel('views'),
                value: viewValue
            },
            {
                label: expertPostMetaLabel('shelf'),
                value: shelfTime || '--'
            },
            {
                type: 'like',
                label: '',
                value: likeCount,
                liked: !!(likeState && likeState.liked),
                postId: likeState ? likeState.postId : ''
            }
        ];
    }

    function getShanghaiDateParts(value) {
        var now = value instanceof Date ? value : new Date();
        var formatter;
        var parts;
        var map = {};

        try {
            formatter = new Intl.DateTimeFormat('en-US', {
                timeZone: 'Asia/Shanghai',
                year: 'numeric',
                month: '2-digit',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            });
            parts = formatter.formatToParts(now);
            parts.forEach(function (part) {
                if (part.type !== 'literal') {
                    map[part.type] = part.value;
                }
            });
        } catch (error) {
            map.year = '';
        }

        return {
            year: Number(map.year || 0),
            month: Number(map.month || 0),
            day: Number(map.day || 0),
            hour: Number(map.hour || 0),
            minute: Number(map.minute || 0),
            second: Number(map.second || 0)
        };
    }

    function formatShanghaiDateKey(value) {
        var parts = getShanghaiDateParts(value);

        if (parts.year > 0 && parts.month > 0 && parts.day > 0) {
            return String(parts.year) + '-' + pad(parts.month) + '-' + pad(parts.day);
        }

        return getShanghaiDateKey();
    }

    function resolveFrontPostViewContext(root, frameUrl) {
        var resolvedUrl = '';
        var parsedUrl;
        var postId = 0;
        var region = '';
        var body = root && root.body ? root.body : document.body;

        try {
            resolvedUrl = String(frameUrl || (root && root.location ? root.location.href : window.location.href) || '').trim();
            parsedUrl = new URL(resolvedUrl, window.location.href);
            postId = parseInt(parsedUrl.searchParams.get('id') || '0', 10) || 0;
            region = String(parsedUrl.searchParams.get('region') || '').trim();
        } catch (error) {
            postId = 0;
            region = '';
        }

        if (!region && body && body.getAttribute) {
            region = String(body.getAttribute('data-region') || '').trim();
        }

        return {
            postId: postId,
            region: region || 'macau'
        };
    }

    function resolveFrontPostViewCycle(now) {
        var parts = getShanghaiDateParts(now);
        var cycleStartKey = formatShanghaiDateKey(now);
        var minutesFromStart = 0;
        var previousDay = new Date((now instanceof Date ? now.getTime() : Date.now()) - (24 * 60 * 60 * 1000));

        if (parts.hour >= 23) {
            minutesFromStart = ((parts.hour - 23) * 60) + parts.minute;
        } else {
            cycleStartKey = formatShanghaiDateKey(previousDay);
            minutesFromStart = ((parts.hour + 1) * 60) + parts.minute;
        }

        return {
            cycleKey: cycleStartKey,
            minutesFromStart: Math.max(0, Math.min(1439, minutesFromStart))
        };
    }

    function resolveSeededRange(seed, min, max) {
        var lower = Math.min(Number(min) || 0, Number(max) || 0);
        var upper = Math.max(Number(min) || 0, Number(max) || 0);

        if (upper <= lower) {
            return lower;
        }

        return lower + (hashMiddleColorSeed(String(seed || '')) % ((upper - lower) + 1));
    }

    function resolveSeededBucketProgress(goal, ratio, stepCount, seed) {
        var normalizedRatio = Math.max(0, Math.min(1, Number(ratio) || 0));
        var totalSteps = Math.max(1, parseInt(stepCount, 10) || 1);
        var completedSteps = Math.floor(normalizedRatio * totalSteps);
        var totalWeight = 0;
        var currentWeight = 0;
        var index;
        var weight;

        if (completedSteps <= 0) {
            return 0;
        }

        if (completedSteps >= totalSteps) {
            return goal;
        }

        for (index = 0; index < totalSteps; index += 1) {
            weight = 1 + (hashMiddleColorSeed(String(seed || '') + '|step|' + index) % 9);
            totalWeight += weight;

            if (index < completedSteps) {
                currentWeight += weight;
            }
        }

        if (totalWeight <= 0) {
            return 0;
        }

        return Math.min(goal, Math.floor(goal * (currentWeight / totalWeight)));
    }

    function resolveFrontPostViewDisplayCount(realCount, postId, region) {
        var baseCount = Math.max(0, parseInt(realCount, 10) || 0);
        var now = new Date();
        var cycle = resolveFrontPostViewCycle(now);
        var seedBase = String(region || 'macau') + '|' + String(postId || 0) + '|' + cycle.cycleKey;
        var buckets = [
            { duration: 480, min: 220, max: 300 },
            { duration: 240, min: 320, max: 500 },
            { duration: 180, min: 560, max: 900 },
            { duration: 180, min: 700, max: 1100 },
            { duration: 360, min: 1200, max: 2100 }
        ];
        var offset = 0;
        var elapsed = cycle.minutesFromStart;
        var index;
        var bucket;
        var goal;
        var steps;
        var ratio;

        for (index = 0; index < buckets.length; index += 1) {
            bucket = buckets[index];
            goal = resolveSeededRange(seedBase + '|goal|' + index, bucket.min, bucket.max);

            if (elapsed >= bucket.duration) {
                offset += goal;
                elapsed -= bucket.duration;
                continue;
            }

            ratio = bucket.duration > 0 ? (elapsed / bucket.duration) : 1;
            steps = resolveSeededRange(seedBase + '|steps|' + index, 12, Math.min(48, bucket.duration));
            offset += resolveSeededBucketProgress(goal, ratio, steps, seedBase + '|bucket|' + index);
            break;
        }

        return baseCount + offset;
    }

    function extractNumericViewCount(text) {
        var matches = String(text || '').match(/\d+/g);

        if (!matches || !matches.length) {
            return 0;
        }

        return parseInt(matches[matches.length - 1], 10) || 0;
    }

    function normalizeFrontPostViewCount(value) {
        var count = parseInt(String(value || '').replace(/[^\d]/g, ''), 10);

        if (isNaN(count) || count < 0) {
            return '';
        }

        return String(count);
    }

    function writeFrontPostViewCountNode(node, countText) {
        var numberNode;

        if (!node || countText === '') {
            return;
        }

        node.setAttribute('data-post-view-count', countText);

        if (node.classList && node.classList.contains('expert-view-count')) {
            numberNode = node.querySelector('.expert-view-number');
            if (numberNode) {
                numberNode.textContent = countText;
            } else {
                node.textContent = countText;
            }
            node.setAttribute('aria-label', expertPostMetaLabel('views').replace('：', '量 ') + countText);
            return;
        }

        node.textContent = countText;
    }

    function syncFrontPostViewCount(root, postId, countText) {
        var normalizedPostId = String(postId || '').trim();
        var normalizedCount = normalizeFrontPostViewCount(countText);

        if (!root || !root.querySelectorAll || !normalizedPostId || normalizedCount === '') {
            return;
        }

        qsa('[data-post-view-count]', root).forEach(function (node) {
            var nodePostId = String(node.getAttribute('data-post-id') || '').trim();

            if (nodePostId && nodePostId !== normalizedPostId) {
                return;
            }

            writeFrontPostViewCountNode(node, normalizedCount);
        });
    }

    function applyFrontPostViewDisplay(root, frameUrl) {
        var context = resolveFrontPostViewContext(root, frameUrl);
        var latestCount = '';

        if (!root || !root.querySelectorAll) {
            return;
        }

        qsa('[data-post-view-count]', root).forEach(function (node) {
            var countText = normalizeFrontPostViewCount(node.getAttribute('data-post-view-count') || node.textContent);

            if (countText === '') {
                return;
            }

            latestCount = countText;
            writeFrontPostViewCountNode(node, countText);
        });

        if (context.postId && latestCount !== '') {
            syncFrontPostViewCount(document, context.postId, latestCount);
        }
    }

    function refreshFrontPostViewDisplays() {
        var modal = byId('expert-post-modal');

        applyFrontPostViewDisplay(document, window.location.href);

        if (modal && !modal.hasAttribute('hidden')) {
            syncExpertPostModalFrame();
        }
    }

    function toggleExpertPostModalLike(button) {
        var state = ensureExpertPostModal();
        var frameDocument;
        var frameLikeButton;

        if (!state.frame || !button || button.classList.contains('is-loading')) {
            return;
        }

        try {
            frameDocument = state.frame.contentDocument || (state.frame.contentWindow ? state.frame.contentWindow.document : null);
        } catch (error) {
            return;
        }

        if (!frameDocument) {
            return;
        }

        frameLikeButton = frameDocument.querySelector('[data-post-like]');
        if (!frameLikeButton) {
            return;
        }

        button.classList.add('is-loading');
        frameLikeButton.click();
        window.setTimeout(function () {
            syncExpertPostModalFrame();
            button.classList.remove('is-loading');
        }, 500);
        window.setTimeout(syncExpertPostModalFrame, 1200);
    }

    function syncExpertPostModalFrame() {
        var state = ensureExpertPostModal();
        var frameDocument;
        var expectedPostId = '';
        var framePostNode;
        var framePostId = '';
        var titleNode;
        var metaItems;
        var likeButton;
        var likeCountNode;
        var likeState = null;

        if (!state.frame) {
            return;
        }

        try {
            frameDocument = state.frame.contentDocument || (state.frame.contentWindow ? state.frame.contentWindow.document : null);
        } catch (error) {
            return;
        }

        if (!frameDocument) {
            return;
        }

        expectedPostId = state.modal ? String(state.modal.getAttribute('data-expert-post-id') || '').trim() : '';
        framePostNode = frameDocument.querySelector('[data-comment-thread][data-post-id], [data-post-like][data-post-id]');
        framePostId = framePostNode ? String(framePostNode.getAttribute('data-post-id') || '').trim() : '';
        if (expectedPostId && framePostId && framePostId !== expectedPostId) {
            return;
        }

        if (state.modal && frameDocument.querySelector('.front-post-main-content, .front-forecast-card, .front-post-detail-stack, .front-post-buy-actions')) {
            state.modal.classList.remove('is-loading');
        }

        applyFrontPostViewDisplay(frameDocument, frameDocument.location ? frameDocument.location.href : state.frame.getAttribute('src') || '');

        titleNode = frameDocument.querySelector('[data-post-display-title], .front-post-modal-sync-source h1, .front-panel-card h1');
        metaItems = qsa('.front-inline-meta > span', frameDocument).map(function (node) {
            return String(node.textContent || '').replace(/\s+/g, ' ').trim();
        }).filter(function (text) {
            return text !== '';
        });
        likeButton = frameDocument.querySelector('[data-post-like]');
        if (likeButton) {
            likeCountNode = likeButton.querySelector('[data-post-like-count]');
            likeState = {
                postId: String(likeButton.getAttribute('data-post-id') || '').trim(),
                count: normalizeFrontPostViewCount(likeCountNode ? likeCountNode.textContent : ''),
                liked: likeButton.classList.contains('is-liked') || likeButton.getAttribute('aria-pressed') === 'true'
            };
            if (likeState.postId && likeState.count !== '') {
                syncExpertLikeButtons(likeState.postId, Number(likeState.count || 0), likeState.liked);
            }
        }

        if (state.title && titleNode) {
            while (state.title.firstChild) {
                state.title.removeChild(state.title.firstChild);
            }
            Array.prototype.forEach.call(titleNode.childNodes, function (childNode) {
                state.title.appendChild(childNode.cloneNode(true));
            });
            if (!String(state.title.textContent || '').replace(/\s+/g, ' ').trim()) {
                state.title.textContent = '帖子阅读';
            }
        }

        setExpertPostModalMeta(buildExpertPostModalMeta(metaItems, likeState));
    }

    function scheduleExpertPostModalFrameSync() {
        [0, 80, 240, 600, 1200, 2200].forEach(function (delay) {
            window.setTimeout(syncExpertPostModalFrame, delay);
        });
    }

    window.addEventListener('message', function (event) {
        var data = event.data || {};
        var modal;
        var frame;

        if (event.origin && event.origin !== window.location.origin) {
            return;
        }

        if (!data || data.type !== 'front-post-modal-meta-ready') {
            return;
        }

        modal = byId('expert-post-modal');
        if (!modal) {
            return;
        }
        frame = modal ? modal.querySelector('.expert-post-modal-frame') : null;
        if (frame && event.source && event.source !== frame.contentWindow) {
            return;
        }
        if (modal && modal.getAttribute('data-expert-post-id') && String(data.postId || '') !== String(modal.getAttribute('data-expert-post-id') || '')) {
            return;
        }

        modal.classList.remove('is-loading');
        scheduleExpertPostModalFrameSync();
    });

    function closeExpertPostModal() {
        var state = ensureExpertPostModal();

        if (!state.modal || state.modal.hasAttribute('hidden')) {
            return;
        }

        state.modal.classList.remove('is-visible', 'is-loading');
        document.body.classList.remove('expert-post-modal-open');

        setExpertPostModalMeta([]);
        window.setTimeout(function () {
            if (!document.body.classList.contains('expert-post-modal-open')) {
                state.modal.setAttribute('hidden', 'hidden');
                state.modal.removeAttribute('data-expert-post-frame-url');
                state.modal.removeAttribute('data-expert-post-id');
                replaceExpertPostModalFrame(state.modal);
            }
        }, 180);
    }

    var expertPostModalFreshCounter = 0;

    function nextExpertPostModalFreshToken() {
        expertPostModalFreshCounter += 1;
        return String(Date.now ? Date.now() : (new Date()).getTime()) + '-' + String(expertPostModalFreshCounter);
    }

    function openExpertPostModal(url, titleText) {
        var state = ensureExpertPostModal();
        var frameUrl = String(url || '').trim();
        var currentFrameUrl = '';
        var nextPostId = '';
        var freshToken = nextExpertPostModalFreshToken();

        if (!state.modal || !state.frame) {
            return;
        }

        if (state.title) {
            state.title.textContent = '帖子阅读';
        }

        setExpertPostModalMeta([]);

        try {
            frameUrl = new URL(frameUrl, window.location.href);
            frameUrl.searchParams.set('modal', '1');
            frameUrl.searchParams.set('_fresh', freshToken);
            nextPostId = String(frameUrl.searchParams.get('id') || '').trim();
            frameUrl = frameUrl.href;
        } catch (error) {
            if (frameUrl) {
                frameUrl += (frameUrl.indexOf('?') === -1 ? '?' : '&') + 'modal=1&_fresh=' + encodeURIComponent(freshToken);
            }
        }

        state.modal.removeAttribute('hidden');
        state.modal.setAttribute('data-expert-post-frame-url', frameUrl);
        if (nextPostId) {
            state.modal.setAttribute('data-expert-post-id', nextPostId);
        } else {
            state.modal.removeAttribute('data-expert-post-id');
        }
        state.modal.classList.add('is-loading');
        currentFrameUrl = String(state.frame.getAttribute('src') || '').trim();
        if (currentFrameUrl !== frameUrl) {
            state.frame = replaceExpertPostModalFrame(state.modal);
            currentFrameUrl = '';
        }
        window.requestAnimationFrame(function () {
            state.modal.classList.add('is-visible');
            if (currentFrameUrl !== frameUrl) {
                state.frame.setAttribute('src', frameUrl);
            }
        });
        if (currentFrameUrl === frameUrl) {
            syncExpertPostModalFrame();
        }
        scheduleExpertPostModalFrameSync();
        document.body.classList.add('expert-post-modal-open');
    }

    function normalizeExpertPostOpenId(value) {
        var postId = parseInt(String(value || '').replace(/[^\d]/g, ''), 10);

        return postId > 0 ? postId : 0;
    }

    function buildExpertPostFrameUrl(postId, region) {
        var frameUrl;

        try {
            frameUrl = new URL('post.php', window.location.href);
            frameUrl.searchParams.set('id', String(postId));
            frameUrl.searchParams.set('region', region || 'macau');
            if (new URL(window.location.href).searchParams.get('agent') === '1') {
                frameUrl.searchParams.set('agent', '1');
            }

            return frameUrl.href;
        } catch (error) {
            return 'post.php?id=' + encodeURIComponent(String(postId)) + '&region=' + encodeURIComponent(region || 'macau') + (/[?&]agent=1(?:&|$)/.test(window.location.search) ? '&agent=1' : '');
        }
    }

    function clearExpertPostOpenParam() {
        var currentUrl;

        if (!window.history || !window.history.replaceState) {
            return;
        }

        try {
            currentUrl = new URL(window.location.href);
            currentUrl.searchParams.delete('open_post');
            window.history.replaceState(window.history.state, document.title, currentUrl.pathname + currentUrl.search + currentUrl.hash);
        } catch (error) {
        }
    }

    function initExpertPostAutoOpen() {
        var params;
        var postId = 0;
        var region = document.body ? String(document.body.getAttribute('data-region') || 'macau').trim() : 'macau';
        var frameUrl = '';
        var titleText = '帖子阅读';

        try {
            params = new URL(window.location.href).searchParams;
            postId = normalizeExpertPostOpenId(params.get('open_post'));
        } catch (error) {
            postId = 0;
        }

        if (!postId) {
            return;
        }

        qsa('.expert-item-link').some(function (link) {
            var linkUrl;
            var linkPostId = 0;

            try {
                linkUrl = new URL(String(link.getAttribute('href') || ''), window.location.href);
                linkPostId = normalizeExpertPostOpenId(linkUrl.searchParams.get('id'));
            } catch (error) {
                linkPostId = 0;
            }

            if (linkPostId !== postId) {
                return false;
            }

            frameUrl = String(link.getAttribute('href') || '').trim();
            titleText = String(link.textContent || '').replace(/\s+/g, ' ').trim() || titleText;

            return true;
        });

        if (!frameUrl) {
            frameUrl = buildExpertPostFrameUrl(postId, region === 'hongkong' ? 'hongkong' : 'macau');
        }

        openExpertPostModal(frameUrl, titleText);
        clearExpertPostOpenParam();
    }

    function initExpertPostLinks() {
        if (!document.body || document.body.getAttribute('data-post-modal-link-ready') === '1') {
            return;
        }

        document.body.setAttribute('data-post-modal-link-ready', '1');
        document.addEventListener('click', throttle(function (event) {
            var link = event.target.closest ? event.target.closest('.expert-item-link') : null;
            var href;
            var titleText;

            if (!link || !document.body.contains(link)) {
                return;
            }

            href = String(link.getAttribute('href') || '').trim();
            if (!href || event.defaultPrevented || ((typeof event.button === 'number') && event.button !== 0) || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            if (link.closest('.expert-ad-slot-card, [data-expert-ad-slot="1"]')) {
                return;
            }
            if (isAdNativeLink(link)) {
                return;
            }

            titleText = String(link.textContent || '').replace(/\s+/g, ' ').trim();

            event.preventDefault();
            openExpertPostModal(href, titleText);
        }, 180));
    }

    var calendarDateKey = '';

    function formatCalendarDateKey(value) {
        return value.getFullYear() + '-' + pad(value.getMonth() + 1) + '-' + pad(value.getDate());
    }

    var zodiacReferenceAnimals = [
        { name: '鼠', icon: '🐀' },
        { name: '牛', icon: '🐂' },
        { name: '虎', icon: '🐅' },
        { name: '兔', icon: '🐇' },
        { name: '龙', icon: '🐉' },
        { name: '蛇', icon: '🐍' },
        { name: '马', icon: '🐎' },
        { name: '羊', icon: '🐐' },
        { name: '猴', icon: '🐒' },
        { name: '鸡', icon: '🐓' },
        { name: '狗', icon: '🐕' },
        { name: '猪', icon: '🐖' }
    ];

    function zodiacReferenceModulo(value, divisor) {
        var result = value % divisor;
        return result < 0 ? result + divisor : result;
    }

    function resolveZodiacReferenceYear(value) {
        var currentDate = value instanceof Date ? value : getShanghaiNow();
        var lunarDate = solarToLunar(currentDate);
        var year = lunarDate && lunarDate.year ? lunarDate.year : currentDate.getFullYear();

        return {
            year: year,
            animalIndex: zodiacReferenceModulo(year - 4, 12)
        };
    }

    function setZodiacReferenceHead(headNode, animal) {
        var clashAnimal = zodiacReferenceAnimals[zodiacReferenceModulo(animal.index + 6, 12)];
        var iconNode;
        var labelNode;

        if (!headNode || !animal || !clashAnimal) {
            return;
        }

        while (headNode.firstChild) {
            headNode.removeChild(headNode.firstChild);
        }

        iconNode = document.createElement('span');
        iconNode.className = 'zodiac-ref-head-icon';
        iconNode.setAttribute('aria-hidden', 'true');
        iconNode.textContent = animal.icon;

        labelNode = document.createElement('span');
        labelNode.className = 'zodiac-ref-head-label';
        labelNode.textContent = animal.name + '【冲' + clashAnimal.name + '】';

        headNode.appendChild(iconNode);
        headNode.appendChild(labelNode);
    }

    function syncZodiacReferenceYear(value) {
        var section = byId('section-zodiac');
        var yearInfo = resolveZodiacReferenceYear(value);
        var year = yearInfo.year;
        var titleNode;
        var titleSpans;
        var nextText = String(year) + '年-12生肖号码对照表';
        var headNodes;
        var animalIndex;
        var index;

        if (!section || !year) {
            return;
        }

        titleNode = section.querySelector('.section-title');
        if (!titleNode || String(titleNode.textContent || '').indexOf('12生肖号码对照表') === -1) {
            return;
        }

        titleSpans = qsa('span', titleNode);
        for (index = 0; index < titleSpans.length; index += 1) {
            if (String(titleSpans[index].textContent || '').indexOf('12生肖号码对照表') !== -1) {
                if (titleSpans[index].textContent !== nextText) {
                    titleSpans[index].textContent = nextText;
                }

                break;
            }
        }

        headNodes = qsa('.zodiac-reference-card .zodiac-ref-grid .zodiac-ref-head', section);
        for (index = 0; index < headNodes.length && index < zodiacReferenceAnimals.length; index += 1) {
            animalIndex = zodiacReferenceModulo(yearInfo.animalIndex - index, 12);
            setZodiacReferenceHead(headNodes[index], {
                name: zodiacReferenceAnimals[animalIndex].name,
                icon: zodiacReferenceAnimals[animalIndex].icon,
                index: animalIndex
            });
        }
    }

    function updateClock(draw, region, managedIssue) {
        var now = getShanghaiNow();
        var weekdayNames = ['星期日', '星期一', '星期二', '星期三', '星期四', '星期五', '星期六'];
        var currentDateKey = formatCalendarDateKey(now);
        var lunarConflict;

        setText('live-time', pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds()));

        if (calendarDateKey !== currentDateKey) {
            lunarConflict = resolveLunarConflictMeta(now);
            calendarDateKey = currentDateKey;

            setText('live-weekday', weekdayNames[now.getDay()]);
            setText('solar-date', (now.getMonth() + 1) + '月' + now.getDate() + '日');
            setText('solar-year', now.getFullYear() + '年');
            setText('lunar-date', formatLunarDateText(now));
            setText('lunar-sha', '煞:' + lunarConflict.sha);
            setText('lunar-chong', '冲肖:' + lunarConflict.chongZodiac);
            syncZodiacReferenceYear(now);
        }

        renderLatestDraw(draw, region, now, managedIssue);
        renderIssuePrefixes(draw, region, now, managedIssue);
    }

    function calendarDayStart(value) {
        return new Date(value.getFullYear(), value.getMonth(), value.getDate()).getTime();
    }

    function drawTrackingWindowForDay(value) {
        return {
            start: new Date(value.getFullYear(), value.getMonth(), value.getDate(), 21, 32, 0, 0),
            end: new Date(value.getFullYear(), value.getMonth(), value.getDate(), 21, 40, 59, 999)
        };
    }

    function resolveDrawTrackingWindow(region, managedIssue, draw, now) {
        var current = now instanceof Date ? now : new Date();
        var reference = managedIssue && managedIssue.planned_open_at ? parseDateTime(managedIssue.planned_open_at) : null;
        var todayStart = calendarDayStart(current);
        var referenceStart;
        var base;
        var windowInfo;

        if (region !== 'macau' && region !== 'hongkong') {
            return null;
        }

        if (!reference && draw) {
            reference = parseDateTime(draw.next_open_time) || parseDateTime(draw.open_time);
        }

        base = reference || current;
        if (reference) {
            referenceStart = calendarDayStart(reference);
            if (referenceStart < todayStart) {
                base = current;
            }
        }

        windowInfo = drawTrackingWindowForDay(base);
        if (current.getTime() > windowInfo.end.getTime() && (!reference || calendarDayStart(reference) <= todayStart)) {
            windowInfo = drawTrackingWindowForDay(new Date(current.getFullYear(), current.getMonth(), current.getDate() + 1));
        }

        return windowInfo;
    }

    function isInsideDrawTrackingWindow(windowInfo, now) {
        var time = (now instanceof Date ? now : new Date()).getTime();

        return !!windowInfo && time >= windowInfo.start.getTime() && time <= windowInfo.end.getTime();
    }

    function setupLiveDrawTracking(options) {
        var pollTimer = 0;
        var inFlight = false;
        var pollInterval = 8000;
        var idlePollInterval = 30000;
        var maxWait = 60000;

        if (!options || !window.fetch || !window.URLSearchParams) {
            return;
        }

        function clearPollTimer() {
            if (pollTimer) {
                window.clearTimeout(pollTimer);
                pollTimer = 0;
            }
        }

        function schedule(delay) {
            clearPollTimer();
            pollTimer = window.setTimeout(requestLatestDraw, Math.max(0, Number(delay || 0)));
        }

        function scheduleByWindow() {
            var now = getShanghaiNow();
            var windowInfo = resolveDrawTrackingWindow(options.region(), options.managedIssue(), options.draw(), now);
            var delay;

            if (!windowInfo) {
                return;
            }

            if (isInsideDrawTrackingWindow(windowInfo, now)) {
                schedule(0);
                return;
            }

            delay = windowInfo ? windowInfo.start.getTime() - now.getTime() : idlePollInterval;
            schedule(Math.min(Math.max(delay, idlePollInterval), maxWait));
        }

        function requestLatestDraw() {
            var now = getShanghaiNow();
            var region = options.region();
            var windowInfo = resolveDrawTrackingWindow(region, options.managedIssue(), options.draw(), now);
            var isTrackingWindow = isInsideDrawTrackingWindow(windowInfo, now);
            var body;

            if (inFlight || !options.apiUrl || !options.token) {
                schedule(isTrackingWindow ? pollInterval : idlePollInterval);
                return;
            }

            inFlight = true;
            body = new window.URLSearchParams();
            body.append('action', 'draw.latest');
            body.append('region', region);
            body.append('_token', options.token);

            window.fetch(options.apiUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: body.toString()
            }).then(function (response) {
                if (!response || !response.ok) {
                    return null;
                }

                return response.json();
            }).then(function (result) {
                if (result && result.success && typeof options.apply === 'function') {
                    options.apply(result);
                }
            }).catch(function () {
            }).then(function () {
                var checkedNow = getShanghaiNow();

                inFlight = false;
                if (isInsideDrawTrackingWindow(resolveDrawTrackingWindow(region, options.managedIssue(), options.draw(), checkedNow), checkedNow)) {
                    schedule(pollInterval);
                    return;
                }

                schedule(idlePollInterval);
            });
        }

        scheduleByWindow();
        window.addEventListener('focus', scheduleByWindow);
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) {
                scheduleByWindow();
            }
        });
    }

    function createFrameScheduler(callback) {
        var timerId = 0;
        var frameId = 0;

        return function (delay) {
            if (timerId) {
                window.clearTimeout(timerId);
                timerId = 0;
            }

            if (frameId) {
                window.cancelAnimationFrame(frameId);
                frameId = 0;
            }

            if (delay && delay > 0) {
                timerId = window.setTimeout(function () {
                    timerId = 0;
                    frameId = window.requestAnimationFrame(function () {
                        frameId = 0;
                        callback();
                    });
                }, delay);
                return;
            }

            frameId = window.requestAnimationFrame(function () {
                frameId = 0;
                callback();
            });
        };
    }

    function setupModeBadge(region) {
        var badge = byId('hero-mode-badge');
        var adSection = byId('section-ad');
        var label = byId('hero-mode-label');
        var left = badge && badge.closest ? badge.closest('.hero-live-left') : null;
        var isForecastPage = !!(badge && badge.closest && badge.closest('.forecast-page'));
        var regionLabel = region === 'hongkong' ? '香港' : '澳门';

        if (!badge) {
            return;
        }

        if (isForecastPage) {
            if (label) {
                label.textContent = regionLabel;
            }

            if (left) {
                left.setAttribute('data-region-mark', region === 'hongkong' ? '港' : '澳');
            }

            badge.setAttribute('aria-disabled', 'true');
            badge.setAttribute('tabindex', '-1');
            badge.removeAttribute('title');
            return;
        }

        badge.addEventListener('click', throttle(function () {
            if (region === 'hongkong' || region === 'macau') {
                window.location.href = './history.php?region=' + encodeURIComponent(region);
                return;
            }

            var target = adSection || byId('section-live');
            if (!target) {
                return;
            }

            target.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }, 220));
    }

    function boot() {
        var payload = parseData();
        var region = payload.region || document.body.getAttribute('data-region') || 'macau';
        var draw = normalizeDraw(payload.draw);
        var managedIssue = normalizeManagedIssue(payload.current_issue || payload.issue);
        var apiUrl = String(payload.api_url || './api.php').trim();
        var apiToken = String(payload.api_token || '').trim();
        var scheduleLiveHeadFit;

        function runLiveHeadFit() {
            var liveBox = byId('section-live');

            if (!liveBox) {
                return;
            }

            resetLiveHeadInlineStyles(liveBox);
            fitLiveHead();
        }

        scheduleLiveHeadFit = createFrameScheduler(runLiveHeadFit);

        applyManagedTitleBackgrounds(document);
        applyAdItemBorderColors(document);
        applyAdItemExpiry(document);
        applyAdItemMiddleColors(document);
        applyAdItemTailTexts(document);
        applyAdItemLinks(document);
        initExpertPostLinks();
        initExpertPostAutoOpen();
        initExpertLikeButtons();
        refreshFrontPostViewDisplays();
        updateClock(draw, region, managedIssue);
        scheduleLiveHeadFit(0);
        window.setTimeout(function () {
            scheduleLiveHeadFit(120);
        }, 120);
        setupModeBadge(region);
        setupLiveDrawTracking({
            region: function () {
                return region;
            },
            draw: function () {
                return draw;
            },
            managedIssue: function () {
                return managedIssue;
            },
            apiUrl: apiUrl,
            token: apiToken,
            apply: function (result) {
                var nextDraw = normalizeDraw(result.draw);
                var nextIssue = normalizeManagedIssue(result.current_issue || result.issue);

                if (nextDraw) {
                    draw = nextDraw;
                }

                if (nextIssue) {
                    managedIssue = nextIssue;
                }

                updateClock(draw, region, managedIssue);
                scheduleLiveHeadFit(0);
            }
        });
        window.addEventListener('resize', function () {
            scheduleLiveHeadFit(120);
        });
        window.addEventListener('orientationchange', function () {
            scheduleLiveHeadFit(160);
        });
        window.addEventListener('load', function () {
            scheduleLiveHeadFit(0);
        }, { once: true });
        if (document.fonts && document.fonts.ready && typeof document.fonts.ready.then === 'function') {
            document.fonts.ready.then(function () {
                scheduleLiveHeadFit(0);
            });
        }
        function scheduleClockTick() {
            var now = new Date();
            var delay = 1000 - now.getMilliseconds();

            window.setTimeout(function () {
                updateClock(draw, region, managedIssue);
                scheduleClockTick();
            }, Math.max(250, delay));
        }

        scheduleClockTick();

        window.setInterval(function () {
            refreshFrontPostViewDisplays();
        }, 60000);
    }

    if (document.body) {
        boot();
    } else {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    }
}());
