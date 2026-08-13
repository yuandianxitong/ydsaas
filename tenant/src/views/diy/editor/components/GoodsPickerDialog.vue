<template>
    <el-dialog
        :model-value="modelValue"
        title="选择商品"
        width="720px"
        destroy-on-close
        append-to-body
        class="goods-picker-dialog"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <div class="gpd">
            <aside class="gpd__cats">
                <div
                    class="gpd__cat"
                    :class="{ 'gpd__cat--on': categoryId === 0 }"
                    @click="selectCat(0)"
                >
                    全部分类
                </div>
                <div
                    v-for="c in flatCats"
                    :key="c.id"
                    class="gpd__cat"
                    :class="{ 'gpd__cat--on': categoryId === c.id }"
                    :style="{ paddingLeft: 12 + c.depth * 12 + 'px' }"
                    @click="selectCat(c.id)"
                >
                    {{ c.name }}
                </div>
            </aside>
            <div class="gpd__main">
                <el-input
                    v-model="keyword"
                    clearable
                    placeholder="搜索商品名称"
                    class="gpd__search"
                    @keyup.enter="loadGoods(1)"
                >
                    <template #append>
                        <el-button @click="loadGoods(1)">搜索</el-button>
                    </template>
                </el-input>
                <el-table
                    ref="tableRef"
                    v-loading="loading"
                    :data="list"
                    height="360"
                    row-key="id"
                    @selection-change="onSelectionChange"
                >
                    <el-table-column type="selection" width="42" :reserve-selection="true" />
                    <el-table-column label="商品" min-width="220">
                        <template #default="{ row }">
                            <div class="gpd__goods">
                                <img
                                    v-if="coverOf(row)"
                                    :src="coverOf(row)"
                                    class="gpd__cover"
                                    alt=""
                                />
                                <div v-else class="gpd__cover gpd__cover--ph" />
                                <span class="gpd__name">{{ row.name }}</span>
                            </div>
                        </template>
                    </el-table-column>
                    <el-table-column prop="price" label="价格" width="90" />
                    <el-table-column prop="sales" label="销量" width="70" />
                </el-table>
                <div class="gpd__pager">
                    <span>已选 {{ draftIds.length }} 件</span>
                    <el-pagination
                        layout="prev, pager, next"
                        :total="total"
                        :page-size="limit"
                        :current-page="page"
                        small
                        @current-change="loadGoods"
                    />
                </div>
            </div>
        </div>
        <template #footer>
            <el-button @click="emit('update:modelValue', false)">取消</el-button>
            <el-button type="primary" @click="confirm">确定</el-button>
        </template>
    </el-dialog>
</template>

<script setup lang="ts">
import { nextTick, ref, watch } from 'vue'

import { categoryApi } from '@/api/plugins/shop/category'
import { goodsApi } from '@/api/plugins/shop/goods'

interface CatNode {
    id: number
    name: string
    children?: CatNode[]
}

const props = defineProps<{ modelValue: boolean; selectedIds: number[] }>()
const emit = defineEmits<{
    (e: 'update:modelValue', v: boolean): void
    (e: 'confirm', ids: number[]): void
}>()

const tableRef = ref<any>(null)
const flatCats = ref<Array<{ id: number; name: string; depth: number }>>([])
const categoryId = ref(0)
const keyword = ref('')
const list = ref<any[]>([])
const loading = ref(false)
const page = ref(1)
const limit = 10
const total = ref(0)
const draftIds = ref<number[]>([])
const syncing = ref(false)

function flatten(nodes: CatNode[], depth = 0): Array<{ id: number; name: string; depth: number }> {
    const out: Array<{ id: number; name: string; depth: number }> = []
    for (const n of nodes || []) {
        out.push({ id: Number(n.id), name: String(n.name), depth })
        if (n.children?.length) out.push(...flatten(n.children, depth + 1))
    }
    return out
}

function coverOf(row: any): string {
    if (Array.isArray(row.images) && row.images[0]) return String(row.images[0])
    return String(row.image || '')
}

async function loadCats() {
    try {
        const res = await categoryApi.tree()
        flatCats.value = flatten((res.data || []) as CatNode[])
    } catch {
        flatCats.value = []
    }
}

async function loadGoods(p = 1) {
    page.value = p
    loading.value = true
    try {
        const res = await goodsApi.list({
            tab: 'onsale',
            page: page.value,
            limit,
            keyword: keyword.value || undefined,
            category_id: categoryId.value || undefined,
        })
        const data = res.data || {}
        list.value = data.list || []
        total.value = Number(data.pagination?.total || data.total || 0)
        await nextTick()
        restoreSelection()
    } catch {
        list.value = []
        total.value = 0
    } finally {
        loading.value = false
    }
}

function restoreSelection() {
    const table = tableRef.value
    if (!table) return
    syncing.value = true
    const set = new Set(draftIds.value)
    for (const row of list.value) {
        table.toggleRowSelection(row, set.has(Number(row.id)))
    }
    syncing.value = false
}

function onSelectionChange(rows: any[]) {
    if (syncing.value) return
    const pageIds = new Set(list.value.map((r) => Number(r.id)))
    const kept = draftIds.value.filter((id) => !pageIds.has(id))
    const picked = rows.map((r) => Number(r.id))
    draftIds.value = [...kept, ...picked]
}

function selectCat(id: number) {
    categoryId.value = id
    loadGoods(1)
}

function confirm() {
    emit('confirm', [...draftIds.value])
    emit('update:modelValue', false)
}

watch(
    () => props.modelValue,
    (open) => {
        if (!open) return
        draftIds.value = [...(props.selectedIds || [])]
        loadCats()
        loadGoods(1)
    }
)
</script>

<style scoped>
.gpd {
    display: flex;
    gap: 12px;
    min-height: 420px;
}
.gpd__cats {
    width: 180px;
    flex-shrink: 0;
    border: 1px solid var(--el-border-color-lighter);
    border-radius: 2px;
    overflow: auto;
    max-height: 440px;
}
.gpd__cat {
    padding: 10px 12px;
    font-size: 13px;
    cursor: pointer;
    color: var(--el-text-color-regular);
}
.gpd__cat--on {
    background: var(--el-color-primary-light-9);
    color: var(--el-color-primary);
    font-weight: 600;
}
.gpd__main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.gpd__search {
    width: 100%;
}
.gpd__goods {
    display: flex;
    align-items: center;
    gap: 8px;
}
.gpd__cover {
    width: 40px;
    height: 40px;
    border-radius: 6px;
    object-fit: cover;
    flex-shrink: 0;
}
.gpd__cover--ph {
    background: #f2f3f5;
}
.gpd__name {
    font-size: 13px;
    line-height: 1.3;
}
.gpd__pager {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 12px;
    color: var(--el-text-color-secondary);
}
</style>
