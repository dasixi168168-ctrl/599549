# 架构边界清单

> 本文件只记录真实项目的架构边界、职责归属和不可动点。不改变任何运行逻辑。

## 1. 当前边界总览

| 层级 | 文件/目录 | 当前职责 | 开发边界 |
|---|---|---|---|
| 启动 | `bootstrap/app.php` | 注册 `App\` 自动加载，加载 helper，创建 `Application` | 不改启动流程，不改自动加载规则 |
| 核心 | `app/Core` | Application、Database、Auth、Session、CSRF、View、Security、Validator | 不放宽登录、权限、CSRF、Session |
| 入口 | `public/*.php` | 前台、后台、API、客服、安装等旧 URL 入口 | 保持旧 URL，不拆入口 |
| 服务 | `app/Services` | 用户、后台、帖子、开奖预测、客服、上传、设置、统计、日志 | 优先只读归类，暂不大拆 |
| 模板 | `app/Views/front`、`app/Views/admin`、`app/Views/partials`、`app/Views/layouts` | 原生 PHP 模板渲染前台、后台和公共片段 | 不改业务条件渲染，UI 修复需双端检查 |
| API | `public/api.php` | POST 单入口，按 `action` 分发 | 保持旧 action、参数、返回字段、错误格式 |
| 数据库 | `database/schema.sql` | 真实表结构基线，共 49 张表 | 默认不改表，不删字段，不改字段含义 |
| 前台资源 | `public/styles/style.css`、`public/assets/app.js`、`public/assets/home-legacy.js` 等 | 前台布局、底部导航、客服、首页交互 | 小范围修复，避免全站覆盖 |
| 后台资源 | `public/assets/app.css`、`public/assets/app.js` | 后台布局、表格、弹窗、表单、客服轮询 | 不改权限和查询，只修可验证 UI |
| 运行数据 | `storage/*`、`public/uploads/*` | Session、cache、上传、截图、运行文件 | 不移动、不删除、不清空 |

## 2. 入口职责

| 入口 | 当前职责 | 地区/业务边界 | 不可破坏点 |
|---|---|---|---|
| `index.php` | 根入口跳转到 `/public/index.php` | 默认澳门首页 | 不改旧跳转 |
| `public/index.php` | 澳门首页 | 固定 `macau` | 不改为动态地区入口 |
| `public/record.php` | 香港首页 | 固定 `hongkong` | 不与澳门首页合并 |
| `public/forecast.php` | AI 预测页 | `macau/hongkong` | 不改变预测请求参数 |
| `public/history.php` | 历史开奖页 | `macau/hongkong` | 不混查两地开奖 |
| `public/member.php` | 登录、注册、会员中心 | `macau/hongkong` 影响返回路径 | 不改登录、注册、Session |
| `public/post.php` | 帖子详情 | 以帖子 `region` 为准 | 不破坏购买、回复、权限状态 |
| `public/service.php` | 会员客服、游客客服入口、客服坐席入口 | `macau/hongkong` 页面参数；客服会话按用户/坐席绑定 | 不改坐席登录跳转和会话绑定 |
| `public/admin.php` | 后台登录、页面分发、表单处理 | 后台统一入口，按权限和模块处理 | 不拆、不放宽权限 |
| `public/api.php` | API POST action 分发 | 前台、后台、客服、预测共用 | 不改旧 action 契约 |
| `public/install.php` | 在线安装 | 安装状态控制 | 不动安装和配置写入 |

## 3. Service 职责归属

| Service | 当前主要职责 | 风险等级 | 开发边界 |
|---|---|---|---|
| `AdminService.php` | 后台列表、权限种子、安装种子、帖子管理、开奖管理、首页配置、日志 | 高 | 先按函数清单归类，不直接拆 |
| `PostService.php` | 发帖、回帖、购买、浏览、互动、前台帖子查询 | 高 | 不改积分/购买/权限逻辑 |
| `SupportService.php` | 在线客服账号、会话、消息、坐席、未读、支付设置 | 高 | 先做状态矩阵，不改轮询 action |
| `PredictionService.php` | 澳门/香港开奖、预测、缓存、远程拉取、期数逻辑 | 高 | 不改外部接口和地区规则 |
| `UserService.php` | 会员登录、注册、资料、密码、头像、发帖权限 | 高 | 不改登录和 Session |
| `UploadService.php` | 上传路径、文件校验、上传记录 | 高 | 不移动旧文件，不放宽类型 |
| `SettingsService.php` | 站点设置读取/保存 | 中 | 只读梳理设置 key |
| `StatisticsService.php` | 统计、访问量 | 中 | 检查分页和索引，不做全表扫描改造 |
| `LogService.php` | 后台/系统日志 | 中 | 不输出敏感数据 |
| `CacheService.php` | 文件缓存 | 中 | 先记录缓存 key，不清空 |
| `InstallService.php` | 安装流程 | 高 | 不改 |

## 4. API 边界

| 分组 | 代表 action | 边界要求 |
|---|---|---|
| 认证 | `auth.login`、`auth.register`、`auth.logout`、`auth.admin_login` | 不改参数、Session、返回格式 |
| 会员资料 | `profile.update`、`profile.avatar`、`profile.password`、`profile.recovery` | 必须登录，保持 CSRF |
| 帖子 | `post.create`、`post.reply`、`post.buy`、`post.like`、`comment.like` | 不改积分、购买、回复权限 |
| 预测/开奖 | `draw.latest`、`forecast.generate`、`admin.draw.save` | 不混澳门/香港 |
| 会员客服 | `customer_service.member.*` | 不改会话绑定和未读规则 |
| 客服坐席 | `customer_service.agent.*` | 不改坐席登录、状态、屏蔽、关闭逻辑 |
| 后台客服 | `customer_service.admin.*` | 不放宽后台权限 |
| 后台管理 | `admin.*` | 保持权限校验、审计和返回结构 |

## 5. 模板和 UI 边界

| 区域 | 文件 | 当前结构 | UI 开发边界 |
|---|---|---|---|
| 前台布局 | `app/Views/layouts/home_legacy.php` | 加载 `style.css`、Font Awesome、`app.js`、按需 `home-legacy.js` | 不改资源加载策略，除非验证双端 |
| 后台布局 | `app/Views/layouts/admin.php` | 后台 shell、侧边栏、顶部账号、页面标题 | 后台手机端关键操作必须可用 |
| 顶部栏 | `app/Views/partials/front_top_bar.php` | 前台统一顶部栏，客服坐席可显示退出接待 | 坐席/普通用户状态结构要一致 |
| 底部导航 | `app/Views/partials/front_bottom_nav.php` | 澳门、香港、预测、客服、登录/我的/管理 | 未登录/已登录/坐席状态高度和徽章一致 |
| 客服页 | `app/Views/front/service.php` | guest/member/agent 多状态 | 优先建立状态矩阵后再改 |
| 帖子页 | `app/Views/front/post_detail.php` | 购买、回复、登录弹窗、预测内容 | 不为 UI 改购买逻辑 |
| 后台帖子 | `app/Views/admin/posts_forum.php` | 地区切换、发布、管理、生成器 | 不混 region，不改审核/锁定 |

## 6. CSS/JS 边界

| 文件 | 当前用途 | 风险 | 开发原则 |
|---|---|---|---|
| `public/styles/style.css` | 前台主样式，含首页、导航、客服、手机端补丁 | 高 | 每次只改一个组件或一个状态问题 |
| `public/assets/app.css` | 后台主样式，也含部分管理 UI 响应式补丁 | 高 | 不做全局格式化，不批量删除 `!important` |
| `public/assets/app.js` | 前后台通用 AJAX、toast、客服轮询、弹窗等 | 高 | 不改公共提交契约 |
| `public/assets/front-auth.js` | 前台登录注册相关 | 高 | 不改登录流程 |
| `public/assets/comment-thread.js` | 评论线程交互 | 中 | 不改回复 action |
| `public/assets/home-legacy.js` | 首页开奖、导航预取、弹窗等旧交互 | 高 | 不改地区和开奖时序 |

## 7. 数据边界

| 边界 | 当前基线 | 要求 |
|---|---|---|
| 地区 | `macau`、`hongkong`、部分配置支持 `all` | 查询、展示、写入都不能串区 |
| 业务 | 未见明确 `business_a/business_b` 命名；现有按论坛、开奖预测、客服、后台运营、上传模块隔离 | 未确认前不新增业务 A/B 命名 |
| 上传 | `uploads.business_type`、`public/uploads/*`、`storage/uploads` | 不移动旧文件，不改旧 URL |
| 权限 | 前台角色表和后台角色表分离 | 不合并权限体系 |
| 客服 | `customer_service_accounts`、`customer_service_sessions`、`customer_service_messages` | 不改会话唯一绑定 |

## 8. 推荐开发切入点

| 顺序 | 项目 | 最小动作 | 验证 |
|---|---|---|---|
| 1 | 客服状态矩阵 | 只读列出 guest/member/agent/admin 状态下的 DOM 和 class | 电脑 1366、手机 375/390/430，macau/hongkong |
| 2 | 底部导航状态一致性 | 检查未登录、已登录、坐席管理三种第五项结构 | 不改 API，不改登录 |
| 3 | 后台表格容器 | 检查后台列表是否撑破手机页面 | 只允许容器横向滚动 |
| 4 | API action 回归清单 | 生成手工测试清单，不调用写接口 | 对照旧返回格式 |
| 5 | 上传暴露面清单 | 只记录公开上传路径和引用位置 | 不移动、不删除 |

## 9. 每次修改前判断

| 检查项 | 结论要求 |
|---|---|
| 业务问题还是结构问题 | 先按现有代码判断 |
| 数据问题还是样式问题 | 不用样式掩盖业务问题 |
| 单状态还是跨状态一致性 | 同类位置合理同步 |
| 单端还是双端同步问题 | 电脑端和手机端同步检查 |
| 是否影响旧 URL/API | 默认不改，必须改时先说明影响 |
| 是否影响登录/权限/CSRF/Session | 默认不改，必须改时先说明影响 |
| 是否影响 macau/hongkong | 必须保持数据归属和开奖独立 |
| 是否影响上传/缓存/Session 文件 | 默认不动 |
| 是否可回滚 | 修改范围尽量小，可清晰回退 |

## 10. 本文件后续维护规则

1. 只记录架构边界，不写业务实现方案。
2. 新增重要模块时，同步补充边界信息。
3. 如果发现真实代码与本文件不一致，以真实代码为准，先修本文档。
4. 未经确认，不把高风险边界改成重构任务。
