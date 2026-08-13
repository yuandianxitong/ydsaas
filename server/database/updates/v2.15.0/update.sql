-- v2.15.0 装修自定义页面：diy_pages 增 page_key + 换唯一键
ALTER TABLE `diy_pages`
  ADD COLUMN `page_key` varchar(64) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '' COMMENT '页面标识(slug);home固定home' AFTER `page_type`;

UPDATE `diy_pages` SET `page_key`='home' WHERE `page_type`='home' AND (`page_key`='' OR `page_key` IS NULL);

ALTER TABLE `diy_pages` DROP INDEX `uk_tenant_page_platform`;
ALTER TABLE `diy_pages` ADD UNIQUE KEY `uk_tenant_pagekey_platform` (`tenant_id`,`page_key`,`platform`);
