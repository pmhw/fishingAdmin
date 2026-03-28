<?php
declare (strict_types = 1);

namespace app\controller\Api\Mini;

use app\BaseController;
use app\model\Banner;
use app\model\FishingVenue;
use think\response\Json;

/**
 * 小程序端 - 轮播图接口（无需登录）
 */
class BannerController extends BaseController
{
    /** 同城推荐钓场条数上限 */
    private const RECOMMENDED_VENUE_LIMIT = 10;

    /**
     * 轮播图列表
     * GET /api/mini/banners?type=banner
     *
     * 参数：
     * - type: 可选，banner=普通轮播图，ranking=排行榜轮播图，不传返回全部
     * - city: 可选，城市名（与 fishing_venue.city 一致，如「广州市」）；传则返回同城优质钓场推荐
     * - province: 可选，未传 city 时可按省筛选推荐钓场（如「广东省」）
     * - recommended_limit: 可选，推荐条数，默认 10，最大 20
     * 仅返回 status=1 且在有效期内（start_time/end_time 为空或当前时间在区间内）的数据，按 sort_order 升序
     *
     * 响应：`data` 仍为轮播数组（兼容旧版）；顶层另含 `recommended_venues`、`recommended_title` 等。
     * 推荐钓场项含 `facilities`（原文）与 `facilities_list`（解析后的设施标签数组，便于渲染）。
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

        $city = trim((string) $this->request->get('city', ''));
        $province = trim((string) $this->request->get('province', ''));
        $recLimit = (int) $this->request->get('recommended_limit', self::RECOMMENDED_VENUE_LIMIT);
        $recLimit = min(20, max(1, $recLimit));

        [$recommendedVenues, $scopeLabel, $scopeCity, $scopeProvince] = $this->recommendedVenues(
            $city,
            $province,
            $recLimit
        );

        $title = $scopeCity !== null && $scopeCity !== ''
            ? $scopeCity . '优质钓场推荐'
            : ($scopeProvince !== null && $scopeProvince !== ''
                ? $scopeProvince . '优质钓场推荐'
                : '优质钓场推荐');

        return json([
            'code'                 => 0,
            'msg'                  => 'success',
            'data'                 => $items,
            'recommended_venues'   => $recommendedVenues,
            'recommended_title'    => $title,
            'recommended_city'     => $scopeCity,
            'recommended_province' => $scopeProvince,
            'recommended_scope'    => $scopeLabel,
        ]);
    }

    /**
     * @return array{0: array<int, array<string, mixed>>, 1: string, 2: string|null, 3: string|null}
     */
    private function recommendedVenues(string $city, string $province, int $limit): array
    {
        $q = FishingVenue::where('status', 1);

        $cityCandidates = $this->cityNameCandidates($city);
        if ($cityCandidates !== []) {
            $q->whereIn('city', $cityCandidates);
            $scopeLabel = 'city';
            $scopeCity = trim($city);
            $scopeProvince = null;
        } elseif ($province !== '') {
            $q->where('province', $province);
            $scopeLabel = 'province';
            $scopeCity = null;
            $scopeProvince = $province;
        } else {
            return [[], 'none', null, null];
        }

        $rows = $q->order('sort_order', 'asc')
            ->order('view_count', 'desc')
            ->order('id', 'desc')
            ->limit($limit)
            ->select();

        $out = [];
        foreach ($rows as $row) {
            $arr = $row->toArray();
            $out[] = [
                'id'            => (int) ($arr['id'] ?? 0),
                'name'          => (string) ($arr['name'] ?? ''),
                'intro'         => (string) ($arr['intro'] ?? ''),
                'cover_image'   => (string) ($arr['cover_image'] ?? ''),
                'province'      => (string) ($arr['province'] ?? ''),
                'city'          => (string) ($arr['city'] ?? ''),
                'district'      => (string) ($arr['district'] ?? ''),
                'address'       => (string) ($arr['address'] ?? ''),
                'contact_phone' => (string) ($arr['contact_phone'] ?? ''),
                'longitude'     => $arr['longitude'] ?? null,
                'latitude'      => $arr['latitude'] ?? null,
                'opening_hours' => (string) ($arr['opening_hours'] ?? ''),
                'price_type'    => (string) ($arr['price_type'] ?? ''),
                'price_info'    => (string) ($arr['price_info'] ?? ''),
                'price_min'        => $arr['price_min'] ?? null,
                'price_max'        => $arr['price_max'] ?? null,
                'facilities'       => (string) ($arr['facilities'] ?? ''),
                'facilities_list'  => $this->parseFacilitiesList($arr['facilities'] ?? ''),
                'fish_species'     => (string) ($arr['fish_species'] ?? ''),
                'view_count'    => (int) ($arr['view_count'] ?? 0),
                'sort_order'    => (int) ($arr['sort_order'] ?? 0),
            ];
        }

        return [$out, $scopeLabel, $scopeCity, $scopeProvince];
    }

    /**
     * 与钓场详情一致：facilities 可为 JSON 数组或中英文逗号分隔
     *
     * @return string[]
     */
    private function parseFacilitiesList(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        $s = is_string($raw) ? $raw : (string) $raw;
        $s = trim($s);
        if ($s === '') {
            return [];
        }
        $decoded = json_decode($s, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map(static function ($v) {
                return trim((string) $v);
            }, $decoded), static function ($v) {
                return $v !== '';
            }));
        }
        $parts = preg_split('/[，,]/u', $s);

        return array_values(array_filter(array_map('trim', is_array($parts) ? $parts : []), static function ($v) {
            return $v !== '';
        }));
    }

    /**
     * 与库内 city 常见写法兼容：如「广州」「广州市」
     *
     * @return string[]
     */
    private function cityNameCandidates(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $cands = [$raw];
        if (mb_substr($raw, -1) === '市') {
            $cands[] = mb_substr($raw, 0, -1);
        } else {
            $cands[] = $raw . '市';
        }

        return array_values(array_unique(array_filter($cands)));
    }
}
