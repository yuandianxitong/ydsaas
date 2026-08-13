<template>
    <div class="tenant-profile p-6">
        <el-card v-if="saas" shadow="never">
            <template #header>
                <div class="flex items-center justify-between">
                    <span class="font-medium">租户资料</span>
                    <el-tag :type="lifecycleTagType">{{ lifecycleLabel }}</el-tag>
                </div>
            </template>

            <el-descriptions :column="2" border>
                <el-descriptions-item label="租户名称">
                    {{ saas.tenant_name || '—' }}
                </el-descriptions-item>
                <el-descriptions-item label="租户 Code">
                    {{ saas.tenant_code || '—' }}
                </el-descriptions-item>
                <el-descriptions-item label="生命周期状态">
                    {{ lifecycleLabel }}
                </el-descriptions-item>
                <el-descriptions-item label="到期时间">
                    {{ saas.expires_at || '永不过期' }}
                </el-descriptions-item>
                <el-descriptions-item v-if="saas.lifecycle_state === 'grace'" label="宽限截止">
                    {{ saas.grace_until || '—' }}
                </el-descriptions-item>
                <el-descriptions-item label="Logo" :span="2">
                    <el-image
                        v-if="saas.tenant_logo"
                        :src="saas.tenant_logo"
                        fit="contain"
                        style="width: 64px; height: 64px"
                    />
                    <span v-else class="text-gray-400">未设置</span>
                </el-descriptions-item>
            </el-descriptions>

            <div style="margin-top: 24px">
                <h3 class="font-medium" style="margin-bottom: 12px">存储配额</h3>
                <el-progress
                    :percentage="storagePercent"
                    :stroke-width="16"
                    :color="storageColor"
                    :format="storageFormat"
                />
            </div>

            <div style="margin-top: 24px">
                <h3 class="font-medium" style="margin-bottom: 12px">已启用功能</h3>
                <template v-if="saas.features && saas.features.length > 0">
                    <el-tag
                        v-for="f in saas.features"
                        :key="f"
                        size="small"
                        style="margin-right: 8px; margin-bottom: 8px"
                    >
                        {{ f }}
                    </el-tag>
                </template>
                <span v-else class="text-gray-400">未启用任何功能</span>
            </div>
        </el-card>

        <el-empty v-else description="未加载到租户资料" />
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { useUserStore } from '@/store'

const userStore = useUserStore()
const saas = computed(() => userStore.saas)

const lifecycleLabel = computed(() => {
    switch (saas.value?.lifecycle_state) {
        case 'active':
            return '正常'
        case 'trial':
            return '试用'
        case 'grace':
            return '宽限期'
        case 'frozen':
            return '已冻结'
        case 'disabled':
            return '已禁用'
        default:
            return saas.value?.lifecycle_state || '-'
    }
})

const lifecycleTagType = computed((): 'success' | 'warning' | 'danger' | 'info' => {
    switch (saas.value?.lifecycle_state) {
        case 'active':
            return 'success'
        case 'trial':
            return 'success'
        case 'grace':
            return 'warning'
        case 'frozen':
            return 'danger'
        case 'disabled':
            return 'info'
        default:
            return 'info'
    }
})

const storagePercent = computed(() => {
    const limits = saas.value?.limits
    if (!limits || !limits.storage_limit_bytes) return 0
    const used = Number(limits.storage_used_bytes) || 0
    const limit = Number(limits.storage_limit_bytes) || 1
    const p = (used / limit) * 100
    return Math.min(Math.round(p * 10) / 10, 100)
})

const storageColor = computed(() => {
    const p = storagePercent.value
    if (p >= 90) return '#f56c6c' // red
    if (p >= 70) return '#e6a23c' // orange
    return '#67c23a' // green
})

function storageFormat(): string {
    const limits = saas.value?.limits
    if (!limits) return '-'
    const used = formatBytes(limits.storage_used_bytes)
    const limit = formatBytes(limits.storage_limit_bytes)
    return `${used} / ${limit}`
}

function formatBytes(bytes: number | string | undefined | null): string {
    const n = Number(bytes ?? 0)
    if (!Number.isFinite(n) || n < 0) return '-'
    if (n >= 1073741824) return (n / 1073741824).toFixed(2) + ' GB'
    if (n >= 1048576) return (n / 1048576).toFixed(2) + ' MB'
    if (n >= 1024) return (n / 1024).toFixed(2) + ' KB'
    return n + ' B'
}
</script>

<style scoped lang="scss">
.tenant-profile {
    min-height: 100%;
}
</style>
