<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::group(['namespace' => 'Api'], function ($route) {
    $route->group(['prefix' => 'auth'], function ($route) {
        $route->post('/login', 'AuthController@login');// 登录
        $route->post('/logins', 'AuthController@logins');// 登录接口
    });

    $route->group(['prefix' => 'order', 'middleware' => 'login'], function ($route) {
        $route->post('order_list', 'OrderController@order_list');// 订单列表
        $route->post('order_info', 'OrderController@order_info');// 订单详情
        $route->post('take_order', 'OrderController@take_order');// 订单接单
        $route->post('done_order', 'OrderController@done_order');// 订单制作完成
    });
});
