<template>
    <div class="subscription">
        <div v-if="pendingOrder" class="pending-bar">
            <div class="pending-bar__main">
                <div class="pending-bar__title">待支付续费订单</div>
                <div class="pending-bar__meta">
                    <span>订单号 {{ pendingOrder.order_no }}</span>
                    <span class="pending-bar__amount"
                        >¥{{ Number(pendingOrder.amount).toFixed(2) }}</span
                    >
                </div>
            </div>
            <el-button type="primary" @click="handlePayPending">立即支付</el-button>
        </div>

        <el-row :gutter="16">
            <el-col :xs="24" :md="10">
                <el-card shadow="never" class="sub-card">
                    <template #header>
                        <span class="sub-card__title">当前订阅</span>
                    </template>
                    <div class="current-hero" :class="`current-hero--${lifecycleTone}`">
                        <div class="current-hero__name">{{ currentPlanName }}</div>
                        <el-tag :type="lifecycleTagType" effect="dark" size="small">
                            {{ lifecycleLabel }}
                        </el-tag>
                    </div>
                    <div class="current-rows">
                        <div class="current-row">
                            <span class="current-row__label">到期时间</span>
                            <span class="current-row__value">{{
                                saas?.expires_at || '永不过期'
                            }}</span>
                        </div>
                        <div v-if="expireHint" class="current-row">
                            <span class="current-row__label">状态说明</span>
                            <span class="current-row__value current-row__value--hint">{{
                                expireHint
                            }}</span>
                        </div>
                        <div v-if="saas?.lifecycle_state === 'grace'" class="current-row">
                            <span class="current-row__label">宽限截止</span>
                            <span class="current-row__value">{{ saas?.grace_until || '—' }}</span>
                        </div>
                    </div>

                    <div class="feature-block">
                        <div class="feature-block__title">套餐权益</div>
                        <div v-if="featureItems.length" class="feature-grid">
                            <div v-for="item in featureItems" :key="item.code" class="feature-item">
                                <div class="feature-item__icon">
                                    <img v-if="item.icon" :src="item.icon" :alt="item.name" />
                                    <span v-else>{{ item.initial }}</span>
                                </div>
                                <div class="feature-item__body">
                                    <div class="feature-item__name" :title="item.name">
                                        {{ item.name }}
                                    </div>
                                    <div class="feature-item__meta">
                                        <el-tag size="small" effect="plain">{{
                                            item.kindLabel
                                        }}</el-tag>
                                        <el-tag
                                            size="small"
                                            :type="item.source === 'purchase' ? 'warning' : 'info'"
                                            effect="plain"
                                            >{{ item.sourceLabel }}</el-tag
                                        >
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="feature-empty">未启用任何功能</div>
                    </div>
                </el-card>
            </el-col>

            <el-col :xs="24" :md="14">
                <el-card shadow="never" class="sub-card">
                    <template #header>
                        <span class="sub-card__title">续费 / 升级</span>
                    </template>

                    <div class="plan-picker">
                        <div
                            v-for="plan in paidPlans"
                            :key="plan.id"
                            class="plan-card"
                            :class="{
                                'plan-card--active': selectedPlanId === plan.id,
                                'plan-card--disabled': plan.code === 'free'
                            }"
                            @click="plan.code !== 'free' && (selectedPlanId = plan.id)"
                        >
                            <div class="plan-card__name">
                                {{ plan.name }}
                                <el-tag
                                    v-if="isCurrentPlan(plan)"
                                    size="small"
                                    type="success"
                                    effect="plain"
                                    >当前</el-tag
                                >
                            </div>
                            <div class="plan-card__price">
                                <template v-if="plan.code !== 'free'">
                                    ¥{{ plan.price_monthly }}<span>/月</span>
                                </template>
                                <template v-else>免费</template>
                            </div>
                        </div>
                    </div>

                    <el-form label-position="top" class="renew-form">
                        <el-form-item label="购买月数">
                            <el-radio-group v-model="selectedMonths">
                                <el-radio-button :value="1">1 个月</el-radio-button>
                                <el-radio-button :value="3">3 个月</el-radio-button>
                                <el-radio-button :value="6">6 个月</el-radio-button>
                                <el-radio-button :value="12">12 个月</el-radio-button>
                            </el-radio-group>
                        </el-form-item>

                        <el-form-item label="支付方式">
                            <el-radio-group v-model="selectedChannel">
                                <el-radio value="wechat">微信支付</el-radio>
                                <el-radio value="alipay">支付宝</el-radio>
                            </el-radio-group>
                        </el-form-item>
                    </el-form>

                    <div class="checkout-bar">
                        <div class="checkout-bar__amount">
                            <span class="checkout-bar__label">应付金额</span>
                            <span class="total-amount">¥{{ totalAmount.toFixed(2) }}</span>
                        </div>
                        <el-button
                            type="primary"
                            size="large"
                            :disabled="!selectedPlan || totalAmount <= 0"
                            :loading="submitting"
                            @click="handleSubmit"
                        >
                            立即支付
                        </el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <PayDialog
            v-model:visible="payDialogVisible"
            :order="currentOrder"
            :channel="selectedChannel"
            @success="handlePaySuccess"
        />
    </div>
