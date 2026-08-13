-- v2.26.0 装修链接库：diy_links 表 + 菜单/权限种子
-- 幂等脚本，可重复执行

-- 1) 新增 diy_links 表
CREATE TABLE IF NOT EXISTS `diy_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL DEFAULT 0,
  `label` varchar(64) NOT NULL DEFAULT '',
  `path` varchar(255) NOT NULL DEFAULT '',
  `category` varchar(32) NOT NULL DEFAULT '我的链接',
  `icon` varchar(64) DEFAULT NULL,
  `sort` int NOT NULL DEFAULT 0,
  `status` tinyint NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='装修链接库';

-- 2) 模板租户(tenant_id=0)新增链接库权限（幂等）
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0,'diy.link.list','查看链接库','装修','查看装修链接库','admin',1,183,NOW(),NOW()
 WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='diy.link.list');
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0,'diy.link.create','新增链接','装修','新增装修链接','admin',1,184,NOW(),NOW()
 WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='diy.link.create');
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0,'diy.link.update','编辑链接','装修','编辑装修链接','admin',1,185,NOW(),NOW()
 WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='diy.link.update');
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0,'diy.link.delete','删除链接','装修','删除装修链接','admin',1,186,NOW(),NOW()
 WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='diy.link.delete');

-- 3) 模板租户(tenant_id=0)新增"链接管理"页菜单（幂等；父级 DiyPageGroup 用子查询定位）
INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`name`,`path`,`component`,`icon`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, g.id, 2, '链接管理', 'DiyLinks', '/diy/links', 'diy/links', 'i-svg:link', 'diy.link.list', 0, 1, 0, 0, 1, 1, 5, NOW(), NOW()
FROM (SELECT `id` FROM `menus` WHERE `tenant_id`=0 AND `name`='DiyPageGroup' LIMIT 1) g
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `tenant_id`=0 AND `name`='DiyLinks');

-- 4) 模板租户(tenant_id=0)新增链接管理按钮菜单（幂等）
INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, dl.id, 3, '新增', 'diy.link.create', 0, 1, 0, 0, 1, 1, 1, NOW(), NOW()
FROM (SELECT `id` FROM `menus` WHERE `tenant_id`=0 AND `name`='DiyLinks' LIMIT 1) dl
WHERE NOT EXISTS (SELECT 1 FROM `menus` m WHERE m.`tenant_id`=0 AND m.`type`=3 AND m.`permission`='diy.link.create');

INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, dl.id, 3, '编辑', 'diy.link.update', 0, 1, 0, 0, 1, 1, 2, NOW(), NOW()
FROM (SELECT `id` FROM `menus` WHERE `tenant_id`=0 AND `name`='DiyLinks' LIMIT 1) dl
WHERE NOT EXISTS (SELECT 1 FROM `menus` m WHERE m.`tenant_id`=0 AND m.`type`=3 AND m.`permission`='diy.link.update');

INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, dl.id, 3, '删除', 'diy.link.delete', 0, 1, 0, 0, 1, 1, 3, NOW(), NOW()
FROM (SELECT `id` FROM `menus` WHERE `tenant_id`=0 AND `name`='DiyLinks' LIMIT 1) dl
WHERE NOT EXISTS (SELECT 1 FROM `menus` m WHERE m.`tenant_id`=0 AND m.`type`=3 AND m.`permission`='diy.link.delete');

-- ============================================================
-- cron_jobs：清理无效种子任务 + 登记核心菜单同步任务（幂等）
-- clear:cache / clear:temp 命令不存在且未在白名单，删除
-- ============================================================
DELETE FROM `cron_jobs` WHERE `tenant_id`=0 AND `command` IN ('clear:cache', 'clear:temp');

INSERT INTO `cron_jobs` (`tenant_id`, `name`, `command`, `expression`, `description`, `status`, `created_at`, `updated_at`)
SELECT 0, '核心菜单同步', 'saas:menu-sync', '0 * * * *', '每小时按模板给存量租户补齐核心菜单/权限（指纹门控，模板未变更则空跑）', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `cron_jobs` WHERE `tenant_id`=0 AND `command`='saas:menu-sync' AND `deleted_at` IS NULL);
