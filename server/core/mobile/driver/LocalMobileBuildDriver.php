<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile\driver;

use core\mobile\MobileBuildContext;
use core\mobile\MobileBuildDriverResult;
use core\mobile\UniBuildRunner;

/**
 * 本机构建：直接复用现有 UniBuildRunner（pnpm install + uni build）。
 * 行为与重构前的 DefaultMobileBuilder 第 6 步完全一致。
 */
final class LocalMobileBuildDriver implements MobileBuildDriver
{
    public function __construct(private readonly UniBuildRunner $runner)
    {
    }

    public function build(MobileBuildContext $ctx): MobileBuildDriverResult
    {
        $run = $this->runner->run($ctx->uniappDir, $ctx->platform);

        return new MobileBuildDriverResult(
            success: (bool) $run['success'],
            artifactPath: (string) $run['artifactPath'],
            log: (string) $run['log'],
            runtime: ['driver' => 'local'],
        );
    }
}
