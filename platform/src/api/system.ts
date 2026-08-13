import type {
    AdminInfo,
    AdminQuery,
    AdminReq,
    ConfigBatchItem,
    ConfigItem,
    ConfigUpdateReq,
    LogQuery,
    MenuInfo,
    MenuReq,
    MenuSortItem,
    PageResult,
    RoleInfo,
    RoleQuery,
    RoleReq
} from '@/types/api'
import { myRequest } from '@/utils/request'

export const platformAdminApi = {
    list: (params?: AdminQuery) =>
        myRequest.get<PageResult<AdminInfo>>('/platformapi/system/admin', { params }),
    show: (id: number) => myRequest.get<AdminInfo>(`/platformapi/system/admin/${id}`),
    create: (data: AdminReq) => myRequest.post<AdminInfo>('/platformapi/system/admin', data),
    update: (id: number, data: Partial<AdminReq>) =>
        myRequest.put(`/platformapi/system/admin/${id}`, data),
    destroy: (id: number) => myRequest.delete(`/platformapi/system/admin/${id}`),
    updateStatus: (id: number, status: number) =>
        myRequest.put(`/platformapi/system/admin/${id}/status`, { status }),
    resetPassword: (id: number, password: string) =>
        myRequest.put(`/platformapi/system/admin/${id}/reset-password`, { password }),
    roleOptions: () =>
        myRequest.get<Array<{ id: number; name: string }>>('/platformapi/system/admin/role/options')
}

export const platformRoleApi = {
    list: (params?: RoleQuery) =>
        myRequest.get<PageResult<RoleInfo>>('/platformapi/system/role', { params }),
    show: (id: number) => myRequest.get<RoleInfo>(`/platformapi/system/role/${id}`),
    create: (data: RoleReq) => myRequest.post<RoleInfo>('/platformapi/system/role', data),
    update: (id: number, data: Partial<RoleReq>) =>
        myRequest.put(`/platformapi/system/role/${id}`, data),
    destroy: (id: number) => myRequest.delete(`/platformapi/system/role/${id}`),
    assignPermissions: (id: number, menuIds: number[]) =>
        myRequest.put(`/platformapi/system/role/${id}/assign-permissions`, { menu_ids: menuIds }),
    updateStatus: (id: number, status: number) =>
        myRequest.put(`/platformapi/system/role/${id}/status`, { status }),
    menuTree: () => myRequest.get<MenuInfo[]>('/platformapi/system/role/menu/tree'),
    options: () =>
        myRequest.get<Array<{ id: number; name: string }>>('/platformapi/system/role/options')
}

export const platformMenuApi = {
    list: () => myRequest.get<MenuInfo[]>('/platformapi/system/menu'),
    options: (excludeId?: number) =>
        myRequest.get<MenuInfo[]>('/platformapi/system/menu/options', {
            params: { exclude_id: excludeId }
        }),
    create: (data: MenuReq) => myRequest.post<MenuInfo>('/platformapi/system/menu', data),
    update: (id: number, data: Partial<MenuReq>) =>
        myRequest.put(`/platformapi/system/menu/${id}`, data),
    destroy: (id: number) => myRequest.delete(`/platformapi/system/menu/${id}`),
    batchSort: (items: MenuSortItem[]) =>
        myRequest.post('/platformapi/system/menu/batch-sort', { items }),
    updateStatus: (id: number, status: number) =>
        myRequest.put(`/platformapi/system/menu/${id}/status`, { status })
}

export const platformLogApi = {
    loginLogs: (params?: LogQuery) =>
        myRequest.get<PageResult<Record<string, unknown>>>('/platformapi/system/log/login', {
            params
        }),
    operationLogs: (params?: LogQuery) =>
        myRequest.get<PageResult<Record<string, unknown>>>('/platformapi/system/log/operation', {
            params
        })
}

export const platformConfigApi = {
    groups: () => myRequest.get<Record<string, string>>('/platformapi/system/config/groups'),
    list: (group?: string) =>
        myRequest.get<ConfigItem[]>('/platformapi/system/config', { params: { group } }),
    update: (id: number, data: ConfigUpdateReq) =>
        myRequest.put(`/platformapi/system/config/${id}`, data),
    batchUpdate: (configs: ConfigBatchItem[]) =>
        myRequest.post('/platformapi/system/config/batch-update', { configs })
}
