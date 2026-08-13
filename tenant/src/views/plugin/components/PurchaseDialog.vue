<script setup lang="ts">
import { ElMessage } from 'element-plus'
import { ref, watch } from 'vue'

import { pluginApi } from '@/api/plugin'

const props = defineProps<{ pluginId: number; pluginCode: string; pluginName: string }>()
const visible = defineModel<boolean>('visible', { required: true })
const emit = defineEmits<{ purchased: [] }>()

const months = ref(1)
const amount = ref(0)
const channel = ref<'wechat' | 'alipay'>('wechat')
const submitting = ref(false)

watch(visible, (v) => {
    if (v) {
        months.value = 1
        amount.value = 0
        channel.value = 'wechat'
    }
})

async function submit() {
    if (months.value <= 0) {
        ElMessage.error('月数必须 > 0')
        return
    }
    if (amount.value <= 0) {
        ElMessage.error('金额必须 > 0')
        return
    }
    submitting.value = true
    try {
        const res = await pluginApi.purchase(props.pluginId, {
            months: months.value,
            amount: amount.value,
            channel: channel.value,
            method: 'native'
        })
        ElMessage.success(`订单已创建：${res.data.order.order_no}`)
        visible.value = false
        emit('purchased')
        // 实际项目可在此打开支付二维码弹窗 — 本期仅创建订单即可
    } finally {
        submitting.value = false
    }
}
</script>

<template>
    <el-dialog v-model="visible" :title="`购买插件 — ${pluginName}`" class="dlg-sm">
        <el-form label-width="80px">
            <el-form-item label="插件 Code">{{ pluginCode }}</el-form-item>
            <el-form-item label="月数">
                <el-input-number v-model="months" :min="1" :max="36" />
            </el-form-item>
            <el-form-item label="金额">
                <el-input-number v-model="amount" :min="0" :precision="2" :step="10" />
                <span class="unit-hint">元（含税）</span>
            </el-form-item>
            <el-form-item label="支付方式">
                <el-radio-group v-model="channel">
                    <el-radio value="wechat">微信</el-radio>
                    <el-radio value="alipay">支付宝</el-radio>
                </el-radio-group>
            </el-form-item>
        </el-form>
        <template #footer>
            <el-button @click="visible = false">取消</el-button>
            <el-button type="primary" :loading="submitting" @click="submit">下单</el-button>
        </template>
    </el-dialog>
</template>

<style scoped>
.unit-hint {
    margin-left: 8px;
    font-size: var(--font-size-caption);
    color: var(--color-text-tertiary);
}
</style>
