<template>
  <div class="diy-stage">
    <div class="pc-page" :style="phoneStyle">
      <div v-if="!loaded" class="loading">加载中...</div>
      <DiyRenderer v-else-if="page && page.components && page.components.length" :components="page.components" :page-settings="page.page_settings" />
      <div v-else class="empty">{{ errMsg || '页面暂无内容' }}</div>
    </div>
  </div>
</template>
<script setup lang="ts">
// ref/computed/onMounted/useRoute/useHead 由 Nuxt 自动导入（与其它页面一致）
import DiyRenderer from '~/components/diy/DiyRenderer.vue'
import { diyApi, type DiyPagePayload } from '~/api/diy'

const route = useRoute()
const page = ref<DiyPagePayload | null>(null)
const loaded = ref(false)
const errMsg = ref('')

const phoneStyle = computed(() => {
  const bg = (page.value?.page_settings as any)?.background_color
  return bg ? { backgroundColor: bg } : {}
})

onMounted(async () => {
  const key = String(route.params.key || '')
  if (!key) { errMsg.value = '缺少页面标识'; loaded.value = true; return }
  try {
    const res = await diyApi.getDiyPage(key)
    page.value = res.data
    const title = (res.data?.page_settings as any)?.title
    if (title) useHead({ title })
  } catch {
    errMsg.value = '页面不存在或未发布'
  } finally {
    loaded.value = true
  }
})
</script>
<style scoped>
.diy-stage { min-height: 100vh; background: #f8fafc; }
.pc-page { position: relative; width: 100%; min-height: 100vh; background: #fff; overflow: hidden; }
.empty, .loading { padding: 80px 0; text-align: center; color: #999; font-size: 14px; }
</style>
