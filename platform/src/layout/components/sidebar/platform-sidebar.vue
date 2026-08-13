<template>
    <div class="platform-sidebar h-full flex">
        <!-- 一级菜单（保持原有样式） -->
        <div class="first-level h-full flex flex-col">
            <!-- Logo -->
            <div
                class="flex items-center justify-center cursor-pointer h-[50px]"
                @click="router.push('/')"
            >
                <img :src="logoSrc" class="w-[36px] h-[36px] rounded-lg" />
            </div>

            <!-- Divider -->
            <div class="border-t border-white/8 mx-3"></div>

            <!-- Menu Items -->
            <el-scrollbar class="flex-1 mt-1">
                <div class="flex flex-col">
                    <platform-menu-item
                        v-for="item in topRoutes"
                        :key="item.path"
                        :route="item"
                        :active="selectedFirst === item.path"
                        @click="onFirstSelect(item)"
                    />
                </div>
            </el-scrollbar>
        </div>

        <!-- 二级菜单面板 -->
        <aside v-if="secondRoutes.length" class="second-level h-full flex flex-col">
            <div v-if="selectedFirstRoute" class="second-menu-header">
                <Icon
                    v-if="selectedFirstRoute.meta?.icon"
                    :name="selectedFirstRoute.meta.icon"
                    size="18"
                    class="text-[var(--el-color-primary)]"
                />
                <span>{{
                    translateRouteTitle(selectedFirstRoute.meta?.title, selectedFirstRoute.name)
                }}</span>
            </div>
            <el-scrollbar class="flex-1">
                <el-menu
                    :default-active="currentFullPath"
                    router
                    unique-opened
                    class="h-full !border-none"
                    background-color="transparent"
                    text-color="var(--color-text-primary)"
                    @select="onSecondSelect"
                >
                    <template v-for="item in secondRoutes" :key="item.path">
                        <el-menu-item
                            v-if="!visibleChildren(item).length"
                            :index="resolvePath(item.path)"
                            class="flex items-center"
                        >
                            <Icon v-if="item.meta?.icon" :name="item.meta.icon" size="20" />
                            <span class="ml-2">{{
                                translateRouteTitle(item.meta?.title, item.name)
                            }}</span>
                        </el-menu-item>
                        <el-sub-menu v-else :index="resolvePath(item.path)">
                            <template #title>
                                <Icon v-if="item.meta?.icon" :name="item.meta.icon" size="20" />
                                <span class="ml-2">{{
                                    translateRouteTitle(item.meta?.title, item.name)
                                }}</span>
                            </template>
                            <el-menu-item
                                v-for="sub in visibleChildren(item)"
                                :key="resolvePath(sub.path)"
                                :index="resolvePath(sub.path)"
                            >
                                <span>{{ translateRouteTitle(sub.meta?.title, sub.name) }}</span>
                            </el-menu-item>
                        </el-sub-menu>
                    </template>
                </el-menu>
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
.first-level {
    width: 80px;
    background-color: var(--color-sidebar-bg);
    overflow: hidden;
    flex-shrink: 0;
}

.second-level {
    width: 180px;
    background-color: var(--color-surface, #f8f9fb);
    box-shadow: 1px 1px 4px rgba(0, 21, 41, 0.08);
    flex-shrink: 0;
}

.second-menu-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 16px 20px;
    font-weight: 600;
    font-size: 15px;
    color: var(--el-text-color-primary);
    border-bottom: 1px solid var(--el-border-color-light, #f0f0f0);
    flex-shrink: 0;
}
</style>
