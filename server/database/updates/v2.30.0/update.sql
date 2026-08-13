-- v2.30.0 装修管理端重构（幂等可重跑）
-- 内容：菜单「首页装修」改为「页面装修」列表页（title + component 指向变更）。
-- path/name/permission 不变；编辑器改为前端常量路由 /diy/editor（不经菜单）。
-- 匹配条件带旧 component 值，重跑时不再命中，天然幂等；覆盖模板(tenant_id=0)与全部存量租户。

UPDATE `menus`
SET `title` = '页面装修', `component` = 'diy/decorate-list', `updated_at` = NOW()
WHERE `name` = 'DiyHome' AND `component` = 'diy/home';
