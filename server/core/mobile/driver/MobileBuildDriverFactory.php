<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile\driver;

use core\mobile\MobileBuildEnv;
use think\App;

/**
 * 按 .env 的 MOBILE_BUILD_DRIVER 解析具体 driver。
 * Phase 1 仅 local；Phase 2 追加 docker、Phase 3 追加 remote 分支。
 */
final class MobileBuildDriverFactory
{
    public function __construct(private readonly App $app)
    {
    }

    public function make(): MobileBuildDriver
    {
        // 必须经 MobileBuildEnv：ThinkPHP 不会把 .env 写进 $_ENV
        $driver = MobileBuildEnv::get('MOBILE_BUILD_DRIVER', 'local') ?? 'local';

        return match ($driver) {
            'docker' => $this->app->make(DockerMobileBuildDriver::class),
            'remote' => $this->app->make(RemoteMobileBuildDriver::class),
            default => $this->app->make(LocalMobileBuildDriver::class),
        };
    }
}
