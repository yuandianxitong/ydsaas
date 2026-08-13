# YdAdmin SaaS 架构速查表

> 本文档描述 YdAdmin SaaS 的分层架构、多租户隔离机制、插件应用体系、计费系统和安全设计。

## 后端分层架构

```
请求 → Controller → Service → Repository → Model
                       ↓
                    Listener（事件驱动副作用）
```

| 层 | 目录 | 职责 | 禁止 |
|---|---|---|---|
| **Controller** | `app/adminapi/controller/v1/` | 接收请求、参数校验、调用 Service、返回响应 | 不直接操作 Repository 或 Model |
| **Service** | `app/service/` | 业务逻辑编排、事务管理、触发事件 | 不直接用 `Db::table()` 或 Model 静态方法 |
| **Repository** | `app/repository/` | 数据访问封装、所有 ORM 查询 | 不包含业务逻辑 |
| **Model** | `app/model/` | ORM 映射、关联关系、访问器/修改器 | 不包含查询逻辑 |
| **Listener** | `app/listener/` | 处理副作用（日志、通知、缓存清理） | 不影响主流程 |

## 依赖注入

Controller 和 Service 的基类内置自动 DI。声明带类型的 `protected` 属性即可：

```php
class ArticleService extends Service
{
    protected ArticleRepository $articleRepository;   // 自动注入
    protected CategoryRepository $categoryRepository; // 自动注入
}
```

## 事件系统

Service 中触发事件：
```php
$this->trigger('admin.login.success', ['admin_id' => $id]);
```

事件 → 监听器映射在 `app/event.php` 中配置。添加副作用只需新增 Listener，无需改 Service。

判断标准：操作失败不影响主流程 → 放 Listener；必须成功 → 留在 Service。

## 数据库约定

- 时间戳：`created_at`、`updated_at`、`deleted_at`
- 状态字段：`status`（1=启用，0=禁用）
- 软删除：统一使用 `deleted_at`
- 字符集：utf8mb4

## API 响应格式

```json
{
    "code": 200,
    "message": "操作成功",
    "data": {},
    "timestamp": 1710529800
}
```

分页响应：
```json
{
    "code": 200,
    "data": {
        "list": [],
        "pagination": {
            "current_page": 1,
            "per_page": 20,
            "total": 100,
            "last_page": 5
        }
    }
}
```

## 前端目录映射

| 目录 | 用途 |
|------|------|
| `src/views/` | 页面组件，按模块组织，自动映射为路由 |
| `src/api/` | API 接口定义，每个模块一个文件 |
| `src/components/` | 通用组件，跨模块复用 |
| `src/hooks/` | 组合式函数（usePaging、useDictOptions 等） |
| `src/store/modules/` | Pinia 状态管理（app/user/settings/multipleTabs） |
| `src/router/` | 路由系统，动态路由由后端菜单数据生成 |
| `src/theme/` | 主题系统，CSS 变量 + Element Plus 样式覆盖 |
| `src/locales/` | 国际化语言包（zh-CN / en-US） |

## SaaS 多租户架构

多租户隔离是 YdAdmin SaaS 的核心机制。

### 物理三域名隔离

```
┌─────────────────────────────────────────────────────────────┐
│  admin.example.com        → platformapi + platform/dist    │
│  (精确匹配 server_name)   → 平台超管后台                    │
│                                                              │
│  *.example.com            → tenantapi + tenant/dist         │
│  (正则通配 server_name)   → 租户后台（subdomain = tenant_code）│
│                                                              │
│  example.com / www.       → api + pc/.output/public         │
│  (精确匹配 server_name)   → 公共前台                         │
└─────────────────────────────────────────────────────────────┘
```

Nginx 配置见 `docker/nginx/default.conf`。DNS 需要 `*.example.com` 通配记录。

### 后端三个 API app

| App | 路径前缀 | 中间件链 | 用途 |
|---|---|---|---|
| `platformapi` | `/platformapi/*` | `platform_context` → `platform_auth` | 平台超管所有操作 |
| `tenantapi` | `/tenantapi/*` | `tenant_context` → `tenant_auth` → `tenant_status` → `admin_permission` → `admin_log` | 租户后台业务 |
| `api` | `/api/*` | (可选 `tenant_context`) | 公共 API（pc / uniapp）+ 支付回调 |

物理分离的好处：中间件链完全独立，不会因为疏忽让某个 API 在错误的 scope 下执行。

