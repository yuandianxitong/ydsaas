<?php
declare(strict_types=1);

namespace app\api\controller\v1\health;

use core\base\Controller;
use core\http\Response;
use think\facade\Db;
use think\facade\Cache;
use think\facade\Config;

class HealthController extends Controller
{
    public function check(): \think\Response
    {
        $checks = [
            'database'         => $this->checkDatabase(),
            'redis'            => $this->checkRedis(),
            'storage_writable' => $this->checkStorage(),
        ];

        $allOk = !in_array('fail', $checks, true);

        return Response::success('', [
            'status'    => $allOk ? 'ok' : 'degraded',
            'version'   => Config::get('version.version', 'unknown'),
            'checks'    => $checks,
            'timestamp' => time(),
        ], $allOk ? 200 : 503);
    }

    private function checkDatabase(): string
    {
        try {
            Db::query('SELECT 1');
            return 'ok';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    private function checkRedis(): string
    {
        try {
            Cache::set('health_check', 1, 5);
            return Cache::get('health_check') ? 'ok' : 'fail';
        } catch (\Throwable) {
            return 'fail';
        }
    }

    private function checkStorage(): string
    {
        $dir = app()->getRootPath() . 'public/storage';
        return is_writable($dir) ? 'ok' : 'fail';
    }
}
