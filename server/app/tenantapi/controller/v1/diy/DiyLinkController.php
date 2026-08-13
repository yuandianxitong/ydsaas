<?php

/* ============================================================
 * 项目：元点Admin SaaS
 * ============================================================ */

declare(strict_types=1);

namespace app\tenantapi\controller\v1\diy;

use app\service\diy\DiyLinkService;
use app\validate\diy\DiyLinkValidate;
use core\attribute\Permission;
use core\base\Controller;
use think\Response;

/** 租户后台：装修链接库 CRUD。 */
class DiyLinkController extends Controller
{
    protected DiyLinkService $diyLinkService;

    #[Permission('diy.link.list')]
    public function index(): Response
    {
        return $this->success('ok', $this->diyLinkService->list());
    }

    #[Permission('diy.link.create')]
    public function save(): Response
    {
        $data = $this->request->post();
        $this->validate($data, DiyLinkValidate::class, [], 'create');
        $id = $this->diyLinkService->create($data);

        return $this->success('创建成功', ['id' => $id]);
    }

    #[Permission('diy.link.update')]
    public function update(): Response
    {
        $id   = (int) $this->request->param('id');
        $data = $this->request->put();
        $this->validate($data, DiyLinkValidate::class, [], 'update');
        $this->diyLinkService->update($id, $data);

        return $this->success('保存成功');
    }

    #[Permission('diy.link.delete')]
    public function delete(): Response
    {
        $this->diyLinkService->delete((int) $this->request->param('id'));

        return $this->success('已删除');
    }
}
