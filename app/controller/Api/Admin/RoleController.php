<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\AdminRole;
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
        $arr['permission_ids'] = $role->permissions()->column('id');
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
        $role->load('permissions');
        return json(['code' => 0, 'msg' => '更新成功', 'data' => $role->toArray()]);
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
        $role->delete();
        return json(['code' => 0, 'msg' => '删除成功', 'data' => null]);
    }
}
