<?php
declare(strict_types=1);

namespace app\platformapi\controller\v1;

use core\base\Controller;
use core\attribute\Permission;
use app\service\saas\PlatformLogService;
use think\Response;

class AuditController extends Controller
{
    protected PlatformLogService $platformLogService;

    #[Permission('platform.audit.list')]
    public function logs(): Response
    {
        $data = $this->platformLogService->getAuditLogs($this->request->param());
        return $this->success('', $data);
    }
}
