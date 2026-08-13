# v2.7.2 升级说明

## 摘要

Patch：根因修平台 RBAC 隐性漏权 + 修订 v2.6.7/v2.7.1 update.sql 写错的目标表。

- **#1 `PlatformAdmin::getPermissions()` 含 type=2**：原实现只查 `type=3` 按钮，但 20 个 type=2 菜单行的 `permission` 字段（`plan.view` / `platform.plugin.list` / `platform.refund.list` / `platform.config.list` 等）都是真实接口权限，对应 Controllers 的 `#[Permission]` 注解。非超管角色即使分到对应菜单，调对应 list/view 接口仍 403。1 行代码修复，覆盖 plugin / plan / refund / config / announcement / generator / cron / file / dictionary / permission / admin / role / menu / audit / log / api_doc 全部平台模块
- **#2 v2.6.7 + v2.7.1 update.sql 表名修订**：两份历史 SQL 误把 `platform_menus`（平台菜单）写成 `menus`（租户菜单）+ 用了错字段名（`permission_code` / `tenant_id`）。MySQL 直接报「Unknown column」拒绝执行 → 没有用户数据被污染。本补丁在原文件 in-place 修正，并在 v2.7.2/update.sql 一次性 INSERT IGNORE 补齐两批漏 seed

## 升级步骤

### 1. 执行增量 SQL

```bash
mysql -u<user> -p<pass> <db_name> < server/database/updates/v2.7.2/update.sql
```

幂等可重跑（INSERT IGNORE）。已经成功跑过修订后的 v2.6.7/v2.7.1 SQL 的用户重跑无副作用。

> 历史背景：如果你在 v2.6.7 或 v2.7.1 升级时跑过那时的 update.sql，会看到 MySQL 报错（Unknown column）—— 这是好事，意味着错的 SQL 一行都没插。v2.7.2 这次 SQL 把那两批漏掉的 seed 行补齐。

### 2. 重启 PHP

让 `getPermissions()` 改动生效。

## 影响范围

- **超管**：无影响（始终 `*`）
- **非超管**：原本被「getPermissions 只查 type=3」隐性卡住的平台接口，升级后能按角色菜单分配正常访问。这是**恢复了被 bug 错误拒绝的合法访问**，不是放权
- **审计**：所有 type=2 菜单 perm 现在出现在 `getPermissions()` 列表里。若你之前依赖「我给了 X 菜单但 X 接口仍 403」的隐式行为，需要重新审视角色定义

## 回滚方案

- checkout v2.7.1 标签 → 代码层回滚
- 不需要 SQL 反向 —— v2.7.2 仅补漏 seed，回滚后旧代码不读这些 seed
