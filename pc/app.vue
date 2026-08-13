<template>
  <NConfigProvider :locale="zhCN" :date-locale="dateZhCN">
    <NMessageProvider>
      <NDialogProvider>
        <NuxtLayout>
          <NuxtPage />
        </NuxtLayout>
      </NDialogProvider>
    </NMessageProvider>
  </NConfigProvider>
</template>

<script setup lang="ts">
import { NConfigProvider, NMessageProvider, NDialogProvider } from 'naive-ui'
import { zhCN, dateZhCN } from 'naive-ui'
import { useSiteStore } from '~/store/site'

const siteStore = useSiteStore()

onMounted(async () => {
  const config = await siteStore.load()
  useHead({
    title: config.seo?.title || config.site_name || '元点 SaaS',
    meta: [
      { name: 'keywords', content: config.seo?.keywords || '' },
      { name: 'description', content: config.seo?.description || config.site_intro || '' },
    ],
  })
})
</script>
