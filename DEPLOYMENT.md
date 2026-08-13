# ydsaas 部署指南

本指南从零到生产部署 ydsaas 到一台 Linux 服务器。预计耗时 30-60 分钟（不含 DNS 生效等待）。

---

## 1. 前置条件

**服务器**
- Linux 发行版：Ubuntu 22.04+ / Debian 11+ / CentOS 8+ / Rocky Linux 9+
- 配置：最低 2 核 4GB RAM + 40GB SSD（支持 50 租户；100+ 租户建议 4 核 8GB）
- 软件：
  - Docker 24+ （`curl -fsSL https://get.docker.com | sh`）
  - Docker Compose v2+（已包含在 Docker 24 中，`docker compose version`）
  - git
- 网络：公网 IP、80/443 端口开放

**域名**
- 一个你拥有的主域名，例如 `example.com`
- DNS 能做通配符记录（`*.example.com`）

**可选但推荐**
- Let's Encrypt 免费 SSL 证书
- 一个 SMTP 服务（发送系统邮件用，本文未覆盖配置细节）

---

## 2. DNS 配置

在你的 DNS 服务商（阿里云 / Cloudflare / Namecheap 等）添加以下记录，把它们全部指向服务器公网 IP：

| 类型 | 名称 | 值 | 说明 |
|---|---|---|---|
| A | `@` | `SERVER_IP` | 根域名 `example.com` → 公共前台 |
| A | `www` | `SERVER_IP` | `www.example.com` → 公共前台 |
| A | `admin` | `SERVER_IP` | `admin.example.com` → 平台超管后台 |
| A | `*` | `SERVER_IP` | 通配符 `*.example.com` → 租户后台 |

**验证 DNS 生效**（替换 `example.com` 为你的域名）：

```bash
dig admin.example.com +short      # 应返回 SERVER_IP
dig demo.example.com +short       # 应返回 SERVER_IP（通配符）
dig example.com +short            # 应返回 SERVER_IP
```

DNS 通常需要 5-30 分钟全球生效。可以先继续后面的步骤，到最后再验证域名可达。

---

## 3. Clone 代码 + 配置 .env

```bash
# 推荐 /opt 或 /srv 作为部署目录
sudo mkdir -p /opt/ydsaas
sudo chown $USER:$USER /opt/ydsaas
cd /opt/ydsaas

# Clone（任选一个镜像）
git clone --depth=1 https://github.com/yuandianxitong/ydsaas.git .
# 或：git clone --depth=1 https://gitee.com/yuandianxitong/ydsaas.git .
```

### 3.1 server/.env

```bash
cp server/.env.example server/.env
```

编辑 `server/.env`，**必须修改**的字段：

```bash
# 数据库连接（docker 内的 mysql 服务）
HOSTNAME = mysql
HOSTPORT = 3306
DATABASE = yd_admin
USERNAME = ydadmin
PASSWORD = <改成强密码>

# 缓存驱动：多租户生产环境必须用 tenant_redis（见下方警告），需先装 Redis + phpredis
[CACHE]
DRIVER = tenant_redis

[REDIS]
HOST = redis
PORT = 6379
PASSWORD =

# 两套独立的 JWT 密钥（必须 32 字节以上随机串）
# 生成命令：openssl rand -base64 48
JWT_TENANT_SECRET = <openssl rand -base64 48 输出>
JWT_PLATFORM_SECRET = <openssl rand -base64 48 输出，必须和上面不同>
JWT_TENANT_ISSUER = ydsaas-saas-tenant
JWT_PLATFORM_ISSUER = ydsaas-saas-platform

# SaaS 域名配置
# 注意：以下键位于 .env 的 [SAAS] 分节内，键名不带 SAAS_ 前缀，
# ThinkPHP 会自动补 SAAS_ 前缀（切勿另写 SAAS_PLATFORM_DOMAIN，否则会变成 SAAS_SAAS_*）
[SAAS]
ROOT_DOMAIN = example.com
# 必须与浏览器实际访问平台后台的 Host 完全一致，否则 platformapi 返回 404 租户上下文未识别
PLATFORM_DOMAIN = admin.example.com
GRACE_DAYS = 7

# 支付回调 base URL（部署 HTTPS 后改成 https://）
PAY_NOTIFY_BASE_URL = http://admin.example.com

# SaaS 级支付（生产环境启用，开发可留空）
PAY_WECHAT_ENABLED = false
PAY_ALIPAY_ENABLED = false
```

