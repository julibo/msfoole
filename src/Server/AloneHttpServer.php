<?php
// +----------------------------------------------------------------------
// | msfoole [ 基于swoole的多进程API服务框架 ]
// +----------------------------------------------------------------------
// | Copyright (c) 2018 http://julibo.com All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: carson <yuzhanwei@aliyun.com>
// +----------------------------------------------------------------------

namespace Julibo\Msfoole\Server;

use Julibo\Msfoole\Helper;
use Swoole\Http\Request as SwooleRequest;
use Swoole\Http\Response as SwooleResponse;
use Swoole\Websocket\Server as Websocket;
use Swoole\WebSocket\Frame as Webframe;
use Swoole\Process;
use Swoole\Table;
use Julibo\Msfoole\Application;
use Julibo\Msfoole\Facade\Config;
use Julibo\Msfoole\Facade\Log;
use Julibo\Msfoole\Cache;
use Julibo\Msfoole\Channel;
use Julibo\Msfoole\Interfaces\Server as BaseServer;

class AloneHttpServer extends BaseServer
{
    /**
     * SwooleServer类型
     * @var string
     */
    protected $serverType = 'http';

    /**
     * 支持的响应事件
     * @var array
     */
    protected $event = [
        'Start',
        'Shutdown',
        'ManagerStart',
        'ManagerStop',
        'WorkerStart',
        'WorkerStop',
        'WorkerExit',
        'WorkerError',
        'Close',
        'Open',
        'Message',
        'Request'
    ];

    /**
     * 应用服务
     * @var
     */
    protected $app;

    /**
     * 客户端连接内存表
     */
    protected $table;

    /**
     * 全局缓存
     * @var Cache
     */
    protected $cache;

    /**
     * 初始化
     */
    protected function init()
    {
        $this->option['upload_tmp_dir'] = TEMP_PATH;
        $this->option['http_parse_post'] = true;
        $this->config = array_merge($this->config, Config::get('msfoole') ?? []);
    }

    /**
     * 启动辅助逻辑
     */
    protected function startLogic()
    {
        # 创建全局队列
        $channelSwitch = $this->config['channel']['switch'] ?? false;
        if ($channelSwitch) {
            Channel::instance($this->config['channel']['size'] ?? 65536);
            $this->workingPool($this->config['channel']['pool'] ?? 1);
        }
        # 创建客户端连接内存表
        if ($this->serverType == 'socket') {
            $this->createTable();
        }
        # 开启全局缓存
        $cacheConfig = Config::get('cache.default') ?? [];
        $this->cache = new Cache($cacheConfig);
        # 开启异步定时监控
        $this->monitorProcess();
    }

    /**
     * 创建客户端连接内存表
     */
    private function createTable()
    {
        $this->table = new table($this->config['table']['size'] ?? 1024);
        $this->table->column('token', table::TYPE_STRING, 32);
        $this->table->column('counter', table::TYPE_INT, 4);
        $this->table->column('create_time', table::TYPE_INT, 4);
        $this->table->column('last_time', table::TYPE_INT, 4);
        $this->table->column('user_info', table::TYPE_STRING, 1024);
        $this->table->create();
    }

    /**
     * 创建队列工作池
     * @param int $num
     */
    private function workingPool(int $num = 1)
    {
        $pool = [];
        for ($i = 0; $i < $num; $i++) {
            $pool[$i] = new Process(function (Process $process) {
                Helper::setProcessTitle("msfoole:pool");
                // 初始化日志
                $logConfig = Config::get("log") ?? [];
                Log::init($logConfig);
                do {
                    $data = Channel::instance()->pop();
                    if (!empty($data)) {
                        switch ($data['type']) {
                            case 2:
                                // 执行自定义方法
                                if ($data['class'] && $data['method']) {
                                    $parameter = $data['parameter'] ?? [];
                                    call_user_func_array([$data['class'], $data['method']], $parameter);
                                }
                                break;
                            case 1;
                                // 发送广播
                                foreach($this->table as $fd => $row)
                                {
                                    if ($row['token'] == $data['client']) {
                                        $this->swoole->push($fd, json_encode($data));
                                        break;
                                    }
                                }
                                break;
                            default:
                                // 日志记录
                                if (!empty($data['log'])) {
                                    Log::saveData($data['log']);
                                }
                                break;
                        }
                    } else {
                        sleep(1);
                    }
                } while (true);
            });
            $this->swoole->addProcess($pool[$i]);
        }
    }

