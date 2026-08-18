// src/constants/appSetting.ts

export const defaultSetting = {
    showCrumb: true,
    showLogo: true,
    isUniqueOpened: false,
    sideWidth: 200,
    sideTheme: 'light',
    openMultipleTabs: true,
    primaryColor: '#4f6bff',
    compact: false,
    sidebarLabels: true
}

export const appConfig = {
    terminal: 1,
    title: '元点Saas',
    language: 'zh-cn',
    version: '1.0.0',
    baseUrl: import.meta.env.VITE_APP_API_URL || '',
    urlPrefix: 'platformapi',
    timeout: 10 * 1000
}
