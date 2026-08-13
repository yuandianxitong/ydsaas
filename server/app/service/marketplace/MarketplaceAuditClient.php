<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\marketplace;

use app\repository\marketplace\MarketplaceConnectionRepository;
use core\base\Service;
use think\facade\Log;

/**
 * 匿名审计上报客户端（fire-and-forget）。
 *
 * 数据边界（spec §5.3 / §9.4 红线）：
 * - 只上报: instance_uuid / framework_saas_version / event_type / plugin_code
 *           plugin_from_version / plugin_to_version / error_code / occurred_at
 * - 禁止上报: tenant_id / 租户业务数据 / 用户数据 / exception trace / 请求 body
 *
 * 失败语义: silent log，绝不抛、绝不重试。
 */
class MarketplaceAuditClient extends Service
{
    protected MarketplaceConnectionRepository $connRepo;
    protected OfficialMarketplaceClient $client;
    protected TokenCipher $cipher;

    /**
     * @param array{type:string,plugin_code:string,from_version?:string,to_version?:string,error_code?:string,occurred_at?:string} $event
     */
    public function report(array $event): void
    {
        try {
            if (!config('saas.marketplace.audit_report_enabled', true)) {
                return;
            }
            $conn = $this->connRepo->findActive();
            if (!$conn) {
                return;
            }

            $payload = [
                'instance_uuid'          => (string) $conn['instance_uuid'],
                'framework_saas_version' => (string) config('version.version'),
                'event_type'             => (string) ($event['type']        ?? ''),
                'plugin_code'            => (string) ($event['plugin_code'] ?? ''),
                'plugin_from_version'    => $event['from_version'] ?? null,
                'plugin_to_version'      => $event['to_version']   ?? null,
                'error_code'             => $event['error_code']   ?? null,
                'occurred_at'            => $event['occurred_at']  ?? date('c'),
            ];

            $token = $this->cipher->decrypt((string) $conn['encrypted_instance_token']);
            $this->client->postAudit(
                (string) $conn['site_base_url'],
                $token,
                $payload,
                (int) config('saas.marketplace.audit_timeout_seconds', 3)
            );
        } catch (\Throwable $e) {
            Log::warning('[audit] report failed: ' . $e->getMessage());
        }
    }
}
