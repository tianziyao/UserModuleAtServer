<?php
/**
 * Created by PhpStorm.
 * User: Tian
 * Date: 2017/9/15
 * Time: 下午8:49
 */

$enums = array(
    0 => '请求成功',
    10001 => '用户名已存在',
    20001 => '数据库操作失败',
    30001 => '用户名或密码错误',
    40001 => '系统错误，请稍后重试',
);

function response($rc, $data)
{
    global $enums;
    $return_value = array();
    $return_value['rc'] = $rc;
    $return_value['data'] = $data;
    $return_value['errorInfo'] = $enums[$rc];
    return json_encode($return_value);
}