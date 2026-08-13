# v2.25.0 升级说明

## 内容
移动端构建适配器可观测：`tenant_mobile_builds` 新增 4 列
`driver` / `remote_job_id` / `artifact_url` / `runtime_json`。

## 升级
```bash
mysql -u<user> -p<pass> <db> < server/database/updates/v2.25.0/update.sql
```
幂等可重跑（information_schema 守卫，缺列才 ALTER）。

## 兼容性
纯加列、全部可空，向后兼容。存量构建行 `driver=NULL` 视作历史 local 构建。
无需数据回填。
