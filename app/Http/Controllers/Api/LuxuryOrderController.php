<?php

namespace App\Http\Controllers\Api;

use App\Models\Coupon_user;
use App\Models\Goods_effect;
use App\Models\Goods_flaw;
use App\Models\Goods_part;
use App\Models\Order;
use App\Models\Order_action;
use App\Models\Order_good;
use App\Models\Order_visit;
use App\Models\Payment;
use App\Models\Price_list;
use App\Models\Price_type;
use App\Models\User_account;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class LuxuryOrderController extends Controller
{
    /**
     * 奢护预约订单列表接口
     *
     * 通知物流状态：1未通知  2已通知   order_visits.status
     */
    public function book_order_list (Request $request){
        if(empty($request->order_status)) return status(40001, 'order_status参数有误');
        if(empty($request->shipping_status)) return status(40002, 'shipping_status参数有误');
        $shipping_status = json_decode($request->shipping_status, true);
        if(empty($request->status)) return status(40003, 'status参数有误');
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $order = Order::join('order_visits', 'orders.order_sn', '=', 'order_visits.order_sn')
            ->where('shop_id', $admin['shop_id'])
            ->where('order_status', $request->order_status)
            ->whereIn('shipping_status', $shipping_status)
            ->where('order_visits.status', $request->status)
            ->select(['orders.id', 'orders.consignee', 'orders.phone', 'orders.shipping_status', 'order_visits.number', 'order_visits.province', 'order_visits.city', 'order_visits.district', 'order_visits.address', 'order_visits.visit_time', 'order_visits.id as visits_id'])
            ->orderBy('id', 'desc');
        if(!empty($request->name)) {
            $order->where('orders.consignee', 'like', '%'.$request->name.'%');
        }
        if(!empty($request->phone)) {
            $order->where('orders.phone', 'like', '%'.$request->phone.'%');
        }
        $info = $order->get();
        if(count($info) == 0) return status(404, '没有数据');
        return status(200, 'success', $info);
    }


    /**
     * 通知物流接口
     *
     * 通知物流状态：1未通知  2已通知   order_visits.status
     */
    public function inform_logistics (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        if(empty($request->visits_id)) return status(40001, 'visits_id参数有误');
        $order_visits = Order_visit::find($request->visits_id);
        if(empty($order_visits)) return status(404, '预约信息不存在');
        if($order_visits->status != 1) return status(40002, '此状态不可操作');
        $order_visits->status = 2;
        $order_visits->save();
        $order_action = new Order_action();
        $order_action->order_sn = $order_visits['order_sn'];
        $order_action->action_user = '员工：'.$admin['phone'].'（'.$admin['name'].')';
        $order_action->order_status = 1;
        $order_action->shipping_status = 1;
        $order_action->pay_status = 1;
        $order_action->action_note = '员工通知物流师傅';
        $order_action->save();
        return status(200, '通知成功');
    }


    /**
     * 物流揽件接口
     *
     * 通知物流状态：1未通知  2已通知   order_visits.status
     */
    public function claim_goods (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        if(empty($request->order_id)) return status(40001, 'order_id参数有误');
        $order = Order::find($request->order_id);
        if(empty($order)) return status(404, '订单信息不存在');
        if($order->order_status != 1) return status(40002, '此状态不可操作');
        if($order->shipping_status != 1) return status(40003, '此状态不可操作');
        $order->shipping_status = 2;
        $order->save();
        $order_visit = Order_visit::where('order_sn', $order['order_sn'])->first();
        if(empty($request->number)) return status(40004, 'number参数有误');
        $order_visit->number = $request->number;
        $order_visit->save();
        $order_action = new Order_action();
        $order_action->order_sn = $order['order_sn'];
        $order_action->action_user = '员工：'.$admin['phone'].'（'.$admin['name'].')';
        $order_action->order_status = 1;
        $order_action->shipping_status = 2;
        $order_action->pay_status = 1;
        $order_action->action_note = '物流师傅揽件';
        $order_action->save();
        return status(200, '揽件成功');
    }


    /**
     * 送货至门店接口
     *
     * 通知物流状态：1未通知  2已通知   order_visits.status
     */
    public function receive_goods (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        if(empty($request->order_id)) return status(40001, 'order_id参数有误');
        $order = Order::find($request->order_id);
        if(empty($order)) return status(404, '订单信息不存在');
        if($order->order_status != 1) return status(40002, '此状态不可操作');
        if($order->shipping_status != 2) return status(40003, '此状态不可操作');
        $order->shipping_status = 3;
        $order->save();
        $order_action = new Order_action();
        $order_action->order_sn = $order['order_sn'];
        $order_action->action_user = '员工：'.$admin['phone'].'（'.$admin['name'].')';
        $order_action->order_status = 1;
        $order_action->shipping_status = 3;
        $order_action->pay_status = 1;
        $order_action->action_note = '物流师傅送货至门店';
        $order_action->save();
        return status(200, '送货成功');
    }


    /**
     * 发起上门取件接口
     *
     * 通知物流状态：1未通知  2已通知   order_visits.status
     * 订单类别：1奢饰品护理 2名车护理 3花艺 4优惠券 5优惠服务 6好货  order_type
     * 订单状态：1已预约 2洗护中 3洗护完工 4已完成 5已取消 6已下单 7制作中 8制作完成 order_status
     * 配送状态：1未揽件 2已揽件 3未发货 4后台显示已发货 客户端显示待收货 5已收货 6已退货 7到店自取 8发放账户  shipping_status
     * 支付状态：1未付款 2付款中 3已付款  pay_status
     * 配送方式：1自取 2快递 3同城上门 4账户  shipping_type
     */
    public function add_visits_order (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        if(empty($request->phone)) return status(40001, 'phone参数有误');
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
            $user->source_msg = '奢护门店';
            $user->source_shop_id = $admin['shop_id'];
            $user->save();
            $user = User::where('phone', $request->phone)->first();
        }
        $order_sn = sn_26();
        DB::beginTransaction();
        try {
            // 1.写入订单
            $order = new Order();
            $order->order_sn = $order_sn;
            $order->order_type = 1;
            $order->user_id = $user->id;
            $order->order_status = 1;
            $order->shipping_status = 1;
            $order->pay_status = 1;
            $order->shipping_type = 3;
            $order->consignee = $request->user_name;
            $order->phone = $request->phone;
            if(!empty($request->postscript))
                $order->postscript = $request->postscript;
            $order->shop_id = $admin['shop_id'];
            $order->shipping_type = 3;
            $order->save();
            // 2.写入订单操作状态
            $order_action = new Order_action();
            $order_action->order_sn = $order_sn;
            $order_action->action_user = '员工';
            $order_action->order_status = 1;
            $order_action->shipping_status = 1;
            $order_action->pay_status = 1;
            $order_action->action_note = '员工提交预约';
            $order_action->save();
            // 3.写入预约信息
            $order_visit = new Order_visit();
            $order_visit->order_sn = $order_sn;
            if(empty($request->province)) return status(40003, 'province参数有误');
            $order_visit->province = $request->province;
            if(empty($request->city)) return status(40004, 'city参数有误');
            $order_visit->city = $request->city;
            if(empty($request->district)) return status(40005, 'district参数有误');
            $order_visit->district = $request->district;
            if(empty($request->address)) return status(40006, 'address参数有误');
            $order_visit->address = $request->address;
            if(empty($request->visit_time)) return status(40007, 'visit_time参数有误');
            $order_visit->visit_time = $request->visit_time;
            if(empty($request->number)) return status(40008, 'number参数有误');
            $order_visit->number = $request->number;
            $order_visit->user_name = $request->user_name;
            $order_visit->phone = $request->phone;
            $order_visit->status = 1;
            $order_visit->save();
            DB::commit();
            return status(200, 'success', ['order_sn' => $order_sn]);
        } catch (QueryException $ex) {
            DB::rollback();
            return status(400, '参数有误');
        }
    }


    /**
     * 奢护开单接口
     *
     * 通知物流状态：1未通知  2已通知   order_visits.status
     * 配送方式：1自取 2快递 3同城上门 4账户  shipping_type
     */
    public function open_order (Request $request){
        DB::beginTransaction();
        try {
            $shipping_fee = 0;// 配送费
            $coupon = 0;// 优惠券抵扣金额
            $goods_amount = 0;// 订单商品金额
            $data_goods = [];// 订单商品信息
            $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
            if(empty($request->phone)) return status(40001, 'phone参数有误');
            // 判断是否新开订单
            if(empty($request->order_id)){
                // 添加新订单
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
                    $user->source_msg = '奢护门店';
                    $user->source_shop_id = $admin['shop_id'];
                    $user->save();
                    $user = User::where('phone', $request->phone)->first();
                }
                $order = new Order();
                $order_sn = sn_26();
            }else{
                // 预约订单修改
                $order = Order::find($request->order_id);
                $user = User::first($order['user_id']);
                $order_sn = $order['order_sn'];
            }
            // 1.订单信息
            $user_name = $user['user_name'];
            $phone = $user['phone'];
            $order->order_sn = $order_sn;
            $order->order_type = 1;
            $order->user_id = $user['id'];
            $order->shop_id = $admin['shop_id'];
            $order->order_status = 2;
            $order->shipping_status = 3;
            $order->pay_status = 3;
            if(empty($request->shipping_type)) return status(40003, 'shipping_type参数有误');
            $order->shipping_type = $request->shipping_type;
            if($request->shipping_type != 1){
                $order->country = 86;
                if(empty($request->province)) return status(40004, 'province参数有误');
                $order->province = $request->province;
                if(empty($request->city)) return status(40005, 'city参数有误');
                $order->city = $request->city;
                if(empty($request->district)) return status(40006, 'district参数有误');
                $order->district = $request->district;
                if(empty($request->address)) return status(40007, 'address参数有误');
                $order->address = $request->address;
                if(empty($request->address_user_name)) return status(40008, 'address_user_name参数有误');
                $user_name = $request->address_user_name;
                if(empty($request->address_phone)) return status(40009, 'address_phone参数有误');
                $phone = $request->address_phone;
                $shipping_fee = 10;
            }
            $order->consignee = $user_name;
            $order->phone = $phone;
            if(empty($request->pay_id)) return status(40010, 'pay_id参数有误');
            $pay = Payment::find($request->pay_id);
            $order->pay_id = $request->pay_id;
            $order->pay_name = $pay['pay_name'];
            // 获取商品信息 计算商品价格
            if(empty($request->goods_info)) return status(40011, 'goods_info参数有误');
            $goods_info = json_decode($request->goods_info, true);
            foreach ($goods_info as $k => $v){
                $goods_amount = $goods_amount+$v['make_price'];
                array_push($data_goods, ['order_sn' => $order_sn, 'goods_sn' => sn_20(), 'goods_name' => $v['goods_name'], 'goods_img' => $v['goods_img'], 'goods_number' => 1,'market_price' => $v['market_price'] , 'make_price' => $v['make_price'], 'attr_name' => '奢侈品护理', 'status' => 1, 'to_buyer' => $v['to_buyer'], 'is_urgent' => $v['is_urgent'], 'best_time' => $v['best_time'], 'brand' => $v['brand'], 'colour' => $v['colour'], 'part' => json_encode($v['part']), 'effect' => json_encode($v['effect']), 'flaw' => json_encode($v['flaw']), 'price_list_info' => json_encode($v['price_list_info']), 'shipping_status' => 1, 'is_rework' => 1, 'created_at' => date('Y-m-d H:i:s', time()), 'updated_at' => date('Y-m-d H:i:s', time())]);
            }
            $order->goods_amount = $goods_amount;
            $order->shipping_fee = $shipping_fee;
            if(!empty($request->coupon_id)){
                $coupon = Coupon_user::where('full_money', '<=' ,$order_goods_coupon)->find($request->coupon_id);
                if(empty($coupon)) return status(40004, '优惠券不可用');
                $coupon->status = 2;
                $coupon->coupon_order = $order->order_sn;
                $coupon->save();
                $order->coupon = $coupon->money;
                $order->order_amount = $order->order_amount-$coupon->money;
            }
            $order->coupon = $coupon;
            $order->order_amount = $goods_amount+$shipping_fee-$coupon;
            $order->pay_time = date('Y-m-d H:i:s', time());
            $order->shipping_time = date('Y-m-d H:i:s', time());
            $order->admin_id = $admin['id'];
            $order->postscript = $request->postscript;
            $order->save();
            // 2.判断支付方式为余额 自动扣卡
            if($request->pay_id ==1){
                if($user->user_money < $order->order_amount) return status(40011, '账户余额不足');
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
            // 3.写入订单商品表
            DB::table('order_goods')->insert($data_goods);
            // 4.写入订单操作状态
            $order_action = new Order_action();
            $order_action->order_sn = $order_sn;
            $order_action->action_user = '员工';
            $order_action->order_status = 2;
            $order_action->shipping_status = 3;
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
     * 奢护物件类别接口
     *
     */
    public function article_type (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $article_type = Price_type::where('parent_id', null)
            ->where('shop_id', $admin['shop_id'])
            ->select(['id', 'name'])
            ->orderBy('sort', 'desc')
            ->get();
        return status(200, 'success', $article_type);
    }


    /**
     * 奢护服务项目接口
     *
     */
    public function serve_item (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        if(empty($request->parent_id)) return status(40001, 'parent_id参数有误');
        $article_type = Price_type::where('parent_id', $request->parent_id)
            ->where('shop_id', $admin['shop_id'])
            ->select(['id', 'name'])
            ->orderBy('sort', 'desc')
            ->get();
        $ss = $this->select_children($article_type);
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
     * 获取奢护价目表接口
     *
     */
    public function price_list (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        if(empty($request->parent_id)) return status(40001, 'parent_id参数有误');
        $price_list = Price_list::where('shop_id', $admin['shop_id'])
            ->where('price_list_type_id', $request->parent_id)
            ->select(['id', 'price_sn', 'price_list_name', 'price', 'sell_money', 'job_money'])
            ->get();
        return status(200, 'success', $price_list);
    }


    /**
     * 附带物件接口
     *
     */
    public function goods_part (Request $request){
        $goods_part = Goods_part::get(['id', 'name']);
        return status(200, 'success', $goods_part);
    }


    /**
     * 不良效果接口
     *
     */
    public function goods_effect (Request $request){
        $goods_effect = Goods_effect::get(['id', 'name']);
        return status(200, 'success', $goods_effect);
    }


    /**
     * 物件瑕疵接口
     *
     */
    public function goods_flaw (Request $request){
        $goods_flaw = Goods_flaw::get(['id', 'name']);
        return status(200, 'success', $goods_flaw);
    }


    /**
     * 奢护开单后物件列表接口
     *
     * 物件状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工  status
     * 物件物流：1已接收 2已送出 3已送达 4已送回  shipping_status
     */
    public function order_goods_list (Request $request){
        if(empty($request->status)) return status(40001, 'status参数有误');
        if(empty($request->shipping_status)) return status(40002, 'shipping_status参数有误');
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $order_goods_list = Order_good::join('orders', 'order_goods.order_sn', '=', 'orders.order_sn')
            ->where('orders.shop_id', $admin['shop_id'])
            ->where('order_goods.status', $request->status)
            ->where('order_goods.shipping_status', $request->shipping_status)
            ->select(['order_goods.id', 'goods_sn', 'is_urgent', 'is_rework', 'goods_img', 'goods_name', 'brand', 'colour', 'price_list_info', 'make_price', 'order_goods.to_buyer', 'orders.shipping_type', 'orders.province', 'orders.city', 'orders.district', 'orders.address'])
            ->get();
        if(count($order_goods_list) == 0) return status(404, '找不到数据');
        return status(200, 'success', $order_goods_list);
    }


    /**
     * 员工送出接口
     *
     * 物件状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工  status
     * 物件物流：1已接收 2已送出 3已送达 4已送回  shipping_status
     */
    public function staff_send (Request $request){
        if(empty($request->order_goods_id)) return status(40001, 'order_goods_id参数有误');
        $order_goods_id = json_decode($request->order_goods_id, true);
        $order_goods = Order_good::whereIn('id', $order_goods_id)
            ->where('shipping_status', 1)
            ->update(['shipping_info' => $request->shipping_info, 'shipping_status' => 5, 'logistics_type' => 2]);
        if($order_goods == 0) return status(40002, '操作失败');
        return status(200, '操作成功');
    }


    /**
     * 物流从门送出接口
     *
     * 物件状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工  status
     * 物件物流：1已接收 2已送出 3已送达 4已送回  shipping_status
     */
    public function logistics_send (Request $request){
        if(empty($request->order_goods_id)) return status(40001, 'order_goods_id参数有误');
        $order_goods_id = json_decode($request->order_goods_id, true);
        $order_goods = Order_good::whereIn('id', $order_goods_id)
            ->where('shipping_status', 1)
            ->update(['shipping_status' => 2, 'logistics_type' => 1]);
        if($order_goods == 0) return status(40002, '操作失败');
        return status(200, '操作成功');
    }


    /**
     * 物流送达工厂接口
     *
     * 物件状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工  status
     * 物件物流：1已接收 2已送出 3已送达 4已送回  shipping_status
     */
    public function logistics_delivery (Request $request){
        if(empty($request->order_goods_id)) return status(40001, 'order_goods_id参数有误');
        $order_goods_id = json_decode($request->order_goods_id, true);
        $order_goods = Order_good::whereIn('id', $order_goods_id)
            ->where('shipping_status', 2)
            ->update(['shipping_status' => 3]);
        if($order_goods == 0) return status(40002, '操作失败');
        return status(200, '操作成功');
    }


    /**
     * 物流从工厂取货接口
     *
     * 物件状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工  status
     * 物件物流：1已接收 2已送出 3已送达 4已送回  shipping_status
     */
    public function logistics_claim (Request $request){
        if(empty($request->order_goods_id)) return status(40001, 'order_goods_id参数有误');
        $order_goods_id = json_decode($request->order_goods_id, true);
        $order_goods = Order_good::whereIn('id', $order_goods_id)
            ->where('shipping_status', 3)
            ->update(['shipping_status' => 4]);
        if($order_goods == 0) return status(40002, '操作失败');
        return status(200, '操作成功');
    }


    /**
     * 洗护完成物流送回门店接口
     *
     * 物件状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工  status
     * 物件物流：1已接收 2已送出 3已送达 4已送回  shipping_status
     */
    public function logistics_remand (Request $request){
        if(empty($request->order_goods_id)) return status(40001, 'order_goods_id参数有误');
        $order_goods_id = json_decode($request->order_goods_id, true);
        $order_goods = Order_good::whereIn('id', $order_goods_id)
            ->where('shipping_status', 4)
            ->update(['shipping_status' => 5]);
        if($order_goods == 0) return status(40002, '操作失败');
        return status(200, '操作成功');
    }


    /**
     * 员工确认洗护到店接口
     *
     * 物件状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工  status
     * 物件物流：1已接收 2已送出 3已送达 4已送回  shipping_status
     */
    public function staff_receive (Request $request){
        if(empty($request->order_goods_id)) return status(40001, 'order_goods_id参数有误');
        $order_goods = Order_good::where('shipping_status', 5)
            ->find($request->order_goods_id);
        if(empty($order_goods)) return status(404, '数据不存在');
        $order_goods->status = 2;
        if(empty($request->shipping_type)) return status(40002, 'shipping_type参数有误');
        $order_goods->shipping_type = $request->shipping_type;
        if($request->shipping_type == 2){
            if(empty($request->express_sn)) return status(40003, 'express_sn参数有误');
            $order_goods->express_sn = $request->express_sn;
        }else if($request->shipping_type == 3){
            $order_goods->shipping_status = 6;
        }
        if($request->shipping_type != 1){
            if(empty($request->province)) return status(40004, 'province参数有误');
            $order_goods->province = $request->province;
            if(empty($request->city)) return status(40005, 'city参数有误');
            $order_goods->city = $request->city;
            if(empty($request->district)) return status(40006, 'district参数有误');
            $order_goods->district = $request->district;
            if(empty($request->address)) return status(40007, 'address参数有误');
            $order_goods->address = $request->address;
        }
        $order_goods->save();
        return status(200, '操作成功');
    }


    /**
     * 员工发起返工接口
     *
     * 物件状态：1洗护中 2待收货 3已收货 4待施工 5施工中 6已完工  status
     * 物件物流：1已接收 2已送出 3已送达 4已送回  shipping_status
     */
    public function staff_rework (Request $request){
        if(empty($request->order_goods_id)) return status(40001, 'order_goods_id参数有误');
        $order_goods = Order_good::where('shipping_status', 5)
            ->find($request->order_goods_id);
        if(empty($order_goods)) return status(404, '数据不存在');
        $order_goods->is_rework = 2;
        if($order_goods['logistics_type'] == 1){
            $order_goods->shipping_status = 1;
        }else{
            $order_goods->shipping_status = 5;
        }
        $order_goods->save();
        return status(200, '操作成功');
    }


    /**
     * 订单列表接口
     *
     * 默认查询当日的订单
     */
    public function order_list (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        if(!empty($request->time)){
            $time = $request->time;
        }else{
            $time = date("Y-m-d", time());
        }
        $order = Order::where('shop_id', $admin['shop_id'])
            ->join('admins', 'orders.admin_id', '=', 'admins.id')
            ->where('orders.created_at', 'like', '%'.$time.'%')
            ->orderBy('orders.id', 'desc')
            ->select(['orders.id', 'order_sn', 'orders.created_at', 'consignee', 'orders.phone', 'name as admin_name']);
        if(!empty($request->order_sn)){
            $order->where('order_sn', 'like', '%'.$request->order_sn.'%');
        }
        $data = $order->get();
        if(count($data) == 0) return status(404, '没有数据');
        return status(200, 'success', $data);
    }


    /**
     * 订单详情接口
     *
     */
    public function order_info (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        if(empty($request->order_id)) return status(40001, 'order_id参数有误');
        $order = Order::join('admins', 'orders.admin_id', '=', 'admins.id')
            ->join('users', 'orders.user_id', '=', 'users.id')
            ->with(['order_coupon' => function($query){
                $query->select('coupon_order', 'coupon_name', 'money');
            }])
            ->with(['order_goods' => function($query){
                $query->select('id', 'order_sn', 'goods_sn', 'goods_name', 'goods_img', 'make_price', 'to_buyer', 'is_urgent', 'brand', 'colour', 'price_list_info', 'is_rework');
            }])
            ->select(['orders.id', 'admins.name as admin_name', 'order_sn', 'orders.created_at', 'done_time', 'consignee', 'orders.phone', 'users.user_money', 'shipping_type', 'province', 'city', 'district', 'address', 'goods_amount', 'shipping_fee', 'coupon', 'order_amount'])
            ->where('orders.shop_id', $admin['shop_id'])
            ->find($request->order_id);
        if(empty($order)) return status(404, '没有数据');
        return status(200, 'success', $order);
    }


    /**
     * 物件列表接口
     *
     * 默认查询当日的订单物件
     */
    public function goods_list (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        if(!empty($request->time)){
            $time = $request->time;
        }else{
            $time = date("Y-m-d", time());
        }
        $order_goods = Order_good::join('orders', 'order_goods.order_sn', '=', 'orders.order_sn')
            ->where('shop_id', $admin['shop_id'])
            ->where('orders.created_at', 'like', '%'.$time.'%')
            ->orderBy('order_goods.id', 'desc')
            ->select(['order_goods.id', 'goods_sn', 'is_urgent', 'is_rework', 'goods_img', 'goods_name', 'brand', 'colour', 'price_list_info', 'make_price', 'order_goods.to_buyer']);
        if(!empty($request->goods_sn)){
            $order_goods->where('goods_sn', 'like', '%'.$request->goods_sn.'%');
        }
        $data = $order_goods->get();
        if(count($data) == 0) return status(404, '没有数据');
        return status(200, 'success', $data);
    }


    /**
     * 物件详情接口
     *
     */
    public function goods_info (Request $request){
        if(empty($request->goods_id)) return status(40001, 'goods_id参数有误');
        $goods = Order_good::select(['id', 'goods_name', 'price_list_info', 'is_urgent', 'is_rework', 'best_time', 'to_buyer', 'brand', 'colour', 'part', 'effect', 'flaw', 'goods_img', 'make_price'])->find($request->goods_id);
        $price = json_decode($goods['price_list_info'], true);
        foreach ($price as $k => $v){
            $price_list = Price_list::find($v['id']);
            $parent = Price_type::find($price_list['price_list_type_id']);
            $price[$k]['desc'] = $parent['name'].'，'.$price_list['price_list_name'];
        }
        $goods['price_list'] = $price;
        return status(200, 'success', $goods);
    }
}
