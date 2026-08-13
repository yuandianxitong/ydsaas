# v2.6.7 升级说明

## 摘要

- **安全修复**：平台插件管理 3 个路由（`/plugins`、`/plugin-builds`、`/plans/:planId/plugin-grants`）原本只挂了 `platform_auth`，缺 `platform_permission` 与 `platform_log` → 任何登录平台用户都能调用 upload/install/uninstall/upgrade/purge/grant.sync/build.rebuild 等高危接口绕过 RBAC，且不写操作日志。v2.6.7 全部补齐
- **数据正确性修复**：软卸载 → purge 流程的迁移回滚原本因目录被先删而跳过 → 「清理数据」实际没删表。新增 `runtime/plugin-graveyard/<pluginId>/` 快照机制，purge 时优先 `plugins/<code>/`、否则回退到 graveyard 跑 `migrator.down()`

## 升级步骤

1. **执行增量 SQL**（补 seed 漏掉的两个按钮权限）：

   ```bash
   mysql -u<user> -p<pass> <db_name> < server/database/updates/v2.6.7/update.sql
   ```

   或在 PMA / DBeaver 直接粘贴 `update.sql` 内容执行。幂等，多次执行无副作用。

2. **重启 PHP / queue worker**（让中间件链生效）。

## 影响范围

- 非超管平台账号：原本可越权调插件高危接口的非超管账号，升级后会被 `platform_permission` 中间件按 `#[Permission('platform.plugin.*')]` 注解校验。如果你给了「应用管理」菜单访问权但未单独勾选按钮权限，需要去 **平台后台 → 角色管理** 给对应角色重新勾选 247/248 这两个新按钮权限
- 超管账号：无影响（超管在 `PlatformPermissionMiddleware` 始终跳过 RBAC）
- 已软卸载、未 purge 的存量插件：graveyard 不存在 → purge 时会跳过 `migrator.down()`（与旧行为一致，无回归）；新执行的软卸载会创建 graveyard，purge 正确删表
- `runtime/plugin-graveyard/` 是新目录，已被 `server/runtime/` 默认 gitignore 覆盖
