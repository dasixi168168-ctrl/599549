#!/usr/bin/env bash
set -e

echo "========== 599549 基线保护任务开始 =========="

ROOT_DIR="$(pwd)"
TS="$(date +%F_%H%M%S)"
BACKUP_DIR="/root/599549_baseline_backups"
CODE_BACKUP="$BACKUP_DIR/599549_code_baseline_$TS.tar.gz"
DB_BACKUP="$BACKUP_DIR/599549_db_baseline_$TS.sql"
REPORT_FILE="docs/BASELINE_REPORT.md"

echo "当前目录：$ROOT_DIR"

CHECK_COUNT=0
[ -d "app" ] && CHECK_COUNT=$((CHECK_COUNT+1))
[ -d "config" ] && CHECK_COUNT=$((CHECK_COUNT+1))
[ -d "public" ] && CHECK_COUNT=$((CHECK_COUNT+1))
[ -f "index.php" ] && CHECK_COUNT=$((CHECK_COUNT+1))
[ -f "composer.json" ] && CHECK_COUNT=$((CHECK_COUNT+1))
[ -d "database" ] && CHECK_COUNT=$((CHECK_COUNT+1))
[ -d "resources" ] && CHECK_COUNT=$((CHECK_COUNT+1))

if [ "$CHECK_COUNT" -lt 2 ]; then
  echo "错误：当前目录不像项目根目录，已停止。"
  exit 1
fi

mkdir -p "$BACKUP_DIR"
mkdir -p docs
mkdir -p tools

echo "第一步：创建代码备份包"

TAR_EXCLUDE_ARGS=()
if [ -f ".deployignore" ]; then
  TAR_EXCLUDE_ARGS+=(--exclude-from=.deployignore)
fi

tar \
  "${TAR_EXCLUDE_ARGS[@]}" \
  --exclude='./.git' \
  --exclude='./vendor' \
  --exclude='./node_modules' \
  --exclude='./storage/logs/*' \
  --exclude='./storage/cache/*' \
  --exclude='./storage/sessions/*' \
  --exclude='./runtime/*' \
  --exclude='./public/uploads/*' \
  --exclude='./storage/uploads/*' \
  -czf "$CODE_BACKUP" .

echo "代码备份完成：$CODE_BACKUP"

echo "第二步：尝试备份数据库"

DB_STATUS="未备份"
DB_REASON="未发现 .env 数据库配置或 mysqldump 不可用"

if [ -f ".env" ] && command -v mysqldump >/dev/null 2>&1; then
  DB_HOST="$(grep -E '^DB_HOST=' .env | tail -1 | cut -d= -f2- | sed "s/^['\"]//;s/['\"]$//")"
  DB_NAME="$(grep -E '^DB_DATABASE=' .env | tail -1 | cut -d= -f2- | sed "s/^['\"]//;s/['\"]$//")"
  DB_USER="$(grep -E '^DB_USERNAME=' .env | tail -1 | cut -d= -f2- | sed "s/^['\"]//;s/['\"]$//")"
  DB_PASS="$(grep -E '^DB_PASSWORD=' .env | tail -1 | cut -d= -f2- | sed "s/^['\"]//;s/['\"]$//")"

  [ -z "$DB_HOST" ] && DB_HOST="localhost"

  if [ -n "$DB_NAME" ] && [ -n "$DB_USER" ]; then
    if MYSQL_PWD="$DB_PASS" mysqldump --single-transaction --quick --lock-tables=false -h "$DB_HOST" -u "$DB_USER" "$DB_NAME" > "$DB_BACKUP" 2>/tmp/599549_mysqldump_error.log; then
      DB_STATUS="已备份"
      DB_REASON="$DB_BACKUP"
      echo "数据库备份完成：$DB_BACKUP"
    else
      rm -f "$DB_BACKUP"
      DB_STATUS="备份失败"
      DB_REASON="$(cat /tmp/599549_mysqldump_error.log 2>/dev/null | head -5)"
      echo "数据库备份失败：$DB_REASON"
    fi
  fi
else
  echo "未执行数据库备份：没有 .env 或 mysqldump 不可用"
fi

echo "第三步：创建 .gitignore 保护规则"

touch .gitignore

if ! grep -q "599549 baseline protection" .gitignore; then
cat >> .gitignore <<'EOF'

# 599549 baseline protection
.env
*.log
/vendor/
/node_modules/
/storage/logs/
/storage/cache/
/storage/sessions/
/runtime/
/public/uploads/
/storage/uploads/
/deploy_packages/
.DS_Store
Thumbs.db
EOF
fi

echo "第四步：创建冻结范围文件"

cat > docs/FREEZE_SCOPE.md <<'EOF'
# 599549 已完成冻结范围

这个文件用于防止“改这里坏那里”。

## 默认冻结内容

以下内容默认禁止随便修改：

