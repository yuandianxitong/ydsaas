import type {
    AdminInfo,
    AdminQuery,
    AdminReq,
    BatchDeleteReq,
    PageResult,
    ResetPasswordReq,
    SelectOption,
    StatusReq
} from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 管理员管理API
 */
export const adminApi = {
    /**
     * 获取管理员列表
     */
    getAdminList(params: AdminQuery) {
        return myRequest.get<PageResult<AdminInfo>>('/tenantapi/system/admin', { params })
    },

    /**
     * 获取管理员详情
     */
    getAdminDetail(id: number) {
        return myRequest.get<AdminInfo>(`/tenantapi/system/admin/${id}`)
    },

    /**
     * 创建管理员
     */
    createAdmin(data: AdminReq) {
        return myRequest.post<void>('/tenantapi/system/admin', data)
    },

    /**
     * 更新管理员
     */
    updateAdmin(id: number, data: Partial<AdminReq>) {
        return myRequest.put<void>(`/tenantapi/system/admin/${id}`, data)
    },

    /**
     * 删除管理员
     */
    deleteAdmin(id: number) {
        return myRequest.delete<void>(`/tenantapi/system/admin/${id}`)
    },

    /**
     * 批量删除管理员
     */
    batchDeleteAdmin(data: BatchDeleteReq) {
        return myRequest.post<void>('/tenantapi/system/admin/batch-delete', data)
    },

    /**
     * 更新管理员状态
     */
    updateAdminStatus(id: number, data: StatusReq) {
        return myRequest.put<void>(`/tenantapi/system/admin/${id}/status`, data)
    },

    /**
     * 重置管理员密码
     */
    resetAdminPassword(id: number, data: ResetPasswordReq) {
        return myRequest.put<void>(`/tenantapi/system/admin/${id}/reset-password`, data)
    },

    /**
     * 获取角色选项
     */
    getRoleOptions() {
        return myRequest.get<SelectOption[]>('/tenantapi/system/admin/role/options')
    }
}
