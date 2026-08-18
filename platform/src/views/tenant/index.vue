<template>
    <div class="tenant-container">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('tenant.title') }}</div>
                <div class="page-desc">{{ $t('tenant.desc') }}</div>
            </div>
            <div class="page-actions">
                <el-button v-perms="'platform.tenant.create'" type="primary" @click="handleAdd">
                    <i class="i-svg:plus" />
                    {{ $t('tenant.addTenant') }}
                </el-button>
            </div>
        </div>

        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('common.search')">
                    <el-input
                        v-model="searchForm.keyword"
                        :placeholder="$t('tenant.searchPlaceholder')"
                        clearable
                        style="width: 220px"
                        @keyup.enter="handleSearch"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 140px"
                    >
                        <el-option :label="$t('common.enable')" :value="1" />
                        <el-option :label="$t('common.disable')" :value="0" />
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
            :title="$t('tenant.title')"
            storage-key="platform-tenant-list"
            :columns="columns"
            :data="list"
            :loading="loading"
            :pagination="pagination"
            :batch-delete-fn="handleBatchDelete"
            @page-change="handlePageChange"
            @size-change="handleSizeChange"
        >
            <template #access_domain="{ row }">
                <a
                    v-if="row.access_domain"
                    :href="`//${row.access_domain}`"
                    target="_blank"
                    rel="noopener"
                    class="domain-link"
                >
                    {{ row.access_domain }}
                </a>
                <span v-else>—</span>
            </template>
            <template #plan_name="{ row }">
                <el-tag v-if="row.plan_name" size="small" type="info">{{ row.plan_name }}</el-tag>
                <span v-else class="muted">未绑定</span>
            </template>
            <template #lifecycle_state="{ row }">
                <el-tag :type="lifecycleTagType(row.lifecycle_state)">
                    {{ lifecycleLabel(row.lifecycle_state) }}
                </el-tag>
            </template>
            <template #expires_at="{ row }">{{ row.expires_at || '—' }}</template>
            <template #action="{ row }">
                <el-button
                    v-perms="'platform.tenant.update'"
                    type="primary"
                    size="small"
                    text
                    @click="handleEdit(row)"
                >
                    {{ $t('common.edit') }}
                </el-button>
                <el-button
                    v-perms="'platform.tenant.update'"
                    type="success"
                    size="small"
                    text
                    @click="openOfflineRenew(row)"
                >
                    {{ $t('tenant.offlineRenew') }}
                </el-button>
                <el-button type="warning" size="small" text @click="handleAdmins(row)">
                    {{ $t('tenant.tenantAdmins') }}
                </el-button>
                <el-popconfirm
                    v-perms="'platform.tenant.delete'"
                    :title="$t('common.deleteConfirm')"
                    :confirm-button-text="$t('common.confirm')"
                    :cancel-button-text="$t('common.cancel')"
                    @confirm="handleDelete(row.id, row.name)"
                >
                    <template #reference>
                        <el-button type="danger" size="small" text>
                            {{ $t('common.delete') }}
                        </el-button>
                    </template>
                </el-popconfirm>
            </template>
        </ProTable>

        <!-- 新增/编辑弹窗 -->
        <TenantForm v-model="formVisible" :source-id="currentId" @success="getList" />

        <TenantAdmins
            v-model="adminsVisible"
            :tenant-id="currentTenantId"
            :tenant-name="currentTenantName"
        />

        <el-dialog
            v-model="renewVisible"
            :title="$t('tenant.offlineRenewTitle')"
            width="480px"
            destroy-on-close
            @open="loadRenewPlans"
        >
            <el-form label-width="100px">
                <el-form-item :label="$t('tenant.tenantName')">
                    <span>{{ renewTenant?.name }}</span>
                </el-form-item>
                <el-form-item :label="$t('tenant.plan')" required>
                    <el-select v-model="renewForm.plan_id" style="width: 100%">
                        <el-option
                            v-for="plan in renewPlanOptions"
                            :key="plan.id"
                            :label="`${plan.name} (${plan.code})`"
                            :value="plan.id"
                        />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('tenant.months')" required>
                    <el-input-number
                        v-model="renewForm.months"
                        :min="1"
                        :max="120"
                        controls-position="right"
                        style="width: 100%"
                    />
                </el-form-item>
                <el-form-item :label="$t('tenant.offlineRemark')">
                    <el-input
                        v-model="renewForm.remark"
                        :placeholder="$t('tenant.offlineRemarkPlaceholder')"
                        maxlength="255"
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="renewVisible = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="renewSubmitting" @click="submitOfflineRenew">
                    {{ $t('common.confirm') }}
                </el-button>
            </template>
        </el-dialog>
    </div>
</template>

<script setup lang="ts" name="TenantList">
import { ElMessage } from 'element-plus'
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { planApi } from '@/api/plan'
import { tenantApi } from '@/api/tenant'
import ProTable from '@/components/ProTable/index.vue'
import type { ProColumn } from '@/components/ProTable/types'
import { useListPage } from '@/hooks/useListPage'
import type { PlanInfo, TenantInfo, TenantQuery } from '@/types/api'

