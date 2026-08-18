// src/modules/setting/setting.store.ts

// eslint-disable-next-line vue/prefer-import-from-vue
import { isObject } from '@vue/shared'
import { defineStore } from 'pinia'

import { defaultSetting } from '@/constants'
import { SETTING_KEY } from '@/constants/cache'
import cache from '@/utils/cache'
import { applySettings, COLOR_MAP } from '@/utils/theme'

export interface SettingState {
    showDrawer: boolean
    showCrumb: boolean
    showLogo: boolean
    isUniqueOpened: boolean
    sideWidth: number
    sideTheme: string
    openMultipleTabs: boolean
    primaryColor: string
    compact: boolean
    sidebarLabels: boolean
}

function resolvePrimaryColor(cached: Record<string, unknown>): string {
    const fromNew = typeof cached.primaryColor === 'string' ? cached.primaryColor : ''
    const fromOld = typeof cached.theme === 'string' ? cached.theme : ''
    if (fromNew && COLOR_MAP[fromNew]) return fromNew
    if (fromOld && COLOR_MAP[fromOld]) return fromOld
    return defaultSetting.primaryColor
}

export const useSettingStore = defineStore('setting', {
    state: (): SettingState => {
        const storageSetting = cache.get(SETTING_KEY)
        const initialState: SettingState = {
            showDrawer: false,
            ...defaultSetting
        }

        if (isObject(storageSetting)) {
            const cached = storageSetting as Record<string, unknown>
            if (typeof cached.showCrumb === 'boolean') initialState.showCrumb = cached.showCrumb
            if (typeof cached.showLogo === 'boolean') initialState.showLogo = cached.showLogo
            if (typeof cached.isUniqueOpened === 'boolean') {
                initialState.isUniqueOpened = cached.isUniqueOpened
            }
            if (typeof cached.sideWidth === 'number') initialState.sideWidth = cached.sideWidth
            if (typeof cached.sideTheme === 'string') initialState.sideTheme = cached.sideTheme
            if (typeof cached.openMultipleTabs === 'boolean') {
                initialState.openMultipleTabs = cached.openMultipleTabs
            }
            if (typeof cached.compact === 'boolean') initialState.compact = cached.compact
            if (typeof cached.sidebarLabels === 'boolean') {
                initialState.sidebarLabels = cached.sidebarLabels
            }
            initialState.primaryColor = resolvePrimaryColor(cached)
        }

        return initialState
    },
    actions: {
        setSetting<K extends keyof SettingState>(data: { key: K; value: SettingState[K] }): void {
            const { key, value } = data
            if (Object.prototype.hasOwnProperty.call(this, key)) {
                this.$patch({ [key]: value } as Partial<SettingState>)
            }
            const settings: Partial<SettingState> = { ...this.$state }
            delete (settings as { showDrawer?: boolean }).showDrawer
            cache.set(SETTING_KEY, settings)
            if (key === 'primaryColor' || key === 'compact' || key === 'sidebarLabels') {
                this.apply()
            }
        },

        apply(): void {
            applySettings({
                primaryColor: this.primaryColor,
                compact: this.compact,
                sidebarLabels: this.sidebarLabels
            })
        },

        resetTheme(): void {
            const defaults = defaultSetting as Partial<SettingState>
            ;(Object.keys(defaults) as (keyof SettingState)[]).forEach((key) => {
                const value = defaults[key]
                if (value !== undefined) {
                    ;(this as unknown as Record<string, unknown>)[key as string] = value
                }
            })
            cache.remove(SETTING_KEY)
            this.apply()
        }
    }
})

export default useSettingStore
