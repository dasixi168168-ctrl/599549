#!/usr/bin/env python3
"""Smoke-check the front forecast page in a real browser."""

import argparse
import json
import pathlib
import sys
from typing import Any, Dict, Iterable, List

try:
    from playwright.sync_api import sync_playwright
except ImportError as exc:
    raise SystemExit(
        "Playwright is not installed. Run: python3 -m pip install --user playwright && "
        "python3 -m playwright install chromium"
    ) from exc


def parse_widths(values: Iterable[str]) -> List[int]:
    widths: List[int] = []
    for value in values:
        for part in value.split(","):
            part = part.strip()
            if part:
                widths.append(int(part))
    return widths or [1024, 640, 390]


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", default="http://127.0.0.1:18080/public/forecast.php")
    parser.add_argument("--width", action="append", default=[], help="Viewport width; repeat or comma-separate")
    parser.add_argument("--height", type=int, default=900)
    parser.add_argument("--output", help="Write JSON to this file")
    parser.add_argument("--screenshot-dir", help="Write viewport screenshots to this directory")
    args = parser.parse_args()

    widths = parse_widths(args.width)
    result: Dict[str, Any] = {"base_url": args.base_url, "checks": [], "failures": []}
    screenshot_dir = pathlib.Path(args.screenshot_dir) if args.screenshot_dir else None
    if screenshot_dir:
        screenshot_dir.mkdir(parents=True, exist_ok=True)

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(
            headless=True,
            args=["--no-sandbox", "--disable-dev-shm-usage", "--disable-gpu"],
        )
        page = browser.new_page(viewport={"width": widths[0], "height": args.height})
        page.set_extra_http_headers({
            "X-Forwarded-Proto": "https",
            "Purpose": "prefetch",
        })
        console_errors: List[str] = []
        http_errors: List[Dict[str, Any]] = []
        page.on(
            "console",
            lambda message: console_errors.append(message.text) if message.type == "error" else None,
        )
        page.on("pageerror", lambda error: console_errors.append(str(error)))
        page.on(
            "response",
            lambda response: http_errors.append({"status": response.status, "url": response.url})
            if response.url.startswith(args.base_url.rsplit("/public/", 1)[0]) and response.status >= 400
            else None,
        )

        for region in ["macau", "hongkong"]:
            for width in widths:
                page.set_viewport_size({"width": width, "height": args.height})
                response = page.goto(f"{args.base_url}?region={region}", wait_until="domcontentloaded")
                page.wait_for_selector(".forecast-live-slot #section-live", timeout=30000)
                page.wait_for_timeout(500)
                page.evaluate("() => document.fonts ? document.fonts.ready : Promise.resolve()")
                check = page.evaluate(
                    """({region}) => {
                      const html = document.documentElement;
                      const body = document.body;
                      const forecastPage = document.querySelector('.forecast-page');
                      const filter = document.querySelector('.forecast-filter-row');
                      const filterSelects = filter
                        ? Array.from(filter.querySelectorAll(':scope > .forecast-filter-select'))
                        : [];
                      const summary = document.querySelector('.forecast-result-summary');
                      const blessing = document.querySelector('.forecast-result-blessing-card');
                      const resultTitle = document.querySelector('.forecast-result-title');
                      const resultIssueBadge = document.querySelector('.forecast-result-issue-badge');
                      const resultIssueMark = document.querySelector('.forecast-result-issue-mark');
                      const resultTitleText = document.querySelector('.forecast-result-title-text');
                      const resultScroll = document.querySelector('.forecast-result-scroll');
                      const resultLines = summary
                        ? Array.from(summary.querySelectorAll(':scope > .forecast-result-summary-line'))
                        : [];
                      const resultLabels = resultLines.map((line) =>
                        line.querySelector('.forecast-result-summary-label')
                      );
                      const resultPlaceholders = resultLines.map((line) =>
                        line.querySelector('.forecast-result-placeholder')
                      );
                      const liveSlot = document.querySelector('.forecast-live-slot');
                      const live = liveSlot ? liveSlot.querySelector(':scope > #section-live.hero-live-box') : null;
                      const liveHead = live ? live.querySelector(':scope > .hero-live-head') : null;
                      const liveLeft = liveHead ? liveHead.querySelector('.hero-live-left') : null;
                      const liveNumbers = live ? live.querySelector(':scope > .hero-live-numbers') : null;
                      const liveBadge = liveLeft ? liveLeft.querySelector(':scope > .hero-live-badge') : null;
                      const livePeriod = liveLeft ? liveLeft.querySelector(':scope > #hero-result-period') : null;
                      const liveBalls = liveNumbers
                        ? Array.from(liveNumbers.querySelectorAll(':scope > .hero-ball-item'))
                        : [];
                      const livePlus = liveNumbers ? liveNumbers.querySelector(':scope > .hero-ball-plus') : null;
                      const sectionTitle = document.querySelector('.forecast-section-title');
                      const primaryCard = document.querySelector('.forecast-card-primary');
                      const resultCard = document.querySelector('.forecast-result-card');
                      const statGrid = document.querySelector('.forecast-stat-grid');
                      const bottomNav = document.querySelector('.bottom-float-nav');
                      const rect = (element) => {
                        if (!element) return null;
                        const value = element.getBoundingClientRect();
                        return {
                          x: Number(value.x.toFixed(2)),
                          y: Number(value.y.toFixed(2)),
                          width: Number(value.width.toFixed(2)),
                          height: Number(value.height.toFixed(2)),
                          right: Number(value.right.toFixed(2)),
                          bottom: Number(value.bottom.toFixed(2))
                        };
                      };
                      const overlaps = (first, second) => !!first && !!second
                        && first.right > second.x + 1
                        && second.right > first.x + 1
                        && first.bottom > second.y + 1
                        && second.bottom > first.y + 1;
                      const contains = (parent, child) => !!parent && !!child
                        && child.x >= parent.x - 1
                        && child.y >= parent.y - 1
                        && child.right <= parent.right + 1
                        && child.bottom <= parent.bottom + 1;
                      const centerY = (value) => value ? value.y + (value.height / 2) : null;
                      const rowsOrdered = (values) => values.every((value, index) =>
                        index === 0 || values[index - 1].bottom <= value.y + 1
                      );
                      const cssNumber = (style, property) => style
                        ? Number.parseFloat(style.getPropertyValue(property))
                        : null;
                      const liveRect = rect(live);
                      const slotRect = rect(liveSlot);
                      const leftRect = rect(liveLeft);
                      const numbersRect = rect(liveNumbers);
                      const badgeRect = rect(liveBadge);
                      const periodRect = rect(livePeriod);
                      const ballRects = liveBalls.map(rect);
                      const plusRect = rect(livePlus);
                      const titleRect = rect(sectionTitle);
                      const cardRect = rect(primaryCard);
                      const filterRect = rect(filter);
                      const resultCardRect = rect(resultCard);
                      const resultTitleRect = rect(resultTitle);
                      const resultIssueBadgeRect = rect(resultIssueBadge);
                      const resultIssueMarkRect = rect(resultIssueMark);
                      const resultTitleTextRect = rect(resultTitleText);
                      const resultScrollRect = rect(resultScroll);
                      const resultSummaryRect = rect(summary);
                      const resultLineRects = resultLines.map(rect);
                      const resultLabelRects = resultLabels.map(rect);
                      const resultPlaceholderRects = resultPlaceholders.map(rect);
                      const statGridRect = rect(statGrid);
                      const verticalGap = (first, second) => first && second
                        ? Number((second.y - first.bottom).toFixed(2))
                        : null;
                      const filterSelectRects = filterSelects.map(rect);
                      const filterWidths = filterSelectRects.map((filterSelectRect) => filterSelectRect.width);
                      const filterHeights = filterSelectRects.map((filterSelectRect) => filterSelectRect.height);
                      const filterGaps = filterSelectRects.slice(1).map((filterSelectRect, index) =>
                        Number((filterSelectRect.x - filterSelectRects[index].right).toFixed(2))
                      );
                      const filterStyles = filterSelects.map((filterSelect) => {
                        const style = getComputedStyle(filterSelect);
                        return {
                          fontSize: style.fontSize,
                          fontWeight: style.fontWeight,
                          paddingLeft: style.paddingLeft,
                          paddingRight: style.paddingRight
                        };
                      });
                      const forecastPageStyle = forecastPage ? getComputedStyle(forecastPage) : null;
                      const resultLineHeights = resultLineRects.map((lineRect) => lineRect.height);
                      const resultBaselineOffsets = resultLabelRects.map((labelRect, index) => {
                        const placeholderRect = resultPlaceholderRects[index];
                        return labelRect && placeholderRect
                          ? Number(Math.abs(centerY(labelRect) - centerY(placeholderRect)).toFixed(2))
                          : null;
                      });
                      return {
                        expectedRegion: region,
                        bodyRegion: body ? body.getAttribute('data-region') : '',
                        title: document.title,
                        hasForecastPage: !!forecastPage,
                        hasFilter: !!filter,
                        hasSummary: !!summary,
                        hasBlessing: !!blessing,
                        liveContract: {
                          liveCount: liveSlot ? liveSlot.querySelectorAll(':scope > #section-live.hero-live-box').length : 0,
                          calendarCount: forecastPage ? forecastPage.querySelectorAll('.calendar-panel').length : 0,
                          badgeCount: live ? live.querySelectorAll('.hero-live-left > .hero-live-badge').length : 0,
                          periodCount: live ? live.querySelectorAll('#hero-result-period').length : 0,
                          timeCount: live ? live.querySelectorAll('#hero-result-open-time').length : 0,
                          ballCount: liveNumbers ? liveNumbers.querySelectorAll(':scope > .hero-ball-item').length : 0,
                          plusCount: liveNumbers ? liveNumbers.querySelectorAll(':scope > .hero-ball-plus').length : 0,
                          directChildren: live ? Array.from(live.children).map((child) => {
                            if (child.classList.contains('hero-live-head')) return 'hero-live-head';
                            if (child.classList.contains('hero-live-numbers')) return 'hero-live-numbers';
                            if (child.classList.contains('hero-live-meta')) return 'hero-live-meta';
                            return child.tagName.toLowerCase() + '.' + Array.from(child.classList).join('.');
                          }) : [],
                          regionTabs: forecastPage ? forecastPage.querySelectorAll('.forecast-region-tab').length : 0,
                          activeRegionTabs: forecastPage ? forecastPage.querySelectorAll('.forecast-region-tab.is-active').length : 0,
                          filterSelects: forecastPage ? forecastPage.querySelectorAll('.forecast-filter-select').length : 0,
                          statCards: forecastPage ? forecastPage.querySelectorAll('.forecast-stat-card').length : 0,
                          generateForms: forecastPage ? forecastPage.querySelectorAll('#forecast-generate-form').length : 0,
                          bottomNavs: bottomNav ? 1 : 0
                        },
                        layout: {
                          slot: slotRect,
                          live: liveRect,
                          left: leftRect,
                          numbers: numbersRect,
                          sectionTitle: titleRect,
                          primaryCard: cardRect,
                          resultCard: resultCardRect,
                          statGrid: statGridRect,
                          bottomNav: rect(bottomNav),
                          verticalGaps: {
                            titleToLive: verticalGap(titleRect, liveRect),
                            liveToFilter: verticalGap(liveRect, filterRect),
                            filterToResult: verticalGap(filterRect, resultCardRect),
                            resultToStats: verticalGap(resultCardRect, statGridRect)
                          },
                          liveInsideSlot: contains(slotRect, liveRect),
                          leftInsideLive: contains(liveRect, leftRect),
                          numbersInsideLive: contains(liveRect, numbersRect),
                          badgeInsideLeft: contains(leftRect, badgeRect),
                          periodInsideLeft: contains(leftRect, periodRect),
                          ballsInsideNumbers: ballRects.length === 7
                            && ballRects.every((ballRect) => contains(numbersRect, ballRect)),
                          plusInsideNumbers: contains(numbersRect, plusRect),
                          cardsHeightAligned: !!leftRect && !!numbersRect
                            && Math.abs(leftRect.height - numbersRect.height) <= 1,
                          leftNumbersOverlap: overlaps(leftRect, numbersRect),
                          liveCardOverlap: overlaps(liveRect, cardRect),
                          liveBeforeCard: !!liveRect && !!cardRect && liveRect.bottom <= cardRect.y + 1
                        },
                        filterLayout: {
                          row: filterRect,
                          selects: filterSelectRects,
                          styles: filterStyles,
                          widthsEqual: filterWidths.length === 4
                            && Math.max(...filterWidths) - Math.min(...filterWidths) <= 1,
                          heightsEqual: filterHeights.length === 4
                            && Math.max(...filterHeights) - Math.min(...filterHeights) <= 1,
                          gapsBalanced: filterGaps.length === 3
                            && Math.max(...filterGaps) - Math.min(...filterGaps) <= 1,
                          selectsInsideRow: filterSelectRects.length === 4
                            && filterSelectRects.every((filterSelectRect) => contains(filterRect, filterSelectRect)),
                          paddingBalanced: filterStyles.length === 4
                            && filterStyles.every((style) =>
                              Math.abs(parseFloat(style.paddingLeft) - parseFloat(style.paddingRight)) <= 0.5
                            ),
                          textRoomFits: filterSelectRects.length === 4
                            && filterSelectRects.every((filterSelectRect, index) => {
                              const style = filterStyles[index];
                              const availableWidth = filterSelectRect.width
                                - parseFloat(style.paddingLeft)
                                - parseFloat(style.paddingRight);
                              return availableWidth + 1 >= parseFloat(style.fontSize) * 4;
                            }),
                          formBindingsValid: filterSelects.length === 4
                            && filterSelects.every((filterSelect) =>
                              filterSelect.form && filterSelect.form.id === 'forecast-generate-form'
                            ),
                          optionsAvailable: filterSelects.length === 4
                            && filterSelects.every((filterSelect) => filterSelect.options.length > 1),
                          controlsEnabled: filterSelects.length === 4
                            && filterSelects.every((filterSelect) => !filterSelect.disabled),
                          leftInset: filterRect && filterSelectRects[0]
                            ? Number((filterSelectRects[0].x - filterRect.x).toFixed(2))
                            : null,
                          rightInset: filterRect && filterSelectRects[3]
                            ? Number((filterRect.right - filterSelectRects[3].right).toFixed(2))
                            : null,
                          gaps: filterGaps
                        },
                        resultLayout: {
                          card: resultCardRect,
                          title: resultTitleRect,
                          issueBadge: resultIssueBadgeRect,
                          issueMark: resultIssueMarkRect,
                          titleText: resultTitleTextRect,
                          scroll: resultScrollRect,
                          summary: resultSummaryRect,
                          lines: resultLineRects,
                          labels: resultLabelRects,
                          placeholders: resultPlaceholderRects,
                          lineCount: resultLines.length,
                          unselectedLineCount: resultLines.filter((line) =>
                            line.classList.contains('is-unselected')
                          ).length,
                          titleInsideCard: contains(resultCardRect, resultTitleRect),
                          scrollInsideCard: contains(resultCardRect, resultScrollRect),
                          summaryInsideScroll: contains(resultScrollRect, resultSummaryRect),
                          linesInsideSummary: resultLineRects.length === 4
                            && resultLineRects.every((lineRect) => contains(resultSummaryRect, lineRect)),
                          titleBeforeScroll: !!resultTitleRect && !!resultScrollRect
                            && resultTitleRect.bottom <= resultScrollRect.y + 1,
                          linesOrdered: rowsOrdered(resultLineRects),
                          lineHeightsBalanced: resultLineHeights.length === 4
                            && Math.max(...resultLineHeights) - Math.min(...resultLineHeights) <= 1,
                          baselineOffsets: resultBaselineOffsets,
                          contentRhythmBalanced: resultBaselineOffsets.length === 4
                            && resultBaselineOffsets.every((offset) => offset !== null)
                            && Math.max(...resultBaselineOffsets) - Math.min(...resultBaselineOffsets) <= 2.1,
                          horizontalOverflow: !!resultCard && (
                            resultCard.scrollWidth > resultCard.clientWidth + 1
                            || (resultScroll && resultScroll.scrollWidth > resultScroll.clientWidth + 1)
                            || (summary && summary.scrollWidth > summary.clientWidth + 1)
                          ),
                          variables: {
                            cardPaddingX: cssNumber(forecastPageStyle, '--forecast-result-card-padding-x'),
                            cardPaddingY: cssNumber(forecastPageStyle, '--forecast-result-card-padding-y'),
                            markSize: cssNumber(forecastPageStyle, '--forecast-result-mark-size'),
                            issueFontSize: cssNumber(forecastPageStyle, '--forecast-result-issue-font-size'),
                            titleFontSize: cssNumber(forecastPageStyle, '--forecast-result-title-font-size'),
                            labelFontSize: cssNumber(forecastPageStyle, '--forecast-result-label-font-size'),
                            placeholderFontSize: cssNumber(forecastPageStyle, '--forecast-result-placeholder-font-size'),
                            unselectedLineHeight: cssNumber(forecastPageStyle, '--forecast-result-unselected-line-height'),
                            typeChipHeight: cssNumber(forecastPageStyle, '--forecast-result-type-chip-height'),
                            chipHeight: cssNumber(forecastPageStyle, '--forecast-result-chip-height'),
                            confidenceHeight: cssNumber(forecastPageStyle, '--forecast-result-confidence-height'),
                            blessingTextSize: cssNumber(forecastPageStyle, '--forecast-result-blessing-text-size')
                          }
                        },
                        scrollWidth: html ? html.scrollWidth : 0,
                        clientWidth: html ? html.clientWidth : 0,
                        hasHorizontalOverflow: html ? html.scrollWidth > html.clientWidth + 1 : false
                      };
                    }""",
                    {"region": region},
                )
                check["status"] = response.status if response else None
                check["url"] = page.url
                check["viewportWidth"] = width
                check["selectedResultLayout"] = page.evaluate(
                    """({region}) => {
                      const card = document.querySelector('.forecast-result-card');
                      const title = card ? card.querySelector('.forecast-result-title') : null;
                      const priceStrip = card ? card.querySelector('.forecast-result-price-strip') : null;
                      const priceTotal = priceStrip
                        ? priceStrip.querySelector('.forecast-result-price-total')
                        : null;
                      const priceDiscount = priceStrip
                        ? priceStrip.querySelector('[data-forecast-price-discount]')
                        : null;
                      const scroll = card ? card.querySelector('.forecast-result-scroll') : null;
                      const summary = scroll ? scroll.querySelector('.forecast-result-summary') : null;
                      if (!card || !title || !priceStrip || !priceTotal || !priceDiscount || !scroll || !summary) {
                        return {fixtureReady: false};
                      }

                      const waveClass = (index) => ['is-green', 'is-blue', 'is-red'][index % 3];
                      const zodiacChips = ['马', '羊', '兔', '虎', '牛', '猴', '龙', '蛇', '狗']
                        .map((value) => `<span class="forecast-result-zodiac-chip">${value}</span>`)
                        .join('');
                      const numberChips = [49, 26, 29, 48, 15, 12, 2, 6, 42, 5, 41, 38, 23, 40, 16, 4, 33, 14, 36, 27, 21, 39, 13, 1, 25, 18, 28, 3, 9, 24]
                        .map((value, index) => `<span class="forecast-result-number-chip ${waveClass(index)}">${String(value).padStart(2, '0')}</span>`)
                        .join('');
                      const pingteGroups = [[37, 15, 19], [30, 38, 45], [37, 9, 1], [37, 1, 30], [37, 45, 19], [29, 22, 15], [38, 9, 29], [38, 29, 19]]
                        .map((group) => {
                          const numbers = group.map((value, index) =>
                            `<span class="forecast-result-pingte-number ${waveClass(index)}">${String(value).padStart(2, '0')}</span>`
                          ).join('<span class="forecast-result-pingte-separator">-</span>');
                          return `<span class="forecast-result-pingte-text is-group is-size-3"><span class="forecast-result-pingte-bracket">【</span>${numbers}<span class="forecast-result-pingte-bracket">】</span></span>`;
                        })
                        .join('');
                      const otherChips = ['蛇', '鸡', '鼠', '马', '虎']
                        .map((value) => `<span class="forecast-result-other-chip">${value}</span>`)
                        .join('');

                      priceStrip.hidden = false;
                      priceDiscount.hidden = false;
                      const priceValue = priceTotal.querySelector('[data-forecast-price-total]');
                      if (priceValue) priceValue.textContent = '34积分';
                      priceDiscount.textContent = '8.5折';
                      summary.innerHTML = `
                        <div class="forecast-result-summary-line is-zodiac">
                          <span class="forecast-result-summary-head"><span class="forecast-result-summary-label">生肖类型:</span></span>
                          <span class="forecast-result-summary-confidence">91.8%</span>
                          <span class="forecast-result-summary-body"><span class="forecast-result-summary-type"><span class="forecast-result-type-chip">九肖:</span></span><span class="forecast-result-summary-value">${zodiacChips}</span></span>
                        </div>
                        <div class="forecast-result-summary-line is-number">
                          <span class="forecast-result-summary-head"><span class="forecast-result-summary-label">号码类型:</span></span>
                          <span class="forecast-result-summary-confidence">91.3%</span>
                          <span class="forecast-result-summary-body"><span class="forecast-result-summary-type"><span class="forecast-result-type-chip">30码:</span></span><span class="forecast-result-summary-value">${numberChips}</span></span>
                        </div>
                        <div class="forecast-result-summary-line is-pingte">
                          <span class="forecast-result-summary-head"><span class="forecast-result-summary-label">平特一肖:</span></span>
                          <span class="forecast-result-summary-confidence">91.8%</span>
                          <span class="forecast-result-summary-body"><span class="forecast-result-summary-type"><span class="forecast-result-type-chip">8组3中3:</span></span><span class="forecast-result-summary-value">${pingteGroups}</span></span>
                        </div>
                        <div class="forecast-result-summary-line is-other">
                          <span class="forecast-result-summary-head"><span class="forecast-result-summary-label">其他类型:</span></span>
                          <span class="forecast-result-summary-confidence">92.3%</span>
                          <span class="forecast-result-summary-body"><span class="forecast-result-summary-type"><span class="forecast-result-type-chip">平特五肖:</span></span><span class="forecast-result-summary-value">${otherChips}</span></span>
                        </div>`;
                      const existingBlessing = scroll.querySelector('.forecast-result-blessing-card');
                      if (existingBlessing) existingBlessing.remove();
                      scroll.insertAdjacentHTML('beforeend', `
                        <div class="forecast-result-blessing-card">
                          <div class="forecast-result-blessing-row"><span class="forecast-result-blessing-label">☀</span><span class="forecast-result-blessing-text is-idiom">财星高照</span></div>
                          <div class="forecast-result-blessing-row"><span class="forecast-result-blessing-label">◎</span><span class="forecast-result-blessing-text">祝您喜运开起，赢钱笑口常开。</span></div>
                        </div>`);
                      card.setAttribute('data-smoke-selected-fixture', region);
                      void card.offsetHeight;

                      const blessing = scroll.querySelector('.forecast-result-blessing-card');
                      const rows = Array.from(summary.querySelectorAll(':scope > .forecast-result-summary-line'));
                      const confidenceChips = Array.from(summary.querySelectorAll('.forecast-result-summary-confidence'));
                      const typeChips = Array.from(summary.querySelectorAll('.forecast-result-type-chip'));
                      const dataChips = Array.from(summary.querySelectorAll('.forecast-result-zodiac-chip, .forecast-result-number-chip, .forecast-result-other-chip'));
                      const mark = card.querySelector('.forecast-result-issue-mark');
                      const blessingLabel = blessing ? blessing.querySelector('.forecast-result-blessing-label') : null;
                      const blessingText = blessing ? blessing.querySelector('.forecast-result-blessing-text:not(.is-idiom)') : null;
                      const rect = (element) => {
                        if (!element) return null;
                        const value = element.getBoundingClientRect();
                        return {
                          x: Number(value.x.toFixed(2)),
                          y: Number(value.y.toFixed(2)),
                          width: Number(value.width.toFixed(2)),
                          height: Number(value.height.toFixed(2)),
                          right: Number(value.right.toFixed(2)),
                          bottom: Number(value.bottom.toFixed(2))
                        };
                      };
                      const contains = (parent, child) => !!parent && !!child
                        && child.x >= parent.x - 1
                        && child.y >= parent.y - 1
                        && child.right <= parent.right + 1
                        && child.bottom <= parent.bottom + 1;
                      const rowRects = rows.map(rect);
                      const cardRect = rect(card);
                      const titleRect = rect(title);
                      const scrollRect = rect(scroll);
                      const summaryRect = rect(summary);
                      const blessingRect = rect(blessing);
                      const dimension = (element) => {
                        if (!element) return null;
                        const value = rect(element);
                        const style = getComputedStyle(element);
                        return {
                          width: value.width,
                          height: value.height,
                          fontSize: Number.parseFloat(style.fontSize)
                        };
                      };
                      const rowHeights = rowRects.map((rowRect) => rowRect.height);
                      const rowDetails = rows.map((row) => {
                        const body = row.querySelector('.forecast-result-summary-body');
                        const type = row.querySelector('.forecast-result-summary-type');
                        const value = row.querySelector('.forecast-result-summary-value');
                        const groups = value
                          ? Array.from(value.querySelectorAll('.forecast-result-pingte-text')).map(rect)
                          : [];
                        return {
                          className: row.className,
                          clientWidth: row.clientWidth,
                          scrollWidth: row.scrollWidth,
                          clientHeight: row.clientHeight,
                          scrollHeight: row.scrollHeight,
                          body: rect(body),
                          type: rect(type),
                          value: rect(value),
                          groups: groups
                        };
                      });
                      return {
                        fixtureReady: true,
                        card: cardRect,
                        title: titleRect,
                        scroll: scrollRect,
                        summary: summaryRect,
                        blessing: blessingRect,
                        rows: rowRects,
                        rowDetails: rowDetails,
                        rowCount: rows.length,
                        titleInsideCard: contains(cardRect, titleRect),
                        scrollInsideCard: contains(cardRect, scrollRect),
                        rowsInsideSummary: rowRects.length === 4
                          && rowRects.every((rowRect) => contains(summaryRect, rowRect)),
                        titleBeforeScroll: titleRect.bottom <= scrollRect.y + 1,
                        rowsOrdered: rowRects.every((rowRect, index) =>
                          index === 0 || rowRects[index - 1].bottom <= rowRect.y + 1
                        ),
                        rowContentFits: rows.every((row) =>
                          row.scrollWidth <= row.clientWidth + 1 && row.scrollHeight <= row.clientHeight + 1
                        ),
                        rowHeightsBalanced: rowHeights.length === 4
                          && Math.max(...rowHeights) - Math.min(...rowHeights) <= 1,
                        summaryBeforeBlessing: summaryRect.bottom <= blessingRect.y + 1,
                        blessingInsideScroll: contains(scrollRect, blessingRect),
                        blessingHeightBalanced: blessingRect.height <= 80
                          && blessingRect.height < summaryRect.height / 2,
                        horizontalOverflow: card.scrollWidth > card.clientWidth + 1
                          || scroll.scrollWidth > scroll.clientWidth + 1
                          || summary.scrollWidth > summary.clientWidth + 1,
                        overflowWidths: {
                          card: [card.clientWidth, card.scrollWidth],
                          scroll: [scroll.clientWidth, scroll.scrollWidth],
                          summary: [summary.clientWidth, summary.scrollWidth]
                        },
                        verticalOverflow: scroll.scrollHeight > scroll.clientHeight + 1,
                        components: {
                          mark: dimension(mark),
                          priceTotal: dimension(priceTotal),
                          priceDiscount: dimension(priceDiscount),
                          confidences: confidenceChips.map(dimension),
                          typeChips: typeChips.map(dimension),
                          dataChips: dataChips.map(dimension),
                          blessingLabel: dimension(blessingLabel),
                          blessingText: dimension(blessingText)
                        }
                      };
                    }""",
                    {"region": region},
                )
                if screenshot_dir:
                    page.wait_for_timeout(250)
                    page.locator(".forecast-result-card").screenshot(
                        path=str(screenshot_dir / f"forecast-selected-fixture-{region}-{width}.png"),
                        animations="disabled",
                    )
                    page.goto(f"{args.base_url}?region={region}", wait_until="domcontentloaded")
                    page.wait_for_selector(".forecast-live-slot #section-live", timeout=30000)
                    page.wait_for_timeout(500)
                    page.evaluate("() => document.fonts ? document.fonts.ready : Promise.resolve()")
                    page.screenshot(
                        path=str(screenshot_dir / f"forecast-{region}-{width}.png"),
                        full_page=False,
                        animations="disabled",
                    )
                check["consoleErrors"] = list(console_errors)
                check["httpErrors"] = list(http_errors)
                result["checks"].append(check)
                console_errors.clear()
                http_errors.clear()

        browser.close()

    expected_children = ["hero-live-head", "hero-live-numbers", "hero-live-meta"]
    expected_contract = {
        "liveCount": 1,
        "calendarCount": 0,
        "badgeCount": 1,
        "periodCount": 1,
        "timeCount": 1,
        "ballCount": 7,
        "plusCount": 1,
        "regionTabs": 2,
        "activeRegionTabs": 1,
        "filterSelects": 4,
        "statCards": 3,
        "generateForms": 1,
        "bottomNavs": 1,
    }
    signatures: Dict[int, Dict[str, List[str]]] = {}
    filter_signatures: Dict[int, Dict[str, Dict[str, Any]]] = {}
    result_signatures: Dict[int, Dict[str, Dict[str, Any]]] = {}
    for check in result["checks"]:
        label = f"{check['expectedRegion']}/{check['viewportWidth']}"
        if check["status"] != 200:
            result["failures"].append(f"{label}: HTTP {check['status']}")
        if check["bodyRegion"] != check["expectedRegion"]:
            result["failures"].append(f"{label}: body region mismatch")
        for key, expected in expected_contract.items():
            if check["liveContract"].get(key) != expected:
                result["failures"].append(
                    f"{label}: {key}={check['liveContract'].get(key)} expected {expected}"
                )
        children = check["liveContract"]["directChildren"]
        if children != expected_children:
            result["failures"].append(f"{label}: live children mismatch: {children}")
        signatures.setdefault(check["viewportWidth"], {})[check["expectedRegion"]] = children
        filter_layout = check["filterLayout"]
        filter_signatures.setdefault(check["viewportWidth"], {})[check["expectedRegion"]] = {
            "sizes": [
                [filter_select["width"], filter_select["height"]]
                for filter_select in filter_layout["selects"]
            ],
            "gaps": filter_layout["gaps"],
            "styles": filter_layout["styles"],
        }
        layout = check["layout"]
        for key in (
            "liveInsideSlot",
            "leftInsideLive",
            "numbersInsideLive",
            "badgeInsideLeft",
            "periodInsideLeft",
            "ballsInsideNumbers",
            "plusInsideNumbers",
            "cardsHeightAligned",
            "liveBeforeCard",
        ):
            if layout.get(key) is not True:
                result["failures"].append(f"{label}: {key} failed")
        for key in ("leftNumbersOverlap", "liveCardOverlap"):
            if layout.get(key) is not False:
                result["failures"].append(f"{label}: {key} detected")
        if check["hasHorizontalOverflow"]:
            result["failures"].append(f"{label}: horizontal overflow")
        live_height = (layout.get("live") or {}).get("height", 0)
        if not 48 <= live_height <= 56:
            result["failures"].append(f"{label}: compact live height out of range: {live_height}")
        for gap_name, gap_value in layout.get("verticalGaps", {}).items():
            if gap_value is None or abs(gap_value - 2) > 0.5:
                result["failures"].append(
                    f"{label}: {gap_name}={gap_value} expected 2px"
                )
        result_layout = check["resultLayout"]
        result_signatures.setdefault(check["viewportWidth"], {})[check["expectedRegion"]] = {
            "variables": result_layout.get("variables", {}),
            "unselectedRowHeights": [row.get("height") for row in result_layout.get("lines", [])],
            "selectedRowHeights": [
                row.get("height")
                for row in check.get("selectedResultLayout", {}).get("rows", [])
            ],
            "selectedComponents": check.get("selectedResultLayout", {}).get("components", {}),
        }
        if result_layout.get("lineCount") != 4 or result_layout.get("unselectedLineCount") != 4:
            result["failures"].append(
                f"{label}: unselected result rows are not the expected four-row structure"
            )
        for key in (
            "titleInsideCard",
            "scrollInsideCard",
            "summaryInsideScroll",
            "linesInsideSummary",
            "titleBeforeScroll",
            "linesOrdered",
            "lineHeightsBalanced",
            "contentRhythmBalanced",
        ):
            if result_layout.get(key) is not True:
                result["failures"].append(f"{label}: result {key} failed")
        if result_layout.get("horizontalOverflow"):
            result["failures"].append(f"{label}: result card horizontal overflow")

        if check["viewportWidth"] > 720:
            expected_result_variables = {
                "cardPaddingX": 12,
                "cardPaddingY": 6,
                "markSize": 24,
                "issueFontSize": 14,
                "titleFontSize": 19,
                "labelFontSize": 14,
                "placeholderFontSize": 13,
                "unselectedLineHeight": 22,
                "typeChipHeight": 25,
                "chipHeight": 25,
                "confidenceHeight": 26,
                "blessingTextSize": 14.5,
            }
            expected_price_height = 30
            expected_blessing_label_size = 17
        elif check["viewportWidth"] > 390:
            expected_result_variables = {
                "cardPaddingX": 10,
                "cardPaddingY": 6,
                "markSize": 23,
                "issueFontSize": 13.4,
                "titleFontSize": 18,
                "labelFontSize": 13.5,
                "placeholderFontSize": 12.8,
                "unselectedLineHeight": 22,
                "typeChipHeight": 24,
                "chipHeight": 24,
                "confidenceHeight": 25,
                "blessingTextSize": 13.8,
            }
            expected_price_height = 28
            expected_blessing_label_size = 16
        else:
            expected_result_variables = {
                "cardPaddingX": 9,
                "cardPaddingY": 6,
                "markSize": 22,
                "issueFontSize": 12.8,
                "titleFontSize": 17,
                "labelFontSize": 13,
                "placeholderFontSize": 12.4,
                "unselectedLineHeight": 21,
                "typeChipHeight": 23,
                "chipHeight": 23,
                "confidenceHeight": 24,
                "blessingTextSize": 13,
            }
            expected_price_height = 27
            expected_blessing_label_size = 15
        for variable_name, expected_value in expected_result_variables.items():
            actual_value = result_layout.get("variables", {}).get(variable_name)
            if actual_value is None or abs(actual_value - expected_value) > 0.1:
                result["failures"].append(
                    f"{label}: result variable {variable_name}={actual_value} expected {expected_value}px"
                )

        selected_layout = check.get("selectedResultLayout", {})
        if selected_layout.get("fixtureReady") is not True:
            result["failures"].append(f"{label}: selected result fixture was not created")
        else:
            if selected_layout.get("rowCount") != 4:
                result["failures"].append(f"{label}: selected result row count mismatch")
            for key in (
                "titleInsideCard",
                "scrollInsideCard",
                "rowsInsideSummary",
                "titleBeforeScroll",
                "rowsOrdered",
                "rowContentFits",
                "summaryBeforeBlessing",
                "blessingInsideScroll",
                "blessingHeightBalanced",
            ):
                if selected_layout.get(key) is not True:
                    result["failures"].append(f"{label}: selected result {key} failed")
            if selected_layout.get("horizontalOverflow"):
                result["failures"].append(f"{label}: selected result horizontal overflow")
            if selected_layout.get("verticalOverflow"):
                result["failures"].append(f"{label}: selected result vertical overflow")

            components = selected_layout.get("components", {})
            mark = components.get("mark") or {}
            for dimension_name in ("width", "height"):
                if abs(mark.get(dimension_name, 0) - expected_result_variables["markSize"]) > 0.5:
                    result["failures"].append(
                        f"{label}: selected mark {dimension_name} does not match its variable"
                    )
            for component_name in ("priceTotal", "priceDiscount"):
                component = components.get(component_name) or {}
                if abs(component.get("height", 0) - expected_price_height) > 0.5:
                    result["failures"].append(
                        f"{label}: selected {component_name} height does not match its variable"
                    )
            for component_name, expected_count, expected_height in (
                ("confidences", 4, expected_result_variables["confidenceHeight"]),
                ("typeChips", 4, expected_result_variables["typeChipHeight"]),
                ("dataChips", 44, expected_result_variables["chipHeight"]),
            ):
                components_list = components.get(component_name, [])
                if len(components_list) != expected_count or any(
                    abs(component.get("height", 0) - expected_height) > 0.5
                    for component in components_list
                ):
                    result["failures"].append(
                        f"{label}: selected {component_name} dimensions do not match the shared variables"
                    )
            blessing_label = components.get("blessingLabel") or {}
            blessing_text = components.get("blessingText") or {}
            if abs(blessing_label.get("fontSize", 0) - expected_blessing_label_size) > 0.1:
                result["failures"].append(f"{label}: selected blessing label font mismatch")
            if abs(
                blessing_text.get("fontSize", 0)
                - expected_result_variables["blessingTextSize"]
            ) > 0.1:
                result["failures"].append(f"{label}: selected blessing text font mismatch")
        for key in (
            "widthsEqual",
            "heightsEqual",
            "gapsBalanced",
            "selectsInsideRow",
            "paddingBalanced",
            "textRoomFits",
            "formBindingsValid",
            "optionsAvailable",
            "controlsEnabled",
        ):
            if filter_layout.get(key) is not True:
                result["failures"].append(f"{label}: filter {key} failed")
        left_inset = filter_layout.get("leftInset")
        right_inset = filter_layout.get("rightInset")
        if left_inset is None or right_inset is None or abs(left_inset - right_inset) > 1:
            result["failures"].append(
                f"{label}: filter side insets unbalanced: {left_inset}/{right_inset}"
            )
        expected_filter_height = 32 if check["viewportWidth"] > 720 else (
            30 if check["viewportWidth"] > 390 else 29
        )
        if any(
            abs(filter_select["height"] - expected_filter_height) > 0.5
            for filter_select in filter_layout["selects"]
        ):
            result["failures"].append(
                f"{label}: filter height does not match {expected_filter_height}px"
            )
        if check["consoleErrors"]:
            result["failures"].append(f"{label}: console errors: {check['consoleErrors']}")
        if check["httpErrors"]:
            result["failures"].append(f"{label}: HTTP resource errors: {check['httpErrors']}")

    for width, region_signatures in signatures.items():
        if region_signatures.get("macau") != region_signatures.get("hongkong"):
            result["failures"].append(f"{width}: Macau/Hong Kong structure mismatch")

    for width, region_signatures in filter_signatures.items():
        if region_signatures.get("macau") != region_signatures.get("hongkong"):
            result["failures"].append(f"{width}: Macau/Hong Kong filter layout mismatch")

    for width, region_signatures in result_signatures.items():
        if region_signatures.get("macau") != region_signatures.get("hongkong"):
            result["failures"].append(f"{width}: Macau/Hong Kong result layout mismatch")

    result["passed"] = not result["failures"]

    text = json.dumps(result, ensure_ascii=False, indent=2)
    if args.output:
        with open(args.output, "w", encoding="utf-8") as handle:
            handle.write(text + "\n")
    else:
        print(text)
    return 0 if result["passed"] else 1


if __name__ == "__main__":
    sys.exit(main())
