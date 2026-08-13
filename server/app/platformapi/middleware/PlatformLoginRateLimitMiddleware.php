<?php
declare(strict_types=1);

namespace app\platformapi\middleware;

use think\Request;
use think\Response;
use think\facade\Cache;
use core\http\Response as CoreResponse;

class PlatformLoginRateLimitMiddleware
{
    protected int $maxAttempts = 5;

    protected int $lockoutSeconds = 900;

    public function handle(Request $request, \Closure $next): Response
    {
        $ip = $request->ip();
        $key = 'platform_login_attempts:' . $ip;
        $lockKey = 'platform_login_locked:' . $ip;

        if (Cache::get($lockKey)) {
            $ttl = Cache::get($lockKey . ':ttl', 0);
            $remaining = max(0, $ttl - time());
            return CoreResponse::error(sprintf(lang('messages.login_rate_limit'), $remaining), 429);
        }

        $attempts = (int) Cache::get($key, 0);

        if ($attempts >= $this->maxAttempts) {
            Cache::set($lockKey, true, $this->lockoutSeconds);
            Cache::set($lockKey . ':ttl', time() + $this->lockoutSeconds, $this->lockoutSeconds);
            Cache::delete($key);
            return CoreResponse::error(sprintf(lang('messages.login_rate_limit'), $this->lockoutSeconds), 429);
        }

        /** @var Response $response */
        $response = $next($request);

        $statusCode = $response->getCode();
        if ($statusCode === 200) {
            Cache::delete($key);
        } else {
            Cache::set($key, $attempts + 1, 60);
        }

        return $response;
    }
}
