-- ==========================================
-- 验证开票详情字段是否存在
-- ==========================================

-- 查看所有新增的字段
SHOW COLUMNS FROM `invoice_applications` WHERE Field IN (
  'period_year', 'period_month', 'company_name', 'application_date',
  'invoice_method', 'invoice_type', 'deduction_amount', 'tax_rate',
  'amount_excluding_tax', 'invoice_tax_amount', 'tax_amount',
  'invoice_date', 'is_completed', 'invoicer', 'invoice_number', 'invoice_remark'
);

-- ==========================================
-- 查看完整的表结构
-- ==========================================

DESCRIBE `invoice_applications`;

-- ==========================================
-- 查看字段的详细信息
-- ==========================================

SELECT 
  COLUMN_NAME AS '字段名',
  COLUMN_TYPE AS '数据类型',
  IS_NULLABLE AS '可否为空',
  COLUMN_DEFAULT AS '默认值',
  COLUMN_COMMENT AS '注释'
FROM 
  INFORMATION_SCHEMA.COLUMNS
WHERE 
  TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'invoice_applications'
  AND COLUMN_NAME IN (
    'period_year', 'period_month', 'company_name', 'application_date',
    'invoice_method', 'invoice_type', 'deduction_amount', 'tax_rate',
    'amount_excluding_tax', 'invoice_tax_amount', 'tax_amount',
    'invoice_date', 'is_completed', 'invoicer', 'invoice_number', 'invoice_remark'
  )
ORDER BY 
  ORDINAL_POSITION;

