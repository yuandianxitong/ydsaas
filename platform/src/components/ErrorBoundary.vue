<script setup lang="ts">
import { onErrorCaptured, ref } from 'vue'

const hasError = ref(false)
const errorInfo = ref<{ message: string; stack?: string }>({ message: '' })

onErrorCaptured((err: Error & { isAxiosError?: boolean; __handled?: boolean }, instance, info) => {
    // 忽略已被 request 拦截器处理的 API 错误（仅 ElMessage 提示，不渲染错误页）
    if (err.isAxiosError || err.__handled) {
        return false
    }
    // ElMessageBox.confirm/alert/prompt 用户点取消/关闭时 reject 的是字符串 'cancel'/'close'，
    // 不是 Error 实例。属于正常交互分支，调用方理应自行 try/catch；这里兜底防止整页崩。
    if (typeof err === 'string' && (err === 'cancel' || err === 'close')) {
        return false
    }
    hasError.value = true
    errorInfo.value = {
        message: err.message || 'Page render error',
        stack: import.meta.env.DEV ? err.stack : undefined
    }
    console.error('[ErrorBoundary]', err, '\nComponent:', instance, '\nInfo:', info)
    // 返回 false 阻止错误继续向上传播
    return false
})

function handleReload() {
    hasError.value = false
    errorInfo.value = { message: '' }
}

function handleRefresh() {
    window.location.reload()
}
</script>

<template>
    <slot v-if="!hasError" />
    <div v-else class="error-boundary">
        <div class="error-boundary__content">
            <div class="error-boundary__icon">
                <svg
                    viewBox="0 0 24 24"
                    width="80"
                    height="80"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="1.5"
                >
                    <circle cx="12" cy="12" r="10" />
                    <line x1="12" y1="8" x2="12" y2="12" />
                    <line x1="12" y1="16" x2="12.01" y2="16" />
                </svg>
            </div>
            <h2 class="error-boundary__title">{{ $t('errorPage.renderError') }}</h2>
            <p class="error-boundary__message">{{ errorInfo.message }}</p>
            <pre v-if="errorInfo.stack" class="error-boundary__stack">{{ errorInfo.stack }}</pre>
            <div class="error-boundary__actions">
                <el-button @click="handleReload">{{ $t('errorPage.retry') }}</el-button>
                <el-button type="primary" @click="handleRefresh">{{
                    $t('errorPage.refreshPage')
                }}</el-button>
            </div>
        </div>
    </div>
</template>

<style lang="scss" scoped>
.error-boundary {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: 100vh;
    background: var(--el-bg-color);
    padding: 20px;

    &__content {
        text-align: center;
        max-width: 600px;
    }

    &__icon {
        color: var(--el-color-danger);
        margin-bottom: 20px;
    }

    &__title {
        font-size: 24px;
        color: var(--el-text-color-primary);
        margin-bottom: 12px;
    }

    &__message {
        font-size: 14px;
        color: var(--el-text-color-secondary);
        margin-bottom: 20px;
    }

    &__stack {
        text-align: left;
        font-size: 12px;
        color: var(--el-text-color-regular);
        background: var(--el-fill-color-light);
        border-radius: 4px;
        padding: 16px;
        max-height: 200px;
        overflow: auto;
        margin-bottom: 20px;
        white-space: pre-wrap;
        word-break: break-all;
    }

    &__actions {
        display: flex;
        justify-content: center;
        gap: 12px;
    }
}
</style>
