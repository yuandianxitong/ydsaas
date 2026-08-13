<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\tenantapi\controller\v1\mobile;

use app\service\saas\H5ReleaseService;
use app\service\saas\TenantMobileBuildService;
use app\service\saas\WechatMiniprogramUploadService;
use app\service\saas\WechatUploadKeyService;
use core\attribute\Permission;
use core\base\Controller;
use core\exception\BusinessException;
use core\tenant\TenantContext;
use think\facade\App;
use think\Response;

/**
 * 租户后台移动端构建生命周期：
 *
 *   GET    /tenantapi/mobile/builds                 列表（分页）
 *   POST   /tenantapi/mobile/builds                 触发构建（body: { platform: 'h5' | 'mp-weixin' | 'app' }）
 *   GET    /tenantapi/mobile/builds/:id             详情（含 error_log 摘要、upload_result）
 *   POST   /tenantapi/mobile/builds/:id/requeue     重投递排队中的构建
 *   POST   /tenantapi/mobile/builds/:id/release     发布 H5 产物
 *   POST   /tenantapi/mobile/builds/:id/upload      上传小程序体验版
 *   POST   /tenantapi/mobile/builds/:id/cancel      取消排队/构建中的任务
 *   POST   /tenantapi/mobile/config/wechat-key      上传 / 替换 小程序私钥（须 POST，PHP 才有 $_FILES）
 *   DELETE /tenantapi/mobile/config/wechat-key      清除已存私钥
 */
class MobileBuildController extends Controller
{
    protected TenantMobileBuildService $buildService;
    protected H5ReleaseService $h5Release;
    protected WechatMiniprogramUploadService $wechatUpload;
    protected WechatUploadKeyService $keyService;

    private function tenantId(): int
    {
        $ctx = TenantContext::current();
        if ($ctx === null) {
            throw new BusinessException('租户上下文缺失');
        }
        return $ctx->id();
    }

    #[Permission('mobile.build.view')]
    public function list(): Response
    {
        $page     = (int) $this->request->param('page', 1);
        $limit    = max(1, min((int) $this->request->param('limit', 20), 100));
        $platform = $this->request->param('platform', null);
        $platform = is_string($platform) && $platform !== '' ? $platform : null;

        $result = $this->buildService->listForTenant($this->tenantId(), $page, $limit, $platform);
        return $this->success('ok', $result);
    }

    #[Permission('mobile.build.view')]
    public function detail(int $id): Response
    {
        $row = $this->buildService->findForTenant($this->tenantId(), $id);
        if (!$row) {
            return $this->error(lang('messages.not_found'), 404);
        }
        $row = $this->withRuntimeHints($row);
        // 不下发 manifest_json 全文（体积可能较大）；前端要看可单独接口
        unset($row['manifest_json']);
        return $this->success('ok', $row);
    }

    #[Permission('mobile.build.create')]
    public function create(): Response
    {
        $platform = (string) $this->request->post('platform', '');
        if ($platform === '') {
            throw new BusinessException('platform 必填', 422);
        }
        $tenantId = $this->tenantId();
        // 取租户当前 plan_id 快照存到 build 行（经 Service/Repository，禁止 Controller 直查 Db）
        $planId = $this->buildService->planIdForTenant($tenantId);
        $buildId = $this->buildService->enqueue(
            $tenantId,
            $platform,
            $planId,
            operatorId: (int) ($this->request->userId ?? 0),
        );

        // v2.6.0：enqueue 内部已经 dispatch 到 mobile-builds 队列；
        // 接口立即返回 queued 行，前端轮询 GET /builds/:id 拿最终结果。
        $row = $this->buildService->findForTenant($tenantId, $buildId);
        if ($row) {
            $row = $this->withRuntimeHints($row);
        }
        return $this->success('queued', $row);
    }

    #[Permission('mobile.build.create')]
    public function requeue(int $id): Response
    {
        $row = $this->buildService->requeueForTenant($this->tenantId(), $id);
        return $this->success('queued', $this->withRuntimeHints($row));
    }

    #[Permission('mobile.build.create')]
    public function cancel(int $id): Response
    {
        $row = $this->buildService->cancelForTenant($this->tenantId(), $id);
        return $this->success('cancelled', $this->withRuntimeHints($row));
    }

    #[Permission('mobile.build.release')]
    public function release(int $id): Response
    {
        $row = $this->h5Release->release($this->tenantId(), $id);
        return $this->success('released', $this->withRuntimeHints($row));
    }

    #[Permission('mobile.build.release')]
    public function upload(int $id): Response
    {
        $row = $this->wechatUpload->upload($this->tenantId(), $id);
        return $this->success('uploaded', $row);
    }

    #[Permission('mobile.config.update')]
    public function saveWechatKey(): Response
    {
        $file = $this->request->file('key_file');
        if ($file === null) {
            // 兼容直接传文本（POST body 字段 key）
            $plaintext = (string) $this->request->post('key', '');
            if ($plaintext === '') {
                throw new BusinessException('请上传 .key 文件 或 提交 key 字段', 422);
            }
        } else {
            $plaintext = (string) file_get_contents($file->getPathname());
        }
        $this->keyService->save($this->tenantId(), $plaintext);
        return $this->success('saved', ['has_key' => true]);
    }

    #[Permission('mobile.config.update')]
    public function clearWechatKey(): Response
    {
        $this->keyService->save($this->tenantId(), '');
        return $this->success('cleared', ['has_key' => false]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function withRuntimeHints(array $row): array
    {
        $tenantId = (int) ($row['tenant_id'] ?? 0);
        $buildId  = (int) ($row['id'] ?? 0);
        $platform = (string) ($row['platform'] ?? '');
        $platformDir = match ($platform) {
            'h5' => 'h5',
            'mp-weixin' => 'mp-weixin',
            'app' => 'app',
            default => $platform,
        };

        $base = rtrim(App::getRootPath(), '/') . '/runtime/mobile-builds/' . $tenantId . '/' . $buildId;
        $row['work_dir'] = $base . '/uniapp';
        $row['artifact_hint'] = $base . '/uniapp/dist/build/' . $platformDir;

        if ($platform === 'h5') {
            $scheme = $this->request->header('x-forwarded-proto')
                ?: $this->request->scheme();
            $host = $this->request->host();
            $row['release_url'] = $scheme . '://' . $host . '/mobile/';
        }

        return $row;
    }
}
