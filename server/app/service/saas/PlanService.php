<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\service\saas;

use core\base\Service;
use core\exception\BusinessException;
use app\repository\saas\PlanRepository;

class PlanService extends Service
{
    protected PlanRepository $planRepository;

    public function paginate(array $params): array
    {
        $page = (int) ($params['page'] ?? 1);
        $size = (int) ($params['size'] ?? 20);
        $where = [];
        if (!empty($params['keyword'])) {
            $where[] = ['name|code', 'like', "%{$params['keyword']}%"];
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', (int) $params['status']];
        }
        return $this->planRepository->paginate($where, $page, $size);
    }

    public function show(int $id): array
    {
        $row = $this->planRepository->find($id);
        if (!$row) {
            throw new BusinessException('套餐不存在');
        }
        return $row;
    }

    public function create(array $data): array
    {
        if ($this->planRepository->findByCode((string) ($data['code'] ?? ''))) {
            throw new BusinessException('code 已被占用');
        }
        $data['status'] = $data['status'] ?? 1;
        $data['sort']   = $data['sort']   ?? 0;
        // v2.3.x 后 features 已迁移到 plugin_grants/entitlement。
        // 防御性：丢弃任何上游传过来的 features，避免在 v2.3.1 已 DROP 列的环境写库报错。
        unset($data['features']);
        return $this->planRepository->create($data);
    }

    public function update(int $id, array $data): bool
    {
        $row = $this->planRepository->find($id);
        if (!$row) {
            throw new BusinessException('套餐不存在');
        }
        // code 不允许修改
        unset($data['code']);
        // v2.3.x 后 features 已迁移到 plugin_grants/entitlement。
        // 防御性：丢弃任何上游传过来的 features，避免在 v2.3.1 已 DROP 列的环境写库报错。
        unset($data['features']);
        return $this->planRepository->update($id, $data);
    }

    public function destroy(int $id): bool
    {
        $row = $this->planRepository->find($id);
        if (!$row) {
            throw new BusinessException('套餐不存在');
        }
        return $this->planRepository->delete($id);
    }

    public function listActive(): array
    {
        return $this->planRepository->listActive();
    }
}
