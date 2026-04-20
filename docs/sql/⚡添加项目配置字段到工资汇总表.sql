-- ========================================
-- 添加项目配置信息字段到工资汇总表
-- ========================================

ALTER TABLE `salary_summaries`
ADD COLUMN `insurance_import_setting` varchar(20) NULL COMMENT '保险导入设置（current/next/none）' AFTER `employee_count`,
ADD COLUMN `social_security_location` varchar(255) NULL COMMENT '参保地' AFTER `insurance_import_setting`,
ADD COLUMN `salary_payment_day` int(11) NULL COMMENT '工资发放日（几号）' AFTER `social_security_location`,
ADD COLUMN `requires_salary_basis` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要上传工资依据' AFTER `salary_payment_day`,
ADD COLUMN `salary_basis_uploaded` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已上传工资依据' AFTER `requires_salary_basis`;

-- 验证字段是否添加成功
DESCRIBE salary_summaries;

