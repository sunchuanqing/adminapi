<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Order_visit;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CarOrderController extends Controller
{
    /**
     * 车护已预约订单列表接口
     *
     * 预约单状态：1预约成功 2取消预约 3已过期 4已开单 5等待开单  car_status
     */
    public function car_make_list (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $car_make_list = Order_visit::join('orders', 'order_visits.order_sn', '=', 'orders.order_sn')
            ->where('orders.shop_id', $admin['shop_id'])
            ->where('order_visits.car_status', 1)
            ->orderBy('order_visits.visit_time', 'desc')
            ->select(['order_visits.id', 'orders.id as order_id', 'orders.order_sn', 'order_visits.user_name', 'order_visits.phone', 'order_visits.visit_time', 'order_visits.car_number', 'order_visits.car_status', 'order_visits.visit_info', 'order_visits.visit_info_name', 'orders.user_id']);
        if(!empty($request->user_name)){
            $car_make_list->where('order_visits.user_name', 'like', '%'.$request->user_name.'%');
        }
        if(!empty($request->phone)){
            $car_make_list->where('order_visits.phone', 'like', '%'.$request->phone.'%');
        }
        if(count($car_make_list->get()) == 0) return status(404, '没有预约信息');
        return status(200, 'success', $car_make_list->get());
    }


    /**
     * 车护稍后开单接口
     *
     * 预约单状态：1预约成功 2取消预约 3已过期 4已开单 5等待开单  car_status
     */
    public function car_await (Request $request){
        if(empty($request->id)) return status(40001, 'id参数有误');
        $order = Order_visit::where('car_status', 1)->find($request->id);
        if(empty($order)) return status(40002, '数据有误');
        $order->car_status = 5;
        $order->save();
        return status(200, '操作成功');
    }


    /**
     * 车护稍后开单列表接口
     *
     * 预约单状态：1预约成功 2取消预约 3已过期 4已开单 5等待开单  car_status
     */
    public function car_await_list (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $car_await_list = Order_visit::join('orders', 'order_visits.order_sn', '=', 'orders.order_sn')
            ->where('orders.shop_id', $admin['shop_id'])
            ->where('order_visits.car_status', 5)
            ->orderBy('order_visits.visit_time', 'desc')
            ->select(['order_visits.id', 'orders.id as order_id', 'orders.order_sn', 'order_visits.user_name', 'order_visits.phone', 'order_visits.visit_time', 'order_visits.car_number', 'order_visits.car_status', 'order_visits.visit_info', 'order_visits.visit_info_name', 'orders.user_id']);
        if(!empty($request->user_name)){
            $car_await_list->where('order_visits.user_name', 'like', '%'.$request->user_name.'%');
        }
        if(!empty($request->phone)){
            $car_await_list->where('order_visits.phone', 'like', '%'.$request->phone.'%');
        }
        if(count($car_await_list->get()) == 0) return status(404, '没有预约信息');
        return status(200, 'success', $car_await_list->get());
    }



    /**
     * 车护已过期列表接口
     *
     * 预约单状态：1预约成功 2取消预约 3已过期 4已开单 5等待开单  car_status
     */
    public function car_past_list (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $car_past_list = Order_visit::join('orders', 'order_visits.order_sn', '=', 'orders.order_sn')
            ->where('orders.shop_id', $admin['shop_id'])
            ->where('order_visits.car_status', 3)
            ->orderBy('order_visits.visit_time', 'desc')
            ->select(['order_visits.id', 'orders.id as order_id', 'orders.order_sn', 'order_visits.user_name', 'order_visits.phone', 'order_visits.visit_time', 'order_visits.car_number', 'order_visits.car_status', 'order_visits.visit_info', 'order_visits.visit_info_name', 'orders.user_id']);
        if(!empty($request->user_name)){
            $car_past_list->where('order_visits.user_name', 'like', '%'.$request->user_name.'%');
        }
        if(!empty($request->phone)){
            $car_past_list->where('order_visits.phone', 'like', '%'.$request->phone.'%');
        }
        if(count($car_past_list->get()) == 0) return status(404, '没有预约信息');
        return status(200, 'success', $car_past_list->get());
    }
}
