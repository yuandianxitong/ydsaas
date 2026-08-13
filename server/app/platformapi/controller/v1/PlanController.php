<?php
/* ============================================================
 * 项目：元点Admin SaaS
 * 官网：https://www.dev007.cn
 * ============================================================ */
declare(strict_types=1);

namespace app\platformapi\controller\v1;

use app\platformapi\validate\v1\PlanValidate;
use app\service\saas\PlanService;
use core\attribute\Permission;
use core\base\Controller;

class PlanController extends Controller
{
    protected PlanService $planService;

    #[Permission('plan.view')]
    public function index()
    {
        $result = $this->planService->paginate($this->request->get());
        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('plan.view')]
    public function show($id)
    {
        return $this->success(lang('messages.get_success'), $this->planService->show((int) $id));
    }

    #[Permission('platform.plan.create')]
    public function store()
    {
        $data = $this->request->post();
        $this->validate($data, PlanValidate::class, [], 'create');
        $plan = $this->planService->create($data);
        return $this->success(lang('messages.create_success'), $plan);
    }

    #[Permission('platform.plan.update')]
    public function update($id)
    {
        $data = $this->request->post();
        $this->validate($data, PlanValidate::class, [], 'update');
        $this->planService->update((int) $id, $data);
        return $this->success(lang('messages.update_success'));
    }

    #[Permission('platform.plan.delete')]
    public function destroy($id)
    {
        $this->planService->destroy((int) $id);
        return $this->success(lang('messages.delete_success'));
    }

    #[Permission('plan.view')]
    public function options()
    {
        return $this->success(lang('messages.get_success'), $this->planService->listActive());
    }
}
