<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\AdminRole;
use app\model\AdminRolePond;
use app\model\AdminRoleVenue;
use app\model\AdminUser;
use think\response\Json;

/**
 * 角色管理（含权限分配）
 */
class RoleController extends BaseController
{
    /**
     * 角色列表
     * GET /api/admin/roles
     */
    public function list(): Json
    {
        $list = AdminRole::order('id', 'asc')->select();
        return json(['code' => 0, 'msg' => 'success', 'data' => $list->toArray()]);
    }

    /**
     * 角色详情（含已选权限 id 列表）
     * GET /api/admin/roles/:id
     */
    public function detail(int $id): Json
    {
        $role = AdminRole::find($id);
        if (!$role) {
            return json(['code' => 404, 'msg' => '角色不存在', 'data' => null]);
        }
        $arr = $role->toArray();
        $ids = $role->permissions()->column('id');
        $arr['permission_ids'] = array_values(array_map('intval', is_array($ids) ? $ids : []));
        return json(['code' => 0, 'msg' => 'success', 'data' => $arr]);
    }

    /**
     * 新增角色
     * POST /api/admin/roles  body: name, code, description?, permission_ids?
     */
    public function create(): Json
    {
        $name = $this->request->post('name');
        $code = $this->request->post('code');
        $description = $this->request->post('description', '');
        $permissionIds = $this->request->post('permission_ids/a', []);
        if (empty($name) || empty($code)) {
            return json(['code' => 400, 'msg' => '角色名称和编码不能为空', 'data' => null]);
        }
        if (AdminRole::where('code', $code)->find()) {
            return json(['code' => 400, 'msg' => '角色编码已存在', 'data' => null]);
        }
        $role = AdminRole::create([
            'name'        => $name,
            'code'        => $code,
            'description' => $description,
        ]);
        $role->syncPermissions(is_array($permissionIds) ? $permissionIds : []);
        return json(['code' => 0, 'msg' => '创建成功', 'data' => $role->toArray()]);
    }

    /**
     * 更新角色（含权限分配）
     * PUT /api/admin/roles/:id  body: name?, code?, description?, permission_ids?
     */
    public function update(int $id): Json
    {
        $role = AdminRole::find($id);
        if (!$role) {
            return json(['code' => 404, 'msg' => '角色不存在', 'data' => null]);
        }
        $name = $this->request->param('name');
        $code = $this->request->param('code');
        $description = $this->request->param('description');
        $permissionIds = $this->request->param('permission_ids/a', null);
        $data = [];
        if ($name !== null && $name !== '') {
            $data['name'] = $name;
        }
        if ($code !== null && $code !== '') {
            if ($role->code !== $code && AdminRole::where('code', $code)->find()) {
                return json(['code' => 400, 'msg' => '角色编码已存在', 'data' => null]);
            }
            $data['code'] = $code;
        }
        if ($description !== null) {
            $data['description'] = $description;
        }
        if (!empty($data)) {
            $role->save($data);
        }
        if ($permissionIds !== null) {
            $role->syncPermissions(is_array($permissionIds) ? $permissionIds : []);
        }
        $role = AdminRole::with('permissions')->find($id);
        return json(['code' => 0, 'msg' => '更新成功', 'data' => $role ? $role->toArray() : []]);
    }

    /**
     * 删除角色
     * DELETE /api/admin/roles/:id
     */
    public function delete(int $id): Json
    {
        $role = AdminRole::find($id);
        if (!$role) {
            return json(['code' => 404, 'msg' => '角色不存在', 'data' => null]);
        }
        if ($role->code === 'super_admin') {
            return json(['code' => 400, 'msg' => '不可删除超级管理员角色', 'data' => null]);
        }
        $count = AdminUser::where('role_id', $id)->count();
        if ($count > 0) {
            return json(['code' => 400, 'msg' => '该角色下还有管理员，无法删除', 'data' => null]);
        }
        $role->permissions()->detach();
        AdminRolePond::where('role_id', $id)->delete();
        AdminRoleVenue::where('role_id', $id)->delete();
        $role->delete();
        return json(['code' => 0, 'msg' => '删除成功', 'data' => null]);
    }

