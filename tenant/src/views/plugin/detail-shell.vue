<script setup lang="ts">
import { computed, defineAsyncComponent, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'

import usePluginStore from '@/store/modules/plugin.store'

const route = useRoute()
const router = useRouter()
const store = usePluginStore()

const code = computed(() => route.params.code as string)
const panel = computed(() => route.params.panel as string | undefined)

const plugin = computed(() => store.byCode(code.value))

// glob 拿到的每个值是 lazy loader (() => Promise<Module>)，必须用 defineAsyncComponent
// 包装后才能给 <component :is> — 直接传 loader 会被 Vue 当成字符串渲染成
// "[object Promise]"。
const panelModules = import.meta.glob('../plugins/*/*.vue')

const panelComponent = computed(() => {
    const p = plugin.value
    if (!p || !panel.value) return null
    const matched = p.panels.find((x) => x.code === panel.value)
    if (!matched) return null
    const key = Object.keys(panelModules).find((k) =>
        k.endsWith(`/plugins/${p.plugin_code}/${matched.component}.vue`)
    )
    if (!key) return null
    return defineAsyncComponent(panelModules[key] as () => Promise<any>)
})

// 进入插件时若未指定 panel，redirect 到第一个
watch(
    [plugin, panel],
    ([p, pn]) => {
        if (p && !pn && p.panels.length) {
            router.replace(`/plugin/${p.plugin_code}/${p.panels[0].code}`)
        }
    },
    { immediate: true }
)

onMounted(() => store.load())
</script>

<template>
    <!-- v2.7.6：去掉 shell-head（与侧栏/面包屑里的插件名重复）；shell-body 不再加内边距，
         由各 panel 自己控制（与 mall 等 menu-style 应用页一致） -->
    <div class="plugin-detail-shell">
        <div class="shell-body">
            <component :is="panelComponent" v-if="panelComponent" :plugin-code="code" />
            <el-empty v-else-if="plugin" description="该功能页未实现" />
            <el-empty v-else description="插件不存在或未授权" />
        </div>
    </div>
</template>

<style scoped>
.plugin-detail-shell { display: flex; flex-direction: column; height: 100%; }
.shell-body { flex: 1; overflow: auto; min-height: 0; }
</style>
