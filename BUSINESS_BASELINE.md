# 澳门/香港半成品站点业务基线清单

> 用途：作为后续安全增量纠偏前的对照清单。此文件只记录现状、不可破坏点、待确认项和回归测试项，不改变任何运行逻辑。

## 1. 当前已知结构

| 类型 | 已知内容 | 状态 |
|---|---|---|
| 根入口 | `index.php` 跳转到 `/public/index.php` | 已确认 |
| 静态入口 | `index.html`、`404.html` | 已确认存在 |
| 前台入口 | `public/index.php` 澳门首页；`public/record.php` 香港首页；`public/forecast.php` AI 预测；`public/history.php` 历史开奖；`public/member.php` 会员中心；`public/post.php` 帖子详情；`public/service.php` 在线客服 | 已确认 |
| 后台入口 | `public/admin.php` 统一后台，按 `page` 参数分发模块 | 已确认 |
| API 入口 | `public/api.php`，仅支持 POST，按 `action` 参数分发 | 已确认 |
| 安装入口 | `public/install.php` | 已确认，暂不动 |
| 业务目录 | `app/Core`、`app/Services`、`app/Views` | 已确认 |
| 前台模板 | `app/Views/front`、`app/Views/layouts/home_legacy.php`、`app/Views/partials` | 已确认 |
| 后台模板 | `app/Views/admin`、`app/Views/layouts/admin.php` | 已确认 |
| 配置目录 | `config/app.php`、`config/database.php`、`config/database.php.example` | 已确认；数据库配置含敏感信息，不外泄 |
| 数据库目录 | `database/schema.sql` | 已确认 |
| 资源目录 | `resources/defaults/home_editor_default.html` | 已确认 |
| 任务目录 | `cli/run_post_generator_schedule.php` | 已确认 |
| 运行数据 | `storage/` | 保护，不扫描清理 |
| 公开上传 | `public/uploads/avatar`、`public/uploads/customer_service`、`public/uploads/customer_service_payment`、`public/uploads/material` | 保护，不移动 |

## 2. 不可破坏清单

| 类型 | 不可破坏项 |
|---|---|
| 页面 URL | `/`、`/index.php`、`/index.html`、`/404.html`、`/public/index.php`、所有旧页面 URL |
| API 契约 | 旧请求参数、返回字段、状态码、错误格式 |
| 数据库 | 旧表、旧字段、旧数据、主表结构 |
| 权限 | 登录态、后台角色、区域权限、业务权限、危险操作权限 |
| 澳门/香港 | 两地数据不能混查、混写、混展示 |
| 业务 A/B | 两套业务数据、审核、统计、通知不能串 |
| 上传 | `storage/`、旧图片 URL、附件 URL、上传路径 |
| 后台流程 | 登录、审核、封禁、删除、批量操作、审计日志 |
| 定时任务 | `cli/` 中可能存在的任务脚本 |
| SEO | 首页、404、旧收录链接、跳转逻辑 |

## 3. 待补全业务基线

| 清单 | 需要记录 | 状态 |
|---|---|---|
| 前台页面 | `/public/index.php` 澳门首页；`/public/record.php` 香港首页；`/public/forecast.php` 双地区预测；`/public/history.php` 双地区历史；`/public/member.php` 双地区会员；`/public/post.php?id=` 帖子弹窗详情；`/public/service.php` 双地区客服 | 已补初版 |
| 后台页面 | `dashboard`、`admins`、`roles`、`settings`、`support`、`install`、`uploads`、`users`、`posts`、`sections`、`categories`、`comments`、`interactions`、`reports`、`audits`、`draws`、`home`、`issues`、`login_logs`、`operation_logs`、`exceptions`、`security` | 已补初版 |
| API | `public/api.php` POST `action` 分发；核心 action 见下方 API 清单 | 已补初版 |
| 数据库表 | `database/schema.sql` 共 48 张表，核心分组见下方数据库清单 | 已补初版 |
| 用户角色 | 前台 `roles/users/permissions`；后台 `admin_roles/admin_users/admin_permissions/admin_menus` | 已确认结构，权限语义待补 |
| 澳门/香港逻辑 | 代码使用 `macau`、`hongkong`；首页分 `index.php`/`record.php`；多表有 `region` 字段 | 已确认 |
| 业务 A/B 边界 | 当前未看到明确 `business_a/business_b`；现有边界更像“论坛/开奖预测/客服/后台”模块，上传表有 `business_type` | 待业务确认 |
| 上传文件 | `public/uploads/*`、`storage/uploads`、`storage/screenshots` | 已确认路径，不能移动 |
| 定时任务 | `cli/run_post_generator_schedule.php` | 已确认文件，任务规则待补 |

