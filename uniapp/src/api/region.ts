import { http } from '@/utils/request'

/**
 * 地区树节点（`RegionRepository::buildTree()` 递归构建，级联选择器用）。
 * 仅含 value/label，不含 code——需要三级编码时用 `regionApi.children()` 按需查平级列表。
 */
export interface RegionTreeNode {
  value: number
  label: string
  children?: RegionTreeNode[]
}

/**
 * 地区平级列表行（`regions` 表原始字段，见 `server/app/model/region/Region.php`）。
 * `level`：1 省 2 市 3 区；`level_text` 为模型 getter 附加的中文层级文本。
 */
export interface RegionRow {
  id: number
  parent_id: number
  name: string
  code: string
  level: 1 | 2 | 3
  sort: number
  status: 0 | 1
  level_text: string
}

/**
 * C 端地区数据（无需登录，`app/api/route/region.php`）。后端提供两种契约：
 * - `tree()`：一次性拿到全量省市区级联树（{value,label,children}），不含 code；
 * - `children(parentId)`：按需拿某一级的平级列表（含 code），地址编辑页需要写入
 *   `province_code`/`city_code`/`district_code` 时用这个逐级查询。
 */
export const regionApi = {
  tree(): Promise<RegionTreeNode[]> {
    return http.get<RegionTreeNode[]>('/api/region/tree')
  },

  children(parentId = 0): Promise<RegionRow[]> {
    return http.get<RegionRow[]>('/api/region/children', { parent_id: parentId })
  },
}
