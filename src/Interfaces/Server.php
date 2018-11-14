<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole的简易微服务框架 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://julibo.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: carson <yuzhanwei@aliyun.com>
// +----------------------------------------------------------------------

namespace Julibo\Msfoole\Interfaces;

use Swoole\Http\Server as HttpServer;
use Swoole\Server as SwooleServer;
use Swoole\Websocket\Server as Websocket;

abstract class Server
{
    # const KEY = 'BXHospital201811';

    /**
     * Swoole对象
     * @var object
     */
    protected $swoole;

    /**
     * SwooleServer类型
     * @var string
     */
    protected $serverType = 'http';

    /**
     * Socket的类型
     * @var int
     */
    protected $sockType = SWOOLE_SOCK_TCP;

    /**
     * 运行模式
     * @var int
     */
    protected $mode = SWOOLE_PROCESS;

    /**
     * 监听地址
     * @var string
     */
    protected $host = '0.0.0.0';

    /**
     * 监听端口
     * @var int
     */
    protected $port = 9555;

    /**
     * 配置
     * @var array
     */
    protected $option = [];

    /**
     * 支持的响应事件
     * @var array
     */
    protected $event = [
        'Start',
        'Shutdown',
        'WorkerStop',
        'WorkerExit',
        'Connect',
        'Receive',
        'Packet',
        'Close',
        'BufferFull',
        'BufferEmpty',
        'Task',
        'Finish',
        'PipeMessage',
        'WorkerError',
        'ManagerStart',
        'ManagerStop',
        'Open',
        'Message',
        'HandShake',
        'Request',
    ];

    /**
     * todo
     * 解密字符串
     * @param $data
     * @param $iv
     * @return bool|string
     */
    public function encryptWithOpenssl($data, $iv)
    {
        return base64_decode(base64_encode($data), 'AES-128-CBC', self::KEY, $iv);
    }

    /**
     * todo
     * 加密字符串
     * @param $data
     * @param $iv
     * @return string
     */
    public function decryptWithOpenssl($data, $iv)
    {
        return openssl_decrypt(base64_encode($data), 'AES-128-CBC', self::KEY, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * 魔术方法，又不存在的操作时候执行
     * @param $method
     * @param $args
     */
    public function __call($method, $args)
    {
        call_user_func_array([$this->swoole, $method], $args);
    }

    final public function __construct($host, $port, $mode, $sockType, $option = [], $mix = false)
    {
        $this->host = $host;
        $this->port = $port;
        $this->mode = $mode;
        $this->sockType = $sockType;
        $this->option = $option;
        if ($mix && $this->serverType == 'http') {
            $this->serverType = 'socket';
        }
        
        // 实例化 Swoole 服务
        switch ($this->serverType) {
            case 'socket':
                $this->swoole = new Websocket($this->host, $this->port, $this->mode, $this->sockType);
                break;
            case 'server':
                $this->swoole = new SwooleServer($this->host, $this->port, $this->mode, $this->sockType);
                break;
            default:
                $this->swoole = new HttpServer($this->host, $this->port, $this->mode, $this->sockType);

        }

        // 初始化
        $this->init();

        // 设置参数
        if (!empty($this->option)) {
            $this->swoole->set($this->option);
        }

        // 设置回调
        foreach ($this->event as $event) {
            if (method_exists($this, "on{$event}")) {
                $this->swoole->on($event, [$this, "on{$event}"]);
            } else if ($this->serverType == 'socket' && method_exists($this, 'Websocketon' . $event)) {
                $this->swoole->on($event, [$this, 'Websocketon' . $event]);
            }
        }

        // 补充逻辑
        $this->startup();
    }

    abstract protected function init();

    abstract protected function startup();

}