<template>
    <div class="order-container">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('order.title') }}</div>
                <div class="page-desc">{{ $t('order.desc') }}</div>
            </div>
        </div>

        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('order.orderNo')">
                    <el-input
                        v-model="searchForm.order_no"
                        :placeholder="$t('order.orderNoPlaceholder')"
                        clearable
                        style="width: 220px"
                        @keyup.enter="handleSearch"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 140px"
                    >
                        <el-option :label="$t('order.unpaid')" :value="1" />
                        <el-option :label="$t('order.paid')" :value="2" />
                        <el-option :label="$t('order.cancelled')" :value="3" />
                        <el-option :label="$t('order.refunded')" :value="4" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('order.tenant')">
                    <el-select
                        v-model="searchForm.tenant_id"
                        :placeholder="$t('common.all')"
                        clearable
                        filterable
                        remote
                        :remote-method="searchTenants"
                        :loading="tenantSearching"
                        style="width: 220px"
                    >
                        <el-option
                            v-for="tn in tenantOptions"
                            :key="tn.id"
                            :label="`${tn.name} (${tn.tenant_code})`"
                            :value="tn.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('order.plan')">
                    <el-select
                        v-model="searchForm.plan_id"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 160px"
                    >
                        <el-option
                            v-for="p in planOptions"
                            :key="p.id"
                            :label="p.name"
                            :value="p.id"
                        />
                    </el-select>
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
            :title="$t('order.title')"
            storage-key="platform-order-list"
            :columns="columns"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            @page-change="handlePageChange"
            @size-change="handleSizeChange"
        >
            <template #tenant="{ row }">
                {{ row.tenant_name || `#${row.tenant_id}` }}
            </template>
            <template #plan="{ row }">
                {{ row.plan_name || row.plugin_name || '—' }}
            </template>
            <template #months="{ row }">
                {{ row.months }} {{ $t('order.monthsUnit') }}
            </template>
            <template #amount="{ row }">¥{{ row.amount }}</template>
            <template #status="{ row }">
                <el-tag :type="statusTagType(row.status)">
                    {{ statusLabel(row.status) }}
                </el-tag>
            </template>
            <template #action="{ row }">
                <el-button link type="primary" @click="handleShow(row)">
                    {{ $t('common.detail') }}
                </el-button>
            </template>
        </ProTable>

        <!-- 详情弹窗 -->
        <OrderDetail :id="currentId" v-model:visible="detailVisible" @refresh="getList" />
    </div>
</template>

<script setup lang="ts" name="OrderList">
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { orderApi } from '@/api/order'
import { planApi } from '@/api/plan'
import { tenantApi } from '@/api/tenant'
import ProTable from '@/components/ProTable/index.vue'
import type { ProColumn } from '@/components/ProTable/types'
import { useListPage } from '@/hooks/useListPage'
import type { OrderQuery, PlanInfo, SaasOrderInfo, TenantInfo } from '@/types/api'

import OrderDetail from './components/OrderDetail.vue'

const { t } = useI18n()

interface OrderSearchForm {
    order_no: string
    status: number | ''
    tenant_id: number | undefined
    plan_id: number | ''
}

const {
    list,
    loading,
    pagination,
    searchForm,
    getList,
    handleSearch,
    resetSearch,
    handleSizeChange,
    handlePageChange
} = useListPage<SaasOrderInfo, OrderSearchForm>({
    fetchFn: (params) => orderApi.list(params as OrderQuery),
    defaultSearchForm: {
        order_no: '',
        status: '',
        tenant_id: undefined,
        plan_id: ''
    }
})

const columns: ProColumn[] = [
    { key: 'id', label: 'ID', prop: 'id', width: 70, required: true },
    {
        key: 'order_no',
        label: t('order.orderNo'),
        prop: 'order_no',
        minWidth: 180,
        showOverflowTooltip: true
    },
    { key: 'tenant', label: t('order.tenant'), minWidth: 150, showOverflowTooltip: true },
    { key: 'plan', label: t('order.plan'), minWidth: 130, showOverflowTooltip: true },
    { key: 'months', label: t('order.months'), width: 110, align: 'center' },
    { key: 'amount', label: t('order.amount'), width: 100, align: 'right' },
    {
        key: 'payment_channel',
        label: t('order.paymentChannel'),
        prop: 'payment_channel',
        width: 120,
        align: 'center'
    },
    { key: 'status', label: t('common.status'), width: 110, align: 'center' },
    { key: 'created_at', label: t('common.createdAt'), prop: 'created_at', minWidth: 160 },
    { key: 'action', label: t('common.operation'), width: 110, fixed: 'right', required: true }
]

// 租户远程搜索下拉（按名称/code 模糊搜，避免记 ID）
const tenantOptions = ref<TenantInfo[]>([])
const tenantSearching = ref(false)

async function searchTenants(keyword = '') {
    tenantSearching.value = true
    try {
        const res = await tenantApi.list({ page: 1, limit: 20, keyword })
        tenantOptions.value = res.data.list || []
    } finally {
        tenantSearching.value = false
    }
}

// 套餐筛选下拉
const planOptions = ref<PlanInfo[]>([])

onMounted(async () => {
    searchTenants()
    try {
        const res = await planApi.options()
        planOptions.value = res.data || []
    } catch {
        planOptions.value = []
    }
})

// 详情弹窗
const detailVisible = ref(false)
const currentId = ref<number | undefined>(undefined)

function handleShow(row: SaasOrderInfo) {
    currentId.value = row.id
    detailVisible.value = true
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

