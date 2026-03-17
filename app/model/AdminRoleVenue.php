<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 角色-钓场关联：该角色可管理的钓场 ID 列表
 * 若本表无该角色记录 → 可管理全部钓场；若有记录 → 仅可管理表中指定的钓场
 */
class AdminRoleVenue extends Model
{
    protected $table = 'admin_role_venue';
}

