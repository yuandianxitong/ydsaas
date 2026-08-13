# v2.7.0 升级说明

## 摘要

v2.7.0 是一个 **minor**，引入两块可靠性增强：

1. **插件 migration 状态表**（`plugin_migrations`）
   - PluginMigrator 改为状态感知：跑过的不再重跑、失败行可重试、文件 hash 漂移可检测、完整审计历史
   - 新增 console 命令 `php think saas:plugin-migration-backfill` 回填存量

2. **插件安装 saga 补偿**
   - PluginService::install 重构为 6 步 `CompensatableStep` 链 + `SagaRunner`
   - 任一 step 失败 → 已完成 step 按倒序触发 compensate，把副作用清干净（菜单/迁移/目录）
   - 补偿失败仅记日志 + 写 `plugins.last_error`，best-effort 不阻断

## 升级步骤

### 1. 执行增量 SQL

```bash
mysql -u<user> -p<pass> <db_name> < server/database/updates/v2.7.0/update.sql
```

或在 PMA / DBeaver 直接粘贴 `update.sql` 内容执行（幂等，可重跑）。

### 2. ⭐ 必跑：回填存量插件 migration 状态

```bash
php think saas:plugin-migration-backfill
```

**这一步是强制的**。原因：

- v2.7.0 后 `PluginMigrator::up()` 会查 `plugin_migrations` 表判断哪些 migration 已跑
- 升级前的存量插件虽然已 ENABLED，但状态表是空的 → PluginMigrator 会认为「全部 pending」→ 重跑 `CREATE TABLE` 直接 SQL 报错
- backfill 命令把这些插件的 migration 文件登记为 `success up`，且不真跑 SQL

可选参数：

```bash
php think saas:plugin-migration-backfill --plugin=42        # 只回填 plugin_id=42
php think saas:plugin-migration-backfill --dry-run          # 只打印不落库
```

### 3. 重启 PHP / queue worker

让新的 saga 编排 + 状态表逻辑生效。

## 配置项

可选 `.env` 配置：

```ini
[SAAS]
# 默认 false：migration 文件 hash 漂移仅记 error_log
# 设为 true：hash 漂移直接 422 拒绝继续 up/upgrade（生产环境推荐）
PLUGIN_MIGRATION_STRICT_HASH = false
```

## 影响范围

- **现有已 ENABLED 插件**：必须跑 backfill；未跑的话下次 install/upgrade 触发 `migrator->up()` 会重跑全部 → 表已存在直接 SQL 错。报错本身是保护，不会损坏数据
- **新装插件**：完全走新流程，每条 migration 写状态表
- **uninstall→purge 流程**：v2.6.7 引入的 graveyard 机制仍然有效；purge 会按状态表里有 success up 的 migrations 倒序 down，完成后 `DELETE FROM plugin_migrations WHERE plugin_id=?` 清理历史
- **install 失败**：原先只清目录 + 标 FAILED；现在按倒序 compensate（删菜单、down 表、删目录），副作用清干净
- **lifecycle.uninstall 未实现**：lifecycle.install 失败时框架尝试调 uninstall 补偿；插件没实现会静默跳过，不阻断其它 step 补偿

## 回滚方案

1. checkout v2.6.7 标签
2. `DROP TABLE plugin_migrations`（如想彻底回到 v2.6.x 状态）

回滚不需要逆向 backfill —— v2.6.x 不读状态表，存量插件不受影响。
