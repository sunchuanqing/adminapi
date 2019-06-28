<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;

class OrderController extends Controller
{
    /**
     * 查询订单列表
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
        if(empty($request->shipping_status)) return status(40002, 'shipping_status参数有误');
        if(empty($request->pay_status)) return status(40002, 'pay_status参数有误');
        $order_list = Order::where('shop_id', $shop_id)
            ->where('order_status', $request->order_status)
            ->where('shipping_status', $request->shipping_status)
            ->where('pay_status', $request->pay_status)
            ->with(['order_goods' => function($query){
                $query->select('id', 'order_sn', 'goods_name', 'goods_img', 'goods_number');
            }])
            ->select(['id', 'order_sn', 'consignee', 'phone', 'best_time', 'created_at', 'postscript'])
            ->get();
        if(empty($order_list)) return status(404, '找不到数据');
        return status(200, 'success', $order_list);
    }

    /**
     * 查询订单详情列表
     *
     * 订单类别：1奢饰品护理 2名车护理 3花艺 4优惠券 5优惠服务 6好货  order_type
     * 订单状态：1已预约 2洗护中 3洗护完工 4已完成 5已取消 6已下单 7制作中 8制作完成 order_status
     * 配送状态：1未揽件 2已揽件 3.已接收 4未发货 5后台显示已发货 客户端显示待收货 6已收货 7已退货 8到店自取 9发放账户  shipping_status
     * 支付状态：1未付款 2付款中 3已付款  pay_status
     * 配送方式：1自取 2快递 3同城上门 4账户  shipping_type
     * 评价状态：1未评价 2已评价   comment_status
     */
    public function order_info (Request $request){
        if(empty($request->order_sn)) return status(40001, 'order_sn参数有误');
        $order_info = Order::where('order_sn', $request->order_sn)
            ->with(['order_goods' => function($query){
                $query->select('id', 'order_sn', 'goods_name', 'goods_img', 'goods_number');
            }])
            ->select(['id', 'order_sn', 'consignee', 'phone', 'best_time', 'created_at', 'postscript'])
            ->first();
        if(empty($order_list)) return status(404, '找不到数据');
        return status(200, 'success', $order_info);
    }
}
