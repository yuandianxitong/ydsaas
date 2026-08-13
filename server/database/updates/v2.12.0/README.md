# v2.12.0 升级说明

## 变更
装修编辑器升级为可视化三栏拖拽编辑器（组件样式 componentStyle + 撤销/重做 + 版本历史）。

## 升级步骤
1. 执行 `update.sql`（建 `diy_page_versions` 表 + 模板租户版本权限）：
   ```bash
   mysql -u<user> -p <db> < update.sql
   ```
2. 为存量租户补齐版本按钮菜单（幂等）：
   ```bash
   php think saas:decorate-menu-reconcile
   ```

## 说明
- componentStyle 存在组件 props 内，无表结构变更；旧装修数据无需迁移（无样式即默认）。
- 版本快照在每次「发布」时自动创建；「回滚」只写回草稿，需再次发布生效。
