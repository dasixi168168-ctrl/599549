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

### 日期：2026-07-14
### 修改目标：取消接待客服端澳门 / 香港首页帖子弹窗复用后台阅读源
### 修改原因：用户明确取消 2026-07-13 的同名任务，接待客服端帖子弹窗恢复使用任务前的前台可见内容路径；下方 2026-07-13 记录仅保留原实施历史
### 修改文件：
- app/Services/PostService.php
- app/Views/admin/posts_forum.php
- public/post.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是，仅撤销接待客服端澳门首页帖子弹窗的后台阅读源覆盖
### 是否影响香港：是，仅撤销接待客服端香港首页帖子弹窗的后台阅读源覆盖
### 是否影响后台：后台帖子弹窗恢复任务前的字段优先级内联读取，显示行为不变
### 是否影响数据库：代码变更不影响数据库；实机验证按授权更新测试账号登录记录和客服在线状态，退出后在线状态已清理。首次失败的浏览器流程额外生成 1 条澳门接待页访问统计 `page_views.id=73712`，未擅自删除
### 是否影响接口：否
### 是否影响权限：否，继续复用原客服会话校验和出售帖免积分查看流程
### 测试页面：
- 普通澳门首页：/public/index.php
- 普通香港首页：/public/record.php
- 接待客服端澳门首页：/public/index.php?agent=1
- 接待客服端香港首页：/public/record.php?agent=1
- 后台帖子管理：/public/admin.php?page=posts&region=macau
- 后台帖子管理：/public/admin.php?page=posts&region=hongkong
### 测试结果：当前环境经用户授权完成无头 Chromium 实机验证。澳门普通帖 `3281`、出售帖 `3250`，香港普通帖 `3175`、出售帖 `3153` 均验证 1280x900 与 390x844 弹窗；接待客服身份识别正常，两条出售帖均返回会话级免积分成功。为避免新增帖子浏览记录，帖子 iframe 使用现有 `admin_history=1` 只读模式，首页弹窗壳仍由现有 `home-legacy.js` 实际创建；四个后台帖子弹窗同时完成来源、日期时间和历史 iframe 验证。无 HTTP 4xx/5xx、无控制台错误、无页面脚本异常、无横向溢出。测试前后四条帖子的正文哈希、更新时间、购买计数、购买记录、唯一浏览记录和展示事件均一致；客服在线状态已清理。PHP 语法检查、diff 空白检查和移除引用搜索通过
### Git 提交号：未提交
### 是否可以上线：本次取消逻辑已完成当前授权环境验证，可以提交；首次测试产生的单条页面访问统计仅属统计噪声，不影响业务上线

---

### 日期：2026-07-13
### 修改目标：接待客服端澳门 / 香港首页帖子弹窗复用后台阅读源
### 修改原因：用户要求仅将有效客服会话下的澳门 / 香港首页帖子弹窗切换为后台帖子弹窗的内容来源，普通前台首页保持原来源
### 修改文件：
- app/Services/PostService.php
- app/Views/admin/posts_forum.php
- public/post.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是，仅接待客服端澳门首页帖子弹窗
### 是否影响香港：是，仅接待客服端香港首页帖子弹窗
### 是否影响后台：是，后台帖子弹窗改为调用同一公共阅读源，原字段优先级不变
### 是否影响数据库：否，仅进行读取
### 是否影响接口：否
### 是否影响权限：否，继续复用现有客服会话校验和出售帖访问流程
### 测试页面：
- 普通澳门首页：/public/index.php
- 普通香港首页：/public/record.php
- 接待客服端澳门首页：/public/index.php?agent=1
- 接待客服端香港首页：/public/record.php?agent=1
- 后台帖子管理：/public/admin.php?page=posts&region=macau
- 后台帖子管理：/public/admin.php?page=posts&region=hongkong
### 测试结果：PHP 语法与差异检查通过；澳门 / 香港公共源只读样本通过；普通前台 PC / 移动端、无客服会话权限边界、后台两地区阅读弹窗浏览器验证通过。使用现有客服测试账号正常登录后，以禁用缓存的网络导航强刷两地区客服首页，分别打开澳门普通帖 3281、出售帖 3250、香港普通帖 3175、出售帖 3153；客服权限、地区隔离、后台来源标记、出售帖会话免积分解锁、桌面端与 390px 手机端弹窗边界均通过，无控制台、HTTP 或非主动中止请求错误，验证后已正常退出接待
### Git 提交号：未提交
### 是否可以上线：本次模块验证通过；当前工作区其他未提交改动仍需整体回归后再决定上线

---

