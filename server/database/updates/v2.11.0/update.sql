-- v2.11.0 装修 C1：首页装修 + 装修菜单 + 移除移动端配置
-- 1) 新增 diy_pages 表
CREATE TABLE IF NOT EXISTS `diy_pages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `page_type` varchar(32) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'home' COMMENT '页面类型:home',
  `platform` varchar(16) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'uniapp' COMMENT '端:uniapp',
  `title` varchar(100) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '' COMMENT '页面名称',
  `components_draft` json DEFAULT NULL COMMENT '草稿组件树',
  `components_published` json DEFAULT NULL COMMENT '已发布组件树',
  `page_settings` json DEFAULT NULL COMMENT '页面设置',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_page_platform` (`tenant_id`,`page_type`,`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='装修页面表';

-- 2) 模板租户(tenant_id=0)新增 diy 权限（幂等）
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0,'diy.home.view','查看首页装修','装修','查看首页装修配置','admin',1,172,NOW(),NOW()
 WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='diy.home.view');
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0,'diy.home.save','保存首页装修','装修','保存首页装修草稿','admin',1,173,NOW(),NOW()
 WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='diy.home.save');
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0,'diy.home.publish','发布首页装修','装修','发布首页装修','admin',1,174,NOW(),NOW()
 WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='diy.home.publish');

-- 3) 存量租户的菜单/授权迁移：执行命令（见 README）
--    php think saas:decorate-menu-reconcile
