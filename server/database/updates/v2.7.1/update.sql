-- v2.7.1 增量 SQL：补 platform.plan.{create,update,delete} 3 个按钮权限 seed。
--
-- 配合 PlanController 新加的 #[Permission] 注解 + plan.php 路由新挂的
-- platform_permission + platform_log 中间件使用。
--
-- 没有 #1（数据 schema 改动），#1 修的是 PluginMigrationLogRepository 的写入语义，
-- 在已有 plugin_migrations 表上正常工作 —— 升级 v2.7.1 不需要任何 SQL 对 plugin_migrations 动手。
--
-- ⚠️ v2.7.2 修订：原 SQL 写错了目标表（菜单本身在 platform_menus，写成了
-- 租户的 menus 表 + 错字段名 permission_code/tenant_id）→ SQL 直接报错无法运行；
-- 没有用户数据被污染。修订后正确插入 platform_menus。
--
-- 幂等：INSERT IGNORE 跳过已存在的 menu_id。

INSERT IGNORE INTO `platform_menus` (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `sort`, `permission`, `type`, `hidden`, `status`, `created_at`, `updated_at`) VALUES
  (4, 3, '新建套餐', '', '', '', 1, 'platform.plan.create', 3, 0, 1, NOW(), NOW()),
  (6, 3, '更新套餐', '', '', '', 2, 'platform.plan.update', 3, 0, 1, NOW(), NOW()),
  (7, 3, '删除套餐', '', '', '', 3, 'platform.plan.delete', 3, 0, 1, NOW(), NOW());
