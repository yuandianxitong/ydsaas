<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin;

use core\plugin\contracts\EventSubscriber;
use think\facade\Event;

/**
 * 插件事件订阅注册器（由 PluginManager 在 boot 已启用插件时调用）。
 *
 * 两种声明方式（可并存）：
 *   1. manifest.events    —— 声明式：{"事件名": "Plugin\\Xxx\\listeners\\Foo"} 或值为类名数组
 *   2. manifest.subscriber —— 实现 EventSubscriber 接口的类，subscribe() 返回同构映射
 *      （适合需要条件判断/闭包 handler 的复杂订阅场景）
 *
 * 安全约束：字符串 handler 必须位于 Plugin\ 命名空间（与 PSR-4 规则一致），
 * 防止插件把监听器绑到框架内部类上。manifest 层由 PluginManifestValidator 把关，
 * 此处对 subscribe() 的运行期返回值做兜底校验。
 */
class PluginEventRegistrar
{
    /**
     * @param array<string, mixed> $manifest 校验通过的 plugin.json
     * @return int 实际注册的监听器数量
     */
    public function register(string $code, array $manifest): int
    {
        $bindings = [];

        foreach ((array) ($manifest['events'] ?? []) as $event => $handlers) {
            foreach ((array) $handlers as $handler) {
                $bindings[] = [(string) $event, $handler];
            }
        }

        $subscriber = (string) ($manifest['subscriber'] ?? '');
        if ($subscriber !== '') {
            if (!class_exists($subscriber) || !is_subclass_of($subscriber, EventSubscriber::class)) {
                error_log(sprintf(
                    '[plugin:%s] subscriber %s 不存在或未实现 EventSubscriber，跳过',
                    $code,
                    $subscriber,
                ));
            } else {
                foreach ($subscriber::subscribe() as $event => $handlers) {
                    foreach ((array) $handlers as $handler) {
                        $bindings[] = [(string) $event, $handler];
                    }
                }
            }
        }

        $registered = 0;
        foreach ($bindings as [$event, $handler]) {
            if ($event === '') {
                continue;
            }
            if (is_string($handler) && !str_starts_with($handler, 'Plugin\\')) {
                error_log(sprintf(
                    '[plugin:%s] 事件 %s 的 handler 必须位于 Plugin\\ 命名空间，跳过：%s',
                    $code,
                    $event,
                    $handler,
                ));
                continue;
            }
            if (!is_string($handler) && !is_callable($handler)) {
                error_log(sprintf('[plugin:%s] 事件 %s 的 handler 类型非法，跳过', $code, $event));
                continue;
            }
            Event::listen($event, $handler);
            $registered++;
        }

        return $registered;
    }
}
