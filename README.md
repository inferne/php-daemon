# 一级标题
## 二级标题
### 三级标题
#### 四级标题
##### 五级标题

## 多进程类 -- Worker.php

依赖拓展<br/>
pcntl<br/>
posix<br/>
sysvmsg<br/>

进程间使用sysvmsg进行通讯<br/>
### Worker.php
1. 支持的参数
   * -c <number>    worker number
   * -d             daemonize
   * --stop         stop all worker
   * --help         help
2. 支持设置的属性
   * worker_num
      * worker process number from 0 to 99999
   * daemonize
      * bool true or false
   * onReceive
      * when your child receive message execute this function
   * onStart
      * when your child start execute this function
   * log_file
      * define log filename
   * max_size
      * define a message max size(byte)
   * block
      * bool true or false,set send message and receive message block
3. 支持的方法
   * set()
      * set your config valid
   * run()
      * run worker and create child process
   * send()
      * worker send message to child process
   * stop()
      * worker recyce child process

### demo1

假如你有一个队列，此示例适用于快速消费掉一个队列
```php
include 'Autoload.php';
spl_autoload_register("Autoload::load");

$worker = new Worker();
$worker->set(array(
    'worker_num' => 32,
    'daemonize'  => false,
    'max_size'   => 128,
));

$worker->onReceive = function(Worker $fd, $message){
    echo $message;
};
$worker->run();

foreach ($data as $key => $value){
    $result = $worker->send(json_encode($value));//发送到子进程
}

$worker->stop();
```
### demo2

假如你有一个消费脚本来消费队列里面的任务，此示例可以简单的把你的脚本变成多进程版，当前脚本无需任何修改
```php
include 'Autoload.php';
spl_autoload_register("Autoload::load");

$worker = new Worker();
$worker->set(array(
    'worker_num' => 32,
    'daemonize'  => false,
));

$worker->onStart = function(Worker $fd){
    include 'your_file.php';
};
$worker->run();

$worker->stop();
```

## 全局计数器 -- Counter.php

假如你需要在多个进程的运行环境中使用一个全局计数器，那么这个可以满足你
### 性能测试
```php
 * 1000000 times incr
 * real	0m8.636s
 * user	0m6.868s
 * sys	0m1.771s
```
每秒自增操作10w+，可以满足大部分使用场景

## 任务管理器 -- Task.php

一个任务管理工具，假如crontab无法满足你，可以试试这个，也可以当作daemon程序的进程管理工具

### 如何使用

只需按下面规则配置json文件，然后启动任务管理器即可
```php
[
    {//第一个任务
        "root" : "",//任务执行的目录
        "interval" : 1,//任务执行的时间间隔
        "cmd" : "sleep 100",//执行的命令
        "count" : 1//启动相同进程的数量
    },
    {//第二个任务
        ...
    }
]
```
启动
```php
php task.php
```
