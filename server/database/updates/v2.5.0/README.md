# v2.5.0 升级说明

## 概要

移动端多租户独立编译：每个租户基于自己的权益 + 配置生成独立的 UniApp 产物，并按平台（H5 / 微信小程序 / App）独立编译。

## 改动

- **新增表 `tenant_mobile_builds`**：每条构建任务一行，状态机 queued → running → success/failed → uploaded/released。
- **`tenant_mobile_configs.wechat_upload_key_ciphertext`**：AES-256-CBC 加密存储租户的小程序上传私钥。
- **新增权限**：`mobile.build.view` / `mobile.build.create` / `mobile.build.release`。
- **租户后台菜单**：「构建记录」（挂在系统管理下）。

## 顺序

1. `php think migrate:run` 或手工执行 `update.sql`。
2. 部署时确保 Host 装有 `node` (≥ 18) 与 `pnpm`，PATH 可执行；构建期间会在 `server/runtime/mobile-builds/{tenant_id}/{build_no}/` 调 `pnpm install + pnpm run build:h5` 等。
3. 配置 cron 每天执行 `php think saas:mobile-build-prune` 清理超过 5 个的旧构建。
4. 通知租户后台用户：在「系统」→「构建记录」可触发独立构建；小程序需上传 `.key` 私钥。

## .env 新增

```
APP_KEY=base64:<32 字节随机>      # 加密 wechat_upload_key 时派生密钥（不要泄露）
MOBILE_BUILD_TIMEOUT_SEC=600      # 单次构建超时（默认 10 分钟）
MOBILE_BUILD_KEEP_N=5             # 每租户每平台保留多少个产物
```

## 回滚

```sql
DROP TABLE tenant_mobile_builds;
ALTER TABLE tenant_mobile_configs DROP COLUMN wechat_upload_key_ciphertext;
DELETE FROM permissions WHERE name IN ('mobile.build.view', 'mobile.build.create', 'mobile.build.release');
DELETE FROM menus WHERE name IN ('MobileBuilds', 'MobileBuildCreate', 'MobileBuildRelease');
```

并删除 `server/public/mobile-tenants/` 和 `server/runtime/mobile-builds/` 两个目录。
