-- ============================================================
-- ydadmin v1.5.x  →  ydadmin-SaaS  存量升级脚本
-- ============================================================
-- 适用范围：已经在生产环境运行 ydadmin v1.5.x 的用户
-- 效果：在现有数据库上原地添加 SaaS 多租户支持
--       现有数据自动归属 tenant_id = 1（第一个租户）
--
-- ⚠️  运行前务必备份！见同目录 README.md
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- Phase 0: 创建 tenants 表（SaaS 新增，原库没有此表）
-- ============================================================

CREATE TABLE IF NOT EXISTS `tenants` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL COMMENT '租户名称',
  `code` varchar(50) NOT NULL COMMENT '租户标识',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态:1正常,0禁用',
  `plan` varchar(50) NOT NULL DEFAULT 'basic' COMMENT '套餐',
  `expired_at` timestamp NULL DEFAULT NULL COMMENT '到期时间(NULL=永久)',
  `contact_name` varchar(50) DEFAULT NULL COMMENT '联系人',
  `contact_mobile` varchar(20) DEFAULT NULL COMMENT '联系电话',
  `contact_email` varchar(100) DEFAULT NULL COMMENT '联系邮箱',
  `config` json DEFAULT NULL COMMENT '租户配置',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `tenants_code_unique` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='租户表';

-- 插入默认租户（对应现有数据的归属）
INSERT INTO `tenants` (`id`, `name`, `code`, `status`, `plan`, `created_at`, `updated_at`)
VALUES (1, '默认租户', 'default', 1, 'basic', NOW(), NOW())
ON DUPLICATE KEY UPDATE `name` = `name`;

-- ============================================================
-- Phase 1: 给 A 类表加 tenant_id 列
--           DEFAULT 1 => 现有数据自动归属租户 1
-- ============================================================

ALTER TABLE `admins`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `admin_roles`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `admin_id`;

ALTER TABLE `admin_login_logs`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `admin_operation_logs`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `departments`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `roles`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `role_menus`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `role_id`;

ALTER TABLE `role_permissions`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `role_id`;

ALTER TABLE `menus`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `permissions`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `users`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `user_notifications`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `user_notification_reads`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `dictionaries`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `dictionary_items`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `system_configs`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `files`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `cron_jobs`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `cron_job_logs`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `message_templates`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `message_logs`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `wechat_auto_replies`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `notifications`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `notification_reads`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `payment_orders`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `balance_logs`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `points_logs`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `feedbacks`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `announcements`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `agreements`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `app_versions`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

ALTER TABLE `data_imports`
  ADD COLUMN `tenant_id` int(10) unsigned NOT NULL DEFAULT 1 COMMENT '租户ID' AFTER `id`;

-- ============================================================
-- Phase 2: 重建复合唯一索引
--           将原单列唯一索引替换为 (tenant_id, ...) 复合索引
--           索引名均来自 ydadmin v1.5.x 实际 schema
-- ============================================================

-- ---- admins ----
-- 原: UNIQUE KEY `admins_username_unique` (`username`)
-- 原: UNIQUE KEY `admins_email_unique` (`email`)
ALTER TABLE `admins`
  DROP INDEX `admins_username_unique`,
  DROP INDEX `admins_email_unique`,
  ADD UNIQUE KEY `admins_tenant_username_unique` (`tenant_id`, `username`),
  ADD UNIQUE KEY `admins_tenant_email_unique` (`tenant_id`, `email`);

-- ---- departments ----
-- 原: UNIQUE KEY `uk_code` (`code`)
ALTER TABLE `departments`
  DROP INDEX `uk_code`,
  ADD UNIQUE KEY `departments_tenant_code_unique` (`tenant_id`, `code`);

-- ---- roles ----
-- 原: UNIQUE KEY `roles_name_unique` (`name`)
ALTER TABLE `roles`
  DROP INDEX `roles_name_unique`,
  ADD UNIQUE KEY `roles_tenant_name_unique` (`tenant_id`, `name`);

-- ---- permissions ----
-- 原: UNIQUE KEY `permissions_name_unique` (`name`)
ALTER TABLE `permissions`
  DROP INDEX `permissions_name_unique`,
  ADD UNIQUE KEY `permissions_tenant_name_unique` (`tenant_id`, `name`);

