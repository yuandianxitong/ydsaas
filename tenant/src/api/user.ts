import type { BalanceLogItem, PageResult, PointsLogItem, UserItem } from '@/types/api'
import { myRequest } from '@/utils/request'

// 向后兼容：视图文件直接 `import { UserItem } from '@/api/user'` 仍可工作
export type { BalanceLogItem, PointsLogItem, UserItem }

/**
 * 用户管理API
 */
export const userManageApi = {
    /**
     * 获取用户列表
     */
    getUserList(params: any) {
        return myRequest.get<PageResult<UserItem>>('/tenantapi/user/list', { params })
    },

    /**
     * 获取用户详情
     */
    getUserDetail(id: number) {
        return myRequest.get<UserItem>(`/tenantapi/user/detail/${id}`)
    },

    /**
     * 调整余额
     */
    adjustBalance(data: { user_id: number; amount: number; remark: string }) {
        return myRequest.post<void>('/tenantapi/user/adjust-balance', data)
    },

    /**
     * 调整积分
     */
    adjustPoints(data: { user_id: number; points: number; remark: string }) {
        return myRequest.post<void>('/tenantapi/user/adjust-points', data)
    },

    /**
     * 更新用户状态
     */
    updateStatus(id: number, data: { status: number }) {
        return myRequest.put<void>(`/tenantapi/user/${id}/status`, data)
    },

    /**
     * 获取余额记录
     */
    getBalanceLogs(params: any) {
        return myRequest.get<PageResult<BalanceLogItem>>('/tenantapi/user/balance-logs', { params })
    },

    /**
     * 获取积分记录
     */
    getPointsLogs(params: any) {
        return myRequest.get<PageResult<PointsLogItem>>('/tenantapi/user/points-logs', { params })
    }
}
