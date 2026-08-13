# v2.3.0 升级说明

## 概要
为租户权益模型重构铺路：
- `plugins` 表新增 `entitlement` 列（权益码，默认 = code）。
- `plugins` 表新增 `depends` 列（依赖的插件 code 列表，JSON）。

## 顺序
1. `php think migrate:run` 或手工执行 `update.sql`。
2. `php think saas:backfill-grants --dry-run` 检查 `plans.features` 是否能全部映射到 `plugin_grants`。
3. 若有 unmatched 行，人工修正（要么把 feature 改成 plugin code，要么注册对应插件）。
4. `php think saas:backfill-grants --apply` 真正写入 `plugin_grants`。
5. 待生产观察 1 - 7 天确认无回滚需要后，执行 v2.3.1（DROP COLUMN plans.features）。

## 回滚
本升级只 ADD COLUMN，回滚等于：
```sql
ALTER TABLE plugins DROP COLUMN entitlement;
ALTER TABLE plugins DROP COLUMN depends;
```
