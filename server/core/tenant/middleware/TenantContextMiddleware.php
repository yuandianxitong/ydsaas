<?php
declare(strict_types=1);

namespace core\tenant\middleware;

use Closure;
use core\http\Middleware;
use core\tenant\TenantContext;
use core\tenant\TenantResolver;
use core\tenant\TenantNotFoundException;
use app\model\saas\Tenant;
use think\Request;
use think\Response;
use think\facade\Cache;
use think\facade\Config;
use think\facade\Db;

/**
 * tenantapi / api 应用使用的中间件：
 *   1. 从 Host 解析 tenant_code
 *   2. 通过 Tenant 模型加载租户记录（带 Redis 缓存）
 *   3. 写入 TenantContext（id / code / raw data 含 lifecycle_state 计算属性）
 *
 * 解析失败则抛 TenantNotFoundException → 全局异常处理器返回 404。
 */
class TenantContextMiddleware extends Middleware
{
    private const CACHE_TTL = 300; // 5 minutes

    public function handle(Request $request, Closure $next): Response
    {
        TenantContext::reset();

        $rootDomains = (array) Config::get('saas.root_domains', ['app.com']);
        $resolver = new TenantResolver($rootDomains);

        $host = $request->host(true);
        $code = $resolver->parseSubdomain($host);

        // UniApp 小程序 / App 通常请求统一 API 域名，无法靠 Host 子域名区分租户。
        // 独立构建产物会把 tenantCode 写入 src/generated/tenant-config.ts，
        // 请求层通过 X-Tenant-Code 传回，作为非 H5 场景的租户解析兜底。
        if ($code === null) {
            $headerCode = strtolower(trim((string) $request->header('X-Tenant-Code', '')));
            if ($headerCode !== '' && preg_match('/^[a-z0-9][a-z0-9_-]{0,62}$/', $headerCode)) {
                $code = $headerCode;
            }
        }

        // 本地开发 fallback：子域名解析失败时，使用 SAAS_DEV_TENANT_CODE 环境变量
        if ($code === null && app()->isDebug()) {
            $devCode = (string) Config::get('saas.dev_tenant_code', '');
            if ($devCode !== '') {
                $code = $devCode;
            }
        }

        if ($code === null) {
            throw new TenantNotFoundException();
        }

        // TenantContext 尚未设置，缓存 key 自动走 global: 前缀
        $raw = $this->loadTenantData($code);

        if ($raw === null) {
            throw new TenantNotFoundException($code);
        }

        TenantContext::set([
            'id'          => (int) $raw['id'],
            'code'        => $code,
            'is_platform' => false,
            'raw'         => $raw,
        ]);

        return $next($request);
    }

    /**
     * 加载租户数据（优先从缓存读取）
     */
    private function loadTenantData(string $code): ?array
    {
        $cacheKey = 'tenant_lookup:' . $code;

        return Cache::remember($cacheKey, function () use ($code) {
            /** @var Tenant|null $tenant */
            $tenant = Tenant::where('tenant_code', $code)->find();
            if (!$tenant) {
                return null;
            }
            // toArray 包含 lifecycle_state 计算属性（Tenant model 的 $append）
            return $tenant->toArray();
        }, self::CACHE_TTL);
    }

    /**
     * 清除指定租户的查询缓存（租户状态变更时调用）
     *
     * 必须显式构造 global: 前缀的 key，因为 tenant_lookup 缓存在请求引导阶段
     * （TenantContext 未设置）写入，使用 global: 前缀。而本方法可能在平台上下文
     * 中调用（前缀为 platform:），直接用 Cache::delete() 会前缀不匹配。
     */
    public static function clearTenantCache(string $code): void
    {
        // tenant_lookup 缓存在 TenantContext 未设置时写入（global: 前缀），
        // 但本方法可能在平台上下文中调用（platform: 前缀）。
        // 临时清除上下文以确保 Cache facade 使用 global: 前缀。
        $ctx = TenantContext::current();
        TenantContext::reset();
        try {
            Cache::delete('tenant_lookup:' . $code);
        } finally {
            if ($ctx !== null) {
                TenantContext::set([
                    'id'          => $ctx->id(),
                    'code'        => $ctx->code(),
                    'is_platform' => $ctx->isPlatform(),
                    'raw'         => $ctx->raw(),
                ]);
            }
        }
    }

    /**
     * 用 tenant_id 清缓存的便捷入口。
     * 大多数 mutation 路径只持有 tenant_id（plan_id 变更、状态变更、过期回写等），
     * 这里替它们查一次 code 再委托 clearTenantCache。
     */
    public static function clearTenantCacheById(int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $code = (string) Db::table('tenants')->where('id', $tenantId)->value('tenant_code');
        if ($code !== '') {
            self::clearTenantCache($code);
        }
    }
}
