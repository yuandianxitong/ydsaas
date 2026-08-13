<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\repository\plugin;

use app\model\plugin\PluginBuild;
use core\base\Repository;
use think\Model;

class PluginBuildRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new PluginBuild();
    }

    /**
     * 获取最近一次成功构建（用于回滚定位上一份产物）
     */
    public function findLastSuccessful(string $target): ?array
    {
        $row = $this->getModel()->db()
            ->where('target', $target)
            ->where('status', PluginBuild::STATUS_SUCCESS)
            ->order('id desc')
            ->find();
        return $row ? (is_array($row) ? $row : $row->toArray()) : null;
    }

    /**
     * 分页列表（按 id desc）
     *
     * @return array{list: array<int, array<string, mixed>>, total: int}
     */
    public function listPaginated(int $page, int $limit): array
    {
        $query = $this->getModel()->db();
        $total = (int) $query->count();
        $list = $this->getModel()->db()->order('id desc')->page($page, $limit)->select()->toArray();
        return ['list' => $list, 'total' => $total];
    }

    public function markRunning(int $id): bool
    {
        return $this->update($id, [
            'status'     => \app\model\plugin\PluginBuild::STATUS_RUNNING,
            'started_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markSuccess(int $id, string $log, string $artifactPath): bool
    {
        return $this->update($id, [
            'status'        => \app\model\plugin\PluginBuild::STATUS_SUCCESS,
            'log'           => $log,
            'artifact_path' => $artifactPath,
            'finished_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    public function markFailed(int $id, string $log): bool
    {
        return $this->update($id, [
            'status'      => \app\model\plugin\PluginBuild::STATUS_FAILED,
            'log'         => $log,
            'finished_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
