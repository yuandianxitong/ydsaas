-- v2.8.0 升级脚本（幂等可重跑）

CREATE TABLE IF NOT EXISTS `marketplace_connections` (
  `id`                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `instance_uuid`            CHAR(36)     NOT NULL,
  `instance_name`            VARCHAR(120) NOT NULL,
  `site_base_url`            VARCHAR(200) NOT NULL,
  `encrypted_instance_token` TEXT         NOT NULL,
  `token_prefix`             VARCHAR(16)  NOT NULL,
  `bound_user_email`         VARCHAR(120) DEFAULT NULL,
  `bound_user_name`          VARCHAR(60)  DEFAULT NULL,
  `status`                   VARCHAR(16)  NOT NULL DEFAULT 'active',
  `last_heartbeat_at`        DATETIME     DEFAULT NULL,
  `last_sync_at`             DATETIME     DEFAULT NULL,
  `last_error`               VARCHAR(500) DEFAULT NULL,
  `created_at`               DATETIME     NOT NULL,
  `updated_at`               DATETIME     NOT NULL,
  `deleted_at`               DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_instance_uuid` (`instance_uuid`),
  KEY `idx_status` (`status`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `marketplace_app_cache` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `connection_id`     INT UNSIGNED NOT NULL,
  `entitlement_code`  VARCHAR(64)  NOT NULL,
  `remote_app_id`     VARCHAR(64)  NOT NULL,
  `app_code`          VARCHAR(80)  NOT NULL,
  `app_name`          VARCHAR(120) NOT NULL,
  `app_description`   VARCHAR(500) DEFAULT NULL,
  `app_icon_url`      VARCHAR(255) DEFAULT NULL,
  `publisher_name`    VARCHAR(80)  DEFAULT NULL,
  `plan_code`         VARCHAR(64)  DEFAULT NULL,
  `billing_cycle`     VARCHAR(16)  DEFAULT NULL,
  `period_end`        DATETIME     DEFAULT NULL,
  `latest_version`    VARCHAR(20)  DEFAULT NULL,
  `latest_version_id` VARCHAR(64)  DEFAULT NULL,
  `compatible`        TINYINT      NOT NULL DEFAULT 1,
  `synced_at`         DATETIME     NOT NULL,
  `created_at`        DATETIME     NOT NULL,
  `updated_at`        DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conn_entitlement` (`connection_id`, `entitlement_code`),
  KEY `idx_app_code` (`app_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `marketplace_public_keys` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_id`     VARCHAR(64)  NOT NULL,
  `pem`        TEXT         NOT NULL,
  `fetched_at` DATETIME     NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key_id` (`key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 逐列幂等 ALTER plugins (MySQL 8.0 全版本兼容, 无 DELIMITER, 重跑安全)
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugins' AND COLUMN_NAME='distribution_source')=0,
  'ALTER TABLE `plugins` ADD COLUMN `distribution_source` VARCHAR(20) NOT NULL DEFAULT ''zip'' AFTER `source`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugins' AND COLUMN_NAME='remote_app_id')=0,
  'ALTER TABLE `plugins` ADD COLUMN `remote_app_id` VARCHAR(64) DEFAULT NULL AFTER `distribution_source`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugins' AND COLUMN_NAME='remote_version_id')=0,
  'ALTER TABLE `plugins` ADD COLUMN `remote_version_id` VARCHAR(64) DEFAULT NULL AFTER `remote_app_id`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugins' AND COLUMN_NAME='publisher_name')=0,
  'ALTER TABLE `plugins` ADD COLUMN `publisher_name` VARCHAR(80) DEFAULT NULL AFTER `remote_version_id`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugins' AND COLUMN_NAME='installed_hash')=0,
  'ALTER TABLE `plugins` ADD COLUMN `installed_hash` VARCHAR(64) DEFAULT NULL AFTER `publisher_name`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugins' AND COLUMN_NAME='signature_status')=0,
  'ALTER TABLE `plugins` ADD COLUMN `signature_status` VARCHAR(16) DEFAULT NULL AFTER `installed_hash`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugins' AND COLUMN_NAME='latest_version')=0,
  'ALTER TABLE `plugins` ADD COLUMN `latest_version` VARCHAR(20) DEFAULT NULL AFTER `signature_status`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugins' AND COLUMN_NAME='update_available')=0,
  'ALTER TABLE `plugins` ADD COLUMN `update_available` TINYINT NOT NULL DEFAULT 0 AFTER `latest_version`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugins' AND INDEX_NAME='idx_distribution')=0,
  'ALTER TABLE `plugins` ADD INDEX `idx_distribution` (`distribution_source`)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='plugins' AND INDEX_NAME='idx_remote_app')=0,
  'ALTER TABLE `plugins` ADD INDEX `idx_remote_app` (`remote_app_id`)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 权限点位
INSERT IGNORE INTO `permissions` (`tenant_id`, `name`, `title`, `group`, `description`, `guard_name`, `status`, `sort`, `created_at`, `updated_at`)
VALUES
  (0, 'marketplace.connection.manage', '管理 Site 连接',       '平台管理', '管理官方市场 Site 连接', 'admin', 1, 0, NOW(), NOW()),
  (0, 'marketplace.connection.view',   '查看 Site 连接',       '平台管理', '查看官方市场 Site 连接列表', 'admin', 1, 1, NOW(), NOW()),
  (0, 'marketplace.catalog.view',      '查看官方市场应用目录', '平台管理', '查看官方市场已购应用目录', 'admin', 1, 2, NOW(), NOW()),
  (0, 'marketplace.install',           '安装/升级官方应用',    '平台管理', '从官方市场安装或升级应用', 'admin', 1, 3, NOW(), NOW());
