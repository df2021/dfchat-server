<?php


namespace app\api\controller;


class Chat extends Index
{
    public function upload(){
        if(!empty($_FILES)){
            $allowedExts = array("gif", "jpeg", "jpg", "png","ico");
            $temp = explode(".", $_FILES["file"]["name"]);
//            echo $_FILES["file"]["size"];
            $extension = end($temp);     // 获取文件后缀名
            if ((($_FILES["file"]["type"] == "image/gif")
                    || ($_FILES["file"]["type"] == "image/jpeg")
                    || ($_FILES["file"]["type"] == "image/jpg")
                    || ($_FILES["file"]["type"] == "image/pjpeg")
                    || ($_FILES["file"]["type"] == "image/x-png")
                    || ($_FILES["file"]["type"] == "image/png"))
                && ($_FILES["file"]["size"] < 5120000)   // 小于 5M
                && in_array($extension, $allowedExts))
            {
                $path = '/uploads/images/'.date('Ymd',time())."/";
                $new_dir = $_SERVER["DOCUMENT_ROOT"].$path;
                if(!file_exists($new_dir)){
                    //检查是否有该文件夹，如果没有就创建，并给予最高权限
                    mkdir($new_dir, 0755,true);
                }

                $bool = move_uploaded_file($_FILES["file"]["tmp_name"], $new_dir . $_FILES["file"]["name"]);
                if($bool){
                    return json([
                        'code'=>0,
                        'url'=>$path . $_FILES["file"]["name"],
                        'msg'=>'success'
                    ]);
                }
            }else {
                echo "非法的文件格式";
            }

        }
    }
}