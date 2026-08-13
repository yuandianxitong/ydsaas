# UniApp 构建镜像

供 `DockerMobileBuildDriver`（`MOBILE_BUILD_DRIVER=docker`）使用。

## 构建

```bash
docker build -t ydsaas/uniapp-builder:latest server/docker/uniapp-builder
# 自定义 pnpm 版本：
docker build --build-arg PNPM_VERSION=9.15.9 -t ydsaas/uniapp-builder:latest server/docker/uniapp-builder
```

## 运行（由主系统自动调用，无需手动）

主系统按 `.env` 配置执行：

```bash
docker run --rm \
  -v {workspaceDir}:/workspace -w /workspace/uniapp \
  [--memory 2g] [--cpus 2] [--network none] \
  ydsaas/uniapp-builder:latest \
  sh -lc "CI=true pnpm install --prefer-offline --no-frozen-lockfile && CI=true pnpm exec uni build"
```

产物落在 `{workspaceDir}/uniapp/dist/build/{platform}`，由主系统读取发布。

## 注意

- pnpm 固定 9.x，与 `uniapp/pnpm-lock.yaml`（lockfileVersion 9.0）匹配。
- 容器默认以 root 运行，挂载目录内 `node_modules`/`dist` 将归 root 所有；
  如需非 root，可在 `docker run` 加 `--user`（出本阶段范围）。
