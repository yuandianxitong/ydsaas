import { get } from '~/composables/useRequest'

export interface DiyPagePayload {
  components: Array<{ id: string; type: string; props: Record<string, any> }>
  page_settings?: Record<string, any>
}

export const diyApi = {
  /** 按 slug 拉取某自定义页已发布树。HTTP 404 = 未发布/禁用/不存在（ofetch 会 reject，调用方 catch）。 */
  getDiyPage: (key: string) => get<DiyPagePayload>('/api/pc/diy-page', { key }),
}
