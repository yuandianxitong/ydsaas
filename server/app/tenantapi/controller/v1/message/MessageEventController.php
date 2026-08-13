<?php
declare(strict_types=1);

namespace app\tenantapi\controller\v1\message;

use core\base\Controller;
use app\service\message\MessageEventService;
use core\attribute\Permission;
use think\Response;

class MessageEventController extends Controller
{
    protected MessageEventService $service;

    /**
     * 获取所有事件及其通道配置
     */
    #[Permission('message.event.list')]
    public function index(): Response
    {
        $result = $this->service->getEventList();
        return $this->success(lang('messages.get_success'), $result);
    }

    /**
     * 更新（或新建）某个事件通道的配置
     *
     * PUT /message/events/:eventCode/:channel
     */
    #[Permission('message.event.update')]
    public function update(): Response
    {
        try {
            $eventCode = (string) $this->request->param('eventCode', '');
            $channel   = (string) $this->request->param('channel', '');

            if (!$eventCode || !$channel) {
                return $this->error(lang('messages.param_missing'));
            }

            $data = $this->request->put();
            $result = $this->service->createOrUpdateConfig($eventCode, $channel, $data);
            return $this->success(lang('messages.update_success'), $result);
        } catch (\Exception $e) {
            return $this->error($e->getMessage());
        }
    }
}
