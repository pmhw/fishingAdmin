<?php

// 微信支付（小程序 JSAPI）配置
// 请在 .env 中配置以下环境变量：
// WECHAT_PAY_MCH_ID      商户号
// WECHAT_PAY_KEY         API v2 密钥（32位）
// WECHAT_PAY_NOTIFY_URL  支付结果回调地址（完整 https URL）
// 默认使用 wechat_mini.appid 作为支付 appid
return [
    // 商户号 mch_id
    'mch_id'     => env('WECHAT_PAY_MCH_ID', ''),
    // API v2 密钥（用于统一下单/签名），32 位
    'key'        => env('WECHAT_PAY_KEY', ''),
    // 回调通知地址（必须 https，可在 .env 中覆盖）
    // 默认指向当前项目的微信支付回调接口 /api/mini/pay/wechat/notify
    'notify_url' => env('WECHAT_PAY_NOTIFY_URL', 'https://你的域名/api/mini/pay/wechat/notify'),
];

