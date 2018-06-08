<?php 

/**
 * worker
 * @author liyunfei
 * 消息队列
 */

class Queue
{
    public $queue;
    
    public $queuename = '';
    
    public $block = true;
    
    public $max_size = 4096;
    
    public function __construct($queuename=''){
        do {
            $this->queuename = __DIR__."/q".rand(1000,9999);
        } while ( is_file($this->queuename) );
        
        touch($this->queuename);
        $key = ftok($this->queuename, 'R');
        if(msg_queue_exists($key)){
            $this->queue = msg_get_queue($key, 0666);
            msg_remove_queue($this->queue);
        }
        $this->queue = msg_get_queue($key, 0666);
    }
    
    public function write($pid, $message, $block=''){
        if(!$block){
            $block = $this->block;
        }
        $errcode = 0;
        $result = @msg_send($this->queue, $pid, $message, false, $block, $errcode);
        //send failed retry
        return ['code' => $errcode, 'msg' => $result];
    }
    
    public function read($pid){
        $flags = 0;
        if(!$this->block){//设置非阻塞
            $flags = MSG_IPC_NOWAIT;
        }
        
        $msgtype = '';
        $message = '';
        $errcode = 0;
        //只接收msgtype=$pid的消息
        msg_receive($this->queue, $pid, $msgtype, $this->max_size, $message, false, $flags, $errcode);
        
        return ['code' => $errcode, 'msg' => $message];
    }
    
    public function close(){
        msg_remove_queue($this->queue);//destory a message queue
        unlink($this->queuename);
    }
    
    public function __destruct(){
        $this->close();
    }
}