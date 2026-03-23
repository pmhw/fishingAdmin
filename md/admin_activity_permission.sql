-- 活动管理后台权限（执行前请确认 admin_permission / admin_role_permission 表已存在）
-- 在「角色与权限」里给需要的角色勾选「活动管理」即可；以下为手工 SQL 示例

INSERT INTO `admin_permission` (`name`, `code`, `module`)
VALUES ('活动管理', 'admin.activity.manage', 'biz')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- 将新权限赋给超级管理员角色（按你库中 super_admin 角色 code 调整）
INSERT INTO `admin_role_permission` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `admin_role` r
CROSS JOIN `admin_permission` p
WHERE r.code = 'super_admin' AND p.code = 'admin.activity.manage'
ON DUPLICATE KEY UPDATE `role_id` = VALUES(`role_id`);