### TenantContext 自动 scope

**核心类**：`core\tenant\TenantContext`（栈式 singleton，支持 `runAs` 嵌套）

```php
// TenantContextMiddleware 在 tenantapi 入口挂载：
TenantContext::set([
    'id'          => $tenant->id,
    'code'        => $tenant_code,
    'is_platform' => false,
    'raw'         => $tenant->toArray(),   // 包含 plan_id / lifecycle_state / ...
]);

// 之后 Repository 基类的 query() 方法会自动：
return $this->model->newQuery()
    ->where('tenant_id', TenantContext::current()->id());
```

任何继承 `core\base\Repository` 的类只要调用 `$this->query()->where(...)` 都自动带 tenant scope，**业务代码不需要手动加 tenant 过滤**，也不可能忘记加。

测试时的强制规则：新写的 Repository 必须用 `$this->query()`，不能用 `$this->model` 直接调 ORM，确保 tenant scope 自动生效。

### 双 JWT secret

`core\auth\TokenManager` 支持按 scope 分发 token：

```php
// 签发
$token = TokenManager::scope('tenant')->issue(['uid' => $adminId, 'tid' => $tenantId]);

// 验签（如果用错 scope 会直接失败）
$payload = TokenManager::scope('platform')->verify($token);  // → 抛异常，密钥不匹配
```

`.env` 配置：

```env
JWT_TENANT_SECRET = <32 字节随机串>
JWT_PLATFORM_SECRET = <另一个 32 字节随机串，必须不同>
```

红线测试 Test4 专门验证：伪造的跨 scope token 必须被拒绝。

### 生命周期状态机

租户有 5 个 lifecycle 状态（存在 `tenants.lifecycle_state` 计算列）：

```
trial  (试用中)      → active   (正常订阅中)
                   ↘ grace    (过期宽限期，读 OK 写阻断)
                     ↓
                     frozen   (宽限期也过了，全部阻断除了续费 whitelist)
                     ↓
                     disabled (平台超管手动禁用)
```

`TenantStatusMiddleware` 按状态 + `saas.status_whitelist` 拦截：
- `active` / `trial`：放行所有
- `grace`：读 OK，写 → 402
- `frozen` / `disabled`：全阻断，除非 URI 在 whitelist（`auth/*`、`subscription/*`）

`saas:tenant-lifecycle` CLI 每小时扫描 `tenants.expires_at`，自动把过期的转入 grace / frozen。

### 计费数据模型

```
subscriptions          (订阅记录，append-only)
  ├── id
  ├── tenant_id
  ├── plan_id
  ├── type             1=trial 2=formal 3=renew 4=upgrade
  ├── starts_at / ends_at
  ├── status           1=active 2=ended 3=refunded
  └── order_id         → saas_orders.id（可空，试用订阅没订单）

saas_orders            (SaaS 订单)
  ├── id
  ├── order_no (unique)
  ├── tenant_id
  ├── plan_id / months
  ├── amount / paid_amount
  ├── payment_channel  wechat / alipay
  ├── payment_method   native / page / jsapi / ...
  ├── prepay_id / transaction_id
  ├── status           1=pending 2=paid 3=cancelled 4=refunded
  ├── paid_at / expired_at
  └── deleted_at       (支持软删除)

tenant_usage_daily     (每日用量快照，append-only)
  ├── tenant_id
  ├── date
  ├── storage_used_bytes / admin_count / user_count / request_count
```

Service：
- `SubscriptionService` — `createInitial` / `extend` / `getCurrent`，DB 事务保证 `subscriptions` + `tenants.expires_at` 一致
- `SaasOrderService` — `createOrder` / `createPayment` / `handleCallback` / `markPaid` / `markCancelled` / `markRefunded`，金额用整数分校验 + TOCTOU 防护 + 幂等

### 配额 + 功能开关

**`TenantQuotaService`** 硬拦截存储上限：

```php
$this->tenantQuotaService->assertCanStore($fileSize);  // 上传前一行
// ... upload ...
$this->tenantQuotaService->consume($fileSize);         // 上传成功后
// ... delete ...
$this->tenantQuotaService->release($fileSize);         // DB 删除确认后
```

- `consume` / `release` 用 `Db::table('tenants')->inc/dec` 原子 SQL，无 read-modify-write loop
- `release` 用 `GREATEST(0, CAST(storage_used_bytes AS SIGNED) - ?)` 防下溢

