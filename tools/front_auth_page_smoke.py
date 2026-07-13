#!/usr/bin/env python3
"""Smoke-check front member auth pages in a real browser."""

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
    parser.add_argument("--base-url", default="http://127.0.0.1:18112/public/member.php")
    parser.add_argument("--width", action="append", default=[])
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
        page.on("console", lambda message: console_errors.append(message.text) if message.type == "error" else None)
        page.on("pageerror", lambda error: console_errors.append(str(error)))

        for region in ["macau", "hongkong"]:
            for mode in ["login", "register", "reset"]:
                page.set_viewport_size({"width": widths[0], "height": args.height})
                response = page.goto(
                    f"{args.base_url}?region={region}&mode={mode}",
                    wait_until="domcontentloaded",
                )
                for width in widths:
                    page.set_viewport_size({"width": width, "height": args.height})
                    page.wait_for_timeout(120)
                    check = page.evaluate(
                        """({region, mode}) => {
                          const html = document.documentElement;
                          const body = document.body;
                          const frame = document.querySelector('.member-auth-frame');
                          const card = document.querySelector('.member-auth-card');
                          const activeTab = document.querySelector('.member-auth-tab.is-active');
                          const submit = document.querySelector('.member-auth-submit');
                          return {
                            expectedRegion: region,
                            expectedMode: mode,
                            bodyRegion: body ? body.getAttribute('data-region') : '',
                            hasFrame: !!frame,
                            hasCard: !!card,
                            hasActiveTab: !!activeTab,
                            activeTabText: activeTab ? activeTab.textContent.trim() : '',
                            hasSubmit: !!submit,
                            scrollWidth: html ? html.scrollWidth : 0,
                            clientWidth: html ? html.clientWidth : 0,
                            hasHorizontalOverflow: html ? html.scrollWidth > html.clientWidth + 1 : false
                          };
                        }""",
                        {"region": region, "mode": mode},
                    )
                    check["status"] = response.status if response else None
                    check["url"] = page.url
                    check["viewportWidth"] = width
                    check["consoleErrors"] = list(console_errors)
                    result["checks"].append(check)
                    console_errors.clear()
                page.wait_for_timeout(1100)

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
