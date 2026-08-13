<template>
    <div class="sim-wrap">
        <div class="phone">
            <img class="sim-statusbar" :src="STATUSBAR_IMG" alt="statusbar" />
            <div
                v-if="showHeader"
                class="sim-titlebar"
                :class="{ 'is-active': !readonly && pageSettingsActive }"
                :style="titlebarStyle"
                @click.stop="onTitlebarClick"
            >
                <span class="sim-titlebar__text">{{
                    pageTitle || $t('diyEditor.pageTitleFallback')
                }}</span>
            </div>
            <div ref="listEl" class="sim-list" :style="listStyle">
                <div
                    v-for="(c, i) in components"
                    :key="c.id"
                    :data-id="c.id"
                    class="sim-item"
                    :class="{ active: c.id === selectedId, 'is-hidden': c.hidden }"
                    :style="styleOf(c)"
                    @click="onItemClick(c.id)"
                >
                    <PluginWidgetPreview
                        v-if="isPlugin(c.type)"
                        :type="c.type"
                        :props="c.props"
                        :meta="metaOf(c.type)"
                    />
                    <component
                        :is="renderer(c.type)"
                        v-else
                        :props="c.props"
                        :is-first="i === 0"
                    />
                </div>
                <div v-if="!components.length" class="sim-empty">{{ $t('diyEditor.empty') }}</div>
            </div>
            <div class="sim-home-indicator" />
        </div>

        <!-- 选中组件浮动工具条：固定在选中组件右侧外层（teleport 到 body，不遮挡组件） -->
        <Teleport v-if="!readonly" to="body">
            <div
                v-if="rect"
                class="diy-floating-actions"
                :style="{ top: rect.top + 'px', left: rect.left + 'px' }"
                @click.stop
            >
                <button
                    class="diy-fa__btn"
                    :title="selectedComponent?.hidden ? $t('diyEditor.show') : $t('diyEditor.hide')"
                    @click.stop="emit('toggle-hidden', selectedId as string)"
                >
                    <Icon :name="selectedComponent?.hidden ? 'i-svg:eye-off' : 'i-svg:eye'" />
                </button>
                <button
                    class="diy-fa__btn"
                    title="删除"
                    @click.stop="emit('remove', selectedId as string)"
                >
                    <Icon name="i-svg:trash-2" />
                </button>
                <button
                    class="diy-fa__btn"
                    :title="$t('diyEditor.duplicate')"
                    @click.stop="emit('duplicate', selectedId as string)"
                >
                    <Icon name="i-svg:copy" />
                </button>
                <button
                    class="diy-fa__btn"
                    title="上移"
                    :disabled="selectedIdx <= 0"
                    @click.stop="emit('move', selectedId as string, -1)"
                >
                    <Icon name="i-svg:chevron-up" />
                </button>
                <button
                    class="diy-fa__btn"
                    title="下移"
                    :disabled="selectedIdx === components.length - 1"
                    @click.stop="emit('move', selectedId as string, 1)"
                >
                    <Icon name="i-svg:chevron-down" />
                </button>
            </div>
        </Teleport>
    </div>
</template>
<script setup lang="ts">
import Sortable from 'sortablejs'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'

// 手机模拟器状态栏图（375×44 浅底深字变体，来自设计套件；显示高度收为 28px 见样式）
import STATUSBAR_IMG from '@/assets/images/sim-statusbar.svg'

import Banner from './preview/Banner.vue'
import CategoryNav from './preview/CategoryNav.vue'
import Divider from './preview/Divider.vue'
import FloatButton from './preview/FloatButton.vue'
import ImageAd from './preview/ImageAd.vue'
import Hotzone from './preview/Hotzone.vue'
import ImageCube from './preview/ImageCube.vue'
import NavGrid from './preview/NavGrid.vue'
import SearchBanner from './preview/SearchBanner.vue'
import Notice from './preview/Notice.vue'
import PluginWidgetPreview from './preview/PluginWidgetPreview.vue'
import RichText from './preview/RichText.vue'
import SearchBar from './preview/SearchBar.vue'
import ServiceMenu from './preview/ServiceMenu.vue'
import TitleBar from './preview/TitleBar.vue'
import UserInfoCard from './preview/UserInfoCard.vue'
import Video from './preview/Video.vue'
import { componentStyleToCss } from './styleUtils'
import { usePluginWidgets } from './usePluginWidgets'