⚠️ **.env 注释里绝对不要写半角符号 `(` `)` `[` `]` `$` `'` `"` 反引号** —— ThinkPHP 用 `parse_ini_file` 加载，注释行含这些字符会让整份 .env 解析失败（返回 false），所有 env 静默回退默认值。用全角标点或 `、` CJK 逗号替代。

⚠️ **缓存驱动 `[CACHE] DRIVER` 生产环境必须用 `tenant_redis`**（需先安装 Redis 服务 + phpredis 扩展）。
只有 `tenant_redis` 会给缓存 key 加租户前缀（`t{id}:`）保证多租户隔离。若用 `file` / `redis`：
- 不同租户中 `admin_id` 相同的管理员会**共用权限缓存**（跨租户串号，安全隐患）；
- `file` 驱动的标签缓存一旦标签键损坏，会抛 `only array cache can be push` 使租户后台整体 500
  （排查：`rm -rf server/runtime/cache/* && php think clear` 可临时恢复，但根因是缓存驱动应改 `tenant_redis`）。

### 3.2 docker/.env.docker

```bash
cp docker/.env.docker docker/.env.docker.local
# 或直接编辑 docker/.env.docker
```

修改：

```bash
MYSQL_ROOT_PASSWORD=<改成强密码>
MYSQL_PASSWORD=<和 server/.env 的 PASSWORD 保持一致>
REDIS_PASSWORD=<可选，留空或设置强密码>
SAAS_ROOT_DOMAIN=example.com
```

---

## 4. 前端构建

在宿主机上构建三个前端产物，nginx 会通过 bind mount 读取它们。

```bash
# 安装 Node.js 18+（如未安装）
# curl -fsSL https://deb.nodesource.com/setup_20.x | sudo bash -
# sudo apt-get install -y nodejs
npm install -g pnpm

# 构建 platform
cd /opt/ydsaas/platform
pnpm install
pnpm build
# 产物在 platform/dist/

# 构建 tenant
cd /opt/ydsaas/tenant
pnpm install
pnpm build
# 产物在 tenant/dist/

# 构建 pc
cd /opt/ydsaas/pc
pnpm install
pnpm build
# Nuxt 3 产物在 pc/.output/public/
```

验证三个目录都存在且非空：

```bash
ls -la /opt/ydsaas/platform/dist/index.html
ls -la /opt/ydsaas/tenant/dist/index.html
ls -la /opt/ydsaas/pc/.output/public/index.html
```

---

## 5. 启动 Docker 服务

```bash
cd /opt/ydsaas/docker
docker compose up -d
```

等待健康检查（约 30 秒）：

```bash
docker compose ps
```

所有服务应为 `running` 且 mysql / redis 显示 `healthy`。

查看 nginx 启动日志确认配置生成正确：

```bash
docker compose logs nginx | head -20
```

应该看到 `[entrypoint] Substituting SAAS_ROOT_PLACEHOLDER -> example.com` 和生成后的 config 前 30 行。

---

## 6. 数据库迁移

```bash
cd /opt/ydsaas/docker
docker compose exec php php think migrate:run
```

应输出 `All Done.`，~20 个迁移全部执行。

---

## 7. 初始化种子数据

```bash
docker compose exec -T mysql mysql -uroot -p"${MYSQL_ROOT_PASSWORD}" yd_admin < /opt/ydsaas/server/public/install/data/init.sql
```

⚠️ 如果 `-T` 选项不被支持或 `$MYSQL_ROOT_PASSWORD` 未定义，改用：

```bash
docker compose exec mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" yd_admin' < /opt/ydsaas/server/public/install/data/init.sql
```

验证种子：

```bash
docker compose exec mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" yd_admin -e "SELECT code, name FROM plans"'
```

应输出 `free / basic / pro` 三行。

---

## 8. 创建平台超管账号

```bash
cd /opt/ydsaas/docker
docker compose exec php php think saas:create-platform-admin admin@example.com 'YourStrongPassword!'
```

应输出 `平台超管创建成功`。

---

## 9. 访问平台后台 + 创建首个租户

打开浏览器访问：

```
http://admin.example.com/
```

用上一步创建的账号登录。左侧菜单 → **租户管理** → **新建租户**：

- 租户 Code：`demo`（将作为子域名，访问 `demo.example.com`）
- 租户名称：Demo Company
- 套餐：basic
- 试用天数：30

