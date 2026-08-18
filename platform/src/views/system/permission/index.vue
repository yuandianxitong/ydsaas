<template>
    <div class="permission-container">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('permission.title') }}</div>
                <div class="page-desc">{{ $t('permission.desc') }}</div>
            </div>
            <div class="page-actions">
                <el-button
                    v-has-perm="['platform.permission.create']"
                    type="primary"
                    @click="handleAdd"
                >
                    <i class="i-svg:plus" />
                    {{ $t('permission.addPermission') }}
                </el-button>
            </div>
        </div>

        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('log.keyword')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('permission.searchPlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('permission.group')">
                    <el-input
                        v-model="searchForm.group"
                        :placeholder="$t('permission.groupPlaceholder')"
                        clearable
                        style="width: 150px"
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
            :title="$t('permission.title')"
            storage-key="platform-permission-list"
            :columns="columns"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            :batch-delete-fn="handleBatchDelete"
            @page-change="handlePageChange"
            @size-change="handleSizeChange"
        >
            <template #status="{ row }">
                <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                    {{ row.status === 1 ? $t('common.enable') : $t('common.disable') }}
                </el-tag>
            </template>
            <template #action="{ row }">
                <el-button
                    v-has-perm="['platform.permission.update']"
                    type="primary"
                    size="small"
                    text
                    @click="handleEdit(row)"
                >
                    {{ $t('common.edit') }}
                </el-button>
                <el-button
                    v-has-perm="['platform.permission.delete']"
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
        <el-dialog
            v-model="formVisible"
            class="dialog-md"
            :title="formData.id ? $t('permission.editPermission') : $t('permission.addPermission')"
            destroy-on-close
        >
            <el-form ref="formRef" :model="formData" :rules="rules" label-width="90px">
                <el-form-item :label="$t('permission.permCode')" prop="name">
                    <el-input
                        v-model="formData.name"
                        :placeholder="$t('permission.codePlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('permission.permName')" prop="title">
                    <el-input
                        v-model="formData.title"
                        :placeholder="$t('permission.namePlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('permission.group')" prop="group">
                    <el-input
                        v-model="formData.group"
                        :placeholder="$t('permission.groupInputPlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('permission.description')" prop="description">
                    <el-input
                        v-model="formData.description"
                        type="textarea"
                        :rows="2"
                        :placeholder="$t('permission.descPlaceholder')"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.sort')" prop="sort">
                    <el-input-number v-model="formData.sort" :min="0" :max="9999" />
                </el-form-item>
                <el-form-item :label="$t('common.status')" prop="status">
                    <el-switch v-model="formData.status" :active-value="1" :inactive-value="0" />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="formVisible = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="submitLoading" @click="handleSubmit">{{
                    $t('common.confirm')
                }}</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts">
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { permissionApi } from '@/api/permission'
import ProTable from '@/components/ProTable/index.vue'
import type { ProColumn } from '@/components/ProTable/types'
import { useListPage } from '@/hooks/useListPage'

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
    handleBatchDelete
} = useListPage<any, { keyword: string; group: string }>({
    fetchFn: (params) => permissionApi.list(params),
    deleteFn: (id) => permissionApi.delete(id),
    batchDeleteFn: (ids) => Promise.all(ids.map((id) => permissionApi.delete(id))),
    defaultSearchForm: { keyword: '', group: '' }
})

const columns: ProColumn[] = [
    { key: 'id', label: t('common.id'), prop: 'id', width: 80, required: true },
    {
        key: 'name',
        label: t('permission.permCode'),
        prop: 'name',
        minWidth: 180,
        showOverflowTooltip: true
    },
    { key: 'title', label: t('permission.permName'), prop: 'title', width: 150 },
    { key: 'group', label: t('permission.group'), prop: 'group', width: 120 },
    {
        key: 'description',
        label: t('permission.description'),
        prop: 'description',
        minWidth: 180,
        showOverflowTooltip: true
    },
    { key: 'status', label: t('common.status'), width: 100 },
    { key: 'sort', label: t('common.sort'), prop: 'sort', width: 90 },
    { key: 'created_at', label: t('common.createdAt'), prop: 'created_at', width: 180 },
    { key: 'action', label: t('common.operation'), width: 160, fixed: 'right', required: true }
]

const formVisible = ref(false)
const formData = ref<any>({ status: 1, sort: 0 })
const formRef = ref<FormInstance>()
const submitLoading = ref(false)

const rules: FormRules = {
    name: [
        { required: true, message: () => t('permission.validate.codeRequired'), trigger: 'blur' }
    ],
    title: [
        { required: true, message: () => t('permission.validate.nameRequired'), trigger: 'blur' }
    ],
    group: [
        { required: true, message: () => t('permission.validate.groupRequired'), trigger: 'blur' }
    ]
}

const handleAdd = () => {
    formData.value = { status: 1, sort: 0 }
    formVisible.value = true
}

const handleEdit = (row: any) => {
    formData.value = { ...row }
    formVisible.value = true
}

const handleSubmit = async () => {
    if (!formRef.value) return
    await formRef.value.validate()
    try {
        submitLoading.value = true
        if (formData.value.id) {
            await permissionApi.update(formData.value.id, formData.value)
            ElMessage.success(t('message.updateSuccess'))
        } else {
            await permissionApi.create(formData.value)
            ElMessage.success(t('message.createSuccess'))
        }
        formVisible.value = false
        getList()
    } catch (error) {
        console.error('提交失败:', error)
    } finally {
        submitLoading.value = false
    }
}
</script>

