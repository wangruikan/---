-- ========================================
-- 完整版工资汇总表（与工资明细表格式完全一致）
-- ========================================

-- 先删除旧表（如果存在）
DROP TABLE IF EXISTS `salary_summaries`;

-- 创建新的工资汇总表
CREATE TABLE `salary_summaries` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `account_set_id` bigint(20) UNSIGNED NOT NULL COMMENT '账套ID',
  `project_id` bigint(20) UNSIGNED NOT NULL COMMENT '项目ID',
  `project_name` varchar(255) NOT NULL COMMENT '项目名称',
  `month` varchar(255) NOT NULL COMMENT '工资期间（YYYY-MM）',
  
  -- 基础信息
  `period_start` varchar(255) NULL COMMENT '工资周期开始',
  `period_end` varchar(255) NULL COMMENT '工资周期结束',
  `employee_count` int(11) NOT NULL DEFAULT 0 COMMENT '员工人数',
  
  -- 考勤相关
  `total_work_days` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '应出勤天数合计',
  `total_actual_work_days` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '实际出勤天数合计',
  `total_absent_days` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '缺勤天数合计',
  `total_absent_deduction` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '缺勤扣款合计',
  
  -- 工资相关
  `total_basic_salary` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '基本工资合计',
  `total_gross_salary` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '应发工资合计',
  
  -- 累计收入
  `total_cumulative_income` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计收入合计',
  
  -- 累计减除费用
  `total_cumulative_basic_deduction` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计减除费用合计',
  
  -- 累计专项扣除（社保公积金）
  `total_cumulative_special_deduction_insurance` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计专项扣除合计',
  
  -- 税率和速算扣除数（平均值或最高值，可为空）
  `avg_tax_rate` decimal(10,2) NULL COMMENT '平均税率',
  `avg_quick_deduction` decimal(12,2) NULL COMMENT '平均速算扣除数',
  
  -- 累计应扣缴税额
  `total_cumulative_tax_payable` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计应扣缴税额合计',
  
  -- 已扣缴税额
  `total_tax_already_withheld` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '已扣缴税额合计',
  
  -- 社保（单位部分按险种细分）
  `total_pension_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '养老保险单位合计',
  `total_medical_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '医疗保险单位合计',
  `total_unemployment_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '失业保险单位合计',
  `total_work_injury_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '工伤保险单位合计',
  `total_maternity_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '生育保险单位合计',
  
  -- 社保（个人部分按险种细分）
  `total_pension_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '养老保险个人合计',
  `total_medical_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '医疗保险个人合计',
  `total_unemployment_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '失业保险个人合计',
  
  -- 公积金
  `total_housing_fund_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '公积金单位合计',
  `total_housing_fund_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '公积金个人合计',
  
  -- 大额医疗
  `total_large_medical_company` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '大额医疗单位合计',
  `total_large_medical_personal` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '大额医疗个人合计（不计入个人合计）',
  
  -- 社保补差
  `total_social_security_compensation` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '社保补差合计',
  
  -- 公积金补差
  `total_housing_fund_compensation` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '公积金补差合计',
  
  -- 保险合计
  `total_company_insurance_total` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '单位保险合计',
  `total_personal_insurance_total` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '个人保险合计',
  
  -- 专项附加扣除
  `total_special_deduction_monthly` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '当月专项附加扣除合计',
  `total_special_deduction` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计专项附加扣除合计',
  
  -- 应纳税所得额
  `total_taxable_income` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '应纳税所得额合计',
  
  -- 累计其他应纳税项
  `total_cumulative_other_taxable` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '累计其他应纳税项合计',
  
  -- 应补退税额
  `total_tax_payable_or_refundable` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '应补退税额合计',
  
  -- 本人签字（汇总表不需要）
  -- `employee_signature` varchar(255) NULL COMMENT '本人签字',
  
  -- 扣款项目（如果有的话）
  `total_deductions` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '其他扣款合计',
  
  -- 实发工资
  `total_net_salary` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '实发工资合计',
  
  -- 发放状态
  `total_paid_salary` decimal(12,2) NOT NULL DEFAULT 0.00 COMMENT '已发放工资合计',
  
  -- 状态和时间
  `status` varchar(255) NOT NULL DEFAULT 'approved' COMMENT '状态',
  `salary_approval_id` bigint(20) UNSIGNED NULL COMMENT '工资表审批ID',
  `approved_at` timestamp NULL COMMENT '审批时间',
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_project_month` (`project_id`, `month`),
  KEY `salary_summaries_account_set_id_foreign` (`account_set_id`),
  KEY `salary_summaries_project_id_foreign` (`project_id`),
  KEY `salary_summaries_salary_approval_id_foreign` (`salary_approval_id`),
  CONSTRAINT `salary_summaries_account_set_id_foreign` FOREIGN KEY (`account_set_id`) REFERENCES `account_sets` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_summaries_project_id_foreign` FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE,
  CONSTRAINT `salary_summaries_salary_approval_id_foreign` FOREIGN KEY (`salary_approval_id`) REFERENCES `salary_approvals` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='工资汇总表（完整版）';

-- 验证表是否创建成功
DESCRIBE salary_summaries;

