<template>
    <el-dialog
        class="dialog-md"
        :model-value="visible"
        :title="$t('order.detail')"
        @update:model-value="(v: boolean) => emit('update:visible', v)"
    >
        <div v-if="order" class="order-detail">
            <!-- 金额主视觉 -->
            <div class="hero">
                <div class="hero-top">
                    <span class="order-no">{{ order.order_no }}</span>
                    <el-tag :type="statusTagType(order.status)">
                        {{ statusLabel(order.status) }}
                    </el-tag>
                </div>
                <div class="amount"><span class="currency">¥</span>{{ order.amount }}</div>
                <div v-if="showPaidHint" class="paid-hint">
                    {{ $t('order.paidAmount') }} ¥{{ order.paid_amount }}
                </div>
            </div>

            <!-- 订购内容 -->
            <section class="block">
                <div class="block-title">{{ $t('order.orderInfo') }}</div>
                <div class="row">
                    <span class="label">{{ $t('order.tenant') }}</span>
                    <span class="value">{{ order.tenant_name || `#${order.tenant_id}` }}</span>
                </div>
                <div class="row">
                    <span class="label">{{ $t('order.plan') }}</span>
                    <span class="value">{{ productText }}</span>
                </div>
                <div class="row">
                    <span class="label">{{ $t('order.orderType') }}</span>
                    <span class="value">{{ typeLabel(order.type) }}</span>
                </div>
            </section>

            <!-- 支付信息 -->
            <section class="block">
                <div class="block-title">{{ $t('order.paymentInfo') }}</div>
                <div class="row">
                    <span class="label">{{ $t('order.paymentChannel') }}</span>
                    <span class="value">{{ channelText }}</span>
                </div>
                <div class="row">
                    <span class="label">{{ $t('order.transactionId') }}</span>
                    <span class="value txn">{{ order.transaction_id || '—' }}</span>
                </div>
            </section>

            <!-- 时间 -->
            <section class="block">
                <div class="row">
                    <span class="label">{{ $t('common.createdAt') }}</span>
                    <span class="value">{{ order.created_at || '—' }}</span>
                </div>
                <div class="row">
                    <span class="label">{{ $t('order.paymentTime') }}</span>
                    <span class="value">{{ order.paid_at || '—' }}</span>
                </div>
                <div class="row">
                    <span class="label">{{ $t('order.expiredAt') }}</span>
                    <span class="value">{{ order.expired_at || '—' }}</span>
                </div>
            </section>
        </div>

        <div v-else class="loading-placeholder">
            <el-icon class="is-loading"><Loading /></el-icon>
            {{ $t('common.loading') }}
        </div>

        <template #footer>
            <el-button @click="emit('update:visible', false)">{{ $t('common.close') }}</el-button>
            <template v-if="order?.status === 1">
                <el-button type="warning" @click="handleCancel">{{
                    $t('order.cancelOrder')
                }}</el-button>
                <el-button type="success" @click="handleMarkPaid">{{
                    $t('order.forceMarkPaid')
                }}</el-button>
            </template>
            <el-button
                v-if="order?.status === 2"
                v-perms="'platform.refund.create'"
                type="danger"
                @click="openRefund"
            >
                {{ $t('order.refund') }}
            </el-button>
        </template>
    </el-dialog>

    <el-dialog
        v-model="refundVisible"
        class="dialog-sm"
        :title="$t('order.refundDialogTitle')"
        append-to-body
    >
        <el-form label-width="90px" @submit.prevent>
            <el-form-item :label="$t('refund.amount')" required>
                <el-input-number
                    v-model="refundAmount"
                    :min="0.01"
                    :max="Number(order?.amount) || 0"
                    :precision="2"
                    :step="1"
                    style="width: 100%"
                />
            </el-form-item>
            <el-form-item :label="$t('refund.reason')">
                <el-input v-model="refundReason" type="textarea" :rows="3" maxlength="200" />
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="refundVisible = false">{{ $t('common.cancel') }}</el-button>
            <el-button type="danger" :loading="refundSubmitting" @click="submitRefund">
                {{ $t('order.refund') }}
            </el-button>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { Loading } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { orderApi } from '@/api/order'
import { refundApi } from '@/api/refund'
import type { SaasOrderInfo } from '@/types/api'

const { t } = useI18n()

const props = defineProps<{
    visible: boolean
    id?: number
}>()

const emit = defineEmits<{
    'update:visible': [v: boolean]
    refresh: []
}>()

const order = ref<SaasOrderInfo | null>(null)

async function loadOrder() {
    if (!props.id) {
        order.value = null
        return
    }
    try {
        const res = await orderApi.show(props.id)
        order.value = res.data
    } catch (e: any) {
        ElMessage.error(e.message || t('order.loadFailed'))
        order.value = null
    }
}

watch(
    () => [props.visible, props.id],
    async ([v]) => {
        if (v) {
            await loadOrder()
        } else {
            order.value = null
        }
    },
    { immediate: true }
)

