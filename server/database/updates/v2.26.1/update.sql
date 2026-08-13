-- v2.26.1 升级脚本（幂等可重跑）
-- 修复平台菜单系统配置子页路径：归一到 system/* 前缀，消除前端 /dir-30 嵌套
-- （前端 createRouteRecord 已统一规整为绝对路径；本脚本仅修正 3 条不一致的种子 path）

UPDATE `platform_menus` SET `path`='system/config',       `updated_at`=NOW() WHERE `id`=31 AND `path`='config';
UPDATE `platform_menus` SET `path`='system/audit',        `updated_at`=NOW() WHERE `id`=35 AND `path`='audit';
UPDATE `platform_menus` SET `path`='system/announcement', `updated_at`=NOW() WHERE `id`=37 AND `path`='announcement';
