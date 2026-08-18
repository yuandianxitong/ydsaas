<template>
    <div class="app-tabs flex">
        <div class="flex-1 min-w-0">
            <el-tabs
                v-model="currentTab"
                :closable="tabsLists.length > 1"
                @tab-change="handleChange"
                @tab-remove="removeTab"
            >
                <template v-for="item in tabsLists" :key="item.fullPath">
                    <el-tab-pane
                        :label="translateRouteTitle(item.title, item.name)"
                        :name="item.fullPath"
                    ></el-tab-pane>
                </template>
            </el-tabs>
        </div>
        <el-dropdown @command="handleCommand">
            <span class="flex items-center px-3">
                <div class="i-svg:arrow-down"></div>
            </span>
            <template #dropdown>
                <el-dropdown-menu>
                    <el-dropdown-item command="closeCurrent">{{
                        $t('tabs.closeCurrent')
                    }}</el-dropdown-item>
                    <el-dropdown-item command="closeOther">{{
                        $t('tabs.closeOther')
                    }}</el-dropdown-item>
                    <el-dropdown-item command="closeAll">{{
                        $t('tabs.closeAll')
                    }}</el-dropdown-item>
                </el-dropdown-menu>
            </template>
        </el-dropdown>
    </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'

import useMultipleTabs from '@/hooks/useMultipleTabs'
import { useWatchRoute } from '@/hooks/useWatchRoute'
import useTabsStore from '@/store/modules/multipleTabs.store'
import { translateRouteTitle } from '@/utils/i18n'

const router = useRouter()
const tabsStore = useTabsStore()

// 从 hooks 获取对 tab 操作的方法和响应式列表
const { addTab, removeTab, removeOtherTab, removeAllTab, tabsLists, currentTab } = useMultipleTabs()

// 在路由每次变化时自动调用 addTab
useWatchRoute(() => {
    addTab()
})

// 切换标签时触发：根据 fullPath 在 tabMap 中找到对应的 TabItem，然后 push 跳转
const handleChange = (fullPath: any) => {
    const tabItem = tabsStore.tabMap[fullPath]
    if (!tabItem) return
    router.push({
        path: tabItem.path,
        query: tabItem.query
    })
}

// 下拉菜单的三种操作：关闭当前 / 关闭其他 / 关闭全部
const handleCommand = (command: string) => {
    switch (command) {
        case 'closeCurrent':
            removeTab(currentTab.value)
            break
        case 'closeOther':
            removeOtherTab()
            break
        case 'closeAll':
            removeAllTab()
            break
    }
}
</script>

<style lang="scss" scoped>
.app-tabs {
    height: var(--tabs-h);
    background: #fff;
    border-bottom: 1px solid var(--ink-100);

    :deep(.el-tabs) {
        height: var(--tabs-h);

        .el-tabs {
            &__header {
                margin-bottom: 0;
            }

            &__content {
                display: none;
            }

            &__nav-next,
            &__nav-prev {
                height: 40px;
                display: flex;
                align-items: center;
                justify-content: center;
                width: 24px;

                // 隐藏默认 Element Plus 箭头图标
                .el-icon {
                    display: none;
                }

                // 用本地 SVG 替代
                &::after {
                    content: '';
                    display: block;
                    width: 16px;
                    height: 16px;
                    background-color: var(--el-text-color-primary);
                }

                &:hover::after {
                    background-color: var(--el-color-primary);
                }
            }

            &__nav-prev::after {
                mask: url('@/assets/icons/chevron-left.svg') no-repeat center / contain;
                -webkit-mask: url('@/assets/icons/chevron-left.svg') no-repeat center / contain;
            }

            &__nav-next::after {
                mask: url('@/assets/icons/chevron-right.svg') no-repeat center / contain;
                -webkit-mask: url('@/assets/icons/chevron-right.svg') no-repeat center / contain;
            }

            &__nav-wrap::after {
                height: 0;
            }

            &__item {
                font-weight: 400;
                font-size: 12.5px;
                color: var(--ink-500);
                padding: 0 15px !important;
                box-sizing: border-box;

                &.is-active {
                    background-color: var(--brand-50);
                    color: var(--brand-500);
                    border: 1px solid var(--brand-100);
                    border-bottom: none;
                    border-radius: var(--r-sm) var(--r-sm) 0 0;

                    &::before {
                        content: '';
                        display: inline-block;
                        width: 4px;
                        height: 4px;
                        background-color: var(--brand-500);
                        margin-right: 6px;
                        border-radius: 50%;
                        vertical-align: 2px;
                    }
                }

                .is-icon-close {
                    color: var(--ink-400);
                    vertical-align: -2px;

                    &:hover {
                        color: #fff;
                        background-color: var(--brand-500);
                    }
                }
            }

            &__active-bar {
                display: none;
            }
        }
    }
}
</style>
