<template>
    <el-dialog
        v-model="visible"
        class="dialog-md"
        :title="form.id ? $t('system.menu.editMenu') : $t('system.menu.addMenu')"
        :close-on-click-modal="false"
        @closed="handleDialogClosed"
    >
        <el-form ref="formRef" :model="form" :rules="rules" label-width="100px">
            <!-- 上级菜单 -->
            <el-form-item :label="$t('system.menu.parentMenu')" prop="parent_id">
                <el-tree-select
                    v-model="form.parent_id"
                    :data="parentTreeData"
                    node-key="id"
                    :props="{ label: 'title', children: 'children' }"
                    :placeholder="$t('common.selectPlaceholder')"
                    check-strictly
                    clearable
                    default-expand-all
                    style="width: 100%"
                />
            </el-form-item>

            <!-- 菜单类型 -->
            <el-form-item :label="$t('system.menu.menuType')" prop="type">
                <el-radio-group v-model="form.type" @change="handleTypeChange">
                    <el-radio :value="1">{{ $t('system.menu.directory') }}</el-radio>
                    <el-radio :value="2">{{ $t('system.menu.menu') }}</el-radio>
                    <el-radio :value="3">{{ $t('system.menu.button') }}</el-radio>
                </el-radio-group>
            </el-form-item>

            <!-- 菜单名称 -->
            <el-form-item :label="$t('system.menu.menuName')" prop="title">
                <el-input v-model="form.title" :placeholder="$t('system.menu.searchPlaceholder')" />
            </el-form-item>

            <!-- 路由名称（按钮类型不显示） -->
            <el-form-item v-if="form.type !== 3" :label="$t('system.menu.routeName')" prop="name">
                <el-input v-model="form.name" :placeholder="$t('system.menu.routeName')" />
            </el-form-item>

            <!-- 路由路径（按钮类型不显示） -->
            <el-form-item v-if="form.type !== 3" :label="$t('system.menu.routePath')" prop="path">
                <el-input v-model="form.path" :placeholder="$t('system.menu.routePath')" />
            </el-form-item>

            <!-- 组件路径（仅菜单类型显示） -->
            <el-form-item
                v-if="form.type === 2"
                :label="$t('system.menu.component')"
                prop="component"
            >
                <el-input v-model="form.component" :placeholder="$t('system.menu.component')">
                    <template #prepend>src/views/</template>
                    <template #append>.vue</template>
                </el-input>
            </el-form-item>

            <!-- 图标（按钮类型不显示） -->
            <el-form-item v-if="form.type !== 3" :label="$t('system.menu.icon')" prop="icon">
                <IconSelect v-model="form.icon" width="100%" />
            </el-form-item>

            <!-- 权限标识 -->
            <el-form-item :label="$t('system.menu.permission')" prop="permission">
                <el-input v-model="form.permission" placeholder="system.admin.list" />
            </el-form-item>

            <!-- 排序 -->
            <el-form-item :label="$t('common.sort')" prop="sort">
                <el-input-number
                    v-model="form.sort"
                    :min="0"
                    :max="9999"
                    controls-position="right"
                    style="width: 100%"
                />
            </el-form-item>

            <!-- 状态 -->
            <el-form-item :label="$t('common.status')" prop="status">
                <el-radio-group v-model="form.status">
                    <el-radio :value="1">{{ $t('common.enable') }}</el-radio>
                    <el-radio :value="0">{{ $t('common.disable') }}</el-radio>
                </el-radio-group>
            </el-form-item>

            <!-- 高级选项（按钮类型不显示） -->
            <template v-if="form.type !== 3">
                <el-divider content-position="left">{{ $t('system.menu.meta') }}</el-divider>

                <el-form-item :label="$t('system.menu.hidden')">
                    <el-switch v-model="metaForm.hidden" />
                </el-form-item>

                <el-form-item :label="$t('system.menu.cache')">
                    <el-switch v-model="metaForm.cache" />
                </el-form-item>

                <el-form-item :label="$t('system.menu.affix')">
                    <el-switch v-model="metaForm.affix" />
                </el-form-item>

                <el-form-item :label="$t('system.menu.badge')">
                    <el-input v-model="metaForm.badge" :placeholder="$t('system.menu.badge')" />
                </el-form-item>

                <el-form-item :label="$t('system.menu.iframe')">
                    <el-input v-model="metaForm.iframe" :placeholder="$t('system.menu.iframe')" />
                </el-form-item>
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

