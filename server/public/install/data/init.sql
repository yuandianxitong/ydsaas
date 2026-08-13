-- ============================================================
-- 元点Saas - 初始数据
-- ============================================================

-- 插入超级管理员角色
INSERT INTO `roles` (`tenant_id`, `id`, `name`, `title`, `description`, `data_scope`, `is_system`, `status`, `sort`, `created_at`, `updated_at`) VALUES
    (0, 1, 'super_admin', '超级管理员', '系统超级管理员，拥有所有权限', 1, 1, 1, 0, NOW(), NOW());

-- ============================================================
-- 权限数据
-- ============================================================
INSERT INTO `permissions` (`tenant_id`, `id`, `name`, `title`, `group`, `description`, `guard_name`, `status`, `sort`, `created_at`, `updated_at`) VALUES
    -- 系统管理（原有）
    (0, 1, 'system', '系统管理', '系统管理', '系统管理权限', 'admin', 1, 0, NOW(), NOW()),
    -- 管理员管理
    (0, 2, 'system.admin', '管理员管理', '系统管理', '管理员管理权限', 'admin', 1, 1, NOW(), NOW()),
    (0, 3, 'system.admin.list', '管理员列表', '系统管理', '查看管理员列表', 'admin', 1, 2, NOW(), NOW()),
    (0, 4, 'system.admin.create', '创建管理员', '系统管理', '创建新管理员', 'admin', 1, 3, NOW(), NOW()),
    (0, 5, 'system.admin.update', '编辑管理员', '系统管理', '编辑管理员信息', 'admin', 1, 4, NOW(), NOW()),
    (0, 6, 'system.admin.delete', '删除管理员', '系统管理', '删除管理员', 'admin', 1, 5, NOW(), NOW()),
    (0, 7, 'system.admin.status', '管理员状态', '系统管理', '修改管理员状态', 'admin', 1, 6, NOW(), NOW()),
    -- 角色管理
    (0, 8, 'system.role', '角色管理', '系统管理', '角色管理权限', 'admin', 1, 7, NOW(), NOW()),
    (0, 9, 'system.role.list', '角色列表', '系统管理', '查看角色列表', 'admin', 1, 8, NOW(), NOW()),
    (0, 10, 'system.role.create', '创建角色', '系统管理', '创建新角色', 'admin', 1, 9, NOW(), NOW()),
    (0, 11, 'system.role.update', '编辑角色', '系统管理', '编辑角色信息', 'admin', 1, 10, NOW(), NOW()),
    (0, 12, 'system.role.delete', '删除角色', '系统管理', '删除角色', 'admin', 1, 11, NOW(), NOW()),
    (0, 13, 'system.role.permission', '角色授权', '系统管理', '为角色分配权限', 'admin', 1, 12, NOW(), NOW()),
    -- 权限（保留 list 供角色分配树使用，CRUD 已迁移到 platform）
    (0, 14, 'system.permission', '权限管理', '系统管理', '权限管理', 'admin', 1, 13, NOW(), NOW()),
    (0, 15, 'system.permission.list', '权限列表', '系统管理', '查看权限列表', 'admin', 1, 14, NOW(), NOW()),
    -- 菜单管理
    (0, 19, 'system.menu', '菜单管理', '系统管理', '菜单管理权限', 'admin', 1, 18, NOW(), NOW()),
    (0, 20, 'system.menu.list', '菜单列表', '系统管理', '查看菜单列表', 'admin', 1, 19, NOW(), NOW()),
    (0, 21, 'system.menu.create', '创建菜单', '系统管理', '创建新菜单', 'admin', 1, 20, NOW(), NOW()),
    (0, 22, 'system.menu.update', '编辑菜单', '系统管理', '编辑菜单信息', 'admin', 1, 21, NOW(), NOW()),
    (0, 23, 'system.menu.delete', '删除菜单', '系统管理', '删除菜单', 'admin', 1, 22, NOW(), NOW()),
    -- 日志管理
    (0, 24, 'system.log', '日志管理', '系统管理', '日志管理权限', 'admin', 1, 23, NOW(), NOW()),
    (0, 25, 'system.log.login', '登录日志', '系统管理', '查看登录日志', 'admin', 1, 24, NOW(), NOW()),
    (0, 26, 'system.log.operation', '操作日志', '系统管理', '查看操作日志', 'admin', 1, 25, NOW(), NOW()),
    -- 部门管理
    (0, 27, 'system.department', '部门管理', '系统管理', '部门管理权限', 'admin', 1, 26, NOW(), NOW()),
    (0, 28, 'system.department.list', '部门列表', '系统管理', '查看部门列表', 'admin', 1, 27, NOW(), NOW()),
    (0, 29, 'system.department.create', '创建部门', '系统管理', '创建新部门', 'admin', 1, 28, NOW(), NOW()),
    (0, 30, 'system.department.update', '编辑部门', '系统管理', '编辑部门信息', 'admin', 1, 29, NOW(), NOW()),
    (0, 31, 'system.department.delete', '删除部门', '系统管理', '删除部门', 'admin', 1, 30, NOW(), NOW()),
    -- 数据字典
    (0, 32, 'system.dictionary', '数据字典', '系统管理', '数据字典管理权限', 'admin', 1, 31, NOW(), NOW()),
    (0, 33, 'system.dictionary.list', '字典列表', '系统管理', '查看字典列表', 'admin', 1, 32, NOW(), NOW()),
    (0, 34, 'system.dictionary.create', '创建字典', '系统管理', '创建数据字典', 'admin', 1, 33, NOW(), NOW()),
    (0, 35, 'system.dictionary.update', '编辑字典', '系统管理', '编辑数据字典', 'admin', 1, 34, NOW(), NOW()),
    (0, 36, 'system.dictionary.delete', '删除字典', '系统管理', '删除数据字典', 'admin', 1, 35, NOW(), NOW()),
    -- 文件管理
    (0, 37, 'system.file', '文件管理', '系统管理', '文件管理权限', 'admin', 1, 36, NOW(), NOW()),
    (0, 38, 'system.file.list', '文件列表', '系统管理', '查看文件列表', 'admin', 1, 37, NOW(), NOW()),
    (0, 39, 'system.file.delete', '删除文件', '系统管理', '删除文件', 'admin', 1, 38, NOW(), NOW()),
    (0, 192, 'system.file.update', '编辑文件', '系统管理', '重命名/移动文件', 'admin', 1, 44, NOW(), NOW()),
    (0, 193, 'system.file-category.create', '新建文件分类', '系统管理', '创建素材分类', 'admin', 1, 45, NOW(), NOW()),
    (0, 194, 'system.file-category.update', '编辑文件分类', '系统管理', '重命名素材分类', 'admin', 1, 46, NOW(), NOW()),
    (0, 195, 'system.file-category.delete', '删除文件分类', '系统管理', '删除素材分类', 'admin', 1, 47, NOW(), NOW()),
    -- 通知管理
    (0, 40, 'system.notification', '通知管理', '系统管理', '通知管理权限', 'admin', 1, 39, NOW(), NOW()),
    (0, 41, 'system.notification.list', '通知列表', '系统管理', '查看通知列表', 'admin', 1, 40, NOW(), NOW()),
    (0, 42, 'system.notification.create', '发布通知', '系统管理', '发布系统通知', 'admin', 1, 41, NOW(), NOW()),
    (0, 43, 'system.notification.update', '编辑通知', '系统管理', '编辑通知内容', 'admin', 1, 42, NOW(), NOW()),
    (0, 44, 'system.notification.delete', '删除通知', '系统管理', '删除通知', 'admin', 1, 43, NOW(), NOW()),
    -- 系统配置
    (0, 51, 'system.config', '系统配置', '系统管理', '系统配置管理权限', 'admin', 1, 50, NOW(), NOW()),
    (0, 52, 'system.config.list', '配置列表', '系统管理', '查看系统配置', 'admin', 1, 51, NOW(), NOW()),
    (0, 53, 'system.config.update', '修改配置', '系统管理', '修改系统配置', 'admin', 1, 52, NOW(), NOW()),
    -- 消息管理（系统管理子模块）
    (0, 60, 'system.message', '消息管理', '系统管理', '消息管理权限', 'admin', 1, 60, NOW(), NOW()),
    (0, 61, 'system.message.template', '消息模板', '系统管理', '消息模板管理', 'admin', 1, 61, NOW(), NOW()),
    (0, 62, 'system.message.template.list', '模板列表', '系统管理', '查看消息模板列表', 'admin', 1, 62, NOW(), NOW()),
    (0, 63, 'system.message.template.create', '创建模板', '系统管理', '创建消息模板', 'admin', 1, 63, NOW(), NOW()),
    (0, 64, 'system.message.template.update', '编辑模板', '系统管理', '编辑消息模板', 'admin', 1, 64, NOW(), NOW()),
    (0, 65, 'system.message.template.delete', '删除模板', '系统管理', '删除消息模板', 'admin', 1, 65, NOW(), NOW()),
    (0, 66, 'system.message.log', '发送记录', '系统管理', '查看发送记录', 'admin', 1, 66, NOW(), NOW()),
    -- 渠道管理
    (0, 70, 'channel', '渠道管理', '渠道管理', '渠道管理权限', 'admin', 1, 70, NOW(), NOW()),
    (0, 71, 'channel.official', '公众号管理', '渠道管理', '公众号管理权限', 'admin', 1, 71, NOW(), NOW()),
    (0, 72, 'channel.official.config', '公众号配置', '渠道管理', '公众号配置管理', 'admin', 1, 72, NOW(), NOW()),
    (0, 73, 'channel.official.menu', '自定义菜单', '渠道管理', '公众号菜单管理', 'admin', 1, 73, NOW(), NOW()),
    (0, 74, 'channel.official.auto_reply', '自动回复', '渠道管理', '公众号自动回复管理', 'admin', 1, 74, NOW(), NOW()),
    (0, 75, 'channel.miniapp', '小程序管理', '渠道管理', '小程序管理权限', 'admin', 1, 75, NOW(), NOW()),
    (0, 76, 'channel.miniapp.config', '小程序配置', '渠道管理', '小程序配置管理', 'admin', 1, 76, NOW(), NOW()),
    -- 反馈管理
    (0, 93, 'feedback', '反馈管理', '内容管理', '反馈管理权限', 'admin', 1, 93, NOW(), NOW()),
    (0, 94, 'feedback.list', '反馈列表', '内容管理', '查看反馈列表', 'admin', 1, 94, NOW(), NOW()),
    (0, 96, 'feedback.reply', '回复反馈', '内容管理', '回复用户反馈', 'admin', 1, 96, NOW(), NOW()),
    (0, 97, 'feedback.close', '关闭反馈', '内容管理', '关闭反馈', 'admin', 1, 97, NOW(), NOW()),
    (0, 98, 'feedback.delete', '删除反馈', '内容管理', '删除反馈', 'admin', 1, 98, NOW(), NOW()),
    -- 用户管理
    (0, 150, 'user', '用户管理', '用户管理', '用户管理权限', 'admin', 1, 150, NOW(), NOW()),
    (0, 151, 'user.list', '用户列表', '用户管理', '查看用户列表', 'admin', 1, 151, NOW(), NOW()),
    (0, 152, 'user.detail', '用户详情', '用户管理', '查看用户详情', 'admin', 1, 152, NOW(), NOW()),
    (0, 153, 'user.adjust-balance', '调整余额', '用户管理', '调整用户余额', 'admin', 1, 153, NOW(), NOW()),
    (0, 154, 'user.adjust-points', '调整积分', '用户管理', '调整用户积分', 'admin', 1, 154, NOW(), NOW()),
    (0, 155, 'user.status', '用户状态', '用户管理', '启用/禁用用户', 'admin', 1, 155, NOW(), NOW()),
    (0, 156, 'user.balance-logs', '余额记录', '用户管理', '查看余额记录', 'admin', 1, 156, NOW(), NOW()),
    (0, 157, 'user.points-logs', '积分记录', '用户管理', '查看积分记录', 'admin', 1, 157, NOW(), NOW()),
    -- 开放平台
    (0, 160, 'channel.open', '开放平台', '渠道管理', '开放平台管理权限', 'admin', 1, 160, NOW(), NOW()),
    (0, 161, 'channel.open.config', '开放平台配置', '渠道管理', '开放平台配置管理', 'admin', 1, 161, NOW(), NOW()),
    -- 移动端配置（v2.4.0 Phase B）
    (0, 170, 'mobile.config.view',   '查看移动端配置', '系统管理', '查看移动端配置', 'admin', 1, 170, NOW(), NOW()),
    (0, 171, 'mobile.config.update', '修改移动端配置', '系统管理', '保存移动端配置', 'admin', 1, 171, NOW(), NOW()),
    -- PC 端配置（v2.27.0）
    (0, 187, 'pc.config.view',   '查看PC端配置', '系统管理', '查看PC端配置', 'admin', 1, 187, NOW(), NOW()),
    (0, 188, 'pc.config.update', '修改PC端配置', '系统管理', '保存PC端配置', 'admin', 1, 188, NOW(), NOW()),
    -- 移动端打包发布（v2.27.1：补齐 MobileBuildController 对应权限）
    (0, 189, 'mobile.build.view',    '查看打包记录', '系统管理', '查看移动端打包记录', 'admin', 1, 189, NOW(), NOW()),
    (0, 190, 'mobile.build.create',  '发起打包',     '系统管理', '发起移动端打包构建', 'admin', 1, 190, NOW(), NOW()),
    (0, 191, 'mobile.build.release', '发布应用',     '系统管理', '发布H5/上传小程序',  'admin', 1, 191, NOW(), NOW()),
    -- 装修（v2.11.0）
    (0, 172, 'diy.home.view',    '查看首页装修', '装修', '查看首页装修配置', 'admin', 1, 172, NOW(), NOW()),
    (0, 173, 'diy.home.save',    '保存首页装修', '装修', '保存首页装修草稿', 'admin', 1, 173, NOW(), NOW()),
    (0, 174, 'diy.home.publish', '发布首页装修', '装修', '发布首页装修',     'admin', 1, 174, NOW(), NOW()),
    -- 装修版本（v2.12.0 C2）
    (0, 175, 'diy.home.version.view',    '查看装修版本', '装修', '查看首页装修历史版本', 'admin', 1, 175, NOW(), NOW()),
    (0, 176, 'diy.home.version.restore', '回滚装修版本', '装修', '回滚首页装修历史版本', 'admin', 1, 176, NOW(), NOW()),
    (0, 177, 'diy.page.view',    '查看自定义页', '装修', '查看自定义页面列表/草稿', 'admin', 1, 177, NOW(), NOW()),
    (0, 178, 'diy.page.create',  '创建自定义页', '装修', '创建自定义页面',         'admin', 1, 178, NOW(), NOW()),
    (0, 179, 'diy.page.update',  '编辑自定义页', '装修', '改名/改标识/启停',       'admin', 1, 179, NOW(), NOW()),
    (0, 180, 'diy.page.delete',  '删除自定义页', '装修', '删除自定义页面',         'admin', 1, 180, NOW(), NOW()),
    (0, 181, 'diy.page.save',    '保存自定义页', '装修', '保存自定义页草稿/回滚',  'admin', 1, 181, NOW(), NOW()),
    (0, 182, 'diy.page.publish', '发布自定义页', '装修', '发布自定义页',           'admin', 1, 182, NOW(), NOW()),
    -- 链接库（v2.26.0）
    (0, 183, 'diy.link.list',   '查看链接库', '装修', '查看装修链接库', 'admin', 1, 183, NOW(), NOW()),
    (0, 184, 'diy.link.create', '新增链接',   '装修', '新增装修链接',   'admin', 1, 184, NOW(), NOW()),
    (0, 185, 'diy.link.update', '编辑链接',   '装修', '编辑装修链接',   'admin', 1, 185, NOW(), NOW()),
    (0, 186, 'diy.link.delete', '删除链接',   '装修', '删除装修链接',   'admin', 1, 186, NOW(), NOW());

