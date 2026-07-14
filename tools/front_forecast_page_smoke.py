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
                      const primaryCard = document.querySelector('.forecast-card-primary');
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
                      const liveRect = rect(live);
                      const slotRect = rect(liveSlot);
                      const leftRect = rect(liveLeft);
                      const numbersRect = rect(liveNumbers);
                      const badgeRect = rect(liveBadge);
                      const periodRect = rect(livePeriod);
                      const ballRects = liveBalls.map(rect);
                      const plusRect = rect(livePlus);
                      const cardRect = rect(primaryCard);
                      const filterRect = rect(filter);
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
                          primaryCard: cardRect,
                          bottomNav: rect(bottomNav),
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
                check["consoleErrors"] = list(console_errors)
                check["httpErrors"] = list(http_errors)
                result["checks"].append(check)
                if screenshot_dir:
                    page.screenshot(
                        path=str(screenshot_dir / f"forecast-{region}-{width}.png"),
                        full_page=False,
                    )
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