-- ---- users ----
-- 原: UNIQUE KEY `uk_mobile` (`mobile`)
ALTER TABLE `users`
  DROP INDEX `uk_mobile`,
  ADD UNIQUE KEY `users_tenant_mobile_unique` (`tenant_id`, `mobile`);

-- ---- user_notification_reads ----
-- 原: UNIQUE KEY `idx_notification_user_unique` (`notification_id`, `user_id`)
ALTER TABLE `user_notification_reads`
  DROP INDEX `idx_notification_user_unique`,
  ADD UNIQUE KEY `unr_tenant_notification_user_unique` (`tenant_id`, `notification_id`, `user_id`);

-- ---- dictionaries ----
-- 原: UNIQUE KEY `uk_code` (`code`)
ALTER TABLE `dictionaries`
  DROP INDEX `uk_code`,
  ADD UNIQUE KEY `dictionaries_tenant_code_unique` (`tenant_id`, `code`);

-- ---- dictionary_items ----
-- 原: UNIQUE KEY `uk_dict_value` (`dictionary_id`, `value`)
ALTER TABLE `dictionary_items`
  DROP INDEX `uk_dict_value`,
  ADD UNIQUE KEY `dictionary_items_tenant_dict_value_unique` (`tenant_id`, `dictionary_id`, `value`);

-- ---- system_configs ----
-- 原: UNIQUE KEY `system_configs_key_unique` (`config_key`)
ALTER TABLE `system_configs`
  DROP INDEX `system_configs_key_unique`,
  ADD UNIQUE KEY `system_configs_tenant_key_unique` (`tenant_id`, `config_key`);

-- ---- message_templates ----
-- 原: UNIQUE KEY `uk_code` (`code`)
ALTER TABLE `message_templates`
  DROP INDEX `uk_code`,
  ADD UNIQUE KEY `message_templates_tenant_code_unique` (`tenant_id`, `code`);

-- ---- notification_reads ----
-- 原: UNIQUE KEY `uk_notification_admin` (`notification_id`, `admin_id`)
ALTER TABLE `notification_reads`
  DROP INDEX `uk_notification_admin`,
  ADD UNIQUE KEY `notification_reads_tenant_notif_admin_unique` (`tenant_id`, `notification_id`, `admin_id`);

-- ---- payment_orders ----
-- 原: UNIQUE KEY `uk_order_no` (`order_no`)
ALTER TABLE `payment_orders`
  DROP INDEX `uk_order_no`,
  ADD UNIQUE KEY `payment_orders_tenant_order_no_unique` (`tenant_id`, `order_no`);

-- ---- agreements ----
-- 原: UNIQUE KEY `uk_code` (`code`)
ALTER TABLE `agreements`
  DROP INDEX `uk_code`,
  ADD UNIQUE KEY `agreements_tenant_code_unique` (`tenant_id`, `code`);

-- ============================================================
-- Phase 3: 给 tenant_id 加索引（提高多租户查询性能）
-- ============================================================

ALTER TABLE `admins`             ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `admin_roles`        ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `admin_login_logs`   ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `admin_operation_logs` ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `departments`        ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `roles`              ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `role_menus`         ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `role_permissions`   ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `menus`              ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `permissions`        ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `users`              ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `user_notifications` ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `user_notification_reads` ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `dictionaries`       ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `dictionary_items`   ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `system_configs`     ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `files`              ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `cron_jobs`          ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `cron_job_logs`      ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `message_templates`  ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `message_logs`       ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `wechat_auto_replies` ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `notifications`      ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `notification_reads` ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `payment_orders`     ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `balance_logs`       ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `points_logs`        ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `feedbacks`          ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `announcements`      ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `agreements`         ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `app_versions`       ADD INDEX `idx_tenant_id` (`tenant_id`);
ALTER TABLE `data_imports`       ADD INDEX `idx_tenant_id` (`tenant_id`);

-- ============================================================
-- 注意：regions 表为全局公共数据，不加 tenant_id
-- ============================================================

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 升级完成
-- 现有数据已自动归属 tenant_id = 1（默认租户）
-- 如需添加更多租户，请在管理后台操作或手动 INSERT INTO `tenants`
-- ============================================================
