<?php

namespace App\Http\Controllers\Api;

use App\Models\Admin;
use App\Models\Admin_role;
use App\Models\Order;
use App\Models\User_account;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;

class AdminController extends Controller
{
    /**
     * 员工列表接口
     *
     * 查询当前登录账号下的门店员工
     */
    public function admin_list (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $where_key = $request->where_key;
        $where_value = $request->where_value;
        $admin_list = Admin_role::where('shop_id', $admin['shop_id'])
            ->with(['role_admin' => function($query) use ($where_key, $where_value){
                if(!empty($where_key)){
                    $query->where($where_key, 'like', '%'.$where_value.'%');
                }
                $query->select('id', 'name', 'phone', 'admin_role_id');
            }])
            ->get(['id', 'shop_id', 'role_name']);
        return status(200, 'success', $admin_list);
    }


    /**
     * 员工详情信息接口
     *
     */
    public function admin_info (Request $request){
        if(empty($request->id)) return status(40001, 'id参数有误');
        $admin = Admin::join('admin_roles', 'admins.admin_role_id', '=', 'admin_roles.id')
            ->select(['admins.id', 'admins.name', 'admins.phone', 'admin_roles.id as role_id', 'admin_roles.role_name'])
            ->find($request->id);
        return status(200, 'success', $admin);
    }


    /**
     * 添加员工接口
     *
     */
    public function add_admin (Request $request){
        if(empty($request->phone)) return status(40001, 'phone参数有误');
        if(empty($request->name)) return status(40002, 'name参数有误');
        if(empty($request->admin_role_id)) return status(40003, 'admin_role_id参数有误');
        if(empty($request->password)) return status(40004, 'password参数有误');
        if(empty(Admin::where('phone', $request->phone)->first())){
            $add_admin = new Admin();
            $add_admin->phone = $request->phone;
            $add_admin->name = $request->name;
            $add_admin->admin_role_id = $request->admin_role_id;
            $add_admin->password = MD5($request->password);
            $add_admin->photo = 'http://img.jiaranjituan.cn/photo.jpg';
            // 管理后台的最基本角色
            $add_admin->role_id = 4;
            $add_admin->role_name = '员工';
            $add_admin->save();
            return status(200, '添加成功');
        }else{
            return status(40005, '管理员已存在');
        }
    }


    /**
     * 员工角色列表接口
     *
     */
    public function admin_role_list (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $admin_role = Admin_role::where('shop_id', $admin['shop_id'])
            ->select(['id', 'role_name'])
            ->get();
        return status(200, 'success', $admin_role);
    }


    /**
     * 删除员工接口
     *
     */
    public function del_admin (Request $request){
        if(empty($request->id)) return status(40001, 'id参数有误');
        $admin_id = json_decode($request->id, true);
        if(count($admin_id) == 0) return status(40002, 'id参数有误');
        foreach ($admin_id as $k => $v){
            $admin = Admin::find($v['id']);
            if(empty($admin)) return status(404, '管理员已存在');
            $admin->delete();
        }
        return status(200, '删除成功');
    }


    /**
     * 修改员工接口
     *
     */
    public function update_admin (Request $request){
        if(empty($request->id)) return status(40001, 'id参数有误');
        if(empty($request->phone)) return status(40002, 'phone参数有误');
        if(empty($request->name)) return status(40003, 'name参数有误');
        if(empty($request->admin_role_id)) return status(40004, 'admin_role_id参数有误');
        $admin = Admin::find($request->id);
        if(empty($admin)){
            return status(40005, '管理员不存在');
        }else{
            if($admin->phone != $request->phone){
                if(!empty(Admin::where('phone', $request->phone)->first())) return status(40005, '手机号已存在');
                $admin->phone = $request->phone;
            }
            $admin->name = $request->name;
            $admin->admin_role_id = $request->admin_role_id;
            $admin->save();
            return status(200, '修改成功');
        }
    }


    /**
     * 修改密码接口
     *
     * 获取原始密码判断与数据库密码一致则进行修改 记录新密码
     */
    public function update_password (Request $request){
        if(empty($request->original_password)) return status(40001, 'original_password参数有误');
        if(empty($request->password)) return status(40002, 'password参数有误');
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $update_password = Admin::find($admin['id']);
        if($update_password->password == MD5($request->original_password)){
            $update_password->password = MD5($request->password);
            $update_password->save();
            return status(200, '修改成功');
        }else{
            return status(40003, '原始密码输入不正确');
        }
    }


    /**
     * 统计接口
     *
     * 订单实收金额 + 充值实收金额 = 现金流
     */
    public function statistics (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $order_money = Order::where('shop_id', $admin['shop_id'])
            ->where('pay_status', 3)
            ->sum('order_amount');
        $recharge_money = User_account::where('shop_id', $admin['shop_id'])
            ->where('recharge_sn', '!=', null)
            ->sum('money_change');
        return $recharge_money;
    }
}
