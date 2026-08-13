<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\listener\marketplace;

use app\service\marketplace\MarketplaceAuditClient;

/**
 * 订阅 plugin.uninstalled 事件，对 marketplace 来源插件匿名上报 uninstall 审计事件。
 * 非 marketplace 来源（本地上传 / 系统内置）忽略 — spec §5.3 仅追踪 marketplace 生命周期。
 */
class PluginUninstalledListener
{
    public function handle(array $event): void
    {
        if (($event['distribution_source'] ?? '') !== 'marketplace') {
            return;
        }
        $code = (string) ($event['code'] ?? '');
        if ($code === '') {
            return;
        }
        app(MarketplaceAuditClient::class)->report([
            'type'         => 'uninstall',
            'plugin_code'  => $code,
            'from_version' => (string) ($event['version'] ?? ''),
        ]);
    }
}