<script setup lang="ts" name="PlatformMenuForm">
import type { FormRules } from 'element-plus'
import { computed, reactive, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { platformMenuApi } from '@/api/system'
import IconSelect from '@/components/IconSelect/index.vue'
import { useFormDialog } from '@/hooks/useFormDialog'

const { t } = useI18n()

interface Props {
    modelValue: boolean
    formData: Record<string, any>
    parentOptions: any[]
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

interface MenuMeta {
    hidden: boolean
    cache: boolean
    affix: boolean
    badge: string
    iframe: string
}

interface MenuFormData {
    id?: number
    parent_id: number
    type: number
    title: string
    name: string
    path: string
    component: string
    icon: string
    permission: string
    sort: number
    status: number
}

const defaultMeta: MenuMeta = {
    hidden: false,
    cache: true,
    affix: false,
    badge: '',
    iframe: ''
}

const metaForm = reactive<MenuMeta>({ ...defaultMeta })

const { form, formRef, submitting, visible, handleSubmit, handleClose, resetForm } =
    useFormDialog<MenuFormData>({
        defaultForm: {
            id: undefined,
            parent_id: 0,
            type: 1,
            title: '',
            name: '',
            path: '',
            component: '',
            icon: '',
            permission: '',
            sort: 100,
            status: 1
        },
        modelValue: () => props.modelValue,
        onUpdate: (v) => emit('update:modelValue', v),
        onSuccess: () => emit('success'),
        createFn: (data) => {
            const { id: _id, ...payload } = data
            return platformMenuApi.create({
                ...payload,
                meta: payload.type !== 3 ? { ...metaForm } : undefined
            })
        },
        updateFn: (id, data) => {
            const { id: _id, ...payload } = data
            return platformMenuApi.update(id, {
                ...payload,
                meta: payload.type !== 3 ? { ...metaForm } : undefined
            })
        },
        sourceData: () => props.formData as Partial<MenuFormData>
    })

// 同步外部 formData.meta 到 metaForm
watch(
    () => props.formData,
    (newData) => {
        if (newData?.meta) {
            Object.assign(metaForm, defaultMeta, newData.meta)
        } else {
            Object.assign(metaForm, defaultMeta)
        }
    },
    { deep: true, immediate: true }
)

// 父级菜单树形数据（顶部插入顶级选项）
const parentTreeData = computed(() => [
    { id: 0, title: t('system.menu.rootMenu'), children: [] },
    ...(props.parentOptions || [])
])

// 表单校验规则
const rules = computed<FormRules>(() => ({
    title: [{ required: true, message: t('message.required'), trigger: 'blur' }],
    type: [{ required: true, message: t('message.required'), trigger: 'change' }],
    name: [
        {
            validator: (_rule: any, value: string, callback: (err?: Error) => void) => {
                if (form.type === 3) {
                    callback()
                    return
                }
                if (!value) {
                    callback(new Error(t('message.required')))
                    return
                }
                callback()
            },
            trigger: 'blur'
        }
    ],
    path: [
        {
            validator: (_rule: any, value: string, callback: (err?: Error) => void) => {
                if (form.type === 3) {
                    callback()
                    return
                }
                if (!value) {
                    callback(new Error(t('message.required')))
                    return
                }
                callback()
            },
            trigger: 'blur'
        }
    ],
    component: [
        {
            validator: (_rule: any, value: string, callback: (err?: Error) => void) => {
                if (form.type !== 2) {
                    callback()
                    return
                }
                if (!value) {
                    callback(new Error(t('message.required')))
                    return
                }
                callback()
            },
            trigger: 'blur'
        }
    ]
}))

// 类型变更处理
const handleTypeChange = () => {
    if (form.type === 3) {
        form.name = ''
        form.path = ''
        form.component = ''
        form.icon = ''
    }
}

// 弹窗关闭后重置 metaForm
const handleDialogClosed = () => {
    resetForm()
    Object.assign(metaForm, defaultMeta)
}
</script>

<style lang="scss" scoped>
.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
