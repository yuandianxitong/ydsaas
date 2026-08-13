import type { AxiosRequestConfig } from 'axios'

import type { ActiveRanking, ActivityItem, DashboardStats, LoginLogInfo } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface StoreAccessChannel {
    ready: boolean
    reason_code: string
    url?: string
    qr_url?: string
    message?: string
    action_path: string
}

export interface StoreAccessInfo {
    h5: StoreAccessChannel
    miniprogram: StoreAccessChannel
    pc: StoreAccessChannel
}

// 获取仪表板统计数据
export const getDashboardStats = (days: number = 7, config?: AxiosRequestConfig) => {
    return myRequest.get<DashboardStats>('/tenantapi/dashboard/stats', {
        ...config,
        params: { days, ...(config?.params as object) }
    })
}

// 获取最近登录日志
export const getRecentLoginLogs = (config?: AxiosRequestConfig) => {
    return myRequest.get<LoginLogInfo[]>('/tenantapi/dashboard/recent-logs', config)
}

// 获取最近动态
export const getRecentActivities = (config?: AxiosRequestConfig) => {
    return myRequest.get<ActivityItem[]>('/tenantapi/dashboard/recent-activities', config)
}

// 获取活跃排行
export const getActiveRanking = (period: string = 'day', config?: AxiosRequestConfig) => {
    return myRequest.get<ActiveRanking>('/tenantapi/dashboard/active-ranking', {
        ...config,
        params: { period, ...(config?.params as object) }
    })
}

/** 店铺访问入口（H5 / 小程序 / PC） */
export const getAccessInfo = (config?: AxiosRequestConfig) => {
    return myRequest.get<StoreAccessInfo>('/tenantapi/dashboard/access-info', config)
}
