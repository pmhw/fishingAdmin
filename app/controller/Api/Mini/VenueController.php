<?php
declare (strict_types = 1);

namespace app\controller\Api\Mini;

use app\model\FishingVenue;
use think\response\Json;

/**
 * 小程序端 - 门店/钓场展示
 */
class VenueController extends \app\BaseController
{
    /**
     * 列表：GET /api/mini/venues
     * 可选参数：
     * - page, limit 分页
     * - keyword     按名称/地址模糊搜索
     * - city        按城市精确匹配（如 广州市）
     */
    public function list(): Json
    {
        $page   = (int) $this->request->get('page', 1);
        $limit  = min(max((int) $this->request->get('limit', 10), 1), 50);
        $keyword = trim((string) $this->request->get('keyword', ''));
        $city    = trim((string) $this->request->get('city', ''));

        $query = FishingVenue::where('status', 1)
            ->order('sort_order', 'asc')
            ->order('id', 'desc');

        if ($keyword !== '') {
            $like = '%' . $keyword . '%';
            $query->where(function ($q) use ($like) {
                $q->whereLike('name', $like)
                  ->whereOrLike('intro', $like)
                  ->whereOrLike('address', $like);
            });
        }
        if ($city !== '') {
            $query->where('city', $city);
        }

        $paginator = $query->paginate(['list_rows' => $limit, 'page' => $page]);

        $items = array_map(function ($row) {
            $arr = $row->toArray();
            // 小程序列表只返回常用字段，避免数据过大
            return [
                'id'           => $arr['id'],
                'name'         => $arr['name'] ?? '',
                'intro'        => $arr['intro'] ?? '',
                'cover_image'  => $arr['cover_image'] ?? '',
                'province'     => $arr['province'] ?? '',
                'city'         => $arr['city'] ?? '',
                'district'     => $arr['district'] ?? '',
                'address'      => $arr['address'] ?? '',
                'longitude'    => $arr['longitude'] ?? null,
                'latitude'     => $arr['latitude'] ?? null,
                'opening_hours'=> $arr['opening_hours'] ?? '',
                'price_type'   => $arr['price_type'] ?? '',
                'price_info'   => $arr['price_info'] ?? '',
                'price_min'    => $arr['price_min'] ?? null,
                'price_max'    => $arr['price_max'] ?? null,
                'facilities'   => $arr['facilities'] ?? '',
                'fish_species' => $arr['fish_species'] ?? '',
                'view_count'   => $arr['view_count'] ?? 0,
            ];
        }, $paginator->items());

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'list'  => $items,
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * 详情：GET /api/mini/venues/:id
     * 仅允许访问已上架(status=1)的门店
     */
    public function detail(int $id): Json
    {
        $row = FishingVenue::where('id', $id)
            ->where('status', 1)
            ->find();

        if (!$row) {
            return json(['code' => 404, 'msg' => '门店不存在或未上架', 'data' => null]);
        }

        // 浏览量 +1（无需严格一致性）
        try {
            $row->where('id', $id)->inc('view_count')->update();
        } catch (\Throwable $e) {
            // 忽略统计失败
        }

        $data = $row->toArray();
        if (is_string($data['images'] ?? null)) {
            $decoded = json_decode($data['images'], true);
            $data['images'] = is_array($decoded) ? $decoded : [];
        }

        return json(['code' => 0, 'msg' => 'success', 'data' => $data]);
    }
}