### 3.1 API action 清单

| 分组 | action |
|---|---|
| 公共/认证 | `draw.latest`、`auth.captcha`、`auth.login`、`auth.admin_login`、`auth.register`、`auth.logout` |
| 找回密码 | `password_reset.verify_reset`、`password_reset.reset` |
| 会员资料 | `profile.update`、`profile.avatar`、`profile.password`、`profile.recovery` |
| 预测/开奖 | `prediction.logs.delete`、`forecast.generate`、`admin.predict.generate`、`admin.draw.save` |
| 帖子 | `post.create`、`post.reply`、`post.view_count`、`post.buy`、`post.customer_service.update`、`post.like`、`post.favorite`、`post.follow`、`comment.like` |
| 会员客服 | `customer_service.member.poll`、`customer_service.member.unread`、`customer_service.member.send`、`customer_service.member.clear`、`customer_service.member.payment_settings`、`customer_service.typing` |
| 客服坐席 | `customer_service.agent.login`、`customer_service.agent.logout`、`customer_service.agent.presence`、`customer_service.agent.settings`、`customer_service.agent.nickname_delete`、`customer_service.agent.payment_settings`、`customer_service.agent.poll`、`customer_service.agent.send`、`customer_service.agent.score`、`customer_service.agent.clear`、`customer_service.agent.queue_delete`、`customer_service.agent.block`、`customer_service.agent.unblock`、`customer_service.agent.close` |
| 后台 | `admin.settings.save`、`admin.admin.save`、`admin.admin.toggle`、`admin.role.save`、`admin.post.save`、`admin.post.bulk`、`admin.post_lock.save`、`admin.post_like_increment.save`、`admin.post_view_display.save`、`admin.post_sale_buyer_increment.save`、`admin.post.quick`、`admin.post.categories`、`admin.user.save`、`admin.user.score`、`admin.reset.process`、`admin.cache.clear` |
| 后台客服 | `customer_service.admin.poll`、`customer_service.admin.unread`、`customer_service.admin.send`、`customer_service.admin.clear`、`customer_service.admin.close`、`customer_service.agent.save`、`customer_service.agent.delete` |

### 3.2 数据库表清单

| 分组 | 表 |
|---|---|
| 前台用户/权限 | `roles`、`permissions`、`role_permissions`、`users`、`login_logs`、`password_reset_requests`、`user_ban_records`、`user_vips` |
| 上传 | `uploads` |
| 帖子/社区 | `posts`、`replies`、`comment_likes`、`purchases`、`post_manage_meta`、`post_unique_views`、`post_view_display_events`、`forum_sections`、`forum_categories`、`audit_records`、`post_interactions`、`post_reports` |
| 开奖/AI预测 | `lottery_draws`、`ai_predictions`、`ai_prediction_participations`、`lottery_issues` |
| 首页/运营配置 | `settings`、`notices`、`home_banners`、`ad_slots`、`recommend_positions`、`home_nav_entries`、`home_module_configs` |
| 日志/统计 | `admin_logs`、`system_logs`、`page_views`、`system_exception_logs`、`admin_login_logs`、`admin_operation_logs` |
| 后台权限 | `admin_roles`、`admin_permissions`、`admin_role_permissions`、`admin_menus`、`admin_role_menus`、`admin_users` |
| 在线客服 | `customer_service_accounts`、`customer_service_sessions`、`customer_service_messages` |
| 安装初始化 | `install_records`、`init_data_records` |

### 3.3 地区字段清单

