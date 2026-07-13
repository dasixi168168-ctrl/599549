#!/usr/bin/env python3
"""Capture front history page computed-style baselines from a real browser."""

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
    "zIndex",
    "boxSizing",
    "width",
    "height",
    "maxWidth",
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
    "borderTopColor",
    "borderRightColor",
    "borderBottomColor",
    "borderLeftColor",
    "backgroundColor",
    "backgroundImage",
    "boxShadow",
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
    "page": ".history-page",
    "sectionTitle": ".history-section-title",
    "sectionTitleMain": ".history-section-title-main",
    "backButton": ".history-list-back-btn",
    "frame": ".history-frame",
    "listStack": ".history-list-stack",
    "drawCard": ".history-draw-card",
    "drawCardHead": ".history-draw-card-head",
    "drawIssue": ".history-draw-issue",
    "drawDate": ".history-draw-date",
    "drawRows": ".history-draw-rows",
    "drawItems": ".history-draw-items.history-live-numbers",
    "ballItem": ".history-draw-items.history-live-numbers .hero-ball-item",
    "ballCode": ".history-draw-items.history-live-numbers .result-jl-code",
    "ballZodiac": ".history-draw-items.history-live-numbers .hero-ball-zodiac",
    "ballPlus": ".history-draw-items.history-live-numbers .hero-ball-plus",
    "resultPlus": ".history-draw-items.history-live-numbers .result-jl-plus",
    "bottomNav": ".bottom-float-nav",
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
    page.wait_for_timeout(350)
    return page.evaluate(
        """({props, selectors}) => {
          const html = document.documentElement;
          const body = document.body;
          const result = {
            page: {
              scrollWidth: html ? html.scrollWidth : 0,
              clientWidth: html ? html.clientWidth : 0,
              bodyRegion: body ? body.getAttribute("data-region") : null,
              hasHorizontalOverflow: html ? html.scrollWidth > html.clientWidth + 1 : false,
              historyCardCount: document.querySelectorAll(".history-draw-card").length
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
    parser.add_argument("--base-url", default="http://127.0.0.1:18080/public/history.php")
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

        for region in ["macau", "hongkong"]:
            result["viewports"][region] = {}
            for width in widths:
                response = page.goto(f"{args.base_url}?region={region}", wait_until="domcontentloaded")
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
