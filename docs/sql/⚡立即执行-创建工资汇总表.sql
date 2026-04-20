-- ========================================
-- 工资汇总表（工资表审批完成后自动生成）
-- ========================================

CREATE TABLE IF NOT EXISTS `salary_summaries` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(20) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_id` bigint(20) UNSIGNED NOT NULL COMMENT '项目ID',
  `project_name` varchar(255) NOT NULL COMMENT '项目名称',
  `month` varchar(255) NOT NULL COMMENT '工资期间（YYYY-MM）',
  `period_start` varchar(255) NULL COMMENT '工资周期开始（如：19）',
  `period_end` varchar(255) NULL COMMENT '工资周期结束（如：30）',
  `employee_count` int(11) NOT NULL DEFAULT 0 COMMENT '员工人数',
  `total_gross_salary` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '应发合计',
  `total_net_salary` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '实发合计',
  `total_company_insurance` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '单位保险合计',
  `total_personal_insurance` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '个人保险合计',
  `total_tax` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '个税合计',
  `status` varchar(255) NOT NULL DEFAULT 'approved' COMMENT '状态：approved已审批',
  `salary_approval_id` bigint(20) UNSIGNED NULL COMMENT '工资表审批ID',
  `approved_at` timestamp NULL COMMENT '审批时间',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_month` (`project_id`, `month`),
  KEY `salary_summaries_account_set_id_foreign` (`account_set_id`),
  KEY `salary_summaries_project_id_foreign` (`project_id`),
  KEY `salary_summaries_salary_approval_id_foreign` (`salary_approval_id`),
  CONSTRAINT `salary_summaries_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_summaries_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_summaries_salary_approval_id_foreign` FOREIGN KEY (`salary_approval_id`) REFERENCES `salary_approvals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工资汇总表';

-- 验证表是否创建成功
SELECT 
    '工资汇总表' as table_name,
    COUNT(*) as record_count
FROM salary_summaries;

