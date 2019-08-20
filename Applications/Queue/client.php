<?php

if ($argv) {
    try {
        $result = client::send('Mail', 'sendJson', ['from' => "快乐", 'to' => "方法", 'content' => "hello"], '1.0.0');
        $result = client::send('Mail', 'send', ["ff", "gg", "hello"], '1.0.0', 1);
    } catch (Exception $e) {
        // 记录log日志
    }
    echo $result;
}


class client
{
    /**
     * 服务地址
     */
    const IP = 'tcp://127.0.0.1';

    /**
     * 端口号
     */
    const PORT = '1236';

    /**
     * 端口号
     */
    const TIMEOUT = 5;

    /**
     * 服务句柄
     */
    private static $fp = null;

    /**
     * 错误信息
     */
    private static $errno;
    private static $errstr;

    /**
     * 服务句柄
     *
     * @return object|mixed
     * @throws Exception
     */
    private static function getInstance()
    {
        if (self::$fp == null) {
            self::$fp = stream_socket_client(self:: IP.':'.self::PORT, self::$errno, self::$errstr, self::TIMEOUT);
            if (!self::$fp) {
                throw new Exception("stream_socket_client fail errno=".(self::$errno)." errstr=".self::$errstr);
            }
        }
        return self::$fp;
    }

    /**
     * rpc client 客户端
     *
     * @param string $class
     * @param string $method
     * @param string|array|mixed $params
     * @param string $version
     * @param integer $arrType
     * @return string|mixed
     * @throws Exception
     */
    public static function send(string $class='', string $method='', array $params=[], string $version='', int $arrType=0)
    {
        $ftcp = self::getInstance();
        $data = [
            'class' => $class,
            'version'   => $version,
            'method'    => $method,
            'args'    => $params,
            'logid'     => uniqid(),
            'spanid'    => 0,
            'arrType'    => $arrType,
        ];

        $data = json_encode($data, JSON_UNESCAPED_UNICODE)."\n";
        fwrite($ftcp, $data);
        $result = fread($ftcp, 8192);
//        fclose($ftcp);
        return $result;
    }
}