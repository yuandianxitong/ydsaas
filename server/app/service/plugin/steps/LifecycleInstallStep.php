<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\service\plugin\steps;

use core\exception\BusinessException;
use core\plugin\contracts\LifecycleHook;
use core\saga\CompensatableStep;

/**
 * v2.7.0：调插件 LifecycleHook.install。
 *
 * - execute：调 install()；无 lifecycle 类是合法的，直接通过
 * - compensate：调 uninstall()；插件作者未实现 uninstall 时静默忽略
 *
 * 协议边界：execute 的副作用域完全由插件作者自由发挥（Redis key / 外部 webhook /
 * 邮件 / 等），框架只能调它自己 declare 的 uninstall 去清理，无法兜底。
 */
class LifecycleInstallStep implements CompensatableStep
{
    /** @param array<string, mixed> $manifest */
    public function __construct(private array $manifest)
    {
    }

    public function name(): string
    {
        return 'LifecycleInstall';
    }

    public function execute(): void
    {
        $this->invoke('install');
    }

    public function compensate(): void
    {
        // 内部 catch：插件作者写挂的 uninstall 不应阻断其它 step 补偿
        try {
            $this->invoke('uninstall');
        } catch (\Throwable $e) {
            error_log('[LifecycleInstallStep] compensate uninstall hook failed: ' . $e->getMessage());
        }
    }

    private function invoke(string $hook): void
    {
        $lifecycleClass = (string) ($this->manifest['lifecycle'] ?? '');
        if ($lifecycleClass === '' || !class_exists($lifecycleClass)) {
            return; // 无 lifecycle 是合法的
        }
        $instance = new $lifecycleClass();
        if (!$instance instanceof LifecycleHook) {
            throw BusinessException::pluginRuntimeError(
                (string) ($this->manifest['code'] ?? '?'),
                $hook,
                new \RuntimeException('lifecycle 类未实现 LifecycleHook 接口')
            );
        }
        try {
            $instance->{$hook}();
        } catch (\Throwable $e) {
            throw BusinessException::pluginRuntimeError(
                (string) ($this->manifest['code'] ?? '?'),
                $hook,
                $e
            );
        }
    }
}
