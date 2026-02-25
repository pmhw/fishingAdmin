-- ============================================
-- 轮播图排行榜关联表
-- ============================================
-- 创建时间: 2026-02-08
-- 说明: 存储排行榜轮播图中的排行榜数据
-- ============================================

-- 创建排行榜关联表（若表已存在则跳过）
CREATE TABLE IF NOT EXISTS `banner_ranking` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '排行榜ID',
  `banner_id` INT(11) UNSIGNED NOT NULL COMMENT '关联的轮播图ID',
  `rank` INT(11) NOT NULL COMMENT '排名：1,2,3...',
  `user_name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '用户名称',
  `weight` VARCHAR(20) NOT NULL DEFAULT '' COMMENT '重量，如：8.5kg',
  `spot_name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '钓场名称',
  `user_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '用户ID（可选，关联用户表）',
  `spot_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '钓场ID（可选，关联钓场表）',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_banner_id` (`banner_id`),
  KEY `idx_rank` (`rank`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_spot_id` (`spot_id`),
  CONSTRAINT `fk_banner_ranking_banner` FOREIGN KEY (`banner_id`) REFERENCES `banner` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='轮播图排行榜关联表';

-- 插入示例数据（关联到 banner_id=2 的排行榜轮播图）
INSERT INTO `banner_ranking` (`banner_id`, `rank`, `user_name`, `weight`, `spot_name`, `user_id`, `spot_id`) VALUES
(2, 1, '张钓友', '8.5kg', '阳光钓场', NULL, NULL),
(2, 2, '李钓友', '7.2kg', '清溪钓场', NULL, NULL),
(2, 3, '王钓友', '6.8kg', '湖畔钓场', NULL, NULL);
