-- 修复onboarding_forms表的signature字段长度
-- 问题：VARCHAR(100)太短，签名路径可能超过100字符导致保存失败
-- 解决：改为VARCHAR(255)

-- 修改signature字段长度
ALTER TABLE `onboarding_forms` 
MODIFY COLUMN `signature` VARCHAR(255) NULL COMMENT '本人签名图片路径';

-- 验证修改
-- SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, COLUMN_COMMENT 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_NAME = 'onboarding_forms' AND COLUMN_NAME = 'signature';
