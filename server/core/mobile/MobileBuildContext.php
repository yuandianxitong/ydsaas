<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile;

/**
 * MobileBuildPreparer 的产物：一个已准备好的租户构建工作区描述。
 * 作为 MobileBuildDriver::build() 的唯一入参。
 */
final class MobileBuildContext
{
    /**
     * @param array<int, string>   $includedPlugins 本次合入的插件 code 列表
     * @param array<string, mixed> $pagesJson       生成的 pages.json
     * @param array<string, mixed> $manifestJson    生成的 manifest.json
     */
    public function __construct(
        public readonly int $tenantId,
        public readonly int $buildId,
        public readonly string $platform,
        public readonly string $tenantCode,
        public readonly string $workspaceDir,
        public readonly string $uniappDir,
        public readonly array $includedPlugins,
        public readonly array $pagesJson,
        public readonly array $manifestJson,
    ) {
    }
}
