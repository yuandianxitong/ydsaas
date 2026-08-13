import type { PageQuery, PageResult } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface AuditLogQuery extends PageQuery {
    username?: string
    start_date?: string
    end_date?: string
}

export const auditApi = {
    logs: (params?: AuditLogQuery) =>
        myRequest.get<PageResult<Record<string, unknown>>>('/platformapi/audit/logs', { params })
}