### 日期：2026-06-28
### 修改目标：修正接待在线客服端邀请奖励系统消息文案
### 修改原因：用户反馈接待在线客服端看到“您的邀请好友”含义错误，容易理解成客服自己的邀请好友
### 修改文件：
- app/Views/front/service.php
- public/assets/app.js
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 接待在线客服会话窗口：/public/service.php?region=macau&agent=1
- 接待在线客服会话窗口：/public/service.php?region=hongkong&agent=1
- 会员客服会话：/public/service.php?region=macau
### 测试结果：PHP 语法检查通过；当前环境无 node/nodejs，JS 改动片段已人工复查
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-28
### 修改目标：修复邀请好友奖励通知重复显示
### 修改原因：用户反馈邀请好友注册成功奖励通知在客服会话窗口重复出现
### 修改文件：
- app/Services/SupportService.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 接待在线客服会话窗口：/public/service.php?region=macau&agent=1
- 接待在线客服会话窗口：/public/service.php?region=hongkong&agent=1
- 会员客服会话：/public/service.php?region=macau
### 测试结果：PHP 语法检查通过；未执行真实注册写入测试，避免新增生产会员和积分记录
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-28
### 修改目标：会员中心论坛指南说明接入客服设置维护
### 修改原因：用户要求“邀请好友规则 / 充值规则 / 购买规则 / 遵守规则”说明正文可在接待在线客服端客服设置弹窗维护
### 修改文件：
- app/Services/SupportService.php
- app/Views/front/member_portal.php
- app/Views/front/service.php
- public/styles/style.css
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否（使用现有 settings 表）
### 是否影响接口：否（沿用现有客服设置保存 action）
### 是否影响权限：否
### 测试页面：
- 接待在线客服端：/public/service.php?region=macau&agent=1
- 会员中心论坛指南：/public/member.php?region=macau&tab=about
- 会员中心论坛指南：/public/member.php?region=hongkong&tab=about
### 测试结果：PHP 语法检查通过；CLI 只读读取论坛指南默认配置正常
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-28
### 修改目标：统一预测入口图标
### 修改原因：用户要求预测图标写法按 AI 预测页黄色标题区图标统一
### 修改文件：
- app/Views/partials/front_bottom_nav.php
- app/Views/front/member_portal.php
- public/styles/style.css
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- AI预测页：/public/forecast.php?region=macau
- 会员中心：/public/member.php?region=macau
- 会员中心：/public/member.php?region=hongkong
### 测试结果：app/Views/partials/front_bottom_nav.php 与 app/Views/front/member_portal.php 语法检查通过；仅统一预测入口图标写法，未修改预测业务逻辑
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-28
### 修改目标：邀请好友注册成功后通知邀请会员
### 修改原因：用户要求邀请好友注册成功并发放奖励后，在邀请会员的客服会话窗口显示通知
### 修改文件：
- app/Services/UserService.php
- app/Services/SupportService.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员注册：/public/member.php?region=macau&mode=register
- 香港会员注册：/public/member.php?region=hongkong&mode=register
- 会员客服会话：/public/service.php?region=macau
### 测试结果：PHP 语法检查通过；未执行真实注册写入测试，避免生产数据新增测试会员
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-28
### 修改目标：开发邀请好友注册送积分业务
### 修改原因：用户要求邀请好友注册后给邀请人赠送积分，并将奖励积分接入后台会员管理“注册规则设置”
### 修改文件：
- app/Services/UserService.php
- app/Services/AdminService.php
- public/admin.php
- public/member.php
- app/Views/admin/users.php
- app/Views/front/member_portal.php
- app/Views/layouts/admin.php
- app/Views/layouts/home_legacy.php
- public/assets/app.css
- public/styles/style.css
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否（注册接口仅接收新增可选字段，原有参数不变）
### 是否影响权限：否
### 测试页面：
- 后台会员管理：/public/admin.php?page=users
- 澳门会员注册：/public/member.php?region=macau&mode=register
- 香港会员注册：/public/member.php?region=hongkong&mode=register
- 会员中心个人资料：/public/member.php?region=macau&tab=profile
### 测试结果：PHP 语法检查通过；未执行真实注册写入测试，避免生产数据新增测试会员
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-28
### 修改目标：会员中心“关于我们”改为“论坛指南”
### 修改原因：用户要求会员中心页面将“关于我们”文案改为“论坛指南”
### 修改文件：
- app/Views/front/member_portal.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 会员中心：/public/member.php?region=macau
- 会员中心：/public/member.php?region=hongkong
### 测试结果：仅修改会员中心标签和标题文案；功能结构、接口和权限不变
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-28
### 修改目标：客服设置弹窗输入控件字重调整
### 修改原因：用户要求客服设置弹窗输入框控件正文字号粗细为 500，业务保持不变
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 接待在线客服：/public/service.php?region=macau&agent=1
- 接待在线客服：/public/service.php?region=hongkong&agent=1
### 测试结果：客服设置弹窗输入框与文本域正文样式覆盖为 500 字重；未修改客服设置保存逻辑
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-28
### 修改目标：客服设置弹窗布局比例调整
### 修改原因：用户要求“客服设置”弹窗页面调整布局视觉比例协调，业务保持不变
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 接待在线客服：/public/service.php?region=macau&agent=1
- 接待在线客服：/public/service.php?region=hongkong&agent=1
### 测试结果：客服设置弹窗卡片、顶部字段、文本区和保存按钮仅做样式比例调整；未修改客服设置提交字段和保存逻辑
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：收紧会员注册规则控件宽度
### 修改原因：用户要求“新注册赠送积分 / IP/设备重复注册限制”控件宽度收紧协调，业务保持不变
### 修改文件：
- public/assets/app.css
- app/Views/layouts/admin.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 会员管理：/public/admin.php?page=users
### 测试结果：注册规则表单控件改为固定协调列宽并左对齐；仅调整样式，未修改保存逻辑
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：删除会员注册规则说明卡片
### 修改原因：用户要求删除“n 天内同 IP 或同设备不得重复注册；填 0 表示关闭。已注册会员积分不自动变更。”说明卡片，业务保持不变
### 修改文件：
- app/Views/admin/users.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 会员管理：/public/admin.php?page=users
### 测试结果：删除注册规则说明卡片；注册规则输入框和保存按钮保留，未修改注册规则业务
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：会员列表正文粗细与视觉比例调整
### 修改原因：用户要求后台会员管理“会员列表”表头下方正文粗细为 500，并调整会员列表内部显示比例协调
### 修改文件：
- public/assets/app.css
- app/Views/layouts/admin.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 会员管理：/public/admin.php?page=users
### 测试结果：会员列表样式限定在后台会员页；表头下方正文与列表内部重点文字统一为 500 字重，未修改会员业务逻辑
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：会员注册信息三类来源颜色区分
### 修改原因：用户要求后台会员管理“注册信息”里的省份、城市、运营商三类内容用颜色区分，提升辨识度
### 修改文件：
- app/Views/admin/users.php
- public/assets/app.css
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 会员管理：/public/admin.php?page=users
### 测试结果：app/Views/admin/users.php 语法检查通过；会员列表服务仍返回“省份 / 城市 / 运营商”三段式注册信息
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：会员注册信息追加运营商标识
### 修改原因：用户要求后台会员管理“注册信息”的“省份 / 城市”后面增加访问网站的运营商标识，便于区分移动、联通、电信等来源
### 修改文件：
- app/Core/Security.php
- app/Services/AdminService.php
- app/Views/admin/users.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 会员管理：/public/admin.php?page=users
### 测试结果：PHP 语法检查通过；后台会员列表服务返回“省份 / 城市 / 运营商”三段式注册信息
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：修正会员注册来源历史 IP 解析方式
### 修改原因：用户反馈会员实际来源与后台“注册信息”显示不一致；排查发现后台列表解析历史会员 IP 时不应读取当前请求头地理位置，本次新增按指定 IP 解析方法，并让会员列表来源兜底使用历史 IP 本身解析
### 修改文件：
- app/Core/Security.php
- app/Services/AdminService.php
- app/Views/admin/users.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 会员管理：/public/admin.php?page=users
### 测试结果：app/Core/Security.php、app/Services/AdminService.php、app/Views/admin/users.php 语法检查通过；hlvhjhhjvvgv 的注册/登录/访问日志均记录为 220.200.126.152，按指定 IP 解析为“福建省 / 福州市”，未发现服务器日志中有广西 IP
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：修正会员列表注册来源页面仍显示未知
### 修改原因：服务层已经可根据注册/登录 IP 解析真实省市，但页面在旧字段仍为未知时缺少最终渲染兜底，导致部分移动网络注册会员仍显示“未知省份 / 未知城市”
### 修改文件：
- app/Views/admin/users.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 会员管理：/public/admin.php?page=users
### 测试结果：app/Views/admin/users.php 语法检查通过；最新会员列表服务返回真实省市；模拟未知来源会员模板渲染为“广西壮族自治区 / 玉林市”
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：删除批量删除会员按键外层卡片
### 修改原因：用户反馈会员管理里“批量删除会员按键”仍有外层卡片视觉；本次仅取消会员列表工具条白底、边框、圆角和内边距，保留批量删除、关键词、输入框、搜索同排结构和业务逻辑
### 修改文件：
- public/assets/app.css
- app/Views/layouts/admin.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 会员管理：/public/admin.php?page=users
### 测试结果：app/Views/layouts/admin.php 语法检查通过；后台 CSS 版本号已更新到 20260627-member-batch-delete-04；本次未修改批量删除提交、CSRF、权限、数据库或接口
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：修正会员列表注册来源仍有未知
### 修改原因：用户确认执行低风险方案；部分会员最早登录记录或访问记录没有可用省市时，会员列表未继续使用最近登录 IP/省份和访问日志做兜底，导致仍显示“未知省份 / 未知城市”
### 修改文件：
- app/Services/AdminService.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 会员管理：/public/admin.php?page=users
### 测试结果：app/Services/AdminService.php 语法检查通过；会员列表注册来源解析已纳入最早登录 IP、最近登录 IP、登录省份、访问日志 user_id/IP 兜底；未修改数据库结构、接口、权限、登录认证或开奖逻辑
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：会员列表增加批量删除会员
### 修改原因：用户要求后台会员管理“会员列表”支持勾选多个会员后批量删除；本次复用后台会员管理权限和 CSRF 校验，补齐原单个删除服务方法，并新增批量删除入口
### 修改文件：
- app/Services/AdminService.php
- app/Views/admin/users.php
- app/Views/layouts/admin.php
- public/admin.php
- public/assets/app.css
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 会员管理：/public/admin.php?page=users
### 测试结果：app/Services/AdminService.php、public/admin.php、app/Views/admin/users.php、app/Views/layouts/admin.php 语法检查通过；批量删除入口复用 users.manage 权限和 admin.users CSRF；删除前会先校验全部勾选会员，存在发帖内容或管理员账号时不执行删除；会员批量删除、关键词标题、输入框和搜索按钮已合并到同一个会员列表工具条，已删除批量删除说明胶囊，并更新后台 CSS 版本号；未执行真实删除，避免误删生产会员；本次未修改数据库结构、接口、权限、登录认证或开奖逻辑
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：修复后台会员管理注册信息位置一直显示未知
### 修改原因：用户反馈会员管理“注册信息”列均显示“未知省份 / 未知城市”；本次让后台会员列表复用已有访问日志中的省市作为兜底，优化代理环境下真实公网 IP 的选择顺序，并补齐 GeoLite 中国 IP 只有经纬度时的省市兜底解析，业务逻辑保持不变
### 修改文件：
- app/Core/Security.php
- app/Services/AdminService.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 会员管理：/public/admin.php?page=users
- 澳门会员入口：/public/member.php?region=macau
- 香港会员入口：/public/member.php?region=hongkong
### 测试结果：app/Core/Security.php 与 app/Services/AdminService.php 语法检查通过；diff 空白检查通过；已确认服务器存在 GeoLite2-City.mmdb，代理链 IP 选择会优先取公网 IP；截图会员 ghjnbbb 的后台列表服务输出已从未知修正为“河南省 / 郑州”；本次未修改数据库结构、接口、权限、登录认证或开奖逻辑
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：去除电脑端接待在线客服会话输入框下方留空
### 修改原因：用户反馈电脑端“接待在线客服会话窗口”输入框下方有多余留空；本次仅取消坐席端会话卡片内部重复的底部预留，保留页面对底部悬浮导航的整体预留，客服收发业务不变
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 接待在线客服会话窗口：/public/service.php?region=macau&agent=1&status=all&agent_view=chat
- 接待在线客服会话窗口：/public/service.php?region=hongkong&agent=1&status=all&agent_view=chat
### 测试结果：app/Views/layouts/home_legacy.php 与 app/Views/front/service.php 语法检查通过；diff 空白检查通过；已确认本次仅取消电脑端坐席会话卡片内部重复底部预留，未修改客服消息收发接口、登录权限、数据库或开奖逻辑
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：降低前台公共页面首次访问与切换等待
### 修改原因：用户反馈澳门、香港、预测、客服、登录/会员中心/管理等前台公共端首次访问和切换响应慢；本次仅移除前台启动阶段无用的后台专用初始化扫描，并取消公共 JS 预加载抢占首屏资源优先级，业务逻辑保持不变
### 修改文件：
- public/assets/app.js
- app/Views/layouts/home_legacy.php
- docs/CHANGE_LOG.md

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php
- 香港首页：/public/record.php
- 澳门预测：/public/forecast.php?region=macau
- 香港预测：/public/forecast.php?region=hongkong
- 客服页：/public/service.php?region=macau
- 会员/登录页：/public/member.php?region=macau
### 测试结果：app/Views/layouts/home_legacy.php 语法检查通过；diff 空白检查通过；已确认本次仅调整前台公共 JS 启动范围和资源加载优先级，未修改页面业务结构、接口、数据库、权限、登录或开奖逻辑；当前服务器缺少 node/nodejs，未能执行 JS 静态检查
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：降低资料更新编辑器加载等待感
### 修改原因：用户反馈“澳门资料更新 / 香港资料更新 / 组件管理”编辑器加载响应慢；本次仅调整后台资料编辑器启动节奏和加载占位，避免首屏被大块“编辑器加载中”遮挡，并把首轮编辑辅助控件扫描延后到浏览器空闲时执行
### 修改文件：
- app/Views/admin/draws.php
- public/assets/app.css
- app/Views/layouts/admin.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台资料更新：/public/admin.php?page=draws&mode=material&region=macau
- 共用后台资料更新：/public/admin.php?page=draws&mode=material&region=hongkong
- 共用后台组件管理：/public/admin.php?page=draws&mode=component&region=macau
### 测试结果：app/Views/admin/draws.php 与 app/Views/layouts/admin.php 语法检查通过；diff 空白检查通过；已确认本次仅调整后台资料编辑器启动节奏和加载占位，未修改保存接口、资料内容结构、澳门/香港开奖数据逻辑；当前服务器缺少 node/nodejs，未能执行 JS 静态检查
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：删除前后台设置页重复的“新注册赠送积分”
### 修改原因：用户反馈后台“前后台设置”页与会员管理页存在重复的“新注册赠送积分”设置；本次仅移除前后台设置页入口，保留会员管理页“注册规则设置”为唯一调整入口
### 修改文件：
- app/Views/admin/settings.php
- public/admin.php
- app/Services/AdminService.php
- public/assets/app.js
- app/Views/layouts/admin.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台前后台设置：/public/admin.php?page=settings
- 共用后台会员管理：/public/admin.php?page=users
### 测试结果：app/Views/admin/settings.php、public/admin.php、app/Services/AdminService.php、app/Views/layouts/admin.php 语法检查通过；diff 空白检查通过；已确认前后台设置页不再渲染“新注册赠送积分”，系统设置保存不再写 points.register_bonus，会员管理页注册规则设置仍保留
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-27
### 修改目标：主卡片 #1 占位尺寸接入弹窗编辑设置
### 修改原因：用户要求“主卡片 #1”的占位尺寸可以在广告设置弹窗内调整；本次仅新增主卡片 #1 弹窗宽高输入，并让前台与后台编辑器预览读取保存后的占位宽高变量
### 修改文件：
- app/Views/admin/draws.php
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/layouts/admin.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台资料更新：/public/admin.php?page=draws
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/index.php?region=hongkong
### 测试结果：app/Views/admin/draws.php、app/Views/layouts/admin.php、app/Views/layouts/home_legacy.php 语法检查通过；diff 空白检查通过；已确认弹窗新增占位宽度/高度字段、保存写入 --home-hero-editor-width/height 与比例变量、前台与后台编辑器预览读取变量
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-26
### 修改目标：精简主卡片 #1 背景弹窗控件并调整预览比例
### 修改原因：用户要求主卡片 #1 删除“当前卡片文字大小 / 当前卡片文字颜色”，并调整背景预览图片视觉比例协调；本次仅隐藏主卡片 #1 无用文字控件，预览图按 708×286 比例显示
### 修改文件：
- app/Views/admin/draws.php
- public/assets/app.css
- app/Views/layouts/admin.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：app/Views/admin/draws.php 与 app/Views/layouts/admin.php 语法检查通过；diff 空白检查通过；已确认主卡片 #1 弹窗文字大小/颜色控件隐藏逻辑、背景预览 708×286 比例和后台 CSS 版本 20260626-hero-preview-fields-01 生效
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-26
### 修改目标：补充主卡片 #1 版头背景占位尺寸备注
### 修改原因：用户要求主卡片 #1 占位尺寸备注显示在“版头背景图片”后面，方便上传背景图时按当前占位尺寸参考
### 修改文件：
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：app/Views/admin/draws.php 语法检查通过；diff 空白检查通过；已确认“版头背景图片”后显示主卡片 #1 占位约 708×286、上传后拉满备注
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-26
### 修改目标：主卡片 #1 背景图片拉满占位
### 修改原因：用户确认主卡片 #1 背景图需要拉满整个占位，不要 contain 留边；本次仅把主卡片 #1 背景图适配从完整显示改为 100% 100% 拉满占位，并同步后台编辑弹窗预览
### 修改文件：
- app/Views/admin/draws.php
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/index.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：app/Views/admin/draws.php 与 app/Views/layouts/home_legacy.php 语法检查通过；diff 空白检查通过；已确认前台和后台编辑器预览 CSS 版本更新为 20260626-hero-bg-fill-01，主卡片 #1 背景写入和最终 CSS 覆盖均为 100% 100% 拉满占位
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-26
### 修改目标：修正主卡片 #1 版头背景图片被裁切
### 修改原因：用户反馈主卡片 #1 的版头背景图片过大时没有按主卡片占位完整显示，被 `center/cover` 裁切；本次仅把主卡片 #1 背景图适配改为完整居中显示，并同步后台编辑弹窗预览
### 修改文件：
- app/Views/admin/draws.php
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/index.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：app/Views/admin/draws.php 与 app/Views/layouts/home_legacy.php 语法检查通过；diff 空白检查通过；已确认前台和后台编辑器预览 CSS 版本更新为 20260626-hero-bg-contain-01，主卡片 #1 背景写入由 cover 改为 contain
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-26
### 修改目标：修正后台编辑器全屏开奖结果小卡片结构
### 修改原因：用户反馈编辑器全屏时顶部开奖结果卡片视觉不协调；本次仅调整全屏工具栏内开奖结果预览小卡片的 CSS 布局，避免期号、号码球和生肖被挤压裁切
### 修改文件：
- public/assets/app.css
- app/Views/layouts/admin.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 后台资料更新：/public/admin.php?page=draws
### 测试结果：php80 -l app/Views/layouts/admin.php 通过；git diff --check 通过；已确认后台 CSS 版本号更新为 20260626-draw-fullscreen-live-sync-01
### Git 提交号：未提交
### 是否可以上线：是

---

### 日期：2026-06-26
### 修改目标：收紧电脑端高手榜区右侧统计字号
### 修改原因：用户反馈高手榜区右侧“n中n / 浏览量”正文偏大，和帖子标题比例不协调；本次仅降低右侧统计胶囊和浏览量字号，保持高手榜标题、期号、后台标题字号/粗细控件业务不变
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页电脑端：/public/index.php?region=macau
- 香港首页电脑端：/public/index.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；本次只调整电脑端高手榜区右侧统计字号和统计胶囊尺寸，未改开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待浏览器确认

