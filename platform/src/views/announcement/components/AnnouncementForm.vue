<template>
    <el-dialog
        v-model="visible"
        class="dialog-lg"
        :title="isEdit ? $t('announcement.editAnnouncement') : $t('announcement.addAnnouncement')"
        :close-on-click-modal="false"
        @closed="resetForm"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="80px">
            <el-form-item :label="$t('announcement.announcementTitle')" prop="title">
                <el-input
                    v-model="form.title"
                    :placeholder="$t('announcement.titlePlaceholder')"
                    maxlength="200"
                    show-word-limit
                />
            </el-form-item>

            <el-row :gutter="16">
                <el-col :span="12">
                    <el-form-item :label="$t('common.type')" prop="type">
                        <el-select
                            v-model="form.type"
                            :placeholder="$t('announcement.typePlaceholder')"
                            style="width: 100%"
                        >
                            <el-option :label="$t('announcement.notice')" value="notice" />
                            <el-option :label="$t('announcement.update')" value="update" />
                            <el-option :label="$t('announcement.warning')" value="warning" />
                        </el-select>
                    </el-form-item>
                </el-col>
                <el-col :span="12">
                    <el-form-item :label="$t('common.status')" prop="status">
                        <el-select
                            v-model="form.status"
                            :placeholder="$t('announcement.statusPlaceholder')"
                            style="width: 100%"
                        >
                            <el-option :label="$t('announcement.draft')" value="draft" />
                            <el-option :label="$t('announcement.published')" value="published" />
                        </el-select>
                    </el-form-item>
                </el-col>
            </el-row>

            <el-form-item :label="$t('announcement.content')" prop="content">
                <el-input
                    v-model="form.content"
                    type="textarea"
                    :rows="8"
                    :placeholder="$t('announcement.contentPlaceholder')"
                    maxlength="10000"
                    show-word-limit
                />
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

<script setup lang="ts" name="AnnouncementForm">
import type { FormRules } from 'element-plus'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { platformAnnouncementApi } from '@/api/announcement'
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

interface AnnouncementFormData {
    id?: number
    title: string
    type: string
    status: string
    content: string
}

const isEdit = computed(() => !!props.sourceId)

const sourceData = ref<Partial<AnnouncementFormData> | undefined>(undefined)

const loadDetail = async (id: number) => {
    try {
        const res = await platformAnnouncementApi.show(id)
        const data = res.data
        if (data) {
            sourceData.value = {
                id: data.id,
                title: data.title || '',
                type: data.type || 'notice',
                status: data.status || 'draft',
                content: data.content || ''
            }
        }
    } catch (error) {
        console.error('Failed to load announcement detail:', error)
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
    useFormDialog<AnnouncementFormData>({
        defaultForm: {
            id: undefined,
            title: '',
            type: 'notice',
            status: 'draft',
            content: ''
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => {
            const { id: _id, ...payload } = data
            return platformAnnouncementApi.create(payload)
        },
        updateFn: (id, data) => {
            const { id: _id, ...payload } = data
            return platformAnnouncementApi.update(id, payload)
        },
        sourceData: () => sourceData.value
    })

const rules: FormRules = {
    title: [
        { required: true, message: t('announcement.validate.titleRequired'), trigger: 'blur' },
        { max: 200, message: t('announcement.validate.titleMax'), trigger: 'blur' }
    ],
    type: [{ required: true, message: t('announcement.validate.typeRequired'), trigger: 'change' }],
    status: [
        { required: true, message: t('announcement.validate.statusRequired'), trigger: 'change' }
    ],
    content: [
        { required: true, message: t('announcement.validate.contentRequired'), trigger: 'blur' }
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
