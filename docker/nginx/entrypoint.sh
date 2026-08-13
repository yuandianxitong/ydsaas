#!/bin/sh
# ============================================================
# ydadmin-saas Nginx 容器启动脚本
#
# 1. 从环境变量读 SAAS_ROOT_DOMAIN（默认 example.com）
# 2. 用 sed 把挂载的 default.conf.template 中的 SAAS_ROOT_PLACEHOLDER
#    替换成真实域名，输出到 /etc/nginx/conf.d/default.conf
# 3. 启动 nginx（传递给原入口点）
#
# 用纯 POSIX sed，不依赖 envsubst（alpine 默认不带 gettext）。
# ============================================================
set -e

SAAS_ROOT="${SAAS_ROOT_DOMAIN:-example.com}"

echo "[entrypoint] Substituting SAAS_ROOT_PLACEHOLDER -> $SAAS_ROOT"
sed "s/SAAS_ROOT_PLACEHOLDER/$SAAS_ROOT/g" \
    /etc/nginx/conf.d/default.conf.template \
    > /etc/nginx/conf.d/default.conf

echo "[entrypoint] Generated config (first 30 lines):"
head -30 /etc/nginx/conf.d/default.conf

# 传递给原 nginx 入口点
exec nginx -g 'daemon off;'
