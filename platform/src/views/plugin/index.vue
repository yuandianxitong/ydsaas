<script setup lang="ts">
import { nextTick, ref } from 'vue'

import LocalPluginsPanel from './components/LocalPluginsPanel.vue'
import MarketplacePanel from './components/MarketplacePanel.vue'

const activeTab = ref('local')
const localPanelRef = ref<InstanceType<typeof LocalPluginsPanel> | null>(null)

// 官方市场安装成功后：切回「本地插件」并刷新列表，避免切 tab 显示旧的空数据
async function onInstalled() {
    activeTab.value = 'local'
    await nextTick()
    localPanelRef.value?.refresh()
}
</script>

<template>
    <div class="plugin-container">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('plugin.title') }}</div>
                <div class="page-desc">{{ $t('plugin.desc') }}</div>
            </div>
        </div>

        <div class="set-card plugin-shell">
            <el-tabs v-model="activeTab" class="plugin-tabs">
                <el-tab-pane :label="$t('plugin.local')" name="local">
                    <LocalPluginsPanel ref="localPanelRef" />
                </el-tab-pane>
                <el-tab-pane :label="$t('plugin.marketplace')" name="marketplace">
                    <MarketplacePanel v-if="activeTab === 'marketplace'" @installed="onInstalled" />
                </el-tab-pane>
            </el-tabs>
        </div>
    </div>
</template>

<style scoped>
.plugin-shell {
    overflow: hidden;
}

.plugin-tabs :deep(.el-tabs__header) {
    margin: 0;
    padding: 0 20px;
    border-bottom: 1px solid var(--ink-100);
}

.plugin-tabs :deep(.el-tabs__nav-wrap::after) {
    display: none;
}

.plugin-tabs :deep(.el-tabs__item) {
    height: 52px;
    padding: 0 18px;
    font-weight: 500;
}

.plugin-tabs :deep(.el-tabs__active-bar) {
    height: 3px;
    border-radius: 3px 3px 0 0;
}

.plugin-tabs :deep(.el-tabs__content) {
    padding: 0;
}
</style>
