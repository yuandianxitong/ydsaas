import type { GeneratorColumnInfo, GeneratorConfig, GeneratorTableInfo } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface GeneratorPreviewFile {
    path: string
    content: string
}

export interface GeneratorPreviewResult {
    [filename: string]: GeneratorPreviewFile
}

export const generatorApi = {
    getTables() {
        return myRequest.get<GeneratorTableInfo[]>('/platformapi/system/generator/tables')
    },
    getColumns(table: string) {
        return myRequest.get<GeneratorColumnInfo[]>('/platformapi/system/generator/columns', {
            params: { table }
        })
    },
    preview(data: GeneratorConfig) {
        return myRequest.post<GeneratorPreviewResult>('/platformapi/system/generator/preview', data)
    },
    generate(data: GeneratorConfig) {
        return myRequest.post<{
            files: Array<{ path: string; status: string; reason?: string }>
            route?: string
        }>('/platformapi/system/generator/generate', data)
    }
}
