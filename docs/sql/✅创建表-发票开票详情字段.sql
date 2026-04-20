-- ==========================================
-- 发票开票详情字段
-- 创建时间: 2025-11-03
-- 用途: 为发票申请表添加开票详细信息字段
-- ==========================================

ALTER TABLE `invoice_applications`
ADD COLUMN `period_year` INT NULL COMMENT '所属期-年份' AFTER `project_name`,
ADD COLUMN `period_month` INT NULL COMMENT '所属期-月份' AFTER `period_year`,
ADD COLUMN `company_name` VARCHAR(200) NULL COMMENT '单位名称' AFTER `period_month`,
ADD COLUMN `application_date` DATE NULL COMMENT '申请日期' AFTER `company_name`,
ADD COLUMN `invoice_method` ENUM('full', 'partial', 'none') NULL COMMENT '开票方式：full-全额，partial-缺额，none-无' AFTER `application_date`,
ADD COLUMN `invoice_type` VARCHAR(50) NOT NULL DEFAULT '普票' COMMENT '开票种类：普票、专票等' AFTER `invoice_method`,
ADD COLUMN `deduction_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '扣除额' AFTER `invoice_type`,
ADD COLUMN `tax_rate` DECIMAL(5,4) NOT NULL DEFAULT 0.0000 COMMENT '税率（如0.06表示6%）' AFTER `deduction_amount`,
ADD COLUMN `amount_excluding_tax` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '不含税金额' AFTER `tax_rate`,
ADD COLUMN `invoice_tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '开票税额' AFTER `amount_excluding_tax`,
ADD COLUMN `tax_amount` DECIMAL(15,2) NOT NULL DEFAULT 0.00 COMMENT '税金' AFTER `invoice_tax_amount`,
ADD COLUMN `invoice_date` DATE NULL COMMENT '开票日期' AFTER `tax_amount`,
ADD COLUMN `is_completed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否完成' AFTER `invoice_date`,
ADD COLUMN `invoicer` VARCHAR(100) NULL COMMENT '开票人' AFTER `is_completed`,
ADD COLUMN `invoice_number` VARCHAR(100) NULL COMMENT '发票号码' AFTER `invoicer`,
ADD COLUMN `invoice_remark` TEXT NULL COMMENT '开票备注' AFTER `invoice_number`;

-- ==========================================
-- 验证字段是否添加成功
-- ==========================================

-- 查看表结构
SHOW COLUMNS FROM `invoice_applications` WHERE Field IN (
  'period_year', 'period_month', 'company_name', 'application_date',
  'invoice_method', 'invoice_type', 'deduction_amount', 'tax_rate',
  'amount_excluding_tax', 'invoice_tax_amount', 'tax_amount',
  'invoice_date', 'is_completed', 'invoicer', 'invoice_number', 'invoice_remark'
);

-- ==========================================
-- 测试数据（可选）
-- ==========================================

-- 更新示例数据
UPDATE `invoice_applications`
SET 
  period_year = year,
  period_month = month,
  company_name = '测试单位',
  application_date = CURDATE(),
  invoice_method = 'full',
  invoice_type = '普票',
  tax_rate = 0.06,
  amount_excluding_tax = 1000.00,
  invoice_tax_amount = 60.00,
  tax_amount = 60.00
WHERE id = 1;

-- 查询验证
SELECT 
  id,
  application_no AS '申请单号',
  CONCAT(period_year, '-', LPAD(period_month, 2, '0')) AS '所属期',
  company_name AS '单位名称',
  invoice_method AS '开票方式',
  invoice_type AS '开票种类',
  amount_excluding_tax AS '不含税金额',
  tax_rate AS '税率',
  invoice_tax_amount AS '开票税额',
  is_completed AS '是否完成'
FROM `invoice_applications`
LIMIT 10;

