import type { DashboardStats } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 平台 dashboard 聚合统计 API
 */
export const dashboardApi = {
    stats() {
        return myRequest.get<DashboardStats>('/platformapi/dashboard/stats')
    },
    extendedStats() {
        return myRequest.get('/platformapi/dashboard/extended-stats')
    },
    revenueTrend(months?: number) {
        return myRequest.get('/platformapi/dashboard/revenue-trend', { params: { months } })
    }
}
