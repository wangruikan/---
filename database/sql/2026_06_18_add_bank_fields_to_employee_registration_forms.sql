ALTER TABLE `employee_registration_forms`
  ADD COLUMN `bank_account_holder` VARCHAR(100) NULL COMMENT '户名' AFTER `bank_account`,
  MODIFY COLUMN `bank_name` VARCHAR(100) NULL COMMENT '开户行',
  ADD COLUMN `bank_branch` VARCHAR(200) NULL COMMENT '开户地/支行' AFTER `bank_name`;
