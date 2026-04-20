-- 创建入职登记表
CREATE TABLE IF NOT EXISTS `onboarding_forms` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键ID',
  `employee_id` BIGINT UNSIGNED NOT NULL COMMENT '员工ID',
  `account_set_id` BIGINT UNSIGNED NOT NULL COMMENT '账套ID',
  
  -- 登记日期
  `registration_date` DATE NOT NULL COMMENT '登记日期',
  
  -- 基本信息
  `name` VARCHAR(50) NOT NULL COMMENT '姓名',
  `gender` ENUM('male', 'female') NOT NULL COMMENT '性别',
  `ethnicity` VARCHAR(50) NULL DEFAULT NULL COMMENT '民族',
  `political_status` VARCHAR(50) NULL DEFAULT NULL COMMENT '政治面貌',
  `place_of_origin` VARCHAR(100) NULL DEFAULT NULL COMMENT '籍贯',
  `birth_date` DATE NULL DEFAULT NULL COMMENT '出生年月',
  `graduated_school` VARCHAR(200) NULL DEFAULT NULL COMMENT '毕业学校',
  `graduation_date` DATE NULL DEFAULT NULL COMMENT '毕业时间',
  `education_level` VARCHAR(50) NULL DEFAULT NULL COMMENT '文化程度',
  `major` VARCHAR(100) NULL DEFAULT NULL COMMENT '所学专业',
  `degree` VARCHAR(50) NULL DEFAULT NULL COMMENT '学位',
  `technical_title` VARCHAR(50) NULL DEFAULT NULL COMMENT '技术职称',
  `health_status` VARCHAR(50) NULL DEFAULT NULL COMMENT '健康状况',
  `height` INT NULL DEFAULT NULL COMMENT '身高(cm)',
  `weight` DECIMAL(5, 2) NULL DEFAULT NULL COMMENT '体重(kg)',
  `marital_status` VARCHAR(20) NULL DEFAULT NULL COMMENT '婚姻状况',
  `id_number` VARCHAR(18) NOT NULL COMMENT '身份证号码',
  `current_residence` VARCHAR(200) NULL DEFAULT NULL COMMENT '现居住地',
  `household_registration` VARCHAR(200) NULL DEFAULT NULL COMMENT '户口所在地',
  
  -- 就业信息
  `position` VARCHAR(100) NULL DEFAULT NULL COMMENT '岗位',
  `desired_location` VARCHAR(100) NULL DEFAULT NULL COMMENT '求职地区',
  `accept_assignment` TINYINT(1) NULL DEFAULT NULL COMMENT '是否服从调配',
  `contact_address` VARCHAR(200) NULL DEFAULT NULL COMMENT '联系地址',
  `contact_phone` VARCHAR(20) NULL DEFAULT NULL COMMENT '联系电话',
  
  -- 备注
  `remarks` TEXT NULL DEFAULT NULL COMMENT '备注',
  
  -- 声明和签名
  `declaration_agreed` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否同意声明',
  `signature` VARCHAR(100) NULL DEFAULT NULL COMMENT '本人签名',
  
  -- JSON字段存储多行数据
  `education_background` JSON NULL DEFAULT NULL COMMENT '学习简历',
  `work_experience` JSON NULL DEFAULT NULL COMMENT '工作经历',
  `family_info` JSON NULL DEFAULT NULL COMMENT '家庭情况',
  
  -- 时间戳
  `created_at` TIMESTAMP NULL DEFAULT NULL COMMENT '创建时间',
  `updated_at` TIMESTAMP NULL DEFAULT NULL COMMENT '更新时间',
  
  PRIMARY KEY (`id`),
  INDEX `idx_employee_id` (`employee_id`),
  INDEX `idx_account_set_id` (`account_set_id`),
  INDEX `idx_id_number` (`id_number`),
  CONSTRAINT `fk_onboarding_forms_employee_id` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='入职登记表';

