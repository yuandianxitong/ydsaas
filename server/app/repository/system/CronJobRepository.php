<?php
declare(strict_types=1);

namespace app\repository\system;

use core\base\Model;
use core\base\Repository;
use app\model\system\CronJob;
use app\model\system\CronJobLog;

class CronJobRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new CronJob();
    }

    /**
     * 获取任务列表
     */
    public function getListWithStats(array $where, int $page, int $limit): array
    {
        $query = $this->query()->where('deleted_at', null);

        if (!empty($where['keyword'])) {
            $query->where('name|command', 'like', '%' . $where['keyword'] . '%');
        }
        if (isset($where['status']) && $where['status'] !== '') {
            $query->where('status', (int) $where['status']);
        }

        $total = $query->count();
        $list = $query->order('sort asc, id desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => (int) ceil($total / $limit),
            ]
        ];
    }

    /**
     * 获取执行日志
     */
    public function getLogs(int $cronJobId, int $page, int $limit): array
    {
        // CronJobLog 是 cron_jobs 的子表，没有 tenant_id 列；
        // 通过 cron_job_id 隐式归属当前租户的 CronJob。
        $query = CronJobLog::where('cron_job_id', $cronJobId);

        $total = $query->count();
        $list = $query->order('created_at desc')
            ->page($page, $limit)
            ->select()
            ->toArray();

        return [
            'list' => $list,
            'pagination' => [
                'current_page' => $page,
                'per_page'     => $limit,
                'total'        => $total,
                'last_page'    => (int) ceil($total / $limit),
            ]
        ];
    }

    /**
     * 记录执行日志
     */
    public function createLog(array $data): void
    {
        CronJobLog::create($data);
    }

    /**
     * 清理日志（保留最近N天）
     */
    public function clearLogs(int $cronJobId, int $keepDays = 30): int
    {
        $date = date('Y-m-d H:i:s', strtotime("-{$keepDays} days"));
        return CronJobLog::where('cron_job_id', $cronJobId)
            ->where('created_at', '<', $date)
            ->delete();
    }

    /**
     * 获取所有启用的任务
     */
    public function getEnabledJobs(): array
    {
        return $this->query()->where('status', 1)
            ->where('deleted_at', null)
            ->order('sort asc')
            ->select()
            ->toArray();
    }
}
