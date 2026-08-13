<?php

declare(strict_types=1);

namespace app\service\marketplace;

use app\repository\marketplace\MarketplaceAppCacheRepository;
use app\repository\marketplace\MarketplaceConnectionRepository;
use core\base\Service;
use think\facade\Db;
use think\facade\Log;

class MarketplaceCatalogService extends Service
{
    protected MarketplaceConnectionRepository $connRepo;
    protected MarketplaceAppCacheRepository $cacheRepo;
    protected OfficialMarketplaceClient $client;
    protected InstanceRegistrationService $registration;
    protected PluginCompatibilityChecker $compatibilityChecker;
    protected LicenseStateMachine $stateMachine;

    public function syncConnection(array $connection): array
    {
        $token = $this->registration->decryptToken($connection);
        $base  = (string) $connection['site_base_url'];

        try {
            $this->client->heartbeat($base, $token, (string) config('version.version'));
            $this->connRepo->markHeartbeat((int) $connection['id']);
        } catch (\Throwable $e) {
            $this->connRepo->markError((int) $connection['id'], 'heartbeat 失败: ' . $e->getMessage());
            throw $e;
        }

        $list      = $this->client->entitlements($base, $token);
        $keepCodes = [];
        $now       = date('Y-m-d H:i:s');
        foreach ($list as $ent) {
            $keepCodes[] = (string) $ent['entitlement_code'];
            $this->cacheRepo->upsert((int) $connection['id'], (string) $ent['entitlement_code'], [
                'remote_app_id'     => (string) ($ent['remote_app_id']     ?? $ent['app_id'] ?? ''),
                'app_code'          => (string) ($ent['app_code']          ?? ''),
                'app_name'          => (string) ($ent['app_name']          ?? ''),
                'app_description'   => (string) ($ent['app_description']   ?? ''),
                'app_icon_url'      => (string) ($ent['app_icon_url']      ?? ''),
                'publisher_name'    => (string) ($ent['publisher_name']    ?? ''),
                'plan_code'         => (string) ($ent['plan_code']         ?? ''),
                'billing_cycle'     => (string) ($ent['billing_cycle']     ?? ''),
                'period_end'        => $ent['period_end'] ?? null,
                'latest_version'    => (string) ($ent['latest_version']    ?? ''),
                'latest_version_id' => (string) ($ent['latest_version_id'] ?? ''),
                'compatible'        => $this->compatibilityChecker->isCompatible(
                    (string) config('version.version'),
                    $ent['min_framework_saas_version'] ?? null,
                    $ent['max_framework_saas_version'] ?? null
                ) ? 1 : 0,
                'synced_at'         => $now,
            ]);
        }
        $removed = $this->cacheRepo->deleteMissing((int) $connection['id'], $keepCodes);

        $this->fanOutToPlugins((int) $connection['id']);
        $this->connRepo->markSync((int) $connection['id']);

        // 立即评估 license 状态机 — 让管理员"立即同步"按钮场景下立刻看到状态变化，不等 6h cron
        $this->stateMachine->evaluateConnection((int) $connection['id']);

        return ['changed_count' => count($list), 'removed_count' => $removed];
    }