    /**
     * 文件监控，不包含配置变化
     * table内存表监控
     */
    private function monitorProcess()
    {
        $tableMonitor = false;
        if ($this->cache) {
            $driver = $this->cache->getDriver();
            if (strtolower($driver) == 'table') {
                $tableMonitor = true;
            }
        }
        $paths = $this->config['monitor']['path'] ?? null;
        if ($paths || $tableMonitor) {
            $monitor = new Process(function (Process $process) use ($paths, $tableMonitor) {
                Helper::setProcessTitle("msfoole:monitor");
                if ($tableMonitor) {
                    $rate = $this->config['cache']['rate'] ?? 1;
                    swoole_timer_tick($rate * 60000, function () {
                        $timestamp = time();
                        $table = $this->cache->getTable();
                        foreach ($table as $key => $val) {
                            if ($val['time'] > 0 && $val['time'] < $timestamp)
                                $table->del($key);
                        }
                    });
                }
                if ($paths) {
                    $timer = $this->config['monitor']['interval'] ?? 10;
                    swoole_timer_tick($timer * 1000, function () use($paths) {
                        if (!is_array($paths)) {
                            $paths = array($paths);
                        }
                        foreach ($paths as $path) {
                            $path = ROOT_PATH . $path;
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
                                    break 2;
                                }
                            }
                        }
                    });
                }
            });
            $this->swoole->addProcess($monitor);
        }
    }

    public function onStart(\Swoole\Server $server)
    {
        Helper::setProcessTitle("msfoole:master");
    }

    public function onShutdown(\Swoole\Server $server)
    {
        // echo "主进程结束";
        Helper::sendDingRobotTxt("主进程结束");
    }

    public function onManagerStart(\Swoole\Server $server)
    {
        Helper::setProcessTitle("msfoole:manager");
    }

    public function onManagerStop(\Swoole\Server $server)
    {
        // echo "管理进程停止";
        Helper::sendDingRobotTxt("管理进程停止");
    }

    public function onWorkerStart(\Swoole\Server $server, int $worker_id)
    {
        Helper::setProcessTitle("msfoole:worker");
        // 初始化日志
        $logConfig = Config::get("log") ?? [];
        Log::init($logConfig);
        // 应用实例化
        $this->app = new Application();
        // Swoole Server保存到容器
        $this->app->swoole = $server;
        $this->app->cache = $this->cache;
        if ($this->table) {
            $this->app->table = $this->table;
        }
    }

    public function onWorkerStop(\Swoole\Server $server, int $worker_id)
    {
        // echo "worker进程终止";
        Helper::sendDingRobotTxt("worker进程终止");
    }

    public function onWorkerExit(\Swoole\Server $server, int $worker_id)
    {
        // echo "worker进程退出";
        Helper::sendDingRobotTxt("worker进程退出");
    }

    public function onWorkerError(\Swoole\Server $serv, int $worker_id, int $worker_pid, int $exit_code, int $signal)
    {
        $error = sprintf("worker进程异常:[%d] %d 退出的状态码为%d, 退出的信号为%d", $worker_pid, $worker_id, $exit_code, $signal);
        // echo $error;
        Helper::sendDingRobotTxt($error);
    }

    public function onClose(\Swoole\Server $server, int $fd, int $reactorId)
    {
        // 销毁内存表记录
        if (!is_null($this->table) && $this->table->exist($fd)) {
            $this->table->del($fd);
        }
    }

    /**
     * request回调
     * @param $request
     * @param $response
     */
    public function onRequest(SwooleRequest $request, SwooleResponse $response)
    {
        // 执行应用并响应
        // print_r($request);
        $uri = $request->server['request_uri'];
        if ($uri == '/favicon.ico') {
            $response->status(404);
            $response->end();
        } else {
            if (isset($request->header['origin'])) {
                $origin = true;
                if (is_array(Config::get('application.access.origin'))) {
                    in_array($request->header['origin'], Config::get('application.access.origin')) ? : $origin = false;
                } else {
                    $request->header['origin'] == Config::get('application.access.origin') ? : $origin = false;
                }
                if ($origin) {
                    $response->header('Access-Control-Allow-Origin', $request->header['origin']);
                    $response->header('Access-Control-Allow-Credentials', 'true');
                    $response->header('Access-Control-Max-Age', '3600');
                    $response->header('Access-Control-Allow-Headers', 'Content-Type, Cookie, token, timestamp, level, signstr, identification_code');
                    $response->header("Access-Control-Allow-Methods", "GET,POST,OPTIONS");
                }
            }
            if ($request->server['request_method'] == 'OPTIONS') {
                $response->status(http_response_code());
                $response->end();
            } else {
                $this->app->initialize();
                $this->app->swooleHttp($request, $response);
                $this->app->destruct();
            }
        }
    }

    /**
     * 连接开启回调
     * @param Websocket $server
     * @param SwooleRequest $request
     */
    public function WebsocketonOpen(Websocket $server, SwooleRequest $request)
    {
        // 开启websocket连接
        // print_r($request);
        $this->app->swooleWebSocketOpen($server, $request);
    }

    /**
     * Message回调
     * @param $server
     * @param $frame
     */
    public function WebsocketonMessage(Websocket $server, Webframe $frame)
    {
        // 执行应用并响应
        // print_r("receive from {$frame->fd}:{$frame->data},opcode:{$frame->opcode},fin:{$frame->finish}");
        $this->app->initialize();
        $this->app->swooleWebSocket($server, $frame);
        $this->app->destruct();
    }
}
