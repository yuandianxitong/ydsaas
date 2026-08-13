import { myRequest } from '@/utils/request'

/**
 * 地区树节点（后端 `RegionRepository::buildTree()` 递归构建，级联选择器用）。
 * 仅含 value/label/children，不含 code。
 */
export interface RegionTreeNode {
    value: string | number
    label: string
    children?: RegionTreeNode[]
}

/**
 * 租户端地区数据 API（`app/tenantapi/route/common.php`）
 */
export const regionApi = {
    /** 获取全量省市区级联树 */
    tree() {
        return myRequest.get<RegionTreeNode[]>('/tenantapi/common/regions')
    }
}
