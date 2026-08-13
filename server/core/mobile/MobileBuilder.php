<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile;

/**
 * 移动端构建器入口：把 uniapp/ 模板按租户权益 + 配置编译到独立产物目录。
 *
 * 默认实现是「未配置」占位（throw on build）。真实实现由 C-4..C-7 注入：
 *   - 复制模板
 *   - 注入 pages.json / manifest.json / tenant-config.ts
 *   - 调 pnpm + uni build
 *
 * 测试可继承本类并覆盖 build() 注入 fake。
 */
class MobileBuilder
{
    public function build(int $tenantId, int $buildId, string $platform): MobileBuildOutcome
    {
        return new MobileBuildOutcome(
            success: false,
            artifactPath: '',
            log: '[builder] MobileBuilder not configured; subclass must override build()',
        );
    }
}
