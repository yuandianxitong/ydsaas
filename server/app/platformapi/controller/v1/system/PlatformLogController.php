<?php
declare(strict_types=1);

namespace app\platformapi\controller\v1\system;

use core\base\Controller;
use core\attribute\Permission;
use app\service\saas\PlatformLogService;
use think\Response;

class PlatformLogController extends Controller
{
    protected PlatformLogService $platformLogService;

    #[Permission('platform.log.login')]
    public function loginLogs(): Response
    {
        $data = $this->platformLogService->getLoginLogs($this->request->param());
        return $this->success('', $data);
    }

    #[Permission('platform.log.operation')]
    public function operationLogs(): Response
    {
        $data = $this->platformLogService->getOperationLogs($this->request->param());
        return $this->success('', $data);
    }
}
