<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\repository\saas;

use app\model\saas\TenantMobileBuild;
use core\base\Repository;
use think\Model;

class TenantMobileBuildRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new TenantMobileBuild();
    }

    public function findByTenantAndId(int $tenantId, int $id): ?array
    {
        $row = $this->query()->where('id', $id)->where('tenant_id', $tenantId)->find();
        return $row ? (is_array($row) ? $row : $row->toArray()) : null;
    }

    /**
     * 生成今日内该租户下一个 build_no（格式 yyyymmdd-NNN）。
     */
    public function nextBuildNo(int $tenantId): string
    {
        $today = date('Ymd');
        $count = $this->query()
            ->where('tenant_id', $tenantId)
            ->where('build_no', 'like', "{$today}-%")
            ->count();
        return sprintf('%s-%03d', $today, $count + 1);
    }

    public function insertRow(array $data): int
    {
        return (int) $this->query()->insertGetId($data);
    }

    /**
     * CAS：仅 queued → running，防止取消后被 worker 重新拉起。
     *
     * @return bool 是否更新成功
     */
    public function markRunning(int $id): bool
    {
        $now = date('Y-m-d H:i:s');
        $affected = $this->query()
            ->where('id', $id)
            ->where('status', TenantMobileBuild::STATUS_QUEUED)
            ->update([
                'status'     => TenantMobileBuild::STATUS_RUNNING,
                'started_at' => $now,
                'updated_at' => $now,
            ]);
        return $affected > 0;
    }

    /**
     * CAS：仅 running → success，防止取消后晚到的 worker 覆盖为成功。
     *
     * @return bool 是否更新成功
     */
    public function markSuccess(int $id, array $patch): bool
    {
        $now = date('Y-m-d H:i:s');
        $affected = $this->query()
            ->where('id', $id)
            ->where('status', TenantMobileBuild::STATUS_RUNNING)
            ->update(array_merge($patch, [
                'status'      => TenantMobileBuild::STATUS_SUCCESS,
                'finished_at' => $now,
                'updated_at'  => $now,
            ]));
        return $affected > 0;
    }

    /**
     * 标记失败。默认无状态门控（入队失败等）；传入 $onlyStatuses 时做 CAS。
     *
     * @param array<string, mixed> $meta 额外落库列（driver/remote_job_id/runtime_json 等）
     * @param list<int>|null $onlyStatuses 仅当当前 status 命中时才更新
     *
     * @return bool 是否更新成功
     */
    public function markFailed(int $id, string $errorLog, array $meta = [], ?array $onlyStatuses = null): bool
    {
        $now = date('Y-m-d H:i:s');
        $q = $this->query()->where('id', $id);
        if ($onlyStatuses !== null) {
            $q = $q->whereIn('status', $onlyStatuses);
        }
        $affected = $q->update(array_merge($meta, [
            'status'      => TenantMobileBuild::STATUS_FAILED,
            'error_log'   => mb_substr($errorLog, 0, 51200),
            'finished_at' => $now,
            'updated_at'  => $now,
        ]));
        return $affected > 0;
    }

    public function markStatus(int $id, int $status, array $patch = []): void
    {
        $this->query()->where('id', $id)->update(array_merge($patch, [
            'status'     => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]));
    }

    public function touchQueued(int $id): void
    {
        $this->query()
            ->where('id', $id)
            ->where('status', TenantMobileBuild::STATUS_QUEUED)
            ->update([
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
    }

    /**
     * 平台监控 / cron 用：返回未做 tenant 限制的查询构造器。
     */
    public function queryGlobal(): \think\db\BaseQuery
    {
        return $this->query();
    }

    /**
     * @return array{items: array<int, array<string, mixed>>, total: int}
     */
    public function paginate(int $tenantId, int $page, int $limit, ?string $platform = null): array
    {
        $q = $this->query()->where('tenant_id', $tenantId);
        if ($platform !== null) {
            $q = $q->where('platform', $platform);
        }
        $total = (clone $q)->count();
        $items = $q->order('id', 'desc')
            ->page($page, $limit)
            ->select()
            ->toArray();
        return ['items' => $items, 'total' => (int) $total];
    }

    public function listStuckRunning(int $thresholdSeconds): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - $thresholdSeconds);
        return $this->query()
            ->where('status', TenantMobileBuild::STATUS_RUNNING)
            ->where('started_at', '<', $cutoff)
            ->select()
            ->toArray();
    }

    /**
     * 按平台 prune 旧产物：保留最近 N 条（success / uploaded），返回多出来的供调用方
     * 删磁盘 + DB 行。
     *
     * STATUS_RELEASED **不**纳入候选集 —— 它对应的 artifact_path 是 nginx 当前正在
     * 服务的 live H5 静态资源；prune 删它会导致用户首屏白屏。
     *
     * @return array<int, array<string, mixed>> 待清理的行
     */
    public function pruneCandidates(int $tenantId, string $platform, int $keepN): array
    {
        $rows = $this->query()
            ->where('tenant_id', $tenantId)
            ->where('platform', $platform)
            ->whereIn('status', [
                TenantMobileBuild::STATUS_SUCCESS,
                TenantMobileBuild::STATUS_UPLOADED,
            ])
            ->order('id', 'desc')
            ->select()
            ->toArray();
        return array_slice($rows, $keepN);
    }

    /**
     * 最新一条构建（可按状态过滤）。
     *
     * @param list<int>|null $statuses
     * @return array<string, mixed>|null
     */
    public function findLatestByPlatform(int $tenantId, string $platform, ?array $statuses = null): ?array
    {
        $q = $this->query()
            ->where('tenant_id', $tenantId)
            ->where('platform', $platform);
        if ($statuses !== null) {
            $q = $q->whereIn('status', $statuses);
        }
        $row = $q->order('id', 'desc')->find();

        return $row ? (is_array($row) ? $row : $row->toArray()) : null;
    }

    /**
     * @param list<int> $statuses
     */
    public function hasByPlatformStatuses(int $tenantId, string $platform, array $statuses): bool
    {
        if ($statuses === []) {
            return false;
        }

        return $this->query()
            ->where('tenant_id', $tenantId)
            ->where('platform', $platform)
            ->whereIn('status', $statuses)
            ->count() > 0;
    }
}
