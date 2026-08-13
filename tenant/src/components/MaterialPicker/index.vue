<template>
  <el-dialog
    v-model="visible"
    :title="$t('component.materialPicker.title')"
    :close-on-click-modal="false"
    append-to-body
    class="material-picker-dialog dlg-xl"
    @open="handleOpen"
  >
    <div class="material-picker">
      <!-- 左侧分类树 -->
      <div class="picker-sidebar">
        <div class="sidebar-header">{{ $t('component.materialPicker.categoryTitle') }}</div>

        <div class="category-tree" :key="treeVersion">
          <div
            class="category-item"
            :class="{ active: currentCategoryId === undefined }"
            @click="selectCategory(undefined)"
          >
            <span class="expand-icon placeholder"></span>
            <img :src="folderSvgUrl" class="folder-icon" alt="" />
            <span class="category-name">{{ $t('component.materialPicker.all') }}</span>
          </div>

          <div v-for="node in categoryTree" :key="node.id">
            <CategoryNode
              :node="node"
              :current-id="currentCategoryId"
              :folder-icon="folderSvgUrl"
              :labels="nodeLabels"
              @select="selectCategory"
              @create="handleCreateSubCategory"
              @rename="handleRenameCategory"
              @delete="handleDeleteCategory"
            />
          </div>
        </div>

        <div class="sidebar-footer">
          <div class="add-category-btn" @click="handleCreateCategory">
            +
            {{
              currentCategoryId !== undefined
                ? $t('component.materialPicker.createSub')
                : $t('component.materialPicker.create')
            }}
          </div>
        </div>
      </div>

      <!-- 右侧内容区 -->
      <div class="picker-main">
        <!-- 工具栏 -->
        <div class="picker-toolbar">
          <div class="toolbar-left">
            <el-button type="primary" :disabled="selectedFiles.length === 0" @click="handleConfirm">
              {{ $t('component.materialPicker.useSelected') }}
            </el-button>
            <el-upload
              :action="uploadAction"
              :headers="uploadHeaders"
              :data="{ category_id: currentCategoryId || 0 }"
              :show-file-list="false"
              :on-success="handleUploadSuccess"
              :before-upload="beforeUpload"
              multiple
            >
              <el-button type="primary">{{ $t('component.materialPicker.upload') }}</el-button>
            </el-upload>
            <el-button :disabled="selectedFiles.length === 0" @click="handleBatchDelete">
              {{ $t('component.materialPicker.remove') }}
            </el-button>
            <el-select
              v-if="selectedFiles.length > 0"
              :placeholder="$t('component.materialPicker.moveTo')"
              style="width: 120px"
              @change="handleMoveCategory"
            >
              <el-option
                v-for="cat in flatCategories"
                :key="cat.id"
                :label="cat.name"
                :value="cat.id"
              />
            </el-select>
          </div>
          <div class="toolbar-right">
            <el-input
              v-model="keyword"
              :placeholder="$t('component.materialPicker.searchPlaceholder')"
              clearable
              style="width: 180px"
              @clear="handleSearch"
              @keyup.enter="handleSearch"
            >
              <template #prefix>
                <el-icon class="search-icon" @click="handleSearch"><Search /></el-icon>
              </template>
            </el-input>
          </div>
        </div>

        <!-- 面包屑导航 -->
        <div v-if="currentPath.length > 0" class="picker-breadcrumb">
          <div
            class="breadcrumb-back"
            @click="goBack"
            :title="$t('component.materialPicker.backParent')"
          >
            ←
          </div>
          <div class="breadcrumb-path">
            <span class="breadcrumb-item" @click="selectCategory(undefined)">{{
              $t('component.materialPicker.rootCrumb')
            }}</span>
            <template v-for="(crumb, idx) in breadcrumbs" :key="crumb.id">
              <span class="breadcrumb-sep">/</span>
              <span
                class="breadcrumb-item"
                :class="{ current: idx === breadcrumbs.length - 1 }"
                @click="idx < breadcrumbs.length - 1 ? navigateToBreadcrumb(crumb.id) : undefined"
              >{{ crumb.name }}</span>
            </template>
          </div>
        </div>

        <!-- 内容网格: 文件夹 + 图片 -->
        <div class="picker-grid" v-loading="loading">
          <div class="picker-grid__inner">
            <!-- 子文件夹卡片 -->
            <div
              v-for="child in currentChildren"
              :key="'folder-' + child.id"
              class="folder-card"
              @click="enterFolder(child.id)"
            >
              <div class="folder-card__icon">
                <img :src="folderSvgUrl" alt="folder" />
              </div>
              <div class="folder-card__name" :title="child.name">{{ child.name }}</div>
              <div class="folder-card__count">
                {{ $t('component.materialPicker.countUnit', { count: child.file_count || 0 }) }}
              </div>
            </div>

            <!-- 图片卡片 -->
            <div
              v-for="file in fileList"
              :key="file.id"
              class="file-card"
              :class="{ selected: isSelected(file) }"
              @click="toggleSelect(file)"
            >
              <div class="file-thumb">
                <el-image :src="file.url" fit="contain" class="thumb-img" />
                <div v-if="isSelected(file)" class="selected-badge">
                  <el-icon><Check /></el-icon>
                </div>
              </div>
              <div class="file-name" :title="file.name">{{ file.name }}</div>
            </div>

            <el-empty
              v-if="!loading && currentChildren.length === 0 && fileList.length === 0"
              :description="$t('component.materialPicker.empty')"
              :image-size="60"
            />
          </div>
        </div>

        <!-- 分页 -->
        <div class="picker-pagination">
          <el-pagination
            v-model:current-page="pagination.page"
            v-model:page-size="pagination.limit"
            :total="pagination.total"
            :page-sizes="[18, 36, 54]"
            layout="total, prev, pager, next"
            small
            @current-change="fetchFileList"
            @size-change="fetchFileList"
          />
        </div>
      </div>
    </div>
  </el-dialog>
