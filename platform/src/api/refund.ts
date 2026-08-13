import type { PageQuery, PageResult } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface RefundInfo {
    id: number
    order_id: number
    refund_no: string
    amount: number
    reason: string
    status: number
    created_at?: string
    updated_at?: string
}

export interface RefundQuery extends PageQuery {
    status?: number | ''
    order_no?: string
}

export const refundApi = {
    list: (params?: RefundQuery) =>
        myRequest.get<PageResult<RefundInfo>>('/platformapi/refund', { params }),
    show: (id: number) => myRequest.get<RefundInfo>(`/platformapi/refund/${id}`),
    refundOrder: (orderId: number, data: { amount: number; reason: string }) =>
        myRequest.post(`/platformapi/orders/${orderId}/refund`, data)
}
