<template>
  <div class="diy-sb" :class="`diy-sb--${styleName}`">
    <div class="diy-sb__blur" :style="blurFallback">
      <img v-if="blurSrc" :src="blurSrc" class="diy-sb__blur-img" alt="" />
      <div class="diy-sb__blur-fade" />
    </div>
    <div class="diy-sb__head" :class="{ 'diy-sb__head--no-tabs': !showTabs || !tabs.length }">
      <div class="diy-sb__search">
        <img v-if="brandMode === 'logo' && logo" :src="logo" class="diy-sb__logo" alt="" />
        <span v-else-if="brandMode === 'text' && brandText" class="diy-sb__brand">{{ brandText }}</span>
        <component
          :is="safeLink(searchHref) ? 'a' : 'div'"
          class="diy-sb__box"
          v-bind="safeLink(searchHref) ? { href: safeLink(searchHref), rel: 'noopener noreferrer' } : {}"
        >
          <span class="diy-sb__ph">{{ hotwordPreview || placeholder }}</span>
        </component>
      </div>
      <div v-if="showTabs && tabs.length" class="diy-sb__tabs">
        <component
          :is="safeLink(tab.link) ? 'a' : 'span'"
          v-for="(tab, i) in tabs"
          :key="i"
          class="diy-sb__tab"
          v-bind="safeLink(tab.link) ? { href: safeLink(tab.link), rel: 'noopener noreferrer' } : {}"
        >{{ tab.text }}</component>
      </div>
    </div>
    <div class="diy-sb__stage">
      <div v-if="styleName === 'peek'" class="diy-sb__peek-side">
        <img v-if="sideImg.left" :src="sideImg.left" alt="" />
      </div>
      <div class="diy-sb__banner" :style="{ height: bannerH + 'px' }">
        <template v-if="firstImg">
          <a
            v-if="safeLink(firstLink)"
            :href="safeLink(firstLink)"
            rel="noopener noreferrer"
            class="diy-sb__slide"
          >
            <img :src="firstImg" class="diy-sb__img" alt="" />
          </a>
          <div v-else class="diy-sb__slide">
            <img :src="firstImg" class="diy-sb__img" alt="" />
          </div>
        </template>
        <div v-else class="diy-sb__empty">轮播搜索</div>
      </div>
      <div v-if="styleName === 'peek'" class="diy-sb__peek-side">
        <img v-if="sideImg.right" :src="sideImg.right" alt="" />
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'

import { safeLink } from './safeLink'

function normalizeStyle(raw?: string): 'card' | 'peek' {
  const v = String(raw || 'card')
  if (v === 'peek' || v === 'stacked') return 'peek'
  return 'card'
}

