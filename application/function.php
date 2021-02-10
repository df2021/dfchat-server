<?php
// +----------------------------------------------------------------------
// | 海豚PHP框架 [ DolphinPHP ]
// +----------------------------------------------------------------------
// | 版权所有 2016~2019 广东卓锐软件有限公司 [ http://www.zrthink.com ]
// +----------------------------------------------------------------------
// | 官方网站: http://dolphinphp.com
// +----------------------------------------------------------------------

// 为方便系统核心升级，二次开发中需要用到的公共函数请写在这个文件，不要去修改common.php文件
use think\facade\Config;

//加密
if (!function_exists('encryptToken')) {
    function encryptToken($string = '')
    {
        $key = Config::get('api.key');
        $data =  openssl_encrypt($string, 'aes-128-ecb', $key, OPENSSL_RAW_DATA);
        return base64_encode($data);
    }
}

if (!function_exists('decryptToken')) {
    function decryptToken($string = '')
    {
        $string = base64_decode($string);
        $key = Config::get('api.key');
        $res = openssl_decrypt($string, 'aes-128-ecb', $key, OPENSSL_RAW_DATA); #解密
        return $res;
    }
}

if (!function_exists('uc_time_ago')) {
    function uc_time_ago($ptime) {
        $etime = time() - $ptime;
        switch ($etime){
            case $etime <= 60:
                $msg = '刚刚';
                break;
            case $etime > 60 && $etime <= 60 * 60:
                $msg = floor($etime / 60) . ' 分钟前';
                break;
            case $etime > 60 * 60 && $etime <= 24 * 60 * 60:
                $msg = date('Ymd',$ptime)==date('Ymd',time()) ? '今天 '.date('H:i',$ptime) : '昨天 '.date('H:i',$ptime);
                break;
            case $etime > 24 * 60 * 60 && $etime <= 2 * 24 * 60 * 60:
                $msg = date('Ymd',$ptime)+1==date('Ymd',time()) ? '昨天 '.date('H:i',$ptime) : '前天 '.date('H:i',$ptime);
                break;
            case $etime > 2 * 24 * 60 * 60 && $etime <= 12 * 30 * 24 * 60 * 60:
                $msg = date('Y',$ptime)==date('Y',time()) ? date('m-d H:i',$ptime) : date('Y-m-d H:i',$ptime);
                break;
            default: $msg = date('Y-m-d H:i',$ptime);
        }
        return $msg;
    }
}