**`TenantFeatureService`** 后端功能开关守卫：

```php
$this->tenantFeatureService->requireFeature('code.generator');  // 前端 v-feature 的后端镜像
```

- `plans.features` JSON 数组 → 按租户 `plan_id` 查 Plan → per-request cache 按 `tenant_id` 隔离
- 抛出的 `BusinessException('未开通该功能')` 会被全局异常处理器转成 HTTP 403

### 支付抽象层

**`core\saas\payment\SaasPaymentGateway`** 是 `core\payment\PaymentManager` 的平行实现：

| 区别 | `PaymentManager` | `SaasPaymentGateway` |
|---|---|---|
| 配置源 | `system_configs` 表（租户级，会被 scope 污染） | `config/saas.php` → `.env`（平台级） |
| 用途 | 租户的业务支付（C 端用户对租户系统付费） | 平台的订阅支付（租户对平台付费） |
| Driver 复用 | WechatPayDriver / AlipayDriver | WechatPayDriver / AlipayDriver（**复用**，只换配置源） |
| 测试注入 | 不支持 | `setDriver($channel, $mockDriver)` |

两者严格分离：SaaS 级商户号不会被某个租户的设置污染；租户级商户号也不会被平台意外覆盖。

### 红线测试清单

`server/tests/RedLine/` 下 8 个场景测试，锁定不可越界的语义：

| # | 文件 | 验证 |
|---|---|---|
| 1 | `Test1_CrossTenantListIsolationTest` | 租户 A 列表查询不返回租户 B 的数据 |
| 2 | `Test2_CrossTenantIdInjectionTest` | 租户 A 带着 ID 查询不到租户 B 的资源 |
| 3 | `Test3_PlatformDoesNotLeakTenantDataTest` | 平台上下文不会意外泄露租户 scope 的数据 |
| 4 | `Test4_ForgedTenantTokenRejectedTest` | 伪造 tenant/platform 跨 scope token 被拒绝 |
| 5 | `Test5_GracePeriodBlocksWritesTest` | grace 状态租户的 POST/PUT/DELETE 被拦截 |
| 6 | `Test6_FrozenTenantBlockedExceptWhitelistTest` | frozen 状态只放行 whitelist URI |
| 7 | `Test7_StorageQuotaEnforcedTest` | 超配额 assertCanStore 抛错 + 原子 inc/dec + 跨租户隔离 |
| 8 | `Test8_FeatureNotInPlanBlockedTest` | basic 套餐 requireFeature('code.generator') 抛 403 |

CI 在每次 commit 自动跑这 31 个 test 方法，任何变更导致红线失败都会阻止合并。

## 插件应用架构

插件应用是 YdAdmin SaaS 的扩展单元，支持把完整业务或复用能力拆成可安装、可授权、可按租户启用的包。

```
server/plugins/{code}/plugin.json
        ↓
平台安装：校验 manifest → 解压或登记 → 跑迁移 → 生命周期 install → 同步菜单权限
        ↓
套餐授权：plan_grants 决定哪些租户可见，可设置 auto_enable
        ↓
租户启用：tenant_plugins 记录启用、禁用、购买来源和过期时间
        ↓
运行时：PluginLoader 挂载 PSR-4 + 路由，中间件校验租户、状态、权益、RBAC
        ↓
移动端：按租户权益合并 UniApp 分包，只复制已授权插件源码
```

### 两类插件形态

| 形态 | 适用场景 | 入口 |
|---|---|---|
| `kind=app` | 商城、CRM、工单等完整业务应用 | 作为租户后台一级菜单安装，必须声明 `tenant.menus` |
| `kind=plugin` | 积分兑换、营销组件、第三方能力等扩展能力 | 进入插件中心，通常提供配置面板或被应用调用 |

内置示例：
- `server/plugins/mall` — 一级应用示例，包含商品、分类、订单、租户后台页面和 UniApp 页面。
- `server/plugins/points-exchange` — 能力插件示例，包含配置面板、兑换记录和可复用服务。

### manifest 约定

插件以 `plugin.json` 声明元数据和集成点：

