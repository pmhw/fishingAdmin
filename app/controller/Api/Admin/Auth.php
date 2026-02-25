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
    private const TOKEN_PREFIX   = 'admin_token:'; // token 前缀
    private const TOKEN_TTL      = 86400 * 7; // 7 天 登录有效期
    private const CAPTCHA_PREFIX = 'admin_captcha:';
    private const CAPTCHA_TTL    = 300; // 5 分钟

    /**
     * 获取验证码图片
     * GET /api/admin/captcha  返回 { key, image: "data:image/png;base64,..." }
     */
    public function captcha(): Json
    {
        $key   = bin2hex(random_bytes(8));
        $code  = $this->generateCaptchaCode(4);
        Cache::set(self::CAPTCHA_PREFIX . $key, strtolower($code), self::CAPTCHA_TTL);
        $image = $this->drawCaptchaImage($code);
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'key'   => $key,
                'image' => 'data:image/png;base64,' . base64_encode($image),
            ],
        ]);
    }

    /**
     * 登录
     * POST /api/admin/login  body: username, password, captcha_key, captcha
     */
    public function login(): Json
    {
        $username   = $this->request->post('username');
        $password   = $this->request->post('password');
        $captchaKey = $this->request->post('captcha_key');
        $captcha    = $this->request->post('captcha');
        if (empty($username) || empty($password)) {
            return json(['code' => 400, 'msg' => '账号和密码不能为空', 'data' => null]);
        }
        if (empty($captchaKey) || $captcha === '' || $captcha === null) {
            return json(['code' => 400, 'msg' => '请输入验证码', 'data' => null]);
        }
        $cached = Cache::get(self::CAPTCHA_PREFIX . $captchaKey);
        Cache::delete(self::CAPTCHA_PREFIX . $captchaKey);
        if ($cached === null || $cached !== strtolower((string) $captcha)) {
            return json(['code' => 400, 'msg' => '验证码错误或已过期', 'data' => null]);
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
        $userArr = $this->buildUserWithRoleAndPermissions($user);
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'token' => $token,
                'user'  => $userArr,
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
        $userArr = $this->buildUserWithRoleAndPermissions($user);
        return json(['code' => 0, 'msg' => 'success', 'data' => $userArr]);
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
     * 组装带角色与权限的用户数据（角色/权限表未建或异常时降级为无角色、空权限）
     */
    private function buildUserWithRoleAndPermissions(AdminUser $user): array
    {
        try {
            $user->load('role');
        } catch (\Throwable $e) {
            // role_id 或 admin_role 等不存在时不报错
        }
        $userArr = $user->toArray();
        try {
            $userArr['permissions'] = $user->getPermissionCodes();
        } catch (\Throwable $e) {
            $userArr['permissions'] = [];
        }
        return $userArr;
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

    private function generateCaptchaCode(int $len): string
    {
        $chars = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        $code  = '';
        for ($i = 0; $i < $len; $i++) {
            $code .= $chars[random_int(0, strlen($chars) - 1)];
        }
        return $code;
    }

    private function drawCaptchaImage(string $code): string
    {
        $w = 120;
        $h = 40;
        if (!function_exists('imagecreatetruecolor')) {
            return $this->drawCaptchaImageFallback($code, $w, $h);
        }
        $img = imagecreatetruecolor($w, $h);
        if (!$img) {
            return $this->drawCaptchaImageFallback($code, $w, $h);
        }
        $bg = imagecolorallocate($img, random_int(230, 255), random_int(230, 255), random_int(230, 255));
        imagefill($img, 0, 0, $bg);
        for ($i = 0; $i < 4; $i++) {
            $c = imagecolorallocate($img, random_int(100, 200), random_int(100, 200), random_int(100, 200));
            imageline($img, random_int(0, $w), random_int(0, $h), random_int(0, $w), random_int(0, $h), $c);
        }
        $len = strlen($code);
        $x   = (int) (($w - $len * 18) / 2);
        for ($i = 0; $i < $len; $i++) {
            $fc = imagecolorallocate($img, random_int(0, 80), random_int(0, 80), random_int(0, 80));
            imagestring($img, 5, $x + $i * 22, (int) (($h - 20) / 2), $code[$i], $fc);
        }
        ob_start();
        imagepng($img);
        $out = ob_get_clean();
        imagedestroy($img);
        return $out ?: $this->drawCaptchaImageFallback($code, $w, $h);
    }

    /** GD 绘图失败时返回最小占位图 */
    private function drawCaptchaImageFallback(string $code, int $w, int $h): string
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==', true);
        return $png ?: '';
    }
}
