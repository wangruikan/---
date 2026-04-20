-- 付款申请表
CREATE TABLE IF NOT EXISTS `payment_applications` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(20) UNSIGNED NOT NULL,
  `process_approval_id` bigint(20) UNSIGNED NOT NULL,
  `title` varchar(255) NOT NULL,
  `month` varchar(7) NOT NULL,
  `project_ids` json NOT NULL,
  `description` text,
  `initiator_id` bigint(20) UNSIGNED NOT NULL,
  `approval_instance_id` bigint(20) UNSIGNED NULL,
  `status` enum('draft','pending','approved','rejected') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_process_approval_id` (`process_approval_id`),
  KEY `idx_initiator_id` (`initiator_id`),
  KEY `idx_approval_instance_id` (`approval_instance_id`),
  KEY `idx_status` (`status`),
  KEY `idx_month` (`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 付款申请附件表
CREATE TABLE IF NOT EXISTS `payment_attachments` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_application_id` bigint(20) UNSIGNED NOT NULL,
  `filename` varchar(255) NOT NULL,
  `path` varchar(500) NOT NULL,
  `size` bigint(20) NOT NULL,
  `mime_type` varchar(100) NOT NULL,
  `uploaded_by` bigint(20) UNSIGNED NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payment_application_id` (`payment_application_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
