import type { FileCategory } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 文件分类API（素材库层级分类树）
 */
export const fileCategoryApi = {
    /** 分类树 */
    getTree() {
        return myRequest.get<FileCategory[]>('/tenantapi/system/file-category/tree')
    },

    /** 新建分类 */
    create(data: { name: string; parent_id?: number; sort?: number }) {
        return myRequest.post<FileCategory>('/tenantapi/system/file-category', data)
    },

    /** 重命名分类 */
    update(id: number, data: { name: string }) {
        return myRequest.put<void>(`/tenantapi/system/file-category/${id}`, data)
    },

    /** 删除分类（子分类拒绝，文件移入未分类） */
    delete(id: number) {
        return myRequest.delete<void>(`/tenantapi/system/file-category/${id}`)
    }
}
