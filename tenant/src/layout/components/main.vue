<template>
    <main class="main-wrap h-full">
        <el-scrollbar>
            <div class="p-5">
                <router-view v-if="isRouteShow" v-slot="{ Component, route }">
                    <!-- 不要在这里包 <transition name="fade" mode="out-in">：项目里没有定义 .fade-* 的 CSS，
                         mode=out-in 又强制等待 leave 完成。一旦某个被切走的组件存在 Fragment 根（多根模板），
                         Vue 的 whenTransitionEnds 拿不到根元素来判断有无过渡，leave 钩子悬空 → 下一个组件
                         enter 永远不触发 → 整个主内容空白。直接干掉过渡层，导航即时切换更稳。 -->
                    <keep-alive :include="includeList" :max="20">
                        <component :is="Component" :key="route.fullPath" />
                    </keep-alive>
                </router-view>
            </div>
        </el-scrollbar>
    </main>
</template>

<script setup lang="ts">
import useAppStore from '@/store/modules/app.store'
import useTabsStore from '@/store/modules/multipleTabs.store'
import useSettingStore from '@/store/modules/settings.store'

const appStore = useAppStore()
const tabsStore = useTabsStore()
const settingStore = useSettingStore()

// 是否显示路由内容
const isRouteShow = computed(() => appStore.isRouteShow)

// 需要缓存的组件列表
const includeList = computed(() => (settingStore.openMultipleTabs ? tabsStore.getCacheTabList : []))
</script>
