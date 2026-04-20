-- 1. 清理旧的补差记录
DELETE FROM insurance_compensation_records WHERE employee_name = 'wrk';

-- 2. 验证清理成功
SELECT COUNT(*) AS '剩余记录数' FROM insurance_compensation_records WHERE employee_name = 'wrk';

-- 说明：清理后，请在前端重新修改社保地区的上下限，系统会自动生成正确格式的补差记录

