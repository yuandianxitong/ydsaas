import type { PageResult, RegionInfo, RegionReq } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 地区数据API
 */
export const regionApi = {
    /** 获取地区列表 */
    getList(params?: Record<string, any>) {
        return myRequest.get<PageResult<RegionInfo>>('/platformapi/region/list', { params })
    },

    /** 获取地区树 */
    getTree() {
        return myRequest.get<RegionInfo[]>('/platformapi/region/tree')
    },

    /** 获取地区详情 */
    getDetail(id: number) {
        return myRequest.get<RegionInfo>(`/platformapi/region/detail/${id}`)
    },

    /** 新增地区 */
    create(data: RegionReq) {
        return myRequest.post<void>('/platformapi/region', data)
    },

    /** 更新地区 */
    update(id: number, data: Partial<RegionReq>) {
        return myRequest.put<void>(`/platformapi/region/${id}`, data)
    },

    /** 删除地区 */
    delete(id: number) {
        return myRequest.delete<void>(`/platformapi/region/${id}`)
    }
}
