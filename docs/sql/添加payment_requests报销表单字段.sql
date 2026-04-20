-- ============================================
-- 为 payment_requests 表添加报销表单字段
-- 执行时间：请立即执行
-- ============================================

-- 添加报销表单相关字段
ALTER TABLE `payment_requests` 
ADD COLUMN `project` VARCHAR(255) DEFAULT NULL COMMENT '项目' AFTER `remarks`,
ADD COLUMN `apply_date` DATE DEFAULT NULL COMMENT '申请日期' AFTER `project`,
ADD COLUMN `unit_name` VARCHAR(255) DEFAULT NULL COMMENT '单位名称' AFTER `apply_date`,
ADD COLUMN `invoice_number` VARCHAR(255) DEFAULT NULL COMMENT '发票号码' AFTER `unit_name`,
ADD COLUMN `verified` TINYINT(1) DEFAULT 1 COMMENT '查验（1=已查验）' AFTER `invoice_number`,
ADD COLUMN `payment_date` DATE DEFAULT NULL COMMENT '打款日期' AFTER `verified`,
ADD COLUMN `expenditure_amount` DECIMAL(15,2) DEFAULT NULL COMMENT '支出金额' AFTER `payment_date`,
ADD COLUMN `project_name` VARCHAR(255) DEFAULT NULL COMMENT '项目名称' AFTER `expenditure_amount`,
ADD COLUMN `summary` TEXT DEFAULT NULL COMMENT '摘要' AFTER `project_name`,
ADD COLUMN `invoice_received` TINYINT(1) DEFAULT 0 COMMENT '收到发票（1=已收到）' AFTER `summary`,
ADD COLUMN `invoice_type` VARCHAR(50) DEFAULT NULL COMMENT '发票类型' AFTER `invoice_received`,
ADD COLUMN `invoice_amount` DECIMAL(15,2) DEFAULT NULL COMMENT '开票金额' AFTER `invoice_type`,
ADD COLUMN `tax_rate` VARCHAR(20) DEFAULT NULL COMMENT '税率' AFTER `invoice_amount`,
ADD COLUMN `deduction_amount` DECIMAL(15,2) DEFAULT NULL COMMENT '扣除额' AFTER `tax_rate`,
ADD COLUMN `amount_excluding_tax` DECIMAL(15,2) DEFAULT NULL COMMENT '不含税金额' AFTER `deduction_amount`,
ADD COLUMN `tax_amount` DECIMAL(15,2) DEFAULT NULL COMMENT '税金' AFTER `amount_excluding_tax`,
ADD COLUMN `is_consistent` TINYINT(1) DEFAULT 0 COMMENT '是否一致（1=一致）' AFTER `tax_amount`,
ADD COLUMN `status_checked` TINYINT(1) DEFAULT 1 COMMENT '状态（1=已确认）' AFTER `is_consistent`,
ADD COLUMN `selected_month` VARCHAR(7) DEFAULT NULL COMMENT '勾选月份（YYYY-MM）' AFTER `status_checked`,
ADD COLUMN `reimburser` VARCHAR(100) DEFAULT NULL COMMENT '报销人' AFTER `selected_month`,
ADD COLUMN `invoice_date` DATE DEFAULT NULL COMMENT '开票日期' AFTER `reimburser`,
ADD COLUMN `accounted` TINYINT(1) DEFAULT 1 COMMENT '入账（1=已入账）' AFTER `invoice_date`,
ADD COLUMN `company` VARCHAR(255) DEFAULT NULL COMMENT '公司' AFTER `accounted`;

-- ============================================
-- 执行完成！
-- ============================================
-- 
-- 说明：
-- 这些字段用于存储报销付款申请中的表单信息
-- 只在付款申请模块中显示，不影响其他功能

