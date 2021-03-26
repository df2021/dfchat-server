<?php
// +----------------------------------------------------------------------
// | 海豚PHP框架 [ DolphinPHP ]
// +----------------------------------------------------------------------
// | 版权所有 2016~2019 广东卓锐软件有限公司 [ http://www.zrthink.com ]
// +----------------------------------------------------------------------
// | 官方网站: http://dolphinphp.com
// +----------------------------------------------------------------------

// 为方便系统核心升级，二次开发中需要用到的公共函数请写在这个文件，不要去修改common.php文件
use think\Db;
use think\facade\Config;


if (!function_exists('ipCity')) {
    function ipCity($userip) {
        //return $userip;
        //IP数据库路径，这里用的是QQ IP数据库 20110405 纯真版
        $dat_path = '/www/wwwroot/df/extend/lib/QQWry.Dat';
        //$URLpath="http://".$_SERVER['HTTP_HOST'];
        //$dat_path =$URLpath."/source/plugin/QQWry.dat";
        //判断IP地址是否有效

        /*if(!ereg("^([0-9]{1,3}.){3}[0-9]{1,3}$", $userip)){
            return 'IP Address Invalid';
        }*/


        //打开IP数据库
        if(!$fd = @fopen($dat_path, 'rb')){
            return 'IP data file not exists or access denied';
        }

        //explode函数分解IP地址，运算得出整数形结果
        $userip = explode('.', $userip);
        $useripNum = $userip[0] * 16777216 + $userip[1] * 65536 + $userip[2] * 256 + $userip[3];

        //获取IP地址索引开始和结束位置
        $DataBegin = fread($fd, 4);
        $DataEnd = fread($fd, 4);
        $useripbegin = implode('', unpack('L', $DataBegin));
        if($useripbegin < 0) $useripbegin += pow(2, 32);
        $useripend = implode('', unpack('L', $DataEnd));
        if($useripend < 0) $useripend += pow(2, 32);
        $useripAllNum = ($useripend - $useripbegin) / 7 + 1;

        $BeginNum = 0;
        $EndNum = $useripAllNum;

        //使用二分查找法从索引记录中搜索匹配的IP地址记录
        $userip1num = 0;
        $userip2num = 0;
        while($userip1num>$useripNum || $userip2num<$useripNum) {
            $Middle= intval(($EndNum + $BeginNum) / 2);

            //偏移指针到索引位置读取4个字节
            fseek($fd, $useripbegin + 7 * $Middle);
            $useripData1 = fread($fd, 4);
            if(strlen($useripData1) < 4) {
                fclose($fd);
                return 'File Error';
            }
            //提取出来的数据转换成长整形，如果数据是负数则加上2的32次幂
            $userip1num = implode('', unpack('L', $useripData1));
            if($userip1num < 0) $userip1num += pow(2, 32);

            //提取的长整型数大于我们IP地址则修改结束位置进行下一次循环
            if($userip1num > $useripNum) {
                $EndNum = $Middle;
                continue;
            }

            //取完上一个索引后取下一个索引
            $DataSeek = fread($fd, 3);
            if(strlen($DataSeek) < 3) {
                fclose($fd);
                return 'File Error';
            }
            $DataSeek = implode('', unpack('L', $DataSeek.chr(0)));
            fseek($fd, $DataSeek);
            $useripData2 = fread($fd, 4);
            if(strlen($useripData2) < 4) {
                fclose($fd);
                return 'File Error';
            }
            $userip2num = implode('', unpack('L', $useripData2));
            if($userip2num < 0) $userip2num += pow(2, 32);

            //找不到IP地址对应城市
            if($userip2num < $useripNum) {
                if($Middle == $BeginNum) {
                    fclose($fd);
                    return 'No Data';
                }
                $BeginNum = $Middle;
            }
        }

        $useripFlag = fread($fd, 1);
        if($useripFlag == chr(1)) {
            $useripSeek = fread($fd, 3);
            if(strlen($useripSeek) < 3) {
                fclose($fd);
                return 'System Error';
            }
            $useripSeek = implode('', unpack('L', $useripSeek.chr(0)));
            fseek($fd, $useripSeek);
            $useripFlag = fread($fd, 1);
        }

        if($useripFlag == chr(2)) {
            $AddrSeek = fread($fd, 3);
            if(strlen($AddrSeek) < 3) {
                fclose($fd);
                return 'System Error';
            }
            $useripFlag = fread($fd, 1);
            if($useripFlag == chr(2)) {
                $AddrSeek2 = fread($fd, 3);
                if(strlen($AddrSeek2) < 3) {
                    fclose($fd);
                    return 'System Error';
                }
                $AddrSeek2 = implode('', unpack('L', $AddrSeek2.chr(0)));
                fseek($fd, $AddrSeek2);
            } else {
                fseek($fd, -1, SEEK_CUR);
            }
            $useripAddr2 = '';
            while(($char = fread($fd, 1)) != chr(0))
                $useripAddr2 .= $char;

            $AddrSeek = implode('', unpack('L', $AddrSeek.chr(0)));
            fseek($fd, $AddrSeek);
            $useripAddr1 = '';
            while(($char = fread($fd, 1)) != chr(0))
                $useripAddr1 .= $char;
        } else {
            fseek($fd, -1, SEEK_CUR);
            $useripAddr1 = '';
            while(($char = fread($fd, 1)) != chr(0))
                $useripAddr1 .= $char;

            $useripFlag = fread($fd, 1);
            if($useripFlag == chr(2)) {
                $AddrSeek2 = fread($fd, 3);
                if(strlen($AddrSeek2) < 3) {
                    fclose($fd);
                    return 'System Error';
                }
                $AddrSeek2 = implode('', unpack('L', $AddrSeek2.chr(0)));
                fseek($fd, $AddrSeek2);
            } else {
                fseek($fd, -1, SEEK_CUR);
            }
            $useripAddr2 = '';
            while(($char = fread($fd, 1)) != chr(0)){
                $useripAddr2 .= $char;
            }
        }
        fclose($fd);

        //返回IP地址对应的城市结果
        if(preg_match('/http/i', $useripAddr2)) {
            $useripAddr2 = '';
        }
        $useripaddr = "$useripAddr1 $useripAddr2";
        $useripaddr = preg_replace('/CZ88.Net/is', '', $useripaddr);
        $useripaddr = preg_replace('/^s*/is', '', $useripaddr);
        $useripaddr = preg_replace('/s*$/is', '', $useripaddr);
        if(preg_match('/http/i', $useripaddr) || $useripaddr == '') {
            $useripaddr = 'No Data';
        }

//        $useripaddr = iconv ( "GBK", "UTF-8", $useripaddr );
        $useripaddr = mb_convert_encoding($useripaddr, "UTF-8", "GBK,GB2312,GB18030");
        return $useripaddr;
    }

}
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

if (!function_exists('autoAddFriend')) {
    function autoAddFriend($uid,$friendUid) {
        $time = time();
        $openRemark = '我们已经成为好友了，可以开始聊天了！';
        $configRemark = Db::table('df_system_config')->value('open_remark');
        if(!empty($configRemark)){
            $openRemark = $configRemark;
        }
        Db::table('df_friends')->insertAll([
            ['status'=>1, 'mid'=>$uid,'friend_mid'=>$friendUid,'create_time'=>$time,'update_time'=>$time],
            ['status'=>1, 'mid'=>$friendUid,'friend_mid'=>$uid,'create_time'=>$time,'update_time'=>$time],
        ],true);
        Db::table('df_message')->insert([
            'send_mid'=>$friendUid,
            'to_mid'=>$uid,
            'type'=>1,
            'status'=>1,
            'content'=>$openRemark,
            'create_time'=>$time,
            'update_time'=>$time,
            'send_time'=>$time,
        ]);
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
