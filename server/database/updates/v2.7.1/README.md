# v2.7.1 升级说明

## 摘要

Patch：修 v2.7.0 引入的 markSuccess 唯一索引冲突 + 补 v2.6.7 RBAC 遗漏的 plan 路由。

- **#1 markSuccess 冲突修复**：v2.7.0 的 `PluginMigrationLogRepository::markSuccess` 写入 success 前只清同方向 failed 行；唯一键 `(plugin_id, name, direction, status)` 在 up→down→up 重跑时必然冲突（`Duplicate entry` 422）。v2.7.1 改为「当前状态表」语义 —— 每条 migration 最多 1 个 success 行：写 success 前清掉 (plugin, name) 的所有旧 success 行 + 同 (plugin, name, direction) 的旧 failed 行。`successfulUpNames` 同步简化为单查询，去掉错误的 `array_diff`。无 schema 改动
- **#2 plan 路由 RBAC**：`server/app/platformapi/route/plan.php` 原本只挂 `platform_auth`，PlanController 6 个方法零 `#[Permission]`。套餐管理影响订阅 / 插件授权 / 商业权益，本补丁与 v2.6.7 处理 plugin 路由一致 —— 补 `platform_permission` + `platform_log` 中间件 + 给所有方法加 `#[Permission]` 注解 + 新增 3 个按钮权限 seed

## 升级步骤

### 1. 执行增量 SQL

```bash
mysql -u<user> -p<pass> <db_name> < server/database/updates/v2.7.1/update.sql
```

幂等可重跑（INSERT IGNORE）。

### 2. 重启 PHP / queue worker

让中间件链与新的 Repository 写入语义生效。

## 影响范围

- **#1 修复**：v2.7.0 已成功上线但还未触发过 up→down→up 循环的环境无任何观察到的行为变化；本补丁是预防性修复（未来某次 reinstall-from-disk 或开发重置时会触发）。已有 `plugin_migrations` 表数据不需要清理 —— `successfulUpNames` 简化查询对历史多行数据仍能正确返回 live up 名单
- **#2 RBAC**：
  - 超管：无影响
  - 非超管角色：升级后需要在「平台后台 → 角色管理」给套餐管理员重新勾选新增的 `新建套餐` / `更新套餐` / `删除套餐` 3 个按钮权限。原本能访问菜单的 `plan.view` 不受影响
  - 接口审计：所有 `/plans` 写操作（POST/PUT/DELETE）现在会写 platform_operation_logs

## 回滚方案

- checkout v2.7.0 标签
- updates SQL 留下的 3 个 seed 行可保留不删（不影响 v2.7.0 路由 —— 那时根本没读 `platform.plan.*`）
