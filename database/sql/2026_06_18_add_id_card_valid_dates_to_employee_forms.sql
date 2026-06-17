ALTER TABLE `onboarding_forms`
  ADD COLUMN `id_card_valid_from` DATE NULL COMMENT '身份证有效期开始' AFTER `id_number`,
  ADD COLUMN `id_card_valid_until` DATE NULL COMMENT '身份证有效期至' AFTER `id_card_valid_from`;

ALTER TABLE `employee_registration_forms`
  ADD COLUMN `id_card_valid_from` DATE NULL COMMENT '身份证有效期开始' AFTER `id_number`,
  ADD COLUMN `id_card_valid_until` DATE NULL COMMENT '身份证有效期至' AFTER `id_card_valid_from`;
