-- ============================================
-- 小程序：用户收藏钓场（mini_favorite_venue）
-- ============================================
CREATE TABLE IF NOT EXISTS `mini_favorite_venue` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `mini_user_id` INT(11) UNSIGNED NOT NULL COMMENT '小程序用户 mini_user.id',
  `venue_id` INT(11) UNSIGNED NOT NULL COMMENT '被收藏钓场 fishing_venue.id',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_venue` (`mini_user_id`,`venue_id`),
  KEY `idx_venue_id` (`venue_id`),
  CONSTRAINT `fk_fav_user` FOREIGN KEY (`mini_user_id`) REFERENCES `mini_user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_fav_venue` FOREIGN KEY (`venue_id`) REFERENCES `fishing_venue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='用户收藏钓场';

