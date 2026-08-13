<?php
declare(strict_types=1);

namespace core\cache;

use core\tenant\TenantContext;
use think\cache\driver\Redis;

/**
 * TenantRedisDriver - 租户感知的 Redis 缓存驱动
 *
 * 继承 ThinkPHP Redis 驱动，自动为所有缓存 key 和 tag key 注入租户前缀：
 *   - 平台端 (is_platform=true): "platform:"
 *   - 租户端 (tenant_id=N):      "tN:"
 *   - 上下文未设置:               "global:"
 *
 * clear() 重写为只清除当前上下文前缀下的 key（SCAN），不再 FLUSHDB。
 */
class TenantRedisDriver extends Redis
{
    /**
     * 获取租户前缀
     */
    public function getTenantPrefix(): string
    {
        $ctx = TenantContext::current();
        if ($ctx === null) {
            return 'global:';
        }
        if ($ctx->isPlatform()) {
            return 'platform:';
        }
        return 't' . $ctx->id() . ':';
    }

    /**
     * 重写缓存 key 生成：租户前缀 + 原始 key
     */
    public function getCacheKey(string $name): string
    {
        return $this->getTenantPrefix() . parent::getCacheKey($name);
    }

    /**
     * 重写 tag key 生成：租户前缀 + tag key
     * 保证不同租户的 tag 集合互相隔离
     */
    public function getTagKey(string $tag): string
    {
        return $this->getTenantPrefix() . parent::getTagKey($tag);
    }

    /**
     * 重写 clear：只清除当前租户前缀的 key
     * 使用 SCAN 避免 KEYS 命令阻塞 Redis
     */
    public function clear(): bool
    {
        $prefix = $this->getTenantPrefix();
        $pattern = $prefix . '*';
        $cursor = null;
        $handler = $this->handler();

        do {
            $result = $handler->scan($cursor, $pattern, 200);
            if ($result === false) {
                break;
            }
            if (!empty($result)) {
                $handler->del(...$result);
            }
        } while ($cursor > 0);

        return true;
    }
}
