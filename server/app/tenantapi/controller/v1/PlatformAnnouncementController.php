<?php
declare(strict_types=1);

namespace app\tenantapi\controller\v1;

use core\base\Controller;
use core\attribute\PermissionSkip;
use app\service\saas\PlatformAnnouncementService;
use core\tenant\TenantContext;
use think\Request;
use think\Response;

class PlatformAnnouncementController extends Controller
{
    protected PlatformAnnouncementService $platformAnnouncementService;

    #[PermissionSkip]
    public function index(Request $request): Response
    {
        return $this->success('', $this->platformAnnouncementService->getPublishedForTenant($request->param()));
    }

    #[PermissionSkip]
    public function show(Request $request, int $id): Response
    {
        $announcement = $this->platformAnnouncementService->detail($id);
        if (!$announcement) {
            return $this->error('公告不存在');
        }

        // auto mark as read
        $tenantId = TenantContext::current()?->id() ?? 0;
        $adminId  = (int) $request->userId;
        if ($tenantId && $adminId) {
            $this->platformAnnouncementService->markAsRead($id, $tenantId, $adminId);
        }

        return $this->success('', $announcement);
    }

    #[PermissionSkip]
    public function unreadCount(Request $request): Response
    {
        $tenantId = TenantContext::current()?->id() ?? 0;
        $adminId  = (int) $request->userId;
        $count    = $this->platformAnnouncementService->getUnreadCount($tenantId, $adminId);
        return $this->success('', ['count' => $count]);
    }

    #[PermissionSkip]
    public function markAsRead(Request $request, int $id): Response
    {
        $tenantId = TenantContext::current()?->id() ?? 0;
        $adminId  = (int) $request->userId;
        $this->platformAnnouncementService->markAsRead($id, $tenantId, $adminId);
        return $this->success();
    }
}
