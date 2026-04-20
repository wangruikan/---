-- 为工资表添加保险导入设置字段
-- 用于记录生成工资表时的保险导入设置，确保查看历史工资表时不会受项目当前设置影响

ALTER TABLE `salaries` 
ADD COLUMN `insurance_import_setting` ENUM('current', 'next', 'none') DEFAULT 'current' 
COMMENT '保险导入设置（生成时的设置）：current-当月，next-次月，none-不导入' 
AFTER `position`;

-- 更新现有记录的默认值为 'current'（已有数据默认为导入当月保险）
UPDATE `salaries` SET `insurance_import_setting` = 'current' WHERE `insurance_import_setting` IS NULL;

