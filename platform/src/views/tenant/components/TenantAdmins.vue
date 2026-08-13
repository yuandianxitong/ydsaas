<template>
    <el-dialog
        v-model="visible"
        class="dialog-lg"
        :title="`${$t('tenant.tenantAdmins')} — ${tenantName}`"
        :close-on-click-modal="false"
    >
        <el-table v-loading="loading" :data="admins" stripe>
            <el-table-column label="ID" prop="id" width="80" />
            <el-table-column :label="$t('admin.username')" prop="username" min-width="120" />
            <el-table-column :label="$t('admin.nickname')" prop="nickname" min-width="120" />
            <el-table-column :label="$t('common.status')" width="90" align="center">
                <template #default="{ row }">
                    <el-tag :type="row.status === 1 ? 'success' : 'danger'" size="small">
                        {{ row.status === 1 ? $t('common.enabled') : $t('common.disabled') }}
                    </el-tag>
                </template>
            </el-table-column>
            <el-table-column
                :label="$t('system.admin.lastLoginIp')"
                prop="last_login_ip"
                min-width="130"
            >
                <template #default="{ row }">{{ row.last_login_ip || '—' }}</template>
            </el-table-column>
            <el-table-column
                :label="$t('system.admin.lastLoginTime')"
                prop="last_login_time"
                min-width="160"
            >
                <template #default="{ row }">{{ row.last_login_time || '—' }}</template>
            </el-table-column>
            <el-table-column :label="$t('common.operation')" width="120" fixed="right">
                <template #default="{ row }">
                    <el-button
                        v-perms="'platform.tenant.reset_password'"
                        type="warning"
                        size="small"
                        text
                        @click="openResetDialog(row)"
                    >
                        {{ $t('common.resetPassword') }}
                    </el-button>
                </template>
            </el-table-column>
        </el-table>

        <!-- 重置密码弹窗 -->
        <el-dialog
            v-model="resetVisible"
            class="dialog-sm"
            :title="$t('common.resetPassword')"
            append-to-body
            :close-on-click-modal="false"
        >
            <el-form ref="resetFormRef" :model="resetForm" :rules="resetRules" label-width="80px">
                <el-form-item :label="$t('admin.username')">
                    <el-input :model-value="resetAdmin?.username" disabled />
                </el-form-item>
                <el-form-item :label="$t('admin.newPassword')" prop="new_password">
                    <el-input
                        v-model="resetForm.new_password"
                        type="password"
                        :placeholder="$t('tenant.validate.adminPasswordLength')"
                        maxlength="30"
                        show-password
                    />
                </el-form-item>
            </el-form>
            <template #footer>
                <el-button @click="resetVisible = false">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="resetting" @click="handleReset">{{
                    $t('common.confirm')
                }}</el-button>
            </template>
        </el-dialog>
    </el-dialog>
</template>

<script setup lang="ts">
import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { tenantApi } from '@/api/tenant'
import type { TenantAdmin } from '@/types/api'

const { t } = useI18n()

interface Props {
    modelValue: boolean
    tenantId?: number
    tenantName?: string
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
}

const props = withDefaults(defineProps<Props>(), {
    tenantId: undefined,
    tenantName: ''
})
const emit = defineEmits<Emits>()

const visible = computed({
    get: () => props.modelValue,
    set: (v) => emit('update:modelValue', v)
})

const loading = ref(false)
const admins = ref<TenantAdmin[]>([])

const loadAdmins = async () => {
    if (!props.tenantId) return
    loading.value = true
    try {
        const res = await tenantApi.listAdmins(props.tenantId)
        admins.value = res.data || []
    } catch {
        admins.value = []
    } finally {
        loading.value = false
    }
}

watch(
    () => props.modelValue,
    (val) => {
        if (val && props.tenantId) {
            loadAdmins()
        } else {
            admins.value = []
        }
    }
)

// 重置密码
const resetVisible = ref(false)
const resetting = ref(false)
const resetAdmin = ref<TenantAdmin | null>(null)
const resetFormRef = ref<FormInstance>()
const resetForm = ref({ new_password: '' })

const resetRules: FormRules = {
    new_password: [
        { required: true, message: t('admin.validate.passwordRequired'), trigger: 'blur' },
        { min: 6, max: 30, message: t('admin.validate.passwordLength'), trigger: 'blur' }
    ]
}

const openResetDialog = (row: TenantAdmin) => {
    resetAdmin.value = row
    resetForm.value.new_password = ''
    resetVisible.value = true
}

const handleReset = async () => {
    if (!resetFormRef.value) return
    await resetFormRef.value.validate()
    if (!props.tenantId || !resetAdmin.value) return

    resetting.value = true
    try {
        await tenantApi.resetPassword(
            props.tenantId,
            resetAdmin.value.id,
            resetForm.value.new_password
        )
        ElMessage.success(t('admin.resetSuccess'))
        resetVisible.value = false
    } catch {
        // error handled by request interceptor
    } finally {
        resetting.value = false
    }
}
</script>
