<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace app\middleware;

use app\repository\plugin\PluginRepository;
use Closure;
use think\facade\Log;
use think\Request;
use think\Response;

/**
 * 运行时 license 守护：
 *   - active 放行
 *   - grace 放行 + 加响应头 X-License-Warning（前端弹 banner）
 *   - expired 写请求 403（POST/PUT/DELETE/PATCH），读请求放行；plugin.json 声明
 *     的 read_safe_routes 豁免对应写请求
 *   - 非 marketplace 来源 plugin 永远放行
 *
 * Fail-open: 查表/解析异常 → 放行 + log warning，绝不因 guard 自身 bug 把业务打挂。
 *
 * Task 19 才挂载到三应用 middleware.php，本 Task 仅实现并单测。
 */
class RuntimeLicenseGuard
{
    public function __construct(private PluginRepository $pluginRepo)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        try {
            $path       = '/' . ltrim($request->pathinfo(), '/');
            $pluginCode = $this->matchPluginCode($path);
            if ($pluginCode === null) {
                return $next($request);
            }

            $plugin = $this->pluginRepo->findByCode($pluginCode);
            if (!$plugin || ($plugin['distribution_source'] ?? '') !== 'marketplace') {
                return $next($request);
            }

            $status = (string) ($plugin['license_status'] ?? 'active');
            switch ($status) {
                case 'active':
                    return $next($request);

                case 'grace':
                    // Spec §5.5 drift 保护: 若 cron 失败/未跑，license_status 仍是 grace
                    // 但 grace_started_at + grace_days 已过 → 视为 expired，避免过期
                    // plugin 继续接受写请求。
                    $started   = $plugin['license_grace_started_at'] ?? null;
                    $graceDays = (int) config('saas.marketplace.grace_period_days', 14);
                    if ($started && (strtotime((string) $started) + $graceDays * 86400) < time()) {
                        Log::warning(sprintf(
                            '[license-guard] drift: plugin %s grace started %s elapsed but still status=grace, treating as expired (cron lag?)',
                            $pluginCode,
                            $started
                        ));
                        if ($this->isWriteMethod($request->method()) && !$this->inReadSafeRoutes($request, $path, $plugin)) {
                            return json([
                                'code'    => 403,
                                'message' => '应用授权已过期，仅可读',
                                'data'    => [
                                    'plugin_code'      => $pluginCode,
                                    'license_status'   => 'expired',
                                    'grace_started_at' => $started,
                                    'drift'            => true,
                                ],
                            ], 403);
                        }
                        return $next($request);
                    }
                    $resp = $next($request);
                    $resp->header([
                        'X-License-Warning'     => 'grace',
                        'X-License-Grace-Until' => $this->graceUntil($plugin),
                    ]);
                    return $resp;

                case 'expired':
                    if ($this->isWriteMethod($request->method()) && !$this->inReadSafeRoutes($request, $path, $plugin)) {
                        return json([
                            'code'    => 403,
                            'message' => '应用授权已过期，仅可读',
                            'data'    => [
                                'plugin_code'      => $pluginCode,
                                'license_status'   => 'expired',
                                'grace_started_at' => $plugin['license_grace_started_at'] ?? null,
                            ],
                        ], 403);
                    }
                    return $next($request);

                default:
                    return $next($request);
            }
        } catch (\Throwable $e) {
            Log::warning(sprintf(
                '[license-guard] fail-open path=%s plugin=%s err=%s',
                $path ?? '?',
                $pluginCode ?? '?',
                $e->getMessage()
            ));
            return $next($request);
        }
    }

    /**
     * 把 URI 与已注册的 marketplace plugin code 列表匹配。
     * 命中规则：path 含 "/{code}/" 或以 "/{code}" 结尾。
     * Repository 已按 code 长度倒序返回，确保 longer prefix 先匹配。
     *
     * **假设**: marketplace plugin 的 code 是项目级唯一、非泛词字符串
     * （如 "crm-pro" / "mall-saas"），避免与系统路径段（v1 / api / auth /
     * login / admin 等）冲突。若未来发布 plugin code = "auth"、"crm" 等
     * 短词，可能让 guard 对核心路由也做 license 判定（active 时无害，
     * grace/expired 时会拒写）。C-2 应在 plugin upload validation 层
     * 增加 code 命名规范校验（最小长度 / 命名空间前缀）。
     */
    private function matchPluginCode(string $path): ?string
    {
        $codes = $this->pluginRepo->listMarketplaceCodes();
        foreach ($codes as $code) {
            $code = (string) $code;
            if ($code === '') {
                continue;
            }
            if (str_contains($path, '/' . $code . '/') || str_ends_with($path, '/' . $code)) {
                return $code;
            }
        }
        return null;
    }

    private function isWriteMethod(string $method): bool
    {
        return in_array(strtoupper($method), ['POST', 'PUT', 'DELETE', 'PATCH'], true);
    }

    /**
     * read_safe_routes 模式形如 "POST /api/crm/contacts/search" 或
     * "POST /api/crm/reports/*"。用 fnmatch (shell glob)，禁止正则避免 ReDoS。
     *
     * @param array<string, mixed> $plugin
     */
    private function inReadSafeRoutes(Request $request, string $path, array $plugin): bool
    {
        $raw = $plugin['read_safe_routes'] ?? null;
        if (!$raw) {
            return false;
        }
        $routes = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (!is_array($routes)) {
            return false;
        }
        $candidate = strtoupper($request->method()) . ' ' . $path;
        foreach ($routes as $pattern) {
            if (fnmatch((string) $pattern, $candidate)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param array<string, mixed> $plugin
     */
    private function graceUntil(array $plugin): string
    {
        $days    = (int) config('saas.marketplace.grace_period_days', 14);
        $started = isset($plugin['license_grace_started_at'])
            ? strtotime((string) $plugin['license_grace_started_at'])
            : 0;
        if ($started <= 0) {
            $started = time();
        }
        return date('c', $started + $days * 86400);
    }
}
