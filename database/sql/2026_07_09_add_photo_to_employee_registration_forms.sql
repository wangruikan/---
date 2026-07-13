ALTER TABLE `employee_registration_forms`
  ADD COLUMN `photo` VARCHAR(255) NULL COMMENT '一寸照片路径' AFTER `birth_date`;
