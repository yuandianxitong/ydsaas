<script setup lang="ts">
interface Props {
    status?: 'active' | 'grace' | 'expired' | null
    graceStartedAt?: string | null
    gracePeriodDays?: number
}
const props = withDefaults(defineProps<Props>(), {
    gracePeriodDays: 14
})

function graceUntilLabel(): string {
    if (!props.graceStartedAt) return ''
    const startedTs = Date.parse(props.graceStartedAt)
    if (Number.isNaN(startedTs)) return ''
    const untilTs = startedTs + props.gracePeriodDays * 86400 * 1000
    const d = new Date(untilTs)
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
</script>

<template>
    <el-tag v-if="status === 'grace'" type="warning" size="small" effect="light">
        宽限期<span v-if="graceUntilLabel()"> 至 {{ graceUntilLabel() }}</span>
    </el-tag>
    <el-tag v-else-if="status === 'expired'" type="danger" size="small" effect="light">
        授权过期
    </el-tag>
</template>
