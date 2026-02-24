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
});
