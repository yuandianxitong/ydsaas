<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\service\saas;

use core\base\Service;
use core\exception\BusinessException;
use core\tenant\TenantContext;
use think\facade\Db;

/**
 * TenantQuotaService
 *
 * 租户存储配额硬约束 Service（M3C T95）。
 *
 * 职责：
 *   - usage()                当前租户配额快照 [used, limit, free]
 *   - assertCanStore($bytes) 上传前检查：used + bytes > limit → 抛 QuotaExceeded
 *   - consume($bytes)        上传后增加：原子 storage_used_bytes +=
 *   - release($bytes)        删除后减少：原子 storage_used_bytes -=，不下溢
 *
 * 全部操作针对 TenantContext::current()->id()。platformapi 调用会因为
 * TenantContext 不存在或处于 platform 上下文而抛错 —— 这是故意的：
 * 平台不应"代租户消耗配额"，如果有这种场景请显式 TenantContext::runAs()。
 *
 * 原子性：consume / release 用单条 UPDATE 语句，不做 find + save。
 */
class TenantQuotaService extends Service
{
    /**
     * 当前租户的配额快照。
     *
     * @return array{used:int,limit:int,free:int}
     */
    public function usage(): array
    {
        $tenantId = $this->currentTenantId();
        $row = Db::table('tenants')
            ->where('id', $tenantId)
            ->field('storage_used_bytes,storage_limit_bytes')
            ->find();
        if (!$row) {
            throw new BusinessException('租户不存在：' . $tenantId);
        }
        $used  = (int) ($row['storage_used_bytes']  ?? 0);
        $limit = (int) ($row['storage_limit_bytes'] ?? 0);
        return [
            'used'  => $used,
            'limit' => $limit,
            'free'  => max(0, $limit - $used),
        ];
    }

    /**
     * 断言：当前租户还能再存下 $bytes 字节。
     *
     * limit = 0 表示不限制（历史兼容），直接放行。
     */
    public function assertCanStore(int $bytes): void
    {
        if ($bytes < 0) {
            throw new BusinessException('非法的文件大小：' . $bytes);
        }
        $snap = $this->usage();
        if ($snap['limit'] <= 0) {
            return; // 0 = unlimited
        }
        if ($snap['used'] + $bytes > $snap['limit']) {
            throw new BusinessException(sprintf(
                '存储配额已满：已用 %s / 配额 %s，本次需要 %s，请升级套餐或删除旧文件',
                $this->formatBytes($snap['used']),
                $this->formatBytes($snap['limit']),
                $this->formatBytes($bytes)
            ));
        }
    }

    /**
     * 原子递增当前租户的 storage_used_bytes。
     */
    public function consume(int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }
        $tenantId = $this->currentTenantId();
        Db::table('tenants')
            ->where('id', $tenantId)
            ->inc('storage_used_bytes', $bytes)
            ->update();
    }

    /**
     * 原子递减当前租户的 storage_used_bytes，GREATEST(0, ...) 防下溢。
     */
    public function release(int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }
        $tenantId = $this->currentTenantId();
        Db::execute(
            'UPDATE tenants SET storage_used_bytes = GREATEST(0, CAST(storage_used_bytes AS SIGNED) - ?) WHERE id = ?',
            [$bytes, $tenantId]
        );
    }

    private function currentTenantId(): int
    {
        $ctx = TenantContext::current();
        if ($ctx === null || $ctx->isPlatform()) {
            throw new BusinessException('当前不在租户上下文，无法操作配额');
        }
        $id = $ctx->id();
        if ($id <= 0) {
            throw new BusinessException('租户 ID 非法：' . $id);
        }
        return $id;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . 'B';
        }
        $kb = $bytes / 1024;
        if ($kb < 1024) {
            return number_format($kb, 2) . 'KB';
        }
        $mb = $kb / 1024;
        if ($mb < 1024) {
            return number_format($mb, 2) . 'MB';
        }
        $gb = $mb / 1024;
        return number_format($gb, 2) . 'GB';
    }
}
