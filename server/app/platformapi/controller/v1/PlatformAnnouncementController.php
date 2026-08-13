<?php
declare(strict_types=1);

namespace app\platformapi\controller\v1;

use core\base\Controller;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use app\service\saas\PlatformAnnouncementService;
use think\Request;
use think\Response;

class PlatformAnnouncementController extends Controller
{
    protected PlatformAnnouncementService $platformAnnouncementService;

    #[Permission('platform.announcement.list')]
    public function index(Request $request): Response
    {
        return $this->success('', $this->platformAnnouncementService->getList($request->param()));
    }

    #[Permission('platform.announcement.list')]
    public function show(int $id): Response
    {
        $announcement = $this->platformAnnouncementService->detail($id);
        return $announcement ? $this->success('', $announcement) : $this->error(lang('messages.announcement_not_found'));
    }

    #[Permission('platform.announcement.create')]
    public function store(Request $request): Response
    {
        $data = $request->param();
        $data['created_by'] = $request->platformAdminId;
        return $this->success('', $this->platformAnnouncementService->create($data));
    }

    #[Permission('platform.announcement.update')]
    public function update(Request $request, int $id): Response
    {
        $this->platformAnnouncementService->update($id, $request->param());
        return $this->success();
    }

    #[Permission('platform.announcement.delete')]
    public function delete(int $id): Response
    {
        $this->platformAnnouncementService->delete($id);
        return $this->success();
    }

    #[Permission('platform.announcement.update')]
    public function publish(int $id): Response
    {
        $this->platformAnnouncementService->publish($id);
        return $this->success();
    }
}
