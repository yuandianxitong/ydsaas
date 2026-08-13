<?php
declare(strict_types=1);

namespace app\platformapi\middleware;

use core\http\Middleware;
use Closure;
use think\facade\Db;
use think\facade\Log;
use think\Request;
use think\Response;

class PlatformLogMiddleware extends Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $response = $next($request);

        if (in_array($request->method(), ['POST', 'PUT', 'DELETE'], true)) {
            // 之前走 QueueManager::push → redis 队列；dev/单机环境没人 queue:work 消费 →
            // 操作日志永远不落库。直接 try/catch INSERT 表（操作日志失败不应阻塞响应）。
            try {
                $responseData = json_decode($response->getContent(), true);
                Db::table('platform_operation_logs')->insert([
                    'platform_admin_id' => (int) ($request->platformAdminId ?? 0),
                    'method'            => $request->method(),
                    'path'              => $request->pathinfo(),
                    'params'            => json_encode($request->param(), JSON_UNESCAPED_UNICODE),
                    'ip'                => $request->ip(),
                    'target_tenant_id'  => (int) ($request->param('tenant_id') ?? 0),
                    'response_code'     => (int) ($responseData['code'] ?? 0),
                    'duration_ms'       => (int) round((microtime(true) - $startTime) * 1000),
                    'created_at'        => date('Y-m-d H:i:s'),
                ]);
            } catch (\Throwable $e) {
                Log::warning('platform operation log write failed: ' . $e->getMessage());
            }
        }

        return $response;
    }
}
