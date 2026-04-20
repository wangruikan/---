-- ========================================
-- 简单查看：工资汇总表的一条记录
-- ========================================

-- 查看最新的一条记录，只看关键字段
SELECT 
    '======== 基本信息 ========' AS '';

SELECT 
    id AS 'ID',
    project_name AS '项目名称',
    month AS '月份',
    employee_count AS '员工人数',
    total_gross_salary AS '应发工资'
FROM salary_summaries 
ORDER BY id DESC 
LIMIT 1;

SELECT 
    '======== 个人社保字段（这些导致NaN） ========' AS '';

SELECT 
    id AS 'ID',
    total_pension_personal AS '养老保险个人',
    total_medical_personal AS '医疗保险个人',
    total_unemployment_personal AS '失业保险个人'
FROM salary_summaries 
ORDER BY id DESC 
LIMIT 1;

SELECT 
    '======== 大额医疗字段（这个显示正常） ========' AS '';

SELECT 
    id AS 'ID',
    total_large_medical_personal AS '大额医疗个人'
FROM salary_summaries 
ORDER BY id DESC 
LIMIT 1;

SELECT 
    '======== 其他扣除字段 ========' AS '';

SELECT 
    id AS 'ID',
    total_work_injury_company AS '工伤保险单位',
    total_maternity_company AS '生育保险单位'
FROM salary_summaries 
ORDER BY id DESC 
LIMIT 1;

-- 如果上面的查询显示字段不存在的错误，说明这些字段需要添加
-- 如果显示的值是NULL，说明需要删除记录重新生成
-- 如果显示的值是0.00，但前端还是NaN，说明是前端或API的问题

