<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User_account extends Model
{
    // 一对一关联 充值信息
    public function recharge_balance()
    {
        return $this->hasOne('App\Models\Recharge_balance', 'recharge_sn', 'recharge_sn');
    }
}
