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
        <el-card class="plugin-shell" shadow="never">
            <el-tabs v-model="activeTab" class="plugin-tabs">
                <el-tab-pane label="本地插件" name="local">
                    <LocalPluginsPanel ref="localPanelRef" />
                </el-tab-pane>
                <el-tab-pane label="官方市场" name="marketplace">
                    <MarketplacePanel v-if="activeTab === 'marketplace'" @installed="onInstalled" />
                </el-tab-pane>
            </el-tabs>
        </el-card>
    </div>
</template>

<style scoped>
.plugin-shell {
    overflow: hidden;
    background:
        linear-gradient(180deg, rgba(255, 255, 255, 0.96) 0%, rgba(250, 252, 255, 0.96) 100%),
        var(--color-surface);
    box-shadow: 0 12px 32px rgba(31, 41, 55, 0.08);
}

.plugin-shell :deep(.el-card__header) {
    padding: 0 20px;
    border-bottom: 1px solid var(--el-border-color-lighter);
}

.plugin-shell :deep(.el-card__body) {
    padding: 0;
}

.plugin-tabs :deep(.el-tabs__header) {
    margin: 0;
    padding: 0 20px;
    border-bottom: 1px solid var(--el-border-color-lighter);
}

.plugin-tabs :deep(.el-tabs__nav-wrap::after) {
    display: none;
}

.plugin-tabs :deep(.el-tabs__item) {
    height: 58px;
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
