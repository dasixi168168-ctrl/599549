#!/usr/bin/env python3
"""Capture forecast summary computed-style baselines with a real browser."""

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


DEFAULT_PROPS = [
    "display",
    "gridTemplateColumns",
    "gap",
    "columnGap",
    "rowGap",
    "alignItems",
    "alignContent",
    "justifyContent",
    "justifySelf",
    "width",
    "height",
    "maxWidth",
    "minWidth",
    "minHeight",
    "marginTop",
    "marginLeft",
    "paddingTop",
    "paddingRight",
    "paddingBottom",
    "paddingLeft",
    "borderRadius",
    "fontWeight",
    "color",
    "textAlign",
    "whiteSpace",
    "overflowX",
    "overflowY",
    "fontSize",
    "lineHeight",
]


def forecast_summary_lines() -> str:
    return """
<div class="forecast-result-summary-line is-zodiac">
  <span class="forecast-result-summary-head"><span class="forecast-result-summary-label">生肖类型：</span></span>
  <span class="forecast-result-summary-confidence">89.5%</span>
  <span class="forecast-result-summary-body">
    <span class="forecast-result-summary-type"><span class="forecast-result-type-chip">特码:</span></span>
    <span class="forecast-result-summary-value">
      <span class="forecast-result-zodiac-chip">鼠</span>
      <span class="forecast-result-zodiac-chip">牛</span>
      <span class="forecast-result-zodiac-chip">虎</span>
    </span>
  </span>
</div>
<div class="forecast-result-summary-line is-number">
  <span class="forecast-result-summary-head"><span class="forecast-result-summary-label">号码类型：</span></span>
  <span class="forecast-result-summary-confidence">88.0%</span>
  <span class="forecast-result-summary-body">
    <span class="forecast-result-summary-type"><span class="forecast-result-type-chip">六码:</span></span>
    <span class="forecast-result-summary-value">
      <span class="forecast-result-number-chip is-red">01</span>
      <span class="forecast-result-number-chip is-blue">02</span>
      <span class="forecast-result-number-chip is-green">03</span>
    </span>
  </span>
</div>
<div class="forecast-result-summary-line is-other">
  <span class="forecast-result-summary-head"><span class="forecast-result-summary-label">其他类型：</span></span>
  <span class="forecast-result-summary-confidence">76.0%</span>
  <span class="forecast-result-summary-body">
    <span class="forecast-result-summary-type"><span class="forecast-result-type-chip">组合:</span></span>
    <span class="forecast-result-summary-value">
      <span class="forecast-result-other-chip">红波</span>
      <span class="forecast-result-other-chip">小单</span>
    </span>
  </span>
</div>
<div class="forecast-result-summary-line is-pingte">
  <span class="forecast-result-summary-head"><span class="forecast-result-summary-label">平特类型：</span></span>
  <span class="forecast-result-summary-confidence">82.0%</span>
  <span class="forecast-result-summary-body">
    <span class="forecast-result-summary-type"><span class="forecast-result-type-chip">平特:</span></span>
    <span class="forecast-result-summary-value">
      <span class="forecast-result-pingte-text is-group is-size-3"><span class="forecast-result-pingte-bracket">【</span><span class="forecast-result-pingte-number is-red">01</span><span class="forecast-result-pingte-separator">-</span><span class="forecast-result-pingte-number is-blue">02</span><span class="forecast-result-pingte-separator">-</span><span class="forecast-result-pingte-number is-green">03</span><span class="forecast-result-pingte-bracket">】</span></span>
      <span class="forecast-result-pingte-text is-combo"><span class="forecast-result-pingte-bracket">【</span><span class="forecast-result-pingte-number is-red">04</span><span class="forecast-result-pingte-separator">-</span><span class="forecast-result-pingte-number is-blue">05</span><span class="forecast-result-pingte-bracket">】</span></span>
    </span>
  </span>
</div>
<div class="forecast-result-summary-line is-other is-unselected">
  <span class="forecast-result-summary-head"><span class="forecast-result-summary-label">其他类型：</span></span>
  <span class="forecast-result-summary-body">
    <span class="forecast-result-summary-value">
      <span class="forecast-result-placeholder">
        <span class="forecast-result-placeholder-text">请选择类型</span>
        <span class="forecast-result-placeholder-idiom"><span class="forecast-result-placeholder-emoji">✨</span><span>心想事成</span></span>
      </span>
    </span>
  </span>
</div>
<div class="forecast-result-blessing-card">
  <div class="forecast-result-blessing-row">
    <span class="forecast-result-blessing-label" title="寓意词" aria-label="寓意词">✨</span>
    <span class="forecast-result-blessing-text is-idiom">心想事成</span>
  </div>
  <div class="forecast-result-blessing-row">
    <span class="forecast-result-blessing-label" title="寓意语" aria-label="寓意语">🎉</span>
    <span class="forecast-result-blessing-text">好运常伴，步步高升。</span>
  </div>
</div>
"""


def html_fixture(css_path: pathlib.Path) -> str:
    css_text = css_path.read_text(encoding="utf-8")
    css_text = css_text.replace("</style", "<\\/style")
    lines = forecast_summary_lines()
    return f"""<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
{css_text}
  </style>
</head>
<body class="bg-slate-100 text-slate-900 standalone-panel front-unified-panel-page forecast-panel-page" data-region="macau">
  <section class="front-page-shell front-unified-page forecast-page">
    <div class="forecast-filter-row" aria-label="AI预测选项">
      <select class="forecast-filter-select" name="zodiac_type" aria-label="生肖选项"><option>生肖</option></select>
      <select class="forecast-filter-select" name="number_type" aria-label="号码选项"><option>号码</option></select>
      <select class="forecast-filter-select" name="pingte_type" aria-label="平特选项"><option>平特</option></select>
      <select class="forecast-filter-select" name="other_type" aria-label="其他选项"><option>其他</option></select>
    </div>
    <div class="forecast-result-card">
      <div class="forecast-result-scroll">
        <div class="forecast-result-summary">
          {lines}
        </div>
      </div>
    </div>
    <div class="member-ai-log-card">
      <div class="member-ai-log-card-content">
        <div class="forecast-result-summary mt-2">
          {lines}
        </div>
      </div>
    </div>
  </section>
</body>
</html>"""


