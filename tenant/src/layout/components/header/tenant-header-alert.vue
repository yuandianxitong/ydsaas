<template>
    <div v-if="shouldShow" class="tenant-header-alert" :class="alertClass">
        <el-icon class="alert-icon"><Warning /></el-icon>
        <span class="alert-message">{{ message }}</span>
        <el-button v-if="showRenewButton" type="primary" size="small" @click="goRenew">
            立即续费
        </el-button>
    </div>
</template>

<script setup lang="ts">
import { Warning } from '@element-plus/icons-vue'
import { computed } from 'vue'
import { useRouter } from 'vue-router'

import { useUserStore } from '@/store'

const userStore = useUserStore()
const router = useRouter()

const shouldShow = computed(() => {
    const s = userStore.saas
    if (!s) return false
    return ['grace', 'frozen', 'disabled'].includes(s.lifecycle_state)
})

const alertClass = computed(() => {
    const state = userStore.saas?.lifecycle_state
    if (state === 'grace') return 'alert-warning'
    if (state === 'frozen') return 'alert-danger'
    if (state === 'disabled') return 'alert-info'
    return ''
})

const message = computed(() => {
    const s = userStore.saas
    if (!s) return ''
    const state = s.lifecycle_state

    if (state === 'grace') {
        const days = calcRemainingDays(s.grace_until)
        return days !== null
            ? `租户处于宽限期，还剩 ${days} 天自动冻结。写操作已禁用，请尽快续费。`
            : '租户处于宽限期，写操作已禁用，请尽快续费。'
    }

    if (state === 'frozen') {
        return '租户已过期冻结。所有业务操作已停止，请续费后恢复使用。'
    }

    if (state === 'disabled') {
        return '租户账号已被平台禁用，请联系平台管理员。'
    }

    return ''
})

const showRenewButton = computed(() => {
    const state = userStore.saas?.lifecycle_state
    return state === 'grace' || state === 'frozen'
})

function calcRemainingDays(until: string | null): number | null {
    if (!until) return null
    const target = new Date(until).getTime()
    if (isNaN(target)) return null
    const diff = target - Date.now()
    if (diff < 0) return 0
    return Math.ceil(diff / (1000 * 60 * 60 * 24))
}

function goRenew() {
    router.push('/subscription').catch(() => {
        // 路由不存在时降级静默
    })
}
</script>

<style scoped lang="scss">
.tenant-header-alert {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 8px 20px;
    font-size: 14px;
    border-bottom: 1px solid var(--color-divider, #dcdfe6);
}

.alert-warning {
    background-color: #fef6ec;
    color: #b88230;
}

.alert-danger {
    background-color: #fef0f0;
    color: #c45656;
}

.alert-info {
    background-color: #f4f4f5;
    color: #606266;
}

.alert-icon {
    font-size: 16px;
}

.alert-message {
    flex: 1;
}
</style>
