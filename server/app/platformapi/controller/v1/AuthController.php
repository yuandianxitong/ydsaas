<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * Slogan：提供高质量行业系统源码，帮助中小企业快速搭建专属应用
 * Author：mashanglai Team
 * ============================================================ */
declare(strict_types=1);

namespace app\platformapi\controller\v1;

use core\base\Controller;
use core\auth\TokenManager;
use app\service\saas\PlatformAuthService;
use app\service\system\CaptchaService;
use app\platformapi\validate\v1\PlatformLoginValidate;
use think\Response;

/**
 * 平台超管认证 Controller
 *
 * 三个端点：
 *   POST /platformapi/auth/login    登录
 *   GET  /platformapi/auth/me       当前用户信息（需 token）
 *   POST /platformapi/auth/refresh  刷新 token（需 token）
 *   POST /platformapi/auth/logout   注销（需 token）
 */
class AuthController extends Controller
{
    protected PlatformAuthService $platformAuthService;
    protected CaptchaService $captchaService;

    /**
     * 获取验证码
     * GET /platformapi/auth/captcha
     */
    public function captcha(): Response
    {
        $data = $this->captchaService->generate();
        return $this->success(lang('messages.get_success'), $data);
    }

    /**
     * 平台超管登录
     */
    public function login(): Response
    {
        // 兼容 JSON 与表单提交
        $data = $this->request->param();
        if (empty($data)) {
            $json = json_decode((string) $this->request->getContent(), true);
            if (is_array($json)) {
                $data = $json;
            }
        }
        $data = array_merge(['username' => null, 'password' => null], (array) $data);

        $this->validate($data, PlatformLoginValidate::class);

        $result = $this->platformAuthService->login(
            (string) $data['username'],
            (string) $data['password'],
            (string) $this->request->ip()
        );

        return $this->success(lang('messages.login_success'), $result);
    }

    /**
     * 当前登录用户信息
     */
    public function me(): Response
    {
        $id = (int) ($this->request->platformAdminId ?? 0);
        $admin = $this->platformAuthService->me($id);
        return $this->success(lang('messages.get_success'), $admin);
    }

    /**
     * 当前登录用户信息 + 菜单 + 权限
     *
     * 前端 permission guard 登录后调用此接口拿到 routes 并动态注册。
     */
    public function info(): Response
    {
        $id = (int) ($this->request->platformAdminId ?? 0);
        $info = $this->platformAuthService->info($id);
        return $this->success(lang('messages.get_success'), $info);
    }

    /**
     * 刷新 Token
     *
     * 前端 axios 请求拦截器在剩余寿命 < 50% 时静默调用此接口，
     * 实现"用户编辑文章过程中 token 即将过期，保存时自动续签"的体验。
     */
    public function refresh(): Response
    {
        $tokenManager = TokenManager::scope('platform');
        $token = $tokenManager->getTokenFromHeader();
        if (!$token) {
            return $this->error(lang('auth.token_empty'), 401);
        }
        $newToken = $tokenManager->refresh($token);
        return $this->success(lang('messages.refresh_success'), ['token' => $newToken]);
    }

    /**
     * 注销
     */
    public function logout(): Response
    {
        $token = TokenManager::scope('platform')->getTokenFromHeader();
        if ($token) {
            $this->platformAuthService->logout($token);
        }
        return $this->success(lang('messages.logout_success'));
    }
}
