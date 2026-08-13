<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { type RouteRecordRaw, useRoute, useRouter } from 'vue-router'

import usePluginStore from '@/store/modules/plugin.store'
import useUserStore from '@/store/modules/user.store'

import { buildSubNavGroups, type SubNavRoute } from './subNavGroups'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()
const pluginStore = usePluginStore()

const subRoutes = computed<RouteRecordRaw[]>(() => {
    const m = route.path.match(/^\/plugin\/([^/]+)\/[^/]+$/)
    if (m) {
        const code = m[1]
        if (code !== 'apps' && code !== 'plugins') {
            const p = pluginStore.byCode(code)
            if (p && p.panels.length) {
                return p.panels.map((panel) => ({
                    path: `/plugin/${code}/${panel.code}`,
                    meta: { title: panel.name, icon: panel.icon, hidden: false } as any,
                    children: []
                })) as RouteRecordRaw[]
            }
        }
    }
    // 归属的一级菜单 = matched[1]（matched[0] 恒为占位 INDEX_ROUTE）；常量页用 meta.activeMenu 指定。
    // 首页(/workbench) 为无子项的叶子菜单，root.children 为空 → hasMenu=false → 侧栏自动隐藏。
    const top = (route.meta?.activeMenu as string) || route.matched[1]?.path
    const root = (userStore.routes as RouteRecordRaw[]).find((r) => r.path === top)
    return root?.children?.filter((c) => !c.meta?.hidden) || []
})

const groups = computed(() => buildSubNavGroups(subRoutes.value as unknown as SubNavRoute[]))
const hasMenu = computed(() => groups.value.some((g) => g.items.length))
const activeMenu = computed(() => (route.meta?.activeMenu as string) || route.path)

onMounted(() => pluginStore.load())
</script>

<template>
    <aside v-if="hasMenu" class="sub-sidebar">
        <el-scrollbar>
            <div class="sub-groups">
                <div v-for="(g, gi) in groups" :key="gi" class="sub-group">
                    <div v-if="g.title" class="group-title">
                        <span class="group-bar" />{{ g.title }}
                    </div>
                    <div class="group-grid">
                        <div
                            v-for="it in g.items"
                            :key="it.path"
                            class="grid-item"
                            :class="{ active: activeMenu === it.path }"
                            @click="router.push(it.path)"
                        >
                            <span class="item-label">{{ it.title }}</span>
                            <span v-if="it.badge" class="item-badge">{{ it.badge }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </el-scrollbar>
    </aside>
</template>

<style scoped>
.sub-sidebar {
    width: 184px;
    height: 100%;
    background: #fff;
    border-right: 1px solid var(--el-border-color-lighter);
    flex-shrink: 0;
}
.sub-groups {
    display: flex;
    flex-direction: column;
    gap: 18px;
    padding: 16px 12px 24px;
}
.group-title {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 0 6px 8px;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--ink, #11151c);
    letter-spacing: 0.02em;
}
.group-bar {
    width: 3px;
    height: 12px;
    border-radius: 2px;
    background: var(--el-color-primary);
}
.group-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 2px;
}
.grid-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 6px;
    padding: 6px 8px;
    border-radius: 4px;
    font-size: 12.5px;
    font-weight: 500;
    color: var(--ink-2, #4b5366);
    cursor: pointer;
    transition:
        background 0.15s,
        color 0.15s;
}
.grid-item:hover {
    background: var(--color-bg-page, #f4f6fa);
    color: var(--ink, #11151c);
}
.grid-item.active {
    background: var(--el-color-primary-light-9);
    color: var(--el-color-primary);
    font-weight: 600;
}
.item-badge {
    min-width: 16px;
    height: 14px;
    padding: 0 4px;
    border-radius: 4px;
    background: #ff4d4f;
    color: #fff;
    font-size: 10px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
</style>
