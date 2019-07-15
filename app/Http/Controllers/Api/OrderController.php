<?php

namespace App\Http\Controllers\Api;

use App\Models\Coupon_user;
use App\Models\Flower;
use App\Models\Order;
use App\Models\Order_action;
use App\Models\Order_visit;
use App\Models\Payment;
use App\Models\Price_list;
use App\Models\Price_type;
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
                ->where('user_id', $user['id'])
                ->whereIn('pay_status', [0, 3])
                ->where('status', 1)
                ->where('full_money', '<=' ,$goods_money)
                ->where('coupon_start_time', '<=', date('Y-m-d', time()))
                ->where('coupon_end_time', '>=', date('Y-m-d', time()))
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
        // 获取此订单选择的服务项目
        if(empty($request->car_info)) return status(40005, 'car_info参数有误');
        $car_info = json_decode($request->car_info, true);
        if(count($car_info) == 0) return status(40006, '选择项目错误');
        $data_goods = array();// 订单商品
        $goods_money = 0;// 商品总价
        foreach ($car_info as $k => $v){
            $price_list = Price_list::find($v['id']);
            $goods_money = $goods_money+$price_list['price'];
            if($price_list['price_list_type_id'] == 0){
                array_push($data_goods, ['order_sn' => $order_sn, 'goods_sn' => $price_list->price_sn, 'goods_name' => $price_list->price_list_name, 'goods_number' => 1, 'make_price' => $price_list->price, 'attr_name' => '名车护理', 'status' => null, 'created_at' => date('Y-m-d H:i:s', time()), 'updated_at' => date('Y-m-d H:i:s', time())]);
            }else{
                array_push($data_goods, ['order_sn' => $order_sn, 'goods_sn' => $price_list->price_sn, 'goods_name' => $price_list->price_list_name, 'goods_number' => 1, 'make_price' => $price_list->price, 'attr_name' => '名车护理', 'status' => 4, 'created_at' => date('Y-m-d H:i:s', time()), 'updated_at' => date('Y-m-d H:i:s', time())]);
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
            $order->order_amount = $goods_money;
            $order->goods_amount = $goods_money;
            $order->pay_points = 0;
            $order->pay_points_money = 0;
            $order->coupon = 0;
            $order->admin_id = $admin['id'];
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
     * 价目表分类接口
     *
     */
    public function price_list_type (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $price_type = Price_type::where('shop_id', $admin['shop_id'])->select(['id', 'name']);
        if(empty($request->parent_id)){
            $price_type->where('parent_id', null);
        }else{
            $price_type->where('parent_id', $request->parent_id);
        }
        $info = $price_type->get();

        return status(200, 'success', $info);
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
        if(!empty($request->type)){
            $price_list->where('price_list_type_id', $request->type);
        }
        $info = $price_list->get();
        return status(200, 'success', $info);
    }
}
