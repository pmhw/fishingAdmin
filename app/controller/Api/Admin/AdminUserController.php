<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\AdminUser;
use think\response\Json;

/**
 * 后台管理员 CRUD（需登录）
 */
class AdminUserController extends BaseController
{
    /**
     * 列表
     * GET /api/admin/admins?page=1&limit=10
     */
    public function list(): Json
    {
        $page  = (int) $this->request->get('page', 1);
        $limit = min((int) $this->request->get('limit', 10), 100);
        $list  = AdminUser::with('role')->order('id', 'desc')->paginate([
            'list_rows' => $limit,
            'page'      => $page,
        ]);
        $items = array_map(function ($u) {
            $arr = $u->toArray();
            $arr['role_name'] = $u->role ? $u->role->name : '';
            return $arr;
        }, $list->items());
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'list'  => $items,
                'total' => $list->total(),
            ],
        ]);
    }

    /**
     * 详情
     * GET /api/admin/admins/:id
     */
    public function detail(int $id): Json
    {
        $user = AdminUser::with('role')->find($id);
        if (!$user) {
            return json(['code' => 404, 'msg' => '管理员不存在', 'data' => null]);
        }
        $arr = $user->toArray();
        $arr['role_name'] = $user->role ? $user->role->name : '';
        return json(['code' => 0, 'msg' => 'success', 'data' => $arr]);
    }

    /**
     * 新增
     * POST /api/admin/admins
     */
    public function create(): Json
    {
        $username = $this->request->post('username');
        $password = $this->request->post('password');
        $nickname = $this->request->post('nickname', '');
        $roleId   = $this->request->post('role_id');
        if (empty($username) || empty($password)) {
            return json(['code' => 400, 'msg' => '账号和密码不能为空', 'data' => null]);
        }
        if (strlen($password) < 6) {
            return json(['code' => 400, 'msg' => '密码至少6位', 'data' => null]);
        }
        if (AdminUser::where('username', $username)->find()) {
            return json(['code' => 400, 'msg' => '账号已存在', 'data' => null]);
        }
        $data = [
            'username' => $username,
            'password' => $password,
            'nickname' => $nickname,
            'status'   => 1,
        ];
        if ($roleId !== null && $roleId !== '') {
            $data['role_id'] = (int) $roleId;
        }
        $user = AdminUser::create($data);
        $user = AdminUser::with('role')->find($user->id);
        $arr = $user ? $user->toArray() : [];
        $arr['role_name'] = $user && $user->role ? $user->role->name : '';
        return json(['code' => 0, 'msg' => '创建成功', 'data' => $arr]);
    }

    /**
     * 更新
     * PUT /api/admin/admins/:id
     */
    public function update(int $id): Json
    {
        $user = AdminUser::find($id);
        if (!$user) {
            return json(['code' => 404, 'msg' => '管理员不存在', 'data' => null]);
        }
        $nickname = $this->request->param('nickname');
        $status   = $this->request->param('status');
        $password = $this->request->param('password');
        $roleId   = $this->request->param('role_id');
        $data     = [];
        if ($nickname !== null && $nickname !== '') {
            $data['nickname'] = $nickname;
        }
        if ($status !== null && $status !== '') {
            $data['status'] = (int) $status;
        }
        if ($roleId !== null && $roleId !== '') {
            $data['role_id'] = (int) $roleId;
        }
        if ($password !== null && $password !== '') {
            if (strlen($password) < 6) {
                return json(['code' => 400, 'msg' => '密码至少6位', 'data' => null]);
            }
            $data['password'] = $password;
        }
        if (!empty($data)) {
            $user->save($data);
        }
        $user = AdminUser::with('role')->find($id);
        $arr = $user->toArray();
        $arr['role_name'] = $user->role ? $user->role->name : '';
        return json(['code' => 0, 'msg' => '更新成功', 'data' => $arr]);
    }

    /**
     * 删除
     * DELETE /api/admin/admins/:id
     */
    public function delete(int $id): Json
    {
        $user = AdminUser::find($id);
        if (!$user) {
            return json(['code' => 404, 'msg' => '管理员不存在', 'data' => null]);
        }
        $user->delete();
        return json(['code' => 0, 'msg' => '删除成功', 'data' => null]);
    }
}
