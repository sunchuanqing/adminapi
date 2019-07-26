<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    // 一对多关联 订单商品
    public function order_goods()
    {
        return $this->hasMany('App\Models\Order_good', 'order_sn', 'order_sn');
    }


    // 一对一关联 预约信息
    public function order_visit()
    {
        return $this->hasOne('App\Models\Order_visit', 'order_sn', 'order_sn');
    }


    // 一对一关联 使用的优惠券
    public function order_coupon()
    {
        return $this->hasOne('App\Models\Coupon_user', 'coupon_order', 'order_sn');
    }


    // 一对一关联 使用已有的优惠服务
    public function order_serve()
    {
        return $this->hasOne('App\Models\Shop_serve_user', 'id', 'serve_user_id');
    }


    // 一对一关联 使用没有的优惠服务
    public function serve_info()
    {
        return $this->hasOne('App\Models\Shop_serve', 'id', 'serve_id');
    }


    // 一对一关联 使用已购的价目表服务
    public function price_list_serve()
    {
        return $this->hasOne('App\Models\Price_list_user', 'id', 'price_list_user_id');
    }
}
