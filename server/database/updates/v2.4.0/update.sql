-- v2.4.0 升级 SQL：移动端配置层
-- 1. 新增租户移动端配置表

CREATE TABLE IF NOT EXISTS `tenant_mobile_configs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL COMMENT '租户 ID（一对一）',
  `app_name` varchar(100) NOT NULL DEFAULT '' COMMENT '小程序/App 应用名（显示用）',
  `app_logo` varchar(500) NOT NULL DEFAULT '' COMMENT '应用 Logo URL',
  `theme_color` varchar(16) NOT NULL DEFAULT '' COMMENT '主题色（如 #2979ff）',
  `home_app_code` varchar(80) NOT NULL DEFAULT '' COMMENT '启动首页所属插件 code',
  `home_page` varchar(200) NOT NULL DEFAULT '' COMMENT '启动首页完整路径',
  `tabbar_json` json DEFAULT NULL COMMENT 'tabBar 配置 JSON',
  `wechat_appid` varchar(64) NOT NULL DEFAULT '' COMMENT '小程序 AppID（Phase C 才使用）',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1=启用 0=禁用',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='租户移动端配置';

-- 2. 租户后台权限 + 菜单（移动端配置入口）
--    模板行（tenant_id=0），后续会复制到所有租户。
--    若环境已通过重新执行 init.sql 升级，可跳过这一段。

INSERT INTO `permissions` (`tenant_id`, `id`, `name`, `title`, `group`, `description`, `guard_name`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (0, 170, 'mobile.config.view',   '查看移动端配置', '系统管理', '查看移动端配置', 'admin', 1, 170, NOW(), NOW()),
  (0, 171, 'mobile.config.update', '修改移动端配置', '系统管理', '保存移动端配置', 'admin', 1, 171, NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

INSERT INTO `menus` (`tenant_id`, `id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (0, 1100, 2,    2, '移动端配置', 'MobileConfig',     '/mobile-config', 'mobile-config/index', NULL, 'i-svg:mobile-phone', 'mobile.config.view',   0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 90, NOW(), NOW()),
  (0, 1101, 1100, 3, '保存配置',   'MobileConfigSave', '',               '',                    NULL, '',                   'mobile.config.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1,  NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- 3. 复制模板到已有租户（菜单和权限同时下发）
--    若使用 saas:plugin-menu-reconcile 等 reconcile 机制，可省略本步骤。
INSERT INTO `menus` (`tenant_id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`)
SELECT t.id, m.parent_id, m.type, m.title, m.name, m.path, m.component, m.redirect, m.icon, m.permission, m.is_hidden, m.is_cache, m.is_affix, m.is_iframe, m.external_link, m.breadcrumb, m.active_menu, m.meta, m.status, m.sort, NOW(), NOW()
FROM `menus` m
CROSS JOIN `tenants` t
WHERE m.tenant_id = 0 AND m.id IN (1100, 1101) AND t.id > 0
ON DUPLICATE KEY UPDATE `menus`.`updated_at` = NOW();
