-- ============================================
-- 角色-钓场管理范围（替代角色-池塘范围）
-- ============================================
-- 说明：
-- - 若 admin_role_venue 表里该角色没有任何记录 → 视为可管理全部钓场
-- - 若有记录 → 仅可管理这些钓场（并自动映射为这些钓场下的池塘可管理范围）
--
-- 执行前请先 USE 你的数据库；
-- ============================================

CREATE TABLE IF NOT EXISTS `admin_role_venue` (
  `role_id` INT(11) UNSIGNED NOT NULL COMMENT '角色ID admin_role.id',
  `venue_id` INT(11) UNSIGNED NOT NULL COMMENT '钓场ID fishing_venue.id',
  PRIMARY KEY (`role_id`, `venue_id`),
  KEY `idx_venue_id` (`venue_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色-钓场关联（可管理钓场范围）';