async function handleMarkPaid() {
    if (!order.value) return
    try {
        await ElMessageBox.confirm(t('order.confirmMarkPaid'), t('common.tips'), {
            type: 'warning'
        })
        await orderApi.markPaid(order.value.id)
        ElMessage.success(t('order.markPaidSuccess'))
        emit('update:visible', false)
        emit('refresh')
    } catch (e: any) {
        if (e !== 'cancel') ElMessage.error(e.message || t('common.failed'))
    }
}

async function handleCancel() {
    if (!order.value) return
    try {
        await ElMessageBox.confirm(t('order.confirmCancel'), t('common.tips'), { type: 'warning' })
        await orderApi.cancel(order.value.id)
        ElMessage.success(t('order.cancelSuccess'))
        emit('update:visible', false)
        emit('refresh')
    } catch (e: any) {
        if (e !== 'cancel') ElMessage.error(e.message || t('common.failed'))
    }
}

const refundVisible = ref(false)
const refundAmount = ref(0)
const refundReason = ref('')
const refundSubmitting = ref(false)

function openRefund() {
    if (!order.value) return
    refundAmount.value = Number(order.value.amount) || 0
    refundReason.value = ''
    refundVisible.value = true
}

async function submitRefund() {
    if (!order.value) return
    // 后端 RefundService 以「分」为单位校验可退余额
    const cents = Math.round(refundAmount.value * 100)
    if (cents <= 0) {
        ElMessage.warning(t('order.refundAmountInvalid'))
        return
    }
    refundSubmitting.value = true
    try {
        await refundApi.refundOrder(order.value.id, {
            amount: cents,
            reason: refundReason.value.trim()
        })
        ElMessage.success(t('order.refundSuccess'))
        refundVisible.value = false
        emit('update:visible', false)
        emit('refresh')
    } catch (e: any) {
        if (e !== 'cancel' && !e?.__handled) ElMessage.error(e.message || t('common.failed'))
    } finally {
        refundSubmitting.value = false
    }
}

// 已付金额与订单金额不一致（部分退款/优惠等）时才单独提示
const showPaidHint = computed(
    () =>
        order.value &&
        order.value.status >= 2 &&
        Number(order.value.paid_amount) !== Number(order.value.amount)
)

// 套餐/插件名 · N 个月
const productText = computed(() => {
    if (!order.value) return ''
    const name = order.value.plan_name || order.value.plugin_name || '—'
    return `${name} · ${order.value.months} ${t('order.monthsUnit')}`
})

// 支付渠道 · 方式
const channelText = computed(() => {
    if (!order.value) return '—'
    const channelMap: Record<string, string> = {
        wechat: t('order.wechat'),
        alipay: t('order.alipay')
    }
    const channel = channelMap[order.value.payment_channel] || order.value.payment_channel
    if (!channel) return '—'
    return order.value.payment_method ? `${channel} · ${order.value.payment_method}` : channel
})

function typeLabel(type: number): string {
    const map: Record<number, string> = {
        1: t('order.typeNew'),
        2: t('order.typeRenew'),
        3: t('order.typeUpgrade'),
        4: t('order.typePlugin')
    }
    return map[type] || String(type)
}

function statusLabel(s: number): string {
    const labels = [
        '',
        t('order.unpaid'),
        t('order.paid'),
        t('order.cancelled'),
        t('order.refunded')
    ]
    return labels[s] || String(s)
}

function statusTagType(s: number): 'info' | 'success' | 'warning' | 'danger' {
    const map = ['info', 'info', 'success', 'warning', 'danger'] as const
    return map[s] || 'info'
}
</script>

<style scoped lang="scss">
.order-detail {
    /* 金额主视觉区 */
    .hero {
        padding-bottom: 16px;

        .hero-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .order-no {
            font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
            font-size: 13px;
            color: var(--el-text-color-secondary);
            letter-spacing: 0.3px;
        }

        .amount {
            font-size: 30px;
            font-weight: 700;
            line-height: 1.2;
            color: var(--el-text-color-primary);
            font-variant-numeric: tabular-nums;

            .currency {
                font-size: 18px;
                font-weight: 600;
                margin-right: 2px;
                color: var(--el-text-color-regular);
            }
        }

        .paid-hint {
            margin-top: 4px;
            font-size: 12px;
            color: var(--el-text-color-secondary);
        }
    }

    /* 功能分区 */
    .block {
        padding: 14px 0;
        border-top: 1px dashed var(--el-border-color-lighter);

        &:last-child {
            padding-bottom: 0;
        }

        .block-title {
            margin-bottom: 8px;
            font-size: 12px;
            color: var(--el-text-color-secondary);
            letter-spacing: 0.5px;
        }

        .row {
            display: flex;
            gap: 16px;
            padding: 4px 0;
            font-size: 14px;
            line-height: 1.6;

            .label {
                flex-shrink: 0;
                width: 72px;
                color: var(--el-text-color-secondary);
            }

            .value {
                color: var(--el-text-color-primary);
                word-break: break-all;

                &.txn {
                    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
                    font-size: 13px;
                }
            }
        }
    }
}

.loading-placeholder {
    text-align: center;
    padding: 24px;
    color: var(--el-text-color-secondary);
}
</style>
