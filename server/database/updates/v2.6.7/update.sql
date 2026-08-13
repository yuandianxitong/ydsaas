-- v2.6.7 增量 SQL：补 plugin_build.list / plugin_build.detail 按钮权限
--
-- 历史原因：v2.5.0 引入 PluginBuildController 时声明了
-- #[Permission('platform.plugin_build.list')] / .detail，但 seed 漏写。
-- v2.6.7 同时给 platform plugin 路由挂上 platform_permission 中间件，原本被
-- 「无中间件 → 不校验权限」掩盖的漏权问题暴露 → 此 patch 补齐 seed。
--
-- ⚠️ v2.7.2 修订：原 SQL 写错了目标表（菜单本身在 platform_menus，写成了
-- 租户的 menus 表 + 错字段名）→ SQL 直接报「Unknown column」无法运行；
-- 没有用户数据被污染。修订后正确插入 platform_menus。
--
-- 幂等：用 INSERT IGNORE 跳过已存在的 menu_id；新装直接走 init.sql 即可。

INSERT IGNORE INTO `platform_menus` (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `sort`, `permission`, `type`, `hidden`, `status`, `created_at`, `updated_at`) VALUES
  (247, 240, '构建日志列表', '', '', '', 9,  'platform.plugin_build.list',    3, 0, 1, NOW(), NOW()),
  (248, 240, '构建日志详情', '', '', '', 10, 'platform.plugin_build.detail',  3, 0, 1, NOW(), NOW());
