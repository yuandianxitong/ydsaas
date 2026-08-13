<template>
    <div class="role-container">
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
                <div class="table-title">{{ $t('system.role.title') }}</div>
                <div class="table-actions">
                    <el-button type="primary" @click="handleAdd">
                        <el-icon><Plus /></el-icon>
                        {{ $t('system.role.addRole') }}
                    </el-button>
                </div>
            </div>

            <!-- 表格 -->
            <el-table v-loading="loading" :data="list" stripe>
                <el-table-column :label="$t('common.id')" prop="id" width="80" />

                <el-table-column :label="$t('system.role.roleName')" prop="name" min-width="160" />

                <el-table-column
                    :label="$t('common.description')"
                    prop="description"
                    min-width="200"
                    show-overflow-tooltip
                />

                <el-table-column
                    :label="$t('system.role.adminCount')"
                    prop="admin_count"
                    width="100"
                    align="center"
                />

                <el-table-column :label="$t('common.status')" width="100" align="center">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.status"
                            :active-value="1"
                            :inactive-value="0"
                            @change="handleStatusChange(row)"
                        />
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="180" />

                <el-table-column :label="$t('common.operation')" width="220" fixed="right">
                    <template #default="{ row }">
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
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ref } from 'vue'

import { platformRoleApi } from '@/api/system'
import { useListPage } from '@/hooks/useListPage'

import AssignPermissionsDialog from './components/AssignPermissionsDialog.vue'
import RoleForm from './components/RoleForm.vue'

interface RoleSearchForm {
    keyword: string
}

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
    handleStatusChange
} = useListPage<any, RoleSearchForm>({
    fetchFn: (params) => platformRoleApi.list(params),
    deleteFn: (id) => platformRoleApi.destroy(id),
    updateStatusFn: (id, status) => platformRoleApi.updateStatus(id, status),
    defaultSearchForm: {
        keyword: ''
    }
})

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

<style lang="scss" scoped>
.role-container {
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
