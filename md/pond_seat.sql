-- ============================================
-- 钓位表（独立钓位 + 唯一 code，用于点餐/计费/回鱼时临时关联）
-- ============================================
-- 依赖：fishing_pond、pond_region（可选，用于归属区域）
-- 与现有设计关系：
--   pond_region：按「区域+序号范围」配置（西岸1~29），用于展示与后台配置；
--   pond_seat：每个钓位一行，带唯一 code，用于用户钓鱼时临时关联、点餐、计费、回鱼。
-- 后续：用户开杆/选座时关联 seat_id 或 code，点餐/计费/回鱼接口用 code 或 seat_id 定位。
-- ============================================

CREATE TABLE IF NOT EXISTS `pond_seat` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `pond_id` INT(11) UNSIGNED NOT NULL COMMENT '所属池塘 fishing_pond.id',
  `region_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '所属区域 pond_region.id（可选，便于按区域展示）',
  `seat_no` INT(11) UNSIGNED NOT NULL COMMENT '钓位序号，同池塘内唯一，如 1、2、…、89',
  `code` VARCHAR(32) NOT NULL DEFAULT '' COMMENT '唯一业务编码，用于点餐/计费/回鱼扫码或输入，如 A-01、P1-012',
  `status` VARCHAR(20) NOT NULL DEFAULT 'idle' COMMENT '状态：idle-空闲 in_use-使用中（可选，用于选座）',
  `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`),
  KEY `idx_pond_id` (`pond_id`),
  KEY `idx_region_id` (`region_id`),
  KEY `idx_pond_seat_no` (`pond_id`, `seat_no`),
  KEY `idx_status` (`status`),
  CONSTRAINT `fk_seat_pond` FOREIGN KEY (`pond_id`) REFERENCES `fishing_pond` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_seat_region` FOREIGN KEY (`region_id`) REFERENCES `pond_region` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钓位表（独立钓位+唯一code，点餐/计费/回鱼关联）';
