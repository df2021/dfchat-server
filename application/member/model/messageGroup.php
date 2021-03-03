<?php


namespace app\member\model;


use think\Db;
use think\Model;

class messageGroup extends Model
{
    // 设置当前模型对应的完整数据表名称
    protected $name = 'message_group';

    // 自动写入时间戳
    protected $autoWriteTimestamp = true;

    public function getSendMidAttr($value)
    {
        if($value==0){
            return '系统';
        }
        $creator = Member::where('id',$value)->find();
        return !empty($creator['nickname']) ? $creator['nickname'] : $creator['username'];
    }

    public function getGroupIdAttr($value)
    {
        return Db::table('df_group')->where('id',$value)->value('name');
    }

    public function getContentAttr($value,$data)
    {
        $msg = '';
        switch ($data['type']){
            case 1:
                $msg = $value;
                break;
            case 2:
                $msg = '语音';
            case 3:
                $msg = json_decode($value,true);
                $msg = '<img width="40px" height="40px" src='.$msg['url'].' />';
                break;
            case 5:
                $msg = json_decode($value,true);
                $url = escapeshellarg($msg['url']);
                $msg = '<video width="auto" height="40px" src='.$url.' ></video>';
            default:
                break;
        }
        return $msg;
    }

}