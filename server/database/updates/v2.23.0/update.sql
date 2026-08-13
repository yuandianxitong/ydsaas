-- v2.23.0 核心清理与关系重排：公告端到端移除、协议收进配置、反馈迁入设置·其他
-- 幂等：可重复执行。menus/permissions 主键为全局自增 id，一律按 name（每租户内）匹配。

-- 1) 为所有租户补 2 条协议配置（缺则插，存在则跳过）
INSERT INTO `system_configs`
  (`tenant_id`, `config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `sort_order`, `status`, `created_at`, `updated_at`)
SELECT t.`tenant_id`, 'agreement_user_agreement', '', 'agreement', 'richtext', '用户协议', '注册/登录页展示的用户协议正文', 1, 1, NOW(), NOW()
FROM (SELECT DISTINCT `tenant_id` FROM `system_configs`) t
WHERE NOT EXISTS (
  SELECT 1 FROM `system_configs` s
  WHERE s.`tenant_id` = t.`tenant_id` AND s.`config_key` = 'agreement_user_agreement'
);

INSERT INTO `system_configs`
  (`tenant_id`, `config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `sort_order`, `status`, `created_at`, `updated_at`)
SELECT t.`tenant_id`, 'agreement_privacy_policy', '', 'agreement', 'richtext', '隐私政策', '注册/登录页展示的隐私政策正文', 2, 1, NOW(), NOW()
FROM (SELECT DISTINCT `tenant_id` FROM `system_configs`) t
WHERE NOT EXISTS (
  SELECT 1 FROM `system_configs` s
  WHERE s.`tenant_id` = t.`tenant_id` AND s.`config_key` = 'agreement_privacy_policy'
);

-- 2) 迁移既有协议正文 agreements → system_configs（仅当 agreements 表存在；幂等/可重复执行安全）
SET @has_agreements := (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'agreements' AND table_type = 'BASE TABLE');
SET @mig_sql := IF(@has_agreements > 0,
  'UPDATE `system_configs` s JOIN `agreements` a ON a.`tenant_id` = s.`tenant_id` AND s.`config_key` = CONCAT(''agreement_'', a.`code`) SET s.`config_value` = a.`content`, s.`config_name` = COALESCE(NULLIF(a.`title`, ''''), s.`config_name`), s.`updated_at` = NOW() WHERE a.`code` IN (''user_agreement'', ''privacy_policy'') AND (s.`config_value` IS NULL OR s.`config_value` = '''')',
  'SELECT 1');
PREPARE mig_stmt FROM @mig_sql; EXECUTE mig_stmt; DEALLOCATE PREPARE mig_stmt;

-- 3) 反馈菜单迁入各租户的 SettingsOther 之下
UPDATE `menus` m
JOIN `menus` p ON p.`tenant_id` = m.`tenant_id` AND p.`name` = 'SettingsOther'
SET m.`parent_id` = p.`id`,
    m.`name`      = 'SystemFeedback',
    m.`path`      = '/system/feedback',
    m.`component` = '/system/feedback/index',
    m.`sort`      = 10,
    m.`updated_at`= NOW()
WHERE m.`name` = 'ContentFeedback';

-- 4) 内容菜单（Content）redirect/permission 改指向文章栏目
UPDATE `menus`
SET `redirect`   = '/content/article-category',
    `permission` = 'article_category.list',
    `updated_at` = NOW()
WHERE `name` = 'Content';

-- 5) 删除协议/公告菜单的子按钮（按父菜单 name 关联）
DELETE c FROM `menus` c
JOIN `menus` p ON p.`id` = c.`parent_id` AND p.`tenant_id` = c.`tenant_id`
WHERE p.`name` IN ('ContentAgreement', 'ContentAnnouncement');

-- 6) 删除协议/公告菜单本体
DELETE FROM `menus` WHERE `name` IN ('ContentAgreement', 'ContentAnnouncement');

-- 7) 删除协议/公告权限（permissions.name 存的是权限码）
DELETE FROM `permissions` WHERE `name` IN (
  'agreement', 'agreement.list', 'agreement.detail', 'agreement.create', 'agreement.update', 'agreement.delete',
  'announcement', 'announcement.list', 'announcement.detail', 'announcement.create', 'announcement.update', 'announcement.status', 'announcement.delete'
);

-- 8) 清理孤儿关联（角色↔菜单/权限、插件↔菜单）
DELETE rm FROM `role_menus` rm LEFT JOIN `menus` m ON m.`id` = rm.`menu_id` WHERE m.`id` IS NULL;
DELETE rp FROM `role_permissions` rp LEFT JOIN `permissions` pe ON pe.`id` = rp.`permission_id` WHERE pe.`id` IS NULL;
DELETE pm FROM `plugin_menus` pm LEFT JOIN `menus` m ON m.`id` = pm.`menu_id` WHERE m.`id` IS NULL;

-- 9) 删除公告表（数据已无用）与协议表（已迁入配置）
DROP TABLE IF EXISTS `announcements`;
DROP TABLE IF EXISTS `agreements`;

-- ===== v2.23.0 CMS 插件切换（接前述清理）=====
-- 说明：cms 插件经平台后台「上传插件」安装（注册+建 cms_* 表），按租户启用即自动迁移 articles→cms_*。
-- 本段只下线核心 article 菜单/权限；article 业务表在"迁移验证通过后"再删（见末尾注释）。

-- 1) 删核心文章菜单子按钮（按父菜单 name）
DELETE c FROM `menus` c
JOIN `menus` p ON p.`id` = c.`parent_id` AND p.`tenant_id` = c.`tenant_id`
WHERE p.`name` IN ('ContentArticleCategory', 'ContentArticle');

-- 2) 删核心文章菜单 + 「内容」顶级菜单
DELETE FROM `menus` WHERE `name` IN ('ContentArticleCategory', 'ContentArticle', 'ContentArticleGroup', 'Content');

-- 3) 删核心 article 权限
DELETE FROM `permissions` WHERE `name` IN (
  'article', 'article.list', 'article.detail', 'article.create', 'article.update', 'article.delete', 'article.status',
  'article_category', 'article_category.list', 'article_category.detail', 'article_category.create', 'article_category.update', 'article_category.delete'
);

-- 4) 清孤儿关联
DELETE rm FROM `role_menus` rm LEFT JOIN `menus` m ON m.`id` = rm.`menu_id` WHERE m.`id` IS NULL;
DELETE rp FROM `role_permissions` rp LEFT JOIN `permissions` pe ON pe.`id` = rp.`permission_id` WHERE pe.`id` IS NULL;

-- 5) （迁移验证通过后再执行）删 article 业务表
-- DROP TABLE IF EXISTS `articles`;
-- DROP TABLE IF EXISTS `article_categories`;
