<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admin_role extends Model
{
    // 一对多关联 订单商品
    public function role_admin()
    {
        return $this->hasMany('App\Models\Admin', 'admin_role_id', 'id');
    }
}
