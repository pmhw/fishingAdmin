-- ============================================
-- 系统配置表（Key-Value 存储）
-- ============================================
-- 说明：用于存储各类配置项，key 唯一，value 为字符串，remark 为备注说明
-- ============================================

CREATE TABLE IF NOT EXISTS `system_config` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `config_key` VARCHAR(128) NOT NULL COMMENT '配置键，唯一',
  `config_value` TEXT COMMENT '配置值',
  `remark` VARCHAR(255) DEFAULT NULL COMMENT '备注说明',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_config_key` (`config_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统配置表(key-value)';

-- ============================================
-- 后台权限：全局配置（需先执行 admin_role_permission.sql）
-- ============================================
INSERT INTO `admin_permission` (`name`,`code`,`module`) VALUES
('全局配置','admin.config.manage','misc')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `module`=VALUES(`module`);

INSERT IGNORE INTO `admin_role_permission` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `admin_role` r CROSS JOIN `admin_permission` p WHERE r.code='super_admin' AND p.code='admin.config.manage';
