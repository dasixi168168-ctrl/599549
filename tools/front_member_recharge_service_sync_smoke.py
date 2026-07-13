#!/usr/bin/env python3
"""Smoke-check member recharge service modal header sync."""

import argparse
import json
import sys
from typing import Any, Dict, List

try:
    from playwright.sync_api import sync_playwright
except ImportError as exc:
    raise SystemExit(
        "Playwright is not installed. Run: python3 -m pip install --user playwright && "
        "python3 -m playwright install chromium"
    ) from exc


def html(region: str) -> str:
    return f"""<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body class="standalone-modal-post front-unified-panel-page" data-region="{region}">
  <a id="service-link" href="about:blank?embed=1" data-member-recharge-service>联系客服确认充值</a>
  <script src="/public/assets/app.js?v=front-member-recharge-service-sync-smoke"></script>
</body>
</html>"""


def run_case(page: Any, base_url: str, region: str) -> Dict[str, Any]:
    console_errors: List[str] = []
    page.on("console", lambda message: console_errors.append(message.text) if message.type == "error" else None)
    page.on("pageerror", lambda error: console_errors.append(str(error)))
    page.goto(f"{base_url}/public/index.php?region={region}", wait_until="domcontentloaded")
    page.set_content(html(region), wait_until="load")
    page.click("#service-link")
    page.wait_for_selector("[data-member-recharge-service-modal]:not([hidden]) [data-member-recharge-service-frame]")
    result = page.evaluate(
        """() => {
          const modal = document.querySelector('[data-member-recharge-service-modal]');
          const frame = modal ? modal.querySelector('[data-member-recharge-service-frame]') : null;
          if (!modal || !frame || !frame.contentDocument)
            return { ok: false, reason: 'modal-or-frame-missing' };

          frame.contentDocument.open();
          frame.contentDocument.write(`<!doctype html>
            <html><body>
              <section class="service-thread service-thread--member">
                <div class="service-thread-head">
                  <div class="service-thread-peer">
                    <div class="service-thread-avatar">
                      <span class="service-thread-avatar-state" data-customer-service-avatar-status data-status-type="voice">语音中</span>
                    </div>
                    <div class="service-thread-peer-copy">
                      <div class="service-thread-title">新版客服标题</div>
                      <div class="service-thread-hours">接待时间：10:00-20:00</div>
                    </div>
                  </div>
                  <button type="button" data-customer-service-clear>清除</button>
                </div>
              </section>
            </body></html>`);
          frame.contentDocument.close();
          frame.dispatchEvent(new Event('load'));

          const title = modal.querySelector('[data-member-recharge-service-title]');
          const hours = modal.querySelector('[data-member-recharge-service-hours]');
          const state = modal.querySelector('[data-member-recharge-service-state]');
          const clear = modal.querySelector('[data-member-recharge-service-clear]');
          return {
            ok: true,
            title: title ? title.textContent.trim() : '',
            hours: hours ? hours.textContent.trim() : '',
            state: state ? state.textContent.trim() : '',
            stateType: state ? state.getAttribute('data-status-type') : '',
            stateHidden: state ? state.hidden : true,
            clearHidden: clear ? clear.hidden : true
          };
        }"""
    )
    result["region"] = region
    result["consoleErrors"] = console_errors
    return result


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", default="http://127.0.0.1:18112")
    parser.add_argument("--output", help="Write JSON to this file")
    args = parser.parse_args()

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(
            headless=True,
            args=["--no-sandbox", "--disable-dev-shm-usage", "--disable-gpu"],
        )
        try:
            page = browser.new_page(viewport={"width": 390, "height": 900})
            page.set_extra_http_headers({"X-Forwarded-Proto": "https"})
            checks = [run_case(page, args.base_url.rstrip("/"), region) for region in ["macau", "hongkong"]]
        finally:
            browser.close()

    data = {"base_url": args.base_url, "checks": checks}
    text = json.dumps(data, ensure_ascii=False, indent=2)
    if args.output:
        with open(args.output, "w", encoding="utf-8") as handle:
            handle.write(text + "\n")
    else:
        print(text)
    return 0


if __name__ == "__main__":
    sys.exit(main())
