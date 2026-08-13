<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin\contracts;

/**
 * 插件事件订阅接口。
 * 与 plugin.json 中声明式 events 二选一；本接口适合复杂订阅场景。
 */
interface EventSubscriber
{
    /**
     * 返回事件名到 handler 的映射。
     * handler 可以是同一插件下的类名（自动实例化）或 callable。
     *
     * @return array<string, string|callable>
     */
    public static function subscribe(): array;
}
