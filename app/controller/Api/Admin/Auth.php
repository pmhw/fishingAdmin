<?php
declare (strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use app\model\AdminUser;
use think\facade\Cache;
use think\response\Json;

/**
 * 后台登录 / 初始化
 */
class Auth extends BaseController
{
    private const TOKEN_PREFIX = 'admin_token:'; // token 前缀
    private const TOKEN_TTL = 86400 * 7; // 7 天 登录有效期

    /**
     * 登录
     * POST /api/admin/login
     */
    public function login(): Json
    {
        $username = $this->request->post('username');
        $password = $this->request->post('password');
        if (empty($username) || empty($password)) {
            return json(['code' => 400, 'msg' => '账号和密码不能为空', 'data' => null]);
        }
        $user = AdminUser::where('username', $username)->find();
        if (!$user || !$user->verifyPassword($password)) {
            return json(['code' => 401, 'msg' => '账号或密码错误', 'data' => null]);
        }
        if ($user->status !== 1) {
            return json(['code' => 403, 'msg' => '账号已禁用', 'data' => null]);
        }
        $token = self::TOKEN_PREFIX . bin2hex(random_bytes(32));
        Cache::set($token, $user->id, self::TOKEN_TTL);
        $user->save([
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip'  => $this->request->ip(),
        ]);
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'token' => $token,
                'user'  => $user->toArray(),
            ],
        ]);
    }

    /**
     * 首次初始化管理员（仅当没有任何管理员时可用）
     * POST /api/admin/init
     */
    public function init(): Json
    {
        if (AdminUser::count() > 0) {
            return json(['code' => 403, 'msg' => '已有管理员，无法初始化', 'data' => null]);
        }
        $username = $this->request->post('username', 'admin');
        $password = $this->request->post('password', '123456');
        $nickname = $this->request->post('nickname', '超级管理员');
        if (strlen($password) < 6) {
            return json(['code' => 400, 'msg' => '密码至少6位', 'data' => null]);
        }
        $user = AdminUser::create([
            'username' => $username,
            'password' => $password,
            'nickname'  => $nickname,
            'status'    => 1,
        ]);
        return json([
            'code' => 0,
            'msg'  => '初始化成功，请使用该账号登录',
            'data' => ['user' => $user->toArray()],
        ]);
    }

    /**
     * 当前登录信息（需 token）
     * GET /api/admin/me
     */
    public function me(): Json
    {
        $adminId = $this->request->adminId ?? null;
        if (!$adminId) {
            return json(['code' => 401, 'msg' => '未登录', 'data' => null]);
        }
        $user = AdminUser::find($adminId);
        if (!$user || $user->status !== 1) {
            return json(['code' => 401, 'msg' => '账号不存在或已禁用', 'data' => null]);
        }
        return json(['code' => 0, 'msg' => 'success', 'data' => $user->toArray()]);
    }

    /**
     * 登出（需 token）
     * POST /api/admin/logout
     */
    public function logout(): Json
    {
        $token = $this->request->header('Authorization', '');
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }
        if ($token) {
            Cache::delete($token);
        }
        return json(['code' => 0, 'msg' => '已登出', 'data' => null]);
    }

    /**
     * 供中间件校验：根据 token 返回 adminId
     */
    public static function getAdminIdByToken(string $token): ?int
    {
        if (!str_starts_with($token, self::TOKEN_PREFIX)) {
            return null;
        }
        $id = Cache::get($token);
        return $id ? (int) $id : null;
    }
}
