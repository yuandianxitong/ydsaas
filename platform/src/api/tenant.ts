import type { PageResult, TenantAdmin, TenantInfo, TenantQuery, TenantReq } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 租户管理 API
 */
export const tenantApi = {
    /** 分页列表 */
    list(params: TenantQuery) {
        return myRequest.get<PageResult<TenantInfo>>('/platformapi/tenants', { params })
    },

    /** 详情 */
    show(id: number) {
        return myRequest.get<TenantInfo>(`/platformapi/tenants/${id}`)
    },

    /** 创建 */
    create(data: TenantReq) {
        return myRequest.post<TenantInfo>('/platformapi/tenants', data)
    },

    /** 更新 */
    update(id: number, data: TenantReq) {
        return myRequest.put(`/platformapi/tenants/${id}`, data)
    },

    /** 删除（软删除） */
    destroy(id: number) {
        return myRequest.delete(`/platformapi/tenants/${id}`)
    },

    /** 获取租户管理员列表 */
    listAdmins(tenantId: number) {
        return myRequest.get<TenantAdmin[]>(`/platformapi/tenants/${tenantId}/admins`)
    },

    /** 重置租户管理员密码 */
    resetPassword(tenantId: number, adminId: number, newPassword: string) {
        return myRequest.put(`/platformapi/tenants/${tenantId}/reset-password`, {
            admin_id: adminId,
            new_password: newPassword
        })
    },

    /** 平台线下续费：代建 offline 订单并标记已支付 */
    offlineRenew(
        id: number,
        data: { plan_id?: number; months: number; remark?: string; transaction_id?: string }
    ) {
        return myRequest.post(`/platformapi/tenants/${id}/offline-renew`, data)
    }
}
