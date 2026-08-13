import type { PaymentResponse, PlanInfo, SaasOrderInfo, SubscriptionInfo } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 租户侧订阅 + 支付 API（M3D T103）
 *
 * 对齐 M3B 后端端点：
 *   GET  /tenantapi/subscription/current
 *   GET  /tenantapi/subscription/plans
 *   POST /tenantapi/subscription/create-order
 *   POST /tenantapi/subscription/pay
 *   GET  /tenantapi/subscription/query-order
 */
export const subscriptionApi = {
    /** 当前订阅 */
    current() {
        return myRequest.get<{ subscription: SubscriptionInfo | null }>(
            '/tenantapi/subscription/current'
        )
    },

    /** 可选套餐列表（公开） */
    plans() {
        return myRequest.get<{ list: PlanInfo[] }>('/tenantapi/subscription/plans')
    },

    /** 创建订单（待支付） */
    createOrder(data: {
        plan_id: number
        months: number
        channel?: 'wechat' | 'alipay'
        method?: string
    }) {
        return myRequest.post<SaasOrderInfo>('/tenantapi/subscription/create-order', data)
    },

    /** 发起支付（调 driver.create 拿 code_url/prepay_id） */
    pay(order_id: number) {
        return myRequest.post<PaymentResponse>('/tenantapi/subscription/pay', { order_id })
    },

    /** 查询订单状态（前端轮询用） */
    queryOrder(order_id: number) {
        return myRequest.get<{ order: SaasOrderInfo }>('/tenantapi/subscription/query-order', {
            params: { order_id }
        })
    },

    /** 查询待支付的续费订单 */
    pendingRenewal() {
        return myRequest.get<SaasOrderInfo | null>('/tenantapi/subscription/pending-renewal')
    }
}
