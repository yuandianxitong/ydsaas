/* cspell:disable */
import type { ConfigBatchUpdateItem, ConfigInfo, ConfigReq } from '@/types/api'
import { myRequest } from '@/utils/request'

// 获取配置分组
export function getConfigGroups() {
    return myRequest.get<Record<string, string>>('/tenantapi/system/config/groups')
}

// 获取指定分组的配置列表
export function getConfigsByGroup(group: string = 'basic') {
    return myRequest.get<ConfigInfo[]>(`/tenantapi/system/config?group=${group}`)
}

// 获取单个配置
export function getConfig(id: number) {
    return myRequest.get<ConfigInfo>(`/tenantapi/system/config/${id}`)
}

// 更新单个配置
export function updateConfig(id: number, data: Partial<ConfigReq>) {
    return myRequest.put<void>(`/tenantapi/system/config/${id}`, data)
}

// 批量更新配置
export function batchUpdateConfigs(configs: ConfigBatchUpdateItem[]) {
    return myRequest.post<void>('/tenantapi/system/config/batch-update', { configs })
}

// 清除系统缓存
export function clearSystemCache() {
    return myRequest.post<void>('/tenantapi/system/config/clear-cache')
}
