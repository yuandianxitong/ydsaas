# v2.8.0 升级说明

## 必做

1. **生成密钥**：`openssl rand -hex 32` → 写入 `.env` 的 `[SAAS]` 段：
   ```
   [SAAS]
   MARKETPLACE_ENCRYPTION_KEY = <64 字符 hex>
   ```
   该密钥用于 AES-256-GCM 加解密 marketplace 连接的 Site instance_token。**丢失等同于丢失所有连接**，必须重新走 OAuth 绑定。

2. **加 cron**：
   ```
   0 * * * * cd /path/to/saas/server && php think saas:marketplace-sync
   ```
   每小时同步 1 次官方市场已购应用清单 + 心跳。

3. **跑数据库升级**：
   ```bash
   mysql -u root -p <db> < server/database/updates/v2.8.0/update.sql
   ```
   幂等可重跑。

## 新增

- 3 张表：`marketplace_connections` / `marketplace_app_cache` / `marketplace_public_keys`
- `plugins` 表 +8 列（`distribution_source` / `remote_app_id` / `remote_version_id` / `publisher_name` / `installed_hash` / `signature_status` / `latest_version` / `update_available`）
- 4 个权限点位
- 1 个控制台命令 `saas:marketplace-sync`
- 平台后台「插件管理」页新增「官方市场」Tab

## 依赖

需 Site v1.8.0+ 上线（提供 OAuth-PKCE 兑换端点）。
