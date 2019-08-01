<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Qiniu\Auth;

class QiniuTokenController extends Controller
{
    public function token (Request $request){
        // 需要填写你的 Access Key 和 Secret Key
        $accessKey ="Ad03_KpRkFUA-vxOqRS-xL7LzHPj1rLHYzLfRvLQ";
        $secretKey = "HIEHlvRcEHG055axV2LJARA__Rl6nPoIFPX90QKU";
        $bucket = "goods";
        // 构建鉴权对象
        $auth = new Auth($accessKey, $secretKey);
        // 生成上传 Token
        $token = $auth->uploadToken($bucket);
        return status(200, 'success', $token);
    }
}