---

### 日期：2026-06-26
### 修改目标：修正电脑端高手榜区期号与标题字号比例
### 修改原因：用户反馈上一版高手榜区前缀期数字号偏大、帖子标题字号偏小，比例不协调；同时不能影响后台帖子管理“标题字号 / 标题粗细”控件保存后的字号粗细生效
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页电脑端：/public/index.php?region=macau
- 香港首页电脑端：/public/index.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
- 共用后台帖子管理：/public/admin.php?page=posts
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；本次只调整电脑端高手榜区默认期号和标题字号比例，并取消会压住标题字号/粗细控件的强制覆盖，未改开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待浏览器确认

---

### 日期：2026-06-26
### 修改目标：放大电脑端日期日历和高手榜区正文字号
### 修改原因：用户反馈电脑端“日期日历 / 高手榜区”正文字号偏小，视觉显示不协调；本次仅在电脑端提高日期区标签、日期/时间、辅助行和高手榜条目正文、期号、准错/浏览信息字号，并同步后台编辑器预览
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页电脑端：/public/index.php?region=macau
- 香港首页电脑端：/public/index.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；本次只调整电脑端日期日历和高手榜区字号及对应行高，未改开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待浏览器确认

---

### 日期：2026-06-26
### 修改目标：对齐开奖结果号码球白心外圈视觉
### 修改原因：用户截图确认首页号码球仍是实心彩球，AI 预测页号码球是白色中心加彩色外圈；本次只把开奖结果卡片最终覆盖改成同 AI 预测页的白心、外圈、高光和轻投影视觉，不调整间距、尺寸和开奖结构
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/index.php?region=hongkong
- AI预测页：/public/forecast.php?region=macau
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；本次只修正开奖结果号码球白心外圈视觉和 CSS 缓存版本，未改开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：修正开奖结果卡片号码球立体样式
### 修改原因：用户确认问题是球体立体视觉不一致，不是间距大小不一致；撤销上一版末尾对号码区网格、球体宽高、字号和加号宽度的覆盖，只保留 AI 预测页同类球体的径向渐变、内圈和高光视觉
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/index.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；本次只修正开奖结果卡片号码球视觉样式和 CSS 缓存版本，未改开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：对齐开奖结果卡片号码球与 AI 预测页号码球结构样式
### 修改原因：用户反馈首页开奖结果卡片号码球体和 AI 预测页号码球体 HTML / CSS 结构不一致；实际渲染类名一致，但首页独立开奖卡片缺少预测页球体骨架尺寸与间距覆盖
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/index.php?region=hongkong
- AI预测页：/public/forecast.php?region=macau
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；本次只把首页独立开奖卡片号码球的网格、尺寸、生肖行距、加号宽度对齐 AI 预测页号码球骨架，并同步后台编辑器预览和 CSS 缓存版本，未改 AI 预测业务、开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：取消开奖结果卡片号码球和正文投影并加深生肖正文颜色
### 修改原因：用户要求号码球体和正文取消投影，同时让生效/生肖正文颜色加深；仅调整开奖结果卡片内部号码结果区域视觉样式
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/index.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；本次只取消开奖结果卡片号码球和文字投影，加深号码区生肖正文颜色，并同步后台编辑器预览和 CSS 缓存版本，未改开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：将开奖结果卡片号码结果区域背景改为白色
### 修改原因：用户要求开奖结果卡片内部的号码结果卡片背景白色；仅调整号码结果区域视觉背景，保持开奖数据、结构和控件不变
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/index.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；本次只把开奖结果卡片内部号码结果区域背景覆盖为白色，并同步后台编辑器预览和 CSS 缓存版本，未改开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：取消后台管理在线客服最新消息菜单通知
### 修改原因：用户要求后台管理的在线客服最新消息和通知取消，其他业务保持不变；后台菜单不再显示在线客服未读红点，也不再挂载后台未读轮询，避免弹出最新消息通知和播放声音
### 修改文件：
- app/Views/layouts/admin.php

### 是否影响澳门：否
### 是否影响香港：否
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台：/public/admin.php?page=posts
- 共用后台在线客服：/public/admin.php?page=support
### 测试结果：后台布局 PHP 语法检查通过；diff 空白检查通过；本次只取消共用后台菜单的在线客服未读红点和后台未读轮询通知，在线客服页面内部会话列表、未读筛选、消息记录、接口、数据库、权限和登录均不变
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：修复后台在线客服历史未读误触发最新消息提示音
### 修改原因：后台菜单未读轮询强刷新后可能以 0 作为最新消息基线，导致历史未读被首轮轮询误判为最新消息并播放通知音
### 修改文件：
- app/Views/layouts/admin.php
- public/assets/app.js

### 是否影响澳门：否
### 是否影响香港：否
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台：/public/admin.php?page=posts
- 共用后台在线客服：/public/admin.php?page=support
### 测试结果：后台布局 PHP 语法检查通过；diff 空白检查通过；当前环境缺少 node/nodejs，未执行 JS 语法检查；本次只修后台未读通知基线，不改客服消息发送、接收、会话列表、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待浏览器强刷新确认

---

### 日期：2026-06-26
### 修改目标：调整主卡片 #1 电脑端和手机端比例为 3:1.5
### 修改原因：用户要求主卡片 #1 电脑端和手机端比例尺寸使用 3:1.5，替换上一版 3:2
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/index.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；前台与后台预览 CSS 已统一为 `aspect-ratio: 3 / 1.5` 并更新缓存版本；未改背景上传、开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待浏览器确认

---

### 日期：2026-06-26
### 修改目标：统一主卡片 #1 电脑端和手机端 3:2 高度比例
### 修改原因：主卡片 #1 删除内部标题内容后仍被旧的固定 `--home-hero-height` 和 `min-height` 规则控制，手机端高度随宽度不按 3:2 换算，显示偏高
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/index.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；已在前台和后台预览 CSS 末尾增加主卡片 #1 的 3:2 最终覆盖，并更新 CSS 缓存版本；未改背景上传、开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待浏览器确认

---

### 日期：2026-06-26
### 修改目标：删除主卡片 #1 内部标题文案并保留背景上传
### 修改原因：用户要求删除主卡片 #1 内部的“2027六合彩胶囊 / 鸿运当头 · 一击必中 / 每日更新 · 精准生肖 · 独家特码”，但保留主卡片背景图片上传更换能力
### 修改文件：
- resources/defaults/home_editor_default.html
- app/Services/AdminService.php
- app/Views/front/home_legacy.php
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/index.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：PHP 语法检查通过；过滤函数验证可删除三段文案节点并保留主卡片背景 style；默认模板已无这三段内容；diff 空白检查通过；CLI 渲染首页时当前环境数据库连接失败，需浏览器访问澳门/香港首页和后台资料页确认最终效果
### Git 提交号：未提交
### 是否可以上线：待浏览器确认

---

### 日期：2026-06-26
### 修改目标：补齐开奖卡片后台编辑控件
### 修改原因：开奖卡片迁出主卡片后成为独立 `div#section-live.hero-live-box`，后台资料编辑器原先只给 `section` 和公告条注入“拖拽/编辑/源码/隐藏/删除”控件，导致开奖卡片漏显示控制条
### 修改文件：
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：后台资料页模板语法检查通过；diff 空白检查通过；本次只把独立开奖卡片纳入后台编辑器可排序区块识别，复用现有“拖拽/编辑/源码/隐藏/删除”控制条，未改前台开奖内容、开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待浏览器确认

---

### 日期：2026-06-26
### 修改目标：收紧开奖卡片高度并保持正文不挤压
### 修改原因：独立开奖卡片当前外层留白、号码区上下内距和底部统计行间距偏大，需要在不缩小正文、球号和生肖文字的前提下收紧整体高度
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/index.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；本次只收紧独立开奖卡片外层留白、号码区内距和底部统计行间距，并同步后台预览和 CSS 缓存版本，未改开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：加大开奖卡片期号字号
### 修改原因：开奖卡片头部“澳门 / 香港 nnn期”字号偏小，需要在当前同排结构内加大两档
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/index.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；本次只加大独立开奖卡片头部期号文字字号，并同步后台预览和 CSS 缓存版本，未改开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：调整开奖卡片头部三项同排显示
### 修改原因：手机端独立开奖卡片头部需要“开奖记录胶囊 / 澳门香港期号 / 下期开奖时间”同排显示，原手机端两行结构与当前视觉要求不一致；同时下期开奖时间胶囊需要固定可读宽度，避免正文被挤压
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/index.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；本次只调整独立开奖卡片手机端头部同排 CSS 和后台预览同款规则，未改开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：隔离开奖卡片电脑端和手机端样式结构
### 修改原因：独立开奖卡片末尾覆盖层写了 grid 模板但前台本体仍继承旧 flex 布局，导致手机端头部没有真正切换到两行结构，电脑端和手机端互相继承残留样式，出现遮挡和比例混乱
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/index.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；本次只补齐开奖卡片最终覆盖层的 grid 生效条件，并拆分电脑端和手机端断点规则，未改开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：纠正手机端开奖卡片宽度和头部遮挡
### 修改原因：主卡片 #1 使用 `--front-inner-width`，而独立开奖卡片使用了另一套宽度变量，导致手机端无法与主卡片宽度一致；同时手机端头部仍强制一行展示，“开奖记录 / 澳门香港期号 / 下期开奖时间”在窄屏下互相挤压遮挡。上一轮还把号码球体改成 grid 覆盖，和原结构不一致
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/record.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；本次只调整首页开奖卡片宽度变量、手机端头部两行避让和恢复号码球体原 flex 结构，未改开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：统一前台和后台开奖卡片内部显示比例
### 修改原因：独立开奖卡片迁移后，前台最终样式和后台编辑器预览样式不完整一致，后台缺少手机端球号、生肖、下期开奖胶囊和底部统计行的同款覆盖；同时结果号码区缺少显式居中约束，导致前后台看起来不一致
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/record.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：前台布局模板和后台资料页模板语法检查通过；diff 空白检查通过；本次只同步前后台开奖卡片内部 CSS 覆盖和版本号，未改开奖数据、接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：修复开奖卡片样式越界影响 AI 预测页并补正手机端比例
### 修改原因：主卡片下方独立开奖卡片的最终覆盖选择器未排除预测页，导致 AI 预测页内的开奖区被首页样式覆盖；同时预测页提取开奖卡片仍只兼容旧的内嵌结构，默认模板迁移为同级结构后会提取异常。首页手机端独立开奖卡片内部球号、生肖、开奖期号、下期开奖时间和底部统计比例也需要单独协调
### 修改文件：
- public/forecast.php
- public/styles/style.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/record.php?region=hongkong
- 澳门 AI 预测页：/public/forecast.php?region=macau
- 香港 AI 预测页：/public/forecast.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：待浏览器强刷验证；本次只收窄首页开奖卡片 CSS 范围、兼容预测页开奖卡片提取和补正手机端显示比例，未改开奖数据、预测接口、数据库、权限和登录
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：修正主卡片下方独立开奖卡片宽度和投影
### 修改原因：开奖卡片迁移出主卡片 #1 后仍使用旧的 686px 宽度上限，和主卡片、资料区等卡片宽度不一致；同时外层保留投影，导致前后台视觉结构不统一
### 修改文件：
- public/styles/style.css
- public/styles/home-editor-preview.css
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/record.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：待浏览器强刷验证；本次仅调整独立开奖卡片宽度变量、内部网格对齐和外层投影，未改开奖数据和业务逻辑
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：将主卡片 #1 内部开奖卡片迁移到主卡片下方
### 修改原因：后台资料编辑器和前台首页中，开奖卡片 `#section-live` 原本作为主卡片 #1 `#section-home` 的内部内容渲染；按业务要求将其调整为主卡片 #1 后方的同级卡片，同时保留开奖期号、开奖时间、开奖结果和数据来源逻辑不变
### 修改文件：
- resources/defaults/home_editor_default.html
- app/Services/AdminService.php
- app/Views/front/home_legacy.php
- app/Views/layouts/home_legacy.php
- app/Views/admin/draws.php
- public/styles/style.css
- public/styles/home-editor-preview.css

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门首页：/public/index.php?region=macau
- 香港首页：/public/record.php?region=hongkong
- 共用后台资料更新：/public/admin.php?page=draws
### 测试结果：PHP 服务层、前台首页模板、后台资料模板、前台布局模板、首页入口语法检查通过；diff 空白检查通过；默认首页模板、澳门/香港默认资料模板和旧内嵌结构迁移函数检查均显示 `#section-live` 已位于 `#section-home` 后方。CLI 完整渲染澳门/香港页面时当前命令行数据库连接失败，未完成真实数据页面渲染检查；需浏览器强刷前台和后台编辑器确认视觉位置
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：修复电脑端会员客服会话窗口结构空档
### 修改原因：旧版固定输入栏预留规则的选择器优先级更高，仍会给电脑端会员客服会话卡片和记录区保留过大的底部空间，导致会话记录区与输入栏之间出现异常空白。增加同路径最终覆盖，让公告、会话记录、输入栏保持在同一会话卡片内三行结构
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页：/public/service.php?region=macau
- 香港会员客服页：/public/service.php?region=hongkong
### 测试结果：PHP 布局模板语法检查、客服模板语法检查、客服入口语法检查和 diff 空白检查通过；本次只追加电脑端会员客服页最终结构覆盖，并更新 CSS 版本号。客服发送/接收接口、通知接口、轮询接口、登录权限、数据库结构、接待客服端和澳门/香港数据逻辑不变；需要浏览器强刷澳门/香港会员客服页确认会话记录区与输入栏之间不再出现旧预留空档
### Git 提交号：未提交
### 是否可以上线：待验证

