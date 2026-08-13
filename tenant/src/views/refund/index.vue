<template>
    <div class="refund-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item label="状态">
                    <el-select
                        v-model="searchForm.status"
                        placeholder="全部"
                        clearable
                        style="width: 130px"
                    >
                        <el-option label="处理中" value="processing" />
                        <el-option label="退款成功" value="success" />
                        <el-option label="退款失败" value="failed" />
                    </el-select>
                </el-form-item>
                <el-form-item label="关键词">
                    <el-input
                        v-model="searchForm.keyword"
                        placeholder="退款单号"
                        clearable
                        style="width: 220px"
                        @keyup.enter="handleSearch"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">
                        <el-icon><Search /></el-icon>
                        搜索
                    </el-button>
                    <el-button @click="resetSearch">
                        <el-icon><Refresh /></el-icon>
                        重置
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <!-- 列表区域 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">退款记录</div>
            </div>

            <el-table v-loading="loading" :data="list" stripe>
                <el-table-column label="ID" prop="id" width="80" />

                <el-table-column
                    label="退款单号"
                    prop="refund_no"
                    min-width="200"
                    show-overflow-tooltip
                />

                <el-table-column label="退款金额" width="120">
                    <template #default="{ row }">
                        ¥{{ Number(row.refund_amount).toFixed(2) }}
                    </template>
                </el-table-column>

                <el-table-column label="支付渠道" width="110">
                    <template #default="{ row }">
                        <el-tag size="small" effect="light">
                            {{ channelLabel(row.payment_channel) }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column label="状态" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)" size="small">
                            {{ statusLabel(row.status) }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column
                    label="退款原因"
                    prop="reason"
                    min-width="160"
                    show-overflow-tooltip
                />

                <el-table-column label="申请时间" prop="created_at" width="170" />

                <el-table-column label="完成时间" width="170">
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

<script setup lang="ts" name="TenantRefundList">
import { Refresh, Search } from '@element-plus/icons-vue'

import { refundApi } from '@/api/refund'
import { useListPage } from '@/hooks/useListPage'

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
        processing: '处理中',
        success: '退款成功',
        failed: '退款失败'
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
        wechat: '微信支付',
        alipay: '支付宝',
        manual: '手动'
    }
    return map[channel] || channel || '—'
}
</script>

<style lang="scss" scoped>
.refund-container {
    padding: 16px;

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
