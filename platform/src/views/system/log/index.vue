<template>
    <div class="log-container">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('system.log.title') }}</div>
                <div class="page-desc">{{ $t('system.log.desc') }}</div>
            </div>
        </div>

        <el-card class="search-card" shadow="never">
            <div class="search-bar">
                <div class="search-tabs">
                    <button :class="{ on: activeTab === 'login' }" @click="activeTab = 'login'">
                        {{ $t('system.log.loginLog') }}
                    </button>
                    <button
                        :class="{ on: activeTab === 'operation' }"
                        @click="activeTab = 'operation'"
                    >
                        {{ $t('system.log.operationLog') }}
                    </button>
                </div>

                <el-form
                    :model="activeTab === 'login' ? loginSearchForm : opSearchForm"
                    inline
                    class="search-form"
                >
                    <template v-if="activeTab === 'login'">
                        <el-form-item :label="$t('system.admin.username')">
                            <el-input
                                v-model="loginSearchForm.keyword"
                                :placeholder="$t('system.admin.username')"
                                clearable
                                style="width: 160px"
                                @keyup.enter="loginHandleSearch"
                            />
                        </el-form-item>
                        <el-form-item label="IP">
                            <el-input
                                v-model="loginSearchForm.ip"
                                :placeholder="$t('system.log.ip')"
                                clearable
                                style="width: 150px"
                                @keyup.enter="loginHandleSearch"
                            />
                        </el-form-item>
                        <el-form-item :label="$t('common.status')">
                            <el-select
                                v-model="loginSearchForm.login_result"
                                :placeholder="$t('common.all')"
                                clearable
                                style="width: 100px"
                            >
                                <el-option :label="$t('common.yes')" :value="1" />
                                <el-option :label="$t('common.no')" :value="0" />
                            </el-select>
                        </el-form-item>
                        <el-form-item>
                            <el-button type="primary" @click="loginHandleSearch">
                                <i class="i-svg:search" />
                                {{ $t('common.search') }}
                            </el-button>
                            <el-button @click="loginResetSearch">
                                <i class="i-svg:refresh-cw" />
                                {{ $t('common.reset') }}
                            </el-button>
                        </el-form-item>
                    </template>

                    <template v-else>
                        <el-form-item :label="$t('common.search')">
                            <el-input
                                v-model="opSearchForm.keyword"
                                :placeholder="$t('common.search')"
                                clearable
                                style="width: 200px"
                                @keyup.enter="opHandleSearch"
                            />
                        </el-form-item>
                        <el-form-item :label="$t('system.log.method')">
                            <el-select
                                v-model="opSearchForm.method"
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
                        <el-form-item>
                            <el-button type="primary" @click="opHandleSearch">
                                <i class="i-svg:search" />
                                {{ $t('common.search') }}
                            </el-button>
                            <el-button @click="opResetSearch">
                                <i class="i-svg:refresh-cw" />
                                {{ $t('common.reset') }}
                            </el-button>
                        </el-form-item>
                    </template>
                </el-form>
            </div>
        </el-card>

        <ProTable
            :title="tableTitle"
            :storage-key="tableStorageKey"
            :columns="tableColumns"
            :data="tableData"
            :loading="tableLoading"
            :pagination="tablePagination"
            @page-change="handlePageChange"
            @size-change="handleSizeChange"
        >
            <template #login_result="{ row }">
                <el-tag :type="row.login_result ? 'success' : 'danger'" size="small">
                    {{ row.login_result ? $t('common.yes') : $t('common.no') }}
                </el-tag>
            </template>
            <template #method="{ row }">
                <el-tag :type="methodTagType(row.method)" size="small" effect="light">
                    {{ row.method }}
                </el-tag>
            </template>
            <template #execution_time="{ row }">
                {{ row.execution_time ? (row.execution_time * 1000).toFixed(0) : '-' }}
            </template>
        </ProTable>
    </div>
</template>

<script setup lang="ts" name="PlatformLog">
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { platformLogApi } from '@/api/system'
import ProTable from '@/components/ProTable/index.vue'
import type { ProColumn } from '@/components/ProTable/types'
import { useListPage } from '@/hooks/useListPage'

