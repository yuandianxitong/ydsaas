# v2.33.0 升级说明

## 变更

- 平台后台新增「系统配置 → 产品授权」：录入/激活官网授权码
- 权限码：`platform.license.view`、`platform.license.update`
- 菜单 id：`38`（页面）、`39`（激活按钮）

## 数据库

```bash
cd server && php think yd:update
```

超管自动可见全部菜单；非超管需在角色中勾选新权限。升级后重新登录刷新菜单。
