# ============================================================
# UniApp 构建镜像（供 DockerMobileBuildDriver `docker run` 使用）
# 运行期挂载租户 workspace 到 /workspace，工作目录 /workspace/uniapp，
# 构建命令由 driver 通过 `sh -lc` 注入（pnpm install + pnpm exec uni build）。
# pnpm 固定 9.x：uniapp/pnpm-lock.yaml 为 lockfileVersion 9.0。
# ============================================================
FROM node:20-bookworm

ARG PNPM_VERSION=9.15.9

RUN corepack enable \
    && corepack prepare pnpm@${PNPM_VERSION} --activate \
    && apt-get update \
    && apt-get install -y --no-install-recommends git bash zip unzip ca-certificates \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /workspace

# 构建命令在 docker run 时由 DockerMobileBuildDriver 注入，这里不设 CMD。
