<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile;

use core\mobile\driver\MobileBuildDriverFactory;

/**
 * MobileBuilder 编排器：
 *   1. MobileBuildPreparer 生成 workspace（复制模板/pages/manifest/tenant-config/插件）
 *   2. MobileBuildDriverFactory 按 .env 选 driver（local/docker/remote）
 *   3. driver 执行构建，结果合并成 MobileBuildOutcome
 *
 * 准备与执行解耦后，docker/remote driver 可插拔；本地行为与重构前一致。
 */
final class DefaultMobileBuilder extends MobileBuilder
{
    public function __construct(
        private readonly MobileBuildPreparer $preparer,
        private readonly MobileBuildDriverFactory $driverFactory,
    ) {
    }

    public function build(int $tenantId, int $buildId, string $platform): MobileBuildOutcome
    {
        try {
            $context = $this->preparer->prepare($tenantId, $buildId, $platform);
            $result  = $this->driverFactory->make()->build($context);

            return MobileBuildOutcome::fromDriverResult($result, $context);
        } catch (\Throwable $e) {
            return new MobileBuildOutcome(
                success: false,
                artifactPath: '',
                log: ErrorLogSanitizer::fromException($e),
            );
        }
    }
}
