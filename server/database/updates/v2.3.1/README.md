# v2.3.1 升级说明

## 前提
v2.3.0 已执行且 `php think saas:backfill-grants --apply` 已成功（命令 exit 0）。

## 执行前手工检查
```sql
SELECT id, name, features
FROM plans
WHERE features IS NOT NULL AND JSON_LENGTH(features) > 0;
```
预期：0 行。如果有，说明 backfill 未跑或有遗漏，**不要执行本升级**。

## 动作
DROP `plans.features` 列。该列内容已迁移到 `plugin_grants`。

## 回滚
不建议回滚。如必须：
```sql
ALTER TABLE plans ADD COLUMN features JSON DEFAULT NULL AFTER ...;
```
但 features 数据无法恢复。