</template>

<script setup lang="ts">
import { ElMessage } from 'element-plus'
import { computed, onMounted, ref } from 'vue'

import { subscriptionApi } from '@/api/subscription'
import { useUserStore } from '@/store'
import type { Entitlement, PlanInfo, SaasOrderInfo } from '@/types/api'

import PayDialog from './components/PayDialog.vue'

interface FeatureItem {
    code: string
    name: string
    icon: string
    initial: string
    kind: string
    kindLabel: string
    source: string
    sourceLabel: string
}

const userStore = useUserStore()
const saas = computed(() => userStore.saas)

const plans = ref<PlanInfo[]>([])
const selectedPlanId = ref<number>(0)
const selectedMonths = ref<number>(12)
const selectedChannel = ref<'wechat' | 'alipay'>('wechat')
const submitting = ref(false)

const payDialogVisible = ref(false)
const currentOrder = ref<SaasOrderInfo | null>(null)
const pendingOrder = ref<SaasOrderInfo | null>(null)

const paidPlans = computed(() => plans.value.filter((p) => p.code !== 'free'))
const selectedPlan = computed(() => plans.value.find((p) => p.id === selectedPlanId.value) || null)

const totalAmount = computed(() => {
    if (!selectedPlan.value) return 0
    return Number(selectedPlan.value.price_monthly) * selectedMonths.value
})

const featureItems = computed<FeatureItem[]>(() => {
    const ents = saas.value?.entitlements || []
    if (ents.length) {
        return ents.map((e: Entitlement) => {
            const name = (e.name || e.code || '').trim() || e.code
            return {
                code: e.code,
                name,
                icon: e.icon || '',
                initial: name.slice(0, 1).toUpperCase() || '?',
                kind: e.kind,
                kindLabel: e.kind === 'app' ? '应用' : '插件',
                source: e.source,
                sourceLabel: e.source === 'purchase' ? '加购' : '套餐'
            }
        })
    }
    // 兼容旧字段：仅有 features code 列表
    return (saas.value?.features || []).filter(Boolean).map((code) => ({
        code,
        name: code,
        icon: '',
        initial: code.slice(0, 1).toUpperCase() || '?',
        kind: 'plugin',
        kindLabel: '插件',
        source: 'plan',
        sourceLabel: '套餐'
    }))
})

const currentPlanName = computed(() => {
    const hit = plans.value.find((p) => isCurrentPlan(p))
    if (hit) return hit.name
    return saas.value?.tenant_name ? `${saas.value.tenant_name} 套餐` : '当前套餐'
})

function isCurrentPlan(plan: PlanInfo): boolean {
    const planId = Number(saas.value?.plan_id || 0)
    return planId > 0 && plan.id === planId
}

const expireHint = computed(() => {
    const state = saas.value?.lifecycle_state
    if (state === 'grace') return '已过期，处于宽限期，部分写操作可能受限'
    if (state === 'frozen') return '账号已冻结，请尽快续费恢复'
    if (state === 'trial') return '试用中，到期后将进入正式/宽限流程'
    if (!saas.value?.expires_at) return ''
    const end = Date.parse(saas.value.expires_at.replace(/-/g, '/'))
    if (Number.isNaN(end)) return ''
    const days = Math.ceil((end - Date.now()) / 86400000)
    if (days < 0) return '已过期'
    if (days === 0) return '今天到期'
    if (days <= 30) return `剩余约 ${days} 天`
    return ''
})

const lifecycleTone = computed(() => {
    switch (saas.value?.lifecycle_state) {
        case 'grace':
            return 'warn'
        case 'frozen':
        case 'disabled':
            return 'danger'
        default:
            return 'ok'
    }
})

async function loadPlans() {
    try {
        const res = await subscriptionApi.plans()
        plans.value = res.data.list || []
        const current = plans.value.find((p) => isCurrentPlan(p) && p.code !== 'free')
        const firstNonFree = plans.value.find((p) => p.code !== 'free')
        selectedPlanId.value = (current || firstNonFree)?.id || 0
    } catch (e: any) {
        ElMessage.error(e.message || '加载套餐失败')
    }
}

async function handleSubmit() {
    if (!selectedPlan.value) {
        ElMessage.warning('请选择套餐')
        return
    }
    submitting.value = true
    try {
        const res = await subscriptionApi.createOrder({
            plan_id: selectedPlanId.value,
            months: selectedMonths.value,
            channel: selectedChannel.value,
            method: selectedChannel.value === 'wechat' ? 'native' : 'page'
        })
        currentOrder.value = res.data
        payDialogVisible.value = true
    } catch (e: any) {
        ElMessage.error(e.message || '创建订单失败')
    } finally {
        submitting.value = false
    }
}

