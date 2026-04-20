-- =====================================================
-- 测试用：手动生成资料交付记录
-- 用途：在配置好交付配置后，快速生成测试数据
-- =====================================================

-- 📌 使用前请确认：
-- 1. 已经在"交付配置"中添加了至少一个项目配置
-- 2. 确认配置的 project_id 和 account_set_id

-- =====================================================
-- 步骤1：查看当前的交付配置
-- =====================================================
SELECT 
    id,
    project_id,
    delivery_cycle,
    delivery_method,
    account_set_id,
    is_active
FROM project_delivery_configs
WHERE is_active = 1;

-- =====================================================
-- 步骤2：为每个配置生成交付记录（根据实际情况修改）
-- =====================================================

-- 示例：为配置ID=1的项目生成本月交付记录（月度）
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
    id as config_id,
    project_id,
    account_set_id,
    delivery_cycle,
    delivery_method,
    DATE_FORMAT(NOW(), '%Y-%m') as delivery_period,  -- 本月：2025-10
    'pending' as status,
    created_by as handler_id,
    required_documents,
    NOW() as created_at,
    NOW() as updated_at
FROM project_delivery_configs
WHERE is_active = 1 
  AND delivery_cycle = 'monthly'
  AND id NOT IN (
      SELECT config_id 
      FROM document_deliveries 
      WHERE delivery_period = DATE_FORMAT(NOW(), '%Y-%m')
  );

-- 示例：为季度交付的项目生成本季度交付记录
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
    id as config_id,
    project_id,
    account_set_id,
    delivery_cycle,
    delivery_method,
    CONCAT(YEAR(NOW()), '-Q', QUARTER(NOW())) as delivery_period,  -- 本季度：2025-Q4
    'pending' as status,
    created_by as handler_id,
    required_documents,
    NOW() as created_at,
    NOW() as updated_at
FROM project_delivery_configs
WHERE is_active = 1 
  AND delivery_cycle = 'quarterly'
  AND id NOT IN (
      SELECT config_id 
      FROM document_deliveries 
      WHERE delivery_period = CONCAT(YEAR(NOW()), '-Q', QUARTER(NOW()))
  );

-- =====================================================
-- 步骤3：查看生成的交付记录
-- =====================================================
SELECT 
    dd.id,
    p.name as project_name,
    dd.delivery_cycle,
    dd.delivery_method,
    dd.delivery_period,
    dd.status,
    u.name as handler_name,
    dd.created_at
FROM document_deliveries dd
LEFT JOIN projects p ON dd.project_id = p.id
LEFT JOIN users u ON dd.handler_id = u.id
ORDER BY dd.created_at DESC;

-- =====================================================
-- 🎯 快速测试步骤：
-- =====================================================
-- 1. 先执行"步骤1"的查询，确认有配置存在
-- 2. 执行"步骤2"的 INSERT 语句，生成交付记录
-- 3. 执行"步骤3"的查询，确认记录已生成
-- 4. 刷新前端"交付记录"页面，应该能看到数据了！

-- =====================================================
-- 🗑️ 如果需要清空测试数据重新测试：
-- =====================================================
-- TRUNCATE TABLE document_deliveries;
-- TRUNCATE TABLE document_delivery_attachments;
-- TRUNCATE TABLE document_delivery_reminders;

