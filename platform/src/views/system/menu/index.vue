<template>
    <div class="menu-container">
        <!-- 搜索区域 -->
        <el-card class="search-card" shadow="never">
            <el-form :model="searchForm" inline class="search-form">
                <el-form-item :label="$t('system.menu.menuName')">
                    <el-input
                        v-model="searchForm.title"
                        :placeholder="$t('system.menu.searchPlaceholder')"
                        clearable
                        style="width: 200px"
                    />
                </el-form-item>
                <el-form-item :label="$t('common.status')">
                    <el-select
                        v-model="searchForm.status"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('common.enable')" :value="1" />
                        <el-option :label="$t('common.disable')" :value="0" />
                    </el-select>
                </el-form-item>
                <el-form-item :label="$t('common.type')">
                    <el-select
                        v-model="searchForm.type"
                        :placeholder="$t('common.all')"
                        clearable
                        style="width: 120px"
                    >
                        <el-option :label="$t('system.menu.directory')" :value="1" />
                        <el-option :label="$t('system.menu.menu')" :value="2" />
                        <el-option :label="$t('system.menu.button')" :value="3" />
                    </el-select>
                </el-form-item>
                <el-form-item>
                    <el-button type="primary" @click="getMenuList">
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
                <div class="table-title">{{ $t('system.menu.title') }}</div>
                <div class="table-actions">
                    <el-button @click="expandAll">
                        {{ isExpandAll ? $t('common.collapseAll') : $t('common.expandAll') }}
                    </el-button>
                    <el-button type="primary" @click="handleAdd()">
                        <el-icon><Plus /></el-icon>
                        {{ $t('system.menu.addMenu') }}
                    </el-button>
                </div>
            </div>

            <!-- 树形表格 -->
            <el-table
                :key="tableKey"
                v-loading="loading"
                :data="menuList"
                row-key="id"
                :tree-props="{ children: 'children', hasChildren: 'hasChildren' }"
                :default-expand-all="isExpandAll"
                stripe
            >
                <el-table-column :label="$t('system.menu.menuName')" prop="title" min-width="200">
                    <template #default="{ row }">
                        <span>{{ row.title }}</span>
                        <span v-if="row.permission" class="permission-text">{{
                            row.permission
                        }}</span>
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.type')" prop="type" width="90">
                    <template #default="{ row }">
                        <el-tag :type="getTypeTagType(row.type)" size="small">
                            {{ getTypeText(row.type) }}
                        </el-tag>
                    </template>
                </el-table-column>

                <el-table-column
                    :label="$t('system.menu.routePath')"
                    prop="path"
                    width="200"
                    show-overflow-tooltip
                />

                <el-table-column
                    :label="$t('system.menu.component')"
                    prop="component"
                    width="200"
                    show-overflow-tooltip
                />

                <el-table-column :label="$t('common.sort')" prop="sort" width="80" align="center" />

                <el-table-column :label="$t('common.status')" width="100" align="center">
                    <template #default="{ row }">
                        <el-switch
                            v-model="row.status"
                            :active-value="1"
                            :inactive-value="0"
                            @change="handleStatusChange(row)"
                        />
                    </template>
                </el-table-column>

                <el-table-column :label="$t('common.createdAt')" prop="created_at" width="180" />

                <el-table-column :label="$t('common.operation')" width="200" fixed="right">
                    <template #default="{ row }">
                        <el-button type="primary" size="small" text @click="handleAdd(row)">
                            {{ $t('common.add') }}
                        </el-button>
                        <el-button type="primary" size="small" text @click="handleEdit(row)">
                            {{ $t('common.edit') }}
                        </el-button>
                        <el-popconfirm
                            :title="$t('system.menu.deleteConfirm')"
                            :confirm-button-text="$t('common.confirm')"
                            :cancel-button-text="$t('common.cancel')"
                            @confirm="handleDelete(row)"
                        >
                            <template #reference>
                                <el-button type="danger" size="small" text>{{
                                    $t('common.delete')
                                }}</el-button>
                            </template>
                        </el-popconfirm>
                    </template>
                </el-table-column>
            </el-table>
        </el-card>

        <!-- 新增/编辑弹窗 -->
        <MenuForm
            v-model="formVisible"
            :form-data="formData"
            :parent-options="parentOptions"
            @success="getMenuList"
        />
    </div>
