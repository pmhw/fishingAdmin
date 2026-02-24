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

    public function setPasswordAttr($value): string
    {
        return password_hash($value, PASSWORD_BCRYPT);
    }

    public function verifyPassword(string $raw): bool
    {
        return password_verify($raw, $this->password);
    }
}
