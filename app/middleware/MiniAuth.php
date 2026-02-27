<?php
declare (strict_types = 1);

namespace app\middleware;

use app\controller\Api\Mini\AuthController;
use Closure;
use think\Request;
use think\Response;

/**
 * 小程序接口鉴权：校验 Authorization Bearer token，并写入 request->miniOpenid
 */
class MiniAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Authorization', '');
        if (str_starts_with($token, 'Bearer ')) {
            $token = trim(substr($token, 7));
        }
        $openid = $token ? AuthController::getOpenidByToken($token) : null;
        if (!$openid) {
            return json(['code' => 401, 'msg' => '未登录或登录已过期', 'data' => null]);
        }
        $request->miniOpenid = $openid;
        return $next($request);
    }
}
