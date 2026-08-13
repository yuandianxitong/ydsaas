<?php

declare(strict_types=1);

namespace app\service\marketplace;

use app\repository\marketplace\MarketplaceAppCacheRepository;
use app\repository\marketplace\MarketplaceConnectionRepository;
use app\repository\plugin\PluginRepository;
use app\service\plugin\PluginPackageService;
use app\service\plugin\PluginService;
use core\base\Service;
use core\exception\BusinessException;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Event;

class MarketplaceInstallService extends Service
{
    protected MarketplaceConnectionRepository $connRepo;
    protected MarketplaceAppCacheRepository $cacheRepo;
    protected PluginRepository $pluginRepo;
    protected OfficialMarketplaceClient $client;
    protected OfficialPackageDownloader $downloader;
    protected InstanceRegistrationService $registration;
    protected PluginPackageService $packageService;
    protected PluginService $pluginService;
    protected PluginCompatibilityChecker $compatibilityChecker;
    protected MarketplaceAuditClient $auditClient;

    public function install(string $entitlementCode, ?string $versionId = null): array
    {
        $lockKey = 'mkt:install:' . $entitlementCode;
        $ttl     = (int) config('saas.marketplace.install_lock_ttl', 60);
        // ThinkPHP Cache has no nx option uniformly — use store get/set check
        if (Cache::get($lockKey)) {
            throw new BusinessException('正在安装中, 请稍后', 429);
        }
        Cache::set($lockKey, 1, $ttl);

        // 用于 audit 上报的元数据；在解析阶段尽量填充
        $auditCode    = '';
        $auditVersion = '';

        try {
            $conn = $this->connRepo->findActive();
            if (!$conn) {
                throw new BusinessException('未绑定 Site, 请先绑定', 422);
            }
            $cache = $this->cacheRepo->findByEntitlement((int) $conn['id'], $entitlementCode);
            if (!$cache) {
                throw new BusinessException('未找到此权益, 请先同步', 404);
            }

            $auditCode    = (string) ($cache['app_code']       ?? '');
            $auditVersion = (string) ($cache['latest_version'] ?? '');

            // 装前 hard reject: 不兼容当前 framework-saas 版本 → 422
            $this->compatibilityChecker->ensureCompatible($cache, (string) config('version.version'));

            $appCode = (string) $cache['app_code'];
            $version = $versionId ?: (string) $cache['latest_version'];
            $token   = $this->registration->decryptToken($conn);

            $dlInfo = $this->client->requestDownloadToken((string) $conn['site_base_url'], $token, $appCode, $version);
            $url    = (string) ($dlInfo['download_url'] ?? '');
            if ($url === '') {
                throw new BusinessException('Site 未返回 download_url', 502);
            }
            if (!str_starts_with($url, 'http')) {
                $url = rtrim((string) $conn['site_base_url'], '/') . $url;
            }

            $dl = $this->downloader->download($url, $token, (string) $conn['site_base_url']);

            try {
                $upload = $this->packageService->upload($dl['path']);
                Db::name('plugins')->where('id', $upload['plugin_id'])->update([
                    'distribution_source' => 'marketplace',
                    'remote_app_id'       => $cache['remote_app_id'],
                    'remote_version_id'   => $cache['latest_version_id'],
                    'publisher_name'      => $cache['publisher_name'],
                    'installed_hash'      => $dl['sha256'],
                    'signature_status'    => 'verified',
                    'latest_version'      => $cache['latest_version'],
                    'update_available'    => 0,
                    'updated_at'          => date('Y-m-d H:i:s'),
                ]);

                $installed = $this->pluginService->install((int) $upload['plugin_id']);
                Event::trigger('marketplace.app.installed', ['plugin_id' => $installed['id'] ?? $upload['plugin_id'], 'connection_id' => $conn['id']]);
                $this->auditClient->report([
                    'type'        => 'install_success',
                    'plugin_code' => $auditCode,
                    'to_version'  => $auditVersion,
                ]);
                return ['plugin' => $installed];
            } finally {
                @unlink($dl['path']);
            }
        } catch (\Throwable $e) {
            // 数据边界：error_code 仅含异常短类名 + 数值 code，不含 message/trace
            $this->auditClient->report([
                'type'        => 'install_failure',
                'plugin_code' => $auditCode,
                'to_version'  => $auditVersion,
                'error_code'  => $this->classifyError($e),
            ]);
            throw $e;
        } finally {
            Cache::delete($lockKey);
        }
    }

    private function classifyError(\Throwable $e): string
    {
        $short = (new \ReflectionClass($e))->getShortName();
        return $short . '_' . (int) $e->getCode();
    }
}
