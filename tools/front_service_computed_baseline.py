#!/usr/bin/env python3
"""Capture front customer-service computed-style baselines with a real browser."""

import argparse
import json
import pathlib
import sys
from typing import Any, Dict, Iterable, List

try:
    from playwright.sync_api import sync_playwright
except ImportError as exc:
    raise SystemExit(
        "Playwright is not installed. Run: python3 -m pip install --user playwright && "
        "python3 -m playwright install chromium"
    ) from exc


DEFAULT_CSS = [
    "public/styles/style.css",
    "public/styles/front-service.css",
    "public/styles/front-floating.css",
]

DEFAULT_PROPS = [
    "display",
    "position",
    "zIndex",
    "boxSizing",
    "width",
    "height",
    "maxWidth",
    "maxHeight",
    "paddingTop",
    "paddingRight",
    "paddingBottom",
    "paddingLeft",
    "overflowX",
    "overflowY",
    "overscrollBehavior",
    "alignItems",
    "justifyItems",
    "justifyContent",
    "gap",
    "borderRadius",
    "objectFit",
    "fontSize",
    "fontFamily",
    "fontWeight",
    "lineHeight",
    "top",
    "right",
    "gridTemplateColumns",
    "gridTemplateRows",
    "minWidth",
    "minHeight",
    "marginTop",
    "marginRight",
    "marginBottom",
    "marginLeft",
    "flex",
    "flexBasis",
    "flexDirection",
    "opacity",
    "pointerEvents",
    "transform",
    "transitionProperty",
    "transitionDuration",
    "willChange",
    "visibility",
    "backgroundColor",
    "backgroundImage",
    "backgroundPosition",
    "backgroundRepeat",
    "backgroundSize",
    "color",
    "cursor",
    "appearance",
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
    "boxShadow",
]

