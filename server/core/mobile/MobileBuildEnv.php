<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile;

/**
 * 读取移动端构建相关环境变量。
 *
 * ThinkPHP 的 Env::load() 只写入内部 data，不会 putenv / 写入 $_ENV。
 * 因此不能只读 $_ENV（会永远落到默认 local）。优先级：
 *   1. $_ENV 已设置该键（含空串：表示显式清空，不再回退 .env——供测试用）
 *   2. env()（.env 经 ThinkPHP 加载）
 *   3. 调用方 default
 */
final class MobileBuildEnv
{
    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, $_ENV)) {
            $raw = $_ENV[$key];
            if ($raw === null || $raw === '') {
                return $default;
            }

            return trim((string) $raw);
        }

        $v = env($key);
        if ($v === null || $v === '') {
            return $default;
        }

        return trim((string) $v);
    }

    public static function getInt(string $key, int $default): int
    {
        $v = self::get($key);
        if ($v === null || !is_numeric($v)) {
            return $default;
        }

        return (int) $v;
    }
}
