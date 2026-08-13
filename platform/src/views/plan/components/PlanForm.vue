<template>
    <el-dialog
        v-model="visible"
        class="dialog-xl"
        :title="isEdit ? $t('plan.editPlan') : $t('plan.addPlan')"
        :close-on-click-modal="false"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="110px">
            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('plan.planCode')" prop="code">
                        <el-input
                            v-model="form.code"
                            :placeholder="$t('plan.codePlaceholder')"
                            :disabled="isEdit"
                            maxlength="50"
                        />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('plan.planName')" prop="name">
                        <el-input
                            v-model="form.name"
                            :placeholder="$t('plan.namePlaceholder')"
                            maxlength="100"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="$t('common.description')" prop="description">
                <el-input
                    v-model="form.description"
                    type="textarea"
                    :placeholder="$t('plan.descriptionPlaceholder')"
                    :autosize="{ minRows: 2, maxRows: 4 }"
                    maxlength="1000"
                    show-word-limit
                />
            </el-form-item>

            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('plan.priceMonthly')" prop="price_monthly">
                        <el-input-number
                            v-model="form.price_monthly"
                            :min="0"
                            :precision="2"
                            :step="1"
                            style="width: 200px"
                        />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('plan.priceYearly')" prop="price_yearly">
                        <el-input-number
                            v-model="form.price_yearly"
                            :min="0"
                            :precision="2"
                            :step="1"
                            style="width: 200px"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="$t('plan.storageQuota')">
                <el-input-number
                    v-model="storageGb"
                    :min="0"
                    :precision="1"
                    :step="1"
                    style="width: 200px"
                />
                <span class="storage-hint"> = {{ formatBytes(form.storage_limit_bytes) }} </span>
            </el-form-item>

            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('common.sort')" prop="sort">
                        <el-input-number
                            v-model="form.sort"
                            :min="0"
                            :step="1"
                            style="width: 200px"
                        />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('common.status')" prop="status">
                        <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
                    </el-form-item>
                </el-col>
            </el-row>

            <PluginGrantPanel
                ref="grantPanelRef"
                v-model:selected-ids="pluginGrantIds"
                v-model:auto-enable-ids="pluginAutoEnableIds"
                :plan-id="props.sourceId ?? null"
            />
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

<script setup lang="ts" name="PlanForm">
import type { FormRules } from 'element-plus'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { planApi } from '@/api/plan'
import { useFormDialog } from '@/hooks/useFormDialog'
import type { PlanReq } from '@/types/api'

import PluginGrantPanel from './PluginGrantPanel.vue'

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

// 表单内部完整字段
type PlanFormData = PlanReq & {
    id?: number
    code: string
    name: string
    description: string
    price_monthly: number
    price_yearly: number
    storage_limit_bytes: number
    sort: number
    status: number
}

// 是否编辑模式
const isEdit = computed(() => !!props.sourceId)

// 编辑模式下从后端拉到的源数据
const sourceData = ref<Partial<PlanFormData> | undefined>(undefined)

// 插件授权面板（Stage 3）
const grantPanelRef = ref<InstanceType<typeof PluginGrantPanel> | null>(null)
const pluginGrantIds = ref<number[]>([])
const pluginAutoEnableIds = ref<number[]>([])

const loadPlanDetail = async (id: number) => {
    try {
        const res = await planApi.show(id)
        const data = res.data
        if (data) {
            // storage_limit_bytes 可能来自 BigInt 列，做一次 Number 兜底
            const bytes =
                typeof data.storage_limit_bytes === 'string'
                    ? Number(data.storage_limit_bytes)
                    : (data.storage_limit_bytes ?? 0)
            sourceData.value = {
                id: data.id,
                code: data.code,
                name: data.name,
                description: data.description || '',
                price_monthly: Number(data.price_monthly ?? 0),
                price_yearly: Number(data.price_yearly ?? 0),
                storage_limit_bytes: Number.isFinite(bytes) ? bytes : 0,
                sort: data.sort ?? 0,
                status: data.status ?? 1
            }
        }
    } catch (error) {
        console.error('Failed to load plan detail:', error)
    }
}

