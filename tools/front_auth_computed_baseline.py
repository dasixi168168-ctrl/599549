#!/usr/bin/env python3
import argparse
import json
import pathlib
import sys
from typing import Dict, Iterable, List

from playwright.sync_api import sync_playwright


ROOT = pathlib.Path(__file__).resolve().parents[1]
CSS_PATHS = [
    ROOT / "public/styles/style.css",
    ROOT / "public/styles/front-floating.css",
]

PROPERTIES = [
    "display",
    "position",
    "boxSizing",
    "width",
    "maxWidth",
    "height",
    "minHeight",
    "paddingTop",
    "paddingRight",
    "paddingBottom",
    "paddingLeft",
    "marginTop",
    "marginRight",
    "marginBottom",
    "marginLeft",
    "alignItems",
    "alignContent",
    "justifyItems",
    "justifyContent",
    "gap",
    "gridTemplateColumns",
    "borderRadius",
    "borderTopColor",
    "borderTopWidth",
    "borderTopStyle",
    "backgroundColor",
    "boxShadow",
    "fontSize",
    "fontWeight",
    "lineHeight",
    "overflowX",
    "overflowY",
]

SELECTORS = {
    "authFrame": ".member-auth-frame",
    "authCard": "#front-auth-card-fixture",
    "authHead": "#front-auth-card-fixture .member-auth-head",
    "authKicker": "#front-auth-card-fixture .member-auth-kicker",
    "authTabs": "#front-auth-card-fixture .member-auth-tabs",
    "authTab": "#front-auth-card-fixture .member-auth-tab.is-active",
    "authForm": "#front-auth-card-fixture .member-auth-form",
    "authField": "#front-auth-card-fixture .member-auth-field",
    "authLabel": "#front-auth-card-fixture .member-auth-label",
    "authCaptcha": "#front-auth-card-fixture .front-auth-captcha",
    "authInput": "#front-auth-card-fixture .auth-input",
    "authSubmit": "#front-auth-card-fixture .member-auth-submit",
}


def css_bundle(paths: Iterable[pathlib.Path]) -> str:
    chunks: List[str] = []
    for path in paths:
        text = path.read_text(encoding="utf-8")
        escaped = text.replace("</style", "<\\/style")
        chunks.append(f"/* {path} */\n{escaped}")
    return "\n\n".join(chunks)


def html_fixture(css_text: str) -> str:
    return f"""<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
{css_text}
  </style>
</head>
<body class="front-unified-panel-page member-auth-page" data-region="macau">
  <div class="page-frame">
    <section class="front-page-shell front-unified-page member-page member-auth-shell member-auth-shell--login">
      <div class="data-frame front-panel-stack front-unified-frame member-auth-frame" data-member-auth-mode="login">
        <div id="front-auth-card-fixture" class="member-auth-card front-auth-card front-standard-panel">
          <div class="member-auth-head front-auth-head">
            <div>
              <div class="member-auth-kicker">澳门论坛</div>
              <h1 class="member-auth-heading">会员登录</h1>
              <p class="member-auth-copy">输入账号密码，进入会员中心。</p>
            </div>
            <div class="member-auth-mark" aria-hidden="true">会</div>
          </div>
          <div class="member-auth-tabs" role="navigation" aria-label="会员入口">
            <a class="member-auth-tab is-active" href="#">登录</a>
            <a class="member-auth-tab" href="#">注册</a>
            <a class="member-auth-tab" href="#">找回</a>
          </div>
          <form class="front-auth-form member-auth-form member-auth-form--login">
            <div class="member-auth-field member-auth-field--account">
              <label class="member-auth-label">会员账号</label>
              <input class="auth-input member-auth-input" type="text" placeholder="请输入会员账号">
            </div>
            <div class="member-auth-field member-auth-field--password">
              <label class="member-auth-label">登录密码</label>
              <input class="auth-input member-auth-input" type="password" placeholder="请输入登录密码">
            </div>
            <div class="front-captcha-grid front-auth-captcha">
              <input class="auth-input member-auth-input" type="text" placeholder="验证码">
              <img class="auth-captcha-code" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="验证码">
              <button class="auth-captcha-refresh" type="button" aria-label="刷新验证码">换</button>
            </div>
            <div class="front-form-actions member-auth-actions">
              <button type="submit" class="member-auth-submit">立即登录</button>
            </div>
          </form>
        </div>
      </div>
    </section>
  </div>
</body>
</html>"""


def read_style(page, selector: str):
    return page.evaluate(
        """([selector, props]) => {
            const node = document.querySelector(selector);
            if (!node) {
                return null;
            }
            const style = getComputedStyle(node);
            const rect = node.getBoundingClientRect();
            const result = {
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
        [selector, PROPERTIES],
    )


def collect(widths: Iterable[int]) -> Dict[str, object]:
    html = html_fixture(css_bundle(CSS_PATHS))
    output: Dict[str, object] = {
        "css": [str(path) for path in CSS_PATHS],
        "viewports": {},
    }
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True)
        try:
            for width in widths:
                page = browser.new_page(viewport={"width": int(width), "height": 900})
                page.set_content(html, wait_until="load")
                output["viewports"][str(width)] = {
                    name: read_style(page, selector)
                    for name, selector in SELECTORS.items()
                }
                page.close()
        finally:
            browser.close()
    return output


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--width", default="1024,640,390")
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    widths = [int(item.strip()) for item in args.width.split(",") if item.strip()]
    pathlib.Path(args.output).write_text(
        json.dumps(collect(widths), ensure_ascii=False, indent=2),
        encoding="utf-8",
    )
    return 0


if __name__ == "__main__":
    sys.exit(main())
