-- 交易中心 - 订单管理 权限初始化

INSERT INTO `admin_permission` (`name`, `code`, `module`)
VALUES ('订单管理', 'admin.trade.order.manage', 'trade')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`), `module` = VALUES(`module`);

-- 默认赋予超级管理员（code = super_admin）
INSERT IGNORE INTO `admin_role_permission` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `admin_role` r
JOIN `admin_permission` p ON p.code = 'admin.trade.order.manage'
WHERE r.code = 'super_admin';

