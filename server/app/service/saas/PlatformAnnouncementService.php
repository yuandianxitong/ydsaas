<?php
declare(strict_types=1);

namespace app\service\saas;

use app\model\saas\PlatformAnnouncement;
use app\repository\saas\PlatformAnnouncementRepository;
use core\base\Service;
use core\exception\BusinessException;

class PlatformAnnouncementService extends Service
{
    protected PlatformAnnouncementRepository $platformAnnouncementRepository;

    /**
     * 获取公告列表（平台管理端）
     */
    public function getList(array $params): array
    {
        [$page, $limit] = $this->extractPagination($params);
        return $this->platformAnnouncementRepository->getSearchList($params, $page, $limit);
    }

    /**
     * 公告详情
     */
    public function detail(int $id): ?array
    {
        return $this->platformAnnouncementRepository->find($id);
    }

    /**
     * 创建公告
     */
    public function create(array $data): array
    {
        return $this->platformAnnouncementRepository->create($data);
    }

    /**
     * 更新公告
     */
    public function update(int $id, array $data): bool
    {
        $this->findOrFail($this->platformAnnouncementRepository, $id);
        return $this->platformAnnouncementRepository->update($id, $data);
    }

    /**
     * 删除公告
     */
    public function delete(int $id): bool
    {
        $this->findOrFail($this->platformAnnouncementRepository, $id);
        return $this->platformAnnouncementRepository->delete($id);
    }

    /**
     * 发布公告
     */
    public function publish(int $id): bool
    {
        $announcement = $this->findOrFail($this->platformAnnouncementRepository, $id);

        if ($announcement['status'] === PlatformAnnouncement::STATUS_PUBLISHED) {
            throw new BusinessException('公告已发布');
        }

        return $this->platformAnnouncementRepository->update($id, [
            'status'       => PlatformAnnouncement::STATUS_PUBLISHED,
            'published_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 获取已发布公告（租户端）
     */
    public function getPublishedForTenant(array $params): array
    {
        [$page, $limit] = $this->extractPagination($params);
        return $this->platformAnnouncementRepository->getPublishedList($page, $limit);
    }

    /**
     * 获取未读数量
     */
    public function getUnreadCount(int $tenantId, int $adminId): int
    {
        $totalPublished = $this->platformAnnouncementRepository->countPublished();
        $readCount = $this->platformAnnouncementRepository->countRead($tenantId, $adminId);

        return max(0, $totalPublished - $readCount);
    }

    /**
     * 标记已读
     */
    public function markAsRead(int $announcementId, int $tenantId, int $adminId): bool
    {
        // 确认公告存在且已发布
        $announcement = $this->platformAnnouncementRepository->find($announcementId);
        if (!$announcement || $announcement['status'] !== PlatformAnnouncement::STATUS_PUBLISHED) {
            return false;
        }

        try {
            $this->platformAnnouncementRepository->markRead($announcementId, $tenantId, $adminId);
        } catch (\Exception) {
            // duplicate key — already read, ignore
        }

        return true;
    }
}
