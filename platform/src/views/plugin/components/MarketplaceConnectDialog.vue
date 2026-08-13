<script setup lang="ts">
import { ElButton, ElDialog, ElMessage } from 'element-plus'
import { ref, watch } from 'vue'

import type { InstallResolution } from '@/api/marketplace'
import { useMarketplaceInstall } from '@/composables/useMarketplaceInstall'

const visible = defineModel<boolean>('visible', { default: false })
const props = defineProps<{
    appCode?: string | null
    appName?: string
}>()
const emit = defineEmits<{
    resolved: [resolution: InstallResolution]
}>()

const { openPopup, connectAndResolve } = useMarketplaceInstall()
const loading = ref(false)
let activePopup: Window | null = null

async function onConnect() {
    // 必须在此同步栈里先开窗口，避免被浏览器拦截
    const popup = openPopup()
    if (!popup) {
        ElMessage.error('请允许浏览器弹出窗口后重试')
        return
    }
    activePopup = popup
    loading.value = true
    try {
        const resolution = await connectAndResolve(props.appCode ?? null, popup)
        visible.value = false
        emit('resolved', resolution)
    } catch (e: any) {
        if (!e?.__handled) ElMessage.error(e?.message || '连接失败')
    } finally {
        loading.value = false
        activePopup = null
    }
}

// 授权进行中关闭对话框（取消）时，关掉残留弹窗以中止流程，
// 否则用户事后在弹窗里完成授权会把安装意图投递到已关闭的上下文。
watch(visible, (v) => {
    if (!v && loading.value && activePopup && !activePopup.closed) {
        activePopup.close()
    }
})
</script>

<template>
    <ElDialog
        v-model="visible"
        class="dialog-sm"
        title="连接元点官方市场账号"
        align-center
        :close-on-click-modal="false"
    >
        <div class="connect-body">
            <div class="lead">尚未连接元点官方市场账号，连接后可验证应用权益并继续安装。</div>
            <p v-if="appName" class="hint">
                即将为「<b>{{ appName }}</b
                >」验证你的已购权益。
            </p>
            <p class="hint sub">
                将打开元点官方市场的登录授权页面，授权后自动完成连接与权益同步，全程无需填写任何凭证。
            </p>
        </div>
        <template #footer>
            <ElButton @click="visible = false">取消</ElButton>
            <ElButton type="primary" :loading="loading" @click="onConnect">
                连接账号并继续
            </ElButton>
        </template>
    </ElDialog>
</template>

<style scoped>
.connect-body {
    padding: 4px 2px;
}

.lead {
    font-size: 14px;
    color: #1f2329;
    line-height: 1.6;
    font-weight: 500;
}

.hint {
    margin: 12px 0 0;
    font-size: 13px;
    color: #5f6772;
    line-height: 1.6;
}

.hint.sub {
    color: #8a8f99;
    font-size: 12px;
}
</style>
