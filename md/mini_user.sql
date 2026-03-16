-- ============================================
-- 小程序用户表（微信登录）
-- ============================================
-- 说明：
-- 1. 与 /api/mini/login（jscode2session）配合使用
-- 2. 用 openid 作为唯一标识，可根据需要扩展 unionid、手机号等
-- ============================================

CREATE TABLE IF NOT EXISTS `mini_user` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '用户ID',
  `openid` VARCHAR(64) NOT NULL COMMENT '微信 openid',
  `unionid` VARCHAR(64) DEFAULT NULL COMMENT '微信 unionid（如有）',
  `nickname` VARCHAR(60) DEFAULT NULL COMMENT '昵称',
  `is_vip` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否会员：0-否 1-是',
  `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT '账户余额（元）',
  `avatar` VARCHAR(255) DEFAULT NULL COMMENT '头像 URL',
  `gender` TINYINT(1) DEFAULT 0 COMMENT '性别：0-未知 1-男 2-女',
  `country` VARCHAR(50) DEFAULT NULL COMMENT '国家',
  `province` VARCHAR(50) DEFAULT NULL COMMENT '省份',
  `city` VARCHAR(50) DEFAULT NULL COMMENT '城市',
  `mobile` VARCHAR(20) DEFAULT NULL COMMENT '手机号（如有绑定）',
  `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '状态：1-正常 0-禁用',
  `last_login_at` DATETIME DEFAULT NULL COMMENT '最后登录时间',
  `last_login_ip` VARCHAR(45) DEFAULT NULL COMMENT '最后登录 IP',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_openid` (`openid`),
  KEY `idx_unionid` (`unionid`),
  KEY `idx_mobile` (`mobile`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='小程序用户表';

