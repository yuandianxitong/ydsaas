<?php
declare(strict_types=1);

namespace app\tenantapi\middleware;

use think\Request;
use think\Response;
use think\facade\Config;

class CorsMiddleware
{
    public function handle(Request $request, \Closure $next): Response
    {
        $origin = $request->header('Origin', '');
        $allowed = self::isOriginAllowed($origin);
        $corsOrigin = $allowed ? $origin : '';

        if ($request->method(true) === 'OPTIONS') {
            if (!$allowed) {
                return response('', 403);
            }
            return response('', 204)->header($this->corsHeaders($corsOrigin));
        }

        /** @var Response $response */
        $response = $next($request);

        if ($allowed && $corsOrigin) {
            $response->header($this->corsHeaders($corsOrigin));
        }

        return $response;
    }

    public static function isOriginAllowed(string $origin): bool
    {
        if (empty($origin)) {
            return true;
        }

        if (env('APP_DEBUG', false)) {
            $parsed = parse_url($origin);
            $host = $parsed['host'] ?? '';
            if (in_array($host, ['localhost', '127.0.0.1', '0.0.0.0'], true)) {
                return true;
            }
        }

        $parsed = parse_url($origin);
        $originHost = $parsed['host'] ?? '';

        $platformDomains = Config::get('saas.platform_domains', []);
        if (in_array($originHost, $platformDomains, true)) {
            return true;
        }

        $rootDomains = Config::get('saas.root_domains', []);
        foreach ($rootDomains as $root) {
            if ($originHost === $root || str_ends_with($originHost, '.' . $root)) {
                return true;
            }
        }

        $extra = Config::get('saas.cors.allowed_origins', '');
        if (!empty($extra)) {
            $extraList = array_map('trim', explode(',', $extra));
            if (in_array($origin, $extraList, true)) {
                return true;
            }
        }

        return false;
    }

    private function corsHeaders(string $origin): array
    {
        return [
            'Access-Control-Allow-Origin'      => $origin,
            'Access-Control-Allow-Methods'     => 'GET, POST, PUT, DELETE, PATCH, OPTIONS',
            'Access-Control-Allow-Headers'     => 'Authorization, Content-Type, X-Requested-With, Accept, Origin, X-Trace-Id, think-lang',
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Max-Age'           => '86400',
        ];
    }

}
