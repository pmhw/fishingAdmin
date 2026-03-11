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
        $userLat = (float) $this->request->get('latitude', 0);
        $userLng = (float) $this->request->get('longitude', 0);

        $query = FishingVenue::where('status', 1);

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

        // 如果有经纬度，则在内存中计算距离并按距离排序后再手动分页；
        // 若没有经纬度，则按 sort_order/id 排序使用数据库分页。
        $items = [];
        $total = 0;

        if ($userLat && $userLng) {
            $rows = $query->select();
            $data = [];
            foreach ($rows as $row) {
                $arr = $row->toArray();
                $lat = isset($arr['latitude']) ? (float) $arr['latitude'] : 0.0;
                $lng = isset($arr['longitude']) ? (float) $arr['longitude'] : 0.0;
                $distance = null;
                if ($lat && $lng) {
                    $distance = $this->calcDistanceMeters($userLat, $userLng, $lat, $lng);
                }
                $arr['_distance_m'] = $distance;
                $data[] = $arr;
            }
            // 按距离从近到远排序，空距离排在最后
            usort($data, function ($a, $b) {
                $da = $a['_distance_m'];
                $db = $b['_distance_m'];
                if ($da === null && $db === null) {
                    return 0;
                }
                if ($da === null) {
                    return 1;
                }
                if ($db === null) {
                    return -1;
                }
                return $da <=> $db;
            });
            $total = count($data);
            $offset = ($page - 1) * $limit;
            $slice = array_slice($data, $offset, $limit);

            $items = array_map(function ($arr) {
                $distanceM = $arr['_distance_m'];
                unset($arr['_distance_m']);
                $distanceKm = $distanceM !== null ? round($distanceM / 1000, 2) : null;
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
                    'distance_m'   => $distanceM,
                    'distance_km'  => $distanceKm,
                ];
            }, $slice);
        } else {
            $paginator = $query
                ->order('sort_order', 'asc')
                ->order('id', 'desc')
                ->paginate(['list_rows' => $limit, 'page' => $page]);

            $items = array_map(function ($row) {
                $arr = $row->toArray();
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
                    'distance_m'   => null,
                    'distance_km'  => null,
                ];
            }, $paginator->items());
            $total = $paginator->total();
        }

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'list'  => $items,
                'total' => $total,
            ],
        ]);
    }

    /**
     * 计算两点间距离（单位：米）——简化版 Haversine 公式
     */
    private function calcDistanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000; // 地球半径（米）
        $radLat1 = deg2rad($lat1);
        $radLat2 = deg2rad($lat2);
        $radLng1 = deg2rad($lng1);
        $radLng2 = deg2rad($lng2);

        $a = $radLat1 - $radLat2;
        $b = $radLng1 - $radLng2;
        $s = 2 * asin(sqrt(pow(sin($a / 2), 2) + cos($radLat1) * cos($radLat2) * pow(sin($b / 2), 2)));
        return $earthRadius * $s;
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

