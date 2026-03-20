<?php
declare (strict_types = 1);

namespace app\controller\Api\Mini;

use app\model\MiniUser;
use think\response\Json;

/**
 * 小程序端 - 用户信息（需登录）
 *
 * 流程建议：先 POST /api/mini/login 只传 code 拿到 token，再调起头像昵称授权，
 * 授权后调用 POST /api/mini/profile 更新资料。
 */
class UserController extends MiniBaseController
{
    /**
     * 更新当前用户资料（头像、昵称等，来自授权后前端传入）
     * POST /api/mini/profile  Header: Authorization: Bearer {token}
     * body: { nickname?, avatar?, gender?, country?, province?, city? }
     */
    public function profile(): Json
    {
        [$user, $err] = $this->getCurrentUserOrFail();
        if ($err !== null) {
            return $err;
        }

        $nickname = $this->request->param('nickname');
        $avatar   = $this->request->param('avatar');
        $gender   = $this->request->param('gender');
        $country  = $this->request->param('country');
        $province = $this->request->param('province');
        $city     = $this->request->param('city');

        $updates = [];
        if ($nickname !== null && $nickname !== '') {
            $updates['nickname'] = $nickname;
        }
        if ($avatar !== null && $avatar !== '') {
            // 微信临时地址（如 http://tmp/xxx.jpeg）会失效，需下载到服务器并存本地展示地址
            if (preg_match('#^https?://#i', $avatar)) {
                $localUrl = $this->downloadImageToStorage($avatar, 'avatar');
                $updates['avatar'] = $localUrl !== null ? $localUrl : $avatar;
            } else {
                $updates['avatar'] = $avatar;
            }
        }
        if ($gender !== null && $gender !== '') {
            $updates['gender'] = (int) $gender;
        }
        if ($country !== null) {
            $updates['country'] = $country === '' ? null : $country;
        }
        if ($province !== null) {
            $updates['province'] = $province === '' ? null : $province;
        }
        if ($city !== null) {
            $updates['city'] = $city === '' ? null : $city;
        }

        if (!empty($updates)) {
            $user->save($updates);
            $user = MiniUser::find($user->id);
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => $user->toArray(),
        ]);
    }

    /**
     * 当前登录用户信息
     * GET /api/mini/me  Header: Authorization: Bearer {token}
     */
    public function me(): Json
    {
        return $this->getCurrentUserInfo();
    }

    /**
     * 获取当前用户信息（与 me 相同，路径更语义化）
     * GET /api/mini/user/info  Header: Authorization: Bearer {token}
     * 返回: { code: 0, msg: 'success', data: { id, openid, nickname, avatar, gender, ... } }
     */
    public function info(): Json
    {
        return $this->getCurrentUserInfo();
    }

    /**
     * 当前用户会员余额
     * GET /api/mini/user/balance
     * 返回：{ balance, is_vip, nickname?, avatar? }
     */
    public function balance(): Json
    {
        [$user, $err] = $this->getCurrentUserOrFail();
        if ($err !== null) {
            return $err;
        }

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'is_vip' => (int) (($user->is_vip ?? 0) === 1),
                'balance' => (string) number_format((float) ($user->balance ?? 0), 2, '.', ''),
                'balance_number' => (float) ($user->balance ?? 0),
                'nickname' => (string) ($user->nickname ?? ''),
                'avatar' => (string) ($user->avatar ?? ''),
            ],
        ]);
    }

    /**
     * 内部：根据 token 获取当前用户信息（复用公共登录校验）
     */
    private function getCurrentUserInfo(): Json
    {
        [$user, $err] = $this->getCurrentUserOrFail();
        if ($err !== null) {
            return $err;
        }
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => $user->toArray(),
        ]);
    }

    /**
     * 从 URL（如微信临时地址）下载图片并保存到本地存储，返回可访问路径
     * @param string $url 图片 URL，如 http://tmp/xxx.jpeg
     * @param string $subDir 存储子目录，如 avatar
     * @return string|null 成功返回如 /storage/avatar/202502/xxx.jpeg，失败返回 null
     */
    private function downloadImageToStorage(string $url, string $subDir = 'avatar'): ?string
    {
        $allowedExt = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $maxSize   = 2 * 1024 * 1024; // 2MB

        $context = stream_context_create([
            'http' => [
                'timeout'    => 10,
                'user_agent' => 'Mozilla/5.0 (compatible; FishingAdmin/1.0)',
            ],
        ]);
        $content = @file_get_contents($url, false, $context);
        if ($content === false || strlen($content) > $maxSize) {
            return null;
        }

        $ext = 'jpg';
        if (preg_match('#\.(jpe?g|png|gif|webp)(\?|$)#i', $url, $m)) {
            $ext = strtolower($m[1]);
            if ($ext === 'jpeg') {
                $ext = 'jpg';
            }
        }
        if (!in_array($ext, $allowedExt, true)) {
            $ext = 'jpg';
        }

        $root = config('filesystem.disks.public.root');
        if (!is_dir($root)) {
            @mkdir($root, 0755, true);
        }
        $dir = $subDir . '/' . date('Ym');
        $fullDir = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $dir);
        if (!is_dir($fullDir)) {
            @mkdir($fullDir, 0755, true);
        }
        $filename = md5($content . uniqid('', true)) . '.' . $ext;
        $path     = $dir . '/' . $filename;
        $fullPath = $root . '/' . str_replace('/', DIRECTORY_SEPARATOR, $path);
        if (file_put_contents($fullPath, $content) === false) {
            return null;
        }
        return '/storage/' . str_replace('\\', '/', $path);
    }
}
