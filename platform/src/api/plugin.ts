import type { PageResult, PluginInfo, PluginUploadResp } from '@/types/api'
import { myRequest } from '@/utils/request'

/**
 * 插件管理 API
 */
export const pluginApi = {
    list(params: { page?: number; limit?: number; kind?: 'app' | 'plugin' }) {
        return myRequest.get<PageResult<PluginInfo>>('/platformapi/plugins', { params })
    },

    show(id: number) {
        return myRequest.get<PluginInfo>(`/platformapi/plugins/${id}`)
    },

    upload(file: File) {
        const form = new FormData()
        form.append('plugin', file)
        return myRequest.post<PluginUploadResp>('/platformapi/plugins/upload', form, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })
    },

    install(id: number) {
        return myRequest.post<{ id: number; code: string; version: string; status: number }>(
            `/platformapi/plugins/${id}/install`
        )
    },

    /**
     * 直接从磁盘登记并安装一个本地插件（git 一起带的，例如 mall / points-exchange）。
     * 列表里 id===0 的「未安装」虚拟行走这里。
     */
    installFromDisk(code: string) {
        return myRequest.post<{ id: number; code: string; version: string; status: number }>(
            '/platformapi/plugins/install-from-disk',
            { code }
        )
    },

    uninstall(id: number) {
        return myRequest.post<{ id: number; code: string; uninstalled: boolean }>(
            `/platformapi/plugins/${id}/uninstall`
        )
    },

    /** 平台级临时禁用（保留代码与数据，随时可启用） */
    disable(id: number) {
        return myRequest.post<{ id: number; code: string; status: number }>(
            `/platformapi/plugins/${id}/disable`
        )
    },

    enable(id: number) {
        return myRequest.post<{ id: number; code: string; status: number }>(
            `/platformapi/plugins/${id}/enable`
        )
    },

    upgrade(id: number, file: File) {
        const form = new FormData()
        form.append('plugin', file)
        return myRequest.post<{ id: number; from: string; to: string; status: number }>(
            `/platformapi/plugins/${id}/upgrade`,
            form,
            { headers: { 'Content-Type': 'multipart/form-data' } }
        )
    },

    purgeData(id: number) {
        return myRequest.delete<{ id: number; code: string; purged: boolean }>(
            `/platformapi/plugins/${id}/data?force=true`
        )
    }
}
