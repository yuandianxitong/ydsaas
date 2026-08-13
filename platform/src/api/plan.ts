import type { PageResult, PlanInfo, PlanReq } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 套餐管理 API
 */
export const planApi = {
    list(params: { page?: number; size?: number; keyword?: string; status?: number }) {
        return myRequest.get<PageResult<PlanInfo>>('/platformapi/plans', { params })
    },

    show(id: number) {
        return myRequest.get<PlanInfo>(`/platformapi/plans/${id}`)
    },

    create(data: PlanReq) {
        return myRequest.post<PlanInfo>('/platformapi/plans', data)
    },

    update(id: number, data: PlanReq) {
        return myRequest.put(`/platformapi/plans/${id}`, data)
    },

    destroy(id: number) {
        return myRequest.delete(`/platformapi/plans/${id}`)
    },

    /** 下拉数据：所有启用的套餐 */
    options() {
        return myRequest.get<PlanInfo[]>('/platformapi/plans/options')
    }
}
