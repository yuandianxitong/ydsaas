<template>
  <div class="decor-page">
    <DecorPageHeader title="底部导航" subtitle="设置移动端店铺底部 tabBar 的菜单项、图标与样式（最多 5 项）">
      <template #actions>
        <el-button @click="loadAll">重置</el-button>
        <el-button type="primary" :loading="saving" @click="handleSave">保存</el-button>
      </template>
    </DecorPageHeader>

    <el-row :gutter="14">
      <!-- 左：菜单项列表 -->
      <el-col :span="9">
        <DecorSection title="菜单项">
          <template #extra>
            <span class="tb-count">{{ form.tabbar.length }}/5</span>
          </template>
          <div v-if="form.tabbar.length === 0" class="decor-empty">暂无菜单项，点击下方新增。</div>
          <div ref="listEl" class="tb-list">
            <div
              v-for="(item, idx) in form.tabbar"
              :key="itemKey(item)"
              class="tb-item"
              :data-uid="itemKey(item)"
              :class="{ 'tb-item--active': idx === selIdx }"
              @click="onItemClick(idx)"
            >
              <el-icon class="tb-item__handle" title="拖动排序"><Rank /></el-icon>
              <div class="tb-item__icon">
                <img v-if="item.icon" :src="item.icon" alt="" />
              </div>
              <div class="tb-item__main">
                <div class="tb-item__label">{{ item.text || '未命名' }}</div>
                <div class="tb-item__sub">跳转：{{ item.path || '—' }}</div>
              </div>
            </div>
          </div>
          <div v-if="form.tabbar.length < 5" class="tb-add" @click="addTabbarItem">
            <el-icon><Plus /></el-icon> 新增菜单
          </div>
        </DecorSection>
      </el-col>

      <!-- 右：选中项设置 + 整体样式 -->
      <el-col :span="15">
        <DecorSection :title="sel ? `菜单项设置 · ${sel.text || '未命名'}` : '菜单项设置'">
          <template v-if="sel" #extra>
            <el-button link type="danger" @click="removeTabbarItem(selIdx)">删除</el-button>
          </template>
          <div v-if="!sel" class="decor-empty">从左侧选择或新增一个菜单项进行编辑。</div>
          <el-form v-else label-position="top" class="decor-form">
            <el-form-item label="链接" required>
              <LinkPicker
                :model-value="sel.path || ''"
                @update:model-value="(v) => onTabbarPathChange(selIdx, v)"
              />
              <div class="decor-field-tip">
                选择或输入站内页面路径（内置页 / 已发布 DIY 页 / 已授权插件页）。不支持外链。
              </div>
            </el-form-item>
            <div class="decor-grid-2">
              <el-form-item label="标题">
                <el-input v-model="sel.text" placeholder="底部文字" />
              </el-form-item>
              <el-form-item label="选中标题">
                <el-input v-model="sel.sel_label" placeholder="选中时文字，可空" />
              </el-form-item>
            </div>
            <div class="decor-grid-2">
              <el-form-item label="未选图标">
                <ImageSelect :model-value="sel.icon || ''" @update:model-value="(v: string | string[]) => { if (sel) sel.icon = v as string }" />
              </el-form-item>
              <el-form-item label="选中图标">
                <ImageSelect :model-value="sel.selected_icon || ''" @update:model-value="(v: string | string[]) => { if (sel) sel.selected_icon = v as string }" />
              </el-form-item>
            </div>
          </el-form>
        </DecorSection>

        <DecorSection title="整体样式">
          <div class="decor-hint" style="margin-bottom: 14px">
            文案、图标与颜色保存后用户刷新即可生效；若新增了当前包中不存在的页面作为跳转目标，需重新「打包发布」。
          </div>
          <div class="decor-grid-3">
            <div class="color-field">
              <span class="color-field__label">文字颜色</span>
              <div class="color-field__row">
                <el-color-picker v-model="tabbarStyle.text_color" />
                <span class="color-field__hex mono">{{ tabbarStyle.text_color || '默认' }}</span>
              </div>
            </div>
            <div class="color-field">
              <span class="color-field__label">选中文字颜色</span>
              <div class="color-field__row">
                <el-color-picker v-model="tabbarStyle.active_color" />
                <span class="color-field__hex mono">{{ tabbarStyle.active_color || '默认' }}</span>
              </div>
            </div>
            <div class="color-field">
              <span class="color-field__label">背景颜色</span>
              <div class="color-field__row">
                <el-color-picker v-model="tabbarStyle.bg_color" />
                <span class="color-field__hex mono">{{ tabbarStyle.bg_color || '默认' }}</span>
              </div>
            </div>
          </div>
        </DecorSection>
      </el-col>
    </el-row>
  </div>
