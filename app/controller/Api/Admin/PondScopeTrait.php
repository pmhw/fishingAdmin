<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\model\AdminRolePond;
use app\model\AdminRoleVenue;
use app\model\AdminUser;
use app\model\FishingPond;

/**
 * 池塘数据范围：按角色-池塘关联细分权限
 * 返回 null = 可管理全部池塘，[] = 无权限，[id,...] = 仅可管理指定池塘
 */
trait PondScopeTrait
{
    private const POND_MANAGE_CODE = 'admin.pond.manage';

    /**
     * 获取当前管理员可管理的池塘 ID 列表
     * @return null 全部池塘 | array 仅这些池塘 ID | [] 无池塘权限
     */
    protected function getAdminAllowedPondIds(): ?array
    {
        $adminId = (int) ($this->request->adminId ?? 0);
        if ($adminId < 1) {
            return [];
        }
        $user = AdminUser::with('role')->find($adminId);
        if (!$user || !$user->role) {
            return [];
        }
        $codes = $user->getPermissionCodes();
        if (in_array('*', $codes, true)) {
            return null; // 超级管理员：全部
        }
        if (!in_array(self::POND_MANAGE_CODE, $codes, true)) {
            return []; // 无池塘管理权限
        }
        $roleId = (int) $user->role_id;

        // 优先使用「钓场范围」：若配置了可管理钓场，则映射为这些钓场下的池塘集合
        $venueIds = AdminRoleVenue::where('role_id', $roleId)->column('venue_id');
        $venueIds = array_values(array_unique(array_map('intval', is_array($venueIds) ? $venueIds : [])));
        if (!empty($venueIds)) {
            $pondIds = FishingPond::whereIn('venue_id', $venueIds)->column('id');
            return array_values(array_unique(array_map('intval', is_array($pondIds) ? $pondIds : [])));
        }

        // 兼容旧逻辑：按「池塘范围」配置
        $pondIds = AdminRolePond::where('role_id', $roleId)->column('pond_id');
        if (empty($pondIds)) {
            return null; // 未配置指定范围 → 全部
        }
        return array_map('intval', $pondIds);
    }

    protected function canAccessPond(int $pondId): bool
    {
        $allowed = $this->getAdminAllowedPondIds();
        if ($allowed === null) {
            return true;
        }
        return in_array($pondId, $allowed, true);
    }
}
