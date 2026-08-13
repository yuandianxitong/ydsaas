# v2.27.1 数据库升级说明

本版本包含五部分菜单/权限变更。

## 1. 修复移动端打包发布的 RBAC 授权断链

- `MobileBuildController` 接口要求 `mobile.build.view` / `mobile.build.create` / `mobile.build.release` 权限，但菜单模板从未种入对应节点，非超管角色即使被授予「打包发布」菜单也会在调用打包接口时收到 403（超管因 `isSuperAdmin()` 短路不受影响，掩盖了该问题）。
- 新增租户后台权限 `mobile.build.view` / `mobile.build.create` / `mobile.build.release`。
- 在「装修 / 发布管理 / 打包发布」菜单下新增三个按钮权限节点（查看构建 / 发起构建 / 发布应用），并复制到全部存量租户。

## 2. 平台端「开发工具 → 移动构建监控」菜单

- `/platformapi/mobile-builds` 接口此前后端完整但无前端页面，本版本补齐页面并在 `platform_menus` 新增菜单（`platform.mobile.build.view`）与「强制收尾卡死任务」按钮权限（`platform.mobile.build.manage`）。

## 3. 租户 CRUD 权限拆分（⚠️ 行为变更）

- 此前平台端租户的新建/编辑/删除/重置管理员密码全部共用 `tenant.view`，「只读租户」角色实际可删租户。
- 现拆分为 `platform.tenant.create` / `platform.tenant.update` / `platform.tenant.delete` / `platform.tenant.reset_password` 四个按钮权限（挂「租户管理」下）。
- **升级后仅持有 `tenant.view` 的非超管角色将失去租户写能力**，需在「角色管理」中按需勾选新按钮权限（超管不受影响）。

## 4. 平台级插件禁用/启用按钮权限

- 新增 `platform.plugin.status`（挂「应用管理」下），对应新增的 `POST /platformapi/plugins/:id/disable|enable` 临时禁用/启用能力（保留代码与数据，区别于软卸载）。

## 5. 官方市场权限修复与清理

- 此前整个 marketplace 权限家族只 seed 在不参与鉴权的遗留 `permissions` 表，**非超管平台管理员无法被授权使用应用市场**。现将 5 个控制器实际使用的权限码（`marketplace.connection.view/manage`、`marketplace.catalog.view`、`marketplace.install`、`marketplace.connection.rotate_token`）补进 `platform_menus`（挂「应用管理」下）。
- 删除三个从未实现的预埋点位 `marketplace.license.view` / `marketplace.license.manual_renew` / `marketplace.audit.toggle`（含各租户副本）：license 状态已随应用目录返回；「同步」操作已触发 license 重评估；审计上报开关走配置文件 `saas.marketplace.audit_report_enabled`。

升级方式：在备份数据库后执行 `update.sql`（幂等，可重复执行）。

注意：升级只补齐菜单/权限节点，不会自动授予任何角色。存量租户需要在「角色管理」中为相应角色勾选新增的按钮权限后，非超管角色才能使用打包功能；平台端同理需为角色勾选「移动构建监控」菜单。
