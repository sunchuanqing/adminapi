<?php

namespace App\Http\Controllers\Api;

use App\Models\Coupon_user;
use App\Models\User_address;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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
     * 会员地址信息接口
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
}
