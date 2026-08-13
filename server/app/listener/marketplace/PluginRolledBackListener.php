<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\listener\marketplace;

use app\service\marketplace\MarketplaceAuditClient;

/**
 * 订阅 plugin.rolled_back 事件，匿名上报 rollback 审计事件。
 * 由 MarketplaceUpgradeService 升级失败回滚路径触发，因此默认就是 marketplace 来源。
 */
class PluginRolledBackListener
{
    public function handle(array $event): void
    {
        $code = (string) ($event['plugin_code'] ?? '');
        if ($code === '') {
            return;
        }
        app(MarketplaceAuditClient::class)->report([
            'type'         => 'rollback',
            'plugin_code'  => $code,
            'from_version' => (string) ($event['from_version'] ?? ''),
            'to_version'   => (string) ($event['to_version']   ?? ''),
        ]);
    }
}
