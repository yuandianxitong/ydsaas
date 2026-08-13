-- v2.28.0 插件 database/ SQL 化配套（幂等可重跑）
-- 1) 租户插件演示数据导入标记
-- 内容：tenant_plugins +1 列（testdata_imported_at，演示数据导入时间标记）
-- 纯加列，向后兼容；存量行 NULL 表示演示数据未导入。

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenant_plugins' AND COLUMN_NAME='testdata_imported_at')=0,
  'ALTER TABLE `tenant_plugins` ADD COLUMN `testdata_imported_at` datetime DEFAULT NULL COMMENT ''演示数据导入时间（NULL=未导入）'' AFTER `installed_version`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) 导入演示数据按钮权限（plugin.testdata），挂「已安装」PluginInstalled 下
-- 写法照抄 v2.27.1 update.sql 第 3/4 节：模板（tenant_id=0）+ 存量租户复制，均 WHERE NOT EXISTS 幂等

-- 2.1) 模板菜单按钮（tenant_id=0）
INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, b.`id`, 3, '导入演示数据', 'plugin.testdata', 0, 1, 0, 0, 1, 1, 6, NOW(), NOW()
FROM (SELECT `id` FROM `menus` WHERE `tenant_id`=0 AND `name`='PluginInstalled' LIMIT 1) b
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `tenant_id`=0 AND `permission`='plugin.testdata');

-- 2.2) 复制到存量租户（挂各租户自己的 PluginInstalled 下）
INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT b.`tenant_id`, b.`id`, 3, '导入演示数据', 'plugin.testdata', 0, 1, 0, 0, 1, 1, 6, NOW(), NOW()
FROM `menus` b
WHERE b.`name` = 'PluginInstalled' AND b.`tenant_id` > 0
  AND NOT EXISTS (
    SELECT 1 FROM `menus` m WHERE m.`tenant_id` = b.`tenant_id` AND m.`permission` = 'plugin.testdata'
  );
