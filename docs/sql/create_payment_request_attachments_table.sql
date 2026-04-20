-- 创建付款申请附件表
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

-- 添加 approval_instance_id 到 payment_requests 表（如果不存在）
ALTER TABLE `payment_requests` 
ADD COLUMN IF NOT EXISTS `approval_instance_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '审批实例ID' AFTER `salary_approval_id`;

