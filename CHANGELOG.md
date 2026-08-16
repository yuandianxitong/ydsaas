# Changelog

本项目遵循 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/) 格式，版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

YdAdmin SaaS 的版本历史。

## [Unreleased]

### Changed
- 应用市场公开目录与权益同步强制 `runtime=saas`，拒绝安装 Shop 商城组件

### Added
- 平台租户管理：新建支持开通方式（试用 / 线下正式 / 暂不订阅）；线下正式落 `payment_channel=offline` 已支付订单并开通正式订阅；列表支持「线下续费」（`POST /platformapi/tenants/:id/offline-renew`）；表单「账号启用」与生命周期列语义拆分

### Changed
- 公开仓库改为 `yuandianxitong/ydsaas`（GitHub / Gitee）；`NOTICE` 纳入发行物
- DIY 页面装修（`/diy/home`）：改为首页 / 个人中心双列卡片（标题 + 发布状态 + 去装修 + 预览）；移除 Tab、侧栏元数据（组件数/最近更新）与 H5 访问区
- 租户订阅页：去掉与布局叠层的 padding；套餐权益改为图标+名称卡片；`auth/info` 的 `saas.entitlements` 补充 `name`/`icon`，并下发 `plan_id` 以正确标记当前套餐
- 插件 zip / 皮肤 zip 归档目录迁到 `runtime/plugin-packages`、`runtime/skin-packages`（kebab-case，纳入 `RuntimePaths`，不入库）；存量请把旧 `server/plugin_packages/`、`server/skin_packages/` 下的 zip 挪过去。安装时若 `package_path` 仍指向旧目录，会自动回退到新路径

### Fixed
- 平台审计日志：管理员列展示名称（昵称/用户名）而非 ID；结果码从 `result.code` 正确输出
- DIY 编辑器：关闭「显示头部导航栏」后模拟器仍保留标题栏，避免无法再点开页面设置（该开关仅作用于 C 端）
- DIY 轮播搜索：隐藏分类条时加大搜索与轮播间距；搜索框高度统一 32px

### Added
- DIY 轮播搜索组件（`search-banner`）：logo+搜索叠在轮播模糊底上；样式一左右留白圆角、样式二两侧露边；StylePresetPicker 选风格；编辑器预览不占状态栏空白；C 端随当前轮播图更新模糊背景；每页最多 1 个
- DIY 图片魔方：新增 11 种固定拼版（StylePresetPicker）与三端 CSS Grid 渲染；无 `layout` 的存量草稿回退等宽列
- DIY 热区组件（`hotzone`）：底图多矩形热区 + 链接；编辑器拖拽绘制/缩放，uniapp/PC 透明点击层
- DIY 属性面板体验增强：`section`/`hint`/`checkbox-group`/`goods-picker` 字段；ApiSelect 空值显示「请选择」；缺省 props 回填修复开关/预设未选中；轮播指示器样式与位置；公告排版预设（通栏/资讯/跑马灯卡）；高级 Tab（显示/生效端/定时/备注）+ C 端过滤
- DIY 组件样式预设框架：`props_schema` 新增 `style-preset` 字段类型（缩略图切换 + `patch` 合并 props/`componentStyle`）；编辑器 `StylePresetPicker` 内置与插件共用；公告组件支持 3 套皮肤与滚动方式，默认外层透明底避免盖住皮肤色
- 整套皮肤包（主题）导入导出：租户「页面装修」支持导出/导入 zip（`skin.json` + 主题色/TabBar/启动配置 + DIY 页面 + 资源）；预检依赖应用与组件类型，套用写入 DIY 草稿（系统页先版本备份），主题色与底部导航立即生效。API：`POST /tenantapi/diy/skin/export|import|apply`；官方皮肤 zip 目录 `server/runtime/skin-packages/` + `GET /tenantapi/diy/skin/official`
- 主题市场一键安装：`GET /tenantapi/diy/skin/market` 浏览 Site 上架主题，`POST /tenantapi/diy/skin/market/install` 经平台已绑定实例下载签名皮肤包并套用（依赖 Site v1.27+ 主题下载令牌）
- 平台后台「系统配置 → 产品授权」：录入/激活官网平台授权码（`platform.license.view` / `platform.license.update`）



### Added
- 工作台「店铺访问」：右栏套餐卡下方独立卡片，弹窗展示 H5（已发布链接 + 二维码）、小程序（渠道上传码）、PC（与 H5 同域 `/pc/` 链接 + 二维码）；新增 `GET /tenantapi/dashboard/access-info` 聚合就绪态
- 装修「启动与首页」页（`DiyLaunch` / `/diy/launch`）：从底部导航迁出 App 冷启动入口（`home_app_code` / `home_page`）；菜单种子、`saas:diy-menu-reconcile` 与 `v2.32.0` 增量对齐
- 微信小程序上传可维护版本号与项目备注：`tenant_mobile_configs.wechat_upload_version` / `wechat_upload_desc`（默认 `1.0.0` /「租户后台发布」）；上传走 `--uv`/`--ud`，成功后版本号自动 patch +1；租户打包页「微信小程序上传配置」可编辑。CI 机器人编号保持微信默认
- 运维命令 `php think saas:ensure-runtime [--user=www]`：统一创建/修复 `runtime/*` 与 `public/storage` 可写目录（含 `mobile-builds/_keys`）；root 执行时可 chown 给 PHP-FPM 用户。配套 `core/runtime/RuntimePaths`，web 安装器与 `saas:install` 复用；仓库保留 runtime 骨架 `.gitkeep`（不解决属主，部署后仍须跑 ensure）
- 框架数据库升级命令 `php think yd:update`：扫描 `server/database/updates/vX.Y.Z/`，依次执行未应用版本的 `update.sql`（可选）与 `update.php` 钩子（可选），自动套用 `DB_PREFIX`，已应用版本记录在新增的 `system_upgrades` 表，保证幂等、可断点续跑，并支持 `--dry-run` 预览与 `--baseline` 老库基线标记；全新安装（web 安装向导与 `php think saas:install`）自动写入历史版本基线。新增零框架依赖的 `core/database/SqlRunner`（前缀改写/语句拆分/占位符替换），安装与升级共用同一套前缀处理逻辑
- 微信小程序登录授权弹窗组件 `d-wechat-auth-popup`（头像 chooseAvatar/昵称 nickname 键盘/手机号 getPhoneNumber 三合一）：快捷登录仅在资料不全（昵称为默认「微信用户」或无头像）时弹出，昵称必填保存后完成登录，关闭即放弃；强绑手机号场景并入同一弹窗（temp_token 流不变）；后端 wechat-quick-login 响应新增 need_profile
- uniapp 公共支付层：`useOrderPayment` hook（插件注入自身 pay API 即可接支付，五分支支付参数消费/mock 回调/渠道候选全公共，接入示例见 hook docblock）+ `d-payment-popup` 支持自定义渠道列表、元金额串（`amountText`）、可选 `close` 事件与平台感知缺省渠道（微信小程序端不出现支付宝）；locafy/shop 插件已迁移，删除各自 ~200 行重复支付 hook 与自建渠道弹层
- 装修链接目录页面排除声明：插件 `uniapp.pages[]` 项可声明 `"no_diy_link": true`（流程中间态页面，如支付成功/发布成功回跳页），该页不参与 `DiyLinkCatalog` 装修链接自动派生；页面注册（PagesJsonGenerator）只读 path/title 不受影响
- uniapp 主包公共 hook `useRegionPicker`（省市区三级联动纯逻辑，零 uni 依赖 node 可测，含 vitest 用例）：自 shop 插件同名 hook 提升为主包公共资产，配套数据源 `regionApi.children()` 已在主包；shop 插件内副本待其下一版本切换（#7 前置）
- 租户端地区 API 模块 `tenant/src/api/region.ts`（`regionApi.tree()` → `GET /tenantapi/common/regions`）；孤儿组件 `components/Region/index.vue` 激活：内联请求改走 api 模块，`change` 事件增补第二参数 `labels`（所选各级 label 数组，末级 label 取末位），v-model value 数组契约不变（#6 前置）
- 存量租户消息模板回填命令 `saas:message-template-sync [--tenant=ID] [--dry-run]`：以 tenant_id=0 模板为准按 code 补插缺失的消息模板（幂等只增不改，软删行占用 uk_tenant_code 不重复补插）；补插逻辑收敛为 `TenantInitService::syncMessageTemplates()`，新租户初始化与存量回填共用同一份代码。init.sql 消息模板种子补齐 `notification`（通用通知）/`subscription_reminder`（订阅到期提醒）/`subscription_auto_renew`（订阅自动续费通知）三条调用点已在用但从未种过的模板（无 DDL 变化，schema.sql 不动）（#6）
- CMS 栏目与内容列表新增双端链接复制弹层，分别提供 PC 完整访问地址和 UniApp 页面路径，并补齐两端栏目页 `category_id` 参数落地、未发布/停用状态提示与回收站禁用规则（cms 2.3.0）。
- 会员统计键注册表：plugin.json `member_stats` 声明 + `MemberStatProvider` 契约 + `GET /api/user/member-stats` 聚合端点（entitlement 门控、异常隔离）；用户信息卡资产格可配置（名称/统计源/跳转）、图文导航与服务菜单支持角标；个人中心首位用户信息卡预留状态栏安全距离。
- 自定义装修页支持复制：新增 `POST /tenantapi/diy/pages/:id/copy`（复用 `diy.page.create` 权限），副本恒为未发布草稿（草稿取源页草稿、无草稿时取已发布内容），标识自动生成 `<源>-copy[N]` 去重且保证 slug 合法；页面列表改为服务端分页表格（`GET /tenantapi/diy/pages` 支持 page/limit/keyword/published 参数，返回 `{list,total}`），tenant「自定义页面」界面由卡片网格改为分页表格并新增「复制」操作
- 装修编辑器新增基础组件「分类导航」（category-nav，对齐元点Shop 实际渲染形态）：纯手工配置分类项（图标/名称/链接），支持图标网格与横向滚动两种展示样式、行数（1-3，决定空态占位数量）与每行列数（3-6）配置；三端（编辑器画布/uniapp/PC）圆形图标视觉一致，空 items 时 C 端与 PC 不渲染、画布显示灰圆占位
- DIY widget 面板图标协议（渲染器协议扩展）：`diy_widgets[].icon` 可声明单段图片文件名（随 zip 分发于插件 `assets/diy/`，经新路由 `/plugin-icon/<code>/assets/diy/<file>` 服务，防枚举策略与插件 logo 通道一致）或 http URL；编辑器面板图标三级回退 widget 声明 → 插件 logo → 占位图标，svg 用 CSS mask 渲染跟随主题着色、位图 `<img>` 直出。打包白名单新增 `assets/` 顶层目录，inspect/pack 校验声明的图标文件存在于 zip
- DIY 渲染器协议 v1（装修插件组件前端插件化）：`plugin.json` 的 `diy_widgets[]` 新增可选 `renderer`（`protocol` 版本闸 + tenant/uniapp/pc 三端裸组件名，约定目录 `<端>/components/diy/`）与 `item_fields`（item 字段追加白名单，text/raw 两类）；`PluginManifestValidator` 覆盖格式校验，`PluginPackageInstaller::inspect()` 新增"声明的渲染器组件必须存在于 zip"校验（`plugin:pack` 同闸拦截）。三端宿主改为注册表解析：tenant 编辑器按目录约定 glob + `defineAsyncComponent`，PC 由 `sync-plugin-pc.mjs` 生成 `pc/generated/diy-renderers.ts`，uniapp 由 `sync-plugin-uniapp.mjs` 同步插件渲染器到主包 `src/components/diy/plugins/` 并生成静态 import 注册表 `src/generated/diy-plugin-renderers.ts`（不用异步组件，规避小程序动态组件坑）；注册表未命中一律回退核心通用渲染器（card-list/list/single/grid-3/scroll-x），行为与旧版一致。生产移动端构建链同步支持：`PluginUniappCopier::copyDiyRenderers()` 按租户权益复制渲染器到主包、新增 `DiyRendererRegistryGenerator` 按权益重写注册表、`TemplateCopier` 跳过 dev 态渲染器软链
- 租户取消移动端构建：`POST /tenantapi/mobile/builds/:id/cancel`（排队/构建中 → 失败「用户取消构建」）；`markRunning`/`markSuccess`/`markFailed` 增加 CAS，避免取消后晚到的 worker 写回成功；打包页列表/卡片/详情提供「取消」

### Removed
- 从「框架分发升级」链路彻底移除 `think-migration`：删除 composer 依赖 `topthink/think-migration`、`server/database/migrations/`、`server/database/seeds/`、`core/database/Migration.php`；`php think saas:install` 与 `Makefile` 由 `php think migrate:run` 改为从 `schema.sql` + `init.sql` 建库（与 web 安装向导统一）；代码生成器不再产出迁移文件。表结构变更一律走 `database/updates/vX.Y.Z/update.sql` + `php think yd:update`。注：插件系统（`plugins/*`）自身的建表/升级机制不受影响

### Changed
- 租户「插件购买记录」列表对齐通用 CRUD 表格：搜索卡 + 表格卡标题条 + 标准分页；状态用 `el-tag`；支持按订单号/状态筛选
- 页面装修列表升级为枢纽页：左侧只读手机预览（首页/个人中心切换）+ 右侧元信息与 H5 链接/二维码；个人中心页面设置锁定标题、强制关闭头部导航并隐藏弹窗广告（与 C 端一致）
- 租户订阅管理页重排：待支付条、响应式两栏、套餐卡片选择、权益并入当前订阅区
- 装修编辑器发布增加发版弹窗：自动预览下一版本号，必填发版名称写入 `diy_page_versions.note`；历史版本列表展示发版名称便于回滚辨认
- 管理端构建拆包：tenant/platform Vite `manualChunks` 拆出 element-plus / echarts / wangeditor / highlight（不强制拆 vue 生态）；`chunkSizeWarningLimit` 从 2000 降到 800；Element Plus 改为仅注册 `ElLoading`（组件走按需解析），highlight.js 改为指令挂载时动态加载，降低首屏单 JS 体积
- 租户工作台：按 `days` 缓存 stats，趋势切换复用避免重复全量请求；失败展示可重试错误态（不再静默空卡片）；趋势/排行切换 AbortController 取消旧请求
- README：版本徽章对齐 `2.32.1`；「演示体验」改为与代码一致的平台域 / 租户域 / H5 `/mobile/` / PC `/pc/` 入口矩阵
- 登录监听器与消息推送监听器：将模糊 TODO 改为明确「未实现」产品状态注释（不接 UniPush 第三方）；删除无引用的废弃组件 `ImportData` / `TableColumnSetting`（tenant + platform）
- 平台仪表盘统计（`app/service/saas/DashboardService`）：租户日趋势 / 收入月趋势 / 收入与即将过期计数改为一次 `GROUP BY`（或条件聚合）+ PHP 补零，并加 60s TTL 缓存（键含 stats/extended/months 维度）
- 移动端软配置运行时刷新：页面装修（发布后）、主题风格、底部导航、启动与首页保存后，C 端拉 `/api/mobile/config` 即可生效，无需为内容变更重新构建；构建期 `tenant-config` 仅作首屏兜底。C 端白名单补齐 `theme_colors` / `tabbar_style` / `app_intro` / `service_*` / `share_*`；结构变更（新页面进包）仍需打包发布
- uniapp 移动端图标统一：移除 iconfont（含阿里 CDN 字体运行时依赖），22 个图标迁移到 @iconify-json/ri（UnoCSS presetIcons，line 为主/品牌 fill）；uno safelist 登记数据驱动图标类名（后端下发的 `i-ri-*` 静态提取不可见，不登记生产构建丢图标）；顺带修复 icon-headset 豆腐块
- 装修编辑器 8 个项目列表（轮播图/图文导航/分类导航/图片广告/图片魔方/服务菜单/公告/悬浮按钮）支持拖拽排序（useDragSort，原生 HTML5 drag，撤销时序正确）
- 装修组件 uniapp 与编辑器预览两端对齐批（20 对组件审计）：修复预览侧三处 rpx÷2 换算缺失（分割线/魔方间距/搜索框圆角显示为真机 2 倍）、标题栏"更多"与公告图标消费缺口、悬浮按钮形态、轮播/视频常驻圆角背景；uniapp 侧信息卡底部直角、服务菜单外轮廓交还装修样式面板；优惠券组 C 端重写为联券视觉（shop 2.7.3 / cms 2.3.3 同批）
- 租户端弹窗全局契约：el-dialog 统一按屏幕比例定宽（四档 `dlg-sm/md/lg/xl` class，clamp 上下限钳制，替代 47 处硬编码 px 宽度）、整体高度封顶 86vh、超高时 header/footer 固定仅 body 内部滚动（取代 EP 默认整页滚动）、矮弹窗垂直安全居中（flex 遮罩 + auto 边距，超高不裁头）；提供 `.dialog-scroll-free` 逃生舱恢复 EP 默认行为；ImportDialog 私有滚动方案收编、MaterialPicker 双栏布局适配矮视口（cms 2.2.5 / shop 2.7.1 视图同批迁移，见插件 CHANGELOG）
- 打包发布页多端卡片调整：「H5 / 网页商城」更名「H5 / 微信公众号」并移除独立公众号卡片（公众号内打开的就是 H5 产物，同一端）；新增「PC 门户」占位卡；六端图标改用本地彩色品牌 svg（html5/weapp/google-chrome/android/ios/douyin，图标圆底改浅色描边、iOS 白 logo 保留品牌黑底）
- `NormalizedWidget` render 白名单收窄为核心 5 种（`CORE_RENDER_KINDS`），插件自定义 render 改由 manifest 声明钉死（核心 5 种内 hydrator 仍可自由切换，保留 goods-grid 按 layout 切换行为）；item 字段白名单改为"基座 + manifest `item_fields` 声明合并"，`normalize()` 增加 widgetMeta 参数（`DiyWidgetCatalog::widgetMetaForNormalize()` 提供）。三端核心渲染文件仅保留通用形态，Shop/CMS 专属渲染迁入各自插件（见插件 CHANGELOG）；tenant 契约文件 `pluginWidgetRender.ts` 迁移为插件开发 facade `src/diy-kit/`；PC 通用渲染器补齐 `grid-3`/`scroll-x` 两种核心形态（此前缺失，商品组三列/横滑布局在 PC 上被降级为列表）
- 装修编辑器插件组件真数据预览：新增 `POST /tenantapi/diy/widget-preview` 预览注水端点（单组件复用与 C 端下发同一份 hydrator 注水逻辑，`diy.home.view` 权限），tenant 编辑器画布以通用渲染器 `PluginWidgetPreview` 按 render 模式（card-list/grid-3/scroll-x/tab-goods/coupon-row/seckill-row/entry-grid/asset-card/list/single）渲染真实数据，替代原骨架占位；500ms 防抖 + hash 去重；空数据渲染 Shop 同款真实感示例卡片（假券/占位商品卡/示例倒计时），加载中/失败回退骨架。任何插件 widget 自动获得真数据预览，无需逐组件开发

