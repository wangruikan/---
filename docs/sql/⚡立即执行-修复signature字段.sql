-- 修复 onboarding_forms 表的 signature 字段
-- 将字段类型改为TEXT以存储base64图片数据

ALTER TABLE `onboarding_forms` 
MODIFY COLUMN `signature` TEXT NULL COMMENT '本人手写签名(base64图片)';

-- 查看表结构
DESC onboarding_forms;

-- 查看现有签名数据
SELECT id, employee_id, name, 
       CASE 
         WHEN signature IS NOT NULL THEN '已签名' 
         ELSE '未签名' 
       END as signature_status,
       LENGTH(signature) as signature_length
FROM onboarding_forms 
ORDER BY created_at DESC 
LIMIT 10;
