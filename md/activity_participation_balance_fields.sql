-- 活动报名：会员余额抵扣与免押金（与开钓单 use_balance 语义一致）
--
-- 若执行时报 1060 Duplicate column：说明该列已存在，跳过对应语句即可。
-- 两条 ALTER 都报 1060 → 表已具备这两列，无需再执行本文件。
-- 可先检查：SHOW COLUMNS FROM activity_participation WHERE Field IN ('balance_deduct_fen','deposit_waived');
--
-- 1) 仅当尚无 balance_deduct_fen 时执行：
ALTER TABLE `activity_participation`
  ADD COLUMN `balance_deduct_fen` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '报名抵扣的会员余额（分）' AFTER `pay_order_no`;

-- 2) 仅当尚无 deposit_waived 时执行（若上列刚新增，deposit_waived 应紧跟在 balance_deduct_fen 后）：
ALTER TABLE `activity_participation`
  ADD COLUMN `deposit_waived` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=会员且 use_balance 时押金不计入应付' AFTER `balance_deduct_fen`;
