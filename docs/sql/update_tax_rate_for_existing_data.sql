-- 为已存在的工资记录更新税率和速算扣除数

-- 先查看当前数据
SELECT id, employee_id, month, cumulative_income, tax_rate, quick_deduction 
FROM salaries 
WHERE tax_rate = 0 OR tax_rate IS NULL
ORDER BY employee_id, month;

-- 更新税率和速算扣除数（基于累计应纳税所得额）
-- 注意：这个脚本假设 taxable_income 字段已经正确存储了累计应纳税所得额

-- 级数1: 不超过36000元的部分，税率3%，速算扣除数0
UPDATE salaries 
SET tax_rate = 3, quick_deduction = 0
WHERE (tax_rate = 0 OR tax_rate IS NULL) 
AND taxable_income <= 36000;

-- 级数2: 超过36000元至144000元的部分，税率10%，速算扣除数2520
UPDATE salaries 
SET tax_rate = 10, quick_deduction = 2520
WHERE (tax_rate = 0 OR tax_rate IS NULL) 
AND taxable_income > 36000 AND taxable_income <= 144000;

-- 级数3: 超过144000元至300000元的部分，税率20%，速算扣除数16920
UPDATE salaries 
SET tax_rate = 20, quick_deduction = 16920
WHERE (tax_rate = 0 OR tax_rate IS NULL) 
AND taxable_income > 144000 AND taxable_income <= 300000;

-- 级数4: 超过300000元至420000元的部分，税率25%，速算扣除数31920
UPDATE salaries 
SET tax_rate = 25, quick_deduction = 31920
WHERE (tax_rate = 0 OR tax_rate IS NULL) 
AND taxable_income > 300000 AND taxable_income <= 420000;

-- 级数5: 超过420000元至660000元的部分，税率30%，速算扣除数52920
UPDATE salaries 
SET tax_rate = 30, quick_deduction = 52920
WHERE (tax_rate = 0 OR tax_rate IS NULL) 
AND taxable_income > 420000 AND taxable_income <= 660000;

-- 级数6: 超过660000元至960000元的部分，税率35%，速算扣除数85920
UPDATE salaries 
SET tax_rate = 35, quick_deduction = 85920
WHERE (tax_rate = 0 OR tax_rate IS NULL) 
AND taxable_income > 660000 AND taxable_income <= 960000;

-- 级数7: 超过960000元的部分，税率45%，速算扣除数181920
UPDATE salaries 
SET tax_rate = 45, quick_deduction = 181920
WHERE (tax_rate = 0 OR tax_rate IS NULL) 
AND taxable_income > 960000;

-- 验证更新结果
SELECT id, employee_id, month, cumulative_income, taxable_income, tax_rate, quick_deduction 
FROM salaries 
ORDER BY employee_id, month;

