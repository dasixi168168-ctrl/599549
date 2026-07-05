# AI 自动纠偏业务基线

> 本文件记录只读扫描得到的真实项目基线，用于后续每次纠偏前对照。不得把示例目录、示例 schema 当成真实结构。

## 技术栈

| 项目 | 判断 |
|---|---|
| 后端 | PHP 自定义站点 |
| 启动 | `bootstrap/app.php` 注册 `App\` 自动加载 |
| 核心代码 | `app/Core` |
| 服务层 | `app/Services` |
| 模板 | `app/Views` |
| 数据库 | MySQL/MariaDB，结构文件为 `database/schema.sql` |
| 前端资源 | `public/assets`、`public/styles` |
| 定时任务 | `cli/run_post_generator_schedule.php` |

## 入口结构

| 类型 | 路径 | 说明 |
|---|---|---|
| 根入口 | `index.php` | 302 跳转到 `/public/index.php` |
| 静态入口 | `index.html` | 根目录静态页，需确认服务器优先级 |
| 澳门首页 | `public/index.php` | 固定 `region=macau` |
| 香港首页 | `public/record.php` | 固定 `region=hongkong` |
| 预测页 | `public/forecast.php` | 支持 `region=macau/hongkong` |
| 历史开奖 | `public/history.php` | 支持 `region=macau/hongkong` |
| 会员中心 | `public/member.php` | 支持 `region=macau/hongkong` |
| 帖子详情 | `public/post.php` | 按帖子 `region` 回到对应首页 |
| 在线客服 | `public/service.php` | 支持会员、坐席和地区参数 |
| 后台 | `public/admin.php` | `page` 参数分发后台模块 |
| API | `public/api.php` | 仅 POST，`action` 参数分发 |
| 安装 | `public/install.php` | 安装入口，暂不动 |

## 后台页面

| 模块 | 权限 |
|---|---|
| 仪表盘 | `dashboard.view` |
| 管理员管理 | `admins.view` |
| 角色权限 | `roles.view` |
| 系统设置 | `settings.view` |
| 在线客服 | `customer_service.view` |
| 当前安装快照 | `install.view` |
| 资料更新 | `uploads.view` |
| 会员管理 | `users.view` |
| 帖子管理 | `posts.view` |
| 板块管理 | `sections.view` |
| 分类管理 | `categories.view` |
| 评论管理 | `comments.view` |
| 帖子互动 | `interactions.view` |
| 帖子举报 | `reports.view` |
| 审核管理 | `audits.view` |
| 开奖管理 | `draws.view` |
| AI预测设置 | `home.view` |
| 期数管理 | `issues.view` |
| 登录日志 | `login_logs.view` |
| 操作日志 | `operation_logs.view` |
| 异常日志 | `exceptions.view` |
| 安全策略 | `security.view` |

## API action 分组

| 分组 | action |
|---|---|
| 认证 | `auth.captcha`、`auth.login`、`auth.admin_login`、`auth.register`、`auth.logout` |
| 找回密码 | `password_reset.verify_reset`、`password_reset.reset` |
| 会员资料 | `profile.update`、`profile.avatar`、`profile.password`、`profile.recovery` |
| 开奖/预测 | `draw.latest`、`forecast.generate`、`prediction.logs.delete`、`admin.predict.generate`、`admin.draw.save` |
| 帖子 | `post.create`、`post.reply`、`post.view_count`、`post.buy`、`post.customer_service.update`、`post.like`、`post.favorite`、`post.follow`、`comment.like` |
| 会员客服 | `customer_service.member.poll`、`customer_service.member.unread`、`customer_service.member.send`、`customer_service.member.clear`、`customer_service.member.payment_settings`、`customer_service.typing` |
| 客服坐席 | `customer_service.agent.login`、`customer_service.agent.logout`、`customer_service.agent.presence`、`customer_service.agent.settings`、`customer_service.agent.nickname_delete`、`customer_service.agent.payment_settings`、`customer_service.agent.poll`、`customer_service.agent.send`、`customer_service.agent.score`、`customer_service.agent.clear`、`customer_service.agent.queue_delete`、`customer_service.agent.block`、`customer_service.agent.unblock`、`customer_service.agent.close` |
| 后台 | `admin.settings.save`、`admin.admin.save`、`admin.admin.toggle`、`admin.role.save`、`admin.post.save`、`admin.post.bulk`、`admin.post_lock.save`、`admin.post_like_increment.save`、`admin.post_view_display.save`、`admin.post_sale_buyer_increment.save`、`admin.post.quick`、`admin.post.categories`、`admin.user.save`、`admin.user.score`、`admin.reset.process`、`admin.cache.clear` |
| 后台客服 | `customer_service.admin.poll`、`customer_service.admin.unread`、`customer_service.admin.send`、`customer_service.admin.clear`、`customer_service.admin.close`、`customer_service.agent.save`、`customer_service.agent.delete` |

## 数据表分组

| 分组 | 表 |
|---|---|
| 前台用户/权限 | `roles`、`permissions`、`role_permissions`、`users`、`login_logs`、`password_reset_requests`、`user_ban_records`、`user_vips` |
| 上传 | `uploads` |
| 帖子/社区 | `posts`、`replies`、`comment_likes`、`purchases`、`post_manage_meta`、`post_unique_views`、`post_view_display_events`、`forum_sections`、`forum_categories`、`audit_records`、`post_interactions`、`post_reports` |
| 开奖/AI预测 | `lottery_draws`、`ai_predictions`、`ai_prediction_participations`、`lottery_issues` |
| 首页/运营 | `settings`、`notices`、`home_banners`、`ad_slots`、`recommend_positions`、`home_nav_entries`、`home_module_configs` |
| 日志/统计 | `admin_logs`、`system_logs`、`page_views`、`system_exception_logs`、`admin_login_logs`、`admin_operation_logs` |
| 后台权限 | `admin_roles`、`admin_permissions`、`admin_role_permissions`、`admin_menus`、`admin_role_menus`、`admin_users` |
| 在线客服 | `customer_service_accounts`、`customer_service_sessions`、`customer_service_messages` |
| 安装初始化 | `install_records`、`init_data_records` |

## 地区和业务边界

| 项目 | 当前基线 |
|---|---|
| 澳门/香港字段 | 多张表使用 `region`，值主要为 `macau`、`hongkong`、`all` |
| 澳门首页 | `public/index.php` 固定澳门 |
| 香港首页 | `public/record.php` 固定香港 |
| 双地区页面 | `forecast.php`、`history.php`、`member.php`、`service.php` |
| 业务 A/B | 未发现明确 `business_a/business_b` 命名；当前更像论坛、开奖预测、客服、后台运营等模块边界 |
| 业务字段 | `uploads.business_type` 已存在，其他业务边界需继续按表和服务确认 |

