import type { PageQuery, PageResult } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface MobileBuildInfo {
    id: number
    tenant_id: number
    plan_id: number
    platform: string
    build_no: string
    /** 0=queued 1=running 2=success 3=failed 4=uploaded 5=released */
    status: number
    driver: string
    remote_job_id: string
    artifact_path: string
    artifact_url: string
    error_log: string
    started_at?: string
    finished_at?: string
    created_at?: string
}

export interface MobileBuildQuery extends PageQuery {
    status?: number | ''
    platform?: string
    tenant_id?: number | ''
}

export const mobileBuildApi = {
    list: (params?: MobileBuildQuery) =>
        myRequest.get<PageResult<MobileBuildInfo>>('/platformapi/mobile-builds', { params }),
    /** 把 running 超过 threshold_seconds 的构建强制标记失败 */
    forceFailStuck: (thresholdSeconds: number) =>
        myRequest.post<{ closed: number }>('/platformapi/mobile-builds/force-fail', {
            threshold_seconds: thresholdSeconds
        })
}