const props = withDefaults(
    defineProps<{
        components: any[]
        selectedId: string | null
        pageTitle?: string
        pageSettingsActive?: boolean
        showHeader?: boolean
        // 对齐 uniapp 端实际渲染背景（page_settings.background_color || $bg-color #f5f5f5，
        // 见 uniapp/src/pages/index/index.vue 与 DiyRenderer.vue）；缺省兜底同色，状态栏/标题栏不受影响
        pageBackground?: string
        pageBackgroundImage?: string
        /** 只读预览：禁拖拽、禁浮动工具条（装修列表枢纽用） */
        readonly?: boolean
    }>(),
    { readonly: false }
)

const { isPlugin, metaOf } = usePluginWidgets()
const emit = defineEmits<{
    (e: 'select', id: string): void
    (e: 'reorder', ids: string[]): void
    (e: 'move', id: string, dir: -1 | 1): void
    (e: 'remove', id: string): void
    (e: 'duplicate', id: string): void
    (e: 'toggle-hidden', id: string): void
    (e: 'activate-page-settings'): void
}>()

const showHeader = computed(() => props.showHeader !== false)

const titlebarStyle = computed(() => {
    const bg = props.pageBackground || ''
    return bg ? { backgroundColor: bg } : undefined
})

const listStyle = computed(() => {
    const css: Record<string, string> = {
        background: props.pageBackground || '#f5f5f5'
    }
    if (props.pageBackgroundImage) {
        css.backgroundImage = `url(${props.pageBackgroundImage})`
        css.backgroundSize = 'cover'
        css.backgroundPosition = 'center'
    }
    return css
})
const MAP: Record<string, any> = {
    banner: Banner,
    'nav-grid': NavGrid,
    'category-nav': CategoryNav,
    'rich-text': RichText,
    'title-bar': TitleBar,
    divider: Divider,
    'image-ad': ImageAd,
    'image-cube': ImageCube,
    hotzone: Hotzone,
    'search-banner': SearchBanner,
    video: Video,
    notice: Notice,
    'search-bar': SearchBar,
    'float-button': FloatButton,
    'user-info-card': UserInfoCard,
    'service-menu': ServiceMenu
}
function renderer(t: string) {
    return MAP[t]
}
function styleOf(c: any) {
    return c.type === 'float-button' ? {} : componentStyleToCss(c.props?.componentStyle)
}
const listEl = ref<HTMLElement>()
let sortable: Sortable | null = null
const dragging = ref(false)

// ───── 浮动工具条定位（锚定选中组件右侧外层） ─────
const rect = ref<{ top: number; left: number } | null>(null)
const selectedIdx = computed(() => props.components.findIndex((c) => c.id === props.selectedId))
const selectedComponent = computed(
    () => props.components.find((c) => c.id === props.selectedId) || null
)
let ro: ResizeObserver | null = null

function updateRect() {
    if (!props.selectedId) {
        rect.value = null
        return
    }
    const el = listEl.value?.querySelector<HTMLElement>(`[data-id="${props.selectedId}"]`)
    if (!el) {
        rect.value = null
        return
    }
    const r = el.getBoundingClientRect()
    rect.value = { top: r.top, left: r.right + 6 }
}

function observeSelected() {
    ro?.disconnect()
    ro = null
    if (!props.selectedId) return
    nextTick(() => {
        const el = listEl.value?.querySelector<HTMLElement>(`[data-id="${props.selectedId}"]`)
        if (!el) return
        ro = new ResizeObserver(updateRect)
        ro.observe(el)
    })
}

watch(
    () => props.selectedId,
    () => {
        nextTick(updateRect)
        observeSelected()
    }
)
watch(
    () => props.components.length,
    () => nextTick(updateRect)
)
watch(
    () => props.components.find((c) => c.id === props.selectedId)?.props,
    () => nextTick(updateRect),
    { deep: true }
)

function initSortable() {
    sortable?.destroy()
    sortable = null
    if (!listEl.value || props.readonly) return
    sortable = Sortable.create(listEl.value, {
        animation: 150,
        onStart: () => {
            dragging.value = true
            rect.value = null
        },
        onEnd: async () => {
            const ids = Array.from(listEl.value!.querySelectorAll('.sim-item'))
                .map((el) => (el as HTMLElement).dataset.id!)
                .filter(Boolean)
            emit('reorder', ids)
            // 等 Vue 依据新数组重排后，重建 Sortable，保证其内部 DOM 状态与 Vue 一致；
            // 同时在重排提交后再清 dragging，避免拖拽结束的原生 click 误触发选中。
            await nextTick()
            dragging.value = false
            initSortable()
            updateRect()
        }
    })
}

