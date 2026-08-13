<template>
    <el-dialog
        v-model="visible"
        class="dialog-xl"
        :title="isEdit ? $t('tenant.editTenant') : $t('tenant.addTenant')"
        :close-on-click-modal="false"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="110px">
            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('tenant.tenantCode')" prop="tenant_code">
                        <el-input
                            v-model="form.tenant_code"
                            :placeholder="$t('tenant.tenantCodePlaceholder')"
                            :disabled="isEdit"
                            maxlength="50"
                        />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('tenant.tenantName')" prop="name">
                        <el-input
                            v-model="form.name"
                            :placeholder="$t('tenant.tenantNamePlaceholder')"
                            maxlength="100"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-row v-if="!isEdit" :gutter="16">
                <el-col :span="24">
                    <el-form-item :label="$t('tenant.openingMode')" prop="opening_mode">
                        <el-radio-group v-model="form.opening_mode" class="opening-mode-group">
                            <el-radio-button value="trial">{{ $t('tenant.modeTrial') }}</el-radio-button>
                            <el-radio-button value="formal">{{
                                $t('tenant.modeFormal')
                            }}</el-radio-button>
                            <el-radio-button value="none">{{ $t('tenant.modeNone') }}</el-radio-button>
                        </el-radio-group>
                    </el-form-item>
                </el-col>
            </el-row>

            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('tenant.plan')" prop="plan_id">
                        <el-select
                            v-model="form.plan_id"
                            :placeholder="$t('tenant.planPlaceholder')"
                            clearable
                            style="width: 100%"
                        >
                            <el-option
                                v-for="plan in planOptions"
                                :key="plan.id"
                                :label="`${plan.name} (${plan.code})`"
                                :value="plan.id"
                            />
                        </el-select>
                    </el-form-item>
                </el-col>
            </el-row>

            <el-row v-if="!isEdit && form.opening_mode === 'trial'" :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('tenant.trialDays')" prop="trial_days">
                        <el-input-number
                            v-model="form.trial_days"
                            :min="1"
                            :max="365"
                            :step="1"
                            controls-position="right"
                            style="width: 100%"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-row v-if="!isEdit && form.opening_mode === 'formal'" :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('tenant.months')" prop="months">
                        <el-input-number
                            v-model="form.months"
                            :min="1"
                            :max="120"
                            :step="1"
                            controls-position="right"
                            style="width: 100%"
                        />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('tenant.offlineRemark')" prop="remark">
                        <el-input
                            v-model="form.remark"
                            :placeholder="$t('tenant.offlineRemarkPlaceholder')"
                            maxlength="255"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('tenant.contactName')" prop="contact_name">
                        <el-input
                            v-model="form.contact_name"
                            :placeholder="$t('tenant.contactNamePlaceholder')"
                            maxlength="50"
                        />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('tenant.contactPhone')" prop="contact_phone">
                        <el-input
                            v-model="form.contact_phone"
                            :placeholder="$t('tenant.contactPhone')"
                            maxlength="20"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="$t('tenant.accountEnabled')" prop="status">
                <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
            </el-form-item>

            <template v-if="!isEdit">
                <el-divider content-position="left">{{ $t('tenant.initialAdmin') }}</el-divider>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="$t('tenant.adminUsername')" prop="admin_username">
                            <el-input
                                v-model="form.admin_username"
                                :placeholder="$t('tenant.adminUsernamePlaceholder')"
                                maxlength="30"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item :label="$t('tenant.adminPassword')" prop="admin_password">
                            <el-input
                                v-model="form.admin_password"
                                type="password"
                                :placeholder="$t('tenant.adminPasswordPlaceholder')"
                                maxlength="30"
                                show-password
                            />
                        </el-form-item>
                    </el-col>
                </el-row>
            </template>
        </el-form>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="handleClose">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="submitting" @click="handleSubmit">
                    {{ $t('common.confirm') }}
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts" name="TenantForm">
import type { FormRules } from 'element-plus'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { planApi } from '@/api/plan'
import { tenantApi } from '@/api/tenant'
import { useFormDialog } from '@/hooks/useFormDialog'
import type { PlanInfo, TenantReq } from '@/types/api'

const { t } = useI18n()

