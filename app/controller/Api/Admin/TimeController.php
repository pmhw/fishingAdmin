<?php
declare(strict_types = 1);

namespace app\controller\Api\Admin;

use app\BaseController;
use think\response\Json;

/**
 * 后台时间服务（轻量）：用于前端校准本地计时，避免重复查询业务详情。
 */
class TimeController extends BaseController
{
    /**
     * GET /api/admin/time/now
     */
    public function now(): Json
    {
        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'server_now' => date('Y-m-d H:i:s'),
            ],
        ]);
    }
}