function onTitlebarClick() {
    if (props.readonly) return
    emit('activate-page-settings')
}

function onItemClick(id: string) {
    if (props.readonly || dragging.value) return
    emit('select', id)
}

onMounted(() => {
    initSortable()
    // 捕获阶段监听，覆盖任意滚动祖先（panel-center overflow:auto）
    window.addEventListener('scroll', updateRect, true)
    window.addEventListener('resize', updateRect)
})
onBeforeUnmount(() => {
    sortable?.destroy()
    sortable = null
    ro?.disconnect()
    ro = null
    window.removeEventListener('scroll', updateRect, true)
    window.removeEventListener('resize', updateRect)
})
</script>
<style scoped>
.sim-wrap {
    display: flex;
    justify-content: center;
}
/* 对齐 Shop：机身固定高度（clamp 自适应视口），内容在 .sim-list 内部滚动 */
.phone {
    width: 375px;
    height: clamp(600px, calc(100vh - 120px), 844px);
    background: var(--color-surface);
    overflow: hidden;
    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12);
    display: flex;
    flex-direction: column;
}
/* SVG 原生 375×44，按用户要求收到 28px 高；cover 垂直裁掉上下留白避免图标拉伸变形 */
.sim-statusbar {
    width: 100%;
    height: 28px;
    object-fit: cover;
    display: block;
    flex-shrink: 0;
}
.sim-titlebar {
    height: 44px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid var(--color-divider);
    flex-shrink: 0;
    background: var(--color-surface);
    cursor: pointer;
    transition: outline-color 0.2s;
    outline: 2px solid transparent;
    outline-offset: -2px;
}
.sim-titlebar:hover,
.sim-titlebar.is-active {
    outline-color: var(--color-brand);
}
.sim-titlebar__text {
    font-size: 16px;
    font-weight: 500;
    color: var(--color-text-primary);
}
.sim-list {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
}
/* 选中/悬停用 ::after 内描边：零布局占位、盖过全幅内容（轮播），且不被 .sim-list overflow 裁剪 */
.sim-item {
    position: relative;
    cursor: pointer;
}
/* 全幅组件（轮播等）会盖住内侧 outline，改用高层级伪边框保证四边可见 */
.sim-item::after {
    content: '';
    position: absolute;
    inset: 0;
    border: 2px solid transparent;
    border-radius: inherit;
    pointer-events: none;
    z-index: 5;
    transition: border-color 0.2s;
    box-sizing: border-box;
}
.sim-item:hover::after {
    border-color: var(--el-color-primary-light-7);
}
.sim-item.active::after {
    border-color: var(--color-brand);
}
.sim-item.is-hidden {
    opacity: 0.4;
    background-image: repeating-linear-gradient(
        135deg,
        transparent,
        transparent 8px,
        rgba(0, 0, 0, 0.04) 8px,
        rgba(0, 0, 0, 0.04) 12px
    );
}
.sim-empty {
    text-align: center;
    color: var(--color-text-disabled);
    padding: 60px 0;
    font-size: 13px;
    margin: 16px;
    border: 2px dashed var(--color-border);
    border-radius: 8px;
}
.sim-home-indicator {
    width: 134px;
    height: 5px;
    border-radius: 3px;
    background: var(--color-border-strong);
    margin: 8px auto;
    flex-shrink: 0;
}

/* 浮动工具条：固定在视口、锚定选中组件右侧外层（teleport 到 body） */
/* 对齐 Shop diy-floating-actions：32px 按钮、4px 圆角、统一白色悬停（无 danger 特化） */
.diy-floating-actions {
    position: fixed;
    z-index: 2000;
    display: flex;
    flex-direction: column;
    background: var(--color-brand);
    border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.18);
}
.diy-fa__btn {
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 0;
    padding: 0;
    background: transparent;
    color: #fff;
    cursor: pointer;
    font-size: 14px;
    transition: background 0.15s;
}
.diy-fa__btn + .diy-fa__btn {
    border-top: 1px solid rgba(255, 255, 255, 0.18);
}
.diy-fa__btn:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.18);
}
.diy-fa__btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
</style>
