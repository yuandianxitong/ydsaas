-- v2.25.0 升级脚本（幂等可重跑）
-- 内容：tenant_mobile_builds +4 列（移动端构建适配器可观测：driver / remote_job_id / artifact_url / runtime_json）
-- 纯加列，向后兼容；存量行 driver=NULL 视作历史 local 构建。

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenant_mobile_builds' AND COLUMN_NAME='driver')=0,
  'ALTER TABLE `tenant_mobile_builds` ADD COLUMN `driver` VARCHAR(20) DEFAULT NULL COMMENT ''构建所用 driver：local/docker/remote'' AFTER `artifact_path`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenant_mobile_builds' AND COLUMN_NAME='remote_job_id')=0,
  'ALTER TABLE `tenant_mobile_builds` ADD COLUMN `remote_job_id` VARCHAR(100) DEFAULT NULL COMMENT ''remote driver 的远端 job id'' AFTER `driver`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenant_mobile_builds' AND COLUMN_NAME='artifact_url')=0,
  'ALTER TABLE `tenant_mobile_builds` ADD COLUMN `artifact_url` VARCHAR(500) DEFAULT NULL COMMENT ''remote artifact 下载 URL（相对路径）'' AFTER `remote_job_id`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql := IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tenant_mobile_builds' AND COLUMN_NAME='runtime_json')=0,
  'ALTER TABLE `tenant_mobile_builds` ADD COLUMN `runtime_json` JSON DEFAULT NULL COMMENT ''driver 运行时元数据（driver/image/节点/耗时等）'' AFTER `artifact_url`', 'DO 0');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
