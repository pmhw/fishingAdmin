-- ============================================
-- 后台管理员表
-- ============================================
-- 说明: 用于后台登录与权限管理，配合 Vben Admin 等后台使用
-- ============================================

CREATE TABLE IF NOT EXISTS `admin_user` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '管理员ID',
  `username` VARCHAR(50) NOT NULL COMMENT '登录账号',
  `password` VARCHAR(255) NOT NULL COMMENT '密码（bcrypt 哈希）',
  `nickname` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '昵称/姓名',
  `avatar` VARCHAR(255) DEFAULT NULL COMMENT '头像URL',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：1-正常 0-禁用',
  `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip` VARCHAR(45) DEFAULT NULL COMMENT '最后登录IP',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_username` (`username`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台管理员表';

-- 默认管理员：账号 admin，密码 123456（导入后可直接登录，请及时修改密码）
INSERT INTO `admin_user` (`username`, `password`, `nickname`, `status`) VALUES
('admin', '$2y$10$KODweJD04h5zABnKyhSg0.tx9iqNdC1CFgKUJFhOa/UD5lW0k7RAK', '超级管理员', 1);
