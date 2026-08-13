# v2.31.0 升级指南

## 变更说明

新增租户个人中心装修页系统模板，包含 `user-info-card`（用户信息卡片）和 `service-menu`（服务菜单）两个默认组件。

- **模板行**：tenant_id=0 的 member 系统装修页，作为新租户初始化的来源（由 TenantInitService::copyDiyPages 自动复制）
- **存量租户回填**：为所有已有租户自动补充 member 系统装修页，确保视觉一致性

## 升级方式

**仅需执行本 SQL 文件，勿使用 `php think migrate:run`：**

```bash
# 开发环境
mysql -h127.0.0.1 -uroot -proot dev007_framework_saas < server/database/updates/v2.31.0/update.sql

# 或使用 Docker
docker exec framework-saas-mysql mysql -h127.0.0.1 -uroot -proot dev007_framework_saas < server/database/updates/v2.31.0/update.sql

# 生产环境
mysql -h<your-host> -u<username> -p<password> <dbname> < server/database/updates/v2.31.0/update.sql
```

## 验证 SQL

升级完成后，运行以下 SQL 验证：

```sql
SELECT tenant_id, page_type, title FROM diy_pages WHERE page_key='member' LIMIT 5;
```

期望结果：每个租户（包括 tenant_id=0）都有一行 page_type='member'、title='个人中心' 的记录。

## 注意事项

### 幂等性 & 安全性

本脚本按唯一键 `(tenant_id, page_key='member', platform='uniapp')` 判存（包括已软删行），可以安全地重跑多次，不会重复插入或撞唯一键：

- 若某租户已存在 member 装修页（包括软删状态），脚本自动跳过，不会中止执行
- 模板行（tenant_id=0）同样采用幂等判逻
- 软删场景（存在 deleted_at 非空的 member 行）不会导致脚本报错

### 自建 member 页冲突预案（边缘场景）

如果某些存量租户在 v2.31.0 发布前已自建过 `page_key='member'` 的自定义页（4B 之前 assertSlug 未挡住 member），本脚本会跳过该租户——该租户的自建页继续工作，系统 member 页缺失。

**排查方法**：若发现某租户的个人中心页失效，可运行以下 SQL 检查是否有自建的 member 页：

```sql
SELECT tenant_id FROM diy_pages WHERE page_key='member' AND page_type='custom';
```

若有结果，这些租户的自建 member 页将优先级更高，阻止系统 member 页的插入。建议在后台手工删除或重命名这些自建页，然后重新执行本升级脚本。
