-- v2.12.0 装修 C2：可视化编辑器 + 版本历史
-- 1) 新增 diy_page_versions 表
CREATE TABLE IF NOT EXISTS `diy_page_versions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `page_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'diy_pages.id',
  `version_no` int(11) NOT NULL DEFAULT '1' COMMENT '版本号(按page递增)',
  `components` json DEFAULT NULL COMMENT '组件树快照',
  `page_settings` json DEFAULT NULL COMMENT '页面设置快照',
  `note` varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '' COMMENT '备注',
  `created_by` bigint(20) DEFAULT NULL COMMENT '创建人admin_id',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_page_version` (`tenant_id`,`page_id`,`version_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='装修页面版本快照表';

-- 2) 模板租户(tenant_id=0)新增版本权限（幂等）
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0,'diy.home.version.view','查看装修版本','装修','查看首页装修历史版本','admin',1,175,NOW(),NOW()
 WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='diy.home.version.view');
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0,'diy.home.version.restore','回滚装修版本','装修','回滚首页装修历史版本','admin',1,176,NOW(),NOW()
 WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='diy.home.version.restore');

-- 3) 模板租户(tenant_id=0)新增版本按钮菜单（幂等；父级 DecorateHome 用子查询定位，
--    若模板暂无该父菜单则安全跳过，存量租户菜单由 reconcile 命令对齐）
INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, dh.id, 3, '版本列表', 'diy.home.version.view', 0, 1, 0, 0, 1, 1, 3, NOW(), NOW()
FROM (SELECT `id` FROM `menus` WHERE `tenant_id`=0 AND `name`='DecorateHome' LIMIT 1) dh
WHERE NOT EXISTS (SELECT 1 FROM `menus` m WHERE m.`tenant_id`=0 AND m.`type`=3 AND m.`permission`='diy.home.version.view');

INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, dh.id, 3, '回滚版本', 'diy.home.version.restore', 0, 1, 0, 0, 1, 1, 4, NOW(), NOW()
FROM (SELECT `id` FROM `menus` WHERE `tenant_id`=0 AND `name`='DecorateHome' LIMIT 1) dh
WHERE NOT EXISTS (SELECT 1 FROM `menus` m WHERE m.`tenant_id`=0 AND m.`type`=3 AND m.`permission`='diy.home.version.restore');

-- 4) 存量租户的版本按钮菜单：执行命令（见 README）
--    php think saas:decorate-menu-reconcile
