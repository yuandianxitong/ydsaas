<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */

declare(strict_types=1);

namespace core\apidoc;

use core\attribute\Permission;
use core\attribute\PermissionSkip;
use ReflectionMethod;
use think\Route;

/**
 * 路由驱动的 OpenAPI 文档生成器。
 *
 * 背景：原文档完全依赖手写 #[OA\...] 注解，~30 个平台控制器仅 4 个有注解，
 * 导致租户/套餐/订单/应用等接口在文档里全部缺失。
 *
 * 本生成器：
 *   1. 先用 swagger-php 扫出已写注解的「富文档」路径（保留 schema/参数细节）；
 *   2. 再枚举该 app 的全部路由（include 路由文件 → Route::getRuleList），
 *      反射控制器方法的 #[Permission]/#[PermissionSkip] + docblock，
 *      为「未注解」的接口补齐 path（方法/分组/权限码/鉴权）。
 *
 * 结果：三套文档一次性覆盖各自 app 的全部路由，且零逐方法注解、自动随路由同步。
 */
class RouteApiDocBuilder
{
    /** type => app 配置 */
    private const APPS = [
        'platform' => ['app' => 'platformapi', 'title' => 'YdAdmin SaaS 平台管理 API', 'desc' => '平台超管 RESTful API 文档', 'server' => '/platformapi'],
        'admin'    => ['app' => 'tenantapi',   'title' => 'YdAdmin SaaS 租户后台 API', 'desc' => '租户后台管理 RESTful API 文档', 'server' => '/tenantapi'],
        'api'      => ['app' => 'api',          'title' => 'YdAdmin SaaS 前端应用 API', 'desc' => '前端应用 RESTful API 文档', 'server' => '/api'],
    ];

    /** 控制器短名 → 中文分组（仅美化，未命中回退短名） */
    private const TAG_MAP = [
        'Tenant' => '租户管理', 'Plan' => '套餐管理', 'Order' => '订单管理', 'Refund' => '退款管理',
        'Plugin' => '应用管理', 'PluginGrant' => '应用授权', 'PluginBuild' => '应用云编译',
        'MobileBuild' => '移动端构建', 'PlatformAnnouncement' => '平台公告', 'Audit' => '审计日志',
        'Dashboard' => '仪表盘', 'Auth' => '认证', 'Upload' => '上传',
        'CronJob' => '定时任务', 'Dictionary' => '数据字典', 'CodeGenerator' => '代码生成器',
        'File' => '文件管理', 'Permission' => '权限管理', 'SystemConfig' => '系统配置',
        'PlatformAdmin' => '平台管理员', 'PlatformRole' => '平台角色', 'PlatformMenu' => '平台菜单',
        'PlatformLog' => '平台日志', 'Catalog' => '应用市场', 'Connection' => '市场连接',
        'Install' => '市场安装', 'InstallIntent' => '市场安装意图', 'Changelog' => '更新日志',
        'AppVersion' => '应用版本', 'Region' => '区域管理', 'ApiDoc' => '系统工具',
    ];

    private const HTTP_METHODS = ['get', 'post', 'put', 'delete', 'patch'];

    /** @var array<string,int> operationId 去重 */
    private array $opIds = [];

    /**
     * @return array OpenAPI 3.0 文档数组
     */
    public function build(string $type): array
    {
        $cfg = self::APPS[$type] ?? self::APPS['platform'];
        $app = $cfg['app'];

        $data = $this->scanAnnotated($app);
        // 注解里部分 path 写成了带 app 前缀的绝对路径（如 /platformapi/region），
        // 与 servers.url 叠加会双前缀、且无法与路由派生的相对 path 去重；先归一化。
        $data['paths'] = $this->stripServerPrefix($data['paths'], $cfg['server']);
        $this->mergeRoutes($data, $this->enumerateRoutes($app), $app);

        $data['openapi']            = $data['openapi'] ?? '3.0.0';
        $data['info']['title']       = $cfg['title'];
        $data['info']['description'] = $cfg['desc'];
        $data['info']['version']     = $data['info']['version'] ?? '1.0.0';
        $data['servers']             = [['url' => $cfg['server'], 'description' => $cfg['title']]];
        $data['components']['securitySchemes']['bearerAuth'] ??= [
            'type' => 'http', 'scheme' => 'bearer', 'bearerFormat' => 'JWT',
        ];

        if (!empty($data['paths'])) {
            ksort($data['paths']);
        } else {
            $data['paths'] = [];
        }

        return $data;
    }

    /**
     * swagger-php 扫描已写注解的控制器，返回富文档（失败回退空骨架）。
     */
    private function scanAnnotated(string $app): array
    {
        $scanPaths = [
            root_path() . 'app/' . $app . '/controller/',
            root_path() . 'core/base/Controller.php',
        ];

        $previousLevel = error_reporting(E_ALL & ~E_DEPRECATED);
        ob_start();
        try {
            $openapi = \OpenApi\Generator::scan($scanPaths);
            $json    = $openapi->toJson();
            ob_end_clean();
            $data = json_decode($json, true);
        } catch (\Throwable) {
            ob_end_clean();
            $data = null;
        } finally {
            error_reporting($previousLevel);
        }

        if (!is_array($data)) {
            $data = [];
        }
        $data['paths'] = $data['paths'] ?? [];
        return $data;
    }

    /**
     * 枚举指定 app 的全部路由：临时把容器里的 route 实例换成全新的，
     * include 该 app 的路由文件后读 getRuleList，再还原。
     *
     * @return array<int,array<string,mixed>>
     */
    private function enumerateRoutes(string $app): array
    {
        $container = app();
        $original  = $container->route;
        $fresh     = new Route($container);
        $container->instance('route', $fresh);

        try {
            $dir = $container->getBasePath() . $app . DIRECTORY_SEPARATOR . 'route' . DIRECTORY_SEPARATOR;
            foreach (glob($dir . '*.php') ?: [] as $file) {
                include $file;
            }
            return $fresh->getRuleList();
        } catch (\Throwable) {
            return [];
        } finally {
            $container->instance('route', $original);
        }
    }

