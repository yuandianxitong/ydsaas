<?php
declare(strict_types=1);

namespace app\service\message;

use app\model\message\MessageEvent;
use app\repository\message\MessageEventRepository;
use app\repository\message\MessageTemplateRepository;
use core\base\Service;
use core\tenant\TenantContext;

class MessageEventService extends Service
{
    protected MessageEventRepository $eventRepository;
    protected MessageTemplateRepository $templateRepository;

    /**
     * 获取所有支持的事件列表，并合并当前租户的配置
     *
     * 返回结构：
     * [
     *   [
     *     'event_code' => 'order_paid',
     *     'label'      => '支付成功',
     *     'variables'  => ['order_no', 'amount', ...],
     *     'channels'   => [
     *       'sms'           => ['template_id' => 0, 'is_enabled' => 0, 'variable_mapping' => []],
     *       'wechat_oa'     => [...],
     *       'wechat_miniapp' => [...],
     *     ],
     *   ],
     *   ...
     * ]
     */
    public function getEventList(): array
    {
        $tenantId = TenantContext::current()?->id() ?? 0;
        $supported = MessageEvent::supportedEvents();

        // 加载当前租户已有的配置，索引为 event_code:channel
        $existingConfigs = [];
        if ($tenantId > 0) {
            $rows = $this->eventRepository->getByTenant($tenantId);
            foreach ($rows as $row) {
                $existingConfigs[$row['event_code'] . ':' . $row['channel']] = $row;
            }
        }

        $channels = ['sms', 'wechat_oa', 'wechat_miniapp'];
        $result = [];

        foreach ($supported as $eventCode => $meta) {
            $channelConfigs = [];
            foreach ($channels as $channel) {
                $key = $eventCode . ':' . $channel;
                if (isset($existingConfigs[$key])) {
                    $cfg = $existingConfigs[$key];
                    $channelConfigs[$channel] = [
                        'id'               => $cfg['id'],
                        'template_id'      => (int) $cfg['template_id'],
                        'is_enabled'       => (int) $cfg['is_enabled'],
                        'variable_mapping' => $cfg['variable_mapping'] ?? [],
                    ];
                } else {
                    $channelConfigs[$channel] = [
                        'id'               => null,
                        'template_id'      => 0,
                        'is_enabled'       => 0,
                        'variable_mapping' => [],
                    ];
                }
            }

            $result[] = [
                'event_code' => $eventCode,
                'label'      => $meta['label'],
                'variables'  => $meta['variables'],
                'channels'   => $channelConfigs,
            ];
        }

        return $result;
    }

    /**
     * 新增或更新某个事件通道的配置
     *
     * @param string $eventCode 事件码，如 'order_paid'
     * @param string $channel   通道，如 'sms'/'wechat_oa'/'wechat_miniapp'
     * @param array  $data      包含 template_id / is_enabled / variable_mapping
     */
    public function createOrUpdateConfig(string $eventCode, string $channel, array $data): array
    {
        $tenantId = TenantContext::current()?->id() ?? 0;

        $existing = $this->eventRepository->findWhere([
            'tenant_id'  => $tenantId,
            'event_code' => $eventCode,
            'channel'    => $channel,
        ]);

        $payload = array_filter([
            'template_id'      => isset($data['template_id']) ? (int) $data['template_id'] : null,
            'is_enabled'       => isset($data['is_enabled']) ? (int) $data['is_enabled'] : null,
            'variable_mapping' => $data['variable_mapping'] ?? null,
        ], fn($v) => $v !== null);

        if ($existing) {
            $this->eventRepository->update($existing['id'], $payload);
            return $this->eventRepository->find($existing['id']) ?? [];
        }

        return $this->eventRepository->create(array_merge([
            'tenant_id'  => $tenantId,
            'event_code' => $eventCode,
            'channel'    => $channel,
            'template_id'      => 0,
            'is_enabled'       => 1,
            'variable_mapping' => [],
        ], $payload));
    }
}
