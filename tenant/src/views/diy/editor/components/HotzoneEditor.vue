<template>
    <div class="hz-ed">
        <div class="config-row config-row--top">
            <span class="config-label">底图</span>
            <div class="config-control">
                <ImageSelect :model-value="image" @update:model-value="onImage" />
                <div class="config-field-hint">上传后在图上拖拽绘制热区，点击选中后可拖动/缩放并绑定链接</div>
            </div>
        </div>

        <div
            ref="stageRef"
            class="hz-ed__stage"
            :class="{ 'hz-ed__stage--empty': !image }"
            @pointerdown="onStageDown"
        >
            <img v-if="image" :src="image" class="hz-ed__img" alt="" draggable="false" />
            <div v-else class="hz-ed__empty">请先上传底图</div>
            <div
                v-for="(a, i) in areas"
                :key="a.id"
                class="hz-ed__area"
                :class="{ 'hz-ed__area--on': a.id === selectedId }"
                :style="areaStyle(a)"
                @pointerdown.stop="onAreaDown($event, a)"
            >
                <span class="hz-ed__idx">{{ i + 1 }}</span>
                <i
                    v-if="a.id === selectedId"
                    class="hz-ed__handle"
                    @pointerdown.stop="onResizeDown($event, a)"
                />
            </div>
            <div v-if="draft" class="hz-ed__area hz-ed__area--draft" :style="areaStyle(draft)" />
        </div>

        <div class="config-section">热区列表（{{ areas.length }}）</div>
        <div v-for="(a, i) in areas" :key="a.id" class="config-card hz-ed__card">
            <span class="hz-ed__card-idx">{{ i + 1 }}</span>
            <span class="config-card__close" @click="removeArea(a.id)">×</span>
            <div class="config-card__body">
                <el-input
                    :model-value="a.title || ''"
                    placeholder="备注（可选）"
                    @focus="$emit('begin')"
                    @update:model-value="(v: string) => patchArea(a.id, { title: v })"
                />
                <div class="config-card__link-row">
                    <LinkPicker
                        :model-value="a.link || ''"
                        @update:model-value="(v: string) => patchArea(a.id, { link: v })"
                    />
                </div>
            </div>
        </div>
        <el-button type="primary" class="config-add-btn" :disabled="!image" @click="addDefaultArea">
            + 添加热区
        </el-button>
    </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

import ImageSelect from '@/components/ImageSelect/index.vue'

import LinkPicker from './LinkPicker.vue'

export interface HotArea {
    id: string
    left: number
    top: number
    width: number
    height: number
    link: string
    title?: string
}

const props = defineProps<{
    image: string
    areas: HotArea[]
}>()

const emit = defineEmits<{
    (e: 'begin'): void
    (e: 'update:image', v: string): void
    (e: 'update:areas', v: HotArea[]): void
}>()

const stageRef = ref<HTMLElement | null>(null)
const selectedId = ref<string | null>(null)
const draft = ref<HotArea | null>(null)

const areas = computed(() => (Array.isArray(props.areas) ? props.areas : []))

function genId() {
    return 'hz_' + Date.now().toString(36) + Math.random().toString(36).slice(2, 5)
}

function onImage(v: string | string[]) {
    emit('begin')
    emit('update:image', Array.isArray(v) ? String(v[0] || '') : String(v || ''))
}

function commit(next: HotArea[]) {
    emit('begin')
    emit('update:areas', next)
}

function patchArea(id: string, patch: Partial<HotArea>) {
    commit(areas.value.map((a) => (a.id === id ? { ...a, ...patch } : a)))
}

function removeArea(id: string) {
    emit('begin')
    if (selectedId.value === id) selectedId.value = null
    commit(areas.value.filter((a) => a.id !== id))
}

function addDefaultArea() {
    const a: HotArea = {
        id: genId(),
        left: 10,
        top: 10,
        width: 30,
        height: 20,
        link: '',
        title: '',
    }
    selectedId.value = a.id
    commit([...areas.value, a])
}

function areaStyle(a: Pick<HotArea, 'left' | 'top' | 'width' | 'height'>) {
    return {
        left: `${a.left}%`,
        top: `${a.top}%`,
        width: `${a.width}%`,
        height: `${a.height}%`,
    }
}

function pctFromEvent(e: PointerEvent) {
    const el = stageRef.value
    if (!el) return { x: 0, y: 0 }
    const r = el.getBoundingClientRect()
    const x = ((e.clientX - r.left) / Math.max(r.width, 1)) * 100
    const y = ((e.clientY - r.top) / Math.max(r.height, 1)) * 100
    return {
        x: Math.max(0, Math.min(100, x)),
        y: Math.max(0, Math.min(100, y)),
    }
}

