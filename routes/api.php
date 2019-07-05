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
        $route->post('add_user', 'UserController@add_user');// 会员开卡
        $route->post('user_sn', 'UserController@user_sn');// 会员卡号生成
        $route->post('user_rank', 'UserController@user_rank');// 会员等级
        $route->post('add_user_car', 'UserController@add_user_car');// 会员车辆添加
        $route->post('del_user_car', 'UserController@del_user_car');// 会员车辆删除
        $route->post('update_user_car', 'UserController@update_user_car');// 会员车辆修改
        $route->post('user_car', 'UserController@user_car');// 会员车辆列表
        $route->post('add_user_address', 'UserController@add_user_address');// 会员添加地址
        $route->post('del_user_address', 'UserController@del_user_address');// 会员删除地址
        $route->post('update_user_address', 'UserController@update_user_address');// 会员修改地址
        $route->post('user_address', 'UserController@user_address');// 会员地址列表
        $route->post('user_coupon', 'UserController@user_coupon');// 会员可用优惠券信息
        $route->post('add_user_money', 'UserController@add_user_money');// 会员充值
    });
});
