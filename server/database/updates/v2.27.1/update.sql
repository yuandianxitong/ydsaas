-- v2.27.1 补齐移动端打包发布权限节点（mobile.build.view / create / release）
-- 背景：MobileBuildController 接口要求 mobile.build.* 权限，但菜单模板从未种入对应节点，
--       非超管角色即使被授予「打包发布」菜单（mobile.config.view）也无法调用打包接口（403）。

-- 1) 模板权限（tenant_id=0）
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0,'mobile.build.view','查看打包记录','系统管理','查看移动端打包记录','admin',1,189,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='mobile.build.view');

INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0,'mobile.build.create','发起打包','系统管理','发起移动端打包构建','admin',1,190,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='mobile.build.create');

INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0,'mobile.build.release','发布应用','系统管理','发布H5/上传小程序','admin',1,191,NOW(),NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `tenant_id`=0 AND `name`='mobile.build.release');

-- 2) 复制到存量租户
INSERT INTO `permissions` (`tenant_id`,`name`,`title`,`group`,`description`,`guard_name`,`status`,`sort`,`created_at`,`updated_at`)
SELECT t.`id`, p.`name`, p.`title`, p.`group`, p.`description`, p.`guard_name`, p.`status`, p.`sort`, NOW(), NOW()
FROM `tenants` t
JOIN `permissions` p ON p.`tenant_id` = 0 AND p.`name` IN ('mobile.build.view', 'mobile.build.create', 'mobile.build.release')
WHERE t.`id` > 0
  AND NOT EXISTS (
    SELECT 1 FROM `permissions` e WHERE e.`tenant_id` = t.`id` AND e.`name` = p.`name`
  );

-- 3) 模板菜单按钮（tenant_id=0，挂「打包发布」DiyBuild 下）
INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, b.`id`, 3, '查看构建', 'mobile.build.view', 0, 1, 0, 0, 1, 1, 1, NOW(), NOW()
FROM (SELECT `id` FROM `menus` WHERE `tenant_id`=0 AND `name`='DiyBuild' LIMIT 1) b
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `tenant_id`=0 AND `permission`='mobile.build.view');

INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, b.`id`, 3, '发起构建', 'mobile.build.create', 0, 1, 0, 0, 1, 1, 2, NOW(), NOW()
FROM (SELECT `id` FROM `menus` WHERE `tenant_id`=0 AND `name`='DiyBuild' LIMIT 1) b
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `tenant_id`=0 AND `permission`='mobile.build.create');

INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT 0, b.`id`, 3, '发布应用', 'mobile.build.release', 0, 1, 0, 0, 1, 1, 3, NOW(), NOW()
FROM (SELECT `id` FROM `menus` WHERE `tenant_id`=0 AND `name`='DiyBuild' LIMIT 1) b
WHERE NOT EXISTS (SELECT 1 FROM `menus` WHERE `tenant_id`=0 AND `permission`='mobile.build.release');

-- 4) 复制到存量租户（挂各租户自己的 DiyBuild 下）
INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT b.`tenant_id`, b.`id`, 3, '查看构建', 'mobile.build.view', 0, 1, 0, 0, 1, 1, 1, NOW(), NOW()
FROM `menus` b
WHERE b.`name` = 'DiyBuild' AND b.`tenant_id` > 0
  AND NOT EXISTS (
    SELECT 1 FROM `menus` m WHERE m.`tenant_id` = b.`tenant_id` AND m.`permission` = 'mobile.build.view'
  );

INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT b.`tenant_id`, b.`id`, 3, '发起构建', 'mobile.build.create', 0, 1, 0, 0, 1, 1, 2, NOW(), NOW()
FROM `menus` b
WHERE b.`name` = 'DiyBuild' AND b.`tenant_id` > 0
  AND NOT EXISTS (
    SELECT 1 FROM `menus` m WHERE m.`tenant_id` = b.`tenant_id` AND m.`permission` = 'mobile.build.create'
  );

INSERT INTO `menus` (`tenant_id`,`parent_id`,`type`,`title`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`breadcrumb`,`status`,`sort`,`created_at`,`updated_at`)
SELECT b.`tenant_id`, b.`id`, 3, '发布应用', 'mobile.build.release', 0, 1, 0, 0, 1, 1, 3, NOW(), NOW()
FROM `menus` b
WHERE b.`name` = 'DiyBuild' AND b.`tenant_id` > 0
  AND NOT EXISTS (
    SELECT 1 FROM `menus` m WHERE m.`tenant_id` = b.`tenant_id` AND m.`permission` = 'mobile.build.release'
  );

