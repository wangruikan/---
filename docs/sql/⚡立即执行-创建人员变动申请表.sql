-- ========================================
-- 人员变动申请表（用于记录工资表导入时发现的人员差异）
-- ========================================

-- 创建人员变动申请表
CREATE TABLE IF NOT EXISTS `personnel_change_requests` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(20) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_id` bigint(20) UNSIGNED NOT NULL COMMENT '项目ID',
  `month` varchar(255) NOT NULL COMMENT '工资期间（YYYY-MM）',
  `change_type` enum('add','remove') NOT NULL COMMENT '变动类型：add新增，remove减少',
  `personnel_list` json NOT NULL COMMENT '人员列表 [{"id_card":"xxx", "name":"xxx"}]',
  `remark` text NULL COMMENT '备注',
  `status` varchar(255) NOT NULL DEFAULT 'pending' COMMENT '状态：pending待审批, in_approval审批中, approved已通过, rejected已拒绝',
  `created_by` bigint(20) UNSIGNED NOT NULL COMMENT '创建人',
  `approval_flow_id` bigint(20) UNSIGNED NULL COMMENT '审批流程ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `deleted_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_month_type` (`project_id`, `month`, `change_type`, `deleted_at`),
  KEY `personnel_change_requests_account_set_id_foreign` (`account_set_id`),
  KEY `personnel_change_requests_project_id_foreign` (`project_id`),
  KEY `personnel_change_requests_created_by_foreign` (`created_by`),
  KEY `personnel_change_requests_approval_flow_id_foreign` (`approval_flow_id`),
  CONSTRAINT `personnel_change_requests_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `personnel_change_requests_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `personnel_change_requests_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `personnel_change_requests_approval_flow_id_foreign` FOREIGN KEY (`approval_flow_id`) REFERENCES `approval_instances` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='人员变动申请表';

-- 创建附件表
CREATE TABLE IF NOT EXISTS `personnel_change_request_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `personnel_change_request_id` bigint(20) UNSIGNED NOT NULL COMMENT '人员变动申请ID',
  `file_name` varchar(255) NOT NULL COMMENT '文件名',
  `file_path` varchar(255) NOT NULL COMMENT '文件路径',
  `file_type` varchar(255) NULL COMMENT '文件类型',
  `file_size` bigint(20) NULL COMMENT '文件大小（字节）',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `personnel_change_request_attachments_request_id_foreign` (`personnel_change_request_id`),
  CONSTRAINT `personnel_change_request_attachments_request_id_foreign` FOREIGN KEY (`personnel_change_request_id`) REFERENCES `personnel_change_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='人员变动申请附件表';

-- 验证表是否创建成功
SELECT 
    '人员变动申请表' as table_name,
    COUNT(*) as record_count
FROM personnel_change_requests
UNION ALL
SELECT 
    '人员变动申请附件表' as table_name,
    COUNT(*) as record_count
FROM personnel_change_request_attachments;

