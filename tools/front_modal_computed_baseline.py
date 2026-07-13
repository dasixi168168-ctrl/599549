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
    ROOT / "public/styles/front-service.css",
    ROOT / "public/styles/front-floating.css",
]

PROPERTIES = [
    "display",
    "position",
    "zIndex",
    "boxSizing",
    "width",
    "height",
    "maxWidth",
    "maxHeight",
    "minWidth",
    "minHeight",
    "flexBasis",
    "paddingTop",
    "paddingRight",
    "paddingBottom",
    "paddingLeft",
    "overflowX",
    "overflowY",
    "webkitOverflowScrolling",
    "alignItems",
    "justifyContent",
    "gap",
    "borderRadius",
    "fontSize",
    "fontWeight",
    "lineHeight",
    "opacity",
    "pointerEvents",
    "transform",
    "transitionProperty",
    "transitionDuration",
    "willChange",
    "visibility",
    "backgroundColor",
    "color",
    "borderTopColor",
    "borderTopWidth",
    "borderTopStyle",
    "boxShadow",
]

SELECTORS = {
    "body": "body",
    "expertModal": ".expert-post-modal",
    "expertBackdrop": ".expert-post-modal-backdrop",
    "expertDialog": ".expert-post-modal-dialog",
    "expertHeader": ".expert-post-modal-header",
    "expertHeading": ".expert-post-modal-heading",
    "expertIdentity": ".expert-post-modal-identity",
    "expertAuthor": ".expert-post-modal-author",
    "expertAuthorMeta": ".expert-post-modal-author .expert-post-modal-meta-item",
    "expertBody": ".expert-post-modal-body",
    "expertClose": ".expert-post-modal-close",
    "expertCloseBefore": ".expert-post-modal-close::before",
    "expertCloseAfter": ".expert-post-modal-close::after",
    "loginModal": ".front-post-login-modal:not(.front-post-customer-service-edit-modal)",
    "loginBackdrop": ".front-post-login-modal:not(.front-post-customer-service-edit-modal) .front-post-login-backdrop",
    "loginCard": ".front-post-login-card",
    "rechargeModal": ".member-recharge-modal",
    "rechargeBackdrop": ".member-recharge-backdrop",
    "rechargeDialog": ".member-recharge-dialog",
    "rechargeHead": ".member-recharge-head",
    "rechargeBody": ".member-recharge-body",
    "rechargeUser": ".member-recharge-user",
    "rechargeUserLabel": ".member-recharge-user span",
    "rechargeUserValue": ".member-recharge-user strong",
    "rechargeMethods": ".member-recharge-methods",
    "rechargeMethod": ".member-recharge-method",
    "rechargeMethodIcon": ".member-recharge-method-icon",
    "rechargeMethodTitle": ".member-recharge-method-body h3",
    "rechargeQrDrawer": ".member-recharge-qr-drawer",
    "rechargeQrBox": ".member-recharge-qr-panel[data-member-recharge-panel='alipay'] .member-recharge-qr-box",
    "rechargeUsdtQrBox": ".member-recharge-usdt-grid .member-recharge-qr-box",
    "rechargeCopy": ".member-recharge-copy",
    "rechargeNote": ".member-recharge-note",
    "rechargeNoteText": ".member-recharge-note span",
    "rechargeClose": ".member-recharge-close",
    "rechargeService": ".member-recharge-service",
    "serviceModal": ".front-post-service-modal",
    "serviceBackdrop": ".front-post-service-backdrop",
    "serviceDialog": ".front-post-service-dialog",
    "serviceTitle": ".front-post-service-title",
    "serviceIcon": ".front-post-service-icon",
    "serviceState": ".front-post-service-state",
    "serviceTitleCopy": ".front-post-service-title-copy",
    "serviceTitleSmall": ".front-post-service-title-copy > small",
    "serviceClose": ".front-post-service-close",
    "editModal": ".front-post-customer-service-edit-modal",
    "editBackdrop": ".front-post-customer-service-edit-modal .front-post-login-backdrop",
    "editCard": ".front-post-customer-service-edit-card",
    "editClose": ".front-post-customer-service-edit-head button[data-front-post-customer-service-edit-close]",
    "forecastGuestModal": ".forecast-guest-modal",
    "forecastGuestDialog": ".forecast-guest-dialog",
    "forecastGuestBackdrop": ".forecast-guest-backdrop",
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
<body class="front-unified-panel-page front-post-detail-page forecast-panel-page expert-post-modal-open front-post-login-modal-open member-recharge-modal-open front-post-service-modal-open front-post-customer-service-edit-modal-open" data-region="macau">
  <div class="expert-post-modal front-standard-modal is-visible" id="expert-post-modal">
    <div class="expert-post-modal-backdrop front-standard-modal-backdrop"></div>
    <div class="expert-post-modal-dialog front-standard-modal-dialog" role="dialog" aria-modal="true">
      <div class="expert-post-modal-header front-standard-modal-head">
        <div class="expert-post-modal-heading">
          <div class="expert-post-modal-identity">
            <span class="expert-post-modal-avatar"><i class="fa-solid fa-circle-user"></i><span class="expert-post-modal-avatar-level">超级vip</span></span>
            <div class="expert-post-modal-author"><span class="expert-post-modal-meta-item">作者</span></div>
          </div>
          <div class="expert-post-modal-copy">
            <div class="expert-post-modal-title">帖子阅读</div>
            <div class="expert-post-modal-meta">作者：演示</div>
          </div>
        </div>
        <button type="button" class="expert-post-modal-close">x</button>
      </div>
      <div class="expert-post-modal-body front-standard-modal-body">
        <iframe class="expert-post-modal-frame" title="帖子阅读窗口"></iframe>
      </div>
    </div>
  </div>

  <div class="front-post-login-modal front-standard-modal is-visible" data-front-post-login-modal>
    <div class="front-post-login-backdrop front-standard-modal-backdrop"></div>
    <div class="member-auth-card front-auth-card front-post-login-card front-standard-panel front-standard-modal-dialog">
      <div class="member-auth-head front-auth-head front-standard-modal-head">
        <div>
          <h1 class="member-auth-heading">会员登录</h1>
          <p class="member-auth-copy">输入账号密码，进入会员中心。</p>
        </div>
      </div>
      <form class="front-auth-form member-auth-form member-auth-form--modal">
        <input class="auth-input member-auth-input" type="text">
        <input class="auth-input member-auth-input" type="password">
      </form>
    </div>
  </div>

  <div class="member-recharge-modal front-standard-modal is-visible" id="member-recharge-modal">
    <div class="member-recharge-backdrop front-standard-modal-backdrop"></div>
    <section class="member-recharge-dialog front-standard-modal-dialog" role="dialog" aria-modal="true">
      <div class="member-recharge-head front-standard-modal-head">
        <h2>选择充值方式</h2>
        <div class="member-recharge-user"><span>当前会员</span><strong>账号：demo</strong><strong>积分：100</strong></div>
        <button type="button" class="member-recharge-close"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <div class="member-recharge-body front-standard-modal-body">
        <div class="member-recharge-methods">
          <button type="button" class="member-recharge-method is-alipay"><div class="member-recharge-method-icon"></div><div class="member-recharge-method-body"><h3>支付宝</h3></div></button>
          <button type="button" class="member-recharge-method is-wechat"><div class="member-recharge-method-icon"></div><div class="member-recharge-method-body"><h3>微信</h3></div></button>
          <button type="button" class="member-recharge-method is-usdt"><div class="member-recharge-method-icon"></div><div class="member-recharge-method-body"><h3>USDT</h3></div></button>
        </div>
        <div class="member-recharge-qr-drawer" data-member-recharge-drawer>
          <div class="member-recharge-qr-panel" data-member-recharge-panel="alipay">
            <div class="member-recharge-qr-head">
              <strong>支付宝收款二维码</strong>
              <span>付款后截图提交转账记录，联系客服确认充值</span>
              <button type="button" class="member-recharge-qr-refresh" aria-label="刷新支付宝收款二维码"></button>
            </div>
            <div class="member-recharge-qr-box">
              <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="支付宝充值二维码">
              <div class="member-recharge-qr-fallback">请联系客服获取支付宝二维码</div>
            </div>
          </div>
          <div class="member-recharge-qr-panel" data-member-recharge-panel="usdt">
            <div class="member-recharge-usdt-grid">
              <div class="member-recharge-usdt-qr-column">
                <div class="member-recharge-qr-box">
                  <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="USDT 充值二维码">
                  <div class="member-recharge-qr-fallback">请联系客服获取 USDT 二维码</div>
                </div>
              </div>
              <div class="member-recharge-usdt-address">
                <div class="member-recharge-usdt-title"><span>USDT</span><span>地址</span></div>
                <code>TA1234567890</code>
                <button type="button" class="member-recharge-copy"><span>复制地址</span></button>
              </div>
            </div>
          </div>
        </div>
        <div class="member-recharge-note">
          <strong>充值说明</strong>
          <span>付款完成后截图发送提交凭证，等待客服核对无误后充值积分。</span>
        </div>
      </div>
      <a class="member-recharge-service" href="#">联系客服确认充值</a>
    </section>
  </div>

  <div class="front-post-service-modal front-standard-modal is-visible" data-member-recharge-service-modal>
    <button type="button" class="front-post-service-backdrop front-standard-modal-backdrop"></button>
    <section class="front-post-service-dialog front-standard-modal-dialog" role="dialog" aria-modal="true">
      <header class="front-post-service-head front-standard-modal-head">
        <strong class="front-post-service-title"><span class="front-post-service-icon"><i class="fa-solid fa-headset"></i><span class="front-post-service-state" data-status-type="online">在线</span></span><span class="front-post-service-title-copy"><span>在线客服</span><small>接待时间：09:00-23:00</small></span></strong>
        <span class="front-post-service-actions"><button type="button" class="front-post-service-close"><i class="fa-solid fa-xmark"></i></button></span>
      </header>
      <iframe class="front-post-service-frame" title="在线客服会话"></iframe>
    </section>
  </div>

  <div class="front-post-login-modal front-post-customer-service-edit-modal front-standard-modal is-visible" data-front-post-customer-service-edit-modal>
    <div class="front-post-login-backdrop front-standard-modal-backdrop"></div>
    <form class="front-post-customer-service-edit-card front-standard-modal-dialog">
      <div class="front-post-customer-service-edit-head front-standard-modal-head">
        <div class="front-post-customer-service-edit-title"><h2>编辑资料</h2><p>演示标题</p></div>
        <button type="button" data-front-post-customer-service-edit-close><i class="fa-solid fa-xmark"></i></button>
      </div>
      <label class="front-post-customer-service-edit-field"><textarea rows="3">演示内容</textarea></label>
      <div class="front-post-customer-service-edit-foot"><button type="submit">保存</button></div>
    </form>
  </div>

  <div id="forecast-guest-modal" class="forecast-guest-modal front-standard-modal is-visible">
    <div class="forecast-guest-backdrop front-standard-modal-backdrop" data-forecast-guest-close></div>
    <section class="forecast-guest-dialog front-standard-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="forecast-guest-title">
      <div class="forecast-guest-mark"><i class="fa-solid fa-user-plus"></i></div>
      <div class="forecast-guest-copy">
        <h2 id="forecast-guest-title">注册后参与AI预测</h2>
        <p>当前为游客访问，请先注册会员后再参与预测。</p>
      </div>
      <div class="forecast-guest-actions">
        <button type="button" class="forecast-guest-register" data-forecast-guest-register>立即注册</button>
        <button type="button" class="forecast-guest-login" data-forecast-guest-login>会员登录</button>
        <button type="button" class="forecast-guest-cancel" data-forecast-guest-close>先看看</button>
      </div>
    </section>
  </div>
</body>
</html>"""


def read_style(page, selector: str):
    return page.evaluate(
        """([selector, props]) => {
            let pseudo = null;
            let baseSelector = selector;
            if (selector.endsWith("::before")) {
                pseudo = "::before";
                baseSelector = selector.slice(0, -"::before".length);
            } else if (selector.endsWith("::after")) {
                pseudo = "::after";
                baseSelector = selector.slice(0, -"::after".length);
            }
            const node = document.querySelector(baseSelector);
            if (!node) {
                return null;
            }
            const rect = node.getBoundingClientRect();
            const style = window.getComputedStyle(node, pseudo);
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
    css_text = css_bundle(CSS_PATHS)
    html = html_fixture(css_text)
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
                page.wait_for_timeout(250)
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
    data = collect(widths)
    pathlib.Path(args.output).write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
    return 0


if __name__ == "__main__":
    sys.exit(main())
