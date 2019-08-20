<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

use Workerman\Worker;
require_once __DIR__ . '/../../Workerman/Autoloader.php';

global $db;
$db = new Workerman\MySQL\Connection('10.255.255.209', '3306', 'root', '37zD7NhpC4','chehang168');

/**
 * 启动服务
 */
$queue = (new queue($argv));
// 生产者
$queue->servers();
// 消费者
$queue->consumer();

/*******************************************************************
 * 基于Worker实现的一个简单的消息队列服务
 * 服务分为两组进程，
 * 一组监听端口并把发来的数据放到sysv消息队列中
 * 另外一组进程为消费者，负责从队列中读取数据并处理
 * 
 * 注意：
 * 使用的是系统自带的 sysv 队列，即使队列服务重启数据也不会丢失
 * 但服务器重启后数据会丢失
 * 系统默认sysv队列容量比较小，可以根据需要配置Linux内核参数，
 * 增大队列容量
 *******************************************************************/
class queue
{
    /**
     * @var Workerman\Worker
     */
    public $server;

    /**
     * @var Workerman\Worker
     */
    public $consumer;

    /**
     * 端口
     */
    public static $IP = 'Text://0.0.0.0';

    /**
     * 端口
     */
    public static $PORT = 1236;

    /**
     * 队列的id。为了避免混淆，可以和监听的端口相同
     */
    public static $QUEUE_ID = 1236;

    public function __construct($argv)
    {
        // 队列名称
        if (!empty($argv[2])) {
            self::$QUEUE_ID = $argv[2];
        }

        // todo #######消息队列服务监听的端口##########
        $this->server = new Worker(self::$IP.":".self::$PORT);
        // 向哪个队列放数据
        $this->server->queueId = self::$QUEUE_ID;

        // todo ######## 消息队列消费者 ########
        $this->consumer = new Worker();
        // 消费的队列的id
        $this->consumer->queueId = self::$QUEUE_ID;
        // 慢任务，消费者的进程数可以开多一些
        $this->consumer->count = 32;

        if (!extension_loaded('sysvmsg')) {
            echo "Please install sysvmsg extension.\n";
            exit;
        }
    }

    /**
     * 生产者
     */
    public function servers()
    {
        /**
         * 进程启动时，初始化sysv消息队列
         */
        $server = $this->server;
        $server->onWorkerStart = function($server)
        {
            $server->queue = msg_get_queue($server->queueId);
        };

        /**
         * 服务接收到消息时，将消息写入系统的sysv消息队列，消费者从该队列中读取
         */
        $server->onMessage = function($connection, $message) use ($server)
        {
//            $msg_recver::$prefix = 'other';
            $msgtype = 1;
            $errorcode = 500;
            // @see http://php.net/manual/zh/function.msg-send.php
            if(extension_loaded('sysvmsg') && msg_send( $server->queue , $msgtype , $message, true , true , $errorcode))
            {
                $server::logs($message);
                return $connection->send('{"code":0, "msg":"success"}');
            }
            else
            {
                $server::logs('server fail');
                return $connection->send('{"code":'.$errorcode.', "msg":"fail"}');
            }
        };
    }

    /**
     * 消费者
     */
    public function consumer()
    {
        /**
         * 进程启动阻塞式的从队列中读取数据并处理
         */
        $consumer = $this->consumer;
        $consumer->onWorkerStart = function($consumer)
        {
            // 获得队列资源
            $consumer->queue = msg_get_queue($consumer->queueId);
            \Workerman\Lib\Timer::add(0.5, function() use ($consumer){
                if(extension_loaded('sysvmsg'))
                {
                    // 循环取数据
                    while(1)
                    {
                        $desiredmsgtype = 1;
                        $msgtype = 0;
                        $message = '';
                        $maxsize = 65535;
                        // 从队列中获取消息 @see http://php.net/manual/zh/function.msg-receive.php
                        @msg_receive($consumer->queue , $desiredmsgtype , $msgtype , $maxsize , $message, true, MSG_IPC_NOWAIT);
                        if(!$message)
                        {
                            return;
                        }
                        // 假设消息数据为json，格式类似{"class":"class_name", "method":"method_name", "args":[]}
                        $message = json_decode($message, true);
                        // 格式如果是正确的，则尝试执行对应的类方法
                        if(isset($message['class']) && isset($message['method']) && isset($message['args']))
                        {
                            // 要调用的类名，加上Consumer命名空间
                            $class_name = "\\Consumer\\".$message['class'];
                            // 要调用的方法名
                            $method = $message['method'];
                            // 调用参数，是个数组
                            $args = ($message['arrType'] == 1 ? $message['args'] : json_encode($message['args'], JSON_UNESCAPED_UNICODE));

                            // 类存在则尝试执行
                            if(class_exists($class_name))
                            {
                                $class = new $class_name;
                                $callback = array($class, $method);
                                if(is_callable($callback))
                                {
                                    $consumer::logs("$class_name::$method -> ".(is_array($args) ? json_encode($args, JSON_UNESCAPED_UNICODE) : $args));
                                    call_user_func_array($callback, (is_array($args) ? $args : [$args]));
                                }
                                else
                                {
                                    echo "$class_name::$method not exist\n";
                                    $consumer::logs("$class_name::$method not exist");
                                }
                            }
                            else
                            {
                                echo "$class_name not exist\n";
                                $consumer::logs("$class_name not exist");
                            }
                        }
                        else
                        {
                            echo "unknow message\n";
                            $consumer::logs('unknow message');
                        }
                    }
                }
            });
        };
    }
}

// 如果不是在根目录启动，则运行runAll方法
if(!defined('GLOBAL_START'))
{
    Worker::runAll();
}
