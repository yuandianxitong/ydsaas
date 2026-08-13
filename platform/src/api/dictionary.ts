import type { PageResult, SelectOption } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface DictionaryInfo {
    id: number
    name: string
    code: string
    description?: string
    sort: number
    status: number
    items_count?: number
    created_at?: string
}

export interface DictionaryItemInfo {
    id: number
    dictionary_id: number
    label: string
    value: string
    tag_type?: string
    description?: string
    sort: number
    status: number
}

export interface DictionaryReq {
    name: string
    code: string
    description?: string
    sort?: number
    status?: number
}

export interface DictionaryItemReq {
    dictionary_id: number
    label: string
    value: string
    tag_type?: string
    description?: string
    sort?: number
    status?: number
}

export interface DictionaryQuery {
    keyword?: string
    status?: number
    page?: number
    limit?: number
}

export const dictionaryApi = {
    getList(params: DictionaryQuery) {
        return myRequest.get<PageResult<DictionaryInfo>>('/platformapi/system/dictionary', {
            params
        })
    },
    getDetail(id: number) {
        return myRequest.get<DictionaryInfo & { items: DictionaryItemInfo[] }>(
            `/platformapi/system/dictionary/${id}`
        )
    },
    create(data: DictionaryReq) {
        return myRequest.post<void>('/platformapi/system/dictionary', data)
    },
    update(id: number, data: Partial<DictionaryReq>) {
        return myRequest.put<void>(`/platformapi/system/dictionary/${id}`, data)
    },
    delete(id: number) {
        return myRequest.delete<void>(`/platformapi/system/dictionary/${id}`)
    },
    batchDelete(ids: number[]) {
        return myRequest.post<void>('/platformapi/system/dictionary/batch-delete', { ids })
    },
    getOptions(code: string) {
        return myRequest.get<SelectOption[]>('/platformapi/system/dictionary/options', {
            params: { code }
        })
    },
    getBatchOptions(codes: string[]) {
        return myRequest.get<Record<string, SelectOption[]>>(
            '/platformapi/system/dictionary/batch-options',
            {
                params: { codes: codes.join(',') }
            }
        )
    },
    getItems(dictionaryId: number) {
        return myRequest.get<DictionaryItemInfo[]>(
            `/platformapi/system/dictionary/${dictionaryId}/items`
        )
    },
    createItem(data: DictionaryItemReq) {
        return myRequest.post<void>('/platformapi/system/dictionary/item', data)
    },
    updateItem(id: number, data: Partial<Omit<DictionaryItemReq, 'dictionary_id'>>) {
        return myRequest.put<void>(`/platformapi/system/dictionary/item/${id}`, data)
    },
    deleteItem(id: number) {
        return myRequest.delete<void>(`/platformapi/system/dictionary/item/${id}`)
    }
}
