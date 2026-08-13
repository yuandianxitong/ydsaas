<?php
declare(strict_types=1);

namespace app\listener\message;

use app\repository\message\MessageEventRepository;
use app\repository\message\MessageLogRepository;
use think\facade\Log;

/**
 * 统一消息事件调度监听器
 *
 * 接收 'message.event.dispatch' 事件，根据 message_events 配置表
 * 找出启用的通道，为每个通道创建发送日志并推送 MessageSendJob 队列。
 *
 * 事件数据格式：
 * [
 *   'event_code' => 'order_paid',
 *   'tenant_id'  => 1,
 *   'receivers'  => ['phone' => '138...', 'openid' => 'oxxx', 'mini_openid' => 'oyyy'],
 *   'variables'  => ['order_no' => 'ORD001', 'amount' => '99.00', ...],
 * ]
 */
class MessageEventListener
{
    // 通道标识 → receivers 数组中对应的 key
    private const CHANNEL_RECEIVER_KEY = [
        'sms'             => 'phone',
        'wechat_oa'       => 'openid',
        'wechat_miniapp'  => 'mini_openid',
    ];

    // 通道标识 → MessageSendJob / resolveChannel 可识别的内部 channel 标识
    private const CHANNEL_INTERNAL = [
        'sms'            => 'sms',
        'wechat_oa'      => 'wechat_official',
        'wechat_miniapp' => 'wechat_mini',
    ];

    public function __construct(
        protected MessageEventRepository $eventRepository,
        protected MessageLogRepository $logRepository,
    ) {
    }

    public function handle(array $event): void
    {
        $eventCode = $event['event_code'] ?? '';
        $tenantId  = (int) ($event['tenant_id'] ?? 0);
        $receivers = $event['receivers'] ?? [];
        $variables = $event['variables'] ?? [];

        if (!$eventCode || !$tenantId) {
            Log::warning('MessageEventListener: 缺少 event_code 或 tenant_id', $event);
            return;
        }

        $configs = $this->eventRepository->getEnabledByEventCode($eventCode, $tenantId);

        if (empty($configs)) {
            return;
        }

        foreach ($configs as $config) {
            $channel    = $config['channel'];      // e.g. 'sms', 'wechat_oa', 'wechat_miniapp'
            $templateId = (int) $config['template_id'];

            $receiverKey = self::CHANNEL_RECEIVER_KEY[$channel] ?? null;
            if ($receiverKey === null) {
                Log::warning("MessageEventListener: 未知通道 [{$channel}]，跳过");
                continue;
            }

            $receiver = $receivers[$receiverKey] ?? '';
            if (empty($receiver)) {
                // 该通道没有对应的接收者，跳过
                continue;
            }

            // 合并变量映射
            $mappedVariables = $this->applyVariableMapping(
                $variables,
                $config['variable_mapping'] ?? []
            );

            $internalChannel = self::CHANNEL_INTERNAL[$channel] ?? $channel;

            try {
                // 创建发送日志（status=0 待发送）
                $log = $this->logRepository->createLog([
                    'tenant_id'     => $tenantId,
                    'template_id'   => $templateId,
                    'template_code' => $eventCode,
                    'channel'       => $internalChannel,
                    'receiver'      => $receiver,
                    'variables'     => $mappedVariables,
                    'status'        => 0,
                ]);

                // 推送队列
                \core\queue\QueueManager::push(\app\job\MessageSendJob::class, [
                    'channel'     => $internalChannel,
                    'receiver'    => $receiver,
                    'template_id' => (string) $templateId,
                    'variables'   => $mappedVariables,
                    'extra'       => [],
                    'log_id'      => $log['id'],
                    'tenant_id'   => $tenantId,
                ]);
            } catch (\Throwable $e) {
                Log::error("MessageEventListener: 推送失败 [{$channel}]", [
                    'event_code' => $eventCode,
                    'tenant_id'  => $tenantId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 应用变量映射
     *
     * variable_mapping 是一个 key→value 的 JSON 对象，例如：
     * {"order_no": "orderNo", "amount": "totalAmount"}
     * 表示将 variables['orderNo'] 映射为发送时的 'order_no' 键。
     *
     * 若 mapping 为空或不含对应 key，则原样透传。
     */
    private function applyVariableMapping(array $variables, array $mapping): array
    {
        if (empty($mapping)) {
            return $variables;
        }

        $result = [];
        foreach ($mapping as $templateKey => $sourceKey) {
            if (isset($variables[$sourceKey])) {
                $result[$templateKey] = $variables[$sourceKey];
            } elseif (isset($variables[$templateKey])) {
                $result[$templateKey] = $variables[$templateKey];
            }
        }

        // 将未被映射的变量原样保留
        foreach ($variables as $k => $v) {
            if (!isset($result[$k])) {
                $result[$k] = $v;
            }
        }

        return $result;
    }
}
