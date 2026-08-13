<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\plugin\steps;

use app\model\plugin\Plugin;
use app\repository\plugin\PluginRepository;
use core\saga\CompensatableStep;

/**
 * v2.7.0：把 plugins 行标 ENABLED + 写 depends/entitlement/installed_at。
 *
 * - execute：update 一行
 * - compensate：改回 STATUS_FAILED（保留 last_error 由外层 PluginService 写）
 */
class MarkEnabledStep implements CompensatableStep
{
    /** @param array<string, mixed> $updateData */
    public function __construct(
        private int $pluginId,
        private array $updateData,
        private PluginRepository $pluginRepo,
    ) {
    }

    public function name(): string
    {
        return 'MarkEnabled';
    }

    public function execute(): void
    {
        $this->pluginRepo->update($this->pluginId, $this->updateData);
    }

    public function compensate(): void
    {
        $this->pluginRepo->update($this->pluginId, [
            'status' => Plugin::STATUS_FAILED,
        ]);
    }
}
