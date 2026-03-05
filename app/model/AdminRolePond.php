<?php
declare (strict_types = 1);

namespace app\model;

use think\Model;

/**
 * 角色-池塘关联：该角色可管理的池塘 ID 列表
 * 仅当角色拥有 admin.pond.manage 时生效；
 * 若本表无该角色记录 → 可管理全部池塘；若有记录 → 仅可管理表中指定的池塘
 */
class AdminRolePond extends Model
{
    protected $table = 'admin_role_pond';
}
