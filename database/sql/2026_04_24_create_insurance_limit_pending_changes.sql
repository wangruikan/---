-- 待生效上下限变更表（MySQL 5.6 兼容）
CREATE TABLE `insurance_limit_pending_changes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `target_type` varchar(50) NOT NULL COMMENT '目标类型：social_security_region/medical_insurance_region/housing_fund_config',
  `target_id` bigint(20) unsigned NOT NULL COMMENT '目标ID',
  `account_set_id` bigint(20) unsigned NOT NULL COMMENT '账套ID',
  `pending_min_base_amount` decimal(10,2) DEFAULT NULL COMMENT '待生效最低基数',
  `pending_max_base_amount` decimal(10,2) DEFAULT NULL COMMENT '待生效最高基数',
  `effective_date` date NOT NULL COMMENT '生效日期',
  `status` varchar(20) NOT NULL DEFAULT 'pending' COMMENT '状态：pending/applied/cancelled/failed',
  `created_by` bigint(20) unsigned DEFAULT NULL COMMENT '创建人',
  `applied_at` datetime DEFAULT NULL COMMENT '应用时间',
  `error_message` varchar(500) DEFAULT NULL COMMENT '失败原因',
  `created_at` datetime NOT NULL,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_status_effective_date` (`status`,`effective_date`),
  KEY `idx_target_status` (`target_type`,`target_id`,`status`),
  KEY `idx_account_set_id` (`account_set_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='保险上下限待生效变更表';
