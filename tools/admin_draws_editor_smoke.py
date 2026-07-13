#!/usr/bin/env python3
"""Smoke-test the managed draw material editor in a real browser."""

import argparse
import json
import sys
from typing import Any, Dict, List

from playwright.sync_api import sync_playwright

from admin_dashboard_computed_baseline import login


def collect_region(page: Any, base_url: str, region: str) -> Dict[str, Any]:
    response = page.goto(
        f"{base_url}/public/admin.php?page=draws&mode=material&region={region}",
        wait_until="networkidle",
        timeout=45000,
    )
    iframe_el = page.wait_for_selector("iframe.tox-edit-area__iframe", timeout=30000)
    frame = iframe_el.content_frame()
    if frame is None:
        raise RuntimeError("TinyMCE iframe not available")
    frame.wait_for_selector("body.admin-draw-preview", timeout=30000)
    page.wait_for_timeout(1500)

    metrics = frame.evaluate(
        """() => {
          const directControlCount = (el) => Array.from(el ? el.children : []).filter((child) => {
            return child.classList && child.classList.contains('editor-section-floating-handle-row');
          }).length;
          const elementState = (selector) => {
            const el = document.querySelector(selector);
            if (!el) {
              return { selector, exists: false };
            }
            const style = window.getComputedStyle(el);
            const rect = el.getBoundingClientRect();
            return {
              selector,
              exists: true,
              display: style.display,
              visibility: style.visibility,
              width: Number(rect.width.toFixed(3)),
              height: Number(rect.height.toFixed(3)),
              text: (el.textContent || '').replace(/\\s+/g, ' ').trim().slice(0, 80)
            };
          };
          const protectedItems = ['#section-home', '#section-live.hero-live-box', '.marquee', 'section.calendar-panel'].map((selector) => {
            const el = document.querySelector(selector);
            return {
              selector,
              exists: !!el,
              contenteditable: el ? el.getAttribute('contenteditable') : null,
              directControls: directControlCount(el),
              className: el ? el.className : ''
            };
          });
          const editableSections = Array.from(document.querySelectorAll('section')).filter((section) => {
            if (section.id === 'section-home') return false;
            if (section.classList.contains('calendar-panel')) return false;
            return !!section.querySelector(':scope > .editor-section-floating-handle-row');
          });
          const vip = document.querySelector('#home-vip-title');
          const vipSection = vip ? vip.closest('section') : null;
          const doc = document.documentElement;
          const scroller = document.scrollingElement || doc;
          const originalScrollLeft = scroller.scrollLeft;
          scroller.scrollLeft = 9999;
          const horizontalScrollLeft = scroller.scrollLeft;
          scroller.scrollLeft = originalScrollLeft;
          const viewportWidth = window.innerWidth || doc.clientWidth;
          const layoutWidth = Math.max(doc.clientWidth, viewportWidth, document.body.clientWidth || 0);
          const scrollWidthOverflow = doc.scrollWidth > doc.clientWidth + 1 || doc.scrollWidth > layoutWidth + 1;
          const widestElements = Array.from(document.body.querySelectorAll('*')).map((el) => {
            const rect = el.getBoundingClientRect();
            return {
              tag: el.tagName.toLowerCase(),
              id: el.id || '',
              className: typeof el.className === 'string' ? el.className : '',
              width: Number(rect.width.toFixed(3)),
              right: Number(rect.right.toFixed(3)),
              text: (el.textContent || '').replace(/\\s+/g, ' ').trim().slice(0, 40)
            };
          }).filter((item) => item.width > 0).sort((a, b) => b.right - a.right).slice(0, 10);
          return {
            bodyClass: document.body.className,
            scrollWidth: doc.scrollWidth,
            clientWidth: doc.clientWidth,
            viewportWidth,
            bodyClientWidth: document.body.clientWidth,
            bodyWidth: Number(document.body.getBoundingClientRect().width.toFixed(3)),
            horizontalScrollLeft,
            hasHorizontalOverflow: scrollWidthOverflow && horizontalScrollLeft > 1,
            legacyClientWidthOverflow: doc.scrollWidth > doc.clientWidth + 1,
            widestElements,
            liveHead: elementState('#section-live .hero-live-head'),
            liveLeft: elementState('#section-live .hero-live-left'),
            liveBadge: elementState('#section-live .hero-live-badge'),
            livePeriod: elementState('#section-live .hero-live-period'),
            liveTime: elementState('#section-live .hero-live-time'),
            totalControlRows: document.querySelectorAll('.editor-section-floating-handle-row').length,
            protectedItems,
            editableSectionCount: editableSections.length,
            editableTitles: editableSections.slice(0, 8).map((section) => {
              const title = section.querySelector(':scope > .section-title');
              return title ? title.textContent.replace(/\\s+/g, ' ').trim() : section.textContent.replace(/\\s+/g, ' ').trim().slice(0, 24);
            }),
            vipExists: !!vip,
            vipDirectControls: directControlCount(vipSection),
            vipSectionClass: vipSection ? vipSection.className : '',
            vipWrapClass: vip && vip.parentElement ? vip.parentElement.className : '',
            hasBottomNav: !!document.querySelector('nav.bottom-float-nav'),
            hasTopBar: !!document.querySelector('header.top-bar')
          };
        }"""
    )

    return {
        "status": response.status if response else None,
        "url": page.url,
        "metrics": metrics,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--base-url", default="http://599549.com")
    parser.add_argument("--username", default="admin")
    parser.add_argument("--password", default="123456")
    parser.add_argument("--width", type=int, default=1220)
    parser.add_argument("--height", type=int, default=900)
    args = parser.parse_args()

    result: Dict[str, Any] = {"baseUrl": args.base_url, "regions": {}, "consoleErrors": {}}
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(
            headless=True,
            args=["--no-sandbox", "--disable-dev-shm-usage", "--disable-gpu"],
        )
        try:
            for region in ["macau", "hongkong"]:
                page = browser.new_page(
                    viewport={"width": args.width, "height": args.height},
                    extra_http_headers={"X-Forwarded-Proto": "https"},
                )
                console_messages: List[Dict[str, str]] = []
                page.on("console", lambda msg: console_messages.append({"type": msg.type, "text": msg.text}))
                page.on("pageerror", lambda exc: console_messages.append({"type": "pageerror", "text": str(exc)}))
                login(page, args.base_url.rstrip("/"), args.username, args.password)
                result["regions"][region] = collect_region(page, args.base_url.rstrip("/"), region)
                result["consoleErrors"][region] = [
                    item for item in console_messages if item["type"] in ("error", "pageerror")
                ]
                page.close()
        finally:
            browser.close()

    print(json.dumps(result, ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
