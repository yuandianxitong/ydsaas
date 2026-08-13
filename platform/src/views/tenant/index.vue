<template>
    <div class="tenant-container">
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
                <div class="table-title">{{ $t('tenant.title') }}</div>
                <div class="table-actions">
                    <el-button v-perms="'platform.tenant.create'" type="primary" @click="handleAdd">
                        <el-icon><Plus /></el-icon>
                        {{ $t('tenant.addTenant') }}
                    </el-button>
                </div>
            </div>

            <!-- 表格 -->
            <el-table v-loading="loading" :data="list" stripe>
                <el-table-column label="ID" prop="id" width="80" />

                <el-table-column
                    :label="$t('tenant.tenantCode')"
                    prop="tenant_code"
                    min-width="120"
                    show-overflow-tooltip
                />

                <el-table-column
                    :label="$t('tenant.tenantName')"
                    prop="name"
                    min-width="150"
                    show-overflow-tooltip
                />

                <el-table-column
                    label="租户域名"
                    prop="access_domain"
                    min-width="200"
                    show-overflow-tooltip
                >
                    <template #default="{ row }">
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
                </el-table-column>

                <el-table-column
                    :label="$t('tenant.contactName')"
                    prop="contact_name"
                    min-width="120"
                    show-overflow-tooltip
                />

                <el-table-column
                    :label="$t('tenant.contactPhone')"
                    prop="contact_phone"
                    min-width="140"
                    show-overflow-tooltip
                />

                <el-table-column
                    :label="$t('tenant.plan')"
                    prop="plan_name"
                    min-width="100"
                    align="center"
                >
                    <template #default="{ row }">
                        <el-tag v-if="row.plan_name" size="small" type="info">{{
                            row.plan_name
                        }}</el-tag>
                        <span v-else class="muted">未绑定</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.status')" width="110" align="center">
                    <template #default="{ row }">
                        <el-tag :type="lifecycleTagType(row.lifecycle_state)">
                            {{ lifecycleLabel(row.lifecycle_state) }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('tenant.expiredAt')" prop="expires_at" min-width="160">
                    <template #default="{ row }">
                        {{ row.expires_at || '—' }}
                    </template>
                </el-table-column>

                <el-table-column
                    :label="$t('common.createdAt')"
                    prop="created_at"
                    min-width="160"
                />

                <el-table-column :label="$t('common.operation')" width="320" fixed="right">
                    <template #default="{ row }">
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
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { planApi } from '@/api/plan'
import { tenantApi } from '@/api/tenant'
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
    handleDelete
} = useListPage<TenantInfo, TenantSearchForm>({
    fetchFn: (params) => tenantApi.list(params as TenantQuery),
    deleteFn: (id) => tenantApi.destroy(id),
    defaultSearchForm: {
        keyword: '',
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

.tenant-container {
    /* 外层 LayoutMain 已有 padding */
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
