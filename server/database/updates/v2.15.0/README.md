# v2.15.0 升级说明

## 装修：自定义页面

`diy_pages` 新增 `page_key` slug 列，唯一键由 `(tenant_id,page_type,platform)` 改为 `(tenant_id,page_key,platform)`，以支持每租户多张自定义页面。存量 home 行 `page_key` 自动回填为 `home`。

## 升级步骤

1. 执行 `update.sql`（或 `php think migrate:run`）。
2. 对齐存量租户菜单与权限：`php think saas:decorate-menu-reconcile`（幂等，新增「自定义页面」子菜单 + `diy.page.*` 权限）。

无数据丢失风险；回滚见迁移 `down()`。
