<?php

/**
 * @example php task.php xxx.json
 * $task_config = [
 *     [
 *         'root' => "",
 *         'interval' => 1, 
 *         'cmd' => "sleep 100",
 *         'count' => 1,
 *     ],
 *     [
 *         'interval' => 1, 
 *         'cmd' => "sleep 120",
 *         'count' => 1,
 *     ]
 * ];
 * @var Ambiguous $conf_file
 * @desc this is a daemon manager. when we have a task must always exec or interval less than 1min, because crontab min interval is 1min
 * so it is not good when we want less than 1min. 
 * @author liyunfei
 */

if (system("ps -ef|grep -v '/bin/sh'|grep -v lockf|grep -v grep|grep ".$argv[0]."|wc -l") > 1){
    exit('已经有任务在进行中');
}

$conf_file = isset($argv[1]) ? $argv[1] : 'task_config.json';

function parseConfig($conf_file) 
{
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

// $root = dirname(dirname(__DIR__));

// chdir($root);

$arr_worker = [];

while (1) {
    $task_config = parseConfig($conf_file);//简单的热加载
    if (!$task_config) {
        sleep(1);
        continue;
    }
    
    foreach ($task_config as $task) {
        //没有任务则跳过
        if (!isset($task['cmd']) || $task['cmd'] == '') {
            continue;
        }
        
        $tk = md5($task['cmd']);
        
        //判断时间间隔
        if (isset($task['interval']) && isset($arr_worker[$tk]['time']) && time() - $arr_worker[$tk]['time'] < $task['interval']) {
            continue;
        }
        
        if (!isset($arr_worker[$tk])) {
            $arr_worker[$tk] = [];
            $arr_worker[$tk]['cnt'] = 0;
        }
        
        if ($arr_worker[$tk]['cnt'] < $task['count']) {
            $cnt = $task['count'] - $arr_worker[$tk]['cnt'];//计算还需启动的任务数量
        } else {//有足够的任务在运行则不再启动任务
            continue;
        }
        /* 启动任务 */
        for ($i = 0; $i < $cnt; $i++) {
            $pid = pcntl_fork();
            echo $pid."\n";
            if ( $pid == 0) {//error
                //检查是否切换目录
                if (isset($task['root'])) {
                    chdir($task['root']);
                }
                //echo $task['cmd']."\n";
                system($task['cmd']);
            } elseif ($pid == -1) {
                echo "fork error!";
            } else {
                $arr_worker[$tk]['pid'][] = $pid;
                $arr_worker[$tk]['cnt'] += 1;
                $arr_worker[$tk]['time'] = time();
            }
        }
    }
    
    //回收执行完成的任务
    while ( ($pid = pcntl_wait($status, WNOHANG)) > 0 ) {
        foreach ($arr_worker as $key => $worker) {
            if (in_array($pid, $worker['pid'])) {
                $arr_worker[$key]['cnt'] -= 1;
            }
        }
    }
    //echo "1\n";
    sleep(1);
}
