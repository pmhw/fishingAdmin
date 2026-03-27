-- ============================================
-- 经营链路：开钓（正钓/偷驴）- 回鱼 - 卖鱼/收鱼（卖入/卖出）
-- ============================================
-- 目的：
-- 1) 把“收费规则(正钓/偷驴)”落到一次开钓单（session）上
-- 2) 回鱼、卖鱼、收鱼都作为流水事件挂到 session 上（或可独立）
-- 3) 便于按 钓场/池塘/钓位/用户/时间 统计与对账
--
-- 依赖表：
--   mini_user, fishing_venue, fishing_pond, pond_seat, pond_fee_rule, pond_return_rule, fishing_order(可选)
-- 注意：
-- - 执行前先 USE 你的数据库；
-- - 若你不想要外键约束，可删除 CONSTRAINT ... FOREIGN KEY 相关行。
-- ============================================

-- --------------------------------------------
-- 1. 开钓单（fishing_session）
--    一次用户在某钓位/池塘的开钓记录（正钓/偷驴都属于 fee_rule）
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `fishing_session` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `session_no` VARCHAR(40) NOT NULL COMMENT '业务单号（全局唯一）',
  `mini_user_id` INT(11) UNSIGNED NOT NULL COMMENT '小程序用户 mini_user.id',
  `venue_id` INT(11) UNSIGNED NOT NULL COMMENT '钓场 fishing_venue.id',
  `pond_id` INT(11) UNSIGNED NOT NULL COMMENT '池塘 fishing_pond.id',
  `seat_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '钓位 pond_seat.id（可空）',
  `seat_no` INT(11) UNSIGNED DEFAULT NULL COMMENT '钓位序号（冗余）',
  `seat_code` VARCHAR(32) DEFAULT NULL COMMENT '钓位业务编码（冗余）',
  `fee_rule_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '收费规则 pond_fee_rule.id（正钓/偷驴/时长等）',
  `order_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '关联支付订单 fishing_order.id（可空）',
  `start_time` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '开始时间',
  `expire_time` DATETIME DEFAULT NULL COMMENT '到期时间（start_time + 时长），用于自动结束释放座位',
  `timeout_time` DATETIME DEFAULT NULL COMMENT '超时时间（到期由定时任务标记）',
  `end_time` DATETIME DEFAULT NULL COMMENT '结束时间（需人工结束或取消）',
  `status` VARCHAR(20) NOT NULL DEFAULT 'ongoing' COMMENT '状态：ongoing-进行中 timeout-已超时 finished-已结束 settled-已结算 cancelled-已取消',
  `amount_total` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '应收金额（分，可用 fee_rule 推导）',
  `amount_paid` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '实收金额（分，来自订单）',
  `deposit_total` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '押金（分，可选）',
  `remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_session_no` (`session_no`),
  KEY `idx_user_time` (`mini_user_id`, `start_time`),
  KEY `idx_venue_time` (`venue_id`, `start_time`),
  KEY `idx_pond_time` (`pond_id`, `start_time`),
  KEY `idx_expire_time` (`expire_time`),
  KEY `idx_seat_id` (`seat_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_session_user` FOREIGN KEY (`mini_user_id`) REFERENCES `mini_user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_session_venue` FOREIGN KEY (`venue_id`) REFERENCES `fishing_venue` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_session_pond` FOREIGN KEY (`pond_id`) REFERENCES `fishing_pond` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_session_seat` FOREIGN KEY (`seat_id`) REFERENCES `pond_seat` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_session_fee_rule` FOREIGN KEY (`fee_rule_id`) REFERENCES `pond_fee_rule` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_session_order` FOREIGN KEY (`order_id`) REFERENCES `fishing_order` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='开钓单（一次正钓/偷驴/垂钓会话）';

-- --------------------------------------------
-- 2. 回鱼流水（pond_return_log）
--    每次回鱼一条记录，挂到 session 上，便于统计“条数/斤数/金额”
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `pond_return_log` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `session_id` INT(11) UNSIGNED NOT NULL COMMENT '关联开钓单 fishing_session.id',
  `venue_id` INT(11) UNSIGNED NOT NULL COMMENT '钓场 fishing_venue.id（冗余）',
  `pond_id` INT(11) UNSIGNED NOT NULL COMMENT '池塘 fishing_pond.id（冗余）',
  `return_rule_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '回鱼规则 pond_return_rule.id（可空）',
  `return_type` VARCHAR(10) NOT NULL DEFAULT 'jin' COMMENT '回鱼方式：jin-按斤 tiao-按条',
  `qty` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '数量（斤/条）',
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '单价（元/斤 或 元/条）',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '金额（元）',
  `remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_pond_time` (`pond_id`, `created_at`),
  KEY `idx_venue_time` (`venue_id`, `created_at`),
  CONSTRAINT `fk_return_session` FOREIGN KEY (`session_id`) REFERENCES `fishing_session` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_return_venue` FOREIGN KEY (`venue_id`) REFERENCES `fishing_venue` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_return_pond` FOREIGN KEY (`pond_id`) REFERENCES `fishing_pond` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_return_rule` FOREIGN KEY (`return_rule_id`) REFERENCES `pond_return_rule` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='回鱼流水（挂到开钓单）';

-- --------------------------------------------
-- 3. 卖鱼/收鱼流水（fish_trade_log）
--    trade_type：
--      buy_in  = 收鱼/卖入（钓场从用户处收鱼）
--      sell_out= 卖鱼/卖出（钓场卖鱼给用户/他人）
--    可挂到 session（常见：用户开钓后卖鱼），也可不挂（独立交易）
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `fish_trade_log` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `session_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '关联开钓单 fishing_session.id（可空）',
  `mini_user_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '相关用户 mini_user.id（可空：独立进货/批发等）',
  `venue_id` INT(11) UNSIGNED NOT NULL COMMENT '钓场 fishing_venue.id',
  `pond_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '池塘 fishing_pond.id（可空）',
  `trade_type` VARCHAR(20) NOT NULL DEFAULT 'buy_in' COMMENT '类型：buy_in-卖入/收鱼 sell_out-卖出/卖鱼',
  `unit` VARCHAR(10) NOT NULL DEFAULT 'jin' COMMENT '单位：jin-斤 tiao-条',
  `qty` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '数量（斤/条）',
  `unit_price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '单价（元/斤 或 元/条）',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '金额（元）',
  `remark` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
  `images` TEXT COMMENT '凭证图片（JSON 数组）',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_venue_time` (`venue_id`, `created_at`),
  KEY `idx_pond_time` (`pond_id`, `created_at`),
  KEY `idx_session_id` (`session_id`),
  KEY `idx_user_time` (`mini_user_id`, `created_at`),
  KEY `idx_trade_type` (`trade_type`),
  CONSTRAINT `fk_trade_session` FOREIGN KEY (`session_id`) REFERENCES `fishing_session` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_trade_user` FOREIGN KEY (`mini_user_id`) REFERENCES `mini_user` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_trade_venue` FOREIGN KEY (`venue_id`) REFERENCES `fishing_venue` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_trade_pond` FOREIGN KEY (`pond_id`) REFERENCES `fishing_pond` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='卖鱼/收鱼流水（可挂开钓单）';

-- --------------------------------------------
-- 4. 可选优化：订单表增加 session_id（便于从支付单反查开钓单）
--    如果你希望“一笔支付”明确对应“一次开钓单”
-- --------------------------------------------
-- ALTER TABLE `fishing_order`
--   ADD COLUMN `session_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '关联开钓单 fishing_session.id（可空）' AFTER `return_rule_id`,
--   ADD KEY `idx_session_id` (`session_id`),
--   ADD CONSTRAINT `fk_order_session` FOREIGN KEY (`session_id`) REFERENCES `fishing_session` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