-- 5) 平台端「移动构建监控」菜单（接口早已存在，本版本补前端页面与菜单入口）
INSERT INTO `platform_menus` (`parent_id`,`name`,`path`,`component`,`icon`,`sort`,`permission`,`type`,`hidden`,`status`,`created_at`,`updated_at`)
SELECT g.`id`, '移动构建监控', 'dev-tools/mobile-builds', 'mobile-build/index', 'i-svg:smartphone', 3, 'platform.mobile.build.view', 2, 0, 1, NOW(), NOW()
FROM (SELECT `id` FROM `platform_menus` WHERE `name`='开发工具' AND `type`=1 LIMIT 1) g
WHERE NOT EXISTS (SELECT 1 FROM `platform_menus` WHERE `permission`='platform.mobile.build.view');

INSERT INTO `platform_menus` (`parent_id`,`name`,`path`,`component`,`icon`,`sort`,`permission`,`type`,`hidden`,`status`,`created_at`,`updated_at`)
SELECT p.`id`, '强制收尾卡死任务', '', '', '', 1, 'platform.mobile.build.manage', 3, 0, 1, NOW(), NOW()
FROM (SELECT `id` FROM `platform_menus` WHERE `permission`='platform.mobile.build.view' LIMIT 1) p
WHERE NOT EXISTS (SELECT 1 FROM `platform_menus` WHERE `permission`='platform.mobile.build.manage');

-- 6) 租户 CRUD 权限拆分：此前增删改/重置密码全部共用 tenant.view（越权风险），
--    现拆为独立按钮权限。注意：升级后仅持有 tenant.view 的角色将失去写能力，
--    需在「角色管理」中按需勾选新按钮权限（超管不受影响）。
INSERT INTO `platform_menus` (`parent_id`,`name`,`path`,`component`,`icon`,`sort`,`permission`,`type`,`hidden`,`status`,`created_at`,`updated_at`)
SELECT t.`id`, '新建租户', '', '', '', 1, 'platform.tenant.create', 3, 0, 1, NOW(), NOW()
FROM (SELECT `id` FROM `platform_menus` WHERE `permission`='tenant.view' AND `type`=2 LIMIT 1) t
WHERE NOT EXISTS (SELECT 1 FROM `platform_menus` WHERE `permission`='platform.tenant.create');

INSERT INTO `platform_menus` (`parent_id`,`name`,`path`,`component`,`icon`,`sort`,`permission`,`type`,`hidden`,`status`,`created_at`,`updated_at`)
SELECT t.`id`, '编辑租户', '', '', '', 2, 'platform.tenant.update', 3, 0, 1, NOW(), NOW()
FROM (SELECT `id` FROM `platform_menus` WHERE `permission`='tenant.view' AND `type`=2 LIMIT 1) t
WHERE NOT EXISTS (SELECT 1 FROM `platform_menus` WHERE `permission`='platform.tenant.update');

INSERT INTO `platform_menus` (`parent_id`,`name`,`path`,`component`,`icon`,`sort`,`permission`,`type`,`hidden`,`status`,`created_at`,`updated_at`)
SELECT t.`id`, '删除租户', '', '', '', 3, 'platform.tenant.delete', 3, 0, 1, NOW(), NOW()
FROM (SELECT `id` FROM `platform_menus` WHERE `permission`='tenant.view' AND `type`=2 LIMIT 1) t
WHERE NOT EXISTS (SELECT 1 FROM `platform_menus` WHERE `permission`='platform.tenant.delete');

INSERT INTO `platform_menus` (`parent_id`,`name`,`path`,`component`,`icon`,`sort`,`permission`,`type`,`hidden`,`status`,`created_at`,`updated_at`)
SELECT t.`id`, '重置管理员密码', '', '', '', 4, 'platform.tenant.reset_password', 3, 0, 1, NOW(), NOW()
FROM (SELECT `id` FROM `platform_menus` WHERE `permission`='tenant.view' AND `type`=2 LIMIT 1) t
WHERE NOT EXISTS (SELECT 1 FROM `platform_menus` WHERE `permission`='platform.tenant.reset_password');

