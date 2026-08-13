import { myRequest } from '@/utils/request'

export interface MessageEventChannelConfig {
    id: number | null
    template_id: number
    is_enabled: number
    variable_mapping: Record<string, string>
}

export interface MessageEventItem {
    event_code: string
    label: string
    variables: string[]
    channels: {
        sms: MessageEventChannelConfig
        wechat_oa: MessageEventChannelConfig
        wechat_miniapp: MessageEventChannelConfig
    }
}

export interface MessageEventUpdateReq {
    template_id?: number
    is_enabled?: number
    variable_mapping?: Record<string, string>
}

export const messageEventApi = {
    list: () => myRequest.get<MessageEventItem[]>('/tenantapi/message/events'),
    update: (eventCode: string, channel: string, data: MessageEventUpdateReq) =>
        myRequest.put<void>(`/tenantapi/message/events/${eventCode}/${channel}`, data)
}
