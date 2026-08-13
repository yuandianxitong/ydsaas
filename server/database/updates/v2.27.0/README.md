# v2.27.0 数据库升级说明

本版本新增租户 PC 前台配置能力：

- 新增 `tenant_pc_configs` 表，用于保存租户 PC 站点信息、首页、导航、SEO 与登录开关。
- 为所有存量租户写入默认 PC 配置。
- 为所有存量租户补充 `platform=pc` 的默认首页装修页。
- 修正早期保存为 `home_page='/'` 的 CMS PC 首页配置，统一迁移到 `/cms`。
- 新增租户后台权限 `pc.config.view` / `pc.config.update`。
- 新增租户后台菜单 `装修 / 发布管理 / PC端配置`。

升级方式：在备份数据库后执行 `update.sql`。执行完成后建议运行：

```bash
php think saas:menu-sync --update-meta
```
