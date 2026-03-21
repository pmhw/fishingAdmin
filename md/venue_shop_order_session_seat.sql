-- 店铺订单：关联开钓单与座位快照（先开卡/开钓后才可下单）
-- 在业务库执行（已存在 venue_shop_order 表）

ALTER TABLE `venue_shop_order`
  ADD COLUMN `fishing_session_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '下单时进行中开钓单 fishing_session.id' AFTER `mini_user_id`,
  ADD COLUMN `pond_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '池塘（冗余）' AFTER `fishing_session_id`,
  ADD COLUMN `seat_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '钓位 pond_seat.id（冗余）' AFTER `pond_id`,
  ADD COLUMN `seat_no` INT(11) UNSIGNED DEFAULT NULL COMMENT '钓位序号（冗余）' AFTER `seat_id`,
  ADD COLUMN `seat_code` VARCHAR(32) DEFAULT NULL COMMENT '钓位编码（冗余）' AFTER `seat_no`,
  ADD KEY `idx_fishing_session` (`fishing_session_id`),
  ADD KEY `idx_pond` (`pond_id`);

-- 说明：无外键，避免历史数据/删 session 时级联问题；座位信息为下单时快照。
