import { myRequest } from '@/utils/request'

export interface MarketplaceConnection {
    id: number
    instance_uuid: string
    instance_name: string
    site_base_url: string
    token_prefix: string
    bound_user_email?: string
    bound_user_name?: string
    status: 'active' | 'suspended' | 'revoked'
    last_heartbeat_at?: string
    last_sync_at?: string
    last_error?: string
    // v2.9.0 token rotation derived fields
    token_rotated_at?: string
    token_expires_at?: string
    token_age_days?: number | null
    token_rotate_warn?: boolean
    token_rotate_overdue?: boolean
}

export interface MarketplaceCategory {
    id: number
    code: string
    name: string
    parent_id: number
    sort: number
}

export interface MarketplaceAppRow {
    entitlement_code: string | null
    remote_app_id: string
    app_code: string
    app_name: string
    app_description?: string
    app_icon_url?: string
    publisher_name?: string
    latest_version?: string
    installed?: boolean
    installed_version?: string
    plugin_id?: number
    has_upgrade?: boolean
    is_public?: boolean
    // 连接态下每行必有：是否已购；buy_url 仅非 owned 行有
    owned?: boolean
    buy_url?: string
    category_id?: number | null
    category?: { id: number; code: string; name: string } | null
    // v2.9.0 license derived fields (only present when installed)
    license_status?: 'active' | 'grace' | 'expired' | null
    license_grace_started_at?: string | null
    read_safe_routes_count?: number
    // v2.9.0 compatibility flag (backend returns int 0/1)
    compatible?: boolean | number | null
}

// 安装意图：被官方市场详情精简后的应用信息
export interface MarketplaceIntentApp {
    app_code: string
    app_name?: string
    app_description?: string
    app_icon_url?: string
    publisher_name?: string
    entitlement_code?: string
    latest_version?: string
    latest_version_id?: string
    plan_code?: string
    period_end?: string | null
}

// 安装意图判定结果（后端单接口编排，前端据 status 分流）
export type InstallResolution =
    | { status: 'authorization_required'; authorize_url: string; state: string }
    | { status: 'connected'; connection?: MarketplaceConnection }
    | { status: 'ready'; app: MarketplaceIntentApp; current_version: string }
    | { status: 'purchase_required'; app: { app_code: string }; buy_url: string }
    | {
          status: 'incompatible'
          app: MarketplaceIntentApp
          current_version: string
          required: { min: string | null; max: string | null }
      }
    | { status: 'error'; message: string }

export const marketplaceApi = {
    // 点安装 / 连接账号：app_code 缺省表示纯连接意图；未连接时需 callback_url 发起 OAuth
    createInstallIntent(body: { app_code?: string; callback_url?: string }) {
        return myRequest.post<InstallResolution>('/platformapi/marketplace/install-intents', body)
    },

    exchange(body: { state: string; code: string }) {
        return myRequest.post<MarketplaceConnection & { install_resolution?: InstallResolution }>(
            '/platformapi/marketplace/connections/exchange',
            body
        )
    },

    listConnections() {
        return myRequest.get<{ data: MarketplaceConnection[] }>(
            '/platformapi/marketplace/connections'
        )
    },

    revokeConnection(id: number) {
        return myRequest.delete(`/platformapi/marketplace/connections/${id}`)
    },

    categories() {
        return myRequest.get<{ data: MarketplaceCategory[] }>('/platformapi/marketplace/categories')
    },

    apps(params?: { categoryId?: number; page?: number; limit?: number }) {
        const query: Record<string, number> = {}
        if (params?.categoryId && params.categoryId > 0) query.category_id = params.categoryId
        if (params?.page && params.page > 0) query.page = params.page
        if (params?.limit && params.limit > 0) query.limit = params.limit
        return myRequest.get<{
            data: MarketplaceAppRow[]
            connection_id?: number
            site_base_url?: string
            is_public?: boolean
            pagination?: { total: number; page: number; limit: number }
        }>('/platformapi/marketplace/apps', { params: query })
    },

    sync() {
        return myRequest.post<{ changed_count: number; removed_count: number }>(
            '/platformapi/marketplace/sync'
        )
    },

    install(body: { entitlement_code: string; version_id?: string }) {
        return myRequest.post('/platformapi/marketplace/install', body)
    },

    upgrade(body: { plugin_id: number }) {
        return myRequest.post('/platformapi/marketplace/upgrade', body)
    },

    // v2.9.0: changelog drawer + 手动 token rotate
    fetchChangelog(entitlementCode: string, toVersion: string) {
        return myRequest.get<{ changelog: string }>(
            `/platformapi/marketplace/apps/${encodeURIComponent(entitlementCode)}/changelog`,
            { params: { to_version: toVersion } }
        )
    },

    rotateConnectionToken(connectionId: number) {
        return myRequest.post<{ connection_id: number }>(
            `/platformapi/marketplace/connections/${connectionId}/rotate-token`
        )
    }
}
