<template>
    <div class="plan-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('common.search')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('plan.searchPlaceholder')"
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
                        <el-option :label="$t('common.enable')" :value="1" />
                        <el-option :label="$t('common.disable')" :value="0" />
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

        <!-- 操作区域 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ $t('plan.title') }}</div>
                <div class="table-actions">
                    <el-button type="primary" @click="handleAdd">
                        <el-icon><Plus /></el-icon>
                        {{ $t('plan.addPlan') }}
                    </el-button>
                </div>
            </div>

            <!-- 表格 -->
            <el-table v-loading="loading" :data="list" stripe>
                <el-table-column label="ID" prop="id" width="80" />

                <el-table-column
                    :label="$t('plan.planCode')"
                    prop="code"
                    min-width="100"
                    show-overflow-tooltip
                />

                <el-table-column
                    :label="$t('plan.planName')"
                    prop="name"
                    min-width="150"
                    show-overflow-tooltip
                />

                <el-table-column :label="$t('plan.priceMonthly')" prop="price_monthly" width="120">
                    <template #default="{ row }"> ¥{{ row.price_monthly }} </template>
                </el-table-column>

                <el-table-column :label="$t('plan.priceYearly')" prop="price_yearly" width="120">
                    <template #default="{ row }"> ¥{{ row.price_yearly }} </template>
                </el-table-column>

                <el-table-column
                    :label="$t('plan.storageLimit')"
                    prop="storage_limit_bytes"
                    width="140"
                >
                    <template #default="{ row }">
                        {{ formatBytes(row.storage_limit_bytes) }}
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.sort')" prop="sort" width="80" align="center" />

                <el-table-column :label="$t('common.status')" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="row.status === 1 ? 'success' : 'info'">
                            {{ row.status === 1 ? $t('common.enabled') : $t('common.disabled') }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column
                    :label="$t('common.createdAt')"
                    prop="created_at"
                    min-width="160"
                />

                <el-table-column :label="$t('common.operation')" width="180" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" size="small" text @click="handleEdit(row)">
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-popconfirm
                            :title="$t('common.deleteConfirm')"
                            :confirm-button-text="$t('common.confirm')"
                            :cancel-button-text="$t('common.cancel')"
                            @confirm="handleDelete(row.id, row.name)"
                        >
                            <template #reference>
                                <el-button type="danger" size="small" text>
                                    {{ $t('common.delete') }}
                                </el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>

            <!-- 分页 -->
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

        <!-- 新增/编辑弹窗 -->
        <PlanForm v-model="formVisible" :source-id="currentId" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="PlanList">
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ref } from 'vue'

import { planApi } from '@/api/plan'
import { useListPage } from '@/hooks/useListPage'
import type { PlanInfo } from '@/types/api'

import PlanForm from './components/PlanForm.vue'

interface PlanSearchForm {
    keyword: string
    status: number | ''
}

// 列表 composable
const {
    list,
    loading,
    pagination,
    searchForm,
    getList,
    handleSearch,
    resetSearch,
    handleSizeChange,
    handlePageChange,
    handleDelete
} = useListPage<PlanInfo, PlanSearchForm>({
    fetchFn: (params) => planApi.list(params),
    deleteFn: (id) => planApi.destroy(id),
    defaultSearchForm: {
        keyword: '',
        status: ''
    }
})

// 弹窗状态
const formVisible = ref(false)
const currentId = ref<number | undefined>(undefined)

const handleAdd = () => {
    currentId.value = undefined
    formVisible.value = true
}

const handleEdit = (row: PlanInfo) => {
    currentId.value = row.id
    formVisible.value = true
}

// bytes 格式化（兼容 number/string 两种类型，后端 BigInt 列可能返回 string）
function formatBytes(bytes: number | string | undefined | null): string {
    if (bytes === null || bytes === undefined || bytes === '') return '-'
    const n = typeof bytes === 'string' ? Number(bytes) : bytes
    if (!Number.isFinite(n) || n < 0) return '-'
    if (n >= 1073741824) return (n / 1073741824).toFixed(1) + ' GB'
    if (n >= 1048576) return (n / 1048576).toFixed(1) + ' MB'
    if (n >= 1024) return (n / 1024).toFixed(1) + ' KB'
    return n + ' B'
}
</script>

<style lang="scss" scoped>
.plan-container {
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

    .text-gray-400 {
        color: #9ca3af;
    }
}
</style>
