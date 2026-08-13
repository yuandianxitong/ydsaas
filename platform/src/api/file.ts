import type { PageResult } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface FileInfo {
    id: number
    name: string
    path: string
    url: string
    mime_type: string
    extension: string
    size: number
    size_text?: string
    is_image?: boolean
    group: string
    storage: string
    created_at?: string
}

export interface FileQuery {
    keyword?: string
    group?: string
    mime_type?: string
    page?: number
    limit?: number
}

export const fileApi = {
    getList(params: FileQuery) {
        return myRequest.get<PageResult<FileInfo>>('/platformapi/system/file', { params })
    },
    getGroups() {
        return myRequest.get<string[]>('/platformapi/system/file/groups')
    },
    moveGroup(ids: number[], group: string) {
        return myRequest.post<void>('/platformapi/system/file/move-group', { ids, group })
    },
    rename(id: number, name: string) {
        return myRequest.put<void>(`/platformapi/system/file/${id}/rename`, { name })
    },
    delete(id: number) {
        return myRequest.delete<void>(`/platformapi/system/file/${id}`)
    },
    batchDelete(ids: number[]) {
        return myRequest.post<void>('/platformapi/system/file/batch-delete', { ids })
    }
}
