import { defineStore } from 'pinia'
import { ref } from 'vue'

import { pluginApi, type TenantPluginInfo } from '@/api/plugin'

const usePluginStore = defineStore('plugin', () => {
    const list = ref<TenantPluginInfo[]>([])
    const loaded = ref(false)

    async function load(force = false): Promise<void> {
        if (loaded.value && !force) return
        const res = await pluginApi.list()
        list.value = res.data
        loaded.value = true
    }

    function byCode(code: string): TenantPluginInfo | null {
        return list.value.find((p) => p.plugin_code === code) ?? null
    }

    function invalidate(): void {
        loaded.value = false
    }

    return { list, loaded, load, byCode, invalidate }
})

export default usePluginStore
