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
}
