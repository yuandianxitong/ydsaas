<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace core\mobile;

use Throwable;

/**
 * 把异常 / 长 log 浓缩成「短 DB 可存 + 不含敏感细节」的字符串。
 *
 * 入库的 `tenant_mobile_builds.error_log` 会被租户后台展示，因此必须避免：
 *   - 含 args 的 stack trace（可能泄漏私钥临时文件路径、token、URL）
 *   - 容器内绝对路径（暴露部署结构）
 *   - 超过 50KB 的 log（DB text 列限制）
 *
 * 策略：
 *   - exception → message + 浅层 trace（class::method:line × 5）
 *   - 普通字符串 log → 路径替换为 hash + 截断 50KB
 *   - 完整 trace 仍写 Log::error 供运维查看
 */
final class ErrorLogSanitizer
{
    private const MAX_LEN = 51200;        // DB error_log 列上限
    private const TRACE_DEPTH = 5;

    /**
     * @param array<string, mixed> $context  额外上下文（如 build_id），写 Log
     */
    public static function fromException(Throwable $e, array $context = []): string
    {
        // 完整 trace 进框架日志，便于运维定位
        \think\facade\Log::error('[mobile-builder] exception', array_merge($context, [
            'class'   => $e::class,
            'message' => $e->getMessage(),
            'file'    => self::redactPath($e->getFile()),
            'line'    => $e->getLine(),
            'trace'   => $e->getTraceAsString(),
        ]));

        // DB 入库版本：浅层 + 脱敏
        $brief  = '[exception] ' . $e::class . ': ' . self::redactPath($e->getMessage()) . "\n";
        $brief .= '  at ' . self::redactPath($e->getFile()) . ':' . $e->getLine() . "\n";

        $frames = $e->getTrace();
        $shown  = 0;
        foreach ($frames as $f) {
            if ($shown >= self::TRACE_DEPTH) {
                break;
            }
            $where = (isset($f['class'], $f['function'])
                ? $f['class'] . ($f['type'] ?? '::') . $f['function']
                : ($f['function'] ?? '<closure>'));
            $loc = isset($f['file'], $f['line'])
                ? self::redactPath((string) $f['file']) . ':' . $f['line']
                : '<internal>';
            $brief .= "  - {$where} ({$loc})\n";
            $shown++;
        }
        if (count($frames) > self::TRACE_DEPTH) {
            $brief .= '  ... ' . (count($frames) - self::TRACE_DEPTH) . " more frames omitted; see app log for full trace\n";
        }

        return self::truncate($brief);
    }

    /**
     * 普通 log（uni build stdout/stderr 等）：脱敏 + 截断。
     */
    public static function fromLog(string $log): string
    {
        return self::truncate(self::redactPath($log));
    }

    /**
     * 把绝对路径替换为 short hash，避免暴露：
     *   - server runtime/mobile-builds/{tenant_id}/{build_id}/_keys/*.key  → 私钥文件名
     *   - server 容器绝对路径                                              → 部署结构
     *
     * 替换规则：
     *   - 文件名含敏感扩展名（.key / .pem）→ 全路径 hash，保留扩展名
     *   - 其它绝对路径 → /<hash>/{最后一段}
     *
     * 用 (?<![A-Za-z0-9.]) 避免误命中 `./uniapp/src/pages.json` 等相对路径。
     */
    private static function redactPath(string $text): string
    {
        return preg_replace_callback(
            '#(?<![A-Za-z0-9.])(/[A-Za-z0-9_.\-]+){3,}#',
            static function (array $m) {
                $full = $m[0];
                $hash = substr(md5($full), 0, 8);
                $base = basename($full);
                // 敏感扩展名整体打码
                if (preg_match('/\.(key|pem|crt|p12)$/i', $base, $extM)) {
                    return "/<{$hash}>/<redacted>.{$extM[1]}";
                }
                return "/<{$hash}>/{$base}";
            },
            $text,
        ) ?? $text;
    }

    private static function truncate(string $text): string
    {
        if (mb_strlen($text) <= self::MAX_LEN) {
            return $text;
        }
        // 保留头尾：编译过程很长时，真正错误通常在末尾（如 miniprogram-ci [error]）
        $marker = "\n... [truncated by sanitizer]\n";
        $budget = self::MAX_LEN - mb_strlen($marker);
        $headLen = (int) floor($budget * 0.25);
        $tailLen = $budget - $headLen;
        return mb_substr($text, 0, $headLen) . $marker . mb_substr($text, -$tailLen);
    }
}