- 前台首页结构
- 澳门首页结构
- 香港首页结构
- 顶部 Logo 区
- 下载 APP 区
- 主视觉 Banner
- 开奖卡片
- 开奖期号
- 开奖时间
- 开奖结果
- 公告区域
- 日期时间区域
- 资料区
- 底部导航
- 预测入口
- 客服入口
- 我的页面入口
- 后台登录
- 后台菜单
- 后台权限
- 后台列表
- 后台筛选
- 后台分页
- 已确认正常的 CSS
- 已确认正常的 JS
- 已确认正常的接口地址
- 已确认正常的数据库字段
- 澳门 / 香港开奖时间独立逻辑
- 澳门 / 香港开奖结果独立逻辑
- 澳门 / 香港期号独立逻辑
- 澳门 / 香港开奖数据来源独立逻辑

## 修改规则

没有明确说明，不允许修改冻结内容。

如果必须修改冻结内容，必须先说明：

- 为什么必须改
- 会影响哪些页面
- 会影响哪些功能
- 是否影响澳门
- 是否影响香港
- 是否影响后台
- 是否影响数据库
- 怎么回滚
- 改完要测试哪些地方

## 禁止行为

- 不准为了修一个小问题全站大改。
- 不准为了美化页面改业务逻辑。
- 不准为了优化速度乱删功能。
- 不准改澳门时影响香港。
- 不准改香港时影响澳门。
- 不准改前台时破坏后台。
- 不准改后台时破坏前台。
- 不准没看清楚代码就直接新增补丁。
- 不准一次处理多个无关问题。
EOF

echo "第五步：创建改动记录文件"

cat > docs/CHANGE_LOG.md <<'EOF'
# 599549 改动记录

每次修改必须记录。

## 固定记录格式

### 日期：
### 修改目标：
### 修改原因：
### 修改文件：
### 是否影响澳门：
### 是否影响香港：
### 是否影响后台：
### 是否影响数据库：
### 是否影响接口：
### 是否影响权限：
### 测试页面：
### 测试结果：
### Git 提交号：
### 是否可以上线：

---

## 示例

### 日期：2026-06-22
### 修改目标：修复首页手机端底部导航遮挡内容
### 修改原因：手机端底部固定栏盖住资料区最后内容
### 修改文件：
- public/css/mobile.css
- app/Views/home/index.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页
- 香港首页
- 预测页
- 客服页
- 我的页面

### 测试结果：通过
### Git 提交号：
### 是否可以上线：是
EOF

echo "第六步：创建测试清单文件"

cat > docs/TEST_CHECKLIST.md <<'EOF'
# 599549 每次修改后的测试清单

每次改代码后，必须照这个清单检查。

## 一、前台手机端测试

- [ ] 澳门首页能打开
- [ ] 香港首页能打开
- [ ] 顶部 Logo / 下载 APP 区正常
- [ ] 主视觉 Banner 正常
- [ ] 开奖卡片显示正常
- [ ] 开奖期号显示正常
- [ ] 开奖时间显示正常
- [ ] 开奖结果显示正常
- [ ] 公告栏显示正常
- [ ] 日期时间区显示正常
- [ ] 资料区显示正常
- [ ] 底部导航不遮挡内容
- [ ] 点击澳门正常
- [ ] 点击香港正常
- [ ] 点击预测正常
- [ ] 点击客服正常
- [ ] 点击我的正常
- [ ] 页面上下滑动不卡死
- [ ] 按钮点击有反应

## 二、前台电脑端测试

- [ ] 首页能打开
- [ ] 页面没有错位
- [ ] 内容没有挤压
- [ ] 图片加载正常
- [ ] 导航点击正常
- [ ] 页面没有明显卡顿

## 三、后台测试

- [ ] 后台能登录
- [ ] 后台菜单能打开
- [ ] 列表能显示
- [ ] 分页能点击
- [ ] 筛选能使用
- [ ] 新增功能正常
- [ ] 编辑功能正常
- [ ] 保存后不报错
- [ ] 删除或状态切换有确认
- [ ] 澳门 / 香港数据没有串区

## 四、性能测试

- [ ] 手机刷新首页不明显卡顿
- [ ] 连续点击底部导航不卡死
- [ ] 页面没有一直转圈
- [ ] 浏览器控制台没有大量报错
- [ ] 接口没有重复疯狂请求
- [ ] 后台列表没有一次性加载大量数据

## 五、禁止继续开发新功能的情况

出现以下任何一个问题，不允许继续开发新功能：

- 页面白屏
- 500 错误
- 404 错误
- 后台无法登录
- 数据保存失败
- 澳门 / 香港数据串了
- 开奖时间错了
- 开奖结果错了
- 手机端明显卡顿
- 修改一个地方导致另一个已完成页面坏掉
EOF

