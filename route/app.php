<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2018 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
use think\facade\Route;

Route::get('think', function () {
    return 'hello,ThinkPHP8!';
});

Route::get('hello/:name', 'index/hello');

// 上传文件访问（runtime/storage 下的文件，如 /storage/banner/202502/xxx.jpg）
Route::get('storage/:path', 'Storage/read')->pattern(['path' => '.+']);

// ========== 示例 API 路由 ==========
Route::group('api', function () {
    // 通用接口
    Route::get('index/ping', 'Api.Index/ping');
    Route::get('index/info', 'Api.Index/info');
    // 用户接口
    Route::get('user/list', 'Api.User/list');
    Route::get('user/detail/:id', 'Api.User/detail');
    Route::post('user/create', 'Api.User/create');

    // ========== Vben Admin 官方模板兼容接口（code/data/message、accessToken、userInfo） ==========
    Route::post('auth/login', 'Api.VbenAuth/login');
    Route::get('user/info', 'Api.VbenAuth/userInfo');
    Route::get('auth/codes', 'Api.VbenAuth/codes');

    // ========== 小程序端 ==========
    Route::post('mini/login', 'Api.Mini.AuthController/login');
    Route::get('mini/banners', 'Api.Mini.BannerController/list');
    // 钓场 / 门店展示（先具体路径，再 :id，再 list，避免误匹配）
    Route::get('mini/venues/:id/spot', 'Api.Mini.VenueController/spot');
    // 钓场放鱼动态（汇总该钓场下所有池塘的放鱼记录）
    Route::get('mini/venues/:id/feeds', 'Api.Mini.VenueController/feeds');
    Route::get('mini/venues/:id', 'Api.Mini.VenueController/detail');
    Route::get('mini/venues', 'Api.Mini.VenueController/list');
    // 池塘详情（收费规则 + 钓位，用于开卡页）
    Route::get('mini/ponds/:id', 'Api.Mini.PondController/detail');
    // 位置上报（用于解析城市等）
    Route::post('mini/location/report', 'Api.Mini.LocationController/report');
    // 仅查天气（GET，query: latitude、longitude；与 location/report 共用 Open-Meteo + 网格缓存）
    Route::get('mini/weather', 'Api.Mini.LocationController/weather');
    // 支付：下单需登录，回调不需登录
    Route::post('mini/pay/wechat/jsapi', 'Api.Mini.PayController/jsapi')->middleware(\app\middleware\MiniAuth::class);
    Route::post('mini/pay/wechat/notify', 'Api.Mini.PayController/notify');
    // 需登录（任一路径均可）
    Route::post('mini/upload', 'Api.Mini.UploadController/index')->middleware(\app\middleware\MiniAuth::class);
    Route::get('mini/me', 'Api.Mini.UserController/me')->middleware(\app\middleware\MiniAuth::class);
    Route::get('mini/user/me', 'Api.Mini.UserController/me')->middleware(\app\middleware\MiniAuth::class);
    Route::get('mini/user/info', 'Api.Mini.UserController/info')->middleware(\app\middleware\MiniAuth::class);
    // 小程序会员余额：仅返回 balance/is_vip 等关键信息
    Route::get('mini/user/balance', 'Api.Mini.UserController/balance')->middleware(\app\middleware\MiniAuth::class);
    Route::post('mini/profile', 'Api.Mini.UserController/profile')->middleware(\app\middleware\MiniAuth::class);
    Route::put('mini/user/profile', 'Api.Mini.UserController/profile')->middleware(\app\middleware\MiniAuth::class);
    Route::post('mini/user/profile', 'Api.Mini.UserController/profile')->middleware(\app\middleware\MiniAuth::class);
    // 小程序订单查询（支付页用）
    Route::get('mini/orders/:order_no', 'Api.Mini.OrderController/show')->middleware(\app\middleware\MiniAuth::class);
    // 小程序端开钓单（开卡）
    Route::post('mini/sessions', 'Api.Mini.SessionController/create')->middleware(\app\middleware\MiniAuth::class);
    // 小程序端垂钓记录（开钓单列表 + 总数 + 总回鱼重量）
    Route::get('mini/session-records', 'Api.Mini.SessionRecordController/list')->middleware(\app\middleware\MiniAuth::class);

    // 小程序端：用户收藏钓场
    Route::post('mini/favorites/venues', 'Api.Mini.FavoriteVenueController/add')->middleware(\app\middleware\MiniAuth::class);
    Route::delete('mini/favorites/venues/:venue_id', 'Api.Mini.FavoriteVenueController/remove')->middleware(\app\middleware\MiniAuth::class);
    Route::get('mini/favorites/venues', 'Api.Mini.FavoriteVenueController/list')->middleware(\app\middleware\MiniAuth::class);
    // 是否收藏（不强制登录）
    Route::get('mini/favorites/venues/:venue_id/check', 'Api.Mini.FavoriteVenueController/check');

    // 小程序会员码：动态二维码 + 过期校验
    Route::post('mini/vip/codes', 'Api.Mini.VipCodeController/create')->middleware(\app\middleware\MiniAuth::class);
    Route::get('mini/vip/codes/verify', 'Api.Mini.VipCodeController/verify');

    // ========== 后台管理 ==========
    Route::group('admin', function () {
        // 以下不需要登录（不走登录中间件）
        Route::get('captcha', 'Api.Admin.Auth/captcha');
        Route::post('login', 'Api.Admin.Auth/login');
        Route::post('init', 'Api.Admin.Auth/init');
        // 以下需登录，统一走 AdminAuth 中间件
        Route::group(function () {
            Route::get('me', 'Api.Admin.Auth/me');
            Route::post('logout', 'Api.Admin.Auth/logout');
            Route::post('upload/image', 'Api.Admin.UploadController/image');
            // admin-users：先 :id 后集合，避免 /admin-users/1 被误匹配
            Route::get('admin-users/:id', 'Api.Admin.AdminUserController/detail');
            Route::put('admin-users/:id', 'Api.Admin.AdminUserController/update');
            Route::delete('admin-users/:id', 'Api.Admin.AdminUserController/delete');
            Route::get('admin-users', 'Api.Admin.AdminUserController/list');
            Route::post('admin-users', 'Api.Admin.AdminUserController/create');
            // roles：先子路径 /:id/ponds 和 /:id，再 list/create
            Route::get('roles/:id/ponds', 'Api.Admin.RoleController/ponds');
            Route::put('roles/:id/ponds', 'Api.Admin.RoleController/updatePonds');
            Route::get('roles/:id/venues', 'Api.Admin.RoleController/venues');
            Route::put('roles/:id/venues', 'Api.Admin.RoleController/updateVenues');
            Route::get('roles/:id', 'Api.Admin.RoleController/detail');
            Route::put('roles/:id', 'Api.Admin.RoleController/update');
            Route::delete('roles/:id', 'Api.Admin.RoleController/delete');
            Route::get('roles', 'Api.Admin.RoleController/list');
            Route::post('roles', 'Api.Admin.RoleController/create');
            Route::get('permissions', 'Api.Admin.PermissionController/list');
            // banners：先 :id 后集合
            Route::get('banners/:id', 'Api.Admin.BannerController/detail');
            Route::put('banners/:id', 'Api.Admin.BannerController/update');
            Route::delete('banners/:id', 'Api.Admin.BannerController/delete');
            Route::get('banners', 'Api.Admin.BannerController/list');
            Route::post('banners', 'Api.Admin.BannerController/create');
            // configs：先 /values、/:id 后 list/create/update
            Route::get('configs/values', 'Api.Admin.SystemConfigController/values');
            Route::get('configs/:id', 'Api.Admin.SystemConfigController/detail');
            Route::put('configs/:id', 'Api.Admin.SystemConfigController/update');
            Route::get('configs', 'Api.Admin.SystemConfigController/list');
            Route::post('configs', 'Api.Admin.SystemConfigController/create');
            // venues：先 /:id/status、/:id 后 list/create/update/delete
            Route::put('venues/:id/status', 'Api.Admin.FishingVenueController/updateStatus');
            Route::get('venues/:id', 'Api.Admin.FishingVenueController/detail');
            Route::put('venues/:id', 'Api.Admin.FishingVenueController/update');
            Route::delete('venues/:id', 'Api.Admin.FishingVenueController/delete');
            // venue-options：钓场下拉选项（按角色钓场范围过滤）
            Route::get('venue-options', 'Api.Admin.FishingVenueController/options');
            Route::get('venues', 'Api.Admin.FishingVenueController/list');
            Route::post('venues', 'Api.Admin.FishingVenueController/create');
            // ponds：先 :id 后集合
            Route::get('ponds/:id/seats', 'Api.Admin.PondSeatController/list');
            Route::post('ponds/:id/seats/sync', 'Api.Admin.PondSeatController/sync');
            Route::post('ponds/:id/seats/qrcodes/zip', 'Api.Admin.PondSeatController/downloadQrcodesZip');
            Route::delete('ponds/:id/seats/qrcodes/cleanup', 'Api.Admin.PondSeatController/cleanupQrcodes');
            Route::post('ponds/:id/seats/qrcodes', 'Api.Admin.PondSeatController/generateQrcodes');
            Route::get('ponds/:id', 'Api.Admin.PondController/detail');
            Route::put('ponds/:id', 'Api.Admin.PondController/update');
            Route::delete('ponds/:id', 'Api.Admin.PondController/delete');
            Route::get('ponds', 'Api.Admin.PondController/list');
            Route::post('ponds', 'Api.Admin.PondController/create');
            // pond-regions：先 :id 后集合
            Route::delete('pond-regions/:id', 'Api.Admin.PondRegionController/delete');
            Route::get('pond-regions', 'Api.Admin.PondRegionController/list');
            Route::post('pond-regions', 'Api.Admin.PondRegionController/create');
            // pond-fee-rules：先 :id 后集合
            Route::delete('pond-fee-rules/:id', 'Api.Admin.PondFeeRuleController/delete');
            Route::put('pond-fee-rules/:id', 'Api.Admin.PondFeeRuleController/update');
            Route::get('pond-fee-rules', 'Api.Admin.PondFeeRuleController/list');
            Route::post('pond-fee-rules', 'Api.Admin.PondFeeRuleController/create');
            // pond-return-rules：先 :id 后集合
            Route::delete('pond-return-rules/:id', 'Api.Admin.PondReturnRuleController/delete');
            Route::put('pond-return-rules/:id', 'Api.Admin.PondReturnRuleController/update');
            Route::get('pond-return-rules', 'Api.Admin.PondReturnRuleController/list');
            Route::post('pond-return-rules', 'Api.Admin.PondReturnRuleController/create');
            // pond-feed-logs：放鱼记录管理
            Route::delete('pond-feed-logs/:id', 'Api.Admin.PondFeedLogController/delete');
            Route::put('pond-feed-logs/:id', 'Api.Admin.PondFeedLogController/update');
            Route::get('pond-feed-logs', 'Api.Admin.PondFeedLogController/list');
            Route::post('pond-feed-logs', 'Api.Admin.PondFeedLogController/create');
            // sessions：开钓单（经营链路）
            Route::get('sessions/:id', 'Api.Admin.FishingSessionController/detail');
            Route::get('sessions', 'Api.Admin.FishingSessionController/list');
            Route::post('sessions', 'Api.Admin.FishingSessionController/create');
            Route::put('sessions/:id/finish', 'Api.Admin.FishingSessionController/finish');
            Route::put('sessions/:id/cancel', 'Api.Admin.FishingSessionController/cancel');
            // pond-return-logs：回鱼流水（经营链路）
            Route::delete('pond-return-logs/:id', 'Api.Admin.PondReturnLogController/delete');
            Route::put('pond-return-logs/:id', 'Api.Admin.PondReturnLogController/update');
            Route::get('pond-return-logs', 'Api.Admin.PondReturnLogController/list');
            Route::post('pond-return-logs', 'Api.Admin.PondReturnLogController/create');
            // fish-trade-logs：卖鱼/收鱼流水（经营链路）
            Route::delete('fish-trade-logs/:id', 'Api.Admin.FishTradeLogController/delete');
            Route::put('fish-trade-logs/:id', 'Api.Admin.FishTradeLogController/update');
            Route::get('fish-trade-logs', 'Api.Admin.FishTradeLogController/list');
            Route::post('fish-trade-logs', 'Api.Admin.FishTradeLogController/create');
            // orders：订单管理（只读）
            Route::get('orders', 'Api.Admin.FishingOrderController/list');
            // mini-users：小程序用户（用于后台搜索）
            Route::get('mini-users', 'Api.Admin.MiniUserController/list');
        })->middleware([\app\middleware\AdminAuth::class, \app\middleware\AdminPermission::class]);
    });
});
