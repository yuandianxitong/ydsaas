<?php
/* ============================================================
 * 项目：元点Admin
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\tenantapi\controller\v1\auth;

use app\service\system\AdminService;
use app\service\system\CaptchaService;
use app\service\system\MenuService;
use app\tenantapi\validate\v1\auth\LoginValidate;
use core\base\Controller;
use core\auth\TokenManager;
use think\Response;
use core\attribute\PermissionSkip;
use OpenApi\Attributes as OA;

#[OA\Tag(name: '认证管理', description: '登录、登出、Token刷新、验证码')]
class AuthController extends Controller
{
    protected AdminService $adminService;
    protected CaptchaService $captchaService;
    protected MenuService $menuService;
    protected \app\service\saas\TenantFeatureService $tenantFeatureService;
    protected \app\service\saas\EntitlementService $entitlementService;
    protected TokenManager $tokenManager;

    protected function initialize(): void
    {
        // TokenManager 构造器为 private，无法被基类的 DI 自动解析；显式通过工厂获取 tenant scope 实例
        $this->tokenManager = TokenManager::scope('tenant');
    }

    #[OA\Get(
        path: '/auth/captcha',
        summary: '获取验证码',
        tags: ['认证管理'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: '获取成功'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'captcha_key', type: 'string', description: '验证码key'),
                    new OA\Property(property: 'captcha_image', type: 'string', description: '验证码图片Base64'),
                ], type: 'object'),
            ]))
        ]
    )]
    #[PermissionSkip]
    public function captcha(): Response
    {
        $data = $this->captchaService->generate();
        return $this->success(lang('messages.get_success'), $data);
    }

    #[OA\Post(
        path: '/auth/login',
        summary: '管理员登录',
        tags: ['认证管理'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password', 'captcha_key', 'captcha'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', description: '用户名', example: 'admin'),
                    new OA\Property(property: 'password', type: 'string', description: '密码', example: '123456'),
                    new OA\Property(property: 'captcha_key', type: 'string', description: '验证码key'),
                    new OA\Property(property: 'captcha', type: 'string', description: '验证码'),
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: '登录成功', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'message', type: 'string', example: '登录成功'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'token', type: 'string', description: 'JWT Token'),
                    new OA\Property(property: 'admin', properties: [
                        new OA\Property(property: 'id', type: 'integer'),
                        new OA\Property(property: 'username', type: 'string'),
                        new OA\Property(property: 'nickname', type: 'string'),
                        new OA\Property(property: 'avatar', type: 'string'),
                    ], type: 'object'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 400, description: '登录失败', content: new OA\JsonContent(ref: '#/components/schemas/ErrorResponse'))
        ]
    )]
    #[PermissionSkip]
    public function login(): Response
    {
        // 兼容 JSON 与表单提交
        $data = $this->request->param();
        if (empty($data)) {
            $json = json_decode((string)$this->request->getContent(), true);
            if (is_array($json)) {
                $data = $json;
            }
        }

        // 预填默认键以避免未定义下标
        $data = array_merge(['username' => null, 'password' => null, 'captcha_key' => null, 'captcha' => null], (array)$data);

        // 参数验证
        $this->validate($data, LoginValidate::class);

        // 验证码校验
        $captchaKey = (string)($data['captcha_key'] ?? '');
        $captchaCode = (string)($data['captcha'] ?? '');
        if (empty($captchaKey) || empty($captchaCode)) {
            return $this->error(lang('auth.captcha_required'));
        }
        if (!$this->captchaService->verify($captchaKey, $captchaCode)) {
            return $this->error(lang('auth.captcha_invalid'));
        }

        // 执行登录逻辑（BusinessException 由全局异常处理器统一返回 400）
        $result = $this->adminService->login(
            (string)($data['username'] ?? ''),
            (string)($data['password'] ?? ''),
            (string)$this->request->ip(),
            (string)$this->request->header('User-Agent', '')
        );

        return $this->success(lang('messages.login_success'), $result);
    }

    #[PermissionSkip]
    #[OA\Get(
        path: '/auth/info',
        summary: '获取当前用户信息',
        security: [['bearerAuth' => []]],
        tags: ['认证管理'],
        responses: [
            new OA\Response(response: 200, description: '获取成功', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'admin', type: 'object', description: '管理员信息'),
                    new OA\Property(property: 'routes', type: 'array', items: new OA\Items(type: 'object'), description: '菜单路由'),
                    new OA\Property(property: 'permissions', type: 'array', items: new OA\Items(type: 'string'), description: '权限列表'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: '未认证')
        ]
    )]
    public function info(): Response
    {
        $adminId = $this->getUserId();
        $adminInfo = $this->adminService->getAdminInfo($adminId);

        // 获取菜单路由
        $routes = $this->menuService->getFrontendRoutes($adminInfo['menu_ids']);

        // SaaS 租户元数据
        $tenant = \core\tenant\TenantContext::current();
        $saas = [
            'lifecycle_state' => 'active',
            'grace_until'     => null,
            'expires_at'      => null,
            'tenant_name'     => '',
            'tenant_code'     => '',
            'tenant_logo'     => '',
            'plan_id'         => 0,
            'features'        => [],
            'entitlements'    => [],
            'limits'          => [
                'storage_used_bytes'  => 0,
                'storage_limit_bytes' => 0,
            ],
        ];
        if ($tenant !== null && !$tenant->isPlatform()) {
            $rawTop = $tenant->raw();
            // TenantContextSnapshot 的 raw() 返回 set() 时传入的 data 数组。
            // 兼容嵌套（'raw' 子键）和平铺两种形态，避免上游结构变动时崩掉 info 接口。
            $raw = $rawTop['raw'] ?? $rawTop;
            $saas['lifecycle_state'] = (string) ($raw['lifecycle_state'] ?? 'active');
            $saas['expires_at']      = $raw['expires_at'] ?? null;
            $saas['tenant_name']     = (string) ($raw['name'] ?? '');
            $saas['tenant_code']     = (string) ($raw['tenant_code'] ?? '');
            $saas['tenant_logo']     = (string) ($raw['logo'] ?? '');
            $saas['limits']['storage_used_bytes']  = (int) ($raw['storage_used_bytes'] ?? 0);
            $saas['limits']['storage_limit_bytes'] = (int) ($raw['storage_limit_bytes'] ?? 0);

            // 计算 grace_until（expires_at + grace_days）
            if (!empty($raw['expires_at'])) {
                $graceDays = (int) \think\facade\Config::get('saas.grace_days', 7);
                $expiresAtTs = strtotime((string) $raw['expires_at']);
                if ($expiresAtTs !== false) {
                    $saas['grace_until'] = date('Y-m-d H:i:s', $expiresAtTs + $graceDays * 86400);
                }
            }

            $saas['plan_id'] = (int) ($raw['plan_id'] ?? 0);

            // 通过 TenantFeatureService 获取 features（含增值功能）
            $saas['features'] = $this->tenantFeatureService->listFeatures();

            // 权利清单（含 name/icon 展示字段）
            $saas['entitlements'] = $this->entitlementService->listForDisplay($tenant->id());
        }

        $result = [
            'admin'       => $adminInfo,
            'routes'      => $routes,
            'permissions' => $adminInfo['permissions'] ?? [],
            'saas'        => $saas,
        ];

        return $this->success(lang('messages.get_success'), $result);
    }

    #[PermissionSkip]
    #[OA\Post(
        path: '/auth/refresh',
        summary: '刷新Token',
        security: [['bearerAuth' => []]],
        tags: ['认证管理'],
        responses: [
            new OA\Response(response: 200, description: '刷新成功', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'code', type: 'integer', example: 200),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'token', type: 'string', description: '新的JWT Token'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Token无效')
        ]
    )]
    public function refresh(): Response
    {
        $token = $this->tokenManager->getTokenFromHeader();
        if (!$token) {
            return $this->error(lang('auth.token_empty'), 401);
        }

        $newToken = $this->tokenManager->refresh($token);

        return $this->success(lang('messages.refresh_success'), ['token' => $newToken]);
    }

    #[PermissionSkip]
    #[OA\Post(
        path: '/auth/logout',
        summary: '登出',
        security: [['bearerAuth' => []]],
        tags: ['认证管理'],
        responses: [
            new OA\Response(response: 200, description: '登出成功', content: new OA\JsonContent(ref: '#/components/schemas/SuccessResponse'))
        ]
    )]
    public function logout(): Response
    {
        $token = $this->tokenManager->getTokenFromHeader();
        if ($token) {
            $this->tokenManager->blacklist($token);
        }

        return $this->success(lang('messages.logout_success'));
    }
}
