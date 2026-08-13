<template>
    <el-dialog
        v-model="visible"
        class="dialog-lg"
        :title="isEdit ? $t('system.admin.editAdmin') : $t('system.admin.addAdmin')"
        :close-on-click-modal="false"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="90px">
            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('system.admin.username')" prop="username">
                        <el-input
                            v-model="form.username"
                            :placeholder="$t('system.admin.username')"
                            :disabled="isEdit"
                            maxlength="30"
                        />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('system.admin.nickname')" prop="real_name">
                        <el-input
                            v-model="form.real_name"
                            :placeholder="$t('system.admin.nickname')"
                            maxlength="50"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('system.admin.phone')" prop="mobile">
                        <el-input
                            v-model="form.mobile"
                            :placeholder="$t('system.admin.phone')"
                            maxlength="20"
                        />
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('system.admin.email')" prop="email">
                        <el-input
                            v-model="form.email"
                            :placeholder="$t('system.admin.email')"
                            maxlength="100"
                        />
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="$t('system.admin.role')" prop="role_ids">
                <el-select
                    v-model="form.role_ids"
                    multiple
                    :placeholder="$t('common.selectPlaceholder')"
                    style="width: 100%"
                >
                    <el-option
                        v-for="role in roleOptions"
                        :key="role.id"
                        :label="role.name"
                        :value="role.id"
                    />
                </el-select>
            </el-form-item>

            <el-form-item :label="$t('common.status')" prop="status">
                <el-switch v-model="form.status" :active-value="1" :inactive-value="0" />
            </el-form-item>

            <!-- 初始密码（仅创建时显示） -->
            <template v-if="!isEdit">
                <el-divider content-position="left">{{ $t('login.password') }}</el-divider>
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item :label="$t('login.password')" prop="password">
                            <el-input
                                v-model="form.password"
                                type="password"
                                :placeholder="$t('login.password')"
                                maxlength="30"
                                show-password
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item
                            :label="$t('system.admin.confirmPassword')"
                            prop="confirm_password"
                        >
                            <el-input
                                v-model="form.confirm_password"
                                type="password"
                                :placeholder="$t('system.admin.confirmPassword')"
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

<script setup lang="ts" name="PlatformAdminForm">
import type { FormRules } from 'element-plus'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { platformAdminApi } from '@/api/system'
import { useFormDialog } from '@/hooks/useFormDialog'

const { t } = useI18n()

interface Props {
    modelValue: boolean
    sourceId?: number
    roleOptions: Array<{ id: number; name: string }>
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = withDefaults(defineProps<Props>(), {
    sourceId: undefined
})
const emit = defineEmits<Emits>()

interface AdminFormData {
    id?: number
    username: string
    real_name: string
    mobile: string
    email: string
    password: string
    confirm_password: string
    role_ids: number[]
    status: number
}

const isEdit = computed(() => !!props.sourceId)

const sourceData = ref<Partial<AdminFormData> | undefined>(undefined)

const loadDetail = async (id: number) => {
    try {
        const res = await platformAdminApi.show(id)
        const data = res.data
        if (data) {
            sourceData.value = {
                id: data.id,
                username: data.username || '',
                real_name: data.real_name || '',
                mobile: data.mobile || '',
                email: data.email || '',
                password: '',
                confirm_password: '',
                role_ids: data.roles?.map((r: any) => r.id) || [],
                status: data.status ?? 1
            }
        }
    } catch (error) {
        console.error('加载管理员详情失败:', error)
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
    useFormDialog<AdminFormData>({
        defaultForm: {
            id: undefined,
            username: '',
            real_name: '',
            mobile: '',
            email: '',
            password: '',
            confirm_password: '',
            role_ids: [],
            status: 1
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => {
            const { id: _id, confirm_password: _c, ...payload } = data
            return platformAdminApi.create(payload)
        },
        updateFn: (id, data) => {
            const { id: _id, username: _u, password: _p, confirm_password: _c, ...payload } = data
            return platformAdminApi.update(id, payload)
        },
        sourceData: () => sourceData.value
    })

const rules = computed<FormRules>(() => ({
    username: isEdit.value
        ? []
        : [
              { required: true, message: t('message.required'), trigger: 'blur' },
              { min: 4, max: 30, message: t('message.required'), trigger: 'blur' },
              {
                  pattern: /^[a-zA-Z0-9_]+$/,
                  message: t('message.invalidFormat'),
                  trigger: 'blur'
              }
          ],
    real_name: [{ required: true, message: t('message.required'), trigger: 'blur' }],
    email: [{ type: 'email' as const, message: t('message.invalidFormat'), trigger: 'blur' }],
    password: isEdit.value
        ? []
        : [
              { required: true, message: t('message.required'), trigger: 'blur' },
              { min: 6, max: 30, message: t('message.required'), trigger: 'blur' }
          ],
    confirm_password: isEdit.value
        ? []
        : [
              { required: true, message: t('message.required'), trigger: 'blur' },
              {
                  validator: (_rule: any, value: string, callback: (err?: Error) => void) => {
                      if (value !== form.password) {
                          callback(new Error(t('login.message.password.inconformity')))
                      } else {
                          callback()
                      }
                  },
                  trigger: 'blur'
              }
          ]
}))
</script>

<style lang="scss" scoped>
.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
