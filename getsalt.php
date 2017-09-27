<?php
/**
 * Created by PhpStorm.
 * User: Tian
 * Date: 2017/9/16
 * Time: 下午10:33
 */

require_once 'database.php';
require_once 'response.php';

/*获取 get 请求传递的参数*/
$account = $_GET['account'];
$bundle_id = $_GET['bundleId'];

/*创建数据连接*/
$db = new DataBase();

/*获取秘钥*/
$salt = $db->get_salt($account, $bundle_id);

if ($salt == '') {
    echo response(40001, false);
}
else {
    $data = ['salt'=>$salt];
    echo response(0, $data);
}
