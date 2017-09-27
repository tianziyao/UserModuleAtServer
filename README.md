# UserModuleAtServer

最近想要做一个小项目，由于前后都是一个人，在登录和注册的接口上就被卡住了，因此想登录、注册、口令之间的关系，使用 PHP 实现登录注册模块，和访问口令。

出于安全的考虑，首先定下三项原则：

1. 在传输中，不允许明文传输用户隐私数据；
2. 在本地，不允许明文保存用户隐私数据；
3. 在服务器，不允许明文保存用户隐私数据；

在网络来说，我们知道不论 POST 请求和 GET 请求都会被抓包，在没有使用 HTTPS 的情况下，抓包我们是防不住的，如果明文传输用户隐私，那后果就不说了。

本地和服务器也是如此，比如 iOS 设备，如果存储在本地，越狱之后通过设备 Finder 之类的功能，也能轻易找到我们存储在本地的用户隐私。

> 使用 Keychain 在本地也有保存，但不在沙盒，暂且忽略。

上面讲到，用户隐私数据总归可以被拿到的，如何保证被拿到之后不会被用来做坏事？



## 加密

将用户的隐私数据加密，那么就算被拿到，也无法被拿来使用。在这里呢，我们先不谈加密，而是先纠正一个误区，有些朋友会认为 Base64 可以加密，甚至有 Base64 加密的说法。

Base64 主要不是加密，它主要的用途是把二进制数据序列转化为 ASCII 字符序列，用以数据传输。二进制数据是什么呢？计算机上存储的所有数据，都是二进制数据。

Base64 最常见的应用场景是 URL，因为 URL 只能是特定的一些 ASCII 字符。这时需要用到 Base64 编码，当然这也只是对二进制数据本身的编码，编码后的数据里面可能包含 `+/=` 等符号，真正放到 URL 里面时候，还需要URL-Encoding，变成 `%XX` 模式，以消除这些符号的歧义。其次就是将图片转为 Base64 的字符串。

**因此，Base64 只是一种编码方式，而不是加密方式。**

好了，现在回到我们的主题，先说登录和注册之间的关系，这 3 个模块需要做什么事情呢？

- 注册：将用户输入的隐私数据，发送给服务器，服务器进行保存；
- 登录：将用户输入的隐私数据，发送给服务器，服务器进行比对，确认是否有权限登录；
- token：确保用户在登录中；

我们把用户输入的隐私数据再具象一些，比如账号和密码，结合我们上面提到的安全原则，那么分解开来，实际我们要做以下几件事：

- 服务器-注册接口：接收客户端传来的账号和密码，将其保存在数据库中；
- 服务器-登录接口：接收客户端传来的账号和密码，与数据库比对，完全命中则登录成功，否则登录失败；
  - 登录成功后，生成或更新 token 和过期时间，保存在数据库， token 返回给客户端；
  - 服务器定期清除 token；
- 客户端-注册模块：向服务器注册接口发送账号和密码；
- 客户端-登录模块：向服务器登录接口发送账号和密码；
  - 登录成功后，保存 token 到本地；
  - 退出登录后，清除 token；
- 发送的账号和密码需要加密；
- 数据库中需要保存的是加密后的账号和密码；
- 请求敏感数据时，将客户端传来的 token 和服务器验证，不通过则提示客户端登录；

上面逻辑理清楚后，相信对于大家来说并不难实现，以下是服务器注册接口做的事情：

```php
/*获取 get 请求传递的参数*/
$account = $_GET['account'];
$password = $_GET['password'];

/*创建数据连接*/
$db = new DataBase();

/*检查用户名是否存在*/
$is_exist = $db->check_user_exist($account);

if ($is_exist) {
    echo return_value(10001, false);
}
else {
    /*检查用户名是否添加成功*/
    $result = $db->add_user($account, $password);
    if ($result) {
        echo return_value(0, true);
    }
    else {
        echo return_value(20001, false);
    }
}
```

现在是服务器登录接口做的事情：

```php
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
```

