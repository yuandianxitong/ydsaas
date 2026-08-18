<template>
    <div class="announcement-container">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('announcement.title') }}</div>
                <div class="page-desc">{{ $t('announcement.desc') }}</div>
            </div>
            <div class="page-actions">
                <el-button type="primary" @click="handleAdd">
                    <i class="i-svg:plus" />
                    {{ $t('announcement.addAnnouncement') }}
                </el-button>
            </div>
        </div>

        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('common.search')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('announcement.searchPlaceholder')"
                        clearable
                        style="width: 200px"
                        @keyup.enter="handleSearch"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.type')">
                    <el-select
                        v-model="searchForm.type"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('announcement.notice')" value="notice" />
                        <el-option :label="$t('announcement.update')" value="update" />
                        <el-option :label="$t('announcement.warning')" value="warning" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('announcement.draft')" value="draft" />
                        <el-option :label="$t('announcement.published')" value="published" />
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
            :title="$t('announcement.title')"
            storage-key="platform-announcement-list"
            :columns="columns"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            :batch-delete-fn="handleBatchDelete"
            @page-change="handlePageChange"
            @size-change="handleSizeChange"
        >
            <template #type="{ row }">
                <el-tag :type="typeTagMap[row.type] || 'info'" size="small">
                    {{ row.type_text }}
                </el-tag>
            </template>
            <template #status="{ row }">
                <el-tag :type="row.status === 'published' ? 'success' : 'info'" size="small">
                    {{ row.status_text }}
                </el-tag>
            </template>
            <template #published_at="{ row }">{{ row.published_at || '—' }}</template>
            <template #action="{ row }">
                <el-button type="primary" size="small" text @click="handleEdit(row)">
                    {{ $t('common.edit') }}
                </el-button>
                <el-button
                    v-if="row.status !== 'published'"
                    type="success"
                    size="small"
                    text
                    @click="handlePublish(row)"
                >
                    {{ $t('announcement.publish') }}
                </el-button>
                <el-popconfirm
                    :title="$t('common.deleteConfirm')"
                    :confirm-button-text="$t('common.confirm')"
                    :cancel-button-text="$t('common.cancel')"
                    @confirm="handleDelete(row.id, row.title)"
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
        <AnnouncementForm v-model="formVisible" :source-id="currentId" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="PlatformAnnouncementList">
import { ElMessage } from 'element-plus'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { platformAnnouncementApi } from '@/api/announcement'
import ProTable from '@/components/ProTable/index.vue'
import type { ProColumn } from '@/components/ProTable/types'
import { useListPage } from '@/hooks/useListPage'

import AnnouncementForm from './components/AnnouncementForm.vue'

const { t } = useI18n()

interface AnnouncementSearchForm {
    keyword: string
    type: string
    status: string
}

type ElTagType = 'primary' | 'success' | 'info' | 'warning' | 'danger'
const typeTagMap: Record<string, ElTagType> = {
    notice: 'primary',
    update: 'success',
    warning: 'warning'
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
    handleBatchDelete
} = useListPage<any, AnnouncementSearchForm>({
    fetchFn: (params) => platformAnnouncementApi.list(params),
    deleteFn: (id) => platformAnnouncementApi.destroy(id),
    batchDeleteFn: (ids) => Promise.all(ids.map((id) => platformAnnouncementApi.destroy(id))),
    defaultSearchForm: {
        keyword: '',
        type: '',
        status: ''
    }
})

const columns: ProColumn[] = [
    { key: 'id', label: 'ID', prop: 'id', width: 80, required: true },
    {
        key: 'title',
        label: t('announcement.announcementTitle'),
        prop: 'title',
        minWidth: 200,
        showOverflowTooltip: true
    },
    { key: 'type', label: t('common.type'), width: 100, align: 'center' },
    { key: 'status', label: t('common.status'), width: 100, align: 'center' },
    { key: 'published_at', label: t('announcement.publishTime'), prop: 'published_at', width: 160 },
    { key: 'created_at', label: t('common.createdAt'), prop: 'created_at', width: 160 },
    { key: 'action', label: t('common.operation'), width: 220, fixed: 'right', required: true }
]

const formVisible = ref(false)
const currentId = ref<number | undefined>(undefined)

const handleAdd = () => {
    currentId.value = undefined
    formVisible.value = true
}

const handleEdit = (row: any) => {
    currentId.value = row.id
    formVisible.value = true
}

const handlePublish = async (row: any) => {
    try {
        await platformAnnouncementApi.publish(row.id)
        ElMessage.success(t('announcement.publishSuccess'))
        getList()
    } catch (error) {
        console.error('Publish failed:', error)
    }
}
</script>