| 表/代码 | 地区逻辑 |
|---|---|
| `posts` | `region` 区分澳门/香港帖子 |
| `lottery_draws`、`ai_predictions`、`ai_prediction_participations`、`lottery_issues` | `region` 区分开奖和预测 |
| `notices`、`home_banners`、`ad_slots`、`recommend_positions`、`home_nav_entries`、`home_module_configs` | `region` 支持 `all` 或地区配置 |
| `forum_sections`、`forum_categories` | `region` 区分论坛板块/分类 |
| `public/index.php` | 固定澳门 `macau` |
| `public/record.php` | 固定香港 `hongkong` |
| `public/forecast.php`、`public/history.php`、`public/member.php`、`public/service.php` | 接收 `region`，仅允许 `macau/hongkong` |

## 4. 当前混乱点

| 类型 | 当前表现 | 风险 |
|---|---|---|
| 目录结构 | 根目录混有入口、静态页、大 zip、部署包、运行目录 | 高 |
| 前台入口 | `index.html` 与 `index.php` 可能存在首页优先级冲突 | 高 |
| 后台结构 | `admin.php` 单文件承载登录、路由、表单处理和页面分发 | 高 |
| API 结构 | `api.php` 单文件 POST action 分发，action 数量较多 | 高 |
| 数据库 | 前台角色表与后台角色表并存，社区/开奖/首页/客服/日志表较多 | 高 |
| 权限 | 后台权限已通过 `requireAdminPortal` 检查；区域/业务数据权限粒度仍需逐模块确认 | 极高 |
| 地区逻辑 | 已有 `macau/hongkong/all`，但散落在入口、SQL、模板和样式中 | 高 |
| 业务边界 | 未看到明确业务 A/B 命名，当前以模块和 `region/business_type` 做边界 | 高 |
| 安全 | 配置、上传、运行目录访问风险待查 | 高 |
| 合规 | 站点含“六合彩/预测”相关功能，澳门/香港上线前需做当地合规核验 | 高 |
| 可暂缓 | 大 zip、部署包、非核心 UI 不统一 | 中低 |

## 5. 低风险优先项

| 优先项 | 处理原则 |
|---|---|
| 补全页面/API/数据库基线 | 只读整理，不改运行逻辑 |
| 文案本地化 | 优先繁体中文，不硬编码地区 |
| 非核心 UI 样式 | 单页面小改，不动业务逻辑 |
| 静态资源路径 | 只修明确错误，不改旧 URL |
| 后台列表展示 | 可优先处理分页、筛选、空状态，但不改权限 |
| 已知低风险 bug | 只修定位明确、可回滚问题 |

## 6. 高风险暂缓项

| 模块 | 暂缓原因 |
|---|---|
| 登录/注册 | 影响用户会话和前后台访问 |
| 权限系统 | 可能造成越权 |
| 数据库结构 | 可能破坏旧数据 |
| 核心 API | 可能破坏前台、后台、移动端调用 |
| 澳门/香港筛选 | 可能串地区数据 |
| 业务 A/B 查询 | 可能串业务数据 |
| 上传路径 | 可能导致旧图片或附件失效 |
| 定时任务 | 可能影响自动审核、通知、清理 |
| 首页入口 | 可能影响 SEO 和旧访问路径 |
| 废弃文件清理 | 未确认引用前不能删除 |

## 7. 每次修改前检查

| 检查项 | 要求 |
|---|---|
| 是否影响旧 URL | 影响则暂停，先确认 |
| 是否影响旧 API | 参数、返回、状态码、错误格式必须不变 |
| 是否影响数据库 | 默认不允许删除、改名、改主表结构 |
| 是否影响权限 | 不能放宽登录、后台、区域、业务权限 |
| 是否影响澳门/香港 | 必须确认地区边界不串 |
| 是否影响业务 A/B | 必须确认业务边界不串 |
| 是否影响上传 | 旧文件路径和 URL 必须可用 |
| 是否影响后台危险操作 | 必须保留确认、权限、审计 |

## 8. 回归测试清单

| 检查项 | 通过标准 |
|---|---|
| 原有页面 | 旧 URL 可打开，状态码和跳转符合旧逻辑 |
| 原有 API | 旧参数可用，返回格式不变 |
| 登录注册 | 登录、退出、注册流程正常 |
| 后台权限 | 有权限可操作，无权限被拒绝 |
| 澳门数据 | 不进入香港业务 |
| 香港数据 | 不进入澳门业务 |
| 业务 A/B | 数据、审核、统计不串 |
| 手机端 | 无普通页面横向滚动，关键操作可点击 |
| 电脑端 | 菜单、列表、表单、弹窗可用 |
| 旧数据 | 用户、内容、上传文件仍可读取 |
| 回滚 | 可按备份或反向 diff 恢复 |