创建成功后，可以访问：

```
http://demo.example.com/
```

用租户自动生成的初始管理员账号登录（创建租户时会显示一次）。

---

## 10. HTTPS 配置（强烈推荐生产环境启用）

### 10.1 使用 Certbot 获取证书

```bash
# 安装 Certbot
sudo apt-get install certbot python3-certbot-nginx

# 先停止 docker nginx 释放 80 端口
cd /opt/ydsaas/docker
docker compose stop nginx

# standalone 模式获取证书（包含通配符需要 DNS 验证）
sudo certbot certonly --standalone \
    -d example.com \
    -d www.example.com \
    -d admin.example.com

# 通配符证书需要 DNS-01 验证（阿里云 / Cloudflare / DNSPod 有相应插件）
sudo certbot certonly --manual --preferred-challenges=dns \
    -d '*.example.com'
```

证书文件位于 `/etc/letsencrypt/live/example.com/`。

### 10.2 更新 Nginx 配置添加 443 server block

编辑 `docker/nginx/default.conf`，把每个 `listen 80;` 的 server block 复制一份改成 443，并添加：

```nginx
    listen 443 ssl http2;
    ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # 可选：80 → 443 强制跳转
    if ($scheme = http) {
        return 301 https://$host$request_uri;
    }
```

把 Let's Encrypt 证书目录挂进 nginx 容器（编辑 `docker-compose.yml` 的 nginx volumes）：

```yaml
    volumes:
      - /etc/letsencrypt:/etc/letsencrypt:ro
      # ... 其他 volumes 保留
    ports:
      - "80:80"
      - "443:443"
```

重启：

```bash
docker compose up -d
```

### 10.3 证书自动续期

```bash
# 加到 crontab
(crontab -l; echo "0 3 * * * certbot renew --quiet --deploy-hook 'cd /opt/ydsaas/docker && docker compose restart nginx'") | crontab -
```

---

## 11. Cron 任务配置

ydsaas 依赖两个 cron 任务持续运行。在宿主机 crontab 添加：

```bash
crontab -e
```

添加：

```cron
# ydsaas 订阅生命周期扫描（每小时）
# 处理 grace / frozen 状态转移 + 每日用量快照
0 * * * * cd /opt/ydsaas/docker && docker compose exec -T php php think saas:tenant-lifecycle >> /var/log/ydadmin-saas-lifecycle.log 2>&1

# ydsaas 过期订单清理（每 5 分钟）
# 取消超过 expired_at + 60s 的待支付订单
*/5 * * * * cd /opt/ydsaas/docker && docker compose exec -T php php think saas:order-cleanup >> /var/log/ydadmin-saas-order-cleanup.log 2>&1
```

验证：

```bash
crontab -l
tail -f /var/log/ydadmin-saas-lifecycle.log  # 1 小时后应看到输出
```

---

## 12. 移动端构建与发布

租户后台 `/diy/build` 触发构建后，只会写入 `tenant_mobile_builds` 并投递到 `mobile-builds` 队列。实际编译由专用 worker 执行：

```bash
docker compose exec php supervisorctl status
# 应看到 mobile-build-worker:RUNNING
```

本地非 Docker 调试时需要单独启动：

```bash
make queue-mobile
```

构建过程会复制 `uniapp/` 到：

```text
server/runtime/mobile-builds/{tenant_id}/{build_id}/uniapp
```

构建产物默认在：

```text
server/runtime/mobile-builds/{tenant_id}/{build_id}/uniapp/dist/build/{h5|mp-weixin|app}
```

H5 构建成功后，在租户后台点击“发布”，产物会复制到：

```text
server/public/mobile-tenants/{tenant_code}/
```

访问入口：

```text
https://{tenant_code}.{SAAS_ROOT_DOMAIN}/mobile/
```

**不要为每个租户单独写一段 nginx `location`。** 发布目录已按 `tenant_code` 分文件夹，nginx 只需从 Host 子域名解析一次 `$mobile_tenant`，所有新租户自动生效。

#### 宝塔（推荐：一个通配站点 + Host 动态目录）

1. 宝塔只建 **一个** 租户站，域名绑定 `*.saas.dev007.cn`（或你的 `*.{SAAS_ROOT}`），不要每个租户建一个站。
2. 写在该站 **设置 → 配置文件**（不要写「伪静态」）。`$tenant_code` 不是内置变量；宝塔默认 `server_name` 无命名捕获时，用下面的 `set` + `if` 从 Host 取子域：

