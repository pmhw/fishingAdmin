-- ============================================
-- 钓场店铺 · 小程序订单（明细 + 支付衔接 fishing_order）
-- ============================================
-- 在业务库执行（与 venue_shop_product 同一库）
-- 依赖：fishing_venue, mini_user, venue_product, venue_product_sku, product, product_sku, fishing_order（微信支付用）
-- 订单号规则：SO 开头，与 fishing_order.order_no 一致（需微信支付时写入 fishing_order）
-- ============================================

CREATE TABLE IF NOT EXISTS `venue_shop_order` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `order_no` VARCHAR(40) NOT NULL COMMENT '业务单号，唯一；SO 开头；微信 out_trade_no',
  `venue_id` INT(11) UNSIGNED NOT NULL COMMENT '钓场',
  `mini_user_id` INT(11) UNSIGNED NOT NULL COMMENT '下单用户',
  `amount_goods_fen` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '商品合计（分）',
  `balance_deduct_fen` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '会员余额抵扣（分）',
  `wx_amount_fen` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '待微信支付金额（分），0 表示无需微信',
  `status` VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending-待支付 paid-已支付 closed-已关闭',
  `pay_channel` VARCHAR(20) NOT NULL DEFAULT 'wx_mini' COMMENT 'wx_mini | balance | mixed',
  `remark` VARCHAR(255) DEFAULT NULL COMMENT '用户备注',
  `pay_trade_no` VARCHAR(64) DEFAULT NULL COMMENT '微信 transaction_id',
  `pay_time` DATETIME DEFAULT NULL COMMENT '支付完成时间',
  `raw_notify` TEXT COMMENT '支付回调原文（可选）',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_shop_order_no` (`order_no`),
  KEY `idx_venue` (`venue_id`),
  KEY `idx_user` (`mini_user_id`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_vso_venue` FOREIGN KEY (`venue_id`) REFERENCES `fishing_venue` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_vso_user` FOREIGN KEY (`mini_user_id`) REFERENCES `mini_user` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钓场店铺-订单主表';

CREATE TABLE IF NOT EXISTS `venue_shop_order_item` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `shop_order_id` INT(11) UNSIGNED NOT NULL COMMENT 'venue_shop_order.id',
  `venue_product_id` INT(11) UNSIGNED NOT NULL COMMENT '本店 SPU 行',
  `venue_product_sku_id` INT(11) UNSIGNED NOT NULL COMMENT '本店 SKU 行',
  `product_id` INT(11) UNSIGNED NOT NULL COMMENT '公共库 SPU',
  `product_sku_id` INT(11) UNSIGNED NOT NULL COMMENT '公共库 SKU',
  `product_name` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '商品名快照',
  `spec_label` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '规格快照',
  `price_fen` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '单价（分）',
  `quantity` INT(11) UNSIGNED NOT NULL DEFAULT 1 COMMENT '数量',
  `line_total_fen` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '行小计（分）',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_shop_order` (`shop_order_id`),
  CONSTRAINT `fk_vsoi_order` FOREIGN KEY (`shop_order_id`) REFERENCES `venue_shop_order` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钓场店铺-订单明细';

-- 说明：
-- 1. 下单成功即扣减 venue_product_sku.stock；关闭未支付单需自行做库存回滚（后续可接取消接口）。
-- 2. wx_amount_fen>0 时会创建 fishing_order（description=店铺订单），支付走 POST /api/mini/pay/wechat/jsapi。
-- 3. 回调 notify 在 fishing_order 入账后，将同单号 venue_shop_order 置为 paid。
--
-- 【已有库升级】若表已创建，需执行 md/venue_shop_order_session_seat.sql，
-- 增加 fishing_session_id、pond_id、seat_id、seat_no、seat_code（开钓后才能下单 + 后台座位展示）。
