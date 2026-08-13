<script setup lang="ts">
import { Refresh, Search } from '@element-plus/icons-vue'

import { pluginApi, type PluginOrder } from '@/api/plugin'
import { useListPage } from '@/hooks/useListPage'

const STATUS_TEXT: Record<number, string> = {
    1: '待支付',
    2: '已支付',
    3: '已取消',
    4: '已退款'
}

type TagType = 'success' | 'warning' | 'info' | 'danger' | 'primary'
const STATUS_TYPE: Record<number, TagType> = {
    1: 'warning',
    2: 'success',
    3: 'info',
    4: 'danger'
}

const CHANNEL_TEXT: Record<string, string> = {
    wechat: '微信',
    alipay: '支付宝'
}

const {
    list,
    loading,
    pagination,
    searchForm,
    handleSearch,
    resetSearch,
    handlePageChange,
    handleSizeChange
} = useListPage<PluginOrder, { keyword: string; status?: number }>({
    fetchFn: (params) => pluginApi.orders(params),
    defaultSearchForm: {
        keyword: '',
        status: undefined
    }
})

function formatAmount(v: string | number | null | undefined): string {
    const n = Number(v)
    if (Number.isNaN(n)) return '—'
    return `¥${n.toFixed(2)}`
}

function statusTagType(status: number): TagType {
    return STATUS_TYPE[status] ?? 'info'
}

function channelLabel(channel: string): string {
    return CHANNEL_TEXT[channel] || channel || '—'
}
</script>

<template>
    <div class="plugin-orders-container">
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form" @submit.prevent>
                <el-form-item label="关键词">
                    <el-input
                        v-model="searchForm.keyword"
                        placeholder="订单号"
                        clearable
                        style="width: 200px"
                        @keyup.enter="handleSearch"
                    />
                </el-form-item>
                <el-form-item label="状态">
                    <el-select
                        v-model="searchForm.status"
                        placeholder="全部状态"
                        clearable
                        style="width: 140px"
                    >
                        <el-option
                            v-for="(label, value) in STATUS_TEXT"
                            :key="value"
                            :label="label"
                            :value="Number(value)"
                        />
                    </el-select>
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

        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">购买记录</div>
            </div>

            <el-table v-loading="loading" :data="list">
                <el-table-column label="ID" prop="id" width="80" />
                <el-table-column
                    prop="order_no"
                    label="订单号"
                    min-width="180"
                    show-overflow-tooltip
                />
                <el-table-column
                    prop="plugin_name"
                    label="插件"
                    min-width="140"
                    show-overflow-tooltip
                />
                <el-table-column prop="months" label="月数" width="80" align="center" />
                <el-table-column label="金额" width="110" align="right">
                    <template #default="{ row }">
                        {{ formatAmount(row.amount) }}
                    </template>
                </el-table-column>
                <el-table-column label="实付" width="110" align="right">
                    <template #default="{ row }">
                        {{ formatAmount(row.paid_amount) }}
                    </template>
                </el-table-column>
                <el-table-column label="支付渠道" width="100" align="center">
                    <template #default="{ row }">
                        {{ channelLabel(row.payment_channel) }}
                    </template>
                </el-table-column>
                <el-table-column label="状态" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)" size="small" effect="light">
                            {{ STATUS_TEXT[row.status] ?? row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="支付时间" prop="paid_at" width="170">
                    <template #default="{ row }">
                        {{ row.paid_at || '—' }}
                    </template>
                </el-table-column>
                <el-table-column label="创建时间" prop="created_at" width="170" />
                <template #empty>
                    <el-empty description="暂无购买记录" />
                </template>
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
