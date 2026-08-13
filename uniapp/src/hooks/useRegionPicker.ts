/**
 * 主包公共 hook，源自 shop 插件同名 hook 提升（server/plugins/shop/uniapp/hooks/useRegionPicker.ts）；
 * shop 插件内副本待其下一版本切换到本文件。
 * 配套数据源：`@/api/region` 的 `regionApi.children(parentId)`（含 code 的平级列表）。
 */
import { ref, type Ref } from 'vue'

/** 地区节点最小形状（regionApi.children() 的 RegionRow 结构上是它的超集，可直接传入） */
export interface RegionNode {
  id: number
  code: string
  name: string
}

export interface RegionCodes {
  province_code: string
  city_code: string
  district_code: string
}

export interface RegionSelection {
  province: string
  province_code: string
  city: string
  city_code: string
  district: string
  district_code: string
}

/** 按 parentId 查子级地区（0 = 查省列表）——由调用方注入，便于 node 环境下用 mock 测试 */
export type RegionLoader = (parentId: number) => Promise<RegionNode[]>

/**
 * picker mode="multiSelector" 省市区三级联动的纯逻辑封装（零 uni 依赖，node 可测）。
 *
 * - columns：3 级候选数据（picker :range 由调用方 map 出 name 数组展示）
 * - indexes：当前选中的 [省, 市, 区] 列索引
 * - onColumnChange：用户滑动某一列后调用——重置下级列索引为 0 并异步加载新下级数据
 * - resolveByCodes：编辑回显——按 province_code/city_code/district_code 反查三级索引，
 *   查不到时该级索引兜底为 0（不阻断渲染，允许用户重新选择）
 */
export function useRegionPicker(loadChildren: RegionLoader) {
  const columns: Ref<RegionNode[][]> = ref([[], [], []])
  const indexes: Ref<number[]> = ref([0, 0, 0])
  const loading = ref(false)

  async function loadLevel(level: 1 | 2, parentId: number): Promise<void> {
    const rows = await loadChildren(parentId)
    const next = columns.value.slice() as RegionNode[][]
    next[level] = rows
    if (level === 1) next[2] = []
    columns.value = next
    if (level === 1 && rows.length) {
      await loadLevel(2, rows[0].id)
    }
  }

  /** 新增地址：从省列表第一项开始逐级加载默认联动列 */
  async function init(): Promise<void> {
    loading.value = true
    try {
      const provinces = await loadChildren(0)
      columns.value = [provinces, [], []]
      indexes.value = [0, 0, 0]
      if (provinces.length) {
        await loadLevel(1, provinces[0].id)
      }
    } finally {
      loading.value = false
    }
  }

  /** picker 某一列变化：下级列索引归零并异步刷新下级候选数据 */
  async function onColumnChange(colIndex: number, newIndex: number): Promise<void> {
    const nextIdx = indexes.value.slice()
    nextIdx[colIndex] = newIndex
    if (colIndex === 0) {
      nextIdx[1] = 0
      nextIdx[2] = 0
    } else if (colIndex === 1) {
      nextIdx[2] = 0
    }
    indexes.value = nextIdx

    if (colIndex === 0) {
      const province = columns.value[0][newIndex]
      if (province) await loadLevel(1, province.id)
    } else if (colIndex === 1) {
      const city = columns.value[1][newIndex]
      if (city) await loadLevel(2, city.id)
    }
  }

  /** 取当前 indexes 对应的省市区文案 + code，供表单提交前读取 */
  function selected(): RegionSelection {
    const [p, c, d] = indexes.value
    const province = columns.value[0][p]
    const city = columns.value[1][c]
    const district = columns.value[2][d]
    return {
      province: province?.name ?? '',
      province_code: province?.code ?? '',
      city: city?.name ?? '',
      city_code: city?.code ?? '',
      district: district?.name ?? '',
      district_code: district?.code ?? '',
    }
  }

  /** 编辑回显：按已存的三级 code 反查索引；某级查不到就该级兜底第 0 项 */
  async function resolveByCodes(codes: RegionCodes): Promise<void> {
    loading.value = true
    try {
      const provinces = await loadChildren(0)
      const pIdx = Math.max(0, provinces.findIndex((r) => r.code === codes.province_code))
      const province = provinces[pIdx]

      const cities = province ? await loadChildren(province.id) : []
      const cIdx = Math.max(0, cities.findIndex((r) => r.code === codes.city_code))
      const city = cities[cIdx]

      const districts = city ? await loadChildren(city.id) : []
      const dIdx = Math.max(0, districts.findIndex((r) => r.code === codes.district_code))

      columns.value = [provinces, cities, districts]
      indexes.value = [pIdx, cIdx, dIdx]
    } finally {
      loading.value = false
    }
  }

  return { columns, indexes, loading, init, onColumnChange, selected, resolveByCodes }
}
