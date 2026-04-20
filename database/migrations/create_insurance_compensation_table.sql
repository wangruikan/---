-- 创建保险补差记录表
-- 该表用于存储社保、医保、公积金、大病医疗的基数补差记录
-- 与 insurance_personnel 表分离，避免补差数据混入当前月份参保数据

CREATE TABLE IF NOT EXISTS `insurance_compensation_records` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(20) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_id` bigint(20) UNSIGNED NOT NULL COMMENT '项目ID',
  `employee_id` bigint(20) UNSIGNED NOT NULL COMMENT '员工ID',
  `employee_name` varchar(100) NOT NULL COMMENT '员工姓名',
  `employee_id_number` varchar(18) NOT NULL COMMENT '员工身份证号',
  
  -- 补差类型：social_security(社保), medical_insurance(医保), housing_fund(公积金), large_medical(大病医疗)
  `compensation_type` varchar(50) NOT NULL COMMENT '补差类型',
  
  -- 基数信息
  `old_base` decimal(10,2) NOT NULL COMMENT '旧基数',
  `new_base` decimal(10,2) NOT NULL COMMENT '新基数',
  
  -- 补差时段
  `compensation_start_month` varchar(7) NOT NULL COMMENT '补差开始月份 YYYY-MM',
  `compensation_end_month` varchar(7) NOT NULL COMMENT '补差结束月份 YYYY-MM',
  `compensation_months` int(11) NOT NULL COMMENT '补差月数',
  
  -- 补差明细 JSON 格式
  -- 例如：[{"name":"养老保险","company_base":5000,"personal_base":5000,"company_rate":16,"personal_rate":8,"company_amount":800,"personal_amount":400}]
  `compensation_details` json DEFAULT NULL COMMENT '补差明细JSON',
  
  -- 合计金额
  `company_total` decimal(10,2) DEFAULT 0.00 COMMENT '单位补差合计',
  `personal_total` decimal(10,2) DEFAULT 0.00 COMMENT '个人补差合计',
  `total_amount` decimal(10,2) DEFAULT 0.00 COMMENT '补差总计',
  
  -- 关联的基数调整记录
  `base_adjustment_id` bigint(20) UNSIGNED DEFAULT NULL COMMENT '关联的基数调整记录ID',
  
  -- 状态：pending(待处理), processed(已处理), cancelled(已取消)
  `status` varchar(20) DEFAULT 'pending' COMMENT '状态',
  
  -- 备注
  `remark` text DEFAULT NULL COMMENT '备注',
  
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  KEY `idx_account_set` (`account_set_id`),
  KEY `idx_project` (`project_id`),
  KEY `idx_employee` (`employee_id`),
  KEY `idx_compensation_month` (`compensation_start_month`, `compensation_end_month`),
  KEY `idx_base_adjustment` (`base_adjustment_id`),
  KEY `idx_created_at` (`created_at`),
  UNIQUE KEY `uk_compensation` (`employee_id`, `project_id`, `compensation_type`, `compensation_start_month`, `compensation_end_month`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='保险补差记录表';