### Fixed
- uniapp 自定义 TabBar：图标容器固定 22×22 并 flex 居中，消除图文之间因 image 基线撑高产生的空白；移除调试埋点
- uniapp 首页 / DIY 自定义页改为 `navigationStyle: custom`，标题仅由装修 `show_header` 内页栏控制，避免与原生导航栏「元点SaaS」双标题叠层；无装修回退首页显示轻量「首页」顶栏
- 租户「底部导航」左侧菜单项支持拖拽排序（sortablejs + 手柄）
- 管理端 Vite 拆包去掉强制 `vue-vendor`：原先 `manualChunks` 用 `/vue/` 匹配，打不进 `@vue/*`，生产环境循环依赖报 `Cannot access 'd' before initialization`；vue/vue-router/pinia/vue-i18n 改回 Rollup 自然分包，保留 element-plus / echarts / wangeditor / highlight 拆分
- 平台端 `useListPage` 同步租户端幽灵行守卫，避免 el-table 列内省触发 `PUT .../undefined/status`；平台 `request` 同步 FormData 去掉默认 Content-Type 以便 multipart boundary（平台无租户生命周期，刻意不抄 402 分支）
- 上传接口由「路由漏挂权限中间件」隐式放行改为显式策略：平台挂 `platform_permission` + `#[Permission('platform.file.upload')]`；租户挂 `admin_permission` + 显式 `#[PermissionSkip]`（无独立 upload 权限种子）
- 租户 `MobileBuildController` 不再 `Db::table('tenants')` 直读套餐，改经 `TenantMobileBuildService::planIdForTenant` → `TenantRepository::planIdOf`
- 反馈管理详情按钮权限与服务端详情接口统一使用 `feedback.list`，并从安装种子及存量升级中移除未使用的 `feedback.detail` 幽灵权限。
- 租户工作台 PC 门户仅在存在且启用的租户 PC 配置时标为可访问；未配置或已停用时不再展示不可用的链接和二维码。
- 租户工作台统计缓存键加入 tenant_id，避免同一趋势周期的跨租户缓存串读；租户生命周期在有效试用订阅期间正确返回 `trial`。
- 平台仪表盘路由纳入 `platform_permission`，并为统计接口补齐 `platform.dashboard.view` 权限与存量菜单升级数据。
- UniApp 通用支付请求改为提交后端创建支付订单所需的 `subject`、`total_amount`，不再错误传入由服务端生成的 `order_no`。
- 租户端微信小程序「上传成功」但公众平台无开发版本：`WechatMiniprogramUploadService` 误用 `--version`/`--desc`（前者是打印 CLI 版本并 exit 0），改为官方 `--uv`/`--ud`，并拒绝仅返回 CLI 版本号的假成功；上传失败时解析微信 `errMsg`（如 IP 未加白名单）返回可读提示；失败态可重新上传；`ErrorLogSanitizer` 长日志改为头尾保留以免截掉末尾错误
- 移动端独立构建（Mode B）首页无 DIY、底部导航回落默认「首页/我的」：`TenantConfigWriter` 此前未写入 `home_decoration` / `tabbar_style` / `theme_colors` 等字段，而 store 在 `tenantId>0` 时不再拉 `/api/mobile/config`，导致首页树与 tabBar 样式丢失；现构建期完整注入，前端 `readInjectedConfig` 同步读取。`get()` 读 `tabbar_json` 改为 `decodeJson`，避免字符串形态被当成空数组

### Changed
- 部署文档补充宝塔多租户 H5 `/mobile/`：一个通配站点 + Host 解析 `$mobile_tenant`，避免每租户改 nginx；SPA 回退改用 `@mobile_spa`，禁止 `try_files … /index.html`（会进 PHP 导致移动端 302 死循环）；目录入口补 `$uri/index.html`（见 `DEPLOYMENT.md` §12）
- 根路径分流 `Index`：若请求已是 `/mobile|/pc|/platform` 仍落到 PHP，返回 404 而不再 302，避免移动端死循环；docker nginx `/mobile/` 同步改为命名 location 回退
- uniapp H5 生产 API 基址强制同域相对路径：清空误配的 `VITE_APP_API_URL=https://admin.dev007.cn`（平台域无租户上下文）；`request`/`upload`/`useOrderPayment` 在 H5 下不再读该环境变量

### Fixed
- 租户小程序上传私钥 `config/wechat-key` 选 `.key` 仍 422：前端曾用 PUT + FormData，PHP 只对 POST 填充 `$_FILES`；改为 `POST`，并在 axios 拦截器对 FormData 去掉默认 `Content-Type` 以便浏览器带 boundary
- 租户打包页轮询时 H5/微信「触发构建」按钮一起闪烁：拆分 `listLoading` / 按平台 `triggerLoading`，轮询改为静默刷新；该端有排队/构建中时禁用触发并显示「构建中」
- 移动端构建 `MOBILE_BUILD_DRIVER=remote` 不生效、仍报 `[runner] pnpm not found in PATH`：ThinkPHP `Env::load()` 不写 `$_ENV`，而 `MobileBuildDriverFactory` 等只读 `$_ENV` 导致永远回落 `local`；新增 `MobileBuildEnv` 统一走 `$_ENV` → `env()` 回退

### Changed
- `firebase/php-jwt` 从 `^6.0` 升级到 `^7.0`（实装 7.1.0）：解除 Composer 因争议 CVE（PKSA-y2cr-5h3j-g3ys / CVE-2025-45769）对全部 v6 的 `block-insecure` 拦截，使无 lock / 重新解析依赖时 `composer install` 可继续；v7 会强制 HS256 密钥长度 ≥32 字节（安装器与 `.env.example` 已按此生成，存量短密钥需手动加长）

### Fixed
- 租户端上传图片 `/tenantapi/upload/image` 在 PHP 8.4 下失败：锁定依赖 `league/mime-type-detection` 从 1.15.0 升到 ^1.16（实装 1.17.0），修复 `FinfoMimeTypeDetector` 隐式 nullable 参数弃用被 ThinkPHP 错误处理器转成异常的问题
- 租户端操作日志不显示：`AdminLogMiddleware` 此前 `QueueManager::push` 到 redis，开发/单机环境无人 `queue:work` 导致永远不落库；且 job 未带 `tenant_id`，worker 无 TenantContext 时会写成 `tenant_id=0`、列表查不到。现改为请求内同步写入（失败不阻塞响应），并修复 `AdminOperationLogJob` 兼容积压任务时恢复租户上下文
- 平台日志管理登录/操作日志列表字段空白：`PlatformLogService` 此前直接透传 `platform_login_logs`/`platform_operation_logs` 原始列（`status`/`message`/`created_at`/`user_agent`、`duration_ms`），与前端表格 prop（`username`/`browser`/`os`/`login_result`/`login_message`/`login_time`、`execution_time`/`operation_time`）不对齐；现 join `platform_admins` 补用户名、别名映射、从 UA 解析浏览器/OS，并修正 `PlatformLogMiddleware`/`PlatformOperationLogJob` 写入列名与 schema 一致（此前写 `admin_id`/`execution_time` 等不存在列导致操作日志静默失败）
- uniapp 微信快捷登录/绑定手机号后 user store 不更新：`useLogin` 此前只 `setToken` 持久化、不写 pinia，`isLoggedIn`/`userInfo` 直到冷启动前一直是未登录态。user store 新增 `applyToken(token)` action（写 store + 持久化 + 拉取用户信息，getUserInfo 失败不影响登录态），两处登录分支改走该 action（#3）
- uniapp C 端上传链路三修（#4）：① `POST /api/common/upload/image` local 驱动此前返回相对 `/storage/...` 当作 url，现响应 `url`（绝对，域名拼接对齐 tenantapi UploadController）与 `path`（相对，DB 存储约定）并存，云存储驱动同样补 `path`；② `useUpload` 消费 `result.path` 补 `|| result.url` 兜底；③ 上传失败提示乱码——`uni.uploadFile` 不按响应 charset 解码（DCloud 已知行为），`uploadApi.uploadImage` 不再透传后端中文 message，统一前端本地文案
- 系统配置读取租户隔离：`SystemConfig::getConfigValue()/getConfigsByGroup()`（core 层静态入口）此前无 tenant_id 过滤，全表扫 `find()` 恒命中 id 最小的 tenant_id=0 空模板行，租户在后台配置的微信/支付/存储凭据永远读不到——WechatManager 报「微信小程序配置不完整」、PaymentManager/StorageManager 同病。现按 `TenantContext::current()` 当前租户过滤（无上下文时读 tenant_id=0 平台行），租户行/组缺失时回退平台模板行（#5）
- 新建租户缺失消息模板：`TenantInitService::initTenant` 复制链此前不含 message_templates，新租户任何 `MessageService::trySend` 一律「模板不存在」；复制链接入 `syncMessageTemplates()` 补齐（#6）
- 富文本编辑器（Editor 组件）本地上传图片崩溃修复：素材库重构后 MaterialPicker 契约改为 `v-model` + `@confirm`，Editor 仍调用已移除的 `showPopup()` 导致所有富文本消费方图片上传报错；图片路径已对齐新契约（多选后逐张插入），视频上传菜单默认从工具栏排除（素材库仅支持图片，原视频路径同样必崩；消费方可传 `toolbarConfig` 覆盖恢复）。
- CMS UniApp 装修内容列表改用注水数据中的内容 ID 跳转文章详情，修复将 PC `/content/{slug}` 路径交给 `switchTab` 后提示页面不存在；默认“查看更多”同时映射到合法的 UniApp 发现页或频道页（cms 2.3.2）。
- CMS 栏目链接补齐单页与频道入口：新增插件自有 UniApp 单页/频道分包页，频道不再复用会丢失查询参数的 TabBar 发现页；栏目与内容列表的“链接”操作移除前置图标以统一操作列样式（cms 2.3.1）。
- CMS 内容详情标签 JOIN 改用带主表别名的租户作用域，并限定标签关系表租户，修复编辑内容时 `/tenantapi/cms/content/detail/:id` 报 `tenant_id` ambiguous。
- CMS 模型字段编辑改为按字段 ID 增量同步，修复删除字段提交后仍存在、修改必填状态后重复新增同名字段的问题。
- 插件权限无法授权给非超管角色：租户端权限校验只认 `menus.permission`（`role_menus ⨝ menus` 取 `type IN (2,3) AND permission<>''`），但插件菜单安装器（`AppMenuInstaller` 与各插件 `Lifecycle::syncMenus`）从不写 `permission` 列、也不生成 type=3 按钮节点，导致插件声明的权限码永远进不了任何角色的权限集——非超管管理员即便被授权了插件菜单，访问插件接口仍一律 403（超管被短路放行，掩盖了缺陷）。修复：菜单安装器持久化 `permission` 列并新增 `core\plugin\PluginMenuButtons` 把 `plugin.json` 菜单的 `buttons` 声明展开为 type=3 操作权限节点（按 `(tenant_id,parent_id,permission)` 幂等）；插件 `plugin.json` 页面菜单绑定 `.list/.view` 权限 + `buttons` 声明增删改等细权限，对齐内置模块的菜单驱动权限模型。存量租户经插件版本升级（upgrade 钩子重同步）自动补齐；升级后需清一次权限缓存或等 TTL/重登录生效（与框架既有菜单同步一致，不主动刷缓存）
- 全局侧栏插件接口 403：`/tenantapi/plugin`（本租户可用插件列表）被全局侧栏 `sub-sidebar` 每页 `onMounted` 调用以渲染插件菜单区块，却声明了 `#[Permission('plugin.list')]`（插件管理权限），未授权插件管理的普通角色每页 403。改为 `#[PermissionSkip]`（只读 UI chrome，任何已认证租户管理员可访问；enable/disable/config/purchase 等管理动作仍各自受权限约束）
- 列表页（`useListPage`）状态开关幽灵请求：进入角色管理等页面即发出一次 `PUT /system/role/undefined/status`（后端返回「角色不存在」）。根因是 el-table 会把带默认插槽的列在隐藏的 `.hidden-columns` 容器里再渲染一遍做列内省，此时 slot 的 `row` 为空，插槽内 el-switch 挂载期把越界 modelValue 归一化并 emit 一次 change，以 `row=undefined` 触发 `handleStatusChange`。在 `handleStatusChange` 补 `row.id` 守卫拦掉，一处修复覆盖全部复用该 hook 的列表页（角色/管理员/部门/菜单/用户）
- `core\base\Repository::query()` 新增可选主表别名参数：join 查询时自动注入的租户过滤按 `别名.tenant_id` 限定——此前不带表前缀，与被 join 表的同名 `tenant_id` 列冲突触发 SQLSTATE 1052 ambiguous（cms 插件 publishedList/推荐位查询首个暴露，装修 CMS 内容列表预览接口 500）。零参调用行为不变
- `NormalizedWidget` 白名单漏收 4B 的 `entry-grid`/`asset-card` render 与 `icon/label/badge_key` item 键，导致个人中心页插件组件下发时被降级为 list 且字段被剥离（uniapp 侧白名单早已收录，后端单侧遗漏）
- uniapp 自绘底栏组件重命名 `AppTabBar`，避开微信小程序保留名 `custom-tab-bar` 触发的编译告警（能力不变）
- RuleConditionEditor 暗色模式 token 回退失效修正（`var(--ink-*, #hex)` 换 tenant 语义变量，此前暗色下恒走硬编码 hex）

### Removed
- 清理素材库 group→category 迁移遗留死代码（tenantapi 文件分组端点/前端死 api 方法/死 i18n 键；platformapi 仍在用的分组能力保留）

## [2.31.0] - 2026-07-14

### Changed
- 菜单「首页装修」重构为「页面装修」列表页（首页/个人中心系统卡片，个人中心待 4B 开放），
  自定义页面编辑入口同步改跳全屏编辑器（v2.30.0 增量迁移存量租户菜单）
- 平台端弹窗全局视觉升级（分层精致方向）：`.el-dialog` 统一分层阴影、通栏 header 分隔线、footer 淡灰背景带（`--color-bg-page`，暗色自适应），标题 16px 半粗，遮罩加深；容器 padding 经 `--el-dialog-padding-primary: 0px` 变量归零，留白全由分区自身控制；MessageBox 对齐同一视觉语言。新增 `dialog-sm/md/lg/xl` 宽度档位（420/560/680/800，小屏自动收窄），28 处散落的内联宽度全部收敛入档。覆盖层选择器统一加 `html` 前缀提升一级优先级——vite `importStyle: "sass"` 按组件懒注入的 EP 样式晚于覆盖文件加载，同优先级会被反超（历史上 tokens 里的 `--el-border-radius-base` 映射即因此长期休眠）。圆角保持 EP 默认 4px（产品定调不走大圆角），tokens 中的休眠 radius 映射显式移除防止日后意外激活。落点 `platform/src/theme/overrides/element-plus.scss`，零业务逻辑改动。
- 平台端列表/表单全局去组件气：表头改淡灰背景带 + 13px 半粗次级色小字、行 hover 品牌浅色、单元格纵向留白加大；分页页码圆角胶囊化（当前页品牌浅底）；搜索区条件项等距排布并去掉底部多余留白；表单 label 与分组分隔线文案统一降为次级色。
- 套餐表单「插件授权」重设计：两个多选下拉 → 图标 + 名称 + 版本的卡片网格多选（无图标插件沿用 PluginCard 同款哈希渐变底），选中卡片内嵌「自动启用」开关（未选中禁用、取消授权自动连带清除），并接入 i18n。
- 订单管理去 ID 化：筛选区租户 ID 数字输入改为按名称/code 远程搜索下拉，新增套餐筛选；列表与详情显示租户名/套餐名（type=4 显示插件名），月数补「个月」单位。后端 `SaasOrderService::paginate/show` 批量附加 `tenant_name/plan_name/plugin_name`（只增字段，循 TenantService 先例），paginate 支持 `plan_id` 筛选。
- 订单详情弹窗按功能重排版：金额主视觉（大号金额 + 订单号 + 状态）+「订购内容 / 支付信息 / 时间」三分区自定义排版，替代 el-descriptions 表格；新增订单类型（新购/续费/升级/插件购买）与支付渠道中文化展示。
- 租户端内容区主题质感对齐元点Shop 设计系统：slate 中性色阶、分层轻阴影、13px 基础字号、表格/卡片/弹窗观感重塑、标题主色竖条；主色与头部/侧栏布局保持不变，暗色模式与多主题兼容

### Fixed
- 框架 DI（core/base/Service、Controller 的属性注入）解析失败时不再静默吞异常，改为记 Log::warning 留痕——
  此前真实原因（如迁移未跑导致表不存在）会被伪装成 "typed property must not be accessed before initialization"
- 租户表单状态开关残留「启用/禁用」两侧文字（历史「状态开关去文字」批量清理的漏改），与全平台其余 12 处开关对齐。
- 插件模块 4 个弹窗（上传/升级/构建日志/授权管理）硬编码中文接入 i18n（新增 `pluginMgr` 文案段，中英双语）。
- 插件路由按应用挂载：此前 PluginLoader 在全局 boot 把 tenantapi/api/platformapi 三组插件路由注册进同一路由表，多应用 pathinfo 不含应用前缀导致跨应用同名路径被先注册组抢走（如 C 端 `/api/shop/product/list` 被 tenantapi 规则拦成 401）。现改为各应用 `route/plugins.php` 只挂当前应用的插件路由，中间件链不变。
- 测试库隔离加固：5 个 Integration/Feature 测试在 setUpBeforeClass 自行初始化 App 会重读 .env 覆盖 _test 库切换，TRUNCATE 直接打到 dev 库（曾清掉演示租户）。现统一走 bootstrap 共享回断言 helper，并在危险操作前硬校验当前库名以 _test 结尾，不满足则响亮失败。
- saas:diy-menu-reconcile 漂移修复分支补同步 component/path/permission（防菜单种子变更后出现"新标题旧组件"坏态）

### Known Issues
- `saas:plugin-menu-reconcile` 工具当前只做菜单新增/删除对账，尚不支持更新已持有菜单行的 component/meta（整插件粒度对账）。插件迭代若需改已持有行的菜单指向，可在插件自身 Lifecycle 升级钩子中自行同步，或手工 SQL 对齐。

### Added
- 装修编辑器全屏化：独立路由 /diy/editor（不套后台布局），Shop 风格骨架（品牌色工具栏/220+400
  面板/画布投影），组件浮动操作条补齐 显隐/复制，未保存离开守卫；组件新增 hidden 跨端契约
  （uniapp 渲染器过滤隐藏组件）；新增 diy/home/summary 状态摘要端点
- 素材库升级层级分类树：新表 file_categories（租户隔离/无限层级），files 挂 category_id，
  存量分组自动迁移一级分类；新增 MaterialPicker 素材选择器（分类树/文件夹下钻/上传/批量移动删除），
  ImageSelect/WangEditor 接入素材库（对外契约不变），素材管理页支持分类管理；图片上传上限 2MB→10MB
- 插件事件订阅正式接线：`PluginEventRegistrar` 在 boot 时为已启用插件注册事件监听，支持 `plugin.json` 声明式 `events`（事件名 → Plugin\ 命名空间 handler 类）与 `subscriber`（实现 `core\plugin\contracts\EventSubscriber` 的类，适合复杂订阅）两种方式；manifest 校验器同步覆盖两字段（handler 强制 Plugin\ 命名空间，防止绑到框架内部类）。此前该契约"接口存在但零调度代码"。
- 平台级插件禁用/启用：`POST /platformapi/plugins/:id/disable|enable`（权限 `platform.plugin.status`），插件卡片下拉新增「禁用/启用」。临时下架语义：保留代码目录、业务数据与租户菜单勾选关系，下个请求起路由不再挂载、entitlement 全量失效；区别于删代码目录的软卸载。`Plugin::STATUS_DISABLED` 由死代码转为真实状态流转，被依赖的插件禁止禁用。
- 平台「开发工具 → 移动构建监控」页面：全租户移动构建列表（租户/平台/状态筛选）+「强制收尾卡死任务」，对接既有 `/platformapi/mobile-builds` 接口（此前后端完整但前端零页面）；平台菜单种子与增量 SQL 见 `server/database/updates/v2.27.1`。
- 租户端头像下拉新增「租户资料」入口：tenant-profile 页面（租户资料/生命周期/存储配额，此前无路由 100% 不可达）接入常量路由 `/tenant-profile`。
- 插件菜单 manifest 新增 `is_hidden` 透传（核心 AppMenuInstaller，任何插件可声明隐藏路由页）。
- tenant vite 放行插件软链目录使 vitest 可测插件前端。
- uniapp 新增 vitest 测试基建。
- 框架级：插件 manifest 支持 console 命令注册、C 端 api_auth 认证路由组；租户级支付新增 mock 驱动（仅调试环境）。
- 框架级：PaymentService::refund 支持多笔累计部分退款（行锁事务防并发竞态，累计满额才置 REFUNDED，向后兼容）。
- 通用组件库扩充（移植自元点Shop 并令牌化/i18n 化）：ProTable 增强表格（插槽驱动列、
  列配置弹窗含拖拽排序/固定/显隐、配置持久化走 cache 封装、批量操作区、导出回调），
  角色列表页试点接入；ImportDialog 三步导入向导（接口全经 props 注入，err_fields 结构化
  错误高亮）；RuleConditionEditor AND/OR 条件编辑器（储备组件，fieldOptions 注入）；
  旧 TableColumnSetting/ImportData 标注废弃