```nginx
# 从 demo.saas.dev007.cn → demo；新租户 foo.saas.dev007.cn → foo，无需再改 nginx
set $mobile_tenant "";
if ($host ~* ^([^.]+)\.saas\.dev007\.cn$) {
    set $mobile_tenant $1;
}

# 切勿写 try_files … /index.html —— 会落到 location / → index.php，
# 移动 UA 被 Index 再次 302 到 /mobile/，形成死循环；PC 则被误转到 /pc/。
location ^~ /mobile/ {
    rewrite ^/mobile/(.*)$ /$1 break;
    # 路径按机器修改；末尾必须是 $mobile_tenant（动态），不要写死 demo
    root /home/wwwroot/saas.dev007.cn/ydadmin-saas-main/server/public/mobile-tenants/$mobile_tenant;
    index index.html;
    # /mobile/ rewrite 后 URI 为「/」，必须带 $uri/index.html，否则目录访问 404、只有 /mobile/index.html 能开
    try_files $uri $uri/index.html $uri/ @mobile_spa;
}
location @mobile_spa {
    root /home/wwwroot/saas.dev007.cn/ydadmin-saas-main/server/public/mobile-tenants/$mobile_tenant;
    rewrite ^ /index.html break;
}
```

3. 保存前确认：根域名正则与真实域名一致（上例是 `saas.dev007.cn`）；`root` 指向本机 `server/public/mobile-tenants`。
4. DNS 需有泛解析 `*.saas.dev007.cn` → 服务器 IP；新租户发布后访问 `https://{新tenant_code}.saas.dev007.cn/mobile/` 即可，**不用再改 nginx**。
5. 自检：`/mobile/` 与 `/mobile/index.html` 均应 **200**；若仅后者可开，把 `try_files` 补上 `$uri/index.html`。
6. H5 API 必须同域：`http://demo.saas.dev007.cn/api/...`。若浏览器请求打到 `https://admin.dev007.cn/api/...`，是旧产物把 `VITE_APP_API_URL` 打进包了——需用当前仓库重新 **构建并发布** H5（生产 H5 已强制相对路径）。

Docker 部署仍用仓库 [`docker/nginx/default.conf`](docker/nginx/default.conf) 的 `server_name ~^(?<tenant_code>...)` + `location ^~ /mobile/`，语义相同。

微信小程序构建成功后，需要先在租户后台配置小程序 AppID 与上传私钥，再点击“上传”。服务器需能执行 `miniprogram-ci` 或 `npx miniprogram-ci`。

宝塔 PHP-FPM 注意：
- FPM 的 `env[PATH]` 需包含 Node/`npx`（如 `/www/server/nodejs/v24.x/bin`），改完后重载 PHP-FPM。
- **runtime 权限（必做）**：若 `runtime` 曾被 root 建成 755，PHP-FPM（`www`）会无法写日志/构建缓存/小程序临时私钥。部署或拉代码后在 **server** 目录执行一次：

```bash
cd /path/to/ydsaas/server
# 推荐（root 下会 chown 给 www，并 mkdir 0775 含 mobile-builds/_keys）
php think saas:ensure-runtime --user=www

# 等价手工：
# chown -R www:www runtime public/storage && chmod -R 775 runtime public/storage
```

仅把空目录提交进 git **不能**解决属主问题（解压用户仍是 root 则仍不可写）；`saas:ensure-runtime` 才是可重复修复手段。

### 12.1 Docker 构建模式（可选）

默认 `MOBILE_BUILD_DRIVER=local`，构建在主系统主机直接跑 pnpm/uni build。若不想在运行服务器直接安装 Node/pnpm，可改用容器构建：

```bash
# 1. 构建 UniApp 构建镜像
docker build -t ydsaas/uniapp-builder:latest server/docker/uniapp-builder
```

```ini
# 2. server/.env 切换 driver
MOBILE_BUILD_DRIVER = docker
MOBILE_BUILD_DOCKER_IMAGE = ydsaas/uniapp-builder:latest
MOBILE_BUILD_DOCKER_MEMORY = 2g
MOBILE_BUILD_DOCKER_CPUS = 2
MOBILE_BUILD_DOCKER_NETWORK =
MOBILE_BUILD_TIMEOUT_SEC = 900
```