## 9. 继续开发推进业务规则

> 本节用于承接“PHP 项目 AI 常驻自定义指令 - 继续开发推进版”的业务执行规则。它不覆盖 `AGENTS.md` 中对 `/www/wwwroot/599549.com` 的站点锁定、防跑偏、防旧站点、防 `archive`、防误改规则。

### 9.1 站点边界

| 规则 | 要求 |
|---|---|
| 当前站点 | 只处理 `599549.com`，项目根目录固定为 `/www/wwwroot/599549.com` |
| 锁站规则 | 保留并服从 `AGENTS.md` 的站点锁定规则，不进入 `/www/wwwroot` 父目录做站点修改 |
| 旧站隔离 | 不套用 `5995459`、`123249.com` 或其它旧站点方案 |
| archive 隔离 | 不读取 `archive` 作为当前实现方案，不从 `archive` 复制旧方案覆盖当前站点 |
| 功能保护 | 不删除会员、充值、购买、订单、客服、帖子、预测、开奖资料、后台管理功能 |

### 9.2 默认工作模式

| 场景 | 处理方式 |
|---|---|
| 普通开发、修改、完善、修复、优化、页面调整、体验处理 | 默认进入继续开发推进模式，先理解当前文件和现有逻辑，再做最小必要修改 |
| 普通 UI、普通样式、手机端适配、电脑端适配 | 可以直接推进，保持现有接口、字段、权限、登录、数据库不变 |
| 同类样式同步、已确认功能的小修复 | 可以直接推进，但只同步本次影响范围内的同类位置，不借机扩散到无关模块 |
| 用户明确要求只检查、审计、纠偏、回滚、恢复旧版本 | 进入对应专项模式，按用户限定范围执行 |

### 9.3 高风险修改必须先确认

以下变更属于高风险，必须先说明原因、影响范围和替代方案，等待用户确认后才能修改：

| 高风险项 | 保护要求 |
|---|---|
| 数据库 | 新增表、字段、索引；删除字段；修改字段类型、字段含义、旧数据结构 |
| 权限 | 后台角色、菜单权限、区域权限、业务权限、危险操作权限 |
| 登录 | 前台登录、后台登录、会话、验证码、密码校验、找回密码逻辑 |
| 支付 | 真实支付通道、第三方支付、支付回调、金额结算规则 |
| 充值 | 充值入口、充值订单、充值记录、充值到账逻辑 |
| 购买 | 帖子资料购买、授权、重复购买判断、购买记录 |
| 订单 | 订单号、订单状态、订单金额、订单回调、订单核对链路 |
| 积分扣减 | 积分余额、扣减、回滚、消费记录、管理员手动增减积分 |
| 全局 CSS/JS | 影响全站或大面积页面的公共样式、公共脚本、事件绑定、资源加载方式 |
| 入口文件 | `index.php`、`public/index.php`、`public/admin.php`、`public/api.php`、`public/install.php` 等入口 |
| 路由 | 页面 URL、API action、请求方式、参数名、返回结构、跳转逻辑 |
| 后台核心逻辑 | 后台登录、权限校验、菜单分发、批量操作、审核、封禁、删除、审计日志 |

### 9.4 默认保护项

| 类型 | 默认不改 |
|---|---|
| 接口 | 接口地址、请求方式、参数名、参数含义、返回字段、返回结构 |
| 表单 | 表单 `action`、字段名、提交语义 |
| 数据库 | 表结构、字段含义、旧数据、旧表兼容 |
| 权限登录 | 登录态、管理员权限、角色权限、区域权限、危险操作权限 |
| 澳门/香港 | 开奖时间、开奖结果、期号、数据来源、历史记录、接口来源、展示规则独立 |
| 上传资源 | `storage/`、`public/uploads/`、旧图片、旧附件 URL |
| 已确认链路 | 已确认正确的页面、样式、接口和业务流程 |

### 9.5 开发推进要求

