// API响应通用类型
export interface ApiResponse<T = any> {
    code: number
    message: string
    data: T
    timestamp: number
}

// 分页请求参数
export interface PageQuery {
    page?: number
    limit?: number
    keyword?: string
}

// 分页响应数据
export interface PageResult<T> {
    list: T[]
    pagination: {
        current_page: number
        per_page: number
        total: number
        last_page: number
    }
}

// 登录相关类型
export interface LoginReq {
    username: string
    password: string
    captcha: string
    captcha_key: string
}

export interface CaptchaRes {
    key: string
    image: string
}

export interface LoginRes {
    token: string
    admin: AdminInfo
}

export interface AdminInfo {
    id: number
    username: string
    real_name?: string
    email?: string
    mobile?: string
    nickname?: string
    avatar?: string
    department_id?: number | null
    department?: string
    position?: string
    status: number
    is_super?: number
    last_login_ip?: string
    last_login_time?: string
    login_count?: number
    roles?: RoleInfo[]
    permissions?: string[]
}

export interface AdminQuery extends PageQuery {
    status?: number | ''
    department_id?: number | ''
}

export interface AdminReq {
    username: string
    email: string
    mobile?: string
    password?: string
    nickname?: string
    avatar?: string
    department_id?: number | null
    position?: string
    status?: number
    role_ids?: number[]
}

// 角色类型
export interface RoleInfo {
    id: number
    name: string
    title: string
    description?: string
    data_scope?: number
    is_system: boolean
    status: number
    menu_ids?: number[]
    menus?: MenuInfo[]
    created_at?: string
    updated_at?: string
}

export interface RoleQuery extends PageQuery {
    status?: number | ''
}

export interface RoleReq {
    name: string
    title?: string
    description?: string
    data_scope?: number
    status?: number
    sort?: number
    menu_ids?: number[]
}

// 菜单类型
export interface MenuInfo {
    id: number
    parent_id: number
    type: number // 1目录/2菜单/3按钮
    title: string
    name?: string
    path?: string
    component?: string
    icon?: string
    permission?: string
    hidden?: number // 0=显示 1=隐藏（后端直接返回顶层字段，非 meta 嵌套）
    sort?: number
    status: number
    redirect?: string
    meta?: MenuMeta
    children?: MenuInfo[]
    created_at?: string
    updated_at?: string
}

export interface MenuMeta {
    title?: string
    icon?: string
    permission?: string
    activeMenu?: string
    hidden?: boolean
    cache?: boolean
    affix?: boolean
    badge?: string
    dot?: boolean
    iframe?: string
}

export interface MenuReq {
    parent_id: number
    type: number
    title: string
    name?: string
    path?: string
    component?: string
    icon?: string
    permission?: string
    hidden?: number
    sort?: number
    status?: number
    redirect?: string
    meta?: MenuMeta
}

export interface MenuSortItem {
    id: number
    parent_id: number
    sort: number
}

export interface LogQuery extends PageQuery {
    username?: string
    start_date?: string
    end_date?: string
}

export interface ConfigItem {
    id: number
    config_key: string
    config_value: string | number | boolean | Record<string, any>
    config_type: string
    config_group: string
    title: string
    description?: string
    sort_order: number
    status: number
}

export interface ConfigUpdateReq {
    config_value: string | number | boolean | Record<string, any>
}

export interface ConfigBatchItem {
    config_key: string
    config_value: string | number | boolean | Record<string, any>
}

// 修改密码
export interface ChangePasswordReq {
    old_password: string
    new_password: string
}

// 通用选项类型
export interface SelectOption {
    label: string
    value: string | number
}

// ========== 用户认证信息（含路由和权限） ==========
export interface AuthInfoRes {
    admin: AdminInfo
    routes: MenuInfo[]
    permissions: string[]
}

// ============================================================
// SaaS 业务类型（M2C 新增）
// ============================================================

export interface TenantInfo {
    id: number
    tenant_code: string
    name: string
    logo?: string
    contact_name?: string
    contact_phone?: string
    plan_id: number
    status: number // 1=启用 0=禁用
    expires_at: string | null
    storage_used_bytes: number
    storage_limit_bytes: number
    lifecycle_state?: 'trial' | 'active' | 'grace' | 'frozen' | 'disabled'
    custom_settings?: Record<string, any>
    created_at?: string
    updated_at?: string
}

export interface TenantReq {
    tenant_code?: string
    name: string
    logo?: string
    contact_name?: string
    contact_phone?: string
    plan_id?: number
    status?: number
    storage_limit_bytes?: number
    /** trial=试用 formal=线下正式 none=暂不订阅 */
    opening_mode?: 'trial' | 'formal' | 'none'
    trial_days?: number
    months?: number
    remark?: string
    transaction_id?: string
    expires_at?: string
    admin_username?: string
    admin_password?: string
}

export interface TenantQuery extends PageQuery {
    keyword?: string
    status?: number
    plan_id?: number
}

export interface TenantAdmin {
    id: number
    tenant_id: number
    username: string
    nickname: string
    status: number
    last_login_ip: string | null
    last_login_time: string | null
    created_at: string
}

