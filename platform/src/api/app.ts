import type { AuthInfoRes, SelectOption } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 应用全局配置API
 */

// 获取全局配置
export function getConfig() {
    return myRequest.get<AuthInfoRes>('/platformapi/system/config/global')
}

// 字典数据
export function getDictData(params: { codes: string }) {
    return myRequest.get<Record<string, SelectOption[]>>(
        '/platformapi/system/dictionary/batch-options',
        { params }
    )
}
