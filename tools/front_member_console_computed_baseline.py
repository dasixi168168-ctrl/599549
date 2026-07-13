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
    "boxSizing",
    "gridTemplateColumns",
    "gridColumn",
    "alignItems",
    "alignContent",
    "justifyItems",
    "justifyContent",
    "placeItems",
    "gap",
    "rowGap",
    "columnGap",
    "flexWrap",
    "alignSelf",
    "justifySelf",
    "width",
    "height",
    "minWidth",
    "minHeight",
    "maxWidth",
    "paddingTop",
    "paddingRight",
    "paddingBottom",
    "paddingLeft",
    "marginLeft",
    "marginRight",
    "marginTop",
    "borderRadius",
    "objectFit",
    "borderTopColor",
    "borderTopWidth",
    "borderTopStyle",
    "backgroundColor",
    "boxShadow",
    "fontSize",
    "fontWeight",
    "lineHeight",
    "color",
    "overflowX",
    "overflowY",
    "whiteSpace",
    "visibility",
    "transform",
]

SELECTORS = {
    "console": ".member-console",
    "hero": ".member-console-hero",
    "top": ".member-console-top",
    "main": ".member-console-main",
    "avatar": ".member-console-avatar",
    "avatarImg": ".member-console-avatar img",
    "avatarLevel": ".member-console-avatar-level",
    "identity": ".member-console-identity",
    "name": ".member-console-name",
    "meta": ".member-console-meta",
    "metaPill": ".member-console-meta span",
    "status": ".member-console-status",
    "statusStrong": ".member-console-status strong",
    "infoRow": ".member-console-info-row",
    "score": ".member-console-score",
    "scoreRefresh": ".member-console-score-refresh",
    "recharge": ".member-console-recharge",
    "links": ".member-console-links",
    "link": ".member-console-link",
    "activeLink": ".member-console-link.is-active",
    "panel": ".member-console-panel",
    "aiPanel": ".member-ai-log-panel",
    "aiHead": ".member-ai-log-head",
    "aiBadge": ".member-ai-log-badge",
    "aiSelectAll": ".member-ai-log-select-all",
    "aiDeleteBtn": ".member-ai-log-delete-btn",
    "aiList": ".member-ai-log-list",
    "aiCard": ".member-ai-log-card",
    "aiCardContent": ".member-ai-log-card-content",
    "aiCheck": ".member-ai-log-check",
    "aiCheckInput": ".member-ai-log-check-input",
    "aiResultTitle": ".member-ai-log-card .forecast-result-title",
    "aiIssueBadge": ".member-ai-log-card .forecast-result-issue-badge",
    "aiIssueMark": ".member-ai-log-card .forecast-result-issue-mark",
    "aiIssueText": ".member-ai-log-card .forecast-result-issue-text",
    "aiTitleText": ".member-ai-log-card .forecast-result-title-text",
    "aiDrawResult": ".member-ai-log-draw-result",
    "aiDrawPending": ".member-ai-log-draw-result.is-pending",
    "aiDrawRow": ".member-ai-log-draw-row",
    "aiDrawToken": ".member-ai-log-draw-token",
    "aiDrawSeparator": ".member-ai-log-draw-separator",
    "aiDrawPlus": ".member-ai-log-draw-plus",
    "aiDrawGap": ".member-ai-log-draw-gap",
    "aiTime": ".member-ai-log-time",
    "aiSummary": ".member-ai-log-card .forecast-result-summary",
    "aiSummaryLineZodiac": ".member-ai-log-card .forecast-result-summary-line.is-zodiac",
    "aiSummaryLineNumber": ".member-ai-log-card .forecast-result-summary-line.is-number",
    "aiSummaryLineOther": ".member-ai-log-card .forecast-result-summary-line.is-other",
    "aiSummaryLinePingte": ".member-ai-log-card .forecast-result-summary-line.is-pingte",
    "aiSummaryBodyZodiac": ".member-ai-log-card .forecast-result-summary-line.is-zodiac .forecast-result-summary-body",
    "aiSummaryBodyNumber": ".member-ai-log-card .forecast-result-summary-line.is-number .forecast-result-summary-body",
    "aiSummaryBodyOther": ".member-ai-log-card .forecast-result-summary-line.is-other .forecast-result-summary-body",
    "aiSummaryBodyPingte": ".member-ai-log-card .forecast-result-summary-line.is-pingte .forecast-result-summary-body",
    "aiSummaryTypeZodiac": ".member-ai-log-card .forecast-result-summary-line.is-zodiac .forecast-result-summary-type",
    "aiSummaryTypeNumber": ".member-ai-log-card .forecast-result-summary-line.is-number .forecast-result-summary-type",
    "aiSummaryTypeOther": ".member-ai-log-card .forecast-result-summary-line.is-other .forecast-result-summary-type",
    "aiSummaryTypePingte": ".member-ai-log-card .forecast-result-summary-line.is-pingte .forecast-result-summary-type",
    "aiSummaryValueZodiac": ".member-ai-log-card .forecast-result-summary-line.is-zodiac .forecast-result-summary-value",
    "aiSummaryValueNumber": ".member-ai-log-card .forecast-result-summary-line.is-number .forecast-result-summary-value",
    "aiSummaryValueOther": ".member-ai-log-card .forecast-result-summary-line.is-other .forecast-result-summary-value",
    "aiSummaryValuePingte": ".member-ai-log-card .forecast-result-summary-line.is-pingte .forecast-result-summary-value",
    "aiTypeChip": ".member-ai-log-card .forecast-result-type-chip",
    "aiOtherChip": ".member-ai-log-card .forecast-result-other-chip",
    "aiPingteCombo": ".member-ai-log-card .forecast-result-pingte-text.is-combo",
    "purchaseList": ".member-purchase-list",
    "purchaseCard": ".member-purchase-card",
    "purchaseHead": ".member-purchase-head",
    "purchaseTitle": ".member-purchase-title",
    "purchaseMeta": ".member-purchase-meta",
    "purchaseRegion": ".member-purchase-meta .is-region",
    "purchaseState": ".member-purchase-state",
    "purchaseStateView": ".member-purchase-state.is-view",
    "aboutList": ".member-about-list",
    "aboutCard": ".member-about-card",
    "aboutIcon": ".member-about-icon",
    "aboutTitle": ".member-about-title",
    "profileGrid": ".member-profile-grid",
    "profileCard": ".member-profile-card",
    "profileTitle": ".member-profile-title",
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
<body class="front-unified-panel-page member-center-page" data-region="macau">
  <div class="page-frame">
    <section class="front-page-shell front-unified-page member-page">
      <div class="data-frame front-panel-stack front-unified-frame">
        <div class="member-console">
          <section class="member-console-hero">
            <div class="member-console-top">
              <div class="member-console-main">
                <div class="member-console-avatar">
                  <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="会员头像">
                  <i class="fa-solid fa-circle-user"></i>
                  <span class="member-console-avatar-level" data-avatar-level="普通会员">普通会员</span>
                </div>
                <div class="member-console-identity">
                  <div class="member-console-name">demo_user_168</div>
                  <div class="member-console-meta"><span>普通会员</span></div>
                </div>
              </div>
              <div class="member-console-status">
                <span>最近登录</span>
                <strong>2026-07-09 20:30</strong>
              </div>
              <div class="member-console-info-row">
                <div class="member-console-score">
                  <span>积分：</span><strong>1888</strong>
                  <a class="member-console-score-refresh" href="#" aria-label="刷新积分">刷</a>
                </div>
                <button type="button" class="member-console-recharge">充值中心</button>
              </div>
            </div>
          </section>
          <nav class="member-console-links" aria-label="会员功能链路">
            <a class="member-console-link is-active" href="#"><i>AI</i><span>预测记录</span></a>
            <a class="member-console-link" href="#"><i>购</i><span>购买记录</span></a>
            <a class="member-console-link" href="#"><i>指</i><span>论坛指南</span></a>
            <a class="member-console-link" href="#"><i>资</i><span>个人资料</span></a>
          </nav>
          <div class="member-console-panel">
            <section class="member-ai-log-panel">
              <form data-member-prediction-delete-form>
                <div class="member-ai-log-head">
                  <div>
                    <div class="member-ai-log-kicker">AI LOG</div>
                    <h2>预测记录</h2>
                  </div>
                  <div>
                    <span class="member-ai-log-badge">1条</span>
                    <label class="member-ai-log-select-all">
                      <input type="checkbox" class="member-ai-log-check-input" data-member-prediction-check-all>
                      <span>全选</span>
                    </label>
                    <button type="submit" class="member-ai-log-delete-btn" data-member-prediction-delete-submit disabled>删除记录</button>
                  </div>
                </div>
                <div class="member-ai-log-list">
                  <div class="member-ai-log-card">
                    <label class="member-ai-log-check">
                      <input type="checkbox" class="member-ai-log-check-input h-3.5 w-3.5 accent-blue-600" name="prediction_ids[]" value="1">
                      <span class="sr-only">选择记录</span>
                    </label>
                    <div class="member-ai-log-card-content">
                      <div class="min-w-0 flex-1">
                        <div class="forecast-block-title forecast-result-title">
                          <span class="forecast-result-issue-badge is-macau">
                            <span class="forecast-result-issue-mark">澳</span>
                            <span class="forecast-result-issue-text">191期</span>
                          </span>
                          <span class="forecast-result-title-text">结果:</span>
                          <span class="member-ai-log-draw-result">
                            <span class="member-ai-log-draw-row">
                              <span class="member-ai-log-draw-token is-red">01</span><span class="member-ai-log-draw-separator">-</span><span class="member-ai-log-draw-token is-blue">02</span><span class="member-ai-log-draw-separator">-</span><span class="member-ai-log-draw-token is-green">03</span><span class="member-ai-log-draw-separator">-</span><span class="member-ai-log-draw-token is-red">04</span><span class="member-ai-log-draw-separator">-</span><span class="member-ai-log-draw-token is-blue">05</span><span class="member-ai-log-draw-separator">-</span><span class="member-ai-log-draw-token is-green">06</span><span class="member-ai-log-draw-plus">+</span><span class="member-ai-log-draw-token is-red">07</span>
                            </span>
                            <span class="member-ai-log-draw-row">
                              <span class="member-ai-log-draw-token">鼠</span><span class="member-ai-log-draw-gap">-</span><span class="member-ai-log-draw-token">牛</span><span class="member-ai-log-draw-gap">-</span><span class="member-ai-log-draw-token">虎</span><span class="member-ai-log-draw-gap">-</span><span class="member-ai-log-draw-token">兔</span><span class="member-ai-log-draw-gap">-</span><span class="member-ai-log-draw-token">龙</span><span class="member-ai-log-draw-gap">-</span><span class="member-ai-log-draw-token">蛇</span><span class="member-ai-log-draw-gap">+</span><span class="member-ai-log-draw-token">马</span>
                            </span>
                          </span>
                        </div>
                        <span class="member-ai-log-draw-result is-pending">
                          <span class="member-ai-log-draw-row">
                            <span class="member-ai-log-draw-label">待开奖</span>
                          </span>
                        </span>
                        <div class="forecast-result-summary mt-2">
                          <div class="forecast-result-summary-line is-zodiac">
                            <span class="forecast-result-summary-head"><span class="forecast-result-summary-label">生肖</span></span>
                            <span class="forecast-result-summary-confidence">80.0%</span>
                            <span class="forecast-result-summary-body">
                              <span class="forecast-result-summary-type"><span class="forecast-result-type-chip">生肖:</span></span>
                              <span class="forecast-result-summary-value"><span class="forecast-result-zodiac-chip">鼠</span><span class="forecast-result-zodiac-chip">牛</span></span>
                            </span>
                          </div>
                          <div class="forecast-result-summary-line is-number">
                            <span class="forecast-result-summary-head"><span class="forecast-result-summary-label">号码</span></span>
                            <span class="forecast-result-summary-confidence">76.0%</span>
                            <span class="forecast-result-summary-body">
                              <span class="forecast-result-summary-type"><span class="forecast-result-type-chip is-correct">号码:<span class="forecast-result-correct-mark" aria-hidden="true">✓</span></span></span>
                              <span class="forecast-result-summary-value"><span class="forecast-result-number-chip">01</span><span class="forecast-result-number-chip">02</span></span>
                            </span>
                          </div>
                          <div class="forecast-result-summary-line is-other">
                            <span class="forecast-result-summary-head"><span class="forecast-result-summary-label">波色</span></span>
                            <span class="forecast-result-summary-body">
                              <span class="forecast-result-summary-type"><span class="forecast-result-type-chip">波色:</span></span>
                              <span class="forecast-result-summary-value"><span class="forecast-result-other-chip">红波</span><span class="forecast-result-other-chip">蓝波</span></span>
                            </span>
                          </div>
                          <div class="forecast-result-summary-line is-pingte">
                            <span class="forecast-result-summary-head"><span class="forecast-result-summary-label">平特</span></span>
                            <span class="forecast-result-summary-body">
                              <span class="forecast-result-summary-type"><span class="forecast-result-type-chip">平特:</span></span>
                              <span class="forecast-result-summary-value"><span class="forecast-result-pingte-text is-combo"><span class="forecast-result-pingte-bracket">[</span><span class="forecast-result-pingte-number">01</span><span class="forecast-result-pingte-separator">,</span><span class="forecast-result-pingte-number">02</span><span class="forecast-result-pingte-bracket">]</span></span></span>
                            </span>
                          </div>
                        </div>
                        <div class="member-ai-log-time">2026-07-09 20:30</div>
                      </div>
                    </div>
                  </div>
                </div>
              </form>
            </section>
            <div class="member-purchase-list">
              <a class="member-purchase-card" href="#">
                <div class="member-purchase-head">
                  <div class="member-purchase-title">演示购买帖子标题</div>
                  <span class="member-purchase-state is-view">可查看</span>
                </div>
                <div class="member-purchase-meta">
                  <span class="is-region">澳门</span>
                  <span class="is-score">188积分</span>
                  <span class="is-time">2026-07-09</span>
                </div>
              </a>
            </div>
            <div class="member-about-list">
              <div class="member-about-card">
                <span class="member-about-icon is-invite">邀</span>
                <div class="member-about-body">
                  <div class="member-about-title">邀请好友</div>
                  <p>复制邀请链接给好友。</p>
                </div>
              </div>
            </div>
            <div class="member-profile-grid">
              <div class="member-profile-card member-invite-card">
                <div class="member-profile-title">邀请好友</div>
                <input class="auth-input" readonly value="https://example.test/invite/demo_user_168">
              </div>
              <form class="member-profile-card">
                <div class="member-profile-title">基础资料</div>
                <input class="auth-input" value="demo_user_168">
              </form>
            </div>
          </div>
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
