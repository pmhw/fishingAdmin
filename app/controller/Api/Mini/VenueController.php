<?php
declare (strict_types = 1);

namespace app\controller\Api\Mini;

use app\model\FishingVenue;
use app\model\FishingPond;
use app\model\PondFeeRule;
use app\model\PondSeat;
use app\model\PondFeedLog;
use think\response\Json;

/**
 * 小程序端 - 门店/钓场展示
 */
class VenueController extends \app\BaseController
{
    /**
     * 列表：GET /api/mini/venues
     * 可选参数（支持 query 或 body，便于小程序传参）：
     * - page, limit 分页
     * - keyword     按名称/地址模糊搜索
     * - city        按城市精确匹配（如 广州市）
     * - latitude, longitude 用户经纬度；传入且钓场有坐标时会返回 distance_m/distance_km 并按距离排序
     */
    public function list(): Json
    {
        $page    = (int) $this->request->param('page', 1);
        $limit   = min(max((int) $this->request->param('limit', 10), 1), 50);
        $keyword = trim((string) $this->request->param('keyword', ''));
        $city    = trim((string) $this->request->param('city', ''));
        // 兼容 GET 时经纬度在 query 或 body 里（小程序 wx.request 的 data 可能放在 body，ThinkPHP GET 时 param 不含 body）
        $userLat = (float) ($this->request->param('latitude') ?? $this->request->get('latitude') ?? $this->request->post('latitude') ?? $this->request->put('latitude') ?? 0);
        $userLng = (float) ($this->request->param('longitude') ?? $this->request->get('longitude') ?? $this->request->post('longitude') ?? $this->request->put('longitude') ?? 0);

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
                    'contact_phone'=> $arr['contact_phone'] ?? '',
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
                    'contact_phone'=> $arr['contact_phone'] ?? '',
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
     * 原始详情，返回钓场表全部字段（兼容老版本）
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

    /**
     * 钓场详情（新版结构）：GET /api/mini/venues/:id/spot
     * 返回小程序展示用的 spot 结构
     *
     * 可选参数（query/body）：
     * - latitude, longitude 用户经纬度，用于计算距离展示文案
     */
    public function spot(int $id): Json
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

        $venue = $row->toArray();

        // 顶部轮播图
        $images = [];
        if (is_string($venue['images'] ?? null) && $venue['images'] !== '') {
            $decoded = json_decode($venue['images'], true);
            if (is_array($decoded)) {
                $images = array_values(array_filter($decoded, static function ($v) {
                    return (string) $v !== '';
                }));
            }
        }
        if (empty($images) && !empty($venue['cover_image'] ?? '')) {
            $images = [(string) $venue['cover_image']];
        }

        // 设施标签
        $facilities = [];
        if (!empty($venue['facilities'] ?? '')) {
            $decoded = json_decode((string) $venue['facilities'], true);
            if (is_array($decoded)) {
                $facilities = array_values(array_filter(array_map('strval', $decoded), static function ($v) {
                    return trim($v) !== '';
                }));
            } else {
                $parts = preg_split('/[，,]/u', (string) $venue['facilities']);
                $facilities = array_values(array_filter(array_map('trim', $parts), static function ($v) {
                    return $v !== '';
                }));
            }
        }

        // 用户经纬度（兼容 query/body）
        $userLat = (float) ($this->request->param('latitude') ?? $this->request->get('latitude') ?? $this->request->post('latitude') ?? $this->request->put('latitude') ?? 0);
        $userLng = (float) ($this->request->param('longitude') ?? $this->request->get('longitude') ?? $this->request->post('longitude') ?? $this->request->put('longitude') ?? 0);
        $venueLat = isset($venue['latitude']) ? (float) $venue['latitude'] : 0.0;
        $venueLng = isset($venue['longitude']) ? (float) $venue['longitude'] : 0.0;

        $distanceText = null;
        if ($userLat && $userLng && $venueLat && $venueLng) {
            $distanceM = $this->calcDistanceMeters($userLat, $userLng, $venueLat, $venueLng);
            if ($distanceM < 1000) {
                $distanceText = (int) round($distanceM) . 'm';
            } else {
                $km = $distanceM / 1000;
                $distanceText = round($km, 1) . 'km';
            }
        }

        // 关联池塘列表
        $pondRows = FishingPond::where('venue_id', $id)
            ->order('sort_order', 'asc')
            ->order('id', 'asc')
            ->select();

        $ponds = [];
        $feeds = [];
        $totalSeat = 0;
        $totalSeatAll = 0;

        if (!$pondRows->isEmpty()) {
            $pondIds = array_map(static function ($p) {
                return (int) $p['id'];
            }, $pondRows->toArray());

            // 钓场放鱼动态：汇总该钓场下所有池塘的放鱼记录（按时间倒序）
            if (!empty($pondIds)) {
                $pondNameMap = FishingPond::whereIn('id', $pondIds)->column('name', 'id');
                $feedRows = PondFeedLog::whereIn('pond_id', $pondIds)
                    ->order('feed_time', 'desc')
                    ->order('id', 'desc')
                    ->limit(10)
                    ->select();

                $feeds = [];
                foreach ($feedRows as $fr) {
                    $arr = $fr->toArray();
                    if (is_string($arr['images'] ?? null) && $arr['images'] !== '') {
                        $decoded = json_decode($arr['images'], true);
                        $arr['images'] = is_array($decoded) ? $decoded : [];
                    } else {
                        $arr['images'] = [];
                    }
                    $pondIdVal = (int) ($arr['pond_id'] ?? 0);
                    $feeds[] = [
                        'id' => (int) ($arr['id'] ?? 0),
                        'pond_id' => $pondIdVal,
                        'pond_name' => $pondNameMap[$pondIdVal] ?? '',
                        'title' => (string) ($arr['title'] ?? ''),
                        'content' => (string) ($arr['content'] ?? ''),
                        'images' => $arr['images'],
                        'feed_time' => $arr['feed_time'] ?? null,
                        'created_at' => $arr['created_at'] ?? null,
                    ];
                }
            }

            // 钓位统计：每个池塘总钓位数 + 使用中数量
            $seatStatsByPond = [];
            if (!empty($pondIds)) {
                $seatStats = PondSeat::whereIn('pond_id', $pondIds)
                    ->fieldRaw("pond_id, COUNT(*) AS total_seat, SUM(CASE WHEN status = 'in_use' THEN 1 ELSE 0 END) AS used_seat")
                    ->group('pond_id')
                    ->select();
                foreach ($seatStats as $stat) {
                    $seatStatsByPond[(int) $stat->pond_id] = [
                        'total' => (int) ($stat->total_seat ?? 0),
                        'used'  => (int) ($stat->used_seat ?? 0),
                    ];
                }
            }

            // 预加载收费规则，避免 N+1
            $feeRulesByPond = [];
            if (!empty($pondIds)) {
                $feeRuleRows = PondFeeRule::whereIn('pond_id', $pondIds)
                    ->order('sort_order', 'asc')
                    ->order('id', 'asc')
                    ->select();
                foreach ($feeRuleRows as $fr) {
                    $pondId = (int) $fr->pond_id;
                    if (!isset($feeRulesByPond[$pondId])) {
                        $feeRulesByPond[$pondId] = [];
                    }
                    $amount = (float) ($fr->amount ?? 0);
                    $priceText = ($amount > 0 ? rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.') : '0') . '元';
                    $feeRulesByPond[$pondId][] = [
                        'name'     => (string) $fr->name,
                        'duration' => (string) ($fr->duration ?? ''),
                        'price'    => $priceText,
                    ];
                }
            }

            foreach ($pondRows as $pond) {
                $pondArr = $pond->toArray();
                $pondId = (int) $pondArr['id'];

                $seatTotal = $seatStatsByPond[$pondId]['total'] ?? (int) ($pondArr['seat_count'] ?? 0);
                $seatUsed  = $seatStatsByPond[$pondId]['used'] ?? 0;

                $totalSeatAll += $seatTotal;
                $totalSeat += $seatUsed;

                // 鱼种标签
                $fishTypes = [];
                if (!empty($pondArr['fish_species'] ?? '')) {
                    $parts = preg_split('/[，,]/u', (string) $pondArr['fish_species']);
                    $fishTypes = array_values(array_filter(array_map('trim', $parts), static function ($v) {
                        return $v !== '';
                    }));
                }

                // 池塘类型、状态文案
                $typeLabel = match ($pondArr['pond_type'] ?? '') {
                    FishingPond::TYPE_BLACK_PIT => '黑坑',
                    FishingPond::TYPE_JIN_TANG  => '斤塘',
                    FishingPond::TYPE_PRACTICE  => '练杆塘',
                    default                     => '',
                };
                $statusLabel = match ($pondArr['status'] ?? '') {
                    FishingPond::STATUS_OPEN   => '开放中',
                    FishingPond::STATUS_CLOSED => '关闭',
                    default                    => '',
                };

                $ponds[] = [
                    'id'        => $pondId,
                    'name'      => (string) $pondArr['name'],
                    'status'    => $statusLabel,
                    'area'      => $pondArr['area_mu'] !== null ? ((string) $pondArr['area_mu'] . '亩') : '',
                    'depth'     => (string) ($pondArr['water_depth'] ?? ''),
                    'type'      => $typeLabel,
                    'rodLimit'  => (string) ($pondArr['rod_rule'] ?? ''),
                    'baitRule'  => (string) ($pondArr['bait_rule'] ?? ''),
                    'seat'      => $seatUsed,
                    'seatTotal' => $seatTotal,
                    'fishTypes' => $fishTypes,
                    'feeRules'  => $feeRulesByPond[$pondId] ?? [],
                ];
            }
        }

        $venueStatusLabel = match ((int) ($venue['status'] ?? 0)) {
            1       => '营业中',
            default => '休息中',
        };

        $spot = [
            'id'          => (int) $venue['id'],
            'name'        => (string) $venue['name'],
            'images'      => $images,
            'status'      => $venueStatusLabel,
            'rating'      => null,
            'distance'    => $distanceText,
            'seat'        => $totalSeat,
            'seatTotal'   => $totalSeatAll,
            'description' => (string) ($venue['description'] ?? ''),
            'openTime'    => (string) ($venue['opening_hours'] ?? ''),
            'phone'       => (string) ($venue['contact_phone'] ?? ''),
            'latitude'    => $venueLat ?: null,
            'longitude'   => $venueLng ?: null,
            'address'     => (string) ($venue['address'] ?? ''),
            'facilities'  => $facilities,
            'feeds'       => $feeds,
            'ponds'       => $ponds,
        ];

        return json(['code' => 0, 'msg' => 'success', 'data' => ['spot' => $spot]]);
    }

    /**
     * 钓场放鱼动态（汇总该钓场下所有池塘）
     * GET /api/mini/venues/:id/feeds
     *
     * 可选参数：
     * - page, limit
     *
     * 返回：
     * data.list / data.total
     */
    public function feeds(int $id): Json
    {
        $page = max((int) $this->request->get('page', 1), 1);
        $limit = min(max((int) $this->request->get('limit', 10), 1), 50);

        $venue = FishingVenue::where('id', $id)->where('status', 1)->find();
        if (!$venue) {
            return json(['code' => 404, 'msg' => '钓场不存在或未上架', 'data' => null]);
        }

        // 该钓场下所有池塘
        $pondRows = FishingPond::where('venue_id', $id)->select();
        if ($pondRows->isEmpty()) {
            return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => [], 'total' => 0]]);
        }

        $pondIds = array_values(array_map(static fn ($p) => (int) ($p['id'] ?? 0), $pondRows->toArray()));
        $pondIds = array_values(array_filter($pondIds, static fn ($v) => $v > 0));
        if (empty($pondIds)) {
            return json(['code' => 0, 'msg' => 'success', 'data' => ['list' => [], 'total' => 0]]);
        }

        $pondNameMap = FishingPond::whereIn('id', $pondIds)->column('name', 'id');

        $query = PondFeedLog::whereIn('pond_id', $pondIds)
            ->order('feed_time', 'desc')
            ->order('id', 'desc');

        $paginator = $query->paginate(['list_rows' => $limit, 'page' => $page]);
        $rows = $paginator->items();

        $list = [];
        foreach ($rows as $r) {
            /** @var \app\model\PondFeedLog $r */
            $arr = $r->toArray();
            if (is_string($arr['images'] ?? null) && $arr['images'] !== '') {
                $decoded = json_decode($arr['images'], true);
                $arr['images'] = is_array($decoded) ? $decoded : [];
            } else {
                $arr['images'] = [];
            }

            $pondIdVal = (int) ($arr['pond_id'] ?? 0);
            $list[] = [
                'id' => (int) ($arr['id'] ?? 0),
                'pond_id' => $pondIdVal,
                'pond_name' => (string) ($pondNameMap[$pondIdVal] ?? ''),
                'title' => (string) ($arr['title'] ?? ''),
                'content' => (string) ($arr['content'] ?? ''),
                'images' => $arr['images'],
                'feed_time' => $arr['feed_time'] ?? null,
                'created_at' => $arr['created_at'] ?? null,
            ];
        }

        return json([
            'code' => 0,
            'msg' => 'success',
            'data' => [
                'list' => $list,
                'total' => (int) $paginator->total(),
            ],
        ]);
    }
}