### Changed
- `platformapi /mobile-builds` 列表返回结构由 `items/total` 对齐到平台惯例的 `list/pagination`，并剔除列表不需要的大 JSON 字段。
- 租户 CRUD 权限拆分（⚠️ 行为变更）：新建/编辑/删除/重置管理员密码由共用 `tenant.view` 拆为 `platform.tenant.create/update/delete/reset_password` 四个按钮权限，前端按钮以 `v-perms` 门控。升级后仅持有 `tenant.view` 的非超管角色失去写能力，需在角色管理中重新勾选（见 `server/database/updates/v2.27.1`）。
- `TenantService` 事务改用 `runInTransaction`，三处裸 `Db::table` 查询下沉到 `PlanRepository::namesByIds` / `PluginGrantRepository::appPluginIdsByPlan` / `TenantPluginRepository::hasActivePurchase`，消除该 Service 对 `think\facade\Db` 的直接依赖（对齐 CLAUDE.md 分层规范）。
- pc 门户 login/register 补路由级守卫（`middleware/entry-gate.ts`）：`login_enabled`/`register_enabled` 关闭时直接访问 URL 也会重定向首页，此前只做了入口显隐。
- uniapp 开启 pnpm `enable-pre-post-scripts`（此前 predev/prebuild 钩子在 pnpm 下不会自动触发），并新增 `pretype-check` 钩子在类型检查前生成 `pages.json`。

### Removed
- 租户端遗留死代码：无路由注册的 `CronJobController`/`CodeGeneratorController` 及 `CronJobValidate`（平台端已有对应功能；`cron:run` 调度与 CronJobService 不受影响）；无菜单入口的 `system/permission` 孤儿页面及其 api 层（其 create/update/delete 后端本就不存在）。
- uniapp 未被引用的 `wechat-pay.ts`/`alipay.ts` 支付工具（实际支付统一走 `usePayment` 的 `uni.requestPayment`）。
- platformapi marketplace `CatalogController::show` 死接口（前端零调用）及对应路由。

### Added
- 租户 PC 前台 SaaS 化：新增 `tenant_pc_configs`、`/api/pc/config`、`/tenantapi/pc/config`，支持站点信息、首页类型、导航、SEO、登录注册开关；PC 默认首页可回退到 `platform=pc` 的装修单页。DB 升级用 `server/database/updates/v2.27.0`。
- 插件新增 PC 前台协议：`plugin.json.pc.pages/allowHome/home`，插件 PC 源码约定为 `server/plugins/{code}/pc/`，新增 `scripts/sync-plugin-pc.mjs` 同步到 Nuxt PC 壳。
- 移动端构建适配器（Phase 3a）：`tenant_mobile_builds` 增 `driver/remote_job_id/artifact_url/runtime_json` 四列，构建结果落库 driver 与运行时元数据；租户端构建详情展示 driver。DB 升级用 `server/database/updates/v2.25.0`。

### Fixed
- 官方市场 RBAC 断链：整个 marketplace 权限家族（`marketplace.connection.view/manage`、`marketplace.catalog.view`、`marketplace.install`、`marketplace.connection.rotate_token`）此前只 seed 在不参与鉴权的遗留 `permissions` 表，非超管平台管理员无法被授权使用应用市场。现补进 `platform_menus`（「应用管理」下）。同时删除三个从未实现的预埋点位 `marketplace.license.view`/`marketplace.license.manual_renew`/`marketplace.audit.toggle`（license 状态已随目录返回、「同步」已触发 license 重评估、审计开关走配置文件）。DB 升级用 `server/database/updates/v2.27.1`。
- uniapp `webview/index.vue` 类型错误：`onLoad` 回调参数与 `@dcloudio/uni-app` 类型定义不兼容，uniapp `pnpm type-check` 首次全绿。
- 租户端 402（宽限期/冻结拦截）此前落在拦截器 default 分支：现显式处理，展示后端准确提示并即时刷新 saas 生命周期状态，让顶部告警条（含「立即续费」入口）立即出现。
- `PluginGrantService` 分层债：三处 `Db::table('tenants')` 改用 `TenantRepository::listIdsByPlan`，`tenantHasActivePlugin` 下沉为 `TenantPluginRepository::hasActive`（`hasActivePurchase` 改为其特化），移除该 Service 对 `think\facade\Db` 的直接依赖。
- 租户端 `system/config/clear-cache` 越权：同一方法同时挂 `#[PermissionSkip]` 与 `#[Permission('system.config.update')]`，中间件优先命中前者导致任意已登录管理员可清空全站缓存；删除多余的 `#[PermissionSkip]`。
- 平台端代码生成器非超管必 403：`CodeGeneratorController` 四个方法此前无任何权限注解（无注解=默认拒绝），补齐 `#[Permission('platform.generator.list')]` / `#[Permission('platform.generator.generate')]`，与菜单种子对齐。
- 移动端打包发布 RBAC 断链：接口要求 `mobile.build.view/create/release` 但菜单模板从未种入对应节点，非超管角色即使被授予「打包发布」菜单也无法调用打包接口。菜单模板补种三个按钮权限节点。DB 升级用 `server/database/updates/v2.27.1`。
- 平台端退款闭环断裂：订单详情「退款」按钮不传金额（后端以分校验必抛「退款金额不合法」），且正确的 `refundApi.refundOrder` 全前端零调用。改为金额/原因弹窗（按钮以 `platform.refund.create` 门控），删除 `orderApi.refund` 歧义方法与 `OrderController::refund` 死代码（路由从未注册）。
- 插件 platformapi 路由裸挂载：`PluginLoader` 对 `routes.platformapi` 原为裸 `require_once`（无任何认证/权限中间件），改为与官方路由同链路的 `Route::group + locale/platform_context/platform_auth/platform_permission/platform_log` 包裹，新增守护测试。
- uniapp 默认首页「商城」入口硬编码：未授权 mall 插件的租户构建产物中分包不存在但入口仍渲染，点击 404。改为按构建产物 `pages.json` 分包存在性门控。
- 平台「开发工具 → API 文档」租户端文档空白：tenantapi 11 个控制器 25 处方法存在重复的 `#[PermissionSkip]`/`#[Permission]` 注解（含别名 import 造成的同类重复），swagger-php 反射实例化时抛 “Attribute must not be repeated”，导致 `type=admin` 文档生成 500。去重后租户端文档恢复（98 路径）。新增 `ApiDocAttributesTest` 守护三套 controller 不再出现重复注解。
- 平台「应用管理」本地插件图标 404：未安装的本地插件（随仓库自带、磁盘有 `plugin.json` 但 DB 未登记）现在也能显示 manifest 声明的图标。`PluginIconResolver` 在无已启用 DB 行时回退磁盘 manifest，仍只放行 manifest 声明过的文件。

### Added
- 平台「开发工具 → API 文档」改为路由驱动全量生成（`core\apidoc\RouteApiDocBuilder`）：原先纯靠手写 `#[OA\...]` 注解，平台文档仅 14 路径、缺失租户/套餐/订单/应用/定时任务/数据字典等。现先扫注解富文档，再枚举各 app 全部路由（临时替换容器 `route` 实例 → include 路由文件 → `Route::getRuleList`）+ 反射 `#[Permission]`/docblock 补齐未注解接口，并按控制器归类中文分组。平台 14→120、租户 98→152、前端 39→47 路径；归一化注解里多余的 app 前缀，消除双前缀/重复。
- 平台「开发工具 → API 文档」补齐三套文档切换：管理端（platform）/租户端（admin）/前端应用（api）三个 Tab，默认展示管理端；后端早已支持三种 `type`，前端原仅暴露两个且默认指向崩溃的租户端。
- 平台「应用市场」分页：原先只取 Site 公开目录首页（20 条），现 `OfficialMarketplaceClient::publicCatalog` 逐页拉全量，`CatalogController::apps` 接受 `page/limit` 并对公开态/已连接态统一服务端分页（返回 `pagination:{total,page,limit}`），前端 `MarketplacePanel` 增加分页器、切分类回到第一页。

### Changed
- 平台「应用市场」卡片图标改为铺满图标容器（`MarketplaceAppCard` 的 `.icon` 宽高 100%）。
- 平台「应用管理」一级菜单图标 `i-svg:plug` → `i-svg:blocks`。DB 升级用 `server/database/updates/v2.26.2`。
- 插件数据库变更全面 SQL 化：`database/install.sql`（全新安装）+ `database/updates/vX.Y.Z.sql`（版本区间增量）+ `uninstall.sql`（purge 清理），目录语义与主仓 schema.sql/init.sql/database/updates 同构；PluginSqlRunner 沿用 plugin_migrations 状态表的幂等/hash/版本区间机制。

### Added
- 插件演示数据：包内可携带 `database/testdata.sql`（仅 DML，`{TENANT_ID}` 占位），租户端「已安装」插件一键导入（事务包裹、每租户一次，`tenant_plugins.testdata_imported_at` 防重，权限 `plugin.testdata`）。DB 升级用 `server/database/updates/v2.28.0`。

### Removed
- BREAKING CHANGE: 旧 `migrations/*.php` 插件布局不再支持——包校验硬拒绝并提示迁移；`PluginMigrator`、`PluginMigration` 契约、`saas:plugin-migration-backfill` 命令删除（官方市场零存量插件，无实际受害者）。

### Added
- diy 装修机制扩展：属性面板 `api-select`/`api-multi-select` 字段类型（插件声明 options_url）与 `show_if` 条件显隐；uniapp 插件渲染器新增 `grid-3`/`scroll-x`/`tab-goods` 模式（tab 切换经插件公开端点实时拉取）；编辑器插件占位卡按 render 模式绘制骨架。
- 个人中心装修闭环：member 成为系统保留装修页（不可改删/不进自定义页列表/v2.31.0 存量回填），
  编辑器新增用户信息卡/服务菜单内置组件并支持组件按页过滤（插件 widget pages 维度），
  uniapp 个人中心页消费已发布装修数据渲染（无数据回退静态布局）

## [2.24.0] - 2026-06-23

### Added
- 装修字段对齐（Spec B）：自定义页面改卡片网格（全部/已发布/草稿 tabs + 搜索 + 组件数/状态徽标 + 占位渐变缩略图，`listPages` 派生 `component_count`/`published`）；打包发布改 6 平台卡（H5/微信小程序可触发构建 + 最新状态聚合，iOS/Android/抖音/公众号 置灰占位）
- 装修字段对齐（Spec A）：主题风格 6 色板（theme_colors）、底部导航 item 选中标题/双图标/角标 + tabBar 整体样式（tabbar_style）、基础设置 简介/客服(方式+电话)/分享(标题+图)；`tenant_mobile_configs` 扩字段（JSON+标量列，update.sql information_schema 守卫幂等）；uniapp 消费——CustomTabBar 选中标题/角标/整体色、主题色板注入 H5 CSS 变量、index/my 全局分享（onShareAppMessage）、我的页「联系客服」入口（电话拨打/微信客服）。注：新字段在 dev/stub（API 拉取）模式即时生效；独立打包「模式 B」产物的新主题色板/tabbar 样式/分享/客服注入待下一 patch 更新 `TenantConfigWriter`
- 装修编辑器交互增强：模拟器选中组件浮动工具条（上移/下移/删除，teleport 到组件右侧外层）、新增组件插入到当前选中项之后；图片魔方/图片广告 item 增加「名称」字段、视频组件改为上传视频（VideoSelect）、辅助分割/标题栏/图片广告布局改 radio 单选
- 默认首页装修：`init.sql` 种 `tenant_id=0` 的 home 模板（搜索框+公告+标题栏），`TenantInitService.copyDiyPages` 复制给新租户，新租户开箱即有默认首页
- 移动端底部导航内置「首页(pages/index/index)/我的(pages/my/index)」入口选项：`MobileEligibilityService` 前置内置项、`TenantMobileConfigService` 免 entitlement 放行（path 锁定），租户无插件也能配置 tabBar；`PagesJsonGenerator` 按路径回退基础壳图标；uniapp `index.vue` 内置首页不自跳
- 装修菜单重构为二级分组：装修 >「页面装修」(首页装修/自定义页面/底部导航/主题风格) +「发布管理」(基础设置/打包发布)；`DiyTheme` 标题「主题」→「主题风格」。新装/新租户走 init.sql；存量租户由 `saas:diy-menu-reconcile` 建分组+叶子改挂（幂等迁移）
- uniapp：四个 tab 页 onShow 隐藏自带原生 tabBar；首页移除 `/api/cms/article/list` 调用与最新文章区块
- 装修（C4b-3）：编辑器暴露插件 widget —— ComponentPanel 列出租户授权的插件 widget（拉 `GET /tenantapi/diy/widgets`），添加时用插件 `default_props`；PropertyPanel 按 `props_schema` 渲染通用属性表单（number/text/select）；SimulatorPreview 显示诚实占位预览（编辑器不注水）。至此插件 widget 全链路闭合：编辑器添加 → 保存发布 → c-api 注水 → uniapp/PC 端渲染。装修路线（C1→C4b）全部交付
- 装修（C4b-2）：端通用渲染器 —— uniapp 与 PC 的 DiyRenderer 新增插件 widget 通用回退：对带归一化负载（`props.render`）的组件，用内置通用渲染器按 `render`（card-list/list/single）展示 `items`。mall.goods-grid 等数据驱动插件 widget 现可在端上渲染（无后端变更）。编辑器暴露留待 C4b-3
- 装修（C4b-1）：插件 widget 端渲染框架件 —— hydrator 输出经 `NormalizedWidget` 归一化为自描述的 `{render,items}`（card-list/list/single）供端通用渲染器消费；`DiyWidgetCatalog` 暴露插件 widget 元数据，新增 `GET /tenantapi/diy/widgets` 供编辑器列出租户可用组件。mall 演示插件贡献真实 `mall.goods-grid`（商品组，走 MallProductService），重打包 `mall-1.1.0.zip`，首次端到端验证 C4 接缝。端通用渲染器与编辑器暴露留待 C4b-2/3
- 装修（C4）：插件贡献 widget 后端接缝 —— 插件可在 `plugin.json` 的 `diy_widgets` 声明 DIY widget 类型 + 可选 hydrator（实现 `core\diy\contracts\DiyWidgetHydrator`）。新增 `DiyWidgetCatalog` 按租户 entitlement 解析可用类型，`getPublishedForTenant` 下发时为数据驱动 widget 注水、丢弃未知/未授权组件（fail-safe）。纯后端框架接缝、无 DB/菜单/端/编辑器变更（端渲染与编辑器暴露留待 C4b）
- 装修（C3d）：PC（Nuxt）端渲染 —— 新增路由 `/p/:key`，按 slug 客户端拉取并渲染租户已发布的自定义 DIY 页（复用 c-api `GET /api/mobile/diy-page`）。新增 PC 版 DiyRenderer + 11 个 Web widget 渲染器 + px componentStyle 转换器；居中 375px 手机列忠实还原模拟器版式。富文本经 DOMPurify 净化、链接经 scheme 白名单(safeLink) 防 XSS。纯 PC 前端、无后端/DB/菜单变更
- 装修（C3c）：自定义页面 —— 支持每租户多张 `page_type='custom'` 页面（按 slug 寻址）。后台新增「自定义页面」管理（增删改查/启停/进入编辑器），编辑器复用首页装修三栏；端上新增通用页 `pages/diy/index` 经 `GET /api/mobile/diy-page?key=` 懒加载渲染任意页。后端把 home 单页链路重构为按 key 通用核心 + home 薄包装（零回归）。DB：`diy_pages` 增 `page_key` + 换唯一键，升级用 `database/updates/v2.15.0` + `php think saas:decorate-menu-reconcile`
- 装修（C3b）：componentStyle 扩展 —— 组件支持渐变/图片背景、阴影(box-shadow)、边框(border)、不透明度(opacity)。后台样式面板新增对应控件，旧装修数据按选中时深合并自动兼容、无需迁移。纯前端、无 DB/菜单/权限变更
- 装修（C3a）：新增 5 个通用组件 —— 图片魔方(image-cube)、视频(video)、公告(notice)、搜索框(search-bar)、悬浮按钮(float-button)，均为装饰性无后端 hydration；自动继承 componentStyle。无 DB/菜单/权限变更
- 装修（C2）：首页装修升级为可视化三栏拖拽编辑器（组件面板/手机模拟器预览/属性面板），新增 componentStyle 核心样式（外边距/内边距/背景色/圆角，端上经 DiyRenderer 包裹层 rpx 渲染）、撤销/重做、版本历史（diy_page_versions：发布即快照 + 回滚到草稿）。升级用 `database/updates/v2.12.0` + `php think saas:decorate-menu-reconcile`
- 装修（C1）：租户端新增「装修」一级菜单（首页装修/底部导航/主题/基础设置/打包发布），首页装修为运行时 DIY（diy_pages 表 + /api/mobile/config 下发 home_decoration + uniapp DiyRenderer + 6 个通用组件），并移除「系统/移动端配置」菜单（面板归并到装修）。升级用 `database/updates/v2.11.0` + `php think saas:decorate-menu-reconcile`

### Changed
- 装修「启动首页」改名「首页入口（可选）」并加说明：留空则用「首页装修」内容作为 App 首页
- 装修主题色默认 `#2979ff`（与 uniapp pages.json selectedColor 一致）
- 装修模块前端命名统一为 **diy**（对齐后端早已存在的 `/tenantapi/diy/*` 路由与 `diy.*` 权限码）：`views/decorate/` → `views/diy/`、路由 `/decorate/*` → `/diy/*`、`api/decorate.ts(decorateApi)` → `api/diy.ts(diyApi)`、菜单 `name/path/component` `Decorate*`/`/decorate/*`/`decorate/*` → `Diy*`/`/diy/*`/`diy/*`、后端命令 `saas:decorate-menu-reconcile`（`SaasDecorateMenuReconcile`）→ `saas:diy-menu-reconcile`（`SaasDiyMenuReconcile`）。装修左侧组件图标改用本地 svg 图标库（`src/assets/icons/diy-*.svg` → `i-svg:diy-*`，插件/未知类型回退 `diy-default`）。存量租户菜单重命名用 `database/updates/v2.24.0`（按 name 幂等）
- 官方市场：连接账号后展示全部上架应用，按"购买/安装/升级/已安装"四态区分；未拥有应用可点「购买」新标签页跳转官网。后端新增 `MarketplaceCatalogService::mergedCatalog` 合并公开目录与已购权益（并集去重），派生 `owned` / `buy_url`，`CatalogController::apps()` 已连接分支改调该方法
- 租户端顶级菜单改名为 首页/用户/应用/内容/渠道/系统：前端 i18n（zh-CN/en-US `route.*`，含新增 `TenantPlugin`）驱动侧边栏，`init.sql` 同步种子 title，`database/updates/v2.10.1/update.sql` 同步存量租户（保留自定义标题）。顺序/路径/组件/权限不变

---

## [2.23.0] - 2026-06-20

### Added
- 系统配置新增 `agreement` 配置组，含 `agreement_user_agreement` / `agreement_privacy_policy` 两条 `richtext` 类型配置（WangEditor 富文本编辑器）；为全部租户自动补录，存量协议内容迁入

### Changed
- 协议正文迁入系统配置：原 `agreements` 表内容写入 `system_configs`，C 端 `/api/agreement/:code` 改读 `system_configs` 配置项（uniapp 无需改动）
- 反馈管理菜单从「内容」迁入「设置 · 其他」（`ContentFeedback` → `SystemFeedback`，路径 `/system/feedback`）
- 「内容」顶级菜单 redirect 改为 `/content/article-category`，入口权限改为 `article_category.list`
- 文章资讯迁入 CMS 插件。

### Removed
- 公告管理端到端移除：后端三端路由/控制器/模型、租户前端页面、uniapp 分包页面及首页入口、`announcements` 表
- 协议管理 B 端栈移除：后端路由/控制器/模型、租户前端协议菜单页面、`agreements` 表（数据已迁入 `system_configs`）
- 核心 article 后台栈（tenantapi/api 控制器/路由/服务/仓库/模型/校验、租户前端文章页）与「内容」核心顶级菜单；`articles`/`article_categories` 表在升级迁移后删除

### Upgrade
- 存量租户执行 `server/database/updates/v2.23.0/update.sql`（幂等，可重复执行）。执行后协议/公告菜单与权限消失、反馈在设置·其他、协议正文进 `system_configs`。建议在线用户重新登录以刷新菜单缓存
- CMS 插件切换：详见 `server/database/updates/v2.23.0/README.md` 中「CMS 插件升级步骤」一节（先装插件+迁移数据，再删旧表）

---

## [2.22.0] - 2026-06-18