</template>

<script setup lang="ts">
import { Plus, Rank } from '@element-plus/icons-vue'
import { ElMessage } from 'element-plus'
import Sortable from 'sortablejs'
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'

import { mobileConfigApi, type EligibilityResponse, type MobileTabbarItem, type TabbarStyle } from '@/api/mobile-config'

import DecorPageHeader from './components/DecorPageHeader.vue'
import DecorSection from './components/DecorSection.vue'
import LinkPicker from './editor/components/LinkPicker.vue'

type TabbarRow = MobileTabbarItem & { _uid: string }

const form = reactive({
  tabbar: [] as TabbarRow[],
})

const tabbarStyle = reactive<TabbarStyle>({})

const eligibility = reactive<EligibilityResponse>({
  homeOptions: [],
  tabBarOptions: [],
})

const saving = ref(false)
const selIdx = ref(0)
const listEl = ref<HTMLElement | null>(null)
const dragging = ref(false)
let sortable: Sortable | null = null
let uidSeq = 0

const sel = computed<TabbarRow | null>(() => form.tabbar[selIdx.value] ?? null)

function nextUid(): string {
  uidSeq += 1
  return `tb-${Date.now()}-${uidSeq}`
}

function withUid(item: MobileTabbarItem): TabbarRow {
  return { ...item, _uid: nextUid() }
}

function itemKey(item: TabbarRow): string {
  return item._uid
}

function destroySortable() {
  sortable?.destroy()
  sortable = null
}

async function initSortable() {
  destroySortable()
  await nextTick()
  if (!listEl.value || form.tabbar.length === 0) return
  sortable = Sortable.create(listEl.value, {
    animation: 150,
    handle: '.tb-item__handle',
    draggable: '.tb-item',
    onStart: () => {
      dragging.value = true
    },
    onEnd: async () => {
      if (!listEl.value) return
      const selectedUid = form.tabbar[selIdx.value]?._uid
      const uids = Array.from(listEl.value.querySelectorAll('.tb-item'))
        .map((el) => (el as HTMLElement).dataset.uid || '')
        .filter(Boolean)
      const map = new Map(form.tabbar.map((t) => [t._uid, t]))
      form.tabbar = uids.map((uid) => map.get(uid)!).filter(Boolean)
      if (selectedUid) {
        const next = form.tabbar.findIndex((t) => t._uid === selectedUid)
        selIdx.value = next >= 0 ? next : 0
      }
      await nextTick()
      dragging.value = false
      initSortable()
    },
  })
}

function onItemClick(idx: number) {
  if (dragging.value) return
  selIdx.value = idx
}

async function loadAll() {
  const [cfg, eligible] = await Promise.all([mobileConfigApi.get(), mobileConfigApi.eligible()])
  form.tabbar = (cfg.data.tabbar || []).map((t) => withUid(t))
  Object.assign(tabbarStyle, cfg.data.tabbar_style || {})
  eligibility.tabBarOptions = eligible.data.tabBarOptions
  selIdx.value = 0
  await initSortable()
}

