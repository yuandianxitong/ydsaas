import { type Ref, ref } from 'vue'

import { myRequest } from '@/utils/request'

export interface ApiOption {
    label: string
    value: number | string
}

// 模块级缓存：同 url 只拉一次（编辑器整页刷新才失效）；失败时删除缓存允许下次重试
const cache = new Map<string, Ref<ApiOption[]>>()

export function useApiOptions(url: string): Ref<ApiOption[]> {
    const hit = cache.get(url)
    if (hit) return hit
    const options = ref<ApiOption[]>([])
    cache.set(url, options)
    // 错误 toast 已由 myRequest 拦截器统一处理，这里静默留空
    myRequest.get<ApiOption[]>(url).then(
        (res) => {
            options.value = Array.isArray(res?.data) ? res.data : []
        },
        () => {
            cache.delete(url)
        }
    )
    return options
}

/** 仅测试用 */
export function __clearApiOptionsCache(): void {
    cache.clear()
}
