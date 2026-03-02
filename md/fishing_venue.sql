-- ============================================
-- 钓场表（fishing_venue）
-- ============================================
-- 说明：存储钓场基础信息、位置、营业与收费、状态等，供小程序/后台展示与管理
-- ============================================

CREATE TABLE IF NOT EXISTS `fishing_venue` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '钓场名称',
  `intro` VARCHAR(500) DEFAULT NULL COMMENT '简短简介',
  `description` TEXT COMMENT '详细描述',
  `cover_image` VARCHAR(255) DEFAULT NULL COMMENT '封面图URL',
  `images` TEXT COMMENT '多图，JSON数组如 ["/storage/venue/1.jpg"]',
  `province` VARCHAR(50) DEFAULT NULL COMMENT '省',
  `city` VARCHAR(50) DEFAULT NULL COMMENT '市',
  `district` VARCHAR(50) DEFAULT NULL COMMENT '区/县',
  `address` VARCHAR(255) DEFAULT NULL COMMENT '详细地址',
  `longitude` DECIMAL(10,7) DEFAULT NULL COMMENT '经度',
  `latitude` DECIMAL(10,7) DEFAULT NULL COMMENT '纬度',
  `contact_phone` VARCHAR(30) DEFAULT NULL COMMENT '联系电话',
  `contact_wechat` VARCHAR(64) DEFAULT NULL COMMENT '微信/客服',
  `opening_hours` VARCHAR(200) DEFAULT NULL COMMENT '营业时间',
  `price_type` VARCHAR(20) DEFAULT NULL COMMENT '计费: day-按天 jin-按斤 mix-混合 free-免费',
  `price_info` VARCHAR(255) DEFAULT NULL COMMENT '价格说明',
  `price_min` DECIMAL(10,2) DEFAULT NULL COMMENT '最低价(元)',
  `price_max` DECIMAL(10,2) DEFAULT NULL COMMENT '最高价(元)',
  `facilities` VARCHAR(500) DEFAULT NULL COMMENT '设施，逗号分隔或JSON',
  `fish_species` VARCHAR(500) DEFAULT NULL COMMENT '鱼种，逗号分隔或JSON',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1-上架 0-下架 2-待审核',
  `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序，越小越靠前',
  `view_count` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '浏览量',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_province_city` (`province`,`city`),
  KEY `idx_sort_order` (`sort_order`),
  KEY `idx_price_min` (`price_min`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钓场表';

-- ============================================
-- 后台权限：钓场管理（需先执行 admin_role_permission.sql）
-- ============================================
INSERT INTO `admin_permission` (`name`,`code`,`module`) VALUES
('钓场管理','admin.venue.manage','content')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `module`=VALUES(`module`);

INSERT IGNORE INTO `admin_role_permission` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `admin_role` r CROSS JOIN `admin_permission` p WHERE r.code='super_admin' AND p.code='admin.venue.manage';
