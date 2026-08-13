-- v2.3.0 升级 SQL：为权益模型重构铺路
-- 1. plugins 表加 entitlement 列并回填
ALTER TABLE `plugins` ADD COLUMN `entitlement` VARCHAR(80) NOT NULL DEFAULT '' AFTER `code`;
ALTER TABLE `plugins` ADD INDEX `idx_entitlement` (`entitlement`);
UPDATE `plugins` SET `entitlement` = `code` WHERE `entitlement` = '';

-- 2. plugins 表加 depends 列（供 uninstall 反向检查 + 依赖图查询）
ALTER TABLE `plugins` ADD COLUMN `depends` JSON DEFAULT NULL AFTER `entitlement`;

-- 注意：plans.features 迁移由 saas:backfill-grants 命令完成，不在本 SQL 中。
-- DROP COLUMN plans.features 在 v2.3.1 单独执行，给运维一个明确的"已确认迁完"标志点。
