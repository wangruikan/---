ALTER TABLE `projects`
  MODIFY `delivery_frequency` ENUM('monthly', 'quarterly', 'semiannual', 'annual') NULL DEFAULT 'monthly' COMMENT '交付频率';
