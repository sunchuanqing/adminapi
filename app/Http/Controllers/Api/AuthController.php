<?php

namespace App\Http\Controllers\Api;

use App\Models\Admin;
use App\Models\Admin_role;
use App\Models\Shop;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;

class AuthController extends Controller
{
    /**
     * 员工端登录接口
     *
     * phone手机号
     * password用户密码 加密方式MD5
     * status登录状态 1正常 2禁用
     * admin_status是否准许登录员工端 1准许 2不准许
     * token为最后登录和id的组合MD5
     */
    public function login (Request $request){
        if(empty($request->phone)) return status(40001, '手机号有误');
        if(empty($request->password)) return status(40002, '密码有误');
        $admin = Admin::where('phone', $request->phone)
            ->select(['id', 'name', 'phone', 'email', 'sex', 'photo', 'todolist', 'status', 'admin_status', 'admin_role_id', 'last_time', 'password'])
            ->first();
        if(empty($admin)) return status(404, '此账号不存在');
        if($admin['status'] == 2) return status(40003, '此账号已被禁用');
        if($admin['admin_status'] == 2) return status(40004, '此账号不许登录员工端');
        if(MD5($request->password) == $admin['password']){
            $admin_role = Admin_role::find($admin['admin_role_id']);// 员工角色shop_id=0 为管理员有所有门店信息
            $admin['role_name'] = $admin_role['role_name'];
            if($admin_role['shop_id'] != 0){// 单个门店信息
                $shop = Shop::join('shop_types', 'shops.shop_type_id', '=', 'shop_types.id')
                    ->select(['shops.id', 'shops.shop_name', 'shop_types.type_name'])
                    ->find($admin_role['shop_id']);
                $admin['shop_id'] = $shop['id'];
                $admin['shop_name'] = $shop['shop_name'];
                $admin['type_name'] = $shop['type_name'];
            }
            $last_time = date('Y-m-d H:i:s', time());// 本次登陆时间 也是 最后登录时间
            $key = MD5($last_time.$admin['id']);
            $last_key = MD5($admin['last_time'].$admin['id']);
            $admin['token'] = $key;
            Redis::set('admin_token_'.$key, $admin);// redis存入新的token
            Redis::del('admin_token_'.$last_key);// 杀死redis以前存储的token
            $update_admin = Admin::find($admin['id']);// 更新最后登录信息
            $update_admin->last_ip = $_SERVER["REMOTE_ADDR"];
            $update_admin->last_time = $last_time;
            $update_admin->save();
            return status(200, 'success', $admin);
        }else{
            return status(40005, '密码不正确');
        }
    }
}
