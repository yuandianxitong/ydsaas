<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { pluginApi, type TenantPluginInfo } from '@/api/plugin'

import { groupByCategory } from './plugin-market'

const list = ref<TenantPluginInfo[]>([])
const loading = ref(false)
const keyword = ref('')
const router = useRouter()

const groups = computed(() => {
    const kw = keyword.value.trim()
    const src = kw ? list.value.filter((p) => p.name.includes(kw)) : list.value
    return groupByCategory(src)
})

async function load() {
    loading.value = true
    try {
        list.value = (await pluginApi.list()).data
    } finally {
        loading.value = false
    }
}
function openDetail(row: TenantPluginInfo) {
    router.push(`/plugin/${row.plugin_code}`)
}
onMounted(load)
</script>

<template>
    <div v-loading="loading" class="market">
        <div class="search-bar">
            <el-input v-model="keyword" placeholder="插件名称" clearable style="width: 280px" />
        </div>
        <div v-for="g in groups" :key="g.key" class="category">
            <div class="category-head"><span class="bar" />{{ g.label }}</div>
            <div class="grid">
                <div v-for="p in g.items" :key="p.plugin_id" class="tile" @click="openDetail(p)">
                    <img v-if="p.icon" :src="p.icon" class="tile-icon" alt="" />
                    <div v-else class="tile-icon tile-icon--ph">{{ p.name.slice(0, 1) }}</div>
                    <div class="tile-body">
                        <div class="tile-title">{{ p.name }}</div>
                        <div class="tile-desc">{{ p.description }}</div>
                    </div>
                </div>
            </div>
        </div>
        <el-empty v-if="!loading && !groups.length" description="暂无插件" />
    </div>
</template>

<style scoped>
.search-bar {
    display: flex;
    justify-content: flex-end;
    margin-bottom: 14px;
}
.category {
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-hairline);
    padding: 18px 22px;
    margin-bottom: 14px;
}
.category-head {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 15px;
    font-weight: 700;
    margin-bottom: 14px;
}
.category-head .bar {
    width: 3px;
    height: 14px;
    border-radius: 2px;
    background: var(--el-color-primary);
}
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
    gap: 12px;
}
.tile {
    display: flex;
    gap: 12px;
    padding: 14px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
    min-width: 0;
}
.tile:hover {
    border-color: var(--el-color-primary-light-7);
    background: #fafbff;
    box-shadow: 0 4px 12px -8px rgba(44, 115, 255, 0.35);
}
.tile-icon {
    width: 42px;
    height: 42px;
    border-radius: var(--radius-md);
    flex-shrink: 0;
    object-fit: cover;
}
.tile-icon--ph {
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: 700;
    background: var(--el-color-primary);
}
.tile-body {
    min-width: 0;
}
.tile-title {
    font-size: 13px;
    font-weight: 600;
}
.tile-desc {
    font-size: 11.5px;
    color: var(--el-text-color-secondary);
    margin-top: 4px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
</style>
