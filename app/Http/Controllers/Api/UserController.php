<?php

namespace App\Http\Controllers\Api;

use App\Models\Coupon_user;
use App\Models\Recharge_balance;
use App\Models\User_account;
use App\Models\User_address;
use App\Models\User_car;
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
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $user = new User();
        $user->user_sn = $request->user_sn;
        $user->user_name = $request->user_name;
        $user->phone = $request->phone;
        $user->user_rank_id = $request->user_rank_id;
        $user->sex = $request->sex;
        $user->photo = 'http://img.jiaranjituan.cn/photo.jpg';
        $user->source_msg = $admin['shop_name'];
        $user->source_shop_id = $admin['shop_id'];
        $user->save();
        $user_info = User::where('phone', $request->phone)->first();
        if(!empty($request->car)){
            $car = json_decode($request->car, true);
            $user_car = new User_car();
            foreach ($car as $k => $v) {
                $user_car->user_id = $user_info['id'];
                $user_car->car_province = $v['car_province'];
                $user_car->car_city = $v['car_city'];
                $user_car->car_number = $v['car_number'];
                $user_car->car_info = $v['car_info'];
                $user_car->car_colour = $v['car_colour'];
                $user_car->car_type = $v['car_type'];
                $user_car->remark = $v['remark'];
                $user_car->save();
            }
        }
        if(!empty($request->address)){
            $car = json_decode($request->address, true);
            $user_address = new User_address();
            foreach ($car as $k => $v) {
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
        return status(200, '开卡成功');
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
        if(empty($request->car_info)) return status(40005, 'car_info参数有误');
        if(empty($request->car_colour)) return status(40006, 'car_colour参数有误');
        if(empty($request->car_type)) return status(40007, 'car_type参数有误');
        $user_car = User_car::find($request->id);
        if(empty($user_car)) return status(404, '车辆不存在');
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
        if(empty($request->user_id)) return status(40001, 'user_id参数有误');
        $user_car = User_car::where('user_id', $request->user_id)
            ->select(['id', 'car_province', 'car_city', 'car_number', 'plate_number', 'car_info', 'car_colour', 'car_type', 'remark'])
            ->get();
        if(count($user_car) == 0) return status(404, '车辆不存在');
        return status(200, 'success', $user_car);
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
            ->select(['coupon_sn', 'money', 'full_money', 'coupon_name', 'coupon_type'])
            ->get();
        if(count($coupon_user) == 0) return status(404, '找不到可用优惠券');
        return status(200, 'success', $coupon_user);
    }


    /**
     * 会员充值接口
     *
     */
    public function add_user_money (Request $request){
        if(empty($request->user_id)) return status(40001, 'user_id参数不正确');
        if(empty($request->money)) return status(40002, 'money参数不正确');
        if(empty($request->pay_type)) return status(40003, 'pay_type参数不正确');
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        DB::beginTransaction();
        try {
            // 1.添加用户余额
            $user = User::find($request->user_id);
            $user->user_money = $user->user_money+$request->money;
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
            $recharge_balance->save();
            // 2.记录流水
            $user_account = new User_account();
            $user_account->account_sn = sn_20();
            $user_account->recharge_sn = $recharge_sn;
            $user_account->user_id = $request->user_id;
            $user_account->money_change = $request->money;
            $user_account->money = $user['user_money'];
            $user_account->change_name = '余额充值';
            $user_account->change_desc = $request->change_desc;
            $user_account->shop_id = $admin['shop_id'];
            $user_account->save();
            // 3.修改会员等级
            DB::commit();
            return status(200, 'success');
        } catch (QueryException $ex) {
            DB::rollback();
            return status(400, '参数有误');
        }
    }
}