| 步骤 | 要求 |
|---|---|
| 先看现有代码 | 修改前先读取相关文件和当前逻辑，不编造文件、函数、字段、接口 |
| 确认主线 | Controller 接收请求，Service 处理业务，Repository 查询写入，View/PHP 展示，JavaScript 负责交互，CSS 只负责样式 |
| 最小修改 | 只改本次目标相关文件，不全项目重构，不新增并行业务绕开旧逻辑 |
| 同类同步 | 同一字段来源、业务含义、组件样式、前后台展示链路、电脑端/手机端体验属于同类时同步检查 |
| 安装初始同步 | 仅在新增后台菜单、权限、配置、默认数据、安装 SQL、初始化脚本、页面路由、默认导航等场景触发 |
| 验证 | 修改后给出简短验证方法，必要时说明未能验证的环境原因 |

### 9.6 澳门/香港双系统规则

| 规则 | 要求 |
|---|---|
| 双前台 | 澳门系统和香港系统同步考虑，前台结构、栏目、导航、广告、公告、SEO、视觉和操作体验默认统一 |
| 共后台 | 两套前台共用一个后台管理系统，不擅自拆分共用后台 |
| 地区参数 | `region=macau` / `region=hongkong` 不得丢失，切换后导航、页面状态、数据归属必须正确 |
| 开奖独立 | 不强制统一澳门/香港开奖时间、开奖结果、期号、数据来源、历史记录、接口来源、展示规则 |

### 9.7 积分、充值、购买边界

| 允许推进 | 禁止开发 |
|---|---|
| 会员充值积分 UI、积分余额显示、充值记录、消费记录、帖子资料积分购买入口、已购买/未购买状态、积分不足提示、后台积分配置、后台帖子资料积分设置 | 下注、赔率、返奖、提现、彩票销售、赌博交易、赌博资金结算、积分兑换现金、积分参与开奖结果交易、承诺购买资料必然盈利或中奖 |

涉及真实支付通道、第三方支付回调、资金结算、订单金额结算、积分扣减、购买授权的改动，必须先确认。

### 9.8 前后台与响应式规则

| 类型 | 要求 |
|---|---|
| 前台 CSS | 与后台 CSS 分开管理，不让前台加载后台无关 CSS |
| 后台 CSS | 统一表格、筛选、分页、表单、弹窗、按钮、状态标签，不为每页复制一套样式 |
| 电脑端 | 信息密度、表格、筛选、分页、批量操作、后台效率和弹窗表单尺寸合理 |
| 手机端 | 按钮易点，输入易用，表格必要时卡片化或横向滚动，固定导航不遮挡内容，弹窗可关闭可滚动 |
| 性能 | 不重复加载 CSS/JS，不加载无关资源，不产生大量 404，不重复请求接口，大列表必须分页或懒加载 |

### 9.9 安全底线

| 类型 | 要求 |
|---|---|
| SQL | 使用预处理语句，不拼接用户输入到 SQL |
| 输入输出 | GET、POST、COOKIE、REQUEST、SERVER 输入验证过滤；输出 HTML 使用 `htmlspecialchars` |
| CSRF | 新增、修改、删除、批量操作、状态切换必须考虑 CSRF |
| 密码 | 使用 `password_hash` 和 `password_verify`，不记录明文密码、Token、Session ID、Cookie、密钥 |
| 后台 | 后台功能必须验证管理员权限，危险操作必须二次确认并保留审计 |
| 积分 | 充值、购买、扣减、后台手动增减积分必须校验身份并保留记录，余额不能为负数 |

### 9.10 技术栈边界

| 项目 | 要求 |
|---|---|
| 默认技术栈 | PHP 7.3.4+、MySQL 5.7+/8.0+ 或 MariaDB、原生 JavaScript + Fetch API、HTML5、CSS3 |
| 禁止擅自引入 | Composer 包、PHP 框架、数据库抽象库、模板引擎、Tailwind、Bootstrap、Vue、React、Alpine、jQuery、前端构建工具、UI 库 |
| PHP 兼容 | 不使用 typed properties、arrow functions、nullsafe、match、union types、named arguments、attributes、readonly、enum、mixed 类型声明、`str_contains`、`str_starts_with`、`str_ends_with` |