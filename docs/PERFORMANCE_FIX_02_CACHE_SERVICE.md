# 第二轮低风险性能修复：CacheService 缓存防 Fatal 保护

## 修复原因

排查发现 storage/cache/settings_all.cache.php 曾经出现缺失、内容不完整或 parse error，CacheService.php 直接 require 缓存文件，导致 PHP Fatal / Parse error，进而造成页面卡慢、日志刷屏或偶发异常。

## 本次处理

- CacheService::get() 增加缓存文件存在性、可读性、内容格式检查。
- CacheService::get() 在缓存不可用时返回默认值，避免 Fatal。
- CacheService::put() 改为先写临时文件，再 rename 为正式缓存文件，避免半写入文件被读取。
- 不删除缓存文件。
- 不清空缓存目录。
- 不改变业务逻辑。

## 本次没有修改

- 没有修改数据库
- 没有修改接口
- 没有修改权限
- 没有修改登录
- 没有修改开奖逻辑
- 没有修改 CSS
- 没有修改 JS
- 没有修改页面结构
- 没有修改 crontab
- 没有修改 PHP 版本
- 没有重启服务
- 没有清空日志
- 没有清空缓存

## 验证方式

- 使用 PHP 8.0 检查 CacheService.php 语法。
- 检查 Git diff 是否只涉及允许文件。
- 访问首页或相关页面后观察 error.log 是否继续出现 settings_all.cache.php Fatal。
- 确认没有修改业务功能。
