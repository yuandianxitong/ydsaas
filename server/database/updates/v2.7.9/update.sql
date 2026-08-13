-- v2.7.9 增量 SQL：
--
-- #1 租户端「系统管理」子菜单顺序重排
-- 新顺序：系统配置(1) → 移动端配置(2) → 管理员(3) → 角色(4) → 部门(5) → 菜单(6)
--        → 数据字典(7) → 文件(8) → 通知(9) → 日志(10) → 消息(11)
--
-- #2 租户端「应用管理」子菜单图标修正
-- 原 'i-svg:appstore' / 'i-svg:plugin' 在 tenant/src/assets/icons/ 不存在 → 不显示
-- 改为 'i-svg:boxes' / 'i-svg:box'
--
-- 幂等：UPDATE 按 id 锁定，可重复执行。

UPDATE `menus` SET `sort` = 1  WHERE `id` = 100  AND `tenant_id` = 0;  -- 系统配置
UPDATE `menus` SET `sort` = 2  WHERE `id` = 1100 AND `tenant_id` = 0;  -- 移动端配置
UPDATE `menus` SET `sort` = 3  WHERE `id` = 10   AND `tenant_id` = 0;  -- 管理员管理
UPDATE `menus` SET `sort` = 4  WHERE `id` = 20   AND `tenant_id` = 0;  -- 角色管理
UPDATE `menus` SET `sort` = 5  WHERE `id` = 30   AND `tenant_id` = 0;  -- 部门管理
UPDATE `menus` SET `sort` = 6  WHERE `id` = 50   AND `tenant_id` = 0;  -- 菜单管理
UPDATE `menus` SET `sort` = 7  WHERE `id` = 60   AND `tenant_id` = 0;  -- 数据字典
UPDATE `menus` SET `sort` = 8  WHERE `id` = 70   AND `tenant_id` = 0;  -- 文件管理
UPDATE `menus` SET `sort` = 9  WHERE `id` = 80   AND `tenant_id` = 0;  -- 通知管理
UPDATE `menus` SET `sort` = 10 WHERE `id` = 110  AND `tenant_id` = 0;  -- 日志管理
UPDATE `menus` SET `sort` = 11 WHERE `id` = 120  AND `tenant_id` = 0;  -- 消息管理

UPDATE `menus` SET `icon` = 'i-svg:boxes' WHERE `id` = 1248 AND `tenant_id` = 0;  -- 应用
UPDATE `menus` SET `icon` = 'i-svg:box'   WHERE `id` = 1249 AND `tenant_id` = 0;  -- 插件

-- 注意：menus 表是租户菜单模板 + 各租户副本。tenant_id=0 是模板行。
-- 已经复制到具体租户的副本（tenant_id != 0）需要同步更新 sort / icon，否则旧租户看不到变化。
-- 如果你的 menus 表有按 tenant_id 复制的副本，把上面 12 条改成不带 tenant_id 限制：
--   UPDATE menus SET sort=1 WHERE id=100;  -- 全部租户
-- 或在租户管理后台触发"菜单同步"。
