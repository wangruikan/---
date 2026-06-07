-- 我的印章增加账套隔离字段

ALTER TABLE `user_seals`
  ADD COLUMN `account_set_id` BIGINT UNSIGNED NULL COMMENT '账套ID' AFTER `user_id`,
  ADD INDEX `user_seals_user_account_set_index` (`user_id`, `account_set_id`);

-- 回填已有印章到用户当前账套/默认账套，避免原有印章迁移后全部不可见。
UPDATE `user_seals` us
LEFT JOIN `users` u ON u.`id` = us.`user_id`
SET us.`account_set_id` = COALESCE(
  u.`current_account_set_id`,
  u.`account_set_id`,
  (
    SELECT asu.`account_set_id`
    FROM `account_set_users` asu
    WHERE asu.`user_id` = us.`user_id`
    ORDER BY asu.`is_default` DESC, asu.`id` ASC
    LIMIT 1
  )
)
WHERE us.`account_set_id` IS NULL;
