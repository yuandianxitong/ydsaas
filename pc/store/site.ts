import { defineStore } from 'pinia'
import { pcApi, type PcSiteConfig } from '~/api/pc'

const defaultConfig: PcSiteConfig = {
  site_name: '元点 SaaS',
  site_logo: '',
  site_intro: '',
  theme_color: '#2563eb',
  home_type: 'diy',
  home_app_code: '',
  home_page: 'home',
  nav: [{ label: '首页', path: '/', code: '', auth: false, sort: 1 }],
  seo: {},
  login_enabled: true,
  register_enabled: true,
  status: 1,
  fallback: { type: 'diy', page_key: 'home' },
}

export const useSiteStore = defineStore('site', {
  state: () => ({
    config: { ...defaultConfig } as PcSiteConfig,
    loaded: false,
  }),

  getters: {
    navItems: (state) => {
      const nav = Array.isArray(state.config.nav) && state.config.nav.length
        ? state.config.nav
        : defaultConfig.nav
      return [...nav].sort((a, b) => Number(a.sort || 0) - Number(b.sort || 0))
    },
    title: (state) => state.config.site_name || defaultConfig.site_name,
    themeColor: (state) => state.config.theme_color || defaultConfig.theme_color,
  },

  actions: {
    async load() {
      if (this.loaded) return this.config
      try {
        const res = await pcApi.getConfig()
        if (res.code === 200 && res.data) {
          this.config = {
            ...defaultConfig,
            ...res.data,
            nav: Array.isArray(res.data.nav) ? res.data.nav : defaultConfig.nav,
            seo: res.data.seo || {},
          }
        }
      } finally {
        this.loaded = true
        if (import.meta.client) {
          document.documentElement.style.setProperty('--color-primary', this.themeColor)
        }
      }
      return this.config
    },
  },
})