-- ============================================================
-- 菜单数据
-- ============================================================
INSERT INTO `menus` (`tenant_id`, `id`, `parent_id`, `type`, `title`, `name`, `path`, `component`, `redirect`, `icon`, `permission`, `is_hidden`, `is_cache`, `is_affix`, `is_iframe`, `external_link`, `breadcrumb`, `active_menu`, `meta`, `status`, `sort`, `created_at`, `updated_at`) VALUES
  -- 控制台
  (0, 1, 0, 2, '首页', 'Workbench', '/workbench', 'workbench/index', NULL, 'i-svg:gauge', NULL, 0, 1, 1, 0, NULL, 1, NULL, NULL, 1, 0, NOW(), NOW()),

  -- ===== 系统管理（v2.21.0：改名为「设置」） =====
  (0, 2, 0, 1, '设置', 'System', '/system', 'LAYOUT', NULL, 'i-svg:settings', 'system', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 900, NOW(), NOW()),

  -- 设置 二级分组目录（v2.21.0）
  (0, 200, 2, 1, '系统设置', 'SettingsSystem', '', NULL, NULL, 'i-svg:settings',     NULL, 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 210, 2, 1, '权限',     'SettingsPerm',   '', NULL, NULL, 'i-svg:lock',         NULL, 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 220, 2, 1, '其他',     'SettingsOther',  '', NULL, NULL, 'i-svg:layout-list',  NULL, 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 管理员管理（v2.7.9：sort 1→3；v2.21.0：parent_id=2→210）
  (0, 10, 210, 2, '管理员管理', 'SystemAdmin', '/system/admin', '/system/admin/index', NULL, 'i-svg:user', 'system.admin.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (0, 11, 10, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.admin.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 12, 10, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.admin.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 13, 10, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.admin.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (0, 14, 10, 3, '状态', NULL, NULL, NULL, NULL, NULL, 'system.admin.status', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),

  -- 角色管理（v2.7.9：sort 2→4；v2.21.0：parent_id=2→210）
  (0, 20, 210, 2, '角色管理', 'SystemRole', '/system/role', '/system/role/index', NULL, 'i-svg:users', 'system.role.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (0, 21, 20, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.role.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 22, 20, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.role.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 23, 20, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.role.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (0, 24, 20, 3, '授权', NULL, NULL, NULL, NULL, NULL, 'system.role.permission', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (0, 25, 20, 3, '状态', NULL, NULL, NULL, NULL, NULL, 'system.role.status', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),

  -- 部门管理（v2.7.9：sort 3→5；v2.21.0：parent_id=2→210）
  (0, 30, 210, 2, '部门管理', 'SystemDepartment', '/system/department', '/system/department/index', NULL, 'i-svg:network', 'system.department.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),
  (0, 31, 30, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.department.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 32, 30, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.department.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 33, 30, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.department.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 菜单管理（v2.7.9：sort 5→6；v2.21.0：parent_id=2→200）
  (0, 50, 200, 2, '菜单管理', 'SystemMenu', '/system/menu', '/system/menu/index', NULL, 'i-svg:layout-grid', 'system.menu.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 6, NOW(), NOW()),
  (0, 51, 50, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.menu.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 52, 50, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.menu.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 53, 50, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.menu.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 数据字典（v2.7.9：sort 6→7；v2.21.0：parent_id=2→200）
  (0, 60, 200, 2, '数据字典', 'SystemDictionary', '/system/dictionary', '/system/dictionary/index', NULL, 'i-svg:library-big', 'system.dictionary.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 7, NOW(), NOW()),
  (0, 61, 60, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.dictionary.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 62, 60, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.dictionary.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 63, 60, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.dictionary.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 文件管理（v2.7.9：sort 7→8；v2.21.0：parent_id=2→200）
  (0, 70, 200, 2, '文件管理', 'SystemFile', '/system/file', '/system/file/index', NULL, 'i-svg:folder-open', 'system.file.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 8, NOW(), NOW()),
  (0, 72, 70, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.file.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 71, 70, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.file.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 1251, 70, 3, '新建分类', NULL, NULL, NULL, NULL, NULL, 'system.file-category.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (0, 1252, 70, 3, '编辑分类', NULL, NULL, NULL, NULL, NULL, 'system.file-category.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (0, 1253, 70, 3, '删除分类', NULL, NULL, NULL, NULL, NULL, 'system.file-category.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),

  -- 通知管理（v2.7.9：sort 8→9；v2.21.0：parent_id=2→220）
  (0, 80, 220, 2, '通知管理', 'SystemNotification', '/system/notification', '/system/notification/index', NULL, 'i-svg:bell', 'system.notification.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 9, NOW(), NOW()),
  (0, 81, 80, 3, '发布', NULL, NULL, NULL, NULL, NULL, 'system.notification.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 82, 80, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.notification.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 83, 80, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.notification.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 系统配置（v2.7.9：sort 10→1；v2.21.0：parent_id=2→200）
  (0, 100, 200, 2, '系统配置', 'SystemConfig', '/system/config', '/system/config/index', NULL, 'i-svg:cog', 'system.config.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 101, 100, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.config.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),

  -- 日志管理（目录）（v2.7.9：sort 11→10）
  (0, 110, 2, 1, '日志管理', 'SystemLog', '/system/log', 'LAYOUT', NULL, 'i-svg:scroll-text', 'system.log', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 10, NOW(), NOW()),
  (0, 111, 110, 2, '登录日志', 'SystemLoginLog', '/system/log/login', '/system/log/login', NULL, NULL, 'system.log.login', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 112, 110, 2, '操作日志', 'SystemOperationLog', '/system/log/operation', '/system/log/operation', NULL, NULL, 'system.log.operation', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 113, 110, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.log.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (0, 114, 110, 3, '清空', NULL, NULL, NULL, NULL, NULL, 'system.log.clear', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),

  -- ===== 消息管理（系统管理子模块）（v2.7.9：sort 12→11） =====
  (0, 120, 2, 1, '消息管理', 'SystemMessage', '/system/message', 'LAYOUT', '/system/message/template', 'i-svg:message-circle-more', 'system.message', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 11, NOW(), NOW()),
  (0, 121, 120, 2, '消息模板', 'SystemMessageTemplate', '/system/message/template', '/system/message/template/index', NULL, 'el-icon-Tickets', 'system.message.template.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 122, 120, 2, '发送记录', 'SystemMessageLog', '/system/message/log', '/system/message/log/index', NULL, 'el-icon-List', 'system.message.log.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 123, 121, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'system.message.template.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 124, 121, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'system.message.template.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 125, 121, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'system.message.template.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (0, 126, 121, 3, '发送测试', NULL, NULL, NULL, NULL, NULL, 'system.message.template.send', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),

  -- ===== 渠道管理 =====
  (0, 4, 0, 1, '渠道', 'Channel', '/channel', 'LAYOUT', '/channel/official/config', 'i-svg:send', 'channel', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 700, NOW(), NOW()),

  -- 公众号（目录）
  (0, 5, 4, 1, '公众号', 'ChannelOfficial', '/channel/official', 'LAYOUT', '/channel/official/config', 'i-svg:compass', 'channel.official', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 400, 5, 2, '公众号配置', 'ChannelOfficialConfig', '/channel/official/config', '/channel/official/config', NULL, 'el-icon-Setting', 'channel.official.config', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 401, 400, 3, '发送模板', NULL, NULL, NULL, NULL, NULL, 'channel.official.config.send', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 410, 5, 2, '自定义菜单', 'ChannelOfficialMenu', '/channel/official/menu', '/channel/official/menu', NULL, 'el-icon-Grid', 'channel.official.menu', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 411, 410, 3, '创建', NULL, NULL, NULL, NULL, NULL, 'channel.official.menu.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 412, 410, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'channel.official.menu.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 420, 5, 2, '自动回复', 'ChannelAutoReply', '/channel/official/auto-reply', '/channel/official/auto-reply', NULL, 'el-icon-ChatSquare', 'channel.official.auto_reply', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (0, 421, 420, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'channel.official.auto_reply.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 422, 420, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'channel.official.auto_reply.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 423, 420, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'channel.official.auto_reply.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- 小程序（目录）
  (0, 6, 4, 1, '小程序', 'ChannelMiniApp', '/channel/miniapp', 'LAYOUT', '/channel/miniapp/config', 'i-svg:smartphone', 'channel.miniapp', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 500, 6, 2, '小程序配置', 'ChannelMiniAppConfig', '/channel/miniapp/config', '/channel/miniapp/config', NULL, 'el-icon-Setting', 'channel.miniapp.config', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),

  -- ===== 内容管理 =====
  (0, 720, 220, 2, '反馈管理', 'SystemFeedback', '/system/feedback', '/system/feedback/index', NULL, 'i-svg:message-square-text', 'feedback.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 10, NOW(), NOW()),
  (0, 721, 720, 3, '回复', NULL, NULL, NULL, NULL, NULL, 'feedback.reply', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 722, 720, 3, '关闭', NULL, NULL, NULL, NULL, NULL, 'feedback.close', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 723, 720, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'feedback.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  -- ===== 会员管理（v2.21.0：「用户」改名「会员」） =====
  (0, 9, 0, 1, '会员', 'User', '/user', 'LAYOUT', '/user/user', 'i-svg:users', 'user', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 500, NOW(), NOW()),

  -- 会员 二级分组目录（v2.21.0）
  (0, 930, 9, 1, '会员管理', 'MemberManage', '', NULL, NULL, 'i-svg:user',   NULL, 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 940, 9, 1, '资产记录', 'MemberAssets', '', NULL, NULL, 'i-svg:wallet', NULL, 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),

  -- 会员列表（v2.21.0：title「用户列表」→「会员列表」，parent_id=9→930）
  (0, 900, 930, 2, '会员列表', 'UserList', '/user/user', '/user/user/index', NULL, 'i-svg:user', 'user.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 901, 900, 3, '查看详情', NULL, NULL, NULL, NULL, NULL, 'user.detail', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 902, 900, 3, '调整余额', NULL, NULL, NULL, NULL, NULL, 'user.adjust-balance', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 903, 900, 3, '调整积分', NULL, NULL, NULL, NULL, NULL, 'user.adjust-points', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (0, 904, 900, 3, '更新状态', NULL, NULL, NULL, NULL, NULL, 'user.status', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  -- 余额记录（v2.21.0：parent_id=9→940）
  (0, 910, 940, 2, '余额记录', 'UserBalanceLog', '/user/balance-log', '/user/balance-log/index', NULL, 'i-svg:wallet', 'user.balance-logs', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  -- 积分记录（v2.21.0：parent_id=9→940）
  (0, 920, 940, 2, '积分记录', 'UserPointsLog', '/user/points-log', '/user/points-log/index', NULL, 'i-svg:star', 'user.points-logs', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),

  -- ===== 开放平台（渠道管理子菜单） =====
  (0, 15, 4, 1, '开放平台', 'ChannelOpen', '/channel/open', 'LAYOUT', '/channel/open/config', 'i-svg:globe', 'channel.open', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (0, 550, 15, 2, '开放平台配置', 'ChannelOpenConfig', '/channel/open/config', '/channel/open/config', NULL, 'el-icon-Setting', 'channel.open.config', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),

  -- ===== 插件中心（v2.22.0：插件管理 / 插件市场 两组，下线 应用展示页） =====
  (0, 1000, 0,    1, '插件',     'TenantPlugin',     '/plugin',           'LAYOUT', '/plugin/installed', 'i-svg:plug',        'plugin.list',       0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 550, NOW(), NOW()),
  -- 分组目录（空 path，靠 MenuRepository 合成 /dir-<id> 渲染）
  (0, 1240, 1000, 1, '插件管理', 'PluginManage',     '',                  NULL,     NULL,                'i-svg:box',         NULL,                0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 10,  NOW(), NOW()),
  (0, 1241, 1000, 1, '插件市场', 'PluginMarket',     '',                  NULL,     NULL,                'i-svg:boxes',       NULL,                0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 20,  NOW(), NOW()),
  -- 插件管理叶子
  (0, 1242, 1240, 2, '已安装',   'PluginInstalled',  '/plugin/installed', 'plugin/installed', NULL,       'i-svg:plug',        'plugin.list',       0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1,   NOW(), NOW()),
  (0, 1243, 1240, 2, '可用插件', 'PluginAvailable',  '/plugin/available', 'plugin/available', NULL,       'i-svg:unplug',      'plugin.list',       0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2,   NOW(), NOW()),
  (0, 1244, 1240, 2, '即将到期', 'PluginExpiring',   '/plugin/expiring',  'plugin/expiring',  NULL,       'i-svg:bell-ring',   'plugin.list',       0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3,   NOW(), NOW()),
  -- 插件市场叶子
  (0, 1245, 1241, 2, '插件市场', 'PluginMarketGrid', '/plugin/market',    'plugin/market',    NULL,       'i-svg:layout-grid', 'plugin.list',       0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1,   NOW(), NOW()),
  (0, 1246, 1241, 2, '购买记录', 'PluginOrders',     '/plugin/orders',    'plugin/orders',    NULL,       'i-svg:receipt-text','plugin.order.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2,   NOW(), NOW()),
  -- 动作权限节点（type=3，不可见；reparent 到 插件管理 1240）
  (0, 1001, 1240, 3, '启用插件', 'TenantPluginEnable',   '', '', NULL, '', 'plugin.enable',        0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 1002, 1240, 3, '禁用插件', 'TenantPluginDisable',  '', '', NULL, '', 'plugin.disable',       0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 1003, 1240, 3, '查看配置', 'TenantPluginCfgGet',   '', '', NULL, '', 'plugin.config.get',    0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (0, 1004, 1240, 3, '更新配置', 'TenantPluginCfgSet',   '', '', NULL, '', 'plugin.config.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (0, 1005, 1240, 3, '购买插件', 'TenantPluginPurchase', '', '', NULL, '', 'plugin.purchase',      0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),
  -- 导入演示数据（v2.28.0：挂「已安装」1242 下，仅已启用插件可用）
  (0, 1250, 1242, 3, '导入演示数据', NULL, NULL, NULL, NULL, NULL, 'plugin.testdata', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 6, NOW(), NOW()),

  -- ===== 装修（v2.11.0）=====
  (0, 1200, 0,    1, '装修',     'Diy',             '/diy',        'LAYOUT', '/diy/home', 'i-svg:paint-roller', 'diy.home.view',      0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 800, NOW(), NOW()),
  -- 二级分组目录（空 path → 前端合成 /dir-<id>，作为 sub-sidebar 分组标题）
  (0, 1220, 1200, 1, '页面装修', 'DiyPageGroup',    '',            NULL,     NULL,        'i-svg:layout-grid',  NULL,                 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1,   NOW(), NOW()),
  (0, 1221, 1200, 1, '发布管理', 'DiyPublishGroup', '',            NULL,     NULL,        'i-svg:rocket',       NULL,                 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2,   NOW(), NOW()),
  (0, 1201, 1220, 2, '页面装修', 'DiyHome',   '/diy/home',    'diy/decorate-list',   NULL,             'i-svg:house',        'diy.home.view',      0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1,   NOW(), NOW()),
  (0, 1234, 1220, 2, '启动与首页', 'DiyLaunch', '/diy/launch', 'diy/launch', NULL,             'i-svg:rocket',       'mobile.config.view', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2,   NOW(), NOW()),
  (0, 1202, 1220, 2, '底部导航', 'DiyTabbar', '/diy/tabbar',  'diy/tabbar', NULL,             'i-svg:layout-list',  'mobile.config.view', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4,   NOW(), NOW()),
  (0, 1203, 1220, 2, '主题风格', 'DiyTheme',  '/diy/theme',   'diy/theme',  NULL,             'i-svg:palette',      'mobile.config.view', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5,   NOW(), NOW()),
  (0, 1204, 1221, 2, '基础设置', 'DiyBasic',  '/diy/basic',   'diy/basic',  NULL,             'i-svg:cog',          'mobile.config.view', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1,   NOW(), NOW()),
  (0, 1205, 1221, 2, '打包发布', 'DiyBuild',  '/diy/build',   'diy/build',  NULL,             'i-svg:monitor',      'mobile.config.view', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2,   NOW(), NOW()),
  (0, 1218, 1221, 2, 'PC端配置', 'DiyPcConfig', '/diy/pc',    'diy/pc',     NULL,             'i-svg:monitor',      'pc.config.view',     0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3,   NOW(), NOW()),
  -- 装修写权限按钮（type 3）：非超管角色通过 role_menus JOIN menus 获得权限
  (0, 1206, 1201, 3, '保存',   NULL, NULL, NULL, NULL, NULL, 'diy.home.save',              0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 1207, 1201, 3, '发布',   NULL, NULL, NULL, NULL, NULL, 'diy.home.publish',           0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 1209, 1201, 3, '版本列表', NULL, NULL, NULL, NULL, NULL, 'diy.home.version.view',    0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (0, 1210, 1201, 3, '回滚版本', NULL, NULL, NULL, NULL, NULL, 'diy.home.version.restore', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (0, 1235, 1234, 3, '保存',   NULL, NULL, NULL, NULL, NULL, 'mobile.config.update',       0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 1208, 1204, 3, '保存',   NULL, NULL, NULL, NULL, NULL, 'mobile.config.update',       0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  -- 打包发布按钮权限（v2.27.1：补齐 mobile.build.*，此前非超管角色无法被授权调用打包接口）
  (0, 1247, 1205, 3, '查看构建', NULL, NULL, NULL, NULL, NULL, 'mobile.build.view',        0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 1248, 1205, 3, '发起构建', NULL, NULL, NULL, NULL, NULL, 'mobile.build.create',      0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 1249, 1205, 3, '发布应用', NULL, NULL, NULL, NULL, NULL, 'mobile.build.release',     0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (0, 1219, 1218, 3, '保存',   NULL, NULL, NULL, NULL, NULL, 'pc.config.update',           0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  -- 自定义页面（v2.15.0 C3c）
  (0, 1211, 1220, 2, '自定义页面', 'DiyPages', '/diy/pages', 'diy/pages', NULL, 'i-svg:layout-list', 'diy.page.view', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (0, 1212, 1211, 3, '创建', NULL, NULL, NULL, NULL, NULL, 'diy.page.create',  0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 1213, 1211, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'diy.page.update',  0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 1214, 1211, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'diy.page.delete',  0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW()),
  (0, 1215, 1211, 3, '保存', NULL, NULL, NULL, NULL, NULL, 'diy.page.save',    0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 4, NOW(), NOW()),
  (0, 1216, 1211, 3, '发布', NULL, NULL, NULL, NULL, NULL, 'diy.page.publish', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 5, NOW(), NOW()),
  -- 链接管理（装修链接库，v2.26.0）
  (0, 1230, 1220, 2, '链接管理', 'DiyLinks', '/diy/links', 'diy/links', NULL, 'i-svg:link', 'diy.link.list', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 6, NOW(), NOW()),
  (0, 1231, 1230, 3, '新增', NULL, NULL, NULL, NULL, NULL, 'diy.link.create', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 1, NOW(), NOW()),
  (0, 1232, 1230, 3, '编辑', NULL, NULL, NULL, NULL, NULL, 'diy.link.update', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 2, NOW(), NOW()),
  (0, 1233, 1230, 3, '删除', NULL, NULL, NULL, NULL, NULL, 'diy.link.delete', 0, 1, 0, 0, NULL, 1, NULL, NULL, 1, 3, NOW(), NOW());

-- ============================================================
-- 为超级管理员角色分配所有权限和菜单
-- ============================================================
INSERT INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`, `created_at`, `updated_at`)
SELECT 0, 1, id, NOW(), NOW() FROM `permissions`;

INSERT INTO `role_menus` (`tenant_id`, `role_id`, `menu_id`, `created_at`, `updated_at`)
SELECT 0, 1, id, NOW(), NOW() FROM `menus`;

-- ============================================================
-- 数据字典初始数据
-- ============================================================
INSERT INTO `dictionaries` (`tenant_id`, `name`, `code`, `description`, `status`, `sort`, `created_at`, `updated_at`) VALUES
(0, '性别', 'gender', '用户性别', 1, 0, NOW(), NOW()),
(0, '状态', 'common_status', '通用启用/禁用状态', 1, 1, NOW(), NOW());

INSERT INTO `dictionary_items` (`tenant_id`, `dictionary_id`, `label`, `value`, `tag_type`, `status`, `sort`, `created_at`, `updated_at`) VALUES
(0, 1, '男', '1', '', 1, 0, NOW(), NOW()),
(0, 1, '女', '2', '', 1, 1, NOW(), NOW()),
(0, 1, '未知', '0', 'info', 1, 2, NOW(), NOW()),
(0, 2, '启用', '1', 'success', 1, 0, NOW(), NOW()),
(0, 2, '禁用', '0', 'danger', 1, 1, NOW(), NOW());

-- ============================================================
-- 部门初始数据
-- ============================================================
INSERT INTO `departments` (`tenant_id`, `id`, `parent_id`, `name`, `code`, `leader`, `sort`, `status`, `created_at`, `updated_at`) VALUES
(0, 1, 0, '总公司', 'HQ', '管理员', 0, 1, NOW(), NOW()),
(0, 2, 1, '技术部', 'TECH', NULL, 1, 1, NOW(), NOW()),
(0, 3, 1, '市场部', 'MARKET', NULL, 2, 1, NOW(), NOW()),
(0, 4, 1, '财务部', 'FINANCE', NULL, 3, 1, NOW(), NOW()),
(0, 5, 2, '前端组', 'TECH-FE', NULL, 1, 1, NOW(), NOW()),
(0, 6, 2, '后端组', 'TECH-BE', NULL, 2, 1, NOW(), NOW());

-- ============================================================
-- 定时任务示例数据
-- ============================================================
INSERT INTO `cron_jobs` (`tenant_id`, `name`, `command`, `expression`, `description`, `status`, `created_at`, `updated_at`) VALUES
(0, '核心菜单同步', 'saas:menu-sync', '0 * * * *', '每小时按模板给存量租户补齐核心菜单/权限（指纹门控，模板未变更则空跑）', 1, NOW(), NOW());

-- ============================================================
-- 系统配置种子数据
-- ============================================================

INSERT INTO `system_configs` (`tenant_id`, `config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `config_options`, `config_depends`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES

-- ===== 基础配置 (0, basic) =====
(0, 'site_name', '元点Saas', 'basic', 'string', '网站名称', '显示在浏览器标题栏和系统Logo旁', NULL, NULL, 1, 1, NOW(), NOW()),
(0, 'site_url', 'http://localhost', 'basic', 'string', '网站地址', '网站访问地址，用于生成完整链接', NULL, NULL, 2, 1, NOW(), NOW()),
(0, 'site_logo', '/storage/uploads/images/logo.png', 'basic', 'file', '网站Logo', '建议尺寸 200x50，支持 PNG/SVG 格式', NULL, NULL, 3, 1, NOW(), NOW()),
(0, 'site_favicon', '/storage/uploads/images/favicon.ico', 'basic', 'file', '网站图标', '浏览器标签页图标，建议 32x32 ICO/PNG 格式', NULL, NULL, 4, 1, NOW(), NOW()),
(0, 'site_description', '一款通用的后台管理系统', 'basic', 'string', '网站描述', '用于SEO和网站简介', NULL, NULL, 5, 1, NOW(), NOW()),
(0, 'site_keywords', '后台管理,管理系统,Admin', 'basic', 'string', 'SEO关键词', '多个关键词用英文逗号分隔', NULL, NULL, 6, 1, NOW(), NOW()),
(0, 'site_icp', '', 'basic', 'string', 'ICP备案号', '如：京ICP备XXXXXXXX号', NULL, NULL, 7, 1, NOW(), NOW()),
(0, 'site_copyright', 'Copyright © 2024 Dev007. All rights reserved.', 'basic', 'string', '版权信息', '显示在页面底部的版权声明', NULL, NULL, 8, 1, NOW(), NOW()),
(0, 'site_phone', '', 'basic', 'string', '联系电话', '网站管理员联系电话', NULL, NULL, 9, 1, NOW(), NOW()),
(0, 'site_email', '', 'basic', 'string', '联系邮箱', '网站管理员联系邮箱', NULL, NULL, 10, 1, NOW(), NOW()),
(0, 'site_address', '', 'basic', 'string', '联系地址', '公司或团队地址', NULL, NULL, 11, 1, NOW(), NOW()),
(0, 'site_status', '1', 'basic', 'boolean', '网站开关', '关闭后前台将显示维护提示', NULL, NULL, 12, 1, NOW(), NOW()),
(0, 'site_close_tip', '网站维护中，请稍后再试...', 'basic', 'string', '关闭提示', '网站关闭时显示的提示信息', NULL, NULL, 13, 1, NOW(), NOW()),
(0, 'user_register', '1', 'basic', 'boolean', '开放注册', '是否允许新用户注册', NULL, NULL, 14, 1, NOW(), NOW()),
(0, 'login_captcha', '1', 'basic', 'boolean', '登录验证码', '登录时是否需要输入验证码', NULL, NULL, 15, 1, NOW(), NOW()),
(0, 'password_min_length', '6', 'basic', 'number', '密码最小长度', '用户密码最少字符数', NULL, NULL, 16, 1, NOW(), NOW()),
(0, 'login_max_retry', '5', 'basic', 'number', '登录失败上限', '连续登录失败后锁定账号的次数', NULL, NULL, 17, 1, NOW(), NOW()),
(0, 'login_lock_duration', '30', 'basic', 'number', '锁定时长(分钟)', '账号被锁定后的等待时间', NULL, NULL, 18, 1, NOW(), NOW()),

-- ===== 邮件配置 (0, email) =====
(0, 'smtp_host', '', 'email', 'string', 'SMTP服务器', '例如：smtp.qq.com、smtp.163.com', NULL, NULL, 1, 1, NOW(), NOW()),
(0, 'smtp_port', '465', 'email', 'number', 'SMTP端口', '常用端口：25(不加密)、465(SSL)、587(TLS)', NULL, NULL, 2, 1, NOW(), NOW()),
(0, 'smtp_user', '', 'email', 'string', 'SMTP用户名', '通常为发件人邮箱地址', NULL, NULL, 3, 1, NOW(), NOW()),
(0, 'smtp_pass', '', 'email', 'string', 'SMTP密码', 'SMTP授权码或密码', NULL, NULL, 4, 1, NOW(), NOW()),
(0, 'smtp_from_address', '', 'email', 'string', '发件人地址', '发件人邮箱地址', NULL, NULL, 5, 1, NOW(), NOW()),
(0, 'smtp_from_name', '元点Saas', 'email', 'string', '发件人名称', '收件人看到的发件人名称', NULL, NULL, 6, 1, NOW(), NOW()),
(0, 'smtp_encryption', 'ssl', 'email', 'select', '加密方式', '邮件传输加密方式', '{"ssl":"SSL","tls":"TLS","none":"不加密"}', NULL, 7, 1, NOW(), NOW()),
(0, 'email_test_address', '', 'email', 'string', '测试收件地址', '用于发送测试邮件的收件人地址', NULL, NULL, 8, 1, NOW(), NOW()),

-- ===== 短信配置 (0, sms) =====
(0, 'sms_driver', 'aliyun', 'sms', 'select', '短信服务商', '选择短信发送服务商', '{"aliyun":"阿里云","tencent":"腾讯云"}', NULL, 1, 1, NOW(), NOW()),
(0, 'sms_access_key', '', 'sms', 'string', 'AccessKey ID', '短信服务商提供的 AccessKey ID', NULL, NULL, 2, 1, NOW(), NOW()),
(0, 'sms_access_secret', '', 'sms', 'string', 'AccessKey Secret', '短信服务商提供的 AccessKey Secret', NULL, NULL, 3, 1, NOW(), NOW()),
(0, 'sms_sign_name', '', 'sms', 'string', '短信签名', '已审核通过的短信签名', NULL, NULL, 4, 1, NOW(), NOW()),

-- ===== 支付配置 (0, payment) =====
(0, 'pay_alipay_enabled', '0', 'payment', 'boolean', '启用支付宝', '是否开启支付宝支付', NULL, NULL, 1, 1, NOW(), NOW()),
(0, 'pay_alipay_app_id', '', 'payment', 'string', '支付宝AppID', '支付宝开放平台应用AppID', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 2, 1, NOW(), NOW()),
(0, 'pay_alipay_private_key', '', 'payment', 'string', '应用私钥', '支付宝应用私钥(RSA2)', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 3, 1, NOW(), NOW()),
(0, 'pay_alipay_public_key', '', 'payment', 'string', '支付宝公钥', '支付宝公钥', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 4, 1, NOW(), NOW()),
(0, 'pay_alipay_notify_url', '', 'payment', 'string', '异步通知地址', '支付宝异步回调通知URL', NULL, '{"field":"pay_alipay_enabled","value":"1"}', 5, 1, NOW(), NOW()),
(0, 'pay_wechat_enabled', '0', 'payment', 'boolean', '启用微信支付', '是否开启微信支付', NULL, NULL, 6, 1, NOW(), NOW()),
(0, 'pay_wechat_app_id', '', 'payment', 'string', '微信AppID', '微信公众号或小程序AppID', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 7, 1, NOW(), NOW()),
(0, 'pay_wechat_mch_id', '', 'payment', 'string', '微信商户号', '微信支付商户号', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 8, 1, NOW(), NOW()),
(0, 'pay_wechat_api_key', '', 'payment', 'string', '微信API密钥', '微信支付APIv3密钥', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 9, 1, NOW(), NOW()),
(0, 'pay_wechat_api_v3_key', '', 'payment', 'string', '微信APIv3密钥', '微信支付APIv3密钥（用于V3接口）', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 10, 1, NOW(), NOW()),
(0, 'pay_wechat_serial_no', '', 'payment', 'string', '微信证书序列号', '微信支付平台证书序列号', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 11, 1, NOW(), NOW()),
(0, 'pay_wechat_private_key_path', '', 'payment', 'string', '微信私钥文件', '商户API私钥文件路径（apiclient_key.pem）', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 12, 1, NOW(), NOW()),
(0, 'pay_wechat_cert_path', '', 'payment', 'string', '微信证书文件', '商户API证书文件路径（apiclient_cert.pem）', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 13, 1, NOW(), NOW()),
(0, 'pay_wechat_notify_url', '/api/payment/notify/wechat', 'payment', 'string', '异步通知地址', '微信支付异步回调通知URL（相对路径会自动补全域名）', NULL, '{"field":"pay_wechat_enabled","value":"1"}', 14, 1, NOW(), NOW()),

-- ===== 存储配置 (0, storage) =====
(0, 'storage_driver', 'local', 'storage', 'select', '存储方式', '选择文件存储方式', '{"local":"本地存储","aliyun":"阿里云OSS","tencent":"腾讯云COS","qiniu":"七牛云"}', NULL, 1, 1, NOW(), NOW()),
(0, 'storage_upload_max_size', '10', 'storage', 'number', '最大上传(MB)', '单个文件最大上传大小，单位MB', NULL, NULL, 2, 1, NOW(), NOW()),
(0, 'storage_upload_allowed_ext', 'jpg,jpeg,png,gif,svg,webp,bmp,doc,docx,xls,xlsx,ppt,pptx,pdf,zip,rar,txt,csv', 'storage', 'string', '允许的文件类型', '允许上传的文件扩展名，英文逗号分隔', NULL, NULL, 3, 1, NOW(), NOW()),
(0, 'storage_image_max_size', '5', 'storage', 'number', '图片最大(MB)', '单张图片最大上传大小，单位MB', NULL, NULL, 4, 1, NOW(), NOW()),
-- 阿里云 OSS
(0, 'storage_oss_access_key', '', 'storage', 'string', 'OSS AccessKey', '阿里云OSS AccessKey ID', NULL, '{"field":"storage_driver","value":"aliyun"}', 10, 1, NOW(), NOW()),
(0, 'storage_oss_access_secret', '', 'storage', 'string', 'OSS AccessSecret', '阿里云OSS AccessKey Secret', NULL, '{"field":"storage_driver","value":"aliyun"}', 11, 1, NOW(), NOW()),
(0, 'storage_oss_bucket', '', 'storage', 'string', 'OSS Bucket', '阿里云OSS Bucket名称', NULL, '{"field":"storage_driver","value":"aliyun"}', 12, 1, NOW(), NOW()),
(0, 'storage_oss_endpoint', '', 'storage', 'string', 'OSS Endpoint', '阿里云OSS 访问域名，如 oss-cn-hangzhou.aliyuncs.com', NULL, '{"field":"storage_driver","value":"aliyun"}', 13, 1, NOW(), NOW()),
(0, 'storage_oss_domain', '', 'storage', 'string', 'OSS 自定义域名', '绑定的自定义域名，用于生成访问URL', NULL, '{"field":"storage_driver","value":"aliyun"}', 14, 1, NOW(), NOW()),
-- 腾讯云 COS
(0, 'storage_cos_secret_id', '', 'storage', 'string', 'COS SecretId', '腾讯云COS SecretId', NULL, '{"field":"storage_driver","value":"tencent"}', 20, 1, NOW(), NOW()),
(0, 'storage_cos_secret_key', '', 'storage', 'string', 'COS SecretKey', '腾讯云COS SecretKey', NULL, '{"field":"storage_driver","value":"tencent"}', 21, 1, NOW(), NOW()),
(0, 'storage_cos_bucket', '', 'storage', 'string', 'COS Bucket', '腾讯云COS Bucket名称（含AppId后缀，如 bucket-1250000000）', NULL, '{"field":"storage_driver","value":"tencent"}', 22, 1, NOW(), NOW()),
(0, 'storage_cos_region', '', 'storage', 'string', 'COS Region', '腾讯云COS 地域，如 ap-guangzhou', NULL, '{"field":"storage_driver","value":"tencent"}', 23, 1, NOW(), NOW()),
(0, 'storage_cos_domain', '', 'storage', 'string', 'COS 自定义域名', '绑定的自定义域名，用于生成访问URL', NULL, '{"field":"storage_driver","value":"tencent"}', 24, 1, NOW(), NOW()),
-- 七牛云
(0, 'storage_qiniu_access_key', '', 'storage', 'string', '七牛 AccessKey', '七牛云 AccessKey', NULL, '{"field":"storage_driver","value":"qiniu"}', 30, 1, NOW(), NOW()),
(0, 'storage_qiniu_secret_key', '', 'storage', 'string', '七牛 SecretKey', '七牛云 SecretKey', NULL, '{"field":"storage_driver","value":"qiniu"}', 31, 1, NOW(), NOW()),
(0, 'storage_qiniu_bucket', '', 'storage', 'string', '七牛 Bucket', '七牛云存储空间名称', NULL, '{"field":"storage_driver","value":"qiniu"}', 32, 1, NOW(), NOW()),
(0, 'storage_qiniu_domain', '', 'storage', 'string', '七牛访问域名', '七牛云存储空间绑定的域名（含协议，如 https://cdn.example.com）', NULL, '{"field":"storage_driver","value":"qiniu"}', 33, 1, NOW(), NOW()),

-- ===== 公众号配置 (0, wechat_official) =====
(0, 'wechat_official_name', '', 'wechat_official', 'string', '公众号名称', '微信公众号名称', NULL, NULL, 1, 1, NOW(), NOW()),
(0, 'wechat_official_original_id', '', 'wechat_official', 'string', '原始ID', '公众号原始ID，如 gh_xxxxxxxx', NULL, NULL, 2, 1, NOW(), NOW()),
(0, 'wechat_official_qrcode', '', 'wechat_official', 'file', '公众号二维码', '公众号二维码图片，建议 200x200', NULL, NULL, 3, 1, NOW(), NOW()),
(0, 'wechat_official_app_id', '', 'wechat_official', 'string', 'AppID', '微信公众号AppID（开发者ID）', NULL, NULL, 10, 1, NOW(), NOW()),
(0, 'wechat_official_app_secret', '', 'wechat_official', 'string', 'AppSecret', '微信公众号AppSecret（开发者密码）', NULL, NULL, 11, 1, NOW(), NOW()),
(0, 'wechat_official_token', '', 'wechat_official', 'string', 'Token', '微信公众号消息校验Token', NULL, NULL, 20, 1, NOW(), NOW()),
(0, 'wechat_official_aes_key', '', 'wechat_official', 'string', 'EncodingAESKey', '微信公众号消息加解密密钥（43位字符）', NULL, NULL, 21, 1, NOW(), NOW()),
(0, 'wechat_official_encrypt_type', '1', 'wechat_official', 'select', '消息加密方式', '1=明文模式 2=兼容模式 3=安全模式，需与微信后台保持一致', '{"1":"明文模式","2":"兼容模式","3":"安全模式"}', NULL, 22, 1, NOW(), NOW()),

-- ===== 小程序配置 (0, wechat_mini) =====
(0, 'wechat_mini_name', '', 'wechat_mini', 'string', '小程序名称', '微信小程序名称', NULL, NULL, 1, 1, NOW(), NOW()),
(0, 'wechat_mini_original_id', '', 'wechat_mini', 'string', '原始ID', '小程序原始ID，如 gh_xxxxxxxx', NULL, NULL, 2, 1, NOW(), NOW()),
(0, 'wechat_mini_qrcode', '', 'wechat_mini', 'file', '小程序二维码', '小程序二维码图片，建议 200x200', NULL, NULL, 3, 1, NOW(), NOW()),
(0, 'wechat_mini_app_id', '', 'wechat_mini', 'string', 'AppID', '微信小程序AppID', NULL, NULL, 10, 1, NOW(), NOW()),
(0, 'wechat_mini_app_secret', '', 'wechat_mini', 'string', 'AppSecret', '微信小程序AppSecret', NULL, NULL, 11, 1, NOW(), NOW()),
(0, 'wechat_mini_msg_token', '', 'wechat_mini', 'string', 'Token', '消息推送校验Token', NULL, NULL, 20, 1, NOW(), NOW()),
(0, 'wechat_mini_msg_aes_key', '', 'wechat_mini', 'string', 'EncodingAESKey', '消息推送加解密密钥（43位字符）', NULL, NULL, 21, 1, NOW(), NOW()),
(0, 'wechat_mini_msg_format', 'JSON', 'wechat_mini', 'select', '数据格式', '消息推送数据格式', '{"JSON":"JSON","XML":"XML"}', NULL, 22, 1, NOW(), NOW()),
(0, 'wechat_mini_encrypt_type', '1', 'wechat_mini', 'select', '消息加密方式', '1=明文模式 2=兼容模式 3=安全模式，需与微信后台保持一致', '{"1":"明文模式","2":"兼容模式","3":"安全模式"}', NULL, 23, 1, NOW(), NOW()),

-- ===== 开放平台配置 (0, wechat_open) =====
(0, 'wechat_open_app_id', '', 'wechat_open', 'string', 'AppID', '微信开放平台网站应用AppID', NULL, NULL, 1, 1, NOW(), NOW()),
(0, 'wechat_open_app_secret', '', 'wechat_open', 'string', 'AppSecret', '微信开放平台网站应用AppSecret', NULL, NULL, 2, 1, NOW(), NOW());

-- ===== 预置消息模板 =====
INSERT INTO `message_templates` (`tenant_id`, `name`, `code`, `sms_enabled`, `sms_template_id`, `sms_content`, `wechat_official_enabled`, `wechat_official_template_id`, `wechat_official_url`, `wechat_mini_enabled`, `wechat_mini_template_id`, `wechat_mini_page`, `variables`, `remark`, `status`, `created_at`, `updated_at`) VALUES
(0, '登录验证码', 'login_captcha', 1, '', '您的登录验证码为${code}，5分钟内有效，请勿泄露给他人。', 0, '', '', 0, '', '', '[{"key":"code","name":"验证码","example":"6789"}]', '用户登录时发送的验证码通知', 1, NOW(), NOW()),
(0, '注册验证码', 'register_captcha', 1, '', '您的注册验证码为${code}，5分钟内有效，请勿泄露给他人。', 0, '', '', 0, '', '', '[{"key":"code","name":"验证码","example":"1234"}]', '用户注册时发送的验证码通知', 1, NOW(), NOW()),
(0, '找回密码', 'reset_password', 1, '', '您正在找回密码，验证码为${code}，5分钟内有效。', 0, '', '', 0, '', '', '[{"key":"code","name":"验证码","example":"5678"}]', '用户找回密码时发送的验证码通知', 1, NOW(), NOW()),
(0, '绑定手机', 'bind_mobile', 1, '', '您正在绑定手机号，验证码为${code}，5分钟内有效。', 0, '', '', 0, '', '', '[{"key":"code","name":"验证码","example":"9012"}]', '用户绑定手机号时发送的验证码通知', 1, NOW(), NOW()),
(0, '变更手机', 'change_mobile', 1, '', '您正在变更手机号，验证码为${code}，5分钟内有效。', 0, '', '', 0, '', '', '[{"key":"code","name":"验证码","example":"3456"}]', '用户变更手机号时发送的验证码通知', 1, NOW(), NOW()),
(0, '用户注册欢迎', 'user_register', 0, '', '恭喜您注册成功！感谢您的信任与支持。', 0, '', '', 0, '', '', '[]', '用户注册成功后发送的欢迎通知', 1, NOW(), NOW()),
(0, '支付成功通知', 'payment_success', 0, '', '您的订单${order_no}已支付成功，支付金额${amount}元。', 0, '', '', 0, '', '', '[{"key":"order_no","name":"订单号","example":"202603250001"},{"key":"amount","name":"支付金额","example":"99.00"}]', '用户支付成功后发送的通知', 1, NOW(), NOW()),
(0, '反馈已收到', 'feedback_received', 0, '', '您的反馈我们已收到，将尽快为您处理，感谢您的支持！', 0, '', '', 0, '', '', '[]', '用户提交反馈后发送的确认通知', 1, NOW(), NOW()),
(0, '通用通知', 'notification', 0, '', '${title}：${content}', 0, '', '', 0, '', '', '[{"key":"title","name":"通知标题","example":"系统通知"},{"key":"content","name":"通知内容","example":"您有一条新消息"}]', '站内消息推送到微信小程序等通道时使用的通用通知模板', 1, NOW(), NOW()),
(0, '订阅到期提醒', 'subscription_reminder', 0, '', '尊敬的${tenant_name}，您的订阅将于${expire_date}到期（剩余${days_left}天），请及时续费以免影响使用。', 0, '', '', 0, '', '', '[{"key":"tenant_name","name":"租户名称","example":"演示租户"},{"key":"expire_date","name":"到期时间","example":"2026-08-01 00:00:00"},{"key":"days_left","name":"剩余天数","example":"7"},{"key":"type","name":"提醒类型","example":"expiring"}]', '租户订阅临近到期或已过期时发送的提醒通知', 1, NOW(), NOW()),
(0, '订阅自动续费通知', 'subscription_auto_renew', 0, '', '尊敬的${tenant_name}，您的订阅已自动续费成功，订单号${order_no}，金额${amount}元，服务有效期至${expire_date}。', 0, '', '', 0, '', '', '[{"key":"tenant_name","name":"租户名称","example":"演示租户"},{"key":"order_no","name":"订单号","example":"202603250001"},{"key":"amount","name":"续费金额","example":"99.00"},{"key":"expire_date","name":"新到期时间","example":"2027-08-01 00:00:00"}]', '租户订阅自动续费成功后发送的通知', 1, NOW(), NOW());

-- Banner 配置
INSERT INTO `system_configs` (`tenant_id`, `config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `config_options`, `config_depends`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(0, 'banner_list', '[]', 'banner', 'json', '轮播图列表', '首页轮播图配置，JSON数组格式：[{"image":"图片地址","url":"跳转链接","title":"标题"}]', NULL, NULL, 1, 1, NOW(), NOW());

-- ===== 协议配置 (0, agreement) =====
INSERT INTO `system_configs` (`tenant_id`, `config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `config_options`, `config_depends`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
(0, 'agreement_user_agreement', '', 'agreement', 'richtext', '用户协议', '注册/登录页展示的用户协议正文', NULL, NULL, 1, 1, NOW(), NOW()),
(0, 'agreement_privacy_policy', '', 'agreement', 'richtext', '隐私政策', '注册/登录页展示的隐私政策正文', NULL, NULL, 2, 1, NOW(), NOW());

-- 默认首页装修模板（tenant_id=0；新建租户由 TenantInitService.copyDiyPages 复制）
-- 草稿=已发布，最小可渲染骨架：搜索框 + 公告 + 标题栏
INSERT INTO `diy_pages` (`tenant_id`, `page_type`, `page_key`, `platform`, `title`, `components_draft`, `components_published`, `page_settings`, `status`, `created_at`, `updated_at`) VALUES
(0, 'home', 'home', 'uniapp', '首页',
 '[{"id":"seed-search","type":"search-bar","props":{"placeholder":"搜索商品","radius":20,"bg_color":"#f5f5f5","link":""}},{"id":"seed-notice","type":"notice","props":{"items":[{"text":"欢迎光临，新店开业~","link":""}],"speed":3000,"icon":""}},{"id":"seed-title","type":"title-bar","props":{"title":"热门推荐","subtitle":"","align":"left","more_text":"","more_link":""}}]',
 '[{"id":"seed-search","type":"search-bar","props":{"placeholder":"搜索商品","radius":20,"bg_color":"#f5f5f5","link":""}},{"id":"seed-notice","type":"notice","props":{"items":[{"text":"欢迎光临，新店开业~","link":""}],"speed":3000,"icon":""}},{"id":"seed-title","type":"title-bar","props":{"title":"热门推荐","subtitle":"","align":"left","more_text":"","more_link":""}}]',
 '{"background_color":""}', 1, NOW(), NOW());

INSERT INTO `diy_pages` (`tenant_id`, `page_type`, `page_key`, `platform`, `title`, `components_draft`, `components_published`, `page_settings`, `status`, `created_at`, `updated_at`) VALUES
(0, 'member', 'member', 'uniapp', '个人中心',
 '[{"id":"seed-member-user","type":"user-info-card","props":{"show_assets":true}},{"id":"seed-member-menu","type":"service-menu","props":{"items":[]}}]',
 '[{"id":"seed-member-user","type":"user-info-card","props":{"show_assets":true}},{"id":"seed-member-menu","type":"service-menu","props":{"items":[]}}]',
 '{"background_color":""}', 1, NOW(), NOW());

INSERT INTO `diy_pages` (`tenant_id`, `page_type`, `page_key`, `platform`, `title`, `components_draft`, `components_published`, `page_settings`, `status`, `created_at`, `updated_at`) VALUES
(0, 'home', 'home', 'pc', 'PC 首页',
 '[{"id":"pc-hero","type":"title-bar","props":{"title":"欢迎来到元点 SaaS","subtitle":"一站式搭建租户官网、内容门户和用户前台","align":"center","more_text":"","more_link":""}},{"id":"pc-rich","type":"rich-text","props":{"content":"<p>租户可在后台配置 CMS 官网、插件前台或自定义单页作为 PC 首页。</p>"}}]',
 '[{"id":"pc-hero","type":"title-bar","props":{"title":"欢迎来到元点 SaaS","subtitle":"一站式搭建租户官网、内容门户和用户前台","align":"center","more_text":"","more_link":""}},{"id":"pc-rich","type":"rich-text","props":{"content":"<p>租户可在后台配置 CMS 官网、插件前台或自定义单页作为 PC 首页。</p>"}}]',
 '{"background_color":"#f8fafc","title":"PC 首页"}', 1, NOW(), NOW());

INSERT INTO `tenant_pc_configs` (`tenant_id`, `site_name`, `site_logo`, `site_intro`, `theme_color`, `home_type`, `home_app_code`, `home_page`, `nav_json`, `seo_json`, `login_enabled`, `register_enabled`, `status`, `created_at`, `updated_at`) VALUES
(0, '元点 SaaS', '', '租户 PC 前台', '#2563eb', 'diy', '', 'home', JSON_ARRAY(JSON_OBJECT('label','首页','path','/','code','','auth',false,'sort',1)), JSON_OBJECT('title','元点 SaaS','keywords','','description','租户 PC 前台'), 1, 1, 1, NOW(), NOW());

-- ============================================================
-- SaaS 种子数据
-- ============================================================

-- 默认套餐
-- 套餐 ↔ 插件 授权关系迁到 plugin_grants 表（Stage 3+）；安装后由超管在套餐编辑页勾选授权
-- features 保持 NULL：基础设施（用户/内容/渠道/系统配置）所有套餐通用，不进 features；
-- 真正按套餐差异的是 plan_grants（应用 + 插件），由超管在套餐编辑页勾选授权。
INSERT INTO `plans` (`code`, `name`, `description`, `price_monthly`, `price_yearly`, `storage_limit_bytes`, `sort`, `status`, `created_at`, `updated_at`)
VALUES
  ('free',  '免费版', '免费试用，5GB 存储',    0,      0,      5368709120,  1, 1, NOW(), NOW()),
  ('basic', '基础版', '小团队起步套餐',         99.00,  990.00, 21474836480, 2, 1, NOW(), NOW()),
  ('pro',   '专业版', '中型团队套餐',           299.00, 2990.00, 107374182400, 3, 1, NOW(), NOW());

-- 平台后台菜单
INSERT INTO `platform_menus` (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `sort`, `permission`, `type`, `hidden`, `status`, `created_at`, `updated_at`)
VALUES
  -- 一级菜单（侧边栏显示）
  (1, 0, '工作台',       'dashboard',    'dashboard/index',    'i-svg:house',        1, 'platform.dashboard.view', 2, 0, 1, NOW(), NOW()),
  (2, 0, '租户管理',     'tenant',       'tenant/index',       'i-svg:users-round',  2, 'tenant.view',      2, 0, 1, NOW(), NOW()),
  -- v2.27.1：租户 CRUD 权限拆分（此前增删改/重置密码全部共用 tenant.view，越权风险）
  (270, 2, '新建租户',     '', '', '', 1, 'platform.tenant.create',         3, 0, 1, NOW(), NOW()),
  (271, 2, '编辑租户',     '', '', '', 2, 'platform.tenant.update',         3, 0, 1, NOW(), NOW()),
  (272, 2, '删除租户',     '', '', '', 3, 'platform.tenant.delete',         3, 0, 1, NOW(), NOW()),
  (273, 2, '重置管理员密码', '', '', '', 4, 'platform.tenant.reset_password', 3, 0, 1, NOW(), NOW()),
  (3, 0, '套餐管理',     'plan',         'plan/index',         'i-svg:boxes',        3, 'plan.view',        2, 0, 1, NOW(), NOW()),
  -- v2.7.1：套餐 CUD 按钮权限（与 platform.plugin / platform.refund 命名对齐）
  (4, 3, '新建套餐',     '',             '',                   '',                   1, 'platform.plan.create', 3, 0, 1, NOW(), NOW()),
  (6, 3, '更新套餐',     '',             '',                   '',                   2, 'platform.plan.update', 3, 0, 1, NOW(), NOW()),
  (7, 3, '删除套餐',     '',             '',                   '',                   3, 'platform.plan.delete', 3, 0, 1, NOW(), NOW()),
  -- 订单管理（目录，含退款）
  (5, 0, '订单管理',     '',             '',                   'i-svg:receipt-text', 4, '',                 1, 0, 1, NOW(), NOW()),
  (51, 5, '订单列表',    'order',        'order/index',        'i-svg:receipt-text', 1, 'order.view',       2, 0, 1, NOW(), NOW()),
  (52, 5, '退款管理',    'refund',       'refund/index',       'i-svg:rotate-ccw',   2, 'platform.refund.list', 2, 0, 1, NOW(), NOW()),
  -- 系统配置（目录，含所有管理功能）
  (30, 0, '系统配置',    '',             '',                   'i-svg:cog',          6, '',                 1, 0, 1, NOW(), NOW()),
  (31, 30, '系统设置',   'system/config', 'config/index',      'i-svg:settings-2',   1, 'platform.config.list',   2, 0, 1, NOW(), NOW()),
  (32, 30, '管理员管理', 'system/admin', 'system/admin/index', 'i-svg:user',         2, 'platform.admin.list',    2, 0, 1, NOW(), NOW()),
  (33, 30, '角色管理',   'system/role',  'system/role/index',  'i-svg:users',        3, 'platform.role.list',     2, 0, 1, NOW(), NOW()),
  (34, 30, '菜单管理',   'system/menu',  'system/menu/index',  'i-svg:layout-grid',  4, 'platform.menu.list',     2, 0, 1, NOW(), NOW()),
  (35, 30, '审计日志',   'system/audit', 'audit/index',        'i-svg:scroll-text',  5, 'platform.audit.list',    2, 0, 1, NOW(), NOW()),
  (36, 30, '日志管理',   'system/log',   'system/log/index',   'i-svg:logs',         6, 'platform.log.login',     2, 0, 1, NOW(), NOW()),
  (37, 30, '平台公告',   'system/announcement', 'announcement/index', 'i-svg:bell-ring', 7, 'platform.announcement.list', 2, 0, 1, NOW(), NOW()),
  (38, 30, '产品授权',   'system/license', 'system/license/index', 'i-svg:lock', 8, 'platform.license.view', 2, 0, 1, NOW(), NOW()),
  (39, 38, '激活授权',   '', '', '', 1, 'platform.license.update', 3, 0, 1, NOW(), NOW()),
  -- 按钮权限：系统设置
  (151, 31, '配置列表', '', '', '', 1, 'platform.config.list', 3, 0, 1, NOW(), NOW()),
  (152, 31, '配置更新', '', '', '', 2, 'platform.config.update', 3, 0, 1, NOW(), NOW()),
  -- 按钮权限：管理员
  (101, 32, '新增管理员', '', '', '', 1, 'platform.admin.create', 3, 0, 1, NOW(), NOW()),
  (102, 32, '编辑管理员', '', '', '', 2, 'platform.admin.update', 3, 0, 1, NOW(), NOW()),
  (103, 32, '删除管理员', '', '', '', 3, 'platform.admin.delete', 3, 0, 1, NOW(), NOW()),
  (104, 32, '状态切换',   '', '', '', 4, 'platform.admin.status', 3, 0, 1, NOW(), NOW()),
  -- 按钮权限：角色
  (111, 33, '新增角色', '', '', '', 1, 'platform.role.create',     3, 0, 1, NOW(), NOW()),
  (112, 33, '编辑角色', '', '', '', 2, 'platform.role.update',     3, 0, 1, NOW(), NOW()),
  (113, 33, '删除角色', '', '', '', 3, 'platform.role.delete',     3, 0, 1, NOW(), NOW()),
  (114, 33, '分配权限', '', '', '', 4, 'platform.role.permission', 3, 0, 1, NOW(), NOW()),
  -- 按钮权限：菜单
  (121, 34, '新增菜单', '', '', '', 1, 'platform.menu.create', 3, 0, 1, NOW(), NOW()),
  (122, 34, '编辑菜单', '', '', '', 2, 'platform.menu.update', 3, 0, 1, NOW(), NOW()),
  (123, 34, '删除菜单', '', '', '', 3, 'platform.menu.delete', 3, 0, 1, NOW(), NOW()),
  -- 按钮权限：公告
  (131, 37, '新增公告', '', '', '', 1, 'platform.announcement.create', 3, 0, 1, NOW(), NOW()),
  (132, 37, '编辑公告', '', '', '', 2, 'platform.announcement.update', 3, 0, 1, NOW(), NOW()),
  (133, 37, '删除公告', '', '', '', 3, 'platform.announcement.delete', 3, 0, 1, NOW(), NOW()),
  -- 按钮权限：退款
  (141, 52, '发起退款', '', '', '', 1, 'platform.refund.create', 3, 0, 1, NOW(), NOW()),
  -- 开发工具（目录）
  (40, 0, '开发工具', '', '', 'i-svg:cpu', 8, '', 1, 0, 1, NOW(), NOW()),
  (41, 40, '代码生成器', 'dev-tools/generator', 'system/generator/index', 'i-svg:file-sliders', 1, 'platform.generator.list', 2, 0, 1, NOW(), NOW()),
  (42, 41, '生成代码', '', '', '', 1, 'platform.generator.generate', 3, 0, 1, NOW(), NOW()),
  (43, 40, 'API文档', 'dev-tools/api-doc', 'system/api-doc/index', 'i-svg:notebook-text', 2, 'platform.api_doc', 2, 0, 1, NOW(), NOW()),
  -- 移动构建监控（v2.27.1：接口早已存在，补前端页面与菜单）
  (260, 40, '移动构建监控', 'dev-tools/mobile-builds', 'mobile-build/index', 'i-svg:smartphone', 3, 'platform.mobile.build.view', 2, 0, 1, NOW(), NOW()),
  (261, 260, '强制收尾卡死任务', '', '', '', 1, 'platform.mobile.build.manage', 3, 0, 1, NOW(), NOW()),
  -- 定时任务
  (200, 30, '定时任务', 'system/cron-job', 'system/cron-job/index', 'i-svg:bolt', 8, 'platform.cron_job.list', 2, 0, 1, NOW(), NOW()),
  (201, 200, '新增任务', '', '', '', 1, 'platform.cron_job.create', 3, 0, 1, NOW(), NOW()),
  (202, 200, '编辑任务', '', '', '', 2, 'platform.cron_job.update', 3, 0, 1, NOW(), NOW()),
  (203, 200, '删除任务', '', '', '', 3, 'platform.cron_job.delete', 3, 0, 1, NOW(), NOW()),
  (204, 200, '执行任务', '', '', '', 4, 'platform.cron_job.run', 3, 0, 1, NOW(), NOW()),
  (205, 200, '清空日志', '', '', '', 5, 'platform.cron_job.clear', 3, 0, 1, NOW(), NOW()),
  -- 文件管理
  (210, 30, '文件管理', 'system/file', 'system/file/index', 'i-svg:folder', 9, 'platform.file.list', 2, 0, 1, NOW(), NOW()),
  (211, 210, '上传文件', '', '', '', 1, 'platform.file.upload', 3, 0, 1, NOW(), NOW()),
  (212, 210, '编辑文件', '', '', '', 2, 'platform.file.update', 3, 0, 1, NOW(), NOW()),
  (213, 210, '删除文件', '', '', '', 3, 'platform.file.delete', 3, 0, 1, NOW(), NOW()),
  -- 数据字典
  (220, 30, '数据字典', 'system/dictionary', 'system/dictionary/index', 'i-svg:file-text', 10, 'platform.dictionary.list', 2, 0, 1, NOW(), NOW()),
  (221, 220, '新增字典', '', '', '', 1, 'platform.dictionary.create', 3, 0, 1, NOW(), NOW()),
  (222, 220, '编辑字典', '', '', '', 2, 'platform.dictionary.update', 3, 0, 1, NOW(), NOW()),
  (223, 220, '删除字典', '', '', '', 3, 'platform.dictionary.delete', 3, 0, 1, NOW(), NOW()),
  -- 权限管理
  (230, 30, '权限管理', 'system/permission', 'system/permission/index', 'i-svg:lock', 11, 'platform.permission.list', 2, 0, 1, NOW(), NOW()),
  (231, 230, '新增权限', '', '', '', 1, 'platform.permission.create', 3, 0, 1, NOW(), NOW()),
  (232, 230, '编辑权限', '', '', '', 2, 'platform.permission.update', 3, 0, 1, NOW(), NOW()),
  (233, 230, '删除权限', '', '', '', 3, 'platform.permission.delete', 3, 0, 1, NOW(), NOW());

-- ============================================================
-- 应用管理 - 平台菜单 + 权限码（Stage 2，原名"插件管理"，统一改名为"应用管理"）
-- ============================================================
INSERT INTO `platform_menus` (`id`, `parent_id`, `name`, `path`, `component`, `icon`, `sort`, `permission`, `type`, `hidden`, `status`, `created_at`, `updated_at`) VALUES
  -- 一级菜单：应用管理（放在系统配置 sort=6 前，sort=5）
  (240, 0, '应用管理', 'plugin', 'plugin/index', 'i-svg:blocks', 5, 'platform.plugin.list', 2, 0, 1, NOW(), NOW()),
  -- 按钮权限
  (241, 240, '查看详情', '', '', '', 1, 'platform.plugin.detail',    3, 0, 1, NOW(), NOW()),
  (242, 240, '上传插件', '', '', '', 2, 'platform.plugin.upload',    3, 0, 1, NOW(), NOW()),
  (243, 240, '安装插件', '', '', '', 3, 'platform.plugin.install',   3, 0, 1, NOW(), NOW()),
  (244, 240, '卸载插件',       '', '', '', 4, 'platform.plugin.uninstall',     3, 0, 1, NOW(), NOW()),
  -- 套餐授权按钮权限（Stage 3）
  (245, 240, '查看套餐授权',   '', '', '', 5, 'platform.plugin_grant.list',    3, 0, 1, NOW(), NOW()),
  (246, 240, '同步套餐授权',   '', '', '', 6, 'platform.plugin_grant.sync',    3, 0, 1, NOW(), NOW()),
  -- 云编译菜单（Stage 5）：构建日志/查看日志 顶层入口下线，仅保留'手动重建'按钮权限
  -- v2.6.7：补 plugin_build.list / plugin_build.detail 按钮权限（Controller #[Permission] 已声明，原 seed 漏）
  (247, 240, '构建日志列表', '', '', '', 9,  'platform.plugin_build.list',    3, 0, 1, NOW(), NOW()),
  (248, 240, '构建日志详情', '', '', '', 10, 'platform.plugin_build.detail',  3, 0, 1, NOW(), NOW()),
  (249, 240, '手动重建',       '', '', '', 11, 'platform.plugin_build.rebuild', 3, 0, 1, NOW(), NOW()),
  -- 升级 + 数据清理（Stage 6）
  (251, 240, '升级插件',       '', '', '', 12, 'platform.plugin.upgrade',       3, 0, 1, NOW(), NOW()),
  (252, 240, '清理数据',       '', '', '', 13, 'platform.plugin.purge',         3, 0, 1, NOW(), NOW()),
  -- v2.27.1：平台级插件禁用/启用（临时下架，保留代码与数据）
  (274, 240, '禁用/启用插件',  '', '', '', 14, 'platform.plugin.status',        3, 0, 1, NOW(), NOW()),
  -- v2.27.1：官方市场权限补进 platform_menus（此前只 seed 在不参与鉴权的遗留 permissions 表，
  -- 非超管无法被授权使用应用市场）
  (275, 240, '查看Site连接',       '', '', '', 15, 'marketplace.connection.view',         3, 0, 1, NOW(), NOW()),
  (276, 240, '管理Site连接',       '', '', '', 16, 'marketplace.connection.manage',       3, 0, 1, NOW(), NOW()),
  (277, 240, '查看市场目录',       '', '', '', 17, 'marketplace.catalog.view',            3, 0, 1, NOW(), NOW()),
  (278, 240, '安装/升级官方应用',  '', '', '', 18, 'marketplace.install',                 3, 0, 1, NOW(), NOW()),
  (279, 240, '轮换连接Token',      '', '', '', 19, 'marketplace.connection.rotate_token', 3, 0, 1, NOW(), NOW());

-- 区域管理 + 应用版本 一级菜单已下线（业务无意义），整段移除

-- ============================================================
-- 平台系统配置（tenant_id = 0）
-- ============================================================
INSERT INTO `system_configs` (`tenant_id`, `config_key`, `config_value`, `config_group`, `config_type`, `config_name`, `config_desc`, `config_options`, `config_depends`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES
-- basic
(0, 'platform_site_name', '元点Saas管理系统', 'basic', 'string', '站点名称', '平台管理后台名称', NULL, NULL, 1, 1, NOW(), NOW()),
(0, 'platform_site_logo', '', 'basic', 'file', '站点Logo', '平台后台Logo图片', NULL, NULL, 2, 1, NOW(), NOW()),
(0, 'platform_site_favicon', '', 'basic', 'file', '站点图标', '浏览器标签图标', NULL, NULL, 3, 1, NOW(), NOW()),
(0, 'platform_copyright', '', 'basic', 'string', '版权信息', '页脚版权文字', NULL, NULL, 4, 1, NOW(), NOW()),
-- storage
(0, 'platform_storage_driver', 'local', 'storage', 'select', '存储驱动', '文件存储方式', '{"local":"本地存储","aliyun":"阿里云OSS","tencent":"腾讯云COS","qiniu":"七牛云"}', NULL, 1, 1, NOW(), NOW()),
(0, 'platform_storage_access_key', '', 'storage', 'string', 'AccessKey', '云存储AccessKey', NULL, NULL, 2, 1, NOW(), NOW()),
(0, 'platform_storage_access_secret', '', 'storage', 'string', 'AccessSecret', '云存储AccessSecret', NULL, NULL, 3, 1, NOW(), NOW()),
(0, 'platform_storage_bucket', '', 'storage', 'string', 'Bucket名称', '存储桶名称', NULL, NULL, 4, 1, NOW(), NOW()),
(0, 'platform_storage_domain', '', 'storage', 'string', '访问域名', '文件访问域名（含https://）', NULL, NULL, 5, 1, NOW(), NOW()),
(0, 'platform_storage_region', '', 'storage', 'string', '存储区域', '如 oss-cn-hangzhou', NULL, NULL, 6, 1, NOW(), NOW()),
-- payment
(0, 'platform_pay_wechat_enabled', '0', 'payment', 'boolean', '微信支付开关', '启用微信支付', NULL, NULL, 1, 1, NOW(), NOW()),
(0, 'platform_pay_wechat_app_id', '', 'payment', 'string', '微信AppID', '微信支付应用ID', NULL, NULL, 2, 1, NOW(), NOW()),
(0, 'platform_pay_wechat_mch_id', '', 'payment', 'string', '微信商户号', '微信支付商户号', NULL, NULL, 3, 1, NOW(), NOW()),
(0, 'platform_pay_wechat_api_key', '', 'payment', 'string', '微信APIv3密钥', '微信支付APIv3密钥', NULL, NULL, 4, 1, NOW(), NOW()),
(0, 'platform_pay_wechat_cert_path', '', 'payment', 'string', '微信证书路径', '如 /path/to/apiclient_cert.pem', NULL, NULL, 5, 1, NOW(), NOW()),
(0, 'platform_pay_wechat_cert_key_path', '', 'payment', 'string', '微信证书密钥路径', '如 /path/to/apiclient_key.pem', NULL, NULL, 6, 1, NOW(), NOW()),
(0, 'platform_pay_alipay_enabled', '0', 'payment', 'boolean', '支付宝开关', '启用支付宝支付', NULL, NULL, 7, 1, NOW(), NOW()),
(0, 'platform_pay_alipay_app_id', '', 'payment', 'string', '支付宝AppID', '支付宝应用ID', NULL, NULL, 8, 1, NOW(), NOW()),
(0, 'platform_pay_alipay_private_key', '', 'payment', 'string', '支付宝私钥', '应用私钥', NULL, NULL, 9, 1, NOW(), NOW()),
(0, 'platform_pay_alipay_public_key', '', 'payment', 'string', '支付宝公钥', '支付宝公钥', NULL, NULL, 10, 1, NOW(), NOW()),
-- sms
(0, 'platform_sms_driver', 'aliyun', 'sms', 'select', '短信驱动', '短信服务商', '{"aliyun":"阿里云短信","tencent":"腾讯云短信"}', NULL, 1, 1, NOW(), NOW()),
(0, 'platform_sms_access_key', '', 'sms', 'string', 'AccessKey', '短信服务AccessKey', NULL, NULL, 2, 1, NOW(), NOW()),
(0, 'platform_sms_access_secret', '', 'sms', 'string', 'AccessSecret', '短信服务AccessSecret', NULL, NULL, 3, 1, NOW(), NOW()),
(0, 'platform_sms_sign_name', '', 'sms', 'string', '短信签名', '已审核的短信签名', NULL, NULL, 4, 1, NOW(), NOW()),
-- email
(0, 'platform_email_driver', 'smtp', 'email', 'select', '邮件驱动', '邮件发送方式', '{"smtp":"SMTP","sendmail":"Sendmail"}', NULL, 1, 1, NOW(), NOW()),
(0, 'platform_email_host', '', 'email', 'string', 'SMTP主机', '如 smtp.qq.com', NULL, NULL, 2, 1, NOW(), NOW()),
(0, 'platform_email_port', '465', 'email', 'number', 'SMTP端口', '如 465 (SSL) 或 587 (TLS)', NULL, NULL, 3, 1, NOW(), NOW()),
(0, 'platform_email_username', '', 'email', 'string', '邮箱账号', 'SMTP登录账号', NULL, NULL, 4, 1, NOW(), NOW()),
(0, 'platform_email_password', '', 'email', 'string', '邮箱密码', 'SMTP登录密码或授权码', NULL, NULL, 5, 1, NOW(), NOW()),
(0, 'platform_email_from_address', '', 'email', 'string', '发件地址', '发件人邮箱地址', NULL, NULL, 6, 1, NOW(), NOW()),
(0, 'platform_email_from_name', '', 'email', 'string', '发件人名称', '显示的发件人名称', NULL, NULL, 7, 1, NOW(), NOW());

-- 不在 init.sql 预置 platform_admin 账号。
-- 初装完成后用 CLI 命令创建：
--   php think saas:create-platform-admin <username> <password> [<email>]

-- v2.8.0: 官方市场权限点位
INSERT IGNORE INTO `permissions` (`tenant_id`, `name`, `title`, `group`, `description`, `guard_name`, `status`, `sort`, `created_at`, `updated_at`)
VALUES
  (0, 'marketplace.connection.manage', '管理 Site 连接',       '平台管理', '管理官方市场 Site 连接', 'admin', 1, 0, NOW(), NOW()),
  (0, 'marketplace.connection.view',   '查看 Site 连接',       '平台管理', '查看官方市场 Site 连接列表', 'admin', 1, 1, NOW(), NOW()),
  (0, 'marketplace.catalog.view',      '查看官方市场应用目录', '平台管理', '查看官方市场已购应用目录', 'admin', 1, 2, NOW(), NOW()),
  (0, 'marketplace.install',           '安装/升级官方应用',    '平台管理', '从官方市场安装或升级应用', 'admin', 1, 3, NOW(), NOW());

-- v2.9.0: token 轮换权限点位
-- v2.27.1：移除 marketplace.license.view / license.manual_renew / audit.toggle 三个从未实现的预埋点位：
--   license 状态已随 catalog.view 的应用目录返回；「同步」(connection.manage) 已触发 license 重评估；
--   审计上报开关走配置文件 saas.marketplace.audit_report_enabled。
INSERT IGNORE INTO `permissions` (`tenant_id`, `name`, `title`, `group`, `description`, `guard_name`, `status`, `sort`, `created_at`, `updated_at`)
VALUES
  (0, 'marketplace.connection.rotate_token', '手动轮换 Site 连接 token',  '平台管理', '手动轮换 marketplace 连接的 instance token', 'admin', 1, 6, NOW(), NOW());
