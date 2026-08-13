<?php
declare(strict_types=1);

namespace app\job;

use app\repository\system\AdminOperationLogRepository;
use core\tenant\TenantContext;
use think\facade\Log;
use think\queue\Job;

/**
 * 兼容旧队列任务；新写入已由 AdminLogMiddleware 同步落库。
 * 若仍有积压任务，必须带 tenant_id 并恢复 TenantContext，否则会写成 tenant_id=0。
 */
class AdminOperationLogJob
{
    public function fire(Job $job, array $data): void
    {
        try {
            $tenantId = (int) ($data['tenant_id'] ?? 0);
            if ($tenantId > 0) {
                TenantContext::set([
                    'id'          => $tenantId,
                    'code'        => (string) ($data['tenant_code'] ?? ''),
                    'is_platform' => false,
                ]);
            }

            /** @var AdminOperationLogRepository $logRepository */
            $logRepository = app(AdminOperationLogRepository::class);
            $logRepository->record($data);
            $job->delete();
        } catch (\Throwable $e) {
            Log::error('操作日志写入失败', [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);
            if ($job->attempts() >= 3) {
                $job->delete();
            } else {
                $job->release(10);
            }
        } finally {
            TenantContext::reset();
        }
    }
}
