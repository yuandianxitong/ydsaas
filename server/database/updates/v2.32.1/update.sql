-- ============================================================
-- framework-saas v2.32.1 升级 SQL
-- 平台工作台权限：为现有菜单补 platform.dashboard.view
-- ============================================================

UPDATE `platform_menus`
SET `permission` = 'platform.dashboard.view', `updated_at` = NOW()
WHERE `id` = 1
  AND `path` = 'dashboard'
  AND (`permission` = '' OR `permission` IS NULL);

-- 反馈详情接口复用 feedback.list；移除未被后端使用的幽灵权限及其角色关联。
DELETE rp
FROM `role_permissions` rp
INNER JOIN `permissions` p ON p.id = rp.permission_id
WHERE p.name = 'feedback.detail';

UPDATE `permissions`
SET `status` = 0, `deleted_at` = NOW(), `updated_at` = NOW()
WHERE `name` = 'feedback.detail'
  AND `deleted_at` IS NULL;
