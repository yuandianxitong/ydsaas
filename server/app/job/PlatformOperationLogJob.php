<?php
declare(strict_types=1);

namespace app\job;

use think\facade\Db;
use think\facade\Log;
use think\queue\Job;

class PlatformOperationLogJob
{
    public function fire(Job $job, array $data): void
    {
        try {
            Db::table('platform_operation_logs')->insert([
                'platform_admin_id' => (int) ($data['platform_admin_id'] ?? $data['admin_id'] ?? 0),
                'method'            => (string) ($data['method'] ?? ''),
                'path'              => (string) ($data['path'] ?? ''),
                'params'            => is_string($data['params'] ?? null)
                    ? $data['params']
                    : json_encode($data['params'] ?? [], JSON_UNESCAPED_UNICODE),
                'ip'                => (string) ($data['ip'] ?? ''),
                'target_tenant_id'  => (int) ($data['target_tenant_id'] ?? 0),
                'response_code'     => (int) ($data['response_code'] ?? $data['result_code'] ?? 0),
                'duration_ms'       => (int) ($data['duration_ms'] ?? $data['execution_time'] ?? 0),
                'created_at'        => (string) ($data['created_at'] ?? date('Y-m-d H:i:s')),
            ]);
            $job->delete();
        } catch (\Throwable $e) {
            Log::error('平台操作日志写入失败', [
                'error' => $e->getMessage(),
                'data'  => $data,
            ]);
            if ($job->attempts() >= 3) {
                $job->delete();
            } else {
                $job->release(10);
            }
        }
    }
}
