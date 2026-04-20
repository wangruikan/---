-- 修复 employee_documents 表的 file_type 字段长度
-- 原因: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet 长度超过限制

ALTER TABLE `employee_documents` 
MODIFY COLUMN `file_type` VARCHAR(150) NULL COMMENT '文件MIME类型';

-- 查看表结构
DESC employee_documents;

-- 查看现有数据
SELECT id, employee_id, document_name, file_type, LENGTH(file_type) as type_length 
FROM employee_documents 
ORDER BY LENGTH(file_type) DESC 
LIMIT 10;
