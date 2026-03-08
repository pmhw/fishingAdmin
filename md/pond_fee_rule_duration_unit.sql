-- 收费规则表增加「时长数值+单位」字段，便于计费
-- 执行前请确认 pond_fee_rule 表已存在

ALTER TABLE `pond_fee_rule`
  ADD COLUMN `duration_value` DECIMAL(10,2) NULL DEFAULT NULL COMMENT '时长数值（与 duration_unit 配合，用于计费）' AFTER `duration`,
  ADD COLUMN `duration_unit` VARCHAR(20) NULL DEFAULT NULL COMMENT '时长单位: hour-小时 day-天' AFTER `duration_value`;

-- 可选：将已有 duration 文本同步到 duration_value + duration_unit（需根据实际数据手工或脚本处理）
-- 例如 "4小时" -> duration_value=4, duration_unit='hour'
-- 例如 "1天" -> duration_value=1, duration_unit='day'
