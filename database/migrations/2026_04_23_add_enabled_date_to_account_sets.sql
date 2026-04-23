-- 为 account_sets 表新增启用日期字段（MySQL 5.6 兼容）
ALTER TABLE `account_sets`
ADD COLUMN `enabled_date` DATE NULL COMMENT '启用日期' AFTER `address`;
