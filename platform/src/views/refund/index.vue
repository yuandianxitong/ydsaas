<template>
    <div class="refund-container">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('refund.title') }}</div>
                <div class="page-desc">{{ $t('refund.desc') }}</div>
            </div>
        </div>

        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 130px"
                    >
                        <el-option :label="$t('refund.processing')" value="processing" />
                        <el-option :label="$t('refund.success')" value="success" />
                        <el-option :label="$t('refund.failed')" value="failed" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('common.search')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('refund.searchPlaceholder')"
                        clearable
                        style="width: 220px"
                        @keyup.enter="handleSearch"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">
                        <i class="i-svg:search" />
                        {{ $t('common.search') }}
                    </el-button>
                    <el-button @click="resetSearch">
                        <i class="i-svg:refresh-cw" />
                        {{ $t('common.reset') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <ProTable
            :title="$t('refund.title')"
            storage-key="platform-refund-list"
            :columns="columns"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            @page-change="handlePageChange"
            @size-change="handleSizeChange"
        >
            <template #refund_amount="{ row }">
                ¥{{ (row.refund_amount / 100).toFixed(2) }}
            </template>
            <template #payment_channel="{ row }">
                <el-tag size="small" effect="light">
                    {{ channelLabel(row.payment_channel) }}
                </el-tag>
            </template>
            <template #status="{ row }">
                <el-tag :type="statusTagType(row.status)" size="small">
                    {{ statusLabel(row.status) }}
                </el-tag>
            </template>
            <template #refunded_at="{ row }">
                {{ row.refunded_at || '—' }}
            </template>
        </ProTable>
    </div>
</template>

<script setup lang="ts" name="PlatformRefundList">
import { useI18n } from 'vue-i18n'

import { refundApi } from '@/api/refund'
import ProTable from '@/components/ProTable/index.vue'
import type { ProColumn } from '@/components/ProTable/types'
import { useListPage } from '@/hooks/useListPage'

const { t } = useI18n()

interface RefundSearchForm {
    status: string
    keyword: string
}

const {
    list,
    loading,
    pagination,
    searchForm,
    handleSearch,
    resetSearch,
    handleSizeChange,
    handlePageChange
} = useListPage<any, RefundSearchForm>({
    fetchFn: (params) => refundApi.list(params),
    defaultSearchForm: {
        status: '',
        keyword: ''
    }
})

const columns: ProColumn[] = [
    { key: 'id', label: 'ID', prop: 'id', width: 80, required: true },
    {
        key: 'refund_no',
        label: t('refund.refundNo'),
        prop: 'refund_no',
        minWidth: 200,
        showOverflowTooltip: true
    },
    { key: 'tenant_id', label: t('order.tenantId'), prop: 'tenant_id', width: 100 },
    { key: 'refund_amount', label: t('refund.amount'), width: 120 },
    { key: 'payment_channel', label: t('order.paymentChannel'), width: 110 },
    { key: 'status', label: t('common.status'), width: 100, align: 'center' },
    {
        key: 'reason',
        label: t('refund.reason'),
        prop: 'reason',
        minWidth: 160,
        showOverflowTooltip: true
    },
    { key: 'created_at', label: t('refund.applyTime'), prop: 'created_at', width: 170 },
    { key: 'refunded_at', label: t('refund.completedTime'), width: 170 }
]

const statusLabel = (status: string) => {
    const map: Record<string, string> = {
        processing: t('refund.processing'),
        success: t('refund.success'),
        failed: t('refund.failed')
    }
    return map[status] || status
}

type ElTagType = 'primary' | 'success' | 'info' | 'warning' | 'danger'
const statusTagType = (status: string): ElTagType => {
    const map: Record<string, ElTagType> = {
        processing: 'warning',
        success: 'success',
        failed: 'danger'
    }
    return map[status] || 'info'
}

const channelLabel = (channel: string) => {
    const map: Record<string, string> = {
        wechat: t('order.wechat'),
        alipay: t('order.alipay'),
        manual: t('refund.manual')
    }
    return map[channel] || channel || '—'
}
</script>

