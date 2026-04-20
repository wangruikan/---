-- 创建工资表审批附件表
CREATE TABLE `salary_approval_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `salary_approval_id` bigint(20) UNSIGNED NOT NULL COMMENT '工资表审批ID',
  `filename` varchar(255) NOT NULL COMMENT '原始文件名',
  `file_path` varchar(500) NOT NULL COMMENT '文件路径',
  `file_size` bigint(20) UNSIGNED NOT NULL COMMENT '文件大小（字节）',
  `mime_type` varchar(100) DEFAULT NULL COMMENT '文件类型',
  `uploaded_by` bigint(20) UNSIGNED NOT NULL COMMENT '上传人ID',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_salary_approval_id` (`salary_approval_id`),
  KEY `idx_uploaded_by` (`uploaded_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工资表审批附件';

