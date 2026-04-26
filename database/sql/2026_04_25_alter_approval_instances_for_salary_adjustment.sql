-- 员工工资调整审批：approval_instances 新增字段（MySQL 5.6 兼容）
-- 请在业务低峰期执行

ALTER TABLE `approval_instances`
  ADD COLUMN `old_basic_salary` decimal(10,2) DEFAULT NULL COMMENT '调整前基础工资',
  ADD COLUMN `old_salary_items` text COMMENT '调整前工资项(JSON)',
  ADD COLUMN `new_basic_salary` decimal(10,2) DEFAULT NULL COMMENT '调整后基础工资',
  ADD COLUMN `new_salary_items` text COMMENT '调整后工资项(JSON)',
  ADD COLUMN `salary_adjustment_reason` varchar(500) DEFAULT NULL COMMENT '工资调整原因';

-- 两个登记表新增“学历性质”（统招/非统招）字段（MySQL 5.6 兼容）
ALTER TABLE `onboarding_forms`
  ADD COLUMN `education_type` varchar(20) DEFAULT NULL COMMENT '学历性质(统招/非统招)' AFTER `education_level`;

ALTER TABLE `employee_registration_forms`
  ADD COLUMN `education_type` varchar(20) DEFAULT NULL COMMENT '学历性质(统招/非统招)' AFTER `education_level`;
