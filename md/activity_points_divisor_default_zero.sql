-- 活动「1元积分」字段：新建默认改为 0（不发放积分）
-- 已有库执行；不改变已有行的数值，仅调整列默认值（新插入/未显式赋值时为 0）

ALTER TABLE `activity`
  MODIFY COLUMN `points_divisor` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '每1元实付可得积分；0=不发放；积分=floor(实付元×该值)';
