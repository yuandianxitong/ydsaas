<template>
  <div
    class="pv-banner"
    :class="[`pv-banner--${indicatorPos}`]"
    :style="{ height: ((props.props?.height || 300) / 2) + 'px' }"
  >
    <img v-if="firstImg" :src="firstImg" class="pv-banner__img" alt="" />
    <div v-else class="pv-empty">轮播图</div>
    <div
      v-if="indicatorStyle !== 'none'"
      class="pv-banner__ind"
      :class="[`pv-banner__ind--${indicatorStyle}`, `pv-banner__ind--${indicatorPos}`]"
    >
      <span v-if="indicatorStyle === 'number'" class="pv-banner__num">1/{{ count || 1 }}</span>
      <template v-else>
        <i
          v-for="n in Math.max(count, 1)"
          :key="n"
          class="pv-banner__dot"
          :class="{ 'pv-banner__dot--on': n === 1 }"
        />
      </template>
    </div>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ props: Record<string, any> }>()
const firstImg = computed(() => props.props?.items?.[0]?.image || '')
const count = computed(() => (props.props?.items || []).length)
const indicatorStyle = computed(() => String(props.props?.indicator_style || 'dot'))
const indicatorPos = computed(() => String(props.props?.indicator_position || 'inside-bottom'))
</script>
<style scoped>
.pv-banner { position: relative; width: 100%; }
.pv-banner__img { width: 100%; height: 100%; object-fit: cover; display: block; }
.pv-empty { display:flex;align-items:center;justify-content:center;height:100%;color:#9aa4b2;font-size:12px;background:#eef1f6; }
.pv-banner__ind { display:flex; gap:4px; align-items:center; justify-content:center; z-index:2; }
.pv-banner__ind--inside-bottom { position:absolute; left:0; right:0; bottom:6px; }
.pv-banner__ind--outside-bottom { position:absolute; left:0; right:0; bottom:2px; }
.pv-banner__ind--inside-right { position:absolute; right:6px; top:50%; transform:translateY(-50%); flex-direction:column; }
.pv-banner__dot { width:6px; height:6px; border-radius:50%; background:rgba(255,255,255,.55); display:block; }
.pv-banner__dot--on { background:#fff; }
.pv-banner__ind--line .pv-banner__dot { width:12px; height:3px; border-radius:2px; }
.pv-banner__num { font-size:10px; color:#fff; background:rgba(0,0,0,.35); border-radius:10px; padding:1px 6px; }
.pv-banner--outside-bottom { margin-bottom: 14px; }
</style>
