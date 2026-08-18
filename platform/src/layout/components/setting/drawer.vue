<template>
    <div class="setting-drawer">
        <el-drawer
            v-model="showSetting"
            append-to-body
            direction="rtl"
            size="320px"
            :title="$t('settings.title')"
            :show-close="true"
        >
            <div class="h-full flex flex-col overflow-y-auto px-1">
                <div class="setting-section mb-6">
                    <div class="section-title">{{ $t('settings.primaryColor') }}</div>
                    <div class="flex gap-3 mt-3 flex-wrap">
                        <el-tooltip
                            v-for="color in PRESET_COLORS"
                            :key="color"
                            :content="color"
                            placement="top"
                        >
                            <div
                                class="color-swatch"
                                :class="{ active: settingStore.primaryColor === color }"
                                :style="{ backgroundColor: color }"
                                @click="changePrimaryColor(color)"
                            >
                                <el-icon
                                    v-if="settingStore.primaryColor === color"
                                    color="#fff"
                                    :size="14"
                                >
                                    <Check />
                                </el-icon>
                            </div>
                        </el-tooltip>
                    </div>
                </div>

                <div class="setting-section mb-6">
                    <div class="section-title">{{ $t('settings.otherSettings') }}</div>
                    <div class="mt-3 flex flex-col gap-4">
                        <div class="flex justify-between items-center">
                            <span class="text-sm">{{ $t('settings.compact') }}</span>
                            <el-switch v-model="compact" />
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm">{{ $t('settings.sidebarLabels') }}</span>
                            <el-switch v-model="sidebarLabels" />
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm">{{ $t('settings.tagsView') }}</span>
                            <el-switch v-model="openMultipleTabs" />
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-sm">{{ $t('settings.breadcrumb') }}</span>
                            <el-switch v-model="showCrumb" />
                        </div>
                    </div>
                </div>

                <div class="mt-auto pt-4">
                    <el-button class="w-full" @click="resetAll">
                        {{ $t('settings.reset') }}
                    </el-button>
                </div>
            </div>
        </el-drawer>
    </div>
</template>

<script setup lang="ts">
import { Check } from '@element-plus/icons-vue'
import { computed } from 'vue'

import useSettingStore from '@/store/modules/settings.store'
import { PRESET_COLORS } from '@/utils/theme'

const settingStore = useSettingStore()

const showSetting = computed({
    get: () => settingStore.showDrawer,
    set: (value: boolean) => settingStore.setSetting({ key: 'showDrawer', value })
})

const compact = computed({
    get: () => settingStore.compact,
    set: (value: boolean) => settingStore.setSetting({ key: 'compact', value })
})

const sidebarLabels = computed({
    get: () => settingStore.sidebarLabels,
    set: (value: boolean) => settingStore.setSetting({ key: 'sidebarLabels', value })
})

const openMultipleTabs = computed({
    get: () => settingStore.openMultipleTabs,
    set: (value: boolean) => settingStore.setSetting({ key: 'openMultipleTabs', value })
})

const showCrumb = computed({
    get: () => settingStore.showCrumb,
    set: (value: boolean) => settingStore.setSetting({ key: 'showCrumb', value })
})

function changePrimaryColor(color: string) {
    settingStore.setSetting({ key: 'primaryColor', value: color })
}

function resetAll() {
    settingStore.resetTheme()
}
</script>

<style lang="scss" scoped>
.section-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--ink-800);
}

.color-swatch {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid transparent;
    transition:
        transform 0.15s,
        border-color 0.15s;

    &:hover {
        transform: scale(1.08);
    }

    &.active {
        border-color: var(--ink-200);
        box-shadow: 0 0 0 2px #fff inset;
    }
}
</style>
