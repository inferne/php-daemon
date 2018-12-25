<?php

/**
 * @example php task.php xxx.json
 * $task_config = [
 *     [
 *         'root' => "",//root dir
 *         'interval' => 1, 
 *         'cmd' => "sleep 100",
 *         'count' => 1,//start task count
 *     ],
 *     [
 *         'interval' => 1, 
 *         'cmd' => "sleep 120",
 *         'count' => 1,
 *     ]
 * ];
 * @desc this is a daemon manager. when we have a task must always exec or interval less than 1min, because crontab min interval is 1min
 * so it is not good when we want less than 1min. 
 * @author liyunfei
 */

class Task
{
    public static $daemonize = true;
    
    public static $arr_worker = [];
    
    public static $conf_file = "task_config.json";
    
    /**
     * init php env
     */
    public static function init()
    {
        chdir(dirname(__FILE__));//change to file dirname
        
        $php = explode(" ", system("whereis -b php"));
        if (!$php[1]) {
            echo "php is not found!";
        } else {
            $path = getenv("PATH");
            putenv("PATH=".$path.":".dirname($php[1]));
        }
    }
    
    /**
     * run all task
     */
    public static function run()
    {
        self::init();
        self::daemonize();
        while (1) {
            echo "\n-----------------------start------------------------\n";
            //简单的热加载
            $task_config = self::parseConfig();
            if (!$task_config) {
                sleep(1);
                continue;
            }
            //执行任务
            foreach ($task_config as $task) {
                self::exec($task);
            }
            //回收任务占用的资源
            self::wait();
            echo "\n-----------------------stop------------------------\n";
            //echo "1\n";
            sleep(1);
        }
    }
    
    /**
     * Run as deamon mode.
     * copy from workerman
     * @throws Exception
     */
    public static function daemonize()
    {
        if(!self::$daemonize){
            return 1;
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
    
    /**
     * parse config
     * @param unknown $conf_file
     * @return array|mixed
     */
    public static function parseConfig()
    {
        $conf_file = self::$conf_file;
        $task_config = [];
        switch (strrchr($conf_file, '.')) {
            case '.ini':
                $task_config = parse_ini_file($conf_file);
                break;
            case '.json':
                $task_config = json_decode(file_get_contents($conf_file), 1);
                break;
            case '.xml':
                echo "this old type not use!";
                break;
            default:
                echo "error file type!";
                break;
        }
        
        return $task_config;
    }
    
    /**
     * exec task
     * @param unknown $task_config
     */
    public static function exec($task)
    {
        //没有任务则跳过
        if (!isset($task['cmd']) || $task['cmd'] == '') {
            return 1;
        }
        
        $tk = substr(md5($task['cmd']), 8, 16);
        
        //判断时间间隔
        if (isset($task['interval']) && isset(self::$arr_worker[$tk]['time']) && time() - self::$arr_worker[$tk]['time'] < $task['interval']) {
            return 1;
        }
        
        if (!isset(self::$arr_worker[$tk])) {
            self::$arr_worker[$tk] = [];
            self::$arr_worker[$tk]['cnt'] = 0;
        }
        
        if (self::$arr_worker[$tk]['cnt'] < $task['count']) {
            $cnt = $task['count'] - self::$arr_worker[$tk]['cnt'];//计算还需启动的任务数量
        } else {//有足够的任务在运行则不再启动任务
            return 1;
        }
        //print_r(self::$arr_worker);
        /* 启动任务 */
        for ($i = 0; $i < $cnt; $i++) {
            $pid = pcntl_fork();
            //echo $pid."\n";
            if ( $pid == 0) {//error
                //检查是否切换目录
                if (isset($task['root']) && $task['root']) {
                    chdir($task['root']);
                }
                //echo $task['cmd']."\n";
                system($task['cmd']);
                exit();
            } elseif ($pid == -1) {
                echo "fork error!";
            } else {
                self::$arr_worker[$tk]['pid'][$pid] = $pid;
                self::$arr_worker[$tk]['cnt'] += 1;
                self::$arr_worker[$tk]['time'] = time();
            }
        }
    }
    
    /**
     * 回收任务
     */
    public static function wait()
    {
        while ( ($pid = pcntl_wait($status, WNOHANG)) > 0 ) {
            foreach (self::$arr_worker as $key => $worker) {
                if (isset($worker['pid'][$pid])) {
                    self::$arr_worker[$key]['cnt'] -= 1;
                }
            }
        }
    }
}
//启动task manager
Task::run();
