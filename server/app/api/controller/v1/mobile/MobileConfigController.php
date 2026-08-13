<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\api\controller\v1\mobile;

use app\service\saas\TenantMobileConfigService;
use core\base\Controller;
use core\exception\BusinessException;
use core\tenant\TenantContext;
use think\Response;

/**
 * C 端 UniApp：拉取当前租户的移动端配置（首页 / 主题 / Logo / tabBar）。
 *
 * GET /api/mobile/config
 *   - 不需要 C 端用户登录（启动时即调用）
 *   - 必须有 TenantContext（由 subdomain 解析）
 *
 * 响应：软配置字段（主题 / tabBar / 首页装修等），不含微信密钥等敏感列。
 */
class MobileConfigController extends Controller
{
    protected TenantMobileConfigService $configService;

    /**
     * C 端响应允许的字段白名单。
     *
     * 用白名单而不是黑名单（unset 敏感字段）：未来 Service::get() 新增字段
     * 时不会因忘记加黑名单而泄漏（如 wechat_upload_key_ciphertext）。
     * 与构建期 TenantConfigWriter 注入字段对齐，供运行时软刷新。
     */
    private const C_SIDE_ALLOW = [
        'app_name', 'app_logo', 'app_intro',
        'theme_color', 'theme_colors',
        'service_type', 'service_phone',
        'share_title', 'share_image',
        'home_app_code', 'home_page',
        'tabbar', 'tabbar_style',
        'home_decoration',
    ];

    public function get(): Response
    {
        $ctx = TenantContext::current();
        if ($ctx === null || $ctx->isPlatform()) {
            throw new BusinessException('租户上下文缺失', 401);
        }
        $cfg = $this->configService->get($ctx->id());
        $public = array_intersect_key($cfg, array_flip(self::C_SIDE_ALLOW));

        return $this->success('ok', $public);
    }
}
