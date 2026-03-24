-- 活动：是否允许报名使用会员余额抵扣及免押（与小程序 use_balance 联动）
-- 已有库执行；新建库请直接用更新后的 md/activity_module.sql

ALTER TABLE `activity`
  ADD COLUMN `allow_balance_deduct` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=允许报名使用会员余额抵扣及免押' AFTER `points_divisor`;
