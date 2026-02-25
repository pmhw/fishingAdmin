<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

class AdminRole extends Model
{
    protected $table = 'admin_role';

    public function permissions(): \think\model\relation\BelongsToMany
    {
        return $this->belongsToMany(AdminPermission::class, 'admin_role_permission', 'permission_id', 'role_id');
    }

    /**
     * 同步角色拥有的权限 ID 列表
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->detach();
        if (!empty($permissionIds)) {
            $this->permissions()->attach($permissionIds);
        }
    }

    /**
     * 获取该角色权限码列表
     * @return string[]
     */
    public function getPermissionCodes(): array
    {
        if ($this->code === 'super_admin') {
            return ['*']; // 超级管理员视为拥有全部
        }
        return $this->permissions()->column('code');
    }
}
