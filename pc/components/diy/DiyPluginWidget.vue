<template>
  <!-- 插件自带 PC 渲染器（协议 v1）：注册表命中 → 整块交给插件组件（含区块头） -->
  <component :is="pluginRenderer" v-if="pluginRenderer" :props="props.props" />

  <!-- 核心通用渲染：card-list / single / list（缺省回退，未命中注册表的插件 render 也落到这里） -->
  <div v-else class="diy-pw">
    <div v-if="sectionTitle" class="pw-section">
      <h2 class="pw-section__title">{{ sectionTitle }}</h2>
      <a v-if="safeLink(moreLink)" :href="safeLink(moreLink)" class="pw-section__more">
        查看更多 <span aria-hidden="true">→</span>
      </a>
    </div>
    <!-- card-list: 2 列卡片 -->
    <div v-if="render === 'card-list'" class="pw-cards">
      <template v-for="(it, i) in items" :key="i">
        <a v-if="safeLink(it.link)" :href="safeLink(it.link)" rel="noopener noreferrer" class="pw-card">
          <img v-if="it.image" :src="it.image" :alt="it.title" loading="lazy" class="pw-card__img" />
          <div class="pw-card__body">
            <span class="pw-card__title">{{ it.title }}</span>
            <span v-if="it.desc" class="pw-card__desc">{{ it.desc }}</span>
            <span v-if="it.meta" class="pw-card__meta">{{ it.meta }}</span>
          </div>
        </a>
        <div v-else class="pw-card">
          <img v-if="it.image" :src="it.image" :alt="it.title" loading="lazy" class="pw-card__img" />
          <div class="pw-card__body">
            <span class="pw-card__title">{{ it.title }}</span>
            <span v-if="it.desc" class="pw-card__desc">{{ it.desc }}</span>
            <span v-if="it.meta" class="pw-card__meta">{{ it.meta }}</span>
          </div>
        </div>
      </template>
    </div>

    <!-- single -->
    <div v-else-if="render === 'single' && items.length" class="pw-single">
      <a v-if="safeLink(items[0].link)" :href="safeLink(items[0].link)" rel="noopener noreferrer" class="pw-single__inner">
        <img v-if="items[0].image" :src="items[0].image" class="pw-single__img" />
        <span class="pw-single__title">{{ items[0].title }}</span>
        <span v-if="items[0].desc" class="pw-single__desc">{{ items[0].desc }}</span>
        <span v-if="items[0].meta" class="pw-single__meta">{{ items[0].meta }}</span>
      </a>
      <div v-else class="pw-single__inner">
        <img v-if="items[0].image" :src="items[0].image" class="pw-single__img" />
        <span class="pw-single__title">{{ items[0].title }}</span>
        <span v-if="items[0].desc" class="pw-single__desc">{{ items[0].desc }}</span>
        <span v-if="items[0].meta" class="pw-single__meta">{{ items[0].meta }}</span>
      </div>
    </div>

    <!-- grid-3: 三列 -->
    <div v-else-if="render === 'grid-3'" class="pw-cards pw-cards--3">
      <template v-for="(it, i) in items" :key="i">
        <a v-if="safeLink(it.link)" :href="safeLink(it.link)" rel="noopener noreferrer" class="pw-card pw-card--3">
          <img v-if="it.image" :src="it.image" :alt="it.title" loading="lazy" class="pw-card__img pw-card__img--3" />
          <div class="pw-card__body">
            <span class="pw-card__title pw-card__title--sm">{{ it.title }}</span>
            <span v-if="it.desc" class="pw-card__desc">{{ it.desc }}</span>
            <span v-if="it.meta" class="pw-card__meta">{{ it.meta }}</span>
          </div>
        </a>
        <div v-else class="pw-card pw-card--3">
          <img v-if="it.image" :src="it.image" :alt="it.title" loading="lazy" class="pw-card__img pw-card__img--3" />
          <div class="pw-card__body">
            <span class="pw-card__title pw-card__title--sm">{{ it.title }}</span>
            <span v-if="it.desc" class="pw-card__desc">{{ it.desc }}</span>
            <span v-if="it.meta" class="pw-card__meta">{{ it.meta }}</span>
          </div>
        </div>
      </template>
    </div>

    <!-- scroll-x: 横滑 -->
    <div v-else-if="render === 'scroll-x'" class="pw-scroll">
      <template v-for="(it, i) in items" :key="i">
        <a v-if="safeLink(it.link)" :href="safeLink(it.link)" rel="noopener noreferrer" class="pw-card pw-card--scroll">
          <img v-if="it.image" :src="it.image" :alt="it.title" loading="lazy" class="pw-card__img" />
          <div class="pw-card__body">
            <span class="pw-card__title pw-card__title--sm">{{ it.title }}</span>
            <span v-if="it.desc" class="pw-card__desc">{{ it.desc }}</span>
          </div>
        </a>
        <div v-else class="pw-card pw-card--scroll">
          <img v-if="it.image" :src="it.image" :alt="it.title" loading="lazy" class="pw-card__img" />
          <div class="pw-card__body">
            <span class="pw-card__title pw-card__title--sm">{{ it.title }}</span>
            <span v-if="it.desc" class="pw-card__desc">{{ it.desc }}</span>
          </div>
        </div>
      </template>
    </div>

    <!-- list（缺省） -->
    <div v-else class="pw-list">
      <template v-for="(it, i) in items" :key="i">
        <a v-if="safeLink(it.link)" :href="safeLink(it.link)" rel="noopener noreferrer" class="pw-row">
          <img v-if="it.image" :src="it.image" :alt="it.title" loading="lazy" class="pw-row__img" />
          <div class="pw-row__body">
            <span class="pw-row__title">{{ it.title }}</span>
            <span v-if="it.desc" class="pw-row__desc">{{ it.desc }}</span>
            <span v-if="it.meta" class="pw-row__meta">{{ it.meta }}</span>
          </div>
        </a>
        <div v-else class="pw-row">
          <img v-if="it.image" :src="it.image" :alt="it.title" loading="lazy" class="pw-row__img" />
          <div class="pw-row__body">
            <span class="pw-row__title">{{ it.title }}</span>
            <span v-if="it.desc" class="pw-row__desc">{{ it.desc }}</span>
            <span v-if="it.meta" class="pw-row__meta">{{ it.meta }}</span>
          </div>
        </div>
      </template>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, defineAsyncComponent, markRaw } from 'vue'
