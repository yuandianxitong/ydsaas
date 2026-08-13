<?php
declare(strict_types=1);

namespace app\repository\message;

use app\model\message\MessageLog;
use core\base\Repository;
use think\Model;

class MessageLogRepository extends Repository
{
    protected function getModel(): Model
    {
        return new MessageLog();
    }

    /**
     * 获取发送记录列表（支持搜索）
     */
    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $query = $this->query()->order('id', 'desc');

        if (!empty($params['channel'])) {
            $query->where('channel', $params['channel']);
        }
        if (!empty($params['template_code'])) {
            $query->where('template_code', $params['template_code']);
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $query->where('status', (int) $params['status']);
        }
        if (!empty($params['receiver'])) {
            $query->where('receiver', 'like', '%' . $params['receiver'] . '%');
        }

        $total = $query->count();
        $list = $query->page($page, $limit)->select()->toArray();

        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page' => $limit,
                'total' => $total,
                'last_page' => (int) ceil($total / $limit),
            ],
        ];
    }

    /**
     * 创建发送日志
     *
     * 复用基类 create() 自动注入 tenant_id。
     */
    public function createLog(array $data): array
    {
        return $this->create($data);
    }

    /**
     * 更新发送日志结果
     */
    public function updateLogResult(int|string $id, array $data): bool
    {
        return $this->query()->where('id', $id)->update($data) !== false;
    }
}
