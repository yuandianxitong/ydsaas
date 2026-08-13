<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile;

/**
 * MobileBuilder 产物 VO。
 *
 * - success=true：构建成功；artifactPath 指向最终产物（如 dist/build/h5）
 * - success=false：失败；log 含错误细节，其它字段允许为空
 */
final class MobileBuildOutcome
{
    /**
     * @param array<int, string> $includedPlugins  本次合入的插件 code 列表
     * @param array<string, mixed> $pagesJson     生成的 pages.json
     * @param array<string, mixed> $manifestJson  生成的 manifest.json
     * @param array<string, mixed> $runtime       驱动运行时元数据（driver、node 等）
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $artifactPath,
        public readonly string $log,
        public readonly array $includedPlugins = [],
        public readonly array $pagesJson = [],
        public readonly array $manifestJson = [],
        public readonly ?string $driver = null,
        public readonly ?string $remoteJobId = null,
        public readonly ?string $artifactUrl = null,
        public readonly array $runtime = [],
    ) {
    }

    public static function fromDriverResult(
        MobileBuildDriverResult $result,
        MobileBuildContext $context,
    ): self {
        return new self(
            success: $result->success,
            artifactPath: $result->artifactPath,
            log: ErrorLogSanitizer::fromLog($result->log),
            includedPlugins: $context->includedPlugins,
            pagesJson: $context->pagesJson,
            manifestJson: $context->manifestJson,
            driver: $result->runtime['driver'] ?? null,
            remoteJobId: $result->remoteJobId,
            artifactUrl: $result->artifactUrl,
            runtime: $result->runtime,
        );
    }
}
