import type { PageQuery, PageResult } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface CronJobInfo {
    id: number
    name: string
    command: string
    expression: string
    description?: string
    status: number
    sort: number
    last_run_at?: string
    last_status?: number
    last_result?: string
    run_count: number
    created_at?: string
}

export interface CronJobLogInfo {
    id: number
    cron_job_id: number
    status: number
    output?: string
    error?: string
    duration?: number
    started_at: string
    finished_at?: string
}

export interface CronJobReq {
    name: string
    command: string
    cron_expression: string
    description?: string
    status?: number
    sort?: number
}

export interface CronJobQuery extends PageQuery {
    status?: number
}

/**
 * 定时任务API（Platform 端）
 */
export const cronJobApi = {
    /** 获取任务列表 */
    getList(params?: CronJobQuery) {
        return myRequest.get<PageResult<CronJobInfo>>('/platformapi/system/cron-job', { params })
    },

    /** 获取任务详情 */
    getDetail(id: number) {
        return myRequest.get<CronJobInfo>(`/platformapi/system/cron-job/${id}`)
    },

    /** 创建任务 */
    create(data: CronJobReq) {
        return myRequest.post<void>('/platformapi/system/cron-job', data)
    },

    /** 更新任务 */
    update(id: number, data: Partial<CronJobReq>) {
        return myRequest.put<void>(`/platformapi/system/cron-job/${id}`, data)
    },

    /** 删除任务 */
    delete(id: number) {
        return myRequest.delete<void>(`/platformapi/system/cron-job/${id}`)
    },

    /** 更新状态 */
    updateStatus(id: number, status: number) {
        return myRequest.put<void>(`/platformapi/system/cron-job/${id}/status`, { status })
    },

    /** 手动执行 */
    run(id: number) {
        return myRequest.post<{ status: number; output?: string }>(
            `/platformapi/system/cron-job/${id}/run`
        )
    },

    /** 获取执行日志 */
    getLogs(id: number, params?: PageQuery) {
        return myRequest.get<PageResult<CronJobLogInfo>>(
            `/platformapi/system/cron-job/${id}/logs`,
            { params }
        )
    },

    /** 清理日志 */
    clearLogs(id: number, keepDays?: number) {
        return myRequest.post<void>(`/platformapi/system/cron-job/${id}/clear-logs`, {
            keep_days: keepDays
        })
    }
}