function addTabbarItem() {
  if (form.tabbar.length >= 5) return
  const first = eligibility.tabBarOptions[0]
  form.tabbar.push(
    withUid({
      code: first?.code || '__home__',
      path: first?.default_home_path || 'pages/index/index',
      text: first?.name || '首页',
      icon: '',
      selected_icon: '',
      sel_label: '',
      badge: '',
    })
  )
  selIdx.value = form.tabbar.length - 1
  initSortable()
}

function removeTabbarItem(idx: number) {
  form.tabbar.splice(idx, 1)
  if (selIdx.value >= form.tabbar.length) selIdx.value = Math.max(0, form.tabbar.length - 1)
  initSortable()
}

function onTabbarPathChange(idx: number, path: string) {
  const item = form.tabbar[idx]
  if (!item) return
  item.path = (path || '').replace(/^\//, '')
  if (!item.text || item.text === '未命名') {
    const hit = eligibility.tabBarOptions.find(
      (o) => o.default_home_path === item.path || o.pages?.some((p) => p.path === item.path)
    )
    if (hit) item.text = hit.name
  }
}

function stripUid(items: TabbarRow[]): MobileTabbarItem[] {
  return items.map(({ _uid, ...rest }) => rest)
}

async function handleSave() {
  saving.value = true
  try {
    const res = await mobileConfigApi.update({
      tabbar: stripUid(form.tabbar),
      tabbar_style: { ...tabbarStyle },
    })
    form.tabbar = (res.data.tabbar || []).map((t) => withUid(t))
    Object.assign(tabbarStyle, res.data.tabbar_style || {})
    selIdx.value = Math.min(selIdx.value, Math.max(0, form.tabbar.length - 1))
    await initSortable()
    ElMessage.success('已保存，用户刷新或重新进入即可看到')
  } finally {
    saving.value = false
  }
}

watch(
  () => form.tabbar.length,
  () => {
    initSortable()
  }
)

onMounted(loadAll)
onBeforeUnmount(destroySortable)
</script>

<style scoped lang="scss">
@import './components/decor.scss';

.tb-count {
  font-size: 11.5px;
  color: var(--color-text-tertiary);
}

.tb-list {
  min-height: 4px;
}

.tb-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px 12px;
  margin-bottom: 8px;
  border: 1px solid var(--color-border);
  border-radius: var(--radius-md);
  cursor: pointer;
  background: var(--color-surface, #fff);
}
.tb-item--active {
  border-color: var(--el-color-primary);
  background: var(--el-color-primary-light-9);
}
.tb-item__handle {
  flex-shrink: 0;
  color: var(--color-text-tertiary);
  cursor: grab;
  font-size: 16px;
}
.tb-item__handle:active {
  cursor: grabbing;
}
.tb-item__icon {
  width: 32px;
  height: 32px;
  flex-shrink: 0;
  border-radius: var(--radius-sm);
  border: 1px solid var(--color-border);
  background: var(--color-surface-sunken);
  overflow: hidden;
  display: flex;
  align-items: center;
  justify-content: center;
}
.tb-item__icon img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}
.tb-item__main {
  flex: 1;
  min-width: 0;
}
.tb-item__label {
  font-size: 13px;
  font-weight: 500;
  color: var(--color-text-primary);
}
.tb-item__sub {
  margin-top: 2px;
  font-size: 11px;
  color: var(--color-text-tertiary);
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}
.tb-add {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 12px;
  border: 1px dashed var(--color-border-strong);
  border-radius: var(--radius-md);
  font-size: 12.5px;
  color: var(--color-text-secondary);
  cursor: pointer;
}
.tb-add--disabled {
  opacity: 0.5;
  pointer-events: none;
}

.decor-field-tip {
  margin-top: 4px;
  font-size: 11.5px;
  color: var(--color-text-tertiary);
  line-height: 1.5;
}
.color-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}
.color-field__label {
  font-size: 11.5px;
  color: var(--color-text-secondary);
}
.color-field__row {
  display: flex;
  align-items: center;
  gap: 8px;
}
.color-field__hex {
  font-size: 12px;
  color: var(--color-text-tertiary);
}
</style>
