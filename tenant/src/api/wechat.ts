import type {
    AutoReplyInfo,
    AutoReplyReq,
    PageQuery,
    PageResult,
    WechatFollowerInfo
} from '@/types/api'
import { myRequest } from '@/utils/request'

export interface WechatMenuData {
    button: Array<{
        type: string
        name: string
        key?: string
        url?: string
        sub_button?: Array<{
            type: string
            name: string
            key?: string
            url?: string
        }>
    }>
}

export interface WechatTemplateSendReq {
    touser: string
    template_id: string
    data: Record<string, { value: string; color?: string }>
    url?: string
}

// 微信返回的菜单查询结果
export interface WechatMenuQueryResult {
    selfmenu_info?: {
        button: Array<Record<string, any>>
    }
    [key: string]: any
}

// 公众号管理
export const officialAccountApi = {
    getMenu() {
        return myRequest.get<WechatMenuQueryResult>('/tenantapi/wechat/official/menu')
    },
    createMenu(data: WechatMenuData) {
        return myRequest.post<void>('/tenantapi/wechat/official/menu', data)
    },
    deleteMenu() {
        return myRequest.delete<void>('/tenantapi/wechat/official/menu')
    },
    sendTemplate(data: WechatTemplateSendReq) {
        return myRequest.post<void>('/tenantapi/wechat/official/template/send', data)
    },
    getFollowers(params?: PageQuery) {
        return myRequest.get<PageResult<WechatFollowerInfo>>(
            '/tenantapi/wechat/official/followers',
            { params }
        )
    },
    getUserInfo(params: { openid: string }) {
        return myRequest.get<WechatFollowerInfo>('/tenantapi/wechat/official/user-info', { params })
    }
}

// 自动回复管理
export const autoReplyApi = {
    getList(params?: PageQuery) {
        return myRequest.get<PageResult<AutoReplyInfo>>('/tenantapi/wechat/auto-reply', { params })
    },
    getDetail(id: number) {
        return myRequest.get<AutoReplyInfo>(`/tenantapi/wechat/auto-reply/${id}`)
    },
    create(data: AutoReplyReq | Record<string, any>) {
        return myRequest.post<void>('/tenantapi/wechat/auto-reply', data)
    },
    update(id: number, data: Partial<AutoReplyReq> | Record<string, any>) {
        return myRequest.put<void>(`/tenantapi/wechat/auto-reply/${id}`, data)
    },
    delete(id: number) {
        return myRequest.delete<void>(`/tenantapi/wechat/auto-reply/${id}`)
    }
}
