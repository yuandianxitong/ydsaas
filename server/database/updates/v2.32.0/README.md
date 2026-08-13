# v2.32.0

## 变更

- `tenant_mobile_configs` 新增：
  - `wechat_upload_version`：小程序上传版本号（默认 `1.0.0`，上传成功后自动 patch +1）
  - `wechat_upload_desc`：项目备注（默认「租户后台发布」）
- 装修菜单新增「启动与首页」(`DiyLaunch`，`/diy/launch`)，从底部导航迁出启动入口配置；并重排页面装修分组 sort。
- 移动端软配置（首页装修 / 主题 / tabBar / 启动入口）改为运行时拉取，保存/发布后刷新即可生效（结构变更仍需打包）。

## 升级

```bash
cd server && php think yd:update
# 可选：对齐存量装修菜单（含 DiyLaunch）
php think saas:diy-menu-reconcile
```
