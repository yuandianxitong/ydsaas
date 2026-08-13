# v2.22.0 升级说明

## 变更
租户端「应用」一级菜单重构为「插件」中心：
- 顶级 `应用`(TenantPlugin) 改名 `插件`，默认入口 `/plugin/installed`。
- 新增两个二级分组：`插件管理`（已安装 / 可用插件 / 即将到期）、`插件市场`（插件市场 / 购买记录）。
- 下线 `/plugin/apps`、`/plugin/plugins` 两个旧页面（菜单软删除）。

## 执行
```bash
mysql -u<user> -p <database> < server/database/updates/v2.22.0/update.sql
```
幂等，可重复执行。脚本按菜单 `name` 在每个租户内匹配/挂接，不依赖全局 id。

## 影响
- 租户需重新登录（或清缓存）后才能看到新菜单（`userStore.routes` 在登录时拉取，后端菜单树可能有 `cacheRemember` 缓存）。
- 旧 `/plugin/apps`、`/plugin/plugins` 书签失效，由插件顶级 redirect 兜底到 `/plugin/installed`。
- 插件分类、购买记录依赖 v2.22.0 后端代码，需与本次发布一同上线。
