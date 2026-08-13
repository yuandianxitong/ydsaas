<script setup lang="ts">
import { onLaunch } from '@dcloudio/uni-app'
import { useAppStore } from '@/store/app.store'
import { useMobileConfigStore } from '@/store/mobile-config.store'
import { applyMobileTheme } from '@/utils/mobile-theme'

onLaunch(async () => {
  const appStore = useAppStore()
  const mobileConfigStore = useMobileConfigStore()

  // 注入配置先涂一层主题，避免 API 返回前无主题
  applyMobileTheme(mobileConfigStore.themeColor, mobileConfigStore.themeColors)

  await Promise.allSettled([
    appStore.getConfig(),
    mobileConfigStore.load(),
  ])
  // load() 成功后会再次 applyMobileTheme；失败则保留注入主题

  // #ifdef H5
  import('@/utils/wechat-oauth').then(({ initWechatOAuth }) => {
    initWechatOAuth()
  })
  // #endif
})
</script>

<style lang="scss">
@import 'uview-plus/index.scss';
@import './styles/common.scss';
</style>