```json
{
  "code": "mall",
  "name": "商城",
  "version": "1.0.0",
  "kind": "app",
  "psr4": { "Plugin\\Mall\\": "app/" },
  "lifecycle": "Plugin\\Mall\\hooks\\Lifecycle",
  "entitlement": "mall",
  "routes": {
    "tenantapi": "app/tenantapi/route.php",
    "api": "app/api/route.php"
  },
  "tenant": {
    "menus": [
      { "code": "mall", "name": "商城", "path": "/mall", "icon": "shopping-cart" }
    ],
    "permissions": [
      { "code": "mall.product.list", "name": "商品列表" }
    ]
  },
  "uniapp": {
    "subpackage": "modules/mall",
    "pages": [
      { "path": "pages/product-list/index", "title": "商品列表" }
    ]
  }
}
```

`PluginManifestValidator` 会强制校验：
- `code` / `entitlement` 使用 kebab-case，`version` 使用 semver。
- `psr4` 命名空间必须以 `Plugin\\` 开头。
- `kind=app` 时 `tenant.menus` 不能为空。
- `tenant.permissions` 必须形如 `a.b.c`。
- 声明 `uniapp.pages` 时必须同时声明 `uniapp.subpackage`。
- 旧字段 `admin.*`、顶层 `menus`、顶层 `permissions`、`mobile` 会被拒绝。

### 路由和权益链

插件 `tenantapi` 路由文件只声明裸路由。运行时由 `PluginLoader` 自动包裹到 `/{plugin_code}` 分组，并挂载：

```
locale → tenant_context → tenant_auth → tenant_status
→ entitlement({entitlement}) → admin_permission → admin_log
```

因此插件作者不需要手动处理租户解析、状态拦截、权益校验和操作日志。C 端 `api` 路由会挂载 `tenant_context`、`tenant_status` 和 `api_entitlement`。

### 安装、卸载和升级

平台安装由 `PluginService` 通过 saga 编排：
- ZIP 插件：先 dry-run inspect，再备份到 `server/runtime/plugin-packages/`，安装时解压到 `server/plugins/{code}`。
- 内置插件：从 `server/plugins/{code}/plugin.json` 登记，跳过解压步骤。
- 安装步骤：迁移 up、生命周期 install、标记 enabled、安装菜单权限、加入前端构建队列。
- 卸载默认是软卸载，删除代码目录并软删插件记录，业务数据保留。
- 软卸载前会把 `migrations/` 和 `plugin.json` 快照到 `runtime/plugin-graveyard/{plugin_id}/`，便于后续彻底清理。

### 移动端按权益构建

租户移动端构建由 `DefaultMobileBuilder` 编排：
1. 复制基础 `uniapp/` 主壳。
2. `PagesJsonGenerator` 读取 `pages.base.json`，按租户权益合并插件 `subPackages`。
3. `PluginUniappCopier` 只复制已授权插件的 `server/plugins/{code}/uniapp/`。
4. 注入租户 App 名称、图标、小程序 AppID 和 TabBar。
5. 执行目标平台构建。

这保证未授权插件不会进入租户移动端产物。

### 前端 SaaS 组件

**`platform/`**（平台超管）：Vue 3 + Element Plus SPA，菜单硬编码在 `PlatformAuthService::info()`。页面：
- `dashboard` — 租户统计、近 7 天趋势
- `tenant` — 租户 CRUD + 查看生命周期
- `plan` — 套餐 CRUD（bytes ↔ GB 转换，features multiselect）
- `plan-feature` — feature 元数据 CRUD
- `order` — 订单列表 / 详情 / 强制标记已支付 / 取消 / 退款

**`tenant/`**（租户后台）：完整的后台管理功能，**另加 SaaS 特性**：
- `src/store/modules/user.store.ts` 的 `saas` ref + `hasFeature()` + `lifecycleState` computeds
- `src/layout/components/header/tenant-header-alert.vue` grace/frozen 警告 banner
- `src/directives/feature.ts` `v-feature` 指令
- `src/views/tenant-profile/index.vue` 只读租户信息
- `src/views/subscription/` 续费页 + `PayDialog`（qrcode 扫码 + 3s 轮询）

**`pc/`** 和 **`uniapp/`**：通过后端 JWT + TenantContext middleware 被动 scope，无侵入式改动。

---

## 后端目录结构

