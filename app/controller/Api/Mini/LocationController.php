<?php
declare (strict_types = 1);

namespace app\controller\Api\Mini;

use app\BaseController;
use app\model\SystemConfig;
use think\facade\Cache;
use think\response\Json;

/**
 * 小程序端 - 位置上报与城市解析
 *
 * 前端示例：
 * POST /api/mini/location/report
 * body: { latitude, longitude }
 *
 * 返回：当前经纬度解析出的城市 / 省份信息，以及实时天气（气压、温度、风速、风向、能见度）
 */
class LocationController extends BaseController
{
    /** Open-Meteo 预报接口（无需 key，按经纬度） */
    private const OPEN_METEO_FORECAST = 'https://api.open-meteo.com/v1/forecast';

    /** 天气短时缓存 key 前缀 */
    private const WEATHER_CACHE_PREFIX = 'open_meteo_current:';

    /** 同一网格内复用天气，秒（减轻 Open-Meteo 调用与限流风险） */
    private const WEATHER_CACHE_TTL = 300;

    /**
     * 经纬度量化步长（度）。0.01° 纬度约 1.1km，同一网格共用一条天气缓存
     */
    private const WEATHER_GRID_STEP = 0.01;

    /**
     * 上报位置并返回城市信息
     * POST /api/mini/location/report
     */
    public function report(): Json
    {
        $lat = (float) $this->request->post('latitude', 0);
        $lng = (float) $this->request->post('longitude', 0);

        if (!$lat || !$lng) {
            return json(['code' => 400, 'msg' => '请传入有效的经纬度', 'data' => null]);
        }

        // 优先从 system_config 读取高德「服务端」key（amap_server_key），没有则回退到 amap_key
        $amapKey = SystemConfig::getValue('amap_server_key', '');
        if ($amapKey === '') {
            $amapKey = SystemConfig::getValue('amap_key_webfw', '');
        }
        if ($amapKey === '') {
            return json(['code' => 500, 'msg' => '尚未配置 amap_key_webfw，请在「全局配置」中添加', 'data' => null]);
        }

        $location = $lng . ',' . $lat; // 高德经纬度顺序：lng,lat
        $url = sprintf(
            'https://restapi.amap.com/v3/geocode/regeo?key=%s&location=%s&radius=1000&extensions=base',
            urlencode($amapKey),
            urlencode($location)
        );

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout' => 5,
                ],
            ]);
            $resp = @file_get_contents($url, false, $context);
            if ($resp === false) {
                return json(['code' => 500, 'msg' => '请求高德逆地理接口失败', 'data' => null]);
            }
            $data = json_decode($resp, true);
        } catch (\Throwable $e) {
            return json(['code' => 500, 'msg' => '解析城市失败：' . $e->getMessage(), 'data' => null]);
        }

        if (!is_array($data) || ($data['status'] ?? '0') !== '1') {
            $msg = $data['info'] ?? '高德返回异常';
            return json(['code' => 500, 'msg' => '解析城市失败：' . $msg, 'data' => null]);
        }

        $comp = $data['regeocode']['addressComponent'] ?? [];
        $city = '';
        if (!empty($comp['city'])) {
            // 普通城市：city 字段为字符串或数组
            $city = is_array($comp['city']) ? ($comp['city'][0] ?? '') : (string) $comp['city'];
        } else {
            // 直辖市等，city 可能为空，使用 province 代替
            $city = (string) ($comp['province'] ?? '');
        }
        $province = (string) ($comp['province'] ?? '');
        $district = (string) ($comp['district'] ?? '');

        $weather = $this->fetchCurrentWeatherOpenMeteo($lat, $lng);

        return json([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'city'     => $city,
                'province' => $province,
                'district' => $district,
                'location' => [
                    'latitude'  => $lat,
                    'longitude' => $lng,
                ],
                // 实时天气；请求失败时为 null，不影响城市解析
                'weather'  => $weather,
            ],
        ]);
    }

    /**
     * 从 Open-Meteo 拉取当前时刻天气（含 WMO weather_code → 中文天气状况）
     * 同一经纬度网格 + 缓存 TTL 内复用结果，减少外网请求
     */
    private function fetchCurrentWeatherOpenMeteo(float $lat, float $lng): ?array
    {
        $cacheKey = self::WEATHER_CACHE_PREFIX . self::weatherGridCacheKey($lat, $lng);

        $cached = Cache::get($cacheKey);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $fresh = $this->requestOpenMeteoCurrentWeather($lat, $lng);
        if ($fresh !== null) {
            Cache::set($cacheKey, $fresh, self::WEATHER_CACHE_TTL);
        }

        return $fresh;
    }

    /**
     * 将经纬度落到网格，生成稳定缓存键片段（避免 float 字符串差异）
     */
    private static function weatherGridCacheKey(float $lat, float $lng): string
    {
        $step = self::WEATHER_GRID_STEP;
        $gLat = round($lat / $step) * $step;
        $gLng = round($lng / $step) * $step;

        return sprintf('%.4f_%.4f', $gLat, $gLng);
    }

    /**
     * 实际请求 Open-Meteo 并组装返回结构（不经缓存）
     */
    private function requestOpenMeteoCurrentWeather(float $lat, float $lng): ?array
    {
        $query = http_build_query([
            'latitude'          => $lat,
            'longitude'         => $lng,
            'current'           => 'temperature_2m,pressure_msl,wind_speed_10m,wind_direction_10m,visibility,weather_code',
            'wind_speed_unit'   => 'ms',
            'timezone'          => 'auto',
        ], '', '&', PHP_QUERY_RFC3986);

        $url = self::OPEN_METEO_FORECAST . '?' . $query;

        try {
            $context = stream_context_create([
                'http' => [
                    'timeout'    => 5,
                    'user_agent' => 'Mozilla/5.0 (compatible; FishingAdmin/1.0)',
                ],
            ]);
            $resp = @file_get_contents($url, false, $context);
            if ($resp === false || $resp === '') {
                return null;
            }
            $json = json_decode($resp, true);
        } catch (\Throwable $e) {
            return null;
        }

        if (!is_array($json) || empty($json['current']) || !is_array($json['current'])) {
            return null;
        }

        $c = $json['current'];
        $temp = isset($c['temperature_2m']) ? (float) $c['temperature_2m'] : null;
        $pressure = isset($c['pressure_msl']) ? (float) $c['pressure_msl'] : null;
        $windSpeed = isset($c['wind_speed_10m']) ? (float) $c['wind_speed_10m'] : null;
        $windDeg = isset($c['wind_direction_10m']) ? (float) $c['wind_direction_10m'] : null;
        $vis = isset($c['visibility']) ? (float) $c['visibility'] : null; // 米

        $time = isset($c['time']) ? (string) $c['time'] : '';

        $wcode = isset($c['weather_code']) ? (int) $c['weather_code'] : null;

        return [
            'weather_code'       => $wcode, // WMO 天气代码，可用于图标
            'condition'          => self::wmoWeatherCodeToCn($wcode), // 中文天气状况，如「晴」「小雨」「雷阵雨」
            'pressure'           => $pressure, // 海平面气压，hPa
            'temperature'        => $temp, // 2m 气温，°C
            'wind_speed'         => $windSpeed, // 10m 风速，m/s
            'wind_direction_deg' => $windDeg, // 风吹来的方向，0–360°（气象惯例）
            'wind_direction'     => self::windDirectionLabelCn($windDeg),
            'visibility'         => $vis, // 能见度，米
            'visibility_km'      => $vis !== null ? round($vis / 1000, 2) : null,
            'observed_at'        => $time,
        ];
    }

    /**
     * Open-Meteo / WMO 天气代码转中文简述（与官方 interpretation 表一致）
     * @see https://open-meteo.com/en/docs#api_formatted_variables
     */
    private static function wmoWeatherCodeToCn(?int $code): string
    {
        if ($code === null) {
            return '';
        }
        return match ($code) {
            0 => '晴',
            1 => '晴间多云',
            2 => '多云',
            3 => '阴',
            45, 48 => '雾',
            51 => '小毛毛雨',
            53 => '毛毛雨',
            55 => '大毛毛雨',
            56, 57 => '冻毛毛雨',
            61 => '小雨',
            63 => '中雨',
            65 => '大雨',
            66, 67 => '冻雨',
            71 => '小雪',
            73 => '中雪',
            75 => '大雪',
            77 => '米雪',
            80 => '小阵雨',
            81 => '阵雨',
            82 => '强阵雨',
            85 => '小阵雪',
            86 => '阵雪',
            95 => '雷阵雨',
            96, 99 => '雷雨伴冰雹',
            default => '未知',
        };
    }

    /**
     * 根据风向角度（风吹来的方向）生成中文八方位描述，如「东南风」
     */
    private static function windDirectionLabelCn(?float $deg): string
    {
        if ($deg === null || !is_finite($deg)) {
            return '';
        }
        $d = fmod($deg, 360.0);
        if ($d < 0) {
            $d += 360.0;
        }
        $labels = ['北', '东北', '东', '东南', '南', '西南', '西', '西北'];
        $idx = (int) floor(($d + 22.5) / 45.0) % 8;

        return $labels[$idx] . '风';
    }
}

