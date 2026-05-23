-- 回填历史参保增减记录到 insurance_change_items（幂等）

USE `weiqing`;
SET NAMES utf8mb4;

INSERT INTO `insurance_change_items` (
  `insurance_change_id`, `category`, `change_type`, `status`, `category_snapshot`, `change_details`, `processed_by`, `processed_at`, `created_at`, `updated_at`
)
SELECT
  `id`,
  'social_security',
  `change_type`,
  CASE WHEN `status` = 'completed' THEN 'completed' WHEN `status` = 'submitted' THEN 'submitted' ELSE 'pending' END,
  `social_security_types`,
  `change_details`,
  `processed_by`,
  `processed_at`,
  `created_at`,
  `updated_at`
FROM `insurance_changes`
WHERE `social_security_types` IS NOT NULL AND `social_security_types` <> '' AND `social_security_types` <> '[]'
ON DUPLICATE KEY UPDATE
  `category_snapshot` = VALUES(`category_snapshot`),
  `change_details` = VALUES(`change_details`),
  `updated_at` = VALUES(`updated_at`);

INSERT INTO `insurance_change_items` (
  `insurance_change_id`, `category`, `change_type`, `status`, `category_snapshot`, `change_details`, `processed_by`, `processed_at`, `created_at`, `updated_at`
)
SELECT
  `id`,
  'medical_insurance',
  `change_type`,
  CASE WHEN `status` = 'completed' THEN 'completed' WHEN `status` = 'submitted' THEN 'submitted' ELSE 'pending' END,
  `medical_insurance_types`,
  `change_details`,
  `processed_by`,
  `processed_at`,
  `created_at`,
  `updated_at`
FROM `insurance_changes`
WHERE `medical_insurance_types` IS NOT NULL AND `medical_insurance_types` <> '' AND `medical_insurance_types` <> '[]'
ON DUPLICATE KEY UPDATE
  `category_snapshot` = VALUES(`category_snapshot`),
  `change_details` = VALUES(`change_details`),
  `updated_at` = VALUES(`updated_at`);

INSERT INTO `insurance_change_items` (
  `insurance_change_id`, `category`, `change_type`, `status`, `category_snapshot`, `change_details`, `processed_by`, `processed_at`, `created_at`, `updated_at`
)
SELECT
  `id`,
  'housing_fund',
  `change_type`,
  CASE WHEN `status` = 'completed' THEN 'completed' WHEN `status` = 'submitted' THEN 'submitted' ELSE 'pending' END,
  `housing_fund_params`,
  `change_details`,
  `processed_by`,
  `processed_at`,
  `created_at`,
  `updated_at`
FROM `insurance_changes`
WHERE `housing_fund_params` IS NOT NULL AND `housing_fund_params` <> '' AND `housing_fund_params` <> '[]'
ON DUPLICATE KEY UPDATE
  `category_snapshot` = VALUES(`category_snapshot`),
  `change_details` = VALUES(`change_details`),
  `updated_at` = VALUES(`updated_at`);

INSERT INTO `insurance_change_items` (
  `insurance_change_id`, `category`, `change_type`, `status`, `category_snapshot`, `change_details`, `processed_by`, `processed_at`, `created_at`, `updated_at`
)
SELECT
  `id`,
  'large_medical_insurance',
  `change_type`,
  CASE WHEN `status` = 'completed' THEN 'completed' WHEN `status` = 'submitted' THEN 'submitted' ELSE 'pending' END,
  `large_medical_insurance_config`,
  `change_details`,
  `processed_by`,
  `processed_at`,
  `created_at`,
  `updated_at`
FROM `insurance_changes`
WHERE (`large_medical_insurance_config` IS NOT NULL AND `large_medical_insurance_config` <> '' AND `large_medical_insurance_config` <> '[]')
   OR `large_medical_insurance_enabled` = 1
ON DUPLICATE KEY UPDATE
  `category_snapshot` = VALUES(`category_snapshot`),
  `change_details` = VALUES(`change_details`),
  `updated_at` = VALUES(`updated_at`);

INSERT INTO `insurance_change_items` (
  `insurance_change_id`, `category`, `change_type`, `status`, `category_snapshot`, `change_details`, `processed_by`, `processed_at`, `created_at`, `updated_at`
)
SELECT
  `id`,
  'other_insurance',
  `change_type`,
  CASE WHEN `status` = 'completed' OR `other_insurance_processed` = 1 THEN 'completed' WHEN `status` = 'submitted' THEN 'submitted' ELSE 'pending' END,
  `other_insurance_policies`,
  `change_details`,
  `processed_by`,
  `processed_at`,
  `created_at`,
  `updated_at`
FROM `insurance_changes`
WHERE `other_insurance_policies` IS NOT NULL AND `other_insurance_policies` <> '' AND `other_insurance_policies` <> '[]'
ON DUPLICATE KEY UPDATE
  `category_snapshot` = VALUES(`category_snapshot`),
  `change_details` = VALUES(`change_details`),
  `updated_at` = VALUES(`updated_at`);

SELECT `category`, COUNT(*) AS total
FROM `insurance_change_items`
GROUP BY `category`
ORDER BY `category`;
