-- 开钓单：增加超时状态与超时时间（超时不等于结束，需管理员手动结束）

ALTER TABLE `fishing_session`
  ADD COLUMN `timeout_time` DATETIME DEFAULT NULL COMMENT '超时时间（到期由定时任务标记）' AFTER `expire_time`;

-- status 为 VARCHAR(20)，无需改字段类型；此处仅补业务语义：
-- ongoing-进行中
-- timeout-已超时（仍占用钓位，需后台手动结束）
-- finished-已结束
-- settled-已结算
-- cancelled-已取消

