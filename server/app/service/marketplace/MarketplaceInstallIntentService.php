<?php

declare(strict_types=1);

namespace app\service\marketplace;

use app\repository\marketplace\MarketplaceAppCacheRepository;
use app\repository\marketplace\MarketplaceConnectionRepository;
use core\base\Service;

/**
 * 安装意图编排：把"点安装 → 连接账号 → 同步权益 → 判定 ready/purchase_required/incompatible"
 * 收敛到后端单接口，前端不再自己拼接多次 API。
 *
 * 返回 status 契约：
 *   authorization_required → { authorize_url, state }   未连接，需先走 OAuth
 *   connected              → { connection }              仅连接（无 app_code）
 *   ready                  → { app, current_version }    已购且兼容，可安装
 *   purchase_required      → { app, buy_url }            尚未购买
 *   incompatible           → { app, current_version, required }  版本不兼容，禁止安装
 */
class MarketplaceInstallIntentService extends Service
{
    protected MarketplaceConnectionRepository $connRepo;
    protected MarketplaceAppCacheRepository $cacheRepo;
    protected MarketplaceCatalogService $catalog;
    protected InstanceRegistrationService $registration;
    protected OfficialMarketplaceClient $client;

    /**
     * 点击安装（或连接账号）的入口。
     *
     * @param string|null $appCode     目标应用；为 null 表示纯连接意图（更换账号 / 连接账号）
     * @param string      $callbackUrl 前端 OAuth 回调地址（仅未连接时需要）
     */
    public function create(?string $appCode, string $callbackUrl): array
    {
        $conn = $this->connRepo->findActive();

        if ($conn) {
            if ($appCode === null) {
                return ['status' => 'connected', 'connection' => $this->shapeConn($conn)];
            }
            return $this->resolve($conn, $appCode);
        }

        $intent = $appCode !== null
            ? ['intent' => 'install', 'app_code' => $appCode]
            : ['intent' => 'connect'];
        $init = $this->registration->initiate($callbackUrl, $intent);

        return array_merge(['status' => 'authorization_required'], $init);
    }

    /**
     * 已连接场景：cache 优先，miss 才同步一次（刚购买的应用可能需要再点一次）。
     */
    public function resolve(array $conn, string $appCode): array
    {
        $row = $this->cacheRepo->findByAppCode((int) $conn['id'], $appCode);
        if (!$row) {
            // cache miss：同步一次再判定。同步失败时不能伪装成"未购买"（会误导用户），
            // 显式返回 error 让前端提示稍后重试。
            try {
                $this->catalog->syncConnection($conn);
            } catch (\Throwable) {
                return ['status' => 'error', 'message' => 'Site 暂时不可达，无法验证应用权益，请稍后重试'];
            }
        }
        return $this->finalize($conn, $appCode);
    }

    /**
     * OAuth 兑换成功后调用：按规范强制同步一次权益，再判定。
     */
    public function resolveAfterConnect(int $connectionId, string $appCode): array
    {
        $conn = $this->connRepo->find($connectionId);
        if (!$conn) {
            return ['status' => 'error', 'message' => '连接不存在'];
        }
        try {
            $this->catalog->syncConnection($conn);
        } catch (\Throwable) {
            // 同步失败不阻断：下方按现有 cache 判定
        }
        if ($appCode === '') {
            return ['status' => 'connected', 'connection' => $this->shapeConn($conn)];
        }
        return $this->finalize($conn, $appCode);
    }

    /**
     * 基于已同步的 cache 做最终判定（不再触发同步）。
     */
    private function finalize(array $conn, string $appCode): array
    {
        $row = $this->cacheRepo->findByAppCode((int) $conn['id'], $appCode);
        $current = (string) config('version.version');

        if (!$row) {
            return [
                'status'  => 'purchase_required',
                'app'     => ['app_code' => $appCode],
                'buy_url' => $this->buyUrl($conn, $appCode),
            ];
        }
        if ((int) ($row['compatible'] ?? 1) === 0) {
            return [
                'status'          => 'incompatible',
                'app'             => $this->shapeApp($row),
                'current_version' => $current,
                'required'        => $this->requiredRange($conn, $row),
            ];
        }
        return [
            'status'          => 'ready',
            'app'             => $this->shapeApp($row),
            'current_version' => $current,
        ];
    }

    private function shapeApp(array $row): array
    {
        return [
            'app_code'         => (string) ($row['app_code'] ?? ''),
            'app_name'         => (string) ($row['app_name'] ?? ''),
            'app_description'  => (string) ($row['app_description'] ?? ''),
            'app_icon_url'     => (string) ($row['app_icon_url'] ?? ''),
            'publisher_name'   => (string) ($row['publisher_name'] ?? 'official'),
            'entitlement_code' => (string) ($row['entitlement_code'] ?? ''),
            'latest_version'   => (string) ($row['latest_version'] ?? ''),
            'latest_version_id' => (string) ($row['latest_version_id'] ?? ''),
            'plan_code'        => (string) ($row['plan_code'] ?? ''),
            'period_end'       => $row['period_end'] ?? null,
        ];
    }

    private function shapeConn(array $conn): array
    {
        return [
            'id'                => (int) $conn['id'],
            'instance_name'     => (string) ($conn['instance_name'] ?? ''),
            'site_base_url'     => (string) ($conn['site_base_url'] ?? ''),
            'token_prefix'      => (string) ($conn['token_prefix'] ?? ''),
            'bound_user_email'  => $conn['bound_user_email'] ?? null,
            'bound_user_name'   => $conn['bound_user_name'] ?? null,
            'last_heartbeat_at' => $conn['last_heartbeat_at'] ?? null,
        ];
    }

    private function buyUrl(array $conn, string $appCode): string
    {
        // 购买页是浏览器页面（Site 前端在 /pc），用 web_base_url；
        // 留空则回退到 connection 的 API 基址（生产环境前端与 API 同根）。
        $base = rtrim((string) (
            config('saas.marketplace.web_base_url')
            ?: ($conn['site_base_url'] ?? config('saas.marketplace.default_site_base_url'))
        ), '/');
        return $base . '/market/' . rawurlencode($appCode);
    }

    /**
     * 不兼容时按需向 Site 取该版本的 framework-saas 版本约束，用于提示"所需版本"。
     * 仅在 incompatible 这一冷路径触发，正常安装不会有额外往返。
     */
    private function requiredRange(array $conn, array $row): array
    {
        try {
            $token = $this->registration->decryptToken($conn);
            $meta  = $this->client->getVersion(
                (string) $conn['site_base_url'],
                $token,
                (string) ($row['app_code'] ?? ''),
                (string) ($row['latest_version'] ?? '')
            );
            return [
                'min' => $meta['min_framework_saas_version'] ?? null,
                'max' => $meta['max_framework_saas_version'] ?? null,
            ];
        } catch (\Throwable) {
            return ['min' => null, 'max' => null];
        }
    }
}
