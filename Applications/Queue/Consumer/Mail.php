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
namespace Consumer;

use Workerman\MySQL\Connection;
use Workerman\Worker;

/**
 * 消费者逻辑
 * @author walkor<walkor@workerman.net>
 */
class Mail
{
    /**
     * @var Connection
     */
    public $db;

    public function __construct()
    {
        global $db;
        $this->db = $db;
    }

    /**
     * 数据包格式： {"class":"Mail", "method":"send", "args":["xiaoming","xiaowang","hello"]}
     * @param string $from
     * @param string $to
     * @param string $content
     * @return void
     */
    public function send($from, $to, $content)
    {
        // 作为例子，代码省略
        sleep(5);
        echo "from:$from to:$to content:$content     mail send success\n";
    }
    
    public function read()
    {
        // ..
    }

    /**
     * 测试任务json模式
     * 数据包格式： {"class":"Mail", "method":"sendJson", "args":{"from":"xiaoming","to":"xiaowang","content":"hello"}}
     * @param string $param
     * @return void
     */
    public function sendJson($param)
    {
        // mysql 查询
        $res = $this->db->select(['name', 'title'])->from('ch_user')->where(['uid=1', 'name=100'])->row();
        echo $res['name']."\n";
        echo $res['title']."\n";
        Worker::logs(json_encode($res, JSON_UNESCAPED_UNICODE));

    }
}