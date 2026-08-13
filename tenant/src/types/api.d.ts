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
    email?: string
    mobile?: string
    nickname?: string
    avatar?: string
    department_id?: number | null
    department?: string
    position?: string
    status: number
    last_login_ip?: string
    last_login_time?: string
    login_count?: number
    roles?: RoleInfo[]
    permissions?: string[]
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
    created_at?: string
    updated_at?: string
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

// 管理员管理
export interface AdminReq {
    username: string
    email?: string
    mobile?: string
    password?: string
    nickname?: string
    avatar?: string
    department_id?: number | null
    position?: string
    status: number
    role_ids?: number[]
}

export interface AdminQuery extends PageQuery {
    status?: number
    department?: string
}

// 角色管理
export interface RoleReq {
    name: string
    title: string
    description?: string
    data_scope?: number
    status: number
}

export interface RoleQuery extends PageQuery {
    status?: number
}

// 权限分配（统一使用菜单树）
export interface AssignPermissionsReq {
    menu_ids: number[]
}

// 菜单管理
export interface MenuReq {
    parent_id: number
    type: number
    title: string
    name?: string
    path?: string
    component?: string
    icon?: string
    permission?: string
    sort?: number
    status: number
    meta?: MenuMeta
}

export interface MenuQuery {
    status?: number
    type?: number
    title?: string
}

// 系统配置
export interface ConfigInfo {
    id: number
    config_key: string
    config_name: string
    config_value: any
    config_type: string
    config_group: string
    config_desc?: string
    config_options?: any
    config_depends?: string
    sort_order?: number
    status?: number
    created_at?: string
    updated_at?: string
}

export interface ConfigReq {
    config_key: string
    config_name: string
    config_value: any
    config_type: string
    config_group: string
    config_desc?: string
    config_options?: any
    config_depends?: string
    sort_order?: number
    status?: number
}

// 操作日志
export interface LogInfo {
    id: number
    admin_id: number
    admin_name: string
    action: string
    module: string
    description?: string
    ip: string
    user_agent?: string
    request_data?: any
    response_data?: any
    created_at: string
}

export interface LogQuery extends PageQuery {
    admin_id?: number
    action?: string
    module?: string
    start_date?: string
    end_date?: string
}

// 修改密码
export interface ChangePasswordReq {
    old_password: string
    new_password: string
}

// 重置密码
export interface ResetPasswordReq {
    password: string
}

// 状态更新
export interface StatusReq {
    status: number
}

// 批量删除
export interface BatchDeleteReq {
    ids: number[]
}

// 通用选项类型
export interface SelectOption {
    label: string
    value: string | number
}

export interface TreeOption {
    id: number
    title: string
    children?: TreeOption[]
}

// 角色选项（用于下拉选择）
export interface RoleOption {
    id: number
    name: string
    title: string
}

// ========== 登录日志 ==========
export interface LoginLogInfo {
    id: number
    admin_id?: number
    username: string
    ip: string
    user_agent?: string
    login_time: string
    login_result: number
    login_message?: string
}

export interface OperationLogInfo {
    id: number
    admin_id: number
    admin_name: string
    module: string
    action: string
    method: string
    url: string
    ip: string
    request_data?: Record<string, unknown>
    response_code?: number
    duration?: number
    created_at: string
}

// ========== 数据字典 ==========
export interface DictionaryInfo {
    id: number
    name: string
    code: string
    description?: string
    status: number
    sort?: number
    created_at?: string
    updated_at?: string
}

export interface DictionaryItemInfo {
    id: number
    dictionary_id: number
    label: string
    value: string
    tag_type?: string
    description?: string
    status: number
    sort?: number
    created_at?: string
    updated_at?: string
}

export interface DictionaryQuery extends PageQuery {
    status?: number
}

export interface DictionaryReq {
    name: string
    code: string
    description?: string
    status?: number
    sort?: number
}

export interface DictionaryItemReq {
    dictionary_id: number
    label: string
    value: string
    tag_type?: string
    description?: string
    status?: number
    sort?: number
}

// ========== 部门管理 ==========
export interface DepartmentInfo {
    id: number
    parent_id: number
    name: string
    sort?: number
    status: number
    leader?: string
    phone?: string
    email?: string
    children?: DepartmentInfo[]
    created_at?: string
    updated_at?: string
}

export interface DepartmentReq {
    parent_id: number
    name: string
    sort?: number
    status?: number
    leader?: string
    phone?: string
    email?: string
}