const props = defineProps<{ props: Record<string, any>; isFirst?: boolean }>()
const styleName = computed(() => normalizeStyle(props.props?.style))
const brandMode = computed(() => String(props.props?.brand_mode || 'logo'))
const brandText = computed(() => String(props.props?.brand_text || '').trim())
const logo = computed(() => String(props.props?.logo || ''))
const placeholder = computed(() => String(props.props?.placeholder || '请输入搜索词'))
const searchLink = computed(() => String(props.props?.search_link || ''))
const showTabs = computed(() => props.props?.show_tabs !== false)
const tabs = computed(() =>
  (Array.isArray(props.props?.tabs) ? props.props.tabs : []).filter((t: any) => String(t?.text || '').trim())
)
const hotwords = computed(() =>
  (Array.isArray(props.props?.hotwords) ? props.props.hotwords : []).filter((h: any) => String(h?.text || '').trim())
)
const hotwordPreview = computed(() => String(hotwords.value[0]?.text || ''))
const searchHref = computed(() => String(hotwords.value[0]?.link || searchLink.value || ''))
const theme = computed(() => String(props.props?.theme_color || '#ff6034'))
const bannerH = computed(() => Math.max(80, (Number(props.props?.height) || 360) / 2))
const items = computed(() => (Array.isArray(props.props?.items) ? props.props.items : []))
const firstImg = computed(() => String(items.value[0]?.image || ''))
const firstLink = computed(() => String(items.value[0]?.link || ''))
const blurSrc = computed(() => firstImg.value)
const useBlur = computed(() => props.props?.blur_bg !== false)
const blurFallback = computed(() =>
  blurSrc.value && useBlur.value
    ? {}
    : { background: `linear-gradient(180deg, ${theme.value} 0%, ${theme.value}aa 42%, transparent 100%)` }
)
const sideImg = computed(() => {
  const n = items.value.length
  if (n < 2) return { left: firstImg.value, right: firstImg.value }
  return {
    left: String(items.value[n - 1]?.image || firstImg.value),
    right: String(items.value[1]?.image || firstImg.value),
  }
})
</script>
<style scoped>
.diy-sb { position: relative; width: 100%; overflow: hidden; padding-bottom: 8px; }
.diy-sb__blur {
  position: absolute; top: 0; left: 0; right: 0; height: 92%;
  z-index: 0; overflow: hidden; pointer-events: none;
  -webkit-mask-image: linear-gradient(180deg, #000 0%, #000 38%, rgba(0,0,0,.35) 68%, transparent 100%);
  mask-image: linear-gradient(180deg, #000 0%, #000 38%, rgba(0,0,0,.35) 68%, transparent 100%);
}
.diy-sb__blur-img {
  width: 100%; height: 100%; object-fit: cover;
  filter: blur(28px); transform: scale(1.25); opacity: 0.95;
}
.diy-sb__blur-fade {
  position: absolute; left: 0; right: 0; bottom: 0; height: 55%;
  background: linear-gradient(180deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.55) 45%, #fff 100%);
}
.diy-sb__head { position: relative; z-index: 2; }
.diy-sb__head--no-tabs .diy-sb__search { padding-bottom: 10px; }
.diy-sb__search {
  display: flex; align-items: center; gap: 8px; padding: 10px 12px 6px;
}
.diy-sb__logo { height: 22px; width: auto; max-width: 72px; object-fit: contain; }
.diy-sb__brand {
  color: #fff; font-size: 14px; font-weight: 700; flex-shrink: 0;
  max-width: 72px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  text-shadow: 0 1px 2px rgba(0,0,0,.25);
}
.diy-sb__box {
  flex: 1; min-width: 0; height: 32px; border-radius: 16px;
  background: rgba(255, 255, 255, 0.94);
  display: flex; align-items: center; padding: 0 12px;
  text-decoration: none; color: inherit;
}
.diy-sb__ph { font-size: 12px; color: #999; }
.diy-sb__tabs { display: flex; gap: 6px; padding: 0 12px 8px; flex-wrap: wrap; }
.diy-sb__tab {
  font-size: 11px; color: #fff; text-decoration: none;
  text-shadow: 0 1px 2px rgba(0,0,0,.2);
  background: rgba(255,255,255,.2); border-radius: 999px; padding: 2px 8px;
}
.diy-sb__stage {
  position: relative; z-index: 1;
  display: flex; align-items: stretch; gap: 6px;
  padding: 0 12px;
}
.diy-sb--peek .diy-sb__stage { padding: 0; }
.diy-sb__banner {
  flex: 1; min-width: 0; border-radius: 10px; overflow: hidden;
}
.diy-sb__peek-side {
  width: 28px; flex-shrink: 0; border-radius: 8px; overflow: hidden; opacity: 0.85;
}
.diy-sb__peek-side img { width: 100%; height: 100%; object-fit: cover; display: block; }
.diy-sb__slide, .diy-sb__img { display: block; width: 100%; height: 100%; object-fit: cover; }
.diy-sb__empty {
  height: 100%;
  display: flex; align-items: center; justify-content: center;
  color: rgba(255,255,255,.9); font-size: 12px;
  background: rgba(0,0,0,.08);
}
</style>
