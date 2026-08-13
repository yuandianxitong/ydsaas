-- ============================================================
-- framework-saas v2.32.0 升级 SQL
-- 微信小程序上传：可维护版本号 + 项目备注
-- 幂等：列不存在才 ADD。
-- ============================================================

SET @tbl := 'tenant_mobile_configs';

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=@tbl AND column_name='wechat_upload_version')=0,
  'ALTER TABLE `tenant_mobile_configs` ADD COLUMN `wechat_upload_version` varchar(32) NOT NULL DEFAULT ''1.0.0'' COMMENT ''小程序上传版本号（语义化 x.y.z，成功后自动 patch+1）'' AFTER `wechat_upload_key_ciphertext`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=@tbl AND column_name='wechat_upload_desc')=0,
  'ALTER TABLE `tenant_mobile_configs` ADD COLUMN `wechat_upload_desc` varchar(200) NOT NULL DEFAULT ''租户后台发布'' COMMENT ''小程序上传项目备注'' AFTER `wechat_upload_version`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- ============================================================
-- 装修菜单：新增「启动与首页」DiyLaunch，并重排页面装修分组 sort
-- 幂等：按 tenant_id + name 判重；role_menus 授予 is_system=1 角色
-- ============================================================

-- 模板租户
INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`name`,`path`,`component`,`icon`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, g.`id`, 2, '启动与首页', 'DiyLaunch', '/diy/launch', 'diy/launch', 'i-svg:rocket', 'mobile.config.view', 0, 1, 0, 0, 1, 1, 2, NOW(), NOW()
FROM (SELECT `id` FROM `menus` WHERE `tenant_id`=0 AND `name`='DiyPageGroup' LIMIT 1) g
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `tenant_id`=0 AND `name`='DiyLaunch');

INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, p.`id`, 3, '保存', 'mobile.config.update', 0, 1, 0, 0, 1, 1, 1, NOW(), NOW()
FROM (SELECT `id` FROM `menus` WHERE `tenant_id`=0 AND `name`='DiyLaunch' LIMIT 1) p
WHERE NOT EXISTS (
  SELECT 1 FROM `menus` m
  WHERE m.`tenant_id`=0 AND m.`parent_id`=p.`id` AND m.`type`=3 AND m.`permission`='mobile.config.update'
);

-- 存量租户（挂各自 DiyPageGroup）
INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`name`,`path`,`component`,`icon`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT g.`tenant_id`, g.`id`, 2, '启动与首页', 'DiyLaunch', '/diy/launch', 'diy/launch', 'i-svg:rocket', 'mobile.config.view', 0, 1, 0, 0, 1, 1, 2, NOW(), NOW()
FROM `menus` g
WHERE g.`name` = 'DiyPageGroup' AND g.`tenant_id` > 0
  AND NOT EXISTS (
    SELECT 1 FROM `menus` m WHERE m.`tenant_id` = g.`tenant_id` AND m.`name` = 'DiyLaunch'
  );

INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT p.`tenant_id`, p.`id`, 3, '保存', 'mobile.config.update', 0, 1, 0, 0, 1, 1, 1, NOW(), NOW()
FROM `menus` p
WHERE p.`name` = 'DiyLaunch'
  AND NOT EXISTS (
    SELECT 1 FROM `menus` m
    WHERE m.`tenant_id` = p.`tenant_id` AND m.`parent_id` = p.`id` AND m.`type`=3 AND m.`permission`='mobile.config.update'
  );

-- 重排页面装修分组内 sort（与 reconcile / init.sql 对齐）
UPDATE `menus` SET `sort` = 1, `updated_at` = NOW() WHERE `name` = 'DiyHome'   AND `type` = 2;
UPDATE `menus` SET `sort` = 2, `updated_at` = NOW() WHERE `name` = 'DiyLaunch' AND `type` = 2;
UPDATE `menus` SET `sort` = 3, `updated_at` = NOW() WHERE `name` = 'DiyPages'  AND `type` = 2;
UPDATE `menus` SET `sort` = 4, `updated_at` = NOW() WHERE `name` = 'DiyTabbar' AND `type` = 2;
UPDATE `menus` SET `sort` = 5, `updated_at` = NOW() WHERE `name` = 'DiyTheme'  AND `type` = 2;
UPDATE `menus` SET `sort` = 6, `updated_at` = NOW() WHERE `name` = 'DiyLinks'  AND `type` = 2;

-- 授予系统角色（is_system=1）可见/可保存
INSERT INTO `role_menus` (`tenant_id`, `role_id`, `menu_id`, `created_at`, `updated_at`)
SELECT m.`tenant_id`, r.`id`, m.`id`, NOW(), NOW()
FROM `menus` m
JOIN `roles` r ON r.`tenant_id` = m.`tenant_id` AND r.`is_system` = 1
WHERE m.`name` = 'DiyLaunch'
  AND NOT EXISTS (
    SELECT 1 FROM `role_menus` rm
    WHERE rm.`tenant_id` = m.`tenant_id` AND rm.`role_id` = r.`id` AND rm.`menu_id` = m.`id`
  );

INSERT INTO `role_menus` (`tenant_id`, `role_id`, `menu_id`, `created_at`, `updated_at`)
SELECT btn.`tenant_id`, r.`id`, btn.`id`, NOW(), NOW()
FROM `menus` btn
JOIN `menus` p ON p.`id` = btn.`parent_id` AND p.`name` = 'DiyLaunch' AND p.`tenant_id` = btn.`tenant_id`
JOIN `roles` r ON r.`tenant_id` = btn.`tenant_id` AND r.`is_system` = 1
WHERE btn.`type` = 3 AND btn.`permission` = 'mobile.config.update'
  AND NOT EXISTS (
    SELECT 1 FROM `role_menus` rm
    WHERE rm.`tenant_id` = btn.`tenant_id` AND rm.`role_id` = r.`id` AND rm.`menu_id` = btn.`id`
  );
