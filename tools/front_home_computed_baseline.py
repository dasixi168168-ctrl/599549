#!/usr/bin/env python3
"""Capture public home computed-style baselines from a real browser."""

import argparse
import json
import sys
from typing import Any, Dict, Iterable, List

try:
    from playwright.sync_api import sync_playwright
except ImportError as exc:
    raise SystemExit(
        "Playwright is not installed. Run: python3 -m pip install --user playwright && "
        "python3 -m playwright install chromium"
    ) from exc


DEFAULT_PROPS = [
    "display",
    "position",
    "boxSizing",
    "width",
    "height",
    "maxWidth",
    "maxHeight",
    "minWidth",
    "minHeight",
    "paddingTop",
    "paddingRight",
    "paddingBottom",
    "paddingLeft",
    "marginTop",
    "marginRight",
    "marginBottom",
    "marginLeft",
    "overflowX",
    "overflowY",
    "alignItems",
    "alignContent",
    "justifyItems",
    "justifyContent",
    "justifySelf",
    "gap",
    "columnGap",
    "rowGap",
    "gridTemplateColumns",
    "gridTemplateRows",
    "flex",
    "flexDirection",
    "flexWrap",
    "borderRadius",
    "borderTopWidth",
    "borderRightWidth",
    "borderBottomWidth",
    "borderLeftWidth",
    "backgroundColor",
    "backgroundImage",
    "backgroundPosition",
    "backgroundRepeat",
    "backgroundSize",
    "boxShadow",
    "filter",
    "transform",
    "fontSize",
    "fontWeight",
    "lineHeight",
    "letterSpacing",
    "whiteSpace",
    "textAlign",
    "color",
]

SELECTORS = {
    "body": "body",
    "pageFrame": ".page-frame",
    "hero": ".page-frame > #section-home.hero-banner",
    "heroInner": ".page-frame > #section-home.hero-banner > .max-w-7xl",
    "heroLiveInside": ".page-frame > #section-home.hero-banner #section-live.hero-live-box",
    "live": "body:not(.forecast-panel-page) .page-frame > #section-live.hero-live-box",
    "liveHead": "body:not(.forecast-panel-page) .page-frame > #section-live.hero-live-box .hero-live-head",
    "liveLeft": "body:not(.forecast-panel-page) .page-frame > #section-live.hero-live-box .hero-live-left",
    "liveBadge": "body:not(.forecast-panel-page) .page-frame > #section-live.hero-live-box .hero-live-badge",
    "livePeriod": "body:not(.forecast-panel-page) .page-frame > #section-live.hero-live-box .hero-live-period",
    "liveTime": "body:not(.forecast-panel-page) .page-frame > #section-live.hero-live-box .hero-live-time",
    "liveNumbers": "body:not(.forecast-panel-page) .page-frame > #section-live.hero-live-box .hero-live-numbers",
    "liveBallItem": "body:not(.forecast-panel-page) .page-frame > #section-live.hero-live-box .hero-ball-item",
    "liveBallCode": "body:not(.forecast-panel-page) .page-frame > #section-live.hero-live-box .result-jl-code",
    "liveBallZodiac": "body:not(.forecast-panel-page) .page-frame > #section-live.hero-live-box .hero-ball-zodiac",
    "livePlus": "body:not(.forecast-panel-page) .page-frame > #section-live.hero-live-box .result-jl-plus",
    "liveMeta": "body:not(.forecast-panel-page) .page-frame > #section-live.hero-live-box .hero-live-meta",
    "calendar": ".calendar-panel",
    "sectionTitle": ".section-title",
    "dataFrame": ".data-frame",
    "expertCard": ".expert-item-card",
    "bottomNav": ".bottom-float-nav",
    "bottomNavLink": ".bottom-float-nav .bottom-nav-link",
}


def parse_widths(values: Iterable[str]) -> List[int]:
    widths: List[int] = []
    for value in values:
        for part in value.split(","):
            part = part.strip()
            if part:
                widths.append(int(part))
    return widths or [1024, 640, 390]


def capture_for_width(page: Any, width: int, height: int, props: List[str]) -> Dict[str, Any]:
    page.set_viewport_size({"width": width, "height": height})
    page.wait_for_timeout(300)
    return page.evaluate(
        """({props, selectors}) => {
          const html = document.documentElement;
          const result = {
            page: {
              scrollWidth: html ? html.scrollWidth : 0,
              clientWidth: html ? html.clientWidth : 0,
              bodyRegion: document.body ? document.body.getAttribute("data-region") : null,
              hasHorizontalOverflow: html ? html.scrollWidth > html.clientWidth + 1 : false
            },
            selectors: {}
          };
          for (const [name, selector] of Object.entries(selectors)) {
            const el = document.querySelector(selector);
            if (!el) {
              result.selectors[name] = null;
              continue;
            }
            const style = getComputedStyle(el);
            const rect = el.getBoundingClientRect();
            const item = {rect: {
              x: Number(rect.x.toFixed(3)),
              y: Number(rect.y.toFixed(3)),
              width: Number(rect.width.toFixed(3)),
              height: Number(rect.height.toFixed(3))
            }};
            for (const prop of props)
              item[prop] = style[prop];
            result.selectors[name] = item;
          }
          return result;
        }""",
        {"props": props, "selectors": SELECTORS},
    )


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", default="http://127.0.0.1:18080/public/index.php")
    parser.add_argument("--macau-url", help="Override URL for the Macau homepage")
    parser.add_argument("--hongkong-url", help="Override URL for the Hong Kong homepage")
    parser.add_argument("--width", action="append", default=[], help="Viewport width; repeat or comma-separate")
    parser.add_argument("--height", type=int, default=900)
    parser.add_argument("--output", help="Write JSON to this file")
    args = parser.parse_args()

    widths = parse_widths(args.width)
    result: Dict[str, Any] = {"base_url": args.base_url, "viewports": {}}

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(
            headless=True,
            args=["--no-sandbox", "--disable-dev-shm-usage", "--disable-gpu"],
        )
        page = browser.new_page(viewport={"width": widths[0], "height": args.height})
        page.set_extra_http_headers({"X-Forwarded-Proto": "https"})
        console_errors: List[str] = []
        page.on(
            "console",
            lambda message: console_errors.append(message.text) if message.type == "error" else None,
        )
        page.on("pageerror", lambda error: console_errors.append(str(error)))

        region_urls = {
            "macau": args.macau_url or args.base_url,
            "hongkong": args.hongkong_url or args.base_url,
        }

        for region in ["macau", "hongkong"]:
            result["viewports"][region] = {}
            for width in widths:
                page.set_viewport_size({"width": width, "height": args.height})
                target_url = region_urls[region]
                separator = "&" if "?" in target_url else "?"
                response = page.goto(f"{target_url}{separator}region={region}", wait_until="domcontentloaded")
                item = capture_for_width(page, width, args.height, DEFAULT_PROPS)
                item["status"] = response.status if response else None
                item["url"] = page.url
                item["consoleErrors"] = list(console_errors)
                result["viewports"][region][str(width)] = item
                console_errors.clear()

        browser.close()

    text = json.dumps(result, ensure_ascii=False, indent=2)
    if args.output:
        with open(args.output, "w", encoding="utf-8") as handle:
            handle.write(text + "\n")
    else:
        print(text)
    return 0


if __name__ == "__main__":
    sys.exit(main())
