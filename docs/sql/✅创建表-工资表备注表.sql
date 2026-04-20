-- ==========================================
-- 工资表备注事项表
-- 创建时间: 2025-11-03
-- 用途: 为每个项目按月份设置工资表备注
-- 权限: 只有审批节点2、3、4可以管理
-- ==========================================

CREATE TABLE `payroll_remarks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `account_set_id` BIGINT UNSIGNED NOT NULL COMMENT '账套ID',
  `project_name` VARCHAR(100) NOT NULL COMMENT '项目名称',
  `year` INT NOT NULL COMMENT '年份',
  `month` INT NOT NULL COMMENT '月份（1-12）',
  `remark` TEXT NULL COMMENT '工资表备注内容',
  `created_by` BIGINT UNSIGNED NULL COMMENT '创建人ID',
  `updated_by` BIGINT UNSIGNED NULL COMMENT '更新人ID',
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT '更新时间',
  PRIMARY KEY (`id`),
  INDEX `idx_payroll_remark` (`account_set_id`, `project_name`, `year`, `month`),
  UNIQUE KEY `unique_payroll_remark` (`account_set_id`, `project_name`, `year`, `month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工资表备注事项表';

-- ==========================================
-- 验证表结构
-- ==========================================

-- 查看表结构
DESC payroll_remarks;

-- 查看索引
SHOW INDEX FROM payroll_remarks;

-- ==========================================
-- 测试数据（可选）
-- ==========================================

-- 插入示例数据
INSERT INTO payroll_remarks 
  (account_set_id, project_name, year, month, remark, created_by, created_at) 
VALUES 
  (1, '公园项目', 2025, 11, '本月需注意个税计算，有新员工入职', 1, NOW()),
  (1, '老年公寓', 2025, 11, '工资发放需要在15号之前完成', 1, NOW()),
  (1, '公园项目', 2025, 10, '国庆假期加班费已包含', 1, NOW());

-- 查询验证
SELECT 
  id,
  project_name AS '项目名称',
  CONCAT(year, '-', LPAD(month, 2, '0')) AS '期间',
  remark AS '备注内容',
  created_at AS '创建时间'
FROM payroll_remarks 
WHERE account_set_id = 1
ORDER BY year DESC, month DESC, project_name;

-- ==========================================
-- 常用查询示例
-- ==========================================

-- 1. 查询某个项目某个月份的备注
SELECT remark 
FROM payroll_remarks 
WHERE account_set_id = 1 
  AND project_name = '公园项目' 
  AND year = 2025 
  AND month = 11;

-- 2. 查询某个月份所有项目的备注
SELECT 
  project_name AS '项目名称',
  remark AS '备注内容'
FROM payroll_remarks 
WHERE account_set_id = 1 
  AND year = 2025 
  AND month = 11;

-- 3. 查询最近的备注记录
SELECT 
  project_name AS '项目名称',
  CONCAT(year, '-', LPAD(month, 2, '0')) AS '期间',
  LEFT(remark, 50) AS '备注摘要',
  created_at AS '创建时间'
FROM payroll_remarks 
WHERE account_set_id = 1
ORDER BY created_at DESC 
LIMIT 10;

-- 4. 统计每个项目的备注数量
SELECT 
  project_name AS '项目名称',
  COUNT(*) AS '备注数量'
FROM payroll_remarks 
WHERE account_set_id = 1
GROUP BY project_name
ORDER BY COUNT(*) DESC;

