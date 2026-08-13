-- v2.5.0 升级 SQL：移动端独立编译 + 小程序上传 + H5 发布

-- 1. 新增构建任务表
CREATE TABLE IF NOT EXISTS `tenant_mobile_builds` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL COMMENT '租户 ID',
  `plan_id` int unsigned NOT NULL DEFAULT 0 COMMENT '套餐 ID 快照',
  `platform` varchar(20) NOT NULL COMMENT 'h5 / mp-weixin / app',
  `build_no` varchar(32) NOT NULL COMMENT '本租户内自增编号',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0=queued 1=running 2=success 3=failed 4=uploaded 5=released',
  `included_plugins_json` json DEFAULT NULL,
  `pages_json` json DEFAULT NULL,
  `manifest_json` json DEFAULT NULL,
  `artifact_path` varchar(500) NOT NULL DEFAULT '',
  `upload_result_json` json DEFAULT NULL,
  `error_log` text,
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `operator_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_build_no` (`tenant_id`,`build_no`),
  KEY `idx_tenant_platform_status` (`tenant_id`,`platform`,`status`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='租户移动端编译任务';

-- 2. tenant_mobile_configs 加密上传私钥列
ALTER TABLE `tenant_mobile_configs`
  ADD COLUMN `wechat_upload_key_ciphertext` text NULL
  COMMENT '加密后的小程序上传私钥（AES-256-CBC + base64）'
  AFTER `wechat_appid`;

-- 3. 权限种子
INSERT INTO `permissions` (`tenant_id`, `id`, `name`, `title`, `group`, `description`, `guard_name`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (0, 180, 'mobile.build.view',    '查看构建记录',  '系统管理', '查看移动端构建任务',       'admin', 1, 180, NOW(), NOW()),
  (0, 181, 'mobile.build.create',  '触发构建',     '系统管理', '触发移动端编译',           'admin', 1, 181, NOW(), NOW()),
  (0, 182, 'mobile.build.release', '发布构建',     '系统管理', '发布 H5 或上传小程序体验版', 'admin', 1, 182, NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- 4. 菜单种子（挂在「移动端配置」节点下，作为同级子菜单）
INSERT INTO `menus` (`tenant_id`, `id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  (0, 1110, 2,    2, '构建记录',     'MobileBuilds',         '/mobile-config/builds', 'mobile-config/builds', NULL, 'i-svg:cube',     'mobile.build.view',    0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 91, NOW(), NOW()),
  (0, 1111, 1110, 3, '触发构建',     'MobileBuildCreate',    '',                      '',                     NULL, '',               'mobile.build.create',  0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1,  NOW(), NOW()),
  (0, 1112, 1110, 3, '发布/上传',    'MobileBuildRelease',   '',                      '',                     NULL, '',               'mobile.build.release', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2,  NOW(), NOW())
ON DUPLICATE KEY UPDATE `updated_at` = NOW();

-- 5. 复制到已有租户
INSERT INTO `menus` (`tenant_id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`)
SELECT t.id, m.parent_id, m.type, m.title, m.name, m.path, m.component, m.redirect, m.icon, m.permission, m.is_hidden, m.is_cache, m.is_affix, m.is_iframe, m.external_link, m.breadcrumb, m.active_menu, m.meta, m.status, m.sort, NOW(), NOW()
FROM `menus` m
CROSS JOIN `tenants` t
WHERE m.tenant_id = 0 AND m.id IN (1110, 1111, 1112) AND t.id > 0
ON DUPLICATE KEY UPDATE `menus`.`updated_at` = NOW();
