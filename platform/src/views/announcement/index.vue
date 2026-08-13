<template>
    <div class="announcement-container">
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
                <div class="table-title">{{ $t('announcement.title') }}</div>
                <div class="table-actions">
                    <el-button type="primary" @click="handleAdd">
                        <el-icon><Plus /></el-icon>
                        {{ $t('announcement.addAnnouncement') }}
                    </el-button>
                </div>
            </div>

            <!-- 表格 -->
            <el-table v-loading="loading" :data="list" stripe>
                <el-table-column label="ID" prop="id" width="80" />

                <el-table-column
                    :label="$t('announcement.announcementTitle')"
                    prop="title"
                    min-width="200"
                    show-overflow-tooltip
                />

                <el-table-column :label="$t('common.type')" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag :type="typeTagMap[row.type] || 'info'" size="small">
                            {{ row.type_text }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.status')" width="100" align="center">
                    <template #default="{ row }">
                        <el-tag
                            :type="row.status === 'published' ? 'success' : 'info'"
                            size="small"
                        >
                            {{ row.status_text }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column
                    :label="$t('announcement.publishTime')"
                    prop="published_at"
                    width="160"
                >
                    <template #default="{ row }">
                        {{ row.published_at || '—' }}
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="160" />

                <el-table-column :label="$t('common.operation')" width="220" fixed="right">
                    <template #default="{ row }">
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
        <AnnouncementForm v-model="formVisible" :source-id="currentId" @success="getList" />
    </div>
</template>

<script setup lang="ts" name="PlatformAnnouncementList">
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { platformAnnouncementApi } from '@/api/announcement'
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
    handleDelete
} = useListPage<any, AnnouncementSearchForm>({
    fetchFn: (params) => platformAnnouncementApi.list(params),
    deleteFn: (id) => platformAnnouncementApi.destroy(id),
    defaultSearchForm: {
        keyword: '',
        type: '',
        status: ''
    }
})

// 弹窗状态
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

<style lang="scss" scoped>
.announcement-container {
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