剩下的无非是加密算法的不同，我最常用的是 md5，那么我们经过 md5 加密以后，其实还是不太安全，为什么呢？因为 md5 本身就不安全。虽然 md5 是不可逆的 hash 算法，反向算出来虽然困难，但是如果反向查询，密码设置的简单，也很容易被攻破。

比如我们使用 md5 加密一个密码 `123456`，对应的 md5 是 `e10adc3949ba59abbe56e057f20f883e`，找到一个 md5 解密的网站，比如 http://cmd5.com/，很容易就被破解了密码，怎么办呢？



## 加盐

工作一段时间的同学对这个名词应该不会陌生，这种方式算是给用户的隐私数据加上密了，其实就是一段隐私数据加一段乱码再进行 md5，用代码表示大致是这样：

```python
// 伪代码
salt = '#^&%**(^&(&*)_)_(*&^&#$%GVHKBJ(*^&*%^%&^&'
password = '123456'
post_body = salt + password
print post_body.md5()
// ffb34d898f6573a1cf14fdc34d3343c0
```

现在，密码看起来挺靠谱的了，但是，我们知道加盐这种方式是比较早期的处理方式了，既然现在没有在大范围使用了，就说明单纯加盐还是存在缺陷的。



### 有泄露的可能

现在我们在客户端对密码做了 md5 加盐，服务器保存的也是加密后的内容，但是，盐是写在了客户端的源代码中，一旦对源代码进行反编译，找到 `salt` 这个字符串，那么加盐的做法也就形同虚设了。

反编译源代码的代价也很高，一般对于安全性能要求不高的话，也够用了，但是，对于一些涉及资金之类的 App  来说，仅仅加盐还是不够的。

比如离职的技术同学不是很开心，又或者有人想花钱买这串字符等等，盐一旦被泄露，就是一场灾难，这也是盐最大的缺陷。



### 依赖性太强

盐一旦被设定，那么再做修改的话就非常困难了，因为服务器存储的全部是加盐后的数据，如果换盐，那么这些数据全部都需要改动。**但是可怕的不在于此，如果将服务器的数据改动后，旧版本的用户再访问又都不可以了，因为他们用的是之前的盐。**



## HMAC

目前最常见的方式，应该就是 HMAC 了，HMAC 算法主要应用于身份验证，与加盐的不同点在于，盐被移到了服务器，服务器返回什么，就用什么作为盐。

这么做有什么好处呢？ 如果我们在登录的过程中，黑客截获了我们发送的数据，他也只能得到 HMAC 加密过后的结果，由于不知道密钥，根本不可能获取到用户密码，从而保证了安全性。

但是还有一个问题，前面我们讲到，**盐被获取以后很危险，如果从服务器获取盐，也会被抓包，那还不如写在源代码里面呢，至少被反编译还困难点，那如果解决这个隐患呢**？

那就是，在用户注册时就生成和获取这个秘钥，以代码示例：

现在我们发送一个请求：

```
GET http://localhost:8888/capsule/register.php?account=joy&password=789
```

服务器收到请求后，做了下面的事情：

```php
/*获取 get 请求传递的参数*/
$account = $_GET['account'];
$password = $_GET['password'];  //123456

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
        $data = ['salt'=>$salt];
        echo response(0, $data);
        //echo response(0, true);
    }
    else {
        echo response(20001, false);
    }
}
```

服务器现在保存的是：

```
account: joy
password: 05575c24576
```

客户端拿到的结果是：

```json
{
  "rc": 0,
  "data": {
    "salt": "5633905fdc65b6c57be8698b1f0e884948c05d7f"
  },
  "errorInfo": ""
}
```

那么客户端接下来应该做什么呢？把 `salt` 做本地的持久化，登录时将用户输入的密码做一次同样的 hmac，那么就能通过服务器的 `password: 05575c24576` 校验了，发起登录请求：

```
GET http://localhost:8888/capsule/login.php?account=joy&password=789 
// fail
GET http://localhost:8888/capsule/login.php?account=joy&password=05575c24576 
// success
```

现在我们解决了依赖性太强的问题，盐我们可以随意的更改，甚至可以是随机的，每个用户都不一样。这样单个用户的安全性虽然没有加强，但是整个平台的安全性缺大大提升了，很少有人会针对一个用户搞事情。但是细心的同学应该可以想到，现在的盐，也就是秘钥是保存在本地的，如果用户的秘钥丢失，比如换手机了，那么岂不是**有正确的密码，也无法登陆了吗**？

