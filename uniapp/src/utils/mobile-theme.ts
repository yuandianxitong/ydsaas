import type { ThemeColors } from '@/api/mobile-config'

/**
 * 将移动端主题色应用到导航栏与 H5 CSS 变量。
 * 可在启动与 /api/mobile/config 软刷新后重复调用。
 */
export function applyMobileTheme(theme: string, colors: ThemeColors = {}): void {
  if (!theme) return

  uni.setNavigationBarColor({
    backgroundColor: theme,
    frontColor: '#ffffff',
    fail: () => {},
  })

  // #ifdef H5
  if (typeof document !== 'undefined') {
    const root = document.documentElement.style
    root.setProperty('--theme-color', theme)
    if (colors.dark) root.setProperty('--theme-dark', colors.dark)
    if (colors.price) root.setProperty('--theme-price', colors.price)
    if (colors.page_bg) root.setProperty('--theme-page-bg', colors.page_bg)
    if (colors.button_text) root.setProperty('--theme-button-text', colors.button_text)
    if (colors.badge) root.setProperty('--theme-badge', colors.badge)
  }
  // #endif
}
