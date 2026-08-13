<script setup lang="ts">
import { ElMessage } from 'element-plus'
import { ref, watch } from 'vue'

import { marketplaceApi } from '@/api/marketplace'

interface Props {
    modelValue: boolean
    entitlementCode: string
    appName?: string
    fromVersion?: string
    toVersion: string
}
const props = withDefaults(defineProps<Props>(), {
    appName: '应用',
    fromVersion: ''
})
const emit = defineEmits<{ 'update:modelValue': [v: boolean] }>()

const loading = ref(false)
const text = ref('')

watch(
    () => props.modelValue,
    async (v) => {
        if (!v) return
        text.value = ''
        loading.value = true
        try {
            const resp: any = await marketplaceApi.fetchChangelog(
                props.entitlementCode,
                props.toVersion
            )
            const cl = (resp?.data?.changelog ?? '') as string
            text.value = cl.trim() || '（暂无更新说明）'
        } catch (e: any) {
            // axios 拦截器已弹 ElMessage.error，这里不重复
            text.value = ''
            if (!e?.__handled && e?.message) ElMessage.error(e.message)
        } finally {
            loading.value = false
        }
    }
)

function close() {
    emit('update:modelValue', false)
}
</script>

<template>
    <el-drawer
        :model-value="modelValue"
        title="更新说明"
        size="600px"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <template #default>
            <div class="cl-meta">
                <div class="app">{{ appName }}</div>
                <div class="ver">
                    <span v-if="fromVersion">v{{ fromVersion }} →</span>
                    <span class="to">v{{ toVersion }}</span>
                </div>
            </div>
            <div v-loading="loading" class="cl-body">
                <pre v-if="text">{{ text }}</pre>
                <el-empty v-else-if="!loading" description="加载失败" />
            </div>
        </template>
        <template #footer>
            <el-button @click="close">关闭</el-button>
        </template>
    </el-drawer>
</template>

<style scoped>
.cl-meta {
    padding: 0 20px 12px;
    border-bottom: 1px solid var(--el-border-color-lighter);
    margin-bottom: 16px;
}
.cl-meta .app {
    font-size: 16px;
    font-weight: 600;
}
.cl-meta .ver {
    margin-top: 4px;
    color: var(--el-text-color-secondary);
    font-size: 13px;
}
.cl-meta .ver .to {
    margin-left: 6px;
    color: var(--el-color-primary);
    font-weight: 600;
}
.cl-body {
    padding: 0 20px;
    min-height: 200px;
}
.cl-body pre {
    white-space: pre-wrap;
    word-break: break-word;
    font-family: 'JetBrains Mono', 'Source Code Pro', Consolas, monospace;
    font-size: 13px;
    line-height: 1.7;
    background: var(--el-fill-color-light);
    padding: 12px 16px;
    border-radius: 6px;
    margin: 0;
}
</style>