</template>

<script setup lang="ts" name="MaterialPicker">
import { computed, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import { Check, Search } from '@element-plus/icons-vue'
import { useI18n } from 'vue-i18n'

import { fileApi } from '@/api/file'
import { fileCategoryApi } from '@/api/file-category'
import type { FileCategory, FileInfo } from '@/types/api'
import { appConfig } from '@/constants/appSetting'
import useUserStore from '@/store/modules/user.store'
import folderSvgUrl from '@/assets/icons/material-picker/folder.svg?url'
import CategoryNode from './CategoryNode'

const { t } = useI18n()
const userStore = useUserStore()

interface Props {
  modelValue: boolean
  multiple?: boolean
  limit?: number
}

interface Emits {
  (e: 'update:modelValue', value: boolean): void
  (e: 'confirm', urls: string[]): void
}

const props = withDefaults(defineProps<Props>(), {
  multiple: false,
  limit: 0,
})
const emit = defineEmits<Emits>()

const visible = computed({
  get: () => props.modelValue,
  set: (v) => emit('update:modelValue', v),
})

// ==============================
// State
// ==============================

const categoryTree = ref<FileCategory[]>([])
const fileList = ref<FileInfo[]>([])
const selectedFiles = ref<FileInfo[]>([])
const loading = ref(false)
const keyword = ref('')
const pagination = reactive({ page: 1, limit: 18, total: 0 })

// 分类树节点右键菜单文案（defineComponent 内无法直接使用 setup i18n 上下文，经 props 传入）
const nodeLabels = computed(() => ({
  createSub: t('component.materialPicker.createSub'),
  rename: t('component.materialPicker.rename'),
  remove: t('component.materialPicker.remove'),
}))

// ==============================
// Folder Navigation
// ==============================

const currentPath = ref<number[]>([])

const currentCategoryId = computed<number | undefined>(() =>
  currentPath.value.length > 0
    ? currentPath.value[currentPath.value.length - 1]
    : undefined
)

function findNode(nodes: FileCategory[], id: number): FileCategory | null {
  for (const node of nodes) {
    if (node.id === id) return node
    if (node.children?.length) {
      const found = findNode(node.children, id)
      if (found) return found
    }
  }
  return null
}

function buildPathTo(id: number): number[] {
  const path: number[] = []
  function walk(nodes: FileCategory[], target: number): boolean {
    for (const node of nodes) {
      if (node.id === target) {
        path.push(node.id)
        return true
      }
      if (node.children?.length && walk(node.children, target)) {
        path.unshift(node.id)
        return true
      }
    }
    return false
  }
  walk(categoryTree.value, id)
  return path
}

const breadcrumbs = computed(() => {
  return currentPath.value.map((id) => {
    const node = findNode(categoryTree.value, id)
    return { id, name: node?.name || t('component.materialPicker.unknown') }
  })
})

const currentChildren = computed(() => {
  if (currentPath.value.length === 0) {
    return categoryTree.value
  }
  const node = findNode(categoryTree.value, currentCategoryId.value!)
  return node?.children || []
})

const uploadAction = `${appConfig.baseUrl}/${appConfig.urlPrefix}/upload/image`
const uploadHeaders = computed(() => ({
  Authorization: `Bearer ${userStore.token}`,
}))

// Flat category list for move dropdown
const flatCategories = computed(() => {
  const result: { id: number; name: string }[] = []
  const flatten = (nodes: FileCategory[], prefix = '') => {
    for (const n of nodes) {
      result.push({ id: n.id, name: prefix + n.name })
      if (n.children?.length) flatten(n.children, prefix + n.name + ' / ')
    }
  }
  flatten(categoryTree.value)
  return result
})

// ==============================
// Data Loading
// ==============================

const treeVersion = ref(0)

const fetchCategoryTree = async () => {
  try {
    const res = await fileCategoryApi.getTree()
    categoryTree.value = res.data || []
    // 强制重建分类树组件，确保新增/删除的子分类正确渲染
    treeVersion.value++
  } catch (e) {
    console.error('加载分类树失败:', e)
  }
}

const fetchFileList = async () => {
  try {
    loading.value = true
    const params: Record<string, any> = {
      page: pagination.page,
      limit: pagination.limit,
      mime_type: 'image',
    }
    if (currentCategoryId.value !== undefined) {
      params.category_id = currentCategoryId.value
    }
    if (keyword.value.trim()) {
      params.keyword = keyword.value.trim()
    }
    const res = await fileApi.getList(params)
    fileList.value = res.data?.list || []
    pagination.total = res.data?.pagination?.total || 0
  } catch (e) {
    console.error('加载图片列表失败:', e)
  } finally {
    loading.value = false
  }
}

const handleOpen = () => {
  selectedFiles.value = []
  keyword.value = ''
  pagination.page = 1
  currentPath.value = []
  fetchCategoryTree()
  fetchFileList()
}

// ==============================
// Category Operations
// ==============================

const selectCategory = (id: number | undefined) => {
  if (id === undefined) {
    currentPath.value = []
  } else {
    currentPath.value = buildPathTo(id)
  }
  pagination.page = 1
  selectedFiles.value = []
  fetchFileList()
}

const enterFolder = (folderId: number) => {
  currentPath.value = [...currentPath.value, folderId]
  pagination.page = 1
  selectedFiles.value = []
  fetchFileList()
}

const navigateToBreadcrumb = (id: number) => {
  const idx = currentPath.value.indexOf(id)
  if (idx >= 0) {
    currentPath.value = currentPath.value.slice(0, idx + 1)
    pagination.page = 1
    selectedFiles.value = []
    fetchFileList()
  }
}

const goBack = () => {
  if (currentPath.value.length > 0) {
    currentPath.value = currentPath.value.slice(0, -1)
    pagination.page = 1
    selectedFiles.value = []
    fetchFileList()
  }
}

const handleCreateCategory = async () => {
  const parentId = currentCategoryId.value || 0
  const title = parentId
    ? t('component.materialPicker.createSub')
    : t('component.materialPicker.create')
  try {
    const { value } = await ElMessageBox.prompt(
      t('component.materialPicker.namePlaceholder'),
      title,
      {
        confirmButtonText: t('common.confirm'),
        cancelButtonText: t('common.cancel'),
        inputPattern: /\S+/,
        inputErrorMessage: t('component.materialPicker.nameRequired'),
      }
    )
    await fileCategoryApi.create({ name: value, parent_id: parentId })
    ElMessage.success(t('component.materialPicker.created'))
    fetchCategoryTree()
  } catch {}
}

const handleCreateSubCategory = async (parentId: number) => {
  try {
    const { value } = await ElMessageBox.prompt(
      t('component.materialPicker.subNamePlaceholder'),
      t('component.materialPicker.createSub'),
      {
        confirmButtonText: t('common.confirm'),
        cancelButtonText: t('common.cancel'),
        inputPattern: /\S+/,
        inputErrorMessage: t('component.materialPicker.nameRequired'),
      }
    )
    await fileCategoryApi.create({ name: value, parent_id: parentId })
    ElMessage.success(t('component.materialPicker.created'))
    fetchCategoryTree()
  } catch {}
}

const handleRenameCategory = async (id: number, oldName: string) => {
  try {
    const { value } = await ElMessageBox.prompt(
      t('component.materialPicker.renamePlaceholder'),
      t('component.materialPicker.rename'),
      {
        confirmButtonText: t('common.confirm'),
        cancelButtonText: t('common.cancel'),
        inputValue: oldName,
        inputPattern: /\S+/,
        inputErrorMessage: t('component.materialPicker.nameRequired'),
      }
    )
    await fileCategoryApi.update(id, { name: value })
    ElMessage.success(t('component.materialPicker.renamed'))
    fetchCategoryTree()
  } catch {}
}

const handleDeleteCategory = async (id: number) => {
  try {
    await ElMessageBox.confirm(
      t('component.materialPicker.deleteCategoryConfirm'),
      t('component.materialPicker.deleteConfirmTitle'),
      {
        confirmButtonText: t('common.confirm'),
        cancelButtonText: t('common.cancel'),
        type: 'warning',
      }
    )
    await fileCategoryApi.delete(id)
    ElMessage.success(t('component.materialPicker.deleted'))
    if (currentPath.value.includes(id)) {
      currentPath.value = []
    }
    fetchCategoryTree()
    fetchFileList()
  } catch {}
}

// ==============================
// File Selection
// ==============================

const isSelected = (file: FileInfo) => selectedFiles.value.some((f) => f.id === file.id)

const toggleSelect = (file: FileInfo) => {
  const idx = selectedFiles.value.findIndex((f) => f.id === file.id)
  if (idx >= 0) {
    selectedFiles.value.splice(idx, 1)
  } else {
    if (!props.multiple) {
      selectedFiles.value = [file]
    } else {
      if (props.limit > 0 && selectedFiles.value.length >= props.limit) {
        ElMessage.warning(t('component.materialPicker.overLimit', { limit: props.limit }))
        return
      }
      selectedFiles.value.push(file)
    }
  }
}

const handleConfirm = () => {
  const urls = selectedFiles.value.map((f) => f.url)
  emit('confirm', urls)
  visible.value = false
}

// ==============================
// File Operations
// ==============================

const handleSearch = () => {
  pagination.page = 1
  fetchFileList()
}

const beforeUpload = (file: File) => {
  const isImage = file.type.startsWith('image/')
  if (!isImage) {
    ElMessage.error(t('component.materialPicker.imageOnly'))
    return false
  }
  if (file.size > 10 * 1024 * 1024) {
    ElMessage.error(t('component.materialPicker.maxSize', { size: 10 }))
    return false
  }
  return true
}

const handleUploadSuccess = (response: any) => {
  if (response.code === 200 || response.code === 0) {
    ElMessage.success(t('component.materialPicker.uploaded'))
    fetchFileList()
    fetchCategoryTree()
  } else {
    ElMessage.error(response.message || t('component.materialPicker.uploadFailed'))
  }
}

const handleBatchDelete = async () => {
  if (selectedFiles.value.length === 0) return
  try {
    await ElMessageBox.confirm(
      t('component.materialPicker.deleteFilesConfirm', { n: selectedFiles.value.length }),
      t('component.materialPicker.deleteConfirmTitle'),
      {
        confirmButtonText: t('common.confirm'),
        cancelButtonText: t('common.cancel'),
        type: 'warning',
      }
    )
    const ids = selectedFiles.value.map((f) => f.id)
    await fileApi.batchDelete(ids)
    ElMessage.success(t('component.materialPicker.deleted'))
    selectedFiles.value = []
    fetchFileList()
    fetchCategoryTree()
  } catch {}
}

const handleMoveCategory = async (categoryId: number) => {
  if (selectedFiles.value.length === 0) return
  try {
    const ids = selectedFiles.value.map((f) => f.id)
    await fileApi.moveCategory(ids, categoryId)
    ElMessage.success(t('component.materialPicker.moved'))
    selectedFiles.value = []
    fetchFileList()
    fetchCategoryTree()
  } catch {
    ElMessage.error(t('component.materialPicker.moveFailed'))
  }
}
</script>

<style lang="scss">
// 接入全局弹窗契约的 flex 链：常规视口 body 固定 600px，矮视口随 86vh 封顶收缩
// （原固定 height:600px 会在 <~755px 视口触发 body 外层滚动包裹双栏内滚的嵌套双滚动条）。
// html 前缀：压过全局 html .el-dialog:not(.is-fullscreen) .el-dialog__body 的 flex:1/overflow 声明；
// overflow:hidden 让滚动只发生在双栏内部，body 自身不出滚动条。
html .material-picker-dialog.el-dialog .el-dialog__body {
  padding: 0;
  flex: 0 1 600px;
  overflow: hidden;
}
</style>

<style lang="scss" scoped>

.material-picker {
  display: flex;
  height: 100%;
}

// ============ Sidebar ============

.picker-sidebar {
  width: 210px;
  border-right: 1px solid var(--color-divider);
  display: flex;
  flex-direction: column;
  background: var(--color-surface-sunken);

  .sidebar-header {
    padding: 12px 14px;
    font-weight: 600;
    font-size: 13px;
    color: var(--color-text-primary);
    border-bottom: 1px solid var(--color-divider);
    flex-shrink: 0;
  }

  .category-tree {
    flex: 1;
    overflow-y: auto;
    padding: 4px 8px;
  }

  .sidebar-footer {
    padding: 8px 10px;
    border-top: 1px solid var(--color-divider);
    flex-shrink: 0;

    .add-category-btn {
      padding: 6px 10px;
      border: 1px dashed var(--color-border-strong);
      border-radius: 6px;
      text-align: center;
      font-size: 12px;
      color: var(--color-text-secondary);
      cursor: pointer;
      transition: all 0.15s;

      &:hover {
        border-color: var(--el-color-primary);
        color: var(--el-color-primary);
      }
    }
  }

  :deep(.folder-icon) {
    width: 15px;
    height: 15px;
    flex-shrink: 0;
    display: block;
  }

  :deep(.category-item) {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    cursor: pointer;
    font-size: 13px;
    color: var(--color-text-secondary);
    transition: background 0.15s, color 0.15s;
    white-space: nowrap;
    overflow: hidden;
    border-radius: 8px;
    margin: 3px 0;

    &:hover {
      background: var(--color-bg-page);
    }

    &.active {
      background: var(--el-color-primary);
      color: #fff;
      font-weight: 500;

      .folder-icon {
        filter: brightness(0) invert(1);
      }
      .category-count {
        color: rgba(255, 255, 255, 0.8);
      }
    }

    .category-name {
      flex: 1;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .category-count {
      font-size: 11px;
      color: var(--color-text-disabled);
      flex-shrink: 0;
    }

    .more-icon {
      opacity: 0;
      transition: opacity 0.15s;
      cursor: pointer;
      font-size: 14px;
      flex-shrink: 0;
    }

    &:hover .more-icon {
      opacity: 1;
    }

    .expand-icon {
      font-size: 10px;
      transition: transform 0.2s;
      width: 12px;
      text-align: center;
      flex-shrink: 0;
      line-height: 1;
      color: var(--color-text-disabled);

      &.expanded {
        transform: rotate(90deg);
      }

      &.placeholder {
        visibility: hidden;
      }
    }
  }
}

// ============ Right Panel ============

.picker-main {
  flex: 1;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.picker-toolbar {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 16px;
  border-bottom: 1px solid var(--color-divider);
  gap: 8px;
  flex-shrink: 0;

  .toolbar-left {
    display: flex;
    gap: 8px;
    align-items: center;
  }

  .toolbar-right .search-icon {
    cursor: pointer;
  }
}

.picker-breadcrumb {
  display: flex;
  align-items: center;
  padding: 8px 16px;
  background: var(--color-surface-sunken);
  border-bottom: 1px solid var(--color-divider);
  gap: 8px;
  flex-shrink: 0;

  .breadcrumb-back {
    width: 26px;
    height: 26px;
    border-radius: 6px;
    background: var(--color-bg-page);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 13px;
    color: var(--color-text-secondary);
    flex-shrink: 0;
    transition: background 0.15s;

    &:hover {
      background: var(--color-border);
    }
  }

  .breadcrumb-path {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: var(--color-text-secondary);
    overflow: hidden;
  }

  .breadcrumb-item {
    cursor: pointer;
    color: var(--el-color-primary);
    white-space: nowrap;

    &:hover {
      text-decoration: underline;
    }

    &.current {
      font-weight: 500;
      color: var(--color-text-primary);
      cursor: default;

      &:hover {
        text-decoration: none;
      }
    }
  }

  .breadcrumb-sep {
    color: var(--color-text-disabled);
    flex-shrink: 0;
  }
}

.picker-grid {
  flex: 1;
  overflow-y: auto;
  min-height: 0;

  &__inner {
    display: grid;
    grid-template-columns: repeat(6, 1fr);
    gap: 12px;
    padding: 16px;
    align-content: start;
  }

  // Folder card
  .folder-card {
    cursor: pointer;
    text-align: center;

    &__icon {
      aspect-ratio: 1;
      background: var(--color-surface-sunken);
      border-radius: 8px;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      border: 2px solid transparent;
      transition: all 0.15s;

      img {
        width: 40px;
        height: 40px;
      }
    }

    &:hover .folder-card__icon {
      background: var(--el-color-primary-light-9);
      border-color: var(--el-color-primary-light-8);
    }

    &__name {
      padding: 3px 2px 0;
      font-size: 11px;
      color: var(--color-text-secondary);
      font-weight: 500;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    &__count {
      font-size: 10px;
      color: var(--color-text-disabled);
    }
  }

  // Image card
  .file-card {
    cursor: pointer;
    border: 2px solid transparent;
    border-radius: 8px;
    overflow: hidden;
    transition: border-color 0.15s;

    &:hover {
      border-color: var(--el-border-color);
    }

    &.selected {
      border-color: var(--el-color-primary);
    }

    .file-thumb {
      position: relative;
      width: 100%;
      aspect-ratio: 1;
      background: var(--el-fill-color-lighter);
      display: flex;
      align-items: center;
      justify-content: center;

      .thumb-img {
        width: 100%;
        height: 100%;
      }

      .selected-badge {
        position: absolute;
        top: 4px;
        right: 4px;
        width: 22px;
        height: 22px;
        background: var(--el-color-primary);
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
      }
    }

    .file-name {
      padding: 3px 6px;
      font-size: 11px;
      color: var(--el-text-color-regular);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      text-align: center;
    }
  }

  &__inner .el-empty {
    grid-column: 1 / -1;
  }
}

.picker-pagination {
  padding: 8px 16px;
  border-top: 1px solid var(--color-divider);
  display: flex;
  justify-content: flex-end;
  flex-shrink: 0;
}
</style>
