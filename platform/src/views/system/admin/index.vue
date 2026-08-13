<template>
    <div class="admin-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('common.search')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('system.admin.username')"
                        clearable
                        style="width: 200px"
                        @keyup.enter="handleSearch"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 120px"
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
                <div class="table-title">{{ $t('system.admin.title') }}</div>
                <div class="table-actions">
                    <el-button type="primary" @click="handleAdd">
                        <el-icon><Plus /></el-icon>
                        {{ $t('system.admin.addAdmin') }}
                    </el-button>
                </div>
            </div>

            <!-- 表格 -->
            <el-table v-loading="loading" :data="list" stripe>
                <el-table-column :label="$t('common.id')" prop="id" width="80" />

                <el-table-column :label="$t('system.admin.username')" prop="username" width="140" />

                <el-table-column
                    :label="$t('system.admin.nickname')"
                    prop="real_name"
                    width="120"
                    show-overflow-tooltip
                />

                <el-table-column :label="$t('system.admin.phone')" prop="mobile" width="140" />

                <el-table-column
                    :label="$t('system.admin.email')"
                    prop="email"
                    min-width="180"
                    show-overflow-tooltip
                />

                <el-table-column :label="$t('system.admin.role')" min-width="160">
                    <template #default="{ row }">
                        <el-tag v-if="row.is_super === 1" type="danger" size="small">
                            {{ $t('system.admin.superAdmin') }}
                        </el-tag>
                        <template v-else>
                            <el-tag
                                v-for="role in row.roles"
                                :key="role.id"
                                size="small"
                                effect="light"
                                class="role-tag"
                            >
                                {{ role.name }}
                            </el-tag>
                            <span v-if="!row.roles?.length" class="text-placeholder">—</span>
                        </template>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.status')" width="100" align="center">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.status"
                            :active-value="1"
                            :inactive-value="0"
                            :disabled="row.is_super === 1"
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
                            type="warning"
                            size="small"
                            text
                            @click="handleResetPassword(row)"
                        >
                            {{ $t('system.admin.resetPassword') }}
                        </el-button>
                        <el-popconfirm
                            v-if="row.is_super !== 1"
                            :title="$t('common.deleteConfirm')"
                            :confirm-button-text="$t('common.confirm')"
                            :cancel-button-text="$t('common.cancel')"
                            @confirm="handleDelete(row.id, row.username)"
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
        <AdminForm
            v-model="formVisible"
            :source-id="currentId"
            :role-options="roleOptions"
            @success="getList"
        />

        <!-- 重置密码弹窗 -->
        <el-dialog
            v-model="resetPwdVisible"
            class="dialog-sm"
            :title="$t('system.admin.resetPassword')"
            :close-on-click-modal="false"
        >
            <el-form
                ref="resetPwdFormRef"
                :model="resetPwdForm"
                :rules="resetPwdRules"
                label-width="80px"
            >
                <el-form-item :label="$t('system.admin.newPassword')" prop="password">
                    <el-input
                        v-model="resetPwdForm.password"
                        type="password"
                        :placeholder="$t('system.admin.newPassword')"
                        show-password
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="resetPwdVisible = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="resetPwdLoading" @click="submitResetPassword">
                    {{ $t('common.confirm') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts" name="PlatformAdminList">
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import type { FormInstance, FormRules } from 'element-plus'
import { ElMessage } from 'element-plus'
import { onMounted, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { platformAdminApi } from '@/api/system'
import { useListPage } from '@/hooks/useListPage'

import AdminForm from './components/AdminForm.vue'

const { t } = useI18n()

interface AdminSearchForm {
    keyword: string
    status: number | ''
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
} = useListPage<any, AdminSearchForm>({
    fetchFn: (params) => platformAdminApi.list(params),
    deleteFn: (id) => platformAdminApi.destroy(id),
    updateStatusFn: (id, status) => platformAdminApi.updateStatus(id, status),
    defaultSearchForm: {
        keyword: '',
        status: ''
    }
})

// 弹窗状态
const formVisible = ref(false)
const currentId = ref<number | undefined>(undefined)

// 角色选项
const roleOptions = ref<Array<{ id: number; name: string }>>([])

const loadRoleOptions = async () => {
    try {
        const res = await platformAdminApi.roleOptions()
        roleOptions.value = res.data || []
    } catch (error) {
        console.error('加载角色选项失败:', error)
    }
}

const handleAdd = () => {
    currentId.value = undefined
    formVisible.value = true
}

const handleEdit = (row: any) => {
    currentId.value = row.id
    formVisible.value = true
}

// 重置密码弹窗
const resetPwdVisible = ref(false)
const resetPwdLoading = ref(false)
const resetPwdFormRef = ref<FormInstance>()
const currentAdminId = ref<number | undefined>(undefined)
const resetPwdForm = ref({ password: '' })

const resetPwdRules: FormRules = {
    password: [
        { required: true, message: t('message.required'), trigger: 'blur' },
        { min: 6, max: 30, message: t('message.required'), trigger: 'blur' }
    ]
}

const handleResetPassword = (row: any) => {
    currentAdminId.value = row.id
    resetPwdForm.value.password = ''
    resetPwdVisible.value = true
}

const submitResetPassword = async () => {
    if (!resetPwdFormRef.value) return
    try {
        await resetPwdFormRef.value.validate()
    } catch {
        return
    }
    if (!currentAdminId.value) return
    resetPwdLoading.value = true
    try {
        await platformAdminApi.resetPassword(currentAdminId.value, resetPwdForm.value.password)
        ElMessage.success(t('message.resetSuccess'))
        resetPwdVisible.value = false
    } finally {
        resetPwdLoading.value = false
    }
}

onMounted(() => {
    loadRoleOptions()
})
</script>

<style lang="scss" scoped>
.admin-container {
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

    .role-tag {
        margin-right: 4px;
        margin-bottom: 4px;
    }

    .text-placeholder {
        color: var(--el-text-color-placeholder);
    }
}
</style>
