# v2.26.1 升级说明

## 变更内容

修复平台管理端系统配置菜单路由出现 `/dir-30/...` 前缀的问题：

- 前端 `platform/src/router/index.ts` 将菜单 path 统一规整为绝对路径，目录子菜单不再嵌套到父级合成的 `/dir-<id>` 下（对所有空 path 目录生效：订单管理/系统配置/开发工具）。
- `platform_menus` 种子归一：系统设置/审计日志/平台公告 三个子页 path 加 `system/` 前缀，最终为 `/platform/system/config` 等。

## 执行方法

```bash
mysql -u root -p your_database < update.sql
```

脚本按 `id + 旧 path` 匹配，幂等可重复执行。

## ⚠️ 执行后必须清菜单缓存

平台菜单树有缓存（`PlatformMenuRepository`，tag `platform_menu`）。执行 SQL 后清缓存才会生效：

```bash
php think clear        # 或在平台后台触发一次菜单保存/缓存清理
```

前端 `platform/` 需重新构建（路由生成逻辑变更）。
