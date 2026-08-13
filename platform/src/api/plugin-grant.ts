import type { PluginGrantInfo, PluginGrantSyncReq, PluginOptionInfo } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 套餐授权 API
 */
export const pluginGrantApi = {
    /** 拿可被授权的全部已启用插件，用于多选框选项 */
    options() {
        return myRequest.get<PluginOptionInfo[]>('/platformapi/plugins/options')
    },

    /** 查某套餐的全部已授权插件 */
    listByPlan(planId: number) {
        return myRequest.get<PluginGrantInfo[]>(`/platformapi/plans/${planId}/plugin-grants`)
    },

    /** 同步某套餐的授权 */
    sync(planId: number, data: PluginGrantSyncReq) {
        return myRequest.put<{
            added: PluginGrantInfo[]
            removed: PluginGrantInfo[]
            changed: PluginGrantInfo[]
        }>(`/platformapi/plans/${planId}/plugin-grants`, data)
    }
}
