-- v2.7.0 增量 SQL：新增 plugin_migrations 表（插件 migration 执行状态）。
-- 配合 PluginMigrator 新增的状态感知 API 使用 —— 跑过的 migration 不再重跑，
-- 失败行可重试，up 后改文件会被 file_hash 校验。
--
-- 升级后必须执行一次 backfill 命令把存量已 ENABLED 插件的 migration 文件回填
-- 为已成功，否则下次 up 会重跑已建好的 migration（保护性失败）：
--
--   php think saas:plugin-migration-backfill
--
-- 详见 server/database/updates/v2.7.0/README.md

CREATE TABLE IF NOT EXISTS `plugin_migrations` (
  `id`              int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id`       int unsigned NOT NULL,
  `plugin_code`     varchar(64) NOT NULL,
  `migration_name`  varchar(191) NOT NULL,
  `file_hash`       char(64) NOT NULL,
  `direction`       enum('up','down') NOT NULL,
  `plugin_version`  varchar(32) DEFAULT NULL,
  `duration_ms`     int unsigned DEFAULT NULL,
  `status`          enum('success','failed') NOT NULL,
  `error_msg`       text,
  `created_at`      datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plugin_migration_dir_status` (`plugin_id`,`migration_name`,`direction`,`status`),
  KEY `idx_plugin_code` (`plugin_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='v2.7.0：插件 migration 执行状态表';