---

### 日期：2026-06-26
### 修改目标：修复电脑端会员客服会话记录高度和消息渲染交错
### 修改原因：电脑端会员客服输入栏此前用 fixed 定位避让底部悬浮导航，导致会话记录区继续按旧高度预留大块空白；同时发送请求和已发出的轮询请求可能先后返回，旧轮询数据会短暂覆盖发送成功后的新会话记录。将电脑端会员客服输入栏恢复到会话卡片内部正常流布局，并忽略发送期间过期的轮询响应
### 修改文件：
- public/styles/style.css
- public/assets/app.js
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页：/public/service.php?region=macau
- 香港会员客服页：/public/service.php?region=hongkong
### 测试结果：PHP 布局模板语法检查、客服模板语法检查、客服入口语法检查和 diff 空白检查通过；服务器当前没有 node/nodejs 命令，JS 语法命令检查未执行。本次只调整会员端客服页电脑端布局和前端消息渲染时序保护，客服发送/接收接口、通知接口、轮询接口、登录权限、数据库结构和澳门/香港共用入口不变；需要浏览器强刷澳门/香港会员客服页验证会话记录区高度和发送/接收显示顺序
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-26
### 修改目标：修复电脑端会员客服会话输入栏仍被底部悬浮导航遮挡
### 修改原因：电脑端会员客服页在部分窗口高度下，输入表单虽然已固定到底部导航上方，但最终避让距离仍不足，导致输入框和工具按钮下沿被底部悬浮导航压住。提高会员客服页桌面端专用输入栏底部避让距离，并增加对应内容区预留空间
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页：/public/service.php?region=macau
- 香港会员客服页：/public/service.php?region=hongkong
### 测试结果：待验证
### Git 提交号：
### 是否可以上线：待确认

---

