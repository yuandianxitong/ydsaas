<template>
    <div class="order-container">
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
                        <el-icon><Search /></el-icon>
                        {{ $t('common.search') }}
                    </el-button>
                    <el-button @click="resetSearch">
                        <el-icon><Refresh /></el-icon>
                        {{ $t('common.reset') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 表格 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ $t('order.title') }}</div>
            </div>

            <el-table v-loading="loading" :data="list" stripe>
                <el-table-column label="ID" prop="id" width="70" />
                <el-table-column
                    :label="$t('order.orderNo')"
                    prop="order_no"
                    min-width="180"
                    show-overflow-tooltip
                />
                <el-table-column :label="$t('order.tenant')" min-width="150" show-overflow-tooltip>
                    <template #default="{ row }">
                        {{ row.tenant_name || `#${row.tenant_id}` }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('order.plan')" min-width="130" show-overflow-tooltip>
                    <template #default="{ row }">
                        {{ row.plan_name || row.plugin_name || '—' }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('order.months')" width="90" align="center">
                    <template #default="{ row }">
                        {{ row.months }} {{ $t('order.monthsUnit') }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('order.amount')" width="100" align="right">
                    <template #default="{ row }">¥{{ row.amount }}</template>
                </el-table-column>
                <el-table-column
                    :label="$t('order.paymentChannel')"
                    prop="payment_channel"
                    width="90"
                    align="center"
                />
                <el-table-column :label="$t('common.status')" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('common.createdAt')"
                    prop="created_at"
                    min-width="160"
                />
                <el-table-column :label="$t('common.operation')" width="110" fixed="right">
                    <template #default="{ row }">
                        <el-button link type="primary" @click="handleShow(row)">
                            {{ $t('common.detail') }}
                        </el-button>
                    </template>
                </el-table-column>
            </el-table>

            <div class="pagination">
                <el-pagination
                    v-model:current-page="pagination.page"
                    v-model:page-size="pagination.limit"
                    :total="pagination.total"
                    :page-sizes="[10, 20, 50, 100]"
                    layout="total, sizes, prev, pager, next, jumper"
                    @size-change="handleSizeChange"
                    @current-change="handlePageChange"
                />
            </div>
        </el-card>

        <!-- 详情弹窗 -->
        <OrderDetail :id="currentId" v-model:visible="detailVisible" @refresh="getList" />
    </div>
</template>

<script setup lang="ts" name="OrderList">
import { Refresh, Search } from '@element-plus/icons-vue'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { orderApi } from '@/api/order'
import { planApi } from '@/api/plan'
import { tenantApi } from '@/api/tenant'
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

<style lang="scss" scoped>
.order-container {
    /* 外层 LayoutMain 已有 padding，这里不重复 */
    .search-card {
        margin-bottom: 16px;
    }

    .table-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 16px;

        .table-title {
            font-size: 16px;
            font-weight: 600;
        }
    }

    .pagination {
        margin-top: 16px;
        display: flex;
        justify-content: flex-end;
    }
}
</style>
