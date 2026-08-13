-- v2.1.0 数据库升级脚本：下线旧 addon 系统
-- 升级前请备份数据库：
--   mysqldump -uroot -p <dbname> plan_features tenant_addons tenant_addon_configs > addon_backup_v2.0.0.sql

-- 1. 重命名 saas_orders.addon_feature_id 为 plugin_id（语义已切换到插件）
ALTER TABLE `saas_orders`
    CHANGE `addon_feature_id` `plugin_id` int unsigned DEFAULT NULL COMMENT '插件ID（type=4 时）';

-- 2. 删除 plans.features JSON 字段（已迁移到 plugin_grants 表）
ALTER TABLE `plans` DROP COLUMN `features`;

-- 3. 删除旧 addon 表
DROP TABLE IF EXISTS `tenant_addon_configs`;
DROP TABLE IF EXISTS `tenant_addons`;
DROP TABLE IF EXISTS `plan_features`;