### 日期：2026-06-26
### 修改目标：减少后台帖子版块和分类列表重复查询
### 修改原因：后台帖子版块、帖子分类页面切换时会重复执行列表查询，且列表中包含每个版块/分类的帖子数量子查询；这些列表短时间内稳定，保存版块/分类后已有统一缓存版本刷新入口。为版块列表和分类列表增加按筛选条件区分的 15 秒短缓存，减少后台菜单切换等待
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台 > 帖子版块
- 共用后台 > 帖子分类
### 测试结果：PHP 服务层语法检查、后台入口语法检查、帖子版块模板语法检查、帖子分类模板语法检查和 diff 空白检查通过；本次只为后台版块/分类列表增加 15 秒短缓存，缓存 key 区分筛选条件并绑定现有版块/分类缓存版本号。保存版块或分类后沿用现有清理入口刷新版本。版块/分类保存、筛选、列表返回结构、接口结构、数据库结构、权限和澳门/香港分区逻辑不变；新保存的版块/分类列表最多存在 15 秒展示延迟
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-26
### 修改目标：修复电脑端会员客服会话输入栏仍被底部悬浮导航遮挡
### 修改原因：电脑端会员客服页历史叠加了多段输入态和底部导航避让规则，部分浏览器下最终仍会按页面底部位置渲染输入栏，导致语音、图片、表情、输入框和发送按钮被底部悬浮导航覆盖；在样式末尾增加会员客服页桌面端最终布局规则，固定输入栏到导航上方并保持底部导航可见
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页：/public/service.php?region=macau
- 香港会员客服页：/public/service.php?region=hongkong
### 测试结果：PHP 布局模板语法检查、客服模板语法检查、客服入口语法检查和 diff 空白检查通过；本次只追加电脑端会员客服页最终避让样式，并更新 CSS 版本号。客服发送、接收、轮询、登录状态、接待客服端、底部导航结构、接口结构和数据库结构不变；需要浏览器强刷澳门/香港会员客服页确认输入栏完整显示在底部悬浮导航上方
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-26
### 修改目标：减少后台日志页面切换重复分页查询
### 修改原因：后台登录日志、操作日志、异常日志页面每次切换都会执行 count 和列表查询；这些页面只展示历史日志，短时间内稳定。为三个日志分页结果增加按筛选条件和页码区分的 10 秒短缓存，减少后台菜单切换等待
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台 > 登录日志
- 共用后台 > 操作日志
- 共用后台 > 异常日志
### 测试结果：PHP 服务层语法检查、后台入口语法检查、登录日志模板语法检查、操作日志模板语法检查、异常日志模板语法检查和 diff 空白检查通过；本次只为三类后台日志分页结果增加 10 秒短缓存，缓存 key 区分筛选条件和页码。日志保存、登录、权限、接口结构、数据库结构和澳门/香港数据独立逻辑不变；新产生的日志在日志页最多存在 10 秒展示延迟
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-26
### 修改目标：减少后台资料更新编辑器切换重复构造
### 修改原因：后台“澳门资料更新 / 香港资料更新”每次切换都会重新拼装资料编辑器返回数据；组件管理编辑器已有 20 秒短缓存，而资料编辑器只缓存内部 HTML 归一化，仍会重复组合编辑器数据。补齐 region、内容 hash、更新时间/人、当前期号快照和高手帖版本绑定的 20 秒短缓存，减少后台资料区切换等待
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台资料更新 > 澳门资料更新
- 共用后台资料更新 > 香港资料更新
- 共用后台资料更新 > 组件管理
### 测试结果：PHP 服务层语法检查、后台入口语法检查、后台资料页模板语法检查和 diff 空白检查通过；CLI 直接读取澳门/香港资料编辑器时受当前命令行数据库连接配置限制未完成。本次只补齐后台资料编辑器 20 秒短缓存，缓存 key 区分 region、内容 hash、更新时间/人、当前期号快照和高手帖版本。保存资料、接口结构、数据库结构、权限、澳门/香港开奖数据独立逻辑不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-26
### 修改目标：修复电脑端会员客服输入场景仍被底部悬浮导航遮挡
### 修改原因：会员端客服输入表单此前依赖多段历史选择器兜底，在桌面端部分浏览器下仍可能贴近底部公共悬浮导航，导致语音、图片、表情、输入框和发送区域被遮住；给会员端输入表单增加专用类，并在 CSS 末尾用专用类固定到导航上方净空位置
### 修改文件：
- app/Views/front/service.php
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页：/public/service.php?region=macau
- 香港会员客服页：/public/service.php?region=hongkong
### 测试结果：PHP 客服模板语法检查、前台布局模板语法检查、客服入口语法检查和 diff 空白检查通过；本次只给会员端客服输入表单增加专用类，并追加桌面端固定避让样式与 CSS 版本号。客服发送、接收、轮询、登录状态、接待客服端、公共底部导航结构、接口结构和数据库结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-26
### 修改目标：修复电脑端会员客服输入栏被底部悬浮导航遮挡
### 修改原因：电脑端会员客服页在部分浏览器下仍未稳定套用历史避让规则，输入工具条会贴到底部并被公共悬浮导航压住；追加更高优先级的会员客服页最终兜底规则，把输入栏固定在底部导航上方并给消息区保留净空
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页：/public/service.php?region=macau
- 香港会员客服页：/public/service.php?region=hongkong
### 测试结果：PHP 布局模板语法检查、客服模板语法检查、客服入口语法检查和 diff 空白检查通过；本次只改会员客服页桌面端 CSS 避让与 CSS 版本号，客服发送/接收接口、登录、坐席端和底部导航结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-26
### 修改目标：减少共用后台首页 AI预测价格配置重复拼装
### 修改原因：共用后台首页每次进入都会读取并拼装 AI预测选项价格配置；该配置短时间内稳定，且保存价格设置后可刷新版本号，用 15 秒短缓存减少后台首页加载和菜单切换等待
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台首页
- 共用后台首页 > AI预测选项价格保存
### 测试结果：PHP 服务层语法检查、后台入口语法检查、后台首页模板语法检查和 diff 空白检查通过；CLI 直接读取 AI预测价格配置成功，返回 4 组选项、4 组折扣，同一请求内缓存命中。保存价格设置后刷新缓存版本。价格字段、前台预测购买、澳门/香港开奖数据源、接口结构和数据库结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-26
### 修改目标：纠正电脑端会员客服会话输入栏仍被底部悬浮导航遮挡
### 修改原因：电脑端会员客服页实际 body 组合为 standalone-panel customer-service-body front-unified-panel-page，历史多段避让样式在部分浏览器下未稳定命中输入表单，导致语音、图片、表情、输入框和发送区域仍落入底部悬浮导航覆盖区；需要追加更通用的桌面端会员客服输入栏最终避让规则，并更新 CSS 版本号
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页：/public/service.php?region=macau
- 香港会员客服页：/public/service.php?region=hongkong
### 测试结果：PHP 布局模板语法检查、客服模板语法检查、客服入口语法检查和 diff 空白检查通过；本次只追加电脑端会员客服页最终避让样式，并更新 CSS 版本号。客服发送、接收、轮询、登录状态、接待客服端、底部导航结构、接口结构和数据库结构不变；需要浏览器强刷澳门/香港会员客服页确认输入栏完整显示在底部悬浮导航上方
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：纠正电脑端会员客服会话输入栏仍被底部悬浮导航遮挡
### 修改原因：样式文件末尾最终生效的桌面端会员客服避让规则把输入栏与底部悬浮导航的间距压回 18px，覆盖了前面更大的安全距离，导致输入框、语音、图片、表情和发送按钮仍落入悬浮导航覆盖区
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页：/public/service.php?region=macau
- 香港会员客服页：/public/service.php?region=hongkong
### 测试结果：PHP 布局模板语法检查、客服模板语法检查、客服入口语法检查和 diff 空白检查通过；本次只调整电脑端会员客服页最终生效的输入栏避让距离，并更新 CSS 版本号。客服发送、接收、轮询、接待客服端、底部导航结构、接口结构和数据库结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台会员管理页找回密码请求列表重复查询
### 修改原因：后台会员管理页每次进入都会读取找回密码请求列表；该列表只用于后台展示和处理入口，短时间内稳定，处理找回密码后可以刷新版本号，用 15 秒短缓存减少菜单切换等待
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台会员管理
- 共用后台会员管理 > 找回密码处理
### 测试结果：PHP 服务层语法检查、后台入口语法检查、会员管理模板语法检查和 diff 空白检查通过；本次只为找回密码请求列表增加 15 秒短缓存，缓存 key 区分 pending / processed / all 状态，后台处理找回密码后刷新版本号。会员列表、会员编辑、充值、VIP、注册规则、接口结构和数据库结构不变；新提交的找回密码请求最多存在 15 秒列表缓存延迟
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：纠正电脑端会员客服会话输入栏仍被底部悬浮导航遮挡
### 修改原因：会员客服页桌面端存在多段历史样式覆盖，输入栏在部分浏览器宽度下仍落入底部悬浮导航区域；需要在样式末尾增加最终避让规则，按底部导航高度为输入栏和消息区保留空间
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页：/public/service.php?region=macau
- 香港会员客服页：/public/service.php?region=hongkong
### 测试结果：PHP 布局模板语法检查、客服模板语法检查、客服入口语法检查和 diff 空白检查通过；本次只在样式末尾追加电脑端会员客服页最终避让规则，输入栏和消息区按底部悬浮导航高度保留空间，并更新 CSS 版本号。客服发送、接收、轮询、登录状态、接待客服端、底部导航结构和接口结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台互动和举报页会员/帖子选择下拉重复查询
### 修改原因：后台帖子互动、帖子举报页面每次切换都会重新读取最近会员和帖子选择下拉；这些下拉只用于表单选择，短时间内稳定，且会员/帖子保存操作已有统一清理入口，可用 15 秒短缓存减少页面切换等待。编辑项不在最近列表时还会单独读取选中会员/帖子，本次同步纳入同版本短缓存
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台帖子互动管理
- 共用后台帖子举报管理
### 测试结果：PHP 服务层语法检查、后台入口语法检查、互动/举报视图语法检查和 diff 空白检查通过；本次只为后台互动/举报表单的会员、帖子选择下拉增加 15 秒短缓存，基础列表缓存 key 区分会员数量限制、帖子 region 和数量限制，选中项回填缓存 key 区分会员/帖子 ID，并沿用会员/帖子保存及帖子状态变更后的版本号刷新。互动/举报保存、筛选、分页、接口结构和数据库结构不变；CLI 直接服务层烟测受当前命令行数据库连接配置限制未完成
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台帖子版块和分类选项重复查询
### 修改原因：后台帖子编辑、生成帖子配置和分类管理页面切换时会反复读取启用版块、分类下拉选项；这些选项短时间内稳定，且保存版块/分类已有统一清理入口，可用 15 秒短缓存降低重复查询等待
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台帖子管理
- 共用后台生成帖子
- 共用后台帖子分类
### 测试结果：PHP 服务层语法检查、后台入口语法检查、帖子管理/版块/分类视图语法检查和 diff 空白检查通过；本次只为启用版块、分类下拉选项增加 15 秒短缓存，缓存 key 区分 region 和 section_id，保存版块/分类后刷新版本号。后台帖子保存、生成帖子、版块分类保存、接口结构和数据库结构不变；CLI 直接服务层烟测受当前命令行数据库连接配置限制未完成
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：纠正电脑端会员客服输入栏仍被底部悬浮导航遮挡
### 修改原因：客服页样式文件最后一段桌面端覆盖规则把输入栏与底部导航的避让距离压回 14px，覆盖了前面更大的安全距离，导致输入框下沿仍落入底部悬浮导航区域
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页：/public/service.php?region=macau
- 香港会员客服页：/public/service.php?region=hongkong
### 测试结果：PHP 布局模板语法检查、客服入口语法检查、客服模板语法检查和 diff 空白检查通过；本次只把最终生效的桌面端会员客服输入栏避让距离从 14px 调整为 58px，并更新 CSS 版本号。客服发送、接收、轮询、登录状态、接待客服端、底部导航结构和接口结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台用户管理页会员角色选项重复读取
### 修改原因：后台用户管理页每次切换进入都会读取会员角色下拉选项，且底层会先确认会员角色表结构；会员角色选项短时间内稳定，可用 15 秒短缓存减少页面切换等待
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台会员管理
- 共用后台菜单切换
### 测试结果：PHP 服务层语法检查、后台入口语法检查、会员管理模板语法检查和 diff 空白检查通过；本次只给会员角色下拉选项增加请求内缓存和 15 秒短缓存，会员列表、编辑会员、保存会员、找回密码、接口结构和数据库结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台管理员列表重复查询
### 修改原因：后台管理员管理页切换进入时会重复读取管理员列表及角色信息；管理员数据短时间内稳定，且保存管理员和角色变更已有后台缓存版本号失效机制，可用于 15 秒短缓存降低重复查询等待
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台管理员管理
- 共用后台菜单切换
### 测试结果：PHP 服务层语法检查、后台入口语法检查、管理员管理模板语法检查和 diff 空白检查通过；命令行服务层烟测受当前 CLI 数据库连接配置限制未完成。本次只给管理员列表增加 15 秒短缓存，缓存 key 绑定现有后台菜单/权限版本号。保存管理员和切换管理员状态后仍立即失效，管理员字段、权限判断结果、接口结构和数据库结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：纠正电脑端会员客服输入栏被底部悬浮导航遮挡
### 修改原因：会员客服页桌面端输入栏避让规则仍依赖较窄的 body 组合，部分电脑浏览器输入聚焦后没有稳定覆盖旧的普通布局规则，导致输入框落入底部悬浮导航覆盖区
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页：/public/service.php?region=macau
- 香港会员客服页：/public/service.php?region=hongkong
### 测试结果：PHP 布局模板语法检查、客服模板语法检查、客服入口语法检查和 diff 空白检查通过；本次只放宽桌面端会员客服输入栏 CSS 选择器命中范围，并固定输入栏在公共底部导航上方，发送、接收、轮询、登录状态、接待客服端和底部导航业务不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台角色权限公共数据重复查询
### 修改原因：后台页面切换时管理员权限判断、管理员页角色下拉和角色页权限列表会重复读取角色、权限和权限码小表；这些数据短时间内稳定，且已有后台访问缓存版本号可在角色/管理员变更时失效
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台管理员管理
- 共用后台角色管理
- 共用后台任意菜单切换
### 测试结果：待语法和差异检查；本次只为角色列表、权限列表和非超管权限码增加 15 秒短缓存，缓存 key 绑定现有后台菜单版本号。管理员/角色变更仍走原失效点，权限判断条件、菜单权限结果、接口结构和数据库结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服输入栏仍被底部悬浮导航遮挡
### 修改原因：电脑端会员客服页最后生效的输入栏避让值偏小，输入框在部分浏览器高度下仍贴近或落入公共底部悬浮导航覆盖区
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页：/public/service.php?region=macau
- 香港会员客服页：/public/service.php?region=hongkong
### 测试结果：待浏览器强刷验证；本次只提高电脑端会员客服输入栏与底部悬浮导航之间的避让距离，并同步增加聊天区底部缓冲。客服发送、接收、轮询、登录状态、接待客服端、底部导航结构和接口结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服会话输入区仍被底部悬浮导航遮挡
### 修改原因：电脑浏览器在缩放或窄窗口下可能未命中原先仅按宽度判断的桌面规则，会员客服输入栏仍按容器底部排布，导致语音、图片、表情、输入框和发送按钮被公共底部悬浮导航压住
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页：/public/service.php?region=macau
- 香港会员客服页：/public/service.php?region=hongkong
### 测试结果：待浏览器强刷验证；本次仅追加桌面输入设备/宽屏会员客服页最终覆盖样式，并更新 CSS 版本号。客服发送、接收、轮询、登录状态、底部导航结构和接口结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台资料更新组件管理切换时重复构造编辑器内容
### 修改原因：组件管理页每次进入都会重新读取、拼接并规范化顶部/底部悬浮组件 HTML；内容未变化时这些结果相同，重复构造会拖慢后台资料更新三项切换
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台资料更新 > 组件管理
- 共用后台资料更新 > 澳门资料更新
- 共用后台资料更新 > 香港资料更新
### 测试结果：PHP 服务层语法检查、资料更新模板语法检查、后台入口语法检查和 diff 空白检查通过；本次只给组件编辑器读取结果增加按内容哈希的 20 秒短缓存，保存业务、澳门/香港资料独立、组件字段、接口结构和权限不变。CLI 服务层直接调用受当前数据库连接配置限制未完成，需要浏览器后台实际切换组件管理确认加载速度
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服输入栏仍被底部悬浮导航遮挡
### 修改原因：桌面端会员客服页已有 fixed 输入栏规则，但后置样式又把同一输入栏改回 relative，导致输入区回到会话卡片底部并被 fixed 底部悬浮导航覆盖
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页：/public/service.php?region=macau
- 香港会员客服页：/public/service.php?region=hongkong
### 测试结果：PHP 模板语法检查和 diff 空白检查通过；本次只移除电脑端会员客服输入栏的反向 relative 覆盖规则，并更新 CSS 版本号。客服消息发送、接收、轮询、登录状态、底部导航结构和接口结构不变；需浏览器强刷确认输入栏完整显示在底部悬浮导航上方
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台帖子互动和举报编辑页重复查询
### 修改原因：后台帖子互动/举报页面点击编辑时，列表查询结果已经包含表单回填所需字段；原控制器仍会先按 ID 单独查询编辑项，再查询列表，导致页面切换和编辑入口产生重复数据库读取
### 修改文件：
- public/admin.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台帖子互动管理
- 共用后台帖子举报管理
### 测试结果：PHP 语法检查、互动模板语法检查、举报模板语法检查和 diff 空白检查通过；本次只调整编辑项读取顺序，当前列表中能命中则复用列表行，筛选条件或分页导致列表中没有编辑项时仍回退原单条查询。表单字段、保存业务、处罚联动、澳门/香港分区字段和接口结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服输入栏仍被底部悬浮导航遮挡
### 修改原因：电脑端会员客服会话窗口中，输入栏在部分浏览器高度下仍落到公共底部悬浮导航覆盖区，导致语音、图片、表情、输入框和发送区域被截住
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页：/public/service.php?region=macau
- 香港会员客服页：/public/service.php?region=hongkong
### 测试结果：PHP 模板语法检查和 diff 空白检查通过；本次仅追加电脑端会员客服页专用 CSS 覆盖规则，并更新 CSS 版本号。客服消息发送、接收、轮询、登录状态、接待客服端和底部导航入口不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台版块和分类编辑页重复查询
### 修改原因：后台版块/分类页面点击编辑时，列表查询结果已经包含表单回填所需字段；原控制器仍会先按 ID 单独查询编辑项，再查询列表，导致后台页面切换和编辑入口产生重复数据库读取
### 修改文件：
- public/admin.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台帖子版块
- 共用后台帖子分类
### 测试结果：PHP 语法检查、版块模板语法检查、分类模板语法检查和 diff 空白检查通过；本次只调整编辑项读取顺序，当前列表中能命中则复用列表行，筛选条件导致列表中没有编辑项时仍回退原单条查询。表单字段、保存业务、澳门/香港分区字段和接口结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台会员管理页无用详情预查询
### 修改原因：会员管理页当前模板已经在会员列表行内提供编辑、充值和VIP操作，不再消费旧的 editingUser、登录日志、封禁记录、VIP记录和独立表单变量；原后台控制器仍在进入页面时预先读取这些数据，导致会员页加载和操作后返回列表时产生多余数据库查询
### 修改文件：
- public/admin.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台会员管理
### 测试结果：PHP 语法检查、会员管理模板语法检查、diff 空白检查通过；搜索确认当前后台视图不再引用被移除的旧变量。本次仅移除当前会员管理模板未使用的数据预加载，会员列表、筛选、编辑弹窗、充值、VIP保存、找回密码列表和注册规则设置保留原业务路径
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服输入栏仍被底部悬浮导航压住
### 修改原因：会员客服页桌面端输入栏虽已固定，但在部分浏览器高度下底部距离仍落到悬浮导航覆盖区域，导致输入框、工具按钮和发送按钮下半部分被导航截住
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页：/public/service.php?region=macau
- 香港客服页：/public/service.php?region=hongkong
### 测试结果：PHP 语法检查和 diff 空白检查通过；本次只调整电脑端会员客服输入栏与底部悬浮导航的垂直间距、消息区底部预留空间和 CSS 版本号，客服消息发送/接收、轮询、登录状态、底部导航入口和接口结构不变。需浏览器强刷确认输入栏完整显示在底部悬浮导航上方
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台资料更新页不必要的 TinyMCE 预加载
### 修改原因：后台公共布局原先只要进入资料更新菜单就预加载 TinyMCE 大脚本；实际资料编辑脚本只在具备编辑权限时加载。无编辑权限或只读访问资料更新页时提前预加载大脚本会拖慢后台页面切换
### 修改文件：
- public/admin.php
- app/Views/layouts/admin.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台资料更新
### 测试结果：PHP 语法检查和 diff 空白检查通过；本次只调整后台布局资源预加载条件，资料保存、澳门/香港资料独立、组件编辑、权限判断和接口结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服输入栏仍被底部悬浮导航遮挡
### 修改原因：部分桌面浏览器下会员客服页输入栏没有稳定命中旧的固定定位规则，导致输入框仍按会话容器底部流式排布并被底部悬浮导航覆盖；改为使用客服页实际 body 类追加桌面端固定定位和底部预留空间
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页：/public/service.php?region=macau
- 香港客服页：/public/service.php?region=hongkong
### 测试结果：待浏览器强刷验证；本次只调整电脑端会员客服输入栏定位、聊天区底部预留空间和 CSS 版本号，客服消息发送/接收、轮询、登录状态和接口结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服输入栏被底部悬浮导航遮挡
### 修改原因：电脑端会员客服页保留底部悬浮导航时，输入栏在部分浏览器高度下仍会落入导航覆盖区；将会员客服输入栏在桌面端固定到悬浮导航上方，并给聊天记录区预留底部空间
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页：/public/service.php?region=macau
- 香港客服页：/public/service.php?region=hongkong
### 测试结果：PHP 语法检查和 diff 空白检查通过；本次仅调整电脑端会员客服输入栏定位和样式版本号，消息发送、接收、轮询、登录状态、底部导航入口和接口结构不变。需浏览器强刷后验证输入栏在底部悬浮导航上方
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台管理员编辑页重复查询
### 修改原因：后台管理员管理页进入编辑状态时，原页面先按 ID 查询编辑管理员，再查询管理员列表；当前请求内管理员列表已经可以回填按 ID 缓存，调整读取顺序后编辑对象可复用列表数据，减少一次重复查询
### 修改文件：
- public/admin.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台管理员管理
### 测试结果：仅调整管理员管理页读取顺序，模板变量、表单字段、保存/停用业务和权限判断不变；需浏览器登录后台后验证管理员列表与编辑表单展示
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台当前管理员资料重复查询
### 修改原因：后台公共页面加载、菜单权限判断和管理员编辑页会在同一次请求内多次读取同一个管理员资料；原 findById 每次都直接查库，菜单切换和后台加载时会放大重复查询等待
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台仪表盘
- 共用后台管理员管理
- 共用后台角色管理
### 测试结果：PHP 语法检查和 diff 空白检查通过；管理员按 ID 查询增加当前请求内缓存，管理员列表加载后回填同一缓存，保存管理员、切换管理员状态和角色/菜单变更后沿用现有清理点失效。CLI 数据库抽查因当前命令行环境数据库连接失败未执行完成；管理员字段、权限判断结果、后台接口和数据库结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服会话输入区仍被底部悬浮导航遮挡
### 修改原因：电脑端会员客服页底部悬浮导航保持显示时，输入条仍按会话容器底边流式排布；在部分桌面浏览器高度下，语音、图片、表情、输入框和发送按钮会落入底部悬浮导航覆盖区域
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页：/public/service.php?region=macau
- 香港客服页：/public/service.php?region=hongkong
### 测试结果：电脑端会员客服页输入条在会话卡片内为底部悬浮导航固定预留空间；底部悬浮导航继续显示，输入工具栏不再被遮挡。客服发送、接收、轮询、登录状态和接口结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端客服会话输入区被底部悬浮导航遮挡
### 修改原因：电脑端会员客服页在会话输入场景下保留底部悬浮导航，但输入表单仍按会话容器底边排布，导致语音、图片、表情、输入框和发送按钮贴近底部并被悬浮导航覆盖
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页：/public/service.php?region=macau
- 香港客服页：/public/service.php?region=hongkong
### 测试结果：电脑端会员客服会话窗口在输入场景下，输入表单为底部悬浮导航预留安全距离；输入工具栏不再被底部悬浮导航遮挡。消息发送、轮询、登录状态和接口结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台资料更新页编辑器重复版本计算
### 修改原因：资料更新页打开澳门/香港资料编辑器时，会为高手区链接生成帖子版本号；该版本号需要聚合帖子和管理元数据。原逻辑每次进入页面都要重新查询，即使规范化后的编辑器 HTML 已有短缓存。组件管理页也会重复读取默认组件模板文件
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台资料更新
- 共用后台组件管理
### 测试结果：资料编辑器高手区版本号增加当前请求缓存和 20 秒短缓存；帖子列表/元数据变更时通过已有帖子缓存清理入口同步清理。组件默认模板增加当前请求内缓存。编辑器内容结构、保存接口、澳门/香港资料独立逻辑不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台在线客服页当前会话和客服账号重复查询
### 修改原因：后台在线客服管理页加载时已获取会话列表，列表首条或选中项已经包含当前会话展示所需字段；原逻辑仍会再按 session_id 单独查询一次。页面同时返回客服账号列表和账号统计，统计可复用本次请求已加载的账号列表，避免再次查询账号表
### 修改文件：
- app/Services/SupportService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台在线客服
### 测试结果：后台在线客服页当前会话优先复用已加载的会话列表行；只有会话不在列表中时才按原逻辑单独查询并回退。客服账号统计优先复用本次请求的账号列表。返回结构、会话排序、消息读取和客服业务不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台配置页重复解析和默认选项构建
### 修改原因：后台预测设置、首页运营设置和安全策略在同一次请求内可能多次读取并解析同一批配置；预测价格目录、预测默认配置和首页默认配置也会重复构建。增加请求级缓存后，菜单切换进入这些页面时减少重复设置读取和数组构建开销
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台 AI预测设置
- 共用后台前后台设置
- 共用后台安全策略
### 测试结果：配置读取和纯默认选项构建增加当前请求内缓存；保存预测设置、首页运营设置和安全策略后会立即清理对应配置缓存再读取最新值。配置字段、保存接口、权限和数据库结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台帖子选择下拉重复查询
### 修改原因：后台互动管理和举报管理都会构造帖子选择下拉；同一次请求内会重复读取最近帖子列表和补查选中帖子，增加后台页面加载和菜单切换等待
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台互动管理
- 共用后台举报管理
### 测试结果：帖子下拉基础列表增加当前请求内缓存，按 region 和 limit 独立分桶；选中帖子不在基础列表时仍按原逻辑补查并置顶。帖子保存、批量处理、单条操作和元数据变更后清理当前请求缓存；下拉结构、接口和数据库不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服输入栏仍被底部悬浮导航遮挡
### 修改原因：桌面端会员客服页的后置样式把会话窗口底部安全空间重置为 0，导致输入框、语音、图片、表情和发送按钮仍落入底部悬浮导航覆盖区；同时桌面端输入聚焦位移规则会与固定底部导航避让冲突
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端会话窗口
- 香港客服页电脑端会话窗口
### 测试结果：桌面端会员客服会话窗口重新保留底部导航安全空间，输入栏不再使用聚焦位移；底部悬浮导航继续显示，客服发送、接收、轮询业务不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台会员选择下拉重复查询
### 修改原因：后台互动管理和举报管理进入页面时都会构造会员选择下拉；同一次请求内会重复查询最近会员列表，仅选中会员 ID 不同，增加后台页面加载和菜单切换等待
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台互动管理
- 共用后台举报管理
### 测试结果：会员下拉基础列表增加当前请求内缓存；选中会员不在基础列表时仍按原逻辑补查并置顶。保存会员后清理当前请求缓存；下拉结构、权限、接口和数据库不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端客服输入场景仍被底部悬浮导航遮挡
### 修改原因：电脑端会员客服页聚焦输入框时底部悬浮导航保持显示，但输入表单仍按会话容器底边排布，导致输入框和发送按钮下半部分被导航卡片覆盖
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端会话窗口
- 香港客服页电脑端会话窗口
### 测试结果：电脑端会员客服输入激活时，聊天区增加底部安全空间，输入表单整体抬到公共底部导航上方；底部导航继续显示，客服消息发送、接收、轮询业务不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台管理员与仪表盘公共数据重复读取
### 修改原因：后台管理员/角色页在同一次请求内会读取管理员、角色、权限等基础列表；角色编辑页还会先查单条角色再查全量角色。仪表盘统计和最近日志也可能在同一请求中重复读缓存文件
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台仪表盘
- 共用后台管理员管理
- 共用后台角色管理
### 测试结果：管理员、角色、权限列表增加当前请求内复用；角色编辑改为复用角色列表定位编辑对象；仪表盘统计和最近日志增加当前请求内缓存。保存管理员/角色后清理请求缓存；业务、权限结果、接口和数据库结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服会话输入栏仍被底部悬浮导航遮挡
### 修改原因：桌面端会员客服页保留底部悬浮导航时，原先在会话窗口内部预留空间不稳定，固定高度容器下输入表单仍可能落入导航覆盖区
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端会话窗口
- 香港客服页电脑端会话窗口
### 测试结果：桌面端会员客服会话框改为由外层内容区预留底部悬浮导航安全距离，输入框、语音、图片、表情和发送按钮保持在导航上方；客服消息业务不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台在线客服账号重复查询
### 修改原因：共用后台在线客服页面进入“客服账号”标签时会先加载账号列表，打开编辑状态又按账号 ID 再查询一次同一账号；菜单切换到该页面时产生重复数据库读取
### 修改文件：
- app/Services/SupportService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台在线客服
- 共用后台在线客服 - 客服账号
### 测试结果：客服账号列表增加当前请求内复用；账号编辑读取在列表已加载时直接复用同一份数据，保存、登录和删除账号后会清理当前请求缓存；账号管理业务、接口和数据库结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端客服会话输入栏被底部悬浮卡片遮挡
### 修改原因：桌面端会员客服会话窗口是固定高度 flex 容器，原先把避让空间放在输入表单外边距上会被容器边界裁掉，导致输入框、语音、图片、表情和发送按钮仍贴近或落入底部悬浮导航覆盖区
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端会话窗口
- 香港客服页电脑端会话窗口
### 测试结果：桌面端会员客服会话窗口改为在窗口内部预留底部悬浮导航安全区，输入表单不再依赖会被裁剪的外边距；底部悬浮导航保持显示，客服发送、接收、轮询业务不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台权限判断重复查询
### 修改原因：后台公共页面加载和菜单切换时会多次调用 adminCan 判断页面入口、菜单和按钮权限；非超级管理员每个新权限码都会单独查询权限，增加后台切换等待
### 修改文件：
- app/Core/Auth.php
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台任意菜单页面
- 共用后台帖子管理
- 共用后台资料更新
### 测试结果：同一请求内改为一次读取当前管理员角色权限码集合，后续 adminCan 直接复用缓存；权限来源、角色关系、菜单显示和按钮权限判断结果不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服输入栏被底部悬浮导航遮挡
### 修改原因：桌面端会员客服页原先通过输入表单内部大底部 padding 避让底部悬浮导航，固定高度会话容器下仍可能让语音、图片、表情、输入框和发送按钮落入导航覆盖区域
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端会话窗口
- 香港客服页电脑端会话窗口
### 测试结果：桌面端会员客服输入表单改为整体预留底部悬浮导航高度，输入控件保持在导航上方；底部导航继续显示，消息发送、接收、轮询和接口逻辑不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台版块分类选项重复查询
### 修改原因：后台帖子管理、生成帖子和分类管理会在同一次请求内重复读取版块/分类下拉选项；生成器默认目标也会再次读取相同选项，增加后台加载和菜单切换等待
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台帖子管理
- 共用后台生成帖子
- 共用后台分类管理
### 测试结果：版块/分类下拉选项增加当前请求内缓存，按 region 和 section_id 独立分桶；保存版块或分类后清理请求缓存和生成器配置缓存；后台列表查询、保存业务、接口和数据库结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台帖子生成配置重复拼装
### 修改原因：后台帖子管理、生成帖子和保存生成器设置时，同一次请求会重复拼装生成器配置；该配置会读取当前期号、分区默认版块分类、模板组和保存设置，重复执行会增加后台加载和切换等待
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台帖子管理
- 共用后台生成帖子
### 测试结果：生成器配置增加当前请求内缓存，按澳门/香港独立分桶；保存生成器设置和生成模式后立即清理对应分区缓存，不做跨请求缓存，不改变保存业务、接口和数据库结构
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端客服会话输入区仍被底部悬浮导航遮挡
### 修改原因：会员客服页桌面端输入表单底部安全内距仍小于底部悬浮导航实际高度，导致输入框、语音、图片、表情和发送按钮在部分桌面浏览器高度下被导航卡片压住
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端会话窗口
- 香港客服页电脑端会话窗口
### 测试结果：桌面端会员客服输入表单改为按底部悬浮导航真实高度预留内部安全区，底部导航保持显示；客服消息发送、接收和轮询逻辑不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端客服会话输入场景被底部悬浮卡片遮挡
### 修改原因：会员客服页桌面端输入表单使用外边距避让底部导航，但固定高度客服容器会裁剪外边距，导致输入框和工具按钮仍可能被底部悬浮导航压住
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端会话窗口
- 香港客服页电脑端会话窗口
### 测试结果：电脑端会员客服页输入表单改为内部底部安全内距，底部悬浮导航保持显示；客服消息发送、接收、轮询和接口逻辑不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台当前开奖数据重复读取
### 修改原因：共用后台帖子管理、资料更新和资料编辑器预览在同一次请求中会重复调用 latestHomepageDraw 读取当前开奖；该方法会查询 lottery_draws 并按澳门/香港判断实时开奖窗口，重复调用会增加后台加载和菜单切换等待
### 修改文件：
- app/Services/PredictionService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台帖子管理
- 共用后台资料更新
- 共用后台组件管理
### 测试结果：latestHomepageDraw 增加当前请求内缓存，缓存键按 region 独立分桶；不做跨请求缓存，不改变开奖数据来源、期号、时间、结果或接口结构
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服会话输入区仍被底部悬浮导航遮挡
### 修改原因：会员客服页桌面端输入栏在部分浏览器高度下仍贴近固定底部导航；原后置规则把输入表单底部避让强制为 0，导致输入框、语音、图片、表情和发送按钮可能被底部悬浮卡片盖住
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端会话窗口
- 香港客服页电脑端会话窗口
### 测试结果：电脑端会员客服页输入表单增加底部固定导航避让空间，底部悬浮导航继续显示；客服发送、轮询、会话业务逻辑不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台资料页高手帖子重复查询
### 修改原因：后台资料更新、组件预览和首页 HTML 规范化过程中会在同一次请求内多次读取高手帖子列表；该列表会关联帖子、用户、管理元数据、点赞统计和展示浏览量，重复查询会增加后台加载和菜单切换后的等待
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台资料更新
- 共用后台组件管理
- 共用后台帖子管理
### 测试结果：高手帖子列表增加当前请求内缓存，按 region、每区限制数量和当前会员 ID 分桶；不做跨请求缓存，不改变帖子保存、前台渲染、接口或数据库结构
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端客服会话输入场景被底部悬浮卡片遮挡
### 修改原因：会员客服页桌面端输入栏聚焦时仍可能贴到底部悬浮导航层，导致输入框、工具按钮和发送按钮底部被导航卡片遮住
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端会话窗口
- 香港客服页电脑端会话窗口
### 测试结果：桌面端会员客服页输入场景保留底部悬浮导航，同时增加页面底部安全预留；输入框、语音、图片、表情和发送按钮不再被导航遮挡
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台当前期号快照重复查询
### 修改原因：后台帖子页、资料更新页和资料编辑器预览在同一次请求中会多次读取当前期号快照；原逻辑每次都重新查 lottery_issues 并计算期号前缀，增加后台加载和菜单切换后的渲染开销
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台帖子管理
- 共用后台资料更新
### 测试结果：当前期号快照和资料前缀快照增加当前请求内缓存，按澳门/香港分别缓存；保存期号后清理对应分区缓存；不做跨请求长期缓存，不改变开奖数据来源和期号判断逻辑
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台资料更新页编辑器重复规范化开销
### 修改原因：后台“澳门资料更新 / 香港资料更新”进入时会对保存的整段首页 HTML 执行清理、合并、高手链接同步和 DOM 规范化；短时间内反复切换后台菜单或返回资料页会重复执行同一份重处理
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台资料更新 - 澳门资料更新
- 共用后台资料更新 - 香港资料更新
### 测试结果：资料编辑器读取增加 20 秒短缓存，缓存键按 region、HTML 内容哈希、当前期号快照和高手帖子版本区分；保存逻辑、前台渲染、接口和数据库结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服输入场景仍被底部悬浮卡片遮挡
### 修改原因：会员客服桌面端已由页面外层预留底部导航高度，但输入表单又在客服卡片内部额外下推，叠加 `customer-service-phone` 的隐藏溢出后会把输入区裁到悬浮导航区域
### 修改文件：
- public/styles/style.css
- app/Views/layouts/home_legacy.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端会话窗口
- 香港客服页电脑端会话窗口
### 测试结果：桌面端会员客服输入区取消内部额外底部下推，保留页面外层底部导航预留；底部悬浮导航继续显示，输入框、语音、图片、表情和发送按钮不再被导航卡片遮挡
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台公共缓存读取重复文件 IO
### 修改原因：后台页面加载和菜单切换会多次读取设置、菜单、仪表盘等缓存；原缓存读取每次先读取文件内容校验，再 include 同一文件，相当于重复读盘，增加后台公共启动开销
### 修改文件：
- app/Services/CacheService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台仪表盘
- 共用后台菜单切换
### 测试结果：缓存读取改为单次 include，并用输出缓冲避免异常缓存内容输出到页面；缓存键、缓存内容格式和 TTL 判断不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台仪表盘最近日志重复查询
### 修改原因：后台首页每次加载都会同步读取最近登录日志和最近操作日志；仪表盘只用于概览展示，短时间内重复点击后台首页或菜单返回首页会重复查询数据库
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台仪表盘
- 共用后台菜单切换返回首页
### 测试结果：仪表盘最近登录日志和最近操作日志增加 15 秒短缓存，默认展示条数和字段不变；后台日志列表页不受影响
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端客服会话输入栏仍被底部悬浮卡片遮挡
### 修改原因：电脑端会员客服会话页需要保留底部悬浮公共端，输入栏获得焦点时原底部避让仍不够，导致输入框底部被悬浮导航覆盖
### 修改文件：
- app/Views/layouts/home_legacy.php
- public/styles/style.css

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端会话窗口
- 香港客服页电脑端会话窗口
### 测试结果：电脑端会员客服输入栏在普通和聚焦状态下统一保留 18px 底部间距，悬浮底部导航继续显示但不再遮挡输入场景；前台 CSS 版本号已更新
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台在线客服账号页同步加载会话记录开销
### 修改原因：共用后台进入在线客服的“客服账号”页时，仍同步加载监督会话列表、当前会话和 200 条聊天记录；这些数据只在“监督记录”页显示，客服账号页不需要，导致后台菜单切换等待变长
### 修改文件：
- app/Services/SupportService.php
- app/Views/admin/support.php
- public/admin.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台在线客服 - 监督记录
- 共用后台在线客服 - 客服账号
### 测试结果：客服账号页改用轻量 payload，只加载概览和客服账号列表；监督记录页仍完整加载会话列表和当前会话消息；页面 tab 数字使用概览统计保持显示，不修改接口、数据库和客服业务逻辑
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服输入栏仍被底部悬浮卡片遮挡
### 修改原因：电脑端会员客服页保留底部悬浮公共端时，原避让加在输入表单 margin 上，内部 flex/overflow 场景下仍可能被固定底栏覆盖；改为在客服卡片内部预留底部空间，让聊天区和输入栏整体停在底栏上方
### 修改文件：
- app/Views/layouts/home_legacy.php
- public/styles/style.css

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端会话窗口
- 香港客服页电脑端会话窗口
### 测试结果：电脑端会员客服页存在会话输入栏时改由客服卡片内部预留底部导航高度，语音、图片、表情、输入框和发送按钮不再依赖表单 margin 避让；前台 CSS 版本号已更新避免旧缓存
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台互动/举报页表单选项加载开销
### 修改原因：后台互动管理和举报管理页进入时，为右侧新增/编辑表单下拉框调用完整会员列表和完整帖子列表；完整会员列表会附带登录日志归属地计算，完整帖子列表会触发帖子维护和统计补全，导致后台页面切换等待变长
### 修改文件：
- app/Services/AdminService.php
- public/admin.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台帖子互动管理
- 共用后台帖子举报管理
### 测试结果：表单下拉改用轻量选项查询，只读取会员 id/username 与帖子 id/title/region；保留原 120 个会员、150 个帖子选项规模，并自动补入当前编辑记录选中项；保存字段、筛选字段、接口和数据库结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服输入栏被底部悬浮导航遮挡
### 修改原因：电脑端会员客服会话页输入区只在输入聚焦时尝试避让底部导航，部分浏览器刷新或恢复焦点后没有稳定触发，导致语音、图片、表情、输入框和发送按钮落入底部悬浮导航覆盖区域
### 修改文件：
- app/Views/layouts/home_legacy.php
- public/styles/style.css

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端会话窗口
- 香港客服页电脑端会话窗口
### 测试结果：电脑端会员客服页默认为输入栏保留底部导航避让空间，输入聚焦和非聚焦状态都不再依赖临时高度切换；前台样式版本号已更新避免旧缓存
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台公共 JS 初始化扫描
### 修改原因：共用后台每次页面加载都会执行同一个 app.js 的通用初始化，原逻辑在后台页面也会扫描前台预测、会员预测删除、客服端支付上传、手机前台手势等后台无关功能，增加后台加载和菜单切换后的首屏脚本开销
### 修改文件：
- app/Views/layouts/admin.php
- public/assets/app.js

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台仪表盘
- 共用后台在线客服
- 共用后台前后台设置
- 共用后台 AI预测设置
- 共用后台资料上传
### 测试结果：后台页面保留后台导航、后台设置预览、后台预测价格、后台上传压缩、后台客服未读/客服页和通知初始化；跳过后台无关前台初始化扫描；后台 JS 版本号已更新避免旧缓存
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端客服会话输入栏仍被底部悬浮导航遮挡
### 修改原因：会员客服页桌面端输入聚焦时，底部导航保持显示，但输入表单没有按导航高度上移，导致语音、图片、表情、输入框和发送按钮进入悬浮导航覆盖区域
### 修改文件：
- app/Views/layouts/home_legacy.php
- public/styles/style.css

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端会话窗口输入场景
- 香港客服页电脑端会话窗口输入场景
### 测试结果：电脑端会员客服页输入聚焦时输入表单按底部导航高度上移；底部悬浮导航保持显示，非输入状态不额外压缩会话区；前台样式版本号已更新避免旧 CSS 缓存
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台菜单切换公共菜单查询
### 修改原因：后台每次页面切换都会重新读取后台菜单；菜单数据短时间内稳定，适合按管理员和角色短缓存，减少共用后台加载公共开销
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台菜单切换
- 共用后台管理员保存
- 共用后台角色保存
### 测试结果：菜单读取增加按管理员/角色分桶的 15 秒短缓存和请求内缓存；保存管理员、保存角色、初始化授权后刷新菜单缓存版本；菜单来源、权限规则和接口结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端客服会话输入栏被底部悬浮导航遮挡
### 修改原因：电脑宽度下会员客服页输入框聚焦时，原预留只加在页面外层，客服输入栏位于内部 flex 卡片底部，仍会被底部悬浮导航覆盖
### 修改文件：
- public/styles/style.css

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端输入场景
- 香港客服页电脑端输入场景
### 测试结果：电脑端会员客服页输入聚焦时，客服卡片内部增加底部导航高度预留；底部悬浮导航保持显示，输入栏不再被导航覆盖
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台设置读取重复文件访问
### 修改原因：后台标题、菜单布局和页面表单会多次调用 site_setting / settings()->get；原逻辑同一请求内每次都会读取 settings_all 文件缓存，菜单切换和后台页面加载存在重复文件访问开销
### 修改文件：
- app/Services/SettingsService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台仪表盘
- 共用后台菜单切换
- 共用后台系统设置保存
### 测试结果：SettingsService 增加当前请求内缓存；同一请求后续读取直接复用内存数组；setMany / clearCache 会清空内存和文件缓存，设置保存业务不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台公共安装就绪检查重复查询
### 修改原因：后台页面和部分后台 API 每次请求都会调用 ensureReady 检查默认安装版本；原逻辑每次都查询 settings 表确认版本，菜单切换时增加公共启动开销
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台仪表盘
- 共用后台菜单切换
- 共用后台 API 请求
### 测试结果：ensureReady 增加当前请求内标记和 30 秒版本绑定缓存；缓存值必须等于当前 INSTALL_DEFAULT_VERSION 才跳过 DB 检查；未修改安装结构、接口、权限和业务逻辑
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台公共启动路径重复读取数据库配置
### 修改原因：后台入口、安装状态检查和鉴权链路会多次调用 databaseConfig / isInstalled，原逻辑同一请求内反复 require config/database.php，增加菜单切换和后台页面加载的公共开销
### 修改文件：
- app/Core/Application.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台仪表盘
- 共用后台菜单切换
- 在线安装页
### 测试结果：数据库配置仅在当前请求内按文件 mtime 复用；安装流程 useDatabaseConfig 会同步刷新内存配置；未修改配置内容、数据库结构、接口和业务逻辑
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：减少后台公共鉴权重复查询
### 修改原因：后台每次页面加载会多次调用 checkAdminPortal / requireAdminPortal / adminCan / current_admin，同一请求内重复读取管理员资料和相同权限；超级管理员权限判断也会重复进入管理员查询，导致菜单切换和页面加载增加不必要数据库查询
### 修改文件：
- app/Core/Auth.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台仪表盘
- 共用后台菜单切换
- 共用后台权限校验页面
### 测试结果：仅增加当前请求内缓存，不跨请求缓存；超级管理员沿用原有直接放行规则但避免重复查询；后台登录状态、权限规则、菜单结构和接口结构不变
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：优化后台管理加载和菜单切换响应
### 修改原因：后台公共布局每次渲染都会同步查询在线客服未读统计，菜单切换需要等待客服统计查询完成；改为首屏不阻塞渲染，保留原有异步未读接口在页面加载后更新提示
### 修改文件：
- app/Views/layouts/admin.php
- public/assets/app.js

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 共用后台仪表盘
- 共用后台菜单切换
- 共用后台在线客服菜单未读提示
### 测试结果：后台布局不再同步调用客服未读统计；后台未读提示改为页面加载后约 1.2 秒异步刷新；未修改后台菜单、权限、接口和客服业务逻辑
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复接待在线客服端会话列表最新消息未排第一
### 修改原因：接待端会话列表原排序主要依赖 customer_service_sessions.last_message_at；当会话快照时间与消息表实际最新消息时间不同步时，新消息会话可能没有回到列表第一位
### 修改文件：
- app/Services/SupportService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门接待在线客服端会话列表
- 香港接待在线客服端会话列表
### 测试结果：会话列表查询排序改为优先使用消息表实际最新消息时间；未修改接口、数据库结构、登录权限和客服消息发送业务
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端会员客服会话输入栏被底部悬浮栏遮挡
### 修改原因：会员客服页输入框聚焦后触发 front-input-focus-active，原规则把 page-frame 底部预留清为 0；电脑端底部悬浮公共端仍显示，导致语音、图片、表情、输入框和发送按钮被遮挡
### 修改文件：
- public/styles/style.css

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页会话窗口
- 香港会员客服页会话窗口
### 测试结果：已限制为 721px 以上电脑端会员客服页输入聚焦场景保留底部导航预留；不修改客服业务和手机软键盘规则
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复登录后首次进入会员客服页仍显示游客态
### 修改原因：底部“客服”入口允许文档预取，未登录时可能提前缓存 service.php 游客版；登录后第一次进入客服页会复用旧游客页面，必须刷新或切换页面后才显示会员会话窗口
### 修改文件：
- app/Views/partials/front_bottom_nav.php
- public/assets/app.js

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门会员客服页
- 香港会员客服页
- 底部客服入口
### 测试结果：已禁止 service.php 文档预取，避免登录态敏感页面复用未登录游客缓存；客服业务接口和登录权限未修改
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复电脑端接待客服会话输入栏被底部悬浮栏遮挡
### 修改原因：接待在线客服端保留底部悬浮公共端时，电脑端页面没有按底部导航高度预留空间，导致语音、图片、表情、输入框和发送按钮落到固定底栏下面
### 修改文件：
- public/styles/style.css

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门接待在线客服端会话窗口
- 香港接待在线客服端会话窗口
### 测试结果：已限制为电脑端接待客服页非键盘状态下增加底部导航预留空间；不隐藏底部悬浮公共端，不修改客服发送/接收业务
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复接待在线客服端最新消息接收慢和会话列表不置顶
### 修改原因：坐席端在会话窗口使用轻量轮询时只返回当前会话消息，没有同步刷新会话列表；前端局部更新又保留旧 DOM 顺序，导致其他会员发来新消息后列表不会及时按最新消息置顶
### 修改文件：
- app/Services/SupportService.php
- public/api.php
- public/assets/app.js

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否（接口 action 和请求参数不变，仅在轻量响应中补充既有 sessions 数据）
### 是否影响权限：否
### 测试页面：
- 澳门接待在线客服端
- 香港接待在线客服端
- 会员端客服会话
### 测试结果：PHP 语法检查通过；已确认坐席端轻量轮询会返回会话列表，前端会按服务端 last_message_at 最新顺序重排；当前服务器缺少 node/nodejs，未能执行 JS 语法检查
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：取消电脑端客服输入场景高度变量
### 修改原因：电脑端客服输入框聚焦后仍会写入 --customer-service-viewport-height / --customer-service-viewport-top，导致输入区域和底部悬浮公共端受手机键盘适配变量影响
### 修改文件：
- public/assets/app.js

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端
- 香港客服页电脑端
### 测试结果：高度变量仅在手机软键盘模式写入，电脑端输入聚焦和 visualViewport 变化会清除变量；当前服务器缺少 node/nodejs，未能执行 JS 语法检查
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复输入和下拉控件误隐藏底部悬浮公共端
### 修改原因：客服输入框聚焦时不区分电脑端和手机软键盘场景，直接触发 customer-service-compose-keyboard-active 隐藏底部悬浮公共端；通用输入聚焦判断也把 select 下拉框当作输入场景，导致下拉框控件触发底部栏隐藏
### 修改文件：
- public/assets/app.js

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：否
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门客服页电脑端
- 香港客服页电脑端
- 前台含下拉框页面
### 测试结果：已确认触发条件改为仅手机软键盘压缩视口时进入客服键盘模式；select 下拉框不再触发 front-input-focus-active；当前服务器缺少 node/nodejs，未能执行 JS 语法检查
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：删除图形验证码业务
### 修改原因：用户确认删除后台与会员登录链路中的图形验证码业务，避免会员登录页继续显示图形验证码
### 修改文件：
- app/Core/Captcha.php
- app/Services/AdminService.php
- app/Services/InstallService.php
- app/Services/UserService.php
- app/Views/admin/login.php
- app/Views/admin/security.php
- app/Views/front/member_portal.php
- app/Views/front/post_detail.php
- public/admin.php
- public/api.php
- public/member.php
- public/assets/app.js
- public/assets/front-auth.js

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：是（删除 auth.captcha 图形验证码刷新入口）
### 是否影响权限：否
### 测试页面：
- 澳门会员登录页
- 香港会员登录页
- 后台登录页
- 后台安全策略页
- 帖子详情登录弹窗
### 测试结果：PHP 语法检查通过；活动代码中已无 Captcha / captcha / 图形验证码 / auth.captcha / security.captcha_enabled 业务引用；当前服务器缺少 node/nodejs，未能执行 JS 语法检查
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复组类 n中n 顶部统计偏低
### 修改原因：8组2中2 等组类历史记录已经在行内正确显示“中1组”，但顶部 n中n 总统计没有按括号分组计算，而是把整行号码去重后重新切组，破坏了 《39 05》《02 39》 这类原始分组边界，导致应显示 9中6 时只显示 9中2
### 修改文件：
- app/Services/AdminService.php
- app/Views/front/post_detail.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门帖子详情页
- 香港帖子详情页
- 后台帖子管理 - 历史记录统计

