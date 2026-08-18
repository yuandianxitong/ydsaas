<template>
    <div class="platform-sidebar h-full flex">
        <!-- 一级菜单（保持原有样式） -->
        <div class="first-level h-full flex flex-col">
            <!-- Logo -->
            <div
                class="sidebar-logo flex items-center justify-center cursor-pointer flex-shrink-0"
                @click="router.push('/')"
            >
                <img :src="logoSrc" class="w-[42px] h-[42px] object-contain" />
            </div>

            <nav class="nav flex flex-col flex-1 px-2 py-3 gap-0.5 overflow-y-auto">
                <platform-menu-item
                    v-for="item in topRoutes"
                    :key="item.path"
                    :route="item"
                    :active="selectedFirst === item.path"
                    @click="onFirstSelect(item)"
                />
            </nav>
        </div>

        <!-- 二级菜单面板 -->
        <aside v-if="secondRoutes.length" class="second-level h-full flex flex-col">
            <div v-if="selectedFirstRoute" class="sub-head flex items-center px-3.5 flex-shrink-0">
                <span>{{
                    translateRouteTitle(selectedFirstRoute.meta?.title, selectedFirstRoute.name)
                }}</span>
            </div>
            <el-scrollbar class="flex-1">
                <div class="sub-list">
                    <el-menu
                        :default-active="currentFullPath"
                        router
                        unique-opened
                        class="h-full !border-none"
                        background-color="transparent"
                        text-color="var(--ink-600)"
                        @select="onSecondSelect"
                    >
                        <template v-for="item in secondRoutes" :key="item.path">
                            <el-menu-item
                                v-if="!visibleChildren(item).length"
                                :index="resolvePath(item.path)"
                                class="sub-item"
                            >
                                <span class="sub-dot" />
                                <span class="sub-label">{{
                                    translateRouteTitle(item.meta?.title, item.name)
                                }}</span>
                            </el-menu-item>
                            <el-sub-menu v-else :index="resolvePath(item.path)">
                                <template #title>
                                    <span class="sub-label">{{
                                        translateRouteTitle(item.meta?.title, item.name)
                                    }}</span>
                                </template>
                                <el-menu-item
                                    v-for="sub in visibleChildren(item)"
                                    :key="resolvePath(sub.path)"
                                    :index="resolvePath(sub.path)"
                                    class="sub-item"
                                >
                                    <span class="sub-dot" />
                                    <span class="sub-label">{{
                                        translateRouteTitle(sub.meta?.title, sub.name)
                                    }}</span>
                                </el-menu-item>
                            </el-sub-menu>
                        </template>
                    </el-menu>
                </div>
            </el-scrollbar>
        </aside>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { type RouteRecordRaw, useRoute, useRouter } from 'vue-router'

import defaultLogo from '@/assets/images/logo.png'
import useAppStore from '@/store/modules/app.store'
import useUserStore from '@/store/modules/user.store'
import { translateRouteTitle } from '@/utils/i18n'

import PlatformMenuItem from './platform-menu-item.vue'

const router = useRouter()
const route = useRoute()
const appStore = useAppStore()
const userStore = useUserStore()

const routes = computed(() => userStore.routes as RouteRecordRaw[])

const logoSrc = computed(() => {
    const logo = appStore.config.site_logo
    return logo ? appStore.getImageUrl(logo) : defaultLogo
})

// 顶级路由（过滤 hidden）
const topRoutes = computed(() => routes.value.filter((r) => !r.meta?.hidden))

// 根据当前路由自动匹配选中的一级菜单
const topPaths = computed(() => topRoutes.value.map((r) => r.path))
const selectedFirst = computed<string>(() => {
    const match = route.matched.find((m) => topPaths.value.includes(m.path))
    return match ? match.path : topPaths.value[0]
})

// 选中的一级路由对象
const selectedFirstRoute = computed(() => {
    return routes.value.find((r) => r.path === selectedFirst.value)
})

// 二级路由列表
const secondRoutes = computed(() => {
    const parent = routes.value.find((r) => r.path === selectedFirst.value)
    return (parent?.children || []).filter((r) => !r.meta?.hidden)
})

const currentFullPath = computed(() => route.fullPath)

// 获取可见子路由
function visibleChildren(item: RouteRecordRaw): RouteRecordRaw[] {
    return (item.children || []).filter((r) => !r.meta?.hidden)
}

// 递归查找第一个叶子页面
function findFirstLeafPath(r: RouteRecordRaw, parentPath: string): string | null {
    const fullPath = r.path.startsWith('/') ? r.path : `${parentPath}/${r.path}`
    if (r.meta?.type === 2 && !r.meta?.hidden) {
        return fullPath
    }
    const children = (r.children || []).filter((c) => !c.meta?.hidden)
    for (const child of children) {
        const found = findFirstLeafPath(child, fullPath)
        if (found) return found
    }
    return null
}

// 点击一级菜单
function onFirstSelect(item: RouteRecordRaw) {
    const leafPath = findFirstLeafPath(item, '')
    if (leafPath) {
        router.push(leafPath)
    } else {
        router.push(item.path)
    }
}

// 点击二级菜单
function onSecondSelect(path: string) {
    router.push(path)
}

// 拼接子路由路径
function resolvePath(p: string) {
    return p.startsWith('/') ? p : `${selectedFirst.value}/${p}`
}
</script>

<style lang="scss" scoped>
.sidebar-logo {
    height: var(--header-h);
    border-bottom: 1px solid rgba(255, 255, 255, 0.06);
}

.first-level {
    width: var(--sidebar-w);
    background: #0f172a;
    overflow: hidden;
    flex-shrink: 0;
}

.second-level {
    width: var(--sidebar-sub-w, 180px);
    background: #fff;
    border-right: 1px solid var(--ink-100);
    flex-shrink: 0;
}

.nav {
    &::-webkit-scrollbar {
        width: 0;
    }
}

.sub-head {
    height: var(--header-h);
    font-size: 13px;
    font-weight: 600;
    color: var(--ink-900);
    border-bottom: 1px solid var(--ink-100);
    letter-spacing: 0.5px;
}

.sub-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px;

    &::-webkit-scrollbar {
        width: 4px;
    }

    &::-webkit-scrollbar-thumb {
        background: var(--ink-200);
        border-radius: 2px;
    }
}

:deep(.el-menu-item.sub-item) {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px !important;
    font-size: 13px;
    color: var(--ink-600);
    border-radius: 4px;
    height: auto !important;
    line-height: normal !important;
    cursor: pointer;
    transition: all 0.15s;

    &:hover {
        background: var(--ink-50) !important;
        color: var(--ink-800) !important;
    }

    &.is-active {
        background: var(--brand-50) !important;
        color: var(--brand-600) !important;
        font-weight: 500;

        .sub-dot {
            background: var(--brand-500);
            box-shadow: 0 0 0 2px var(--brand-100);
        }
    }
}

.sub-dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--ink-300);
    flex-shrink: 0;
}

.sub-label {
    flex: 1;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

:deep(.el-sub-menu .el-sub-menu__title) {
    padding: 8px 12px !important;
    font-size: 13px;
    color: var(--ink-700);
    font-weight: 500;
    height: auto;
    line-height: normal;
    border-radius: 4px;

    &:hover {
        background: var(--ink-50) !important;
        color: var(--ink-800) !important;
    }
}

:deep(.el-menu--inline) {
    background: transparent !important;
}
</style>