针对这个问题，核心就是用户没有了秘钥，那么在用户登陆的时候，逻辑就需要变一下。

``` swift
// 伪代码
func login(account, password) {
    //如果有盐
    if let salt = getSalt() {
        //将密码进行 hmac，请求登陆接口
        network.login(account, password.hmac(salt))
    }
    else {
        //请求 getSalt 接口，请求参数为账户+应用标识
        network.getSalt(account + bundleId, { salt in
            //将盐保存在本地，再次调用自身。
            savaSalt(salt)
            login(account, password)
        })
    }
}
```

那么可想而知，我们的注册接口现在也需要新加一个 `bundleId` 的请求参数，然后用 `account + bundleId` 作为 key，来保存 `salt`：

```php
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
```

同时我们需要创建一个获取 `salt` 的接口：

```php
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
```

写到这里，就可以给大家介绍一个比较好玩的东西了。



### 设备锁

一些 App 具有设备锁的功能，比如 QQ，这个功能是将账号与设备进行绑定，如果其他人知道了用户的账号和密码，但是设备不符，同样无法登录，怎样实现呢？

就是用户开启设备锁之后，如果设备中没有 `salt`，那么就不再请求 `getSalt` 接口，而是转为其他验证方式，通过之后，才可以请求 `getSalt`。



### 提升单个用户的安全性

现在这个 App 相对来说比较安全了，上面说到，因为每个用户的 `salt` 都不一样，破解单个用户的利益不大，所以，对于平台来说安全性已经比较高了，但凡是都有例外，如果这个破坏者就是铁了心要搞事情，就针对一个用户，现在这个方案，还有哪些问题存在呢？

1. 注册时返回的 `salt` 被抓包时有可能会泄露；
2. 更换设备后，获取的 `salt` 被抓包时有可能会泄露；
3. 保存在本地的 `salt` ，有可能通过文件路径获取到；
4. 抓包的人就算不知道密码，通过 hmac 加密后的字符，也可以进行登录；

 怎么处理呢？首先我们需要清楚的是，之所以会被破解，是拿到了我们加密时的因子，或者叫种子，这个种子服务器和客户端都必须要有，如果没有的话，两者就无法进行通信了，但是我们也不能在客户端将种子写死，在服务器给客户端种子时，总会有可能被获取。

我们要设计一种思路，需要有一个种子，服务器和客户端之间无需通讯，但是都可以被理解的种子。

同时我们需要这个种子是动态的，每次加密的结果都不一样，那么就算抓到了加密后的密码，这个密码也随之失效了。

**所以，我们需要一个无需服务器和客户端通讯的，动态的种子，时间。**



## HMAC+时间

这个动态的种子是如何使用的呢？

1. 客户端发送注册请求，服务器返回 `salt`，保存 hmac 后的密码；
2. 客户端保存 `salt`；
3. 客户端发送登录请求，参数为 hmac 后的密码，加上当前的时间；
4. 服务器收到登录请求，将数据库中的密码，加上当前的时间，进行比对；

客户端代码：

```javascript
// 秘钥
const salt = ''
// 当前时间，精确到分钟
const currentTime = '201709171204'
// 用户输入的密码
let password = '123456'
// (hmac+currentTime).md5
password = (password.hmac(salt) + currentTime).md5()
network('login', {method: 'GET', params: {password:password}})
```

服务器代码：

```php
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
```

但是现在还有一点问题，那就是对时间的容错上，如果客户端发送的时候是 `201709171204`，服务器响应时却已经到了 `201709171205` 了，那么这样势必是不能通过的，这种情况，只需要服务器把当前的时间减去一分钟，再校验一次，符合其中之一就可以。

聪明的你应该可以想到，这也就是**验证码 5 分钟内有效期的实现**。

现在这个 App，就算注册时拿到了 `salt`，也很难在 1 分钟内反推出密码，同时，抓包的密码一分钟后也就失效了，对于单个用户的安全性，也有了进一步的提升。