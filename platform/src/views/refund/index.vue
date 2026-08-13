<template>
    <div class="refund-container">
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

        <!-- 列表区域 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ $t('refund.title') }}</div>
            </div>

            <el-table v-loading="loading" :data="list" stripe>
                <el-table-column label="ID" prop="id" width="80" />

                <el-table-column
                    :label="$t('refund.refundNo')"
                    prop="refund_no"
                    min-width="200"
                    show-overflow-tooltip
                />

                <el-table-column :label="$t('order.tenantId')" prop="tenant_id" width="90" />

                <el-table-column :label="$t('refund.amount')" width="120">
                    <template #default="{ row }">
                        ¥{{ (row.refund_amount / 100).toFixed(2) }}
                    </template>
                </el-table-column>

                <el-table-column :label="$t('order.paymentChannel')" width="110">
                    <template #default="{ row }">
                        <el-tag size="small" effect="light">
                            {{ channelLabel(row.payment_channel) }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.status')" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column
                    :label="$t('refund.reason')"
                    prop="reason"
                    min-width="160"
                    show-overflow-tooltip
                />

                <el-table-column :label="$t('refund.applyTime')" prop="created_at" width="170" />

                <el-table-column :label="$t('refund.completedTime')" width="170">
                    <template #default="{ row }">
                        {{ row.refunded_at || '—' }}
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[10, 20, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                class="pagination"
                @size-change="handleSizeChange"
                @current-change="handlePageChange"
            />
        </el-card>
    </div>
</template>

<script setup lang="ts" name="PlatformRefundList">
import { Refresh, Search } from '@element-plus/icons-vue'
import { useI18n } from 'vue-i18n'

import { refundApi } from '@/api/refund'
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

<style lang="scss" scoped>
.refund-container {

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
