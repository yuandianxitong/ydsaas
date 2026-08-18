<template>
    <div class="menu-container">
        <div class="page-head">
            <div>
                <div class="page-title">{{ $t('system.menu.title') }}</div>
                <div class="page-desc">{{ $t('system.menu.desc') }}</div>
            </div>
            <div class="page-actions">
                <el-button @click="expandAllNodes">
                    {{ isExpandAll ? $t('common.collapseAll') : $t('common.expandAll') }}
                </el-button>
                <el-button type="primary" @click="handleAdd()">
                    <i class="i-svg:plus" />
                    {{ $t('system.menu.addMenu') }}
                </el-button>
            </div>
        </div>

        <div class="split">
            <div class="panel">
                <div class="panel-head">
                    <div class="panel-title">{{ $t('system.menu.menuStructure') }}</div>
                    <el-button size="small" text type="primary" @click="handleAdd()">
                        <i class="i-svg:plus" />
                        {{ $t('common.add') }}
                    </el-button>
                </div>
                <div class="tree-search">
                    <el-input
                        v-model="treeFilter"
                        :placeholder="$t('system.menu.searchPlaceholder')"
                        clearable
                    >
                        <template #prefix><i class="i-svg:search" /></template>
                    </el-input>
                </div>
                <div v-loading="loading" class="tree">
                    <template v-for="node in filteredMenuList" :key="node.id">
                        <MenuTreeNode
                            :node="node"
                            :depth="0"
                            :selected-id="selectedMenuId"
                            :open-map="openMap"
                            @select="handleTreeSelect"
                            @toggle="handleTreeToggle"
                        />
                    </template>
                </div>
            </div>

            <div class="panel">
                <template v-if="selectedMenu">
                    <div class="panel-head">
                        <div class="panel-title">{{ $t('system.menu.menuDetail') }}</div>
                        <div style="display: flex; gap: 6px">
                            <el-button size="small" @click="handleAdd(selectedMenu)">
                                {{ $t('system.menu.addChildMenu') }}
                            </el-button>
                            <el-button size="small" type="danger" @click="handleDelete(selectedMenu)">
                                {{ $t('common.delete') }}
                            </el-button>
                        </div>
                    </div>
                    <div class="panel-body">
                        <el-form
                            ref="editFormRef"
                            :model="editForm"
                            :rules="editRules"
                            label-position="top"
                        >
                            <div class="form-sec-title">{{ $t('system.menu.basicInfo') }}</div>
                            <div class="form-grid">
                                <div class="form-row">
                                    <div class="form-label">
                                        {{ $t('system.menu.menuType') }}<span class="req">*</span>
                                    </div>
                                    <el-radio-group
                                        v-model="editForm.type"
                                        @change="handleEditTypeChange"
                                    >
                                        <el-radio :value="1">{{
                                            $t('system.menu.directory')
                                        }}</el-radio>
                                        <el-radio :value="2">{{ $t('system.menu.menu') }}</el-radio>
                                        <el-radio :value="3">{{
                                            $t('system.menu.button')
                                        }}</el-radio>
                                    </el-radio-group>
                                </div>
                                <div class="form-row">
                                    <div class="form-label">
                                        {{ $t('system.menu.parentMenu') }}<span class="req">*</span>
                                    </div>
                                    <el-tree-select
                                        v-model="editForm.parent_id"
                                        :data="editParentTreeData"
                                        node-key="id"
                                        :props="{ label: 'title', children: 'children' }"
                                        :placeholder="$t('system.menu.parentMenuPlaceholder')"
                                        check-strictly
                                        clearable
                                        default-expand-all
                                        style="width: 100%"
                                    />
                                </div>
                                <div class="form-row">
                                    <div class="form-label">
                                        {{ $t('system.menu.menuName') }}<span class="req">*</span>
                                    </div>
                                    <el-input
                                        v-model="editForm.title"
                                        :placeholder="$t('system.menu.namePlaceholder')"
                                    />
                                </div>
                                <div class="form-row">
                                    <div class="form-label">{{ $t('system.menu.permission') }}</div>
                                    <el-input
                                        v-model="editForm.permission"
                                        :placeholder="$t('system.menu.permPlaceholder')"
                                    />
                                </div>
                                <div v-if="editForm.type !== 3" class="form-row">
                                    <div class="form-label">
                                        {{ $t('system.menu.routePath') }}<span class="req">*</span>
                                    </div>
                                    <el-input
                                        v-model="editForm.path"
                                        :placeholder="$t('system.menu.routePathPlaceholder')"
                                    />
                                </div>
                                <div v-if="editForm.type === 2" class="form-row">
                                    <div class="form-label">{{ $t('system.menu.component') }}</div>
                                    <el-input
                                        v-model="editForm.component"
                                        :placeholder="$t('system.menu.componentPlaceholder')"
                                    >
                                        <template #prepend>src/views/</template>
                                        <template #append>.vue</template>
                                    </el-input>
                                </div>
                                <div v-if="editForm.type !== 3" class="form-row">
                                    <div class="form-label">{{ $t('system.menu.routeName') }}</div>
                                    <el-input
                                        v-model="editForm.name"
                                        :placeholder="$t('system.menu.routeNamePlaceholder')"
                                    />
                                </div>
                                <div class="form-row">
                                    <div class="form-label">{{ $t('common.sort') }}</div>
                                    <el-input-number
                                        v-model="editForm.sort"
                                        :min="0"
                                        :max="9999"
                                        controls-position="right"
                                        style="width: 100%"
                                    />
                                </div>
                                <div v-if="editForm.type !== 3" class="form-row">
                                    <div class="form-label">{{ $t('system.menu.iframe') }}</div>
                                    <el-input
                                        v-model="editMetaForm.iframe"
                                        :placeholder="$t('system.menu.iframe')"
                                    />
                                </div>
                            </div>

                            <div class="form-sec-title" style="margin-top: 22px">
                                {{ $t('system.menu.displayBehavior') }}
                            </div>
                            <div class="form-grid">
                                <div v-if="editForm.type !== 3" class="form-row">
                                    <div class="form-label">{{ $t('system.menu.icon') }}</div>
                                    <IconSelect v-model="editForm.icon" width="100%" />
                                </div>
                                <div class="form-row">
                                    <div class="form-label">{{ $t('common.status') }}</div>
                                    <div class="meta-hint">
                                        <el-switch
                                            v-model="editForm.status"
                                            :active-value="1"
                                            :inactive-value="0"
                                        />
                                        <span>{{ $t('system.menu.hiddenTip') }}</span>
                                    </div>
                                </div>
                                <div v-if="editForm.type !== 3" class="form-row">
                                    <div class="form-label">{{ $t('system.menu.cache') }}</div>
                                    <div class="meta-hint">
                                        <el-switch v-model="editMetaForm.cache" />
                                        <span>{{ $t('system.menu.cacheTip') }}</span>
                                    </div>
                                </div>
                                <div v-if="editForm.type !== 3" class="form-row">
                                    <div class="form-label">{{ $t('system.menu.affix') }}</div>
                                    <div class="meta-hint">
                                        <el-switch v-model="editMetaForm.affix" />
                                        <span>{{ $t('system.menu.affixTip') }}</span>
                                    </div>
                                </div>
                                <div v-if="editForm.type !== 3" class="form-row">
                                    <div class="form-label">{{ $t('system.menu.hidden') }}</div>
                                    <div class="meta-hint">
                                        <el-switch v-model="editMetaForm.hidden" />
                                        <span>{{ $t('system.menu.hiddenFromNavTip') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-foot">
                                <el-button @click="handleEditCancel">{{
                                    $t('common.cancel')
                                }}</el-button>
                                <el-button @click="handleEditReset">{{
                                    $t('common.reset')
                                }}</el-button>
                                <el-button
                                    type="primary"
                                    :loading="submitting"
                                    @click="handleEditSave"
                                >
                                    {{ $t('system.menu.saveChanges') }}
                                </el-button>
                            </div>
                        </el-form>
                    </div>
                </template>
                <template v-else>
                    <div class="panel-head">
                        <div class="panel-title">{{ $t('system.menu.menuDetail') }}</div>
                    </div>
                    <div class="panel-empty">
                        {{ $t('system.menu.selectMenuTip') }}
                    </div>
                </template>
            </div>
        </div>

        <MenuForm
            v-model="formVisible"
            :form-data="formData"
            :parent-options="parentOptions"
            @success="getMenuList"
        />
    </div>
</template>

<script setup lang="ts" name="PlatformMenuList">
import type { FormInstance, FormRules } from 'element-plus'
import { ElMessage, ElMessageBox } from 'element-plus'
import { computed, nextTick, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { platformMenuApi } from '@/api/system'
import IconSelect from '@/components/IconSelect/index.vue'
import type { MenuInfo, MenuMeta } from '@/types/api'

import MenuForm from './components/MenuForm.vue'
import MenuTreeNode from './components/MenuTreeNode.vue'

const { t } = useI18n()

const menuList = ref<MenuInfo[]>([])
const loading = ref(false)
const isExpandAll = ref(false)

const formVisible = ref(false)
const formData = ref<Record<string, any>>({})
const parentOptions = ref<MenuInfo[]>([])

const treeFilter = ref('')
const selectedMenuId = ref<number | null>(null)
const openMap = ref<Record<number, boolean>>({})

const editFormRef = ref<FormInstance>()
const submitting = ref(false)

interface EditFormData {
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

const defaultEditForm: EditFormData = {
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
}

const editForm = reactive<EditFormData>({ ...defaultEditForm })

const defaultMeta: MenuMeta = {
    hidden: false,
    cache: true,
    affix: false,
    badge: '',
    iframe: ''
}

const editMetaForm = reactive<MenuMeta>({ ...defaultMeta })

const editParentOptions = ref<MenuInfo[]>([])
const editParentTreeData = computed(() => [
    { id: 0, title: t('system.menu.rootMenu'), children: [] },
    ...(editParentOptions.value || [])
])

const editRules = computed<FormRules>(() => ({
    title: [{ required: true, message: t('system.menu.validate.nameRequired'), trigger: 'blur' }],
    type: [{ required: true, message: t('system.menu.validate.typeRequired'), trigger: 'change' }],
    path: [
        {
            trigger: 'blur',
            validator: (_rule: any, value: string, callback: (error?: Error) => void) => {
                if (editForm.type === 3) {
                    callback()
                    return
                }
                if (!value) {
                    callback(new Error(t('system.menu.routePathPlaceholder')))
                    return
                }
                callback()
            }
        }
    ],
    component: [
        {
            trigger: 'blur',
            validator: (_rule: any, value: string, callback: (error?: Error) => void) => {
                if (editForm.type !== 2) {
                    callback()
                    return
                }
                if (!value) {
                    callback(new Error(t('system.menu.componentPlaceholder')))
                    return
                }
                callback()
            }
        }
    ]
}))

const selectedMenu = computed<MenuInfo | null>(() => {
    if (selectedMenuId.value == null) return null
    return findMenuById(menuList.value, selectedMenuId.value)
})

const filteredMenuList = computed(() => {
    const keyword = treeFilter.value.trim().toLowerCase()
    if (!keyword) return menuList.value
    return filterTree(menuList.value, keyword)
})

function filterTree(list: MenuInfo[], keyword: string): MenuInfo[] {
    const result: MenuInfo[] = []
    for (const item of list) {
        const titleMatch = item.title?.toLowerCase().includes(keyword)
        const childMatches = item.children?.length ? filterTree(item.children, keyword) : []
        if (titleMatch || childMatches.length > 0) {
            result.push({
                ...item,
                children: titleMatch ? item.children : childMatches
            })
        }
    }
    return result
}

function findMenuById(list: MenuInfo[], id: number): MenuInfo | null {
    for (const item of list) {
        if (item.id === id) return item
        if (item.children?.length) {
            const found = findMenuById(item.children, id)
            if (found) return found
        }
    }
    return null
}

const getMenuList = async () => {
    loading.value = true
    try {
        const res = await platformMenuApi.list()
        menuList.value = res.data || []

        if (selectedMenuId.value != null) {
            const updated = findMenuById(menuList.value, selectedMenuId.value)
            if (updated) {
                syncEditForm(updated)
            } else {
                selectedMenuId.value = null
            }
        }
    } catch {
        ElMessage.error(t('message.fetchFailed'))
    } finally {
        loading.value = false
    }
}

const getParentOptions = async (excludeId?: number) => {
    try {
        const res = await platformMenuApi.options(excludeId)
        parentOptions.value = res.data || []
    } catch (error) {
        console.error('获取父级菜单选项失败:', error)
    }
}

const getEditParentOptions = async (excludeId?: number) => {
    try {
        const res = await platformMenuApi.options(excludeId)
        editParentOptions.value = res.data || []
    } catch (error) {
        console.error('获取编辑父级菜单选项失败:', error)
    }
}

const expandAllNodes = () => {
    isExpandAll.value = !isExpandAll.value
    const newMap: Record<number, boolean> = {}
    if (isExpandAll.value) {
        const setAll = (list: MenuInfo[]) => {
            for (const item of list) {
                if (item.children?.length) {
                    newMap[item.id] = true
                    setAll(item.children)
                }
            }
        }
        setAll(menuList.value)
    }
    openMap.value = newMap
}

const handleTreeSelect = (node: MenuInfo) => {
    selectedMenuId.value = node.id
    syncEditForm(node)
    getEditParentOptions(node.id)
}

const handleTreeToggle = (nodeId: number) => {
    openMap.value = { ...openMap.value, [nodeId]: !openMap.value[nodeId] }
}

function syncEditForm(menu: MenuInfo) {
    Object.assign(editForm, {
        id: menu.id,
        parent_id: menu.parent_id ?? 0,
        type: menu.type,
        title: menu.title,
        name: menu.name || '',
        path: menu.path || '',
        component: menu.component || '',
        icon: menu.icon || '',
        permission: menu.permission || '',
        sort: menu.sort ?? 100,
        status: menu.status
    })
    if (menu.meta) {
        Object.assign(editMetaForm, defaultMeta, menu.meta)
    } else {
        Object.assign(editMetaForm, defaultMeta)
    }
    nextTick(() => editFormRef.value?.clearValidate())
}

const handleEditTypeChange = () => {
    if (editForm.type === 3) {
        editForm.name = ''
        editForm.path = ''
        editForm.component = ''
        editForm.icon = ''
    }
}

const handleEditCancel = () => {
    if (selectedMenu.value) {
        syncEditForm(selectedMenu.value)
    }
}

const handleEditReset = () => {
    if (selectedMenu.value) {
        syncEditForm(selectedMenu.value)
    }
}

const handleEditSave = async () => {
    if (!editFormRef.value) return
    try {
        await editFormRef.value.validate()
    } catch {
        return
    }

    if (!editForm.id) return

    try {
        submitting.value = true
        const { id, ...payload } = editForm
        await platformMenuApi.update(id, {
            ...payload,
            meta: payload.type !== 3 ? { ...editMetaForm } : undefined
        })
        ElMessage.success(t('message.updateSuccess'))
        await getMenuList()
    } catch {
        ElMessage.error(t('common.error'))
    } finally {
        submitting.value = false
    }
}

const handleAdd = (parent?: MenuInfo) => {
    formData.value = {
        parent_id: parent?.id || 0,
        status: 1,
        sort: 100,
        type: parent ? 2 : 1
    }
    getParentOptions()
    formVisible.value = true
}

const handleDelete = async (row: MenuInfo) => {
    try {
        await ElMessageBox.confirm(
            t('message.deleteConfirmName', { name: row.title }),
            t('message.confirmDelete'),
            {
                confirmButtonText: t('common.confirm'),
                cancelButtonText: t('common.cancel'),
                type: 'warning'
            }
        )

        await platformMenuApi.destroy(row.id)
        ElMessage.success(t('message.deleteSuccess'))

        if (selectedMenuId.value === row.id) {
            selectedMenuId.value = null
        }
        getMenuList()
    } catch (error) {
        if (error !== 'cancel') {
            console.error('删除失败:', error)
        }
    }
}

onMounted(() => {
    getMenuList()
})
</script>

<style lang="scss" scoped>
.menu-container {
    .tree {
        min-height: 200px;
        max-height: calc(100vh - 260px);
        overflow-y: auto;
    }

    .panel-empty {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 400px;
        color: var(--ink-400);
        font-size: 13px;
    }

    .meta-hint {
        display: flex;
        align-items: center;
        gap: 10px;
        padding-top: 6px;

        span {
            font-size: 12.5px;
            color: var(--ink-500);
        }
    }
}
</style>
