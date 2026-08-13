-- v2.9.0 升级脚本（幂等可重跑）
-- 内容：license 状态机列 + token 轮换列 + 4 个权限点位
-- 依赖 Site v1.9.0（audit / token-rotate 端点）。DB 迁移本身独立可执行。

-- ============================================================
-- plugins: +4 列 + 1 索引（license 状态机 / read_safe_routes）
-- 采用 v2.8.0 同款 prepared-statement 幂等 ALTER 模式
-- ============================================================

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugins' AND COLUMN_NAME='license_status')=0,
  'ALTER TABLE `plugins` ADD COLUMN `license_status` VARCHAR(16) NOT NULL DEFAULT ''active'' COMMENT ''active/grace/expired，仅 distribution_source=marketplace 时有意义'' AFTER `update_available`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugins' AND COLUMN_NAME='license_grace_started_at')=0,
  'ALTER TABLE `plugins` ADD COLUMN `license_grace_started_at` DATETIME DEFAULT NULL COMMENT ''进入 grace 的时间，倒计时基准'' AFTER `license_status`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugins' AND COLUMN_NAME='license_last_check_at')=0,
  'ALTER TABLE `plugins` ADD COLUMN `license_last_check_at` DATETIME DEFAULT NULL COMMENT ''RuntimeLicenseEvaluator 上次评估时间'' AFTER `license_grace_started_at`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugins' AND COLUMN_NAME='read_safe_routes')=0,
  'ALTER TABLE `plugins` ADD COLUMN `read_safe_routes` JSON DEFAULT NULL COMMENT ''JSON 数组：plugin.json 同步的只读豁免路由 pattern'' AFTER `license_last_check_at`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- v2.9.0 复合索引 (distribution_source, license_status) for cron LicenseStateMachine 查询
-- 既有 idx_distribution 保留（B-2 兼容），新建 composite 满足 leftmost-prefix
SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugins' AND INDEX_NAME='idx_distribution_license')=0,
  'ALTER TABLE `plugins` ADD INDEX `idx_distribution_license` (`distribution_source`, `license_status`)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================
-- marketplace_connections: +2 列（token 轮换元数据）
-- ============================================================

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='marketplace_connections' AND COLUMN_NAME='token_rotated_at')=0,
  'ALTER TABLE `marketplace_connections` ADD COLUMN `token_rotated_at` DATETIME DEFAULT NULL COMMENT ''token 上次轮换时间，绑定时初始化为 created_at'' AFTER `last_error`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='marketplace_connections' AND COLUMN_NAME='token_expires_at')=0,
  'ALTER TABLE `marketplace_connections` ADD COLUMN `token_expires_at` DATETIME DEFAULT NULL COMMENT ''Site 返回的 token 名义过期时间'' AFTER `token_rotated_at`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 老 marketplace_connections 行 backfill 用 created_at 作为 token 轮换基准（created_at NOT NULL，无需 COALESCE 兜底）
UPDATE `marketplace_connections`
   SET `token_rotated_at` = `created_at`
 WHERE `token_rotated_at` IS NULL;

-- ============================================================
-- 4 个新权限点位（INSERT IGNORE，依赖 uk_name 幂等）
-- ============================================================
INSERT IGNORE INTO `permissions` (`tenant_id`, `name`, `title`, `group`, `description`, `guard_name`, `status`, `sort`, `created_at`, `updated_at`)
VALUES
  (0, 'marketplace.license.view',              '查看应用授权状态',     '平台管理', '查看官方市场应用的 license 状态/grace 倒计时', 'admin', 1, 4, NOW(), NOW()),
  (0, 'marketplace.license.manual_renew',      '手动刷新应用授权状态', '平台管理', '手动触发 RuntimeLicenseEvaluator 重新评估单个应用', 'admin', 1, 5, NOW(), NOW()),
  (0, 'marketplace.connection.rotate_token',   '手动轮换 Site 连接 token', '平台管理', '手动轮换 marketplace 连接的 instance token', 'admin', 1, 6, NOW(), NOW()),
  (0, 'marketplace.audit.toggle',              '切换应用审计上报开关', '平台管理', '开启/关闭官方市场审计事件上报到 Site', 'admin', 1, 7, NOW(), NOW());