// 监听 modelValue 打开时加载数据
watch(
    () => props.modelValue,
    (val) => {
        if (val) {
            if (props.sourceId) {
                loadPlanDetail(props.sourceId)
            } else {
                sourceData.value = undefined
            }
        } else {
            sourceData.value = undefined
        }
    }
)

const defaultForm: PlanFormData = {
    id: undefined,
    code: '',
    name: '',
    description: '',
    price_monthly: 0,
    price_yearly: 0,
    storage_limit_bytes: 0,
    sort: 0,
    status: 1
}

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<PlanFormData>({
        defaultForm,
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        // 创建：保留 code
        createFn: async (data) => {
            const { id: _id, ...payload } = data
            const res = await planApi.create(payload)
            const newId = (res.data as { id?: number } | undefined)?.id
            if (newId && grantPanelRef.value) {
                try {
                    await grantPanelRef.value.commit(newId)
                } catch {
                    /* axios 拦截器已提示 */
                }
            }
            return res
        },
        // 编辑：strip code（后端禁止修改）
        updateFn: async (id, data) => {
            const { id: _id, code: _code, ...payload } = data
            const res = await planApi.update(id, payload)
            if (grantPanelRef.value) {
                try {
                    await grantPanelRef.value.commit(id)
                } catch {
                    /* axios 拦截器已提示 */
                }
            }
            return res
        },
        sourceData: () => sourceData.value
    })

// 存储配额 GB 双向同步（form.storage_limit_bytes <-> storageGb）
const storageGb = ref(0)

// form.storage_limit_bytes → storageGb
// 先定义，确保先初始化 storageGb，再设置反向 watcher
watch(
    () => form.storage_limit_bytes,
    (v) => {
        const n = typeof v === 'string' ? Number(v) : (v ?? 0)
        storageGb.value = Number.isFinite(n) && n > 0 ? +(n / 1073741824).toFixed(1) : 0
    },
    { immediate: true }
)

// storageGb → form.storage_limit_bytes
watch(storageGb, (v) => {
    form.storage_limit_bytes = Math.round((v || 0) * 1073741824)
})

// bytes 格式化（和 index.vue 保持一致）
function formatBytes(bytes: number | string | undefined | null): string {
    if (bytes === null || bytes === undefined || bytes === '') return '-'
    const n = typeof bytes === 'string' ? Number(bytes) : bytes
    if (!Number.isFinite(n) || n < 0) return '-'
    if (n >= 1073741824) return (n / 1073741824).toFixed(1) + ' GB'
    if (n >= 1048576) return (n / 1048576).toFixed(1) + ' MB'
    if (n >= 1024) return (n / 1024).toFixed(1) + ' KB'
    return n + ' B'
}

// 表单校验规则
const rules = computed<FormRules>(() => ({
    code: isEdit.value
        ? []
        : [
              { required: true, message: t('plan.validate.codeRequired'), trigger: 'blur' },
              { min: 2, max: 50, message: t('plan.validate.codeLength'), trigger: 'blur' },
              {
                  pattern: /^[a-zA-Z0-9_-]+$/,
                  message: t('plan.validate.codeFormat'),
                  trigger: 'blur'
              }
          ],
    name: [
        { required: true, message: t('plan.validate.nameRequired'), trigger: 'blur' },
        { min: 2, max: 100, message: t('plan.validate.nameLength'), trigger: 'blur' }
    ],
    description: [{ max: 1000, message: t('plan.validate.descriptionMax'), trigger: 'blur' }]
}))
</script>

<style lang="scss" scoped>
.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}

.storage-hint {
    color: #9ca3af;
    margin-left: 8px;
}
</style>