SELECTORS = {
    "body": "body",
    "imagePreviewModal": ".customer-service-image-preview-modal",
    "imagePreviewBackdrop": ".customer-service-image-preview-backdrop",
    "imagePreviewDialog": ".customer-service-image-preview-dialog",
    "imagePreviewImage": ".customer-service-image-preview-dialog img",
    "imagePreviewTitle": ".customer-service-image-preview-dialog strong",
    "imagePreviewClose": ".customer-service-image-preview-close",
    "paymentPreviewModal": ".service-agent-payment-preview-modal",
    "paymentPreviewBackdrop": ".service-agent-payment-preview-backdrop",
    "paymentPreviewDialog": ".service-agent-payment-preview-dialog",
    "paymentPreviewImage": ".service-agent-payment-preview-dialog img",
    "paymentPreviewTitle": ".service-agent-payment-preview-dialog strong",
    "paymentPreviewClose": ".service-agent-payment-preview-close",
    "paymentGrid": ".customer-service-agent-management .service-agent-payment-preview",
    "paymentGridItem": ".customer-service-agent-management .service-agent-payment-preview-item",
    "paymentGridImage": ".customer-service-agent-management .service-agent-payment-preview-item img",
    "paymentGridEmpty": ".customer-service-agent-management .service-agent-payment-empty",
    "settingsModal": ".service-agent-settings-modal",
    "settingsBackdrop": ".service-agent-settings-backdrop",
    "settingsCard": ".service-agent-settings-card",
    "settingsHead": ".service-agent-settings-head",
    "settingsTitle": ".service-agent-settings-head h2",
    "settingsClose": ".service-agent-settings-head button[data-service-agent-settings-close]",
    "settingsForm": ".service-agent-settings-form",
    "settingsDuo": ".service-agent-settings-duo",
    "settingsNicknameField": ".service-agent-settings-field.is-nickname",
    "settingsHoursField": ".service-agent-settings-field.is-hours",
    "nicknamePicker": ".service-agent-nickname-picker",
    "nicknameInput": ".service-agent-nickname-picker input[data-service-agent-nickname-input]",
    "nicknameToggle": ".service-agent-nickname-picker [data-service-agent-nickname-toggle]",
    "nicknameMenu": ".service-agent-nickname-menu",
    "nicknameRow": ".service-agent-nickname-row",
    "nicknameOption": ".service-agent-nickname-row [data-service-agent-nickname-option]",
    "nicknameDelete": ".service-agent-nickname-row [data-service-agent-nickname-delete]",
    "settingsNotice": ".service-agent-settings-notice",
    "settingsSwitch": ".service-agent-settings-switch",
    "settingsSwitchTrack": ".service-agent-settings-switch > span",
    "settingsSwitchTrackBefore": ".service-agent-settings-switch > span::before",
    "settingsSwitchCheckedTrack": ".service-agent-settings-switch input[type='checkbox']:checked + span",
    "settingsSwitchCheckedBefore": ".service-agent-settings-switch input[type='checkbox']:checked + span::before",
    "settingsActions": ".service-agent-settings-actions",
    "settingsSubmit": ".service-agent-settings-actions button[type='submit']",
    "agentTop": ".service-agent-top",
    "agentTopMain": ".service-agent-top-main",
    "agentTitleName": ".service-agent-title-name",
    "agentPresencePill": ".service-agent-presence-pill",
    "agentSettingsToggle": ".service-agent-settings-toggle",
    "scoreModal": ".service-agent-score-modal",
    "scoreBackdrop": ".service-agent-score-backdrop",
    "scoreCard": ".service-agent-score-card",
    "scoreHead": ".service-agent-score-head",
    "scoreTitle": ".service-agent-score-head h2",
    "scoreClose": ".service-agent-score-head button[data-service-agent-score-close]",
    "scoreForm": ".service-agent-score-form",
    "scoreSummary": ".service-agent-score-summary",
    "scoreCurrentRow": ".service-agent-score-current-row",
    "scoreCurrentLabel": ".service-agent-score-current-row span",
    "scoreCurrentValue": ".service-agent-score-current-row strong[data-service-agent-score-current]",
    "scoreActions": ".service-agent-score-actions",
    "scoreField": ".service-agent-score-field",
    "scoreInput": ".service-agent-score-field input[data-service-agent-score-amount]",
    "scoreSubmit": ".service-agent-score-actions button[data-service-agent-score-submit]",
    "emojiComposer": ".service-thread-composer--agent",
    "emojiPanel": ".service-thread-composer--agent .service-thread-emoji-panel",
    "emojiButton": ".service-thread-composer--agent .service-thread-emoji-panel button",
    "blockThread": "#service-agent-chat.service-thread--agent",
    "blockHead": "#service-agent-chat .service-thread-head",
    "blockPeer": "#service-agent-chat .service-thread-peer",
    "blockAvatar": "#service-agent-chat .service-thread-avatar",
    "blockPeerCopy": "#service-agent-chat .service-thread-peer-copy",
    "blockTitle": "#service-agent-chat .service-thread-title",
    "blockStatus": "#service-agent-chat .service-thread-status",
    "blockActions": "#service-agent-chat .service-thread-actions--agent",
    "blockControls": "#service-agent-chat .service-thread-block-controls",
    "blockSelect": "#service-agent-chat [data-service-agent-block-limit]",
    "blockScoreAction": "#service-agent-chat .service-thread-action--score[data-service-agent-score-open]",
    "blockClearAction": "#service-agent-chat .service-thread-action--danger[data-customer-service-clear]",
    "blockLog": "#service-agent-chat .service-thread-log",
    "blockComposer": "#service-agent-chat .service-thread-composer--agent",
    "blockLocked": "#service-agent-chat .service-thread-locked",
    "messageWrapPeer": "#service-agent-chat .service-thread-message.is-peer .service-thread-message-wrap",
    "messageWrapSelf": "#service-agent-chat .service-thread-message.is-self .service-thread-message-wrap",
    "messageBubblePeer": "#service-agent-chat .service-thread-message.is-peer .service-thread-bubble",
    "messageBubbleSelf": "#service-agent-chat .service-thread-message.is-self .service-thread-bubble",
    "messageImageOpen": "#service-agent-chat .service-thread-image-open",
    "messageImage": "#service-agent-chat .service-thread-bubble img",
    "recallMeta": "#service-agent-chat .service-thread-message.is-self .service-thread-meta",
    "recallButton": "#service-agent-chat .service-thread-recall",
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
<body class="bg-slate-100 text-slate-900 standalone-panel front-unified-panel-page customer-service-body customer-service-panel-page customer-service-agent-body customer-service-image-preview-modal-open service-agent-payment-preview-modal-open service-agent-settings-open service-agent-score-modal-open" data-region="macau">
  <section class="front-page-shell front-unified-page customer-service-page" data-customer-service>
    <div class="data-frame front-panel-stack front-unified-frame customer-service-frame">
      <div class="customer-service-agent-console">
        <div class="service-agent-top">
          <div class="service-agent-top-main">
            <div class="service-agent-title-block">
              <h1 class="service-agent-title-name" data-service-agent-title>值班客服</h1>
            </div>
            <button type="button" class="service-agent-presence-pill" data-status-type="online">在线</button>
            <button type="button" class="service-agent-settings-toggle" data-service-agent-settings-open>设置</button>
          </div>
          <div class="service-agent-view-switch" role="tablist" aria-label="接待视图">
            <button type="button" class="is-active"><span>会话</span></button>
            <button type="button"><span>设置</span></button>
          </div>
        </div>
        <div class="customer-service-agent-management">
          <div class="service-agent-payment-preview">
            <div class="service-agent-payment-preview-item">
              <button class="service-agent-payment-preview-open" type="button" data-service-agent-payment-preview-open="/public/demo.png" data-service-agent-payment-preview-title="支付宝二维码1">
                <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="二维码">
              </button>
              <span class="service-agent-payment-preview-order">1</span>
            </div>
            <div class="service-agent-payment-empty">暂无二维码</div>
          </div>
        </div>
      </div>
      <div id="service-agent-chat" class="service-thread service-thread--agent" aria-label="客服接待区" data-customer-service-role="agent" data-service-agent-panel="chat" data-service-thread>
        <div class="service-thread-head">
          <div class="service-thread-peer">
            <span class="service-thread-avatar" data-customer-service-active-avatar aria-label="会员头像">会</span>
            <div class="service-thread-peer-copy">
              <div class="service-thread-title" data-customer-service-active-name>演示会员</div>
              <div class="service-thread-status" data-customer-service-active-online data-status-type="online">在线</div>
            </div>
          </div>
          <div class="service-thread-actions service-thread-actions--agent">
            <button class="service-thread-action service-thread-action--score" type="button" data-service-agent-score-open aria-label="调整积分" title="调整积分">
              <span>调分</span>
            </button>
            <span class="service-thread-block-controls" data-service-agent-chat-block-controls>
              <select data-service-agent-block-limit data-session-id="168" data-blocked="0" aria-label="屏蔽会话">
                <option value="" data-service-agent-block-placeholder selected disabled>未屏蔽</option>
                <option value="permanent" data-service-agent-block-mode="block">永久屏蔽</option>
                <option value="1h" data-service-agent-block-mode="block">屏蔽1小时</option>
                <option value="24h" data-service-agent-block-mode="block">屏蔽24小时</option>
                <option value="7d" data-service-agent-block-mode="block">屏蔽7天</option>
                <option value="30d" data-service-agent-block-mode="block">屏蔽30天</option>
                <option value="unblock" data-service-agent-block-mode="unblock" hidden disabled>解除屏蔽</option>
              </select>
            </span>
            <button class="service-thread-action service-thread-action--danger" type="button" data-customer-service-clear aria-label="清除聊天记录" title="清除聊天记录">删</button>
          </div>
        </div>
        <div class="service-thread-log">
          <div class="service-thread-message is-peer" data-customer-service-message-id="1">
            <div class="service-thread-message-wrap">
              <div class="service-thread-bubble is-image">
                <button type="button" class="service-thread-image-open" data-customer-service-image-preview-open="/public/demo.png">
                  <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="聊天图片" width="240" height="180">
                </button>
              </div>
            </div>
          </div>
          <div class="service-thread-message is-self" data-customer-service-message-id="2">
            <div class="service-thread-message-wrap">
              <div class="service-thread-meta">
                <span>值班客服</span>
                <span>12:30</span>
                <button type="button" class="service-thread-recall" data-customer-service-recall="2" aria-label="撤回该消息">撤回</button>
              </div>
              <div class="service-thread-bubble is-text">您好，这里是在线客服。</div>
            </div>
          </div>
        </div>
        <form class="service-thread-composer service-thread-composer--agent" method="post" data-customer-service-form>
          <div class="service-thread-tools">
            <button class="service-thread-tool" type="button" data-customer-service-voice aria-label="切换语音输入" aria-pressed="false">话</button>
            <button class="service-thread-tool" type="button" data-customer-service-image-trigger aria-label="选择图片">图</button>
            <button class="service-thread-tool" type="button" data-customer-service-emoji-toggle aria-label="选择表情" aria-haspopup="dialog" aria-expanded="true">表</button>
          </div>
          <textarea class="service-thread-input" name="content" rows="1" maxlength="1000" placeholder="输入回复内容..." autocomplete="off" data-customer-service-input></textarea>
          <button class="service-thread-send" type="submit" aria-label="发送消息">发</button>
          <div class="service-thread-emoji-panel" data-customer-service-emoji-panel>
            <button type="button" data-customer-service-emoji="😀">😀</button>
            <button type="button" data-customer-service-emoji="😂">😂</button>
            <button type="button" data-customer-service-emoji="👍">👍</button>
            <button type="button" data-customer-service-emoji="🔥">🔥</button>
            <button type="button" data-customer-service-emoji="🎯">🎯</button>
            <button type="button" data-customer-service-emoji="✅">✅</button>
          </div>
        </form>
        <div class="service-thread-locked" data-service-agent-locked>当前账号无回复权限，或该会话不可操作。</div>
      </div>
    </div>
  </section>
  <div class="customer-service-image-preview-modal is-visible" data-customer-service-image-preview-modal>
    <button type="button" class="customer-service-image-preview-backdrop" data-customer-service-image-preview-close aria-label="关闭图片预览"></button>
    <section class="customer-service-image-preview-dialog" role="dialog" aria-modal="true" aria-label="聊天图片预览">
      <button type="button" class="customer-service-image-preview-close" data-customer-service-image-preview-close aria-label="关闭图片预览">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="聊天图片预览" decoding="async">
      <strong data-customer-service-image-preview-title>聊天图片</strong>
    </section>
  </div>
  <div class="service-agent-payment-preview-modal is-visible" data-service-agent-payment-preview-modal>
    <button type="button" class="service-agent-payment-preview-backdrop" data-service-agent-payment-preview-close aria-label="关闭二维码预览"></button>
    <section class="service-agent-payment-preview-dialog" role="dialog" aria-modal="true" aria-label="二维码预览">
      <button type="button" class="service-agent-payment-preview-close" data-service-agent-payment-preview-close aria-label="关闭二维码预览">
        <i class="fa-solid fa-xmark"></i>
      </button>
      <img src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" alt="二维码预览" decoding="async">
      <strong data-service-agent-payment-preview-title>支付宝二维码1</strong>
    </section>
  </div>
  <div class="service-agent-settings-modal is-visible" id="service-agent-settings-modal" data-service-agent-settings-modal role="dialog" aria-modal="true" aria-labelledby="service-agent-settings-title">
    <button class="service-agent-settings-backdrop" type="button" data-service-agent-settings-close aria-label="关闭设置弹窗"></button>
    <section class="service-agent-settings-card">
      <div class="service-agent-settings-head">
        <div>
          <h2 id="service-agent-settings-title">客服设置</h2>
        </div>
        <button type="button" data-service-agent-settings-close aria-label="关闭设置"><i class="fa-solid fa-xmark"></i></button>
      </div>
      <form class="service-agent-settings-form" method="post" action="/public/api.php" data-service-agent-settings-form>
        <input type="hidden" name="action" value="customer_service.agent.settings">
        <div class="service-agent-settings-duo">
          <div class="service-agent-settings-field is-nickname">
            <label for="service-agent-display-name">昵称选择</label>
            <div class="service-agent-nickname-picker is-open" data-service-agent-nickname-picker>
              <input id="service-agent-display-name" name="display_name" maxlength="80" value="值班客服" placeholder="例如：值班客服" autocomplete="off" data-service-agent-nickname-input>
              <button type="button" data-service-agent-nickname-toggle aria-haspopup="listbox" aria-expanded="true" aria-label="展开昵称选择">
                <i class="fa-solid fa-caret-down" aria-hidden="true"></i>
              </button>
              <div class="service-agent-nickname-menu" data-service-agent-nickname-menu role="listbox">
                <div class="service-agent-nickname-row is-active" role="option" data-service-agent-nickname-row="值班客服" aria-selected="true">
                  <button type="button" data-service-agent-nickname-option="值班客服">值班客服</button>
                  <button type="button" data-service-agent-nickname-delete="值班客服" aria-label="删除昵称 值班客服">x</button>
                </div>
                <div class="service-agent-nickname-row" role="option" data-service-agent-nickname-row="晚班客服" aria-selected="false">
                  <button type="button" data-service-agent-nickname-option="晚班客服">晚班客服</button>
                  <button type="button" data-service-agent-nickname-delete="晚班客服" aria-label="删除昵称 晚班客服">x</button>
                </div>
              </div>
            </div>
          </div>
          <div class="service-agent-settings-field is-hours">
            <label for="service-agent-service-hours">接待时间</label>
            <input id="service-agent-service-hours" name="service_hours" maxlength="80" value="09:00-23:00" placeholder="例如：09:00-23:00">
          </div>
        </div>
        <div class="service-agent-settings-notice is-wide">
          <div class="service-agent-settings-notice-head">
            <span>活动公告</span>
            <label class="service-agent-settings-switch">
              <input type="hidden" name="activity_notice_enabled" value="0">
              <input type="checkbox" name="activity_notice_enabled" value="1" checked>
              <span aria-hidden="true"></span>
              <em>启动活动</em>
            </label>
          </div>
          <textarea name="activity_notice" rows="4" maxlength="2000">活动公告内容</textarea>
        </div>
        <label class="is-wide">
          <span>欢迎语</span>
          <textarea name="welcome_text" rows="3" maxlength="255">您好，这里是在线客服。</textarea>
        </label>
        <label class="is-wide">
          <span>自动回复语</span>
          <textarea name="auto_reply_text" rows="4" maxlength="1000">客服看到后会尽快回复。</textarea>
        </label>
        <div class="service-agent-settings-guide is-wide">
          <div class="service-agent-settings-guide-head">论坛指南</div>
          <label>
            <span>邀请好友规则</span>
            <textarea name="forum_guide_invite_rule" rows="2" maxlength="800">邀请好友说明</textarea>
          </label>
          <label>
            <span>充值规则</span>
            <textarea name="forum_guide_recharge_rule" rows="2" maxlength="800">充值说明</textarea>
          </label>
        </div>
        <div class="service-agent-settings-actions">
          <button type="submit">保存设置</button>
        </div>
      </form>
    </section>
  </div>
  <div class="service-agent-score-modal is-visible" id="service-agent-score-modal" data-service-agent-score-modal role="dialog" aria-modal="true" aria-labelledby="service-agent-score-title">
    <button class="service-agent-score-backdrop" type="button" data-service-agent-score-close aria-label="关闭调整积分弹窗"></button>
    <section class="service-agent-score-card">
      <div class="service-agent-score-head">
        <h2 id="service-agent-score-title">调整积分</h2>
        <button type="button" data-service-agent-score-close aria-label="关闭调整积分">
          <i class="front-fa-icon front-icon-xmark" aria-hidden="true"></i>
        </button>
      </div>
      <form class="service-agent-score-form" data-service-agent-score-form>
        <div class="service-agent-score-summary">
          <div class="service-agent-score-account-row">
            <span class="service-agent-score-avatar" data-service-agent-score-avatar aria-label="会员头像"><i class="front-fa-icon front-icon-circle-user" aria-hidden="true"></i></span>
            <span>账号</span>
            <strong data-service-agent-score-account>演示会员</strong>
          </div>
          <div class="service-agent-score-current-row">
            <span>积分</span>
            <strong data-service-agent-score-current>1688</strong>
          </div>
        </div>
        <div class="service-agent-score-actions">
          <label class="service-agent-score-field">
            <span>变动积分</span>
            <input type="number" min="-100000000" max="100000000" step="1" inputmode="numeric" placeholder="正数为充值，负数为扣减" data-service-agent-score-amount required>
          </label>
          <button type="submit" data-service-agent-score-submit>确认调整</button>
        </div>
      </form>
    </section>
  </div>
</body>
</html>"""


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
    page.wait_for_timeout(250)
    return page.evaluate(
        """({props, selectors}) => {
          const result = {};
          for (const [name, selector] of Object.entries(selectors)) {
            let pseudo = null;
            let baseSelector = selector;
            if (selector.endsWith("::before")) {
              pseudo = "::before";
              baseSelector = selector.slice(0, -"::before".length);
            } else if (selector.endsWith("::after")) {
              pseudo = "::after";
              baseSelector = selector.slice(0, -"::after".length);
            }
            const el = document.querySelector(baseSelector);
            if (!el) {
              result[name] = null;
              continue;
            }
            const style = getComputedStyle(el, pseudo);
            const rect = el.getBoundingClientRect();
            const item = {rect: {
              x: Number(rect.x.toFixed(3)),
              y: Number(rect.y.toFixed(3)),
              width: Number(rect.width.toFixed(3)),
              height: Number(rect.height.toFixed(3))
            }};
            for (const prop of props)
              item[prop] = style[prop];
            result[name] = item;
          }
          return result;
        }""",
        {"props": props, "selectors": SELECTORS},
    )


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--css", action="append", default=[], help="CSS file; repeat in load order")
    parser.add_argument("--width", action="append", default=[], help="Viewport width; repeat or comma-separate")
    parser.add_argument("--height", type=int, default=900, help="Viewport height")
    parser.add_argument("--output", help="Write JSON to this file")
    args = parser.parse_args()

    css_paths = [pathlib.Path(path) for path in (args.css or DEFAULT_CSS)]
    for path in css_paths:
        if not path.is_file():
            raise SystemExit(f"CSS file not found: {path}")

    widths = parse_widths(args.width)
    html = html_fixture(css_bundle(css_paths))
    result: Dict[str, Any] = {
        "css": [str(path.resolve()) for path in css_paths],
        "viewports": {},
    }

    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(
            headless=True,
            args=["--no-sandbox", "--disable-dev-shm-usage", "--disable-gpu"],
        )
        page = browser.new_page(viewport={"width": widths[0], "height": args.height})
        page.set_content(html, wait_until="load")
        for width in widths:
            result["viewports"][str(width)] = capture_for_width(page, width, args.height, DEFAULT_PROPS)
        browser.close()

    text = json.dumps(result, ensure_ascii=False, indent=2)
    if args.output:
        pathlib.Path(args.output).write_text(text + "\n", encoding="utf-8")
    else:
        print(text)
    return 0


if __name__ == "__main__":
    sys.exit(main())
