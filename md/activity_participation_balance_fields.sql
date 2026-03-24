-- 活动报名：会员余额抵扣与免押金（与开钓单 use_balance 语义一致）
-- 执行前请确认表名、环境；若列已存在可跳过对应语句

ALTER TABLE `activity_participation`
  ADD COLUMN `balance_deduct_fen` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '报名抵扣的会员余额（分）' AFTER `pay_order_no`,
  ADD COLUMN `deposit_waived` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=会员且 use_balance 时押金不计入应付' AFTER `balance_deduct_fen`;
