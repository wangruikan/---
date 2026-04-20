-- 添加重新发起标记字段到发票申请表
-- 执行时间: 2025-11-03

-- 1. 添加 has_resubmitted 字段（是否已重新发起）
ALTER TABLE `invoice_applications` 
ADD COLUMN `has_resubmitted` TINYINT(1) NOT NULL DEFAULT 0 
COMMENT '是否已重新发起（红冲后）' 
AFTER `status`;

-- 2. 添加 new_application_id 字段（新申请ID）
ALTER TABLE `invoice_applications` 
ADD COLUMN `new_application_id` BIGINT NULL 
COMMENT '重新发起后的新申请ID' 
AFTER `has_resubmitted`;

-- 3. 验证字段是否添加成功
SHOW COLUMNS FROM `invoice_applications` WHERE Field IN ('has_resubmitted', 'new_application_id');

