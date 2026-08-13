import type { PageResult, PluginBuildInfo } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 云编译任务 API（Stage 5）
 */
export const pluginBuildApi = {
    list(params: { page?: number; limit?: number }) {
        return myRequest.get<PageResult<PluginBuildInfo>>('/platformapi/plugin-builds', { params })
    },

    show(id: number) {
        return myRequest.get<PluginBuildInfo>(`/platformapi/plugin-builds/${id}`)
    },

    rebuild(target: 'platform' | 'tenant') {
        return myRequest.post<{ build_id: number }>('/platformapi/plugin-builds/rebuild', {
            target
        })
    }
}
