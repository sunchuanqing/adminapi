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
        $route->post('/login_out', 'AuthController@login_out');// 退出登录
    });

    // 订单
    $route->group(['prefix' => 'order', 'middleware' => 'login'], function ($route) {
        $route->post('order_list', 'OrderController@order_list');// 订单列表
        $route->post('order_info', 'OrderController@order_info');// 订单详情
        $route->post('take_order', 'OrderController@take_order');// 订单接单
        $route->post('done_order', 'OrderController@done_order');// 订单制作完成
        $route->post('send_order', 'OrderController@send_order');// 订单送出
        $route->post('add_flower_order', 'OrderController@add_flower_order');// 花艺开单
        $route->post('flower_list', 'OrderController@flower_list');// 花束列表
        $route->post('today_order', 'OrderController@today_order');// 今日代办
        $route->post('add_car_order', 'OrderController@add_car_order');// 车护开单
        $route->post('update_order_serve', 'OrderController@update_order_serve');// 修改车护订单商品信息
        $route->post('price_list_type', 'OrderController@price_list_type');// 价目表分类
        $route->post('price_list', 'OrderController@price_list');// 查询价目表
        $route->post('set_meal_list', 'OrderController@set_meal_list');// 优惠套餐列表
        $route->post('car_goods', 'OrderController@car_goods');// 车护商品列表
        $route->post('server_list', 'OrderController@server_list');// 服务项目列表
        $route->post('to_work', 'OrderController@to_work');// 去施工
        $route->post('work_ok', 'OrderController@work_ok');// 施工完成接口
        $route->post('car_order_list', 'OrderController@car_order_list');// 车护订单列表
        $route->post('car_order_info', 'OrderController@car_order_info');// 车护订单详情
        $route->post('tally_order', 'OrderController@tally_order');// 车护订单入账
        $route->post('car_order_number', 'OrderController@car_order_number');// 可操作车护订单数量
        $route->post('add_serve_order', 'OrderController@add_serve_order');// 购买服务
    });

    // 用户
    $route->group(['prefix' => 'user', 'middleware' => 'login'], function ($route) {
        $route->post('user_info', 'UserController@user_info');// 会员信息
        $route->post('add_user', 'UserController@add_user');// 会员开卡
        $route->post('user_sn', 'UserController@user_sn');// 会员卡号生成
        $route->post('user_rank', 'UserController@user_rank');// 会员等级
        $route->post('add_user_car', 'UserController@add_user_car');// 会员车辆添加
        $route->post('del_user_car', 'UserController@del_user_car');// 会员车辆删除
        $route->post('update_user_car', 'UserController@update_user_car');// 会员车辆修改
        $route->post('user_car', 'UserController@user_car');// 会员车辆列表
        $route->post('update_car_binding', 'UserController@update_car_binding');// 会员车辆更换绑定账号
        $route->post('add_user_address', 'UserController@add_user_address');// 会员添加地址
        $route->post('del_user_address', 'UserController@del_user_address');// 会员删除地址
        $route->post('update_user_address', 'UserController@update_user_address');// 会员修改地址
        $route->post('user_address', 'UserController@user_address');// 会员地址列表
        $route->post('user_coupon', 'UserController@user_coupon');// 会员可用优惠券信息
        $route->post('add_user_money', 'UserController@add_user_money');// 会员充值
        $route->post('user_account', 'UserController@user_account');// 会员流水明细
        $route->post('shop_serve_user', 'UserController@shop_serve_user');// 会员已购的优惠套餐列表
    });

    // 员工
    $route->group(['prefix' => 'admin', 'middleware' => 'login'], function ($route) {
        $route->post('admin_list', 'AdminController@admin_list');// 员工列表
        $route->post('admin_info', 'AdminController@admin_info');// 员工详情
        $route->post('add_admin', 'AdminController@add_admin');// 添加员工
        $route->post('admin_role_list', 'AdminController@admin_role_list');// 员工角色列表
        $route->post('del_admin', 'AdminController@del_admin');// 删除员工
        $route->post('update_admin', 'AdminController@update_admin');// 修改员工
        $route->post('update_password', 'AdminController@update_password');// 修改密码
        $route->post('statistics', 'AdminController@statistics');// 统计
        $route->post('master_worker', 'AdminController@master_worker');// 师傅列表
    });

    // 支付
    $route->group(['prefix' => 'pay', 'middleware' => 'login'], function ($route) {
        $route->post('pay_mode', 'PaymentController@pay_mode');// 支付方式列表
    });
});