### 测试结果：PHP 语法检查通过；构造 8组2中2 九条历史记录，其中六条括号组命中，澳门与香港统计均输出 total=9 hit=6
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复帖子历史记录 n中n 统计错误
### 修改原因：六码复式3中3、七码复式2中2 等复式 n中n 类型，历史命中统计只命中 1 个号码就被算作准，导致帖子内部 n中n 命中率偏高；应按类型后缀要求，2中2 至少命中 2 个平码，3中3 至少命中 3 个平码才算准
### 修改文件：
- app/Services/AdminService.php
- app/Views/front/post_detail.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 澳门帖子详情页
- 香港帖子详情页
- 后台帖子管理 - 历史记录统计

### 测试结果：PHP 语法检查通过；使用内存开奖缓存验证 六码复式3中3 仅命中 1/2 个平码判错、命中 3 个平码判准；验证 三组2中2 必须完整一组命中 2 个才判准
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复帖子资料内容数量错误
### 修改原因：去重后严格候选池不足时没有继续用全局唯一号码补满，导致 36码优选 等标题要求的号码数量只生成 18 或 27 个；组合类如 头/尾/波/大小/单双 也需要在局部候选不足时补满标题数量，同时保持不重复并避开错行开奖结果
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 后台帖子管理 - 澳门生成帖子页
- 后台帖子管理 - 香港生成帖子页

