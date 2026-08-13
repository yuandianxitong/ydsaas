<p align="center">
  <img src="https://www.dev007.cn/oss/logo.png" alt="ydsaas" width="120">
</p>

<h1 align="center">YdAdmin SaaS — 多租户 SaaS 框架</h1>

<p align="center">
  开箱即用的多租户 SaaS 商业闭环：子域名隔离 / 插件应用 / 订阅计费 / 支付集成 / 配额管理 / 功能开关 / 红线测试
</p>

<p align="center">
  <a href="https://docs.dev007.cn/saas/">在线文档</a> · <a href="DEPLOYMENT.md">部署指南</a> · <a href="ARCHITECTURE.md">架构设计</a> · <a href="CHANGELOG.md">更新日志</a> · <a href="CONTRIBUTING.md">贡献指南</a>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/PHP-8.4+-blue?logo=php" alt="PHP">
  <img src="https://img.shields.io/badge/ThinkPHP-8-green" alt="ThinkPHP">
  <img src="https://img.shields.io/badge/Vue-3-brightgreen?logo=vue.js" alt="Vue 3">
  <img src="https://img.shields.io/badge/Element%20Plus-latest-409eff" alt="Element Plus">
  <img src="https://img.shields.io/badge/MySQL-8.0%2B-orange?logo=mysql" alt="MySQL">
  <img src="https://img.shields.io/badge/License-Apache--2.0-blue" alt="License">
  <img src="https://img.shields.io/badge/Version-2.32.1-purple" alt="Version">
</p>

---

## 系统简介

**YdAdmin SaaS** 是一套基于 ThinkPHP 8 + Vue 3 + Element Plus 的开源多租户 SaaS 应用框架，提供完整的**多租户商业闭环**：

- 物理子域名多租户隔离（`tenant1.example.com` / `tenant2.example.com`）
- 插件应用体系（一级应用 / 能力插件 / 平台上架 / 套餐授权 / 租户启用）
- 订阅 + 订单 + 支付（WeChat / Alipay SDK 已集成）
- 生命周期状态机（trial → active → grace → frozen → disabled）
- 存储配额硬拦截 + 功能开关后端守卫
- 8 条红线测试锁定跨租户隔离等不可越界语义
- 完整的 RBAC 权限体系 + CRUD 代码生成器

