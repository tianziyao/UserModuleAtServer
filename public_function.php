<?php
/**
 * Created by PhpStorm.
 * User: Tian
 * Date: 2017/9/15
 * Time: 下午8:23
 */

function str_length_check($str, $max, $min)
{
    if (strlen($str) > $max || strlen($str) < $min) {
        return true;
    }
    else {
        return false;
    }
}

function str_hmac($str, $key)
{
    $signature = "";
    if (function_exists('hash_hmac')) {
        $signature = bin2hex(hash_hmac("sha1", $str, $key, true));
    }
    else {
        $blocksize = 64;
        $hashfunc = 'sha1';
        if (strlen($key) > $blocksize) {
            $key = pack('H*', $hashfunc($key));
        }
        $key = str_pad($key, $blocksize, chr(0x00));
        $ipad = str_repeat(chr(0x36), $blocksize);
        $opad = str_repeat(chr(0x5c), $blocksize);
        $hmac = pack(
            'H*', $hashfunc(
                ($key ^ $opad) . pack(
                    'H*', $hashfunc(
                        ($key ^ $ipad) . $str
                    )
                )
            )
        );
        $signature = bin2hex($hmac);
    }
    return $signature;
}

function salt()
{
    $str = md5(uniqid(md5(microtime(true)),true));  //生成一个不会重复的字符串
    $str = sha1($str);  //加密
    return $str;
}

function current_time()
{
    return '201709171204';
}