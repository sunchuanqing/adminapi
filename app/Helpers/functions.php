<?php
// 返回操作状态
function status (int $code, $msg, $data = ''){
    $info['code'] = $code;
    $info['msg'] = $msg;
    if($data)
        $info['data'] = $data;
    return response()->json($info);
}

// 返回错误码
function error (int $code, $data = ''){
    switch ($code) {
        case '400':
            $msg='Bad Request';// 请求参数有误，语义有误，当前请求无法被服务器理解
            break;
        case '401':
            $msg='Unauthorized';// 没有授权 需要登录
            break;
        case '403':
            $msg='Access Denied';// 拒绝访问
            break;
        case '404':
            $msg='Not Found';// 找不到可用数据
            break;
        case '405':
            $msg='Method Not Allowed';// 方法不准许
            break;
        case '406':
            $msg='Not Acceptable';
            break;
        case '409':
            $msg='Conflict';// 数据冲突 有可能被篡改
            break;
        case '410':
            $msg='Gone';// 数据已经不可用
            break;
        case '411':
            $msg='Length Required';
            break;
        case '412':
            $msg='Precondition Failed';// 条件不满足
            break;
        case '415':
            $msg='Unsupported Media Type';
            break;
        case '428':
            $msg='Precondition Required';
            break;
        case '429':
            $msg='TooMany Requests';
            break;
        case '500':
            $msg='Http Exception';
            break;
        case '503':
            $msg='Service Unavailable';
            break;
        default:
            $msg='Http Exception';
            break;
    }
    $info['code'] = $code;
    $info['msg'] = $msg;
    if($data)
        $info['data'] = $data;
    return response()->json($info);
}

// 生成26位单号
function sn_26 (){
    $sn = date('YmdHis', time()).substr(microtime(), 2, 6).mt_rand(100, 999).mt_rand(100, 999);
    return $sn;
}

// 生成20位单号
function sn_20 (){
    $sn = date('YmdHis', time()).substr(microtime(), 2, 6);
    return $sn;
}

 // 用户错误日志
function user_error_log ($log_info){
    $user_log = new \App\Models\User_error_log();
    $user_log->log_info = $log_info;
    $user_log->ip_address = $_SERVER["REMOTE_ADDR"];
    $user_log->save();
}