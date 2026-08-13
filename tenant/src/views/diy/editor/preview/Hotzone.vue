<template>
  <div class="pv-hz">
    <img v-if="image" :src="image" class="pv-hz__img" alt="" />
    <div v-else class="pv-empty">热区</div>
  </div>
</template>
<script setup lang="ts">
import { computed } from 'vue'
const props = defineProps<{ props: Record<string, any> }>()
const image = computed(() => String(props.props?.image || ''))
</script>
<style scoped>
.pv-hz { width: 100%; }
.pv-hz__img { width: 100%; height: auto; display: block; }
.pv-empty {
  display: flex;
  align-items: center;
  justify-content: center;
  height: 150px;
  color: #9aa4b2;
  font-size: 12px;
  background: #eef1f6;
}
</style>
