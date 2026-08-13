-- v2.29.0 素材库层级分类树（幂等可重跑）
-- 内容：新表 file_categories；files +category_id 列+索引；
--      权限/菜单种子（模板 tenant_id=0 + 存量租户复制）；
--      存量 group 字符串迁移为一级分类并挂接文件。

-- 1) file_categories 表
CREATE TABLE IF NOT EXISTS `file_categories` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `parent_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '父分类ID，0=顶级',
  `name` varchar(100) COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '分类名称',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_parent` (`tenant_id`,`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='文件分类表';

-- 2) files.category_id 列 + 索引
SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='files' AND COLUMN_NAME='category_id')=0,
  'ALTER TABLE `files` ADD COLUMN `category_id` int(11) unsigned NOT NULL DEFAULT 0 COMMENT ''分类ID，0=未分类'' AFTER `group`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='files' AND INDEX_NAME='idx_tenant_category')=0,
  'ALTER TABLE `files` ADD KEY `idx_tenant_category` (`tenant_id`,`category_id`)', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3.1) 权限模板行（tenant_id=0，幂等）
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, 'system.file.update', '编辑文件', '系统管理', '重命名/移动文件', 'admin', 1, 44, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='system.file.update');
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, 'system.file-category.create', '新建文件分类', '系统管理', '创建素材分类', 'admin', 1, 45, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='system.file-category.create');
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, 'system.file-category.update', '编辑文件分类', '系统管理', '重命名素材分类', 'admin', 1, 46, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='system.file-category.update');
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, 'system.file-category.delete', '删除文件分类', '系统管理', '删除素材分类', 'admin', 1, 47, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='system.file-category.delete');

-- 3.2) 权限存量租户复制（每个租户一份，name 唯一键 uk_tenant_name 兜底）
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT t.`id`, p.`name`, p.`title`, p.`group`, p.`description`, p.`guard_name`, p.`status`, p.`sort`, NOW(), NOW()
FROM `tenants` t
JOIN `permissions` p ON p.`tenant_id`=0 AND p.`name` IN ('system.file.update','system.file-category.create','system.file-category.update','system.file-category.delete')
WHERE NOT EXISTS (SELECT 1 FROM `permissions` x WHERE x.`tenant_id`=t.`id` AND x.`name`=p.`name`);

-- 4.1) 菜单按钮模板行（tenant_id=0，parent=70 文件管理页）
INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`name`,`path`,`component`,`redirect`,`icon`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`external_link`,`breadcrumb`,`active_menu`,`meta`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, 70, 3, '新建分类', NULL, NULL, NULL, NULL, NULL, 'system.file-category.create', 0,1,0,0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `tenant_id`=0 AND `permission`='system.file-category.create');
INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`name`,`path`,`component`,`redirect`,`icon`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`external_link`,`breadcrumb`,`active_menu`,`meta`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, 70, 3, '编辑分类', NULL, NULL, NULL, NULL, NULL, 'system.file-category.update', 0,1,0,0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `tenant_id`=0 AND `permission`='system.file-category.update');
INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`name`,`path`,`component`,`redirect`,`icon`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`external_link`,`breadcrumb`,`active_menu`,`meta`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, 70, 3, '删除分类', NULL, NULL, NULL, NULL, NULL, 'system.file-category.delete', 0,1,0,0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `tenant_id`=0 AND `permission`='system.file-category.delete');

-- 4.2) 菜单存量租户补齐：交给 `php think saas:menu-sync`（模板指纹变更后 hourly cron 自动补），README 中说明手动触发方式。

-- 5) 存量分组迁移：每个 (tenant_id, group) 建一级分类（'默认' 视为未分类不建）
INSERT INTO `file_categories` (`tenant_id`,`parent_id`,`name`,`sort`,`created_at`,`updated_at`)
SELECT f.`tenant_id`, 0, f.`group`, 0, NOW(), NOW()
FROM (SELECT DISTINCT `tenant_id`, `group` FROM `files` WHERE `group` IS NOT NULL AND `group` <> '' AND `group` <> '默认' AND `deleted_at` IS NULL) f
WHERE NOT EXISTS (SELECT 1 FROM `file_categories` c WHERE c.`tenant_id`=f.`tenant_id` AND c.`parent_id`=0 AND c.`name`=f.`group`);

-- 6) 存量文件挂接分类
UPDATE `files` f
JOIN `file_categories` c ON c.`tenant_id`=f.`tenant_id` AND c.`parent_id`=0 AND c.`name`=f.`group`
SET f.`category_id`=c.`id`
WHERE f.`category_id`=0 AND f.`group` IS NOT NULL AND f.`group` <> '' AND f.`group` <> '默认' AND f.`deleted_at` IS NULL;
