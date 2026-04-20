-- 为 payment_requests 表添加"稍后上传"标识字段

ALTER TABLE `payment_requests` 
ADD COLUMN `upload_later` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否稍后上传附件：0=否，1=是' AFTER `invoice_uploaded_by`;

-- 添加索引以便查询需要补传附件的记录
ALTER TABLE `payment_requests` 
ADD INDEX `idx_upload_later` (`upload_later`);
