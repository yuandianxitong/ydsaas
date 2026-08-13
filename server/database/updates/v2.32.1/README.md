# v2.32.1

## 变更

- 为平台「工作台」菜单补充 `platform.dashboard.view` 权限码，使仪表盘接口纳入平台 RBAC。
- 移除未被反馈详情接口使用的 `feedback.detail` 幽灵权限及其角色关联；详情访问统一复用 `feedback.list`。

## 升级

```bash
cd server && php think yd:update
```