切换后，`mobile-builds` worker 会以 `docker run` 把租户 workspace 挂进镜像内编译，产物落本地同一路径（`.../uniapp/dist/build/{platform}`），发布/上传流程不变。

- 主系统主机需安装 Docker，且运行 PHP 的用户有权执行 `docker`。
- 限制：构建仍消耗本机资源，多租户高并发需限流；如需完全卸载构建负载，见后续 remote 模式。

---

## 13. 常见问题排查

### 13.1 子域名访问 404 / 无响应

- **检查 DNS 是否生效**：`dig demo.example.com +short`
- **检查 nginx 配置生成正确**：`docker compose logs nginx | grep server_name`
- **检查 server_name 匹配**：容器里 `docker compose exec nginx cat /etc/nginx/conf.d/default.conf` 确认 `SAAS_ROOT_PLACEHOLDER` 已被替换
- **检查浏览器 DNS 缓存**：换一个设备或清缓存再试

### 13.2 502 Bad Gateway

- **php 容器没起来**：`docker compose ps | grep php`
- **php 配置错误**：`docker compose logs php | tail -30`
- **nginx 配置错误**：`docker compose exec nginx nginx -t`

### 13.3 登录后 401 Unauthorized

- **JWT 密钥没配置**：检查 `server/.env` 中 `JWT_TENANT_SECRET` 和 `JWT_PLATFORM_SECRET` 都填了 32 字节以上的随机串
- **两套密钥配成同一个**：必须不同，否则跨 scope 的 token 会意外生效

### 13.4 支付回调不工作

- **SAAS_PAY_NOTIFY_BASE_URL 配错**：必须是外网可达的 URL 前缀
- **签名校验失败**：查看 `docker compose logs php | grep notify`
- **订单金额不一致**：M3B 的整数分比较会拒绝 1 分钱以上的差异

### 13.5 移动端构建一直排队

- **worker 没启动**：`docker compose exec php supervisorctl status`，确认 `mobile-build-worker` 为 `RUNNING`
- **本地没有 worker**：执行 `make queue-mobile`
- **Redis 队列异常**：查看 `docker compose logs redis` 与 `docker compose logs php | grep mobile-build`
- **worker 启动后仍不动**：在租户后台构建记录里点击“重投递”，重新把 queued 任务投递到 `mobile-builds`

### 13.6 跨租户看到别人数据

- 如果出现这种情况立即停服检查！M1-M3 的 31 个红线测试专门防御这种场景
- 运行红线测试：`docker compose exec php ./vendor/bin/phpunit tests/RedLine`
- 检查 `TenantContextMiddleware` 是否正确挂载到 tenantapi 应用
- 检查可疑 Repository 是否绕过了 `$this->query()` 基类方法

### 13.7 配额超限无法上传

- 查看租户当前用量：`docker compose exec mysql sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" yd_admin -e "SELECT id, name, storage_used_bytes, storage_limit_bytes FROM tenants"'`
- 手动扩容：`UPDATE tenants SET storage_limit_bytes = 10737418240 WHERE id = X;`
- 或者让租户升级套餐（platform 后台 → 套餐管理）

---

## 14. Roadmap（当前未覆盖的工作）

以下功能在 M4 时明确 defer，将在未来 milestone 补充：

- **pc/ 和 uniapp/ 的 SaaS 多租户前端改造**：当前这两个应用通过后端 JWT + `TenantContext` 中间件 handle scope，功能上无阻塞；未来会加上前端的 tenant context store + 子域名解析 + feature flag 守卫
- **SSL/Certbot 自动化**：当前只提供手动命令，未来会提供 compose up 一键申请 + 自动续期
- **水平扩展多 PHP 容器**：当前 php + nginx 是 1:1，未来会提供 k8s 或多 php-fpm + haproxy 配置
- **E2E 多域名集成测试**：目前只有单元 + 集成 + 红线测试，未来会补充 Playwright 全流程测试
- **平台可视化监控**：usage 统计已有 API，但没有 Grafana 面板或告警规则

欢迎 PR！

---

## 参考

- 架构设计：[ARCHITECTURE.md](ARCHITECTURE.md)
- 变更日志：[CHANGELOG.md](CHANGELOG.md)
- Upstream 同步协议：[docs/UPSTREAM-SYNC.md](docs/UPSTREAM-SYNC.md)
- 问题反馈：https://gitee.com/yuandianxitong/ydsaas/issues
