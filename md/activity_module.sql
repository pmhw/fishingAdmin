-- ============================================================
-- 活动模块（活动创建/报名/抽号/积分）
-- 说明：
-- 1) 活动池塘与 pond_id 对应；抽号/分配最终映射到 pond_seat.seat_no
-- 2) 活动收费规则复用 pond_fee_rule：通过增加 pond_fee_rule.activity_id 区分
-- 3) 积分体系新增 mini_user_points_ledger（不复用 mini_user.balance）
-- ============================================================

-- ---------- 1) pond_fee_rule：扩展 activity_id（区分活动收费规则） ----------
ALTER TABLE `pond_fee_rule`
  ADD COLUMN `activity_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '所属活动（仅活动使用；非活动为 NULL）' AFTER `pond_id`,
  ADD KEY `idx_activity_id` (`activity_id`);

-- ---------- 2) activity：活动主表 ----------
CREATE TABLE IF NOT EXISTS `activity` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` VARCHAR(120) NOT NULL COMMENT '活动名',
  `pond_id` INT(11) UNSIGNED NOT NULL COMMENT '活动池塘 fishing_pond.id',
  `participant_count` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '参与人数上限（0 表示不限）',
  `open_time` DATETIME NOT NULL COMMENT '开钓时间（活动开始）',
  `register_deadline` DATETIME NOT NULL COMMENT '报名截止时间',
  `description` TEXT DEFAULT NULL COMMENT '活动描述',
  `status` VARCHAR(20) NOT NULL DEFAULT 'draft' COMMENT 'draft-草稿 published-已发布 closed-已结束',
  `draw_mode` VARCHAR(20) NOT NULL DEFAULT 'random' COMMENT 'random-线上随机 self_pick-线上自选 unified-线上统一抽号 offline-线下现场',
  `unified_draw_enabled` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'unified 模式下：是否已开启统一抽号',
  `points_divisor` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '每1元实付可得积分；0=不发放；积分=floor(实付元×该值)',
  `allow_balance_deduct` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1=允许报名使用会员余额抵扣及免押（与小程序 use_balance 联动）',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_pond_time` (`pond_id`, `open_time`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='活动主表';

-- ---------- 3) activity_participation：活动参与/抽号记录 ----------
CREATE TABLE IF NOT EXISTS `activity_participation` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `activity_id` INT(11) UNSIGNED NOT NULL COMMENT 'activity.id',
  `mini_user_id` INT(11) UNSIGNED NOT NULL COMMENT 'mini_user.id',
  `fee_rule_id` INT(11) UNSIGNED NOT NULL COMMENT 'pond_fee_rule.id（活动收费规则）',
  `pay_order_no` VARCHAR(40) DEFAULT NULL COMMENT '支付订单号（fishing_order.order_no）',
  `balance_deduct_fen` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '报名抵扣的会员余额（分）',
  `deposit_waived` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 COMMENT '1=会员且 use_balance 时押金不计入应付',
  `pay_status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending-待支付 paid-已支付 failed-支付失败',
  `draw_status` VARCHAR(30) NOT NULL DEFAULT 'waiting_paid' COMMENT 'waiting_paid-已报名待付 draw_waiting_unified-待统一抽号 assigned-已分配 cancelled-已取消',
  `desired_seat_no` INT(11) UNSIGNED DEFAULT NULL COMMENT '自选号码：pond_seat.seat_no（仅 self_pick 需要）',
  `assigned_seat_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '分配到的钓位 pond_seat.id',
  `assigned_seat_no` INT(11) UNSIGNED DEFAULT NULL COMMENT '冗余：pond_seat.seat_no',
  `assigned_session_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '创建的 fishing_session.id（占座）',
  `claimed_points_at` DATETIME DEFAULT NULL COMMENT '积分领取时间（空表示未领取）',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_activity_user` (`activity_id`, `mini_user_id`) COMMENT '同一活动用户只允许参与一次（可按需调整）',
  UNIQUE KEY `uk_activity_seat_assigned` (`activity_id`, `assigned_seat_id`) COMMENT '同一活动内每个钓位最多分配给一个用户',
  KEY `idx_activity_paid` (`activity_id`, `pay_status`),
  KEY `idx_activity_draw` (`activity_id`, `draw_status`),
  KEY `idx_pay_order_no` (`pay_order_no`),
  CONSTRAINT `fk_participation_activity` FOREIGN KEY (`activity_id`) REFERENCES `activity` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='活动参与记录';

-- ---------- 4) mini_user_points_ledger：积分流水 ----------
CREATE TABLE IF NOT EXISTS `mini_user_points_ledger` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `mini_user_id` INT(11) UNSIGNED NOT NULL COMMENT 'mini_user.id',
  `activity_participation_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '关联 activity_participation.id（积分来源）',
  `delta_points` INT(11) NOT NULL COMMENT '积分变动（本需求为正数发放）',
  `reason` VARCHAR(120) NOT NULL DEFAULT 'activity_points_claim' COMMENT '原因',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  KEY `idx_user_time` (`mini_user_id`, `created_at`),
  KEY `idx_participation` (`activity_participation_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='积分流水';

