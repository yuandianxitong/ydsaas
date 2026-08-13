<template>
    <div class="role-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('role.roleName')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('role.searchPlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('role.statusPlaceholder')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('common.normal')" :value="1" />
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
        <ProTable
            :title="$t('role.title')"
            storage-key="system-role"
            :columns="tableColumns"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            :batch-delete-fn="canBatchDelete ? handleBatchDelete : undefined"
            pagination-layout="prev, pager, next, sizes, jumper"
            @page-change="handlePageChange"
            @size-change="handleSizeChange"
        >
            <template #dataScope="{ row }">
                <el-tag :type="getDataScopeTagType(row.data_scope)" size="small">
                    {{ getDataScopeText(row.data_scope) }}
                </el-tag>
            </template>
            <template #is_system="{ row }">
                <el-tag :type="row.is_system ? 'danger' : 'info'" size="small">
                    {{ row.is_system ? $t('common.yes') : $t('common.no') }}
                </el-tag>
            </template>
            <template #status="{ row }">
                <el-switch
                    v-model="row.status"
                    :active-value="1"
                    :inactive-value="0"
                    :disabled="!hasPerm('system.role.update') || row.is_system"
                    @change="handleStatusChange(row)"
                />
            </template>
            <template #headerExtra>
                <el-button v-has-perm="['system.role.create']" type="primary" @click="handleAdd">
                    <el-icon><Plus /></el-icon>
                    {{ $t('role.addRole') }}
                </el-button>
            </template>
            <template #action="{ row }">
                <el-button
                    v-if="!row.is_system"
                    v-has-perm="['system.role.update']"
                    type="primary"
                    size="small"
                    text
                    @click="handleEdit(row as RoleInfo)"
                >
                    {{ $t('common.edit') }}
                </el-button>
                <el-button
                    v-if="!row.is_system"
                    v-has-perm="['system.role.permission']"
                    type="success"
                    size="small"
                    text
                    @click="handleAssignPermissions(row as RoleInfo)"
                >
                    {{ $t('common.assignPermission') }}
                </el-button>
                <el-tag v-else type="info" size="small">{{ $t('common.systemRole') }}</el-tag>
                <el-button
                    v-if="!row.is_system"
                    v-has-perm="['system.role.delete']"
                    type="danger"
                    size="small"
                    text
                    @click="handleDelete(row.id, row.title)"
                >
                    {{ $t('common.delete') }}
                </el-button>
            </template>
        </ProTable>

        <!-- 新增/编辑弹窗 -->
        <RoleForm v-model="formVisible" :form-data="formData" @success="getList" />

        <!-- 权限分配弹窗 -->
        <AssignPermissionsDialog
            v-model="assignVisible"
            :role-info="currentRole"
            @success="getList"
        />
    </div>
</template>

<script setup lang="ts" name="RoleList">
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { computed, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { roleApi } from '@/api/role'
import ProTable from '@/components/ProTable/index.vue'
import type { ProColumn } from '@/components/ProTable/types'
import { useListPage } from '@/hooks/useListPage'
import { useUserStore } from '@/store'
import type { RoleInfo } from '@/types/api'

import AssignPermissionsDialog from './components/AssignPermissionsDialog.vue'
import RoleForm from './components/RoleForm.vue'

const { t } = useI18n()
const userStore = useUserStore()

// 使用统一的列表页 composable
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
    handleBatchDelete,
    handleStatusChange
} = useListPage<RoleInfo, { keyword: string; status?: number }>({
    fetchFn: (params) => roleApi.getRoleList(params),
    deleteFn: (id) => roleApi.deleteRole(id),
    batchDeleteFn: (ids) => roleApi.batchDeleteRole({ ids }),
    updateStatusFn: (id, status) => roleApi.updateRoleStatus(id, { status }),
    defaultSearchForm: {
        keyword: '',
        status: undefined
    }
})

// 弹窗相关
const formVisible = ref(false)
const formData = ref<Partial<RoleInfo>>({})
const assignVisible = ref(false)
const currentRole = ref<RoleInfo | null>(null)

// 权限判断
const hasPerm = (code: string) => userStore.hasPermission(code)
const canBatchDelete = computed(() => hasPerm('system.role.delete'))

// 表格列定义（与原 el-table-column 一一对应）
const tableColumns = computed<ProColumn[]>(() => [
    { key: 'id', label: 'ID', width: 80 },
    { key: 'name', label: t('role.roleCode'), width: 150 },
    { key: 'title', label: t('role.roleName'), width: 150 },
    { key: 'description', label: t('role.description'), showOverflowTooltip: true },
    { key: 'dataScope', prop: 'data_scope', label: t('role.dataScope'), width: 120 },
    { key: 'is_system', label: t('common.systemRole'), width: 100 },
    { key: 'status', label: t('common.status'), width: 100 },
    { key: 'created_at', label: t('common.createdAt'), width: 160 },
    { key: 'action', label: t('common.operation'), width: 250, fixed: 'right', required: true }
])

// 新增角色
const handleAdd = () => {
    formData.value = {
        status: 1,
        data_scope: 1
    }
    formVisible.value = true
}

// 编辑角色
const handleEdit = (row: RoleInfo) => {
    formData.value = { ...row }
    formVisible.value = true
}

// 分配权限
const handleAssignPermissions = (row: RoleInfo) => {
    currentRole.value = row
    assignVisible.value = true
}

// 获取数据权限文本
const getDataScopeText = (dataScope: number) => {
    const scopeMap: Record<number, string> = {
        1: t('role.dataScopeOptions.all'),
        2: t('role.dataScopeOptions.department'),
        3: t('role.dataScopeOptions.departmentAndBelow'),
        4: t('role.dataScopeOptions.self'),
        5: t('role.dataScopeOptions.custom')
    }
    return scopeMap[dataScope] || t('common.noData')
}

// 获取数据权限标签类型
const getDataScopeTagType = (
    dataScope: number
): 'primary' | 'success' | 'warning' | 'info' | 'danger' => {
    const typeMap: Record<number, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
        1: 'danger',
        2: 'warning',
        3: 'info',
        4: 'success',
        5: 'primary'
    }
    return typeMap[dataScope] || 'info'
}
</script>