// ========== 通知管理 ==========
export interface NotificationInfo {
    id: number
    title: string
    content: string
    type: number
    status: number
    send_to?: string
    read_count?: number
    created_at?: string
    updated_at?: string
}

export interface NotificationReq {
    title: string
    content: string
    type: number
    send_to?: string | number[]
}

export interface NotificationQuery extends PageQuery {
    type?: number
}

// ========== 定时任务 ==========
export interface CronJobInfo {
    id: number
    name: string
    command: string
    cron_expression: string
    description?: string
    status: number
    last_run_at?: string
    next_run_at?: string
    created_at?: string
    updated_at?: string
}

export interface CronJobReq {
    name: string
    command: string
    cron_expression: string
    description?: string
    status?: number
}

export interface CronJobLogInfo {
    id: number
    cron_job_id: number
    status: number
    output?: string
    duration?: number
    started_at: string
    finished_at?: string
}

// ========== 文件管理 ==========
export interface FileInfo {
    id: number
    name: string
    path: string
    url: string
    mime_type: string
    extension: string
    size: number
    group: string
    storage: string
    upload_by?: number
    category_id: number
    created_at?: string
}

export interface FileQuery extends PageQuery {
    group?: string
    mime_type?: string
    category_id?: number
}

export interface FileCategory {
    id: number
    parent_id: number
    name: string
    sort: number
    file_count: number
    children: FileCategory[]
}

// ========== 仪表板 ==========
export interface DashboardTrend {
    value: number
    type: 'up' | 'down'
    unit?: 'percent'
}

export interface DashboardStats {
    adminCount: number
    roleCount: number
    menuCount: number
    configCount: number
    todayLoginCount: number
    todayNewUsers: number
    activeUsers: number
    totalUsers: number
    trends: {
        totalUsers: DashboardTrend
        activeUsers: DashboardTrend
        todayNewUsers: DashboardTrend
        todayLoginCount: DashboardTrend
    }
    operationLogCount: number
    loginTrend: Array<{ date: string; count: number }>
    registerTrend: Array<{ date: string; count: number }>
}

export interface ActivityItem {
    type: 'login_success' | 'login_failed' | 'operation'
    username: string
    description: string
    time: string
    relative_time: string
}

export interface RankingItem {
    rank: number
    username: string
    count: number
}

