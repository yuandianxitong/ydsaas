-- v2.7.2 增量 SQL：
--
-- 主要内容：根因修 PlatformAdmin::getPermissions() 含 type=2（代码侧改动）。
--           不需要 schema 改动 —— 已有 platform_menus 数据直接正确生效。
--
-- 附带：重新执行 v2.6.7 + v2.7.1 漏掉的 platform_menus seed 行
--       （那两次 update.sql 写错表名 → 历史上从未成功执行过 → 这里
--        一次性幂等补齐，已有 menu_id 会被 INSERT IGNORE 跳过）。
--
-- 影响范围：原来非超管即使分到对应菜单也调不通 list/view 接口 —— 代码改动后
-- type=2 菜单行的 permission 字段（如 plan.view / platform.plugin.list /
-- platform.refund.list 等 20 个）自动纳入 hasPermission 集合。无需新增 seed。

-- v2.6.7 漏 seed 补齐
INSERT IGNORE INTO `platform_menus` (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `sort`, `permission`, `type`, `hidden`, `status`, `created_at`, `updated_at`) VALUES
  (247, 240, '构建日志列表', '', '', '', 9,  'platform.plugin_build.list',    3, 0, 1, NOW(), NOW()),
  (248, 240, '构建日志详情', '', '', '', 10, 'platform.plugin_build.detail',  3, 0, 1, NOW(), NOW());

-- v2.7.1 漏 seed 补齐（套餐 CUD 按钮）
INSERT IGNORE INTO `platform_menus` (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `sort`, `permission`, `type`, `hidden`, `status`, `created_at`, `updated_at`) VALUES
  (4, 3, '新建套餐', '', '', '', 1, 'platform.plan.create', 3, 0, 1, NOW(), NOW()),
  (6, 3, '更新套餐', '', '', '', 2, 'platform.plan.update', 3, 0, 1, NOW(), NOW()),
  (7, 3, '删除套餐', '', '', '', 3, 'platform.plan.delete', 3, 0, 1, NOW(), NOW());
