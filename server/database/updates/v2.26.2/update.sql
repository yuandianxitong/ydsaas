-- v2.26.2 升级补丁
-- 平台「应用管理」一级菜单图标 i-svg:plug → i-svg:blocks
-- 幂等：只在仍为旧图标时更新。
UPDATE `platform_menus`
SET `icon` = 'i-svg:blocks', `updated_at` = NOW()
WHERE `id` = 240 AND `permission` = 'platform.plugin.list' AND `icon` = 'i-svg:plug';