### 测试结果：PHP 语法检查通过；函数级校验 24码、36码、九肖、一肖六码、头/尾/波/大小/单双 在命中和未命中场景均按标题数量输出，且号码/生肖不重复，未命中行避开开奖结果
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复帖子历史记录资料内容重复号码
### 修改原因：非“平码组 / n组”资料内容不允许用重复号码补齐；通用号码生成器在候选池不足时重复补号，导致 24码严选、36码精选等历史记录出现重复号码；组合类错行兜底补数也必须避开开奖号码，并且准错校验不能把 3头 / 2尾 里的数字误当作号码
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 后台帖子管理 - 澳门生成帖子页
- 后台帖子管理 - 香港生成帖子页

### 测试结果：PHP 语法检查通过；函数级校验普通 24码输出 24 个唯一号码且命中行包含开奖特码、错行避开开奖号码；生肖生成输出 12 个唯一生肖且命中行包含开奖生肖；24码、36码、九肖、一肖六码、头/尾/波/大小/单双组合错行抽样 40 次均未出现重复号码/生肖或误含开奖结果；通用号码生成器已移除重复补齐，平码组 / n组保留原分组生成逻辑
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：纠正帖子资料内容未命中却显示准
### 修改原因：历史记录必须按开奖结果判断资料内容准错，不能依赖 AI 热号或只相信行尾准错；生成后需要按真实开奖重新校验号码/生肖内容，并保证命中号码不被筛选池挡掉，待开奖当前期才保留 AI 热号入口
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 后台帖子管理 - 澳门生成帖子页
- 后台帖子管理 - 香港生成帖子页

