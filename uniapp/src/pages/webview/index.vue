<template>
  <web-view v-if="url" :src="url" />
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'

const url = ref('')
onLoad((q) => {
  url.value = q?.url ? decodeURIComponent(String(q.url)) : ''
})
</script>
