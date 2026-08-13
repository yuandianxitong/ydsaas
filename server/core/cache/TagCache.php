<?php
declare(strict_types=1);

namespace core\cache;

use think\facade\Cache;

/**
 * 自愈式标签缓存助手。
 *
 * 背景：ThinkPHP 的 `Cache::tag()->remember()/clear()` 在 File 驱动下用
 * 基类 Driver::push() 维护标签集合——push 先读标签键、要求其值是数组，
 * 否则抛 InvalidArgumentException("only array cache can be push")。
 * 一旦标签键因历史/损坏原因存成了非数组标量，该标签下的所有 remember()
 * 都会 500（Redis 驱动用原生 sAdd，不受影响）。
 *
 * 本助手把标签缓存操作包一层：捕获缓存层异常后清掉可能损坏的标签键并重试，
 * 仍失败则直接执行回调返回业务值。**缓存损坏永远不再击穿业务**。
 *
 * 多租户隔离仍依赖 TenantRedisDriver 的租户前缀——File 驱动下不同租户会
 * 共享 key，生产环境务必使用 Redis（见 .env.example / DEPLOYMENT.md）。
 */
class TagCache
{
    /**
     * 带标签的 remember，缓存层异常时自愈并回退直算。
     *
     * @param string|string[] $tag
     * @param int             $ttl 秒
     */
    public static function remember(string|array $tag, string $key, \Closure $callback, int $ttl)
    {
        try {
            return Cache::tag($tag)->remember($key, $callback, $ttl);
        } catch (\Throwable $e) {
            // 标签键可能损坏（如 "only array cache can be push"）：清掉后重试一次
            self::forgetTagKeys($tag);
            try {
                return Cache::tag($tag)->remember($key, $callback, $ttl);
            } catch (\Throwable $e2) {
                // 仍失败（驱动异常等）：直接计算，保证业务可用
                return $callback();
            }
        }
    }

    /**
     * 清除整个标签，异常时兜底直接删标签键。
     *
     * @param string|string[] $tag
     */
    public static function clear(string|array $tag): void
    {
        try {
            Cache::tag($tag)->clear();
        } catch (\Throwable $e) {
            self::forgetTagKeys($tag);
        }
    }

    /**
     * 直接删除标签键本身（下次写入会以空数组重建），绕开 clear 里易碎的成员遍历。
     *
     * @param string|string[] $tag
     */
    private static function forgetTagKeys(string|array $tag): void
    {
        foreach ((array) $tag as $t) {
            try {
                Cache::delete(Cache::getTagKey($t));
            } catch (\Throwable $e) {
                // 忽略：自愈尽力而为
            }
        }
    }
}
