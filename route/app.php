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
    // 需登录：GET /api/mini/me、POST /api/mini/profile（避免 /user/ 与 api/user 冲突）
    Route::get('mini/me', 'Api.Mini.UserController/me')->middleware(\app\middleware\MiniAuth::class);
    Route::post('mini/profile', 'Api.Mini.UserController/profile')->middleware(\app\middleware\MiniAuth::class);

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
            Route::get('admin-users', 'Api.Admin.AdminUserController/list');
            Route::get('admin-users/:id', 'Api.Admin.AdminUserController/detail');
            Route::post('admin-users', 'Api.Admin.AdminUserController/create');
            Route::put('admin-users/:id', 'Api.Admin.AdminUserController/update');
            Route::delete('admin-users/:id', 'Api.Admin.AdminUserController/delete');
            Route::get('roles', 'Api.Admin.RoleController/list');
            Route::get('roles/:id', 'Api.Admin.RoleController/detail');
            Route::post('roles', 'Api.Admin.RoleController/create');
            Route::put('roles/:id', 'Api.Admin.RoleController/update');
            Route::delete('roles/:id', 'Api.Admin.RoleController/delete');
            Route::get('permissions', 'Api.Admin.PermissionController/list');
            Route::get('banners', 'Api.Admin.BannerController/list');
            Route::get('banners/:id', 'Api.Admin.BannerController/detail');
            Route::post('banners', 'Api.Admin.BannerController/create');
            Route::put('banners/:id', 'Api.Admin.BannerController/update');
            Route::delete('banners/:id', 'Api.Admin.BannerController/delete');
        })->middleware([\app\middleware\AdminAuth::class, \app\middleware\AdminPermission::class]);
    });
});
