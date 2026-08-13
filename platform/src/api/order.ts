import type { OrderQuery, PageResult, SaasOrderInfo } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * SaaS 订单管理 API（M3D T106）
 */
export const orderApi = {
    /** 分页列表 */
    list(params: OrderQuery) {
        return myRequest.get<PageResult<SaasOrderInfo>>('/platformapi/orders', { params })
    },

    /** 详情 */
    show(id: number) {
        return myRequest.get<SaasOrderInfo>(`/platformapi/orders/${id}`)
    },

    /** 手动标记已支付（线下汇款场景） */
    markPaid(id: number, transaction_id = 'manual') {
        return myRequest.post<SaasOrderInfo>(`/platformapi/orders/${id}/mark-paid`, {
            transaction_id
        })
    },

    /** 取消订单 */
    cancel(id: number) {
        return myRequest.post(`/platformapi/orders/${id}/cancel`)
    }
}
