<template>
    <header class="header">
        <el-alert
            v-if="globalLicenseAlert"
            :type="globalLicenseAlert.level"
            :closable="false"
            show-icon
            class="license-banner"
        >
            <span>{{ globalLicenseAlert.message }}</span>
        </el-alert>
        <div
            class="navbar flex items-center border-b border-b-solid border-b-[var(--color-divider)] px-4 py-2.5"
        >
            <div class="flex-1 flex">
                <div v-if="!isMobile && settingStore.showCrumb" class="flex items-center px-2">
                    <breadcrumb />
                </div>
            </div>
            <div class="flex items-center">
                <div
                    v-if="!isMobile"
                    class="bg-[var(--gray-100)] text-[var(--color-text-secondary)] w-[34px] h-[34px] rounded-full flex items-center justify-center cursor-pointer mr-4"
                    @click="toggleFullscreen"
                >
                    <el-tooltip
                        effect="dark"
                        :content="
                            isFullscreen ? $t('header.exitFullscreen') : $t('header.fullscreen')
                        "
                        placement="bottom"
                    >
                        <Icon name="i-svg:maximize"></Icon>
                    </el-tooltip>
                </div>
                <lang-select />
                <div
                    class="bg-[var(--gray-100)] text-[var(--color-text-secondary)] w-[34px] h-[34px] rounded-full flex items-center justify-center cursor-pointer mr-4"
                    @click="handleClearCache"
                >
                    <el-tooltip
                        class="box-item"
                        effect="dark"
                        :content="$t('header.clearCache')"
                        placement="bottom"
                    >
                        <Icon name="i-svg:refresh-cw"></Icon>
                    </el-tooltip>
                </div>
                <div
                    class="bg-[var(--gray-100)] text-[var(--color-text-secondary)] w-[34px] h-[34px] rounded-full flex items-center justify-center cursor-pointer mr-4"
                    @click="openSetting"
                >
                    <el-tooltip effect="dark" :content="$t('header.settings')" placement="bottom">
                        <Icon name="i-svg:settings"></Icon>
                    </el-tooltip>
                </div>
                <layout-setting />
                <div class="navbar-item">
                    <user-drop-down />
                </div>
            </div>
        </div>
        <multiple-tabs v-if="settingStore.openMultipleTabs" />
    </header>
</template>

<script setup lang="ts">
import { useFullscreen } from '@vueuse/core'
import { ElMessage } from 'element-plus'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { marketplaceApi } from '@/api/marketplace'
import useAppStore from '@/store/modules/app.store'
import useSettingStore from '@/store/modules/settings.store'
import myRequest from '@/utils/request'

import LayoutSetting from '../setting/drawer.vue'
import Breadcrumb from './breadcrumb.vue'
import LangSelect from './lang-select.vue'
import MultipleTabs from './multiple-tabs.vue'
import UserDropDown from './user-drop-down.vue'

const clearSystemCache = async (): Promise<void> => {
    await myRequest.post('/platformapi/system/config/clear-cache')
}

const { t } = useI18n()
const appStore = useAppStore()
const isMobile = computed(() => appStore.isMobile)
const settingStore = useSettingStore()
const { isFullscreen, toggle: toggleFullscreen } = useFullscreen()
const cacheClearing = ref(false)

const openSetting = () => {
    settingStore.setSetting({ key: 'showDrawer', value: true })
}

const handleClearCache = async () => {
    if (cacheClearing.value) return
    cacheClearing.value = true
    try {
        await clearSystemCache()
        ElMessage.success(t('header.clearCacheSuccess'))
        // 完整刷新页面，确保侧边栏菜单、权限等前端缓存也更新
        setTimeout(() => window.location.reload(), 500)
    } catch {
        ElMessage.error(t('header.clearCacheFailed'))
    } finally {
        cacheClearing.value = false
    }
}

// v2.9.0: 全局 license / token 警告 banner
// 拉一次 connection + marketplace apps 列表，取最严重态展示
const globalLicenseAlert = ref<{ level: 'warning' | 'error'; message: string } | null>(null)

async function loadGlobalLicenseAlert() {
    try {
        const [conns, apps] = await Promise.all([
            marketplaceApi.listConnections().catch(() => null),
            marketplaceApi.apps().catch(() => null)
        ])
        let level: 'warning' | 'error' | null = null
        const parts: string[] = []

        const expiredCount = ((apps as any)?.data?.data ?? []).filter(
            (a: any) => a?.license_status === 'expired'
        ).length
        const graceCount = ((apps as any)?.data?.data ?? []).filter(
            (a: any) => a?.license_status === 'grace'
        ).length
        const overdueConn = ((conns as any)?.data?.data ?? []).some(
            (c: any) => c?.token_rotate_overdue
        )

        if (expiredCount > 0) {
            level = 'error'
            parts.push(`${expiredCount} 个官方应用授权已过期，写操作被拒绝`)
        } else if (graceCount > 0) {
            level = 'warning'
            parts.push(`${graceCount} 个官方应用处于授权宽限期，请尽快续费`)
        }
        if (overdueConn) {
            level = 'error'
            parts.push('Site 凭证轮换失败')
        }

        globalLicenseAlert.value = level ? { level, message: parts.join(' / ') } : null
    } catch {
        // silent — banner 不是核心功能，权限不够 / 网络失败都静默
    }
}

onMounted(() => {
    loadGlobalLicenseAlert()
})
</script>

<style lang="scss">
.navbar {
    height: var(--navbar-height);
}
.license-banner {
    border-radius: 0;
    padding: 8px 16px;
    font-size: 13px;
}
</style>
