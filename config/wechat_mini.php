<?php

// 微信小程序配置（请根据实际填写 appid、secret）
return [
    // 小程序 AppID
    'appid'  => env('WECHAT_MINI_APPID', ''),
    // 小程序 AppSecret
    'secret' => env('WECHAT_MINI_SECRET', ''),
    // 登录 token 有效期（秒），默认 30 天
    'token_ttl' => env('WECHAT_MINI_TOKEN_TTL', 86400 * 30),
];