echo "第七步：创建页面定稿说明"

cat > docs/PAGE_FINAL.md <<'EOF'
# 599549 页面定稿说明

## 首页定稿

首页必须包含：

- 顶部 Logo 区
- 下载 APP 按钮
- 主视觉 Banner
- 开奖卡片
- 公告栏
- 日期时间区域
- 资料区
- 底部导航

底部导航包含：

- 澳门
- 香港
- 预测
- 客服
- 我的

首页默认不再改变整体结构，只允许修复：

- 错位
- 遮挡
- 卡顿
- 数据错误
- 手机端适配问题
- 小范围样式问题

## 澳门 / 香港规则

澳门和香港页面结构默认一致。

允许不同：

- 系统名称
- 开奖数据
- 开奖时间
- 开奖期号
- 必要品牌文案

不允许不同：

- 页面结构乱变
- 按钮位置乱变
- 底部导航乱变
- 后台管理逻辑乱变

## 后台定稿

后台默认保持：

- 登录逻辑不变
- 权限逻辑不变
- 菜单结构不乱改
- 列表必须分页
- 筛选必须保留
- 新增、编辑、删除必须有反馈
- 澳门 / 香港数据必须区分

## 修改原则

页面已经定稿后，不能因为一个小问题重做整页。

只能做最小必要修改。
EOF

echo "第八步：创建 Codex 开发规则文件"

cat > docs/CODEX_WORKFLOW.md <<'EOF'
# 599549 Codex 开发规则

以后每次让 Codex 修改代码，必须遵守：

## 一次只处理一个问题

禁止一次同时修多个无关问题。

正确示例：

本次只修手机端首页底部导航遮挡资料区内容。

错误示例：

顺便优化首页、后台、客服、我的页面、香港同步和性能。

## 修改前必须先看

- docs/FREEZE_SCOPE.md
- docs/TEST_CHECKLIST.md
- docs/CHANGE_LOG.md

## 每次修改必须说明

- 本次问题是什么
- 相关文件有哪些
- 哪些文件允许改
- 哪些文件禁止改
- 是否影响澳门
- 是否影响香港
- 是否影响后台
- 是否影响数据库
- 是否影响接口
- 如何测试
- 如何回滚

## 禁止

- 没找到原因就新增补丁
- 修 UI 时改业务逻辑
- 修性能时乱删功能
- 修澳门时弄坏香港
- 修香港时弄坏澳门
- 改前台时破坏后台
- 改后台时破坏前台
- 一次性重构全站
- 修改冻结内容
- 修改数据库结构
- 修改接口结构
- 修改登录权限
- 修改开奖独立逻辑

## 完成后必须输出

- 修改文件列表
- 每个文件为什么改
- 测试结果
- 是否建议提交 Git
EOF

echo "第九步：创建基线报告"

cat > "$REPORT_FILE" <<EOF
# 599549 基线报告

## 执行时间

$TS

## 项目根目录

$ROOT_DIR

## 代码备份

$CODE_BACKUP

说明：代码备份默认排除了 .git、vendor、node_modules、日志、缓存、session、uploads 目录。

## 数据库备份状态

$DB_STATUS

## 数据库备份说明

$DB_REASON

## 本次新增/修改的保护文件

- .gitignore
- docs/FREEZE_SCOPE.md
- docs/CHANGE_LOG.md
- docs/TEST_CHECKLIST.md
- docs/PAGE_FINAL.md
- docs/CODEX_WORKFLOW.md
- docs/BASELINE_REPORT.md
- tools/create_baseline.sh

## 注意

本次任务只建立基线保护，不修改业务代码、不修页面、不修卡顿、不改数据库结构。
EOF

echo "第十步：初始化本地 Git 并创建 baseline 提交"

if [ ! -d ".git" ]; then
  git init
fi

if ! git config user.name >/dev/null; then
  git config user.name "599549-local"
fi

if ! git config user.email >/dev/null; then
  git config user.email "599549@local"
fi

git add -A

if git diff --cached --quiet; then
  echo "没有需要提交的变更。"
else
  git commit -m "baseline: 599549 stabilization baseline $TS"
fi

GIT_HASH="$(git rev-parse --short HEAD 2>/dev/null || echo 'none')"
TAG_NAME="baseline-$TS"

if [ "$GIT_HASH" != "none" ]; then
  git tag -a "$TAG_NAME" -m "599549 baseline $TS" 2>/dev/null || true
fi

echo "========== 599549 基线保护任务完成 =========="
echo "项目根目录：$ROOT_DIR"
echo "代码备份：$CODE_BACKUP"
echo "数据库备份状态：$DB_STATUS"
echo "数据库备份说明：$DB_REASON"
echo "Git 提交号：$GIT_HASH"
echo "Git 标签：$TAG_NAME"
echo "报告文件：$REPORT_FILE"
