<template>
    <!-- 加载中 / 无数据 / 注水失败：回退骨架占位（保持画布形态稳定） -->
    <PluginPlaceholder
        v-if="!showReal"
        :props="props"
        :meta="meta"
        :hint="loading ? '加载预览数据…' : '预览加载失败 · 端上以真实数据渲染'"
    />

    <!-- 插件自带预览渲染器（协议 v1）：注册表命中 → 整块交给插件组件（含区块头/示例数据兜底） -->
    <component :is="pluginRenderer" v-else-if="pluginRenderer" :props="effective!" />

    <!-- 核心通用渲染（5 种）：与 C 端 diy-plugin-widget.vue 同一契约的 web 实现（画布内不可交互） -->
    <div v-else class="pw">
        <div v-if="sectionTitle" class="pw-section">
            <strong class="pw-section__title">{{ sectionTitle }}</strong>
            <span v-if="moreLink" class="pw-section__more">查看更多 →</span>
        </div>
        <!-- card-list: 2 列卡片 -->
        <div v-if="render === 'card-list'" class="pw-cards">
            <div v-for="(it, i) in items" :key="i" class="pw-card">
                <img v-if="it.image" :src="it.image" class="pw-card__img" />
                <div v-else class="pw-card__img pw-imgph" />
                <div class="pw-card__body">
                    <span class="pw-card__title">{{ it.title }}</span>
                    <span v-if="it.desc" class="pw-card__desc">{{ it.desc }}</span>
                    <span v-if="it.meta" class="pw-card__meta">{{ it.meta }}</span>
                </div>
            </div>
        </div>

        <!-- single: 单 hero（仅 items[0]） -->
        <div v-else-if="render === 'single' && items.length" class="pw-single">
            <img v-if="items[0].image" :src="items[0].image" class="pw-single__img" />
            <div v-else class="pw-single__img pw-imgph" />
            <span class="pw-single__title">{{ items[0].title }}</span>
            <span v-if="items[0].desc" class="pw-single__desc">{{ items[0].desc }}</span>
            <span v-if="items[0].meta" class="pw-single__meta">{{ items[0].meta }}</span>
        </div>

        <!-- grid-3: 三列 -->
        <div v-else-if="render === 'grid-3'" class="pw-cards pw-cards--3">
            <div v-for="(it, i) in items" :key="i" class="pw-card pw-card--3">
                <img v-if="it.image" :src="it.image" class="pw-card__img pw-card__img--3" />
                <div v-else class="pw-card__img pw-card__img--3 pw-imgph" />
                <div class="pw-card__body">
                    <span class="pw-card__title pw-card__title--sm">{{ it.title }}</span>
                    <span v-if="it.desc" class="pw-card__desc">{{ it.desc }}</span>
                    <span v-if="it.meta" class="pw-card__meta">{{ it.meta }}</span>
                </div>
            </div>
        </div>

        <!-- scroll-x: 横滑 -->
        <div v-else-if="render === 'scroll-x'" class="pw-scroll">
            <div v-for="(it, i) in items" :key="i" class="pw-card pw-card--scroll">
                <img v-if="it.image" :src="it.image" class="pw-card__img" />
                <div v-else class="pw-card__img pw-imgph" />
                <div class="pw-card__body">
                    <span class="pw-card__title pw-card__title--sm">{{ it.title }}</span>
                    <span v-if="it.desc" class="pw-card__desc">{{ it.desc }}</span>
                </div>
            </div>
        </div>

        <!-- list（缺省）: 整行 -->
        <div v-else class="pw-list">
            <div v-for="(it, i) in items" :key="i" class="pw-row">
                <img v-if="it.image" :src="it.image" class="pw-row__img" />
                <div v-else class="pw-row__img pw-imgph" />
                <div class="pw-row__body">
                    <span class="pw-row__title">{{ it.title }}</span>
                    <span v-if="it.desc" class="pw-row__desc">{{ it.desc }}</span>
                    <span v-if="it.meta" class="pw-row__meta">{{ it.meta }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, defineAsyncComponent, onBeforeUnmount, onMounted, ref, watch } from 'vue'

import { diyApi, type PluginWidgetMeta } from '@/api/diy'
import {
    applyShowPrice,
    hasRenderableContent,
    type PwItem,
    resolveRender,
    sampleWidgetProps
} from '@/diy-kit'

import PluginPlaceholder from './PluginPlaceholder.vue'

const p = defineProps<{ type: string; props: Record<string, any>; meta: PluginWidgetMeta | null }>()

// ───── 插件自带预览渲染器解析（协议 v1；glob + 异步组件，同 detail-shell.vue 范式） ─────
const rendererModules = import.meta.glob('../../../../components/plugins/*/diy/*.vue')

const pluginRenderer = computed(() => {
    const name = p.meta?.renderer?.tenant
    const pluginCode = p.meta?.plugin
    if (!name || !pluginCode) return null
    const key = Object.keys(rendererModules).find((k) =>
        k.endsWith(`/plugins/${pluginCode}/diy/${name}.vue`)
    )
    // 声明了但文件未同步到（如插件刚安装未重启 dev）→ null，走核心通用回退
    return key ? defineAsyncComponent(rendererModules[key] as () => Promise<any>) : null
})

// ───── 预览注水：500ms 防抖 + hash 去重（对齐 Shop 编辑器 Preview* 组件范式） ─────
const hydrated = ref<Record<string, any> | null>(null)
const loading = ref(false)
let debounceTimer: ReturnType<typeof setTimeout> | null = null
let lastHash = ''

