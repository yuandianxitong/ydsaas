<template>
    <el-dialog
        v-model="visible"
        class="dialog-md"
        :title="$t('system.role.assignPermissions')"
        :close-on-click-modal="false"
        @open="handleOpen"
        @close="handleClose"
    >
        <div v-if="roleInfo" class="assign-header">
            <div class="role-info">
                <span class="label">{{ $t('system.admin.role') }}:</span>
                <el-tag type="primary">{{ roleInfo.name }}</el-tag>
            </div>
        </div>

        <div class="tree-actions">
            <el-checkbox
                v-model="menuCheckAll"
                :indeterminate="menuIndeterminate"
                @change="handleMenuCheckAllChange"
            >
                {{ $t('system.role.selectAll') }}
            </el-checkbox>
            <el-input
                v-model="filterText"
                :placeholder="$t('common.search')"
                clearable
                style="width: 200px"
                size="small"
            />
        </div>

        <el-tree
            ref="menuTreeRef"
            :data="menuTree"
            :props="menuTreeProps"
            show-checkbox
            node-key="id"
            :default-checked-keys="selectedMenus"
            :default-expanded-keys="menuExpandKeys"
            :filter-node-method="filterNode"
            class="menu-tree"
            @check="handleMenuCheck"
        >
            <template #default="{ data }">
                <div class="tree-node">
                    <span class="node-label">{{ data.title || data.name }}</span>
                    <el-tag
                        v-if="data.type"
                        :type="getMenuTypeTag(data.type)"
                        size="small"
                        class="node-tag"
                    >
                        {{ getMenuTypeText(data.type) }}
                    </el-tag>
                    <span v-if="data.permission" class="node-permission">{{
                        data.permission
                    }}</span>
                </div>
            </template>
        </el-tree>

        <template #footer>
            <span class="dialog-footer">
                <el-button @click="handleClose">{{ $t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="submitLoading" @click="handleSubmit">
                    {{ $t('common.confirm') }}
                </el-button>
            </span>
        </template>
    </el-dialog>
</template>

<script setup lang="ts" name="PlatformAssignPermissionsDialog">
import { ElMessage, ElTree } from 'element-plus'
import { computed, ref, watch } from 'vue'
import { useI18n } from 'vue-i18n'

import { platformRoleApi } from '@/api/system'

const { t } = useI18n()

interface Props {
    modelValue: boolean
    roleInfo: any | null
}

interface Emits {
    (e: 'update:modelValue', value: boolean): void
    (e: 'success'): void
}

const props = defineProps<Props>()
const emit = defineEmits<Emits>()

const menuTreeRef = ref<InstanceType<typeof ElTree>>()

const visible = computed({
    get: () => props.modelValue,
    set: (value) => emit('update:modelValue', value)
})

// 菜单树数据
const menuTree = ref<any[]>([])
const selectedMenus = ref<number[]>([])
const menuExpandKeys = ref<number[]>([])
const menuCheckAll = ref(false)
const menuIndeterminate = ref(false)
const filterText = ref('')
const submitLoading = ref(false)

const menuTreeProps = {
    children: 'children',
    label: 'title'
}

// 搜索过滤
watch(filterText, (val) => {
    menuTreeRef.value?.filter(val)
})

const filterNode = (value: string, data: any) => {
    if (!value) return true
    return (
        data.title?.includes(value) ||
        data.name?.includes(value) ||
        data.permission?.includes(value)
    )
}

// 获取菜单树
const getMenuTree = async () => {
    try {
        const res = await platformRoleApi.menuTree()
        const data = res.data
        const list = Array.isArray(data) ? data : ((data as any)?.list ?? [])
        menuTree.value = list
        menuExpandKeys.value = getExpandKeys(list)
    } catch (error) {
        console.error('获取菜单树失败:', error)
    }
}

// 获取角色已分配的权限
const getRolePermissions = async () => {
    if (!props.roleInfo?.id) return
    try {
        const res = await platformRoleApi.show(props.roleInfo.id)
        const data = res.data
        selectedMenus.value = data?.menu_ids || []
        updateMenuCheckAllStatus()
    } catch (error) {
        console.error('获取角色权限失败:', error)
    }
}

// 获取展开的菜单节点键
const getExpandKeys = (menus: any[]): number[] => {
    const keys: number[] = []
    const traverse = (items: any[]) => {
        items.forEach((item) => {
            if (item.children && item.children.length > 0) {
                keys.push(item.id)
                traverse(item.children)
            }
        })
    }
    traverse(menus)
    return keys
}

// 菜单选择变化
const handleMenuCheck = () => {
    updateMenuCheckAllStatus()
}

// 更新全选状态
const updateMenuCheckAllStatus = () => {
    const checkedKeys = menuTreeRef.value?.getCheckedKeys() || []
    const allKeys = getAllMenuKeys(menuTree.value)
    const checkedCount = checkedKeys.length
    const totalCount = allKeys.length

    menuCheckAll.value = checkedCount === totalCount && totalCount > 0
    menuIndeterminate.value = checkedCount > 0 && checkedCount < totalCount
}

// 获取所有菜单节点键
const getAllMenuKeys = (tree: any[]): number[] => {
    const keys: number[] = []
    const traverse = (items: any[]) => {
        items.forEach((item) => {
            keys.push(item.id)
            if (item.children && item.children.length > 0) {
                traverse(item.children)
            }
        })
    }
    traverse(tree)
    return keys
}

// 全选/取消全选
const handleMenuCheckAllChange = (checked: boolean | string | number) => {
    const allKeys = getAllMenuKeys(menuTree.value)
    if (checked) {
        menuTreeRef.value?.setCheckedKeys(allKeys)
    } else {
        menuTreeRef.value?.setCheckedKeys([])
    }
    updateMenuCheckAllStatus()
}

// 获取菜单类型文本
const getMenuTypeText = (type: number) => {
    const typeMap: Record<number, string> = {
        1: t('system.menu.directory'),
        2: t('system.menu.menu'),
        3: t('system.menu.button')
    }
    return typeMap[type] || ''
}

// 获取菜单类型标签样式
const getMenuTypeTag = (type: number): 'primary' | 'success' | 'warning' | 'info' | 'danger' => {
    const typeMap: Record<number, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
        1: 'info',
        2: 'success',
        3: 'warning'
    }
    return typeMap[type] || 'info'
}

// 提交保存
const handleSubmit = async () => {
    if (!props.roleInfo?.id) return

    submitLoading.value = true
    try {
        const checkedKeys = (menuTreeRef.value?.getCheckedKeys() || []).map(Number).filter(Boolean)
        const halfCheckedKeys = (menuTreeRef.value?.getHalfCheckedKeys() || [])
            .map(Number)
            .filter(Boolean)
        const menuIds = [...checkedKeys, ...halfCheckedKeys]

        await platformRoleApi.assignPermissions(props.roleInfo.id, menuIds)
        ElMessage.success(t('common.success'))
        emit('success')
        handleClose()
    } catch (error) {
        console.error('权限分配失败:', error)
    } finally {
        submitLoading.value = false
    }
}

// 打开弹窗
const handleOpen = async () => {
    await getMenuTree()
    await getRolePermissions()
}

// 关闭弹窗
const handleClose = () => {
    filterText.value = ''
    selectedMenus.value = []
    menuCheckAll.value = false
    menuIndeterminate.value = false
    visible.value = false
}
</script>

<style lang="scss" scoped>
.assign-header {
    margin-bottom: 16px;

    .role-info {
        display: flex;
        align-items: center;
        gap: 8px;

        .label {
            color: var(--el-text-color-regular);
            font-size: 14px;
        }
    }
}

.tree-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--el-border-color-light);
}

.menu-tree {
    max-height: 450px;
    overflow-y: auto;
    border: 1px solid var(--el-border-color-light);
    border-radius: 4px;
    padding: 8px;
}

.tree-node {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;

    .node-label {
        font-size: 14px;
        color: var(--el-text-color-primary);
    }

    .node-tag {
        margin-left: 4px;
    }

    .node-permission {
        margin-left: auto;
        font-size: 12px;
        color: var(--el-text-color-placeholder);
    }
}

.dialog-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
}
</style>
