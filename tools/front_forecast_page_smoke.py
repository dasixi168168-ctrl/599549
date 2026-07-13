#!/usr/bin/env python3
"""Smoke-check the front forecast page in a real browser."""

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
    args = parser.parse_args()

    widths = parse_widths(args.width)
    result: Dict[str, Any] = {"base_url": args.base_url, "checks": []}

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
            for width in widths:
                page.set_viewport_size({"width": width, "height": args.height})
                response = page.goto(f"{args.base_url}?region={region}", wait_until="domcontentloaded")
                check = page.evaluate(
                    """({region}) => {
                      const html = document.documentElement;
                      const body = document.body;
                      const forecastPage = document.querySelector('.forecast-page');
                      const filter = document.querySelector('.forecast-filter-row');
                      const summary = document.querySelector('.forecast-result-summary');
                      const blessing = document.querySelector('.forecast-result-blessing-card');
                      return {
                        expectedRegion: region,
                        bodyRegion: body ? body.getAttribute('data-region') : '',
                        title: document.title,
                        hasForecastPage: !!forecastPage,
                        hasFilter: !!filter,
                        hasSummary: !!summary,
                        hasBlessing: !!blessing,
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
                result["checks"].append(check)
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
