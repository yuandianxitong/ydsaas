<template>
    <div class="page-settings-panel">
        <div class="page-settings-panel__item">
            <label class="page-settings-panel__label">页面标题</label>
            <el-input
                v-model="pageTitle"
                placeholder="请输入页面标题"
                maxlength="64"
                show-word-limit
                :disabled="isMember"
            />
            <p v-if="isMember" class="page-settings-panel__tip">个人中心使用自定义导航，标题不可编辑</p>
        </div>
        <div class="page-settings-panel__item">
            <label class="page-settings-panel__label">显示头部导航栏</label>
            <el-switch v-model="showHeader" :disabled="isMember" />
            <p class="page-settings-panel__tip">
                {{
                    isMember
                        ? '个人中心不显示 DIY 标题栏（与 C 端一致）'
                        : '仅影响 C 端；编辑器始终保留标题栏以便点击进入本设置'
                }}
            </p>
        </div>
        <div class="page-settings-panel__item">
            <label class="page-settings-panel__label">背景颜色</label>
            <div class="page-settings-panel__color-row">
                <el-color-picker v-model="bgColor" show-alpha />
                <el-input
                    v-model="bgColor"
                    placeholder="如 #ffffff"
                    style="flex: 1; margin-left: 8px"
                />
            </div>
        </div>
        <div class="page-settings-panel__item">
            <label class="page-settings-panel__label">背景图片</label>
            <ImageSelect v-model="bgImage" />
        </div>

        <template v-if="!isMember">
            <el-divider class="page-settings-panel__divider">弹窗广告</el-divider>

            <div class="page-settings-panel__item">
                <label class="page-settings-panel__label">是否显示</label>
                <el-switch v-model="popupEnabled" />
            </div>
            <template v-if="popupEnabled">
                <div class="page-settings-panel__item">
                    <label class="page-settings-panel__label">显示类型</label>
                    <el-radio-group v-model="popupDisplayType">
                        <el-radio value="first">首次弹出</el-radio>
                        <el-radio value="every">每次弹出</el-radio>
                    </el-radio-group>
                    <p class="page-settings-panel__tip">
                        {{
                            popupDisplayType === 'first'
                                ? '同一设备上仅首次进入页面时弹出'
                                : '每次进入页面都会弹出'
                        }}
                    </p>
                </div>
                <div class="page-settings-panel__item">
                    <label class="page-settings-panel__label">广告图</label>
                    <ImageSelect v-model="popupImage" />
                </div>
                <div class="page-settings-panel__item">
                    <label class="page-settings-panel__label">广告链接</label>
                    <LinkPicker v-model="popupLink" />
                </div>
            </template>
        </template>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import ImageSelect from '@/components/ImageSelect/index.vue'

import { useEditor } from '../useEditor'
import LinkPicker from './LinkPicker.vue'

const props = withDefaults(defineProps<{ pageKey?: string }>(), { pageKey: 'home' })
const isMember = computed(() => props.pageKey === 'member')

const { pageTitle, pageSettings, updatePageSettings } = useEditor()

const bgColor = computed({
    get: () => pageSettings.value.background_color || '',
    set: (val: string) => updatePageSettings({ background_color: val || '' })
})

const bgImage = computed({
    get: () => pageSettings.value.background_image || '',
    set: (val: string) => updatePageSettings({ background_image: val || '' })
})

const showHeader = computed({
    get: () => (isMember.value ? false : pageSettings.value.show_header !== false),
    set: (val: boolean) => {
        if (isMember.value) return
        updatePageSettings({ show_header: val })
    }
})

function ensurePopupAd() {
    if (!pageSettings.value.popup_ad) {
        updatePageSettings({
            popup_ad: { enabled: false, display_type: 'first', image: '', link: '' }
        })
    }
}

const popupEnabled = computed({
    get: () => !!pageSettings.value.popup_ad?.enabled,
    set: (val: boolean) => {
        ensurePopupAd()
        updatePageSettings({
            popup_ad: { ...pageSettings.value.popup_ad!, enabled: val }
        })
    }
})

const popupDisplayType = computed({
    get: () => pageSettings.value.popup_ad?.display_type || 'first',
    set: (val: 'first' | 'every') => {
        ensurePopupAd()
        updatePageSettings({
            popup_ad: { ...pageSettings.value.popup_ad!, display_type: val }
        })
    }
})

const popupImage = computed({
    get: () => pageSettings.value.popup_ad?.image || '',
    set: (val: string) => {
        ensurePopupAd()
        updatePageSettings({
            popup_ad: { ...pageSettings.value.popup_ad!, image: val }
        })
    }
})

const popupLink = computed({
    get: () => pageSettings.value.popup_ad?.link || '',
    set: (val: string) => {
        ensurePopupAd()
        updatePageSettings({
            popup_ad: { ...pageSettings.value.popup_ad!, link: val }
        })
    }
})
</script>

<style lang="scss" scoped>
.page-settings-panel {
    padding: 16px;
}
.page-settings-panel__item {
    margin-bottom: 16px;
}
.page-settings-panel__label {
    display: block;
    font-size: 13px;
    color: #606266;
    margin-bottom: 8px;
}
.page-settings-panel__color-row {
    display: flex;
    align-items: center;
}
.page-settings-panel__divider {
    margin: 8px 0 16px;
    font-size: 12px;
    color: #909399;
}
.page-settings-panel__tip {
    margin: 6px 0 0;
    font-size: 12px;
    color: #909399;
    line-height: 1.5;
}
</style>
