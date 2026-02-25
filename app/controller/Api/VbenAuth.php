<?php
declare (strict_types = 1);

namespace app\controller\Api;

use app\BaseController;
use app\controller\Api\Admin\Auth as AdminAuth;
use app\model\AdminUser;
use think\response\Json;

/**
 * 供官方 Vben Admin 对接的兼容接口（返回 code/data/message、accessToken、userInfo.roles/realName）
 */
class VbenAuth extends BaseController
{
    /**
     * 登录 - Vben 格式
     * POST /api/auth/login
     * 请求体: username, password
     * 返回: { code: 0, data: { accessToken, userInfo: { roles, realName, ... } }, message }
     */
    public function login(): Json
    {
        $username = $this->request->post('username');
        $password = $this->request->post('password');
        if (empty($username) || empty($password)) {
            return $this->vbenJson(400, null, '账号和密码不能为空');
        }
        $user = AdminUser::where('username', $username)->find();
        if (!$user || !$user->verifyPassword($password)) {
            return $this->vbenJson(401, null, '账号或密码错误');
        }
        if ($user->status !== 1) {
            return $this->vbenJson(403, null, '账号已禁用');
        }
        $token = 'admin_token:' . bin2hex(random_bytes(32));
        \think\facade\Cache::set($token, $user->id, 86400 * 7);
        $user->save([
            'last_login_at' => date('Y-m-d H:i:s'),
            'last_login_ip'  => $this->request->ip(),
        ]);
        $userInfo = $this->formatUserInfo($user);
        return $this->vbenJson(0, [
            'accessToken' => $token,
            'userInfo'    => $userInfo,
        ], 'success');
    }

    /**
     * 获取当前用户信息 - Vben 格式
     * GET /api/user/info
     * Header: Authorization: Bearer <accessToken>
     * 返回: { code: 0, data: { roles, realName, ... }, message }
     */
    public function userInfo(): Json
    {
        $token = $this->request->header('Authorization', '');
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }
        $adminId = $token ? AdminAuth::getAdminIdByToken($token) : null;
        if (!$adminId) {
            return $this->vbenJson(401, null, '未登录或登录已过期');
        }
        $user = AdminUser::find($adminId);
        if (!$user || $user->status !== 1) {
            return $this->vbenJson(401, null, '账号不存在或已禁用');
        }
        return $this->vbenJson(0, $this->formatUserInfo($user), 'success');
    }

    /**
     * 权限码（可选）- Vben 格式
     * GET /api/auth/codes
     * 返回: { code: 0, data: [], message }
     */
    public function codes(): Json
    {
        return $this->vbenJson(0, [], 'success');
    }

    private function formatUserInfo(AdminUser $user): array
    {
        $arr = $user->toArray();
        return array_merge($arr, [
            'roles'   => ['admin'],
            'realName' => $arr['nickname'] ?? $arr['username'] ?? '',
        ]);
    }

    private function vbenJson(int $code, $data, string $message): Json
    {
        return json([
            'code'    => $code,
            'data'    => $data,
            'message' => $message,
        ]);
    }
}