import { diyRenderers } from '~/generated/diy-renderers'
import { safeLink } from './safeLink'

const props = defineProps<{ props: Record<string, any>; type?: string }>()

// 插件 DIY 渲染器解析（同 pages/[...slug].vue 范式：generated 注册表 + glob 懒加载）
const rendererModules = import.meta.glob('../../plugins/**/*.vue')
const pluginRenderer = computed(() => {
  if (!props.type) return null
  const entry = diyRenderers.find((r) => r.type === props.type)
  if (!entry) return null
  const key = `../../${entry.component}.vue`
  const loader = rendererModules[key]
  return loader ? markRaw(defineAsyncComponent(loader as () => Promise<any>)) : null
})

const sectionTitle = computed(() => String(props.props?.section_title || '').trim())
const moreLink = computed(() => String(props.props?.more_link || ''))
// 核心 5 种通用 render（与后端 NormalizedWidget::CORE_RENDER_KINDS 一致），越界降级 list
const render = computed(() => {
  const r = props.props?.render
  return ['card-list', 'list', 'single', 'grid-3', 'scroll-x'].includes(r) ? r : 'list'
})
const items = computed(() => (props.props?.items as any[]) || [])
</script>

<style scoped>
.diy-pw { width: 100%; }
.pw-section { display: flex; align-items: end; justify-content: space-between; gap: 24px; margin-bottom: 20px; }
.pw-section__title { margin: 0; color: #172033; font-size: 26px; font-weight: 700; letter-spacing: -.02em; }
.pw-section__more { flex: none; padding-bottom: 2px; color: #687386; font-size: 13px; text-decoration: none; transition: color .2s ease, transform .2s ease; }
.pw-section__more:hover { color: var(--color-primary, #2563eb); transform: translateX(2px); }
.pw-cards { display: flex; flex-wrap: wrap; padding: 6px; box-sizing: border-box; }
.pw-card { width: 50%; box-sizing: border-box; padding: 4px; display: block; text-decoration: none; }
.pw-card__img { width: 100%; height: 100px; object-fit: cover; border-radius: 4px; display: block; }
.pw-card__body { padding: 4px 2px; }
.pw-card__title { font-size: 13px; color: #222; display: block; }
.pw-card__desc { font-size: 12px; color: #fa3534; display: block; margin-top: 2px; }
.pw-card__meta { font-size: 11px; color: #999; display: block; margin-top: 2px; }
.pw-list { padding: 4px 8px; }
.pw-row { display: flex; align-items: center; padding: 6px 0; text-decoration: none; }
.pw-row__img { width: 60px; height: 60px; object-fit: cover; border-radius: 4px; margin-right: 8px; flex-shrink: 0; }
.pw-row__body { flex: 1; min-width: 0; }
.pw-row__title { font-size: 14px; color: #222; display: block; }
.pw-row__desc { font-size: 12px; color: #fa3534; display: block; margin-top: 3px; }
.pw-row__meta { font-size: 11px; color: #999; display: block; margin-top: 2px; }
.pw-cards--3 .pw-card--3 { width: 33.333%; }
.pw-card__img--3 { height: 70px; }
.pw-card__title--sm { font-size: 12px; }
.pw-scroll { display: flex; overflow-x: auto; padding: 6px; box-sizing: border-box; }
.pw-card--scroll { width: 120px; flex-shrink: 0; padding: 4px; }
.pw-single { padding: 8px; }
.pw-single__inner { display: block; text-decoration: none; }
.pw-single__img { width: 100%; height: 160px; object-fit: cover; border-radius: 6px; display: block; }
.pw-single__title { font-size: 16px; font-weight: 600; color: #222; display: block; margin-top: 6px; }
.pw-single__desc { font-size: 13px; color: #fa3534; display: block; margin-top: 3px; }
.pw-single__meta { font-size: 12px; color: #999; display: block; margin-top: 2px; }

@media (max-width: 620px) {
  .pw-section__title { font-size: 22px; }
}
</style>