    /**
     * 把路由列表合并进 $data['paths']，仅补齐「注解里没有」的 path+method。
     *
     * @param array<int,array<string,mixed>> $rules
     */
    private function mergeRoutes(array &$data, array $rules, string $app): void
    {
        foreach ($rules as $rule) {
            $target = $rule['route'] ?? null;
            // 仅处理 'v1.Xxx/action' 形式的字符串目标（闭包路由跳过）
            if (!is_string($target) || !str_contains($target, '/')) {
                continue;
            }
            [$ctrlPath, $action] = explode('/', $target, 2);
            if (str_contains($action, '/')) {
                continue; // 非常规目标
            }
            $fqcn = 'app\\' . $app . '\\controller\\' . str_replace('.', '\\', $ctrlPath);
            if (!class_exists($fqcn) || !method_exists($fqcn, $action)) {
                continue;
            }
            $ref  = new ReflectionMethod($fqcn, $action);
            $path = $this->normalizeRule((string) $rule['rule']);
            if ($path === '/' || str_contains($path, '<MISS>')) {
                continue;
            }

            $perm    = $this->permission($ref);
            $skip    = (bool) $ref->getAttributes(PermissionSkip::class);
            $summary = $this->summary($ref) ?: $action;
            $tag     = $this->tag($fqcn);
            $params  = $this->pathParams($path);

            foreach ($this->httpMethods((string) $rule['method']) as $method) {
                if (isset($data['paths'][$path][$method])) {
                    continue; // 已有注解，保留富文档
                }
                $op = [
                    'tags'        => [$tag],
                    'summary'     => $summary,
                    'operationId' => $this->operationId($method, $path),
                    'responses'   => ['200' => ['description' => 'OK']],
                ];
                if ($perm !== null) {
                    $op['description'] = '权限标识: `' . $perm . '`';
                }
                if (!$skip) {
                    $op['security'] = [['bearerAuth' => []]];
                }
                if ($params) {
                    $op['parameters'] = $params;
                }
                $data['paths'][$path][$method] = $op;
            }
        }
    }

    /**
     * 去掉注解 path 里多余的 app 前缀，使其相对 servers.url；冲突则合并 method。
     *
     * @param array<string,array<string,mixed>> $paths
     * @return array<string,array<string,mixed>>
     */
    private function stripServerPrefix(array $paths, string $server): array
    {
        $prefix = rtrim($server, '/'); // e.g. /platformapi
        if ($prefix === '') {
            return $paths;
        }
        $out = [];
        foreach ($paths as $path => $ops) {
            $key = $path;
            if ($key === $prefix || str_starts_with($key, $prefix . '/')) {
                $key = substr($key, strlen($prefix));
                if ($key === '') {
                    $key = '/';
                }
            }
            $out[$key] = isset($out[$key]) ? array_merge($out[$key], $ops) : $ops;
        }
        return $out;
    }

    /** ThinkPHP 规则 → OpenAPI 路径：:id / <id> / <id?> → {id} */
    private function normalizeRule(string $rule): string
    {
        $rule = trim($rule);
        $rule = str_replace(['[', ']'], '', $rule);
        $rule = preg_replace('/<(\w+)\??>/', '{$1}', $rule) ?? $rule;
        $rule = preg_replace('/:(\w+)/', '{$1}', $rule) ?? $rule;
        return '/' . ltrim($rule, '/');
    }

    /** @return string[] 小写 HTTP 方法集合 */
    private function httpMethods(string $method): array
    {
        $method = strtolower(trim($method));
        if ($method === '' || $method === '*') {
            return ['get'];
        }
        $out = [];
        foreach (explode('|', $method) as $m) {
            $m = trim($m);
            if (in_array($m, self::HTTP_METHODS, true)) {
                $out[] = $m;
            }
        }
        return $out ?: [];
    }

    private function permission(ReflectionMethod $ref): ?string
    {
        $attrs = $ref->getAttributes(Permission::class);
        if (!$attrs) {
            return null;
        }
        $args = $attrs[0]->getArguments();
        return isset($args[0]) ? (string) $args[0] : ($args['value'] ?? null);
    }

    /** docblock 首行作为 summary */
    private function summary(ReflectionMethod $ref): string
    {
        $doc = $ref->getDocComment();
        if (!$doc) {
            return '';
        }
        if (preg_match('/\*\s+([^\n@*][^\n]*)/', $doc, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    private function tag(string $fqcn): string
    {
        $short = substr($fqcn, strrpos($fqcn, '\\') + 1);
        $short = preg_replace('/Controller$/', '', $short) ?? $short;
        return self::TAG_MAP[$short] ?? $short;
    }

    /** @return array<int,array<string,mixed>> path 参数 */
    private function pathParams(string $path): array
    {
        if (!preg_match_all('/\{(\w+)\}/', $path, $m)) {
            return [];
        }
        $params = [];
        foreach ($m[1] as $name) {
            $params[] = [
                'name'     => $name,
                'in'       => 'path',
                'required' => true,
                'schema'   => ['type' => 'string'],
            ];
        }
        return $params;
    }

    private function operationId(string $method, string $path): string
    {
        $base = $method . preg_replace('/[^a-zA-Z0-9]+/', '_', $path);
        $base = trim($base, '_');
        $n    = $this->opIds[$base] ?? 0;
        $this->opIds[$base] = $n + 1;
        return $n === 0 ? $base : $base . '_' . $n;
    }
}
