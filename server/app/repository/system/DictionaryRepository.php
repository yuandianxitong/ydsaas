<?php
declare(strict_types=1);

namespace app\repository\system;

use app\model\system\Dictionary;
use core\base\Repository;
use core\cache\CacheableRepository;
use think\Model;

class DictionaryRepository extends Repository
{
    use CacheableRepository;

    protected string $cacheTag = 'dictionary';
    protected int $cacheTTL = 7200;

    protected function getModel(): Model
    {
        return new Dictionary();
    }

    /**
     * 获取字典列表（包含字典项数量）
     */
    public function getListWithItemCount(array $where = [], int $page = 1, int $limit = 15): array
    {
        $query = $this->query()->withCount(['items'])->where($where);

        $total = $query->count();
        $list = $query->page($page, $limit)
            ->order('sort asc, created_at desc')
            ->select()
            ->toArray();

        return [
            'list'       => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => (int)ceil($total / $limit),
            ],
        ];
    }

    /**
     * 获取字典详情（包含字典项）
     */
    public function getDetailWithItems(int $id): ?array
    {
        $result = $this->query()->with(['items'])->where('id', $id)->find();
        if (!$result) {
            return null;
        }
        return is_array($result) ? $result : $result->toArray();
    }

    /**
     * 根据 code 获取字典（包含启用的字典项）
     */
    public function getByCode(string $code): ?array
    {
        return $this->cacheRemember("dict:{$code}", function () use ($code) {
            $result = $this->query()
                ->where('code', $code)
                ->where('status', 1)
                ->with(['items' => function ($query) {
                    $query->where('status', 1)->order('sort asc, id asc');
                }])
                ->find();
            if (!$result) {
                return null;
            }
            return is_array($result) ? $result : $result->toArray();
        });
    }

    /**
     * 检查 code 是否已存在
     */
    public function existsCode(string $code, int $excludeId = 0): bool
    {
        $query = $this->query()->where('code', $code);
        if ($excludeId > 0) {
            $query->where('id', '<>', $excludeId);
        }
        return $query->count() > 0;
    }

    /**
     * 获取字典列表，合并平台字典（tenant_id=0）
     * 用于 tenant 端展示：自有字典 + 平台系统字典
     */
    public function getListWithPlatform(int $tenantId, array $where = [], int $page = 1, int $limit = 15): array
    {
        $model = $this->getModel();
        $query = $model->db()
            ->whereIn('tenant_id', [0, $tenantId])
            ->withCount(['items'])
            ->where($where);

        $total = $query->count();
        $list = $query->page($page, $limit)
            ->order('tenant_id asc, sort asc, created_at desc')
            ->select()
            ->toArray();

        // 标记系统字典
        foreach ($list as &$item) {
            $item['is_system'] = ($item['tenant_id'] ?? 0) === 0;
        }

        return $this->buildPagination($list, $page, $limit, $total);
    }

    /**
     * 根据 code 获取字典，优先租户自有，否则回退平台字典
     */
    public function getByCodeWithPlatform(string $code, int $tenantId): ?array
    {
        return $this->cacheRemember("dict:{$tenantId}:{$code}", function () use ($code, $tenantId) {
            $model = $this->getModel();

            // 先查租户自有
            $result = $model->db()
                ->where('code', $code)
                ->where('tenant_id', $tenantId)
                ->where('status', 1)
                ->with(['items' => function ($query) {
                    $query->where('status', 1)->order('sort asc, id asc');
                }])
                ->find();

            // 回退到平台字典
            if (!$result) {
                $result = $model->db()
                    ->where('code', $code)
                    ->where('tenant_id', 0)
                    ->where('status', 1)
                    ->with(['items' => function ($query) {
                        $query->where('status', 1)->order('sort asc, id asc');
                    }])
                    ->find();
            }

            if (!$result) {
                return null;
            }
            return is_array($result) ? $result : $result->toArray();
        });
    }
}
