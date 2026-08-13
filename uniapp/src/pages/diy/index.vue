<template>
  <view class="diy-page" :style="pageStyle">
    <view
      v-if="page && pageSettings.show_header !== false"
      class="diy-page-titlebar"
      :style="{ backgroundColor: pageSettings.background_color || '' }"
    >
      <text class="diy-page-titlebar__text">{{ pageTitle }}</text>
    </view>
    <DiyRenderer
      v-if="page && page.components && page.components.length"
      :components="page.components"
      :page-settings="page.page_settings"
    />
    <view v-else-if="loaded" class="diy-empty">
      <text class="diy-empty-text">{{ errMsg || '页面暂无内容' }}</text>
    </view>
    <DiyPopupAd v-if="page" :ad="pageSettings.popup_ad" :page-key="pageKey" />
  </view>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { onLoad } from '@dcloudio/uni-app'
import DiyRenderer from '@/components/diy/DiyRenderer.vue'
import DiyPopupAd from '@/components/diy/DiyPopupAd.vue'
import { mobileConfigApi, type DiyPagePayload } from '@/api/mobile-config'
import { provideMemberStats } from '@/hooks/useMemberStats'
import { useAppStore } from '@/store/app.store'

const appStore = useAppStore()
const page = ref<DiyPagePayload | null>(null)
const loaded = ref(false)
const errMsg = ref('')
const pageKey = ref('')
const { refresh: refreshMemberStats } = provideMemberStats()

const pageSettings = computed(() => (page.value?.page_settings as Record<string, any>) || {})
const pageTitle = computed(
  () => String((page.value as any)?.title || pageSettings.value.title || '')
)
const pageStyle = computed(() => {
  const s = pageSettings.value
  const style: Record<string, string> = {}
  if (s.background_color) style.backgroundColor = s.background_color
  if (s.background_image) {
    style.backgroundImage = `url(${appStore.getImageUrl(s.background_image)})`
    style.backgroundSize = 'cover'
    style.backgroundPosition = 'center'
  }
  return style
})

onLoad((query: Record<string, string> | undefined) => {
  const key = query?.key || ''
  pageKey.value = key
  if (!key) {
    loaded.value = true
    errMsg.value = '缺少页面标识'
    return
  }
  mobileConfigApi
    .getDiyPage(key)
    .then((res) => {
      page.value = res
      // navigationStyle=custom：标题仅由 show_header 内页栏展示
      refreshMemberStats(res.components)
    })
    .catch(() => {
      errMsg.value = '页面不存在或未发布'
    })
    .finally(() => {
      loaded.value = true
    })
})
</script>

<style lang="scss" scoped>
.diy-page {
  min-height: 100vh;
  background-color: #f5f5f5;
}
.diy-page-titlebar {
  height: 88rpx;
  display: flex;
  align-items: center;
  justify-content: center;
  background: #fff;
  padding-top: var(--status-bar-height, 0);
  box-sizing: content-box;

  &__text {
    font-size: 32rpx;
    font-weight: 600;
    color: #172033;
  }
}
.diy-empty {
  padding: 120rpx 0;
  text-align: center;
}
.diy-empty-text {
  font-size: 26rpx;
  color: #999;
}
</style>
