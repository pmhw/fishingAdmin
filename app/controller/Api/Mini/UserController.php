<?php
declare (strict_types = 1);

namespace app\controller\Api\Mini;

use app\BaseController;
use app\model\MiniUser;
use think\response\Json;

/**
 * 小程序端 - 用户信息（需登录）
 *
 * 流程建议：先 POST /api/mini/login 只传 code 拿到 token，再调起头像昵称授权，
 * 授权后调用 POST /api/mini/profile 更新资料。
 */
class UserController extends BaseController
{
    /**
     * 更新当前用户资料（头像、昵称等，来自授权后前端传入）
     * POST /api/mini/profile  Header: Authorization: Bearer {token}
     * body: { nickname?, avatar?, gender?, country?, province?, city? }
     */
    public function profile(): Json
    {
        $openid = $this->request->miniOpenid ?? '';
        if ($openid === '') {
            return json(['code' => 401, 'msg' => '未登录', 'data' => null]);
        }

        $user = MiniUser::where('openid', $openid)->find();
        if (!$user) {
            return json(['code' => 404, 'msg' => '用户不存在', 'data' => null]);
        }
        if ((int) $user->status === 0) {
            return json(['code' => 403, 'msg' => '账号已禁用', 'data' => null]);
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
        $openid = $this->request->miniOpenid ?? '';
        if ($openid === '') {
            return json(['code' => 401, 'msg' => '未登录', 'data' => null]);
        }

        $user = MiniUser::where('openid', $openid)->find();
        if (!$user) {
            return json(['code' => 404, 'msg' => '用户不存在', 'data' => null]);
        }
        if ((int) $user->status === 0) {
            return json(['code' => 403, 'msg' => '账号已禁用', 'data' => null]);
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => $user->toArray(),
        ]);
    }
}
