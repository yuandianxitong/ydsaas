import type { PageResult } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface PermissionInfo {
    id: number
    name: string
    title: string
    group?: string
    description?: string
    guard_name: string
    status: number
    sort: number
    created_at?: string
}

export interface PermissionReq {
    name: string
    title: string
    group?: string
    description?: string
    guard_name?: string
    status?: number
    sort?: number
}

export const permissionApi = {
    list(params?: { keyword?: string; group?: string; page?: number; limit?: number }) {
        return myRequest.get<PageResult<PermissionInfo>>('/platformapi/system/permission', {
            params
        })
    },
    tree() {
        return myRequest.get<Record<string, PermissionInfo[]>>(
            '/platformapi/system/permission/tree'
        )
    },
    create(data: PermissionReq) {
        return myRequest.post<void>('/platformapi/system/permission', data)
    },
    update(id: number, data: Partial<PermissionReq>) {
        return myRequest.put<void>(`/platformapi/system/permission/${id}`, data)
    },
    delete(id: number) {
        return myRequest.delete<void>(`/platformapi/system/permission/${id}`)
    },
    batchCreate(data: PermissionReq[]) {
        return myRequest.post<void>('/platformapi/system/permission/batch-create', {
            permissions: data
        })
    }
}
