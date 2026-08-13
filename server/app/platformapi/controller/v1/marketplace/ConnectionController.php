<?php

declare(strict_types=1);

namespace app\platformapi\controller\v1\marketplace;

use app\repository\marketplace\MarketplaceConnectionRepository;
use app\service\marketplace\InstanceRegistrationService;
use app\service\marketplace\MarketplaceInstallIntentService;
use app\service\marketplace\MarketplaceTokenRotationService;
use core\attribute\Permission;
use core\base\Controller;
use core\exception\BusinessException;
use think\Response;

class ConnectionController extends Controller
{
    protected InstanceRegistrationService $svc;
    protected MarketplaceConnectionRepository $repo;
    protected MarketplaceTokenRotationService $tokenRotation;
    protected MarketplaceInstallIntentService $intentService;

    /**
     * OAuth 兑换。建连后若 state 缓存里带安装意图，则自动同步权益并 resolve，
     * 把 install_resolution 一并返回，前端据此直接进入最终确认 / 购买提示 / 不兼容提示。
     */
    #[Permission('marketplace.connection.manage')]
    public function exchange(): Response
    {
        $state = (string) $this->request->post('state', '');
        $code  = (string) $this->request->post('code',  '');

        $res = $this->svc->exchange($state, $code);
        $intent = $res['_intent'] ?? null;
        unset($res['_intent']);

        if (is_array($intent) && ($intent['intent'] ?? '') === 'install') {
            $res['install_resolution'] = $this->intentService->resolveAfterConnect(
                (int) $res['id'],
                (string) ($intent['app_code'] ?? '')
            );
        } elseif (is_array($intent) && ($intent['intent'] ?? '') === 'connect') {
            $res['install_resolution'] = ['status' => 'connected'];
        }

        return $this->success('ok', $res);
    }

    #[Permission('marketplace.connection.view')]
    public function index(): Response
    {
        $rows = $this->repo->listNotDeleted();
        $shaped = array_map(fn ($r) => $this->shapeForPublic($r), $rows);
        return $this->success('ok', ['data' => $shaped]);
    }

    #[Permission('marketplace.connection.view')]
    public function show($id): Response
    {
        $row = $this->repo->find((int) $id);
        if (!$row) {
            throw new BusinessException('连接不存在', 404);
        }
        return $this->success('ok', $this->shapeForPublic($row));
    }

    #[Permission('marketplace.connection.rotate_token')]
    public function rotateToken($id): Response
    {
        $cid = (int) $id;
        $row = $this->repo->find($cid);
        if (!$row) {
            throw new BusinessException('连接不存在', 404);
        }
        $ok = $this->tokenRotation->rotateOne($cid, $row);
        if (!$ok) {
            $fresh = $this->repo->find($cid);
            throw new BusinessException(
                'token 轮换失败: ' . ($fresh['last_error'] ?? 'unknown'),
                502
            );
        }
        return $this->success('ok', ['connection_id' => $cid]);
    }

    #[Permission('marketplace.connection.manage')]
    public function revoke($id): Response
    {
        $row = $this->repo->find((int) $id);
        if (!$row) {
            throw new BusinessException('连接不存在', 404);
        }
        $this->repo->update((int) $id, [
            'status'     => 'revoked',
            'deleted_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        return $this->success('ok', ['revoked' => true]);
    }

    private function shapeForPublic(array $r): array
    {
        unset($r['encrypted_instance_token']);

        // v2.9.0: token 轮换状态派生字段 (供前端 banner 与 token 提醒)
        $threshold = (int) config('saas.marketplace.token_rotate_threshold_days', 90);
        $warn      = (int) config('saas.marketplace.token_rotate_warn_days', 7);
        $rotatedTs = isset($r['token_rotated_at']) ? strtotime((string) $r['token_rotated_at']) : 0;
        $ageDays   = $rotatedTs > 0 ? (int) floor((time() - $rotatedTs) / 86400) : null;

        $r['token_age_days']       = $ageDays;
        $r['token_rotate_warn']    = $ageDays !== null && $ageDays >= max(0, $threshold - $warn);
        $r['token_rotate_overdue'] = $ageDays !== null
            && $ageDays >= $threshold
            && str_contains((string) ($r['last_error'] ?? ''), 'token-rotate');

        return $r;
    }
}
