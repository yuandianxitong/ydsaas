import type { PageQuery, PageResult } from '@/types/api'
import { myRequest } from '@/utils/request'

export interface AnnouncementInfo {
    id: number
    title: string
    content: string
    type?: string
    status: string
    published_at?: string | null
    created_at?: string
    updated_at?: string
}

export interface AnnouncementReq {
    title: string
    content: string
    type?: string
    status?: string
}

export const platformAnnouncementApi = {
    list: (params?: PageQuery) =>
        myRequest.get<PageResult<AnnouncementInfo>>('/platformapi/announcement', { params }),
    show: (id: number) => myRequest.get<AnnouncementInfo>(`/platformapi/announcement/${id}`),
    create: (data: AnnouncementReq) =>
        myRequest.post<AnnouncementInfo>('/platformapi/announcement', data),
    update: (id: number, data: Partial<AnnouncementReq>) =>
        myRequest.put(`/platformapi/announcement/${id}`, data),
    destroy: (id: number) => myRequest.delete(`/platformapi/announcement/${id}`),
    publish: (id: number) => myRequest.put(`/platformapi/announcement/${id}/publish`)
}
