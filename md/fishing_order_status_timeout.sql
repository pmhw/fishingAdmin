-- 待支付超时状态（由定时任务 php think session:expire 内 OrderTimeoutService 写入）
-- 若库表已存在，仅需更新字段注释可执行：

ALTER TABLE `fishing_order`
  MODIFY COLUMN `status` VARCHAR(20) NOT NULL DEFAULT 'pending'
  COMMENT '订单状态：pending-待支付 paid-已支付 timeout-支付超时 closed-已关闭 refund-已退款';