```
server/
├── app/
│   ├── platformapi/         # 平台超管 API (SaaS 新增)
│   ├── tenantapi/           # 租户后台 API (原 adminapi/)
│   │   ├── controller/v1/   # 控制器（接收请求、调用 Service）
│   │   ├── middleware/      # 中间件（认证、权限、日志）
│   │   ├── route/           # 路由定义
│   │   └── validate/v1/     # 表单验证规则
│   ├── api/                 # 前台用户 API + 支付回调
│   ├── command/             # CLI 命令（saas:* + 代码生成器）
│   ├── listener/            # 事件监听器（副作用处理）
│   ├── model/saas/          # SaaS 模型（Tenant/Plan/Subscription/SaasOrder/...）
│   ├── repository/saas/     # SaaS Repository
│   └── service/
│       ├── saas/            # SaaS Service (TenantService/SubscriptionService/
│       │                    # SaasOrderService/TenantQuotaService/TenantFeatureService/
│       │                    # PlatformAuthService/...)
│       └── ...              # 通用业务 service
├── core/                    # 框架核心
│   ├── auth/                # JWT 认证（TokenManager 支持 scope）
│   ├── base/                # 基类（Controller/Service/Repository/Model）
│   ├── tenant/              # SaaS 租户机制 (TenantContext/Resolver/Middleware)
│   ├── plugin/              # 插件扫描/安装/加载/迁移/菜单安装
│   ├── mobile/              # 租户移动端构建和插件 UniApp 合并
│   ├── saas/payment/        # SaaS 支付网关 (SaasPaymentGateway + MockDriver)
│   ├── payment/             # 原支付网关（支付宝/微信，租户级业务支付）
│   ├── storage/             # 文件存储（本地/阿里云/腾讯云/七牛）
│   └── wechat/              # 微信公众号集成
├── tests/
│   ├── RedLine/             # 红线测试 Test1-8 (SaaS 新增)
│   ├── Integration/
│   └── Feature/
├── config/                  # 配置文件
├── database/
│   ├── migrations/          # 数据库迁移
│   └── seeds/               # 数据填充
├── plugins/                 # 插件应用源码（plugin.json + app/ + tenant/ + uniapp/）
└── public/                  # Web 入口
    ├── admin/               # 预编译的管理后台前端
    └── install/             # 安装向导
```

## 前端目录结构

```
admin/src/
├── api/                     # API 接口定义
├── assets/                  # 静态资源（图片/图标/字体）
├── components/              # 通用组件
├── constants/               # 常量定义
├── directives/              # 自定义指令（v-has-perm、v-copy）
├── hooks/                   # 组合式函数
├── layout/                  # 布局组件（侧边栏、顶栏、主内容区）
├── locales/                 # 国际化语言包
├── router/                  # 路由（动态路由 + 守卫）
├── store/modules/           # Pinia 状态管理
├── styles/                  # 全局样式
├── theme/                   # 主题系统（CSS 变量 + Element Plus 覆盖）
├── types/                   # TypeScript 类型定义
├── utils/                   # 工具函数
└── views/                   # 页面组件（按模块组织）
```

## 认证流程

1. 登录 → 获取 JWT Token → 存入 localStorage
2. 每次请求 → 拦截器自动添加 `Authorization: Bearer <token>`
3. 401 响应 → 清除 Token → 跳转登录页
4. 权限控制 → `v-has-perm="['permission.name']"` 指令

## 中间件链

**tenantapi**（租户后台 API）中间件链：

1. `tenant_context` — `TenantContextMiddleware`，从 Host 头解析 tenant_code，加载 Tenant 模型，写入 `TenantContext` (SaaS)
2. `tenant_auth` — JWT Token 验证（tenant scope），注入 `$request->userId` + `$request->tenantId`
3. `tenant_status` — `TenantStatusMiddleware`，按 lifecycle_state 拦截 (SaaS)
4. `admin_permission` — 权限检查（RBAC）
5. `admin_log` — 自动记录 POST/PUT/DELETE 操作日志

**platformapi**（平台超管 API）中间件链：

1. `platform_context` — `PlatformContextMiddleware`，设置 `TenantContext::isPlatform()` (SaaS)
2. `platform_auth` — JWT Token 验证（platform scope，独立密钥）

**api**（公共 API）：按路由组自定义。支付回调 `/api/saas/notify/*` 无中间件（签名校验替代身份认证）。

---

## 相关文档

- 部署指南: [DEPLOYMENT.md](DEPLOYMENT.md)
- 变更日志: [CHANGELOG.md](CHANGELOG.md)
- 贡献指南: [CONTRIBUTING.md](CONTRIBUTING.md)
