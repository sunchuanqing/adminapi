<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Order_action;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class OrderController extends Controller
{
    /**
     * 订单列表
     *
     * 订单类别：1奢饰品护理 2名车护理 3花艺 4优惠券 5优惠服务 6好货  order_type
     * 订单状态：1已预约 2洗护中 3洗护完工 4已完成 5已取消 6已下单 7制作中 8制作完成 order_status
     * 配送状态：1未揽件 2已揽件 3.已接收 4未发货 5后台显示已发货 客户端显示待收货 6已收货 7已退货 8到店自取 9发放账户  shipping_status
     * 支付状态：1未付款 2付款中 3已付款  pay_status
     * 配送方式：1自取 2快递 3同城上门 4账户  shipping_type
     * 评价状态：1未评价 2已评价   comment_status
     */
    public function order_list (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        if($admin['shop_id'] == 0){// 因为数据表admin_role中设计shop_id=0为管理员 需要传递shop_id才能查询
            if(empty($request->shop_id)) return status(40001, 'shop_id参数有误');
            $shop_id = $request->shop_id;
        }else{
            $shop_id = $admin['shop_id'];
        }
        if(empty($request->order_status)) return status(40002, 'order_status参数有误');
        if(empty($request->pay_status)) return status(40003, 'pay_status参数有误');
        $order_list = Order::where('shop_id', $shop_id)
            ->where('order_status', $request->order_status)
            ->where('pay_status', $request->pay_status)
            ->with(['order_goods' => function($query){
                $query->select('id', 'order_sn', 'goods_name', 'goods_img', 'goods_number');
            }])
            ->select(['id', 'order_sn', 'consignee', 'phone', 'best_time', 'created_at', 'postscript']);
        if(!empty($request->where_key)){// 判断是否筛选
            if(empty($request->where_value)) return status(40004, 'where_value参数有误');
            $order_list->where($request->where_key, 'like', '%'.request('where_value').'%');
        }
        if(!empty($request->order_by_key)){// 判断是否排序
            if(empty($request->order_by_value)) return status(40005, 'order_by_value参数有误');
            $order_list->orderBy($request->order_by_key, $request->order_by_value);
        }
        $info = $order_list->get();
        if(count($info) == 0) return status(404, '找不到数据');
        return status(200, 'success', $info);
    }

    /**
     * 订单详情
     *
     * 关联订单商品 订单预约信息表
     */
    public function order_info (Request $request){
        if(empty($request->order_sn)) return status(40001, 'order_sn参数有误');
        $order_info = Order::where('order_sn', $request->order_sn)
            ->with(['order_goods' => function($query){
                $query->select('id', 'order_sn', 'goods_sn', 'goods_name', 'goods_img', 'goods_number', 'make_price', 'attr_name', 'comment_status', 'status', 'to_buyer');
            }])
            ->with(['order_visit' => function($query){
                $query->select(['id', 'order_sn', 'visit_time', 'number', 'bless_name', 'bless_info', 'use_type']);
            }])
            ->select(['id', 'order_sn', 'order_status', 'shipping_status', 'pay_status', 'comment_status', 'consignee', 'country', 'province', 'city', 'district', 'street', 'address', 'phone', 'best_time', 'postscript', 'pay_name', 'goods_amount', 'shipping_fee', 'pay_points_money', 'coupon', 'order_amount', 'pay_time', 'shipping_time', 'shipping_type', 'to_buyer', 'created_at'])
            ->first();
        if(empty($order_info)) return status(404, '找不到数据');
        return status(200, 'success', $order_info);
    }


    /**
     * 订单接单
     *
     */
    public function take_order (Request $request){
        if(empty($request->order_sn)) return status(40001, '订单编号有误');
        $order = Order::where('order_sn', $request->order_sn)->first();
        if(empty($order)) return status(404, '找不到此订单');
        if(($order['order_status'] != 6) || ($order['pay_status'] != 3)) return status(40002, '订单操作有误');
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        DB::beginTransaction();
        try {
            // 1.修改订单状态为制作中
            $order->order_status = 7;
            $order->save();
            // 2.记录操作信息
            $order_action = new Order_action();
            $order_action->order_sn = $order['order_sn'];
            $order_action->action_user = '员工：'.$admin['phone'].'（'.$admin['name'].')';
            $order_action->order_status = 7;
            $order_action->shipping_status = $order['shipping_status'];
            $order_action->pay_status = 3;
            $order_action->action_note = '花艺接单成功';
            $order_action->save();
            DB::commit();
            return status(200, 'success');
        } catch (QueryException $ex) {
            DB::rollback();
            return status(400, '参数有误');
        }
    }

    /**
     * 订单制作完成接口
     *
     */
    public function done_order (Request $request){
        if(empty($request->order_sn)) return status(40001, '订单编号有误');
        $order = Order::where('order_sn', $request->order_sn)->first();
        if(empty($order)) return status(404, '找不到此订单');
        if(($order['order_status'] != 7) || ($order['pay_status'] != 3)) return status(40002, '订单操作有误');
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        DB::beginTransaction();
        try {
            // 1.修改订单状态为制作中
            $order->order_status = 8;
            $order->save();
            // 2.记录操作信息
            $order_action = new Order_action();
            $order_action->order_sn = $order['order_sn'];
            $order_action->action_user = '员工：'.$admin['phone'].'（'.$admin['name'].')';
            $order_action->order_status = 8;
            $order_action->shipping_status = $order['shipping_status'];
            $order_action->pay_status = 3;
            $order_action->action_note = '花艺制作完成';
            $order_action->save();
            DB::commit();
            return status(200, 'success');
        } catch (QueryException $ex) {
            DB::rollback();
            return status(400, '参数有误');
        }
    }


    /**
     * 开单查询会员接口
     *
     */
    public function user (Request $request){
        if(empty($request->phone)) return status(40001, 'phone参数有误');
    }
}