### Added
- 租户端「插件」中心：插件管理（已安装 / 可用插件 / 即将到期）+ 插件市场（分类网格 / 购买记录）。已安装/可用/即将到期复用同一过滤列表组件（按 `tenant_status`/`expired_at` 过滤）
- 插件分类机制：`plugin.json` 自声明 `category`，固定码表 `config/plugin.php`，租户插件列表 API 透出；未知/缺省归「其他」
- 购买记录接口 `GET /tenantapi/plugin/orders`（读 `saas_orders` type=4，按租户隔离 + 插件名，权限 `plugin.order.list`）

### Changed
- 顶级菜单 `应用`(TenantPlugin) 改名 `插件`，默认入口 `/plugin/installed`；二级菜单重组为「插件管理 / 插件市场」两组共 5 个页面

### Fixed
- 修复 type=4 插件购买订单遗留 bug：`SaasOrderService` 仍用已废弃列名 `addon_feature_id`（该列早已重命名为 `plugin_id`），导致购买时 `plugin_id` 未落库、支付回调无法激活插件；写入/读取/注释三处统一改为 `plugin_id`
- 修复购买记录接口分页结构：统一返回 `buildPagination`（`{list, pagination:{...}}`），对齐前端 `PageResult`/`useListPage`

### Removed
- 下线 `/plugin/apps`、`/plugin/plugins` 两个职责重叠的旧页面（菜单软删除 + 视图删除）

### Upgrade
- 存量租户执行 `server/database/updates/v2.22.0/update.sql`（幂等，按 `name` 逐租户匹配挂接）。租户需重新登录或清缓存后可见新菜单

---

## [2.21.0] - 2026-06-18

### Added
- 设计令牌对齐原型：主色 #2C73FF、页面底色、小圆角（2–4px）、ink 灰阶统一落地
- 顶栏换肤为蓝色渐变 + 下划线选中态
- 二级侧边栏改为分组两列布局
- 租户首页按原型两栏还原：订阅/用户统计数据接真实接口，访问量/资源用量标注示例值

### Changed
- 租户后台菜单重组：顶级「系统」改名为「设置」、「用户」改名为「会员」。「设置」下新增三个二级分组目录：系统设置（系统配置/数据字典/文件管理/菜单管理）、权限（管理员管理/角色管理/部门管理）、其他（通知管理）；日志管理、消息管理目录保持原位直挂「设置」下。「会员」下新增两个二级分组目录：会员管理（原用户列表，改名为会员列表）、资产记录（余额记录/积分记录）。路由路径与权限标识不变，面包屑新增一层。升级用 `database/updates/v2.21.0/update.sql`
- 移除 sidebar 经典布局模式，租户端仅保留顶部布局

---

## [2.10.0] - 2026-06-14

### Added
- 官方市场「点安装即连接」流程：点击安装后自动判定是否已连接元点官方市场账号，未连接则引导一键 OAuth 授权，授权后自动同步权益并判定 ready / purchase_required / incompatible，最后再做最终安装确认
- 新接口 `POST /platformapi/marketplace/install-intents`：单接口编排安装意图，返回 `authorization_required` / `connected` / `ready` / `purchase_required` / `incompatible`，前端不再自行拼接多次 API
- `MarketplaceInstallIntentService` — 安装意图编排服务（已连接走 cache 优先、miss 才同步；OAuth 兑换后强制同步一次再判定）
- OAuth state 缓存内嵌安装上下文（`intent` / `app_code`），`exchange` 兑换成功后自动建连接 + 同步 + resolve，`install_resolution` 一并返回
- 服务端持久化稳定 `installation_uuid`（`PlatformConfigService::getOrCreateValue` 存入 system_configs）— 重连 / 换绑复用同一实例身份，避免 Site 侧产生大量重复实例
- 平台前端：`MarketplaceConnectDialog`（连接账号引导）+ `InstallConfirmDialog`（版本/兼容性/发行方最终确认）+ `useMarketplaceInstall` 编排 composable
- 实例名由 `platform_site_name` + 当前域名自动派生，Site 地址取自服务端配置，普通管理员无需关心实例名 / Site 地址 / Token 等技术概念

### Changed
- `InstanceRegistrationService::initiate()` 签名改为 `initiate(callbackUrl, intent)`：Site 地址 / 实例身份 / 实例名均由服务端决定，不再接受前端传入
- `useMarketplaceOauth` 拆分为 `openPopup()` + `awaitAuthCode()`：在用户点击同步栈里先 `window.open('about:blank')`，再导航到授权页，修复 OAuth 弹窗被浏览器拦截的问题
- 顶部连接栏改为「立即同步 / 更换账号 / 解除连接」
- 匿名 `system/config/global` 接口剔除 `platform_marketplace_*` 内部配置，避免 installation_uuid 泄漏

### Removed
- 移除手动粘贴 Token 绑定入口（`MarketplaceConnectionDrawer` 抽屉、`connections/manual-bind` 路由、`ConnectionController::manualBind`、`InstanceRegistrationService::manualBind`）—— 仅支持官方 Site OAuth，杜绝明文凭证复制粘贴
- 移除前端直连 `connections/initiate` 入口（降为 install-intents 内部调用）

---

## [2.9.0] - 2026-06-09

### Added
- 新增 `plugins` 表 4 列：license_status / license_grace_started_at / license_last_check_at / read_safe_routes (JSON)
- 新增 `marketplace_connections` 表 2 列：token_rotated_at / token_expires_at
- 新 composite index `idx_distribution_license` 优化 license 状态机查询
- `PluginCompatibilityChecker` 服务 — 装前 min/max framework_saas_version 范围校验，不兼容硬拒 422
- `MarketplaceCatalogService` 真实计算 `compatible` 字段写入缓存（按 Site 返回的 min/max）
- `LicenseStateMachine` + `RuntimeLicenseEvaluator` 状态机（active / grace / expired）
- `RuntimeLicenseGuard` 中间件挂载 platformapi / tenantapi / api 三应用：grace 加 X-License-Warning 头 / expired 写请求 403 / `read_safe_routes` glob 豁免 / fail-open 不破坏业务
- `MarketplaceTokenRotationService` + `saas:marketplace-token-rotate` cron — 90d 自动轮换 + 提前 7d UI warn + 失败 last_error 不阻业务
- `MarketplaceAuditClient` + Install/Upgrade/Uninstall/Rollback 事件接入 — fire-and-forget 上报 Site v1.9.0 audit endpoint（严格数据边界：仅 instance_uuid / version / event_type / plugin code / error_code 8 字段白名单）
- `MarketplaceChangelogService` + GET changelog endpoint (1h cache)
- 平台前端：`LicenseStatusBadge` + `ChangelogDrawer` 组件、AppCard 升级角标 + 兼容性禁用、MarketplacePanel token 轮换 banner + 手动重试、顶栏全局 license / token 警告 banner
- `saas:license-evaluate` cron 命令（建议每 6h）
- `OfficialMarketplaceClient::curl()` 支持 per-call timeout（fire-and-forget audit 用 3s 短超时）
- 4 个新权限点位：marketplace.license.view / .license.manual_renew / .connection.rotate_token / .audit.toggle
- 新 config `saas.marketplace.*` 段：grace_period_days / sync_interval_hours / token_rotate_threshold_days 等 8 keys

### Changed
- `MarketplaceCatalogService::syncConnection()` 末尾立即触发 `LicenseStateMachine::evaluateConnection()` — 不等 6h cron
- `OfficialMarketplaceClient` 增 `rotateToken` / `postAudit` / `getVersion` 三方法
- `InstanceRegistrationService::exchange / manualBind` 写 connection 时初始化 `token_rotated_at = now()`
- `PluginPackageService::buildPluginRow` + `PluginUpgradeService::upgrade` 同步 `manifest.read_safe_routes` 写入 plugins 表
- `PluginService::uninstall` 触发 `plugin.uninstalled` event（含 distribution_source payload）
- platformapi Catalog/Connection 列表响应增加 license / token 派生字段供 UI 展示

### Database
- 升级请运行 `server/database/updates/v2.9.0/update.sql`
- 新增 cron（详见 v2.9.0/README.md）：
  ```
  0 */6 * * * cd /path/to/server && php think saas:license-evaluate
  0 3   * * * cd /path/to/server && php think saas:marketplace-token-rotate
  ```

### Depends
- Site ≥ v1.9.0（必须先发布并部署，提供 token rotate / audit 两个新端点）

### 已知遗留（推 C-2）
- `LocalPluginsPanel` 卡片未显示 license badge — `/plugin/list` API 不返 `license_status`；既有 Marketplace Tab UI 已能完整看到，C-2 决定是否扩 API + PluginCard
- `RuntimeLicenseGuard::matchPluginCode()` URL 匹配启发式（path 含 `/{code}/`），plugin code 应为项目唯一非泛词；C-2 应在 plugin upload validation 加 code 命名规范校验
- `SigningService::generateAndActivate()` 的 named lock release 顺序在 transaction rollback **之前** —— Site 端 pre-existing；本期未硬化（C-2 视 HTTP 暴露后并发情况再优化）

---

## [2.8.0] - 2026-06-08

### Added
- 官方市场客户端：OAuth-PKCE 绑定 + 已购应用同步 + 签名包下载 + 一键安装/升级
- 3 张新表 (`marketplace_connections` / `marketplace_app_cache` / `marketplace_public_keys`)
- `plugins` 表 +8 列（distribution_source / remote_app_id / remote_version_id / publisher_name / installed_hash / signature_status / latest_version / update_available）
- 控制台命令 `saas:marketplace-sync`
- 平台后台「插件管理」页新增「官方市场」Tab

---

## [2.7.12] - 2026-06-05

收尾：补 ARCHITECTURE 插件应用章节 + UI 微调 + 开发端口默认值切换。

### Added

- **`ARCHITECTURE.md` 新增「插件应用架构」章节**（+107 行）：从平台安装 → 套餐授权 → 租户启用 → 运行时 4 阶段勾出插件体系的完整流转，与 v2.6–v2.7 实际落地的 saga / migration 状态表 / icon 服务等机制对齐

### Changed

- **租户端侧栏菜单图标 `size 20 → 16`**：`tenant/src/layout/components/sidebar/standard-menu-item.vue` 两处（叶子节点 + 父节点）—— 视觉更克制，与文字基线对齐更佳
- **租户端「管理员列表」列宽 160 → 180**：`tenant/src/views/system/admin/index.vue` 的 `last_login_time` + `created_at` 两列，与 v2.7.9 「菜单管理」同语义延伸
- **开发端口默认值切换**：
  - `server` PHP dev server: `8005` → `8002`
  - `platform` Vite dev: `5175` → `5992`
  - `tenant` Vite dev: `5174` → `5993`
  - 文件落地：`platform/.env.development` + `tenant/.env.development`（本地未入仓的 `CLAUDE.md` 同步对齐）
  - 旧默认端口与多数开发者本地常用端口冲突；新默认值避让，新 clone 直接 `pnpm dev` 即可

### Notes

- 无 DB / 后端代码改动；无须跑 SQL
- 后端测试 **457/457** 保持

---

## [2.7.11] - 2026-06-05

文档治理 + 协议变更 + plugin_packages 默认 zip 入库 + PHP 版本统一升 8.4。

### Changed

