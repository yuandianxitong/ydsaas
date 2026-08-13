import type { ApiResponse, PageResult } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface PluginPanelInfo {
    code: string
    name: string
    component: string
    icon: string
}

export interface TenantPluginInfo {
    plugin_id: number
    plugin_code: string
    name: string
    version: string
    icon: string
    description: string
    type: number
    source: number
    kind: 'app' | 'plugin'
    category: string
    auto_enable: number // 0 | 1
    tenant_status: number // 0=未启用 1=已启用 2=已禁用 3=过期
    installed_version: string
    enabled_at: string | null
    expired_at: string | null
    author: string
    capabilities: Array<{ code: string; name: string }>
    panels: PluginPanelInfo[]
    has_testdata?: boolean
    testdata_imported_at?: string | null
}

export interface PluginOrder {
    id: number
    order_no: string
    plugin_id: number
    plugin_name: string
    months: number
    amount: string
    paid_amount: string
    status: number // 1=待支付 2=已支付 3=已取消 4=已退款
    payment_channel: string
    paid_at: string | null
    created_at: string
}

export const pluginApi = {
    list() {
        return myRequest.get<TenantPluginInfo[]>('/tenantapi/plugin')
    },
    enable(id: number) {
        return myRequest.post<{ plugin_id: number; plugin_code: string; status: number }>(
            `/tenantapi/plugin/${id}/enable`
        )
    },
    disable(id: number) {
        return myRequest.post<{ plugin_id: number; status: number }>(
            `/tenantapi/plugin/${id}/disable`
        )
    },
    /** 导入演示数据（每租户一次） */
    importTestdata(id: number) {
        return myRequest.post<{ imported: number; imported_at: string }>(
            `/tenantapi/plugin/${id}/testdata`
        )
    },
    getConfig(pluginCode: string) {
        return myRequest.get<Record<string, string | null>>(
            `/tenantapi/plugin/${pluginCode}/config`
        )
    },
    updateConfig(pluginCode: string, config: Record<string, string | null>) {
        return myRequest.put<Record<string, string | null>>(
            `/tenantapi/plugin/${pluginCode}/config`,
            { config }
        )
    },
    setConfig(pluginCode: string, config: Record<string, any>) {
        return myRequest.put<Record<string, any>>(`/tenantapi/plugin/${pluginCode}/config`, {
            config
        })
    },
    getConfigSchema(code: string) {
        return myRequest.get<{ fields: any[] } | null>(`/tenantapi/plugin/${code}/config-schema`)
    },
    purchase(
        id: number,
        params: { months: number; amount: number; channel?: string; method?: string }
    ) {
        return myRequest.post<{
            order: { id: number; order_no: string; amount: number }
            payment: { pay_url?: string; qr_code?: string; [k: string]: unknown }
        }>(`/tenantapi/plugin/${id}/purchase`, params)
    },
    orders(params: {
        page?: number
        limit?: number
        keyword?: string
        status?: number
    }): Promise<ApiResponse<PageResult<PluginOrder>>> {
        return myRequest.get<PageResult<PluginOrder>>('/tenantapi/plugin/orders', { params })
    }
}
