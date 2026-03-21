<?php
declare(strict_types=1);

namespace app\controller\Api\Admin;

use app\model\AdminRoleVenue;
use app\model\AdminUser;

/**
 * 钓场数据范围：与 venue-options 一致
 * @return null 可管理全部钓场 | array 仅这些 venue_id | [] 无范围（仅当非超管且配置了空范围时）
 */
trait VenueScopeTrait
{
    /**
     * @return null|int[] null 表示全部；数组表示仅允许这些钓场
     */
    protected function getAdminAllowedVenueIds(): ?array
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
            return null;
        }
        $roleId = (int) $user->role_id;
        if ($roleId < 1) {
            return [];
        }
        $venueIds = AdminRoleVenue::where('role_id', $roleId)->column('venue_id');
        $venueIds = array_values(array_unique(array_map('intval', is_array($venueIds) ? $venueIds : [])));
        if (!empty($venueIds)) {
            return $venueIds;
        }
        // 未配置钓场范围：与池塘逻辑类似，视为可访问全部钓场（仅当已有店铺权限时由调用方控制）
        return null;
    }

    protected function canAccessVenue(int $venueId): bool
    {
        $allowed = $this->getAdminAllowedVenueIds();
        if ($allowed === null) {
            return true;
        }
        return in_array($venueId, $allowed, true);
    }
}
