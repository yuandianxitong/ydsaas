<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\plugin\steps;

use app\model\plugin\PluginBuild;
use app\service\plugin\PluginBuildService;
use core\saga\CompensatableStep;

/**
 * v2.7.0：把云编译任务入队（platform + tenant 两个 target）。
 *
 * compensate 是 no-op：队列消费者在拿到 job 时会读 plugins 表，看到 STATUS_FAILED
 * 自动 skip —— 强行从队列里捞出来反而引入更复杂的并发问题。
 *
 * 把 EnqueueBuild 放在 saga 链最后一步，是因为：
 * - 它纯粹是「触发」，没有逆操作可言
 * - 即便 enqueue 自己抛错，前面所有 step（含 menus）都会被补偿
 */
class EnqueueBuildStep implements CompensatableStep
{
    public function __construct(
        private int $pluginId,
        private PluginBuildService $buildService,
    ) {
    }

    public function name(): string
    {
        return 'EnqueueBuild';
    }

    public function execute(): void
    {
        $this->buildService->enqueue(PluginBuild::TARGET_PLATFORM, 'install', $this->pluginId);
        $this->buildService->enqueue(PluginBuild::TARGET_TENANT, 'install', $this->pluginId);
    }

    public function compensate(): void
    {
        // 故意 no-op：见类注释
    }
}
