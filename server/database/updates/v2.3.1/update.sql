-- v2.3.1：在 saas:backfill-grants --apply 成功后再执行。
-- 删除 plans.features 列：其内容已迁移到 plugin_grants。

-- 前置检查（运维手工跑）：确认所有 plans.features 都已转 grant
--   SELECT id, name, features FROM plans WHERE features IS NOT NULL AND JSON_LENGTH(features) > 0;
--   预期：0 行。

ALTER TABLE `plans` DROP COLUMN `features`;