- **(#5) 开源协议 MIT → Apache-2.0**：`LICENSE` 改为 Apache License 2.0 全文 + Copyright 2026 元点系统 (YdAdmin SaaS Contributors)；同步 `README.md` 协议 badge + 「开源协议」段 + 「基于 MIT 协议开源」措辞，`CONTRIBUTING.md` 协议段
- **(#2) PHP 版本统一 8.2 → 8.4**：本地测试环境早已是 8.4.20，文档要求滞后。统一升到 8.4+：
  - `README.md`：badge + 「环境要求」+ 「技术栈」共 3 处
  - `CONTRIBUTING.md` Requirements 段
  - `docs/guide/introduction.md` 技术栈表
  - `docker/php/Dockerfile` 基础镜像 `php:8.2-fpm-alpine` → `php:8.4-fpm-alpine`
  - `server/composer.json` `php` 约束 `>=8.0.0` → `>=8.4.0`
- **(#2) `README.md` 顶部加在线文档 link**：`<a href="https://docs.dev007.cn/saas/">在线文档</a>` 放到部署/架构/更新日志/贡献指南前面；「系统简介」末尾补 `📖 在线文档` 引用块
- **(#3) 项目中文名统一**：`元点-Saas` → `元点Saas`，扫荡 `docs/guide` + `docs/dev` + `docs/deploy` 5 个 .md 文件
- **(#4) `README.md` 系统截图 URL 重组**：原 10 张（admin01-04 + pc01-02 + mobile01-04）→ 6 张 `docs.dev007.cn/saas/demo/{platform,tenant,mobile}0{1,2}.png`，分组也改为「平台端 / 租户端 / 移动端」
- **(README) version badge 2.0.0 → 2.7**：与当前主线对齐

### Added

- **(#1) `server/plugin_packages/` 默认 zip 包随主仓 ship**：原 `.gitignore` 排除 `/plugin_packages/*`，仅 `points-exchange-1.0.0.zip` 历史 force-add。现去掉排除规则，3 个默认包（`mall-1.0.0.zip` / `points-exchange-1.0.0.zip` / `hello-world-1.0.0.zip`）全部 tracked。用户拉主仓就能直接在平台后台「上传插件」装上 demo

### Notes

- 无 DB 改动，无须跑 SQL
- 后端测试 **457/457** 保持
- 协议变更生效后，所有衍生作品必须遵守 Apache-2.0 条款（保留版权声明、专利授权、贡献者表）

---

## [2.7.10] - 2026-06-05

收尾 v2.7.9 plugins/ untrack —— 补齐 `.gitkeep` 保留空目录 + 清掉 mall 的残留 tracked 文件。

### Fixed

- **(P1) `server/plugins/.gitkeep` 缺失导致空目录消失**：v2.7.9 之前用 `git add -f` 把 mall + points-exchange 强加入库，从未创建过 `.gitkeep`。一旦目录里没任何 tracked 文件，git 不再保留目录，新 clone 后 `server/plugins/` 直接不存在 —— 而 `PluginScanner` 默认期望 root_path + 'plugins' 是目录。补齐 `.gitkeep` 让目录始终被 git 保留
- **(数据正确性) 清掉 v2.7.9 漏 untrack 的 1 个 mall 文件**：`server/plugins/mall/migrations/20260518000100_mall_init.php` 之前被 `git update-index --skip-worktree` 标记过，v2.7.9 的 `git rm -r --cached` 未生效。本次先 `--no-skip-worktree` 重置再 `--cached` 删

### Resulting state

```
server/.gitignore:
  /plugins/*
  !/plugins/.gitkeep         ← 现在真的有这个文件了

git ls-files server/plugins/:
  server/plugins/.gitkeep    ← 仅此一个，目录得以保留

git check-ignore -v server/plugins/mall/*:
  全部命中 /plugins/* → 被忽略 ✓
```

新写的付费插件（`server/plugins/your-paid-app/`）直接放进去就 100% 被忽略，无须 `git add -f`，无须每次新文件都来一次。

### Notes

- 已安装环境无 DB 改动，无须跑 SQL
- 后端测试 **457/457** 保持

---

## [2.7.9] - 2026-06-05

UI 微调 + 主仓 plugins 目录 untrack（作者将自研付费应用）。

### Changed

- **(#1) `tenant/src/views/system/menu/index.vue`**：模块列表「创建时间」列宽 160 → 180 避免折行
- **(#2) 租户端系统管理子菜单顺序重排**：原 sort 值散乱（系统配置 10、移动端 90、其它 1-8、日志 11、消息 12）→ 改为线性 1..11 顺序：系统配置 → 移动端配置 → 管理员 → 角色 → 部门 → 菜单 → 数据字典 → 文件 → 通知 → 日志 → 消息。`init.sql` 改 + `updates/v2.7.9/update.sql` 补存量
- **(#3) 租户端「应用管理」子菜单图标修正**：原 seed 写了 `i-svg:appstore` / `i-svg:plugin`，但 `tenant/src/assets/icons/` 里不存在这两个 SVG → 改为存在的 `i-svg:boxes` / `i-svg:box`

### Removed

- **(#4) `server/plugins/mall` + `server/plugins/points-exchange` 从主仓 untrack**：作者将自研付费应用插件 → 主仓不再 track 任何插件目录。`server/.gitignore:18` 早就有 `/plugins/*` + `!.gitkeep` 守护，新写的付费插件自动被忽略。本地两个 demo 目录保留不动 —— 新 clone 不会下载

### Tests

- `MallProductServiceTest` 加守护：检测到 `server/plugins/mall/app/` 不存在则 `markTestSkipped` 全套 → CI 上没 mall 也不会 fail
- `PluginAppInstallTest` 早就有 `markTestIncomplete` 守护 mall zip fixture 不在的情况，无须改动
- 全量 **457 / 457** 测试通过（mall 本地存在场景），0 risky 保持

### Database

- `server/database/updates/v2.7.9/update.sql` —— 同步存量 `menus` 表的 sort + icon
- `server/database/updates/v2.7.9/README.md` —— 升级说明 + 多租户副本同步提示

---

## [2.7.8] - 2026-06-05

修复 vue-tsc 报 el-tag `type` 字段类型不匹配，导致 `pnpm build` 失败。

### Fixed

- **el-tag 的 `type` 字段类型收敛**：el-tag 在 element-plus 2.13 的类型签名是严格 union `'primary' | 'success' | 'info' | 'warning' | 'danger'`，但项目里多处把 `type` 声明成 `string` 或宽 union 含 `''` → vue-tsc 报 TS2322 →`pnpm build` 失败。统一改成顶部 `type ElTagType = 'primary' | 'success' | 'info' | 'warning' | 'danger'` 别名 + `Record<string, ElTagType>` 字典 / `(): ElTagType` 函数签名，模板表达式 `map[x] || 'info'` 兜底也是合法 union 值
  - `platform/src/views/announcement/index.vue`
  - `platform/src/views/refund/index.vue`
  - `tenant/src/views/refund/index.vue`
  - `tenant/src/views/system/message/event/index.vue`
  - `tenant/src/layout/components/header/platform-announcement-bell.vue`（v2.7.6 已停用渲染，但文件仍要过 type-check）

### Verification

- `platform/`：`pnpm type-check` ✓ + `pnpm build` ✓
- `tenant/`：`pnpm type-check` ✓
- 后端 **457/457** 测试保持

无 DB / 后端代码改动。

---

## [2.7.7] - 2026-06-05

清理 upstream 残留 + install 文案对齐到 SaaS 命名。

### Changed

- **移除 `server/public/admin/`**：upstream ydadmin 残留的 SPA 构建产物（153 文件）。平台前端 vite 实际输出到 `server/public/platform/`（`base: "/platform/"`），admin/ 早就不被读取
- **`server/route/app.php` SPA fallback 从 `admin/<any?>` 改为 `platform/<any?>`**：生产 nginx admin.SAAS_ROOT vhost 直接 `root` 指向 platform dist 不走 PHP；这条路由仅在 dev 直 hit PHP 时兜底
- **install 文案 `元点Admin` → `元点Saas`**：`server/public/install/` 9 个文件 19 处全替换（index.php / install.class.php / css/install.css / css/modern.css / js/install.js / cleanup.php / README.md / steps/license.php / data/init.sql）
- **`server/database/seeds/SystemConfigSeeder.php` 同步替换**：`site_name` 与 `smtp_from_name` 默认值从 `元点Admin` 改为 `元点Saas`，与 `install/data/init.sql` 保持一致避免 seed 漂移

### Notes

- 新装直接走新文案
- 存量 `system_configs` 行（`site_name` / `smtp_from_name` 如果用户没改过）仍是旧值，按需可手动 `UPDATE system_configs SET config_value='元点Saas' WHERE config_key IN ('site_name','smtp_from_name') AND config_value='元点Admin'`
- 后端测试 **457/457 通过，0 risky** 保持

### Files removed

- `server/public/admin/` 整个目录（153 个文件）

---

## [2.7.6] - 2026-06-05

UI 收敛：顶部布局面包屑 + 头部去铃铛 + 插件壳无头无 padding。

### Changed

- **顶部布局展示面包屑代替 MultipleTabs**：`topnav/index.vue` 把 `<MultipleTabs v-if="settingStore.openMultipleTabs">` 换成 `<Breadcrumb>`（如「会员 / 会员管理」）。新增 40px 高的 `.content-crumb` bar，与 sub-sidebar 同色（`--el-bg-color`）+ 浅边框分隔。侧栏布局模式不变（仍是面包屑 + MultipleTabs 组合）
- **租户端头部去掉通知 + 平台公告铃铛**：`header/index.vue`（侧栏模式）+ `topnav/topbar.vue`（顶栏模式）两处一并移除 `PlatformAnnouncementBell` + `NotificationBell` 的 import 与渲染。Bell 组件文件保留在 `header/` 目录，未来需要可随时挂回去
- **plugin detail-shell 去掉 shell-head**：`tenant/src/views/plugin/detail-shell.vue` 移除「插件名 + 已过期 tag」头部 —— 与侧栏菜单 / 面包屑里的插件名重复。shell-body 保持无 padding，由各 panel 自己控制
- **points-exchange 两个 panel 去 padding**：`config.vue` / `exchanges.vue` 拆掉 `<div class="panel">` 外层包装 + 删 `.panel { padding: 16px; }` CSS，直接 `<el-card>` 紧贴 shell-body 渲染。与 mall 等 menu-style 应用页观感一致
  - 由于 `tenant/src/views/plugins/points-exchange/*.vue` 与 `server/plugins/points-exchange/tenant/views/*.vue` 是 hardlink（共享 inode），改动同时落到插件源码与前端 SPA 视图，无须额外 sync

### Notes

- 无后端 / DB 改动；前端 build 即可
- 后端测试套件 **457/457 通过，0 risky** 保持

---

## [2.7.5] - 2026-06-05

Hot fix：v2.7.4 漏的两块 —— dev/prod proxy 把 `/plugin-icon` 转给 PHP-FPM + PluginCard 有真图标时去渐变背景让图标填满 icon-block。

### Fixed

- **(P1) Vite dev server + nginx 没代理 `/plugin-icon`**：浏览器 hit 在前端层 → Vite 报「did you mean /platform/plugin-icon/...?」404；nginx 走 SPA fallback 也 404。修复：
  - `platform/vite.config.ts` + `tenant/vite.config.ts` 加 `/plugin-icon` proxy 到 `VITE_APP_API_URL`
  - `docker/nginx/includes/saas_common.conf` 加 `location /plugin-icon` proxy_pass 到 php，3 个 vhost（admin / 租户通配 / 根域）共享生效
- **(UI) `PluginCard.vue` 有 icon 时去渐变背景 + 图标填满 icon-block**：原 CSS `img { width: 32px; height: 32px }` 让真实图标只占 icon-block（48×48）的中间一小块，且背景的渐变色还在 → 视觉上是"小图浮在彩底上"。改为：
  - 有 `data.icon` 时 `.icon-block--has-image` 移除背景（保留阴影/圆角）
  - `img { width:100%; height:100%; object-fit:contain }` 填满整块、按比例缩放、不裁剪
  - `overflow:hidden` 防图标越界
  - 兜底渐变 + 首字母在无 icon 时保留不变
  - 两端（`platform/` + `tenant/`）的 PluginCard.vue 同步改

### Action required

- 开发：重启 vite（`pnpm dev`）让新 proxy 生效
- 生产：reload nginx（`docker compose exec nginx nginx -s reload`）+ 重新 build 前端

无 DB / 后端代码改动。

---

## [2.7.4] - 2026-06-05

Feature：插件 / 应用图标按需服务 + mall / points-exchange demo 加 icon.png。

### Added

- **`/plugin-icon/<code>/<file>` 全局路由**：PHP 按需服务 `server/plugins/<code>/<file>`。校验链 plugin status=ENABLED + 未软删 + manifest.icon 申明匹配 + 扩展名白名单（png/jpg/svg/webp/ico/gif）+ 文件 ≤ 1MB；返回 Cache-Control public + max-age=86400 + 强 ETag。任何校验失败统一 404 防信息泄露
- **`PluginIconResolver::iconUrl(code, value)` 静态 helper**：把 manifest.icon 原值（如 `"icon.png"`）转成浏览器可拉的 URL；空保留空（前端兜底渐变色）；`http(s)://` 透传（CDN 扩展点）；非法值（含 `/` `..` 或非图片扩展）返回空
- **manifest validator 新增 icon 字段校验**：必须是单段图片文件名或 `http(s)://` URL；其它一律报错
- mall + points-exchange `plugin.json` 的 `icon` 字段从空字符串改为 `"icon.png"`；磁盘上 `icon.png` 实际放进对应目录

### Changed

- `PluginRepository::listWithKind` / `listAvailableForGrant` 返回的 `icon` 字段从 manifest 原值改为浏览器可拉 URL
- `PlatformApi PluginController::show` 返回的 `icon` 同步转 URL（detail 页用）
- `TenantPluginService::listAvailable` 返回的 `icon` 同步转 URL

### Tests

- 新增 `PluginIconResolverUrlTest`（Unit，11 用例 dataProvider）：基本文件名 / 空 / http(s) 透传 / 8 类非法值（path traversal / 绝对路径 / 子目录 / 非法扩展 / 非法 code）
- 新增 `PluginIconResolverServeTest`（Feature 真 DB + 真文件，8 用例）：成功响应 200+content-type+Cache-Control / 插件 DISABLED → 404 / 软删 → 404 / manifest.icon 不申明 → 404 / 文件名与申明不符 → 404 / 路径穿越 → 404 / 非法 code → 404 / 插件不存在 → 404
- 全量 **457 测试通过，0 risky** 保持

### Notes

- 前端 `tenant/PluginCard.vue` 和 `platform/PluginCard.vue` 已写好 `<img v-if="data.icon" :src="data.icon" />` 渲染逻辑 —— 无前端改动需要
- 已安装的 mall / points-exchange 行：`plugins.icon` DB 列需 `UPDATE plugins SET icon='icon.png', manifest=JSON_SET(manifest, '$.icon', 'icon.png') WHERE code IN ('mall','points-exchange')`；新安装直接走 manifest 同步
- 无 schema 改动

---

## [2.7.3] - 2026-06-04

**Hot fix**：修 PluginLoader 中间件传参语法导致租户调任何 app 插件路由都 500。

### Fixed

- **(P1) `PluginLoader` 用错 ThinkPHP 路由中间件传参语法 → 所有插件路由 500**：原写法 `"entitlement:{$entitlement}"` 是 Controller 级 `protected $middleware` 模型才支持的语法（由 `Dispatch::explode(':')` 处理）；route 级 `Rule::middleware([...])` 不解析 `:`，整个字符串被当类名 `make()` → `ClassNotFoundException: class not exists: entitlement:mall`。改为元组形式 `['entitlement', [$entitlement]]` 后 ThinkPHP `buildMiddleware` 在 `is_array` 分支正确解构 `[middleware, params]` → alias 查表 → `handle($req, $next, $code)` 注入参数
- 同步修 `api_entitlement` 同问题
- 修订 `EntitlementMiddleware` / `ApiEntitlementMiddleware` 文档注释中的错误样例（之前注释也写的 `'alias:param'`，会误导读者复制）

### Triggering condition

任何 tenant 调已授权（plan_grants 或 tenant_plugins）的 app 插件路由（如 mall）。bug 自 Phase A 起就在 —— 但单元测试只覆盖 install/uninstall 流程，没跑过 HTTP 请求穿过 `Route::middleware([['entitlement', ...]])` 的完整链路 → 漏检到生产。

### Tests

- 新增 `PluginLoaderMiddlewareTest`（2 用例）—— 源码级 grep 守护：
  - 必须出现 `['entitlement', [$entitlement]]` 元组语法
  - 必须不出现 `"entitlement:{$entitlement}"` 字符串语法（同 `api_entitlement`）
- 全量 **438 测试通过，0 risky** 保持

### 升级动作

无 DB 改动；只需重启 PHP / queue worker 让代码生效。

---

## [2.7.2] - 2026-06-04

Patch：根因修平台 RBAC 隐性漏权 + 修订 v2.6.7/v2.7.1 update.sql 写错的目标表。

### Fixed

- **(issue 1) `PlatformAdmin::getPermissions()` 含 type=2 菜单**：原实现只查 `type=3` 按钮，但 20 个 type=2 菜单行的 `permission` 字段（`plan.view` / `platform.plugin.list` / `platform.refund.list` / `platform.config.list` 等）都是真实接口权限，对应 Controllers 的 `#[Permission]` 注解。非超管角色即使分到对应菜单，调对应 list/view 接口仍 403。**1 行修改 `whereIn('type', [2, 3])`** 一锁解全部平台模块（plugin / plan / refund / config / announcement / generator / cron / file / dictionary / permission / admin / role / menu / audit / log / api_doc）的非超管接口访问
- **(issue 2) v2.6.7 + v2.7.1 update.sql 表名修订**：两份历史 SQL 误把 `platform_menus`（平台菜单）写成 `menus`（租户菜单）+ 用了错字段名（`permission_code` / `tenant_id`）。MySQL 直接报「Unknown column」拒绝执行 → **没有用户数据被污染**。本补丁 in-place 修正两份历史 SQL，并由 v2.7.2/update.sql 一次性 INSERT IGNORE 补齐两批漏 seed（247/248 + 4/6/7 共 5 行）

### Tests

- 新增 `PlatformAdminPermissionsTest`（6 用例，**真 DB**）：type=2 菜单 perm 必须含 / type=3 按钮 perm 仍含 / type=1 目录空 perm 排除 / status=0 排除 / hasPermission(type=2 perm) 返回 true / 超管返回 `*`
- 全量 **436 测试通过，0 risky** 保持

### Database

- `server/database/updates/v2.6.7/update.sql` —— **in-place 修订**到 `platform_menus`
- `server/database/updates/v2.7.1/update.sql` —— **in-place 修订**到 `platform_menus`
- `server/database/updates/v2.7.2/update.sql` —— 一次性补齐 v2.6.7 + v2.7.1 漏掉的 5 行 seed（INSERT IGNORE）
- `server/database/updates/v2.7.2/README.md` —— 升级说明 + 「v2.6.7/v2.7.1 旧 SQL 报 Unknown column 是好事」的历史背景
- 无 schema 改动

---

## [2.7.1] - 2026-06-04

Patch：修 v2.7.0 引入的 markSuccess 唯一索引冲突 + 补 v2.6.7 遗漏的 plan 路由 RBAC。

### Fixed

- **(issue 1) `PluginMigrationLogRepository::markSuccess` 唯一键冲突**：v2.7.0 仅清同方向 failed 行，up→down→up 重跑时 INSERT 同 `(plugin_id, name, direction=up, status=success)` 必然 `Duplicate entry`。修复：表语义改为「当前状态表」—— 每条 migration 最多 1 个 success 行；markSuccess 先清 (plugin, name) 所有 success + 同 (plugin, name, direction) 旧 failed 再 INSERT。`successfulUpNames` 同步简化为单查询（去掉错误的 `array_diff`）。markFailed 同理处理同方向旧 failed 防冲突。无 schema 改动
- **(issue 2) 平台 `plan` 路由 RBAC 旁路**：`server/app/platformapi/route/plan.php` 与 PlanController 同 v2.6.7 之前的 plugin 路由问题 —— 只挂 `platform_auth`，零 `#[Permission]`。任何登录平台用户都能调套餐 CRUD 绕过 RBAC、不写操作日志。本补丁补齐 `platform_permission` + `platform_log` 中间件 + 6 个方法的 `#[Permission]` 注解 + 3 个新按钮权限 seed（`platform.plan.{create,update,delete}`）。index/show/options 复用现有 `plan.view` 不破坏前端菜单

### Tests

- 新增 `PluginMigrationLogRepositoryTest`（5 用例，**真 DB**）：up→down→up 循环不抛 / success 切换方向只剩 1 行 / failed 后 success 清掉 failed / 重复 failed 替换旧 failed / successfulUpNames 仅返回 live up
- 扩 `PluginMigratorTest`：新增 `testUpDownUpCycleWorks`（fake repo 同步新语义） + 重写 fake repo 写入逻辑与真实 Repository 对齐
- 扩 `PlatformPluginRouteMiddlewareTest`：dataProvider 加 `plan` 路由（3 dataProvider 变 4，覆盖 12 断言）
- 全量 **430 测试通过，0 risky** 保持

### Database

- `server/database/updates/v2.7.1/update.sql` —— 幂等 `INSERT IGNORE` 补 menu 行 4/6/7（套餐 CUD 按钮权限）
- `server/database/updates/v2.7.1/README.md` —— 升级说明 + 非超管角色需要重新勾权限的提示
- `server/public/install/data/init.sql` 同步加 seed
- **无** plugin_migrations 表结构改动 —— #1 修的是 Repository 写入语义，旧数据可继续使用

---

## [2.7.0] - 2026-06-04

Minor：插件 migration 状态表 + 安装 saga 补偿。两块都是「正确性→可靠性」演进，不改 API 语义，但有强升级动作（必跑 backfill）。

### Added

- **`plugin_migrations` 状态表**：记录每次 up/down 的 file_hash、耗时、版本、成功/失败。`PluginMigrator` 改为状态感知：
  - `up()` 仅跑未在状态表里有 success 行的 migration（幂等）
  - `down()` 仅回滚状态表里有 success up 的 migration
  - `upgrade()` 在版本范围过滤 + 状态表去重双保险
  - 新增 `pendingUp()` / `verifyHashes()` / `backfillAsApplied()` 公共 API
- **`saas:plugin-migration-backfill` 命令**：把存量 ENABLED 插件的磁盘 migration 文件登记为 success up（不真跑 SQL），供 v2.7.0 升级时一次性对齐
- **`config('saas.plugin_migration_strict_hash')`**：file_hash 漂移默认 warning 不阻断；strict 模式直接 422 拒绝。`.env` 用 `[SAAS] PLUGIN_MIGRATION_STRICT_HASH` 配置
- **Saga 编排基础设施**：`core/saga/CompensatableStep` interface + `core/saga/SagaRunner`。SagaRunner 按倒序触发已成功 step 的 compensate；补偿失败仅记日志 + `compensationErrors()`，不阻断其它清理
- **6 个 install step**（`app/service/plugin/steps/`）：ExtractZip / MigrationUp / LifecycleInstall / MarkEnabled / InstallMenus / EnqueueBuild 各自实现 execute + compensate

### Changed

- **`PluginMigrator::up/down/upgrade` 签名扩展**：增加 `pluginId`、`pluginCode`、`pluginVersion` 参数（必填）。3 个调用方（PluginService / PluginUpgradeService / PluginPurgeService）同步跟签
- **`PluginService::install` 重构为 saga**：原先 7 步直写 try/catch 改为 `$saga->run([6 steps])`。失败时 saga 内部按倒序补偿（删菜单 → migration down → 删目录），外层 catch 仅写 `last_error`（含补偿失败明细）
- **`PluginPurgeService::purge`** 调用 migrator 跟签 + 末尾 `DELETE FROM plugin_migrations WHERE plugin_id=?` 物理清状态表行

### Tests

- 替换 `PluginMigratorTest`：9 用例覆盖状态表新行为（up 幂等、down 仅回滚已 up、失败重试、backfill）
- 新增 `SagaRunnerTest`：4 用例覆盖 happy path / 倒序补偿 / 补偿失败不阻断 / 失败 step 不补偿
- 新增 `PluginInstallSagaCompensationTest`：2 用例覆盖 migration 失败和 lifecycle 失败两个分支的真实副作用补偿（含跨进程的 plugins/<code>/ 目录删除断言 + 业务表 DROP 断言）
- 全量 **421 测试通过，0 risky** 保持

### Database

- `server/database/migrations/20270604000100_create_plugin_migrations_table.php`
- `server/database/updates/v2.7.0/update.sql` —— 幂等 `CREATE TABLE IF NOT EXISTS`
- `server/database/updates/v2.7.0/README.md` —— 升级步骤（含 backfill 强制说明）
- `server/public/install/data/schema.sql` —— 新装直接带 `plugin_migrations`

### 升级动作（强制）

```bash
mysql -uXXX -pXXX <db> < server/database/updates/v2.7.0/update.sql
php think saas:plugin-migration-backfill
```

不跑 backfill：下次 install/upgrade 触发的 migrator.up 会重跑全部 → SQL 报「表已存在」（保护性失败，不会损坏数据）。

---

## [2.6.7] - 2026-06-04

Security + 数据正确性补丁：堵平台插件路由 RBAC 旁路 + 修软卸载→purge 流程迁移回滚被跳过的 bug。

### Security

- **(issue 1) 平台插件 3 个路由全部补挂 `platform_permission` + `platform_log`**：`server/app/platformapi/route/plugin.php`、`plugin_build.php`、`plugin_grant.php` 原本仅挂 `platform_auth` → 任何登录平台用户都能调 upload/install/uninstall/upgrade/purge/grant.sync/build.rebuild 等高危接口绕过 RBAC，且不写操作日志。Controllers 早就声明了 `#[Permission('platform.plugin.*')]`，但中间件没读 → 注解形同摆设。本补丁补齐与 announcement/refund/system/mobile_build 等其它平台路由完全对齐
- **(seed 修复) 补 `platform.plugin_build.list` / `.detail` 按钮权限**：Controller `#[Permission]` 声明了这两个 code，但 `init.sql` seed 漏写。原本被「无 platform_permission 中间件」掩盖，本补丁补齐 + 提供 `database/updates/v2.6.7/update.sql` 给存量升级

### Fixed

- **(issue 2) 软卸载→purge 流程迁移回滚被跳过**：`PluginService::uninstall` 软卸载会删 `plugins/<code>/`，但 `PluginPurgeService::purge` 用 `is_dir($pluginDir)` 守门 `migrator->down()` → 正常流程下 down() 永远跑不到，purge 实际不删表，只清平台关联行。修复：软卸载前 `snapshotMigrationsToGraveyard()` 把 `migrations/` + `plugin.json` 复制到 `runtime/plugin-graveyard/<pluginId>/`；purge 时优先 `pluginDir`、否则回退 graveyard 跑 down()；purge 完成清理 graveyard

### Tests

- 新增 `PluginPurgeGraveyardTest`（3 用例）：软卸载创建快照 + purge 从 graveyard 跑 down 真实删表 + purge 在 pluginDir 存在时仍优先用 pluginDir
- 新增 `PlatformPluginRouteMiddlewareTest`（3 dataProvider × 3 断言 = 9 用例）：3 个路由源码级 grep 守护 `platform_permission` / `platform_log` / `platform_auth` 都挂上 —— 回归保险
- 全量 **410 测试通过，0 risky** 保持

### Database

- `server/database/updates/v2.6.7/update.sql` —— 补 menu 行 247/248（`plugin_build.list` / `.detail` 按钮权限）
- `server/database/updates/v2.6.7/README.md` —— 升级说明 + 影响范围

---

## [2.6.6] - 2026-06-03

Follow-up：补齐 Phase A 顶层目录硬切换 —— 插件 zip 安装器白名单收敛到 `app/` + `tenant/` + `uniapp/`，并同步重打 mall / points-exchange / hello-world 包。

### Changed

- **`PluginPackageInstaller::ALLOWED_TOP_DIRS` 收敛为 Phase A 新布局**：`['app', 'tenant', 'uniapp', 'migrations', 'migration', 'Config', 'config']`。旧顶层目录 `src` / `admin` / `hooks` / `frontend` / `resource` 全部硬拒（与 manifest 的 `admin.*` / `mobile.*` 硬切换风格对齐）
- **顶层 `route.php` 不再接受**：迁移到 `app/{tenantapi,api}/route.php`，与磁盘约定一致
- **`server/plugin_packages/` 三个内置包重新打包**：`mall-1.0.0.zip` / `points-exchange-1.0.0.zip` / `hello-world-1.0.0.zip` 全部按新布局生成，dry-run inspect 通过
- **fixture `tests/fixtures/plugins/hello-world-1.0.0/` 重排**：`src/` → `app/`，`hooks/` → `app/hooks/`（含命名空间 `Plugin\HelloWorld\hooks` 小写化），`migration/` → `migrations/`，新增 `app/tenantapi/route.php` 占位

### Tests

- 新增 `PluginPackageInstallerTest::testLegacyTopDirRejected`（5 dataProvider：src/hooks/admin/frontend/resource）
- 新增 `PluginPackageInstallerTest::testLegacyTopLevelRouteFileRejected`
- 新增 `PluginPackageInstallerTest::testNewLayoutAccepted`（完整新布局接受性回归）
- 全量 **398 测试通过，0 risky** 保持

---

## [2.6.5] - 2026-06-03

Follow-up patch after v2.6.4，处理 3 个一致性 + 测试增强 + 拼写问题。

### Fixed

- **(issue 1) `TenantPluginConfigService` 内部 `findByCode` 拿 entitlement 校验**：v2.6.4 修了 `configSchema` 的语义但 `getConfig` / `updateConfig` 仍把 pluginCode 当 entitlement 校验。现在 Service 参数仍是 plugin code（与 configSchema 一致）；内部 `findByCode` 拿 plugin 行后用 `row.entitlement` 走 `EntitlementService::has`。配置表存取仍按 pluginCode（`plugin_configs` 的索引列）
- **(issue 2) UniApp 模板 `u--` → `u-` 拼写修复**：5 个文件里的 `u--input` / `u--textarea` 改为 `u-input` / `u-textarea`，匹配 easycom 规则 `^u-([^-].*)`。`pnpm run type-check` 不再报「U/Input/Textarea 未定义」
- **(issue 3) sync 软删插件菜单清理增加真实断言**：扩 `test_sync_removes_menus_for_soft_deleted_app_plugin` —— 先用 `AppMenuInstaller::installMenuTemplates` + `copyToTenant` 注入真实租户菜单 → 把插件软删 + sync 移除它 → 断言 `menus` 表里租户那行被 `removeForTenant` 真的拆掉（不仅看 diff）

### Tests

- 扩 `TenantPluginConfigEntitlementGateTest`：增加 2 用例覆盖 entitlement != code 场景 + 未知 code 404
- 扩 `PluginGrantSyncCleansStaleGrantsTest`：菜单清理真实副作用断言（核心 bug 防御）
- 全量 **391 测试通过，0 risky** 保持

---

## [2.6.4] - 2026-06-03

Follow-up patch after v2.6.3，处理 3 个边界与一致性问题。

### Fixed

- **(issue 1) `PluginGrantService::sync` removed 分支用 `findWithTrashedById` 兜底软删插件**：v2.6.3 修了 `sync.diff` 能看到残留 grant，但 removed 分支用 `pluginRepo->find($pid)` 走 `SoftDelete` trait，对已软删插件返回 null → `kind=app` 判断失败 → `removeForTenant()` 不执行 → 菜单残留。新增 `PluginRepository::findWithTrashedById()`，sync removed 分支改用它兜底
- **(issue 2) `TenantMobileConfigService::save` 部分更新防御**：仅改 `home_app_code` 不传 `home_page` 时直接 422 拒收 — 否则旧 `home_page` 残留在 DB，与新 code 不匹配形成脏配置。同 code 提交不带 `home_page` 仍允许（兼容 「只改其它字段」）。同时 `save()` 末尾改为 `return $this->get($tenantId)` —— 与 `get()` 视图一致，调用方拿不到 raw DB 字段（如 `tabbar_json`）
- **(issue 3) `PluginController::configSchema` URL 参数语义豁清**：路由 `:code` **始终是 plugin code**（与磁盘 `plugins/{code}/` 目录命名一致）。Controller 先 `findByCode` 拿到插件行，再用 `row.entitlement` 走 `EntitlementService::has` —— 兼容 entitlement 与 code 不同的插件；同时 `schema.json` 路径用真实 code（即参数）

### Tests

- 扩 `PluginGrantSyncCleansStaleGrantsTest`：新增 `test_sync_removes_menus_for_soft_deleted_app_plugin` + `findWithTrashedById` 直接覆盖
- 扩 `TenantMobileConfigServiceTest`：3 个新用例 —— 部分更新拒收 + `save()` 返回与 `get()` 一致 + 同 code 部分更新通过
- 全量 **389 测试通过，0 risky** 保持

---

## [2.6.3] - 2026-06-03

Follow-up patch after v2.6.2，处理 3 个下游一致性问题。

### Fixed

- **(issue 1) `PluginGrantService::sync()` 用 raw grants 做 diff**：v2.5.1 给 `listByPlan()` 加上「`p.status=ENABLED AND deleted_at IS NULL`」过滤后，sync 的 oldRows 看不到「插件先 grant 后被全局禁用」的残留行，diff 不包含它 → 下游 reconcile 永远拆不掉死菜单。新增 `PluginGrantRepository::listRawByPlan()` 不过滤插件状态，专供 sync.diff / 迁移脚本；`listByPlan()` 行为不变继续供 `EntitlementService` 用
- **(issue 2) `SaasPluginMenuReconcile` 购买分支补 plugin 状态过滤**：与 v2.6.2 修复的 `TenantService::tenantOwnsViaPurchase` 对齐 — 购买路径同样要 `p.status=ENABLED AND deleted_at IS NULL`。手动 reconcile 时已下架但租户曾购买的 app 插件不再被认为「应有菜单」
- **(issue 3) `PluginController::configSchema` 加权益门**：与 `getConfig` / `updateConfig` 对称。`EntitlementService::has` 校验后才返回 schema；schema 不含敏感信息但加门是为一致性（避免「能读 schema 但读不到配置」UX 矛盾）

### Tests

- 新增 `PluginGrantSyncCleansStaleGrantsTest`（3 用例）：raw 包含禁用插件 + listByPlan 仍过滤 + sync 真的拆掉残留 grant
- 全量 **385 测试通过，0 risky** 保持

---

## [2.6.2] - 2026-06-03

Follow-up patch after v2.6.1，处理深度 review 的 5 个权益边界问题 + 1 个测试质量。

### Fixed

- **(issue 1) 后端强校验 `allowHome`**：`TenantMobileConfigService::save()` 设置 `home_app_code` 时除权益 + 路径校验，新增 `pluginAllowsHome()` 校验。前端 `MobileEligibilityService` 隐藏只是 UX；后端门必须独立把守
- **(issue 2) `get()` 全方位过滤**：除了权益失效，新增「路径仍在 `manifest.uniapp.pages` 内 + `allowHome=true` / `allowTabBar=true`」校验。覆盖 manifest 改了页面 / 关闭字段后旧 DB 配置静默失效场景。失效项软隐藏，DB 不动
- **(issue 3) `TenantPluginConfigService` 用 `EntitlementService::has` 校验**：`getConfig()` 与 `updateConfig()` 入口都改用权益服务做门，不再依赖 `listByTenant()`（后者包含历史 active 但插件已下架的行）。租户无法读 / 写已失效插件配置
- **(issue 4) `tenantOwnsViaPurchase` 过滤插件全局状态**：`PluginGrantService` 与 `TenantService` 中两份同语义方法都加 JOIN `plugins.status=ENABLED AND deleted_at IS NULL`。对称 v2.5.1 #1 与 v2.6.1 #1，杜绝「插件下架但租户购买记录仍 active → 死菜单保留」
- **(issue 5) `PluginGrantService::sync()` 严格拒收禁用 / 软删插件**：plugin_id 状态非 ENABLED 或已软删 → 抛 `BusinessException(422)`，列出违规 code。DB 不再有死 grant，避免平台超管误判
- **测试质量：77 个 risky test 全部消除**：`tests/TestCase` 基类 tearDown 精确 `restore_error_handler() + restore_exception_handler()` 各 1 次（ThinkPHP `\think\initializer\Error::bootstrap` 注册的数量），匹配 PHPUnit strict mode 期望

### Refactor

- `TenantMobileConfigService::pluginAllowsTabBar` 抽出 `pluginAllows($code, $flag)` 与新增的 `pluginAllowsHome` 共享 manifest 读取逻辑

### Tests

- 新增 4 个：`PluginGrantSyncRejectsDisabledTest`（grant 写入拒收 + 一次性报错）+ `TenantPluginConfigEntitlementGateTest`（配置读写权益门）
- 扩 `TenantMobileConfigServiceTest`：5 个新用例覆盖 `allowHome` 强校验 + `get()` manifest 改动后过滤
- 全量 **382 测试通过，0 risky**

---

## [2.6.1] - 2026-06-03

Follow-up patch after v2.6.0 Phase D，处理 5 个深度 review 发现。

### Fixed

- **(issue 1) `TenantPluginRepository::listActive` 过滤插件全局状态**：v2.5.1 修了套餐授权（plugin_grants）这条路径，但租户单独购买 / 手动启用（tenant_plugins）走的是另一条 `listActive()`。此函数同样加 `p.status=ENABLED + p.deleted_at IS NULL` 过滤。修复后插件在全局被禁用 / 软卸载后，租户即使单独购买记录仍 active 也不会被算作权益
- **(issue 2) 移动端配置脏数据按当前权益过滤**：`TenantMobileConfigService::get()` 返回前调用新的私有 `filterByEntitlements()`：失去权益的 `home_app_code` 与 `home_page` 清空、tabbar 数组过滤掉无权益项。**DB 保留原始数据**（租户重新获得套餐时自动恢复，避免误删）。API 响应与构建期产物（`PagesJsonGenerator` / `TenantConfigWriter`）都用过滤后的值
- **(issue 3) `PluginUniappCopier` 缺失源码抛 BusinessException**：插件 manifest 声明了 `uniapp.subpackage/pages` 但 `server/plugins/{code}/uniapp/` 不存在时，不再静默 `continue`。改为抛 `BusinessException(500)`，消息包含插件 code、subpackage、期望路径。避免后期 `uni build` 才报「页面不存在」难定位
- **(issue 4+5) 「模式 B 为准」运行时刷新策略文档化**：构建期注入（tenantConfig.tenantId > 0）的产物 store **不再**被 `/api/mobile/config` 覆盖；stub 模式（tenantId=0，开发态）才会触发 `load()`。运行时无 `uni.setTabBarItem` 链路，tabBar 仅在独立构建产物里生效。这是有意设计：「同一场景两套真相」是更大的问题
- **租户后台 UI 文案**：「移动端配置」页加 banner 提示「配置变更需重新构建才会下发」；「构建记录」触发对话框文案对齐「模式 B」语义

### Tests

- 扩 `PluginGrantEntitlementFilterTest`：新增 `test_tenant_plugins_listActive_excludes_disabled_and_deleted`
- 扩 `TenantMobileConfigServiceTest`：新增 2 个测试覆盖 `get()` 过滤失效 home / tabbar 项
- 新增 `PluginUniappCopierMissingSourceTest`：缺失源码抛错 + 错误消息完整性
- 全量 369 测试通过

### Not in scope

- 运行时 `uni.setTabBarItem` 链路（双模式同步）— 与 v2.6.1 选择的「模式 B 为准」冲突，不做
- 套餐变更 hook 主动清理 DB 脏字段 — 误删不可逆，采用「软隐藏」策略

---

## [2.6.0] - 2026-06-02

Phase D：异步构建队列 + error_log 脱敏。

### Added

- **`app/job/MobileBuildJob`**：仿 PluginBuildJob 模板。`fire()` 调用 `TenantMobileBuildService::run(buildId)` + 异常兜底
- **独立 mobile-builds queue**：`config/queue.php` 已支持，本期接入 worker
- **`docker/php/supervisord.conf` 新增 `mobile-build-worker`**：`queue:work --queue=mobile-builds --tries=1 --timeout=900 --sleep=3 numprocs=1`。与默认 queue-worker（60s 超时）解耦，避免长任务被强杀；numprocs=1 避免两个 pnpm install 同时拉 node_modules 抢占磁盘 IO
- **`core/mobile/ErrorLogSanitizer`**：DB 入库 error_log 前脱敏：
  - 异常 → message + 浅层 trace（class::method:line × 5），剥掉 args
  - 完整 trace 仍写 Log::error 供运维查看
  - 绝对路径替换为 `/<hash>/{basename}`
  - 私钥扩展名（`.key/.pem/.crt/.p12`）整体打码为 `<redacted>`
  - 50KB 截断

### Changed

- **`TenantMobileBuildService::enqueue`** 末尾 dispatch 到 `mobile-builds` 队列，不再依赖 Controller 同步调 `run()`
- **`MobileBuildController::create`** 移除 `$buildService->run()` 同步调用；接口立即返回 `status=queued` 行
- **租户后台「构建记录」页**：触发后立即返回；列表自动 3s 轮询 queued/running 行，全部完结后自动停止；详情面板同步刷新
- **`DefaultMobileBuilder` + `WechatMiniprogramUploadService` + `Service::run`** 所有写 `error_log` 的位置改用 `ErrorLogSanitizer`

### Tests

- 新增 `ErrorLogSanitizerTest`（4 用例）：路径替换、敏感扩展名打码、50KB 截断、相对路径保留
- `TenantMobileBuildServiceTest::test_enqueue_does_not_invoke_builder`：注入抛异常的 Builder 验证 enqueue 不同步执行
- 全量 365 测试通过

### Migration / Deployment

升级生产环境必须做的：

1. `docker compose down && docker compose up -d`（让新 supervisord 配置生效，启动 mobile-build-worker）
2. 验证 worker 运行：`docker exec <php_container> supervisorctl status` 应见 `mobile-build-worker:RUNNING`
3. `.env` 可调 `MOBILE_BUILD_TIMEOUT_SEC=600`（worker 进程超时 900s 留余量）

### Not in scope (future)

- 微信第三方平台 OAuth 代商家授权（免租户上传私钥）
- 体验码 URL 从 miniprogram-ci output 解析
- 多 mobile-build worker 并发（当前 numprocs=1）

---

## [2.5.1] - 2026-06-02

Follow-up patch after v2.5.0 三阶段全量交付，修复 6 个主要问题 + 1 个防御性问题。

### Fixed

- **(issue 1) PluginGrantRepository 套餐授权过滤插件状态**：`listByPlan` / `listForPlan` / `listByPlanForTenant` 增加 `p.status=ENABLED` 与 `p.deleted_at IS NULL` 过滤。插件全局禁用 / 软卸载后历史授权记录不再被 `EntitlementService` 算作租户权益，移动端配置 / 编译也不会拿到它
- **(issue 2) TenantMobileConfigService 路径白名单**：`save()` 时 `home_page` 与每个 `tabbar[i].path` 必须命中插件 `manifest.uniapp.subpackage + pages[].path` 白名单；防止租户保存未编译页面 / 错误页面 / code 与 path 不匹配的配置
- **(issue 3) TemplateCopier 排除 src/modules 符号链接**：独立构建从干净壳开始，跳过 dev 期 `scripts/sync-plugin-uniapp.mjs` 注入的所有插件 symlink；新增 `PluginUniappCopier` 按权益单独复制 `server/plugins/{code}/uniapp` → `src/{subpackage}/`。未授权插件源码不再泄漏进构建目录
- **(issue 4) PagesJsonGenerator 注入租户 tabBar**：`generate(tenantId, dir, tabbar)` 新签名，构建期把 `tenant_mobile_configs.tabbar_json` 改写到 `pages.json.tabBar.list`。空 tabbar 时保留 `pages.base.json` 默认（不破坏开发态）
- **(issue 5) UniApp store 闭环 tenant-config.ts**：store 初始值来自构建期注入的 `src/generated/tenant-config.ts`（仓库 commit 一份 stub，TenantConfigWriter 在产物里覆盖）；`load()` 再从 `/api/mobile/config` 拉取覆盖。独立构建产物首屏不闪烁，运行时仍可改配置即时生效
- **(issue 6) 弃用 uniapp.tabBar 字段**：`PluginManifestValidator` 把 `uniapp.tabBar` 加入硬拒绝清单；mall 插件同步移除字段。语义唯一：`allowTabBar` = 该插件能否作为租户 tabBar 项；不再有 `tabBar:false` + 默认 `allowTabBar:true` 的矛盾配置
- **(issue 8) PluginManager.bootAll 守卫**：boot 阶段对每个 plugin.json 跑 `PluginManifestValidator::validate`；失败仅跳过该插件 + 写 `error_log`，DB status 不动。防御「已启用插件 manifest 被手工改坏」破坏运行时

### Changed

- `app/provider.php` `MobileBuilder` 绑定中 `DefaultMobileBuilder` 构造函数新增 `PluginUniappCopier`
- `PluginManager` 构造函数新增 `?PluginManifestValidator $validator = null`；`AppService` 注入

### Tests

- 新增 `PluginGrantEntitlementFilterTest`（3 用例）：禁用 / 软删插件不计入权益
- 新增 `PluginManagerBootValidatorTest`（2 用例）：boot 时坏 manifest 被跳过
- 新增 `TenantMobileConfigServiceTest` 4 个 path 白名单用例
- 新增 `PluginManifestValidatorTest` 弃用字段拒绝用例
- 全量 360 测试通过

### Not in scope (Phase D 候选)

- 异步构建队列（`TenantMobileBuildService::run` 当前仍同步阻塞 HTTP worker）
- `DefaultMobileBuilder` 把 stack trace 全文写 DB `error_log` 的脱敏

---

## [2.5.0] - 2026-06-02

### Added — 移动端多租户独立编译 + 小程序上传 + H5 发布（Phase C）

**核心闭环**：租户后台触发构建 → 按权益生成产物 → H5 发布到子域名 / 小程序上传体验版。

**数据库 / 表结构**：
- 新表 `tenant_mobile_builds`：每次构建一行（plan_id 快照 + platform + build_no + status + 产物路径 + 上传结果 + 错误日志）。状态机：`queued → running → success/failed → uploaded/released`
- `tenant_mobile_configs.wechat_upload_key_ciphertext`：AES-256-CBC 加密存储租户的小程序上传私钥
- 新权限：`mobile.build.view` / `mobile.build.create` / `mobile.build.release` + `platform.mobile.build.view` / `platform.mobile.build.manage`
- 新菜单：「构建记录」挂在「移动端配置」同级

**后端**：
- `TenantMobileBuildService` 编排（仿 `PluginBuildService`）：`enqueue`（写 queued 行 + 自增 build_no）+ `run`（流转状态机，幂等）+ `forceFailStuckRunning`
- `core\mobile\DefaultMobileBuilder` 串联 5 个模块：
  - `TemplateCopier`：复制 `uniapp/` 到 `runtime/mobile-builds/{tenant_id}/{build_id}/`，跳过 `node_modules`/`dist`
  - `PagesJsonGenerator`：按 `EntitlementService` 筛选 + 合并租户拥有的插件 `uniapp.pages`
  - `ManifestInjector`：注入 `app_name` 与 `wechat_appid`（仅 mp-weixin）
  - `TenantConfigWriter`：生成 `src/generated/tenant-config.ts`，启动期注入运行时配置
  - `UniBuildRunner`：Symfony Process 调 `pnpm install + pnpm run build:{platform}`，可控超时（`MOBILE_BUILD_TIMEOUT_SEC`）
- `H5ReleaseService`：将成功的 H5 产物拷贝到 `server/public/mobile-tenants/{tenant_code}/`（nginx 静态服务）
- `WechatUploadKeyService`：AES-256-CBC 加密 / 解密私钥；调 miniprogram-ci 前临时落盘、用完立即覆盖+删除
- `WechatMiniprogramUploadService`：调用 `miniprogram-ci upload`，写 `upload_result_json`
- `tenantapi`：`GET/POST /tenantapi/mobile/builds`、`/:id/release`、`/:id/upload`、`PUT/DELETE /tenantapi/mobile/config/wechat-key`
- `platformapi`：`GET /platformapi/mobile-builds`（全局监控）+ `POST .../force-fail`（卡住的 running 强制收尾）

**租户后台 UI**：
- `tenant/src/views/mobile-config/builds.vue`：构建记录列表 + 状态标签 + 详情面板（日志、上传结果）+ 触发按钮 + 私钥上传/清除

**运维**：
- `php think saas:mobile-build-prune` 命令：每租户每平台保留最近 N 个（默认 `MOBILE_BUILD_KEEP_N=5`）+ 截断 90 天以上的 error_log 到 50KB
- 容器编排：构建期间需要 `node ≥ 18` 与 `pnpm` 在 PATH

**TDD**：
- 10 个 Build Service 端到端测试（enqueue 状态机、build_no 自增、run 流转、forceFail）+ 11 个 Phase B Mobile Config 测试持续绿

### Changed

- `app/provider.php`：绑定 `core\mobile\MobileBuilder` → `DefaultMobileBuilder`（测试通过反射注入 fake）

### Limitations / Known issues

- 当前 `run()` 是同步执行（Controller 创建即跑），大并发场景应改为 ThinkPHP queue + Job
- 体验码（quote/qrcode）在 miniprogram-ci 输出里不一定有，目前只记上传成功与否；二维码需租户在微信公众平台手工查看
- 没有自动提交审核接口（微信不允许第三方代提交，租户必须手工在公众平台操作）

---

## [2.4.0] - 2026-06-02

### Added — 移动端运行时配置层（Phase B）

- **新表 `tenant_mobile_configs`**：一对一存储租户的 `app_name` / `app_logo` / `theme_color` / `home_app_code` / `home_page` / `tabbar_json` / `wechat_appid`（后者留给 v2.5.0 Phase C）
- **后端 Service 三件套**：`TenantMobileConfig` Model + Repository + Service（含 11 个 TDD 测试覆盖权益门、tabBar 交叉校验、JSON 字段 round-trip）
- **新 Service `MobileEligibilityService`**：按当前租户权益 + 插件 `manifest.uniapp.allowHome/allowTabBar` 计算可选首页 / tabBar 插件清单
- **tenantapi**：`GET/PUT /tenantapi/mobile/config`、`GET /tenantapi/mobile/config/eligible`（权限 `mobile.config.view`/`update`）
- **api**：`GET /api/mobile/config`（C 端公开读，过滤 `wechat_appid`/`status`）
- **租户后台 UI**：`tenant/src/views/mobile-config/index.vue` 4 块卡片（应用信息、主题、启动首页、tabBar）+ `mobile-config.ts` API 封装
- **菜单 + 权限种子**：`init.sql` 加菜单 `mobile.config`（挂在「系统管理」下）+ 权限 `mobile.config.view`/`update`；`updates/v2.4.0/update.sql` 含模板行 + 复制到存量租户的 SQL
- **UniApp Pinia store** `useMobileConfigStore`：启动时 `App.vue::onLaunch` 拉一次 `/api/mobile/config`，主题色应用到 `setNavigationBarColor` + H5 `--theme-color` CSS 变量
- **UniApp 启动 redirect**：`pages/index/index.vue::onShow` 读 `homePage`，配置则 `redirectTo`，未配置则保留原首页

### Fixed

- 测试库切换 bug：`tests/bootstrap.php` 和 `tests/TestCase.php` 之前虽然把 `$_ENV['DB_NAME']` 切到 `_test` 库，但 ThinkPHP `Env::load()` 会从 `.env` 反向覆盖回 dev 库，导致所有测试实际跑在 dev 库上。修复：在 `App::initialize()` 后强制覆盖 `Config::set('database.connections.mysql.database')` + 通过 reflection 重置 `DbManager::$instance` 数组，使测试真正落到 `_test` 库
- `SaasBackfillGrantsTest` 在 v2.3.1 已 DROP `plans.features` 列的环境上 markTestSkipped，避免 schema 漂移导致的伪失败

---

## [2.3.0] - 2026-06-01

### Changed — 插件 manifest 规范统一（破坏性）

**BREAKING CHANGE**：plugin.json 字段命名硬切换。所有插件作者必须按新规范同步更新 manifest。

- `admin.menus` → `tenant.menus`
- `admin.panels` → `tenant.panels`
- `admin.permissions` → `tenant.permissions`
- `mobile.*` → `uniapp.*`（含 `subpackage` / `pages` / `tabBar`，新增 `allowHome` / `allowTabBar`）
- `routes.tenantapi` 不再必填，改为「`tenantapi` 与 `api` 至少一个」
- 插件目录从 `src/` 改为 `app/`，并强制拆分为：
  - `app/tenantapi/controller/`、`app/api/controller/`（B 端与 C 端控制器分开）
  - `app/service/`、`app/repository/`、`app/model/`、`app/hooks/`（共享，全小写）
- 路由文件位置：`route.php` → `app/tenantapi/route.php`；`api-route.php` → `app/api/route.php`

### Added — 插件 C 端 API 中间件分离

- 新增 `app\api\middleware\ApiEntitlementMiddleware`，注册别名 `api_entitlement`
- `PluginLoader` 的 `/api` 路由组改用 `api_entitlement:{code}`（与 B 端 `entitlement` 中间件区分）
- C 端无超管 bypass，匿名访客与登录用户走同一权益门
- `uniapp.allowHome` / `uniapp.allowTabBar` 字段（默认 `kind=app→true`，`kind=plugin→false`，可显式 override）
- mall 插件 `app/api/controller/MallProductController`（只读 list + detail）

### Migration

升级前备份。详见 `server/database/updates/v2.3.0/README.md`：

- `plugins` 表新增 `entitlement varchar(80)`（默认 = code，含 `idx_entitlement` 索引）
- `plugins` 表新增 `depends json`（manifest.depends 快照，反向依赖图查询用）
- 增量 SQL 与 migration 双轨：`migrate:run` 或手工跑 `update.sql` 均可
- v2.3.1 计划单独 DROP `plans.features`（在 `saas:backfill-grants` 完成后）

### Removed

- 旧 manifest 字段读取兼容路径全部下线（`admin.*` / `mobile.*` 直接报错并提示新字段）

---

## [2.2.0] - 2026-05-18

### Added — 应用/插件拆分 + Tenant Topnav 布局 + 演示包

- **插件类别（`plugins.kind`）**：拆分为 `app` / `plugin`
  - `app` 安装后通过 `AppMenuInstaller` 自动写入菜单模板（`tenant_id=0`）并按 `plugin_grants → tenants.plan_id` 复制到已授权租户
  - `plugin` 不贡献菜单，仅在租户「应用管理」展示
  - 新增 `plugin_menus` 关联表 + `menus.code` 列 + `menus.uq_tenant_code` 唯一索引
- **manifest schema 扩展**：`kind` 枚举 + `menus[]` 数组（code/name/path/parent_code/icon/sort/component/permission），`PluginManifestValidator` 加 6 个用例覆盖
- **Tenant Topnav 布局**：第 3 种布局模式 `topnav`（顶部横排一级菜单 + 左侧二级），设为默认。`layout.store` 持久化当前一级 key，刷新保留选中态。原 `classic` / `sidebar` 保留
- **Tenant 应用管理重做**：卡片网格 + 详情页 + schema-driven 配置表单（`SchemaForm.vue`）。后端 `tenantapi/plugin/{code}/config-schema` 读取插件 `Config/schema.json`
- **Platform 端 Tab + 卡片管理页**：`platform/src/views/plugin/index.vue` Tab 切换 App/Plugin + 4 列卡片网格 + 3-dot 下拉操作菜单（PluginCard）
- **演示包 `plugins/mall/`**：App 类型，贡献「商城」一级菜单 + 商品/分类/订单 3 个子菜单；后端 3 model/repo/service/controller；migration 建 `mall_products/categories/orders` 三表；下单时调用 `plugin.sms`（容器绑定）
- **演示包 `plugins/sms/`**：Plugin 类型，注册 `plugin.sms` 容器服务（mock 实现，仅写 log）；`Config/schema.json` 定义配置表单结构

### Changed — 区域 / 应用版本 后端 + 前端从 tenant 平移到 platform

- 后端：`platformapi/v1/region/RegionController` + `platformapi/v1/version/AppVersionController`（含 service/repository/validate/route）
- 前端：`platform/src/views/{region,version}/` + `platform/src/api/{region,version}.ts`
- `app_versions` 表去掉 `tenant_id` 列（regions 原本就是全局表，无需改动）
- Tenant 「插件商城」菜单更名为「应用管理」（路径 `/plugin`），仅显示 `kind=plugin`
- `PluginRepository::listWithKind` + `PluginGrantRepository::listByPlanForTenant` 加 kind 过滤

### Removed — Tenant 错位功能下线

- `tenantapi/v1/region/`、`tenantapi/v1/version/` 控制器 / service 引用 / 路由 / 权限种子 / 菜单种子
- `tenant/src/views/region/`、`tenant/src/views/version/`、`tenant/src/api/{region,version}.ts`
- 对应 locale 条目（`AppRegion`/`AppVersion`/`regionMgmt`/`versionMgmt`）和类型定义

### Migration

升级前备份。详见 `server/database/updates/v2.2.0/README.md`：

- 加 `plugins.kind` + `menus.code` + 建 `plugin_menus` 表
- 去 `app_versions.tenant_id` 列（脚本中含安全 SELECT 校验提示）
- 重命名 「插件商城」 菜单 → 「应用管理」
- 删除 tenant 端 region/version 菜单种子
- 手工补充 platform 端 region/version 菜单种子 + 权限（参考 init.sql 新增段）

---

## [2.1.0] - 2026-05-17

### Added — 插件系统 Stage 6

- **插件升级**：zip 上传新版 → 自动 backup → 解压 → 跑升级 migration → Lifecycle.upgrade → 失败回滚到 `.backup` 旧版
  - `PluginUpgradeService::upgrade(int $pluginId, string $tmpZipPath)` 编排
  - `PluginMigrator::upgrade($pluginDir, $from, $to)` 按文件名 `v1.1.0_xxx` 版本前缀选区间
  - 平台 UI：列表加"升级"按钮 + UpgradeDialog 上传弹窗
- **插件单独购买**：
  - `TenantPluginService::activatePurchased($tenantId, $pluginId, $months)` 含 `expired_at` 延期叠加
  - `SaasOrderService::createPluginOrder($tenantId, $pluginId, $months, $amount)`；`markPaid` type=4 切到 `TenantPluginService::activatePurchased`
  - 租户 UI：插件商城 `type=4` 插件显示"购买"按钮 + PurchaseDialog
- **插件数据清理**：
  - `PluginPurgeService::purge($pluginId, force=true)`：仅在已软卸载行上 + `force=true` 时执行；跑 migrator.down + 物理删 5 张相关表行
  - 平台 UI：列表已软卸载行显示"清理数据"按钮（二次确认）

### Removed — 旧 addon 系统全面下线

- 数据库：`plan_features`、`tenant_addons`、`tenant_addon_configs` 三张表
- 数据库：`plans.features` JSON 字段（已迁移到 `plugin_grants` 表）
- 服务端代码：`AddonService` / `AddonConfigService` / `PlanFeatureService` / 三个 Repository / 三个 Model / `PlanFeatureController` / `PlanFeatureValidate` / `AddonController` + 路由 / `SyncBuiltinPluginsCommand`
- 服务端代码：`SaasOrderService::createAddonOrder`（被 `createPluginOrder` 替代）
- 前端模块：`tenant/src/api/addon.ts` + `tenant/src/views/addon/`
- 前端模块：`platform/src/api/plan-feature.ts` + `platform/src/views/plan-feature/`
- 前端逻辑：plan 编辑器去掉"功能开关"分组 UI（被 Stage 3 `PluginGrantPanel` 替代）

### Changed

- `saas_orders.addon_feature_id` 列重命名为 `plugin_id`
- `SubscriptionService::extend()` 移除 `AddonService.syncExpiration` / `deactivateOverlapping` 调用
- `TenantFeatureService::listFeatures()` 不再合并 `tenant_addons.feature_codes`

### Migration Notes

升级前请备份 addon 系统三张表：

```bash
mysqldump -uroot -p <dbname> plan_features tenant_addons tenant_addon_configs > addon_backup_v2.0.0.sql
```

然后执行升级脚本：

```bash
mysql -uroot -p <dbname> < server/database/updates/v2.1.0/update.sql
```

---

## 版本历史

## [0.4.0] - 2026-04-08

### Added — M4 部署基础设施 + 文档

- **Nginx 多 vhost 配置**（`docker/nginx/default.conf`）：
  - `admin.$SAAS_ROOT_DOMAIN` → 平台超管后台（`platform/dist` + `/platformapi` + `/api/saas/notify` 支付回调）
  - `*.$SAAS_ROOT_DOMAIN`（正则通配，捕获 `$tenant_code`）→ 租户后台（`tenant/dist` + `/tenantapi` + `/api`）
  - `$SAAS_ROOT_DOMAIN` / `www.$SAAS_ROOT_DOMAIN` → 公共前台（`pc/.output/public` + `/api`）
  - `default_server` 返回 444 防 IP 直连暴露
  - 抽 `docker/nginx/includes/saas_common.conf` 共用片段（安全头 / gzip / `/storage` / `/uploads`）
- **`docker/nginx/entrypoint.sh`**：纯 POSIX sed 把 `SAAS_ROOT_PLACEHOLDER` 替换成 `$SAAS_ROOT_DOMAIN` 环境变量（避免 alpine 默认不带 envsubst）
- **`docker/docker-compose.yml` 更新**：nginx service 新增 `platform/dist` / `tenant/dist` / `pc/.output/public` 三个 bind mount；`entrypoint` 改为 `/docker-entrypoint-saas.sh`；`SAAS_ROOT_DOMAIN` 从 `.env.docker` 注入
- **`docker/.env.docker`** 追加 `SAAS_ROOT_DOMAIN` 配置项
- **`DEPLOYMENT.md`** — 13 步生产部署指南（DNS 配置 → .env 填写 → 前端构建 → Docker compose → 迁移 + 种子 → 创建平台超管 + 首个租户 → HTTPS Certbot → cron 设置 → 常见问题排查 → Roadmap）
- **`README.md`** SaaS positioning：标题改为 `ydadmin-saas`，顶部声明 soft fork，新增 "SaaS 特性" 章节列出 11 条 SaaS 专属能力
- **`ARCHITECTURE.md`** 新增 "SaaS 多租户架构" 章节（约 150 行）：物理三域名隔离 / 后端三 API app / TenantContext 自动 scope / 双 JWT secret / lifecycle 状态机 / 计费数据模型 / 配额 + 功能开关 / 支付抽象层 / 红线测试清单 / 前端 SaaS 组件

### Deferred — 下个 milestone
- pc/ 和 uniapp/ 的前端 SaaS 多租户改造（目前通过后端 JWT + TenantContext 中间件被动 scope 工作正常）
- pc/ 和 uniapp/ 的 `v-feature` 前端守卫
- SSL/Certbot 自动化
- 水平扩展多 PHP 容器 / Kubernetes 配置
- E2E 多域名集成测试
- 平台可视化监控（Grafana 面板）

## [0.3.0] - 2026-04-08

### Added — M3 SaaS 计费闭环

#### M3A — 订阅 / 订单数据层 + 生命周期 cron

- **三张表**：`subscriptions`（订阅记录，append-only）/ `saas_orders`（SaaS 订单）/ `tenant_usage_daily`（每日用量快照）
- **`SubscriptionService`** — `createInitial` / `extend` / `getCurrent` / `listOfTenant`，DB 事务保证 `subscriptions` + `tenants.expires_at` 一致
- **`SaasOrderService`** — `createOrder` / `markPaid` / `markCancelled` / `markRefunded` 状态机
- **`TenantService.create` 重构**：改为 tenant 创建 + `SubscriptionService.createInitial` 两步一事务
- **CLI `saas:tenant-lifecycle`** — 每小时扫描 `expires_at`，自动处理 grace / frozen 状态转移 + 写 `tenant_usage_daily` 快照
- **`SaasOrderFlowTest`** 2 个集成测试

#### M3B — 支付网关 + 回调 + 续费端点

- **`core/saas/payment/SaasPaymentGateway`** — 平行于 `core/payment/PaymentManager` 的 SaaS 级支付网关，复用 `WechatPayDriver` / `AlipayDriver`，但配置源从 `config/saas.php`（平台级）而非 `system_configs`（租户级）
- **`core/saas/payment/driver/MockDriver`** — 测试用 Mock driver，通过 `setDriver` 注入
- **`SaasOrderService::createPayment`** — 调用网关 `driver->create()`，回写 `prepay_id`
- **`SaasOrderService::handleCallback`** — 验签 + 幂等（已支付直接返回 true）+ 金额校验（整数分比较，防 double 精度陷阱）+ `markPaid` TOCTOU 防护 + 非 paid 终态 ACK + 空 `trade_no` 拒绝
- **tenantapi/subscription endpoints**：`current` / `create-order` / `pay` / `query-order`，服务层 `expectedTenantId` 硬校验 + 跨租户 / 不存在统一 404 响应 + 输入白名单校验 + `queryOrder` 用 `projectOrder` 投影隐藏 `prepay_id` / `transaction_id` 等内部字段
- **`/api/saas/notify/{wechat,alipay}` 匿名回调路由** — `SaasPaymentGateway::channel()` 先取 driver fail-fast，然后 `handleCallback`；异常 catch 后日志记录 + 非 200 响应让平台重试
- **platformapi/orders endpoints**：`index` / `show` / `mark-paid` / `cancel` / `refund`，`refund` 只推进状态机不调用 gateway
- **CLI `saas:order-cleanup`** — 原子条件 UPDATE 批量取消 `status=1 AND expired_at < NOW() - 60s` 的订单，避免和回调竞态
- **`SaasPaymentFlowTest`** 3 个集成测试（完整流程 / 金额篡改 / 过期订单）

#### M3C — 配额 + 功能开关 + 红线 7/8

- **`TenantQuotaService`** — `usage` / `assertCanStore` / `consume` / `release`，原子 inc/dec 用 `Db::table('tenants')->inc()`，release 用 `GREATEST(0, CAST(... AS SIGNED) - ?)` 防下溢
- **`TenantFeatureService`** — `hasFeature` / `hasAnyFeature` / `requireFeature` / `listFeatures`，per-request 缓存按 `tenant_id` 隔离，读取路径兼容 middleware nested `raw` 和测试扁平两种 shape
- **`UploadController.handleUpload`** 首行调 `assertCanStore`
- **`FileService.recordFile`** 成功后 `consume`，`deleteFile` DB 删除确认后 `release`（物理删除失败不影响配额，悲观策略）
- **`CodeGeneratorController`** 4 个端点共用 `assertFeature()` 私有 helper 调 `requireFeature('code.generator')`
- **`init.sql` `plans.features` 填充**：free `[]` / basic `["wechat.official", "sms.send"]` / pro 全部 5 个 feature
- **红线测试 Test7**（存储配额硬拦截）5 个 case + **Test8**（功能开关后端守卫）5 个 case

#### M3D — 前端续费 UI + 订单管理 UI

- **`tenantapi/subscription/plans`** 公共套餐列表端点 + **`PlatformAuthService.info`** 追加第 5 项菜单 "订单管理"
- **`qrcode` npm 包** + tenant 类型扩展（`PlanInfo` / `SubscriptionInfo` / `SaasOrderInfo` / `PaymentData` / `PaymentResponse`）
- **`tenant/src/api/subscription.ts`** — 5 个方法封装 M3B 后端端点
- **`tenant/src/views/subscription/index.vue`** — 重写 M2D shell：套餐 radio + 月数 radio-group + 渠道 radio + 金额预览 + 立即支付按钮
- **`tenant/src/views/subscription/components/PayDialog.vue`** — `qrcode.toCanvas` 渲染 WeChat native 二维码 + Alipay HTML body 用 `Blob + URL.createObjectURL` 沙箱加载 + `setInterval` 3 秒轮询 `queryOrder`（status=2 emit success / status=3/4 提示关闭 / 网络错误不中断）+ close/unmount 时清理 timer + blob URL
- **`platform/src/api/order.ts`** — 5 个方法（list / show / markPaid / cancel / refund）
- **`platform/src/views/order/index.vue`** — 搜索卡片（order_no / status / tenant_id）+ 表格 + 分页，沿用 `useListPage` hook
- **`platform/src/views/order/components/OrderDetail.vue`** — `el-descriptions` 全字段展示 + 根据 `status` 动态显示操作按钮（待支付: 强制标记已支付 / 取消；已支付: 退款；终态: 无）

### Tests
- phpunit 208 tests（195 M3A baseline + 3 M3B + 10 M3C）
- 红线 31 tests 跨 Test1-8

## [0.2.0] - 2026-04-08

### Added — M2 平台前台 + 租户生命周期 UI

#### M2A — platformapi 骨架 + 平台超管登录

- **9 张 platform_* 表**：`platform_admins` / `platform_admin_tokens` / `platform_roles` / `platform_permissions` / `platform_role_permissions` / 等
- **`PlatformAuthService`** / **`PlatformContextMiddleware`** / **`PlatformAuthMiddleware`**
- **`/platformapi/auth/{login,info,logout,refresh}`** 端点
- **双 JWT secret 隔离**：`TokenManager::scope('platform' | 'tenant')`

#### M2B — 平台前端骨架 + 登录流程

- **`platform/`** 完整 Vue 3 SPA（新建，非从 admin/ 拷贝）
- 登录页 + `permission.guard` 动态路由挂载
- `dashboard` 占位（M2C 补 real content）

#### M2C — 平台业务模块 CRUD

- **`Tenant` CRUD** 带 `trial_days` 字段 + 默认 30 天试用
- **`Plan` CRUD** 带 bytes ↔ GB 转换 + features multiselect
- **`PlanFeature` CRUD**
- **Dashboard** 真实数据：stats cards（租户数 / 订阅数 / 收入）+ 7 天趋势图
- API: `platform/src/api/{tenant,plan,plan-feature,dashboard}.ts`

#### M2D — 租户侧生命周期 UI

- **`user.store`** 扩展 `saas` ref（包含 `lifecycle_state`、`expires_at`、`grace_until`、`features` 等）+ `hasFeature()` + `lifecycleState` computeds
- **`tenant-header-alert.vue`** grace / frozen / disabled 警告 banner
- **`v-feature` 指令**（`tenant/src/directives/feature.ts`）
- **`tenant-profile/index.vue`** 只读租户信息页（含存储用量进度条）
- **`subscription/index.vue`** M2D shell（M3D 替换为完整续费流程）
- **红线测试 Test3/4/5/6 新增**（总 6 个红线 test）

## [0.1.0] - 2026-04-08

### Added — M1 核心多租户机制

#### M1A — 项目初始化

- 项目初始化，建立多租户 SaaS 架构
- 重命名 `admin/` → `tenant/` / `adminapi/` → `tenantapi/`

#### M1B — 核心租户机制

- **`core/tenant/TenantContext`** 栈式 singleton（支持 `runAs` / `runWithoutScope` 嵌套）
- **`core/tenant/TenantContextSnapshot`** 不可变视图
- **`core/tenant/TenantResolver`** 子域名解析 + `RESERVED_SUBDOMAINS`
- **`core/tenant/middleware/TenantContextMiddleware`** / **`PlatformContextMiddleware`** / **`TenantStatusMiddleware`**
- **`core/base/Repository`** 基类新增 `query()` 方法，自动注入 `where('tenant_id', ctx.id())`；`create()` 自动填 tenant_id
- **`core/auth/TokenManager`** 重构为 `::scope(...)` 工厂方法 + 双 JWT secret 支持

#### M1C — SaaS 数据模型 + CLI

- **3 张核心表**：`tenants` / `plans` / `plan_features`
- **`Tenant` model** with `lifecycle_state` 计算属性
- **`Plan` model** with `features` JSON cast
- **`PlanFeature` model**（显式 `$deleteTime = false`，无 `deleted_at` 列）
- **`PlatformAdmin` model**
- **CLI `saas:create-platform-admin`**
- **红线测试 Test1**（跨租户列表隔离）+ **Test2**（跨租户 ID 注入防御）

---

## [1.5.1] - 2026-04-07

### Added

#### 后端 (server)
- 新增 `MenuChangedListener`，统一处理菜单相关操作后的缓存失效

### Changed

#### 后端 (server)
- `createMenu`/`updateMenu`/`deleteMenu`/`batchDeleteMenu`/`batchSort` 成功后触发 `menu.changed` 事件
- `MenuRepository` 移除缓存失效副作用，迁移至 `MenuChangedListener`
- 移除 `MenuRepository` 中无调用方的 `deleteWithChildren` 和已废弃的 `collectChildrenIds` 方法

#### Admin 前端 (admin)
- 菜单表单改造为单列布局，dialog 宽度收窄至 600px，组件路径输入框添加前后缀 UI 提示

### Fixed

#### Admin 前端 (admin)
- 修复菜单新增/编辑/删除后列表缓存未失效、页面不刷新的问题
- 修复 `IconSelect` SVG 图标 glob 路径错误导致 SVG tab 一直空白的问题
- 修复 `IconSelect` `popoverWidth` prop 传入百分比时 popover 撑满视口的问题

## [1.5.0] - 2026-04-07

### Added

#### 后端 (server)
- `core\base\Service`：新增 `extractPagination()`、`findOrFail()`、`runInTransaction()` 三个基类方法
- `core\base\Repository`：新增 `buildPagination()` 统一分页响应构造
- `core\base\Model`：新增默认 `getStatusTextAttr()` 实现
- 新增 `AbstractLedgerLogRepository` 账目流水抽象基类（BalanceLog/PointsLog 复用）
- `MessageService::sendToUser()`：封装按用户 ID 发送模板消息
- `RoleService::batchDeleteRole()`、`DictionaryService::batchDeleteDictionary()`：批量删除带事务
- `AdminService::batchDeleteAdmin()`：批量删除带事务
- 代码生成器生成的 Model 自动包含 `$append = ['status_text']` 声明
- CronJobService 命令白名单 + Console::call 进程内调用替代 exec
- 新增语言键：wechat_open_platform_not_configured / wechat_auth_failed / cron_command_empty / cron_command_not_allowed

#### Admin 前端 (admin)
- 新增 `hooks/useListPage.ts`：列表页通用 composable（分页/搜索/删除/批删/状态切换）
- 新增 `hooks/useFormDialog.ts`：表单弹窗通用 composable
- 新增 `utils/createCrudApi.ts`：标准 CRUD API 工厂
- 新增 `styles/crud-layout.scss`：全局列表页布局样式
- 新增 `constants/options.ts`：状态选项 hooks（useStatusOptions）
- 错误页面白名单含 404/500，loadRouteView 找不到组件时降级到 404

#### 移动端 (uniapp)
- 新增 `hooks/useCountdown.ts`：SMS 倒计时 composable
- 新增 `hooks/usePagingList.ts`：自动注册 onShow + onPullDownRefresh
- 新增 `hooks/useMessageList.ts`：消息列表共享逻辑 + messageCache 跨页面传递
- 新增 `utils/time.ts`：日期格式化工具（formatDate/formatDateTime/formatRelativeTime）
- 新增 `utils/platform.ts::getStatusBarHeight()`：状态栏高度封装
- 新增 `components/d-ledger-list/`：账目/积分流水列表通用组件
- 全局样式新增 .d-submit-btn / .d-section-card / .d-section-title

### Changed

#### 后端 (server)
- 分层架构合规：UserService 多处直接 Model 操作迁至 Repository
- User Model 删除静态查询方法（findByMobile/findByOpenid/findByMiniOpenid），逻辑迁至 UserRepository
- AdminLoginLog/AdminOperationLog 静态 record 方法迁至 Repository
- SystemConfig 静态查询逐步迁移至 Repository（保留 core 层使用的静态方法，避免反向依赖）
- MenuRepository.getAllChildrenIds 改为"一次全量查询 + BFS"消除 N+1
- NotificationRepository.markAsRead/markAllAsRead 改为批量 SQL 消除 N+1
- AlipayDriver create() 返回结构改为嵌套 data 字段，与 WechatPayDriver 对齐
- AdminController/MenuService.batchDelete 加事务包裹
- Listener 全部改为构造函数 DI（FeedbackCreated/UserRegister/PaymentSuccess/MessagePush/AdminLoginSuccess/AdminLoginFailed）
- DashboardService 删除 getRelativeTime，统一使用 DateHelper::diffForHumans
- ArticleCategoryService/DepartmentService 删除 buildTree，统一使用 ArrayHelper::toTree
- upload 路由添加 admin_log 中间件
- DashboardService 删除重复字段 newAdmins/newRoles/newMenus
- Admin Model 移除冲突的 setPasswordAttr，密码 hash 统一在 Service 层
- AuthController.wechatWebLogin 下沉至 UserService，使用 Guzzle 替代 file_get_contents
- 8 个 Service 应用 extractPagination 消除分页参数解构样板
- 5 处 findOrFail 替换样板代码
- UserManageService.adjustBalance/adjustPoints 改用 runInTransaction
- UserRepository.updateLastLogin 用 Db::raw 实现 login_count 原子自增

#### Admin 前端 (admin)
- 22 个列表页迁移到 useListPage（admin/role/department/cron-job/notification/dictionary/log×2/permission/file/announcement/agreement/article/article-category/feedback/region/version/auto-reply/balance-log/points-log/user/message-log/message-template）
- 15 个 Form 组件迁移到 useFormDialog
- v-has-perm 改用 removeChild 完全从 DOM 移除元素
- API 类型定义集中到 types/api.d.ts（UserItem/BalanceLogItem/PointsLogItem）
- usePaging 修复 res.data.list 数据解构对齐
- settings.store 清理 @ts-ignore，改用 $patch
- i18n 补全 feedback/article/article-category/announcement 等模块，合并 userMgmt.common.* 到顶层 common.*
- 首页快速导航重新排版

#### 移动端 (uniapp)
- LoginResult 类型字段从 user 改为 user_info（与后端对齐）
- user.store 新增 register 方法封装注册流程
- balance.vue/points.vue 接入 d-ledger-list
- register.vue 接入 useCountdown，移除手动 timer 管理
- announcement-list 接入 usePagingList 自动注册生命周期
- my/index.vue 头部高度通过 createSelectorQuery 动态测量替代硬编码
- settings.vue 接入真实的 useVersionCheck，删除假"检查更新"实现
- wechat-oauth 存储改用 uni.getStorageSync 跨端兼容
- upload.ts BASE_URL 与 request.ts 对齐（H5 DEV 代理）
- d-wechat-login 移除废弃的 uni.getUserProfile 调用
- 安全区域适配修复（加 env(safe-area-inset-bottom)）
- usePaging 新增 hasLoaded 状态供空状态组件防闪烁

#### PC 网站 (pc)
- 余额充值流程支持支付宝 PC page 支付（DOMParser 解析表单 + 新窗口手动提交）

### Fixed

#### 后端 (server)
- 修复 CodeGeneratorService.getTableColumns SQL 注入风险（白名单校验）
- 修复 FileService.deleteFile 物理文件删除失败未记录日志
- 统一 SystemConfigService 异常类为 BusinessException

#### Admin 前端 (admin)
- 修复 file/index.vue 模板语法错误导致页面无法加载

#### 移动端 (uniapp)
- 修复 balance.vue 支付字段名 payment_data 类型
- 修复 usePaging 初始 loading=true 导致 getList 阻塞（改为 hasLoaded 方案）
- 修复 useMessageList 从 modules 子包移到 hooks 主包（修复主包不能引用子包的限制）

### Removed

#### Admin 前端 (admin)
- 移除 ThemePicker 组件及 theme/apply.ts 遗留代码

#### 移动端 (uniapp)
- 移除 profile.vue 孤儿页面

## [1.4.0] - 2026-04-05

### Added
- 新增 `CacheableRepository` Trait，Repository 层声明式缓存抽象
- 新增 Redis 队列异步处理（操作日志、消息通知）
- 新增 `log:archive` 命令，定期清理过期管理员日志
- 新增前端 `useDebounceRequest` Hook，搜索请求防抖
- 新增前端 GET 请求去重机制

### Changed
- 缓存驱动由 file 切换为 Redis
- 字典数据增加 7200s Redis 缓存
- 菜单树增加 3600s Redis 缓存
- SystemConfigRepository / Permission 缓存迁移到标签化管理
- 操作日志由同步写 DB 改为异步队列
- 消息通知由同步 API 调用改为异步队列
- 余额/积分日志改用 eager loading，消除 enrichListWithNames 额外查询
- AdminRepository.getDetailWithPermissions 改用 eager loading 消除 N+1
- 前端删除 Auth Guard 冗余 menuApi.getAdminRoutes() fallback 请求

### Fixed
- 修复 admin_login_logs 缺少 (admin_id, login_time) 复合索引

## [1.3.0] - 2026-04-01

### Added
- API 文档支持后台管理 API / 前端应用 API 切换，C 端 11 个控制器添加 OpenAPI 注解
- UniApp 注册页新增手机短信验证码（与 PC 端注册流程统一）

### Changed
- UniApp 微信快捷登录按钮改为圆形绿色微信图标
- UniApp 引入 iconify 图标系统（@iconify-json/ri + presetIcons）
- 重构 Controller/Service/Repository 分层，消除架构违规（Controller 不再直接调用 Model，Service 不再绕过 Repository）
- Menu/Permission Model 查询逻辑迁移至 Repository 层
- AdminLogMiddleware 改用 Repository 记录操作日志
- 统一 Repository 调用风格（`$this->getModel()::` → `$this->model->`）
- `RequestCodeEnum` 更新为与后端一致的 HTTP 状态码（200/400/401/403/500）
- 超级管理员权限标识前后端对齐（后端注入 `'*'` 通配符）
- 事务 catch 类型统一为 `\Throwable`（RoleService、AdminService、DictionaryService）
- MessageLog Model 改为继承 `core\base\Model`
- `ConfigInfo` TypeScript 类型定义与实际 API 响应字段对齐
- `app.store.getConfig()` 返回值统一为 config 数据对象

### Fixed
- 修复开放平台配置保存成功但刷新后值为空（`system_configs` 缺少 `wechat_open` 组初始数据）
- 修复 `batchUpdateConfigs()` 对不存在的配置键静默跳过，改为抛出异常提示具体键名
- 修复异常类在 PHP 8.4 下隐式 nullable 参数弃用警告（BusinessException、ApiException、PermissionException）
- 修复 `server/public/static/fonts` 未纳入 git 版本控制
- 补充英文语言包缺失的 `config_group_wechat_open` 翻译
- 修复 Upload 组件响应码检查（`code == 1` → `code == 200`），错误消息字段（`msg` → `message`）
- 修复上传路由重复定义（移除 `common.php` 中多余的 upload 路由组）
- 修复超级管理员 `v-hasPerm` 指令不生效（后端未向前端发送 `'*'` 权限标识）
- 修复 21 个 Model 缺少 `$append` 声明导致访问器字段不出现在 API 响应
- 修复日志 Model 缺少 `$updateTime = false`（AdminLoginLog、AdminOperationLog）
- 修复静默吞掉异常的 catch 块（FileService、CodeGeneratorService、UploadController），改为 Log::warning
- 修复 `api-doc` 页面硬编码 localStorage key 获取 token
- 修复 `workbench` 页面 `v-for` + `v-if` 同元素（Vue 3 不允许）
- 修复 Vite 开发模式新页面首次访问触发依赖重优化整页刷新（改用动态解析组件样式路径）
- 修复 Dashboard stats 接口报 "Undefined array key 'login_result'"（AdminLoginLog 访问器加 isset 防御）
- 修复多个 Model 访问器在字段缺省时抛出 "Undefined array key" 警告（Admin、Role、Menu、Dictionary、DictionaryItem、Permission、BalanceLog、PointsLog）

### Removed
- 移除重复的消息模块视图（`views/message/`，保留 `views/system/message/`）
- 移除未使用的路由 guard 文件（`router/guards/init.ts`）
- 移除死代码：`getWorkbench()`、`getGlobalConfigs()`、`ThemePicker/demo.vue`
- 注册 3 个孤立事件到 `event.php`（`announcement.created`、`article.created`、`user.notification.created`）

## [1.2.1] - 2026-03-28

### Added
- 新增系统版本配置文件 `server/config/version.php`
- 新增数据库升级目录 `server/database/updates/` 及通用升级指南
- 新增 v1.2.1 数据库升级脚本（权限系统修复）

### Changed
- `CLAUDE.md` 新增发版数据规范章节

### Fixed
- 修正菜单权限命名不一致（type=2 菜单添加 `.list` 后缀）
- 补充缺失的 type=3 按钮菜单权限
- 为超级管理员角色分配新增按钮权限

### Removed
- 移除 `server/public/install/data/fix_permissions.sql`（内容已合并至 init.sql 和 updates/v1.2.1）

## [1.2.0] - 2026-03-24

### Added
- 仪表盘新增「最近活动」和「活跃用户排行」数据端点（`/adminapi/dashboard/recent-activities`、`/adminapi/dashboard/active-ranking`）
- DashboardRepository 新增用户统计、排行榜、最近活动查询方法
- DashboardService 新增用户注册/活跃统计、最近活动聚合、活跃排行逻辑

### Changed
- 仪表盘前端整体重设计：渐变玻璃态风格（gradient glassmorphism）
- KPI 卡片调整为冷色系渐变配色（左亮右暗）
- 移除系统信息卡片，快捷导航扩展为 4×2 网格布局
- 简化仪表盘整体布局，放大关键数字排版
- 移除仪表盘区域背景色覆盖
- 更新仪表盘相关 TypeScript 类型定义与 API 函数
- 更新仪表盘 i18n 多语言翻译

### Fixed
- 修复仪表盘中 `appStore` 属性名引用错误

## [1.1.0] - 2026-03-23

### Added
- 微信支付多端适配：小程序 JSAPI、公众号 JSAPI、H5 MWEB、APP、PC Native 五种支付方式自动路由
- 客户端平台识别：`X-Client-Type` 请求头（miniapp/wechat_h5/h5/app/pc），后端白名单校验
- 多 AppID 支付配置：按平台自动选择小程序/公众号/开放平台/移动应用 AppID
- JSAPI/APP 支付参数二次签名：`buildJsapiParams()`、`buildAppParams()` 方法
- 微信平台证书自动下载与缓存（无需手动配置 cert_path）
- 小程序微信快捷登录 + 手机号绑定（`wechatQuickLogin`、`wechatBindPhone` 接口）
- H5 公众号 OAuth 静默授权获取 oa_openid（`wechat-oauth.ts`）
- H5 微信浏览器 WeixinJSBridge 调起支付
- PC 端充值二维码展示 + 轮询支付状态（qrcode 库）
- 用户表新增 `oa_openid` 字段，支付订单表新增 `client_type` 字段
- 注册成功后自动登录（token + userInfo 同步写入 store）
- `notify_url` 支持相对路径，运行时自动补全域名

### Changed
- `PaymentManager::getWechatConfig()` 改为 public，新增多端 appid 配置加载
- `WechatPayDriver::create()` 支持动态 appid 参数
- `WechatPayDriver::query()` 使用 URI 模板避免订单号大写被 normalize 转义
- `PaymentService::createOrder()` 存储 client_type 到订单记录
- `UserController::recharge()` 根据客户端类型自动路由支付方式
- `PaymentController::query()` 不再强制要求 channel 参数，自动从订单记录获取
- `WechatController::oauthCallback()` 支持 SPA 重定向模式和 JSON 模式
- `OfficialAccountService::getUserByCode()` 返回 unionid 字段

### Fixed
- 修复微信支付未启用时返回 500 错误（改为友好提示）
- 修复微信支付 V3 SDK certs 参数为空导致初始化失败
- 修复微信 WXSS 编译错误（UnoCSS presetUno → presetWeapp）
- 修复发现页 tabs 四周边距不合理及多余 scroll-view
- 修复 H5 微信 OAuth 死循环（前端直接处理 code 参数）
- 修复 el-tree-select `value` 属性 TS 类型错误（改用 `node-key`）
- 修复 el-tag type 属性 TS 联合类型不匹配
- 修复微信支付查询订单号大写被转为 kebab-case（W → -w）
- 修复 ORDER_NOT_EXIST 轮询报错暴露给用户（静默返回 pending）

## [1.0.0] - 2026-03-20

### Added

#### Admin 后台管理
- 基于 Vue 3 + TypeScript + Element Plus + Vite + Pinia 的管理后台
- 动态路由系统，通过后端菜单数据自动生成
- 用户管理、角色权限、菜单管理
- 文章管理（分类、标签、封面、富文本编辑）
- 公告管理、反馈管理、协议管理
- 系统配置（站点设置、上传配置、支付配置等）
- 余额记录、积分记录管理
- 控制台仪表盘（统计卡片、登录趋势图表）
- 操作日志、登录日志
- 代码生成器（自动生成 CRUD 全栈代码）

#### Server 后端服务
- 基于 ThinkPHP 8 + PHP 8.0+ 的 RESTful API 服务
- 分层架构：Controller → Service → Repository → Model + Listener + Job
- 自动依赖注入（DI）
- JWT 认证与 RBAC 权限控制
- 支付系统（微信支付、支付宝）
- 余额/积分体系
- 消息通知系统（站内信、短信）
- 事件驱动的副作用处理（Listener 机制）
- 文件上传（本地、阿里云 OSS、腾讯云 COS、七牛云）
- 安装向导（含演示数据与动态 URL 替换）
- 开放平台（OAuth 第三方登录）

#### PC 前台网站
- 基于 Nuxt 3 (SPA) + Naive UI + UnoCSS 的前台网站
- 文章列表与详情（分类筛选、标签、阅读量）
- 用户中心（个人资料、密码修改、余额充值、积分明细）
- 登录注册（密码、短信、微信扫码）
- 全局错误页面（404/500）

#### UniApp 移动端
- 基于 uni-app + Vue 3 + wot-design-uni 的移动端应用
- 首页（轮播图、公告栏、功能入口、最新文章）
- 发现页（文章分类筛选、下拉刷新、上拉加载）
- 消息中心
- 个人中心（资料编辑、余额、积分）
- 文章详情（富文本渲染、标签展示）
- 反馈、公告、协议页面
