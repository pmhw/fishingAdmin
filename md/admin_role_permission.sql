-- ============================================
-- 后台角色与权限（RBAC）
-- ============================================
-- 使用前请先有 admin_user 表。执行后需给 admin_user 表增加 role_id 字段（见下方 ALTER）。
-- ============================================

-- 角色表
CREATE TABLE IF NOT EXISTS `admin_role` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL COMMENT '角色名称',
  `code` VARCHAR(50) NOT NULL COMMENT '角色编码',
  `description` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台角色表';

-- 权限表
CREATE TABLE IF NOT EXISTS `admin_permission` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL COMMENT '展示名称',
  `code` VARCHAR(80) NOT NULL COMMENT '权限码',
  `module` VARCHAR(50) DEFAULT NULL COMMENT '模块/分组',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='后台权限表';

-- 角色-权限关联
CREATE TABLE IF NOT EXISTS `admin_role_permission` (
  `role_id` INT(11) UNSIGNED NOT NULL,
  `permission_id` INT(11) UNSIGNED NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `idx_permission_id` (`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='角色-权限关联';

-- admin_user 增加 role_id（若已存在该字段可跳过）
ALTER TABLE `admin_user` ADD COLUMN `role_id` INT(11) UNSIGNED DEFAULT NULL COMMENT '角色ID' AFTER `status`;
ALTER TABLE `admin_user` ADD KEY `idx_role_id` (`role_id`);

-- 初始化：超级管理员角色
INSERT INTO `admin_role` (`name`,`code`,`description`) VALUES
('超级管理员','super_admin','拥有全部权限')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`);

-- 初始化：常用权限码
INSERT INTO `admin_permission` (`name`,`code`,`module`) VALUES
('管理员列表','admin.user.list','user'),
('新增管理员','admin.user.create','user'),
('编辑管理员','admin.user.update','user'),
('删除管理员','admin.user.delete','user'),
('角色与权限','admin.role.manage','system'),
('全局配置','admin.config.manage','misc'),
('经营管理-开钓单','admin.biz.session.manage','biz'),
('经营管理-回鱼流水','admin.biz.return.manage','biz'),
('经营管理-卖鱼/收鱼流水','admin.biz.trade.manage','biz')
ON DUPLICATE KEY UPDATE `name`=VALUES(`name`), `module`=VALUES(`module`);

-- 超级管理员角色拥有全部权限（将上面插入的 permission 都挂到 super_admin 上）
INSERT INTO `admin_role_permission` (`role_id`,`permission_id`)
SELECT r.id, p.id FROM `admin_role` r CROSS JOIN `admin_permission` p WHERE r.code='super_admin'
ON DUPLICATE KEY UPDATE role_id=role_id;

-- 将默认 admin 用户设为超级管理员（按你的 admin 用户 id 调整，这里假设 id=1）
UPDATE `admin_user` SET `role_id`=(SELECT id FROM `admin_role` WHERE code='super_admin' LIMIT 1) WHERE id=1;
