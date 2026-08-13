<template>
    <div class="mobile-build-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('order.tenantId')">
                    <el-input
                        v-model="searchForm.tenant_id"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 120px"
                        @keyup.enter="handleSearch"
                    />
                </el-form-item>
                <el-form-item :label="$t('mobileBuild.platform')">
                    <el-select
                        v-model="searchForm.platform"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 140px"
                    >
                        <el-option label="H5" value="h5" />
                        <el-option :label="$t('mobileBuild.mpWeixin')" value="mp-weixin" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 130px"
                    >
                        <el-option
                            v-for="(label, value) in statusOptions"
                            :key="value"
                            :label="label"
                            :value="Number(value)"
                        />
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

        <!-- 列表区域 -->
        <el-card class="table-card" shadow="never">
            <div class="table-header">
                <div class="table-title">{{ $t('mobileBuild.title') }}</div>
                <el-button
                    v-perms="'platform.mobile.build.manage'"
                    type="warning"
                    @click="handleForceFail"
                >
                    {{ $t('mobileBuild.forceFail') }}
                </el-button>
            </div>

            <el-table v-loading="loading" :data="list" stripe>
                <el-table-column label="ID" prop="id" width="80" />
                <el-table-column :label="$t('order.tenantId')" prop="tenant_id" width="90" />
                <el-table-column
                    :label="$t('mobileBuild.buildNo')"
                    prop="build_no"
                    min-width="170"
                    show-overflow-tooltip
                />
                <el-table-column :label="$t('mobileBuild.platform')" width="110">
                    <template #default="{ row }">
                        <el-tag size="small" effect="light">{{ row.platform }}</el-tag>
                    </template>
                </el-table-column>
                <el-table-column label="Driver" prop="driver" width="90" />
                <el-table-column :label="$t('common.status')" width="110" align="center">
                    <template #default="{ row }">
                        <el-tag :type="statusTagType(row.status)" size="small">
                            {{ statusOptions[row.status] || row.status }}
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column
                    :label="$t('mobileBuild.errorLog')"
                    prop="error_log"
                    min-width="180"
                    show-overflow-tooltip
                >
                    <template #default="{ row }">
                        {{ row.error_log || '—' }}
                    </template>
                </el-table-column>
                <el-table-column :label="$t('mobileBuild.startedAt')" prop="started_at" width="170">
                    <template #default="{ row }">{{ row.started_at || '—' }}</template>
                </el-table-column>
                <el-table-column
                    :label="$t('mobileBuild.finishedAt')"
                    prop="finished_at"
                    width="170"
                >
                    <template #default="{ row }">{{ row.finished_at || '—' }}</template>
                </el-table-column>
            </el-table>

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
    </div>
</template>

<script setup lang="ts" name="PlatformMobileBuildList">
import { Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { mobileBuildApi, type MobileBuildInfo } from '@/api/mobile-build'
import { useListPage } from '@/hooks/useListPage'

const { t } = useI18n()

interface MobileBuildSearchForm {
    tenant_id: string
    platform: string
    status: number | ''
}

const {
    list,
    loading,
    pagination,
    searchForm,
    handleSearch,
    resetSearch,
    handleSizeChange,
    handlePageChange
} = useListPage<MobileBuildInfo, MobileBuildSearchForm>({
    fetchFn: (params) => mobileBuildApi.list(params),
    defaultSearchForm: {
        tenant_id: '',
        platform: '',
        status: ''
    }
})

const statusOptions = computed<Record<number, string>>(() => ({
    0: t('mobileBuild.queued'),
    1: t('mobileBuild.running'),
    2: t('mobileBuild.success'),
    3: t('mobileBuild.failed'),
    4: t('mobileBuild.uploaded'),
    5: t('mobileBuild.released')
}))

type ElTagType = 'primary' | 'success' | 'info' | 'warning' | 'danger'
const statusTagType = (status: number): ElTagType => {
    const map: Record<number, ElTagType> = {
        0: 'info',
        1: 'warning',
        2: 'success',
        3: 'danger',
        4: 'success',
        5: 'success'
    }
    return map[status] || 'info'
}

async function handleForceFail() {
    try {
        const { value } = await ElMessageBox.prompt(
            t('mobileBuild.forceFailPrompt'),
            t('mobileBuild.forceFail'),
            {
                inputValue: '1800',
                inputPattern: /^\d+$/,
                inputErrorMessage: t('mobileBuild.thresholdInvalid')
            }
        )
        const threshold = Number(value)
        const res = await mobileBuildApi.forceFailStuck(threshold)
        ElMessage.success(t('mobileBuild.forceFailDone', { count: res.data?.closed ?? 0 }))
        handleSearch()
    } catch (e: any) {
        if (e !== 'cancel' && e !== 'close' && !e?.__handled) {
            ElMessage.error(e.message || t('common.failed'))
        }
    }
}
</script>

<style lang="scss" scoped>
.mobile-build-container {
    padding: 16px;

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
