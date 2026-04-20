-- 添加项目工资和保险设置字段

-- 添加工资发放设置字段（本月/次月）
ALTER TABLE `projects` ADD COLUMN `salary_payment_month` ENUM('current', 'next') DEFAULT 'current' 
COMMENT '工资发放月份：current-本月，next-次月' AFTER `name`;

-- 添加保险导入设置字段（当月/次月/不导入）
ALTER TABLE `projects` ADD COLUMN `insurance_import_month` ENUM('current', 'next', 'none') DEFAULT 'current' 
COMMENT '保险导入设置：current-当月，next-次月，none-不导入' AFTER `salary_payment_month`;

-- 查看结果
SELECT id, name, salary_payment_month, insurance_import_month FROM projects;

