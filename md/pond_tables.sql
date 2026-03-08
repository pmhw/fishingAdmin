-- ============================================
-- 钓场下：池塘、钓位区域、收费规则、回鱼规则
-- ============================================
-- 依赖：需先存在 fishing_venue 表
-- 关联：池塘 -> 钓场(venue_id)；钓位区域/收费/回鱼 -> 池塘(pond_id)
-- ============================================

-- --------------------------------------------
-- 1. 池塘表（fishing_pond）
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `fishing_pond` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `venue_id` INT(11) UNSIGNED NOT NULL COMMENT '所属钓场 fishing_venue.id',
  `name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '池塘名称',
  `images` TEXT COMMENT '池塘图片，JSON数组如 ["/storage/pond/1.jpg"]',
  `pond_type` VARCHAR(20) NOT NULL DEFAULT 'black_pit' COMMENT '池塘类型: black_pit-黑坑 jin_tang-斤塘 practice-练杆塘',
  `seat_count` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '钓位数（个）',
  `area_mu` DECIMAL(6,2) DEFAULT NULL COMMENT '池塘面积（亩）',
  `water_depth` VARCHAR(50) DEFAULT NULL COMMENT '池塘水深，如 1.5-2米',
  `fish_species` VARCHAR(500) DEFAULT NULL COMMENT '鱼种类型，逗号分隔如 鲫鱼,鲤鱼,草鱼',
  `rod_rule` VARCHAR(500) DEFAULT NULL COMMENT '限杆规则',
  `bait_rule` VARCHAR(500) DEFAULT NULL COMMENT '限饵规则',
  `extra_rule` VARCHAR(500) DEFAULT NULL COMMENT '补充规则',
  `open_time` DATE DEFAULT NULL COMMENT '开塘时间',
  `status` VARCHAR(20) NOT NULL DEFAULT 'open' COMMENT '池塘状态: open-开放 closed-关闭',
  `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序，越小越靠前',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_venue_id` (`venue_id`),
  KEY `idx_status` (`status`),
  KEY `idx_sort_order` (`sort_order`),
  CONSTRAINT `fk_pond_venue` FOREIGN KEY (`venue_id`) REFERENCES `fishing_venue` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='池塘表';

-- --------------------------------------------
-- 2. 钓位区域表（pond_region）：按区域+序号范围配置，如 西岸1~29、中间浮桥30~89
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `pond_region` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `pond_id` INT(11) UNSIGNED NOT NULL COMMENT '所属池塘 fishing_pond.id',
  `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '区域名称，如 西岸、中间浮桥',
  `start_no` INT(11) UNSIGNED NOT NULL COMMENT '钓位起始序号',
  `end_no` INT(11) UNSIGNED NOT NULL COMMENT '钓位结束序号（含）',
  `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序，越小越靠前',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_pond_id` (`pond_id`),
  KEY `idx_sort_order` (`sort_order`),
  CONSTRAINT `fk_region_pond` FOREIGN KEY (`pond_id`) REFERENCES `fishing_pond` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钓位区域表（序号范围）';

-- --------------------------------------------
-- 3. 池塘收费规则表（pond_fee_rule）
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `pond_fee_rule` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `pond_id` INT(11) UNSIGNED NOT NULL COMMENT '所属池塘 fishing_pond.id',
  `name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '收费名称，如 正钓4小时',
  `duration` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '垂钓时长展示，如 4小时、1天',
  `duration_value` DECIMAL(10,2) NULL DEFAULT NULL COMMENT '时长数值（计费用）',
  `duration_unit` VARCHAR(20) NULL DEFAULT NULL COMMENT '时长单位: hour-小时 day-天（计费用）',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '收费金额（元）',
  `deposit` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '押金（元）',
  `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_pond_id` (`pond_id`),
  KEY `idx_sort_order` (`sort_order`),
  CONSTRAINT `fk_fee_pond` FOREIGN KEY (`pond_id`) REFERENCES `fishing_pond` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='池塘收费规则表';

-- --------------------------------------------
-- 4. 池塘回鱼规则表（pond_return_rule）：上下限为条数范围
-- --------------------------------------------
CREATE TABLE IF NOT EXISTS `pond_return_rule` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `pond_id` INT(11) UNSIGNED NOT NULL COMMENT '所属池塘 fishing_pond.id',
  `name` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '规则名称，如 鲫鱼回鱼',
  `lower_limit` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '下限（条数）',
  `upper_limit` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '上限（条数），0可表示不限',
  `return_type` VARCHAR(10) NOT NULL DEFAULT 'jin' COMMENT '回鱼方式: jin-按斤 tiao-按条',
  `amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '金额（元/斤 或 元/条）',
  `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_pond_id` (`pond_id`),
  KEY `idx_sort_order` (`sort_order`),
  CONSTRAINT `fk_return_pond` FOREIGN KEY (`pond_id`) REFERENCES `fishing_pond` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='池塘回鱼规则表（条数范围）';

-- ============================================
-- 后台权限：池塘管理（需先执行 admin_role_permission.sql）
-- ============================================
INSERT INTO `admin_permission` (`name`,`code`,`module`) VALUES
('池塘管理','admin.pond.manage','content')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `module`=VALUES(`module`);

INSERT IGNORE INTO `admin_role_permission` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `admin_role` r CROSS JOIN `admin_permission` p WHERE r.code='super_admin' AND p.code='admin.pond.manage';
