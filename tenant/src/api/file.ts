import type { FileInfo, FileQuery, PageResult } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 文件管理API
 */
export const fileApi = {
    /** 获取文件列表 */
    getList(params: FileQuery) {
        return myRequest.get<PageResult<FileInfo>>('/tenantapi/system/file', { params })
    },

    /** 重命名 */
    rename(id: number, name: string) {
        return myRequest.put<void>(`/tenantapi/system/file/${id}/rename`, { name })
    },

    /** 删除文件 */
    delete(id: number) {
        return myRequest.delete<void>(`/tenantapi/system/file/${id}`)
    },

    /** 批量删除 */
    batchDelete(ids: number[]) {
        return myRequest.post<void>('/tenantapi/system/file/batch-delete', { ids })
    },

    /** 批量移动到分类（0=未分类） */
    moveCategory(ids: number[], categoryId: number) {
        return myRequest.post<void>('/tenantapi/system/file/move-category', { ids, category_id: categoryId })
    }
}
