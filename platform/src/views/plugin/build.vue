<script setup lang="ts">
import { Refresh } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { onMounted, ref } from 'vue'

import { pluginBuildApi } from '@/api/plugin-build'
import type { PluginBuildInfo } from '@/types/api'

import BuildLogDialog from './components/BuildLogDialog.vue'

const list = ref<PluginBuildInfo[]>([])
const total = ref(0)
const page = ref(1)
const limit = ref(20)
const loading = ref(false)
const logVisible = ref(false)
const currentBuildId = ref<number | null>(null)

const STATUS_LABELS: Record<
    number,
    { label: string; type: 'success' | 'info' | 'warning' | 'danger' }
> = {
    0: { label: '排队中', type: 'info' },
    1: { label: '构建中', type: 'warning' },
    2: { label: '成功', type: 'success' },
    3: { label: '失败', type: 'danger' },
    4: { label: '已回滚', type: 'info' }
}

async function load() {
    loading.value = true
    try {
        const res = await pluginBuildApi.list({ page: page.value, limit: limit.value })
        list.value = res.data.list
        total.value = res.data.pagination.total
    } finally {
        loading.value = false
    }
}

async function rebuild(target: 'platform' | 'tenant') {
    const res = await pluginBuildApi.rebuild(target)
    ElMessage.success(`已触发 ${target} 重建，build_id=${res.data.build_id}`)
    await load()
}

function openLog(id: number) {
    currentBuildId.value = id
    logVisible.value = true
}

onMounted(load)
</script>

<template>
    <div style="padding: 16px">
        <el-card shadow="never">
            <template #header>
                <div style="display: flex; justify-content: space-between; align-items: center">
                    <span style="font-size: 16px; font-weight: 600">云编译任务</span>
                    <div>
                        <el-button @click="rebuild('platform')"> 重建 Platform </el-button>
                        <el-button @click="rebuild('tenant')"> 重建 Tenant </el-button>
                        <el-button :icon="Refresh" @click="load"> 刷新 </el-button>
                    </div>
                </div>
            </template>

            <el-table v-loading="loading" :data="list" stripe>
                <el-table-column prop="id" label="ID" width="60" />
                <el-table-column prop="target" label="目标" width="100" />
                <el-table-column prop="trigger" label="触发" width="120" />
                <el-table-column label="状态" width="120">
                    <template #default="{ row }">
                        <el-tag :type="STATUS_LABELS[row.status]?.type ?? 'info'">
                            {{ STATUS_LABELS[row.status]?.label ?? row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column prop="started_at" label="开始" width="160" />
                <el-table-column prop="finished_at" label="结束" width="160" />
                <el-table-column label="操作" width="120" align="right">
                    <template #default="{ row }">
                        <el-button size="small" @click="openLog(row.id)"> 查看日志 </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="page"
                v-model:page-size="limit"
                :total="total"
                layout="total, prev, pager, next"
                style="margin-top: 16px; justify-content: end; display: flex"
                @current-change="load"
                @size-change="load"
            />
        </el-card>

        <BuildLogDialog v-model:visible="logVisible" :build-id="currentBuildId" />
    </div>
</template>
