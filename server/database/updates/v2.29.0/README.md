# v2.29.0 数据库升级说明

## 概述
本升级为素材库新增分类树功能，引入 `file_categories` 表用于管理文件的层级分类，并为 `files` 表补充 `category_id` 列关联分类。同时补齐缺失的权限点，并完成存量文件分组到分类的迁移。

## 1. 新表 file_categories

**表结构**：
```sql
CREATE TABLE `file_categories` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `parent_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '父分类ID，0=顶级',
  `name` varchar(100) COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '分类名称',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_parent` (`tenant_id`,`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='文件分类表';
```

**用途**：按租户隔离存储素材库分类树，支持多级分类；`parent_id=0` 表示顶级分类。

## 2. files 表新增 category_id 列

**新增列**：
```sql
`category_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '分类ID，0=未分类'
```

**新增索引**：
```sql
KEY `idx_tenant_category` (`tenant_id`,`category_id`)
```

**语义**：每个文件关联一个分类ID；`category_id=0` 表示文件未分类（孤立状态）。

## 3. 权限与菜单种子

### 3.1 权限补齐
新增/补齐以下 4 个权限（涉及 `system.file-category.*` 和缺失的 `system.file.update`）：

| 权限名 | 标题 | 描述 |
|--------|------|------|
| system.file.update | 编辑文件 | 重命名/移动文件（历史缺行补齐） |
| system.file-category.create | 新建文件分类 | 创建素材分类 |
| system.file-category.update | 编辑文件分类 | 重命名素材分类 |
| system.file-category.delete | 删除文件分类 | 删除素材分类 |

**模板种子**（`tenant_id=0`）已包含在 `init.sql` 中（全新安装显式 id=192-195）；升级时，`update.sql` 不显式指定 id（改靠自增，避免与存量库已占用的自增位冲突），以幂等方式插入模板行，然后复制给所有存量租户。

### 3.2 菜单按钮
在文件管理页（id=70）下新增 3 个操作按钮（type=3）：

| 标题 | 权限 | 排序 |
|------|------|------|
| 新建分类 | system.file-category.create | 3 |
| 编辑分类 | system.file-category.update | 4 |
| 删除分类 | system.file-category.delete | 5 |

`init.sql` 中为全新安装显式指定 id=1251-1253；`update.sql` 中同样不显式指定 id，改靠自增。

**存量租户菜单补齐**：运行 `php think saas:menu-sync` 自动检测模板变更并补齐。

## 4. 存量分组迁移

### 4.1 分组 → 分类
升级时自动扫描 `files` 表中所有不同的 `group` 值，为每个 `(tenant_id, group)` 组合在 `file_categories` 中创建一级分类（`parent_id=0`）。

**规则**：
- 跳过 `group IS NULL` 或 `group=''` 或 `group='默认'`（视为未分类，不生成分类）
- 仅对 `deleted_at IS NULL` 的文件扫描

### 4.2 文件关联分类
完成分类创建后，自动将所有 `category_id=0` 的文件通过 group 名称匹配到对应分类，填充 `category_id` 字段。

## 升级方式

> ⚠️ **本脚本仅在升级时执行一次，业务运行后请勿重跑。**
> 第 5/6 步（存量分组迁移、文件挂接分类）结构上幂等（不会重复插入分类、不会重复报错），但**语义上不是幂等**：一旦升级后业务已运行，用户可能已手动把文件移出分类挂回未分类（`category_id=0`）。此时若重跑本脚本，第 6 步会重新按 `group` 字段把这些文件挂回分类，覆盖用户此后的手动整理结果。脚本中的 `WHERE NOT EXISTS` / `information_schema` 守卫防的是**升级过程中断续跑**（保证中断后能安全续跑到底），而不是日常任意时间点的重复执行。

在完整备份数据库后，执行以下命令：

```bash
cd server
mysql -u<user> -p<password> <dbname> < database/updates/v2.29.0/update.sql
```

本版本无 migration 文件，请勿使用 `php think migrate:run` 作为升级手段（该命令不会应用本次任何变更）。

### 升级后操作

**1. 菜单补齐**（将新按钮同步给存量租户）：
```bash
php think saas:menu-sync
```

若要查看变更预览而不应用，加 `--dry-run` 参数：
```bash
php think saas:menu-sync --dry-run
```

**2. （可选）指定租户补齐菜单**：
```bash
php think saas:menu-sync --tenant=<tenant_id>
```

## 验证升级结果

升级完成后，运行以下检查命令：

```sql
-- 检查 files 表是否有 category_id 列
SHOW COLUMNS FROM `files` LIKE 'category_id';

-- 检查是否成功创建 file_categories 表
SELECT COUNT(*) FROM `file_categories`;

-- 检查权限是否已补齐
SELECT `name` FROM `permissions` WHERE `tenant_id`=0 AND `name` LIKE 'system.file-category.%';
```

**预期结果**：
- `files.category_id` 列存在（int(11) unsigned, NOT NULL, DEFAULT 0）
- `file_categories` 表存在，行数取决于存量分组数
- 4 条权限记录（`system.file.update` / `system.file-category.create` / `system.file-category.update` / `system.file-category.delete`）在 permissions 表中（id 由自增分配，存量库与全新安装的具体 id 值可能不同）

## 回滚（如需）

若升级出现问题，可执行以下回滚（需在备份基础上）：

```sql
-- 删除新表
DROP TABLE IF EXISTS `file_categories`;

-- 删除 files.category_id 列及索引
ALTER TABLE `files` DROP KEY `idx_tenant_category`;
ALTER TABLE `files` DROP COLUMN `category_id`;

-- 删除新权限（可选，保留不影响；按 name 匹配，update.sql 未显式指定 id）
DELETE FROM `permissions` WHERE `name` IN ('system.file.update','system.file-category.create','system.file-category.update','system.file-category.delete');

-- 删除新菜单按钮（可选；按 permission 匹配）
DELETE FROM `menus` WHERE `permission` IN ('system.file-category.create','system.file-category.update','system.file-category.delete');
```

## 注意事项

1. **结构幂等，语义非幂等**：升级脚本使用 `CREATE TABLE IF NOT EXISTS` 和 `WHERE NOT EXISTS` 条件，中断后可安全续跑到底、不会重复插入或报错；但**不代表可在任意时间点重复执行**——业务运行后重跑第 6 步会把用户手动移出分类的文件重新按 `group` 挂回分类。详见「升级方式」一节的警示。
2. **租户隔离**：`file_categories` 表包含 `tenant_id`，分类数据完全按租户隔离；每个租户运行菜单同步后获得独立的按钮。
3. **性能**：存量分组迁移涉及全表扫描和批量 UPDATE，建议在非业务高峰期执行。
4. **'默认' 分组处理**：现有的 `group='默认'` 文件不会生成分类，保持 `category_id=0` 状态（未分类）。

## 相关文档

- 菜单同步工具 — `php think saas:menu-sync` 详细用法
- 权限系统 — 权限管理架构