</template>

<script setup lang="ts" name="PlatformMenuList">
import { Plus, Refresh, Search } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import { nextTick, onMounted, reactive, ref } from 'vue'
import { useI18n } from 'vue-i18n'

import { platformMenuApi } from '@/api/system'

import MenuForm from './components/MenuForm.vue'

const { t } = useI18n()

// 搜索表单
const searchForm = reactive({
    title: '',
    status: undefined as number | undefined,
    type: undefined as number | undefined
})

// 列表数据
const menuList = ref<any[]>([])
const loading = ref(false)

// 展开/折叠
const isExpandAll = ref(false)
const tableKey = ref(0)

// 弹窗相关
const formVisible = ref(false)
const formData = ref<Record<string, any>>({})
const parentOptions = ref<Array<{ id: number; title: string }>>([])

// 获取菜单列表
const getMenuList = async () => {
    loading.value = true
    try {
        const res = await platformMenuApi.list()
        menuList.value = res.data || []
    } catch (error) {
        ElMessage.error(t('message.fetchFailed'))
    } finally {
        loading.value = false
    }
}

// 获取父级菜单选项
const getParentOptions = async (excludeId?: number) => {
    try {
        const res = await platformMenuApi.options(excludeId)
        parentOptions.value = res.data || []
    } catch (error) {
        console.error('获取父级菜单选项失败:', error)
    }
}

// 重置搜索
const resetSearch = () => {
    Object.assign(searchForm, { title: '', status: undefined, type: undefined })
    getMenuList()
}

// 展开/折叠所有
const expandAll = () => {
    isExpandAll.value = !isExpandAll.value
    tableKey.value++
}

// 状态变更
const handleStatusChange = async (row: any) => {
    try {
        await platformMenuApi.updateStatus(row.id, row.status)
        ElMessage.success(t('message.statusUpdateSuccess'))
    } catch (error) {
        row.status = row.status === 1 ? 0 : 1
    }
}

// 新增菜单
const handleAdd = (parent?: any) => {
    formData.value = {
        parent_id: parent?.id || 0,
        status: 1,
        sort: 100,
        type: parent ? 2 : 1
    }
    getParentOptions()
    formVisible.value = true
}

// 编辑菜单
const handleEdit = (row: any) => {
    formData.value = { ...row }
    getParentOptions(row.id)
    formVisible.value = true
}

// 删除菜单
const handleDelete = async (row: any) => {
    try {
        await platformMenuApi.destroy(row.id)
        ElMessage.success(t('message.deleteSuccess'))
        getMenuList()
    } catch (error) {
        console.error('删除失败:', error)
    }
}

// 获取类型文本
const getTypeText = (type: number) => {
    const typeMap: Record<number, string> = {
        1: t('system.menu.directory'),
        2: t('system.menu.menu'),
        3: t('system.menu.button')
    }
    return typeMap[type] || String(type)
}

// 获取类型标签样式
const getTypeTagType = (type: number): 'primary' | 'success' | 'warning' | 'info' | 'danger' => {
    const typeMap: Record<number, 'primary' | 'success' | 'warning' | 'info' | 'danger'> = {
        1: 'info',
        2: 'success',
        3: 'warning'
    }
    return typeMap[type] || 'info'
}

onMounted(() => {
    getMenuList()
})
</script>

<style lang="scss" scoped>
.menu-container {
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

        .table-actions {
            display: flex;
            gap: 8px;
        }
    }

    .permission-text {
        margin-left: 8px;
        font-size: 12px;
        color: var(--el-text-color-placeholder);
    }
}
</style>
