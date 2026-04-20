-- ============================================
-- 创建出款汇总表
-- 执行时间：请立即执行
-- ============================================

CREATE TABLE IF NOT EXISTS `payment_summaries` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `payment_request_id` bigint(20) UNSIGNED NOT NULL COMMENT '付款申请ID',
  `payment_type` VARCHAR(50) NOT NULL COMMENT '付款类型：salary/insurance/reimbursement/报销/差旅/采购/项目/其他',
  `account_set_id` bigint(20) UNSIGNED NOT NULL COMMENT '账套ID',
  `month` VARCHAR(7) NOT NULL COMMENT '月份（YYYY-MM）',
  `project` VARCHAR(255) DEFAULT NULL COMMENT '项目',
  `apply_date` DATE DEFAULT NULL COMMENT '申请日期',
  `unit_name` VARCHAR(255) DEFAULT NULL COMMENT '单位名称',
  `invoice_number` VARCHAR(255) DEFAULT NULL COMMENT '发票号码',
  `verified` TINYINT(1) DEFAULT 1 COMMENT '查验（1=已查验）',
  `payment_date` DATE DEFAULT NULL COMMENT '打款日期',
  `expenditure_amount` DECIMAL(15,2) DEFAULT NULL COMMENT '支出金额',
  `project_name` VARCHAR(255) DEFAULT NULL COMMENT '项目名称',
  `summary` TEXT DEFAULT NULL COMMENT '摘要',
  `invoice_received` TINYINT(1) DEFAULT 0 COMMENT '收到发票（1=已收到）',
  `invoice_type` VARCHAR(50) DEFAULT NULL COMMENT '发票类型',
  `invoice_amount` DECIMAL(15,2) DEFAULT NULL COMMENT '开票金额',
  `tax_rate` VARCHAR(20) DEFAULT NULL COMMENT '税率',
  `deduction_amount` DECIMAL(15,2) DEFAULT NULL COMMENT '扣除额',
  `amount_excluding_tax` DECIMAL(15,2) DEFAULT NULL COMMENT '不含税金额',
  `tax_amount` DECIMAL(15,2) DEFAULT NULL COMMENT '税金',
  `is_consistent` TINYINT(1) DEFAULT 0 COMMENT '是否一致（1=一致）',
  `status_checked` TINYINT(1) DEFAULT 1 COMMENT '状态（1=已确认）',
  `selected_month` VARCHAR(7) DEFAULT NULL COMMENT '勾选月份（YYYY-MM）',
  `reimburser` VARCHAR(100) DEFAULT NULL COMMENT '报销人',
  `invoice_date` DATE DEFAULT NULL COMMENT '开票日期',
  `accounted` TINYINT(1) DEFAULT 1 COMMENT '入账（1=已入账）',
  `company` VARCHAR(255) DEFAULT NULL COMMENT '公司',
  `amount` DECIMAL(15,2) NOT NULL COMMENT '付款金额',
  `approved_at` DATETIME DEFAULT NULL COMMENT '审批通过时间',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payment_request_id` (`payment_request_id`),
  KEY `idx_payment_type` (`payment_type`),
  KEY `idx_account_set_id` (`account_set_id`),
  KEY `idx_month` (`month`),
  KEY `idx_approved_at` (`approved_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='出款汇总表';

-- ============================================
-- 执行完成！
-- ============================================

