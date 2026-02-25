<?php
declare (strict_types = 1);

namespace app\controller\Api\Mini;

use app\BaseController;
use app\model\Banner;
use think\response\Json;

/**
 * 小程序端 - 轮播图接口（无需登录）
 */
class BannerController extends BaseController
{
    /**
     * 轮播图列表
     * GET /api/mini/banners?type=banner
     *
     * 参数：
     * - type: 可选，banner=普通轮播图，ranking=排行榜轮播图，不传返回全部
     * 仅返回 status=1 且在有效期内（start_time/end_time 为空或当前时间在区间内）的数据，按 sort_order 升序
     */
    public function list(): Json
    {
        $type = $this->request->get('type');
        $query = Banner::where('status', 1);

        if ($type !== null && $type !== '') {
            $query->where('type', $type);
        }

        // 有效期内：start_time 为空或 <= 当前时间，end_time 为空或 >= 当前时间
        $now = date('Y-m-d H:i:s');
        $query->whereRaw('(start_time IS NULL OR start_time <= ?)', [$now])
              ->whereRaw('(end_time IS NULL OR end_time >= ?)', [$now]);

        $list = $query->order('sort_order', 'asc')->order('id', 'asc')->select();
        $items = $list->map(function ($row) {
            $arr = $row->toArray();
            $arr['image_url'] = $arr['image'] ?? '';
            $arr['sort']      = (int) ($arr['sort_order'] ?? 0);
            unset($arr['created_at'], $arr['updated_at']);
            return $arr;
        })->toArray();

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => $items,
        ]);
    }
}
