-- 社保公积金缴费提醒功能数据库表（分账套版本）

-- 1. 缴费日期配置表（按月份配置，分账套）
CREATE TABLE IF NOT EXISTS `payment_due_date_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(20) unsigned NOT NULL COMMENT '账套ID',
  `payment_type` enum('social_security','housing_fund') NOT NULL COMMENT '缴费类型：social_security=社保, housing_fund=公积金',
  `month` tinyint(4) NOT NULL COMMENT '月份（1-12）',
  `due_day` tinyint(4) NOT NULL COMMENT '缴费日（1-31）',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_account_payment_month` (`account_set_id`,`payment_type`,`month`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_payment_type` (`payment_type`),
  KEY `idx_month` (`month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='缴费日期配置表（按月份，分账套）';

-- 2. 提醒时间配置表（分账套，社保和公积金通用）
CREATE TABLE IF NOT EXISTS `payment_reminder_configs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(20) unsigned NOT NULL COMMENT '账套ID',
  `days_before` tinyint(4) NOT NULL COMMENT '提前几天提醒（0=当天）',
  `reminder_time` time NOT NULL COMMENT '提醒时间（HH:mm:ss）',
  `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '是否启用',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_account_set_id` (`account_set_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='提醒时间配置表（社保和公积金通用，分账套）';

-- 3. 提醒记录表（用于防止重复提醒，保留此表但不在前端显示）
CREATE TABLE IF NOT EXISTS `payment_reminder_logs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(20) unsigned NOT NULL COMMENT '账套ID',
  `payment_request_id` bigint(20) unsigned NOT NULL COMMENT '付款申请ID',
  `payment_type` enum('social_security','housing_fund') NOT NULL COMMENT '缴费类型',
  `year` smallint(6) NOT NULL COMMENT '年份',
  `month` tinyint(4) NOT NULL COMMENT '月份',
  `due_date` date NOT NULL COMMENT '缴费日期',
  `reminder_date` date NOT NULL COMMENT '提醒日期',
  `reminder_time` time NOT NULL COMMENT '提醒时间',
  `notified_user_id` bigint(20) unsigned NOT NULL COMMENT '被提醒的用户ID',
  `notification_id` bigint(20) unsigned NULL COMMENT '通知记录ID',
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payment_request_id` (`payment_request_id`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_notified_user_id` (`notified_user_id`),
  KEY `idx_reminder_date` (`reminder_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='提醒记录表';

-- 注意：不插入默认数据，由用户在前端配置
