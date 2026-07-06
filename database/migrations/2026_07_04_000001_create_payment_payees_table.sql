CREATE TABLE IF NOT EXISTS `payment_payees` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(20) UNSIGNED NOT NULL COMMENT '账套ID',
  `payee_name` varchar(100) NOT NULL COMMENT '支付对象',
  `bank_name` varchar(255) NOT NULL COMMENT '开户行',
  `bank_account` varchar(100) NOT NULL COMMENT '账号',
  `created_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '创建人',
  `updated_by` bigint(20) UNSIGNED DEFAULT NULL COMMENT '更新人',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payment_payees_account_set_payee` (`account_set_id`, `payee_name`),
  KEY `idx_payment_payees_account_set_id` (`account_set_id`),
  KEY `idx_payment_payees_bank_account` (`bank_account`),
  KEY `idx_payment_payees_created_by` (`created_by`),
  KEY `idx_payment_payees_updated_by` (`updated_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='收款信息配置表';
