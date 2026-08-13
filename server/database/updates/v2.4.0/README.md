# v2.4.0 升级说明

## 概要

移动端配置层：租户可以为自己的 UniApp 配置启动首页、主题色、Logo、tabBar。

UniApp 在启动时通过 `GET /api/mobile/config` 拉取配置并应用 redirect / theme / tabBar。
本期不涉及多租户编译产物隔离（留给 v2.5.0 Phase C）。

## 改动

- **新增表 `tenant_mobile_configs`**：一对一存储租户的 app_name / app_logo / theme_color / home_app_code / home_page / tabbar_json / wechat_appid。
- **新增权限 `mobile.config.view` / `mobile.config.update`**。
- **租户后台新增菜单「移动端配置」**：见 init.sql 的同步条目；存量环境用 update.sql 的 SQL 写入。

## 顺序

1. `php think migrate:run`（自动建表）。
2. 或手工执行 `update.sql`。
3. 部署后通知租户后台用户：「系统」→「移动端配置」可填写。

## 回滚

```sql
DROP TABLE tenant_mobile_configs;
DELETE FROM permissions WHERE code IN ('mobile.config.view', 'mobile.config.update');
```