export interface PlanInfo {
    id: number
    code: string
    name: string
    description?: string
    price_monthly: number
    price_yearly: number
    storage_limit_bytes: number
    sort: number
    status: number
    created_at?: string
    updated_at?: string
}

export interface PlanReq {
    code?: string
    name: string
    description?: string
    price_monthly?: number
    price_yearly?: number
    storage_limit_bytes?: number
    sort?: number
    status?: number
}

export interface PlanFeatureInfo {
    id: number
    code: string
    name: string
    description?: string
    group?: string
    sort: number
    status: number
    type: number // 1=基础功能 2=增值功能
    price_monthly: number
    price_yearly: number
    trial_days: number
    icon?: string
    created_at?: string
    updated_at?: string
}

export interface PlanFeatureReq {
    code?: string
    name: string
    description?: string
    group?: string
    sort?: number
    status?: number
    type?: number
    price_monthly?: number
    price_yearly?: number
    trial_days?: number
    icon?: string
}

export interface DashboardStats {
    tenant_total: number
    tenant_active: number
    tenant_expiring: number
    plan_total: number
    plugin_total: number
    admin_total: number
    tenant_trend: Array<{ date: string; count: number }>
}

// ========== SaaS 订单管理 (M3D) ==========

export interface SaasOrderInfo {
    id: number
    order_no: string
    tenant_id: number
    plan_id: number
    type: number // 1=new 2=renew 3=upgrade
    months: number
    amount: number | string
    paid_amount: number | string
    payment_channel: string
    payment_method: string
    prepay_id: string
    transaction_id: string
    status: number // 1=pending 2=paid 3=cancelled 4=refunded
    paid_at: string | null
    expired_at: string | null
    created_at?: string
    updated_at?: string
    /** 展示用名称（后端批量附加，type=4 插件订单时 plan_name 为空、plugin_name 有值） */
    tenant_name?: string
    plan_name?: string
    plugin_name?: string
}

export interface OrderQuery extends PageQuery {
    tenant_id?: number | ''
    plan_id?: number | ''
    status?: number | ''
    order_no?: string
}

// ========== 代码生成器 ==========
export interface GeneratorTableInfo {
    name: string
    comment: string
    engine: string
    rows: number
}

export interface GeneratorColumnInfo {
    name: string
    type: string
    raw_type: string
    nullable: boolean
    default: string | null
    comment: string
    key: string
    extra: string
    form_type: string
    searchable: boolean
    in_list: boolean
    in_form: boolean
}

export interface GeneratorConfig {
    table_name: string
    module_name: string
    model_name: string
    table_comment?: string
    columns?: GeneratorColumnInfo[]
}

// ============================================================
// 插件管理（Stage 2）
// ============================================================

export interface PluginInfo {
    id: number
    code: string
    name: string
    version: string
    author: string
    description: string
    icon: string
    type: number
    kind: 'app' | 'plugin'
    source: number
    status: number
    last_error: string
    installed_at: string | null
    created_at: string
    updated_at: string
    deleted_at: string | null
    manifest: Record<string, unknown>
    requires: Record<string, unknown> | null
    /** 本地包 / 磁盘 / 市场同步检测到的更高版本 */
    latest_version?: string
    update_available?: number
}

export interface PluginUploadResp {
    plugin_id: number
    manifest: Record<string, unknown>
    dry_run_ok: boolean
}

// ============================================================
// 套餐授权（Stage 3）
// ============================================================

export interface PluginGrantInfo {
    id?: number
    plan_id: number
    plugin_id: number
    plugin_code: string
    auto_enable: number // 0 | 1
}

export interface PluginGrantSyncReq {
    plugin_ids: number[]
    auto_enable_ids: number[]
}

export interface PluginOptionInfo {
    id: number
    code: string
    name: string
    version: string
    type: number
    source: number
    icon: string
    description: string
}

// ============================================================
// 云编译（Stage 5）
// ============================================================

export interface PluginBuildInfo {
    id: number
    target: 'platform' | 'tenant'
    trigger: string
    plugin_id: number | null
    status: number // 0=排队 1=构建中 2=成功 3=失败 4=已回滚
    log: string
    artifact_path: string
    started_at: string | null
    finished_at: string | null
    operator_id: number | null
    created_at: string
}

// ============================================================
// 区域管理 / 应用版本（Stage 6 platform migration）
// ============================================================

export interface RegionInfo {
    id: number
    parent_id: number
    name: string
    code: string
    level: number
    sort: number
    status: number
    children?: RegionInfo[]
}

export interface RegionReq {
    parent_id?: number
    name: string
    code: string
    level?: number
    sort?: number
    status?: number
}

export interface AppVersionInfo {
    id: number
    platform: string
    version: string
    version_code: number
    download_url: string
    description?: string
    force_update: number
    status: number
    created_at?: string
    updated_at?: string
}

export interface AppVersionReq {
    platform: string
    version: string
    version_code: number
    download_url: string
    description?: string
    force_update?: number
    status?: number
}

export interface AppVersionQuery extends PageQuery {
    platform?: string
    status?: number
}
