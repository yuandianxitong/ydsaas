# framework-saas v2.9.0 升级说明

## 数据库变更

### `plugins` 表
- 新增 4 列：
  - `license_status` VARCHAR(16) NOT NULL DEFAULT 'active' — active/grace/expired，仅 distribution_source=marketplace 时有意义
  - `license_grace_started_at` DATETIME — 进入 grace 的时间，倒计时基准
  - `license_last_check_at` DATETIME — RuntimeLicenseEvaluator 上次评估时间
  - `read_safe_routes` JSON — 数组，从 plugin.json 同步的只读豁免路由 pattern
- 新增 composite index `idx_distribution_license` (`distribution_source`, `license_status`)

### `marketplace_connections` 表
- 新增 2 列：
  - `token_rotated_at` DATETIME — token 上次轮换时间，绑定时初始化为 created_at
  - `token_expires_at` DATETIME — Site 返回的 token 名义过期时间
- 老行 backfill：`token_rotated_at` 设为 `COALESCE(created_at, NOW())`

### `permissions` 表
- 新增 4 个权限点位（tenant_id=0）：
  - `marketplace.license.view` — 查看应用授权状态
  - `marketplace.license.manual_renew` — 手动刷新应用授权状态
  - `marketplace.connection.rotate_token` — 手动轮换 Site 连接 token
  - `marketplace.audit.toggle` — 切换应用审计上报开关

### 执行
```bash
mysql -u root -p <db> < server/database/updates/v2.9.0/update.sql
```

脚本幂等可重跑（与 v2.8.0 同款 prepared-statement 模式 + `INSERT IGNORE`）。

## 必须新增的 cron（Task 15 + Task 22 上线后启用）

```cron
# 每 6 小时评估一次 license 状态（grace/expired 推进）
0 */6 * * * cd /path/to/server && php think saas:license-evaluate

# 每日凌晨 3 点检查并轮换临近过期的 instance token
0 3   * * * cd /path/to/server && php think saas:marketplace-token-rotate
```

## 可选新增的 env（默认值已合理，可不配置）

```ini
[SAAS]
MARKETPLACE_GRACE_DAYS=14
MARKETPLACE_SYNC_HOURS=1
MARKETPLACE_LICENSE_EVAL_HOURS=6
MARKETPLACE_AUDIT_ENABLED=true
```

## 依赖

- **Site ≥ v1.9.0**（必须先发布并部署到 site.yuandianxitong.com）
  - `POST /api/open/instances/tokens/rotate` — Task 22 token rotation 依赖
  - `POST /api/open/instances/audit` — Task 24 audit client 依赖

## 回滚

本次仅追加列、追加索引、追加权限，不会修改既有数据语义。如需回滚：

```sql
ALTER TABLE plugins
  DROP INDEX idx_distribution_license,
  DROP COLUMN license_status,
  DROP COLUMN license_grace_started_at,
  DROP COLUMN license_last_check_at,
  DROP COLUMN read_safe_routes;

ALTER TABLE marketplace_connections
  DROP COLUMN token_rotated_at,
  DROP COLUMN token_expires_at;

DELETE FROM permissions
 WHERE tenant_id = 0
   AND name IN (
     'marketplace.license.view',
     'marketplace.license.manual_renew',
     'marketplace.connection.rotate_token',
     'marketplace.audit.toggle'
   );
```
