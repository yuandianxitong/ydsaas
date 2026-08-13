<template>
    <div class="audit-container">
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('audit.tenantId')">
                    <el-input
                        v-model="searchForm.tenant_id"
                        :placeholder="$t('audit.tenantIdPlaceholder')"
                        clearable
                        style="width: 120px"
                        @keyup.enter="handleSearch"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.search')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('audit.keywordPlaceholder')"
                        clearable
                        style="width: 200px"
                        @keyup.enter="handleSearch"
                    />
                </el-form-item>
                <el-form-item :label="$t('audit.requestMethod')">
                    <el-select
                        v-model="searchForm.method"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 110px"
                    >
                        <el-option label="GET" value="GET" />
                        <el-option label="POST" value="POST" />
                        <el-option label="PUT" value="PUT" />
                        <el-option label="DELETE" value="DELETE" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('audit.dateRange')">
                    <el-date-picker
                        v-model="dateRange"
                        type="daterange"
                        range-separator="~"
                        :start-placeholder="$t('common.startDate')"
                        :end-placeholder="$t('common.endDate')"
                        value-format="YYYY-MM-DD"
                        style="width: 240px"
                        @change="handleDateChange"
                    />
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="handleSearch">
                        <el-icon><Search /></el-icon>
                        {{ $t('common.search') }}
                    </el-button>
                    <el-button @click="handleReset">
                        <el-icon><Refresh /></el-icon>
                        {{ $t('common.reset') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ $t('audit.title') }}</div>
            </div>

            <el-table v-loading="loading" :data="list" stripe>
                <el-table-column label="ID" prop="id" width="80" />
                <el-table-column
                    :label="$t('audit.tenant')"
                    prop="tenant_name"
                    width="140"
                    show-overflow-tooltip
                >
                    <template #default="{ row }">
                        <span v-if="row.tenant_name">{{ row.tenant_name }}</span>
                        <el-text v-else type="info" size="small">-</el-text>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('audit.adminName')"
                    prop="admin_name"
                    width="140"
                    show-overflow-tooltip
                >
                    <template #default="{ row }">
                        <span v-if="row.admin_name || row.username">{{
                            row.admin_name || row.username
                        }}</span>
                        <el-text v-else type="info" size="small">-</el-text>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('audit.requestMethod')" width="100">
                    <template #default="{ row }">
                        <el-tag :type="methodTagType(row.method)" size="small" effect="light">
                            {{ row.method }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('audit.requestPath')"
                    prop="path"
                    min-width="200"
                    show-overflow-tooltip
                />
                <el-table-column label="IP" prop="ip" width="140" />
                <el-table-column :label="$t('audit.resultCode')" width="90">
                    <template #default="{ row }">
                        <el-tag
                            v-if="row.result_code != null && row.result_code !== ''"
                            :type="Number(row.result_code) === 200 ? 'success' : 'danger'"
                            size="small"
                        >
                            {{ row.result_code }}
                        </el-tag>
                        <el-text v-else type="info" size="small">-</el-text>
                    </template>
                </el-table-column>
                <el-table-column :label="$t('audit.duration')" width="100">
                    <template #default="{ row }">
                        {{
                            row.execution_time != null
                                ? (row.execution_time * 1000).toFixed(0)
                                : '-'
                        }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('audit.operationTime')" width="170">
                    <template #default="{ row }">
                        {{ row.operation_time || row.created_at || '-' }}
                    </template>
                </el-table-column>
            </el-table>

            <el-pagination
                v-model:current-page="pagination.page"
                v-model:page-size="pagination.limit"
                :total="pagination.total"
                :page-sizes="[15, 30, 50, 100]"
                layout="total, sizes, prev, pager, next, jumper"
                class="pagination"
                @size-change="handleSizeChange"
                @current-change="handlePageChange"
            />
        </el-card>
    </div>
</template>

<script setup lang="ts" name="AuditLog">
import { Refresh, Search } from '@element-plus/icons-vue'
import { ref } from 'vue'

import { auditApi } from '@/api/audit'
import { useListPage } from '@/hooks/useListPage'

const dateRange = ref<[string, string] | null>(null)

const {
    list,
    loading,
    pagination,
    searchForm,
    handleSearch,
    resetSearch,
    handleSizeChange,
    handlePageChange
} = useListPage<
    any,
    {
        tenant_id: string
        keyword: string
        method: string
        date_from: string
        date_to: string
    }
>({
    fetchFn: (params) => auditApi.logs(params),
    defaultSearchForm: {
        tenant_id: '',
        keyword: '',
        method: '',
        date_from: '',
        date_to: ''
    }
})

const handleDateChange = (val: [string, string] | null) => {
    if (val) {
        searchForm.date_from = val[0]
        searchForm.date_to = val[1]
    } else {
        searchForm.date_from = ''
        searchForm.date_to = ''
    }
}

const handleReset = () => {
    dateRange.value = null
    resetSearch()
}

const methodTagType = (method: string) => {
    const map: Record<string, string> = {
        GET: 'success',
        POST: 'primary',
        PUT: 'warning',
        DELETE: 'danger'
    }
    return (map[method] || 'info') as any
}
</script>

<style lang="scss" scoped>
.audit-container {
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