function handlePaySuccess() {
    ElMessage.success('支付成功，订阅已续费')
    payDialogVisible.value = false
    currentOrder.value = null
    pendingOrder.value = null
    userStore.getUserInfo?.()
}

async function loadPendingOrder() {
    try {
        const res = await subscriptionApi.pendingRenewal()
        if (res.data) {
            pendingOrder.value = res.data
        }
    } catch {
        // No pending order — silent
    }
}

function handlePayPending() {
    if (pendingOrder.value) {
        currentOrder.value = pendingOrder.value
        payDialogVisible.value = true
    }
}

onMounted(() => {
    loadPlans()
    loadPendingOrder()
})

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
            return '-'
    }
})

const lifecycleTagType = computed((): 'success' | 'warning' | 'danger' | 'info' => {
    switch (saas.value?.lifecycle_state) {
        case 'active':
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
</script>

<style scoped lang="scss">
.subscription {
    min-height: 100%;
}

.pending-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 16px;
    padding: 14px 18px;
    border-radius: var(--radius-lg, 10px);
    background: var(--el-color-warning-light-9);
    border: 1px solid var(--el-color-warning-light-5);
}

.pending-bar__title {
    font-size: 14px;
    font-weight: 600;
    color: var(--el-color-warning-dark-2);
}

.pending-bar__meta {
    margin-top: 4px;
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    font-size: 13px;
    color: var(--color-text-secondary, #606266);
}

.pending-bar__amount {
    font-weight: 600;
    color: #f56c6c;
}

.sub-card {
    margin-bottom: 16px;
}

.sub-card__title {
    font-weight: 600;
}

.current-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 14px 16px;
    border-radius: 10px;
    margin-bottom: 14px;
    background: var(--el-color-success-light-9);

    &--warn {
        background: var(--el-color-warning-light-9);
    }
    &--danger {
        background: var(--el-color-danger-light-9);
    }
}

.current-hero__name {
    font-size: 18px;
    font-weight: 700;
    color: var(--color-text-primary, #303133);
}

.current-rows {
    display: flex;
    flex-direction: column;
    gap: 10px;
    margin-bottom: 18px;
}

.current-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    font-size: 13px;
}

.current-row__label {
    color: var(--color-text-tertiary, #909399);
}

.current-row__value {
    color: var(--color-text-primary, #303133);
    font-weight: 500;
}

.current-row__value--hint {
    color: var(--el-color-warning);
    font-weight: 400;
    text-align: right;
}

.feature-block__title {
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 10px;
    color: var(--color-text-primary, #303133);
}

.feature-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(148px, 1fr));
    gap: 10px;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 10px;
    min-width: 0;
    padding: 10px;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 10px;
    background: var(--color-surface-sunken, #f7f8fa);
}

.feature-item__icon {
    width: 36px;
    height: 36px;
    flex-shrink: 0;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    background: var(--color-surface, #fff);
    color: var(--el-color-primary);
    font-size: 14px;
    font-weight: 700;

    img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        display: block;
    }
}

.feature-item__body {
    min-width: 0;
    flex: 1;
}

.feature-item__name {
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text-primary, #303133);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    margin-bottom: 4px;
}

.feature-item__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.feature-empty {
    font-size: 13px;
    color: var(--color-text-tertiary, #909399);
}

.plan-picker {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 10px;
    margin-bottom: 18px;
}

.plan-card {
    padding: 12px 14px;
    border: 1px solid var(--el-border-color);
    border-radius: 10px;
    cursor: pointer;
    transition:
        border-color 0.15s,
        box-shadow 0.15s;
    background: var(--color-surface, #fff);

    &:hover {
        border-color: var(--el-color-primary-light-5);
    }

    &--active {
        border-color: var(--el-color-primary);
        box-shadow: 0 0 0 1px var(--el-color-primary-light-7);
        background: var(--el-color-primary-light-9);
    }

    &--disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
}

.plan-card__name {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 6px;
}

.plan-card__price {
    font-size: 18px;
    font-weight: 700;
    color: #f56c6c;

    span {
        font-size: 12px;
        font-weight: 400;
        color: var(--color-text-tertiary, #909399);
        margin-left: 2px;
    }
}

.renew-form {
    margin-top: 4px;
}

.checkout-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    margin-top: 8px;
    padding-top: 16px;
    border-top: 1px solid var(--el-border-color-lighter);
}

.checkout-bar__label {
    display: block;
    font-size: 12px;
    color: var(--color-text-tertiary, #909399);
    margin-bottom: 4px;
}

.total-amount {
    font-size: 26px;
    color: #f56c6c;
    font-weight: 700;
    line-height: 1.2;
}
</style>
