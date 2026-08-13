# v2.23.0 升级说明

## 变更
- 公告管理端到端移除（后端三端路由/控制器/模型 + 租户前端 + uniapp + `announcements` 表）。
- 协议管理收进系统配置：`system_configs` 新增 `agreement_user_agreement` / `agreement_privacy_policy`（`richtext`），原 `agreements` 表数据迁入后删表；C 端 `/api/agreement/:code` 改读配置（uniapp 无需改动）。
- 反馈管理从「内容」迁入「设置 · 其他」（菜单父级→SettingsOther，路径 `/system/feedback`）。
- 「内容」菜单仅保留文章资讯（重定向改为文章栏目）。

## 执行
```bash
mysql -u<user> -p<pass> <db> < server/database/updates/v2.23.0/update.sql
```
幂等，可重复执行。多租户库会按 `name` 在每个租户内匹配处理。

## 影响
- 删除协议/公告菜单与权限后，建议让在线用户重新登录或清菜单缓存以刷新左侧菜单。
- 协议正文迁移后请到「设置 · 系统配置 · 协议设置」核对用户协议/隐私政策内容。
- uniapp 需重新发布（已移除公告分包页面与首页公告入口）。

## CMS 插件升级步骤

**重要：请严格按以下顺序执行，先装插件并完成迁移，再删旧表。**

1. **上传并安装 CMS 插件**：在平台后台「插件中心 → 上传插件」上传随包发布的 `server/plugin_packages/cms-1.0.0.zip`。上传后系统自动注册插件并创建 `cms_*` 系列表（cms_models、cms_categories、cms_contents、cms_single_pages 等）。

2. **给套餐授权 CMS**：在平台后台「套餐管理」中为目标套餐开启 `cms` 插件授权（写入 `plugin_grants`，`plan_id` 取自 `plans` 表对应记录）。

3. **按租户启用 CMS**：在各租户的「插件管理」页面启用 cms 插件，或由平台后台批量启用。启用时触发插件 `Lifecycle::enable`，自动将租户的 `articles` 和 `article_categories` 数据迁入 `cms_*` 表（迁移操作幂等，可重复执行安全）。

4. **验证**：
   - C 端：`GET /api/cms/article/list` 返回数据正常。
   - 租户后台：左侧菜单出现「内容」一级菜单，含「栏目管理 / 内容管理 / 模型管理 / 单页管理」四个子项。
   - 确认原有文章数据已完整迁入 CMS 插件内容列表。

5. **删除旧表（迁移验证通过后）**：确认所有租户迁移无误后，手动执行 `update.sql` 末尾被注释掉的两条 DROP 语句：
   ```sql
   DROP TABLE IF EXISTS `articles`;
   DROP TABLE IF EXISTS `article_categories`;
   ```
   或直接取消注释后重新执行 `update.sql`。
