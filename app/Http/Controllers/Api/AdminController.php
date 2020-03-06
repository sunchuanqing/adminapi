<?php

namespace App\Http\Controllers\Api;

use App\Models\Admin;
use App\Models\Admin_messages;
use App\Models\Admin_role;
use App\Models\App_version;
use App\Models\Order;
use App\Models\Recharge_balance;
use App\Models\Shop;
use App\Models\User_account;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;
use App\Http\Controllers\Api\NotifiedController;

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
     */
    public function statistics (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        // 订单实收金额 实际支付金额(除去余额支付、优惠、赠送)
        $order_money = Order::where('shop_id', $admin['shop_id'])
            ->where('pay_status', 3)
            ->where('pay_id', '!=', 1)
            ->sum('order_amount');
        // 充值实收金额 顾客充值实际支付金额(除去赠送)
        $recharge_receipts_money = Recharge_balance::where('shop_id', $admin['shop_id'])
            ->where('pay_type', '!=', 6)
            ->where('status', 2)
            ->where('pay_status', 2)
            ->sum('money');
        // 营业额 订单的实收金额+余额支付的订单金额
        $turnover = Order::where('shop_id', $admin['shop_id'])
            ->where('pay_status', 3)
            ->sum('order_amount');
        // 充值金额 顾客获取的所有金额(包含赠送)
        $recharge_money = Recharge_balance::where('shop_id', $admin['shop_id'])
            ->where('status', 2)
            ->where('pay_status', 2)
            ->sum('money');
        // 开单数量 已经付款的订单数量
        $order_number = Order::where('shop_id', $admin['shop_id'])
            ->where('pay_status', 3)
            ->count();
        // 充值比数
        $recharge_number = Recharge_balance::where('shop_id', $admin['shop_id'])
            ->where('status', 2)
            ->where('pay_status', 2)
            ->count();
        $data = [
            'order_money' => $order_money,
            'recharge_receipts_money' => $recharge_receipts_money,
            'turnover' => $turnover,
            'recharge_money' => $recharge_money,
            'order_number' => $order_number,
            'recharge_number' => $recharge_number
        ];
        return status(200, 'success', $data);
    }


    /**
     * 施工人员列表接口
     *
     */
    public function master_worker (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $admin_role = Admin_role::where('shop_id', $admin['shop_id'])->select(['id'])->get();
        $info = Admin::whereIn('admin_role_id', $admin_role)
            ->where('status', 1)
            ->where('admin_status', 1)
            ->select(['id', 'name'])
            ->get();
        return status(200, 'success', $info);
    }


    /**
     * 发送短信验证码接口
     *
     * 使用场景：1忘记密码 2换绑    type
     */
    public function send_code (Request $request){
        if(empty($request->phone)) return status(40001, '手机号有误');
        if(empty($request->type)) return status(40002, 'type参数有误');
        $phone = $request->phone;
        $type = $request->type;
        $code = mt_rand(100000, 999999);
        // 使用场景分类
        switch ($type) {
            case '1':// 忘记密码
                $admin = Admin::where('phone', $request->phone)->first();
                if(empty($admin)) return status(40003, '手机号不存在');
                break;
            case '2':// 换绑手机
                $admin = Admin::where('phone', $request->phone)->first();
                if(!empty($admin)) return status(40004, '手机号已存在');
                break;
            default:
                return status(400, 'type参数有误');
                break;
        }
        $info = json_decode(code_sn($phone, $code), true);
        if($info['error'] == 0){
            Redis::setex('code_'.$phone, 1200, $code);// redis存入验证码
            return status(200, '发送成功');
        }else{
            return $info;
        }
    }


    /**
     * 验证短信验证码接口
     *
     */
    public function verify_code (Request $request){
        if(empty($request->phone)) return status(40001, '手机号有误');
        if(empty($request->code)) return status(40002, '验证码必填');
        $code = Redis::get('code_'.$request->phone);
        if($code == $request->code){
            Redis::del('code_'.$request->phone);// 杀死之前发送的验证码
            return status(200, '验证成功');
        }else{
            return status(40003, '验证失败');
        }
    }


    /**
     * 更换绑定手机号接口
     *
     */
    public function phone_update (Request $request){
        if(empty($request->phone)) return status(40001, '手机号有误');
        if(empty($request->code)) return status(40002, '验证码必填');
        $code = Redis::get('code_'.$request->phone);
        if($code == $request->code){
            Redis::del('code_'.$request->phone);// 杀死之前发送的验证码
            // 修改手机号
            $admin_id = json_decode(Redis::get('admin_token_'.$request->token), true);
            $admin = Admin::find($admin_id['id']);
            if(Admin::where('phone', $request->phone)->count() == 0){
                $admin->phone = $request->phone;
                $admin->save();
                return status(200, '绑定成功');
            }else{
                return status(40004, '此手机号已存在');
            }
        }else{
            return status(40003, '验证码有误');
        }
    }


    /**
     * 忘记密码修改密码接口
     *
     */
    public function password_update (Request $request){
        if(empty($request->phone)) return status(40001, 'phone参数有误');
        $admin = Admin::where('phone', $request->phone)->first();
        if(empty($admin)) return status(404, '信息不存在');
        if(empty($request->password)) return status(40002, 'password参数有误');
        $password = MD5($request->password);
        $admin->password = $password;
        $admin->save();
        return status(200, '修改成功');
    }


    /**
     * 今日消息列表接口
     *
     */
    public function message_list (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $message = Admin_messages::where('admin_id', $admin['id'])
            ->where('shop_id', $admin['shop_id'])
            ->where('created_at', 'like', '%'.date("Y-m-d", time()).'%')
            ->OrderBy('id', 'desc')
            ->select(['id', 'content', 'created_at'])
            ->get();
        if(count($message) == 0) return status(404, '没有消息');
        return status(200, 'success', $message);
    }


    /**
     * 历史消息列表接口
     *
     */
    public function old_message_list (Request $request){
        if(empty($request->page)) return status(40001, 'page参数有误');
        $page = $request->page;// 页数
        $limit = 5;// 每页显示的条数
        $num = ($page-1)*$limit;// 跳过多少条
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $message = Admin_messages::where('admin_id', $admin['id'])
            ->where('shop_id', $admin['shop_id'])
            ->where('created_at', '<', date("Y-m-d", time()))
            ->OrderBy('id', 'desc')
            ->select(['id', 'content', 'created_at'])
            ->offset($num)
            ->limit($limit)
            ->get();
        if(count($message) == 0) return status(404, '没有消息');
        $info = [
            'current_page' => $page,
            'count' => Admin_messages::where('admin_id', $admin['id'])
                ->where('shop_id', $admin['shop_id'])
                ->where('created_at', '<', date("Y-m-d", time()))
                ->count(),
            'page_count' => $limit,
            'data' => $message
        ];
        return status(200, 'success', $info);
    }


    public function test (Request $request){
        $test = new NotifiedController();
        $request->offsetSet('title', '这里是一个标题');
        $request->offsetSet('text', '这里是展示的内容');
        $admin = Admin::whereIn('id', [17])->get();
        $request->offsetSet('admin', $admin);
        $info = $test->notified_push($request);
        return $info;
    }


    /**
     * 检测版本更新接口
     *
     */
    public function version_detection (Request $request){
        if(empty($request->version_number)) return status(40001, 'version_number参数有误');
        $app_version = App_version::OrderBy('id', 'desc')->select(['id', 'version_number', 'version_info', 'version_url'])->first();
        if($request->version_number >= $app_version['version_number']){
            return status(404, '没有新版本');
        }else{
            return status(200, 'success', $app_version);
        }
    }


    /**
     * 管理员模块统计接口
     *
     */
    public function admin_statistics (Request $request){
        if(empty($request->shop_id)) return status(40001, 'shop_id参数有误');
        $shop_id = json_decode($request->shop_id, true);
        if(!empty($request->time)){
            $time = $request->time;
        }else{
            $time = date('Y-m', time());
        }
        // 订单实收金额 实际支付金额(除去余额支付、优惠、赠送)
        $order_money = Order::whereIn('shop_id', $shop_id)
            ->where('created_at', 'like', '%'.$time.'%')
            ->where('pay_status', 3)
            ->where('pay_id', '!=', 1)
            ->sum('order_amount');
        // 充值实收金额 顾客充值实际支付金额(除去赠送)
        $recharge_receipts_money = Recharge_balance::whereIn('shop_id', $shop_id)
            ->where('created_at', 'like', '%'.$time.'%')
            ->where('pay_type', '!=', 6)
            ->where('status', 2)
            ->where('pay_status', 2)
            ->sum('money');
        // 营业额 订单的实收金额+余额支付的订单金额
        $turnover = Order::whereIn('shop_id', $shop_id)
            ->where('created_at', 'like', '%'.$time.'%')
            ->where('pay_status', 3)
            ->sum('order_amount');
        // 充值金额 顾客获取的所有金额(包含赠送)
        $recharge_money = Recharge_balance::whereIn('shop_id', $shop_id)
            ->where('created_at', 'like', '%'.$time.'%')
            ->where('status', 2)
            ->where('pay_status', 2)
            ->sum('money');
        // 开单数量 已经付款的订单数量
        $order_number = Order::whereIn('shop_id', $shop_id)
            ->where('created_at', 'like', '%'.$time.'%')
            ->where('pay_status', 3)
            ->count();
        // 充值比数
        $recharge_number = Recharge_balance::whereIn('shop_id', $shop_id)
            ->where('created_at', 'like', '%'.$time.'%')
            ->where('status', 2)
            ->where('pay_status', 2)
            ->count();
        $data = [
            'order_money' => $order_money,
            'recharge_receipts_money' => $recharge_receipts_money,
            'turnover' => $turnover,
            'recharge_money' => $recharge_money,
            'order_number' => $order_number,
            'recharge_number' => $recharge_number
        ];
        return status(200, 'success', $data);
    }


    /**
     * 管理员用户流水明细接口
     *
     * 状态：1充值 2消费   status
     * 排序：1倒序 2正序   time
     */
    public function user_account (Request $request){
        if(empty($request->shop_id)) return status(40001, 'shop_id参数有误');
        $shop_id = json_decode($request->shop_id, true);
        if(!empty($request->time)){
            $time = $request->time;
        }else{
            $time = date('Y-m', time());
        }
        $user_account = User_account::whereIn('shop_id', $shop_id)
            ->join('users', 'user_accounts.user_id', '=', 'users.id')
            ->select(['user_accounts.id', 'user_accounts.account_sn', 'user_accounts.money_change', 'user_accounts.change_desc', 'user_accounts.recharge_sn', 'users.user_name', 'users.phone', 'user_accounts.created_at'])
            ->where('user_accounts.created_at', 'like', '%'.$time.'%')
            ->OrderBy('user_accounts.id', 'desc')
            ->with(['recharge_balance' => function($query){
                $query->join('admins', 'recharge_balances.admin_id', '=', 'admins.id');
                $query->select('recharge_balances.recharge_sn', 'recharge_balances.admin_id', 'admins.name');
            }]);
        if($request->status == 1){
            $user_account->where('recharge_sn', '!=', null);
        }else if($request->status == 2){
            $user_account->where('recharge_sn', '=', null);
        }
        $info = $user_account->get();
        if(count($info) == 0) return status(404, '没有数据');
        return status(200, 'success', $info);
    }
}
