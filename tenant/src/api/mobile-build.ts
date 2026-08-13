import { myRequest } from '@/utils/request'

export type BuildStatus = 0 | 1 | 2 | 3 | 4 | 5
// 0=queued 1=running 2=success 3=failed 4=uploaded 5=released

export type BuildPlatform = 'h5' | 'mp-weixin' | 'app'

export interface MobileBuild {
    id: number
    tenant_id: number
    platform: BuildPlatform
    build_no: string
    status: BuildStatus
    artifact_path: string
    included_plugins_json: string[] | null
    upload_result_json: Record<string, any> | null
    error_log: string | null
    driver?: string | null
    remote_job_id?: string | null
    artifact_url?: string | null
    runtime_json?: Record<string, unknown> | null
    work_dir?: string
    artifact_hint?: string
    release_url?: string
    started_at: string | null
    finished_at: string | null
    operator_id: number | null
    created_at: string
    updated_at: string
}

export interface PaginatedBuilds {
    items: MobileBuild[]
    total: number
}

export const mobileBuildApi = {
    list(params: { page?: number; limit?: number; platform?: BuildPlatform } = {}) {
        return myRequest.get<PaginatedBuilds>('/tenantapi/mobile/builds', { params })
    },
    detail(id: number) {
        return myRequest.get<MobileBuild>(`/tenantapi/mobile/builds/${id}`)
    },
    create(platform: BuildPlatform) {
        return myRequest.post<MobileBuild>('/tenantapi/mobile/builds', { platform })
    },
    requeue(id: number) {
        return myRequest.post<MobileBuild>(`/tenantapi/mobile/builds/${id}/requeue`)
    },
    release(id: number) {
        return myRequest.post<MobileBuild>(`/tenantapi/mobile/builds/${id}/release`)
    },
    upload(id: number) {
        return myRequest.post<MobileBuild>(`/tenantapi/mobile/builds/${id}/upload`)
    },
    cancel(id: number) {
        return myRequest.post<MobileBuild>(`/tenantapi/mobile/builds/${id}/cancel`)
    },
    saveWechatKey(file: File) {
        const form = new FormData()
        form.append('key_file', file)
        // 必须用 POST：PHP 只对 POST 填充 $_FILES。勿手写 Content-Type，由浏览器带 boundary。
        return myRequest.post<{ has_key: boolean }>('/tenantapi/mobile/config/wechat-key', form)
    },
    clearWechatKey() {
        return myRequest.delete<{ has_key: boolean }>('/tenantapi/mobile/config/wechat-key')
    },
}
