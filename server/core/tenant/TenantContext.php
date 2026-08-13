<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace core\tenant;

/**
 * TenantContext - 请求级租户上下文
 *
 * 单例风格，但每个请求都会被中间件 reset + set。
 * 平台超管可以用 runAs() / runWithoutScope() 临时切换。
 */
final class TenantContext
{
    /** @var array<int, array{id:int, code:string, is_platform:bool, raw:array}>  上下文栈，支持嵌套 runAs */
    private static array $stack = [];

    /** @var int  作用域绕过计数器（runWithoutScope 嵌套时累加） */
    private static int $bypassCount = 0;

    /** 重置所有状态（测试或请求结束时调用） */
    public static function reset(): void
    {
        self::$stack = [];
        self::$bypassCount = 0;
    }

    /**
     * 设置当前请求的租户上下文。中间件在 handle() 里调用一次。
     *
     * @param array{id:int, code?:string, is_platform?:bool} $data
     */
    public static function set(array $data): void
    {
        self::$stack = [[
            'id'          => (int)($data['id'] ?? 0),
            'code'        => (string)($data['code'] ?? ''),
            'is_platform' => (bool)($data['is_platform'] ?? false),
            'raw'         => $data,
        ]];
    }

    /** 获取当前上下文（栈顶） */
    public static function current(): ?TenantContextSnapshot
    {
        if (empty(self::$stack)) {
            return null;
        }
        return new TenantContextSnapshot(end(self::$stack));
    }

    /**
     * 临时切换到指定 tenant_id 执行回调。
     * 仅平台超管允许调用。回调结束后自动恢复。
     */
    public static function runAs(int $tenantId, \Closure $fn): mixed
    {
        $snapshot = [
            'id'          => $tenantId,
            'code'        => '',
            'is_platform' => false,
            'raw'         => ['id' => $tenantId, 'via' => 'runAs'],
        ];
        self::$stack[] = $snapshot;

        try {
            return $fn();
        } finally {
            array_pop(self::$stack);
        }
    }

    /**
     * 临时绕过 tenant scope 执行回调。
     * 用于平台运营的全局统计场景。
     */
    public static function runWithoutScope(\Closure $fn): mixed
    {
        self::$bypassCount++;
        try {
            return $fn();
        } finally {
            self::$bypassCount--;
        }
    }

    public static function isScopeBypassed(): bool
    {
        return self::$bypassCount > 0;
    }
}
