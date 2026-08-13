<script setup lang="ts">
import { useFullscreen } from '@vueuse/core'
import { ElMessage } from 'element-plus'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'
import { type RouteRecordRaw, useRoute, useRouter } from 'vue-router'

import { clearSystemCache } from '@/api/system/config'
import LangSelect from '@/layout/components/header/lang-select.vue'
import UserDropDown from '@/layout/components/header/user-drop-down.vue'
import LayoutSetting from '@/layout/components/setting/drawer.vue'
import useAppStore from '@/store/modules/app.store'
import useSettingStore from '@/store/modules/settings.store'
import useUserStore from '@/store/modules/user.store'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const appStore = useAppStore()
const settingStore = useSettingStore()
const { isFullscreen, toggle: toggleFullscreen } = useFullscreen()
const cacheClearing = ref(false)

function openSetting() {
    settingStore.setSetting({ key: 'showDrawer', value: true })
}

async function handleClearCache() {
    if (cacheClearing.value) return
    cacheClearing.value = true
    try {
        await clearSystemCache()
        ElMessage.success(t('header.clearCacheSuccess'))
        appStore.refreshView()
    } catch {
        ElMessage.error(t('header.clearCacheFailed'))
    } finally {
        cacheClearing.value = false
    }
}

const topRoutes = computed(() =>
    (userStore.routes as RouteRecordRaw[]).filter((r) => !r.meta?.hidden)
)

// 顶部高亮以当前路由为唯一来源：matched[1] 即归属的一级菜单（所有菜单路由都挂在 INDEX_ROUTE 下，
// matched[0] 恒为占位的 INDEX_ROUTE）；常量页（如插件详情）通过 meta.activeMenu 指定高亮项。
const activeKey = computed(() => (route.meta?.activeMenu as string) || route.matched[1]?.path || '')

function findLeaf(rs: RouteRecordRaw[], parentPath: string): string | undefined {
    for (const r of rs) {
        const fullPath = r.path.startsWith('/') ? r.path : `${parentPath}/${r.path}`
        if (!r.children?.length) return fullPath
        const child = findLeaf(r.children, fullPath)
        if (child) return child
    }
}

function onSelect(key: string) {
    const root = topRoutes.value.find((r) => r.path === key)
    if (root?.children?.length) {
        const target = findLeaf(root.children, root.path) || root.path
        router.push(target)
    } else {
        router.push(root?.path || key)
    }
}
</script>

<template>
    <header class="topbar">
        <div class="brand" @click="router.push('/')">
            <img
                v-if="appStore.config.site_logo"
                :src="appStore.getImageUrl(appStore.config.site_logo)"
                class="brand-logo"
            />
            <span class="brand-name">{{ appStore.config.site_name || '元点Saas' }}</span>
        </div>
        <el-menu
            :default-active="activeKey"
            mode="horizontal"
            class="topbar-menu"
            background-color="transparent"
            text-color="#cdd9e6"
            active-text-color="#ffffff"
            @select="onSelect"
        >
            <el-menu-item v-for="r in topRoutes" :key="r.path" :index="r.path">
                {{ r.meta?.title }}
            </el-menu-item>
        </el-menu>
        <div class="actions">
            <div
                class="icon-btn"
                :title="isFullscreen ? t('header.exitFullscreen') : t('header.fullscreen')"
                @click="toggleFullscreen"
            >
                <Icon name="i-svg:maximize" />
            </div>
            <LangSelect />
            <div class="icon-btn" :title="t('header.clearCache')" @click="handleClearCache">
                <Icon name="i-svg:refresh-cw" />
            </div>
            <div class="icon-btn" :title="t('header.settings')" @click="openSetting">
                <Icon name="i-svg:settings" />
            </div>
            <LayoutSetting />
            <UserDropDown />
        </div>
    </header>
</template>

<style scoped>
.topbar {
    display: flex;
    align-items: center;
    height: 60px;
    /* 整体不要左右 padding，让 .brand 自己占满 180px 与左侧 sub-sidebar 对齐；
       右侧 .actions 自己负责右内边距 */
    padding: 0;
    background: linear-gradient(90deg, #1b4fe8 0%, #2c73ff 60%, #3b82f7 100%);
    color: #fff;
    flex-shrink: 0;
    box-shadow:
        0 1px 0 rgba(11, 32, 86, 0.08),
        0 8px 24px -16px rgba(20, 55, 150, 0.55);
    position: sticky;
    top: 0;
    z-index: 20;
}

.brand {
    /* 占据顶部最左侧 180px，恰好与下方 sub-sidebar(180px) 左对齐 */
    width: 180px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 16px;
    box-sizing: border-box;
    cursor: pointer;
    font-weight: 600;
    white-space: nowrap;
}

.brand-logo {
    width: 28px;
    height: 28px;
    object-fit: contain;
}

.brand-name {
    color: #fff;
    font-size: 15px;
}

.topbar-menu {
    flex: 1;
    height: 60px;
    /* topbar 整体去掉 padding 后，给菜单左侧留点呼吸 */
    padding-left: 8px;
    border-bottom: none !important;
}

.actions {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-shrink: 0;
    padding-right: 16px;
}

.icon-btn {
    width: 34px;
    height: 34px;
    border-radius: 4px;
    background: transparent;
    color: rgba(255, 255, 255, 0.85);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.15s;
}
.icon-btn:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
}

/* el-menu 横向条目：把 active 横线锁在 60px 内，避免溢出底部 */
:deep(.el-menu--horizontal.el-menu) {
    height: 60px;
    border-bottom: none !important;
}
:deep(.el-menu--horizontal > .el-menu-item) {
    height: 60px;
    line-height: 60px;
    background-color: transparent !important;
    color: rgba(255, 255, 255, 0.78) !important;
    font-weight: 500;
}
/* 选中态：白色底部横线 + 轻白色背景高亮 */
:deep(.el-menu--horizontal > .el-menu-item.is-active),
:deep(.el-menu--horizontal > .el-menu-item.is-active:focus),
:deep(.el-menu--horizontal > .el-menu-item.is-active:hover) {
    color: #fff !important;
    font-weight: 600;
    background-color: rgba(255, 255, 255, 0.12) !important;
    border-bottom: 3px solid #fff !important;
}
/* hover 态：极轻的白色 overlay */
:deep(.el-menu--horizontal > .el-menu-item:hover),
:deep(.el-menu--horizontal > .el-menu-item:focus) {
    color: #fff !important;
    background-color: rgba(255, 255, 255, 0.1) !important;
}

/* UserDropDown 里 username 是默认深色 div，需显式覆盖 */
:deep(.actions .el-dropdown) {
    color: rgba(255, 255, 255, 0.85);
}
:deep(.actions .el-dropdown:hover) {
    color: #fff;
}
</style>
