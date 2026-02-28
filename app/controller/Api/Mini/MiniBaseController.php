<?php
declare (strict_types = 1);

namespace app\controller\Api\Mini;

use app\model\MiniUser;
use think\response\Json;

/**
 * 小程序端控制器基类：提供「需登录」接口的公共方法
 */
abstract class MiniBaseController extends \app\BaseController
{
    /**
     * 获取当前登录用户，失败则返回错误响应
     * 需配合 MiniAuth 中间件使用（会注入 miniOpenid）
     * @return array{0: MiniUser|null, 1: Json|null} 成功为 [$user, null]，失败为 [null, $jsonResponse]
     */
    protected function getCurrentUserOrFail(): array
    {
        $openid = $this->request->miniOpenid ?? '';
        if ($openid === '') {
            return [null, json(['code' => 401, 'msg' => '未登录', 'data' => null])];
        }

        $user = MiniUser::where('openid', $openid)->find();
        if (!$user) {
            return [null, json(['code' => 404, 'msg' => '用户不存在', 'data' => null])];
        }
        if ((int) $user->status === 0) {
            return [null, json(['code' => 403, 'msg' => '账号已禁用', 'data' => null])];
        }

        return [$user, null];
    }
}