interface Props {
    modelValue: boolean
    sourceId?: number
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = withDefaults(defineProps<Props>(), {
    sourceId: undefined
})
const emit = defineEmits<Emits>()

type TenantFormData = TenantReq & {
    id?: number
    tenant_code: string
    name: string
    plan_id?: number
    opening_mode?: 'trial' | 'formal' | 'none'
    trial_days?: number
    months?: number
    remark?: string
    contact_name?: string
    contact_phone?: string
    status: number
    admin_username?: string
    admin_password?: string
}

const isEdit = computed(() => !!props.sourceId)
const sourceData = ref<Partial<TenantFormData> | undefined>(undefined)
const planOptions = ref<PlanInfo[]>([])

const loadPlanOptions = async () => {
    try {
        const res = await planApi.options()
        planOptions.value = res.data || []
    } catch (error) {
        console.error('Failed to load plan options:', error)
    }
}

const loadTenantDetail = async (id: number) => {
    try {
        const res = await tenantApi.show(id)
        const data = res.data
        if (data) {
            sourceData.value = {
                id: data.id,
                tenant_code: data.tenant_code,
                name: data.name,
                plan_id: data.plan_id,
                contact_name: data.contact_name || '',
                contact_phone: data.contact_phone || '',
                status: data.status ?? 1
            }
        }
    } catch (error) {
        console.error('Failed to load tenant detail:', error)
    }
}

watch(
    () => props.modelValue,
    (val) => {
        if (val) {
            loadPlanOptions()
            if (props.sourceId) {
                loadTenantDetail(props.sourceId)
            } else {
                sourceData.value = undefined
            }
        } else {
            sourceData.value = undefined
        }
    }
)

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<TenantFormData>({
        defaultForm: {
            id: undefined,
            tenant_code: '',
            name: '',
            plan_id: undefined,
            opening_mode: 'trial',
            trial_days: 30,
            months: 12,
            remark: '',
            contact_name: '',
            contact_phone: '',
            status: 1,
            admin_username: '',
            admin_password: ''
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => {
            const {
                id: _id,
                trial_days,
                months,
                remark,
                opening_mode = 'trial',
                ...rest
            } = data
            const payload: TenantReq = {
                ...rest,
                opening_mode
            }
            if (opening_mode === 'trial') {
                payload.trial_days = trial_days ?? 30
                delete payload.months
                delete payload.remark
            } else if (opening_mode === 'formal') {
                payload.months = months ?? 12
                if (remark) payload.remark = remark
                delete payload.trial_days
            } else {
                delete payload.trial_days
                delete payload.months
                delete payload.remark
            }
            return tenantApi.create(payload)
        },
        updateFn: (id, data) => {
            const {
                id: _id,
                tenant_code: _code,
                trial_days: _t,
                months: _m,
                remark: _r,
                opening_mode: _o,
                admin_username: _au,
                admin_password: _ap,
                ...payload
            } = data
            return tenantApi.update(id, payload)
        },
        sourceData: () => sourceData.value
    })

const rules = computed<FormRules>(() => {
    const base: FormRules = {
        tenant_code: isEdit.value
            ? []
            : [
                  { required: true, message: t('tenant.validate.codeRequired'), trigger: 'blur' },
                  { min: 3, max: 50, message: t('tenant.validate.codeLength'), trigger: 'blur' },
                  {
                      pattern: /^[a-zA-Z0-9_-]+$/,
                      message: t('tenant.validate.codeFormat'),
                      trigger: 'blur'
                  }
              ],
        name: [
            { required: true, message: t('tenant.validate.nameRequired'), trigger: 'blur' },
            { min: 2, max: 100, message: t('tenant.validate.nameLength'), trigger: 'blur' }
        ],
        admin_username: isEdit.value
            ? []
            : [
                  {
                      required: true,
                      message: t('tenant.validate.adminUsernameRequired'),
                      trigger: 'blur'
                  },
                  {
                      min: 4,
                      max: 30,
                      message: t('tenant.validate.adminUsernameLength'),
                      trigger: 'blur'
                  },
                  {
                      pattern: /^[a-zA-Z0-9]+$/,
                      message: t('tenant.validate.adminUsernameFormat'),
                      trigger: 'blur'
                  }
              ],
        admin_password: isEdit.value
            ? []
            : [
                  {
                      required: true,
                      message: t('tenant.validate.adminPasswordRequired'),
                      trigger: 'blur'
                  },
                  {
                      min: 6,
                      max: 30,
                      message: t('tenant.validate.adminPasswordLength'),
                      trigger: 'blur'
                  }
              ]
    }

    if (!isEdit.value && form.opening_mode !== 'none') {
        base.plan_id = [
            { required: true, message: t('tenant.validate.planRequired'), trigger: 'change' }
        ]
    }
    if (!isEdit.value && form.opening_mode === 'formal') {
        base.months = [
            { required: true, message: t('tenant.validate.monthsRequired'), trigger: 'change' }
        ]
    }

    return base
})
</script>

<style lang="scss" scoped>
.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.opening-mode-group {
    flex-wrap: nowrap;
    white-space: nowrap;
}
</style>
