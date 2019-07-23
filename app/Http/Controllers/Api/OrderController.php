<?php

namespace App\Http\Controllers\Api;

use App\Models\Coupon_user;
use App\Models\Flower;
use App\Models\Order;
use App\Models\Order_action;
use App\Models\Order_good;
use App\Models\Order_visit;
use App\Models\Payment;
use App\Models\Price_list;
use App\Models\Price_list_user;
use App\Models\Price_type;
use App\Models\Shop_serve;
use App\Models\Shop_serve_user;
use App\Models\User_account;
use App\Models\User_car;
use App\User;
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
            ->orderBy('id', 'desc')
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
            // 1.修改订单状态为制作完成
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
     * 订单送出接口
     *
     * 配送状态：1未揽件 2已揽件 3.已接收 4未发货 5后台显示已发货 客户端显示待收货 6已收货 7已退货 8到店自取 9发放账户  shipping_status
     * 支付状态：1未付款 2付款中 3已付款  pay_status
     * 配送方式：1自取 2快递 3同城上门 4账户  shipping_type
     */
    public function send_order (Request $request){
        if(empty($request->order_sn)) return status(40001, '订单编号有误');
        $order = Order::where('order_sn', $request->order_sn)->first();
        if(empty($order)) return status(404, '找不到此订单');
        if(($order['order_status'] != 8) || ($order['pay_status'] != 3)) return status(40002, '订单操作有误');
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        DB::beginTransaction();
        try {
            // 1.判断订单物流是 到店自取 还是 送货上门
            if($order['shipping_type'] == 1){
                // 自取情况 此步骤完结订单
                $order->order_status = 4;
                $order->shipping_status = 6;
                $order->done_time = date('Y-m-d H:i:s', time());
                $order->save();
                // 记录操作信息
                $order_action = new Order_action();
                $order_action->order_sn = $order['order_sn'];
                $order_action->action_user = '员工：'.$admin['phone'].'（'.$admin['name'].')';
                $order_action->order_status = 4;
                $order_action->shipping_status = 6;
                $order_action->pay_status = 3;
                $order_action->action_note = '商品已交至顾客';
                $order_action->save();
            }else{
                // 上门情况 此步骤发货
                $order->shipping_status = 5;
                $order->save();
                // 记录操作信息
                $order_action = new Order_action();
                $order_action->order_sn = $order['order_sn'];
                $order_action->action_user = '员工：'.$admin['phone'].'（'.$admin['name'].')';
                $order_action->order_status = 8;
                $order_action->shipping_status = 5;
                $order_action->pay_status = 3;
                $order_action->action_note = '商品离店，已交至物流人员。';
                $order_action->save();
            }
            DB::commit();
            return status(200, '操作成功');
        } catch (QueryException $ex) {
            DB::rollback();
            return status(400, '参数有误');
        }
    }


    /**
     * 花艺开单接口
     *
     * 订单类别：1奢饰品护理 2名车护理 3花艺 4优惠券 5优惠服务 6好货  order_type
     * 配送方式：1自取 2快递 3同城上门 4账户  shipping_type
     * 根据phone判断是否为平台会员 不是会员默认注册 记录会员来源为此门店
     */
    public function add_flower_order (Request $request){
        if(empty($request->phone)) return status(40001, 'phone参数有误');
        // 开单管理员信息
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $order_sn = sn_26();
        if(User::where('phone', $request->phone)->count() == 1){// 断是否为平台会员
            $user = User::where('phone', $request->phone)->first();
        }else{
            // 不是会员默认注册
            if(empty($request->user_name)) return status(40002, 'user_name参数有误');
            $user = new User();
            $user->user_sn = user_sn();
            $user->user_name = $request->user_name;
            $user->phone = $request->phone;
            $user->photo = 'http://img.jiaranjituan.cn/photo.jpg';
            $user->source_msg = '花艺门店';
            $user->source_shop_id = $admin['shop_id'];
            $user->save();
            $user = User::where('phone', $request->phone)->first();
        }
        // 抓取此订单的花束 前端传递花束id 和购买数量number
        if(empty($request->flower_info)) return status(40003, 'flower_info参数设置有误');
        $flower_info = json_decode($request->flower_info, true);
        if(count($flower_info) == 0) return status(40004, '选择花束错误');
        $data_goods = array();// 订单商品
        $goods_money = 0;// 商品总价
        foreach ($flower_info as $k => $v){
            $flower = Flower::find($v['id']);
            $goods_money = $goods_money+$flower['price']*$v['number'];
            array_push($data_goods, ['order_sn' => $order_sn, 'goods_sn' => $flower->flower_sn, 'goods_name' => $flower->flower_name, 'goods_img' => $flower->flower_img, 'goods_number' => $v['number'], 'make_price' => $flower->price, 'attr_name' => '花艺', 'give_integral' => $flower->give_integral, 'rank_integral' => $flower->rank_integral, 'created_at' => date('Y-m-d H:i:s', time()), 'updated_at' => date('Y-m-d H:i:s', time())]);
        }
        // 判断是否使用优惠券
        $coupon_money = 0;// 优惠券金额
        if(!empty($request->coupon_sn)){
            $coupon = Coupon_user::where('coupon_sn', $request->coupon_sn)
                ->where('full_money', '<=' ,$goods_money)
                ->first();
            if(empty($coupon)) return status(40005, '优惠券不可用');
            $coupon_money = $coupon->money;// 优惠券金额
        }
        DB::beginTransaction();
        try {
            // 1.添加订单
            $order = new Order();
            $order->shop_id = $admin['shop_id'];
            $order->order_sn = $order_sn;
            $order->order_type = 3;
            $order->user_id = $user['id'];
            $order->order_status = 7;
            $order->pay_status = 3;
            if(empty($request->pay_id)) return status(40006, 'pay_id参数有误');
            $order->pay_id = $request->pay_id;
            $order->pay_name = Payment::find($request->pay_id)->pay_name;
            $order->pay_time = date('Y-m-d H:i:s', time());
            if(empty($request->shipping_type)) return status(40007, 'shipping_type参数有误');
            $order->shipping_type = $request->shipping_type;
            if($request->shipping_type == 1){
                $order->shipping_status = 8;
                $order->consignee = $user['user_name'];
                $order->phone = $user['phone'];
                $order->shipping_fee = 0;
                $order->order_amount = $goods_money-$coupon_money;
            }else{
                $order->shipping_status = 4;
                if(empty($request->address_user_name)) return status(40008, 'address_user_name参数有误');
                $order->consignee = $request->address_user_name;
                if(empty($request->address_user_phone)) return status(40009, 'address_user_phone参数有误');
                $order->phone = $request->address_user_phone;
                $order->country = 86;
                if(empty($request->province)) return status(40010, 'province参数有误');
                $order->province = $request->province;
                if(empty($request->city)) return status(40011, 'city参数有误');
                $order->city = $request->city;
                if(empty($request->district)) return status(40012, 'district参数有误');
                $order->district = $request->district;
                if(empty($request->address)) return status(40013, 'address参数有误');
                $order->address = $request->address;
                $order->shipping_fee = 10;
                $order->order_amount = $goods_money-$coupon_money+10;
            }
            $order->goods_amount = $goods_money;
            $order->pay_points = 0;
            $order->pay_points_money = 0;
            $order->coupon = $coupon_money;
            if(empty($request->visit_time)) return status(40014, 'visit_time参数有误');
            $order->best_time = $request->visit_time;
            $order->admin_id = $admin['id'];
            if(!empty($request->postscript))
                $order->postscript = $request->postscript;
            $order->save();
            // 2.写入订单商品
            DB::table('order_goods')->insert($data_goods);
            // 3.赠送积分 添加积分流水
            // 4.修改花束库存 和销量
            foreach ($flower_info as $k => $v){
                $flower = Flower::find($v['id']);
                $flower->flower_number = $flower->flower_number-$v['number'];
                $flower->sales = $flower->sales+$v['number'];
                $flower->virtual_sales = $flower->virtual_sales+$v['number'];
                $flower->save();
            }
            // 5.写入预约信息
            $order_visit = new Order_visit();
            $order_visit->order_sn = $order_sn;
            $order_visit->visit_time = $request->visit_time;
            if(!empty($request->bless_name))
                $order_visit->bless_name = $request->bless_name;
            if(!empty($request->bless_info))
                $order_visit->bless_info = $request->bless_info;
            if(!empty($request->use_type))
                $order_visit->use_type = $request->use_type;
            $order_visit->save();
            // 6.优惠券核销
            if(!empty($request->coupon_sn)){
                $coupon_melt = Coupon_user::where('coupon_sn', $request->coupon_sn)->first();
                $coupon_melt->status = 2;
                $coupon_melt->coupon_order = $order_sn;
                $coupon_melt->save();
            }
            // 7.判断是否为余额支付 是 扣卡
            if($request->pay_id == 1){
                if($user['user_money'] < $order->order_amount) return status(40006, '余额不足');
                $user->user_money = $user['user_money']-$order->order_amount;
                $user->save();
                $user_account = new User_account();
                $user_account->account_sn = sn_20();
                $user_account->order_sn = $order_sn;
                $user_account->user_id = $user['id'];
                $user_account->shop_id = $admin['shop_id'];
                $user_account->money_change = -$order->order_amount;
                $user_account->money = $user->user_money;
                $user_account->change_name = '订单支付';
                $user_account->change_desc = '门店员工开单，自动扣除会员卡金额。';
                $user_account->save();
            }
            // 8.写入订单操作状态
            $order_action = new Order_action();
            $order_action->order_sn = $order_sn;
            $order_action->action_user = '员工：'.$admin['phone'].'（'.$admin['name'].')';
            $order_action->order_status = 7;
            if($request->shipping_type == 1){
                $order_action->shipping_status = 8;
            }else{
                $order_action->shipping_status = 4;
            }
            $order_action->pay_status = 3;
            $order_action->action_note = '员工开单';
            $order_action->save();
            DB::commit();
            return status(200, 'success', ['order_sn' => $order_sn]);
        } catch (QueryException $ex) {
            DB::rollback();
            return status(400, '参数有误');
        }
    }


    /**
     * 花束列表接口
     *
     * 花艺开单需要选择花束
     * 花束状态：1正常 2下架   status
     */
    public function flower_list (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $flower = Flower::where('shop_id', $admin['shop_id'])
            ->where('status', 1)
            ->orderBy('sort', 'desc')
            ->orderBy('id', 'desc')
            ->select(['id', 'flower_sn', 'flower_name', 'flower_img_thumb', 'flower_number', 'price', 'flower_brief', 'virtual_sales'])
            ->get();
        return status(200, 'success', $flower);
    }


    /**
     * 今日代办接口
     *
     * 订单类别：1奢饰品护理 2名车护理 3花艺 4优惠券 5优惠服务 6好货  order_type
     * 配送方式：1自取 2快递 3同城上门 4账户  shipping_type
     * 根据phone判断是否为平台会员 不是会员默认注册 记录会员来源为此门店
     */
    public function today_order (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $order_list = Order::where('shop_id', $admin['shop_id'])
            ->whereIn('order_status', [7, 8])
            ->whereIn('shipping_status', [8, 4])
            ->where('pay_status', 3)
            ->with(['order_goods' => function($query){
                $query->select('id', 'order_sn', 'goods_name', 'goods_img', 'goods_number');
            }])
            ->where('best_time', 'like', '%'.date('Y-m-d', time()).'%')
            ->orderBy('id', 'desc')
            ->select(['id', 'order_sn', 'consignee', 'phone', 'best_time', 'created_at', 'postscript', 'order_status']);
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
     * 车护开单接口
     *
     * 订单类别：1奢饰品护理 2名车护理 3花艺 4优惠券 5优惠服务 6好货  order_type
     * 配送方式：1自取 2快递 3同城上门 4账户  shipping_type
     * 根据phone判断是否为平台会员 不是会员默认注册 记录会员来源为此门店
     */
    public function add_car_order (Request $request){
        if(empty($request->phone)) return status(40001, 'phone参数有误');
        // 开单管理员信息
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $order_sn = sn_26();
        if(User::where('phone', $request->phone)->count() == 1){// 断是否为平台会员
            $user = User::where('phone', $request->phone)->first();
        }else{
            // 不是会员默认注册
            if(empty($request->user_name)) return status(40002, 'user_name参数有误');
            $user = new User();
            $user->user_sn = user_sn();
            $user->user_name = $request->user_name;
            $user->phone = $request->phone;
            $user->photo = 'http://img.jiaranjituan.cn/photo.jpg';
            $user->source_msg = '车护门店';
            $user->source_shop_id = $admin['shop_id'];
            $user->save();
            $user = User::where('phone', $request->phone)->first();
        }
        // 判断此车是否绑定会员
        if(empty($request->car_province)) return status(40002, 'car_province参数有误');
        if(empty($request->car_city)) return status(40003, 'car_city参数有误');
        if(empty($request->car_number)) return status(40004, 'car_number参数有误');
        if(User_car::where('plate_number', $request->car_province.$request->car_city.$request->car_number)->count() == 0){
            $user_car = new User_car();
            $user_car->user_id = $user->id;
            $user_car->car_province = $request->car_province;
            $user_car->car_city = $request->car_city;
            $user_car->car_number = $request->car_number;
            $user_car->plate_number = $request->car_province.$request->car_city.$request->car_number;
            $user_car->car_info = $request->car_info;
            $user_car->car_colour = $request->car_colour;
            $user_car->car_type = $request->car_type;
            $user_car->remark = $request->remark;
            $user_car->save();
        }
        $user_car_info = User_car::where('plate_number', $request->car_province.$request->car_city.$request->car_number)->first();
        // 获取此订单选择的服务项目
        if(empty($request->car_info)) return status(40005, 'car_info参数有误');
        $car_info = json_decode($request->car_info, true);
        if(count($car_info) == 0) return status(40006, '选择项目错误');
        $data_goods = array();// 订单商品
        $goods_money = 0;// 商品总价
        foreach ($car_info as $k => $v){
            $price_list = Price_list::find($v['id']);
            $goods_money = $goods_money+$price_list['price']*$v['number'];
            if($price_list['price_list_type_id'] == 0){
                array_push($data_goods, ['order_sn' => $order_sn, 'goods_sn' => $price_list->price_sn, 'goods_name' => $price_list->price_list_name, 'goods_number' => $v['number'], 'make_price' => $price_list->price, 'attr_name' => '名车护理', 'status' => null, 'created_at' => date('Y-m-d H:i:s', time()), 'updated_at' => date('Y-m-d H:i:s', time())]);
            }else{
                array_push($data_goods, ['order_sn' => $order_sn, 'goods_sn' => $price_list->price_sn, 'goods_name' => $price_list->price_list_name, 'goods_number' => $v['number'], 'make_price' => $price_list->price, 'attr_name' => '名车护理', 'status' => 4, 'created_at' => date('Y-m-d H:i:s', time()), 'updated_at' => date('Y-m-d H:i:s', time())]);
            }
        }
        DB::beginTransaction();
        try {
            // 1.添加订单
            $order = new Order();
            $order->shop_id = $admin['shop_id'];
            $order->order_sn = $order_sn;
            $order->order_type = 2;
            $order->user_id = $user['id'];
            $order->order_status = 2;
            $order->pay_status = 1;
            $order->shipping_type = 1;
            $order->shipping_status = 8;
            $order->consignee = $user['user_name'];
            $order->phone = $user['phone'];
            $order->shipping_fee = 0;
            // 判断是否使用优惠服务
            if(!empty($request->user_server_sn)){
                $server = Shop_serve_user::where('user_id', $user['id'])
                    ->where('status', 1)
                    ->where('pay_status', 3)
                    ->where('user_serve_sn', $request->user_server_sn)
                    ->first();
                if(!empty($server)){
                    $server->status = 2;
                    $server->save();
                    $order_amount = $goods_money-$server['market_price'];
                    $order->server_user_sn = $request->user_server_sn;
                    $order->server = $server['market_price'];
                    $order->serve_id = $server->shop_serve_id;
                    $order->serve_user_id = $server->id;
                }else{
                    $price_list_user = Price_list_user::where('user_id', $user['id'])
                        ->where('status', 1)
                        ->where('pay_status', 3)
                        ->where('price_list_sn', $request->user_server_sn)
                        ->first();
                    if(empty($price_list_user)) return status(40007, '优惠服务不可用');
                    $price_list_user->status = 2;
                    $price_list_user->save();
                    $order_amount = $goods_money-$price_list_user['price_list_money'];
                    $order->server_user_sn = $request->user_server_sn;
                    $order->server = $price_list_user['price_list_money'];
                    $order->price_list_user_id = $price_list_user->id;
                }
            }else{
                if(!empty($request->serve_id)){
                    $server = Shop_serve::find($request->serve_id);
                    $order_amount = $goods_money-($server['market_price']-$server['shop_price']);
                    $order->server = $server['market_price']-$server['shop_price'];
                    $order->serve_id = $request->serve_id;
                }else{
                    $order_amount = $goods_money;
                }
            }
            $order->order_amount = $order_amount;
            $order->goods_amount = $goods_money;
            $order->pay_points = 0;
            $order->pay_points_money = 0;
            $order->coupon = 0;
            $order->admin_id = $admin['id'];
            $order->user_car_id = $user_car_info->id;
            $order->save();
            // 2.写入订单商品
            DB::table('order_goods')->insert($data_goods);
            // 3.赠送积分 添加积分流水
            // 8.写入订单操作状态
            $order_action = new Order_action();
            $order_action->order_sn = $order_sn;
            $order_action->action_user = '员工：'.$admin['phone'].'（'.$admin['name'].')';
            $order_action->order_status = 2;
            $order_action->shipping_status = 8;
            $order_action->pay_status = 1;
            $order_action->action_note = '员工开单';
            $order_action->save();
            DB::commit();
            return status(200, 'success', ['order_sn' => $order_sn]);
        } catch (QueryException $ex) {
            DB::rollback();
            return status(400, '参数有误');
        }
    }


    /**
     * 车护订单项目修改接口
     *
     */
    public function update_order_serve (Request $request){
        if(empty($request->order_sn)) return status(40001, 'order_sn参数有误');
        if(empty($request->goods_info)) return status(40002, 'goods_info参数有误');
        DB::beginTransaction();
        try {
            $goods_info = json_decode($request->goods_info, true);
            $order = Order::where('order_sn', $request->order_sn)->first();
            $change_money = 0;// 记录订单变动的价格
            foreach ($goods_info as $k => $v){
                $price_list = Price_list::where('price_sn', $v['goods_sn'])->first();
                if(empty($price_list)) return status(40003, '价目表不存在');
                if($v['id'] == 0){// 0代表添加商品
                    if($v['number'] <= 0) return status(40004, '商品数量不正确');
                    $order_goods = new Order_good();
                    $order_goods->order_sn = $request->order_sn;
                    $order_goods->goods_sn = $v['goods_sn'];
                    $order_goods->goods_name = $price_list->price_list_name;
                    $order_goods->goods_number = $v['number'];
                    $order_goods->make_price = $price_list->price;
                    $order_goods->attr_name = '名车护理';
                    if($price_list['price_list_type_id'] == 0){
                        $order_goods->status = null;
                    }else{
                        $order_goods->status = 4;
                    }
                    $order_goods->save();
                    $change_money = $change_money+$price_list->price*$v['number'];
                }else{// 修改
                    $order_goods = Order_good::find($v['id']);
                    if(empty($order_goods)) return status(40005, '订单商品id不正确');
                    if($order_goods['goods_number'] < $v['number']){// 数量增加
                        if($order_goods['status'] != null) return status(40008, '商品不可修改');
                        $change_money = $change_money+$price_list->price*($v['number']-$order_goods['goods_number']);
                        $order_goods->goods_number = $v['number'];
                        $order_goods->save();
                    }else if($order_goods['goods_number'] > $v['number']){// 数量减少
                        if($v['number'] > 0){
                            if($order_goods['status'] != null) return status(40007, '商品不可修改');
                            $change_money = $change_money+$price_list->price*($v['number']-$order_goods['goods_number']);
                            $order_goods->goods_number = $v['number'];
                            $order_goods->save();
                        }else{// 删除
                            if($order_goods['status'] != null) return status(40006, '商品不可删除');
                            $change_money = $change_money-$price_list->price*$order_goods['goods_number'];
                            $order_goods->delete();
                        }
                    }else{
                        return status(40009, '商品数量有误');
                    }
                }
            }
            $order->goods_amount = $order->goods_amount+$change_money;
            $order->order_amount = $order->order_amount+$change_money;
            $order->save();
            DB::commit();
            return status(200, '修改成功');
        } catch (QueryException $ex) {
            DB::rollback();
            return status(400, '参数有误');
        }
    }


    /**
     * 价目表分类接口
     *
     */
    public function price_list_type (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $price_type = Price_type::where('shop_id', $admin['shop_id'])->select(['id', 'name'])->where('parent_id', null)->get();
        $ss = $this->select_children($price_type);
        return status(200, 'success', $ss);
    }


    /**
     * 获取子类价目表分类发布方法
     *
     */
    private function select_children($parent){
        foreach ($parent as $k => $v){
            $children = Price_type::where('parent_id', $v['id'])->select(['id', 'name'])->get();
            $parent[$k]['children'] = $children;
            if(count($children) > 0){
                $this->select_children($children);
            }
        }
        return $parent;
    }


    /**
     * 价目表接口
     *
     */
    public function price_list (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $price_list = Price_list::where('price_list_type_id', '>', 0)
            ->where('shop_id', $admin['shop_id'])
            ->select(['id', 'price_list_name', 'price', 'sell_money', 'job_money']);
        if(!empty($request->name)){
            $price_list->where('price_list_name', 'like', '%'.$request->name.'%');
        }
        if(!empty($request->type)){
            $price_list->where('price_list_type_id', $request->type);
        }
        $info = $price_list->get();
        return status(200, 'success', $info);
    }


    /**
     * 优惠套餐列表接口
     *
     */
    public function set_meal_list (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $serve = Shop_serve::where('shop_id', $admin['shop_id'])
            ->where('is_on_sale', 1)
            ->select(['id', 'serve_sn', 'serve_name', 'shop_price', 'serve_item']);
        if(!empty($request->name)){
            $serve->where('serve_name', 'like', '%'.$request->name.'%');
        }
        $info = $serve->get();
        return status(200, 'success', $info);
    }


    /**
     * 车护商品列表接口
     *
     */
    public function car_goods (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $car_goods = Price_list::where('shop_id', $admin['shop_id'])
            ->where('price_list_type_id', 0)
            ->select(['id', 'price_list_name', 'price', 'sell_money', 'job_money'])
            ->get();
        return status(200, 'success', $car_goods);
    }


    /**
     * 服务项目列表接口
     *
     * 洗护状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工   ststus
     */
    public function server_list (Request $request){
        if(empty($request->status)) return status(40001, 'status参数有误');
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $order = Order_good::join('orders', 'order_goods.order_sn', '=', 'orders.order_sn')
            ->join('user_cars', 'orders.user_car_id', '=', 'user_cars.id')
            ->select(['order_goods.id', 'orders.order_sn', 'orders.created_at', 'user_cars.car_province', 'user_cars.car_city', 'user_cars.car_number', 'user_cars.car_info', 'order_goods.goods_name'])
            ->where('orders.shop_id', '=', $admin['shop_id'])
            ->where('order_goods.status', '=', $request->status);
        if(!empty($request->time)){
            $order->whereDate('orders.created_at', $request->time);
        }
        if(!empty($request->order_sn)){
            $order->where('orders.order_sn', 'like', '%'.$request->order_sn.'%');
        }
        $data = $order->get();
        if(count($data) == 0) return status(404, '没有数据');
        return status(200, 'success', $data);
    }


    /**
     * 服务项目去施工接口
     *
     * 洗护状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工   ststus
     */
    public function to_work (Request $request){
        if(empty($request->id)) return status(40001, 'id参数有误');
        if(empty($request->work_id)) return status(40002, 'work_id参数有误');
        $order = Order_good::find($request->id);
        if($order->status != 4) return status(40003, '操作有误');
        $order->status = 5;
        $order->work_id = $request->work_id;
        $order->save();
        return status(200, '操作成功');
    }



    /**
     * 服务项目完工接口
     *
     * 洗护状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工   ststus
     * 订单状态：1已预约 2洗护中 3洗护完工 4已完成 5已取消 6已下单 7制作中 8制作完成   order_status
     * 当所有项目都完成时  订单状态改变为 3洗护完成
     */
    public function work_ok (Request $request){
        if(empty($request->id)) return status(40001, 'id参数有误');
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $order = Order_good::find($request->id);
        if($order->status != 5) return status(40002, '操作有误');
        if($order->work_id != $request->work_id){
            $order->work_id = $request->work_id;
        }
        $order->status = 6;
        $order->save();
        if(Order_good::where('order_sn', $order->order_sn)->where('status', '!=', 6)->count() == 0){
            $order = Order::where('order_sn', $order->order_sn)->first();
            $order->order_status = 3;
            $order->save();
            $order_action = new Order_action();
            $order_action->order_sn = $order->order_sn;
            $order_action->action_user = '员工：'.$admin['phone'].'（'.$admin['name'].')';
            $order_action->order_status = 3;
            $order_action->shipping_status = 8;
            $order_action->pay_status = 1;
            $order_action->action_note = '员工施工完成';
            $order_action->save();
        }
        return status(200, '操作成功');
    }


    /**
     * 车护订单列表接口
     *
     * 洗护状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工   ststus
     */
    public function car_order_list (Request $request){
        if(empty($request->order_status)) return status(40001, 'order_status参数有误');
        $order_status = json_decode($request->order_status, true);
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $order = Order::where('shop_id', $admin['shop_id'])
            ->join('user_cars', 'orders.user_car_id', '=', 'user_cars.id')
            ->select(['orders.id', 'orders.order_sn', 'orders.created_at', 'user_cars.car_province', 'user_cars.car_city', 'user_cars.car_number', 'user_cars.car_info'])
            ->whereIn('orders.order_status', $order_status)
            ->with(['order_goods' => function($query){
                $query->select('order_sn', 'goods_name', 'goods_number', 'status');
            }]);
        if(!empty($request->time)){
            $order->whereDate('orders.created_at', $request->time);
        }
        if(!empty($request->order_sn)){
            $order->where('orders.order_sn', 'like', '%'.$request->order_sn.'%');
        }
        $data = $order->get();
        if(count($data) == 0) return status(404, '没有数据');
        return status(200, 'success', $data);
    }


    /**
     * 车护订单详情接口
     *
     * 洗护状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工   ststus
     */
    public function car_order_info (Request $request){
        if(empty($request->order_id)) return status(40001, 'order_id参数有误');
        $order_info = Order::with(['order_goods' => function($query){
                $query->select('id', 'order_sn', 'goods_sn', 'goods_name', 'goods_number', 'make_price', 'status', 'to_buyer');
            }])
            ->join('user_cars', 'orders.user_car_id', '=', 'user_cars.id')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->join('user_ranks', 'users.rank_id', '=', 'user_ranks.id')
            ->select(['orders.id', 'orders.order_sn', 'orders.goods_amount', 'orders.coupon', 'orders.server', 'orders.order_amount', 'orders.created_at', 'orders.done_time', 'orders.pay_time', 'orders.pay_id', 'orders.pay_name', 'user_cars.car_province', 'user_cars.car_city', 'user_cars.car_number', 'user_cars.car_info', 'user_cars.car_colour', 'user_cars.remark', 'users.user_sn', 'users.user_name', 'users.sex', 'users.phone', 'users.user_money', 'user_ranks.name'])
            ->find($request->order_id);
        if(empty($order_info)) return status(404, '找不到数据');
        return status(200, 'success', $order_info);
    }


    /**
     * 车护订单入账接口
     *
     * 洗护状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工   ststus
     */
    public function tally_order (Request $request){
        if(empty($request->id)) return status(40001, 'id参数有误');
        if(empty($request->pay_id)) return status(40002, 'pay_id参数有误');
        DB::beginTransaction();
        try {
            $pay = Payment::find($request->pay_id);
            $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
            $order = Order::find($request->id);
            if($order->order_status != 3) return status(40003, '订单操作有误');
            $order->order_status = 4;
            $order->shipping_status = 6;
            $order->pay_status = 3;
            $order->pay_id = $request->pay_id;
            $order->pay_name = $pay->pay_name;
            if(!empty($request->coupon_id)){
                if($order->serve_id == null){
                    $order_goods_coupon = $order['order_amount'];
                }else{
                    $order_goods_coupon = $order['goods_amount'] - Shop_serve::find($order['serve_id'])->shop_price;
                }
                $coupon = Coupon_user::where('full_money', '<=' ,$order_goods_coupon)->find($request->coupon_id);
                if(empty($coupon)) return status(40004, '优惠券不可用');
                $coupon->status = 2;
                $coupon->coupon_order = $order->order_sn;
                $coupon->save();
                $order->coupon = $coupon->money;
                $order->order_amount = $order->order_amount-$coupon->money;
            }
            $order->pay_time = date('Y-m-d H:i:s', time());
            $order->shipping_time = date('Y-m-d H:i:s', time());
            $order->done_time = date('Y-m-d H:i:s', time());
            $order->save();
            // 余额付款扣卡
            if($request->pay_id ==1){
                $user = User::find($order['user_id']);
                if($user->user_money < $order->order_amount) return status(40004, '账户余额不足');
                $user->user_money = $user->user_money-$order->order_amount;
                $user->save();
                // 记录余额交易流水
                $user_account = new User_account();
                $user_account->account_sn = sn_20();
                $user_account->order_sn = $order->order_sn;
                $user_account->user_id = $order->user_id;
                $user_account->money_change = -$order->order_amount;
                $user_account->money = $user->user_money;
                $user_account->change_name = '订单支付';
                $user_account->change_desc = '余额主动扣卡';
                $user_account->shop_id = $admin['shop_id'];
                $user_account->save();
            }
            // 记录订单操作
            $order_action = new Order_action();
            $order_action->order_sn = $order->order_sn;
            $order_action->action_user = '员工：'.$admin['phone'].'（'.$admin['name'].')';
            $order_action->order_status = 4;
            $order_action->shipping_status = 6;
            $order_action->pay_status = 3;
            $order_action->action_note = '客户支付完成，车辆已取走。';
            $order_action->save();
            return status(200, '操作成功');
            DB::commit();
            return status(200, '修改成功');
        } catch (QueryException $ex) {
            DB::rollback();
            return status(400, '参数有误');
        }
    }


    /**
     * 可操作车护订单数量接口
     *
     * 洗护状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工   ststus
     */
    public function car_order_number (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        // 待施工
        $to_work_number = Order_good::join('orders', 'order_goods.order_sn', '=', 'orders.order_sn')
            ->where('orders.shop_id', $admin['shop_id'])
            ->where('order_goods.status', 4)
            ->count();
        // 施工中
        $work_ok_number = Order_good::join('orders', 'order_goods.order_sn', '=', 'orders.order_sn')
            ->where('orders.shop_id', $admin['shop_id'])
            ->where('order_goods.status', 5)
            ->count();
        // 待入账
        $tally_order_number = Order::where('shop_id', $admin['shop_id'])->where('order_status', 3)->count();
        $info = [
            'to_work_number' => $to_work_number,
            'work_ok_number' => $work_ok_number,
            'tally_order_number' => $tally_order_number,
        ];
        return status(200, 'success', $info);
    }


    /**
     * 购买服务接口
     *
     * 洗护状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工   ststus
     * 服务类别：1套餐 2价目表
     */
    public function add_serve_order (Request $request){
        if(empty($request->serve_type)) return status(40001, 'serve_type参数有误');
        if(empty($request->serve_sn)) return status(40002, 'serve_sn参数有误');
        if(empty($request->number)) return status(40003, 'number参数有误');
        if(empty($request->user_id)) return status(40004, 'user_id参数有误');
        if(empty($request->pay_id)) return status(40005, 'pay_id参数有误');
        if(empty($request->order_amount)) return status(40006, 'order_amount参数有误');
        if(empty($request->end_time)) return status(40007, 'end_time参数有误');
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $user = User::find($request->user_id);
        $pay = Payment::find($request->pay_id);
        if(empty($user)) return status(40008, '用户不存在');
        if($request->serve_type == 1){
            $serve = Shop_serve::where('serve_sn', $request->serve_sn)->first();
            $goods_sn = $serve['serve_sn'];
            $goods_name = $serve['serve_name'];
            $goods_img = $serve['serve_img'];
            $market_price = $serve['market_price'];
        }else if($request->serve_type == 2){
            $serve = Price_list::where('price_sn', $request->serve_sn)->first();
            $goods_sn = $serve['price_sn'];
            $goods_name = $serve['price_list_name'];
            $goods_img = '';
            $market_price = 0;
        }else{
            return status(40009, 'serve_type参数有误');
        }
        if(empty($serve)) return status(40010, '服务信息不正确');
        DB::beginTransaction();
        try {
            $order_sn = sn_26();
            // 1.添加订单
            $order = new Order();
            $order->order_sn = $order_sn;
            $order->order_type = 5;
            $order->user_id = $user['id'];
            $order->shop_id = $admin['shop_id'];
            $order->order_status = 4;
            $order->shipping_type = 4;
            $order->shipping_status = 9;
            $order->pay_status = 3;
            $order->consignee = $user['user_name'];
            $order->phone = $user['phone'];
            $order->pay_id = $request->pay_id;
            $order->pay_name = $pay['pay_name'];
            $order->goods_amount = $request->order_amount;
            $order->shipping_fee = 0;
            $order->coupon = 0;
            $order->order_amount = $request->order_amount;
            $order->pay_time = date("Y-m-d H:i:s", time());
            $order->shipping_time = date("Y-m-d H:i:s", time());
            $order->admin_id = $admin['id'];
            $order->done_time = date("Y-m-d H:i:s", time());
            $order->save();
            // 2.写入订单商品
            $order_goods = new Order_good();
            $order_goods->order_sn = $order_sn;
            $order_goods->goods_sn = $goods_sn;
            $order_goods->goods_name = $goods_name;
            $order_goods->goods_img = $goods_img;
            $order_goods->goods_number = $request->number;
            $order_goods->market_price = $market_price;
            $order_goods->make_price = $request->order_amount/$request->number;
            $order_goods->attr_name = '门店服务';
            $order_goods->save();
            // 3.如果是余额支付 自动扣卡
            if($request->pay_id ==1){
                if($user->user_money < $request->order_amount) return status(40004, '账户余额不足');
                $user->user_money = $user->user_money-$request->order_amount;
                $user->save();
                // 记录余额交易流水
                $user_account = new User_account();
                $user_account->account_sn = sn_20();
                $user_account->order_sn = $order_sn;
                $user_account->user_id = $user['id'];
                $user_account->money_change = -$request->order_amount;
                $user_account->money = $user->user_money;
                $user_account->change_name = '订单支付';
                $user_account->change_desc = '余额主动扣卡';
                $user_account->shop_id = $admin['shop_id'];
                $user_account->save();
            }
            // 4.商品写入用户账户
            if($request->serve_type == 1){
                for ($i = 1; $i <= $request->number; $i++){
                    $shop_serve_user = new Shop_serve_user();
                    $shop_serve_user->user_serve_sn = sn_26();
                    $shop_serve_user->shop_serve_id = $serve['id'];
                    $shop_serve_user->user_id = $user['id'];
                    $shop_serve_user->order_sn = $order_sn;
                    $shop_serve_user->serve_name = $serve['serve_name'];
                    $shop_serve_user->serve_brief = $serve['serve_brief'];
                    $shop_serve_user->serve_item = $serve['serve_item'];
                    $shop_serve_user->serve_img = $serve['serve_img'];
                    $shop_serve_user->serve_start_time = date("Y-m-d", time());
                    $shop_serve_user->serve_end_time = $request->end_time;
                    $shop_serve_user->valid_except = $serve['valid_except'];
                    $shop_serve_user->market_price = $serve['market_price'];
                    $shop_serve_user->make_price = $serve['shop_price'];
                    $shop_serve_user->usable_range = $serve['usable_range'];
                    $shop_serve_user->else_msg = $serve['else_msg'];
                    $shop_serve_user->bc_msg = $serve['bc_msg'];
                    $shop_serve_user->status = 1;
                    $shop_serve_user->pay_status = 3;
                    $shop_serve_user->save();
                }
            }else if($request->serve_type == 2){
                for ($i = 1; $i <= $request->number; $i++){
                    $price_list_user = new Price_list_user();
                    $price_list_user->user_id = $user['id'];
                    $price_list_user->price_list_id = $serve['id'];
                    $price_list_user->shop_id = $admin['shop_id'];
                    $price_list_user->admin_id = $admin['id'];
                    $price_list_user->price_list_sn = sn_26();
                    $price_list_user->order_sn = $order_sn;
                    $price_list_user->price_list_name = $serve['price_list_name'];
                    $price_list_user->price_list_money = $serve['price'];
                    $price_list_user->end_time = $request->end_time;
                    $price_list_user->status = 1;
                    $price_list_user->pay_status = 3;
                    $price_list_user->save();
                }
            }
            // 5.写入订单操作状态
            $order_action = new Order_action();
            $order_action->order_sn = $order_sn;
            $order_action->action_user = '员工：'.$admin['phone'].'（'.$admin['name'].')';
            $order_action->order_status = 4;
            $order_action->shipping_status = 9;
            $order_action->pay_status = 3;
            $order_action->action_note = '员工销售服务';
            $order_action->save();
            DB::commit();
            return status(200, 'success', ['order_sn' => $order_sn]);
        } catch (QueryException $ex) {
            DB::rollback();
            return status(400, '参数有误');
        }
    }
}
