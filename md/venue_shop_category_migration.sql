-- ============================================
-- 增量迁移：每钓场店内商品分类 + venue_product.shop_category_id
-- ============================================
-- 适用：已按旧版执行过 venue_shop_product.sql（无 venue_shop_category、无 shop_category_id）
-- 执行前：USE `你的库名`;
-- ============================================

CREATE TABLE IF NOT EXISTS `venue_shop_category` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `venue_id` INT(11) UNSIGNED NOT NULL COMMENT '钓场 ID',
  `name` VARCHAR(64) NOT NULL DEFAULT '' COMMENT '分类名称',
  `sort_order` INT(11) NOT NULL DEFAULT 0 COMMENT '排序',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1-启用 0-停用',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_venue_sort` (`venue_id`,`sort_order`),
  KEY `idx_venue_status` (`venue_id`,`status`),
  CONSTRAINT `fk_vsc_venue` FOREIGN KEY (`venue_id`) REFERENCES `fishing_venue` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='钓场店铺-店内商品分类';

-- 若列已存在会报错，可忽略本步
ALTER TABLE `venue_product`
  ADD COLUMN `shop_category_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '本店分类，NULL 表示未分组' AFTER `product_id`,
  ADD KEY `idx_shop_category` (`shop_category_id`);

-- 若外键已存在会报错，可忽略
ALTER TABLE `venue_product`
  ADD CONSTRAINT `fk_venue_product_shop_cat` FOREIGN KEY (`shop_category_id`) REFERENCES `venue_shop_category` (`id`) ON DELETE SET NULL;
