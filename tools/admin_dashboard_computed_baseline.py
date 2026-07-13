#!/usr/bin/env python3
"""Capture backend dashboard computed-style baselines with a real browser."""

import argparse
import json
import pathlib
import sys
from typing import Any, Dict, Iterable, List, Optional

try:
    from playwright.sync_api import TimeoutError as PlaywrightTimeoutError
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
    "minWidth",
    "minHeight",
    "maxWidth",
    "maxHeight",
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
    "gridTemplateColumns",
    "gridTemplateRows",
    "flex",
    "flexBasis",
    "flexDirection",
    "borderRadius",
    "borderTopColor",
    "borderTopWidth",
    "borderTopStyle",
    "borderRightColor",
    "borderRightWidth",
    "borderRightStyle",
    "borderBottomColor",
    "borderBottomWidth",
    "borderBottomStyle",
    "borderLeftColor",
    "borderLeftWidth",
    "borderLeftStyle",
    "backgroundColor",
    "backgroundImage",
    "boxShadow",
    "color",
    "fontSize",
    "fontWeight",
    "lineHeight",
    "letterSpacing",
    "whiteSpace",
    "textOverflow",
    "opacity",
    "pointerEvents",
    "transform",
    "transitionProperty",
    "transitionDuration",
]

SELECTORS = {
    "body": "body",
    "adminShell": ".admin-shell",
    "adminStage": ".admin-stage",
    "frameHeader": ".admin-frame-header",
    "mobileNavToggle": ".admin-mobile-nav-toggle",
    "frameIdentity": ".admin-frame-identity",
    "frameBrand": ".admin-frame-brand",
    "frameAccount": ".admin-frame-account",
    "headerDrawSlot": ".admin-header-draw-slot",
    "frameActions": ".admin-frame-actions",
    "sidebar": ".admin-sidebar",
    "activeNavLink": ".admin-nav-link.is-active",
    "workspace": ".admin-workspace",
    "main": ".admin-main",
    "contentShell": ".admin-content-shell",
    "pageSurface": ".admin-page-surface",
    "pageBody": ".admin-page-body",
    "commandDashboard": ".command-dashboard",
    "metricGrid": ".command-metric-grid",
    "metricCard": ".command-metric-card",
    "metricLabel": ".command-metric-label",
    "metricValue": ".command-metric-card strong",
    "metricNote": ".command-metric-card small",
    "metricDelta": ".command-metric-card em",
    "commandCard": ".command-card",
    "cardHead": ".command-card-head",
    "cardTitle": ".command-card-head h3",
    "memberCard": ".command-member-card",
    "memberList": ".command-member-list",
    "memberItem": ".command-member-list article",
    "memberName": ".command-member-list strong",
    "memberTime": ".command-member-list span",
    "memberMeta": ".command-member-list small",
    "sourceCard": ".command-source-card",
    "sourceSummary": ".command-source-summary",
    "sourceSummaryPill": ".command-source-summary span",
    "sourceList": ".command-source-list",
    "sourceItem": ".command-source-list article",
    "sourceMain": ".command-source-main",
    "sourceLink": ".command-source-link",
    "sourceVisit": ".command-source-visit",
    "sourceMeta": ".command-source-list small",
    "empty": ".command-empty",
}


def normalize_base_url(value: str) -> str:
    return value.rstrip("/")


def read_style(page, selector: str) -> Optional[Dict[str, Any]]:
    return page.evaluate(
        """([selector, props]) => {
            const node = document.querySelector(selector);
            if (!node) {
                return null;
            }
            const style = getComputedStyle(node);
            const rect = node.getBoundingClientRect();
            const result = {
                count: document.querySelectorAll(selector).length,
                text: (node.innerText || node.textContent || '').trim().slice(0, 120),
                rect: {
                    x: Number(rect.x.toFixed(3)),
                    y: Number(rect.y.toFixed(3)),
                    width: Number(rect.width.toFixed(3)),
                    height: Number(rect.height.toFixed(3))
                }
            };
            for (const prop of props) {
                result[prop] = style[prop];
            }
            return result;
        }""",
        [selector, DEFAULT_PROPS],
    )


def page_metrics(page) -> Dict[str, Any]:
    return page.evaluate(
        """() => {
            const doc = document.documentElement;
            const body = document.body;
            return {
                title: document.title,
                url: location.href,
                bodyClass: body ? body.className : '',
                scrollWidth: doc.scrollWidth,
                clientWidth: doc.clientWidth,
                scrollHeight: doc.scrollHeight,
                clientHeight: doc.clientHeight,
                hasHorizontalOverflow: doc.scrollWidth > doc.clientWidth + 1,
                dashboardCount: document.querySelectorAll('.command-dashboard').length,
                metricCardCount: document.querySelectorAll('.command-metric-card').length,
                commandCardCount: document.querySelectorAll('.command-card').length,
                legacyDashboardNodeCount: document.querySelectorAll('[class*="dashboard-"]').length
            };
        }"""
    )


def login(page, base_url: str, username: str, password: str) -> None:
    login_url = f"{base_url}/public/admin.php"
    page.goto(login_url, wait_until="domcontentloaded")
    try:
        page.wait_for_selector("form.ops-form input[name='username']", timeout=8000)
    except PlaywrightTimeoutError:
        if "page=dashboard" in page.url or page.locator(".command-dashboard").count() > 0:
            return
        raise
    page.fill("form.ops-form input[name='username']", username)
    page.fill("form.ops-form input[name='password']", password)
    page.click("form.ops-form button[type='submit']")
    page.wait_for_load_state("domcontentloaded")


def collect(base_url: str, username: str, password: str, widths: Iterable[int]) -> Dict[str, Any]:
    output: Dict[str, Any] = {
        "baseUrl": base_url,
        "viewports": {},
    }
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            for width in widths:
                console_messages: List[Dict[str, str]] = []
                page = browser.new_page(
                    viewport={"width": int(width), "height": 900},
                    extra_http_headers={"X-Forwarded-Proto": "https"},
                )
                page.on(
                    "console",
                    lambda msg: console_messages.append({"type": msg.type, "text": msg.text}),
                )
                page.on(
                    "pageerror",
                    lambda exc: console_messages.append({"type": "pageerror", "text": str(exc)}),
                )
                login(page, base_url, username, password)
                response = page.goto(f"{base_url}/public/admin.php?page=dashboard", wait_until="networkidle")
                status = response.status if response else None
                try:
                    page.wait_for_selector(".command-dashboard", timeout=10000)
                except PlaywrightTimeoutError as exc:
                    raise RuntimeError(
                        f"Dashboard did not render for width {width}; current URL: {page.url}"
                    ) from exc
                output["viewports"][str(width)] = {
                    "status": status,
                    "metrics": page_metrics(page),
                    "console": console_messages,
                    "styles": {
                        name: read_style(page, selector)
                        for name, selector in SELECTORS.items()
                    },
                }
                page.close()
        finally:
            browser.close()
    return output


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--base-url", required=True)
    parser.add_argument("--username", default="admin")
    parser.add_argument("--password", default="123456")
    parser.add_argument("--width", default="1365,1024,700,390")
    parser.add_argument("--output", required=True)
    args = parser.parse_args()

    widths = [int(item.strip()) for item in args.width.split(",") if item.strip()]
    data = collect(normalize_base_url(args.base_url), args.username, args.password, widths)
    pathlib.Path(args.output).write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
    return 0


if __name__ == "__main__":
    sys.exit(main())