    /**
     * 合并 Site 公开目录（全部上架应用）与本地权益缓存（已购应用），
     * 以 remote_app_id（退 app_code）为键去重并集，派生 owned / installed /
     * has_upgrade / license / buy_url 供前端四态卡片决策。
     */
    public function mergedCatalog(array $conn, ?int $categoryId = null): array
    {
        $connId  = (int) $conn['id'];
        $apiBase = (string) $conn['site_base_url'];
        $webBase = rtrim((string) (config('saas.marketplace.web_base_url')
            ?: config('saas.marketplace.default_site_base_url', 'https://www.dev007.cn')), '/');

        try {
            $public = $this->client->publicCatalog($apiBase, ($categoryId && $categoryId > 0) ? $categoryId : null);
        } catch (\Throwable $e) {
            // Site 公开目录拉取失败时降级为"仅展示已购应用"，但保留诊断信息（不静默丢弃）
            Log::warning('[merged-catalog] public catalog fetch failed: ' . $e->getMessage());
            $public = [];
        }

        $ents       = $this->cacheRepo->listByConnection($connId);
        $entByRemote = [];
        $entByCode   = [];
        foreach ($ents as $e) {
            if (!empty($e['remote_app_id'])) {
                $entByRemote[(string) $e['remote_app_id']] = $e;
            }
            if (!empty($e['app_code'])) {
                $entByCode[(string) $e['app_code']] = $e;
            }
        }

        $pluginByRemote = [];
        foreach (Db::name('plugins')->where('distribution_source', 'marketplace')->whereNull('deleted_at')->select()->toArray() as $p) {
            $remoteAppId = (string) ($p['remote_app_id'] ?? '');
            if ($remoteAppId === '') {
                continue; // 防止空 remote_app_id 落到 '' 桶，与无 id/code 的公开应用错配
            }
            $pluginByRemote[$remoteAppId] = $p;
        }

        $rows        = [];
        $usedEntKeys = [];

        foreach ($public as $app) {
            $remoteId = (string) ($app['id'] ?? $app['code'] ?? '');
            $appCode  = (string) ($app['code'] ?? '');
            $ent      = $entByRemote[$remoteId] ?? ($appCode !== '' ? ($entByCode[$appCode] ?? null) : null);
            $owned    = $ent !== null;
            if ($owned) {
                $usedEntKeys[(string) $ent['entitlement_code']] = true;
            }
            $rows[] = $this->buildRow([
                'entitlement_code'  => $owned ? (string) $ent['entitlement_code'] : null,
                'remote_app_id'     => $remoteId,
                'app_code'          => $appCode,
                'app_name'          => (string) ($app['name'] ?? ''),
                'app_description'   => (string) (($app['summary'] ?? '') ?: ($app['description'] ?? '')),
                'app_icon_url'      => (string) ($app['icon'] ?? $app['icon_url'] ?? ''),
                'publisher_name'    => (string) ($app['publisher_name'] ?? 'official'),
                'latest_version'    => $owned ? (string) $ent['latest_version'] : (string) ($app['latest_version'] ?? ''),
                'latest_version_id' => $owned ? (string) ($ent['latest_version_id'] ?? '') : '',
                'compatible'        => $owned ? (int) ($ent['compatible'] ?? 1) : 1,
                'category_id'       => $app['category_id'] ?? null,
                'category'          => $app['category'] ?? null,
            ], $owned, $pluginByRemote, $webBase);
        }

        foreach ($ents as $e) {
            if (isset($usedEntKeys[(string) $e['entitlement_code']])) {
                continue;
            }
            $rows[] = $this->buildRow([
                'entitlement_code'  => (string) $e['entitlement_code'],
                'remote_app_id'     => (string) ($e['remote_app_id'] ?? ''),
                'app_code'          => (string) ($e['app_code'] ?? ''),
                'app_name'          => (string) ($e['app_name'] ?? ''),
                'app_description'   => (string) ($e['app_description'] ?? ''),
                'app_icon_url'      => (string) ($e['app_icon_url'] ?? ''),
                'publisher_name'    => (string) ($e['publisher_name'] ?? 'official'),
                'latest_version'    => (string) ($e['latest_version'] ?? ''),
                'latest_version_id' => (string) ($e['latest_version_id'] ?? ''),
                'compatible'        => (int) ($e['compatible'] ?? 1),
                'category_id'       => null,
                'category'          => null,
            ], true, $pluginByRemote, $webBase);
        }

        return $rows;
    }

    /**
     * 派生单行的 owned/installed/has_upgrade/license/buy_url 字段。
     * 字段集与 CatalogController::apps() 已连接分支保持一致。
     */
    private function buildRow(array $base, bool $owned, array $pluginByRemote, string $webBase): array
    {
        $p = $pluginByRemote[$base['remote_app_id']] ?? null;

        $base['owned']                    = $owned;
        $base['is_public']                = false;
        $base['installed']                = $p ? true : false;
        $base['installed_version']        = $p['version'] ?? null;
        $base['plugin_id']                = $p['id'] ?? null;
        $base['has_upgrade']              = $p ? ((int) ($p['update_available'] ?? 0) === 1) : false;
        $base['license_status']           = $p['license_status'] ?? null;
        $base['license_grace_started_at'] = $p['license_grace_started_at'] ?? null;
        $rsr                              = $p['read_safe_routes'] ?? null;
        $base['read_safe_routes_count']   = $rsr ? count(json_decode((string) $rsr, true) ?: []) : 0;
        $base['buy_url']                  = $owned ? null : ($webBase . '/market/' . rawurlencode((string) $base['app_code']));

        return $base;
    }

    private function fanOutToPlugins(int $connectionId): void
    {
        $cacheRows = $this->cacheRepo->listByConnection($connectionId);
        foreach ($cacheRows as $c) {
            if (empty($c['remote_app_id']) || empty($c['latest_version'])) {
                continue;
            }
            $plugin = Db::name('plugins')
                ->where('distribution_source', 'marketplace')
                ->where('remote_app_id', $c['remote_app_id'])
                ->find();
            if (!$plugin) {
                continue;
            }
            $installed = (string) ($plugin['version'] ?? '');
            $latest    = (string) $c['latest_version'];
            $update    = version_compare($latest, $installed, '>') ? 1 : 0;
            Db::name('plugins')->where('id', $plugin['id'])->update([
                'latest_version'   => $latest,
                'update_available' => $update,
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
