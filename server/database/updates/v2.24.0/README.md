# framework-saas v2.24.0 升级说明

## 概述

装修模块前端命名统一为 **diy**（与后端早已存在的 `/tenantapi/diy/*` 路由、`diy.*` 权限码对齐）。

- 前端：`views/decorate/` → `views/diy/`，路由 `/decorate/*` → `/diy/*`，`api/decorate.ts(decorateApi)` → `api/diy.ts(diyApi)`。
- 后端：`SaasDecorateMenuReconcile`（`saas:decorate-menu-reconcile`）→ `SaasDiyMenuReconcile`（`saas:diy-menu-reconcile`）。
- 装修左侧组件图标改用本地 svg 图标库（`src/assets/icons/diy-*.svg` → `i-svg:diy-*`）。

## 数据库变更

### `menus` 表（无结构变更，仅数据重命名）

将装修一级目录与 6 个子菜单的 `name` / `path` / `component` / `redirect` 由 `Decorate*` / `/decorate/*` / `decorate/*` 改为 `Diy*` / `/diy/*` / `diy/*`：

| 旧 name | 新 name | 新 path | 新 component |
|---|---|---|---|
| Decorate | Diy | /diy | (LAYOUT, redirect=/diy/home) |
| DecorateHome | DiyHome | /diy/home | diy/home |
| DecorateTabbar | DiyTabbar | /diy/tabbar | diy/tabbar |
| DecorateTheme | DiyTheme | /diy/theme | diy/theme |
| DecorateBasic | DiyBasic | /diy/basic | diy/basic |
| DecorateBuild | DiyBuild | /diy/build | diy/build |
| DecoratePages | DiyPages | /diy/pages | diy/pages |

权限码（`diy.*`）本就为 diy，无需变更。

## 装修菜单重构（二级分组）

本版同时把装修菜单由「装修 > 6 个平铺子项」重构为二级分组：

- 装修
  - 页面装修（`DiyPageGroup`）→ 首页装修 / 自定义页面 / 底部导航 / 主题风格
  - 发布管理（`DiyPublishGroup`）→ 基础设置 / 打包发布

`DiyTheme` 标题由「主题」改为「主题风格」。该重构含每租户动态目录 id，**由 `saas:diy-menu-reconcile` 命令完成**（建分组目录 + 把叶子改挂到对应分组，幂等可重入），不放在 update.sql。

## 升级步骤

```bash
# 1. 执行增量 SQL（存量租户菜单 decorate→diy 重命名，幂等）
mysql -u<user> -p <db> < server/database/updates/v2.24.0/update.sql

# 2. 对齐装修菜单结构（重命名后必跑：建二级分组 + 叶子改挂 + 主题→主题风格，幂等）
php think saas:diy-menu-reconcile
```

## 幂等性

- `update.sql` 的每条 UPDATE 仅命中仍为旧名（`Decorate*`）的行；重复执行第二次匹配 0 行。
- `saas:diy-menu-reconcile`：分组目录按 name 判存、叶子按 name 定位后只在 parent/sort/title 不一致时更新，重复执行不产生重复行。

## 移动端配置字段对齐（Spec A）

`tenant_mobile_configs` 增字段（向后兼容，默认空）：`theme_colors`/`tabbar_style`(JSON)、`app_intro`/`service_type`/`service_phone`/`share_title`/`share_image`(标量)、`tabbar_json` item 增 `sel_label`/`badge`。update.sql 用 information_schema 守卫逐列幂等。
