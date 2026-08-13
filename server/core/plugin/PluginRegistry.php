<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin;

/**
 * 运行期已加载插件清单缓存。
 * 单例语义；由 PluginManager::bootAll() 写入，请求期只读。
 */
class PluginRegistry
{
    /** @var array<string, array<string, mixed>> */
    private array $plugins = [];

    public function register(string $code, array $meta): void
    {
        $this->plugins[$code] = $meta;
    }

    public function has(string $code): bool
    {
        return isset($this->plugins[$code]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function get(string $code): ?array
    {
        return $this->plugins[$code] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->plugins;
    }

    public function clear(): void
    {
        $this->plugins = [];
    }
}
