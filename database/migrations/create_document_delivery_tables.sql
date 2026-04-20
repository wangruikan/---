-- ============================================
-- 资料交付管理相关表
-- ============================================

-- 1. 项目资料交付配置表
CREATE TABLE `project_delivery_configs` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(20) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_id` bigint(20) UNSIGNED NOT NULL COMMENT '项目ID',
  `delivery_cycle` enum('monthly', 'quarterly') NOT NULL DEFAULT 'monthly' COMMENT '交付周期：monthly-按月，quarterly-按季度',
  `delivery_method` enum('express', 'electronic') NOT NULL DEFAULT 'electronic' COMMENT '交付方式：express-快递，electronic-电子推送',
  `required_documents` text DEFAULT NULL COMMENT '需交付的资料清单（JSON格式存储）',
  `is_active` tinyint(1) DEFAULT 1 COMMENT '是否启用：1-启用，0-禁用',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '创建人ID',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '更新人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project` (`project_id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_delivery_cycle` (`delivery_cycle`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='项目资料交付配置表';

-- 2. 资料交付记录表
CREATE TABLE `document_deliveries` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `config_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '配置ID',
  `account_set_id` bigint(20) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_id` bigint(20) UNSIGNED NOT NULL COMMENT '项目ID',
  `delivery_cycle` enum('monthly', 'quarterly') NOT NULL COMMENT '交付周期',
  `delivery_method` enum('express', 'electronic') NOT NULL COMMENT '交付方式',
  `delivery_period` varchar(20) NOT NULL COMMENT '交付期间（月份：YYYY-MM，季度：YYYY-Q1/Q2/Q3/Q4）',
  `status` enum('pending', 'submitted', 'completed') NOT NULL DEFAULT 'pending' COMMENT '状态：pending-待交付，submitted-已提交，completed-已完成',
  `handler_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '经办人ID（负责交付的人）',
  `required_documents` text DEFAULT NULL COMMENT '应交付资料清单（JSON格式）',
  `submitted_documents` text DEFAULT NULL COMMENT '已提交资料说明',
  `express_number` varchar(100) DEFAULT NULL COMMENT '快递单号',
  `express_date` date DEFAULT NULL COMMENT '快递寄出日期',
  `submitted_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '提交人ID（经办）',
  `submitted_at` datetime DEFAULT NULL COMMENT '提交时间',
  `completed_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '完成人ID',
  `completed_at` datetime DEFAULT NULL COMMENT '完成时间',
  `remarks` text DEFAULT NULL COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_period` (`project_id`, `delivery_period`),
  KEY `idx_config_id` (`config_id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_delivery_period` (`delivery_period`),
  KEY `idx_status` (`status`),
  KEY `idx_handler_id` (`handler_id`),
  KEY `idx_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资料交付记录表';

-- 3. 资料交付附件表
CREATE TABLE `document_delivery_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `delivery_id` bigint(20) UNSIGNED NOT NULL COMMENT '交付记录ID',
  `filename` varchar(255) NOT NULL COMMENT '文件名',
  `file_path` varchar(500) NOT NULL COMMENT '文件路径',
  `file_size` bigint(20) DEFAULT NULL COMMENT '文件大小（字节）',
  `mime_type` varchar(100) DEFAULT NULL COMMENT '文件类型',
  `uploaded_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '上传人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_delivery_id` (`delivery_id`),
  KEY `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资料交付附件表';

-- 4. 资料交付提醒记录表
CREATE TABLE `document_delivery_reminders` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(20) UNSIGNED NOT NULL COMMENT '账套ID',
  `delivery_id` bigint(20) UNSIGNED NOT NULL COMMENT '交付记录ID',
  `reminder_type` enum('new_period', 'not_submitted') NOT NULL COMMENT '提醒类型：new_period-新周期提醒，not_submitted-未交付提醒',
  `recipient_id` bigint(20) UNSIGNED NOT NULL COMMENT '接收人ID（经办）',
  `is_read` tinyint(1) DEFAULT 0 COMMENT '是否已读：0-未读，1-已读',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_delivery_id` (`delivery_id`),
  KEY `idx_recipient_id` (`recipient_id`),
  KEY `idx_is_read` (`is_read`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='资料交付提醒记录表';

