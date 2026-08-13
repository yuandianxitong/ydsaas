-- v2.22.0 租户插件菜单重构：应用→插件，插件管理/插件市场两组 + 5 叶子，下线 /plugin/apps
-- 幂等：可重复执行。
--
-- 重要：menus 主键是全局 `id`（AUTO_INCREMENT），并非 (tenant_id,id) 复合键。
-- 一律按 `name` 在「每个租户内」匹配/挂接；分组目录用自增 id 插入（不写死 id），
-- 靠 (tenant_id,name) NOT EXISTS 保证幂等。

-- 1) 顶级改名 + redirect（命中各租户的 TenantPlugin 顶级行）
UPDATE `menus` SET `title` = '插件', `redirect` = '/plugin/installed'
  WHERE `name` = 'TenantPlugin' AND `parent_id` = 0;

-- 2) 为每个租户插入 2 个分组目录（自增 id；parent 取该租户自己的 TenantPlugin id）
INSERT INTO `menus`
  (`tenant_id`,`parent_id`,`type`,`title`,`name`,`path`,`component`,`redirect`,`icon`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`external_link`,`breadcrumb`,`active_menu`,`meta`,`status`,`sort`,`created_at`,`updated_at`)
SELECT p.`tenant_id`, p.`id`, 1, g.`title`, g.`name`, '', NULL, NULL, g.`icon`, NULL, 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, g.`sort`, NOW(), NOW()
FROM (
  SELECT '插件管理' AS title, 'PluginManage' AS name, 'i-svg:box'   AS icon, 10 AS sort
  UNION ALL SELECT '插件市场', 'PluginMarket', 'i-svg:boxes', 20
) g
JOIN `menus` p ON p.`name` = 'TenantPlugin' AND p.`parent_id` = 0
WHERE NOT EXISTS (
  SELECT 1 FROM `menus` m WHERE m.`tenant_id` = p.`tenant_id` AND m.`name` = g.`name`
);

-- 3) 为每个租户插入 5 个叶子（自增 id；parent 取该租户自己的分组目录 id）
INSERT INTO `menus`
  (`tenant_id`,`parent_id`,`type`,`title`,`name`,`path`,`component`,`redirect`,`icon`,`permission`,`is_hidden`,`is_cache`,`is_affix`,`is_iframe`,`external_link`,`breadcrumb`,`active_menu`,`meta`,`status`,`sort`,`created_at`,`updated_at`)
SELECT g2.`tenant_id`, g2.`id`, 2, leaf.`title`, leaf.`name`, leaf.`path`, leaf.`component`, NULL, leaf.`icon`, leaf.`perm`, 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, leaf.`sort`, NOW(), NOW()
FROM (
  SELECT 'PluginManage' AS grp, '已安装'   AS title, 'PluginInstalled'  AS name, '/plugin/installed' AS path, 'plugin/installed' AS component, 'i-svg:plug'         AS icon, 'plugin.list'       AS perm, 1 AS sort
  UNION ALL SELECT 'PluginManage', '可用插件', 'PluginAvailable',  '/plugin/available', 'plugin/available', 'i-svg:unplug',       'plugin.list',       2
  UNION ALL SELECT 'PluginManage', '即将到期', 'PluginExpiring',   '/plugin/expiring',  'plugin/expiring',  'i-svg:bell-ring',    'plugin.list',       3
  UNION ALL SELECT 'PluginMarket', '插件市场', 'PluginMarketGrid', '/plugin/market',    'plugin/market',    'i-svg:layout-grid',  'plugin.list',       1
  UNION ALL SELECT 'PluginMarket', '购买记录', 'PluginOrders',     '/plugin/orders',    'plugin/orders',    'i-svg:receipt-text', 'plugin.order.list', 2
) leaf
JOIN `menus` g2 ON g2.`name` = leaf.`grp` AND g2.`parent_id` <> 0
WHERE NOT EXISTS (
  SELECT 1 FROM `menus` m WHERE m.`tenant_id` = g2.`tenant_id` AND m.`name` = leaf.`name`
);

-- 4) reparent 动作权限节点到各租户自己的「插件管理」分组目录
UPDATE `menus` c JOIN `menus` g ON g.`tenant_id` = c.`tenant_id` AND g.`name` = 'PluginManage'
  SET c.`parent_id` = g.`id`
  WHERE c.`name` IN ('TenantPluginEnable','TenantPluginDisable','TenantPluginCfgGet','TenantPluginCfgSet','TenantPluginPurchase');

-- 5) 软删除旧「应用展示页 / 插件页」叶子（按 name，每租户其 id 各异）
UPDATE `menus` SET `deleted_at` = NOW()
  WHERE `name` IN ('PluginApps','PluginPlugins') AND `deleted_at` IS NULL;