const { t } = useI18n()
const activeTab = ref<'login' | 'operation'>('login')

const {
    list: loginList,
    loading: loginLoading,
    pagination: loginPagination,
    searchForm: loginSearchForm,
    handleSearch: loginHandleSearch,
    resetSearch: loginResetSearch,
    handleSizeChange: loginHandleSizeChange,
    handlePageChange: loginHandlePageChange
} = useListPage<any, { keyword: string; ip: string; login_result?: number }>({
    fetchFn: (params) => platformLogApi.loginLogs(params),
    defaultSearchForm: { keyword: '', ip: '', login_result: undefined }
})

const loginColumns: ProColumn[] = [
    { key: 'id', label: t('common.id'), prop: 'id', width: 80, required: true },
    { key: 'username', label: t('system.admin.username'), prop: 'username', width: 120 },
    { key: 'ip', label: t('system.log.loginIp'), prop: 'ip', width: 140 },
    {
        key: 'browser',
        label: t('system.log.browser'),
        prop: 'browser',
        width: 160,
        showOverflowTooltip: true
    },
    { key: 'os', label: t('system.log.os'), prop: 'os', width: 130 },
    { key: 'login_result', label: t('system.log.result'), width: 110 },
    {
        key: 'login_message',
        label: t('common.remark'),
        prop: 'login_message',
        minWidth: 160,
        showOverflowTooltip: true
    },
    { key: 'login_time', label: t('system.log.loginTime'), prop: 'login_time', width: 180 }
]

const {
    list: opList,
    loading: opLoading,
    pagination: opPagination,
    searchForm: opSearchForm,
    handleSearch: opHandleSearch,
    resetSearch: opResetSearch,
    handleSizeChange: opHandleSizeChange,
    handlePageChange: opHandlePageChange,
    getList: opGetList
} = useListPage<any, { keyword: string; method: string }>({
    fetchFn: (params) => platformLogApi.operationLogs(params),
    defaultSearchForm: { keyword: '', method: '' },
    immediate: false
})

const opColumns: ProColumn[] = [
    { key: 'id', label: t('common.id'), prop: 'id', width: 80, required: true },
    { key: 'username', label: t('system.log.operator'), prop: 'username', width: 120 },
    { key: 'method', label: t('system.log.method'), width: 110 },
    {
        key: 'path',
        label: t('system.log.url'),
        prop: 'path',
        minWidth: 200,
        showOverflowTooltip: true
    },
    { key: 'action', label: t('system.log.action'), prop: 'action', width: 120, required: true },
    {
        key: 'description',
        label: t('common.description'),
        prop: 'description',
        minWidth: 160,
        showOverflowTooltip: true
    },
    { key: 'ip', label: 'IP', prop: 'ip', width: 140 },
    { key: 'execution_time', label: t('system.log.duration'), width: 120 },
    { key: 'operation_time', label: t('system.log.operationTime'), prop: 'operation_time', width: 180 }
]

const tableTitle = computed(() =>
    activeTab.value === 'login' ? t('system.log.loginLog') : t('system.log.operationLog')
)
const tableStorageKey = computed(() =>
    activeTab.value === 'login' ? 'platform-login-log-list' : 'platform-operation-log-list'
)
const tableColumns = computed(() =>
    activeTab.value === 'login' ? loginColumns : opColumns
)
const tableData = computed(() => (activeTab.value === 'login' ? loginList.value : opList.value))
const tableLoading = computed(() =>
    activeTab.value === 'login' ? loginLoading.value : opLoading.value
)
const tablePagination = computed(() =>
    activeTab.value === 'login' ? loginPagination : opPagination
)

const handlePageChange = (page: number) => {
    if (activeTab.value === 'login') loginHandlePageChange(page)
    else opHandlePageChange(page)
}

const handleSizeChange = (size: number) => {
    if (activeTab.value === 'login') loginHandleSizeChange(size)
    else opHandleSizeChange(size)
}

watch(activeTab, (tab) => {
    if (tab === 'operation' && opList.value.length === 0) {
        opGetList()
    }
})

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
