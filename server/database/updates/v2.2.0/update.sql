-- v2.2.0 升级 SQL（增量）
-- 注意：升级前请备份。本脚本对 app_versions 表的 tenant_id 数据有安全说明。
-- 请先手工运行下方安全检查 SELECT，确认结果为 0 再继续执行。
--
--   SELECT COUNT(*) FROM app_versions WHERE tenant_id != 0;
--
-- 如结果不为 0，需人工合并非 0 数据后再执行第 4 步。

-- 1. plugins 加 kind 字段（app=贡献顶级菜单, plugin=配置型）
ALTER TABLE `plugins`
  ADD COLUMN `kind` varchar(16) NOT NULL DEFAULT 'plugin' COMMENT 'app|plugin' AFTER `type`,
  ADD INDEX `idx_kind` (`kind`);

-- 2. menus 加 code 字段 + 复合唯一索引（允许 NULL；旧菜单种子可空）
ALTER TABLE `menus`
  ADD COLUMN `code` varchar(80) DEFAULT NULL COMMENT '插件菜单唯一标识，与 plugin.json menus[].code 对应' AFTER `parent_id`,
  ADD UNIQUE INDEX `uq_tenant_code` (`tenant_id`, `code`);

-- 3. plugin_menus 关联表（App 安装时贡献的菜单关联）
CREATE TABLE IF NOT EXISTS `plugin_menus` (
  `plugin_id` int unsigned NOT NULL,
  `menu_id` int unsigned NOT NULL,
  `sort` int NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`plugin_id`, `menu_id`),
  KEY `idx_plugin` (`plugin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='App 安装时贡献的菜单关联';

-- 4. app_versions 去 tenant_id
--    前提：已确认 SELECT COUNT(*) FROM app_versions WHERE tenant_id != 0; 结果为 0
ALTER TABLE `app_versions` DROP INDEX `idx_tenant_platform_ver`;
ALTER TABLE `app_versions` DROP INDEX `idx_tenant_status`;
ALTER TABLE `app_versions` DROP COLUMN `tenant_id`;
ALTER TABLE `app_versions` ADD INDEX `idx_platform_ver` (`platform`, `version_code`);
ALTER TABLE `app_versions` ADD INDEX `idx_status` (`status`);

-- 5. 菜单名调整：'插件商城' -> '应用管理'（tenant 端，tenant_id=0 模板）
UPDATE `menus` SET `name` = '应用管理' WHERE `name` = '插件商城' AND `tenant_id` = 0;

-- 6. 删除 tenant 端 区域管理 / 应用版本 菜单（模板 tenant_id=0 + 已复制给各租户的实例）
DELETE FROM `menus` WHERE `path` IN ('/app/region', '/app/version') OR `path` REGEXP '^/app$' AND `name` = '应用管理' AND `component` = 'LAYOUT';
-- 精确删除：按已知菜单 id（种子值）删除，不影响其他数据
DELETE FROM `menus` WHERE `id` IN (8, 800, 801, 802, 803, 810, 811, 812, 813);

-- 7. platform 端新增 区域管理 / 应用版本 菜单（见 init.sql 新增段落）
--    在全新安装时由 init.sql 写入；存量升级请手工执行以下 INSERT：
INSERT IGNORE INTO `platform_menus` (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `sort`, `permission`, `type`, `hidden`, `status`, `created_at`, `updated_at`) VALUES
  (260, 0, '区域管理', 'region', 'region/index', 'i-svg:map-pinned', 10, 'platform.region.list', 2, 0, 1, NOW(), NOW()),
  (261, 260, '新增区域', '', '', '', 1, 'platform.region.create', 3, 0, 1, NOW(), NOW()),
  (262, 260, '编辑区域', '', '', '', 2, 'platform.region.update', 3, 0, 1, NOW(), NOW()),
  (263, 260, '删除区域', '', '', '', 3, 'platform.region.delete', 3, 0, 1, NOW(), NOW()),
  (270, 0, '应用版本', 'version', 'version/index', 'i-svg:arrow-up-from-line', 11, 'platform.version.list', 2, 0, 1, NOW(), NOW()),
  (271, 270, '新增版本', '', '', '', 1, 'platform.version.create', 3, 0, 1, NOW(), NOW()),
  (272, 270, '编辑版本', '', '', '', 2, 'platform.version.update', 3, 0, 1, NOW(), NOW()),
  (273, 270, '删除版本', '', '', '', 3, 'platform.version.delete', 3, 0, 1, NOW(), NOW());
