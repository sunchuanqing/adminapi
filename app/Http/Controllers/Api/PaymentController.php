<?php

namespace App\Http\Controllers\Api;

use App\Models\Payment;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class PaymentController extends Controller
{
    /**
     * 支付方式接口
     *
     * 是否可用：1是 2否    enabled
     */
    public function pay_mode (Request $request){
        $pay_mode = Payment::where('enabled', 1)
            ->orderBy('sort', 'desc')
            ->select(['id', 'pay_name'])
            ->get();
        return status(200, 'success', $pay_mode);
    }
}
