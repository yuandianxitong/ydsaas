-- v2.10.1 租户端顶级菜单标题改名：首页/用户/应用/内容/渠道/系统
-- 覆盖所有 tenant_id（模板 tenant_id=0 + 各租户副本，故不限定 tenant_id）。
-- title = '<旧默认>' 守卫：只改仍是默认标题的行，保留租户自定义过的标题。
-- 幂等：可安全重复执行（已改过的行不再匹配旧默认）。

UPDATE `menus` SET `title` = '首页', `updated_at` = NOW()
 WHERE `parent_id` = 0 AND `name` = 'Workbench' AND `title` = '控制台';

UPDATE `menus` SET `title` = '用户', `updated_at` = NOW()
 WHERE `parent_id` = 0 AND `name` = 'User' AND `title` = '用户管理';

UPDATE `menus` SET `title` = '应用', `updated_at` = NOW()
 WHERE `parent_id` = 0 AND `name` = 'TenantPlugin' AND `title` = '应用管理';

UPDATE `menus` SET `title` = '内容', `updated_at` = NOW()
 WHERE `parent_id` = 0 AND `name` = 'Content' AND `title` = '内容管理';

UPDATE `menus` SET `title` = '渠道', `updated_at` = NOW()
 WHERE `parent_id` = 0 AND `name` = 'Channel' AND `title` = '渠道管理';

UPDATE `menus` SET `title` = '系统', `updated_at` = NOW()
 WHERE `parent_id` = 0 AND `name` = 'System' AND `title` = '系统管理';
