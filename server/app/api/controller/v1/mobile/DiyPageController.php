<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\api\controller\v1\mobile;

use app\service\diy\DiyPageService;
use core\base\Controller;
use core\exception\BusinessException;
use core\tenant\TenantContext;
use think\Response;

/**
 * C 端 UniApp：按 slug 拉取某自定义装修页的已发布树（懒加载）。
 *
 * GET /api/mobile/diy-page?key=about
 *   - 不需要 C 端登录
 *   - 必须有 TenantContext（subdomain 解析）
 *   - 仅返回 status=1 且已发布的页；否则 404
 */
class DiyPageController extends Controller
{
    protected DiyPageService $diyPageService;

    public function get(): Response
    {
        $ctx = TenantContext::current();
        if ($ctx === null || $ctx->isPlatform()) {
            throw new BusinessException('租户上下文缺失', 401);
        }
        $key = (string) $this->request->param('key', '');
        if ($key === '') {
            throw new BusinessException('缺少页面标识', 422);
        }
        $page = $this->diyPageService->getPublishedForTenant($ctx->id(), $key);
        if ($page === null) {
            throw new BusinessException('页面不存在', 404);
        }

        return $this->success('ok', $page);
    }
}
