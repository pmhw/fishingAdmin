<?php
declare (strict_types = 1);

namespace app\controller\Api;

use app\BaseController;
use think\response\Json;

/**
 * 示例 API：用户相关接口
 */
class User extends BaseController
{
    /**
     * 用户列表（示例数据）
     * GET /api/user/list
     */
    public function list(): Json
    {
        $list = [
            ['id' => 1, 'name' => '张三', 'phone' => '13800138001'],
            ['id' => 2, 'name' => '李四', 'phone' => '13800138002'],
            ['id' => 3, 'name' => '王五', 'phone' => '13800138003'],
        ];
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => $list,
        ]);
    }

    /**
     * 用户详情
     * GET /api/user/detail/:id
     */
    public function detail(int $id): Json
    {
        $user = [
            'id'    => $id,
            'name'  => '用户' . $id,
            'phone' => '13800138' . str_pad((string) $id, 3, '0', STR_PAD_LEFT),
            'time'  => date('Y-m-d H:i:s'),
        ];
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => $user,
        ]);
    }

    /**
     * 创建用户（示例：接收参数并返回）
     * POST /api/user/create
     */
    public function create(): Json
    {
        $name  = $this->request->post('name', '');
        $phone = $this->request->post('phone', '');
        if (empty($name) || empty($phone)) {
            return json([
                'code' => 400,
                'msg'  => 'name 和 phone 不能为空',
                'data' => null,
            ]);
        }
        return json([
            'code' => 0,
            'msg'  => '创建成功',
            'data' => [
                'id'    => (int) (microtime(true) * 1000),
                'name'  => $name,
                'phone' => $phone,
            ],
        ]);
    }
}
