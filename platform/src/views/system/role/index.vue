<template>
    <div class="role-container">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('system.role.title') }}</div>
                <div class="page-desc">{{ $t('system.role.desc') }}</div>
            </div>
            <div class="page-actions">
                <el-button type="primary" @click="handleAdd">
                    <i class="i-svg:plus" />
                    {{ $t('system.role.addRole') }}
                </el-button>
            </div>
        </div>

        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('common.search')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('system.role.roleName')"
                        clearable
                        style="width: 200px"
                        @keyup.enter="handleSearch"
                    />
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
            :title="$t('system.role.title')"
            storage-key="platform-role-list"
            :columns="columns"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            :batch-delete-fn="handleBatchDelete"
            @page-change="handlePageChange"
            @size-change="handleSizeChange"
        >
            <template #status="{ row }">
                <el-switch
                    v-model="row.status"
                    :active-value="1"
                    :inactive-value="0"
                    @change="handleStatusChange(row)"
                />
            </template>
            <template #action="{ row }">
                <el-button type="primary" size="small" text @click="handleEdit(row)">
                    {{ $t('common.edit') }}
                </el-button>
                <el-button
                    v-if="row.code !== 'platform_admin'"
                    type="success"
                    size="small"
                    text
                    @click="handleAssignPermissions(row)"
                >
                    {{ $t('system.role.assignPermissions') }}
                </el-button>
                <el-tag
                    v-else
                    type="info"
                    size="small"
                    effect="plain"
                    style="margin-right: 8px"
                >
                    全部权限
                </el-tag>
                <el-popconfirm
                    :title="$t('common.deleteConfirm')"
                    :confirm-button-text="$t('common.confirm')"
                    :cancel-button-text="$t('common.cancel')"
                    @confirm="handleDelete(row.id, row.name)"
                >
                    <template #reference>
                        <el-button type="danger" size="small" text>{{
                            $t('common.delete')
                        }}</el-button>
                    </template>
                </el-popconfirm>
            </template>
        </ProTable>

        <!-- 新增/编辑弹窗 -->
        <RoleForm v-model="formVisible" :source-id="currentId" @success="getList" />

        <!-- 权限分配弹窗 -->
        <AssignPermissionsDialog
            v-model="assignVisible"
            :role-info="currentRole"
            @success="getList"
        />
    </div>
</template>

<script setup lang="ts" name="PlatformRoleList">
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { platformRoleApi } from '@/api/system'
import ProTable from '@/components/ProTable/index.vue'
import type { ProColumn } from '@/components/ProTable/types'
import { useListPage } from '@/hooks/useListPage'

import AssignPermissionsDialog from './components/AssignPermissionsDialog.vue'
import RoleForm from './components/RoleForm.vue'

interface RoleSearchForm {
    keyword: string
}

const { t } = useI18n()

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
} = useListPage<any, RoleSearchForm>({
    fetchFn: (params) => platformRoleApi.list(params),
    deleteFn: (id) => platformRoleApi.destroy(id),
    batchDeleteFn: (ids) => Promise.all(ids.map((id) => platformRoleApi.destroy(id))),
    updateStatusFn: (id, status) => platformRoleApi.updateStatus(id, status),
    defaultSearchForm: {
        keyword: ''
    }
})

const columns: ProColumn[] = [
    { key: 'id', label: t('common.id'), prop: 'id', width: 80, required: true },
    { key: 'name', label: t('system.role.roleName'), prop: 'name', minWidth: 160 },
    {
        key: 'description',
        label: t('common.description'),
        prop: 'description',
        minWidth: 200,
        showOverflowTooltip: true
    },
    { key: 'admin_count', label: t('system.role.adminCount'), prop: 'admin_count', width: 110, align: 'center' },
    { key: 'status', label: t('common.status'), width: 100, align: 'center' },
    { key: 'created_at', label: t('common.createdAt'), prop: 'created_at', width: 180 },
    { key: 'action', label: t('common.operation'), width: 220, fixed: 'right', required: true }
]

// 弹窗状态
const formVisible = ref(false)
const currentId = ref<number | undefined>(undefined)
const assignVisible = ref(false)
const currentRole = ref<any | null>(null)

const handleAdd = () => {
    currentId.value = undefined
    formVisible.value = true
}

const handleEdit = (row: any) => {
    currentId.value = row.id
    formVisible.value = true
}

const handleAssignPermissions = (row: any) => {
    currentRole.value = row
    assignVisible.value = true
}
</script>

