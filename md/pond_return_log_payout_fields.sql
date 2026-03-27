-- 回鱼流水：打款/转账字段（会员走余额，非会员走微信转账）
-- 执行前可先检查字段是否已存在：
-- SHOW COLUMNS FROM pond_return_log LIKE 'payout_%';

ALTER TABLE `pond_return_log`
  ADD COLUMN `payout_status` VARCHAR(20) NOT NULL DEFAULT 'none' COMMENT 'none-未发起 pending-处理中 success-成功 failed-失败 cancelled-取消' AFTER `remark`,
  ADD COLUMN `payout_channel` VARCHAR(20) DEFAULT NULL COMMENT 'balance-会员余额 wechat-微信转账' AFTER `payout_status`,
  ADD COLUMN `payout_amount` DECIMAL(10,2) DEFAULT NULL COMMENT '实际打款金额（元，默认=amount）' AFTER `payout_channel`,
  ADD COLUMN `payout_out_bill_no` VARCHAR(64) DEFAULT NULL COMMENT '外部转账单号（微信 out_bill_no）' AFTER `payout_amount`,
  ADD COLUMN `payout_time` DATETIME DEFAULT NULL COMMENT '打款成功时间' AFTER `payout_out_bill_no`,
  ADD COLUMN `payout_fail_reason` VARCHAR(255) DEFAULT NULL COMMENT '失败原因（可选）' AFTER `payout_time`,
  ADD COLUMN `payout_raw` TEXT COMMENT '微信返回/回调原文（可选）' AFTER `payout_fail_reason`,
  ADD KEY `idx_payout_status` (`payout_status`),
  ADD KEY `idx_payout_out_bill_no` (`payout_out_bill_no`);

