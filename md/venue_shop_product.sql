-- ============================================
-- 钓场店铺 · 公共商品库 + 多规格 + 按店库存/售价
-- ============================================
-- 概念：
--   product / product_sku     → 平台公共「商品库」（SPU / SKU）
--   venue_product             → 某钓场「上架了哪些 SPU」
--   venue_product_sku         → 该店每个 SKU 的售价、库存、上下架
-- 价格：只在 venue_product_sku.price（店内价）；product_sku.default_price 为建议价，可选
-- ============================================

-- ---------- 商品分类（可选，先建空表方便后台扩展）----------
CREATE TABLE IF NOT EXISTS `product_category` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '分类名，如饵料/线组/饮料',
  `parent_id` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '父级，0 为顶级',
  `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序，越小越靠前',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1-启用 0-禁用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_parent` (`parent_id`),
  KEY `idx_status_sort` (`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品分类';

-- ---------- 公共商品库 SPU ----------
CREATE TABLE IF NOT EXISTS `product` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `category_id` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT '分类，0 表示未分类',
  `name` VARCHAR(120) NOT NULL DEFAULT '' COMMENT '商品名称',
  `intro` VARCHAR(500) DEFAULT NULL COMMENT '短描述/卖点',
  `cover_image` VARCHAR(255) DEFAULT NULL COMMENT '封面图',
  `images` TEXT COMMENT '多图 JSON 数组',
  `detail` MEDIUMTEXT COMMENT '详情富文本或 HTML',
  `unit` VARCHAR(16) NOT NULL DEFAULT '件' COMMENT '计量单位展示，如件/包/瓶',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1-库内启用 0-停用（停用后新店不可再上架）',
  `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '库内列表排序',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_category` (`category_id`),
  KEY `idx_status_sort` (`status`,`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='公共商品库-SPU';

-- ---------- 规格 SKU（同一 SPU 下多条，每条独立建议价）----------
CREATE TABLE IF NOT EXISTS `product_sku` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `product_id` INT(11) UNSIGNED NOT NULL COMMENT '所属 SPU',
  `spec_label` VARCHAR(128) NOT NULL DEFAULT '' COMMENT '规格展示名，如 500ml×1袋 / 红色-L码',
  `spec_json` JSON DEFAULT NULL COMMENT '结构化规格，如 {"容量":"500ml","口味":"腥香"}，可选',
  `sku_code` VARCHAR(64) DEFAULT NULL COMMENT '条码/内部编码，可选',
  `default_price` DECIMAL(10,2) DEFAULT NULL COMMENT '建议零售价，同步到店铺时可作默认值',
  `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '同一商品下规格排序',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1-启用 0-停用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_product` (`product_id`),
  KEY `idx_product_status` (`product_id`,`status`),
  CONSTRAINT `fk_product_sku_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品规格-SKU';

-- ---------- 钓场与 SPU 关联（本店是否售卖该商品）----------
CREATE TABLE IF NOT EXISTS `venue_product` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `venue_id` INT(11) UNSIGNED NOT NULL COMMENT '钓场 ID，关联 fishing_venue.id',
  `product_id` INT(11) UNSIGNED NOT NULL COMMENT '公共库 SPU',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1-在售 0-本店下架（不展示）',
  `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '本店商品排序',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_venue_product` (`venue_id`,`product_id`),
  KEY `idx_venue_status` (`venue_id`,`status`),
  CONSTRAINT `fk_venue_product_venue` FOREIGN KEY (`venue_id`) REFERENCES `fishing_venue` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_venue_product_product` FOREIGN KEY (`product_id`) REFERENCES `product` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钓场店铺-已上架SPU';

-- ---------- 店内 SKU：售价 + 库存（核心）----------
CREATE TABLE IF NOT EXISTS `venue_product_sku` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `venue_product_id` INT(11) UNSIGNED NOT NULL COMMENT '关联 venue_product',
  `product_sku_id` INT(11) UNSIGNED NOT NULL COMMENT '公共库 SKU',
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '本店该规格售价（元）',
  `stock` INT(11) NOT NULL DEFAULT 0 COMMENT '本店库存，整数；称重商品可后续改 decimal',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1-可售 0-本规格停售',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vp_sku` (`venue_product_id`,`product_sku_id`),
  KEY `idx_product_sku` (`product_sku_id`),
  CONSTRAINT `fk_vps_venue_product` FOREIGN KEY (`venue_product_id`) REFERENCES `venue_product` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vps_product_sku` FOREIGN KEY (`product_sku_id`) REFERENCES `product_sku` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钓场店铺-SKU售价库存';

-- ============================================
-- 业务规则说明（实现时注意）
-- ============================================
-- 1. 给某店「添加商品」：插入 venue_product，再为需要的每条 product_sku 插入 venue_product_sku
--    （价格可填 product_sku.default_price，库存默认 0）。
-- 2. 公共库新增 SKU：已上架该 SPU 的店可批量「补齐新规格」或仅新店自动带出。
-- 3. 校验：venue_product_sku 的 product_sku 必须属于 venue_product.product_id
--    （建议在 Service 层校验，避免跨 SPU 误绑）。
-- 4. 下单扣库存：事务内更新 venue_product_sku.stock，并写订单明细（订单表需另设计）。
-- ============================================

-- ============================================
-- 后台权限（需在 admin_permission / admin_role_permission 已存在的前提下执行）
-- ============================================
INSERT INTO `admin_permission` (`name`, `code`, `module`) VALUES
('店铺-公共商品库', 'admin.shop.product.manage', 'shop'),
('店铺-钓场选品库存', 'admin.shop.venue.manage', 'shop')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `module` = VALUES(`module`);

INSERT IGNORE INTO `admin_role_permission` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `admin_role` r
CROSS JOIN `admin_permission` p
WHERE r.code = 'super_admin'
  AND p.code IN ('admin.shop.product.manage', 'admin.shop.venue.manage');