-- 7) 平台级插件禁用/启用按钮权限（挂「应用管理」下）
INSERT INTO `platform_menus` (`parent_id`,`name`,`path`,`component`,`icon`,`sort`,`permission`,`type`,`hidden`,`status`,`created_at`,`updated_at`)
SELECT g.`id`, '禁用/启用插件', '', '', '', 14, 'platform.plugin.status', 3, 0, 1, NOW(), NOW()
FROM (SELECT `id` FROM `platform_menus` WHERE `permission`='platform.plugin.list' AND `type`=2 LIMIT 1) g
WHERE NOT EXISTS (SELECT 1 FROM `platform_menus` WHERE `permission`='platform.plugin.status');

-- 8) 官方市场权限补进 platform_menus：此前整个 marketplace 权限家族只 seed 在
--    不参与鉴权的遗留 permissions 表，非超管无法被授权使用应用市场。
INSERT INTO `platform_menus` (`parent_id`,`name`,`path`,`component`,`icon`,`sort`,`permission`,`type`,`hidden`,`status`,`created_at`,`updated_at`)
SELECT g.`id`, '查看Site连接', '', '', '', 15, 'marketplace.connection.view', 3, 0, 1, NOW(), NOW()
FROM (SELECT `id` FROM `platform_menus` WHERE `permission`='platform.plugin.list' AND `type`=2 LIMIT 1) g
WHERE NOT EXISTS (SELECT 1 FROM `platform_menus` WHERE `permission`='marketplace.connection.view');

INSERT INTO `platform_menus` (`parent_id`,`name`,`path`,`component`,`icon`,`sort`,`permission`,`type`,`hidden`,`status`,`created_at`,`updated_at`)
SELECT g.`id`, '管理Site连接', '', '', '', 16, 'marketplace.connection.manage', 3, 0, 1, NOW(), NOW()
FROM (SELECT `id` FROM `platform_menus` WHERE `permission`='platform.plugin.list' AND `type`=2 LIMIT 1) g
WHERE NOT EXISTS (SELECT 1 FROM `platform_menus` WHERE `permission`='marketplace.connection.manage');

INSERT INTO `platform_menus` (`parent_id`,`name`,`path`,`component`,`icon`,`sort`,`permission`,`type`,`hidden`,`status`,`created_at`,`updated_at`)
SELECT g.`id`, '查看市场目录', '', '', '', 17, 'marketplace.catalog.view', 3, 0, 1, NOW(), NOW()
FROM (SELECT `id` FROM `platform_menus` WHERE `permission`='platform.plugin.list' AND `type`=2 LIMIT 1) g
WHERE NOT EXISTS (SELECT 1 FROM `platform_menus` WHERE `permission`='marketplace.catalog.view');

INSERT INTO `platform_menus` (`parent_id`,`name`,`path`,`component`,`icon`,`sort`,`permission`,`type`,`hidden`,`status`,`created_at`,`updated_at`)
SELECT g.`id`, '安装/升级官方应用', '', '', '', 18, 'marketplace.install', 3, 0, 1, NOW(), NOW()
FROM (SELECT `id` FROM `platform_menus` WHERE `permission`='platform.plugin.list' AND `type`=2 LIMIT 1) g
WHERE NOT EXISTS (SELECT 1 FROM `platform_menus` WHERE `permission`='marketplace.install');

INSERT INTO `platform_menus` (`parent_id`,`name`,`path`,`component`,`icon`,`sort`,`permission`,`type`,`hidden`,`status`,`created_at`,`updated_at`)
SELECT g.`id`, '轮换连接Token', '', '', '', 19, 'marketplace.connection.rotate_token', 3, 0, 1, NOW(), NOW()
FROM (SELECT `id` FROM `platform_menus` WHERE `permission`='platform.plugin.list' AND `type`=2 LIMIT 1) g
WHERE NOT EXISTS (SELECT 1 FROM `platform_menus` WHERE `permission`='marketplace.connection.rotate_token');

-- 9) 清理三个从未实现的预埋权限点位（它们位于不参与鉴权的遗留 permissions 表，仅有误导性）：
--    license 状态已随应用目录返回（catalog.view）；「同步」已触发 license 重评估（connection.manage）；
--    审计上报开关走配置文件 saas.marketplace.audit_report_enabled。
-- 含模板（tenant_id=0）与 TenantInitService 复制到各租户的副本
DELETE FROM `permissions`
WHERE `name` IN ('marketplace.license.view', 'marketplace.license.manual_renew', 'marketplace.audit.toggle');
