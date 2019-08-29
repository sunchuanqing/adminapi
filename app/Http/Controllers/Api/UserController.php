<?php

namespace App\Http\Controllers\Api;

use App\Models\Coupon_user;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Price_list_user;
use App\Models\Recharge_balance;
use App\Models\Shop_serve_user;
use App\Models\User_account;
use App\Models\User_address;
use App\Models\User_car;
use App\Models\User_pay_point;
use App\Models\User_rank;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class UserController extends Controller
{
    /**
     * 会员信息接口
     *
     */
    public function user_info (Request $request){
        if(empty($request->sn)) return status(40001, 'sn参数有误');
        $data = User::join('user_ranks', 'users.user_rank_id', '=', 'user_ranks.id')->select(['users.id', 'users.user_sn', 'users.user_name', 'users.sex', 'users.phone', 'users.created_at', 'users.user_money', 'users.pay_points', 'user_ranks.name as vip_name']);
        if(strlen($request->sn) == 10){
            $user = $data->where('user_sn', $request->sn)->first();
        }else{
            $user = $data->where('phone', $request->sn)->first();
        }
        if(empty($user)) return status(404, '找不到会员');
        return status(200, 'success', $user);
    }



    /**
     * 会员开卡接口
     *
     * 性别：1男 2女 3保密
     */
    public function add_user (Request $request){
        if(empty($request->user_sn)) return status(40001, 'user_sn参数不正确');
        if(empty($request->user_name)) return status(40002, 'user_name参数不正确');
        if(empty($request->phone)) return status(40003, 'phone参数不正确');
        if(empty($request->user_rank_id)) return status(40004, 'user_rank_id参数不正确');
        if(empty($request->sex)) return status(40005, 'sex参数不正确');
        if(User::where('phone', $request->phone)->count() == 1) return status(40006, '会员已存在');
        DB::beginTransaction();
        try {
            $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
            $user = new User();
            $user->user_sn = $request->user_sn;
            $user->user_name = $request->user_name;
            $user->phone = $request->phone;
            $user->user_rank_id = $request->user_rank_id;
            $user->sex = $request->sex;
            $user->photo = 'http://img.jiaranjituan.cn/photo.png';
            $user->source_msg = $admin['shop_name'];
            $user->source_shop_id = $admin['shop_id'];
            $user->save();
            $user_info = User::where('phone', $request->phone)->first();
            if(!empty($request->car)){
                $car = json_decode($request->car, true);
                foreach ($car as $k => $v) {
                    $user_car = new User_car();
                    $user_car->user_id = $user_info['id'];
                    $user_car->car_province = $v['car_province'];
                    $user_car->car_city = $v['car_city'];
                    $user_car->car_number = $v['car_number'];
                    $user_car->plate_number = $v['car_province'].$v['car_city'].$v['car_number'];
                    $user_car->car_info = $v['car_info'];
                    $user_car->car_colour = $v['car_colour'];
                    $user_car->car_type = $v['car_type'];
                    $user_car->remark = $v['remark'];
                    $user_car->save();
                }
            }
            if(!empty($request->address)){
                $car = json_decode($request->address, true);
                foreach ($car as $k => $v) {
                    $user_address = new User_address();
                    $user_address->user_id = $user_info['id'];
                    $user_address->name = $user_info['user_name'];
                    $user_address->phone = $user_info['phone'];
                    $user_address->country = 86;
                    $user_address->province = $v['province'];
                    $user_address->city = $v['city'];
                    $user_address->district = $v['district'];
                    $user_address->address = $v['address'];
                    $user_address->save();
                }
            }
            DB::commit();
            return status(200, '开卡成功');
        } catch (QueryException $ex) {
            DB::rollback();
            return status(400, '参数有误');
        }
    }


    /**
     * 会员卡号生成接口
     *
     */
    public function user_sn (Request $request){
        $user_sn = user_sn();
        return status(200, 'success', $user_sn);
    }


    /**
     * 会员等级接口
     *
     */
    public function user_rank (Request $request){
        $user_rank = User_rank::select(['id', 'name'])->get();
        return status(200, 'success', $user_rank);
    }


    /**
     * 会员添加车辆接口
     *
     */
    public function add_user_car (Request $request){
        if(empty($request->user_id)) return status(40001, 'user_id参数有误');
        if(empty($request->car_province)) return status(40002, 'car_province参数有误');
        if(empty($request->car_city)) return status(40003, 'car_city参数有误');
        if(empty($request->car_number)) return status(40004, 'car_number参数有误');
        if(empty($request->car_info)) return status(40005, 'car_info参数有误');
        if(empty($request->car_colour)) return status(40006, 'car_colour参数有误');
        if(empty($request->car_type)) return status(40007, 'car_type参数有误');
        if(User_car::where('plate_number', $request->car_province.$request->car_city.$request->car_number)->count() == 1) return status(40008, '车辆已存在');
        $user_car = new User_car();
        $user_car->user_id = $request->user_id;
        $user_car->car_province = $request->car_province;
        $user_car->car_city = $request->car_city;
        $user_car->car_number = $request->car_number;
        $user_car->plate_number = $request->car_province.$request->car_city.$request->car_number;
        $user_car->car_info = $request->car_info;
        $user_car->car_colour = $request->car_colour;
        $user_car->car_type = $request->car_type;
        $user_car->remark = $request->remark;
        $user_car->save();
        return status(200, '添加成功');
    }


    /**
     * 会员车辆删除接口
     *
     */
    public function del_user_car (Request $request){
        if(empty($request->id)) return status(40001, 'id参数有误');
        $user_car = User_car::find($request->id);
        if(empty($user_car)) return status(404, '车辆不存在');
        $user_car->delete();
        return status(200, '删除成功');
    }


    /**
     * 会员车辆修改接口
     *
     */
    public function update_user_car (Request $request){
        if(empty($request->id)) return status(40001, 'id参数有误');
        if(empty($request->car_province)) return status(40002, 'car_province参数有误');
        if(empty($request->car_city)) return status(40003, 'car_city参数有误');
        if(empty($request->car_number)) return status(40004, 'car_number参数有误');
        $user_car = User_car::find($request->id);
        if(empty($user_car)) return status(404, '车辆不存在');
        if($user_car->plate_number != $request->car_province.$request->car_city.$request->car_number){
            if(User_car::where('plate_number', $request->car_province.$request->car_city.$request->car_number)->count() == 1) return status(40008, '车辆已存在');
        }
        $user_car->car_province = $request->car_province;
        $user_car->car_city = $request->car_city;
        $user_car->car_number = $request->car_number;
        $user_car->plate_number = $request->car_province.$request->car_city.$request->car_number;
        $user_car->car_info = $request->car_info;
        $user_car->car_colour = $request->car_colour;
        $user_car->car_type = $request->car_type;
        $user_car->remark = $request->remark;
        $user_car->save();
        return status(200, '修改成功');
    }


    /**
     * 会员车辆列表接口
     *
     */
    public function user_car (Request $request){
        if(!empty($request->user_id)){
            $user_car = User_car::where('user_id', $request->user_id)
                ->select(['id', 'car_province', 'car_city', 'car_number', 'plate_number', 'car_info', 'car_colour', 'car_type', 'remark'])
                ->get();
            if(count($user_car) == 0) return status(404, '车辆不存在');
            return status(200, 'success', $user_car);
        }else if(!empty($request->phone)){
            $user_car = User::where('phone', $request->phone)
                ->join('user_cars', 'users.id', '=', 'user_cars.user_id')
                ->select(['user_cars.id', 'user_cars.car_province', 'user_cars.car_city', 'user_cars.car_number', 'user_cars.plate_number', 'user_cars.car_info', 'user_cars.car_colour', 'user_cars.car_type', 'user_cars.remark', 'users.user_name', 'users.phone'])
                ->get();
            if(count($user_car) == 0) return status(404, '车辆不存在');
            return status(200, 'success', $user_car);
        }else if(!empty($request->plate_number)){
            $user_car = User_car::where('plate_number', $request->plate_number)
                ->join('users', 'user_cars.user_id', '=', 'users.id')
                ->join('user_ranks', 'users.rank_id', '=', 'user_ranks.id')
                ->select(['user_cars.id', 'user_cars.user_id', 'user_cars.car_province', 'user_cars.car_city', 'user_cars.car_number', 'user_cars.plate_number', 'user_cars.car_info', 'user_cars.car_colour', 'user_cars.car_type', 'user_cars.remark', 'users.user_name', 'users.phone', 'users.sex', 'users.user_money', 'users.user_sn', 'user_ranks.name as vip_name'])
                ->get();
            if(count($user_car) == 0) return status(404, '车辆不存在');
            return status(200, 'success', $user_car);
        }else{
            return status(40001, '参数有误');
        }
    }


    /**
     * 会员车辆更换绑定账号接口
     *
     */
    public function update_car_binding (Request $request){
        if(empty($request->user_id)) return status(40001, 'user_id不正确');
        if(empty($request->car_province)) return status(40002, 'car_province参数有误');
        if(empty($request->car_city)) return status(40003, 'car_city参数有误');
        if(empty($request->car_number)) return status(40004, 'car_number参数有误');
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $user = User::find($request->user_id);
        if(empty($user)) return status(40005, '用户信息不正确');
        $user_car = User_car::where('plate_number', $request->car_province.$request->car_city.$request->car_number)->first();
//        if(empty($user_car)) return status(40006, '车辆信息不正确');
        if(empty($user_car)) {
            $user_car = new User_car();
            $user_car->car_province = $request->car_province;
            $user_car->car_city = $request->car_city;
            $user_car->car_number = $request->car_number;
            $user_car->plate_number = $request->car_province.$request->car_city.$request->car_number;
            $user_car->user_id = $request->user_id;
            $user_car->save();
            return status(200, '绑定成功');
        }
        if(Order::where('shop_id', $admin['shop_id'])->where('user_car_id', $user_car['id'])->where('order_status', '!=', 4)->count() > 0){
            if(empty($request->order_id)) return status(40007, 'order_id参数有误');
            $order = Order::find($request->order_id);
            if($order['serve_user_id'] != null) return status(40008, '订单含有优惠套餐不许换绑');
            $order->user_id = $request->user_id;
            $order->save();
        }
        $user_car->user_id = $request->user_id;
        $user_car->save();
        return status(200, '绑定成功');
    }


    /**
     * 会员添加地址接口
     *
     */
    public function add_user_address (Request $request){
        if(empty($request->user_id)) return status(40001, 'user_id参数有误');
        if(empty($request->name)) return status(40002, 'name参数有误');
        if(empty($request->phone)) return status(40003, 'phone参数有误');
        if(empty($request->province)) return status(40004, 'province参数有误');
        if(empty($request->city)) return status(40005, 'city参数有误');
        if(empty($request->district)) return status(40006, 'district参数有误');
        if(empty($request->address)) return status(40007, 'address参数有误');
        $add_user_address = new User_address();
        $add_user_address->user_id = $request->user_id;
        $add_user_address->name = $request->name;
        $add_user_address->phone = $request->phone;
        $add_user_address->country = 86;
        $add_user_address->province = $request->province;
        $add_user_address->city = $request->city;
        $add_user_address->district = $request->district;
        $add_user_address->address = $request->address;
        $add_user_address->save();
        return status(200, '添加成功');
    }


    /**
     * 会员删除地址接口
     *
     */
    public function del_user_address (Request $request){
        if(empty($request->id)) return status(40001, 'id参数有误');
        $user_address = User_address::find($request->id);
        if(empty($user_address)) return status(404, '地址不存在');
        $user_address->delete();
        return status(200, '删除成功');
    }


    /**
     * 会员修改地址接口
     *
     */
    public function update_user_address (Request $request){
        if(empty($request->id)) return status(40001, 'id参数有误');
        if(empty($request->name)) return status(40002, 'name参数有误');
        if(empty($request->phone)) return status(40003, 'phone参数有误');
        if(empty($request->province)) return status(40004, 'province参数有误');
        if(empty($request->city)) return status(40005, 'city参数有误');
        if(empty($request->district)) return status(40006, 'district参数有误');
        if(empty($request->address)) return status(40007, 'address参数有误');
        $user_address = User_address::find($request->id);
        if(empty($user_address)) return status(404, '地址不存在');
        $user_address->name = $request->name;
        $user_address->phone = $request->phone;
        $user_address->country = 86;
        $user_address->province = $request->province;
        $user_address->city = $request->city;
        $user_address->district = $request->district;
        $user_address->address = $request->address;
        $user_address->save();
        return status(200, '修改成功');
    }


    /**
     * 会员地址列表接口
     *
     */
    public function user_address (Request $request){
        if(empty($request->user_id)) return status(40001, 'user_id参数不正确');
        $user_address = User_address::where('user_id', $request->user_id)
            ->select(['id', 'user_id', 'name', 'phone', 'province', 'city', 'district', 'address', 'tab'])
            ->get();
        if(count($user_address) == 0) return status(404, '找不到地址信息');
        return status(200, 'success', $user_address);
    }


    /**
     * 会员可用优惠券信息接口
     *
     * 使用主体：1通用 2门店 3好货 4指定商品(指定的商品见 coupon_goods 表)  subject_type
     * 券类别：1现金券 2满减券 3折扣券 4满赠券 5新人券  coupon_type
     */
    public function user_coupon (Request $request){
        if(empty($request->user_id)) return status(40001, 'user_id参数不正确');
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $coupon_user = Coupon_user::where('user_id', $request->user_id)
            ->whereIn('subject_type', [1, 2])
            ->whereIn('shop_id', [0, $admin['shop_id']])
            ->whereIn('pay_status', [0, 3])
            ->where('status', 1)
            ->where('coupon_start_time', '<=', date('Y-m-d', time()))
            ->where('coupon_end_time', '>=', date('Y-m-d', time()))
            ->select(['id', 'coupon_sn', 'money', 'full_money', 'coupon_name', 'coupon_type'])
            ->get();
        if(count($coupon_user) == 0) return status(404, '找不到可用优惠券');
        return status(200, 'success', $coupon_user);
    }


    /**
     * 会员充值接口
     *
     * money_type  1定额充值  2其他充值
     */
    public function add_user_money (Request $request){
        if(empty($request->user_id)) return status(40001, 'user_id参数不正确');
        if(empty($request->money)) return status(40002, 'money参数不正确');
        if(empty($request->pay_type)) return status(40003, 'pay_type参数不正确');
        if(empty($request->money_type)) return status(40004, 'money_type参数不正确');
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        DB::beginTransaction();
        try {
            // 1.添加用户余额
            $user = User::find($request->user_id);
            $pay_points = 0;// 赠送积分
            $give_money = 0;// 赠送金额
            if($request->money_type == 1){
                if($request->money == 3000){
                    $pay_points = 1500;
                    $give_money = 300;
                }else if($request->money == 5000){
                    $pay_points = 2500;
                    $give_money = 1000;
                }else if($request->money == 10000){
                    $pay_points = 5000;
                    $give_money = 3000;
                }else if($request->money == 20000){
                    $pay_points = 10000;
                    $give_money = 8000;
                }else if($request->money == 30000){
                    $pay_points = 20000;
                    $give_money = 15000;
                }
            }
            $user->user_money = $user->user_money+$request->money+$give_money;
            $user->pay_points = $user->pay_points+$pay_points;
            if($user->rank_id != 4){// 会员类型  若果不是年卡  改变为充值会员
                $user->rank_id = 3;
            }
            $user->save();
            // 2.充值表记录数据
            $recharge_sn = sn_26();
            $recharge_balance = new Recharge_balance();
            $recharge_balance->recharge_sn = $recharge_sn;
            $recharge_balance->user_id = $request->user_id;
            $recharge_balance->money = $request->money;
            $recharge_balance->status = 2;
            $recharge_balance->pay_status = 2;
            $recharge_balance->pay_type = $request->pay_type;
            $recharge_balance->admin_id = $admin['id'];
            $recharge_balance->shop_id = $admin['shop_id'];
            $recharge_balance->save();
            // 3.赠送积分
            $user_pay_point = new User_pay_point();
            $user_pay_point->user_id = $user->id;
            $user_pay_point->change_name = '充值赠送';
            $user_pay_point->point_change = $pay_points;
            $user_pay_point->point = $user->pay_points;
            $user_pay_point->change_msg = '充值赠送积分';
            $user_pay_point->save();
            // 4.记录流水
            $pay = Payment::find($request->pay_type);
            $user_account = new User_account();
            $user_account->account_sn = sn_20();
            $user_account->recharge_sn = $recharge_sn;
            $user_account->user_id = $request->user_id;
            $user_account->money_change = $request->money;
            $user_account->money = $user['user_money'];
            $user_account->change_name = $pay->pay_name.'支付';
            if(empty($request->change_desc)) {
                $user_account->change_desc = '工作人员充值（含赠送金额：'.$give_money.'元）';
            }else{
                $user_account->change_desc = $request->change_desc;
            }
            $user_account->shop_id = $admin['shop_id'];
            $user_account->save();
            // 3.修改会员等级
            // 4.赠送礼品
            DB::commit();
            return status(200, '充值成功');
        } catch (QueryException $ex) {
            DB::rollback();
            return status(400, '参数有误');
        }
    }


    /**
     * 用户流水明细接口
     *
     * 状态：1充值 2消费   status
     * 排序：1倒序 2正序   time
     */
    public function user_account (Request $request){
        if(empty($request->page)) return status(40001, 'page参数有误');
        $page = $request->page;// 页数
        $limit = 10;// 每页显示的条数
        $num = ($page-1)*$limit;// 跳过多少条
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $user_account = User_account::where('shop_id', $admin['shop_id'])
            ->join('users', 'user_accounts.user_id', '=', 'users.id')
            ->select(['user_accounts.id', 'user_accounts.account_sn', 'user_accounts.money_change', 'user_accounts.change_desc', 'user_accounts.recharge_sn', 'users.user_name', 'users.phone'])
            ->with(['recharge_balance' => function($query){
                $query->join('admins', 'recharge_balances.admin_id', '=', 'admins.id');
                $query->select('recharge_balances.recharge_sn', 'recharge_balances.admin_id', 'admins.name');
            }])
            ->offset($num)
            ->limit($limit);
        if($request->status == 1){
            $user_account->where('recharge_sn', '!=', null);
            $count = User_account::where('shop_id', $admin['shop_id'])->where('recharge_sn', '!=', null)->count();
        }else if($request->status == 2){
            $user_account->where('recharge_sn', '=', null);
            $count = User_account::where('shop_id', $admin['shop_id'])->where('recharge_sn', '=', null)->count();
        }else{
            $count = User_account::where('shop_id', $admin['shop_id'])->count();
        }
        if($request->time == 1){
            $user_account->orderBy('user_accounts.id', 'desc');
        }else if($request->time == 2){
            $user_account->orderBy('user_accounts.id', 'asc');
        }else{
            $user_account->orderBy('user_accounts.id', 'desc');
        }
        if(count($user_account->get()) == 0) return status(404, '没有数据');
        $info = [
            'current_page' => $page,
            'count' => $count,
            'page_count' => $limit,
            'data' => $user_account->get()
        ];
        return status(200, 'success', $info);
    }



    /**
     * 用户流水明细详情接口
     *
     * 状态：1充值 2消费   status
     * 排序：1倒序 2正序   time
     */
    public function user_account_info (Request $request){
        if(empty($request->account_id)) return status(40001, 'account_id参数有误');
        $user_account = User_account::join('users', 'user_accounts.user_id', '=', 'users.id')
            ->join('user_ranks', 'users.rank_id', '=', 'user_ranks.id')
            ->select(['user_accounts.id', 'user_accounts.account_sn', 'user_accounts.order_sn', 'user_accounts.money_change', 'user_accounts.change_name', 'user_accounts.change_desc', 'user_accounts.recharge_sn', 'users.user_sn', 'users.user_name', 'users.sex', 'users.phone', 'users.user_money', 'user_ranks.name'])
            ->with(['recharge_balance' => function($query){
                $query->join('admins', 'recharge_balances.admin_id', '=', 'admins.id');
                $query->select('recharge_balances.recharge_sn', 'recharge_balances.money', 'recharge_balances.admin_id', 'admins.name');
            }])
            ->with(['order' => function($query){
                $query->select('id', 'order_sn', 'goods_amount');
            }])
            ->find($request->account_id);
        return status(200, 'success', $user_account);
    }


    /**
     * 会员已购的优惠套餐列表接口
     *
     */
    public function shop_serve_user (Request $request){
        if(empty($request->user_id)) return status(40001, 'user_id参数有误');
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $shop_serve_user = Shop_serve_user::where('user_id', $request->user_id)
            ->where('status', 1)
            ->where('pay_status', 3)
            ->join('shop_serves', 'shop_serve_users.shop_serve_id', '=', 'shop_serves.id')
            ->where('shop_serves.shop_id', $admin['shop_id'])
            ->select(['shop_serve_users.user_serve_sn', 'shop_serves.serve_name', 'shop_serves.shop_price', 'shop_serves.serve_item'])
            ->get();
        $price_list_user = Price_list_user::where('price_list_users.user_id', $request->user_id)
            ->where('shop_id', $admin['shop_id'])
            ->where('status', 1)
            ->where('pay_status', 3)
            ->select(['price_list_id', 'price_list_sn', 'price_list_name', 'price_list_money'])
            ->get();
        $data = [
            'shop_serve_user' => $shop_serve_user,
            'price_list_user' => $price_list_user
        ];
        return status(200, 'success', $data);
    }


    /**
     * 会员车辆消费记录接口
     *
     */
    public function car_consume_record (Request $request){
        if(empty($request->car_id)) return status(40001, 'car_id参数有误');
        $order = Order::where('user_car_id', $request->car_id)
            ->with(['order_goods' => function($query){
                $query->select(['order_sn', 'goods_name', 'status', 'make_price', 'goods_number']);
            }])
            ->select(['id', 'order_sn', 'created_at', 'order_amount']);
        if(!empty($request->time)){
            $order->where('created_at', 'like', '%'.$request->time.'%');
        }
        if(!empty($request->order_sn)){
            $order->where('order_sn', 'like', '%'.$request->order_sn.'%');
        }
        $info = $order->get();
        if(count($info) == 0) return status(404, '没有数据');
        return status(200, 'success', $info);
    }
}
