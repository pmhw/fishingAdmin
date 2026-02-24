<?php
declare (strict_types = 1);

namespace app\controller\Api;

use app\BaseController;
use think\response\Json;

/**
 * 示例 API：通用接口
 */
class Index extends BaseController
{
    /**
     * API 心跳/健康检查
     * GET /api/index/ping
     */
    public function ping(): Json
    {
        return json([
            'code' => 0,
            'msg'  => 'pong',
            'data' => [
                'time'    => date('Y-m-d H:i:s'),
                'version' => \think\facade\App::version(),
            ],
        ]);
    }

    /**
     * API 版本信息
     * GET /api/index/info
     */
    public function info(): Json
    {
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'name'    => 'fishingAdmin API',
                'version' => \think\facade\App::version(),
                'php'     => PHP_VERSION,
            ],
        ]);
    }
}
