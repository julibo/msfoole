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
use Swoole\Process;
use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Cache;

abstract class Server
{

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
    protected $event = [ 'Start', 'Shutdown', 'WorkerStart', 'WorkerStop', 'WorkerExit', 'Connect', 'Receive', 'Packet', 'Close', 'BufferFull', 'BufferEmpty', 'Task', 'Finish', 'PipeMessage', 'WorkerError', 'ManagerStart', 'ManagerStop', 'Open', 'Message', 'HandShake', 'Request'];

    /**
     * 文件更新阈值
     * @var int
     */
    protected $lastMtime;

    /**
     * 全局缓存
     * @var Cache
     */
    public $cache;

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
        $this->lastMtime = time();
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

        # 开启全局缓存
        $cacheConfig = Config::get('cache.default') ?? [];
        $this->cache = new Cache($cacheConfig);

        // 文件变化监控进程
        $this->monitorProcess();

        // 补充逻辑
        $this->startLogic();
    }

    abstract protected function init();

    abstract protected function startLogic();

    /**
     * 文件监控，不包含配置变化
     */
    protected function monitorProcess()
    {
        $tableMonitor = false;
        if ($this->cache) {
            $cacheConfig = $this->cache->getConfig();
            if (strtolower($cacheConfig['driver']) == 'table') {
                $tableMonitor = true;
            }
        }
        $paths = Config::get('msfoole.monitor.path');
        if ($paths || $tableMonitor) {
            $mp = new Process(function (Process $process) use ($paths, $tableMonitor) {
                if ($paths) {
                    $timer = Config::get('msfoole.monitor.interval') ?? 10;
                    swoole_timer_tick($timer*1000, function () use($paths) {
                        foreach ($paths as $path) {
                            $dir      = new \RecursiveDirectoryIterator($path);
                            $iterator = new \RecursiveIteratorIterator($dir);

                            foreach ($iterator as $file) {
                                if (pathinfo($file, PATHINFO_EXTENSION) != 'php') {
                                    continue;
                                }

                                if ($this->lastMtime < $file->getMTime()) {
                                    $this->lastMtime = $file->getMTime();
                                    echo '[update]' . $file . " reload...\n";
                                    $this->swoole->reload();
                                    return;
                                }
                            }
                        }
                    });
                }
                if ($tableMonitor) {
                    swoole_timer_tick(60000, function () {
                        $timestamp = time();
                        foreach ($this->cache as $key => $val) {
                            if ($val['time'] > 0 && $val['time'] < $timestamp)
                            $this->del($key);
                        }
                    });
                }
            });

            $this->swoole->addProcess($mp);
        }
    }

}