<?php
/**
 * Created by PhpStorm.
 * User: Tian
 * Date: 2017/9/11
 * Time: 下午5:42
 */

require_once 'database.php';
require_once 'public_function.php';
require_once 'response.php';

/*获取 get 请求传递的参数*/
$account = $_GET['account'];
$password = $_GET['password'];  //123456
$bundle_id = $_GET['bundleId'];

/*创建数据连接*/
$db = new DataBase();

/*制作一个随机的盐*/
$salt = salt();

/*检查用户名是否存在*/
$is_exist = $db->check_user_exist($account);

if ($is_exist) {
    echo response(10001, false);
}
else {
    /*将密码进行 hmac 加密*/
    $password = str_hmac($password,  $salt);

    /*检查用户名是否添加成功*/
    $result = $db->add_user($account, $password);

    if ($result) {

        /*检查秘钥是否保存成功*/
        $save_salt = $db->save_salt($salt, $account, $bundle_id);

        if ($save_salt) {
            $data = ['salt'=>$salt];
            echo response(0, $data);
        }
        else {
            echo response(20001, false);
        }
    }
    else {
        echo response(20001, false);
    }
}
