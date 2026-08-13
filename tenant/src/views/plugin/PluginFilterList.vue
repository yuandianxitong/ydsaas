<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'

import { pluginApi, type TenantPluginInfo } from '@/api/plugin'

import PluginCard from './components/PluginCard.vue'
import PurchaseDialog from './components/PurchaseDialog.vue'

const props = defineProps<{
    filter: (list: TenantPluginInfo[]) => TenantPluginInfo[]
    emptyText: string
}>()

const list = ref<TenantPluginInfo[]>([])
const loading = ref(false)
const router = useRouter()
const purchaseVisible = ref(false)
const currentForPurchase = ref({ id: 0, code: '', name: '' })

const shown = computed(() => props.filter(list.value))

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
function openPurchase(row: TenantPluginInfo) {
    currentForPurchase.value = { id: row.plugin_id, code: row.plugin_code, name: row.name }
    purchaseVisible.value = true
}
onMounted(load)
</script>

<template>
    <div v-loading="loading" class="plugin-list">
        <div v-if="shown.length" class="grid">
            <PluginCard
                v-for="row in shown"
                :key="row.plugin_id"
                :data="row"
                @click="openDetail(row)"
                @purchase="openPurchase(row)"
                @refresh="load"
            />
        </div>
        <el-empty v-else-if="!loading" class="empty" :description="emptyText" />
        <PurchaseDialog
            v-model:visible="purchaseVisible"
            :plugin-id="currentForPurchase.id"
            :plugin-code="currentForPurchase.code"
            :plugin-name="currentForPurchase.name"
            @purchased="load"
        />
    </div>
</template>

<style scoped>
.grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 16px;
}
.empty {
    /* 空状态铺满容器并居中（原先嵌在 grid 单元里被挤到左侧） */
    width: 100%;
    padding: 48px 0;
}
</style>
