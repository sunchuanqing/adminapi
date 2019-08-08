<?php

namespace App\Http\Controllers\Api;

use App\Models\Admin;
use App\Models\Admin_messages;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Redis;

header("Content-Type: text/html; charset=utf-8");

require_once(public_path() . '/getui/getui/IGt.Push.php');
//http的域名
define('HOST','http://sdk.open.api.igexin.com/apiex.htm');
define('APPKEY','lIH3KBryGa5DUl3JwhlfX2');
define('APPID','6JkpH6TZdlAUQI9vZkBmJ2');
define('MASTERSECRET','ZIMeoYUfumAVgBwffaYM21');
define('HOSTS','http://sdk.open.api.igexin.com/apiex.htm');

//pushMessageToApp();

class NotifiedController extends Controller
{
    public function notified_push (Request $request){
        $admin = json_decode(Redis::get('admin_token_'.$request->token), true);
        $igt = new \IGeTui(HOST, APPKEY, MASTERSECRET);
        $title = $request->title;
        $text = $request->text;
        //NotyPopLoadTemplate：通知弹框下载功能模板
        $template = $this->IGtNotyPopLoadTemplateDemo($title, $text);
        //定义"SingleMessage"
        $message = new \IGtSingleMessage();
        $message->set_isOffline(true);//是否离线
        $message->set_offlineExpireTime(3600*12*1000);//离线时间
        $message->set_data($template);//设置推送消息类型
        //$message->set_PushNetWorkType(0);//设置是否根据WIFI推送消息，2为4G/3G/2G，1为wifi推送，0为不限制推送
        //接收方
        $target = new \IGtTarget();
        $target->set_appId(APPID);
        foreach ($request->admin as $k => $v){
            $target->set_clientId($v['cid']);
            $igt->pushMessageToSingle($message, $target);
            $admin_message = new Admin_messages();
            $admin_message->admin_id = $v['id'];
            $admin_message->shop_id = $admin['shop_id'];
            $admin_message->title = $title;
            $admin_message->content = $text;
            $admin_message->save();
        }
    }


    function IGtNotyPopLoadTemplateDemo($title, $text){
        $template =  new \IGtNotificationTemplate();
        $template->set_appId(APPID);                      //应用appid
        $template->set_appkey(APPKEY);                    //应用appkey
        $template->set_transmissionType(1);               //透传消息类型
        $template->set_transmissionContent("测试离线");   //透传内容
        $template->set_title($title);                     //通知栏标题
        $template->set_text($text);        //通知栏内容
        $template->set_logo("logo.png");                  //通知栏logo
        $template->set_logoURL("http://wwww.igetui.com/logo.png"); //通知栏logo链接
        $template->set_isRing(true);                      //是否响铃
        $template->set_isVibrate(true);                   //是否震动
        $template->set_isClearable(true);                 //通知栏是否可清除
        //$template->set_duration(BEGINTIME,ENDTIME); //设置ANDROID客户端在此时间区间内展示消息
        return $template;
    }
}
