#!/usr/bin/env python3
"""Smoke-test the post detail login modal in a real browser."""

import argparse
import json
import sys
from typing import Any, Dict, Iterable, List, Tuple

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


def parse_cases(values: Iterable[str]) -> List[Tuple[str, int]]:
    cases: List[Tuple[str, int]] = []
    for value in values:
        for part in value.split(","):
            part = part.strip()
            if not part:
                continue
            region, sep, post_id = part.partition(":")
            if sep != ":" or region not in {"macau", "hongkong"}:
                raise SystemExit(f"Invalid case {part!r}; expected macau:123 or hongkong:123")
            cases.append((region, int(post_id)))
    return cases


def inspect_page(page: Any) -> Dict[str, Any]:
    return page.evaluate(
        """() => {
          const html = document.documentElement;
          const body = document.body;
          const modal = document.querySelector("[data-front-post-login-modal]");
          const card = document.querySelector(".front-post-login-card");
          const style = modal ? getComputedStyle(modal) : null;
          const bodyStyle = body ? getComputedStyle(body) : null;
          const rect = card ? card.getBoundingClientRect() : null;
          return {
            url: location.href,
            bodyRegion: body ? body.getAttribute("data-region") : null,
            bodyClass: body ? body.className : "",
            bodyOverflow: bodyStyle ? bodyStyle.overflow : null,
            hasOpenButton: !!document.querySelector("[data-front-post-login-open]"),
            modalExists: !!modal,
            modalHidden: modal ? modal.hidden : null,
            modalDisplay: style ? style.display : null,
            modalOpacity: style ? style.opacity : null,
            modalPointerEvents: style ? style.pointerEvents : null,
            cardRect: rect ? {
              x: Number(rect.x.toFixed(3)),
              y: Number(rect.y.toFixed(3)),
              width: Number(rect.width.toFixed(3)),
              height: Number(rect.height.toFixed(3))
            } : null,
            hasHorizontalOverflow: html ? html.scrollWidth > html.clientWidth + 1 : false,
            scrollWidth: html ? html.scrollWidth : 0,
            clientWidth: html ? html.clientWidth : 0
          };
        }"""
    )


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", default="http://127.0.0.1:18080/public/post.php")
    parser.add_argument("--case", action="append", default=[], help="region:post_id; repeat or comma-separate")
    parser.add_argument("--width", action="append", default=[], help="Viewport width; repeat or comma-separate")
    parser.add_argument("--height", type=int, default=900)
    parser.add_argument("--output", help="Write JSON to this file")
    args = parser.parse_args()

    cases = parse_cases(args.case)
    if not cases:
        raise SystemExit("At least one --case region:post_id is required")
    widths = parse_widths(args.width)
    result: Dict[str, Any] = {"base_url": args.base_url, "cases": {}}

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(
            headless=True,
            args=["--no-sandbox", "--disable-dev-shm-usage", "--disable-gpu"],
        )
        page = browser.new_page(viewport={"width": widths[0], "height": args.height})
        page.set_extra_http_headers({"X-Forwarded-Proto": "https"})

        for region, post_id in cases:
            case_key = f"{region}:{post_id}"
            result["cases"][case_key] = {}
            for width in widths:
                console_errors: List[str] = []
                page.on(
                    "console",
                    lambda message: console_errors.append(message.text)
                    if message.type == "error"
                    else None,
                )
                page.on("pageerror", lambda error: console_errors.append(str(error)))
                page.set_viewport_size({"width": width, "height": args.height})
                response = page.goto(
                    f"{args.base_url}?id={post_id}&region={region}&modal=1",
                    wait_until="domcontentloaded",
                )
                page.wait_for_timeout(350)
                button = page.query_selector("[data-front-post-login-open]")
                if button:
                    page.evaluate("(button) => button.click()", button)
                    page.wait_for_timeout(250)
                item = inspect_page(page)
                item["status"] = response.status if response else None
                item["consoleErrors"] = console_errors
                result["cases"][case_key][str(width)] = item

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
