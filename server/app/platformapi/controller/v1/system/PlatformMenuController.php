<?php
declare(strict_types=1);

namespace app\platformapi\controller\v1\system;

use core\base\Controller;
use core\attribute\Permission;
use core\attribute\PermissionSkip;
use app\service\saas\PlatformMenuService;
use think\Request;
use think\Response;

class PlatformMenuController extends Controller
{
    protected PlatformMenuService $platformMenuService;

    #[Permission('platform.menu.list')]
    public function index(): Response
    {
        return $this->success('', $this->platformMenuService->getMenuTree(false));
    }

    #[PermissionSkip]
    public function options(Request $request): Response
    {
        $excludeId = (int) $request->param('exclude_id', 0);
        return $this->success('', $this->platformMenuService->getMenuOptions($excludeId));
    }

    #[Permission('platform.menu.create')]
    public function store(Request $request): Response
    {
        return $this->success('', $this->platformMenuService->createMenu($request->param()));
    }

    #[Permission('platform.menu.update')]
    public function update(Request $request, int $id): Response
    {
        $this->platformMenuService->updateMenu($id, $request->param());
        return $this->success();
    }

    #[Permission('platform.menu.delete')]
    public function delete(int $id): Response
    {
        $this->platformMenuService->deleteMenu($id);
        return $this->success();
    }

    #[Permission('platform.menu.update')]
    public function batchSort(Request $request): Response
    {
        $this->platformMenuService->batchSort($request->param('items', []));
        return $this->success();
    }

    #[Permission('platform.menu.update')]
    public function updateStatus(Request $request, int $id): Response
    {
        $this->platformMenuService->updateStatus($id, (int) $request->param('status'));
        return $this->success();
    }
}
