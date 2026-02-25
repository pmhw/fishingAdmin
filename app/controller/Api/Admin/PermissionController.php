<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\AdminPermission;
use think\response\Json;

/**
 * 权限列表（供前端分配权限时展示）
 */
class PermissionController extends BaseController
{
    /**
     * 全部权限列表（按 module 分组）
     * GET /api/admin/permissions
     */
    public function list(): Json
    {
        $list = AdminPermission::order('module', 'asc')->order('id', 'asc')->select();
        $grouped = [];
        foreach ($list as $p) {
            $m = $p->module ?: 'other';
            if (!isset($grouped[$m])) {
                $grouped[$m] = [];
            }
            $grouped[$m][] = $p->toArray();
        }
        return json(['code' => 0, 'msg' => 'success', 'data' => $grouped]);
    }
}
