<template>
  <div class="diy-rich-text" v-html="safeHtml" />
</template>
<script setup lang="ts">
import { computed } from 'vue'
import DOMPurify from 'dompurify'
const props = defineProps<{ props: Record<string, any> }>()
// SPA(ssr:false)，DOMPurify 在客户端运行；每次渲染都净化（历史数据可能绕过保存期校验）
const safeHtml = computed(() => DOMPurify.sanitize(String(props.props?.content || '')))
</script>
<style scoped>.diy-rich-text { padding: 10px; }</style>
