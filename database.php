<?php
/**
 * Created by PhpStorm.
 * User: Tian
 * Date: 2017/9/11
 * Time: 下午3:47
 */

require_once 'response.php';

class DataBase
{

    public $host = 'localhost';
    public $database = 'capsule';
    public $username = 'root';
    public $password = 'root';
    public $connection;

    public function __construct()
    {
        if ($this->connection == null) {
            $this->connection();
        }
        else {
            echo '1111';
        }
    }

    /*连接数据库*/
    function connection()
    {
        $this->connection = mysqli_connect($this->host, $this->username, $this->password);//连接到数据库
        mysqli_query($this->connection,"set names 'utf8'");//编码转化
        if (!$this->connection) {
            die(mysqli_error($this->connection));//诊断连接错误
        }
        $selectedDb = mysqli_select_db($this->connection, $this->database);//选择数据库
        if (!$selectedDb) {
            die(mysqli_error($this->connection));//数据库连接错误
        }
    }

    function add_user($account, $password)
    {
        $account = mysqli_real_escape_string($this->connection ,$account);
        $password = mysqli_real_escape_string($this->connection, $password);
        $insert = "insert into user(account, password) values('$account', '$password')";
        $result = $this->sql_command($insert);
        return $result;
    }

    function save_salt($salt, $account, $bundle_id)
    {
        $data = json_encode([$account.$bundle_id => $salt]);
        $save_salt = "update user set salt = '$data' where user.account = '$account'";
        $result = $this->sql_command($save_salt);
        return $result;
    }

    function get_salt($account, $bundle_id) {
        $select = "select * from user where account like '$account'";
        $result = $this->sql_command($select);
        $row = mysqli_fetch_assoc($result);
        $data = json_decode($row['salt'], true);
        return $data[$account.$bundle_id];
    }

    function check_user_exist($account)
    {
        $account = mysqli_real_escape_string($this->connection ,$account);
        $select = "select * from user where account like '$account'";
        $result = $this->sql_command($select);
        $rows = mysqli_fetch_all($result);
        return count($rows) != 0;
    }

    function get_user($account)
    {
        $select = "select * from user where account like '$account'";
        $result = $this->sql_command($select);
        return mysqli_fetch_assoc($result);
    }

    function should_login($account, $password)
    {
        $account = mysqli_real_escape_string($this->connection ,$account);
        $password = mysqli_real_escape_string($this->connection, $password);
        $user = $this->get_user($account);
        if ($user == null) {
            return false;
        }
        $password_local = $user['password'];
        if ($password_local == '') {
            return false;
        }
        $password_local = md5($password_local.current_time());
        if ($password_local == $password) {
            return true;
        }
        else {
            return false;
        }
    }

    function insert_token($account)
    {
        $str = md5(uniqid(md5(microtime(true)),true));  //生成一个不会重复的字符串
        $str = sha1($str);  //加密
        $time_out = strtotime("+7 days");
        $token = "update user set token = '$str', time_out = '$time_out' where user.account = '$account'";
        $result = $this->sql_command($token);
        if ($result) {
            return $str;
        }
        else {
            return '';
        }
    }

    function sql_command($sql)
    {
        $result = mysqli_query($this->connection, $sql);
        return $result;
    }

    function close()
    {
        mysqli_close($this->connection);
        $this->connection = null;
        echo '断开数据库连接';
    }

}

