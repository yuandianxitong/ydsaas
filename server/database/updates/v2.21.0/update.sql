-- v2.21.0 菜单改造：用户→会员、系统→设置 + 二级分组重组
-- 幂等：可重复执行。
--
-- 重要：menus 主键是全局 `id`（AUTO_INCREMENT），并非 (tenant_id,id) 复合键。
-- 存量租户的菜单由 TenantInitService 复制模板时分配各自的自增 id，跨租户 id 不同，
-- 但 `name` 在每个租户内稳定。因此本脚本一律按 `name` 在「每个租户内」匹配/挂接，
-- 分组目录用自增 id 插入（不写死 id），靠 (tenant_id,name) 去重保证幂等。

-- 1) 顶级改名（按 name 命中各租户顶级行；顶级 parent_id=0）
UPDATE `menus` SET `title` = '设置' WHERE `name` = 'System' AND `parent_id` = 0;
UPDATE `menus` SET `title` = '会员' WHERE `name` = 'User'   AND `parent_id` = 0;

-- 2) 为每个租户插入 5 个分组目录（自增 id；parent 取该租户自己的 设置/会员 顶级 id）
--    NOT EXISTS 保证幂等：同租户已存在同名目录则不重复插入。
INSERT INTO `menus`
  (`tenant_id`,`parent_id`,`type`,`title`,`name`,`path`,`component`,`redirect`,`icon`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`external_link`,`breadcrumb`,`active_menu`,`meta`,`status`,`sort`,`created_at`,`updated_at`)
SELECT p.`tenant_id`, p.`id`, 1, g.`title`, g.`name`, '', NULL, NULL, g.`icon`, NULL, 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, g.`sort`, NOW(), NOW()
FROM (
  SELECT 'System' AS parent_name, '系统设置' AS title, 'SettingsSystem' AS name, 'i-svg:settings'    AS icon, 1 AS sort
  UNION ALL SELECT 'System', '权限',     'SettingsPerm',  'i-svg:lock',        2
  UNION ALL SELECT 'System', '其他',     'SettingsOther', 'i-svg:layout-list', 3
  UNION ALL SELECT 'User',   '会员管理', 'MemberManage',  'i-svg:user',        1
  UNION ALL SELECT 'User',   '资产记录', 'MemberAssets',  'i-svg:wallet',      2
) g
JOIN `menus` p ON p.`name` = g.`parent_name` AND p.`parent_id` = 0
WHERE NOT EXISTS (
  SELECT 1 FROM `menus` m WHERE m.`tenant_id` = p.`tenant_id` AND m.`name` = g.`name`
);

-- 3) reparent 现有叶子到各租户自己的分组目录（同租户内按 name JOIN，幂等）
UPDATE `menus` c JOIN `menus` g ON g.`tenant_id` = c.`tenant_id` AND g.`name` = 'SettingsSystem'
  SET c.`parent_id` = g.`id`
  WHERE c.`name` IN ('SystemConfig','SystemDictionary','SystemFile','SystemMenu');

UPDATE `menus` c JOIN `menus` g ON g.`tenant_id` = c.`tenant_id` AND g.`name` = 'SettingsPerm'
  SET c.`parent_id` = g.`id`
  WHERE c.`name` IN ('SystemAdmin','SystemRole','SystemDepartment');

UPDATE `menus` c JOIN `menus` g ON g.`tenant_id` = c.`tenant_id` AND g.`name` = 'SettingsOther'
  SET c.`parent_id` = g.`id`
  WHERE c.`name` = 'SystemNotification';

UPDATE `menus` c JOIN `menus` g ON g.`tenant_id` = c.`tenant_id` AND g.`name` = 'MemberManage'
  SET c.`parent_id` = g.`id`, c.`title` = '会员列表'
  WHERE c.`name` = 'UserList';

UPDATE `menus` c JOIN `menus` g ON g.`tenant_id` = c.`tenant_id` AND g.`name` = 'MemberAssets'
  SET c.`parent_id` = g.`id`
  WHERE c.`name` IN ('UserBalanceLog','UserPointsLog');
