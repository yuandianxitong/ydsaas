<template>
    <el-dropdown trigger="click" @command="handleChange">
        <div
            class="lang-trigger w-[34px] h-[34px] rounded flex items-center justify-center cursor-pointer"
        >
            <el-tooltip effect="dark" :content="currentLabel" placement="bottom">
                <Icon name="i-svg:languages" :size="18" />
            </el-tooltip>
        </div>
        <template #dropdown>
            <el-dropdown-menu>
                <el-dropdown-item
                    v-for="item in localeOptions"
                    :key="item.value"
                    :command="item.value"
                    :class="{ 'is-active': currentLocale === item.value }"
                >
                    {{ item.label }}
                </el-dropdown-item>
            </el-dropdown-menu>
        </template>
    </el-dropdown>
</template>

<script setup lang="ts">
import { ElMessage } from 'element-plus'
import { computed, nextTick } from 'vue'
import { useI18n } from 'vue-i18n'

import { getLocale, localeOptions, type LocaleType, setLocale } from '@/locales/setupI18n'

const { t } = useI18n()
const currentLocale = computed(() => getLocale())

const currentLabel = computed(() => {
    const opt = localeOptions.find((o) => o.value === currentLocale.value)
    return opt?.label || ''
})

const handleChange = (locale: LocaleType) => {
    if (locale === currentLocale.value) return
    setLocale(locale)
    // 先切换语言再获取翻译，需要在 nextTick 中提示
    nextTick(() => {
        ElMessage.success(t('langSelect.message.success'))
    })
}
</script>

<style lang="scss" scoped>
/* 顶栏渐变背景下：透明底、亮色图标、与相邻 icon-btn 一致的 hover 反馈 */
.lang-trigger {
    color: rgba(255, 255, 255, 0.85);
    background: transparent;
    transition:
        background 0.15s,
        color 0.15s;
}
.lang-trigger:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
}

:deep(.el-dropdown-menu__item.is-active) {
    color: var(--el-color-primary);
    font-weight: 500;
}
</style>
