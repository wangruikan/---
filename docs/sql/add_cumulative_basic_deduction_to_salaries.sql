-- 为工资表添加累计减除费用字段
-- 累计减除费用 = 从入职月份（或当年1月）到本月的月数 × 5000

ALTER TABLE `salaries` 
ADD COLUMN `cumulative_basic_deduction` DECIMAL(10, 2) DEFAULT 0 
COMMENT '累计减除费用（5000×月数）' 
AFTER `cumulative_income`;