    /**
     * 角色可管理钓场 ID 列表（用于权限细分）
     * GET /api/admin/roles/:id/venues  → { venue_ids: [1,2,3] }
     * 空数组表示「全部钓场」，非空表示仅可管理这些钓场
     */
    public function venues(int $id): Json
    {
        $role = AdminRole::find($id);
        if (!$role) {
            return json(['code' => 404, 'msg' => '角色不存在', 'data' => null]);
        }
        $venueIds = AdminRoleVenue::where('role_id', $id)->column('venue_id');
        return json(['code' => 0, 'msg' => 'success', 'data' => ['venue_ids' => array_map('intval', $venueIds)]]);
    }

    /**
     * 设置角色可管理钓场
     * PUT /api/admin/roles/:id/venues  body: venue_ids [1,2,3]
     * 传空数组 = 管理全部钓场；传非空 = 仅管理这些钓场
     */
    public function updateVenues(int $id): Json
    {
        $role = AdminRole::find($id);
        if (!$role) {
            return json(['code' => 404, 'msg' => '角色不存在', 'data' => null]);
        }
        $venueIds = [];
        $raw = $this->request->getContent();
        if ($raw !== '' && $raw !== false) {
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded) && isset($decoded['venue_ids']) && is_array($decoded['venue_ids'])) {
                $venueIds = $decoded['venue_ids'];
            }
        }
        if (empty($venueIds)) {
            $fromParam = $this->request->param('venue_ids/a', []);
            $venueIds = is_array($fromParam) ? $fromParam : [];
        }
        $venueIds = array_values(array_unique(array_map('intval', array_filter($venueIds))));
        AdminRoleVenue::where('role_id', $id)->delete();
        foreach ($venueIds as $vid) {
            if ($vid > 0) {
                AdminRoleVenue::create(['role_id' => $id, 'venue_id' => $vid]);
            }
        }
        $saved = AdminRoleVenue::where('role_id', $id)->column('venue_id');
        return json(['code' => 0, 'msg' => '保存成功', 'data' => ['venue_ids' => array_map('intval', $saved)]]);
    }

    /**
     * 角色可管理池塘 ID 列表（用于权限细分）
     * GET /api/admin/roles/:id/ponds  → { pond_ids: [1,2,3] }
     * 空数组表示「全部池塘」，非空表示仅可管理这些池塘
     */
    public function ponds(int $id): Json
    {
        $role = AdminRole::find($id);
        if (!$role) {
            return json(['code' => 404, 'msg' => '角色不存在', 'data' => null]);
        }
        $pondIds = AdminRolePond::where('role_id', $id)->column('pond_id');
        return json(['code' => 0, 'msg' => 'success', 'data' => ['pond_ids' => array_map('intval', $pondIds)]]);
    }

    /**
     * 设置角色可管理池塘
     * PUT /api/admin/roles/:id/ponds  body: pond_ids [1,2,3]
     * 传空数组 = 管理全部池塘；传非空 = 仅管理这些池塘
     */
    public function updatePonds(int $id): Json
    {
        $role = AdminRole::find($id);
        if (!$role) {
            return json(['code' => 404, 'msg' => '角色不存在', 'data' => null]);
        }
        $pondIds = [];
        $raw = $this->request->getContent();
        if ($raw !== '' && $raw !== false) {
            $decoded = json_decode((string) $raw, true);
            if (is_array($decoded) && isset($decoded['pond_ids']) && is_array($decoded['pond_ids'])) {
                $pondIds = $decoded['pond_ids'];
            }
        }
        if (empty($pondIds)) {
            $fromParam = $this->request->param('pond_ids/a', []);
            $pondIds = is_array($fromParam) ? $fromParam : [];
        }
        $pondIds = array_values(array_unique(array_map('intval', array_filter($pondIds))));
        AdminRolePond::where('role_id', $id)->delete();
        foreach ($pondIds as $pid) {
            if ($pid > 0) {
                AdminRolePond::create(['role_id' => $id, 'pond_id' => $pid]);
            }
        }
        $saved = AdminRolePond::where('role_id', $id)->column('pond_id');
        return json(['code' => 0, 'msg' => '保存成功', 'data' => ['pond_ids' => array_map('intval', $saved)]]);
    }
}
