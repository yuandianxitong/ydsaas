<template>
  <view class="custom-tabbar" :style="{ background: bgColor }">
    <view
      v-for="item in items"
      :key="item.path"
      class="ctb-item"
      @tap="switchTo(item.path)"
    >
      <view class="ctb-icon-wrap">
        <image v-if="iconOf(item)" class="ctb-icon" :src="iconOf(item)" mode="aspectFit" />
        <text v-if="item.badge" class="ctb-badge">{{ item.badge }}</text>
      </view>
      <text class="ctb-text" :style="{ color: isActive(item.path) ? activeColor : textColor }">{{ labelOf(item) }}</text>
    </view>
  </view>
</template>

<script setup lang="ts">
import { computed } from 'vue'

import { useAppStore } from '@/store/app.store'
import { useMobileConfigStore } from '@/store/mobile-config.store'

interface TabItem {
  code?: string
  path: string
  text: string
  icon?: string
  selected_icon?: string
  sel_label?: string
  badge?: string
}

const props = defineProps<{ current: string }>()
const store = useMobileConfigStore()
const appStore = useAppStore()

// 默认底部导航（无配置时）：首页 + 我的（base 壳页面，switchTab 可达）
const DEFAULT_ITEMS: TabItem[] = [
  { path: 'pages/index/index', text: '首页', icon: '/static/tabbar/home.png', selected_icon: '/static/tabbar/home-active.png' },
  { path: 'pages/my/index', text: '我的', icon: '/static/tabbar/my.png', selected_icon: '/static/tabbar/my-active.png' },
]

// base 壳页面默认图标（配置项未给图标时按 path 回退）
const BASE_ICONS: Record<string, { icon: string; active: string }> = {
  'pages/index/index': { icon: '/static/tabbar/home.png', active: '/static/tabbar/home-active.png' },
  'pages/discover/index': { icon: '/static/tabbar/discover.png', active: '/static/tabbar/discover-active.png' },
  'pages/message/index': { icon: '/static/tabbar/message.png', active: '/static/tabbar/message-active.png' },
  'pages/my/index': { icon: '/static/tabbar/my.png', active: '/static/tabbar/my-active.png' },
}

const items = computed<TabItem[]>(() => {
  const t = store.tabbar as TabItem[]
  return Array.isArray(t) && t.length ? t : DEFAULT_ITEMS
})
const activeColor = computed(() => store.tabbarStyle.active_color || store.themeColor)
const textColor = computed(() => store.tabbarStyle.text_color || '#999999')
const bgColor = computed(() => store.tabbarStyle.bg_color || '#ffffff')

function normalize(p: string): string {
  return (p || '').replace(/^\//, '')
}
function isActive(path: string): boolean {
  return normalize(path) === normalize(props.current)
}
function labelOf(item: TabItem): string {
  return isActive(item.path) && item.sel_label ? item.sel_label : item.text
}

function resolveIcon(raw: string): string {
  if (!raw) return ''
  if (raw.startsWith('static')) return '/' + raw
  if (raw.startsWith('/static') || raw.startsWith('http') || raw.startsWith('data:')) return raw
  return appStore.getImageUrl(raw)
}

function iconOf(item: TabItem): string {
  const base = BASE_ICONS[normalize(item.path)]
  if (isActive(item.path)) {
    return resolveIcon(item.selected_icon || '') || (base?.active ?? '') || resolveIcon(item.icon || '')
  }
  return resolveIcon(item.icon || '') || (base?.icon ?? '')
}

function switchTo(path: string): void {
  if (isActive(path)) return
  const raw = normalize(path)
  const url = '/' + raw
  // 带 query（如 DIY 自定义页）不能 switchTab，直接 navigateTo
  if (raw.includes('?')) {
    uni.navigateTo({ url, fail: () => {} })
    return
  }
  uni.switchTab({ url, fail: () => uni.navigateTo({ url, fail: () => {} }) })
}
</script>

<style lang="scss" scoped>
.custom-tabbar {
  position: fixed;
  left: 0;
  right: 0;
  bottom: 0;
  z-index: 999;
  display: flex;
  height: 50px;
  background: #ffffff;
  border-top: 1px solid #ececec;
  padding-bottom: constant(safe-area-inset-bottom);
  padding-bottom: env(safe-area-inset-bottom);
}

.ctb-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.ctb-icon-wrap {
  position: relative;
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  line-height: 0;
}

.ctb-icon {
  width: 22px;
  height: 22px;
  display: block;
}

.ctb-badge {
  position: absolute;
  top: -4px;
  right: -10px;
  min-width: 14px;
  height: 14px;
  padding: 0 4px;
  border-radius: 7px;
  background: #fa3534;
  color: #ffffff;
  font-size: 9px;
  line-height: 14px;
  text-align: center;
}

.ctb-text {
  margin-top: 2px;
  font-size: 11px;
}
</style>