export interface ActiveRanking {
    period: string
    list: RankingItem[]
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

// ========== 消息管理 ==========
export interface MessageTemplateInfo {
    id: number
    name: string
    code: string
    remark?: string
    status: number
    sms_enabled?: number
    sms_template_id?: string
    sms_content?: string
    wechat_official_enabled?: number
    wechat_official_template_id?: string
    wechat_official_url?: string
    wechat_mini_enabled?: number
    wechat_mini_template_id?: string
    wechat_mini_page?: string
    // Legacy fields
    channel?: string
    content?: string
    variables?: string
    description?: string
    created_at?: string
    updated_at?: string
}

export interface MessageTemplateReq {
    name: string
    code: string
    remark?: string
    status?: number
    sms_enabled?: number
    sms_template_id?: string
    sms_content?: string
    wechat_official_enabled?: number
    wechat_official_template_id?: string
    wechat_official_url?: string
    wechat_mini_enabled?: number
    wechat_mini_template_id?: string
    wechat_mini_page?: string
    // Legacy fields
    channel?: string
    content?: string
    variables?: string
    description?: string
}

export interface MessageLogInfo {
    id: number
    template_id?: number
    channel: string
    to: string
    content: string
    status: number
    error_message?: string
    sent_at?: string
    created_at?: string
}

// ========== 微信管理 ==========
export interface AutoReplyInfo {
    id: number
    type: string
    keyword: string
    match_type: string
    content: string
    sort_order?: number
    status: number
    created_at?: string
    updated_at?: string
}

export interface AutoReplyReq {
    type: string
    keyword?: string
    match_type?: string
    content: string
    sort_order?: number
    status?: number
}

export interface WechatFollowerInfo {
    openid: string
    nickname?: string
    sex?: number
    city?: string
    province?: string
    country?: string
    headimgurl?: string
    subscribe_time?: number
}

// ========== 系统配置（补充） ==========
export interface ConfigGroup {
    name: string
    title: string
    icon?: string
}

export interface ConfigBatchUpdateItem {
    config_key: string
    config_value: string | number | boolean
}

// ========== 用户认证信息（含路由和权限） ==========
export interface AuthInfoRes {
    admin: AdminInfo
    routes: MenuInfo[]
    permissions: string[]
    saas?: SaasInfo
}

/**
 * 租户权益（Phase 1 后端在 /tenantapi/auth/info 的 saas.entitlements 返回）
 */
export interface Entitlement {
    code: string
    kind: 'app' | 'plugin'
    source: 'plan' | 'purchase'
    expires_at: string | null
    /** 展示名（插件 name，缺省回退 code） */
    name?: string
    /** 可访问图标 URL */
    icon?: string
}

/**
 * SaaS lifecycle 元数据（由后端 /tenantapi/auth/info 的 saas 字段返回，M2A 已实现）
 */
export interface SaasInfo {
    lifecycle_state: 'trial' | 'active' | 'grace' | 'frozen' | 'disabled'
    grace_until: string | null
    expires_at: string | null
    tenant_name: string
    tenant_code: string
    tenant_logo: string
    /** 当前套餐 ID（0=无） */
    plan_id?: number
    /** @deprecated 用 entitlements 拿富对象 */
    features: string[]
    /** 富对象权益列表（含 name/icon 展示字段） */
    entitlements: Entitlement[]
    limits: {
        storage_used_bytes: number
        storage_limit_bytes: number
    }
}

// ========== 用户管理（C 端用户） ==========
export interface UserItem {
    id: number
    nickname: string
    avatar: string
    mobile: string
    balance: string
    points: number
    status: number
    last_login_ip: string
    last_login_time: string
    login_count: number
    created_at: string
}

export interface BalanceLogItem {
    id: number
    user_id: number
    user_nickname: string
    amount: string
    before_balance: string
    after_balance: string
    type: number
    type_text: string
    source: string
    remark: string
    operator_id: number | null
    operator_name: string | null
    created_at: string
}

export interface PointsLogItem {
    id: number
    user_id: number
    user_nickname: string
    points: number
    before_points: number
    after_points: number
    type: number
    type_text: string
    source: string
    remark: string
    operator_id: number | null
    operator_name: string | null
    created_at: string
}

// ========== SaaS 套餐 / 订阅 / 订单 (M3D) ==========

/**
 * 公开套餐信息（/tenantapi/subscription/plans 返回）
 */
export interface PlanInfo {
    id: number
    code: string
    name: string
    description: string
    price_monthly: number | string
    price_yearly: number | string
    storage_limit_bytes: number
    features: string[]
    sort: number
}

/**
 * 当前订阅记录（/tenantapi/subscription/current 返回的 subscription 字段）
 */
export interface SubscriptionInfo {
    id: number
    tenant_id: number
    plan_id: number
    type: number // 1=trial 2=formal 3=renew 4=upgrade
    starts_at: string
    ends_at: string
    status: number // 1=active 2=ended 3=refunded
    order_id: number
    operator_id: number
    remark: string
    created_at?: string
    updated_at?: string
}

/**
 * SaaS 订单（createOrder / pay / queryOrder 返回）
 */
export interface SaasOrderInfo {
    id: number
    order_no: string
    tenant_id: number
    plan_id: number
    type: number // 1=新购 2=续费 3=升级
    months: number
    amount: number | string
    paid_amount: number | string
    payment_channel: string // wechat / alipay
    payment_method: string // native / jsapi / page / wap / ...
    prepay_id: string
    transaction_id: string
    status: number // 1=待支付 2=已支付 3=已取消 4=已退款
    paid_at: string | null
    expired_at: string | null
    created_at?: string
    updated_at?: string
}

/**
 * POST /tenantapi/subscription/pay 返回的 payment_data 结构。
 * Driver 的 create() 返回，不同 channel 结构不同，前端通过 trade_type 区分。
 */
export interface PaymentDataWechat {
    trade_type: 'native'
    data: {
        code_url: string
        prepay_id?: string
    }
}
export interface PaymentDataAlipay {
    trade_type: 'page' | 'wap'
    data: {
        body: string // HTML 表单 / 跳转 URL
    }
}
export type PaymentData = PaymentDataWechat | PaymentDataAlipay

/**
 * POST /tenantapi/subscription/pay 完整响应
 */
export interface PaymentResponse {
    order: SaasOrderInfo
    payment_data: PaymentData
}