async function fetchData() {
    const hash = JSON.stringify({ type: p.type, props: p.props })
    if (hash === lastHash) return
    lastHash = hash
    loading.value = true
    try {
        const res = await diyApi.previewWidget(p.type, p.props)
        // await 后已被更新的请求取代（慢响应晚到）→ 丢弃，避免旧数据覆盖新数据
        if (hash !== lastHash) return
        hydrated.value = res.data?.props || null
    } catch {
        // 失败回退骨架；错误提示已由响应拦截器统一处理
        if (hash === lastHash) hydrated.value = null
    } finally {
        // 过期响应不碰 loading（由最新一次请求自己收尾）
        if (hash === lastHash) loading.value = false
    }
}
function debouncedFetch() {
    if (debounceTimer) clearTimeout(debounceTimer)
    debounceTimer = setTimeout(fetchData, 500)
}
watch(() => p.props, debouncedFetch, { deep: true })
onMounted(fetchData)

// 插件渲染器命中 → 注水结果整体交给插件组件（示例数据兜底是渲染器自己的职责）；
// 核心通用渲染 → 有真数据用真数据，注水成功但无数据用核心示例卡；失败/未返回 → null 走骨架
const effective = computed<Record<string, any> | null>(() => {
    if (!hydrated.value) return null
    if (pluginRenderer.value) return hydrated.value
    if (hasRenderableContent(hydrated.value)) return hydrated.value
    return { ...hydrated.value, ...sampleWidgetProps(resolveRender(hydrated.value.render)) }
})
const showReal = computed(() => !loading.value && effective.value !== null)
const sectionTitle = computed(() => String(effective.value?.section_title || '').trim())
const render = computed(() => resolveRender(effective.value?.render))
const items = computed(() =>
    applyShowPrice((effective.value?.items as PwItem[]) || [], effective.value?.show_price)
)
const moreLink = computed(() => (effective.value?.more_link as string) || '')

onBeforeUnmount(() => {
    if (debounceTimer) clearTimeout(debounceTimer)
})
</script>

<style scoped>
/* 画布模拟 C 端观感：颜色对齐 uniapp diy-plugin-widget.vue（价格红 #fa3534 等），rpx ÷ 2 */
.pw {
    width: 100%;
    background: #fff;
}
.pw-section {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 12px;
    padding: 13px 10px 7px;
}
.pw-section__title {
    color: #172033;
    font-size: 17px;
    line-height: 1.3;
}
.pw-section__more {
    flex: none;
    color: #8a93a2;
    font-size: 10px;
}
/* 无图占位（示例数据/缺图商品）：对齐 Shop 编辑器的渐变图占位 */
.pw-imgph {
    background: linear-gradient(135deg, #f2f3f5 0%, #e5e6eb 100%);
}
/* card-list / grid-3 */
.pw-cards {
    display: flex;
    flex-wrap: wrap;
    padding: 6px;
    box-sizing: border-box;
}
.pw-card {
    width: 50%;
    box-sizing: border-box;
    padding: 4px;
}
.pw-card__img {
    width: 100%;
    height: 100px;
    border-radius: 4px;
    display: block;
    object-fit: cover;
    background: #f5f5f5;
}
.pw-card__body {
    padding: 4px 2px;
}
.pw-card__title {
    font-size: 13px;
    color: #222;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pw-card__desc {
    font-size: 12px;
    color: #fa3534;
    display: block;
    margin-top: 2px;
}
.pw-card__meta {
    font-size: 11px;
    color: #999;
    display: block;
    margin-top: 2px;
}
.pw-cards--3 .pw-card--3 {
    width: 33.333%;
}
.pw-card__img--3 {
    height: 70px;
}
.pw-card__title--sm {
    font-size: 12px;
}
/* single */
.pw-single {
    padding: 8px;
}
.pw-single__img {
    width: 100%;
    height: 160px;
    border-radius: 6px;
    display: block;
    object-fit: cover;
    background: #f5f5f5;
}
.pw-single__title {
    font-size: 16px;
    font-weight: 600;
    color: #222;
    display: block;
    margin-top: 6px;
}
.pw-single__desc {
    font-size: 13px;
    color: #fa3534;
    display: block;
    margin-top: 3px;
}
.pw-single__meta {
    font-size: 12px;
    color: #999;
    display: block;
    margin-top: 2px;
}
/* scroll-x */
.pw-scroll {
    display: flex;
    overflow: hidden;
    padding: 6px;
    box-sizing: border-box;
}
.pw-card--scroll {
    width: 120px;
    flex-shrink: 0;
    padding: 4px;
}
/* list */
.pw-list {
    padding: 4px 8px;
}
.pw-row {
    display: flex;
    align-items: center;
    padding: 6px 0;
}
.pw-row__img {
    width: 60px;
    height: 60px;
    border-radius: 4px;
    margin-right: 8px;
    flex-shrink: 0;
    object-fit: cover;
    background: #f5f5f5;
}
.pw-row__body {
    flex: 1;
    min-width: 0;
}
.pw-row__title {
    font-size: 14px;
    color: #222;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pw-row__desc {
    font-size: 12px;
    color: #fa3534;
    display: block;
    margin-top: 3px;
}
.pw-row__meta {
    font-size: 11px;
    color: #999;
    display: block;
    margin-top: 2px;
}
</style>
