// src/router/routes.config.ts
import type { RouteRecordRaw } from 'vue-router'

import { PageEnum } from '@/constants/page'

// 推荐直接懒加载 Layout 组件（更符合 Vite 的分包习惯）
export const LAYOUT = () => import('@/layout/index.vue')

// 首页、动态路由挂载的占位名称（父路由 name）
export const INDEX_ROUTE_NAME = 'INDEX_ROUTE'

// 404 独立导出，方便在需要时复用（可选）
export const NOT_FOUND_ROUTE: RouteRecordRaw = {
    path: '/:pathMatch(.*)*',
    component: () => import('@/views/error/404.vue'),
    meta: { hidden: true }
}

// 插件详情页（常量路由，登录后均可访问，activeMenu 高亮「应用管理」菜单项）
export const PLUGIN_DETAIL_ROUTE: RouteRecordRaw = {
    path: '/plugin/:code/:panel?',
    component: () => import('@/views/plugin/detail-shell.vue'),
    meta: { title: '插件详情', activeMenu: '/plugin', hidden: true }
}

// 订阅管理（常量路由，登录后可访问；view 已存在但无菜单/路由，home 升级按钮与告警条均跳转此处）
export const SUBSCRIPTION_ROUTE: RouteRecordRaw = {
    path: '/subscription',
    component: () => import('@/views/subscription/index.vue'),
    meta: { title: '订阅管理', hidden: true }
}

// 租户资料（常量路由，登录后可访问；头像下拉「租户资料」入口跳转此处）
export const TENANT_PROFILE_ROUTE: RouteRecordRaw = {
    path: '/tenant-profile',
    component: () => import('@/views/tenant-profile/index.vue'),
    meta: { title: '租户资料', hidden: true }
}

// 常量路由：无需权限或基础错误页、登录页等
// 注意：NOT_FOUND_ROUTE 不在此处，在动态路由加载完成后由 permission.guard 添加，
// 避免 catch-all 在动态路由挂载前抢先匹配导致 404
export const constantRoutes: Array<RouteRecordRaw> = [
    {
        path: PageEnum.ERROR_403,
        component: () => import('@/views/error/403.vue'),
        meta: { hidden: true }
    },
    {
        path: PageEnum.ERROR_500,
        component: () => import('@/views/error/500.vue'),
        meta: { hidden: true }
    },
    {
        path: PageEnum.LOGIN,
        component: () => import('@/views/login/index.vue'),
        meta: { hidden: true }
    },
    // 装修全屏编辑器（同标签全屏，不套 layout；返回按钮回列表页）
    {
        path: '/diy/editor',
        name: 'DiyEditor',
        component: () => import('@/views/diy/editor-page.vue'),
        meta: { hidden: true, hideTab: true, title: '装修编辑器' }
    }
]

// "动态挂载"父路由：登录后把后端返回的子路由都挂在这里
// redirect 由 permission.guard 动态设置为第一个有效菜单页，此处不能设为 '/'（会自引用死循环）
export const INDEX_ROUTE: RouteRecordRaw = {
    path: PageEnum.INDEX,
    component: LAYOUT,
    name: INDEX_ROUTE_NAME,
    children: [PLUGIN_DETAIL_ROUTE, SUBSCRIPTION_ROUTE, TENANT_PROFILE_ROUTE]
}
