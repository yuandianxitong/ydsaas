# v2.26.2 升级说明

## 数据库变更

平台后台「应用管理」一级菜单图标由 `i-svg:plug` 改为 `i-svg:blocks`。

执行：

```bash
mysql -u<user> -p <database> < update.sql
```

`update.sql` 幂等，可重复执行（仅当图标仍为旧值 `i-svg:plug` 时更新）。

## 代码变更（无需手动操作）

- 本地插件图标接口 `/plugin-icon/<code>/<file>`：未安装的本地插件（随仓库自带、磁盘有
  `plugin.json` 但 DB 未登记）现在也能显示 manifest 声明的图标。原先仅对已启用的 DB 插件
  放行，导致本地未安装插件图标 404。
- 平台「应用市场」卡片图标改为铺满图标容器（`.icon` 宽高 100%）。