基于 [Apache-2.0](LICENSE) 协议开源，个人和企业均可免费商用。再分发须保留 `LICENSE` 与 [NOTICE](NOTICE) 版权声明。「元点SaaS」商标不随协议授权；应用与主题请在 [官方市场](https://www.dev007.cn/market) 购买。

> 📖 在线文档：[https://docs.dev007.cn/saas/](https://docs.dev007.cn/saas/)

## 快速开始

### Docker 一键启动（推荐）

```bash
git clone https://github.com/yuandianxitong/ydsaas.git
cd ydsaas
make setup
```

启动后访问：
- 平台管理后台：`http://admin.localhost`
- 租户管理后台：`http://demo.localhost`

### 本地开发

```bash
git clone https://github.com/yuandianxitong/ydsaas.git
cd ydsaas
php server/think saas:install
```

**环境要求：** PHP 8.4+、MySQL 8.0+、Redis 7+、Node.js 20+、pnpm 9+

> 生产环境部署请查看 **[DEPLOYMENT.md](DEPLOYMENT.md)**

## 访问入口

按 `saas.root_domains` / `saas.platform_domains` 与工作台 `access-info` 的真实规则：

| 端 | 地址规则 | 说明 |
|---|---|---|
| 平台后台 | `https://admin.{root}/` | 平台域；前端构建 `base` 为 `/platform/` |
| 租户后台 | `https://{tenant}.{root}/` | 租户子域；前端构建 `base` 为 `/tenant/` |
| H5 前台 | `https://{tenant}.{root}/mobile/` | UniApp H5 发布产物（需租户已发布） |
| PC 门户 | `https://{tenant}.{root}/pc/` | 与 H5 同域；需租户 PC 配置已启用 |

本地 Docker 示例：`admin.localhost` / `demo.localhost`（`{root}=localhost`，`{tenant}=demo`）。

## 技术栈

| 端 | 技术 |
|---|---|
| 后端 | ThinkPHP 8 / PHP 8.4+ / MySQL 8.0+ / Redis 7+ |
| 平台管理 | Vue 3 / TypeScript / Element Plus / Vite / Pinia |
| 租户管理 | Vue 3 / TypeScript / Element Plus / Vite / Pinia |
| 公共前台 | Nuxt 3 / Naive UI / UnoCSS |
| 移动端 | UniApp / Vue 3 / uview-plus |
| 容器化 | Docker Compose / Nginx / Supervisor |

## 架构概览

```
┌─────────────────────────────────────────────────────────────┐
│  admin.example.com        → platformapi + platform/         │
│  (平台超管后台)            → 租户管理 / 套餐 / 订单          │
│                                                              │
│  *.example.com            → tenantapi + tenant/              │
│  (租户后台，子域名隔离)    → RBAC / 内容 / 用户 / 订阅       │
│                                                              │
│  example.com              → api + pc/                        │
│  (公共前台)                → 文章 / 注册 / 用户中心           │
└─────────────────────────────────────────────────────────────┘
```

**后端分层：** Controller → Service → Repository → Model

- **Controller** — 请求处理、参数校验
- **Service** — 业务逻辑、事务管理
- **Repository** — 数据访问（基类自动注入 tenant scope）
- **Model** — ORM 映射

## SaaS 核心特性

- **物理三域名隔离** — Nginx 多 vhost 路由，平台 / 租户 / 公共完全分离
- **TenantContext 自动 scope** — Repository 基类自动注入 `where('tenant_id', ctx.id())`，业务代码无需手动过滤
- **插件应用体系** — 支持 `kind=app` 一级应用和 `kind=plugin` 能力插件，覆盖上传、安装、升级、套餐授权、租户启用
- **移动端按权益构建** — UniApp 主壳 + 插件分包，租户构建时只复制已授权插件源码
- **双 JWT secret** — 平台与租户独立密钥，跨 scope token 自动拒绝
- **生命周期状态机** — trial / active / grace / frozen / disabled 五态自动流转
- **订阅 + 订单** — 完整支付闭环（微信/支付宝），幂等回调 + TOCTOU 防护
- **到期提醒 + 自动续费** — 6 级提醒时间线 + 自动创建续费订单
- **存储配额硬拦截** — 原子 inc/dec + 下溢保护
- **功能开关守卫** — 按套餐控制功能访问，前后端同语义
- **红线测试** — 8 条不可越界场景测试，CI 自动守护
- **CORS 白名单** — 域名自动推导，拒绝非法 Origin
- **邮件通知** — symfony/mailer 集成，到期提醒 + 续费通知

## 通用功能

- **RBAC 权限** — 管理员 / 角色 / 权限 / 菜单，按钮级权限控制
- **系统管理** — 部门、数据字典、文件管理、通知管理、定时任务、系统配置
- **日志审计** — 登录日志、操作日志（队列异步写入）
- **内容管理** — 协议、公告、用户反馈、文章管理
- **渠道管理** — 微信公众号、小程序配置
- **消息系统** — 多通道模板（短信/微信/邮件/站内信），队列异步发送
- **支付集成** — 微信支付 / 支付宝（APP/小程序/H5/PC）
- **代码生成** — 可视化 CRUD 生成器，一键生成前后端代码
- **API 文档** — 内置 OpenAPI 文档自动生成

## 项目结构

```
├── platform/              # 平台超管后台（Vue 3 + Element Plus）
├── tenant/                # 租户管理后台（Vue 3 + Element Plus）
├── pc/                    # 公共前台（Nuxt 3）
├── uniapp/                # 移动端（UniApp + Vue 3）
├── server/                # 后端（ThinkPHP 8）
│   ├── app/
│   │   ├── platformapi/   # 平台超管 API
│   │   ├── tenantapi/     # 租户后台 API
│   │   ├── api/           # 公共 API
│   │   ├── service/saas/  # SaaS 核心 Service
│   │   └── command/       # CLI 命令（saas:install 等）
│   ├── core/
│   │   ├── tenant/        # TenantContext / Resolver / Middleware
│   │   ├── plugin/        # 插件扫描 / 安装 / 加载 / 迁移
│   │   ├── mobile/        # 租户移动端构建 / 插件 UniApp 合并
│   │   ├── saas/payment/  # SaaS 支付网关
│   │   └── auth/          # JWT 认证（双 scope）
│   ├── plugins/           # 插件应用源码（内置 mall / points-exchange 演示）
│   └── tests/
│       └── RedLine/       # 红线测试 Test1-8
├── docker/                # Docker Compose + Nginx 多 vhost
├── Makefile               # 一键启动（make setup / dev / stop）
├── server/app/command/     # CLI 命令（saas:install 等）
├── DEPLOYMENT.md          # 生产部署指南
├── ARCHITECTURE.md        # 架构设计文档
├── CONTRIBUTING.md        # 贡献指南
└── CHANGELOG.md           # 更新日志
```

## 系统截图

### 平台端（admin.example.com）

| | |
|---|---|
| ![平台端](https://docs.dev007.cn/saas/demo/platform01.png) | ![平台端](https://docs.dev007.cn/saas/demo/platform02.png) |

### 租户端（{tenant}.example.com）

| | |
|---|---|
| ![租户端](https://docs.dev007.cn/saas/demo/tenant01.png) | ![租户端](https://docs.dev007.cn/saas/demo/tenant02.png) |

### 移动端

| | |
|---|---|
| ![移动端](https://docs.dev007.cn/saas/demo/mobile01.png) | ![移动端](https://docs.dev007.cn/saas/demo/mobile02.png) |

## 开源协议

[Apache License 2.0](LICENSE)

## 联系我们

<p align="center">
  <img src="https://www.dev007.cn/support.png" alt="联系我们" width="800">
</p>

## 链接

- 部署指南: [DEPLOYMENT.md](DEPLOYMENT.md)
- 架构设计: [ARCHITECTURE.md](ARCHITECTURE.md)
- 变更日志: [CHANGELOG.md](CHANGELOG.md)
- 贡献指南: [CONTRIBUTING.md](CONTRIBUTING.md)
- GitHub: [https://github.com/yuandianxitong/ydsaas](https://github.com/yuandianxitong/ydsaas)
- Gitee: [https://gitee.com/yuandianxitong/ydsaas](https://gitee.com/yuandianxitong/ydsaas)
