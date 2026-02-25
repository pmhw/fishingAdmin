-- ============================================
-- 首页轮播图表
-- ============================================
-- 创建时间: 2026-02-08
-- 说明: 存储首页轮播图数据，包括普通轮播图和排行榜轮播图
-- ============================================

-- 创建轮播图表（若表已存在则跳过）
CREATE TABLE IF NOT EXISTS `banner` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '轮播图ID',
  `type` VARCHAR(20) NOT NULL DEFAULT 'banner' COMMENT '类型：banner-普通轮播图, ranking-排行榜轮播图',
  `title` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '标题',
  `image` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '图片路径',
  `link_url` VARCHAR(255) DEFAULT NULL COMMENT '跳转链接（可选）',
  `link_type` VARCHAR(20) DEFAULT NULL COMMENT '链接类型：page-页面, webview-网页, none-无链接',
  `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序顺序，数字越小越靠前',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：1-启用, 0-禁用',
  `start_time` DATETIME DEFAULT NULL COMMENT '开始时间',
  `end_time` DATETIME DEFAULT NULL COMMENT '结束时间',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`),
  KEY `idx_status` (`status`),
  KEY `idx_sort_order` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='首页轮播图表';

-- 插入示例数据（若 id 已存在则跳过，可重复执行）
INSERT IGNORE INTO `banner` (`id`, `type`, `title`, `image`, `link_url`, `link_type`, `sort_order`, `status`, `start_time`, `end_time`) VALUES
(1, 'banner', '春季垂钓活动', '/images/banner1.jpg', NULL, NULL, 1, 1, NULL, NULL),
(2, 'ranking', '本周排行榜', '', NULL, NULL, 2, 1, NULL, NULL),
(3, 'banner', '会员专享优惠', '/images/banner3.jpg', NULL, NULL, 3, 1, NULL, NULL);

-- ============================================
-- 后台权限：轮播图管理（需先执行 admin_role_permission.sql，确保三张表存在）
-- ============================================
INSERT INTO `admin_permission` (`name`,`code`,`module`) VALUES
('轮播图管理','admin.banner.manage','content')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `module`=VALUES(`module`);

-- 为超级管理员绑定该权限（已绑定则忽略）
INSERT IGNORE INTO `admin_role_permission` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `admin_role` r CROSS JOIN `admin_permission` p WHERE r.code='super_admin' AND p.code='admin.banner.manage';
