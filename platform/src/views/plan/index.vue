<template>
    <div class="plan-container">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('plan.title') }}</div>
                <div class="page-desc">{{ $t('plan.desc') }}</div>
            </div>
            <div class="page-actions">
                <el-button type="primary" @click="handleAdd">
                    <i class="i-svg:plus" />
                    {{ $t('plan.addPlan') }}
                </el-button>
            </div>
        </div>

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
            :title="$t('plan.title')"
            storage-key="platform-plan-list"
            :columns="columns"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            :batch-delete-fn="handleBatchDelete"
            @page-change="handlePageChange"
            @size-change="handleSizeChange"
        >
            <template #price_monthly="{ row }">¥{{ row.price_monthly }}</template>
            <template #price_yearly="{ row }">¥{{ row.price_yearly }}</template>
            <template #storage_limit_bytes="{ row }">
                {{ formatBytes(row.storage_limit_bytes) }}
            </template>
            <template #status="{ row }">
                <el-tag :type="row.status === 1 ? 'success' : 'info'">
                    {{ row.status === 1 ? $t('common.enabled') : $t('common.disabled') }}
                </el-tag>
            </template>
            <template #action="{ row }">
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
        </ProTable>

        <!-- 新增/编辑弹窗 -->
        <PlanForm v-model="formVisible" :source-id="currentId" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="PlanList">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { planApi } from '@/api/plan'
import ProTable from '@/components/ProTable/index.vue'
import type { ProColumn } from '@/components/ProTable/types'
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
    handleDelete,
    handleBatchDelete
} = useListPage<PlanInfo, PlanSearchForm>({
    fetchFn: (params) => planApi.list(params),
    deleteFn: (id) => planApi.destroy(id),
    batchDeleteFn: (ids) => Promise.all(ids.map((id) => planApi.destroy(id))),
    defaultSearchForm: {
        keyword: '',
        status: ''
    }
})

const { t } = useI18n()

const columns: ProColumn[] = [
    { key: 'id', label: 'ID', prop: 'id', width: 80, required: true },
    { key: 'code', label: t('plan.planCode'), prop: 'code', minWidth: 100, showOverflowTooltip: true },
    { key: 'name', label: t('plan.planName'), prop: 'name', minWidth: 150, showOverflowTooltip: true },
    { key: 'price_monthly', label: t('plan.priceMonthly'), prop: 'price_monthly', width: 120 },
    { key: 'price_yearly', label: t('plan.priceYearly'), prop: 'price_yearly', width: 120 },
    { key: 'storage_limit_bytes', label: t('plan.storageLimit'), prop: 'storage_limit_bytes', width: 140 },
    { key: 'sort', label: t('common.sort'), prop: 'sort', width: 90, align: 'center' },
    { key: 'status', label: t('common.status'), width: 100, align: 'center' },
    { key: 'created_at', label: t('common.createdAt'), prop: 'created_at', minWidth: 160 },
    { key: 'action', label: t('common.operation'), width: 180, fixed: 'right', required: true }
]

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
.text-gray-400 {
    color: #9ca3af;
}
</style>
