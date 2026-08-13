<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile\driver;

use core\mobile\MobileBuildContext;
use core\mobile\MobileBuildDriverResult;

/**
 * 移动端构建执行器：把已准备好的 workspace 编译成产物目录。
 * 实现：LocalMobileBuildDriver（本机）/ DockerMobileBuildDriver（Phase 2）/ RemoteMobileBuildDriver（Phase 3）。
 */
interface MobileBuildDriver
{
    public function build(MobileBuildContext $ctx): MobileBuildDriverResult;
}
