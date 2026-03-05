-- ============================================
-- 角色-池塘关联：细分到每个池塘的管理权限
-- ============================================
-- 若角色拥有「池塘管理」权限且本表无该角色的记录 → 可管理全部池塘
-- 若本表存在该角色的记录 → 仅可管理表中指定的池塘
-- ============================================

CREATE TABLE IF NOT EXISTS `admin_role_pond` (
  `role_id` INT(11) UNSIGNED NOT NULL COMMENT '角色ID',
  `pond_id` INT(11) UNSIGNED NOT NULL COMMENT '池塘ID',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`role_id`, `pond_id`),
  KEY `idx_pond_id` (`pond_id`),
  CONSTRAINT `fk_role_pond_role` FOREIGN KEY (`role_id`) REFERENCES `admin_role` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_role_pond_pond` FOREIGN KEY (`pond_id`) REFERENCES `fishing_pond` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色可管理池塘（为空则拥有池塘管理权限时管理全部）';
