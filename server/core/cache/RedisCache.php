<?php
declare(strict_types=1);

namespace core\cache;

use core\tenant\TenantContext;
use think\facade\Cache;

class RedisCache
{
    protected $redis;

    public function __construct()
    {
        $this->redis = Cache::store('redis')->handler();
    }

    /**
     * 获取租户前缀（与 TenantRedisDriver 保持一致）
     */
    private function prefixKey(string $key): string
    {
        $ctx = TenantContext::current();
        if ($ctx === null) {
            return 'global:' . $key;
        }
        if ($ctx->isPlatform()) {
            return 'platform:' . $key;
        }
        return 't' . $ctx->id() . ':' . $key;
    }

    /**
     * 分布式锁
     */
    public function lock(string $key, int $expire = 10): bool
    {
        $lockKey = $this->prefixKey("lock:{$key}");
        $identifier = uniqid();

        $result = $this->redis->set($lockKey, $identifier, ['NX', 'EX' => $expire]);
        return $result === 'OK';
    }

    /**
     * 释放锁
     */
    public function unlock(string $key): bool
    {
        $lockKey = $this->prefixKey("lock:{$key}");
        return $this->redis->del($lockKey) > 0;
    }

    /**
     * 计数器
     */
    public function counter(string $key, int $expire = 3600): int
    {
        $prefixed = $this->prefixKey($key);
        $count = $this->redis->incr($prefixed);
        if ($count === 1) {
            $this->redis->expire($prefixed, $expire);
        }
        return $count;
    }

    /**
     * 限流器
     */
    public function rateLimit(string $key, int $maxAttempts, int $window): bool
    {
        $prefixed = $this->prefixKey($key);
        $current = $this->redis->incr($prefixed);
        if ($current === 1) {
            $this->redis->expire($prefixed, $window);
        }

        return $current <= $maxAttempts;
    }

    /**
     * 队列推送
     */
    public function push(string $queue, $data): bool
    {
        return $this->redis->lpush($this->prefixKey($queue), json_encode($data)) > 0;
    }

    /**
     * 队列弹出
     */
    public function pop(string $queue)
    {
        $data = $this->redis->rpop($this->prefixKey($queue));
        return $data ? json_decode($data, true) : null;
    }

    /**
     * 阻塞队列弹出
     */
    public function blockPop(string $queue, int $timeout = 0)
    {
        $result = $this->redis->brpop($this->prefixKey($queue), $timeout);
        return $result ? json_decode($result[1], true) : null;
    }

    /**
     * 发布消息
     */
    public function publish(string $channel, $message): int
    {
        return $this->redis->publish($this->prefixKey($channel), json_encode($message));
    }

    /**
     * 订阅消息
     */
    public function subscribe(array $channels, \Closure $callback): void
    {
        $prefixedChannels = array_map(fn($ch) => $this->prefixKey($ch), $channels);
        $this->redis->subscribe($prefixedChannels, function($_redis, $channel, $message) use ($callback) {
            $callback($channel, json_decode($message, true));
        });
    }
}
