<?php
declare(strict_types=1);

namespace app\service;

/**
 * 微信支付 v3 轻量客户端（仅覆盖本项目需要的「转账到零钱」发起请求）
 *
 * 配置来自 system_config：
 * - pay_mch_id（商户号，与 v2 复用）
 * - wxpay_v3_serial_no          商户证书序列号（用于 Authorization.serial_no）
 * - wxpay_v3_private_key_pem    商户私钥 PEM（PKCS#8，带 BEGIN PRIVATE KEY）
 * - wxpay_v3_appid              小程序 appid（收款 openid 所属）
 *
 * 说明：
 * - 这里只负责请求签名与发送，不做平台证书验签（如需可再补）。
 * - 敏感字段（user_name）加密较繁琐；本项目默认不传 user_name。
 */
class WechatPayV3Client
{
    private string $mchId;
    private string $serialNo;
    private string $privateKeyPem;
    private string $appId;

    public function __construct(string $mchId, string $serialNo, string $privateKeyPem, string $appId)
    {
        $this->mchId = trim($mchId);
        $this->serialNo = trim($serialNo);
        $this->privateKeyPem = trim($privateKeyPem);
        $this->appId = trim($appId);
    }

    /**
     * 发起「转账到零钱」
     *
     * @param array{
     *  out_bill_no:string,
     *  openid:string,
     *  transfer_amount:int,
     *  transfer_remark:string,
     *  notify_url?:string,
     *  transfer_scene_id?:string,
     *  user_recv_perception?:string,
     *  transfer_scene_report_infos?:array<int,array{info_type:string,info_content:string}>
     * } $payload
     *
     * @return array{http_code:int, body:array|string}
     */
    public function transferBills(array $payload): array
    {
        $path = '/v3/fund-app/mch-transfer/transfer-bills';
        $url = 'https://api.mch.weixin.qq.com' . $path;

        $bodyArr = array_merge([
            'appid' => $this->appId,
        ], $payload);

        $body = json_encode($bodyArr, JSON_UNESCAPED_UNICODE);
        if ($body === false) {
            return ['http_code' => 0, 'body' => ['code' => 'JSON_ENCODE_FAILED', 'message' => 'json encode failed']];
        }

        $timestamp = (string) time();
        $nonceStr = bin2hex(random_bytes(16));
        $signature = $this->sign('POST', $path, $timestamp, $nonceStr, $body);
        if ($signature === '') {
            return ['http_code' => 0, 'body' => ['code' => 'SIGN_FAILED', 'message' => 'sign failed']];
        }

        $authorization = sprintf(
            'WECHATPAY2-SHA256-RSA2048 mchid="%s",nonce_str="%s",timestamp="%s",serial_no="%s",signature="%s"',
            $this->mchId,
            $nonceStr,
            $timestamp,
            $this->serialNo,
            $signature
        );

        $headers = [
            'Authorization: ' . $authorization,
            'Accept: application/json',
            'Content-Type: application/json',
            // v3 API 建议携带 User-Agent；可选
            'User-Agent: fishingAdmin/1.0',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 20,
        ]);
        $resp = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($resp === false || $resp === null) {
            return ['http_code' => $httpCode, 'body' => ['code' => 'NETWORK_ERROR', 'message' => 'curl failed']];
        }
        $decoded = json_decode((string) $resp, true);
        return ['http_code' => $httpCode, 'body' => is_array($decoded) ? $decoded : (string) $resp];
    }

    private function sign(string $method, string $path, string $timestamp, string $nonceStr, string $body): string
    {
        $message = $method . "\n" . $path . "\n" . $timestamp . "\n" . $nonceStr . "\n" . $body . "\n";
        $pkey = openssl_pkey_get_private($this->privateKeyPem);
        if ($pkey === false) {
            return '';
        }
        $signature = '';
        $ok = openssl_sign($message, $signature, $pkey, OPENSSL_ALGO_SHA256);
        openssl_free_key($pkey);
        if (!$ok) {
            return '';
        }
        return base64_encode($signature);
    }
}

