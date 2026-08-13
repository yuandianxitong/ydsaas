-- ============================================================
-- framework-saas v2.24.0 升级 SQL
-- 装修菜单 decorate → diy 重命名
--
-- 背景：后端路由（/tenantapi/diy/*）与权限码（diy.*）早已是 diy，
--       仅前端目录/路由与菜单的 name/path/component 仍是 decorate。
--       本次统一为 diy，对齐既有后端命名。
--
-- 幂等：仅命中仍为旧名(Decorate*)的行；重复执行第二次匹配 0 行。
--       菜单按 name 在每个租户内稳定，故按 name 匹配可覆盖全部租户。
-- ============================================================

-- 一级目录
UPDATE `menus` SET `name` = 'Diy', `path` = '/diy', `redirect` = '/diy/home', `updated_at` = NOW()
  WHERE `name` = 'Decorate';

-- 子菜单（name / path / component 同步）
UPDATE `menus` SET `name` = 'DiyHome',   `path` = '/diy/home',   `component` = 'diy/home',   `updated_at` = NOW() WHERE `name` = 'DecorateHome';
UPDATE `menus` SET `name` = 'DiyTabbar', `path` = '/diy/tabbar', `component` = 'diy/tabbar', `updated_at` = NOW() WHERE `name` = 'DecorateTabbar';
UPDATE `menus` SET `name` = 'DiyTheme',  `path` = '/diy/theme',  `component` = 'diy/theme',  `updated_at` = NOW() WHERE `name` = 'DecorateTheme';
UPDATE `menus` SET `name` = 'DiyBasic',  `path` = '/diy/basic',  `component` = 'diy/basic',  `updated_at` = NOW() WHERE `name` = 'DecorateBasic';
UPDATE `menus` SET `name` = 'DiyBuild',  `path` = '/diy/build',  `component` = 'diy/build',  `updated_at` = NOW() WHERE `name` = 'DecorateBuild';
UPDATE `menus` SET `name` = 'DiyPages',  `path` = '/diy/pages',  `component` = 'diy/pages',  `updated_at` = NOW() WHERE `name` = 'DecoratePages';

-- v2.24.0 追加：移动端配置字段对齐（Spec A）。逐列幂等：仅当列不存在才加。
SET @tbl := 'tenant_mobile_configs';

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=@tbl AND column_name='theme_colors')=0,
  'ALTER TABLE `tenant_mobile_configs` ADD COLUMN `theme_colors` json DEFAULT NULL AFTER `theme_color`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=@tbl AND column_name='app_intro')=0,
  'ALTER TABLE `tenant_mobile_configs` ADD COLUMN `app_intro` varchar(255) NOT NULL DEFAULT '''' AFTER `app_logo`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=@tbl AND column_name='service_type')=0,
  'ALTER TABLE `tenant_mobile_configs` ADD COLUMN `service_type` varchar(16) NOT NULL DEFAULT '''' AFTER `app_intro`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=@tbl AND column_name='service_phone')=0,
  'ALTER TABLE `tenant_mobile_configs` ADD COLUMN `service_phone` varchar(32) NOT NULL DEFAULT '''' AFTER `service_type`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=@tbl AND column_name='share_title')=0,
  'ALTER TABLE `tenant_mobile_configs` ADD COLUMN `share_title` varchar(200) NOT NULL DEFAULT '''' AFTER `service_phone`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=@tbl AND column_name='share_image')=0,
  'ALTER TABLE `tenant_mobile_configs` ADD COLUMN `share_image` varchar(500) NOT NULL DEFAULT '''' AFTER `share_title`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=@tbl AND column_name='tabbar_style')=0,
  'ALTER TABLE `tenant_mobile_configs` ADD COLUMN `tabbar_style` json DEFAULT NULL AFTER `tabbar_json`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