function clampArea(a: HotArea): HotArea {
    const left = Math.max(0, Math.min(100, a.left))
    const top = Math.max(0, Math.min(100, a.top))
    const width = Math.max(4, Math.min(100 - left, a.width))
    const height = Math.max(4, Math.min(100 - top, a.height))
    return { ...a, left, top, width, height }
}

function onStageDown(e: PointerEvent) {
    if (!props.image) return
    if ((e.target as HTMLElement).closest('.hz-ed__area')) return
    emit('begin')
    selectedId.value = null
    const p0 = pctFromEvent(e)
    const id = genId()
    draft.value = { id, left: p0.x, top: p0.y, width: 0, height: 0, link: '', title: '' }
    const move = (ev: PointerEvent) => {
        if (!draft.value) return
        const p1 = pctFromEvent(ev)
        const left = Math.min(p0.x, p1.x)
        const top = Math.min(p0.y, p1.y)
        draft.value = {
            ...draft.value,
            left,
            top,
            width: Math.abs(p1.x - p0.x),
            height: Math.abs(p1.y - p0.y),
        }
    }
    const up = () => {
        window.removeEventListener('pointermove', move)
        window.removeEventListener('pointerup', up)
        const d = draft.value
        draft.value = null
        if (!d || d.width < 3 || d.height < 3) return
        const next = clampArea(d)
        selectedId.value = next.id
        commit([...areas.value, next])
    }
    window.addEventListener('pointermove', move)
    window.addEventListener('pointerup', up)
}

function onAreaDown(e: PointerEvent, a: HotArea) {
    selectedId.value = a.id
    emit('begin')
    const p0 = pctFromEvent(e)
    const start = { ...a }
    const move = (ev: PointerEvent) => {
        const p1 = pctFromEvent(ev)
        const next = clampArea({
            ...start,
            left: start.left + (p1.x - p0.x),
            top: start.top + (p1.y - p0.y),
        })
        commit(areas.value.map((x) => (x.id === a.id ? next : x)))
    }
    const up = () => {
        window.removeEventListener('pointermove', move)
        window.removeEventListener('pointerup', up)
    }
    window.addEventListener('pointermove', move)
    window.addEventListener('pointerup', up)
}

function onResizeDown(e: PointerEvent, a: HotArea) {
    selectedId.value = a.id
    emit('begin')
    const p0 = pctFromEvent(e)
    const start = { ...a }
    const move = (ev: PointerEvent) => {
        const p1 = pctFromEvent(ev)
        const next = clampArea({
            ...start,
            width: start.width + (p1.x - p0.x),
            height: start.height + (p1.y - p0.y),
        })
        commit(areas.value.map((x) => (x.id === a.id ? next : x)))
    }
    const up = () => {
        window.removeEventListener('pointermove', move)
        window.removeEventListener('pointerup', up)
    }
    window.addEventListener('pointermove', move)
    window.addEventListener('pointerup', up)
}
</script>

<style scoped lang="scss">
@import '../config-ui.scss';

.hz-ed__stage {
    position: relative;
    width: 100%;
    margin: 8px 0 12px;
    background: #f5f7fa;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 4px;
    overflow: hidden;
    user-select: none;
    touch-action: none;
    min-height: 120px;
}
.hz-ed__stage--empty {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 140px;
}
.hz-ed__img {
    display: block;
    width: 100%;
    height: auto;
    pointer-events: none;
}
.hz-ed__empty {
    color: #909399;
    font-size: 12px;
    padding: 24px;
}
.hz-ed__area {
    position: absolute;
    box-sizing: border-box;
    border: 1px dashed rgba(64, 158, 255, 0.9);
    background: rgba(64, 158, 255, 0.22);
    cursor: move;
}
.hz-ed__area--on {
    border-style: solid;
    background: rgba(64, 158, 255, 0.32);
    z-index: 2;
}
.hz-ed__area--draft {
    border-style: dotted;
    pointer-events: none;
}
.hz-ed__idx {
    position: absolute;
    top: 2px;
    left: 2px;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    border-radius: 2px;
    background: var(--el-color-primary);
    color: #fff;
    font-size: 10px;
    line-height: 16px;
    text-align: center;
}
.hz-ed__handle {
    position: absolute;
    right: -4px;
    bottom: -4px;
    width: 12px;
    height: 12px;
    background: #fff;
    border: 2px solid var(--el-color-primary);
    border-radius: 2px;
    cursor: nwse-resize;
    box-sizing: border-box;
}
.hz-ed__card {
    position: relative;
}
.hz-ed__card-idx {
    position: absolute;
    top: 8px;
    left: 8px;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    background: var(--el-color-primary-light-8);
    color: var(--el-color-primary);
    font-size: 11px;
    line-height: 18px;
    text-align: center;
    font-weight: 600;
}
.hz-ed__card .config-card__body {
    padding-left: 22px;
}
</style>
