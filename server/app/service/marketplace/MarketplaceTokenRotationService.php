<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\marketplace;

use app\repository\marketplace\MarketplaceConnectionRepository;
use core\base\Service;
use think\facade\Db;
use think\facade\Log;

/**
 * marketplace_connections token 自动轮换：
 * - rotateAll(): cron 入口，遍历 active connection，触发到期的轮换
 * - rotateOne(int): controller 手动触发用
 *
 * 触发阈值: token_rotate_threshold_days - token_rotate_warn_days (默认 90 - 7 = 83 天)
 *           提前 7 天就开始尝试轮换，让 UI 有时间显示 warn banner
 * 失败语义: 写 last_error + Log::warning，不抛、不动 status，cron 下次再试
 */
class MarketplaceTokenRotationService extends Service
{
    protected MarketplaceConnectionRepository $connRepo;
    protected OfficialMarketplaceClient $client;
    protected TokenCipher $cipher;

    public function rotateAll(): void
    {
        $threshold = (int) config('saas.marketplace.token_rotate_threshold_days', 90);
        $warn      = (int) config('saas.marketplace.token_rotate_warn_days', 7);
        $triggerSeconds = max(0, $threshold - $warn) * 86400;

        $rows = Db::name('marketplace_connections')
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->select()
            ->toArray();
        $now = time();
        foreach ($rows as $row) {
            $rotatedTs = isset($row['token_rotated_at']) ? strtotime((string) $row['token_rotated_at']) : 0;
            if ($rotatedTs <= 0 || ($now - $rotatedTs) < $triggerSeconds) {
                continue;
            }
            $this->rotateOne((int) $row['id'], $row);
        }
    }

    public function rotateOne(int $connectionId, ?array $row = null): bool
    {
        $row ??= Db::name('marketplace_connections')->find($connectionId);
        if (!$row) {
            return false;
        }
        try {
            $oldPlain = $this->cipher->decrypt((string) $row['encrypted_instance_token']);
            $resp = $this->client->rotateToken((string) $row['site_base_url'], $oldPlain);
            $newPlain = (string) $resp['access_token'];
            Db::name('marketplace_connections')->where('id', $connectionId)->update([
                'encrypted_instance_token' => $this->cipher->encrypt($newPlain),
                'token_prefix'             => substr($newPlain, 0, 11),
                'token_rotated_at'         => date('Y-m-d H:i:s'),
                'token_expires_at'         => $resp['expires_at'] ?? null,
                'last_error'               => null,
                'updated_at'               => date('Y-m-d H:i:s'),
            ]);
            Log::info("[token-rotate] connection_id={$connectionId} success");
            return true;
        } catch (\Throwable $e) {
            $msg = 'token-rotate failed: ' . substr($e->getMessage(), 0, 380);
            Db::name('marketplace_connections')->where('id', $connectionId)->update([
                'last_error' => $msg,
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            Log::warning("[token-rotate] connection_id={$connectionId} failed: " . $e->getMessage());
            return false;
        }
    }
}
