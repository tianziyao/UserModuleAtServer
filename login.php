<?php
/**
 * Created by PhpStorm.
 * User: Tian
 * Date: 2017/9/11
 * Time: 下午3:34
 */

require_once 'database.php';
require_once 'response.php';

/*获取 get 请求传递的参数*/
$account = $_GET['account'];
$password = $_GET['password'];



/*创建数据连接*/
$db = new DataBase();

/*是否命中用户名和密码*/
$should_login = $db->should_login($account, $password);

if ($should_login) {
    /*更新 token*/
    $token = $db->insert_token($account);
    if ($token == '') {
        echo response(40001, false);
    }
    else {
        $data = ['token' => $token];
        echo response(0, $data);
    }
}
else {
    echo response(30001, false);
}

