<?php
declare(strict_types=1);

namespace app\repository\saas;

use app\model\saas\PlatformAnnouncement;
use core\base\Repository;
use think\Model;

class PlatformAnnouncementRepository extends Repository
{
    protected bool $tenantScoped = false;

    protected function getModel(): Model
    {
        return new PlatformAnnouncement();
    }

    /**
     * 获取模型实例（用于更新操作）
     */
    public function findModel(int $id): ?PlatformAnnouncement
    {
        /** @var PlatformAnnouncement|null $row */
        $row = $this->query()->where('id', $id)->find();
        return $row;
    }

    /**
     * 搜索公告列表（平台管理端）
     */
    public function getSearchList(array $params, int $page = 1, int $limit = 20): array
    {
        $where = [];

        if (!empty($params['keyword'])) {
            $where[] = ['title', 'like', "%{$params['keyword']}%"];
        }
        if (isset($params['type']) && $params['type'] !== '') {
            $where[] = ['type', '=', $params['type']];
        }
        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = ['status', '=', $params['status']];
        }

        return $this->getList($where, $page, $limit, 'id desc');
    }

    /**
     * 获取已发布公告列表（租户端只读）
     */
    public function getPublishedList(int $page = 1, int $limit = 20): array
    {
        $where = [
            ['status', '=', PlatformAnnouncement::STATUS_PUBLISHED],
        ];
        return $this->getList($where, $page, $limit, 'published_at desc, id desc');
    }

    /**
     * 获取已发布公告总数
     */
    public function countPublished(): int
    {
        return $this->getModel()->db()
            ->where('status', PlatformAnnouncement::STATUS_PUBLISHED)
            ->count();
    }

    /**
     * 获取已读数量
     */
    public function countRead(int $tenantId, int $adminId): int
    {
        return \think\facade\Db::table('platform_announcement_reads')
            ->where('tenant_id', $tenantId)
            ->where('admin_id', $adminId)
            ->count();
    }

    /**
     * 标记公告已读（upsert）
     */
    public function markRead(int $announcementId, int $tenantId, int $adminId): void
    {
        \think\facade\Db::table('platform_announcement_reads')->insertOrUpdate([
            'announcement_id' => $announcementId,
            'tenant_id'       => $tenantId,
            'admin_id'        => $adminId,
            'read_at'         => date('Y-m-d H:i:s'),
        ], ['read_at' => date('Y-m-d H:i:s')]);
    }
}
