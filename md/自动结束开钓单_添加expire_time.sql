-- ============================================
-- 自动结束开钓单（方案A）：
-- 1) fishing_session 增加 expire_time
-- 2) 建索引，便于定时任务快速扫描
-- ============================================

ALTER TABLE `fishing_session`
  ADD COLUMN `expire_time` DATETIME DEFAULT NULL COMMENT '到期时间（start_time + 时长）' AFTER `start_time`,
  ADD KEY `idx_expire_time` (`expire_time`);

