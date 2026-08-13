import type { AppVersionInfo, AppVersionQuery, AppVersionReq, PageResult } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 版本管理API
 */
export const versionApi = {
    /** 获取版本列表 */
    getList(params?: AppVersionQuery) {
        return myRequest.get<PageResult<AppVersionInfo>>('/platformapi/version/list', { params })
    },

    /** 获取版本详情 */
    getDetail(id: number) {
        return myRequest.get<AppVersionInfo>(`/platformapi/version/detail/${id}`)
    },

    /** 新增版本 */
    create(data: AppVersionReq) {
        return myRequest.post<void>('/platformapi/version', data)
    },

    /** 更新版本 */
    update(id: number, data: Partial<AppVersionReq>) {
        return myRequest.put<void>(`/platformapi/version/${id}`, data)
    },

    /** 删除版本 */
    delete(id: number) {
        return myRequest.delete<void>(`/platformapi/version/${id}`)
    }
}