import TenantAdmins from './components/TenantAdmins.vue'
import TenantForm from './components/TenantForm.vue'

const { t } = useI18n()

interface TenantSearchForm {
    keyword: string
    status: number | ''
}

// 列表 composable
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
} = useListPage<TenantInfo, TenantSearchForm>({
    fetchFn: (params) => tenantApi.list(params as TenantQuery),
    deleteFn: (id) => tenantApi.destroy(id),
    batchDeleteFn: (ids) => Promise.all(ids.map((id) => tenantApi.destroy(id))),
    defaultSearchForm: {
        keyword: '',
        status: ''
    }
})

const columns: ProColumn[] = [
    { key: 'id', label: 'ID', prop: 'id', width: 80, required: true },
    {
        key: 'tenant_code',
        label: t('tenant.tenantCode'),
        prop: 'tenant_code',
        minWidth: 120,
        showOverflowTooltip: true
    },
    { key: 'name', label: t('tenant.tenantName'), prop: 'name', minWidth: 150, showOverflowTooltip: true },
    {
        key: 'access_domain',
        label: '租户域名',
        prop: 'access_domain',
        minWidth: 200,
        showOverflowTooltip: true
    },
    {
        key: 'contact_name',
        label: t('tenant.contactName'),
        prop: 'contact_name',
        minWidth: 120,
        showOverflowTooltip: true
    },
    {
        key: 'contact_phone',
        label: t('tenant.contactPhone'),
        prop: 'contact_phone',
        minWidth: 140,
        showOverflowTooltip: true
    },
    { key: 'plan_name', label: t('tenant.plan'), prop: 'plan_name', minWidth: 110, align: 'center' },
    { key: 'lifecycle_state', label: t('common.status'), width: 110, align: 'center' },
    { key: 'expires_at', label: t('tenant.expiredAt'), prop: 'expires_at', minWidth: 180 },
    { key: 'created_at', label: t('common.createdAt'), prop: 'created_at', minWidth: 180 },
    { key: 'action', label: t('common.operation'), width: 320, fixed: 'right', required: true }
]

const formVisible = ref(false)
const currentId = ref<number | undefined>(undefined)

const handleAdd = () => {
    currentId.value = undefined
    formVisible.value = true
}

const handleEdit = (row: TenantInfo) => {
    currentId.value = row.id
    formVisible.value = true
}

// 管理员弹窗状态
const adminsVisible = ref(false)
const currentTenantId = ref<number | undefined>(undefined)
const currentTenantName = ref('')

const handleAdmins = (row: TenantInfo) => {
    currentTenantId.value = row.id
    currentTenantName.value = row.name
    adminsVisible.value = true
}

const renewVisible = ref(false)
const renewSubmitting = ref(false)
const renewTenant = ref<TenantInfo | null>(null)
const renewPlanOptions = ref<PlanInfo[]>([])
const renewForm = reactive({
    plan_id: undefined as number | undefined,
    months: 12,
    remark: ''
})

const openOfflineRenew = (row: TenantInfo) => {
    renewTenant.value = row
    renewForm.plan_id = row.plan_id || undefined
    renewForm.months = 12
    renewForm.remark = ''
    renewVisible.value = true
}

const loadRenewPlans = async () => {
    try {
        const res = await planApi.options()
        renewPlanOptions.value = res.data || []
    } catch {
        renewPlanOptions.value = []
    }
}

const submitOfflineRenew = async () => {
    if (!renewTenant.value) return
    if (!renewForm.plan_id) {
        ElMessage.warning(t('tenant.validate.planRequired'))
        return
    }
    if (!renewForm.months || renewForm.months < 1) {
        ElMessage.warning(t('tenant.validate.monthsRequired'))
        return
    }
    renewSubmitting.value = true
    try {
        await tenantApi.offlineRenew(renewTenant.value.id, {
            plan_id: renewForm.plan_id,
            months: renewForm.months,
            remark: renewForm.remark || undefined
        })
        ElMessage.success(t('tenant.offlineRenewSuccess'))
        renewVisible.value = false
        getList()
    } finally {
        renewSubmitting.value = false
    }
}

// lifecycle_state 映射
type LifecycleState = TenantInfo['lifecycle_state']
const lifecycleLabel = (state?: LifecycleState): string => {
    switch (state) {
        case 'active':
            return t('tenant.active')
        case 'trial':
            return t('tenant.trial')
        case 'grace':
            return t('tenant.grace')
        case 'frozen':
            return t('tenant.frozen')
        case 'disabled':
            return t('tenant.disabled')
        default:
            return t('tenant.unknown')
    }
}

const lifecycleTagType = (state?: LifecycleState): 'success' | 'warning' | 'danger' | 'info' => {
    switch (state) {
        case 'active':
        case 'trial':
            return 'success'
        case 'grace':
            return 'warning'
        case 'frozen':
            return 'danger'
        case 'disabled':
            return 'info'
        default:
            return 'info'
    }
}
</script>

<style lang="scss" scoped>
.domain-link {
    color: var(--el-color-primary);
    text-decoration: none;
}
.domain-link:hover {
    text-decoration: underline;
}
.muted {
    color: var(--el-text-color-secondary);
    font-size: 12px;
}

</style>
