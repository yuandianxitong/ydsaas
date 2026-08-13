import { myRequest } from '@/utils/request'

export const platformAnnouncementApi = {
    list: (params?: any) => myRequest.get('/tenantapi/platform-announcement', { params }),
    show: (id: number) => myRequest.get(`/tenantapi/platform-announcement/${id}`),
    unreadCount: () => myRequest.get('/tenantapi/platform-announcement/unread-count'),
    markAsRead: (id: number) => myRequest.post(`/tenantapi/platform-announcement/${id}/read`)
}
