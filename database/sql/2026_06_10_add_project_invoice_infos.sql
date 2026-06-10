ALTER TABLE `projects`
ADD COLUMN `invoice_infos` TEXT NULL COMMENT '项目开票信息数组(JSON字符串，text存储)' AFTER `invoice_bank_code`;
