# v2.28.0 数据库升级说明

本版本包含插件 database/ SQL 化配套的数据库表调整。

## 1. ⚠️ 破坏性变更：旧迁移布局插件包不再可安装

v2.28.0 起，插件系统完全采用 SQL 化的 `plugins/<code>/database/` 目录方案。使用旧 `migrations/` 布局的插件包将无法被安装系统正确识别和加载。

**升级前须知**：如有依赖旧 migrations/ 布局的定制插件，需迁移至新 SQL 布局后方可继续使用。

## 2. 租户插件新增演示数据导入标记列

新增 `tenant_plugins.testdata_imported_at` 字段，用于记录该租户下每个插件的演示数据导入时间。

- 字段类型：`datetime DEFAULT NULL`
- **NULL 值含义**：该插件演示数据尚未导入
- **非 NULL 值含义**：记录该租户的该插件演示数据的首次导入时间戳

此字段支持后续插件动态数据初始化流程，避免重复导入演示数据。

## 3. 新增「导入演示数据」按钮权限（plugin.testdata）

在「插件 → 插件管理 → 已安装」（`PluginInstalled`）下新增 type=3 动作权限节点 `plugin.testdata`，供
`POST /tenantapi/plugin/:id/testdata` 接口鉴权使用。模板（tenant_id=0）与存量租户均幂等补齐。

## 升级方式

在完整备份数据库后，执行以下命令：

```bash
mysql -uroot -proot dev007_framework_saas < server/database/updates/v2.28.0/update.sql
```

本次升级为 **幂等操作**，可安全重复执行（information_schema 守卫，缺列才 ALTER）。

### 验证升级结果

```bash
mysql -uroot -proot -e "SHOW COLUMNS FROM dev007_framework_saas.tenant_plugins LIKE 'testdata%'"
```

应输出：

```
Field                 | Type     | Null | Key | Default | Extra
testdata_imported_at  | datetime | YES  |     | NULL    |
```

```bash
mysql -uroot -proot -e "SELECT tenant_id,title,permission FROM dev007_framework_saas.menus WHERE permission='plugin.testdata'"
```

应输出模板（tenant_id=0）与每个存量租户各一行 `plugin.testdata` 菜单记录。