def parse_widths(values: Iterable[str]) -> List[int]:
    widths: List[int] = []
    for value in values:
        for part in value.split(","):
            part = part.strip()
            if not part:
                continue
            widths.append(int(part))
    return widths or [1024, 640, 390]


def capture_for_width(page: Any, width: int, height: int, props: List[str]) -> Dict[str, Any]:
    page.set_viewport_size({"width": width, "height": height})
    return page.evaluate(
        """({props}) => {
          const result = {};
          for (const scope of ['forecast-page', 'member-ai-log-card']) {
            const root = document.querySelector('.' + scope);
            result[scope] = {};
            result[scope].filter = {};
            for (const [part, selector] of Object.entries({
              row: '.forecast-filter-row',
              select: '.forecast-filter-select'
            })) {
              const el = root.querySelector(selector);
              if (!el) {
                result[scope].filter[part] = null;
                continue;
              }
              const style = getComputedStyle(el);
              const rect = el.getBoundingClientRect();
              const item = {rect: {
                x: Number(rect.x.toFixed(3)),
                y: Number(rect.y.toFixed(3)),
                width: Number(rect.width.toFixed(3)),
                height: Number(rect.height.toFixed(3))
              }};
              for (const prop of props)
                item[prop] = style[prop];
              result[scope].filter[part] = item;
            }
            for (const type of ['zodiac', 'number', 'other', 'pingte']) {
              const line = root.querySelector('.forecast-result-summary-line.is-' + type);
              result[scope][type] = {};
              for (const [part, selector] of Object.entries({
                body: '.forecast-result-summary-body',
                value: '.forecast-result-summary-value',
                typeChip: '.forecast-result-type-chip',
                zodiacChip: '.forecast-result-zodiac-chip',
                numberChip: '.forecast-result-number-chip',
                otherChip: '.forecast-result-other-chip',
                pingteGroup: '.forecast-result-pingte-text.is-group',
                pingteCombo: '.forecast-result-pingte-text.is-combo'
              })) {
                const el = line.querySelector(selector);
                if (!el) {
                  result[scope][type][part] = null;
                  continue;
                }
                const style = getComputedStyle(el);
                const rect = el.getBoundingClientRect();
                const item = {rect: {
                  x: Number(rect.x.toFixed(3)),
                  y: Number(rect.y.toFixed(3)),
                  width: Number(rect.width.toFixed(3)),
                  height: Number(rect.height.toFixed(3))
                }};
                for (const prop of props)
                  item[prop] = style[prop];
                result[scope][type][part] = item;
              }
            }
            const unselected = root.querySelector('.forecast-result-summary-line.is-unselected');
            result[scope].unselected = {};
            for (const [part, selector] of Object.entries({
              line: '.forecast-result-summary-line.is-unselected',
              head: '.forecast-result-summary-head',
              label: '.forecast-result-summary-label',
              body: '.forecast-result-summary-body',
              value: '.forecast-result-summary-value',
              placeholder: '.forecast-result-placeholder',
              placeholderText: '.forecast-result-placeholder-text',
              placeholderIdiom: '.forecast-result-placeholder-idiom'
            })) {
              const el = part === 'line' ? unselected : unselected.querySelector(selector);
              const style = getComputedStyle(el);
              const rect = el.getBoundingClientRect();
              const item = {rect: {
                x: Number(rect.x.toFixed(3)),
                y: Number(rect.y.toFixed(3)),
                width: Number(rect.width.toFixed(3)),
                height: Number(rect.height.toFixed(3))
              }};
              for (const prop of props)
                item[prop] = style[prop];
              result[scope].unselected[part] = item;
            }
            const blessing = root.querySelector('.forecast-result-blessing-card');
            result[scope].blessing = {};
            for (const [part, selector] of Object.entries({
              card: '.forecast-result-blessing-card',
              row: '.forecast-result-blessing-row',
              label: '.forecast-result-blessing-label',
              text: '.forecast-result-blessing-text:not(.is-idiom)',
              idiom: '.forecast-result-blessing-text.is-idiom'
            })) {
              const el = blessing.querySelector(selector) || blessing;
              const style = getComputedStyle(el);
              const rect = el.getBoundingClientRect();
              const item = {rect: {
                x: Number(rect.x.toFixed(3)),
                y: Number(rect.y.toFixed(3)),
                width: Number(rect.width.toFixed(3)),
                height: Number(rect.height.toFixed(3))
              }};
              for (const prop of props)
                item[prop] = style[prop];
              result[scope].blessing[part] = item;
            }
          }
          return result;
        }""",
        {"props": props},
    )


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--css", default="public/styles/style.css", help="CSS file to load")
    parser.add_argument("--width", action="append", default=[], help="Viewport width; repeat or comma-separate")
    parser.add_argument("--height", type=int, default=900, help="Viewport height")
    parser.add_argument("--output", help="Write JSON to this file")
    args = parser.parse_args()

    css_path = pathlib.Path(args.css)
    if not css_path.is_file():
        raise SystemExit(f"CSS file not found: {css_path}")

    widths = parse_widths(args.width)
    html = html_fixture(css_path)
    result: Dict[str, Any] = {"css": str(css_path.resolve()), "viewports": {}}

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
