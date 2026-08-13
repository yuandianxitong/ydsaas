<template>
    <!-- 分类导航画布预览（视觉对齐元点Shop PreviewCategoryNav：40px 圆图标 + 11px 灰字） -->
    <div v-if="isScroll" class="pv-cat pv-cat--scroll">
        <div v-for="(it, i) in displayItems" :key="i" class="pv-cat__item pv-cat__item--scroll">
            <img v-if="it.icon" :src="it.icon" class="pv-cat__icon pv-cat__icon--real" />
            <div v-else class="pv-cat__icon"></div>
            <span>{{ it.title || `分类${i + 1}` }}</span>
        </div>
    </div>
    <div v-else class="pv-cat" :style="{ gridTemplateColumns: `repeat(${cols}, 1fr)` }">
        <div v-for="(it, i) in displayItems" :key="i" class="pv-cat__item">
            <img v-if="it.icon" :src="it.icon" class="pv-cat__icon pv-cat__icon--real" />
            <div v-else class="pv-cat__icon"></div>
            <span>{{ it.title || `分类${i + 1}` }}</span>
        </div>
    </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ props: Record<string, any> }>()
const isScroll = computed(() => props.props?.style === 'scroll')
const cols = computed(() => props.props?.columns || 5)
const rows = computed(() => props.props?.rows || 2)
// 空数据 → rows×columns 灰圆占位（横滑单行 columns+2 个，示意可滑）；有数据用真实项
const displayItems = computed<{ icon?: string; title?: string }[]>(() => {
    const real = Array.isArray(props.props?.items) ? props.props.items : []
    if (real.length) return real
    const n = isScroll.value ? cols.value + 2 : rows.value * cols.value
    return Array.from({ length: n }, () => ({}))
})
</script>
<style scoped>
.pv-cat {
    display: grid;
    gap: 8px;
    padding: 12px;
    text-align: center;
}
.pv-cat--scroll {
    display: flex;
    overflow: hidden;
}
.pv-cat__item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: #666;
}
/* 尺寸对齐 uniapp diy-category-nav：横滑项 128rpx=64px、图标 88rpx=44px */
.pv-cat__item--scroll {
    flex-shrink: 0;
    width: 64px;
}
.pv-cat__icon {
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #f0f2f5;
}
.pv-cat__icon--real {
    object-fit: cover;
}
</style>
