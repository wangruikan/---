-- 创建付款申请表（完整版）
CREATE TABLE `payment_requests` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_type` enum('salary', 'insurance') NOT NULL DEFAULT 'insurance' COMMENT '付款类型：工资/保险',
  `account_set_id` bigint(20) UNSIGNED NOT NULL COMMENT '账套ID',
  `insurance_summary_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '保险汇总ID（当payment_type=insurance时使用）',
  `salary_approval_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '工资表审批ID（当payment_type=salary时使用）',
  `amount` decimal(15, 2) NOT NULL DEFAULT 0.00 COMMENT '付款金额',
  `status` enum('pending', 'approved', 'rejected', 'paid') NOT NULL DEFAULT 'pending' COMMENT '状态：待审批/已通过/已拒绝/已付款',
  `submitted_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '提交人ID',
  `submitted_at` datetime DEFAULT NULL COMMENT '提交时间',
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '审批人ID',
  `approved_at` datetime DEFAULT NULL COMMENT '审批时间',
  `paid_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '付款人ID',
  `paid_at` datetime DEFAULT NULL COMMENT '付款时间',
  `rejection_reason` text DEFAULT NULL COMMENT '拒绝原因',
  `remarks` text DEFAULT NULL COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_payment_type` (`payment_type`),
  KEY `idx_insurance_summary_id` (`insurance_summary_id`),
  KEY `idx_salary_approval_id` (`salary_approval_id`),
  KEY `idx_status` (`status`),
  KEY `idx_submitted_at` (`submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='付款申请表';

