<?php 

/**
 * worker
 * @author liyunfei
 * 多进程类
 */

include 'Queue.php';

class Worker
{
    //worker process number
    public $worker_num = 4;
    //if daemonize
    public $daemonize = false;
    //worker array
    public $arr_worker = array();
    //linux ipc(can be is queue/pipe/socket)
    public $ipc;
    public $onReceive;
    
    public $onStart;
    //log file
    public $log_file;
    //max message size
    public $max_size = 4096;
    
    public $block = true;
    
    public function __construct() {
        $this->init();
    }
    
    public function init(){
        //$this->log_file = 'Worker.log';
        if(!$this->ipc){
            $this->ipc = function(){
                return new Queue();//延迟加载
            };
        }
    }
    
    /**
     * 设置
     * @param unknown $config
     */
    public function set($config){
        foreach ($config as $key => $value){
            $this->{$key} = $value;
        }
    }
    
    /**
     * 运行
     */
    public function run(){
        $this->options();//parse options
        $this->daemonize();
        for ($i = 0; $i < $this->worker_num; $i++){
            $this->fork_child($i);
        }
        //$this->monitor();
        sleep(1);//睡一觉（进程同步）
    }
    /**
     * parse user options
     */
    public function options(){
        $short = "c:d::";
        $longopts = array('stop', 'help');
        $options = getopt($short, $longopts);
        if(isset($options['c']) && $options['c'] > 0){
            $this->worker_num = $options['c'];
        }
        if(isset($options['d'])){
            $this->daemonize = true;
        }
        if(isset($options['stop'])){
            global $argv;
            exec("kill `ps -ef|grep ".$argv[0]."|grep -v grep|grep -v vi|awk '{print $2}'`");
            exit();
        }
        if(isset($options['help'])){
            echo "if user set options and set config, options is first vaild!".PHP_EOL;
            echo "-c <number>    worker number".PHP_EOL;
            echo "-d             daemonize".PHP_EOL;
            echo "--stop         stop all worker".PHP_EOL;
            echo "--help         help".PHP_EOL;
            exit();
        }
    }
    
    /**
     * Run as deamon mode.
     * copy from workerman
     * @throws Exception
     */
    public function daemonize(){
        if(!$this->daemonize){
            return ;
        }
        umask(0);
        $pid = pcntl_fork();
        if($pid === -1){
            throw new Exception("fork error!");
        }else if($pid > 0){
            exit(0);
        }
        if (-1 === posix_setsid()) {
            throw new Exception("setsid fail");
        }
        // Fork again avoid SVR4 system regain the control of terminal.
        if($pid === -1){
            throw new Exception("fork error!");
        }else if($pid > 0){
            exit(0);
        }
    }
    /* 
    public function monitor(){
        while (1){
            $pid = pcntl_wait($status);
            $this->arr_worker[$pid];
            $this->fork_child();
        }
    }
     */
    
    /**
     * fork a child process
     * @throws Exception
     */
    public function fork_child($i){
        $pid = pcntl_fork();
        if( $pid > 0 ){
            $this->arr_worker[$i] = $pid;
        }else if($pid == 0){
            if($this->onStart){
                call_user_func($this->onStart, $this);
            }
            $this->loop();
        }else{
            throw new Exception("fork error!");
        }
    }
    
    /**
     * 轮询
     */
    public function loop(){
        $pid = posix_getpid();
        while (1){
            $result = $this->ipc->read($pid);
            $errcode = $result['code'];
            if($errcode > 0){
                $this->log("read errcode:".$errcode);
                sleep(1);
            }
            $message = $result['msg'];
            if(trim($message) == "exit()"){
                $this->log("process exit!");
                exit();
            }else{
                call_user_func($this->onReceive, $this, $message);
            }
            usleep(100);
        }
    }
    
    public $i = 0;//第i个子进程
    
    /**
     * 发送数据
     * @param string $message
     * @param int    $id
     */
    public function send($message, $id = -1){
        $i = $this->i % $this->worker_num;
        $this->i++;
        $j = 100;//最大重试次数
        do{
            $result = $this->ipc->write($this->arr_worker[$i], $message);
            $errcode = $result['code'];
            if($errcode > 0){
                $this->log("write errcode:".$errcode);
                sleep(1);
                $j--;
            }
        } while(!$result && $j);
        return $result;
    }
    /**
     * 停止
     */
    public function stop(){
        foreach ($this->arr_worker as $pid){
            //发送消息类型为$pid的message
            if($this->ipc->write($pid, "exit()", true)){
                $pid = pcntl_wait($status);
                $this->log("recover child process $pid");
                unset($this->arr_worker[array_search($pid, $this->arr_worker)]);
            }
        }
        $this->ipc->close();//close ipc
    }
    private $index = 0;
    
    /**
     * log
     * @param unknown $message
     */
    public function log($message){
        $message = date("Y-m-d H:i:s").' '.posix_getpid().' '.$message.PHP_EOL;
        if(!$this->daemonize || !$this->log_file){
            echo $message;
        }else{
            $filename = $this->log_file.sprintf("%04d", $this->index);
            $stat = stat($filename);
            //日志文件大小大于2G则更换文件
            while($stat['size'] >= 2 * 1024 * 1024 * 1024){
                $this->index++;
                $filename = $this->log_file.sprintf("%04d", $this->index);
                $stat = stat($filename);
            }
            error_log($message, 3, $filename);
        }
    }
}