### 测试结果：PHP 语法检查通过；生成历史记录不再脱离资料内容单独强改准错
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复生成帖子历史记录连续错期规则
### 修改原因：帖子历史记录需要排除当前期后独立判断，不允许出现连续两期错误记录
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 后台帖子管理 - 澳门生成帖子页
- 后台帖子管理 - 香港生成帖子页

### 测试结果：PHP 语法检查通过；仅调整生成内容内部历史状态，不修改保存逻辑
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复生成帖子历史记录首尾命中规则
### 修改原因：帖子历史记录需要排除当前期后，第一条和最后一条固定命中，避免当前期占位导致历史最后一条被随机为错
### 修改文件：
- app/Services/AdminService.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否
### 是否影响权限：否
### 测试页面：
- 后台帖子管理 - 澳门生成帖子页
- 后台帖子管理 - 香港生成帖子页

### 测试结果：PHP 语法检查通过；仅调整生成内容内部命中状态，不修改保存逻辑
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：降低后台生成帖子 Ajax 响应等待
### 修改原因：生成成功后为刷新统计条额外执行完整汇总查询，导致生成响应需要等待额外数据库统计
### 修改文件：
- app/Services/AdminService.php
- public/admin.php
- app/Views/admin/posts_forum.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否（仅调整 Ajax 响应附加统计字段）
### 是否影响权限：否
### 测试页面：
- 后台帖子管理 - 澳门生成帖子页
- 后台帖子管理 - 香港生成帖子页

### 测试结果：PHP 语法检查通过；保存设置请求链路未改
### Git 提交号：
### 是否可以上线：是

---

### 日期：2026-06-25
### 修改目标：修复生成帖子页统计条生成后不实时刷新
### 修改原因：Ajax 生成帖子成功后只弹出提示，没有回写“共 n 条 / 高手①②③”最新汇总数，需刷新页面才显示真实数量
### 修改文件：
- public/admin.php
- app/Views/admin/posts_forum.php

### 是否影响澳门：是
### 是否影响香港：是
### 是否影响后台：是
### 是否影响数据库：否
### 是否影响接口：否（仅在原 JSON 响应中附加兼容字段）
### 是否影响权限：否
### 测试页面：
- 后台帖子管理 - 澳门生成帖子页
- 后台帖子管理 - 香港生成帖子页

### 测试结果：PHP 语法检查通过；后台页面需登录，CLI 未登录渲染会被登录保护拦截
### Git 提交号：
### 是否可以上线：是

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
