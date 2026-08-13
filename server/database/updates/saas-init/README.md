# 存量单租户 → SaaS 多租户升级指南

本目录包含将已有单租户数据库**原地升级**为 SaaS 多租户版本的 SQL 脚本。

> 适用版本：v1.5.0 / v1.5.1 单租户版本
> 如果你是全新安装，请直接使用 `server/public/install/data/schema.sql` + `server/public/install/data/init.sql`，无需执行本脚本。

---

## 升级内容概览

| 变更类型 | 说明 |
|---|---|
| 新增 `tenants` 表 | SaaS 租户主表（如已存在则跳过） |
| 31 张 A 类表加 `tenant_id` 列 | 默认值 `1`，现有数据自动归属租户 1 |
| 13 组唯一索引重建 | 原单列/多列唯一约束升级为含 `tenant_id` 的复合唯一索引 |
| 32 个 `tenant_id` 普通索引 | 提升多租户查询性能 |
| `regions` 表 **不改动** | 地区数据为全局公共数据，无需隔离 |

升级后，现有所有数据属于 **tenant_id = 1**（默认租户）。可在管理后台继续新建租户。

---

## 前置条件

- MySQL ≥ 5.7 / MariaDB ≥ 10.3
- 当前数据库版本为 ydadmin **v1.5.x**（未曾运行过此脚本）
- 拥有 `ALTER TABLE`、`CREATE TABLE`、`INSERT` 权限的数据库账号

---

## 升级步骤

### 第一步：备份（必须！）

```bash
# 替换 <DB_HOST>、<DB_PORT>、<DB_USER>、<DB_NAME> 为实际值
mysqldump -h <DB_HOST> -P <DB_PORT> -u <DB_USER> -p \
  --single-transaction --routines --triggers \
  <DB_NAME> > backup_before_saas_$(date +%Y%m%d_%H%M%S).sql
```

确认备份文件大小非零后再继续。

### 第二步：停服（推荐）

升级期间建议将后端服务停止，避免半升级状态下产生脏数据：

```bash
# 示例（根据实际部署方式调整）
php artisan down
# 或 pm2 stop ydadmin-api
```

### 第三步：执行升级脚本

```bash
mysql -h <DB_HOST> -P <DB_PORT> -u <DB_USER> -p <DB_NAME> \
  < /path/to/server/database/updates/saas-init/update.sql
```

若脚本执行中途报错，请：
1. 查看报错信息，确认是否为版本不符（如列已存在、索引已存在）
2. 参考下方「常见问题」排查
3. 如无法继续，从备份恢复后再次排查

### 第四步：升级代码

将 ydadmin-SaaS 代码部署到服务器，并执行：

```bash
composer install --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 第五步：验证

```bash
# 确认 tenants 表存在且有 1 条记录
mysql -u <DB_USER> -p <DB_NAME> -e "SELECT id, name, code FROM tenants;"

# 抽查 admins 表的 tenant_id 列
mysql -u <DB_USER> -p <DB_NAME> -e \
  "SELECT id, username, tenant_id FROM admins LIMIT 5;"

# 确认复合唯一索引已重建（以 admins 为例）
mysql -u <DB_USER> -p <DB_NAME> -e \
  "SHOW INDEX FROM admins WHERE Key_name LIKE '%tenant%';"
```

### 第六步：重启服务

```bash
php artisan up
# 或 pm2 start ydadmin-api
```

---

## 回滚方案

如果升级失败需要回滚，从第一步的备份恢复：

```bash
mysql -h <DB_HOST> -P <DB_PORT> -u <DB_USER> -p <DB_NAME> \
  < backup_before_saas_<timestamp>.sql
```

> ⚠️ 回滚会将数据库完全还原到升级前状态，升级后写入的数据**将丢失**。请在停服状态下回滚。

---

## 常见问题

### `Duplicate column name 'tenant_id'`

说明该表的 `tenant_id` 列已存在（可能之前手动添加或脚本部分执行过）。
可以手动跳过对应的 `ALTER TABLE ... ADD COLUMN` 语句，继续执行后续部分。

### `Can't DROP 'xxx'; check that column/key exists`

说明该索引已被删除或名称不同。请用以下命令查看实际索引名：

```sql
SHOW INDEX FROM <table_name>;
```

然后将 `update.sql` 中对应的 `DROP INDEX` 语句改为实际名称后重试。

### `Table 'tenants' already exists`

脚本使用 `CREATE TABLE IF NOT EXISTS`，不会报错；`INSERT ... ON DUPLICATE KEY UPDATE` 也是幂等的，可安全重跑。

### 升级后无法登录后台

请检查：
1. 代码是否已部署 SaaS 版本
2. `.env` 中 `APP_KEY`、数据库连接是否正确
3. 执行 `php artisan config:clear && php artisan cache:clear`

---

## 变更的索引清单

以下是本脚本重建的全部唯一索引（索引名来自 ydadmin v1.5.x 实际 schema）：

| 表名 | 原索引名 | 原列 | 新索引名 | 新列 |
|---|---|---|---|---|
| `admins` | `admins_username_unique` | `username` | `admins_tenant_username_unique` | `tenant_id, username` |
| `admins` | `admins_email_unique` | `email` | `admins_tenant_email_unique` | `tenant_id, email` |
| `departments` | `uk_code` | `code` | `departments_tenant_code_unique` | `tenant_id, code` |
| `roles` | `roles_name_unique` | `name` | `roles_tenant_name_unique` | `tenant_id, name` |
| `permissions` | `permissions_name_unique` | `name` | `permissions_tenant_name_unique` | `tenant_id, name` |
| `users` | `uk_mobile` | `mobile` | `users_tenant_mobile_unique` | `tenant_id, mobile` |
| `user_notification_reads` | `idx_notification_user_unique` | `notification_id, user_id` | `unr_tenant_notification_user_unique` | `tenant_id, notification_id, user_id` |
| `dictionaries` | `uk_code` | `code` | `dictionaries_tenant_code_unique` | `tenant_id, code` |
| `dictionary_items` | `uk_dict_value` | `dictionary_id, value` | `dictionary_items_tenant_dict_value_unique` | `tenant_id, dictionary_id, value` |
| `system_configs` | `system_configs_key_unique` | `config_key` | `system_configs_tenant_key_unique` | `tenant_id, config_key` |
| `message_templates` | `uk_code` | `code` | `message_templates_tenant_code_unique` | `tenant_id, code` |
| `notification_reads` | `uk_notification_admin` | `notification_id, admin_id` | `notification_reads_tenant_notif_admin_unique` | `tenant_id, notification_id, admin_id` |
| `payment_orders` | `uk_order_no` | `order_no` | `payment_orders_tenant_order_no_unique` | `tenant_id, order_no` |
| `agreements` | `uk_code` | `code` | `agreements_tenant_code_unique` | `tenant_id, code` |

---

## 不受影响的表

以下表**无唯一约束需要重建**，仅加了 `tenant_id` 列和普通索引：

`admin_roles`, `admin_login_logs`, `admin_operation_logs`, `role_menus`, `role_permissions`, `menus`, `user_notifications`, `files`, `cron_jobs`, `cron_job_logs`, `message_logs`, `wechat_auto_replies`, `notifications`, `balance_logs`, `points_logs`, `feedbacks`, `announcements`, `app_versions`, `data_imports`

---

## 技术支持

如遇到升级问题，请提交 Issue 并附上：
- ydadmin 原始版本号（`git log --oneline -1`）
- MySQL/MariaDB 版本（`SELECT VERSION();`）
- 完整错误信息
