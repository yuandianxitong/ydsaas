# v2.2.0 升级说明

## 主要变更

- 插件系统拆分为 App / Plugin（`plugins.kind` 字段）；App 安装时贡献顶级菜单
- Tenant 端布局新增 topnav 模式（顶部横排 + 左侧二级），默认启用
- Tenant 端「插件商城」改名「应用管理」，仅展示插件类型（plugin），App 通过安装机制贡献自己的顶级菜单
- 区域管理 / 应用版本 后端 + 前端从 tenant 端迁移到 platform 端
- 演示包：商城 App + 短信网关 Plugin（Phase H/I）

## 数据库结构变更

| 表 | 变更 |
|---|---|
| `plugins` | 新增 `kind VARCHAR(16) DEFAULT 'plugin'` 列 + `idx_kind` 索引 |
| `menus` | 新增 `code VARCHAR(80)` 列 + `uq_tenant_code(tenant_id, code)` 唯一索引 |
| `plugin_menus` | 新建表，关联 plugin_id → menu_id |
| `app_versions` | 删除 `tenant_id` 列及其相关复合索引，改为 `idx_platform_ver` + `idx_status` |

## 升级步骤

### 1. 备份数据库

```bash
mysqldump -uroot -p<password> dev007_framework_saas > backup_$(date +%Y%m%d).sql
```

### 2. 校验 app_versions.tenant_id 数据

运行以下 SQL，确认结果为 **0** 再继续：

```sql
SELECT COUNT(*) FROM app_versions WHERE tenant_id != 0;
```

如果结果不为 0，需要人工将非 0 租户的版本数据迁移处理后再执行升级脚本。

### 3. 执行 update.sql

```bash
mysql -uroot -p<password> dev007_framework_saas < server/database/updates/v2.2.0/update.sql
```

### 4. 重启服务

```bash
docker compose restart php nginx
```

## 回滚

如需回滚，使用步骤 1 的备份恢复：

```bash
mysql -uroot -p<password> dev007_framework_saas < backup_YYYYMMDD.sql
```

本版本对 `menus` / `plugins` / `app_versions` 表都有结构变更，不建议部分回滚。
