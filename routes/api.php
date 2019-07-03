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
        $route->post('add_flower_order', 'OrderController@add_flower_order');// 花艺开单
    });

    $route->group(['prefix' => 'user', 'middleware' => 'login'], function ($route) {
        $route->post('user_info', 'UserController@user_info');// 会员信息
        $route->post('user_address', 'UserController@user_address');// 会员地址信息
        $route->post('user_coupon', 'UserController@user_coupon');// 会员可用优惠券信息
    });
});
