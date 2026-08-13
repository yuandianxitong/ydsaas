<template>
    <el-dialog
        v-model="visible"
        class="dialog-md"
        :title="isEdit ? $t('system.role.editRole') : $t('system.role.addRole')"
        :close-on-click-modal="false"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="80px">
            <el-form-item :label="$t('system.role.roleName')" prop="name">
                <el-input
                    v-model="form.name"
                    :placeholder="$t('system.role.roleName')"
                    maxlength="50"
                />
            </el-form-item>

            <el-form-item :label="$t('common.description')" prop="description">
                <el-input
                    v-model="form.description"
                    type="textarea"
                    :rows="3"
                    :placeholder="$t('common.description')"
                    maxlength="200"
                />
            </el-form-item>

            <el-form-item :label="$t('common.status')" prop="status">
                <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
            </el-form-item>
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

<script setup lang="ts" name="PlatformRoleForm">
import type { FormRules } from 'element-plus'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { platformRoleApi } from '@/api/system'
import { useFormDialog } from '@/hooks/useFormDialog'

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

interface RoleFormData {
    id?: number
    name: string
    description: string
    status: number
}

const isEdit = computed(() => !!props.sourceId)

const sourceData = ref<Partial<RoleFormData> | undefined>(undefined)

const loadDetail = async (id: number) => {
    try {
        const res = await platformRoleApi.show(id)
        const data = res.data
        if (data) {
            sourceData.value = {
                id: data.id,
                name: data.name || '',
                description: data.description || '',
                status: data.status ?? 1
            }
        }
    } catch (error) {
        console.error('加载角色详情失败:', error)
    }
}

watch(
    () => props.modelValue,
    (val) => {
        if (val) {
            if (props.sourceId) {
                loadDetail(props.sourceId)
            } else {
                sourceData.value = undefined
            }
        } else {
            sourceData.value = undefined
        }
    }
)

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<RoleFormData>({
        defaultForm: {
            id: undefined,
            name: '',
            description: '',
            status: 1
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => {
            const { id: _id, ...payload } = data
            return platformRoleApi.create(payload)
        },
        updateFn: (id, data) => {
            const { id: _id, ...payload } = data
            return platformRoleApi.update(id, payload)
        },
        sourceData: () => sourceData.value
    })

const rules: FormRules = {
    name: [
        { required: true, message: t('message.required'), trigger: 'blur' },
        { min: 2, max: 50, message: t('message.required'), trigger: 'blur' }
    ]
}
</script>

<style lang="scss" scoped>
.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
