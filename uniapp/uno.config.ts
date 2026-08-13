import { defineConfig } from 'unocss'
import presetWeapp from 'unocss-preset-weapp'
import presetIcons from '@unocss/preset-icons'
import { transformerClass } from 'unocss-preset-weapp/transformer'

const isH5 = process.env.UNI_PLATFORM === 'h5'

export default defineConfig({
  presets: [
    presetWeapp({ isH5, platform: 'uniapp' }),
    presetIcons({
      scale: 1.2,
      extraProperties: {
        'display': 'inline-block',
        'vertical-align': 'middle',
      },
    }),
  ],
  transformers: [
    transformerClass(),
  ],
  // 动态图标类名兜底：这些类名只存在于后端下发的数据里（DIY hydrator 注入的 items[].icon，
  // 如 shop OrderEntriesHydrator 的订单入口四宫格），UnoCSS 静态扫描源码看不到它们，
  // 不进 safelist 生产构建会静默丢图标。新增"数据驱动图标"时必须同步登记在此。
  safelist: [
    // shop 插件 · 订单入口（server/plugins/shop/app/diy/OrderEntriesHydrator.php）
    'i-ri-wallet-3-line',
    'i-ri-box-3-line',
    'i-ri-checkbox-circle-line',
    'i-ri-shield-check-line',
  ],
})
