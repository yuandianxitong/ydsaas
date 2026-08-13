<?php
declare(strict_types=1);

namespace app\platformapi\controller\v1\system;

use core\base\Controller;
use app\service\system\CronJobService;
use app\tenantapi\validate\v1\system\CronJobValidate;
use core\attribute\Permission;

class CronJobController extends Controller
{
    protected CronJobService $service;

    #[Permission('platform.cron_job.list')]
    public function index()
    {
        $params = $this->getRequestData([
            'keyword' => '',
            'status'  => '',
            'page'    => 1,
            'limit'   => 20,
        ]);
        $result = $this->service->getCronJobList($params);
        return $this->paginate($result);
    }

    #[Permission('platform.cron_job.list')]
    public function show()
    {
        $id = (int) $this->request->param('id');
        $result = $this->service->getCronJobDetail($id);
        if (!$result) {
            return $this->error(lang('business.task_not_found'));
        }
        return $this->success(lang('messages.get_success'), $result);
    }

    #[Permission('platform.cron_job.create')]
    public function store()
    {
        $data = $this->request->post();
        $this->validate($data, CronJobValidate::class, [], 'create');
        $data['created_by'] = $this->getUserId();
        $result = $this->service->createCronJob($data);
        return $this->success(lang('messages.create_success'), $result);
    }

    #[Permission('platform.cron_job.update')]
    public function update()
    {
        $id = (int) $this->request->param('id');
        $data = $this->request->post();
        $this->validate($data, CronJobValidate::class, [], 'update');
        $this->service->updateCronJob($id, $data);
        return $this->success(lang('messages.update_success'));
    }

    #[Permission('platform.cron_job.delete')]
    public function delete()
    {
        $id = (int) $this->request->param('id');
        $this->service->deleteCronJob($id);
        return $this->success(lang('messages.delete_success'));
    }

    #[Permission('platform.cron_job.update')]
    public function status()
    {
        $id = (int) $this->request->param('id');
        $status = (int) $this->request->post('status');
        $this->service->updateStatus($id, $status);
        return $this->success(lang('messages.update_success'));
    }

    #[Permission('platform.cron_job.run')]
    public function run()
    {
        $id = (int) $this->request->param('id');
        $result = $this->service->runCronJob($id);
        return $this->success(lang('messages.execute_complete'), $result);
    }

    #[Permission('platform.cron_job.list')]
    public function logs()
    {
        $id = (int) $this->request->param('id');
        $params = $this->getRequestData([
            'page'  => 1,
            'limit' => 20,
        ]);
        $result = $this->service->getCronJobLogs($id, $params);
        return $this->paginate($result);
    }

    #[Permission('platform.cron_job.clear')]
    public function clearLogs()
    {
        $id = (int) $this->request->param('id');
        $keepDays = (int) ($this->request->post('keep_days') ?: 30);
        $count = $this->service->clearLogs($id, $keepDays);
        return $this->success(sprintf(lang('messages.log_clean_count'), $count));
    }
}
