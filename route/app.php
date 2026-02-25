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

    // ========== 后台管理（需登录的加中间件） ==========
    Route::group('admin', function () {
        Route::post('login', 'Api.Admin.Auth/login');
        Route::post('init', 'Api.Admin.Auth/init');
        Route::get('me', 'Api.Admin.Auth/me')->middleware(\app\middleware\AdminAuth::class);
        Route::post('logout', 'Api.Admin.Auth/logout')->middleware(\app\middleware\AdminAuth::class);
        Route::get('admins', 'Api.Admin.AdminUserController/list')->middleware(\app\middleware\AdminAuth::class);
        Route::get('admins/:id', 'Api.Admin.AdminUserController/detail')->middleware(\app\middleware\AdminAuth::class);
        Route::post('admins', 'Api.Admin.AdminUserController/create')->middleware(\app\middleware\AdminAuth::class);
        Route::put('admins/:id', 'Api.Admin.AdminUserController/update')->middleware(\app\middleware\AdminAuth::class);
        Route::delete('admins/:id', 'Api.Admin.AdminUserController/delete')->middleware(\app\middleware\AdminAuth::class);
    });
});
