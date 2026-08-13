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
use think\facade\Db;
use think\facade\Log;

/**
 * License 状态机编排：
 * - evaluateAll(): cron 入口，遍历所有 marketplace plugin
 * - evaluateConnection(int): sync 触发，针对单连接的所有相关 plugin
 * - transition(): 私有，处理状态切换 + 持久化 + log
 */
class LicenseStateMachine extends Service
{
    protected MarketplaceConnectionRepository $connRepo;
    protected MarketplaceAppCacheRepository $cacheRepo;
    protected RuntimeLicenseEvaluator $evaluator;

    public function evaluateAll(): void
    {
        // B-2 UI 限制 active connection ≤ 1，快路径
        $conn = $this->connRepo->findActive();
        $this->evaluateForConnection($conn);
    }

    public function evaluateConnection(int $connectionId): void
    {
        $conn = $this->connRepo->find($connectionId);
        $this->evaluateForConnection($conn);
    }

    private function evaluateForConnection(?array $conn): void
    {
        $now = time();
        $plugins = Db::name('plugins')
            ->where('distribution_source', 'marketplace')
            ->select()
            ->toArray();

        foreach ($plugins as $plugin) {
            $cache = null;
            if ($conn !== null && !empty($plugin['remote_app_id'])) {
                $cache = $this->cacheRepo->findByConnectionAndRemoteApp(
                    (int) $conn['id'],
                    (string) $plugin['remote_app_id']
                );
            }
            $current = (string) ($plugin['license_status'] ?? 'active');
            $target  = $this->evaluator->determineTarget($plugin, $conn, $cache, $now);
            if ($target !== $current) {
                $this->transition($plugin, $current, $target, $conn, $cache, $now);
            } else {
                Db::name('plugins')->where('id', $plugin['id'])->update([
                    'license_last_check_at' => date('Y-m-d H:i:s', $now),
                ]);
            }
        }
    }

    private function transition(array $plugin, string $from, string $to, ?array $conn, ?array $cache, int $now): void
    {
        $update = [
            'license_status'        => $to,
            'license_last_check_at' => date('Y-m-d H:i:s', $now),
            'updated_at'            => date('Y-m-d H:i:s', $now),
        ];

        if ($from === 'active' && $to === 'grace') {
            $update['license_grace_started_at'] = $this->evaluator->computeGraceStart($conn, $cache, $now);
        }
        if ($to === 'expired') {
            if (($plugin['signature_status'] ?? '') === 'verified') {
                $update['signature_status'] = 'expired';
            }
        }
        if ($to === 'active') {
            $update['license_grace_started_at'] = null;
            if (($plugin['signature_status'] ?? '') === 'expired') {
                $update['signature_status'] = 'verified';
            }
        }

        $this->runInTransaction(function () use ($plugin, $update) {
            Db::name('plugins')->where('id', $plugin['id'])->update($update);
        });

        Log::info(sprintf(
            '[license] plugin_id=%d code=%s %s -> %s',
            $plugin['id'],
            $plugin['code'] ?? '',
            $from,
            $to
        ));

        // C-2 hook: 此处可触发 marketplace.license.transition 事件 + 注册 Listener 接通知/消息渠道
    }
}
