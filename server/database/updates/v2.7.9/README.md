# v2.7.9 升级说明

## 摘要

UI 微调 + plugins 目录从主仓 untrack。

- **#1** 租户端「模块列表」创建时间列宽 160 → 180（避免显示折行）
- **#2** 租户端「系统管理」子菜单顺序重排：系统配置 → 移动端配置 → 管理员 → 角色 → 部门 → 菜单 → 数据字典 → 文件 → 通知 → 日志 → 消息
- **#3** 租户端「应用管理」子菜单图标修正：原 `i-svg:appstore` / `i-svg:plugin` 不在 `tenant/src/assets/icons/` → 改为 `i-svg:boxes` / `i-svg:box`
- **#4** `server/plugins/{mall,points-exchange}` 从主仓 untrack —— 作者将自研付费应用插件，目录走私有路径

## 升级步骤

### 1. 执行增量 SQL（同步存量 menus 表）

```bash
mysql -u<user> -p<pass> <db_name> < server/database/updates/v2.7.9/update.sql
```

幂等可重跑。若你的 `menus` 表已为不同 `tenant_id` 复制过模板行（即每个租户都有一份副本），需要把 SQL 里所有的 `AND tenant_id = 0` 去掉，让 UPDATE 命中全部租户副本：

```sql
UPDATE menus SET sort = 1 WHERE id = 100;
-- 等等...
```

或在租户管理后台触发「菜单同步」（如有该功能）。

### 2. 前端

`pnpm build` 即可（仅 `system/menu/index.vue` 1 行 width 调整）。

## #4 mall / points-exchange untrack 说明

- 本地的 `server/plugins/mall/` 与 `server/plugins/points-exchange/` 目录保留不动
- 主仓后续 `git pull` / 新 clone 不会再带这两个目录
- `server/.gitignore:18` 早就有 `/plugins/*` + `!/plugins/.gitkeep`，新写的付费插件目录会被自动忽略
- 后端测试 `MallProductServiceTest` 加了守护：检测到 `plugins/mall/` 不存在则整套 `markTestSkipped`，CI 不会因此 fail
- `PluginAppInstallTest` 早就有 `markTestIncomplete` 守护 mall zip fixture 不在的情况

如果团队成员需要 mall / points-exchange 作为参考实现，可单独 git clone 到 `server/plugins/` 下：

```bash
# 例（假设你把 demo 放在独立仓）
cd server/plugins
git clone <demo-mall-repo> mall
git clone <demo-points-exchange-repo> points-exchange
```

## 后端测试

- 有本地 mall：**457/457** 测试通过
- 无本地 mall：MallProductServiceTest 整套 skipped → **测试数减 1，0 failure，0 risky** 保持
