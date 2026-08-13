-- v2.31.0 个人中心装修页（幂等可重跑）
-- 内容：为每个租户回填 member 系统装修页行（page_type='member'，不进自定义页列表、
--      不可改删）。新租户由 TenantInitService::copyDiyPages 自动复制模板行，无需本脚本。
-- 幂等：WHERE NOT EXISTS 按 (tenant_id, page_key='member', platform='uniapp') 判存。

-- 1) 模板行（tenant_id=0）
INSERT INTO `diy_pages` (`tenant_id`,`page_type`,`page_key`,`platform`,`title`,`components_draft`,`components_published`,`page_settings`,`status`,`created_at`,`updated_at`)
SELECT 0, 'member', 'member', 'uniapp', '个人中心',
 '[{"id":"seed-member-user","type":"user-info-card","props":{"show_assets":true}},{"id":"seed-member-menu","type":"service-menu","props":{"items":[]}}]',
 '[{"id":"seed-member-user","type":"user-info-card","props":{"show_assets":true}},{"id":"seed-member-menu","type":"service-menu","props":{"items":[]}}]',
 '{"background_color":""}', 1, NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `diy_pages` WHERE `tenant_id`=0 AND `page_key`='member' AND `platform`='uniapp');

-- 2) 存量租户回填（每租户一行）
INSERT INTO `diy_pages` (`tenant_id`,`page_type`,`page_key`,`platform`,`title`,`components_draft`,`components_published`,`page_settings`,`status`,`created_at`,`updated_at`)
SELECT t.`id`, 'member', 'member', 'uniapp', '个人中心',
 p.`components_draft`, p.`components_published`, p.`page_settings`, 1, NOW(), NOW()
FROM `tenants` t
JOIN `diy_pages` p ON p.`tenant_id`=0 AND p.`page_key`='member' AND p.`platform`='uniapp' AND p.`deleted_at` IS NULL
WHERE NOT EXISTS (SELECT 1 FROM `diy_pages` x WHERE x.`tenant_id`=t.`id` AND x.`page_key`='member' AND x.`platform`='uniapp');
