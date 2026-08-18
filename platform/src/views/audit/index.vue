<template>
    <div class="audit-container">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('audit.title') }}</div>
                <div class="page-desc">{{ $t('audit.desc') }}</div>
            </div>
        </div>

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
                        <i class="i-svg:search" />
                        {{ $t('common.search') }}
                    </el-button>
                    <el-button @click="handleReset">
                        <i class="i-svg:refresh-cw" />
                        {{ $t('common.reset') }}
                    </el-button>
                </el-form-item>
            </el-form>
        </el-card>

        <ProTable
            :title="$t('audit.title')"
            storage-key="platform-audit-list"
            :columns="columns"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            @page-change="handlePageChange"
            @size-change="handleSizeChange"
        >
            <template #tenant_name="{ row }">
                <span v-if="row.tenant_name">{{ row.tenant_name }}</span>
                <el-text v-else type="info" size="small">-</el-text>
            </template>
            <template #admin_name="{ row }">
                <span v-if="row.admin_name || row.username">{{
                    row.admin_name || row.username
                }}</span>
                <el-text v-else type="info" size="small">-</el-text>
            </template>
            <template #method="{ row }">
                <el-tag :type="methodTagType(row.method)" size="small" effect="light">
                    {{ row.method }}
                </el-tag>
            </template>
            <template #result_code="{ row }">
                <el-tag
                    v-if="row.result_code != null && row.result_code !== ''"
                    :type="Number(row.result_code) === 200 ? 'success' : 'danger'"
                    size="small"
                >
                    {{ row.result_code }}
                </el-tag>
                <el-text v-else type="info" size="small">-</el-text>
            </template>
            <template #execution_time="{ row }">
                {{
                    row.execution_time != null
                        ? (row.execution_time * 1000).toFixed(0)
                        : '-'
                }}
            </template>
            <template #operation_time="{ row }">
                {{ row.operation_time || row.created_at || '-' }}
            </template>
        </ProTable>
    </div>
</template>

<script setup lang="ts" name="AuditLog">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { auditApi } from '@/api/audit'
import ProTable from '@/components/ProTable/index.vue'
import type { ProColumn } from '@/components/ProTable/types'
import { useListPage } from '@/hooks/useListPage'

const { t } = useI18n()

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

const columns: ProColumn[] = [
    { key: 'id', label: 'ID', prop: 'id', width: 80, required: true },
    {
        key: 'tenant_name',
        label: t('audit.tenant'),
        prop: 'tenant_name',
        width: 140,
        showOverflowTooltip: true
    },
    {
        key: 'admin_name',
        label: t('audit.adminName'),
        prop: 'admin_name',
        width: 140,
        showOverflowTooltip: true
    },
    { key: 'method', label: t('audit.requestMethod'), width: 110 },
    {
        key: 'path',
        label: t('audit.requestPath'),
        prop: 'path',
        minWidth: 200,
        showOverflowTooltip: true
    },
    { key: 'ip', label: 'IP', prop: 'ip', width: 140 },
    { key: 'result_code', label: t('audit.resultCode'), width: 100 },
    { key: 'execution_time', label: t('audit.duration'), width: 140 },
    { key: 'operation_time', label: t('audit.operationTime'), width: 180 }
]

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

