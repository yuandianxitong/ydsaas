<template>
    <div class="log-container">
        <el-tabs v-model="activeTab" class="log-tabs">
            <!-- 登录日志 -->
            <el-tab-pane :label="$t('system.log.loginLog')" name="login">
                <el-card class="search-card" shadow="never">
                    <el-form :model="loginSearchForm" inline class="search-form">
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
                                <el-icon><Search /></el-icon>
                                {{ $t('common.search') }}
                            </el-button>
                            <el-button @click="loginResetSearch">
                                <el-icon><Refresh /></el-icon>
                                {{ $t('common.reset') }}
                            </el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <el-card class="table-card" shadow="never">
                    <div class="table-header">
                        <div class="table-title">{{ $t('system.log.loginLog') }}</div>
                    </div>

                    <el-table v-loading="loginLoading" :data="loginList" stripe>
                        <el-table-column :label="$t('common.id')" prop="id" width="80" />
                        <el-table-column
                            :label="$t('system.admin.username')"
                            prop="username"
                            width="120"
                        />
                        <el-table-column :label="$t('system.log.loginIp')" prop="ip" width="140" />
                        <el-table-column
                            :label="$t('system.log.browser')"
                            prop="browser"
                            width="160"
                            show-overflow-tooltip
                        />
                        <el-table-column :label="$t('system.log.os')" prop="os" width="130" />
                        <el-table-column :label="$t('system.log.result')" width="100">
                            <template #default="{ row }">
                                <el-tag
                                    :type="row.login_result ? 'success' : 'danger'"
                                    size="small"
                                >
                                    {{ row.login_result ? $t('common.yes') : $t('common.no') }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column
                            :label="$t('common.remark')"
                            prop="login_message"
                            min-width="160"
                            show-overflow-tooltip
                        />
                        <el-table-column
                            :label="$t('system.log.loginTime')"
                            prop="login_time"
                            width="170"
                        />
                    </el-table>

                    <el-pagination
                        v-model:current-page="loginPagination.page"
                        v-model:page-size="loginPagination.limit"
                        :total="loginPagination.total"
                        :page-sizes="[10, 20, 50, 100]"
                        layout="total, sizes, prev, pager, next, jumper"
                        class="pagination"
                        @size-change="loginHandleSizeChange"
                        @current-change="loginHandlePageChange"
                    />
                </el-card>
            </el-tab-pane>

            <!-- 操作日志 -->
            <el-tab-pane :label="$t('system.log.operationLog')" name="operation">
                <el-card class="search-card" shadow="never">
                    <el-form :model="opSearchForm" inline class="search-form">
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
                                <el-icon><Search /></el-icon>
                                {{ $t('common.search') }}
                            </el-button>
                            <el-button @click="opResetSearch">
                                <el-icon><Refresh /></el-icon>
                                {{ $t('common.reset') }}
                            </el-button>
                        </el-form-item>
                    </el-form>
                </el-card>

                <el-card class="table-card" shadow="never">
                    <div class="table-header">
                        <div class="table-title">{{ $t('system.log.operationLog') }}</div>
                    </div>

                    <el-table v-loading="opLoading" :data="opList" stripe>
                        <el-table-column :label="$t('common.id')" prop="id" width="80" />
                        <el-table-column
                            :label="$t('system.log.operator')"
                            prop="username"
                            width="120"
                        />
                        <el-table-column :label="$t('system.log.method')" width="100">
                            <template #default="{ row }">
                                <el-tag
                                    :type="methodTagType(row.method)"
                                    size="small"
                                    effect="light"
                                >
                                    {{ row.method }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column
                            :label="$t('system.log.url')"
                            prop="path"
                            min-width="200"
                            show-overflow-tooltip
                        />
                        <el-table-column
                            :label="$t('system.log.action')"
                            prop="action"
                            width="120"
                        />
                        <el-table-column
                            :label="$t('common.description')"
                            prop="description"
                            min-width="160"
                            show-overflow-tooltip
                        />
                        <el-table-column label="IP" prop="ip" width="140" />
                        <el-table-column :label="$t('system.log.duration')" width="100">
                            <template #default="{ row }">
                                {{
                                    row.execution_time
                                        ? (row.execution_time * 1000).toFixed(0)
                                        : '-'
                                }}
                            </template>
                        </el-table-column>
                        <el-table-column
                            :label="$t('system.log.operationTime')"
                            prop="operation_time"
                            width="170"
                        />
                    </el-table>

                    <el-pagination
                        v-model:current-page="opPagination.page"
                        v-model:page-size="opPagination.limit"
                        :total="opPagination.total"
                        :page-sizes="[10, 20, 50, 100]"
                        layout="total, sizes, prev, pager, next, jumper"
                        class="pagination"
                        @size-change="opHandleSizeChange"
                        @current-change="opHandlePageChange"
                    />
                </el-card>
            </el-tab-pane>
        </el-tabs>
    </div>
</template>

<script setup lang="ts" name="PlatformLog">
import { Refresh, Search } from '@element-plus/icons-vue'
import { ref, watch } from 'vue'

import { platformLogApi } from '@/api/system'
import { useListPage } from '@/hooks/useListPage'

const activeTab = ref('login')

// ===== 登录日志 =====
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

// ===== 操作日志 =====
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

// 切换到操作日志 tab 时懒加载
watch(activeTab, (tab) => {
    if (tab === 'operation' && opList.value.length === 0) {
        opGetList()
    }
})

// 请求方法标签样式
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
.log-container {
    .log-tabs {
        :deep(.el-tabs__header) {
            margin-bottom: 0;
        }

        :deep(.el-tabs__content) {
            padding-top: 16px;
        }
    }

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
