# v2.26.0 升级说明

## 变更内容

新增装修链接库功能：

- **新表** `diy_links`：租户运营位/外链/命名快捷链接库
- **新增权限**（模板租户 tenant_id=0）：`diy.link.list` / `diy.link.create` / `diy.link.update` / `diy.link.delete`
- **新增菜单**（模板租户 tenant_id=0）：链接管理页（挂载到 DiyPageGroup 下）及 3 个按钮权限菜单

## 执行方法

```bash
mysql -u root -p your_database < update.sql
```

脚本幂等，可重复执行不会报错。

## 存量租户菜单对齐

`update.sql` 只写入模板租户（tenant_id=0）。菜单按租户复制存储，存量租户不会自动获得新菜单，
需执行以下命令以模板为准把链接管理菜单与 `diy.link.*` 权限补齐到所有存量租户（幂等、只增不删）：

```bash
php think saas:menu-sync            # 全部存量租户
php think saas:menu-sync --dry-run  # 先预演，确认将补齐的数量
```

> 注：`saas:plugin-menu-reconcile` 只对齐插件菜单，**不能**下发核心菜单（如链接管理）；核心菜单一律用 `saas:menu-sync`。

### 零运维（推荐）

`saas:menu-sync` 带指纹门控（模板未变更时空跑跳过），适合常驻定时任务。配置一次 hourly cron 后，
以后任何核心菜单变更都会在下个周期内自动下发到存量租户，无需每次手动执行：

```cron
0 * * * * cd /path/to/server && php think saas:menu-sync >> /var/log/saas-menu-sync.log 2>&1
```
