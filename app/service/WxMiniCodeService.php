<?php
declare(strict_types=1);

namespace app\service;

use app\model\SystemConfig;
use think\facade\Cache;

/**
 * 微信小程序码（getwxacodeunlimit）生成服务
 */
class WxMiniCodeService
{
    private const TOKEN_CACHE_KEY = 'wechat_mini:access_token';

    /**
     * 获取 access_token（带缓存）
     */
    public static function getAccessToken(): ?string
    {
        $cached = (string) Cache::get(self::TOKEN_CACHE_KEY, '');
        if ($cached !== '') {
            return $cached;
        }

        $miniConfig = config('wechat_mini');
        $appId  = (string) ($miniConfig['appid'] ?? '');
        $secret = (string) ($miniConfig['secret'] ?? '');

        // system_config 可覆盖
        $cfgAppId  = SystemConfig::getValue('mini_appid', '');
        $cfgSecret = SystemConfig::getValue('mini_secret', '');
        if ($cfgAppId !== '') $appId = $cfgAppId;
        if ($cfgSecret !== '') $secret = $cfgSecret;

        if ($appId === '' || $secret === '') {
            return null;
        }

        $tokenUrl = sprintf(
            'https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=%s&secret=%s',
            urlencode($appId),
            urlencode($secret)
        );
        $tokenJson = @file_get_contents($tokenUrl);
        if (!$tokenJson) {
            return null;
        }
        $tokenArr = json_decode($tokenJson, true);
        if (!is_array($tokenArr) || empty($tokenArr['access_token'])) {
            return null;
        }
        $token = (string) $tokenArr['access_token'];
        $ttl = (int) ($tokenArr['expires_in'] ?? 7000);
        if ($ttl <= 0) $ttl = 7000;
        // 提前 60 秒过期，避免边界失效
        Cache::set(self::TOKEN_CACHE_KEY, $token, max(60, $ttl - 60));
        return $token;
    }

    /**
     * 获取小程序码二进制（png）
     * @return array{0: string|null, 1: string|null} [$pngBinary, $errorMessage]
     */
    public static function getUnlimitedCode(string $page, string $scene, string $envVersion = 'trial', int $width = 430): array
    {
        $token = self::getAccessToken();
        if (!$token) {
            return [null, 'mini access_token 获取失败（请配置 mini_appid/mini_secret）'];
        }

        $env = $envVersion === 'release' ? 'release' : 'trial';

        $postData = json_encode([
            'page'        => $page,
            'scene'       => $scene,
            'check_path'  => false,
            'env_version' => $env,
            'width'       => $width,
        ], JSON_UNESCAPED_UNICODE);

        $url = 'https://api.weixin.qq.com/wxa/getwxacodeunlimit?access_token=' . urlencode($token);
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $resp = curl_exec($ch);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($resp === false || !$resp) {
            return [null, '请求微信小程序码失败'];
        }

        // 如果返回 json，代表报错
        if (is_string($contentType) && stripos($contentType, 'json') !== false) {
            $arr = json_decode((string) $resp, true);
            $msg = is_array($arr) ? (($arr['errmsg'] ?? '') . ' (errcode=' . ($arr['errcode'] ?? '') . ')') : '微信返回异常';
            return [null, $msg];
        }

        return [(string) $resp, null];
    }
}

