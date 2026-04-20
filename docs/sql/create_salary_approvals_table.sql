-- 创建工资表审批表
CREATE TABLE `salary_approvals` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(20) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_id` bigint(20) UNSIGNED NOT NULL COMMENT '项目ID',
  `month` varchar(7) NOT NULL COMMENT '工资期间（YYYY-MM）',
  `approval_type` enum('online', 'offline') NOT NULL DEFAULT 'online' COMMENT '审批方式：线上/线下',
  `status` enum('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending' COMMENT '审批状态',
  `submitted_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '提交人ID',
  `submitted_at` datetime DEFAULT NULL COMMENT '提交时间',
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '审批人ID',
  `approved_at` datetime DEFAULT NULL COMMENT '审批时间',
  `rejection_reason` text DEFAULT NULL COMMENT '拒绝原因',
  `remarks` text DEFAULT NULL COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_project_id` (`project_id`),
  KEY `idx_month` (`month`),
  KEY `idx_status` (`status`),
  UNIQUE KEY `unique_project_month` (`project_id`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工资表审批记录';

-- 为工资表（salaries）添加审批实例ID字段
ALTER TABLE `salaries` 
ADD COLUMN `salary_approval_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '工资表审批ID' AFTER `insurance_import_setting`,
ADD KEY `idx_salary_approval_id` (`salary_approval_id`);

