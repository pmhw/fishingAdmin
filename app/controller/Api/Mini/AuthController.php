<?php
declare (strict_types = 1);

namespace app\controller\Api\Mini;

use app\BaseController;
use app\model\MiniUser;
use think\facade\Cache;
use think\response\Json;

/**
 * 小程序端 - 登录
 *
 * 使用方式（小程序前端）：
 * 1. 调用 wx.login 拿到 code
 * 2. 将 code POST 到 /api/mini/login
 * 3. 后端向微信 jscode2session 换取 openid，签发自己的 token 返回
 */
class AuthController extends BaseController
{
    private const TOKEN_PREFIX = 'mini_token:'; // token 前缀

    /**
     * 登录
     * POST /api/mini/login
     * body: {
     *   code: string,
     *   nickname?: string,
     *   avatar?: string,
     *   gender?: int,   // 0 未知 1 男 2 女
     *   country?: string,
     *   province?: string,
     *   city?: string
     * }
     */
    public function login(): Json
    {
        $code = (string) $this->request->post('code', '');
        if ($code === '') {
            return json(['code' => 400, 'msg' => '缺少 code', 'data' => null]);
        }

        $appid  = (string) config('wechat_mini.appid', '');
        $secret = (string) config('wechat_mini.secret', '');
        if ($appid === '' || $secret === '') {
            return json(['code' => 500, 'msg' => '小程序未配置 appid/secret', 'data' => null]);
        }

        $url = sprintf(
            'https://api.weixin.qq.com/sns/jscode2session?appid=%s&secret=%s&js_code=%s&grant_type=authorization_code',
            urlencode($appid),
            urlencode($secret),
            urlencode($code)
        );

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                ],
            ]);
            $resp = @file_get_contents($url, false, $context);
            if ($resp === false) {
                return json(['code' => 500, 'msg' => '请求微信登录接口失败', 'data' => null]);
            }
            $data = json_decode($resp, true);
        } catch (\Throwable $e) {
            return json(['code' => 500, 'msg' => '微信登录异常：' . $e->getMessage(), 'data' => null]);
        }

        if (!is_array($data)) {
            return json(['code' => 500, 'msg' => '微信返回数据异常', 'data' => null]);
        }

        if (!empty($data['errcode']) && (int) $data['errcode'] !== 0) {
            return json([
                'code' => 400,
                'msg'  => '微信登录失败：' . ($data['errmsg'] ?? '未知错误'),
                'data' => null,
            ]);
        }

        $openid  = $data['openid'] ?? '';
        $unionid = $data['unionid'] ?? null;
        if ($openid === '') {
            return json(['code' => 500, 'msg' => '微信登录失败：缺少 openid', 'data' => null]);
        }

        // 处理本地用户：已存在则不覆盖头像昵称，仅更新最后登录；不存在时用前端授权信息创建
        $now = date('Y-m-d H:i:s');
        $ip  = $this->request->ip();

        $nickname = (string) $this->request->post('nickname', '');
        $avatar   = (string) $this->request->post('avatar', '');
        $gender   = (int) $this->request->post('gender', 0);
        $country  = (string) $this->request->post('country', '');
        $province = (string) $this->request->post('province', '');
        $city     = (string) $this->request->post('city', '');

        $user = MiniUser::where('openid', $openid)->find();
        if ($user) {
            if ((int) $user->status !== 1) {
                return json(['code' => 403, 'msg' => '账号已禁用', 'data' => null]);
            }
            $user->save([
                'unionid'       => $unionid ?: $user->unionid,
                'last_login_at' => $now,
                'last_login_ip' => $ip,
            ]);
        } else {
            $user = MiniUser::create([
                'openid'        => $openid,
                'unionid'       => $unionid,
                'nickname'      => $nickname !== '' ? $nickname : null,
                'avatar'        => $avatar !== '' ? $avatar : null,
                'gender'        => $gender,
                'country'       => $country !== '' ? $country : null,
                'province'      => $province !== '' ? $province : null,
                'city'          => $city !== '' ? $city : null,
                'status'        => 1,
                'last_login_at' => $now,
                'last_login_ip' => $ip,
            ]);
        }

        $tokenTtl = (int) config('wechat_mini.token_ttl', 86400 * 30);
        $token    = self::TOKEN_PREFIX . bin2hex(random_bytes(32));
        Cache::set($token, $openid, $tokenTtl > 0 ? $tokenTtl : 86400 * 30);

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'token'   => $token,
                'openid'  => $openid,
                'unionid' => $unionid,
                'user'    => $user ? $user->toArray() : null,
                // session_key 如需前端加解密可一并返回
                // 'session_key' => $data['session_key'] ?? null,
            ],
        ]);
    }

    /**
     * 根据 token 获取 openid（后续小程序受保护接口可使用）
     */
    public static function getOpenidByToken(string $token): ?string
    {
        if (!str_starts_with($token, self::TOKEN_PREFIX)) {
            return null;
        }
        $openid = Cache::get($token);
        return $openid ? (string) $openid : null;
    }
}

