-- ============================================
-- 小程序订单表（钓场/池塘/钓位 订单）
-- ============================================
-- 依赖：
--   mini_user       小程序用户
--   fishing_venue   钓场
--   fishing_pond    池塘
--   pond_seat       钓位（可选）
--   pond_fee_rule   池塘收费规则（可选）
--   pond_return_rule 池塘回鱼规则（可选）
-- 说明：
--   1. 一个订单对应一次付费（如一次正钓）
--   2. 使用 order_no 作为业务唯一单号，对接微信支付 out_trade_no
--   3. 与支付表（微信）解耦，仅存必要支付信息
-- ============================================

CREATE TABLE IF NOT EXISTS `fishing_order` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `order_no` VARCHAR(40) NOT NULL COMMENT '业务订单号（全局唯一，对接微信 out_trade_no）',
  `mini_user_id` INT(11) UNSIGNED NOT NULL COMMENT '小程序用户 mini_user.id',
  `venue_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '钓场 fishing_venue.id（冗余，便于统计）',
  `pond_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '池塘 fishing_pond.id',
  `seat_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '钓位 pond_seat.id（可为空）',
  `seat_no` INT(11) UNSIGNED DEFAULT NULL COMMENT '钓位序号（冗余，便于展示）',
  `seat_code` VARCHAR(32) DEFAULT NULL COMMENT '钓位业务编码（冗余，便于扫码展示）',
  `fee_rule_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '收费规则 pond_fee_rule.id（可空）',
  `return_rule_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '回鱼规则 pond_return_rule.id（可空）',
  `description` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '订单描述（用于微信支付 body）',
  `amount_total` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '总金额（单位：分）',
  `amount_paid` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '实付金额（单位：分）',
  `currency` VARCHAR(8) NOT NULL DEFAULT 'CNY' COMMENT '币种，默认 CNY',
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT '订单状态：pending-待支付 paid-已支付 timeout-支付超时 closed-已关闭 refund-已退款',
  `pay_channel` VARCHAR(20) NOT NULL DEFAULT 'wx_mini' COMMENT '支付渠道：wx_mini-微信小程序',
  `pay_trade_no` VARCHAR(64) DEFAULT NULL COMMENT '第三方交易号（如微信 transaction_id）',
  `pay_time` DATETIME DEFAULT NULL COMMENT '支付时间',
  `raw_notify` TEXT COMMENT '支付回调原始数据（可选，用于排查问题）',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_order_no` (`order_no`),
  KEY `idx_mini_user_id` (`mini_user_id`),
  KEY `idx_pond_id` (`pond_id`),
  KEY `idx_seat_id` (`seat_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_order_user` FOREIGN KEY (`mini_user_id`) REFERENCES `mini_user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_order_pond` FOREIGN KEY (`pond_id`) REFERENCES `fishing_pond` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_order_seat` FOREIGN KEY (`seat_id`) REFERENCES `pond_seat` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='小程序订单表（钓场/池塘/钓位订单）';

