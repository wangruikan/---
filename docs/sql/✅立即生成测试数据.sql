-- =====================================================
-- 🚀 立即生成交付记录测试数据
-- 执行后立即在"交付记录"页面看到数据
-- =====================================================

-- 为所有激活的配置生成本月/本季度的交付记录

-- 🎯 生成月度交付记录
INSERT INTO document_deliveries (
    config_id,
    project_id,
    account_set_id,
    delivery_cycle,
    delivery_method,
    delivery_period,
    status,
    handler_id,
    required_documents,
    created_at,
    updated_at
)
SELECT 
    pdc.id as config_id,
    pdc.project_id,
    pdc.account_set_id,
    pdc.delivery_cycle,
    pdc.delivery_method,
    DATE_FORMAT(NOW(), '%Y-%m') as delivery_period,
    'pending' as status,
    pdc.created_by as handler_id,
    pdc.required_documents,
    NOW() as created_at,
    NOW() as updated_at
FROM project_delivery_configs pdc
WHERE pdc.is_active = 1 
  AND pdc.delivery_cycle = 'monthly'
  AND NOT EXISTS (
      SELECT 1 
      FROM document_deliveries dd 
      WHERE dd.config_id = pdc.id 
        AND dd.delivery_period = DATE_FORMAT(NOW(), '%Y-%m')
  );

-- 🎯 生成季度交付记录
INSERT INTO document_deliveries (
    config_id,
    project_id,
    account_set_id,
    delivery_cycle,
    delivery_method,
    delivery_period,
    status,
    handler_id,
    required_documents,
    created_at,
    updated_at
)
SELECT 
    pdc.id as config_id,
    pdc.project_id,
    pdc.account_set_id,
    pdc.delivery_cycle,
    pdc.delivery_method,
    CONCAT(YEAR(NOW()), '-Q', QUARTER(NOW())) as delivery_period,
    'pending' as status,
    pdc.created_by as handler_id,
    pdc.required_documents,
    NOW() as created_at,
    NOW() as updated_at
FROM project_delivery_configs pdc
WHERE pdc.is_active = 1 
  AND pdc.delivery_cycle = 'quarterly'
  AND NOT EXISTS (
      SELECT 1 
      FROM document_deliveries dd 
      WHERE dd.config_id = pdc.id 
        AND dd.delivery_period = CONCAT(YEAR(NOW()), '-Q', QUARTER(NOW()))
  );

-- ✅ 查看生成结果
SELECT 
    dd.id as '交付ID',
    p.name as '项目名称',
    CASE dd.delivery_cycle 
        WHEN 'monthly' THEN '月度'
        WHEN 'quarterly' THEN '季度'
    END as '交付周期',
    CASE dd.delivery_method 
        WHEN 'express' THEN '快递交付'
        WHEN 'electronic' THEN '电子推送'
    END as '交付方式',
    dd.delivery_period as '交付期间',
    CASE dd.status 
        WHEN 'pending' THEN '待交付'
        WHEN 'completed' THEN '已完成'
    END as '状态',
    u.name as '经办人',
    dd.created_at as '创建时间'
FROM document_deliveries dd
LEFT JOIN projects p ON dd.project_id = p.id
LEFT JOIN users u ON dd.handler_id = u.id
ORDER BY dd.created_at DESC
LIMIT 20;

-- =====================================================
-- ✅ 执行完成！
-- 现在刷新前端"交付记录"页面即可看到数据
-- =====================================================

