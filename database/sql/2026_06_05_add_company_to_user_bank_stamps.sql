ALTER TABLE `user_bank_stamps`
  ADD COLUMN `company` VARCHAR(100) NULL DEFAULT NULL AFTER `name`;

ALTER TABLE `user_bank_stamps`
  MODIFY COLUMN `type` VARCHAR(50) NOT NULL DEFAULT 'bank';
