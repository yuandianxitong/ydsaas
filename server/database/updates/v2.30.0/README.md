# v2.30.0 数据库升级说明

## 概述

本升级为装修管理端重构，涉及菜单信息变更。将菜单「首页装修」改为「页面装修」，并调整页面组件指向为新的列表页组件。

## 变更内容

### 菜单数据变更

**菜单「首页装修」信息更新**：

| 字段 | 旧值 | 新值 | 说明 |
|------|------|------|------|
| title | 首页装修 | 页面装修 | 菜单标题更新为新的业务语义 |
| component | diy/home | diy/decorate-list | 前端组件路径指向重构后的列表页组件 |
| path | /diy/home | /diy/home | 保持不变 |
| name | DiyHome | DiyHome | 保持不变 |
| permission | diy.home.view | diy.home.view | 保持不变 |

**影响范围**：
- 模板租户（`tenant_id=0`）菜单 id 1201
- 全部存量租户对应菜单（按 `name='DiyHome'` 匹配）

**前端影响**：编辑器入口改为前端常量路由 `/diy/editor`（不经菜单），列表页通过新菜单路由 `/diy/home` 指向组件 `diy/decorate-list`。

## 升级方式

> ⚠️ **本脚本使用幂等语句，可安全重跑。**
> 
> 匹配条件包含旧 component 值（`component = 'diy/home'`），重跑时不再命中，天然幂等；覆盖模板和所有存量租户。

在完整备份数据库后，执行以下命令：

```bash
cd server
mysql -u<user> -p<password> <dbname> < database/updates/v2.30.0/update.sql
```

本版本无 migration 文件，请勿使用 `php think migrate:run` 作为升级手段（该命令不会应用本次任何变更）。

## 验证升级结果

升级完成后，运行以下检查命令验证菜单已正确更新：

```sql
SELECT `tenant_id`, `title`, `component` FROM `menus` WHERE `name` = 'DiyHome' LIMIT 5;
```

**预期结果**：
- 所有行的 `title` 为 `'页面装修'`
- 所有行的 `component` 为 `'diy/decorate-list'`
- 应包含 1 行模板数据（`tenant_id=0`）+ 各存量租户行

## 注意事项

1. **菜单幂等**：脚本使用 `WHERE name='DiyHome' AND component='diy/home'` 条件，确保重跑不会重复修改。
2. **租户隔离**：变更自动覆盖所有租户的对应菜单。
3. **前端适配**：升级后，前端应确保 `/diy/home` 路由指向 `diy/decorate-list` 组件；编辑器入口改为常量路由 `/diy/editor`。

## 回滚（如需）

若升级出现问题，可执行以下回滚：

```sql
UPDATE `menus`
SET `title` = '首页装修', `component` = 'diy/home', `updated_at` = NOW()
WHERE `name` = 'DiyHome' AND `component` = 'diy/decorate-list';
```
