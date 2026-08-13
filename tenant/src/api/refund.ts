import { myRequest } from '@/utils/request'

export const refundApi = {
    list: (params?: any) => myRequest.get('/tenantapi/refund', { params }),
    show: (id: number) => myRequest.get(`/tenantapi/refund/${id}`),
    refundOrder: (orderId: number, data: { amount: number; reason: string }) =>
        myRequest.post(`/tenantapi/payment-order/${orderId}/refund`, data)
}
