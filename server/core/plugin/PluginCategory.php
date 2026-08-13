<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */
declare(strict_types=1);

namespace core\plugin;

final class PluginCategory
{
    public const DEFAULT = 'other';

    /** @return array<string, array{label:string,sort:int}> */
    public static function all(): array
    {
        return (array) config('plugin.categories');
    }

    /** @return string[] */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function normalize(?string $category): string
    {
        $key = (string) $category;
        return array_key_exists($key, self::all()) ? $key : self::DEFAULT;
    }
}
