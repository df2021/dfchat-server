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

if (!function_exists('uc_time_format')) {
    function uc_time_format($etime) {
        $no=date("H",$etime);
        $format = date('H:i',$etime);
        if ($no>0&&$no<=6){
            return "凌晨" . $format;
        }
        if ($no>6&&$no<12){
            return "上午" . $format;
        }
        if ($no>=12&&$no<=18){
            return "下午" . $format;
        }
        if ($no>18&&$no<=24){
            return "晚上" . $format;
        }
        return $format;
    }
}

if (!function_exists('getFirstChar')) {
    function getFirstChar($s0) {
        $fchar = ord(substr($s0, 0, 1));
        if (($fchar >= ord("a") and $fchar <= ord("z"))or($fchar >= ord("A") and $fchar <= ord("Z"))) return strtoupper(chr($fchar));
        $s = iconv("UTF-8", "GBK", $s0);
        $asc = ord($s{0}) * 256 + ord($s{1})-65536;
        if ($asc >= -20319 and $asc <= -20284)return "A";
        if ($asc >= -20283 and $asc <= -19776)return "B";
        if ($asc >= -19775 and $asc <= -19219)return "C";
        if ($asc >= -19218 and $asc <= -18711)return "D";
        if ($asc >= -18710 and $asc <= -18527)return "E";
        if ($asc >= -18526 and $asc <= -18240)return "F";
        if ($asc >= -18239 and $asc <= -17923)return "G";
        if ($asc >= -17922 and $asc <= -17418)return "H";
        if ($asc >= -17417 and $asc <= -16475)return "J";
        if ($asc >= -16474 and $asc <= -16213)return "K";
        if ($asc >= -16212 and $asc <= -15641)return "L";
        if ($asc >= -15640 and $asc <= -15166)return "M";
        if ($asc >= -15165 and $asc <= -14923)return "N";
        if ($asc >= -14922 and $asc <= -14915)return "O";
        if ($asc >= -14914 and $asc <= -14631)return "P";
        if ($asc >= -14630 and $asc <= -14150)return "Q";
        if ($asc >= -14149 and $asc <= -14091)return "R";
        if ($asc >= -14090 and $asc <= -13319)return "S";
        if ($asc >= -13318 and $asc <= -12839)return "T";
        if ($asc >= -12838 and $asc <= -12557)return "W";
        if ($asc >= -12556 and $asc <= -11848)return "X";
        if ($asc >= -11847 and $asc <= -11056)return "Y";
        if ($asc >= -11055 and $asc <= -10247)return "Z";
//        return null;
        return '☆';
    }

}
