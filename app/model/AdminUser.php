<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 后台管理员模型
 */
class AdminUser extends Model
{
    protected $table = 'admin_user';

    protected $hidden = ['password'];

    public function role(): \think\model\relation\BelongsTo
    {
        return $this->belongsTo(AdminRole::class, 'role_id');
    }

    /**
     * 获取该管理员拥有的权限码列表（超级管理员返回 ['*']）
     * @return string[]
     */
    public function getPermissionCodes(): array
    {
        $role = $this->role;
        if (!$role) {
            return [];
        }
        return $role->getPermissionCodes();
    }

    public function setPasswordAttr($value): string
    {
        return password_hash($value, PASSWORD_BCRYPT);
    }

    public function verifyPassword(string $raw): bool
    {
        return password_verify($raw, $this->password);
    }
}
