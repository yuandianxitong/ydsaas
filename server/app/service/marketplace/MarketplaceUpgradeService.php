<?php

declare(strict_types=1);

namespace app\service\marketplace;

use app\repository\marketplace\MarketplaceAppCacheRepository;
use app\repository\marketplace\MarketplaceConnectionRepository;
use app\repository\plugin\PluginRepository;
use app\service\plugin\PluginUpgradeService;
use core\base\Service;
use core\exception\BusinessException;
use think\facade\Db;
use think\facade\Event;

class MarketplaceUpgradeService extends Service
{
    protected MarketplaceConnectionRepository $connRepo;
    protected MarketplaceAppCacheRepository $cacheRepo;
    protected PluginRepository $pluginRepo;
    protected OfficialMarketplaceClient $client;
    protected OfficialPackageDownloader $downloader;
    protected InstanceRegistrationService $registration;
    protected PluginUpgradeService $upgradeService;
    protected PluginCompatibilityChecker $compatibilityChecker;
    protected MarketplaceAuditClient $auditClient;

    public function upgrade(int $pluginId): array
    {
        $plugin = $this->pluginRepo->find($pluginId);
        if (!$plugin) {
            throw new BusinessException('插件不存在', 404);
        }
        if (($plugin['distribution_source'] ?? '') !== 'marketplace') {
            throw new BusinessException('非 marketplace 来源, 不允许 marketplace 升级', 422);
        }

        // audit 元数据（先填 plugin 本身可知的值）
        $auditCode = (string) ($plugin['code']    ?? '');
        $auditFrom = (string) ($plugin['version'] ?? '');
        $auditTo   = '';

        try {
            $conn = $this->connRepo->findActive();
            if (!$conn) {
                throw new BusinessException('未绑定 Site', 422);
            }
            $cache = (new \app\model\marketplace\MarketplaceAppCache())
                ->where('connection_id', $conn['id'])
                ->where('remote_app_id', $plugin['remote_app_id'])
                ->find();
            if (!$cache) {
                throw new BusinessException('找不到对应权益, 请先同步', 404);
            }
            $cache = $cache->toArray();
            $auditTo = (string) ($cache['latest_version'] ?? '');

            // 升前 hard reject: 新版本不兼容当前 framework-saas → 422
            $this->compatibilityChecker->ensureCompatible($cache, (string) config('version.version'));

            $token  = $this->registration->decryptToken($conn);
            $dlInfo = $this->client->requestDownloadToken((string) $conn['site_base_url'], $token, (string) $plugin['code'], (string) $cache['latest_version']);
            $url    = (string) ($dlInfo['download_url'] ?? '');
            if ($url === '') {
                throw new BusinessException('Site 未返回 download_url', 502);
            }
            if (!str_starts_with($url, 'http')) {
                $url = rtrim((string) $conn['site_base_url'], '/') . $url;
            }
            $dl = $this->downloader->download($url, $token, (string) $conn['site_base_url']);

            try {
                $result = $this->upgradeService->upgrade($pluginId, $dl['path']);
                Db::name('plugins')->where('id', $pluginId)->update([
                    'remote_version_id' => $cache['latest_version_id'],
                    'installed_hash'    => $dl['sha256'],
                    'signature_status'  => 'verified',
                    'update_available'  => 0,
                    'updated_at'        => date('Y-m-d H:i:s'),
                ]);
                Event::trigger('marketplace.app.upgraded', ['plugin_id' => $pluginId, 'connection_id' => $conn['id']]);
                $this->auditClient->report([
                    'type'         => 'upgrade_success',
                    'plugin_code'  => $auditCode,
                    'from_version' => $auditFrom,
                    'to_version'   => $auditTo,
                ]);
                return ['plugin' => $result];
            } finally {
                @unlink($dl['path']);
            }
        } catch (\Throwable $e) {
            // PluginUpgradeService 失败会自动回滚到 .backup 旧版（见其 catch 块），
            // 这里既上报 upgrade_failure，也补一条 rollback（保持与 spec 卸载/回滚事件对齐）。
            $this->auditClient->report([
                'type'         => 'upgrade_failure',
                'plugin_code'  => $auditCode,
                'from_version' => $auditFrom,
                'to_version'   => $auditTo,
                'error_code'   => $this->classifyError($e),
            ]);
            // NOTE: 此处 trigger 可能在 plugin 层未真正 rollback 时触发（如 download / metadata
            // update 失败、cache miss 等阶段）。严格语义应由 PluginUpgradeService 内部 backup-restore
            // 成功路径触发，而非 marketplace 层无条件 fire。
            // C-2 计划项: 把 rollback event 触发下沉到 PluginUpgradeService 真正回滚分支，避免
            // 虚假 rollback 噪声污染 Site BI。
            // 仅在确实尝试过升级（拿到了 newer to_version）时再触发 rollback 事件（保留既有 saga 行为）
            if ($auditTo !== '') {
                Event::trigger('plugin.rolled_back', [
                    'plugin_code'  => $auditCode,
                    'from_version' => $auditTo,   // 升级前刚装的新版（已被回滚掉）
                    'to_version'   => $auditFrom, // 实际回滚到的旧版
                ]);
            }
            throw $e;
        }
    }

    private function classifyError(\Throwable $e): string
    {
        $short = (new \ReflectionClass($e))->getShortName();
        return $short . '_' . (int) $e->getCode();
    }
}
