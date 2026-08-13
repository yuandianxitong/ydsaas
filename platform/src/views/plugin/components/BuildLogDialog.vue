<script setup lang="ts">
import { ref, watch } from 'vue'

import { pluginBuildApi } from '@/api/plugin-build'
import type { PluginBuildInfo } from '@/types/api'

const props = defineProps<{ buildId: number | null }>()
const visible = defineModel<boolean>('visible', { required: true })

const data = ref<PluginBuildInfo | null>(null)
const loading = ref(false)

async function load() {
    if (!props.buildId) return
    loading.value = true
    try {
        const res = await pluginBuildApi.show(props.buildId)
        data.value = res.data
    } finally {
        loading.value = false
    }
}

watch([visible, () => props.buildId], ([v]) => v && load(), { immediate: true })
</script>

<template>
    <el-dialog v-model="visible" class="dialog-xl" :title="$t('pluginMgr.buildLogTitle')" top="5vh">
        <div v-loading="loading">
            <el-descriptions v-if="data" :column="2" border>
                <el-descriptions-item label="ID">{{ data.id }}</el-descriptions-item>
                <el-descriptions-item :label="$t('pluginMgr.buildTarget')">{{
                    data.target
                }}</el-descriptions-item>
                <el-descriptions-item :label="$t('pluginMgr.buildTrigger')">{{
                    data.trigger
                }}</el-descriptions-item>
                <el-descriptions-item :label="$t('pluginMgr.buildStatus')">{{
                    data.status
                }}</el-descriptions-item>
                <el-descriptions-item :label="$t('pluginMgr.buildStartedAt')">{{
                    data.started_at ?? '-'
                }}</el-descriptions-item>
                <el-descriptions-item :label="$t('pluginMgr.buildFinishedAt')">{{
                    data.finished_at ?? '-'
                }}</el-descriptions-item>
            </el-descriptions>
            <pre
                v-if="data"
                style="
                    background: #1e1e1e;
                    color: #d4d4d4;
                    padding: 12px;
                    margin-top: 12px;
                    max-height: 50vh;
                    overflow: auto;
                    font-size: 12px;
                    line-height: 1.5;
                "
                >{{ data.log || '(empty)' }}</pre
            >
        </div>
    </el-dialog>
</template>
