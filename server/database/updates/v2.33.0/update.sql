-- ============================================================
-- v2.33.0：平台端产品授权管理菜单
-- ============================================================

INSERT INTO `platform_menus`
  (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `sort`, `permission`, `type`, `hidden`, `status`, `created_at`, `updated_at`)
SELECT
  38, 30, '产品授权', 'system/license', 'system/license/index', 'i-svg:lock', 8, 'platform.license.view', 2, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `platform_menus` WHERE `id` = 38 OR `permission` = 'platform.license.view'
);

INSERT INTO `platform_menus`
  (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `sort`, `permission`, `type`, `hidden`, `status`, `created_at`, `updated_at`)
SELECT
  39, 38, '激活授权', '', '', '', 1, 'platform.license.update', 3, 0, 1, NOW(), NOW()
WHERE NOT EXISTS (
  SELECT 1 FROM `platform_menus` WHERE `id` = 39 OR `permission` = 'platform.license.update'
);
