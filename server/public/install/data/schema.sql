-- ============================================================
-- 元点 Framework SaaS - 数据库结构文件
-- 由 migration dump 自动生成，所有 A 类业务表含 tenant_id
-- ============================================================
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_login_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `admin_id` bigint(20) DEFAULT NULL COMMENT '管理员ID',
  `username` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '用户名',
  `ip` varchar(45) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '登录IP',
  `user_agent` text COLLATE utf8mb4_0900_ai_ci COMMENT 'User Agent',
  `login_time` datetime DEFAULT NULL COMMENT '登录时间',
  `login_result` int(4) DEFAULT NULL COMMENT '登录结果:1成功,0失败',
  `login_message` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '登录消息',
  `browser` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '浏览器',
  `os` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '操作系统',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_admin` (`tenant_id`,`admin_id`),
  KEY `idx_tenant_username` (`tenant_id`,`username`),
  KEY `idx_tenant_ip` (`tenant_id`,`ip`),
  KEY `idx_tenant_login_time` (`tenant_id`,`login_time`),
  KEY `idx_tenant_login_result` (`tenant_id`,`login_result`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='管理员登录日志表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_operation_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `admin_id` bigint(20) DEFAULT NULL COMMENT '管理员ID',
  `username` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '用户名',
  `method` varchar(10) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '请求方法',
  `path` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '请求路径',
  `ip` varchar(45) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '请求IP',
  `user_agent` text COLLATE utf8mb4_0900_ai_ci COMMENT 'User Agent',
  `action` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '操作动作',
  `description` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '操作描述',
  `params` json DEFAULT NULL COMMENT '请求参数',
  `result` json DEFAULT NULL COMMENT '响应结果',
  `operation_time` datetime DEFAULT NULL COMMENT '操作时间',
  `execution_time` decimal(8,3) DEFAULT NULL COMMENT '执行时间(秒)',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_admin` (`tenant_id`,`admin_id`),
  KEY `idx_tenant_username` (`tenant_id`,`username`),
  KEY `idx_tenant_method` (`tenant_id`,`method`),
  KEY `idx_tenant_path` (`tenant_id`,`path`),
  KEY `idx_tenant_action` (`tenant_id`,`action`),
  KEY `idx_tenant_op_time` (`tenant_id`,`operation_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='管理员操作日志表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admin_roles` (
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `admin_id` bigint(20) unsigned NOT NULL COMMENT '管理员ID',
  `role_id` bigint(20) unsigned NOT NULL COMMENT '角色ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`admin_id`,`role_id`),
  KEY `idx_tenant_admin` (`tenant_id`,`admin_id`),
  KEY `idx_tenant_role` (`tenant_id`,`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='管理员角色关联表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `admins` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `username` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '用户名',
  `email` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '邮箱',
  `mobile` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '手机号',
  `password` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '密码',
  `nickname` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '头像',
  `department_id` int(10) DEFAULT '0' COMMENT '部门ID',
  `department` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '部门名称',
  `position` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '职位',
  `last_login_ip` varchar(45) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '最后登录IP',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `login_count` int(11) DEFAULT '0' COMMENT '登录次数',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `created_by` bigint(20) DEFAULT NULL COMMENT '创建人',
  `updated_by` bigint(20) DEFAULT NULL COMMENT '更新人',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_username` (`tenant_id`,`username`),
  UNIQUE KEY `uk_tenant_email` (`tenant_id`,`email`),
  KEY `idx_tenant_mobile` (`tenant_id`,`mobile`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_tenant_created` (`tenant_id`,`created_at`),
  KEY `idx_tenant_dept` (`tenant_id`,`department_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='管理员表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `app_versions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `platform` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '平台',
  `version` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '版本号',
  `version_code` int(11) unsigned DEFAULT NULL COMMENT '版本编码',
  `download_url` varchar(500) COLLATE utf8mb4_0900_ai_ci DEFAULT '' COMMENT '下载地址',
  `description` text COLLATE utf8mb4_0900_ai_ci COMMENT '版本描述',
  `force_update` tinyint(4) DEFAULT '0' COMMENT '强制更新：0否 1是',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态：0禁用 1启用',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_platform_ver` (`platform`,`version_code`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='APP版本表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `balance_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '用户ID',
  `amount` decimal(10,2) DEFAULT NULL COMMENT '变动金额',
  `before_balance` decimal(10,2) DEFAULT NULL COMMENT '变动前余额',
  `after_balance` decimal(10,2) DEFAULT NULL COMMENT '变动后余额',
  `type` tinyint(4) DEFAULT '1' COMMENT '类型:1充值,2消费,3退款,4后台调整',
  `source` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT '' COMMENT '来源标识',
  `remark` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT '' COMMENT '备注',
  `operator_id` int(11) unsigned DEFAULT NULL COMMENT '操作管理员ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_tenant_type` (`tenant_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='余额变动记录';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cron_job_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `cron_job_id` int(11) unsigned DEFAULT NULL COMMENT '定时任务ID',
  `status` int(4) DEFAULT NULL COMMENT '执行状态',
  `output` text COLLATE utf8mb4_0900_ai_ci COMMENT '输出内容',
  `error` text COLLATE utf8mb4_0900_ai_ci COMMENT '错误信息',
  `started_at` datetime DEFAULT NULL COMMENT '开始时间',
  `finished_at` datetime DEFAULT NULL COMMENT '结束时间',
  `duration` int(11) unsigned DEFAULT NULL COMMENT '执行时长(秒)',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_cron` (`tenant_id`,`cron_job_id`),
  KEY `idx_tenant_created` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='定时任务日志表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cron_jobs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `name` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '任务名称',
  `command` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '执行命令',
  `expression` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'Cron表达式',
  `description` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '任务描述',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `last_run_at` datetime DEFAULT NULL COMMENT '上次运行时间',
  `last_result` text COLLATE utf8mb4_0900_ai_ci COMMENT '上次运行结果',
  `last_status` int(4) DEFAULT NULL COMMENT '上次运行状态',
  `run_count` int(11) unsigned DEFAULT '0' COMMENT '运行次数',
  `sort` int(11) DEFAULT '0' COMMENT '排序',
  `created_by` int(11) unsigned DEFAULT NULL COMMENT '创建人',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='定时任务表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `data_imports` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `module` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '模块',
  `filename` varchar(200) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '文件名',
  `total_count` int(11) DEFAULT '0' COMMENT '总条数',
  `success_count` int(11) DEFAULT '0' COMMENT '成功条数',
  `fail_count` int(11) DEFAULT '0' COMMENT '失败条数',
  `status` tinyint(4) DEFAULT '0' COMMENT '状态：0处理中 1完成 2失败',
  `errors` text COLLATE utf8mb4_0900_ai_ci COMMENT '错误信息JSON',
  `admin_id` int(11) unsigned DEFAULT NULL COMMENT '管理员ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_module` (`tenant_id`,`module`),
  KEY `idx_tenant_admin` (`tenant_id`,`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='数据导入表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `departments` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `parent_id` int(10) DEFAULT '0' COMMENT '父级ID',
  `name` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '部门名称',
  `code` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '部门编码',
  `leader` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '负责人',
  `phone` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '联系电话',
  `email` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '邮箱',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `sort` int(11) DEFAULT '0' COMMENT '排序',
  `remark` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '备注',
  `created_by` int(10) DEFAULT NULL COMMENT '创建人',
  `updated_by` int(10) DEFAULT NULL COMMENT '更新人',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_code` (`tenant_id`,`code`),
  KEY `idx_tenant_parent` (`tenant_id`,`parent_id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='部门表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dictionaries` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `name` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '字典名称',
  `code` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '字典编码',
  `description` varchar(500) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '描述',
  `status` int(4) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `sort` int(11) DEFAULT '0' COMMENT '排序',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_code` (`tenant_id`,`code`),
  KEY `idx_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='字典表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `dictionary_items` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `dictionary_id` int(11) unsigned DEFAULT NULL COMMENT '字典ID',
  `label` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '标签',
  `value` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '值',
  `tag_type` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '标签类型',
  `description` varchar(500) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '描述',
  `status` int(4) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `sort` int(11) DEFAULT '0' COMMENT '排序',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_dict_value` (`tenant_id`,`dictionary_id`,`value`),
  KEY `idx_tenant_dict` (`tenant_id`,`dictionary_id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='字典项表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `feedbacks` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '用户ID',
  `type` varchar(30) COLLATE utf8mb4_0900_ai_ci DEFAULT 'suggestion' COMMENT '类型：suggestion/bug/complaint/other',
  `content` text COLLATE utf8mb4_0900_ai_ci COMMENT '反馈内容',
  `images` text COLLATE utf8mb4_0900_ai_ci COMMENT '图片路径JSON数组',
  `contact` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '联系方式',
  `status` tinyint(4) DEFAULT '0' COMMENT '状态：0待处理 1处理中 2已回复 3已关闭',
  `reply` text COLLATE utf8mb4_0900_ai_ci COMMENT '管理员回复',
  `replied_at` datetime DEFAULT NULL COMMENT '回复时间',
  `replied_by` int(11) unsigned DEFAULT NULL COMMENT '回复人ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_tenant_type` (`tenant_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户反馈表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `files` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `name` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '文件名称',
  `path` varchar(500) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '文件路径',
  `url` varchar(500) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '文件URL',
  `mime_type` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'MIME类型',
  `extension` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '文件扩展名',
  `size` bigint(20) unsigned DEFAULT NULL COMMENT '文件大小(字节)',
  `group` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT '默认' COMMENT '文件分组',
  `category_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '分类ID，0=未分类',
  `upload_by` int(11) unsigned DEFAULT NULL COMMENT '上传人',
  `storage` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT 'local' COMMENT '存储方式',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_group` (`tenant_id`,`group`),
  KEY `idx_tenant_category` (`tenant_id`,`category_id`),
  KEY `idx_tenant_mime` (`tenant_id`,`mime_type`),
  KEY `idx_tenant_upload` (`tenant_id`,`upload_by`),
  KEY `idx_tenant_created` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='文件表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
-- 文件分类表（素材库层级分类，v2.29.0）
CREATE TABLE `file_categories` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `parent_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '父分类ID，0=顶级',
  `name` varchar(100) COLLATE utf8mb4_0900_ai_ci NOT NULL COMMENT '分类名称',
  `sort` int(11) NOT NULL DEFAULT '0' COMMENT '排序',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_parent` (`tenant_id`,`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='文件分类表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `menus` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `parent_id` bigint(20) DEFAULT '0' COMMENT '父级ID',
  `code` varchar(80) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '插件菜单唯一标识，与 plugin.json menus[].code 对应',
  `type` int(4) DEFAULT NULL COMMENT '类型:1目录,2菜单,3按钮',
  `title` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '菜单标题',
  `name` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '路由名称',
  `path` varchar(200) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '路由路径',
  `component` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '组件路径',
  `redirect` varchar(200) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '重定向地址',
  `icon` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '图标',
  `permission` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '权限标识',
  `is_hidden` int(4) DEFAULT '0' COMMENT '是否隐藏:0否,1是',
  `is_cache` int(4) DEFAULT '1' COMMENT '是否缓存:0否,1是',
  `is_affix` int(4) DEFAULT '0' COMMENT '是否固定:0否,1是',
  `is_iframe` int(4) DEFAULT '0' COMMENT '是否内嵌:0否,1是',
  `external_link` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '外链地址',
  `breadcrumb` int(4) DEFAULT '1' COMMENT '是否显示面包屑:0否,1是',
  `active_menu` varchar(200) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '高亮菜单',
  `meta` json DEFAULT NULL COMMENT '元数据',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `sort` int(11) DEFAULT '0' COMMENT '排序',
  `created_by` bigint(20) DEFAULT NULL COMMENT '创建人',
  `updated_by` bigint(20) DEFAULT NULL COMMENT '更新人',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_code` (`tenant_id`,`code`),
  KEY `idx_tenant_parent` (`tenant_id`,`parent_id`),
  KEY `idx_tenant_type` (`tenant_id`,`type`),
  KEY `idx_tenant_name` (`tenant_id`,`name`),
  KEY `idx_tenant_path` (`tenant_id`,`path`),
  KEY `idx_tenant_perm` (`tenant_id`,`permission`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_tenant_sort` (`tenant_id`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='菜单表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `template_id` bigint(20) unsigned DEFAULT NULL COMMENT '模板ID',
  `template_code` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '模板编码',
  `channel` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '发送渠道',
  `receiver` varchar(200) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '接收者',
  `content` text COLLATE utf8mb4_0900_ai_ci COMMENT '发送内容',
  `variables` json DEFAULT NULL COMMENT '模板变量',
  `status` int(4) DEFAULT '0' COMMENT '状态:0待发,1成功,2失败',
  `error_msg` text COLLATE utf8mb4_0900_ai_ci COMMENT '错误信息',
  `sent_at` datetime DEFAULT NULL COMMENT '发送时间',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_template` (`tenant_id`,`template_id`),
  KEY `idx_tenant_channel` (`tenant_id`,`channel`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_tenant_created` (`tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='消息发送日志表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message_templates` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `name` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '模板名称',
  `code` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '模板编码',
  `sms_enabled` int(4) DEFAULT '0' COMMENT '短信启用:0否,1是',
  `sms_template_id` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '短信模板ID',
  `sms_content` varchar(500) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '短信内容',
  `wechat_official_enabled` int(4) DEFAULT '0' COMMENT '公众号模板消息启用:0否,1是',
  `wechat_official_template_id` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '公众号模板ID',
  `wechat_official_url` varchar(500) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '公众号模板跳转URL',
  `wechat_mini_enabled` int(4) DEFAULT '0' COMMENT '小程序订阅消息启用:0否,1是',
  `wechat_mini_template_id` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '小程序模板ID',
  `wechat_mini_page` varchar(200) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '小程序跳转页面',
  `variables` json DEFAULT NULL COMMENT '模板变量',
  `remark` varchar(500) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '备注',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_code` (`tenant_id`,`code`),
  KEY `idx_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='消息模板表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `message_events` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `event_code` varchar(50) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `channel` varchar(30) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '' COMMENT 'wechat_oa/wechat_miniapp/sms',
  `template_id` int(11) NOT NULL DEFAULT '0',
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `variable_mapping` text COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'JSON mapping',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_event_channel` (`tenant_id`,`event_code`,`channel`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='消息事件-模板绑定配置';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `version` bigint(20) NOT NULL,
  `migration_name` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `start_time` timestamp NULL DEFAULT NULL,
  `end_time` timestamp NULL DEFAULT NULL,
  `breakpoint` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notification_reads` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `notification_id` int(11) unsigned DEFAULT NULL COMMENT '通知ID',
  `admin_id` int(11) unsigned DEFAULT NULL COMMENT '管理员ID',
  `read_at` datetime DEFAULT NULL COMMENT '阅读时间',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_notif_admin` (`tenant_id`,`notification_id`,`admin_id`),
  KEY `idx_tenant_admin` (`tenant_id`,`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='通知已读记录表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `notifications` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `title` varchar(200) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '通知标题',
  `content` text COLLATE utf8mb4_0900_ai_ci COMMENT '通知内容',
  `type` int(4) DEFAULT '1' COMMENT '类型:1系统,2待办,3业务',
  `sender_id` int(11) unsigned DEFAULT NULL COMMENT '发送人ID',
  `target_type` int(4) DEFAULT '1' COMMENT '目标类型:1全部,2指定',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_type` (`tenant_id`,`type`),
  KEY `idx_tenant_sender` (`tenant_id`,`sender_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='通知表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `payment_orders` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '用户ID',
  `biz_type` varchar(30) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '业务类型',
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `order_no` varchar(64) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '订单号',
  `trade_no` varchar(128) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '交易号',
  `channel` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '支付渠道',
  `trade_type` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '交易类型',
  `subject` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '订单标题',
  `body` varchar(500) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '订单描述',
  `total_amount` decimal(10,2) DEFAULT '0.00' COMMENT '总金额',
  `refund_amount` decimal(10,2) DEFAULT '0.00' COMMENT '退款金额',
  `status` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT 'pending' COMMENT '状态',
  `notify_data` text COLLATE utf8mb4_0900_ai_ci COMMENT '通知数据',
  `extra` text COLLATE utf8mb4_0900_ai_ci COMMENT '扩展数据',
  `paid_at` datetime DEFAULT NULL COMMENT '支付时间',
  `refunded_at` datetime DEFAULT NULL COMMENT '退款时间',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_order_no` (`tenant_id`,`order_no`),
  KEY `idx_tenant_trade_no` (`tenant_id`,`trade_no`),
  KEY `idx_tenant_channel` (`tenant_id`,`channel`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_tenant_created` (`tenant_id`,`created_at`),
  KEY `idx_tenant_user_id` (`tenant_id`,`user_id`),
  KEY `idx_tenant_biz_type` (`tenant_id`,`biz_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='支付订单表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `permissions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `name` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '权限标识',
  `title` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '权限名称',
  `group` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '权限分组',
  `description` text COLLATE utf8mb4_0900_ai_ci COMMENT '权限描述',
  `guard_name` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT 'admin' COMMENT '守卫名称',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `sort` int(11) DEFAULT '0' COMMENT '排序',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_name` (`tenant_id`,`name`),
  KEY `idx_tenant_group` (`tenant_id`,`group`),
  KEY `idx_tenant_guard` (`tenant_id`,`guard_name`),
  KEY `idx_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='权限表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `plans` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '套餐 code',
  `name` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '套餐名称',
  `description` text COLLATE utf8mb4_0900_ai_ci,
  `price_monthly` decimal(10,2) DEFAULT '0.00',
  `price_yearly` decimal(10,2) DEFAULT '0.00',
  `storage_limit_bytes` bigint(20) unsigned DEFAULT '0',
  `sort` int(11) DEFAULT '0',
  `status` int(1) DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status_sort` (`status`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='套餐';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_admin_roles` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `platform_admin_id` int(10) unsigned DEFAULT NULL,
  `platform_role_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_admin_role` (`platform_admin_id`,`platform_role_id`),
  KEY `idx_role` (`platform_role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='平台超管-角色关联';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_admins` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `real_name` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `avatar` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `mobile` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `last_login_ip` varchar(45) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `last_login_at` datetime DEFAULT NULL,
  `login_count` int(11) DEFAULT '0',
  `status` int(1) DEFAULT '1',
  `is_super` int(1) DEFAULT '0' COMMENT '是否超级管理员 1=是 0=否',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  UNIQUE KEY `uk_mobile` (`mobile`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='平台超管账号';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_login_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `platform_admin_id` int(10) unsigned DEFAULT '0',
  `ip` varchar(45) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `user_agent` text COLLATE utf8mb4_0900_ai_ci,
  `status` int(1) DEFAULT '1' COMMENT '1=成功 0=失败',
  `message` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin_time` (`platform_admin_id`,`created_at`),
  KEY `idx_status_time` (`status`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='平台登录日志';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_menus` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(10) unsigned DEFAULT '0',
  `name` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `path` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `component` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `icon` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `sort` int(11) DEFAULT '0',
  `permission` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `type` int(1) DEFAULT '2' COMMENT '1=目录 2=菜单 3=按钮',
  `hidden` int(1) DEFAULT '0',
  `status` int(1) DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_parent_sort` (`parent_id`,`sort`),
  KEY `idx_permission` (`permission`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='平台菜单';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_operation_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `platform_admin_id` int(10) unsigned DEFAULT '0',
  `method` varchar(10) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `path` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `params` text COLLATE utf8mb4_0900_ai_ci,
  `ip` varchar(45) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `target_tenant_id` int(10) unsigned DEFAULT '0' COMMENT '跨租户操作时记录',
  `response_code` int(11) DEFAULT '200',
  `duration_ms` int(11) DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_admin_time` (`platform_admin_id`,`created_at`),
  KEY `idx_tenant_time` (`target_tenant_id`,`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='平台操作日志';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_announcements` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(200) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `content` text COLLATE utf8mb4_0900_ai_ci NOT NULL,
  `type` varchar(20) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'notice' COMMENT 'notice/update/warning',
  `status` varchar(20) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'draft' COMMENT 'draft/published',
  `published_at` datetime DEFAULT NULL,
  `created_by` int(11) NOT NULL DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_published` (`status`,`published_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='平台公告';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_announcement_reads` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `announcement_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `admin_id` int(11) NOT NULL,
  `read_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ann_tenant_admin` (`announcement_id`,`tenant_id`,`admin_id`),
  KEY `idx_tenant_admin` (`tenant_id`,`admin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='平台公告已读记录';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_permissions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `code` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `group` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `status` int(1) DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_group_status` (`group`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='平台权限';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_role_menus` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `platform_role_id` int(10) unsigned DEFAULT NULL,
  `platform_menu_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_menu` (`platform_role_id`,`platform_menu_id`),
  KEY `idx_menu` (`platform_menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='平台角色-菜单关联';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_role_permissions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `platform_role_id` int(10) unsigned DEFAULT NULL,
  `platform_permission_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_perm` (`platform_role_id`,`platform_permission_id`),
  KEY `idx_perm` (`platform_permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='平台角色-权限关联';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `platform_roles` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `code` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL,
  `description` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `sort` int(11) DEFAULT '0',
  `status` int(1) DEFAULT '1',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='平台角色';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `points_logs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '用户ID',
  `points` int(11) DEFAULT NULL COMMENT '变动积分',
  `before_points` int(11) DEFAULT NULL COMMENT '变动前积分',
  `after_points` int(11) DEFAULT NULL COMMENT '变动后积分',
  `type` tinyint(4) DEFAULT '1' COMMENT '类型:1后台调整,2注册赠送,3签到,4消费赠送,5消费扣减',
  `source` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT '' COMMENT '来源标识',
  `remark` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT '' COMMENT '备注',
  `operator_id` int(11) unsigned DEFAULT NULL COMMENT '操作管理员ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_tenant_type` (`tenant_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='积分变动记录';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `regions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `parent_id` int(11) unsigned DEFAULT '0' COMMENT '父级ID',
  `name` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '名称',
  `code` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT '' COMMENT '编码',
  `level` tinyint(4) DEFAULT '1' COMMENT '层级：1省 2市 3区',
  `sort` int(11) DEFAULT '0' COMMENT '排序',
  `status` tinyint(4) DEFAULT '1' COMMENT '状态：0禁用 1启用',
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`),
  KEY `parent_id` (`parent_id`),
  KEY `level` (`level`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='地区表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `refund_orders` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `refund_no` varchar(64) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '' COMMENT '退款单号',
  `order_type` varchar(20) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '' COMMENT 'saas/business',
  `original_order_id` int(11) NOT NULL COMMENT '原订单ID',
  `tenant_id` int(11) NOT NULL DEFAULT '0',
  `refund_amount` int(11) NOT NULL COMMENT '退款金额（分）',
  `reason` varchar(500) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `status` varchar(20) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'pending' COMMENT 'pending/processing/success/failed',
  `payment_channel` varchar(20) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '' COMMENT 'wechat/alipay',
  `channel_refund_no` varchar(100) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `operated_by` int(11) NOT NULL DEFAULT '0',
  `error_msg` varchar(500) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '',
  `refunded_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_refund_no` (`refund_no`),
  KEY `idx_order` (`order_type`,`original_order_id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='退款订单';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_menus` (
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `role_id` bigint(20) unsigned NOT NULL COMMENT '角色ID',
  `menu_id` bigint(20) unsigned NOT NULL COMMENT '菜单ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`role_id`,`menu_id`),
  KEY `idx_tenant_role` (`tenant_id`,`role_id`),
  KEY `idx_tenant_menu` (`tenant_id`,`menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='角色菜单关联表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `role_permissions` (
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `role_id` bigint(20) unsigned NOT NULL COMMENT '角色ID',
  `permission_id` bigint(20) unsigned NOT NULL COMMENT '权限ID',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `idx_tenant_role` (`tenant_id`,`role_id`),
  KEY `idx_tenant_perm` (`tenant_id`,`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='角色权限关联表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `roles` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `name` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '角色标识',
  `title` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '角色名称',
  `description` text COLLATE utf8mb4_0900_ai_ci COMMENT '角色描述',
  `data_scope` int(4) DEFAULT '1' COMMENT '数据范围:1全部,2自定义,3本部门,4部门及以下,5仅自己',
  `is_system` int(4) DEFAULT '0' COMMENT '是否系统角色',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `sort` int(11) DEFAULT '0' COMMENT '排序',
  `created_by` bigint(20) DEFAULT NULL COMMENT '创建人',
  `updated_by` bigint(20) DEFAULT NULL COMMENT '更新人',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_name` (`tenant_id`,`name`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_tenant_sort` (`tenant_id`,`sort`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='角色表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `saas_orders` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_no` varchar(64) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '订单号',
  `tenant_id` int(10) unsigned DEFAULT NULL COMMENT '租户ID',
  `plan_id` int(10) unsigned DEFAULT NULL COMMENT '套餐ID',
  `type` int(1) DEFAULT NULL COMMENT '1=新购 2=续费 3=升级 4=增值功能',
  `plugin_id` int unsigned DEFAULT NULL COMMENT '插件ID（type=4 时）',
  `months` int(11) DEFAULT '1' COMMENT '购买月数',
  `amount` decimal(10,2) DEFAULT NULL COMMENT '订单金额',
  `paid_amount` decimal(10,2) DEFAULT '0.00' COMMENT '实付金额',
  `payment_channel` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT '' COMMENT 'wechat / alipay',
  `payment_method` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT '' COMMENT 'native / jsapi / pc / wap',
  `prepay_id` varchar(128) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `transaction_id` varchar(128) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `status` int(1) DEFAULT '1' COMMENT '1=待支付 2=已支付 3=已取消 4=已退款',
  `paid_at` datetime DEFAULT NULL COMMENT '支付时间',
  `expired_at` datetime DEFAULT NULL COMMENT '支付二维码过期时间',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_status_expired` (`status`,`expired_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='SaaS 订单';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `subscriptions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned DEFAULT NULL COMMENT '租户ID',
  `plan_id` int(10) unsigned DEFAULT NULL COMMENT '套餐ID',
  `type` int(1) DEFAULT NULL COMMENT '1=试用 2=正式 3=续费 4=升级',
  `starts_at` datetime DEFAULT NULL COMMENT '订阅开始时间',
  `ends_at` datetime DEFAULT NULL COMMENT '订阅结束时间',
  `status` int(1) DEFAULT '1' COMMENT '1=生效 2=已结束 3=已退款',
  `order_id` int(10) unsigned DEFAULT '0' COMMENT '关联 saas_orders.id，0=无',
  `operator_id` int(10) unsigned DEFAULT '0' COMMENT 'platform_admins.id, 0=系统',
  `remark` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT '',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_ends_at` (`ends_at`),
  KEY `idx_order` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='订阅记录';
/*!40101 SET character_set_client = @saved_cs_client */;
CREATE TABLE `system_configs` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `config_key` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '配置键',
  `config_value` text COLLATE utf8mb4_0900_ai_ci COMMENT '配置值',
  `config_group` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT 'basic' COMMENT '配置分组',
  `config_type` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT 'string' COMMENT '配置类型',
  `config_name` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '配置名称',
  `config_desc` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '配置描述',
  `config_options` json DEFAULT NULL COMMENT '配置选项',
  `config_depends` json DEFAULT NULL COMMENT '配置依赖',
  `sort_order` int(11) DEFAULT '0' COMMENT '排序',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_key` (`tenant_id`,`config_key`),
  KEY `idx_tenant_group` (`tenant_id`,`config_group`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_tenant_sort` (`tenant_id`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='系统配置表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenant_usage_daily` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned DEFAULT NULL,
  `date` date DEFAULT NULL COMMENT '统计日期',
  `storage_used_bytes` bigint(20) unsigned DEFAULT '0',
  `admin_count` int(11) unsigned DEFAULT '0',
  `user_count` int(11) unsigned DEFAULT '0',
  `request_count` int(11) unsigned DEFAULT '0',
  `created_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_date` (`tenant_id`,`date`),
  KEY `idx_date` (`date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='租户用量快照（每日 cron 写入，dashboard 展示用）';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tenants` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_code` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '子域名 code',
  `name` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '租户名称',
  `logo` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT '' COMMENT '租户 Logo URL',
  `contact_name` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT '' COMMENT '联系人',
  `contact_phone` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT '' COMMENT '联系电话',
  `plan_id` int(10) unsigned DEFAULT '0' COMMENT '套餐ID',
  `status` int(1) DEFAULT '1' COMMENT '硬开关 1=启用 0=禁用',
  `expires_at` datetime DEFAULT NULL COMMENT '到期时间',
  `storage_used_bytes` bigint(20) unsigned DEFAULT '0' COMMENT '已用存储字节',
  `storage_limit_bytes` bigint(20) unsigned DEFAULT '0' COMMENT '配额字节，从 plan 同步并支持手工覆盖',
  `custom_settings` text COLLATE utf8mb4_0900_ai_ci COMMENT '租户自定义设置 JSON',
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_code` (`tenant_code`),
  KEY `idx_status_expires` (`status`,`expires_at`),
  KEY `idx_plan` (`plan_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='租户主表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_notification_reads` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `notification_id` int(11) unsigned DEFAULT NULL COMMENT '通知ID',
  `user_id` int(11) unsigned DEFAULT NULL COMMENT '用户ID',
  `read_at` datetime DEFAULT NULL COMMENT '阅读时间',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_notif_user` (`tenant_id`,`notification_id`,`user_id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户通知已读记录表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_notifications` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `user_id` int(11) unsigned DEFAULT '0' COMMENT '用户ID，0为全体',
  `title` varchar(200) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '标题',
  `content` text COLLATE utf8mb4_0900_ai_ci COMMENT '内容',
  `type` varchar(30) COLLATE utf8mb4_0900_ai_ci DEFAULT 'system' COMMENT '类型：system/order/payment/feedback',
  `biz_id` int(11) DEFAULT NULL COMMENT '关联业务ID',
  `extra` text COLLATE utf8mb4_0900_ai_ci COMMENT '额外数据JSON',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  KEY `idx_tenant_type` (`tenant_id`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户站内通知表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `nickname` varchar(50) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '昵称',
  `avatar` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '头像',
  `mobile` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '手机号',
  `email` varchar(100) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '邮箱',
  `password` varchar(255) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '密码',
  `gender` int(4) DEFAULT '0' COMMENT '性别:0未知,1男,2女',
  `birthday` date DEFAULT NULL COMMENT '生日',
  `openid` varchar(128) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '公众号OpenID',
  `unionid` varchar(128) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT 'UnionID',
  `mini_openid` varchar(128) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '小程序OpenID',
  `last_login_ip` varchar(45) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '最后登录IP',
  `last_login_time` datetime DEFAULT NULL COMMENT '最后登录时间',
  `login_count` int(11) DEFAULT '0' COMMENT '登录次数',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `balance` decimal(10,2) DEFAULT '0.00' COMMENT '余额',
  `points` int(11) DEFAULT '0' COMMENT '积分',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_mobile` (`tenant_id`,`mobile`),
  KEY `idx_tenant_openid` (`tenant_id`,`openid`),
  KEY `idx_tenant_unionid` (`tenant_id`,`unionid`),
  KEY `idx_tenant_mini_openid` (`tenant_id`,`mini_openid`),
  KEY `idx_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='用户表';
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `wechat_auto_replies` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `type` varchar(20) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '回复类型',
  `keyword` varchar(200) COLLATE utf8mb4_0900_ai_ci DEFAULT NULL COMMENT '关键词',
  `match_type` varchar(10) COLLATE utf8mb4_0900_ai_ci DEFAULT 'exact' COMMENT '匹配方式',
  `reply_type` varchar(10) COLLATE utf8mb4_0900_ai_ci DEFAULT 'text' COMMENT '回复内容类型',
  `content` text COLLATE utf8mb4_0900_ai_ci COMMENT '回复内容',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `sort_order` int(11) DEFAULT '0' COMMENT '排序',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_type` (`tenant_id`,`type`),
  KEY `idx_tenant_keyword` (`tenant_id`,`keyword`),
  KEY `idx_tenant_status` (`tenant_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='微信自动回复表';
/*!40101 SET character_set_client = @saved_cs_client */;
-- ===========================================
-- 插件系统（Stage 1）
-- ===========================================

DROP TABLE IF EXISTS `plugins`;
CREATE TABLE `plugins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `code` varchar(80) NOT NULL,
  `name` varchar(100) NOT NULL,
  `version` varchar(20) NOT NULL,
  `author` varchar(100) NOT NULL DEFAULT '',
  `description` varchar(500) NOT NULL DEFAULT '',
  `icon` varchar(255) NOT NULL DEFAULT '',
  `type` tinyint NOT NULL DEFAULT 1 COMMENT '1=业务功能 2=通讯/通知 3=支付 4=增值服务',
  `kind` varchar(16) NOT NULL DEFAULT 'plugin' COMMENT 'app|plugin',
  `entitlement` varchar(80) NOT NULL DEFAULT '' COMMENT '权益标识符，由 EntitlementService 计算',
  `depends` JSON DEFAULT NULL,
  `source` tinyint NOT NULL DEFAULT 1 COMMENT '1=zip 上传 2=仓库内置',
  `distribution_source` VARCHAR(20)  NOT NULL DEFAULT 'zip',
  `remote_app_id`       VARCHAR(64)  DEFAULT NULL,
  `remote_version_id`   VARCHAR(64)  DEFAULT NULL,
  `publisher_name`      VARCHAR(80)  DEFAULT NULL,
  `installed_hash`      VARCHAR(64)  DEFAULT NULL,
  `signature_status`    VARCHAR(16)  DEFAULT NULL,
  `latest_version`      VARCHAR(20)  DEFAULT NULL,
  `update_available`    TINYINT      NOT NULL DEFAULT 0,
  `license_status`            VARCHAR(16)  NOT NULL DEFAULT 'active' COMMENT 'active/grace/expired，仅 distribution_source=marketplace 时有意义',
  `license_grace_started_at`  DATETIME     DEFAULT NULL COMMENT '进入 grace 的时间，倒计时基准',
  `license_last_check_at`     DATETIME     DEFAULT NULL COMMENT 'RuntimeLicenseEvaluator 上次评估时间',
  `read_safe_routes`          JSON         DEFAULT NULL COMMENT 'JSON 数组：plugin.json 同步的只读豁免路由 pattern',
  `package_path` varchar(255) NOT NULL DEFAULT '',
  `package_hash` char(64) NOT NULL DEFAULT '',
  `manifest` json NOT NULL,
  `requires` json DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1=已启用 2=已禁用 3=安装中 4=升级中 5=安装失败',
  `last_error` varchar(1000) NOT NULL DEFAULT '',
  `installed_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_status_type` (`status`,`type`),
  KEY `idx_kind` (`kind`),
  KEY `idx_distribution` (`distribution_source`),
  KEY `idx_remote_app`   (`remote_app_id`),
  KEY `idx_distribution_license` (`distribution_source`, `license_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='插件主表';

DROP TABLE IF EXISTS `plugin_grants`;
CREATE TABLE `plugin_grants` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plan_id` int unsigned NOT NULL,
  `plugin_id` int unsigned NOT NULL,
  `plugin_code` varchar(80) NOT NULL,
  `auto_enable` tinyint NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_plan_plugin` (`plan_id`,`plugin_id`),
  KEY `idx_plugin` (`plugin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='套餐插件授权';

DROP TABLE IF EXISTS `tenant_plugins`;
CREATE TABLE `tenant_plugins` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `plugin_id` int unsigned NOT NULL,
  `plugin_code` varchar(80) NOT NULL,
  `status` tinyint NOT NULL DEFAULT 0,
  `source` tinyint NOT NULL DEFAULT 1,
  `enabled_at` datetime DEFAULT NULL,
  `expired_at` datetime DEFAULT NULL,
  `installed_version` varchar(20) NOT NULL,
  `testdata_imported_at` datetime DEFAULT NULL COMMENT '演示数据导入时间（NULL=未导入）',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_plugin` (`tenant_id`,`plugin_id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`),
  KEY `idx_expired` (`expired_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='租户插件启用记录';

DROP TABLE IF EXISTS `tenant_plugin_configs`;
CREATE TABLE `tenant_plugin_configs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL,
  `plugin_code` varchar(80) NOT NULL,
  `config_key` varchar(100) NOT NULL,
  `config_value` text,
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_plugin_key` (`tenant_id`,`plugin_code`,`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='租户插件配置';

DROP TABLE IF EXISTS `tenant_mobile_configs`;
CREATE TABLE `tenant_mobile_configs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL COMMENT '租户 ID（一对一）',
  `app_name` varchar(100) NOT NULL DEFAULT '' COMMENT '小程序/App 应用名（显示用）',
  `app_logo` varchar(500) NOT NULL DEFAULT '' COMMENT '应用 Logo URL',
  `theme_color` varchar(16) NOT NULL DEFAULT '' COMMENT '主题色=主色（兼容旧字段，与 theme_colors.primary 同步）',
  `theme_colors` json DEFAULT NULL COMMENT '主题色板 {primary,dark,price,page_bg,button_text,badge}',
  `app_intro` varchar(255) NOT NULL DEFAULT '' COMMENT '应用简介',
  `service_type` varchar(16) NOT NULL DEFAULT '' COMMENT '客服方式 ""/online/wechat/phone',
  `service_phone` varchar(32) NOT NULL DEFAULT '' COMMENT '客服电话',
  `share_title` varchar(200) NOT NULL DEFAULT '' COMMENT '默认分享标题',
  `share_image` varchar(500) NOT NULL DEFAULT '' COMMENT '默认分享图',
  `home_app_code` varchar(80) NOT NULL DEFAULT '' COMMENT '启动首页所属插件 code（用于权益校验快速过滤）',
  `home_page` varchar(200) NOT NULL DEFAULT '' COMMENT '启动首页完整路径，如 modules/mall/pages/home/index',
  `tabbar_json` json DEFAULT NULL COMMENT 'tabBar 配置 [{code,path,text,icon,selected_icon,sel_label,badge}, ...]',
  `tabbar_style` json DEFAULT NULL COMMENT 'tabBar 整体样式 {text_color,active_color,bg_color}',
  `wechat_appid` varchar(64) NOT NULL DEFAULT '' COMMENT '小程序 AppID（Phase C 才使用）',
  `wechat_upload_key_ciphertext` text COMMENT '加密后的小程序上传私钥（AES-256-CBC + base64）',
  `wechat_upload_version` varchar(32) NOT NULL DEFAULT '1.0.0' COMMENT '小程序上传版本号（语义化 x.y.z，成功后自动 patch+1）',
  `wechat_upload_desc` varchar(200) NOT NULL DEFAULT '租户后台发布' COMMENT '小程序上传项目备注',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1=启用 0=禁用',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='租户移动端配置（启动首页、主题、tabBar 等）';

DROP TABLE IF EXISTS `tenant_pc_configs`;
CREATE TABLE `tenant_pc_configs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL COMMENT '租户 ID（一对一）',
  `site_name` varchar(100) NOT NULL DEFAULT '' COMMENT 'PC 站点名称',
  `site_logo` varchar(500) NOT NULL DEFAULT '' COMMENT 'PC 站点 Logo URL',
  `site_intro` varchar(255) NOT NULL DEFAULT '' COMMENT 'PC 站点简介',
  `theme_color` varchar(16) NOT NULL DEFAULT '#2563eb' COMMENT 'PC 主题色',
  `home_type` varchar(20) NOT NULL DEFAULT 'diy' COMMENT '首页类型：diy/app/redirect',
  `home_app_code` varchar(80) NOT NULL DEFAULT '' COMMENT '首页所属插件 entitlement/code',
  `home_page` varchar(200) NOT NULL DEFAULT 'home' COMMENT '首页路径或装修 page_key',
  `nav_json` json DEFAULT NULL COMMENT 'PC 导航 [{label,path,code,auth,sort}]',
  `seo_json` json DEFAULT NULL COMMENT 'PC SEO 配置 {title,keywords,description}',
  `login_enabled` tinyint NOT NULL DEFAULT 1 COMMENT '是否显示登录入口',
  `register_enabled` tinyint NOT NULL DEFAULT 1 COMMENT '是否显示注册入口',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1=启用 0=禁用',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant` (`tenant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='租户 PC 前台配置';

DROP TABLE IF EXISTS `tenant_mobile_builds`;
CREATE TABLE `tenant_mobile_builds` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL COMMENT '租户 ID',
  `plan_id` int unsigned NOT NULL DEFAULT 0 COMMENT '套餐 ID 快照（决定权益范围）',
  `platform` varchar(20) NOT NULL COMMENT 'h5 / mp-weixin / app',
  `build_no` varchar(32) NOT NULL COMMENT '本租户内自增编号（如 20270615-001）',
  `status` tinyint NOT NULL DEFAULT 0 COMMENT '0=queued 1=running 2=success 3=failed 4=uploaded 5=released',
  `included_plugins_json` json DEFAULT NULL COMMENT '本次构建合入的插件 code + version 快照',
  `pages_json` json DEFAULT NULL COMMENT '生成的 pages.json 快照',
  `manifest_json` json DEFAULT NULL COMMENT '生成的 manifest.json 快照',
  `artifact_path` varchar(500) NOT NULL DEFAULT '' COMMENT '构建产物绝对路径',
  `driver` varchar(20) DEFAULT NULL COMMENT '构建所用 driver：local/docker/remote',
  `remote_job_id` varchar(100) DEFAULT NULL COMMENT 'remote driver 的远端 job id',
  `artifact_url` varchar(500) DEFAULT NULL COMMENT 'remote artifact 下载 URL（相对路径）',
  `runtime_json` json DEFAULT NULL COMMENT 'driver 运行时元数据（driver/image/节点/耗时等）',
  `upload_result_json` json DEFAULT NULL COMMENT 'miniprogram-ci 上传结果（含体验码 URL）',
  `error_log` text COMMENT '构建/上传 stdout+stderr 摘要（最多 50KB）',
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `operator_id` int unsigned DEFAULT NULL COMMENT '触发构建的 admin_id',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenant_build_no` (`tenant_id`,`build_no`),
  KEY `idx_tenant_platform_status` (`tenant_id`,`platform`,`status`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='租户移动端编译任务';

DROP TABLE IF EXISTS `plugin_builds`;
CREATE TABLE `plugin_builds` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `target` varchar(20) NOT NULL,
  `trigger` varchar(30) NOT NULL,
  `plugin_id` int unsigned DEFAULT NULL,
  `status` tinyint NOT NULL DEFAULT 0,
  `log` longtext,
  `artifact_path` varchar(255) NOT NULL DEFAULT '',
  `started_at` datetime DEFAULT NULL,
  `finished_at` datetime DEFAULT NULL,
  `operator_id` int unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_plugin` (`plugin_id`),
  KEY `idx_target_status` (`target`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='云编译任务';

DROP TABLE IF EXISTS `plugin_migrations`;
CREATE TABLE `plugin_migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `plugin_id` int unsigned NOT NULL,
  `plugin_code` varchar(64) NOT NULL,
  `migration_name` varchar(191) NOT NULL,
  `file_hash` char(64) NOT NULL,
  `direction` enum('up','down') NOT NULL,
  `plugin_version` varchar(32) DEFAULT NULL,
  `duration_ms` int unsigned DEFAULT NULL,
  `status` enum('success','failed') NOT NULL,
  `error_msg` text,
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_plugin_migration_dir_status` (`plugin_id`,`migration_name`,`direction`,`status`),
  KEY `idx_plugin_code` (`plugin_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='v2.7.0：插件 migration 执行状态表';

DROP TABLE IF EXISTS `plugin_menus`;
CREATE TABLE `plugin_menus` (
  `plugin_id` int unsigned NOT NULL,
  `menu_id` int unsigned NOT NULL,
  `sort` int NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`plugin_id`,`menu_id`),
  KEY `idx_plugin` (`plugin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='App 安装时贡献的菜单关联';

-- ============================================================
-- v2.8.0：官方市场客户端
-- ============================================================

CREATE TABLE IF NOT EXISTS `marketplace_connections` (
  `id`                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `instance_uuid`            CHAR(36)     NOT NULL,
  `instance_name`            VARCHAR(120) NOT NULL,
  `site_base_url`            VARCHAR(200) NOT NULL,
  `encrypted_instance_token` TEXT         NOT NULL,
  `token_prefix`             VARCHAR(16)  NOT NULL,
  `bound_user_email`         VARCHAR(120) DEFAULT NULL,
  `bound_user_name`          VARCHAR(60)  DEFAULT NULL,
  `status`                   VARCHAR(16)  NOT NULL DEFAULT 'active'
    COMMENT 'active/suspended/revoked',
  `last_heartbeat_at`        DATETIME     DEFAULT NULL,
  `last_sync_at`             DATETIME     DEFAULT NULL,
  `last_error`               VARCHAR(500) DEFAULT NULL,
  `token_rotated_at`         DATETIME     DEFAULT NULL COMMENT 'token 上次轮换时间，绑定时初始化为 created_at',
  `token_expires_at`         DATETIME     DEFAULT NULL COMMENT 'Site 返回的 token 名义过期时间',
  `created_at`               DATETIME     NOT NULL,
  `updated_at`               DATETIME     NOT NULL,
  `deleted_at`               DATETIME     DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_instance_uuid` (`instance_uuid`),
  KEY `idx_status` (`status`, `deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='官方市场 Site 连接';

CREATE TABLE IF NOT EXISTS `marketplace_app_cache` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `connection_id`     INT UNSIGNED NOT NULL,
  `entitlement_code`  VARCHAR(64)  NOT NULL,
  `remote_app_id`     VARCHAR(64)  NOT NULL,
  `app_code`          VARCHAR(80)  NOT NULL,
  `app_name`          VARCHAR(120) NOT NULL,
  `app_description`   VARCHAR(500) DEFAULT NULL,
  `app_icon_url`      VARCHAR(255) DEFAULT NULL,
  `publisher_name`    VARCHAR(80)  DEFAULT NULL,
  `plan_code`         VARCHAR(64)  DEFAULT NULL,
  `billing_cycle`     VARCHAR(16)  DEFAULT NULL,
  `period_end`        DATETIME     DEFAULT NULL,
  `latest_version`    VARCHAR(20)  DEFAULT NULL,
  `latest_version_id` VARCHAR(64)  DEFAULT NULL,
  `compatible`        TINYINT      NOT NULL DEFAULT 1,
  `synced_at`         DATETIME     NOT NULL,
  `created_at`        DATETIME     NOT NULL,
  `updated_at`        DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_conn_entitlement` (`connection_id`, `entitlement_code`),
  KEY `idx_app_code` (`app_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='已购应用本地缓存';

CREATE TABLE IF NOT EXISTS `marketplace_public_keys` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_id`     VARCHAR(64)  NOT NULL,
  `pem`        TEXT         NOT NULL,
  `fetched_at` DATETIME     NOT NULL,
  `expires_at` DATETIME     NOT NULL,
  `created_at` DATETIME     NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key_id` (`key_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='官方市场签名公钥缓存';

-- ----------------------------
-- 装修页面表
-- ----------------------------
DROP TABLE IF EXISTS `diy_pages`;
CREATE TABLE `diy_pages` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `page_type` varchar(32) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'home' COMMENT '页面类型:home/custom',
  `page_key` varchar(64) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '' COMMENT '页面标识(slug);home固定home',
  `platform` varchar(16) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT 'uniapp' COMMENT '端:uniapp/pc',
  `title` varchar(100) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '' COMMENT '页面名称',
  `components_draft` json DEFAULT NULL COMMENT '草稿组件树',
  `components_published` json DEFAULT NULL COMMENT '已发布组件树',
  `page_settings` json DEFAULT NULL COMMENT '页面设置',
  `status` tinyint(1) DEFAULT '1' COMMENT '状态:1启用,0禁用',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  `updated_at` datetime DEFAULT NULL COMMENT '更新时间',
  `deleted_at` datetime DEFAULT NULL COMMENT '删除时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_pagekey_platform` (`tenant_id`,`page_key`,`platform`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='装修页面表';

-- 装修页面版本快照表
-- ----------------------------
DROP TABLE IF EXISTS `diy_page_versions`;
CREATE TABLE `diy_page_versions` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '租户ID',
  `page_id` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'diy_pages.id',
  `version_no` int(11) NOT NULL DEFAULT '1' COMMENT '版本号(按page递增)',
  `components` json DEFAULT NULL COMMENT '组件树快照',
  `page_settings` json DEFAULT NULL COMMENT '页面设置快照',
  `note` varchar(255) COLLATE utf8mb4_0900_ai_ci NOT NULL DEFAULT '' COMMENT '备注',
  `created_by` bigint(20) DEFAULT NULL COMMENT '创建人admin_id',
  `created_at` datetime DEFAULT NULL COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_tenant_page_version` (`tenant_id`,`page_id`,`version_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='装修页面版本快照表';

DROP TABLE IF EXISTS `diy_links`;
-- 装修链接库（租户运营位/外链/命名快捷链接）
CREATE TABLE `diy_links` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` int unsigned NOT NULL DEFAULT 0,
  `label` varchar(64) NOT NULL DEFAULT '',
  `path` varchar(255) NOT NULL DEFAULT '',
  `category` varchar(32) NOT NULL DEFAULT '我的链接',
  `icon` varchar(64) DEFAULT NULL,
  `sort` int NOT NULL DEFAULT 0,
  `status` tinyint NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tenant_status` (`tenant_id`,`status`,`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='装修链接库';

-- ============================================================
-- 框架数据库升级记录表（php think yd:update 使用）
-- ============================================================
DROP TABLE IF EXISTS `system_upgrades`;
CREATE TABLE `system_upgrades` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(20) NOT NULL COMMENT '已应用的版本号',
  `applied_at` datetime NOT NULL COMMENT '应用时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_version` (`version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='框架数据库升级记录';
