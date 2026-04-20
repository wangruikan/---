-- 为工资表添加累计应扣缴税额字段
-- 计算公式：累计应纳税所得额 × 税率 - 速算扣除数

ALTER TABLE `salaries` 
ADD COLUMN `cumulative_tax_payable` DECIMAL(10, 2) DEFAULT 0 
COMMENT '累计应扣缴税额（累计应纳税所得额×税率-速算扣除数）' 
AFTER `quick_deduction`;

