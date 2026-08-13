<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace app\service\member;

use app\service\user\UserManageService;
use core\base\Service;
use core\member\MemberStatCatalog;

/**
 * 会员统计数聚合：解析 keys → 目录白名单过滤 → 内置/插件 provider 分发 → 合并。
 * 单插件 provider 异常隔离（error_log + 该插件键剔除），与装修树 hydrate 的 fail-safe 同策略。
 */
class MemberStatsService extends Service
{
    protected MemberStatCatalog $memberStatCatalog;
    protected UserManageService $userManageService;

    /** @return array<string,int|string> 全限定 key => 数值 */
    public function statsFor(int $tenantId, int $userId, array $keys): array
    {
        $keys = array_slice(array_values(array_unique(array_filter(array_map('strval', $keys)))), 0, 32);
        $index = $this->memberStatCatalog->keyIndex($tenantId);

        /** @var array<string, array<string,string>> plugin => [raw_key => fq_key] */
        $byPlugin = [];
        foreach ($keys as $fq) {
            if (!isset($index[$fq])) {
                continue; // 目录外（退订/未声明/拼写错误）静默剔除
            }
            $byPlugin[$index[$fq]['plugin']][$index[$fq]['raw_key']] = $fq;
        }

        $out = [];
        if (!empty($byPlugin[''])) {
            foreach ($this->builtinCounts($userId, array_keys($byPlugin[''])) as $raw => $v) {
                $out[$byPlugin[''][$raw]] = $v;
            }
            unset($byPlugin['']);
        }
        foreach ($byPlugin as $code => $map) {
            $provider = $this->memberStatCatalog->providerFor($code, $tenantId);
            if ($provider === null) {
                continue;
            }
            try {
                $vals = $provider->counts($tenantId, $userId, array_keys($map));
            } catch (\Throwable $e) {
                error_log("[member-stats] provider {$code} failed: {$e->getMessage()}");
                continue;
            }
            foreach ($map as $raw => $fq) {
                if (array_key_exists($raw, $vals)) {
                    $out[$fq] = $vals[$raw];
                }
            }
        }
        return $out;
    }

    /** @return array<string,int|string> */
    private function builtinCounts(int $userId, array $rawKeys): array
    {
        $out = [];
        foreach ($rawKeys as $k) {
            if ($k === 'balance') {
                $out['balance'] = $this->userManageService->getUserBalance($userId)['balance'];
            } elseif ($k === 'points') {
                $out['points'] = $this->userManageService->getUserPoints($userId)['points'];
            }
        }
        return $out;
    }
}
