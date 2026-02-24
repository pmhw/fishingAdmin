<?php
declare (strict_types = 1);

namespace app\middleware;

use app\controller\Api\Admin\Auth;
use Closure;
use think\Request;
use think\Response;

/**
 * 后台接口鉴权：校验 Authorization Bearer token，并写入 request->adminId
 */
class AdminAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->header('Authorization', '');
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }
        $adminId = $token ? Auth::getAdminIdByToken($token) : null;
        if (!$adminId) {
            return json(['code' => 401, 'msg' => '未登录或登录已过期', 'data' => null]);
        }
        $request->adminId = $adminId;
        return $next($request);
    }
}
