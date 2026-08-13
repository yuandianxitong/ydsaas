-- v2.27.0 租户 PC 前台配置与插件 PC 页面能力

CREATE TABLE IF NOT EXISTS `tenant_pc_configs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL COMMENT '租户 ID（一对一）',
  `site_name` varchar(100) NOT NULL DEFAULT '' COMMENT 'PC 站点名称',
  `site_logo` varchar(500) NOT NULL DEFAULT '' COMMENT 'PC 站点 Logo URL',
  `site_intro` varchar(255) NOT NULL DEFAULT '' COMMENT 'PC 站点简介',
  `theme_color` varchar(16) NOT NULL DEFAULT '#2563eb' COMMENT 'PC 主题色',
  `home_type` varchar(20) NOT NULL DEFAULT 'diy' COMMENT '首页类型：diy/app/redirect',
  `home_app_code` varchar(80) NOT NULL DEFAULT '' COMMENT '首页所属插件 entitlement/code',
  `home_page` varchar(200) NOT NULL DEFAULT 'home' COMMENT '首页路径或装修 page_key',
  `nav_json` json DEFAULT NULL COMMENT 'PC 导航 [{label,path,code,auth,sort}]',
  `seo_json` json DEFAULT NULL COMMENT 'PC SEO 配置 {title,keywords,description}',
  `login_enabled` tinyint NOT NULL DEFAULT 1 COMMENT '是否显示登录入口',
  `register_enabled` tinyint NOT NULL DEFAULT 1 COMMENT '是否显示注册入口',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1=启用 0=禁用',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='租户 PC 前台配置';

INSERT INTO `tenant_pc_configs` (`tenant_id`, `site_name`, `site_logo`, `site_intro`, `theme_color`, `home_type`, `home_app_code`, `home_page`, `nav_json`, `seo_json`, `login_enabled`, `register_enabled`, `status`, `created_at`, `updated_at`)
SELECT 0, '元点 SaaS', '', '租户 PC 前台', '#2563eb', 'diy', '', 'home',
       JSON_ARRAY(JSON_OBJECT('label','首页','path','/','code','','auth',false,'sort',1)),
       JSON_OBJECT('title','元点 SaaS','keywords','','description','租户 PC 前台'),
       1, 1, 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `tenant_pc_configs` WHERE `tenant_id` = 0);

INSERT INTO `tenant_pc_configs` (`tenant_id`, `site_name`, `site_logo`, `site_intro`, `theme_color`, `home_type`, `home_app_code`, `home_page`, `nav_json`, `seo_json`, `login_enabled`, `register_enabled`, `status`, `created_at`, `updated_at`)
SELECT t.`id`, t.`name`, '', '租户 PC 前台', '#2563eb', 'diy', '', 'home',
       JSON_ARRAY(JSON_OBJECT('label','首页','path','/','code','','auth',false,'sort',1)),
       JSON_OBJECT('title', t.`name`, 'keywords', '', 'description', '租户 PC 前台'),
       1, 1, 1, NOW(), NOW()
FROM `tenants` t
WHERE t.`id` > 0
  AND NOT EXISTS (SELECT 1 FROM `tenant_pc_configs` c WHERE c.`tenant_id` = t.`id`);

UPDATE `tenant_pc_configs`
SET `home_page` = '/cms', `updated_at` = NOW()
WHERE `home_type` = 'app'
  AND `home_app_code` = 'cms'
  AND `home_page` = '/';

INSERT INTO `diy_pages` (`tenant_id`, `page_type`, `page_key`, `platform`, `title`, `components_draft`, `components_published`, `page_settings`, `status`, `created_at`, `updated_at`)
SELECT 0, 'home', 'home', 'pc', 'PC 首页',
       '[{"id":"pc-hero","type":"title-bar","props":{"title":"欢迎来到元点 SaaS","subtitle":"一站式搭建租户官网、内容门户和用户前台","align":"center","more_text":"","more_link":""}},{"id":"pc-rich","type":"rich-text","props":{"content":"<p>租户可在后台配置 CMS 官网、插件前台或自定义单页作为 PC 首页。</p>"}}]',
       '[{"id":"pc-hero","type":"title-bar","props":{"title":"欢迎来到元点 SaaS","subtitle":"一站式搭建租户官网、内容门户和用户前台","align":"center","more_text":"","more_link":""}},{"id":"pc-rich","type":"rich-text","props":{"content":"<p>租户可在后台配置 CMS 官网、插件前台或自定义单页作为 PC 首页。</p>"}}]',
       '{"background_color":"#f8fafc","title":"PC 首页"}',
       1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `diy_pages` p
  WHERE p.`tenant_id` = 0 AND p.`platform` = 'pc' AND p.`page_key` = 'home' AND p.`deleted_at` IS NULL
);

INSERT INTO `diy_pages` (`tenant_id`, `page_type`, `page_key`, `platform`, `title`, `components_draft`, `components_published`, `page_settings`, `status`, `created_at`, `updated_at`)
SELECT t.`id`, 'home', 'home', 'pc', 'PC 首页',
       '[{"id":"pc-hero","type":"title-bar","props":{"title":"欢迎来到元点 SaaS","subtitle":"一站式搭建租户官网、内容门户和用户前台","align":"center","more_text":"","more_link":""}},{"id":"pc-rich","type":"rich-text","props":{"content":"<p>租户可在后台配置 CMS 官网、插件前台或自定义单页作为 PC 首页。</p>"}}]',
       '[{"id":"pc-hero","type":"title-bar","props":{"title":"欢迎来到元点 SaaS","subtitle":"一站式搭建租户官网、内容门户和用户前台","align":"center","more_text":"","more_link":""}},{"id":"pc-rich","type":"rich-text","props":{"content":"<p>租户可在后台配置 CMS 官网、插件前台或自定义单页作为 PC 首页。</p>"}}]',
       '{"background_color":"#f8fafc","title":"PC 首页"}',
       1, NOW(), NOW()
FROM `tenants` t
WHERE NOT EXISTS (
  SELECT 1 FROM `diy_pages` p
  WHERE p.`tenant_id` = t.`id` AND p.`platform` = 'pc' AND p.`page_key` = 'home' AND p.`deleted_at` IS NULL
);

INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0,'pc.config.view','查看PC端配置','系统管理','查看PC端配置','admin',1,187,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='pc.config.view');

INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0,'pc.config.update','修改PC端配置','系统管理','保存PC端配置','admin',1,188,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='pc.config.update');

INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT t.`id`, p.`name`, p.`title`, p.`group`, p.`description`, p.`guard_name`, p.`status`, p.`sort`, NOW(), NOW()
FROM `tenants` t
JOIN `permissions` p ON p.`tenant_id` = 0 AND p.`name` IN ('pc.config.view', 'pc.config.update')
WHERE t.`id` > 0
  AND NOT EXISTS (
    SELECT 1 FROM `permissions` e WHERE e.`tenant_id` = t.`id` AND e.`name` = p.`name`
  );

INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`name`,`path`,`component`,`icon`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, g.`id`, 2, 'PC端配置', 'DiyPcConfig', '/diy/pc', 'diy/pc', 'i-svg:monitor', 'pc.config.view', 0, 1, 0, 0, 1, 1, 3, NOW(), NOW()
FROM (SELECT `id` FROM `menus` WHERE `tenant_id`=0 AND `name`='DiyPublishGroup' LIMIT 1) g
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `tenant_id`=0 AND `name`='DiyPcConfig');

INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, p.`id`, 3, '保存', 'pc.config.update', 0, 1, 0, 0, 1, 1, 1, NOW(), NOW()
FROM (SELECT `id` FROM `menus` WHERE `tenant_id`=0 AND `name`='DiyPcConfig' LIMIT 1) p
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `tenant_id`=0 AND `permission`='pc.config.update');

INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`name`,`path`,`component`,`icon`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT g.`tenant_id`, g.`id`, 2, 'PC端配置', 'DiyPcConfig', '/diy/pc', 'diy/pc', 'i-svg:monitor', 'pc.config.view', 0, 1, 0, 0, 1, 1, 3, NOW(), NOW()
FROM `menus` g
WHERE g.`name` = 'DiyPublishGroup' AND g.`tenant_id` > 0
  AND NOT EXISTS (SELECT 1 FROM `menus` m WHERE m.`tenant_id` = g.`tenant_id` AND m.`name` = 'DiyPcConfig');

INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT p.`tenant_id`, p.`id`, 3, '保存', 'pc.config.update', 0, 1, 0, 0, 1, 1, 1, NOW(), NOW()
FROM `menus` p
WHERE p.`name` = 'DiyPcConfig'
  AND NOT EXISTS (
    SELECT 1 FROM `menus` m WHERE m.`tenant_id` = p.`tenant_id` AND m.`permission` = 'pc.config.update'
  );
