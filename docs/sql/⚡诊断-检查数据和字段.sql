-- ========================================
-- 诊断工具：检查工资汇总表的实际情况
-- ========================================

-- 第一步：查看表中有哪些字段
SELECT '========== 第一步：查看当前表结构 ==========' AS info;
SELECT 
    COLUMN_NAME as '字段名',
    DATA_TYPE as '数据类型',
    IS_NULLABLE as '可空',
    COLUMN_DEFAULT as '默认值',
    COLUMN_COMMENT as '注释'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
ORDER BY ORDINAL_POSITION;

-- 第二步：检查关键字段是否存在
SELECT '========== 第二步：检查关键字段是否存在 ==========' AS info;
SELECT 
    CASE WHEN COUNT(*) > 0 THEN '✅ 存在' ELSE '❌ 不存在' END AS '检查结果',
    'total_pension_personal' AS '字段名'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME = 'total_pension_personal'

UNION ALL

SELECT 
    CASE WHEN COUNT(*) > 0 THEN '✅ 存在' ELSE '❌ 不存在' END,
    'total_medical_personal'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME = 'total_medical_personal'

UNION ALL

SELECT 
    CASE WHEN COUNT(*) > 0 THEN '✅ 存在' ELSE '❌ 不存在' END,
    'total_unemployment_personal'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME = 'total_unemployment_personal'

UNION ALL

SELECT 
    CASE WHEN COUNT(*) > 0 THEN '✅ 存在' ELSE '❌ 不存在' END,
    'total_large_medical_personal'
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_NAME = 'salary_summaries' 
AND TABLE_SCHEMA = DATABASE()
AND COLUMN_NAME = 'total_large_medical_personal';

-- 第三步：查看实际数据
SELECT '========== 第三步：查看汇总表的实际数据 ==========' AS info;
SELECT 
    id as 'ID',
    project_name as '项目',
    month as '月份',
    employee_count as '人数',
    total_gross_salary as '应发',
    -- 检查这些字段的实际值
    total_pension_personal as '养老个人',
    total_medical_personal as '医疗个人',
    total_unemployment_personal as '失业个人',
    total_large_medical_personal as '大额个人',
    total_work_injury_company as '工伤单位',
    total_maternity_company as '生育单位'
FROM salary_summaries
ORDER BY id DESC
LIMIT 5;

-- 第四步：检查是否有NULL值
SELECT '========== 第四步：检查NULL值 ==========' AS info;
SELECT 
    COUNT(*) as '总记录数',
    SUM(CASE WHEN total_pension_personal IS NULL THEN 1 ELSE 0 END) as 'pension_personal为NULL',
    SUM(CASE WHEN total_medical_personal IS NULL THEN 1 ELSE 0 END) as 'medical_personal为NULL',
    SUM(CASE WHEN total_unemployment_personal IS NULL THEN 1 ELSE 0 END) as 'unemployment_personal为NULL',
    SUM(CASE WHEN total_large_medical_personal IS NULL THEN 1 ELSE 0 END) as 'large_medical_personal为NULL'
FROM salary_summaries;

-- 第五步：显示完整的一条记录（包含所有字段）
SELECT '========== 第五步：显示最新一条完整记录 ==========' AS info;
SELECT * FROM salary_summaries ORDER BY id DESC LIMIT 1;

-- 诊断完成
SELECT '========================================' AS '';
SELECT '✅ 诊断完成！' AS '';
SELECT '请查看上面的结果：' AS '';
SELECT '1. 如果关键字段显示【❌ 不存在】，需要执行添加字段的SQL' AS '';
SELECT '2. 如果字段存在但数据为NULL，需要删除旧记录并重新生成' AS '';
SELECT '3. 如果字段根本不在表结构中，说明建表SQL没执行完整' AS '';
SELECT '========================================' AS '';

