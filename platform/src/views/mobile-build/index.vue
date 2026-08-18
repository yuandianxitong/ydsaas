<template>
    <div class="mobile-build-container">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('mobileBuild.title') }}</div>
                <div class="page-desc">{{ $t('mobileBuild.desc') }}</div>
            </div>
            <div class="page-actions">
                <el-button
                    v-perms="'platform.mobile.build.manage'"
                    type="warning"
                    @click="handleForceFail"
                >
                    {{ $t('mobileBuild.forceFail') }}
                </el-button>
            </div>
        </div>

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
            :title="$t('mobileBuild.title')"
            storage-key="platform-mobile-build-list"
            :columns="columns"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            @page-change="handlePageChange"
            @size-change="handleSizeChange"
        >
            <template #platform="{ row }">
                <el-tag size="small" effect="light">{{ row.platform }}</el-tag>
            </template>
            <template #status="{ row }">
                <el-tag :type="statusTagType(row.status)" size="small">
                    {{ statusOptions[row.status] || row.status }}
                </el-tag>
            </template>
            <template #error_log="{ row }">
                {{ row.error_log || '—' }}
            </template>
            <template #started_at="{ row }">{{ row.started_at || '—' }}</template>
            <template #finished_at="{ row }">{{ row.finished_at || '—' }}</template>
        </ProTable>
    </div>
</template>

<script setup lang="ts" name="PlatformMobileBuildList">
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed } from 'vue'
import { useI18n } from 'vue-i18n'

import { mobileBuildApi, type MobileBuildInfo } from '@/api/mobile-build'
import ProTable from '@/components/ProTable/index.vue'
import type { ProColumn } from '@/components/ProTable/types'
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

const columns: ProColumn[] = [
    { key: 'id', label: 'ID', prop: 'id', width: 80, required: true },
    { key: 'tenant_id', label: t('order.tenantId'), prop: 'tenant_id', width: 90 },
    {
        key: 'build_no',
        label: t('mobileBuild.buildNo'),
        prop: 'build_no',
        minWidth: 170,
        showOverflowTooltip: true
    },
    { key: 'platform', label: t('mobileBuild.platform'), width: 110 },
    { key: 'driver', label: 'Driver', prop: 'driver', width: 90 },
    { key: 'status', label: t('common.status'), width: 110, align: 'center' },
    {
        key: 'error_log',
        label: t('mobileBuild.errorLog'),
        prop: 'error_log',
        minWidth: 180,
        showOverflowTooltip: true
    },
    { key: 'started_at', label: t('mobileBuild.startedAt'), prop: 'started_at', width: 170 },
    { key: 'finished_at', label: t('mobileBuild.finishedAt'), prop: 'finished_at', width: 170 }
]

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

