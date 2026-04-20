-- ============================================
-- 工资付款申请功能 - 完整数据库脚本
-- 执行时间：请立即执行
-- ============================================

-- 1. 创建付款申请表（如果不存在）
CREATE TABLE IF NOT EXISTS `payment_requests` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_type` enum('salary','insurance') NOT NULL DEFAULT 'insurance' COMMENT '付款类型：工资/保险',
  `account_set_id` bigint(20) UNSIGNED NOT NULL COMMENT '账套ID',
  `insurance_summary_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '保险汇总ID（当payment_type=insurance时使用）',
  `salary_approval_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '工资表审批ID（当payment_type=salary时使用）',
  `approval_instance_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '审批实例ID',
  `amount` decimal(12,2) NOT NULL DEFAULT '0.00' COMMENT '付款金额',
  `status` enum('pending','approved','rejected','paid') NOT NULL DEFAULT 'pending' COMMENT '状态',
  `submitted_by` bigint(20) UNSIGNED NOT NULL COMMENT '提交人ID',
  `submitted_at` timestamp NULL DEFAULT NULL COMMENT '提交时间',
  `approved_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '审批人ID',
  `approved_at` timestamp NULL DEFAULT NULL COMMENT '审批时间',
  `paid_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '付款人ID',
  `paid_at` timestamp NULL DEFAULT NULL COMMENT '付款时间',
  `rejection_reason` text COMMENT '驳回原因',
  `remarks` text COMMENT '备注',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payment_type` (`payment_type`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_insurance_summary_id` (`insurance_summary_id`),
  KEY `idx_salary_approval_id` (`salary_approval_id`),
  KEY `idx_status` (`status`),
  KEY `idx_submitted_by` (`submitted_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='付款申请表';

-- 2. 创建付款申请附件表
CREATE TABLE IF NOT EXISTS `payment_request_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_request_id` bigint(20) UNSIGNED NOT NULL COMMENT '付款申请ID',
  `filename` varchar(255) NOT NULL COMMENT '原始文件名',
  `file_path` varchar(500) NOT NULL COMMENT '文件存储路径',
  `file_size` bigint(20) NOT NULL COMMENT '文件大小（字节）',
  `mime_type` varchar(100) DEFAULT NULL COMMENT '文件MIME类型',
  `uploaded_by` bigint(20) UNSIGNED NOT NULL COMMENT '上传人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payment_request_id` (`payment_request_id`),
  KEY `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='付款申请附件表';

-- ============================================
-- 执行完成提示
-- ============================================
SELECT '✅ 工资付款申请数据库表创建完成！' AS message;
SELECT '📁 请确保 public/payment_requests 目录存在并有写入权限' AS reminder;

