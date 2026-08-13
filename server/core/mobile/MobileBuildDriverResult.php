<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile;

/**
 * MobileBuildDriver::build() 的产物。
 *
 * - artifactPath 必须是「本地目录」（local/docker 直出；remote 下载 zip 后解压成目录），
 *   以满足 H5ReleaseService / WechatMiniprogramUploadService 对目录的消费契约。
 * - remoteJobId / artifactUrl / runtime 供远程 driver 回填可观测信息（本地/docker 留默认）。
 */
final class MobileBuildDriverResult
{
    /**
     * @param array<string, mixed> $runtime driver/耗时/镜像/节点等运行时元数据
     */
    public function __construct(
        public readonly bool $success,
        public readonly string $artifactPath,
        public readonly string $log,
        public readonly ?string $remoteJobId = null,
        public readonly ?string $artifactUrl = null,
        public readonly array $runtime = [],
    ) {
    }
}
