<template>
    <el-dialog
        :model-value="visible"
        title="支付订单"
        class="dlg-sm"
        :close-on-click-modal="false"
        :close-on-press-escape="false"
        @update:model-value="(v: boolean) => emit('update:visible', v)"
        @close="handleClose"
    >
        <div v-if="loading" class="text-center py-6">
            <el-icon class="is-loading" :size="32"><Loading /></el-icon>
            <div class="mt-2">正在生成支付订单...</div>
        </div>

        <div v-else-if="error" class="text-center py-6">
            <el-icon :size="40" color="#f56c6c"><CircleClose /></el-icon>
            <div class="mt-2">{{ error }}</div>
            <el-button class="mt-4" @click="handleClose">关闭</el-button>
        </div>

        <div v-else-if="channel === 'wechat'" class="text-center">
            <div class="amount-preview">¥{{ order?.amount || 0 }}</div>
            <div class="qr-box">
                <canvas ref="qrCanvas" width="220" height="220" />
            </div>
            <div class="hint-primary">请使用微信扫码支付</div>
            <div class="hint-secondary">订单号：{{ order?.order_no }}</div>
            <el-divider />
            <div class="polling-note">
                <el-icon class="is-loading"><Loading /></el-icon>
                正在等待支付完成，请不要关闭此窗口
            </div>
        </div>

        <div v-else-if="channel === 'alipay'" class="text-center py-6">
            <div class="amount-preview">¥{{ order?.amount || 0 }}</div>
            <el-button type="primary" size="large" @click="openAlipayWindow">
                打开支付宝支付页
            </el-button>
            <el-divider />
            <div class="polling-note">
                <el-icon class="is-loading"><Loading /></el-icon>
                支付完成后会自动检测状态
            </div>
        </div>

        <template #footer>
            <el-button @click="handleClose">取消支付</el-button>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { CircleClose, Loading } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import QRCode from 'qrcode'
import { nextTick, onUnmounted, ref, watch } from 'vue'

import { subscriptionApi } from '@/api/subscription'
import type { PaymentData, SaasOrderInfo } from '@/types/api'

const props = defineProps<{
    visible: boolean
    order: SaasOrderInfo | null
    channel: 'wechat' | 'alipay'
}>()

const emit = defineEmits<{
    'update:visible': [v: boolean]
    success: []
}>()

const loading = ref(false)
const error = ref('')
const paymentData = ref<PaymentData | null>(null)
const qrCanvas = ref<HTMLCanvasElement | null>(null)
let pollTimer: ReturnType<typeof setInterval> | null = null
let alipayBlobUrl: string | null = null

function clearPoll() {
    if (pollTimer) {
        clearInterval(pollTimer)
        pollTimer = null
    }
}

function revokeBlob() {
    if (alipayBlobUrl) {
        URL.revokeObjectURL(alipayBlobUrl)
        alipayBlobUrl = null
    }
}

function handleClose() {
    clearPoll()
    revokeBlob()
    paymentData.value = null
    error.value = ''
    emit('update:visible', false)
}

async function kickOffPayment() {
    if (!props.order) return
    loading.value = true
    error.value = ''
    paymentData.value = null
    try {
        const res = await subscriptionApi.pay(props.order.id)
        paymentData.value = res.data.payment_data
        loading.value = false

        if (props.channel === 'wechat' && paymentData.value.trade_type === 'native') {
            await nextTick()
            const codeUrl = paymentData.value.data.code_url
            if (qrCanvas.value && codeUrl) {
                await QRCode.toCanvas(qrCanvas.value, codeUrl, { width: 220 })
            }
        }

        startPolling()
    } catch (e: any) {
        loading.value = false
        error.value = e.message || '发起支付失败'
    }
}

function startPolling() {
    if (!props.order) return
    clearPoll()
    pollTimer = setInterval(async () => {
        try {
            const res = await subscriptionApi.queryOrder(props.order!.id)
            const status = res.data.order.status
            if (status === 2) {
                clearPoll()
                emit('success')
            } else if (status === 3 || status === 4) {
                clearPoll()
                ElMessage.error('订单已关闭')
                handleClose()
            }
            // status=1 继续轮询
        } catch (e) {
            // 网络抖动不中断
        }
    }, 3000)
}

/**
 * 安全地打开支付宝支付页：
 *
 * AlipayDriver.create() 返回的 body 是一段完整 HTML（含 auto-submit 表单）。
 * 使用 Blob + URL.createObjectURL 在独立源中加载，完全隔离主应用上下文，
 * 避免将 innerHTML 直接注入当前页面带来的安全风险。
 * alipayBlobUrl 在关闭/unmount/重新发起支付三处通过 revokeObjectURL 释放内存。
 */
function openAlipayWindow() {
    if (!paymentData.value || paymentData.value.trade_type === 'native') return
    const body = paymentData.value.data.body
    if (!body) {
        ElMessage.error('支付宝返回内容为空')
        return
    }
    revokeBlob()
    const blob = new Blob([body], { type: 'text/html;charset=utf-8' })
    alipayBlobUrl = URL.createObjectURL(blob)
    window.open(alipayBlobUrl, '_blank')
}

watch(
    () => props.visible,
    (v) => {
        if (v && props.order) {
            kickOffPayment()
        } else if (!v) {
            clearPoll()
            revokeBlob()
        }
    }
)

onUnmounted(() => {
    clearPoll()
    revokeBlob()
})
</script>

<style scoped lang="scss">
.amount-preview {
    font-size: 28px;
    color: #f56c6c;
    font-weight: 600;
    margin-bottom: 16px;
}
.qr-box {
    display: flex;
    justify-content: center;
    padding: 16px;
    background: #fafafa;
    border-radius: 8px;
    margin-bottom: 12px;
}
.hint-primary {
    color: #606266;
    font-size: 14px;
    margin-top: 6px;
}
.hint-secondary {
    color: #909399;
    font-size: 12px;
    margin-top: 4px;
}
.polling-note {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    color: #909399;
    font-size: 13px;
}
</style>
