<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\marketplace;

use app\repository\marketplace\MarketplaceAppCacheRepository;
use app\repository\marketplace\MarketplaceConnectionRepository;
use core\base\Service;
use core\exception\BusinessException;
use think\facade\Cache;

/**
 * marketplace 应用版本 changelog 拉取 + 缓存 1h（避免反复打 Site）。
 */
class MarketplaceChangelogService extends Service
{
    protected MarketplaceConnectionRepository $connRepo;
    protected MarketplaceAppCacheRepository $cacheRepo;
    protected OfficialMarketplaceClient $client;
    protected TokenCipher $cipher;

    public function fetch(string $entitlementCode, string $toVersion): string
    {
        $conn = $this->connRepo->findActive();
        if (!$conn) {
            throw new BusinessException('未绑定 Site', 422);
        }
        $cacheRow = $this->cacheRepo->findByEntitlement((int) $conn['id'], $entitlementCode);
        if (!$cacheRow) {
            throw new BusinessException('entitlement 不存在', 404);
        }

        $appCode  = (string) $cacheRow['app_code'];
        $cacheKey = "mkt:changelog:{$conn['id']}:{$appCode}:{$toVersion}";
        $cached   = Cache::get($cacheKey);
        if ($cached !== null) {
            return (string) $cached;
        }

        $token = $this->cipher->decrypt((string) $conn['encrypted_instance_token']);
        $resp  = $this->client->getVersion(
            (string) $conn['site_base_url'],
            $token,
            $appCode,
            $toVersion
        );
        $changelog = (string) ($resp['changelog'] ?? '');
        Cache::set($cacheKey, $changelog, 3600);
        return $changelog;
    }
}
