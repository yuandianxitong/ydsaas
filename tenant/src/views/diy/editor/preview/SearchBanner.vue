<template>
  <div class="pv-sb" :class="`pv-sb--${styleName}`">
    <!-- 当前轮播图模糊底：自上而下渐隐；编辑器不模拟状态栏占位 -->
    <div class="pv-sb__blur" :style="blurFallback">
      <img v-if="blurSrc" :src="blurSrc" class="pv-sb__blur-img" alt="" />
      <div class="pv-sb__blur-fade" />
    </div>
    <div class="pv-sb__head" :class="{ 'pv-sb__head--no-tabs': !showTabs || !tabs.length }">
      <div class="pv-sb__search">
        <img v-if="brandMode === 'logo' && logo" :src="logo" class="pv-sb__logo" alt="" />
        <span v-else-if="brandMode === 'text' && brandText" class="pv-sb__brand">{{ brandText }}</span>
        <div class="pv-sb__box">
          <span class="pv-sb__ph">{{ hotwordPreview || placeholder }}</span>
        </div>
      </div>
      <div v-if="showTabs && tabs.length" class="pv-sb__tabs">
        <span v-for="(tab, i) in tabs" :key="i" class="pv-sb__tab">{{ tab.text }}</span>
      </div>
    </div>
    <div class="pv-sb__stage">
      <div v-if="styleName === 'peek'" class="pv-sb__peek-side pv-sb__peek-side--l">
        <img v-if="sideImg.left" :src="sideImg.left" alt="" />
      </div>
      <div class="pv-sb__banner">
        <img v-if="firstImg" :src="firstImg" class="pv-sb__img" alt="" />
        <div v-else class="pv-sb__empty">轮播搜索</div>
        <div v-if="indicatorStyle !== 'none'" class="pv-sb__ind">
          <i
            v-for="n in Math.max(count, 1)"
            :key="n"
            class="pv-sb__dot"
            :class="{ 'pv-sb__dot--on': n === 1 }"
          />
        </div>
      </div>
      <div v-if="styleName === 'peek'" class="pv-sb__peek-side pv-sb__peek-side--r">
        <img v-if="sideImg.right" :src="sideImg.right" alt="" />
      </div>
    </div>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'

import { normalizeSearchBannerStyle } from '../stylePreset'

const props = defineProps<{ props: Record<string, any>; isFirst?: boolean }>()
const styleName = computed(() => normalizeSearchBannerStyle(props.props?.style))
const brandMode = computed(() => String(props.props?.brand_mode || 'logo'))
const brandText = computed(() => String(props.props?.brand_text || '').trim())
const logo = computed(() => String(props.props?.logo || ''))
const placeholder = computed(() => String(props.props?.placeholder || '请输入搜索词'))
const showTabs = computed(() => props.props?.show_tabs !== false)
const tabs = computed(() =>
  (Array.isArray(props.props?.tabs) ? props.props.tabs : []).filter((t: any) => String(t?.text || '').trim())
)
const hotwordPreview = computed(() => {
  const list = Array.isArray(props.props?.hotwords) ? props.props.hotwords : []
  return String(list[0]?.text || '').trim()
})
const theme = computed(() => String(props.props?.theme_color || '#ff6034'))
const items = computed(() => (Array.isArray(props.props?.items) ? props.props.items : []))
const firstImg = computed(() => String(items.value[0]?.image || ''))
const blurSrc = computed(() => firstImg.value)
const count = computed(() => items.value.length)
const indicatorStyle = computed(() => String(props.props?.indicator_style || 'dot'))
const sideImg = computed(() => {
  const n = items.value.length
  if (n < 2) return { left: firstImg.value, right: firstImg.value }
  return {
    left: String(items.value[n - 1]?.image || firstImg.value),
    right: String(items.value[1]?.image || firstImg.value),
  }
})
const blurFallback = computed(() =>
  blurSrc.value
    ? {}
    : { background: `linear-gradient(180deg, ${theme.value} 0%, ${theme.value}aa 42%, transparent 100%)` }
)
</script>
<style scoped>
.pv-sb { position: relative; width: 100%; overflow: hidden; padding-bottom: 10px; }
.pv-sb__blur {
  position: absolute; top: 0; left: 0; right: 0; height: 92%;
  z-index: 0; overflow: hidden; pointer-events: none;
  -webkit-mask-image: linear-gradient(180deg, #000 0%, #000 38%, rgba(0,0,0,.35) 68%, transparent 100%);
  mask-image: linear-gradient(180deg, #000 0%, #000 38%, rgba(0,0,0,.35) 68%, transparent 100%);
}
.pv-sb__blur-img {
  width: 100%; height: 100%; object-fit: cover;
  filter: blur(28px); transform: scale(1.25);
  opacity: 0.95;
}
.pv-sb__blur-fade {
  position: absolute; left: 0; right: 0; bottom: 0; height: 55%;
  background: linear-gradient(180deg, rgba(255,255,255,0) 0%, rgba(255,255,255,.55) 45%, #fff 100%);
}
.pv-sb__head { position: relative; z-index: 2; }
.pv-sb__head--no-tabs .pv-sb__search { padding-bottom: 10px; }
.pv-sb__search {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 12px 6px;
}
.pv-sb__logo { height: 22px; width: auto; max-width: 72px; object-fit: contain; flex-shrink: 0; }
.pv-sb__brand {
  color: #fff; font-size: 14px; font-weight: 700; flex-shrink: 0;
  max-width: 72px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  text-shadow: 0 1px 2px rgba(0,0,0,.25);
}
.pv-sb__box {
  flex: 1; min-width: 0; height: 32px; border-radius: 16px;
  background: rgba(255, 255, 255, 0.92);
  display: flex; align-items: center; padding: 0 12px;
}
.pv-sb__ph { font-size: 12px; color: #999; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.pv-sb__tabs { display: flex; gap: 6px; padding: 0 12px 8px; overflow: hidden; }
.pv-sb__tab {
  flex-shrink: 0; font-size: 11px; color: #fff;
  text-shadow: 0 1px 2px rgba(0,0,0,.2);
  background: rgba(255,255,255,.2); border-radius: 999px; padding: 2px 8px;
}
.pv-sb__stage {
  position: relative; z-index: 1;
  display: flex; align-items: stretch; gap: 6px;
  padding: 0 12px;
  height: 140px;
}
.pv-sb--card .pv-sb__stage { padding: 0 12px; }
.pv-sb--peek .pv-sb__stage { padding: 0 0; gap: 6px; }
.pv-sb__banner {
  position: relative; flex: 1; min-width: 0;
  border-radius: 10px; overflow: hidden;
  height: 100%;
}
.pv-sb--peek .pv-sb__banner { flex: 1 1 auto; width: 0; }
.pv-sb__peek-side {
  width: 28px; flex-shrink: 0; border-radius: 8px; overflow: hidden;
  opacity: 0.85;
}
.pv-sb__peek-side img { width: 100%; height: 100%; object-fit: cover; display: block; }
.pv-sb__img { width: 100%; height: 100%; object-fit: cover; display: block; }
.pv-sb__empty {
  width: 100%; height: 100%;
  display: flex; align-items: center; justify-content: center;
  color: rgba(255,255,255,.9); font-size: 12px;
  background: rgba(0,0,0,.12);
}
.pv-sb__ind {
  position: absolute; left: 0; right: 0; bottom: 8px;
  display: flex; justify-content: center; gap: 4px; z-index: 2;
}
.pv-sb__dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: rgba(255,255,255,.5); display: block;
}
.pv-sb__dot--on { background: #fff; }
</style>
