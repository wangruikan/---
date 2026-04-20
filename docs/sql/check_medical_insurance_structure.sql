-- ========================================
-- 检查医保字段结构
-- ========================================

-- 查看 medical_insurance_types JSON 字段的内容
SELECT 
    employee_name,
    employee_medical_insurance_base AS '医保基数',
    medical_insurance_types AS '医保配置JSON'
FROM insurance_personnel
WHERE employee_name = 'wrk'
LIMIT 1;

