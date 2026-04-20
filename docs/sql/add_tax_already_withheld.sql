-- 为工资表添加已扣缴税额字段
-- 计算公式：查询1月到上月的累计应扣缴税额相加

ALTER TABLE `salaries` 
ADD COLUMN `tax_already_withheld` DECIMAL(10, 2) DEFAULT 0 
COMMENT '已扣缴税额（1月到上月的累计应扣缴税额之和）' 
AFTER `cumulative_tax_payable`;

