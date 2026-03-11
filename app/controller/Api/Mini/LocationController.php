<?php
declare (strict_types = 1);

namespace app\controller\Api\Mini;

use app\BaseController;
use app\model\SystemConfig;
use think\response\Json;

/**
 * 小程序端 - 位置上报与城市解析
 *
 * 前端示例：
 * POST /api/mini/location/report
 * body: { latitude, longitude }
 *
 * 返回：当前经纬度解析出的城市 / 省份信息
 */
class LocationController extends BaseController
{
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
            ],
        ]);
    }
}

